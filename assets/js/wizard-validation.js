/**
 * Product Studio Validation Handler for Setup Wizard
 * 
 * Handles AJAX validation button clicks for:
 * - API Access verification
 * - IAM Permissions check
 * - Merchant Linkage validation
 */

(function($) {
    'use strict';

    // Toggle help tooltips
    $(document).on('click', '.cirrusly-help-icon', function(e) {
        e.preventDefault();
        const helpType = $(this).data('help');
        const tooltip = $(`.cirrusly-help-tooltip[data-for="${helpType}"]`);
        
        // Hide all other tooltips
        $('.cirrusly-help-tooltip').not(tooltip).slideUp(200);
        
        // Toggle this tooltip
        tooltip.slideToggle(200);
    });

    // Handle validation button clicks
    $(document).on('click', '.cirrusly-validate-btn', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const action = button.data('action');
        let statusContainer, nonce, ajaxAction;

        // Map button actions to AJAX actions and status containers
        switch(action) {
            case 'validate_ps_api':
                statusContainer = $('#status-api');
                ajaxAction = 'cirrusly_validate_ps_api';
                nonce = cirruslyWizardValidation.nonce_api;
                break;
            case 'validate_ps_iam':
                statusContainer = $('#status-iam');
                ajaxAction = 'cirrusly_validate_ps_iam';
                nonce = cirruslyWizardValidation.nonce_iam;
                break;
            case 'validate_ps_linkage':
                statusContainer = $('#status-linkage');
                ajaxAction = 'cirrusly_validate_ps_linkage';
                nonce = cirruslyWizardValidation.nonce_linkage;
                break;
            default:
                return;
        }

        // Set loading state
        button.prop('disabled', true).text(cirruslyWizardValidation.i18n.testing);
        statusContainer.html('<span style="color: #999;"><span class="spinner is-active" style="float: none; margin: 0 5px 0 0;"></span>' + cirruslyWizardValidation.i18n.checking + '</span>');

        // Make AJAX request
        $.ajax({
            url: cirruslyWizardValidation.ajax_url,
            type: 'POST',
            data: {
                action: ajaxAction,
                _nonce: nonce
            },
            success: function(response) {
                if (response.success) {
                    // Success - show green checkmark (safely)
                    statusContainer.empty();
                    var successSpan = $('<span>', {
                        'style': 'color: #008a20;'
                    }).text('✓ ' + response.data.message);
                    statusContainer.append(successSpan);
                } else {
                    // Error - show red X and error message (safely)
                    const errorMsg = response.data && response.data.message ? response.data.message : cirruslyWizardValidation.i18n.unknown_error;
                    statusContainer.empty();
                    var errorSpan = $('<span>', {
                        'style': 'color: #d63638;'
                    }).text('✗ ' + errorMsg);
                    statusContainer.append(errorSpan);
                    
                    // If there's a help URL, show it (with validation)
                    if (response.data && response.data.help_url) {
                        var helpUrl = response.data.help_url;
                        // Validate URL is http(s) or relative
                        if (helpUrl && (helpUrl.startsWith('http://') || helpUrl.startsWith('https://') || helpUrl.startsWith('/'))) {
                            statusContainer.append($('<br>'));
                            var helpLink = $('<a>', {
                                'href': helpUrl,
                                'target': '_blank',
                                'style': 'font-size: 12px;'
                            }).text(cirruslyWizardValidation.i18n.fix_this);
                            statusContainer.append(helpLink);
                        }
                    }
                }
            },
            error: function(xhr, status, error) {
                // Network/server error (safely)
                statusContainer.empty();
                var networkErrorSpan = $('<span>', {
                    'style': 'color: #d63638;'
                }).text('✗ ' + cirruslyWizardValidation.i18n.network_error);
                statusContainer.append(networkErrorSpan);
                console.error('Validation AJAX error:', error);
            },
            complete: function() {
                // Reset button state
                button.prop('disabled', false);
                
                // Restore button text based on action
                switch(action) {
                    case 'validate_ps_api':
                        button.text(cirruslyWizardValidation.i18n.test_api);
                        break;
                    case 'validate_ps_iam':
                        button.text(cirruslyWizardValidation.i18n.verify_permissions);
                        break;
                    case 'validate_ps_linkage':
                        button.text(cirruslyWizardValidation.i18n.check_linkage);
                        break;
                }
            }
        });
    });

})(jQuery);
