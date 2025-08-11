<?php
/**
 * Plugin Bootstrap Functions
 *
 * @package PhyniteSignupForm
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check if we meet minimum requirements
 */
function phynite_signup_form_requirements_check() {
	if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Phynite Analytics Signup Form requires PHP 8.3 or higher.', 'phynite-signup-form' ) . ' ' . esc_html( PHP_VERSION );
				echo '</p></div>';
			}
		);
		return false;
	}

	if ( ! function_exists( 'curl_init' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>';
				echo esc_html__( 'Phynite Analytics Signup Form requires cURL extension to be installed.', 'phynite-signup-form' );
				echo '</p></div>';
			}
		);
		return false;
	}

	return true;
}

/**
 * Initialize the plugin
 */
function phynite_signup_form_init() {
	return Phynite_Signup_Form::get_instance();
}
