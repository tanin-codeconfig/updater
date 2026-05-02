<?php

/**
 * Plugin Name: MyPlugin API
 * Description: Custom update API server for MyPlugin (Free + Pro)
 * Version: 1.0.3
 * Author: Your Name
 * Text Domain: myplugin-api
 */

if (! defined('ABSPATH')) {
    exit;
}

define('MYPLUGIN_API_VERSION', '1.0.5');
define('MYPLUGIN_API_PATH', plugin_dir_path(__FILE__));
define('MYPLUGIN_API_URL', plugin_dir_url(__FILE__));
define('MYPLUGIN_API_STORAGE', MYPLUGIN_API_PATH . 'storage/');

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
