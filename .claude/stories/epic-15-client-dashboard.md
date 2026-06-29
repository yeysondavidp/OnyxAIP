# EPIC-15 — Client Dashboard

> PM-facing aggregated view at the client level: store counts by state, asset counts by type and
> status, open faults drillable to store, overdue (SLA-breached) jobs, recent service activity,
> and a full CSV export of all client assets. Strictly `client_id`-scoped — no cross-tenant
> aggregates ever. Designed scope-aware so a future read-only client view (Rosie, v2) is a
> thin permission addition, not a rework.
>
> Each story carries an **Engineering Bar checklist** — the four non-negotiables from `/CLAUDE.md`
> turned concrete for this story. Tick every box before the story is Done. The global Definition
> of Done in `../README.md` also applies.

Related: EPIC-02 (Client Management), EPIC-03 (Store Management), EPIC-04 (Asset Registry),
EPIC-06 (Asset Status Lifecycle), EPIC-08 (Service Job Management), EPIC-12 (SLA Management),
US-00.4 (scoping foundation), US-00.5 (audit), US-01.3 (policies). Sprint: 10.

---

## US-15.1 — Client-level aggregates (stores by state, assets by type and status, recent service activity)

**As** Yeis (PM)
**I want** a dashboard page for each client showing store counts by state, asset counts by type
and status, and recent service activity
**So that** I can assess a client's overall portfolio health at a glance without hunting across
individual store views.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-02.x (Client model), US-03.x (Store
model), US-04.x (Asset model), US-06.x (asset status lifecycle), US-08.x (Service Job model),
US-00.4 (scoping), US-01.3 (policies) · **Status:** 📋 Ready

### Acceptance criteria

- **Given** Yeis is on the client dashboard for Pandora, **when** the page loads, **then** a
  "Stores by state" panel shows the count of active stores for each Australian state (NSW, VIC,
  QLD, WA, SA, TAS, ACT, NT), with states that have zero stores shown as `0` rather than
  omitted, and the total across all states is also displayed.

- **Given** the same page, **when** the asset counts panel renders, **then** it shows asset counts
  broken down by type (Digital Screen, Media Player, Lightbox, Window Fixture, Infrastructure)
  and by status (Active, Faulty, Offline, Under Maintenance, Decommissioned), presented as a
  two-axis summary (type rows × status columns), all scoped to this client's assets only.

- **Given** the same page, **when** the recent service activity panel renders, **then** it shows
  the ten most recent service jobs for this client (any status), each showing job reference,
  store name, job type, status, and scheduled date — ordered by scheduled date descending.

- **Given** Pandora has 1,000 active assets, **when** the dashboard loads, **then** all three
  panels render within **2 seconds** (§14.1) — verified by a performance assertion in the
  feature test using `DB::getQueryLog()` to confirm aggregate queries are used (no N+1).

- **Given** Yeis authenticates as a PM, **when** the dashboard URL for any client is opened,
  **then** they can only see the dashboard for clients they are authorised to manage; any attempt
  to open another tenant's client dashboard is denied with a 403.

- **Given** a client with no stores or no assets, **when** the dashboard loads, **then** a
  designed empty state is displayed for each panel — plain-language, non-blaming Australian
  English — not a blank or broken panel.

- **Given** the dashboard is loading aggregate data, **when** the request is in flight, **then** a
  visible loading state is shown (Livewire wire:loading or skeleton) so Yeis knows the page is
  working.

- **Given** a future `client_user` (Rosie), **when** the policy and scoping are reviewed, **then**
  the route, controller, and view are structured so that a `client_user` role scoped to one
  `client_id` could be granted view access in v2 by adding a policy allow — no structural rework
  required (Rosie is inert in v1; the dashboard must not break if the role exists).

### Engineering Bar checklist

- **Secure:** the `ClientDashboardController` authorises against **both** the actor's role (`pm`)
  and the target `client_id` via Laravel policy (Policy + Controller, two layers). The global
  scope from US-00.4 applies to all queries — no raw `WHERE client_id = ?` substituted from
  request input. `client_id` is never read from the request body; it is resolved from the
  authenticated actor's permitted client list and the route model binding. DB-level FK + NOT NULL
  on `client_id` (US-00.4) enforces the constraint even if application guards were bypassed.

- **Clean:** aggregate queries use a single `ClientDashboardRepository` (or dedicated query
  objects) that Livewire or the controller calls — not inline query blobs in Blade. Reuse the
  existing `Asset` scopes and `ServiceJob` scopes from their respective epics. Cache warm
  aggregates in Redis (namespaced per ADR-002: `onyx_aip:dashboard:client:{id}:aggregates`) with
  a short TTL (e.g., 60 seconds) to stay within the 2s budget on 1,000-asset clients without
  stale data becoming misleading. No N+1: stores-by-state, asset-by-type-and-status, and recent
  jobs are each a single aggregate query.

- **UX:** clear visual hierarchy — three panels with labelled headings; the asset type/status
  breakdown is a scannable table or grid, not a raw data dump; designed **empty state** per panel
  (icon + one-line explanation + suggested action); designed **loading state** (skeleton or
  spinner); designed **error state** if queries fail. All copy in Australian English. Dashboard
  optimised for 1280px+ desktop per §14.2; functional at 768px. Primary action per panel
  (e.g., "View all stores", "View all assets") is obvious and one click.

- **No guessing:** before writing query code, verify column names with tinker:
  `App\Models\Store::first()->toArray()`, `App\Models\Asset::first()->toArray()`,
  `App\Models\ServiceJob::first()->toArray()` — confirm `client_id`, `state`, `asset_type`,
  `asset_status`, `job_status`, `scheduled_date` exist as named. Confirm the `Asset` model's
  status enum values match the SRA §4.5 lifecycle before hard-coding them in queries.

### Definition of Done

- `ClientDashboardController` (or Livewire component) authorises via policy; policy covers `pm`
  allow and `client_user` structurally prepared (inert).
- Three aggregate queries verified N+1-free via `DB::getQueryLog()` in tests.
- Redis cache layer in place; TTL documented; cache key namespaced per ADR-002.
- Performance: feature test asserts page loads within 2s for a seeded 1,000-asset client.
- Empty / loading / error states implemented and covered by tests.
- Cross-tenant deny test: PM attempting another tenant's client dashboard gets 403.
- Pint + Larastan clean; Australian English in all UI copy.

---

## US-15.2 — Open faults + overdue jobs + CSV asset export

**As** Yeis (PM)
**I want** to see a count and drillable list of open faults, a list of SLA-breached overdue
jobs, and be able to export all client assets with their current status to CSV
**So that** I can take immediate action on the worst problems for a client and share a complete
asset register with stakeholders without manual spreadsheet work.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-15.1 (client dashboard page exists),
US-06.x (asset status lifecycle), US-08.x (Service Job model), US-12.x (SLA management),
US-00.4 (scoping), US-01.3 (policies) · **Status:** 📋 Ready

### Acceptance criteria

- **Given** Yeis is on the Pandora client dashboard, **when** the "Open faults" panel renders,
  **then** it shows the total count of assets with status `Faulty` or `Offline` for this client,
  followed by a list of those assets (up to 25 per page) showing: AssetCode, AssetName,
  AssetType, current status, store name, and state — each row is a link that drills through
  to the store dashboard (EPIC-08/EPIC-03) for that asset's store.

- **Given** the open faults list has more than 25 results, **when** Yeis reaches the end, **then**
  pagination controls allow navigating to subsequent pages — no silent truncation.

- **Given** a client with no open faults, **when** the open faults panel loads, **then** a
  designed empty state is displayed: "No open faults — all assets are active." in Australian
  English, not a blank panel.

- **Given** the "Overdue jobs" panel, **when** it renders, **then** it shows all service jobs for
  this client where `sla_breached = true` (or where the computed SLA breach condition is met per
  EPIC-12), ordered by scheduled date ascending (oldest breach first), each row showing: job
  reference, job name, store name, job type, scheduled date, current status, and how long
  overdue — displayed in business hours relative to the SLA window per §10.2.

- **Given** a client with no overdue jobs, **when** the overdue panel loads, **then** a designed
  empty state is displayed: "No overdue jobs." — not a blank panel.

- **Given** Yeis clicks "Export assets as CSV" for the current client, **when** the export runs,
  **then** a CSV file is downloaded containing all non-decommissioned assets for that client with
  columns: AssetCode, AssetName, AssetType, Manufacturer, Model, SerialNumber, AssetStatus,
  StoreName, StoreCode, State, InstallDate, WarrantyExpiry — with a filename of
  `{ClientCode}-assets-{YYYY-MM-DD}.csv` in the local date at time of export.

- **Given** a client with 1,000 assets, **when** the CSV export is triggered, **then** it
  completes without timeout — generated using chunked streaming or a queued export with a
  download link (not a single in-memory `collect()` of all rows).

- **Given** Yeis authenticated as PM, **when** she triggers the CSV export for any client,
  **then** the controller re-authorises the export request against her permitted `client_id`
  scope — the `client_id` is never read from request input.

- **Given** a future `client_user` (Rosie), **when** the export route and policy are reviewed,
  **then** they are structured so a `client_user` restricted to her own `client_id` could be
  permitted the export action in v2 by a policy update alone (Rosie is inert in v1).

### Engineering Bar checklist

- **Secure:** the open-faults query, overdue-jobs query, and CSV export controller action each
  authorise via Laravel policy (role `pm` + `client_id` scope) before touching data. `client_id`
  is resolved from the authenticated actor and route model binding — never from the request body.
  The CSV stream does not include any field that would expose another tenant's data; the query
  carries a hard `WHERE client_id = ?` (bound from the policy-verified client record) as a second
  DB-layer guard even with the global scope in place. File download response uses the correct
  `Content-Type: text/csv` and `Content-Disposition: attachment` headers; no MIME-type sniffing
  risk. Rate-limit the export endpoint (§14.3) to prevent bulk scraping.

- **Clean:** open-faults and overdue-jobs queries live in the same `ClientDashboardRepository`
  introduced in US-15.1 — no new query location. CSV generation uses Laravel's `Response::stream`
  or a `StreamedResponse` with chunked DB reads (`chunk(500, ...)`) to avoid loading 1,000 rows
  into memory. Reuse the `sla_breached` field or the SLA computation service from EPIC-12 rather
  than re-deriving the breach logic here. Column headings in the CSV are human-readable Australian
  English labels, not raw DB column names.

- **UX:** open faults panel has a clear count badge (e.g., "12 open faults") as the visual
  anchor before the list; each fault row is a tappable/clickable link — no secondary "Go to
  store" button needed. Overdue panel rows show time-overdue in plain language (e.g., "3 business
  days overdue"), not raw timestamps. CSV export has a single obvious "Export assets as CSV"
  button with a loading/spinner state while generating; on completion, the browser download starts
  automatically. If the export fails, a plain-language inline error appears — not a silent blank
  download. Empty states are designed for both panels (see criteria above). All copy in
  Australian English.

- **No guessing:** before writing the overdue query, verify with tinker that `sla_breached` is
  a stored boolean column on `service_jobs` or confirm the exact computed condition used by
  EPIC-12 — do not assume. Verify the CSV column names against the actual `assets` table columns
  (`asset_code`, `asset_name`, `asset_type`, etc.) via `Schema::getColumnListing('assets')` in
  tinker before writing the export select. Confirm `ClientCode` is the `client_code` column on
  the `clients` table before using it in the filename.

### Definition of Done

- Open-faults panel: count + paginated list (25/page); drill-through links to store dashboard;
  empty state; cross-tenant deny tested.
- Overdue-jobs panel: SLA-breached jobs ordered oldest-first; time-overdue in plain language;
  empty state; reuses EPIC-12 SLA logic (no duplication).
- CSV export: streams in chunks; correct headers; filename pattern `{ClientCode}-assets-{date}.csv`;
  rate-limited endpoint; authorised by policy; tested with a seeded 1,000-asset client — no
  timeout or memory exhaustion.
- All three actions (faults, overdue, export) covered by feature tests: happy path + cross-tenant
  deny (403) + empty-state rendering.
- Pint + Larastan clean; Australian English in all UI copy and CSV column headings.
