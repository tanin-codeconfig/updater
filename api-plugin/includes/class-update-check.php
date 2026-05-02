<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MyPlugin_Update_Check {

	public static function handle( $request ) {

		$slug    = $request->get_param( 'slug' );
		$version = $request->get_param( 'version' );
		$key     = $request->get_param( 'api_key' );
		$domain  = $request->get_param( 'domain' );

		// Require domain
		if ( empty( $domain ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'Domain parameter is required.',
			), 400 );
		}

		$user_id = null;

		if ( $key ) {
			$user = MyPlugin_Users_DB::get_user_by_api_key( $key );

			if ( ! $user ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => 'Invalid API key or user deactivated.',
				), 403 );
			}

			$user_id = $user['id'];
			MyPlugin_Users_DB::update_last_used( $user['id'] );
		}

		MyPlugin_Analytics_DB::log_request( array(
			'user_id'      => $user_id,
			'api_key'       => $key,
			'slug'          => $slug,
			'version'       => $version,
			'request_type'  => 'update-check',
			'domain'        => $domain,
		) );

		$latest = MyPlugin_Version_DB::get_active_version( $slug );

		if ( ! $latest ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'No version found.',
			), 404 );
		}

		if ( version_compare( $version, $latest['version'], '>=' ) ) {
			return new WP_REST_Response( array(
				'success'   => true,
				'update'    => false,
				'version'   => $latest['version'],
				'message'   => 'You are running the latest version.',
			), 200 );
		}

		$token = self::generate_download_token( $slug, $key, $domain );

		$download_url = self::get_download_url( $token, $slug, $key );

		// Get download count
		$download_count = MyPlugin_Version_DB::get_download_count( $slug, $latest['version'] );

		return new WP_REST_Response( array(
			'success'     => true,
			'update'      => true,
			'new_version' => $latest['version'],
			'slug'        => $slug,
			'package'     => $download_url,
			'changelog'   => $latest['changelog'] ?? '',
			'downloads'   => (int) $download_count,
		), 200 );
	}

	private static function generate_download_token( $slug, $api_key = '', $domain = '' ) {
		$token = wp_generate_password( 32, false );

		set_transient(
			'myplugin_dl_' . $token,
			array(
				'slug'      => $slug,
				'api_key'   => $api_key,
				'created'   => time(),
				'domain'    => $domain,
			),
			300
		);

		return $token;
	}

	private static function get_download_url( $token, $slug, $api_key = '' ) {

		$plugin_dir = basename( MYPLUGIN_API_PATH );
		$site_url   = untrailingslashit( get_site_url() );

		$download_url = sprintf( '%s/wp-content/plugins/%s/download.php?token=%s&slug=%s',
			$site_url,
			$plugin_dir,
			urlencode( $token ),
			urlencode( $slug )
		);

		if ( $api_key ) {
			$download_url .= '&api_key=' . urlencode( $api_key );
		}

		return $download_url;
	}
}
