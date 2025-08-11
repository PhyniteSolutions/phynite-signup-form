<?php
/**
 * Security Utilities Class
 *
 * @package PhyniteSignupForm
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Security utilities for Phynite Signup Form
 */
class Phynite_Signup_Form_Security {

	/**
	 * Settings
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings = get_option( 'phynite_signup_form_settings', array() );
		$this->init();
	}

	/**
	 * Initialize security measures
	 */
	private function init() {
		// Add security headers.
		add_action( 'send_headers', array( $this, 'add_security_headers' ) );

		// Clean up rate limiting table periodically.
		add_action( 'wp_scheduled_delete', array( $this, 'cleanup_rate_limit_table' ) );

		// Add custom sanitization functions.
		add_filter( 'wp_kses_allowed_html', array( $this, 'allow_svg_in_forms' ), 10, 2 );
	}

	/**
	 * Add security headers for plugin pages
	 */
	public function add_security_headers() {
		global $pagenow;

		// Only add headers for admin pages and REST API requests.
		if ( ! is_admin() && strpos( $_SERVER['REQUEST_URI'], '/wp-json/phynite-signup/' ) === false ) {
			return;
		}

		// Content Security Policy.
		if ( ! headers_sent() ) {
			header( 'X-Content-Type-Options: nosniff' );
			header( 'X-Frame-Options: SAMEORIGIN' );
			header( 'X-XSS-Protection: 1; mode=block' );
			header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		}
	}

	/**
	 * Sanitize and validate form input
	 */
	public static function sanitize_form_input( $input, $type = 'text' ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( $input );

			case 'url':
				return esc_url_raw( $input );

			case 'name':
				$sanitized = sanitize_text_field( $input );
				// Only allow letters, spaces, hyphens, and apostrophes.
				return preg_replace( "/[^a-zA-Z\s'-]/", '', $sanitized );

			case 'plan':
				$allowed = array( 'monthly', 'yearly' );
				return in_array( $input, $allowed ) ? $input : '';

			case 'boolean':
				return filter_var( $input, FILTER_VALIDATE_BOOLEAN );

			default:
				return sanitize_text_field( $input );
		}
	}

	/**
	 * Validate email against disposable email providers
	 */
	public static function is_disposable_email( $email ) {
		$domain = substr( strrchr( $email, '@' ), 1 );

		// List of common disposable email domains.
		$disposable_domains = array(
			'10minutemail.com',
			'guerrillamail.com',
			'mailinator.com',
			'yopmail.com',
			'temp-mail.org',
			'throwaway.email',
			'maildrop.cc',
			'tempmail.net',
			'dispostable.com',
			'trashmail.com',
			'mohmal.com',
			'sharklasers.com',
			'guerrillamailblock.com',
			'pokemail.net',
			'spam4.me',
			'bccto.me',
			'mytrashmail.com',
		);

		return in_array( strtolower( $domain ), $disposable_domains );
	}

	/**
	 * Check if request is from allowed domain
	 */
	public function is_request_allowed() {
		$allowed_domains = isset( $this->settings['allowed_domains'] ) ? $this->settings['allowed_domains'] : '';

		if ( empty( $allowed_domains ) ) {
			return true; // Allow all if not configured.
		}

		$referer = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
		if ( empty( $referer ) ) {
			return false; // Block if no referer and domains are restricted.
		}

		$referer_domain = parse_url( $referer, PHP_URL_HOST );
		$allowed_list   = array_filter( array_map( 'trim', explode( "\n", $allowed_domains ) ) );

		foreach ( $allowed_list as $domain ) {
			if ( $referer_domain === $domain || strpos( $referer_domain, '.' . $domain ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect bot requests
	 */
	public static function is_bot_request( $user_agent = null, $additional_checks = array() ) {
		$user_agent = $user_agent ? $user_agent : ( isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '' );

		// Common bot signatures.
		$bot_patterns = array(
			'/bot/i',
			'/crawler/i',
			'/spider/i',
			'/scraper/i',
			'/wget/i',
			'/curl/i',
			'/python/i',
			'/perl/i',
			'/java/i',
			'/php/i',
			'/node/i',
			'/go-http/i',
			'/ruby/i',
			'/okhttp/i',
			'/apache/i',
		);

		foreach ( $bot_patterns as $pattern ) {
			if ( preg_match( $pattern, $user_agent ) ) {
				return true;
			}
		}

		// Check for missing or suspicious headers.
		if ( empty( $user_agent ) ) {
			return true;
		}

		// Check honeypot field if provided.
		if ( isset( $additional_checks['honeypot'] ) && ! empty( $additional_checks['honeypot'] ) ) {
			return true;
		}

		// Check for suspicious timing (too fast).
		if ( isset( $additional_checks['timing'] ) && $additional_checks['timing'] < 2000 ) {
			return true; // Submitted in less than 2 seconds.
		}

		return false;
	}

	/**
	 * Generate secure nonce for form
	 */
	public static function generate_form_nonce( $action = 'phynite_signup_form' ) {
		return wp_create_nonce( $action );
	}

	/**
	 * Verify form nonce
	 */
	public static function verify_form_nonce( $nonce, $action = 'phynite_signup_form' ) {
		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Rate limiting check with IP whitelisting
	 */
	public function check_rate_limit( $ip = null ) {
		$ip = $ip ? $ip : $this->get_client_ip();

		// Allow localhost and private IPs during development.
		if ( $this->is_development_environment() && $this->is_private_ip( $ip ) ) {
			return true;
		}

		$rate_limit = isset( $this->settings['rate_limit'] ) ? intval( $this->settings['rate_limit'] ) : 5;

		global $wpdb;
		$table_name = $wpdb->prefix . 'phynite_rate_limits';

		// Check if IP is currently blocked.
		$blocked_until = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT blocked_until FROM $table_name WHERE ip_address = %s AND blocked_until > %s",
				$ip,
				current_time( 'mysql' )
			)
		);

		if ( $blocked_until ) {
			return false;
		}

		// Clean up old entries (older than 1 minute).
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE last_attempt < %s AND blocked_until IS NULL",
				date( 'Y-m-d H:i:s', strtotime( '-1 minute' ) )
			)
		);

		// Get current attempts in the last minute.
		$current_attempts = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT attempts FROM $table_name WHERE ip_address = %s AND last_attempt > %s",
				$ip,
				date( 'Y-m-d H:i:s', strtotime( '-1 minute' ) )
			)
		);

		$current_attempts = intval( $current_attempts );

		if ( $current_attempts >= $rate_limit ) {
			// Block IP for 5 minutes after exceeding rate limit.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE $table_name SET blocked_until = %s WHERE ip_address = %s",
					date( 'Y-m-d H:i:s', strtotime( '+5 minutes' ) ),
					$ip
				)
			);

			$this->log_security_event(
				'rate_limit_exceeded',
				array(
					'ip'       => $ip,
					'attempts' => $current_attempts,
				)
			);

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
	 * Get client IP address with proxy support
	 */
	private function get_client_ip() {
		$ip_keys = array(
			'HTTP_CF_CONNECTING_IP',     // Cloudflare.
			'HTTP_X_FORWARDED_FOR',      // Load balancer/proxy.
			'HTTP_X_FORWARDED',          // Proxy.
			'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster.
			'HTTP_FORWARDED_FOR',        // Proxy.
			'HTTP_FORWARDED',            // Proxy.
			'REMOTE_ADDR',                // Standard.
		);

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
	 * Check if IP is private/local
	 */
	private function is_private_ip( $ip ) {
		return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
	}

	/**
	 * Check if we're in development environment
	 */
	private function is_development_environment() {
		$environment = isset( $this->settings['environment'] ) ? $this->settings['environment'] : 'production';
		return in_array( $environment, array( 'development', 'staging' ) );
	}

	/**
	 * Clean up rate limiting table
	 */
	public function cleanup_rate_limit_table() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'phynite_rate_limits';

		// Remove entries older than 24 hours.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE last_attempt < %s",
				date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);

		// Remove expired blocks.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM $table_name WHERE blocked_until IS NOT NULL AND blocked_until < %s",
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Log security events
	 */
	private function log_security_event( $event_type, $data = array() ) {
		$log_data = array(
			'timestamp'  => current_time( 'c' ),
			'event'      => $event_type,
			'data'       => $data,
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '',
			'referer'    => isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '',
		);

		error_log( '[Phynite Signup Form Security] ' . wp_json_encode( $log_data ) );

		// Optionally store in database for analysis.
		if ( $this->should_store_security_logs() ) {
			$this->store_security_log( $log_data );
		}
	}

	/**
	 * Check if security logs should be stored in database
	 */
	private function should_store_security_logs() {
		return isset( $this->settings['store_security_logs'] ) && $this->settings['store_security_logs'];
	}

	/**
	 * Store security log in database
	 */
	private function store_security_log( $log_data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'phynite_security_logs';

		// Create table if it doesn't exist.
		$this->maybe_create_security_logs_table();

		$wpdb->insert(
			$table_name,
			array(
				'event_type' => $log_data['event'],
				'event_data' => wp_json_encode( $log_data['data'] ),
				'ip_address' => isset( $log_data['data']['ip'] ) ? $log_data['data']['ip'] : $this->get_client_ip(),
				'user_agent' => $log_data['user_agent'],
				'created_at' => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Create security logs table if needed
	 */
	private function maybe_create_security_logs_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'phynite_security_logs';

		if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) !== $table_name ) {
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                event_type varchar(50) NOT NULL,
                event_data text,
                ip_address varchar(45) NOT NULL,
                user_agent text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY event_type (event_type),
                KEY ip_address (ip_address),
                KEY created_at (created_at)
            ) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}
	}

	/**
	 * Allow SVG in forms (for icons)
	 */
	public function allow_svg_in_forms( $tags, $context ) {
		if ( $context === 'post' ) {
			$tags['svg']  = array(
				'class'   => true,
				'width'   => true,
				'height'  => true,
				'viewbox' => true,
				'xmlns'   => true,
				'fill'    => true,
				'stroke'  => true,
			);
			$tags['path'] = array(
				'd'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			);
		}
		return $tags;
	}

	/**
	 * Encrypt sensitive data
	 */
	public static function encrypt_data( $data ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return base64_encode( $data ); // Fallback to base64 encoding.
		}

		$key       = wp_salt( 'AUTH_SALT' );
		$iv        = openssl_random_pseudo_bytes( 16 );
		$encrypted = openssl_encrypt( $data, 'AES-256-CBC', $key, 0, $iv );

		return base64_encode( $iv . $encrypted );
	}

	/**
	 * Decrypt sensitive data
	 */
	public static function decrypt_data( $data ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return base64_decode( $data ); // Fallback from base64 encoding.
		}

		$key       = wp_salt( 'AUTH_SALT' );
		$data      = base64_decode( $data );
		$iv        = substr( $data, 0, 16 );
		$encrypted = substr( $data, 16 );

		return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
	}
}
