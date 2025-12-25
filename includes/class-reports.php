<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_Reports {

    /**
     * Hooks the report generation to the scheduled action and ensures cron is scheduled.
     */
    public static function init() {
        add_action( 'cirrusly_weekly_profit_report', array( __CLASS__, 'dispatch_weekly_report' ) );

        // Schedule cron if not already scheduled - use option flag to avoid repeated checks
        if ( ! get_option( 'cirrusly_weekly_report_scheduled', false ) ) {
            if ( ! wp_next_scheduled( 'cirrusly_weekly_profit_report' ) ) {
                $scheduled = wp_schedule_event( time(), 'weekly', 'cirrusly_weekly_profit_report' );

                if ( false !== $scheduled ) {
                    // Only set flag on successful scheduling
                    update_option( 'cirrusly_weekly_report_scheduled', true, false );
                } else {
                    // Log error for debugging - don't set flag so retry is possible
                    error_log( 'Cirrusly Commerce: Failed to schedule weekly profit report cron event' );
                }
            } else {
                // Cron is already scheduled - sync the flag state
                update_option( 'cirrusly_weekly_report_scheduled', true, false );
            }
        } else {
            // Periodically verify cron still exists (e.g., once per week)
            $last_verify = get_option( 'cirrusly_weekly_report_last_verify', 0 );
            if ( time() - $last_verify > WEEK_IN_SECONDS ) {
                if ( ! wp_next_scheduled( 'cirrusly_weekly_profit_report' ) ) {
                    // Cron was removed - reset flag to allow rescheduling
                    delete_option( 'cirrusly_weekly_report_scheduled' );
                    error_log( 'Cirrusly Commerce: Weekly profit report cron was removed, flag reset for rescheduling' );
                }
                update_option( 'cirrusly_weekly_report_last_verify', time(), false );
            }
        }
    }

    /**
     * Dispatcher: Checks configuration and Pro status before loading the logic.
     */
    public static function dispatch_weekly_report() {
        // 1. Check if enabled
        $scan_cfg = get_option( 'cirrusly_scan_config', array() );
        
        $general_email = !empty($scan_cfg['enable_email_report']) && $scan_cfg['enable_email_report'] === 'yes';
        $weekly_email  = !empty($scan_cfg['alert_weekly_report']) && $scan_cfg['alert_weekly_report'] === 'yes';

        // If neither is enabled, abort. 
        if ( ! $general_email && ! $weekly_email ) return;

        // 2. Pro Check
        if ( ! class_exists( 'Cirrusly_Commerce_Core' ) || ! Cirrusly_Commerce_Core::cirrusly_is_pro() ) {
            return;
        }

        // 3. Load and Run Pro Logic
        // Following the pattern of dynamically loading Pro files only when needed
        $pro_file = plugin_dir_path( __FILE__ ) . 'pro/class-reports-pro.php';
        
        if ( file_exists( $pro_file ) ) {
            require_once $pro_file;
            if ( class_exists( 'Cirrusly_Commerce_Reports_Pro' ) ) {
                Cirrusly_Commerce_Reports_Pro::generate_and_send( $scan_cfg );
            }
        }
    }
}