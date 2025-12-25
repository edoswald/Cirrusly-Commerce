<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * API Key Migration Notice
 *
 * Displays a one-time admin notice prompting existing Pro users with manual
 * API keys to link them to their Freemius account for automatic management.
 *
 * Display Conditions:
 * - User is Pro or higher
 * - API key exists in settings
 * - API key is NOT auto-generated (auto_generated flag is false/missing)
 * - Notice has NOT been dismissed
 * - User has 'manage_options' capability
 *
 * Features:
 * - Dismissible notice (stores dismissal in user meta)
 * - "Link Key Now" button (uses existing AJAX handler)
 * - "Maybe Later" dismiss link
 * - Clean, informative messaging
 */
class Cirrusly_Commerce_API_Key_Migration_Notice {

    /**
     * Initialize hooks
     */
    public static function init() {
        add_action( 'admin_notices', array( __CLASS__, 'render_migration_notice' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_dismiss' ) );
    }

    /**
     * Check if migration notice should be displayed
     *
     * @return bool True if notice should show
     */
    private static function should_display_notice() {
        // Only show to admins
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        // Only show to Pro users
        if ( ! class_exists( 'Cirrusly_Commerce_Core' ) || ! Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            return false;
        }

        // Check if user has dismissed the notice
        $dismissed = get_user_meta( get_current_user_id(), 'cirrusly_api_migration_dismissed', true );
        if ( $dismissed ) {
            return false;
        }

        // Check if API key exists and is manual (not auto-generated)
        $config = get_option( 'cirrusly_scan_config', array() );

        // No API key at all
        if ( empty( $config['api_key'] ) ) {
            return false;
        }

        // Already auto-generated
        if ( isset( $config['auto_generated'] ) && $config['auto_generated'] ) {
            return false;
        }

        // Manual key exists and hasn't been linked - show notice!
        return true;
    }

    /**
     * Render the migration notice
     */
    public static function render_migration_notice() {
        if ( ! self::should_display_notice() ) {
            return;
        }

        // Create dismiss URL
        $dismiss_url = wp_nonce_url(
            add_query_arg( 'cirrusly_dismiss_migration', '1' ),
            'cirrusly_dismiss_migration_nonce'
        );

        ?>
        <div class="notice notice-info is-dismissible cirrusly-migration-notice" style="position: relative; padding: 15px 20px; border-left-color: #2271b1; border-left-width: 4px;">
            <div style="display: flex; align-items: flex-start; gap: 15px;">
                <!-- Icon -->
                <div style="flex-shrink: 0;">
                    <span class="dashicons dashicons-admin-network" style="font-size: 32px; width: 32px; height: 32px; color: #2271b1; margin-top: 2px;"></span>
                </div>

                <!-- Content -->
                <div style="flex: 1;">
                    <h3 style="margin: 0 0 8px 0; font-size: 15px; font-weight: 600;">
                        <?php esc_html_e( 'Enable Automatic API Key Management', 'cirrusly-commerce' ); ?>
                    </h3>
                    <p style="margin: 0 0 12px 0; font-size: 13px; line-height: 1.6;">
                        <?php esc_html_e( 'We detected you\'re using a manually configured API key. Link it to your Freemius account to enable automatic regeneration, plan updates, and enhanced security features.', 'cirrusly-commerce' ); ?>
                    </p>

                    <!-- Benefits List -->
                    <ul style="margin: 0 0 15px 20px; font-size: 13px; line-height: 1.8; color: #555;">
                        <li><strong><?php esc_html_e( 'Automatic Regeneration:', 'cirrusly-commerce' ); ?></strong> <?php esc_html_e( 'Regenerate keys from your dashboard', 'cirrusly-commerce' ); ?></li>
                        <li><strong><?php esc_html_e( 'Plan Sync:', 'cirrusly-commerce' ); ?></strong> <?php esc_html_e( 'API quotas update automatically when you upgrade/downgrade', 'cirrusly-commerce' ); ?></li>
                        <li><strong><?php esc_html_e( 'License Protection:', 'cirrusly-commerce' ); ?></strong> <?php esc_html_e( 'Keys are automatically revoked if subscription is cancelled', 'cirrusly-commerce' ); ?></li>
                    </ul>

                    <!-- Action Buttons -->
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" class="button button-primary cirrusly-link-manual-key-btn">
                            <span class="dashicons dashicons-admin-links" style="margin-top: 3px;"></span>
                            <?php esc_html_e( 'Link Key Now', 'cirrusly-commerce' ); ?>
                        </button>
                        <a href="<?php echo esc_url( $dismiss_url ); ?>" class="button button-secondary">
                            <?php esc_html_e( 'Maybe Later', 'cirrusly-commerce' ); ?>
                        </a>
                        <span class="cirrusly-api-link-status" style="margin-left: 10px; font-size: 13px; font-weight: 600;"></span>
                    </div>

                    <!-- Note -->
                    <p style="margin: 12px 0 0 0; font-size: 12px; color: #666; font-style: italic;">
                        <span class="dashicons dashicons-info" style="font-size: 14px; vertical-align: text-bottom;"></span>
                        <?php esc_html_e( 'Your existing key will continue to work. Linking simply enables automatic management features.', 'cirrusly-commerce' ); ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Inline JavaScript for Link Button -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Only bind if settings automation script is loaded
            if (typeof cirruslySettingsApiAutomation === 'undefined') {
                // Settings script not loaded, handle inline
                $('.cirrusly-migration-notice .cirrusly-link-manual-key-btn').on('click', function(e) {
                    e.preventDefault();

                    var button = $(this);
                    var statusContainer = $('.cirrusly-api-link-status');
                    var notice = $('.cirrusly-migration-notice');

                    // Confirm action
                    if (!confirm('<?php echo esc_js( __( 'Link your manual API key to this install? This enables automatic regeneration features.', 'cirrusly-commerce' ) ); ?>')) {
                        return;
                    }

                    // Set loading state
                    var originalHtml = button.html();
                    button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; animation: rotation 1s infinite linear;"></span> <?php echo esc_js( __( 'Linking...', 'cirrusly-commerce' ) ); ?>');
                    statusContainer.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

                    // Make AJAX request
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cirrusly_link_manual_key',
                            nonce: '<?php echo wp_create_nonce( 'cirrusly-admin-nonce' ); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                // Success - show message and fade out notice
                                statusContainer.html('<span style="color: #008a20;">✓ <?php echo esc_js( __( 'Linked successfully!', 'cirrusly-commerce' ) ); ?></span>');
                                setTimeout(function() {
                                    notice.fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                    // Reload page to show updated status
                                    location.reload();
                                }, 1500);
                            } else {
                                // Error
                                var errorMsg = response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Unknown error occurred', 'cirrusly-commerce' ) ); ?>';
                                statusContainer.html('<span style="color: #d63638;">✗ ' + errorMsg + '</span>');
                                button.prop('disabled', false).html(originalHtml);
                            }
                        },
                        error: function() {
                            statusContainer.html('<span style="color: #d63638;">✗ <?php echo esc_js( __( 'Network error. Please try again.', 'cirrusly-commerce' ) ); ?></span>');
                            button.prop('disabled', false).html(originalHtml);
                        }
                    });
                });

                // Add rotation animation CSS if not already present
                if (!$('style#cirrusly-rotation-animation').length) {
                    $('<style id="cirrusly-rotation-animation">@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }</style>').appendTo('head');
                }
            }
            // If settings script IS loaded, the global handler will catch the click
        });
        </script>
        <?php
    }

    /**
     * Handle notice dismissal
     * Stores dismissal in user meta so notice doesn't show again for this user
     */
    public static function handle_dismiss() {
        // Check for dismissal query parameter
        if ( ! isset( $_GET['cirrusly_dismiss_migration'] ) ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cirrusly_dismiss_migration_nonce' ) ) {
            return;
        }

        // Check capability
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Store dismissal in user meta
        update_user_meta( get_current_user_id(), 'cirrusly_api_migration_dismissed', true );

        // Redirect back to remove query args
        wp_safe_redirect( remove_query_arg( array( 'cirrusly_dismiss_migration', '_wpnonce' ) ) );
        exit;
    }

    /**
     * Reset dismissal for a user (for testing or if they want to see notice again)
     *
     * @param int $user_id User ID (defaults to current user)
     */
    public static function reset_dismissal( $user_id = null ) {
        if ( null === $user_id ) {
            $user_id = get_current_user_id();
        }

        delete_user_meta( $user_id, 'cirrusly_api_migration_dismissed' );
    }

    /**
     * Reset dismissal for all users (admin utility function)
     */
    public static function reset_all_dismissals() {
        global $wpdb;

        $wpdb->delete(
            $wpdb->usermeta,
            array( 'meta_key' => 'cirrusly_api_migration_dismissed' ),
            array( '%s' )
        );
    }
}
