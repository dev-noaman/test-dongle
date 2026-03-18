# CLAUDE.md

This file provides guidance to Claude Code when working with code in this repository.

## Project Overview

FreePBX BMO module (`donglemanager`) for managing Huawei GSM USB dongles via chan_dongle. Provides SMS send/receive, USSD commands, dongle monitoring, traffic reports, and system logging — all within the FreePBX admin panel.

## Technology Stack

- **Backend**: PHP 7.4+ (plain PHP, no framework, PDO for database)
- **Framework**: FreePBX BMO (Big Module Object) pattern
- **Database**: MariaDB — tables prefixed `donglemanager_` in FreePBX `asterisk` database
- **Frontend**: Bootstrap 3 (FreePBX built-in) + jQuery + custom CSS
- **Charts**: Chart.js 4 (bundled locally in `assets/vendor/`)
- **Icons**: Font Awesome 6 (bundled locally in `assets/vendor/fontawesome/`)
- **AMI**: Asterisk Manager Interface on 127.0.0.1:5038 via `\FreePBX::astman()` (web) and direct socket (worker)
- **No Composer, no npm, no build step, no CDN** — fully air-gapped capable
- **AJAX Navigation** — Tab switches via AJAX (~30-75ms) instead of full page reload (~5000ms)

## Module Structure

```
donglemanager/                          # → /var/www/html/admin/modules/donglemanager/
├── module.xml                          # Module manifest (rawname=donglemanager, v1.0.0)
├── Donglemanager.class.php             # Main BMO class (~45KB, 23 AJAX handlers)
├── install.php                         # CREATE TABLE for 5 donglemanager_* tables
├── uninstall.php                       # DROP TABLE cleanup
├── page.donglemanager.php              # FreePBX page entry (FREEPBX_IS_AUTH guard)
├── Backup.php / Restore.php            # FreePBX backup/restore handlers
├── functions.inc.php                   # Empty (required by framework)
├── views/
│   ├── main.php                        # View router + AJAX navigation + asset loading + CSRF token
│   ├── dashboard.php                   # Summary cards, 7-day chart, dongle status
│   ├── sms_inbox.php                   # Paginated inbox with filters, bulk actions
│   ├── sms_outbox.php                  # Outbox with status filter, retry
│   ├── sms_send.php                    # Send form with dongle selector, char counter
│   ├── ussd.php                        # USSD send with quick buttons, polling, history
│   ├── dongles.php                     # Card grid with signal bars, start/stop/restart
│   ├── reports.php                     # Bar chart, pie chart, stats table
│   └── logs.php                        # Filterable log viewer, CSV export
├── assets/
│   ├── css/donglemanager.css           # Custom styles (color scheme, signal bars, badges)
│   ├── js/donglemanager.js             # DM namespace: AJAX, toast, auto-refresh, SMS counter
│   └── vendor/
│       ├── chart.umd.min.js            # Chart.js 4.4.7 (~206KB)
│       └── fontawesome/                # Font Awesome 6.5.2
│           ├── css/all.min.css
│           └── webfonts/ (6 font files)
├── includes/
│   ├── AmiClient.php                   # Direct AMI socket client (for cron worker)
│   └── ConfigReader.php                # Reads /etc/freepbx.conf or /etc/amportal.conf
└── cron/
    └── worker.php                      # Background worker (runs every minute via cron)
```

## Database Tables

All in the FreePBX `asterisk` database with `donglemanager_` prefix:

| Table | Purpose |
|-------|---------|
| `donglemanager_dongles` | Per-modem status (device, IMEI, signal, state, counters) |
| `donglemanager_sms_inbox` | Incoming SMS (dongle, sender, message, is_read) |
| `donglemanager_sms_outbox` | Outgoing SMS queue (dongle, destination, status, retry_count) |
| `donglemanager_ussd_history` | USSD commands and responses |
| `donglemanager_logs` | System event log (auto-pruned at 90 days, batch delete LIMIT 1000) |

### Composite Indexes (for filtered queries)

```sql
idx_dongle_received ON donglemanager_sms_inbox(dongle, received_at)
idx_dongle_created ON donglemanager_sms_outbox(dongle, created_at)
idx_status_created ON donglemanager_sms_outbox(status, created_at)
idx_created_level ON donglemanager_logs(created_at, level)
```

## Key Patterns

### BMO Class (Donglemanager.class.php)

- Extends `\FreePBX_Helpers implements \BMO`
- `$db` is `protected` (not private) so `Backup.php`/`Restore.php` subclasses can access it
- `getAllDongles()` and `getActiveDongles()` are `public` — called from view files
- `ajaxRequest()` whitelists 23 AJAX commands
- `ajaxHandler()` routes to `handle*()` private methods with try/catch
- CSRF validation on all POST (write) commands via `validateCsrfToken()`
- All queries use PDO prepared statements with `?` placeholders
- `sendAmiCommand()` wraps `\FreePBX::astman()->send_request()` with null-check

### Internal Helpers (Donglemanager.class.php)

These private helpers reduce duplication across AJAX handlers:

| Helper | Purpose |
|--------|---------|
| `extractIds($key)` | Sanitize integer ID arrays from `$_POST` (used by 5 SMS bulk-action handlers) |
| `parsePagination($defaultPerPage)` | Parse `page`/`per_page` from `$_GET`, return `[$page, $perPage, $offset]` |
| `paginatedResponse($rows, $total, $page, $perPage)` | Build standard `{rows, total, page, per_page, pages}` envelope |
| `buildLogFilters()` | Build `[$where, $params]` for log queries (shared by list + CSV export) |
| `handleDongleControl($amiCommand, $verb)` | Unified restart/stop/start handler |

### AJAX Pattern (JavaScript)

```javascript
// GET requests
DM.ajax('dashboard_stats', {}, function(response) { ... });

// POST requests (auto-includes CSRF token)
DM.post('sms_send', { dongle: 'dongle0', destination: '+123', message: 'Hi' }, callback);
```

All AJAX goes through FreePBX's `ajax.php?module=donglemanager&command=...`

### Shared JS Utilities (donglemanager.js)

- `DM.getSelectedIds(selector)` — get checked checkbox IDs (used by inbox + outbox bulk actions)
- `DM.escapeHtml(str)` — XSS-safe HTML escaping; **always use when inserting user data via `.html()`**
- `DM.buildPagination()` — uses event delegation (`click.dmPagination`) for page links
- `DM.loadView(view)` — AJAX navigation to switch views without full page reload
- `DM.startAutoRefresh(command, callback, intervalMs, paramsFn)` — managed auto-refresh with optional dynamic params; returns interval ID
- `DM.stopAutoRefresh(id)` — stop specific auto-refresh interval
- `DM.stopAllAutoRefresh()` — stop all auto-refresh intervals (called on AJAX navigation)

### Client-Side Dongle Caching

Dongle list is fetched once on initial load and cached in `DM.dongleCache`. Filter dropdowns are populated client-side:

```javascript
// Fetch and cache dongle list (returns cached data on subsequent calls)
DM.fetchDongles(function(dongles) {
    DM.populateDongleSelectors(dongles);
});

// Invalidate cache after dongle control actions
DM.invalidateDongleCache();
```

**Filter dropdown classes:**
- `.dm-dongle-filter` — Populated with all dongles (for filter dropdowns)
- `.dm-dongle-active-filter` — Populated with only Free/Busy dongles (for send forms)

View files render empty `<select>` elements with these classes; `main.php` populates them after AJAX view load.

### SMS Character Counter

Uses only the `input` event (not `input keyup`) to avoid double-firing:

```javascript
$textarea.on('input', updateCounter);  // CORRECT: fires once per change
$textarea.on('input keyup', updateCounter);  // WRONG: fires twice per keystroke
```

The `input` event covers all input methods: typing, paste, cut, drag.

### AJAX Navigation (main.php)

The module uses client-side AJAX navigation for tab switching. Key points:

1. **No full page reloads** — Nav clicks are intercepted and content is loaded via AJAX
2. **View endpoint** — `?ajax_view=1` parameter returns only view content (no assets/navbar)
3. **History support** — Browser back/forward buttons work correctly
4. **Loading indicator** — Shows `.dm-nav-loading` during AJAX requests

```javascript
// How tab switching works:
$('#dm-main-nav').on('click', 'a[data-view]', function(e) {
    e.preventDefault();
    DM.loadView($(this).data('view'));  // AJAX load
});
```

**Performance:** Tab switches take ~30-75ms vs ~5000ms for full page reload (99% improvement)

### Asset Loading Order (main.php)

**CRITICAL:** Script loading order matters for inline scripts in view files:

```html
<!-- CORRECT: donglemanager.js loads immediately (inline scripts need DM namespace) -->
<script src="modules/donglemanager/assets/js/donglemanager.js"></script>
<script src="modules/donglemanager/assets/vendor/chart.umd.min.js" defer></script>

<!-- WRONG: defer on donglemanager.js breaks inline scripts -->
<script src="modules/donglemanager/assets/js/donglemanager.js" defer></script>  <!-- DON'T DO THIS -->
```

- `donglemanager.js` — Must load immediately (defines `DM` namespace used by inline view scripts)
- `chart.umd.min.js` — Can be deferred (206KB, only needed for charts)
- Font Awesome fonts — Preloaded for faster rendering

## Performance Patterns

### Index-Friendly Date Queries

**CRITICAL:** Never wrap date columns in `DATE()` — it prevents index usage:

```php
// WRONG: Full table scan
$sql = "WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?";

// CORRECT: Uses index on created_at
$sql = "WHERE created_at >= ? AND created_at <= ?";
$params = [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'];
```

This pattern is used in:
- `handleSmsListInbox()` — date filters
- `handleSmsListOutbox()` — date filters
- `buildLogFilters()` — date filters
- `handleReportSummary()` — date range
- `handleReportChart()` — date range
- `handleReportDongleStats()` — date range

### Composite Indexes

Defined in `install.php` for common filter patterns:

```sql
ALTER TABLE donglemanager_sms_inbox ADD INDEX idx_dongle_received (dongle, received_at)
ALTER TABLE donglemanager_sms_outbox ADD INDEX idx_dongle_created (dongle, created_at)
ALTER TABLE donglemanager_sms_outbox ADD INDEX idx_status_created (status, created_at)
ALTER TABLE donglemanager_logs ADD INDEX idx_created_level (created_at, level)
```

### Streaming CSV Export

Large exports must stream row-by-row to avoid memory exhaustion:

```php
// CORRECT: O(1) memory
$stmt = $this->db->prepare($sql);
$stmt->execute($params);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

// WRONG: O(n) memory
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) { ... }
```

### Batch Delete Pattern

Log cleanup uses `LIMIT 1000` to prevent long table locks:

```php
$sql = "DELETE FROM donglemanager_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY) LIMIT 1000";
```

### Chart Update In-Place

Dashboard chart updates data without destroy/recreate to eliminate flicker:

```javascript
// Reuse existing chart instance
if (DM.dashboardChart && DM.dashboardChart.canvas === ctx) {
    DM.dashboardChart.data.labels = chartData.labels;
    DM.dashboardChart.data.datasets[0].data = chartData.sent;
    DM.dashboardChart.data.datasets[1].data = chartData.received;
    DM.dashboardChart.update('none'); // No animation
    return;
}
```

### Background Worker (cron/worker.php)

Runs every minute. 6-step cycle:
1. Query `DongleShowDevices` via `$this->ami->readResponse()` → update dongle statuses
2. Process SMS outbox queue (up to 5, rate limit 10/min/dongle)
3. Listen for AMI events for 5 seconds (DongleNewSMS, DongleNewUSSD, DongleStatus)
4. Timeout checks (SMS: 120s, USSD: 30s)
5. Auto-restart dongles in Error state > 5 minutes
6. Batch delete logs older than 90 days (LIMIT 1000 per cycle)

Uses PID file locking (`/tmp/dongle-worker.pid`). Has its own DB + AMI connections (runs outside FreePBX framework).

Worker logging is minimal by design — only errors, warnings, and meaningful events are logged to the database. Per-step start/end noise is suppressed to avoid filling the logs table (~1.5M rows/90 days if verbose).

## Security

- **Auth**: FreePBX native (`FREEPBX_IS_AUTH`) — no module-level users/roles
- **CSRF**: Token generated per session, validated on all POST AJAX commands
- **SQL**: All user input via PDO prepared statements — zero raw interpolation
- **XSS**: PHP views use `htmlspecialchars()`, JS uses `DM.escapeHtml()` — expandable message content must also escape via `DM.escapeHtml()` before `.html()` insertion
- **AMI**: Credentials auto-detected from FreePBX config (`AMPMGRUSER`/`AMPMGRPASS`/`ASTMANAGERPORT`), never exposed to frontend
- **Rate limit**: 10 SMS per minute per dongle (configurable)
- **SMS retry**: Max 3 attempts per message

## AMI Commands Used

| Command | Purpose |
|---------|---------|
| `DongleShowDevices` | List all dongles with status/signal/IMEI |
| `DongleSendSMS` | Send SMS via specific dongle |
| `DongleSendUSSD` | Send USSD command |
| `DongleRestart` / `DongleStop` / `DongleStart` | Dongle control |

## Installation

```bash
cp -r donglemanager /var/www/html/admin/modules/
chown -R asterisk:asterisk /var/www/html/admin/modules/donglemanager
fwconsole ma install donglemanager
fwconsole reload

# Set up cron for background worker
echo "* * * * * php /var/www/html/admin/modules/donglemanager/cron/worker.php >> /var/log/dongle-worker.log 2>&1" | crontab -u asterisk -
```

## Shell Script Conventions (from parent repo)

- Heredocs writing Asterisk dialplan must use **quoted delimiters** (`cat << 'EOF'`)
- Udev rules use `MODE="0660"` — all runtime `chmod` on `/dev/ttyUSB*` must use `660`
- Config files use `644` — never set execute bit on config files
- No `chmod 666` or `chmod 777` anywhere

## Permission Conventions

- `config.php` (if generated): `chmod 640` readable only by www-data/asterisk
- Module files: owned by `asterisk:asterisk`
- Worker PID file: `/tmp/dongle-worker.pid`
- Worker log: `/var/log/dongle-worker.log`

<!-- MANUAL ADDITIONS START -->
<!-- MANUAL ADDITIONS END -->
