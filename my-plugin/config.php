<?php

if (! defined('ABSPATH')) {
    exit;
}

define('MY_PLUGIN_API_URL', 'http://localhost:10003/wp-json/myplugin/v1');
define('MY_PLUGIN_SLUG', 'my-plugin');
define('MY_PLUGIN_BASENAME', 'my-plugin/my-plugin.php');

function my_plugin_get_api_key()
{
    return get_option('my_plugin_api_key', '');
}

function my_plugin_update_api_key($key)
{
    update_option('my_plugin_api_key', sanitize_text_field($key));
}
