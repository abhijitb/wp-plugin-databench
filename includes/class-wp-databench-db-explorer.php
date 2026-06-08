<?php
/**
 * Database explorer — all $wpdb query logic for browsing and editing tables.
 *
 * @package WP_DataBench
 */

defined( 'ABSPATH' ) || exit;

/**
 * Database explorer.
 *
 * @internal
 */
class WP_DataBench_DB_Explorer {

	const PER_PAGE = 25;

	// ── Helpers ───────────────────────────────────────────────────────────────

	/**
	 * Validates a table name against the live table list.
	 *
	 * @param string $name Requested table name.
	 * @return string|WP_Error Validated table name, or a 404 WP_Error if not found.
	 */
	private static function validate_table( $name ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_col( 'SHOW TABLES' );
		if ( ! in_array( $name, $tables, true ) ) {
			return new WP_Error( 'invalid_table', 'Table not found.', array( 'status' => 404 ) );
		}
		return $name;
	}

	/**
	 * Backtick-quotes a validated identifier to safely use it in SQL.
	 *
	 * @param string $identifier Column or table name, already validated against a live whitelist.
	 * @return string Backtick-quoted identifier, e.g. `column_name`.
	 */
	private static function qi( $identifier ) {
		return '`' . str_replace( '`', '``', $identifier ) . '`';
	}

	/**
	 * Returns column metadata for a table via DESCRIBE.
	 *
	 * @param string $table Validated table name.
	 * @return array<array{name:string,type:string,null:bool,key:string,default:string|null,extra:string}>
	 */
	private static function get_columns( $table ) {
		global $wpdb;
		$tbl = self::qi( $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$cols = $wpdb->get_results( "DESCRIBE {$tbl}", ARRAY_A );
		return array_map(
			static function ( $col ) {
				return array(
					'name'    => $col['Field'],
					'type'    => $col['Type'],
					'null'    => 'YES' === $col['Null'],
					'key'     => $col['Key'],
					'default' => $col['Default'],
					'extra'   => $col['Extra'],
				);
			},
			$cols ?? array()
		);
	}

	/**
	 * Returns the name of the table's primary key column, or null if none exists.
	 *
	 * @param string $table Validated table name.
	 * @return string|null
	 */
	private static function get_primary_key( $table ) {
		global $wpdb;
		$tbl = self::qi( $table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$keys = $wpdb->get_results( "SHOW KEYS FROM {$tbl} WHERE Key_name = 'PRIMARY'", ARRAY_A );
		return ! empty( $keys ) ? $keys[0]['Column_name'] : null;
	}

	// ── REST handlers ─────────────────────────────────────────────────────────

	/**
	 * GET /tables — lists all tables with approximate row counts and storage engine.
	 *
	 * @param WP_REST_Request $request Unused.
	 * @return WP_REST_Response
	 */
	public static function get_tables( WP_REST_Request $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$tables = $wpdb->get_results( 'SHOW TABLE STATUS', ARRAY_A );
		$result = array_map(
			static function ( $t ) {
				return array(
					'name'   => $t['Name'],
					'rows'   => (int) $t['Rows'],
					'engine' => $t['Engine'],
				);
			},
			$tables ?? array()
		);
		return rest_ensure_response( $result );
	}

	/**
	 * GET /tables/{table}/structure — returns column definitions and the primary key name.
	 *
	 * @param WP_REST_Request $request Route param: table.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_structure( WP_REST_Request $request ) {
		$table = self::validate_table( $request->get_param( 'table' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		return rest_ensure_response(
			array(
				'columns'     => self::get_columns( $table ),
				'primary_key' => self::get_primary_key( $table ),
			)
		);
	}

	/**
	 * GET /tables/{table}/rows — returns a paginated, searchable, sortable row set.
	 *
	 * @param WP_REST_Request $request Query params: page, search, orderby, order.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_rows( WP_REST_Request $request ) {
		global $wpdb;

		$table = self::validate_table( $request->get_param( 'table' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$page     = max( 1, (int) ( $request->get_param( 'page' ) ?? 1 ) );
		$search   = sanitize_text_field( (string) ( $request->get_param( 'search' ) ?? '' ) );
		$orderby  = (string) ( $request->get_param( 'orderby' ) ?? '' );
		$order    = 'DESC' === strtoupper( (string) ( $request->get_param( 'order' ) ?? '' ) ) ? 'DESC' : 'ASC';
		$per_page = self::PER_PAGE;
		$offset   = ( $page - 1 ) * $per_page;
		$tbl      = self::qi( $table );

		// Validate ORDER BY column.
		$orderby_sql = '';
		if ( '' !== $orderby ) {
			$valid_cols = array_column( self::get_columns( $table ), 'name' );
			if ( ! in_array( $orderby, $valid_cols, true ) ) {
				return new WP_Error( 'invalid_column', 'Invalid column.', array( 'status' => 400 ) );
			}
			$orderby_sql = ' ORDER BY ' . self::qi( $orderby ) . " {$order}";
		}

		// Build WHERE clause for full-text search across all columns.
		if ( '' !== $search ) {
			$cols        = self::get_columns( $table );
			$like        = '%' . $wpdb->esc_like( $search ) . '%';
			$where_parts = array();
			$like_args   = array();
			foreach ( $cols as $col ) {
				$where_parts[] = self::qi( $col['name'] ) . ' LIKE %s';
				$like_args[]   = $like;
			}
			$where_sql = ' WHERE ' . implode( ' OR ', $where_parts );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$count_query = $wpdb->prepare( "SELECT COUNT(*) FROM {$tbl}{$where_sql}", ...$like_args );
			$rows_args   = array_merge( $like_args, array( $per_page, $offset ) );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$rows_query = $wpdb->prepare( "SELECT * FROM {$tbl}{$where_sql}{$orderby_sql} LIMIT %d OFFSET %d", ...$rows_args );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$count_query = "SELECT COUNT(*) FROM {$tbl}";
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows_query = $wpdb->prepare( "SELECT * FROM {$tbl}{$orderby_sql} LIMIT %d OFFSET %d", $per_page, $offset );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $count_query );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $rows_query, ARRAY_A );

		return rest_ensure_response(
			array(
				'rows'     => $rows ?? array(),
				'total'    => $total,
				'page'     => $page,
				'per_page' => $per_page,
			)
		);
	}

	/**
	 * GET /tables/{table}/rows/{pk} — fetches a single row by primary key value.
	 *
	 * @param WP_REST_Request $request Route params: table, pk.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_row( WP_REST_Request $request ) {
		global $wpdb;

		$table = self::validate_table( $request->get_param( 'table' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$pk = self::get_primary_key( $table );
		if ( ! $pk ) {
			return new WP_Error( 'no_primary_key', 'Table has no primary key.', array( 'status' => 400 ) );
		}

		$tbl    = self::qi( $table );
		$pk_col = self::qi( $pk );
		$pk_val = $request->get_param( 'pk' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tbl} WHERE {$pk_col} = %s", $pk_val ), ARRAY_A );

		if ( ! $row ) {
			return new WP_Error( 'not_found', 'Row not found.', array( 'status' => 404 ) );
		}

		return rest_ensure_response( $row );
	}

	/**
	 * POST /tables/{table}/rows — inserts a new row from the JSON request body.
	 *
	 * @param WP_REST_Request $request JSON body keyed by column name.
	 * @return WP_REST_Response|WP_Error Response contains insert_id on success.
	 */
	public static function insert_row( WP_REST_Request $request ) {
		global $wpdb;

		$table = self::validate_table( $request->get_param( 'table' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$body       = $request->get_json_params() ?? array();
		$valid_cols = array_column( self::get_columns( $table ), 'name' );
		$data       = array_intersect_key( $body, array_flip( $valid_cols ) );

		if ( empty( $data ) ) {
			return new WP_Error( 'empty_data', 'No valid fields provided.', array( 'status' => 400 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return new WP_Error( 'insert_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'insert_id' => $wpdb->insert_id ) );
	}

	/**
	 * PUT /tables/{table}/rows/{pk} — updates an existing row; the primary key field is ignored.
	 *
	 * @param WP_REST_Request $request JSON body with fields to update. Route params: table, pk.
	 * @return WP_REST_Response|WP_Error Response contains the number of updated rows.
	 */
	public static function update_row( WP_REST_Request $request ) {
		global $wpdb;

		$table = self::validate_table( $request->get_param( 'table' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$pk = self::get_primary_key( $table );
		if ( ! $pk ) {
			return new WP_Error( 'no_primary_key', 'Table has no primary key.', array( 'status' => 400 ) );
		}

		$pk_val     = $request->get_param( 'pk' );
		$body       = $request->get_json_params() ?? array();
		$valid_cols = array_column( self::get_columns( $table ), 'name' );
		$data       = array_intersect_key( $body, array_flip( $valid_cols ) );
		unset( $data[ $pk ] ); // Never update the primary key.

		if ( empty( $data ) ) {
			return new WP_Error( 'empty_data', 'No valid fields to update.', array( 'status' => 400 ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update( $table, $data, array( $pk => $pk_val ) );

		if ( false === $result ) {
			return new WP_Error( 'update_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'updated' => $result ) );
	}

	const QUERY_ROW_CAP = 1000;

	/**
	 * POST /query — executes a SELECT query and returns rows, columns, timing, and cap status.
	 *
	 * Non-SELECT statements are rejected with 403. When the query contains no LIMIT clause,
	 * one is appended automatically, capping results at QUERY_ROW_CAP rows.
	 *
	 * @param WP_REST_Request $request JSON body: { sql: string }.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function execute_query( WP_REST_Request $request ) {
		global $wpdb;

		$sql = trim( (string) ( $request->get_param( 'sql' ) ?? '' ) );

		if ( '' === $sql ) {
			return new WP_Error( 'empty_query', 'No SQL provided.', array( 'status' => 400 ) );
		}

		if ( ! preg_match( '/^\s*SELECT\b/i', $sql ) ) {
			return new WP_Error( 'select_only', 'Only SELECT queries are allowed.', array( 'status' => 403 ) );
		}

		// Enforce a hard row cap — append LIMIT if the query doesn't already have one.
		if ( ! preg_match( '/\bLIMIT\b/i', $sql ) ) {
			$sql .= ' LIMIT ' . self::QUERY_ROW_CAP;
		}

		$start = microtime( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		$ms   = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( $wpdb->last_error ) {
			return new WP_Error( 'query_error', $wpdb->last_error, array( 'status' => 400 ) );
		}

		$count  = count( $rows ?? array() );
		$capped = ! preg_match( '/\bLIMIT\b/i', $request->get_param( 'sql' ) ) && self::QUERY_ROW_CAP === $count;

		return rest_ensure_response(
			array(
				'rows'    => $rows ?? array(),
				'columns' => ! empty( $rows ) ? array_keys( $rows[0] ) : array(),
				'count'   => $count,
				'time_ms' => $ms,
				'capped'  => $capped,
			)
		);
	}

	/**
	 * DELETE /tables/{table}/rows/{pk} — deletes the row matching the primary key value.
	 *
	 * @param WP_REST_Request $request Route params: table, pk.
	 * @return WP_REST_Response|WP_Error Response contains { deleted: true } on success.
	 */
	public static function delete_row( WP_REST_Request $request ) {
		global $wpdb;

		$table = self::validate_table( $request->get_param( 'table' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}

		$pk = self::get_primary_key( $table );
		if ( ! $pk ) {
			return new WP_Error( 'no_primary_key', 'Table has no primary key.', array( 'status' => 400 ) );
		}

		$pk_val = $request->get_param( 'pk' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->delete( $table, array( $pk => $pk_val ) );

		if ( false === $result ) {
			return new WP_Error( 'delete_failed', $wpdb->last_error, array( 'status' => 500 ) );
		}

		return rest_ensure_response( array( 'deleted' => true ) );
	}
}
