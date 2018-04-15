<?php

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

abstract class _The_Reporter__Connection {
	
	function __construct() {
		
		do_action_ref_array( 'the_reporter/init_connection', array( &$this ) );
	}
	
	abstract function maybe_report_observation( _The_Reporter__Observation $observation );
	
}

?>