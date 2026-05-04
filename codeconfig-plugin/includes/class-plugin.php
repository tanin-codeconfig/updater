<?php

if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig
{
    private static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init()
    {
        $this->define_constants();
        $this->init_hooks();
        $this->load_classes();
    }

    private function define_constants()
    {
        if (! defined('CODECONFIG_VERSION')) {
            define('CODECONFIG_VERSION', '1.0.20');
        }
        if (! defined('CODECONFIG_PATH')) {
            define('CODECONFIG_PATH', plugin_dir_path(__DIR__));
        }
        if (! defined('CODECONFIG_URL')) {
            $upload_dir = wp_upload_dir();
            define('CODECONFIG_URL', $upload_dir['baseurl'] . '/codeconfig-plugin/');
        }
        if (! defined('CODECONFIG_BASENAME')) {
            define('CODECONFIG_BASENAME', 'codeconfig-plugin/codeconfig-plugin.php');
        }
    }

    private function init_hooks()
    {
        add_action('init', array($this, 'load_textdomain'));
    }

    private function load_classes()
    {
        if (file_exists(CODECONFIG_PATH . 'includes/freemius.php')) {
            require_once CODECONFIG_PATH . 'includes/freemius.php';
        }

        if (! function_exists('codeconfig_is_pro')) {
            function codeconfig_is_pro()
            {
                return defined('CODECONFIG_PRO_ACTIVE') && CODECONFIG_PRO_ACTIVE;
            }
        }

        if (! codeconfig_is_pro()) {
            if (file_exists(CODECONFIG_PATH . 'includes/updater/index.php')) {
                require_once CODECONFIG_PATH . 'includes/updater/index.php';
            }

            if (function_exists('ccupd')) {
                ccupd(array(
                    'api_url'         => 'http://localhost:10078/wp-json/codeconfig/v1',
                    'slug'            => 'codeconfig-plugin',
                    'basename'        => 'codeconfig-plugin/codeconfig-plugin.php',
                    'version'         => '1.0.20',
                    'name'            => 'CodeConfig Plugin',
                    'show_admin_page' => true,
                ));
            }
        }
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('codeconfig-plugin', false, dirname(CODECONFIG_BASENAME) . '/languages/');
    }
}

function CodeConfig()
{
    return CodeConfig::instance();
}

CodeConfig()->init();
