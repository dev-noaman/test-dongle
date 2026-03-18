<?php
/**
 * USSD View
 *
 * Send USSD commands with quick buttons, poll for response, display history.
 */

$module = \FreePBX::Donglemanager();
$dongles = $module->getActiveDongles();
?>
<div class="dm-container">
    <div class="dm-page-header">
        <h2><i class="fa fa-terminal"></i> USSD Commands</h2>
    </div>

    <div class="row">
        <div class="col-md-6">
            <?php if (empty($dongles)): ?>
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>No Active Dongles</strong> - There are no dongles currently available.
                </div>
            <?php else: ?>
                <div class="dm-card">
                    <div class="dm-card-header">
                        <i class="fa fa-paper-plane"></i> Send USSD
                    </div>
                    <div class="dm-card-body">
                        <form id="ussd-send-form">
                            <div class="form-group">
                                <label for="dongle">Select Dongle</label>
                                <select name="dongle" id="ussd-dongle" class="form-control" required>
                                    <option value="">-- Select Dongle --</option>
                                    <?php foreach ($dongles as $d): ?>
                                        <option value="<?php echo htmlspecialchars($d['device']); ?>">
                                            <?php echo htmlspecialchars($d['device']); ?>
                                            <?php if ($d['operator']): ?>
                                                (<?php echo htmlspecialchars($d['operator']); ?>)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="ussd_command">USSD Code</label>
                                <input type="text" name="ussd_command" id="ussd_command" class="form-control"
                                       placeholder="*100#" maxlength="255" required>
                                <div class="dm-ussd-quick-buttons">
                                    <button type="button" class="dm-btn dm-btn-outline dm-btn-sm quick-ussd" data-code="*100#">*100#</button>
                                    <button type="button" class="dm-btn dm-btn-outline dm-btn-sm quick-ussd" data-code="*101#">*101#</button>
                                    <button type="button" class="dm-btn dm-btn-outline dm-btn-sm quick-ussd" data-code="*102#">*102#</button>
                                    <button type="button" class="dm-btn dm-btn-outline dm-btn-sm quick-ussd" data-code="*111#">*111#</button>
                                </div>
                            </div>

                            <div class="form-group">
                                <button type="submit" class="dm-btn dm-btn-primary" id="ussd-submit-btn">
                                    <i class="fa fa-paper-plane"></i> Send
                                </button>
                                <span id="ussd-spinner" style="display:none; margin-left:10px;">
                                    <i class="fa fa-spinner fa-spin"></i> Waiting for response...
                                </span>
                            </div>
                        </form>

                        <div id="ussd-response-area" style="display:none;">
                            <label>Response:</label>
                            <div class="dm-ussd-response" id="ussd-response-text"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <div class="dm-card">
                <div class="dm-card-header">
                    <i class="fa fa-history"></i> Recent USSD History
                </div>
                <div class="dm-card-body">
                    <table class="dm-table" id="ussd-history-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Dongle</th>
                                <th>Command</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Loading...</td>
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

    var ussdPollInterval = null;
    var currentUssdId = null;

    // Quick USSD buttons
    $('.quick-ussd').on('click', function() {
        $('#ussd_command').val($(this).data('code'));
    });

    // USSD form submission
    $('#ussd-send-form').on('submit', function(e) {
        e.preventDefault();

        var dongle = $('#ussd-dongle').val();
        var command = $('#ussd_command').val();

        if (!dongle || !command) {
            DM.toast('Please select a dongle and enter USSD code', 'warning');
            return;
        }

        // Disable button, show spinner
        $('#ussd-submit-btn').prop('disabled', true);
        $('#ussd-spinner').show();
        $('#ussd-response-area').hide();

        DM.post('ussd_send', {
            dongle: dongle,
            ussd_command: command
        }, function(response) {
            if (response.success) {
                currentUssdId = response.data.id;
                startPolling(currentUssdId);
                loadUssdHistory();
            } else {
                DM.toast(response.message || 'Failed to send USSD', 'error');
                resetUssdForm();
            }
        });
    });

    function startPolling(ussdId) {
        var pollCount = 0;
        var maxPolls = 15; // 30 seconds max

        ussdPollInterval = setInterval(function() {
            pollCount++;

            DM.ajax('ussd_check', { id: ussdId }, function(response) {
                if (!response.success) {
                    clearInterval(ussdPollInterval);
                    resetUssdForm();
                    return;
                }

                var status = response.data.status;

                if (status === 'received') {
                    clearInterval(ussdPollInterval);
                    $('#ussd-response-text').text(response.data.response || '(empty response)');
                    $('#ussd-response-area').show();
                    resetUssdForm();
                    DM.toast('USSD response received', 'success');
                } else if (status === 'timeout' || status === 'failed') {
                    clearInterval(ussdPollInterval);
                    $('#ussd-response-text').text('Request ' + status);
                    $('#ussd-response-area').show();
                    resetUssdForm();
                    DM.toast('USSD ' + status, 'warning');
                } else if (pollCount >= maxPolls) {
                    clearInterval(ussdPollInterval);
                    resetUssdForm();
                    DM.toast('USSD response timeout', 'warning');
                }
            });
        }, 2000);
    }

    function resetUssdForm() {
        $('#ussd-submit-btn').prop('disabled', false);
        $('#ussd-spinner').hide();
    }

    function loadUssdHistory() {
        DM.ajax('ussd_history', { per_page: 10 }, function(response) {
            if (!response.success) return;

            var html = '';
            if (response.data.rows.length === 0) {
                html = '<tr><td colspan="4" class="text-center text-muted">No USSD history</td></tr>';
            } else {
                response.data.rows.forEach(function(row) {
                    html += '<tr class="ussd-history-row" data-response="' + DM.escapeHtml(row.response || '') + '">';
                    html += '<td>' + DM.formatDate(row.created_at) + '</td>';
                    html += '<td>' + DM.escapeHtml(row.dongle) + '</td>';
                    html += '<td>' + DM.escapeHtml(row.command) + '</td>';
                    html += '<td><span class="dm-badge ' + row.status + '">' + DM.escapeHtml(row.status) + '</span></td>';
                    html += '</tr>';
                });
            }
            $('#ussd-history-table tbody').html(html);
        });
    }

    // Click history row to show full response
    $(document).on('click', '.ussd-history-row', function() {
        var response = $(this).data('response');
        if (response) {
            $('#ussd-response-text').text(response);
            $('#ussd-response-area').show();
        }
    });

    $(document).ready(function() {
        loadUssdHistory();
    });

})(jQuery);
</script>
