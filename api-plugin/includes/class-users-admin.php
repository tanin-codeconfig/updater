<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MyPlugin_Users_Admin {

	public static function add_menu() {
		add_submenu_page(
			'myplugin-api',
			'MyPlugin Users',
			'Users',
			'manage_options',
			'myplugin-users',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_actions() {
		add_action( 'admin_post_myplugin_users_action', array( __CLASS__, 'handle_form_submit' ) );
	}

	public static function handle_form_submit() {

		if ( ! check_admin_referer( 'myplugin_users_action', 'myplugin_users_nonce' ) ) {
			wp_die( 'Security check failed.' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Permission denied.' );
		}

		$action = sanitize_text_field( $_POST['myplugin_users_action'] );
		$args   = array( 'page' => 'myplugin-users' );

		if ( 'add_user' === $action ) {
			$name   = sanitize_text_field( $_POST['name'] );
			$email  = sanitize_email( $_POST['email'] );
			$domain = isset( $_POST['domain'] ) ? esc_url_raw( $_POST['domain'] ) : '';

			if ( empty( $name ) || empty( $email ) ) {
				$args['myplugin_users_notice'] = 'missing_fields';
			} else {
				MyPlugin_Users_DB::insert_user( array(
					'name'      => $name,
					'email'     => $email,
					'domain'    => $domain,
					'is_active' => 1,
				) );
				$args['myplugin_users_notice'] = 'user_added';
			}
		}

		if ( 'activate' === $action ) {
			$id = (int) $_POST['id'];
			MyPlugin_Users_DB::update_user( $id, array( 'is_active' => 1 ) );
			$args['myplugin_users_notice'] = 'user_activated';
		}

		if ( 'deactivate' === $action ) {
			$id = (int) $_POST['id'];
			MyPlugin_Users_DB::update_user( $id, array( 'is_active' => 0 ) );
			$args['myplugin_users_notice'] = 'user_deactivated';
		}

		if ( 'delete_user' === $action ) {
			$id = (int) $_POST['id'];
			MyPlugin_Users_DB::delete_user( $id );
			$args['myplugin_users_notice'] = 'user_deleted';
		}

		if ( 'edit_user' === $action ) {
			$id    = (int) $_POST['id'];
			$name  = sanitize_text_field( $_POST['name'] );
			$email = sanitize_email( $_POST['email'] );
			$domain = isset( $_POST['domain'] ) ? esc_url_raw( $_POST['domain'] ) : '';

			if ( empty( $name ) || empty( $email ) ) {
				$args['myplugin_users_notice'] = 'missing_fields';
			} else {
				MyPlugin_Users_DB::update_user( $id, array(
					'name'   => $name,
					'email'  => $email,
					'domain' => $domain,
				) );
				$args['myplugin_users_notice'] = 'user_updated';
			}
		}

		if ( 'regenerate_key' === $action ) {
			$id = (int) $_POST['id'];
			$new_key = MyPlugin_Users_DB::generate_api_key();
			MyPlugin_Users_DB::update_user( $id, array( 'api_key' => $new_key ) );
			$args['myplugin_users_notice'] = 'key_regenerated';
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_page() {

		$search    = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';
		$is_active = isset( $_GET['is_active'] ) ? sanitize_text_field( $_GET['is_active'] ) : '';
		$paged     = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page  = 20;
		$offset    = ( $paged - 1 ) * $per_page;

		$filter_args = array(
			'search'    => $search,
			'is_active' => '' !== $is_active ? (int) $is_active : null,
			'limit'     => $per_page,
			'offset'    => $offset,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
		);

		$users       = MyPlugin_Users_DB::get_users_filtered( $filter_args );
		$total_users = MyPlugin_Users_DB::get_users_count( $filter_args );
		$total_pages = ceil( $total_users / $per_page );

		self::maybe_show_notice();
		?>
		<div class="wrap">
			<h1>MyPlugin — Access Users</h1>

			<h2>Add New User</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'myplugin_users_action', 'myplugin_users_nonce' ); ?>
				<input type="hidden" name="action" value="myplugin_users_action" />
				<input type="hidden" name="myplugin_users_action" value="add_user" />

				<table class="form-table">
					<tr>
						<th><label for="user_name">Name</label></th>
						<td><input type="text" id="user_name" name="name" class="regular-text" required placeholder="Client or site name" /></td>
					</tr>
					<tr>
						<th><label for="user_email">Email</label></th>
						<td><input type="email" id="user_email" name="email" class="regular-text" required /></td>
					</tr>
					<tr>
						<th><label for="user_domain">Domain (optional)</label></th>
						<td><input type="url" id="user_domain" name="domain" class="regular-text" placeholder="https://client-site.com" /></td>
					</tr>
				</table>

				<?php submit_button( 'Add User' ); ?>
			</form>

			<hr />

			<h2>Users List</h2>

			<form method="get" action="">
				<input type="hidden" name="page" value="myplugin-users" />
				<p class="search-box">
					<label for="user-search">Search Users:</label>
					<input type="search" id="user-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Name or email..." />
					<select name="is_active" style="margin-left:10px;">
						<option value="">All Statuses</option>
						<option value="1" <?php selected( $is_active, '1' ); ?>>Active</option>
						<option value="0" <?php selected( $is_active, '0' ); ?>>Inactive</option>
					</select>
					<button type="submit" class="button">Filter</button>
					<?php if ( $search || '' !== $is_active ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=myplugin-users' ) ); ?>" class="button">Clear</a>
					<?php endif; ?>
				</p>
			</form>

			<?php
			$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
			$edit_user = $edit_id ? MyPlugin_Users_DB::get_user( $edit_id ) : null;
			if ( $edit_user ) :
			?>
			<div id="myplugin-edit-user-form" style="background:#f9f9f9;padding:15px;margin-bottom:20px;border:1px solid #ccc;">
				<h3>Edit User</h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'myplugin_users_action', 'myplugin_users_nonce' ); ?>
					<input type="hidden" name="action" value="myplugin_users_action" />
					<input type="hidden" name="myplugin_users_action" value="edit_user" />
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
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=myplugin-users' ) ); ?>" class="button">Cancel</a>
				</form>
			</div>
			<?php endif; ?>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>ID</th>
						<th>Name</th>
						<th>Email</th>
						<th>API Key</th>
						<th>Domain</th>
						<th>Status</th>
						<th>Created</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $users ) ) : ?>
						<tr><td colspan="8">No users added yet.</td></tr>
					<?php else : ?>
						<?php foreach ( $users as $u ) : ?>
							<tr>
								<td><?php echo esc_html( $u['id'] ); ?></td>
								<td><?php echo esc_html( $u['name'] ); ?></td>
								<td><?php echo esc_html( $u['email'] ); ?></td>
								<td>
									<code class="myplugin-api-key" id="api-key-<?php echo (int) $u['id']; ?>"><?php echo esc_html( substr( $u['api_key'], 0, 12 ) ) . '...'; ?></code>
									<button type="button" class="button button-small myplugin-copy-key" data-key="<?php echo esc_attr( $u['api_key'] ); ?>">Copy</button>
								</td>
								<td><?php echo esc_html( $u['domain'] ?? '—' ); ?></td>
								<td><?php echo $u['is_active'] ? '<span class="myplugin-status-active">Active</span>' : '<span class="myplugin-status-inactive">Inactive</span>'; ?></td>
								<td><?php echo esc_html( $u['created_at'] ); ?></td>
								<td>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=myplugin-users&edit=' . (int) $u['id'] ) ); ?>" class="button button-small">Edit</a>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'myplugin_users_action', 'myplugin_users_nonce' ); ?>
										<input type="hidden" name="action" value="myplugin_users_action" />
										<input type="hidden" name="myplugin_users_action" value="regenerate_key" />
										<input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>" />
										<button type="submit" class="button button-small" onclick="return confirm('Regenerate API key? Old key will stop working.');">Regen Key</button>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'myplugin_users_action', 'myplugin_users_nonce' ); ?>
										<input type="hidden" name="action" value="myplugin_users_action" />
										<?php if ( $u['is_active'] ) : ?>
											<input type="hidden" name="myplugin_users_action" value="deactivate" />
											<input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>" />
											<button type="submit" class="button button-small">Deactivate</button>
										<?php else : ?>
											<input type="hidden" name="myplugin_users_action" value="activate" />
											<input type="hidden" name="id" value="<?php echo (int) $u['id']; ?>" />
											<button type="submit" class="button button-small">Activate</button>
										<?php endif; ?>
									</form>
									<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
										<?php wp_nonce_field( 'myplugin_users_action', 'myplugin_users_nonce' ); ?>
										<input type="hidden" name="action" value="myplugin_users_action" />
										<input type="hidden" name="myplugin_users_action" value="delete_user" />
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
		</div>

		<script>
		jQuery( document ).ready( function( $ ) {
			$( '.myplugin-copy-key' ).on( 'click', function() {
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
		} );
		</script>
		<?php
	}

	private static function maybe_show_notice() {

		if ( ! isset( $_GET['myplugin_users_notice'] ) ) {
			return;
		}

		$notice  = sanitize_text_field( $_GET['myplugin_users_notice'] );
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
