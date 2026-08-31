<?php
/**
 * Elementor Send SMS action tests.
 *
 * @package SMS_Aero_Elementor
 */

use PHPUnit\Framework\TestCase;
use SMS_Aero_Elementor\Send_SMS_Action;
use SMS_Aero_Elementor\Settings;

final class SendSmsActionTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['sms_aero_test_options'] = array(
			Settings::OPTION_NAME => array(
				'login'   => 'login@example.test',
				'api_key' => 'secret-key',
				'sign'    => 'GlobalSign',
			),
		);
		$GLOBALS['sms_aero_test_uuid'] = 0;
		$_POST                          = array();
	}

	public function test_processes_unique_recipients_with_one_batch_and_one_safe_error() {
		$logs   = new SMS_Aero_Test_Log_Repository();
		$client = new SMS_Aero_Test_SMS_Client(
			array(
				'79990000000' => 'accepted',
				'79991111111' => 'rejected',
			)
		);
		$record = new SMS_Aero_Test_Form_Record(
			array(
				'id'                                  => 'contact-form',
				'form_name'                           => 'Contact',
				Send_SMS_Action::RECIPIENTS_CONTROL => "+7 (999) 000-00-00, 8 999 000 00 00\ninvalid\n+7 999 111-11-11",
				Send_SMS_Action::MESSAGE_CONTROL    => 'New request from [field id="name"]',
				Send_SMS_Action::SENDER_CONTROL     => '',
			),
			array(
				'name' => array(
					'id'    => 'name',
					'title' => 'Name',
					'value' => 'Alice',
				),
				'email' => array(
					'id'    => 'email',
					'title' => 'Email',
					'value' => 'alice@example.test',
				),
			)
		);
		$ajax   = new SMS_Aero_Test_Ajax_Handler();
		$action = new Send_SMS_Action( $logs, $client );

		$action->run( $record, $ajax );

		$this->assertCount( 2, $client->calls );
		$this->assertSame( '79990000000', $client->calls[0]['number'] );
		$this->assertSame( '79991111111', $client->calls[1]['number'] );
		$message = "New request from Alice\nName: Alice\nEmail: alice@example.test";
		$this->assertSame( $message, $client->calls[0]['message'] );
		$this->assertSame( $message, $client->calls[1]['message'] );
		$this->assertSame( 'GlobalSign', $client->calls[0]['sender'] );
		$this->assertSame( 'login@example.test', $client->calls[0]['login'] );
		$this->assertSame( 'secret-key', $client->calls[0]['api_key'] );

		$this->assertCount( 3, $logs->rows );
		$this->assertSame( array( 'invalid_input', 'accepted', 'rejected' ), array_column( $logs->rows, 'outcome' ) );
		$this->assertCount( 1, array_unique( array_column( $logs->rows, 'batch_uuid' ) ) );
		$this->assertCount( 3, array_unique( array_column( $logs->rows, 'attempt_uuid' ) ) );
		$this->assertCount( 2, $logs->updates );
		$this->assertSame( array( $message, $message, $message ), array_column( $logs->rows, 'message' ) );

		$this->assertFalse( $ajax->is_success );
		$this->assertSame(
			array( 'One or more SMS notifications could not be sent. Please contact the site administrator.' ),
			$ajax->messages
		);
		$this->assertStringNotContainsString( '7999', $ajax->messages[0] );
		$this->assertStringNotContainsString( 'secret-key', $ajax->messages[0] );
	}

	public function test_all_accepted_recipients_leave_form_successful() {
		$logs   = new SMS_Aero_Test_Log_Repository();
		$client = new SMS_Aero_Test_SMS_Client(
			array(
				'79990000000' => 'accepted',
				'79991111111' => 'accepted',
			)
		);
		$record = new SMS_Aero_Test_Form_Record(
			array(
				Send_SMS_Action::RECIPIENTS_CONTROL => '79990000000,79991111111',
				Send_SMS_Action::MESSAGE_CONTROL    => 'Static message',
			)
		);
		$ajax   = new SMS_Aero_Test_Ajax_Handler();

		( new Send_SMS_Action( $logs, $client ) )->run( $record, $ajax );

		$this->assertTrue( $ajax->is_success );
		$this->assertSame( array(), $ajax->messages );
		$this->assertSame( array( 'accepted', 'accepted' ), array_column( $logs->rows, 'outcome' ) );
	}

	public function test_blank_template_sends_all_fields() {
		$logs   = new SMS_Aero_Test_Log_Repository();
		$client = new SMS_Aero_Test_SMS_Client( array( '79990000000' => 'accepted' ) );
		$record = new SMS_Aero_Test_Form_Record(
			array(
				Send_SMS_Action::RECIPIENTS_CONTROL => '79990000000',
				Send_SMS_Action::MESSAGE_CONTROL    => '',
			),
			array(
				'name' => array(
					'id'    => 'name',
					'title' => 'Name',
					'value' => 'Alice',
				),
			)
		);
		$ajax   = new SMS_Aero_Test_Ajax_Handler();

		( new Send_SMS_Action( $logs, $client ) )->run( $record, $ajax );

		$this->assertTrue( $ajax->is_success );
		$this->assertCount( 1, $client->calls );
		$this->assertSame( 'Name: Alice', $client->calls[0]['message'] );
		$this->assertSame( 'Name: Alice', $logs->rows[0]['message'] );
	}

	public function test_skips_when_a_previous_action_failed() {
		$logs   = new SMS_Aero_Test_Log_Repository();
		$client = new SMS_Aero_Test_SMS_Client( array() );
		$record = new SMS_Aero_Test_Form_Record(
			array(
				Send_SMS_Action::RECIPIENTS_CONTROL => '79990000000',
				Send_SMS_Action::MESSAGE_CONTROL    => 'Message',
			)
		);
		$ajax             = new SMS_Aero_Test_Ajax_Handler();
		$ajax->is_success = false;

		( new Send_SMS_Action( $logs, $client ) )->run( $record, $ajax );

		$this->assertSame( array(), $logs->rows );
		$this->assertSame( array(), $client->calls );
	}

	public function test_blank_template_and_no_fields_is_invalid() {
		$logs   = new SMS_Aero_Test_Log_Repository();
		$client = new SMS_Aero_Test_SMS_Client( array() );
		$record = new SMS_Aero_Test_Form_Record(
			array(
				Send_SMS_Action::RECIPIENTS_CONTROL => '79990000000',
				Send_SMS_Action::MESSAGE_CONTROL    => '',
			)
		);
		$ajax   = new SMS_Aero_Test_Ajax_Handler();

		( new Send_SMS_Action( $logs, $client ) )->run( $record, $ajax );

		$this->assertSame( array(), $client->calls );
		$this->assertSame( 'invalid_input', $logs->rows[0]['outcome'] );
		$this->assertSame( 'empty_message', $logs->rows[0]['error_code'] );
		$this->assertCount( 1, $ajax->messages );
	}

	public function test_logs_missing_configuration_without_calling_transport() {
		$GLOBALS['sms_aero_test_options'][ Settings::OPTION_NAME ] = array();
		$logs   = new SMS_Aero_Test_Log_Repository();
		$client = new SMS_Aero_Test_SMS_Client( array() );
		$record = new SMS_Aero_Test_Form_Record(
			array(
				Send_SMS_Action::RECIPIENTS_CONTROL => '79990000000',
				Send_SMS_Action::MESSAGE_CONTROL    => 'Message',
			)
		);
		$ajax   = new SMS_Aero_Test_Ajax_Handler();

		( new Send_SMS_Action( $logs, $client ) )->run( $record, $ajax );

		$this->assertSame( array(), $client->calls );
		$this->assertCount( 1, $logs->rows );
		$this->assertSame( 'configuration_error', $logs->rows[0]['outcome'] );
		$this->assertSame( 'missing_sender', $logs->rows[0]['error_code'] );
		$this->assertCount( 1, $ajax->messages );
	}
}

final class SMS_Aero_Test_Log_Repository {
	public $rows = array();
	public $updates = array();

	public function insert( $row ) {
		if ( ! isset( $row['attempt_uuid'] ) ) {
			$row['attempt_uuid'] = wp_generate_uuid4();
		}
		if ( ! isset( $row['outcome'] ) ) {
			$row['outcome'] = 'pending';
		}
		$this->rows[] = $row;

		return count( $this->rows );
	}

	public function update( $id, $result ) {
		$this->updates[] = array(
			'id'     => $id,
			'result' => $result,
		);
		$this->rows[ $id - 1 ] = array_merge( $this->rows[ $id - 1 ], $result );

		return true;
	}
}

final class SMS_Aero_Test_SMS_Client {
	public $calls = array();
	private $outcomes;

	public function __construct( $outcomes ) {
		$this->outcomes = $outcomes;
	}

	public function send( $number, $message, $sender, $login, $api_key ) {
		$this->calls[] = compact( 'number', 'message', 'sender', 'login', 'api_key' );
		$outcome      = isset( $this->outcomes[ $number ] ) ? $this->outcomes[ $number ] : 'transport_error';

		return array(
			'outcome'       => $outcome,
			'http_status'   => 'accepted' === $outcome ? 200 : 400,
			'error_code'    => 'accepted' === $outcome ? '' : 'provider_rejected',
			'error_message' => 'accepted' === $outcome ? '' : 'Rejected',
			'raw_response'  => '{}',
		);
	}
}

final class SMS_Aero_Test_Form_Record {
	private $settings;
	private $fields;

	public function __construct( $settings, $fields = array() ) {
		$this->settings = $settings;
		$this->fields   = $fields;
	}

	public function get( $property ) {
		if ( 'form_settings' === $property ) {
			return $this->settings;
		}

		return 'fields' === $property ? $this->fields : null;
	}

	public function replace_setting_shortcodes( $template ) {
		foreach ( $this->fields as $id => $field ) {
			$value = isset( $field['value'] ) && is_scalar( $field['value'] ) ? (string) $field['value'] : '';
			$template = str_replace( '[field id="' . $id . '"]', $value, $template );
		}

		return $template;
	}
}

final class SMS_Aero_Test_Ajax_Handler {
	public $is_success = true;
	public $messages = array();

	public function add_error_message( $message ) {
		$this->is_success = false;
		$this->messages[] = $message;
	}
}
