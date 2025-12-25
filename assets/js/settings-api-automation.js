/**
 * API Key Automation Handler for Settings Page
 *
 * Handles AJAX operations for:
 * - API key validation with detailed display
 * - API key regeneration
 * - Manual key linking
 * - New key generation
 */

(function($) {
    'use strict';

    // Validate API Key Button with Enhanced Details Display
    $(document).on('click', '.cirrusly-validate-api-key-btn', function(e) {
        e.preventDefault();

        const button = $(this);
        const statusContainer = $('.cirrusly-api-validation-status');
        const detailsContainer = $('#cirrusly-api-validation-details');

        // Set loading state
        button.prop('disabled', true).text(cirruslySettingsApiAutomation.i18n.validating);
        statusContainer.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');
        detailsContainer.hide();

        // Make AJAX request
        $.ajax({
            url: cirruslySettingsApiAutomation.ajax_url,
            type: 'POST',
            data: {
                action: 'cirrusly_validate_api_key',
                nonce: cirruslySettingsApiAutomation.nonce_validate
            },
            success: function(response) {
                if (response.success) {
                    // Success - show status and populate details
                    const data = response.data;
                    statusContainer.html('<span style="color: #008a20; font-weight: 600; font-size: 14px;">✓ ' + cirruslySettingsApiAutomation.i18n.valid_connection + '</span>');

                    // Populate detail fields
                    if (data.plan_id) {
                        $('#cirrusly-api-plan').text(data.plan_id.charAt(0).toUpperCase() + data.plan_id.slice(1));
                    }

                    if (data.quota_used !== undefined && data.quota_limit !== undefined) {
                        const quotaPercent = data.quota_limit > 0 ? Math.round((data.quota_used / data.quota_limit) * 100) : 0;
                        let quotaColor = '#008a20'; // Green
                        if (quotaPercent > 80) quotaColor = '#d63638'; // Red
                        else if (quotaPercent > 60) quotaColor = '#f0ad4e'; // Amber

                        $('#cirrusly-api-quota').html(
                            '<span style="color: ' + quotaColor + ';">' + data.quota_used + '</span> / ' + data.quota_limit +
                            ' <span style="font-size: 11px; color: #666;">(' + quotaPercent + '%)</span>'
                        );
                    }

                    if (data.last_used) {
                        const lastUsed = new Date(data.last_used);
                        const now = new Date();
                        const diffMs = now - lastUsed;
                        const diffMins = Math.floor(diffMs / 60000);
                        const diffHours = Math.floor(diffMs / 3600000);
                        const diffDays = Math.floor(diffMs / 86400000);

                        let lastUsedText = '';
                        if (diffMins < 1) lastUsedText = cirruslySettingsApiAutomation.i18n.just_now;
                        else if (diffMins < 60) lastUsedText = diffMins + ' ' + (diffMins > 1 ? cirruslySettingsApiAutomation.i18n.mins_ago : cirruslySettingsApiAutomation.i18n.min_ago);
                        else if (diffHours < 24) lastUsedText = diffHours + ' ' + (diffHours > 1 ? cirruslySettingsApiAutomation.i18n.hours_ago : cirruslySettingsApiAutomation.i18n.hour_ago);
                        else lastUsedText = diffDays + ' ' + (diffDays > 1 ? cirruslySettingsApiAutomation.i18n.days_ago : cirruslySettingsApiAutomation.i18n.day_ago);

                        $('#cirrusly-api-last-used').text(lastUsedText);
                    } else {
                        $('#cirrusly-api-last-used').text(cirruslySettingsApiAutomation.i18n.never);
                    }

                    if (data.status) {
                        const statusText = data.status.charAt(0).toUpperCase() + data.status.slice(1);
                        const statusColor = data.status === 'active' ? '#008a20' : '#666';
                        $('#cirrusly-api-status').html('<span style="color: ' + statusColor + ';">' + statusText + '</span>');
                    }

                    // Show details panel with fade-in
                    detailsContainer.slideDown(300);
                } else {
                    // Error - show error message
                    const errorMsg = response.data && response.data.message ? response.data.message : cirruslySettingsApiAutomation.i18n.unknown_error;
                    statusContainer.html('<span style="color: #d63638; font-weight: 600; font-size: 14px;">✗ ' + errorMsg + '</span>');
                    detailsContainer.hide();
                }
            },
            error: function(xhr, status, error) {
                // Network/server error
                statusContainer.html('<span style="color: #d63638; font-weight: 600; font-size: 14px;">✗ ' + cirruslySettingsApiAutomation.i18n.network_error + '</span>');
                detailsContainer.hide();
                console.error('Validation AJAX error:', error);
            },
            complete: function() {
                // Reset button state
                button.prop('disabled', false).html('<span class="dashicons dashicons-yes-alt" style="margin-top: 3px;"></span> ' + cirruslySettingsApiAutomation.i18n.validate_connection);
            }
        });
    });

    // Regenerate API Key Button
    $(document).on('click', '.cirrusly-regenerate-api-key-btn', function(e) {
        e.preventDefault();

        const button = $(this);

        // Show confirmation dialog with reason selection
        const reason = prompt(
            cirruslySettingsApiAutomation.i18n.regenerate_confirm + '\n\n' +
            '1 = ' + cirruslySettingsApiAutomation.i18n.reason_compromise + '\n' +
            '2 = ' + cirruslySettingsApiAutomation.i18n.reason_testing + '\n' +
            '3 = ' + cirruslySettingsApiAutomation.i18n.reason_other + '\n\n' +
            cirruslySettingsApiAutomation.i18n.reason_prompt
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
            alert(cirruslySettingsApiAutomation.i18n.invalid_selection);
            return;
        }

        // Set loading state
        const originalHtml = button.html();
        button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; animation: rotation 1s infinite linear;"></span> ' + cirruslySettingsApiAutomation.i18n.regenerating);

        // Make AJAX request
        $.ajax({
            url: cirruslySettingsApiAutomation.ajax_url,
            type: 'POST',
            data: {
                action: 'cirrusly_regenerate_api_key',
                nonce: cirruslySettingsApiAutomation.nonce_regenerate,
                reason: reasonString
            },
            success: function(response) {
                if (response.success) {
                    // Success - reload page to show new key status
                    alert(cirruslySettingsApiAutomation.i18n.regenerate_success);
                    location.reload();
                } else {
                    // Error - show error message (likely cooldown)
                    const errorMsg = response.data && response.data.message ? response.data.message : cirruslySettingsApiAutomation.i18n.unknown_error;
                    alert(cirruslySettingsApiAutomation.i18n.regenerate_failed + ' ' + errorMsg);
                    button.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                // Network/server error
                alert(cirruslySettingsApiAutomation.i18n.network_error);
                button.prop('disabled', false).html(originalHtml);
                console.error('Regeneration AJAX error:', error);
            }
        });
    });

    // Link Manual Key Button
    $(document).on('click', '.cirrusly-link-manual-key-btn', function(e) {
        e.preventDefault();

        const button = $(this);

        // Show confirmation dialog
        if (!confirm(cirruslySettingsApiAutomation.i18n.link_confirm)) {
            return;
        }

        // Set loading state
        const originalHtml = button.html();
        button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; animation: rotation 1s infinite linear;"></span> ' + cirruslySettingsApiAutomation.i18n.linking);

        // Make AJAX request
        $.ajax({
            url: cirruslySettingsApiAutomation.ajax_url,
            type: 'POST',
            data: {
                action: 'cirrusly_link_manual_key',
                nonce: cirruslySettingsApiAutomation.nonce_link
            },
            success: function(response) {
                if (response.success) {
                    // Success - reload page to show updated status
                    alert(cirruslySettingsApiAutomation.i18n.link_success);
                    location.reload();
                } else {
                    // Error - show error message
                    const errorMsg = response.data && response.data.message ? response.data.message : cirruslySettingsApiAutomation.i18n.unknown_error;
                    alert(cirruslySettingsApiAutomation.i18n.link_failed + ' ' + errorMsg);
                    button.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                // Network/server error
                alert(cirruslySettingsApiAutomation.i18n.network_error);
                button.prop('disabled', false).html(originalHtml);
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
        const originalHtml = button.html();
        button.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-top: 3px; animation: rotation 1s infinite linear;"></span> ' + cirruslySettingsApiAutomation.i18n.generating);
        statusContainer.html('<span class="spinner is-active" style="float: none; margin: 0;"></span>');

        // Make AJAX request
        $.ajax({
            url: cirruslySettingsApiAutomation.ajax_url,
            type: 'POST',
            data: {
                action: 'cirrusly_generate_api_key',
                nonce: cirruslySettingsApiAutomation.nonce_generate
            },
            success: function(response) {
                if (response.success) {
                    // Success - reload page to show new key
                    statusContainer.html('<span style="color: #008a20; font-weight: 600;">✓ ' + cirruslySettingsApiAutomation.i18n.generate_success + '</span>');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    // Error - show error message
                    const errorMsg = response.data && response.data.message ? response.data.message : cirruslySettingsApiAutomation.i18n.unknown_error;
                    statusContainer.html('<span style="color: #d63638; font-weight: 600;">✗ ' + errorMsg + '</span>');
                    button.prop('disabled', false).html(originalHtml);
                }
            },
            error: function(xhr, status, error) {
                // Network/server error
                statusContainer.html('<span style="color: #d63638; font-weight: 600;">✗ ' + cirruslySettingsApiAutomation.i18n.network_error + '</span>');
                button.prop('disabled', false).html(originalHtml);
                console.error('Generation AJAX error:', error);
            }
        });
    });

    // Add CSS for rotation animation
    $('<style>')
        .prop('type', 'text/css')
        .html('@keyframes rotation { from { transform: rotate(0deg); } to { transform: rotate(359deg); } }')
        .appendTo('head');

})(jQuery);
