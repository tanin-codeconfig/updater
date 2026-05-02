<?php

/**
 * Plugin Name: My Plugin
 * Description: Your awesome plugin with custom update system
 * Version: 1.0.15
 * Author: Your Name
 * Text Domain: my-plugin
 */

if (! defined('ABSPATH')) {
    exit;
}

define('MY_PLUGIN_VERSION', '1.0.15');
define('MY_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('MY_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once MY_PLUGIN_PATH . 'config.php';
require_once MY_PLUGIN_PATH . 'includes/class-updater.php';
require_once MY_PLUGIN_PATH . 'includes/class-ajax.php';
require_once MY_PLUGIN_PATH . 'includes/class-admin.php';
require_once MY_PLUGIN_PATH . 'includes/freemius.php';

if (! my_plugin_is_pro()) {
    MyPlugin_Updater::init();
    MyPlugin_Ajax::init();
}

MyPlugin_Admin::init();

add_filter('plugin_action_links_' . MY_PLUGIN_BASENAME, array( 'MyPlugin_Admin', 'add_plugin_action_links' ));

// Register activation and deactivation hooks for cron
register_activation_hook(__FILE__, array( 'MyPlugin_Updater', 'activate' ));
register_deactivation_hook(__FILE__, array( 'MyPlugin_Updater', 'deactivate' ));
