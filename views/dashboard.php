<?php
/**
 * Dashboard View
 *
 * Main overview page showing summary stats, charts, and recent activity.
 * Auto-refreshes every 10 seconds.
 */

// Get module instance
$module = \FreePBX::Donglemanager();
?>
<div class="dm-container">
    <div class="dm-page-header">
        <h2><i class="fa fa-tachometer-alt"></i> Dashboard</h2>
    </div>

    <!-- AMI Warning Banner (hidden by default, shown when AMI unavailable) -->
    <div id="dm-ami-warning" class="alert alert-danger" style="display:none;">
        <i class="fa fa-exclamation-triangle"></i>
        <strong>AMI Connection Unavailable</strong> — Cannot communicate with Asterisk. Dongle status and send operations are disabled. Check that Asterisk is running and AMI is configured.
    </div>

    <!-- Summary Cards Row -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-sm-6 col-md-3">
            <div class="dm-summary-card primary">
                <div class="dm-summary-icon"><i class="fa fa-signal"></i></div>
                <div class="dm-summary-value" id="dm-total-dongles">-</div>
                <div class="dm-summary-label">Total Dongles</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="dm-summary-card success">
                <div class="dm-summary-icon"><i class="fa fa-envelope"></i></div>
                <div class="dm-summary-value" id="dm-received-today">-</div>
                <div class="dm-summary-label">Received Today</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="dm-summary-card primary">
                <div class="dm-summary-icon"><i class="fa fa-paper-plane"></i></div>
                <div class="dm-summary-value" id="dm-sent-today">-</div>
                <div class="dm-summary-label">Sent Today</div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="dm-summary-card danger">
                <div class="dm-summary-icon"><i class="fa fa-exclamation-triangle"></i></div>
                <div class="dm-summary-value" id="dm-failed-today">-</div>
                <div class="dm-summary-label">Failed Today</div>
            </div>
        </div>
    </div>

    <!-- Chart and Dongle Status Row -->
    <div class="row" style="margin-bottom: 20px;">
        <div class="col-md-8">
            <div class="dm-card">
                <div class="dm-card-header">
                    <i class="fa fa-chart-line"></i> SMS Activity (7 Days)
                </div>
                <div class="dm-card-body">
                    <div class="dm-chart-container">
                        <canvas id="chart7day"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dm-card">
                <div class="dm-card-header">
                    <i class="fa fa-broadcast-tower"></i> Dongle Status
                </div>
                <div class="dm-card-body" id="dm-dongle-status-list">
                    <div class="dm-loading">
                        <div class="dm-spinner"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Row -->
    <div class="row">
        <div class="col-md-6">
            <div class="dm-card">
                <div class="dm-card-header">
                    <i class="fa fa-inbox"></i> Recent Inbox
                </div>
                <div class="dm-card-body">
                    <table class="dm-table" id="dm-recent-inbox" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>From</th>
                                <th>Message</th>
                                <th>Dongle</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    <div class="dm-loading">
                                        <div class="dm-spinner"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="dm-card">
                <div class="dm-card-header">
                    <i class="fa fa-terminal"></i> Recent USSD
                </div>
                <div class="dm-card-body">
                    <table class="dm-table" id="dm-recent-ussd" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Dongle</th>
                                <th>Command</th>
                                <th>Response</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center text-muted">
                                    <div class="dm-loading">
                                        <div class="dm-spinner"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';

    var autoRefreshId = null;

    // Initialize dashboard
    DM.initDashboard = function() {
        // Load initial data
        DM.ajax('dashboard_stats', {}, function(response) {
            if (response.success) {
                DM.updateDashboard(response);
            }
        });

        // Start auto-refresh (10 seconds)
        autoRefreshId = DM.startAutoRefresh('dashboard_stats', DM.updateDashboard, 10000);
    };

    // Cleanup on page unload
    $(window).on('beforeunload', function() {
        if (autoRefreshId) {
            DM.stopAutoRefresh(autoRefreshId);
        }
    });

    // Initialize on DOM ready
    $(document).ready(function() {
        DM.initDashboard();
    });

})(jQuery);
</script>
