<?php
/**
 * Stewie API Integration Class
 *
 * @package PhyniteSignupForm
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API handler for Stewie integration
 */
class Phynite_Signup_Form_API {

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * API base URL
	 */
	private $api_base_url;

	/**
	 * API key
	 */
	private $api_key;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings     = get_option( 'phynite_signup_form_settings', array() );
		$this->api_base_url = isset( $this->settings['stewie_url'] ) ? rtrim( $this->settings['stewie_url'], '/' ) : 'https://api.phynitesolutions.com';
		$this->api_key      = isset( $this->settings['api_key'] ) ? $this->settings['api_key'] : '';
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		register_rest_route(
			'phynite-signup/v1',
			'/check-email',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check_email_exists' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
			)
		);

		register_rest_route(
			'phynite-signup/v1',
			'/check-website',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'check_website_exists' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
			)
		);

		register_rest_route(
			'phynite-signup/v1',
			'/create-checkout',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_checkout_session' ),
				'permission_callback' => array( $this, 'verify_nonce' ),
			)
		);

		register_rest_route(
			'phynite-signup/v1',
			'/get-products',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_products' ),
				'permission_callback' => '__return_true', // Public endpoint.
			)
		);

		register_rest_route(
			'phynite-signup/v1',
			'/test-connection',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'test_connection' ),
				'permission_callback' => array( $this, 'verify_admin_permission' ),
			)
		);
	}

	/**
	 * Verify nonce for API requests
	 */
	public function verify_nonce( $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		return wp_verify_nonce( $nonce, 'wp_rest' );
	}

	/**
	 * Verify admin permission
	 */
	public function verify_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check if email exists in Stewie
	 */
	public function check_email_exists( $request ) {
		// Rate limiting check.
		if ( ! $this->check_rate_limit() ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'Too many requests. Please try again later.', 'phynite-signup-form' ), array( 'status' => 429 ) );
		}

		$email = sanitize_email( $request->get_param( 'email' ) );

		if ( ! $email || ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'phynite-signup-form' ), array( 'status' => 400 ) );
		}

		$response = $this->make_api_request(
			'/v1/users/signup-existence-check',
			array(
				'email' => $email,
			),
			'POST'
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Email check failed', $response->get_error_message() );
			return new WP_Error( 'api_error', __( 'Unable to verify email. Please try again.', 'phynite-signup-form' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'exists'  => isset( $response['data']['user_exists'] ) ? $response['data']['user_exists'] : false,
				'message' => isset( $response['data']['message'] ) ? $response['data']['message'] : '',
			)
		);
	}

	/**
	 * Check if website exists in Stewie
	 */
	public function check_website_exists( $request ) {
		// Rate limiting check.
		if ( ! $this->check_rate_limit() ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'Too many requests. Please try again later.', 'phynite-signup-form' ), array( 'status' => 429 ) );
		}

		$website = esc_url_raw( $request->get_param( 'website' ) );

		if ( ! $website || ! filter_var( $website, FILTER_VALIDATE_URL ) ) {
			return new WP_Error( 'invalid_website', __( 'Invalid website URL.', 'phynite-signup-form' ), array( 'status' => 400 ) );
		}

		// Validate protocol and path.
		$parsed = parse_url( $website );
		if ( ! in_array( $parsed['scheme'], array( 'http', 'https' ) ) ) {
			return new WP_Error( 'invalid_protocol', __( 'Website must use http or https protocol.', 'phynite-signup-form' ), array( 'status' => 400 ) );
		}

		$response = $this->make_api_request(
			'/v1/users/signup-existence-check',
			array(
				'website' => $website,
			),
			'POST'
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Website check failed', $response->get_error_message() );
			return new WP_Error( 'api_error', __( 'Unable to verify website. Please try again.', 'phynite-signup-form' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'exists'  => isset( $response['data']['website_exists'] ) ? $response['data']['website_exists'] : false,
				'message' => isset( $response['data']['message'] ) ? $response['data']['message'] : '',
			)
		);
	}

	/**
	 * Create checkout session
	 */
	public function create_checkout_session( $request ) {
		// Rate limiting check.
		if ( ! $this->check_rate_limit() ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'Too many requests. Please try again later.', 'phynite-signup-form' ), array( 'status' => 429 ) );
		}

		// Validate and sanitize input.
		$data = $this->validate_signup_data( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$response = $this->make_api_request( '/v1/billing/checkout', $data, 'POST' );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Checkout creation failed', $response->get_error_message() );
			return new WP_Error( 'checkout_error', __( 'Unable to create checkout session. Please try again.', 'phynite-signup-form' ), array( 'status' => 500 ) );
		}

		if ( ! isset( $response['sessionId'] ) ) {
			$this->log_error( 'Checkout response missing sessionId', json_encode( $response ) );
			return new WP_Error( 'checkout_error', __( 'Invalid response from payment processor.', 'phynite-signup-form' ), array( 'status' => 500 ) );
		}

		return rest_ensure_response(
			array(
				'success'   => true,
				'sessionId' => $response['sessionId'],
				'message'   => __( 'Checkout session created successfully.', 'phynite-signup-form' ),
			)
		);
	}

	/**
	 * Get subscription products
	 */
	public function get_products( $request ) {
		// Try to get from cache first.
		$cached = get_transient( 'phynite_signup_form_products' );
		if ( $cached !== false ) {
			return rest_ensure_response( $cached );
		}

		$response = $this->make_api_request( '/v1/billing/products', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Failed to fetch products', $response->get_error_message() );
			return new WP_Error( 'products_error', __( 'Unable to fetch pricing information.', 'phynite-signup-form' ), array( 'status' => 500 ) );
		}

		$products = isset( $response['data'] ) ? $response['data'] : array();

		// Cache for 1 hour.
		set_transient( 'phynite_signup_form_products', $products, HOUR_IN_SECONDS );

		return rest_ensure_response( $products );
	}

	/**
	 * Test API connection
	 */
	public function test_connection( $request = null ) {
		if ( empty( $this->api_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'API key is not configured.', 'phynite-signup-form' ),
			);
		}

		$response = $this->make_api_request( '/v1/billing/health', array(), 'GET' );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'API connection successful.', 'phynite-signup-form' ),
			'data'    => $response,
		);
	}

	/**
	 * Validate signup data
	 */
	private function validate_signup_data( $request ) {
		$errors = array();

		// Get and sanitize data.
		$website         = esc_url_raw( $request->get_param( 'website' ) );
		$first_name      = sanitize_text_field( $request->get_param( 'firstName' ) );
		$last_name       = sanitize_text_field( $request->get_param( 'lastName' ) );
		$email           = sanitize_email( $request->get_param( 'email' ) );
		$plan_id         = sanitize_text_field( $request->get_param( 'planId' ) );
		$accept_terms    = $request->get_param( 'acceptTerms' );
		$website_confirm = sanitize_text_field( $request->get_param( 'website_confirm' ) );

		// Validate website.
		if ( empty( $website ) || ! filter_var( $website, FILTER_VALIDATE_URL ) ) {
			$errors[] = __( 'Valid website URL is required.', 'phynite-signup-form' );
		} else {
			$parsed = parse_url( $website );
			if ( ! in_array( $parsed['scheme'], array( 'http', 'https' ) ) ) {
				$errors[] = __( 'Website must use http or https protocol.', 'phynite-signup-form' );
			}
			if ( isset( $parsed['path'] ) && $parsed['path'] !== '/' ) {
				$errors[] = __( 'Please enter the root domain without any path.', 'phynite-signup-form' );
			}
		}

		// Validate first name.
		if ( empty( $first_name ) ) {
			$errors[] = __( 'First name is required.', 'phynite-signup-form' );
		} elseif ( strlen( $first_name ) > 64 ) {
			$errors[] = __( 'First name must be less than 64 characters.', 'phynite-signup-form' );
		} elseif ( ! preg_match( "/^[a-zA-Z\s'-]+$/", $first_name ) ) {
			$errors[] = __( 'First name can only contain letters, spaces, hyphens, and apostrophes.', 'phynite-signup-form' );
		}

		// Validate last name.
		if ( empty( $last_name ) ) {
			$errors[] = __( 'Last name is required.', 'phynite-signup-form' );
		} elseif ( strlen( $last_name ) > 128 ) {
			$errors[] = __( 'Last name must be less than 128 characters.', 'phynite-signup-form' );
		} elseif ( ! preg_match( "/^[a-zA-Z\s'-]+$/", $last_name ) ) {
			$errors[] = __( 'Last name can only contain letters, spaces, hyphens, and apostrophes.', 'phynite-signup-form' );
		}

		// Validate email.
		if ( empty( $email ) || ! is_email( $email ) ) {
			$errors[] = __( 'Valid email address is required.', 'phynite-signup-form' );
		} elseif ( strlen( $email ) > 255 ) {
			$errors[] = __( 'Email address must be less than 255 characters.', 'phynite-signup-form' );
		}

		// Validate plan.
		$allowed_plans = array( 'monthly', 'yearly' );
		if ( empty( $plan_id ) || ! in_array( $plan_id, $allowed_plans ) ) {
			$errors[] = __( 'Please select a valid subscription plan.', 'phynite-signup-form' );
		}

		// Validate terms acceptance.
		if ( ! $accept_terms ) {
			$errors[] = __( 'You must accept the Terms of Service to continue.', 'phynite-signup-form' );
		}

		// Validate honeypot field (should be empty for legitimate users).
		if ( ! empty( $website_confirm ) ) {
			$errors[] = __( 'Bot detected.', 'phynite-signup-form' );
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error( 'validation_error', implode( ' ', $errors ), array( 'status' => 400 ) );
		}

		return array(
			'website'         => $website,
			'firstName'       => $first_name,
			'lastName'        => $last_name,
			'email'           => $email,
			'planId'          => $plan_id,
			'acceptTerms'     => true,
			'tosAcceptedAt'   => gmdate( 'Y-m-d\TH:i:s\Z' ), // UTC ISO 8601 format.
			'website_confirm' => $website_confirm, // Honeypot field.
		);
	}

	/**
	 * Make API request to Stewie
	 */
	private function make_api_request( $endpoint, $data = array(), $method = 'GET' ) {
		if ( empty( $this->api_key ) ) {
			return new WP_Error( 'no_api_key', __( 'API key not configured.', 'phynite-signup-form' ) );
		}

		$url = $this->api_base_url . $endpoint;

		$args = array(
			'method'  => $method,
			'timeout' => 30,
			'headers' => array(
				'X-API-Key'    => $this->api_key,
				'Content-Type' => 'application/json',
				'User-Agent'   => 'PhyniteSignupForm/' . PHYNITE_SIGNUP_FORM_VERSION . ' WordPress/' . get_bloginfo( 'version' ),
			),
		);

		if ( $method === 'POST' && ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$this->log_request( $url, $args );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'HTTP request failed', $response->get_error_message() );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		$this->log_response( $status_code, $body );

		if ( $status_code >= 400 ) {
			$error_data    = json_decode( $body, true );
			$error_message = isset( $error_data['message'] ) ? $error_data['message'] :
							( isset( $error_data['error'] ) ? $error_data['error'] :
							'API request failed with status ' . $status_code );

			return new WP_Error( 'api_error', $error_message );
		}

		$decoded = json_decode( $body, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->log_error( 'JSON decode error', json_last_error_msg() );
			return new WP_Error( 'json_error', __( 'Invalid response from API.', 'phynite-signup-form' ) );
		}

		return $decoded;
	}

	/**
	 * Check rate limiting
	 */
	private function check_rate_limit() {
		$ip         = $this->get_client_ip();
		$rate_limit = isset( $this->settings['rate_limit'] ) ? intval( $this->settings['rate_limit'] ) : 5;

		global $wpdb;
		$table_name = $wpdb->prefix . 'phynite_rate_limits';

		// Clean up old entries.
		$wpdb->query( $wpdb->prepare( "DELETE FROM $table_name WHERE last_attempt < %s", date( 'Y-m-d H:i:s', strtotime( '-1 minute' ) ) ) );

		// Check current attempts.
		$current_attempts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT attempts FROM $table_name WHERE ip_address = %s AND last_attempt > %s",
				$ip,
				date( 'Y-m-d H:i:s', strtotime( '-1 minute' ) )
			)
		);

		if ( $current_attempts >= $rate_limit ) {
			return false;
		}

		// Update or insert attempt record.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO $table_name (ip_address, attempts, last_attempt) 
             VALUES (%s, 1, %s) 
             ON DUPLICATE KEY UPDATE attempts = attempts + 1, last_attempt = %s",
				$ip,
				current_time( 'mysql' ),
				current_time( 'mysql' )
			)
		);

		return true;
	}

	/**
	 * Get client IP address
	 */
	private function get_client_ip() {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( array_key_exists( $key, $_SERVER ) === true ) {
				foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {
					$ip = trim( $ip );
					if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
						return $ip;
					}
				}
			}
		}

		return isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
	}

	/**
	 * Log API requests (if logging is enabled)
	 */
	private function log_request( $url, $args ) {
		if ( ! $this->is_logging_enabled() ) {
			return;
		}

		$log_data = array(
			'timestamp' => current_time( 'c' ),
			'type'      => 'request',
			'url'       => $url,
			'method'    => $args['method'],
			'headers'   => array_map(
				function ( $header ) {
					return strpos( $header, 'API-Key' ) !== false ? '[REDACTED]' : $header;
				},
				$args['headers']
			),
		);

		if ( isset( $args['body'] ) ) {
			$body = json_decode( $args['body'], true );
			if ( $body && isset( $body['email'] ) ) {
				$body['email'] = '[REDACTED]';
			}
			$log_data['body'] = $body;
		}

		error_log( '[Phynite Signup Form] Request: ' . wp_json_encode( $log_data ) );
	}

	/**
	 * Log API responses (if logging is enabled)
	 */
	private function log_response( $status_code, $body ) {
		if ( ! $this->is_logging_enabled() ) {
			return;
		}

		$response_data = json_decode( $body, true );
		if ( $response_data && isset( $response_data['data'] ) ) {
			if ( isset( $response_data['data']['clientEmail'] ) ) {
				$response_data['data']['clientEmail'] = '[REDACTED]';
			}
			if ( isset( $response_data['data']['email'] ) ) {
				$response_data['data']['email'] = '[REDACTED]';
			}
		}

		$log_data = array(
			'timestamp'   => current_time( 'c' ),
			'type'        => 'response',
			'status_code' => $status_code,
			'response'    => $response_data ? $response_data : substr( $body, 0, 500 ),
		);

		error_log( '[Phynite Signup Form] Response: ' . wp_json_encode( $log_data ) );
	}

	/**
	 * Log errors
	 */
	private function log_error( $context, $message ) {
		$log_data = array(
			'timestamp' => current_time( 'c' ),
			'type'      => 'error',
			'context'   => $context,
			'message'   => $message,
		);

		error_log( '[Phynite Signup Form] Error: ' . wp_json_encode( $log_data ) );
	}

	/**
	 * Check if logging is enabled
	 */
	private function is_logging_enabled() {
		return isset( $this->settings['enable_logging'] ) && $this->settings['enable_logging'];
	}
}
