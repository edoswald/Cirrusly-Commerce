<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cirrusly_Commerce_Audit_UI {

    /**
     * Render the Store Financial Audit admin page.
     *
     * Processes input (filters, search, sorting, pagination), handles transient refresh and CSV import (delegated to Pro when applicable), enforces the 'edit_products' capability, and outputs the dashboard overview, filters toolbar, sortable/paginated products table, and Pro-only inline editing UI.
     */
    public static function render_page() {
        if ( ! current_user_can( 'edit_products' ) ) wp_die( 'No permission' );
        
        // Handle Import Submission (Delegated to Pro)
        if (
            isset( $_POST['cirrusly_import_nonce'] )
            && wp_verify_nonce(
                sanitize_text_field( wp_unslash( $_POST['cirrusly_import_nonce'] ) ),
                'cirrusly_import_action'
            )
            && Cirrusly_Commerce_Core::cirrusly_is_pro()
            && class_exists( 'Cirrusly_Commerce_Audit_Pro' )
        ) {
            Cirrusly_Commerce_Audit_Pro::handle_import();
        }

        echo '<div class="wrap">'; 

        Cirrusly_Commerce_Core::render_global_header( 'Store Financial Audit' );
        settings_errors('cirrusly_audit');

        // 1. Handle Cache & Refresh
        $refresh = isset( $_GET['refresh_audit'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cirrusly_refresh_audit' );
        if ( $refresh ) {
            delete_transient( 'cirrusly_audit_data' );
        }

        // 2. Get Data via Core Logic
        $cached_data = Cirrusly_Commerce_Audit::get_compiled_data( $refresh );

        // --- Calculate Audit Aggregates ---
        $total_skus = count($cached_data);
        $loss_count = 0;
        $alert_count = 0;
        $low_margin_count = 0;

        foreach($cached_data as $row) {
            if($row['net'] < 0) $loss_count++;
            if(!empty($row['alerts'])) $alert_count++;
            if($row['margin'] < 15) $low_margin_count++;
        }

        $is_pro = Cirrusly_Commerce_Core::cirrusly_is_pro();
        $disabled_attr = $is_pro ? '' : 'disabled';

        // 3. Process Filters & Pagination (Moved Up)
        $f_margin = isset($_GET['margin']) ? floatval($_GET['margin']) : 25;
        $f_cat = isset($_GET['cat']) ? sanitize_text_field(wp_unslash($_GET['cat'])) : '';
        $f_oos = isset($_GET['hide_oos']);
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 50;
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $orderby = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'margin';
        $allowed_orderby = array('cost', 'price', 'ship_pl', 'net', 'margin');
        if (!in_array($orderby, $allowed_orderby, true)) {
            $orderby = 'margin';
        }
        $order = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'asc';

        $filtered_data = array();
        foreach($cached_data as $row) {
            if($f_oos && !$row['is_in_stock']) continue;
            if($f_cat && !in_array($f_cat, $row['cats'])) continue;
            if($search && stripos($row['name'], $search) === false) continue;
            if ( $row['margin'] >= $f_margin && empty($row['alerts']) ) continue;
            
            $filtered_data[] = $row;
        }
        
        usort($filtered_data, function($a, $b) use ($orderby, $order) {
            if ($a[$orderby] == $b[$orderby]) return 0;
            if ($order === 'asc') return ($a[$orderby] < $b[$orderby]) ? -1 : 1;
            return ($a[$orderby] > $b[$orderby]) ? -1 : 1;
        });

        $total = count($filtered_data);
        $pages = ceil($total/$per_page);
        $slice = array_slice($filtered_data, ($paged-1)*$per_page, $per_page);

        // DASHBOARD GRID - Modern KPI Cards (Analytics Pro Style)
        ?>
        <div class="cirrusly-kpi-grid" style="margin-bottom: 25px;">
            <div class="cirrusly-kpi-card" style="--card-accent: #2271b1;">
                <span class="dashicons dashicons-products" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(34, 113, 177, 0.15);"></span>
                <span class="cirrusly-kpi-label">Audited SKUs</span>
                <span class="cirrusly-kpi-value"><?php echo esc_html( number_format( $total_skus ) ); ?></span>
            </div>
            
            <div class="cirrusly-kpi-card" style="--card-accent: #d63638; --card-accent-end: #f56565;">
                <span class="dashicons dashicons-warning" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(214, 54, 56, 0.15);"></span>
                <span class="cirrusly-kpi-label">Loss Makers</span>
                <span class="cirrusly-kpi-value" style="color: #d63638;"><?php echo esc_html( number_format( $loss_count ) ); ?></span>
                <small style="font-size: 11px; color: #646970; margin-top: 5px;">Net Profit &lt; $0</small>
            </div>
            
            <div class="cirrusly-kpi-card" style="--card-accent: #f0ad4e; --card-accent-end: #f6c15b;">
                <span class="dashicons dashicons-flag" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(240, 173, 78, 0.15);"></span>
                <span class="cirrusly-kpi-label">Data Alerts</span>
                <span class="cirrusly-kpi-value" style="color: #f0ad4e;"><?php echo esc_html( number_format( $alert_count ) ); ?></span>
                <small style="font-size: 11px; color: #646970; margin-top: 5px;">Missing Cost/Weight</small>
            </div>
            
            <div class="cirrusly-kpi-card" style="--card-accent: #00a32a; --card-accent-end: #00d084;">
                <span class="dashicons dashicons-chart-line" style="position: absolute; top: 15px; right: 15px; font-size: 24px; color: rgba(0, 163, 42, 0.15);"></span>
                <span class="cirrusly-kpi-label">Low Margin</span>
                <span class="cirrusly-kpi-value"><?php echo esc_html( number_format( $low_margin_count ) ); ?></span>
                <small style="font-size: 11px; color: #646970; margin-top: 5px;">Below 15%</small>
            </div>
        </div>

        <!-- Info Banner -->
        <div class="cirrusly-chart-card" style="background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 15px 20px; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px; font-size: 12px; color: #646970;">
                <span class="dashicons dashicons-info-outline" style="color: #2271b1; font-size: 18px;"></span>
                <div style="display: flex; gap: 25px; flex-wrap: wrap;">
                    <span><strong style="color: #2271b1;">Ship P/L:</strong> Shipping Charged - Estimated Cost</span>
                    <span><strong style="color: #2271b1;">Net Profit:</strong> Gross Profit - Payment Fees</span>
                    <span><strong style="color: #2271b1;">Margin:</strong> (Gross Profit / Price) × 100</span>
                </div>
            </div>
        </div>

        <div class="cirrusly-chart-card" style="margin-bottom: 20px;">
            <form method="get" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <input type="hidden" name="page" value="cirrusly-audit">
                
                <span class="dashicons dashicons-filter" style="color: #2271b1; font-size: 20px;"></span>
                
                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search product..." style="height:32px; min-width:200px; border-radius: 6px; border: 1px solid #c3c4c7;">
                
                <select name="margin" style="height:32px; border-radius: 6px; border: 1px solid #c3c4c7;">
                    <option value="5" <?php selected($f_margin,5); ?>>Margin < 5%</option>
                    <option value="15" <?php selected($f_margin,15); ?>>Margin < 15%</option>
                    <option value="25" <?php selected($f_margin,25); ?>>Margin < 25%</option>
                    <option value="100" <?php selected($f_margin,100); ?>>Show All</option>
                </select>

                <?php 
                $allowed_form_tags = array( 'select' => array('name' => true, 'id' => true, 'class' => true, 'style'=>true), 'option' => array('value' => true, 'selected' => true) );
                echo wp_kses( wc_product_dropdown_categories(array('option_none_text'=>'All Categories','name'=>'cat','selected'=>$f_cat,'value_field'=>'slug','echo'=>0, 'class'=>'', 'style'=>'height:32px; max-width:150px; border-radius: 6px; border: 1px solid #c3c4c7;')), $allowed_form_tags ); 
                ?>

                <label style="margin-left:5px; white-space:nowrap; background:#f8f9fa; padding:0 10px; border-radius:6px; border:1px solid #c3c4c7; height:30px; line-height:28px; font-size:12px; font-weight: 500; cursor: pointer; transition: all 0.3s;">
                    <input type="checkbox" name="hide_oos" value="1" <?php checked($f_oos,true); ?>> Hide Out of Stock
                </label>
                
                <button class="cirrusly-btn-success" style="height:32px; padding: 0 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 5px;"><span class="dashicons dashicons-search" style="line-height:inherit;"></span> Filter</button>
                <a href="<?php echo esc_url( wp_nonce_url( '?page=cirrusly-audit&refresh_audit=1', 'cirrusly_refresh_audit' ) ); ?>" class="cirrusly-btn-secondary" title="Refresh Data from Database" style="height:32px; padding: 0 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center;"><span class="dashicons dashicons-update" style="line-height:inherit;"></span></a>
                
                <div style="margin-left: auto; display:flex; align-items:center; gap:8px; border-left:1px solid #e5e5e5; padding-left:15px;">
                    <?php if(!$is_pro): ?>
                         <a href="<?php echo esc_url( function_exists('cirrusly_fs') ? cirrusly_fs()->get_upgrade_url() : '#' ); ?>" title="Upgrade to Pro" style="color:#d63638; text-decoration:none; margin-right:5px; font-weight:bold;">
                            <span class="dashicons dashicons-lock" style="line-height:inherit;"></span>
                         </a>
                    <?php endif; ?>

                    <?php if($is_pro): ?>
                    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg('action', 'export_csv'), 'cirrusly_export_csv' ) ); ?>" class="cirrusly-btn-secondary" title="Export CSV" style="padding: 8px 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 5px;">
                        <span class="dashicons dashicons-download" style="line-height:inherit;"></span> Export
                    </a>
                    <?php else: ?>
                        <label class="cirrusly-btn-secondary" style="cursor:not-allowed; opacity:0.6; padding: 8px 14px; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; gap: 5px;" title="Export CSV is available in Pro.">
                            <span class="dashicons dashicons-download" style="line-height:inherit;"></span> Export
                        </label>
                    <?php endif; ?>

                    <label class="cirrusly-btn-secondary cirrusly-import-trigger" style="cursor:pointer; padding: 8px 14px; border-radius: 6px; font-weight: 600; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: inline-flex; align-items: center; gap: 5px;" title="Import Cost CSV" <?php echo esc_attr( $disabled_attr ); ?>>
                        <span class="dashicons dashicons-upload" style="line-height:inherit;"></span> Import
                    </label>
                </div>
            </form>
        </div>

        <!-- Import Form (Separate from Filter Form) -->
        <form method="post" enctype="multipart/form-data" id="cirrusly-import-form" style="display:none;">
            <?php wp_nonce_field('cirrusly_import_action', 'cirrusly_import_nonce'); ?>
            <input type="file" name="csv_import" id="cirrusly-import-file" accept=".csv" <?php echo esc_attr( $disabled_attr ); ?>>
        </form>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.cirrusly-import-trigger').on('click', function(e) {
                e.preventDefault();
                if (!$(this).attr('disabled')) {
                    $('#cirrusly-import-file').trigger('click');
                }
            });
            
            $('#cirrusly-import-file').on('change', function() {
                if (this.files.length > 0) {
                    $('#cirrusly-import-form').submit();
                }
            });
        });
        </script>
        <?php

        // Pagination
        $pagination_html = '';
        if($pages>1) {
            $pagination_html .= '<div class="tablenav-pages"><span class="displaying-num">'.esc_html($total).' items</span>';
            $pagination_html .= '<span class="pagination-links">';
            for($i=1; $i<=$pages; $i++) {
                if($i==1 || $i==$pages || abs($i-$paged)<2) {
                    $cls = $i==$paged ? 'current' : '';
                    $pagination_html .= '<a class="button '.esc_attr($cls).'" href="'.esc_url(add_query_arg('paged',$i)).'">'.esc_html($i).'</a> ';
                } elseif($i==2 || $i==$pages-1) $pagination_html .= '<span class="tablenav-pages-navspan button disabled">...</span> ';
            }
            $pagination_html .= '</span></div>';
        }

        // Render Top Pagination
        if($pagination_html) {
             echo '<div class="tablenav top" style="margin-top:0;">' . wp_kses_post( $pagination_html ) . '</div>';
        }

        $sort_link = function($col, $label) use ($orderby, $order) {
            $new_order = ($orderby === $col && $order === 'asc') ? 'desc' : 'asc';
            $arrow = ($orderby === $col) ? ($order === 'asc' ? ' ▲' : ' ▼') : '';
            return '<a href="'.esc_url(add_query_arg(array('orderby'=>$col, 'order'=>$new_order))).'" style="color:#333;text-decoration:none;font-weight:600;">'.esc_html($label).$arrow.'</a>';
        };

        echo '<table class="widefat fixed striped"><thead><tr>
            <th style="width:60px;">ID</th>
            <th>Product</th>
            <th>'.wp_kses_post($sort_link('cost', 'Total Cost')).'</th>
            <th>'.wp_kses_post($sort_link('price', 'Price')).'</th>
            <th>'.wp_kses_post($sort_link('ship_pl', 'Ship P/L')).'</th>
            <th>'.wp_kses_post($sort_link('net', 'Net Profit')).'</th>
            <th>'.wp_kses_post($sort_link('margin', 'Margin')).'</th>
            <th>Alerts</th>
            <th>Action</th>
        </tr></thead><tbody>';
        
        if ( empty($slice) ) {
            echo '<tr><td colspan="9" style="padding:20px; text-align:center;">No products found matching your criteria.</td></tr>';
        } else {
            foreach($slice as $row) {
                $name_html = esc_html($row['name']);
                if ( $row['type'] == 'variation' ) {
                    $parent = wc_get_product( $row['parent_id'] );
                    if($parent) {
                        $name_html = esc_html($parent->get_name()) . ' &rarr; <span style="color:#555;">' . esc_html(str_replace($parent->get_name().' - ', '', $row['name'])) . '</span>';
                    }
                }
                
                $net_style = $row['net'] < 0 ? 'color:#d63638;font-weight:bold;' : 'color:#008a20;font-weight:bold;';
                $ship_style = $row['ship_pl'] >= 0 ? 'color:#008a20;' : 'color:#d63638;';
                
                $cost_cell = wp_kses_post(wc_price($row['cost']));
                $ship_cell = wp_kses_post(wc_price($row['ship_pl']));
                
                if($is_pro) {
                     $cost_cell = '<span class="cirrusly-inline-edit" data-pid="'.esc_attr($row['id']).'" data-field="_cogs_total_value" contenteditable="true" style="border-bottom:1px dashed #999; cursor:pointer;">'.number_format($row['item_cost'], 2).'</span> <small style="color:#999;">+ Ship '.number_format($row['ship_cost'], 2).'</small>';
                }

                echo '<tr>
                    <td>'.esc_html($row['id']).'</td>
                    <td><a href="'.esc_url(get_edit_post_link($row['id'])).'">'.wp_kses_post($name_html).'</a></td>
                    <td>'.wp_kses_post($cost_cell).'</td>
                    <td>'.wp_kses_post(wc_price($row['price'])).'</td>
                    <td style="'.esc_attr($ship_style).'">'.wp_kses_post($ship_cell).'</td>
                    <td class="col-net" style="'.esc_attr($net_style).'">'.wp_kses_post(wc_price($row['net'])).'</td>
                    <td class="col-margin">'.esc_html(number_format($row['margin'],1)).'%</td>
                    <td>'.wp_kses_post(implode(' ',$row['alerts'])).'</td>
                    <td><a href="'.esc_url(get_edit_post_link($row['id'])).'" target="_blank" class="cirrusly-btn-secondary" style="padding: 6px 12px; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);"><span class="dashicons dashicons-edit"></span>Edit</a></td>
                </tr>';
            }
        }
        echo '</tbody></table>';

        if($pagination_html) {
             echo '<div class="tablenav bottom">' . wp_kses_post( $pagination_html ) . '</div>';
        }

        // Inline script block removed.
        // It is now handled by assets/js/audit.js which is enqueued in Cirrusly_Commerce_Admin_Assets
        
        echo '</div>'; 
    }
}