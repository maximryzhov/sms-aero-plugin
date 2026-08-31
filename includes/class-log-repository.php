<?php
/**
 * Audit log persistence.
 *
 * @package SMS_Aero_Elementor
 */

namespace SMS_Aero_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes SMS audit rows.
 */
final class Log_Repository {

	/** Allowed values written to the outcome column. */
	const OUTCOMES = array(
		'pending',
		'accepted',
		'rejected',
		'transport_error',
		'response_error',
		'configuration_error',
		'invalid_input',
		'unknown',
	);

	/**
	 * Insert a new audit row.
	 *
	 * @param array<string,mixed> $data Row data.
	 * @return int|false Inserted ID or false.
	 */
	public function insert( $data ) {
		global $wpdb;

		$now      = gmdate( 'Y-m-d H:i:s' );
		$defaults = array(
			'attempt_uuid'           => wp_generate_uuid4(),
			'batch_uuid'             => '',
			'created_at'             => $now,
			'updated_at'             => $now,
			'post_id'                => 0,
			'form_id'                => '',
			'form_name'              => '',
			'recipient_original'     => '',
			'recipient'              => '',
			'sender'                 => '',
			'message'                => '',
			'outcome'                => 'pending',
			'http_status'            => null,
			'provider_message_id'    => '',
			'provider_status'        => '',
			'provider_extend_status' => '',
			'error_code'             => '',
			'error_message'          => '',
			'raw_response'           => '',
		);
		$row      = wp_parse_args( $data, $defaults );
		$row      = $this->normalize_row( $row );
		$result   = $wpdb->insert( Installer::table_name(), $row, $this->formats( $row ) );

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Finalize an existing audit row.
	 *
	 * @param int                 $id   Row ID.
	 * @param array<string,mixed> $data Final values.
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$id = absint( $id );
		if ( 0 === $id ) {
			return false;
		}

		$allowed = array(
			'updated_at',
			'outcome',
			'http_status',
			'provider_message_id',
			'provider_status',
			'provider_extend_status',
			'error_code',
			'error_message',
			'raw_response',
		);
		$row     = array_intersect_key( $data, array_flip( $allowed ) );
		$row['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$row     = $this->normalize_row( $row );
		$result  = $wpdb->update(
			Installer::table_name(),
			$row,
			array( 'id' => $id ),
			$this->formats( $row ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Retrieve one row.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public function find( $id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . Installer::table_name() . ' WHERE id = %d', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				absint( $id )
			)
		);
	}

	/**
	 * Retrieve paginated rows for the administration table.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array<int,object>
	 */
	public function query( $args = array() ) {
		global $wpdb;

		$args = wp_parse_args(
			$args,
			array(
				'page'     => 1,
				'per_page' => 20,
				'outcome'  => '',
				'search'   => '',
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		list( $where, $values ) = $this->build_where( $args );
		$allowed_orderby = array( 'id', 'created_at', 'outcome', 'recipient', 'http_status', 'provider_message_id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';
		$per_page        = max( 1, min( 100, absint( $args['per_page'] ) ) );
		$offset          = ( max( 1, absint( $args['page'] ) ) - 1 ) * $per_page;
		$sql             = 'SELECT * FROM ' . Installer::table_name() . " {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$values[]        = $per_page;
		$values[]        = $offset;

		return $wpdb->get_results( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Count rows matching query filters.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return int
	 */
	public function count( $args = array() ) {
		global $wpdb;

		list( $where, $values ) = $this->build_where( $args );
		$sql = 'SELECT COUNT(*) FROM ' . Installer::table_name() . " {$where}"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $values ) ) {
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Build safe filter SQL.
	 *
	 * @param array<string,mixed> $args Query arguments.
	 * @return array{0:string,1:array<int,mixed>}
	 */
	private function build_where( $args ) {
		global $wpdb;

		$clauses = array();
		$values  = array();
		$outcome = isset( $args['outcome'] ) ? sanitize_key( $args['outcome'] ) : '';
		$search  = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';

		if ( in_array( $outcome, self::OUTCOMES, true ) ) {
			$clauses[] = 'outcome = %s';
			$values[]  = $outcome;
		}

		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$clauses[] = '(CAST(id AS CHAR) = %s OR provider_message_id LIKE %s OR batch_uuid LIKE %s OR recipient LIKE %s)';
			$values[]  = $search;
			$values[]  = $like;
			$values[]  = $like;
			$values[]  = $like;
		}

		return array( empty( $clauses ) ? '' : 'WHERE ' . implode( ' AND ', $clauses ), $values );
	}

	/**
	 * Normalize known row values.
	 *
	 * @param array<string,mixed> $row Row data.
	 * @return array<string,mixed>
	 */
	private function normalize_row( $row ) {
		if ( isset( $row['outcome'] ) && ! in_array( $row['outcome'], self::OUTCOMES, true ) ) {
			$row['outcome'] = 'unknown';
		}

		foreach ( array( 'post_id', 'http_status' ) as $integer_key ) {
			if ( array_key_exists( $integer_key, $row ) ) {
				$row[ $integer_key ] = null === $row[ $integer_key ] ? null : absint( $row[ $integer_key ] );
			}
		}

		foreach ( $row as $key => $value ) {
			if ( ! in_array( $key, array( 'post_id', 'http_status' ), true ) ) {
				$row[ $key ] = (string) $value;
			}
		}

		return $row;
	}

	/**
	 * Generate matching database formats.
	 *
	 * @param array<string,mixed> $row Row data.
	 * @return array<int,string>
	 */
	private function formats( $row ) {
		$formats = array();

		foreach ( array_keys( $row ) as $key ) {
			$formats[] = in_array( $key, array( 'post_id', 'http_status' ), true ) ? '%d' : '%s';
		}

		return $formats;
	}
}
