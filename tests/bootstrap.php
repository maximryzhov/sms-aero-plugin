<?php
/**
 * PHPUnit bootstrap with a minimal WordPress compatibility layer.
 *
 * @package SMS_Aero_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/wordpress/' );
}

if ( ! defined( 'SMS_AERO_ELEMENTOR_SCHEMA_VERSION' ) ) {
	define( 'SMS_AERO_ELEMENTOR_SCHEMA_VERSION', '1.0.0' );
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		private $data;

		public function __construct( $code = '', $message = '', $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code() {
			return $this->code;
		}

		public function get_error_message() {
			return $this->message;
		}
	}
}

$GLOBALS['sms_aero_test_options'] = array();
$GLOBALS['sms_aero_test_uuid']    = 0;

function is_wp_error( $value ) {
	return $value instanceof WP_Error;
}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, is_array( $args ) ? $args : array() );
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $value ) );
}

function sanitize_text_field( $value ) {
	$value = strip_tags( (string) $value );
	$value = preg_replace( '/[\r\n\t ]+/', ' ', $value );
	return trim( $value );
}

function wp_unslash( $value ) {
	return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
}

function wp_strip_all_tags( $value, $remove_breaks = false ) {
	$value = strip_tags( (string) $value );
	return $remove_breaks ? preg_replace( '/[\r\n\t ]+/', ' ', $value ) : $value;
}

function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}

function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? $response['response']['code'] : '';
}

function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['sms_aero_test_options'] ) ? $GLOBALS['sms_aero_test_options'][ $name ] : $default;
}

function wp_generate_uuid4() {
	$GLOBALS['sms_aero_test_uuid']++;
	return sprintf( '00000000-0000-4000-8000-%012d', $GLOBALS['sms_aero_test_uuid'] );
}

function absint( $value ) {
	return abs( (int) $value );
}

function esc_html__( $text, $domain = '' ) {
	return $text;
}

function esc_html_e( $text, $domain = '' ) {
	echo esc_html__( $text, $domain );
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return (string) $url;
}

function disabled( $disabled, $current = true, $echo = true ) {
	$output = $disabled ? 'disabled="disabled"' : '';
	if ( $echo ) {
		echo $output;
	}
	return $output;
}

function checked( $checked, $current = true, $echo = true ) {
	$output = $checked ? 'checked="checked"' : '';
	if ( $echo ) {
		echo $output;
	}
	return $output;
}

function selected( $selected, $current = true, $echo = true ) {
	$output = (string) $selected === (string) $current ? 'selected="selected"' : '';
	if ( $echo ) {
		echo $output;
	}
	return $output;
}

function wp_html_excerpt( $text, $length, $ellipsis = null ) {
	$text = (string) $text;
	if ( strlen( $text ) <= $length ) {
		return $text;
	}
	return substr( $text, 0, $length ) . ( null === $ellipsis ? '' : $ellipsis );
}

function register_setting() {}
function add_settings_section() {}
function add_settings_field() {}
function add_action() {}
function load_plugin_textdomain() {}
function plugin_basename( $file ) { return basename( dirname( $file ) ) . '/' . basename( $file ); }
function plugin_dir_path( $file ) { return trailingslashit( dirname( $file ) ); }
function trailingslashit( $value ) { return rtrim( (string) $value, '/\\' ) . '/'; }
function current_user_can( $capability ) { return true; }
function did_action( $hook_name ) { return 1; }

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
	abstract class SMS_Aero_Test_Action_Base {
		abstract public function get_name();
		abstract public function get_label();
		abstract public function run( $record, $ajax_handler );
		abstract public function register_settings_section( $form );
		abstract public function on_export( $element );
	}
}

if ( ! class_exists( 'SMS_Aero_Test_Controls_Manager' ) ) {
	class SMS_Aero_Test_Controls_Manager {
		const TEXTAREA = 'textarea';
		const TEXT     = 'text';
	}
}

class_alias( 'SMS_Aero_Test_Action_Base', 'ElementorPro\\Modules\\Forms\\Classes\\Action_Base' );
class_alias( 'SMS_Aero_Test_Controls_Manager', 'Elementor\\Controls_Manager' );

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-sms-aero-client.php';
require_once dirname( __DIR__ ) . '/includes/class-submission-mapper.php';
require_once dirname( __DIR__ ) . '/includes/class-send-sms-action.php';
