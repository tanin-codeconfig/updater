<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('ccupd')) {

    function ccupd($config = [])
    {
        global $ccupd_instances;

        if (! isset($config['slug']) || empty($config['slug'])) {
            trigger_error('ccupd(): slug is required for multi-plugin support', E_USER_WARNING);
            return null;
        }

        $slug = sanitize_text_field($config['slug']);

        if (! isset($ccupd_instances)) {
            $ccupd_instances = [];
        }

        $is_new = ! isset($ccupd_instances[$slug]);

        if ($is_new) {
            require_once __DIR__ . '/class-updater.php';
            require_once __DIR__ . '/class-rest.php';
            require_once __DIR__ . '/class-admin.php';

            $ccupd_instances[$slug] = new CodeConfig_Updater_Manager($config);
        } else {
            if (! empty($config)) {
                $ccupd_instances[$slug]->update_config($config);
            }
        }

        if ($is_new) {
            add_action('plugins_loaded', [$ccupd_instances[$slug], 'init']);
            add_action('activate_plugin', [$ccupd_instances[$slug], 'on_activate']);
            add_action('deactivate_plugin', [$ccupd_instances[$slug], 'on_deactivate']);
        }

        return $ccupd_instances[$slug];
    }
}

if (! function_exists('ccupd_config')) {

    function ccupd_config($key = '', $default = '')
    {
        $current_instance = CodeConfig_Updater_Manager::get_current_instance();
        if ($current_instance) {
            return $current_instance->get_config($key, $default);
        }

        return $default;
    }
}

if (! function_exists('ccupd_get_manager')) {

    function ccupd_get_manager($slug = '')
    {
        global $ccupd_instances;

        if (empty($slug)) {
            return CodeConfig_Updater_Manager::get_current_instance();
        }

        if (isset($ccupd_instances, $ccupd_instances[$slug])) {
            return $ccupd_instances[$slug];
        }

        return null;
    }
}

class CodeConfig_Updater_Manager
{
    private static $instances = [];
    private $config = [];
    private $slug = '';
    private static $current_instance = null;

    public function __construct($config = [])
    {
        $this->config = wp_parse_args($config, [
            'api_url' => '',
            'slug' => '',
            'basename' => '',
            'version' => '1.0.0',
            'name' => 'CodeConfig Plugin',
            'show_admin_page' => false,
            'is_pro' => false,
            'menu' => [],
        ]);

        $this->slug = $this->config['slug'];

        if (! empty($this->slug)) {
            self::$instances[$this->slug] = $this;
        }

        self::$current_instance = $this;
    }

    public static function get_instance($slug)
    {
        if (isset(self::$instances[$slug])) {
            return self::$instances[$slug];
        }

        return null;
    }

    public static function get_current_instance()
    {
        return self::$current_instance;
    }

    public static function get_all_instances()
    {
        return self::$instances;
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

    public function get_slug()
    {
        return $this->slug;
    }

    public function init()
    {
        if ($this->is_pro()) {
            return;
        }

        $this->updater = new CodeConfig_Updater($this);
        $this->rest = new CodeConfig_REST($this);
        $this->admin = new CodeConfig_Updater_Admin($this);

        $this->updater->init();
        $this->rest->init();
        $this->admin->init();

        do_action('ccupd_loaded', $this);
    }

    public function on_activate($plugin)
    {
        if ($this->is_pro()) {
            return;
        }

        $basename = $this->get_config('basename', '');
        if ($plugin === $basename) {
            if (! $this->updater) {
                $this->init();
            }
            if ($this->updater) {
                $this->updater->activate();
            }
        }
    }

    public function on_deactivate($plugin)
    {
        if ($this->is_pro()) {
            return;
        }

        $basename = $this->get_config('basename', '');
        if ($plugin === $basename) {
            if (! $this->updater) {
                $this->init();
            }
            if ($this->updater) {
                $this->updater->deactivate();
            }
        }
    }

    public function activate()
    {
        if ($this->is_pro()) {
            return;
        }
        $this->updater->activate();
    }

    public function deactivate()
    {
        if ($this->is_pro()) {
            return;
        }
        $this->updater->deactivate();
    }

    private function is_pro()
    {
        return $this->get_config('is_pro', false);
    }

    public function get_basename()
    {
        return $this->get_config('basename', '');
    }

    public function get_version()
    {
        return $this->get_config('version', '1.0.0');
    }

    public function get_updater()
    {
        return $this->updater;
    }

    private $updater = null;
    private $rest = null;
    private $admin = null;
}