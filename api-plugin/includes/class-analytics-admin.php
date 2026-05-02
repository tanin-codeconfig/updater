<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MyPlugin_Analytics_Admin {

	public static function add_menu() {
		add_submenu_page(
			'myplugin-api',
			'MyPlugin Analytics',
			'Analytics',
			'manage_options',
			'myplugin-analytics',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function render_page() {
		$range = isset( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '30days';

		switch ( $range ) {
			case '7days':
				$start = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );
				break;
			case '90days':
				$start = gmdate( 'Y-m-d H:i:s', strtotime( '-90 days' ) );
				break;
			case 'today':
				$start = gmdate( 'Y-m-d 00:00:00' );
				break;
			default:
				$start = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days' ) );
				break;
		}

		$stats = MyPlugin_Analytics_DB::get_stats( array( 'start_date' => $start ) );
		?>
		<div class="wrap">
			<h1>MyPlugin Analytics</h1>

			<form method="get" action="" style="margin-bottom:20px;">
				<input type="hidden" name="page" value="myplugin-analytics" />
				<select name="range" onchange="this.form.submit()">
					<option value="today" <?php selected( $range, 'today' ); ?>>Today</option>
					<option value="7days" <?php selected( $range, '7days' ); ?>>Last 7 Days</option>
					<option value="30days" <?php selected( $range, '30days' ); ?>>Last 30 Days</option>
					<option value="90days" <?php selected( $range, '90days' ); ?>>Last 90 Days</option>
				</select>
			</form>

			<div style="display:flex; gap:20px; margin-bottom:20px;">
				<div class="card" style="flex:1; padding:15px;">
					<h3>Update Checks</h3>
					<p style="font-size:32px; font-weight:bold; margin:0;"><?php echo (int) $stats['total_update_checks']; ?></p>
				</div>
				<div class="card" style="flex:1; padding:15px;">
					<h3>Downloads</h3>
					<p style="font-size:32px; font-weight:bold; margin:0;"><?php echo (int) $stats['total_downloads']; ?></p>
				</div>
				<div class="card" style="flex:1; padding:15px;">
					<h3>Total Requests</h3>
					<p style="font-size:32px; font-weight:bold; margin:0;"><?php echo (int) $stats['total_update_checks'] + (int) $stats['total_downloads']; ?></p>
				</div>
			</div>

			<h2>Top Plugins</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Plugin Slug</th>
						<th>Requests</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $stats['by_slug'] ) ) : ?>
						<tr><td colspan="2">No data yet.</td></tr>
					<?php else : ?>
						<?php foreach ( $stats['by_slug'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['slug'] ); ?></td>
								<td><?php echo (int) $row['count']; ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<h2 style="margin-top:30px;">Recent Activity</h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>Time</th>
						<th>User</th>
						<th>Type</th>
						<th>Slug</th>
						<th>Version</th>
						<th>IP Address</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $stats['recent_activity'] ) ) : ?>
						<tr><td colspan="6">No activity yet.</td></tr>
					<?php else : ?>
						<?php foreach ( $stats['recent_activity'] as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row['created_at'] ); ?></td>
								<td><?php echo esc_html( $row['user_name'] ?? $row['api_key'] ); ?></td>
								<td><?php echo esc_html( ucfirst( $row['request_type'] ) ); ?></td>
								<td><?php echo esc_html( $row['slug'] ); ?></td>
								<td><?php echo esc_html( $row['version'] ?? '—' ); ?></td>
								<td><?php echo esc_html( $row['ip_address'] ?? '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
