<?php
/**
 * sem_fixes_admin
 *
 * @package Semiologic Fixes
 **/

# http://core.trac.wordpress.org/ticket/4298
add_filter('content_save_pre', array('sem_fixes_admin', 'fix_wpautop'), 0);
add_filter('excerpt_save_pre', array('sem_fixes_admin', 'fix_wpautop'), 0);
add_filter('pre_term_description', array('sem_fixes_admin', 'fix_wpautop'), 0);
add_filter('pre_user_description', array('sem_fixes_admin', 'fix_wpautop'), 0);
add_filter('pre_link_description', array('sem_fixes_admin', 'fix_wpautop'), 0);

# http://core.trac.wordpress.org/ticket/9843
if ( !defined('WP_POST_REVISIONS') || WP_POST_REVISIONS )
	add_action('save_post', array('sem_fixes_admin', 'save_post_revision'));

# http://core.trac.wordpress.org/ticket/9876
add_action('admin_menu', array('sem_fixes_admin', 'sort_admin_menu'), 1000000);

# tinymce
add_filter('tiny_mce_before_init', array('sem_fixes_admin', 'editor_options'), -1000);
add_filter('mce_external_plugins', array('sem_fixes_admin', 'editor_plugin'), 1000);
add_filter('mce_buttons', array('sem_fixes_admin', 'editor_buttons'), -1000);
add_filter('mce_buttons_2', array('sem_fixes_admin', 'editor_buttons_2'), -1000);
add_filter('mce_buttons_3', array('sem_fixes_admin', 'editor_buttons_3'), -1000);
add_filter('mce_buttons_4', array('sem_fixes_admin', 'editor_buttons_4'), -1000);

class sem_fixes_admin {
	/**
	 * fix_wpautop()
	 *
	 * @param string $content
	 * @return string $content
	 **/

	function fix_wpautop($content) {
		$content = str_replace(array("\r\n", "\r"), "\n", $content);
		
		while ( preg_match("/<[^<>]*\n/", $content) ) {
			$content = preg_replace("/(<[^<>]*)\n+/", "$1", $content);
		}
		
		return $content;
	} # fix_wpautop()
	
	
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
			$to_sort = array();
			while ( $_data = array_pop($data) ) {
				// Default WP items don't have $data[3] title set
				if ( isset($_data[3]) ) {
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
} # sem_fixes_admin
?>