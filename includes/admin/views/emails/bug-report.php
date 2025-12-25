<?php
/**
 * Bug Report Email Template
 * 
 * Variables available:
 * @var string $user_email User's email address
 * @var string $subject    Bug report subject
 * @var string $message    Bug description
 * @var string $sys_info   System information
 */
defined( 'ABSPATH' ) || exit;
?>
<h3>New Bug Report</h3>
<p><strong>User:</strong> <?php echo esc_html( $user_email ); ?></p>
<p><strong>Description:</strong><br><?php echo nl2br( esc_html( $message ) ); ?></p>
<hr>
<h4>System Information</h4>
<pre style="background:#f0f0f1; padding:10px; border:1px solid #ccc;"><?php echo esc_html( $sys_info ); ?></pre>
