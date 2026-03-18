/**
 * Dongle Manager Module JavaScript
 *
 * Provides AJAX communication, UI helpers, and auto-refresh functionality.
 */

// Create namespace
var DM = DM || {};

(function($) {
    'use strict';

    // ============================================
    // Configuration
    // ============================================
    DM.config = {
        autoRefreshIntervals: {},
        toastContainer: null
    };

    // ============================================
    // AJAX Wrapper
    // ============================================

    /**
     * Make an AJAX request to the module
     *
     * @param {string} command - The AJAX command name
     * @param {object} data - Data to send
     * @param {function} callback - Success callback
     * @param {function} errorCallback - Error callback
     */
    DM.ajax = function(command, data, callback, errorCallback) {
        var isPost = data && data._method === 'POST';
        var requestData = $.extend({ module: 'donglemanager', command: command }, data);
        delete requestData._method;

        $.ajax({
            url: 'ajax.php',
            type: isPost ? 'POST' : 'GET',
            data: requestData,
            dataType: 'json',
            timeout: 30000
        })
        .done(function(response) {
            if (typeof callback === 'function') {
                callback(response);
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX Error:', command, status, error);
            if (typeof errorCallback === 'function') {
                errorCallback(xhr, status, error);
            } else {
                DM.toast('Request failed: ' + (error || status), 'error');
            }
        });
    };

    /**
     * POST wrapper — includes CSRF token automatically
     */
    DM.post = function(command, data, callback, errorCallback) {
        data = data || {};
        data._method = 'POST';
        // Include CSRF token for write operations
        if (typeof DM_CSRF_TOKEN !== 'undefined') {
            data.csrf_token = DM_CSRF_TOKEN;
        }
        DM.ajax(command, data, callback, errorCallback);
    };

    // ============================================
    // Toast Notifications
    // ============================================

    /**
     * Show a toast notification
     *
     * @param {string} message - Message to display
     * @param {string} type - Type: success, error, warning, info
     * @param {number} duration - Duration in ms (default: 4000)
     */
    DM.toast = function(message, type, duration) {
        type = type || 'info';
        duration = duration || 4000;

        // Create container if needed
        if (!DM.config.toastContainer) {
            DM.config.toastContainer = $('<div class="dm-toast-container"></div>').appendTo('body');
        }

        // Build toast element
        var icon = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };

        var toast = $(
            '<div class="dm-toast ' + type + '">' +
            '<i class="fa ' + (icon[type] || icon.info) + '"></i>' +
            '<span>' + DM.escapeHtml(message) + '</span>' +
            '</div>'
        );

        DM.config.toastContainer.append(toast);

        // Auto-remove after duration
        setTimeout(function() {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, duration);
    };

    // ============================================
    // Auto-Refresh
    // ============================================

    /**
     * Start auto-refresh for a command
     *
     * @param {string} command - AJAX command to call
     * @param {function} callback - Callback for response
     * @param {number} intervalMs - Refresh interval in milliseconds
     * @param {function} paramsFn - Optional function that returns params object
     * @returns {number} Interval ID for stopping
     */
    DM.startAutoRefresh = function(command, callback, intervalMs, paramsFn) {
        intervalMs = intervalMs || 10000;

        var id = setInterval(function() {
            var params = typeof paramsFn === 'function' ? paramsFn() : {};
            DM.ajax(command, params, callback);
        }, intervalMs);

        DM.config.autoRefreshIntervals[id] = {
            command: command,
            callback: callback,
            interval: intervalMs,
            paramsFn: paramsFn
        };

        return id;
    };

    /**
     * Stop auto-refresh
     *
     * @param {number} id - Interval ID from startAutoRefresh
     */
    DM.stopAutoRefresh = function(id) {
        if (id && DM.config.autoRefreshIntervals[id]) {
            clearInterval(id);
            delete DM.config.autoRefreshIntervals[id];
        }
    };

    /**
     * Stop all auto-refresh intervals
     */
    DM.stopAllAutoRefresh = function() {
        for (var id in DM.config.autoRefreshIntervals) {
            clearInterval(parseInt(id));
        }
        DM.config.autoRefreshIntervals = {};
    };

    // ============================================
    // Utility Functions
    // ============================================

    /**
     * Format a datetime string
     *
     * @param {string} datetime - MySQL datetime format
     * @returns {string} Formatted date/time
     */
    DM.formatDate = function(datetime) {
        if (!datetime) return '-';

        var date = new Date(datetime);
        if (isNaN(date.getTime())) return datetime;

        var now = new Date();
        var diff = now - date;

        // Less than 24 hours ago - show time only
        if (diff < 86400000) {
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        // Less than 7 days ago - show day and time
        if (diff < 604800000) {
            return date.toLocaleDateString([], { weekday: 'short' }) + ' ' +
                   date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        // Otherwise show full date
        return date.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' });
    };

    /**
     * Build pagination HTML
     *
     * @param {number} total - Total items
     * @param {number} page - Current page (1-indexed)
     * @param {number} perPage - Items per page
     * @param {function} callback - Callback for page change, receives page number
     * @returns {string} HTML string
     */
    DM.buildPagination = function(total, page, perPage, callback) {
        var pages = Math.ceil(total / perPage);
        if (pages <= 1) return '';

        var html = '<div class="dm-pagination">';
        var maxVisible = 5;
        var start = Math.max(1, page - Math.floor(maxVisible / 2));
        var end = Math.min(pages, start + maxVisible - 1);

        // Adjust start if we're near the end
        if (end - start < maxVisible - 1) {
            start = Math.max(1, end - maxVisible + 1);
        }

        // Previous
        if (page > 1) {
            html += '<a href="#" data-page="' + (page - 1) + '">&laquo;</a>';
        } else {
            html += '<span class="disabled">&laquo;</span>';
        }

        // First page + ellipsis
        if (start > 1) {
            html += '<a href="#" data-page="1">1</a>';
            if (start > 2) html += '<span class="disabled">...</span>';
        }

        // Page numbers
        for (var i = start; i <= end; i++) {
            if (i === page) {
                html += '<span class="active">' + i + '</span>';
            } else {
                html += '<a href="#" data-page="' + i + '">' + i + '</a>';
            }
        }

        // Last page + ellipsis
        if (end < pages) {
            if (end < pages - 1) html += '<span class="disabled">...</span>';
            html += '<a href="#" data-page="' + pages + '">' + pages + '</a>';
        }

        // Next
        if (page < pages) {
            html += '<a href="#" data-page="' + (page + 1) + '">&raquo;</a>';
        } else {
            html += '<span class="disabled">&raquo;</span>';
        }

        html += '</div>';

        // Bind click events via delegation if callback provided
        if (typeof callback === 'function') {
            $(document).off('click.dmPagination').on('click.dmPagination', '.dm-pagination a[data-page]', function(e) {
                e.preventDefault();
                callback(parseInt($(this).data('page')));
            });
        }

        return html;
    };

    /**
     * Build a dongle selector dropdown
     *
     * @param {array} dongles - Array of dongle objects
     * @param {object} options - Options: name, id, selected, showAll, onlyActive
     * @returns {string} HTML string
     */
    DM.buildDongleSelector = function(dongles, options) {
        options = $.extend({
            name: 'dongle',
            id: 'dongle-select',
            selected: '',
            showAll: true,
            onlyActive: false,
            class: 'form-control'
        }, options);

        var html = '<select name="' + options.name + '" id="' + options.id + '" class="' + options.class + '">';

        if (options.showAll) {
            html += '<option value="all">All Dongles</option>';
        }

        dongles.forEach(function(dongle) {
            // Filter by active status if requested
            if (options.onlyActive && dongle.state !== 'Free' && dongle.state !== 'Busy') {
                return;
            }

            var selected = (options.selected === dongle.device) ? ' selected' : '';
            var label = dongle.device;

            if (dongle.phone_number) {
                label += ' — ' + dongle.phone_number;
            }
            if (dongle.operator) {
                label += ' (' + dongle.operator + ')';
            }

            html += '<option value="' + DM.escapeHtml(dongle.device) + '"' + selected + '>';
            html += DM.escapeHtml(label);
            html += '</option>';
        });

        html += '</select>';
        return html;
    };

    /**
     * Get selected checkbox IDs
     *
     * @param {string} selector - jQuery selector for checkboxes (default: '.msg-checkbox:checked')
     * @returns {array} Array of integer IDs
     */
    DM.getSelectedIds = function(selector) {
        selector = selector || '.msg-checkbox:checked';
        var ids = [];
        $(selector).each(function() {
            ids.push(parseInt($(this).val()));
        });
        return ids;
    };

    /**
     * Escape HTML to prevent XSS
     *
     * @param {string} str - String to escape
     * @returns {string} Escaped string
     */
    DM.escapeHtml = function(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    /**
     * Truncate text with ellipsis
     *
     * @param {string} text - Text to truncate
     * @param {number} maxLength - Maximum length
     * @returns {string} Truncated text
     */
    DM.truncate = function(text, maxLength) {
        if (!text || text.length <= maxLength) return text || '';
        return text.substring(0, maxLength) + '...';
    };

    // ============================================
    // SMS Character Counter
    // ============================================

    /**
     * Initialize SMS character counter
     *
     * @param {string} textareaId - Textarea element ID
     * @param {string} counterId - Counter display element ID
     */
    DM.smsCounter = function(textareaId, counterId) {
        var $textarea = $('#' + textareaId);
        var $counter = $('#' + counterId);

        function updateCounter() {
            var text = $textarea.val();
            var isGsm7 = DM.isGsm7(text);
            var charLen = text.length;
            var maxChars = isGsm7 ? 160 : 70;
            var multiChars = isGsm7 ? 153 : 67;
            var parts = charLen === 0 ? 0 : (charLen <= maxChars ? 1 : Math.ceil(charLen / multiChars));
            var remaining = parts === 0 ? maxChars : (parts === 1 ? maxChars - charLen : (multiChars * parts) - charLen);

            var displayClass = '';
            if (remaining < 20) displayClass = 'warning';
            if (remaining < 0) displayClass = 'danger';

            $counter
                .removeClass('warning danger')
                .addClass(displayClass)
                .text(charLen + ' / ' + (parts === 0 ? maxChars : multiChars * parts) +
                      ' characters (' + parts + ' SMS parts, ' + (isGsm7 ? 'GSM-7' : 'UCS-2') + ')');
        }

        $textarea.on('input', updateCounter);
        updateCounter();
    };

    /**
     * Check if text is GSM-7 compatible
     */
    DM.isGsm7 = function(text) {
        // GSM-7 basic character set (simplified check)
        var gsm7Regex = /^[@£$¥èéùìòÇ\fØø\nÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&'()*+,\-./0-9:;<=>?¡¿A-ZÄÖÑÜ§àäöñüà^{}\\[~\]|€]*$/;
        return gsm7Regex.test(text);
    };

    // ============================================
    // Dashboard Functions
    // ============================================

    DM.dashboardChart = null;

    /**
     * Initialize dashboard chart
     */
    DM.initDashboardChart = function(canvasId, chartData) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return;

        // If chart already exists on same canvas, update data instead of destroying
        if (DM.dashboardChart && DM.dashboardChart.canvas === ctx) {
            DM.dashboardChart.data.labels = chartData.labels || [];
            DM.dashboardChart.data.datasets[0].data = chartData.sent || [];
            DM.dashboardChart.data.datasets[1].data = chartData.received || [];
            DM.dashboardChart.update('none'); // 'none' = no animation on data update
            return;
        }

        // Destroy stale chart if canvas changed (e.g., AJAX navigation)
        if (DM.dashboardChart) {
            DM.dashboardChart.destroy();
        }

        DM.dashboardChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: chartData.labels || [],
                datasets: [
                    {
                        label: 'Sent',
                        data: chartData.sent || [],
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        fill: true,
                        tension: 0.3
                    },
                    {
                        label: 'Received',
                        data: chartData.received || [],
                        borderColor: '#2ec4b6',
                        backgroundColor: 'rgba(46, 196, 182, 0.1)',
                        fill: true,
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    };

    /**
     * Update dashboard with new data
     */
    DM.updateDashboard = function(response) {
        if (!response.success) {
            // Show AMI warning if the error message mentions AMI
            if (response.message && response.message.indexOf('AMI') !== -1) {
                $('#dm-ami-warning').show();
            }
            DM.toast('Failed to load dashboard data', 'error');
            return;
        }
        // Hide AMI warning on success
        $('#dm-ami-warning').hide();

        var data = response.data;

        // Update summary cards
        $('#dm-total-dongles').text(data.dongles_total || 0);
        $('#dm-received-today').text(data.sms_received_today || 0);
        $('#dm-sent-today').text(data.sms_sent_today || 0);
        $('#dm-failed-today').text(data.sms_failed_today || 0);

        // Update chart
        if (data.chart_7day) {
            DM.initDashboardChart('chart7day', data.chart_7day);
        }

        // Update dongle status list
        DM.renderDongleStatusList(data.dongles || []);

        // Update recent inbox
        DM.renderRecentInbox(data.recent_inbox || []);

        // Update recent USSD
        DM.renderRecentUssd(data.recent_ussd || []);
    };

    /**
     * Render dongle status list for dashboard
     */
    DM.renderDongleStatusList = function(dongles) {
        var html = '';

        if (dongles.length === 0) {
            html = '<p class="text-muted">No dongles detected</p>';
        } else {
            dongles.forEach(function(d) {
                var signalClass = d.signal_percent > 60 ? 'strong' : (d.signal_percent > 30 ? 'medium' : 'weak');
                var stateClass = d.state ? d.state.toLowerCase() : 'offline';

                html += '<div class="dm-dongle-mini-card" style="margin-bottom:10px;padding:10px;background:#f8f9fa;border-radius:8px;">';
                html += '<div style="display:flex;justify-content:space-between;align-items:center;">';
                html += '<strong>' + DM.escapeHtml(d.device) + '</strong>';
                html += '<span class="dm-badge ' + stateClass + '">' + DM.escapeHtml(d.state || 'Unknown') + '</span>';
                html += '</div>';
                html += '<div class="dm-signal-bar"><div class="dm-signal-bar-fill ' + signalClass + '" style="width:' + (d.signal_percent || 0) + '%"></div></div>';
                html += '<small>' + DM.escapeHtml(d.operator || '-') + ' - ' + (d.signal_percent || 0) + '%</small>';
                html += '</div>';
            });
        }

        $('#dm-dongle-status-list').html(html);
    };

    /**
     * Render recent inbox for dashboard
     */
    DM.renderRecentInbox = function(messages) {
        var html = '';

        if (messages.length === 0) {
            html = '<tr><td colspan="4" class="text-center text-muted">No messages</td></tr>';
        } else {
            messages.forEach(function(m) {
                html += '<tr>';
                html += '<td>' + DM.formatDate(m.received_at) + '</td>';
                html += '<td>' + DM.escapeHtml(m.sender) + '</td>';
                html += '<td>' + DM.escapeHtml(DM.truncate(m.message, 40)) + '</td>';
                html += '<td>' + DM.escapeHtml(m.dongle) + '</td>';
                html += '</tr>';
            });
        }

        $('#dm-recent-inbox tbody').html(html);
    };

    /**
     * Render recent USSD for dashboard
     */
    DM.renderRecentUssd = function(entries) {
        var html = '';

        if (entries.length === 0) {
            html = '<tr><td colspan="4" class="text-center text-muted">No USSD activity</td></tr>';
        } else {
            entries.forEach(function(u) {
                html += '<tr>';
                html += '<td>' + DM.formatDate(u.created_at) + '</td>';
                html += '<td>' + DM.escapeHtml(u.dongle) + '</td>';
                html += '<td>' + DM.escapeHtml(u.command) + '</td>';
                html += '<td>' + DM.escapeHtml(DM.truncate(u.response, 30)) + '</td>';
                html += '</tr>';
            });
        }

        $('#dm-recent-ussd tbody').html(html);
    };

    // ============================================
    // Reports Charts
    // ============================================

    DM.reportsDailyChart = null;
    DM.reportsDongleChart = null;

    /**
     * Render daily bar chart for reports
     */
    DM.renderDailyChart = function(canvasId, data) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return;

        if (DM.reportsDailyChart) {
            DM.reportsDailyChart.destroy();
        }

        DM.reportsDailyChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [
                    {
                        label: 'Sent',
                        data: data.sent || [],
                        backgroundColor: '#4361ee'
                    },
                    {
                        label: 'Received',
                        data: data.received || [],
                        backgroundColor: '#2ec4b6'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    };

    /**
     * Render per-dongle doughnut chart
     */
    DM.renderDongleChart = function(canvasId, data) {
        var ctx = document.getElementById(canvasId);
        if (!ctx) return;

        if (DM.reportsDongleChart) {
            DM.reportsDongleChart.destroy();
        }

        var colors = ['#4361ee', '#2ec4b6', '#ff9f1c', '#e63946', '#4895ef', '#7209b7'];

        DM.reportsDongleChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.map(function(d) { return d.label || d.device; }),
                datasets: [{
                    data: data.map(function(d) { return d.total || 0; }),
                    backgroundColor: colors.slice(0, data.length)
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    };

    /**
     * Make table columns sortable
     */
    DM.sortableTable = function(tableId) {
        var $table = $('#' + tableId);
        var $headers = $table.find('th.sortable');

        $headers.on('click', function() {
            var $th = $(this);
            var column = $th.index();
            var isAsc = $th.hasClass('asc');

            // Reset all headers
            $headers.removeClass('asc desc');

            // Toggle sort direction
            if (isAsc) {
                $th.addClass('desc');
            } else {
                $th.addClass('asc');
            }

            // Sort rows
            var $tbody = $table.find('tbody');
            var $rows = $tbody.find('tr').not('.totals-row').get();

            $rows.sort(function(a, b) {
                var aVal = $(a).find('td').eq(column).text().trim();
                var bVal = $(b).find('td').eq(column).text().trim();

                // Try numeric comparison
                var aNum = parseFloat(aVal);
                var bNum = parseFloat(bVal);

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAsc ? bNum - aNum : aNum - bNum;
                }

                // String comparison
                return isAsc ? bVal.localeCompare(aVal) : aVal.localeCompare(bVal);
            });

            // Re-append sorted rows (keeping totals row at bottom)
            var $totalsRow = $tbody.find('tr.totals-row');
            $.each($rows, function(index, row) {
                $tbody.append(row);
            });
            if ($totalsRow.length) {
                $tbody.append($totalsRow);
            }
        });
    };

    // ============================================
    // Dongle List Cache
    // ============================================

    DM.dongleCache = null;

    /**
     * Fetch and cache dongle list. Returns cached data on subsequent calls.
     *
     * @param {function} callback - Receives array of dongle objects
     */
    DM.fetchDongles = function(callback) {
        if (DM.dongleCache !== null) {
            if (typeof callback === 'function') callback(DM.dongleCache);
            return;
        }

        DM.ajax('dongle_list', {}, function(response) {
            if (response.success) {
                DM.dongleCache = response.data;
            } else {
                DM.dongleCache = [];
            }
            if (typeof callback === 'function') callback(DM.dongleCache);
        });
    };

    /**
     * Invalidate dongle cache (call after dongle start/stop/restart)
     */
    DM.invalidateDongleCache = function() {
        DM.dongleCache = null;
    };

    /**
     * Populate all dongle filter <select> elements on the current view.
     * Targets selects with class "dm-dongle-filter" or "dm-dongle-active-filter".
     *
     * @param {array} dongles - Array of dongle objects
     */
    DM.populateDongleSelectors = function(dongles) {
        // Filter selectors (show all dongles, with "All Dongles" option)
        $('.dm-dongle-filter').each(function() {
            var $select = $(this);
            var current = $select.val();
            $select.find('option:not(:first)').remove(); // Keep "All Dongles"
            dongles.forEach(function(d) {
                $select.append('<option value="' + DM.escapeHtml(d.device) + '">' +
                    DM.escapeHtml(d.device) + '</option>');
            });
            if (current) $select.val(current);
        });

        // Active-only selectors (for send forms — only Free/Busy dongles)
        $('.dm-dongle-active-filter').each(function() {
            var $select = $(this);
            var current = $select.val();
            $select.find('option:not(:first)').remove();
            dongles.forEach(function(d) {
                if (d.state !== 'Free' && d.state !== 'Busy') return;
                var label = d.device;
                if (d.phone_number) label += ' \u2014 ' + d.phone_number;
                if (d.operator) label += ' (' + d.operator + ')';
                $select.append('<option value="' + DM.escapeHtml(d.device) + '">' +
                    DM.escapeHtml(label) + '</option>');
            });
            if (current) $select.val(current);
        });
    };

    // ============================================
    // Initialize on DOM Ready
    // ============================================

    $(document).ready(function() {
        // Initialize any page-specific functionality based on current view
        var view = DM.getCurrentView ? DM.getCurrentView() : 'dashboard';

        // Common initialization
        if (typeof DM.initPage === 'function') {
            DM.initPage(view);
        }
    });

})(jQuery);
