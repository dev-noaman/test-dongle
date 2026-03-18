<?php
/**
 * SMS Outbox View
 *
 * Paginated outbox with status filters, retry, delete, and row expansion.
 */
?>
<div class="dm-container">
    <div class="dm-page-header">
        <h2><i class="fa fa-paper-plane"></i> SMS Outbox</h2>
    </div>

    <!-- Filter Bar -->
    <div class="dm-filter-bar">
        <div class="dm-filter-group">
            <label>Dongle</label>
            <select id="filter-dongle" class="dm-dongle-filter">
                <option value="all">All Dongles</option>
            </select>
        </div>
        <div class="dm-filter-group">
            <label>Status</label>
            <select id="filter-status">
                <option value="all">All Statuses</option>
                <option value="queued">Queued</option>
                <option value="sending">Sending</option>
                <option value="sent">Sent</option>
                <option value="failed">Failed</option>
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
            <input type="text" id="filter-search" placeholder="Search destination or message...">
        </div>
        <div class="dm-filter-group">
            <button type="button" id="filter-apply" class="dm-btn dm-btn-primary">
                <i class="fa fa-filter"></i> Apply
            </button>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="dm-btn-group" style="margin-bottom: 15px;">
        <button type="button" id="btn-retry" class="dm-btn dm-btn-warning dm-btn-sm">
            <i class="fa fa-redo"></i> Retry Failed
        </button>
        <button type="button" id="btn-delete" class="dm-btn dm-btn-danger dm-btn-sm">
            <i class="fa fa-trash"></i> Delete Selected
        </button>
    </div>

    <!-- Messages Table -->
    <table class="dm-table" id="outbox-table">
        <thead>
            <tr>
                <th style="width: 30px;"><input type="checkbox" id="select-all"></th>
                <th style="width: 140px;">Time</th>
                <th style="width: 120px;">Destination</th>
                <th style="width: 80px;">Dongle</th>
                <th>Message</th>
                <th style="width: 80px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td colspan="6" class="text-center text-muted">
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

    function loadOutbox(page) {
        page = page || 1;
        currentPage = page;

        var params = $.extend({
            page: page,
            per_page: 25
        }, currentFilters);

        DM.ajax('sms_list_outbox', params, function(response) {
            if (!response.success) return;

            var html = '';
            if (response.data.rows.length === 0) {
                html = '<tr><td colspan="6" class="text-center text-muted">No messages found</td></tr>';
            } else {
                response.data.rows.forEach(function(row) {
                    var statusClass = row.status;
                    html += '<tr data-id="' + row.id + '" data-error="' + DM.escapeHtml(row.error || '') + '">';
                    html += '<td><input type="checkbox" class="msg-checkbox" value="' + row.id + '"></td>';
                    html += '<td>' + DM.formatDate(row.sent_at || row.created_at) + '</td>';
                    html += '<td>' + DM.escapeHtml(row.destination) + '</td>';
                    html += '<td>' + DM.escapeHtml(row.dongle) + '</td>';
                    html += '<td class="dm-row-expand-btn"><span class="msg-preview">' + DM.escapeHtml(DM.truncate(row.message, 50)) + '</span>';
                    html += '<div class="dm-expandable-content" data-full="' + DM.escapeHtml(row.message) + '"></div></td>';
                    html += '<td><span class="dm-badge ' + statusClass + '">' + DM.escapeHtml(row.status) + '</span></td>';
                    html += '</tr>';
                });
            }

            $('#outbox-table tbody').html(html);

            $('#pagination-container').html(DM.buildPagination(
                response.data.total,
                response.data.page,
                response.data.per_page,
                loadOutbox
            ));
        });
    }

    $('#filter-apply').on('click', function() {
        currentFilters = {
            dongle: $('#filter-dongle').val(),
            status: $('#filter-status').val(),
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val(),
            search: $('#filter-search').val()
        };
        loadOutbox(1);
    });

    $('#select-all').on('change', function() {
        $('.msg-checkbox').prop('checked', this.checked);
    });

    $('#btn-retry').on('click', function() {
        var ids = DM.getSelectedIds();
        if (ids.length === 0) {
            DM.toast('Please select messages to retry', 'warning');
            return;
        }
        if (!confirm('Retry ' + ids.length + ' failed message(s)?')) return;

        DM.post('sms_retry', { ids: ids }, function(response) {
            if (response.success) {
                DM.toast('Retrying ' + response.data.retried + ' messages', 'success');
                loadOutbox(currentPage);
            }
        });
    });

    $('#btn-delete').on('click', function() {
        var ids = DM.getSelectedIds();
        if (ids.length === 0) {
            DM.toast('Please select messages to delete', 'warning');
            return;
        }
        if (!confirm('Delete ' + ids.length + ' message(s)?')) return;

        DM.post('sms_delete_outbox', { ids: ids }, function(response) {
            if (response.success) {
                DM.toast('Deleted ' + response.data.deleted + ' messages', 'success');
                loadOutbox(currentPage);
            }
        });
    });

    // Row expansion (shows error for failed messages)
    $(document).on('click', '.dm-row-expand-btn', function() {
        var $row = $(this).closest('tr');
        var $content = $(this).find('.dm-expandable-content');
        var error = $row.data('error');

        if ($content.hasClass('expanded')) {
            $content.removeClass('expanded').html('');
        } else {
            $('.dm-expandable-content.expanded').removeClass('expanded').html('');
            var fullContent = '<pre>' + DM.escapeHtml($content.data('full')) + '</pre>';
            if (error) {
                fullContent += '<div class="alert alert-danger" style="margin-top:10px;"><strong>Error:</strong> ' + DM.escapeHtml(error) + '</div>';
            }
            $content.addClass('expanded').html(fullContent);
        }
    });

    $(document).ready(function() {
        loadOutbox();
    });

})(jQuery);
</script>
