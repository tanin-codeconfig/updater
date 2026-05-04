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
            define('CODECONFIG_PATH', plugin_dir_path(__FILE__));
        }
        if (! defined('CODECONFIG_URL')) {
            define('CODECONFIG_URL', plugin_dir_url(__FILE__));
        }
        if (! defined('CODECONFIG_BASENAME')) {
            define('CODECONFIG_BASENAME', plugin_basename(__FILE__));
        }
    }

    private function init_hooks()
    {
        add_action('init', array($this, 'load_textdomain'));
        add_filter('plugin_action_links_' . CODECONFIG_BASENAME, array($this, 'add_plugin_links'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    private function load_classes()
    {
        require_once CODECONFIG_PATH . 'includes/config.php';
        require_once CODECONFIG_PATH . 'includes/freemius.php';

        if (! codeconfig_is_pro()) {
            require_once CODECONFIG_PATH . 'includes/updater/index.php';
            require_once CODECONFIG_PATH . 'includes/class-ajax.php';

            ccupd()->init();
            CodeConfig_Ajax::init();
        }

        require_once CODECONFIG_PATH . 'includes/class-admin.php';
        CodeConfig_Admin::init();
    }

    public function load_textdomain()
    {
        load_plugin_textdomain('codeconfig-plugin', false, dirname(CODECONFIG_BASENAME) . '/languages/');
    }

    public function add_plugin_links($links)
    {
        if (! codeconfig_is_pro()) {
            $links['check_update'] = sprintf(
                '<a href="#" id="codeconfig-plugin-row-check" style="color:#d63638;">%s</a>',
                esc_html__('Check for Updates', 'codeconfig-plugin')
            );
        }
        return $links;
    }

    public function activate()
    {
        if (! codeconfig_is_pro()) {
            ccupd()->activate();
        }
    }

    public function deactivate()
    {
        if (! codeconfig_is_pro()) {
            ccupd()->deactivate();
        }
    }
}

function CodeConfig()
{
    return CodeConfig::instance();
}

CodeConfig()->init();