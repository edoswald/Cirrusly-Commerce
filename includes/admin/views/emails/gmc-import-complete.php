<?php
/**
 * GMC Analytics Import Complete Email Template
 * 
 * Variables available:
 * @var array $progress Progress data with keys: products_processed, current_batch, total_batches
 */
defined( 'ABSPATH' ) || exit;

$products_processed = isset( $progress['products_processed'] ) ? intval( $progress['products_processed'] ) : 0;
$current_batch = isset( $progress['current_batch'] ) ? intval( $progress['current_batch'] ) : 0;
$total_batches = isset( $progress['total_batches'] ) ? intval( $progress['total_batches'] ) : 0;
$analytics_url = admin_url( 'admin.php?page=cirrusly-analytics' );
?>
<h2><?php esc_html_e( 'GMC Analytics Import Complete', 'cirrusly-commerce' ); ?></h2>

<p><?php esc_html_e( 'Your 90-day GMC Analytics import has completed successfully!', 'cirrusly-commerce' ); ?></p>

<table cellpadding="0" cellspacing="0" style="width:100%; max-width:500px; border:1px solid #eee; font-family: sans-serif; margin: 20px 0;">
    <tr>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><strong><?php esc_html_e( 'Products Processed', 'cirrusly-commerce' ); ?></strong></td>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><?php echo esc_html( $products_processed ); ?></td>
    </tr>
    <tr>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><strong><?php esc_html_e( 'Batches Completed', 'cirrusly-commerce' ); ?></strong></td>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><?php echo esc_html( $current_batch . '/' . $total_batches ); ?></td>
    </tr>
</table>

<p><?php esc_html_e( 'You can now view GMC performance data in your Analytics dashboard.', 'cirrusly-commerce' ); ?></p>

<p><a href="<?php echo esc_url( $analytics_url ); ?>" style="display:inline-block; background:#2271b1; color:#fff; padding:10px 20px; text-decoration:none; border-radius:4px; margin-top:10px;"><?php esc_html_e( 'View Analytics', 'cirrusly-commerce' ); ?></a></p>
