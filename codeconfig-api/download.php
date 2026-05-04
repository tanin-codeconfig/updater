<?php
define( 'CODECONFIG_API_PATH', rtrim( dirname( __FILE__ ), '/' ) . '/' );

require_once __DIR__ . '/../../../wp-load.php';

require_once CODECONFIG_API_PATH . 'includes/class-version-db.php';
require_once CODECONFIG_API_PATH . 'includes/class-users-db.php';
require_once CODECONFIG_API_PATH . 'includes/class-analytics-db.php';

$token = isset( $_GET['token'] ) ? sanitize_text_field( $_GET['token'] ) : '';
$slug  = isset( $_GET['slug'] ) ? sanitize_text_field( $_GET['slug'] ) : 'my-plugin';
$key   = isset( $_GET['api_key'] ) ? sanitize_text_field( $_GET['api_key'] ) : '';

if ( empty( $token ) ) {
	status_header( 403 );
	die( 'Missing download token.' );
}

$transient = get_transient( 'codeconfig_dl_' . $token );

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

delete_transient( 'codeconfig_dl_' . $token );

$latest = MyPlugin_Version_DB::get_active_version( $slug );

if ( ! $latest || empty( $latest['download_path'] ) ) {
	status_header( 404 );
	die( 'Plugin file not found.' );
}

// Log the download
if ( class_exists( 'MyPlugin_Analytics_DB' ) ) {
    $log_user_id = isset( $user ) && $user ? $user['id'] : null;
    $log_api_key = $stored_key ? $stored_key : ( $key ? $key : '' );
    $log_domain = $transient['domain'] ?? '';
    
    MyPlugin_Analytics_DB::log_request( array(
        'user_id'      => $log_user_id,
        'api_key'      => $log_api_key,
        'slug'         => $slug,
        'version'      => $latest['version'] ?? '',
        'request_type' => 'download',
        'domain'       => $log_domain,
    ) );
}

// Increment download count in versions table
if ( class_exists( 'MyPlugin_Version_DB' ) && ! empty( $latest['version'] ) ) {
    MyPlugin_Version_DB::increment_download_count( $slug, $latest['version'] );
}

if ( ! $latest || empty( $latest['download_path'] ) ) {
	status_header( 404 );
	die( 'Plugin file not found.' );
}

$possible_paths = array(
	$latest['download_path'],
	CODECONFIG_API_STORAGE . $latest['slug'] . '-v' . $latest['version'] . '.zip',
	CODECONFIG_API_STORAGE . basename( $latest['download_path'] ),
);

$file_path = null;
foreach ( $possible_paths as $path ) {
	if ( file_exists( $path ) ) {
		$file_path = $path;
		break;
	}
}

if ( ! $file_path ) {
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
