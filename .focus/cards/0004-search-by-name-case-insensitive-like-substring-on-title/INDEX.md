---
schema_version: 2
id: 4
uuid: 019e3d5a-c91e-7258-8c6e-4ffb24451253
title: 'search-by-name: case-insensitive LIKE substring on title'
type: card
status: backlog
priority: p2
project: folio-takehome
created: 2026-05-18
---
## Overview

Staff can find a document by title from the admin page. Search is server-side case-insensitive LIKE substring on `documents.title` (`WHERE LOWER(title) LIKE LOWER('%query%')`). Not FTS5, not fuzzy, not ranked. Justify in video: dataset is small (single-staff seed), FTS5 adds migration complexity for zero practical benefit at this scale, and a reviewer reading `WHERE title LIKE …` understands it in two seconds.

## Design doc

See [.coda/designs/search.md](.coda/designs/search.md) (TBD — written after Evan locks the proposed decision).

Related: [[2026-05-18-1645-decision-search-like]] — `status: proposed`.

## Acceptance

- [ ] `admin.php` gains a search input that GETs `?q=<query>` and re-renders the doc list filtered
- [ ] Empty `q` shows the full list (no breaking change to current behavior)
- [ ] SQL uses parameterized LIKE with `%query%` wildcards; user input goes through PDO bind, NOT string interpolation
- [ ] Result list reuses the existing doc-row rendering — no separate "search results" template
- [ ] One test in `tests/test.php` covers: exact-title match, substring match, case-insensitive match, no-match returns empty list, SQL-injection input (e.g. `'; DROP TABLE`) is safe

## Blocks

Depends on card #1 (migrations-infra) ONLY if we add an index. For LIKE-substring on a small dataset, no index needed — could ship without touching schema. Design doc decides.

## Tags

`feature`, `search-by-name`, `p2`
