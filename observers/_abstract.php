<?php

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

/**
 * Abstract class for observers.
 */
abstract class _The_Reporter__Observer {
	
	/** @var string Observer ID. */
	protected $_id = null;
	
	function __construct() {
	
	}
	
	/**
	 * Check if observer is configured properly.
	 *
	 * @return bool
	 */
	function is_configured() {
		return (
			!is_null( $this->get_id() )
		);
	}
	
	/**
	 * Get observer ID.
	 *
	 * @return string
	 */
	function get_id() { 
		return $this->_id; 
	}
	
	/**
	 * Create the observation.
	 *
	 * Initialize an observation, like so:
	 * new The_Reporter__Observervation_{Single|Recurring}.
	 *
	 * @param array $data 
	 */
	abstract function maybe_create_observation( array $data );
	
	/**
	 * @todo implement white/blacklist
	 */
	protected function _maybe_submit_to_recipients( _The_Reporter__Observation $observation ) {
	
	}
	
}

?>