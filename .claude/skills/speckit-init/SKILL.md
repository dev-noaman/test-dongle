---
name: speckit-init
description: Initialize SpecKit - Run status check, ensure constitution exists, show next steps. Use when verifying SpecKit setup or when /speckit produces no output.
disable-model-invocation: true
---

# SpecKit Init – Bootstrap & Status

Run this skill to verify SpecKit is loaded and to initialize the project if needed.

## What This Does

1. **Status check**: Reports current branch, specs directory, constitution, and readiness
2. **Bootstrap**: If `.specify/memory/constitution.md` is missing, copies from template
3. **Output**: Always displays the full command table and next steps

## Execution Steps

1. Run `.specify/scripts/powershell/check-prerequisites.ps1 -Json -PathsOnly` from repo root. If it fails (e.g. no feature branch yet), capture the error and explain the fix.
2. If `check-prerequisites.ps1` fails with "Feature directory not found" or similar:
   - Explain: "You're on master/main. SpecKit needs a feature branch. Run: `/speckit.specify your feature description` to create one."
3. Check if `.specify/memory/constitution.md` exists and has real content (not just `[PROJECT_NAME]` placeholders). If missing or template-only, copy from `.specify/templates/constitution-template.md` and note that `/speckit.constitution` should be run to fill it.
4. **Always output** the full content below so the user sees it.

---

## SpecKit Status

**SpecKit is loaded.** Commands are in `.claude/commands/`.

### Available Commands

| # | Command | Description |
|---|---------|-------------|
| 1 | `/speckit.constitution` | Create or update the project constitution |
| 2 | `/speckit.specify` | Create a feature spec (run this first to create a feature branch) |
| 3 | `/speckit.clarify` | Refine underspecified areas |
| 4 | `/speckit.plan` | Generate implementation plan |
| 5 | `/speckit.tasks` | Break plan into tasks |
| 6 | `/speckit.checklist` | Generate quality checklists |
| 7 | `/speckit.analyze` | Consistency analysis |
| 8 | `/speckit.implement` | Execute implementation |
| 9 | `/speckit.compare` | Compare spec vs codebase |
| 10 | `/speckit.taskstoissues` | Convert tasks to GitHub issues |

### Recommended Workflow

```
1. /speckit.constitution  →  Set project principles (once)
2. /speckit.specify       →  Create feature branch + spec (start here if new)
3. /speckit.clarify       →  Refine the spec
4. /speckit.plan          →  Design the plan
5. /speckit.tasks         →  Task breakdown
6. /speckit.checklist     →  Quality checklists
7. /speckit.analyze       →  Validate consistency
8. /speckit.implement     →  Build it
```

### Instructions

After running the steps above, output your status findings and **always include the command table and workflow above**. Tell the user the next command to run (e.g. `/speckit.specify "Add freight quote lookup"` or `/speckit.constitution`).
