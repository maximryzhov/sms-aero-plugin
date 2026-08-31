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
	 * Resolve the configured template and append every submitted form field.
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
		$message = trim( null === $message ? '' : $message );
		$lines   = $this->field_lines( $record );

		if ( empty( $lines ) ) {
			return $message;
		}

		$fields = implode( "\n", $lines );

		return '' === $message ? $fields : $message . "\n" . $fields;
	}

	/**
	 * Format submitted fields in their original order.
	 *
	 * @param object $record Elementor Form_Record-like object.
	 * @return array<int,string>
	 */
	private function field_lines( $record ) {
		if ( ! is_object( $record ) || ! is_callable( array( $record, 'get' ) ) ) {
			return array();
		}

		$fields = $record->get( 'fields' );
		if ( ! is_array( $fields ) ) {
			return array();
		}

		$lines = array();

		foreach ( $fields as $key => $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$label = $this->field_label( $field, $key );
			if ( '' === $label ) {
				continue;
			}

			if ( array_key_exists( 'value', $field ) ) {
				$value = $field['value'];
			} elseif ( array_key_exists( 'raw_value', $field ) ) {
				$value = $field['raw_value'];
			} else {
				$value = '';
			}

			$value   = $this->printable_value( $value );
			$lines[] = $label . ':' . ( '' === $value ? '' : ' ' . $value );
		}

		return $lines;
	}

	/**
	 * Resolve a printable field label with stable fallbacks.
	 *
	 * @param array<string,mixed> $field Submitted field.
	 * @param int|string          $key   Field collection key.
	 * @return string
	 */
	private function field_label( $field, $key ) {
		$candidates = array(
			isset( $field['title'] ) && is_scalar( $field['title'] ) ? $field['title'] : '',
			isset( $field['id'] ) && is_scalar( $field['id'] ) ? $field['id'] : '',
			is_scalar( $key ) ? $key : '',
		);

		foreach ( $candidates as $candidate ) {
			$label = $this->single_line( (string) $candidate );
			if ( '' !== $label ) {
				return $label;
			}
		}

		return '';
	}

	/**
	 * Convert a submitted field value to printable single-line text.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	private function printable_value( $value ) {
		if ( is_array( $value ) ) {
			$parts = array();

			foreach ( $value as $item ) {
				$item = $this->printable_value( $item );
				if ( '' !== $item ) {
					$parts[] = $item;
				}
			}

			return implode( ', ', $parts );
		}

		if ( ! is_scalar( $value ) || is_bool( $value ) && false === $value ) {
			return '';
		}

		return $this->single_line( (string) $value );
	}

	/**
	 * Strip markup and collapse whitespace into one physical line.
	 *
	 * @param string $value Text to normalize.
	 * @return string
	 */
	private function single_line( $value ) {
		$value = wp_strip_all_tags( (string) $value, true );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return trim( null === $value ? '' : $value );
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
