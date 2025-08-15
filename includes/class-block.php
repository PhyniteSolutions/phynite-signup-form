<?php
/**
 * Gutenberg Block Class
 *
 * @package PhyniteSignupForm
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Gutenberg block handler for Phynite Signup Form
 */
class Phynite_Signup_Form_Block {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_block_category' ) );
		add_action( 'init', array( $this, 'register_block' ), 20 );
		add_action( 'init', array( $this, 'register_frontend_assets' ), 15 );
	}

	/**
	 * Register frontend assets for editor preview
	 */
	public function register_frontend_assets() {
		// Register frontend script for editor use.
		wp_register_script(
			'phynite-signup-form-frontend',
			PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/js/frontend.js',
			array( 'jquery' ),
			PHYNITE_SIGNUP_FORM_VERSION,
			true
		);

		// Get tracking settings.
		$settings        = get_option( 'phynite_signup_form_settings', array() );
		$tracking_config = array(
			'ga_enabled'        => isset( $settings['enable_ga_tracking'] ) ? $settings['enable_ga_tracking'] : false,
			'ga_measurement_id' => isset( $settings['ga_measurement_id'] ) ? $settings['ga_measurement_id'] : '',
			'fb_enabled'        => isset( $settings['enable_fb_tracking'] ) ? $settings['enable_fb_tracking'] : false,
			'fb_pixel_id'       => isset( $settings['fb_pixel_id'] ) ? $settings['fb_pixel_id'] : '',
			'debug'             => isset( $settings['enable_logging'] ) ? $settings['enable_logging'] : false,
		);

		// Localize frontend script with data.
		wp_localize_script(
			'phynite-signup-form-frontend',
			'phyniteSignupForm',
			array(
				'apiUrl'               => rest_url( 'phynite-signup/v1/' ),
				'nonce'                => wp_create_nonce( 'wp_rest' ),
				'isEditor'             => defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_GET['context'] ) && 'edit' === $_GET['context'],
				'tracking'             => $tracking_config,
				'stripePublishableKey' => isset( $settings['stripe_publishable_key'] ) ? $settings['stripe_publishable_key'] : '',
			)
		);
	}

	/**
	 * Register the block
	 */
	public function register_block() {
		// Register block script.
		wp_register_script(
			'phynite-signup-form-block',
			PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/js/block.js',
			array(
				'wp-blocks',
				'wp-element',
				'wp-components',
				'wp-block-editor',
				'wp-api-fetch',
				'wp-i18n',
				'wp-server-side-render',
			),
			PHYNITE_SIGNUP_FORM_VERSION,
			true
		);

		// Register block style.
		wp_register_style(
			'phynite-signup-form-block-editor',
			PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/css/block-editor-styles.css',
			array(),
			PHYNITE_SIGNUP_FORM_VERSION
		);

		// Register frontend style.
		wp_register_style(
			'phynite-signup-form-frontend',
			PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/css/frontend-styles.css',
			array(),
			PHYNITE_SIGNUP_FORM_VERSION
		);

		// Localize script with data.
		wp_localize_script(
			'phynite-signup-form-block',
			'phyniteSignupBlockData',
			array(
				'apiUrl'    => rest_url( 'phynite-signup/v1/' ),
				'nonce'     => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => PHYNITE_SIGNUP_FORM_PLUGIN_URL,
				'settings'  => $this->get_block_settings(),
			)
		);

		// Register the block.
		register_block_type(
			'phynite/signup-form',
			array(
				'editor_script'   => 'phynite-signup-form-block',
				'editor_style'    => 'phynite-signup-form-block-editor',
				'style'           => 'phynite-signup-form-frontend',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => $this->get_block_attributes(),
			)
		);
	}

	/**
	 * Register block category
	 */
	public function register_block_category() {
		if ( function_exists( 'get_default_block_categories' ) ) {
			add_filter( 'block_categories_all', array( $this, 'add_block_category' ), 10, 2 );
		} else {
			add_filter( 'block_categories', array( $this, 'add_block_category' ), 10, 2 );
		}
	}

	/**
	 * Add custom block category
	 *
	 * @param array   $categories Current block categories.
	 * @param WP_Post $post       The current post object.
	 * @return array Modified block categories.
	 */
	public function add_block_category( $categories, $post ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'phynite',
					'title' => __( 'Phynite Analytics', 'phynite-signup-form' ),
					'icon'  => 'chart-line',
				),
			)
		);
	}

	/**
	 * Get block attributes
	 */
	private function get_block_attributes() {
		return array(
			'title'             => array(
				'type'    => 'string',
				'default' => __( 'Get Started with Phynite Analytics', 'phynite-signup-form' ),
			),
			'subtitle'          => array(
				'type'    => 'string',
				'default' => __( 'Enter your details below to begin tracking your analytics.', 'phynite-signup-form' ),
			),
			'showTitle'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showSubtitle'      => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'showLogo'          => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'formStyle'         => array(
				'type'    => 'string',
				'default' => 'default',
			),
			'primaryColor'      => array(
				'type'    => 'string',
				'default' => '#007cba',
			),
			'buttonText'        => array(
				'type'    => 'string',
				'default' => __( 'Continue to Payment', 'phynite-signup-form' ),
			),
			'showPlanSelection' => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'defaultPlan'       => array(
				'type'    => 'string',
				'default' => 'monthly',
			),
			'showTermsLinks'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'termsUrl'          => array(
				'type'    => 'string',
				'default' => '/terms-of-service',
			),
			'privacyUrl'        => array(
				'type'    => 'string',
				'default' => '/privacy-policy',
			),
			'alignment'         => array(
				'type'    => 'string',
				'default' => 'center',
			),
			'maxWidth'          => array(
				'type'    => 'number',
				'default' => 600,
			),
			'className'         => array(
				'type'    => 'string',
				'default' => '',
			),
		);
	}

	/**
	 * Get settings for block editor
	 */
	private function get_block_settings() {
		$settings = get_option( 'phynite_signup_form_settings', array() );
		return array(
			'primaryColor'   => isset( $settings['primary_color'] ) ? $settings['primary_color'] : '#007cba',
			'formStyle'      => isset( $settings['form_style'] ) ? $settings['form_style'] : 'default',
			'showTermsLinks' => isset( $settings['show_terms_links'] ) ? $settings['show_terms_links'] : true,
			'apiConfigured'  => ! empty( $settings['api_key'] ),
		);
	}

	/**
	 * Render block on frontend
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $content    Block content.
	 * @return string Rendered block HTML.
	 */
	public function render_block( $attributes, $content ) {
		// Check if we're in the editor context.
		$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST && isset( $_GET['context'] ) && 'edit' === $_GET['context'];

		// Don't render if API is not configured (unless in editor).
		$settings = get_option( 'phynite_signup_form_settings', array() );
		if ( empty( $settings['api_key'] ) && ! $is_editor ) {
			return '<div class="phynite-signup-form-error">' .
					__( 'Phynite Analytics signup form is not configured. Please check the plugin settings.', 'phynite-signup-form' ) .
					'</div>';
		}

		// For editor preview, show configuration notice if API not configured.
		if ( empty( $settings['api_key'] ) && $is_editor ) {
			return '<div class="phynite-signup-form-error" style="padding: 20px; background: #fcf0f1; border: 1px solid #f087a1; color: #d63638; text-align: center;">' .
					__( 'API configuration required. Please configure your Stewie API key in the plugin settings to see the live preview.', 'phynite-signup-form' ) .
					'</div>';
		}

		// Enqueue assets based on context.
		if ( $is_editor ) {
			// In editor, enqueue both editor and frontend styles.
			wp_enqueue_style( 'phynite-signup-form-block-editor' );
			wp_enqueue_style( 'phynite-signup-form-frontend' );
			// Don't enqueue interactive JS in editor context.
		} else {
			// On frontend, enqueue normal assets.
			wp_enqueue_script( 'phynite-signup-form-frontend' );
			wp_enqueue_style( 'phynite-signup-form-frontend' );
		}

		// Generate unique form ID.
		$form_id = 'phynite-signup-form-' . wp_generate_uuid4();

		// Merge attributes with defaults.
		$attributes = wp_parse_args( $attributes, $this->get_default_attributes() );

		// Start output buffering.
		ob_start();
		?>
		
		<div class="phynite-signup-form-container <?php echo esc_attr( $attributes['className'] ); ?><?php echo $is_editor ? ' is-editor-preview' : ''; ?>" 
			data-form-id="<?php echo esc_attr( $form_id ); ?>"
			data-style="<?php echo esc_attr( $attributes['formStyle'] ); ?>"
			data-alignment="<?php echo esc_attr( $attributes['alignment'] ); ?>"
			<?php
			if ( $is_editor ) :
				?>
				data-is-preview="true"<?php endif; ?>
			style="max-width: <?php echo esc_attr( $attributes['maxWidth'] ); ?>px; --primary-color: <?php echo esc_attr( $attributes['primaryColor'] ); ?>;">
			
			<?php if ( $attributes['showLogo'] ) : ?>
				<div class="phynite-form-logo">
					<img src="<?php echo esc_url( PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'assets/images/phynite-logo.svg' ); ?>" 
						alt="<?php esc_attr_e( 'Phynite Analytics', 'phynite-signup-form' ); ?>"
						width="150" height="40">
				</div>
			<?php endif; ?>
			
			<?php if ( $attributes['showTitle'] ) : ?>
				<h2 class="phynite-form-title"><?php echo esc_html( $attributes['title'] ); ?></h2>
			<?php endif; ?>
			
			<?php if ( $attributes['showSubtitle'] ) : ?>
				<p class="phynite-form-subtitle"><?php echo esc_html( $attributes['subtitle'] ); ?></p>
			<?php endif; ?>
			
			<form id="<?php echo esc_attr( $form_id ); ?>" class="phynite-signup-form" data-nonce="<?php echo esc_attr( wp_create_nonce( 'phynite_signup_form' ) ); ?>">
				
				<!-- Website URL Field -->
				<div class="phynite-form-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-website" class="phynite-form-label">
						<?php esc_html_e( 'Website URL', 'phynite-signup-form' ); ?>
						<span class="phynite-required">*</span>
					</label>
					<input type="url" 
							id="<?php echo esc_attr( $form_id ); ?>-website"
							name="website" 
							class="phynite-form-input" 
							placeholder="https://example.com"
							required
							autocomplete="url">
					<div class="phynite-field-loading" style="display: none;">
						<div class="phynite-spinner"></div>
					</div>
					<div class="phynite-field-error" role="alert"></div>
				</div>
				
				<!-- Name Fields -->
				<div class="phynite-form-row">
					<div class="phynite-form-field phynite-form-field-half">
						<label for="<?php echo esc_attr( $form_id ); ?>-firstName" class="phynite-form-label">
							<?php esc_html_e( 'First Name', 'phynite-signup-form' ); ?>
							<span class="phynite-required">*</span>
						</label>
						<input type="text" 
								id="<?php echo esc_attr( $form_id ); ?>-firstName"
								name="firstName" 
								class="phynite-form-input" 
								placeholder="John"
								required
								maxlength="64"
								autocomplete="given-name">
						<div class="phynite-field-error" role="alert"></div>
					</div>
					
					<div class="phynite-form-field phynite-form-field-half">
						<label for="<?php echo esc_attr( $form_id ); ?>-lastName" class="phynite-form-label">
							<?php esc_html_e( 'Last Name', 'phynite-signup-form' ); ?>
							<span class="phynite-required">*</span>
						</label>
						<input type="text" 
								id="<?php echo esc_attr( $form_id ); ?>-lastName"
								name="lastName" 
								class="phynite-form-input" 
								placeholder="Doe"
								required
								maxlength="128"
								autocomplete="family-name">
						<div class="phynite-field-error" role="alert"></div>
					</div>
				</div>
				
				<!-- Email Field -->
				<div class="phynite-form-field">
					<label for="<?php echo esc_attr( $form_id ); ?>-email" class="phynite-form-label">
						<?php esc_html_e( 'Email Address', 'phynite-signup-form' ); ?>
						<span class="phynite-required">*</span>
					</label>
					<input type="email" 
							id="<?php echo esc_attr( $form_id ); ?>-email"
							name="email" 
							class="phynite-form-input" 
							placeholder="you@example.com"
							required
							maxlength="255"
							autocomplete="email">
					<div class="phynite-field-loading" style="display: none;">
						<div class="phynite-spinner"></div>
					</div>
					<div class="phynite-field-error" role="alert"></div>
				</div>
				
				<?php if ( $attributes['showPlanSelection'] ) : ?>
					<!-- Plan Selection -->
					<div class="phynite-form-field">
						<fieldset class="phynite-plan-selection">
							<legend class="phynite-form-label"><?php esc_html_e( 'Choose Your Plan', 'phynite-signup-form' ); ?></legend>
							<div class="phynite-plans-container" <?php echo $is_editor ? 'data-loading="false"' : 'data-loading="true"'; ?>>
								<?php if ( $is_editor ) : ?>
									<!-- Static plans for editor preview -->
									<label class="phynite-plan-option <?php echo 'monthly' === $attributes['defaultPlan'] ? 'selected' : ''; ?>" for="<?php echo esc_attr( $form_id ); ?>-plan-monthly">
										<input type="radio" 
												id="<?php echo esc_attr( $form_id ); ?>-plan-monthly"
												name="planId" 
												value="monthly" 
												<?php echo 'monthly' === $attributes['defaultPlan'] ? 'checked' : ''; ?>
												class="phynite-plan-radio">
										<div class="phynite-plan-content">
											<div class="phynite-plan-header">
												<h3 class="phynite-plan-title">Monthly Plan</h3>
												<div class="phynite-plan-price">
													<span class="phynite-price-amount">$29</span>
													<span class="phynite-price-interval">/month</span>
												</div>
											</div>
											<p class="phynite-plan-description">Perfect for getting started</p>
											<ul class="phynite-plan-features">
												<li>Full Analytics Access</li>
												<li>GA4 Integration</li>
												<li>Pinterest Analytics</li>
												<li>Email Support</li>
											</ul>
										</div>
										<div class="phynite-plan-checkmark">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
												<polyline points="20,6 9,17 4,12"></polyline>
											</svg>
										</div>
									</label>
									
									<label class="phynite-plan-option <?php echo 'yearly' === $attributes['defaultPlan'] ? 'selected' : ''; ?>" for="<?php echo esc_attr( $form_id ); ?>-plan-yearly">
										<input type="radio" 
												id="<?php echo esc_attr( $form_id ); ?>-plan-yearly"
												name="planId" 
												value="yearly" 
												<?php echo 'yearly' === $attributes['defaultPlan'] ? 'checked' : ''; ?>
												class="phynite-plan-radio">
										<div class="phynite-plan-content">
											<div class="phynite-plan-header">
												<h3 class="phynite-plan-title">Annual Plan</h3>
												<div class="phynite-plan-price">
													<span class="phynite-price-amount">$290</span>
													<span class="phynite-price-interval">/year</span>
												</div>
											</div>
											<p class="phynite-plan-description">Best value - Save 17%</p>
											<ul class="phynite-plan-features">
												<li>Full Analytics Access</li>
												<li>GA4 Integration</li>
												<li>Pinterest Analytics</li>
												<li>Email Support</li>
											</ul>
											<div class="phynite-plan-savings">Save $58</div>
										</div>
										<div class="phynite-plan-checkmark">
											<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
												<polyline points="20,6 9,17 4,12"></polyline>
											</svg>
										</div>
									</label>
								<?php else : ?>
									<div class="phynite-plans-loading">
										<?php esc_html_e( 'Loading pricing...', 'phynite-signup-form' ); ?>
									</div>
								<?php endif; ?>
							</div>
						</fieldset>
					</div>
				<?php else : ?>
					<input type="hidden" name="planId" value="<?php echo esc_attr( $attributes['defaultPlan'] ); ?>">
				<?php endif; ?>
				
				<!-- Terms and Conditions -->
				<div class="phynite-form-field">
					<label class="phynite-form-checkbox-label">
						<input type="checkbox" 
								id="<?php echo esc_attr( $form_id ); ?>-acceptTerms"
								name="acceptTerms" 
								class="phynite-form-checkbox" 
								required>
						<span class="phynite-checkbox-checkmark"></span>
						<span class="phynite-checkbox-text">
							<?php
							if ( $attributes['showTermsLinks'] ) {
								printf(
									/* translators: %1$s is the Terms of Service link, %2$s is the Privacy Policy link */
									esc_html__( 'I agree to the %1$s and acknowledge the %2$s', 'phynite-signup-form' ),
									'<a href="' . esc_url( $attributes['termsUrl'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terms of Service', 'phynite-signup-form' ) . '</a>',
									'<a href="' . esc_url( $attributes['privacyUrl'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy Policy', 'phynite-signup-form' ) . '</a>'
								);
							} else {
								esc_html_e( 'I agree to the Terms of Service and Privacy Policy', 'phynite-signup-form' );
							}
							?>
							<span class="phynite-required">*</span>
						</span>
					</label>
					<div class="phynite-field-error" role="alert"></div>
				</div>
				
				<!-- Honeypot Field (hidden) -->
				<input type="text" name="website_confirm" class="phynite-honeypot" tabindex="-1" autocomplete="off">
				
				<!-- Submit Button -->
				<div class="phynite-form-field">
					<?php if ( $is_editor ) : ?>
						<button type="button" class="phynite-form-submit" disabled style="opacity: 0.8; cursor: not-allowed;">
							<span class="phynite-submit-text"><?php echo esc_html( $attributes['buttonText'] ); ?></span>
							<span class="phynite-preview-notice" style="font-size: 11px; opacity: 0.7;">
								(Preview Mode)
							</span>
						</button>
					<?php else : ?>
						<button type="submit" class="phynite-form-submit" disabled>
							<span class="phynite-submit-text"><?php echo esc_html( $attributes['buttonText'] ); ?></span>
							<span class="phynite-submit-loading" style="display: none;">
								<svg class="phynite-spinner" viewBox="0 0 24 24">
									<circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-dasharray="31.416" stroke-dashoffset="31.416">
										<animate attributeName="stroke-dasharray" dur="2s" values="0 31.416;15.708 15.708;0 31.416" repeatCount="indefinite"/>
										<animate attributeName="stroke-dashoffset" dur="2s" values="0;-15.708;-31.416" repeatCount="indefinite"/>
									</circle>
								</svg>
								<?php esc_html_e( 'Processing...', 'phynite-signup-form' ); ?>
							</span>
						</button>
					<?php endif; ?>
				</div>
				
				<!-- Form Messages -->
				<div class="phynite-form-messages" role="alert" aria-live="assertive"></div>
				
			</form>
			
			<?php if ( $attributes['showTermsLinks'] ) : ?>
				<p class="phynite-form-footer">
					<?php esc_html_e( 'By proceeding, you agree to our Terms of Service and acknowledge our Privacy Policy. Your subscription will automatically renew at the end of each billing period.', 'phynite-signup-form' ); ?>
				</p>
			<?php endif; ?>
			
		</div>
		
		<?php
		return ob_get_clean();
	}

	/**
	 * Get default attribute values
	 */
	private function get_default_attributes() {
		$attributes = $this->get_block_attributes();
		$defaults   = array();

		foreach ( $attributes as $key => $config ) {
			if ( isset( $config['default'] ) ) {
				$defaults[ $key ] = $config['default'];
			}
		}

		return $defaults;
	}
}