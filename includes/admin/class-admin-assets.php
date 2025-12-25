<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_Admin_Assets {

    /**
     * Constructor - Register admin footer hooks for quota bar
     */
    public function __construct() {
        add_action( 'admin_footer', array( $this, 'render_api_quota_bar' ) );
        add_action( 'wp_ajax_cirrusly_get_quota_status', array( $this, 'ajax_get_quota_status' ) );
        add_action( 'wp_ajax_cirrusly_toggle_quota_bar', array( $this, 'ajax_toggle_quota_bar' ) );
    }

    /**
     * Enqueue and localize admin styles, scripts, and UI helper code.
     */
    public function enqueue( $hook ) {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $is_plugin_page = strpos( $page, 'cirrusly-' ) !== false;
        $is_product_page = 'post.php' === $hook || 'post-new.php' === $hook;
        $is_dashboard = 'index.php' === $hook;

        // Always load base CSS on dashboard (for widget styling), plugin pages, and product pages
        if ( $is_dashboard ) {
            wp_register_style( 'cirrusly-admin-css', CIRRUSLY_COMMERCE_URL . 'assets/css/admin.css', array(), CIRRUSLY_COMMERCE_VERSION );
            wp_enqueue_style( 'cirrusly-admin-css' );
            return; // Dashboard only needs CSS, no JS
        }

        if ( ! $is_plugin_page && ! $is_product_page ) {
            return;
        }

        wp_enqueue_media(); 
        
        // Base Admin CSS
        wp_register_style( 'cirrusly-admin-css', CIRRUSLY_COMMERCE_URL . 'assets/css/admin.css', array(), CIRRUSLY_COMMERCE_VERSION );
        wp_enqueue_style( 'cirrusly-admin-css' );

        // Base Admin JS (Restored: Loads external file instead of inline)
        wp_register_script( 'cirrusly-admin-base-js', CIRRUSLY_COMMERCE_URL . 'assets/js/admin.js', array( 'jquery' ), CIRRUSLY_COMMERCE_VERSION, true );
        wp_enqueue_script( 'cirrusly-admin-base-js' );

        // Audit JS Logic
        if ( $page === 'cirrusly-audit' ) {
            wp_enqueue_script( 'cirrusly-audit-js', CIRRUSLY_COMMERCE_URL . 'assets/js/audit.js', array( 'jquery' ), CIRRUSLY_COMMERCE_VERSION, true );

            // Renamed localized object to match new prefix
            wp_localize_script( 'cirrusly-audit-js', 'cirrusly_audit_vars', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'cirrusly_audit_save' )
            ));
        }

        // Settings Page API Automation (Pro only)
        if ( $page === 'cirrusly-commerce' && class_exists( 'Cirrusly_Commerce_Core' ) && Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            wp_enqueue_script(
                'cirrusly-settings-api-automation',
                CIRRUSLY_COMMERCE_URL . 'assets/js/settings-api-automation.js',
                array( 'jquery' ),
                CIRRUSLY_COMMERCE_VERSION,
                true
            );

            // Localize script with AJAX URL, nonces, and i18n strings
            wp_localize_script(
                'cirrusly-settings-api-automation',
                'cirruslySettingsApiAutomation',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce_validate' => wp_create_nonce( 'cirrusly-admin-nonce' ),
                    'nonce_regenerate' => wp_create_nonce( 'cirrusly-admin-nonce' ),
                    'nonce_link' => wp_create_nonce( 'cirrusly-admin-nonce' ),
                    'nonce_generate' => wp_create_nonce( 'cirrusly-admin-nonce' ),
                    'i18n' => array(
                        'validating' => __( 'Validating...', 'cirrusly-commerce' ),
                        'regenerating' => __( 'Regenerating...', 'cirrusly-commerce' ),
                        'linking' => __( 'Linking...', 'cirrusly-commerce' ),
                        'generating' => __( 'Generating...', 'cirrusly-commerce' ),
                        'unknown_error' => __( 'Unknown error occurred', 'cirrusly-commerce' ),
                        'network_error' => __( 'Network error. Please try again.', 'cirrusly-commerce' ),
                        'regenerate_confirm' => __( 'Select a reason for regenerating your API key:', 'cirrusly-commerce' ),
                        'link_confirm' => __( 'Link your manual API key to this install? This enables automatic regeneration features.', 'cirrusly-commerce' ),
                        'validate_connection' => __( 'Validate Connection', 'cirrusly-commerce' ),
                        'valid_connection' => __( 'Valid Connection', 'cirrusly-commerce' ),
                        'just_now' => __( 'Just now', 'cirrusly-commerce' ),
                        'min_ago' => __( 'min ago', 'cirrusly-commerce' ),
                        'mins_ago' => __( 'mins ago', 'cirrusly-commerce' ),
                        'hour_ago' => __( 'hour ago', 'cirrusly-commerce' ),
                        'hours_ago' => __( 'hours ago', 'cirrusly-commerce' ),
                        'day_ago' => __( 'day ago', 'cirrusly-commerce' ),
                        'days_ago' => __( 'days ago', 'cirrusly-commerce' ),
                        'never' => __( 'Never', 'cirrusly-commerce' ),
                        'reason_compromise' => __( 'Compromise (key was exposed)', 'cirrusly-commerce' ),
                        'reason_testing' => __( 'Testing', 'cirrusly-commerce' ),
                        'reason_other' => __( 'Other', 'cirrusly-commerce' ),
                        'reason_prompt' => __( 'Enter 1, 2, or 3:', 'cirrusly-commerce' ),
                        'invalid_selection' => __( 'Invalid selection. Please try again.', 'cirrusly-commerce' ),
                        'regenerate_success' => __( 'API key regenerated successfully. Reloading page...', 'cirrusly-commerce' ),
                        'regenerate_failed' => __( 'Regeneration failed:', 'cirrusly-commerce' ),
                        'link_success' => __( 'Manual key linked successfully. Reloading page...', 'cirrusly-commerce' ),
                        'link_failed' => __( 'Linking failed:', 'cirrusly-commerce' ),
                        'generate_success' => __( 'Generated successfully!', 'cirrusly-commerce' ),
                    ),
                )
            );
        }

        // Pricing JS Logic
        if ( $is_product_page ) {
            wp_enqueue_script( 'cirrusly-pricing-js', CIRRUSLY_COMMERCE_URL . 'assets/js/pricing.js', array( 'jquery' ), CIRRUSLY_COMMERCE_VERSION, true );
            
            $config = get_option( 'cirrusly_shipping_config', array() );
            $defaults = array(
                'revenue_tiers_json' => json_encode(array(
                    array( 'min' => 0, 'max' => 10.00, 'charge' => 3.99 ),
                    array( 'min' => 10.01, 'max' => 20.00, 'charge' => 4.99 ),
                    array( 'min' => 60.00, 'max' => 99999, 'charge' => 0.00 ),
                )),
                'matrix_rules_json' => json_encode(array(
                    'economy'   => array( 'key'=>'economy', 'label' => 'Eco', 'cost_mult' => 1.0 ),
                    'standard'  => array( 'key'=>'standard', 'label' => 'Std', 'cost_mult' => 1.4 ),
                )),
                'class_costs_json' => json_encode(array('default' => 10.00)),
                'payment_pct' => 2.9, 'payment_flat' => 0.30,
                'profile_mode' => 'single', 'profile_split' => 100
            );
            $config = wp_parse_args( $config, $defaults );
            
            $js_config = array(
                'revenue_tiers' => json_decode( $config['revenue_tiers_json'] ),
                'matrix_rules'  => json_decode( $config['matrix_rules_json'] ),
                'classes'       => array(),
                'payment_pct'   => isset($config['payment_pct']) ? (float)$config['payment_pct'] : 2.9,
                'payment_flat'  => isset($config['payment_flat']) ? (float)$config['payment_flat'] : 0.30,
                'profile_mode'  => isset($config['profile_mode']) ? $config['profile_mode'] : 'single',
                'payment_pct_2' => isset($config['payment_pct_2']) ? (float)$config['payment_pct_2'] : 2.9,
                'payment_flat_2'=> isset($config['payment_flat_2']) ? (float)$config['payment_flat_2'] : 0.30,
                'profile_split' => isset($config['profile_split']) ? (float)$config['profile_split'] : 100,
            );
            
            $class_costs = json_decode( $config['class_costs_json'], true );
            if ( is_array( $class_costs ) ) {
                foreach( $class_costs as $slug => $cost ) {
                    $js_config['classes'][$slug] = array( 'cost' => (float)$cost, 'matrix' => true ); 
                }
            }
            
            $terms = get_terms( array( 'taxonomy' => 'product_shipping_class', 'hide_empty' => false ) );
            $id_map = array();
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $term ) $id_map[ $term->term_id ] = $term->slug;
            }

            // Renamed localized object
            wp_localize_script( 'cirrusly-pricing-js', 'cirrusly_pricing_vars', array( 'ship_config' => $js_config, 'id_map' => $id_map ));
        }
    }

    /**
     * Render the fixed API quota status bar on API-heavy admin pages
     */
    public function render_api_quota_bar() {
        // Only show on specific pages that make API calls
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        $api_pages = array( 'cirrusly-analytics', 'cirrusly-gmc', 'cirrusly-commerce' );
        
        if ( ! in_array( $page, $api_pages, true ) ) {
            return;
        }

        // Check if Pro (required for GMC Analytics API calls)
        if ( ! class_exists( 'Cirrusly_Commerce_Core' ) || ! Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            return;
        }

        // Check if user has dismissed the quota bar
        $is_dismissed = get_user_meta( get_current_user_id(), 'cirrusly_quota_bar_dismissed', true );

        // Get initial quota status
        $quota = class_exists( 'Cirrusly_Commerce_Google_API_Client' ) 
            ? Cirrusly_Commerce_Google_API_Client::get_quota_status() 
            : array( 'used' => 0, 'limit' => 500, 'remaining' => 500, 'percentage' => 0, 'by_action' => array(), 'reset_time' => ( new DateTime( 'tomorrow midnight', wp_timezone() ) )->getTimestamp() );

        $color = '#2271b1'; // Blue default
        if ( $quota['percentage'] >= 95 ) {
            $color = '#d63638'; // Red
        } elseif ( $quota['percentage'] >= 80 ) {
            $color = '#f56e28'; // Orange
        } elseif ( $quota['percentage'] >= 50 ) {
            $color = '#dba617'; // Yellow
        }

        $time_until_reset = human_time_diff( time(), $quota['reset_time'] );
        
        // If dismissed, show minimized restore link instead
        if ( $is_dismissed ) {
            ?>
            <div id="cirrusly-quota-restore" style="position: fixed; bottom: 10px; right: 20px; z-index: 99999; background: #fff; padding: 8px 12px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-left: 3px solid <?php echo esc_attr( $color ); ?>;">
                <button type="button" id="cirrusly-restore-quota-btn" style="background: none; border: none; color: #2271b1; cursor: pointer; font-weight: 600; font-size: 12px; display: flex; align-items: center; gap: 5px;">
                    <span class="dashicons dashicons-chart-line" style="line-height: inherit;"></span>
                    <span>API Usage: <strong style="color: <?php echo esc_attr( $color ); ?>;"><?php echo esc_html( $quota['percentage'] ); ?>%</strong></span>
                </button>
            </div>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#cirrusly-restore-quota-btn').on('click', function() {
                    $.post(ajaxurl, {
                        action: 'cirrusly_toggle_quota_bar',
                        dismissed: false,
                        nonce: '<?php echo esc_js( wp_create_nonce( 'cirrusly_quota_toggle' ) ); ?>'
                    }, function() {
                        location.reload();
                    });
                });
            });
            </script>
            <?php
            return;
        }
        ?>
        <div id="cirrusly-quota-bar" style="position: fixed; bottom: 0; left: 160px; right: 0; height: 50px; background: #fff; border-top: 2px solid <?php echo esc_attr( $color ); ?>; z-index: 99999; display: flex; align-items: center; padding: 0 20px; box-shadow: 0 -2px 8px rgba(0,0,0,0.1); font-size: 13px;">
            <div style="flex: 1; display: flex; align-items: center; gap: 15px;">
                <strong style="color: #1d2327;">API Quota:</strong>
                <div style="flex: 1; max-width: 300px; background: #f0f0f1; border-radius: 10px; height: 8px; overflow: hidden;">
                    <div id="cirrusly-quota-progress" style="width: <?php echo esc_attr( $quota['percentage'] ); ?>%; height: 100%; background: <?php echo esc_attr( $color ); ?>; transition: width 0.5s ease, background 0.5s ease;"></div>
                </div>
                <span id="cirrusly-quota-text" style="font-weight: 600; color: <?php echo esc_attr( $color ); ?>;">
                    <?php echo esc_html( $quota['used'] ); ?> / <?php echo esc_html( $quota['limit'] ); ?> used
                </span>
                <span style="color: #646970; font-size: 12px;">
                    • Resets in <span id="cirrusly-quota-reset"><?php echo esc_html( $time_until_reset ); ?></span>
                </span>
            </div>
            <button id="cirrusly-quota-toggle" type="button" style="background: none; border: none; color: #2271b1; cursor: pointer; padding: 5px 10px; font-weight: 600; font-size: 12px;">
                <span class="dashicons dashicons-arrow-up-alt2" style="line-height: inherit;"></span> Details
            </button>
            <button id="cirrusly-dismiss-quota" type="button" style="background: none; border: none; color: #646970; cursor: pointer; padding: 5px 10px; font-size: 12px; margin-left: 5px;" title="Dismiss quota bar">
                <span class="dashicons dashicons-dismiss" style="line-height: inherit;"></span>
            </button>
        </div>

        <!-- Expandable Details Panel -->
        <div id="cirrusly-quota-details" style="position: fixed; bottom: 50px; left: 160px; right: 0; background: #fff; border-top: 1px solid #dcdcde; z-index: 99998; max-height: 0; overflow: hidden; transition: max-height 0.3s ease;">
            <div style="padding: 15px 20px;">
                <h4 style="margin: 0 0 10px 0; font-size: 13px; font-weight: 600; color: #1d2327;">API Usage Breakdown</h4>
                <table id="cirrusly-quota-breakdown" style="width: 100%; max-width: 600px; font-size: 12px;">
                    <thead>
                        <tr style="border-bottom: 1px solid #dcdcde;">
                            <th style="text-align: left; padding: 5px 10px; color: #646970;">Action</th>
                            <th style="text-align: right; padding: 5px 10px; color: #646970;">Calls</th>
                            <th style="text-align: right; padding: 5px 10px; color: #646970;">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        if ( ! empty( $quota['by_action'] ) ) {
                            foreach ( $quota['by_action'] as $action => $count ) {
                                $pct = ( $quota['used'] > 0 ) ? round( ( $count / $quota['used'] ) * 100, 1 ) : 0;
                                ?>
                                <tr>
                                    <td style="padding: 5px 10px;"><?php echo esc_html( $action ); ?></td>
                                    <td style="text-align: right; padding: 5px 10px; font-weight: 600;"><?php echo esc_html( $count ); ?></td>
                                    <td style="text-align: right; padding: 5px 10px; color: #646970;"><?php echo esc_html( $pct ); ?>%</td>
                                </tr>
                                <?php
                            }
                        } else {
                            ?>
                            <tr>
                                <td colspan="3" style="padding: 10px; text-align: center; color: #646970;">No API calls yet today</td>
                            </tr>
                            <?php
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var isDetailsOpen = <?php echo ( $quota['percentage'] >= 80 ) ? 'true' : 'false'; ?>;
            var quotaPercentage = <?php echo esc_js( $quota['percentage'] ); ?>;
            var quotaToggleNonce = '<?php echo esc_js( wp_create_nonce( 'cirrusly_quota_toggle' ) ); ?>';
            var $bar = $('#cirrusly-quota-bar');
            var $details = $('#cirrusly-quota-details');
            var $toggle = $('#cirrusly-quota-toggle');
            var $progress = $('#cirrusly-quota-progress');
            var $text = $('#cirrusly-quota-text');
            var $reset = $('#cirrusly-quota-reset');
            var $breakdown = $('#cirrusly-quota-breakdown tbody');

            // Auto-expand if over 80%
            if (isDetailsOpen) {
                $details.css('max-height', '200px');
                $toggle.html('<span class="dashicons dashicons-arrow-down-alt2" style="line-height: inherit;"></span> Hide');
            }

            // Toggle details panel
            $toggle.on('click', function() {
                isDetailsOpen = !isDetailsOpen;
                if (isDetailsOpen) {
                    $details.css('max-height', '200px');
                    $toggle.html('<span class="dashicons dashicons-arrow-down-alt2" style="line-height: inherit;"></span> Hide');
                } else {
                    $details.css('max-height', '0');
                    $toggle.html('<span class="dashicons dashicons-arrow-up-alt2" style="line-height: inherit;"></span> Details');
                }
            });

            // Dismiss quota bar
            $('#cirrusly-dismiss-quota').on('click', function() {
                $.post(ajaxurl, {
                    action: 'cirrusly_toggle_quota_bar',
                    dismissed: true,
                    nonce: quotaToggleNonce
                }, function(response) {
                    if (response.success) {
                        $bar.fadeOut(300, function() {
                            $details.remove();
                            $bar.remove();
                            // Show minimized restore button
                            $('body').append('<div id="cirrusly-quota-restore" style="position: fixed; bottom: 10px; right: 20px; z-index: 99999; background: #fff; padding: 8px 12px; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-left: 3px solid ' + $bar.css('border-top-color') + ';"><button type="button" id="cirrusly-restore-quota-btn" style="background: none; border: none; color: #2271b1; cursor: pointer; font-weight: 600; font-size: 12px; display: flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-chart-line" style="line-height: inherit;"></span><span>API Usage: <strong style="color: ' + $text.css('color') + ';">' + quotaPercentage + '%</strong></span></button></div>');
                            
                            $('#cirrusly-restore-quota-btn').on('click', function() {
                                $.post(ajaxurl, {
                                    action: 'cirrusly_toggle_quota_bar',
                                    dismissed: false,
                                    nonce: quotaToggleNonce
                                }, function(response) {
                                    if (response.success) {
                                        location.reload();
                                    }
                                });
                            });
                        });
                    }
                });
            });

            // Live polling every 30 seconds
            function updateQuotaStatus() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: { action: 'cirrusly_get_quota_status' },
                    success: function(response) {
                        if (response.success && response.data) {
                            var quota = response.data;
                            
                            // Update stored percentage
                            quotaPercentage = quota.percentage;
                            
                            // Determine color
                            var color = '#2271b1';
                            if (quota.percentage >= 95) color = '#d63638';
                            else if (quota.percentage >= 80) color = '#f56e28';
                            else if (quota.percentage >= 50) color = '#dba617';

                            // Update progress bar
                            $progress.css({
                                'width': quota.percentage + '%',
                                'background': color
                            });

                            // Update text
                            $text.css('color', color).text(quota.used + ' / ' + quota.limit + ' used');
                            $reset.text(quota.reset_time_human);

                            // Update bar border
                            $bar.css('border-top-color', color);

                            // Update breakdown table
                            if (quota.by_action && Object.keys(quota.by_action).length > 0) {
                                var rows = '';
                                $.each(quota.by_action, function(action, count) {
                                    var pct = (quota.used > 0) ? ((count / quota.used) * 100).toFixed(1) : 0;
                                    rows += '<tr><td style="padding: 5px 10px;">' + action + '</td>';
                                    rows += '<td style="text-align: right; padding: 5px 10px; font-weight: 600;">' + count + '</td>';
                                    rows += '<td style="text-align: right; padding: 5px 10px; color: #646970;">' + pct + '%</td></tr>';
                                });
                                $breakdown.html(rows);
                            } else {
                                $breakdown.html('<tr><td colspan="3" style="padding: 10px; text-align: center; color: #646970;">No API calls yet today</td></tr>');
                            }

                            // Auto-expand if usage crosses 80%
                            if (quota.percentage >= 80 && !isDetailsOpen) {
                                $toggle.trigger('click');
                            }
                        }
                    }
                });
            }

            // Poll every 30 seconds
            setInterval(updateQuotaStatus, 30000);
        });
        </script>
        <?php
    }

    /**
     * AJAX handler for live quota status updates
     */
    public function ajax_get_quota_status() {
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            wp_send_json_error( array( 'message' => 'API client not available' ) );
        }

        $quota = Cirrusly_Commerce_Google_API_Client::get_quota_status();
        $quota['reset_time_human'] = human_time_diff( time(), $quota['reset_time'] );

        wp_send_json_success( $quota );
    }

    /**
     * AJAX handler for toggling quota bar visibility preference
     */
    public function ajax_toggle_quota_bar() {
        check_ajax_referer( 'cirrusly_quota_toggle', 'nonce' );
        
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied' ) );
        }

        $dismissed = isset( $_POST['dismissed'] ) && $_POST['dismissed'] === 'true';
        
        if ( $dismissed ) {
            update_user_meta( get_current_user_id(), 'cirrusly_quota_bar_dismissed', true );
        } else {
            delete_user_meta( get_current_user_id(), 'cirrusly_quota_bar_dismissed' );
        }

        wp_send_json_success( array( 'dismissed' => $dismissed ) );
    }
}