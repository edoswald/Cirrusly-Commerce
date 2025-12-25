<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_Settings_Manager {

    public function __construct() {
        // Register AJAX handlers for data management
        add_action( 'wp_ajax_cirrusly_clear_cache', array( $this, 'handle_clear_cache' ) );
        add_action( 'wp_ajax_cirrusly_reset_gmc_import', array( $this, 'handle_reset_import' ) );
        add_action( 'wp_ajax_cirrusly_export_settings', array( $this, 'handle_export_settings' ) );
        add_action( 'wp_ajax_cirrusly_import_settings', array( $this, 'handle_import_settings' ) );

        // Register AJAX handlers for API key management
        add_action( 'wp_ajax_cirrusly_validate_api_key', array( $this, 'handle_validate_api_key' ) );
        add_action( 'wp_ajax_cirrusly_regenerate_api_key', array( $this, 'handle_regenerate_api_key' ) );
        add_action( 'wp_ajax_cirrusly_link_manual_key', array( $this, 'handle_link_manual_key' ) );
        add_action( 'wp_ajax_cirrusly_generate_api_key', array( $this, 'handle_generate_api_key' ) );
        
        // Email management AJAX handlers
        add_action( 'wp_ajax_cirrusly_send_test_email', array( $this, 'handle_send_test_email' ) );
        add_action( 'wp_ajax_cirrusly_clear_email_log', array( $this, 'handle_clear_email_log' ) );
    }

    /**
     * Register admin menus.
     * Note: References UI classes for callbacks.
     */
    public function register_admin_menus() {
        // Dashboard (Main Menu) - Assumes Dashboard UI class is loaded
        $dash_cb = class_exists( 'Cirrusly_Commerce_Dashboard_UI' ) ? array( 'Cirrusly_Commerce_Dashboard_UI', 'render_main_dashboard' ) : '__return_false';
        
        add_menu_page( 'Cirrusly Commerce', 'Cirrusly Commerce', 'edit_products', 'cirrusly-commerce', $dash_cb, 'dashicons-analytics', 56 );
        add_submenu_page( 'cirrusly-commerce', 'Dashboard', 'Dashboard', 'edit_products', 'cirrusly-commerce', $dash_cb );
        
        // Submenus
        if ( class_exists( 'Cirrusly_Commerce_GMC' ) ) {
            add_submenu_page( 'cirrusly-commerce', 'Compliance Hub', 'Compliance Hub', 'edit_products', 'cirrusly-gmc', array( 'Cirrusly_Commerce_GMC', 'render_page' ) );
        }
        if ( class_exists( 'Cirrusly_Commerce_Audit' ) ) {
            add_submenu_page( 'cirrusly-commerce', 'Financial Audit', 'Financial Audit', 'edit_products', 'cirrusly-audit', array( 'Cirrusly_Commerce_Audit', 'render_page' ) );
        }
        
        if ( class_exists( 'Cirrusly_Commerce_Manual' ) ) {
            add_submenu_page( 'cirrusly-commerce', 'User Manual', 'User Manual', 'edit_products', 'cirrusly-manual', array( 'Cirrusly_Commerce_Manual', 'render_page' ) );
        }

        add_submenu_page( 'cirrusly-commerce', 'Settings', 'Settings', 'manage_options', 'cirrusly-settings', array( $this, 'render_settings_page' ) );
        
    }

    /**
     * Register settings and sanitization callbacks.
     */
    public function register_settings() {
        // Group: General
        register_setting( 'cirrusly_general_group', 'cirrusly_scan_config', array( 'sanitize_callback' => array( $this, 'handle_scan_schedule' ) ) );
        register_setting( 'cirrusly_general_group', 'cirrusly_msrp_config', array( 'sanitize_callback' => array( $this, 'sanitize_options_array' ) ) );
        register_setting( 'cirrusly_general_group', 'cirrusly_google_reviews_config', array( 'sanitize_callback' => array( $this, 'sanitize_options_array' ) ) );
        register_setting( 'cirrusly_general_group', 'cirrusly_countdown_rules', array( 'sanitize_callback' => array( $this, 'sanitize_countdown_rules' ) ) );

        // Group: Profit Engine
        register_setting( 'cirrusly_shipping_group', 'cirrusly_shipping_config', array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) ) );

        // Group: Badges
        register_setting( 'cirrusly_badge_group', 'cirrusly_badge_config', array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) ) );

        // Group: GMC Product Mapping (Pro)
        register_setting( 'cirrusly_gmc_mapping_group', 'cirrusly_gmc_product_mapping', array( 'sanitize_callback' => array( $this, 'sanitize_gmc_mappings' ) ) );
        register_setting( 'cirrusly_gmc_mapping_group', 'cirrusly_gmc_mapping_config', array( 'sanitize_callback' => array( $this, 'sanitize_options_array' ) ) );
        
        // Group: Analytics (Pro Plus)
        register_setting( 'cirrusly_analytics_group', 'cirrusly_analytics_preferences', array( 'sanitize_callback' => array( $this, 'sanitize_options_array' ) ) );
        
        // Group: Email Settings
        register_setting( 'cirrusly_email_group', 'cirrusly_email_settings', array( 'sanitize_callback' => array( $this, 'sanitize_email_settings' ) ) );
    }

    /**
     * Sanitization Helpers
     */
    public function sanitize_options_array( $input ) {
        $clean = array();
        if ( is_array( $input ) ) {
            foreach( $input as $key => $val ) {
                $clean[ sanitize_text_field( $key ) ] = sanitize_text_field( $val );
            }
        }
        return $clean;
    }

    public function sanitize_countdown_rules( $input ) {
        $clean_rules = array();
        if ( ! is_array( $input ) ) return array();

        foreach( $input as $rule ) {
            if ( ! is_array( $rule ) ) continue;
            $clean_rule = array();
            $clean_rule['taxonomy'] = isset( $rule['taxonomy'] ) ? sanitize_key( $rule['taxonomy'] ) : '';
            $clean_rule['term']     = isset( $rule['term'] ) ? sanitize_text_field( $rule['term'] ) : '';
            $clean_rule['end']      = isset( $rule['end'] ) ? sanitize_text_field( $rule['end'] ) : '';
            $clean_rule['label']    = isset( $rule['label'] ) ? sanitize_text_field( $rule['label'] ) : '';
            
            $align = isset( $rule['align'] ) ? sanitize_key( $rule['align'] ) : 'left';
            $clean_rule['align'] = in_array( $align, array('left', 'right', 'center'), true ) ? $align : 'left';

            if ( ! empty( $clean_rule['taxonomy'] ) && ! empty( $clean_rule['term'] ) && ! empty( $clean_rule['end'] ) ) {

                $clean_rules[] = $clean_rule;
            }
        }
        return $clean_rules;
    }

    public function sanitize_email_settings( $input ) {
        $clean = array();
        if ( ! is_array( $input ) ) {
            return $clean;
        }

        // Email types that need recipients
        $email_types = array(
            'bug_report',
            'weekly_profit',
            'gmc_import',
            'webhook_failures',
            'gmc_disapproval',
            'unmapped_products',
            'weekly_analytics',
        );

        foreach ( $email_types as $type ) {
            // Sanitize recipient
            if ( isset( $input[ $type . '_recipient' ] ) ) {
                $email = sanitize_email( $input[ $type . '_recipient' ] );
                if ( is_email( $email ) ) {
                    $clean[ $type . '_recipient' ] = $email;
                }
            }

            // Sanitize enabled checkbox
            $clean[ $type . '_enabled' ] = isset( $input[ $type . '_enabled' ] ) ? 1 : 0;
        }

        return $clean;
    }

    /**
     * Process scan scheduling settings and handle an uploaded service-account file when provided.
     *
     * Schedules or clears the 'cirrusly_gmc_daily_scan' cron based on the `enable_daily_scan` flag,
     * delegates service-account file processing to the Pro handler when a file is uploaded and Pro is active,
     * and records a settings error if upload is attempted without Pro. Returns the sanitized settings array.
     *
     * @param array $input Associative settings array (e.g., ['enable_daily_scan' => 'yes', ...]). May be modified if a Pro upload handler processes a service-account file.
     * @return array The sanitized settings array suitable for storage.
     */
    public function handle_scan_schedule( $input ) {
        // Retrieve the current configuration to preserve existing data.
        $existing_config = get_option( 'cirrusly_scan_config', array() );

        // MIGRATION: Consolidate merchant ID from old locations into global field
        // Only run migration if global merchant_id is empty
        if ( empty( $existing_config['merchant_id'] ) ) {
            // Check old locations for merchant ID values
            $gcr_config = get_option( 'cirrusly_google_reviews_config', array() );
            $old_merchant_id_gcr = isset( $gcr_config['merchant_id'] ) ? $gcr_config['merchant_id'] : '';
            $old_merchant_id_pro = isset( $existing_config['merchant_id_pro'] ) ? $existing_config['merchant_id_pro'] : '';

            // Use the first non-empty value found
            if ( ! empty( $old_merchant_id_gcr ) ) {
                $existing_config['merchant_id'] = $old_merchant_id_gcr;
                error_log( 'Cirrusly Commerce: Migrated Merchant ID from Google Customer Reviews config' );
            } elseif ( ! empty( $old_merchant_id_pro ) ) {
                $existing_config['merchant_id'] = $old_merchant_id_pro;
                error_log( 'Cirrusly Commerce: Migrated Merchant ID from merchant_id_pro field' );
            }

            // If we migrated a value, save it immediately
            if ( ! empty( $existing_config['merchant_id'] ) ) {
                update_option( 'cirrusly_scan_config', $existing_config, false );
            }
        }

        // CRITICAL: Start with existing config and merge in new values
        // This ensures that fields missing from $input (disabled fields, hidden fields, etc.)
        // are preserved instead of being wiped out.
        // We merge $input into $existing_config, with $input values taking precedence.
        if ( ! is_array( $existing_config ) ) {
            $existing_config = array();
        }
        if ( ! is_array( $input ) ) {
            $input = array();
        }

        // Save the original submitted input before merging (to detect unchecked checkboxes)
        $submitted_input = $input;

        // Merge: existing values are preserved unless explicitly overridden by $input
        $input = array_merge( $existing_config, $submitted_input );

        // Note: Unchecked checkboxes will be missing from $submitted_input entirely (normal HTML behavior).
        // To explicitly clear an unchecked checkbox, we need to detect which checkboxes exist
        // in the form and clear them if they're not in the submitted $submitted_input.
        // The checkbox fields in the form are:
        $checkbox_fields = array(
            'enable_daily_scan',
            'enable_email_report',
            'alert_weekly_report',
            'alert_gmc_disapproval',
            'enable_automated_discounts',
            'enable_dev_mode',
            'delete_on_uninstall',
        );

        // For each checkbox field, if it's not in the submitted data, it was unchecked.
        // We need to explicitly clear it from the merged config.
        // However, we need to preserve Pro/Pro Plus fields that are disabled (not submitted because disabled).
        $is_pro = class_exists( 'Cirrusly_Commerce_Core' ) && Cirrusly_Commerce_Core::cirrusly_is_pro();
        $is_pro_plus = class_exists( 'Cirrusly_Commerce_Core' ) && Cirrusly_Commerce_Core::cirrusly_is_pro_plus();

        foreach ( $checkbox_fields as $field ) {
            // If field wasn't submitted, check if it should be cleared or preserved
            if ( ! isset( $submitted_input[ $field ] ) ) {
                // Pro/Pro Plus fields should be preserved if user doesn't have required license
                $pro_fields = array( 'alert_weekly_report', 'alert_gmc_disapproval' );
                $pro_plus_fields = array( 'enable_automated_discounts' );

                if ( in_array( $field, $pro_fields, true ) && ! $is_pro ) {
                    // Preserve Pro field for non-Pro users (field was disabled)
                    continue;
                } elseif ( in_array( $field, $pro_plus_fields, true ) && ! $is_pro_plus ) {
                    // Preserve Pro Plus field for non-Pro Plus users (field was disabled)
                    continue;
                } else {
                    // Field was unchecked by user - explicitly remove it
                    unset( $input[ $field ] );
                }
            }
        }

        // Additional Pro/Pro Plus text fields that should be preserved when disabled
        $pro_text_fields = array( 'api_key' );
        // Note: merchant_id is now a global field accessible to all users, so it's not license-gated
        $pro_plus_text_fields = array( 'google_public_key' );

        foreach ( $pro_text_fields as $field ) {
            if ( ! $is_pro && ! isset( $submitted_input[ $field ] ) && isset( $existing_config[ $field ] ) ) {
                // Preserve Pro field when user doesn't have Pro (field was disabled)
                $input[ $field ] = $existing_config[ $field ];
            }
        }

        foreach ( $pro_plus_text_fields as $field ) {
            if ( ! $is_pro_plus && ! isset( $submitted_input[ $field ] ) && isset( $existing_config[ $field ] ) ) {
                // Preserve Pro Plus field when user doesn't have Pro Plus (field was disabled)
                $input[ $field ] = $existing_config[ $field ];
            }
        }

        // Always preserve service account metadata (not present in form fields)
        if ( isset( $existing_config['service_account_uploaded'] ) ) {
            $input['service_account_uploaded'] = $existing_config['service_account_uploaded'];
        }
        if ( isset( $existing_config['service_account_name'] ) ) {
            $input['service_account_name'] = $existing_config['service_account_name'];
        }
        // ---------------------------------------------

        // 1. Schedule Logic (Core Feature)
        wp_clear_scheduled_hook( 'cirrusly_gmc_daily_scan' );
        delete_option( 'cirrusly_gmc_daily_scan_scheduled' ); // Reset flag when user changes settings

        if ( isset($input['enable_daily_scan']) && $input['enable_daily_scan'] === 'yes' ) {
            if ( ! wp_next_scheduled( 'cirrusly_gmc_daily_scan' ) ) {
                $scheduled = wp_schedule_event( time(), 'daily', 'cirrusly_gmc_daily_scan' );

                if ( false !== $scheduled ) {
                    // Set flag on successful scheduling
                    update_option( 'cirrusly_gmc_daily_scan_scheduled', true, false );
                } else {
                    error_log( 'Cirrusly Commerce: Failed to schedule daily GMC scan cron event' );
                }
            } else {
                // Cron is already scheduled - sync the flag state
                update_option( 'cirrusly_gmc_daily_scan_scheduled', true, false );
            }
        }
        
        // 2. File Upload Logic (Pro Feature)
        if ( isset( $_FILES['cirrusly_service_account'] ) && ! empty( $_FILES['cirrusly_service_account']['tmp_name'] ) ) {
            if ( ! isset( $_FILES['cirrusly_service_account']['error'] ) || $_FILES['cirrusly_service_account']['error'] !== UPLOAD_ERR_OK ) {
                add_settings_error( 'cirrusly_scan_config', 'upload_failed', __( 'Upload failed. Please try again.', 'cirrusly-commerce' ) );
                return $this->sanitize_options_array( $input );
            }

            // Use original tmp_name for security check (Not sanitized as it is a system path)
            $original_tmp_name = $_FILES['cirrusly_service_account']['tmp_name'];

            if ( is_uploaded_file( $original_tmp_name ) ) {
                // OLD CODE (Likely failing):
                /*
                $ft = wp_check_filetype_and_ext( $original_tmp_name, $_FILES['cirrusly_service_account']['name'] );
                if ( empty( $ft['ext'] ) || $ft['ext'] !== 'json' ) {
                    add_settings_error( 'cirrusly_scan_config', 'invalid_type', __( 'Please upload a valid .json file.', 'cirrusly-commerce' ) );
                    return $this->sanitize_options_array( $input );
                }
                */
            // NEW CODE (Correct):
            // Rely on Pro class validation which correctly handles JSON types
            if ( class_exists( 'Cirrusly_Commerce_Core' ) && Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
                    
                    // --- FIX 2: Correct Path Resolution ---
                    // __DIR__ refers to 'includes/admin'. dirname(__DIR__) refers to 'includes'.
                    $pro_class = dirname( __DIR__ ) . '/pro/class-settings-pro.php';
                    
                    if ( file_exists( $pro_class ) ) {
                        require_once $pro_class;
                        
                        // Construct a sanitized array of file data to pass to the handler
                        // Directly access and sanitize fields to avoid assigning raw $_FILES to a variable
                        $safe_file = array(
                            'name'     => sanitize_file_name( $_FILES['cirrusly_service_account']['name'] ),
                            'type'     => sanitize_mime_type( $_FILES['cirrusly_service_account']['type'] ),
                            'tmp_name' => $original_tmp_name, // System path validated by is_uploaded_file
                            'error'    => intval( $_FILES['cirrusly_service_account']['error'] ),
                            'size'     => intval( $_FILES['cirrusly_service_account']['size'] ),
                        );

                        // The Pro method returns the modified $input array
                        $input = Cirrusly_Commerce_Settings_Pro::cirrusly_process_service_account_upload( $input, $safe_file );
                    } else {
                        // Debug helper: This error will appear if the path is still wrong
                        add_settings_error( 'cirrusly_scan_config', 'missing_file', 'Error: Pro settings file not found at ' . $pro_class );
                    }
                } else {
                     add_settings_error( 'cirrusly_scan_config', 'pro_required', 'Using this feature requires Pro or higher. Upgrade today.' );
                }
            }
        }

        return $this->sanitize_options_array( $input );
    }

    public function sanitize_settings( $input ) {
        if ( isset( $input['revenue_tiers'] ) && is_array( $input['revenue_tiers'] ) ) {

            $clean_tiers = array();
            foreach ( $input['revenue_tiers'] as $tier ) {
                if ( isset($tier['min']) && is_numeric($tier['min']) ) {
                    $clean_tiers[] = array( 
                        'min' => floatval( $tier['min'] ), 
                        'max' => floatval( isset($tier['max']) ? $tier['max'] : 99999 ), 
                        'charge' => floatval( isset($tier['charge']) ? $tier['charge'] : 0 ),
                    );
                }
            }
            $input['revenue_tiers_json'] = json_encode( $clean_tiers );
            unset( $input['revenue_tiers'] );
        }
        if ( isset( $input['matrix_rules'] ) && is_array( $input['matrix_rules'] ) ) {
            $clean_matrix = array();
            foreach ( $input['matrix_rules'] as $idx => $rule ) {
                $key = isset($rule['key']) ? sanitize_title($rule['key']) : 'rule_'.$idx;
                if ( ! empty( $key ) && isset( $rule['label'] ) ) {
                    $clean_matrix[ $key ] = array( 'key' => $key, 'label' => sanitize_text_field( $rule['label'] ), 'cost_mult' => isset( $rule['cost_mult'] ) ? floatval( $rule['cost_mult'] ) : 1.0 );
                }
            }
            $input['matrix_rules_json'] = json_encode( $clean_matrix );
            unset( $input['matrix_rules'] );
        }
        if ( isset( $input['class_costs'] ) && is_array( $input['class_costs'] ) ) {
            $clean_costs = array();
            foreach ( $input['class_costs'] as $slug => $cost ) {
                if ( ! empty( $slug ) ) $clean_costs[ sanitize_text_field( $slug ) ] = floatval( $cost );
            }
            $input['class_costs_json'] = json_encode( $clean_costs );
            unset( $input['class_costs'] );
        }
        if ( isset( $input['custom_badges'] ) && is_array( $input['custom_badges'] ) ) {
            $clean_badges = array();
            foreach ( $input['custom_badges'] as $badge ) {
                if ( ! empty($badge['tag']) && ! empty($badge['url']) ) {
                    $clean_badges[] = array(
                        'tag' => sanitize_title( $badge['tag'] ),
                        'url' => esc_url_raw( $badge['url'] ),
                        'tooltip' => isset( $badge['tooltip'] ) ? sanitize_text_field( $badge['tooltip'] ) : '',
                        'width' => isset( $badge['width'] ) && intval( $badge['width'] ) > 0 ? intval( $badge['width'] ) : 60
                    );
                }
            }
            $input['custom_badges_json'] = json_encode( $clean_badges );
            unset( $input['custom_badges'] );
        }
       if ( isset( $input['scheduler_start'] ) ) {
            $input['scheduler_start'] = sanitize_text_field( $input['scheduler_start'] );
        }
        if ( isset( $input['scheduler_end'] ) ) {
            $input['scheduler_end'] = sanitize_text_field( $input['scheduler_end'] );
        }
        
        $fields = ['payment_pct', 'payment_flat', 'payment_pct_2', 'payment_flat_2', 'profile_split'];
        foreach($fields as $f) { if(isset($input[$f])) $input[$f] = floatval($input[$f]); }
        if(isset($input['profile_mode'])) $input['profile_mode'] = sanitize_text_field($input['profile_mode']);

        if ( isset( $input['smart_inventory'] ) ) $input['smart_inventory'] = 'yes';
        if ( isset( $input['smart_performance'] ) ) $input['smart_performance'] = 'yes';
        if ( isset( $input['smart_scheduler'] ) ) $input['smart_scheduler'] = 'yes';

        return $input;
    }

    /**
     * Get global shipping config (Used by Pricing/Audit logic)
     */
    public static function get_global_config() {
        // Delegate to Core class to avoid duplication
        if ( class_exists( 'Cirrusly_Commerce_Core' ) ) {
            $core = new Cirrusly_Commerce_Core();
            return $core->get_global_config();
        }
        // Fallback if Core not loaded (shouldn't happen in admin)
        return get_option( 'cirrusly_shipping_config', array() );
    }

    /**
     * Render the plugin Settings admin page with tabbed sections and the setup wizard link.
     *
     * Determines the active tab from the sanitized `$_GET['tab']`, displays the global header
     * (delegating to Cirrusly_Commerce_Core when available), shows a Setup Wizard button,
     * renders navigation tabs, opens a multipart settings form, and outputs the appropriate
     * settings fields and section UI for the selected tab (General, Profit Intelligence, or Badge Manager).
     */
    public function render_settings_page() {
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'general';
        echo '<div class="wrap">';

        settings_errors();

        if ( class_exists( 'Cirrusly_Commerce_Core' ) ) {
            Cirrusly_Commerce_Core::render_global_header( 'Settings' );
        } else {
            echo '<h1>Settings</h1>';
        }

    // --- NEW: Rerun Wizard Button Here ---
            echo '<div class="cirrusly-settings-header-actions">
                    <a href="' . esc_url( admin_url( 'admin.php?page=cirrusly-setup' ) ) . '" class="cirrusly-btn-secondary">
                    <span class="dashicons dashicons-admin-tools"></span> ' . esc_html__( 'Setup Wizard', 'cirrusly-commerce' ) . '
                    </a>
                </div>';
        
        echo '<nav class="nav-tab-wrapper">
                <a href="?page=cirrusly-settings&tab=general" class="nav-tab '.($tab=='general'?'nav-tab-active':'').'">General Settings</a>
                <a href="?page=cirrusly-settings&tab=shipping" class="nav-tab '.($tab=='shipping'?'nav-tab-active':'').'">Profit Intelligence</a>
                <a href="?page=cirrusly-settings&tab=badges" class="nav-tab '.($tab=='badges'?'nav-tab-active':'').'">Badge Manager</a>
                <a href="?page=cirrusly-settings&tab=emails" class="nav-tab '.($tab=='emails'?'nav-tab-active':'').'">Email Settings</a>';
        
        // Analytics tab (Pro Plus only)
        if ( class_exists( 'Cirrusly_Commerce_Core' ) && Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            echo '<a href="?page=cirrusly-settings&tab=analytics" class="nav-tab '.($tab=='analytics'?'nav-tab-active':'').'">Analytics <span class="cirrusly-pro-badge" style="margin-left:5px;">PRO+</span></a>';
        }
        
        // GMC Mapping tab (Pro only)
        if ( class_exists( 'Cirrusly_Commerce_Core' ) && Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            echo '<a href="?page=cirrusly-settings&tab=gmc_mapping" class="nav-tab '.($tab=='gmc_mapping'?'nav-tab-active':'').'">GMC Product Mapping</a>';
        }
        
        echo '</nav>';
        
        echo '<br><form method="post" action="options.php" enctype="multipart/form-data">';
        
        if($tab==='emails'){ 
            settings_fields('cirrusly_email_group'); 
            $this->render_email_settings(); 
        } elseif($tab==='analytics'){ 
            settings_fields('cirrusly_analytics_group'); 
            $this->render_analytics_settings(); 
        } elseif($tab==='gmc_mapping'){ 
            settings_fields('cirrusly_gmc_mapping_group'); 
            $this->render_gmc_mapping_settings(); 
        } elseif($tab==='badges'){ 
            settings_fields('cirrusly_badge_group'); 
            $this->render_badges_settings(); 
        } elseif($tab==='shipping') { 
            settings_fields('cirrusly_shipping_group'); 
            $this->render_profit_engine_settings(); 
        } else { 
            settings_fields('cirrusly_general_group'); 
            $this->render_general_settings(); 
        }
        
        // Modern gradient submit button
        echo '<div style="margin-top: 20px; padding: 15px 0; border-top: 1px solid #ddd;">';
        echo '<button type="submit" class="cirrusly-btn-success" style="padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-saved" style="line-height:inherit;"></span> Save Changes</button>';
        echo '</div>';
        echo '</form></div>';
    }

    private function render_general_settings() {
        $msrp = get_option( 'cirrusly_msrp_config', array() );
        $msrp_enable = isset($msrp['enable_display']) ? $msrp['enable_display'] : '';
        $pos_prod = isset($msrp['position_product']) ? $msrp['position_product'] : 'before_price';
        $pos_loop = isset($msrp['position_loop']) ? $msrp['position_loop'] : 'before_price';

        $gcr = get_option( 'cirrusly_google_reviews_config', array() );
        $gcr_enable = isset($gcr['enable_reviews']) ? $gcr['enable_reviews'] : '';

        $scan = get_option( 'cirrusly_scan_config', array() );
        $daily = isset($scan['enable_daily_scan']) ? $scan['enable_daily_scan'] : '';
        $api_key = isset($scan['api_key']) ? $scan['api_key'] : '';

        $alert_reports = isset($scan['alert_weekly_report']) ? $scan['alert_weekly_report'] : '';
        $alert_disapproval = isset($scan['alert_gmc_disapproval']) ? $scan['alert_gmc_disapproval'] : '';
        $uploaded_file = isset($scan['service_account_name']) ? $scan['service_account_name'] : '';

        $is_pro = class_exists( 'Cirrusly_Commerce_Core' ) && method_exists( 'Cirrusly_Commerce_Core', 'cirrusly_is_pro' ) && Cirrusly_Commerce_Core::cirrusly_is_pro();
        $pro_class = $is_pro ? '' : 'cirrusly-pro-feature';
        $disabled_attr = $is_pro ? '' : 'disabled';
        
        $countdown_rules = get_option( 'cirrusly_countdown_rules', array() );
        if ( ! is_array( $countdown_rules ) ) $countdown_rules = array();

        echo '<div class="cirrusly-settings-grid">';

        // Google Account (Global Merchant ID)
        $global_merchant_id = isset($scan['merchant_id']) ? $scan['merchant_id'] : '';
        echo '<div class="cirrusly-settings-card" style="background: linear-gradient(135deg, #f0f9ff 0%, #ffffff 100%); border-left: 4px solid #2271b1;">
            <div class="cirrusly-card-header" style="background: linear-gradient(135deg, #2271b1, #135e96);"><h3 style="color: white;">Google Merchant Center</h3><span class="dashicons dashicons-google" style="color: white;"></span></div>
            <div class="cirrusly-card-body">
                <p class="description" style="margin-bottom: 15px;"><strong>Your Merchant ID is used across all Google integrations.</strong> You only need to enter it once here and it will automatically populate all features that need it.</p>
                <table class="form-table cirrusly-settings-table">
                    <tr>
                        <th scope="row" style="width: 200px;"><label for="global_merchant_id">Merchant Center ID</label></th>
                        <td>
                            <input type="text" id="global_merchant_id" name="cirrusly_scan_config[merchant_id]" value="'.esc_attr($global_merchant_id).'" class="regular-text" placeholder="123456789" style="font-size: 15px; padding: 8px;">
                            <p class="description">Find this in your <a href="https://merchants.google.com/" target="_blank">Google Merchant Center</a> account settings</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>';

        // API Key Management (Pro Only)
        if ( $is_pro ) {
            $has_api_key = ! empty( $scan['api_key'] );
            $is_auto_generated = isset( $scan['auto_generated'] ) && $scan['auto_generated'];
            $api_key_generated = isset( $scan['api_key_generated'] ) ? $scan['api_key_generated'] : '';
            $api_key_plan = isset( $scan['api_key_plan'] ) ? $scan['api_key_plan'] : '';

            echo '<div class="cirrusly-settings-card" style="background: linear-gradient(135deg, #f0fff0 0%, #ffffff 100%); border-left: 4px solid #00a32a;">
                <div class="cirrusly-card-header" style="background: linear-gradient(135deg, #00a32a, #008a20);"><h3 style="color: white;">API Key Management <span class="cirrusly-pro-badge" style="background: white; color: #00a32a; margin-left: 8px;">PRO</span></h3><span class="dashicons dashicons-admin-network" style="color: white;"></span></div>
                <div class="cirrusly-card-body">';

            if ( $has_api_key ) {
                // Determine status badge
                $badge_color = $is_auto_generated ? '#00a32a' : '#2271b1';
                $badge_text = $is_auto_generated ? 'Active (Auto-Generated)' : 'Active (Manual)';

                echo '<div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 4px;">
                    <span style="display: inline-block; padding: 8px 16px; background: linear-gradient(135deg, '.esc_attr($badge_color).', '.esc_attr($badge_color).'dd); color: white; border-radius: 20px; font-size: 13px; font-weight: 600; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        ✓ '.esc_html($badge_text).'
                    </span>';

                if ( $api_key_plan ) {
                    echo '<span style="font-size: 13px; color: #666;">
                        <strong>Plan:</strong> '.esc_html(ucfirst($api_key_plan)).'
                    </span>';
                }

                if ( $api_key_generated ) {
                    echo '<span style="font-size: 13px; color: #666;">
                        <strong>Generated:</strong> '.esc_html(date_i18n('M j, Y g:i A', strtotime($api_key_generated))).'
                    </span>';
                }

                echo '</div>';

                // Validation Section
                echo '<div style="margin-bottom: 20px;">
                    <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600;">Connection Status</h4>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" class="button button-secondary cirrusly-validate-api-key-btn">
                            <span class="dashicons dashicons-yes-alt" style="margin-top: 3px;"></span>
                            Validate Connection
                        </button>
                        <span class="cirrusly-api-validation-status"></span>
                    </div>
                    <div id="cirrusly-api-validation-details" style="margin-top: 10px; display: none; padding: 12px; background: #f0f6fc; border-left: 3px solid #2271b1; border-radius: 3px;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                            <div>
                                <strong style="display: block; font-size: 11px; text-transform: uppercase; color: #666; margin-bottom: 4px;">Plan Tier</strong>
                                <span id="cirrusly-api-plan" style="font-size: 14px; font-weight: 600;">—</span>
                            </div>
                            <div>
                                <strong style="display: block; font-size: 11px; text-transform: uppercase; color: #666; margin-bottom: 4px;">Quota Usage</strong>
                                <span id="cirrusly-api-quota" style="font-size: 14px; font-weight: 600;">—</span>
                            </div>
                            <div>
                                <strong style="display: block; font-size: 11px; text-transform: uppercase; color: #666; margin-bottom: 4px;">Last Used</strong>
                                <span id="cirrusly-api-last-used" style="font-size: 14px; font-weight: 600;">—</span>
                            </div>
                            <div>
                                <strong style="display: block; font-size: 11px; text-transform: uppercase; color: #666; margin-bottom: 4px;">Status</strong>
                                <span id="cirrusly-api-status" style="font-size: 14px; font-weight: 600;">—</span>
                            </div>
                        </div>
                    </div>
                </div>';

                // Key Management Actions
                if ( $is_auto_generated ) {
                    echo '<div style="padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">
                        <h4 style="margin: 0 0 10px 0; font-size: 14px; font-weight: 600;">Key Management</h4>
                        <p class="description" style="margin-bottom: 15px;">Regenerate your API key if it has been compromised or for security testing.</p>
                        <button type="button" class="button cirrusly-regenerate-api-key-btn">
                            <span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
                            Regenerate Key
                        </button>';

                    if ( class_exists( 'Cirrusly_Commerce_API_Key_Manager' ) ) {
                        $cooldown_days = Cirrusly_Commerce_API_Key_Manager::get_regeneration_cooldown_days();
                        if ( $cooldown_days > 0 ) {
                            echo '<p class="description" style="margin-top: 10px; color: #d63638;"><span class="dashicons dashicons-warning" style="color: #d63638;"></span> <strong>Regeneration available in '.absint($cooldown_days).' days</strong> (7-day cooldown active)</p>';
                        } else {
                            echo '<p class="description" style="margin-top: 10px;">7-day cooldown applies after regeneration.</p>';
                        }
                    }

                    echo '</div>';
                } else {
                    // Manual key - show link option
                    echo '<div style="padding: 15px; background: #f0f6fc; border: 1px solid #2271b1; border-left: 4px solid #2271b1; border-radius: 4px;">
                        <h4 style="margin: 0 0 8px 0; font-size: 14px; font-weight: 600; color: #135e96;">Manual API Key Detected</h4>
                        <p style="margin: 0 0 12px 0; font-size: 13px; color: #555;">Your manual key will continue to work. Link it to your Freemius account to enable automatic regeneration and advanced management features.</p>
                        <button type="button" class="button button-primary cirrusly-link-manual-key-btn">
                            <span class="dashicons dashicons-admin-links" style="margin-top: 3px;"></span>
                            Link to Freemius Account
                        </button>
                    </div>';
                }

            } else {
                // No API key found
                echo '<div style="padding: 20px; background: #fff3cd; border-left: 4px solid #f0ad4e; margin-bottom: 15px;">
                    <h4 style="margin: 0 0 8px 0; color: #856404;"><span class="dashicons dashicons-warning" style="color: #f0ad4e;"></span> No API Key Found</h4>
                    <p style="margin: 0 0 12px 0; color: #555;">Your API key should have been automatically generated when you activated your Pro license.</p>
                </div>
                <button type="button" class="button button-primary cirrusly-generate-api-key-btn">
                    <span class="dashicons dashicons-admin-network" style="margin-top: 3px;"></span>
                    Generate API Key Now
                </button>
                <span class="cirrusly-api-generation-status" style="margin-left: 10px;"></span>';
            }

            echo '    </div>
            </div>';
        }

        // Integrations
        echo '<div class="cirrusly-settings-card">
             <div class="cirrusly-card-header"><h3>Integrations</h3><span class="dashicons dashicons-google"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Connect your store to Google Customer Reviews to gather post-purchase feedback and display trust badges on your site.</p>
                <table class="form-table cirrusly-settings-table">
                    <tr><th scope="row">Google Customer Reviews</th><td><label><input type="checkbox" name="cirrusly_google_reviews_config[enable_reviews]" value="yes" '.checked('yes', $gcr_enable, false).'> Enable</label></td></tr>
                </table>
                <p class="description" style="margin-top: 10px; padding: 10px; background: #f0f9ff; border-left: 3px solid #2271b1;"><span class="dashicons dashicons-info" style="color: #2271b1;"></span> <strong>Merchant ID:</strong> Using global Merchant Center ID configured above</p>
            </div>
        </div>';

        // MSRP
        echo '<div class="cirrusly-settings-card">
            <div class="cirrusly-card-header"><h3>Frontend Display</h3><span class="dashicons dashicons-store"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Display manufacturer suggested retail prices to highlight savings. Choose where the MSRP and strikethrough price appear on product pages and catalog loops.</p>
                <table class="form-table cirrusly-settings-table">
                    <tr><th scope="row">MSRP Price</th><td><label><input type="checkbox" name="cirrusly_msrp_config[enable_display]" value="yes" '.checked('yes', $msrp_enable, false).'> Show Strikethrough</label></td></tr>
                    <tr><th scope="row">Product Page</th><td><select name="cirrusly_msrp_config[position_product]">
                        <option value="before_title" '.selected('before_title', $pos_prod, false).'>Before Title</option>
                        <option value="before_price" '.selected('before_price', $pos_prod, false).'>Before Price</option>
                        <option value="inline" '.selected('inline', $pos_prod, false).'>Inline</option>
                        <option value="after_price" '.selected('after_price', $pos_prod, false).'>After Price</option>
                        <option value="after_excerpt" '.selected('after_excerpt', $pos_prod, false).'>After Excerpt</option>
                        <option value="before_add_to_cart" '.selected('before_add_to_cart', $pos_prod, false).'>Before Add to Cart</option>
                        <option value="after_add_to_cart" '.selected('after_add_to_cart', $pos_prod, false).'>After Add to Cart</option>
                        <option value="after_meta" '.selected('after_meta', $pos_prod, false).'>After Meta</option>
                    </select></td></tr>
                    <tr><th scope="row">Catalog Loop</th><td><select name="cirrusly_msrp_config[position_loop]">
                        <option value="before_price" '.selected('before_price', $pos_loop, false).'>Before Price</option>
                        <option value="inline" '.selected('inline', $pos_loop, false).'>Inline</option>
                        <option value="after_price" '.selected('after_price', $pos_loop, false).'>After Price</option>
                    </select></td></tr>
                </table>
            </div>
        </div>';

        // Automation
        echo '<div class="cirrusly-settings-card">
            <div class="cirrusly-card-header"><h3>Automation</h3><span class="dashicons dashicons-update"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Schedule automated daily health scans to detect compliance issues. Enable email reporting to receive summaries of any flagged products.</p>
                <label><input type="checkbox" name="cirrusly_scan_config[enable_daily_scan]" value="yes" '.checked('yes', $daily, false).'> <strong>Daily Health Scan</strong></label>
                <p class="description" style="margin-top:5px;">Checks for missing GTINs and prohibited terms.</p>
                <br><label><input type="checkbox" name="cirrusly_scan_config[enable_email_report]" value="yes" '.checked('yes', isset($scan['enable_email_report']) ? $scan['enable_email_report'] : '', false).'> <strong>Email Reports</strong></label>
            </div>
        </div>';

        // Countdown (Pro)
        echo '<div class="cirrusly-settings-card '.esc_attr($pro_class).'">';
            if(!$is_pro) echo '<div class="cirrusly-pro-overlay"><a href="'.esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ).'" class="cirrusly-upgrade-btn"><span class="dashicons dashicons-lock cirrusly-lock-icon"></span> Unlock Smart Rules</a></div>';
        echo '<div class="cirrusly-card-header"><h3>Smart Countdown <span class="cirrusly-pro-badge">PRO</span></h3><span class="dashicons dashicons-clock"></span></div>
            <div class="cirrusly-card-body">
            <p class="description">Create urgency by displaying countdown timers based on specific categories or tags. Define the taxonomy term and the expiration date to automatically show the timer.</p>
            <table class="widefat striped cirrusly-settings-table"><thead><tr><th>Taxonomy</th><th>Term</th><th>End Date</th><th>Label</th><th>Align</th><th></th></tr></thead><tbody id="cirrusly-countdown-rows">';
        if ( ! empty( $countdown_rules ) ) {
            foreach ( $countdown_rules as $idx => $rule ) {
                $align = isset($rule['align']) ? $rule['align'] : 'left';
                echo '<tr>
                    <td><input type="text" name="cirrusly_countdown_rules['.esc_attr($idx).'][taxonomy]" value="'.esc_attr($rule['taxonomy']).'" '.esc_attr($disabled_attr).'></td>
                    <td><input type="text" name="cirrusly_countdown_rules['.esc_attr($idx).'][term]" value="'.esc_attr($rule['term']).'" '.esc_attr($disabled_attr).'></td>
                    <td><input type="text" name="cirrusly_countdown_rules['.esc_attr($idx).'][end]" value="'.esc_attr($rule['end']).'" '.esc_attr($disabled_attr).'></td>
                    <td><input type="text" name="cirrusly_countdown_rules['.esc_attr($idx).'][label]" value="'.esc_attr($rule['label']).'" '.esc_attr($disabled_attr).'></td>
                    <td><select name="cirrusly_countdown_rules['.esc_attr($idx).'][align]" '.esc_attr($disabled_attr).'>
                        <option value="left" '.selected('left', $align, false).'>Left</option>
                        <option value="right" '.selected('right', $align, false).'>Right</option>
                        <option value="center" '.selected('center', $align, false).'>Center</option>
                    </select></td>
                    <td><button type="button" class="cirrusly-btn-secondary cirrusly-remove-row" style="padding: 6px 10px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 4px;" '.esc_attr($disabled_attr).'><span class="dashicons dashicons-trash"></span></button></td>
                </tr>';
            }
        }
        echo '</tbody></table><button type="button" class="cirrusly-btn-success" id="cirrusly-add-countdown-row" style="margin-top:10px; padding: 8px 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 5px;" '.esc_attr($disabled_attr).'><span class="dashicons dashicons-plus-alt" style="line-height:inherit;"></span> Add Rule</button></div></div>';

        // API Connection (Pro)
        echo '<div class="cirrusly-settings-card '.esc_attr($pro_class).'">';
        if(!$is_pro) echo '<div class="cirrusly-pro-overlay"><a href="'.esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ).'" class="cirrusly-upgrade-btn"><span class="dashicons dashicons-lock cirrusly-lock-icon"></span> Upgrade</a></div>';
        echo '<div class="cirrusly-card-header"><h3>Content API <span class="cirrusly-pro-badge">PRO</span></h3><span class="dashicons dashicons-cloud"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Upload your Google Service Account JSON to enable real-time API scanning. This allows the plugin to fetch live disapproval statuses directly from Google Merchant Center.</p>
                <table class="form-table cirrusly-settings-table">
                <tr><th>Service Account JSON</th><td><input type="file" name="cirrusly_service_account" accept=".json" '.esc_attr($disabled_attr).'>'.($uploaded_file ? '<br><small>Uploaded: '.esc_html($uploaded_file).'</small>' : '').'</td></tr>

                <tr><th>API License Key</th><td><input type="text" name="cirrusly_scan_config[api_key]" value="'.esc_attr($api_key).'" '.esc_attr($disabled_attr).' placeholder="Enter License Key"></td></tr>
            </table>
            <p class="description" style="margin-top: 15px; padding: 10px; background: #f0f9ff; border-left: 3px solid #2271b1;"><span class="dashicons dashicons-info" style="color: #2271b1;"></span> <strong>Merchant ID:</strong> Using global Merchant Center ID configured above</p>
        </div></div>';

        // Advanced Alerts (Pro)
        echo '<div class="cirrusly-settings-card '.esc_attr($pro_class).'">';
        if(!$is_pro) echo '<div class="cirrusly-pro-overlay"><a href="'.esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ).'" class="cirrusly-upgrade-btn"><span class="dashicons dashicons-lock cirrusly-lock-icon"></span> Unlock</a></div>';
        echo '<div class="cirrusly-card-header"><h3>Alerts <span class="cirrusly-pro-badge">PRO</span></h3><span class="dashicons dashicons-email-alt"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Configure proactive notifications for your store health. Receive weekly profit summaries and instant alerts if products are disapproved by Google.</p>
                <label><input type="checkbox" name="cirrusly_scan_config[alert_weekly_report]" value="yes" '.checked('yes', $alert_reports, false).' '.esc_attr($disabled_attr).'> Weekly Profit Reports</label><br>
                <label><input type="checkbox" name="cirrusly_scan_config[alert_gmc_disapproval]" value="yes" '.checked('yes', $alert_disapproval, false).' '.esc_attr($disabled_attr).'> Instant Disapproval Alerts</label>
            </div></div>';

        // Automated Discounts (Pro Plus)
        $is_pro_plus = class_exists( 'Cirrusly_Commerce_Core' ) && method_exists( 'Cirrusly_Commerce_Core', 'cirrusly_is_pro_plus' ) && Cirrusly_Commerce_Core::cirrusly_is_pro_plus();
        $pro_plus_class = $is_pro_plus ? '' : 'cirrusly-pro-feature';
        $disabled_attr_plus = $is_pro_plus ? '' : 'disabled';
        $ad_enabled = isset($scan['enable_automated_discounts']) ? $scan['enable_automated_discounts'] : '';
        $ad_public_key = isset($scan['google_public_key']) ? $scan['google_public_key'] : '';

        echo '<div class="cirrusly-settings-card '.esc_attr($pro_plus_class).'">';
        if(!$is_pro_plus) echo '<div class="cirrusly-pro-overlay"><a href="'.esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ).'" class="cirrusly-upgrade-btn"><span class="dashicons dashicons-lock cirrusly-lock-icon"></span> Unlock Pro Plus</a></div>';
        echo '<div class="cirrusly-card-header"><h3>Google Automated Discounts <span class="cirrusly-pro-badge" style="background: linear-gradient(135deg, #2271b1, #00a32a);">PRO PLUS</span></h3><span class="dashicons dashicons-google"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Allow Google to dynamically adjust your prices via Shopping Ads. Google tests different prices to maximize conversions while respecting your minimum price (Google Min Price) and cost floor.</p>
                <label style="margin-bottom: 15px; display: block;"><input type="checkbox" name="cirrusly_scan_config[enable_automated_discounts]" value="yes" '.checked('yes', $ad_enabled, false).' '.esc_attr($disabled_attr_plus).'> <strong>Enable Dynamic Pricing</strong></label>
                <p class="description" style="margin-bottom: 15px; padding: 10px; background: #f0f9ff; border-left: 3px solid #2271b1;"><span class="dashicons dashicons-info" style="color: #2271b1;"></span> <strong>Merchant ID:</strong> Using global Merchant Center ID configured above</p>
                <table class="form-table cirrusly-settings-table">
                    <tr>
                        <th scope="row"><label for="ad_public_key">Google Public Key (PEM)</label></th>
                        <td>
                            <textarea id="ad_public_key" name="cirrusly_scan_config[google_public_key]" rows="6" class="large-text code" '.esc_attr($disabled_attr_plus).' placeholder="-----BEGIN PUBLIC KEY-----&#10;MIIBIjANBgkqhki...&#10;-----END PUBLIC KEY-----" style="font-family: monospace; background:#f0f0f1; color:#50575e;">'.esc_textarea($ad_public_key).'</textarea>
                            <p class="description">⚠️ <strong>Note:</strong> Google does not provide a PEM key in Merchant Center UI. You may need to contact Google Support or use a test key for development. <a href="https://support.google.com/merchants/answer/11542980" target="_blank">Learn more about Automated Discounts →</a></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>';

        // Developer Tools
        $dev_mode = isset($scan['enable_dev_mode']) ? $scan['enable_dev_mode'] : '';
        echo '<div class="cirrusly-settings-card">
            <div class="cirrusly-card-header"><h3>Developer Tools</h3><span class="dashicons dashicons-admin-tools"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Advanced diagnostic tools for troubleshooting and development. Enable Developer Mode to access the API Debug Console from the admin menu.</p>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px;">
                    <input type="checkbox" name="cirrusly_scan_config[enable_dev_mode]" value="yes" '.checked('yes', $dev_mode, false).'>
                    <strong>Enable Developer Mode</strong>
                    <small style="color: #646970; margin-left: 5px;">Shows Debug Console in admin menu</small>
                </label>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="button" id="cirrusly-clear-cache-btn" class="button" style="padding: 8px 15px; border-radius: 6px; font-weight: 600;">
                        <span class="dashicons dashicons-update" style="line-height: inherit;"></span> Clear All Cache
                    </button>
                    <button type="button" id="cirrusly-reset-import-btn" class="button" style="padding: 8px 15px; border-radius: 6px; font-weight: 600;">
                        <span class="dashicons dashicons-image-rotate" style="line-height: inherit;"></span> Reset GMC Import
                    </button>
                </div>
                <div id="cirrusly-dev-tools-response" style="margin-top: 10px;"></div>
            </div>
        </div>';

        // Data Management
        $delete_on_uninstall = isset($scan['delete_on_uninstall']) ? $scan['delete_on_uninstall'] : '';
        echo '<div class="cirrusly-settings-card">
            <div class="cirrusly-card-header"><h3>Data Management</h3><span class="dashicons dashicons-database"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Export your plugin settings for backup or migration. Import previously exported settings to restore configuration.</p>
                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <button type="button" id="cirrusly-export-btn" class="button" style="padding: 8px 15px; border-radius: 6px; font-weight: 600;">
                        <span class="dashicons dashicons-download" style="line-height: inherit;"></span> Export Settings (JSON)
                    </button>
                    <label for="cirrusly-import-file" class="button" style="padding: 8px 15px; border-radius: 6px; font-weight: 600; margin: 0; cursor: pointer;">
                        <span class="dashicons dashicons-upload" style="line-height: inherit;"></span> Import Settings
                    </label>
                    <input type="file" id="cirrusly-import-file" accept=".json" style="display: none;">
                </div>
                <hr style="border: 0; border-top: 1px solid #e0e0e0; margin: 20px 0;">
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="cirrusly_scan_config[delete_on_uninstall]" value="yes" '.checked('yes', $delete_on_uninstall, false).'>
                    <strong>Delete all data on plugin uninstall</strong>
                    <small style="color: #d63638; margin-left: 5px;">(Warning: This cannot be undone)</small>
                </label>
                <div id="cirrusly-data-mgmt-response" style="margin-top: 10px;"></div>
            </div>
        </div>';
        
        echo '</div>';
    }

    private function render_badges_settings() {
        $cfg = get_option( 'cirrusly_badge_config', array() );
        $enabled = isset($cfg['enable_badges']) ? $cfg['enable_badges'] : '';
        $size = isset($cfg['badge_size']) ? $cfg['badge_size'] : 'medium';
        $calc_from = isset($cfg['calc_from']) ? $cfg['calc_from'] : 'msrp';
        $new_days = isset($cfg['new_days']) ? $cfg['new_days'] : 30;
        
        $smart_inv = isset($cfg['smart_inventory']) ? $cfg['smart_inventory'] : '';
        $smart_perf = isset($cfg['smart_performance']) ? $cfg['smart_performance'] : '';
        $smart_sched = isset($cfg['smart_scheduler']) ? $cfg['smart_scheduler'] : '';
        $sched_start = isset($cfg['scheduler_start']) ? $cfg['scheduler_start'] : '';
        $sched_end = isset($cfg['scheduler_end']) ? $cfg['scheduler_end'] : '';
        
        $custom_badges = isset($cfg['custom_badges_json']) ? json_decode($cfg['custom_badges_json'], true) : array();
        if(!is_array($custom_badges)) $custom_badges = array();

        $is_pro = class_exists( 'Cirrusly_Commerce_Core' ) && method_exists( 'Cirrusly_Commerce_Core', 'cirrusly_is_pro' ) && Cirrusly_Commerce_Core::cirrusly_is_pro();
        $pro_class = $is_pro ? '' : 'cirrusly-pro-feature';
        $disabled_attr = $is_pro ? '' : 'disabled';

        echo '<div class="cirrusly-settings-card">
            <div class="cirrusly-card-header"><h3>Badge Manager</h3></div>
            <div class="cirrusly-card-body">
            <p class="description">Configure global settings for product badges, including size and price basis. Define the "New" badge threshold to automatically highlight recently added products.</p>
            <table class="form-table cirrusly-settings-table">
                <tr><th>Enable Module</th><td><label><input type="checkbox" name="cirrusly_badge_config[enable_badges]" value="yes" '.checked('yes', $enabled, false).'> Activate</label></td></tr>
                <tr><th>Badge Size</th><td><select name="cirrusly_badge_config[badge_size]"><option value="small" '.selected('small', $size, false).'>Small</option><option value="medium" '.selected('medium', $size, false).'>Medium</option><option value="large" '.selected('large', $size, false).'>Large</option></select></td></tr>
                <tr><th>Discount Base</th><td><select name="cirrusly_badge_config[calc_from]"><option value="msrp" '.selected('msrp', $calc_from, false).'>MSRP</option><option value="regular" '.selected('regular', $calc_from, false).'>Regular Price</option></select></td></tr>
                <tr><th>"New" Badge</th><td><input type="number" name="cirrusly_badge_config[new_days]" value="'.esc_attr($new_days).'" style="width:70px;"> days</td></tr>
            </table>
            <hr><h4>Custom Tag Badges</h4>
            <p class="description">Map specific product tags to custom badge images. These badges will appear automatically on products containing the specified tag.</p>
            <table class="widefat striped cirrusly-settings-table"><thead><tr><th>Tag Slug</th><th>Image</th><th>Tooltip</th><th>Width</th><th></th></tr></thead><tbody id="cirrusly-badge-rows">';
            if(!empty($custom_badges)) {
                foreach($custom_badges as $idx => $badge) {
                    echo '<tr><td><input type="text" name="cirrusly_badge_config[custom_badges]['.esc_attr($idx).'][tag]" value="'.esc_attr($badge['tag']).'"></td><td><input type="text" name="cirrusly_badge_config[custom_badges]['.esc_attr($idx).'][url]" class="regular-text" value="'.esc_attr($badge['url']).'"> <button type="button" class="cirrusly-btn-secondary cirrusly-upload-btn" style="padding: 6px 12px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 4px;"><span class="dashicons dashicons-upload" style="line-height:inherit;"></span> Upload</button></td><td><input type="text" name="cirrusly_badge_config[custom_badges]['.esc_attr($idx).'][tooltip]" value="'.esc_attr($badge['tooltip']).'"></td><td><input type="number" name="cirrusly_badge_config[custom_badges]['.esc_attr($idx).'][width]" value="'.esc_attr($badge['width']).'" style="width:60px"> px</td><td><button type="button" class="cirrusly-btn-secondary cirrusly-remove-row" style="padding: 6px 10px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 4px;"><span class="dashicons dashicons-trash"></span></button></td></tr>';
                }
            }
            echo '</tbody></table><button type="button" class="cirrusly-btn-success" id="cirrusly-add-badge-row" style="margin-top:10px; padding: 8px 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-plus-alt" style="line-height:inherit;"></span> Add Badge Rule</button></div></div>';

        // Smart Badges (Pro)
        echo '<div class="cirrusly-settings-card '.esc_attr($pro_class).'">';
        if(!$is_pro) echo '<div class="cirrusly-pro-overlay"><a href="'.esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ).'" class="cirrusly-upgrade-btn"><span class="dashicons dashicons-lock cirrusly-lock-icon"></span> Unlock Smart Badges</a></div>';
        echo '<div class="cirrusly-card-header"><h3>Smart Badges <span class="cirrusly-pro-badge">PRO</span></h3><span class="dashicons dashicons-awards"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Enable intelligent badges based on live store data. Highlight low stock items, best sellers, or schedule specific event badges for a date range.</p>
                <label><input type="checkbox" name="cirrusly_badge_config[smart_inventory]" value="yes" '.checked('yes', $smart_inv, false).' '.esc_attr($disabled_attr).'> <strong>Low Stock:</strong> Show when qty < 5</label><br>
                <label><input type="checkbox" name="cirrusly_badge_config[smart_performance]" value="yes" '.checked('yes', $smart_perf, false).' '.esc_attr($disabled_attr).'> <strong>Best Seller:</strong> Show for top sellers</label><br>
                <div style="margin-top:10px;">
                    <label><input type="checkbox" name="cirrusly_badge_config[smart_scheduler]" value="yes" '.checked('yes', $smart_sched, false).' '.esc_attr($disabled_attr).'> <strong>Scheduler:</strong> Show "Event" between dates:</label><br>
                    <input type="date" name="cirrusly_badge_config[scheduler_start]" value="'.esc_attr($sched_start).'" '.esc_attr($disabled_attr).'> to 
                    <input type="date" name="cirrusly_badge_config[scheduler_end]" value="'.esc_attr($sched_end).'" '.esc_attr($disabled_attr).'>
                </div>
            </div></div>';
    }

    private function render_profit_engine_settings() {
        $config = self::get_global_config();
        $revenue_tiers = json_decode( $config['revenue_tiers_json'], true );
        $matrix_rules = json_decode( $config['matrix_rules_json'], true );
        $class_costs  = json_decode( $config['class_costs_json'], true );
        
        $payment_pct = isset($config['payment_pct']) ? $config['payment_pct'] : 2.9;
        $payment_flat = isset($config['payment_flat']) ? $config['payment_flat'] : 0.30;
        $profile_mode = isset($config['profile_mode']) ? $config['profile_mode'] : 'single';

        if ( ! is_array( $revenue_tiers ) ) $revenue_tiers = array();
        if ( ! is_array( $matrix_rules ) )  $matrix_rules  = array();
        if ( ! is_array( $class_costs ) )   $class_costs   = array();

        $terms = get_terms( array( 'taxonomy' => 'product_shipping_class', 'hide_empty' => false ) );
        $all_classes = array( 'default' => 'Default (No Class)' );
        if( ! is_wp_error( $terms ) ) { foreach ( $terms as $term ) { $all_classes[ $term->slug ] = $term->name; } }

        $is_pro = class_exists( 'Cirrusly_Commerce_Core' ) && method_exists( 'Cirrusly_Commerce_Core', 'cirrusly_is_pro' ) && Cirrusly_Commerce_Core::cirrusly_is_pro();
        $pro_class = $is_pro ? '' : 'cirrusly-pro-feature';
        $disabled_attr = $is_pro ? '' : 'disabled';

        // Revenue Tiers
        echo '<div class="cirrusly-settings-card"><div class="cirrusly-card-header"><h3>1. Shipping Revenue</h3></div>';
        echo '<div class="cirrusly-card-body">
        <p class="description">Define the shipping revenue collected from customers based on cart total. Set price ranges (Min/Max) and the corresponding shipping charge for each tier.</p>
        <table class="widefat striped cirrusly-settings-table"><thead><tr><th>Min Price</th><th>Max Price</th><th>Charge</th><th></th></tr></thead><tbody id="cirrusly-revenue-rows">';
        foreach($revenue_tiers as $idx => $tier) {
            echo '<tr><td><input type="number" step="0.01" name="cirrusly_shipping_config[revenue_tiers]['.esc_attr($idx).'][min]" value="'.esc_attr($tier['min']).'"></td><td><input type="number" step="0.01" name="cirrusly_shipping_config[revenue_tiers]['.esc_attr($idx).'][max]" value="'.esc_attr($tier['max']).'"></td><td><input type="number" step="0.01" name="cirrusly_shipping_config[revenue_tiers]['.esc_attr($idx).'][charge]" value="'.esc_attr($tier['charge']).'"></td><td><button type="button" class="cirrusly-btn-secondary cirrusly-remove-row" style="padding: 6px 10px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 4px;"><span class="dashicons dashicons-trash"></span></button></td></tr>';
        }
        echo '</tbody></table><button type="button" class="cirrusly-btn-success" id="cirrusly-add-revenue-row" style="margin-top:10px; padding: 8px 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-plus-alt" style="line-height:inherit;"></span> Add Tier</button></div></div>';

        // Class Costs
        echo '<div class="cirrusly-settings-card"><div class="cirrusly-card-header"><h3>2. Internal Cost</h3></div><div class="cirrusly-card-body">
        <p class="description">Estimate your actual shipping and fulfillment costs per shipping class. These values are used to calculate the net profit and margin for each order.</p>
        <table class="widefat striped cirrusly-settings-table"><thead><tr><th>Class</th><th>Cost ($)</th></tr></thead><tbody>';
        foreach ( $all_classes as $slug => $name ) {
            $val = isset( $class_costs[$slug] ) ? $class_costs[$slug] : ( ($slug==='default')?10.00:0.00 );
            echo '<tr><td><strong>'.esc_html($name).'</strong><br><small>'.esc_html($slug).'</small></td><td><input type="number" step="0.01" name="cirrusly_shipping_config[class_costs]['.esc_attr($slug).']" value="'.esc_attr($val).'"></td></tr>';
        }
        echo '</tbody></table></div></div>';

        // Payment
        echo '<div class="cirrusly-settings-card"><div class="cirrusly-card-header"><h3>Payment Fees</h3></div><div class="cirrusly-card-body">
            <p class="description">Enter your payment processor fees (e.g., Stripe, PayPal) to calculate true net revenue. For mixed profiles, define secondary rates and the split percentage.</p>
            <input type="number" step="0.1" name="cirrusly_shipping_config[payment_pct]" value="'.esc_attr($payment_pct).'"> % + <input type="number" step="0.01" name="cirrusly_shipping_config[payment_flat]" value="'.esc_attr($payment_flat).'"> $
            <div class="'.esc_attr($pro_class).'" style="margin-top:15px; border-top:1px dashed #ccc; padding-top:15px;">
                <p><strong>Multi-Profile <span class="cirrusly-pro-badge">PRO</span></strong></p>
                <label><input type="radio" name="cirrusly_shipping_config[profile_mode]" value="single" '.checked('single', $profile_mode, false).' '.esc_attr($disabled_attr).'> Single</label><br>
                <label><input type="radio" name="cirrusly_shipping_config[profile_mode]" value="multi" '.checked('multi', $profile_mode, false).' '.esc_attr($disabled_attr).'> Mixed</label><br>
                <div style="display:'.($profile_mode==='multi'?'block':'none').';">
                    Secondary: <input type="number" step="0.1" name="cirrusly_shipping_config[payment_pct_2]" value="'.esc_attr(isset($config['payment_pct_2'])?$config['payment_pct_2']:3.49).'" style="width:60px"> % + <input type="number" step="0.01" name="cirrusly_shipping_config[payment_flat_2]" value="'.esc_attr(isset($config['payment_flat_2'])?$config['payment_flat_2']:0.49).'" style="width:60px"> $<br>
                    Split: <input type="number" name="cirrusly_shipping_config[profile_split]" value="'.esc_attr(isset($config['profile_split'])?$config['profile_split']:100).'" style="width:60px"> % Primary
                </div>
            </div></div></div>';

        // Matrix
        echo '<div class="cirrusly-settings-card"><div class="cirrusly-card-header"><h3>3. Scenario Matrix</h3></div><div class="cirrusly-card-body">
        <p class="description">Create different cost scenarios (e.g., "High Gas Prices") by applying multipliers to your base costs. Use these in the Financial Audit tool to stress-test your margins.</p>
        <table class="widefat striped cirrusly-settings-table"><thead><tr><th>Key</th><th>Label</th><th>Multiplier</th><th></th></tr></thead><tbody id="cirrusly-matrix-rows">';
        $idx = 0;
        foreach ( $matrix_rules as $rule ) {
            $keyVal = isset( $rule['key'] ) ? $rule['key'] : 'rule_' . $idx;
            echo '<tr><td><input type="text" name="cirrusly_shipping_config[matrix_rules][' . esc_attr( $idx ) . '][key]" value="' . esc_attr( $keyVal ) . '"></td><td><input type="text" name="cirrusly_shipping_config[matrix_rules][' . esc_attr( $idx ) . '][label]" value="' . esc_attr( $rule['label'] ) . '"></td><td>x <input type="number" step="0.1" name="cirrusly_shipping_config[matrix_rules][' . esc_attr( $idx ) . '][cost_mult]" value="' . esc_attr( isset( $rule['cost_mult'] ) ? $rule['cost_mult'] : 1.0 ) . '"></td><td><button type="button" class="cirrusly-btn-secondary cirrusly-remove-row" style="padding: 6px 10px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 4px;"><span class="dashicons dashicons-trash"></span></button></td></tr>';
            $idx++;
        }
        echo '</tbody></table><button type="button" class="cirrusly-btn-success" id="cirrusly-add-matrix-row" style="margin-top:10px; padding: 8px 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-plus-alt" style="line-height:inherit;"></span> Add Scenario</button></div></div>';
    }

    public function render_onboarding_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // Suppress notice on setup wizard page
        if ( isset( $_GET['page'] ) && $_GET['page'] === 'cirrusly-setup' ) {
            return;
        }

        $config = get_option( 'cirrusly_shipping_config' );
        if ( ! $config || empty( $config['revenue_tiers_json'] ) ) {
            echo '<div class="notice notice-info is-dismissible"><p><strong>Welcome!</strong> Please set up your <a href="'.esc_url( admin_url('admin.php?page=cirrusly-settings&tab=shipping') ).'">Profit Intelligence</a>.</p></div>';
        }
    }

    /**
     * Sanitize GMC product mappings (offer_id => product_id pairs)
     */
    public function sanitize_gmc_mappings( $input ) {
        $clean = array();
        if ( is_array( $input ) ) {
            foreach ( $input as $offer_id => $product_id ) {
                $clean_offer_id = sanitize_text_field( $offer_id );
                $clean_product_id = intval( $product_id );
                if ( $clean_product_id > 0 ) {
                    $clean[ $clean_offer_id ] = $clean_product_id;
                }
            }
        }
        return $clean;
    }

    /**
     * AJAX: Clear all analytics and audit cache
     */
    public function handle_clear_cache() {
        check_ajax_referer( 'cirrusly-admin-nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        
        // Clear audit transient
        delete_transient( 'cirrusly_audit_data' );
        
        // Clear dashboard metrics
        delete_transient( 'cirrusly_dashboard_metrics' );
        
        // Invalidate all analytics transients by updating version key
        update_option( 'cirrusly_analytics_cache_version', time(), false );
        
        // Clear GMC scan data
        delete_transient( 'cirrusly_gmc_scan_debug' );
        delete_transient( 'cirrusly_active_promos_stats' );
        
        wp_send_json_success( array( 'message' => 'All cache cleared successfully!' ) );
    }

    /**
     * AJAX: Reset GMC import status
     */
    public function handle_reset_import() {
        check_ajax_referer( 'cirrusly-admin-nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        
        // Reset import progress flag
        delete_option( 'cirrusly_gmc_import_progress' );
        
        // Clear imported data markers
        delete_option( 'cirrusly_gmc_last_import_date' );
        delete_option( 'cirrusly_gmc_import_total' );
        delete_option( 'cirrusly_gmc_analytics_imported' );
        
        wp_send_json_success( array( 'message' => 'GMC import status reset. You can now run a fresh import.' ) );
    }

    /**
     * AJAX: Export all plugin settings as JSON
     */
    public function handle_export_settings() {
        check_ajax_referer( 'cirrusly-admin-nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        
        // Collect all Cirrusly settings
        $settings = array(
            'scan_config' => get_option( 'cirrusly_scan_config', array() ),
            'shipping_config' => get_option( 'cirrusly_shipping_config', array() ),
            'badge_config' => get_option( 'cirrusly_badge_config', array() ),
            'msrp_config' => get_option( 'cirrusly_msrp_config', array() ),
            'google_reviews_config' => get_option( 'cirrusly_google_reviews_config', array() ),
            'countdown_rules' => get_option( 'cirrusly_countdown_rules', array() ),
            'gmc_mapping_config' => get_option( 'cirrusly_gmc_mapping_config', array() ),
            'analytics_preferences' => get_option( 'cirrusly_analytics_preferences', array() ),
            'export_date' => current_time( 'mysql' ),
            'export_version' => CIRRUSLY_COMMERCE_VERSION
        );
        
        wp_send_json_success( array( 
            'settings' => $settings,
            'filename' => 'cirrusly-settings-' . wp_date( 'Y-m-d-His' ) . '.json'
        ) );
    }

    /**
     * AJAX: Import plugin settings from JSON
     */
    public function handle_import_settings() {
        check_ajax_referer( 'cirrusly-admin-nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }
        
        // Validate JSON input
        if ( ! isset( $_POST['settings'] ) ) {
            wp_send_json_error( array( 'message' => 'No settings data provided' ) );
        }
        
        $settings = json_decode( stripslashes( $_POST['settings'] ), true );
        
        if ( ! is_array( $settings ) ) {
            wp_send_json_error( array( 'message' => 'Invalid JSON format' ) );
        }
        
        // Import each setting group
        $imported = array();
        $settings_map = array(
            'scan_config' => array(
                'option' => 'cirrusly_scan_config',
                'sanitizer' => 'handle_scan_schedule'
            ),
            'shipping_config' => array(
                'option' => 'cirrusly_shipping_config',
                'sanitizer' => 'sanitize_settings'
            ),
            'badge_config' => array(
                'option' => 'cirrusly_badge_config',
                'sanitizer' => 'sanitize_settings'
            ),
            'msrp_config' => array(
                'option' => 'cirrusly_msrp_config',
                'sanitizer' => 'sanitize_options_array'
            ),
            'google_reviews_config' => array(
                'option' => 'cirrusly_google_reviews_config',
                'sanitizer' => 'sanitize_options_array'
            ),
            'countdown_rules' => array(
                'option' => 'cirrusly_countdown_rules',
                'sanitizer' => 'sanitize_countdown_rules'
            ),
            'gmc_mapping_config' => array(
                'option' => 'cirrusly_gmc_mapping_config',
                'sanitizer' => 'sanitize_options_array'
            ),
            'analytics_preferences' => array(
                'option' => 'cirrusly_analytics_preferences',
                'sanitizer' => 'sanitize_options_array'
            )
        );
        
        foreach ( $settings_map as $key => $config ) {
            if ( isset( $settings[ $key ] ) && is_array( $settings[ $key ] ) ) {
                // Apply the registered sanitization callback before saving
                $sanitized_data = call_user_func( array( $this, $config['sanitizer'] ), $settings[ $key ] );
                update_option( $config['option'], $sanitized_data, false );
                $imported[] = $key;
            }
        }
        
        if ( empty( $imported ) ) {
            wp_send_json_error( array( 'message' => 'No valid settings found in import file' ) );
        }
        
        wp_send_json_success( array(
            'message' => 'Settings imported successfully! ' . count( $imported ) . ' groups updated.',
            'imported' => $imported
        ) );
    }

    /**
     * AJAX: Validate API Key
     * Returns status, plan_id, quota usage, and expiration info
     */
    public function handle_validate_api_key() {
        // Accept both settings page nonce and wizard nonce
        $nonce_valid = false;
        if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cirrusly-admin-nonce' ) ) {
            $nonce_valid = true;
        } elseif ( isset( $_POST['_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'cirrusly_validate_api_key' ) ) {
            $nonce_valid = true;
        }

        if ( ! $nonce_valid ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        // Load API Key Manager
        if ( ! class_exists( 'Cirrusly_Commerce_API_Key_Manager' ) ) {
            $api_key_manager_path = CIRRUSLY_COMMERCE_PATH . 'includes/pro/class-api-key-manager.php';
            if ( file_exists( $api_key_manager_path ) ) {
                require_once $api_key_manager_path;
            } else {
                wp_send_json_error( array( 'message' => 'API Key Manager not available' ) );
            }
        }

        // Validate key
        $result = Cirrusly_Commerce_API_Key_Manager::validate_key_status();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Regenerate API Key
     * Enforces 7-day cooldown and logs reason
     */
    public function handle_regenerate_api_key() {
        // Accept both settings page nonce and wizard nonce
        $nonce_valid = false;
        if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cirrusly-admin-nonce' ) ) {
            $nonce_valid = true;
        } elseif ( isset( $_POST['_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'cirrusly_regenerate_api_key' ) ) {
            $nonce_valid = true;
        }

        if ( ! $nonce_valid ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        // Get reason from POST
        $reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : 'other';

        // Validate reason
        $valid_reasons = array( 'compromise', 'testing', 'other' );
        if ( ! in_array( $reason, $valid_reasons, true ) ) {
            wp_send_json_error( array( 'message' => 'Invalid regeneration reason' ) );
        }

        // Load API Key Manager
        if ( ! class_exists( 'Cirrusly_Commerce_API_Key_Manager' ) ) {
            $api_key_manager_path = CIRRUSLY_COMMERCE_PATH . 'includes/pro/class-api-key-manager.php';
            if ( file_exists( $api_key_manager_path ) ) {
                require_once $api_key_manager_path;
            } else {
                wp_send_json_error( array( 'message' => 'API Key Manager not available' ) );
            }
        }

        // Regenerate key
        $result = Cirrusly_Commerce_API_Key_Manager::regenerate_api_key( $reason );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Get cooldown info
        $cooldown_days = Cirrusly_Commerce_API_Key_Manager::get_regeneration_cooldown_days();

        wp_send_json_success( array(
            'api_key' => $result['api_key'],
            'regenerated' => $result['regenerated'],
            'cooldown_days' => $cooldown_days,
            'message' => 'API key regenerated successfully. Next regeneration available in ' . $cooldown_days . ' days.'
        ) );
    }

    /**
     * AJAX: Link Manual API Key to Freemius Install
     * Migration endpoint for existing manual keys
     */
    public function handle_link_manual_key() {
        // Accept both settings page nonce and wizard nonce
        $nonce_valid = false;
        if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cirrusly-admin-nonce' ) ) {
            $nonce_valid = true;
        } elseif ( isset( $_POST['_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'cirrusly_link_manual_key' ) ) {
            $nonce_valid = true;
        }

        if ( ! $nonce_valid ) {
            wp_send_json_error( array( 'message' => 'Security check failed' ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        // Check if Freemius is available
        if ( ! function_exists( 'cirrusly_fs' ) ) {
            wp_send_json_error( array( 'message' => 'Freemius SDK not available' ) );
        }

        $install = cirrusly_fs()->get_site();
        if ( ! $install || ! isset( $install->id ) ) {
            wp_send_json_error( array( 'message' => 'Freemius installation not found' ) );
        }

        // Get current API key
        $config = get_option( 'cirrusly_scan_config', array() );
        $api_key = isset( $config['api_key'] ) ? $config['api_key'] : '';

        if ( ! $api_key ) {
            wp_send_json_error( array( 'message' => 'No API key found to link' ) );
        }

        // Check if already auto-generated
        if ( isset( $config['auto_generated'] ) && $config['auto_generated'] ) {
            wp_send_json_error( array( 'message' => 'API key is already linked to your Freemius account' ) );
        }

        // Load API Key Manager
        if ( ! class_exists( 'Cirrusly_Commerce_API_Key_Manager' ) ) {
            $api_key_manager_path = CIRRUSLY_COMMERCE_PATH . 'includes/pro/class-api-key-manager.php';
            if ( file_exists( $api_key_manager_path ) ) {
                require_once $api_key_manager_path;
            } else {
                wp_send_json_error( array( 'message' => 'API Key Manager not available' ) );
            }
        }

        // Link the key
        $result = Cirrusly_Commerce_API_Key_Manager::link_manual_key( $api_key, $install->id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'message' => 'API key successfully linked to your Freemius account. Automatic management is now enabled.'
        ) );
    }

    /**
     * AJAX: Generate API Key
     * For Pro users who don't have an API key yet
     */
    public function handle_generate_api_key() {
    // Accept both settings page nonce and wizard nonce
    $nonce_valid = false;
    if ( isset( $_POST['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'cirrusly-admin-nonce' ) ) {
        $nonce_valid = true;
    } elseif ( isset( $_POST['_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ), 'cirrusly_generate_api_key' ) ) {
        $nonce_valid = true;
    }

    if ( ! $nonce_valid ) {
        wp_send_json_error( array( 'message' => 'Security check failed' ) );
    }

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Permission denied' ) );
    }

    // Check if Freemius is available
    if ( ! function_exists( 'cirrusly_fs' ) ) {
        wp_send_json_error( array( 'message' => 'Freemius SDK not available' ) );
    }

    // Get Freemius install info
    $install = cirrusly_fs()->get_site();
    if ( ! $install || ! isset( $install->id ) ) {
        wp_send_json_error( array( 'message' => 'Freemius installation not found' ) );
    }

    $user = cirrusly_fs()->get_user();
    if ( ! $user || ! isset( $user->id ) ) {
        wp_send_json_error( array( 'message' => 'Freemius user not found' ) );
    }

    // 🔧 MOVED: Load API Key Manager BEFORE using class constants
    if ( ! class_exists( 'Cirrusly_Commerce_API_Key_Manager' ) ) {
        $api_key_manager_path = CIRRUSLY_COMMERCE_PATH . 'includes/pro/class-api-key-manager.php';
        if ( file_exists( $api_key_manager_path ) ) {
            require_once $api_key_manager_path;
        } else {
            wp_send_json_error( array( 'message' => 'API Key Manager not available' ) );
        }
    }

    // Get plan ID (now safe to use class constants)
    $plan_id = Cirrusly_Commerce_API_Key_Manager::PLAN_ID_FREE; // Default to free
    if ( cirrusly_fs()->is_plan( 'proplus' ) ) {
        $plan_id = Cirrusly_Commerce_API_Key_Manager::PLAN_ID_PROPLUS;
    } elseif ( cirrusly_fs()->can_use_premium_code() ) {
        $plan_id = Cirrusly_Commerce_API_Key_Manager::PLAN_ID_PRO;
    }

    // Check if API key already exists
    $config = get_option( 'cirrusly_scan_config', array() );
    if ( ! empty( $config['api_key'] ) ) {
        wp_send_json_error( array( 'message' => 'API key already exists. Use regenerate if you need a new key.' ) );
    }

    // Request new API key
    $result = Cirrusly_Commerce_API_Key_Manager::request_api_key( $install->id, $user->id, $plan_id );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( array( 'message' => $result->get_error_message() ) );
    }

    // Store the key
    $stored = Cirrusly_Commerce_API_Key_Manager::store_api_key( $result['api_key'], $result );

    if ( ! $stored ) {
        wp_send_json_error( array( 'message' => 'Failed to store API key' ) );
    }

    wp_send_json_success( array(
        'api_key' => $result['api_key'],
        'plan_id' => $result['plan_id'],
        'generated' => $result['generated'],
        'message' => 'API key generated successfully'
    ) );
}

    /**
     * Render Analytics Preferences settings tab (Pro Plus only)
     */
    private function render_analytics_settings() {
        $prefs = get_option( 'cirrusly_analytics_preferences', array() );
        
        $default_range = isset($prefs['default_range']) ? $prefs['default_range'] : '30';
        $auto_import = isset($prefs['auto_import']) ? $prefs['auto_import'] : '';
        $email_summary = isset($prefs['email_summary']) ? $prefs['email_summary'] : '';
        $chart_view = isset($prefs['chart_view']) ? $prefs['chart_view'] : 'both';
        
        echo '<div class="cirrusly-settings-grid">';
        
        // General Preferences
        echo '<div class="cirrusly-settings-card">
            <div class="cirrusly-card-header"><h3>Analytics Preferences</h3><span class="dashicons dashicons-chart-bar"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Configure default settings for the Analytics dashboard. These preferences will be applied when you first visit the Analytics page.</p>
                <table class="form-table cirrusly-settings-table">
                    <tr>
                        <th scope="row">Default Date Range</th>
                        <td>
                            <select name="cirrusly_analytics_preferences[default_range]">
                                <option value="7" '.selected('7', $default_range, false).'>Last 7 Days</option>
                                <option value="14" '.selected('14', $default_range, false).'>Last 14 Days</option>
                                <option value="30" '.selected('30', $default_range, false).'>Last 30 Days</option>
                                <option value="90" '.selected('90', $default_range, false).'>Last 90 Days</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Default Chart View</th>
                        <td>
                            <select name="cirrusly_analytics_preferences[chart_view]">
                                <option value="net_profit" '.selected('net_profit', $chart_view, false).'>Net Profit Only</option>
                                <option value="gross_sales" '.selected('gross_sales', $chart_view, false).'>Gross Sales Only</option>
                                <option value="both" '.selected('both', $chart_view, false).'>Both Metrics</option>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
        </div>';
        
        // Automation
        echo '<div class="cirrusly-settings-card">
            <div class="cirrusly-card-header"><h3>Automation</h3><span class="dashicons dashicons-update"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Automate data synchronization and reporting tasks to keep your analytics up to date without manual intervention.</p>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 15px;">
                    <input type="checkbox" name="cirrusly_analytics_preferences[auto_import]" value="yes" '.checked('yes', $auto_import, false).'>
                    <strong>Auto-import GMC data on daily scan</strong>
                    <small style="color: #646970; margin-left: 5px;">Fetches latest performance metrics automatically</small>
                </label>
                <label style="display: flex; align-items: center; gap: 8px;">
                    <input type="checkbox" name="cirrusly_analytics_preferences[email_summary]" value="yes" '.checked('yes', $email_summary, false).'>
                    <strong>Email weekly analytics summary</strong>
                    <small style="color: #646970; margin-left: 5px;">Sent every Monday morning</small>
                </label>
            </div>
        </div>';
        
        echo '</div>';
    }

    /**
     * Render GMC Product Mapping settings tab
     */
    private function render_gmc_mapping_settings() {
        $unmapped = get_option( 'cirrusly_gmc_unmapped_products', array() );
        $manual_mappings = get_option( 'cirrusly_gmc_product_mapping', array() );
        
        echo '<div class="cirrusly-settings-card">';
        echo '<div class="cirrusly-card-header"><h3>GMC Product Mapping</h3><span class="dashicons dashicons-admin-links"></span></div>';
        echo '<div class="cirrusly-card-body">';
        echo '<p class="description">Map Google Merchant Center offer IDs to WooCommerce products. Products are auto-mapped via SKU when possible. Use this table to manually map products that couldn\'t be auto-matched.</p>';
        
        if ( ! empty( $unmapped ) ) {
            echo '<div class="notice notice-warning inline" style="margin: 15px 0;"><p><strong>Unmapped Products:</strong> ' . count( $unmapped ) . ' GMC products could not be auto-mapped via SKU.</p></div>';
        } else {
            echo '<div class="notice notice-success inline" style="margin: 15px 0;"><p><strong>All Set!</strong> No unmapped products detected. All GMC products were successfully auto-mapped via SKU.</p></div>';
        }

        // CSV Import/Export Buttons
        echo '<div style="margin-bottom: 15px; display: flex; gap: 10px;">';
        echo '<button type="button" id="cirrusly-export-mappings" class="cirrusly-btn-secondary" style="padding: 8px 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-download" style="line-height:inherit;"></span> Export CSV</button>';
        echo '<button type="button" id="cirrusly-import-mappings" class="cirrusly-btn-secondary" style="padding: 8px 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-upload" style="line-height:inherit;"></span> Import CSV</button>';
        echo '<input type="file" id="cirrusly-mapping-csv-upload" accept=".csv" style="display:none;">';
        echo '</div>';

        // Mapping Table
        echo '<table class="widefat striped cirrusly-settings-table">';
        echo '<thead><tr><th>GMC Offer ID</th><th>Product Title (GMC)</th><th>WooCommerce Product</th><th>Status</th><th>Actions</th></tr></thead>';
        echo '<tbody id="cirrusly-mapping-table">';
        
        // Show unmapped products first
        if ( ! empty( $unmapped ) ) {
            foreach ( $unmapped as $offer_id => $title ) {
                $mapped_id = isset( $manual_mappings[ $offer_id ] ) ? $manual_mappings[ $offer_id ] : 0;
                echo '<tr data-offer-id="' . esc_attr( $offer_id ) . '">';
                echo '<td><strong>' . esc_html( $offer_id ) . '</strong></td>';
                echo '<td>' . esc_html( $title ) . '</td>';
                echo '<td>';
                
                // Using Select2 with AJAX search for better performance
                // Consider adding a 'cirrusly_search_products' AJAX endpoint
                $args = array(
                    'post_type' => array( 'product', 'product_variation' ),
                    'posts_per_page' => 100, // Initial limit
                    'post_status' => 'publish',
                    'orderby' => 'title',
                    'order' => 'ASC'
                );
                $products = get_posts( $args );
                echo '<select name="cirrusly_gmc_product_mapping[' . esc_attr( $offer_id ) . ']" class="cirrusly-product-select" style="width: 100%; max-width: 400px;">';
                echo '<option value="0">— Select Product —</option>';
                foreach ( $products as $product ) {
                    $product_obj = wc_get_product( $product->ID );
                    $sku = $product_obj ? $product_obj->get_sku() : '';
                    $label = $product->post_title . ( $sku ? ' (SKU: ' . $sku . ')' : '' );
                    echo '<option value="' . esc_attr( $product->ID ) . '" ' . selected( $mapped_id, $product->ID, false ) . '>' . esc_html( $label ) . '</option>';
                }
                echo '</select>';
                echo '</td>';
                echo '<td>';
                if ( $mapped_id > 0 ) {
                    echo '<span class="cirrusly-badge-pill" style="background: #008a20;">Mapped</span>';
                } else {
                    echo '<span class="cirrusly-badge-pill" style="background: #dba617;">Unmapped</span>';
                }
                echo '</td>';
                echo '<td><button type="button" class="cirrusly-btn-secondary cirrusly-remove-mapping" data-offer-id="' . esc_attr( $offer_id ) . '" style="padding: 6px 10px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 4px;"><span class="dashicons dashicons-trash"></span></button></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #646970;">No unmapped products. All GMC products have been auto-matched!</td></tr>';
        }
        
        echo '</tbody></table>';
        
        echo '<p class="description" style="margin-top: 15px;"><strong>CSV Format:</strong> gmc_offer_id,woocommerce_product_id,notes</p>';
        
        echo '</div></div>';
        
        // Automation Settings
        $automation_config = get_option( 'cirrusly_gmc_mapping_config', array() );
        $auto_retry = isset($automation_config['auto_retry']) ? $automation_config['auto_retry'] : '';
        $email_alerts = isset($automation_config['email_alerts']) ? $automation_config['email_alerts'] : '';
        $bulk_sku_pattern = isset($automation_config['bulk_sku_pattern']) ? $automation_config['bulk_sku_pattern'] : '';
        
        echo '<div class="cirrusly-settings-card" style="margin-top: 20px;">
            <div class="cirrusly-card-header"><h3>Automation</h3><span class="dashicons dashicons-update"></span></div>
            <div class="cirrusly-card-body">
                <p class="description">Automate product mapping tasks to reduce manual work. These features run during the daily GMC health scan.</p>
                <table class="form-table cirrusly-settings-table">
                    <tr>
                        <th scope="row">Auto-Retry Unmapped</th>
                        <td>
                            <label>
                                <input type="checkbox" name="cirrusly_gmc_mapping_config[auto_retry]" value="yes" '.checked('yes', $auto_retry, false).'>
                                Automatically retry mapping unmapped products on daily scan
                            </label>
                            <p class="description">Useful when products are added to GMC before WooCommerce</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Email Alerts</th>
                        <td>
                            <label>
                                <input type="checkbox" name="cirrusly_gmc_mapping_config[email_alerts]" value="yes" '.checked('yes', $email_alerts, false).'>
                                Send email when new unmapped products are detected
                            </label>
                            <p class="description">Sent to site admin email address</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Bulk SKU Pattern</th>
                        <td>
                            <input type="text" name="cirrusly_gmc_mapping_config[bulk_sku_pattern]" value="'.esc_attr($bulk_sku_pattern).'" class="regular-text" placeholder="e.g., PROD-">
                            <p class="description">Automatically map products where GMC offer ID starts with this prefix + WooCommerce SKU</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>';
        
        // JavaScript for CSV export/import
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Export CSV
            $('#cirrusly-export-mappings').on('click', function() {
                var mappings = {};
                $('#cirrusly-mapping-table tr[data-offer-id]').each(function() {
                    var offerId = $(this).data('offer-id');
                    var productId = $(this).find('select').val();
                    if (productId > 0) {
                        mappings[offerId] = productId;
                    }
                });
                
                var csv = 'gmc_offer_id,woocommerce_product_id,notes\n';
                $.each(mappings, function(offerId, productId) {
                    csv += offerId + ',' + productId + ',\n';
                });
                
                var blob = new Blob([csv], { type: 'text/csv' });
                var url = URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url;
                a.download = 'gmc_product_mappings_' + Date.now() + '.csv';
                a.click();
            });
            
            // Import CSV
            $('#cirrusly-import-mappings').on('click', function() {
                $('#cirrusly-mapping-csv-upload').click();
            });
            
            $('#cirrusly-mapping-csv-upload').on('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    var csv = e.target.result;
                    var lines = csv.split('\n');
                    
                    for (var i = 1; i < lines.length; i++) {
                        var line = lines[i].trim();
                        if (!line) continue;
                        
                        var parts = line.split(',');
                        var offerId = parts[0].replace(/["\\]/g, '\\$&'); // Escape special chars for attr selector
                        var productId = parts[1];
                        
                        var $row = $('#cirrusly-mapping-table tr[data-offer-id="' + offerId + '"]');
                        if ($row.length) {
                            $row.find('select').val(productId).trigger('change');
                        }
                    }
                    
                    alert('CSV imported! Please save settings to apply changes.');
                };
                reader.readAsText(file);
            });
            
            // Remove mapping
            $('.cirrusly-remove-mapping').on('click', function() {
                var offerId = $(this).data('offer-id');
                $('#cirrusly-mapping-table tr[data-offer-id="' + offerId + '"] select').val('0').trigger('change');
            });
            
            // Update status badge when select changes
            $('.cirrusly-product-select').on('change', function() {
                var $row = $(this).closest('tr');
                var val = $(this).val();
                var $statusCell = $row.find('td:eq(3)');
                
                if (val > 0) {
                    $statusCell.html('<span class="cirrusly-badge-pill" style="background: #008a20;">Mapped</span>');
                } else {
                    $statusCell.html('<span class="cirrusly-badge-pill" style="background: #dba617;">Unmapped</span>');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Render Email Settings tab UI
     */
    private function render_email_settings() {
        // Ensure Mailer class is loaded
        if ( ! class_exists( 'Cirrusly_Commerce_Mailer' ) ) {
            require_once CIRRUSLY_COMMERCE_PATH . 'class-mailer.php';
        }

        $email_settings = get_option( 'cirrusly_email_settings', array() );
        
        // Email types configuration
        $email_types = array(
            'bug_report' => array(
                'title' => 'Bug Reports',
                'description' => 'Sent to help@cirruslyweather.com when users submit bug reports',
                'tier' => 'free',
            ),
            'weekly_profit' => array(
                'title' => 'Weekly Profit Reports',
                'description' => 'P&L summary sent every Monday at 9 AM site time',
                'tier' => 'pro',
            ),
            'gmc_import' => array(
                'title' => 'GMC Analytics Import Complete',
                'description' => 'Notification when 90-day GMC analytics import finishes',
                'tier' => 'pro_plus',
            ),
            'webhook_failures' => array(
                'title' => 'Webhook Failure Alerts',
                'description' => 'Critical alert when 3+ consecutive webhook failures occur',
                'tier' => 'pro',
            ),
            'gmc_disapproval' => array(
                'title' => 'GMC Product Disapprovals',
                'description' => 'Real-time alert when Google disapproves products',
                'tier' => 'pro',
            ),
            'unmapped_products' => array(
                'title' => 'Unmapped Products Alert',
                'description' => 'Notification when new unmapped GMC products are detected',
                'tier' => 'pro_plus',
            ),
            'weekly_analytics' => array(
                'title' => 'Weekly Analytics Summary',
                'description' => 'GMC performance summary sent every Monday morning',
                'tier' => 'pro_plus',
            ),
        );

        $is_pro = class_exists( 'Cirrusly_Commerce_Core' ) && Cirrusly_Commerce_Core::cirrusly_is_pro();
        $is_pro_plus = class_exists( 'Cirrusly_Commerce_Core' ) && Cirrusly_Commerce_Core::cirrusly_is_pro_plus();

        echo '<div class="cirrusly-settings-grid">';

        // Email Log Card
        echo '<div class="cirrusly-settings-card" style="grid-column: 1 / -1;">
            <div class="cirrusly-card-header"><h3>Email Activity Log</h3><span class="dashicons dashicons-email"></span></div>
            <div class="cirrusly-card-body">';

        $email_log = Cirrusly_Commerce_Mailer::get_email_log( 20 );

        if ( ! empty( $email_log ) ) {
            echo '<div style="overflow-x:auto;">
                <table class="widefat striped" style="margin-bottom: 15px;">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Recipient</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ( $email_log as $entry ) {
                $status_color = $entry['status'] === 'success' ? '#008a20' : '#d63638';
                $status_icon = $entry['status'] === 'success' ? 'yes-alt' : 'dismiss';
                
                echo '<tr>
                    <td>' . esc_html( date_i18n( 'M j, Y g:i A', $entry['timestamp'] ) ) . '</td>
                    <td>' . esc_html( $entry['recipient'] ) . '</td>
                    <td>' . esc_html( $entry['subject'] ) . '</td>
                    <td>' . esc_html( strtoupper( $entry['type'] ) ) . '</td>
                    <td><span style="color:' . esc_attr( $status_color ) . '; font-weight:600;"><span class="dashicons dashicons-' . esc_attr( $status_icon ) . '"></span> ' . esc_html( ucfirst( $entry['status'] ) ) . '</span></td>
                </tr>';
            }

            echo '</tbody>
                </table>
            </div>';

            echo '<button type="button" class="button button-secondary" id="cirrusly-clear-email-log">
                <span class="dashicons dashicons-trash"></span> Clear Log
            </button>';
        } else {
            echo '<p class="description">No emails have been sent yet.</p>';
        }

        echo '</div>
        </div>';

        // Email Configuration Cards
        foreach ( $email_types as $type => $config ) {
            $tier_label = '';
            $is_locked = false;

            if ( $config['tier'] === 'pro' && ! $is_pro ) {
                $tier_label = '<span class="cirrusly-pro-badge" style="margin-left:8px;">PRO</span>';
                $is_locked = true;
            } elseif ( $config['tier'] === 'pro_plus' && ! $is_pro_plus ) {
                $tier_label = '<span class="cirrusly-pro-badge" style="margin-left:8px;">PRO+</span>';
                $is_locked = true;
            }

            $recipient = isset( $email_settings[ $type . '_recipient' ] ) ? $email_settings[ $type . '_recipient' ] : '';
            $enabled = isset( $email_settings[ $type . '_enabled' ] ) ? (bool) $email_settings[ $type . '_enabled' ] : true;
            $disabled_attr = $is_locked ? 'disabled' : '';

            // Default recipient
            if ( empty( $recipient ) ) {
                if ( $type === 'bug_report' ) {
                    $recipient = 'help@cirruslyweather.com';
                } else {
                    $recipient = get_option( 'admin_email' );
                }
            }

            echo '<div class="cirrusly-settings-card">
                <div class="cirrusly-card-header"><h3>' . esc_html( $config['title'] ) . $tier_label . '</h3><span class="dashicons dashicons-email-alt"></span></div>
                <div class="cirrusly-card-body">';

            if ( $is_locked ) {
                echo '<p class="description" style="color:#d63638; margin-bottom:15px;"><span class="dashicons dashicons-lock"></span> ' . esc_html__( 'Upgrade to unlock this email notification', 'cirrusly-commerce' ) . '</p>';
            }

            echo '<p class="description" style="margin-bottom:15px;">' . esc_html( $config['description'] ) . '</p>
                
                <table class="form-table cirrusly-settings-table">
                    <tr>
                        <th scope="row"><label for="' . esc_attr( $type ) . '_enabled">Enable</label></th>
                        <td>
                            <label>
                                <input type="checkbox" name="cirrusly_email_settings[' . esc_attr( $type ) . '_enabled]" id="' . esc_attr( $type ) . '_enabled" value="1" ' . checked( $enabled, true, false ) . ' ' . esc_attr( $disabled_attr ) . '>
                                Send this notification
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="' . esc_attr( $type ) . '_recipient">Recipient</label></th>
                        <td>
                            <input type="email" name="cirrusly_email_settings[' . esc_attr( $type ) . '_recipient]" id="' . esc_attr( $type ) . '_recipient" value="' . esc_attr( $recipient ) . '" class="regular-text" ' . esc_attr( $disabled_attr ) . '>
                            <p class="description">Leave empty to use default recipient</p>
                        </td>
                    </tr>
                </table>
                
                <button type="button" class="button button-secondary cirrusly-send-test-email" data-email-type="' . esc_attr( $type ) . '" ' . esc_attr( $disabled_attr ) . '>
                    <span class="dashicons dashicons-email"></span> Send Test Email
                </button>
                
                </div>
            </div>';
        }

        echo '</div>';

        // JavaScript for email settings
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Send test email
            $('.cirrusly-send-test-email').on('click', function() {
                var $btn = $(this);
                var type = $btn.data('email-type');
                var recipient = $('#' + type + '_recipient').val();
                
                if (!recipient) {
                    alert('Please enter a recipient email address');
                    return;
                }
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Sending...');
                
                $.post(ajaxurl, {
                    action: 'cirrusly_send_test_email',
                    email_type: type,
                    recipient: recipient,
                    _nonce: '<?php echo wp_create_nonce( 'cirrusly_email_test' ); ?>'
                }, function(response) {
                    if (response.success) {
                        alert('Test email sent successfully to ' + recipient);
                    } else {
                        alert('Failed to send test email: ' + (response.data || 'Unknown error'));
                    }
                }).fail(function() {
                    alert('Server error. Please try again.');
                }).always(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-email"></span> Send Test Email');
                });
            });
            
            // Clear email log
            $('#cirrusly-clear-email-log').on('click', function() {
                if (!confirm('Are you sure you want to clear the email log? This cannot be undone.')) {
                    return;
                }
                
                var $btn = $(this);
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Clearing...');
                
                $.post(ajaxurl, {
                    action: 'cirrusly_clear_email_log',
                    _nonce: '<?php echo wp_create_nonce( 'cirrusly_email_log' ); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to clear log: ' + (response.data || 'Unknown error'));
                        $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear Log');
                    }
                }).fail(function() {
                    alert('Server error. Please try again.');
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span> Clear Log');
                });
            });
        });
        </script>
        <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spin {
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        </style>
        <?php
    }

    /**
     * AJAX handler: Send test email
     */
    public function handle_send_test_email() {
        if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'cirrusly_email_test', '_nonce', false ) ) {
            wp_send_json_error( __( 'Permission denied', 'cirrusly-commerce' ) );
        }

        $email_type = isset( $_POST['email_type'] ) ? sanitize_text_field( wp_unslash( $_POST['email_type'] ) ) : '';
        $recipient = isset( $_POST['recipient'] ) ? sanitize_email( wp_unslash( $_POST['recipient'] ) ) : '';

        if ( empty( $email_type ) || ! is_email( $recipient ) ) {
            wp_send_json_error( __( 'Invalid email type or recipient', 'cirrusly-commerce' ) );
        }

        // Ensure Mailer class is loaded
        if ( ! class_exists( 'Cirrusly_Commerce_Mailer' ) ) {
            require_once CIRRUSLY_COMMERCE_PATH . 'class-mailer.php';
        }

        // Prepare test data based on email type
        $test_data = array();
        switch ( $email_type ) {
            case 'bug_report':
                $test_data = array(
                    'user_email' => $recipient,
                    'subject' => 'Test Bug Report',
                    'message' => 'This is a test bug report to verify email delivery.',
                    'sys_info' => 'Test system information',
                );
                break;

            case 'weekly_profit':
                $test_data = array(
                    'data' => array(
                        'orders' => array(),
                        'totals' => array(
                            'count' => 5,
                            'revenue' => 1250.00,
                            'cogs' => 500.00,
                            'shipping' => 50.00,
                            'fees' => 37.50,
                            'net_profit' => 662.50,
                            'margin' => 53.0,
                        ),
                    ),
                );
                break;

            case 'gmc_import':
                $test_data = array(
                    'progress' => array(
                        'products_processed' => 150,
                        'current_batch' => 9,
                        'total_batches' => 9,
                    ),
                );
                break;

            case 'webhook_failures':
                $test_data = array(
                    'recent_logs' => array(
                        array(
                            'received_at' => date_i18n( 'M j, Y g:i A' ),
                            'event_type' => 'test.event',
                            'notes' => 'Test webhook failure for email testing',
                        ),
                    ),
                );
                break;

            case 'gmc_disapproval':
                $test_data = array(
                    'products' => array(
                        array(
                            'product_id' => 123,
                            'product_name' => 'Test Product',
                            'reason' => 'Test disapproval reason for email verification',
                            'detail' => 'Additional details about the disapproval',
                            'sku' => 'TEST-SKU-001',
                        ),
                    ),
                );
                break;

            case 'unmapped_products':
                $test_data = array(
                    'count' => 3,
                    'products' => array(
                        array(
                            'product_id' => 'test-123',
                            'product_name' => 'Test Product 1',
                            'sku' => 'TEST-SKU-1',
                        ),
                        array(
                            'product_id' => 'test-456',
                            'product_name' => 'Test Product 2',
                            'sku' => 'TEST-SKU-2',
                        ),
                        array(
                            'product_id' => 'test-789',
                            'product_name' => 'Test Product 3',
                            'sku' => 'TEST-SKU-3',
                        ),
                    ),
                );
                break;

            case 'weekly_analytics':
                $test_data = array(
                    'analytics_data' => array(
                        'total_clicks' => 1250,
                        'total_impressions' => 45000,
                        'total_conversions' => 35,
                        'conversion_value' => 2500.00,
                        'ctr' => 2.78,
                        'conversion_rate' => 2.80,
                        'top_products' => array(
                            array( 'name' => 'Product 1', 'clicks' => 300, 'conversions' => 10 ),
                            array( 'name' => 'Product 2', 'clicks' => 250, 'conversions' => 8 ),
                            array( 'name' => 'Product 3', 'clicks' => 200, 'conversions' => 7 ),
                        ),
                    ),
                );
                break;

            default:
                wp_send_json_error( __( 'Unknown email type', 'cirrusly-commerce' ) );
        }

        // Map email types to correct template names
        $template_map = array(
            'weekly_profit' => 'weekly-profit-report',
            'gmc_import' => 'gmc-import-complete',
        );
        
        $template_name = isset( $template_map[ $email_type ] ) ? $template_map[ $email_type ] : str_replace( '_', '-', $email_type );
        $subject = '[TEST] ' . ucwords( str_replace( '_', ' ', $email_type ) );
        $result = Cirrusly_Commerce_Mailer::send_from_template( $recipient, $template_name, $test_data, $subject );

        if ( $result ) {
            wp_send_json_success( __( 'Test email sent successfully', 'cirrusly-commerce' ) );
        } else {
            wp_send_json_error( __( 'Failed to send test email', 'cirrusly-commerce' ) );
        }
    }

    /**
     * AJAX handler: Clear email log
     */
    public function handle_clear_email_log() {
        if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'cirrusly_email_log', '_nonce', false ) ) {
            wp_send_json_error( __( 'Permission denied', 'cirrusly-commerce' ) );
        }

        delete_option( 'cirrusly_email_log' );
        wp_send_json_success( __( 'Email log cleared successfully', 'cirrusly-commerce' ) );
    }
}
