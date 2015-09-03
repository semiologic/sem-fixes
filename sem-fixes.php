<?php
/*
Plugin Name: Semiologic Tweaks and Fixes
Plugin URI: http://www.semiologic.com/software/sem-fixes/
Description: A variety of Semiologic implemented tweaks and fixes for WordPress.
Version: 3.0.1
Author: Denis de Bernardy & Mike Koepke
Author URI: https://www.semiologic.com
Text Domain: sem-fixes
Domain Path: /lang
License: Dual licensed under the MIT and GPLv2 licenses
*/

/*
Terms of use
------------

This software is copyright Denis de Bernardy & Mike Koepke, and is distributed under the terms of the MIT and GPLv2 licenses.
**/


define('sem_fixes_version', '3.0.1');

/**
 * sem_fixes
 *
 * @package Semiologic Fixes
 **/

# give Magpie a litte bit more time
if ( !defined('MAGPIE_FETCH_TIME_OUT') )
	define('MAGPIE_FETCH_TIME_OUT', 4);	// 4 second timeout, instead of 2

# fix shortcodes
if ( @ini_get('pcre.backtrack_limit') <= 1000000 )
	@ini_set('pcre.backtrack_limit', 1000000);
if ( @ini_get('pcre.recursion_limit') <= 250000 )
	@ini_set('pcre.recursion_limit', 250000);


if (!defined('AUTOMATIC_UPDATER_DISABLED'))
	define('AUTOMATIC_UPDATER_DISABLED',  true);


class sem_fixes {

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

		if ( is_admin() )
			include dirname(__FILE__) . '/sem-fixes-admin.php';
    }

	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		// more stuff: register actions and filters
		register_activation_hook(__FILE__, array($this, 'activate'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate'));

		$version = get_option('sem_fixes_version');
		if ( ( $version === false || version_compare( $version, sem_fixes_version, '<' ) ) && !defined('DOING_CRON') )
			add_action('init', array($this, 'upgrade'));

		if ( !is_admin() ) {
		# remove #more-id in more links
			add_filter('the_content_more_link', array($this, 'fix_more'), 10000);
		}

		# http://core.trac.wordpress.org/ticket/6698
		if ( wp_next_scheduled('do_generic_ping') > time() + 60 )
		    sem_fixes::do_generic_ping();

		# http://core.trac.wordpress.org/changeset/14996
		foreach ( array('the_content', 'the_title', 'wp_title' ) as $hook ) {
			remove_filter($hook, 'capital_P_dangit');
			remove_filter($hook, 'capital_P_dangit', 11);
		}
		remove_filter('comment_text', 'capital_P_dangit', 31);

		# Fix curl SSL
		add_filter('http_api_curl', array($this, 'curl_ssl'));


		# no emoji stuff
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );


//		$this->load_modules();
	}


    /**
	 * fix_more()
	 *
	 * @param string $more_link
	 * @return string $more_link
	 **/
	
	function fix_more($more_link) {
		if ( is_singular() || !in_the_loop() )
			return $more_link;
		else
			return str_replace("#more-" . get_the_ID(), '', $more_link);
	} # fix_more()

	
	/**
	 * fix_wysiwyg()
	 *
	 * @param string $content
	 * @return string $content
	 **/
	function fix_wysiwyg($content) {
		$find_replace = array(
			# broken paragraph tag
			"~
				<p>\s*&lt;\s*</p>
				\s*
				<p>p(?:>|&gt;>)
			~isx" => "<p>",
			
			# closed p, div or noscript singleton
			"~
				<\s*(?:p|div|noscript)	# p, div or noscript tag
				(?:\s[^>]*)?			# optional attributes
				/\s*>					# />
			~ix" => "",
			
			# empty paragraph
			"~
				<p></p>					# empty paragraph
			~ix" => "",
			
			# broken div align
			"~
				<p>&lt;</p>
				\s*
				<p>div
					\s+
					align=(?:&\#034;|&\#8221;)right(?:&\#034;|&\#8221;)
					>
				(.*?)
				</p>
			~isx" => "<p style=\"text-align: right;\">$1</p>",
			
			# more|nextpage in div tag
			"~
				<div\s+align=\"right\">(<!--(?:more|nextpage)-->)</div>
			~isx" => "<p style=\"text-align: right;\">$1</p>",
			);
		
		return preg_replace(array_keys($find_replace), array_values($find_replace), $content);
	} # fix_wysiwyg()
	

	/**
	 * do_generic_ping()
	 *
	 * @return void
	 **/

	function do_generic_ping() {
		if ( get_transient('last_ping') )
			return;
		
		wp_clear_scheduled_hook('do_generic_ping');
		wp_schedule_single_event(time(), 'do_generic_ping');
		set_transient('last_ping', time(), 1800);
	} # do_generic_ping()


	/**
	 * Disable SSL validation for Curl
	 *
	 * @param resource $ch
	 * @return resource $ch
	 **/
	function curl_ssl($ch)
	{
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		return $ch;
	}

	/**
	 * load_modules()
	 *
	 * @return void
	 **/

	function load_modules() {
		if ( !class_exists('sem_security') )
			include_once dirname(__FILE__) . '/inc/sem-security.php';
	} # load_modules()

	/**
	 * activate()
	 *
	 * @return void
	 **/

	function activate() {
		if ( !function_exists('save_mod_rewrite_rules') || !function_exists('get_home_path') )
			include_once ABSPATH . 'wp-admin/includes/admin.php';
		
		if ( !isset($GLOBALS['wp_rewrite']) ) $GLOBALS['wp_rewrite'] = new WP_Rewrite;
		
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	} # activate()
	
	
	/**
	 * deactivate()
	 *
	 * @return void
	 **/

	function deactivate() {
		if ( !function_exists('save_mod_rewrite_rules') || !function_exists('get_home_path') )
			include_once ABSPATH . 'wp-admin/includes/admin.php';
		
		if ( !isset($GLOBALS['wp_rewrite']) ) $GLOBALS['wp_rewrite'] = new WP_Rewrite;
		
		remove_filter('mod_rewrite_rules', array($this, 'rewrite_rules'));
		
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	} # deactivate()


	/**
	 * upgrade()
	 *
	 * @return void
	 **/

	function upgrade() {

//		update_option( 'sem_fixes_version', sem_fixes_version );

	}

} # sem_fixes

$sem_fixes = sem_fixes::get_instance();


if ( !function_exists('wp_redirect') ) :
/**
 * Redirects to another page.
 *
 * @param string $location The path to redirect to.
 * @param int $status Status code to use.
 * @return bool False if $location is not provided, true otherwise.
 */
function wp_redirect($location, $status = 302) {
	global $is_IIS;

	$location = apply_filters( 'wp_redirect', $location, $status );

	$status = apply_filters( 'wp_redirect_status', $status, $location );

	if ( ! $location )
		return false;

	if ( function_exists('wp_sanitize_redirect') ) {
		$location = wp_sanitize_redirect($location);
	}

	if ( !$is_IIS && php_sapi_name() != 'cgi-fcgi' )
		status_header($status); // This causes problems on IIS and some FastCGI setups

	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Location: $location", true, $status);

	return true;
}
endif;

if ( !is_admin() ) {
	/**
	 * enable_widget_php()
	 *
	 * @param string $text
	 * @return string $text
	 **/
	function enable_widget_php ($text) {
	    if (strpos($text, '<' . '?') !== false) {
	        ob_start();
	        @eval('?' . '>' . $text);
	        $text = ob_get_contents();
	        ob_end_clean();
	    }
	    return $text;
	}

	# enable php code in widgets
	add_filter('widget_text', 'enable_widget_php', 1);


	#Filter to allow shortcodes in text widgets
	global $wp_embed;

	add_filter( 'widget_text', 'shortcode_unautop');
	add_filter( 'widget_text', 'do_shortcode', 11);

	// embed trick props http://daisyolsen.com/
	add_filter( 'widget_text', array( $wp_embed, 'run_shortcode' ), 8 );
	add_filter( 'widget_text', array( $wp_embed, 'autoembed'), 8 );
}