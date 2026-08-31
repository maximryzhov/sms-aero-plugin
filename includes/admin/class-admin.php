<?php
/**
 * WordPress administration pages.
 *
 * @package SMS_Aero_Elementor
 */

namespace SMS_Aero_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders settings and audit pages.
 */
final class Admin {

	/** Settings page slug. */
	const SETTINGS_SLUG = 'sms-aero-elementor';

	/** Logs page slug. */
	const LOGS_SLUG = 'sms-aero-elementor-logs';

	/**
	 * Register administration menu entries.
	 *
	 * @return void
	 */
	public static function register_menu() {
		add_menu_page(
			esc_html__( 'SMS Aero', 'sms-aero-elementor' ),
			esc_html__( 'SMS Aero', 'sms-aero-elementor' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings' ),
			'dashicons-email-alt',
			58
		);

		add_submenu_page(
			self::SETTINGS_SLUG,
			esc_html__( 'SMS Aero Settings', 'sms-aero-elementor' ),
			esc_html__( 'Settings', 'sms-aero-elementor' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( __CLASS__, 'render_settings' )
		);

		add_submenu_page(
			self::SETTINGS_SLUG,
			esc_html__( 'SMS Logs', 'sms-aero-elementor' ),
			esc_html__( 'SMS Logs', 'sms-aero-elementor' ),
			'manage_options',
			self::LOGS_SLUG,
			array( __CLASS__, 'render_logs' )
		);
	}

	/**
	 * Render global settings.
	 *
	 * @return void
	 */
	public static function render_settings() {
		self::require_capability();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SMS Aero Settings', 'sms-aero-elementor' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'sms_aero_elementor' );
				do_settings_sections( self::SETTINGS_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the log list or one log detail.
	 *
	 * @return void
	 */
	public static function render_logs() {
		self::require_capability();

		$log_id = isset( $_GET['log_id'] ) ? absint( wp_unslash( $_GET['log_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $log_id > 0 ) {
			self::render_log_detail( $log_id );
			return;
		}

		$table = new Log_List_Table( new Log_Repository() );
		$table->prepare_items();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SMS Logs', 'sms-aero-elementor' ); ?></h1>
			<p><?php esc_html_e( '“Accepted” means that SMS Aero accepted the request. It does not confirm delivery to the handset.', 'sms-aero-elementor' ); ?></p>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::LOGS_SLUG ); ?>" />
				<?php
				$table->search_box( esc_html__( 'Search logs', 'sms-aero-elementor' ), 'sms-aero-log' );
				$table->display();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render one complete protected audit record.
	 *
	 * @param int $log_id Log row ID.
	 * @return void
	 */
	private static function render_log_detail( $log_id ) {
		$repository = new Log_Repository();
		$row        = $repository->find( $log_id );
		$back_url   = admin_url( 'admin.php?page=' . self::LOGS_SLUG );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'SMS Log Details', 'sms-aero-elementor' ); ?></h1>
			<p><a href="<?php echo esc_url( $back_url ); ?>">&larr; <?php esc_html_e( 'Back to SMS logs', 'sms-aero-elementor' ); ?></a></p>
			<?php if ( ! $row ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'The requested log record was not found.', 'sms-aero-elementor' ); ?></p></div>
			<?php else : ?>
				<p><?php esc_html_e( '“Accepted” means accepted by SMS Aero, not confirmed mobile delivery.', 'sms-aero-elementor' ); ?></p>
				<table class="widefat striped" style="max-width: 1100px">
					<tbody>
					<?php foreach ( self::detail_fields() as $key => $label ) : ?>
						<tr>
							<th scope="row" style="width:220px"><?php echo esc_html( $label ); ?></th>
							<td><?php echo nl2br( esc_html( (string) $row->{$key} ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<h2><?php esc_html_e( 'Raw provider response', 'sms-aero-elementor' ); ?></h2>
				<pre style="max-width:1100px;overflow:auto;white-space:pre-wrap;background:#fff;border:1px solid #ccd0d4;padding:12px"><?php echo esc_html( (string) $row->raw_response ); ?></pre>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Audit fields displayed in the detail view.
	 *
	 * @return array<string,string>
	 */
	private static function detail_fields() {
		return array(
			'id'                         => esc_html__( 'Local ID', 'sms-aero-elementor' ),
			'attempt_uuid'               => esc_html__( 'Attempt UUID', 'sms-aero-elementor' ),
			'batch_uuid'                 => esc_html__( 'Batch UUID', 'sms-aero-elementor' ),
			'created_at'                 => esc_html__( 'Created (UTC)', 'sms-aero-elementor' ),
			'updated_at'                 => esc_html__( 'Updated (UTC)', 'sms-aero-elementor' ),
			'post_id'                    => esc_html__( 'Post ID', 'sms-aero-elementor' ),
			'form_id'                    => esc_html__( 'Form ID', 'sms-aero-elementor' ),
			'form_name'                  => esc_html__( 'Form name', 'sms-aero-elementor' ),
			'recipient_original'         => esc_html__( 'Configured recipient', 'sms-aero-elementor' ),
			'recipient'                  => esc_html__( 'Normalized recipient', 'sms-aero-elementor' ),
			'sender'                     => esc_html__( 'Sender', 'sms-aero-elementor' ),
			'message'                    => esc_html__( 'Message', 'sms-aero-elementor' ),
			'outcome'                    => esc_html__( 'Outcome', 'sms-aero-elementor' ),
			'http_status'                => esc_html__( 'HTTP status', 'sms-aero-elementor' ),
			'provider_message_id'        => esc_html__( 'Provider message ID', 'sms-aero-elementor' ),
			'provider_status'            => esc_html__( 'Provider status', 'sms-aero-elementor' ),
			'provider_extend_status'     => esc_html__( 'Provider extended status', 'sms-aero-elementor' ),
			'error_code'                 => esc_html__( 'Error code', 'sms-aero-elementor' ),
			'error_message'              => esc_html__( 'Error message', 'sms-aero-elementor' ),
		);
	}

	/**
	 * Enforce the administration capability.
	 *
	 * @return void
	 */
	private static function require_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'sms-aero-elementor' ) );
		}
	}
}
