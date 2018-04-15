<?php

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

class The_Reporter__Observer__phpFatal extends _The_Reporter__Observer {
	
	function maybe_create_observation( array $data ) {
		
	}
	
}

?>