<?php

if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_Updater
{
    private $manager = null;
    private $slug = '';

    public function __construct($manager)
    {
        $this->manager = $manager;
        $this->slug = $manager->get_slug();
    }

    public function init()
    {
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'on_update_complete'], 10, 2);
        add_filter('http_request_args', [$this, 'allow_api_host'], 10, 2);

        add_filter('cron_schedules', [$this, 'add_cron_schedule']);
        add_action('ccupd_' . $this->slug . '_cron_update_check', [$this, 'cron_check_for_update']);
    }

    public function activate()
    {
        $cron_hook = 'ccupd_' . $this->slug . '_cron_update_check';
        if (! wp_next_scheduled($cron_hook)) {
            wp_schedule_event(time(), 'six_hours', $cron_hook);
        }
    }

    public function deactivate()
    {
        $cron_hook = 'ccupd_' . $this->slug . '_cron_update_check';
        wp_clear_scheduled_hook($cron_hook);
    }

    public function add_cron_schedule($schedules)
    {
        $schedules['six_hours'] = [
            'interval' => 6 * HOUR_IN_SECONDS,
            'display' => 'Every 6 Hours (4 times daily)',
        ];

        return $schedules;
    }

    public function cron_check_for_update()
    {
        $this->force_check_for_update();
    }

    public function force_check_for_update()
    {
        if ($this->manager->get_config('is_pro', false)) {
            return;
        }

        $installed_version = $this->manager->get_config('version', '1.0.0');
        $data = CodeConfig_REST::check_api_for_version($installed_version, $this->manager);

        $option_prefix = 'ccupd_' . $this->slug . '_';
        if (! is_wp_error($data)) {
            update_option($option_prefix . 'check_result', $data);
            update_option($option_prefix . 'last_check', time());
            update_option($option_prefix . 'api_status', $data['success'] ? 'connected' : 'error');
        } else {
            update_option($option_prefix . 'api_status', 'error');
        }

        $basename = $this->manager->get_config('basename', '');
        $slug = $this->manager->get_config('slug', '');

        if (is_wp_error($data) || ! $data['update']) {
            $transient = get_site_transient('update_plugins');
            if (is_object($transient) && isset($transient->response[$basename])) {
                unset($transient->response[$basename]);
                set_site_transient('update_plugins', $transient);
            }

            return;
        }

        $transient = get_site_transient('update_plugins');
        if (! is_object($transient)) {
            $transient = new stdClass();
        }

        $transient->response[$basename] = (object) [
            'slug' => $slug,
            'plugin' => $this->manager->get_config('basename', ''),
            'new_version' => $data['new_version'],
            'package' => $data['package'],
            'url' => $data['changelog'] ?? '',
            'tested' => get_bloginfo('version'),
            'requires' => '',
            'requires_php' => '',
            'icons' => [],
            'banners' => [],
        ];

        set_site_transient('update_plugins', $transient);
    }

    public function allow_api_host($args, $url)
    {
        $parsed = parse_url($this->manager->get_config('api_url'));
        $api_host = isset($parsed['host']) ? $parsed['host'] : '';
        $url_host = parse_url($url, PHP_URL_HOST);

        if ($url_host === $api_host) {
            $args['reject_unsafe_urls'] = false;
            $args['sslverify'] = false;
        }

        return $args;
    }

    public function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        if ($this->manager->get_config('is_pro', false)) {
            return $transient;
        }

        $basename = $this->manager->get_config('basename');
        $slug = $this->manager->get_config('slug');
        $version = $this->manager->get_config('version');

        $installed_version = isset($transient->checked[$basename])
            ? $transient->checked[$basename]
            : $version;

        $data = CodeConfig_REST::check_api_for_version($installed_version, $this->manager);

        if (is_wp_error($data) || ! $data['update']) {
            return $transient;
        }

        $transient->response[$basename] = (object) [
            'slug' => $slug,
            'plugin' => $this->manager->get_config('basename', ''),
            'new_version' => $data['new_version'],
            'package' => $data['package'],
            'url' => $data['changelog'] ?? '',
            'tested' => get_bloginfo('version'),
            'requires' => '',
            'requires_php' => '',
            'icons' => [],
            'banners' => [],
        ];

        return $transient;
    }

    public function plugin_info($false, $action, $args)
    {
        $slug = $this->manager->get_config('slug');

        if ($action !== 'plugin_information' || $args->slug !== $slug) {
            return $false;
        }

        $data = CodeConfig_REST::check_api_for_version('0.0.0', $this->manager);

        if (is_wp_error($data) || ! $data['success']) {
            return $false;
        }

        $plugin_name = $this->manager->get_config('name', 'My Plugin');

        $plugin_info = new stdClass();
        $plugin_info->name = $plugin_name;
        $plugin_info->slug = $slug;
        $plugin_info->version = $data['new_version'];
        $plugin_info->author = '<a href="#">Your Name</a>';
        $plugin_info->homepage = home_url();
        $plugin_info->download_link = $data['package'];
        $plugin_info->package = $data['package'];
        $plugin_info->tested = get_bloginfo('version');
        $plugin_info->requires = '';
        $plugin_info->requires_php = '';
        $plugin_info->last_updated = gmdate('Y-m-d H:i:s');
        $plugin_info->sections = [
            'description' => 'A custom plugin with an update system.',
            'changelog' => wpautop(esc_html($data['changelog'] ?? 'No changelog available.')),
        ];
        $plugin_info->banners = [];
        $plugin_info->banners_rtl = [];
        $plugin_info->icons = [];
        $plugin_info->contributors = [];
        $plugin_info->compatibility = new stdClass();
        $plugin_info->downloaded = 0;
        $plugin_info->rating = 0;
        $plugin_info->num_ratings = 0;

        return $plugin_info;
    }

    public function on_update_complete($upgrader, $hook_extra)
    {
        $basename = $this->manager->get_config('basename');

        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== $basename) {
            return;
        }

        delete_site_transient('update_plugins');

        $option_prefix = 'ccupd_' . $this->slug . '_';
        delete_option($option_prefix . 'check_result');
        delete_option($option_prefix . 'api_status');
        delete_option($option_prefix . 'last_check');
    }
}