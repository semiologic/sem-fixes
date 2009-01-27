<?php

class sem_fixes_admin
{
	#
	# init()
	#
	
	function init()
	{
		# sort admin menu
		add_action('admin_menu', array('sem_fixes_admin', 'sort_admin_menu'), 1000000);
		
		# kill link updater
		add_filter('option_use_linksupdate', create_function('$in', 'return 0;'));
		add_action('load-options-misc.php', array('sem_fixes_admin', 'kill_link_updater'));
		
		# allow html in term descriptions
		add_action('init', array('sem_fixes_admin', 'set_kses'));
		add_action('set_current_user', array('sem_fixes_admin', 'set_kses'));
		
		# fix plugins
		add_action('plugins_loaded', array('sem_fixes_admin', 'fix_plugins'), 1000000);
		
		# Dashboard Link
		add_action('load-index.php', array('sem_fixes_admin', 'dashboard_link'));
		
		# Lists in admin area
		add_action('admin_print_styles', array('sem_fixes_admin', 'admin_css'));
		
		# sticky sidebar
		add_action('load-widgets.php', array('sem_fixes_admin', 'sticky_sidebar'));
		
		# tinymce
		add_filter('tiny_mce_before_init', array('sem_fixes_admin', 'editor_options'), -1000);
		add_filter('mce_external_plugins', array('sem_fixes_admin', 'editor_plugin'), 1000);
		add_filter('mce_buttons', array('sem_fixes_admin', 'editor_buttons'), -1000);
		add_filter('mce_buttons_2', array('sem_fixes_admin', 'editor_buttons_2'), -1000);
		add_filter('mce_buttons_3', array('sem_fixes_admin', 'editor_buttons_3'), -1000);
		add_filter('mce_buttons_4', array('sem_fixes_admin', 'editor_buttons_4'), -1000);
	} # init()
	
	
	#
	# sticky_sidebar()
	#
	
	function sticky_sidebar()
	{
		global $wp_registered_sidebars;
		
		if ( isset($_GET['sidebar']) )
		{
			$sidebar = $_GET['sidebar'];
			
			if ( isset($wp_registered_sidebars[$sidebar]) )
			{
				setcookie(
					'sidebar_' . COOKIEHASH,
					$sidebar,
					time() + 14 * 86400,
					COOKIEPATH,
					COOKIE_DOMAIN
					);
			}
		}
		elseif ( isset($_COOKIE['sidebar_' . COOKIEHASH]) )
		{
			$sidebar = $_COOKIE['sidebar_' . COOKIEHASH];
			
			if ( isset($wp_registered_sidebars[$sidebar]) )
			{
				$_GET['sidebar'] = $sidebar;
			}
		}
	} # sticky_sidebar()
	
	
	#
	# dashboard_link()
	#
	
	function dashboard_link()
	{
		if ( strpos($_SERVER['REQUEST_URI'], 'admin.php?page=index.php') !== false )
		{
			wp_redirect(trailingslashit(get_option('siteurl')) . 'wp-admin/');
			die;
		}
	} # dashboard_link()
	
	
	#
	# kill_link_updater()
	#
	
	function kill_link_updater()
	{
		ob_start(array('sem_fixes_admin', 'kill_link_updater_ob'));
	} # kill_link_updater()
	
	
	#
	# kill_link_updater_ob()
	#
	
	function kill_link_updater_ob($buffer)
	{
		$buffer = preg_replace_callback(
			"|<tr>.+?</tr>|is",
			array('sem_fixes_admin', 'kill_link_updater_callback'),
			$buffer);
		
		return $buffer;
	} # kill_link_updater_ob()
	
	
	#
	# kill_link_updater_callback()
	#
	
	function kill_link_updater_callback($match)
	{
		$match = $match[0];
		
		if ( strpos($match, 'use_linksupdate') !== false )
		{
			return '';
		}
		else
		{
			return $match;
		}
	} # kill_link_updater_callback()
	
	
	#
	# set_kses()
	#
	
	function set_kses()
	{
		remove_filter('pre_term_description', 'wp_filter_kses');
		
		if ( !current_user_can('unfiltered_html') )
		{
			add_filter('pre_term_description', 'wp_filter_post_kses');
		}
	} # term_kses()

	#
	# sort_admin_menu()
	#

	function sort_admin_menu()
	{
		global $submenu;
		
		#dump($submenu);
		
		foreach ( $submenu as $key => $menu_items )
		{
			switch ( $key )
			{
			case 'edit.php':
			case 'upload.php';
			case 'link-manager.php';
			case 'edit-pages.php';
				$stop = 2;
				$caps = array();
				break;

			case 'themes.php':
				$stop = 2;
				$caps = array();
				unset($menu_items[10]); # theme and plugin editors
				break;

			case 'plugins.php':
				$stop = 2;
				$caps = array();
				unset($menu_items[15]); # theme and plugin editors
				break;

			default:
				$stop = 1;
				$caps = array();
				break;
			}

			foreach ( $caps as $cap )
			{
				if ( current_user_can($cap) )
				{
					$stop++;
				}
			}

			$unsortable = array();
			$sortable = $menu_items;
			reset($sortable);

			while ( $stop != 0 )
			{
				$mkey = key($sortable);
				$unsortable[$mkey] = current($sortable);
				unset($sortable[$mkey]);

				$stop--;
			}

			uasort($sortable, array('sem_fixes_admin', 'menu_nat_sort'));

			$submenu[$key] = array_merge($unsortable, $sortable);
			
			if ( count($submenu[$key]) == 1 )
			{
				unset($submenu[$key]);
			}
		}
	} # sort_admin_menu()


	#
	# menu_nat_sort()
	#

	function menu_nat_sort($a, $b)
	{
		return strnatcmp($a[0], $b[0]);
	} # menu_nat_sort()
	
	
	#
	# fix_plugins()
	#
	
	function fix_plugins()
	{
		# hashcash: disable in admin area, so as to enable ajax replies
		remove_filter('preprocess_comment', 'wphc_check_hidden_tag');
	} # fix_plugins()
	
	
	#
	# fix_db_backup()
	#
	
	function fix_db_backup()
	{
		
		if ( isset($_POST['do_backup']) )
		{
			if ( $_POST['do_backup'] == 'backup' )
			{
				remove_action('admin_menu', array(&$mywpdbbackup, 'fragment_menu'));
				add_action('admin_menu', array('sem_fixes_admin', 'db_backup_fragment_menu'));
			}
		}
		elseif ( !isset($_GET['fragment']) && !isset($_GET['backup']) )
		{
			remove_action('admin_menu', array(&$mywpdbbackup, 'admin_menu'));
			add_action('admin_menu', array('sem_fixes_admin', 'db_backup_admin_menu'));
		}
		
	} # fix_db_backup()


	#
	# db_backup_fragment_menu()
	#
	
	function db_backup_fragment_menu()
	{
		global $mywpdbbackup;
		$_page_hook = add_management_page(__('Backup','wp-db-backup'), __('Backup','wp-db-backup'), 'administrator', $mywpdbbackup->basename, array(&$mywpdbbackup, 'build_backup_script'));
		add_action('load-' . $_page_hook, array(&$mywpdbbackup, 'admin_load'));
		if ( function_exists('add_contextual_help') ) {
			$text = $mywpdbbackup->help_menu();
			add_contextual_help($_page_hook, $text);
		}
	} # db_backup_fragment_menu()
	
	
	#
	# db_backup_admin_menu()
	#
	
	function db_backup_admin_menu()
	{
		global $mywpdbbackup;
		$page_hook = add_management_page(__('Backup','wp-db-backup'), __('Backup','wp-db-backup'), 'administrator', $mywpdbbackup->basename, array(&$mywpdbbackup, 'backup_menu'));
		add_action('load-' . $page_hook, array(&$mywpdbbackup, 'admin_load'));
	} # db_backup_admin_menu()
	
	
	#
	# admin_css()
	#
	
	function admin_css()
	{
		$folder = plugins_url() . '/' . basename(dirname(__FILE__));
		$css = $folder . '/css/admin.css';
		
		wp_enqueue_style('sem_fixes', $css, null, '20091018');
	} # admin_css()
	
	
	#
	# editor_options()
	#
	
	function editor_options($init)
	{
		$init['wordpress_adv_hidden'] = false;
		
		return $init;
	} # editor_options()
	
	
	#
	# editor_plugin()
	#
	
	function editor_plugin($plugin_array)
	{
		if ( get_user_option('rich_editing') == 'true')
		{
			$folder = plugins_url() . '/' . basename(dirname(__FILE__));
			
			foreach ( array('advlink', 'emotions', 'searchreplace', 'table') as $plugin )
			{
				$file = $folder . '/tinymce/' . $plugin . '/editor_plugin.js';
				$plugin_array[$plugin] = $file;
			}
		}

		return $plugin_array;
	} # editor_plugin()


	#
	# editor_buttons()
	#
	
	function editor_buttons($buttons)
	{
		return array(
			'bold', 'italic', 'underline', 'strikethrough', '|',
			'bullist', 'numlist', 'blockquote', '|',
			'outdent', 'indent', '|',
			'justifyleft', 'justifycenter', 'justifyright', 'justifyfull', '|',
			'link', 'unlink', 'anchor',
			);
	} # editor_buttons()


	#
	# editor_buttons_2()
	#
	
	function editor_buttons_2($buttons)
	{
		return array(
			'formatselect', 'fontselect', 'fontsizeselect', 'forecolor', 'backcolor', '|',
			'media', 'emotions', 'charmap',
			);
	} # editor_buttons_2()


	#
	# editor_buttons_3()
	#
	
	function editor_buttons_3($buttons)
	{
		return array(
			'tablecontrols', '|',
			'wp_more', 'wp_page', '|',
			'spellchecker','|',
			'wp_help',
			);
	} # editor_buttons_3()


	#
	# editor_buttons_4()
	#
	
	function editor_buttons_4($buttons)
	{
		return array(
			'pastetext', 'pasteword', 'removeformat', '|',
			'search', 'replace', '|',
			'undo', 'redo', '|',
			'fullscreen', 
			);
	} # editor_buttons_4()
} # sem_fixes_admin

sem_fixes_admin::init();
?>