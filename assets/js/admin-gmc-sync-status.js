/**
 * Cirrusly Commerce - GMC Sync Status Dashboard
 * Handles real-time refresh of sync queue and quota status
 */
(function($) {
    'use strict';

    const AUTO_REFRESH_INTERVAL_MS = 30000; // 30 seconds
    let refreshInterval = null;
    let isRefreshing = false;

    /**
     * Initialize sync status dashboard
     */
    function init() {
        // Manual refresh button
        $('#cirrusly-refresh-sync-status').on('click', function(e) {
            e.preventDefault();
            refreshSyncStatus();
        });

        // Start auto-refresh if queue is active
        const queueSize = parseInt($('#cirrusly-queue-size').data('value'), 10) || 0;
        if (queueSize > 0) {
            startAutoRefresh();
        }
    }

    /**
     * Refresh sync status via AJAX
     */
    function refreshSyncStatus() {
        if (isRefreshing) {
            return;
        }

        isRefreshing = true;
        const $button = $('#cirrusly-refresh-sync-status');
        const $icon = $button.find('.dashicons');

        // Add spinning animation
        $button.prop('disabled', true);
        $icon.addClass('cirrusly-spinning');

        $.ajax({
            url: cirruslySyncStatus.ajax_url,
            type: 'POST',
            data: {
                action: 'cirrusly_get_sync_status',
                _nonce: cirruslySyncStatus.refresh_nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    updateDashboard(response.data);
                    // Hide any previous error messages
                    $('#cirrusly-sync-status-error').hide();

                    // Start/stop auto-refresh based on queue size
                    if (response.data.queue_size > 0) {
                        startAutoRefresh();
                    } else {
                        stopAutoRefresh();
                    }
                } else {
                    console.error('Sync status refresh failed:', response.data);
                    // Show user-friendly error message
                    const errorMsg = response.data && response.data.message 
                        ? response.data.message 
                        : 'Failed to refresh sync status. Please try again.';
                    $('#cirrusly-sync-status-error').text(errorMsg).show();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX error:', error);
                $('#cirrusly-sync-status-error')
                    .text('Network error: Unable to refresh sync status.')
                    .show();
            },
            complete: function() {
                isRefreshing = false;
                $button.prop('disabled', false);
                $icon.removeClass('cirrusly-spinning');
            }
        });
    }

    /**
     * Update dashboard with new data
     */
    function updateDashboard(data) {
        // Update KPI cards with both data attribute and formatted text
        $('#cirrusly-queue-size')
            .data('value', data.queue_size)
            .text(formatNumber(data.queue_size));
        $('#cirrusly-quota-used').text(formatNumber(data.quota_used));
        $('#cirrusly-quota-limit').text('of ' + formatNumber(data.quota_limit) + ' calls used today');
        $('#cirrusly-success-rate').text(formatNumber(data.success_rate) + '%');

        // Update last sync time
        if (data.last_sync_time) {
            const lastSyncText = formatTimeDiff(data.last_sync_time);
            $('#cirrusly-last-sync').text(lastSyncText);
        } else {
            $('#cirrusly-last-sync').text('Never');
        }

        // Update next sync time
        if (data.next_sync_due) {
            const now = Math.floor(Date.now() / 1000);
            const timeUntil = data.next_sync_due - now;

            if (timeUntil > 0) {
                const nextSyncText = formatTimeDiff(data.next_sync_due, now);
                $('#cirrusly-next-sync').text('Next: ' + nextSyncText);
            } else {
                $('#cirrusly-next-sync').text('Next: Processing now...');
            }
        } else {
            $('#cirrusly-next-sync').text('Next: Not scheduled');
        }

        // Update status indicator
        const $indicator = $('#cirrusly-sync-status-indicator');
        if (data.queue_size > 0) {
            $indicator.text('SYNCING')
                .attr('data-status', 'syncing');
        } else {
            $indicator.text('IDLE')
                .attr('data-status', 'idle');
        }

        // Update queue table
        updateQueueTable(data.queue_items);
    }

    /**
     * Update the sync queue table
     */
    function updateQueueTable(items) {
        const $container = $('#cirrusly-sync-queue-container');

        if (!items || items.length === 0) {
            $container.html(`
                <div style="text-align: center; padding: 40px; color: #646970;">
                    <span class="dashicons dashicons-yes-alt" style="font-size: 48px; color: #00a32a; margin-bottom: 10px;"></span>
                    <p style="margin: 0; font-size: 14px;"><strong>All products synced!</strong></p>
                    <p style="margin: 5px 0 0 0; font-size: 12px;">No items in queue.</p>
                </div>
            `);
            return;
        }

        let tableHTML = `
            <table class="wp-list-table widefat fixed striped" style="border: none;">
                <thead>
                    <tr>
                        <th style="width: 50px;">Image</th>
                        <th>Product</th>
                        <th style="width: 120px;">SKU</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 80px;">Attempts</th>
                    </tr>
                </thead>
                <tbody>
        `;

        items.forEach(function(item) {
            const statusColor = item.status === 'failed' ? '#d63638' : '#f0ad4e';
            const statusBg = item.status === 'failed' ? '#ffe6e6' : '#fff4e6';
            const statusLabel = item.status === 'failed' ? 'FAILED' : 'PENDING';
            const imageHTML = item.image_url
                ? `<img src="${escapeHtml(item.image_url)}" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">`
                : `<span class="dashicons dashicons-format-image" style="font-size: 40px; color: #ddd;"></span>`;

            tableHTML += `
                <tr>
                    <td>${imageHTML}</td>
                    <td>
                        <strong>${escapeHtml(item.name)}</strong><br>
                        <small style="color: #646970;">ID: ${escapeHtml(String(item.id))}</small>
                    </td>
                    <td><code>${escapeHtml(item.sku)}</code></td>
                    <td>
                        <span style="padding: 4px 10px; background: ${statusBg}; border: 1px solid ${statusColor}; border-radius: 12px; font-size: 11px; font-weight: 600; color: ${statusColor};">
                            ${statusLabel}
                        </span>
                    </td>
                    <td style="text-align: center;">
                        <span style="color: ${item.attempts >= 2 ? '#d63638' : '#646970'}; font-weight: 600;">
                            ${escapeHtml(String(item.attempts))}/3
                        </span>
                    </td>
                </tr>
            `;
        });

        tableHTML += '</tbody></table>';
        $container.html(tableHTML);
    }

    /**
     * Start auto-refresh interval (every 30 seconds)
     */
    function startAutoRefresh() {
        if (refreshInterval) {
            return; // Already running
        }

        refreshInterval = setInterval(function() {
            refreshSyncStatus();
        }, AUTO_REFRESH_INTERVAL_MS);
    }

    /**
     * Stop auto-refresh interval
     */
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }

    /**
     * Format number with thousands separator
     */
    function formatNumber(num) {
        return Number(num).toLocaleString();
    }

    /**
     * Format time difference in human-readable format
     * Handles both past ("X ago") and future ("in X") times
     */
    function formatTimeDiff(timestamp1, timestamp2) {
        const now = timestamp2 || Math.floor(Date.now() / 1000);
        const diff = Math.abs(now - timestamp1);
        const isFuture = timestamp1 > now;
        const prefix = isFuture ? 'in ' : '';
        const suffix = isFuture ? '' : ' ago';

        if (diff < 60) {
            return prefix + diff + ' second' + (diff !== 1 ? 's' : '') + suffix;
        } else if (diff < 3600) {
            const minutes = Math.floor(diff / 60);
            return prefix + minutes + ' minute' + (minutes !== 1 ? 's' : '') + suffix;
        } else if (diff < 86400) {
            const hours = Math.floor(diff / 3600);
            return prefix + hours + ' hour' + (hours !== 1 ? 's' : '') + suffix;
        } else {
            const days = Math.floor(diff / 86400);
            return prefix + days + ' day' + (days !== 1 ? 's' : '') + suffix;
        }
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // Initialize on document ready
    $(document).ready(function() {
        init();
    });

    // Clean up on page unload
    $(window).on('beforeunload', function() {
        stopAutoRefresh();
    });

})(jQuery);
