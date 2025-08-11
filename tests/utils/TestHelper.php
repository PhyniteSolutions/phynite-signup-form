<?php
/**
 * Test Helper Utilities
 */

class PhyniteTestHelper {

	/**
	 * Create a test user
	 */
	public static function create_test_user( $role = 'administrator' ) {
		return wp_insert_user(
			array(
				'user_login' => 'testuser_' . wp_generate_uuid4(),
				'user_pass'  => 'testpass123',
				'user_email' => 'test' . time() . '@example.com',
				'role'       => $role,
			)
		);
	}

	/**
	 * Set up plugin settings for testing
	 */
	public static function setup_test_settings() {
		update_option(
			'phynite_signup_form_settings',
			array(
				'api_key'        => 'phyn_test_' . wp_generate_uuid4(),
				'stewie_url'     => 'http://localhost:4000',
				'environment'    => 'development',
				'rate_limit'     => 10,
				'form_style'     => 'default',
				'enable_logging' => true,
			)
		);
	}

	/**
	 * Clean up test settings
	 */
	public static function cleanup_test_settings() {
		delete_option( 'phynite_signup_form_settings' );
	}

	/**
	 * Create test API request data
	 */
	public static function get_test_signup_data() {
		return array(
			'website'         => 'https://example.com',
			'firstName'       => 'John',
			'lastName'        => 'Doe',
			'email'           => 'john.doe@example.com',
			'planId'          => 'monthly',
			'acceptTerms'     => true,
			'tosAcceptedAt'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'website_confirm' => '', // Honeypot field
		);
	}

	/**
	 * Generate test nonce
	 */
	public static function get_test_nonce( $action = 'wp_rest' ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Mock HTTP responses
	 */
	public static function mock_http_response( $status = 200, $body = array() ) {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Simulate API request
	 */
	public static function simulate_api_request( $endpoint, $method = 'GET', $data = array() ) {
		$request = new WP_REST_Request( $method, "/phynite-signup/v1{$endpoint}" );

		if ( $method === 'POST' && ! empty( $data ) ) {
			foreach ( $data as $key => $value ) {
				$request->set_param( $key, $value );
			}
		}

		$request->set_header( 'X-WP-Nonce', self::get_test_nonce() );

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Assert successful API response
	 */
	public static function assertApiSuccess( $response, $message = '' ) {
		PHPUnit\Framework\Assert::assertEquals( 200, $response->get_status(), $message ?: 'API response should be successful' );

		$data = $response->get_data();
		PHPUnit\Framework\Assert::assertTrue( $data['success'] ?? false, $message ?: 'API response should indicate success' );
	}

	/**
	 * Assert API error response
	 */
	public static function assertApiError( $response, $expected_status = 400, $message = '' ) {
		PHPUnit\Framework\Assert::assertEquals( $expected_status, $response->get_status(), $message ?: "API response should return status {$expected_status}" );

		$data = $response->get_data();
		PHPUnit\Framework\Assert::assertFalse( $data['success'] ?? true, $message ?: 'API response should indicate error' );
	}

	/**
	 * Set up rate limiting test environment
	 */
	public static function setup_rate_limiting() {
		global $wpdb;

		// Clean rate limiting table
		$table_name = $wpdb->prefix . 'phynite_rate_limits';
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );
	}

	/**
	 * Simulate multiple requests from same IP
	 */
	public static function simulate_rate_limit_breach( $ip = '192.168.1.100', $attempts = 6 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'phynite_rate_limits';

		$wpdb->insert(
			$table_name,
			array(
				'ip_address'   => $ip,
				'attempts'     => $attempts,
				'last_attempt' => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%s' )
		);

		// Set $_SERVER['REMOTE_ADDR'] for testing
		$_SERVER['REMOTE_ADDR'] = $ip;
	}

	/**
	 * Clean up test data
	 */
	public static function cleanup() {
		global $wpdb;

		// Clean rate limiting table
		$table_name = $wpdb->prefix . 'phynite_rate_limits';
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );

		// Clean security logs if exists
		$security_table = $wpdb->prefix . 'phynite_security_logs';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$security_table}'" ) == $security_table ) {
			$wpdb->query( "TRUNCATE TABLE {$security_table}" );
		}

		// Clean transients
		delete_transient( 'phynite_signup_form_products' );

		// Clean up test settings
		self::cleanup_test_settings();
	}
}
