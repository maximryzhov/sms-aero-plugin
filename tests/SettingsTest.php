<?php
/**
 * Settings tests.
 *
 * @package SMS_Aero_Elementor
 */

use PHPUnit\Framework\TestCase;
use SMS_Aero_Elementor\Settings;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['sms_aero_test_options'] = array();
	}

	public function test_blank_api_key_preserves_saved_secret() {
		$GLOBALS['sms_aero_test_options'][ Settings::OPTION_NAME ] = array(
			'login'               => 'old@example.test',
			'api_key'             => 'saved-secret',
			'sign'                => 'OldSign',
			'delete_on_uninstall' => 1,
		);

		$result = Settings::sanitize(
			array(
				'login'   => ' new@example.test ',
				'api_key' => '   ',
				'sign'    => ' NewSign ',
			)
		);

		$this->assertSame( 'new@example.test', $result['login'] );
		$this->assertSame( 'saved-secret', $result['api_key'] );
		$this->assertSame( 'NewSign', $result['sign'] );
		$this->assertSame( 0, $result['delete_on_uninstall'] );
	}

	public function test_nonblank_api_key_replaces_saved_secret() {
		$GLOBALS['sms_aero_test_options'][ Settings::OPTION_NAME ] = array(
			'api_key' => 'saved-secret',
		);

		$result = Settings::sanitize(
			array(
				'api_key'             => ' replacement-secret ',
				'delete_on_uninstall' => '1',
			)
		);

		$this->assertSame( 'replacement-secret', $result['api_key'] );
		$this->assertSame( 1, $result['delete_on_uninstall'] );
	}

	public function test_credentials_use_stored_values_without_overrides() {
		$GLOBALS['sms_aero_test_options'][ Settings::OPTION_NAME ] = array(
			'login'   => 'login@example.test',
			'api_key' => 'secret',
			'sign'    => 'ApprovedSign',
		);

		$this->assertSame(
			array(
				'login'   => 'login@example.test',
				'api_key' => 'secret',
				'sign'    => 'ApprovedSign',
			),
			Settings::credentials()
		);
	}
}
