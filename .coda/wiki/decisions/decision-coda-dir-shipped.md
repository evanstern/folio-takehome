---
tags: [decision, meta, deliverable]
description: .coda/ ships in the submission. Orchestrator setup is part of the grade per the README.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Decision: ship `.coda/` in the submission

## Context

README has a whole section titled "Agent setup":
> How you configure this repo for AI-assisted work is part of the exercise.
> That can include context files, permissions, hooks, custom commands,
> conventions to follow, orchestration (subagents, parallel tasks, custom
> skills or commands) — whatever fits how you work.
>
> We're not prescribing specifics. Commit what you'd commit on a real
> project. If you decide setup isn't worth it for a three-hour exercise,
> say so in your video and explain why.

## Decision

**`.coda/` ships in the branch.** It's not hidden, not stripped at submission
time, not gitignored.

The submission therefore shows three layers:
1. The feature code (the PR-of-PRs)
2. The orchestrator config that produced it (`.coda/`)
3. The video narrative that connects them

## What goes in `.coda/`

- `SOUL.md`, `AGENTS.md`, `PROJECT.md`, `PERSONALITY.md` — identity + ops
- `wiki/` — codebase map + decisions made
- `designs/` — one design doc per feature, plus migrations infra
- `memory/` — session narrative (raw input for the video)
- `learnings/` — subagent teardown notes
- `inbox/`, `outbox/` — gitignored at the entry level (transient comms)

## What does NOT go in

- The session-id, port, serve.log files (gitignored)
- Anything user-specific (no API keys, no local paths beyond what's intentional)

## Trade-off

- **Pro:** demonstrates a real working AI workflow, not a Potemkin one. The README explicitly invites this.
- **Pro:** the wiki + decisions are themselves part of the "judgment" deliverable. They're easier to read than git log alone.
- **Con:** more surface area for the reviewer to skim. Mitigated by keeping the docs short and structured.

## Talking points for the video

- "The README asked. We answered. Here's the actual setup — it's not a
  demo, it's what we used to ship the features. Read the decisions in
  `.coda/wiki/decisions/` for the design calls, the daily entries in
  `.coda/memory/` for the moment-to-moment, and the design docs in
  `.coda/designs/` for the feature contracts the subagents executed against."

## Related
- (all the other decisions and entities — this is the meta-decision that
  contains them)
