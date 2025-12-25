<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * API Key Manager for Automated API Key Issuance
 *
 * Handles automated API key generation, validation, and lifecycle management
 * by interfacing with the service worker key lifecycle endpoints.
 */
class Cirrusly_Commerce_API_Key_Manager {

    const SERVICE_WORKER_URL = 'https://api.cirruslyweather.com/';
    const REGENERATION_COOLDOWN_DAYS = 7;

    // Freemius Plan ID Constants
    const PLAN_ID_FREE = '36829';
    const PLAN_ID_PRO = '36830';
    const PLAN_ID_PROPLUS = '37116';

    /**
     * Request a new API key from the service worker.
     *
     * @param string $install_id Freemius install ID
     * @param string $user_id Freemius user ID
     * @param string $plan_id Freemius plan ID (use PLAN_ID_* constants)
     * @return array|WP_Error Array with 'api_key' on success, WP_Error on failure
     */
    public static function request_api_key( $install_id, $user_id, $plan_id ) {
        // Validate plan_id is one of our constants
        $valid_plans = array(
            self::PLAN_ID_FREE,
            self::PLAN_ID_PRO,
            self::PLAN_ID_PROPLUS,
        );

        // Default to Free if invalid plan_id provided
        if ( ! in_array( $plan_id, $valid_plans, true ) ) {
            $plan_id = self::PLAN_ID_FREE;
        }

        // Get install API token for authentication
        $install_api_token = self::get_install_api_token();
        if ( ! $install_api_token ) {
            return new WP_Error( 'missing_token', __( 'Install API token not found. Please configure your Freemius integration.', 'cirrusly-commerce' ) );
        }

        // Prepare request
        $response = wp_remote_post( self::SERVICE_WORKER_URL, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $install_api_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'action'     => 'generate_api_key',
                'install_id' => $install_id,
                'user_id'    => $user_id,
                'plan_id'    => $plan_id,
            ) ),
        ) );

        // Handle response
        if ( is_wp_error( $response ) ) {
            error_log( 'Cirrusly API Key Manager: Failed to request API key - ' . $response->get_error_message() );
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['success'] ) || ! $data['success'] ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'cirrusly-commerce' );
            error_log( 'Cirrusly API Key Manager: Service worker error - ' . $error_message );
            return new WP_Error( 'service_worker_error', $error_message );
        }

        if ( ! isset( $data['api_key'] ) ) {
            return new WP_Error( 'invalid_response', __( 'API key not returned from service worker', 'cirrusly-commerce' ) );
        }

        return array(
            'api_key'   => $data['api_key'],
            'plan_id' => $plan_id,
            'generated' => current_time( 'mysql' ),
        );
    }

    /**
     * Store API key in WordPress settings.
     *
     * @param string $api_key The API key to store
     * @param array  $metadata Additional metadata (plan_id, generated timestamp, etc.)
     * @return bool True on success
     */
    public static function store_api_key( $api_key, $metadata = array() ) {
        $config = get_option( 'cirrusly_scan_config', array() );

        $config['api_key'] = $api_key;
        $config['api_key_generated'] = isset( $metadata['generated'] ) ? $metadata['generated'] : current_time( 'mysql' );
        $config['api_key_plan'] = isset( $metadata['plan_id'] ) ? $metadata['plan_id'] : '';
        $config['auto_generated'] = true;

        return update_option( 'cirrusly_scan_config', $config, false );
    }

    /**
     * Clear API key from WordPress settings.
     * Called on license deactivation.
     *
     * @return bool True on success
     */
    public static function clear_api_key() {
        $config = get_option( 'cirrusly_scan_config', array() );

        unset( $config['api_key'] );
        unset( $config['api_key_generated'] );
        unset( $config['api_key_plan'] );
        unset( $config['auto_generated'] );

        return update_option( 'cirrusly_scan_config', $config, false );
    }

    /**
     * Regenerate API key with cooldown enforcement.
     *
     * @param string $reason Reason for regeneration (compromise, testing, other)
     * @return array|WP_Error Array with 'api_key' on success, WP_Error on failure
     */
    public static function regenerate_api_key( $reason ) {
        // Check cooldown
        $config = get_option( 'cirrusly_scan_config', array() );
        $last_regenerated = isset( $config['api_key_last_regenerated'] ) ? $config['api_key_last_regenerated'] : '';

        if ( $last_regenerated ) {
            $last_timestamp = strtotime( $last_regenerated );
            if ( false === $last_timestamp ) {
                // Invalid stored timestamp, clear it and allow regeneration
                $config['api_key_last_regenerated'] = '';
                update_option( 'cirrusly_scan_config', $config, false );
            } else {
                $cooldown_end = $last_timestamp + ( self::REGENERATION_COOLDOWN_DAYS * DAY_IN_SECONDS );

                if ( time() < $cooldown_end ) {
                    $days_remaining = ceil( ( $cooldown_end - time() ) / DAY_IN_SECONDS );
                    return new WP_Error(
                        'cooldown_active',
                        sprintf( __( 'API key regeneration is available in %d days.', 'cirrusly-commerce' ), $days_remaining )
                    );
                }
            }
        }

        // Validate reason
        $valid_reasons = array( 'compromise', 'testing', 'other' );
        if ( ! in_array( $reason, $valid_reasons, true ) ) {
            return new WP_Error( 'invalid_reason', __( 'Invalid regeneration reason.', 'cirrusly-commerce' ) );
        }

        // Get Freemius install info
        if ( ! function_exists( 'cirrusly_fs' ) ) {
            return new WP_Error( 'freemius_unavailable', __( 'Freemius SDK not available.', 'cirrusly-commerce' ) );
        }

        $install = cirrusly_fs()->get_site();
        if ( ! $install ) {
            return new WP_Error( 'no_install', __( 'Freemius installation not found.', 'cirrusly-commerce' ) );
        }

        // Get install API token
        $install_api_token = self::get_install_api_token();
        if ( ! $install_api_token ) {
            return new WP_Error( 'missing_token', __( 'Install API token not found.', 'cirrusly-commerce' ) );
        }

        // Call service worker regenerate endpoint
        $response = wp_remote_post( self::SERVICE_WORKER_URL, array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $install_api_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'action'     => 'regenerate_api_key',
                'install_id' => $install->id,
                'reason'     => $reason,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Cirrusly API Key Manager: Failed to regenerate API key - ' . $response->get_error_message() );
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['success'] ) || ! $data['success'] ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'cirrusly-commerce' );
            return new WP_Error( 'service_worker_error', $error_message );
        }

        if ( ! isset( $data['api_key'] ) ) {
            return new WP_Error( 'invalid_response', __( 'New API key not returned from service worker', 'cirrusly-commerce' ) );
        }

        // Store new key and update regeneration timestamp
        $config['api_key'] = $data['api_key'];
        $config['api_key_last_regenerated'] = current_time( 'mysql' );
        $config['api_key_regeneration_reason'] = $reason;
        update_option( 'cirrusly_scan_config', $config, false );

        return array(
            'api_key'    => $data['api_key'],
            'regenerated' => $config['api_key_last_regenerated'],
            'reason'     => $reason,
        );
    }

    /**
     * Validate API key status with service worker.
     *
     * @param string $api_key Optional API key to validate (defaults to stored key)
     * @return array|WP_Error Array with status info on success, WP_Error on failure
     */
    public static function validate_key_status( $api_key = null ) {
        if ( ! $api_key ) {
            $config = get_option( 'cirrusly_scan_config', array() );
            $api_key = isset( $config['api_key'] ) ? $config['api_key'] : '';
        }

        if ( ! $api_key ) {
            return new WP_Error( 'no_key', __( 'No API key found to validate.', 'cirrusly-commerce' ) );
        }

        // Call service worker validate endpoint
        $response = wp_remote_post( self::SERVICE_WORKER_URL, array(
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'action'  => 'validate_api_key',
                'api_key' => $api_key,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['success'] ) || ! $data['success'] ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Validation failed', 'cirrusly-commerce' );
            return new WP_Error( 'validation_failed', $error_message );
        }

        return array(
            'status'      => isset( $data['status'] ) ? $data['status'] : 'unknown',
            'plan_id'     => isset( $data['plan_id'] ) ? $data['plan_id'] : '',
            'last_used'   => isset( $data['last_used'] ) ? $data['last_used'] : null,
            'expires_at'  => isset( $data['expires_at'] ) ? $data['expires_at'] : null,
            'quota_used'  => isset( $data['quota_used'] ) ? $data['quota_used'] : 0,
            'quota_limit' => isset( $data['quota_limit'] ) ? $data['quota_limit'] : 0,
        );
    }

    /**
     * Revoke API key on license deactivation.
     *
     * @param string $install_id Freemius install ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function revoke_api_key( $install_id ) {
        $install_api_token = self::get_install_api_token();
        if ( ! $install_api_token ) {
            return new WP_Error( 'missing_token', __( 'Install API token not found.', 'cirrusly-commerce' ) );
        }

        $response = wp_remote_post( self::SERVICE_WORKER_URL, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $install_api_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'action'     => 'revoke_api_key',
                'install_id' => $install_id,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Cirrusly API Key Manager: Failed to revoke API key - ' . $response->get_error_message() );
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['success'] ) || ! $data['success'] ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Revocation failed', 'cirrusly-commerce' );
            return new WP_Error( 'revocation_failed', $error_message );
        }

        // Clear local key
        self::clear_api_key();

        return true;
    }

    /**
     * Update plan ID in service worker without regenerating key.
     * Called on plan changes/upgrades.
     *
     * @param string $install_id Freemius install ID
     * @param string $plan_id New Freemius plan ID
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function update_plan_id( $install_id, $plan_id ) {
        // Validate plan_id is one of our constants
        $valid_plans = array(
            self::PLAN_ID_FREE,
            self::PLAN_ID_PRO,
            self::PLAN_ID_PROPLUS,
        );

        // Default to Free if invalid plan_id provided
        if ( ! in_array( $plan_id, $valid_plans, true ) ) {
            $plan_id = self::PLAN_ID_FREE;
        }

        $install_api_token = self::get_install_api_token();
        if ( ! $install_api_token ) {
            return new WP_Error( 'missing_token', __( 'Install API token not found.', 'cirrusly-commerce' ) );
        }

        $response = wp_remote_post( self::SERVICE_WORKER_URL, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $install_api_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'action'     => 'update_plan_id',
                'install_id' => $install_id,
                'plan_id'    => $plan_id,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Cirrusly API Key Manager: Failed to update plan ID - ' . $response->get_error_message() );
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['success'] ) || ! $data['success'] ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Plan update failed', 'cirrusly-commerce' );
            return new WP_Error( 'plan_update_failed', $error_message );
        }

        // Update local plan info
        $config = get_option( 'cirrusly_scan_config', array() );
        $config['api_key_plan'] = $plan_id;
        update_option( 'cirrusly_scan_config', $config, false );

        return true;
    }

    /**
     * Link existing manual API key to Freemius install ID.
     * Migration endpoint for existing users.
     *
     * @param string $api_key Existing manual API key
     * @param string $install_id Freemius install ID to link to
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function link_manual_key( $api_key, $install_id ) {
        $install_api_token = self::get_install_api_token();
        if ( ! $install_api_token ) {
            return new WP_Error( 'missing_token', __( 'Install API token not found.', 'cirrusly-commerce' ) );
        }

        $response = wp_remote_post( self::SERVICE_WORKER_URL, array(
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $install_api_token,
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'action'     => 'link_key_to_install',
                'api_key'    => $api_key,
                'install_id' => $install_id,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'Cirrusly API Key Manager: Failed to link manual key - ' . $response->get_error_message() );
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! isset( $data['success'] ) || ! $data['success'] ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Key linking failed', 'cirrusly-commerce' );
            return new WP_Error( 'link_failed', $error_message );
        }

        // Update local settings to mark as auto-generated
        $config = get_option( 'cirrusly_scan_config', array() );
        $config['auto_generated'] = true;
        $config['api_key_generated'] = current_time( 'mysql' );
        update_option( 'cirrusly_scan_config', $config, false );

        return true;
    }

    /**
     * Get install API token from settings or Freemius.
     *
     * @return string|false Install API token or false if not found
     */
    private static function get_install_api_token() {
        // First, try to get from stored option
        $token = get_option( 'cirrusly_install_api_token' );

        if ( $token ) {
            return $token;
        }

        // If not stored, try to get from Freemius SDK
        if ( function_exists( 'cirrusly_fs' ) ) {
            $install = cirrusly_fs()->get_site();
            if ( $install && isset( $install->install_api_token ) ) {
                // Store for future use
                update_option( 'cirrusly_install_api_token', $install->install_api_token, false );
                return $install->install_api_token;
            }
        }

        return false;
    }

    /**
     * Get days remaining until regeneration is available.
     *
     * @return int Days remaining (0 if available now)
     */
    public static function get_regeneration_cooldown_days() {
        $config = get_option( 'cirrusly_scan_config', array() );
        $last_regenerated = isset( $config['api_key_last_regenerated'] ) ? $config['api_key_last_regenerated'] : '';

        if ( ! $last_regenerated ) {
            return 0;
        }

        $last_timestamp = strtotime( $last_regenerated );
        if ( false === $last_timestamp ) {
            return 0;
        }
    
        $cooldown_end = $last_timestamp + ( self::REGENERATION_COOLDOWN_DAYS * DAY_IN_SECONDS );
        $days_remaining = ceil( ( $cooldown_end - time() ) / DAY_IN_SECONDS );

        return max( 0, $days_remaining );
    }
}
