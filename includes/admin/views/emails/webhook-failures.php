<?php
/**
 * Webhook Failure Alert Email Template
 * 
 * Variables available:
 * @var array $recent_logs Array of recent webhook failure log entries
 */
defined( 'ABSPATH' ) || exit;

$settings_url = admin_url( 'admin.php?page=cirrusly-commerce&tab=advanced' );
?>
<h2><?php esc_html_e( 'Webhook Failures Detected', 'cirrusly-commerce' ); ?></h2>

<p><?php esc_html_e( 'Multiple Freemius webhook failures have been detected for Cirrusly Commerce.', 'cirrusly-commerce' ); ?></p>

<h3><?php esc_html_e( 'Recent Failures:', 'cirrusly-commerce' ); ?></h3>

<table cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; border:1px solid #eee; font-family: sans-serif; margin: 15px 0;">
    <thead>
        <tr style="background:#f0f0f1;">
            <th style="border-bottom:2px solid #ccc; padding: 10px; text-align:left;"><?php esc_html_e( 'Timestamp', 'cirrusly-commerce' ); ?></th>
            <th style="border-bottom:2px solid #ccc; padding: 10px; text-align:left;"><?php esc_html_e( 'Event Type', 'cirrusly-commerce' ); ?></th>
            <th style="border-bottom:2px solid #ccc; padding: 10px; text-align:left;"><?php esc_html_e( 'Error', 'cirrusly-commerce' ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $recent_logs as $log ) : ?>
        <tr>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( $log['received_at'] ); ?></td>
            <td style="border-bottom:1px solid #eee; padding: 8px;"><?php echo esc_html( $log['event_type'] ); ?></td>
            <td style="border-bottom:1px solid #eee; padding: 8px; color:#d63638;"><?php echo esc_html( $log['notes'] ); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<p><?php esc_html_e( 'Please check your webhook configuration in Settings.', 'cirrusly-commerce' ); ?></p>

<p><a href="<?php echo esc_url( $settings_url ); ?>" style="display:inline-block; background:#d63638; color:#fff; padding:10px 20px; text-decoration:none; border-radius:4px; margin-top:10px;"><?php esc_html_e( 'View Webhook Logs', 'cirrusly-commerce' ); ?></a></p>
