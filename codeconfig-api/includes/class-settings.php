<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CodeConfig_Settings {

	const OPTION_GROUP = 'codeconfig_settings_group';
	const OPTION_NAME  = 'codeconfig_settings';

	public static function add_menu() {
		add_submenu_page(
			'codeconfig-api',
			'CodeConfig Settings',
			'Settings',
			'manage_options',
			'codeconfig-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
			)
		);

		add_settings_section(
			'codeconfig_general_section',
			'General Settings',
			'__return_empty_string',
			'codeconfig-settings'
		);

		add_settings_field(
			'auto_create_users',
			'Auto-create users on update check',
			array( __CLASS__, 'render_checkbox' ),
			'codeconfig-settings',
			'codeconfig_general_section',
			array(
				'label_for' => 'auto_create_users',
				'description' => 'Automatically create a user when a new domain makes an update check request.',
			)
		);

		add_settings_field(
			'new_user_default_status',
			'Default status for new users',
			array( __CLASS__, 'render_select' ),
			'codeconfig-settings',
			'codeconfig_general_section',
			array(
				'label_for' => 'new_user_default_status',
				'options'   => array(
					'1' => 'Active',
					'0' => 'Inactive',
				),
				'description' => 'Set the default active status for users created via update check.',
			)
		);

		add_settings_field(
			'require_name',
			'Require name field',
			array( __CLASS__, 'render_checkbox' ),
			'codeconfig-settings',
			'codeconfig_general_section',
			array(
				'label_for' => 'require_name',
				'description' => 'Make name a required field when auto-creating users.',
			)
		);

		add_settings_field(
			'require_email',
			'Require email field',
			array( __CLASS__, 'render_checkbox' ),
			'codeconfig-settings',
			'codeconfig_general_section',
			array(
				'label_for' => 'require_email',
				'description' => 'Make email a required field when auto-creating users.',
			)
		);

		add_settings_section(
			'codeconfig_headless_section',
			'Headless Mode',
			'__return_empty_string',
			'codeconfig-settings'
		);

		add_settings_field(
			'headless_enabled',
			'Enable Headless Mode',
			array( __CLASS__, 'render_checkbox' ),
			'codeconfig-settings',
			'codeconfig_headless_section',
			array(
				'label_for' => 'headless_enabled',
				'description' => 'Disable WordPress frontend. Only API and admin panel will be accessible.',
			)
		);

		add_settings_field(
			'headless_behavior',
			'Frontend Behavior',
			array( __CLASS__, 'render_select' ),
			'codeconfig-settings',
			'codeconfig_headless_section',
			array(
				'label_for' => 'headless_behavior',
				'options'   => array(
					'404' => 'Return 404',
					'message' => 'Show Custom Message',
					'redirect' => 'Redirect to API Docs',
					'custom_url' => 'Redirect to Custom URL',
				),
				'description' => 'What to show when frontend is accessed.',
			)
		);

		add_settings_field(
			'headless_redirect_url',
			'Custom Redirect URL',
			array( __CLASS__, 'render_text_input' ),
			'codeconfig-settings',
			'codeconfig_headless_section',
			array(
				'label_for'   => 'headless_redirect_url',
				'placeholder' => 'https://your-api-docs.com',
				'description' => 'URL to redirect to when "Redirect to Custom URL" is selected.',
				'show_if'     => 'custom_url',
			)
		);

		add_settings_field(
			'headless_message',
			'Custom Message',
			array( __CLASS__, 'render_text_input' ),
			'codeconfig-settings',
			'codeconfig_headless_section',
			array(
				'label_for'   => 'headless_message',
				'placeholder' => 'This site is running in headless mode.',
				'description' => 'Message to display when frontend is blocked (used with "Show Custom Message" option).',
				'show_if'     => 'message',
			)
		);

		add_settings_field(
			'headless_allowed_routes',
			'Allowed Routes',
			array( __CLASS__, 'render_text_input' ),
			'codeconfig-settings',
			'codeconfig_headless_section',
			array(
				'label_for' => 'headless_allowed_routes',
				'placeholder' => '/wp-json/, /wp-admin/, /xmlrpc.php',
				'description' => 'Comma-separated list of routes to still allow. Default: /wp-json/, /wp-admin/, /xmlrpc.php',
			)
		);

		add_settings_field(
			'headless_keep_feeds',
			'Keep RSS/Atom Feeds',
			array( __CLASS__, 'render_checkbox' ),
			'codeconfig-settings',
			'codeconfig_headless_section',
			array(
				'label_for' => 'headless_keep_feeds',
				'description' => 'Allow RSS and Atom feeds to remain accessible.',
			)
		);
	}

	public static function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['auto_create_users']        = isset( $input['auto_create_users'] ) ? 1 : 0;
		$sanitized['new_user_default_status']  = isset( $input['new_user_default_status'] ) ? (int) $input['new_user_default_status'] : 1;
		$sanitized['require_name']              = isset( $input['require_name'] ) ? 1 : 0;
		$sanitized['require_email']             = isset( $input['require_email'] ) ? 1 : 0;

		$sanitized['headless_enabled']         = isset( $input['headless_enabled'] ) ? 1 : 0;
		$sanitized['headless_behavior']        = isset( $input['headless_behavior'] ) ? sanitize_text_field( $input['headless_behavior'] ) : '404';
		$sanitized['headless_message']          = isset( $input['headless_message'] ) ? sanitize_text_field( $input['headless_message'] ) : '';
		$sanitized['headless_allowed_routes']  = isset( $input['headless_allowed_routes'] ) ? sanitize_text_field( $input['headless_allowed_routes'] ) : '/wp-json/, /wp-admin/, /xmlrpc.php';
		$sanitized['headless_keep_feeds']      = isset( $input['headless_keep_feeds'] ) ? 1 : 0;
		$sanitized['headless_redirect_url']   = isset( $input['headless_redirect_url'] ) ? esc_url_raw( $input['headless_redirect_url'] ) : '';

		return $sanitized;
	}

	public static function render_checkbox( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : 0;
		?>
		<input
			type="checkbox"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
			value="1"
			<?php checked( $value, 1 ); ?>
		/>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif;
	}

	public static function render_select( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : 1;
		?>
		<select
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
		>
			<?php foreach ( $args['options'] as $option_value => $option_label ) : ?>
				<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $value, $option_value ); ?>>
					<?php echo esc_html( $option_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif;
	}

	public static function render_text_input( $args ) {
		$options = get_option( self::OPTION_NAME, array() );
		$value   = isset( $options[ $args['label_for'] ] ) ? $options[ $args['label_for'] ] : '';
		
		$conditional_class = '';
		$show_if = isset( $args['show_if'] ) ? $args['show_if'] : '';
		if ( ! empty( $show_if ) ) {
			$conditional_class = 'codeconfig-conditional-field';
		}
		?>
		<input
			type="text"
			id="<?php echo esc_attr( $args['label_for'] ); ?>"
			name="<?php echo esc_attr( self::OPTION_NAME ); ?>[<?php echo esc_attr( $args['label_for'] ); ?>]"
			class="regular-text <?php echo esc_attr( $conditional_class ); ?>"
			data-show-if="<?php echo esc_attr( $show_if ); ?>"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="<?php echo isset( $args['placeholder'] ) ? esc_attr( $args['placeholder'] ) : ''; ?>"
		/>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description <?php echo esc_attr( $conditional_class ); ?>" data-show-if="<?php echo esc_attr( $show_if ); ?>"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif;
	}

	public static function render_page() {
		?>
		<div class="wrap">
			<h1>CodeConfig Settings</h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'codeconfig-settings' );
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>

		<script>
		(function() {
			var behaviorSelect = document.getElementById('headless_behavior');
			if (!behaviorSelect) return;

			function toggleFields() {
				var value = behaviorSelect.value;
				var conditionals = document.querySelectorAll('[data-show-if]');
				conditionals.forEach(function(el) {
					var showIf = el.getAttribute('data-show-if');
					var parentRow = el.closest('tr');
					if (!parentRow) return;
					
					if (showIf === '') {
						parentRow.style.display = 'table-row';
					} else if (showIf === value) {
						parentRow.style.display = 'table-row';
					} else {
						parentRow.style.display = 'none';
					}
				});
			}

			behaviorSelect.addEventListener('change', toggleFields);
			toggleFields();
		})();
		</script>
		<?php
	}

	public static function get_settings() {
		$defaults = array(
			'auto_create_users'        => 1,
			'new_user_default_status'  => 1,
			'require_name'              => 0,
			'require_email'             => 0,
			'headless_enabled'          => 0,
			'headless_behavior'         => '404',
			'headless_message'          => '',
			'headless_allowed_routes'   => '/wp-json/, /wp-admin/, /xmlrpc.php',
			'headless_keep_feeds'       => 0,
			'headless_redirect_url'    => '',
		);

		$options = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( $options, $defaults );
	}

	public static function is_headless_mode() {
		$settings = self::get_settings();
		return ! empty( $settings['headless_enabled'] );
	}
}