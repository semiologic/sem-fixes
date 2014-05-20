<?php
/*
Plugin Name: Semiologic Fixes
Plugin URI: http://www.semiologic.com/software/sem-fixes/
Description: A variety of teaks and fixes for WordPress and third party plugins.
Version: 2.4.1
Author: Denis de Bernardy & Mike Koepke
Author URI: http://www.getsemiologic.com
Text Domain: sem-fixes
Domain Path: /lang
License: Dual licensed under the MIT and GPLv2 licenses
*/

/*
Terms of use
------------

This software is copyright Denis de Bernardy & Mike Koepke, and is distributed under the terms of the MIT and GPLv2 licenses.
**/


define('sem_fixes_version', '2.4');

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

# fix calendar, see http://core.trac.wordpress.org/ticket/9588
if ( function_exists('date_default_timezone_set') )
	date_default_timezone_set('UTC');
wp_timezone_override_offset();


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

			# fix wysiwyg
			add_option('fix_wysiwyg', '0');
			if ( get_option('fix_wysiwyg') )
			    add_filter('the_content', array($this, 'fix_wysiwyg'), 10000);

			# kill generator
			remove_action('wp_head', 'wp_generator');
			add_filter('the_generator', array($this, 'the_generator'));
		}

		# http://core.trac.wordpress.org/ticket/9873
		sem_fixes::readonly_url();

		# http://core.trac.wordpress.org/ticket/6698
		if ( wp_next_scheduled('do_generic_ping') > time() + 60 )
		    sem_fixes::do_generic_ping();

		# http://core.trac.wordpress.org/ticket/9874
		add_filter('tiny_mce_before_init', array($this, 'tiny_mce_config'));


		# http://core.trac.wordpress.org/ticket/9105
		if ( !get_option('show_on_front') )
		    update_option('show_on_front', 'posts');

		# http://core.trac.wordpress.org/changeset/14996
		foreach ( array('the_content', 'the_title', 'wp_title' ) as $hook ) {
			remove_filter($hook, 'capital_P_dangit');
			remove_filter($hook, 'capital_P_dangit', 11);
		}
		remove_filter('comment_text', 'capital_P_dangit', 31);

		# Fix curl SSL
		add_filter('http_api_curl', array($this, 'curl_ssl'));

		# https://core.trac.wordpress.org/ticket/26974
		add_filter( 'date_rewrite_rules', array($this, 'stripDayRules'));

		$this->load_plugins();
		$this->fix_plugins();

	}


	/**
	 * load_plugins()
	 *
	 * @return void
	 **/

	function load_plugins() {
		if ( !function_exists('wpguy_category_order_menu') )
			include_once dirname(__FILE__) . '/inc/category-order.php';

		if ( is_admin() && !function_exists('mypageorder_menu') )
			include_once dirname(__FILE__) . '/inc/mypageorder.php';

		if ( defined('LIBXML_DOTTED_VERSION') && in_array(LIBXML_DOTTED_VERSION, array('2.7.0', '2.7.1', '2.7.2', '2.7.3') ) && !function_exists('jms_libxml2_fix') )
			include_once dirname(__FILE__) . '/inc/libxml2-fix.php';
	} # plugins_loaded()

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
	 * the_generator()
	 *
	 * @param string $in
	 * @return string ''
	 **/

	function the_generator($in) {
		return '';
	} # the_generator()
	
	
	/**
	 * readonly_url()
	 *
	 * @return void
	 **/

	function readonly_url() {
		$home_url = get_option('home');
		$site_url = get_option('siteurl');
		
		$home_www = strpos($home_url, '://www.') !== false;
		$site_www = strpos($site_url, '://www.') !== false;
		
		if ( $home_www != $site_www ) {
			if ( $home_www )
				$site_url = str_replace('://', '://www.', $site_url);
			else
				$site_url = str_replace('://www.', '://', $site_url);
			update_option('siteurl', $site_url);
		}
		
		if ( !defined('WP_HOME') )
			define('WP_HOME', $home_url);
		if ( !defined('WP_SITEURL') )
			define('WP_SITEURL', $site_url);
	} # readonly_url()
	
	
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
	 * fix_plugins()
	 *
	 * @return void
	 **/
	
	function fix_plugins() {
		# easy auction ads
		if ( function_exists('wp_easy_auctionads_start') ) {
			add_action('before_the_wrapper', 'wp_easy_auctionads_start');
			add_action('after_the_wrapper', 'wp_easy_auctionads_end');
			
			add_action('before_the_canvas', 'wp_easy_auctionads_start');
			add_action('after_the_canvas', 'wp_easy_auctionads_end');
		}
		
		# hashcash
		if ( function_exists('wphc_add_commentform') ) {
			add_filter('option_plugin_wp-hashcash', array($this, 'hc_options'));
			remove_action('admin_menu', 'wphc_add_options_to_admin');
			remove_action('widgets_init', 'wphc_widget_init');
			remove_action('comment_form', 'wphc_add_commentform');
			remove_action('wp_head', 'wphc_posthead');
			add_action('comment_form', array($this, 'hc_add_message'));
			add_action('wp_head', array($this, 'hc_addhead'));
			
			if ( is_admin() )
				remove_filter('preprocess_comment', 'wphc_check_hidden_tag');
		}
	} # fix_plugins()
	
	
	/**
	 * hc_options()
	 *
	 * @param array $o
	 * @return array $o
	 **/
	
	static function hc_options($o) {
		if ( function_exists('akismet_init') && get_option('wordpress_api_key') ) {
			$o['moderation'] = 'akismet';
		} else {
			$o['moderation'] = 'delete';
		}
		
		$o['validate-ip'] = 'on';
		$o['validate-url'] = 'on';
		$o['logging'] = '';
		
		return $o;
	} # hc_options()
	
	
	/**
	 * hc_add_message()
	 *
	 * @return void
	 **/

	function hc_add_message() {
		$options = wphc_option();

		switch( $options['moderation'] ) {
		case 'delete':
			$warning = __('Wordpress Hashcash needs javascript to work, but your browser has javascript disabled. Your comment will be deleted!', 'sem-fixes');
			break;
		case 'akismet':
			$warning = __('Wordpress Hashcash needs javascript to work, but your browser has javascript disabled. Your comment will be queued in Akismet!', 'sem-fixes');
			break;
		case 'moderate':
		default:
			$warning = __('Wordpress Hashcash needs javascript to work, but your browser has javascript disabled. Your comment will be placed in moderation!', 'sem-fixes');
			break;
		}
		
		echo '<input type="hidden" id="wphc_value" name="wphc_value" value="" />' . "\n";
		echo '<noscript><p><strong>' . $warning . '</strong></p></noscript>' . "\n";
	} # hc_add_message()
	
	
	/**
	 * hc_addhead()
	 *
	 * @return void
	 **/
	
	function hc_addhead() {
		if ( !is_singular() )
			return;
		
		$hc_js = wphc_getjs();
		
		echo <<<EOS

<script type="text/javascript">
<!--
function addLoadEvent(func) {
  var oldonload = window.onload;
  if (typeof window.onload != 'function') {
    window.onload = func;
  } else {
    window.onload = function() {
      if (oldonload) {
        oldonload();
      }
      func();
    }
  }
}

$hc_js

addLoadEvent(function(){
	if ( document.getElementById('wphc_value') )
		document.getElementById('wphc_value').value=wphc();
});
//-->
</script>

EOS;
	} # hc_addhead()

	
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
    * Disables day links when using /yyyy/mm/slug/ permalinks
    *
    * @see https://core.trac.wordpress.org/ticket/5305
    * @see https://core.trac.wordpress.org/ticket/26974
    *
    * @param array $date_rewrite_rules
    * @return array $date_rewrite_rules
    */
    public function stripDayRules($date_rewrite_rules)
    {
		if (get_option('permalink_structure') == '/%year%/%monthnum%/%postname%/') {
		    $date_rewrite_rules = array_filter($date_rewrite_rules, array($this, 'stripDayRulesFilter'));
		}
		return $date_rewrite_rules;
    }

	/**
	* Filter used in stripDayRules()
	*
	* @param string $rule
	* @return boolean $strip
	*/
	public function stripDayRulesFilter($rule)
	{
		return strpos($rule, '&day=') === false;
	}


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

	$location = wp_sanitize_redirect($location);

	if ( !$is_IIS && php_sapi_name() != 'cgi-fcgi' )
		status_header($status); // This causes problems on IIS and some FastCGI setups

	header("Cache-Control: no-store, no-cache, must-revalidate");
	header("Location: $location", true, $status);

	return true;
}
endif;

$sem_fixes = sem_fixes::get_instance();