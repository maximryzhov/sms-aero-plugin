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

	public function test_appends_every_submitted_field_after_resolved_template() {
		$record = new SMS_Aero_Test_Mapper_Record(
			array(
				'name' => array(
					'id'    => 'name',
					'title' => 'Name',
					'value' => '<b>Alice</b>',
				),
				'other_name' => array(
					'id'    => 'other_name',
					'title' => 'Name',
					'value' => "Bob\nBuilder",
				),
				'email' => array(
					'id'    => 'email',
					'title' => '',
					'value' => 'alice@example.test',
				),
				'optional' => array(
					'id'    => '',
					'title' => '',
					'value' => '',
				),
				'count' => array(
					'id'    => 'count',
					'title' => 'Count',
					'value' => 0,
				),
				'choices' => array(
					'id'    => 'choices',
					'title' => 'Choices',
					'value' => array( 'One', 'Two', '', array( 'Three' ) ),
				),
				'notes' => array(
					'id'    => 'notes',
					'title' => " Notes\nLabel ",
					'value' => "<i>Line 1</i>\r\nLine\t2",
				),
				'raw' => array(
					'id'        => 'raw',
					'title'     => 'Raw fallback',
					'raw_value' => 'Raw value',
				),
			)
		);
		$mapper = new Submission_Mapper();

		$this->assertSame(
			"New request from Alice\nThank you\nName: Alice\nName: Bob Builder\nemail: alice@example.test\noptional:\nCount: 0\nChoices: One, Two, Three\nNotes Label: Line 1 Line 2\nRaw fallback: Raw value",
			$mapper->resolve_message( $record, "New request from [field id=\"name\"]\r\nThank you" )
		);
	}

	public function test_blank_template_sends_field_lines_without_leading_newline() {
		$record = new SMS_Aero_Test_Mapper_Record(
			array(
				'name' => array(
					'id'    => 'name',
					'title' => 'Name',
					'value' => 'Alice',
				),
			)
		);

		$this->assertSame( 'Name: Alice', ( new Submission_Mapper() )->resolve_message( $record, '' ) );
	}

	public function test_template_without_fields_remains_unchanged() {
		$record = new SMS_Aero_Test_Mapper_Record( array() );

		$this->assertSame(
			"Static message\nSecond line",
			( new Submission_Mapper() )->resolve_message( $record, "<b>Static message</b>\r\nSecond line" )
		);
		$this->assertSame( '', ( new Submission_Mapper() )->resolve_message( $record, '' ) );
	}
}

final class SMS_Aero_Test_Mapper_Record {
	private $fields;

	public function __construct( $fields ) {
		$this->fields = $fields;
	}

	public function get( $property ) {
		return 'fields' === $property ? $this->fields : null;
	}

	public function replace_setting_shortcodes( $template ) {
		foreach ( $this->fields as $id => $field ) {
			$value = isset( $field['value'] ) && is_scalar( $field['value'] ) ? (string) $field['value'] : '';
			$template = str_replace( '[field id="' . $id . '"]', $value, $template );
		}

		return $template;
	}
}
