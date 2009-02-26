<?php
/*
Plugin Name: Semiologic Fixes
Plugin URI: http://www.semiologic.com/software/wp-tweaks/sem-fixes/
Description: A variety of teaks and fixes for WordPress and third party plugins
Version: 1.8.2 alpha
Author: Denis de Bernardy
Author URI: http://www.getsemiologic.com
*/

/*
Terms of use
------------

This software is copyright Mesoconcepts and is distributed under the terms of the Mesoconcepts license. In a nutshell, you may freely use it for any purpose, but may not redistribute it without written permission.

http://www.mesoconcepts.com/license/
**/

define('sem_fixes_path', ABSPATH . PLUGINDIR);

global $sem_fixes_files;
global $sem_fixes_admin_files;

$sem_fixes_files = array(
	'favicon-head.php',
	'impostercide.php',
	'not-to-me.php',
	);
$sem_fixes_admin_files = array(
	'mypageorder/mypageorder.php',
	'order-categories/category-order.php',
	);

# Fix IP behind a load balancer
if ( isset($_SERVER['HTTP_X_FORWARDED_FOR']) ) {
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_FORWARDED_FOR'];
} elseif ( isset($_SERVER['HTTP_X_REAL_IP']) ) {
	$_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_X_REAL_IP'];
}


class sem_fixes
{
	#
	# init()
	#
	
	function init()
	{
		# auto-maintain db
		add_action('maintain_db', array('sem_fixes', 'maintain_db'));
		
		if ( !is_admin() )
		{
			if ( !wp_next_scheduled('maintain_db') )
			{
				wp_schedule_event(time(), 'daily', 'maintain_db');
			}
			
			# remove #more-id in more links
			add_filter('the_content', array('sem_fixes', 'fix_more'), 10000);

			# fix wysiwyg
			add_filter('the_content', array('sem_fixes', 'fix_wysiwyg'), 10000);
		}
		
		# fix plugins
		add_action('plugins_loaded', array('sem_fixes', 'fix_plugins'), 1000000);
		
		# fix widgets
		add_action('init', array('sem_fixes', 'widgetize'));
		
		# security fix
		add_filter('option_default_role', array('sem_fixes', 'default_role'));
		
		# generator
		add_filter('the_generator', array('sem_fixes', 'the_generator'));

		# security fix		
		add_filter('option_active_plugins', array('sem_fixes', 'kill_hack_files'));
		
		# tinyMCE
		add_filter('tiny_mce_before_init', array('sem_fixes', 'tiny_mce_config'));
		
		# convert dots to dashes in sanitize_title
		add_filter('sanitize_title', array('sem_fixes', 'sanitize_title'), 0);
		
		# move wp version check
		remove_action( 'init', 'wp_version_check' );
		add_action( 'wp_footer', 'wp_version_check', 10000 );
		add_action( 'admin_footer', 'wp_version_check', 10000 );
		
		# strip double slashes from permalink
		add_filter('the_permalink', array('sem_fixes', 'fix_permalink'), 1000);
		
		# give Magpie a litte bit more time
		if ( !defined('MAGPIE_FETCH_TIME_OUT') ) {
			define('MAGPIE_FETCH_TIME_OUT', 4);	// 4 second timeout, instead of 2
		}
	} # init()
	
	
	#
	# fix_permalink()
	#
	
	function fix_permalink($link)
	{
		if ( strpos(substr($link, 8), '//') === false ) return $link;
		
		$good = get_option('home') . '/';
		$bad = $good . '/';
		
		$link = str_replace($bad, $good, $link);
		
		return $link;
	} # fix_permalink()
	
	
	#
	# sanitize_title()
	#
	
	function sanitize_title($title)
	{
		return str_replace('.', '-', $title);
	} # sanitize_title()
	
	
	#
	# tiny_mce_config()
	#
	
	function tiny_mce_config($o)
	{
		# http://wordpress.org/support/topic/164217/page/2?replies=69#post-718683
		
		$o['compress'] = false;
		
		# http://forum.semiologic.com/discussion/4807/iframe-code-disappears-switching-visualhtml/
		# http://wiki.moxiecode.com/index.php/TinyMCE:Configuration/valid_elements#Full_XHTML_rule_set
		# assume the stuff below is properly set if they exist already
		
		if ( current_user_can('unfiltered_html') )
		{
			if ( !isset($o['extended_valid_elements']) )
			{
				$elts = array();
				
				$elts[] = "iframe[align<bottom?left?middle?right?top|class|frameborder|height|id"
					. "|longdesc|marginheight|marginwidth|name|scrolling<auto?no?yes|src|style"
					. "|title|width]";

				$elts = implode(',', $elts);

				$o['extended_valid_elements'] = $elts;
			}
		}
		else
		{
			if ( !isset($o['invalid_elements']) )
			{
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
		#dump($o);die;
		
		return $o;
	} # tiny_mce_config()
	
	
	#
	# the_generator()
	#
	
	function the_generator($in)
	{
		return '';
	} # the_generator()
	

	#
	# default_role()
	#
	
	function default_role($o)
	{
		if ( $o == 'administrator' && get_option('users_can_register') )
		{
			global $wp_roles;
			
			foreach ( $wp_roles->role_names as $role => $name )
			{
				if ( $role != 'administrator' )
				{
					$o = $role;
					add_action('shutdown', create_function('', "update_option('default_role', '$role');"));
					break;
				}
			}
		}
		
		return $o;
	} # default_role()
	
	
	#
	# widgetize()
	#
	
	function widgetize()
	{
		global $wp_registered_widgets;
		global $wp_registered_widget_controls;
		
		$wp_registered_widgets['calendar']['callback'] = array('sem_fixes', 'widget_calendar');
		unset($wp_registered_widget_controls['calendar']);
		
		global $sem_options;
		
		if ( isset($sem_options) && $sem_options['show_search_form'] )
		{
			unset($wp_registered_widgets['search']);
		}
	} # widgetize()
	
	
	#
	# widget_calendar()
	#
	
	function widget_calendar($args)
	{
		extract($args);
		echo $before_widget;
		if ( !is_admin() )
		{
			echo '<div id="calendar_wrap">';
			get_calendar();
			echo '</div>';
		}
		echo $after_widget;
	} # widget_calendar()
	


	#
	# maintain_db()
	#

	function maintain_db()
	{
		global $wpdb;

		$tablelist = $wpdb->get_results("SHOW TABLE STATUS LIKE '$wpdb->prefix%'", ARRAY_N);
		
		foreach ( $tablelist as $table )
		{
			$tablename = $table[0];
			
			if ( strtoupper($table[1]) != 'MYISAM' ) continue;
			
			$check = $wpdb->get_row("CHECK TABLE $tablename", ARRAY_N);

			if ( $check[2] == 'error' )
			{
				if ( $check[3] == 'The handler for the table doesn\'t support check/repair' )
				{
					continue;
				}
				else
				{
					$repair = $wpdb->get_row("REPAIR TABLE $tablename", ARRAY_N);

					if ( $repair[3] != 'OK' )
					{
						continue;
					}
				}
			}

			$wpdb->query("OPTIMIZE TABLE $tablename");
		}
	} # maintain_db()
	
	
	#
	# fix_more()
	#

	function fix_more($content)
	{
		if ( is_singular() || !in_the_loop() )
		{
			return $content;
		}
		else
		{
			return str_replace(
				"#more-" . get_the_ID(),
				'',
				$content
				);
		}
	} # fix_more()
	
	
	#
	# fix_wysiwyg()
	#
	
	function fix_wysiwyg($content)
	{
		if ( ( $option = get_option('fix_wysiwyg') ) === false )
		{
			add_option('fix_wysiwyg', '0');
		}
		elseif ( !$option )
		{
			return $content;
		}
		
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
		
		$find = array();
		$replace = array();
		
		foreach ( $find_replace as $key => $val )
		{
			$find[] = $key;
			$replace[] = $val;
		}
		
		$content = preg_replace(
			$find,
			$replace,
			$content
			);
		
		return $content;
	} # fix_wysiwyg()
	
	
	#
	# fix_plugins()
	#
	
	function fix_plugins()
	{
		# wp db backup: major security issue
		global $mywpdbbackup;
		
		if ( isset($mywpdbbackup) && !current_user_can('administrator') )
		{
			remove_action('init', array(&$mywpdbbackup, 'perform_backup'));
		}
		
		# easy auction ads
		if ( function_exists('wp_easy_auctionads_start') )
		{
			add_action('before_the_wrapper', 'wp_easy_auctionads_start');
			add_action('after_the_wrapper', 'wp_easy_auctionads_end');
			
			add_action('before_the_canvas', 'wp_easy_auctionads_start');
			add_action('after_the_canvas', 'wp_easy_auctionads_end');
		}
		
		# hashcash
		if ( function_exists('wphc_add_commentform') )
		{
			add_filter('option_plugin_wp-hashcash', array('sem_fixes', 'hc_options'));
			remove_action('admin_menu', 'wphc_add_options_to_admin');
			remove_action('widgets_init', 'wphc_widget_init');
			remove_action('comment_form', 'wphc_add_commentform');
			add_action('comment_form', array('sem_fixes', 'hc_add_message'));
			add_action('wp_footer', array('sem_fixes', 'hc_add_elt'));
		}
	} # fix_plugins()
	
	
	#
	# wphc_options()
	#
	
	function hc_options($o)
	{
		if ( function_exists('akismet_init') && get_option('wordpress_api_key') )
		{
			$o['moderation'] = 'akismet';
		}
		else
		{
			$o['moderation'] = 'delete';
		}
		
		$o['validate-ip'] = 'on';
		$o['validate-url'] = 'on';
		$o['logging'] = '';
		
		#dump($o);
		
		return $o;
	} # hc_options()
	
	
	#
	# hc_add_message()
	#
	
	function hc_add_message()
	{
		$options = wphc_option();

		switch($options['moderation']){
			case 'delete':
				$verb = 'deleted';
				break;
			case 'akismet':
				$verb = 'queued in Akismet';
				break;
			case 'moderate':
			default:
				$verb = 'placed in moderation';
				break;
		}

		echo '<input type="hidden" id="wphc_value" name="wphc_value" value=""/>';
		echo '<noscript><small>Wordpress Hashcash needs javascript to work, but your browser has javascript disabled. Your comment will be '.$verb.'!</small></noscript>';
	} # hc_add_message()
	
	
	#
	# hc_add_elt()
	#
	
	function hc_add_elt()
	{
		# prevent js errors on pages with no comment form
		if ( is_singular() )
		{
			echo <<<EOF

<script type="text/javascript">
if ( !document.getElementById('wphc_value') )
{
	document.write('<input type="hidden" id="wphc_value" name="wphc_value" value="" />');
}
</script>

EOF;
		}
	} # hc_add_elt()
	

	#
	# kill_hack_files()
	#
	function kill_hack_files($files)
	{
		foreach ( (array) $files as $k => $v )
		{
			if ( strpos($v, '..') !== false )
			{
				// maybe log the issue and auto-correct the option
				unset($files[$k]);
			}
		}
		return $files;
	}

} # sem_fixes

sem_fixes::init();


if ( is_admin() )
{
	include dirname(__FILE__) . '/sem-fixes-admin.php';
}
?>