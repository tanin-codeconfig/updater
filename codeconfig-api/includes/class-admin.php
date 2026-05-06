<?php
if (! defined('ABSPATH')) {
    exit;
}

class CodeConfig_Admin
{
    public static function add_menu()
    {
        add_menu_page(
            'CodeConfig Versions',
            'CodeConfig API',
            'manage_options',
            'codeconfig-api',
            array( __CLASS__, 'render_page' ),
            'dashicons-update',
            30
        );

        add_submenu_page(
            'codeconfig-api',
            'Latest Version URLs',
            'Latest URLs',
            'manage_options',
            'codeconfig-latest-urls',
            array( __CLASS__, 'render_latest_urls_page' )
        );
    }

    public static function enqueue_assets($hook)
    {
        if ('toplevel_page_codeconfig-api' !== $hook) {
            return;
        }

        wp_enqueue_style(
            'codeconfig-admin-css',
            CODECONFIG_API_URL . 'assets/css/admin.css',
            array(),
            CODECONFIG_API_VERSION
        );

        wp_enqueue_script(
            'codeconfig-jszip',
            CODECONFIG_API_URL . 'assets/js/jszip.min.js',
            array(),
            '3.10.1',
            true
        );

        wp_enqueue_media();
        wp_enqueue_script(
            'codeconfig-admin-js',
            CODECONFIG_API_URL . 'assets/js/admin.js',
            array( 'jquery', 'media-views', 'codeconfig-jszip', 'thickbox' ),
            CODECONFIG_API_VERSION,
            true
        );

        wp_enqueue_style('thickbox');

        $selected_slug = isset($_GET['filter_slug']) ? sanitize_text_field($_GET['filter_slug']) : 'all';
        $versions      = $selected_slug === 'all' ? CodeConfig_Version_DB::get_all_versions() : CodeConfig_Version_DB::get_all_versions($selected_slug);

        $versions_data = array();
        foreach ($versions as $v) {
            $versions_data[ $v['id'] ] = array(
                'version'   => $v['version'],
                'slug'      => $v['slug'],
                'changelog' => $v['changelog'] ?? '',
            );
        }

        wp_localize_script('codeconfig-admin-js', 'codeconfigAdmin', array(
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('codeconfig_admin_nonce'),
            'mediaNonce'    => wp_create_nonce('media_nonce'),
            'confirmUpdate' => 'This version already exists. Do you want to update it?',
            'detecting'     => 'Detecting...',
            'detected'      => 'Auto-detected',
            'edit'          => 'Edit',
            'selectFromMedia' => 'Select from Media Library',
            'versions'      => $versions_data,
        ));
    }

    public static function ajax_check_version()
    {

        check_ajax_referer('codeconfig_admin_nonce', 'nonce');

        $version = sanitize_text_field($_POST['version'] ?? '');
        $slug    = sanitize_text_field($_POST['slug'] ?? 'codeconfig-plugin');

        $existing = CodeConfig_Version_DB::get_existing_version($slug, $version);

        wp_send_json_success(array(
            'exists' => $existing ? true : false,
            'id'     => $existing ? $existing['id'] : 0,
        ));
    }

    public static function ajax_parse_zip()
    {

        check_ajax_referer('codeconfig_admin_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array( 'message' => 'Permission denied.' ));
        }

        if (empty($_FILES['zip_file']['tmp_name'])) {
            wp_send_json_error(array( 'message' => 'No file uploaded.' ));
        }

        $result = CodeConfig_Zip_Parser::parse($_FILES['zip_file']['tmp_name']);

        if (! $result) {
            wp_send_json_error(array( 'message' => 'Could not parse ZIP file.' ));
        }

        wp_send_json_success($result);
    }

    public static function ajax_parse_media_zip()
    {

        check_ajax_referer('media_nonce', 'nonce');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(array( 'message' => 'Permission denied.' ));
        }

        $attachment_id = (int) ($_POST['attachment_id'] ?? 0);

        if (! $attachment_id) {
            wp_send_json_error(array( 'message' => 'No attachment selected.' ));
        }

        $file_path = get_attached_file($attachment_id);

        if (! $file_path || ! file_exists($file_path)) {
            wp_send_json_error(array( 'message' => 'File not found.' ));
        }

        $result = CodeConfig_Zip_Parser::parse($file_path);

        if (! $result) {
            wp_send_json_error(array( 'message' => 'Could not parse ZIP file.' ));
        }

        wp_send_json_success($result);
    }

    public static function register_actions()
    {
        add_action('admin_post_codeconfig_versions_action', array( __CLASS__, 'handle_form_submit' ));
        add_action('wp_ajax_codeconfig_parse_zip', array( __CLASS__, 'ajax_parse_zip' ));
        add_action('wp_ajax_codeconfig_parse_media_zip', array( __CLASS__, 'ajax_parse_media_zip' ));
    }

    public static function handle_form_submit()
    {

        if (! check_admin_referer('codeconfig_admin_action', 'codeconfig_nonce')) {
            wp_die('Security check failed.');
        }

        if (! current_user_can('manage_options')) {
            wp_die('Permission denied.');
        }

        // Prevent browser from caching POST requests
        nocache_headers();

        $action = sanitize_text_field($_POST['codeconfig_action']);
        $args   = array( 'page' => 'codeconfig-api' );

        // Handle bulk actions
        if (in_array($action, array( 'activate', 'deactivate', 'delete' ), true) && isset($_POST['bulk_ids'])) {
            $bulk_ids = array_map('intval', (array) $_POST['bulk_ids']);
            $bulk_ids = array_filter($bulk_ids);

            if (empty($bulk_ids)) {
                $args['codeconfig_notice'] = 'no_items_selected';
                nocache_headers();
                wp_redirect(add_query_arg($args, admin_url('admin.php')), 303);
                exit;
            }

            foreach ($bulk_ids as $id) {
                if ('delete' === $action) {
                    $version = CodeConfig_Version_DB::get_existing_version_by_id($id);
                    if ($version && ! empty($version['download_path']) && file_exists($version['download_path'])) {
                        unlink($version['download_path']);
                    }
                    // Only delete media attachment if it exists and we're deleting
                    if ($version && ! empty($version['attachment_id'])) {
                        wp_delete_attachment((int) $version['attachment_id'], true);
                    }
                    CodeConfig_Version_DB::delete_version($id);
                } else {
                    CodeConfig_Version_DB::update_version($id, array( 'is_active' => 'activate' === $action ? 1 : 0 ));
                }
            }

            $args['codeconfig_notice'] = 'bulk_' . $action;
            nocache_headers();
            wp_redirect(add_query_arg($args, admin_url('admin.php')), 303);
            exit;
        }

        if ('add_version' === $action) {
            $version       = sanitize_text_field($_POST['version']);
            $slug         = sanitize_text_field($_POST['slug']);
            $changelog   = wp_kses_post($_POST['changelog'] ?? '');
            $attachment_id = isset($_POST['media_attachment_id']) ? (int) $_POST['media_attachment_id'] : 0;

            $has_file = ! empty($_FILES['plugin_zip']['tmp_name']);
            $has_attachment = $attachment_id > 0;

            if (! $has_file && ! $has_attachment) {
                $args['codeconfig_notice'] = 'upload_error';
            } else {
                if (! is_dir(CODECONFIG_API_STORAGE)) {
                    wp_mkdir_p(CODECONFIG_API_STORAGE);
                }

                $upload_file = self::handle_file_upload($has_file, $has_attachment, $attachment_id);

                if (! $upload_file) {
                    $args['codeconfig_notice'] = $has_file ? 'upload_failed' : 'upload_error';
                    nocache_headers();
                    wp_redirect(add_query_arg($args, admin_url('admin.php')), 303);
                    exit;
                }

                if (empty($version) || empty($slug)) {
                    $parsed = CodeConfig_Zip_Parser::parse($upload_file);
                    if ($parsed) {
                        if (empty($slug)) {
                            $slug = sanitize_text_field($parsed['slug']);
                        }
                        if (empty($version)) {
                            $version = sanitize_text_field($parsed['version']);
                        }
                    }
                }

                if (empty($slug) || empty($version)) {
                    $args['codeconfig_notice'] = 'parse_failed';
                    nocache_headers();
                    wp_redirect(add_query_arg($args, admin_url('admin.php')), 303);
                    exit;
                }

                $rename = CODECONFIG_API_STORAGE . $slug . '-v' . $version . '.zip';
                $upload_file = str_replace('//', '/', $upload_file);
                $rename = str_replace('//', '/', $rename);

                if (file_exists($upload_file) && $upload_file !== $rename) {
                    if (file_exists($rename)) {
                        unlink($rename);
                    }
                    rename($upload_file, $rename);
                } else {
                    $rename = $upload_file;
                }

                if (! file_exists($rename)) {
                    $args['codeconfig_notice'] = 'upload_failed';
                    nocache_headers();
                    wp_redirect(add_query_arg($args, admin_url('admin.php')), 303);
                    exit;
                }

                $existing = CodeConfig_Version_DB::get_existing_version($slug, $version);

                if ($existing) {
                    CodeConfig_Version_DB::update_version($existing['id'], array(
                        'changelog'      => $changelog,
                        'download_path'  => $rename,
                        'is_active'      => 0,
                        'attachment_id'  => $attachment_id,
                    ));
                    $args['codeconfig_notice'] = 'updated';
                } else {
                    CodeConfig_Version_DB::insert_version(array(
                        'version'        => $version,
                        'slug'           => $slug,
                        'changelog'      => $changelog,
                        'download_path'  => $rename,
                        'attachment_id'  => $attachment_id,
                        'is_active'      => 0,
                    ));
                    $args['codeconfig_notice'] = 'success';
                }
                $args['version'] = $version;
            }
        }

        if ('activate' === $action) {
            $id = (int) $_POST['id'];
            CodeConfig_Version_DB::update_version($id, array( 'is_active' => 1 ));
            $args['codeconfig_notice'] = 'activated';
        }

        if ('deactivate' === $action) {
            $id = (int) $_POST['id'];
            CodeConfig_Version_DB::update_version($id, array( 'is_active' => 0 ));
            $args['codeconfig_notice'] = 'deactivated';
        }

        if ('delete' === $action) {
            $id = (int) $_POST['id'];
            $version = CodeConfig_Version_DB::get_existing_version_by_id($id);
            if ($version && ! empty($version['download_path']) && file_exists($version['download_path'])) {
                unlink($version['download_path']);
            }
            // Only delete media attachment when explicitly deleting the version
            if ($version && ! empty($version['attachment_id'])) {
                wp_delete_attachment((int) $version['attachment_id'], true);
            }
            CodeConfig_Version_DB::delete_version($id);
            $args['codeconfig_notice'] = 'deleted';
        }

        if ('edit' === $action) {
            $id = (int) $_POST['id'];
            $new_version = sanitize_text_field($_POST['version']);
            $new_slug = sanitize_text_field($_POST['slug']);
            $new_changelog = wp_kses_post($_POST['changelog'] ?? '');

            $existing = CodeConfig_Version_DB::get_existing_version_by_id($id);

            if (empty($new_slug) && $existing) {
                $new_slug = $existing['slug'];
            }
            if (empty($new_version) && $existing) {
                $new_version = $existing['version'];
            }

            $update_data = array();

            if ($new_version) {
                $update_data['version'] = $new_version;
            }
            if ($new_slug) {
                $update_data['slug'] = $new_slug;
            }
            if (isset($_POST['changelog'])) {
                $update_data['changelog'] = $new_changelog;
            }

            if (! empty($_FILES['plugin_zip']['name'])) {
                require_once ABSPATH . 'wp-admin/includes/file.php';

                $override_upload_dir = function ($dirs) {
                    $dirs['path'] = CODECONFIG_API_STORAGE;
                    $dirs['url']  = CODECONFIG_API_STORAGE_URL;
                    $dirs['subdir']  = '';
                    return $dirs;
                };

                add_filter('upload_dir', $override_upload_dir);
                $upload = wp_handle_upload($_FILES['plugin_zip'], array( 'test_form' => false ));
                remove_filter('upload_dir', $override_upload_dir);

                if (isset($upload['error'])) {
                    $args['codeconfig_notice'] = 'upload_failed';
                    nocache_headers();
                    wp_redirect(add_query_arg($args, admin_url('admin.php')), 303);
                    exit;
                }

                $ext = strtolower(pathinfo($_FILES['plugin_zip']['name'], PATHINFO_EXTENSION));
                if ('zip' === $ext) {
                    if ($existing && ! empty($existing['download_path']) && file_exists($existing['download_path'])) {
                        unlink($existing['download_path']);
                    }

                    $rename = CODECONFIG_API_STORAGE . $new_slug . '-v' . $new_version . '.zip';

                    if (file_exists($upload['file'])) {
                        if (file_exists($rename)) {
                            unlink($rename);
                        }
                        rename($upload['file'], $rename);
                        $update_data['download_path'] = $rename;
                    }
                }
            }

            if (! empty($update_data)) {
                CodeConfig_Version_DB::update_version($id, $update_data);
            }

            $args['codeconfig_notice'] = 'updated';
            $args['version'] = $new_version;
        }

        nocache_headers();
        wp_redirect(add_query_arg($args, admin_url('admin.php')), 303);
        exit;
    }

    public static function render_page()
    {

        $selected_slug = isset($_GET['filter_slug']) ? sanitize_text_field($_GET['filter_slug']) : 'all';
        $versions      = $selected_slug === 'all' ? CodeConfig_Version_DB::get_all_versions() : CodeConfig_Version_DB::get_all_versions($selected_slug);
        $all_slugs     = CodeConfig_Version_DB::get_unique_slugs();

        self::maybe_show_notice();
        ?>
		<div class="wrap">
			<h1>CodeConfig Update Manager</h1>

			<div class="codeconfig-upload-metabox closed">
				<div class="postbox-header">
					<h2 class="handle"><span>Add New Version</span></h2>
					<div class="handle-actions">
						<button type="button" class="handlediv codeconfig-toggle-btn" aria-expanded="false">
							<span class="screen-reader-text">Toggle panel: Add New Version</span>
							<span class="toggle-indicator" aria-hidden="true"></span>
						</button>
					</div>
				</div>
				<div class="inside">
					<form id="codeconfig-add-version-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<?php wp_nonce_field('codeconfig_admin_action', 'codeconfig_nonce'); ?>
						<input type="hidden" name="action" value="codeconfig_versions_action" />
						<input type="hidden" name="codeconfig_action" value="add_version" />
						<input type="hidden" id="media_attachment_id" name="media_attachment_id" value="" />

					<div class="codeconfig-upload-grid">
						<div class="codeconfig-upload-zone codeconfig-drop-zone" id="codeconfig-drop-zone">
							<div class="codeconfig-drop-zone-inner">
								<span class="dashicons dashicons-upload"></span>
								<p class="codeconfig-drop-text">Drag & Drop your ZIP file here</p>
								<p class="codeconfig-drop-or">- OR -</p>
								<input type="file" id="plugin_zip" name="plugin_zip" accept=".zip" />
							</div>
							<button type="button" id="codeconfig-select-media" class="codeconfig-text-link">
								Select from Media Library
							</button>
							<div id="codeconfig-detect-status" class="codeconfig-detect-status"></div>
						</div>

						<div class="codeconfig-upload-fields">
							<div class="codeconfig-field-row">
								<label for="version" class="codeconfig-field-label">Version</label>
								<div class="codeconfig-field-wrapper" id="version-wrapper">
									<input type="text" id="version" name="version" class="regular-text" placeholder="e.g. 1.1.0" />
									<span class="codeconfig-detected-badge" style="display:none;"></span>
									<button type="button" class="codeconfig-edit-toggle" style="display:none;"><?php esc_html_e('Edit'); ?></button>
								</div>
							</div>

							<div class="codeconfig-field-row">
								<label for="slug" class="codeconfig-field-label">Plugin Slug</label>
								<div class="codeconfig-field-wrapper" id="slug-wrapper">
									<input type="text" id="slug" name="slug" class="regular-text" placeholder="e.g. codeconfig-plugin" />
									<span class="codeconfig-detected-badge" style="display:none;"></span>
									<button type="button" class="codeconfig-edit-toggle" style="display:none;"><?php esc_html_e('Edit'); ?></button>
								</div>
							</div>

							<div class="codeconfig-field-row">
								<label for="changelog" class="codeconfig-field-label">Changelog</label>
								<textarea id="changelog" name="changelog" rows="4" class="large-text" placeholder="What's new in this version..."></textarea>
							</div>
						</div>
					</div>

					<?php submit_button('Upload', 'primary', 'submit_btn', true, array( 'name' => 'submit_btn' )); ?>
				</form>
			</div>
		</div>

	<hr />

	<div class="codeconfig-section-header">
		<h2>Version History</h2>
	</div>

	<div class="codeconfig-table-controls">
		<div class="codeconfig-bulk-actions-top">
			<div class="bulkactions">
				<label for="bulk-action-selector" class="screen-reader-text">Select bulk action</label>
				<select name="bulk_action" id="bulk-action-selector">
					<option value="">Bulk Actions</option>
					<option value="activate">Activate</option>
					<option value="deactivate">Deactivate</option>
					<option value="delete">Delete</option>
				</select>
				<button type="button" class="button" id="doaction" onclick="confirmBulkAction();">Apply</button>
			</div>
		</div>

		<form method="get" class="codeconfig-filter-form">
			<input type="hidden" name="page" value="codeconfig-api" />
			<label for="filter_slug">Filter by Plugin:</label>
			<select name="filter_slug" id="filter_slug" onchange="this.form.submit()">
				<option value="all"<?php selected($selected_slug, "all"); ?>>All Plugins</option>
				<?php foreach ($all_slugs as $s) : ?>
					<option value="<?php echo esc_attr($s); ?>"<?php selected($selected_slug, $s); ?>><?php echo esc_html($s); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ($selected_slug !== 'all') : ?>
				<a href="<?php echo esc_url(admin_url('admin.php?page=codeconfig-api')); ?>" class="button">Clear</a>
			<?php endif; ?>
		</form>
	</div>

	<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="codeconfig-table-form">
		<?php wp_nonce_field('codeconfig_admin_action', 'codeconfig_nonce'); ?>
		<input type="hidden" name="action" value="codeconfig_versions_action" />
		<input type="hidden" name="codeconfig_action" id="bulk-action-type" value="" />

		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="cb-select-all" /></th>
					<th>ID</th>
					<th>File</th>
					<th>Version</th>
					<th>Slug</th>
					<th>Active</th>
					<th>Downloads</th>
					<th>Date</th>
				</tr>
			</thead>
			<tbody>
				<?php if (empty($versions)) : ?>
					<tr><td colspan="8">No versions uploaded yet.</td></tr>
				<?php else : ?>
					<?php foreach ($versions as $v) : ?>
						<?php
                        $file_exists = ! empty($v['download_path']) && file_exists($v['download_path']);
					    $file_display = $file_exists ? basename($v['download_path']) : 'File not found';
					    $file_color = $file_exists ? '' : 'color: #d63638;';
					    $is_active = $v['is_active'] && $file_exists;
					    $download_url = CODECONFIG_API_URL . 'download.php?id=' . (int) $v['id'];
					    ?>
						<tr>
							<th scope="row" class="check-column"><input type="checkbox" name="bulk_ids[]" value="<?php echo (int) $v['id']; ?>" /></th>
							<td><?php echo esc_html($v['id']); ?></td>
							<td style="<?php echo esc_attr($file_color); ?>">
								<?php echo esc_html($file_display); ?>
								<div class="row-actions">
									<?php if ($file_exists) : ?>
										<a href="<?php echo $download_url; ?>" class="button-link" target="_blank">Download</a>
										|
										<button type="button" class="button-link codeconfig-edit-btn" data-id="<?php echo (int) $v['id']; ?>" data-version="<?php echo esc_attr($v['version']); ?>" data-slug="<?php echo esc_attr($v['slug']); ?>">Edit</button>
										|
										<button type="button" class="button-link codeconfig-single-action-btn" data-action="<?php echo $is_active ? 'deactivate' : 'activate'; ?>" data-id="<?php echo (int) $v['id']; ?>">
											<?php echo $is_active ? 'Deactivate' : 'Activate'; ?>
										</button>
										|
										<button type="button" class="button-link codeconfig-single-action-btn" data-action="delete" data-id="<?php echo (int) $v['id']; ?>">Delete</button>
									<?php else : ?>
										<button type="button" class="button-link codeconfig-edit-btn" data-id="<?php echo (int) $v['id']; ?>" data-version="<?php echo esc_attr($v['version']); ?>" data-slug="<?php echo esc_attr($v['slug']); ?>">Edit</button>
										|
										<button type="button" class="button-link codeconfig-single-action-btn" data-action="delete" data-id="<?php echo (int) $v['id']; ?>">Delete</button>
									<?php endif; ?>
								</div>
							</td>
							<td><?php echo esc_html($v['version']); ?></td>
							<td><?php echo esc_html($v['slug']); ?></td>
							<td><?php echo $is_active ? '<span class="codeconfig-status-active">Active</span>' : '<span class="codeconfig-status-inactive">Inactive</span>'; ?></td>
							<td>
								<?php
					            $download_count = 0;
					    if (class_exists('CodeConfig_Analytics_DB')) {
					        global $wpdb;
					        $analytics_table = $wpdb->prefix . 'codeconfig_analytics';
					        $download_count = (int) $wpdb->get_var(
					            $wpdb->prepare(
					                "SELECT COUNT(*) FROM {$analytics_table} WHERE slug = %s AND version = %s AND request_type = 'download'",
					                $v['slug'],
					                $v['version']
					            )
					        );
					    }
					    echo esc_html($download_count);
					    ?>
							</td>
							<td><?php echo esc_html($v['created_at']); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</form>

	<form id="codeconfig-single-action-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:none;">
		<?php wp_nonce_field('codeconfig_admin_action', 'codeconfig_nonce'); ?>
		<input type="hidden" name="action" value="codeconfig_versions_action" />
		<input type="hidden" name="codeconfig_action" id="single-action-type" value="" />
		<input type="hidden" name="id" id="single-action-id" value="" />
	</form>
	<div id="codeconfig-edit-form" style="display:none;">
		<div class="card">
			<h3>Edit Version</h3>
			<form id="codeconfig-edit-version-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
				<?php wp_nonce_field('codeconfig_admin_action', 'codeconfig_nonce'); ?>
				<input type="hidden" name="action" value="codeconfig_versions_action" />
				<input type="hidden" name="codeconfig_action" value="edit" />
				<input type="hidden" id="edit_id" name="id" value="" />

				<table class="form-table">
					<tr>
						<th><label for="edit_version">Version</label></th>
						<td><input type="text" id="edit_version" name="version" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="edit_slug">Plugin Slug</label></th>
						<td><input type="text" id="edit_slug" name="slug" class="regular-text" /></td>
					</tr>
					<tr>
						<th><label for="edit_changelog">Changelog</label></th>
						<td><textarea id="edit_changelog" name="changelog" rows="4" class="large-text"></textarea></td>
					</tr>
					<tr>
						<th><label for="edit_zip">Replace ZIP (optional)</label></th>
						<td><input type="file" id="edit_zip" name="plugin_zip" accept=".zip" /> <p class="description">Leave empty to keep the current file.</p></td>
					</tr>
				</table>

				<p>
					<?php submit_button('Save Changes', 'primary', 'submit', false); ?>
					<button type="button" id="codeconfig-cancel-edit" class="button">Cancel</button>
				</p>
			</form>
		</div>
	</div>
	</div>
	<?php
    }

    private static function handle_file_upload($has_file, $has_attachment, $attachment_id)
    {
        if ($has_file) {
            $ext = strtolower(pathinfo($_FILES['plugin_zip']['name'], PATHINFO_EXTENSION));
            if ('zip' !== $ext) {
                return false;
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';

            $override_upload_dir = function ($dirs) {
                $dirs['path']   = CODECONFIG_API_STORAGE;
                $dirs['url']    = CODECONFIG_API_STORAGE_URL;
                $dirs['subdir'] = '';
                return $dirs;
            };

            add_filter('upload_dir', $override_upload_dir);
            $upload = wp_handle_upload($_FILES['plugin_zip'], array( 'test_form' => false ));
            remove_filter('upload_dir', $override_upload_dir);

            if (isset($upload['error'])) {
                return false;
            }

            return str_replace('//', '/', $upload['file']);
        }

        if ($has_attachment) {
            $source_path = get_attached_file($attachment_id);
            if ($source_path && file_exists($source_path)) {
                $copied_file = str_replace('//', '/', CODECONFIG_API_STORAGE . basename($source_path));
                copy($source_path, $copied_file);
                return $copied_file;
            }
        }

        return false;
    }

    private static function maybe_show_notice()
    {
        // Check for version deactivation notification
        $deactivated = get_transient('codeconfig_version_deactivated');
        if ($deactivated) {
            delete_transient('codeconfig_version_deactivated');
            echo '<div class="notice notice-warning is-dismissible"><p>';
            printf(
                '⚠️ Plugin file not found for version %s. Version deactivated automatically.',
                esc_html($deactivated['version'] ?? 'unknown')
            );
            echo '</p></div>';
        }

        if (! isset($_GET['codeconfig_notice'])) {
            return;
        }

        $notice  = sanitize_text_field($_GET['codeconfig_notice']);
        $type    = 'info';
        $message = '';

        switch ($notice) {
            case 'success':
                $type    = 'success';
                $version = isset($_GET['version']) ? sanitize_text_field($_GET['version']) : '';
                $message = sprintf('Version %s uploaded. Activate it below.', $version);
                break;
            case 'updated':
                $type    = 'success';
                $version = isset($_GET['version']) ? sanitize_text_field($_GET['version']) : '';
                $message = $version ? sprintf('Version %s updated.', $version) : 'Version updated.';
                break;
            case 'activated':
                $type    = 'success';
                $message = 'Version activated.';
                break;
            case 'deactivated':
                $type    = 'success';
                $message = 'Version deactivated.';
                break;
            case 'deleted':
                $type    = 'success';
                $message = 'Version deleted.';
                break;
            case 'bulk_activate':
                $type    = 'success';
                $message = 'Selected versions activated.';
                break;
            case 'bulk_deactivate':
                $type    = 'success';
                $message = 'Selected versions deactivated.';
                break;
            case 'bulk_delete':
                $type    = 'success';
                $message = 'Selected versions deleted.';
                break;
            case 'upload_error':
                $type    = 'error';
                $message = 'Please upload a ZIP file.';
                break;
            case 'not_zip':
                $type    = 'error';
                $message = 'Only ZIP files are allowed.';
                break;
            case 'upload_failed':
                $type    = 'error';
                $message = 'Upload failed. Please try again.';
                break;
            case 'parse_failed':
                $type    = 'error';
                $message = 'Could not detect version or slug from the ZIP file. Please fill in manually.';
                break;
            case 'no_items_selected':
                $type    = 'error';
                $message = 'Please select at least one version for bulk actions.';
                break;
        }

        if ($message) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message)
            );
        }
    }

    public static function render_latest_urls_page()
    { ?>
		<div class="wrap">
			<h1>Latest Version Download URLs</h1>
			<p>Share these URLs - they always point to the latest active version for each plugin:</p>

			<?php
            $all_slugs = CodeConfig_Version_DB::get_unique_slugs();
        $site_url = untrailingslashit(get_site_url());

        if (empty($all_slugs)) : ?>
				<p>No plugins found. Upload a version first.</p>
			<?php else : ?>
				<table class="wp-list-table widefat striped">
					<thead>
						<tr>
							<th>Plugin Slug</th>
							<th>Latest Version</th>
							<th>Download URL</th>
							<th>Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($all_slugs as $slug) : ?>
							<?php
                        $latest = CodeConfig_Version_DB::get_active_version($slug);
						    $version = $latest ? $latest['version'] : 'N/A';
						    $url = $site_url . '/wp-json/codeconfig/v1/latest-download?slug=' . urlencode($slug);
						    ?>
							<tr>
								<td><strong><?php echo esc_html($slug); ?></strong></td>
								<td><?php echo esc_html($version); ?></td>
								<td>
									<input type="text" id="latest-url-<?php echo esc_attr($slug); ?>" value="<?php echo esc_url($url); ?>" class="regular-text" readonly onclick="this.select();" />
								</td>
								<td>
									<button type="button" class="button" onclick="copyLatestUrl('<?php echo esc_js($slug); ?>')">Copy URL</button>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<hr />
			<p class="description">Add these URLs to your frontend for initial plugin installation. The URLs always serve the active version.</p>
		</div>

		<script>
			function copyLatestUrl(slug) {
				var input = document.getElementById('latest-url-' + slug);
				input.select();
				document.execCommand('copy');
				alert('URL copied to clipboard!');
			}
		</script>
		<?php
    }
}
