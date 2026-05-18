---
tags: [pattern, workflow, git, worktrees]
description: Git worktrees per feature, branched off main. The branching convention for parallel feature sessions.
created: 2026-05-18
updated: 2026-05-18
---

# Pattern: branching via worktrees

Per Evan's directive, the repo at `~/projects/folio-takehome` uses the
**coda-lite bare layout** with worktrees-per-feature branching. See
[[2026-05-18-1848-decision-bare-layout]] for the migration rationale. The project
directory is a container; each branch lives in its own sibling worktree,
all backed by the bare repo at `.bare/`.

## Why

- **Parallelism without context switching.** Civicplus spawns three feature
  sessions in parallel. Each needs its own checkout — but cloning the repo
  three times is wasteful and creates `.coda/` divergence.
- **`.coda/` lives in `main/`, sessions see it as-is.** Worktrees share
  the same bare repo at `.bare/`, so any inline commit civicplus makes
  to `.coda/memory/` or `.coda/wiki/` on `main` is immediately visible
  to all feature worktrees on their next `git fetch` / `git merge main`.
- **Branch isolation is real.** A worktree on `feat/readable-ids` can't
  accidentally touch files on `main` or another worktree's branch.
- **Symmetry.** `main/` and `feat/<slug>/` worktrees are structurally
  identical — neither is privileged. Easier to reason about.

## Layout

```
~/projects/folio-takehome/                   ← project container (NOT a worktree)
  .bare/                                     ← bare repo (the real git data)
  .git                                       ← pointer file: gitdir: ./.bare
  main/                                      ← main worktree (civicplus's home; .coda/ lives here)
  migrations-infra/                          ← worktree on feat/migrations-infra
  scheduled-publishing/                      ← worktree on feat/scheduled-publishing
  readable-ids/                              ← worktree on feat/readable-ids
  search-by-name/                            ← worktree on feat/search-by-name
```

All worktrees branch from `main`. Migrations-infra lands first; the other
three rebase onto the resulting main before opening their PRs.

## Commands

Create a worktree (from anywhere inside the project; the `.git` pointer
finds the bare repo):
```sh
cd ~/projects/folio-takehome
git worktree add ./<slug> -b feat/<slug> main
```

List:
```sh
cd ~/projects/folio-takehome/main
git worktree list
```

Tear down (after PR merge):
```sh
git worktree remove ~/projects/folio-takehome/<slug>
git branch -d feat/<slug>
```

## Conventions

- **Branch name format:** `feat/<slug>` matching the design-doc slug
- **Branch off `main`**, always
- **Rebase onto main** before opening the PR (especially after migrations-infra lands)
- **One PR per worktree.** Don't combine features even if the worktree somehow has unrelated changes.

## Civicplus's responsibility

- Creates the worktrees before spawning feature sessions
- Tells each feature session its worktree path + branch name in the brief
- Assigns a `FOLIO_PORT` and `COMPOSE_PROJECT_NAME` per worktree if
  parallel docker runs are needed (see [[2026-05-18-1851-decision-compose-project-name-pinned]])
- Owns merge order: migrations-infra first, then the three features in dependency-aware order
- Cleans up worktrees after PR merge

## Docker note

Each worktree has its own `.env` (gitignored). For concurrent docker
runs, give each worktree a unique `FOLIO_PORT` AND
`COMPOSE_PROJECT_NAME` so containers don't collide. `main` keeps the
defaults (`folio-takehome` / 8000 or whatever Evan's local override is).
See [[2026-05-18-1842-decision-port-configurable]] and
[[2026-05-18-1851-decision-compose-project-name-pinned]].

## Related
- [[2026-05-18-1610-pattern-feature-session-brief]]
- [[2026-05-18-1848-decision-bare-layout]]
- [[2026-05-18-1851-decision-compose-project-name-pinned]]
- [[2026-05-18-1842-decision-port-configurable]]
- [[2026-05-18-1630-decision-migrations-shape]]
