<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cirrusly Commerce Remote Announcements
 * 
 * Fetches announcements from a remote API, caches them, and displays
 * them as Freemius sticky notices with plan-level targeting and
 * per-user dismissal tracking.
 *
 * @since 1.4.6
 */
class Cirrusly_Commerce_Announcements {

	/**
	 * API endpoint for announcements JSON.
	 */
	const API_URL = 'https://api.cirruslyweather.com/announcements.json';

	/**
	 * Transient cache key for storing fetched announcements.
	 */
	const CACHE_KEY = 'cirrusly_remote_announcements';

	/**
	 * Cache expiry time (12 hours).
	 */
	const CACHE_EXPIRY = 12 * HOUR_IN_SECONDS;

	/**
	 * API request timeout (seconds).
	 */
	const API_TIMEOUT = 10;

	/**
	 * Initialize the announcements system.
	 * 
	 * Hooks into admin_init to check and display announcements.
	 */
	public static function init() {
		// Only run in admin context for users with appropriate capabilities
		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'maybe_show_announcements' ) );
		}
	}

    /**
     * Allowed capabilities for announcement targeting.
     */
    private static $allowed_capabilities = array(
        'edit_products',
        'manage_woocommerce',
        'manage_options',
    );

	/**
	 * Fetch announcements from the remote API.
	 * 
	 * Attempts to retrieve cached announcements first. If cache is expired
	 * or missing, makes a fresh API request with timeout and error handling.
	 *
	 * @param bool $force_refresh Whether to bypass cache and fetch fresh data.
	 * @return array Array of announcement objects, or empty array on failure.
	 */
	public static function fetch_announcements( $force_refresh = false ) {
		// Check cache first unless forcing refresh
		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}
		}

		// Make API request
		$response = wp_remote_get(
			self::API_URL,
			array(
				'timeout'    => self::API_TIMEOUT,
				'user-agent' => 'Cirrusly-Commerce/' . CIRRUSLY_COMMERCE_VERSION,
				'headers'    => array(
					'Accept' => 'application/json',
				),
			)
		);

		// Handle errors
		if ( is_wp_error( $response ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Cirrusly Commerce Announcements API Error: ' . $response->get_error_message() );
			}
			return array();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Cirrusly Commerce Announcements API returned HTTP ' . $code );
			}
			return array();
		}

		// Parse JSON response
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Cirrusly Commerce Announcements API returned invalid JSON' );
			}
			return array();
		}

		// Validate and sanitize each announcement
		$announcements = array();
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) || empty( $item['message'] ) ) {
				continue;
			}

			$announcements[] = array(
				'id'          => sanitize_key( $item['id'] ),
				'title'       => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
				'message'     => isset( $item['message'] ) ? wp_kses_post( $item['message'] ) : '',
				'type'        => isset( $item['type'] ) ? sanitize_key( $item['type'] ) : 'info',
				'plan_level'  => isset( $item['plan_level'] ) ? sanitize_key( $item['plan_level'] ) : 'all',
				'min_version' => isset( $item['min_version'] ) ? sanitize_text_field( $item['min_version'] ) : '',
			'capability'  => isset( $item['capability'] ) && in_array( $item['capability'], self::$allowed_capabilities, true )
				? sanitize_key( $item['capability'] )
				: 'edit_products',
		);
		}

		// Cache the results
		set_transient( self::CACHE_KEY, $announcements, self::CACHE_EXPIRY );

		return $announcements;
	}

	/**
	 * Check and display applicable announcements.
	 * 
	 * Retrieves announcements from cache/API, filters them based on user
	 * targeting rules, and displays non-dismissed announcements via
	 * Freemius sticky notices.
	 */
	public static function maybe_show_announcements() {
		// Ensure Freemius is available
		if ( ! function_exists( 'cirrusly_fs' ) ) {
			return;
		}

		$announcements = self::fetch_announcements();

		if ( empty( $announcements ) ) {
			return;
		}

		foreach ( $announcements as $announcement ) {
			// Check if announcement should be shown to current user
			if ( ! self::should_show_to_user( $announcement ) ) {
				continue;
			}

			// Check if user has dismissed this announcement
			$user_id = get_current_user_id();
			if ( $user_id > 0 ) {
				$dismissed_key = 'cirrusly_dismissed_' . $announcement['id'];
				$dismissed     = get_user_meta( $user_id, $dismissed_key, true );
				if ( 'yes' === $dismissed ) {
					continue;
				}
			}

			// Display via Freemius sticky notice
			self::display_announcement( $announcement );
		}
	}

	/**
	 * Determine if an announcement should be shown to the current user.
	 * 
	 * Checks plan level, minimum version, and user capability.
	 *
	 * @param array $announcement Announcement data array.
	 * @return bool True if announcement should be shown, false otherwise.
	 */
	private static function should_show_to_user( $announcement ) {
		// Check user capability
		$capability = ! empty( $announcement['capability'] ) ? $announcement['capability'] : 'edit_products';
		if ( ! current_user_can( $capability ) ) {
			return false;
		}

		// Check minimum version requirement
		if ( ! empty( $announcement['min_version'] ) ) {
			if ( ! defined( 'CIRRUSLY_COMMERCE_VERSION' ) || 
			     version_compare( CIRRUSLY_COMMERCE_VERSION, $announcement['min_version'], '<' ) ) {
				return false;
			}
		}

		// Check plan level targeting
		$plan_level = ! empty( $announcement['plan_level'] ) ? $announcement['plan_level'] : 'all';

		switch ( $plan_level ) {
			case 'free':
				// Show only to free users (not pro or pro plus)
				if ( Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
					return false;
				}
				break;

			case 'pro':
				// Show only to pro users (not free, not pro plus)
				if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro() || 
				     Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
					return false;
				}
				break;

			case 'proplus':
				// Show only to pro plus users
				if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
					return false;
				}
				break;

			case 'all':
			default:
				// Show to everyone (no filtering)
				break;
		}

		return true;
	}

	/**
	 * Display a sticky notice using Freemius or native WordPress fallback.
	 * 
	 * Encapsulates interaction with Freemius to avoid direct access to private properties.
	 * Falls back to native WordPress admin_notices if Freemius is unavailable.
	 * 
	 * @param string $message Notice message (HTML allowed).
	 * @param string $notice_id Unique notice identifier.
	 * @param string $title Optional notice title.
	 * @param string $type Notice type (info, success, error, warning, update).
	 */
	private static function show_sticky_notice( $message, $notice_id, $title = '', $type = 'info' ) {
		// Attempt to use Freemius SDK
		if ( function_exists( 'cirrusly_fs' ) && cirrusly_fs() ) {
			$fs = cirrusly_fs();
			
			// Check for _admin_notices property (private, but currently the only Freemius API)
			if ( isset( $fs->_admin_notices ) && is_object( $fs->_admin_notices ) && method_exists( $fs->_admin_notices, 'add_sticky' ) ) {
				$fs->_admin_notices->add_sticky( $message, $notice_id, $title, $type );
				return;
			}
		}
		
		// Fallback: Use native WordPress admin notice with dismissible capability
		add_action( 'admin_notices', function() use ( $message, $notice_id, $title, $type ) {
			// Check if user has dismissed this notice
			$dismissed_key = 'cirrusly_dismissed_' . str_replace( 'cirrusly_announcement_', '', $notice_id );
			if ( get_user_meta( get_current_user_id(), $dismissed_key, true ) === 'yes' ) {
				return;
			}
			
			// Map Freemius types to WordPress notice types
			$wp_type = in_array( $type, array( 'error', 'warning', 'success', 'info' ), true ) ? $type : 'info';
			
			// Build notice HTML
			$notice_html = '<div class="notice notice-' . esc_attr( $wp_type ) . ' is-dismissible" data-notice-id="' . esc_attr( $notice_id ) . '">';
			if ( ! empty( $title ) ) {
				$notice_html .= '<p><strong>' . esc_html( $title ) . '</strong></p>';
			}
			$notice_html .= '<p>' . wp_kses_post( $message ) . '</p>';
			$notice_html .= '</div>';
			
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $notice_html;
			
			// Add dismissal handling script
			?>
			<script type="text/javascript">
			jQuery(document).on('click', '.notice[data-notice-id="<?php echo esc_js( $notice_id ); ?>"] .notice-dismiss', function() {
				jQuery.post(ajaxurl, {
					action: 'cirrusly_dismiss_announcement',
					notice_id: '<?php echo esc_js( $notice_id ); ?>',
					_wpnonce: '<?php echo esc_js( wp_create_nonce( 'cirrusly_dismiss_announcement' ) ); ?>'
				});
			});
			</script>
			<?php
		} );
		
		// Register AJAX handler for native notice dismissal (if not already registered)
		if ( ! has_action( 'wp_ajax_cirrusly_dismiss_announcement' ) ) {
			add_action( 'wp_ajax_cirrusly_dismiss_announcement', function() {
				check_ajax_referer( 'cirrusly_dismiss_announcement' );
				if ( isset( $_POST['notice_id'] ) ) {
					self::handle_dismissal( sanitize_text_field( wp_unslash( $_POST['notice_id'] ) ) );
					wp_send_json_success();
				}
				wp_send_json_error();
			} );
		}
	}

	/**
	 * Display an announcement using Freemius sticky notice.
	 * 
	 * @param array $announcement Announcement data array.
	 */
	private static function display_announcement( $announcement ) {
		// Validate required fields
		if ( empty( $announcement['id'] ) || empty( $announcement['message'] ) ) {
			return;
		}

		// Prepare notice ID (must be unique)
		$notice_id = 'cirrusly_announcement_' . $announcement['id'];

		// Prepare title (optional)
		$title = ! empty( $announcement['title'] ) ? $announcement['title'] : '';

		// Prepare type (info, success, error, warning)
		$type = ! empty( $announcement['type'] ) ? $announcement['type'] : 'info';
		
		// Validate type against allowed values
		if ( ! in_array( $type, array( 'info', 'success', 'error', 'warning', 'update' ), true ) ) {
			$type = 'info';
		}

		// Hook into dismissal to track user meta (only if not already registered)
		$dismissal_hook = 'fs_after_admin_notice_dismissed_' . $notice_id;
		if ( ! has_action( $dismissal_hook, array( __CLASS__, 'handle_dismissal' ) ) ) {
			add_action( $dismissal_hook, array( __CLASS__, 'handle_dismissal' ), 10, 1 );
		}

		// Display sticky notice via wrapper method
		self::show_sticky_notice( $announcement['message'], $notice_id, $title, $type );
	}

	/**
	 * Handle announcement dismissal.
	 * 
	 * Records dismissal in user meta when user dismisses a sticky notice.
	 * 
	 * @param string $notice_id The notice ID that was dismissed.
	 */
	public static function handle_dismissal( $notice_id ) {
		// Extract announcement ID from notice ID
		$announcement_id = str_replace( 'cirrusly_announcement_', '', $notice_id );
		
		if ( empty( $announcement_id ) || $announcement_id === $notice_id ) {
			// ID extraction failed or notice_id didn't have expected prefix
			return;
		}

		// Ensure the ID is a valid key format
		$announcement_id = sanitize_key( $announcement_id );
		if ( empty( $announcement_id ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( $user_id > 0 ) {
			$dismissed_key = 'cirrusly_dismissed_' . $announcement_id;
			update_user_meta( $user_id, $dismissed_key, 'yes' );
		}
	}

	/**
	 * Clear the announcements cache.
	 * 
	 * Useful for debugging or forcing a refresh of announcements.
	 */
	public static function clear_cache() {
		delete_transient( self::CACHE_KEY );
	}
}
