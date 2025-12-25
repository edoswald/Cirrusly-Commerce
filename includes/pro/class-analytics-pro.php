<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_Analytics_Pro {

    /**
     * Register admin hooks required for the analytics feature.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_analytics_page' ), 20 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'cirrusly_gmc_daily_scan', array( $this, 'capture_daily_gmc_snapshot' ), 20 );
        
        // Schedule weekly analytics summary email
        add_action( 'init', array( $this, 'schedule_weekly_email' ) );
        add_action( 'cirrusly_weekly_analytics_email', array( $this, 'send_weekly_summary' ) );
    }

    /**
     * Register the submenu.
     */
    public function register_analytics_page() {
        add_submenu_page(
            'cirrusly-commerce',
            'Pro Plus Analytics',
            'Analytics',
            'manage_woocommerce',
            'cirrusly-analytics',
            array( $this, 'render_analytics_view' )
        );
    }

    /**
     * Enqueue Chart.js for analytics charts.
     */
    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'cirrusly-analytics' ) === false ) {
            return;
        }

        wp_enqueue_script( 'cirrusly-chartjs', CIRRUSLY_COMMERCE_URL . 'assets/js/vendor/chart.umd.min.js', array(), '4.4.0', true );
    }

    /**
     * Renders the Pro Plus Analytics admin page.
     */
    public function render_analytics_view() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'cirrusly-commerce' ) );
        }

        $params = self::get_filter_params();
        $days = $params['days'];
        $selected_statuses = $params['statuses'];

        // Use WooCommerce default statuses only (simplified filter UX)
        $all_statuses = array(
            'wc-pending'    => 'Pending',
            'wc-processing' => 'Processing',
            'wc-on-hold'    => 'On Hold',
            'wc-completed'  => 'Completed/Shipped', // Covers renamed statuses
            'wc-cancelled'  => 'Cancelled',
            'wc-refunded'   => 'Refunded',
            'wc-failed'     => 'Failed'
        );

        // Handle Force Refresh (clears cache only, doesn't reset import)
        if ( isset( $_GET['cirrusly_refresh'] ) && check_admin_referer( 'cirrusly_refresh_analytics' ) ) {
            update_option( 'cirrusly_analytics_cache_version', time(), false );
            
            wp_safe_redirect( remove_query_arg( array( 'cirrusly_refresh', '_wpnonce' ) ) );
            exit;
        }

        // Standard Header (Reverted)
        if ( class_exists( 'Cirrusly_Commerce_Core' ) ) {
            Cirrusly_Commerce_Core::render_page_header( 'Pro Plus Analytics' );
        } else {
            echo '<div class="wrap"><h1>Analytics</h1>';
        }
        
        // GMC Analytics Import UI
        $import_completed = get_option( 'cirrusly_gmc_analytics_imported' );
        if ( class_exists( 'Cirrusly_Commerce_GMC_Analytics' ) && ! $import_completed ) {
            // Generate nonces for AJAX security
            $start_nonce = wp_create_nonce( 'cirrusly_start_import' );
            $progress_nonce = wp_create_nonce( 'cirrusly_import_progress' );
            
            echo '<div id="cirrusly-gmc-import-banner" class="notice notice-info" style="position: relative; padding: 15px 20px; margin: 15px 0; border-left: 4px solid #2271b1;">';
            echo '<p style="margin: 0 0 10px 0;"><strong>Analytics Available!</strong> Import 90 days of Google Merchant Center data to see click-through rates, conversions, and price competitiveness insights.</p>';
            echo '<button type="button" id="cirrusly-start-gmc-import" class="button button-primary"><span class="dashicons dashicons-download" style="line-height:inherit;"></span> Start Import</button>';
            echo '<div id="cirrusly-import-progress" style="display:none; margin-top: 15px;">';
            echo '<div style="background: #f0f0f1; border-radius: 10px; height: 20px; overflow: hidden; margin-bottom: 10px;"><div id="cirrusly-import-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #2271b1, #00a32a); transition: width 0.5s ease;"></div></div>';
            echo '<p id="cirrusly-import-status" style="margin: 0; font-size: 13px; color: #646970;">Preparing import...</p>';
            echo '</div></div>';
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#cirrusly-start-gmc-import').on('click', function() {
                    var $btn = $(this);
                    $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: rotation 1s infinite linear; line-height:inherit;"></span> Starting...');
                    $('#cirrusly-import-progress').slideDown();
                    
                    // Show timing guidance
                    $('#cirrusly-import-status').html('<span style="color: #2271b1; font-weight: 600;">⏳ Import started!</span> Data may take several minutes to appear. Small stores: ~30 seconds, Larger stores: 5-10 minutes.');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: { 
                            action: 'cirrusly_start_gmc_import',
                            nonce: '<?php echo esc_js( $start_nonce ); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                pollImportProgress();
                            } else {
                                alert('Failed to start import: ' + (response.data ? response.data.message : 'Unknown error'));
                                $btn.prop('disabled', false).html('<span class="dashicons dashicons-download" style="line-height:inherit;"></span> Start Import');
                                $('#cirrusly-import-progress').slideUp();
                            }
                        }
                    });
                });
                
                function pollImportProgress() {
                    var pollInterval = setInterval(function() {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: { 
                                action: 'cirrusly_import_progress',
                                nonce: '<?php echo esc_js( $progress_nonce ); ?>'
                            },
                            success: function(response) {
                                if (response.success && response.data) {
                                    var progress = response.data;
                                    var percentage = (progress.current_batch / progress.total_batches) * 100;
                                    
                                    $('#cirrusly-import-progress-bar').css('width', percentage + '%');
                                    $('#cirrusly-import-status').text('Batch ' + progress.current_batch + '/' + progress.total_batches + ' • ' + progress.products_processed + ' products processed');
                                    
                                    if (progress.status === 'completed') {
                                        clearInterval(pollInterval);
                                        $('#cirrusly-import-progress-bar').css('width', '100%');
                                        $('#cirrusly-import-status').html('<span style="color: #00a32a; font-weight: 600;">✓ Import Complete!</span> Reloading page...');
                                        setTimeout(function() { location.reload(); }, 2000);
                                    } else if (progress.status === 'error') {
                                        clearInterval(pollInterval);
                                        $('#cirrusly-import-status').html('<span style="color: #d63638; font-weight: 600;">✗ Import Failed:</span> ' + (progress.errors[0] || 'Unknown error'));
                                        $('#cirrusly-start-gmc-import').prop('disabled', false).html('<span class="dashicons dashicons-download" style="line-height:inherit;"></span> Retry Import');
                                    }
                                }
                            }
                        });
                    }, 3000);
                }
            });
            </script>
            <style>@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }</style>
            <?php
        }
        
        // Data Retrieval
        $data = self::get_pnl_data( $days, $selected_statuses );
        if ( empty( $selected_statuses ) ) {
            $selected_statuses = $data['statuses_used'];
        }
        $velocity = self::get_inventory_velocity();
        $refresh_url = wp_nonce_url( add_query_arg( array( 'cirrusly_refresh' => '1' ) ), 'cirrusly_refresh_analytics' );
        
        // JS Data
        $gmc_history = get_option( 'cirrusly_gmc_history', array() );
        $perf_history_json = wp_json_encode( $data['history'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
        $gmc_history_json  = wp_json_encode( $gmc_history, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
        
        $cost_breakdown = array(
            'COGS'     => $data['cogs'],
            'Shipping' => $data['shipping'],
            'Fees'     => $data['fees'],
            'Refunds'  => $data['refunds']
        );
        $cost_json = wp_json_encode( array_values($cost_breakdown) );
        $cost_labels_json = wp_json_encode( array_keys($cost_breakdown) );

        $script = "
        document.addEventListener('DOMContentLoaded', function() {
            // Currency formatter helper
            const formatCurrency = function(value) {
                return '$' + parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            };

            // 1. Performance Chart
            const perfCtx = document.getElementById('performanceChart');
            if (perfCtx) {
                const perfData = {$perf_history_json};
                const dates = Object.keys(perfData);
                const sales = dates.map(d => parseFloat(perfData[d].revenue));
                const costs = dates.map(d => parseFloat(perfData[d].costs));
                const profit = dates.map(d => parseFloat(perfData[d].profit));

                new Chart(perfCtx, {
                    type: 'bar',
                    data: {
                        labels: dates,
                        datasets: [
                            { label: 'Net Profit', data: profit, type: 'line', borderColor: '#00a32a', borderWidth: 2, pointRadius: 1, fill: false, tension: 0.1, order: 1 },
                            { label: 'Net Sales', data: sales, backgroundColor: '#2271b1', barPercentage: 0.6, order: 2 },
                            { label: 'Costs', data: costs, backgroundColor: '#d63638', barPercentage: 0.6, order: 3 }
                        ]
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        interaction: { mode: 'index', intersect: false }, 
                        plugins: { 
                            legend: { position: 'top', align: 'end', labels: { usePointStyle: true, boxWidth: 6 } },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) { label += ': '; }
                                        label += formatCurrency(context.parsed.y);
                                        return label;
                                    }
                                }
                            }
                        }, 
                        scales: { 
                            y: { 
                                beginAtZero: false,
                                grid: { borderDash: [2, 2] },
                                ticks: {
                                    callback: function(value) {
                                        return formatCurrency(value);
                                    }
                                }
                            }, 
                            x: { grid: { display: false } } 
                        } 
                    }
                });
            }

            // 2. Cost Breakdown (Doughnut)
            const costCtx = document.getElementById('costBreakdownChart');
            if (costCtx) {
                new Chart(costCtx, {
                    type: 'doughnut',
                    data: {
                        labels: {$cost_labels_json},
                        datasets: [{
                            data: {$cost_json},
                            backgroundColor: ['#d63638', '#dba617', '#a7aaad', '#1d2327'],
                            borderWidth: 0
                        }]
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { 
                            legend: { position: 'right', labels: { boxWidth: 10 } },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) { label += ': '; }
                                        label += formatCurrency(context.parsed);
                                        return label;
                                    }
                                }
                            }
                        }, 
                        cutout: '65%' 
                    }
                });
            }

            // 3. Refunds Trend (Small Line)
            const refCtx = document.getElementById('refundsTrendChart');
            if (refCtx) {
                const h = {$perf_history_json};
                const dates = Object.keys(h);
                const refunds = dates.map(d => parseFloat(h[d].refunds || 0));
                new Chart(refCtx, {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{ label: 'Refunds', data: refunds, borderColor: '#646970', borderWidth: 1.5, fill: true, backgroundColor: 'rgba(100,105,112,0.1)', tension: 0.3, pointRadius: 0 }]
                    },
                    options: { 
                        responsive: true, 
                        maintainAspectRatio: false, 
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return 'Refunds: ' + formatCurrency(context.parsed.y);
                                    }
                                }
                            }
                        }, 
                        scales: { 
                            y: { beginAtZero: true, ticks: { display: false }, grid: { display: false } }, 
                            x: { display: false } 
                        } 
                    }
                });
            }

            // 4. GMC Trend
            const gmcCtx = document.getElementById('gmcTrendChart');
            if (gmcCtx) {
                const history = {$gmc_history_json};
                const labels = Object.keys(history).slice(-30);
                const criticalData = labels.map(d => history[d].critical || 0);
                new Chart(gmcCtx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{ label: 'Issues', data: criticalData, borderColor: '#d63638', backgroundColor: 'rgba(214, 54, 56, 0.05)', fill: true, tension: 0.3, pointRadius: 2 }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
                });
            }

            // Status Pill Logic
            const formEl = document.getElementById('cirrusly-analytics-form');
            document.querySelectorAll('.cirrusly-status-pill input[type=\"checkbox\"]').forEach(cb => {
                cb.addEventListener('change', function() {
                    const pill = this.closest('.cirrusly-status-pill');
                    if (pill) pill.classList.toggle('is-active', this.checked);
                    if (formEl) formEl.submit();
                });
            });
        });";

        wp_add_inline_script( 'cirrusly-chartjs', $script );
        ?>
        
        <div class="wrap cirrusly-analytics-wrapper">

            <form method="get" action="" id="cirrusly-analytics-form">
                <input type="hidden" name="page" value="cirrusly-analytics">
                
                <!-- Gradient Toolbar -->
                <div class="cirrusly-analytics-toolbar">
                    <div class="cirrusly-toolbar-inner">
                        <div class="cirrusly-toolbar-left">
                            <span class="cirrusly-beta-tag">BETA</span>
                            <select name="period" class="cirrusly-period-select" onchange="this.form.submit()">
                                <option value="7" <?php selected( $days, 7 ); ?>>Last 7 Days</option>
                                <option value="30" <?php selected( $days, 30 ); ?>>Last 30 Days</option>
                                <option value="90" <?php selected( $days, 90 ); ?>>Last 90 Days</option>
                                <option value="180" <?php selected( $days, 180 ); ?>>Last 6 Months</option>
                                <option value="365" <?php selected( $days, 365 ); ?>>Last Year</option>
                            </select>
                            <span class="cirrusly-order-count">Analyzing <strong><?php echo number_format(intval($data['count'])); ?></strong> orders</span>
                        </div>
                        <a href="<?php echo esc_url( $refresh_url ); ?>" class="cirrusly-btn-secondary" title="Re-fetch data from database">
                            <span class="dashicons dashicons-update"></span> Refresh Data
                        </a>
                    </div>
                </div>

                <!-- Status Filter Pills -->
                <div class="cirrusly-status-filter">
                    <span class="cirrusly-filter-label">Filter by Order Status</span>
                    <div class="cirrusly-status-pills">
                        <?php foreach ( $all_statuses as $slug => $label ) : 
                            $is_active = in_array( $slug, $selected_statuses ) || in_array( str_replace('wc-','',$slug), $selected_statuses );
                        ?>
                            <label class="cirrusly-status-pill <?php echo $is_active ? 'is-active' : ''; ?>">
                                <input type="checkbox" name="cirrusly_statuses[]" value="<?php echo esc_attr($slug); ?>" <?php checked( $is_active ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>

            <div class="cirrusly-dash-grid four-cols">
                
                <div class="cirrusly-dash-card" style="--card-accent: #2271b1; --card-accent-end: #1557a0;">
                    <div class="cirrusly-card-head">
                        <span>Net Sales</span> 
                        <span class="dashicons dashicons-chart-bar"></span>
                    </div>
                    <div class="cirrusly-stat-big" style="color: #2271b1;"><?php echo wp_kses_post( wc_price( $data['revenue'] ) ); ?></div>
                    <div class="cirrusly-card-footer">Total Revenue</div>
                </div>

                <div class="cirrusly-dash-card" style="--card-accent: #d63638; --card-accent-end: #a72828;">
                    <div class="cirrusly-card-head">
                        <span>Total Costs</span> 
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <div class="cirrusly-stat-big" style="color:#d63638;"><?php echo wp_kses_post( wc_price( $data['total_costs'] ) ); ?></div>
                    <div class="cirrusly-card-footer">All Expenses</div>
                </div>

                <div class="cirrusly-dash-card" style="--card-accent: #00a32a; --card-accent-end: #007a20;">
                    <div class="cirrusly-card-head">
                        <span>Net Profit</span> 
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="cirrusly-stat-big" style="color:#00a32a;"><?php echo wp_kses_post( wc_price( $data['net_profit'] ) ); ?></div>
                    <div class="cirrusly-card-footer"><?php echo esc_html( number_format( $data['margin'], 1 ) ); ?>% Profit Margin</div>
                </div>

                <div class="cirrusly-dash-card" style="--card-accent: #f0ad4e; --card-accent-end: #d89a3b;">
                    <div class="cirrusly-card-head">
                        <span>Returns</span> 
                        <span class="dashicons dashicons-undo"></span>
                    </div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin: 10px 0;">
                        <div class="cirrusly-stat-big" style="color:#f0ad4e; font-size:28px; margin: 0;"><?php echo wp_kses_post( wc_price( $data['refunds'] ) ); ?></div>
                        <div style="width: 80px; height: 45px;"><canvas id="refundsTrendChart"></canvas></div>
                    </div>
                    <div class="cirrusly-card-footer">Refund Volume</div>
                </div>

            </div>

            <!-- Main Performance Chart -->
            <div class="cirrusly-chart-card" style="margin-bottom: 35px;">
                <div class="cirrusly-chart-header">
                    <div>
                        <h2>
                            <span class="dashicons dashicons-chart-line" style="font-size: 20px;"></span>
                            Performance Overview
                        </h2>
                        <span class="cirrusly-chart-subtitle">Revenue vs. Costs vs. Net Profit Trend</span>
                    </div>
                </div>
                <div class="cirrusly-chart-body">
                    <div style="width: 100%; height: 380px;">
                        <canvas id="performanceChart"></canvas>
                    </div>
                </div>
            </div>

            <?php
            // GMC Analytics Section (Only if import completed)
            if ( class_exists( 'Cirrusly_Commerce_GMC_Analytics' ) && get_option( 'cirrusly_gmc_analytics_imported' ) ) {
                $date_range = array(
                    'start' => date( 'Y-m-d', strtotime( '-' . $days . ' days' ) ),
                    'end'   => date( 'Y-m-d' )
                );
                $gmc_data = Cirrusly_Commerce_GMC_Analytics::get_gmc_analytics_compiled( $date_range );
                
                if ( ! empty( $gmc_data ) ) {
                    // Calculate aggregate metrics
                    $total_clicks = 0;
                    $total_impressions = 0;
                    $total_conversions = 0;
                    $products_above_benchmark = 0;
                    $total_price_diff = 0;
                    $price_diff_count = 0;
                    
                    foreach ( $gmc_data as $product_id => $product_data ) {
                        $total_clicks += isset( $product_data['gmc']['clicks'] ) ? $product_data['gmc']['clicks'] : 0;
                        $total_impressions += isset( $product_data['gmc']['impressions'] ) ? $product_data['gmc']['impressions'] : 0;
                        $total_conversions += isset( $product_data['gmc']['conversions'] ) ? $product_data['gmc']['conversions'] : 0;
                        
                        if ( isset( $product_data['pricing']['competitiveness'] ) && $product_data['pricing']['competitiveness'] === 'too_high' ) {
                            $products_above_benchmark++;
                            if ( isset( $product_data['pricing']['price_diff_pct'] ) ) {
                                $total_price_diff += abs( $product_data['pricing']['price_diff_pct'] );
                                $price_diff_count++;
                            }
                        }
                    }
                    
                    $avg_ctr = ( $total_impressions > 0 ) ? round( ( $total_clicks / $total_impressions ) * 100, 2 ) : 0;
                    $conversion_rate = ( $total_clicks > 0 ) ? round( ( $total_conversions / $total_clicks ) * 100, 2 ) : 0;
                    $avg_price_diff = ( $price_diff_count > 0 ) ? round( $total_price_diff / $price_diff_count, 1 ) : 0;
                    
                    echo '<h2 style="margin: 35px 0 20px 0; font-size: 20px; font-weight: 600; color: #1d2327;"><span class="dashicons dashicons-google" style="color: #4285f4;"></span> Google Merchant Center Analytics</h2>';
                    
                    // GMC Stats Grid
                    echo '<div class="cirrusly-dash-grid four-cols" style="margin-bottom: 25px;">';
                    
                    echo '<div class="cirrusly-dash-card" style="--card-accent: #4285f4; --card-accent-end: #3367d6;">';
                    echo '<div class="cirrusly-card-head"><span>Impressions</span><span class="dashicons dashicons-visibility"></span></div>';
                    echo '<div class="cirrusly-stat-big" style="color: #4285f4;">' . number_format( $total_impressions ) . '</div>';
                    echo '<div class="cirrusly-card-footer">Google Shopping Product Views</div></div>';
                    
                    echo '<div class="cirrusly-dash-card" style="--card-accent: #ea4335; --card-accent-end: #c5221f;">';
                    echo '<div class="cirrusly-card-head"><span>Clicks</span><span class="dashicons dashicons-admin-links"></span></div>';
                    echo '<div class="cirrusly-stat-big" style="color: #ea4335;">' . number_format( $total_clicks ) . '</div>';
                    echo '<div class="cirrusly-card-footer">' . esc_html( $avg_ctr ) . '% Click-Through Rate</div></div>';
                    
                    echo '<div class="cirrusly-dash-card" style="--card-accent: #34a853; --card-accent-end: #0f9d58;">';
                    echo '<div class="cirrusly-card-head"><span>Conversions</span><span class="dashicons dashicons-yes-alt"></span></div>';
                    echo '<div class="cirrusly-stat-big" style="color: #34a853;">' . number_format( $total_conversions ) . '</div>';
                    echo '<div class="cirrusly-card-footer">' . esc_html( $conversion_rate ) . '% Conversion Rate</div></div>';
                    
                    echo '<div class="cirrusly-dash-card" style="--card-accent: #fbbc04; --card-accent-end: #f9ab00;">';
                    echo '<div class="cirrusly-card-head"><span>Price Alerts</span><span class="dashicons dashicons-warning"></span></div>';
                    echo '<div class="cirrusly-stat-big" style="color: #fbbc04;">' . number_format( $products_above_benchmark ) . '</div>';
                    echo '<div class="cirrusly-card-footer">' . ( $avg_price_diff > 0 ? '+' . esc_html( $avg_price_diff ) . '% avg' : 'Competitive' ) . '</div></div>';
                    
                    echo '</div>';
                    
                    // GMC Charts Row
                    echo '<div class="cirrusly-dash-grid" style="grid-template-columns: 2fr 1fr; margin-bottom: 35px;">';
                    
                    // GMC Traffic Funnel Chart
                    echo '<div class="cirrusly-chart-card">';
                    echo '<div class="cirrusly-chart-header"><div><h2><span class="dashicons dashicons-chart-bar"></span> GMC Traffic Funnel</h2>';
                    echo '<span class="cirrusly-chart-subtitle">Impressions → Clicks → Conversions</span></div></div>';
                    echo '<div class="cirrusly-chart-body"><div style="width: 100%; height: 280px;"><canvas id="gmcFunnelChart"></canvas></div></div></div>';
                    
                    // Price Competitiveness Alert Card
                    echo '<div class="cirrusly-chart-card">';
                    echo '<div class="cirrusly-chart-header"><div><h2><span class="dashicons dashicons-tag"></span> Price Competitiveness</h2>';
                    echo '<span class="cirrusly-chart-subtitle">vs. Google Benchmark</span></div></div>';
                    echo '<div class="cirrusly-chart-body" style="padding: 20px;">';
                    
                    if ( $products_above_benchmark > 0 ) {
                        echo '<div style="background: #fff3cd; border-left: 4px solid #fbbc04; padding: 15px; border-radius: 4px; margin-bottom: 15px;">';
                        echo '<p style="margin: 0 0 10px 0; font-weight: 600; color: #856404;">⚠️ ' . $products_above_benchmark . ' products priced above market</p>';
                        echo '<p style="margin: 0; font-size: 13px; color: #856404;">Average: +' . $avg_price_diff . '% vs. Google benchmark</p></div>';
                        
                        // List top 3 overpriced products
                        $overpriced = array();
                        foreach ( $gmc_data as $product_id => $product_data ) {
                            if ( isset( $product_data['pricing']['competitiveness'] ) && $product_data['pricing']['competitiveness'] === 'too_high' ) {
                                $overpriced[] = array(
                                    'id' => $product_id,
                                    'diff' => isset( $product_data['pricing']['price_diff_pct'] ) ? abs( $product_data['pricing']['price_diff_pct'] ) : 0
                                );
                            }
                        }
                        uasort( $overpriced, function( $a, $b ) { return $b['diff'] - $a['diff']; } );
                        $top_3 = array_slice( $overpriced, 0, 3, true );
                        
                        echo '<div style="font-size: 12px;">';
                        echo '<strong style="display: block; margin-bottom: 8px; color: #646970;">Top Overpriced:</strong>';
                        foreach ( $top_3 as $item ) {
                            $product = wc_get_product( $item['id'] );
                            if ( $product ) {
                                echo '<div style="margin-bottom: 5px;">';
                                echo '<a href="' . esc_url( get_edit_post_link( $item['id'] ) ) . '" target="_blank" style="color: #2271b1;">' . esc_html( $product->get_name() ) . '</a>';
                                echo ' <span style="color: #d63638; font-weight: 600;">+' . esc_html( number_format( $item['diff'], 1 ) ) . '%</span></div>';
                            }
                        }
                        echo '</div>';
                    } else {
                        echo '<div style="background: #d1f4d1; border-left: 4px solid #34a853; padding: 15px; border-radius: 4px;">';
                        echo '<p style="margin: 0; font-weight: 600; color: #0f5132;">✓ All products are competitively priced.</p>';
                        echo '<p style="margin: 5px 0 0 0; font-size: 13px; color: #0f5132;">No pricing alerts from Google</p></div>';
                    }
                    
                    echo '</div></div></div>';
                    
                    // Top GMC Products Table
                    echo '<div class="cirrusly-chart-card" style="margin-bottom: 35px;">';
                    echo '<div class="cirrusly-chart-header"><div><h2><span class="dashicons dashicons-star-filled"></span>Top Products on Google Shopping</h2>';
                    echo '<span class="cirrusly-chart-subtitle">By Conversion Performance</span></div></div>';
                    echo '<div class="cirrusly-chart-body" style="padding: 0;">';
                    echo '<table class="widefat striped" style="margin: 0; border: none;"><thead><tr>';
                    echo '<th style="padding: 12px 15px;">Product</th>';
                    echo '<th style="text-align: right; padding: 12px 15px;">Clicks</th>';
                    echo '<th style="text-align: right; padding: 12px 15px;">Conv.</th>';
                    echo '<th style="text-align: right; padding: 12px 15px;">Conv%</th>';
                    echo '<th style="text-align: right; padding: 12px 15px;">Value</th>';
                    echo '<th style="text-align: right; padding: 12px 15px;">ROAS</th></tr></thead><tbody>';
                    
                    // Sort by conversion rate
                    uasort( $gmc_data, function( $a, $b ) {
                        $a_rate = ( isset( $a['gmc']['clicks'] ) && $a['gmc']['clicks'] > 0 ) ? ( $a['gmc']['conversions'] / $a['gmc']['clicks'] ) : 0;
                        $b_rate = ( isset( $b['gmc']['clicks'] ) && $b['gmc']['clicks'] > 0 ) ? ( $b['gmc']['conversions'] / $b['gmc']['clicks'] ) : 0;
                        return $b_rate - $a_rate;
                    } );
                    
                    $top_10 = array_slice( array_filter( $gmc_data, function( $item ) {
                        return isset( $item['gmc']['clicks'] ) && $item['gmc']['clicks'] > 0;
                    } ), 0, 10, true );
                    
                    foreach ( $top_10 as $product_id => $product_data ) {
                        $product = wc_get_product( $product_id );
                        if ( ! $product ) continue;
                        
                        $clicks = isset( $product_data['gmc']['clicks'] ) ? $product_data['gmc']['clicks'] : 0;
                        $conversions = isset( $product_data['gmc']['conversions'] ) ? $product_data['gmc']['conversions'] : 0;
                        $conv_rate = ( $clicks > 0 ) ? round( ( $conversions / $clicks ) * 100, 1 ) : 0;
                        $value = isset( $product_data['gmc']['conversion_value'] ) ? $product_data['gmc']['conversion_value'] : 0;
                        $roas = isset( $product_data['roas'] ) ? $product_data['roas'] : 0;
                        
                        echo '<tr>';
                        echo '<td style="padding: 10px 15px;"><a href="' . esc_url( get_edit_post_link( $product_id ) ) . '" target="_blank" style="color: #2271b1; font-weight: 500;">' . esc_html( $product->get_name() ) . '</a></td>';
                        echo '<td style="text-align: right; padding: 10px 15px;">' . number_format( $clicks ) . '</td>';
                        echo '<td style="text-align: right; padding: 10px 15px;">' . number_format( $conversions ) . '</td>';
                        echo '<td style="text-align: right; padding: 10px 15px; font-weight: 600; color: #34a853;">' . esc_html( $conv_rate ) . '%</td>';
                        echo '<td style="text-align: right; padding: 10px 15px;">' . wc_price( $value ) . '</td>';
                        echo '<td style="text-align: right; padding: 10px 15px; font-weight: 600;' . ( $roas >= 2 ? ' color: #34a853;' : '' ) . '">' . esc_html( number_format( $roas, 2 ) ) . 'x</td>';
                        echo '</tr>';
                    }
                    
                    if ( empty( $top_10 ) ) {
                        echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #646970;">No Google Shopping traffic data is available for this period.</td></tr>';
                    }
                    
                    echo '</tbody></table></div></div>';
                    
                    // Add GMC Funnel Chart Script
                    $funnel_script = "
                    if (document.getElementById('gmcFunnelChart')) {
                        new Chart(document.getElementById('gmcFunnelChart'), {
                            type: 'bar',
                            data: {
                                labels: ['Impressions', 'Clicks', 'Conversions'],
                                datasets: [{
                                    label: 'GMC Traffic',
                                    data: [" . $total_impressions . ", " . $total_clicks . ", " . $total_conversions . "],
                                    backgroundColor: ['#4285f4', '#ea4335', '#34a853']
                                }]
                            },
                            options: {
                                indexAxis: 'y',
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: { display: false },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                return context.parsed.x.toLocaleString();
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: { beginAtZero: true, ticks: { callback: function(value) { return value.toLocaleString(); } } }
                                }
                            }
                        });
                    }";
                    wp_add_inline_script( 'cirrusly-chartjs', $funnel_script );
                }
            }
            ?>

            <!-- Three-Column Insight Grid -->
            <div class="cirrusly-insights-grid">
                
                <div class="cirrusly-chart-card">
                    <div class="cirrusly-chart-header">
                        <h2>
                            <span class="dashicons dashicons-chart-pie" style="font-size: 18px; color: #d63638;"></span>
                            Cost Breakdown
                        </h2>
                    </div>
                    <div class="cirrusly-chart-body">
                        <div style="height: 260px; display: flex; align-items: center; justify-content: center;">
                            <canvas id="costBreakdownChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="cirrusly-chart-card">
                    <div class="cirrusly-chart-header">
                        <h2>
                            <span class="dashicons dashicons-warning" style="font-size: 18px; color: #d63638;"></span>
                            Google Merchant Center Issues Trend
                        </h2>
                    </div>
                    <div class="cirrusly-chart-body">
                        <div style="height: 260px;">
                            <canvas id="gmcTrendChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="cirrusly-chart-card">
                    <div class="cirrusly-chart-header">
                        <h2>
                            <span class="dashicons dashicons-star-filled" style="font-size: 18px; color: #f0ad4e;"></span>
                            Top Performers
                        </h2>
                    </div>
                    <div style="padding: 0;">
                        <table class="cirrusly-top-performers-table wp-list-table widefat">
                            <tbody>
                                <?php 
                                $top_products = array_slice( $data['products'], 0, 5 ); 
                                if ( empty( $top_products ) ) {
                                    echo '<tr><td style="padding: 25px; color: #a7aaad; text-align: center; font-style: italic;">No product data available</td></tr>';
                                } else {
                                    foreach ( $top_products as $idx => $p ) {
                                        $bg = $idx % 2 === 0 ? '#fafafa' : '#ffffff';
                                        echo '<tr style="background: ' . $bg . ';">';
                                        echo '<td style="padding: 16px 20px;"><a href="' . esc_url( get_edit_post_link($p['id']) ) . '" style="font-weight: 600; text-decoration: none; color: #2271b1; display: flex; align-items: center; gap: 8px;"><span class="dashicons dashicons-tag" style="font-size: 16px;"></span>'. esc_html( mb_strimwidth($p['name'], 0, 32, '...') ) .'</a></td>';
                                        echo '<td style="text-align: right; font-weight: 700; color: #00a32a; padding: 16px 20px; font-size: 15px;">' . wp_kses_post( wc_price($p['net']) ) . '</td>';
                                        echo '</tr>';
                                    }
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Inventory Risk Alert -->
            <?php if ( ! empty( $velocity ) ) : ?>
            <div class="cirrusly-chart-card cirrusly-alert-card">
                <div class="cirrusly-chart-header" style="background: transparent; border-bottom: 3px solid #f0ad4e;">
                    <div>
                        <h2 style="color: #f0ad4e; display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-warning" style="font-size: 22px;"></span>
                            Inventory Risk Alert
                        </h2>
                        <span class="cirrusly-chart-subtitle">Products at risk of stockout within 14 days</span>
                    </div>
                </div>
                <table class="cirrusly-risk-table wp-list-table widefat" style="border: none; box-shadow: none; margin: 0;">
                    <thead>
                        <tr>
                            <th style="padding: 14px 20px; font-weight: 700; color: #646970; text-transform: uppercase; font-size: 11px; letter-spacing: 1px;">Product</th>
                            <th style="padding: 14px 20px; font-weight: 700; color: #646970; text-transform: uppercase; font-size: 11px; letter-spacing: 1px;">Current Stock</th>
                            <th style="padding: 14px 20px; font-weight: 700; color: #646970; text-transform: uppercase; font-size: 11px; letter-spacing: 1px;">Velocity</th>
                            <th style="padding: 14px 20px; font-weight: 700; color: #646970; text-transform: uppercase; font-size: 11px; letter-spacing: 1px;">Days Remaining</th>
                            <th style="padding: 14px 20px; font-weight: 700; color: #646970; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( array_slice($velocity, 0, 5) as $idx => $v ) : 
                            $bg = $idx % 2 === 0 ? '#ffffff' : '#fffbf7';
                            $is_critical = $v['days_left'] < 7;
                        ?>
                            <tr style="background: <?php echo $bg; ?>;">
                                <td style="padding: 16px 20px; border-bottom: 1px solid #f0f0f1;">
                                    <a href="<?php echo esc_url( get_edit_post_link($v['id']) ); ?>" style="font-weight: 600; color: #2271b1; text-decoration: none; display: flex; align-items: center; gap: 8px;">
                                        <span class="dashicons dashicons-products" style="font-size: 16px;"></span>
                                        <?php echo esc_html( mb_strimwidth($v['name'], 0, 45, '...') ); ?>
                                    </a>
                                </td>
                                <td style="padding: 16px 20px; border-bottom: 1px solid #f0f0f1;">
                                    <span style="color: #d63638; font-weight: 700; font-size: 16px;"><?php echo esc_html( $v['stock'] ); ?></span>
                                </td>
                                <td style="padding: 16px 20px; border-bottom: 1px solid #f0f0f1; color: #646970; font-weight: 500;">
                                    <?php echo esc_html( number_format( $v['velocity'], 1 ) ); ?><span style="font-size: 11px; color: #a7aaad; font-weight: 400;">/day</span>
                                </td>
                                <td style="padding: 16px 20px; border-bottom: 1px solid #f0f0f1;">
                                    <span class="cirrusly-risk-badge <?php echo $is_critical ? 'cirrusly-risk-critical' : 'cirrusly-risk-warning'; ?>">
                                        <?php echo esc_html( round( $v['days_left'] ) ); ?> days
                                    </span>
                                </td>
                                <td style="padding: 16px 20px; border-bottom: 1px solid #f0f0f1; text-align: right;">
                                    <a href="<?php echo esc_url( get_edit_post_link($v['id']) ); ?>" class="cirrusly-btn-success" style="font-size: 12px; padding: 8px 14px;">
                                        <span class="dashicons dashicons-update" style="font-size: 14px;"></span> Restock
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Helper: Extract filter params.
     */
    private static function get_filter_params() {
        $default_days = 90;
        $days = isset($_GET['period']) ? intval($_GET['period']) : $default_days;
        $days = max( 7, min( $days, 365 ) ); 

        $selected_statuses = array();
        if ( isset( $_GET['cirrusly_statuses'] ) && is_array( $_GET['cirrusly_statuses'] ) ) {
            $selected_statuses = array_map( 'sanitize_text_field', wp_unslash( $_GET['cirrusly_statuses'] ) );
        }

        return array( 'days' => $days, 'statuses' => $selected_statuses );
    }

    /**
     * Compute PnL metrics.
     */
    private static function get_pnl_data( $days = 90, $custom_statuses = array() ) {
        // Use midnight today as end date, then calculate start date using WP timezone
        $wp_timezone = wp_timezone();
        $end_date = new DateTime( 'today', $wp_timezone );
        $start_date = new DateTime( 'today', $wp_timezone );
        $start_date->modify( '-' . $days . ' days' );

        $start_date_ymd = $start_date->format( 'Y-m-d' );
        $end_date_ymd = $end_date->format( 'Y-m-d' );

        $target_statuses = array();
        if ( ! empty( $custom_statuses ) ) {
            $target_statuses = $custom_statuses;
        } else {
            $all_statuses = array_keys( wc_get_order_statuses() );
            $excluded_statuses = array( 'wc-cancelled', 'wc-failed', 'wc-trash', 'wc-pending', 'wc-checkout-draft' );
            $target_statuses = array_diff( $all_statuses, $excluded_statuses );
            if ( empty( $target_statuses ) ) $target_statuses = array('wc-completed', 'wc-processing', 'wc-on-hold');
        }

        $status_hash = md5( json_encode( $target_statuses ) );
        $version     = get_option( 'cirrusly_analytics_cache_version', '1' );
        $cache_key   = 'cirrusly_analytics_pnl_v7_' . $days . '_' . $status_hash . '_' . $version;

        // Use option instead of transient for Redis/Memcached compatibility 
        $cached = get_option( $cache_key, false );
        if ( false !== $cached ) { return $cached; }

        $stats = array(
            'revenue' => 0, 'cogs' => 0, 'shipping'=> 0, 'fees' => 0, 'refunds' => 0, 'total_costs' => 0, 'net_profit' => 0, 'margin' => 0,
            'count'   => 0, 'products'=> array(), 'history' => array(),
            'method' => 'Unknown', 'statuses_used' => $target_statuses
        );

        // Generate date range with explicit timezone to avoid UTC/local timezone mismatches
        $period = new DatePeriod( clone $start_date, new DateInterval('P1D'), (clone $end_date)->modify('+1 day') );
        foreach ( $period as $dt ) {
            $stats['history'][ $dt->format( 'Y-m-d' ) ] = array( 'revenue' => 0, 'costs' => 0, 'profit' => 0, 'refunds' => 0 );
        }

        $fee_config = get_option( 'cirrusly_shipping_config', array() );
        
        // Query Logic (HPOS vs Legacy)
        $hpos_enabled = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        $order_ids = array();

        if ( $hpos_enabled ) {
            $page = 1;
            do {
                $batch = wc_get_orders( array(
                    'limit'        => 500,
                    'page'         => $page,
                    'status'       => $target_statuses,
                    'date_created' => strtotime($start_date_ymd) . '...' . strtotime($end_date_ymd . ' 23:59:59'),
                    'type'         => 'shop_order',
                    'return'       => 'ids',
                ) );
                $order_ids = array_merge( $order_ids, $batch );
                $page++;
            } while ( count( $batch ) === 500 );
        } else {
            global $wpdb;
            // Build placeholders for IN clause (proper SQL injection protection)
            $placeholders = implode( ',', array_fill( 0, count( $target_statuses ), '%s' ) );
            // Prepare query with all parameters
            $query = $wpdb->prepare( "
                SELECT ID FROM {$wpdb->posts}
                WHERE post_type = 'shop_order'
                AND post_status IN ($placeholders)
                AND post_date >= %s
                AND post_date <= %s
            ", array_merge( $target_statuses, array( $start_date_ymd . ' 00:00:00', $end_date_ymd . ' 23:59:59' ) ) );
            $order_ids = $wpdb->get_col( $query );
        }

        // Process
        if ( ! empty( $order_ids ) ) {
            $stats['count'] = count( $order_ids );
            foreach ( $order_ids as $order_id ) {
                $order = wc_get_order( $order_id );
                if ( ! $order ) continue;

                $date_created = $order->get_date_created();
                if ( ! $date_created ) continue;
                $order_date = wp_date( 'Y-m-d', $date_created->getTimestamp() );
                
                if ( ! isset( $stats['history'][ $order_date ] ) ) continue;

                $order_revenue = (float) $order->get_total();
                $order_refunds = (float) $order->get_total_refunded();
                $order_fees    = self::calculate_single_order_fee( $order_revenue, $fee_config );
                
                $order_cogs = 0;
                $order_ship = 0;

                foreach ( $order->get_items() as $item ) {
                    $product = $item->get_product();
                    if ( ! $product ) continue;
                    $qty = $item->get_quantity();
                    
                    $cogs_val = (float) $product->get_meta( '_cogs_total_value' );
                    $ship_val = (float) $product->get_meta( '_cirrusly_est_shipping' );
                    
                    $cost_basis = ($cogs_val + $ship_val) * $qty;
                    $order_cogs += ($cogs_val * $qty);
                    $order_ship += ($ship_val * $qty);

                    $pid = $item->get_product_id();
                    if ( ! isset( $stats['products'][$pid] ) ) {
                        $stats['products'][$pid] = array( 'id' => $pid, 'name' => $product->get_name(), 'qty' => 0, 'net' => 0 );
                    }
                    $stats['products'][$pid]['qty'] += $qty;
                    $stats['products'][$pid]['net'] += ( (float)$item->get_total() - $cost_basis );
                }

                $stats['revenue']  += $order_revenue;
                $stats['refunds']  += $order_refunds;
                $stats['fees']     += $order_fees;
                $stats['cogs']     += $order_cogs;
                $stats['shipping'] += $order_ship;

                $daily_costs = $order_cogs + $order_ship + $order_fees + $order_refunds;
                $daily_profit = $order_revenue - $daily_costs;

                $stats['history'][ $order_date ]['revenue'] += $order_revenue;
                $stats['history'][ $order_date ]['costs']   += $daily_costs;
                $stats['history'][ $order_date ]['profit']  += $daily_profit;
                $stats['history'][ $order_date ]['refunds'] += $order_refunds;
            }
        }

        $stats['total_costs'] = $stats['cogs'] + $stats['shipping'] + $stats['fees'] + $stats['refunds'];
        $stats['net_profit']  = $stats['revenue'] - $stats['total_costs'];
        $stats['margin']      = $stats['revenue'] > 0 ? ( $stats['net_profit'] / $stats['revenue'] ) * 100 : 0;

        usort( $stats['products'], function($a, $b) { return $b['net'] <=> $a['net']; });

        // Use option instead of transient for Redis/Memcached compatibility.
        // Cache is manually invalidated by incrementing cirrusly_analytics_cache_version
        // via the "Refresh Data" button (line 67) or other cache-busting events.
        update_option( $cache_key, $stats, false );
        return $stats;
    }

    private static function get_inventory_velocity() {
        $sold_map = array();
        $date_from = strtotime( '-30 days' );
        
        $page = 1;
        $per_page = 250; 
        do {
            $orders = wc_get_orders( array(
                'limit' => $per_page, 'page' => $page, 'status' => array( 'completed', 'processing' ), 'date_created' => '>=' . $date_from, 'return' => 'ids'
            ) );
            foreach ( $orders as $oid ) {
                $order = wc_get_order($oid);
                if (!$order) continue;
                foreach ( $order->get_items() as $item ) {
                    $pid = $item->get_product_id();
                    $sold_map[ $pid ] = ( $sold_map[ $pid ] ?? 0 ) + $item->get_quantity();
                }
            }
            $page++;
        } while ( count( $orders ) === $per_page );

        $risky_items = array();
        foreach ( $sold_map as $pid => $qty_30 ) {
            $product = wc_get_product( $pid );
            if ( ! $product || ! $product->managing_stock() ) continue;
            $stock = $product->get_stock_quantity();
            if ( $stock <= 0 ) continue;
            $velocity = $qty_30 / 30;
            if ( $velocity <= 0 ) continue;
            $days_left = $stock / $velocity;
            if ( $days_left < 14 ) { 
                $risky_items[] = array( 'id' => $pid, 'name' => $product->get_name(), 'stock' => $stock, 'velocity' => $velocity, 'days_left' => $days_left );
            }
        }
        usort( $risky_items, function($a, $b) { return $a['days_left'] <=> $b['days_left']; });
        return $risky_items;
    }

    public function capture_daily_gmc_snapshot() {
        $scan_data = get_option( 'cirrusly_gmc_scan_data', array() );
        if ( empty( $scan_data['results'] ) ) return;

        $critical = 0; $warnings = 0;
        foreach ( $scan_data['results'] as $res ) {
            if ( empty( $res['issues'] ) ) continue;
            foreach ( $res['issues'] as $issue ) {
                if ( ($issue['type'] ?? '') === 'critical' ) $critical++; else $warnings++;
            }
        }

        $history = get_option( 'cirrusly_gmc_history', array() );
        $today   = wp_date( 'Y-m-d' );
        $history[$today] = array( 'critical' => $critical, 'warnings' => $warnings, 'ts' => time() );
        if ( count( $history ) > 90 ) $history = array_slice( $history, -90, 90, true );
        update_option( 'cirrusly_gmc_history', $history, false );
    }

    /**
     * Schedule weekly analytics email (runs every Monday at 9 AM site time)
     */
    public function schedule_weekly_email() {
        // Check if cron is already scheduled
        if ( wp_next_scheduled( 'cirrusly_weekly_analytics_email' ) ) {
            return;
        }

        // Schedule for next Monday at 9 AM site time
        $timezone = new DateTimeZone( wp_timezone_string() );
        $now = new DateTime( 'now', $timezone );
        
        // Get next Monday
        $next_monday = new DateTime( 'next monday', $timezone );
        $next_monday->setTime( 9, 0, 0 ); // 9:00 AM
        
        // If it's Monday and before 9 AM, use today
        if ( $now->format( 'N' ) == 1 && $now->format( 'H' ) < 9 ) {
            $next_monday = clone $now;
            $next_monday->setTime( 9, 0, 0 );
        }
        
        // Schedule the event
        wp_schedule_event( $next_monday->getTimestamp(), 'weekly', 'cirrusly_weekly_analytics_email' );
    }

    /**
     * Send weekly analytics summary email
     */
    public function send_weekly_summary() {
        // Ensure Mailer class is loaded
        if ( ! class_exists( 'Cirrusly_Commerce_Mailer' ) ) {
            require_once CIRRUSLY_COMMERCE_PATH . 'class-mailer.php';
        }

        // Check if email is enabled
        if ( ! Cirrusly_Commerce_Mailer::is_email_enabled( 'weekly_analytics' ) ) {
            return;
        }

        // Check if GMC analytics data exists
        if ( ! class_exists( 'Cirrusly_Commerce_GMC_Analytics' ) ) {
            return;
        }

        // Get 7-day analytics data
        $end_date = wp_date( 'Y-m-d' );
        $start_date = wp_date( 'Y-m-d', strtotime( '-7 days' ) );
        
        $date_range = array(
            'start' => $start_date,
            'end'   => $end_date,
        );
        $weekly_data = Cirrusly_Commerce_GMC_Analytics::get_gmc_analytics_compiled( $date_range );

        // Calculate totals
        $total_clicks = 0;
        $total_impressions = 0;
        $total_conversions = 0;
        $conversion_value = 0;
        $top_products = array();

        if ( ! empty( $weekly_data ) ) {
            foreach ( $weekly_data as $product_id => $data ) {
                $total_clicks += isset( $data['gmc']['clicks'] ) ? $data['gmc']['clicks'] : 0;
                $total_impressions += isset( $data['gmc']['impressions'] ) ? $data['gmc']['impressions'] : 0;
                $total_conversions += isset( $data['gmc']['conversions'] ) ? $data['gmc']['conversions'] : 0;
                $conversion_value += isset( $data['gmc']['conversion_value'] ) ? $data['gmc']['conversion_value'] : 0;

                // Collect for top products
                if ( isset( $data['gmc']['clicks'] ) && $data['gmc']['clicks'] > 0 ) {
                    $product = wc_get_product( $product_id );
                    if ( $product ) {
                        $top_products[] = array(
                            'name' => $product->get_name(),
                            'clicks' => $data['gmc']['clicks'],
                            'conversions' => isset( $data['gmc']['conversions'] ) ? $data['gmc']['conversions'] : 0,
                        );
                    }
                }
            }
        }

        // Sort top products by clicks
        usort( $top_products, function( $a, $b ) {
            return $b['clicks'] - $a['clicks'];
        });
        $top_products = array_slice( $top_products, 0, 10 ); // Top 10 only

        // Calculate rates
        $ctr = $total_impressions > 0 ? round( ( $total_clicks / $total_impressions ) * 100, 2 ) : 0;
        $conversion_rate = $total_clicks > 0 ? round( ( $total_conversions / $total_clicks ) * 100, 2 ) : 0;

        // Prepare analytics data for email
        $analytics_data = array(
            'start_date' => date_i18n( 'M j, Y', strtotime( $start_date ) ),
            'end_date' => date_i18n( 'M j, Y', strtotime( $end_date ) ),
            'total_clicks' => $total_clicks,
            'total_impressions' => $total_impressions,
            'total_conversions' => $total_conversions,
            'conversion_value' => $conversion_value,
            'ctr' => $ctr,
            'conversion_rate' => $conversion_rate,
            'top_products' => $top_products,
        );

        // Only send if there's activity
        if ( $total_impressions > 0 ) {
            $recipient = Cirrusly_Commerce_Mailer::get_recipient( 'weekly_analytics' );
            $subject = 'Weekly GMC Analytics Summary - ' . date_i18n( 'M j, Y', strtotime( $end_date ) );
            
            $result = Cirrusly_Commerce_Mailer::send_from_template(
                $recipient,
                'weekly-analytics',
                array( 'analytics_data' => $analytics_data ),
                $subject
            );
            
            if ( ! $result ) {
                error_log( 'Cirrusly Commerce: Failed to send weekly analytics email to ' . $recipient );
            }
        }
    }

    private static function calculate_single_order_fee( $total, $config ) {
        $pay_pct  = isset($config['payment_pct']) ? ($config['payment_pct'] / 100) : 0.029;
        $pay_flat = isset($config['payment_flat']) ? $config['payment_flat'] : 0.30;
        return ($total * $pay_pct) + $pay_flat;
    }
}