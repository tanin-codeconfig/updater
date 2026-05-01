<?php

if (! defined('ABSPATH')) {
    exit;
}

class MyPlugin_Updater
{
    public static function init()
    {
        add_filter('pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_update' ));
        add_filter('plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3);
        add_action('upgrader_process_complete', array( __CLASS__, 'on_update_complete' ), 10, 2);
        add_filter('http_request_args', array( __CLASS__, 'allow_api_host' ), 10, 2);
    }

    public static function allow_api_host($args, $url)
    {
        $parsed = parse_url(MY_PLUGIN_API_URL);
        $api_host = isset($parsed['host']) ? $parsed['host'] : '';
        $url_host = parse_url($url, PHP_URL_HOST);

        if ($url_host === $api_host) {
            $args['reject_unsafe_urls'] = false;
            $args['sslverify'] = false;
        }
        return $args;
    }

    public static function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        if (my_plugin_is_pro()) {
            return $transient;
        }

        $installed_version = isset($transient->checked[ MY_PLUGIN_BASENAME ])
            ? $transient->checked[ MY_PLUGIN_BASENAME ]
            : MY_PLUGIN_VERSION;

        $data = MyPlugin_Ajax::check_api_for_version($installed_version);

        if (is_wp_error($data) || ! $data['update']) {
            return $transient;
        }

        $transient->response[ MY_PLUGIN_BASENAME ] = (object) array(
            'slug'         => MY_PLUGIN_SLUG,
            'plugin'       => MY_PLUGIN_BASENAME,
            'new_version'  => $data['new_version'],
            'package'      => $data['package'],
            'url'          => $data['changelog'] ?? '',
            'tested'       => get_bloginfo('version'),
            'requires'     => '',
            'requires_php' => '',
            'icons'        => array(),
            'banners'      => array(),
        );

        return $transient;
    }

    public static function plugin_info($false, $action, $args)
    {
        if ($action !== 'plugin_information' || $args->slug !== MY_PLUGIN_SLUG) {
            return $false;
        }

        $data = MyPlugin_Ajax::check_api_for_version('0.0.0');

        if (is_wp_error($data) || ! $data['success']) {
            return $false;
        }

        $plugin_info = new stdClass();
        $plugin_info->name           = 'My Plugin';
        $plugin_info->slug           = MY_PLUGIN_SLUG;
        $plugin_info->version        = $data['new_version'];
        $plugin_info->author         = '<a href="#">Your Name</a>';
        $plugin_info->homepage       = home_url();
        $plugin_info->download_link  = $data['package'];
        $plugin_info->package        = $data['package'];
        $plugin_info->tested         = get_bloginfo('version');
        $plugin_info->requires       = '';
        $plugin_info->requires_php   = '';
        $plugin_info->last_updated   = gmdate('Y-m-d H:i:s');
        $plugin_info->sections       = array(
            'description' => 'A custom plugin with an update system.',
            'changelog'   => wpautop(esc_html($data['changelog'] ?? 'No changelog available.')),
        );
        $plugin_info->banners        = array();
        $plugin_info->banners_rtl    = array();
        $plugin_info->icons          = array();
        $plugin_info->contributors   = array();
        $plugin_info->compatibility  = new stdClass();
        $plugin_info->downloaded     = 0;
        $plugin_info->rating         = 0;
        $plugin_info->num_ratings    = 0;

        return $plugin_info;
    }

    public static function on_update_complete($upgrader, $hook_extra)
    {
        if (empty($hook_extra['plugin']) || $hook_extra['plugin'] !== MY_PLUGIN_BASENAME) {
            return;
        }

        delete_site_transient('update_plugins');
        delete_option('my_plugin_check_result');
        delete_option('my_plugin_api_status');
        delete_option('my_plugin_last_check');
    }
}
