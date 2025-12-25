<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Freemius Webhook Handler
 *
 * Receives and processes webhook events from Freemius for real-time
 * license management and API key lifecycle automation.
 *
 * Supported Events:
 * - subscription.activated - Generate API key if doesn't exist
 * - subscription.plan_changed - Update plan_id in service worker
 * - subscription.deactivated - Revoke API key
 * - subscription.cancelled - Revoke API key
 * - subscription.trial_ended - Optional grace period handling
 *
 * Security:
 * - HMAC-SHA256 signature validation
 * - Webhook secret stored in options
 * - All events logged to database
 */
class Cirrusly_Commerce_Freemius_Webhook {

    /**
     * Webhook secret key for HMAC validation
     * Stored in: cirrusly_freemius_webhook_secret option
     */
    private static $webhook_secret = null;

    /**
     * Register REST API endpoint
     */
    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_endpoint' ) );
    }

    /**
     * Register the webhook REST endpoint
     */
    public static function register_endpoint() {
        register_rest_route( 'cirrusly/v1', '/freemius-webhook', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'handle_webhook' ),
            'permission_callback' => '__return_true', // We validate via HMAC signature instead
        ) );
    }

    /**
     * Main webhook handler
     * Validates signature, processes event, logs to database
     *
     * @param WP_REST_Request $request The webhook request
     * @return WP_REST_Response Response to Freemius
     */
    public static function handle_webhook( $request ) {
        // Get webhook secret
        self::$webhook_secret = get_option( 'cirrusly_freemius_webhook_secret', '' );

        if ( empty( self::$webhook_secret ) ) {
            self::log_webhook_error( 'Webhook secret not configured', $request->get_body() );
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Webhook secret not configured. Please configure in plugin settings.',
            ), 500 );
        }

        // Get raw body for signature validation
        $raw_body = $request->get_body();

        // Try REST API header first, then fall back to $_SERVER
        $signature = $request->get_header( 'x-signature' );
        if ( empty( $signature ) && isset( $_SERVER['HTTP_X_SIGNATURE'] ) ) {
            $signature = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_SIGNATURE'] ) );
        }

        if ( empty( $signature ) ) {
            self::log_webhook_error( 'Missing signature header', $raw_body );
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Missing signature header',
            ), 401 );
        }

        // Validate HMAC signature
        if ( ! self::validate_signature( $raw_body, $signature ) ) {
            self::log_webhook_error( 'Invalid signature', $raw_body );
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Invalid signature',
            ), 401 );
        }

        // Parse JSON body
        $data = json_decode( $raw_body, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            self::log_webhook_error( 'Invalid JSON: ' . json_last_error_msg(), $raw_body );
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Invalid JSON payload',
            ), 400 );
        }

        // Extract event type
        $event_type = isset( $data['type'] ) ? $data['type'] : '';
        $event_data = isset( $data['objects'] ) ? $data['objects'] : array();

        if ( empty( $event_type ) ) {
            self::log_webhook_error( 'Missing event type', $raw_body );
            return new WP_REST_Response( array(
                'success' => false,
                'message' => 'Missing event type',
            ), 400 );
        }

        // Log incoming webhook
        self::log_webhook( $event_type, $event_data, 'received' );

        // Process event
        $result = self::process_event( $event_type, $event_data );

        if ( is_wp_error( $result ) ) {
            self::log_webhook( $event_type, $event_data, 'failed', $result->get_error_message() );
            return new WP_REST_Response( array(
                'success' => false,
                'message' => $result->get_error_message(),
            ), 500 );
        }

        // Log success
        self::log_webhook( $event_type, $event_data, 'processed', 'Event processed successfully' );

        return new WP_REST_Response( array(
            'success' => true,
            'message' => 'Webhook processed successfully',
        ), 200 );
    }

    /**
     * Validate HMAC-SHA256 signature
     *
     * @param string $body Raw request body
     * @param string $signature Signature from header
     * @return bool True if valid
     */
    private static function validate_signature( $body, $signature ) {
        $computed_signature = hash_hmac( 'sha256', $body, self::$webhook_secret );
        return hash_equals( $computed_signature, $signature );
    }

    /**
     * Process webhook event based on type
     *
     * @param string $event_type Event type (e.g., 'subscription.activated')
     * @param array  $event_data Event data from webhook
     * @return true|WP_Error True on success, WP_Error on failure
     */
    private static function process_event( $event_type, $event_data ) {
        // Load API Key Manager
        if ( ! class_exists( 'Cirrusly_Commerce_API_Key_Manager' ) ) {
            $api_key_manager_path = CIRRUSLY_COMMERCE_PATH . 'includes/pro/class-api-key-manager.php';
            if ( file_exists( $api_key_manager_path ) ) {
                require_once $api_key_manager_path;
            } else {
                return new WP_Error( 'missing_class', 'API Key Manager not available' );
            }
        }

        // Extract install and subscription data
        $install = isset( $event_data['install'] ) ? $event_data['install'] : array();
        $subscription = isset( $event_data['subscription'] ) ? $event_data['subscription'] : array();
        $user = isset( $event_data['user'] ) ? $event_data['user'] : array();

        $install_id = isset( $install['id'] ) ? $install['id'] : '';
        $user_id = isset( $user['id'] ) ? $user['id'] : '';
        $plan_id = isset( $subscription['plan_id'] ) ? $subscription['plan_id'] : '';

        if ( empty( $install_id ) ) {
            return new WP_Error( 'missing_install_id', 'Install ID not found in webhook data' );
        }

        // Handle different event types
        switch ( $event_type ) {
            case 'subscription.activated':
                return self::handle_subscription_activated( $install_id, $user_id, $plan_id );

            case 'subscription.plan_changed':
                $old_plan_id = isset( $event_data['old_subscription']['plan_id'] ) ? $event_data['old_subscription']['plan_id'] : '';
                return self::handle_plan_changed( $install_id, $plan_id, $old_plan_id );

            case 'subscription.deactivated':
            case 'subscription.cancelled':
                return self::handle_subscription_deactivated( $install_id, $event_type );

            case 'subscription.trial_ended':
                return self::handle_trial_ended( $install_id );

            default:
                // Log unhandled event types but don't fail
                error_log( "Cirrusly Webhook: Unhandled event type: {$event_type}" );
                return true;
        }
    }

    /**
     * Handle subscription.activated event
     * Generates API key if it doesn't exist
     *
     * @param string $install_id Freemius install ID
     * @param string $user_id Freemius user ID
     * @param string $plan_id Freemius plan ID
     * @return true|WP_Error
     */
    private static function handle_subscription_activated( $install_id, $user_id, $plan_id ) {
        // Check if API key already exists for this install
        $config = get_option( 'cirrusly_scan_config', array() );

        // If key exists and is auto-generated, just update plan if needed
        if ( ! empty( $config['api_key'] ) && isset( $config['auto_generated'] ) && $config['auto_generated'] ) {
            // Key exists, just ensure plan is up to date
            if ( ! empty( $plan_id ) ) {
                Cirrusly_Commerce_API_Key_Manager::update_plan_id( $install_id, $plan_id );
            }
            return true;
        }

        // No auto-generated key exists, create one
        if ( empty( $user_id ) || empty( $plan_id ) ) {
            return new WP_Error( 'missing_data', 'User ID or Plan ID missing for key generation' );
        }

        $result = Cirrusly_Commerce_API_Key_Manager::request_api_key( $install_id, $user_id, $plan_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Store the key
        Cirrusly_Commerce_API_Key_Manager::store_api_key( $result['api_key'], $result );

        return true;
    }

    /**
     * Handle subscription.plan_changed event
     * Updates plan_id in service worker without regenerating key
     *
     * @param string $install_id Freemius install ID
     * @param string $new_plan_id New plan ID
     * @param string $old_plan_id Old plan ID (for logging)
     * @return true|WP_Error
     */
    private static function handle_plan_changed( $install_id, $new_plan_id, $old_plan_id ) {
        if ( empty( $new_plan_id ) ) {
            return new WP_Error( 'missing_plan_id', 'New plan ID not provided' );
        }

        // Log plan transition for audit trail
        if ( ! empty( $old_plan_id ) ) {
            error_log( sprintf( 'Cirrusly Webhook: Plan changed from %s to %s for install %s', $old_plan_id, $new_plan_id, $install_id ) );
        }

        $result = Cirrusly_Commerce_API_Key_Manager::update_plan_id( $install_id, $new_plan_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    /**
     * Handle subscription.deactivated or subscription.cancelled event
     * Revokes API key and clears from local storage
     *
     * @param string $install_id Freemius install ID
     * @param string $event_type Event type (for logging context)
     * @return true|WP_Error
     */
    private static function handle_subscription_deactivated( $install_id, $event_type ) {
        // Log event for audit trail
        error_log( sprintf( 'Cirrusly Webhook: Handling %s for install %s', $event_type, $install_id ) );

        $result = Cirrusly_Commerce_API_Key_Manager::revoke_api_key( $install_id );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        return true;
    }

    /**
     * Handle subscription.trial_ended event
     * Optional: Set grace period or revoke immediately
     *
     * @param string $install_id Freemius install ID
     * @return true|WP_Error
     */
    private static function handle_trial_ended( $install_id ) {
        // Option 1: Revoke immediately
        // return self::handle_subscription_deactivated( $install_id, 'trial_ended' );

        // Option 2: Set grace period (3 days)
        // This requires extending the service worker to support expires_at field
        // For now, we'll just log it and let the key continue working
        // Users who don't convert will be handled by subscription.deactivated event

        error_log( "Cirrusly Webhook: Trial ended for install {$install_id}. Key remains active until subscription deactivates." );
        return true;
    }

    /**
     * Log webhook event to database
     *
     * @param string $event_type Event type
     * @param array  $event_data Event data
     * @param string $status Status (received/processed/failed)
     * @param string $notes Optional notes
     */
    private static function log_webhook( $event_type, $event_data, $status, $notes = '' ) {
        // Create log entry
        $log_data = array(
            'event_type'   => $event_type,
            'event_data'   => wp_json_encode( $event_data ),
            'status'       => $status,
            'notes'        => $notes,
            'ip_address'   => self::get_client_ip(),
            'received_at'  => current_time( 'mysql' ),
            'processed_at' => ( $status === 'processed' || $status === 'failed' ) ? current_time( 'mysql' ) : null,
        );

        // Store in option (WordPress table for webhook logs)
        // Using option instead of custom table for simplicity
        $webhook_logs = get_option( 'cirrusly_freemius_webhook_logs', array() );

        // Keep only last 100 entries
        if ( count( $webhook_logs ) >= 100 ) {
            array_shift( $webhook_logs );
        }

        $webhook_logs[] = $log_data;
        update_option( 'cirrusly_freemius_webhook_logs', $webhook_logs, false );
    }

    /**
     * Log webhook error (for debugging)
     *
     * @param string $error_message Error message
     * @param string $raw_body Raw request body
     */
    private static function log_webhook_error( $error_message, $raw_body ) {
        error_log( sprintf(
            'Cirrusly Webhook Error: %s | Body: %s',
            $error_message,
            substr( $raw_body, 0, 500 ) // Limit to 500 chars for log
        ) );

        self::log_webhook( 'error', array( 'error' => $error_message ), 'failed', $error_message );

        // Email admin after 3+ consecutive failures
        self::check_consecutive_failures();
    }

    /**
     * Check for consecutive failures and email admin if threshold reached
     */
    private static function check_consecutive_failures() {
        $webhook_logs = get_option( 'cirrusly_freemius_webhook_logs', array() );

        if ( count( $webhook_logs ) < 3 ) {
            return;
        }

        // Get last 3 entries
        $recent_logs = array_slice( $webhook_logs, -3 );

        // Check if all 3 failed
        $all_failed = true;
        foreach ( $recent_logs as $log ) {
            if ( $log['status'] !== 'failed' ) {
                $all_failed = false;
                break;
            }
        }

        if ( ! $all_failed ) {
            return;
        }

        // Check if we already sent email recently (within 24 hours)
        $last_email_sent = get_option( 'cirrusly_webhook_failure_email_sent', 0 );
        if ( time() - $last_email_sent < DAY_IN_SECONDS ) {
            return;
        }

        // Check if we're in a retry backoff period (5 minutes after failed send attempt)
        if ( get_transient( 'cirrusly_webhook_email_retry_backoff' ) ) {
            return;
        }

        // Ensure Mailer is loaded
        if ( ! class_exists( 'Cirrusly_Commerce_Mailer' ) ) {
            require_once CIRRUSLY_COMMERCE_PATH . 'class-mailer.php';
        }

        $to = Cirrusly_Commerce_Mailer::get_recipient( 'webhook_failures' );
        
        // Check if email type is enabled
        if ( ! Cirrusly_Commerce_Mailer::is_email_enabled( 'webhook_failures' ) ) {
            return;
        }
        
        $subject = '[Cirrusly Commerce] Webhook Failures Detected';
        
        try {
            $email_sent = Cirrusly_Commerce_Mailer::send_from_template(
                $to,
                'webhook-failures',
                array( 'recent_logs' => $recent_logs ),
                $subject
            );
            
            // Update last email sent timestamp only if email was successfully sent
            if ( $email_sent ) {
                update_option( 'cirrusly_webhook_failure_email_sent', time(), false );
            } else {
                // Log failure and set retry backoff
                error_log( sprintf(
                    '[Cirrusly Commerce] Failed to send webhook failure notification email. Template: webhook-failures, Recipient: %s',
                    $to
                ) );
                
                // Set a short-term transient to prevent immediate retry loops (5 minutes)
                set_transient( 'cirrusly_webhook_email_retry_backoff', time(), 5 * MINUTE_IN_SECONDS );
            }
        } catch ( Exception $e ) {
            // Catch any exceptions from mailer and log them
            error_log( sprintf(
                '[Cirrusly Commerce] Exception while sending webhook failure email: %s. Template: webhook-failures, Recipient: %s',
                $e->getMessage(),
                $to
            ) );
            
            // Set retry backoff on exception as well
            set_transient( 'cirrusly_webhook_email_retry_backoff', time(), 5 * MINUTE_IN_SECONDS );
        }
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            // Extract first IP if multiple are present (client, proxy1, proxy2)
            if ( strpos( $ip, ',' ) !== false ) {
                $ip = trim( explode( ',', $ip )[0] );
            }
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return $ip;
    }

    /**
     * Get webhook logs (for admin display)
     *
     * @param int $limit Number of logs to return
     * @return array Webhook logs
     */
    public static function get_webhook_logs( $limit = 50 ) {
        $webhook_logs = get_option( 'cirrusly_freemius_webhook_logs', array() );

        // Return most recent first
        $webhook_logs = array_reverse( $webhook_logs );

        if ( $limit > 0 ) {
            $webhook_logs = array_slice( $webhook_logs, 0, $limit );
        }

        return $webhook_logs;
    }

    /**
     * Clear all webhook logs
     */
    public static function clear_webhook_logs() {
        delete_option( 'cirrusly_freemius_webhook_logs' );
        delete_option( 'cirrusly_webhook_failure_email_sent' );
    }
}
