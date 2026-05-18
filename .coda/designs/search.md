---
tags: [design, feature, search]
description: Server-side case-insensitive LIKE substring match on document title.
status: skeleton-awaiting-decision
created: 2026-05-18
updated: 2026-05-18
---

# Design: search by title

> **Status: SKELETON.** Body fills in once Evan locks
> [[2026-05-18-1645-decision-search-like]] from `status: proposed` to
> `status: approved`. The shape below assumes the proposed decision:
> `WHERE LOWER(title) LIKE LOWER('%q%')`, no FTS, no fuzzy, no ranking.

## Problem

> README: "Staff should be able to find a document to share by searching
> for it by title, not just by scrolling a list. Decide what 'search'
> means here — exact match, prefix, fuzzy, something else — and justify
> your choice."

The interesting call is the "what does search mean" definition. The
README explicitly invites a justified choice over a feature-checklist.

## Decision pending Evan

[[2026-05-18-1645-decision-search-like]] — case-insensitive LIKE
substring on title. **Civicplus recommends.**

## Scope

- [TBD on lock] `admin.php`: add a `<form method="GET">` with a single
  `q` text input
- [TBD on lock] Server-side: if `$_GET['q']` is set and non-empty, filter
  the doc list with parameterized LIKE; otherwise show full list
- [TBD on lock] Re-render the existing doc-row template; no new "search
  results" page
- [TBD on lock] No schema changes. No index. No FTS. No new audit-log
  events (search is read-only).

## Schema impact

**None.** No migration required for this feature.

(For dataset sizes this take-home reaches — handfuls to low hundreds of
docs — sequential scan on `title` is faster than maintaining an FTS5
virtual table. Justify in video.)

## Audit-log impact

**None.** Search is a read-only browse. Per [[2026-05-18-1600-pattern-audit-log]],
audit events are for create/schedule/share lifecycle — not reads.

The README requirement is "Document creation, scheduling changes, and
share actions" — search isn't on that list.

## Query shape

```php
if ($q = trim($_GET['q'] ?? '')) {
    $stmt = $db->prepare(
        "SELECT * FROM documents
         WHERE LOWER(title) LIKE LOWER(:q)
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

PDO bind handles the SQL-injection vector. The user-supplied `%` is
NOT escaped — wildcards in user input expand search. That's a feature
(`*` is conventional shorthand for users), not a bug.

## Test plan

In `tests/test.php`:

- `test('search exact title')` — seed 3 docs, search the full title of
  one, assert single result
- `test('search substring case-insensitive')` — search lowercase fragment
  of a mixed-case title, assert match
- `test('search no match returns empty')` — search nonsense, assert empty
- `test('search SQL injection input is safe')` — search
  `"'; DROP TABLE documents; --"`, assert empty results AND `documents`
  table still exists
- `test('empty q shows full list')` — submit empty `q`, assert all docs
  returned

## Rejected alternatives

- **SQLite FTS5 virtual table.** Rejected: requires migration + new
  virtual table + sync triggers. For dataset sizes this app reaches,
  zero practical benefit. Adds reviewer cognitive load.
- **Fuzzy match (Levenshtein, soundex).** Rejected: ambiguous UX
  ("did you mean…?"), and SQLite needs an extension for it. Overkill.
- **Prefix-only match (`LIKE 'q%'`).** Rejected: substring is what users
  expect from a doc list ("find anything containing X"). Prefix is for
  autocomplete, not list filter.
- **Ranking / relevance scoring.** Rejected: doc list, not search engine.
  Recency order (existing `ORDER BY created_at DESC`) is the right sort.
- **Client-side filter (JS over the full doc list).** Rejected: adds JS
  to a zero-JS codebase and breaks if list grows beyond browser-tractable.
- **Separate `/search` page.** Rejected: filtered doc list is the same
  doc list. Reusing the existing template is honest UX and zero new
  code paths.

## Video talking points

- "The README invited a justified choice. I picked the simplest thing
  that works: case-insensitive LIKE substring on title. FTS5 was the
  obvious 'show off' option, and I rejected it deliberately — the
  dataset is small, the maintenance cost is real, and the reviewer
  understands LIKE in two seconds."
- "Search is a read action. The audit-log requirement was creation,
  scheduling, and share actions. Searching for a doc isn't on that
  list, so this feature is the only one of the three with no audit
  events. That asymmetry is correct, not an oversight."
- "I parameterized the query with PDO bind. The user-supplied wildcards
  (`%`) are intentionally not escaped — power users get wildcard search
  for free. SQL-injection is closed at the bind layer."

## Related

- [[2026-05-18-1645-decision-search-like]]
- [[folio-schema]]
- [[folio-admin-page]]
- [[2026-05-18-1600-pattern-audit-log]] — explicitly NOT triggered here
- Focus card #4
