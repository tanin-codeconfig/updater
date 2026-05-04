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
require_once MYPLUGIN_API_PATH . 'includes/class-settings.php';

register_activation_hook(__FILE__, array( 'MyPlugin_Version_DB', 'create_table' ));
register_activation_hook(__FILE__, array( 'MyPlugin_Users_DB', 'create_table' ));
register_activation_hook(__FILE__, array( 'MyPlugin_Analytics_DB', 'create_table' ));


// Check and update table structure and migrate storage on admin init
add_action('admin_init', function () {
    $db_version = get_option('myplugin_api_db_version', '1.0.5');

    if (version_compare($db_version, '1.0.6', '<')) {
        MyPlugin_Version_DB::create_table();
        update_option('myplugin_api_db_version', '1.0.6');
    }
});
register_deactivation_hook(__FILE__, array( 'MyPlugin_Version_DB', 'drop_table' ));
register_deactivation_hook(__FILE__, array( 'MyPlugin_Users_DB', 'drop_table' ));
register_deactivation_hook(__FILE__, array( 'MyPlugin_Analytics_DB', 'drop_table' ));

add_action('rest_api_init', array( 'MyPlugin_Routes', 'register_routes' ));
add_action('admin_menu', array( 'MyPlugin_Admin', 'add_menu' ));
add_action('admin_menu', array( 'MyPlugin_Users_Admin', 'add_menu' ));
add_action('admin_menu', array( 'MyPlugin_Analytics_Admin', 'add_menu' ));
add_action('admin_menu', array( 'MyPlugin_Settings', 'add_menu' ));
add_action('admin_enqueue_scripts', array( 'MyPlugin_Admin', 'enqueue_assets' ));
add_action('admin_init', array( 'MyPlugin_Admin', 'register_actions' ));
add_action('admin_init', array( 'MyPlugin_Users_Admin', 'register_actions' ));
add_action('admin_init', array( 'MyPlugin_Settings', 'register_settings' ));
add_action('wp_ajax_myplugin_check_version', array( 'MyPlugin_Admin', 'ajax_check_version' ));

// Headless Mode - block frontend access
add_action('init', 'myplugin_headless_mode');
function myplugin_headless_mode() {
    $settings = MyPlugin_Settings::get_settings();

    if (empty($settings['headless_enabled'])) {
        return;
    }

    if (is_admin() || current_user_can('manage_options')) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $request_uri = parse_url($request_uri, PHP_URL_PATH);

    $allowed_routes = ! empty($settings['headless_allowed_routes']) 
        ? explode(',', $settings['headless_allowed_routes']) 
        : array('/wp-json/', '/wp-admin/', '/xmlrpc.php');

    $allowed_routes = array_map('trim', $allowed_routes);

    foreach ($allowed_routes as $route) {
        if (! empty($route) && strpos($request_uri, $route) === 0) {
            return;
        }
    }

    if (! empty($settings['headless_keep_feeds'])) {
        $feed_paths = array('/feed/', '/rss/', '/atom/', '/rdf/');
        foreach ($feed_paths as $feed) {
            if (strpos($request_uri, $feed) !== false) {
                return;
            }
        }
    }

    $behavior = $settings['headless_behavior'];

    if ('404' === $behavior) {
        header('HTTP/1.1 404 Not Found');
        echo '<!DOCTYPE html>
<html>
<head><title>404 Not Found</title></head>
<body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
</body>
</html>';
        exit;
    } elseif ('message' === $behavior) {
        $message = ! empty($settings['headless_message']) 
            ? $settings['headless_message'] 
            : 'This site is running in headless mode.';
        header('HTTP/1.1 200 OK');
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html>
<html>
<head><title>Headless Mode</title></head>
<body>
<h1>Headless Mode</h1>
<p>' . esc_html($message) . '</p>
</body>
</html>';
        exit;
    } elseif ('redirect' === $behavior) {
        $api_docs = get_site_url(null, '/wp-json/myplugin/v1');
        wp_redirect($api_docs, 302);
        exit;
    } elseif ('custom_url' === $behavior) {
        $custom_url = ! empty($settings['headless_redirect_url']) ? $settings['headless_redirect_url'] : get_site_url(null, '/wp-json/myplugin/v1');
        wp_redirect($custom_url, 302);
        exit;
    }
}
