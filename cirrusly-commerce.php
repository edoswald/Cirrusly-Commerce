<?php
/**
 * Plugin Name: Cirrusly Commerce
 * Plugin URI: https://commerce.cirruslyweather.com
 * Description: The Financial Operating System for WooCommerce that doesn't cost an arm and a leg.
 * Version: 1.7
 * Author: Cirrusly Weather
 * Author URI: https://cirruslyweather.com
 * Text Domain: cirrusly-commerce
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * @fs_premium_only /includes/pro/, /assets/js/pro/, /assets/css/pro/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Composer autoloader if available.
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
}

define( 'CIRRUSLY_COMMERCE_VERSION', '1.7' );
define( 'CIRRUSLY_COMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'CIRRUSLY_COMMERCE_URL', plugin_dir_url( __FILE__ ) );


if ( ! function_exists( 'cirrusly_fs' ) ) {
    /**
     * Provide access to the plugin's initialized Freemius SDK instance.
     *
     * Initializes the Freemius SDK on first invocation and returns the shared instance.
     *
     * @return object The Freemius SDK instance used by the plugin.
     */
    function cirrusly_fs() {
        global $cirrusly_fs;

        if ( ! isset( $cirrusly_fs ) ) {
            // Include Freemius SDK.
            require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

            $cirrusly_fs = fs_dynamic_init( array(
                'id'                  => '22048',
                'slug'                => 'cirrusly-commerce',
                'type'                => 'plugin',
                'public_key'          => 'pk_34dc77b4bc7764037f0e348daac4a',
                'is_premium'          => true,
                'premium_suffix'      => 'Pro',
                // If your plugin is a serviceware, set this option to false.
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'is_org_compliant'    => true,
                // Automatically removed in the free version. If you're not using the
                // auto-generated free version, delete this line before uploading to wp.org.
                'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
                'trial'               => array(
                    'days'               => 3,
                    'is_require_payment' => false,
                ),
                'menu'                => array(
                    'slug'           => 'cirrusly-commerce',
                    'contact'        => false,
                    'support'        => false,
                ),
            ) );

            // Hooks to persist Install API Token from response and auto-generate API keys
            $cirrusly_fs->add_action( 'after_account_connection', function( $_user, $_account, $install ) {
                $token = ( is_object( $install ) && ! empty( $install->install_api_token ) )
                    ? sanitize_text_field( (string) $install->install_api_token )
                    : '';
                if ( '' !== $token ) {
                    update_option( 'cirrusly_install_api_token', $token, false );
                }

                // Auto-generate API key on first connection (free tier gets key immediately)
                if ( is_object( $install ) && isset( $install->id ) && isset( $_user->id ) ) {
                    if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/pro/class-api-key-manager.php' ) ) {
                        require_once plugin_dir_path( __FILE__ ) . 'includes/pro/class-api-key-manager.php';

                        $plan_id = isset( $install->plan_id ) ? $install->plan_id : Cirrusly_Commerce_API_Key_Manager::PLAN_ID_FREE; // Default to free
                        $result = Cirrusly_Commerce_API_Key_Manager::request_api_key(
                            $install->id,
                            $_user->id,
                            $plan_id
                        );

                        if ( ! is_wp_error( $result ) && isset( $result['api_key'] ) ) {
                            Cirrusly_Commerce_API_Key_Manager::store_api_key( $result['api_key'], $result );
                        }
                    }
                }
            }, 10, 3 );

            $cirrusly_fs->add_action( 'after_license_activation', function( $_license, $install ) {
                $token = ( is_object( $install ) && ! empty( $install->install_api_token ) )
                    ? sanitize_text_field( (string) $install->install_api_token )
                    : '';
                if ( '' !== $token ) {
                    update_option( 'cirrusly_install_api_token', $token, false );
                }

                // Auto-generate or update API key on license activation
                if ( is_object( $install ) && isset( $install->id ) && is_object( $_license ) ) {
                    if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/pro/class-api-key-manager.php' ) ) {
                        require_once plugin_dir_path( __FILE__ ) . 'includes/pro/class-api-key-manager.php';

                        $user = cirrusly_fs()->get_user();
                        $plan_id = isset( $_license->plan_id ) ? $_license->plan_id : Cirrusly_Commerce_API_Key_Manager::PLAN_ID_FREE;

                        $result = Cirrusly_Commerce_API_Key_Manager::request_api_key(
                            $install->id,
                            $user ? $user->id : '',
                            $plan_id
                        );

                        if ( ! is_wp_error( $result ) && isset( $result['api_key'] ) ) {
                            Cirrusly_Commerce_API_Key_Manager::store_api_key( $result['api_key'], $result );
                        }
                    }
                }
            }, 10, 2 );

            // Hook for plan changes (upgrade/downgrade)
            $cirrusly_fs->add_action( 'after_plan_change', function( $new_plan, $_license, $install ) {
                if ( is_object( $install ) && isset( $install->id ) && is_object( $new_plan ) ) {
                    if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/pro/class-api-key-manager.php' ) ) {
                        require_once plugin_dir_path( __FILE__ ) . 'includes/pro/class-api-key-manager.php';

                        $plan_id = isset( $new_plan->id ) ? $new_plan->id : Cirrusly_Commerce_API_Key_Manager::PLAN_ID_FREE;
                        Cirrusly_Commerce_API_Key_Manager::update_plan_id( $install->id, $plan_id );
                    }
                }
            }, 10, 3 );

            // Hook for license deactivation
            $cirrusly_fs->add_action( 'after_license_deactivation', function( $_license, $install ) {
                if ( is_object( $install ) && isset( $install->id ) ) {
                    if ( file_exists( plugin_dir_path( __FILE__ ) . 'includes/pro/class-api-key-manager.php' ) ) {
                        require_once plugin_dir_path( __FILE__ ) . 'includes/pro/class-api-key-manager.php';
                        Cirrusly_Commerce_API_Key_Manager::revoke_api_key( $install->id );
                    }
                }
            }, 10, 2 );

        }

        return $cirrusly_fs;
    }

    // Init Freemius.
    cirrusly_fs();
    // Signal that SDK was initiated.
    do_action( 'cirrusly_fs_loaded' );

    // Suppress Freemius admin notices on setup wizard page
    function cirrusly_suppress_freemius_notices() {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
        if ( 'cirrusly-setup' === $page ) {
            cirrusly_fs()->add_filter( 'show_admin_notice', '__return_false' );
            cirrusly_fs()->add_filter( 'show_activation_notice', '__return_false' );
            cirrusly_fs()->add_filter( 'show_trial_notice', '__return_false' );
        }
    }
    add_action( 'admin_init', 'cirrusly_suppress_freemius_notices' );
}

if ( ! class_exists( 'Cirrusly_Commerce_Core' ) ) {
	include_once dirname( __FILE__ ) . '/includes/class-core.php';
}

class Cirrusly_Commerce_Main {

    private static $instance = null;

    /**
     * Retrieve the singleton instance of the main plugin class.
     *
     * @return self The shared Cirrusly_Commerce_Main instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bootstraps the plugin: loads module files, instantiates components, and registers hooks.
     *
     * Loads standard modules from the includes directory, conditionally loads pro and admin
     * modules when available, creates instances of plugin components (including optional pro
     * modules), initializes the help subsystem, and registers activation/deactivation hooks
     * along with the cron schedule and plugin action links filters.
     */
    public function __construct() {
        $includes_path = plugin_dir_path( __FILE__ ) . 'includes/';

        // 1. Load Standard Modules
        require_once $includes_path . 'class-frontend-assets.php'; // NEW
        require_once $includes_path . 'class-gmc.php';
        require_once $includes_path . 'class-pricing.php';
        require_once $includes_path . 'class-audit.php';
        require_once $includes_path . 'class-reviews.php';
        require_once $includes_path . 'class-blocks.php';
        require_once $includes_path . 'class-compatibility.php';
        require_once $includes_path . 'class-badges.php';
        require_once $includes_path . 'class-manual.php';
        require_once $includes_path . 'class-countdown.php';
        require_once $includes_path . 'class-help.php';

        // 2. Load Pro-Only Modules
        if ( Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            if ( ! wp_doing_cron() && file_exists( $includes_path . 'pro/class-automated-discounts.php' ) ) {
                require_once $includes_path . 'pro/class-automated-discounts.php';
            }
            if ( is_admin() && file_exists( $includes_path . 'pro/class-analytics-pro.php' ) ) {
                require_once $includes_path . 'pro/class-analytics-pro.php';
            }
            if ( file_exists( $includes_path . 'pro/class-product-studio.php' ) ) {
                require_once $includes_path . 'pro/class-product-studio.php';
            }
        }

        // 2.5 Load Admin Setup Wizard & Debug UI
        if ( is_admin() ) {
            if ( file_exists( $includes_path . 'admin/class-setup-wizard.php' ) ) {
                require_once $includes_path . 'admin/class-setup-wizard.php';
            }
            if ( file_exists( $includes_path . 'admin/class-debug-ui.php' ) ) {
                require_once $includes_path . 'admin/class-debug-ui.php';
            }
        }

        // 3. Initialize Modules
        new Cirrusly_Commerce_Frontend_Assets(); // NEW
        new Cirrusly_Commerce_Core();
        new Cirrusly_Commerce_GMC();
        new Cirrusly_Commerce_Pricing();
        new Cirrusly_Commerce_Audit();
        new Cirrusly_Commerce_Reviews();
        new Cirrusly_Commerce_Blocks();
        new Cirrusly_Commerce_Compatibility();
        new Cirrusly_Commerce_Badges();
        new Cirrusly_Commerce_Countdown();
        new Cirrusly_Commerce_Manual();
        
        if ( class_exists( 'Cirrusly_Commerce_Debug_UI' ) ) {
            new Cirrusly_Commerce_Debug_UI();
        }
        
        if ( class_exists( 'Cirrusly_Commerce_Automated_Discounts' ) ) {
            new Cirrusly_Commerce_Automated_Discounts();
        }
        if ( is_admin() && class_exists( 'Cirrusly_Commerce_Analytics_Pro' ) ) {
            new Cirrusly_Commerce_Analytics_Pro();
        }
        if ( class_exists( 'Cirrusly_Commerce_Product_Studio' ) ) {
            new Cirrusly_Commerce_Product_Studio();
        }
        
        Cirrusly_Commerce_Help::init();

        // 4. Register Hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        
        add_filter( 'cron_schedules', array( $this, 'add_weekly_schedule' ) );
        add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'add_settings_link' ) );
    }

    /**
     * Ensure a 'weekly' cron schedule exists in the provided schedules array.
     *
     * @param array $schedules Associative array of cron schedules keyed by schedule name.
     * @return array The schedules array, guaranteed to include a 'weekly' schedule with a 7-day interval and label "Once Weekly".
     */
    public function add_weekly_schedule( $schedules ) {
        if ( ! isset( $schedules['weekly'] ) ) {
            $schedules['weekly'] = array(
                'interval' => 7 * DAY_IN_SECONDS,
                'display'  => __( 'Once Weekly', 'cirrusly-commerce' )
            );
        }
        return $schedules;
    }

    /**
     * Perform plugin activation tasks: schedule cron events, migrate legacy options, and set an activation redirect transient.
     *
     * Uses an option flag system to prevent duplicate scheduling checks on every page load. Schedules a daily
     * 'cirrusly_gmc_daily_scan' and a weekly 'cirrusly_weekly_profit_report' cron event if not already scheduled,
     * and sets corresponding option flags on successful scheduling. Migrates the legacy
     * 'woocommerce_enable_cost_of_goods_sold' option into 'cirrusly_enable_cost_of_goods_sold' when present,
     * or sets 'cirrusly_enable_cost_of_goods_sold' to 'yes' when no value exists. Copies a legacy merchant ID from
     * 'cirrusly_gmc_merchant_id' into the 'merchant_id_pro' key of the 'cirrusly_scan_config' option if needed.
     * Finally, sets a short-lived 'cirrusly_activation_redirect' transient to trigger a post-activation redirect.
     */
    public function activate() {
        // Schedule daily GMC scan with option flag system (prevents duplicate scheduling checks)
        if ( ! get_option( 'cirrusly_gmc_daily_scan_scheduled', false ) ) {
            if ( ! wp_next_scheduled( 'cirrusly_gmc_daily_scan' ) ) {
                $scheduled = wp_schedule_event( time(), 'daily', 'cirrusly_gmc_daily_scan' );

                if ( false !== $scheduled ) {
                    // Only set flag on successful scheduling
                    update_option( 'cirrusly_gmc_daily_scan_scheduled', true, false );
                }
            } else {
                // Cron is already scheduled - sync the flag state
                update_option( 'cirrusly_gmc_daily_scan_scheduled', true, false );
            }
        }

        // Schedule weekly profit report with option flag system
        if ( ! get_option( 'cirrusly_weekly_report_scheduled', false ) ) {
            if ( ! wp_next_scheduled( 'cirrusly_weekly_profit_report' ) ) {
                $scheduled = wp_schedule_event( time(), 'weekly', 'cirrusly_weekly_profit_report' );

                if ( false !== $scheduled ) {
                    // Only set flag on successful scheduling
                    update_option( 'cirrusly_weekly_report_scheduled', true, false );
                }
            } else {
                // Cron is already scheduled - sync the flag state
                update_option( 'cirrusly_weekly_report_scheduled', true, false );
            }
        }
        
        $old_value = get_option( 'woocommerce_enable_cost_of_goods_sold', null );
        $new_value = get_option( 'cirrusly_enable_cost_of_goods_sold', null );

        if ( null !== $old_value && null === $new_value ) {
            update_option( 'cirrusly_enable_cost_of_goods_sold', $old_value );
            delete_option( 'woocommerce_enable_cost_of_goods_sold' );
        } elseif ( null === $new_value ) {
            update_option( 'cirrusly_enable_cost_of_goods_sold', 'yes' );
        }

        $legacy_id = get_option( 'cirrusly_gmc_merchant_id' );
        $scan_config = get_option( 'cirrusly_scan_config', array() );
        
        if ( ! empty( $legacy_id ) && empty( $scan_config['merchant_id_pro'] ) ) {
            $scan_config['merchant_id_pro'] = $legacy_id;
            update_option( 'cirrusly_scan_config', $scan_config );
            delete_option( 'cirrusly_gmc_merchant_id' );

        }
        set_transient( 'cirrusly_activation_redirect', true, 60 );
    }

    /**
     * Removes the plugin's scheduled background tasks.
     *
     * Clears any scheduled 'cirrusly_gmc_daily_scan' and 'cirrusly_weekly_profit_report' cron hooks so those events will no longer run.
     */
    public function deactivate() {
        wp_clear_scheduled_hook( 'cirrusly_gmc_daily_scan' );
        wp_clear_scheduled_hook( 'cirrusly_weekly_profit_report' );

        // Clean up cron scheduling flags and verification timestamps
        delete_option( 'cirrusly_gmc_daily_scan_scheduled' );
        delete_option( 'cirrusly_weekly_report_scheduled' );
        delete_option( 'cirrusly_weekly_report_last_verify' );
        delete_option( 'cirrusly_gmc_daily_sync_scheduled' );
        delete_option( 'cirrusly_gmc_cron_last_verify' );
    }

    /**
     * Add plugin action links for the Cirrusly settings and (optionally) a Go Pro upgrade.
     *
     * Prepends a "Settings" link to the provided plugin action links and appends a prominent
     * "Go Pro" upgrade link when the Freemius SDK is available and the current user is not paying.
     *
     * @param array $links Existing plugin action links.
     * @return array Modified plugin action links including the Settings link and, if applicable, Go Pro.
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . esc_url( admin_url( 'admin.php?page=cirrusly-settings' ) ) . '">Settings</a>';
        array_unshift( $links, $settings_link );
        
        if ( function_exists('cirrusly_fs') && cirrusly_fs() && cirrusly_fs()->is_not_paying() ) {
            $links['go_pro'] = '<a href="' . cirrusly_fs()->get_upgrade_url() . '" style="color:#d63638;font-weight:bold;">Go Pro</a>';
        }
        return $links;
    }
}
Cirrusly_Commerce_Main::instance();
