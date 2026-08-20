<?php
namespace YukDigitalz\AIConnectorGoogle;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Telemetry_Logger
 *
 * Records Google AI / Gemini API requests, auto-failover traces, latency, and token metrics.
 */
class Telemetry_Logger {

	/**
	 * Table name without WP prefix.
	 */
	const TABLE_NAME = 'yukdiconfo_logs';

	/**
	 * Get full table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	/**
	 * Create database table for telemetry logs.
	 */
	public static function create_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			provider varchar(50) NOT NULL DEFAULT 'gemini',
			requested_model varchar(100) NOT NULL,
			resolved_model varchar(100) NOT NULL,
			status_code int(5) NOT NULL DEFAULT 200,
			is_success tinyint(1) NOT NULL DEFAULT 1,
			is_failover tinyint(1) NOT NULL DEFAULT 0,
			failover_attempts int(3) NOT NULL DEFAULT 0,
			failover_trail text NULL,
			latency_ms int(10) NOT NULL DEFAULT 0,
			prompt_tokens int(10) NOT NULL DEFAULT 0,
			response_tokens int(10) NOT NULL DEFAULT 0,
			error_message text NULL,
			request_preview text NULL,
			response_preview text NULL,
			client_source varchar(100) NOT NULL DEFAULT 'wordpress',
			PRIMARY KEY  (id),
			KEY idx_timestamp (timestamp),
			KEY idx_resolved_model (resolved_model),
			KEY idx_is_failover (is_failover),
			KEY idx_status_code (status_code)
		) {$charset_collate};"; // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		dbDelta( $sql );
	}

	/**
	 * Log a request execution result.
	 *
	 * @param array $log_data Data to log.
	 * @return int|false Inserted row ID or false.
	 */
	public static function log( array $log_data ) {
		$settings = Settings::get_instance();
		if ( ! $settings->get( 'enable_telemetry', true ) ) {
			return false;
		}

		global $wpdb;
		$table_name = self::get_table_name();

		$data = array(
			'timestamp'         => current_time( 'mysql' ),
			'provider'          => isset( $log_data['provider'] ) ? sanitize_text_field( $log_data['provider'] ) : 'gemini',
			'requested_model'   => isset( $log_data['requested_model'] ) ? sanitize_text_field( $log_data['requested_model'] ) : '',
			'resolved_model'    => isset( $log_data['resolved_model'] ) ? sanitize_text_field( $log_data['resolved_model'] ) : '',
			'status_code'       => isset( $log_data['status_code'] ) ? intval( $log_data['status_code'] ) : 200,
			'is_success'        => ! empty( $log_data['is_success'] ) ? 1 : 0,
			'is_failover'       => ! empty( $log_data['is_failover'] ) ? 1 : 0,
			'failover_attempts' => isset( $log_data['failover_attempts'] ) ? intval( $log_data['failover_attempts'] ) : 0,
			'failover_trail'    => isset( $log_data['failover_trail'] ) ? maybe_serialize( $log_data['failover_trail'] ) : '',
			'latency_ms'        => isset( $log_data['latency_ms'] ) ? intval( $log_data['latency_ms'] ) : 0,
			'prompt_tokens'     => isset( $log_data['prompt_tokens'] ) ? intval( $log_data['prompt_tokens'] ) : 0,
			'response_tokens'   => isset( $log_data['response_tokens'] ) ? intval( $log_data['response_tokens'] ) : 0,
			'error_message'     => isset( $log_data['error_message'] ) ? sanitize_textarea_field( $log_data['error_message'] ) : '',
			'request_preview'   => isset( $log_data['request_preview'] ) ? wp_strip_all_tags( mb_substr( (string) $log_data['request_preview'], 0, 500 ) ) : '',
			'response_preview'  => isset( $log_data['response_preview'] ) ? wp_strip_all_tags( mb_substr( (string) $log_data['response_preview'], 0, 500 ) ) : '',
			'client_source'     => isset( $log_data['client_source'] ) ? sanitize_text_field( $log_data['client_source'] ) : 'wordpress',
		);

		$format = array(
			'%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s'
		);

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$inserted = $wpdb->insert( $table_name, $data, $format ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Periodically clean up old logs if table exceeds retention limits.
		if ( wp_rand( 1, 100 ) === 50 ) {
			self::prune_old_logs();
		}

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * Get recent logs for Admin Dashboard.
	 *
	 * @param int $limit Number of rows.
	 * @param int $offset Offset.
	 * @param array $filters Filter conditions.
	 * @return array
	 */
	public static function get_logs( $limit = 50, $offset = 0, $filters = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$where = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $filters['status'] ) ) {
			if ( 'success' === $filters['status'] ) {
				$where .= ' AND is_success = 1';
			} elseif ( 'error' === $filters['status'] ) {
				$where .= ' AND is_success = 0';
			} elseif ( 'failover' === $filters['status'] ) {
				$where .= ' AND is_failover = 1';
			}
		}

		if ( ! empty( $filters['model'] ) ) {
			$where .= ' AND (requested_model = %s OR resolved_model = %s)';
			$params[] = sanitize_text_field( $filters['model'] );
			$params[] = sanitize_text_field( $filters['model'] );
		}

		$params[] = intval( $limit );
		$params[] = intval( $offset );

		$query = "SELECT * FROM {$table_name} {$where} ORDER BY id DESC LIMIT %d OFFSET %d"; // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$prepared = $wpdb->prepare( $query, ...$params ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $prepared, ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		if ( empty( $rows ) ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			if ( ! empty( $row['failover_trail'] ) ) {
				$row['failover_trail'] = maybe_unserialize( $row['failover_trail'] );
			}
		}

		return $rows;
	}

	/**
	 * Get total log count for pagination.
	 *
	 * @param array $filters Filter conditions.
	 * @return int
	 */
	public static function get_logs_count( $filters = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$where = 'WHERE 1=1';
		$params = array();

		if ( ! empty( $filters['status'] ) ) {
			if ( 'success' === $filters['status'] ) {
				$where .= ' AND is_success = 1';
			} elseif ( 'error' === $filters['status'] ) {
				$where .= ' AND is_success = 0';
			} elseif ( 'failover' === $filters['status'] ) {
				$where .= ' AND is_failover = 1';
			}
		}

		if ( ! empty( $filters['model'] ) ) {
			$where .= ' AND (requested_model = %s OR resolved_model = %s)';
			$params[] = sanitize_text_field( $filters['model'] );
			$params[] = sanitize_text_field( $filters['model'] );
		}

		if ( ! empty( $params ) ) {
			$query = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} {$where}", ...$params ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		} else {
			$query = "SELECT COUNT(*) FROM {$table_name} {$where}"; // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $query ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Get aggregated statistics for overview dashboard.
	 * Optimized into a single aggregated SQL query for maximum scalability.
	 *
	 * @return array
	 */
	public static function get_stats() {
		global $wpdb;
		$table_name = self::get_table_name();

		// Check if table exists.
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) ) !== $table_name ) { // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			self::create_table();
		}

		$sql = "SELECT 
			COUNT(*) AS total_requests,
			SUM(CASE WHEN is_success = 1 THEN 1 ELSE 0 END) AS success_count,
			SUM(CASE WHEN is_failover = 1 THEN 1 ELSE 0 END) AS failover_count,
			SUM(CASE WHEN status_code = 503 THEN 1 ELSE 0 END) AS error_503_count,
			AVG(CASE WHEN is_success = 1 AND latency_ms > 0 THEN latency_ms ELSE NULL END) AS avg_latency
		FROM {$table_name} 
		WHERE client_source NOT IN ('live_playground', 'admin_test', 'quick_test')"; // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$total_requests  = isset( $row['total_requests'] ) ? (int) $row['total_requests'] : 0;
		$success_count   = isset( $row['success_count'] ) ? (int) $row['success_count'] : 0;
		$failover_count  = isset( $row['failover_count'] ) ? (int) $row['failover_count'] : 0;
		$error_503_count = isset( $row['error_503_count'] ) ? (int) $row['error_503_count'] : 0;
		$avg_latency     = isset( $row['avg_latency'] ) && null !== $row['avg_latency'] ? (int) round( $row['avg_latency'] ) : 0;

		$success_rate = $total_requests > 0 ? round( ( $success_count / $total_requests ) * 100, 1 ) : 100;

		return array(
			'total_requests'  => $total_requests,
			'success_count'   => $success_count,
			'success_rate'    => $success_rate,
			'failover_count'  => $failover_count,
			'error_503_count' => $error_503_count,
			'avg_latency_ms'  => $avg_latency,
		);
	}

	/**
	 * Clear all logs with strict capability checks.
	 *
	 * @param string $capability Capability required. Default 'manage_options'.
	 * @return bool
	 */
	public static function clear_logs( $capability = 'manage_options' ) {
		if ( ! current_user_can( $capability ) ) {
			return false;
		}

		global $wpdb;
		$table_name = self::get_table_name();
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return false !== $wpdb->query( "TRUNCATE TABLE {$table_name}" ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Prune old logs based on retention days and max rows.
	 */
	public static function prune_old_logs() {
		global $wpdb;
		$table_name = self::get_table_name();
		$settings = Settings::get_instance();

		$days = (int) $settings->get( 'log_retention_days', 30 );
		
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$days
			)
		);

		$max_rows = (int) $settings->get( 'max_log_rows', 1000 );
		
		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		
		if ( $total > $max_rows ) {
			$excess = $total - $max_rows;
			
			// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"DELETE FROM {$table_name} ORDER BY id ASC LIMIT %d", // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$excess
				)
			);
		}
	}
}
