<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Cirrusly_Commerce_Automated_Discounts {

    const SESSION_KEY_PREFIX = 'cirrusly_google_ad_';
    const TOKEN_PARAM = 'pv2';

    private static $instance = null;
    private static $hooks_registered = false;

    /**
     * Singleton instance.
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the automated discounts integration and register WordPress/WooCommerce hooks.
     */
    public function __construct() {
        // Prevent multiple hook registrations
        if ( self::$hooks_registered ) {
            return;
        }
        self::$hooks_registered = true;

        if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Class instantiated. Registering hooks...');

        // Logic
        add_action( 'template_redirect', array( $this, 'capture_google_token' ) );
        add_filter( 'woocommerce_get_price_html', array( $this, 'override_price_display' ), 99, 2 );
        add_filter( 'woocommerce_product_get_price', array( $this, 'override_price_value' ), 99, 2 );
        add_filter( 'woocommerce_product_get_sale_price', array( $this, 'override_price_value' ), 99, 2 );
        add_filter( 'woocommerce_product_variation_get_price', array( $this, 'override_price_value' ), 99, 2 );
        add_filter( 'woocommerce_product_variation_get_sale_price', array( $this, 'override_price_value' ), 99, 2 );
        add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_discount_to_cart' ), 99, 1 );
        add_action( 'send_headers', array( $this, 'prevent_caching_if_active' ) );

        // Debug endpoint for testing (only with WP_DEBUG and admin capability)
        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            add_action( 'template_redirect', array( $this, 'debug_test_discount' ), 5 );
        }
    }

    /**
     * Debug endpoint to test discount application without JWT.
     * URL: /any-product/?cirrusly_test_discount=PRODUCT_ID&test_price=PRICE
     * Requires WP_DEBUG and manage_options capability.
     */
    public function debug_test_discount() {
        if ( ! isset( $_GET['cirrusly_test_discount'] ) || ! current_user_can('manage_options') ) {
            return;
        }

        $product_id = intval( $_GET['cirrusly_test_discount'] );
        $test_price = isset( $_GET['test_price'] ) ? floatval( $_GET['test_price'] ) : 10.00;

        if ( ! function_exists('WC') ) {
            wp_die('WooCommerce not loaded');
        }

        // Ensure cart and session are initialized
        if ( is_null( WC()->cart ) ) {
            WC()->cart = new WC_Cart();
        }

        if ( ! WC()->session ) {
            WC()->initialize_session();
        }

        if ( ! WC()->session ) {
            wp_die('WooCommerce session not available');
        }

        // Start session if not started
        if ( ! WC()->session->get_customer_id() ) {
            WC()->session->set_customer_session_cookie( true );
        }

        $data = array(
            'price' => $test_price,
            'exp'   => time() + ( 48 * HOUR_IN_SECONDS )
        );

        WC()->session->set( self::SESSION_KEY_PREFIX . $product_id, $data );
        WC()->session->set( 'cirrusly_has_active_discount', true );
        WC()->session->save_data(); // Force save session data

        error_log('Cirrusly Discount DEBUG: Test discount set for product ' . $product_id . ' at price ' . $test_price);
        error_log('Cirrusly Discount DEBUG: Session data after save: ' . print_r(WC()->session->get( self::SESSION_KEY_PREFIX . $product_id ), true));
        error_log('Cirrusly Discount DEBUG: Customer ID: ' . WC()->session->get_customer_id());
        error_log('Cirrusly Discount DEBUG: Session cookie: ' . (isset($_COOKIE[WC()->session->get_session_cookie_name()]) ? 'SET' : 'NOT SET'));

        // Don't redirect - show success message with link to test
        $product = wc_get_product( $product_id );
        if ( $product ) {
            $product_url = get_permalink( $product_id );
            wp_die('
                <h1>Debug Discount Set Successfully</h1>
                <p>Discount of <strong>$' . number_format($test_price, 2) . '</strong> has been set for product #' . $product_id . '</p>
                <p>Session ID: <code>' . WC()->session->get_customer_id() . '</code></p>
                <p><strong>Important:</strong> Stay in the same browser window/tab to maintain your session.</p>
                <p><a href="' . esc_url($product_url) . '" style="display:inline-block;background:#2271b1;color:white;padding:10px 20px;text-decoration:none;border-radius:4px;">View Product Page &rarr;</a></p>
                <p><small>Session data: <code>' . esc_html(print_r($data, true)) . '</code></small></p>
            ', 'Discount Set', array('response' => 200));
        } else {
            wp_die('Product not found for ID: ' . $product_id);
        }
    }

    /**
     * Render the admin settings UI for Google Automated Discounts.
     */
    public function render_settings_field() {
        $scan_cfg = get_option('cirrusly_scan_config', array());
        $checked = isset( $scan_cfg['enable_automated_discounts'] ) && $scan_cfg['enable_automated_discounts'] === 'yes';
        $merchant_id = isset($scan_cfg['merchant_id']) ? $scan_cfg['merchant_id'] : '';
        // Note: Google Public Key field retained for UI consistency.
        $public_key = isset($scan_cfg['google_public_key']) ? $scan_cfg['google_public_key'] : '';
        ?>
        <div class="cirrusly-ad-settings" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #c3c4c7;">
            <h4 style="margin: 0 0 10px 0;">Google Automated Discounts</h4>
            <label><input type="checkbox" name="cirrusly_scan_config[enable_automated_discounts]" value="yes" <?php checked( $checked ); ?>> <strong>Enable Dynamic Pricing</strong></label>
            <p class="description">Allows Google to dynamically lower prices via Shopping Ads. Requires Cost of Goods and Google Min Price.</p>
            <div class="cirrusly-ad-fields" style="margin-left: 25px; background: #fff; padding: 15px; border: 1px solid #c3c4c7; border-radius:4px;">
                <p><label><strong>Merchant ID</strong></label><br><input type="text" name="cirrusly_scan_config[merchant_id]" value="<?php echo esc_attr( $merchant_id ); ?>" class="regular-text"></p>
                <p><label><strong>Google Public Key (PEM)</strong></label><br><textarea name="cirrusly_scan_config[google_public_key]" rows="5" class="large-text code" placeholder="Paste Google Public Key here" style="background:#f0f0f1; color:#50575e;"><?php echo esc_textarea( $public_key ); ?></textarea></p>
            </div>
        </div>
        <?php
    }

    /**
     * Captures a Google Automated Discounts token from the request.
     */
    public function capture_google_token() {
        if ( is_admin() || ! isset( $_GET[ self::TOKEN_PARAM ] ) ) return;

        if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: capture_google_token() called. Token param present.');

        $cfg = get_option('cirrusly_scan_config', array());
        if ( empty($cfg['enable_automated_discounts']) || $cfg['enable_automated_discounts'] !== 'yes' ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Feature not enabled in settings.');
            return;
        }

        // Ensure WooCommerce is loaded
        if ( ! function_exists('WC') ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: WooCommerce not loaded.');
            return;
        }

        // Initialize session if not already initialized
        if ( ! WC()->session ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Initializing WooCommerce session...');
            WC()->initialize_session();
        }

        if ( ! WC()->session ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: WooCommerce session not available after initialization attempt.');
            return;
        }

        // Unwrap raw request with wp_unslash before sanitizing
        // JWTs contain base64url chars and dots - sanitize while preserving structure
        $token = preg_replace( '/[^A-Za-z0-9_.\-]/', '', wp_unslash( $_GET[ self::TOKEN_PARAM ] ) );

        if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Processing token: ' . substr($token, 0, 50) . '...');

        if ( $payload = $this->verify_jwt( $token ) ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: JWT verified successfully. Payload: ' . print_r($payload, true));
            $this->store_discount_session( $payload );
        } else {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: JWT verification failed.');
        }
    }

    /**
     * Verifies a Google-signed JWT using the configured Public Key (ES256).
     * * Note: Google Automated Discounts uses ES256 signatures with a static public key provided
     * in the Merchant Center. Standard Google_Client::verifyIdToken defaults to OIDC RS256 keys,
     * so we use Firebase\JWT\JWT directly for this specific integration.
     *
     * @param string $token The JWT to verify.
     * @return array|false The decoded JWT payload when verification succeeds, `false` on failure.
     */
    private function verify_jwt( $token ) {
        $cfg = get_option('cirrusly_scan_config', array());
        
        // Basic shape/size guard
        if ( ! is_string( $token ) || strlen( $token ) > 4096 ) {
            return false;
        }

        // 1. Get Configuration
        $merchant_id = isset( $cfg['merchant_id'] ) ? $cfg['merchant_id'] : get_option( 'cirrusly_gmc_merchant_id' );
        $public_key  = isset( $cfg['google_public_key'] ) ? $cfg['google_public_key'] : '';
        
        if ( empty( $merchant_id ) ) {
             if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Missing Merchant ID.');
             return false;
        }

        if ( empty( $public_key ) ) {
             if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Missing Google Public Key.');
             return false;
        }

        try {
            // Ensure Firebase JWT classes are available (provided by google/apiclient)
            if ( ! class_exists( '\Firebase\JWT\JWT' ) || ! class_exists( '\Firebase\JWT\Key' ) ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Firebase JWT class not found.');
                return false;
            }

            // 2. Decode & Verify Signature (ES256)
            // Note: Automated Discounts use ES256. 'exp' (Expiration) is automatically verified by JWT::decode.
            $payload = \Firebase\JWT\JWT::decode( $token, new \Firebase\JWT\Key( $public_key, 'ES256' ) );
            
            // Convert object to array for consistency
            $payload = (array) $payload;

            // 3. Validate Merchant ID (Claim 'm')
            // The token's 'm' claim must match the configured Merchant ID.
            if ( ! isset( $payload['m'] ) || (string) $payload['m'] !== (string) $merchant_id ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log( 'Cirrusly Commerce JWT Fail: Merchant ID mismatch.' );
                return false;
            }

            // 4. Validate Currency (Claim 'c')
            if ( isset( $payload['c'] ) && $payload['c'] !== get_woocommerce_currency() ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log( 'Cirrusly Commerce JWT Fail: Currency mismatch. Expected ' . get_woocommerce_currency() . ', got ' . $payload['c'] );
                return false;
            }

            // 5. Validate Required Claims
            // 'o' = Offer ID, 'p' = Price
            if ( ! isset( $payload['o'] ) || ! isset( $payload['p'] ) ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log( 'Cirrusly Commerce JWT Fail: Missing required claims (o or p).' );
                return false;
            }

            return $payload;

        } catch ( \Exception $e ) {
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) error_log( 'Cirrusly Discount: Token verification failed - ' . $e->getMessage() );
            return false;
        }
    }
    
    /**
     * Stores the validated discount in the WooCommerce Session.
     */
    private function store_discount_session( $payload ) {
        if ( ! isset( WC()->session ) ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Session not available in store_discount_session.');
            return;
        }

        // Claims: 'o' = Offer ID (SKU/ID), 'p' = Price, 'exp' = Expiration
        $offer_id = isset( $payload['o'] ) ? $payload['o'] : '';
        $price    = isset( $payload['p'] ) ? floatval( $payload['p'] ) : 0;
        $expiry   = isset( $payload['exp'] ) ? (int) $payload['exp'] : time() + ( 48 * HOUR_IN_SECONDS );

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Cirrusly Discount: Offer ID: ' . $offer_id . ', Price: ' . $price . ', Expiry: ' . $expiry);
        }

        if ( ! $offer_id || $price <= 0 ) {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Invalid offer ID or price.');
            return;
        }

        // Map Offer ID to Product ID
        $product_id = wc_get_product_id_by_sku( $offer_id );
        if ( ! $product_id ) {
            $product_id = intval( $offer_id );
        }

        if ( defined('WP_DEBUG') && WP_DEBUG ) {
            error_log('Cirrusly Discount: Mapped offer ID "' . $offer_id . '" to product ID: ' . $product_id);
        }

        if ( $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Product not found for ID: ' . $product_id);
                return;
            }

            // Safety: Discount must be lower than current price (regular or sale)
            $regular_price = $product->get_regular_price();
            $current_price = $product->get_price(); // Gets sale price if on sale, otherwise regular

            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log('Cirrusly Discount: Product regular price: ' . $regular_price . ', Current price: ' . $current_price . ', JWT discount price: ' . $price);
            }

            // Reject if discount is not actually a discount (must be lower than current price)
            if ( $current_price && $price >= $current_price ) {
                if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: Discount rejected - JWT price (' . $price . ') not lower than current price (' . $current_price . ').');
                return;
            }

            $data = array(
                'price' => $price,
                'exp'   => $expiry
            );
            WC()->session->set( self::SESSION_KEY_PREFIX . $product_id, $data );
            // Set flag for faster cache header checks
            WC()->session->set( 'cirrusly_has_active_discount', true );

            if ( defined('WP_DEBUG') && WP_DEBUG ) {
                error_log('Cirrusly Discount: Successfully stored discount for product ID ' . $product_id . ' at price ' . $price);
            }
        } else {
            if ( defined('WP_DEBUG') && WP_DEBUG ) error_log('Cirrusly Discount: No product ID found for offer: ' . $offer_id);
        }
    }

    /**
     * Frontend Logic: Override the displayed price string (e.g. "<del>$20</del> $18")
     */
    public function override_price_display( $price_html, $product ) {
        $discount = self::get_active_discount( $product->get_id() );
        if ( ! $discount ) return $price_html;

        if ( $product->is_type( 'variable' ) ) {
            $regular_price = $product->get_variation_regular_price( 'min', true );
        } else {
            $regular_price = $product->get_regular_price();
        }

        return wc_format_sale_price( $regular_price, $discount['price'] );
    }

    /**
     * Logic: Override the raw price value (used by plugins/sorting)
     */
    public function override_price_value( $price, $product ) {
        $discount = self::get_active_discount( $product->get_id() );
        if ( $discount ) {
            return $discount['price'];
        }
        return $price;
    }

    /**
     * Cart Logic: Ensure they pay the discounted price.
     */
    public function apply_discount_to_cart( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart->get_cart() as $cart_item ) {
            $product_id = $cart_item['product_id'];
            $variation_id = isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0;

            $discount = self::get_active_discount( $variation_id ? $variation_id : $product_id );

            if ( $discount ) {
                $cart_item['data']->set_price( $discount['price'] );
            }
        }
    }

    /**
     * Helper: Retrieve active discount from session if valid.
     * Cleans up expired discount data and the active flag when needed.
     */
    public static function get_active_discount( $product_id ) {
        if ( ! isset( WC()->session ) ) return false;

        $session_key = self::SESSION_KEY_PREFIX . $product_id;
        $data = WC()->session->get( $session_key );

        if ( $data && isset( $data['exp'] ) && $data['exp'] > time() ) {
            return $data;
        }

        // Expired or invalid - clean up
        if ( $data ) {
            WC()->session->__unset( $session_key );

            // Check if any active discounts remain
            if ( ! self::has_any_active_discounts() ) {
                WC()->session->__unset( 'cirrusly_has_active_discount' );
            }
        }

        return false;
    }

    /**
     * Check if any active (non-expired) discounts exist in the session.
     *
     * @return bool True if at least one active discount exists.
     */
    private static function has_any_active_discounts() {
        if ( ! isset( WC()->session ) ) return false;

        $session_data = WC()->session->get_session_data();
        $current_time = time();

        foreach ( $session_data as $key => $value ) {
            // Only check discount session keys
            if ( strpos( $key, self::SESSION_KEY_PREFIX ) !== 0 ) {
                continue;
            }

            // Skip the current discount being checked (already known to be expired)
            $data = maybe_unserialize( $value );
            if ( is_array( $data ) && isset( $data['exp'] ) && $data['exp'] > $current_time ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Prevent caching if a discount session is active.
     * Optimized to use flag instead of iterating all session data.
     */
    public function prevent_caching_if_active() {
        if ( isset( $_GET[ self::TOKEN_PARAM ] ) ) {
            nocache_headers();
            return;
        }

        // Use direct flag check instead of iterating all session data
        if ( isset( WC()->session ) ) {
            $has_active_discount = WC()->session->get( 'cirrusly_has_active_discount' );
            if ( $has_active_discount ) {
                nocache_headers();
            }
        }
    }
}