# PROJECT.md — Folio Take-Home (Civicplus)

## Vision

Ship a thoughtful submission for the getstreamline Folio take-home exercise
inside a ~3 hour budget. The exercise extends a minimal PHP/SQLite document-
sharing app with three customer-requested features. Grading weights judgment,
ambiguity-handling, and AI-workflow visibility at least as heavily as raw code.

Deliverables (from README):
1. A branch with changes and a commit log that tells a story
2. A ~5 min video covering approach, decisions, AI workflow, what was scoped out
3. (Optional) chat transcripts

## The App (as-shipped)

- **Stack:** PHP 8.3 CLI server + SQLite + plain HTML/CSS, all in Docker
- **Schema:** `staff`, `documents`, `shares`, `audit_log` (see `wiki/entities/folio-schema`)
- **Entry points:** `public/admin.php` (create + list), `public/share.php`
  (generate share token), `public/view.php` (recipient view by token)
- **Auth:** none. `current_staff()` hardcodes staff id=1 from seed
- **Tests:** single `tests/test.php` with a homemade `test()` helper
- **Seed:** `seed.php` wipes and reseeds `db.sqlite` on every `docker compose up`

## The Three Features

### 1. Scheduled publishing
Staff can schedule a doc to become visible to recipients at a future time.
Before that time, the share link shows "not yet available" instead of the
document.

### 2. Human-readable document IDs
Replace or complement opaque integer IDs + hex share tokens with short
readable IDs (e.g. `welcome-2026`, `FOLIO-7QX4`). Format, length, URL
structure are our call. Tradeoffs: collisions, guessability, privacy,
link permanence.

### 3. Search by name
Staff can find a document by title. Search semantics (exact / prefix /
fuzzy / FTS) are our call and need justification.

## Non-Negotiables (from README)

- **Migrations:** schema changes go through a migration file (or files)
  we add. No direct edits to `schema.sql`. There is NO existing migration
  system — we design one.
- **Tests:** at least one test per feature, in the existing `tests/test.php`
  pattern.
- **Audit log:** document creation, scheduling changes, and share actions
  log to `audit_log` (pattern in `lib/bootstrap.php`).
- **Docker still works:** `docker compose up` from a fresh clone must work
  for whoever reviews the branch.

## Strategy (Civicplus's plan)


0. **Sanity Check**. Verify the app runs as described via docker-compose. 
1. **Playwrite MCP**. Install Playwrite MCP (globally, locally, whatever) so we
   can use it for debugging/testing of the app.
2. **Wiki sweep (Civicplus, ~15 min).** Read everything. Verify wiki seed
   against actual code. Update where reality diverges.
3. **Design phase (Civicplus, ~30 min).** Four design docs:
   - `migrations-infra.md` — migration runner, naming, integration with `seed.php`
   - `scheduled-publishing.md`
   - `readable-ids.md`
   - `search.md`
   Each doc resolves the ambiguous-spec calls upfront. Locked before any
   feature session spawns.
   Use the Focus tool plus Focus MCP to create tasks that link back to the
   design docs. Focus tasks should contain the overview of the task and links
   to the design docs (where available). This also means `focus init` must be
   run.
4. **Migrations infra session (~20 min, blocks everything).** One feature
   session spawns and lands the migration runner. Everyone else waits.
5. **Three parallel feature sessions (~75 min).** One per feature, on
   separate branches/worktrees. Each spec is the contract.
6. **Review + integrate (~30 min).** Civicplus reviews each PR. Resolve
   merge order. Verify `docker compose up` works end-to-end. Run tests.
7. **Decision-log polish (~10 min).** Cleanup memory/ entries into a
   coherent narrative for the video.

## Risks / Pre-decided Calls

These are Civicplus calls, made upfront so subagents don't conflict.
Re-decide if Evan disagrees:

- **Feature interaction — readable ID + scheduling:** Readable ID is the
  document's permanent identifier. Scheduling gates *content visibility*,
  not ID existence. Hitting `/d/welcome-2026` before publish time shows
  "not yet available," not 404.
  *Question to decide*: how does publish time work with timezones? we don't
  need/want a CRON or task runner to do "publish" things so we're relying on
  time as a function from PHP or wherever. We have to iron out timezones etc.
- **Readable ID scope:** Complement, not replace, the existing hex share
  token. Readable IDs identify the *document*; share tokens still gate
  recipient access. Justifies link-permanence + privacy tradeoff.
- **Search scope:** Server-side LIKE with case-insensitive substring match on
  title. (SQLite Full Text Search -- FTS5)[https://sqlite.org/fts5.html] is
  overkill for the dataset and adds migration complexity. **Justify in video**.
- **Migration shape:** Numbered SQL files in `migrations/`, applied in
  order by a tiny PHP runner invoked from `seed.php`. No down-migrations.
  Idempotent re-runs via `schema_migrations` tracking table.
  *Question to consider*: Are there PHP/SQLLite specific migration tools/orms
  we should consider? What is "best practice"? What is "overkill" for us?

## Time Budget

| Phase | Budget | Cumulative |
|-------|--------|------------|
| Wiki sweep | 15 min | 0:15 |
| Design phase | 30 min | 0:45 |
| Migrations session | 20 min | 1:05 |
| Three feature sessions (parallel) | 75 min | 2:20 |
| Review + integrate | 30 min | 2:50 |
| Decision-log polish | 10 min | 3:00 |

If we slip, drop in this order: search → readable IDs → scheduling
(scheduling is the most "obvious" feature and best demonstrates the
audit-log + migration pattern; do it first to lock in patterns).

## What Civicplus Will Actively Not Do

- Build auth. Out of scope, would eat the budget.
- Refactor existing code unless a feature requires it.
- Add docstrings/comments to code we didn't touch.
- Build FTS, fuzzy search, ranking. LIKE is enough; justify it.
- Replace the share-token mechanism with readable IDs (complement, don't replace).
- Add a JS framework, build step, or any front-end tooling.
