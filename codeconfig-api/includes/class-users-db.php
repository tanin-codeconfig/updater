<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeConfig_Users_DB {

	const TABLE_NAME = 'codeconfig_users';

	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	public static function create_table() {
		global $wpdb;

		$table_name  = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(100) NOT NULL,
			email VARCHAR(150) NOT NULL,
			api_key VARCHAR(64) NOT NULL,
			domain VARCHAR(255),
			is_active TINYINT(1) DEFAULT 1,
			last_used_at DATETIME NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY api_key (api_key),
			KEY email (email),
			KEY is_active (is_active),
			KEY last_used_at (last_used_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	public static function generate_api_key() {
		return wp_generate_password( 32, false );
	}

	public static function insert_user( $args ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$api_key = isset( $args['api_key'] ) ? $args['api_key'] : self::generate_api_key();

		$wpdb->insert(
			$table_name,
			array(
				'name'      => sanitize_text_field( $args['name'] ),
				'email'     => sanitize_email( $args['email'] ),
				'api_key'   => sanitize_text_field( $api_key ),
				'domain'    => isset( $args['domain'] ) ? esc_url_raw( $args['domain'] ) : '',
				'is_active' => (int) ( $args['is_active'] ?? 1 ),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		return $wpdb->insert_id;
	}

	public static function update_user( $id, $args ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$data = array();
		$format = array();

		if ( isset( $args['name'] ) ) {
			$data['name'] = sanitize_text_field( $args['name'] );
			$format[] = '%s';
		}
		if ( isset( $args['email'] ) ) {
			$data['email'] = sanitize_email( $args['email'] );
			$format[] = '%s';
		}
		if ( isset( $args['domain'] ) ) {
			$data['domain'] = esc_url_raw( $args['domain'] );
			$format[] = '%s';
		}
		if ( isset( $args['is_active'] ) ) {
			$data['is_active'] = (int) $args['is_active'];
			$format[] = '%d';
		}
		if ( isset( $args['api_key'] ) ) {
			$data['api_key'] = sanitize_text_field( $args['api_key'] );
			$format[] = '%s';
		}

		if ( empty( $data ) ) {
			return false;
		}

		return $wpdb->update(
			$table_name,
			$data,
			array( 'id' => (int) $id ),
			$format,
			array( '%d' )
		);
	}

	public static function get_user( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				(int) $id
			),
			ARRAY_A
		);
	}

	public static function get_user_by_api_key( $api_key ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE api_key = %s AND is_active = 1 LIMIT 1",
				sanitize_text_field( $api_key )
			),
			ARRAY_A
		);
	}

	public static function get_all_users() {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY created_at DESC",
			ARRAY_A
		);
	}

	public static function get_active_users() {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_results(
			"SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY created_at DESC",
			ARRAY_A
		);
	}

	public static function delete_user( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->delete(
			$table_name,
			array( 'id' => (int) $id ),
			array( '%d' )
		);
	}

	public static function update_last_used( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->update(
			$table_name,
			array( 'last_used_at' => current_time( 'mysql' ) ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function get_users_filtered( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(name LIKE %s OR email LIKE %s OR domain LIKE %s)';
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		if ( isset( $args['is_active'] ) && '' !== $args['is_active'] ) {
			$where[] = 'is_active = %d';
			$values[] = (int) $args['is_active'];
		}

		if ( ! empty( $args['domain'] ) ) {
			$where[] = 'domain = %s';
			$values[] = esc_url_raw( $args['domain'] );
		}

		$orderby = 'created_at';
		$order   = 'DESC';

		if ( ! empty( $args['orderby'] ) && in_array( $args['orderby'], array( 'name', 'email', 'created_at', 'last_used_at' ), true ) ) {
			$orderby = $args['orderby'];
		}

		if ( ! empty( $args['order'] ) && in_array( strtoupper( $args['order'] ), array( 'ASC', 'DESC' ), true ) ) {
			$order = $args['order'];
		}

		$limit  = isset( $args['limit'] ) ? (int) $args['limit'] : 20;
		$offset = isset( $args['offset'] ) ? (int) $args['offset'] : 0;

		$sql = "SELECT * FROM {$table_name} WHERE " . implode( ' AND ', $where ) . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$values[] = $limit;
		$values[] = $offset;

		if ( ! empty( $values ) ) {
			return $wpdb->get_results(
				$wpdb->prepare( $sql, $values ),
				ARRAY_A
			);
		}

		return $wpdb->get_results( $sql, ARRAY_A );
	}

	public static function get_users_count( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$where = array( '1=1' );
		$values = array();

		if ( ! empty( $args['search'] ) ) {
			$where[] = '(name LIKE %s OR email LIKE %s OR domain LIKE %s)';
			$search = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		if ( isset( $args['is_active'] ) && '' !== $args['is_active'] ) {
			$where[] = 'is_active = %d';
			$values[] = (int) $args['is_active'];
		}

		if ( ! empty( $args['domain'] ) ) {
			$where[] = 'domain = %s';
			$values[] = esc_url_raw( $args['domain'] );
		}

		$sql = "SELECT COUNT(*) FROM {$table_name} WHERE " . implode( ' AND ', $where );

		if ( ! empty( $values ) ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $values ) );
		}

		return (int) $wpdb->get_var( $sql );
	}

	public static function get_all_domains() {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_col(
			"SELECT DISTINCT domain FROM {$table_name} WHERE domain != '' ORDER BY domain ASC"
		);
	}

	public static function get_user_by_domain( $domain ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE domain = %s LIMIT 1",
				esc_url_raw( $domain )
			),
			ARRAY_A
		);
	}
}
