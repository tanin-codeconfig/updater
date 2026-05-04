<?php

if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_Ajax
{
    public static function init()
    {
        add_action('wp_ajax_codeconfig_manual_check', array( __CLASS__, 'handle_manual_check' ));
    }

    public static function handle_manual_check()
    {

        if (! check_ajax_referer('codeconfig_check_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => 'Security check failed.',
            ));
        }

        if (! current_user_can('update_plugins')) {
            wp_send_json_error(array(
                'message' => 'You do not have permission to check for updates.',
            ));
        }

        if (codeconfig_is_pro()) {
            wp_send_json_success(array(
                'update_available' => false,
                'message'          => 'Updates are managed by Freemius.',
                'is_pro'           => true,
            ));
        }

        self::clear_update_transient();

        // Use the new force check method
        CodeConfig_Updater::force_check_for_update();

        $result = get_option('codeconfig_check_result', array());

        if (empty($result)) {
            $result = self::check_api();
        }

        if (is_wp_error($result)) {
            update_option('codeconfig_api_status', 'error');
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        }

        update_option('codeconfig_api_status', 'connected');
        update_option('codeconfig_last_check', time());

        wp_send_json_success(array(
            'update_available' => ! empty($result['update']),
            'new_version'      => $result['new_version'] ?? '',
            'message'          => ! empty($result['update']) ? sprintf('Update available! Version %s is ready to install.', $result['new_version']) : 'You are running the latest version.',
            'changelog'        => $result['changelog'] ?? '',
        ));
    }

    private static function clear_update_transient()
    {
        delete_site_transient('update_plugins');
    }

    public static function check_api()
    {
        return self::check_api_for_version(CODECONFIG_VERSION);
    }

    public static function check_api_for_version($version)
    {

        $api_url = codeconfig_config('api_url') . '/update-check';
        $api_key = codeconfig_get_api_key();

        $params = array(
            'version' => $version,
            'slug'    => codeconfig_config('slug'),
            'domain'  => site_url(),
        );

        if ($api_key) {
            $params['api_key'] = $api_key;
        }

        $name = get_option('codeconfig_name', '');
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
            codeconfig_update_api_key($data['api_key']);
            do_action('codeconfig_api_key_updated', $data['api_key']);
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
}
