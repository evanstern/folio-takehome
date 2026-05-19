---
schema_version: 2
id: 3
uuid: 019e3d5a-c4f2-752a-8de8-fd43ec7b253d
title: 'readable-ids: short doc IDs that complement share tokens'
type: card
status: done
priority: p1
project: folio-takehome
created: 2026-05-18
---
## Overview

Replace document URLs that look like `/d/<int-id>?token=<hex>` with `/d/<readable-id>?token=<hex>`. Readable IDs identify the document; the hex share token still gates recipient access. Format proposal: `<slug-from-title>-<4-char-base32>` (e.g. `welcome-packet-7QX4`) — short, sayable, low collision risk.

The share-token mechanism is NOT replaced. This is a complement, not a replacement, because removing the token would change the privacy model (anyone who guesses or hears a readable ID could read the doc). Justify in video.

## Design doc

See [.coda/designs/readable-ids.md](.coda/designs/readable-ids.md) (TBD — written after Evan locks the proposed decision).

Related: [[2026-05-18-1640-decision-readable-ids-complement]] — `status: proposed`.

## Acceptance

- [ ] Migration adds `documents.readable_id TEXT UNIQUE NOT NULL`
- [ ] Migration backfills all existing rows with a generated `readable_id`
- [ ] Document creation auto-generates a `readable_id` from the title slug + 4-char base32 suffix
- [ ] `view.php` accepts `/d/<readable-id>?token=<hex>`; integer-id route can stay (no breakage) or be removed (TBD in design doc)
- [ ] `share.php` builds the share URL using the `readable_id`
- [ ] `audit_log('create', 'document', …)` details include `readable_id`
- [ ] One test in `tests/test.php` covers: uniqueness on generation, slug formatting (lowercase, hyphen-separated), URL lookup works

## Blocks

Depends on card #1 (migrations-infra). Interacts with card #2 (scheduled-publishing) — readable-id resolution happens BEFORE the publish-at gate in `view.php`.

## Tags

`feature`, `readable-ids`, `p1`
