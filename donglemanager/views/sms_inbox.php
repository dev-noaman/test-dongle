<?php
/**
 * SMS Inbox View
 *
 * Paginated, filterable inbox with bulk actions and row expansion.
 */

$module = \FreePBX::Donglemanager();
$dongles = $module->getAllDongles();
?>
<div class="dm-container">
    <div class="dm-page-header">
        <h2><i class="fa fa-inbox"></i> SMS Inbox</h2>
    </div>

    <!-- Filter Bar -->
    <div class="dm-filter-bar">
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
            <input type="text" id="filter-search" placeholder="Search sender or message...">
        </div>
        <div class="dm-filter-group">
            <button type="button" id="filter-apply" class="dm-btn dm-btn-primary">
                <i class="fa fa-filter"></i> Apply
            </button>
        </div>
    </div>

    <!-- Bulk Actions -->
    <div class="dm-btn-group" style="margin-bottom: 15px;">
        <button type="button" id="btn-mark-read" class="dm-btn dm-btn-outline dm-btn-sm">
            <i class="fa fa-check"></i> Mark Read
        </button>
        <button type="button" id="btn-mark-unread" class="dm-btn dm-btn-outline dm-btn-sm">
            <i class="fa fa-envelope"></i> Mark Unread
        </button>
        <button type="button" id="btn-delete" class="dm-btn dm-btn-danger dm-btn-sm">
            <i class="fa fa-trash"></i> Delete Selected
        </button>
    </div>

    <!-- Messages Table -->
    <table class="dm-table" id="inbox-table">
        <thead>
            <tr>
                <th style="width: 30px;"><input type="checkbox" id="select-all"></th>
                <th style="width: 140px;">Time</th>
                <th style="width: 120px;">Sender</th>
                <th style="width: 80px;">Dongle</th>
                <th>Message</th>
                <th style="width: 60px;">Status</th>
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

    // Load inbox messages
    function loadInbox(page) {
        page = page || 1;
        currentPage = page;

        var params = $.extend({
            page: page,
            per_page: 25
        }, currentFilters);

        DM.ajax('sms_list_inbox', params, function(response) {
            if (!response.success) return;

            var html = '';
            if (response.data.rows.length === 0) {
                html = '<tr><td colspan="6" class="text-center text-muted">No messages found</td></tr>';
            } else {
                response.data.rows.forEach(function(row) {
                    var rowClass = row.is_read == 0 ? 'unread' : '';
                    var readBadge = row.is_read == 0 ? '<span class="dm-badge info">New</span>' : '';
                    html += '<tr class="' + rowClass + '" data-id="' + row.id + '">';
                    html += '<td><input type="checkbox" class="msg-checkbox" value="' + row.id + '"></td>';
                    html += '<td>' + DM.formatDate(row.received_at) + '</td>';
                    html += '<td>' + DM.escapeHtml(row.sender) + '</td>';
                    html += '<td>' + DM.escapeHtml(row.dongle) + '</td>';
                    html += '<td class="dm-row-expand-btn"><span class="msg-preview">' + DM.escapeHtml(DM.truncate(row.message, 60)) + '</span>';
                    html += '<div class="dm-expandable-content" data-full="' + DM.escapeHtml(row.message) + '"></div></td>';
                    html += '<td>' + readBadge + '</td>';
                    html += '</tr>';
                });
            }

            $('#inbox-table tbody').html(html);

            // Update pagination
            $('#pagination-container').html(DM.buildPagination(
                response.data.total,
                response.data.page,
                response.data.per_page,
                loadInbox
            ));
        });
    }

    // Apply filters
    $('#filter-apply').on('click', function() {
        currentFilters = {
            dongle: $('#filter-dongle').val(),
            date_from: $('#filter-date-from').val(),
            date_to: $('#filter-date-to').val(),
            search: $('#filter-search').val()
        };
        loadInbox(1);
    });

    // Select all checkbox
    $('#select-all').on('change', function() {
        $('.msg-checkbox').prop('checked', this.checked);
    });

    // Bulk actions
    $('#btn-mark-read').on('click', function() {
        var ids = DM.getSelectedIds();
        if (ids.length === 0) {
            DM.toast('Please select messages', 'warning');
            return;
        }
        DM.post('sms_mark_read', { ids: ids }, function(response) {
            if (response.success) {
                DM.toast('Marked ' + response.data.updated + ' messages as read', 'success');
                loadInbox(currentPage);
            }
        });
    });

    $('#btn-mark-unread').on('click', function() {
        var ids = DM.getSelectedIds();
        if (ids.length === 0) {
            DM.toast('Please select messages', 'warning');
            return;
        }
        DM.post('sms_mark_unread', { ids: ids }, function(response) {
            if (response.success) {
                DM.toast('Marked ' + response.data.updated + ' messages as unread', 'success');
                loadInbox(currentPage);
            }
        });
    });

    $('#btn-delete').on('click', function() {
        var ids = DM.getSelectedIds();
        if (ids.length === 0) {
            DM.toast('Please select messages', 'warning');
            return;
        }
        if (!confirm('Delete ' + ids.length + ' message(s)?')) return;

        DM.post('sms_delete_inbox', { ids: ids }, function(response) {
            if (response.success) {
                DM.toast('Deleted ' + response.data.deleted + ' messages', 'success');
                loadInbox(currentPage);
            }
        });
    });

    // Row expansion
    $(document).on('click', '.dm-row-expand-btn', function() {
        var $content = $(this).find('.dm-expandable-content');
        var $parent = $(this).closest('tr');

        if ($content.hasClass('expanded')) {
            $content.removeClass('expanded').html('');
        } else {
            // Collapse others
            $('.dm-expandable-content.expanded').removeClass('expanded').html('');
            $content.addClass('expanded').html('<pre>' + DM.escapeHtml($content.data('full')) + '</pre>');
        }
    });

    // Load on page ready
    $(document).ready(function() {
        loadInbox();
    });

})(jQuery);
</script>
