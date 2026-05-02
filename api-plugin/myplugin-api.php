<?php

/**
 * Plugin Name: MyPlugin API
 * Description: Custom update API server for MyPlugin (Free + Pro)
 * Version: 1.0.5
 * Author: Your Name
 * Text Domain: myplugin-api
 */

if (! defined('ABSPATH')) {
    exit;
}

define('MYPLUGIN_API_VERSION', '1.0.7');
define('MYPLUGIN_API_PATH', plugin_dir_path(__FILE__));
define('MYPLUGIN_API_URL', plugin_dir_url(__FILE__));

// Use wp-content/uploads for storage
$myplugin_upload_dir = wp_upload_dir();
define('MYPLUGIN_API_STORAGE', $myplugin_upload_dir['basedir'] . '/myplugin-api/');
define('MYPLUGIN_API_STORAGE_URL', $myplugin_upload_dir['baseurl'] . '/myplugin-api/');

require_once MYPLUGIN_API_PATH . 'includes/class-version-db.php';
require_once MYPLUGIN_API_PATH . 'includes/class-users-db.php';
require_once MYPLUGIN_API_PATH . 'includes/class-analytics-db.php';
require_once MYPLUGIN_API_PATH . 'includes/class-analytics-admin.php';
require_once MYPLUGIN_API_PATH . 'includes/class-routes.php';
require_once MYPLUGIN_API_PATH . 'includes/class-update-check.php';
require_once MYPLUGIN_API_PATH . 'includes/class-download.php';
require_once MYPLUGIN_API_PATH . 'includes/class-zip-parser.php';
require_once MYPLUGIN_API_PATH . 'includes/class-admin.php';
require_once MYPLUGIN_API_PATH . 'includes/class-users-admin.php';

register_activation_hook(__FILE__, array( 'MyPlugin_Version_DB', 'create_table' ));
register_activation_hook(__FILE__, array( 'MyPlugin_Users_DB', 'create_table' ));
register_activation_hook(__FILE__, array( 'MyPlugin_Analytics_DB', 'create_table' ));

// Migration: Move storage from plugin dir to wp-content/uploads
function myplugin_migrate_storage_to_uploads() {
    global $wpdb;
    
    $old_storage = MYPLUGIN_API_PATH . 'storage/';
    $new_storage = MYPLUGIN_API_STORAGE;
    
    if ( ! is_dir( $old_storage ) || ! is_dir( $new_storage ) ) {
        return false;
    }
    
    // 1. Copy files to new location
    $files = scandir( $old_storage );
    foreach ( $files as $file ) {
        if ( $file !== '.' && $file !== '..' && pathinfo( $file, PATHINFO_EXTENSION ) === 'zip' ) {
            $old_path = $old_storage . $file;
            $new_path = $new_storage . $file;
            if ( ! file_exists( $new_path ) ) {
                copy( $old_path, $new_path );
            }
        }
    }
    
    // 2. Update database paths
    $table = $wpdb->prefix . 'myplugin_versions';
    $versions = $wpdb->get_results( "SELECT id, download_path FROM {$table}" );
    
    foreach ( $versions as $version ) {
        if ( strpos( $version->download_path, $old_storage ) === 0 ) {
            $new_path = str_replace( $old_storage, $new_storage, $version->download_path );
            if ( file_exists( $new_path ) ) {
                $wpdb->update(
                    $table,
                    array( 'download_path' => $new_path ),
                    array( 'id' => $version->id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
        }
    }
    
    return true;
}

// Check and update table structure and migrate storage on admin init
add_action('admin_init', function() {
    $db_version = get_option('myplugin_api_db_version', '1.0.5');
    
    if (version_compare($db_version, '1.0.6', '<')) {
        MyPlugin_Version_DB::create_table();
        update_option('myplugin_api_db_version', '1.0.6');
    }
    
    if (version_compare($db_version, '1.0.7', '<')) {
        // Migrate storage from plugin dir to wp-content/uploads
        if (function_exists('myplugin_migrate_storage_to_uploads')) {
            myplugin_migrate_storage_to_uploads();
        }
        update_option('myplugin_api_db_version', '1.0.7');
    }
});
register_deactivation_hook(__FILE__, array( 'MyPlugin_Version_DB', 'drop_table' ));
register_deactivation_hook(__FILE__, array( 'MyPlugin_Users_DB', 'drop_table' ));
register_deactivation_hook(__FILE__, array( 'MyPlugin_Analytics_DB', 'drop_table' ));

add_action('rest_api_init', array( 'MyPlugin_Routes', 'register_routes' ));
add_action('admin_menu', array( 'MyPlugin_Admin', 'add_menu' ));
add_action('admin_menu', array( 'MyPlugin_Users_Admin', 'add_menu' ));
add_action('admin_menu', array( 'MyPlugin_Analytics_Admin', 'add_menu' ));
add_action('admin_enqueue_scripts', array( 'MyPlugin_Admin', 'enqueue_assets' ));
add_action('admin_init', array( 'MyPlugin_Admin', 'register_actions' ));
add_action('admin_init', array( 'MyPlugin_Users_Admin', 'register_actions' ));
add_action('wp_ajax_myplugin_check_version', array( 'MyPlugin_Admin', 'ajax_check_version' ));
