<?php
/**
 * Plugin lifecycle and schema installation.
 *
 * @package SMS_Aero_Elementor
 */

namespace SMS_Aero_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates and upgrades the plugin database schema.
 */
final class Installer {

	/** Option containing the installed schema version. */
	const SCHEMA_OPTION = 'sms_aero_elementor_schema_version';

	/** Main settings option. */
	const SETTINGS_OPTION = 'sms_aero_elementor_settings';

	/**
	 * Activate the plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		self::install_schema();
	}

	/**
	 * Upgrade the schema when required.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = (string) get_option( self::SCHEMA_OPTION, '' );

		if ( SMS_AERO_ELEMENTOR_SCHEMA_VERSION !== $installed ) {
			self::install_schema();
		}
	}

	/**
	 * Return the prefixed audit table name.
	 *
	 * @return string
	 */
	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'sms_aero_logs';
	}

	/**
	 * Create or upgrade the audit table with dbDelta().
	 *
	 * @return void
	 */
	private static function install_schema() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attempt_uuid char(36) NOT NULL,
			batch_uuid char(36) NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			form_id varchar(191) NOT NULL DEFAULT '',
			form_name varchar(255) NOT NULL DEFAULT '',
			recipient_original varchar(255) NOT NULL DEFAULT '',
			recipient varchar(32) NOT NULL DEFAULT '',
			sender varchar(255) NOT NULL DEFAULT '',
			message longtext NOT NULL,
			outcome varchar(32) NOT NULL DEFAULT 'pending',
			http_status smallint(5) unsigned DEFAULT NULL,
			provider_message_id varchar(191) NOT NULL DEFAULT '',
			provider_status varchar(32) NOT NULL DEFAULT '',
			provider_extend_status varchar(64) NOT NULL DEFAULT '',
			error_code varchar(191) NOT NULL DEFAULT '',
			error_message text NOT NULL,
			raw_response longtext NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY attempt_uuid (attempt_uuid),
			KEY batch_uuid (batch_uuid),
			KEY created_at (created_at),
			KEY outcome_created (outcome,created_at),
			KEY recipient_created (recipient,created_at),
			KEY provider_message_id (provider_message_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::SCHEMA_OPTION, SMS_AERO_ELEMENTOR_SCHEMA_VERSION, false );
	}
}
