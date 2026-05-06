<?php

if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_Update_Check
{
    public static function handle($request)
    {

        $slug    = $request->get_param('slug');
        $version = $request->get_param('version');
        $key     = $request->get_param('api_key');
        $domain  = $request->get_param('domain');
        $name    = $request->get_param('name');
        $email   = $request->get_param('email');

        if (empty($domain)) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Domain parameter is required.',
            ), 400);
        }

        $settings = CodeConfig_Settings::get_settings();
        $auto_create = ! empty($settings['auto_create_users']);
        $user_id = null;
        $new_user_created = false;

        if ($key) {
            $user = CodeConfig_Users_DB::get_user_by_api_key($key);

            if (! $user) {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'Invalid API key or user deactivated.',
                ), 403);
            }

            $user_id = $user['id'];
            CodeConfig_Users_DB::update_last_used($user['id']);
        } else {
            $existing_user = CodeConfig_Users_DB::get_user_by_domain($domain);

            if ($existing_user) {
                if (! empty($existing_user['is_active'])) {
                    CodeConfig_Users_DB::update_last_used($existing_user['id']);
                    $key = $existing_user['api_key'];
                    $user_id = $existing_user['id'];
                } else {
                    return new WP_REST_Response(array(
                        'success' => false,
                        'message' => 'User is deactivated. Please contact the support.',
                    ), 403);
                }
            } elseif ($auto_create) {
                $require_name = ! empty($settings['require_name']);
                $require_email = ! empty($settings['require_email']);

                if ($require_name && empty($name)) {
                    return new WP_REST_Response(array(
                        'success' => false,
                        'message' => 'Name is required.',
                    ), 400);
                }

                if ($require_email && empty($email)) {
                    return new WP_REST_Response(array(
                        'success' => false,
                        'message' => 'Email is required.',
                    ), 400);
                }

                if (empty($name)) {
                    $name = self::generate_name_from_domain($domain);
                }

                $new_user_id = CodeConfig_Users_DB::insert_user(array(
                    'name'      => $name,
                    'email'     => $email,
                    'domain'    => $domain,
                    'is_active' => $settings['new_user_default_status'] ?? 1,
                ));

                $new_user = CodeConfig_Users_DB::get_user($new_user_id);

                if ($new_user) {
                    $user_id = $new_user['id'];
                    $key = $new_user['api_key'];
                    $new_user_created = true;
                }
            } else {
                return new WP_REST_Response(array(
                    'success' => false,
                    'message' => 'API key required. Please provide a valid API key or enable auto-create in settings.',
                ), 403);
            }
        }

        CodeConfig_Analytics_DB::log_request(array(
            'user_id'      => $user_id,
            'api_key'       => $key,
            'slug'          => $slug,
            'version'       => $version,
            'request_type'  => 'update-check',
            'domain'        => $domain,
        ));

        $latest = CodeConfig_Version_DB::get_active_version($slug);

        if (! $latest) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'No active version found.',
            ), 404);
        }

        if (empty($latest['download_path']) || ! file_exists($latest['download_path'])) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Plugin file not found. Please contact support.',
            ), 404);
        }

        if (version_compare($version, $latest['version'], '>=')) {
            $response = array(
                'success'   => true,
                'update'    => false,
                'version'   => $latest['version'],
                'message'   => 'You are running the latest version.',
            );
            if ($key) {
                $response['api_key'] = $key;
                if ($new_user_created) {
                    $response['user_created'] = true;
                }
            }
            return new WP_REST_Response($response, 200);
        }

        $token = self::generate_download_token($slug, $key, $domain);

        $download_url = self::get_download_url($token, $slug, $key);

        $download_count = CodeConfig_Version_DB::get_download_count($slug, $latest['version']);

        $response = array(
            'success'     => true,
            'update'      => true,
            'new_version' => $latest['version'],
            'slug'        => $slug,
            'package'     => $download_url,
            'changelog'   => $latest['changelog'] ?? '',
            'downloads'   => (int) $download_count,
        );

        if ($key) {
            $response['api_key'] = $key;
            if ($new_user_created) {
                $response['user_created'] = true;
            }
        }

        return new WP_REST_Response($response, 200);
    }

    private static function generate_download_token($slug, $api_key = '', $domain = '')
    {
        $token = wp_generate_password(32, false);

        set_transient(
            'codeconfig_dl_' . $token,
            array(
                'slug'      => $slug,
                'api_key'   => $api_key,
                'created'   => time(),
                'domain'    => $domain,
            ),
            300
        );

        return $token;
    }

    private static function get_download_url($token, $slug, $api_key = '')
    {

        $plugin_dir = basename(CODECONFIG_API_PATH);
        $site_url   = untrailingslashit(get_site_url());

        $download_url = sprintf(
            '%s/wp-content/plugins/%s/download.php?token=%s&slug=%s',
            $site_url,
            $plugin_dir,
            urlencode($token),
            urlencode($slug)
        );

        if ($api_key) {
            $download_url .= '&api_key=' . urlencode($api_key);
        }

        return $download_url;
    }

    private static function generate_name_from_domain($domain)
    {
        $parsed = wp_parse_url($domain);
        $host   = isset($parsed['host']) ? $parsed['host'] : $domain;
        $host   = preg_replace('/^www\./', '', $host);
        $name   = ucwords(sanitize_title(str_replace(array( '-', '_', '.' ), ' ', $host)));
        return $name;
    }
}
