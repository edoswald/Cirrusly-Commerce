<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Cirrusly_Commerce_Debug_UI {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_debug_page' ), 99 );
    }

    public function register_debug_page() {
        add_submenu_page(
            null, // Hidden from menu
            'API Debug Console',
            'API Debug',
            'manage_options',
            'cirrusly-debug',
            array( $this, 'render_debug_page' )
        );
    }

    public function render_debug_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $test_action = isset( $_GET['test_action'] ) ? sanitize_text_field( $_GET['test_action'] ) : 'gmc_scan';
        $debug_data = array();

        if ( isset( $_GET['run_test'] ) && check_admin_referer( 'cirrusly_debug_test' ) ) {
            $debug_data = $this->run_api_test( $test_action );
        }

        ?>
        <div class="wrap">
            <h1>🔧 Cirrusly API Debug Console</h1>
            <p>This page tests the API connection and shows detailed request/response data.</p>

            <form method="get" style="background: #fff; padding: 20px; border: 1px solid #ccc; margin: 20px 0;">
                <input type="hidden" name="page" value="cirrusly-debug">
                <?php wp_nonce_field( 'cirrusly_debug_test' ); ?>
                
                <label><strong>Test Action:</strong></label>
                <select name="test_action" style="width: 300px; margin: 0 10px;">
                    <option value="gmc_scan" <?php selected( $test_action, 'gmc_scan' ); ?>>GMC Scan</option>
                    <option value="fetch_account_status" <?php selected( $test_action, 'fetch_account_status' ); ?>>Fetch Account Status</option>
                    <option value="promo_list" <?php selected( $test_action, 'promo_list' ); ?>>Promotion List</option>
                    <option value="nlp_analyze" <?php selected( $test_action, 'nlp_analyze' ); ?>>NLP Analyze (with test data)</option>
                    <option value="validate_product_studio_api" <?php selected( $test_action, 'validate_product_studio_api' ); ?>>Validate Product Studio</option>
                    <option value="validate_product_studio_setup" <?php selected( $test_action, 'validate_product_studio_setup' ); ?>>🔍 Product Studio Full Setup Check</option>
                </select>
                
                <button type="submit" name="run_test" class="button button-primary" style="margin-left: 10px;">
                    🚀 Run Test
                </button>
            </form>

            <?php if ( ! empty( $debug_data ) ) : ?>
                <div style="background: #f0f0f1; padding: 20px; border: 2px solid #2271b1; border-radius: 4px; margin: 20px 0;">
                    <h2 style="margin-top: 0;">📋 Debug Output - Copy & Paste This:</h2>
                    <textarea readonly style="width: 100%; height: 500px; font-family: 'Courier New', monospace; font-size: 12px; background: #fff; padding: 15px; border: 1px solid #ccc;"><?php echo esc_textarea( $this->format_debug_output( $debug_data ) ); ?></textarea>
                    
                    <button onclick="navigator.clipboard.writeText(this.previousElementSibling.value).then(() => this.textContent = '✅ Copied!')" class="button" style="margin-top: 10px;">
                        📋 Copy to Clipboard
                    </button>
                </div>

                <?php $this->render_visual_debug( $debug_data ); ?>
            <?php else : ?>
                <div style="background: #fff; padding: 40px; text-align: center; border: 2px dashed #ccc; margin: 20px 0;">
                    <p style="font-size: 16px; color: #666;">👆 Select an action above and click "Run Test" to see debug output</p>
                </div>
            <?php endif; ?>

            <div style="background: #fffbcc; padding: 15px; border-left: 4px solid #f0b849; margin: 20px 0;">
                <h3 style="margin-top: 0;">💡 Direct URL Access</h3>
                <p>Bookmark this URL for quick access:</p>
                <code style="background: #fff; padding: 5px 10px; display: inline-block;">
                    <?php echo esc_url( admin_url( 'admin.php?page=cirrusly-debug' ) ); ?>
                </code>
            </div>
        </div>
        <?php
    }

    private function run_api_test( $action ) {
        $debug = array(
            'timestamp' => current_time( 'mysql' ),
            'action' => $action,
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_version' => CIRRUSLY_COMMERCE_VERSION,
            'request' => array(),
            'response' => array(),
            'errors' => array(),
        );

        // Check if Pro is active
        $debug['is_pro'] = Cirrusly_Commerce_Core::cirrusly_is_pro();
        if ( ! $debug['is_pro'] ) {
            $debug['errors'][] = 'Pro features not active';
            return $debug;
        }

        // Check if API client class exists
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            $debug['errors'][] = 'Google_API_Client class not found';
            return $debug;
        }

        // Get configuration
        $scan_config = get_option( 'cirrusly_scan_config', array() );
        $debug['config'] = array(
            'has_service_account' => ! empty( get_option( 'cirrusly_service_account_json' ) ),
            'has_api_key' => ! empty( $scan_config['api_key'] ),
            'merchant_id' => isset( $scan_config['merchant_id'] ) ? $scan_config['merchant_id'] : 'NOT SET',
            'api_endpoint' => Cirrusly_Commerce_Google_API_Client::API_ENDPOINT,
        );

        // Build test payload based on action
        $payload = array();
        if ( $action === 'nlp_analyze' ) {
            $payload = array( 'text' => 'Test product title for NLP analysis' );
        } elseif ( $action === 'validate_product_studio_setup' ) {
            // Comprehensive setup validation for Product Studio
            return $this->run_product_studio_setup_check();
        }

        // Capture request details
        $debug['request']['action'] = $action;
        $debug['request']['payload'] = $payload;
        $debug['request']['payload_json'] = json_encode( $payload );
        $debug['request']['payload_encoded'] = wp_json_encode( $payload );

        // Test JSON encoding
        $test_body = array(
            'action' => $action,
            'payload' => $payload,
            'test_field' => 'Test value with "quotes" and \'apostrophes\''
        );
        $encoded = wp_json_encode( $test_body );
        $debug['request']['test_encoding'] = array(
            'input_array' => $test_body,
            'encoded_result' => $encoded,
            'encoding_success' => ( false !== $encoded ),
            'can_decode_back' => ( json_decode( $encoded, true ) !== null ),
        );

        // Make actual API call
        $start_time = microtime( true );
        $result = Cirrusly_Commerce_Google_API_Client::request( $action, $payload, array( 'timeout' => 15 ) );
        $end_time = microtime( true );

        $debug['response']['duration_seconds'] = round( $end_time - $start_time, 3 );

        if ( is_wp_error( $result ) ) {
            $debug['response']['is_error'] = true;
            $debug['response']['error_code'] = $result->get_error_code();
            $debug['response']['error_message'] = $result->get_error_message();
            $debug['errors'][] = 'API Error: ' . $result->get_error_code() . ' - ' . $result->get_error_message();
        } else {
            $debug['response']['is_error'] = false;
            $debug['response']['data'] = $result;
            $debug['response']['data_type'] = gettype( $result );
            $debug['response']['is_array'] = is_array( $result );
            
            if ( is_array( $result ) ) {
                $debug['response']['keys'] = array_keys( $result );
                $debug['response']['has_success_key'] = isset( $result['success'] );
                $debug['response']['has_error_key'] = isset( $result['error'] );
            }
        }

        return $debug;
    }

    /**
     * Comprehensive Product Studio setup validation
     * Tests all required APIs and permissions
     */
    private function run_product_studio_setup_check() {
        $debug = array(
            'timestamp' => current_time( 'mysql' ),
            'action' => 'validate_product_studio_setup',
            'php_version' => phpversion(),
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_version' => CIRRUSLY_COMMERCE_VERSION,
            'is_pro' => Cirrusly_Commerce_Core::cirrusly_is_pro_plus(),
            'checks' => array(),
            'errors' => array(),
            'warnings' => array(),
        );

        if ( ! $debug['is_pro'] ) {
            $debug['errors'][] = 'Pro Plus version required for Product Studio features';
            return $debug;
        }

        // Test 1: Service Account JSON
        $service_account = get_option( 'cirrusly_service_account_json' );
        $debug['checks']['service_account'] = array(
            'status' => ! empty( $service_account ) ? 'pass' : 'fail',
            'message' => ! empty( $service_account ) ? '✓ Service account JSON uploaded' : '✗ No service account JSON found'
        );

        if ( empty( $service_account ) ) {
            $debug['errors'][] = 'Upload service account JSON in Settings';
            return $debug;
        }

        // Test 2: Project ID extraction
        $scan_config = get_option( 'cirrusly_scan_config', array() );
        $json_raw = Cirrusly_Commerce_Security::decrypt_data( $service_account );
        if ( ! $json_raw ) {
            $test = json_decode( $service_account, true );
            if ( isset( $test['private_key'] ) ) {
                $json_raw = $service_account;
            }
        }
        
        $project_id = '';
        if ( $json_raw ) {
            $creds = json_decode( $json_raw, true );
            $project_id = isset( $creds['project_id'] ) ? $creds['project_id'] : '';
        }

        $debug['checks']['project_id'] = array(
            'status' => ! empty( $project_id ) ? 'pass' : 'fail',
            'message' => ! empty( $project_id ) ? "✓ Project ID: {$project_id}" : '✗ Could not extract project ID',
            'value' => $project_id
        );

        if ( empty( $project_id ) ) {
            $debug['errors'][] = 'Invalid service account JSON format';
            return $debug;
        }

        // Test 3: Basic API validation
        if ( class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            $result = Cirrusly_Commerce_Google_API_Client::request( 'validate_product_studio_api', array(), array( 'timeout' => 10 ) );
            
            if ( is_wp_error( $result ) ) {
                $debug['checks']['api_validation'] = array(
                    'status' => 'fail',
                    'message' => '✗ API validation failed: ' . $result->get_error_message()
                );
                $debug['errors'][] = $result->get_error_message();
            } else {
                $debug['checks']['api_validation'] = array(
                    'status' => 'pass',
                    'message' => '✓ Product Studio API is accessible',
                    'features' => isset( $result['available_features'] ) ? $result['available_features'] : array()
                );
            }
        }

        // Test 4: Check required APIs (provide helpful links)
        $required_apis = array(
            'Content API for Shopping' => array(
                'status' => 'unknown',
                'enable_url' => "https://console.cloud.google.com/apis/enableflow?apiid=content.googleapis.com&project={$project_id}"
            ),
            'Cloud Natural Language API' => array(
                'status' => 'unknown',
                'enable_url' => "https://console.cloud.google.com/apis/enableflow?apiid=language.googleapis.com&project={$project_id}"
            ),
            'Cloud Vision API' => array(
                'status' => 'required_for_images',
                'enable_url' => "https://console.cloud.google.com/apis/enableflow?apiid=vision.googleapis.com&project={$project_id}"
            ),
            'Vertex AI API' => array(
                'status' => 'required_for_images',
                'enable_url' => "https://console.cloud.google.com/apis/enableflow?apiid=aiplatform.googleapis.com&project={$project_id}"
            ),
        );

        $debug['checks']['required_apis'] = $required_apis;
        $debug['warnings'][] = 'Vision API and Vertex AI are required for image generation features';

        // Test 5: IAM Permissions check
        $service_account_email = '';
        if ( $json_raw ) {
            $creds = json_decode( $json_raw, true );
            $service_account_email = isset( $creds['client_email'] ) ? $creds['client_email'] : '';
        }

        $required_roles = array(
            'Content API User' => 'roles/content.admin',
            'Cloud Natural Language User' => 'roles/cloudlanguage.user',
            'Cloud Vision User' => 'roles/cloudvision.user (for image features)',
            'Vertex AI User' => 'roles/aiplatform.user (for image generation)',
        );

        $debug['checks']['iam_permissions'] = array(
            'service_account_email' => $service_account_email,
            'required_roles' => $required_roles,
            'iam_console_url' => "https://console.cloud.google.com/iam-admin/iam?project={$project_id}",
            'message' => 'Verify service account has these roles in IAM Console'
        );

        // Overall status
        $has_errors = ! empty( $debug['errors'] );
        $debug['overall_status'] = $has_errors ? 'incomplete' : 'ready';
        $debug['overall_message'] = $has_errors 
            ? '⚠️ Setup incomplete - see errors above' 
            : '✅ Product Studio is configured and ready to use!';

        return $debug;
    }

    private function format_debug_output( $debug ) {
        $output = "=================================================\n";
        $output .= "CIRRUSLY COMMERCE API DEBUG OUTPUT\n";
        $output .= "=================================================\n\n";
        
        $output .= "TIMESTAMP: " . $debug['timestamp'] . "\n";
        $output .= "ACTION: " . $debug['action'] . "\n";
        $output .= "PLUGIN VERSION: " . $debug['plugin_version'] . "\n";
        $output .= "PHP VERSION: " . $debug['php_version'] . "\n";
        $output .= "WP VERSION: " . $debug['wp_version'] . "\n";
        $output .= "IS PRO: " . ( $debug['is_pro'] ? 'YES' : 'NO' ) . "\n\n";

        $output .= "-------------------------------------------------\n";
        $output .= "CONFIGURATION\n";
        $output .= "-------------------------------------------------\n";
        foreach ( $debug['config'] as $key => $value ) {
            $output .= strtoupper( str_replace( '_', ' ', $key ) ) . ": " . ( is_bool( $value ) ? ( $value ? 'YES' : 'NO' ) : $value ) . "\n";
        }

        $output .= "\n-------------------------------------------------\n";
        $output .= "REQUEST DETAILS\n";
        $output .= "-------------------------------------------------\n";
        $output .= "ACTION: " . $debug['request']['action'] . "\n";
        $output .= "PAYLOAD: " . print_r( $debug['request']['payload'], true ) . "\n";
        
        if ( isset( $debug['request']['test_encoding'] ) ) {
            $output .= "\nENCODING TEST:\n";
            $output .= "  Encoding Success: " . ( $debug['request']['test_encoding']['encoding_success'] ? 'YES' : 'NO' ) . "\n";
            $output .= "  Can Decode Back: " . ( $debug['request']['test_encoding']['can_decode_back'] ? 'YES' : 'NO' ) . "\n";
            $output .= "  Encoded Result: " . substr( $debug['request']['test_encoding']['encoded_result'], 0, 200 ) . "...\n";
        }

        $output .= "\n-------------------------------------------------\n";
        $output .= "RESPONSE DETAILS\n";
        $output .= "-------------------------------------------------\n";
        $output .= "DURATION: " . $debug['response']['duration_seconds'] . " seconds\n";
        $output .= "IS ERROR: " . ( $debug['response']['is_error'] ? 'YES' : 'NO' ) . "\n";

        if ( $debug['response']['is_error'] ) {
            $output .= "ERROR CODE: " . $debug['response']['error_code'] . "\n";
            $output .= "ERROR MESSAGE: " . $debug['response']['error_message'] . "\n";
        } else {
            $output .= "DATA TYPE: " . $debug['response']['data_type'] . "\n";
            $output .= "IS ARRAY: " . ( $debug['response']['is_array'] ? 'YES' : 'NO' ) . "\n";
            if ( isset( $debug['response']['keys'] ) ) {
                $output .= "RESPONSE KEYS: " . implode( ', ', $debug['response']['keys'] ) . "\n";
            }
            $output .= "\nFULL RESPONSE DATA:\n";
            $output .= print_r( $debug['response']['data'], true ) . "\n";
        }

        if ( ! empty( $debug['errors'] ) ) {
            $output .= "\n-------------------------------------------------\n";
            $output .= "ERRORS ENCOUNTERED\n";
            $output .= "-------------------------------------------------\n";
            foreach ( $debug['errors'] as $error ) {
                $output .= "❌ " . $error . "\n";
            }
        }

        $output .= "\n=================================================\n";
        $output .= "END OF DEBUG OUTPUT\n";
        $output .= "=================================================\n";

        return $output;
    }

    private function render_visual_debug( $debug ) {
        ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ccc; margin: 20px 0;">
            <h2>📊 Visual Debug Output</h2>

            <table class="widefat striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 30%;">Property</th>
                        <th>Value</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Action</strong></td>
                        <td><code><?php echo esc_html( $debug['action'] ); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>Pro Active</strong></td>
                        <td><?php echo $debug['is_pro'] ? '<span style="color: green;">✅ YES</span>' : '<span style="color: red;">❌ NO</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Duration</strong></td>
                        <td><?php echo esc_html( $debug['response']['duration_seconds'] ); ?> seconds</td>
                    </tr>
                    <tr>
                        <td><strong>Has Service Account</strong></td>
                        <td><?php echo $debug['config']['has_service_account'] ? '<span style="color: green;">✅ YES</span>' : '<span style="color: red;">❌ NO</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Has API Key</strong></td>
                        <td><?php echo $debug['config']['has_api_key'] ? '<span style="color: green;">✅ YES</span>' : '<span style="color: red;">❌ NO</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Merchant ID</strong></td>
                        <td><code><?php echo esc_html( $debug['config']['merchant_id'] ); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong>API Endpoint</strong></td>
                        <td><code><?php echo esc_html( $debug['config']['api_endpoint'] ); ?></code></td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top: 30px;">📤 Request Encoding Test</h3>
            <?php if ( isset( $debug['request']['test_encoding'] ) ) : ?>
                <table class="widefat striped">
                    <tr>
                        <td style="width: 30%;"><strong>wp_json_encode() Success</strong></td>
                        <td><?php echo $debug['request']['test_encoding']['encoding_success'] ? '<span style="color: green;">✅ YES</span>' : '<span style="color: red;">❌ NO (THIS IS THE PROBLEM!)</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Can Decode Back</strong></td>
                        <td><?php echo $debug['request']['test_encoding']['can_decode_back'] ? '<span style="color: green;">✅ YES</span>' : '<span style="color: red;">❌ NO</span>'; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Encoded Sample</strong></td>
                        <td><code style="font-size: 11px;"><?php echo esc_html( substr( $debug['request']['test_encoding']['encoded_result'], 0, 200 ) ); ?>...</code></td>
                    </tr>
                </table>
            <?php endif; ?>

            <h3 style="margin-top: 30px;">📥 Response Status</h3>
            <div style="padding: 20px; background: <?php echo $debug['response']['is_error'] ? '#fee' : '#efe'; ?>; border-left: 4px solid <?php echo $debug['response']['is_error'] ? '#c00' : '#0a0'; ?>;">
                <?php if ( $debug['response']['is_error'] ) : ?>
                    <h4 style="color: #c00; margin: 0 0 10px 0;">❌ Error Occurred</h4>
                    <p><strong>Error Code:</strong> <code><?php echo esc_html( $debug['response']['error_code'] ); ?></code></p>
                    <p><strong>Error Message:</strong> <?php echo esc_html( $debug['response']['error_message'] ); ?></p>
                <?php else : ?>
                    <h4 style="color: #0a0; margin: 0 0 10px 0;">✅ Response Received Successfully</h4>
                    <p><strong>Data Type:</strong> <?php echo esc_html( $debug['response']['data_type'] ); ?></p>
                    <?php if ( isset( $debug['response']['keys'] ) ) : ?>
                        <p><strong>Response Keys:</strong> <code><?php echo esc_html( implode( ', ', $debug['response']['keys'] ) ); ?></code></p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if ( ! empty( $debug['errors'] ) ) : ?>
                <h3 style="margin-top: 30px; color: #c00;">⚠️ Errors</h3>
                <ul style="background: #fee; padding: 15px 15px 15px 35px; border-left: 4px solid #c00;">
                    <?php foreach ( $debug['errors'] as $error ) : ?>
                        <li><?php echo esc_html( $error ); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }
}
