# Feature Specification: FreePBX Dongle SMS Manager Module

**Feature Branch**: `001-freepbx-dongle-module`
**Created**: 2026-03-17
**Status**: Draft
**Input**: User description: "Build a production-ready FreePBX module for managing GSM USB dongles (chan_dongle) — SMS send/receive, USSD, dongle monitoring, reports, and a background worker — all integrated into the FreePBX admin panel using the BMO module architecture."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Install and Configure Module (Priority: P1)

A FreePBX administrator installs the dongle SMS manager module via `fwconsole ma install donglemanager` on a server that already has Asterisk and chan_dongle configured with one or more USB GSM modems. On first access through the FreePBX admin menu, the module auto-detects FreePBX database and AMI credentials, creates its own database tables, detects connected dongles, and presents the dashboard — all without manual credential entry.

**Why this priority**: Without successful installation and credential auto-detection, no other feature can function. This is the foundation.

**Independent Test**: Can be fully tested by installing the module on a FreePBX server with chan_dongle and verifying the admin menu appears, database tables are created, and dongles are detected.

**Acceptance Scenarios**:

1. **Given** a FreePBX 16+ server with chan_dongle installed and at least one USB modem connected, **When** the administrator runs `fwconsole ma install donglemanager && fwconsole reload`, **Then** the module installs without errors, creates the `dongle_manager` database tables, and a "Dongle Manager" menu item appears in the FreePBX admin navigation.
2. **Given** the module is installed and the admin navigates to Dongle Manager for the first time, **When** the page loads, **Then** the system auto-detects DB credentials from `/etc/freepbx.conf` (or `/etc/amportal.conf` fallback), auto-detects AMI credentials, and queries `DongleShowDevices` to populate the dongles table.
3. **Given** the module is installed on a server with no dongles connected, **When** the admin opens the dashboard, **Then** the system displays a clear warning ("No dongles detected") but remains functional for when dongles are later connected.
4. **Given** the module was previously installed, **When** the admin runs `fwconsole ma uninstall donglemanager`, **Then** all module database tables are dropped and the menu item is removed cleanly.

---

### User Story 2 - Monitor Dongle Status on Dashboard (Priority: P1)

An administrator opens the Dongle Manager dashboard and immediately sees an overview of all connected GSM modems: how many are active vs offline, today's SMS counts (sent/received/failed), a 7-day traffic chart, and per-dongle status cards showing signal strength, operator, phone number, and state. The dashboard auto-refreshes every 10 seconds.

**Why this priority**: Real-time monitoring is the core value proposition — administrators need to know dongle health at a glance before performing any actions.

**Independent Test**: Can be tested by accessing the dashboard with multiple dongles and verifying all status indicators, counts, and charts display correctly and update automatically.

**Acceptance Scenarios**:

1. **Given** 3 dongles are connected (2 active, 1 offline), **When** the admin views the dashboard, **Then** summary cards show "3 dongles: 2 active / 1 offline" and today's SMS sent, received, and failed counts.
2. **Given** the dashboard is open, **When** 10 seconds elapse, **Then** the dashboard updates all statistics and dongle status indicators via AJAX without a full page reload.
3. **Given** a dongle has RSSI value 20 (approximately 65%), **When** the dongle status card renders, **Then** the signal bar shows green and displays "65%".
4. **Given** SMS messages have been sent and received over the past 7 days, **When** the dashboard loads, **Then** a line chart shows daily sent and received volumes for the last 7 days.

---

### User Story 3 - Send SMS via Specific Dongle (Priority: P1)

The user selects an active dongle from a dropdown, enters a destination phone number and message text, and sends an SMS. The system queues the message, the background worker sends it via the selected dongle's AMI command, and the operator sees a success/failure notification. A character counter shows SMS part calculations.

**Why this priority**: Sending SMS is the primary action users need to perform — it's the core functionality.

**Independent Test**: Can be tested by selecting a dongle, entering a number and message, clicking send, and verifying the SMS appears in the outbox with the correct status.

**Acceptance Scenarios**:

1. **Given** dongle0 is active, **When** the user selects dongle0, enters "+1234567890", types "Hello", and clicks Send, **Then** the SMS is inserted into the outbox with status "queued" and a success toast notification appears.
2. **Given** a message is 180 characters using only GSM-7 characters, **When** the user types the message, **Then** the character counter displays "180 / 306 characters (2 SMS parts)".
3. **Given** no dongles are currently active, **When** the user opens the Send SMS page, **Then** the dongle dropdown is empty, a warning message appears ("No active dongles available"), and the send button is disabled.
4. **Given** a message contains Unicode characters (e.g., emoji or Cyrillic), **When** the user types, **Then** the counter switches to UCS-2 calculation (70 chars per single SMS, 67 per part).

---

### User Story 4 - View and Manage SMS Inbox (Priority: P2)

The user views all received SMS messages in a paginated table, filterable by dongle, date range, and search text. They can mark messages as read/unread, delete selected messages, and expand a row to see the full message text.

**Why this priority**: Viewing incoming messages is essential for two-way SMS communication but depends on the background worker (Story 7) capturing incoming messages.

**Independent Test**: Can be tested by verifying inbox displays messages with correct filters, pagination, bulk actions, and message expansion.

**Acceptance Scenarios**:

1. **Given** 50 messages exist in the inbox, **When** the user opens SMS Inbox, **Then** the first 25 messages display in a table with pagination controls showing page 1 of 2.
2. **Given** the user selects "dongle1" from the filter dropdown and clicks Apply, **When** the table refreshes, **Then** only messages received via dongle1 are shown.
3. **Given** 3 unread messages are selected via checkboxes, **When** the user clicks "Mark Read", **Then** those messages update to "read" status and the unread count badge in the sidebar decreases by 3.
4. **Given** a message row is clicked, **When** the row expands, **Then** the full message text is displayed below the row.

---

### User Story 5 - View and Manage SMS Outbox (Priority: P2)

The user views all sent/queued/failed SMS messages in a paginated table with status filters. They can retry failed messages and delete selected entries.

**Why this priority**: Outbox management is needed to track delivery status and handle failures, complementing the send functionality.

**Independent Test**: Can be tested by verifying outbox displays with correct status badges, filter functionality, retry action, and deletion.

**Acceptance Scenarios**:

1. **Given** outbox contains messages with statuses queued, sent, and failed, **When** the user filters by "Failed", **Then** only failed messages appear, each showing a red badge and the error detail.
2. **Given** 2 failed messages are selected, **When** the user clicks "Retry Failed", **Then** those messages reset to "queued" status for re-processing by the worker.
3. **Given** a failed message row is clicked, **When** it expands, **Then** the full message text and error reason are displayed.

---

### User Story 6 - Send USSD and View Responses (Priority: P2)

The user selects an active dongle, enters a USSD code (or clicks a quick-button for common codes like *100#), and sends it. The system shows a loading spinner, polls for the response, and displays the USSD response text when received. A history table shows all past USSD queries.

**Why this priority**: USSD is important for checking balances and managing SIM accounts but is secondary to core SMS functionality.

**Independent Test**: Can be tested by sending a USSD command and verifying the response appears within 30 seconds, and that history is recorded.

**Acceptance Scenarios**:

1. **Given** dongle0 is active, **When** the user selects dongle0, types "*100#", and clicks Send, **Then** a loading spinner appears and the system polls every 2 seconds for a response.
2. **Given** the USSD response arrives within 10 seconds, **When** the worker captures the `DongleNewUSSD` event, **Then** the response text is displayed on screen and recorded in USSD history.
3. **Given** no response arrives after 30 seconds, **When** the timeout elapses, **Then** the status changes to "timeout" and the spinner stops with a timeout message.
4. **Given** the user clicks the "*100#" quick button, **When** the button is clicked, **Then** the USSD input field is filled with "*100#".

---

### User Story 7 - Background Worker Processes Queues and Captures Events (Priority: P1)

A background cron job runs every minute, connects to AMI, updates all dongle statuses, processes the SMS outbox queue (sending up to 5 queued messages per run), captures incoming SMS and USSD events, handles timeouts, auto-restarts frozen dongles, and cleans up old logs.

**Why this priority**: The worker is the engine that powers all async operations — without it, SMS sending, receiving, USSD responses, and dongle monitoring don't function.

**Independent Test**: Can be tested by running the worker script manually and verifying it updates dongle status, processes queued messages, and captures events.

**Acceptance Scenarios**:

1. **Given** 3 messages are queued in sms_outbox, **When** the worker runs, **Then** it sends up to 5 messages via AMI `DongleSendSMS` using each message's specified dongle and updates their status to "sending".
2. **Given** the worker is running and an incoming SMS arrives on dongle1, **When** the `DongleNewSMS` event fires, **Then** the worker inserts the message into sms_inbox with the correct dongle, sender, message, and timestamp.
3. **Given** a message has been in "sending" status for over 120 seconds, **When** the worker runs, **Then** it marks the message as "failed" with error "Send timeout".
4. **Given** a dongle has been in "Error" state for 5+ minutes, **When** the worker runs, **Then** it sends a `DongleRestart` command for that dongle.
5. **Given** another worker instance is already running (PID file exists with active process), **When** a new worker instance starts, **Then** it exits immediately without processing.

---

### User Story 8 - Manage Dongles (Start/Stop/Restart) (Priority: P2)

The user views all dongles in a card grid layout, each showing detailed hardware info (IMEI, IMSI, model, firmware), signal strength with colored bar, connection state, operator name, and SMS counts. They can restart, stop, or start individual dongles.

**Why this priority**: Direct dongle control is important for troubleshooting but is secondary to core monitoring and SMS features.

**Independent Test**: Can be tested by viewing dongle cards, verifying all fields populate correctly, and performing restart/stop/start actions.

**Acceptance Scenarios**:

1. **Given** 4 dongles exist (3 active, 1 offline), **When** the user opens the Dongle Manager page, **Then** 4 cards display in a responsive grid with correct status badges and signal bars color-coded by strength.
2. **Given** dongle0 is active, **When** the user clicks "Restart" on dongle0's card, **Then** a `DongleRestart` command is sent via AMI, a success toast appears, and the card refreshes.
3. **Given** dongle2 is stopped, **When** the user clicks "Start", **Then** a `DongleStart` command is sent and the card state updates.
4. **Given** the dongle manager page is open, **When** 15 seconds elapse, **Then** all cards auto-refresh their status via AJAX.

---

### User Story 9 - View Reports and Analytics (Priority: P3)

The user views SMS traffic reports with date range and dongle filters. Summary cards show totals and success rate. Charts display daily volume (bar chart) and per-dongle distribution (pie chart). A stats table shows per-dongle performance.

**Why this priority**: Reporting provides valuable insights but all core functionality must work first.

**Independent Test**: Can be tested by setting a date range, verifying summary numbers match database records, and checking chart rendering.

**Acceptance Scenarios**:

1. **Given** 500 SMS messages exist in the last 30 days, **When** the user sets the date range to the last 30 days and clicks Apply, **Then** summary cards show correct totals for sent, received, failed, and success rate percentage.
2. **Given** the report is filtered to "dongle0", **When** the charts render, **Then** the bar chart shows only dongle0's daily volumes and the pie chart highlights dongle0's portion.
3. **Given** 3 dongles have handled SMS, **When** the dongle stats table renders, **Then** it shows per-dongle sent/received/failed/success% with a totals row at the bottom, sortable by any column.

---

### User Story 10 - View and Export System Logs (Priority: P3)

The user views system logs filterable by level (info/warning/error), category (sms/ussd/dongle/system/worker), dongle, date range, and search text. They can export filtered logs as CSV. An auto-refresh toggle enables real-time log monitoring.

**Why this priority**: Logging is essential for troubleshooting but all core features must work first.

**Independent Test**: Can be tested by generating log entries via other features and verifying they appear with correct filters and export correctly.

**Acceptance Scenarios**:

1. **Given** logs exist with various levels and categories, **When** the user filters by level "error" and category "sms", **Then** only error-level SMS logs are displayed.
2. **Given** filtered logs are displayed, **When** the user clicks "Export CSV", **Then** a CSV file downloads containing all filtered log entries.
3. **Given** auto-refresh is toggled on, **When** new log entries are created, **Then** they appear in the table without manual refresh.

---

### Edge Cases

- What happens when a dongle is removed mid-SMS-send? The worker marks the message as "failed" with error "Dongle disconnected" when AMI returns an error.
- What happens when the AMI connection fails? The worker logs the error and exits gracefully. The UI shows "AMI connection unavailable" on the dashboard.
- What happens when the database is unreachable? The module displays a user-friendly error page; the worker logs to stdout and exits.
- What happens when two workers run simultaneously? PID file locking ensures only one worker processes at a time; the second instance exits immediately.
- What happens when a user sends SMS to an invalid phone number? The dongle/network returns an error; the outbox entry is marked "failed" with the error detail.
- What happens when a SIM card has no credit? SMS sending fails at the network level; the outbox entry records the error. USSD balance check can reveal the issue.
- What happens when SMS exceeds maximum length? The UI character counter warns the user about multi-part SMS; the system sends as multipart if the dongle supports it.
- What happens when 100+ dongles are connected? The UI remains functional with scrollable card grid; the worker processes all dongles per run; dashboard summarizes all.
- What happens when the FreePBX session expires? The user is redirected to the FreePBX login page; AJAX calls return 401 and FreePBX handles re-authentication.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST integrate as a proper FreePBX BMO module, installable via `fwconsole ma install donglemanager` and manageable through the standard FreePBX module admin interface.
- **FR-002**: System MUST auto-detect FreePBX database credentials from `/etc/freepbx.conf` (primary) or `/etc/amportal.conf` (fallback) without any manual configuration.
- **FR-003**: System MUST auto-detect AMI credentials from the same FreePBX config files and connect to AMI on 127.0.0.1:5038.
- **FR-004**: System MUST create and manage its own database (`dongle_manager`) separate from the FreePBX `asterisk` database.
- **FR-005**: System MUST support 1 to unlimited USB GSM modems simultaneously, with every feature (SMS, USSD, monitoring, reports) working per-dongle and across all dongles.
- **FR-006**: System MUST provide a dashboard showing real-time dongle status, today's SMS statistics, a 7-day traffic chart, and recent activity.
- **FR-007**: System MUST allow sending SMS via a specific selected dongle, with a character counter that calculates GSM-7 and UCS-2 SMS part counts.
- **FR-008**: System MUST capture incoming SMS via the background worker listening for `DongleNewSMS` AMI events and store them in the inbox.
- **FR-009**: System MUST provide paginated, filterable (by dongle, date range, search text, status) views for SMS inbox and outbox.
- **FR-010**: System MUST support sending USSD commands via specific dongles and displaying responses, with quick-buttons for common codes.
- **FR-011**: System MUST provide a dongle management page with per-dongle status cards, signal strength visualization, and restart/stop/start controls.
- **FR-012**: System MUST include a background worker (cron, every minute) that: updates dongle statuses, processes the outbox queue, captures incoming events, handles timeouts, auto-restarts frozen dongles, and performs log cleanup.
- **FR-014**: System MUST provide reporting with date range and dongle filters, summary statistics, daily volume charts, and per-dongle distribution charts.
- **FR-015**: System MUST provide system log viewing with filters (level, category, dongle, date, search) and CSV export capability.
- **FR-017**: System MUST use FreePBX authentication (`FREEPBX_IS_AUTH`) for all module pages; use CSRF tokens on all POST forms and PDO prepared statements for all database queries.
- **FR-018**: System MUST use PID file locking for the background worker to prevent concurrent execution.
- **FR-019**: System MUST rate-limit SMS sending to a maximum of 10 messages per minute per dongle (configurable).
- **FR-020**: System MUST support FreePBX backup and restore operations for all module data.
- **FR-021**: System MUST operate with zero external dependencies — no Composer, no npm, no build step. All vendor assets (Tailwind CSS, Chart.js, Font Awesome) are bundled locally.
- **FR-022**: System MUST auto-refresh dashboard data every 10 seconds and dongle cards every 15 seconds without full page reloads.
- **FR-023**: System MUST return consistent JSON responses from all API endpoints in the format `{"success": bool, "data": mixed, "message": string}`.
- **FR-024**: System MUST automatically mark dongles as offline when they are no longer returned by AMI `DongleShowDevices` and auto-restart dongles that have been in "Error" state for 5+ minutes.
- **FR-025**: System MUST clean up system logs older than 90 days automatically via the background worker.

### Key Entities

- **Dongle**: A physical USB GSM modem registered in Asterisk via chan_dongle. Has device name, IMEI, IMSI, phone number, operator, signal strength, state, and SMS counters. Updated by background worker from AMI data.
- **SMS (Inbox)**: An incoming SMS message received by a specific dongle. Has sender number, message text, receive timestamp, read/unread status.
- **SMS (Outbox)**: An outgoing SMS message sent or queued via a specific dongle. Has destination number, message text, status (queued/sending/sent/failed), error detail, retry count.
- **USSD History**: A USSD command sent via a specific dongle and its response. Has command code, response text, status (sent/received/timeout/failed).
- **System Log**: An application event log entry with level, category, associated dongle, and message. Used for troubleshooting.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Administrators can install the module and see all connected dongles on the dashboard within 5 minutes of running `fwconsole ma install donglemanager && fwconsole reload`.
- **SC-002**: Users can compose and send an SMS message through a selected dongle in under 30 seconds from page load to confirmation.
- **SC-003**: Incoming SMS messages appear in the inbox within 60 seconds of being received by the modem (one worker cycle).
- **SC-004**: Dashboard auto-refresh updates all statistics and dongle statuses without any user-perceived page disruption.
- **SC-005**: All pages load and render within 3 seconds under normal operating conditions (up to 20 dongles, 10,000 messages in the database).
- **SC-006**: The background worker completes a full cycle (status update + queue processing + event capture) within 30 seconds.
- **SC-007**: USSD responses display on screen within 30 seconds of sending the command (or show timeout status).
- **SC-008**: Reports accurately reflect all SMS activity matching the selected date range and dongle filters, with totals matching database records.
- **SC-010**: The module supports at least 20 simultaneous dongles without performance degradation on the dashboard or worker cycle.
- **SC-011**: Zero external runtime dependencies — the module functions fully on an air-gapped server with no internet access after installation.
- **SC-012**: Module backup and restore operations preserve all configuration and message history.

## Assumptions

- FreePBX 16+ is installed and functioning on the target server.
- chan_dongle is already compiled, installed, and configured in `/etc/asterisk/dongle.conf`.
- At least one Huawei USB GSM modem is connected and recognized by the OS (visible as `/dev/ttyUSB*`).
- Asterisk is running with the AMI enabled on 127.0.0.1:5038.
- MariaDB is running on localhost with credentials accessible via FreePBX config files.
- PHP 7.4+ is available with extensions: pdo_mysql, sockets, json, mbstring.
- The FreePBX web server (Apache or Nginx) is running and serving the admin interface.
- The system cron daemon is available for scheduling the background worker.
- Module follows FreePBX GPLv3+ licensing convention.
- The admin page uses FreePBX's existing Bootstrap CSS framework for standard elements (forms, tables) with custom styling for dongle-specific UI components (signal bars, status cards).
- Tailwind CSS 3.4 is used for the custom UI within module views (loaded as standalone JS), coexisting with FreePBX's Bootstrap without conflicts.
