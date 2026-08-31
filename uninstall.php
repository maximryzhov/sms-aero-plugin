<?php
/**
 * Plugin uninstall handler.
 *
 * @package SMS_Aero_Elementor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'sms_aero_elementor_settings', array() );

if ( ! is_array( $settings ) || empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$table_name = $wpdb->prefix . 'sms_aero_logs';
$wpdb->query( "DROP TABLE IF EXISTS `{$table_name}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

delete_option( 'sms_aero_elementor_settings' );
delete_option( 'sms_aero_elementor_schema_version' );
