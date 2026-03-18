# Tasks: FreePBX Dongle SMS Manager Module

**Input**: Design documents from `/specs/001-freepbx-dongle-module/`
**Prerequisites**: plan.md, spec.md, data-model.md, contracts/ajax-api.md, research.md, quickstart.md

**Tests**: Not explicitly requested — test tasks omitted.

**Organization**: Tasks grouped by user story. 10 user stories (4x P1, 4x P2, 2x P3).

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2)
- Paths relative to `donglemanager/` module directory

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Create module directory structure and bundle vendor assets

- [x] T001 Create module directory structure: `donglemanager/` with subdirectories `views/`, `assets/css/`, `assets/js/`, `assets/vendor/fontawesome/css/`, `assets/vendor/fontawesome/webfonts/`, `includes/`, `cron/`
- [x] T002 Create `donglemanager/module.xml` with rawname=donglemanager, version=1.0.0, category=Admin, FreePBX 16+ dependency, PHP 7.4+ dependency, menuitems entry "Dongle Manager", supported backup/restore
- [x] T003 [P] Download and bundle Chart.js 4 to `donglemanager/assets/vendor/chart.umd.min.js`
- [x] T004 [P] Download and bundle Font Awesome 6 CSS and webfonts to `donglemanager/assets/vendor/fontawesome/`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core BMO skeleton, database schema, shared includes, and base UI framework

**CRITICAL**: No user story work can begin until this phase is complete

- [x] T005 Create `donglemanager/install.php` with CREATE TABLE IF NOT EXISTS statements for all 5 tables: `donglemanager_dongles`, `donglemanager_sms_inbox`, `donglemanager_sms_outbox`, `donglemanager_ussd_history`, `donglemanager_logs` — including all indexes per data-model.md
- [x] T006 [P] Create `donglemanager/uninstall.php` with DROP TABLE IF EXISTS for all 5 tables
- [x] T007 [P] Create `donglemanager/includes/ConfigReader.php` — static class that reads `/etc/freepbx.conf` (primary) or `/etc/amportal.conf` (fallback) to extract AMPDBHOST, AMPDBUSER, AMPDBPASS, AMPMGRUSER, AMPMGRPASS
- [x] T008 [P] Create `donglemanager/includes/AmiClient.php` — AMI socket client class with methods: `connect()`, `login()`, `sendAction($action, $params)`, `readResponse()`, `readEvents($timeoutSec)`, `disconnect()` — for use by background worker only
- [x] T009 Create `donglemanager/Donglemanager.class.php` — BMO class skeleton: `namespace FreePBX\modules`, extends `\FreePBX_Helpers implements \BMO`, constructor with `$this->db = $freepbx->Database()`, empty `install()`, `uninstall()`, `backup()`, `restore()`, `doConfigPageInit()`, `showPage()` that includes views/main.php, `ajaxRequest()` whitelist for all 21 commands per contracts/ajax-api.md, `ajaxHandler()` router with switch on `$_REQUEST['command']`
- [x] T010 [P] Create `donglemanager/page.donglemanager.php` — guard with `FREEPBX_IS_AUTH` check, instantiate module via `\FreePBX::Donglemanager()`, call `showPage()`
- [x] T011 [P] Create `donglemanager/functions.inc.php` — empty file (required by FreePBX framework auto-loader)
- [x] T012 [P] Create `donglemanager/views/main.php` — view router: read `$_REQUEST['view']` (default: `dashboard`), include corresponding view file from `views/` directory, load module CSS and JS assets via FreePBX asset loading
- [x] T013 [P] Create `donglemanager/assets/css/donglemanager.css` — base styles: color scheme variables (primary #4361ee, success #2ec4b6, warning #ff9f1c, danger #e63946), sidebar sub-navigation styling, card layout (border-radius 12px, box-shadow), signal strength bar component (green >60%, yellow 30-60%, red <30%), status badge styles (Active green, Busy blue, Offline red, Init yellow, Error red), responsive grid for dongle cards, table styling (striped rows, hover), toast notification styles
- [x] T014 [P] Create `donglemanager/assets/js/donglemanager.js` — base JavaScript: `DM` namespace object with methods: `ajax(command, data, callback)` wrapper for FreePBX AJAX calls, `toast(message, type)` notification, `startAutoRefresh(command, callback, intervalMs)`, `stopAutoRefresh(id)`, `formatDate(datetime)`, `buildPagination(total, page, perPage, callback)`, `buildDongleSelector(dongles, options)` dropdown builder, `escapeHtml(str)` for XSS prevention
- [x] T015 [P] Create `donglemanager/Backup.php` and `donglemanager/Restore.php` — backup handler dumps all 5 `donglemanager_*` tables; restore handler imports them

**Checkpoint**: Module installs via `fwconsole ma install donglemanager` — shows empty page, tables created, menu visible

---

## Phase 3: User Story 1 - Install and Configure Module (Priority: P1) MVP

**Goal**: Module installs cleanly, auto-detects credentials, detects connected dongles, shows menu item

**Independent Test**: Run `fwconsole ma install donglemanager && fwconsole reload`, navigate to Dongle Manager in admin menu, verify tables exist and dongles detected

### Implementation for User Story 1

- [x] T016 [US1] Implement `install()` method in `donglemanager/Donglemanager.class.php` — on first page load, use `\FreePBX::astman()` to send `DongleShowDevices`, parse response events, INSERT/UPDATE `donglemanager_dongles` table with detected dongle data (device, IMEI, IMSI, phone, operator, signal, state)
- [x] T017 [US1] Implement `getActionBar()` and sidebar navigation rendering in `donglemanager/Donglemanager.class.php` — return sub-menu links: Dashboard, SMS (Inbox/Outbox/Send), USSD, Dongles, Reports, Logs — with unread inbox count badge and active/total dongle badge
- [x] T018 [US1] Implement `sidebar_counts` AJAX handler in `donglemanager/Donglemanager.class.php` — query unread inbox count and active/total dongle counts, return per contracts/ajax-api.md

**Checkpoint**: Module installed, menu visible with sub-navigation, dongles detected and stored in DB

---

## Phase 4: User Story 2 - Monitor Dongle Status on Dashboard (Priority: P1)

**Goal**: Dashboard shows summary cards, 7-day chart, dongle status list, recent activity — auto-refreshes every 10s

**Independent Test**: Open dashboard with dongles connected, verify all stats display, wait 10s for auto-refresh

### Implementation for User Story 2

- [x] T019 [US2] Implement `dashboard_stats` AJAX handler in `donglemanager/Donglemanager.class.php` — query dongles (total/active/offline counts), today's SMS stats (sent/received/failed from outbox/inbox), 7-day chart data (daily sent/received grouped by DATE), dongle list with signal/state, recent 5 inbox messages, recent 5 USSD entries
- [x] T020 [US2] Create `donglemanager/views/dashboard.php` — Row 1: 4 summary cards (total dongles, received today, sent today, failed today) with color accents. Row 2: left 2/3 Chart.js line chart container (id=chart7day), right 1/3 dongle status compact cards. Row 3: left 1/2 recent inbox table, right 1/2 recent USSD table. All content loaded via AJAX on page load.
- [x] T021 [US2] Add dashboard Chart.js initialization in `donglemanager/assets/js/donglemanager.js` — `DM.initDashboardChart(canvasId, chartData)` renders 7-day line chart with two datasets (sent=blue, received=teal), tooltips, responsive sizing. `DM.updateDashboard(data)` refreshes all DOM elements with new stats.
- [x] T022 [US2] Add dashboard auto-refresh in `donglemanager/assets/js/donglemanager.js` — on dashboard page load, call `DM.startAutoRefresh('dashboard_stats', DM.updateDashboard, 10000)`, destroy chart instance before re-render to prevent memory leaks

**Checkpoint**: Dashboard fully functional with live data, auto-refreshing stats and chart

---

## Phase 5: User Story 7 - Background Worker (Priority: P1)

**Goal**: Cron worker updates dongle statuses, sends queued SMS, captures incoming events, handles timeouts, auto-restarts, cleans logs

**Independent Test**: Run `php donglemanager/cron/worker.php` manually, verify dongle statuses update in DB, queue a test SMS and verify it sends

### Implementation for User Story 7

- [ ] T023 [US7] Create `donglemanager/cron/worker.php` — main execution: PID file lock (`/tmp/dongle-worker.pid`), load ConfigReader for credentials, create PDO connection to asterisk DB, create AmiClient instance, connect to AMI, then execute steps T024-T029 in order, finally release PID lock
- [ ] T024 [US7] Implement worker step 1 in `donglemanager/cron/worker.php` — send `DongleShowDevices` via AmiClient, parse each `DongleDeviceEntry` event, UPDATE existing dongles (signal, state, operator, gsm_status, counters, last_seen=NOW), INSERT new dongles, mark dongles not in response as offline (state='Offline' if last_seen > 5 min ago)
- [ ] T025 [US7] Implement worker step 2 in `donglemanager/cron/worker.php` — SELECT from `donglemanager_sms_outbox` WHERE status='queued' ORDER BY created_at LIMIT 5, for each: check rate limit (10/min/dongle via COUNT in last 60s), send `DongleSendSMS` via AmiClient, UPDATE status to 'sending', log errors to `donglemanager_logs`
- [ ] T026 [US7] Implement worker step 3 in `donglemanager/cron/worker.php` — call `AmiClient::readEvents(5)` to listen for 5 seconds, handle: `DongleNewSMS` → INSERT into `donglemanager_sms_inbox`; `DongleNewUSSD` → UPDATE matching `donglemanager_ussd_history` with response+status='received'; `DongleStatus` → UPDATE dongle state
- [ ] T027 [US7] Implement worker step 4 in `donglemanager/cron/worker.php` — UPDATE `donglemanager_sms_outbox` SET status='failed', error='Send timeout' WHERE status='sending' AND created_at < NOW() - 120 seconds; UPDATE `donglemanager_ussd_history` SET status='timeout' WHERE status='sent' AND created_at < NOW() - 30 seconds
- [ ] T028 [US7] Implement worker step 5 in `donglemanager/cron/worker.php` — SELECT dongles WHERE state='Error' AND last_seen < NOW() - 300 seconds, send `DongleRestart` for each, log action to `donglemanager_logs`
- [ ] T029 [US7] Implement worker step 6 in `donglemanager/cron/worker.php` — DELETE FROM `donglemanager_logs` WHERE created_at < NOW() - INTERVAL 90 DAY
- [ ] T030 [US7] Add structured logging throughout worker in `donglemanager/cron/worker.php` — INSERT into `donglemanager_logs` (category='worker') for: worker start/stop, dongle status changes, SMS sent/failed, events captured, errors encountered. Also echo to stdout for cron log.

**Checkpoint**: Worker runs, updates dongle statuses, processes SMS queue, captures incoming SMS/USSD events, handles timeouts

---

## Phase 6: User Story 3 - Send SMS via Specific Dongle (Priority: P1)

**Goal**: User selects dongle, enters destination and message, sends SMS with character counter and part calculation

**Independent Test**: Send an SMS via the form, verify it appears in outbox as 'queued', worker picks it up and sends

### Implementation for User Story 3

- [ ] T031 [US3] Implement `sms_send` AJAX handler in `donglemanager/Donglemanager.class.php` — validate destination (non-empty, max 30 chars) and message (non-empty), verify dongle is active (state='Free' or 'Busy'), check rate limit (10/min/dongle), INSERT into `donglemanager_sms_outbox` with status='queued', log to `donglemanager_logs` (category='sms'), return `{"success": true, "data": {"id": N}}`
- [ ] T032 [US3] Create `donglemanager/views/sms_send.php` — dongle selector dropdown (only active dongles, format: "dongle0 — +1234567890 (Operator)"), destination phone input, message textarea with character counter below, send button (disabled when no active dongles), warning banner when no dongles active. Below form: table of last 10 sent messages from outbox.
- [ ] T033 [US3] Implement SMS character counter in `donglemanager/assets/js/donglemanager.js` — `DM.smsCounter(textareaId, counterDisplayId)`: detect if message is GSM-7 or UCS-2, calculate chars used / max chars (160/153 for GSM-7, 70/67 for UCS-2), show "X / Y characters (Z SMS parts)", update on each keyup
- [ ] T034 [US3] Add send SMS form submission handler in `donglemanager/assets/js/donglemanager.js` — on form submit: collect dongle, destination, message; call `DM.ajax('sms_send', ...)`, show success/error toast, reload recent sent table, clear form on success

**Checkpoint**: Can send SMS from the UI, message queued in outbox, worker delivers it

---

## Phase 7: User Story 4 - SMS Inbox (Priority: P2)

**Goal**: Paginated, filterable inbox with bulk actions (mark read/unread, delete) and row expansion

**Independent Test**: View inbox with messages, apply filters, use bulk actions, expand rows

### Implementation for User Story 4

- [ ] T035 [P] [US4] Implement `sms_list_inbox` AJAX handler in `donglemanager/Donglemanager.class.php` — build query with optional WHERE clauses for dongle, date_from, date_to, search (LIKE on sender and message), ORDER BY received_at DESC, paginate with LIMIT/OFFSET, return paginated response per contracts
- [ ] T036 [P] [US4] Implement `sms_mark_read`, `sms_mark_unread`, `sms_delete_inbox` AJAX handlers in `donglemanager/Donglemanager.class.php` — validate ids[] array (integers only), execute UPDATE/DELETE with IN clause using prepared statements, return count of affected rows
- [ ] T037 [US4] Create `donglemanager/views/sms_inbox.php` — filter bar: dongle dropdown (all+each), date from/to inputs, search text input, Apply button. Table: checkbox column, time, sender, dongle, message (truncated 60 chars), read/unread badge. Unread rows have left blue border. Action buttons: Mark Read, Mark Unread, Delete Selected. Pagination below table.
- [ ] T038 [US4] Add inbox JS logic in `donglemanager/assets/js/donglemanager.js` — `DM.initInbox()`: load inbox via AJAX on page load, handle filter apply, bulk checkbox select/deselect all, mark read/unread/delete actions, row click to expand/collapse full message text, pagination click handler

**Checkpoint**: Inbox fully functional with filters, pagination, bulk actions, row expansion

---

## Phase 8: User Story 5 - SMS Outbox (Priority: P2)

**Goal**: Paginated outbox with status filters, retry failed messages, delete

**Independent Test**: View outbox with mixed statuses, filter by status, retry failed messages, expand rows

### Implementation for User Story 5

- [ ] T039 [P] [US5] Implement `sms_list_outbox` AJAX handler in `donglemanager/Donglemanager.class.php` — build query with optional WHERE for dongle, status, date_from, date_to, search (LIKE on destination and message), ORDER BY created_at DESC, paginate
- [ ] T040 [P] [US5] Implement `sms_retry` and `sms_delete_outbox` AJAX handlers in `donglemanager/Donglemanager.class.php` — retry: UPDATE status='queued' WHERE id IN (...) AND status='failed' AND retry_count < 3, increment retry_count; delete: DELETE WHERE id IN (...)
- [ ] T041 [US5] Create `donglemanager/views/sms_outbox.php` — filter bar: dongle dropdown, status dropdown (All/Queued/Sending/Sent/Failed), date range, search input. Table: checkbox, time (created_at or sent_at), destination, dongle, message truncated, status badge (queued=yellow, sending=blue, sent=green, failed=red). Actions: Retry Failed, Delete Selected.
- [ ] T042 [US5] Add outbox JS logic in `donglemanager/assets/js/donglemanager.js` — `DM.initOutbox()`: load outbox, handle filters, bulk actions, retry with confirmation, row click to expand full message + error text for failed

**Checkpoint**: Outbox fully functional with status filters, retry, delete, expansion

---

## Phase 9: User Story 6 - USSD Send and History (Priority: P2)

**Goal**: Send USSD commands with quick buttons, poll for response, display history

**Independent Test**: Send a USSD code, see spinner, get response (or timeout), verify in history table

### Implementation for User Story 6

- [ ] T043 [US6] Implement `ussd_send`, `ussd_check`, `ussd_history` AJAX handlers in `donglemanager/Donglemanager.class.php` — send: validate dongle active + command non-empty, send `DongleSendUSSD` via `\FreePBX::astman()`, INSERT into `donglemanager_ussd_history` with status='sent', return record ID. check: SELECT status+response by ID. history: paginated list filtered by dongle.
- [ ] T044 [US6] Create `donglemanager/views/ussd.php` — top section: dongle selector (active only), USSD command input, quick buttons (*100#, *101#, *102#), Send button, response display area (initially hidden). Bottom section: USSD history table with columns: time, dongle, command, response (truncated 80 chars), status badge. Filter by dongle. Pagination.
- [ ] T045 [US6] Add USSD JS logic in `donglemanager/assets/js/donglemanager.js` — `DM.initUssd()`: quick button click fills input, form submit sends USSD and starts polling (`DM.ajax('ussd_check', {id: N})` every 2 seconds), show spinner during poll, display response when status changes to 'received', stop polling on 'timeout'/'failed' or after 30s, click row in history to show full response in modal

**Checkpoint**: USSD send, poll, response display, and history all working

---

## Phase 10: User Story 8 - Dongle Management (Priority: P2)

**Goal**: Card grid showing all dongle details with restart/stop/start controls, auto-refresh every 15s

**Independent Test**: View dongle cards with correct status, signal bars, and hardware info; restart a dongle

### Implementation for User Story 8

- [ ] T046 [US8] Implement `dongle_list`, `dongle_restart`, `dongle_stop`, `dongle_start`, `dongle_refresh` AJAX handlers in `donglemanager/Donglemanager.class.php` — list: SELECT all from donglemanager_dongles. restart/stop/start: validate device exists, send `DongleRestart`/`DongleStop`/`DongleStart` via `\FreePBX::astman()`, log to donglemanager_logs. refresh: send `DongleShowDevices`, update DB, return updated list.
- [ ] T047 [US8] Create `donglemanager/views/dongles.php` — top summary bar ("5 Dongles: 3 Active, 1 Busy, 1 Offline" with badges). Responsive card grid (3 cols desktop, 2 tablet, 1 mobile). Each card: device name + state badge header, phone + operator subtitle, signal bar with percentage, IMEI, IMSI, GSM status, model, SMS in/out counts, queue count, last seen. Action buttons: Restart, Stop (or Start if stopped), Refresh.
- [ ] T048 [US8] Add dongle cards JS logic in `donglemanager/assets/js/donglemanager.js` — `DM.initDongles()`: load cards via AJAX, render signal bar with color-coding (green >60%, yellow 30-60%, red <30%), handle restart/stop/start button clicks with confirmation, auto-refresh every 15s, update cards in-place without flicker

**Checkpoint**: Dongle cards display all info, controls work, auto-refresh active

---

## Phase 11: User Story 9 - Reports and Analytics (Priority: P3)

**Goal**: SMS traffic reports with filters, summary cards, daily bar chart, per-dongle pie chart, stats table

**Independent Test**: Set date range, verify summary numbers match DB, check charts render, sort stats table

### Implementation for User Story 9

- [ ] T049 [US9] Implement `report_summary`, `report_chart`, `report_dongle_stats` AJAX handlers in `donglemanager/Donglemanager.class.php` — summary: COUNT inbox+outbox in date range with optional dongle filter, calculate success rate. chart: GROUP BY DATE for daily sent/received, GROUP BY dongle for per-dongle totals. dongle_stats: per-dongle sent/received/failed/success% for date range.
- [ ] T050 [US9] Create `donglemanager/views/reports.php` — filter bar: date_from, date_to, dongle dropdown, Apply button. Summary row: 5 cards (Total, Sent, Received, Failed, Success Rate%). Chart row: left 2/3 bar chart container (id=chartDaily), right 1/3 doughnut chart container (id=chartDongle). Below: dongle stats table with sortable columns (dongle, phone, operator, sent, received, failed, success%), totals row.
- [ ] T051 [US9] Add reports Chart.js logic in `donglemanager/assets/js/donglemanager.js` — `DM.initReports()`: on Apply click, fetch summary+chart+stats data via 3 parallel AJAX calls, `DM.renderDailyChart(canvasId, data)` bar chart (sent=blue, received=teal), `DM.renderDongleChart(canvasId, data)` doughnut chart with per-dongle colors, update summary cards and stats table
- [ ] T052 [US9] Add sortable table functionality in `donglemanager/assets/js/donglemanager.js` — `DM.sortableTable(tableId)`: click column header to sort ascending/descending, maintain totals row at bottom

**Checkpoint**: Reports page with all charts, filters, sortable table working

---

## Phase 12: User Story 10 - System Logs (Priority: P3)

**Goal**: Filterable log viewer with CSV export and auto-refresh toggle

**Independent Test**: View logs, apply filters, export CSV, toggle auto-refresh

### Implementation for User Story 10

- [ ] T053 [US10] Implement `log_list` and `log_export` AJAX handlers in `donglemanager/Donglemanager.class.php` — list: paginated query with optional WHERE for level, category, dongle, date range, search (LIKE on message), ORDER BY created_at DESC, per_page=50. export: same query without pagination, output CSV with headers (Content-Type: text/csv, Content-Disposition: attachment).
- [ ] T054 [US10] Create `donglemanager/views/logs.php` — filter bar: level dropdown (All/Info/Warning/Error), category dropdown (All/sms/ussd/dongle/system/worker), dongle dropdown, date range, search input, auto-refresh toggle button, Apply button. Table: time, level badge (info=blue, warning=yellow, error=red), category, dongle (or "—"), message. Pagination (50/page). Export CSV button.
- [ ] T055 [US10] Add logs JS logic in `donglemanager/assets/js/donglemanager.js` — `DM.initLogs()`: load logs with filters, pagination handler, auto-refresh toggle (start/stop 10s interval), CSV export button triggers `log_export` AJAX as file download

**Checkpoint**: Logs page fully functional with filters, export, auto-refresh

---

## Phase 13: Polish & Cross-Cutting Concerns

**Purpose**: Security hardening, error handling, final integration

- [ ] T056 Add CSRF token generation and validation for all POST AJAX handlers in `donglemanager/Donglemanager.class.php` — generate token in session on page load, include in all POST requests via JS, validate in ajaxHandler before processing POST commands
- [ ] T057 Add comprehensive error handling across all AJAX handlers in `donglemanager/Donglemanager.class.php` — wrap all handler methods in try/catch, log PDOException and AMI errors to donglemanager_logs, return `{"success": false, "message": "..."}` with user-friendly messages (never expose raw PHP errors)
- [ ] T058 [P] Audit all PDO queries in `donglemanager/Donglemanager.class.php` and `donglemanager/cron/worker.php` for prepared statement usage — ensure zero raw variable interpolation in SQL, all user input via `?` or named placeholders
- [ ] T059 [P] Audit all HTML output in views for XSS prevention — ensure all dynamic content uses `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`, verify JS escapeHtml used for AJAX-rendered content
- [ ] T060 [P] Add AMI connection failure graceful handling in `donglemanager/Donglemanager.class.php` — when `\FreePBX::astman()` returns null/false, show "AMI unavailable" warning on dashboard, disable send/USSD/control actions with clear message
- [ ] T061 Run quickstart.md validation — manual install test on FreePBX server: install module, verify all pages load, send test SMS, check worker runs, verify backup/restore

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies — can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion — BLOCKS all user stories
- **US1 (Phase 3)**: Depends on Foundational — first MVP increment
- **US2 (Phase 4)**: Depends on US1 (needs sidebar + menu structure)
- **US7 (Phase 5)**: Depends on Foundational — independent of US2 but needed by US3
- **US3 (Phase 6)**: Depends on US7 (worker sends queued SMS) and US1 (dongle detection)
- **US4-US8 (Phases 7-10)**: Depend on Foundational; US4/US5 benefit from US7 (incoming SMS)
- **US9-US10 (Phases 11-12)**: Depend on Foundational; benefit from existing data
- **Polish (Phase 13)**: Depends on all user stories being complete

### User Story Dependencies

```
Phase 1 (Setup)
  └── Phase 2 (Foundational)
        ├── Phase 3 (US1: Install) ─── Phase 4 (US2: Dashboard)
        │                                    │
        ├── Phase 5 (US7: Worker) ──── Phase 6 (US3: Send SMS)
        │
        ├── Phase 7 (US4: Inbox)     ← benefits from US7 but not blocked
        ├── Phase 8 (US5: Outbox)    ← benefits from US7 but not blocked
        ├── Phase 9 (US6: USSD)      ← benefits from US7 but not blocked
        ├── Phase 10 (US8: Dongles)
        ├── Phase 11 (US9: Reports)
        └── Phase 12 (US10: Logs)
              └── Phase 13 (Polish)
```

### Within Each User Story

- AJAX handlers before views (data layer before presentation)
- Views before JS logic (DOM structure before behavior)
- Core functionality before polish (send before counter)

### Parallel Opportunities

- T003, T004 (vendor bundling) can run in parallel
- T006, T007, T008, T010, T011, T012, T013, T014, T015 can all run in parallel after T005
- US4 through US10 phases can be worked on in parallel after Foundational
- T035/T036, T039/T040 can run in parallel within their stories
- All Polish tasks (T056-T061) marked [P] can run in parallel

---

## Parallel Example: Phase 2 (Foundational)

```bash
# After T005 (install.php) completes, launch in parallel:
Task T006: "Create uninstall.php"
Task T007: "Create includes/ConfigReader.php"
Task T008: "Create includes/AmiClient.php"
Task T010: "Create page.donglemanager.php"
Task T011: "Create functions.inc.php"
Task T012: "Create views/main.php"
Task T013: "Create assets/css/donglemanager.css"
Task T014: "Create assets/js/donglemanager.js"
Task T015: "Create Backup.php and Restore.php"

# Then T009 (Donglemanager.class.php) which references all the above
```

## Parallel Example: P2 Stories (after Foundational)

```bash
# All P2 stories can be worked in parallel:
Phase 7 (US4: Inbox)   — Developer A
Phase 8 (US5: Outbox)  — Developer A (after inbox, shared patterns)
Phase 9 (US6: USSD)    — Developer B
Phase 10 (US8: Dongles) — Developer C
```

---

## Implementation Strategy

### MVP First (P1 Stories Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: US1 (Install & Configure)
4. Complete Phase 4: US2 (Dashboard)
5. Complete Phase 5: US7 (Worker)
6. Complete Phase 6: US3 (Send SMS)
7. **STOP and VALIDATE**: Module installs, shows dashboard with live dongle data, sends SMS, worker processes queue
8. Deploy MVP

### Incremental Delivery

1. Setup + Foundational → Module skeleton
2. US1 + US2 → Dashboard monitoring MVP
3. US7 + US3 → SMS sending capability
4. US4 + US5 → Full SMS management (inbox/outbox)
5. US6 → USSD commands
6. US8 → Dongle control
7. US9 + US10 → Reports and logging
8. Polish → Production hardening

---

## Notes

- All file paths are relative to `donglemanager/` module root directory
- On production server, module lives at `/var/www/html/admin/modules/donglemanager/`
- [P] tasks = different files, no dependencies on incomplete tasks
- [USx] label maps task to specific user story from spec.md
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- The BMO class (`Donglemanager.class.php`) accumulates AJAX handlers across stories — each story adds its handlers to the existing ajaxRequest whitelist and ajaxHandler switch
