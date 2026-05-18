# 2026-05-18 — Evan's edits to SOUL.md + PROJECT.md (pre-boot)

## Context

Before civicplus's first session, Evan reviewed Zach's initial seed of
SOUL.md and PROJECT.md and made targeted edits. This is a learning doc
because the edits change civicplus's *operating mode*, not just facts —
and that's exactly the kind of thing that should be captured before the
first real interaction.

## What Evan changed in SOUL.md

### Added to "What you do"
- Work directly with Evan as a **peer and collaborator** (not just an
  autonomous executor that asks permission)
- Jot down notes/memories on decisions and Evan interactions — these
  notes are the workflow-reveal artifact for the video

### Added to "What you don't do"
- **Don't make design decisions without consulting Evan**

### Changed authority
The old text said "Make and record ambiguous design calls — Evan can
override." The new text says "Make and record ambiguous design calls
— Evan can override, **and you should inform him directly when these
decisions are made by pointing them out and showing any artifacts.**"

The difference is enormous: civicplus must **proactively surface**, not
silently record and hope Evan reads the wiki.

### Changed repo line
From "fork TBD" to "Bare repo with workspace branching strategy."
Confirmed with Evan: this means **git worktrees per feature, branched
off main**. Documented in [[pattern-branching-worktrees]].

## What Evan changed in PROJECT.md

Reshaped the strategy from 6 steps to 8, inserting two new ones up front:

### Step 0 (new): Sanity check
Verify `docker compose up` works as described in the README *before*
doing anything else. Don't trust the README — run it.

### Step 1 (new): Playwright MCP
Install Playwright MCP so civicplus can drive a browser for
debugging/testing the actual UI. This is a real tool acquisition,
not a future-tense aspiration.

### Step 3 (was step 2): Focus integration
Design phase now explicitly uses **focus + focus MCP** to track tasks.
Each focus card has the feature overview + link to the design doc.
Civicplus must run `focus init` to bootstrap. Documented in
[[pattern-focus-task-convention]].

### Opened questions on two decisions
Evan re-opened two decisions civicplus had pre-locked:

1. **Migrations decision**: "Are there PHP/SQLite specific migration
   tools/ORMs we should consider? What is 'best practice'? What is
   'overkill' for us?" → civicplus must research before locking.
2. **Scheduled publishing**: "How does publish time work with timezones?
   We don't need/want a CRON or task runner so we're relying on time as
   a function from PHP. We have to iron out timezones." → civicplus's
   proposal stands but must be surfaced for approval.

### Search decision
Same call (LIKE substring), softened tone + cleaner link formatting.
No semantic change. Bolded "Justify in video."

## What I (Zach, on Evan's behalf) did in response

1. Created [[pattern-branching-worktrees]] — documents the
   worktrees-per-feature convention
2. Created [[pattern-focus-task-convention]] — documents the focus card
   structure (overview + link to design doc + acceptance list)
3. Created [[pattern-collaboration-with-evan]] — the **operating mode**:
   peer collaboration, proactive surfacing, decisions as PROPOSED until
   approved
4. Marked all four feature/infra decisions as `status: proposed` in
   frontmatter and added "PROPOSED" banners at the top
5. Added "Open question" sections to migrations + scheduling decisions
   capturing Evan's exact wording
6. Marked `.coda/-dir-shipped` decision as `status: approved` (Evan's
   meta-decision is the one thing locked from the start)

## What this means for civicplus's first session

The boot sequence now has very different first actions than what
`memory/2026-05-18.md` originally listed:

1. **Don't dive into wiki sweep yet.** First run `docker compose up`
   and verify the app works (Evan's step 0).
2. **Install Playwright MCP.** Step 1.
3. **Then** wiki sweep / verification against code.
4. **Before writing the migrations design doc, spawn a librarian/explore
   agent** to research PHP migration tools. Report findings to Evan.
5. **Before locking the scheduling decision, surface the timezone
   question to Evan** with civicplus's recommendation.
6. **Run `focus init`** and create cards per the new convention.
7. **Every design decision** gets surfaced to Evan with the artifact
   link before it advances to "approved."

This is the workflow Evan wants visible in the video. The orchestrator
isn't a black box — it's a peer that proposes, justifies, and waits.

## Why this is in learnings/ not memory/

Memory is what *happened*. Learnings are what civicplus needs to know
to *operate*. Evan's edits are operating-mode changes, so they belong
here. They should be re-read on every boot until they're internalized.
