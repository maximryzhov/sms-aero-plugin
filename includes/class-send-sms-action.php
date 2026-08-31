<?php
/**
 * Elementor Pro Send SMS form action.
 *
 * @package SMS_Aero_Elementor
 */

namespace SMS_Aero_Elementor;

use Elementor\Controls_Manager;
use ElementorPro\Modules\Forms\Classes\Action_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sends a configured message to one or more configured phone numbers.
 */
final class Send_SMS_Action extends Action_Base {

	/** Action identifier stored by Elementor. */
	const ACTION_NAME = 'sms_aero_send_sms';

	/** Recipient control ID. */
	const RECIPIENTS_CONTROL = 'sms_aero_recipients';

	/** Message control ID. */
	const MESSAGE_CONTROL = 'sms_aero_message';

	/** Sender control ID. */
	const SENDER_CONTROL = 'sms_aero_sender';

	/** @var Log_Repository|object */
	private $logs;

	/** @var SMS_Aero_Client|object */
	private $client;

	/** @var Submission_Mapper|object */
	private $mapper;

	/**
	 * Constructor.
	 *
	 * Optional dependencies make the action testable without network access.
	 *
	 * @param object|null $logs   Log repository.
	 * @param object|null $client SMS Aero client.
	 * @param object|null $mapper Submission mapper.
	 */
	public function __construct( $logs = null, $client = null, $mapper = null ) {
		$this->logs   = null === $logs ? new Log_Repository() : $logs;
		$this->client = null === $client ? new SMS_Aero_Client() : $client;
		$this->mapper = null === $mapper ? new Submission_Mapper() : $mapper;
	}

	/**
	 * Get the action identifier.
	 *
	 * @return string
	 */
	public function get_name() {
		return self::ACTION_NAME;
	}

	/**
	 * Get the editor label.
	 *
	 * @return string
	 */
	public function get_label() {
		return esc_html__( 'Send SMS', 'sms-aero-elementor' );
	}

	/**
	 * Add action-specific Form widget controls.
	 *
	 * @param \Elementor\Widget_Base $widget Elementor Form widget.
	 * @return void
	 */
	public function register_settings_section( $widget ) {
		$widget->start_controls_section(
			'section_sms_aero',
			array(
				'label'     => esc_html__( 'SMS Aero', 'sms-aero-elementor' ),
				'condition' => array(
					'submit_actions' => self::ACTION_NAME,
				),
			)
		);

		$widget->add_control(
			self::RECIPIENTS_CONTROL,
			array(
				'label'       => esc_html__( 'Recipient phone numbers', 'sms-aero-elementor' ),
				'type'        => Controls_Manager::TEXTAREA,
				'label_block' => true,
				'render_type' => 'none',
				'dynamic'     => array( 'active' => false ),
				'description' => esc_html__( 'Enter one phone number or a comma/newline-separated list. Numbers are configured here and are not read from submitted fields.', 'sms-aero-elementor' ),
			)
		);

		$widget->add_control(
			self::MESSAGE_CONTROL,
			array(
				'label'       => esc_html__( 'Message', 'sms-aero-elementor' ),
				'type'        => Controls_Manager::TEXTAREA,
				'label_block' => true,
				'rows'        => 6,
				'render_type' => 'none',
				'description' => esc_html__( 'Optional leading text. Elementor field shortcodes are supported, and all submitted fields are appended automatically as label: value lines.', 'sms-aero-elementor' ),
			)
		);

		$widget->add_control(
			self::SENDER_CONTROL,
			array(
				'label'       => esc_html__( 'Sender override', 'sms-aero-elementor' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'render_type' => 'none',
				'dynamic'     => array( 'active' => false ),
				'description' => esc_html__( 'Optional static sender. It must be approved in SMS Aero. Leave blank to use the global sender.', 'sms-aero-elementor' ),
			)
		);

		$widget->end_controls_section();
	}

	/**
	 * Process all configured recipients independently.
	 *
	 * @param \ElementorPro\Modules\Forms\Classes\Form_Record  $record       Submission record.
	 * @param \ElementorPro\Modules\Forms\Classes\Ajax_Handler $ajax_handler AJAX response handler.
	 * @return void
	 */
	public function run( $record, $ajax_handler ) {
		// Elementor continues running actions after an error. Avoid sending when
		// a prior action has already failed this submission.
		if ( isset( $ajax_handler->is_success ) && ! $ajax_handler->is_success ) {
			return;
		}

		$settings    = $record->get( 'form_settings' );
		$settings    = is_array( $settings ) ? $settings : array();
		$configured  = isset( $settings[ self::RECIPIENTS_CONTROL ] ) ? (string) $settings[ self::RECIPIENTS_CONTROL ] : '';
		$template    = isset( $settings[ self::MESSAGE_CONTROL ] ) ? (string) $settings[ self::MESSAGE_CONTROL ] : '';
		$recipients  = $this->mapper->parse_recipients( $configured );
		$message     = $this->mapper->resolve_message( $record, $template );
		$credentials = Settings::credentials();
		$sender      = isset( $settings[ self::SENDER_CONTROL ] ) ? sanitize_text_field( (string) $settings[ self::SENDER_CONTROL ] ) : '';
		$sender      = '' === trim( $sender ) ? trim( $credentials['sign'] ) : trim( $sender );
		$batch_uuid  = wp_generate_uuid4();
		$context     = $this->context( $settings, $batch_uuid, $sender, $message );
		$failed      = false;

		foreach ( $recipients['invalid'] as $invalid_recipient ) {
			$failed = true;
			$this->insert_final(
				array_merge(
					$context,
					array(
						'recipient_original' => $invalid_recipient,
						'outcome'            => 'invalid_input',
						'error_code'         => 'invalid_recipient',
						'error_message'      => 'The configured recipient is not a valid international phone number.',
					)
				)
			);
		}

		if ( empty( $recipients['valid'] ) && empty( $recipients['invalid'] ) ) {
			$failed = true;
			$this->insert_final(
				array_merge(
					$context,
					array(
						'outcome'       => 'invalid_input',
						'error_code'    => 'missing_recipients',
						'error_message' => 'No recipient phone numbers are configured.',
					)
				)
			);
		}

		$configuration_error = $this->configuration_error( $message, $sender, $credentials );

		foreach ( $recipients['valid'] as $recipient ) {
			$row = array_merge(
				$context,
				array(
					'recipient_original' => $recipient['original'],
					'recipient'          => $recipient['number'],
				)
			);

			if ( null !== $configuration_error ) {
				$failed = true;
				$this->insert_final( array_merge( $row, $configuration_error ) );
				continue;
			}

			$log_id = $this->logs->insert( $row );
			if ( false === $log_id ) {
				$failed = true;
				continue;
			}

			$result = $this->client->send(
				$recipient['number'],
				$message,
				$sender,
				$credentials['login'],
				$credentials['api_key']
			);

			if ( ! $this->logs->update( $log_id, $result ) || 'accepted' !== $result['outcome'] ) {
				$failed = true;
			}
		}

		if ( $failed ) {
			$ajax_handler->add_error_message(
				esc_html__( 'One or more SMS notifications could not be sent. Please contact the site administrator.', 'sms-aero-elementor' )
			);
		}
	}

	/**
	 * Keep non-secret action settings in Elementor exports.
	 *
	 * @param array<string,mixed> $element Exported element settings.
	 * @return array<string,mixed>
	 */
	public function on_export( $element ) {
		return $element;
	}

	/**
	 * Build common row fields for one form submission.
	 *
	 * @param array<string,mixed> $settings   Form settings.
	 * @param string              $batch_uuid Correlation UUID.
	 * @param string              $sender     Resolved sender.
	 * @param string              $message    Resolved message.
	 * @return array<string,mixed>
	 */
	private function context( $settings, $batch_uuid, $sender, $message ) {
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		return array(
			'batch_uuid' => $batch_uuid,
			'post_id'    => $post_id,
			'form_id'    => isset( $settings['id'] ) ? sanitize_text_field( (string) $settings['id'] ) : '',
			'form_name'  => isset( $settings['form_name'] ) ? sanitize_text_field( (string) $settings['form_name'] ) : '',
			'sender'     => $sender,
			'message'    => $message,
		);
	}

	/**
	 * Return the first configuration error blocking provider requests.
	 *
	 * @param string              $message     Resolved message.
	 * @param string              $sender      Resolved sender.
	 * @param array<string,mixed> $credentials Global credentials.
	 * @return array<string,string>|null
	 */
	private function configuration_error( $message, $sender, $credentials ) {
		if ( '' === $message ) {
			return array(
				'outcome'       => 'invalid_input',
				'error_code'    => 'empty_message',
				'error_message' => 'The resolved SMS message is empty.',
			);
		}

		if ( '' === $sender ) {
			return array(
				'outcome'       => 'configuration_error',
				'error_code'    => 'missing_sender',
				'error_message' => 'No SMS Aero sender is configured.',
			);
		}

		if ( '' === trim( $credentials['login'] ) || '' === trim( $credentials['api_key'] ) ) {
			return array(
				'outcome'       => 'configuration_error',
				'error_code'    => 'missing_credentials',
				'error_message' => 'SMS Aero credentials are incomplete.',
			);
		}

		return null;
	}

	/**
	 * Insert a row that does not reach provider transport.
	 *
	 * @param array<string,mixed> $row Final audit data.
	 * @return void
	 */
	private function insert_final( $row ) {
		$row['attempt_uuid'] = wp_generate_uuid4();
		$this->logs->insert( $row );
	}
}
