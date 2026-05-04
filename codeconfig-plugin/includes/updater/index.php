<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('ccupd')) {

    function ccupd()
    {
        global $ccupd;

        if (isset($ccupd) && $ccupd instanceof CodeConfig_Updater_Manager) {
            return $ccupd;
        }

        require_once __DIR__ . '/class-updater.php';

        $ccupd = new CodeConfig_Updater_Manager();

        return $ccupd;
    }

    ccupd();
    do_action('ccupd_loaded');
}

class CodeConfig_Updater_Manager
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
        CodeConfig_Updater::init();
    }

    public function activate()
    {
        CodeConfig_Updater::activate();
    }

    public function deactivate()
    {
        CodeConfig_Updater::deactivate();
    }
}