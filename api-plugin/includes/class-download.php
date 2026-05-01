<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MyPlugin_Download {

	public static function handle( $request ) {

		$token = $request->get_param( 'token' );
		$slug  = $request->get_param( 'slug' );
		$key   = $request->get_param( 'api_key' );

		$transient = get_transient( 'myplugin_dl_' . $token );

		if ( ! $transient || $transient['slug'] !== $slug ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'Invalid or expired download token.',
			), 403 );
		}

		delete_transient( 'myplugin_dl_' . $token );

		$stored_key = $transient['api_key'] ?? '';

		if ( $stored_key ) {
			$user = MyPlugin_Users_DB::get_user_by_api_key( $stored_key );

			if ( ! $user ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => 'API key invalid or user deactivated.',
				), 403 );
			}
		} elseif ( $key ) {
			$user = MyPlugin_Users_DB::get_user_by_api_key( $key );

			if ( ! $user ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => 'Invalid API key or user deactivated.',
				), 403 );
			}
		}

		$latest = MyPlugin_Version_DB::get_active_version( $slug );

		if ( ! $latest || empty( $latest['download_path'] ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'Plugin file not found.',
			), 404 );
		}

		$file_path = $latest['download_path'];

		if ( ! file_exists( $file_path ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'Plugin ZIP does not exist on server.',
			), 404 );
		}

		$filename = basename( $file_path );

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );

		readfile( $file_path );
		exit;
	}
}
