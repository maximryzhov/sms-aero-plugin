<?php
/**
 * Elementor submission mapping and validation.
 *
 * @package SMS_Aero_Elementor
 */

namespace SMS_Aero_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves action settings into deterministic recipients and message content.
 */
final class Submission_Mapper {

	/**
	 * Parse a comma/newline-separated list of configured recipients.
	 *
	 * @param string $configured Configured recipient list.
	 * @return array{valid:array<int,array{original:string,number:string}>,invalid:array<int,string>}
	 */
	public function parse_recipients( $configured ) {
		$parts   = preg_split( '/[\r\n,]+/', (string) $configured );
		$valid   = array();
		$invalid = array();
		$seen    = array();

		foreach ( false === $parts ? array() : $parts as $part ) {
			$original = trim( wp_strip_all_tags( (string) $part, true ) );
			if ( '' === $original ) {
				continue;
			}

			$number = $this->normalize_phone( $original );
			if ( null === $number ) {
				$invalid[] = $original;
				continue;
			}

			if ( isset( $seen[ $number ] ) ) {
				continue;
			}

			$seen[ $number ] = true;
			$valid[]         = array(
				'original' => $original,
				'number'   => $number,
			);
		}

		return array(
			'valid'   => $valid,
			'invalid' => $invalid,
		);
	}

	/**
	 * Resolve and sanitize the message template using Elementor form fields.
	 *
	 * @param object $record   Elementor Form_Record-like object.
	 * @param string $template Configured message template.
	 * @return string
	 */
	public function resolve_message( $record, $template ) {
		$message = (string) $template;

		if ( is_object( $record ) && is_callable( array( $record, 'replace_setting_shortcodes' ) ) ) {
			$message = (string) $record->replace_setting_shortcodes( $message );
		}

		$message = wp_strip_all_tags( $message );
		$message = str_replace( array( "\r\n", "\r" ), "\n", $message );
		$message = preg_replace( "/[\t ]+\n/", "\n", $message );

		return trim( null === $message ? '' : $message );
	}

	/**
	 * Normalize an international phone number.
	 *
	 * @param string $phone Configured phone value.
	 * @return string|null
	 */
	public function normalize_phone( $phone ) {
		$phone = trim( (string) $phone );
		if ( '' === $phone ) {
			return null;
		}

		// Allow common display punctuation, but reject letters and other symbols.
		if ( ! preg_match( '/^\+?[0-9\s().-]+$/', $phone ) ) {
			return null;
		}

		$number = preg_replace( '/[\s().-]+/', '', ltrim( $phone, '+' ) );
		if ( null === $number || ! ctype_digit( $number ) ) {
			return null;
		}

		if ( 11 === strlen( $number ) && '8' === $number[0] ) {
			$number = '7' . substr( $number, 1 );
		}

		$length = strlen( $number );
		if ( $length < 10 || $length > 15 ) {
			return null;
		}

		return $number;
	}
}
