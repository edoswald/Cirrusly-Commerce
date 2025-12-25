<?php
/**
 * GMC Product Disapproval Alert Email Template
 * 
 * Variables available:
 * @var array $products Array of disapproved products with keys: product_id, product_name, reason
 */
defined( 'ABSPATH' ) || exit;

$gmc_url = admin_url( 'admin.php?page=cirrusly-gmc' );
?>
<h2><?php esc_html_e( 'Google Merchant Center Disapproval Alert', 'cirrusly-commerce' ); ?></h2>

<p style="color:#d63638; font-weight:bold;"><?php esc_html_e( 'Google has disapproved one or more of your products!', 'cirrusly-commerce' ); ?></p>

<p><?php esc_html_e( 'The following products have been flagged by Google Merchant Center:', 'cirrusly-commerce' ); ?></p>

<table cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; border:1px solid #eee; font-family: sans-serif; margin: 15px 0;">
    <thead>
        <tr style="background:#f0f0f1;">
            <th style="border-bottom:2px solid #ccc; padding: 10px; text-align:left;"><?php esc_html_e( 'Product ID', 'cirrusly-commerce' ); ?></th>
            <th style="border-bottom:2px solid #ccc; padding: 10px; text-align:left;"><?php esc_html_e( 'Product Name', 'cirrusly-commerce' ); ?></th>
            <th style="border-bottom:2px solid #ccc; padding: 10px; text-align:left;"><?php esc_html_e( 'Reason', 'cirrusly-commerce' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $products as $product ) : ?>
        <tr>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( $product['product_id'] ); ?></td>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( $product['product_name'] ); ?></td>
            <td style="border-bottom:1px solid #eee; padding: 8px; color:#d63638;"><?php echo esc_html( $product['reason'] ); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p><?php esc_html_e( 'Please review these products in the Compliance Hub and take corrective action as soon as possible.', 'cirrusly-commerce' ); ?></p>

<p><a href="<?php echo esc_url( $gmc_url ); ?>" style="display:inline-block; background:#d63638; color:#fff; padding:10px 20px; text-decoration:none; border-radius:4px; margin-top:10px;"><?php esc_html_e( 'View Compliance Hub', 'cirrusly-commerce' ); ?></a></p>
