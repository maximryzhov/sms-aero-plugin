<?php
/**
 * Paginated SMS audit table.
 *
 * @package SMS_Aero_Elementor
 */

namespace SMS_Aero_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Displays SMS audit rows in WordPress administration.
 */
final class Log_List_Table extends \WP_List_Table {

	/** @var Log_Repository */
	private $repository;

	/**
	 * Constructor.
	 *
	 * @param Log_Repository $repository Log repository.
	 */
	public function __construct( $repository ) {
		$this->repository = $repository;

		parent::__construct(
			array(
				'singular' => 'sms_aero_log',
				'plural'   => 'sms_aero_logs',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Define visible columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'created_at'              => esc_html__( 'Time (UTC)', 'sms-aero-elementor' ),
			'outcome'                 => esc_html__( 'Outcome', 'sms-aero-elementor' ),
			'form_name'               => esc_html__( 'Form', 'sms-aero-elementor' ),
			'recipient'               => esc_html__( 'Recipient', 'sms-aero-elementor' ),
			'sender'                  => esc_html__( 'Sender', 'sms-aero-elementor' ),
			'message'                 => esc_html__( 'Message', 'sms-aero-elementor' ),
			'http_status'             => esc_html__( 'HTTP', 'sms-aero-elementor' ),
			'provider_message_id'     => esc_html__( 'Provider ID', 'sms-aero-elementor' ),
		);
	}

	/**
	 * Define allowlisted sortable columns.
	 *
	 * @return array<string,array{0:string,1:bool}>
	 */
	protected function get_sortable_columns() {
		return array(
			'created_at'          => array( 'created_at', true ),
			'outcome'             => array( 'outcome', false ),
			'recipient'           => array( 'recipient', false ),
			'http_status'         => array( 'http_status', false ),
			'provider_message_id' => array( 'provider_message_id', false ),
		);
	}

	/**
	 * Render outcome filter links.
	 *
	 * @param string $which Table position.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current = isset( $_GET['outcome'] ) ? sanitize_key( wp_unslash( $_GET['outcome'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="filter-by-outcome"><?php esc_html_e( 'Filter by outcome', 'sms-aero-elementor' ); ?></label>
			<select name="outcome" id="filter-by-outcome">
				<option value=""><?php esc_html_e( 'All outcomes', 'sms-aero-elementor' ); ?></option>
				<?php foreach ( Log_Repository::OUTCOMES as $outcome ) : ?>
					<option value="<?php echo esc_attr( $outcome ); ?>" <?php selected( $current, $outcome ); ?>><?php echo esc_html( $outcome ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( esc_html__( 'Filter', 'sms-aero-elementor' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Load table rows and pagination totals.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = $this->get_items_per_page( 'sms_aero_logs_per_page', 20 );
		$args     = array(
			'page'     => $this->get_pagenum(),
			'per_page' => $per_page,
			'outcome'  => isset( $_GET['outcome'] ) ? sanitize_key( wp_unslash( $_GET['outcome'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'DESC', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$this->items           = $this->repository->query( $args );
		$this->set_pagination_args(
			array(
				'total_items' => $this->repository->count( $args ),
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Render ordinary cells.
	 *
	 * @param object $item        Audit row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		if ( 'recipient' === $column_name ) {
			return esc_html( self::mask_phone( (string) $item->recipient ) );
		}

		if ( 'message' === $column_name ) {
			return esc_html( wp_html_excerpt( (string) $item->message, 70, '…' ) );
		}

		$value = isset( $item->{$column_name} ) ? (string) $item->{$column_name} : '';

		return esc_html( $value );
	}

	/**
	 * Render timestamp and row actions.
	 *
	 * @param object $item Audit row.
	 * @return string
	 */
	protected function column_created_at( $item ) {
		$url = add_query_arg(
			array(
				'page'   => Admin::LOGS_SLUG,
				'log_id' => (int) $item->id,
			),
			admin_url( 'admin.php' )
		);

		$actions = array(
			'view' => sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'View details', 'sms-aero-elementor' ) ),
		);

		return esc_html( (string) $item->created_at ) . $this->row_actions( $actions );
	}

	/**
	 * Render the empty-state message.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No SMS logs found.', 'sms-aero-elementor' );
	}

	/**
	 * Mask all but a small prefix and suffix of a destination number.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	private static function mask_phone( $phone ) {
		$length = strlen( $phone );
		if ( $length <= 5 ) {
			return str_repeat( '•', $length );
		}

		return substr( $phone, 0, 2 ) . str_repeat( '•', $length - 4 ) . substr( $phone, -2 );
	}
}
