# EPIC-17 — Audit Trail Viewer

> Read-only PM window into the append-only audit log built in US-00.5. Yeis can filter by
> asset, store, job, actor, action type, or date range; read before/after diffs clearly; and
> export entries for compliance or incident investigation. No edit, no delete, ever.

Related: US-00.5 (audit foundation), EPIC-02–EPIC-16 (entities being audited). Sprint: 10.

---

## US-17.1 — PM audit-log viewer with filtered, paginated read-only entries

**As** Yeis (PM)
**I want** to browse and filter the platform's audit log by asset, store, job, actor, action
type, and date range — with before/after diffs rendered clearly — paginated for large logs
**So that** I can investigate incidents, verify data integrity, and satisfy any future audit
without touching raw database records.

**Estimate:** 8 · **Priority:** P2 · **Depends on:** US-00.5, US-01.1, US-01.2, US-01.3 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** Yeis is authenticated as `pm`, **when** she opens `/audit`, **then** the audit log
  viewer loads with the most recent entries first, paginated at a sensible page size (e.g. 50
  rows), and the page title and breadcrumb make clear she is viewing a read-only log.
- **Given** the audit log page, **when** it loads with zero entries matching the current
  filters, **then** a designed empty state is shown ("No audit entries match your filters") —
  never a blank table or a raw "0 results".
- **Given** the audit log page, **when** the entries are loading (first render or after a
  filter change), **then** a loading indicator replaces the table body — the page does not
  flash blank.
- **Given** a large log, **when** Yeis pages through results, **then** the query uses keyset
  or offset pagination indexed on `created_at` + `id` so it does not perform full-table scans
  on large datasets; verified by inspecting the query with `EXPLAIN` during development.
- **Given** the filter panel, **when** Yeis applies any combination of:
  - **Asset** (searchable select, by `asset_code` or `asset_name`)
  - **Store** (searchable select)
  - **Job** (searchable select, by `job_reference`)
  - **Actor** (searchable select, by user name / id)
  - **Action type** (multi-select, values sourced from the `action` enum/values in the audit
    schema defined by US-00.5)
  - **Date range** (from / to, date-only pickers, inclusive)
  then **only matching rows are returned** and the active filters are clearly displayed so Yeis
  knows what she is looking at.
- **Given** a single audit entry in the table, **when** Yeis expands or views its detail,
  **then** she can see: UTC timestamp (with local-time tooltip), actor name + role, action
  type, target entity (model + ID + a human-readable label if available), IP address, user
  agent, and a **before/after diff** rendered as a two-column key-value comparison (added,
  removed, and changed fields visually distinguished — not raw JSON).
- **Given** an audit entry with no `before` value (e.g. a create event), **when** displayed,
  **then** the before column shows "—" or "New record" rather than blank or null.
- **Given** an audit entry with no `after` value (e.g. a delete/decommission event), **when**
  displayed, **then** the after column shows "—" or "Record removed" rather than blank or null.
- **Given** the viewer at any point, **when** any UI element is inspected, **then** there is
  **no button, link, or form** that allows editing or deleting any audit entry — not even for
  a `pm`; the read-only constraint is enforced in the controller and policy, not just hidden
  in the UI.
- **Given** a `technician` (or any non-`pm` user), **when** they attempt to reach `/audit` or
  any audit endpoint, **then** they receive a 403 and are shown a plain-language denial page.
- **Given** a guest (unauthenticated) visitor, **when** they attempt to reach `/audit`,
  **then** they are redirected to the login page.
- **Given** the audit schema from US-00.5, **when** the filter and display code is written,
  **then** all column and relation names are verified against the migration before use — no
  guessing.

### Engineering Bar checklist

- **Secure:** Route guarded by `auth` + `role:pm` middleware — no other role or guest can
  reach audit endpoints. Controller calls `$this->authorize()` against an `AuditPolicy` (view
  only; no `update`/`delete` methods defined). The `AuditLog` model has no `update()` or
  `delete()` method (enforced by US-00.5); the viewer adds no new mutation surface. Filter
  inputs are validated and sanitised server-side (Form Request); only columns in the allow-list
  are used in `WHERE` clauses — no raw user input injected into SQL. `client_id` scoping:
  the audit log captures ONYX-wide data; in v1 PMs see all entries, which is correct. When
  `client_user` (Rosie) is enabled in v2, she must **never** see the audit log — the policy
  must explicitly deny `client_user` now, not later.
- **Clean:** Livewire component for filter state + table (PM desktop surface, per ADR-001).
  Reuse the existing searchable-select component if one exists; don't duplicate. Pagination via
  Laravel's built-in paginator (cursor or length-aware, chosen for the data volume). Before/after
  diff rendering is a dedicated Blade partial so it can be reused if a diff view appears on
  entity detail pages later (YAGNI boundary: extract only if actually reused). No dead code.
- **UX:** Clear page hierarchy — heading, active-filter chips, results count, table, pagination.
  Active filters shown as dismissible chips above the table so Yeis always knows her current
  scope. Before/after diff uses colour and symbol cues (green added / red removed / amber
  changed) with sufficient contrast (WCAG AA). Empty, loading, and error states all designed.
  Plain-language Australian English throughout — "No audit entries match your filters" not
  "0 results"; "Something went wrong loading the audit log" not "500 error". Tap targets not
  a concern here (PM desktop surface), but keyboard navigation and focus order must be logical.
- **No guessing:** Read the `audit_logs` migration produced by US-00.5 before writing any
  query or filter. Verify every column name (`auditable_type`, `auditable_id`, `old_values`,
  `new_values`, `event`, `user_id`, `user_type`, `ip_address`, `user_agent`, `created_at` —
  or whatever the migration defines) with `php artisan tinker` before use. If the column shape
  differs from those names, match the migration, not these notes.

### Definition of Done

- Audit viewer route (`/audit`) exists, guarded by `auth` + `role:pm`; 403 for technician
  and guest verified by feature tests.
- All six filter types work in combination; verified by feature tests (apply each filter in
  isolation + at least one combined case).
- Pagination tested with a seeded dataset large enough to span multiple pages.
- Before/after diff renders correctly for create (no before), update (before + after), and
  decommission/delete (no after) event shapes — each covered by a unit test on the diff
  partial/presenter.
- `AuditPolicy` has no `update` or `delete` gate; confirmed by test that attempting `PUT`/
  `DELETE` to any audit endpoint returns 405 or 403.
- `client_user` explicitly denied in `AuditPolicy` — test proves Rosie's fixture cannot
  reach the viewer.
- Pint + Larastan clean; one happy-path integration test (PM loads viewer, applies asset
  filter, sees correct entries).
- Loading, empty, and error states render; verified by browser smoke-test.
- Before/after diff colours pass WCAG AA contrast check.
- Australian English copy reviewed; no "you have no items" or generic framework strings
  visible to the user.

---

## US-17.2 — Audit-log CSV export

**As** Yeis (PM)
**I want** to export the currently filtered audit log as a CSV file
**So that** I can share audit evidence with clients, supply records to an external auditor, or
archive a snapshot without granting third-party access to the live platform (SRA §14.5 —
"read-only at the application level"; §13 reporting pattern).

**Estimate:** 3 · **Priority:** P2 · **Depends on:** US-17.1 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** Yeis has applied filters in the audit viewer (US-17.1), **when** she clicks
  "Export CSV", **then** a CSV download begins containing **only the rows matching the current
  filters** — not the full unfiltered log.
- **Given** no filters applied, **when** Yeis exports, **then** the CSV contains all audit
  entries (subject to any system row limit; see below).
- **Given** a very large result set (e.g. > 10,000 rows), **when** Yeis requests an export,
  **then** the export is streamed (Laravel's `StreamedResponse` / chunked query) so it does
  not exhaust server memory — it must not load all rows into memory at once.
- **Given** the CSV, **when** opened in a spreadsheet tool, **then** columns include at
  minimum: `timestamp_utc`, `actor_name`, `actor_role`, `action`, `target_model`, `target_id`,
  `target_label`, `ip_address`, `user_agent`, `changed_fields` (a flat summary of keys that
  changed, e.g. `"status, notes"`), `old_values` (JSON string), `new_values` (JSON string).
- **Given** the export endpoint, **when** a `technician`, `client_user`, or guest attempts
  access, **then** they receive 403 — same policy as US-17.1.
- **Given** the CSV content, **when** it includes user-supplied strings (e.g. notes fields),
  **then** values are properly quoted and escaped so that CSV injection (formula injection) is
  not possible — cells beginning with `=`, `+`, `-`, or `@` are prefixed with a tab or single
  quote on write.
- **Given** a successful export, **when** the download completes, **then** the filename
  includes the export date and any active primary filter (e.g.
  `audit-log-2026-06-24-store-PAN-SYD-001.csv`) so Yeis can identify it in her downloads
  folder.

### Engineering Bar checklist

- **Secure:** Export route guarded by `auth` + `role:pm`; same `AuditPolicy` view gate as
  US-17.1; no mutation surface added. Filter inputs re-validated server-side on the export
  request (do not trust query-string parameters blindly — validate + sanitise the same way as
  the viewer Form Request). CSV injection prevention applied to all user-supplied string
  columns. The export endpoint does not expose columns absent from the viewer (no bonus data
  leak). Rate-limit the export endpoint (e.g. 10 exports/minute per user) to prevent
  accidental or deliberate log scraping.
- **Clean:** Reuse the filter validation logic from US-17.1 (extract to a shared Form Request
  or query builder service if not already done). Chunked query (`LazyCollection` / `chunk()`)
  on the export path — do not duplicate the whole query builder. No speculative streaming
  abstraction; plain `StreamedResponse` + `fputcsv` is sufficient.
- **UX:** "Export CSV" button is visually secondary to the filter/table primary actions —
  present but not dominant. Show a brief "Preparing export…" state if the response is not
  instant. If the result set is empty, disable the export button with a tooltip ("No entries
  to export") rather than delivering an empty file silently.
- **No guessing:** Confirm `StreamedResponse` + `fputcsv` handles the target MySQL/Postgres
  driver correctly by testing with a seeded dataset before marking done. Verify CSV injection
  prefix behaviour against actual spreadsheet tools (LibreOffice Calc / Excel) if there is any
  doubt.

### Definition of Done

- Export route exists, returns `text/csv` with `Content-Disposition: attachment`; verified
  by a feature test that downloads the file and asserts column headers + row count match the
  filtered dataset.
- Streaming verified: export of 500+ rows completes without memory spike (smoke test with
  seeded data).
- CSV injection test: a seeded audit entry whose `new_values` contains `=SUM(A1)` must appear
  in the CSV with the injection-safe prefix — covered by a unit test on the CSV writer.
- `technician` and guest denied — feature test proves 403.
- Rate limiter applied; test confirms the 11th request within the window returns 429 with a
  plain "try again shortly" message.
- Empty-result export button is disabled — verified by browser smoke-test.
- Filename includes date and active filter — verified by feature test on the
  `Content-Disposition` header.
- Pint + Larastan clean; Australian English on all user-facing strings.
