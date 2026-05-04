<?php

if (! defined('ABSPATH')) {
    exit;
}

if (! function_exists('ccupd_fs')) {

    function ccupd_fs()
    {
        global $ccupd_fs;

        if (isset($ccupd_fs) && $ccupd_fs instanceof CodeConfig_Updater_Manager) {
            return $ccupd_fs;
        }

        require_once __DIR__ . '/class-updater.php';

        $ccupd_fs = new CodeConfig_Updater_Manager();

        return $ccupd_fs;
    }

    ccupd_fs();
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