<?php
/**
 * Plugin Name: SMS Aero for Elementor Forms
 * Description: Adds an SMS Aero action to Elementor Pro Forms and logs every SMS attempt.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Custom Integration
 * Text Domain: sms-aero-elementor
 *
 * @package SMS_Aero_Elementor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SMS_AERO_ELEMENTOR_VERSION', '1.0.0' );
define( 'SMS_AERO_ELEMENTOR_SCHEMA_VERSION', '1.0.0' );
define( 'SMS_AERO_ELEMENTOR_FILE', __FILE__ );
define( 'SMS_AERO_ELEMENTOR_DIR', plugin_dir_path( __FILE__ ) );

require_once SMS_AERO_ELEMENTOR_DIR . 'includes/class-installer.php';
require_once SMS_AERO_ELEMENTOR_DIR . 'includes/class-settings.php';
require_once SMS_AERO_ELEMENTOR_DIR . 'includes/class-log-repository.php';
require_once SMS_AERO_ELEMENTOR_DIR . 'includes/class-sms-aero-client.php';
require_once SMS_AERO_ELEMENTOR_DIR . 'includes/class-submission-mapper.php';
require_once SMS_AERO_ELEMENTOR_DIR . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'SMS_Aero_Elementor\\Installer', 'activate' ) );

add_action( 'plugins_loaded', array( 'SMS_Aero_Elementor\\Plugin', 'init' ), 20 );
