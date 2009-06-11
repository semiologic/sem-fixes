<?php
/*
Plugin Name: Semiologic Fixes
Plugin URI: http://www.semiologic.com/software/sem-fixes/
Description: A variety of teaks and fixes for WordPress and third party plugins.
Version: 1.9 RC
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
Text Domain: sem-fixes-info
Domain Path: /lang
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/

if ( file_exists(WP_PLUGIN_DIR . '/order-categories/category-order.php') )
	include WP_PLUGIN_DIR . '/order-categories/category-order.php';

if ( is_admin() && file_exists(WP_PLUGIN_DIR . '/mypageorder/mypageorder.php') )
	include WP_PLUGIN_DIR . '/mypageorder/mypageorder.php';

if ( defined('LIBXML_DOTTED_VERSION') && in_array(LIBXML_DOTTED_VERSION, array('2.7.0', '2.7.1', '2.7.2') ) && file_exists(WP_PLUGIN_DIR . '/libxml2-fix/libxml2-fix.php') )
	include WP_PLUGIN_DIR . '/libxml2-fix/libxml2-fix.php';

// see http://core.trac.wordpress.org/ticket/9235
// Correct comment's ip address with X-Forwarded-For http header if you are behind a proxy or load balancer.
if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
	// this one can have multiple IPs separated by a coma
	$_SERVER['HTTP_X_FORWARDED_FOR'] = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
	$_SERVER['HTTP_X_FORWARDED_FOR'] = $_SERVER['HTTP_X_FORWARDED_FOR'][0];
}

if ( function_exists('filter_var') ) {
	if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) && filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) )
		$_SERVER['REMOTE_ADDR'] = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE);
	elseif ( isset($_SERVER['HTTP_X_REAL_IP']) && filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) )
		$_SERVER['REMOTE_ADDR'] = filter_var($_SERVER['HTTP_X_REAL_IP'], FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE);
} else {
	if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) )
		$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
	elseif ( isset($_SERVER['HTTP_X_REAL_IP']) )
		$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
}


load_plugin_textdomain('sem-fixes', null, dirname(__FILE__) . '/lang');


/**
 * sem_fixes
 *
 * @package Semiologic Fixes
 **/

# give Magpie a litte bit more time
if ( !defined('MAGPIE_FETCH_TIME_OUT') )
	define('MAGPIE_FETCH_TIME_OUT', 4);	// 4 second timeout, instead of 2

if ( !is_admin() ) {
	# remove #more-id in more links
	add_filter('the_content_more_link', array('sem_fixes', 'fix_more'), 10000);

	# fix wysiwyg
	add_option('fix_wysiwyg', '0');
	if ( get_option('fix_wysiwyg') )
		add_filter('the_content', array('sem_fixes', 'fix_wysiwyg'), 10000);

	# kill generator
	remove_action('wp_head', 'wp_generator');
	add_filter('the_generator', array('sem_fixes', 'the_generator'));
}

# http://core.trac.wordpress.org/ticket/9873
add_action('login_head', array('sem_fixes', 'fix_www_pref'));

# http://core.trac.wordpress.org/ticket/9874
add_filter('tiny_mce_before_init', array('sem_fixes', 'tiny_mce_config'));

# fix plugins
add_action('plugins_loaded', array('sem_fixes', 'fix_plugins'));

class sem_fixes {
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
	 * fix_www_pref()
	 *
	 * @return void
	 **/

	function fix_www_pref() {
		$home_url = get_option('home');
		$site_url = get_option('siteurl');
		
		$home_www = strpos($home_url, '://www.') !== false;
		$site_www = strpos($site_url, '://www.') !== false;
		
		if ( $home_www != $site_www ) {
			if ( $home_www )
				$site_url = str_replace('://', '://www.', $site_url);
			else
				$site_url = str_replace('://www.', '://', $site_url);
			update_option('site_url', $site_url);
		}
	} # fix_www_pref()
	
	
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
			add_filter('option_plugin_wp-hashcash', array('sem_fixes', 'hc_options'));
			remove_action('admin_menu', 'wphc_add_options_to_admin');
			remove_action('widgets_init', 'wphc_widget_init');
			remove_action('comment_form', 'wphc_add_commentform');
			remove_action('wp_head', 'wphc_posthead');
			add_action('comment_form', array('sem_fixes', 'hc_add_message'));
			add_action('wp_head', array('sem_fixes', 'hc_addhead'));
			
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
	
	function hc_options($o) {
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
		echo '<noscript><p><strong>' . $warning . '</stron></p></noscript>' . "\n";
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
		$hc_enable = <<<EOS

addLoadEvent(function(){
	if ( document.getElementById('wphc_value') )
		document.getElementById('wphc_value').value=wphc();
});

EOS;

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

$hc_enable

//-->
</script>

EOS;
	} # hc_addhead()
} # sem_fixes


if ( is_admin() )
	include dirname(__FILE__) . '/sem-fixes-admin.php';
?>