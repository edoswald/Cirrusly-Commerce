/**
 * API Key Automation Handler for Setup Wizard
 *
 * Handles AJAX operations for:
 * - API key validation
 * - API key regeneration
 * - Manual key linking
 * - New key generation
 */

(function($) {
    'use strict';

    // Validate API Key Button
    $(document).on('click', '.cirrusly-validate-api-key-btn', function(e) {
        e.preventDefault();

        const button = $(this);
        const statusContainer = $('.cirrusly-api-validation-status');

        // Set loading state
        button.prop('disabled', true).text(cirruslyWizardApiAutomation.i18n.validating);
        statusContainer.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

        // Make AJAX request
        $.ajax({
            url: cirruslyWizardApiAutomation.ajax_url,
            type: 'POST',
            data: {
                action: 'cirrusly_validate_api_key',
                _nonce: cirruslyWizardApiAutomation.nonce_validate
            },
            success: function(response) {
                if (response.success) {
                    // Success - show status with plan and quota info
                    const data = response.data;
                    let html = '<span style="color: #008a20; font-weight: 600;">✓ ' + cirruslyWizardApiAutomation.i18n.valid + '</span>';

                    if (data.quota_used !== undefined && data.quota_limit !== undefined) {
                        html += '<span style="margin-left: 10px; font-size: 12px; color: #666;">(' + data.quota_used + '/' + data.quota_limit + ' ' + cirruslyWizardApiAutomation.i18n.calls_today + ')</span>';
                    }

                    statusContainer.html(html);
                } else {
                    // Error - show error message
                    const errorMsg = response.data && response.data.message ? response.data.message : cirruslyWizardApiAutomation.i18n.unknown_error;
                    statusContainer.html('<span style="color: #d63638; font-weight: 600;">✗ ' + errorMsg + '</span>');
                }
            },
            error: function(xhr, status, error) {
                // Network/server error
                statusContainer.html('<span style="color: #d63638; font-weight: 600;">✗ ' + cirruslyWizardApiAutomation.i18n.network_error + '</span>');
                console.error('Validation AJAX error:', error);
            },
            complete: function() {
                // Reset button state
                button.prop('disabled', false).text(button.data('original-text') || cirruslyWizardApiAutomation.i18n.validate_connection);
            }
        });
    });

    // Regenerate API Key Button
    $(document).on('click', '.cirrusly-regenerate-api-key-btn', function(e) {
        e.preventDefault();

        const button = $(this);

        // Show confirmation dialog with reason selection
        const reason = prompt(
            cirruslyWizardApiAutomation.i18n.regenerate_confirm + '\n\n' +
            '1 = ' + cirruslyWizardApiAutomation.i18n.reason_compromise + '\n' +
            '2 = ' + cirruslyWizardApiAutomation.i18n.reason_testing + '\n' +
            '3 = ' + cirruslyWizardApiAutomation.i18n.reason_other + '\n\n' +
            cirruslyWizardApiAutomation.i18n.reason_prompt
        );

        if (!reason) {
            return; // User cancelled
        }

        // Map user input to reason strings
        const reasonMap = {
            '1': 'compromise',
            '2': 'testing',
            '3': 'other'
        };

        const reasonString = reasonMap[reason];
        if (!reasonString) {
            alert(cirruslyWizardApiAutomation.i18n.invalid_selection);
            return;
        }

        // Set loading state
        const originalText = button.text();
        button.prop('disabled', true).text(cirruslyWizardApiAutomation.i18n.regenerating);

        // Make AJAX request
        $.ajax({
            url: cirruslyWizardApiAutomation.ajax_url,
            type: 'POST',
            data: {
                action: 'cirrusly_regenerate_api_key',
                _nonce: cirruslyWizardApiAutomation.nonce_regenerate,
                reason: reasonString
            },
            success: function(response) {
                if (response.success) {
                    // Success - reload page to show new key status
                    alert(cirruslyWizardApiAutomation.i18n.regenerate_success);
                    location.reload();
                } else {
                    // Error - show error message (likely cooldown)
                    const errorMsg = response.data && response.data.message ? response.data.message : cirruslyWizardApiAutomation.i18n.unknown_error;
                    alert(cirruslyWizardApiAutomation.i18n.regenerate_failed + ' ' + errorMsg);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                // Network/server error
                alert(cirruslyWizardApiAutomation.i18n.network_error);
                button.prop('disabled', false).text(originalText);
                console.error('Regeneration AJAX error:', error);
            }
        });
    });

    // Link Manual Key Button
    $(document).on('click', '.cirrusly-link-manual-key-btn', function(e) {
        e.preventDefault();

        const button = $(this);

        // Show confirmation dialog
        if (!confirm(cirruslyWizardApiAutomation.i18n.link_confirm)) {
            return;
        }

        // Set loading state
        const originalText = button.text();
        button.prop('disabled', true).text(cirruslyWizardApiAutomation.i18n.linking);

        // Make AJAX request
        $.ajax({
            url: cirruslyWizardApiAutomation.ajax_url,
            type: 'POST',
            data: {
                action: 'cirrusly_link_manual_key',
                _nonce: cirruslyWizardApiAutomation.nonce_link
            },
            success: function(response) {
                if (response.success) {
                    // Success - reload page to show updated status
                    alert(cirruslyWizardApiAutomation.i18n.link_success);
                    location.reload();
                } else {
                    // Error - show error message
                    const errorMsg = response.data && response.data.message ? response.data.message : cirruslyWizardApiAutomation.i18n.unknown_error;
                    alert(cirruslyWizardApiAutomation.i18n.link_failed + ' ' + errorMsg);
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                // Network/server error
                alert(cirruslyWizardApiAutomation.i18n.network_error);
                button.prop('disabled', false).text(originalText);
                console.error('Link AJAX error:', error);
            }
        });
    });

    // Generate API Key Button (for users without a key)
    $(document).on('click', '.cirrusly-generate-api-key-btn', function(e) {
        e.preventDefault();

        const button = $(this);
        const statusContainer = $('.cirrusly-api-generation-status');

        // Set loading state
        const originalText = button.text();
        button.prop('disabled', true).text(cirruslyWizardApiAutomation.i18n.generating);
        statusContainer.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

        // Make AJAX request
        $.ajax({
            url: cirruslyWizardApiAutomation.ajax_url,
            type: 'POST',
            data: {
                action: 'cirrusly_generate_api_key',
                _nonce: cirruslyWizardApiAutomation.nonce_generate
            },
            success: function(response) {
                if (response.success) {
                    // Success - reload page to show new key
                    statusContainer.html('<span style="color: #008a20; font-weight: 600;">✓ ' + cirruslyWizardApiAutomation.i18n.generate_success + '</span>');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    // Error - show error message
                    const errorMsg = response.data && response.data.message ? response.data.message : cirruslyWizardApiAutomation.i18n.unknown_error;
                    statusContainer.html('<span style="color: #d63638; font-weight: 600;">✗ ' + errorMsg + '</span>');
                    button.prop('disabled', false).text(originalText);
                }
            },
            error: function(xhr, status, error) {
                // Network/server error
                statusContainer.html('<span style="color: #d63638; font-weight: 600;">✗ ' + cirruslyWizardApiAutomation.i18n.network_error + '</span>');
                button.prop('disabled', false).text(originalText);
                console.error('Generation AJAX error:', error);
            }
        });
    });

})(jQuery);
