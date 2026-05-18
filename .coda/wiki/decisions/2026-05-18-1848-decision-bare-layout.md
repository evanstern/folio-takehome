---
tags: [decision, infrastructure, git, worktrees, bare-layout]
description: Repo uses the coda-lite bare layout — .bare/ + sibling worktrees under the project dir, with .coda/ living inside the main/ worktree.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Decision: coda-lite bare-repo layout

> **Status: APPROVED** by Evan during step 0. Direction was
> "this workspace needs to be a .bare repo as well."

## Context

[[2026-05-18-1730-pattern-branching-worktrees]] commits us to parallel feature sessions
on independent worktrees. With a standard non-bare repo, that means
*one* worktree (the checkout) is the privileged "real" one and the
others are attached via `git worktree add ../folio-takehome-<slug>`.
Asymmetric. The "main" working copy is also the project root, so
`.coda/` lives next to `lib/`, `public/`, etc. — which is what the
original layout had.

Evan called for a bare layout instead.

## Decision

Migrate to the **coda-lite bare layout**:

```
~/projects/folio-takehome/                ← project container (NOT a worktree)
  .bare/                                  ← the bare repo (the real git data)
  .git                                    ← pointer file: gitdir: ./.bare
  main/                                   ← worktree on branch main
    .coda/                                ← orchestrator config + wiki
    lib/  public/  tests/  ...            ← the actual app
    .env                                  ← per-worktree, gitignored
  <feature-slug>/                         ← worktree on feat/<slug>
  ...
```

Run via `coda-lite repo_bare_init` — it stages files, moves `.git/` →
`.bare/`, writes the pointer, and creates the `main` worktree
deterministically.

## Why bare-layout wins here

- **Symmetry.** `main/` and `feat/readable-ids/` are peers — neither
  is "more real." A feature session is just `cd <slug>/`, nothing
  about its checkout is structurally different from `main/`.
- **One project root, many branches visible at once.** `ls
  ~/projects/folio-takehome/` shows every active branch as a
  directory. No scattered sibling-of-project-root worktrees.
- **`.coda/` belongs to `main`, full stop.** Feature worktrees see
  `.coda/` only when they merge `main`. They can't accidentally
  commit to it from their branch because it's not on their branch
  yet. Clean ownership boundary.
- **The bare repo is immutable infrastructure.** Nobody works in
  `.bare/`. No accidental `git checkout` thrashing.
- **It's the coda-lite convention.** The tooling already knows this
  shape; we don't fight it.

## Trade-offs

- **One-time migration cost.** Done once via the MCP tool. Reviewers
  cloning fresh don't see this layout — they get a normal clone.
  That's fine; the layout is a development-time concern, not part
  of the deliverable.
- **The `~/projects/folio-takehome/` path now has two meanings**:
  before migration it was the working tree; after, it's the project
  container and you `cd main/` to actually work. Anyone (including
  future-civicplus rebooting cold) needs to read [[2026-05-18-1730-pattern-branching-worktrees]]
  to know that.
- **Docker compose project name was a casualty.** See
  [[2026-05-18-1851-decision-compose-project-name-pinned]] — same session, this is
  the immediate downstream issue we hit and fixed.
- **`db.sqlite` now lives at `main/db.sqlite`** instead of the project
  root. Already gitignored, no submission impact.
- **The `civicplus` symlink (`~/agents/civicplus`) had to be repointed**
  from `~/projects/folio-takehome/.coda` to
  `~/projects/folio-takehome/main/.coda`. Done.

## What changes for feature sessions

The brief template in [[2026-05-18-1610-pattern-feature-session-brief]] now points each
session at `~/projects/folio-takehome/<slug>/` instead of a
parallel-to-project-root sibling. The `git worktree add` command runs
from anywhere inside the project (the `.git` pointer file resolves to
`.bare/`).

## What does NOT change

- Branch names, merge order, PR conventions — all unchanged
- The actual app code's relationship to its config — `.coda/` still
  lives next to `lib/` and `public/` within whichever worktree owns it
- Docker still works from each worktree dir (see [[2026-05-18-1851-decision-compose-project-name-pinned]])

## Talking points for the video

- "We restructured the repo to a bare layout once we committed to
  parallel feature worktrees. It's not the layout a reviewer sees on
  a fresh clone — that's still a normal clone — but it's the layout
  the orchestrator uses to coordinate parallel work. Symmetric,
  predictable, and `.coda/` ownership is unambiguous."

## Related
- [[2026-05-18-1730-pattern-branching-worktrees]]
- [[2026-05-18-1851-decision-compose-project-name-pinned]]
- [[2026-05-18-1620-decision-coda-dir-shipped]]
