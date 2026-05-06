<?php

/**
 * Plugin Name: CodeConfig API
 * Description: Custom update API server for CodeConfig plugins
 * Version: 1.0.1
 * Author: CodeConfig
 * Author URI: https://codeconfig.io
 * Text Domain: codeconfig-api
 * License: GPL2+
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('CODECONFIG_API_VERSION')) {
    define('CODECONFIG_API_VERSION', '1.0.1');
}
if (! defined('CODECONFIG_API_PATH')) {
    define('CODECONFIG_API_PATH', plugin_dir_path(__FILE__));
}
if (! defined('CODECONFIG_API_URL')) {
    define('CODECONFIG_API_URL', plugin_dir_url(__FILE__));
}

$codeconfig_upload_dir = wp_upload_dir();
if (! defined('CODECONFIG_API_STORAGE')) {
    define('CODECONFIG_API_STORAGE', $codeconfig_upload_dir['basedir'] . '/codeconfig-api/');
}
if (! defined('CODECONFIG_API_STORAGE_URL')) {
    define('CODECONFIG_API_STORAGE_URL', $codeconfig_upload_dir['baseurl'] . '/codeconfig-api/');
}

require_once CODECONFIG_API_PATH . 'includes/class-version-db.php';
require_once CODECONFIG_API_PATH . 'includes/class-users-db.php';
require_once CODECONFIG_API_PATH . 'includes/class-analytics-db.php';
require_once CODECONFIG_API_PATH . 'includes/class-analytics-admin.php';
require_once CODECONFIG_API_PATH . 'includes/class-routes.php';
require_once CODECONFIG_API_PATH . 'includes/class-update-check.php';
require_once CODECONFIG_API_PATH . 'includes/class-download.php';
require_once CODECONFIG_API_PATH . 'includes/class-zip-parser.php';
require_once CODECONFIG_API_PATH . 'includes/class-admin.php';
require_once CODECONFIG_API_PATH . 'includes/class-users-admin.php';
require_once CODECONFIG_API_PATH . 'includes/class-settings.php';

register_activation_hook(__FILE__, array( 'CodeConfig_Version_DB', 'create_table' ));
register_activation_hook(__FILE__, array( 'CodeConfig_Users_DB', 'create_table' ));
register_activation_hook(__FILE__, array( 'CodeConfig_Analytics_DB', 'create_table' ));


add_action('admin_init', function () {
    $db_version = get_option('codeconfig_api_db_version', '1.0.5');

    if (version_compare($db_version, '1.0.6', '<')) {
        CodeConfig_Version_DB::create_table();
        update_option('codeconfig_api_db_version', '1.0.6');
    }
});
register_deactivation_hook(__FILE__, array( 'CodeConfig_Version_DB', 'drop_table' ));
register_deactivation_hook(__FILE__, array( 'CodeConfig_Users_DB', 'drop_table' ));
register_deactivation_hook(__FILE__, array( 'CodeConfig_Analytics_DB', 'drop_table' ));

add_action('rest_api_init', array( 'CodeConfig_Routes', 'register_routes' ));
add_action('admin_menu', array( 'CodeConfig_Admin', 'add_menu' ));
add_action('admin_menu', array( 'CodeConfig_Users_Admin', 'add_menu' ));
add_action('admin_menu', array( 'CodeConfig_Analytics_Admin', 'add_menu' ));
add_action('admin_menu', array( 'CodeConfig_Settings', 'add_menu' ));
add_action('admin_enqueue_scripts', array( 'CodeConfig_Admin', 'enqueue_assets' ));
add_action('admin_init', array( 'CodeConfig_Admin', 'register_actions' ));
add_action('admin_init', array( 'CodeConfig_Users_Admin', 'register_actions' ));
add_action('admin_init', array( 'CodeConfig_Settings', 'register_settings' ));
add_action('wp_ajax_codeconfig_check_version', array( 'CodeConfig_Admin', 'ajax_check_version' ));

add_action('init', 'codeconfig_headless_mode');
function codeconfig_headless_mode()
{
    $settings = CodeConfig_Settings::get_settings();

    if (empty($settings['headless_enabled'])) {
        return;
    }

    if (is_admin() || current_user_can('manage_options') || wp_doing_ajax() || wp_doing_cron()) {
        return;
    }

    $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $request_uri = parse_url($request_uri, PHP_URL_PATH);

    $allowed_routes = ! empty($settings['headless_allowed_routes'])
        ? explode(',', $settings['headless_allowed_routes'])
        : array('/wp-json/', '/wp-admin/', '/xmlrpc.php', '/wp-login.php', '/wp-cron.php');

    // Always allow CodeConfig API download endpoint
    $allowed_routes[] = '/wp-content/plugins/codeconfig-api/download.php';
    $allowed_routes[] = '/codeconfig-api/download.php';

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
        $api_docs = get_site_url(null, '/wp-json/codeconfig/v1');
        wp_redirect($api_docs, 302);
        exit;
    } elseif ('custom_url' === $behavior) {
        $custom_url = ! empty($settings['headless_redirect_url']) ? $settings['headless_redirect_url'] : get_site_url(null, '/wp-json/codeconfig/v1');
        wp_redirect($custom_url, 302);
        exit;
    }
}
