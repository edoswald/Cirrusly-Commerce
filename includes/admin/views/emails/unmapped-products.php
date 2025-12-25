<?php
/**
 * Unmapped Products Alert Email Template
 * 
 * Variables available:
 * @var int $count Number of unmapped products
 * @var array $products Array of unmapped product data with keys: product_id, product_name, sku
 */
defined( 'ABSPATH' ) || exit;

$analytics_url = admin_url( 'admin.php?page=cirrusly-analytics' );
?>
<h2><?php esc_html_e( 'New Unmapped Products Detected', 'cirrusly-commerce' ); ?></h2>

<p><?php 
echo esc_html( 
    sprintf( 
        _n( 
            '%d product in your GMC Analytics data could not be automatically mapped to a WooCommerce product.',
            '%d products in your GMC Analytics data could not be automatically mapped to WooCommerce products.',
            $count,
            'cirrusly-commerce'
        ),
        $count
    )
); 
?></p>

<table cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; border:1px solid #eee; font-family: sans-serif; margin: 15px 0;">
    <thead>
        <tr style="background:#f0f0f1;">
            <th style="border-bottom:2px solid #ccc; padding: 10px; text-align:left;"><?php esc_html_e( 'Product ID', 'cirrusly-commerce' ); ?></th>
            <th style="border-bottom:2px solid #ccc; padding: 10px; text-align:left;"><?php esc_html_e( 'Product Name', 'cirrusly-commerce' ); ?></th>
            <th style="border-bottom:2px solid #ccc; padding: 10px; text-align:left;"><?php esc_html_e( 'SKU', 'cirrusly-commerce' ); ?></th>
        </tr>
    </thead>
    <tbody>
+       <?php if ( ! empty( $products ) ) : ?>
        <?php foreach ( $products as $product ) : ?>
        <tr>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( $product['product_id'] ); ?></td>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( $product['product_name'] ); ?></td>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( $product['sku'] ); ?></td>
        </tr>
        <?php endforeach; ?>
        <?php else : ?>
        <tr>
            <td colspan="3" style="padding: 8px; text-align: center;"><?php esc_html_e( 'No products to display.', 'cirrusly-commerce' ); ?></td>
        </tr>
        <?php endif; ?>
    </tbody>
</table>

<p><?php esc_html_e( 'Please map these products manually in the Analytics dashboard to enable performance tracking.', 'cirrusly-commerce' ); ?></p>

<p><a href="<?php echo esc_url( $analytics_url ); ?>" style="display:inline-block; background:#2271b1; color:#fff; padding:10px 20px; text-decoration:none; border-radius:4px; margin-top:10px;"><?php esc_html_e( 'Map Products', 'cirrusly-commerce' ); ?></a></p>
