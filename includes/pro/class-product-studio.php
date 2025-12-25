<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product Studio AI Content Generation Handler
 *
 * Manages Google Cloud Product Studio API integration for AI-powered product content generation.
 * Includes validation endpoints for setup wizard and content generation workflows.
 *
 * @package Cirrusly_Commerce
 * @subpackage Pro
 */
class Cirrusly_Commerce_Product_Studio {

    /**
     * Initialize Product Studio features: AJAX endpoints.
     */
    public function __construct() {
        // AJAX Endpoints for Setup Validation
        add_action( 'wp_ajax_cirrusly_validate_ps_api', array( $this, 'handle_validate_api_access' ) );
        add_action( 'wp_ajax_cirrusly_validate_ps_iam', array( $this, 'handle_validate_iam_permissions' ) );
        add_action( 'wp_ajax_cirrusly_validate_ps_linkage', array( $this, 'handle_validate_merchant_linkage' ) );
        
        // AJAX Endpoints for AI Fix (Preview + Apply)
        add_action( 'wp_ajax_cirrusly_ai_preview_product', array( $this, 'handle_ai_preview_product' ) );
        add_action( 'wp_ajax_cirrusly_ai_apply_fix', array( $this, 'handle_ai_apply_fix' ) );
        add_action( 'wp_ajax_cirrusly_ai_fix_product', array( $this, 'handle_ai_fix_product' ) );
        
        // AJAX Endpoint for Image Path Resolution
        add_action( 'wp_ajax_cirrusly_resolve_image_path', array( $this, 'ajax_resolve_image_path' ) );
    }

    /**
     * Resolve image path to WordPress attachment ID.
     *
     * Matches Google's flagged image path to a WordPress attachment ID
     * using CDN-safe path matching. Works with Cloudflare, imgix, Cloudinary, etc.
     *
     * @param string $image_path The image path from Google (e.g., "/wp-content/uploads/2024/12/product.jpg")
     * @return int Attachment ID or 0 if not found
     */
    public static function get_attachment_id_by_path( $image_path ) {
        global $wpdb;
        
        if ( empty( $image_path ) ) {
            return 0;
        }
        
        // Remove leading slash and query parameters
        $clean_path = ltrim( parse_url( $image_path, PHP_URL_PATH ), '/' );
        
        // Remove wp-content/uploads/ prefix if present
        $clean_path = preg_replace( '#^wp-content/uploads/#', '', $clean_path );
        
        // Query for matching attachment
        $attachment_id = $wpdb->get_var( $wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} 
             WHERE meta_key = '_wp_attached_file' 
             AND meta_value LIKE %s 
             LIMIT 1",
            '%' . $wpdb->esc_like( $clean_path )
        ) );
        
        return $attachment_id ? intval( $attachment_id ) : 0;
    }

    /**
     * AJAX Handler: Resolve image path to attachment data.
     *
     * Takes a Google-flagged image path and resolves it to WordPress attachment ID and URL.
     * Used by GMC Compliance Hub to identify which specific image needs enhancement.
     */
    public function ajax_resolve_image_path() {
        check_ajax_referer( 'cirrusly_product_studio', 'nonce' );
        
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied', 'cirrusly-commerce' ) ) );
        }
        
        $image_path = isset( $_POST['image_path'] ) ? sanitize_text_field( wp_unslash( $_POST['image_path'] ) ) : '';
        $attachment_id = self::get_attachment_id_by_path( $image_path );
        
        if ( $attachment_id ) {
            $url = wp_get_attachment_url( $attachment_id );
            $metadata = wp_get_attachment_metadata( $attachment_id );
            
            wp_send_json_success( array(
                'attachment_id' => $attachment_id,
                'url' => $url,
                'title' => get_the_title( $attachment_id ),
                'width' => isset( $metadata['width'] ) ? $metadata['width'] : 0,
                'height' => isset( $metadata['height'] ) ? $metadata['height'] : 0
            ) );
        } else {
            wp_send_json_error( array( 
                'message' => __( 'Image not found in Media Library. It may have been deleted or moved.', 'cirrusly-commerce' ),
                'searched_path' => $image_path
            ) );
        }
    }

    /**
     * Sanitize text for JSON encoding to prevent encoding failures.
     *
     * Removes accents, strips HTML tags, ensures valid UTF-8, and sanitizes special characters
     * that could break JSON encoding when product names contain quotes, emoji, or HTML entities.
     *
     * @param string $text The text to sanitize.
     * @return string Sanitized text safe for JSON encoding.
     */
    private function sanitize_for_json( $text ) {
        if ( empty( $text ) || ! is_string( $text ) ) {
            return '';
        }
        
        // Remove HTML tags first
        $text = wp_strip_all_tags( $text );
        
        // Remove accents (café → cafe)
        $text = remove_accents( $text );
        
        // Ensure valid UTF-8 encoding (strips invalid sequences)
        if ( function_exists( 'mb_convert_encoding' ) ) {
            $text = mb_convert_encoding( $text, 'UTF-8', 'UTF-8' );
        }
        
        // Sanitize special characters
        $text = sanitize_text_field( $text );
        
        return $text;
    }

    /**
     * Sanitize array of category names for JSON encoding.
     *
     * @param array $categories Array of category names.
     * @return array Sanitized category names.
     */
    private function sanitize_categories_for_json( $categories ) {
        if ( ! is_array( $categories ) ) {
            return array();
        }
        
        return array_map( array( $this, 'sanitize_for_json' ), $categories );
    }

    /**
     * AJAX Handler: Validate Product Studio API access.
     *
     * Tests if the Product Studio API is enabled and accessible by making a lightweight test call.
     * Requires nonce verification and manage_options capability.
     */
    public function handle_validate_api_access() {
        // Verify nonce and permissions
        $nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'cirrusly_validate_ps_api' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'cirrusly-commerce' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'cirrusly-commerce' ) ) );
        }

        if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            wp_send_json_error( array( 'message' => __( 'Pro Plus version required.', 'cirrusly-commerce' ) ) );
        }

        $result = $this->validate_api_access();
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
                'help_link' => 'https://console.cloud.google.com/apis/library/productstudio.googleapis.com'
            ) );
        }
        
        // Cache successful validation for 1 hour
        set_transient( 'cirrusly_ps_validation_api_' . get_current_user_id(), 'success', HOUR_IN_SECONDS );
        
        wp_send_json_success( array( 'message' => __( '✓ Product Studio API is enabled and accessible', 'cirrusly-commerce' ) ) );
    }

    /**
     * AJAX Handler: Validate IAM permissions.
     *
     * Checks if the service account has the required IAM roles:
     * - roles/merchantapi.productEditor
     * - roles/merchantapi.productStudioUser
     */
    public function handle_validate_iam_permissions() {
        // Verify nonce and permissions
        $nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'cirrusly_validate_ps_iam' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'cirrusly-commerce' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'cirrusly-commerce' ) ) );
        }

        if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            wp_send_json_error( array( 'message' => __( 'Pro Plus version required.', 'cirrusly-commerce' ) ) );
        }

        $result = $this->validate_iam_permissions();
        
        if ( is_wp_error( $result ) ) {
            $project_id = $this->extract_project_id_from_service_account();
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
                'help_link' => 'https://console.cloud.google.com/iam-admin/iam?project=' . urlencode( $project_id )
            ) );
        }
        
        // Cache successful validation for 1 hour
        set_transient( 'cirrusly_ps_validation_iam_' . get_current_user_id(), 'success', HOUR_IN_SECONDS );
        
        wp_send_json_success( array( 'message' => __( '✓ Service account has required IAM permissions', 'cirrusly-commerce' ) ) );
    }

    /**
     * AJAX Handler: Validate Merchant Center linkage.
     *
     * Verifies that the Merchant Center account is linked to the same Google Cloud project
     * as the service account.
     */
    public function handle_validate_merchant_linkage() {
        // Verify nonce and permissions
        $nonce = isset( $_POST['_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_nonce'] ) ) : '';
        
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'cirrusly_validate_ps_linkage' ) ) {
            wp_send_json_error( array( 'message' => __( 'Nonce verification failed.', 'cirrusly-commerce' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'cirrusly-commerce' ) ) );
        }

        if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            wp_send_json_error( array( 'message' => __( 'Pro Plus version required.', 'cirrusly-commerce' ) ) );
        }

        $result = $this->validate_merchant_linkage();
        
        if ( is_wp_error( $result ) ) {
            $scan_config = get_option( 'cirrusly_scan_config', array() );
            $merchant_id = isset( $scan_config['merchant_id'] ) ? $scan_config['merchant_id'] : '';
            
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
                'help_link' => 'https://merchants.google.com/mc/accounts/' . urlencode( $merchant_id ) . '/settings'
            ) );
        }
        
        // Cache successful validation for 1 hour
        set_transient( 'cirrusly_ps_validation_linkage_' . get_current_user_id(), 'success', HOUR_IN_SECONDS );
        
        wp_send_json_success( array( 'message' => __( '✓ Merchant Center is linked to Cloud Project', 'cirrusly-commerce' ) ) );
    }

    /**
     * AJAX Handler: Generate AI preview without saving.
     *
     * Returns current content and AI-generated content for user review.
     * Supports enhancement style parameter for different AI writing modes.
     */
    public function handle_ai_preview_product() {
        // Security checks
        if ( ! check_ajax_referer( 'cirrusly_ai_preview', 'security', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'cirrusly-commerce' ) ) );
        }
        
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cirrusly-commerce' ) ) );
        }
        
        if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            wp_send_json_error( array( 
                'message' => __( 'Product Studio requires Pro Plus. Upgrade to unlock AI-powered fixes.', 'cirrusly-commerce' ),
                'upgrade_url' => function_exists( 'cirrusly_fs' ) ? cirrusly_fs()->get_upgrade_url() : ''
            ) );
        }
        
        // Get and validate input
        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $fix_action = isset( $_POST['fix_action'] ) ? sanitize_key( $_POST['fix_action'] ) : '';
        $enhancement = isset( $_POST['enhancement'] ) ? sanitize_key( $_POST['enhancement'] ) : 'default';
        
        if ( ! $product_id || ! $fix_action ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product or action.', 'cirrusly-commerce' ) ) );
        }
        
        // Verify product exists and user can edit it
        $product = wc_get_product( $product_id );
        if ( ! $product || ! current_user_can( 'edit_post', $product_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Product not found or you cannot edit it.', 'cirrusly-commerce' ) ) );
        }
        
        // Get current content for comparison
        $current_content = $this->get_current_content( $product, $fix_action );
        
        // Generate AI content via service worker
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            wp_send_json_error( array( 'message' => __( 'Google API Client not available.', 'cirrusly-commerce' ) ) );
        }
        
        // Get categories for context
        $categories = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'names' ) );
        
        // Prepare payload with product context and enhancement style
        $payload = array(
            'product_id' => $product->get_id(),
            'product_name' => $this->sanitize_for_json( $product->get_name() ),
            'action_type' => $fix_action,
            'enhancement' => $enhancement,
            'product_data' => array(
                'title' => $this->sanitize_for_json( $product->get_name() ),
                'description' => $this->sanitize_for_json( $product->get_description() ),
                'short_description' => $this->sanitize_for_json( $product->get_short_description() ),
                'categories' => $this->sanitize_categories_for_json( $categories ),
                'price' => $product->get_price(),
                // FIX: Convert attributes to JSON-safe array instead of objects
                'attributes' => array(),
            )
        );
        
        // Add image URL for image generation actions
        if ( $fix_action === 'generate_image' ) {
            $image_url = '';
            
            // Priority 1: Check if targeting specific Google-flagged image
            if ( isset( $_POST['target_attachment_id'] ) && intval( $_POST['target_attachment_id'] ) > 0 ) {
                $target_id = intval( $_POST['target_attachment_id'] );
                $target_url = isset( $_POST['target_image_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_image_url'] ) ) : '';
                
                $payload['target_attachment_id'] = $target_id;
                if ( $target_url ) {
                    $image_url = $target_url;
                }
            }
            
            // Priority 2: Use featured image if no specific target
            if ( empty( $image_url ) ) {
                $image_id = $product->get_image_id();
                if ( $image_id ) {
                    $featured_url = wp_get_attachment_image_url( $image_id, 'full' );
                    if ( $featured_url ) {
                        $image_url = $featured_url;
                    }
                }
            }
            
            // Validate we have an image before proceeding
            if ( empty( $image_url ) ) {
                wp_send_json_error( array( 
                    'message' => __( 'No product image available. Please add a featured image first.', 'cirrusly-commerce' ) 
                ) );
            }
            
            // CRITICAL FIX: Service worker expects image_url INSIDE product_data, not at top level
            $payload['product_data']['image_url'] = $image_url;
            
            // Debug logging (only in WP_DEBUG mode)
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '=== PRODUCT STUDIO DEBUG ===' );
                error_log( 'Product ID: ' . $product_id );
                error_log( 'Action: generate_image' );
                error_log( 'Image URL set: ' . ( ! empty( $image_url ) ? 'YES' : 'NO' ) );
            }
        }
        
        // Call service worker (longer timeout for image generation)
        $timeout = ( $fix_action === 'generate_image' ) ? 60 : 45;
        
        // Debug logging (only in WP_DEBUG mode)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Product Studio: Calling service worker' );
            error_log( 'Action: ' . $fix_action );
            error_log( 'Product ID: ' . $product_id );
            error_log( 'Timeout: ' . $timeout . 's' );
        }
        
        $response = Cirrusly_Commerce_Google_API_Client::request( 'product_studio_generate', $payload, array( 'timeout' => $timeout ) );
        
        // Debug logging (only in WP_DEBUG mode)
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            if ( is_wp_error( $response ) ) {
                error_log( 'Product Studio: Service worker error - ' . $response->get_error_message() );
            } else {
                error_log( 'Product Studio: Service worker success' );
            }
        }
        
        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => $response->get_error_message() ) );
        }
        
        // Handle image generation response differently
        if ( $fix_action === 'generate_image' ) {
            if ( ! isset( $response['image_data'] ) ) {
                wp_send_json_error( array( 'message' => __( 'API did not return image data.', 'cirrusly-commerce' ) ) );
            }
            
            // Return preview data with base64 image
            wp_send_json_success( array(
                'current' => $current_content,
                'generated' => $response['image_data'],
                'mime_type' => isset( $response['mime_type'] ) ? $response['mime_type'] : 'image/png',
                'prompt_used' => isset( $response['prompt_used'] ) ? $response['prompt_used'] : '',
                'product_name' => $this->sanitize_for_json( $product->get_name() ),
                'product_id' => $product_id,
                'action' => $fix_action,
                'is_image' => true
            ) );
        }
        
        // Handle text generation response
        if ( ! isset( $response['generated_text'] ) ) {
            wp_send_json_error( array( 'message' => __( 'API did not return generated content.', 'cirrusly-commerce' ) ) );
        }
        
        // Return preview data
        wp_send_json_success( array(
            'current' => $current_content,
            'generated' => $response['generated_text'],
            'product_name' => $this->sanitize_for_json( $product->get_name() ),
            'product_id' => $product_id,
            'action' => $fix_action
        ) );
    }

    /**
     * AJAX Handler: Apply user-approved AI content.
     *
     * Saves the content that user reviewed and optionally edited in the preview modal.
     * Logs the enhancement style used for analytics.
     */
    public function handle_ai_apply_fix() {
        // Security checks
        if ( ! check_ajax_referer( 'cirrusly_ai_apply', 'security', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'cirrusly-commerce' ) ) );
        }
        
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cirrusly-commerce' ) ) );
        }
        
        if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            wp_send_json_error( array( 'message' => __( 'Pro Plus version required.', 'cirrusly-commerce' ) ) );
        }
        
        // Get and validate input
        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $fix_action = isset( $_POST['fix_action'] ) ? sanitize_key( $_POST['fix_action'] ) : '';
        $generated_content = isset( $_POST['generated_content'] ) ? wp_kses_post( wp_unslash( $_POST['generated_content'] ) ) : '';
        $enhancement = isset( $_POST['enhancement'] ) ? sanitize_key( $_POST['enhancement'] ) : 'default';
        
        if ( ! $product_id || ! $fix_action || empty( $generated_content ) ) {
            wp_send_json_error( array( 'message' => __( 'Missing required data.', 'cirrusly-commerce' ) ) );
        }
        
        // Verify product exists and user can edit it
        $product = wc_get_product( $product_id );
        if ( ! $product || ! current_user_can( 'edit_post', $product_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Product not found or you cannot edit it.', 'cirrusly-commerce' ) ) );
        }
        
        // Apply changes based on action type
        $message = '';
        switch ( $fix_action ) {
            case 'generate_description':
                $product->set_description( $generated_content );
                $product->save();
                $message = __( 'Description updated successfully!', 'cirrusly-commerce' );
                break;
                
            case 'optimize_title':
                $product->set_name( $generated_content );
                $product->save();
                $message = __( 'Title optimized successfully!', 'cirrusly-commerce' );
                break;
                
            case 'enhance_images':
                // Parse pipe-separated alt texts
                $alt_texts = array_map( 'trim', explode( '|', $generated_content ) );
                
                // Get all product images (featured + gallery)
                $image_ids = array();
                if ( $product->get_image_id() ) {
                    $image_ids[] = $product->get_image_id();
                }
                $gallery_ids = $product->get_gallery_image_ids();
                if ( ! empty( $gallery_ids ) ) {
                    $image_ids = array_merge( $image_ids, $gallery_ids );
                }
                
                // Update alt text for each image
                $updated_count = 0;
                foreach ( $image_ids as $index => $image_id ) {
                    if ( isset( $alt_texts[ $index ] ) && ! empty( $alt_texts[ $index ] ) ) {
                        update_post_meta( $image_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_texts[ $index ] ) );
                        $updated_count++;
                    }
                }
                
                $message = sprintf(
                    /* translators: %d: number of images updated */
                    _n( 'Updated alt text for %d image.', 'Updated alt text for %d images.', $updated_count, 'cirrusly-commerce' ),
                    $updated_count
                );
                break;
                
            case 'generate_image':
                // $generated_content contains base64 image data
                $image_data = base64_decode( $generated_content );
                
                if ( ! $image_data ) {
                    wp_send_json_error( array( 'message' => __( 'Invalid image data.', 'cirrusly-commerce' ) ) );
                }
                
                // Upload image to WordPress media library
                require_once( ABSPATH . 'wp-admin/includes/file.php' );
                require_once( ABSPATH . 'wp-admin/includes/media.php' );
                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                
                $upload_dir = wp_upload_dir();
                $filename = 'ai-generated-' . $product_id . '-' . time() . '.png';
                $file_path = $upload_dir['path'] . '/' . $filename;
                
                // Write image data to file
                $file_written = file_put_contents( $file_path, $image_data );
                
                if ( ! $file_written ) {
                    wp_send_json_error( array( 'message' => __( 'Failed to save image file.', 'cirrusly-commerce' ) ) );
                }
                
                // Create attachment
                $attachment = array(
                    'post_mime_type' => 'image/png',
                    'post_title' => sanitize_file_name( $product->get_name() ),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                
                $attachment_id = wp_insert_attachment( $attachment, $file_path, $product_id );
                
                if ( is_wp_error( $attachment_id ) ) {
                    wp_send_json_error( array( 'message' => __( 'Failed to create attachment.', 'cirrusly-commerce' ) ) );
                }
                
                // Generate attachment metadata
                $attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
                wp_update_attachment_metadata( $attachment_id, $attach_data );
                
                // Set as featured image
                set_post_thumbnail( $product_id, $attachment_id );
                
                // Set alt text
                update_post_meta( $attachment_id, '_wp_attachment_image_alt', $product->get_name() );
                
                $message = __( 'AI-generated image set as product featured image!', 'cirrusly-commerce' );
                break;
                
            default:
                wp_send_json_error( array( 'message' => __( 'Unknown action.', 'cirrusly-commerce' ) ) );
        }
        
        // Log enhancement usage for analytics (optional)
        if ( 'default' !== $enhancement ) {
            update_post_meta( $product_id, '_cirrusly_last_enhancement', array(
                'style' => $enhancement,
                'action' => $fix_action,
                'timestamp' => time()
            ) );
        }
        
        wp_send_json_success( array( 'message' => $message ) );
    }

    /**
     * Get current content for a specific action type.
     *
     * @param WC_Product $product The product.
     * @param string $action Action type (generate_description, optimize_title, enhance_images).
     * @return string Current content.
     */
    private function get_current_content( $product, $action ) {
        switch ( $action ) {
            case 'generate_description':
                return $product->get_description() ?: __( '(No description)', 'cirrusly-commerce' );
                
            case 'optimize_title':
                return $product->get_name();
                
            case 'enhance_images':
                // Get all product images and their current alt texts
                $image_ids = array();
                if ( $product->get_image_id() ) {
                    $image_ids[] = $product->get_image_id();
                }
                $gallery_ids = $product->get_gallery_image_ids();
                if ( ! empty( $gallery_ids ) ) {
                    $image_ids = array_merge( $image_ids, $gallery_ids );
                }
                
                if ( empty( $image_ids ) ) {
                    return __( '(No images)', 'cirrusly-commerce' );
                }
                
                $alt_texts = array();
                foreach ( $image_ids as $image_id ) {
                    $alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
                    $alt_texts[] = $alt ?: __( '(no alt text)', 'cirrusly-commerce' );
                }
                
                return implode( ' | ', $alt_texts );
                
            case 'generate_image':
                // Return current image URL or placeholder
                $image_id = $product->get_image_id();
                if ( $image_id ) {
                    return wp_get_attachment_url( $image_id );
                }
                return __( '(No featured image)', 'cirrusly-commerce' );
                
            default:
                return '';
        }
    }

    /**
     * Validate Product Studio API Access.
     *
     * Makes a lightweight test call to the Product Studio API to verify it's enabled and accessible.
     *
     * @return true|WP_Error True if API is accessible, WP_Error otherwise.
     */
    private function validate_api_access() {
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            return new WP_Error( 'missing_client', __( 'Google API Client class not loaded.', 'cirrusly-commerce' ) );
        }

        // Call the service worker with validate_product_studio_api action
        $result = Cirrusly_Commerce_Google_API_Client::request( 'validate_product_studio_api', array(), array( 'timeout' => 10 ) );
        
        if ( is_wp_error( $result ) ) {
            // Parse error for helpful message
            $error_msg = $result->get_error_message();
            
            if ( strpos( $error_msg, '403' ) !== false || strpos( $error_msg, 'disabled' ) !== false ) {
                return new WP_Error(
                    'api_disabled',
                    __( 'Product Studio API is not enabled. Enable it in Google Cloud Console.', 'cirrusly-commerce' )
                );
            }
            
            return $result;
        }
        
        return true;
    }

    /**
     * Validate IAM Permissions.
     *
     * Checks if the service account has the required IAM roles by attempting
     * a privileged operation that requires both productEditor and productStudioUser roles.
     *
     * @return true|WP_Error True if permissions are valid, WP_Error otherwise.
     */
    private function validate_iam_permissions() {
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            return new WP_Error( 'missing_client', __( 'Google API Client class not loaded.', 'cirrusly-commerce' ) );
        }

        // Call the service worker with validate_iam_permissions action
        $result = Cirrusly_Commerce_Google_API_Client::request( 'validate_iam_permissions', array(), array( 'timeout' => 10 ) );
        
        if ( is_wp_error( $result ) ) {
            $error_msg = $result->get_error_message();
            
            if ( strpos( $error_msg, '403' ) !== false || strpos( $error_msg, 'permission' ) !== false ) {
                return new WP_Error(
                    'missing_permissions',
                    __( 'Service account is missing required IAM roles: productEditor and productStudioUser', 'cirrusly-commerce' )
                );
            }
            
            return $result;
        }
        
        return true;
    }

    /**
     * Validate Merchant Center Linkage.
     *
     * Verifies that the Merchant Center account is linked to the Google Cloud project
     * by checking the account's linked project configuration.
     *
     * @return true|WP_Error True if linkage is valid, WP_Error otherwise.
     */
    private function validate_merchant_linkage() {
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            return new WP_Error( 'missing_client', __( 'Google API Client class not loaded.', 'cirrusly-commerce' ) );
        }

        $project_id = $this->extract_project_id_from_service_account();
        
        if ( ! $project_id ) {
            return new WP_Error( 'no_project_id', __( 'Could not extract project ID from service account.', 'cirrusly-commerce' ) );
        }

        // Call the service worker with validate_merchant_linkage action
        $result = Cirrusly_Commerce_Google_API_Client::request( 'validate_merchant_linkage', array( 'project_id' => $project_id ), array( 'timeout' => 10 ) );
        
        if ( is_wp_error( $result ) ) {
            $error_msg = $result->get_error_message();
            
            if ( strpos( $error_msg, 'not linked' ) !== false || strpos( $error_msg, '403' ) !== false ) {
                return new WP_Error(
                    'not_linked',
                    sprintf(
                        /* translators: %s: Google Cloud Project ID */
                        __( 'Merchant Center is not linked to Cloud Project "%s". Link them in Merchant Center settings.', 'cirrusly-commerce' ),
                        $project_id
                    )
                );
            }
            
            return $result;
        }
        
        return true;
    }

    /**
     * Extract Google Cloud Project ID from the uploaded service account JSON.
     *
     * @return string|false Project ID or false if not found.
     */
    private function extract_project_id_from_service_account() {
        $encrypted_json = get_option( 'cirrusly_service_account_json' );
        
        if ( ! $encrypted_json ) {
            return false;
        }

        // Decrypt the service account JSON
        $json_raw = Cirrusly_Commerce_Security::decrypt_data( $encrypted_json );
        
        if ( ! $json_raw ) {
            // Try unencrypted (legacy)
            $test = json_decode( $encrypted_json, true );
            if ( isset( $test['project_id'] ) ) {
                return $test['project_id'];
            }
            return false;
        }

        $json_data = json_decode( $json_raw, true );
        
        return isset( $json_data['project_id'] ) ? $json_data['project_id'] : false;
    }

    /**
     * Get cached validation status for a specific check.
     *
     * @param string $check_type One of: 'api', 'iam', 'linkage'.
     * @return bool True if validation passed and is still cached.
     */
    public static function get_validation_status( $check_type ) {
        $user_id = get_current_user_id();
        $transient_key = 'cirrusly_ps_validation_' . $check_type . '_' . $user_id;
        $status = get_transient( $transient_key );
        
        return 'success' === $status;
    }
    
    /**
     * AJAX Handler: Process AI fix request for a product
     *
     * Accepts POST data:
     * - product_id: The product to fix
     * - fix_action: The type of fix (generate_description, optimize_title, enhance_images)
     * - _nonce: Security nonce
     *
     * Returns JSON response with success/error and updated content
     */
    public function handle_ai_fix_product() {
        // Security checks
        if ( ! check_ajax_referer( 'cirrusly_ai_fix', '_nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'cirrusly-commerce' ) ) );
        }
        
        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cirrusly-commerce' ) ) );
        }
        
        if ( ! Cirrusly_Commerce_Core::cirrusly_is_pro_plus() ) {
            wp_send_json_error( array( 
                'message' => __( 'Product Studio requires Pro Plus. Upgrade to unlock AI-powered fixes.', 'cirrusly-commerce' ),
                'upgrade_url' => function_exists( 'cirrusly_fs' ) ? cirrusly_fs()->get_upgrade_url() : ''
            ) );
        }
        
        // Get and validate input
        $product_id = isset( $_POST['product_id'] ) ? intval( $_POST['product_id'] ) : 0;
        $fix_action = isset( $_POST['fix_action'] ) ? sanitize_key( $_POST['fix_action'] ) : '';
        
        if ( ! $product_id || ! $fix_action ) {
            wp_send_json_error( array( 'message' => __( 'Invalid product or action.', 'cirrusly-commerce' ) ) );
        }
        
        // Verify product exists and user can edit it
        $product = wc_get_product( $product_id );
        if ( ! $product || ! current_user_can( 'edit_post', $product_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Product not found or you cannot edit it.', 'cirrusly-commerce' ) ) );
        }
        
        // Call the appropriate AI fix method
        $result = false;
        switch ( $fix_action ) {
            case 'generate_description':
                $result = $this->ai_generate_description( $product );
                break;
            case 'optimize_title':
                $result = $this->ai_optimize_title( $product );
                break;
            case 'enhance_images':
                $result = $this->ai_enhance_images( $product );
                break;
            default:
                wp_send_json_error( array( 'message' => __( 'Unknown action.', 'cirrusly-commerce' ) ) );
        }
        
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        
        wp_send_json_success( array( 
            'message' => $result['message'],
            'updated_fields' => isset( $result['fields'] ) ? $result['fields'] : array()
        ) );
    }
    
    /**
     * Generate product description using AI
     *
     * @param WC_Product $product The product to enhance
     * @return array|WP_Error Success array or error object
     */
    private function ai_generate_description( $product ) {
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            return new WP_Error( 'missing_client', __( 'Google API Client not available.', 'cirrusly-commerce' ) );
        }
        
        // Prepare payload with product context
        $categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
        $payload = array(
            'product_id' => $product->get_id(),
            'product_name' => $this->sanitize_for_json( $product->get_name() ),
            'short_description' => $this->sanitize_for_json( $product->get_short_description() ),
            'categories' => $this->sanitize_categories_for_json( $categories ),
            'attributes' => $product->get_attributes(),
            'request_type' => 'generate_description'
        );
        
        // Call service worker to generate description via Product Studio API
        $response = Cirrusly_Commerce_Google_API_Client::request( 'product_studio_generate', $payload, array( 'timeout' => 30 ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        if ( ! isset( $response['generated_text'] ) ) {
            return new WP_Error( 'invalid_response', __( 'API did not return generated content.', 'cirrusly-commerce' ) );
        }
        
        // Update product description
        $product->set_description( $response['generated_text'] );
        $product->save();
        
        return array(
            'message' => __( 'Product description generated successfully!', 'cirrusly-commerce' ),
            'fields' => array( 'description' => $response['generated_text'] )
        );
    }
    
    /**
     * Optimize product title using AI
     *
     * @param WC_Product $product The product to enhance
     * @return array|WP_Error Success array or error object
     */
    private function ai_optimize_title( $product ) {
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            return new WP_Error( 'missing_client', __( 'Google API Client not available.', 'cirrusly-commerce' ) );
        }
        
        $payload = array(
            'product_id' => $product->get_id(),
            'current_title' => $product->get_name(),
            'description' => $product->get_description(),
            'categories' => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
            'request_type' => 'optimize_title'
        );
        
        $response = Cirrusly_Commerce_Google_API_Client::request( 'product_studio_generate', $payload, array( 'timeout' => 30 ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        if ( ! isset( $response['generated_text'] ) ) {
            return new WP_Error( 'invalid_response', __( 'API did not return optimized title.', 'cirrusly-commerce' ) );
        }
        
        // Update product title
        $product->set_name( $response['generated_text'] );
        $product->save();
        
        return array(
            'message' => __( 'Product title optimized successfully!', 'cirrusly-commerce' ),
            'fields' => array( 'title' => $response['generated_text'] )
        );
    }
    
    /**
     * Enhance product images with AI-generated alt text and metadata
     *
     * @param WC_Product $product The product to enhance
     * @return array|WP_Error Success array or error object
     */
    private function ai_enhance_images( $product ) {
        if ( ! class_exists( 'Cirrusly_Commerce_Google_API_Client' ) ) {
            return new WP_Error( 'missing_client', __( 'Google API Client not available.', 'cirrusly-commerce' ) );
        }
        
        // Get all product images (featured + gallery) - consistent with apply flow
        $image_ids = array();
        if ( $product->get_image_id() ) {
            $image_ids[] = $product->get_image_id();
        }
        $gallery_ids = $product->get_gallery_image_ids();
        if ( ! empty( $gallery_ids ) ) {
            $image_ids = array_merge( $image_ids, $gallery_ids );
        }
        
        if ( empty( $image_ids ) ) {
            return new WP_Error( 'no_images', __( 'Product has no images.', 'cirrusly-commerce' ) );
        }
        
        $categories = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) );
        $payload = array(
            'product_id' => $product->get_id(),
            'product_name' => $this->sanitize_for_json( $product->get_name() ),
            'action_type' => 'enhance_images',
            'enhancement' => 'default',
            'product_data' => array(
                'title' => $this->sanitize_for_json( $product->get_name() ),
                'description' => $this->sanitize_for_json( $product->get_description() ),
                'categories' => $this->sanitize_categories_for_json( $categories ),
                'image_count' => count( $image_ids )
            )
        );
        
        $response = Cirrusly_Commerce_Google_API_Client::request( 'product_studio_generate', $payload, array( 'timeout' => 30 ) );
        
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        
        if ( ! isset( $response['generated_text'] ) ) {
            return new WP_Error( 'invalid_response', __( 'API did not return image metadata.', 'cirrusly-commerce' ) );
        }
        
        // Parse pipe-separated alt texts (consistent with apply flow)
        $alt_texts = array_map( 'trim', explode( '|', $response['generated_text'] ) );
        
        // Update alt text for all images
        $updated_count = 0;
        foreach ( $image_ids as $index => $image_id ) {
            if ( isset( $alt_texts[ $index ] ) && ! empty( $alt_texts[ $index ] ) ) {
                update_post_meta( $image_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_texts[ $index ] ) );
                $updated_count++;
            }
        }
        
        return array(
            'message' => sprintf(
                /* translators: %d: number of images updated */
                _n( 'Updated alt text for %d image.', 'Updated alt text for %d images.', $updated_count, 'cirrusly-commerce' ),
                $updated_count
            ),
            'fields' => array( 'images_updated' => $updated_count )
        );
    }
}
