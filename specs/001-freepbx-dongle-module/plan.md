# Implementation Plan: FreePBX Dongle SMS Manager Module

**Branch**: `001-freepbx-dongle-module` | **Date**: 2026-03-17 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/001-freepbx-dongle-module/spec.md`

## Summary

Build a production-ready FreePBX BMO module (`donglemanager`) that provides a web-based management interface for Huawei GSM USB dongles via chan_dongle. The module integrates into the FreePBX admin panel with dashboard monitoring, SMS send/receive, USSD commands, dongle control (start/stop/restart), traffic reports with charts, and system logging — all powered by a background cron worker that communicates with Asterisk via AMI. Zero external dependencies; all vendor assets bundled locally.

## Technical Context

**Language/Version**: PHP 7.4+ (plain PHP, no framework, PDO for database)
**Primary Dependencies**: FreePBX BMO framework, jQuery (provided by FreePBX), Chart.js 4 (bundled), Font Awesome 6 (bundled)
**Storage**: MariaDB on localhost (FreePBX `asterisk` database, tables prefixed `donglemanager_`)
**Testing**: Manual testing on FreePBX server with chan_dongle; worker testable via CLI
**Target Platform**: Linux server (CentOS/AlmaLinux/Debian/Ubuntu) with FreePBX 16+
**Project Type**: Single project (FreePBX module)
**Performance Goals**: Dashboard loads < 3s, worker cycle < 30s, 20+ dongles without degradation
**Constraints**: Zero external dependencies (air-gapped capable), no Composer/npm/build step, PHP 7.4 minimum
**Scale/Scope**: 1-20+ dongles, ~100k messages/year, 9 view pages, 21 AJAX endpoints

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

The constitution (`constitution.md`) is for the **Arafat VMS project** (NestJS/React/PostgreSQL), which is a completely different technology stack and domain. This FreePBX module uses plain PHP, MariaDB, and the FreePBX BMO pattern.

**Applicable principles (in spirit)**:

| Principle | Compliance | Notes |
|-----------|------------|-------|
| I. Role-Based Security | N/A | Module uses FreePBX native auth; no module-level roles |
| II. API Contract Fidelity | PASS | AJAX contracts documented in `contracts/ajax-api.md` |
| III. Existing Pattern Reuse | PASS | Follows FreePBX BMO module conventions throughout |
| IV. Incremental Delivery | PASS | 10 user stories prioritized P1/P2/P3 with independent testability |
| V. Defense Against Known Gotchas | PASS | Edge cases documented in spec; permission conventions in CLAUDE.md |
| VI. Simplicity Over Abstraction | PASS | Plain PHP, no framework, no unnecessary abstractions |

**Technology stack deviations** (justified — different project):

| Constitution Tech | This Module | Justification |
|-------------------|-------------|---------------|
| NestJS 10.x | Plain PHP 7.4+ | FreePBX modules must be PHP; BMO pattern is PHP-only |
| Prisma/PostgreSQL | PDO/MariaDB | FreePBX uses MariaDB; `\FreePBX::Database()` returns PDO |
| React/Tailwind | Bootstrap 3 + jQuery + custom CSS | FreePBX admin already loads these; adding React/Tailwind would conflict |
| TypeScript strict | PHP | FreePBX module ecosystem is PHP |

**Gate result**: PASS — all spirit-level principles met; technology deviations fully justified by target platform.

**Post-Phase 1 re-check**: PASS — data model uses prefixed tables in existing DB (simplicity), contracts are explicit (fidelity), no unnecessary abstractions introduced.

## Project Structure

### Documentation (this feature)

```text
specs/001-freepbx-dongle-module/
├── plan.md              # This file
├── spec.md              # Feature specification
├── research.md          # Phase 0: architectural decisions
├── data-model.md        # Phase 1: database schema
├── quickstart.md        # Phase 1: development guide
├── contracts/
│   └── ajax-api.md      # Phase 1: AJAX endpoint contracts
├── checklists/
│   └── requirements.md  # Spec quality checklist
└── tasks.md             # Phase 2 output (NOT created by /speckit.plan)
```

### Source Code (repository root)

```text
donglemanager/
├── module.xml                        # Module manifest (version, deps, menus, hooks)
├── Donglemanager.class.php           # Main BMO class
├── install.php                       # DB schema creation
├── uninstall.php                     # DB cleanup
├── page.donglemanager.php            # FreePBX page entry point
├── Backup.php                        # Backup handler
├── Restore.php                       # Restore handler
├── functions.inc.php                 # Legacy functions (required by framework)
├── views/
│   ├── main.php                      # View router
│   ├── dashboard.php                 # Dashboard overview
│   ├── sms_inbox.php                 # SMS inbox
│   ├── sms_outbox.php                # SMS outbox
│   ├── sms_send.php                  # Send SMS form
│   ├── ussd.php                      # USSD send + history
│   ├── dongles.php                   # Dongle manager cards
│   ├── reports.php                   # Reports + charts
│   └── logs.php                      # System logs
├── assets/
│   ├── css/
│   │   └── donglemanager.css         # Custom styles
│   ├── js/
│   │   └── donglemanager.js          # AJAX, auto-refresh, SMS counter
│   └── vendor/
│       ├── chart.umd.min.js          # Chart.js 4 (bundled)
│       └── fontawesome/              # Font Awesome 6 (bundled)
│           ├── css/all.min.css
│           └── webfonts/
├── includes/
│   ├── AmiClient.php                 # Direct AMI socket client (for worker)
│   └── ConfigReader.php              # FreePBX config file parser
└── cron/
    └── worker.php                    # Background worker (cron every minute)
```

**Structure Decision**: Single FreePBX module directory following the standard BMO module layout. All PHP, views, assets, and the background worker are colocated in `donglemanager/`. This matches the established pattern used by core FreePBX modules (queues, recordings, userman).

## Complexity Tracking

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| Separate AMI client class (includes/AmiClient.php) | Worker runs outside FreePBX framework; cannot use `\FreePBX::astman()` | Direct socket code inline in worker.php would be unmaintainable (~200 lines of socket handling mixed with business logic) |
| Font Awesome 6 alongside FreePBX's FA 4 | Module needs FA 6 icons; FreePBX ships FA 4 | Using only FA 4 icons limits the available icon set; FA 6 CSS is scoped to module views via asset loading |
