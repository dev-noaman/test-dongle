<?php
/**
 * Logs View
 *
 * Filterable log viewer with CSV export and auto-refresh toggle.
 */

$module = \FreePBX::Donglemanager();
$dongles = $module->getAllDongles();
?>
<div class="dm-container">
    <div class="dm-page-header">
        <h2><i class="fa fa-list-alt"></i> System Logs</h2>
    </div>

    <!-- Filter Bar -->
    <div class="dm-filter-bar">
        <div class="dm-filter-group">
            <label>Level</label>
            <select id="filter-level">
                <option value="all">All Levels</option>
                <option value="info">Info</option>
                <option value="warning">Warning</option>
                <option value="error">Error</option>
            </select>
        </div>
        <div class="dm-filter-group">
            <label>Category</label>
            <select id="filter-category">
                <option value="all">All Categories</option>
                <option value="sms">SMS</option>
                <option value="ussd">USSD</option>
                <option value="dongle">Dongle</option>
                <option value="system">System</option>
                <option value="worker">Worker</option>
            </select>
        </div>
        <div class="dm-filter-group">
            <label>Dongle</label>
            <select id="filter-dongle">
                <option value="all">All Dongles</option>
                <?php foreach ($dongles as $d): ?>
                    <option value="<?php echo htmlspecialchars($d['device']); ?>">
                        <?php echo htmlspecialchars($d['device']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="dm-filter-group">
            <label>From Date</label>
            <input type="date" id="filter-date-from">
        </div>
        <div class="dm-filter-group">
            <label>To Date</label>
            <input type="date" id="filter-date-to">
        </div>
        <div class="dm-filter-group">
            <label>Search</label>
            <input type="text" id="filter-search" placeholder="Search message...">
        </div>
        <div class="dm-filter-group">
            <button type="button" id="btn-apply" class="dm-btn dm-btn-primary">
                <i class="fa fa-filter"></i> Apply
            </button>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="dm-btn-group" style="margin-bottom: 15px;">
        <button type="button" id="btn-auto-refresh" class="dm-btn dm-btn-outline dm-btn-sm">
            <i class="fa fa-sync-alt"></i> Auto-Refresh: Off
        </button>
        <button type="button" id="btn-export" class="dm-btn dm-btn-outline dm-btn-sm">
            <i class="fa fa-download"></i> Export CSV
        </button>
    </div>

    <!-- Logs Table -->
    <table class="dm-table" id="logs-table">
        <thead>
            <tr>
                <th style="width: 140px;">Time</th>
                <th style="width: 80px;">Level</th>
                <th style="width: 80px;">Category</th>
                <th style="width: 80px;">Dongle</th>
                <th>Message</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="5" class="text-center text-muted">
                    <div class="dm-loading"><div class="dm-spinner"></div></div>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Pagination -->
    <div id="pagination-container"></div>
</div>

<script>
(function($) {
    'use strict';

    var currentPage = 1;
    var currentFilters = {};
    var autoRefreshId = null;
    var autoRefreshEnabled = false;

    function renderLogRows(response) {
        var html = '';
        if (response.data.rows.length === 0) {
            html = '<tr><td colspan="5" class="text-center text-muted">No logs found</td></tr>';
        } else {
            response.data.rows.forEach(function(row) {
                html += '<tr>';
                html += '<td>' + DM.formatDate(row.created_at) + '</td>';
                html += '<td><span class="dm-badge ' + row.level + '">' + DM.escapeHtml(row.level) + '</span></td>';
                html += '<td>' + DM.escapeHtml(row.category) + '</td>';
                html += '<td>' + DM.escapeHtml(row.dongle || '-') + '</td>';
                html += '<td>' + DM.escapeHtml(row.message) + '</td>';
                html += '</tr>';
            });
        }

        $('#logs-table tbody').html(html);

        $('#pagination-container').html(DM.buildPagination(
            response.data.total,
            response.data.page,
            response.data.per_page,
            loadLogs
        ));
    }

    function loadLogs(page) {
        page = page || 1;
        currentPage = page;

        var params = $.extend({
            page: page,
            per_page: 50
        }, currentFilters);

        DM.ajax('log_list', params, function(response) {
            if (!response.success) return;
            renderLogRows(response);
        });
    }

    // Apply filters
    $('#btn-apply').on('click', function() {
        currentFilters = {
            level: $('#filter-level').val(),
            category: $('#filter-category').val(),
            dongle: $('#filter-dongle').val(),
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val(),
            search: $('#filter-search').val()
        };
        loadLogs(1);
    });

    // Auto-refresh toggle
    $('#btn-auto-refresh').on('click', function() {
        autoRefreshEnabled = !autoRefreshEnabled;

        if (autoRefreshEnabled) {
            $(this).removeClass('dm-btn-outline').addClass('dm-btn-primary');
            $(this).html('<i class="fa fa-sync-alt fa-spin"></i> Auto-Refresh: On');
            autoRefreshId = DM.startAutoRefresh('log_list', function(response) {
                if (response.success) {
                    renderLogRows(response);
                }
            }, 10000, function() {
                var params = $.extend({
                    page: currentPage,
                    per_page: 50
                }, currentFilters);
                return params;
            });
        } else {
            $(this).removeClass('dm-btn-primary').addClass('dm-btn-outline');
            $(this).html('<i class="fa fa-sync-alt"></i> Auto-Refresh: Off');
            if (autoRefreshId) {
                DM.stopAutoRefresh(autoRefreshId);
                autoRefreshId = null;
            }
        }
    });

    // Export CSV
    $('#btn-export').on('click', function() {
        var params = $.extend({
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val(),
            level: $('#filter-level').val(),
            category: $('#filter-category').val(),
            dongle: $('#filter-dongle').val(),
            search: $('#filter-search').val()
        }, currentFilters);

        var queryString = $.param(params);
        window.location.href = 'ajax.php?module=donglemanager&command=log_export&' + queryString;
    });

    // Load on page ready
    $(document).ready(function() {
        loadLogs();
    });

})(jQuery);
</script>
