<?php

if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_Updater_Admin
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
        $basename = $this->manager->get_config('basename', '');
        add_filter('plugin_action_links_' . $basename, [$this, 'add_plugin_action_links']);
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'handle_upgrade_action']);
        add_action('after_plugin_row_' . $basename, [$this, 'render_plugin_update_notice'], 10, 3);
    }

    public function register_settings()
    {
        $option_group = 'ccupd_' . $this->slug . '-settings';
        register_setting($option_group, 'ccupd_' . $this->slug . '_api_key');
        register_setting($option_group, 'ccupd_' . $this->slug . '_name');
        register_setting($option_group, 'ccupd_' . $this->slug . '_email');
    }

    public function add_plugin_action_links($links)
    {
        if ($this->manager->get_config('is_pro', false)) {
            return $links;
        }

        $links['check_update'] = sprintf(
            '<a href="#" id="codeconfig-plugin-row-check" style="color:#d63638;">%s</a>',
            esc_html__('Check for Updates', 'codeconfig-plugin')
        );

        return $links;
    }

    public function add_menu()
    {
        $show_admin_page = $this->manager->get_config('show_admin_page', false);
        $menu_config = $this->manager->get_config('menu', []);
        $plugin_name = $this->manager->get_config('name', 'CodeConfig Plugin');

        if (! $show_admin_page) {
            return;
        }

        $menu_slug = $this->manager->get_config('slug', 'codeconfig-plugin') . '-status';

        if (! empty($menu_config['parent_slug'])) {
            add_submenu_page(
                $menu_config['parent_slug'],
                ! empty($menu_config['page_title']) ? $menu_config['page_title'] : $plugin_name . ' Updates',
                ! empty($menu_config['menu_title']) ? $menu_config['menu_title'] : 'Updates',
                'manage_options',
                $menu_slug,
                [$this, 'render_page']
            );
        } else {
            add_menu_page(
                $plugin_name . ' Updates',
                $plugin_name,
                'manage_options',
                $menu_slug,
                [$this, 'render_page'],
                'dashicons-update',
                31
            );
        }
    }

    public function enqueue_assets($hook)
    {
        $slug = $this->manager->get_config('slug', '');
        if (strpos($hook, $slug) === false && $hook !== 'plugins.php') {
            return;
        }

        $assets_url = plugin_dir_url(__DIR__) . 'updater/assets/';

        wp_enqueue_script(
            'codeconfig-plugin-admin-js-' . $this->slug,
            $assets_url . 'js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        $js_var_name = 'codeconfigPluginAdmin_' . str_replace('-', '_', $this->slug);

        wp_localize_script('codeconfig-plugin-admin-js-' . $this->slug, $js_var_name, [
            'restUrl' => rest_url('ccupd/v1/' . $slug) . '/',
            'nonce' => wp_create_nonce('wp_rest'),
            'checking' => 'Checking...',
            'checkBtn' => 'Check for Updates',
            'pluginName' => $this->manager->get_config('name', 'This plugin'),
            'pluginSlug' => $this->manager->get_config('slug', ''),
            'currentVersion' => $this->manager->get_config('version', ''),
            'basename' => $this->manager->get_config('basename', ''),
            'updateNonce' => wp_create_nonce($this->slug . '_do_update'),
            'updateUrl' => wp_nonce_url(admin_url('update.php?action=' . $this->slug . '-upgrade-plugin&plugin=' . urlencode($this->manager->get_config('basename', ''))), $this->slug . '_upgrade_plugin_' . $this->manager->get_config('basename', '')),
        ]);

        wp_enqueue_style(
            'codeconfig-plugin-admin-css-' . $this->slug,
            $assets_url . 'css/admin.css',
            [],
            '1.0.0'
        );
    }

    public function render_page()
    {
        $is_pro = $this->manager->get_config('is_pro', false);
        $option_prefix = 'ccupd_' . $this->slug . '_';
        $api_status = get_option($option_prefix . 'api_status', 'unknown');
        $last_check = CodeConfig_REST::get_last_check($this->manager);
        $api_data = $is_pro ? [] : get_option($option_prefix . 'check_result', []);

        $this->render_notices();
        ?>
        <div class="wrap codeconfig-plugin-status-wrap">
            <h1><?php echo esc_html($this->manager->get_config('name', 'This plugin')); ?> — Update Status</h1>

            <?php $this->render_status_card($last_check, $api_status, $is_pro, $api_data); ?>

            <?php if (! $is_pro) : ?>
                <?php $this->render_actions($api_data); ?>
                <?php $this->render_connection_info($api_status); ?>
                <?php $this->render_changelog($api_data); ?>
            <?php else : ?>
                <?php $this->render_pro_notice(); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_fresh_api_data()
    {
        $result = CodeConfig_REST::check_api($this->manager);
        $option_prefix = 'ccupd_' . $this->slug . '_';

        if (is_wp_error($result)) {
            update_option($option_prefix . 'api_status', 'error');

            return ['update' => false, 'error' => $result->get_error_message()];
        }

        update_option($option_prefix . 'api_status', 'connected');
        update_option($option_prefix . 'last_check', time());
        update_option($option_prefix . 'check_result', $result);

        return $result;
    }

    private function render_notices()
    {
        $success_transient = 'ccupd_' . $this->slug . '_update_success';
        $transient_success = get_transient($success_transient);
        if ($transient_success) {
            delete_transient($success_transient);
            echo '<div class="notice notice-success is-dismissible"><p>Plugin updated successfully to version ' . esc_html($transient_success) . '! New version is now active.</p></div>';
        }

        if (isset($_GET['ccupd_update_done'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Plugin updated successfully! New version is now active.</p></div>';
        }

        if (isset($_GET['ccupd_update_error'])) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>Update failed: %s</p></div>',
                esc_html(sanitize_text_field($_GET['ccupd_update_error']))
            );
        }
    }

    public function handle_upgrade_action()
    {
        $action_slug = $this->slug . '-upgrade-plugin';
        if (! isset($_GET['action']) || $_GET['action'] !== $action_slug) {
            return;
        }

        if (! isset($_GET['plugin'])) {
            return;
        }

        $basename = sanitize_text_field($_GET['plugin']);
        $nonce_action = $this->slug . '_upgrade_plugin_' . $basename;
        $nonce = $_GET['_wpnonce'] ?? '';

        if (! wp_verify_nonce($nonce, $nonce_action)) {
            wp_die('Security check failed.');
        }

        if (! current_user_can('update_plugins')) {
            wp_die('Permission denied.');
        }

        $api_data = CodeConfig_REST::check_api($this->manager);
        $option_prefix = 'ccupd_' . $this->slug . '_';

        if (is_wp_error($api_data) || empty($api_data['update'])) {
            wp_redirect(add_query_arg([
                'ccupd_update_error' => 'No update available or API error.',
            ], admin_url('plugins.php')));
            exit;
        }

        delete_site_transient('update_plugins');

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        deactivate_plugins($basename, false, true);

        $skin = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader($skin);

        $slug = $this->manager->get_config('slug');
        $version = $this->manager->get_config('version', '1.0.0');

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

        if (is_wp_error($result)) {
            activate_plugin($basename, '', false, true);
            wp_redirect(add_query_arg([
                'ccupd_update_error' => $result->get_error_message(),
            ], admin_url('plugins.php')));
            exit;
        }

        activate_plugin($basename, '', false, true);

        delete_site_transient('update_plugins');
        delete_option($option_prefix . 'check_result');
        delete_option($option_prefix . 'api_status');
        delete_option($option_prefix . 'last_check');

        $new_version = ! empty($api_data['new_version']) ? $api_data['new_version'] : '';
        set_transient($success_transient, $new_version, 30);

        wp_redirect(add_query_arg([
            'ccupd_update_done' => '1',
        ], admin_url('plugins.php')));
        exit;
    }

    private function render_status_card($last_check, $api_status, $is_pro, $api_data)
    {
        $status_class = 'status-unknown';
        $status_text = 'Not checked yet';
        $status_icon = '⏳';

        $new_version = $api_data['new_version'] ?? '';
        $current_version = $this->manager->get_config('version', '1.0.0');
        $has_update = ! $is_pro && ! empty($api_data['update']) && ! empty($new_version) && version_compare($current_version, $new_version, '<');

        if ($is_pro) {
            $status_class = 'status-pro';
            $status_text = 'Managed by Freemius';
            $status_icon = '✅';
        } elseif ($has_update) {
            $status_class = 'status-update';
            $status_text = 'Update Available';
            $status_icon = '⚠️';
        } elseif (! empty($api_data['error'])) {
            $status_class = 'status-error';
            $status_text = 'API Connection Error';
            $status_icon = '❌';
        } elseif ('connected' === $api_status) {
            $status_class = 'status-current';
            $status_text = 'Up to Date';
            $status_icon = '✅';
        }
        ?>
        <div class="card codeconfig-plugin-status-card <?php echo esc_attr($status_class); ?>">
            <h2>Current Status</h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th>Current Version</th>
                        <td><code><?php echo esc_html($this->manager->get_config('version', '1.0.0')); ?></code></td>
                    </tr>
                    <?php if ($has_update && $new_version) : ?>
                        <tr>
                            <th>Latest Version</th>
                            <td><code><?php echo esc_html($new_version); ?></code></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="codeconfig-plugin-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_icon); ?> <?php echo esc_html($status_text); ?></span>
                            <?php if ($has_update) : ?>
                                <button type="button" class="button button-primary codeconfig-plugin-update-btn" style="margin-left:10px;">Update Now to <?php echo esc_html($new_version); ?></button>
                            <?php else : ?>
                                <button type="button" class="button button-primary codeconfig-plugin-update-btn" style="margin-left:10px; display:none;">Update Now</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>License</th>
                        <td><?php echo $is_pro ? 'Pro (Freemius)' : 'Free'; ?></td>
                    </tr>
                    <?php if ($last_check) : ?>
                        <tr>
                            <th>Last Checked</th>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', (int) $last_check['time'])); ?></td>
                        </tr>
                    <?php else : ?>
                        <tr>
                            <th>Last Checked</th>
                            <td>Never</td>
                        </tr>
                    <?php endif; ?>
                    <?php if (! empty($api_data['downloads'])) : ?>
                        <tr>
                            <th>Downloads</th>
                            <td><?php echo (int) $api_data['downloads']; ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function render_actions($api_data = [])
    {
        $option_prefix = 'ccupd_' . $this->slug . '_';
        $api_key = get_option($option_prefix . 'api_key', '');
        $name = get_option($option_prefix . 'name', '');
        $email = get_option($option_prefix . 'email', '');
        ?>
        <div class="card codeconfig-plugin-actions-card">
            <h2>Actions</h2>
            <p>
                <button type="button" id="codeconfig-plugin-check-btn" class="button button-secondary">
                    <span class="dashicons dashicons-update" style="margin-top:3px;"></span>
                    Check for Updates
                </button>
                <button type="button" id="codeconfig-plugin-refresh-btn" class="button button-secondary">
                    Force Refresh
                </button>
            </p>
            <div id="codeconfig-plugin-check-result" class="notice inline" style="display:none;"></div>
        </div>

        <div class="card codeconfig-plugin-api-key-card">
            <h2>API Settings</h2>
            <p>Enter the API key from your server admin panel to enable authenticated updates. Optionally provide your name and email for better user identification.</p>
            <form method="post" action="options.php">
                <?php
                $option_group = 'ccupd_' . $this->slug . '-settings';
                settings_fields($option_group);
                ?>
                <table class="form-table">
                    <tr>
                        <th><label for="ccupd_<?php echo esc_attr($this->slug); ?>_name">Name (optional)</label></th>
                        <td>
                            <input type="text" id="ccupd_<?php echo esc_attr($this->slug); ?>_name" name="ccupd_<?php echo esc_attr($this->slug); ?>_name" class="regular-text" value="<?php echo esc_attr($name); ?>" placeholder="Your name or site name" />
                            <p class="description">This helps identify your site on the API server.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ccupd_<?php echo esc_attr($this->slug); ?>_email">Email (optional)</label></th>
                        <td>
                            <input type="email" id="ccupd_<?php echo esc_attr($this->slug); ?>_email" name="ccupd_<?php echo esc_attr($this->slug); ?>_email" class="regular-text" value="<?php echo esc_attr($email); ?>" placeholder="your@email.com" />
                            <p class="description">Your contact email for update notifications.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ccupd_<?php echo esc_attr($this->slug); ?>_api_key">API Key</label></th>
                        <td>
                            <input type="text" id="ccupd_<?php echo esc_attr($this->slug); ?>_api_key" name="ccupd_<?php echo esc_attr($this->slug); ?>_api_key" class="regular-text" value="<?php echo esc_attr($api_key); ?>" placeholder="Paste your API key here" />
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }

    private function render_connection_info($api_status)
    {
        $status_label = 'Unknown';
        $status_class = 'status-unknown';

        switch ($api_status) {
            case 'connected':
                $status_label = 'Connected';
                $status_class = 'status-current';
                break;
            case 'error':
                $status_label = 'Connection Failed';
                $status_class = 'status-error';
                break;
        }
        ?>
        <div class="card codeconfig-plugin-connection-card">
            <h2>API Connection</h2>
            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th>API URL</th>
                        <td><code><?php echo esc_html($this->manager->get_config('api_url')); ?></code></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td><span class="codeconfig-plugin-status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
                    </tr>
                </tbody>
            </table>
            <p style="margin-top:15px;">
                <button type="button" id="codeconfig-plugin-test-btn" class="button button-secondary">
                    Test Connection
                </button>
            </p>
            <div id="codeconfig-plugin-test-result" class="notice inline" style="display:none;"></div>
        </div>
        <?php
    }

    private function render_changelog($api_data)
    {
        if (empty($api_data['changelog'])) {
            return;
        }
        ?>
        <div class="card codeconfig-plugin-changelog-card">
            <h2>Latest Changelog</h2>
            <div class="codeconfig-plugin-changelog">
                <?php echo wpautop(esc_html($api_data['changelog'])); ?>
            </div>
        </div>
        <?php
    }

    private function render_pro_notice()
    {
        ?>
        <div class="card codeconfig-plugin-pro-card">
            <h2>Freemius License</h2>
            <div class="notice notice-success inline">
                <p>Updates are managed by Freemius. No action needed.</p>
            </div>
        </div>
        <?php
    }

    private $updater = null;

    public function set_updater($updater)
    {
        $this->updater = $updater;
    }

    public function handle_force_refresh()
    {
        if (! check_ajax_referer('ccupd_check_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        if (! current_user_can('update_plugins')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        CodeConfig_REST::force_clear_cache($this->manager);
        delete_site_transient('update_plugins');

        wp_send_json_success([
            'message' => 'Cache cleared. Click "Check for Updates" to fetch latest info.',
        ]);
    }

    public function handle_test_connection()
    {
        if (! check_ajax_referer('ccupd_check_nonce', 'nonce', false)) {
            wp_send_json_error(['message' => 'Security check failed.']);
        }

        $result = CodeConfig_REST::check_api($this->manager);

        if (is_wp_error($result)) {
            $option_prefix = 'ccupd_' . $this->slug . '_';
            update_option($option_prefix . 'api_status', 'error');
            wp_send_json_error([
                'message' => $result->get_error_message(),
            ]);
        }

        $option_prefix = 'ccupd_' . $this->slug . '_';
        update_option($option_prefix . 'api_status', 'connected');
        wp_send_json_success([
            'message' => 'Connection successful!',
        ]);
    }

    public function render_plugin_update_notice($plugin_file, $plugin_data, $status)
    {
        $is_pro = $this->manager->get_config('is_pro', false);
        if ($is_pro) {
            return;
        }

        $basename = $this->manager->get_config('basename', '');
        if ($plugin_file !== $basename) {
            return;
        }

        $option_prefix = 'ccupd_' . $this->slug . '_';
        $api_data = get_option($option_prefix . 'check_result', []);
        if (empty($api_data['update'])) {
            return;
        }

        $new_version = ! empty($api_data['new_version']) ? $api_data['new_version'] : '';
        $current_version = $this->manager->get_config('version', '1.0.0');

        if (empty($new_version) || version_compare($current_version, $new_version, '>=')) {
            return;
        }

        $plugin_name = $this->manager->get_config('name', 'This plugin');
        $slug = $this->manager->get_config('slug', '');

        $wp_list_table = _get_list_table('WP_Plugins_List_Table', ['screen' => get_current_screen()]);
        $column_count = $wp_list_table->get_column_count();

        $update_nonce = wp_create_nonce($this->slug . '_upgrade_plugin_' . $basename);
        $update_url = wp_nonce_url(admin_url('update.php?action=' . $this->slug . '-upgrade-plugin&plugin=' . urlencode($basename)), $this->slug . '_upgrade_plugin_' . $basename);

        $details_url = add_query_arg([
            'tab' => 'plugin-information',
            'plugin' => $slug,
            'section' => 'changelog',
            'TB_iframe' => 'true',
            'width' => 600,
            'height' => 800,
        ], admin_url('plugin-install.php'));

        $is_active = is_plugin_active($basename);
        $active_class = $is_active ? ' active' : '';

        echo '<tr class="codeconfig-plugin-update-tr' . esc_attr($active_class) . '" id="' . esc_attr($slug . '-update') . '" data-slug="' . esc_attr($slug) . '" data-plugin="' . esc_attr($basename) . '">';
        echo '<td colspan="' . esc_attr($column_count) . '" class="plugin-update colspanchange">';
        echo '<div class="update-message notice inline notice-warning notice-alt"><p>';
        printf(
            __('There is a new version of %1$s available. <a href="%2$s" class="thickbox open-plugin-details-modal" aria-label="View %1$s version %3$s details">View version %3$s details</a> or <a href="%4$s" class="codeconfig-update-link" aria-label="Update %1$s now">update now</a>.'),
            esc_html($plugin_name),
            esc_url($details_url),
            esc_attr($new_version),
            esc_url($update_url)
        );
        echo '</p></div>';
        echo '</td></tr>';
    }
}