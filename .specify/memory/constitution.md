<!--
  Sync Impact Report
  ===================
  Version change: (none — template placeholders) → 1.0.0
  Modified principles: N/A (initial ratification, all new)
  Added sections:
    - Core Principles (6 principles)
    - Technology Constraints
    - Development Workflow
    - Governance
  Removed sections: All template placeholders replaced
  Templates requiring updates:
    - .specify/templates/plan-template.md: ✅ No update needed
      (Constitution Check section already references constitution file generically)
    - .specify/templates/spec-template.md: ✅ No update needed
      (Mandatory sections align with Principle IV incremental delivery)
    - .specify/templates/tasks-template.md: ✅ No update needed
      (User story organization aligns with Principle IV)
  Follow-up TODOs: None
-->

# Arafat VMS Constitution

## Core Principles

### I. Role-Based Security (NON-NEGOTIABLE)

Every endpoint MUST enforce role-based access control via `@Roles()` decorators.
Company-scoped data isolation MUST be applied for HOST and STAFF roles using
the `getHostScope()` pattern. No endpoint may return data outside a user's
authorized scope. Rate limiting MUST use all three throttler strategies
(`default`, `login-account`, `login-ip`) — `@SkipThrottle()` MUST explicitly
list all three to skip them.

- ADMIN: Full access, no scoping
- RECEPTION: Create visitors/deliveries, no approve/reject
- HOST: Company-scoped CRUD via `hostId` → `host.company`
- STAFF: Company-scoped (auto-linked to "Arafat Group"), limited access
- Violations detected at review MUST block merge

### II. API Contract Fidelity

Frontend and backend types MUST match exactly. Response shapes, field names,
nesting structure, and enum values are binding contracts. Any change to an
API response shape MUST be reflected in all consuming frontends (admin, kiosk,
mobile) before merge.

- Visit endpoints MUST return nested `visitor: { name, company, phone, email }`
- Dashboard endpoints return flat objects; pending-approvals return plain arrays
- Lookups return `LookupItem[]` with `label` (not `name`)
- Admin list endpoints return nested `host: { name }` (not flat `hostName`)
- Field aliasing (e.g., `expectedDate` vs `visitDate`) MUST be documented in
  CLAUDE.md when discovered

### III. Existing Pattern Reuse

New features MUST follow established patterns before introducing new ones.
Extend existing controllers, reuse existing components via props, and follow
existing state management conventions. Creating a new NestJS module, React
component library, or abstraction layer requires explicit justification in
the plan's Complexity Tracking section.

- Backend: Thin controllers, business logic in services, DTOs with
  class-validator, Prisma queries with explicit `select`/`include`
- Admin frontend: Functional components, React Hook Form + Zod, Sonner
  toasts, HostsList/HostForm/HostModal reuse pattern
- Mobile: Zustand stores, React Query hooks, endpoint modules in
  `services/endpoints/`
- Kiosk: `apiFetch` via `src/lib/api.ts`, never raw `fetch`

### IV. Incremental Delivery

Features MUST be decomposed into independently testable user stories
prioritized P1, P2, P3. Each story MUST deliver standalone value and be
deployable without requiring subsequent stories. MVP scope (P1 story) MUST
be identified and completable first. Tasks MUST be organized by user story
to enable parallel implementation.

- Each user story MUST have acceptance scenarios in Given/When/Then format
- Each story MUST have an independent test description
- Setup and foundational phases MUST complete before any story work begins
- Stop-and-validate checkpoints MUST exist after each story phase

### V. Defense Against Known Gotchas

The project maintains a living list of critical gotchas in CLAUDE.md. Every
implementation MUST be validated against this list before merge. New gotchas
discovered during development MUST be added to CLAUDE.md immediately.

- CORS: Both `www.arafatvisitor.cloud` and `arafatvisitor.cloud` MUST work
- Prisma: Run `npx prisma generate` after any schema change
- Throttler: `@SkipThrottle()` requires all three strategy names
- Timer types: `ReturnType<typeof setTimeout>` in browser context
- QR formats: Both `VMS-NNNNNN` and UUID MUST be accepted
- Field aliasing: `expectedDate` not `visitDate`/`scheduledDate`

### VI. Simplicity Over Abstraction

Prefer the minimum complexity needed for the current task. Three similar lines
of code are better than a premature abstraction. Do not add features, error
handling, or configuration beyond what is explicitly required.

- No new helpers/utilities for one-time operations
- No feature flags or backwards-compatibility shims — change the code directly
- No design for hypothetical future requirements (YAGNI)
- Soft deletes preferred for audit trail; indexes on frequently queried columns
- Only extract when a pattern appears 3+ times, not 2

## Technology Constraints

The Arafat VMS uses a fixed technology stack. All features MUST use these
technologies unless a deviation is justified in the Complexity Tracking
section of the implementation plan.

| Layer | Technology | Version |
|-------|-----------|---------|
| Backend API | NestJS | 10.x |
| ORM | Prisma | 4.16 |
| Database | PostgreSQL | 16 |
| Admin Dashboard | React + Vite + Tailwind | React 18 |
| Kiosk Frontend | React + Vite + Tailwind | React 19 |
| Mobile App | React Native + Expo + NativeWind | Expo 54, RN 0.81 |
| Language | TypeScript strict mode | 5.x |
| State (mobile) | Zustand + React Query v5 | Zustand 5 |

Additional constraints:
- NestJS packages MUST be ^10.x compatible (not 11)
- `esModuleInterop: true` — use default imports for CJS packages
- No `console.log` in production code
- No `any` type without documented justification
- Migrations: `npx prisma migrate dev --name descriptive-name`

## Development Workflow

Every feature follows the Speckit phases:

1. **Discovery** (`/speckit.specify`) — Requirements, user stories, acceptance
   criteria
2. **Clarification** (`/speckit.clarify`) — Resolve ambiguities, max 5
   questions
3. **Architecture** (`/speckit.plan`) — Research, data model, API contracts,
   quickstart guide
4. **Task Breakdown** (`/speckit.tasks`) — Dependency-ordered tasks by user
   story
5. **Implementation** (`/speckit.implement`) — Code generation following tasks
6. **Review** — Validate against spec, run type checks, test all roles

Commit conventions:
- Format: `type(scope): short description`
- Types: `feat`, `fix`, `refactor`, `style`, `docs`, `test`, `chore`, `perf`
- Scopes: `backend`, `admin`, `kiosk`, `mobile`, `prisma`, `ci`
- Branch naming: `feature/VMS-xxx-short-description`

PR checklist (MUST pass before merge):
- Types pass (`tsc --noEmit`)
- No new `any` types without justification
- API contract matches between frontend and backend
- Rate limiting considered
- All affected roles tested
- CORS: both www and non-www work

## Governance

This constitution supersedes ad-hoc coding decisions and informal conventions.
All feature plans MUST include a Constitution Check section validating
compliance with these principles. Violations MUST be justified in the
Complexity Tracking table.

Amendment procedure:
1. Propose change via `/speckit.constitution` with description
2. Document rationale for addition, removal, or modification
3. Version bump follows semantic versioning (see below)
4. Update CLAUDE.md if the amendment affects runtime guidance
5. Propagate changes to dependent templates

Versioning:
- MAJOR: Principle removed or redefined incompatibly
- MINOR: New principle added or existing one materially expanded
- PATCH: Clarifications, wording fixes, non-semantic refinements

Compliance review:
- Every `/speckit.plan` output MUST include a Constitution Check gate
- Plan MUST NOT proceed past Phase 0 with unresolved gate violations
- Post-Phase 1 re-check MUST confirm design still complies

**Version**: 1.0.0 | **Ratified**: 2026-02-25 | **Last Amended**: 2026-02-25
