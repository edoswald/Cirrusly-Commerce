<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_Pricing_UI {

    public function __construct() {
        // Meta Boxes - Priority 20 ensures we render AFTER the COGS field
        add_action( 'woocommerce_product_options_pricing', array( $this, 'pe_render_simple_fields' ), 20 );
        add_action( 'woocommerce_variation_options_pricing', array( $this, 'pe_render_variable_fields' ), 20, 3 );

        // Saving - Priority 5 to run BEFORE WooCommerce's own handlers (which run at 10)
        // IMPORTANT: Priority 5 is required to ensure our custom field data (especially MAP price
        // using cirrusly_map_price_input) is saved before WooCommerce processes and potentially
        // clears certain fields from $_POST. This prevents data loss while maintaining nonce security.
        add_action( 'woocommerce_process_product_meta', array( $this, 'pe_save_simple' ), 5 );
        add_action( 'woocommerce_save_product_variation', array( $this, 'pe_save_variable' ), 5, 2 );

        // Admin Columns
        add_filter( 'manage_edit-product_columns', array( $this, 'add_margin_column' ) );
        add_action( 'manage_product_posts_custom_column', array( $this, 'render_margin_column' ), 10, 2 );

        // Admin Notices
        add_action( 'admin_notices', array( $this, 'display_datetime_validation_errors' ) );
    }

    public function add_margin_column( $columns ) {
        $new_columns = array();
        foreach ( $columns as $key => $title ) {
            $new_columns[$key] = $title;
            if ( 'price' === $key ) {
                $new_columns['cirrusly_margin'] = __( 'Margin', 'cirrusly-commerce' );
            }

        }
        return $new_columns;
    }

    public function render_margin_column( $column, $post_id ) {
        if ( 'cirrusly_margin' !== $column ) return;
        
        $product = wc_get_product( $post_id );
        if ( ! $product ) return;

        $price = (float) $product->get_price();
        // Use get_post_meta to avoid notices
        $cost = (float) get_post_meta( $product->get_id(), '_cogs_total_value', true );

        if ( $price > 0 && $cost > 0 ) {
            $margin = (($price - $cost) / $price) * 100;
            $color = $margin < 15 ? '#d63638' : '#008a20';
            echo '<span style="font-weight:bold; color:' . esc_attr($color) . '">' . number_format( $margin, 0 ) . '%</span>';
        } else {
            echo '<span style="color:#999;">-</span>';
        }
    }

    /**
     * Render pricing-related admin fields for a simple product.
     *
     * Outputs inputs for Google Min Price, MAP, MSRP, Shipping Cost, and Sale Timer End, then renders the pricing toolbar.
     */
    public function pe_render_simple_fields() {
        global $post;
        $product_id = $post->ID;
        
        $ship = get_post_meta( $product_id, '_cirrusly_est_shipping', true );
        $map  = get_post_meta( $product_id, '_cirrusly_map_price', true ); 
        $msrp = get_post_meta( $product_id, '_alg_msrp', true ); 
        $min  = get_post_meta( $product_id, '_auto_pricing_min_price', true );
        $sale_end = get_post_meta( $product_id, '_cirrusly_sale_end', true );
        
        // Pass field values to toolbar for display inside Pricing Intelligence
        $this->pe_render_toolbar( array(
            'min_price' => $min,
            'map' => $map,
            'msrp' => $msrp,
            'shipping' => $ship,
            'sale_end' => $sale_end
        ));
    }

    /**
     * Render pricing-related input fields for a single product variation in the admin UI.
     *
     * Outputs inputs for Google Min Price, MAP, MSRP, and Shipping Cost for the specified variation
     * and renders the pricing toolbar.
     *
     * @param int   $loop           Variation index used to name input fields.
     * @param array $variation_data Variation data (unused; required by the WooCommerce hook).
     * @param object $variation     Variation post object (WP_Post) for which fields are rendered.
     */
    public function pe_render_variable_fields( $loop, $variation_data, $variation ) {
        $cogs = get_post_meta( $variation->ID, '_cogs_total_value', true );
        $ship = get_post_meta( $variation->ID, '_cirrusly_est_shipping', true );
        $map  = get_post_meta( $variation->ID, '_cirrusly_map_price', true );
        $msrp = get_post_meta( $variation->ID, '_alg_msrp', true );
        $min  = get_post_meta( $variation->ID, '_auto_pricing_min_price', true );
        $sale_end = get_post_meta( $variation->ID, '_cirrusly_sale_end', true );

        echo '<div class="cirrusly-cogs-wrapper-var"><div class="cirrusly-dual-row-variable four-cols">';
        woocommerce_wp_text_input( array( 'id' => "_cogs_total_value[$loop]", 'label' => 'Cost of Goods', 'class' => 'wc_input_price short cirrusly-cogs-input', 'value' => $cogs, 'wrapper_class' => 'cirrusly-flex-field' ));
        woocommerce_wp_text_input( array( 'id' => "_auto_pricing_min_price[$loop]", 'label' => 'Google Min Price', 'class' => 'wc_input_price short cirrusly-min-input', 'value' => $min, 'wrapper_class' => 'cirrusly-flex-field' ));
        woocommerce_wp_text_input( array( 'id' => "cirrusly_map_price_input[$loop]", 'label' => 'MAP', 'class' => 'wc_input_price short cirrusly-map-input', 'value' => $map, 'wrapper_class' => 'cirrusly-flex-field' ));
        woocommerce_wp_text_input( array( 'id' => "_alg_msrp[$loop]", 'label' => 'MSRP', 'class' => 'wc_input_price short cirrusly-msrp-input', 'value' => $msrp, 'wrapper_class' => 'cirrusly-flex-field' ));
        woocommerce_wp_text_input( array( 'id' => "_cirrusly_est_shipping[$loop]", 'label' => 'Shipping Cost', 'class' => 'wc_input_price short cirrusly-ship-input', 'value' => $ship, 'wrapper_class' => 'cirrusly-flex-field' ));
        woocommerce_wp_text_input( array( 'id' => "_cirrusly_sale_end[$loop]", 'label' => 'Sale Timer End', 'class' => 'short cirrusly-sale-end-input cirrusly-datetime-picker', 'value' => $sale_end, 'wrapper_class' => 'cirrusly-flex-field', 'custom_attributes' => array( 'readonly' => 'readonly' ) ));
        echo '</div>';
        // Note: Toolbar removed for variations to avoid duplicate fields and wrong product context
        // Consider creating a variation-specific toolbar if metrics/strategies are needed
        echo '</div>';
    }

    /**
     * Render the pricing engine toolbar in the product admin UI.
     *
     * Outputs modern card-based UI with pricing strategies, margin scenarios, and GMC insights.
     *
     * @param array $field_values Optional array of field values (min_price, map, msrp, shipping, sale_end)
     */
    private function pe_render_toolbar( $field_values = array() ) {
        global $post;
        $product_id = $post->ID;

        // Get COGS value - handle variable products differently
        $cost = get_post_meta( $product_id, '_cogs_total_value', true );
        $product = wc_get_product( $product_id );
        $cogs_note = '';

        // For variable products, show range from variations
        if ( $product && $product->is_type( 'variable' ) ) {
            $variation_ids = $product->get_children();
            $variation_costs = array();

            foreach ( $variation_ids as $variation_id ) {
                $variation_cost = get_post_meta( $variation_id, '_cogs_total_value', true );
                if ( $variation_cost && is_numeric( $variation_cost ) ) {
                    $variation_costs[] = floatval( $variation_cost );
                }
            }

            if ( ! empty( $variation_costs ) ) {
                $min_cost = min( $variation_costs );
                $max_cost = max( $variation_costs );

                // Use tolerance-based comparison for floats to avoid precision issues
                if ( abs( $min_cost - $max_cost ) < 0.001 ) {
                    $cost = $min_cost;
                } else {
                    $cost = $min_cost; // Use min for calculations
                    $cogs_note = sprintf(
                        /* translators: 1: minimum cost, 2: maximum cost */
                        __( 'Variation range: %1$s - %2$s', 'cirrusly-commerce' ),
                        wc_price( $min_cost ),
                        wc_price( $max_cost )
                    );
                }
            }
        }

        // Check for GMC analytics data (Pro Plus)
        $gmc_data = array();
        if ( class_exists( 'Cirrusly_Commerce_Core' ) && Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            if ( ! $product ) {
                $product = wc_get_product( $product_id );
            }
            if ( $product ) {
                $sku = $product->get_sku();
                if ( $sku ) {
                    $gmc_data = $this->get_gmc_product_insights( $sku );
                }
            }
        }
        ?>
        <style>
        .cirrusly-pricing-engine-wrapper {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-top: 15px;
        }
        /* Hide native WooCommerce COGS field only when our Pricing Intelligence toolbar is present */
        body.post-type-product .cirrusly-pricing-engine-wrapper ~ .options_group.pricing .form-field._cogs_total_value_field,
        body.post-type-product .options_group.pricing .form-field._cogs_total_value_field:has(~ .cirrusly-pricing-engine-wrapper) {
            display: none !important;
        }
        /* Fallback for browsers without :has() support - only hide when toolbar is active */
        @supports not (selector(:has(*))) {
            body.post-type-product.cirrusly-toolbar-active .options_group.pricing .form-field._cogs_total_value_field {
                display: none !important;
            }
        }
        .cirrusly-custom-fields-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
            padding: 15px;
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
        }
        .cirrusly-field-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .cirrusly-field-group label {
            font-size: 12px;
            font-weight: 600;
            color: #2c3338;
            display: flex;
            align-items: center;
            gap: 4px;
            margin: 0;
        }
        .cirrusly-field-group input {
            width: 100% !important;
            border-radius: 6px;
            border: 1px solid #dcdcde;
            padding: 8px 10px;
            font-size: 14px;
        }
        .cirrusly-field-group input:focus {
            border-color: #2271b1;
            outline: none;
            box-shadow: 0 0 0 1px #2271b1;
        }
        .cirrusly-pricing-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        .cirrusly-pricing-header h4 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
            color: #2271b1;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cirrusly-pricing-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        .cirrusly-pricing-card {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .cirrusly-pricing-card:hover {
            box-shadow: 0 4px 12px rgba(34, 113, 177, 0.1);
            border-color: #2271b1;
        }
        .cirrusly-pricing-card h5 {
            margin: 0 0 12px 0;
            font-size: 13px;
            font-weight: 600;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cirrusly-strategy-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .cirrusly-strategy-group select {
            flex: 1;
            min-width: 140px;
            border-radius: 6px;
            border: 1px solid #dcdcde;
            padding: 6px 10px;
            font-size: 13px;
        }
        .cirrusly-metrics-display {
            background: #ffffff;
            border: 2px solid #2271b1;
            color: #2c3338;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .cirrusly-metrics-row {
            display: flex;
            justify-content: space-around;
            gap: 20px;
        }
        .cirrusly-metric-item {
            text-align: center;
        }
        .cirrusly-metric-label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #646970;
            margin-bottom: 5px;
        }
        .cirrusly-metric-value {
            font-size: 24px;
            font-weight: 700;
            line-height: 1;
            color: #2271b1;
        }
        .cirrusly-scenario-matrix {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            overflow: hidden;
        }
        .cirrusly-scenario-header {
            background: linear-gradient(90deg, #f0f0f1 0%, #ffffff 100%);
            padding: 12px 15px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
            font-size: 13px;
            color: #2271b1;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cirrusly-scenario-row {
            display: grid;
            grid-template-columns: 120px 1fr 100px 100px;
            gap: 15px;
            padding: 12px 15px;
            border-bottom: 1px solid #f0f0f1;
            align-items: center;
            transition: background 0.2s;
        }
        .cirrusly-scenario-row:hover {
            background: #f8f9fa;
        }
        .cirrusly-scenario-row:last-child {
            border-bottom: none;
        }
        .cirrusly-scenario-label {
            font-weight: 600;
            font-size: 13px;
            color: #1d2327;
        }
        .cirrusly-scenario-mult {
            font-size: 12px;
            color: #646970;
        }
        .cirrusly-scenario-profit {
            text-align: right;
            font-weight: 700;
            font-size: 14px;
        }
        .cirrusly-scenario-margin {
            text-align: right;
            font-weight: 600;
            font-size: 13px;
        }
        .cirrusly-profit-positive {
            color: #00a32a;
        }
        .cirrusly-profit-negative {
            color: #d63638;
        }
        .cirrusly-profit-warning {
            color: #dba617;
        }
        .cirrusly-gmc-insights {
            background: linear-gradient(135deg, #f0f6ff 0%, #ffffff 100%);
            border: 1px solid #c3d5e8;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .cirrusly-gmc-insights h5 {
            margin: 0 0 12px 0;
            font-size: 13px;
            font-weight: 600;
            color: #2271b1;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .cirrusly-gmc-metrics {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
        }
        .cirrusly-gmc-metric {
            background: #ffffff;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .cirrusly-gmc-metric-label {
            font-size: 11px;
            color: #646970;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .cirrusly-gmc-metric-value {
            font-size: 18px;
            font-weight: 700;
            color: #2271b1;
        }
        </style>
        
        <div class="cirrusly-pricing-engine-wrapper">
            <!-- Header -->
            <div class="cirrusly-pricing-header">
                <h4>
                    <span class="dashicons dashicons-chart-line" style="font-size: 20px;"></span>
                    Pricing Intelligence
                </h4>
            </div>
            
            <!-- Custom Pricing Fields -->
            <div class="cirrusly-custom-fields-row">
                <!-- First Row: Cost of Goods, MSRP, Base Ship -->
                <div class="cirrusly-field-group">
                    <label for="_cogs_total_value">Cost of Goods ($)</label>
                    <input type="text" id="_cogs_total_value" name="_cogs_total_value" class="wc_input_price short" value="<?php echo esc_attr( $cost ); ?>" placeholder="0.00" />
                    <?php if ( $cogs_note ) : ?>
                        <small style="color: #666; font-size: 11px; margin-top: 4px;"><?php echo wp_kses_post( $cogs_note ); ?></small>
                    <?php endif; ?>
                </div>

                <div class="cirrusly-field-group">
                    <label for="_alg_msrp">MSRP ($)</label>
                    <input type="text" id="_alg_msrp" name="_alg_msrp" class="wc_input_price short" value="<?php echo esc_attr( isset($field_values['msrp']) ? $field_values['msrp'] : '' ); ?>" placeholder="0.00" />
                </div>

                <div class="cirrusly-field-group">
                    <label for="_cirrusly_est_shipping">Base Ship ($)</label>
                    <input type="text" id="_cirrusly_est_shipping" name="_cirrusly_est_shipping" class="wc_input_price short" value="<?php echo esc_attr( isset($field_values['shipping']) ? $field_values['shipping'] : '' ); ?>" placeholder="0.00" />
                </div>

                <!-- Second Row: MAP, Google Min Price, Sale Timer End -->
                <div class="cirrusly-field-group">
                    <label for="cirrusly_map_price_input">MAP ($)</label>
                    <input type="text" id="cirrusly_map_price_input" name="cirrusly_map_price_input" class="wc_input_price short" value="<?php echo esc_attr( isset($field_values['map']) ? $field_values['map'] : '' ); ?>" placeholder="0.00" />
                </div>

                <div class="cirrusly-field-group">
                    <label for="_auto_pricing_min_price">Google Min Price ($)
                        <span class="dashicons dashicons-info-outline" title="Lowest price for Automated Discounts" style="font-size: 14px; color: #2271b1; cursor: help;"></span>
                    </label>
                    <input type="text" id="_auto_pricing_min_price" name="_auto_pricing_min_price" class="wc_input_price short" value="<?php echo esc_attr( isset($field_values['min_price']) ? $field_values['min_price'] : '' ); ?>" placeholder="0.00" />
                </div>

                <div class="cirrusly-field-group">
                    <label for="_cirrusly_sale_end">Sale Timer End
                        <span class="dashicons dashicons-info-outline" title="Countdown timer end date/time" style="font-size: 14px; color: #2271b1; cursor: help;"></span>
                    </label>
                    <input type="text" id="_cirrusly_sale_end" name="_cirrusly_sale_end" class="short cirrusly-datetime-picker" value="<?php echo esc_attr( isset($field_values['sale_end']) ? $field_values['sale_end'] : '' ); ?>" placeholder="YYYY-MM-DD HH:MM" readonly />
                    <small style="display: block; color: #666; margin-top: 4px; font-size: 11px;">Enable countdown with checkbox below</small>
                </div>

                <div class="cirrusly-field-group" style="display: flex; flex-direction: column; justify-content: center;">
                    <label for="_cirrusly_enable_countdown" style="display: inline-flex; align-items: center; gap: 8px; margin: 0; width: fit-content;">
                        <input type="checkbox" id="_cirrusly_enable_countdown" name="_cirrusly_enable_countdown" value="yes" <?php checked( get_post_meta( $product_id, '_cirrusly_enable_countdown', true ), 'yes' ); ?> style="margin: 0; width: 16px !important; height: 16px !important; min-width: 16px; min-height: 16px; max-width: 16px; max-height: 16px; flex: 0 0 16px; appearance: auto;" />
                        <span style="white-space: nowrap; flex: 0 1 auto;">Enable Sale Countdown</span>
                    </label>
                    <small style="display: block; color: #666; margin-top: 4px; font-size: 11px;">Displays countdown to Sale Timer End date</small>
                </div>
            </div>
            
            <!-- Main Metrics Display -->
            <div class="cirrusly-metrics-display">
                <div class="cirrusly-metrics-row">
                    <div class="cirrusly-metric-item">
                        <div class="cirrusly-metric-label">Net Profit</div>
                        <div class="cirrusly-metric-value cirrusly-profit-val">--</div>
                    </div>
                    <div class="cirrusly-metric-item">
                        <div class="cirrusly-metric-label">Margin %</div>
                        <div class="cirrusly-metric-value cirrusly-margin-val">--</div>
                    </div>
                    <div class="cirrusly-metric-item">
                        <div class="cirrusly-metric-label">Min Price</div>
                        <div class="cirrusly-metric-value cirrusly-min-price-val">--</div>
                    </div>
                </div>
            </div>
            
            <!-- Pricing Strategies Grid -->
            <div class="cirrusly-pricing-grid">
                <!-- Sale Strategy Card -->
                <div class="cirrusly-pricing-card">
                    <h5>
                        <span class="dashicons dashicons-tag" style="color: #d63638;"></span>
                        Sale Pricing
                    </h5>
                    <div class="cirrusly-strategy-group">
                        <select class="cirrusly-tool-sale">
                            <option value="">Choose Strategy</option>
                            <option value="msrp_05">5% Off MSRP</option>
                            <option value="msrp_10">10% Off MSRP</option>
                            <option value="msrp_15">15% Off MSRP</option>
                            <option value="msrp_20">20% Off MSRP</option>
                            <option value="msrp_25">25% Off MSRP</option>
                            <option value="msrp_30">30% Off MSRP</option>
                            <option value="msrp_40">40% Off MSRP</option>
                            <option value="reg_5">5% Off Reg</option>
                            <option value="reg_10">10% Off Reg</option>
                            <option value="reg_20">20% Off Reg</option>
                            <option value="clear" style="color:#d63638;">✕ Clear Sale</option>
                        </select>
                        <select class="cirrusly-sale-rounding" style="max-width: 100px;">
                            <option value="99">.99</option>
                            <option value="50">.50</option>
                            <option value="nearest_5">Nearest $5</option>
                            <option value="exact">Exact</option>
                        </select>
                    </div>
                </div>
                
                <!-- Regular Price Strategy Card -->
                <div class="cirrusly-pricing-card">
                    <h5>
                        <span class="dashicons dashicons-money-alt" style="color: #2271b1;"></span>
                        Regular Price
                    </h5>
                    <div class="cirrusly-strategy-group">
                        <select class="cirrusly-tool-reg">
                            <option value="">Choose Strategy</option>
                            <optgroup label="From MSRP">
                                <option value="msrp_exact">Match MSRP</option>
                                <option value="msrp_sub_05">5% Below MSRP</option>
                            </optgroup>
                            <optgroup label="From Cost + Margin">
                                <option value="margin_15">15% Margin</option>
                                <option value="margin_20">20% Margin</option>
                                <option value="margin_30">30% Margin</option>
                            </optgroup>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Shipping Scenario Matrix -->
            <div class="cirrusly-scenario-matrix">
                <div class="cirrusly-scenario-header">
                    <span class="dashicons dashicons-airplane" style="font-size: 16px;"></span>
                    Shipping Method Profitability
                </div>
                <div class="cirrusly-shipping-matrix">
                    <!-- Populated by JavaScript -->
                </div>
            </div>
            
            <?php if ( ! empty( $gmc_data ) ) : ?>
            <!-- GMC Insights (Pro Plus) -->
            <div class="cirrusly-gmc-insights">
                <h5>
                    <span class="dashicons dashicons-google" style="font-size: 16px;"></span>
                    Google Merchant Center Insights
                </h5>
                <div class="cirrusly-gmc-metrics">
                    <?php if ( isset( $gmc_data['clicks'] ) ) : ?>
                    <div class="cirrusly-gmc-metric">
                        <div class="cirrusly-gmc-metric-label">Clicks (30d)</div>
                        <div class="cirrusly-gmc-metric-value"><?php echo esc_html( number_format( $gmc_data['clicks'] ) ); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ( isset( $gmc_data['impressions'] ) ) : ?>
                    <div class="cirrusly-gmc-metric">
                        <div class="cirrusly-gmc-metric-label">Impressions (30d)</div>
                        <div class="cirrusly-gmc-metric-value"><?php echo esc_html( number_format( $gmc_data['impressions'] ) ); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ( isset( $gmc_data['ctr'] ) ) : ?>
                    <div class="cirrusly-gmc-metric">
                        <div class="cirrusly-gmc-metric-label">CTR</div>
                        <div class="cirrusly-gmc-metric-value"><?php echo esc_html( number_format( $gmc_data['ctr'], 2 ) ); ?>%</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ( isset( $gmc_data['price_competitiveness'] ) ) : ?>
                    <div class="cirrusly-gmc-metric">
                        <div class="cirrusly-gmc-metric-label">Price Competitiveness</div>
                        <div class="cirrusly-gmc-metric-value" style="color: <?php echo esc_attr( $gmc_data['price_color'] ?? '#2271b1' ); ?>">
                            <?php echo esc_html( ucfirst( $gmc_data['price_competitiveness'] ) ); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Retrieve GMC analytics data for a specific product SKU.
     *
     * @param string $sku Product SKU
     * @return array GMC insights data
     */
    private function get_gmc_product_insights( $sku ) {
        $gmc_data = array();
        
        // Check for cached analytics data
        $analytics_option = get_option( 'cirrusly_gmc_analytics_daily_' . wp_date( 'Y-m-d' ), array() );
        
        if ( ! empty( $analytics_option ) && is_array( $analytics_option ) ) {
            foreach ( $analytics_option as $product_data ) {
                if ( isset( $product_data['offer_id'] ) && $product_data['offer_id'] === $sku ) {
                    $gmc_data = array(
                        'clicks' => $product_data['clicks'] ?? 0,
                        'impressions' => $product_data['impressions'] ?? 0,
                        'ctr' => ( isset( $product_data['impressions'], $product_data['clicks'] ) && $product_data['impressions'] > 0 )
                            ? ( $product_data['clicks'] / $product_data['impressions'] ) * 100
                            : 0,
                        'price_competitiveness' => $product_data['price_bucket'] ?? 'unknown',
                        'price_color' => $this->get_price_competitiveness_color( $product_data['price_bucket'] ?? '' )
                    );
                    break;
                }
            }
        }
        
        return $gmc_data;
    }
    
    /**
     * Get color code for price competitiveness indicator.
     *
     * @param string $bucket Price bucket from GMC (low/medium/high)
     * @return string CSS color value
     */
    private function get_price_competitiveness_color( $bucket ) {
        $colors = array(
            'low' => '#00a32a',      // Green - competitive pricing
            'medium' => '#dba617',   // Amber - average pricing
            'high' => '#d63638',     // Red - expensive vs competitors
        );
        
        return $colors[ strtolower( $bucket ) ] ?? '#646970';
    }

public function pe_save_simple( $post_id ) {
        // Verify nonce for security (This covers all $_POST access below)
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-post_' . $post_id ) ) {
            return;
        }

        // [Security] Nonce verified above, comments removed.
        if ( isset( $_POST['_cogs_total_value'] ) ) update_post_meta( $post_id, '_cogs_total_value', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_cogs_total_value'] ) ) ) );
        if ( isset( $_POST['_cirrusly_est_shipping'] ) ) update_post_meta( $post_id, '_cirrusly_est_shipping', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_cirrusly_est_shipping'] ) ) ) );

        // MAP field save - Using cirrusly_map_price_input to avoid WooCommerce interference
        if ( isset( $_POST['cirrusly_map_price_input'] ) ) {
            $map_value = sanitize_text_field( wp_unslash( $_POST['cirrusly_map_price_input'] ) );
            if ( ! empty( $map_value ) ) {
                $map_formatted = wc_format_decimal( $map_value );
                // Validate that it's a positive number
                if ( is_numeric( $map_formatted ) && $map_formatted >= 0 ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "Cirrusly MAP Save - Raw: {$map_value}, Formatted: {$map_formatted}, Product ID: {$post_id}" );
                    }
                    update_post_meta( $post_id, '_cirrusly_map_price', $map_formatted );
                } else {
                    // Invalid value - log warning and don't save
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "Cirrusly MAP Save - Invalid value rejected: {$map_value}, Product ID: {$post_id}" );
                    }
                }
            } else {
                // Field was submitted but empty - clear the meta
                delete_post_meta( $post_id, '_cirrusly_map_price' );
            }
        }

        if ( isset( $_POST['_alg_msrp'] ) ) update_post_meta( $post_id, '_alg_msrp', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_alg_msrp'] ) ) ) );
        if ( isset( $_POST['_auto_pricing_min_price'] ) ) update_post_meta( $post_id, '_auto_pricing_min_price', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_auto_pricing_min_price'] ) ) ) );

        // Save countdown timer checkbox
        if ( isset( $_POST['_cirrusly_enable_countdown'] ) && $_POST['_cirrusly_enable_countdown'] === 'yes' ) {
            update_post_meta( $post_id, '_cirrusly_enable_countdown', 'yes' );
        } else {
            delete_post_meta( $post_id, '_cirrusly_enable_countdown' );
        }

        // Save Sale Timer End datetime with validation (consistent with variations)
        if ( isset( $_POST['_cirrusly_sale_end'] ) ) {
            $raw_datetime = sanitize_text_field( wp_unslash( $_POST['_cirrusly_sale_end'] ) );
            $validated_datetime = $this->validate_datetime( $raw_datetime, $post_id );

            // Only save if validation passed (returns string) or if empty (to allow clearing)
            if ( $validated_datetime !== false ) {
                update_post_meta( $post_id, '_cirrusly_sale_end', $validated_datetime );
            }
            // If validation fails, error notice is queued by validate_datetime() and value is not saved
        }

        $this->schedule_gmc_sync( $post_id );
    }

    public function pe_save_variable( $vid, $i ) {
        // Verify nonce for security
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-post_' . get_post_field( 'post_parent', $vid ) ) ) {
            return;
        }

        // [Security] Nonce verified above, comments removed.
        if ( isset( $_POST['_cogs_total_value'] ) && is_array( $_POST['_cogs_total_value'] ) && isset( $_POST['_cogs_total_value'][$i] ) ) {
            update_post_meta( $vid, '_cogs_total_value', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_cogs_total_value'][$i] ) ) ) );
        }
        if ( isset( $_POST['_cirrusly_est_shipping'] ) && is_array( $_POST['_cirrusly_est_shipping'] ) && isset( $_POST['_cirrusly_est_shipping'][$i] ) ) {
            update_post_meta( $vid, '_cirrusly_est_shipping', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_cirrusly_est_shipping'][$i] ) ) ) );
        }

        // MAP field save - Using cirrusly_map_price_input to avoid WooCommerce interference
        if ( isset( $_POST['cirrusly_map_price_input'] ) && is_array( $_POST['cirrusly_map_price_input'] ) &&
             isset( $_POST['cirrusly_map_price_input'][$i] ) ) {
            $map_value = sanitize_text_field( wp_unslash( $_POST['cirrusly_map_price_input'][$i] ) );
            if ( ! empty( $map_value ) ) {
                $map_formatted = wc_format_decimal( $map_value );
                // Validate that it's a positive number
                if ( is_numeric( $map_formatted ) && $map_formatted >= 0 ) {
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "Cirrusly MAP Save (Variation) - Raw: {$map_value}, Formatted: {$map_formatted}, Variation ID: {$vid}" );
                    }
                    update_post_meta( $vid, '_cirrusly_map_price', $map_formatted );
                } else {
                    // Invalid value - log warning and don't save
                    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                        error_log( "Cirrusly MAP Save (Variation) - Invalid value rejected: {$map_value}, Variation ID: {$vid}" );
                    }
                }
            } else {
                // Field was submitted but empty - clear the meta
                delete_post_meta( $vid, '_cirrusly_map_price' );
            }
        }
        if ( isset( $_POST['_alg_msrp'] ) && is_array( $_POST['_alg_msrp'] ) && isset( $_POST['_alg_msrp'][$i] ) ) {
            update_post_meta( $vid, '_alg_msrp', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_alg_msrp'][$i] ) ) ) );
        }
        if ( isset( $_POST['_auto_pricing_min_price'] ) && is_array( $_POST['_auto_pricing_min_price'] ) && isset( $_POST['_auto_pricing_min_price'][$i] ) ) {
            update_post_meta( $vid, '_auto_pricing_min_price', wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['_auto_pricing_min_price'][$i] ) ) ) );
        }

        // Validate and save Sale Timer End datetime with strict format validation
        if ( isset( $_POST['_cirrusly_sale_end'] ) && is_array( $_POST['_cirrusly_sale_end'] ) && isset( $_POST['_cirrusly_sale_end'][$i] ) ) {
            $raw_datetime = sanitize_text_field( wp_unslash( $_POST['_cirrusly_sale_end'][$i] ) );
            $validated_datetime = $this->validate_datetime( $raw_datetime, $vid );

            // Only save if validation passed (returns string) or if empty (to allow clearing)
            if ( $validated_datetime !== false ) {
                update_post_meta( $vid, '_cirrusly_sale_end', $validated_datetime );
            }
            // If validation fails, error notice is queued by validate_datetime() and value is not saved
        }

        $this->schedule_gmc_sync( $vid );
    }

    private function schedule_gmc_sync( $product_id ) {
        // Ensure Pro is active before scheduling
        if ( Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            wp_clear_scheduled_hook( 'cirrusly_commerce_gmc_sync', array( $product_id ) );
            wp_schedule_single_event( time() + 60, 'cirrusly_commerce_gmc_sync', array( $product_id ) );
        }
    }

    /**
     * Validate and normalize datetime string
     *
     * @param string $datetime_string The datetime string to validate
     * @param int    $product_id      Product ID for error messaging
     * @return string|false           Normalized datetime string in 'Y-m-d H:i:s' format or false if invalid
     */
    private function validate_datetime( $datetime_string, $product_id ) {
        // Empty values are allowed (clears the sale end date)
        if ( empty( $datetime_string ) ) {
            return '';
        }

        // Expected format from client: "YYYY-MM-DD HH:MM"
        // We'll accept with or without seconds
        $formats = array(
            'Y-m-d H:i:s',  // With seconds
            'Y-m-d H:i',    // Without seconds (primary format from JS)
        );

        $datetime_obj = false;
        foreach ( $formats as $format ) {
            // Create DateTime with strict parsing
            $datetime_obj = DateTime::createFromFormat( $format, $datetime_string );

            // Check if parsing was successful and no extra characters remain
            if ( $datetime_obj && $datetime_obj->format( $format ) === $datetime_string ) {
                break;
            }
            $datetime_obj = false;
        }

        if ( ! $datetime_obj ) {
            // Validation failed - store error for admin notice
            $product_title = get_the_title( $product_id );
            $error_message = sprintf(
                /* translators: 1: product title, 2: invalid datetime value */
                __( 'Invalid Sale Timer End date format for product "%1$s". Expected format: YYYY-MM-DD HH:MM (e.g., 2024-12-31 23:59). Value "%2$s" was not saved.', 'cirrusly-commerce' ),
                $product_title,
                esc_html( $datetime_string )
            );

            // Store error in transient (expires in 60 seconds)
            set_transient( 'cirrusly_datetime_error_' . get_current_user_id(), $error_message, 60 );

            return false;
        }

        // Convert to WordPress site timezone if needed
        try {
            $timezone = new DateTimeZone( wp_timezone_string() );
            $datetime_obj->setTimezone( $timezone );
        } catch ( Exception $e ) {
            // If timezone conversion fails, use the datetime as-is
            error_log( 'Cirrusly Commerce: Timezone conversion failed - ' . $e->getMessage() );
        }

        // Normalize to standard storage format with seconds
        return $datetime_obj->format( 'Y-m-d H:i:s' );
    }

    /**
     * Display datetime validation errors as admin notices
     */
    public function display_datetime_validation_errors() {
        $error = get_transient( 'cirrusly_datetime_error_' . get_current_user_id() );
        if ( $error ) {
            echo '<div class="notice notice-error is-dismissible"><p>' . wp_kses_post( $error ) . '</p></div>';
            delete_transient( 'cirrusly_datetime_error_' . get_current_user_id() );
        }
    }
}