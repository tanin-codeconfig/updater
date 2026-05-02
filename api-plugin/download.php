<?php
define( 'MYPLUGIN_API_PATH', rtrim( dirname( __FILE__ ), '/' ) . '/' );

require_once __DIR__ . '/../../../wp-load.php';

require_once MYPLUGIN_API_PATH . 'includes/class-version-db.php';
require_once MYPLUGIN_API_PATH . 'includes/class-users-db.php';

$token = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';
$slug  = isset( $_GET['slug'] ) ? sanitize_text_field( $_GET['slug'] ) : 'my-plugin';
$key   = isset( $_GET['api_key'] ) ? sanitize_text_field( $_GET['api_key'] ) : '';

if ( empty( $token ) ) {
	status_header( 403 );
	die( 'Missing download token.' );
}

$transient = get_transient( 'myplugin_dl_' . $token );

if ( ! $transient || $transient['slug'] !== $slug ) {
	status_header( 403 );
	die( 'Invalid or expired download token.' );
}

$stored_key = $transient['api_key'] ?? '';

if ( $stored_key ) {
	$user = MyPlugin_Users_DB::get_user_by_api_key( $stored_key );
	if ( ! $user ) {
		status_header( 403 );
		die( 'API key invalid or user deactivated.' );
	}
} elseif ( $key ) {
	$user = MyPlugin_Users_DB::get_user_by_api_key( $key );
	if ( ! $user ) {
		status_header( 403 );
		die( 'Invalid API key or user deactivated.' );
	}
}

delete_transient( 'myplugin_dl_' . $token );

$latest = MyPlugin_Version_DB::get_active_version( $slug );

if ( ! $latest || empty( $latest['download_path'] ) ) {
	status_header( 404 );
	die( 'Plugin file not found.' );
}

$file_path = $latest['download_path'];

// If stored path doesn't exist, try constructing it from storage dir
if ( ! file_exists( $file_path ) ) {
	$slug    = $latest['slug'];
	$version = $latest['version'];
	$file_path = MYPLUGIN_API_STORAGE . $slug . '-v' . $version . '.zip';
}

if ( ! file_exists( $file_path ) ) {
	status_header( 404 );
	die( 'Plugin ZIP does not exist on server.' );
}

$filename = basename( $file_path );
$filesize = filesize( $file_path );

header( 'Content-Description: File Transfer' );
header( 'Content-Type: application/octet-stream' );
header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
header( 'Content-Transfer-Encoding: binary' );
header( 'Content-Length: ' . $filesize );
header( 'Cache-Control: no-cache, no-store, must-revalidate' );
header( 'Pragma: no-cache' );
header( 'Expires: 0' );

ob_clean();
flush();
readfile( $file_path );
exit;
