# Data Model: FreePBX Dongle SMS Manager Module

**Branch**: `001-freepbx-dongle-module` | **Date**: 2026-03-17

All tables are created in the FreePBX `asterisk` database with `donglemanager_` prefix.

## Entity Relationship Diagram

```
donglemanager_dongles (1) ──< (many) donglemanager_sms_inbox
donglemanager_dongles (1) ──< (many) donglemanager_sms_outbox
donglemanager_dongles (1) ──< (many) donglemanager_ussd_history
donglemanager_dongles (1) ──< (many) donglemanager_logs
```

## Tables

### donglemanager_dongles

One row per physical dongle. Updated by background worker from AMI `DongleShowDevices` data.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal ID |
| device | VARCHAR(50) | NOT NULL, UNIQUE | Asterisk device name (dongle0, dongle1, ...) |
| imei | VARCHAR(20) | DEFAULT '' | IMEI number |
| imsi | VARCHAR(20) | DEFAULT '' | IMSI number |
| phone_number | VARCHAR(30) | DEFAULT '' | Phone number from SIM |
| operator | VARCHAR(100) | DEFAULT '' | Network operator name |
| manufacturer | VARCHAR(100) | DEFAULT '' | Modem manufacturer |
| model | VARCHAR(100) | DEFAULT '' | Modem model |
| firmware | VARCHAR(100) | DEFAULT '' | Firmware version |
| signal_rssi | TINYINT | DEFAULT 0 | Raw RSSI (0-31, 99=unknown) |
| signal_percent | TINYINT | DEFAULT 0 | Calculated 0-100% |
| gsm_status | VARCHAR(50) | DEFAULT '' | GSM registration status |
| state | VARCHAR(30) | DEFAULT '' | Device state (Free, Busy, Error, Init) |
| enabled | TINYINT(1) | DEFAULT 1 | Enabled in module |
| sms_in_count | INT | DEFAULT 0 | Lifetime incoming SMS count |
| sms_out_count | INT | DEFAULT 0 | Lifetime outgoing SMS count |
| tasks_in_queue | INT | DEFAULT 0 | Pending tasks in Asterisk |
| last_seen | DATETIME | NULL | Last time worker detected dongle active |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation |
| updated_at | DATETIME | ON UPDATE CURRENT_TIMESTAMP | Last update |

**State transitions**:
```
(new dongle detected) → Free
Free → Busy (call/SMS in progress)
Free → Init (reinitializing)
Free → Error (hardware/network error)
Busy → Free (operation complete)
Init → Free (initialization complete)
Init → Error (initialization failed)
Error → Free (auto-restart successful)
Any → Offline (not seen by worker for 5+ minutes)
Offline → Free (dongle reconnected)
```

**RSSI to percentage formula**: `percentage = round((rssi / 31) * 100)` for rssi 0-31; rssi 99 = 0%.

**Indexes**: `device` (UNIQUE), `state`, `last_seen`

---

### donglemanager_sms_inbox

Every incoming SMS. Linked to the dongle that received it.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal ID |
| dongle | VARCHAR(50) | NOT NULL | Which dongle received it |
| sender | VARCHAR(30) | NOT NULL | Sender phone number |
| message | TEXT | NOT NULL | SMS body |
| received_at | DATETIME | NOT NULL | When SMS arrived at modem |
| is_read | TINYINT(1) | DEFAULT 0 | Read/unread flag |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation |

**Indexes**: `dongle`, `sender`, `received_at`, `is_read`

---

### donglemanager_sms_outbox

Every outgoing SMS. Linked to the dongle that sent/will send it.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal ID |
| dongle | VARCHAR(50) | NOT NULL | Which dongle sends it |
| destination | VARCHAR(30) | NOT NULL | Recipient phone number |
| message | TEXT | NOT NULL | SMS body |
| status | ENUM('queued','sending','sent','failed') | DEFAULT 'queued' | Delivery status |
| error | TEXT | NULL | Error message if failed |
| retry_count | TINYINT | DEFAULT 0 | Retry attempts (max 3) |
| sent_at | DATETIME | NULL | When actually sent |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation |

**State transitions**:
```
queued → sending (worker picks up for delivery)
sending → sent (AMI confirms delivery)
sending → failed (AMI error or 120s timeout)
failed → queued (manual retry, if retry_count < 3)
```

**Indexes**: `dongle`, `status`, `destination`, `created_at`

---

### donglemanager_ussd_history

Every USSD command sent and response received.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal ID |
| dongle | VARCHAR(50) | NOT NULL | Which dongle was used |
| command | VARCHAR(255) | NOT NULL | USSD code (e.g., *100#) |
| response | TEXT | NULL | Response from network |
| status | ENUM('sent','received','timeout','failed') | DEFAULT 'sent' | Status |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation |

**State transitions**:
```
sent → received (DongleNewUSSD event captured)
sent → timeout (30 seconds elapsed with no response)
sent → failed (AMI error)
```

**Indexes**: `dongle`, `created_at`

---

### donglemanager_logs

Application and dongle event logs.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal ID |
| level | ENUM('info','warning','error') | DEFAULT 'info' | Severity level |
| category | VARCHAR(50) | NOT NULL | Category: sms, ussd, dongle, system, worker |
| dongle | VARCHAR(50) | NULL | Which dongle (NULL for system-wide) |
| message | TEXT | NOT NULL | Log message |
| created_at | DATETIME | DEFAULT CURRENT_TIMESTAMP | Record creation |

**Retention**: Auto-deleted after 90 days by background worker.

**Indexes**: `level`, `category`, `dongle`, `created_at`

## Validation Rules

| Entity | Field | Rule |
|--------|-------|------|
| SMS Outbox | destination | Must be non-empty, max 30 chars |
| SMS Outbox | message | Must be non-empty |
| SMS Outbox | dongle | Must reference an active dongle in donglemanager_dongles |
| SMS Outbox | retry_count | Cannot exceed 3 |
| USSD History | command | Must be non-empty, max 255 chars |
| Dongles | signal_rssi | 0-31 or 99 |
| Dongles | signal_percent | 0-100 |

## Data Volume Assumptions

| Table | Expected rows (1 year, 5 dongles) | Growth rate |
|-------|-----------------------------------|-------------|
| dongles | 5-20 | Stable (matches physical modems) |
| sms_inbox | ~50,000 | ~140/day |
| sms_outbox | ~50,000 | ~140/day |
| ussd_history | ~2,000 | ~5/day |
| logs | ~100,000 (90-day rolling) | ~1,100/day, auto-pruned |
