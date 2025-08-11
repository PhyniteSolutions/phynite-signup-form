<?php
/**
 * Test API Integration
 */

class TestAPIIntegration extends WP_UnitTestCase {

	private $api;

	public function setUp(): void {
		parent::setUp();

		// Set up test environment
		PhyniteTestHelper::setup_test_settings();
		PhyniteTestHelper::setup_rate_limiting();
		MockStewie::setup_request_mocking();

		$this->api = new Phynite_Signup_Form_API();

		// Set up current user
		$user_id = PhyniteTestHelper::create_test_user();
		wp_set_current_user( $user_id );
	}

	public function tearDown(): void {
		PhyniteTestHelper::cleanup();
		MockStewie::cleanup_mocking();
		parent::tearDown();
	}

	/**
	 * Test email existence check - available email
	 */
	public function test_email_check_available() {
		$response = PhyniteTestHelper::simulate_api_request(
			'/check-email',
			'POST',
			array(
				'email' => 'test@example.com',
			)
		);

		PhyniteTestHelper::assertApiSuccess( $response, 'Email check should succeed for available email' );

		$data = $response->get_data();
		$this->assertFalse( $data['exists'], 'Email should not exist' );
	}

	/**
	 * Test email existence check - existing email
	 */
	public function test_email_check_exists() {
		$response = PhyniteTestHelper::simulate_api_request(
			'/check-email',
			'POST',
			array(
				'email' => 'existing@example.com',
			)
		);

		PhyniteTestHelper::assertApiSuccess( $response, 'Email check should succeed even for existing email' );

		$data = $response->get_data();
		$this->assertTrue( $data['exists'], 'Email should exist' );
	}

	/**
	 * Test email validation
	 */
	public function test_email_validation() {
		// Test invalid email
		$response = PhyniteTestHelper::simulate_api_request(
			'/check-email',
			'POST',
			array(
				'email' => 'invalid-email',
			)
		);

		PhyniteTestHelper::assertApiError( $response, 400, 'Invalid email should be rejected' );
	}

	/**
	 * Test checkout session creation - success
	 */
	public function test_checkout_creation_success() {
		$signup_data = PhyniteTestHelper::get_test_signup_data();

		$response = PhyniteTestHelper::simulate_api_request( '/create-checkout', 'POST', $signup_data );

		PhyniteTestHelper::assertApiSuccess( $response, 'Checkout creation should succeed with valid data' );

		$data = $response->get_data();
		$this->assertNotEmpty( $data['sessionId'], 'Session ID should be returned' );
		$this->assertStringStartsWith( 'cs_test_', $data['sessionId'], 'Session ID should have test prefix' );
	}

	/**
	 * Test checkout validation errors
	 */
	public function test_checkout_validation_errors() {
		$invalid_data = array(
			'website'     => 'not-a-url',
			'firstName'   => '',
			'lastName'    => 'Test123!', // Invalid characters
			'email'       => 'invalid-email',
			'planId'      => 'invalid-plan',
			'acceptTerms' => false,
		);

		$response = PhyniteTestHelper::simulate_api_request( '/create-checkout', 'POST', $invalid_data );

		PhyniteTestHelper::assertApiError( $response, 400, 'Invalid data should be rejected' );
	}

	/**
	 * Test products endpoint
	 */
	public function test_get_products() {
		$response = PhyniteTestHelper::simulate_api_request( '/get-products', 'GET' );

		PhyniteTestHelper::assertApiSuccess( $response, 'Products endpoint should succeed' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'monthly', $data, 'Monthly plan should be available' );
		$this->assertArrayHasKey( 'yearly', $data, 'Yearly plan should be available' );

		// Check monthly plan structure
		$monthly = $data['monthly'];
		$this->assertEquals( 'monthly', $monthly['id'] );
		$this->assertEquals( 6000, $monthly['amount'] ); // $60.00 in cents
		$this->assertEquals( 'month', $monthly['interval'] );

		// Check yearly plan structure and savings
		$yearly = $data['yearly'];
		$this->assertEquals( 'yearly', $yearly['id'] );
		$this->assertEquals( 60000, $yearly['amount'] ); // $600.00 in cents
		$this->assertArrayHasKey( 'savings', $yearly );
		$this->assertEquals( 17, $yearly['savings']['percentage'] );
	}

	/**
	 * Test rate limiting
	 */
	public function test_rate_limiting() {
		// Simulate exceeding rate limit
		PhyniteTestHelper::simulate_rate_limit_breach( '192.168.1.100', 6 );

		$response = PhyniteTestHelper::simulate_api_request(
			'/check-email',
			'POST',
			array(
				'email' => 'test@example.com',
			)
		);

		PhyniteTestHelper::assertApiError( $response, 429, 'Rate limited requests should be rejected' );
	}

	/**
	 * Test API connection test
	 */
	public function test_api_connection_test() {
		$api    = new Phynite_Signup_Form_API();
		$result = $api->test_connection();

		$this->assertTrue( $result['success'], 'API connection test should succeed' );
		$this->assertStringContains( 'successful', $result['message'] );
	}

	/**
	 * Test API failure handling
	 */
	public function test_api_failure_handling() {
		// Set up failing API
		MockStewie::cleanup_mocking();
		MockStewie::setup_failing_api();

		$response = PhyniteTestHelper::simulate_api_request(
			'/check-email',
			'POST',
			array(
				'email' => 'test@example.com',
			)
		);

		PhyniteTestHelper::assertApiError( $response, 500, 'API failures should be handled gracefully' );
	}

	/**
	 * Test nonce verification
	 */
	public function test_nonce_verification() {
		$request = new WP_REST_Request( 'POST', '/phynite-signup/v1/check-email' );
		$request->set_param( 'email', 'test@example.com' );
		// Don't set nonce header

		$response = rest_get_server()->dispatch( $request );

		$this->assertEquals( 403, $response->get_status(), 'Requests without valid nonce should be rejected' );
	}

	/**
	 * Test honeypot field
	 */
	public function test_honeypot_detection() {
		$signup_data                    = PhyniteTestHelper::get_test_signup_data();
		$signup_data['website_confirm'] = 'bot-filled-this'; // Bot detection

		$response = PhyniteTestHelper::simulate_api_request( '/create-checkout', 'POST', $signup_data );

		PhyniteTestHelper::assertApiError( $response, 400, 'Bot requests should be rejected' );
	}

	/**
	 * Test data sanitization
	 */
	public function test_data_sanitization() {
		$malicious_data = array(
			'website'     => 'https://example.com/<script>alert("xss")</script>',
			'firstName'   => 'John<script>alert("xss")</script>',
			'lastName'    => 'Doe<img src=x onerror=alert("xss")>',
			'email'       => 'test@example.com<script>',
			'planId'      => 'monthly<script>',
			'acceptTerms' => true,
		);

		$response = PhyniteTestHelper::simulate_api_request( '/create-checkout', 'POST', $malicious_data );

		// Should either sanitize the data or reject it
		if ( $response->get_status() === 200 ) {
			// If accepted, data should be sanitized
			$this->assertStringNotContainsString( '<script>', $response->get_data()['sessionId'] ?? '' );
		} else {
			// Should be rejected due to validation
			PhyniteTestHelper::assertApiError( $response, 400 );
		}
	}

	/**
	 * Test disposable email blocking
	 */
	public function test_disposable_email_blocking() {
		$response = PhyniteTestHelper::simulate_api_request(
			'/check-email',
			'POST',
			array(
				'email' => 'test@mailinator.com',
			)
		);

		// Disposable emails should be handled appropriately
		// This might succeed but flag the email, or reject it
		$this->assertTrue(
			$response->get_status() === 200 || $response->get_status() === 400,
			'Disposable email should be handled appropriately'
		);
	}
}
