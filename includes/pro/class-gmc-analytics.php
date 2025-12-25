<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_GMC_Analytics {

    /**
     * Initialize GMC Analytics integration
     */
    public function __construct() {
        // Register daily sync cron
        add_action( 'cirrusly_gmc_daily_sync', array( $this, 'sync_daily_gmc_data' ) );
        
        // Register initial import cron (scheduled via AJAX, executed by WP cron)
        add_action( 'cirrusly_run_initial_import', array( $this, 'run_initial_import' ) );
        
        // Schedule cron if not already scheduled - use option flag to avoid repeated checks
        if ( ! get_option( 'cirrusly_gmc_daily_sync_scheduled', false ) ) {
            if ( ! wp_next_scheduled( 'cirrusly_gmc_daily_sync' ) ) {
                $scheduled = wp_schedule_event( strtotime( 'tomorrow 2:00am' ), 'daily', 'cirrusly_gmc_daily_sync' );

                if ( false !== $scheduled ) {
                    // Only set flag on successful scheduling
                    update_option( 'cirrusly_gmc_daily_sync_scheduled', true, false );
                } else {
                    // Log error for debugging - don't set flag so retry is possible
                    error_log( 'Cirrusly GMC Analytics: Failed to schedule daily sync cron event' );
                }
            } else {
                // Cron is already scheduled - sync the flag state
                update_option( 'cirrusly_gmc_daily_sync_scheduled', true, false );
            }
        } else {
            // Periodically verify cron still exists (e.g., once per week)
            $last_verify = get_option( 'cirrusly_gmc_cron_last_verify', 0 );
            if ( time() - $last_verify > WEEK_IN_SECONDS ) {
                if ( ! wp_next_scheduled( 'cirrusly_gmc_daily_sync' ) ) {
                    // Cron was removed - reset flag to allow rescheduling
                    delete_option( 'cirrusly_gmc_daily_sync_scheduled' );
                    error_log( 'Cirrusly GMC Analytics: Daily sync cron was removed, flag reset for rescheduling' );
                }
                update_option( 'cirrusly_gmc_cron_last_verify', time(), false );
            }
        }
        
        // AJAX endpoints
        add_action( 'wp_ajax_cirrusly_import_progress', array( $this, 'ajax_get_import_progress' ) );
        add_action( 'wp_ajax_cirrusly_start_gmc_import', array( $this, 'ajax_start_initial_import' ) );
    }

    /**
     * Sync GMC analytics data
     * 
     * @param int         $days             Number of days to sync (default: 1 for daily)
     * @param bool        $is_initial_import Whether this is the initial 90-day import
     * @param string|null $start_date       Optional explicit start date (Y-m-d format)
     * @param string|null $end_date         Optional explicit end date (Y-m-d format)
     * @return int|WP_Error Number of products processed on success, WP_Error on failure
     */
    public function sync_analytics_data( $days = 1, $is_initial_import = false, $start_date = null, $end_date = null ) {
        // Use explicit dates if provided, otherwise calculate from days offset
        if ( null === $end_date ) {
            $end_date = date( 'Y-m-d', strtotime( '-1 day' ) ); // Yesterday
        }
        if ( null === $start_date ) {
            $start_date = date( 'Y-m-d', strtotime( "-{$days} days" ) );
        }
        
        // Log import type for debugging
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            $import_type = $is_initial_import ? 'INITIAL IMPORT' : 'DAILY SYNC';
            error_log( sprintf( 
                'Cirrusly GMC Analytics: %s - Fetching %d days (%s to %s)', 
                $import_type,
                $days,
                $start_date,
                $end_date
            ) );
        }
        
        $all_products = array();
        $all_pricing = array();
        $page_token = null;
        
        // Fetch product analytics (paginated)
        do {
            $result = Cirrusly_Commerce_Google_API_Client::fetch_product_analytics( 
                $start_date, 
                $end_date, 
                $page_token 
            );
            
            if ( is_wp_error( $result ) ) {
                $this->log_sync_error( $result->get_error_message() );
                return $result;
            }
            
            if ( isset( $result['products'] ) && is_array( $result['products'] ) ) {
                $all_products = array_merge( $all_products, $result['products'] );
            }
            
            $page_token = isset( $result['next_page_token'] ) ? $result['next_page_token'] : null;
            
        } while ( $page_token );
        
        // Fetch pricing data (paginated)
        $page_token = null;
        do {
            $result = Cirrusly_Commerce_Google_API_Client::fetch_pricing_analytics( $page_token );
            
            if ( is_wp_error( $result ) ) {
                // Pricing data is optional - log but don't fail sync
                error_log( 'Cirrusly GMC Pricing sync failed: ' . $result->get_error_message() );
            } else {
                if ( isset( $result['pricing'] ) && is_array( $result['pricing'] ) ) {
                    $all_pricing = array_merge( $all_pricing, $result['pricing'] );
                }
                $page_token = isset( $result['next_page_token'] ) ? $result['next_page_token'] : null;
            }
            
        } while ( $page_token );
        
        // Process and map data
        $mapped_data = $this->map_gmc_to_woocommerce( $all_products, $all_pricing );
        
        // Store daily data (with import type metadata)
        $this->store_daily_analytics( $mapped_data, $start_date, $end_date, $is_initial_import );
        
        // Update history snapshot
        $this->update_gmc_history( $mapped_data );
        
        // Clear sync errors on success
        delete_option( 'cirrusly_gmc_sync_errors' );
        
        // Log completion for initial imports
        if ( $is_initial_import && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( sprintf( 
                'Cirrusly GMC Analytics: Initial import batch completed - %d products processed', 
                count( $mapped_data )
            ) );
        }
        
        // Return count of products processed
        return count( $mapped_data );
    }

    /**
     * Map GMC data to WooCommerce products
     * 
     * @param array $products GMC product analytics data
     * @param array $pricing  GMC pricing data
     * @return array Mapped data by product ID
     */
    private function map_gmc_to_woocommerce( $products, $pricing ) {
        $mapped = array();
        $unmapped = array();
        $manual_mappings = get_option( 'cirrusly_gmc_product_mapping', array() );
        
        // Index pricing data by offer_id for quick lookup
        $pricing_index = array();
        foreach ( $pricing as $p ) {
            $pricing_index[ $p['offer_id'] ] = $p;
        }
        
        // Map products
        foreach ( $products as $product ) {
            $offer_id = $product['offer_id'];
            $product_id = null;
            
            // Try manual mapping first
            if ( isset( $manual_mappings[ $offer_id ] ) ) {
                $product_id = $manual_mappings[ $offer_id ];
            } else {
                // Try auto-mapping via SKU
                $product_id = wc_get_product_id_by_sku( $offer_id );
                
                // If SKU match fails, try direct product ID match
                if ( ! $product_id && is_numeric( $offer_id ) ) {
                    $temp_product = wc_get_product( intval( $offer_id ) );
                    if ( $temp_product && $temp_product->get_id() > 0 ) {
                        $product_id = $temp_product->get_id();
                    }
                }
            }
            
            if ( ! $product_id ) {
                // Log unmapped product
                $unmapped[ $offer_id ] = $product['title'];
                continue;
            }
            
            // Get WooCommerce product data
            $wc_product = wc_get_product( $product_id );
            if ( ! $wc_product ) {
                continue;
            }
            
            // Build mapped data
            $mapped[ $product_id ] = array(
                'offer_id' => $offer_id,
                'gmc' => array(
                    'clicks' => $product['clicks'],
                    'impressions' => $product['impressions'],
                    'conversions' => $product['conversions'],
                    'conversion_value' => $product['conversion_value'] ?? 0,
                    'ctr' => $product['ctr']
                ),
                'wc' => array(
                    'price' => (float) $wc_product->get_price(),
                    'regular_price' => (float) $wc_product->get_regular_price(),
                    'sale_price' => (float) $wc_product->get_sale_price()
                ),
                'pricing' => isset( $pricing_index[ $offer_id ] ) ? $pricing_index[ $offer_id ] : null
            );
            
            // Calculate ROAS if we have conversion value
            if ( isset($product['conversion_value']) && $product['conversion_value'] > 0 && $product['clicks'] > 0 ) {
                // Estimate ad spend (assuming avg CPC of $0.50 - this is a fallback)
                $estimated_spend = $product['clicks'] * 0.50;
                $mapped[ $product_id ]['roas'] = round( $product['conversion_value'] / $estimated_spend, 2 );
            }
        }
        
        // Update unmapped products list with deduplication and size limiting
        if ( ! empty( $unmapped ) ) {
            $existing_unmapped = get_option( 'cirrusly_gmc_unmapped_products', array() );
            
            // Determine if there are NEW unmapped products for email alert
            $new_unmapped_products = array();
            foreach ( $unmapped as $offer_id => $title ) {
                if ( ! isset( $existing_unmapped[ $offer_id ] ) ) {
                    $new_unmapped_products[ $offer_id ] = $title;
                }
            }
            
            // Merge existing and new unmapped products
            $merged_unmapped = array_merge( $existing_unmapped, $unmapped );
            
            // Remove duplicates (preserve keys for offer_id => title mapping)
            $merged_unmapped = array_unique( $merged_unmapped, SORT_REGULAR );
            
            // Limit to maximum 1000 unmapped products to prevent unbounded growth
            // Keep most recent entries (array_slice preserves keys)
            $max_unmapped = apply_filters( 'cirrusly_max_unmapped_products', 1000 );
            if ( count( $merged_unmapped ) > $max_unmapped ) {
                $merged_unmapped = array_slice( $merged_unmapped, -$max_unmapped, null, true );
            }
            
            update_option( 'cirrusly_gmc_unmapped_products', $merged_unmapped, false );
            
            // Send email alert for NEW unmapped products
            if ( ! empty( $new_unmapped_products ) ) {
                $this->send_unmapped_products_alert( $new_unmapped_products );
            }
        }
        
        return $mapped;
    }

    /**
     * Send email alert for newly detected unmapped products
     * 
     * @param array $new_unmapped Array of offer_id => title pairs
     */
    private function send_unmapped_products_alert( $new_unmapped ) {
        // Ensure Mailer class is loaded
        if ( ! class_exists( 'Cirrusly_Commerce_Mailer' ) ) {
            require_once CIRRUSLY_COMMERCE_PATH . 'class-mailer.php';
        }

        // Check if email alerts are enabled
        if ( ! Cirrusly_Commerce_Mailer::is_email_enabled( 'unmapped_products' ) ) {
            return;
        }

        // Throttle: Only send email once per day
        $last_alert_time = get_transient( 'cirrusly_last_unmapped_alert' );
        if ( false !== $last_alert_time ) {
            return; // Already sent alert today
        }

        // Format products for email template
        $products_array = array();
        foreach ( $new_unmapped as $offer_id => $title ) {
            $products_array[] = array(
                'product_id' => $offer_id,
                'product_name' => $title,
                'sku' => '', // GMC data doesn't include SKU
            );
        }

        // Send email
        $recipient = Cirrusly_Commerce_Mailer::get_recipient( 'unmapped_products' );
        $subject = 'GMC Alert: ' . count( $products_array ) . ' Unmapped Product(s) Detected';
        
        Cirrusly_Commerce_Mailer::send_from_template(
            $recipient,
            'unmapped-products',
            array(
                'count' => count( $products_array ),
                'products' => $products_array,
            ),
            $subject
        );

        // Set 24-hour throttle
        set_transient( 'cirrusly_last_unmapped_alert', time(), 24 * HOUR_IN_SECONDS );
    }

    /**
     * Store daily analytics data
     * 
     * @param array  $data             Mapped analytics data
     * @param string $start_date       Start date
     * @param string $end_date         End date
     * @param bool   $is_initial_import Whether this is part of initial import
     */
    private function store_daily_analytics( $data, $start_date, $end_date, $is_initial_import = false ) {
        // For daily sync, use single date key
        $date_key = ( $start_date === $end_date ) ? $start_date : $end_date;
        
        // Use update_option instead of set_transient to ensure database persistence
        // (Redis/Memcached intercepts transients and prevents DB storage)
        $option_key = 'cirrusly_gmc_analytics_daily_' . $date_key;
        update_option( $option_key, $data, false );
        
        // If data is older than 30 days, archive it as weekly aggregate
        if ( strtotime( $date_key ) < strtotime( '-30 days' ) ) {
            $this->archive_old_data( $data, $date_key );
        }
    }

    /**
     * Archive old data as weekly aggregates
     * 
     * @param array  $data Mapped analytics data
     * @param string $date Date of the data
     */
    private function archive_old_data( $data, $date ) {
        $week_key = date( 'Y-W', strtotime( $date ) ); // Year-Week format (e.g., 2025-50)
        $archive = get_option( 'cirrusly_gmc_analytics_archive', array() );
        
        if ( ! isset( $archive[ $week_key ] ) ) {
            $archive[ $week_key ] = array();
        }
        
        // Aggregate data by product
        foreach ( $data as $product_id => $product_data ) {
            if ( ! isset( $archive[ $week_key ][ $product_id ] ) ) {
                $archive[ $week_key ][ $product_id ] = array(
                    'clicks' => 0,
                    'impressions' => 0,
                    'conversions' => 0,
                    'conversion_value' => 0
                );
            }
            
            $archive[ $week_key ][ $product_id ]['clicks'] += $product_data['gmc']['clicks'];
            $archive[ $week_key ][ $product_id ]['impressions'] += $product_data['gmc']['impressions'];
            $archive[ $week_key ][ $product_id ]['conversions'] += $product_data['gmc']['conversions'];
            $archive[ $week_key ][ $product_id ]['conversion_value'] += $product_data['gmc']['conversion_value'];
        }
        
        // Keep only last 12 weeks (3 months) of archived data
        $archive_keys = array_keys( $archive );
        rsort( $archive_keys );
        $archive_keys_to_keep = array_slice( $archive_keys, 0, 12 );
        $archive = array_intersect_key( $archive, array_flip( $archive_keys_to_keep ) );
        
        // Compress and store (gzip compression for large datasets)
        update_option( 'cirrusly_gmc_analytics_archive', $archive, false );
    }

    /**
     * Update GMC history snapshot
     * 
     * @param array $data Mapped analytics data
     */
    private function update_gmc_history( $data ) {
        $history = get_option( 'cirrusly_gmc_history', array() );
        $today = date( 'Y-m-d' );
        
        // Calculate totals
        $total_clicks = 0;
        $total_conversions = 0;
        $products_above_benchmark = 0;
        $total_price_diff = 0;
        $price_count = 0;
        
        foreach ( $data as $product_data ) {
            $total_clicks += $product_data['gmc']['clicks'];
            $total_conversions += $product_data['gmc']['conversions'];
            
            if ( isset( $product_data['pricing'] ) && $product_data['pricing'] ) {
                if ( isset($product_data['pricing']['competitiveness']) && $product_data['pricing']['competitiveness'] === 'too_high' ) {
                    $products_above_benchmark++;
                }
                $total_price_diff += $product_data['pricing']['price_diff_pct'] ?? 0;
                $price_count++;
            }
        }
        
        $avg_price_score = ( $price_count > 0 ) ? round( $total_price_diff / $price_count, 2 ) : 0;
        
        $history[ $today ] = array(
            'clicks' => $total_clicks,
            'conversions' => $total_conversions,
            'price_score' => $avg_price_score,
            'products_above_benchmark' => $products_above_benchmark
        );
        
        // Keep only last 90 days of history
        $history_keys = array_keys( $history );
        rsort( $history_keys );
        $history_keys_to_keep = array_slice( $history_keys, 0, 90 );
        $history = array_intersect_key( $history, array_flip( $history_keys_to_keep ) );
        
        update_option( 'cirrusly_gmc_history', $history, false );
    }

    /**
     * Daily sync handler (cron callback)
     */
    public function sync_daily_gmc_data() {
        $result = $this->sync_analytics_data( 1, false );
        
        if ( is_wp_error( $result ) ) {
            error_log( 'Cirrusly GMC daily sync failed: ' . $result->get_error_message() );
        } elseif ( is_int( $result ) ) {
            error_log( sprintf( 'Cirrusly GMC daily sync completed: %d products processed', $result ) );
        }
    }

    /**
     * Run initial 90-day import
     * 
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function run_initial_import() {
        // ALWAYS log import start (critical for debugging)
        error_log( '=== Cirrusly GMC Analytics: Initial Import STARTED ===' );
        
        // Check if already imported
        if ( get_option( 'cirrusly_gmc_analytics_imported' ) ) {
            error_log( 'Cirrusly GMC Analytics: Import already completed' );
            return new WP_Error( 'already_imported', __( 'Initial import already completed.', 'cirrusly-commerce' ) );
        }
        
        // Set progress tracking (using option instead of transient for Redis compatibility)
        $progress = array(
            'status' => 'running',
            'current_batch' => 0,
            'total_batches' => 9,
            'products_processed' => 0,
            'errors' => array()
        );
        update_option( 'cirrusly_gmc_import_progress', $progress, false );
        error_log( 'Cirrusly GMC Analytics: Progress tracking initialized - starting 9 batches' );
        
        // Import in 10-day batches (9 batches total for 90 days)
        for ( $batch = 0; $batch < 9; $batch++ ) {
            $days_offset_start = ( 8 - $batch ) * 10 + 1; // Start from oldest
            $days_offset_end = ( 8 - $batch ) * 10 + 10;
            
            // Calculate actual date range for this batch
            $batch_start_date = date( 'Y-m-d', strtotime( "-{$days_offset_end} days" ) );
            $batch_end_date = date( 'Y-m-d', strtotime( "-{$days_offset_start} days" ) );
            
            // ALWAYS log batch start
            error_log( sprintf( 'Cirrusly GMC Analytics: Starting batch %d/%d (%s to %s)', $batch + 1, 9, $batch_start_date, $batch_end_date ) );
            
            // Fetch this specific 10-day historical period
            $result = $this->sync_analytics_data( 10, true, $batch_start_date, $batch_end_date );
            
            if ( is_wp_error( $result ) ) {
                // ALWAYS log errors (critical)
                error_log( 'Cirrusly GMC Analytics: Batch ' . ($batch + 1) . ' FAILED - ' . $result->get_error_message() );
                $progress['status'] = 'error';
                $progress['errors'][] = $result->get_error_message();
                update_option( 'cirrusly_gmc_import_progress', $progress, false );
                return $result;
            }
            
            // Update progress (result is now count of products processed)
            $progress['current_batch'] = $batch + 1;
            $processed = (int) $result;
            $progress['products_processed'] += $processed;
            update_option( 'cirrusly_gmc_import_progress', $progress, false );
            
            // ALWAYS log batch completion
            error_log( sprintf( 'Cirrusly GMC Analytics: Batch %d/%d completed - %d products processed (total: %d)', $batch + 1, 9, $processed, $progress['products_processed'] ) );
            
            // Delay between batches to avoid rate limits
            if ( $batch < 8 ) {
                sleep( 2 );
            }
        }
        
        // Mark as completed
        $progress['status'] = 'completed';
        update_option( 'cirrusly_gmc_import_progress', $progress, false );
        update_option( 'cirrusly_gmc_analytics_imported', time() );
        
        // ALWAYS log completion
        error_log( '=== Cirrusly GMC Analytics: Initial Import COMPLETED - Total products: ' . $progress['products_processed'] . ' ===' );
        
        // Send completion email
        $this->send_import_completion_email( $progress );
        
        return true;
    }

    /**
     * AJAX handler for import progress
     */
    public function ajax_get_import_progress() {
        check_ajax_referer( 'cirrusly_import_progress', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'cirrusly-commerce' ) ) );
        }
        
        $progress = get_option( 'cirrusly_gmc_import_progress' );
        
        if ( false === $progress ) {
            wp_send_json_success( array( 'status' => 'not_started' ) );
        } else {
            wp_send_json_success( $progress );
        }
    }

    /**
     * AJAX handler to start initial import
     */
    public function ajax_start_initial_import() {
        check_ajax_referer( 'cirrusly_start_import', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'cirrusly-commerce' ) ) );
        }
        
        // Try background cron first
        wp_schedule_single_event( time(), 'cirrusly_run_initial_import' );
        spawn_cron();
        
        // FALLBACK: If spawn_cron() fails (some hosts block it), run directly
        // This will timeout after 60 seconds but the import will continue
        // Check if cron executed by waiting briefly
        sleep( 1 );
        $progress = get_option( 'cirrusly_gmc_import_progress' );
        
        if ( false === $progress || empty( $progress ) ) {
            // Cron didn't fire - run directly (will timeout but import continues)
            error_log( 'Cirrusly GMC Analytics: spawn_cron() failed, running import directly' );
            $this->run_initial_import();
        }
        
        wp_send_json_success( array( 'message' => __( 'Import started', 'cirrusly-commerce' ) ) );
    }

    /**
     * Send import completion email
     * 
     * @param array $progress Progress data
     */
    private function send_import_completion_email( $progress ) {
        // Ensure Mailer is loaded
        if ( ! class_exists( 'Cirrusly_Commerce_Mailer' ) ) {
            require_once dirname( dirname( plugin_dir_path( __FILE__ ) ) ) . '/class-mailer.php';
        }
        
        $to = Cirrusly_Commerce_Mailer::get_recipient( 'gmc_import' );
        
        // Check if email type is enabled
        if ( ! Cirrusly_Commerce_Mailer::is_email_enabled( 'gmc_import' ) ) {
            return;
        }
        
        $subject = __( 'Cirrusly Commerce: GMC Analytics Import Complete', 'cirrusly-commerce' );
        
        Cirrusly_Commerce_Mailer::send_from_template(
            $to,
            'gmc-import-complete',
            array( 'progress' => $progress ),
            $subject
        );
    }

    /**
     * Log sync error
     * 
     * @param string $message Error message
     */
    private function log_sync_error( $message ) {
        // ALWAYS log errors (remove WP_DEBUG requirement for critical errors)
        error_log( 'Cirrusly Pro Plus Analytics Error: ' . $message );
        
        $errors = get_option( 'cirrusly_gmc_sync_errors', array() );
        
        $errors[] = array(
            'timestamp' => time(),
            'message' => $message
        );
        
        // Keep only last 10 errors
        $errors = array_slice( $errors, -10 );
        
        update_option( 'cirrusly_gmc_sync_errors', $errors );
    }

    /**
     * Get compiled GMC analytics for a date range
     * 
     * @param array $date_range Array with 'start' and 'end' dates
     * @return array Compiled analytics data
     */
    public static function get_gmc_analytics_compiled( $date_range ) {
        $start = isset( $date_range['start'] ) ? $date_range['start'] : date( 'Y-m-d', strtotime( '-30 days' ) );
        $end = isset( $date_range['end'] ) ? $date_range['end'] : date( 'Y-m-d' );
        
        $compiled = array();
        
        // Fetch daily data for last 30 days
        $current_date = strtotime( $start );
        $end_timestamp = strtotime( $end );
        
        while ( $current_date <= $end_timestamp ) {
            $date_key = date( 'Y-m-d', $current_date );
            $option_key = 'cirrusly_gmc_analytics_daily_' . $date_key;
            $daily_data = get_option( $option_key );
            
            if ( $daily_data && is_array( $daily_data ) ) {
                // Merge daily data into compiled array
                foreach ( $daily_data as $product_id => $data ) {
                    if ( ! isset( $compiled[ $product_id ] ) ) {
                        $compiled[ $product_id ] = array(
                            'gmc' => array(
                                'clicks' => 0,
                                'impressions' => 0,
                                'conversions' => 0,
                                'conversion_value' => 0
                            ),
                            'pricing' => null
                        );
                    }
                    
                    $compiled[ $product_id ]['gmc']['clicks'] += $data['gmc']['clicks'];
                    $compiled[ $product_id ]['gmc']['impressions'] += $data['gmc']['impressions'];
                    $compiled[ $product_id ]['gmc']['conversions'] += $data['gmc']['conversions'];
                    $compiled[ $product_id ]['gmc']['conversion_value'] += $data['gmc']['conversion_value'];
                    
                    // Use latest pricing data
                    if ( isset( $data['pricing'] ) && $data['pricing'] ) {
                        $compiled[ $product_id ]['pricing'] = $data['pricing'];
                    }
                }
            }
            
            $current_date = strtotime( '+1 day', $current_date );
        }
        
        // Fetch archived data for dates older than 30 days
        if ( strtotime( $start ) < strtotime( '-30 days' ) ) {
            $archive = get_option( 'cirrusly_gmc_analytics_archive', array() );
            
            // Calculate week range to filter archived data
            $start_week = date( 'Y-W', strtotime( $start ) );
            $end_week = date( 'Y-W', strtotime( $end ) );
            
            foreach ( $archive as $week_key => $week_data ) {
                // Only include weeks within the requested date range
                if ( $week_key < $start_week || $week_key > $end_week ) {
                    continue;
                }
                
                // Include archived data in compiled results
                foreach ( $week_data as $product_id => $data ) {
                    if ( ! isset( $compiled[ $product_id ] ) ) {
                        $compiled[ $product_id ] = array(
                            'gmc' => array(
                                'clicks' => 0,
                                'impressions' => 0,
                                'conversions' => 0,
                                'conversion_value' => 0
                            ),
                            'pricing' => null
                        );
                    }
                    
                    $compiled[ $product_id ]['gmc']['clicks'] += $data['clicks'];
                    $compiled[ $product_id ]['gmc']['impressions'] += $data['impressions'];
                    $compiled[ $product_id ]['gmc']['conversions'] += $data['conversions'];
                    $compiled[ $product_id ]['gmc']['conversion_value'] += $data['conversion_value'];
                }
            }
        }
        
        // Calculate derived metrics
        foreach ( $compiled as $product_id => &$data ) {
            $data['gmc']['ctr'] = ( $data['gmc']['impressions'] > 0 ) ? 
                          round( ( $data['gmc']['clicks'] / $data['gmc']['impressions'] ) * 100, 2 ) : 0;
            $data['gmc']['conversion_rate'] = ( $data['gmc']['clicks'] > 0 ) ? 
                                       round( ( $data['gmc']['conversions'] / $data['gmc']['clicks'] ) * 100, 2 ) : 0;
        }
        
        return $compiled;
    }
}
