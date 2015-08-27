<?php
/*
Module Name: Semiologic Security
Description: Starter Module for Semiologic Development

*/

/*
Terms of use
------------

This software is copyright Mike Koepke, and is distributed under the terms of the MIT and GPLv2 licenses.
**/


if ( !defined('sem_security_debug') )
	define('sem_security_debug', false);

if ( !defined('module_textdomain') )
	define('module_textdomain', 'sem-fixes');

/**
 * sem_security
 *
 * @package Semiologic Security
 **/

class sem_security {
	/**
	 * Module instance.
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
	 * Constructor.
	 *
	 *
	 */
	public function __construct() {
		$this->plugin_url    = plugins_url( '/', __FILE__ );
		$this->plugin_path   = plugin_dir_path( __FILE__ );

		$this->init();
    } # __construct()


	/**
	 * init()
	 *
	 * @return void
	 **/

	function init() {
		// more stuff: register actions and filters
		if ( !is_admin() ) {
		    add_action('wp_enqueue_scripts', array($this, 'scripts'));
	    }

		$this->security_tweaks();
	}

	/**
	 * scripts()
	 *
	 * @return void
	 **/

	function scripts() {

	} # scripts()


	/**
	* security_tweaks()
	*
	* @param void
	* @return void
	**/

	function security_tweaks() {

		if ( is_admin() ) {
			define( 'DISALLOW_FILE_EDIT', true );
		}

		// kill generator
		remove_action( 'wp_head', 'wp_generator' );
		add_filter( 'the_generator', array( $this, 'the_generator' ) );

		//remove rsd link from header if turned on
		remove_action( 'wp_head', 'rsd_link' );

		//remove wlmanifest link if turned on
		remove_action( 'wp_head', 'wlwmanifest_link' );

		// block common xml-xpc
		add_filter( 'xmlrpc_methods', array( $this, 'block_XMLRPC_DDoS' ) );

		// disables xml-xpc completely
		// add_filter('xmlrpc_enabled', '__return_false');

		//ban extra-long urls if turned on
		if ( ! strpos( $_SERVER['REQUEST_URI'],
				'infinity=scrolling&action=infinite_scroll' ) && ( strlen( $_SERVER['REQUEST_URI'] ) > 255 || strpos( $_SERVER['REQUEST_URI'],
					'eval(' ) || strpos( $_SERVER['REQUEST_URI'], 'CONCAT' ) || strpos( $_SERVER['REQUEST_URI'],
					'UNION+SELECT' ) || strpos( $_SERVER['REQUEST_URI'], 'base64' ) )
		) {
			header( 'HTTP/1.1 414 Request-URI Too Long' );
			header( 'Status: 414 Request-URI Too Long' );
			header( 'Cache-Control: no-cache, must-revalidate' );
			header( 'Expires: Thu, 22 Jun 1978 00:28:00 GMT' );
			header( 'Connection: Close' );
			exit;

		}
	}

	/**
	 * the_generator()
	 *
	 * @param string $in
	 * @return string ''
	 **/
	function the_generator( $in ) {
		return '';
	} # the_generator()


	/**
	 * block_XMLRPC_DDoS()
	 *
	 * https://wordpress.org/plugins/disable-xml-rpc-pingback/
	 * props https://plugins.trac.wordpress.org/browser/disable-xml-rpc-pingback
	 *
	 * @param array $methods
	 * @return array
	 */
	function block_XMLRPC_DDoS( $methods ) {
		unset( $methods['pingback.ping'] );
		unset( $methods['pingback.extensions.getPingbacks'] );
		unset( $methods['wp.getUsersBlogs'] ); // New method used by attackers to perform brute force discovery of existing users
		return $methods;
	}

	/**
	 * @param $src
	 *
	 * @return mixed
	 */
	function remove_script_version( $src ){
	    $parts = explode( '?ver', $src );
	        return $parts[0];
	}





	/**
	 * get_options()
	 *
	 * @return array $options
	 **/

    static function get_options() {
		static $o;

		if ( !is_admin() && isset($o) )
			return $o;

		$o = get_option('sem_security');

        if ( $o === false || !is_array($o) || !isset($o['dummy'])) {
			$o = sem_security::init_options();
		}

		return $o;
	} # get_options()


	/**
	 * init_options()
	 *
	 * @return array $options
	 **/

	function init_options() {
        $defaults = array(
      	);

        $o = get_option('sem_security');

        if ( !$o ) {
    		$o  = $defaults;
        }
        else
            $o = wp_parse_args($o, $defaults);

		update_option('sem_security', $o);

		return $o;
	} # init_options()
} # sem_security

$sem_module = sem_security::get_instance();