<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_Mailer {

    /**
     * Send an HTML email using the site's default "From" headers.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject line.
     * @param string $message HTML content of the email.
     * @return bool           True on success, false on failure.
     */
    public static function send_html( $to, $subject, $message ) {
        if ( ! is_email( $to ) ) {
            return false;
        }
        
        $headers = self::get_headers();

        // Enforce HTML content type for this specific email
        add_filter( 'wp_mail_content_type', array( __CLASS__, 'get_html_content_type' ) );
        
        try {
            $result = wp_mail( $to, $subject, $message, $headers );
        } finally {
            // Clean up filter to avoid affecting other plugins/emails
            remove_filter( 'wp_mail_content_type', array( __CLASS__, 'get_html_content_type' ) );
        }

        // Log email
        self::log_email( $to, $subject, $result ? 'success' : 'failed', 'html' );

        return $result;
    }

    /**
     * Send a plain text email using the site's default "From" headers.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject line.
     * @param string $message Plain text content of the email.
     * @return bool           True on success, false on failure.
     */
    public static function send_plain( $to, $subject, $message ) {
        if ( ! is_email( $to ) ) {
            return false;
        }
        
        $headers = self::get_headers();
        $result = wp_mail( $to, $subject, $message, $headers );

        // Log email
        self::log_email( $to, $subject, $result ? 'success' : 'failed', 'plain' );

        return $result;
    }

    /**
     * Send an email from a template file.
     *
     * @param string $to            Recipient email address.
     * @param string $template_name Template filename (without .php extension).
     * @param array  $variables     Associative array of variables to pass to template.
     * @param string $subject       Email subject line.
     * @param bool   $is_html       Whether to send as HTML (default: true).
     * @return bool                 True on success, false on failure.
     */
    public static function send_from_template( $to, $template_name, $variables, $subject, $is_html = true ) {
        if ( ! is_email( $to ) ) {
            return false;
        }

        // Load template file
        $template_path = CIRRUSLY_COMMERCE_PATH . 'includes/admin/views/emails/' . $template_name . '.php';
        
        if ( ! file_exists( $template_path ) ) {
            error_log( 'Cirrusly Commerce: Email template not found: ' . $template_name );
            return false;
        }

        // Extract variables for use in template
        extract( $variables );

        // Buffer template output
        ob_start();
        include $template_path;
        $message = ob_get_clean();

        // Send email
        if ( $is_html ) {
            return self::send_html( $to, $subject, $message );
        } else {
            return self::send_plain( $to, $subject, $message );
        }
    }

    /**
     * Get recipient email address for a specific email type.
     *
     * @param string $type     Email type key (e.g., 'bug_report', 'weekly_profit').
     * @param string $fallback Fallback email address if no custom recipient is set.
     * @return string          Email address to use.
     */
    public static function get_recipient( $type, $fallback = '' ) {
        $email_settings = get_option( 'cirrusly_email_settings', array() );
        
        // Check if custom recipient is set for this type
        if ( ! empty( $email_settings[ $type . '_recipient' ] ) && is_email( $email_settings[ $type . '_recipient' ] ) ) {
            return $email_settings[ $type . '_recipient' ];
        }

        // Use fallback or admin email
        if ( ! empty( $fallback ) && is_email( $fallback ) ) {
            return $fallback;
        }

        return get_option( 'admin_email' );
    }

    /**
     * Check if a specific email type is enabled.
     *
     * @param string $type Email type key (e.g., 'bug_report', 'weekly_profit').
     * @return bool        True if enabled, false otherwise.
     */
    public static function is_email_enabled( $type ) {
        $email_settings = get_option( 'cirrusly_email_settings', array() );
        
        // Default to enabled if not explicitly disabled
        if ( ! isset( $email_settings[ $type . '_enabled' ] ) ) {
            return true;
        }

        return (bool) $email_settings[ $type . '_enabled' ];
    }

    /**
     * Log an email send attempt.
     *
     * @param string $to      Recipient email address.
     * @param string $subject Email subject.
     * @param string $status  'success' or 'failed'.
     * @param string $type    Email content type ('html' or 'plain').
     */
    private static function log_email( $to, $subject, $status, $type = 'html' ) {
        $log = get_option( 'cirrusly_email_log', array() );

        // Add new entry
        $log[] = array(
            'timestamp' => current_time( 'timestamp' ),
            'recipient' => $to,
            'subject'   => $subject,
            'status'    => $status,
            'type'      => $type,
        );

        // Keep only last 100 entries
        if ( count( $log ) > 100 ) {
            $log = array_slice( $log, -100 );
        }

        // Remove entries older than 30 days
        $thirty_days_ago = current_time( 'timestamp' ) - ( 30 * DAY_IN_SECONDS );
        $log = array_filter( $log, function( $entry ) use ( $thirty_days_ago ) {
            return $entry['timestamp'] > $thirty_days_ago;
        } );

        update_option( 'cirrusly_email_log', array_values( $log ), false );
    }

    /**
     * Get email log entries.
     *
     * @param int $limit Maximum number of entries to return (default: 100).
     * @return array     Array of log entries.
     */
    public static function get_email_log( $limit = 100 ) {
        $log = get_option( 'cirrusly_email_log', array() );
        
        // Sort by timestamp descending (newest first)
        usort( $log, function( $a, $b ) {
            return $b['timestamp'] - $a['timestamp'];
        } );

        return array_slice( $log, 0, $limit );
    }

    /**
     * Helper to return text/html content type.
     */
    public static function get_html_content_type() {
        return 'text/html';
    }

    /**
     * Generate standard From headers based on site settings.
     * Refactors logic previously found in Core.
     */
    private static function get_headers() {
        $admin_email = get_option( 'admin_email' );
        if ( ! is_email( $admin_email ) ) {
            return array(); // Let wp_mail use its default From header
        }
        $site_title  = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
        
        // You could add a filter here to allow overriding the sender
        return array( 'From: ' . $site_title . ' <' . $admin_email . '>' );
    }
}