<?php
/*
Plugin Name: LibXML2 Fix
Plugin URI: http://josephscott.org/code/wordpress/plugin-libxml2-fix/
Description: Work around for some versions of libxml2 2.7.x that strip out brackets when parsing XML.  This plugin fixes XML-RPC requests that are mangled because of this problem.  The real fix for this (making the use of this plugin unnecessary) is to use PHP 5.2.9+ with libxml2 2.7.3+.  For more information about this problem see <a href="http://core.trac.wordpress.org/ticket/7771">http://core.trac.wordpress.org/ticket/7771</a>.
Version: 0.2.4
Author: Joseph Scott
Author URI: http://josephscott.org/
*/

function jms_libxml2_fix( $methods ) {
	global $HTTP_RAW_POST_DATA;

	// See http://core.trac.wordpress.org/ticket/7771
	if ( 
		version_compare( LIBXML_DOTTED_VERSION, '2.7.3', '<' ) 
		|| (
			LIBXML_DOTTED_VERSION == '2.7.3'
			&& version_compare( PHP_VERSION, '5.2.9', '<' )
		)
	) {
		$HTTP_RAW_POST_DATA = str_replace( '&lt;', '&#60;', $HTTP_RAW_POST_DATA );
		$HTTP_RAW_POST_DATA = str_replace( '&gt;', '&#62;', $HTTP_RAW_POST_DATA );
		$HTTP_RAW_POST_DATA = str_replace( '&amp;', '&#38;', $HTTP_RAW_POST_DATA );
	}

	return $methods;
}
add_filter( 'xmlrpc_methods', 'jms_libxml2_fix' );
