<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MyPlugin_Admin {

	public static function add_menu() {
		add_menu_page(
			'MyPlugin Versions',
			'MyPlugin API',
			'manage_options',
			'myplugin-api',
			array( __CLASS__, 'render_page' ),
			'dashicons-update',
			30
		);
	}

	public static function enqueue_assets( $hook ) {

		if ( 'toplevel_page_myplugin-api' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'myplugin-admin-css',
			MYPLUGIN_API_URL . 'assets/css/admin.css',
			array(),
			MYPLUGIN_API_VERSION
		);

		wp_enqueue_script(
			'myplugin-admin-js',
			MYPLUGIN_API_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			MYPLUGIN_API_VERSION,
			true
		);

		wp_localize_script( 'myplugin-admin-js', 'mypluginAdmin', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'myplugin_admin_nonce' ),
			'confirmUpdate' => 'This version already exists. Do you want to update it?',
		) );
	}

	public static function ajax_check_version() {

		check_ajax_referer( 'myplugin_admin_nonce', 'nonce' );

		$version = sanitize_text_field( $_POST['version'] ?? '' );
		$slug    = sanitize_text_field( $_POST['slug'] ?? 'my-plugin' );

		$existing = MyPlugin_Version_DB::get_existing_version( $slug, $version );

		wp_send_json_success( array(
			'exists' => $existing ? true : false,
			'id'     => $existing ? $existing['id'] : 0,
		) );
	}

	public static function register_actions() {
		add_action( 'admin_post_myplugin_versions_action', array( __CLASS__, 'handle_form_submit' ) );
	}

	public static function handle_form_submit() {

		if ( ! check_admin_referer( 'myplugin_admin_action', 'myplugin_nonce' ) ) {
			wp_die( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}

		$action = sanitize_text_field( $_POST['myplugin_action'] );
		$args   = array( 'page' => 'myplugin-api' );

		if ( 'add_version' === $action ) {
			$version      = sanitize_text_field( $_POST['version'] );
			$slug         = sanitize_text_field( $_POST['slug'] );
			$changelog    = wp_kses_post( $_POST['changelog'] ?? '' );

			if ( empty( $_FILES['plugin_zip']['name'] ) ) {
				$args['myplugin_notice'] = 'upload_error';
			} else {
				$ext = strtolower( pathinfo( $_FILES['plugin_zip']['name'], PATHINFO_EXTENSION ) );
				if ( 'zip' !== $ext ) {
					$args['myplugin_notice'] = 'not_zip';
					wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
					exit;
				}

				if ( ! is_dir( MYPLUGIN_API_STORAGE ) ) {
					wp_mkdir_p( MYPLUGIN_API_STORAGE );
				}

				require_once ABSPATH . 'wp-admin/includes/file.php';
				$upload = wp_handle_upload( $_FILES['plugin_zip'], array( 'test_form' => false ) );

				if ( isset( $upload['error'] ) ) {
					$args['myplugin_notice'] = 'upload_failed';
				} else {
					$rename = MYPLUGIN_API_STORAGE . $slug . '-v' . $version . '.zip';

					if ( file_exists( $upload['file'] ) && $upload['file'] !== $rename ) {
						if ( file_exists( $rename ) ) {
							unlink( $rename );
						}
						rename( $upload['file'], $rename );
					} else {
						$rename = $upload['file'];
					}

					$existing = MyPlugin_Version_DB::get_existing_version( $slug, $version );

					if ( $existing ) {
						if ( ! empty( $existing['download_path'] ) && file_exists( $existing['download_path'] ) ) {
							unlink( $existing['download_path'] );
						}
						MyPlugin_Version_DB::update_version( $existing['id'], array(
							'changelog'     => $changelog,
							'download_path' => $rename,
							'is_active'     => 1,
						) );
						$args['myplugin_notice'] = 'updated';
					} else {
						MyPlugin_Version_DB::insert_version( array(
							'version'     => $version,
							'slug'        => $slug,
							'changelog'   => $changelog,
							'download_path' => $rename,
							'is_active'   => 1,
						) );
						$args['myplugin_notice'] = 'success';
					}
					$args['version'] = $version;
				}
			}
		}

		if ( 'activate' === $action ) {
			$id = (int) $_POST['id'];
			MyPlugin_Version_DB::update_version( $id, array( 'is_active' => 1 ) );
			$args['myplugin_notice'] = 'activated';
		}

		if ( 'deactivate' === $action ) {
			$id = (int) $_POST['id'];
			MyPlugin_Version_DB::update_version( $id, array( 'is_active' => 0 ) );
			$args['myplugin_notice'] = 'deactivated';
		}

		if ( 'delete' === $action ) {
			$id = (int) $_POST['id'];
			MyPlugin_Version_DB::delete_version( $id );
			$args['myplugin_notice'] = 'deleted';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_page() {

		$selected_slug = isset( $_GET['filter_slug'] ) ? sanitize_text_field( $_GET['filter_slug'] ) : 'all';
		$versions      = $selected_slug === 'all' ? MyPlugin_Version_DB::get_all_versions() : MyPlugin_Version_DB::get_all_versions( $selected_slug );
		$all_slugs     = MyPlugin_Version_DB::get_unique_slugs();

		self::maybe_show_notice();
		?>
		<div class="wrap">
			<h1>MyPlugin Update Manager</h1>

			<h2>Add New Version</h2>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'myplugin_admin_action', 'myplugin_nonce' ); ?>
				<input type="hidden" name="action" value="myplugin_versions_action" />
				<input type="hidden" name="myplugin_action" value="add_version" />

				<table class="form-table">
					<tr>
						<th><label for="version">Version</label></th>
						<td><input type="text" id="version" name="version" class="regular-text" required placeholder="e.g. 1.1.0" /></td>
					</tr>
					<tr>
						<th><label for="slug">Plugin Slug</label></th>
						<td><input type="text" id="slug" name="slug" class="regular-text" value="my-plugin" required /></td>
					</tr>
					<tr>
						<th><label for="changelog">Changelog</label></th>
						<td><textarea id="changelog" name="changelog" rows="4" class="large-text" placeholder="What's new in this version..."></textarea></td>
					</tr>
					<tr>
						<th><label for="plugin_zip">Plugin ZIP</label></th>
						<td><input type="file" id="plugin_zip" name="plugin_zip" accept=".zip" required /></td>
					</tr>
				</table>

				<?php submit_button( 'Upload & Activate' ); ?>
			</form>

			<hr />

			<h2>Version History</h2>

			<form method="get" class="myplugin-filter-form" style="margin-bottom:15px;">
				<input type="hidden" name="page" value="myplugin-api" />
				<label for="filter_slug">Filter by Plugin:</label>
				<select name="filter_slug" id="filter_slug" onchange="this.form.submit()">
					<option value="all"<?php selected( $selected_slug, 'all' ); ?>>All Plugins</option>
					<?php foreach ( $all_slugs as $s ) : ?>
						<option value="<?php echo esc_attr( $s ); ?>"<?php selected( $selected_slug, $s ); ?>><?php echo esc_html( $s ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $selected_slug !== 'all' ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=myplugin-api' ) ); ?>" class="button" style="vertical-align:top;">Clear Filter</a>
				<?php endif; ?>
			</form>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Version</th>
						<th>Slug</th>
						<th>Changelog</th>
						<th>File</th>
						<th>Active</th>
						<th>Date</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $versions ) ) : ?>
						<tr><td colspan="8">No versions uploaded yet.</td></tr>
					<?php else : ?>
						<?php foreach ( $versions as $v ) : ?>
							<tr>
								<td><?php echo esc_html( $v['id'] ); ?></td>
								<td><?php echo esc_html( $v['version'] ); ?></td>
								<td><?php echo esc_html( $v['slug'] ); ?></td>
								<td><?php echo esc_html( wp_trim_words( $v['changelog'] ?? '', 10 ) ); ?></td>
								<td><?php echo esc_html( basename( $v['download_path'] ?? '' ) ); ?></td>
								<td><?php echo $v['is_active'] ? '<span class="myplugin-status-active">Active</span>' : '<span class="myplugin-status-inactive">Inactive</span>'; ?></td>
								<td><?php echo esc_html( $v['created_at'] ); ?></td>
								<td>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'myplugin_admin_action', 'myplugin_nonce' ); ?>
										<input type="hidden" name="action" value="myplugin_versions_action" />
										<?php if ( ! $v['is_active'] ) : ?>
											<input type="hidden" name="myplugin_action" value="activate" />
											<input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>" />
											<button type="submit" class="button button-small">Activate</button>
										<?php else : ?>
											<input type="hidden" name="myplugin_action" value="deactivate" />
											<input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>" />
											<button type="submit" class="button button-small">Deactivate</button>
										<?php endif; ?>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'myplugin_admin_action', 'myplugin_nonce' ); ?>
										<input type="hidden" name="action" value="myplugin_versions_action" />
										<input type="hidden" name="myplugin_action" value="delete" />
										<input type="hidden" name="id" value="<?php echo (int) $v['id']; ?>" />
										<button type="submit" class="button button-small" onclick="return confirm('Delete this version?');">Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	private static function maybe_show_notice() {

		if ( ! isset( $_GET['myplugin_notice'] ) ) {
			return;
		}

		$notice  = sanitize_text_field( $_GET['myplugin_notice'] );
		$type    = 'info';
		$message = '';

		switch ( $notice ) {
			case 'success':
				$type    = 'success';
				$version = isset( $_GET['version'] ) ? sanitize_text_field( $_GET['version'] ) : '';
				$message = sprintf( 'Version %s uploaded and activated.', $version );
				break;
			case 'updated':
				$type    = 'success';
				$version = isset( $_GET['version'] ) ? sanitize_text_field( $_GET['version'] ) : '';
				$message = sprintf( 'Version %s updated.', $version );
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
		}

		if ( $message ) {
			printf(
				'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
				esc_attr( $type ),
				esc_html( $message )
			);
		}
	}
}
