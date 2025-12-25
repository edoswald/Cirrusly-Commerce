<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cirrusly_Commerce_GMC_UI {

    /****
     * Initialize the Cirrusly Google Merchant Center admin UI by registering WordPress hooks and filters.
     *
     * Registers handlers for product list columns and rendering, product edit meta box UI, quick-edit controls,
     * admin notices related to blocked saves, and enqueues admin assets for the GMC admin screens.
     */
    public function __construct() {
        add_filter( 'manage_edit-product_columns', array( $this, 'add_gmc_admin_columns' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'render_gmc_admin_columns' ), 10, 2 );
        add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'render_gmc_product_settings' ) );
        add_action( 'quick_edit_custom_box', array( $this, 'render_quick_edit_box' ), 10, 2 );
        add_action( 'admin_notices', array( $this, 'render_blocked_save_notice' ) );
        // New hook for enqueuing assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    /**
     * Generate Product Studio action button or upsell based on issue type and user tier.
     *
     * Returns HTML for:
     * - Pro Plus users: "Fix with AI" button that triggers Product Studio
     * - Pro users: Upgrade prompt showing how Product Studio solves this issue
     * - Free users: Empty string (primary upsell is Pro, not Pro Plus)
     *
     * @param array $issues Array of issue objects with 'type' and 'msg' keys
     * @param int   $product_id The product ID
     * @return string HTML for Product Studio action or upsell
     */
    private function get_product_studio_action( $issues, $product_id ) {
        $is_pro = Cirrusly_Commerce_Core::cirrusly_is_pro();
        $is_pro_plus = Cirrusly_Commerce_Core::cirrusly_is_pro_plus();
        
        // Don't show Pro Plus upsells to Free users (Pro is the primary upsell)
        if ( ! $is_pro ) {
            return '';
        }
        
        // Determine if this issue is AI-fixable
        $ai_fixable = false;
        $ai_action = '';
        $ai_label = '';
        
        foreach ( $issues as $issue ) {
            $msg_lower = strtolower( $issue['msg'] );
            
            // Missing description/content issues
            if ( strpos( $msg_lower, 'missing' ) !== false && 
                 ( strpos( $msg_lower, 'description' ) !== false || strpos( $msg_lower, 'content' ) !== false ) ) {
                $ai_fixable = true;
                $ai_action = 'generate_description';
                $ai_label = 'Generate Description';
                break;
            }
            
            // Title issues (restricted terms, optimization)
            if ( strpos( $msg_lower, 'title' ) !== false || strpos( $msg_lower, 'restricted term' ) !== false ) {
                $ai_fixable = true;
                $ai_action = 'optimize_title';
                $ai_label = 'Optimize Title';
                break;
            }
            
            // Missing image issues
            // Note: Image generation temporarily disabled - feature coming in future update
            if ( strpos( $msg_lower, 'missing image' ) !== false ) {
                // $ai_fixable = true;
                // $ai_action = 'generate_image';
                // $ai_label = 'Generate Image';
                break; // Don't show AI fix button for missing images (yet)
            }
            
            // Low image quality issues -> generate/improve the image
            if ( strpos( $msg_lower, 'image' ) !== false && strpos( $msg_lower, 'quality' ) !== false ) {
                $ai_fixable = true;
                $ai_action = 'generate_image';
                $ai_label = 'Enhance Images';
                break;
            }
            
            // Missing alt text issues -> enhance image metadata
            if ( strpos( $msg_lower, 'alt text' ) !== false || strpos( $msg_lower, 'alt attribute' ) !== false ) {
                $ai_fixable = true;
                $ai_action = 'enhance_images';
                $ai_label = 'Generate Alt Text';
                break;
            }
        }
        
        if ( ! $ai_fixable ) {
            return '';
        }
        
        // Pro Plus: Show functional "Fix with AI" button
        if ( $is_pro_plus ) {
            // Special styling for image generation
            $button_icon = ( $ai_action === 'generate_image' ) ? 'dashicons-camera' : 'dashicons-admin-tools';
            
            return sprintf(
                ' <button type="button" class="cirrusly-ai-fix-btn" data-product-id="%d" data-action="%s"><span class="dashicons %s"></span> %s</button>',
                esc_attr( $product_id ),
                esc_attr( $ai_action ),
                esc_attr( $button_icon ),
                esc_html( $ai_label )
            );
        }
        
        // Pro (not Plus): Show contextual upgrade prompt
        $upgrade_url = function_exists( 'cirrusly_fs' ) ? cirrusly_fs()->get_upgrade_url() : '#';
        return sprintf(
            ' <a href="%s" class="button button-small" style="background: #f0e5ff; color: #764ba2; border: 1px solid #764ba2; font-weight: 600;" title="Upgrade to Pro Plus to use AI-powered fixes"><span class="dashicons dashicons-star-filled" style="font-size: 12px; vertical-align: middle;"></span> AI Fix Available</a>',
            esc_url( $upgrade_url )
        );
    }
    
    /**
     * Restrict script enqueuing to the Cirrusly GMC admin page.
     *
     * Checks the current admin screen and exits immediately if the screen is not
     * the Cirrusly Google Merchant Center (cirrusly-gmc) admin page.
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        if ( ! $screen || false === strpos( $screen->id, '_page_cirrusly-gmc' ) ) {
            return;
        }

        // Tab-specific script loading
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'scan';

        if ( 'promotions' === $tab ) {
            $nonce_list   = wp_create_nonce( 'cirrusly_promo_api_list' );
            $nonce_submit = wp_create_nonce( 'cirrusly_promo_api_submit' );

            wp_enqueue_script(
                'cirrusly-admin-promotions',
                CIRRUSLY_COMMERCE_URL . 'assets/js/admin-promotions.js',
                array( 'jquery', 'cirrusly-admin-base-js' ),
                '1.0.0',
                true
            );

            wp_localize_script( 'cirrusly-admin-promotions', 'cirrusly_promo_data', array(
                'ajaxurl'      => admin_url( 'admin-ajax.php' ),
                'nonce_list'   => $nonce_list,
                'nonce_submit' => $nonce_submit,
            ) );
        } elseif ( 'sync' === $tab && Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            // Enqueue sync status script for Pro users
            wp_enqueue_script(
                'cirrusly-gmc-sync-status',
                CIRRUSLY_COMMERCE_URL . 'assets/js/admin-gmc-sync-status.js',
                array( 'jquery' ),
                CIRRUSLY_COMMERCE_VERSION,
                true
            );

            wp_localize_script( 'cirrusly-gmc-sync-status', 'cirruslySyncStatus', array(
                'ajax_url'       => admin_url( 'admin-ajax.php' ),
                'refresh_nonce'  => wp_create_nonce( 'cirrusly_sync_refresh' ),
            ) );
        } elseif ( 'scan' === $tab && Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            // Enqueue AI Fix handler for Pro+ users on scan tab
            wp_enqueue_script(
                'cirrusly-gmc-scan',
                CIRRUSLY_COMMERCE_URL . 'assets/js/admin-gmc-scan.js',
                array( 'jquery' ),
                CIRRUSLY_COMMERCE_VERSION,
                true
            );
            
            wp_localize_script( 'cirrusly-gmc-scan', 'cirruslyGMCScan', array(
                'ajax_url'            => admin_url( 'admin-ajax.php' ),
                'preview_nonce'       => wp_create_nonce( 'cirrusly_ai_preview' ),
                'apply_nonce'         => wp_create_nonce( 'cirrusly_ai_apply' ),
                'product_studio_nonce' => wp_create_nonce( 'cirrusly_product_studio' ),
            ) );
        }
    }

    /**
     * Render the Google Merchant Center hub page with tabbed navigation.
     *
     * Displays the hub header, a three-tab navigation (Health Check, Promotion Manager, Site Content),
     * and delegates rendering to the corresponding view for the currently selected tab.
     */
    public function render_gmc_hub_page() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tab = isset( $_GET['tab'] ) ? sanitize_text_field( wp_unslash( $_GET['tab'] ) ) : 'scan';
        
        // Get quick metrics for intro section (using canonical 'cirrusly_gmc_scan_data' option)
        $scan_data = get_option( 'cirrusly_gmc_scan_data', array() );
        $critical_count = 0;
        $warning_count = 0;
        
        if ( ! empty( $scan_data['results'] ) ) {
            foreach ( $scan_data['results'] as $r ) {
                if ( ! empty( $r['issues'] ) ) {
                    foreach ( $r['issues'] as $issue ) {
                        if ( 'critical' === $issue['type'] ) {
                            $critical_count++;
                        } else {
                            $warning_count++;
                        }
                    }
                }
            }
        }
        
        $promo_stats = get_transient( 'cirrusly_active_promos_stats' );
        $active_promos = is_array( $promo_stats ) ? count( $promo_stats ) : 0;
        
        $is_pro = Cirrusly_Commerce_Core::cirrusly_is_pro();
        ?>
        <div class="wrap">
            <?php Cirrusly_Commerce_Core::render_global_header( 'Compliance Hub' ); ?>
            
            <div class="cirrusly-kpi-grid" style="margin-bottom: 20px;">
                <div class="cirrusly-kpi-card" style="--card-accent: <?php echo esc_attr( $critical_count > 0 ? '#d63638' : '#00a32a' ); ?>; --card-accent-end: <?php echo esc_attr( $critical_count > 0 ? '#f56565' : '#46b450' ); ?>;">
                    <span class="dashicons dashicons-warning" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: <?php echo esc_attr( $critical_count > 0 ? 'rgba(214, 54, 56, 0.15)' : 'rgba(0, 163, 42, 0.15)' ); ?>;"></span>
                    <span class="cirrusly-kpi-label">Critical Issues</span>
                    <span class="cirrusly-kpi-value" style="color: <?php echo esc_attr( $critical_count > 0 ? '#d63638' : '#00a32a' ); ?>;"><?php echo esc_html( number_format( $critical_count ) ); ?></span>
                    <small style="font-size: 11px; color: #646970; margin-top: 5px;">Requires Immediate Action</small>
                </div>
                
                <div class="cirrusly-kpi-card" style="--card-accent: <?php echo esc_attr( $warning_count > 0 ? '#f0ad4e' : '#00a32a' ); ?>;">
                    <span class="dashicons dashicons-flag" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: <?php echo esc_attr( $warning_count > 0 ? 'rgba(240, 173, 78, 0.15)' : 'rgba(0, 163, 42, 0.15)' ); ?>;"></span>
                    <span class="cirrusly-kpi-label">Warnings</span>
                    <span class="cirrusly-kpi-value" style="color: <?php echo esc_attr( $warning_count > 0 ? '#f0ad4e' : '#00a32a' ); ?>;"><?php echo esc_html( number_format( $warning_count ) ); ?></span>
                    <small style="font-size: 11px; color: #646970; margin-top: 5px;">Potential Policy Issues</small>
                </div>
                
                <div class="cirrusly-kpi-card" style="--card-accent: #2271b1;">
                    <span class="dashicons dashicons-megaphone" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(34, 113, 177, 0.15);"></span>
                    <span class="cirrusly-kpi-label">Active Promotions</span>
                    <span class="cirrusly-kpi-value" style="color: #2271b1;"><?php echo esc_html( number_format( $active_promos ) ); ?></span>
                    <small style="font-size: 11px; color: #646970; margin-top: 5px;">Google Shopping Ads</small>
                </div>
                
                <div class="cirrusly-kpi-card" style="--card-accent: #00a32a;">
                    <span class="dashicons dashicons-yes-alt" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(0, 163, 42, 0.15);"></span>
                    <span class="cirrusly-kpi-label">Compliance Score</span>
                    <span class="cirrusly-kpi-value" style="color: #00a32a;"><?php $total = $critical_count + $warning_count; $score = $total > 0 ? max(0, 100 - ($critical_count * 10) - ($warning_count * 2)) : 100; echo esc_html( number_format( $score ) ); ?>%</span>
                    <small style="font-size: 11px; color: #646970; margin-top: 5px;">GMC Health Status</small>
                </div>
            </div>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=cirrusly-gmc&tab=scan" class="nav-tab <?php echo 'scan'===$tab? 'nav-tab-active' : ''; ?>">Health Check</a>
                <a href="?page=cirrusly-gmc&tab=promotions" class="nav-tab <?php echo 'promotions'===$tab? 'nav-tab-active' : ''; ?>">Promotion Manager</a>
                <a href="?page=cirrusly-gmc&tab=sync" class="nav-tab <?php echo 'sync'===$tab? 'nav-tab-active' : ''; ?>">Sync Status</a>
                <a href="?page=cirrusly-gmc&tab=content" class="nav-tab <?php echo 'content'===$tab? 'nav-tab-active' : ''; ?>">Site Content</a>
            </nav>
            <br>
            <?php
            if ( 'promotions' === $tab ) {
                 $this->render_promotions_view();
            } elseif ( 'sync' === $tab ) {
                 $this->render_sync_status_tab();
            } elseif ( 'content' === $tab ) {
                 $this->render_content_scan_view();
            } else {
                 $this->render_scan_view();
            }
            ?>
        </div>
        <?php
    }


   /**
     * Renders the Health Check admin UI for scanning the product catalog and managing scan-related automation rules.
     *
     * Displays a manual scan control, runs and persists a scan when the scan form is submitted, migrates legacy scan data if present, and shows scan results with per-product actions. Also renders the Automation & Workflow Rules panel (PRO-gated) that exposes settings for blocking saves on critical issues and auto-stripping banned words.
     */
    private function render_scan_view() {
        $is_pro = Cirrusly_Commerce_Core::cirrusly_is_pro();
        $pro_class = $is_pro ? '' : 'cirrusly-pro-feature';
        $disabled_attr = $is_pro ? '' : 'disabled';
        
        $scan_cfg = get_option('cirrusly_scan_config', array());
        $block_save = isset($scan_cfg['block_on_critical']) ? 'checked' : '';
        $auto_strip = isset($scan_cfg['auto_strip_banned']) ? 'checked' : '';

        // Modern info banner
        echo '<div class="cirrusly-chart-card" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: #646970;">
                <span class="dashicons dashicons-info-outline" style="color: #2271b1; font-size: 18px;"></span>
                <div>
                    <strong style="color: #2c3338;">Health Check:</strong> Scan your WooCommerce catalog for GMC policy violations, missing product identifiers, and restricted terms before submitting your feed to Google.
                </div>
            </div>
        </div>';
        
        // Modern scan button card
        echo '<div class="cirrusly-chart-card" style="margin-bottom: 20px;">
            <form method="post" style="display: flex; align-items: center; gap: 10px;">';
        wp_nonce_field( 'cirrusly_gmc_scan', 'cirrusly_gmc_scan_nonce' );
        echo '<input type="hidden" name="run_gmc_scan" value="1">
                <span class="dashicons dashicons-search" style="color: #2271b1; font-size: 20px;"></span>
                <button type="submit" name="run_scan" class="cirrusly-btn-success" style="padding: 10px 20px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-yes" style="line-height: inherit;"></span> Run Health Scan</button>
                <span style="color: #646970; font-size: 13px; margin-left: 10px;">Checks all published products for compliance issues</span>
            </form>
        </div>';

        if ( isset( $_POST['run_gmc_scan'] ) && check_admin_referer( 'cirrusly_gmc_scan', 'cirrusly_gmc_scan_nonce' ) ) {
            // Unwrap new result structure
            $scan_result = Cirrusly_Commerce_GMC::run_gmc_scan_logic();
            $results     = isset( $scan_result['results'] ) ? $scan_result['results'] : array();
            
            update_option( 'cirrusly_gmc_scan_data', array( 'timestamp' => current_time( 'timestamp' ), 'results' => $results ), false );
            
            // Trigger action for Pro features (email alerts, etc.)
            do_action( 'cirrusly_gmc_scan_complete', $scan_result );
            
            echo '<div class="notice notice-success inline"><p>Scan Completed.</p></div>';
        }
        
        // MIGRATION: Check for old scan data and migrate
        $scan_data = get_option( 'cirrusly_gmc_scan_data' );
        if ( false === $scan_data ) {
            $old_scan_data = get_option( 'woo_gmc_scan_data' );
            if ( false !== $old_scan_data ) {
                update_option( 'cirrusly_gmc_scan_data', $old_scan_data );
                delete_option( 'woo_gmc_scan_data' );
                $scan_data = $old_scan_data;
            }
        }

        if ( ! empty( $scan_data ) && !empty($scan_data['results']) ) {
            echo '<div class="cirrusly-chart-card"><table class="wp-list-table widefat fixed striped" style="border: 0; box-shadow: none;"><thead><tr><th>Product</th><th>Issues</th><th>Action</th></tr></thead><tbody>';
            foreach($scan_data['results'] as $r) {
                $p=wc_get_product($r['product_id']); if(!$p) continue;
                $issues = ''; 
                foreach($r['issues'] as $i) {
                    if ($i['type'] === 'critical') {
                        $border_color = '#d63638';
                        $text_color = '#d63638';
                        $bg_color = 'rgba(214, 54, 56, 0.08)';
                    } else {
                        $border_color = '#996800';
                        $text_color = '#996800';
                        $bg_color = 'rgba(219, 166, 23, 0.08)';
                    }
                    $tooltip = isset($i['reason']) ? $i['reason'] : $i['msg'];
                    
                    // Clean up display: remove [image link] text for better UX
                    // The actual image is already accessible via the "Enhance Images" button
                    $display_msg = $i['msg'];
                    if (!empty($i['image_url']) && strpos($display_msg, '[image link]') !== false) {
                        // Remove [image link] text - users will use "Enhance Images" button to target that specific image
                        $display_msg = str_replace('[image link]', '', $display_msg);
                        $display_msg = trim($display_msg);
                        $display_msg = esc_html($display_msg);
                    } else {
                        $display_msg = esc_html($display_msg);
                    }
                    
                    $issues .= '<span class="gmc-badge cirrusly-has-tooltip" style="background:'.esc_attr($bg_color).'; color:'.esc_attr($text_color).'; border: 1px solid '.esc_attr($border_color).';" data-tooltip="'.esc_attr($tooltip).'" data-image-url="'.esc_attr(!empty($i['image_url']) ? $i['image_url'] : '').'" data-image-path="'.esc_attr(!empty($i['image_path']) ? $i['image_path'] : '').'">'. $display_msg .'</span> ';
                }
                
                $actions = '<a href="'.esc_url(get_edit_post_link($p->get_id())).'" class="cirrusly-btn"><span class="dashicons dashicons-edit"></span>Edit</a> ';
                if ( strpos( $issues, 'Missing GTIN' ) !== false ) {
                    $url = wp_nonce_url( admin_url( 'admin-post.php?action=cirrusly_mark_custom&pid=' . $p->get_id() ), 'cirrusly_mark_custom_' . $p->get_id() );
                    $actions .= '<a href="'.esc_url($url).'" class="cirrusly-btn-secondary"><span class="dashicons dashicons-tag"></span>Mark as Custom</a>';
                }
                
                // Add Product Studio upsell/action for AI-fixable issues
                $actions .= $this->get_product_studio_action( $r['issues'], $p->get_id() );

                echo '<tr><td><a href="'.esc_url(get_edit_post_link($p->get_id())).'">'.esc_html($p->get_name()).'</a></td><td>'.wp_kses_post($issues).'</td><td>'.wp_kses_post($actions).'</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        
        // AI Preview Modal (Pro Plus only)
        if ( Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            $this->render_ai_preview_modal();
        }

        // PRO: Automation & Workflow Rules
        echo '<div class="cirrusly-chart-card '.esc_attr($pro_class).'" style="margin-top: 20px; position: relative;">';
            if(!$is_pro) echo '<div class="cirrusly-pro-overlay"><a href="'.esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ).'" class="cirrusly-upgrade-btn"><span class="dashicons dashicons-lock cirrusly-lock-icon"></span> Upgrade to Automate</a></div>';
            
            echo '<div class="cirrusly-chart-header" style="background: linear-gradient(135deg, #f0f6fc 0%, #e0ebf5 100%); padding: 15px 20px; margin: -25px -25px 20px -25px; border-bottom: 3px solid #2271b1; border-radius: 12px 12px 0 0;">
                <h3 style="margin: 0; color: #2c3338; font-size: 16px; font-weight: 600;">Automation & Workflow Rules <span class="cirrusly-pro-badge">PRO</span></h3>
            </div>';
            
            echo '<form method="post" action="options.php">';
            settings_fields('cirrusly_general_group'); 
            
            echo '<div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 15px;">
                <label style="display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 10px 15px; border-radius: 6px; border: 1px solid #c3c4c7; cursor: pointer; transition: all 0.3s;">
                    <input type="checkbox" name="cirrusly_scan_config[block_on_critical]" value="yes" '.esc_attr($block_save).' '.esc_attr($disabled_attr).' style="margin: 0;">
                    <span class="dashicons dashicons-shield" style="color: #d63638;"></span>
                    <strong>Block Save on Critical Error</strong>
                    <small style="color: #646970; margin-left: 5px;">Prevent publishing products with critical GMC issues</small>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; background: #f8f9fa; padding: 10px 15px; border-radius: 6px; border: 1px solid #c3c4c7; cursor: pointer; transition: all 0.3s;">
                    <input type="checkbox" name="cirrusly_scan_config[auto_strip_banned]" value="yes" '.esc_attr($auto_strip).' '.esc_attr($disabled_attr).' style="margin: 0;">
                    <span class="dashicons dashicons-filter" style="color: #2271b1;"></span>
                    <strong>Auto-strip Banned Words</strong>
                    <small style="color: #646970; margin-left: 5px;">Automatically remove restricted terms from product titles</small>
                </label>
            </div>';
            
            // This hook injects the Automated Discounts UI
            do_action( 'cirrusly_commerce_scan_settings_ui' );

            echo '<button type="submit" class="cirrusly-btn-success" style="padding: 8px 16px; border-radius: 6px; font-weight: 600; margin-top: 10px;" '.esc_attr($disabled_attr).'><span class="dashicons dashicons-saved" style="line-height: inherit;"></span> Save Rules</button>
            </form>';
        echo '</div>';
    }

     /**
     * Render the promotions management UI and handle local promotion assignment actions.
     *
     * Outputs the Live Google Promotions admin interface (promotions table, promotion generator,
     * and local product assignment list). When a POST with `gmc_promo_bulk_action` and a valid
     * `cirrusly_promo_bulk` nonce is present, performs bulk updates or removals of the
     * `_gmc_promotion_id` post meta for selected products and clears the promotions transient cache.
     *
     * Additional behaviors:
     * - Loads cached promotion statistics from the `cirrusly_active_promos_stats` transient and
     * regenerates it from postmeta when absent.
     * - Supports filtering by a single promotion ID via the `view_promo` query parameter and
     * displays a paginated product list when filtered.
     * - Outputs markup that is PRO-gated for certain actions (UI elements may be disabled when not PRO).
     *
     * @return void
     */
    private function render_promotions_view() {
        $is_pro = Cirrusly_Commerce_Core::cirrusly_is_pro();
        $pro_class = $is_pro ? '' : 'cirrusly-pro-feature';
        $disabled_attr = $is_pro ? '' : 'disabled';
        
        echo '<div class="cirrusly-settings-card '.esc_attr($pro_class).'" style="margin-bottom:20px;">';
        if(!$is_pro) echo '<div class="cirrusly-pro-overlay"><a href="'.esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ).'" class="cirrusly-upgrade-btn"><span class="dashicons dashicons-lock cirrusly-lock-icon"></span> Unlock Live Feed</a></div>';
        
        echo '<div class="cirrusly-card-header" style="background:#f8f9fa; border-bottom:1px solid #ddd; padding:15px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0;">Live Google Promotions <span class="cirrusly-pro-badge">PRO</span></h3>
                <button type="button" class="cirrusly-btn-secondary" id="cirrusly_load_promos" '.esc_attr($disabled_attr).'><span class="dashicons dashicons-update"></span> Sync from Google</button>
              </div>';
        
        echo '<div class="cirrusly-card-body" style="padding:0;">
                <table class="wp-list-table widefat fixed striped" id="cirrusly-gmc-promos-table" style="border:0; box-shadow:none;">
                    <thead><tr><th>ID</th><th>Title</th><th>Effective Dates</th><th>Status</th><th>Type</th><th>Actions</th></tr></thead>
                    <tbody><tr class="cirrusly-empty-row"><td colspan="6" style="padding:20px; text-align:center; color:#666;">Loading active promotions...</td></tr></tbody>
                </table>
              </div>';
        echo '</div>';
        
        echo '<div class="cirrusly-manual-helper"><h4>Promotion Feed Generator</h4><p>Create or update a promotion entry for Google Merchant Center. Fill in the details, generate the code, and paste it into your Google Sheet feed.</p></div>';
        ?>
        <div class="cirrusly-promo-generator" id="cirrusly_promo_form_container">
            <h3 style="margin-top:0;" id="cirrusly_form_title">Create Promotion Entry</h3>
            <div class="cirrusly-promo-grid">
                <div>
                    <label for="pg_id">Promotion ID <span class="dashicons dashicons-info" title="Unique ID"></span></label>
                    <input type="text" id="pg_id" placeholder="SUMMER_SALE">
                    <label for="pg_title">Long Title <span class="dashicons dashicons-info" title="Customer-facing title"></span></label>
                    <input type="text" id="pg_title" placeholder="20% Off Summer Items">
                    <label for="pg_dates">Dates <span class="dashicons dashicons-info" title="Format: YYYY-MM-DD/YYYY-MM-DD"></span></label>
                    <input type="text" id="pg_dates" placeholder="2025-06-01/2025-06-30">
                </div>
                <div>
                    <label for="pg_app">Product Applicability</label>
                    <select id="pg_app"><option value="SPECIFIC_PRODUCTS">Specific Products</option><option value="ALL_PRODUCTS">All Products</option></select>
                    <label for="pg_type">Offer Type</label>
                    <select id="pg_type"><option value="NO_CODE">No Code Needed</option><option value="GENERIC_CODE">Generic Code</option></select>
                    <label for="pg_code">Generic Code</label>
                    <input type="text" id="pg_code" placeholder="SAVE20">
                </div>
            </div>
            
            <div style="margin-top:15px; display:flex; justify-content:space-between; align-items:center;">
                <button type="button" class="cirrusly-btn-success" id="pg_generate"><span class="dashicons dashicons-code-standards"></span>Generate Code</button>
                
                <div class="<?php echo esc_attr($pro_class); ?>" style="display:flex; align-items:center; gap:10px;">
                    <span class="description" style="font-style:italic; font-size:12px;">
                        Directly push this promotion to your linked Merchant Center account.
                    </span>
                    <div style="position:relative;">
                        <?php if(!$is_pro): ?>
                        <div class="cirrusly-pro-overlay">
                            <a href="<?php echo esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ); ?>" class="cirrusly-upgrade-btn">
                               <span class="dashicons dashicons-lock cirrusly-lock-icon"></span> Upgrade
                            </a>
                        </div>
                        <?php endif; ?>
                        <button type="button" class="cirrusly-btn-secondary" id="pg_api_submit" <?php echo esc_attr($disabled_attr); ?>>
                            <span class="dashicons dashicons-cloud-upload"></span> One-Click Submit to Google
                        </button>
                        <span class="cirrusly-pro-badge">PRO</span>
                    </div>
                </div>
            </div>

            <div id="pg_result_area" style="display:none; margin-top:15px;">
                <span class="cirrusly-copy-hint">Copy and paste this line into your Google Sheet:</span>
                <div id="pg_output" class="cirrusly-generated-code"></div>
            </div>
        </div>
        <?php
        global $wpdb;
        
        if ( isset( $_POST['gmc_promo_bulk_action'] ) && isset( $_POST['gmc_promo_products'] ) && check_admin_referer( 'cirrusly_promo_bulk', 'cirrusly_promo_nonce' ) ) {
            $new_promo_id = isset($_POST['gmc_new_promo_id']) ? sanitize_text_field( wp_unslash( $_POST['gmc_new_promo_id'] ) ) : '';
            $action = sanitize_text_field( wp_unslash( $_POST['gmc_promo_bulk_action'] ) );
            
            // Fix: Unslash and map for safety
            $promo_products_raw = wp_unslash( $_POST['gmc_promo_products'] );
            $promo_products = is_array($promo_products_raw) ? array_map('intval', $promo_products_raw) : array();

            if ( ! empty( $promo_products ) ) {
                $count = 0;
                foreach ( $promo_products as $pid ) {
                    if ( 'update' === $action ) update_post_meta( $pid, '_gmc_promotion_id', $new_promo_id );
                    elseif ( 'remove' === $action ) delete_post_meta( $pid, '_gmc_promotion_id' );
                    $count++;
                }
                delete_transient( 'cirrusly_active_promos_stats' );
                echo '<div class="notice notice-success inline"><p>Success! Updated ' . esc_html($count) . ' products.</p></div>';
            }
        }

        $promo_stats = get_transient( 'cirrusly_active_promos_stats' );
        if ( false === $promo_stats ) {
            // Note: This direct query is used for aggregation which is not supported by WP_Query. 
            // It is strictly wrapped in get_transient to ensure caching compliance.
            $promo_stats = $wpdb->get_results( "SELECT meta_value as promo_id, count(post_id) as count FROM {$wpdb->postmeta} WHERE meta_key = '_gmc_promotion_id' AND meta_value != '' GROUP BY meta_value ORDER BY count DESC" );
            set_transient( 'cirrusly_active_promos_stats', $promo_stats, 1 * HOUR_IN_SECONDS );
        }
        
        $filter_promo = isset( $_GET['view_promo'] ) ? sanitize_text_field( wp_unslash( $_GET['view_promo'] ) ) : '';

        echo '<br><hr><h3>Active Promotion Tags</h3><p class="description">Products in your WooCommerce store tagged with a Promotion ID for Google promotions.</p>';
        if(empty($promo_stats)) echo '<p>No promotions assigned locally.</p>';
        else {
            echo '<table class="wp-list-table widefat fixed striped" style="max-width:600px;"><thead><tr><th>ID</th><th>Products Assigned</th><th>Action</th></tr></thead><tbody>';
            foreach($promo_stats as $stat) {
                echo '<tr><td><strong>'.esc_html($stat->promo_id).'</strong></td><td>'.esc_html($stat->count).'</td><td><a href="?page=cirrusly-gmc&tab=promotions&view_promo='.urlencode($stat->promo_id).'" class="button button-small">View Products</a></td></tr>';
            }
            echo '</tbody></table>';
        }

        if ( $filter_promo ) {
            // FIXED: Added Pagination for better performance with large product lists
            $paged = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
            $per_page = 20;

            $args = array( 
                'post_type'      => 'product', 
                'posts_per_page' => $per_page, 
                'paged'          => $paged,
                'meta_key'       => '_gmc_promotion_id', 
                'meta_value'     => $filter_promo 
            );

            $query = new WP_Query( $args );
            $products = $query->posts;
            $total_pages = $query->max_num_pages;

            echo '<hr><h3>Managing: '.esc_html($filter_promo).'</h3>';
            echo '<form method="post">';
            wp_nonce_field( 'cirrusly_promo_bulk', 'cirrusly_promo_nonce' );
            echo '<div style="background:#e5e5e5; padding:10px; margin-bottom:10px;">With Selected: <input type="text" name="gmc_new_promo_id" placeholder="New ID"> <button type="submit" name="gmc_promo_bulk_action" value="update" class="button">Move</button> <button type="submit" name="gmc_promo_bulk_action" value="remove" class="button">Remove</button></div>';
            
            // Pagination Top
            if ( $total_pages > 1 ) {
                $page_links = paginate_links( array(
                    'base'    => add_query_arg( 'paged', '%#%' ),
                    'format'  => '',
                    'current' => $paged,
                    'total'   => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ) );
                echo '<div class="tablenav top"><div class="tablenav-pages" style="float:right; margin:5px 0;">' . wp_kses_post( $page_links ) . '</div><div class="clear"></div></div>';
            }

            echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th class="check-column"><input type="checkbox" id="cb-all-promo"></th><th>Name</th><th>Action</th></tr></thead><tbody>';
            
            if ( $products ) {
                foreach($products as $pObj) { 
                    $p=wc_get_product($pObj->ID); 
                    if(!$p) continue;
                    echo '<tr><th><input type="checkbox" name="gmc_promo_products[]" value="'.esc_attr($p->get_id()).'"></th><td><a href="'.esc_url(get_edit_post_link($p->get_id())).'">'.esc_html($p->get_name()).'</a></td><td><a href="'.esc_url(get_edit_post_link($p->get_id())).'" class="cirrusly-btn"><span class="dashicons dashicons-edit"></span>Edit</a></td></tr>'; 
                }
            } else {
                 echo '<tr><td colspan="3">No products found.</td></tr>';
            }

            echo '</tbody></table>';
            
            // Pagination Bottom
            if ( $total_pages > 1 ) {
                echo '<div class="tablenav bottom"><div class="tablenav-pages" style="float:right; margin:5px 0;">' . wp_kses_post( $page_links ) . '</div><div class="clear"></div></div>';
            }
            echo '</form>';
            wp_reset_postdata();
        }
    }

    /**
     * Render the Site Content Audit admin view for scanning local content and checking Google account status.
     *
     * Renders a UI that (1) checks for required policy pages on the site, (2) provides a restricted-terms scan with controls to run and display scan results, and (3) shows Google Merchant Center account-level issues for Pro users.
     */
    private function render_content_scan_view() {
        $is_pro = Cirrusly_Commerce_Core::cirrusly_is_pro();
        
        // Modern info banner for content audit
        echo '<div class="cirrusly-chart-card" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: #646970;">
                <span class="dashicons dashicons-admin-page" style="color: #2271b1; font-size: 18px;"></span>
                <div>
                    <strong style="color: #2c3338;">Site Content Audit:</strong> Verify your website has required policy pages and scan for restricted terms in product descriptions.
                </div>
            </div>
        </div>';
        
        $all_pages = get_pages();
        $found_titles = array();
        foreach($all_pages as $p) $found_titles[] = strtolower($p->post_title);
        
        $required = array(
            'Refund/Return Policy' => array('refund', 'return'),
            'Terms of Service'     => array('terms', 'conditions', 'tos'),
            'Contact Page'         => array('contact', 'support'),
            'Privacy Policy'       => array('privacy')
        );

        echo '<div class="cirrusly-chart-card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0; font-size: 16px; font-weight: 600; color: #2c3338;">Required Policy Pages</h3>
            <div class="cirrusly-policy-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">';
        foreach($required as $label => $keywords) {
            $found = false;
            foreach($found_titles as $title) {
                foreach($keywords as $kw) {
                    if(strpos($title, $kw) !== false) { $found = true; break 2; }
                }
            }
            $color = $found ? '#00a32a' : '#d63638';
            $bg = $found ? 'rgba(0, 163, 42, 0.08)' : 'rgba(214, 54, 56, 0.08)';
            $icon = $found ? 'dashicons-yes-alt' : 'dashicons-dismiss';
            echo '<div style="background: '.esc_attr($bg).'; border: 1px solid '.esc_attr($color).'; border-radius: 6px; padding: 12px 15px; display: flex; align-items: center; gap: 10px; transition: all 0.3s;">
                <span class="dashicons '.esc_attr($icon).'" style="color: '.esc_attr($color).'; font-size: 20px;"></span>
                <span style="font-weight: 500; color: #2c3338; font-size: 13px;">'.esc_html($label).'</span>
            </div>';
        }
        echo '</div></div>';

        // Restricted Terms Scan Section
        echo '<div class="cirrusly-chart-card" style="margin-bottom: 20px;">
            <div class="cirrusly-chart-header" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px 20px; margin: -25px -25px 20px -25px; border-bottom: 3px solid #f0ad4e; border-radius: 12px 12px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin: 0; color: #2c3338; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-search" style="color: #f0ad4e;"></span> Restricted Terms Scan</h3>
                <form method="post" style="margin:0;">';
        wp_nonce_field( 'cirrusly_content_scan', 'cirrusly_content_scan_nonce' );
        echo '<input type="hidden" name="run_content_scan" value="1">';
        echo '<button type="submit" name="run_scan" class="cirrusly-btn-success"><span class="dashicons dashicons-search"></span>Run New Scan</button>';
        echo '</form></div>';

        if ( isset( $_POST['run_content_scan'] ) && check_admin_referer( 'cirrusly_content_scan', 'cirrusly_content_scan_nonce' ) ) {
            $issues = $this->execute_content_scan_logic();
            update_option( 'cirrusly_content_scan_data', array('timestamp'=>time(), 'issues'=>$issues) );
            echo '<div class="notice notice-success inline" style="margin-top:10px;"><p>Scan Complete. Results saved.</p></div>';
        }

        $data = get_option( 'cirrusly_content_scan_data' );
        if ( !empty($data) && !empty($data['issues']) ) {
            echo '<div class="cirrusly-chart-card" style="margin-bottom: 20px;"><p style="margin: 0 0 15px 0; color: #646970;"><strong>Last Scan:</strong> ' . esc_html( date_i18n( get_option('date_format').' '.get_option('time_format'), $data['timestamp'] ) ) . '</p>';
            echo '<table class="wp-list-table widefat fixed striped" style="border: 0; box-shadow: none;"><thead><tr><th>Type</th><th>Title</th><th>Flagged Terms</th><th>Action</th></tr></thead><tbody>';
            foreach($data['issues'] as $issue) {
                echo '<tr>
                    <td>'.esc_html(ucfirst($issue['type'])).'</td>
                    <td><strong>'.esc_html($issue['title']).'</strong></td>
                    <td>';
                    foreach($issue['terms'] as $t) {
                        if ($t['severity'] === 'Critical') {
                            $border_color = '#d63638';
                            $text_color = '#d63638';
                            $bg_color = 'rgba(214, 54, 56, 0.08)';
                        } else {
                            $border_color = '#996800';
                            $text_color = '#996800';
                            $bg_color = 'rgba(219, 166, 23, 0.08)';
                        }
                        echo '<span class="gmc-badge" style="background:'.esc_attr($bg_color).';color:'.esc_attr($text_color).';border: 1px solid '.esc_attr($border_color).';cursor:help;" title="'.esc_attr($t['reason']).'">'.esc_html($t['word']).'</span> '; 
                    }
                    echo '</td>
                    <td><a href="'.esc_url(get_edit_post_link($issue['id'])).'" class="cirrusly-btn" target="_blank"><span class="dashicons dashicons-edit"></span>Edit</a></td>
                </tr>';
            }
            echo '</tbody></table></div>';
        } elseif( !empty($data) ) {
            echo '<div class="cirrusly-chart-card" style="background: linear-gradient(135deg, #e6f9ec 0%, #d4f5dd 100%); padding: 15px 20px; margin-bottom: 20px; border-left: 4px solid #00a32a;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a; font-size: 24px;"></span>
                    <span style="color: #2c3338; font-weight: 500;">No restricted terms found in last scan.</span>
                </div>
            </div>';
        } else {
            echo '<div class="cirrusly-chart-card" style="background: #f8f9fa; padding: 20px; text-align: center; border: 2px dashed #c3c4c7;">
                <span class="dashicons dashicons-info" style="color: #646970; font-size: 32px; margin-bottom: 10px;"></span>
                <p style="margin: 0; color: #646970; font-weight: 500;">No scan history found. Click "Run Scan" above to check your content.</p>
            </div>';
        }
        echo '</div>';

        // --- 3. PRO: GOOGLE ACCOUNT STATUS (Moved to Bottom) ---
        echo '<div class="cirrusly-settings-card ' . ( $is_pro ? '' : 'cirrusly-pro-feature' ) . '" style="margin-bottom:20px; border:1px solid #c3c4c7; padding:0;">';
        
        if ( ! $is_pro ) {
            echo '<div class="cirrusly-pro-overlay"><a href="' . esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ) . '" class="cirrusly-upgrade-btn"><span class="dashicons dashicons-lock cirrusly-lock-icon"></span> Check Account Bans</a></div>';
        }

        echo '<div class="cirrusly-card-header" style="background:#f8f9fa; border-bottom:1px solid #ddd; padding:15px;">
                <h3 style="margin:0;">Google Account Status <span class="cirrusly-pro-badge">PRO</span></h3>
              </div>';
        
        echo '<div class="cirrusly-card-body" style="padding:15px;">';
        
        if ( $is_pro ) {
            $account_status = $this->fetch_google_account_issues();
            
            // ERROR HANDLING
            if ( is_wp_error( $account_status ) ) {
                echo '<div class="notice notice-error inline" style="margin:0;"><p><strong>Connection Failed:</strong> ' . esc_html( $account_status->get_error_message() ) . '</p></div>';
            } 
            // SUCCESS - Handle service worker JSON response
            elseif ( is_array( $account_status ) || is_object( $account_status ) ) {
                // Convert object to array if needed
                $status_data = is_array( $account_status ) ? $account_status : (array) $account_status;
                
                // Get issues array from response
                $issues = isset( $status_data['issues'] ) ? $status_data['issues'] : array();
                
                if ( empty( $issues ) ) {
                    echo '<div class="notice notice-success inline" style="margin:0;"><p><strong>Account Healthy:</strong> No account-level policy issues detected.</p></div>';
                } else {
                    echo '<div class="notice notice-error inline" style="margin:0;"><p><strong>Attention Needed:</strong></p><ul style="list-style:disc; margin-left:20px;">';
                    foreach ( $issues as $issue ) {
                        // Handle both array and object formats from service worker
                        $title = isset( $issue['title'] ) ? $issue['title'] : (isset( $issue->title ) ? $issue->title : 'Unknown Issue');
                        $detail = isset( $issue['detail'] ) ? $issue['detail'] : (isset( $issue->detail ) ? $issue->detail : 'No details provided');
                        echo '<li><strong>' . esc_html( $title ) . ':</strong> ' . esc_html( $detail ) . '</li>';
                    }
                    echo '</ul></div>';
                }
            } else {
                echo '<div class="notice notice-warning inline" style="margin:0;"><p><strong>Unexpected Response Format:</strong> Could not parse account status from service worker.</p></div>';
            }
        } else {
            echo '<p>View real-time suspension status and policy violations directly from Google.</p>';
        }
        echo '</div></div>';
    }

    /**
     * Add a "Compliance" column to the product list table.
     *
     * @param array $columns Associative array of existing list table columns (column_key => label).
     * @return array The modified columns array including the `gmc_status` key labeled "Compliance".
     */
    public function add_gmc_admin_columns( $columns ) {
        $columns['gmc_status'] = __( 'Compliance', 'cirrusly-commerce' );
        return $columns;
    }

    /**
     * Render the "GMC Status" column content on the product list table.
     *
     * For each product ID, displays:
     * - GTIN status from post meta (`_gla_identifier_exists`)
     * - Custom product flag status
     * - MAP (Minimum Advertised Price) if configured
     * - Compliance badges (from audit scan)
     * - Quick-link to product edit page for GMC settings
     *
     * @param string $column Column key to render.
     * @param int    $post_id Product post ID.
     * @return void
     */
    public function render_gmc_admin_columns( $column, $post_id ) {
        if ( 'gmc_status' !== $column ) {
            return;
        }

        $product = wc_get_product( $post_id );
        if ( ! $product ) {
            return;
        }

        $gtin = get_post_meta( $post_id, '_gla_identifier_exists', true );
        $custom = get_post_meta( $post_id, '_gmc_product_custom', true );
        $map = get_post_meta( $post_id, '_cirrusly_map_price', true );

        echo '<div style="margin-bottom:5px;">';
        
        echo $gtin ? '<span class="dashicons dashicons-yes-alt" style="color:#28a745;"></span> GTIN: Present' : '<span class="dashicons dashicons-no-alt" style="color:#dc3545;"></span> GTIN: Missing';

        echo '<br>';

        echo $custom ? '<span class="dashicons dashicons-yes-alt" style="color:#28a745;"></span> Custom Product' : '<span class="dashicons dashicons-info" style="color:#ffc107;"></span> Standard Product';

        if ( ! empty( $map ) ) {
            echo '<br><span style="font-size:12px; color:#666;"><strong>MAP:</strong> ' . wp_kses_post( wc_price( $map ) ) . '</span>';
        }

        echo '<br><a href="' . esc_url( get_edit_post_link( $post_id ) ) . '#cirrusly-gmc-meta" class="cirrusly-btn-secondary" style="margin-top:5px;"><span class="dashicons dashicons-admin-generic"></span>Settings</a>';
        echo '</div>';
    }

    /**
     * Render the WooCommerce product data meta box for Cirrusly Commerce GMC settings.
     *
     * Allows setting per-product:
     * - GTIN type (UPC, EAN, ISBN, custom)
     * - GTIN value
     * - Custom product flag (hides product from feed if enabled)
     * - Minimum Advertised Price (MAP)
     * - Promotion ID assignment
     * - Flag for manual product exclusion from Google feeds
     */
    public function render_gmc_product_settings() {
        global $post;
        $product = wc_get_product( $post->ID );

        if ( ! $product ) {
            return;
        }

        // Get meta
        $gtin_type = get_post_meta( $post->ID, '_gtin_type', true ) ?: 'UPC';
        $gtin_value = get_post_meta( $post->ID, '_gtin_value', true ) ?: '';
        $gmc_custom = get_post_meta( $post->ID, '_gmc_product_custom', true ) ?: '';
        $map_price = get_post_meta( $post->ID, '_cirrusly_map_price', true ) ?: '';
        $promo_id = get_post_meta( $post->ID, '_gmc_promotion_id', true ) ?: '';
        $exclude = get_post_meta( $post->ID, '_gmc_product_exclude', true ) ?: '';

        // Render form
        echo '<div id="cirrusly-gmc-meta" class="cirrusly-product-meta">';

        woocommerce_wp_select( array(
            'id'      => '_gtin_type',
            'label'   => 'GTIN Type',
            'options' => array( 'UPC' => 'UPC', 'EAN' => 'EAN', 'ISBN' => 'ISBN', 'CUSTOM' => 'Custom' ),
            'value'   => $gtin_type,
            'desc_tip'=> true,
            'description' => 'Choose the type of GTIN/identifier for this product.',
        ) );

        woocommerce_wp_text_input( array(
            'id'      => '_gtin_value',
            'label'   => 'GTIN Value',
            'value'   => $gtin_value,
            'desc_tip'=> true,
            'description' => 'e.g., 1234567890123',
        ) );

        woocommerce_wp_checkbox( array(
            'id'      => '_gmc_product_custom',
            'label'   => 'Mark as Custom Product',
            'cbvalue' => 'yes',
            'value'   => $gmc_custom,
            'desc_tip'=> true,
            'description' => 'When enabled, this product will not be sent to Google feeds.',
        ) );

        woocommerce_wp_text_input( array(
            'id'      => '_cirrusly_map_price',
            'label'   => 'Minimum Advertised Price (MAP)',
            'value'   => $map_price,
            'type'    => 'number',
            'desc_tip'=> true,
            'description' => 'Override display price for MAP compliance.',
        ) );

        woocommerce_wp_text_input( array(
            'id'      => '_gmc_promotion_id',
            'label'   => 'Promotion ID',
            'value'   => $promo_id,
            'desc_tip'=> true,
            'description' => 'Link this product to an active Google promotion.',
        ) );

        woocommerce_wp_checkbox( array(
            'id'      => '_gmc_product_exclude',
            'label'   => 'Exclude from Google Feeds',
            'cbvalue' => 'yes',
            'value'   => $exclude,
            'desc_tip'=> true,
            'description' => 'Prevent this product from being sent to Google Merchant Center.',
        ) );

        echo '</div>';
    }

    /**
     * Render quick-edit controls for Cirrusly Commerce GMC settings within WooCommerce product list.
     *
     * @param string $column_name The quick-edit form column being rendered.
     * @param string $post_type   The post type being edited.
     */
    public function render_quick_edit_box( $column_name, $post_type ) {
        if ( 'gmc_status' !== $column_name || 'product' !== $post_type ) {
            return;
        }

        woocommerce_wp_checkbox( array(
            'id'      => '_gmc_product_custom',
            'label'   => 'Custom',
            'cbvalue' => 'yes',
            'desc_tip'=> true,
            'description' => 'Mark as custom product',
        ) );
    }

    /**
     * Display a success notice for products that were blocked from publication due to critical terms.
     *
     * When a product save is blocked (transient key `cirrusly_gmc_blocked_save_{user_id}`),
     * displays an admin notice and deletes the transient.
     */
    public function render_blocked_save_notice() {
        $message = get_transient( 'cirrusly_gmc_blocked_save_' . get_current_user_id() );
        if ( ! $message ) {
            return;
        }

        echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
        delete_transient( 'cirrusly_gmc_blocked_save_' . get_current_user_id() );
    }

    /**
     * Content scan logic that checks for restricted terms defined in the main GMC class.
     *
     * @return array Array of issues with structure: [ 'id' => post_id, 'title' => post title, 'type' => 'page'|'post'|'product', 'terms' => [ [ 'word' => term, 'severity' => 'Critical'|'Warning', 'reason' => explanation ], ... ] ]
     */
    private function execute_content_scan_logic() {
        $monitored = Cirrusly_Commerce_GMC::get_monitored_terms();
        $issues = array();

        foreach ( get_posts( array( 'numberposts' => -1, 'post_type' => array( 'page', 'post', 'product' ) ) ) as $p ) {
            $title = $p->post_title;
            $content = $p->post_content;
            $found_terms = array();

            foreach ( $monitored as $cat => $terms ) {
                foreach ( $terms as $word => $rules ) {
                    if ( stripos( $title, $word ) !== false || stripos( $content, $word ) !== false ) {
                        $found_terms[] = array(
                            'word'     => $word,
                            'severity' => isset( $rules['severity'] ) ? $rules['severity'] : 'Warning',
                            'reason'   => isset( $rules['reason'] ) ? $rules['reason'] : '',
                        );
                    }
                }
            }

            if ( ! empty( $found_terms ) ) {
                $issues[] = array(
                    'id'    => $p->ID,
                    'title' => $title,
                    'type'  => $p->post_type,
                    'terms' => $found_terms,
                );
            }
        }

        return $issues;
    }

    /**
     * Wrapper method to call the Pro class method for fetching account issues.
     *
     * @return WP_Error|array Account status response or error object.
     */
    private function fetch_google_account_issues() {
        if ( Cirrusly_Commerce_Core::cirrusly_is_pro() && class_exists( 'Cirrusly_Commerce_GMC_Pro' ) ) {
            return Cirrusly_Commerce_GMC_Pro::fetch_google_account_issues();
        }
        return new WP_Error( 'not_pro', 'Pro version required.' );
    }
    
    /**
     * Render AI Preview Modal HTML.
     */
    private function render_ai_preview_modal() {
        ?>
        <div id="cirrusly-ai-preview-modal" class="cirrusly-modal" style="display:none;">
            <div class="cirrusly-modal-overlay"></div>
            <div class="cirrusly-modal-content">
                <div class="cirrusly-modal-header">
                    <h2><?php esc_html_e( 'AI Content Studio', 'cirrusly-commerce' ); ?> <span class="cirrusly-pro-badge" style="font-size:12px;">PRO+</span></h2>
                    <button type="button" class="cirrusly-modal-close">&times;</button>
                </div>
                
                <!-- AI Enhancement Selector -->
                <div class="cirrusly-enhancement-selector" style="display:none;">
                    <div class="cirrusly-enhancement-label">
                        <span class="dashicons dashicons-admin-appearance" style="color:#667eea;"></span>
                        <strong><?php esc_html_e( 'AI Enhancement Style:', 'cirrusly-commerce' ); ?></strong>
                    </div>
                    <div class="cirrusly-enhancement-options">
                        <!-- Will be populated dynamically based on action type -->
                    </div>
                </div>
                
                <div class="cirrusly-modal-body">
                    <div class="cirrusly-preview-loading" style="text-align:center; padding:40px;">
                        <span class="spinner is-active" style="float:none;"></span>
                        <p><?php esc_html_e( 'Generating AI content...', 'cirrusly-commerce' ); ?></p>
                    </div>
                    <div class="cirrusly-preview-content" style="display:none;">
                        <div class="cirrusly-preview-product-name" style="margin-bottom:20px; font-weight:bold; font-size:16px;"></div>
                        
                        <div class="cirrusly-preview-section">
                            <h3><?php esc_html_e( 'Current Content', 'cirrusly-commerce' ); ?></h3>
                            <div class="cirrusly-preview-current" style="padding:15px; background:#f9f9f9; border-left:4px solid #ddd; min-height:50px; white-space:pre-wrap;"></div>
                        </div>
                        
                        <div class="cirrusly-preview-section" style="margin-top:20px;">
                            <h3><?php esc_html_e( 'AI-Generated Content', 'cirrusly-commerce' ); ?></h3>
                            <div class="cirrusly-preview-generated" contenteditable="true" style="padding:15px; background:#e0f7fa; border-left:4px solid #46b450; min-height:50px; white-space:pre-wrap;" data-placeholder="<?php esc_attr_e( 'You can edit this before applying...', 'cirrusly-commerce' ); ?>"></div>
                            <p class="description" style="margin-top:5px;"><?php esc_html_e( 'Click to edit the AI-generated content before applying.', 'cirrusly-commerce' ); ?></p>
                        </div>
                    </div>
                </div>
                <div class="cirrusly-modal-footer">
                    <button type="button" class="button button-secondary cirrusly-modal-cancel"><?php esc_html_e( 'Cancel', 'cirrusly-commerce' ); ?></button>
                    <button type="button" class="button button-secondary cirrusly-regenerate-btn" style="display:none;">
                        <span class="dashicons dashicons-update" style="margin-top:3px;"></span> <?php esc_html_e( 'Regenerate', 'cirrusly-commerce' ); ?>
                    </button>
                    <div class="cirrusly-footer-spacer" style="flex:1;"></div>
                    <span class="cirrusly-char-count" style="display:none;margin-right:10px;color:#666;font-size:12px;"></span>
                    <button type="button" class="cirrusly-btn-success cirrusly-apply-btn" style="display:none;">
                        <span class="dashicons dashicons-saved" style="margin-top:3px;"></span> <?php esc_html_e( 'Apply Changes', 'cirrusly-commerce' ); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <style>
        .cirrusly-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 100000;
        }
        .cirrusly-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
        }
        .cirrusly-modal-content {
            position: relative;
            background: #fff;
            max-width: 800px;
            margin: 50px auto;
            border-radius: 4px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-height: calc(100vh - 100px);
            display: flex;
            flex-direction: column;
        }
        .cirrusly-modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cirrusly-modal-header h2 {
            margin: 0;
            font-size: 18px;
        }
        .cirrusly-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #666;
            line-height: 1;
            padding: 0;
        }
        .cirrusly-modal-close:hover {
            color: #d63638;
        }
        
        /* AI Enhancement Selector Styles */
        .cirrusly-enhancement-selector {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-bottom: 1px solid #c3c4c7;
            padding: 15px 20px;
        }
        .cirrusly-enhancement-label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            font-size: 13px;
        }
        .cirrusly-enhancement-options {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cirrusly-enhancement-option {
            background: #fff;
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 8px 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cirrusly-enhancement-option:hover {
            border-color: #667eea;
            background: #f9f9ff;
            transform: translateY(-1px);
        }
        .cirrusly-enhancement-option.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-color: #667eea;
        }
        .cirrusly-enhancement-option .dashicons {
            font-size: 16px;
            width: 16px;
            height: 16px;
        }
        .cirrusly-enhancement-option.active .dashicons {
            color: #fff;
        }
        
        /* Cost Badges */
        .cirrusly-cost-badge {
            display: inline-block;
            margin-left: auto;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .cirrusly-cost-free {
            background: #46b450;
            color: #fff;
        }
        .cirrusly-cost-basic {
            background: #f0f0f1;
            color: #50575e;
            border: 1px solid #c3c4c7;
        }
        .cirrusly-cost-advanced {
            background: #fcf0f1;
            color: #d63638;
            border: 1px solid #f0b4b8;
        }
        .cirrusly-enhancement-option.active .cirrusly-cost-badge {
            opacity: 0.9;
        }
        .cirrusly-char-count {
            font-family: 'Courier New', monospace;
        }
        .cirrusly-char-count.warning {
            color: #dba617 !important;
            font-weight: bold;
        }
        .cirrusly-char-count.error {
            color: #d63638 !important;
            font-weight: bold;
        }
        
        .cirrusly-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
        }
        .cirrusly-preview-section h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            font-weight: 600;
            color: #1d2327;
        }
        .cirrusly-preview-generated[contenteditable="true"]:empty:before {
            content: attr(data-placeholder);
            color: #999;
            font-style: italic;
        }
        .cirrusly-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #ddd;
            text-align: right;
            display: flex;
            justify-content: space-between;
        }
        .cirrusly-modal-footer .button {
            margin-left: 10px;
        }
        </style>
        <?php
    }

    /**
     * Render the Sync Status tab showing real-time GMC sync queue and API quota status.
     */
    private function render_sync_status_tab() {
        $is_pro = Cirrusly_Commerce_Core::cirrusly_is_pro();

        if ( ! $is_pro ) {
            echo '<div class="cirrusly-chart-card" style="padding: 40px; text-align: center;">';
            echo '<span class="dashicons dashicons-lock" style="font-size: 48px; color: #d63638; margin-bottom: 15px;"></span>';
            echo '<h2 style="margin: 0 0 10px 0;">Sync Status is a Pro Feature</h2>';
            echo '<p style="color: #646970; margin-bottom: 20px;">Monitor real-time product sync status, track API quota usage, and manage failed syncs with Cirrusly Commerce Pro.</p>';
            if ( function_exists( 'cirrusly_fs' ) ) {
                $upgrade_url = cirrusly_fs()->get_upgrade_url();
                echo '<a href="' . esc_url( $upgrade_url ) . '" class="button button-primary">Upgrade to Pro</a>';
            }
            echo '</div>';
            return;
        }

        // Get sync stats
        $stats = Cirrusly_Commerce_Pricing_Sync::get_sync_stats();

        // Calculate quota percentage
        $quota_percentage = $stats['quota_limit'] > 0 ? ( $stats['quota_used'] / $stats['quota_limit'] ) * 100 : 0;
        $quota_color = $quota_percentage > 90 ? '#d63638' : ($quota_percentage > 70 ? '#f0ad4e' : '#00a32a');

        // Calculate queue status color
        $queue_color = count( $stats['failed_products'] ) > 0 ? '#d63638' : ($stats['queue_size'] > 0 ? '#f0ad4e' : '#00a32a');

        // Format last sync time
        $last_sync_text = 'Never';
        if ( $stats['last_sync_time'] ) {
            $last_sync_text = human_time_diff( $stats['last_sync_time'], current_time( 'timestamp' ) ) . ' ago';
        }

        // Format next sync time
        $next_sync_text = 'Not scheduled';
        if ( $stats['next_sync_due'] ) {
            $time_until = $stats['next_sync_due'] - current_time( 'timestamp' );
            if ( $time_until > 0 ) {
                $next_sync_text = 'In ' . human_time_diff( current_time( 'timestamp' ), $stats['next_sync_due'] );
            } else {
                $next_sync_text = 'Processing now...';
            }
        }

        ?>
        <div class="cirrusly-chart-card" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: #646970;">
                <span class="dashicons dashicons-update" style="color: #2271b1; font-size: 18px;"></span>
                <div>
                    <strong style="color: #2c3338;">Sync Status:</strong> Monitor real-time product sync queue, API quota usage, and track failed syncs. Products are automatically synced to Google Merchant Center when you save them in WooCommerce.
                </div>
            </div>
        </div>

        <!-- KPI Cards Grid -->
        <div class="cirrusly-kpi-grid" style="margin-bottom: 20px;">
            <div class="cirrusly-kpi-card" style="--card-accent: <?php echo esc_attr( $queue_color ); ?>;">
                <span class="dashicons dashicons-cloud-upload" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(34, 113, 177, 0.15);"></span>
                <span class="cirrusly-kpi-label">Queue Size</span>
                <span class="cirrusly-kpi-value" style="color: <?php echo esc_attr( $queue_color ); ?>;" id="cirrusly-queue-size" data-value="<?php echo esc_attr( $stats['queue_size'] ); ?>"><?php echo esc_html( number_format( $stats['queue_size'] ) ); ?></span>
                <small style="font-size: 11px; color: #646970; margin-top: 5px;">Products Waiting to Sync</small>
            </div>

            <div class="cirrusly-kpi-card" style="--card-accent: #2271b1;">
                <span class="dashicons dashicons-clock" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(34, 113, 177, 0.15);"></span>
                <span class="cirrusly-kpi-label">Last Sync</span>
                <span class="cirrusly-kpi-value" style="font-size: 20px; color: #2271b1;" id="cirrusly-last-sync"><?php echo esc_html( $last_sync_text ); ?></span>
                <small style="font-size: 11px; color: #646970; margin-top: 5px;" id="cirrusly-next-sync">Next: <?php echo esc_html( $next_sync_text ); ?></small>
            </div>

            <div class="cirrusly-kpi-card" style="--card-accent: <?php echo esc_attr( $quota_color ); ?>;">
                <span class="dashicons dashicons-chart-line" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(34, 113, 177, 0.15);"></span>
                <span class="cirrusly-kpi-label">API Quota</span>
                <span class="cirrusly-kpi-value" style="color: <?php echo esc_attr( $quota_color ); ?>;" id="cirrusly-quota-used"><?php echo esc_html( number_format( $stats['quota_used'] ) ); ?></span>
                <small style="font-size: 11px; color: #646970; margin-top: 5px;" id="cirrusly-quota-limit">of <?php echo esc_html( number_format( $stats['quota_limit'] ) ); ?> calls used today</small>
            </div>

            <div class="cirrusly-kpi-card" style="--card-accent: <?php echo esc_attr( $stats['success_rate'] > 90 ? '#00a32a' : ($stats['success_rate'] > 50 ? '#f0ad4e' : '#d63638') ); ?>;">
                <span class="dashicons dashicons-yes-alt" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(0, 163, 42, 0.15);"></span>
                <span class="cirrusly-kpi-label">Success Rate</span>
                <span class="cirrusly-kpi-value" style="color: <?php echo esc_attr( $stats['success_rate'] > 90 ? '#00a32a' : ($stats['success_rate'] > 50 ? '#f0ad4e' : '#d63638') ); ?>;" id="cirrusly-success-rate"><?php echo esc_html( number_format( $stats['success_rate'] ) ); ?>%</span>
                <small style="font-size: 11px; color: #646970; margin-top: 5px;">Recent Sync Performance</small>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="cirrusly-chart-card" style="margin-bottom: 20px; padding: 15px 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <button type="button" id="cirrusly-refresh-sync-status" class="button button-secondary">
                    <span class="dashicons dashicons-update" style="line-height: inherit; margin-right: 5px;"></span>
                    Refresh Status
                </button>
                <span style="color: #646970; font-size: 12px; flex-grow: 1;">Auto-refreshes every 30 seconds when queue is active</span>
                <span id="cirrusly-sync-status-indicator" class="cirrusly-status-indicator" data-status="<?php echo esc_attr( $stats['queue_size'] > 0 ? 'syncing' : 'idle' ); ?>">
                    <?php echo esc_html( $stats['queue_size'] > 0 ? 'SYNCING' : 'IDLE' ); ?>
                </span>
            </div>
            <div id="cirrusly-sync-status-error" class="notice notice-error inline" style="display: none; margin: 10px 0 0 0; padding: 8px 12px;">
                <p style="margin: 0;"></p>
            </div>
        </div>

        <?php if ( $stats['last_error'] ) : ?>
        <!-- Error Notice -->
        <div class="notice notice-error inline" style="margin-bottom: 20px;">
            <p><strong>Last Sync Error:</strong> <code><?php echo esc_html( $stats['last_error'] ); ?></code></p>
        </div>
        <?php endif; ?>

        <!-- Sync Queue Table -->
        <div class="cirrusly-chart-card">
            <h3 style="margin: 0 0 20px 0; padding-bottom: 15px; border-bottom: 3px solid #2271b1; color: #2c3338; font-size: 16px;">
                <span class="dashicons dashicons-list-view" style="color: #2271b1; vertical-align: middle;"></span>
                Sync Queue (<?php echo esc_html( count( $stats['queue_items'] ) ); ?> items)
            </h3>

            <div id="cirrusly-sync-queue-container">
                <?php if ( empty( $stats['queue_items'] ) ) : ?>
                <div style="text-align: center; padding: 40px; color: #646970;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #00a32a; margin-bottom: 10px;"></span>
                    <p style="margin: 0; font-size: 14px;"><strong>All products synced!</strong></p>
                    <p style="margin: 5px 0 0 0; font-size: 12px;">No items in queue.</p>
                </div>
                <?php else : ?>
                <table class="wp-list-table widefat fixed striped" style="border: none;">
                    <thead>
                        <tr>
                            <th style="width: 50px;">Image</th>
                            <th>Product</th>
                            <th style="width: 120px;">SKU</th>
                            <th style="width: 100px;">Status</th>
                            <th style="width: 80px;">Attempts</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $stats['queue_items'] as $item ) : ?>
                        <tr>
                            <td>
                                <?php if ( $item['image_url'] ) : ?>
                                <img src="<?php echo esc_url( $item['image_url'] ); ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                <?php else : ?>
                                <span class="dashicons dashicons-format-image" style="font-size: 40px; color: #ddd;"></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo esc_html( $item['name'] ); ?></strong><br>
                                <small style="color: #646970;">ID: <?php echo esc_html( $item['id'] ); ?></small>
                            </td>
                            <td>
                                <code><?php echo esc_html( $item['sku'] ); ?></code>
                            </td>
                            <td>
                                <?php
                                $status_color = $item['status'] === 'failed' ? '#d63638' : '#f0ad4e';
                                $status_bg = $item['status'] === 'failed' ? '#ffe6e6' : '#fff4e6';
                                $status_label = $item['status'] === 'failed' ? 'FAILED' : 'PENDING';
                                ?>
                                <span style="padding: 4px 10px; background: <?php echo esc_attr( $status_bg ); ?>; border: 1px solid <?php echo esc_attr( $status_color ); ?>; border-radius: 12px; font-size: 11px; font-weight: 600; color: <?php echo esc_attr( $status_color ); ?>;">
                                    <?php echo esc_html( $status_label ); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <span style="color: <?php echo esc_attr( $item['attempts'] >= 2 ? '#d63638' : '#646970' ); ?>; font-weight: 600;">
                                    <?php echo esc_html( $item['attempts'] ); ?>/3
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
