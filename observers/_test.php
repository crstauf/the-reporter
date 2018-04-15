<?php

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

class The_Reporter__Observer__Test extends _The_Reporter__Observer {
	
	protected $_id = 'test';
	
	function __construct() {
		parent::__construct();
		
		add_action( 'the_reporter/test', array( &$this, 'maybe_create_observation' ) );
	}
	
	function maybe_create_observation( array $data ) {
		if ( empty( $data ) )
			$data = array( 'Test' );

		do_action( 'the_reporter/process_observation', new The_Reporter__Observation__Single( array( 'text' => $data[0], 'Line' => __LINE__ ) ) );
	}
	
}

do_action( 'the_reporter/register_observer', new The_Reporter__Observer__Test );

?>