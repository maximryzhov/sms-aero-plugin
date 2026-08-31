<?php
/**
 * SMS Aero API v2 client.
 *
 * @package SMS_Aero_Elementor
 */

namespace SMS_Aero_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a single SMS and translates provider responses into audit data.
 */
final class SMS_Aero_Client {

	/** SMS Aero API endpoint. */
	const ENDPOINT = 'https://gate.smsaero.ru/v2/sms/send';

	/** @var callable|null HTTP transport override used by tests. */
	private $transport;

	/**
	 * Constructor.
	 *
	 * @param callable|null $transport Optional transport with wp_remote_post-compatible arguments.
	 */
	public function __construct( $transport = null ) {
		$this->transport = is_callable( $transport ) ? $transport : null;
	}

	/**
	 * Send one SMS.
	 *
	 * @param string $number  Normalized destination number.
	 * @param string $message Plain-text SMS content.
	 * @param string $sender  Approved SMS Aero sign.
	 * @param string $login   SMS Aero login.
	 * @param string $api_key SMS Aero API key.
	 * @return array<string,mixed>
	 */
	public function send( $number, $message, $sender, $login, $api_key ) {
		$args = array(
			'method'      => 'POST',
			'timeout'     => 15,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $login . ':' . $api_key ),
			),
			'body'        => array(
				'number' => $number,
				'text'   => $message,
				'sign'   => $sender,
			),
		);

		$response = $this->request( self::ENDPOINT, $args );

		if ( is_wp_error( $response ) ) {
			return $this->from_wp_error( $response );
		}

		return $this->from_response( $response );
	}

	/**
	 * Perform the HTTP request.
	 *
	 * @param string              $url  Endpoint URL.
	 * @param array<string,mixed> $args WordPress HTTP arguments.
	 * @return array|\WP_Error
	 */
	private function request( $url, $args ) {
		if ( null !== $this->transport ) {
			return call_user_func( $this->transport, $url, $args );
		}

		return wp_remote_post( $url, $args );
	}

	/**
	 * Normalize a WordPress HTTP error without retaining its attached data.
	 *
	 * @param \WP_Error $error Transport error.
	 * @return array<string,mixed>
	 */
	private function from_wp_error( $error ) {
		$code       = sanitize_key( (string) $error->get_error_code() );
		$message    = sanitize_text_field( (string) $error->get_error_message() );
		$ambiguous  = (bool) preg_match( '/timeout|timed_out|operation_timed|connection_closed|http_request_failed/', $code );
		$diagnostic = wp_json_encode(
			array(
				'code'    => $code,
				'message' => $message,
			),
			JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);

		return $this->result(
			array(
				'outcome'       => $ambiguous ? 'unknown' : 'transport_error',
				'error_code'    => $code,
				'error_message' => $message,
				'raw_response'  => false === $diagnostic ? '' : $diagnostic,
			)
		);
	}

	/**
	 * Normalize an HTTP response.
	 *
	 * @param array<string,mixed> $response WordPress HTTP response.
	 * @return array<string,mixed>
	 */
	private function from_response( $response ) {
		$http_status = (int) wp_remote_retrieve_response_code( $response );
		$raw         = (string) wp_remote_retrieve_body( $response );
		$decoded     = json_decode( $raw, true );

		if ( $http_status < 200 || $http_status >= 300 ) {
			return $this->result(
				array(
					'outcome'       => 'rejected',
					'http_status'   => $http_status,
					'error_code'    => 'http_error',
					'error_message' => $this->provider_message( $decoded, sprintf( 'HTTP %d', $http_status ) ),
					'raw_response'  => $raw,
				)
			);
		}

		if ( ! is_array( $decoded ) ) {
			return $this->result(
				array(
					'outcome'       => 'response_error',
					'http_status'   => $http_status,
					'error_code'    => 'invalid_json',
					'error_message' => 'SMS Aero returned an invalid JSON response.',
					'raw_response'  => $raw,
				)
			);
		}

		if ( empty( $decoded['success'] ) ) {
			return $this->result(
				array(
					'outcome'       => 'rejected',
					'http_status'   => $http_status,
					'error_code'    => 'provider_rejected',
					'error_message' => $this->provider_message( $decoded, 'SMS Aero rejected the request.' ),
					'raw_response'  => $raw,
				)
			);
		}

		$provider = $this->provider_record( isset( $decoded['data'] ) ? $decoded['data'] : null );
		if ( null === $provider ) {
			return $this->result(
				array(
					'outcome'       => 'response_error',
					'http_status'   => $http_status,
					'error_code'    => 'missing_provider_data',
					'error_message' => 'SMS Aero accepted the request but returned no message record.',
					'raw_response'  => $raw,
				)
			);
		}

		return $this->result(
			array(
				'outcome'                => 'accepted',
				'http_status'            => $http_status,
				'provider_message_id'    => isset( $provider['id'] ) ? (string) $provider['id'] : '',
				'provider_status'        => isset( $provider['status'] ) ? (string) $provider['status'] : '',
				'provider_extend_status' => isset( $provider['extendStatus'] ) ? (string) $provider['extendStatus'] : '',
				'raw_response'           => $raw,
			)
		);
	}

	/**
	 * Extract one provider message object from documented object/array variants.
	 *
	 * @param mixed $data Response data.
	 * @return array<string,mixed>|null
	 */
	private function provider_record( $data ) {
		if ( ! is_array( $data ) || empty( $data ) ) {
			return null;
		}

		if ( array_key_exists( 'id', $data ) || array_key_exists( 'status', $data ) ) {
			return $data;
		}

		foreach ( $data as $record ) {
			if ( is_array( $record ) ) {
				return $record;
			}
		}

		return null;
	}

	/**
	 * Extract a safe provider message.
	 *
	 * @param mixed  $decoded Response JSON.
	 * @param string $fallback Fallback text.
	 * @return string
	 */
	private function provider_message( $decoded, $fallback ) {
		if ( is_array( $decoded ) && isset( $decoded['message'] ) && is_scalar( $decoded['message'] ) ) {
			$message = sanitize_text_field( (string) $decoded['message'] );
			if ( '' !== $message ) {
				return $message;
			}
		}

		return $fallback;
	}

	/**
	 * Apply stable defaults to a client result.
	 *
	 * @param array<string,mixed> $overrides Result values.
	 * @return array<string,mixed>
	 */
	private function result( $overrides ) {
		return wp_parse_args(
			$overrides,
			array(
				'outcome'                => 'unknown',
				'http_status'            => null,
				'provider_message_id'    => '',
				'provider_status'        => '',
				'provider_extend_status' => '',
				'error_code'             => '',
				'error_message'          => '',
				'raw_response'           => '',
			)
		);
	}
}
