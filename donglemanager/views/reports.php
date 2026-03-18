<?php
/**
 * Reports View
 *
 * SMS traffic reports with filters, summary cards, charts, and stats table.
 */

$module = \FreePBX::Donglemanager();
$dongles = $module->getAllDongles();

// Default date range: last 7 days
$defaultDateFrom = date('Y-m-d', strtotime('-7 days'));
$defaultDateTo = date('Y-m-d');
?>
<div class="dm-container">
    <div class="dm-page-header">
        <h2><i class="fa fa-chart-bar"></i> Reports</h2>
    </div>

    <!-- Filter Bar -->
    <div class="dm-filter-bar">
        <div class="dm-filter-group">
            <label>From Date</label>
            <input type="date" id="filter-date-from" value="<?php echo $defaultDateFrom; ?>">
        </div>
        <div class="dm-filter-group">
            <label>To Date</label>
            <input type="date" id="filter-date-to" value="<?php echo $defaultDateTo; ?>">
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
            <button type="button" id="btn-apply" class="dm-btn dm-btn-primary">
                <i class="fa fa-filter"></i> Apply
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-4 col-md-2-4">
            <div class="dm-summary-card primary">
                <div class="dm-summary-value" id="stat-total">-</div>
                <div class="dm-summary-label">Total Messages</div>
            </div>
        </div>
        <div class="col-sm-4 col-md-2-4">
            <div class="dm-summary-card primary">
                <div class="dm-summary-value" id="stat-sent">-</div>
                <div class="dm-summary-label">Sent</div>
            </div>
        </div>
        <div class="col-sm-4 col-md-2-4">
            <div class="dm-summary-card success">
                <div class="dm-summary-value" id="stat-received">-</div>
                <div class="dm-summary-label">Received</div>
            </div>
        </div>
        <div class="col-sm-4 col-md-2-4">
            <div class="dm-summary-card danger">
                <div class="dm-summary-value" id="stat-failed">-</div>
                <div class="dm-summary-label">Failed</div>
            </div>
        </div>
        <div class="col-sm-4 col-md-2-4">
            <div class="dm-summary-card success">
                <div class="dm-summary-value" id="stat-success-rate">-</div>
                <div class="dm-summary-label">Success Rate</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-8">
            <div class="dm-card">
                <div class="dm-card-header">
                    <i class="fa fa-chart-bar"></i> Daily Traffic
                </div>
                <div class="dm-card-body">
                    <div class="dm-chart-container">
                        <canvas id="chart-daily"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dm-card">
                <div class="dm-card-header">
                    <i class="fa fa-chart-pie"></i> Per Dongle
                </div>
                <div class="dm-card-body">
                    <div class="dm-chart-container">
                        <canvas id="chart-dongle"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dongle Stats Table -->
    <div class="dm-card">
        <div class="dm-card-header">
            <i class="fa fa-table"></i> Dongle Statistics
        </div>
        <div class="dm-card-body">
            <table class="dm-table" id="stats-table">
                <thead>
                    <tr>
                        <th class="sortable">Dongle</th>
                        <th class="sortable">Phone</th>
                        <th class="sortable">Operator</th>
                        <th class="sortable" data-numeric="true">Sent</th>
                        <th class="sortable" data-numeric="true">Received</th>
                        <th class="sortable" data-numeric="true">Failed</th>
                        <th class="sortable" data-numeric="true">Success Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="7" class="text-center text-muted">Click "Apply" to load report</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="totals-row" style="font-weight:bold;background:#f8f9fa;">
                        <td colspan="3">Total</td>
                        <td id="total-sent">-</td>
                        <td id="total-received">-</td>
                        <td id="total-failed">-</td>
                        <td id="total-rate">-</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';

    function loadReport() {
        var dateFrom = $('#filter-date-from').val();
        var dateTo = $('#filter-date-to').val();
        var dongle = $('#filter-dongle').val();

        if (!dateFrom || !dateTo) {
            DM.toast('Please select date range', 'warning');
            return;
        }

        // Load summary
        DM.ajax('report_summary', {
            date_from: dateFrom,
            date_to: dateTo,
            dongle: dongle
        }, function(response) {
            if (response.success) {
                var data = response.data;
                $('#stat-total').text(data.total);
                $('#stat-sent').text(data.sent);
                $('#stat-received').text(data.received);
                $('#stat-failed').text(data.failed);
                $('#stat-success-rate').text(data.success_rate + '%');
            }
        });

        // Load chart data
        DM.ajax('report_chart', {
            date_from: dateFrom,
            date_to: dateTo,
            dongle: dongle
        }, function(response) {
            if (response.success) {
                DM.renderDailyChart('chart-daily', response.data.daily);
                DM.renderDongleChart('chart-dongle', response.data.per_dongle);
            }
        });

        // Load dongle stats
        DM.ajax('report_dongle_stats', {
            date_from: dateFrom,
            date_to: dateTo
        }, function(response) {
            if (response.success) {
                var html = '';
                var totalSent = 0, totalReceived = 0, totalFailed = 0;

                if (response.data.length === 0) {
                    html = '<tr><td colspan="7" class="text-center text-muted">No data for selected period</td></tr>';
                } else {
                    response.data.forEach(function(row) {
                        totalSent += parseInt(row.sent) || 0;
                        totalReceived += parseInt(row.received) || 0;
                        totalFailed += parseInt(row.failed) || 0;

                        html += '<tr>';
                        html += '<td>' + DM.escapeHtml(row.device) + '</td>';
                        html += '<td>' + DM.escapeHtml(row.phone_number || '-') + '</td>';
                        html += '<td>' + DM.escapeHtml(row.operator || '-') + '</td>';
                        html += '<td>' + row.sent + '</td>';
                        html += '<td>' + row.received + '</td>';
                        html += '<td>' + row.failed + '</td>';
                        html += '<td>' + row.success_rate + '%</td>';
                        html += '</tr>';
                    });
                }

                $('#stats-table tbody').html(html);

                // Update totals
                $('#total-sent').text(totalSent);
                $('#total-received').text(totalReceived);
                $('#total-failed').text(totalFailed);
                var totalRate = totalSent > 0 ? Math.round(((totalSent - totalFailed) / totalSent) * 100 * 10) / 10 : 0;
                $('#total-rate').text(totalRate + '%');
            }
        });
    }

    $('#btn-apply').on('click', loadReport);

    // Make table sortable
    DM.sortableTable('stats-table');

    // Load initial report
    $(document).ready(function() {
        loadReport();
    });

})(jQuery);
</script>
