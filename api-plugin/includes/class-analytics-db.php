<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MyPlugin_Analytics_DB {

	const TABLE_NAME = 'myplugin_analytics';

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	public static function create_table() {
		global $wpdb;

		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NULL,
			api_key VARCHAR(64) NULL,
			slug VARCHAR(100) NOT NULL,
			version VARCHAR(20) NULL,
			request_type VARCHAR(20) NOT NULL,
			ip_address VARCHAR(45) NULL,
			domain VARCHAR(255) NULL,
			user_agent TEXT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY slug (slug),
			KEY request_type (request_type),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	public static function log_request( $args ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = array(
			'user_id'      => null,
			'api_key'       => '',
			'slug'          => '',
			'version'       => '',
			'request_type'  => 'update-check',
			'ip_address'   => self::get_client_ip(),
			'domain'        => '',
			'user_agent'    => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
		);

		$data = wp_parse_args( $args, $defaults );

		return $wpdb->insert(
			$table_name,
			array(
				'user_id'      => $data['user_id'] ? $data['user_id'] : null,
				'api_key'       => sanitize_text_field( $data['api_key'] ),
				'slug'          => sanitize_text_field( $data['slug'] ),
				'version'       => sanitize_text_field( $data['version'] ),
				'request_type'  => sanitize_text_field( $data['request_type'] ),
				'ip_address'   => sanitize_text_field( $data['ip_address'] ),
				'domain'        => esc_url_raw( $data['domain'] ),
				'user_agent'    => sanitize_text_field( $data['user_agent'] ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public static function get_stats( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = array(
			'start_date'    => gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) ),
			'end_date'      => null,
			'slug'          => '',
			'request_type'  => '',
			'user_id'       => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( '1=1' );
		$values = array();

		if ( $args['start_date'] ) {
			$where[] = 'a.created_at >= %s';
			$values[] = $args['start_date'];
		}

		if ( $args['end_date'] ) {
			$where[] = 'a.created_at <= %s';
			$values[] = $args['end_date'];
		}

		if ( $args['slug'] ) {
			$where[] = 'slug = %s';
			$values[] = $args['slug'];
		}

		if ( $args['request_type'] ) {
			$where[] = 'request_type = %s';
			$values[] = $args['request_type'];
		}

		if ( $args['user_id'] ) {
			$where[] = 'user_id = %d';
			$values[] = (int) $args['user_id'];
		}

		$where_sql = implode( ' AND ', $where );

		return array(
			'total_update_checks' => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} a WHERE {$where_sql} AND request_type = 'update-check'",
					$values
				)
			),
			'total_downloads'     => (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table_name} a WHERE {$where_sql} AND request_type = 'download'",
					$values
				)
			),
			'by_slug' => $wpdb->get_results(
				$wpdb->prepare(
					"SELECT slug,
						SUM(CASE WHEN request_type = 'update-check' THEN 1 ELSE 0 END) as update_checks,
						SUM(CASE WHEN request_type = 'download' THEN 1 ELSE 0 END) as downloads,
						COUNT(*) as total
					FROM {$table_name} a
					WHERE {$where_sql}
					GROUP BY slug
					ORDER BY total DESC
					LIMIT 10",
					$values
				),
				ARRAY_A
			),
			'recent_activity' => $wpdb->get_results(
				$wpdb->prepare(
					"SELECT a.*, u.name as user_name FROM {$table_name} a LEFT JOIN {$wpdb->prefix}myplugin_users u ON a.user_id = u.id WHERE {$where_sql} ORDER BY a.created_at DESC LIMIT 20",
					$values
				),
				ARRAY_A
			),
		);
	}

	private static function get_client_ip() {
		$keys = array( 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ips = explode( ',', $_SERVER[ $key ] );
				return trim( $ips[0] );
			}
		}

		return '';
	}
}
