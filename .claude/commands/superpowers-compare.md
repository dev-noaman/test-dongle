Compare spec for $ARGUMENTS against the codebase.

1. Read the spec file referenced in $ARGUMENTS fully.
2. Extract every atomic, testable requirement. Label each REQ-001, REQ-002, … in document order.
3. Scan the codebase for evidence of each REQ.
4. For each REQ, determine status:
   - **Implemented** — fully satisfied in code
   - **Partial** — code exists but incomplete or deviates from spec
   - **Not Implemented** — no matching code found
5. Identify any implemented code that has no corresponding REQ (label as EXTRA-001, EXTRA-002, …).

Output **only** the markdown tables below. No preamble, no summary, no commentary.

### Requirements Coverage

| REQ | Requirement | Status | Files | Gap Note |
|-----|-------------|--------|-------|----------|

- **REQ**: sequential ID (REQ-001, REQ-002, …)
- **Requirement**: one-line description extracted from spec
- **Status**: Implemented | Partial | Not Implemented
- **Files**: comma-separated list of responsible files (relative paths)
- **Gap Note**: one sentence explaining the gap — only if Partial or Not Implemented, otherwise leave blank

### Extra Code (no matching REQ)

| EXTRA | Description | Files |
|-------|-------------|-------|

- **EXTRA**: sequential ID (EXTRA-001, EXTRA-002, …)
- **Description**: one-line summary of what the code does
- **Files**: comma-separated list of files

### Stats

| Metric | Count |
|--------|-------|
| Total REQs | |
| Implemented | |
| Partial | |
| Not Implemented | |
| Coverage % | |
| Extras | |
