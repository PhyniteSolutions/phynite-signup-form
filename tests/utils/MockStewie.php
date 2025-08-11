<?php
/**
 * Mock Stewie API for Testing
 */

class MockStewie {

	/**
	 * Mock successful email check response
	 */
	public static function mock_email_check_available() {
		return array(
			'success' => true,
			'message' => 'Existence check completed successfully',
			'data'    => array(
				'user_exists' => false,
				'email'       => 'test@example.com',
				'message'     => 'Email is available',
			),
		);
	}

	/**
	 * Mock email exists response
	 */
	public static function mock_email_exists() {
		return array(
			'success' => true,
			'message' => 'Existence check completed successfully',
			'data'    => array(
				'user_exists' => true,
				'email'       => 'existing@example.com',
				'message'     => 'Email already exists',
			),
		);
	}

	/**
	 * Mock successful website check response
	 */
	public static function mock_website_check_available() {
		return array(
			'success' => true,
			'message' => 'Existence check completed successfully',
			'data'    => array(
				'website_exists' => false,
				'website'        => 'https://example.com',
				'message'        => 'Website is available',
			),
		);
	}

	/**
	 * Mock website exists response
	 */
	public static function mock_website_exists() {
		return array(
			'success' => true,
			'message' => 'Existence check completed successfully',
			'data'    => array(
				'website_exists' => true,
				'website'        => 'https://existing.com',
				'message'        => 'Website already exists',
			),
		);
	}

	/**
	 * Mock successful checkout session creation
	 */
	public static function mock_checkout_success() {
		return array(
			'sessionId' => 'cs_test_' . wp_generate_uuid4(),
			'success'   => true,
			'message'   => 'Checkout session created successfully',
		);
	}

	/**
	 * Mock checkout error response
	 */
	public static function mock_checkout_error( $error = 'EMAIL_EXISTS' ) {
		$errors = array(
			'EMAIL_EXISTS'        => array(
				'success' => false,
				'error'   => 'EMAIL_EXISTS',
				'message' => 'This email is already registered with Phynite Analytics',
			),
			'VALIDATION_ERROR'    => array(
				'success' => false,
				'error'   => 'VALIDATION_ERROR',
				'message' => 'Validation failed',
				'details' => 'email: Please use a permanent email address',
			),
			'RATE_LIMIT_EXCEEDED' => array(
				'success'    => false,
				'error'      => 'RATE_LIMIT_EXCEEDED',
				'message'    => 'Too many requests. Please try again later.',
				'retryAfter' => 60,
			),
		);

		return $errors[ $error ] ?? $errors['EMAIL_EXISTS'];
	}

	/**
	 * Mock products response
	 */
	public static function mock_products() {
		return array(
			'success' => true,
			'message' => 'Products retrieved successfully',
			'data'    => array(
				'monthly' => array(
					'id'            => 'monthly',
					'priceId'       => 'price_test_monthly',
					'amount'        => 6000, // $60.00 in cents
					'currency'      => 'usd',
					'interval'      => 'month',
					'intervalCount' => 1,
					'product'       => array(
						'id'          => 'prod_monthly_123',
						'name'        => 'Monthly Plan',
						'description' => 'Monthly subscription to Phynite Analytics',
					),
				),
				'yearly'  => array(
					'id'            => 'yearly',
					'priceId'       => 'price_test_yearly',
					'amount'        => 60000, // $600.00 in cents
					'currency'      => 'usd',
					'interval'      => 'year',
					'intervalCount' => 1,
					'product'       => array(
						'id'          => 'prod_yearly_123',
						'name'        => 'Yearly Plan',
						'description' => 'Yearly subscription to Phynite Analytics',
					),
					'savings'       => array(
						'amount'      => 12000, // $120.00 savings in cents
						'percentage'  => 17,
						'description' => 'Save 17% with yearly billing',
					),
				),
			),
		);
	}

	/**
	 * Mock health check success
	 */
	public static function mock_health_success() {
		return array(
			'success'   => true,
			'message'   => 'Billing service is healthy',
			'timestamp' => current_time( 'c' ),
		);
	}

	/**
	 * Mock API error responses
	 */
	public static function mock_api_error( $status = 500, $message = 'Internal Server Error' ) {
		return array(
			'success' => false,
			'error'   => 'API_ERROR',
			'message' => $message,
			'status'  => $status,
		);
	}

	/**
	 * Setup HTTP request mocking
	 */
	public static function setup_request_mocking() {
		// Mock email check endpoint
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) {
				if ( strpos( $url, '/v1/users/signup-existence-check' ) !== false ) {
					$body = json_decode( $args['body'], true );

					// Check if this is an email check
					if ( isset( $body['email'] ) ) {
						if ( $body['email'] === 'existing@example.com' ) {
							return PhyniteTestHelper::mock_http_response( 200, MockStewie::mock_email_exists() );
						} else {
							return PhyniteTestHelper::mock_http_response( 200, MockStewie::mock_email_check_available() );
						}
					}

					// Check if this is a website check
					if ( isset( $body['website'] ) ) {
						if ( $body['website'] === 'https://existing.com' ) {
							return PhyniteTestHelper::mock_http_response( 200, MockStewie::mock_website_exists() );
						} else {
							return PhyniteTestHelper::mock_http_response( 200, MockStewie::mock_website_check_available() );
						}
					}
				}

				// Mock checkout endpoint
				if ( strpos( $url, '/v1/billing/checkout' ) !== false ) {
					$body = json_decode( $args['body'], true );

					// Simulate validation errors
					if ( $body['email'] === 'invalid@disposable.com' ) {
						return PhyniteTestHelper::mock_http_response( 400, MockStewie::mock_checkout_error( 'VALIDATION_ERROR' ) );
					}

					// Simulate existing user
					if ( $body['email'] === 'existing@example.com' ) {
						return PhyniteTestHelper::mock_http_response( 400, MockStewie::mock_checkout_error( 'EMAIL_EXISTS' ) );
					}

					// Success case
					return PhyniteTestHelper::mock_http_response( 200, MockStewie::mock_checkout_success() );
				}

				// Mock products endpoint
				if ( strpos( $url, '/v1/billing/products' ) !== false ) {
					return PhyniteTestHelper::mock_http_response( 200, MockStewie::mock_products() );
				}

				// Mock health endpoint
				if ( strpos( $url, '/v1/billing/health' ) !== false ) {
					return PhyniteTestHelper::mock_http_response( 200, MockStewie::mock_health_success() );
				}

				// Return false to allow other filters to handle
				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Setup failing API responses
	 */
	public static function setup_failing_api() {
		add_filter(
			'pre_http_request',
			function ( $response, $args, $url ) {
				// Simulate API being down
				if ( strpos( $url, 'api.phynitesolutions.com' ) !== false || strpos( $url, 'localhost:4000' ) !== false ) {
					return new WP_Error( 'http_request_failed', 'Connection timed out' );
				}

				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Cleanup mocking
	 */
	public static function cleanup_mocking() {
		remove_all_filters( 'pre_http_request' );
	}
}
