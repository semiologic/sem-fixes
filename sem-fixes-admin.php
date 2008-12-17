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
		
		# kill wp notifications (slows down the admin area)
		remove_action( 'init', 'wp_version_check' );
		remove_filter( 'update_footer', 'core_update_footer' );
		remove_action( 'admin_notices', 'update_nag', 3 );
		add_filter( 'pre_option_update_core', array('sem_fixes_admin', 'kill_wp_version_check'));
		
		# kill link updater
		add_filter('option_use_linksupdate', create_function('$in', 'return 0;'));
		
		# allow html in term descriptions
		add_action('init', array('sem_fixes_admin', 'set_kses'));
		add_action('set_current_user', array('sem_fixes_admin', 'set_kses'));
		
		# fix plugins
		add_action('plugins_loaded', array('sem_fixes_admin', 'fix_plugins'), 1000000);
		
		# Dashboard Link
		add_action('load-index.php', array('sem_fixes_admin', 'dashboard_link'));
		
		# Lists in admin area
		add_action('admin_head', array('sem_fixes_admin', 'fix_admin_css'));
	} # init()
	
	
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
	# kill_wp_version_check()
	#
	
	function kill_wp_version_check($o)
	{
		global $wp_version;
		
		$o = (object) null;
		$o->last_checked = time();
		$o->version_checked = $wp_version;
		
		return $o;
	} # kill_wp_version_check()
	
	
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
		
		dump($submenu);
		
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
		# wp backup: major security flaws
		if ( isset($GLOBALS['mywpdbbackup']) )
		{
			sem_fixes_admin::fix_db_backup();
		}
		
		# tinymce advanced
		if ( function_exists('tadv_menu') ) 
		{
			remove_action('admin_menu', 'tadv_menu');
			add_action('admin_menu', array('sem_fixes_admin', 'tinymce_advanced_admin_menu'));
		}
		
	} # fix_plugins()
	
	
	#
	# fix_db_backup()
	#
	
	function fix_db_backup()
	{
		global $mywpdbbackup;
		
		if ( isset($_POST['do_backup']) )
		{
			if ( $_POST['do_backup'] == 'backup' )
			{
				remove_action('admin_menu', array(&$mywpdbbackup, 'fragment_menu'));
				add_action('admin_menu', array('sem_fixes_admin', 'db_backup_fragment_menu'));
			}
			elseif ( !current_user_can('administrator') )
			{
				remove_action('init', array(&$mywpdbbackup, 'perform_backup'));
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
		add_management_page(__('Backup','wp-db-backup'), __('Backup','wp-db-backup'), 'administrator', $mywpdbbackup->basename, array(&$mywpdbbackup, 'build_backup_script'));
	} # db_backup_fragment_menu()
	
	
	#
	# db_backup_admin_menu()
	#
	
	function db_backup_admin_menu()
	{
		global $mywpdbbackup;
		add_management_page(__('Backup','wp-db-backup'), __('Backup','wp-db-backup'), 'administrator', $mywpdbbackup->basename, array(&$mywpdbbackup, 'backup_menu'));
	} # db_backup_admin_menu()
	
	
	#
	# tinymce_advanced_admin_menu()
	#
	
	function tinymce_advanced_admin_menu()
	{
		$page = add_options_page( 'TinyMCE Advanced', 'TinyMCE Advanced', 'manage_options', 'tinymce-advanced', 'tadv_page' );
		add_action( "admin_print_scripts-$page", 'tadv_add_scripts' );
		add_action( "admin_head-$page", 'tadv_admin_head' );
	} #tinymce_advanced_admin_menu()
	
	
	#
	# fix_admin_css()
	#
	
	function fix_admin_css()
	{
		?>
<style type="text/css">
.turbo-nag {
	display: none !important;
}

#contextual-help-wrap li {
	margin-left: 1.5em;
	list-style: square;
}
</style>
<?php
	} # fix_admin_li()
} # sem_fixes_admin

sem_fixes_admin::init();
?>