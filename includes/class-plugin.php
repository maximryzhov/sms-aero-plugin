<?php
/**
 * Plugin composition root.
 *
 * @package SMS_Aero_Elementor
 */

namespace SMS_Aero_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers WordPress, administration, and Elementor integrations.
 */
final class Plugin {

	/** @var bool Whether bootstrap has already run. */
	private static $initialized = false;

	/**
	 * Bootstrap the plugin.
	 *
	 * @return void
	 */
	public static function init() {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;

		Installer::maybe_upgrade();

		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );
		add_action( 'admin_init', array( Settings::class, 'register' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_admin' ) );
		add_action( 'admin_notices', array( __CLASS__, 'dependency_notice' ) );
		add_action( 'elementor_pro/forms/actions/register', array( __CLASS__, 'register_action' ) );
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public static function load_textdomain() {
		load_plugin_textdomain(
			'sms-aero-elementor',
			false,
			dirname( plugin_basename( SMS_AERO_ELEMENTOR_FILE ) ) . '/languages'
		);
	}

	/**
	 * Register administration pages.
	 *
	 * @return void
	 */
	public static function register_admin() {
		require_once SMS_AERO_ELEMENTOR_DIR . 'includes/admin/class-admin.php';
		require_once SMS_AERO_ELEMENTOR_DIR . 'includes/admin/class-log-list-table.php';

		Admin::register_menu();
	}

	/**
	 * Lazily load and register the Elementor action.
	 *
	 * @param object $registrar Elementor form actions registrar.
	 * @return void
	 */
	public static function register_action( $registrar ) {
		if ( ! class_exists( '\\ElementorPro\\Modules\\Forms\\Classes\\Action_Base' ) ) {
			return;
		}

		if ( ! is_object( $registrar ) || ! is_callable( array( $registrar, 'register' ) ) ) {
			return;
		}

		require_once SMS_AERO_ELEMENTOR_DIR . 'includes/class-send-sms-action.php';
		$registrar->register( new Send_SMS_Action() );
	}

	/**
	 * Explain the missing Elementor Pro dependency to administrators.
	 *
	 * @return void
	 */
	public static function dependency_notice() {
		if ( ! current_user_can( 'manage_options' ) || self::elementor_forms_available() ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'SMS Aero for Elementor Forms requires Elementor Pro Forms. Settings and logs remain available, but the Send SMS action is disabled.', 'sms-aero-elementor' );
		echo '</p></div>';
	}

	/**
	 * Determine whether the Elementor Pro Forms action API is loaded.
	 *
	 * @return bool
	 */
	private static function elementor_forms_available() {
		return class_exists( '\\ElementorPro\\Modules\\Forms\\Classes\\Action_Base' ) && did_action( 'elementor/loaded' );
	}
}
