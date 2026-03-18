<?php
/**
 * Dongles View
 *
 * Card grid showing all dongle details with restart/stop/start controls.
 */

$module = \FreePBX::Donglemanager();
?>
<div class="dm-container">
    <div class="dm-page-header">
        <h2><i class="fa fa-broadcast-tower"></i> Dongle Management</h2>
    </div>

    <!-- Summary Bar -->
    <div class="dm-card" id="dongle-summary" style="margin-bottom: 20px;">
        <div class="dm-card-body" style="padding: 10px 20px;">
            <span id="summary-text">Loading...</span>
        </div>
    </div>

    <!-- Refresh Button -->
    <div class="dm-btn-group" style="margin-bottom: 20px;">
        <button type="button" id="btn-refresh-all" class="dm-btn dm-btn-primary">
            <i class="fa fa-sync-alt"></i> Refresh All
        </button>
    </div>

    <!-- Dongle Cards Grid -->
    <div class="dm-dongle-grid" id="dongle-grid">
        <div class="dm-loading"><div class="dm-spinner"></div></div>
    </div>
</div>

<script>
(function($) {
    'use strict';

    var autoRefreshId = null;

    function loadDongles() {
        DM.ajax('dongle_list', {}, function(response) {
            if (!response.success) return;

            var dongles = response.data;
            var html = '';

            if (dongles.length === 0) {
                html = '<div class="col-md-12"><div class="alert alert-info">No dongles detected. Ensure chan_dongle is configured and dongles are connected.</div></div>';
            } else {
                dongles.forEach(function(d) {
                    var signalClass = d.signal_percent > 60 ? 'strong' : (d.signal_percent > 30 ? 'medium' : 'weak');
                    var stateClass = (d.state || 'offline').toLowerCase();

                    html += '<div class="dm-dongle-card" data-device="' + DM.escapeHtml(d.device) + '">';
                    html += '<div class="dm-dongle-card-header">';
                    html += '<div>';
                    html += '<div class="dm-dongle-card-title">' + DM.escapeHtml(d.device) + '</div>';
                    html += '<div class="dm-dongle-card-subtitle">' + DM.escapeHtml(d.phone_number || '-') + ' ' + DM.escapeHtml(d.operator || '') + '</div>';
                    html += '</div>';
                    html += '<span class="dm-badge ' + stateClass + '">' + DM.escapeHtml(d.state || 'Unknown') + '</span>';
                    html += '</div>';

                    html += '<div class="dm-dongle-card-body">';
                    html += '<div style="margin-bottom: 10px;">';
                    html += '<div style="display:flex;justify-content:space-between;margin-bottom:5px;">';
                    html += '<span>Signal</span><span>' + (d.signal_percent || 0) + '%</span>';
                    html += '</div>';
                    html += '<div class="dm-signal-bar"><div class="dm-signal-bar-fill ' + signalClass + '" style="width:' + (d.signal_percent || 0) + '%"></div></div>';
                    html += '</div>';

                    html += '<div class="dm-dongle-info-row">';
                    html += '<span class="dm-dongle-info-label">IMEI</span>';
                    html += '<span class="dm-dongle-info-value">' + DM.escapeHtml(d.imei || '-') + '</span>';
                    html += '</div>';
                    html += '<div class="dm-dongle-info-row">';
                    html += '<span class="dm-dongle-info-label">IMSI</span>';
                    html += '<span class="dm-dongle-info-value">' + DM.escapeHtml(d.imsi || '-') + '</span>';
                    html += '</div>';
                    html += '<div class="dm-dongle-info-row">';
                    html += '<span class="dm-dongle-info-label">GSM Status</span>';
                    html += '<span class="dm-dongle-info-value">' + DM.escapeHtml(d.gsm_status || '-') + '</span>';
                    html += '</div>';
                    html += '<div class="dm-dongle-info-row">';
                    html += '<span class="dm-dongle-info-label">Model</span>';
                    html += '<span class="dm-dongle-info-value">' + DM.escapeHtml(d.model || '-') + '</span>';
                    html += '</div>';
                    html += '<div class="dm-dongle-info-row">';
                    html += '<span class="dm-dongle-info-label">SMS In/Out</span>';
                    html += '<span class="dm-dongle-info-value">' + (d.sms_in_count || 0) + ' / ' + (d.sms_out_count || 0) + '</span>';
                    html += '</div>';
                    html += '<div class="dm-dongle-info-row">';
                    html += '<span class="dm-dongle-info-label">Last Seen</span>';
                    html += '<span class="dm-dongle-info-value">' + DM.formatDate(d.last_seen) + '</span>';
                    html += '</div>';
                    html += '</div>';

                    html += '<div class="dm-dongle-actions">';
                    html += '<button class="dm-btn dm-btn-sm dm-btn-warning btn-restart" data-device="' + DM.escapeHtml(d.device) + '"><i class="fa fa-redo"></i> Restart</button>';

                    if (d.state === 'Stopped') {
                        html += '<button class="dm-btn dm-btn-sm dm-btn-success btn-start" data-device="' + DM.escapeHtml(d.device) + '"><i class="fa fa-play"></i> Start</button>';
                    } else {
                        html += '<button class="dm-btn dm-btn-sm dm-btn-danger btn-stop" data-device="' + DM.escapeHtml(d.device) + '"><i class="fa fa-stop"></i> Stop</button>';
                    }

                    html += '<button class="dm-btn dm-btn-sm dm-btn-outline btn-refresh" data-device="' + DM.escapeHtml(d.device) + '"><i class="fa fa-sync-alt"></i></button>';
                    html += '</div>';

                    html += '</div>';
                });
            }

            $('#dongle-grid').html(html);

            // Update summary
            var total = dongles.length;
            var active = dongles.filter(function(d) { return d.state === 'Free' || d.state === 'Busy'; }).length;
            var busy = dongles.filter(function(d) { return d.state === 'Busy'; }).length;
            var offline = dongles.filter(function(d) { return d.state === 'Offline'; }).length;
            $('#summary-text').html(
                '<strong>' + total + '</strong> Dongles: ' +
                '<span class="dm-badge active">' + active + ' Active</span> ' +
                '<span class="dm-badge busy">' + busy + ' Busy</span> ' +
                '<span class="dm-badge offline">' + offline + ' Offline</span>'
            );
        });
    }

    // Refresh all
    $('#btn-refresh-all').on('click', function() {
        $(this).find('i').addClass('fa-spin');
        DM.post('dongle_refresh', {}, function(response) {
            $('#btn-refresh-all').find('i').removeClass('fa-spin');
            if (response.success) {
                loadDongles();
            }
        });
    });

    // Individual dongle actions
    $(document).on('click', '.btn-restart', function() {
        var device = $(this).data('device');
        if (!confirm('Restart dongle ' + device + '?')) return;

        var $btn = $(this);
        $btn.prop('disabled', true);
        DM.post('dongle_restart', { device: device }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                DM.toast(response.message, 'success');
                DM.invalidateDongleCache();
                setTimeout(loadDongles, 2000);
            } else {
                DM.toast(response.message, 'error');
            }
        });
    });

    $(document).on('click', '.btn-stop', function() {
        var device = $(this).data('device');
        if (!confirm('Stop dongle ' + device + '?')) return;

        var $btn = $(this);
        $btn.prop('disabled', true);
        DM.post('dongle_stop', { device: device }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                DM.toast(response.message, 'success');
                DM.invalidateDongleCache();
                setTimeout(loadDongles, 1000);
            } else {
                DM.toast(response.message, 'error');
            }
        });
    });

    $(document).on('click', '.btn-start', function() {
        var device = $(this).data('device');

        var $btn = $(this);
        $btn.prop('disabled', true);
        DM.post('dongle_start', { device: device }, function(response) {
            $btn.prop('disabled', false);
            if (response.success) {
                DM.toast(response.message, 'success');
                DM.invalidateDongleCache();
                setTimeout(loadDongles, 1000);
            } else {
                DM.toast(response.message, 'error');
            }
        });
    });

    $(document).on('click', '.btn-refresh', function(e) {
        e.stopPropagation();
        loadDongles();
    });

    // Auto-refresh every 15 seconds
    $(document).ready(function() {
        loadDongles();
        autoRefreshId = DM.startAutoRefresh('dongle_list', function(response) {
            if (response.success) {
                // Silently update cards (no spinner)
                var dongles = response.data;
                dongles.forEach(function(d) {
                    var $card = $('.dm-dongle-card[data-device="' + d.device + '"]');
                    if ($card.length) {
                        // Update just the signal and state
                        $card.find('.dm-badge').first()
                            .removeClass('active busy offline error init')
                            .addClass((d.state || 'offline').toLowerCase())
                            .text(d.state || 'Unknown');
                    }
                });
            }
        }, 15000);
    });

    $(window).on('beforeunload', function() {
        if (autoRefreshId) DM.stopAutoRefresh(autoRefreshId);
    });

})(jQuery);
</script>
