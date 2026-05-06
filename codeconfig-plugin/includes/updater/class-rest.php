<?php

if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_REST
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
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    private function get_namespace()
    {
        return 'ccupd/v1/' . $this->slug;
    }

    public function register_routes()
    {
        $namespace = $this->get_namespace();

        register_rest_route($namespace, '/check', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_check'],
            'permission_callback' => function () {
                return current_user_can('update_plugins');
            },
        ]);

        register_rest_route($namespace, '/test-connection', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_test_connection'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            },
        ]);

        register_rest_route($namespace, '/force-refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_force_refresh'],
            'permission_callback' => function () {
                return current_user_can('update_plugins');
            },
        ]);

        register_rest_route($namespace, '/update', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_update'],
            'permission_callback' => function () {
                return current_user_can('update_plugins');
            },
        ]);
    }

    private function get_manager_from_request(WP_REST_Request $request)
    {
        $plugin = $request->get_param('plugin');
        if (empty($plugin)) {
            return null;
        }
        return ccupd_get_manager($plugin);
    }

    public function handle_check(WP_REST_Request $request)
    {
        $manager = $this->get_manager_from_request($request);
        if (! $manager) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Plugin slug is required.',
            ], 400);
        }

        if ($manager->get_config('is_pro', false)) {
            return new WP_REST_Response([
                'update_available' => false,
                'message' => 'Updates are managed by Freemius.',
                'is_pro' => true,
            ], 200);
        }

        $this->clear_update_transient();

        $updater = $manager->get_updater();
        if ($updater) {
            $updater->force_check_for_update();
        }

        $option_prefix = 'ccupd_' . $manager->get_slug() . '_';
        $result = get_option($option_prefix . 'check_result', []);

        if (empty($result)) {
            $result = self::check_api($manager);
        }

        if (is_wp_error($result)) {
            update_option($option_prefix . 'api_status', 'error');

            return new WP_REST_Response([
                'message' => $result->get_error_message(),
            ], 400);
        }

        update_option($option_prefix . 'api_status', 'connected');
        update_option($option_prefix . 'last_check', time());
        update_option($option_prefix . 'check_result', $result);

        return new WP_REST_Response([
            'update_available' => ! empty($result['update']),
            'new_version' => $result['new_version'] ?? '',
            'message' => ! empty($result['update'])
                ? sprintf('Update available! Version %s is ready to install.', $result['new_version'])
                : 'You are running the latest version.',
            'changelog' => $result['changelog'] ?? '',
        ], 200);
    }

    public function handle_test_connection(WP_REST_Request $request)
    {
        $manager = $this->get_manager_from_request($request);
        if (! $manager) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Plugin slug is required.',
            ], 400);
        }

        $api_url = $manager->get_config('api_url', '');

        if (empty($api_url)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'API URL not configured.',
            ], 400);
        }

        $params = [
            'version' => $manager->get_config('version', '1.0.0'),
            'slug' => $manager->get_config('slug', ''),
            'domain' => site_url(),
        ];

        $response = wp_remote_get(add_query_arg($params, $api_url . '/update-check'), [
            'timeout' => 15,
            'sslverify' => false,
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Connection failed: ' . $response->get_error_message(),
            ], 400);
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Connection successful!',
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Connection failed with HTTP ' . $code,
        ], 400);
    }

    public function handle_force_refresh(WP_REST_Request $request)
    {
        $manager = $this->get_manager_from_request($request);
        if (! $manager) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Plugin slug is required.',
            ], 400);
        }

        $option_prefix = 'ccupd_' . $manager->get_slug() . '_';
        delete_option($option_prefix . 'last_check');
        delete_option($option_prefix . 'check_result');
        delete_option($option_prefix . 'api_status');

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Cache cleared. Please run check again.',
        ], 200);
    }

    private function clear_update_transient()
    {
        delete_site_transient('update_plugins');
    }

    public static function check_api($manager = null)
    {
        return self::check_api_for_version($manager ? $manager->get_config('version', '1.0.0') : '1.0.0', $manager);
    }

    public static function check_api_for_version($version, $manager = null)
    {
        if (! $manager) {
            return new WP_Error(
                'no_manager',
                'Manager instance is required.'
            );
        }

        $api_url = $manager->get_config('api_url', '') . '/update-check';
        $option_prefix = 'ccupd_' . $manager->get_slug() . '_';
        $api_key = get_option($option_prefix . 'api_key', '');

        $params = [
            'version' => $version,
            'slug' => $manager->get_config('slug', ''),
            'domain' => site_url(),
        ];

        if ($api_key) {
            $params['api_key'] = $api_key;
        }

        $name = get_option($option_prefix . 'name', '');
        $email = get_option($option_prefix . 'email', '');

        if (! empty($name)) {
            $params['name'] = $name;
        }
        if (! empty($email)) {
            $params['email'] = $email;
        }

        $response = wp_remote_get(add_query_arg($params, $api_url), [
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error(
                'api_error',
                'Could not connect to the update server. ' . $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code($response);

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (! $data || ! $data['success'] || $code !== 200) {
            $message = ! empty($data['message']) ? $data['message'] : 'Invalid response from update server.';

            return new WP_Error(
                $code,
                $message
            );
        }

        if (! empty($data['user_created']) && ! empty($data['api_key'])) {
            update_option($option_prefix . 'api_key', sanitize_text_field($data['api_key']));
        }

        return $data;
    }

    public static function get_last_check($manager = null)
    {
        if (! $manager) {
            return null;
        }

        $option_prefix = 'ccupd_' . $manager->get_slug() . '_';
        $timestamp = get_option($option_prefix . 'last_check');

        if (! $timestamp) {
            return null;
        }

        return [
            'time' => $timestamp,
            'result' => get_option($option_prefix . 'check_result', []),
        ];
    }

    public static function force_clear_cache($manager = null)
    {
        if (! $manager) {
            return;
        }

        $option_prefix = 'ccupd_' . $manager->get_slug() . '_';
        delete_option($option_prefix . 'last_check');
        delete_option($option_prefix . 'check_result');
        delete_option($option_prefix . 'api_status');
    }

    public function handle_update(WP_REST_Request $request)
    {
        $manager = $this->get_manager_from_request($request);
        if (! $manager) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Plugin slug is required.',
            ], 400);
        }

        if ($manager->get_config('is_pro', false)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Updates are managed by Freemius.',
            ], 400);
        }

        $api_data = self::check_api($manager);

        if (is_wp_error($api_data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'API Error: ' . $api_data->get_error_message(),
            ], 400);
        }

        if (empty($api_data) || ! is_array($api_data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid API response. Please check your API URL configuration.',
            ], 400);
        }

        if (empty($api_data['update'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No update available. You are running the latest version.',
            ], 400);
        }

        if (empty($api_data['package'])) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Update package URL not available.',
            ], 400);
        }

        delete_site_transient('update_plugins');

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $basename = $manager->get_config('basename');
        $slug = $manager->get_config('slug');

        deactivate_plugins($basename, false, true);

        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);

        $result = $upgrader->run([
            'package' => $api_data['package'],
            'destination' => WP_PLUGIN_DIR . '/' . $slug,
            'clear_destination' => true,
            'clear_working' => true,
            'hook_extra' => [
                'plugin' => $basename,
            ],
            'incompatible_archive' => false,
        ]);

        $option_prefix = 'ccupd_' . $manager->get_slug() . '_';

        if (is_wp_error($result)) {
            activate_plugin($basename, '', false, true);

            return new WP_REST_Response([
                'success' => false,
                'message' => $result->get_error_message(),
            ], 400);
        }

        activate_plugin($basename, '', false, true);

        delete_site_transient('update_plugins');
        delete_option($option_prefix . 'check_result');
        delete_option($option_prefix . 'api_status');
        delete_option($option_prefix . 'last_check');

        $new_version = ! empty($api_data['new_version']) ? $api_data['new_version'] : '';

        return new WP_REST_Response([
            'success' => true,
            'new_version' => $new_version,
            'message' => 'Plugin updated successfully to version ' . $new_version . '!',
        ], 200);
    }
}