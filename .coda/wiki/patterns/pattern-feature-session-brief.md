---
tags: [pattern, orchestration, feature-sessions]
description: Civicplus's feature-session brief template, adapted from Zach's
created: 2026-05-18
updated: 2026-05-18
---

# Pattern: feature-session brief

Adapted from Zach's `feature-session-brief` pattern. Tuned for the
3-hour single-engagement context.

## Template

```
You are implementing <feature-slug> for the folio-takehome repo.

**Worktree / branch:** <absolute path> / <branch-name>
**Design doc:** `@.coda/designs/<feature-slug>.md` is the contract. Read it before coding.
**Wiki:** Read `.coda/wiki/index.md`, then the entity pages the design doc references.
**Pre-existing patterns:** match audit_log (`pattern-audit-log`), tests (`pattern-test-harness`), migrations (`decision-migrations-shape`).

Execute end-to-end in one PR:
1. Read design doc + referenced wiki pages + the source files they reference
2. Add migration(s) under `migrations/NNNN_<slug>.sql`. **Do NOT edit `schema.sql`.**
3. Implement the feature in `public/` and/or `lib/`
4. Add audit_log entries per design doc + `pattern-audit-log`
5. Add at least one `test('...')` in `tests/test.php` per `pattern-test-harness`
6. Verify locally:
   - `docker compose down -v && docker compose up -d`
   - `docker compose exec app php tests/test.php` is green
   - Hit the affected URLs in a browser or via curl, confirm behavior
7. Commit with a coherent log (≥2 commits is fine; tell a story)
8. Open PR titled `<feature>: <one-liner>`, reference the design doc in the body
9. Report `PR ready: <url>` and write `.coda/learnings/<feature-slug>-teardown.md`

**Critical — DO NOT commit:**
- `db.sqlite`
- `schema.sql` changes (use migrations)
- Any change to `.coda/SOUL.md`, `.coda/AGENTS.md`, `.coda/PROJECT.md` (civicplus owns those)
- Files outside the feature's surface (no incidental refactors)

**Scope lock:** ship exactly the design doc, nothing more. If you find
something worth flagging that's outside scope, write it in the teardown
note. Don't fix it.

**Pre-existing-code policy:** don't refactor anything you didn't have to
touch. Don't add comments/docstrings to untouched code.
```

## When to deviate

- **Migrations infra session** doesn't follow this template exactly — it's
  shared infrastructure, not a feature. Use a stripped version: design doc
  contract, the runner + the `schema_migrations` table, a test that asserts
  the migration table exists post-`seed.php`, single PR.

## Related
- [[decision-migrations-shape]]
- [[pattern-audit-log]]
- [[pattern-test-harness]]
