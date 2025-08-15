<?php
/**
 * Plugin Name: Phynite Analytics Signup Form
 * Plugin URI: https://phynitesolutions.com
 * Description: A WordPress plugin that provides a customizable Gutenberg block for Phynite Analytics signup forms with secure API integration.
 * Version: 1.2.0
 * Requires at least: 6.8
 * Requires PHP: 8.3
 * Author: Phynite Solutions
 * Author URI: https://phynitesolutions.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: phynite-signup-form
 * Domain Path: /languages
 * Network: false
 *
 * @package PhyniteSignupForm
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'PHYNITE_SIGNUP_FORM_VERSION', '1.2.0' );
define( 'PHYNITE_SIGNUP_FORM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PHYNITE_SIGNUP_FORM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PHYNITE_SIGNUP_FORM_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Include bootstrap functions.
require_once PHYNITE_SIGNUP_FORM_PLUGIN_DIR . 'includes/functions.php';

/**
 * Main Plugin Class
 */
class Phynite_Signup_Form {

	/**
	 * Plugin instance
	 */
	private static $instance = null;

	/**
	 * Get plugin instance (Singleton)
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize plugin
	 */
	private function init() {
		// Load dependencies
		$this->load_dependencies();

		// Initialize hooks
		add_action( 'init', array( $this, 'init_plugin' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_block_editor_assets' ) );

		// Plugin activation/deactivation hooks
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		register_uninstall_hook( __FILE__, array( 'Phynite_Signup_Form', 'uninstall' ) );

		// Admin hooks
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'admin_init' ) );
		}
	}

	/**
	 * Load plugin dependencies
	 */
	private function load_dependencies() {
		require_once PHYNITE_SIGNUP_FORM_PLUGIN_DIR . 'includes/class-admin.php';
		require_once PHYNITE_SIGNUP_FORM_PLUGIN_DIR . 'includes/class-api.php';
		require_once PHYNITE_SIGNUP_FORM_PLUGIN_DIR . 'includes/class-block.php';
		require_once PHYNITE_SIGNUP_FORM_PLUGIN_DIR . 'includes/class-security.php';
	}

	/**
	 * Initialize plugin after WordPress is loaded
	 */
	public function init_plugin() {
		// Load text domain for translations
		load_plugin_textdomain(
			'phynite-signup-form',
			false,
			dirname( PHYNITE_SIGNUP_FORM_PLUGIN_BASENAME ) . '/languages'
		);

		// Initialize classes
		new Phynite_Signup_Form_Block();
		new Phynite_Signup_Form_Security();
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		$api = new Phynite_Signup_Form_API();
		$api->register_routes();
	}

	/**
	 * Enqueue frontend assets
	 */
	public function enqueue_frontend_assets() {
		// Only enqueue on pages that have our block
		if ( has_block( 'phynite/signup-form' ) ) {
			// Enqueue Stripe SDK
			wp_enqueue_script(
				'stripe-js',
				'https://js.stripe.com/v3/',
				array(),
				null,
				true
			);

			wp_enqueue_script(
				'phynite-signup-form-frontend',
				PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/js/frontend.js',
				array( 'jquery', 'stripe-js' ),
				PHYNITE_SIGNUP_FORM_VERSION,
				true
			);

			wp_enqueue_style(
				'phynite-signup-form-frontend',
				PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/css/frontend-styles.css',
				array(),
				PHYNITE_SIGNUP_FORM_VERSION
			);

			// Localize script with REST API data
			wp_localize_script(
				'phynite-signup-form-frontend',
				'phyniteSignupForm',
				array(
					'apiUrl'               => rest_url( 'phynite-signup/v1/' ),
					'nonce'                => wp_create_nonce( 'wp_rest' ),
					'ajaxUrl'              => admin_url( 'admin-ajax.php' ),
					'stripePublishableKey' => $this->get_stripe_publishable_key(),
				)
			);
		}
	}

	/**
	 * Get Stripe publishable key from settings
	 */
	private function get_stripe_publishable_key() {
		$settings = get_option( 'phynite_signup_form_settings', array() );

		// Get key from settings, fallback to environment-based logic if not set
		if ( ! empty( $settings['stripe_publishable_key'] ) ) {
			return $settings['stripe_publishable_key'];
		}

		// Fallback logic based on Stewie URL
		$stewie_url = isset( $settings['stewie_url'] ) ? $settings['stewie_url'] : 'https://api.phynitesolutions.com';

		if ( strpos( $stewie_url, 'localhost' ) !== false ) {
			// Test environment - use test key
			return 'pk_test_51MAK49HmpGLWBl6CORQXcxeZ9VKVcjlLQbGdVEv7wbQHKZVVJbAKKEOJzZbnyQbE6F8Mg3vbSRvkK1y6JjAHdjb900VLZlgKVB';
		} else {
			// Production environment - use live key (same as Sidney)
			return 'pk_live_51MAK49HmpGLWBl6Cl6iP3K2l5T5JAW9KivwmLrzprIYrOqtnwwyEBgOZVLJNbUO2ISfYpwMgugndk4XAsE52YOm9006dXtkc9f';
		}
	}

	/**
	 * Enqueue block editor assets
	 */
	public function enqueue_block_editor_assets() {
		wp_enqueue_script(
			'phynite-signup-form-block',
			PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-api-fetch', 'wp-i18n' ),
			PHYNITE_SIGNUP_FORM_VERSION,
			true
		);

		wp_enqueue_style(
			'phynite-signup-form-block-editor',
			PHYNITE_SIGNUP_FORM_PLUGIN_URL . 'dist/css/block-editor-styles.css',
			array(),
			PHYNITE_SIGNUP_FORM_VERSION
		);
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		$admin = new Phynite_Signup_Form_Admin();
		$admin->add_menu();
	}

	/**
	 * Initialize admin settings
	 */
	public function admin_init() {
		$admin = new Phynite_Signup_Form_Admin();
		$admin->init();
	}

	/**
	 * Plugin activation
	 */
	public function activate() {
		// Create default options
		add_option(
			'phynite_signup_form_settings',
			array(
				'api_key'        => '',
				'stewie_url'     => 'https://api.phynitesolutions.com',
				'environment'    => 'production',
				'rate_limit'     => 5,
				'form_style'     => 'default',
				'enable_logging' => false,
			)
		);

		// Create custom database table for rate limiting if needed
		$this->create_rate_limit_table();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation
	 */
	public function deactivate() {
		// Clean up transients
		delete_transient( 'phynite_signup_form_products' );

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Plugin uninstall
	 */
	public static function uninstall() {
		// Remove options
		delete_option( 'phynite_signup_form_settings' );

		// Remove custom table.
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}phynite_rate_limits" );

		// Clean up any remaining transients.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%phynite_signup_form%'" );
	}

	/**
	 * Create rate limiting table
	 */
	private function create_rate_limit_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'phynite_rate_limits';

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            attempts int(11) NOT NULL DEFAULT 0,
            last_attempt datetime DEFAULT CURRENT_TIMESTAMP,
            blocked_until datetime NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ip_address (ip_address),
            KEY last_attempt (last_attempt)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

// Check requirements before initializing.
if ( ! phynite_signup_form_requirements_check() ) {
	return;
}

// Start the plugin.
add_action( 'plugins_loaded', 'phynite_signup_form_init' );
