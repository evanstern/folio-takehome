---
tags: [pattern, workflow, git, worktrees]
description: Git worktrees per feature, branched off main. The branching convention for parallel feature sessions.
created: 2026-05-18
updated: 2026-05-18
---

# Pattern: branching via worktrees

Per Evan's directive, the repo at `~/projects/folio-takehome` uses a
**worktrees-per-feature** branching strategy. Each feature session gets
its own working copy on its own branch, all sharing the same `.git/`.

## Why

- **Parallelism without context switching.** Civicplus spawns three feature
  sessions in parallel. Each needs its own checkout — but cloning the repo
  three times is wasteful and creates `.coda/` divergence.
- **`.coda/` lives in main, sessions see it as-is.** Worktrees share the
  same `.git/`, so any inline commit civicplus makes to `.coda/memory/` or
  `.coda/wiki/` is immediately visible to all running sessions on their
  next `git fetch` / `git pull` (or `git merge main`).
- **Branch isolation is real.** A worktree on `feat/readable-ids` can't
  accidentally touch files on `main` or another worktree's branch.

## Layout

```
~/projects/folio-takehome/                   ← main checkout (civicplus lives here)
~/projects/folio-takehome-migrations/        ← worktree on feat/migrations-infra
~/projects/folio-takehome-scheduling/        ← worktree on feat/scheduled-publishing
~/projects/folio-takehome-readable-ids/      ← worktree on feat/readable-ids
~/projects/folio-takehome-search/            ← worktree on feat/search-by-name
```

All worktrees branch from `main`. Migrations-infra lands first; the other
three rebase onto the resulting main before opening their PRs.

## Commands

Create a worktree:
```sh
cd ~/projects/folio-takehome
git worktree add ../folio-takehome-<slug> -b feat/<slug> main
```

List:
```sh
git worktree list
```

Tear down (after PR merge):
```sh
git worktree remove ../folio-takehome-<slug>
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
- Owns merge order: migrations-infra first, then the three features in dependency-aware order
- Cleans up worktrees after PR merge

## Related
- [[pattern-feature-session-brief]]
- [[decision-migrations-shape]]
