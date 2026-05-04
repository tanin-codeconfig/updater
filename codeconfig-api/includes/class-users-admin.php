<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeConfig_Users_Admin {

	public static function add_menu() {
		add_submenu_page(
			'codeconfig-api',
			'CodeConfig Users',
			'Users',
			'manage_options',
			'codeconfig-users',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_actions() {
		add_action( 'admin_post_codeconfig_users_action', array( __CLASS__, 'handle_form_submit' ) );
	}

	private static function generate_name_from_domain( $domain ) {
		$parsed = wp_parse_url( $domain );
		$host   = isset( $parsed['host'] ) ? $parsed['host'] : $domain;
		$host   = preg_replace( '/^www\./', '', $host );
		$name   = ucwords( sanitize_title( str_replace( array( '-', '_', '.' ), ' ', $host ) ) );
		return $name;
	}

	public static function handle_form_submit() {

		if ( ! check_admin_referer( 'codeconfig_users_action', 'codeconfig_users_nonce' ) ) {
			wp_die( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}

		$action = sanitize_text_field( $_POST['codeconfig_users_action'] );
		$args   = array( 'page' => 'codeconfig-users' );

		if ( in_array( $action, array( 'bulk_activate', 'bulk_deactivate', 'bulk_delete' ), true ) && isset( $_POST['bulk_ids'] ) ) {
			$bulk_ids = array_map( 'intval', (array) $_POST['bulk_ids'] );
			$bulk_ids = array_filter( $bulk_ids );

			if ( empty( $bulk_ids ) ) {
				$args['codeconfig_users_notice'] = 'no_items_selected';
			} else {
				foreach ( $bulk_ids as $id ) {
					if ( 'bulk_delete' === $action ) {
						CodeConfig_Users_DB::delete_user( $id );
					} elseif ( 'bulk_activate' === $action ) {
						CodeConfig_Users_DB::update_user( $id, array( 'is_active' => 1 ) );
					} elseif ( 'bulk_deactivate' === $action ) {
						CodeConfig_Users_DB::update_user( $id, array( 'is_active' => 0 ) );
					}
				}
				$args['codeconfig_users_notice'] = 'bulk_' . str_replace( 'bulk_', '', $action );
			}
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}

		if ( 'export_csv' === $action ) {
			self::export_csv();
			exit;
		}

		if ( 'add_user' === $action ) {
			$name   = isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '';
			$email  = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';
			$domain = isset( $_POST['domain'] ) ? esc_url_raw( $_POST['domain'] ) : '';

			if ( empty( $domain ) ) {
				$args['codeconfig_users_notice'] = 'missing_domain';
			} else {
				if ( empty( $name ) && ! empty( $domain ) ) {
					$name = self::generate_name_from_domain( $domain );
				}

				CodeConfig_Users_DB::insert_user( array(
					'name'      => $name,
					'email'     => $email,
					'domain'    => $domain,
					'is_active' => 1,
				) );
				$args['codeconfig_users_notice'] = 'user_added';
			}
		}

		if ( 'activate' === $action ) {
			$id = (int) $_POST['id'];
			CodeConfig_Users_DB::update_user( $id, array( 'is_active' => 1 ) );
			$args['codeconfig_users_notice'] = 'user_activated';
		}

		if ( 'deactivate' === $action ) {
			$id = (int) $_POST['id'];
			CodeConfig_Users_DB::update_user( $id, array( 'is_active' => 0 ) );
			$args['codeconfig_users_notice'] = 'user_deactivated';
		}

		if ( 'delete_user' === $action ) {
			$id = (int) $_POST['id'];
			CodeConfig_Users_DB::delete_user( $id );
			$args['codeconfig_users_notice'] = 'user_deleted';
		}

		if ( 'edit_user' === $action ) {
			$id    = (int) $_POST['id'];
			$name  = sanitize_text_field( $_POST['name'] );
			$email = sanitize_email( $_POST['email'] );
			$domain = isset( $_POST['domain'] ) ? esc_url_raw( $_POST['domain'] ) : '';

			if ( empty( $name ) || empty( $email ) ) {
				$args['codeconfig_users_notice'] = 'missing_fields';
			} else {
				CodeConfig_Users_DB::update_user( $id, array(
					'name'   => $name,
					'email'  => $email,
					'domain' => $domain,
				) );
				$args['codeconfig_users_notice'] = 'user_updated';
			}
		}

		if ( 'regenerate_key' === $action ) {
			$id = (int) $_POST['id'];
			$new_key = CodeConfig_Users_DB::generate_api_key();
			CodeConfig_Users_DB::update_user( $id, array( 'api_key' => $new_key ) );
			$args['codeconfig_users_notice'] = 'key_regenerated';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_page() {

		$search    = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$is_active = isset( $_GET['is_active'] ) ? sanitize_text_field( $_GET['is_active'] ) : '';
		$domain    = isset( $_GET['domain'] ) ? sanitize_text_field( $_GET['domain'] ) : '';
		$paged     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page  = 20;
		$offset    = ( $paged - 1 ) * $per_page;

		$filter_args = array(
			'search'    => $search,
			'is_active' => '' !== $is_active ? (int) $is_active : null,
			'domain'    => $domain,
			'limit'     => $per_page,
			'offset'    => $offset,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
		);

		$users       = CodeConfig_Users_DB::get_users_filtered( $filter_args );
		$total_users = CodeConfig_Users_DB::get_users_count( $filter_args );
		$total_pages = ceil( $total_users / $per_page );
		$all_domains = CodeConfig_Users_DB::get_all_domains();

		self::maybe_show_notice();
		?>
		<div class="wrap">
			<h1>CodeConfig — Access Users</h1>

			<h2>Add New User</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'codeconfig_users_action', 'codeconfig_users_nonce' ); ?>
				<input type="hidden" name="action" value="codeconfig_users_action" />
				<input type="hidden" name="codeconfig_users_action" value="add_user" />

				<table class="form-table">
					<tr>
						<th><label for="user_domain">Domain</label></th>
						<td><input type="url" id="user_domain" name="domain" class="regular-text" placeholder="https://client-site.com" required /></td>
					</tr>
					<tr>
						<th><label for="user_name">Name (optional)</label></th>
						<td><input type="text" id="user_name" name="name" class="regular-text" placeholder="If empty, will be generated from domain" /></td>
					</tr>
					<tr>
						<th><label for="user_email">Email (optional)</label></th>
						<td><input type="email" id="user_email" name="email" class="regular-text" placeholder="Leave empty if not available" /></td>
					</tr>
				</table>

				<?php submit_button( 'Add User' ); ?>
			</form>

			<hr />

			<h2>Users List</h2>

			<form method="get" action="">
				<input type="hidden" name="page" value="codeconfig-users" />
				<p class="search-box">
					<label for="user-search">Search Users:</label>
					<input type="search" id="user-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Name, email, or domain..." />
					<select name="domain" style="margin-left:10px;">
						<option value="">All Domains</option>
						<?php foreach ( $all_domains as $d ) : ?>
							<option value="<?php echo esc_attr( $d ); ?>" <?php selected( $domain, $d ); ?>><?php echo esc_html( $d ); ?></option>
						<?php endforeach; ?>
					</select>
					<select name="is_active" style="margin-left:10px;">
						<option value="">All Statuses</option>
						<option value="1" <?php selected( $is_active, '1' ); ?>>Active</option>
						<option value="0" <?php selected( $is_active, '0' ); ?>>Inactive</option>
					</select>
					<button type="submit" class="button">Filter</button>
					<?php if ( $search || '' !== $is_active || $domain ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=codeconfig-users' ) ); ?>" class="button">Clear</a>
					<?php endif; ?>
				</p>
			</form>

			<?php
			$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
			$edit_user = $edit_id ? CodeConfig_Users_DB::get_user( $edit_id ) : null;
			if ( $edit_user ) :
			?>
			<div id="codeconfig-edit-user-form" style="background:#f9f9f9;padding:15px;margin-bottom:20px;border:1px solid #ccc;">
				<h3>Edit User</h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'codeconfig_users_action', 'codeconfig_users_nonce' ); ?>
					<input type="hidden" name="action" value="codeconfig_users_action" />
					<input type="hidden" name="codeconfig_users_action" value="edit_user" />
					<input type="hidden" name="id" value="<?php echo (int) $edit_user['id']; ?>" />

					<table class="form-table">
						<tr>
							<th><label for="edit_name">Name</label></th>
							<td><input type="text" id="edit_name" name="name" class="regular-text" required value="<?php echo esc_attr( $edit_user['name'] ); ?>" /></td>
						</tr>
						<tr>
							<th><label for="edit_email">Email</label></th>
							<td><input type="email" id="edit_email" name="email" class="regular-text" required value="<?php echo esc_attr( $edit_user['email'] ); ?>" /></td>
						</tr>
						<tr>
							<th><label for="edit_domain">Domain (optional)</label></th>
							<td><input type="url" id="edit_domain" name="domain" class="regular-text" value="<?php echo esc_attr( $edit_user['domain'] ?? '' ); ?>" /></td>
						</tr>
					</table>

					<?php submit_button( 'Update User' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=codeconfig-users' ) ); ?>" class="button">Cancel</a>
				</form>
			</div>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="codeconfig-users-table-form">
				<?php wp_nonce_field( 'codeconfig_users_action', 'codeconfig_users_nonce' ); ?>
				<input type="hidden" name="action" value="codeconfig_users_action" />
				<input type="hidden" name="codeconfig_users_action" id="bulk-action-type" value="" />

				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
						<select name="codeconfig_users_action" id="bulk-action-selector-top">
							<option value="">Bulk Actions</option>
							<option value="bulk_activate">Activate</option>
							<option value="bulk_deactivate">Deactivate</option>
							<option value="bulk_delete">Delete</option>
						</select>
						<button type="submit" class="button action" onclick="return confirmBulkAction();">Apply</button>
					</div>
					<div class="alignright">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
							<?php wp_nonce_field( 'codeconfig_users_action', 'codeconfig_users_nonce' ); ?>
							<input type="hidden" name="action" value="codeconfig_users_action" />
							<input type="hidden" name="codeconfig_users_action" value="export_csv" />
							<button type="submit" class="button">Export CSV</button>
						</form>
					</div>
					<br class="clear" />
				</div>

				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="check-column"><input type="checkbox" id="cb-select-all" /></th>
							<th>ID</th>
							<th>Name</th>
							<th>Email</th>
							<th>API Key</th>
							<th>Domain</th>
							<th>Status</th>
							<th>Created</th>
							<th>Last Used</th>
							<th>Actions</th>
						</tr>
					</thead>
				<tbody>
					<?php if ( empty( $users ) ) : ?>
						<tr><td colspan="10">No users added yet.</td></tr>
					<?php else : ?>
						<?php foreach ( $users as $u ) : ?>
							<tr>
								<th scope="row" class="check-column"><input type="checkbox" name="bulk_ids[]" value="<?php echo (int) $u['id']; ?>" /></th>
								<td><?php echo esc_html( $u['id'] ); ?></td>
								<td><?php echo esc_html( $u['name'] ); ?></td>
								<td><?php echo esc_html( $u['email'] ); ?></td>
								<td>
									<code class="codeconfig-api-key" id="api-key-<?php echo (int) $u['id']; ?>"><?php echo esc_html( substr( $u['api_key'], 0, 12 ) ) . '...'; ?></code>
									<button type="button" class="button button-small codeconfig-copy-key" data-key="<?php echo esc_attr( $u['api_key'] ); ?>">Copy</button>
								</td>
								<td><?php echo esc_html( $u['domain'] ?? '—' ); ?></td>
								<td><?php echo $u['is_active'] ? '<span class="codeconfig-status-active">Active</span>' : '<span class="codeconfig-status-inactive">Inactive</span>'; ?></td>
								<td><?php echo esc_html( $u['created_at'] ); ?></td>
								<td><?php echo $u['last_used_at'] ? esc_html( $u['last_used_at'] ) : '—'; ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=codeconfig-users&edit=' . (int) $u['id'] ) ); ?>" class="button button-small">Edit</a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'codeconfig_users_action', 'codeconfig_users_nonce' ); ?>
										<input type="hidden" name="action" value="codeconfig_users_action" />
										<input type="hidden" name="codeconfig_users_action" value="regenerate_key" />
										<input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>" />
										<button type="submit" class="button button-small" onclick="return confirm('Regenerate API key? Old key will stop working.');">Regen Key</button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'codeconfig_users_action', 'codeconfig_users_nonce' ); ?>
										<input type="hidden" name="action" value="codeconfig_users_action" />
										<?php if ( $u['is_active'] ) : ?>
											<input type="hidden" name="codeconfig_users_action" value="deactivate" />
											<input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>" />
											<button type="submit" class="button button-small">Deactivate</button>
										<?php else : ?>
											<input type="hidden" name="codeconfig_users_action" value="activate" />
											<input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>" />
											<button type="submit" class="button button-small">Activate</button>
										<?php endif; ?>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'codeconfig_users_action', 'codeconfig_users_nonce' ); ?>
										<input type="hidden" name="action" value="codeconfig_users_action" />
										<input type="hidden" name="codeconfig_users_action" value="delete_user" />
										<input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>" />
										<button type="submit" class="button button-small" onclick="return confirm('Delete this user?');">Delete</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
			<div class="tablenav">
				<div class="tablenav-pages">
					<span class="displaying-num"><?php echo (int) $total_users; ?> users</span>
					<span class="pagination-links">
						<?php if ( $paged > 1 ) : ?>
							<a class="prev-page" href="<?php echo esc_url( add_query_arg( array( 'paged' => $paged - 1, 's' => $search, 'is_active' => $is_active ) ) ); ?>">«</a>
						<?php endif; ?>

						Page <?php echo (int) $paged; ?> of <?php echo (int) $total_pages; ?>

						<?php if ( $paged < $total_pages ) : ?>
							<a class="next-page" href="<?php echo esc_url( add_query_arg( array( 'paged' => $paged + 1, 's' => $search, 'is_active' => $is_active ) ) ); ?>">»</a>
						<?php endif; ?>
					</span>
				</div>
			</div>
			<?php endif; ?>
			</form>
		</div>

		<script>
		jQuery( document ).ready( function( $ ) {
			$( '.codeconfig-copy-key' ).on( 'click', function() {
				var key = $( this ).data( 'key' );
				if ( navigator.clipboard ) {
					navigator.clipboard.writeText( key ).then( function() {
						alert( 'API key copied!' );
					} );
				} else {
					var $temp = $( '<input>' );
					$( 'body' ).append( $temp );
					$temp.val( key ).select();
					document.execCommand( 'copy' );
					$temp.remove();
					alert( 'API key copied!' );
				}
			} );

			$( '#cb-select-all' ).on( 'change', function() {
				$( 'input[name="bulk_ids[]"]' ).prop( 'checked', $( this ).prop( 'checked' ) );
			} );
		} );

		function confirmBulkAction() {
			var action = document.getElementById( 'bulk-action-selector-top' ).value;
			if ( ! action ) {
				return false;
			}
			var checked = document.querySelectorAll( 'input[name="bulk_ids[]"]:checked' );
			if ( checked.length === 0 ) {
				alert( 'Please select at least one user.' );
				return false;
			}
			if ( 'bulk_delete' === action ) {
				return confirm( 'Are you sure you want to delete the selected users?' );
			}
			return true;
		}
		</script>
		<?php
	}

	private static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}

		$users = CodeConfig_Users_DB::get_all_users();

		if ( empty( $users ) ) {
			wp_die( 'No users to export.' );
		}

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=codeconfig-users-' . date( 'Y-m-d' ) . '.csv' );

		$output = fopen( 'php://output', 'w' );

		fputcsv( $output, array( 'ID', 'Name', 'Email', 'API Key', 'Domain', 'Status', 'Created', 'Last Used' ) );

		foreach ( $users as $user ) {
			fputcsv( $output, array(
				$user['id'],
				$user['name'],
				$user['email'],
				$user['api_key'],
				$user['domain'] ?? '',
				$user['is_active'] ? 'Active' : 'Inactive',
				$user['created_at'],
				$user['last_used_at'] ?? '',
			) );
		}

		fclose( $output );
		exit;
	}

	private static function maybe_show_notice() {

		if ( ! isset( $_GET['codeconfig_users_notice'] ) ) {
			return;
		}

		$notice  = sanitize_text_field( $_GET['codeconfig_users_notice'] );
		$type    = 'info';
		$message = '';

		switch ( $notice ) {
			case 'user_added':
				$type    = 'success';
				$message = 'User added successfully.';
				break;
			case 'user_updated':
				$type    = 'success';
				$message = 'User updated successfully.';
				break;
			case 'key_regenerated':
				$type    = 'success';
				$message = 'API key regenerated. Old key is no longer valid.';
				break;
			case 'user_activated':
				$type    = 'success';
				$message = 'User activated.';
				break;
			case 'user_deactivated':
				$type    = 'success';
				$message = 'User deactivated.';
				break;
			case 'user_deleted':
				$type    = 'success';
				$message = 'User deleted.';
				break;
			case 'missing_fields':
				$type    = 'error';
				$message = 'Name and email are required.';
				break;
			case 'missing_domain':
				$type    = 'error';
				$message = 'Domain is required.';
				break;
			case 'no_items_selected':
				$type    = 'error';
				$message = 'Please select at least one user.';
				break;
			case 'bulk_activate':
				$type    = 'success';
				$message = 'Selected users activated.';
				break;
			case 'bulk_deactivate':
				$type    = 'success';
				$message = 'Selected users deactivated.';
				break;
			case 'bulk_delete':
				$type    = 'success';
				$message = 'Selected users deleted.';
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
