<?php

if (! defined('ABSPATH')) {
    exit;
}

class MyPlugin_Config
{
    private static $config = array();

    public static function get($key, $default = '')
    {
        self::load_config();
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
    }

    public static function set($key, $value)
    {
        self::$config[$key] = $value;
    }

    private static function load_config()
    {
        if (empty(self::$config)) {
            self::$config = apply_filters('my_plugin_config', array(
                'api_url'   => 'http://localhost:10078/wp-json/myplugin/v1',
                'slug'      => 'my-plugin',
                'basename'  => 'my-plugin/my-plugin.php',
                'version'   => '1.0.20',
                'name'      => 'My Plugin',
            ));
        }
    }

    public static function get_api_url()
    {
        return self::get('api_url');
    }

    public static function get_slug()
    {
        return self::get('slug');
    }

    public static function get_basename()
    {
        return self::get('basename');
    }

    public static function get_option($option_name, $default = '')
    {
        return get_option('my_plugin_' . $option_name, $default);
    }

    public static function update_option($option_name, $value)
    {
        return update_option('my_plugin_' . $option_name, $value);
    }

    public static function delete_option($option_name)
    {
        return delete_option('my_plugin_' . $option_name);
    }

    public static function get_api_key()
    {
        return self::get_option('api_key', '');
    }

    public static function set_api_key($key)
    {
        return self::update_option('api_key', sanitize_text_field($key));
    }

    public static function get_name()
    {
        return self::get_option('name', '');
    }

    public static function set_name($name)
    {
        return self::update_option('name', sanitize_text_field($name));
    }

    public static function get_email()
    {
        return self::get_option('email', '');
    }

    public static function set_email($email)
    {
        return self::update_option('email', sanitize_email($email));
    }

    public static function get_version()
    {
        return defined('MY_PLUGIN_VERSION') ? MY_PLUGIN_VERSION : '1.0.0';
    }
}

function my_plugin_config($key = null, $default = '')
{
    if ($key === null) {
        return MyPlugin_Config::get('api_url');
    }
    return MyPlugin_Config::get($key, $default);
}

function my_plugin_get_api_key()
{
    return MyPlugin_Config::get_api_key();
}

function my_plugin_update_api_key($key)
{
    return MyPlugin_Config::set_api_key($key);
}

function my_plugin_get_name()
{
    return MyPlugin_Config::get_name();
}

function my_plugin_get_email()
{
    return MyPlugin_Config::get_email();
}