<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeConfig_Download {

	public static function handle( $request ) {

		$token = $request->get_param( 'token' );
		$slug  = $request->get_param( 'slug' );
		$key   = $request->get_param( 'api_key' );

		$transient = get_transient( 'codeconfig_dl_' . $token );

		if ( ! $transient || $transient['slug'] !== $slug ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'Invalid or expired download token.',
			), 403 );
		}

		delete_transient( 'codeconfig_dl_' . $token );

		$stored_key = $transient['api_key'] ?? '';

		$user_id = null;

		if ( $stored_key ) {
			$user = CodeConfig_Users_DB::get_user_by_api_key( $stored_key );

			if ( ! $user ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => 'API key invalid or user deactivated.',
				), 403 );
			}

			$user_id = $user['id'];
			CodeConfig_Users_DB::update_last_used( $user['id'] );
		} elseif ( $key ) {
			$user = CodeConfig_Users_DB::get_user_by_api_key( $key );

			if ( ! $user ) {
				return new WP_REST_Response( array(
					'success' => false,
					'message' => 'Invalid API key or user deactivated.',
				), 403 );
			}

			$user_id = $user['id'];
			CodeConfig_Users_DB::update_last_used( $user['id'] );
		}

		$latest = CodeConfig_Version_DB::get_active_version( $slug );
		$domain = $transient['domain'] ?? '';

		CodeConfig_Analytics_DB::log_request( array(
			'user_id'      => $user_id,
			'api_key'       => $key ? $key : $stored_key,
			'slug'          => $slug,
			'version'       => $latest['version'] ?? '',
			'request_type'  => 'download',
			'domain'        => $domain,
		) );

		// Increment download count
		if ( $latest && ! empty( $latest['version'] ) ) {
			CodeConfig_Version_DB::increment_download_count( $slug, $latest['version'] );
		}

		$latest = CodeConfig_Version_DB::get_active_version( $slug );

		if ( ! $latest || empty( $latest['download_path'] ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'Plugin file not found.',
			), 404 );
		}

		$file_path = $latest['download_path'];

		// If the stored path doesn't exist, try constructing it from storage dir
		if ( ! file_exists( $file_path ) ) {
			$slug    = $latest['slug'];
			$version = $latest['version'];
			$file_path = CODECONFIG_API_STORAGE . $slug . '-v' . $version . '.zip';
		}

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
