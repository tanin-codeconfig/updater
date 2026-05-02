<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once MYPLUGIN_API_PATH . 'includes/class-update-check.php';

class MyPlugin_Routes {

	public static function register_routes() {

		register_rest_route( 'myplugin/v1', '/update-check', array(
			'methods'             => 'GET',
			'callback'            => array( 'MyPlugin_Update_Check', 'handle' ),
			'permission_callback' => array( 'MyPlugin_Routes', 'public_access' ),
			'args'                => array(
				'version' => array(
					'required'    => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'slug' => array(
					'default'     => 'my-plugin',
					'sanitize_callback' => 'sanitize_text_field',
				),
				'api_key' => array(
					'default'     => '',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( 'myplugin/v1', '/latest-download', array(
			'methods'             => 'GET',
			'callback'            => array( 'MyPlugin_Routes', 'handle_latest_download' ),
			'permission_callback' => array( 'MyPlugin_Routes', 'public_access' ),
			'args'                => array(
				'slug' => array(
					'default'     => 'my-plugin',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );
	}

	public static function handle_latest_download( $request ) {
		$slug = $request->get_param( 'slug' );

		$latest = MyPlugin_Version_DB::get_active_version( $slug );

		if ( ! $latest || empty( $latest['download_path'] ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'message' => 'No active version found.',
			), 404 );
		}

		$file_path = $latest['download_path'];

		if ( ! file_exists( $file_path ) ) {
			// Try constructing path from storage dir
			$file_path = MYPLUGIN_API_STORAGE . $slug . '-v' . $latest['version'] . '.zip';
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

	public static function public_access() {
		return true;
	}
}
