<?php
/**
 * Admin Settings Class
 *
 * @package PhyniteSignupForm
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin interface for Phynite Signup Form
 */
class Phynite_Signup_Form_Admin {

	/**
	 * Settings group name
	 *
	 * @var string
	 */
	private $settings_group = 'phynite_signup_form_settings';

	/**
	 * Settings option name
	 *
	 * @var string
	 */
	private $settings_option = 'phynite_signup_form_settings';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Constructor intentionally left empty.
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
		// Register settings.
		register_setting(
			$this->settings_group,
			$this->settings_option,
			array( $this, 'sanitize_settings' )
		);

		// Add settings sections.
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

		add_settings_section(
			'phynite_tracking_settings',
			__( 'Analytics & Tracking', 'phynite-signup-form' ),
			array( $this, 'tracking_section_callback' ),
			'phynite-signup-form'
		);

		// Add settings fields.
		$this->add_settings_fields();

		// Enqueue admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Add settings fields
	 */
	private function add_settings_fields() {
		// API Configuration Fields.
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

		// Form Configuration Fields.
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

		// Security Settings Fields.
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

		// Tracking Settings Fields.
		add_settings_field(
			'enable_ga_tracking',
			__( 'Enable Google Analytics', 'phynite-signup-form' ),
			array( $this, 'enable_ga_tracking_field' ),
			'phynite-signup-form',
			'phynite_tracking_settings'
		);

		add_settings_field(
			'ga_measurement_id',
			__( 'GA4 Measurement ID', 'phynite-signup-form' ),
			array( $this, 'ga_measurement_id_field' ),
			'phynite-signup-form',
			'phynite_tracking_settings'
		);

		add_settings_field(
			'enable_fb_tracking',
			__( 'Enable Facebook Pixel', 'phynite-signup-form' ),
			array( $this, 'enable_fb_tracking_field' ),
			'phynite-signup-form',
			'phynite_tracking_settings'
		);

		add_settings_field(
			'fb_pixel_id',
			__( 'Facebook Pixel ID', 'phynite-signup-form' ),
			array( $this, 'fb_pixel_id_field' ),
			'phynite-signup-form',
			'phynite_tracking_settings'
		);
	}

	/**
	 * Settings page callback
	 */
	public function settings_page() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'phynite-signup-form' ) );
		}

		// Test API connection if requested.
		if ( isset( $_GET['test_api'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'test_api_connection' ) ) {
			$this->test_api_connection();
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php if ( isset( $_GET['settings-updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings saved successfully!', 'phynite-signup-form' ); ?></p>
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
					
					<h2><?php esc_html_e( 'API Connection Test', 'phynite-signup-form' ); ?></h2>
					<p><?php esc_html_e( 'Test your API configuration to ensure it\'s working correctly.', 'phynite-signup-form' ); ?></p>
					<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'options-general.php?page=phynite-signup-form&test_api=1' ), 'test_api_connection' ) ); ?>" 
						class="button button-secondary">
						<?php esc_html_e( 'Test API Connection', 'phynite-signup-form' ); ?>
					</a>
				</div>
				
				<div class="phynite-sidebar">
					<div class="phynite-widget">
						<h3><?php esc_html_e( 'Quick Setup Guide', 'phynite-signup-form' ); ?></h3>
						<ol>
							<li><?php esc_html_e( 'Enter your Stewie API key', 'phynite-signup-form' ); ?></li>
							<li><?php esc_html_e( 'Configure your environment (dev/prod)', 'phynite-signup-form' ); ?></li>
							<li><?php esc_html_e( 'Customize form appearance', 'phynite-signup-form' ); ?></li>
							<li><?php esc_html_e( 'Test the API connection', 'phynite-signup-form' ); ?></li>
							<li><?php esc_html_e( 'Add the signup form block to your pages', 'phynite-signup-form' ); ?></li>
						</ol>
					</div>
					
					<div class="phynite-widget">
						<h3><?php esc_html_e( 'Need Help?', 'phynite-signup-form' ); ?></h3>
						<p><?php esc_html_e( 'Visit our documentation for detailed setup instructions and troubleshooting tips.', 'phynite-signup-form' ); ?></p>
						<a href="https://phynitesolutions.com/docs/wordpress-plugin" target="_blank" class="button">
							<?php esc_html_e( 'View Documentation', 'phynite-signup-form' ); ?>
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
		echo '<p>' . esc_html__( 'Configure your Stewie API connection settings.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Form section callback
	 *
	 * Renders the description text for the Form Configuration settings section.
	 */
	public function form_section_callback() {
		echo '<p>' . esc_html__( 'Customize the appearance and behavior of your signup forms.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Security section callback
	 *
	 * Renders the description text for the Security Settings section.
	 */
	public function security_section_callback() {
		echo '<p>' . esc_html__( 'Configure security and rate limiting options.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Tracking section callback
	 *
	 * Renders the description text for the Analytics & Tracking section.
	 */
	public function tracking_section_callback() {
		echo '<p>' . esc_html__( 'Configure Google Analytics and Facebook Pixel tracking for form events and conversions.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Settings field callbacks
	 */
	public function api_key_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['api_key'] ) ? $settings['api_key'] : '';

		echo '<input type="password" id="api_key" name="' . esc_attr( $this->settings_option ) . '[api_key]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<button type="button" class="button button-secondary" onclick="togglePasswordVisibility(\'api_key\')">' . esc_html__( 'Show', 'phynite-signup-form' ) . '</button>';
		echo '<p class="description">' . esc_html__( 'Your Stewie API key (starts with phyn_). Keep this secure and never share it publicly.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Stewie URL field callback
	 *
	 * Renders the input field for the Stewie API base URL setting.
	 */
	public function stewie_url_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['stewie_url'] ) ? $settings['stewie_url'] : 'https://api.phynitesolutions.com';

		echo '<input type="url" name="' . esc_attr( $this->settings_option ) . '[stewie_url]" value="' . esc_attr( $value ) . '" class="regular-text" />';
		echo '<p class="description">' . esc_html__( 'The base URL for your Stewie API instance.', 'phynite-signup-form' ) . '<br>';
		echo esc_html__( 'Production: https://api.phynitesolutions.com', 'phynite-signup-form' ) . '<br>';
		echo esc_html__( 'Development: http://localhost:4000', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Environment field callback
	 *
	 * Renders the select dropdown for environment selection (production, staging, development).
	 */
	public function environment_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['environment'] ) ? $settings['environment'] : 'production';

		$options = array(
			'production'  => __( 'Production', 'phynite-signup-form' ),
			'staging'     => __( 'Staging', 'phynite-signup-form' ),
			'development' => __( 'Development', 'phynite-signup-form' ),
		);

		echo '<select name="' . esc_attr( $this->settings_option ) . '[environment]">';
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Select the environment this plugin is running in.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Stripe publishable key field callback
	 *
	 * Renders the input field for the Stripe publishable key setting with examples.
	 */
	public function stripe_publishable_key_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['stripe_publishable_key'] ) ? $settings['stripe_publishable_key'] : '';

		echo '<input type="text" id="stripe_publishable_key" name="' . esc_attr( $this->settings_option ) . '[stripe_publishable_key]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="pk_test_... or pk_live_..." />';
		echo '<p class="description">' . esc_html__( 'Your Stripe publishable key (starts with pk_test_ or pk_live_). This is safe to expose publicly.', 'phynite-signup-form' ) . '<br>';
		echo esc_html__( 'Test key: pk_test_51MAK49HmpGLWBl6CORQXcxeZ9VKVcjlLQbGdVEv7wbQHKZVVJbAKKEOJzZbnyQbE6F8Mg3vbSRvkK1y6JjAHdjb900VLZlgKVB', 'phynite-signup-form' ) . '<br>';
		echo esc_html__( 'Live key: pk_live_51MAK49HmpGLWBl6Cl6iP3K2l5T5JAW9KivwmLrzprIYrOqtnwwyEBgOZVLJNbUO2ISfYpwMgugndk4XAsE52YOm9006dXtkc9f', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Form style field callback
	 *
	 * Renders the select dropdown for form style selection (default, minimal, modern, compact).
	 */
	public function form_style_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['form_style'] ) ? $settings['form_style'] : 'default';

		$options = array(
			'default' => __( 'Default', 'phynite-signup-form' ),
			'minimal' => __( 'Minimal', 'phynite-signup-form' ),
			'modern'  => __( 'Modern', 'phynite-signup-form' ),
			'compact' => __( 'Compact', 'phynite-signup-form' ),
		);

		echo '<select name="' . esc_attr( $this->settings_option ) . '[form_style]">';
		foreach ( $options as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Choose the visual style for your signup forms.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Primary color field callback
	 *
	 * Renders the color picker input for the primary color setting used in forms.
	 */
	public function primary_color_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['primary_color'] ) ? $settings['primary_color'] : '#007cba';

		echo '<input type="color" name="' . esc_attr( $this->settings_option ) . '[primary_color]" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Primary color for buttons and form elements.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Show terms links field callback
	 *
	 * Renders the checkbox for showing terms of service and privacy policy links in forms.
	 */
	public function show_terms_links_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['show_terms_links'] ) ? $settings['show_terms_links'] : true;

		echo '<label><input type="checkbox" name="' . esc_attr( $this->settings_option ) . '[show_terms_links]" value="1"' . checked( $value, true, false ) . ' /> ';
		echo esc_html__( 'Show links to Terms of Service and Privacy Policy', 'phynite-signup-form' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Display links to your terms and privacy policy in the form.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Rate limit field callback
	 *
	 * Renders the number input for setting the rate limit (submissions per IP per minute).
	 */
	public function rate_limit_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['rate_limit'] ) ? $settings['rate_limit'] : 5;

		echo '<input type="number" min="1" max="60" name="' . esc_attr( $this->settings_option ) . '[rate_limit]" value="' . esc_attr( $value ) . '" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Maximum number of form submissions allowed per IP address per minute.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Enable logging field callback
	 *
	 * Renders the checkbox for enabling debug logging of API requests and responses.
	 */
	public function enable_logging_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['enable_logging'] ) ? $settings['enable_logging'] : false;

		echo '<label><input type="checkbox" name="' . esc_attr( $this->settings_option ) . '[enable_logging]" value="1"' . checked( $value, true, false ) . ' /> ';
		echo esc_html__( 'Enable debug logging for troubleshooting', 'phynite-signup-form' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Log API requests and responses for debugging. Disable in production.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Allowed domains field callback
	 *
	 * Renders the textarea for entering allowed referrer domains (one per line).
	 */
	public function allowed_domains_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['allowed_domains'] ) ? $settings['allowed_domains'] : '';

		echo '<textarea name="' . esc_attr( $this->settings_option ) . '[allowed_domains]" rows="3" class="large-text">' . esc_textarea( $value ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'One domain per line. Leave blank to allow all domains. Example: yourdomain.com', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Enable GA tracking field callback
	 */
	public function enable_ga_tracking_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['enable_ga_tracking'] ) ? $settings['enable_ga_tracking'] : false;

		echo '<label><input type="checkbox" name="' . esc_attr( $this->settings_option ) . '[enable_ga_tracking]" value="1"' . checked( $value, true, false ) . ' /> ';
		echo esc_html__( 'Enable Google Analytics tracking for form events', 'phynite-signup-form' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Track form views, interactions, and conversions in Google Analytics.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * GA4 Measurement ID field callback
	 */
	public function ga_measurement_id_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['ga_measurement_id'] ) ? $settings['ga_measurement_id'] : '';

		echo '<input type="text" name="' . esc_attr( $this->settings_option ) . '[ga_measurement_id]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="G-XXXXXXXXXX" />';
		echo '<p class="description">' . esc_html__( 'Your Google Analytics 4 Measurement ID (starts with G-). Only required if GA tracking is enabled.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Enable FB tracking field callback
	 */
	public function enable_fb_tracking_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['enable_fb_tracking'] ) ? $settings['enable_fb_tracking'] : false;

		echo '<label><input type="checkbox" name="' . esc_attr( $this->settings_option ) . '[enable_fb_tracking]" value="1"' . checked( $value, true, false ) . ' /> ';
		echo esc_html__( 'Enable Facebook Pixel tracking for form events', 'phynite-signup-form' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Track form conversions and create custom audiences in Facebook Ads Manager.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Facebook Pixel ID field callback
	 */
	public function fb_pixel_id_field() {
		$settings = get_option( $this->settings_option, array() );
		$value    = isset( $settings['fb_pixel_id'] ) ? $settings['fb_pixel_id'] : '';

		echo '<input type="text" name="' . esc_attr( $this->settings_option ) . '[fb_pixel_id]" value="' . esc_attr( $value ) . '" class="regular-text" placeholder="1234567890123456" />';
		echo '<p class="description">' . esc_html__( 'Your Facebook Pixel ID (16-digit number). Only required if Facebook tracking is enabled.', 'phynite-signup-form' ) . '</p>';
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Raw input data from settings form.
	 * @return array Sanitized settings data.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		// Sanitize API key.
		if ( isset( $input['api_key'] ) ) {
			$sanitized['api_key'] = sanitize_text_field( $input['api_key'] );
		}

		// Sanitize Stewie URL (allow HTTP for localhost development).
		if ( isset( $input['stewie_url'] ) ) {
			$url = esc_url_raw( $input['stewie_url'] );
			// Allow HTTP for localhost development.
			if ( strpos( $url, 'http://localhost' ) === 0 || strpos( $url, 'https://' ) === 0 ) {
				$sanitized['stewie_url'] = $url;
			} else {
				$sanitized['stewie_url'] = 'https://api.phynitesolutions.com'; // Default fallback.
			}
		}

		// Sanitize environment.
		if ( isset( $input['environment'] ) ) {
			$allowed_environments     = array( 'production', 'staging', 'development' );
			$sanitized['environment'] = in_array( $input['environment'], $allowed_environments, true ) ? $input['environment'] : 'production';
		}

		// Sanitize Stripe publishable key.
		if ( isset( $input['stripe_publishable_key'] ) ) {
			$key = sanitize_text_field( $input['stripe_publishable_key'] );
			// Validate that it's a proper Stripe publishable key format.
			if ( empty( $key ) || preg_match( '/^pk_(test_|live_)[a-zA-Z0-9]+$/', $key ) ) {
				$sanitized['stripe_publishable_key'] = $key;
			}
		}

		// Sanitize form style.
		if ( isset( $input['form_style'] ) ) {
			$allowed_styles          = array( 'default', 'minimal', 'modern', 'compact' );
			$sanitized['form_style'] = in_array( $input['form_style'], $allowed_styles, true ) ? $input['form_style'] : 'default';
		}

		// Sanitize primary color.
		if ( isset( $input['primary_color'] ) ) {
			$sanitized['primary_color'] = sanitize_hex_color( $input['primary_color'] );
		}

		// Sanitize boolean fields.
		$sanitized['show_terms_links'] = isset( $input['show_terms_links'] ) && '1' === $input['show_terms_links'];
		$sanitized['enable_logging']   = isset( $input['enable_logging'] ) && '1' === $input['enable_logging'];

		// Sanitize rate limit.
		if ( isset( $input['rate_limit'] ) ) {
			$sanitized['rate_limit'] = max( 1, min( 60, intval( $input['rate_limit'] ) ) );
		}

		// Sanitize allowed domains.
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

		// Sanitize tracking settings.
		$sanitized['enable_ga_tracking'] = isset( $input['enable_ga_tracking'] ) && '1' === $input['enable_ga_tracking'];
		$sanitized['enable_fb_tracking'] = isset( $input['enable_fb_tracking'] ) && '1' === $input['enable_fb_tracking'];

		// Sanitize GA4 Measurement ID.
		if ( isset( $input['ga_measurement_id'] ) ) {
			$ga_id = sanitize_text_field( $input['ga_measurement_id'] );
			// Validate GA4 format (G-XXXXXXXXXX).
			if ( empty( $ga_id ) || preg_match( '/^G-[A-Z0-9]{10}$/i', $ga_id ) ) {
				$sanitized['ga_measurement_id'] = $ga_id;
			}
		}

		// Sanitize Facebook Pixel ID.
		if ( isset( $input['fb_pixel_id'] ) ) {
			$fb_id = sanitize_text_field( $input['fb_pixel_id'] );
			// Validate FB Pixel format (16-digit number).
			if ( empty( $fb_id ) || preg_match( '/^\d{15,16}$/', $fb_id ) ) {
				$sanitized['fb_pixel_id'] = $fb_id;
			}
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
					echo '<p>' . esc_html__( 'API connection test successful!', 'phynite-signup-form' ) . '</p>';
					echo '</div>';
				}
			);
		} else {
			add_action(
				'admin_notices',
				function () use ( $result ) {
					echo '<div class="notice notice-error is-dismissible">';
					/* translators: %s: error message from API connection test */
					echo '<p>' . sprintf( esc_html__( 'API connection failed: %s', 'phynite-signup-form' ), esc_html( $result['message'] ) ) . '</p>';
					echo '</div>';
				}
			);
		}
	}

	/**
	 * Enqueue admin assets
	 *
	 * @param string $hook The current admin page hook suffix.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_phynite-signup-form' !== $hook ) {
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