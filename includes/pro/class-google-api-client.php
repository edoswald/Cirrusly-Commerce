<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class Cirrusly_Commerce_Google_API_Client {

    const API_ENDPOINT = 'https://api.cirruslyweather.com/index.php';

    /**
     * GENERIC REQUEST METHOD - Routes all Google API calls through the service worker
     * call this like: self::request('nlp_analyze', ['text' => '...'], ['timeout' => 5])
     * @param string $action  The API action code.
     * @param array  $payload Data to send.
     * @param array  $args    Optional. Overrides for wp_remote_post args (e.g. timeout).
     * @return array|WP_Error Response from service worker or error object
     */
    public static function request( $action, $payload = array(), $args = array() ) {
        // 1. Get Google Credentials
        $json_key    = get_option( 'cirrusly_service_account_json' );
        $scan_config = get_option( 'cirrusly_scan_config', array() );
        $merchant_id = isset( $scan_config['merchant_id'] ) ? sanitize_text_field( $scan_config['merchant_id'] ) : '';

        if ( empty( $json_key ) ) return new WP_Error( 'missing_creds', 'Service Account JSON missing' );

        // 2. Get API Key (Replaces Freemius Token)
        // IMPORTANT: Don't sanitize API key - it's alphanumeric and must match database exactly
        $api_key = isset( $scan_config['api_key'] ) ? trim( $scan_config['api_key'] ) : '';

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_token', 'API License Key missing. Please enter it in Settings > General.' );
        }

        // DEBUG: Log API key for troubleshooting (only first/last 4 chars for security)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $key_preview = strlen( $api_key ) > 8 ? substr( $api_key, 0, 4 ) . '...' . substr( $api_key, -4 ) : 'TOO_SHORT';
            error_log( 'Cirrusly API Client: Sending request with API key: ' . $key_preview . ' (length: ' . strlen( $api_key ) . ')' );
        }

        // 3. Decrypt Google JSON
        $json_raw = Cirrusly_Commerce_Security::decrypt_data( $json_key );
        if ( ! $json_raw ) {
            $test = json_decode( $json_key, true );
            if ( isset( $test['private_key'] ) ) {
                $json_raw = $json_key; // It was unencrypted
            } else {
                return new WP_Error( 'decrypt_fail', 'Could not decrypt Google keys' );
            }
        }

        // 4. Build Body
        $body = array(
            'action'               => $action,
            'service_account_json' => $json_raw,
            'merchant_id'          => $merchant_id,
            'payload'              => $payload
        );
        
        // Debug logging (only in WP_DEBUG mode)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '=== API CLIENT DEBUG ===' );
            error_log( 'Action: ' . $action );
            error_log( 'Payload type: ' . gettype( $payload ) );
            error_log( 'Payload is array: ' . ( is_array( $payload ) ? 'YES' : 'NO' ) );
            if ( is_array( $payload ) ) {
                error_log( 'Payload keys: ' . implode( ', ', array_keys( $payload ) ) );
                error_log( 'Has image_url: ' . ( isset( $payload['image_url'] ) ? 'YES - ' . $payload['image_url'] : 'NO' ) );
            }
        }

        // 5. Send Request
        // Merge defaults with passed args (e.g. allow override of timeout)
        $default_headers = array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $api_key, // required
        );

        $request_args = wp_parse_args(
            $args,
            array(
                'headers' => array(),
                'timeout' => 45,
            )
        );
        
        // Body must not be overridable; always use the constructed payload.
        $request_args['body'] = wp_json_encode( $body );
        
        // Check if JSON encoding failed
        if ( false === $request_args['body'] ) {
            // Detailed field-level validation to identify problematic data
            $error_details = array();
            
            // Test each top-level field individually
            foreach ( $body as $field => $value ) {
                if ( false === json_encode( $value ) ) {
                    $error_details[] = $field;
                }
            }
            
            // Build detailed error message
            $error_msg = 'Failed to encode request body as JSON.';
            if ( ! empty( $error_details ) ) {
                $error_msg .= ' Problem fields: ' . implode( ', ', $error_details );
            }
            
            // Log for debugging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Cirrusly JSON Encoding Error: ' . $error_msg );
                error_log( 'JSON Error Code: ' . json_last_error() );
                error_log( 'JSON Error Message: ' . json_last_error_msg() );
            }
            
            return new WP_Error( 'json_encode_failed', $error_msg );
        }

        // Merge headers but keep required defaults (defaults override user values).
        $user_headers = is_array( $request_args['headers'] ) ? $request_args['headers'] : array();
        $request_args['headers'] = array_merge( $user_headers, $default_headers );

        $response = wp_remote_post( self::API_ENDPOINT, $request_args );

        if ( is_wp_error( $response ) ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log( 'Cirrusly API Client Error: ' . $response->get_error_message() );
            }
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code( $response );
        $raw_body = wp_remote_retrieve_body( $response );
        
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log( 'Cirrusly API Response Code: ' . $code );
            error_log( 'Cirrusly API Response Body: ' . substr( $raw_body, 0, 500 ) );
        }
        
        $res_body = json_decode( $raw_body, true );
    
        // Check for JSON decode errors (null return with error code)
        if ( null === $res_body && json_last_error() !== JSON_ERROR_NONE ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log( 'Cirrusly API: Failed to decode JSON response. Error: ' . json_last_error_msg() );
                error_log( 'Cirrusly API: Raw response: ' . substr( $raw_body, 0, 200 ) );
            }
            $error_msg = 'API returned invalid JSON: ' . json_last_error_msg();
            $error_msg .= ' | Raw response (first 500 chars): ' . substr( $raw_body, 0, 500 );
            return new WP_Error( 'invalid_response', $error_msg );
        }
        
        // Allow both arrays and null (valid empty response)
        if ( ! is_array( $res_body ) ) {
            $res_body = array();
        }

        if ( $code !== 200 ) {
            // Pass through the error from the worker (e.g., "Invalid License")
            return new WP_Error( 'api_error', 'Cloud Error: ' . (isset($res_body['error']) ? $res_body['error'] : 'Unknown') );
        }

        // Track successful API usage
        self::track_api_usage( $action, 1 );

        return $res_body;
    }

    /**
     * Wrapper for the Daily Scan
     */
    public static function execute_scheduled_scan() {
        $result = self::request( 'gmc_scan' );
    
        if ( is_wp_error( $result ) ) {
            error_log( 'Cirrusly Commerce GMC Scan failed: ' . $result->get_error_message() );
            return;
        }
    
        if ( isset( $result['results'] ) ) {
            $scan_result = array( 'timestamp' => time(), 'results' => $result['results'] );
            update_option( 'cirrusly_gmc_scan_data', $scan_result, false );
            
            // Fire action hook for other features to respond to scan completion
            do_action( 'cirrusly_gmc_scan_complete', $scan_result );
        }
    }

    /**
     * Track API usage for quota management
     * 
     * Records API usage statistics without enforcing quota limits.
     * Use get_quota_status() to check if quota has been exceeded.
     * 
     * @param string $action The API action being called
     * @param int    $cost   API call cost (default: 1)
     * @return bool Always returns true (does not enforce quota)
     */
    public static function track_api_usage( $action, $cost = 1 ) {
        $option_key = 'cirrusly_gmc_api_calls_today';
        $usage = get_option( $option_key );
        
        // Initialize or increment usage
        if ( false === $usage ) {
            // New day - initialize with midnight expiry (site timezone)
            $tomorrow_midnight = ( new DateTime( 'tomorrow midnight', wp_timezone() ) )->getTimestamp();
            $usage = array(
                'total' => $cost,
                'by_action' => array( $action => $cost ),
                'reset_time' => $tomorrow_midnight,
                'date' => date( 'Y-m-d' )
            );
            update_option( $option_key, $usage, false );
        } else {
            // Check if it's a new day
            $current_date = date( 'Y-m-d' );
            if ( isset( $usage['date'] ) && $usage['date'] !== $current_date ) {
                // Reset for new day (site timezone)
                $tomorrow_midnight = ( new DateTime( 'tomorrow midnight', wp_timezone() ) )->getTimestamp();
                $usage = array(
                    'total' => $cost,
                    'by_action' => array( $action => $cost ),
                    'reset_time' => $tomorrow_midnight,
                    'date' => $current_date
                );
            } else {
                // Increment existing
                $usage['total'] += $cost;
                $usage['by_action'][ $action ] = isset( $usage['by_action'][ $action ] ) ? 
                                                 $usage['by_action'][ $action ] + $cost : $cost;
            }
            update_option( $option_key, $usage, false );
        }
        
        return true;
    }

    /**
     * Get current quota status
     * 
     * @return array Array with 'used', 'limit', 'remaining', 'percentage', 'by_action', 'reset_time'
     */
    public static function get_quota_status() {
        // Determine quota limit based on Freemius plan
        $limit = 50; // Free tier default
        
        if ( function_exists( 'cirrusly_fs' ) && cirrusly_fs()->is_plan( 'proplus', true ) ) {
            $limit = 2500; // Pro Plus / Agency tier
        } elseif ( function_exists( 'cirrusly_fs' ) && cirrusly_fs()->can_use_premium_code() ) {
            $limit = 500; // Pro tier
        }
        
        $usage = get_option( 'cirrusly_gmc_api_calls_today' );
        $used = ( $usage && isset( $usage['total'] ) ) ? $usage['total'] : 0;
        $remaining = max( 0, $limit - $used );
        $percentage = ( $limit > 0 ) ? round( ( $used / $limit ) * 100, 1 ) : 0;
        
        return array(
            'used' => $used,
            'limit' => $limit,
            'remaining' => $remaining,
            'percentage' => $percentage,
            'by_action' => ( $usage && isset( $usage['by_action'] ) ) ? $usage['by_action'] : array(),
            'reset_time' => ( $usage && isset( $usage['reset_time'] ) ) ? $usage['reset_time'] : ( new DateTime( 'tomorrow midnight', wp_timezone() ) )->getTimestamp()
        );
    }

    /**
     * Fetch GMC product performance analytics
     * 
     * @param string $start_date Start date (Y-m-d format)
     * @param string $end_date   End date (Y-m-d format)
     * @param string $page_token Optional pagination token
     * @return array|WP_Error Analytics data or error
     */
    public static function fetch_product_analytics( $start_date, $end_date, $page_token = null ) {
        // Check quota before making call
        $quota = self::get_quota_status();
        if ( $quota['percentage'] >= 95 ) {
            return new WP_Error( 
                'quota_exceeded', 
                sprintf( 
                    __( 'Daily API quota exceeded (%d/%d calls). Resets in %s.', 'cirrusly-commerce' ),
                    $quota['used'],
                    $quota['limit'],
                    human_time_diff( time(), $quota['reset_time'] )
                )
            );
        }
        
        $result = self::request( 
            'gmc_analytics_products', 
            array(
                'start_date' => $start_date,
                'end_date' => $end_date,
                'page_token' => $page_token
            )
        );
        
        return $result;
    }

    /**
     * Fetch GMC price competitiveness data
     * 
     * @param string $page_token Optional pagination token
     * @return array|WP_Error Pricing data or error
     */
    public static function fetch_pricing_analytics( $page_token = null ) {
        // Check quota before making call
        $quota = self::get_quota_status();
        if ( $quota['percentage'] >= 95 ) {
            return new WP_Error( 
                'quota_exceeded', 
                sprintf( 
                    __( 'Daily API quota exceeded (%d/%d calls). Resets in %s.', 'cirrusly-commerce' ),
                    $quota['used'],
                    $quota['limit'],
                    human_time_diff( time(), $quota['reset_time'] )
                )
            );
        }
        
        $result = self::request( 
            'gmc_analytics_pricing', 
            array( 'page_token' => $page_token )
        );
        
        return $result;
    }
}
