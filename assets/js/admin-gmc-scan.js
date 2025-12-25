/**
 * Cirrusly Commerce - GMC Scan with AI Preview Modal
 * 
 * Two-step workflow for AI content generation with enhancement styles:
 * 1. Preview: Generate content without saving (user can edit and choose styles)
 * 2. Apply: Save user-approved content to product
 * 
 * @package Cirrusly_Commerce
 */

(function($) {
    'use strict';

    // State management
    var modalState = {
        productId: null,
        action: null,
        originalButton: null,
        originalHtml: null,
        enhancement: 'default'
    };

    // Enhancement options for each action type
    var enhancementOptions = {
        'generate_description': [
            { value: 'default', label: 'Balanced', icon: 'dashicons-analytics' },
            { value: 'professional', label: 'Professional', icon: 'dashicons-businessman' },
            { value: 'engaging', label: 'Engaging', icon: 'dashicons-heart' },
            { value: 'technical', label: 'Technical', icon: 'dashicons-admin-tools' },
            { value: 'seo', label: 'SEO Optimized', icon: 'dashicons-search' },
            { value: 'concise', label: 'Concise', icon: 'dashicons-editor-contract' }
        ],
        'optimize_title': [
            { value: 'default', label: 'Balanced', icon: 'dashicons-analytics' },
            { value: 'keyword_first', label: 'Keyword First', icon: 'dashicons-tag' },
            { value: 'brand_focus', label: 'Brand Focus', icon: 'dashicons-star-filled' },
            { value: 'feature_rich', label: 'Feature Rich', icon: 'dashicons-list-view' },
            { value: 'benefit_driven', label: 'Benefit Driven', icon: 'dashicons-thumbs-up' }
        ],
        'enhance_images': [
            { value: 'default', label: 'Standard', icon: 'dashicons-analytics' },
            { value: 'descriptive', label: 'Descriptive', icon: 'dashicons-visibility' },
            { value: 'seo_focused', label: 'SEO Focused', icon: 'dashicons-search' }
        ],
        'generate_image': [
            { value: 'blur_bg', label: 'Blur Background', icon: 'dashicons-camera', cost: 'FREE' },
            { value: 'color_bg', label: 'Color Background', icon: 'dashicons-art', cost: 'FREE' },
            { value: 'gradient_bg', label: 'Gradient Background', icon: 'dashicons-image-filter', cost: 'FREE' },
            { value: 'remove_bg', label: 'Remove Background', icon: 'dashicons-format-image', cost: '.02' },
            { value: 'white_bg', label: 'White Background', icon: 'dashicons-marker', cost: '.02' },
            { value: 'scene_bg', label: 'AI Scene Background', icon: 'dashicons-admin-home', cost: '.04' },
            { value: 'product_photo', label: 'Generate New Image', icon: 'dashicons-camera-alt', cost: '.02' }
        ]
    };

    /**
     * Escape HTML entities to prevent XSS attacks.
     * 
     * @param {string} text - The text to escape
     * @return {string} - Escaped text safe for HTML insertion
     */
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    /**
     * Initialize: Open modal and start preview generation
     */
    $(document).on('click', '.cirrusly-ai-fix-btn', function(e) {
        e.preventDefault();
        
        // Store state
        modalState.originalButton = $(this);
        modalState.originalHtml = $(this).html();
        modalState.productId = $(this).data('product-id');
        modalState.action = $(this).data('action');
        modalState.enhancement = 'default';
        
        // Validation
        if (!modalState.productId || !modalState.action) {
            alert('Invalid product or action');
            return;
        }
        
        // Set enhancement to first valid option for this action (not 'default')
        var options = enhancementOptions[modalState.action] || [];
        if (options.length > 0) {
            modalState.enhancement = options[0].value;
        }
        
        // Check if this is an image quality issue (specific image flagged by Google)
        var $row = $(this).closest('tr');
        var $issueBadge = $row.find('.gmc-badge[data-image-path]').first();
        var imagePath = $issueBadge.data('image-path');
        
        // Capture Google's original warning message for context
        var googleMessage = '';
        var $anyBadge = $row.find('.gmc-badge').first();
        
        if ($anyBadge.length > 0) {
            // Extract Google's detailed message from the tooltip attribute (where GMC stores the full explanation)
            googleMessage = $anyBadge.attr('data-tooltip') ||  // Most common location for Google's detailed message
                           $anyBadge.data('tooltip') ||        // jQuery cached version
                           $anyBadge.attr('data-detail') ||    // Alternative attribute
                           $anyBadge.data('reason') ||         // Fallback to reason field
                           $anyBadge.text().trim();            // Last resort: use badge text
        }
        modalState.googleMessage = googleMessage;
        
        if (imagePath && modalState.action === 'generate_image') {
            // Image quality issue - resolve path to attachment ID first
            resolveImagePath(imagePath);
        } else {
            // Regular issue - open modal with normal product context
            openModal();
            populateEnhancementSelector();
            generatePreview();
        }
    });

    /**
     * Resolve Google-flagged image path to WordPress attachment ID
     * Used for targeting specific image in "Low Image Quality" issues
     */
    function resolveImagePath(imagePath) {
        // Validate that required global JS is loaded before attempting AJAX
        if (typeof cirruslyGMCScan === 'undefined' || !cirruslyGMCScan.product_studio_nonce) {
            // Restore button state
            modalState.originalButton.html(modalState.originalHtml);
            
            // Show clear error message
            alert('Error: Admin scripts not properly loaded. Please refresh the page and try again.\n\n' +
                  'If this error persists, there may be a JavaScript conflict with another plugin.');
            return;
        }
        
        // Show loading state
        modalState.originalButton.html('<span class="dashicons dashicons-update-alt dashicons-spin"></span> Locating Image...');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cirrusly_resolve_image_path',
                image_path: imagePath,
                nonce: cirruslyGMCScan.product_studio_nonce
            },
            success: function(response) {
                if (response.success && response.data.attachment_id) {
                    // Store resolved image context
                    modalState.targetImageUrl = response.data.url;
                    modalState.targetAttachmentId = response.data.attachment_id;
                    modalState.targetImageWidth = response.data.width;
                    modalState.targetImageHeight = response.data.height;
                    
                    // Open modal with specific image pre-loaded
                    openModal();
                    populateEnhancementSelector();
                    
                    // Update modal title to show targeting specific image
                    var titleHtml = 'Enhance Image: <strong>' + response.data.title + '</strong> (' + 
                                   response.data.width + 'x' + response.data.height + 'px)';
                    
                    // Add Google's warning if available (escape to prevent XSS)
                    if (modalState.googleMessage) {
                        titleHtml += '<div style="margin-top: 8px; padding: 8px 12px; background: #fff3cd; border-left: 3px solid #ffc107; font-size: 12px; color: #856404; border-radius: 3px; font-weight: normal;">' +
                                    '<span class="dashicons dashicons-warning" style="color: #ffc107; font-size: 16px; vertical-align: middle;"></span> ' +
                                    '<strong>Google says:</strong> ' + escapeHtml(modalState.googleMessage) +
                                    '</div>';
                    }
                    
                    $('.cirrusly-preview-product-name').html(titleHtml);
                    
                    generatePreview();
                } else {
                    // Image not found - show helpful error
                    var errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Could not locate flagged image in Media Library. It may have been deleted or moved.';
                    
                    alert(errorMsg);
                    modalState.originalButton.html(modalState.originalHtml);
                }
            },
            error: function() {
                alert('Failed to resolve image path. Please try again.');
                modalState.originalButton.html(modalState.originalHtml);
            }
        });
    }

    /**
     * Open modal and reset to loading state
     */
    function openModal() {
        $('#cirrusly-ai-preview-modal').fadeIn(200);
        $('.cirrusly-preview-loading').show();
        $('.cirrusly-preview-content').hide();
        $('.cirrusly-apply-btn, .cirrusly-regenerate-btn').hide();
        $('.cirrusly-char-count').hide();
        
        // Reset editable content
        $('.cirrusly-preview-current').empty();
        $('.cirrusly-preview-generated').empty();
        $('.cirrusly-preview-product-name').empty();
    }

    /**
     * Populate enhancement selector based on action type
     */
    function populateEnhancementSelector() {
        var options = enhancementOptions[modalState.action] || [];
        
        if (options.length === 0) {
            $('.cirrusly-enhancement-selector').hide();
            return;
        }
        
        var optionsHtml = '';
        var hasPaidOptions = false;
        
        options.forEach(function(opt, index) {
            var activeClass = index === 0 ? ' active' : '';
            var costBadge = '';
            
            // Add cost indicator for image generation options
            if (opt.cost) {
                var badgeClass = 'cirrusly-cost-badge';
                if (opt.cost === 'FREE') {
                    badgeClass += ' cirrusly-cost-free';
                } else if (opt.cost === '.02') {
                    badgeClass += ' cirrusly-cost-basic';
                    hasPaidOptions = true;
                } else {
                    badgeClass += ' cirrusly-cost-advanced';
                    hasPaidOptions = true;
                }
                costBadge = '<span class="' + badgeClass + '">' + opt.cost + '</span>';
            }
            
            optionsHtml += '<div class="cirrusly-enhancement-option' + activeClass + '" data-enhancement="' + opt.value + '">' +
                           '<span class="dashicons ' + opt.icon + '"></span>' +
                           opt.label +
                           costBadge +
                           '</div>';
        });
        
        // Add cost explanation if paid options exist
        if (hasPaidOptions) {
            optionsHtml += '<div style="margin-top: 10px; padding: 8px; background: #f0f6fc; border-left: 3px solid #0073aa; font-size: 11px; color: #646970; border-radius: 3px;">' +
                          '<span class="dashicons dashicons-info-outline" style="font-size: 14px; vertical-align: middle;"></span> ' +
                          'Costs shown are estimated Google Cloud API charges per image' +
                          '</div>';
        }
        
        $('.cirrusly-enhancement-options').html(optionsHtml);
        $('.cirrusly-enhancement-selector').fadeIn(200);
    }

    /**
     * Handle enhancement option selection
     */
    $(document).on('click', '.cirrusly-enhancement-option', function() {
        $('.cirrusly-enhancement-option').removeClass('active');
        $(this).addClass('active');
        
        modalState.enhancement = $(this).data('enhancement');
        
        // Regenerate preview with new enhancement
        $('.cirrusly-preview-content').hide();
        $('.cirrusly-preview-loading').show();
        $('.cirrusly-apply-btn, .cirrusly-regenerate-btn').hide();
        generatePreview();
    });

    /**
     * Generate AI content preview (Step 1: Preview without saving)
     */
    function generatePreview() {
        // Build request data
        var requestData = {
            action: 'cirrusly_ai_preview_product',
            security: cirruslyGMCScan.preview_nonce,
            product_id: modalState.productId,
            fix_action: modalState.action,
            enhancement: modalState.enhancement
        };
        
        // Add target image context if available (for Google-flagged image quality issues)
        if (modalState.targetAttachmentId) {
            requestData.target_attachment_id = modalState.targetAttachmentId;
            requestData.target_image_url = modalState.targetImageUrl;
        }
        
        $.ajax({
            url: cirruslyGMCScan.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: requestData,
            success: function(response) {
                // DEBUG: Log the full response to console
                console.log('Preview AJAX Success Response:', response);
                
                if (response.success) {
                    // Populate modal with preview data
                    var data = response.data;
                    
                    // Build title with optional Google warning
                    var titleHtml = data.product_name || 'Product';
                    
                    // Add Google's warning if available (escape to prevent XSS)
                    if (modalState.googleMessage) {
                        titleHtml += '<div style="margin-top: 8px; padding: 8px 12px; background: #fff3cd; border-left: 3px solid #ffc107; font-size: 12px; color: #856404; border-radius: 3px; font-weight: normal;">' +
                                    '<span class="dashicons dashicons-warning" style="color: #ffc107; font-size: 16px; vertical-align: middle;"></span> ' +
                                    '<strong>Google says:</strong> ' + escapeHtml(modalState.googleMessage) +
                                    '</div>';
                    }
                    
                    $('.cirrusly-preview-product-name').html(titleHtml);
                    
                    // Handle image generation differently
                    if (data.is_image) {
                        // Show current image or placeholder
                        if (data.current && data.current !== '(No featured image)') {
                            var $currentImg = $('<img>')
                                .attr('src', data.current)
                                .css({
                                    'max-width': '100%',
                                    'height': 'auto',
                                    'border-radius': '4px',
                                    'border': '1px solid #ddd'
                                });
                            $('.cirrusly-preview-current').empty().append($currentImg);
                        } else {
                            $('.cirrusly-preview-current').html(
                                '<div style="padding:40px; background:#f7f7f7; text-align:center; color:#999; border-radius:4px; border:1px dashed #ddd;">' +
                                '<span class="dashicons dashicons-format-image" style="font-size:48px; width:48px; height:48px;"></span><br>' +
                                'No Current Image' +
                                '</div>'
                            );
                        }
                        
                        // Show generated image from base64
                        var imageDataUrl = 'data:' + (data.mime_type || 'image/png') + ';base64,' + data.generated;
                        var $generatedImg = $('<img>')
                            .attr('src', imageDataUrl)
                            .css({
                                'max-width': '100%',
                                'height': 'auto',
                                'border-radius': '4px',
                                'border': '1px solid #ddd'
                            });
                        $('.cirrusly-preview-generated').empty().append($generatedImg);
                        
                        // Hide character count for images
                        $('.cirrusly-char-count').hide();
                        
                    } else {
                        // Text content (existing logic)
                        $('.cirrusly-preview-current').text(data.current || '(No content)');
                        $('.cirrusly-preview-generated').text(data.generated || '');
                        
                        // Update character count for titles
                        if (modalState.action === 'optimize_title') {
                            updateCharCount();
                            $('.cirrusly-char-count').show();
                            
                            // Update on edit - use namespaced event to prevent handler leak
                            $('.cirrusly-preview-generated').off('input.cirruslyCharCount').on('input.cirruslyCharCount', updateCharCount);
                        } else {
                            $('.cirrusly-char-count').hide();
                        }
                    }
                    
                    // Show content, hide loading
                    $('.cirrusly-preview-loading').fadeOut(200, function() {
                        $('.cirrusly-preview-content').fadeIn(200);
                        $('.cirrusly-apply-btn, .cirrusly-regenerate-btn').fadeIn(200);
                    });
                    
                } else {
                    // Error from server
                    // DEBUG: Log what error we're getting
                    console.error('Preview Error Response:', response);
                    console.error('Error Message:', response.data && response.data.message ? response.data.message : 'No message');
                    
                    closeModalWithError(
                        response.data && response.data.message 
                            ? response.data.message 
                            : 'Failed to generate preview. Please try again.'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Preview AJAX Error:', error);
                console.error('XHR Status:', status);
                console.error('Response Text:', xhr.responseText);
                
                var errorMsg = 'Network error occurred.';
                if (xhr.responseText) {
                    errorMsg += ' Server response: ' + xhr.responseText.substring(0, 200);
                }
                
                closeModalWithError(errorMsg);
            }
        });
    }

    /**
     * Update character count display for titles
     */
    function updateCharCount() {
        var text = $('.cirrusly-preview-generated').text();
        var length = text.length;
        var $counter = $('.cirrusly-char-count');
        
        $counter.removeClass('warning error');
        
        if (length > 150) {
            $counter.addClass('error');
        } else if (length > 130) {
            $counter.addClass('warning');
        }
        
        $counter.text(length + ' / 150 chars');
    }

    /**
     * Regenerate content (re-run preview generation)
     */
    $(document).on('click', '.cirrusly-regenerate-btn', function() {
        $('.cirrusly-preview-content').hide();
        $('.cirrusly-preview-loading').show();
        $('.cirrusly-apply-btn, .cirrusly-regenerate-btn').hide();
        generatePreview();
    });

    /**
     * Apply changes (Step 2: Save user-approved content)
     */
    $(document).on('click', '.cirrusly-apply-btn', function() {
        var generatedContent;
        var isImage = $('.cirrusly-preview-generated img').length > 0;
        
        if (isImage) {
            // Extract base64 data from image src
            var imgSrc = $('.cirrusly-preview-generated img').attr('src');
            
            // Validate data URL format with regex
            var dataUrlRegex = /^data:[\w\/\+\.-]+;base64,/;
            if (!imgSrc || !dataUrlRegex.test(imgSrc)) {
                alert('Invalid image data format. Expected a valid base64 data URL.');
                return;
            }
            
            // Safely extract base64 string
            var parts = imgSrc.split(',');
            if (parts.length !== 2 || !parts[1] || parts[1].trim() === '') {
                alert('Malformed data URL. Unable to extract base64 content.');
                return;
            }
            
            var base64String = parts[1];
            
            // Validate base64 characters (A-Z, a-z, 0-9, +, /, =)
            var base64Regex = /^[A-Za-z0-9+/]+=*$/;
            if (!base64Regex.test(base64String)) {
                alert('Invalid base64 content detected. Data may be corrupted.');
                return;
            }
            
            // Attempt safe decode test to catch malformed content
            try {
                if (typeof atob !== 'undefined') {
                    atob(base64String); // Test decode - will throw if invalid
                }
            } catch (e) {
                alert('Failed to decode base64 data: ' + e.message);
                return;
            }
            
            generatedContent = base64String;
            
        } else {
            // Text content
            generatedContent = $('.cirrusly-preview-generated').text();
            
            // Validation
            if (!generatedContent || generatedContent.trim() === '') {
                alert('No content to apply.');
                return;
            }
            
            // Title length validation
            if (modalState.action === 'optimize_title' && generatedContent.length > 150) {
                if (!confirm('Title exceeds 150 characters (' + generatedContent.length + ' chars). Google Shopping may truncate it. Apply anyway?')) {
                    return;
                }
            }
        }
        
        // Disable button and show loading
        var $applyBtn = $(this);
        $applyBtn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation:spin 1s linear infinite;margin-top:3px;"></span> Applying...');
        
        // Make AJAX request to save content
        $.ajax({
            url: cirruslyGMCScan.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cirrusly_ai_apply_fix',
                security: cirruslyGMCScan.apply_nonce,
                product_id: modalState.productId,
                fix_action: modalState.action,
                generated_content: generatedContent,
                enhancement: modalState.enhancement
            },
            success: function(response) {
                if (response.success) {
                    // Update original button to show success
                    if (modalState.originalButton) {
                        modalState.originalButton
                            .html('<span class="dashicons dashicons-yes" style="color:#008a20;"></span> Fixed!')
                            .css({
                                'background': '#e5f5e5',
                                'color': '#008a20',
                                'border-color': '#008a20',
                                'opacity': '1'
                            })
                            .prop('disabled', false);
                    }
                    
                    // Close modal
                    $('#cirrusly-ai-preview-modal').fadeOut(200);
                    
                    // Show success notice
                    showNotice('success', response.data.message || 'Product updated successfully!');
                    
                    // Reload scan results after 2 seconds
                    setTimeout(function() {
                        var scanButton = $('button[name="run_scan"]');
                        if (scanButton.length) {
                            scanButton.click();
                        } else {
                            location.reload();
                        }
                    }, 2000);
                    
                } else {
                    // Error from server
                    $applyBtn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top:3px;"></span> Apply Changes');
                    showNotice(
                        'error', 
                        response.data && response.data.message 
                            ? response.data.message 
                            : 'Failed to apply changes. Please try again.'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('Apply AJAX Error:', error);
                console.error('XHR Status:', status);
                console.error('Response Text:', xhr.responseText);
                
                $applyBtn.prop('disabled', false).html('<span class="dashicons dashicons-saved" style="margin-top:3px;"></span> Apply Changes');
                
                var errorMsg = 'Network error occurred.';
                if (xhr.responseText) {
                    errorMsg += ' Server response: ' + xhr.responseText.substring(0, 200);
                }
                
                showNotice('error', errorMsg);
            }
        });
    });

    /**
     * Close modal (Cancel or X button)
     */
    $(document).on('click', '.cirrusly-modal-close, .cirrusly-modal-cancel', function() {
        $('#cirrusly-ai-preview-modal').fadeOut(200);
        $('.cirrusly-enhancement-selector').hide();
        resetButtonState();
    });

    /**
     * Close modal when clicking overlay
     */
    $(document).on('click', '.cirrusly-modal-overlay', function() {
        $('#cirrusly-ai-preview-modal').fadeOut(200);
        $('.cirrusly-enhancement-selector').hide();
        resetButtonState();
    });

    /**
     * Close modal with error message and restore button
     */
    function closeModalWithError(message) {
        $('#cirrusly-ai-preview-modal').fadeOut(200);
        $('.cirrusly-enhancement-selector').hide();
        showNotice('error', message);
        resetButtonState();
    }

    /**
     * Reset original button to initial state
     */
    function resetButtonState() {
        if (modalState.originalButton && modalState.originalHtml) {
            modalState.originalButton
                .html(modalState.originalHtml)
                .css({
                    'background': 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                    'color': '#fff',
                    'border': 'none',
                    'opacity': '1'
                })
                .prop('disabled', false);
        }
        
        // Reset state
        modalState = {
            productId: null,
            action: null,
            originalButton: null,
            originalHtml: null,
            enhancement: 'default'
        };
    }

    /**
     * Show WordPress admin notice
     * 
     * @param {string} type - 'success' or 'error'
     * @param {string} message - Notice message
     */
    function showNotice(type, message) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div>', {
            'class': 'notice ' + noticeClass + ' is-dismissible',
            'style': 'margin: 15px 0;'
        });
        
        // Safely create and append message paragraph
        var messageParagraph = $('<p>').text(message);
        notice.append(messageParagraph);
        
        // Insert at top of page
        $('.wrap h1').after(notice);
        
        // Safely create and append dismiss button
        var dismissButton = $('<button>', {
            'type': 'button',
            'class': 'notice-dismiss'
        });
        var screenReaderText = $('<span>', {
            'class': 'screen-reader-text'
        }).text('Dismiss');
        dismissButton.append(screenReaderText);
        notice.append(dismissButton);
        
        // Handle dismiss
        dismissButton.on('click', function() {
            notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Auto-dismiss success after 5 seconds
        if (type === 'success') {
            setTimeout(function() {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);
        }
    }
    
    // Add spinning animation for loading icon
    $('<style>@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>').appendTo('head');

})(jQuery);