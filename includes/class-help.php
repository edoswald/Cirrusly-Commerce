<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_Help {

    public static function init() {
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_script' ) );
        add_action( 'admin_footer', array( __CLASS__, 'render_modal' ) );
        
        // New Standard Action
        add_action( 'wp_ajax_cirrusly_submit_bug_report', array( __CLASS__, 'handle_bug_submission' ) );
        
        // Legacy Action (Deprecated)
        add_action( 'wp_ajax_cc_submit_bug_report', array( __CLASS__, 'handle_legacy_submission' ) );
    }

    public static function handle_legacy_submission() {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'AJAX action cc_submit_bug_report is deprecated. Use cirrusly_submit_bug_report.' );
        }
        self::handle_bug_submission();
    }

    public static function enqueue_script( $hook ) {
        if ( ! isset( $_GET['page'] ) || strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'cirrusly-' ) === false ) {
            return;
        }

        // Updated selectors and action names in JS
        wp_add_inline_script( 'cirrusly-admin-base-js', 'jQuery(document).ready(function($){
            $("#cirrusly-open-help-center").click(function(e){
                e.preventDefault();
                $("#cirrusly-help-backdrop, #cirrusly-help-modal").fadeIn(200);
            });
            $("#cirrusly-close-help, #cirrusly-help-backdrop").click(function(){
                $("#cirrusly-help-backdrop, #cirrusly-help-modal").fadeOut(200);
                $("#cirrusly-help-main-view").show();
                $("#cirrusly-help-form-view").hide();
                $("#cirrusly-bug-response").html("").hide();
            });
            $("#cirrusly-btn-bug-report").click(function(e){
                e.preventDefault();
                $("#cirrusly-help-main-view").hide();
                $("#cirrusly-help-form-view").fadeIn(200);
            });
            $("#cirrusly-btn-back-help").click(function(e){
                e.preventDefault();
                $("#cirrusly-help-form-view").hide();
                $("#cirrusly-help-main-view").fadeIn(200);
            });
            $("#cirrusly-bug-report-form").on("submit", function(e){
                e.preventDefault();
                var $form = $(this);
                var $btn  = $form.find("button[type=submit]");
                var $msg  = $("#cirrusly-bug-response");
                $btn.prop("disabled", true).text("Sending...");
                $msg.hide().removeClass("notice-error notice-success");
                var formData = $form.serialize() + "&system_info=" + encodeURIComponent($("#cirrusly-sys-info-text").val());
                $.post(ajaxurl, formData, function(response) {
                    $btn.prop("disabled", false).text("Send Report");
                    if ( response.success ) {
                        $form[0].reset();
                        $("#cirrusly-help-form-view").hide();
                        $("#cirrusly-help-main-view").fadeIn();
                        alert("Report sent successfully! We will be in touch shortly.");
                    } else {
                        $msg.addClass("notice notice-error").html("<p>" + (response.data || "Unknown error") + "</p>").show();
                    }
                }).fail(function() {
                    $btn.prop("disabled", false).text("Send Report");
                    $msg.addClass("notice notice-error").html("<p>Server error. Please try again later.</p>").show();
                });
            });
            
            // Copy system info to clipboard
            $(".cirrusly-copy-sys-info").click(function(e){
                e.preventDefault();
                var $btn = $(this);
                var copyText = document.getElementById("cirrusly-sys-info-text");
                navigator.clipboard.writeText(copyText.value).then(function(){
                    $btn.html("<span class=\"dashicons dashicons-yes\" style=\"line-height:inherit;\"></span> Copied!");
                    setTimeout(function(){
                        $btn.html("<span class=\"dashicons dashicons-clipboard\" style=\"line-height:inherit;\"></span> Copy");
                    }, 2000);
                }).catch(function(){
                    copyText.select();
                    document.execCommand("copy");
                    alert("Copied to clipboard!");
                });
            });
        });' );
        
        // Add CSS for hover effects and button styling
        wp_add_inline_style( 'cirrusly-admin-base', '
            .cirrusly-help-close-btn:hover { background: rgba(255,255,255,0.3) !important; border-color: rgba(255,255,255,0.5) !important; }
            .cirrusly-copy-sys-info:hover { background: #db2777 !important; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(236, 72, 153, 0.3); }
            .cirrusly-copy-sys-info:active { transform: translateY(0); }
            .cirrusly-help-body .cirrusly-btn-secondary { width: 80%; }
            .cirrusly-help-body .cirrusly-btn-success { width: 80%; }
        ' );
    }

    public static function render_button() {
        echo '<button type="button" id="cirrusly-open-help-center" class="cirrusly-btn-secondary" style="padding:6px 12px; border-radius:6px; font-weight:600; font-size:13px;"><span class="dashicons dashicons-editor-help" style="line-height:inherit; font-size:16px;"></span> Help</button>';
    }

    public static function render_modal() {
        if ( ! isset( $_GET['page'] ) || strpos( sanitize_text_field( wp_unslash( $_GET['page'] ) ), 'cirrusly-' ) === false ) {
            return;
        }
        $support_forum = 'https://wordpress.org/support/plugin/cirrusly-commerce/';
        $mailto = 'mailto:help@cirruslyweather.com?subject=Support%20Request';
        $debug_console = admin_url( 'admin.php?page=cirrusly-debug' );
        $current_user = wp_get_current_user();
        ?>
        <div id="cirrusly-help-backdrop" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.6); z-index:9999; backdrop-filter:blur(2px);"></div>
        <div id="cirrusly-help-modal" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); width:750px; max-width:90vw; background:#fff; box-shadow:0 8px 40px rgba(0,0,0,0.25); z-index:10000; border-radius:12px; overflow:hidden;">
            <div class="cirrusly-help-header" style="background:linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding:25px 30px; border-bottom:none; display:flex; justify-content:space-between; align-items:center; position:relative; overflow:hidden;">
                <div style="position:absolute; top:-50%; right:-20%; width:400px; height:400px; background:radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%); pointer-events:none;"></div>
                <h3 style="margin:0; font-size:20px; font-weight:700; color:#fff; display:flex; align-items:center; gap:10px; text-shadow:0 2px 4px rgba(0,0,0,0.1); position:relative; z-index:1;">
                    <span class="dashicons dashicons-editor-help" style="font-size:26px; color:#fff;"></span>
                    Help Center
                </h3>
                <button type="button" id="cirrusly-close-help" class="cirrusly-help-close-btn" style="background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.3); backdrop-filter:blur(10px); cursor:pointer; font-size:24px; line-height:1; color:#fff; transition:all 0.3s; border-radius:6px; width:32px; height:32px; display:flex; align-items:center; justify-content:center; position:relative; z-index:1;">&times;</button>
            </div>
            <div id="cirrusly-help-main-view" class="cirrusly-help-body" style="padding:0; display:flex; height:550px;">
                <div style="width:45%; padding:20px; border-right:1px solid #ddd; background:#fff; overflow-y:auto;">
                    <h4 style="margin-top:0; font-size:15px; font-weight:600; color:#2c3338; display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-book-alt" style="color:#2271b1; font-size:18px;"></span> 
                        Documentation
                    </h4>
                    <p style="color:#646970; font-size:13px; margin-bottom:15px; line-height:1.6;">Complete setup guides, tutorials, and troubleshooting tips.</p>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=cirrusly-manual') ); ?>" class="cirrusly-btn-secondary" style="justify-content:center; margin-bottom:12px; padding:10px 12px; border-radius:6px; font-weight:600; text-decoration:none;">
                        <span class="dashicons dashicons-media-text" style="line-height:inherit;"></span> User Manual
                    </a>
                    
                    <hr style="border:0; border-top:1px solid #e0e0e0; margin:15px 0;">
                    
                    <h4 style="margin-top:0; font-size:15px; font-weight:600; color:#2c3338; display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-groups" style="color:#667eea; font-size:18px;"></span> 
                        Community Support
                    </h4>
                    <p style="color:#646970; font-size:13px; margin-bottom:12px; line-height:1.6;">Free support on WordPress.org</p>
                    <a href="<?php echo esc_url( $support_forum ); ?>" target="_blank" class="cirrusly-btn-secondary" style="justify-content:center; margin-bottom:12px; padding:10px 12px; border-radius:6px; font-weight:600; text-decoration:none;">
                        <span class="dashicons dashicons-wordpress" style="line-height:inherit;"></span> Support Forum
                    </a>
                    
                    <hr style="border:0; border-top:1px solid #e0e0e0; margin:15px 0;">
                    
                    <h4 style="margin-top:0; font-size:15px; font-weight:600; color:#2c3338; display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-email-alt" style="color:#667eea; font-size:18px;"></span> 
                        Direct Support
                    </h4>
                    <p style="color:#646970; font-size:13px; margin-bottom:12px; line-height:1.6;">Need immediate assistance?</p>
                    <a href="<?php echo esc_url( $mailto ); ?>" class="cirrusly-btn-secondary" style="justify-content:center; margin-bottom:10px; padding:10px 12px; border-radius:6px; font-weight:600; text-decoration:none;">
                        <span class="dashicons dashicons-email" style="line-height:inherit;"></span> Email Support
                    </a>
                    <button type="button" id="cirrusly-btn-bug-report" class="cirrusly-btn-success" style="justify-content:center; padding:10px 12px; border-radius:6px; font-weight:600;">
                        <span class="dashicons dashicons-warning" style="font-size:16px; line-height:inherit;"></span> Submit Bug Report
                    </button>
                    
                    <hr style="border:0; border-top:1px solid #e0e0e0; margin:15px 0;">
                    
                    <h4 style="margin-top:0; font-size:15px; font-weight:600; color:#2c3338; display:flex; align-items:center; gap:8px;">
                        <span class="dashicons dashicons-admin-tools" style="color:#f0ad4e; font-size:18px;"></span> 
                        Developer Tools
                    </h4>
                    <p style="color:#646970; font-size:13px; margin-bottom:12px; line-height:1.6;">Advanced diagnostic tools for troubleshooting.</p>
                    <a href="<?php echo esc_url( $debug_console ); ?>" class="cirrusly-btn-secondary" style="justify-content:center; padding:10px 12px; border-radius:6px; font-weight:600; text-decoration:none;">
                        <span class="dashicons dashicons-code-standards" style="line-height:inherit;"></span> API Debug Console
                    </a>
                </div>
                <div style="width:55%; padding:20px; background:#f0f0f1;">
                    <h4 style="margin-top:0; font-size:15px; font-weight:600; color:#2c3338; display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                        <span style="display:flex; align-items:center; gap:8px;">
                            <span class="dashicons dashicons-admin-generic" style="color:#667eea; font-size:18px;"></span>
                            System Health
                        </span>
                        <button type="button" class="cirrusly-copy-sys-info" style="background:#ec4899; color:#fff; border:none; padding:8px 14px; border-radius:6px; font-weight:600; font-size:12px; cursor:pointer; transition:all 0.3s; display:inline-flex; align-items:center; gap:6px;">
                            <span class="dashicons dashicons-clipboard" style="line-height:inherit;"></span> Copy
                        </button>
                    </h4>
                    <p style="color:#646970; font-size:12px; margin-bottom:12px; line-height:1.5;">This diagnostic info will be automatically attached to bug reports.</p>
                    <textarea id="cirrusly-sys-info-text" style="width:100%; height:330px; font-family:'Courier New',Consolas,monospace; font-size:12px; background:#fff; border:1px solid #c3c4c7; border-radius:6px; padding:12px; white-space:pre; line-height:1.5; overflow-y:auto;" readonly><?php echo esc_textarea( self::get_system_info() ); ?></textarea>
                </div>
            </div>
            <div id="cirrusly-help-form-view" style="display:none; height:550px; padding:25px; background:#fff; overflow-y:auto;">
                <h4 style="margin-top:0; margin-bottom:25px; font-size:16px; font-weight:600; color:#2c3338; display:flex; align-items:center; gap:10px;">
                    <button type="button" id="cirrusly-btn-back-help" class="cirrusly-btn-secondary" style="padding:6px 12px; border-radius:6px; font-weight:600; font-size:13px;">
                        <span class="dashicons dashicons-arrow-left-alt2" style="line-height:inherit;"></span> Back
                    </button>
                    Submit Bug Report
                </h4>
                <div id="cirrusly-bug-response" style="display:none; margin-bottom:15px; padding:12px; border-radius:6px;"></div>
                <form id="cirrusly-bug-report-form">
                    <input type="hidden" name="action" value="cirrusly_submit_bug_report">
                    <?php wp_nonce_field( 'cirrusly_bug_report_nonce', 'security' ); ?>
                    <div style="display:flex; gap:15px; margin-bottom:15px;">
                        <div style="flex:1;">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#2c3338;">Your Email</label>
                            <input type="email" name="user_email" value="<?php echo esc_attr( $current_user->user_email ); ?>" required style="width:100%; padding:10px 12px; border:1px solid #c3c4c7; border-radius:6px; font-size:14px;">
                        </div>
                        <div style="flex:1;">
                            <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#2c3338;">Subject</label>
                            <input type="text" name="subject" placeholder="e.g., Fatal error on checkout" required style="width:100%; padding:10px 12px; border:1px solid #c3c4c7; border-radius:6px; font-size:14px;">
                        </div>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="display:block; margin-bottom:8px; font-weight:600; font-size:13px; color:#2c3338;">Issue Description</label>
                        <textarea name="message" rows="9" style="width:100%; padding:12px; border:1px solid #c3c4c7; border-radius:6px; font-size:14px; line-height:1.5; resize:vertical;" placeholder="Please describe what happened, what you expected to happen, and any steps to reproduce the issue." required></textarea>
                    </div>
                    <div style="text-align:right; border-top:1px solid #e0e0e0; padding-top:20px;">
                        <button type="submit" class="cirrusly-btn-success" style="padding:10px 24px; border-radius:6px; font-weight:600; font-size:14px;">
                            <span class="dashicons dashicons-email" style="line-height:inherit;"></span> Send Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    public static function handle_bug_submission() {
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }
        
        $nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
        $verified = false;

        // Check new standard nonce
        if ( wp_verify_nonce( $nonce, 'cirrusly_bug_report_nonce' ) ) {
            $verified = true;
        }
        // Check legacy nonce (deprecated)
        elseif ( wp_verify_nonce( $nonce, 'cc_bug_report_nonce' ) ) {
            $verified = true;
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Legacy nonce cc_bug_report_nonce used in handle_bug_submission. Use cirrusly_bug_report_nonce.' );
            }
        }

        if ( ! $verified ) {
            wp_send_json_error( 'Security check failed. Please refresh the page.' );
        }
        
        $user_email = isset( $_POST['user_email'] ) ? sanitize_email( wp_unslash( $_POST['user_email'] ) ) : '';
        $subject    = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
        $sys_info   = isset($_POST['system_info']) ? sanitize_textarea_field( wp_unslash( $_POST['system_info'] ) ) : 'Not provided';

        if ( ! is_email( $user_email ) ) {
            wp_send_json_error( 'Please provide a valid email address.' );
        }
        if ( ! class_exists( 'Cirrusly_Commerce_Mailer' ) ) {
            if ( defined( 'CIRRUSLY_COMMERCE_PATH' ) ) {
                require_once CIRRUSLY_COMMERCE_PATH . 'class-mailer.php';
            } else {
                wp_send_json_error( 'Mailer class not found.' );
            }
        }
        
        // Send email using template
        $to = 'help@cirruslyweather.com'; 
        $admin_subject = '[Bug Report] ' . $subject;
        $sent = Cirrusly_Commerce_Mailer::send_from_template(
            $to,
            'bug-report',
            array(
                'user_email' => $user_email,
                'subject'    => $subject,
                'message'    => $message,
                'sys_info'   => $sys_info,
            ),
            $admin_subject
        );
        if ( $sent ) {
            wp_send_json_success( 'Report sent.' );
        } else {
            wp_send_json_error( 'Could not send email. Please verify your server email settings.' );
        }
    }

    public static function get_system_info() {
        global $wp_version;
        $out  = "### System Info ###\n";
        $out .= "Site URL: " . site_url() . "\n";
        $out .= "WP Version: " . $wp_version . "\n";
        $out .= "WooCommerce: " . (class_exists('WooCommerce') ? WC()->version : 'Not Installed') . "\n";
        $out .= "Cirrusly Commerce: " . ( defined('CIRRUSLY_COMMERCE_VERSION') ? CIRRUSLY_COMMERCE_VERSION : 'Unknown' ) . "\n";
        $out .= "PHP Version: " . phpversion() . "\n";
        
        $server_software = isset( $_SERVER['SERVER_SOFTWARE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['SERVER_SOFTWARE'] ) ) : 'Unknown';
        $out .= "Server Software: " . esc_html( $server_software ) . "\n";
        
        $out .= "Active Plugins:\n";
        $plugins = get_option('active_plugins');
        if ( is_array( $plugins ) ) {
            foreach( $plugins as $p ) { $out .= "- " . esc_html( $p ) . "\n"; }
        }
        return $out;
    }

    public static function render_system_info() {
        echo esc_html( self::get_system_info() );
    }
}