<?php
/**
 * sem_fixes_admin
 *
 * @package Semiologic Fixes
 **/

class sem_fixes_admin {
	/**
	 * fix_wpautop()
	 *
	 * @param string $content
	 * @return string $content
	 **/

	function fix_wpautop($content) {
		$content = str_replace(array("\r\n", "\r"), "\n", $content);
		
		if ( !preg_match("/<[a-z][^<>]*\n/i", $content) )
			return $content;
		
		global $sem_fixes_escape;
		$sem_fixes_escape = array();
		
		$content = preg_replace_callback("/<\?php.+\?>/is", array('sem_fixes_admin', 'escape_php_callback'), $content);
		
		while ( preg_match("/<[a-z][^<>]*\n/i", $content) ) {
			$content = preg_replace("/(<[a-z][^<>]*)\n+/i", "$1", $content);
		}
		
		if ( $sem_fixes_escape )
			$content = str_replace(array_keys($sem_fixes_escape), array_values($sem_fixes_escape), $content);
		
		return $content;
	} # fix_wpautop()
	
	
	/**
	 * escape_php_callback()
	 *
	 * @param array $match
	 * @return string $out
	 **/

	function escape_php_callback($match) {
		global $sem_fixes_escape;
		
		$tag_id = "----sem_fixes_escape:" . md5($match[0]) . "----";
		$sem_fixes_escape[$tag_id] = $match[0];
		
		return $tag_id;
	} # escape_php_callback()
	
	
	/**
	 * fix_tinymce_junk()
	 *
	 * @param string $content
	 * @return string $content
	 **/

	function fix_tinymce_junk($content) {
		if ( strpos($content, '_mcePaste') === false )
			return $content;
		
		$content = stripslashes($content);
		
		do {
			$old_content = $content;
			$content = preg_replace_callback("~(<div id=\"_mcePaste\".*?>)(.*?)(</div>)~is", array('sem_fixes_admin', 'fix_tinymce_paste_callback'), $old_content);
		} while ( $content && $content != $old_content );
		
		# http://digitizor.com/2009/08/26/how-to-fix-the-gwproxy-jsproxy/
		$content = preg_replace(array('~<input id="gwProxy" type="hidden" />~', '~<input id="jsProxy" onclick="jsCall();" type="hidden" />~'), '', $content);
		
		global $wpdb;
		$content = $wpdb->escape($content);
		
		return $content;
	} # fix_tinymce_junk()
	
	
	/**
	 * fix_tinymce_paste_callback()
	 *
	 * @param array $match
	 * @return string $output
	 **/
	
	function fix_tinymce_paste_callback($match) {
		$content = $match[2];
		
		if ( !$content || stripos($content, '<div') === false )
			return '';
		
		$content .= $match[3];
		do {
			$old_content = $content;
			$content = preg_replace("~<div.*?>.*?</div>~is", '', $old_content);
		} while ( $content && $content != $old_content );
		
		return $match[1] . $content;
	} # fix_tinymce_paste_callback()
	
	
	/**
	 * get_comment_9935()
	 * 
	 * @see http://core.trac.wordpress.org/ticket/9935
	 * 
	 * @param  stdclass $comment comment to gets.
	 * @return stdclass comment to be edited
	 */
	function get_comment_9935($comment) {
		static $once = true;
		
		if ($once) {
			$once = false;
			$comment = get_comment_to_edit($comment->comment_ID);			
		}
		
		return $comment;								
	} # get_comment_9935()
	
	
	/**
	 * save_post_revision()
	 *
	 * @param int $rev_id
	 * @return void
	 **/
	
	function save_post_revision($rev_id) {
		if ( wp_is_post_autosave($rev_id) ) {
			return;
		} elseif ( $post_id = wp_is_post_revision($rev_id) ) {
			# do nothing
		} else {
			$post_id = $rev_id;
		}
		
		global $wpdb;
		$post = get_post($rev_id);
		
		# drop dup revs
		$kill_ids = $wpdb->get_col("
			SELECT	ID
			FROM	$wpdb->posts
			WHERE	post_type = 'revision'
			AND		ID <> " . intval($rev_id) . "
			AND		post_parent = " . intval($post_id) . "
			AND		post_content = '" . $wpdb->escape($post->post_content) . "'
			");
		
		foreach ( $kill_ids as $kill_id )
			wp_delete_post_revision($kill_id);
		
		# stop here for real posts
		if ( $post_id == $rev_id )
			return;
		
		# drop other potential dup revs
		$kill_ids = $wpdb->get_col("
			SELECT	p2.ID
			FROM	$wpdb->posts as p2
			JOIN	$wpdb->posts as p1
			ON		p1.post_parent = p2.post_parent
			AND		p1.post_type = p2.post_type
			WHERE	p1.post_type = 'revision'
			AND		p1.post_parent = " . intval($post_id) . "
			AND		p1.post_content = p2.post_content
			AND		p1.ID > p2.ID
			");

		foreach ( $kill_ids as $kill_id )
			wp_delete_post_revision($kill_id);
		
		# drop near-empty revs
		$kill_ids = $wpdb->get_col("
			SELECT	ID
			FROM	$wpdb->posts
			WHERE	post_type = 'revision'
			AND		post_parent = " . intval($post_id) . "
			AND		LENGTH(post_content) <= 50
			");
		
		foreach ( $kill_ids as $kill_id )
			wp_delete_post_revision($kill_id);
		
		# drop adjascent revs
		$kill_ids = $wpdb->get_col("
			SELECT	p2.ID
			FROM	$wpdb->posts as p2
			JOIN	$wpdb->posts as p1
			ON		p1.post_parent = p2.post_parent
			AND		p1.post_type = p2.post_type
			WHERE	p1.post_type = 'revision'
			AND		p1.post_parent = " . intval($post_id) . "
			AND		DATEDIFF(p1.post_date, p2.post_date) < 1
			AND		p1.post_date >= p2.post_date
			AND		p1.ID <> p2.ID
			");

		foreach ( $kill_ids as $kill_id )
			wp_delete_post_revision($kill_id);
		
		# drop near-identical revs
		$kill_ids = $wpdb->get_col("
			SELECT	p2.ID
			FROM	$wpdb->posts as p2
			JOIN	$wpdb->posts as p1
			ON		p1.post_parent = p2.post_parent
			AND		p1.post_type = p2.post_type
			WHERE	p1.post_type = 'revision'
			AND		p1.post_parent = " . intval($post_id) . "
			AND		DATEDIFF(p1.post_date, p2.post_date) <= 7
			AND		ABS( LENGTH(p1.post_content) - LENGTH(p2.post_content) ) <= 50
			AND		p1.post_date >= p2.post_date
			AND		p1.ID <> p2.ID
			");

		foreach ( $kill_ids as $kill_id )
			wp_delete_post_revision($kill_id);
	} # save_post_revision()
	
	
	/**
	 * sort_admin_menu()
	 *
	 * @return void
	 **/

	function sort_admin_menu() {
		global $submenu;
		
		foreach ( $submenu as $id => $data ) {
			if ( !in_array($id, array('index.php', 'edit.php', 'upload.php', 'link-manager.php', 'edit-pages.php', 'edit-comments.php', 'themes.php', 'plugins.php', 'users.php', 'tools.php', 'options-general.php')) )
				continue;
			
			$to_sort = array();
			while ( $_data = array_pop($data) ) {
				// Default WP items don't have $data[3] title set
				if ( !empty($_data[3]) ) {
					$to_sort[] = $_data;
				} else {
					$data[] = $_data;
					break;
				}
			}
			usort($to_sort, array('sem_fixes_admin', 'strnatcasecmp_submenu'));
			$data = array_merge($data, $to_sort);
			$submenu[$id] = $data;
		}
	} # sort_admin_menu()
	
	
	/**
	 * strnatcasecmp_submenu()
	 *
	 * @param submenu item $a
	 * @param submenu item $b
	 * @return -1|0|1
	 **/
	
	function strnatcasecmp_submenu($a, $b) {
		return strnatcasecmp($a[0], $b[0]);
	} # strnatcasecmp_submenu()
	
	
	/**
	 * editor_options()
	 *
	 * @param array $init
	 * @return array $init
	 **/
	
	function editor_options($init) {
		$init['wordpress_adv_hidden'] = false;
		
		return $init;
	} # editor_options()
	
	
	/**
	 * editor_plugin()
	 *
	 * @param array $plugin_array
	 * @return array $plugin_array
	 **/
	
	function editor_plugin($plugin_array) {
		if ( get_user_option('rich_editing') == 'true') {
			$folder = plugin_dir_url(__FILE__);
			
			foreach ( array('advlink', 'emotions', 'searchreplace', 'table') as $plugin ) {
				$file = $folder . 'tinymce/' . $plugin . '/editor_plugin.js';
				$plugin_array[$plugin] = $file;
			}
		}

		return $plugin_array;
	} # editor_plugin()
	
	
	/**
	 * editor_buttons()
	 *
	 * @param array $buttons
	 * @return array $buttons
	 **/
	
	function editor_buttons($buttons) {
		return array(
			'bold', 'italic', 'underline', 'strikethrough', '|',
			'bullist', 'numlist', 'blockquote', '|',
			'outdent', 'indent', '|',
			'justifyleft', 'justifycenter', 'justifyright', 'justifyfull', '|',
			'link', 'unlink', 'anchor',
			);
	} # editor_buttons()
	
	
	/**
	 * editor_buttons_2()
	 *
	 * @param array $buttons
	 * @return array $buttons
	 **/
	
	function editor_buttons_2($buttons) {
		return array(
			'formatselect', 'fontselect', 'fontsizeselect', 'forecolor', 'backcolor', '|',
			'media', 'emotions', 'charmap',
			);
	} # editor_buttons_2()
	
	
	/**
	 * editor_buttons_3()
	 *
	 * @param array $buttons
	 * @return array $buttons
	 **/
	
	function editor_buttons_3($buttons) {
		return array(
			'tablecontrols', '|',
			'wp_more', 'wp_page', '|',
			'spellchecker','|',
			'wp_help',
			);
	} # editor_buttons_3()
	
	
	/**
	 * editor_buttons_3()
	 *
	 * @param array $buttons
	 * @return array $buttons
	 **/
	
	function editor_buttons_4($buttons) {
		return array(
			'pastetext', 'pasteword', 'removeformat', '|',
			'search', 'replace', '|',
			'undo', 'redo', '|',
			'fullscreen', 
			);
	} # editor_buttons_4()
	
	
	/**
	 * admin_print_scripts()
	 *
	 * @return void
	 **/

	function admin_print_scripts() {
		$folder = plugin_dir_url(__FILE__);
		wp_deregister_script('common');
		wp_deregister_script('admin-widgets');
		wp_register_script('common', $folder . 'js/common.js', array('jquery', 'hoverIntent', 'utils'), '20090815', true);
		wp_register_script('admin-widgets', $folder . 'js/widgets.js', array('jquery-ui-sortable', 'jquery-ui-draggable', 'jquery-ui-droppable'), '20090815', true);
	} # admin_print_scripts()
	
	
	/**
	 * admin_print_styles()
	 *
	 * @return void
	 **/

	function admin_print_styles() {
		global $editing;
		if ( !$editing )
			return;
		
		echo <<<EOS

<style type="text/css">
#parent_id {
	width: 100%;
}
</style>

EOS;
	} # admin_print_styles()
} # sem_fixes_admin

if ( !function_exists('add_theme_support') ) { // WP 2.9
	# http://core.trac.wordpress.org/ticket/9935
	add_action('load-edit-comments.php', create_function('', "add_action('get_comment', array('sem_fixes_admin', 'get_comment_9935'));"));
}

# http://core.trac.wordpress.org/ticket/10851
add_filter('content_save_pre', array('sem_fixes_admin', 'fix_tinymce_junk'), 0);

# http://core.trac.wordpress.org/ticket/4298
add_filter('content_save_pre', array('sem_fixes_admin', 'fix_wpautop'), 0);
add_filter('excerpt_save_pre', array('sem_fixes_admin', 'fix_wpautop'), 0);
add_filter('pre_term_description', array('sem_fixes_admin', 'fix_wpautop'), 0);
add_filter('pre_user_description', array('sem_fixes_admin', 'fix_wpautop'), 0);
add_filter('pre_link_description', array('sem_fixes_admin', 'fix_wpautop'), 0);

# http://core.trac.wordpress.org/ticket/9843
if ( !defined('WP_POST_REVISIONS') || WP_POST_REVISIONS )
	add_action('save_post', array('sem_fixes_admin', 'save_post_revision'), 1000000);

# http://core.trac.wordpress.org/ticket/9876
add_action('admin_menu', array('sem_fixes_admin', 'sort_admin_menu'), 1000000);

# tinymce
add_filter('tiny_mce_before_init', array('sem_fixes_admin', 'editor_options'), -1000);
add_filter('mce_external_plugins', array('sem_fixes_admin', 'editor_plugin'), 1000);
add_filter('mce_buttons', array('sem_fixes_admin', 'editor_buttons'), -1000);
add_filter('mce_buttons_2', array('sem_fixes_admin', 'editor_buttons_2'), -1000);
add_filter('mce_buttons_3', array('sem_fixes_admin', 'editor_buttons_3'), -1000);
add_filter('mce_buttons_4', array('sem_fixes_admin', 'editor_buttons_4'), -1000);

# scripts and styles
add_action('admin_print_scripts', array('sem_fixes_admin', 'admin_print_scripts'));
add_action('admin_print_styles', array('sem_fixes_admin', 'admin_print_styles'));
?>