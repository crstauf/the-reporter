<?php

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

class The_Reporter__Observer__WooCommerceAddError extends _The_Reporter__Observer {
	
	function __construct() {
		parent::__construct();
		
		add_action( 'woocommerce_add_error', array( &$this, 'maybe_create_observation' ) );
	}
	
	function maybe_create_observation( array $data ) {
	}
	
}

?>