<?php

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

if ( 'cli' === php_sapi_name() )
	return;

if ( 
	defined( 'PULL_THE_REPORTER' )
	       && PULL_THE_REPORTER 
)
	return;

/**
 * Report actions, notifications, and errors.
 */
class The_Reporter {
	
	/** @var The_Reporter The single instance of the class. */
	private static $_instance = null;
	
	/** @var array Array of registered observers. */
	protected $_observers = array();
	
	/** @var array Array of registered connections. */
	protected $_connections = array();
	
	/** @var array Array of registered recipients. */
	protected $_recipients = array();
	
	/** @var array Array of observations. */
	protected $_observations = array();
	
	protected function __construct() {
		add_action( 'the_reporter/register_observer',    array( &$this, 'action__register_observer'    ) );
		add_action( 'the_reporter/register_connection',  array( &$this, 'action__register_connection'  ) );
		add_action( 'the_reporter/register_recipient',   array( &$this, 'action__register_recipient'   ) );
		add_action( 'the_reporter/init_connection',      array( &$this, 'action__init_connection'      ) );
		add_action( 'the_reporter/process_observation',  array( &$this, 'action__process_observation'  ) );
		
		/* Observers */
		require_once 'observers/_abstract.php';
		require_once 'observers/php-fatal.php';
		require_once 'observers/filter-woocommerce_add_error.php';
		
		if ( WP_DEBUG )
			require_once 'observers/_test.php';
		
		/* Observations */
		require_once 'observations/_abstract.php';
		require_once 'observations/single.php';
		require_once 'observations/recurring.php';
		
		/* Connections */
		require_once 'connections/_abstract.php';
		require_once 'connections/slack.php';
		
		/* Recipients */
		require_once 'recipients/_abstract.php';
		
		add_action( 'init', function() {
			do_action( 'the_reporter/include_recipients'  );
			do_action( 'the_reporter/additional_includes' );
		} );
		
	}
	
	/**
	 * The Reporter instance.
	 *
	 * Ensures only one instance of The Reporter is loaded or can be loaded.
	 *
	 * @see The_Reporter()
	 * @return The_Reporter - Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$_instance ) )
			self::$_instance = new The_Reporter;

		return self::$_instance;
	}
	
	
	/*
	 #######  ########   ######  ######## ########  ##     ## ######## ########   ######
	##     ## ##     ## ##    ## ##       ##     ## ##     ## ##       ##     ## ##    ##
	##     ## ##     ## ##       ##       ##     ## ##     ## ##       ##     ## ##
	##     ## ########   ######  ######   ########  ##     ## ######   ########   ######
	##     ## ##     ##       ## ##       ##   ##    ##   ##  ##       ##   ##         ##
	##     ## ##     ## ##    ## ##       ##    ##    ## ##   ##       ##    ##  ##    ##
	 #######  ########   ######  ######## ##     ##    ###    ######## ##     ##  ######
	*/
	
	/**
	 * Action for registering an observer.
	 *
	 * @uses self::_register_observer()
	 *
	 * @return bool
	 */
	function action__register_observer( _The_Reporter__Observer $observer ) {
		if ( 'the_reporter/register_observer' !== current_action() )
			return;
			
		$this->_register_observer( $observer );
	}
	
	/**
	 * Register an observer.
	 *
	 * @param _The_Reporter__Observer $observer Observer object.
	 *
	 * @uses self::observer_exists()
	 *
	 * @return bool If observer was registered.
	 */
	protected function _register_observer( _The_Reporter__Observer $observer ) {
		if ( 
			!is_callable( array( $observer, 'is_configured' ) )
			|| !$observer->is_configured()
		) {
			trigger_error( 'Observer ' . get_class( $observer ) . ' is not properly configured.' );
			return false;
		}
			
		if ( $this->observer_exists( $observer->get_id() ) ) {
			trigger_error( 'Observer ' . $observer->get_id() . ' already exists.' );
			return false;
		}
			
		$this->_observers[$observer->get_id()] = $observer;
		do_action( 'the_reporter/registered_observer', $observer->get_id() );
			
		return $this->observer_exists( $observer->get_id() );
	}
	
	/**
	 * Check if there are registered observers.
	 *
	 * @return bool
	 */
	function has_observers() {
		return !empty( $this->_observers );
	}
	
	/**
	 * Check if observer exists.
	 *
	 * @param string $observer_id ID of observer to check.
	 *
	 * @return bool If observer exists.
	 */
	function observer_exists( string $observer_id ) {
		return (
			$this->has_observers()
			&& array_key_exists( $observer_id, $this->_observers )
		);
	}
	
	/**
	 * Get observer.
	 *
	 * @param string $observer_id ID of observer to get.
	 *
	 * @uses self::observer_exists()
	 *
	 * @return _The_Reporter__Observer|false
	 */
	function get_observer( string $observer_id ) {
		return $this->observer_exists( $observer_id )
			? $this->_observers[$observer_id]
			: false;
	}
	
	
	/*
	 #######  ########   ######  ######## ########  ##     ##    ###    ######## ####  #######  ##    ##  ######
	##     ## ##     ## ##    ## ##       ##     ## ##     ##   ## ##      ##     ##  ##     ## ###   ## ##    ##
	##     ## ##     ## ##       ##       ##     ## ##     ##  ##   ##     ##     ##  ##     ## ####  ## ##
	##     ## ########   ######  ######   ########  ##     ## ##     ##    ##     ##  ##     ## ## ## ##  ######
	##     ## ##     ##       ## ##       ##   ##    ##   ##  #########    ##     ##  ##     ## ##  ####       ##
	##     ## ##     ## ##    ## ##       ##    ##    ## ##   ##     ##    ##     ##  ##     ## ##   ### ##    ##
	 #######  ########   ######  ######## ##     ##    ###    ##     ##    ##    ####  #######  ##    ##  ######
	*/

	function action__process_observation( _The_Reporter__Observation $observation ) {
		if ( $observation->is_reportable() ) {
			$this->_add_observation( $observation );
			foreach ( $this->get_recipients() as $recipient )
				$recipient->maybe_report_observation( $observation );
		}
	}
	
	protected function _add_observation( _The_Reporter__Observation $observation ) {
		$key = md5( serialize( $observation ) );
		$this->_observations[$key] = $observation;
		return $key;
	}
	
	protected function _has_observation( string $key ) {
		return array_key_exists( $key, $this->_get_observations() );
	}
	
	protected function _get_observations() {
		return $this->_observations;
	}
	
	protected function _has_observations() {
		return !empty( $this->_get_observations() );
	}
	
	
	/*
	########  ########  ######  #### ########  #### ######## ##    ## ########  ######
	##     ## ##       ##    ##  ##  ##     ##  ##  ##       ###   ##    ##    ##    ##
	##     ## ##       ##        ##  ##     ##  ##  ##       ####  ##    ##    ##
	########  ######   ##        ##  ########   ##  ######   ## ## ##    ##     ######
	##   ##   ##       ##        ##  ##         ##  ##       ##  ####    ##          ##
	##    ##  ##       ##    ##  ##  ##         ##  ##       ##   ###    ##    ##    ##
	##     ## ########  ######  #### ##        #### ######## ##    ##    ##     ######
	*/

	function action__register_recipient( _The_Reporter__Recipient $recipient ) {
		if ( 'the_reporter/register_recipient' !== current_action() )
			return;
			
		$this->_register_recipient( $recipient );
	}
	
	private function _register_recipient( _The_Reporter__Recipient $recipient ) {
		if ( $this->recipient_exists( $recipient->get_id() ) ) {
			trigger_error( 'Recipient ' . $recipient->get_id() . ' already exists.' );
			return false;
		}
		
		$this->_recipients[$recipient->get_id()] = $recipient;
		do_action( 'the_reporter/registered_recipient', $recipient->get_id() );
		
		return $this->recipient_exists( $recipient->get_id() );
	}
	
	/**
	 * Get registered recipient objects.
	 *
	 * @return array
	 */
	function get_recipients() {
		return $this->_recipients;
	}
	
	/**
	 * Check if there are registered recipients.
	 *
	 * @uses self::get_recipients()
	 *
	 * @return bool
	 */
	function has_recipients() {
		return (
			!empty( $this->get_recipients() ) 
			&& is_array( $this->get_recipients() )
		);
	}
	
	/**
	 * Check if recipient is registered.
	 *
	 * @param string $recipient_id
	 *
	 * @uses self::get_recipients()
	 *
	 * @return bool
	 */
	function recipient_exists( string $recipient_id ) {
		return (
			$this->has_recipients()
			&& array_key_exists( $recipient_id, $this->get_recipients() )
		);
	}
	
	
	/*
	 ######   #######  ##    ## ##    ## ########  ######  ######## ####  #######  ##    ##  ######
	##    ## ##     ## ###   ## ###   ## ##       ##    ##    ##     ##  ##     ## ###   ## ##    ##
	##       ##     ## ####  ## ####  ## ##       ##          ##     ##  ##     ## ####  ## ##
	##       ##     ## ## ## ## ## ## ## ######   ##          ##     ##  ##     ## ## ## ##  ######
	##       ##     ## ##  #### ##  #### ##       ##          ##     ##  ##     ## ##  ####       ##
	##    ## ##     ## ##   ### ##   ### ##       ##    ##    ##     ##  ##     ## ##   ### ##    ##
	 ######   #######  ##    ## ##    ## ########  ######     ##    ####  #######  ##    ##  ######
	*/

	function action__init_connection( _The_Reporter__Connection $connection ) {
		if ( 'the_reporter/init_connection' !== current_action() )
			return;
			
		if ( !$this->connection_exists( $connection::CONNECTION_ID ) )
			$this->_add_connection( $connection::CONNECTION_ID, get_parent_class( $connection ) );
		
		if ( $this->_has_observations() )
			foreach ( $this->_get_observations() as $observation )
				$connection->maybe_report_observation( $observation );
	}
	
	function get_connections() {
		return $this->_connections;
	}
	
	function connection_exists( string $connection_id ) {
		return array_key_exists( $connection_id, $this->get_connections() );
	}
	
	protected function _add_connection( string $connection_id, string $connection_abstract_name ) {
		$this->connection_exists( $connection_id ) || $this->_connections[$connection_id] = $connection_abstract_name;
	}
	
}

if ( !function_exists( 'The_Reporter' ) ) {
	
	/**
	 * Main instance of The Reporter.
	 *
	 * Returns the main instance of The Reporter to prevent the need to use globals.
	 *
	 * @return The_Reporter
	 */
	function The_Reporter() {
		return The_Reporter::get_instance();
	}
	
}

The_Reporter();
require_once 'recipients/success-agency.php';

?>