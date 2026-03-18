<?php
/**
 * Send SMS View
 *
 * Form to compose and send SMS messages via a specific dongle.
 */

$module = \FreePBX::Donglemanager();
$dongles = $module->getActiveDongles();
?>
<div class="dm-container">
    <div class="dm-page-header">
        <h2><i class="fa fa-paper-plane"></i> Send SMS</h2>
    </div>

    <?php if (empty($dongles)): ?>
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>No Active Dongles</strong> - There are no dongles currently available to send SMS.
            Please check the <a href="?display=donglemanager&view=dongles">Dongles</a> page.
        </div>
    <?php else: ?>
        <div class="dm-sms-form">
            <form id="sms-send-form">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="dongle">Select Dongle</label>
                            <select name="dongle" id="dongle" class="form-control" required>
                                <option value="">-- Select Dongle --</option>
                                <?php foreach ($dongles as $d): ?>
                                    <option value="<?php echo htmlspecialchars($d['device']); ?>">
                                        <?php echo htmlspecialchars($d['device']); ?>
                                        <?php if ($d['phone_number']): ?>
                                            — <?php echo htmlspecialchars($d['phone_number']); ?>
                                        <?php endif; ?>
                                        <?php if ($d['operator']): ?>
                                            (<?php echo htmlspecialchars($d['operator']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="destination">Destination Number</label>
                            <input type="text" name="destination" id="destination" class="form-control"
                                   placeholder="+1234567890" maxlength="30" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="message">Message</label>
                    <textarea name="message" id="message" class="form-control" rows="4"
                              maxlength="459" required placeholder="Enter your message here..."></textarea>
                    <div class="dm-sms-counter" id="sms-counter">0 / 160 characters (0 SMS parts, GSM-7)</div>
                </div>

                <div class="form-group">
                    <button type="submit" class="dm-btn dm-btn-primary">
                        <i class="fa fa-paper-plane"></i> Send SMS
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div class="dm-card" style="margin-top: 30px;">
        <div class="dm-card-header">
            <i class="fa fa-history"></i> Recent Sent Messages
        </div>
        <div class="dm-card-body">
            <table class="dm-table" id="recent-sent-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Dongle</th>
                        <th>Destination</th>
                        <th>Message</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="text-center text-muted">Loading...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function($) {
    'use strict';

    // Initialize SMS counter
    DM.smsCounter('message', 'sms-counter');

    // Load recent sent messages
    function loadRecentSent() {
        DM.ajax('sms_list_outbox', { per_page: 10 }, function(response) {
            if (!response.success) return;

            var html = '';
            if (response.data.rows.length === 0) {
                html = '<tr><td colspan="5" class="text-center text-muted">No messages sent</td></tr>';
            } else {
                response.data.rows.forEach(function(row) {
                    var statusClass = row.status;
                    html += '<tr>';
                    html += '<td>' + DM.formatDate(row.created_at) + '</td>';
                    html += '<td>' + DM.escapeHtml(row.dongle) + '</td>';
                    html += '<td>' + DM.escapeHtml(row.destination) + '</td>';
                    html += '<td>' + DM.escapeHtml(DM.truncate(row.message, 40)) + '</td>';
                    html += '<td><span class="dm-badge ' + statusClass + '">' + DM.escapeHtml(row.status) + '</span></td>';
                    html += '</tr>';
                });
            }
            $('#recent-sent-table tbody').html(html);
        });
    }

    // Form submission
    $('#sms-send-form').on('submit', function(e) {
        e.preventDefault();

        var dongle = $('#dongle').val();
        var destination = $('#destination').val();
        var message = $('#message').val();

        if (!dongle) {
            DM.toast('Please select a dongle', 'error');
            return;
        }

        if (!destination || !message) {
            DM.toast('Destination and message are required', 'error');
            return;
        }

        DM.post('sms_send', {
            dongle: dongle,
            destination: destination,
            message: message
        }, function(response) {
            if (response.success) {
                DM.toast('SMS queued for sending', 'success');
                $('#message').val('');
                $('#destination').val('');
                DM.smsCounter('message', 'sms-counter'); // Reset counter
                loadRecentSent();
            } else {
                DM.toast(response.message || 'Failed to send SMS', 'error');
            }
        });
    });

    // Load on page ready
    $(document).ready(function() {
        loadRecentSent();
    });

})(jQuery);
</script>
