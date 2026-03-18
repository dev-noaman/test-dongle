# Research: FreePBX Dongle SMS Manager Module

**Branch**: `001-freepbx-dongle-module` | **Date**: 2026-03-17

## Decision 1: Authentication Model

**Decision**: FreePBX auth only — no module-level login, users, or roles. Any user with FreePBX admin access has full access to all dongle manager features.

**Rationale**: FreePBX BMO modules are accessed through the admin panel which already handles authentication via `FREEPBX_IS_AUTH`. Creating a separate login system or user/role management would be redundant. The module checks `FREEPBX_IS_AUTH` on each request; no additional users or role mapping table is needed.

**Alternatives considered**:
- Fully separate auth (own users table, own login page): Rejected — breaks BMO integration, creates duplicate session management, confusing UX with two logins.
- Hybrid (FreePBX auth + module roles): Rejected — no need for module-specific admin/operator/viewer granularity; FreePBX admin access is sufficient.

## Decision 2: Database Strategy

**Decision**: Create tables with `donglemanager_` prefix in the FreePBX `asterisk` database using `\FreePBX::Database()`.

**Rationale**: FreePBX's BMO pattern uses `\FreePBX::Database()` which returns a PDO connected to the `asterisk` database. The `Backup.php`/`Restore.php` classes use `dumpTables()`/`importTables()` which only work against the main database. A separate database would require custom backup/restore logic and a separate PDO connection. Using prefixed tables in the main DB follows the standard BMO pattern and gets backup/restore for free.

**Alternatives considered**:
- Separate `dongle_manager` database (original spec): Rejected — incompatible with BMO backup/restore helpers, requires custom PDO connection management, nonstandard for FreePBX modules.

**Table name mapping** (original → BMO-compatible):
- `dongles` → `donglemanager_dongles`
- `sms_inbox` → `donglemanager_sms_inbox`
- `sms_outbox` → `donglemanager_sms_outbox`
- `ussd_history` → `donglemanager_ussd_history`
- `system_logs` → `donglemanager_logs`

## Decision 3: CSS Framework

**Decision**: Use FreePBX's built-in Bootstrap 3 for layout and standard components. Add custom CSS (`assets/css/donglemanager.css`) for dongle-specific UI (signal bars, status badges, color scheme, card grid). Do NOT load Tailwind CSS.

**Rationale**: FreePBX admin pages already load Bootstrap 3 and jQuery. Loading Tailwind CSS standalone JS alongside Bootstrap would cause class name conflicts (both define `.container`, `.table`, `.btn`, etc.), increase page weight by ~300KB, and produce unpredictable styling. FreePBX's Bootstrap + custom CSS is the standard approach for all existing modules.

**Alternatives considered**:
- Tailwind CSS standalone (original spec): Rejected — class conflicts with FreePBX Bootstrap, non-standard for modules, breaks visual consistency with rest of admin panel.
- Tailwind with `tw-` prefix: Rejected — adds complexity, still loads 300KB of unused CSS framework alongside Bootstrap.

## Decision 4: AMI Access Strategy

**Decision**: Use `\FreePBX::astman()` for all web-initiated AMI commands (dongle control, sending SMS/USSD from the UI). For the background worker (cron), use a direct AMI socket connection class since the worker runs outside the FreePBX framework context.

**Rationale**: `\FreePBX::astman()` is the standard BMO way to access AMI and is already configured with credentials. The background worker runs as a PHP CLI script via cron — it doesn't have the FreePBX framework loaded, so it needs its own AMI socket client. The worker reads AMI credentials from the same FreePBX config files.

**Alternatives considered**:
- Direct socket everywhere: Rejected — would bypass FreePBX's managed AMI connection in the web context, missing connection pooling/reuse.
- Bootstrap FreePBX framework in worker: Rejected — heavy overhead for a cron job; framework expects web context.

## Decision 5: Background Worker Architecture

**Decision**: PHP CLI script (`cron/worker.php`) run every minute via cron. Uses PID file locking. Connects directly to AMI, reads credentials from FreePBX config files. Includes its own DB connection (PDO) using the same credentials.

**Rationale**: The worker needs to: (1) poll dongle statuses, (2) process outbox queue, (3) listen for AMI events, (4) handle timeouts. A minute-interval cron job with 5-second event listening window balances responsiveness with resource usage. PID file prevents overlap.

**Alternatives considered**:
- Long-running daemon: Rejected — more complex process management, PHP not ideal for long-running processes (memory leaks), harder to recover from crashes.
- FreePBX's built-in cron hook: Investigated — FreePBX has a `cronmanager` module but it's designed for simple periodic tasks, not event-driven AMI listening.

## Decision 6: Module Naming

**Decision**: Module rawname is `donglemanager` (lowercase, no hyphens). Class name is `Donglemanager`. Directory is `donglemanager/`.

**Rationale**: FreePBX rawnames must be lowercase alphanumeric. The class file must be PascalCase of the rawname. "donglemanager" is clear, concise, and follows FreePBX naming conventions.

**Alternatives considered**:
- `dongle-php` (original spec name): Rejected — hyphens not allowed in FreePBX rawnames; "php" suffix is redundant.
- `donglesms`: Rejected — doesn't cover USSD and monitoring features.

## Decision 7: Vendor Assets (Chart.js, Font Awesome)

**Decision**: Bundle Chart.js 4 and Font Awesome 6 locally in `assets/vendor/`. These are the only external vendor libraries. No CDN dependencies.

**Rationale**: The spec requires air-gapped operation (SC-011). Chart.js is needed for dashboard and reports charts. Font Awesome provides icons. Both are small enough to bundle (Chart.js ~200KB, Font Awesome CSS+webfonts ~500KB).

**Alternatives considered**:
- CDN links: Rejected — breaks air-gapped requirement.
- No charts (plain tables only): Rejected — spec requires charts for dashboard and reports.
- FreePBX's built-in icon set: Insufficient — FreePBX uses Font Awesome 4; the module needs FA 6 features. Can load FA 6 scoped to module views only.

## Decision 8: SMS Retry Behavior

**Decision**: Maximum 3 retry attempts per SMS. After 3 failures, the message stays in "failed" status permanently. Retries are manual (operator clicks "Retry Failed") — no automatic retry.

**Rationale**: Automatic retries could flood a network-level issue (no credit, invalid number) with repeated attempts. Manual retry gives operators control. The 3-attempt limit prevents infinite retry loops.

**Alternatives considered**:
- Automatic exponential backoff retry: Rejected — could waste SIM credit on permanently-failed numbers, harder to implement in a cron-based worker.
- Unlimited manual retries: Rejected — no practical benefit over 3; prevents accidental spam.
