<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MyPlugin_Settings {

	const OPTION_GROUP = 'myplugin_settings_group';
	const OPTION_NAME  = 'myplugin_settings';

	public static function add_menu() {
		add_submenu_page(
			'myplugin-api',
			'MyPlugin Settings',
			'Settings',
			'manage_options',
			'myplugin-settings',
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
			'myplugin_general_section',
			'General Settings',
			'__return_empty_string',
			'myplugin-settings'
		);

		add_settings_field(
			'auto_create_users',
			'Auto-create users on update check',
			array( __CLASS__, 'render_checkbox' ),
			'myplugin-settings',
			'myplugin_general_section',
			array(
				'label_for' => 'auto_create_users',
				'description' => 'Automatically create a user when a new domain makes an update check request.',
			)
		);

		add_settings_field(
			'new_user_default_status',
			'Default status for new users',
			array( __CLASS__, 'render_select' ),
			'myplugin-settings',
			'myplugin_general_section',
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
			'myplugin-settings',
			'myplugin_general_section',
			array(
				'label_for' => 'require_name',
				'description' => 'Make name a required field when auto-creating users.',
			)
		);

		add_settings_field(
			'require_email',
			'Require email field',
			array( __CLASS__, 'render_checkbox' ),
			'myplugin-settings',
			'myplugin_general_section',
			array(
				'label_for' => 'require_email',
				'description' => 'Make email a required field when auto-creating users.',
			)
		);
	}

	public static function sanitize_settings( $input ) {
		$sanitized = array();

		$sanitized['auto_create_users']      = isset( $input['auto_create_users'] ) ? 1 : 0;
		$sanitized['new_user_default_status'] = isset( $input['new_user_default_status'] ) ? (int) $input['new_user_default_status'] : 1;
		$sanitized['require_name']            = isset( $input['require_name'] ) ? 1 : 0;
		$sanitized['require_email']           = isset( $input['require_email'] ) ? 1 : 0;

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

	public static function render_page() {
		?>
		<div class="wrap">
			<h1>MyPlugin Settings</h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( 'myplugin-settings' );
				submit_button( 'Save Settings' );
				?>
			</form>
		</div>
		<?php
	}

	public static function get_settings() {
		$defaults = array(
			'auto_create_users'      => 1,
			'new_user_default_status' => 1,
			'require_name'           => 0,
			'require_email'          => 0,
		);

		$options = get_option( self::OPTION_NAME, array() );

		return wp_parse_args( $options, $defaults );
	}
}