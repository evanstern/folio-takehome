---
tags: [design, feature, search]
description: Server-side case-insensitive LIKE substring match on document title. No FTS, no fuzzy, no ranking.
status: approved
created: 2026-05-18
updated: 2026-05-18
---

# Design: search by title

> **Status: APPROVED.** Locked in [[2026-05-18-1645-decision-search-like]]
> (session 2, surfaced via focus card + skeleton, no objections). FTS5
> captured as a punted backlog focus card.

## Problem

> README: "Staff should be able to find a document to share by searching
> for it by title, not just by scrolling a list. Decide what 'search'
> means here — exact match, prefix, fuzzy, something else — and justify
> your choice."

The justification IS the feature.

## Locked decisions

From [[2026-05-18-1645-decision-search-like]]:

- **Case-insensitive LIKE substring on `documents.title`.**
- Server-side. No JS. No ranking. No FTS. No fuzzy.
- UI: `<form method="GET">` with single `q` input on `admin.php`.
  Empty `q` = list all (current behavior). Non-empty = filter.
- No schema changes. No new audit-log events (search is read-only).

## Scope

Single feature session delivers:

1. **`admin.php` form addition:** `<form method="GET" action="admin.php">
   <input name="q" type="text" placeholder="Search by title…"
   value="<?=htmlspecialchars($q)?>"></form>` above the doc list.
2. **Filtered query in `admin.php`:**
   ```php
   $q = trim($_GET['q'] ?? '');
   if ($q !== '') {
       $stmt = $db->prepare(
           "SELECT * FROM documents
            WHERE title LIKE :q COLLATE NOCASE
            ORDER BY created_at DESC"
       );
       $stmt->execute([':q' => '%' . $q . '%']);
   } else {
       $stmt = $db->query(
           "SELECT * FROM documents ORDER BY created_at DESC"
       );
   }
   $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
   ```
3. **Reuse existing doc-row rendering.** No separate "search results"
   page or template.
4. **No migration.** No schema changes. No index (LIKE on a small
   text column is O(n); fine at this scale).
5. **Tests:** five per "Test plan."

Out of scope:
- FTS5 virtual table + sync triggers (rejected; captured as punt card)
- Fuzzy / Levenshtein
- Body-content search (README says "by title")
- Result highlighting
- Client-side JS filter

## Schema impact

**None.** No migration required.

For dataset sizes this take-home reaches (handfuls to low hundreds of
docs), sequential scan on `title` is faster than maintaining an FTS5
virtual table.

## Query shape

```php
$q = trim($_GET['q'] ?? '');
if ($q === '') {
    $stmt = $db->query("SELECT * FROM documents ORDER BY created_at DESC");
} else {
    $stmt = $db->prepare(
        "SELECT * FROM documents
         WHERE title LIKE :q COLLATE NOCASE
         ORDER BY created_at DESC"
    );
    $stmt->execute([':q' => '%' . $q . '%']);
}
```

Notes:
- `COLLATE NOCASE` is SQLite's case-insensitive collation — ASCII only.
  `"café"` won't match `"CAFE"`. Acceptable for English-content
  staff tool; worth noting in the video.
- PDO `:q` bind escapes SQL injection.
- User-supplied `%` is NOT escaped — wildcards in user input are a
  feature ("show me anything containing X"), not a bug. SQL injection
  is closed at the bind layer regardless.

## Audit-log impact

**None.** Per [[2026-05-18-1600-pattern-audit-log]] and the README
requirement ("Document creation, scheduling changes, and share actions"),
audit events are for create/schedule/share — not reads.

This is the one feature of the three with no audit-log surface, and
that asymmetry is correct.

## Test plan

In `tests/test.php`, add:

- `test('search exact title')` — seed 3 docs with distinct titles,
  search the full title of one, assert single matching result
- `test('search substring case-insensitive')` — title `"Welcome to
  Folio"`, search `"welcome"` and `"FOLIO"`, both return the doc
- `test('search no match returns empty')` — search a nonsense string,
  assert empty result list
- `test('search SQL injection input is safe')` — search
  `"'; DROP TABLE documents; --"`, assert empty result AND
  `documents` table still exists with all seeded rows
- `test('empty q returns full list')` — submit empty `q`, assert all
  seeded docs returned in `created_at DESC` order

## Rejected alternatives

Captured in [[2026-05-18-1645-decision-search-like]]:

- **SQLite FTS5 virtual table** — real full-text search with ranking.
  Adds a virtual-table migration + sync trigger trio + new query
  syntax for a dataset of "however many docs staff makes by hand."
  Punted to a backlog focus card so a reviewer can see we considered it.
- **Fuzzy / Levenshtein** — SQLite needs an extension. Overkill.
- **Prefix-only (`LIKE 'q%'`)** — too restrictive for a doc list filter.
- **Client-side JS filter** — adds JS to a zero-JS codebase, doesn't
  scale beyond browser-tractable list sizes.
- **Body search** — README says "by title." Stick to it.
- **Ranking / relevance** — doc list, not search engine. Recency is
  the right sort.
- **Separate `/search` page** — same data, same template. New page
  adds code paths for no UX win.

## Video talking points

- "Search is where the temptation to over-engineer is highest. FTS5
  ships with SQLite and would be a real product win. For this dataset
  and budget, LIKE is the right call — and the test is one line."
- "I parameterized with PDO bind. User-supplied wildcards (`%`) aren't
  escaped — power users get wildcard search for free. SQL injection
  is closed at the bind layer."
- "Search is the one feature of the three with no audit-log surface.
  The README requirement was create / schedule / share — not reads.
  That asymmetry is correct, not an oversight."
- "FTS5 is parked as a backlog focus card with the migration sketch
  and the threshold I'd ship it at. Showing the rejected option as a
  written-up upgrade path is the judgment beat the README asks for."

## Related

- [[2026-05-18-1645-decision-search-like]]
- [[folio-schema]]
- [[folio-admin-page]]
- [[2026-05-18-1600-pattern-audit-log]] — explicitly NOT triggered here
- Focus card #4 `search-by-name`
- Punted focus card: FTS5 upgrade (created in step 6c)
