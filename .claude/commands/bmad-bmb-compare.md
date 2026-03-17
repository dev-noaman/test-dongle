Compare tech spec at $ARGUMENTS against the codebase.

1. Read the tech spec file provided.
2. Extract every atomic requirement as REQ-# (sequentially from spec sections).
3. For each REQ, investigate the codebase to determine:
   - **Status**: Implemented | Partial | Not Implemented
   - **Files**: list all responsible source files
   - **Note**: only if Partial — one sentence explaining the gap
4. List any implemented code related to this feature with no matching REQ as EXTRA-#.

Output as markdown table only. No preamble.

| REQ | Description | Status | Files | Note |
|-----|-------------|--------|-------|------|