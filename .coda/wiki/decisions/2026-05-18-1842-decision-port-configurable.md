---
tags: [decision, infrastructure, dev-loop, pushback]
description: Host port is configurable via FOLIO_PORT in .env. Default 8000 (matches README). Pushback moment — civicplus argued for local-only, Evan overruled.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Decision: configurable port via .env

> **Status: APPROVED** by Evan during step 0 sanity check.

## Context

`docker compose up` failed on the first sanity check because port 8000
was already taken by portainer on Evan's machine. The README hard-codes
`http://localhost:8000` as the access URL.

Civicplus surfaced three options:

1. **Edit `docker-compose.yml` to use 8088:8000** as a local-only fix
   (the docker-compose change wouldn't get committed; reviewers cloning
   fresh still get 8000).
2. **Stop portainer** for the session, run folio at 8000 to match the
   README exactly.
3. **Permanently change to 8088 and update the README** — diverges from
   the spec, flagged as not-recommended.

Civicplus recommended option 1.

## What Evan said

> "No. Make port configurable via .env (and log this as a decision
> where I pushed back)"

## Civicplus's pushback (and why Evan was right)

Civicplus's instinct was the smallest possible change: tweak the
docker-compose locally, don't commit it. That's a "this works for me
right now" fix.

Evan's call is structurally better:

- **The conflict isn't a one-off.** Anyone running portainer, jenkins,
  airflow, or half a dozen other tools has port 8000 taken. The next
  reviewer who hits this gets the same wall civicplus did, with no
  graceful out.
- **`.env` is the idiomatic answer.** It's the standard pattern for
  dev-time config. Reviewers know it. It costs ~10 lines of total
  change and adds zero runtime dependency.
- **Default unchanged.** `FOLIO_PORT=8000` is the default, so the
  README's stated `localhost:8000` still works on a fresh clone. The
  override is opt-in.
- **It's a quality signal.** The README rewards "judgment as much as
  code." Recognizing a real friction point and fixing it cleanly — vs.
  hiding it in a local config — is the judgment.

Civicplus's option-1 framing was budget-paranoid: "smallest change,
don't burn time." Evan's framing was reviewer-paranoid: "make this
robust for the next person." Reviewer-paranoid wins here because the
exercise *is* the reviewer.

## Implementation

Four small changes:

- `.env.example` — committed, documents the variable and the default
- `.env` — gitignored, holds the actual override per developer
- `docker-compose.yml` — uses `${FOLIO_PORT:-8000}` for both the host
  port mapping and a `FOLIO_PORT` env passed to the container
- `seed.php` — reads `getenv('FOLIO_PORT')` to print the correct admin
  + share URLs at startup. Default `8000` if unset.
- `.gitignore` — adds `.env`

The container always listens on 8000 internally; only the host-side
port is configurable. Keeps the docker-side simple and the PHP code
ignorant of the host port except for printed startup URLs.

## Audit / migration / test impact

None. This is dev-loop scaffolding, not app behavior. No schema change,
no migration, no test required. The existing test (`tests/test.php`)
hits the DB directly, not the HTTP surface, so it's unaffected.

## Trade-offs accepted

- Tiny config surface to learn (one variable, one file). Mitigated by
  `.env.example` documenting it and the default matching the README.
- The submission now has an `.env.example` file that didn't exist
  before — slightly more for a reviewer to skim. Worth it.

## Talking points for the video

- "First friction moment of the engagement: port 8000 was taken on the
  host machine. My instinct was a local-only docker-compose tweak.
  Evan pushed back — 'make it configurable via .env, log this as a
  decision where I pushed back.' He was right. The conflict isn't a
  one-off; .env is the idiomatic answer; default is unchanged. This is
  the kind of pushback the README explicitly asks about."

## Related
- [[folio-docker]]
- [[2026-05-18-1740-pattern-collaboration-with-evan]]
