---
tags: [pattern, workflow, focus, decision-hygiene]
description: When civicplus decides to simplify or punt a capability, capture it as a p3/p4 backlog focus card so the rejected upgrade path is visible.
created: 2026-05-18
updated: 2026-05-18
---

# Pattern: punt cards

When civicplus deliberately *doesn't* build something — typically
because it's "more correct" but more work than the budget allows —
the rejection becomes a **punt card**: a low-priority (`p3` or `p4`)
focus card in `backlog` whose body explains:

1. What we punted
2. Why we punted it (budget, scope, or scale)
3. What we shipped instead
4. What it would take to do the punt properly later
5. The trigger condition: when does the punt stop being acceptable?

## Why

Three reasons:

1. **The "what we'd do with more time" beat is part of the deliverable.**
   The README explicitly asks the video to cover "what you skipped and
   why." A focus card per punted capability turns that beat from
   verbal hand-waving into a written artifact a reviewer can read.

2. **Decisions stay honest.** When the decision page says "we picked A
   over C because C was more than necessary," the punt card is the
   receipt — it shows what C looked like in concrete terms, not as a
   vague "later if needed."

3. **A reviewer (or future-self) can grep the backlog and see exactly
   what was deliberately deferred** vs. what was overlooked. The
   backlog status + p3/p4 priority is the visual cue.

## Card structure

Same focus-card body convention as
[[2026-05-18-1735-pattern-focus-task-convention]], with sections:

```markdown
## Overview

Short paragraph: what we punted, what we shipped instead.

## Why we punted

Concrete budget/scope/scale reasoning. Not handwaving.

## What "doing it properly" looks like

The actual upgrade path. Schema sketches, code shape, migration plan
if any. This is what makes the card a credible artifact rather than a
TODO comment.

## Trigger to revisit

When does the punt stop being acceptable? Specific signals
("staff in second tz", "doc count > 1000", "search latency > 500ms").

## Related

Link back to the decision page and the design doc that triggered the punt.
```

## Naming

Card title prefix: `punt: <slug>` so they're scannable in `focus list`
output.

Examples:
- `punt: multi-tz support`
- `punt: FTS5 full-text search`
- `punt: schedule edit UI`

## Priority

- `p3` — meaningful upgrade, would land in a follow-up sprint
- `p4` — nice-to-have, would only land if a specific trigger fires

Default to `p3` unless the upgrade is truly speculative.

## When NOT to make a punt card

- The rejection is captured fully in the design doc's "Rejected
  alternatives" section AND there's no realistic upgrade scenario
  (e.g., "we rejected UUIDs because they're not readable" — there's no
  world where Folio adds UUID support, no card needed)
- The thing punted is small enough to live as a `TODO` comment in
  code
- The rejection is structural to the architecture (e.g., "we rejected
  microservices") — that's a meta-decision, not a card

The rule of thumb: **if a reviewer asking "why didn't you build X"
would benefit from a written-up answer beyond what's in the design
doc, make a card.**

## Origin

Pattern named in session 2 of the folio-takehome engagement after
Evan said:

> "when we decide to do these kinds of 'punt for now because it's
> not important' — let's make a focus card at p3 or p4 that just
> explains and parks it as a backlog item."

The first three punt cards (multi-tz, FTS5, schedule-edit-UI) were
created the same session.

## Related

- [[2026-05-18-1735-pattern-focus-task-convention]] — base focus
  card convention this extends
- [[2026-05-18-1740-pattern-collaboration-with-evan]] — punt cards
  are one way "propose-and-wait" decisions land durably
