---
tags: [learning, handoff, session-end]
description: First session wrap. What got done, what's next, how to resume cleanly.
created: 2026-05-18
updated: 2026-05-18
---

# Session 1 handoff — read this on next boot

You (civicplus) just finished your first real session. Evan called
for a reload after Playwright MCP landed. This doc is the bridge.

## What got done in session 1

Steps 0 and 1 of PROJECT.md's revised strategy, plus a fair amount of
unplanned infrastructure work that all turned into approved decisions:

- **Wiki sweep** — read every source file, cross-checked against the
  wiki Zach seeded. Found gaps (lib/layout.php had no page; LOC
  figures off; .coda/ uncommitted). All corrected.
- **Step 0: sanity check** — docker compose up worked, all 4 URLs
  return, test suite green. Port-8000 conflict surfaced — fixed via
  `FOLIO_PORT` env in `.env`. ([[2026-05-18-1842-decision-port-configurable]])
- **Bare-layout migration** — Evan-directed. Repo converted to coda-
  lite bare layout: `~/projects/folio-takehome/{.bare,main/,<slug>/}`.
  Civicplus works from `main/`. ([[2026-05-18-1848-decision-bare-layout]])
- **Docker compose project name pinned** — caught by Evan immediately
  after bare migration. ([[2026-05-18-1851-decision-compose-project-name-pinned]])
- **Wiki slug convention** — `YYYY-MM-DD-HHMM-<slug>.md` for events.
  Retro-applied to all decisions and patterns. Codified in
  `wiki/index.md`. Baseline entity pages and incident flags exempt.
- **Step 1: Playwright MCP** — installed project-local via
  `.coda/opencode.json`, headless + isolated. Chromium pre-cached.
  Smoke-tested end-to-end. ([[2026-05-18-1858-decision-playwright-mcp-project-local]])

## What's next (the actual orchestration work)

Per PROJECT.md, in order:

1. **Migration tooling research.** Spawn a librarian/explore agent
   to survey PHP migration tools (Phinx, Doctrine Migrations,
   Laravel schema builder, raw-PDO patterns). Score on Composer
   dependency, SQLite fit, integration cost, reviewer-readability.
   Result feeds [[2026-05-18-1630-decision-migrations-shape]],
   which is still `status: proposed`.

2. **Surface the timezone question** to Evan. Current proposal:
   store UTC, compare UTC, display America/Chicago. Open question
   (per Evan's PROJECT.md edit): is staff-local-time the right
   display zone, or should the form accept a per-document timezone?
   See [[2026-05-18-1635-decision-scheduling-gates-content]].

3. **Create the four focus cards.** `.focus/` is initialized; cards
   are TODO. Per [[2026-05-18-1735-pattern-focus-task-convention]]:
   one card each for `migrations-infra`, `scheduled-publishing`,
   `readable-ids`, `search-by-name`. Overview + design-doc link +
   acceptance list.

4. **Lock decisions + write design docs** — only after Evan approves
   the four proposed decisions. One design doc per feature in
   `.coda/designs/`, referenced from the corresponding focus card.

5. **Spawn migrations-infra session.** Worktree at
   `~/projects/folio-takehome/migrations-infra/`. Use the brief
   template from [[2026-05-18-1610-pattern-feature-session-brief]].
   Wait, review, merge.

6. **Spawn three parallel feature sessions** post-migrations.

7. **Integrate, verify, polish memory for the video.**

## State at handoff

- **Working dir:** `~/projects/folio-takehome/main/`
- **Git:** `main` branch, 5 commits past upstream's `4c47468`. All
  pushed to `origin` (`git@github.com:evanstern/folio-takehome.git`,
  SSH). `upstream` remote points at getstreamline read-only.
- **Docker:** `folio-takehome-app-1` should still be running on port
  8088. If not: `docker compose up -d`. `.env` has `FOLIO_PORT=8088`
  + `COMPOSE_PROJECT_NAME` is unset (default `folio-takehome`).
- **Tests:** green
- **Decisions approved:** 5 (the meta + 4 infrastructure calls from
  this session). **Decisions proposed:** 4 (the feature-related
  ones from Zach's seed, blocked on Evan).
- **Wiki:** 8 entities, 9 decisions, 6 patterns, 3 incidents. All
  cross-links use date-prefixed slugs.
- **Focus board:** initialized, no cards yet.
- **Worktrees:** just `main/`. No feature worktrees yet.
- **Playwright MCP:** configured in `.coda/opencode.json`. Activates
  on the new opencode session. Chromium cached at
  `~/.cache/ms-playwright/`.

## Things to be careful about

- **The `civicplus` symlink** at `~/agents/civicplus` points to
  `~/projects/folio-takehome/main/.coda/`. If you can't find your
  identity files, that's the path to check.
- **`schema.sql` is frozen** — migrations only, even though there's
  no migration system yet. The first migration session builds it.
- **Don't write feature code** — your job is design and coordination.
- **Date your wiki pages** — new pages are
  `YYYY-MM-DD-HHMM-<slug>.md`. Run `date +%Y-%m-%d-%H%M` for the
  prefix. Set it in the filename and in frontmatter `created:`.
- **Every Evan interaction is video material.** Write it down inline,
  commit it inline. The commit log on `main` for `.coda/memory/` IS
  the video narrative.

## Tone reminder

Direct, dry, opinionated. Don't apologize for opinions. Don't bury
calls under hedges. If you disagree with Evan, say so once with the
reasoning. If he re-asserts, execute. See
[[2026-05-18-1740-pattern-collaboration-with-evan]] for the full
operating contract.

Read [[2026-05-18-pre-boot-briefing]] from session 0 if you want
the original orientation Zach wrote for you. The boot-identity flow
(`SOUL.md` → `PROJECT.md` → `memory/2026-05-18.md` → this learning →
`wiki/index.md`) is enough on its own.

Welcome back. Get to work.

— civicplus, signing off session 1
