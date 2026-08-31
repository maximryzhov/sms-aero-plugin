<?php
/**
 * Submission mapper tests.
 *
 * @package SMS_Aero_Elementor
 */

use PHPUnit\Framework\TestCase;
use SMS_Aero_Elementor\Submission_Mapper;

final class SubmissionMapperTest extends TestCase {

	public function test_parses_normalizes_and_deduplicates_recipients() {
		$mapper = new Submission_Mapper();
		$result = $mapper->parse_recipients(
			"+7 (999) 000-00-00, 8 999 000 00 00\n+44 20 7946 0958, invalid"
		);

		$this->assertSame(
			array(
				array(
					'original' => '+7 (999) 000-00-00',
					'number'   => '79990000000',
				),
				array(
					'original' => '+44 20 7946 0958',
					'number'   => '442079460958',
				),
			),
			$result['valid']
		);
		$this->assertSame( array( 'invalid' ), $result['invalid'] );
	}

	public function test_rejects_bad_symbols_and_lengths() {
		$mapper = new Submission_Mapper();

		$this->assertNull( $mapper->normalize_phone( '12345' ) );
		$this->assertNull( $mapper->normalize_phone( '+7 999 CALL NOW' ) );
		$this->assertNull( $mapper->normalize_phone( '1234567890123456' ) );
	}

	public function test_resolves_only_message_shortcodes_and_strips_html() {
		$record = new class() {
			public function replace_setting_shortcodes( $template ) {
				return str_replace( '[field id="name"]', '<b>Alice</b>', $template );
			}
		};
		$mapper = new Submission_Mapper();

		$this->assertSame(
			"New request from Alice\nThank you",
			$mapper->resolve_message( $record, "New request from [field id=\"name\"]\r\nThank you" )
		);
	}
}
