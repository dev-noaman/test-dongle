# AJAX API Contracts: Dongle Manager Module

**Module**: `donglemanager` | **Date**: 2026-03-17

All AJAX calls go through FreePBX's `ajax.php` with `module=donglemanager`.
Responses are auto-JSON-encoded by FreePBX framework.

## Base URL

```
POST/GET ajax.php?module=donglemanager&command={command}
```

## Authentication

All requests require an active FreePBX admin session (`FREEPBX_IS_AUTH`).
Unauthenticated requests are rejected by the FreePBX framework before reaching the module.

## Response Format

All `ajaxHandler()` returns follow this shape:

```json
{
  "success": true|false,
  "data": {},
  "message": "Human-readable status"
}
```

For paginated lists, `data` contains:

```json
{
  "rows": [...],
  "total": 150,
  "page": 1,
  "per_page": 25,
  "pages": 6
}
```

---

## Commands

### Dashboard

#### `command=dashboard_stats`

**Method**: GET
**Params**: none
**Returns**:

```json
{
  "success": true,
  "data": {
    "dongles_total": 5,
    "dongles_active": 3,
    "dongles_offline": 2,
    "sms_received_today": 42,
    "sms_sent_today": 38,
    "sms_failed_today": 2,
    "chart_7day": {
      "labels": ["2026-03-11", "2026-03-12", "..."],
      "sent": [12, 15, 10, 8, 20, 18, 38],
      "received": [8, 10, 12, 6, 15, 20, 42]
    },
    "dongles": [
      {
        "device": "dongle0",
        "phone_number": "+1234567890",
        "operator": "Operator A",
        "signal_percent": 74,
        "state": "Free",
        "last_seen": "2026-03-17 10:30:00"
      }
    ],
    "recent_inbox": [
      {
        "id": 100,
        "dongle": "dongle0",
        "sender": "+9876543210",
        "message": "Hello world...",
        "received_at": "2026-03-17 10:25:00"
      }
    ],
    "recent_ussd": [
      {
        "id": 50,
        "dongle": "dongle0",
        "command": "*100#",
        "response": "Your balance is...",
        "created_at": "2026-03-17 10:20:00"
      }
    ]
  }
}
```

---

### SMS

#### `command=sms_list_inbox`

**Method**: GET
**Params**:

| Param | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| page | int | no | 1 | Page number |
| per_page | int | no | 25 | Items per page |
| dongle | string | no | all | Filter by dongle device name |
| search | string | no | — | Search sender and message text |
| date_from | string | no | — | Start date (YYYY-MM-DD) |
| date_to | string | no | — | End date (YYYY-MM-DD) |

**Returns**: Paginated list of inbox rows with fields: `id`, `dongle`, `sender`, `message`, `received_at`, `is_read`, `created_at`

#### `command=sms_list_outbox`

**Method**: GET
**Params**:

| Param | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| page | int | no | 1 | Page number |
| per_page | int | no | 25 | Items per page |
| dongle | string | no | all | Filter by dongle device name |
| status | string | no | all | Filter: queued, sending, sent, failed |
| search | string | no | — | Search destination and message text |
| date_from | string | no | — | Start date (YYYY-MM-DD) |
| date_to | string | no | — | End date (YYYY-MM-DD) |

**Returns**: Paginated list of outbox rows with fields: `id`, `dongle`, `destination`, `message`, `status`, `error`, `retry_count`, `sent_at`, `created_at`

#### `command=sms_send`

**Method**: POST
**Params**:

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| dongle | string | yes | Device name (must be active) |
| destination | string | yes | Phone number |
| message | string | yes | SMS body |

**Returns**: `{"success": true, "data": {"id": 123}, "message": "SMS queued"}`

**Errors**:
- `dongle` not active: `{"success": false, "message": "Dongle dongle0 is not active"}`
- Rate limit exceeded: `{"success": false, "message": "Rate limit: max 10 SMS/min per dongle"}`
- Empty message/destination: `{"success": false, "message": "Destination and message are required"}`

#### `command=sms_mark_read`

**Method**: POST
**Params**: `ids[]` — array of inbox message IDs
**Returns**: `{"success": true, "data": {"updated": 3}}`

#### `command=sms_mark_unread`

**Method**: POST
**Params**: `ids[]` — array of inbox message IDs
**Returns**: `{"success": true, "data": {"updated": 3}}`

#### `command=sms_delete_inbox`

**Method**: POST
**Params**: `ids[]` — array of inbox message IDs
**Returns**: `{"success": true, "data": {"deleted": 3}}`

#### `command=sms_delete_outbox`

**Method**: POST
**Params**: `ids[]` — array of outbox message IDs
**Returns**: `{"success": true, "data": {"deleted": 3}}`

#### `command=sms_retry`

**Method**: POST
**Params**: `ids[]` — array of failed outbox message IDs
**Returns**: `{"success": true, "data": {"retried": 2}}`

**Note**: Only messages with status=`failed` and retry_count < 3 are retried. Others are silently skipped.

---

### USSD

#### `command=ussd_send`

**Method**: POST
**Params**:

| Param | Type | Required | Description |
|-------|------|----------|-------------|
| dongle | string | yes | Device name (must be active) |
| ussd_command | string | yes | USSD code (e.g., *100#) |

**Returns**: `{"success": true, "data": {"id": 50}, "message": "USSD sent"}`

#### `command=ussd_check`

**Method**: GET
**Params**: `id` — USSD history record ID
**Returns**:

```json
{
  "success": true,
  "data": {
    "id": 50,
    "status": "received",
    "response": "Your balance is $5.00",
    "dongle": "dongle0"
  }
}
```

Status will be `sent` (still waiting), `received` (response captured), `timeout`, or `failed`.

#### `command=ussd_history`

**Method**: GET
**Params**:

| Param | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| page | int | no | 1 | Page number |
| dongle | string | no | all | Filter by dongle |

**Returns**: Paginated list with fields: `id`, `dongle`, `command`, `response`, `status`, `created_at`

---

### Dongle Control

#### `command=dongle_list`

**Method**: GET
**Returns**: Array of all dongles with full status (same shape as dashboard dongles but with all fields including IMEI, IMSI, manufacturer, model, firmware, sms_in_count, sms_out_count, tasks_in_queue).

#### `command=dongle_restart`

**Method**: POST
**Params**: `device` — dongle device name
**Returns**: `{"success": true, "message": "Restart command sent to dongle0"}`

#### `command=dongle_stop`

**Method**: POST
**Params**: `device` — dongle device name
**Returns**: `{"success": true, "message": "Stop command sent to dongle0"}`

#### `command=dongle_start`

**Method**: POST
**Params**: `device` — dongle device name
**Returns**: `{"success": true, "message": "Start command sent to dongle0"}`

#### `command=dongle_refresh`

**Method**: POST
**Returns**: Updated dongle list (same as `dongle_list`)

---

### Reports

#### `command=report_summary`

**Method**: GET
**Params**:

| Param | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| date_from | string | yes | — | Start date (YYYY-MM-DD) |
| date_to | string | yes | — | End date (YYYY-MM-DD) |
| dongle | string | no | all | Filter by dongle |

**Returns**:

```json
{
  "success": true,
  "data": {
    "total": 500,
    "sent": 250,
    "received": 230,
    "failed": 20,
    "success_rate": 92.6
  }
}
```

#### `command=report_chart`

**Method**: GET
**Params**: Same as `report_summary`
**Returns**:

```json
{
  "success": true,
  "data": {
    "daily": {
      "labels": ["2026-03-01", "2026-03-02", "..."],
      "sent": [10, 12, 8, "..."],
      "received": [8, 15, 10, "..."]
    },
    "per_dongle": [
      {"device": "dongle0", "total": 200, "label": "dongle0 (+1234567890)"},
      {"device": "dongle1", "total": 150, "label": "dongle1 (+0987654321)"}
    ]
  }
}
```

#### `command=report_dongle_stats`

**Method**: GET
**Params**: `date_from`, `date_to`
**Returns**:

```json
{
  "success": true,
  "data": [
    {
      "device": "dongle0",
      "phone_number": "+1234567890",
      "operator": "Operator A",
      "sent": 100,
      "received": 90,
      "failed": 10,
      "success_rate": 90.9
    }
  ]
}
```

---

### Logs

#### `command=log_list`

**Method**: GET
**Params**:

| Param | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| page | int | no | 1 | Page number |
| per_page | int | no | 50 | Items per page |
| level | string | no | all | Filter: info, warning, error |
| category | string | no | all | Filter: sms, ussd, dongle, system, worker |
| dongle | string | no | all | Filter by dongle |
| search | string | no | — | Search message text |
| date_from | string | no | — | Start date |
| date_to | string | no | — | End date |

**Returns**: Paginated list with fields: `id`, `level`, `category`, `dongle`, `message`, `created_at`

#### `command=log_export`

**Method**: GET
**Params**: Same filter params as `log_list` (no pagination)
**Returns**: CSV file download (Content-Type: text/csv). Columns: Time, Level, Category, Dongle, Message.

---

### Sidebar Data

#### `command=sidebar_counts`

**Method**: GET
**Returns**:

```json
{
  "success": true,
  "data": {
    "unread_inbox": 3,
    "dongles_active": 4,
    "dongles_total": 5
  }
}
```

Used by sidebar to update badge counts on auto-refresh.
