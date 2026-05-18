---
tags: [decision, infrastructure, docker, dev-loop]
description: docker-compose project name pinned to "folio-takehome" by default via `name:` field, overridable per worktree via COMPOSE_PROJECT_NAME.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Decision: pin docker-compose project name

> **Status: APPROVED** by Evan during step 0. Direction was
> "docker compose project name cannot change. or at least not on main".

## Context

When we migrated to the [[2026-05-18-1848-decision-bare-layout]], the working directory
for `main` changed from `~/projects/folio-takehome` to
`~/projects/folio-takehome/main`. Docker Compose derives the project
name from the current directory by default, so the container went from
`folio-takehome-app-1` to `main-app-1`.

Two problems with that:

1. **The submission's documented behavior changes.** README implies (and
   it's the default expectation) that the container is namespaced
   under `folio-takehome`. A reviewer running `docker ps` on a fresh
   clone wouldn't see `main-app-1` — they'd see `folio-takehome-app-1`,
   because *they're* running from `~/projects/folio-takehome/`, not
   from `main/`. So the dev-env diverges from the reviewer-env
   silently.
2. **Compose treats every worktree as a different project**, which
   means `main-app-1` and `readable-ids-app-1` and so on would all be
   distinct projects. That part is actually desirable for parallel
   feature work — but only as the *override*, not the default.

## Decision

Pin the compose project name at the top of `docker-compose.yml`:

```yaml
name: ${COMPOSE_PROJECT_NAME:-folio-takehome}
```

- **Default is `folio-takehome`** regardless of the dir name. This
  matches what a fresh-clone reviewer sees and what they'd expect.
- **Feature worktrees can override** by setting
  `COMPOSE_PROJECT_NAME=folio-<slug>` in their `.env`. The .env is
  already gitignored from [[2026-05-18-1842-decision-port-configurable]]; same
  mechanism.

Compose v2 supports the top-level `name:` field; this needs no plugin,
no version bump, no script wrapper.

## Why not the alternatives

- **Rename the `main/` directory to `folio-takehome/`.** Breaks the
  bare layout convention — every worktree would need a different
  directory naming scheme, and the symmetry between `main/` and
  `<slug>/` worktrees disappears.
- **Always `cd ..` to project root before `docker compose up`.** Then
  compose can't find the file. Could be fixed with `-f main/docker-compose.yml`
  but now every dev command is paragraph-long.
- **Set `COMPOSE_PROJECT_NAME` globally in shell env.** Affects every
  project on the developer's machine. Wrong scope.
- **Use `-p folio-takehome` on every `docker compose` invocation.**
  Works, but every command needs the flag and `docker compose ps`
  without the flag would lie. Better to encode it in the file.

## How feature worktrees use the override

In their `.env`:

```sh
FOLIO_PORT=8089
COMPOSE_PROJECT_NAME=folio-readable-ids
```

Then `docker compose up` in `~/projects/folio-takehome/readable-ids/`
produces `folio-readable-ids-app-1` on port 8089, independent of
`main`'s `folio-takehome-app-1` on 8088. Both can run simultaneously.

The feature-session brief ([[2026-05-18-1610-pattern-feature-session-brief]]) should
mention this so each session knows to set it. Civicplus owns assigning
the ports + names to avoid collisions across the three parallel
sessions.

## Trade-offs accepted

- One more line in `docker-compose.yml`. Trivial.
- Reviewers will see the `name:` field and wonder briefly why it's
  there. Mitigated by it being immediately obvious — `folio-takehome`
  is the project name. If anything, it's *more* explicit than relying
  on dir-based inference.
- Feature sessions need to set `COMPOSE_PROJECT_NAME` in their `.env`
  if they want to run docker concurrently with `main`. Documented in
  the brief.

## Verification

Smoke-tested after the change:

```
NAME                   IMAGE                COMMAND                  ...
folio-takehome-app-1   folio-takehome-app   "docker-php-entrypoi…"   ...
```

Container name is back to `folio-takehome-app-1`. Tests pass. `/admin.php`
returns 200.

## Talking points for the video

- "Bare-layout migration broke the docker compose project name —
  compose derives it from the dir, and `main/` produced `main-app-1`
  instead of `folio-takehome-app-1`. Pinning the project name in
  `docker-compose.yml` makes the default stable regardless of which
  worktree you `cd` into, while feature worktrees can still namespace
  their containers via `COMPOSE_PROJECT_NAME` in `.env`. Default
  matches what a fresh-clone reviewer expects."

## Related
- [[2026-05-18-1848-decision-bare-layout]]
- [[2026-05-18-1842-decision-port-configurable]]
- [[folio-docker]]
- [[2026-05-18-1730-pattern-branching-worktrees]]
