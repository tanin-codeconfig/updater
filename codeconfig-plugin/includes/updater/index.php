<?php

if (! defined('ABSPATH')) {
    exit;
}

// Define local constants if not defined by main plugin (for isolation)
if (! defined('CODECONFIG_BASENAME')) {
    define('CODECONFIG_BASENAME', 'codeconfig-plugin/codeconfig-plugin.php');
}
if (! defined('CODECONFIG_VERSION')) {
    define('CODECONFIG_VERSION', '1.0.20');
}
if (! defined('CODECONFIG_PRO_ACTIVE')) {
    define('CODECONFIG_PRO_ACTIVE', false);
}

if (! function_exists('ccupd')) {

    function ccupd($config = array())
    {
        global $ccupd;

        if (isset($ccupd) && $ccupd instanceof CodeConfig_Updater_Manager) {
            if (! empty($config)) {
                $ccupd->update_config($config);
            }
            return $ccupd;
        }

        if (! class_exists('CodeConfig_Updater')) {
            require_once __DIR__ . '/class-updater.php';
        }
        if (! class_exists('CodeConfig_REST')) {
            require_once __DIR__ . '/class-rest.php';
        }
        if (! class_exists('CodeConfig_Updater_Admin')) {
            require_once __DIR__ . '/class-admin.php';
        }

        $ccupd = new CodeConfig_Updater_Manager($config);

        add_action('plugins_loaded', array($ccupd, 'init'));
        add_action('activate_plugin', array($ccupd, 'on_activate'));
        add_action('deactivate_plugin', array($ccupd, 'on_deactivate'));

        return $ccupd;
    }
}

if (! function_exists('ccupd_config')) {

    function ccupd_config($key = '', $default = '')
    {
        $manager = CodeConfig_Updater_Manager::instance();
        if ($manager) {
            return $manager->get_config($key, $default);
        }
        return $default;
    }
}

class CodeConfig_Updater_Manager
{
    private static $instance = null;
    private $config = array();

    public function __construct($config = array())
    {
        $this->config = wp_parse_args($config, array(
            'api_url'         => '',
            'slug'            => '',
            'basename'        => '',
            'version'         => '1.0.0',
            'name'            => 'CodeConfig Plugin',
            'show_admin_page' => false,
        ));

        self::$instance = $this;
    }

    public static function instance()
    {
        return self::$instance;
    }

    public function update_config($config)
    {
        $this->config = wp_parse_args($config, $this->config);
    }

    public function get_config($key = '', $default = '')
    {
        if (empty($key)) {
            return $this->config;
        }
        return isset($this->config[$key]) ? $this->config[$key] : $default;
    }

    public function init()
    {
        global $ccupd_config;
        $ccupd_config = $this->config;

        if ($this->is_pro()) {
            return;
        }

        CodeConfig_Updater::init();
        CodeConfig_REST::init();
        CodeConfig_Updater_Admin::init();

        do_action('ccupd_loaded');
    }

    public function on_activate($plugin)
    {
        if ($this->is_pro()) {
            return;
        }

        $basename = $this->get_config('basename', '');
        if ($plugin === $basename) {
            CodeConfig_Updater::activate();
        }
    }

    public function on_deactivate($plugin)
    {
        if ($this->is_pro()) {
            return;
        }

        $basename = $this->get_config('basename', '');
        if ($plugin === $basename) {
            CodeConfig_Updater::deactivate();
        }
    }

    public function activate()
    {
        if ($this->is_pro()) {
            return;
        }
        CodeConfig_Updater::activate();
    }

    public function deactivate()
    {
        if ($this->is_pro()) {
            return;
        }
        CodeConfig_Updater::deactivate();
    }

    private function is_pro()
    {
        return defined('CODECONFIG_PRO_ACTIVE') && CODECONFIG_PRO_ACTIVE;
    }
}