<?php
/**
 * SMS Aero client tests.
 *
 * @package SMS_Aero_Elementor
 */

use PHPUnit\Framework\TestCase;
use SMS_Aero_Elementor\SMS_Aero_Client;

final class SmsAeroClientTest extends TestCase {

	public function test_builds_one_secure_post_request() {
		$calls = array();
		$client = new SMS_Aero_Client(
			static function ( $url, $args ) use ( &$calls ) {
				$calls[] = array( $url, $args );

				return self::response(
					200,
					array(
						'success' => true,
						'data'    => array(
							'id'           => 42,
							'status'       => 0,
							'extendStatus' => 'queue',
						),
					)
				);
			}
		);

		$result = $client->send( '79990000000', 'Test message', 'MySign', 'login@example.test', 'secret-key' );

		$this->assertCount( 1, $calls );
		$this->assertSame( SMS_Aero_Client::ENDPOINT, $calls[0][0] );
		$this->assertSame( 'POST', $calls[0][1]['method'] );
		$this->assertSame( 15, $calls[0][1]['timeout'] );
		$this->assertSame( 0, $calls[0][1]['redirection'] );
		$this->assertTrue( $calls[0][1]['sslverify'] );
		$this->assertSame( 'application/json', $calls[0][1]['headers']['Accept'] );
		$this->assertSame( 'Basic ' . base64_encode( 'login@example.test:secret-key' ), $calls[0][1]['headers']['Authorization'] );
		$this->assertSame(
			array(
				'number' => '79990000000',
				'text'   => 'Test message',
				'sign'   => 'MySign',
			),
			$calls[0][1]['body']
		);
		$this->assertSame( 'accepted', $result['outcome'] );
		$this->assertSame( '42', $result['provider_message_id'] );
		$this->assertSame( '0', $result['provider_status'] );
		$this->assertSame( 'queue', $result['provider_extend_status'] );
	}

	public function test_accepts_documented_data_array_variant() {
		$client = new SMS_Aero_Client(
			static function () {
				return self::response(
					200,
					array(
						'success' => true,
						'data'    => array(
							array(
								'id'           => 'abc',
								'status'       => 8,
								'extendStatus' => 'moderation',
							),
						),
					)
				);
			}
		);

		$result = $client->send( '79990000000', 'Message', 'Sign', 'login', 'key' );

		$this->assertSame( 'accepted', $result['outcome'] );
		$this->assertSame( 'abc', $result['provider_message_id'] );
		$this->assertSame( '8', $result['provider_status'] );
		$this->assertSame( 'moderation', $result['provider_extend_status'] );
	}

	public function test_classifies_provider_and_http_rejections() {
		$provider_client = new SMS_Aero_Client(
			static function () {
				return self::response(
					200,
					array(
						'success' => false,
						'message' => 'Invalid sender',
					)
				);
			}
		);
		$http_client = new SMS_Aero_Client(
			static function () {
				return self::response( 403, array( 'message' => 'Forbidden' ) );
			}
		);

		$provider = $provider_client->send( '79990000000', 'Message', 'Sign', 'login', 'key' );
		$http     = $http_client->send( '79990000000', 'Message', 'Sign', 'login', 'key' );

		$this->assertSame( 'rejected', $provider['outcome'] );
		$this->assertSame( 'provider_rejected', $provider['error_code'] );
		$this->assertSame( 'Invalid sender', $provider['error_message'] );
		$this->assertSame( 'rejected', $http['outcome'] );
		$this->assertSame( 403, $http['http_status'] );
		$this->assertSame( 'http_error', $http['error_code'] );
	}

	public function test_classifies_malformed_success_responses() {
		$invalid_json_client = new SMS_Aero_Client(
			static function () {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => '<html>not JSON</html>',
				);
			}
		);
		$missing_data_client = new SMS_Aero_Client(
			static function () {
				return self::response( 200, array( 'success' => true ) );
			}
		);

		$invalid = $invalid_json_client->send( '79990000000', 'Message', 'Sign', 'login', 'key' );
		$missing = $missing_data_client->send( '79990000000', 'Message', 'Sign', 'login', 'key' );

		$this->assertSame( 'response_error', $invalid['outcome'] );
		$this->assertSame( 'invalid_json', $invalid['error_code'] );
		$this->assertSame( '<html>not JSON</html>', $invalid['raw_response'] );
		$this->assertSame( 'response_error', $missing['outcome'] );
		$this->assertSame( 'missing_provider_data', $missing['error_code'] );
	}

	public function test_redacts_wp_error_data_and_marks_timeout_ambiguous() {
		$client = new SMS_Aero_Client(
			static function () {
				return new WP_Error(
					'http_request_failed',
					'Operation timed out',
					array(
						'Authorization' => 'Basic leaked-secret',
						'api_key'       => 'leaked-secret',
					)
				);
			}
		);

		$result = $client->send( '79990000000', 'Message', 'Sign', 'login', 'secret-key' );

		$this->assertSame( 'unknown', $result['outcome'] );
		$this->assertSame( 'http_request_failed', $result['error_code'] );
		$this->assertStringNotContainsString( 'secret-key', $result['raw_response'] );
		$this->assertStringNotContainsString( 'leaked-secret', $result['raw_response'] );
		$this->assertSame(
			array(
				'code'    => 'http_request_failed',
				'message' => 'Operation timed out',
			),
			json_decode( $result['raw_response'], true )
		);
	}

	public function test_marks_clear_local_transport_failure() {
		$client = new SMS_Aero_Client(
			static function () {
				return new WP_Error( 'invalid_url', 'The URL is invalid.' );
			}
		);

		$result = $client->send( '79990000000', 'Message', 'Sign', 'login', 'key' );

		$this->assertSame( 'transport_error', $result['outcome'] );
		$this->assertSame( 'invalid_url', $result['error_code'] );
	}

	private static function response( $status, $body ) {
		return array(
			'response' => array( 'code' => $status ),
			'body'     => wp_json_encode( $body ),
		);
	}
}
