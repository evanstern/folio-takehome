---
tags: [decision, feature, search]
description: Server-side case-insensitive LIKE substring match on title. Not FTS, not fuzzy. Justified by dataset size and feature scope.
status: proposed
created: 2026-05-18
updated: 2026-05-18
---

# Decision: search is LIKE on title

> **Status: PROPOSED.** Pending Evan's review. Per [[pattern-collaboration-with-evan]],
> civicplus does not finalize design decisions unilaterally.

## Context

README:
> Staff should be able to find a document to share by searching for it
> by title, not just by scrolling a list. Decide what "search" means
> here — exact match, prefix, fuzzy, something else — and justify your
> choice.

The justification is the feature.

## Decision

**Case-insensitive substring LIKE match on `documents.title`, server-side,
no ranking.**

```sql
SELECT ... FROM documents WHERE title LIKE ? COLLATE NOCASE ORDER BY created_at DESC
-- bind: '%' || $q || '%'
```

UI: a search input on `/admin.php` that submits via GET to `?q=...`.
Empty `q` = list all (current behavior). Non-empty = filter. No JS.

## Rejected alternatives

- **SQLite FTS5 virtual table.** Real full-text search with ranking. Genuinely good for a real product. For this exercise: adds a virtual-table migration, an insert/update/delete trigger trio to keep it in sync, a different query syntax, and complexity in the test harness — for a dataset of "however many docs Freddy makes by hand." Wrong tradeoff at this scale. **Worth naming as the obvious next step.**
- **Fuzzy / Levenshtein.** SQLite doesn't have it built in. Pulling in an extension or writing it in PHP is a lot of code for "Freddy mistyped Welcom." LIKE substring already catches typos in the middle of a word.
- **Prefix-only.** Too restrictive — finds "Welcome Packet" only if you type from the start. Substring is the right default.
- **Client-side filter (JS).** Doesn't scale. Loses the chance to demonstrate a server-side query test. Also: no JS anywhere else in the app, would be the most "invented" choice.
- **Searching body, not just title.** README says "by title." Stick to it. Body search would also justify FTS, which we already rejected.

## Trade-offs accepted

- No ranking — results are in `created_at DESC` order, same as the unfiltered list. Predictable.
- LIKE on a non-indexed text column is O(n) — fine at this scale, would be a real problem at 100k+ rows. Worth flagging in the video.
- Case-insensitivity via `COLLATE NOCASE` is ASCII-only; "café" won't match "CAFE." For an internal staff tool that's almost certainly fine; the readme implicitly accepts English-content assumptions.

## Audit log

Searches don't get logged. They're not actions per the README requirement
("Document creation, scheduling changes, and share actions"). If staff
end up searching a lot, that's product analytics, not security audit.

## Talking points for the video

- "Search is the feature where the temptation to over-engineer is highest.
  FTS5 ships in SQLite and would be a real product win. For a dataset
  this size and a 3-hour budget, LIKE is the right call — and the test
  is one line."
- "The interesting question wasn't *what kind of search* — it was *how
  visibly do I justify the simple choice*. Hence this design doc."

## Related
- [[folio-admin-page]]
- [[folio-schema]]
