<?php

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

abstract class _The_Reporter__Observation {
	
	/** @var string ID for the observation. */
	protected $id = null;
	
	/** @var int|string Username or encyrpted IP address of the witness. */
	protected $user = null;
	
	/** @var string Text of the reporter. */
	var $text = null;
	
	function __construct( $data ) {
		
		if ( is_string( $data ) )
			$data = array( 'text' => $data );
			
		if ( !array_key_exists( 'id', $data ) )
			$this->id = md5( serialize( $data ) );

		foreach ( $data as $var => $value )
			$this->$var = $value;
			
		$this->user = 
			( 
				function_exists( 'is_user_logged_in' )
				&& is_user_logged_in()
			)
			? get_current_user_id()
			: hash( 'crc32b', $_SERVER['REMOTE_ADDR'] );
		
		do_action_ref_array( 'the_reporter/create_observation/' . $this->get_type(), array( &$this ) );
		do_action_ref_array( 'the_reporter/create_observation', array( &$this ) );
	}
	
	function get( string $prop ) {
		if ( 'type' === $prop )
			return $this->get_type();
			
		return property_exists( $this, $prop )
			? $this->$prop
			: null;
	}
	
	function get_type() {
		return strtolower( str_replace( 'The_Reporter__Observation_', '', get_class( $this ) ) );
	}
	
	function is_type( string $type ) {
		return $this->get_type() === $type;
	}
	
	abstract function is_reportable();
	
	function get_default_properties() {
		return array( 'id', 'text', 'user' );
	}
	
}

?>