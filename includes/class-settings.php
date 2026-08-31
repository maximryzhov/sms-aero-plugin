<?php
/**
 * Global SMS Aero settings.
 *
 * @package SMS_Aero_Elementor
 */

namespace SMS_Aero_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores and resolves account settings without exposing the API key.
 */
final class Settings {

	/** Settings option name. */
	const OPTION_NAME = 'sms_aero_elementor_settings';

	/**
	 * Register settings and fields.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			'sms_aero_elementor',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);

		add_settings_section(
			'sms_aero_elementor_credentials',
			esc_html__( 'SMS Aero credentials', 'sms-aero-elementor' ),
			array( __CLASS__, 'render_credentials_description' ),
			'sms-aero-elementor'
		);

		self::add_text_field( 'login', esc_html__( 'Account email / login', 'sms-aero-elementor' ) );
		self::add_text_field( 'api_key', esc_html__( 'API key', 'sms-aero-elementor' ), 'password' );
		self::add_text_field( 'sign', esc_html__( 'Default sender (sign)', 'sms-aero-elementor' ) );

		add_settings_field(
			'delete_on_uninstall',
			esc_html__( 'Uninstall behavior', 'sms-aero-elementor' ),
			array( __CLASS__, 'render_delete_field' ),
			'sms-aero-elementor',
			'sms_aero_elementor_credentials'
		);
	}

	/**
	 * Return safe defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'login'               => '',
			'api_key'             => '',
			'sign'                => '',
			'delete_on_uninstall' => 0,
		);
	}

	/**
	 * Sanitize submitted settings and preserve an omitted API key.
	 *
	 * @param mixed $input Submitted option value.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$existing = self::stored();
		$api_key  = isset( $input['api_key'] ) ? trim( (string) wp_unslash( $input['api_key'] ) ) : '';

		return array(
			'login'               => array_key_exists( 'login', $input ) ? sanitize_text_field( wp_unslash( $input['login'] ) ) : $existing['login'],
			'api_key'             => '' !== $api_key ? sanitize_text_field( $api_key ) : $existing['api_key'],
			'sign'                => array_key_exists( 'sign', $input ) ? sanitize_text_field( wp_unslash( $input['sign'] ) ) : $existing['sign'],
			'delete_on_uninstall' => empty( $input['delete_on_uninstall'] ) ? 0 : 1,
		);
	}

	/**
	 * Resolve credentials, honoring deployment constants.
	 *
	 * @return array{login:string,api_key:string,sign:string}
	 */
	public static function credentials() {
		$settings = self::stored();

		return array(
			'login'   => defined( 'SMS_AERO_LOGIN' ) ? trim( (string) SMS_AERO_LOGIN ) : $settings['login'],
			'api_key' => defined( 'SMS_AERO_API_KEY' ) ? trim( (string) SMS_AERO_API_KEY ) : $settings['api_key'],
			'sign'    => defined( 'SMS_AERO_SIGN' ) ? trim( (string) SMS_AERO_SIGN ) : $settings['sign'],
		);
	}

	/**
	 * Return normalized settings stored in WordPress.
	 *
	 * @return array<string,mixed>
	 */
	public static function stored() {
		$value = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( is_array( $value ) ? $value : array(), self::defaults() );
	}

	/**
	 * Render settings section help.
	 *
	 * @return void
	 */
	public static function render_credentials_description() {
		echo '<p>' . esc_html__( 'Use the account login and API key from SMS Aero. Constants defined in wp-config.php override these values.', 'sms-aero-elementor' ) . '</p>';
	}

	/**
	 * Render one text setting.
	 *
	 * @param array<string,string> $args Field arguments.
	 * @return void
	 */
	public static function render_text_field( $args ) {
		$key      = $args['key'];
		$type     = isset( $args['type'] ) ? $args['type'] : 'text';
		$settings = self::stored();
		$value    = 'password' === $type ? '' : (string) $settings[ $key ];
		$disabled = self::is_overridden( $key );

		printf(
			'<input class="regular-text" type="%1$s" name="%2$s[%3$s]" value="%4$s" autocomplete="%5$s" %6$s />',
			esc_attr( $type ),
			esc_attr( self::OPTION_NAME ),
			esc_attr( $key ),
			esc_attr( $value ),
			'password' === $type ? 'new-password' : 'off',
			disabled( $disabled, true, false )
		);

		if ( 'password' === $type && '' !== $settings['api_key'] ) {
			echo '<p class="description">' . esc_html__( 'An API key is saved. Leave this field blank to keep it unchanged.', 'sms-aero-elementor' ) . '</p>';
		}

		if ( $disabled ) {
			echo '<p class="description">' . esc_html__( 'This value is supplied by a PHP constant.', 'sms-aero-elementor' ) . '</p>';
		}
	}

	/**
	 * Render the uninstall opt-in.
	 *
	 * @return void
	 */
	public static function render_delete_field() {
		$settings = self::stored();
		?>
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::OPTION_NAME ); ?>[delete_on_uninstall]" value="1" <?php checked( ! empty( $settings['delete_on_uninstall'] ) ); ?> />
			<?php esc_html_e( 'Delete SMS Aero settings and all SMS logs when the plugin is uninstalled.', 'sms-aero-elementor' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Disabled by default so audit records are preserved.', 'sms-aero-elementor' ); ?></p>
		<?php
	}

	/**
	 * Register a Settings API text field.
	 *
	 * @param string $key   Settings key.
	 * @param string $label Field label.
	 * @param string $type  Input type.
	 * @return void
	 */
	private static function add_text_field( $key, $label, $type = 'text' ) {
		add_settings_field(
			$key,
			$label,
			array( __CLASS__, 'render_text_field' ),
			'sms-aero-elementor',
			'sms_aero_elementor_credentials',
			array(
				'key'  => $key,
				'type' => $type,
			)
		);
	}

	/**
	 * Whether a value is controlled by a deployment constant.
	 *
	 * @param string $key Settings key.
	 * @return bool
	 */
	private static function is_overridden( $key ) {
		$constants = array(
			'login'   => 'SMS_AERO_LOGIN',
			'api_key' => 'SMS_AERO_API_KEY',
			'sign'    => 'SMS_AERO_SIGN',
		);

		return isset( $constants[ $key ] ) && defined( $constants[ $key ] );
	}
}
