<?php

if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_Config
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
            self::$config = apply_filters('codeconfig_config', array(
                'api_url'   => 'http://localhost:10078/wp-json/codeconfig/v1',
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
        return get_option('codeconfig_' . $option_name, $default);
    }

    public static function update_option($option_name, $value)
    {
        return update_option('codeconfig_' . $option_name, $value);
    }

    public static function delete_option($option_name)
    {
        return delete_option('codeconfig_' . $option_name);
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
        return defined('CODECONFIG_VERSION') ? CODECONFIG_VERSION : '1.0.0';
    }
}

function codeconfig_config($key = null, $default = '')
{
    if ($key === null) {
        return CodeConfig_Config::get('api_url');
    }
    return CodeConfig_Config::get($key, $default);
}

function codeconfig_get_api_key()
{
    return CodeConfig_Config::get_api_key();
}

function codeconfig_update_api_key($key)
{
    return CodeConfig_Config::set_api_key($key);
}

function codeconfig_get_name()
{
    return CodeConfig_Config::get_name();
}

function codeconfig_get_email()
{
    return CodeConfig_Config::get_email();
}