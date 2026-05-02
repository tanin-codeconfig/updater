<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MyPlugin_Version_DB {

	const TABLE_NAME = 'myplugin_versions';

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
			version VARCHAR(20) NOT NULL,
			slug VARCHAR(100) NOT NULL,
			changelog TEXT,
			download_path VARCHAR(500),
			download_count BIGINT UNSIGNED DEFAULT 0,
			is_active TINYINT(1) DEFAULT 1,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY slug (slug),
			KEY is_active (is_active)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop_table() {
		global $wpdb;
		$table_name = self::get_table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}

	public static function insert_version( $args ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$wpdb->insert(
			$table_name,
			array(
				'version'     => sanitize_text_field( $args['version'] ),
				'slug'        => sanitize_text_field( $args['slug'] ),
				'changelog'   => wp_kses_post( $args['changelog'] ?? '' ),
				'download_path' => sanitize_text_field( $args['download_path'] ?? '' ),
				'is_active'   => (int) ( $args['is_active'] ?? 1 ),
			),
			array( '%s', '%s', '%s', '%s', '%d' )
		);

		return $wpdb->insert_id;
	}

	public static function update_version( $id, $args ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$data = array();
		$format = array();

		if ( isset( $args['version'] ) ) {
			$data['version'] = sanitize_text_field( $args['version'] );
			$format[] = '%s';
		}
		if ( isset( $args['changelog'] ) ) {
			$data['changelog'] = wp_kses_post( $args['changelog'] );
			$format[] = '%s';
		}
		if ( isset( $args['download_path'] ) ) {
			$data['download_path'] = sanitize_text_field( $args['download_path'] );
			$format[] = '%s';
		}
		if ( isset( $args['is_active'] ) ) {
			$data['is_active'] = (int) $args['is_active'];
			$format[] = '%d';
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

	public static function get_active_version( $slug = 'my-plugin' ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE slug = %s AND is_active = 1 ORDER BY id DESC LIMIT 1",
				sanitize_text_field( $slug )
			),
			ARRAY_A
		);
	}

	public static function get_all_versions( $slug = null ) {
		global $wpdb;
		$table_name = self::get_table_name();

		if ( $slug ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE slug = %s ORDER BY created_at DESC",
					sanitize_text_field( $slug )
				),
				ARRAY_A
			);
		}

		return $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY created_at DESC",
			ARRAY_A
		);
	}

	public static function get_unique_slugs() {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_col(
			"SELECT DISTINCT slug FROM {$table_name} ORDER BY slug ASC"
		);
	}

	public static function get_existing_version( $slug, $version ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE slug = %s AND version = %s LIMIT 1",
				sanitize_text_field( $slug ),
				sanitize_text_field( $version )
			),
			ARRAY_A
		);
	}

	public static function delete_version( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->delete(
			$table_name,
			array( 'id' => (int) $id ),
			array( '%d' )
		);
	}

	public static function get_existing_version_by_id( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d LIMIT 1",
				(int) $id
			),
			ARRAY_A
		);
	}

	public static function increment_download_count( $slug, $version ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table_name} SET download_count = download_count + 1 WHERE slug = %s AND version = %s",
				sanitize_text_field( $slug ),
				sanitize_text_field( $version )
			)
		);
	}

	public static function get_download_count( $slug, $version ) {
		global $wpdb;
		$table_name = self::get_table_name();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT download_count FROM {$table_name} WHERE slug = %s AND version = %s LIMIT 1",
				sanitize_text_field( $slug ),
				sanitize_text_field( $version )
			)
		);
	}
}
