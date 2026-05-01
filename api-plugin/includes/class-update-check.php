<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MyPlugin_Update_Check {

	public static function handle( $request ) {

		$slug    = $request->get_param( 'slug' );
		$version = $request->get_param( 'version' );
		$key     = $request->get_param( 'api_key' );

		if ( $key ) {
			$user = MyPlugin_Users_DB::get_user_by_api_key( $key );

			if ( ! $user ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => 'Invalid API key or user deactivated.',
				), 403 );
			}
		}

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

		$token = self::generate_download_token( $slug, $key );

		$download_url = self::get_download_url( $token, $slug, $key );

		return new WP_REST_Response( array(
			'success'     => true,
			'update'      => true,
			'new_version' => $latest['version'],
			'slug'        => $slug,
			'package'     => $download_url,
			'changelog'   => $latest['changelog'] ?? '',
		), 200 );
	}

	private static function generate_download_token( $slug, $api_key = '' ) {
		$token = wp_generate_password( 32, false );

		set_transient(
			'myplugin_dl_' . $token,
			array(
				'slug'      => $slug,
				'api_key'   => $api_key,
				'created'   => time(),
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
