<?php

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

abstract class _The_Reporter__Recipient {
	
	/** @var string Recipient ID. */
	protected $_id = null;
	
	/** @var array Array of connection classes. */
	protected $_connections = array();
	
	function get_id() {
		return $this->_id;
	}
	
	protected function _has_connection( string $connection_id ) {
		return in_array( $connection_id, $this->_get_connection_ids() );
	}
	
	protected function _get_connections() {
		return $this->_connections;
	}
	
	protected function _get_connection_ids() {
		return array_keys( $this->_get_connections() );
	}
	
	function maybe_report_observation( _The_Reporter__Observation $observation ) {
		if ( !apply_filters( 'the_reporter/recipient/should_report_observation', true, $this->get_id(), $observation ) )
			return false;
			
		foreach ( $this->_get_connections() as $connection_id => $connection )
			if ( apply_filters( 'the_reporter/recipient/should_report_observation/via_' . $connection_id, true, $this->get_id(), $observation ) )
				$connection->maybe_report_observation( $observation );
		
		return true;
	}
	
}

?>