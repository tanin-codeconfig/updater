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
	}

	public static function public_access() {
		return true;
	}
}
