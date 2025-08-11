<?php
/**
 * Admin Settings Class
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Phynite_Signup_Form_Admin {

	/**
	 * Settings group name
	 */
	private $settings_group = 'phynite_signup_form_settings';

	/**
	 * Settings option name
	 */
	private $settings_option = 'phynite_signup_form_settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Constructor intentionally left empty
	}

	/**
	 * Add admin menu
	 */
	public function add_menu() {
		add_options_page(
			__( 'Phynite Signup Form Settings', 'phynite-signup-form' ),
			__( 'Phynite Signup', 'phynite-signup-form' ),
			'manage_options',
			'phynite-signup-form',
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Initialize admin settings
	 */
	public function init() {
		// Register settings
		register_setting(
			$this->settings_group,
			$this->settings_option,
			array( $this, 'sanitize_settings' )
		);

		// Add settings sections
		add_settings_section(
			'phynite_api_settings',
			__( 'API Configuration', 'phynite-signup-form' ),
			array( $this, 'api_section_callback' ),
			'phynite-signup-form'
		);

		add_settings_section(
			'phynite_form_settings',
			__( 'Form Configuration', 'phynite-signup-form' ),
			array( $this, 'form_section_callback' ),
			'phynite-signup-form'
		);

		add_settings_section(
			'phynite_security_settings',
			__( 'Security Settings', 'phynite-signup-form' ),
			array( $this, 'security_section_callback' ),
			'phynite-signup-form'
		);

		// Add settings fields
		$this->add_settings_fields();

		// Enqueue admin assets
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add settings fields
	 */
	private function add_settings_fields() {
		// API Configuration Fields
		add_settings_field(
			'api_key',
			__( 'Stewie API Key', 'phynite-signup-form' ),
			array( $this, 'api_key_field' ),
			'phynite-signup-form',
			'phynite_api_settings'
		);

		add_settings_field(
			'stewie_url',
			__( 'Stewie API URL', 'phynite-signup-form' ),
			array( $this, 'stewie_url_field' ),
			'phynite-signup-form',
			'phynite_api_settings'
		);

		add_settings_field(
			'environment',
			__( 'Environment', 'phynite-signup-form' ),
			array( $this, 'environment_field' ),
			'phynite-signup-form',
			'phynite_api_settings'
		);

		add_settings_field(
			'stripe_publishable_key',
			__( 'Stripe Publishable Key', 'phynite-signup-form' ),
			array( $this, 'stripe_publishable_key_field' ),
			'phynite-signup-form',
			'phynite_api_settings'
		);

		// Form Configuration Fields
		add_settings_field(
			'form_style',
			__( 'Form Style', 'phynite-signup-form' ),
			array( $this, 'form_style_field' ),
			'phynite-signup-form',
			'phynite_form_settings'
		);

		add_settings_field(
			'primary_color',
			__( 'Primary Color', 'phynite-signup-form' ),
			array( $this, 'primary_color_field' ),
			'phynite-signup-form',
			'phynite_form_settings'
		);

		add_settings_field(
			'show_terms_links',
			__( 'Show Terms & Privacy Links', 'phynite-signup-form' ),
			array( $this, 'show_terms_links_field' ),
			'phynite-signup-form',
			'phynite_form_settings'
		);

		// Security Settings Fields
		add_settings_field(
			'rate_limit',
			__( 'Rate Limit (per minute)', 'phynite-signup-form' ),
			array( $this, 'rate_limit_field' ),
			'phynite-signup-form',
			'phynite_security_settings'
		);

		add_settings_field(
			'enable_logging',
			__( 'Enable Debug Logging', 'phynite-signup-form' ),
			array( $this, 'enable_logging_field' ),
			'phynite-signup-form',
			'phynite_security_settings'
		);

		add_settings_field(
			'allowed_domains',
			__( 'Allowed Referrer Domains', 'phynite-signup-form' ),
			array( $this, 'allowed_domains_field' ),
			'phynite-signup-form',
			'phynite_security_settings'
		);
	}

	/**
	 * Settings page callback
	 */
	public function settings_page() {
		// Check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have sufficient permissions to access this page.', 'phynite-signup-form' ) );
		}

		// Test API connection if requested
		if ( isset( $_GET['test_api'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'test_api_connection' ) ) {
			$this->test_api_connection();
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php _e( 'Settings saved successfully!', 'phynite-signup-form' ); ?></p>
				</div>
			<?php endif; ?>
			
			<div class="phynite-admin-container">
				<div class="phynite-main-content">
					<form method="post" action="options.php">
						<?php
						settings_fields( $this->settings_group );
						do_settings_sections( 'phynite-signup-form' );
						submit_button();
						?>
					</form>
					
					<hr>
					
					<h2><?php _e( 'API Connection Test', 'phynite-signup-form' ); ?></h2>
					<p><?php _e( 'Test your API configuration to ensure it\'s working correctly.', 'phynite-signup-form' ); ?></p>
					<a href="<?php echo wp_nonce_url( admin_url( 'options-general.php?page=phynite-signup-form&test_api=1' ), 'test_api_connection' ); ?>" 
						class="button button-secondary">
						<?php _e( 'Test API Connection', 'phynite-signup-form' ); ?>
					</a>
				</div>
				
				<div class="phynite-sidebar">
					<div class="phynite-widget">
						<h3><?php _e( 'Quick Setup Guide', 'phynite-signup-form' ); ?></h3>
						<ol>
							<li><?php _e( 'Enter your Stewie API key', 'phynite-signup-form' ); ?></li>
							<li><?php _e( 'Configure your environment (dev/prod)', 'phynite-signup-form' ); ?></li>
							<li><?php _e( 'Customize form appearance', 'phynite-signup-form' ); ?></li>
							<li><?php _e( 'Test the API connection', 'phynite-signup-form' ); ?></li>
							<li><?php _e( 'Add the signup form block to your pages', 'phynite-signup-form' ); ?></li>
						</ol>
					</div>
					
					<div class="phynite-widget">
						<h3><?php _e( 'Need Help?', 'phynite-signup-form' ); ?></h3>
						<p><?php _e( 'Visit our documentation for detailed setup instructions and troubleshooting tips.', 'phynite-signup-form' ); ?></p>
						<a href="https://phynitesolutions.com/docs/wordpress-plugin" target="_blank" class="button">
							<?php _e( 'View Documentation', 'phynite-signup-form' ); ?>
						</a>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Section callbacks
	 */
	public function api_section_callback() {
		echo '<p>' . __( 'Configure your Stewie API connection settings.', 'phynite-signup-form' ) . '</p>';
	}

	public function form_section_callback() {
		echo '<p>' . __( 'Customize the appearance and behavior of your signup forms.', 'phynite-signup-form' ) . '</p>';
	}

	public function security_section_callback() {
		echo '<p>' . __( 'Configure security and rate limiting options.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Settings field callbacks
	 */
	public function api_key_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['api_key'] ) ? $settings['api_key'] : '';

		echo '<input type="password" id="api_key" name="' . $this->settings_option . '[api_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<button type="button" class="button button-secondary" onclick="togglePasswordVisibility(\'api_key\')">' . __( 'Show', 'phynite-signup-form' ) . '</button>';
		echo '<p class="description">' . __( 'Your Stewie API key (starts with phyn_). Keep this secure and never share it publicly.', 'phynite-signup-form' ) . '</p>';
	}

	public function stewie_url_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['stewie_url'] ) ? $settings['stewie_url'] : 'https://api.phynitesolutions.com';

		echo '<input type="url" name="' . $this->settings_option . '[stewie_url]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . __( 'The base URL for your Stewie API instance.', 'phynite-signup-form' ) . '<br>';
		echo __( 'Production: https://api.phynitesolutions.com', 'phynite-signup-form' ) . '<br>';
		echo __( 'Development: http://localhost:4000', 'phynite-signup-form' ) . '</p>';
	}

	public function environment_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['environment'] ) ? $settings['environment'] : 'production';

		$options = array(
			'production'  => __( 'Production', 'phynite-signup-form' ),
			'staging'     => __( 'Staging', 'phynite-signup-form' ),
			'development' => __( 'Development', 'phynite-signup-form' ),
		);

		echo '<select name="' . $this->settings_option . '[environment]">';
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . __( 'Select the environment this plugin is running in.', 'phynite-signup-form' ) . '</p>';
	}

	public function stripe_publishable_key_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['stripe_publishable_key'] ) ? $settings['stripe_publishable_key'] : '';

		echo '<input type="text" id="stripe_publishable_key" name="' . $this->settings_option . '[stripe_publishable_key]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="pk_test_... or pk_live_..." />';
		echo '<p class="description">' . __( 'Your Stripe publishable key (starts with pk_test_ or pk_live_). This is safe to expose publicly.', 'phynite-signup-form' ) . '<br>';
		echo __( 'Test key: pk_test_51MAK49HmpGLWBl6CORQXcxeZ9VKVcjlLQbGdVEv7wbQHKZVVJbAKKEOJzZbnyQbE6F8Mg3vbSRvkK1y6JjAHdjb900VLZlgKVB', 'phynite-signup-form' ) . '<br>';
		echo __( 'Live key: pk_live_51MAK49HmpGLWBl6Cl6iP3K2l5T5JAW9KivwmLrzprIYrOqtnwwyEBgOZVLJNbUO2ISfYpwMgugndk4XAsE52YOm9006dXtkc9f', 'phynite-signup-form' ) . '</p>';
	}

	public function form_style_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['form_style'] ) ? $settings['form_style'] : 'default';

		$options = array(
			'default' => __( 'Default', 'phynite-signup-form' ),
			'minimal' => __( 'Minimal', 'phynite-signup-form' ),
			'modern'  => __( 'Modern', 'phynite-signup-form' ),
			'compact' => __( 'Compact', 'phynite-signup-form' ),
		);

		echo '<select name="' . $this->settings_option . '[form_style]">';
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . __( 'Choose the visual style for your signup forms.', 'phynite-signup-form' ) . '</p>';
	}

	public function primary_color_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['primary_color'] ) ? $settings['primary_color'] : '#007cba';

		echo '<input type="color" name="' . $this->settings_option . '[primary_color]" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . __( 'Primary color for buttons and form elements.', 'phynite-signup-form' ) . '</p>';
	}

	public function show_terms_links_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['show_terms_links'] ) ? $settings['show_terms_links'] : true;

		echo '<label><input type="checkbox" name="' . $this->settings_option . '[show_terms_links]" value="1"' . checked( $value, true, false ) . ' /> ';
		echo __( 'Show links to Terms of Service and Privacy Policy', 'phynite-signup-form' ) . '</label>';
		echo '<p class="description">' . __( 'Display links to your terms and privacy policy in the form.', 'phynite-signup-form' ) . '</p>';
	}

	public function rate_limit_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['rate_limit'] ) ? $settings['rate_limit'] : 5;

		echo '<input type="number" min="1" max="60" name="' . $this->settings_option . '[rate_limit]" value="' . esc_attr( $value ) . '" class="small-text" />';
		echo '<p class="description">' . __( 'Maximum number of form submissions allowed per IP address per minute.', 'phynite-signup-form' ) . '</p>';
	}

	public function enable_logging_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['enable_logging'] ) ? $settings['enable_logging'] : false;

		echo '<label><input type="checkbox" name="' . $this->settings_option . '[enable_logging]" value="1"' . checked( $value, true, false ) . ' /> ';
		echo __( 'Enable debug logging for troubleshooting', 'phynite-signup-form' ) . '</label>';
		echo '<p class="description">' . __( 'Log API requests and responses for debugging. Disable in production.', 'phynite-signup-form' ) . '</p>';
	}

	public function allowed_domains_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['allowed_domains'] ) ? $settings['allowed_domains'] : '';

		echo '<textarea name="' . $this->settings_option . '[allowed_domains]" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . __( 'One domain per line. Leave blank to allow all domains. Example: yourdomain.com', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Sanitize settings
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Sanitize API key
		if ( isset( $input['api_key'] ) ) {
			$sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
		}

		// Sanitize Stewie URL (allow HTTP for localhost development)
		if ( isset( $input['stewie_url'] ) ) {
			$url = esc_url_raw( $input['stewie_url'] );
			// Allow HTTP for localhost development
			if ( strpos( $url, 'http://localhost' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
				$sanitized['stewie_url'] = $url;
			} else {
				$sanitized['stewie_url'] = 'https://api.phynitesolutions.com'; // Default fallback
			}
		}

		// Sanitize environment
		if ( isset( $input['environment'] ) ) {
			$allowed_environments     = array( 'production', 'staging', 'development' );
			$sanitized['environment'] = in_array( $input['environment'], $allowed_environments ) ? $input['environment'] : 'production';
		}

		// Sanitize Stripe publishable key
		if ( isset( $input['stripe_publishable_key'] ) ) {
			$key = sanitize_text_field( $input['stripe_publishable_key'] );
			// Validate that it's a proper Stripe publishable key format
			if ( empty( $key ) || preg_match( '/^pk_(test_|live_)[a-zA-Z0-9]+$/', $key ) ) {
				$sanitized['stripe_publishable_key'] = $key;
			}
		}

		// Sanitize form style
		if ( isset( $input['form_style'] ) ) {
			$allowed_styles          = array( 'default', 'minimal', 'modern', 'compact' );
			$sanitized['form_style'] = in_array( $input['form_style'], $allowed_styles ) ? $input['form_style'] : 'default';
		}

		// Sanitize primary color
		if ( isset( $input['primary_color'] ) ) {
			$sanitized['primary_color'] = sanitize_hex_color( $input['primary_color'] );
		}

		// Sanitize boolean fields
		$sanitized['show_terms_links'] = isset( $input['show_terms_links'] ) && $input['show_terms_links'] === '1';
		$sanitized['enable_logging']   = isset( $input['enable_logging'] ) && $input['enable_logging'] === '1';

		// Sanitize rate limit
		if ( isset( $input['rate_limit'] ) ) {
			$sanitized['rate_limit'] = max( 1, min( 60, intval( $input['rate_limit'] ) ) );
		}

		// Sanitize allowed domains
		if ( isset( $input['allowed_domains'] ) ) {
			$domains           = array_filter( array_map( 'trim', explode( "\n", $input['allowed_domains'] ) ) );
			$sanitized_domains = array();
			foreach ( $domains as $domain ) {
				$domain = sanitize_text_field( $domain );
				if ( filter_var( 'http://' . $domain, FILTER_VALIDATE_URL ) ) {
					$sanitized_domains[] = $domain;
				}
			}
			$sanitized['allowed_domains'] = implode( "\n", $sanitized_domains );
		}

		return $sanitized;
	}

	/**
	 * Test API connection
	 */
	private function test_api_connection() {
		$api    = new Phynite_Signup_Form_API();
		$result = $api->test_connection();

		if ( $result['success'] ) {
			add_action(
				'admin_notices',
				function () {
					echo '<div class="notice notice-success is-dismissible">';
					echo '<p>' . __( 'API connection test successful!', 'phynite-signup-form' ) . '</p>';
					echo '</div>';
				}
			);
		} else {
			add_action(
				'admin_notices',
				function () use ( $result ) {
					echo '<div class="notice notice-error is-dismissible">';
					echo '<p>' . sprintf( __( 'API connection failed: %s', 'phynite-signup-form' ), esc_html( $result['message'] ) ) . '</p>';
					echo '</div>';
				}
			);
		}
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== 'settings_page_phynite-signup-form' ) {
			return;
		}

		wp_enqueue_style(
			'phynite-signup-form-admin',
			PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/css/admin-styles.css',
			array(),
			PHYNITE_SIGNUP_FORM_VERSION
		);

		wp_enqueue_script(
			'phynite-signup-form-admin',
			PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/js/admin.js',
			array( 'jquery' ),
			PHYNITE_SIGNUP_FORM_VERSION,
			true
		);
	}
}