---
description: SpecKit - Feature specification and implementation workflow toolkit. Shows available commands and guides you through the workflow.
---

## User Input

```text
$ARGUMENTS
```

## SpecKit Workflow Toolkit

**If nothing shows below, run `/speckit-init` instead for status and init.**

SpecKit is a structured workflow for turning feature ideas into fully implemented code. Below are the available commands and the recommended workflow order.

### Available Commands

| # | Command | Description |
|---|---------|-------------|
| 1 | `/speckit.constitution` | Create or update the project constitution (principles & governance) |
| 2 | `/speckit.specify` | Create or update a feature specification from a natural language description |
| 3 | `/speckit.clarify` | Identify underspecified areas in the spec and ask targeted clarification questions |
| 4 | `/speckit.plan` | Generate an implementation plan with design artifacts (data model, contracts, research) |
| 5 | `/speckit.tasks` | Generate an actionable, dependency-ordered task list from the plan |
| 6 | `/speckit.checklist` | Generate a custom checklist for the current feature |
| 7 | `/speckit.analyze` | Run a read-only consistency and quality analysis across spec, plan, and tasks |
| 8 | `/speckit.implement` | Execute the implementation by processing all tasks |
| 9 | `/speckit.compare` | Compare a spec against the codebase |
| 10 | `/speckit.taskstoissues` | Convert tasks into GitHub issues |

### Recommended Workflow

```
1. /speckit.constitution  →  Set project principles (once per project)
2. /speckit.specify        →  Write the feature spec
3. /speckit.clarify        →  Refine underspecified areas
4. /speckit.plan           →  Design the implementation plan
5. /speckit.tasks          →  Break plan into executable tasks
6. /speckit.checklist      →  Generate quality checklists
7. /speckit.analyze        →  Validate consistency across artifacts
8. /speckit.implement      →  Build it
```

### Instructions

**You MUST output visible text in your response.** Do not produce an empty reply.

If the user provided input above, interpret it as one of:
- **A command name** (e.g., "plan", "specify", "tasks") → Tell the user to run the specific command directly, e.g., `/speckit.plan`
- **A feature description** → Suggest starting with `/speckit.specify <feature description>` to kick off the workflow
- **A question about the workflow** → Answer it using the information above
- **Empty input** → Output the full command table and workflow block above in your reply, then ask what they'd like to do

Do NOT execute any sub-commands automatically. This command is informational — it helps the user navigate the SpecKit workflow and pick the right command to run next.
