---
tags: [pattern, workflow, focus, task-tracking]
description: How civicplus uses focus + focus-mcp to track design tasks. Cards link back to design docs.
created: 2026-05-18
updated: 2026-05-18
---

# Pattern: focus task convention

Per Evan's directive, civicplus uses **focus** (the kanban CLI / MCP) to
track design and feature work alongside the design docs in `.coda/designs/`.

The design doc is the contract. The focus card is the unit of *work
status* — it points at the contract, doesn't replace it.

## Bootstrap

Run once after wiki sweep, before writing design docs:
```sh
cd ~/projects/folio-takehome
focus init
```

This creates `.focus/` at the repo root. It travels with the repo, so
reviewers can see the work board if they want to.

## Card structure

Every card has:

1. **Title** — short, descriptive, matches the design-doc slug
2. **Body** — three sections:
   - **Overview** — one-paragraph "what + why" (extracted from the design doc, not duplicating it)
   - **Design doc** — relative link to `.coda/designs/<slug>.md`
   - **Acceptance** — bullets from the design doc's "scope" or "deliverables" section
3. **Tags** — at minimum: `feature` or `infra`, plus the slug

Example body:
```markdown
## Overview
Replace opaque integer IDs with short readable IDs that staff can
say out loud. Readable IDs complement the share-token mechanism — they
identify the document, the token still gates recipient access.

## Design doc
See [.coda/designs/readable-ids.md](.coda/designs/readable-ids.md)

## Acceptance
- [ ] Migration adds `documents.readable_id` (UNIQUE)
- [ ] All existing docs get a readable_id via the migration
- [ ] Document creation generates a readable_id automatically
- [ ] Recipient URL accepts `/d/<readable-id>?token=<hex>`
- [ ] audit_log entry includes `readable_id` in details
- [ ] One test in `tests/test.php` covers the format + uniqueness
```

## Lifecycle

| Status | When |
|---|---|
| backlog | card created during design phase |
| active | feature session has been spawned for it |
| done | PR merged + reviewed |
| archived | obsoleted by a decision |

## Civicplus's cards

Expected at design phase:
- `migrations-infra` (epic? — single card if simple)
- `scheduled-publishing`
- `readable-ids`
- `search-by-name`

Plus the "shipping" meta-card for the video + commit-log polish at the end.

## Conventions

- **Link, don't duplicate.** The card body cites the design doc by relative
  path. If the design doc updates, the card doesn't need to.
- **Move the card when the work moves.** When civicplus spawns the feature
  session, the card goes `backlog → active`. When the PR merges, `active → done`.
  Memory writes about a card transition are valuable.
- **The acceptance list IS the PR checklist.** A feature session can't
  claim done until every box ticks.

## Why this exists

- Reviewer can `cat .focus/` and see the state of the work alongside the code
- Video can show the board mid-engagement (cards moving)
- Civicplus has one place to ask "what's blocked / what's next"

## Related
- [[pattern-feature-session-brief]]
- [[pattern-branching-worktrees]]
- [[decision-coda-dir-shipped]]
