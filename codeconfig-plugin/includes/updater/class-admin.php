<?php
if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_Updater_Admin
{
    public static function init()
    {
        add_filter('plugin_action_links_' . ccupd_config('basename', ''), array( __CLASS__, 'add_plugin_action_links' ));
        add_action('admin_menu', array( __CLASS__, 'add_menu' ));
        add_action('admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ));
        add_action('admin_init', array( __CLASS__, 'register_settings' ));
        add_action('admin_init', array( __CLASS__, 'maybe_update_plugin' ));
    }

    public static function register_settings()
    {
        register_setting('codeconfig-plugin-settings', 'codeconfig_api_key');
        register_setting('codeconfig-plugin-settings', 'codeconfig_name');
        register_setting('codeconfig-plugin-settings', 'codeconfig_email');
    }

    public static function add_plugin_action_links($links)
    {
        if (ccupd_config('is_pro', false)) {
            return $links;
        }

        $links['check_update'] = sprintf(
            '<a href="#" id="codeconfig-plugin-row-check" style="color:#d63638;">%s</a>',
            esc_html__('Check for Updates', 'codeconfig-plugin')
        );

        return $links;
    }

    public static function add_menu()
    {
        $show_admin_page = ccupd_config('show_admin_page', false);
        $menu_config = ccupd_config('menu', array());
        $plugin_name = ccupd_config('name', 'CodeConfig Plugin');

        if (! $show_admin_page) {
            return;
        }

        $menu_slug = ccupd_config('slug', 'codeconfig-plugin') . '-status';

        if (! empty($menu_config['parent_slug'])) {
            add_submenu_page(
                $menu_config['parent_slug'],
                ! empty($menu_config['page_title']) ? $menu_config['page_title'] : $plugin_name . ' Updates',
                ! empty($menu_config['menu_title']) ? $menu_config['menu_title'] : 'Updates',
                'manage_options',
                $menu_slug,
                array( __CLASS__, 'render_page' )
            );
        } else {
            add_menu_page(
                $plugin_name . ' Updates',
                $plugin_name,
                'manage_options',
                $menu_slug,
                array( __CLASS__, 'render_page' ),
                'dashicons-update',
                31
            );
        }
    }

    public static function enqueue_assets($hook)
    {
        // Load on plugins.php page AND all codeconfig plugin admin pages
        if (strpos($hook, 'codeconfig') === false && $hook !== 'plugins.php') {
            return;
        }

        // Use native WordPress function - get updater folder URL
        $assets_url = plugin_dir_url(__DIR__) . '/updater/assets/';

        wp_enqueue_script(
            'codeconfig-plugin-admin-js',
            $assets_url . 'js/admin.js',
            array( 'jquery' ),
            '1.0.0',
            true
        );

        wp_localize_script('codeconfig-plugin-admin-js', 'codeconfigPluginAdmin', array(
            'restUrl'    => rest_url('ccupd/v1') . '/',
            'nonce'      => wp_create_nonce('wp_rest'),
            'checking'   => 'Checking...',
            'checkBtn'   => 'Check for Updates',
        ));

        wp_enqueue_style(
            'codeconfig-plugin-admin-css',
            $assets_url . 'css/admin.css',
            array(),
            '1.0.0'
        );
    }

    public static function render_page()
    {

        $is_pro     = ccupd_config('is_pro', false);
        $api_status = get_option('codeconfig_api_status', 'unknown');
        $last_check = CodeConfig_REST::get_last_check();
        // Use cached data from cron job instead of calling API on every page load
        $api_data   = $is_pro ? array() : get_option('codeconfig_check_result', array());

        self::maybe_update_plugin();
        self::render_notices();
        ?>
		<div class="wrap codeconfig-plugin-status-wrap">
			<h1>My Plugin — Update Status</h1>

			<?php self::render_status_card($last_check, $api_status, $is_pro, $api_data); ?>

			<?php if (! $is_pro) : ?>
				<?php self::render_actions($api_data); ?>
				<?php self::render_connection_info($api_status); ?>
				<?php self::render_changelog($api_data); ?>
			<?php else : ?>
				<?php self::render_pro_notice(); ?>
			<?php endif; ?>
		</div>
		<?php
    }

    private static function get_fresh_api_data()
    {
        // Only used for manual "Check for Updates" button via AJAX
        // Page loads now use cached data from cron
        $result = CodeConfig_REST::check_api();

        if (is_wp_error($result)) {
            update_option('codeconfig_api_status', 'error');
            return array( 'update' => false, 'error' => $result->get_error_message() );
        }

        update_option('codeconfig_api_status', 'connected');
        update_option('codeconfig_last_check', time());
        update_option('codeconfig_check_result', $result);

        return $result;
    }

    private static function render_notices()
    {

        if (isset($_GET['codeconfig_update_done'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Plugin updated successfully! New version is now active.</p></div>';
        }

        if (isset($_GET['codeconfig_update_error'])) {
            printf(
                '<div class="notice notice-error is-dismissible"><p>Update failed: %s</p></div>',
                esc_html(sanitize_text_field($_GET['codeconfig_update_error']))
            );
        }
    }

    public static function maybe_update_plugin()
    {

        if (! isset($_GET['codeconfig_do_update']) || ! isset($_GET['update_nonce'])) {
            return;
        }

        if (! wp_verify_nonce($_GET['update_nonce'], 'codeconfig_do_update')) {
            wp_die('Security check failed.');
        }

        if (! current_user_can('update_plugins')) {
            wp_die('Permission denied.');
        }

        $api_data = CodeConfig_REST::check_api();

        if (is_wp_error($api_data) || empty($api_data['update'])) {
            wp_redirect(add_query_arg(array(
                'page'                   => 'codeconfig-plugin-status',
                'codeconfig_update_error' => 'No update available or API error.',
            ), admin_url('admin.php')));
            exit;
        }

        delete_site_transient('update_plugins');

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';

        $basename = ccupd_config('basename');
        $slug = ccupd_config('slug');

        deactivate_plugins($basename);

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
        ));

        if (is_wp_error($result)) {
            activate_plugin($basename, '', false, true);
            wp_redirect(add_query_arg(array(
                'page'                   => 'codeconfig-plugin-status',
                'codeconfig_update_error' => $result->get_error_message(),
            ), admin_url('admin.php')));
            exit;
        }

        $basename = ccupd_config('basename');

        activate_plugin($basename, '', false, true);

        delete_site_transient('update_plugins');
        delete_option('codeconfig_check_result');
        delete_option('codeconfig_api_status');
        delete_option('codeconfig_last_check');

        wp_redirect(add_query_arg(array(
            'page'                 => 'codeconfig-plugin-status',
            'codeconfig_update_done' => '1',
        ), admin_url('admin.php')));
        exit;
    }

    private static function render_status_card($last_check, $api_status, $is_pro, $api_data)
    {

        $status_class = 'status-unknown';
        $status_text  = 'Not checked yet';
        $status_icon  = '⏳';

        if ($is_pro) {
            $status_class = 'status-pro';
            $status_text  = 'Managed by Freemius';
            $status_icon  = '✅';
        } elseif (! empty($api_data['update'])) {
            $status_class = 'status-update';
            $status_text  = 'Update Available';
            $status_icon  = '⚠️';
        } elseif (! empty($api_data['error'])) {
            $status_class = 'status-error';
            $status_text  = 'API Connection Error';
            $status_icon  = '❌';
        } elseif ('connected' === $api_status) {
            $status_class = 'status-current';
            $status_text  = 'Up to Date';
            $status_icon  = '✅';
        }

        $has_update  = ! $is_pro && ! empty($api_data['update']);
        $new_version = $api_data['new_version'] ?? '';
        ?>
		<div class="card codeconfig-plugin-status-card <?php echo esc_attr($status_class); ?>">
			<h2>Current Status</h2>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th>Current Version</th>
						<td><code><?php echo esc_html(ccupd_config('version', '1.0.0')); ?></code></td>
					</tr>
					<?php if ($new_version) : ?>
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
								<a href="<?php echo esc_url(wp_nonce_url(add_query_arg('codeconfig_do_update', '1', admin_url('admin.php?page=codeconfig-plugin-status')), 'codeconfig_do_update', 'update_nonce')); ?>" class="button button-primary" style="margin-left:10px;">Update Now to <?php echo esc_html($new_version); ?></a>
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

    private static function render_actions($api_data = array())
    {
        $api_key      = get_option('codeconfig_api_key', '');
        $name         = get_option('codeconfig_name', '');
        $email        = get_option('codeconfig_email', '');
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
				<?php settings_fields('codeconfig-plugin-settings'); ?>
				<table class="form-table">
					<tr>
						<th><label for="codeconfig_name">Name (optional)</label></th>
						<td>
							<input type="text" id="codeconfig_name" name="codeconfig_name" class="regular-text" value="<?php echo esc_attr($name); ?>" placeholder="Your name or site name" />
							<p class="description">This helps identify your site on the API server.</p>
						</td>
					</tr>
					<tr>
						<th><label for="codeconfig_email">Email (optional)</label></th>
						<td>
							<input type="email" id="codeconfig_email" name="codeconfig_email" class="regular-text" value="<?php echo esc_attr($email); ?>" placeholder="your@email.com" />
							<p class="description">Your contact email for update notifications.</p>
						</td>
					</tr>
					<tr>
						<th><label for="codeconfig_api_key">API Key</label></th>
						<td>
							<input type="text" id="codeconfig_api_key" name="codeconfig_api_key" class="regular-text" value="<?php echo esc_attr($api_key); ?>" placeholder="Paste your API key here" />
						</td>
					</tr>
				</table>
				<?php submit_button('Save Settings'); ?>
			</form>
		</div>
		<?php
    }

    private static function render_connection_info($api_status)
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
						<td><code><?php echo esc_html(ccupd_config('api_url')); ?></code></td>
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

    private static function render_changelog($api_data)
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

    private static function render_pro_notice()
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

    public static function handle_force_refresh()
    {

        if (! check_ajax_referer('codeconfig_check_nonce', 'nonce', false)) {
            wp_send_json_error(array( 'message' => 'Security check failed.' ));
        }

        if (! current_user_can('update_plugins')) {
            wp_send_json_error(array( 'message' => 'Permission denied.' ));
        }

        CodeConfig_REST::force_clear_cache();
        delete_site_transient('update_plugins');

        wp_send_json_success(array(
            'message' => 'Cache cleared. Click "Check for Updates" to fetch latest info.',
        ));
    }

    public static function handle_test_connection()
    {

        if (! check_ajax_referer('codeconfig_check_nonce', 'nonce', false)) {
            wp_send_json_error(array( 'message' => 'Security check failed.' ));
        }

        $result = CodeConfig_REST::check_api();

        if (is_wp_error($result)) {
            update_option('codeconfig_api_status', 'error');
            wp_send_json_error(array(
                'message' => $result->get_error_message(),
            ));
        }

        update_option('codeconfig_api_status', 'connected');
        wp_send_json_success(array(
            'message' => 'Connection successful!',
        ));
    }
}
