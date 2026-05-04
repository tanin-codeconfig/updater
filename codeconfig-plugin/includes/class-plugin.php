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
            define('CODECONFIG_URL', plugin_dir_url(__DIR__ . '/..'));
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
        $log_file = WP_CONTENT_DIR . '/codeconfig-debug.log';
        $log = "=== CodeConfig Plugin Loading ===\n";
        $log .= 'CODECONFIG_PATH: ' . CODECONFIG_PATH . "\n";
        $log .= 'CODECONFIG_URL: ' . CODECONFIG_URL . "\n";
        $log .= 'CODECONFIG_BASENAME: ' . CODECONFIG_BASENAME . "\n";
        
        if (file_exists(CODECONFIG_PATH . 'freemius.php')) {
            require_once CODECONFIG_PATH . 'freemius.php';
            $log .= "freemius.php loaded\n";
        }

        if (! function_exists('codeconfig_is_pro')) {
            function codeconfig_is_pro()
            {
                return defined('CODECONFIG_PRO_ACTIVE') && CODECONFIG_PRO_ACTIVE;
            }
        }

        if (! codeconfig_is_pro()) {
            $updater_path = CODECONFIG_PATH . 'updater/index.php';
            $log .= "Looking for updater at: $updater_path\n";
            $log .= "File exists: " . (file_exists($updater_path) ? 'YES' : 'NO') . "\n";

            if (file_exists($updater_path)) {
                require_once $updater_path;
                $log .= "updater/index.php loaded\n";
            }

            if (function_exists('ccupd')) {
                $log .= "ccupd() called\n";
                ccupd(array(
                    'api_url'   => 'http://localhost:10078/wp-json/codeconfig/v1',
                    'slug'      => 'codeconfig-plugin',
                    'basename'  => 'codeconfig-plugin/codeconfig-plugin.php',
                    'version'   => '1.0.20',
                    'name'      => 'CodeConfig Plugin',
                    'show_admin_page' => true,
                ));
                $log .= "ccupd() done\n";
            } else {
                $log .= "ERROR: ccupd() function not found!\n";
            }
        } else {
            $log .= "Is PRO mode, skipping updater\n";
        }
        
        file_put_contents($log_file, $log);
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
