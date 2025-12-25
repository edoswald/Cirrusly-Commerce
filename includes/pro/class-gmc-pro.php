<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_GMC_Pro {

    /**
     * Initialize Pro features: AJAX endpoints and Save hooks.
     */
    public function __construct() {
        // AJAX Endpoints for Promotions (API)
        add_action( 'wp_ajax_cirrusly_list_promos_gmc', array( $this, 'handle_promo_api_list' ) );
        add_action( 'wp_ajax_cirrusly_submit_promo_to_gmc', array( $this, 'handle_promo_api_submit' ) );

        // Automation Hooks
        add_action( 'save_post_product', array( $this, 'check_compliance_on_save' ), 10, 3 );
        add_filter( 'wp_insert_post_data', array( $this, 'handle_auto_strip_on_save' ), 10, 2 );
        
        // GMC Disapproval Detection (runs after scan completion)
        add_action( 'cirrusly_gmc_scan_complete', array( $this, 'check_for_disapprovals' ), 10, 1 );
    }

    /**
     * Retrieve product-level issues reported by the Google Content API for the configured merchant.
     * Uses the service worker for API communication.
     */
    public static function fetch_google_real_statuses() {
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            return array();
        }

        // Call the service worker with gmc_scan action
        $result = Cirrusly_Commerce_Google_API_Client::request( 'gmc_scan', array() );
        
        if ( is_wp_error( $result ) ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Cirrusly Commerce: Service worker error in fetch_google_real_statuses - ' . $result->get_error_message() );
            }
            return array();
        }

        // Service worker returns: { "results": [ { "product_id": "...", "issues": [ { "msg": "...", "detail": "...", "type": "..." } ] } ] }
        $results = isset( $result['results'] ) ? $result['results'] : array();
        
        // Transform to indexed by product_id for deduplication logic in scan
        $google_issues = array();
        foreach ( $results as $item ) {
            if ( isset( $item['product_id'] ) && isset( $item['issues'] ) && is_array( $item['issues'] ) ) {
                $product_id = intval( $item['product_id'] );
                
                // Deduplicate issues for this product using signatures
                $cleaned_issues = array();
                $seen_signatures = array(); // Track which issues we've already added
                
                foreach ( $item['issues'] as $issue ) {
                    if ( is_array( $issue ) && isset( $issue['msg'] ) ) {
                        // Create a signature for this issue to detect duplicates
                        $issue_signature = md5( strtolower( trim( $issue['msg'] ) ) );
                        
                        // Skip if we've already seen this exact issue for this product
                        if ( in_array( $issue_signature, $seen_signatures, true ) ) {
                            continue;
                        }
                        
                        $seen_signatures[] = $issue_signature;
                        
                        // Normalize issue format: ensure 'type' and 'reason' keys exist
                        $cleaned_issues[] = array(
                            'type'   => isset( $issue['type'] ) ? $issue['type'] : 'warning',
                            'msg'    => $issue['msg'],
                            'reason' => isset( $issue['reason'] ) ? $issue['reason'] : ( isset( $issue['detail'] ) ? $issue['detail'] : 'Google API finding.' )
                        );
                    }
                }
                if ( ! empty( $cleaned_issues ) ) {
                    $google_issues[ $product_id ] = $cleaned_issues;
                }
            }
        }

        return $google_issues;
    }

    /**
     * Retrieve account-level status information (policy issues and suspensions) from the Google Content API.
     * Uses the service worker for API communication.
     */
    public static function fetch_google_account_issues() {
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            return new WP_Error( 'missing_client', 'Google API Client class not loaded.' );
        }

        // Call the service worker with fetch_account_status action
        $result = Cirrusly_Commerce_Google_API_Client::request( 'fetch_account_status', array() );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        // Service worker returns array format: { "issues": [ { "title": "...", "detail": "..." }, ... ] }
        return $result;
    }

    /**
     * List promotions from Google Merchant Center and emit a JSON AJAX response.
     */
    public function handle_promo_api_list() {
        // Get and verify nonce
        $nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'cirrusly_promo_api_list' ) ) {
            wp_send_json_error( 'Nonce verification failed.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        
        if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            wp_send_json_error( 'Pro version required.' );
        }

        try {
            // CACHE CHECK
            $force_raw = isset( $_POST['force_refresh'] ) ? sanitize_text_field( wp_unslash( $_POST['force_refresh'] ) ) : '';
            $force = '1' === $force_raw;
            $cache = get_transient( 'cirrusly_gmc_promos_cache' );
            
            if ( ! $force && false !== $cache ) {
                // Ensure cached value is an array (same type as fresh promotions)
                $cached_promotions = is_array( $cache ) ? $cache : array();
                wp_send_json_success( $cached_promotions );
                return;
            }

            if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
                wp_send_json_error( 'Google API Client missing.' );
            }

            // Call the service worker with promo_list action
            $result = Cirrusly_Commerce_Google_API_Client::request( 'promo_list', array() );
            
            if ( is_wp_error( $result ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Cirrusly Promotions List Error: ' . $result->get_error_message() );
                }
                wp_send_json_error( 'Failed to retrieve promotions: ' . $result->get_error_message() );
            }
            
            // Service worker returns: { "promotions": [ ... ], "success": true }
            $promotions = isset( $result['promotions'] ) ? $result['promotions'] : array();
            
            if ( ! is_array( $promotions ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Cirrusly Promotions: Invalid response format. Expected array, got: ' . gettype( $promotions ) );
                }
                wp_send_json_error( 'Invalid promotions data format from service worker.' );
            }
            
            set_transient( 'cirrusly_gmc_promos_cache', $promotions, 1 * HOUR_IN_SECONDS );
            wp_send_json_success( $promotions );
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Cirrusly Promotions List Exception: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() );
            }
            wp_send_json_error( 'An error occurred: ' . $e->getMessage() );
        }
    }

    /**
     * Handle an AJAX request to create and submit a Promotion to the Google Shopping Content API.
     */
    public function handle_promo_api_submit() {
        // Get and verify nonce with debug logging
        $nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'cirrusly_promo_api_submit' ) ) {
            wp_send_json_error( 'Nonce verification failed.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }
        
        if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            wp_send_json_error( 'Pro version required for API access.' );
        }

        try {

            if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
                wp_send_json_error( 'Google API Client missing.' );
            }

            // Extract and Sanitize POST data
            $raw_data = isset( $_POST['cirrusly_promo_data'] ) ? wp_unslash( $_POST['cirrusly_promo_data'] ) : array();
            $data  = is_array( $raw_data ) ? array_map( 'sanitize_text_field', $raw_data ) : array();

            $id    = isset( $data['id'] ) ? $data['id'] : '';
            $title = isset( $data['title'] ) ? $data['title'] : '';

            if ( '' === $id || '' === $title ) {
                wp_send_json_error( 'Promotion ID and Title are required.' );
            }
            
            $scan_config = get_option( 'cirrusly_scan_config', array() );
            $content_lang = isset( $scan_config['content_language'] ) ? $scan_config['content_language'] : substr( get_locale(), 0, 2 );
            $target_country = isset( $scan_config['target_country'] ) ? $scan_config['target_country'] : WC()->countries->get_base_country();
            
            // Build payload for service worker
            $payload = array(
                'id'              => $id,
                'title'           => $title,
                'content_lang'    => $content_lang,
                'target_country'  => $target_country,
                'applicability'   => isset( $data['app'] ) ? $data['app'] : 'ALL_PRODUCTS',
                'offer_type'      => isset( $data['type'] ) ? $data['type'] : 'NO_CODE',
                'generic_code'    => isset( $data['code'] ) ? $data['code'] : '',
            );
            
            // Parse dates if provided
            if ( ! empty( $data['dates'] ) && strpos( $data['dates'], '/' ) !== false ) {
                list( $start_date, $end_date ) = explode( '/', $data['dates'] );
                $payload['dates'] = array(
                    'start' => $start_date . 'T00:00:00Z',
                    'end'   => $end_date . 'T23:59:59Z'
                );
            }

            // Call the service worker with submit_promotion action
            $result = Cirrusly_Commerce_Google_API_Client::request( 'submit_promotion', $payload );
            
            if ( is_wp_error( $result ) ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( 'Cirrusly Promotion Submit Error: ' . $result->get_error_message() );
                }
                wp_send_json_error( 'Failed to submit promotion: ' . $result->get_error_message() );
            }
            
            // Clear promotion cache after submission
            delete_transient( 'cirrusly_gmc_promos_cache' );
            
            wp_send_json_success( array( 'msg' => 'Promotion submitted successfully.' ) );
        } catch ( Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Cirrusly Promotion Submit Exception: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine() );
            }
            wp_send_json_error( 'An error occurred: ' . $e->getMessage() );
        }
    }

    /**
     * Prevents publishing of products that contain monitored medical terms marked as "Critical".
     * Uses NLP verification if configured.
     */
    public function check_compliance_on_save( $post_id, $post, $update ) {
        if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            return;
        }

        unset( $update );

        $scan_cfg = get_option('cirrusly_scan_config', array());
        if ( empty($scan_cfg['block_on_critical']) || $scan_cfg['block_on_critical'] !== 'yes' ) return;

        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
        if ( $post->post_type !== 'product' ) return;

        // Uses static method from the main GMC class for term definitions
        $monitored = Cirrusly_Commerce_GMC::get_monitored_terms();
        $violation_found = false;

        $title_clean = wp_strip_all_tags( $post->post_title );
        $desc_clean  = wp_strip_all_tags( $post->post_content );

        // Scan all categories (Medical + Misrepresentation)
        foreach( $monitored as $cat => $terms ) {
            foreach( $terms as $word => $rules ) {
                 if ( ! isset( $rules['severity'] ) || 'Critical' !== $rules['severity'] ) {
                     continue;
                 }

                 // Respect case_sensitive flag: if true, don't use 'i' flag
                 $is_case_sensitive = isset( $rules['case_sensitive'] ) && $rules['case_sensitive'];
                 $modifiers = $is_case_sensitive ? 'u' : 'iu';
                 $pattern = '/\b' . preg_quote( $word, '/' ) . '\b/' . $modifiers;
                 
                 $check_title = preg_match( $pattern, $title_clean );
                 $check_desc  = ( isset( $rules['scope'] ) && 'all' === $rules['scope'] ) ? preg_match( $pattern, $desc_clean ) : false;

                 if ( $check_title || $check_desc ) {
                     $violation_found = true;
                     break 2; // Break both loops
                 }
            }
        }

        // --- NLP INTEGRATION (Blocker) ---
        if ( ! $violation_found && isset( $scan_cfg['enable_nlp_guard'] ) && 'yes' === $scan_cfg['enable_nlp_guard'] ) {
             $nlp_res = $this->analyze_text_with_nlp( $title_clean . ' ' . substr($desc_clean, 0, 500), $post_id );
             if ( ! is_wp_error( $nlp_res ) ) {
                 // Handle both service worker response format (array) and Google SDK format (object with methods)
                 $entities = array();
                 if ( is_array( $nlp_res ) ) {
                     $entities = $nlp_res;
                 } elseif ( is_object( $nlp_res ) ) {
                     if ( isset( $nlp_res->entities ) ) {
                         $entities = $nlp_res->entities;
                     } elseif ( method_exists( $nlp_res, 'getEntities' ) ) {
                         $entities = $nlp_res->getEntities();
                     }
                 }
                 
                 foreach ( $entities as $entity ) {
                     // Handle both array and object entity formats
                     $entity_type = is_array( $entity ) ? ( $entity['type'] ?? '' ) : ( is_object( $entity ) && method_exists( $entity, 'getType' ) ? $entity->getType() : '' );
                     $e_name = is_array( $entity ) ? ( $entity['name'] ?? '' ) : ( is_object( $entity ) && method_exists( $entity, 'getName' ) ? $entity->getName() : '' );
                     $e_name = strtolower( $e_name );
                     
                     // Check for restricted entity types 
                     if ( 'EVENT' === $entity_type || 'OTHER' === $entity_type ) {
                         if ( strpos( $e_name, 'virus' ) !== false || strpos( $e_name, 'covid' ) !== false ) {
                             $violation_found = true;
                             break;
                         }
                     }
                 }
             }
        }

        $original_status = get_post_field( 'post_status', $post_id, 'raw' );
        if ( $violation_found && in_array( $original_status, array( 'publish', 'pending', 'future' ) ) ) {
            remove_action( 'save_post_product', array( $this, 'check_compliance_on_save' ), 10 );
            wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
            if ( $original_status !== 'draft' ) {
                set_transient( 'cirrusly_gmc_blocked_save_' . get_current_user_id(), 'Product reverted to Draft due to restricted terms.', 30 );
            }
            add_action( 'save_post_product', array( $this, 'check_compliance_on_save' ), 10, 3 );
        }
    }

    /**
     * Remove configured banned medical terms from a product's title and content during save.
     */
    public function handle_auto_strip_on_save( $data, $postarr ) {
        $scan_cfg = get_option('cirrusly_scan_config', array());
        if ( empty($scan_cfg['auto_strip_banned']) || $scan_cfg['auto_strip_banned'] !== 'yes' ) return $data;
        if ( $data['post_type'] !== 'product' ) return $data;

        $monitored = Cirrusly_Commerce_GMC::get_monitored_terms();

        // Strip terms from all configured categories
        foreach ( $monitored as $cat => $terms ) {
            foreach ( $terms as $word => $rules ) {
                $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
                $data['post_title'] = preg_replace( $pattern, '', $data['post_title'] );
                $data['post_title'] = trim( preg_replace('/\s+/', ' ', $data['post_title']) );
                if ( isset( $rules['scope'] ) && $rules['scope'] === 'all' ) {
                     $data['post_content'] = preg_replace( $pattern, '', $data['post_content'] );
                }
            }
        }
        return $data;
    }

    /**
     * Helper to scan a product using NLP + Advanced Heuristics during the main Health Scan.
     * * @param WC_Product $product
     * @param array $existing_issues
     * @return array New issues found
     */
    public static function scan_product_with_nlp( $product, $existing_issues ) {
        $issues = array();

        // 1. Editorial Standards Check (No API Cost)
        $editorial_issues = self::detect_editorial_violations( $product );
        if ( ! empty( $editorial_issues ) ) {
            $issues = array_merge( $issues, $editorial_issues );
        }

        // 2. NLP-Based Misrepresentation Check
        $scan_cfg = get_option('cirrusly_scan_config', array());
        if ( empty($scan_cfg['enable_nlp_scan']) || $scan_cfg['enable_nlp_scan'] !== 'yes' ) {
            return $issues;
        }

        $instance = new self();
        $text = $product->get_name() . ' ' . $product->get_short_description();
        $result = $instance->analyze_text_with_nlp( $text, $product->get_id() );

        if ( ! is_wp_error( $result ) ) {
            // Check for Misrepresentation Entities
            $misrep_issues = self::detect_misrepresentation_nlp( $result );
            $issues = array_merge( $issues, $misrep_issues );
        }

        return $issues;
    }

    /**
     * Internal: Checks for Editorial & Professional Standards (Caps, Punctuation, Placeholders).
     */
    private static function detect_editorial_violations( $product ) {
        $issues = array();
        $text   = wp_strip_all_tags( $product->get_name() ); // Focus on Title mainly for Editorial

        // CHECK 1: Caps Lock Abuse
        if ( strlen( $text ) > 10 ) {
            // Count uppercase vs total letters (ignoring spaces/numbers)
            $letters = preg_replace( '/[^a-zA-Z]/', '', $text );
            if ( strlen( $letters ) > 0 ) {
                $upper = preg_match_all( '/[A-Z]/', $letters );
                $ratio = $upper / strlen( $letters );
                if ( $ratio > 0.85 ) {
                    $issues[] = array(
                        'type' => 'warning',
                        'msg'  => 'Editorial: Excessive Capitalization',
                        'reason' => 'Google requires professional formatting. Avoid ALL CAPS.'
                    );
                }
            }
        }

        // CHECK 2: Gimmicky Punctuation
        if ( preg_match( '/([!?.])\1{2,}/', $text ) ) { // Matches !!! or ...
             // Allow elipses, block !!! or ???
             if ( strpos( $text, '!!!' ) !== false || strpos( $text, '???' ) !== false ) {
                 $issues[] = array(
                     'type' => 'warning',
                     'msg'  => 'Editorial: Excessive Punctuation',
                     'reason' => 'Avoid gimmicky punctuation like "!!!" in titles.'
                 );
             }
        }

        // CHECK 3: Placeholder Text
        $desc = strtolower( wp_strip_all_tags( $product->get_description() ) );
        $placeholders = array( 'lorem ipsum', 'coming soon', 'test product', 'enter description' );
        foreach( $placeholders as $ph ) {
            if ( strpos( $desc, $ph ) !== false ) {
                $issues[] = array(
                    'type' => 'critical',
                    'msg'  => 'Editorial: Placeholder Text Detected',
                    'reason' => 'Product appears unfinished ("' . $ph . '").'
                );
                break; 
            }
        }

        return $issues;
    }

    /**
     * Internal: Analyzes NLP Entities for Misrepresentation/Trust signals.
     */
    private static function detect_misrepresentation_nlp( $nlp_result ) {
        $issues = array();
        $banned_orgs = array( 'fda', 'cdc', 'who', 'medicare', 'government' );

        // Handle both service worker response format (simple array) and Google SDK format (object with methods)
        $entities = array();
        if ( is_array( $nlp_result ) ) {
            $entities = $nlp_result;
        } elseif ( is_object( $nlp_result ) ) {
            if ( isset( $nlp_result->entities ) ) {
                $entities = $nlp_result->entities;
            } elseif ( method_exists( $nlp_result, 'getEntities' ) ) {
                $entities = $nlp_result->getEntities();
            }
        }

        foreach ( $entities as $entity ) {
            // Handle both array and object entity formats
            $type = is_array( $entity ) ? ( $entity['type'] ?? '' ) : ( is_object( $entity ) && method_exists( $entity, 'getType' ) ? $entity->getType() : '' );
            $name = is_array( $entity ) ? ( $entity['name'] ?? '' ) : ( is_object( $entity ) && method_exists( $entity, 'getName' ) ? $entity->getName() : '' );
            
            $name = strtolower( $name );

            // 1. False Affiliation (Organization)
            if ( 'ORGANIZATION' === $type && in_array( $name, $banned_orgs ) ) {
                $issues[] = array(
                    'type' => 'critical',
                    'msg'  => 'Misrepresentation: Implied Affiliation (' . strtoupper($name) . ')',
                    'reason' => 'Mentioning government/health orgs often triggers "False Affiliation" policies unless verified.'
                );
            }

            // 2. Sensitive Events (Virus/Pandemic)
            if ( 'EVENT' === $type ) {
                if ( strpos( $name, 'virus' ) !== false || strpos( $name, 'covid' ) !== false || strpos( $name, 'pandemic' ) !== false ) {
                    $entity_name = is_array( $entity ) ? ( $entity['name'] ?? 'Unknown' ) : ( is_object( $entity ) && method_exists( $entity, 'getName' ) ? $entity->getName() : 'Unknown' );
                    $issues[] = array(
                        'type'   => 'critical',
                        'msg'    => 'Sensitive Event Detected (NLP)',
                        'reason' => 'Reference to sensitive health event: ' . $entity_name
                    );
                }
            }
        }
        return $issues;
    }

    /**
     * Analyze plain text with Google Cloud Natural Language and extract entities.
     * Uses the service worker for API communication.
     * Caches results to Post Meta to prevent redundant API calls.
     */
    public function analyze_text_with_nlp( $text, $post_id ) {
        if ( ! is_numeric( $post_id ) || $post_id <= 0 ) {
            return new WP_Error( 'invalid_post_id', 'Valid Post ID required for NLP analysis.' );
        }

        $max_length = 5000;
        $text = wp_strip_all_tags( $text );
        if ( strlen( $text ) > $max_length ) {
            $truncated = substr( $text, 0, $max_length );
            $last_space = strrpos( $truncated, ' ' );
            $text = $last_space !== false ? substr( $truncated, 0, $last_space ) : $truncated;
        }

        // Check Cache
        $text_hash = md5( $text );
        $cached_data = get_post_meta( $post_id, '_cirrusly_nlp_cache', true );
        $cache_ttl = 7 * DAY_IN_SECONDS;
        if ( is_array( $cached_data ) && isset( $cached_data['hash'], $cached_data['time'] ) && $cached_data['hash'] === $text_hash && ( time() - $cached_data['time'] ) < $cache_ttl ) {
            // Normalize cached response to match fresh call return type (stdClass with entities property)
            if ( ! isset( $cached_data['response'] ) ) {
                return new WP_Error( 'cache_invalid', 'Cached NLP response invalid.' );
            }
            
            $cached_response = $cached_data['response'];
            
            // If cached response is already an object, return as-is
            if ( is_object( $cached_response ) ) {
                return $cached_response;
            }
            
            // If cached response is an array, wrap it in stdClass with entities property
            if ( is_array( $cached_response ) ) {
                $response_obj = new stdClass();
                $response_obj->entities = $cached_response;
                return $response_obj;
            }
            
            return new WP_Error( 'cache_invalid', 'Cached NLP response has unexpected type.' );
        }

        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            return new WP_Error( 'missing_client', 'Google API Client not loaded.' );
        }

        // Call the service worker with nlp_analyze action
        $payload = array( 'text' => $text );
        $result = Cirrusly_Commerce_Google_API_Client::request( 'nlp_analyze', $payload );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Service worker returns: { "entities": [ { "name": "...", "type": "...", "salience": ... } ] }
        $entities = isset( $result['entities'] ) ? $result['entities'] : array();
        
        // Cache the result
        update_post_meta( $post_id, '_cirrusly_nlp_cache', array(
            'hash'     => $text_hash,
            'response' => $entities,
            'time'     => time()
        ));
        
        // Return a simple object that has getEntities() method for backward compatibility
        $response_obj = new stdClass();
        $response_obj->entities = $entities;
        return $response_obj;
    }

    /**
     * Check for product disapprovals and send email alert
     * 
     * @param array $scan_results Results from GMC scan
     */
    public function check_for_disapprovals( $scan_results ) {
        // Ensure Mailer class is loaded
        if ( ! class_exists( 'Cirrusly_Commerce_Mailer' ) ) {
            require_once CIRRUSLY_COMMERCE_PATH . 'class-mailer.php';
        }

        // Check if email alerts are enabled
        if ( ! Cirrusly_Commerce_Mailer::is_email_enabled( 'gmc_disapproval' ) ) {
            return;
        }

        // Extract disapproved products from scan results
        $disapproved_products = array();
        
        if ( isset( $scan_results['google_issues'] ) && is_array( $scan_results['google_issues'] ) ) {
            foreach ( $scan_results['google_issues'] as $product_id => $issues ) {
                // Check if any issue indicates disapproval
                foreach ( $issues as $issue ) {
                    $msg = isset( $issue['msg'] ) ? strtolower( $issue['msg'] ) : '';
                    $type = isset( $issue['type'] ) ? $issue['type'] : '';
                    
                    // Detect disapproval patterns
                    if ( $type === 'error' || 
                         strpos( $msg, 'disapproved' ) !== false || 
                         strpos( $msg, 'suspended' ) !== false ||
                         strpos( $msg, 'not approved' ) !== false ) {
                        
                        $product = wc_get_product( $product_id );
                        if ( ! $product ) {
                            continue;
                        }
                        
                        $disapproved_products[] = array(
                            'product_id' => $product_id,
                            'product_name' => $product->get_name(),
                            'reason' => isset( $issue['msg'] ) ? $issue['msg'] : 'Unknown reason',
                            'detail' => isset( $issue['reason'] ) ? $issue['reason'] : '',
                            'sku' => $product->get_sku(),
                        );
                        
                        // Only record first disapproval reason per product
                        break;
                    }
                }
            }
        }

        // Send email if disapprovals found
        if ( ! empty( $disapproved_products ) ) {
            // Check if we've already alerted for these products recently (24-hour throttle)
            $last_alert_products = get_transient( 'cirrusly_last_disapproval_alert' );
            $new_disapprovals = array();
            
            foreach ( $disapproved_products as $product ) {
                $product_key = 'prod_' . $product['product_id'];
                if ( ! is_array( $last_alert_products ) || ! isset( $last_alert_products[ $product_key ] ) ) {
                    $new_disapprovals[] = $product;
                }
            }
            
            // Only send email if there are new disapprovals
            if ( ! empty( $new_disapprovals ) ) {
                $recipient = Cirrusly_Commerce_Mailer::get_recipient( 'gmc_disapproval' );
                $subject = 'GMC Alert: ' . count( $new_disapprovals ) . ' Product(s) Disapproved';
                
                Cirrusly_Commerce_Mailer::send_from_template(
                    $recipient,
                    'gmc-disapproval',
                    array( 'products' => $new_disapprovals ),
                    $subject
                );
                
                // Store alert timestamp for throttling
                $alert_record = is_array( $last_alert_products ) ? $last_alert_products : array();
                foreach ( $new_disapprovals as $product ) {
                    $alert_record[ 'prod_' . $product['product_id'] ] = time();
                }
                set_transient( 'cirrusly_last_disapproval_alert', $alert_record, 24 * HOUR_IN_SECONDS );
            }
        }
    }
}
