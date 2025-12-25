<?php
/**
 * Weekly Analytics Summary Email Template
 * 
 * Variables available:
 * @var array $analytics_data Analytics summary data
 */
defined( 'ABSPATH' ) || exit;

$data = wp_parse_args( $analytics_data, array(
    'total_clicks' => 0,
    'total_impressions' => 0,
    'total_conversions' => 0,
    'conversion_value' => 0,
    'ctr' => 0,
    'conversion_rate' => 0,
    'top_products' => array(),
) );

$analytics_url = admin_url( 'admin.php?page=cirrusly-analytics' );
?>
<h2><?php esc_html_e( 'Weekly Analytics Summary', 'cirrusly-commerce' ); ?></h2>

<p><?php esc_html_e( 'Here is your Google Merchant Center performance summary for the last 7 days.', 'cirrusly-commerce' ); ?></p>

<table cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; border:1px solid #eee; font-family: sans-serif; margin: 20px 0;">
    <tr>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><strong><?php esc_html_e( 'Total Clicks', 'cirrusly-commerce' ); ?></strong></td>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><?php echo esc_html( number_format( $data['total_clicks'] ) ); ?></td>
    </tr>
    <tr>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><strong><?php esc_html_e( 'Total Impressions', 'cirrusly-commerce' ); ?></strong></td>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><?php echo esc_html( number_format( $data['total_impressions'] ) ); ?></td>
    </tr>
    <tr>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><strong><?php esc_html_e( 'Click-Through Rate (CTR)', 'cirrusly-commerce' ); ?></strong></td>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><?php echo esc_html( number_format( $data['ctr'], 2 ) ); ?>%</td>
    </tr>
    <tr>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><strong><?php esc_html_e( 'Total Conversions', 'cirrusly-commerce' ); ?></strong></td>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><?php echo esc_html( number_format( $data['total_conversions'] ) ); ?></td>
    </tr>
    <tr>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><strong><?php esc_html_e( 'Conversion Rate', 'cirrusly-commerce' ); ?></strong></td>
        <td style="border-bottom:1px solid #eee; padding: 10px;"><?php echo esc_html( number_format( $data['conversion_rate'], 2 ) ); ?>%</td>
    </tr>
    <tr style="background:#f9f9f9; font-size:1.1em;">
        <td style="padding: 10px;"><strong><?php esc_html_e( 'Conversion Value', 'cirrusly-commerce' ); ?></strong></td>
        <td style="padding: 10px; color:#008a20; font-weight:bold;"><?php echo wp_kses_post( wc_price( $data['conversion_value'] ) ); ?></td>
    </tr>
</table>

<?php if ( ! empty( $data['top_products'] ) ) : ?>
<h3><?php esc_html_e( 'Top Performing Products', 'cirrusly-commerce' ); ?></h3>
<table cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; border:1px solid #eee; font-family: sans-serif; margin: 15px 0;">
    <thead>
        <tr style="background:#f0f0f1;">
            <th style="border-bottom:2px solid #ccc; padding: 8px; text-align:left;"><?php esc_html_e( 'Product', 'cirrusly-commerce' ); ?></th>
            <th style="border-bottom:2px solid #ccc; padding: 8px; text-align:left;"><?php esc_html_e( 'Clicks', 'cirrusly-commerce' ); ?></th>
            <th style="border-bottom:2px solid #ccc; padding: 8px; text-align:left;"><?php esc_html_e( 'Conversions', 'cirrusly-commerce' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( array_slice( $data['top_products'], 0, 5 ) as $product ) : ?>
        <tr>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( $product['name'] ); ?></td>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( number_format( $product['clicks'] ) ); ?></td>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( number_format( $product['conversions'] ) ); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<p><a href="<?php echo esc_url( $analytics_url ); ?>" style="display:inline-block; background:#2271b1; color:#fff; padding:10px 20px; text-decoration:none; border-radius:4px; margin-top:10px;"><?php esc_html_e( 'View Full Analytics', 'cirrusly-commerce' ); ?></a></p>
