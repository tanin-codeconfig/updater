<?php

if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_Updater
{
    public static function init()
    {
        // Remove automatic update check on page reload
        // Updates now only happen via cron or manual check
        add_filter('plugins_api', [ __CLASS__, 'plugin_info' ], 20, 3);
        add_action('upgrader_process_complete', [ __CLASS__, 'on_update_complete' ], 10, 2);
        add_filter('http_request_args', [ __CLASS__, 'allow_api_host' ], 10, 2);

        // Register cron schedule and event
        add_filter('cron_schedules', [ __CLASS__, 'add_cron_schedule' ]);
        add_action('codeconfig_cron_update_check', [ __CLASS__, 'cron_check_for_update' ]);
    }

    public static function activate()
    {
        // Schedule the cron event (4 times daily = every 6 hours)
        if (! wp_next_scheduled('codeconfig_cron_update_check')) {
            wp_schedule_event(time(), 'six_hours', 'codeconfig_cron_update_check');
        }
    }

    public static function deactivate()
    {
        // Clear the cron event
        wp_clear_scheduled_hook('codeconfig_cron_update_check');
    }

    public static function add_cron_schedule($schedules)
    {
        $schedules['six_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display'  => 'Every 6 Hours (4 times daily)',
        ];

        return $schedules;
    }

    public static function cron_check_for_update()
    {
        self::force_check_for_update();
    }

    public static function force_check_for_update()
    {
        global $ccupd_config;

        if (ccupd_config('is_pro', false)) {
            return;
        }

        $installed_version = isset($ccupd_config['version']) ? $ccupd_config['version'] : '1.0.0';
        $data              = CodeConfig_REST::check_api_for_version($installed_version);

        // Save result to options for AJAX handler
        if (! is_wp_error($data)) {
            update_option('codeconfig_check_result', $data);
            update_option('codeconfig_last_check', time());
            update_option('codeconfig_api_status', $data['success'] ? 'connected' : 'error');
        } else {
            update_option('codeconfig_api_status', 'error');
        }

        $basename = isset($ccupd_config['basename']) ? $ccupd_config['basename'] : '';
        $slug     = isset($ccupd_config['slug']) ? $ccupd_config['slug'] : '';

        if (is_wp_error($data) || ! $data['update']) {
            $transient = get_site_transient('update_plugins');
            if (is_object($transient) && isset($transient->response[ $basename ])) {
                unset($transient->response[ $basename ]);
                set_site_transient('update_plugins', $transient);
            }

            return;
        }

        $transient = get_site_transient('update_plugins');
        if (! is_object($transient)) {
            $transient = new stdClass();
        }

        $transient->response[ $basename ] = (object) [
            'slug'         => $slug,
            'plugin'       => ccupd_config('basename', ''),
            'new_version'  => $data['new_version'],
            'package'      => $data['package'],
            'url'          => $data['changelog'] ?? '',
            'tested'       => get_bloginfo('version'),
            'requires'     => '',
            'requires_php' => '',
            'icons'        => [],
            'banners'      => [],
        ];

        set_site_transient('update_plugins', $transient);
    }

    public static function allow_api_host($args, $url)
    {
        $parsed   = parse_url(ccupd_config('api_url'));
        $api_host = isset($parsed['host']) ? $parsed['host'] : '';
        $url_host = parse_url($url, PHP_URL_HOST);

        if ($url_host === $api_host) {
            $args['reject_unsafe_urls'] = false;
            $args['sslverify']          = false;
        }

        return $args;
    }

    public static function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        if (ccupd_config('is_pro', false)) {
            return $transient;
        }

        $basename = ccupd_config('basename');
        $slug     = ccupd_config('slug');
        $version  = ccupd_config('version');

        $installed_version = isset($transient->checked[ $basename ])
            ? $transient->checked[ $basename ]
            : $version;

        $data = CodeConfig_REST::check_api_for_version($installed_version);

        if (is_wp_error($data) || ! $data['update']) {
            return $transient;
        }

        $transient->response[ $basename ] = (object) [
            'slug'         => $slug,
            'plugin'       => ccupd_config('basename', ''),
            'new_version'  => $data['new_version'],
            'package'      => $data['package'],
            'url'          => $data['changelog'] ?? '',
            'tested'       => get_bloginfo('version'),
            'requires'     => '',
            'requires_php' => '',
            'icons'        => [],
            'banners'      => [],
        ];

        return $transient;
    }

    public static function plugin_info($false, $action, $args)
    {
        $slug = ccupd_config('slug');

        if ($action !== 'plugin_information' || $args->slug !== $slug) {
            return $false;
        }

        $data = CodeConfig_REST::check_api_for_version('0.0.0');

        if (is_wp_error($data) || ! $data['success']) {
            return $false;
        }

        $plugin_info                 = new stdClass();
        $plugin_info->name           = 'My Plugin';
        $plugin_info->slug           = $slug;
        $plugin_info->version        = $data['new_version'];
        $plugin_info->author         = '<a href="#">Your Name</a>';
        $plugin_info->homepage       = home_url();
        $plugin_info->download_link  = $data['package'];
        $plugin_info->package        = $data['package'];
        $plugin_info->tested         = get_bloginfo('version');
        $plugin_info->requires       = '';
        $plugin_info->requires_php   = '';
        $plugin_info->last_updated   = gmdate('Y-m-d H:i:s');
        $plugin_info->sections       = [
            'description' => 'A custom plugin with an update system.',
            'changelog'   => wpautop(esc_html($data['changelog'] ?? 'No changelog available.')),
        ];
        $plugin_info->banners        = [];
        $plugin_info->banners_rtl    = [];
        $plugin_info->icons          = [];
        $plugin_info->contributors   = [];
        $plugin_info->compatibility  = new stdClass();
        $plugin_info->downloaded     = 0;
        $plugin_info->rating         = 0;
        $plugin_info->num_ratings    = 0;

        return $plugin_info;
    }

    public static function on_update_complete($upgrader, $hook_extra)
    {
        $basename = ccupd_config('basename');

        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $basename) {
            return;
        }

        delete_site_transient('update_plugins');
        delete_option('codeconfig_check_result');
        delete_option('codeconfig_api_status');
        delete_option('codeconfig_last_check');
    }
}
