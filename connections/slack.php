<?php

if ( !defined( 'ABSPATH' ) || !function_exists( 'add_filter' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

abstract class _The_Reporter__Connection_Slack extends _The_Reporter__Connection {
	
	const CONNECTION_ID = 'slack';
	
	protected $url = null;
	protected $fallback_email = null;
	
	protected $_payload = array();
	protected $_observation = null;
	
	function maybe_report_observation( _The_Reporter__Observation $observation ) {
		if ( empty( $this->url ) )
			return false;
			
		$this->_observation = &$observation;
			
		$this->_generate_payload();
		$this->_send_payload();
	}
	
	protected function _generate_payload() {
		$this->_payload = array(
			'username' => 'The Reporter',
			'icon_emoji' => ':male-detective:',
			'mrkdwn' => true,
			'attachments' => array(
				array(
					'fallback' => wp_kses_post( get_option( 'blogname' ) . ' - ' . $this->_observation->get( 'text' ) ),
					'title' => get_option( 'blogname' ),
					'title_link' => esc_url( home_url() ),
					'text' => wp_kses_post( $this->_observation->get( 'text' ) ),
					'pretext' => $this->_random_pretext(),
					'color' => 'warning',
					'ts' => time(),
					'footer' => 'The Reporter',
					'mrkdwn_in' => array( 'fields' ),
				)
			)
		);
		
		if ( $this->_has_extra_fields() )
			foreach ( $this->_get_extra_fields() as $var => $value )
				if ( is_array( $value ) )
					$this->_payload['attachments'][0]['fields'][] = $value;
				else
					$this->_payload['attachments'][0]['fields'][] = array(
						'title' => esc_html( $var ),
						'value' => esc_html( $value ),
						'short' => 16 > strlen( $var ) && 16 > strlen( $value )
					);
					
		if ( !empty( $this->_observation->get( 'user' ) ) ) {
			if ( is_string( $this->_observation->get( 'user' ) ) )
				$this->_payload['attachments'][0]['author_name'] = 'Guest (' . $this->_observation->get( 'user' ) . ')';
			else {
				$user = get_userdata( $this->_observation->get( 'user' ) );
				$this->_payload['attachments'][0]['author_name'] = esc_html( $user->get( 'display_name' ) );
				$this->_payload['attachments'][0]['author_link'] = esc_url(
					add_query_arg(
						array( 'user_id' => $this->_observation->get( 'user' ) ),
						admin_url( 'user-edit.php' )
					)
				);
			}
		}
		
		$this->_maybe_add_mentions();
	}
	
	protected function _random_pretext() {
		$pretexts = apply_filters(
			'the_reporter/connection/slack/pretexts',
			array(
				'Uh oh!',
				'Mayday! Mayday! Mayday!',
				'Heeeelllppp!',
				'Ummmmm...',
				'Houston, we have a problem.',
				'Get the President on the phone.',
				'Avengers, assemble!',
				'"Error is discipline through which we advance." -William Ellery Channing',
				'If you build it, they will come. :bug:',
				'"To me, error analysis is the sweet spot for improvement." -Donald Norman',
				'"Unreasonable haste is the direct road to error." -Moliere',
				'"We are built to make mistakes, coded for error." -Lewis Thomas',
				'"Failure is essential. Trial and error is necessary." -David Bergen',
				'Keep calm and call Jack Bauer.',
				'"Inconceivable!" -Vizzini',
			)
		);

		return $pretexts[mt_rand( 0, count( $pretexts ) - 1 )];
	}
	
	protected function _get_extra_fields() {
		return array_filter(
			get_object_vars( $this->_observation ),
			function ( $key ) { return !in_array( $key, $this->_observation->get_default_properties() ); },
			ARRAY_FILTER_USE_KEY
		);
	}
	
	protected function _has_extra_fields() {
		return !empty( $this->_get_extra_fields() );
	}
	
	protected function _maybe_add_mentions() {}
	
	protected function _add_mentions( $users ) {
		if ( empty( $users ) )
			return;
			
		is_array( $users ) || $users = explode( ' ', $users );
			
		$users = implode( $users, '><' );
			
		$this->_payload['attachments'][0]['fallback'] .= ' (<' . $users . '>)';
		$this->_payload['attachments'][0]['text']     .= ' (<' . $users . '>)';
	}
	
	private function _send_payload() {
		$request = wp_remote_post(
			$this->url,
			array( 'body' => array(
				'payload' => json_encode( $this->_payload ),
			) )
		);

		if ( is_wp_error( $request ) ) {
			$email_sent = false;

			if ( is_email( $this->fallback_email ) ) {

				if ( wp_mail(
					$this->fallback_email,
					'Fallback for The Reporter',
					"Response:\n" . json_encode( $request ) . "\n\nPayload:\n" . json_encode( $this->_payload ),
					'From: ' . get_option( 'admin_email' )
				) )
					$email_sent = true;

				else if ( mail(
					$this->fallback_email,
					'Fallback for The Success Enforcer - Slack',
					"Response:\n" . json_encode( $request ) . "\n\nPayload:\n" . json_encode( $this->_payload ),
					'From: ' . get_option( 'admin_email' )
				) )
					$email_sent = true;

			}

			error_log( 'The Reporter: Unable to post Slack message; ' . ( $email_sent ? ' error sent to ' . $this->fallback_email . '.' : '' ) );

		}
	}
	
}

?>