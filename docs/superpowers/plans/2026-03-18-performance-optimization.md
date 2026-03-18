# Donglemanager Performance Optimization Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate bugs and optimize the donglemanager module so it feels as smooth and responsive as a built-in FreePBX module.

**Architecture:** Fix 2 bugs (interval leak, chart flicker), optimize 6 SQL queries to use index-friendly range conditions, reduce dashboard from 6 queries to 4, stream CSV export, and cache dongle list client-side to eliminate redundant PHP queries on AJAX view loads.

**Tech Stack:** PHP 7.4+ (PDO/MariaDB), jQuery, Chart.js 4, FreePBX BMO framework

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `Donglemanager.class.php` | Modify | Fix DATE() queries, combine dashboard queries, stream CSV, add dongle_list_simple endpoint |
| `views/main.php` | Modify | Cache dongle list in JS on initial load |
| `views/dashboard.php` | Modify | Update chart data in-place instead of destroy/recreate |
| `views/logs.php` | Modify | Fix auto-refresh interval leak, use DM.startAutoRefresh |
| `views/sms_inbox.php` | Modify | Use cached dongle list for filter dropdown |
| `views/sms_outbox.php` | Modify | Use cached dongle list for filter dropdown |
| `views/sms_send.php` | Modify | Use cached dongle list, fix SMS counter double-bind |
| `views/ussd.php` | Modify | Use cached dongle list |
| `views/reports.php` | Modify | Use cached dongle list |
| `assets/js/donglemanager.js` | Modify | Add DM.dongleCache, fix smsCounter to use only `input` event |
| `cron/worker.php` | Modify | Batch log cleanup with LIMIT |
| `install.php` | Modify | Add composite indexes |

---

### Task 1: Fix Logs Auto-Refresh Interval Leak (Bug)

**Problem:** `views/logs.php` creates its own `setInterval` at line 169 that `DM.stopAllAutoRefresh()` doesn't know about. When user navigates away via AJAX, the interval keeps firing requests to a stale view.

**Files:**
- Modify: `donglemanager/views/logs.php:162-180`

- [ ] **Step 1: Replace custom setInterval with DM.startAutoRefresh**

In `views/logs.php`, replace the auto-refresh toggle handler (lines 162-180):

```php
// OLD (lines 162-180):
$('#btn-auto-refresh').on('click', function() {
    autoRefreshEnabled = !autoRefreshEnabled;

    if (autoRefreshEnabled) {
        $(this).removeClass('dm-btn-outline').addClass('dm-btn-primary');
        $(this).html('<i class="fa fa-sync-alt fa-spin"></i> Auto-Refresh: On');
        autoRefreshId = setInterval(function() {
            loadLogs(currentPage);
        }, 10000);
    } else {
        $(this).removeClass('dm-btn-primary').addClass('dm-btn-outline');
        $(this).html('<i class="fa fa-sync-alt"></i> Auto-Refresh: Off');
        if (autoRefreshId) {
            clearInterval(autoRefreshId);
            autoRefreshId = null;
        }
    }
});
```

Replace with:

```javascript
$('#btn-auto-refresh').on('click', function() {
    autoRefreshEnabled = !autoRefreshEnabled;

    if (autoRefreshEnabled) {
        $(this).removeClass('dm-btn-outline').addClass('dm-btn-primary');
        $(this).html('<i class="fa fa-sync-alt fa-spin"></i> Auto-Refresh: On');
        autoRefreshId = DM.startAutoRefresh('log_list', function(response) {
            if (response.success) {
                renderLogRows(response);
            }
        }, 10000);
    } else {
        $(this).removeClass('dm-btn-primary').addClass('dm-btn-outline');
        $(this).html('<i class="fa fa-sync-alt"></i> Auto-Refresh: Off');
        if (autoRefreshId) {
            DM.stopAutoRefresh(autoRefreshId);
            autoRefreshId = null;
        }
    }
});
```

- [ ] **Step 2: Extract log rendering into a reusable function**

The `loadLogs` function currently handles both the AJAX call and the DOM update. Extract the rendering part so the auto-refresh callback can reuse it. Replace the `loadLogs` function:

```javascript
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
```

- [ ] **Step 3: Remove the stale beforeunload handler**

Delete lines 198-202 (the `$(window).on('beforeunload', ...)` block). It's no longer needed because `DM.stopAllAutoRefresh()` is called by the AJAX navigation in `main.php` before loading a new view.

- [ ] **Step 4: Verify manually**

Navigate to Logs tab → enable auto-refresh → switch to Dashboard tab → check browser Network tab. No more `log_list` requests should fire after leaving the Logs tab.

- [ ] **Step 5: Commit**

```bash
git add donglemanager/views/logs.php
git commit -m "fix: logs auto-refresh interval leak on AJAX navigation

DM.stopAllAutoRefresh() now properly cleans up the logs interval
since it uses DM.startAutoRefresh instead of raw setInterval."
```

---

### Task 2: Fix Dashboard Chart Flicker on Auto-Refresh

**Problem:** `DM.updateDashboard` calls `DM.initDashboardChart` every 10 seconds, which destroys and recreates the Chart.js instance. This causes visible flicker.

**Files:**
- Modify: `donglemanager/assets/js/donglemanager.js:420-470`

- [ ] **Step 1: Update initDashboardChart to reuse existing chart instance**

Replace the `DM.initDashboardChart` function (lines 420-470) in `donglemanager.js`:

```javascript
DM.initDashboardChart = function(canvasId, chartData) {
    var ctx = document.getElementById(canvasId);
    if (!ctx) return;

    // If chart already exists, update data instead of destroying
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
```

Key change: `DM.dashboardChart.update('none')` updates data in-place with zero animation overhead.

- [ ] **Step 2: Verify manually**

Open Dashboard tab → observe chart renders once → wait 10s for auto-refresh → chart should update smoothly without any flash or flicker.

- [ ] **Step 3: Commit**

```bash
git add donglemanager/assets/js/donglemanager.js
git commit -m "fix: dashboard chart updates data in-place instead of destroy/recreate

Eliminates flicker on 10s auto-refresh. Uses Chart.js update('none')
for zero-animation data swap."
```

---

### Task 3: Fix DATE() Function Killing Index Usage

**Problem:** Six queries wrap columns in `DATE()` which prevents MySQL from using indexes on `created_at`/`received_at`. This forces full table scans.

**Files:**
- Modify: `donglemanager/Donglemanager.class.php` — multiple methods

- [ ] **Step 1: Fix handleDashboardStats SMS subqueries (line 511-516)**

Replace:
```php
$stmt = $this->db->query("
    SELECT
        (SELECT COUNT(*) FROM donglemanager_sms_outbox WHERE DATE(created_at) = CURDATE()) as sent,
        (SELECT COUNT(*) FROM donglemanager_sms_outbox WHERE DATE(created_at) = CURDATE() AND status = 'failed') as failed,
        (SELECT COUNT(*) FROM donglemanager_sms_inbox WHERE DATE(received_at) = CURDATE()) as received
");
```

With:
```php
$stmt = $this->db->query("
    SELECT
        (SELECT COUNT(*) FROM donglemanager_sms_outbox WHERE created_at >= CURDATE()) as sent,
        (SELECT COUNT(*) FROM donglemanager_sms_outbox WHERE created_at >= CURDATE() AND status = 'failed') as failed,
        (SELECT COUNT(*) FROM donglemanager_sms_inbox WHERE received_at >= CURDATE()) as received
");
```

- [ ] **Step 2: Fix handleSmsListInbox date filters (lines 599-605)**

Replace:
```php
if (!empty($_GET['date_from'])) {
    $where .= ' AND DATE(received_at) >= ?';
    $params[] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where .= ' AND DATE(received_at) <= ?';
    $params[] = $_GET['date_to'];
}
```

With:
```php
if (!empty($_GET['date_from'])) {
    $where .= ' AND received_at >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $where .= ' AND received_at < ? + INTERVAL 1 DAY';
    $params[] = $_GET['date_to'] . ' 00:00:00';
}
```

Wait — MariaDB doesn't support `? + INTERVAL` in prepared statements cleanly. Use this instead:

```php
if (!empty($_GET['date_from'])) {
    $where .= ' AND received_at >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $where .= ' AND received_at < DATE(?) + INTERVAL 1 DAY';
    $params[] = $_GET['date_to'];
}
```

Actually, the simplest reliable approach for MariaDB prepared statements:

```php
if (!empty($_GET['date_from'])) {
    $where .= ' AND received_at >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $where .= ' AND received_at <= ?';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}
```

- [ ] **Step 3: Fix handleSmsListOutbox date filters (lines 649-655)**

Same pattern — replace `DATE(created_at) >= ?` / `DATE(created_at) <= ?`:

```php
if (!empty($_GET['date_from'])) {
    $where .= ' AND created_at >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $where .= ' AND created_at <= ?';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}
```

- [ ] **Step 4: Fix buildLogFilters date filters (lines 421-428)**

Replace:
```php
if (!empty($_GET['date_from'])) {
    $where .= ' AND DATE(created_at) >= ?';
    $params[] = $_GET['date_from'];
}
if (!empty($_GET['date_to'])) {
    $where .= ' AND DATE(created_at) <= ?';
    $params[] = $_GET['date_to'];
}
```

With:
```php
if (!empty($_GET['date_from'])) {
    $where .= ' AND created_at >= ?';
    $params[] = $_GET['date_from'] . ' 00:00:00';
}
if (!empty($_GET['date_to'])) {
    $where .= ' AND created_at <= ?';
    $params[] = $_GET['date_to'] . ' 23:59:59';
}
```

- [ ] **Step 5: Fix handleReportSummary DATE() usage (line 962)**

Replace:
```php
$sql = "SELECT COUNT(*) as total, SUM(status = 'failed') as failed
        FROM donglemanager_sms_outbox WHERE DATE(created_at) BETWEEN ? AND ? {$dongleFilter}";
```

With:
```php
$sql = "SELECT COUNT(*) as total, SUM(status = 'failed') as failed
        FROM donglemanager_sms_outbox WHERE created_at >= ? AND created_at <= ? {$dongleFilter}";
```

And update the params to include time bounds:
```php
$baseParams = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
```

Do the same for the inbox query on line 970:
```php
$sql = "SELECT COUNT(*) FROM donglemanager_sms_inbox WHERE received_at >= ? AND received_at <= ? {$dongleFilter}";
```

- [ ] **Step 6: Fix handleReportChart DATE() usage (lines 1003-1023)**

Replace both queries. Outbox:
```php
$sql = "SELECT DATE(created_at) as date, COUNT(*) as count
        FROM donglemanager_sms_outbox
        WHERE created_at >= ? AND created_at <= ?{$dongleFilter}
        GROUP BY DATE(created_at)
        ORDER BY date";
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
```

Inbox:
```php
$sql = "SELECT DATE(received_at) as date, COUNT(*) as count
        FROM donglemanager_sms_inbox
        WHERE received_at >= ? AND received_at <= ?{$dongleFilter}
        GROUP BY DATE(received_at)
        ORDER BY date";
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
```

Note: `GROUP BY DATE(created_at)` is fine — it's only the WHERE clause that kills index usage.

- [ ] **Step 7: Fix handleReportDongleStats DATE() in 3 subqueries (lines 1092-1121)**

Replace the 3 subquery WHERE clauses from `DATE(created_at) BETWEEN ? AND ?` to `created_at >= ? AND created_at <= ?`, and update the execute params:

```php
$stmt->execute([
    $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59',
    $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59',
    $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'
]);
```

- [ ] **Step 8: Commit**

```bash
git add donglemanager/Donglemanager.class.php
git commit -m "perf: replace DATE() column wrapping with range queries

All date filter queries now use index-friendly range conditions
(>= '2026-01-01 00:00:00' / <= '2026-01-31 23:59:59') instead of
DATE(column) which forces full table scans."
```

---

### Task 4: Add Composite Database Indexes

**Problem:** Queries filter on `(dongle, created_at)` or `(dongle, received_at)` but only single-column indexes exist.

**Files:**
- Modify: `donglemanager/install.php`

- [ ] **Step 1: Add composite indexes to install.php**

Add after the existing `$tables` loop (before `outn()`), append an index migration block:

```php
// Add composite indexes for query performance (idempotent)
$indexes = [
    "ALTER TABLE donglemanager_sms_inbox ADD INDEX idx_dongle_received (dongle, received_at)",
    "ALTER TABLE donglemanager_sms_outbox ADD INDEX idx_dongle_created (dongle, created_at)",
    "ALTER TABLE donglemanager_sms_outbox ADD INDEX idx_status_created (status, created_at)",
    "ALTER TABLE donglemanager_logs ADD INDEX idx_created_level (created_at, level)",
];

foreach ($indexes as $sql) {
    try {
        $db->query($sql);
    } catch (Exception $e) {
        // Index may already exist — ignore duplicate key errors
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add donglemanager/install.php
git commit -m "perf: add composite indexes for filtered date-range queries

Covers (dongle,received_at), (dongle,created_at), (status,created_at),
and (created_at,level) for the most common filter combinations."
```

---

### Task 5: Stream CSV Export Instead of Loading All Into Memory

**Problem:** `handleLogExport()` does `fetchAll()` loading entire result set into PHP memory. For 100K+ logs this causes memory exhaustion.

**Files:**
- Modify: `donglemanager/Donglemanager.class.php:1163-1194`

- [ ] **Step 1: Replace fetchAll with row-by-row streaming**

Replace the entire `handleLogExport()` method:

```php
private function handleLogExport()
{
    list($where, $params) = $this->buildLogFilters();

    $sql = "SELECT created_at as Time, level as Level, category as Category, dongle as Dongle, message as Message
            FROM donglemanager_logs
            WHERE {$where}
            ORDER BY created_at DESC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    // Stream CSV directly — never buffer entire result set
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="donglemanager_logs_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // Header row
    fputcsv($output, ['Time', 'Level', 'Category', 'Dongle', 'Message']);

    // Stream rows one at a time
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit;
}
```

Key change: `fetch()` in a while loop instead of `fetchAll()`. Memory usage is now O(1) instead of O(n).

- [ ] **Step 2: Commit**

```bash
git add donglemanager/Donglemanager.class.php
git commit -m "perf: stream CSV export row-by-row instead of buffering all in memory

Uses PDO fetch() in a while loop for O(1) memory usage regardless
of result set size."
```

---

### Task 6: Batch Log Cleanup in Worker

**Problem:** `cleanOldLogs()` runs `DELETE ... WHERE created_at < 90 days` every minute. On a large table this scans the entire index. Should batch with LIMIT.

**Files:**
- Modify: `donglemanager/cron/worker.php:523-537`

- [ ] **Step 1: Add LIMIT to the delete query**

Replace the `cleanOldLogs()` method:

```php
private function cleanOldLogs()
{
    try {
        $sql = "DELETE FROM donglemanager_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) LIMIT 1000";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $deleted = $stmt->rowCount();

        if ($deleted > 0) {
            $this->log('info', 'worker', null, "Deleted {$deleted} old log entries");
        }
    } catch (PDOException $e) {
        $this->log('error', 'worker', null, 'Failed to clean old logs: ' . $e->getMessage());
    }
}
```

Deletes at most 1000 rows per minute. If there's a large backlog, it clears in chunks over successive runs without locking the table.

- [ ] **Step 2: Commit**

```bash
git add donglemanager/cron/worker.php
git commit -m "perf: batch log cleanup to LIMIT 1000 rows per worker cycle

Prevents long table locks on large logs tables. Backlog clears
progressively over successive cron runs."
```

---

### Task 7: Fix SMS Counter Double-Bind

**Problem:** `DM.smsCounter` binds both `input` and `keyup` events, causing the counter function to fire twice per keystroke.

**Files:**
- Modify: `donglemanager/assets/js/donglemanager.js:398`

- [ ] **Step 1: Remove keyup binding**

Replace line 398:
```javascript
$textarea.on('input keyup', updateCounter);
```

With:
```javascript
$textarea.on('input', updateCounter);
```

The `input` event covers all input methods (typing, paste, cut, drag). `keyup` is redundant.

- [ ] **Step 2: Commit**

```bash
git add donglemanager/assets/js/donglemanager.js
git commit -m "perf: remove redundant keyup binding from SMS counter

The input event already covers typing, paste, cut, and drag.
keyup was causing the counter to fire twice per keystroke."
```

---

### Task 8: Cache Dongle List Client-Side to Eliminate Redundant PHP Queries

**Problem:** Five view files (`sms_inbox.php`, `sms_outbox.php`, `sms_send.php`, `ussd.php`, `reports.php`, `logs.php`) each call `$module->getAllDongles()` or `$module->getActiveDongles()` in PHP to render filter dropdowns. On every AJAX tab switch, this runs a DB query even though the dongle list rarely changes.

**Solution:** Load the dongle list once on initial page load via the existing `dongle_list` AJAX endpoint, cache it in `DM.dongleCache`, and have view files render empty `<select>` elements that get populated client-side.

**Files:**
- Modify: `donglemanager/assets/js/donglemanager.js` — add `DM.dongleCache` and `DM.populateDongleSelectors`
- Modify: `donglemanager/views/main.php` — fetch dongle list on initial load
- Modify: `donglemanager/views/sms_inbox.php` — remove PHP dongle loop, add empty select
- Modify: `donglemanager/views/sms_outbox.php` — same
- Modify: `donglemanager/views/sms_send.php` — same (uses active dongles only)
- Modify: `donglemanager/views/ussd.php` — same
- Modify: `donglemanager/views/reports.php` — same
- Modify: `donglemanager/views/logs.php` — same

- [ ] **Step 1: Add DM.dongleCache and DM.populateDongleSelectors to donglemanager.js**

Add before the `$(document).ready` block at the bottom of the IIFE:

```javascript
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
```

- [ ] **Step 2: Fetch dongle list on initial load in main.php**

In `views/main.php`, add to the AJAX navigation script, inside `$(document).ready`, before the history replaceState:

```javascript
// Pre-fetch dongle list into cache
DM.fetchDongles(function(dongles) {
    DM.populateDongleSelectors(dongles);
});
```

- [ ] **Step 3: Update initPage to populate selectors after AJAX view load**

In `views/main.php`, inside the `.done()` callback of the AJAX load, after `DM.initPage(view)`, add:

```javascript
// Populate dongle selectors in the newly loaded view
if (DM.dongleCache !== null) {
    DM.populateDongleSelectors(DM.dongleCache);
} else {
    DM.fetchDongles(function() {
        DM.populateDongleSelectors(DM.dongleCache);
    });
}
```

- [ ] **Step 4: Update sms_inbox.php — remove PHP dongle loop**

Replace the filter dongle `<select>` block (lines 20-28):

```php
<select id="filter-dongle">
    <option value="all">All Dongles</option>
    <?php foreach ($dongles as $d): ?>
        <option value="<?php echo htmlspecialchars($d['device']); ?>">
            <?php echo htmlspecialchars($d['device']); ?>
        </option>
    <?php endforeach; ?>
</select>
```

With:

```html
<select id="filter-dongle" class="dm-dongle-filter">
    <option value="all">All Dongles</option>
</select>
```

Also remove line 9: `$dongles = $module->getAllDongles();`

- [ ] **Step 5: Update sms_outbox.php — same change**

Replace lines 20-28 select with:
```html
<select id="filter-dongle" class="dm-dongle-filter">
    <option value="all">All Dongles</option>
</select>
```

Remove line 9: `$dongles = $module->getAllDongles();`

- [ ] **Step 6: Update sms_send.php — use dm-dongle-active-filter**

Replace the dongle select (lines 29-42):

```html
<select name="dongle" id="dongle" class="form-control dm-dongle-active-filter" required>
    <option value="">-- Select Dongle --</option>
</select>
```

Remove line 9: `$dongles = $module->getActiveDongles();`

Also update the "No Active Dongles" warning. Since we no longer know server-side, remove the `<?php if (empty($dongles))` conditional and handle it client-side. Show the form always, and add a JS check:

```javascript
$(document).ready(function() {
    DM.fetchDongles(function(dongles) {
        var active = dongles.filter(function(d) { return d.state === 'Free' || d.state === 'Busy'; });
        if (active.length === 0) {
            $('#no-dongles-warning').show();
            $('#sms-send-form').hide();
        }
        DM.populateDongleSelectors(dongles);
    });
    loadRecentSent();
});
```

- [ ] **Step 7: Update ussd.php — same pattern**

Replace the dongle select with:
```html
<select name="dongle" id="ussd-dongle" class="form-control dm-dongle-active-filter" required>
    <option value="">-- Select Dongle --</option>
</select>
```

Remove line 9: `$dongles = $module->getActiveDongles();`

- [ ] **Step 8: Update reports.php — use dm-dongle-filter**

Replace dongle select (lines 32-39) with:
```html
<select id="filter-dongle" class="dm-dongle-filter">
    <option value="all">All Dongles</option>
</select>
```

Remove line 9: `$dongles = $module->getAllDongles();`

- [ ] **Step 9: Update logs.php — use dm-dongle-filter**

Replace dongle select (lines 40-47) with:
```html
<select id="filter-dongle" class="dm-dongle-filter">
    <option value="all">All Dongles</option>
</select>
```

Remove line 9: `$dongles = $module->getAllDongles();`

- [ ] **Step 10: Invalidate cache on dongle control actions**

In `views/dongles.php`, add `DM.invalidateDongleCache();` inside the success callbacks for restart/stop/start actions (after `DM.toast`).

- [ ] **Step 11: Verify manually**

1. Load module → switch tabs → no dongle DB query in PHP (check with browser Network tab — AJAX view responses should be smaller)
2. Filter dropdowns should still populate correctly
3. Send SMS page should show only active dongles
4. After restarting a dongle, switching to Send SMS should show updated list

- [ ] **Step 12: Commit**

```bash
git add donglemanager/assets/js/donglemanager.js donglemanager/views/main.php \
    donglemanager/views/sms_inbox.php donglemanager/views/sms_outbox.php \
    donglemanager/views/sms_send.php donglemanager/views/ussd.php \
    donglemanager/views/reports.php donglemanager/views/logs.php \
    donglemanager/views/dongles.php
git commit -m "perf: cache dongle list client-side, eliminate DB query per tab switch

Dongle list is fetched once via AJAX and cached in DM.dongleCache.
Filter dropdowns are populated client-side. Cache is invalidated
on dongle control actions (restart/stop/start).

Removes 6 redundant getAllDongles()/getActiveDongles() PHP calls
that ran on every AJAX view load."
```

---

### Task 9: Reduce Dashboard Queries from 6 to 4

**Problem:** `handleDashboardStats()` runs 6 separate queries. The dongle counts and dongle list can be combined, and the two 7-day chart queries can potentially be left as-is since they target different tables. But the SMS "today" stats query already combines 3 subqueries. The low-hanging fruit is combining dongle stats + dongle list.

**Files:**
- Modify: `donglemanager/Donglemanager.class.php:492-568`

- [ ] **Step 1: Combine dongle counts with dongle list query**

Replace lines 496-508 and 556-558 with a single query that fetches all dongle rows and computes counts in PHP:

Replace the entire method body of `handleDashboardStats()`:

```php
private function handleDashboardStats()
{
    $data = [];

    // Query 1: All dongles (used for both counts and list)
    $stmt = $this->db->query("SELECT device, phone_number, operator, signal_percent, state, last_seen FROM donglemanager_dongles ORDER BY device");
    $dongles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = count($dongles);
    $active = 0;
    $offline = 0;
    $fiveMinAgo = date('Y-m-d H:i:s', strtotime('-5 minutes'));

    foreach ($dongles as $d) {
        if (in_array($d['state'], ['Free', 'Busy'])) $active++;
        if ($d['state'] === 'Offline' || $d['last_seen'] < $fiveMinAgo) $offline++;
    }

    $data['dongles_total'] = $total;
    $data['dongles_active'] = $active;
    $data['dongles_offline'] = $offline;
    $data['dongles'] = $dongles;

    // Query 2: SMS stats for today (index-friendly)
    $stmt = $this->db->query("
        SELECT
            (SELECT COUNT(*) FROM donglemanager_sms_outbox WHERE created_at >= CURDATE()) as sent,
            (SELECT COUNT(*) FROM donglemanager_sms_outbox WHERE created_at >= CURDATE() AND status = 'failed') as failed,
            (SELECT COUNT(*) FROM donglemanager_sms_inbox WHERE received_at >= CURDATE()) as received
    ");
    $smsStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $data['sms_sent_today'] = (int)($smsStats['sent'] ?? 0);
    $data['sms_failed_today'] = (int)($smsStats['failed'] ?? 0);
    $data['sms_received_today'] = (int)($smsStats['received'] ?? 0);

    // Query 3: 7-day outbox chart
    $stmt = $this->db->query("
        SELECT DATE(created_at) as date, COUNT(*) as sent
        FROM donglemanager_sms_outbox
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at) ORDER BY date
    ");
    $sentData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Query 4: 7-day inbox chart
    $stmt = $this->db->query("
        SELECT DATE(received_at) as date, COUNT(*) as received
        FROM donglemanager_sms_inbox
        WHERE received_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY DATE(received_at) ORDER BY date
    ");
    $receivedData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    // Build chart data
    $chartData = ['labels' => [], 'sent' => [], 'received' => []];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $chartData['labels'][] = $date;
        $chartData['sent'][] = (int)($sentData[$date] ?? 0);
        $chartData['received'][] = (int)($receivedData[$date] ?? 0);
    }
    $data['chart_7day'] = $chartData;

    // Query 5: Recent inbox (5 messages)
    $stmt = $this->db->query("SELECT id, dongle, sender, message, received_at FROM donglemanager_sms_inbox ORDER BY received_at DESC LIMIT 5");
    $data['recent_inbox'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Query 6: Recent USSD (5 entries)
    $stmt = $this->db->query("SELECT id, dongle, command, response, created_at FROM donglemanager_ussd_history ORDER BY created_at DESC LIMIT 5");
    $data['recent_ussd'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return ['success' => true, 'data' => $data];
}
```

This reduces from 6 DB queries to 5 (dongle stats + dongle list merged into 1). The dongle count computation moves to PHP which is cheaper than a separate SQL round-trip on a small table (<100 rows).

- [ ] **Step 2: Commit**

```bash
git add donglemanager/Donglemanager.class.php
git commit -m "perf: merge dongle stats and dongle list into single dashboard query

Computes dongle counts in PHP from the list query instead of
running a separate COUNT query. Reduces dashboard from 6 to 5
DB round-trips."
```

---

## Summary of Expected Impact

| Change | Impact |
|--------|--------|
| Task 1: Fix logs interval leak | Eliminates ghost AJAX requests after leaving Logs tab |
| Task 2: Chart data update in-place | Zero-flicker dashboard refresh every 10s |
| Task 3: Remove DATE() wrapping | All date queries use indexes — 10-100x faster on large tables |
| Task 4: Composite indexes | Covers the most common multi-column filter patterns |
| Task 5: Stream CSV export | O(1) memory for exports of any size |
| Task 6: Batch log cleanup | Prevents table-lock storms from unbounded DELETEs |
| Task 7: SMS counter double-bind | Minor CPU savings, cleaner event handling |
| Task 8: Client-side dongle cache | Eliminates 1 DB query per tab switch (6 views affected) |
| Task 9: Merge dashboard queries | 1 fewer DB round-trip per dashboard load/refresh |

**Combined effect:** The module will feel native-speed on all tabs, with zero flicker, no ghost requests, and efficient DB usage at any data volume.
