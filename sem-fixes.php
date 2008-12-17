<?php
/*
Plugin Name: Semiologic Fixes
Plugin URI: http://www.getsemiologic.com
Description: A variety of teaks and fixes for WordPress and third party plugins
Author: Denis de Bernardy
Version: 1.8 alpha
Author URI: http://www.getsemiologic.com
Update Service: http://version.semiologic.com/plugins
Update Tag: sem_fixes
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
	'impostercide.php',
	'not-to-me.php',
	'smart-update-pinger.php',
	);
$sem_fixes_admin_files = array(
	'mypageorder/mypageorder.php',
	);

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
				wp_schedule_event(time(), 'hourly', 'maintain_db');
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
	
		# shortcodes
		add_filter('get_the_excerpt', array('sem_fixes', 'strip_shortcodes'), 0);
		add_filter('get_the_excerpt', array('sem_fixes', 'restore_shortcodes'), 20);
		
		# generator
		add_filter('the_generator', array('sem_fixes', 'the_generator'));

		# security fix		
		add_filter('option_active_plugins', array('sem_fixes', 'kill_hack_files'));
		
		# tinyMCE
		add_filter('tiny_mce_before_init', array('sem_fixes', 'tiny_mce_config'));
		
		# extra scripts
		if ( !is_admin() )
		{
			add_action('wp_print_scripts', array('sem_fixes', 'add_scripts'));
			add_action('wp_print_styles', array('sem_fixes', 'add_css'));
			
			add_action('wp_head', array('sem_fixes', 'add_thickbox_images'), 20);
		}
	} # init()
	
	
	#
	# add_scripts()
	#
	
	function add_scripts()
	{
		wp_enqueue_script('thickbox');
	} # add_scripts()
	
	
	#
	# add_css()
	#
	
	function add_css()
	{
		wp_enqueue_style('thickbox');
	} # add_css()
	
	
	#
	# add_thickbox_images()
	#
	
	function add_thickbox_images()
	{
		$site_url = rtrim(get_option('siteurl'), '/');
		
		$js = <<<EOF

<script type="text/javascript">
var tb_pathToImage = "$site_url/wp-includes/js/thickbox/loadingAnimation.gif";
var tb_closeImage = "$site_url/wp-includes/js/thickbox/tb-close.png";
</script>

EOF;
		
		echo $js;
	} # add_thickbox_images()
	
	
	#
	# tiny_mce_config()
	#
	
	function tiny_mce_config($o)
	{
		# http://wordpress.org/support/topic/164217/page/2?replies=69#post-718683
		
		$o['compress'] = false;
		
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
	# strip_shortcodes()
	#
	
	function strip_shortcodes($in = null)
	{
		remove_filter('the_content', 'do_shortcode', 11);
		
		return $in;
	} # strip_shortcodes()
	
	
	#
	# restore_shortcodes()
	#
	
	function restore_shortcodes($in = null)
	{
		add_filter('the_content', 'do_shortcode', 11);
		
		return $in;
	} # restore_shortcodes()
	

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

		$tablelist = $wpdb->get_results("SHOW TABLES", ARRAY_N);

		foreach ($tablelist as $table)
		{
			$tablename = $table[0];

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
		# easy auction ads
		if ( function_exists('wp_easy_auctionads_start') )
		{
			add_action('before_the_wrapper', 'wp_easy_auctionads_start');
			add_action('after_the_wrapper', 'wp_easy_auctionads_end');
		}
		
		# now reading
		if ( function_exists('nr_add_pages') )
		{
			add_filter('option_nowReadingOptions', array('sem_fixes', 'nr_options'));
			remove_action('wp_head', 'nr_header_stats');
		}
		
		# hashcash
		if ( function_exists('wphc_add_commentform') )
		{
			remove_action('admin_menu', 'wphc_add_options_to_admin');
			remove_action('comment_form', 'wphc_add_commentform');
			remove_action('widgets_init', 'wphc_widget_init');
			
			add_action('comment_form', array('sem_fixes', 'wphc_addform'));
		}
	} # fix_plugins()
	
	
	#
	# function nr_options()
	#
	
	function nr_options($o)
	{
		$useModRewrite = $o['useModRewrite'];
		
		$o['menuLayout'] = NR_MENU_MULTIPLE;
		$o['useModRewrite'] = intval(get_option('permalink_structure') != '');
		$o['debugMode'] = 0;
		$o['httpLib'] = function_exists('curl_init') ? 'curl' : 'snoopy';
		$o['formatDate'] = get_option('date_format');
		$o['permalinkBase'] = 'library/';
		$o['booksPerPage'] = $o['booksPerPage'] ? $o['booksPerPage'] : 10;
		
		if ( $useModRewrite != $o['useModRewrite'] )
		{
			remove_filter('option_nowReadingOptions', array('sem_fixes', 'nr_options'));
			update_option('nowReadingOptions', $o);

			$GLOBALS['wp_rewrite'] =& new WP_Rewrite();
			$GLOBALS['wp_rewrite']->flush_rules();
		}
		
		return $o;
	} # nr_options()
	
	
	#
	# wphc_addform()
	#
	
	function wphc_addform()
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
	} # wphc_addform()

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