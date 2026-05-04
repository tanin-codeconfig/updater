<?php

if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_REST
{
    public static function init()
    {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }

    public static function register_routes()
    {
        register_rest_route('ccupd/v1', '/check', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_check'),
            'permission_callback' => function() {
                return current_user_can('update_plugins');
            },
        ));

        register_rest_route('ccupd/v1', '/test-connection', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_test_connection'),
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ));

        register_rest_route('ccupd/v1', '/force-refresh', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_force_refresh'),
            'permission_callback' => function() {
                return current_user_can('update_plugins');
            },
        ));

        register_rest_route('ccupd/v1', '/update', array(
            'methods'             => 'POST',
            'callback'            => array(__CLASS__, 'handle_update'),
            'permission_callback' => function() {
                return current_user_can('update_plugins');
            },
        ));
    }

    public static function handle_check(WP_REST_Request $request)
    {
        if (ccupd_config('is_pro', false)) {
            return new WP_REST_Response(array(
                'update_available' => false,
                'message'          => 'Updates are managed by Freemius.',
                'is_pro'           => true,
            ), 200);
        }

        self::clear_update_transient();

        CodeConfig_Updater::force_check_for_update();

        $result = get_option('codeconfig_check_result', array());

        if (empty($result)) {
            $result = self::check_api();
        }

        if (is_wp_error($result)) {
            update_option('codeconfig_api_status', 'error');
            return new WP_REST_Response(array(
                'message' => $result->get_error_message(),
            ), 400);
        }

        update_option('codeconfig_api_status', 'connected');
        update_option('codeconfig_last_check', time());
        update_option('codeconfig_check_result', $result);

        return new WP_REST_Response(array(
            'update_available' => ! empty($result['update']),
            'new_version'      => $result['new_version'] ?? '',
            'message'          => ! empty($result['update']) 
                ? sprintf('Update available! Version %s is ready to install.', $result['new_version']) 
                : 'You are running the latest version.',
            'changelog'        => $result['changelog'] ?? '',
        ), 200);
    }

    public static function handle_test_connection(WP_REST_Request $request)
    {
        $api_url = ccupd_config('api_url', '');
        
        if (empty($api_url)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'API URL not configured.',
            ), 400);
        }

        $response = wp_remote_get($api_url . '/update-check', array(
            'timeout'   => 15,
            'sslverify' => false,
        ));

        if (is_wp_error($response)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message(),
            ), 400);
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Connection successful!',
            ), 200);
        }

        return new WP_REST_Response(array(
            'success' => false,
            'message' => 'Connection failed with HTTP ' . $code,
        ), 400);
    }

    public static function handle_force_refresh(WP_REST_Request $request)
    {
        self::clear_update_transient();
        delete_option('codeconfig_last_check');
        delete_option('codeconfig_check_result');
        delete_option('codeconfig_api_status');

        return new WP_REST_Response(array(
            'success' => true,
            'message' => 'Cache cleared. Please run check again.',
        ), 200);
    }

    private static function clear_update_transient()
    {
        delete_site_transient('update_plugins');
    }

    public static function check_api()
    {
        return self::check_api_for_version(ccupd_config('version', '1.0.0'));
    }

    public static function check_api_for_version($version)
    {
        $api_url = ccupd_config('api_url', '') . '/update-check';
        $api_key = get_option('codeconfig_api_key', '');

        $params = array(
            'version' => $version,
            'slug'    => ccupd_config('slug', ''),
            'domain'  => site_url(),
        );

        if ($api_key) {
            $params['api_key'] = $api_key;
        }

        $name  = get_option('codeconfig_name', '');
        $email = get_option('codeconfig_email', '');

        if (! empty($name)) {
            $params['name'] = $name;
        }
        if (! empty($email)) {
            $params['email'] = $email;
        }

        $response = wp_remote_get(add_query_arg($params, $api_url), array(
            'timeout' => 15,
        ));

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                'Could not connect to the update server. ' . $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_Error(
                'api_error',
                sprintf('Server returned HTTP %d.', $code)
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (! $data || ! $data['success']) {
            $message = ! empty($data['message']) ? $data['message'] : 'Invalid response from update server.';
            return new WP_Error(
                'api_error',
                $message
            );
        }

        if (! empty($data['user_created']) && ! empty($data['api_key'])) {
            update_option('codeconfig_api_key', sanitize_text_field($data['api_key']));
        }

        return $data;
    }

    public static function get_last_check()
    {
        $timestamp = get_option('codeconfig_last_check');

        if (! $timestamp) {
            return null;
        }

        return array(
            'time'   => $timestamp,
            'result' => get_option('codeconfig_check_result', array()),
        );
    }

    public static function force_clear_cache()
    {
        delete_option('codeconfig_last_check');
        delete_option('codeconfig_check_result');
        delete_option('codeconfig_api_status');
    }

    public static function handle_update(WP_REST_Request $request)
    {
        if (ccupd_config('is_pro', false)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Updates are managed by Freemius.',
            ), 400);
        }

        $api_data = self::check_api();

        if (is_wp_error($api_data) || empty($api_data['update'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'No update available or API error.',
            ), 400);
        }

        delete_site_transient('update_plugins');

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $basename = ccupd_config('basename');
        $slug = ccupd_config('slug');

        deactivate_plugins($basename, false, true);

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);

        $result = $upgrader->run(array(
            'package'           => $api_data['package'],
            'destination'       => WP_PLUGIN_DIR . '/' . $slug,
            'clear_destination' => true,
            'clear_working'     => true,
            'hook_extra'        => array(
                'plugin' => $basename,
            ),
            'incompatible_archive' => false,
        ));

        if (is_wp_error($result)) {
            activate_plugin($basename, '', false, true);
            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 400);
        }

        activate_plugin($basename, '', false, true);

        delete_site_transient('update_plugins');
        delete_option('codeconfig_check_result');
        delete_option('codeconfig_api_status');
        delete_option('codeconfig_last_check');

        $new_version = ! empty($api_data['new_version']) ? $api_data['new_version'] : '';

        return new WP_REST_Response(array(
            'success'      => true,
            'new_version' => $new_version,
            'message'     => 'Plugin updated successfully to version ' . $new_version . '!',
        ), 200);
    }
}