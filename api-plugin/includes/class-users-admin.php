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

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render_page() {

		$users = MyPlugin_Users_DB::get_all_users();

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
