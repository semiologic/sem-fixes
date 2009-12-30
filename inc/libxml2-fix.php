<?php
/*
LibXML2 Fix
http://josephscott.org/code/wordpress/plugin-libxml2-fix/
0.2.2
Joseph Scott <http://josephscott.org/>
*/

function jms_libxml2_fix( $methods ) {
	global $HTTP_RAW_POST_DATA;

	// See http://core.trac.wordpress.org/ticket/7771
	if ( 
		LIBXML_DOTTED_VERSION == '2.6.27'
		|| LIBXML_DOTTED_VERSION == '2.7.0' 
		|| LIBXML_DOTTED_VERSION == '2.7.1' 
		|| LIBXML_DOTTED_VERSION == '2.7.2' 
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
