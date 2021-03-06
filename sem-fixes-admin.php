<?php
/**
 * sem_fixes_admin
 *
 * @package Semiologic Fixes
 **/


class sem_fixes_admin {
	/**
	 * Plugin instance.
	 *
	 * @see get_instance()
	 * @type object
	 */
	protected static $instance = NULL;

	/**
	 * URL to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_url = '';

	/**
	 * Path to this plugin's directory.
	 *
	 * @type string
	 */
	public $plugin_path = '';

	/**
	 * Access this plugin’s working instance
	 *
	 * @wp-hook plugins_loaded
	 * @return  object of this class
	 */
	public static function get_instance()
	{
		NULL === self::$instance and self::$instance = new self;

		return self::$instance;
	}


	/**
	 * Loads translation file.
	 *
	 * Accessible to other classes to load different language files (admin and
	 * front-end for example).
	 *
	 * @wp-hook init
	 * @param   string $domain
	 * @return  void
	 */
	public function load_language( $domain )
	{
		load_plugin_textdomain(
			$domain,
			FALSE,
			dirname(plugin_basename(__FILE__)) . '/lang'
		);
	}

	/**
	 * Constructor.
	 *
	 *
	 */

	public function __construct() {
		$this->plugin_url    = plugins_url( '/', __FILE__ );
		$this->plugin_path   = plugin_dir_path( __FILE__ );
		$this->load_language( 'sem-fixes' );

		add_action( 'plugins_loaded', array ( $this, 'init' ) );
    }

	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		// more stuff: register actions and filters
		$version = get_option('sem_fixes_version');
		if ( ( $version === false || version_compare( $version, sem_fixes_version, '<' ) ) )
	        add_action('admin_init', array($this, 'upgrade'));

		# http://core.trac.wordpress.org/ticket/4298
/*		add_filter('content_save_pre', array($this, 'fix_wpautop'), 0);
		add_filter('excerpt_save_pre', array($this, 'fix_wpautop'), 0);
		add_filter('pre_term_description', array($this, 'fix_wpautop'), 0);
		add_filter('pre_user_description', array($this, 'fix_wpautop'), 0);
		add_filter('pre_link_description', array($this, 'fix_wpautop'), 0);
*/

		if ( !defined( 'WP_POST_REVISIONS' ) )
			define( 'WP_POST_REVISIONS', 5 );

		if ( !defined( 'AUTOSAVE_INTERVAL' )  )
			define( 'AUTOSAVE_INTERVAL', 300 );

		# http://core.trac.wordpress.org/ticket/9876
		add_action('admin_menu', array($this, 'sort_admin_menu'), 1000000);

		# scripts and styles
		add_action('admin_enqueue_scripts', array($this, 'admin_print_scripts'));
		add_action('admin_enqueue_scripts', array($this, 'admin_print_styles'));

		# fix customizer not display sidebar widgets
//		add_action('customize_controls_print_scripts', array($this, 'admin_customizer_styles'));

		# http://core.trac.wordpress.org/ticket/9874
		add_filter('tiny_mce_before_init', array($this, 'tiny_mce_config'));

		# no emoji stuff
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );

		$this->load_modules();
	}


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
		
		$content = preg_replace_callback(
			array("/<\?php.+?\?>/is", "/<!\[CDATA\[.*?\]\]>/"),
			array($this, 'escape_php_callback'),
			$content);
		
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
			usort($to_sort, array($this, 'strnatcasecmp_submenu'));
			$data = array_merge($data, $to_sort);
			$submenu[$id] = $data;
		}
	} # sort_admin_menu()


    /**
     * strnatcasecmp_submenu()
     *
     * @param submenu item $a
     * @param submenu item $b
     * @return int -1|0|1
     */
	
	function strnatcasecmp_submenu($a, $b) {
		return strnatcasecmp($a[0], $b[0]);
	} # strnatcasecmp_submenu()

	
	/**
	 * admin_print_scripts()
	 *
	 * @return void
	 **/

	function admin_print_scripts() {
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
	

	/**
	 * admin_customizer_styles()
	 *
	 * @return void
	 **/

	function admin_customizer_styles() {
		echo <<<EOS
<style type="text/css">
.control-section[id^="accordion-section-sidebar-widgets-"] {
	display: list-item !important;
	height: auto !important;
}
</style>

EOS;
	} # admin_customizer_styles()


	/**
	 * load_modules()
	 *
	 * @return void
	 **/

	function load_modules() {
		if ( !function_exists('wpguy_category_order_menu') )
			include_once dirname(__FILE__) . '/inc/category-order.php';

		if ( !function_exists('mypageorder_menu') )
			include_once dirname(__FILE__) . '/inc/mypageorder.php';

	} # load_modules()

	/**
	 * tiny_mce_config()
	 *
	 * @param array $o
	 * @return array $o
	 **/

	function tiny_mce_config($o) {
		# http://forum.semiologic.com/discussion/4807/iframe-code-disappears-switching-visualhtml/
		# http://wiki.moxiecode.com/index.php/TinyMCE:Configuration/valid_elements#Full_XHTML_rule_set
		# assume the stuff below is properly set if they exist already

		if ( current_user_can('unfiltered_html') ) {
			if ( !isset($o['extended_valid_elements']) ) {
				$elts = array();

				$elts[] = "iframe[align<bottom?left?middle?right?top|class|frameborder|height|id"
					. "|longdesc|marginheight|marginwidth|name|scrolling<auto?no?yes|src|style"
					. "|title|width]";

				$elts = implode(',', $elts);

				$o['extended_valid_elements'] = $elts;
			}
		} else {
			if ( !isset($o['invalid_elements']) ) {
				$elts = array();

				$elts[] = "iframe";
				$elts[] = "script";
				$elts[] = "form";
				$elts[] = "input";
				$elts[] = "button";
				$elts[] = "textarea";

				$elts = implode(',', $elts);

				$o['invalid_elements'] = $elts;
			}
		}

		return $o;
	} # tiny_mce_config()

	/**
	 * upgrade()
	 *
	 * @return void
	 **/

	function upgrade() {

		$version = get_option('sem_fixes_version');
		if ( ( $version === false || version_compare( $version, '2.6', '<' ) ) )
			$this->upgrade_tadv();

		update_option( 'sem_fixes_version', sem_fixes_version );
	}


	/**
	 * upgrade_tadv()
	 *
	 * @return void
	 **/

	function upgrade_tadv() {

		$tadv_settings = get_option( 'tadv_settings' );

		if ( !empty( $tadv_settings ) ) {
			$tadv_settings['toolbar_1'] = implode( ',', array(
				'bold', 'italic', 'underline', 'strikethrough',
	            'sup', 'sub', 'blockquote',
				'outdent', 'indent',
				'alignleft', 'aligncenter', 'alignright', 'alignjustify',
				'link', 'unlink', 'anchor', 'image', 'wp_adv',
				) );

			$tadv_settings['toolbar_2'] = implode( ',', array(
				'formatselect', 'fontselect', 'fontsizeselect', 'styleselect',
				'forecolor', 'backcolor', 'removeformat',
				) );

			$tadv_settings['toolbar_3'] = implode( ',', array(
				'bullist', 'numlist',
                'pastetext', 'paste',
				'searchreplace',
				'undo', 'redo', 'table',
			) );

			$tadv_settings['toolbar_4'] = implode( ',', array(
				'wp_more', 'wp_page', 'hr', 'nonbreaking', 'emoticons', 'charmap',
				'wp_help', 'fullscreen',
			) );

			$tadv_settings['options'] = implode( ',', array(
				'menubar', 'advlink', 'advimage', 'contextmenu',
				'advlist', ) );

			update_option( 'tadv_settings', $tadv_settings );

		}
		else {		// pre TADV 4.0
			$tadv_buts = get_option( 'tadv_btns4' );
			if ( $tadv_buts === false || empty( $tadv_buts ) ) {
				$tadv_btns1 = array(
					'bold', 'italic', 'underline', 'strikethrough', '|',
		            'sup', 'sub', 'blockquote', '|',
					'outdent', 'indent', '|',
					'justifyleft', 'justifycenter', 'justifyright', 'justifyfull', '|',
					'link', 'unlink', 'anchor', 'image', 'wp_adv',
					);

				$tadv_btns2 = array(
					'formatselect', 'fontselect', 'fontsizeselect', 'forecolor', 'backcolor', '|',
					'emotions', 'charmap', 'nonbreaking', 'spellchecker',
					);

				$tadv_btns3 = array(
					'tablecontrols', 'delete_table', '|',
					'wp_more', 'wp_page', '|', 'hr', '|',
					'styleselect', '|', 'wp_help',
					);

				$tadv_btns4 = array(
					'bullist', 'numlist', '|',
	                'pastetext', 'pasteword', 'removeformat', '|',
					'search', 'replace', '|',
					'undo', 'redo', '|', 'fullscreen',
					);

				update_option( 'tadv_btns1', $tadv_btns1 );
				update_option( 'tadv_btns2', $tadv_btns2 );
				update_option( 'tadv_btns3', $tadv_btns3 );
				update_option( 'tadv_btns4', $tadv_btns4 );

				update_option( 'tadv_toolbars', array(
					'toolbar_1' => $tadv_btns1,
					'toolbar_2' => $tadv_btns2,
					'toolbar_3' => $tadv_btns3,
					'toolbar_4' => $tadv_btns4 )
				);

				update_option( 'tadv_options', array(
					'advlink1' => 1,
					'advimage' => 1,
					'editorstyle' => 0,
					'hideclasses' => 0,
					'contextmenu' => 1,
					'no_autop' => 0,
					'advlist' => 1,
					) );

				update_option( 'tadv_plugins', array(
					'nonbreaking',
					'emotions',
					'table',
					'searchreplace',
					'advlink',
					'advlist',
					'advimage',
					'contextmenu'
					) );

				$tadv_allbtns = array( 'wp_adv', 'bold', 'italic', 'strikethrough', 'underline', 'bullist', 'numlist', 'outdent', 'indent', 'justifyleft', 'justifycenter', 'justifyright', 'justifyfull', 'cut', 'copy', 'paste', 'link', 'unlink', 'image', 'wp_more', 'wp_page', 'search', 'replace', 'fontselect', 'fontsizeselect', 'wp_help', 'fullscreen', 'styleselect', 'formatselect', 'forecolor', 'backcolor', 'pastetext', 'pasteword', 'removeformat', 'cleanup', 'spellchecker', 'charmap', 'print', 'undo', 'redo', 'tablecontrols', 'cite', 'ins', 'del', 'abbr', 'acronym', 'attribs', 'layer', 'advhr', 'code', 'visualchars', 'nonbreaking', 'sub', 'sup', 'visualaid', 'insertdate', 'inserttime', 'anchor', 'styleprops', 'emotions', 'media', 'blockquote', 'separator', '|' );

				update_option( 'tadv_allbtns', $tadv_allbtns );
			}
		}
	}


} # sem_fixes_admin

$sem_fixes_admin = sem_fixes_admin::get_instance();