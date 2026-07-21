# EPIC-03 — Store Management & Store Dashboard

> Stores are the physical anchor point for assets and service jobs. This epic delivers the full
> §3.2 store model with client-scoped CRUD, a filterable store list, and the §8 store dashboard —
> a PM-facing command centre per location.

Related: US-00.4 (client_id scoping trait), US-01.3 (policy pattern), EPIC-02 (clients must
exist before stores). Sprint: 2.

---

## US-03.1 — CRUD stores under a client

**As** Yeis (PM)
**I want** to create, view, edit, and deactivate stores belonging to a client, with all §3.2
fields including state, store type, IANA timezone, and store manager contact details
**So that** every physical retail location is accurately registered and scoped to the correct
client before assets or jobs are attached.

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-00.3, US-00.4, US-00.5, US-01.3,
EPIC-02 · **Status:** ✅ Done

### Acceptance criteria

- **Given** a PM on the new-store form, **when** they submit all required fields, **then** the
  store is created with `client_id` set from the authorised PM context (never from request input),
  and a success message in Australian English is displayed.
- **Given** the `StoreCode` field, **when** a value is submitted, **then** uniqueness is enforced
  at the application level (Form Request) **and** at the DB level (unique index); a duplicate
  returns an inline `@error` message identifying the field — not a generic flash.
- **Given** the `State` field, **when** rendered, **then** it presents exactly the eight
  Australian states/territories as a typed enum: NSW, VIC, QLD, WA, SA, TAS, ACT, NT — no
  free-text entry accepted.
- **Given** the `StoreType` field, **when** rendered, **then** it presents the five §3.2 values
  as an enum: Concept Store, Franchise, Department Store Concession, Pop-Up, Other.
- **Given** the `StoreTimezone` field, **when** submitted, **then** the value is validated as a
  real IANA timezone identifier (e.g. `Australia/Sydney`) server-side; an invalid string is
  rejected with an inline field error.
- **Given** an existing store, **when** a PM edits it, **then** all §3.2 fields are editable and
  the `client_id` cannot be changed via the form (ownership is fixed at creation).
- **Given** `IsActive` toggled to false, **when** saved, **then** the store is soft-deactivated
  and excluded from active lists by default; it remains accessible for historical data.
- **Given** a PM not authorised for the store's client, **when** they attempt any CRUD action,
  **then** the policy returns 403 with a plain-language error — not a stack trace.
- **Given** a CRUD action on any store, **when** it completes, **then** an audit log entry is
  written asynchronously (actor, action, before/after values, UTC timestamp) per US-00.5.
- **Given** the store form, **when** any required field is missing or invalid, **then** inline
  `@error` messages appear next to each failing field; the form is not submitted.

### Engineering Bar checklist

- **Secure:** `client_id` set from authorised context only — never `$request->client_id`;
  `$fillable` lists every accepted column explicitly (no `$guarded = []`); StorePolicy authorises
  by role **and** `client_id` on every action (view, create, update, delete); DB has `client_id
  NOT NULL`, FK to `clients`, index; `StoreCode` unique index in DB, not just Form Request;
  `State` and `StoreType` stored as enum columns (or validated-against fixed list) — no
  free-text injection; timezone validated against `DateTimeZone::listIdentifiers()` or equivalent
  server-side; file access N/A for this story.
- **Clean:** reuse the `client_id` scoping trait from US-00.4; reuse US-01.3 policy pattern;
  State and StoreType enums defined once and shared with the Form Request and the view; no inline
  role checks in controllers — all through `$this->authorize()`; read neighbour models (Client)
  first to confirm relation name and column names before writing.
- **UX:** form fields grouped logically (store identity → address → store manager contact →
  settings); store manager fields clearly labelled as optional; IANA timezone field aided by a
  dropdown pre-filtered to Australian zones (AU covers 5 IANA zones) with a fallback text input;
  designed empty state for the "no stores yet" case; loading state on submit; all inputs ≥44px
  touch target; Australian English throughout.
- **No guessing:** read the `clients` migration/model to confirm the FK column name and whether
  `uuid` or auto-increment is used before writing the `stores` migration; validate timezone
  approach against PHP's `DateTimeZone` API before committing.

### Definition of Done

Migration with all §3.2 columns, FK, indexes, and enum constraints deployed; Store model with
scoping trait and `$fillable`; StorePolicy authorises role + `client_id`; Form Request validates
all fields including timezone; CRUD UI works with inline `@error`; `StoreCode` uniqueness
enforced at DB and application layers; deactivation soft-hides from active lists; audit-logged;
Pint + Larastan clean; one happy-path integration test; cross-tenant deny test.

---

## US-03.2 — Store list filterable by client, state, and type

**As** Yeis (PM)
**I want** a paginated list of stores I can filter by client, state, and store type
**So that** I can quickly locate the store I need across large multi-client deployments without
scrolling through hundreds of entries.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-00.3, US-01.3, US-03.1 · **Status:**
✅ Done

### Acceptance criteria

- **Given** a PM on the store list, **when** the page loads, **then** only stores belonging to
  clients within the PM's authorised scope are shown — no stores from other tenants are ever
  returned.
- **Given** the filter bar, **when** a PM selects a client, state, or store type filter (or any
  combination), **then** the list updates to show only matching stores without a full page
  reload (Livewire reactive filtering).
- **Given** the list, **when** more than a configurable page size (default 25) of results exist,
  **then** pagination controls are shown and each page loads correctly; the current filter
  state persists across page turns.
- **Given** no stores match the active filters, **when** the query returns empty, **then** a
  designed empty state is displayed with a plain-language message and a prompt to clear filters
  or create a store — not a blank table.
- **Given** the list is loading (initial render or filter change), **when** there is any
  perceptible delay, **then** a loading indicator is shown so the interface never appears frozen.
- **Given** an inactive store, **when** the list is viewed under default settings, **then**
  inactive stores are hidden; a "Show inactive" toggle reveals them.
- **Given** each store row, **when** viewed, **then** it shows at minimum: StoreName, StoreCode,
  StoreType, State, and active status — enough to identify a store without opening it.
- **Given** a PM not authorised for a client, **when** they attempt to filter by that client or
  view its stores via direct URL, **then** they are denied (403).

### Engineering Bar checklist

- **Secure:** all queries pass through the `client_id` global scope (US-00.4 trait) — no raw
  `where client_id = $request->client_id` from user input; filter values (client, state, type)
  are validated against their enum/allowed set before use in queries (no unsanitised input reaches
  SQL); PM policy checked for the list action; pagination does not bypass scope.
- **Clean:** Livewire component keeps filter state as typed properties, not a raw query-string
  bag; reuse State and StoreType enums from US-03.1; no N+1 queries — eager load the client
  relation; DB index on `client_id`, `state`, `store_type` covers filter queries (confirmed
  against §14.4 indexing requirements).
- **UX:** filter controls are clearly labelled with their purpose; active filters are visually
  indicated (e.g. pill/badge count) so Yeis knows the list is filtered; empty + loading + error
  states all designed; store name is a link to the store dashboard (US-03.3); store list is
  readable on 768px+ desktop per §14.2.
- **No guessing:** confirm the index columns exist on `stores` (from US-03.1 migration) before
  writing filter queries; verify Livewire pagination works with the scoping global scope before
  declaring done.

### Definition of Done

Livewire component renders store list scoped to PM; filters by client/state/type without reload;
pagination with filter persistence; empty + loading + error states present; inactive toggle;
cross-tenant scope verified by test; no N+1 queries (checked with query log or Telescope in dev);
Pint + Larastan clean; one happy-path integration test.

---

## US-03.3 — Store dashboard: store metadata and asset inventory table

**As** Yeis (PM)
**I want** a store-level dashboard showing store metadata and a filterable asset inventory for
that store
**So that** I have a single-screen view of everything deployed at a location (SRA §8).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-03.1, EPIC-04 (asset model must exist
for inventory data) · **Status:** ⏸ Blocked (by EPIC-04)

### Acceptance criteria

- **Given** a PM navigating to a store dashboard, **when** the page loads, **then** store
  metadata is shown at a glance: StoreName, StoreCode, StoreType, full address (AddressLine1,
  Suburb, State, Postcode), StoreTimezone (displayed as the human-readable name), store manager
  contact fields (name, phone, email), and IsActive status.
- **Given** the asset inventory section, **when** assets exist for this store, **then** a table
  shows all assets registered at this store; each row shows at minimum: AssetCode, AssetType,
  AssetName, Manufacturer, Model, and AssetStatus.
- **Given** the asset table filter controls, **when** a PM filters by AssetType or AssetStatus,
  **then** the table updates reactively (Livewire) to show only matching assets.
- **Given** no assets registered for the store, **when** the inventory section renders, **then**
  a designed empty state appears with a link to add the first asset (EPIC-04 route) — not a
  blank table or missing section.
- **Given** an asset row, **when** clicked, **then** it navigates to the asset detail page
  (EPIC-04) — the inventory is read-only aggregation here; no editing of assets from this page.
- **Given** a PM not authorised for this store's client, **when** they request the dashboard
  URL directly, **then** the policy returns 403.
- **Given** the display, **when** the store dashboard loads, **then** the asset count by
  type and the asset count by status are summarised (e.g. as a small stat row or badge strip)
  above the detailed table, giving an at-a-glance health overview.

### Engineering Bar checklist

- **Secure:** StorePolicy `view` action authorises by role **and** `client_id` before any data
  is returned; asset query is scoped to `store_id` **and** `client_id` — no orphaned cross-client
  assets can appear even if a `store_id` is guessed; no asset detail is editable from this page
  (read-only aggregation enforced in the controller — no mutation routes exposed here).
- **Clean:** store dashboard is a dedicated Livewire component (or Blade view with a Livewire
  sub-component for the filterable table) — not bloated into the store CRUD controller from
  US-03.1; asset inventory loaded with a single scoped query + eager-loaded relations (no N+1);
  asset type and status filter reuse the enums defined in EPIC-04; stat summary derived from the
  same query set (not separate queries).
- **UX:** dashboard has clear visual hierarchy — store identity at the top, summary stats, then
  the detailed inventory table; section headers make the page scannable without reading every
  row; filter controls for the asset table are inline above it (not in a modal); the table
  scrolls inside its container on narrow desktop viewports — the page body never scrolls
  horizontally; empty state for the asset section is friendly and action-oriented; loading state
  while assets fetch.
- **No guessing:** read the EPIC-04 Asset model/migration to confirm column names (`asset_code`,
  `asset_type`, `asset_status`, etc.) and the `store_id` FK before writing the inventory query;
  use tinker to verify the scope chain before coding the dashboard component.

### Definition of Done

Store metadata displayed correctly from §3.2 fields; asset inventory table renders with
type/status filters; summary stat row shows counts; empty state present; read-only (no mutation
routes); StorePolicy `view` tested for cross-tenant denial; no N+1 verified; Pint + Larastan
clean; one happy-path integration test.

---

## US-03.4 — Store dashboard: open faults, last service date per asset, and SLA compliance

**As** Yeis (PM)
**I want** the store dashboard to surface open faults (Faulty/Offline assets), the last service
visit date per asset, and the SLA compliance status of any open jobs at that store
**So that** I can see at a glance what is broken, how long since each asset was last touched, and
whether any jobs are at risk of breaching SLA — all from one screen (SRA §8).

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-03.3, EPIC-04 (AssetStatus enum),
EPIC-06 (asset status lifecycle + service history), EPIC-08 (service jobs), EPIC-12 (SLA
profiles and breach computation) · **Status:** ⏸ Blocked (by EPIC-06, EPIC-08, EPIC-12)

### Acceptance criteria

- **Given** a store with assets in Faulty or Offline status, **when** the dashboard loads,
  **then** an "Open Faults" section lists each affected asset with: AssetCode, AssetName,
  AssetStatus (Faulty or Offline), and location notes — clearly distinguished from healthy
  assets.
- **Given** an asset with no open faults, **when** viewed in the open faults section, **then**
  it does not appear; if no faults exist at all, a designed empty state reads "No open faults"
  with a positive visual treatment (not a blank section or a missing heading).
- **Given** the asset inventory table (from US-03.3), **when** service history records exist for
  an asset (EPIC-06), **then** a "Last Service" column shows the date of the most recent
  validated service job for that asset; if no service history exists, the cell displays "Never
  serviced" rather than a null or a dash.
- **Given** open service jobs at the store (EPIC-08), **when** SLA profiles are configured for
  the store's client (EPIC-12), **then** each open job in the dashboard's job section displays
  its SLA compliance status: on track, approaching breach (within the configured warning
  threshold), or breached — using colour and a plain-language label, not just a colour alone.
- **Given** no open service jobs, **when** the jobs/SLA section renders, **then** a designed
  empty state is shown rather than a missing section header.
- **Given** SLA data is not yet configured for a client, **when** the dashboard renders,
  **then** the SLA column/section shows a plain-language "No SLA configured" indicator rather
  than erroring or hiding the column silently.
- **Given** a PM not authorised for this store's client, **when** they request the dashboard,
  **then** the policy returns 403 — consistent with US-03.3 (the same store policy covers the
  full dashboard).

### Engineering Bar checklist

- **Secure:** all fault, service history, and SLA data queries are scoped to `store_id` **and**
  `client_id` — the same StorePolicy `view` gate from US-03.3 covers this; service history is
  read-only aggregation (no mutation here); SLA breach status is computed server-side from
  authoritative job and SLA data — never from a client-supplied value; "last service date" query
  sources from the append-only service history table (EPIC-06) — which is immutable at
  application level per US-00.5.
- **Clean:** open faults, last-service, and SLA sections are loaded as read-only data projections
  from their respective source tables — no duplicated status logic (reuse EPIC-04 and EPIC-12
  status enums); use dedicated scoped queries for each section, each eager-loading only the
  columns needed (no `SELECT *` on joined service history rows); if EPIC-06/08/12 are not yet
  present, return `null` gracefully from the data-gathering layer and render the empty state —
  no conditional feature flags scattered across the view.
- **UX:** open faults section uses a visually distinct treatment (e.g. a warning colour strip or
  icon) so it is immediately scannable even on a busy dashboard; SLA status uses both colour and
  a text label for accessibility (colour alone fails WCAG 2.1 AA for colour-blind users); "Never
  serviced" is plain language, not a null/dash that requires interpretation; each section has its
  own clear heading so a PM can jump to faults or SLA without reading the whole page; all data
  presented in AEST/local store time where dates are shown, not raw UTC.
- **No guessing:** read EPIC-06's service history model/migration to confirm the column name for
  the service date and the FK to assets before writing the "last service" query; read EPIC-12's
  SLA model to confirm how breach state is stored or computed (stored flag vs. computed from
  timestamps) before building the SLA section; read EPIC-08's job model to confirm `job_status`
  column name and the set of "open" statuses before filtering; use tinker to verify all joins
  before coding the component.

### Definition of Done

Open faults section lists Faulty/Offline assets with empty state; last-service column populated
from EPIC-06 service history with "Never serviced" fallback; SLA compliance status shown per open
job with on-track/approaching/breached states and a "No SLA configured" fallback; all three
sections render gracefully when their upstream data is absent; StorePolicy cross-tenant deny
covered by US-03.3 test (no duplicate needed); Pint + Larastan clean; one happy-path integration
test covering the populated dashboard; one test verifying the "no faults / no jobs / no SLA"
empty-state rendering.

---

## US-03.5 — Store country/region support beyond Australia (NZ)

**As** Yeis (PM)
**I want** to record a store's real country, choose from that country's own list of
states/regions, and set an accurate IANA timezone for it
**So that** stores physically located outside Australia — starting with the 4 Auckland, NZ
boutiques already in the platform (Sylvia Park, Commercial Bay, Newmarket, Queen St) — are
recorded with their true location instead of a placeholder Australian state, and every downstream
calculation (SLA clock, reports, dashboards) uses the correct jurisdiction.

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-03.1 · **Impacts:** EPIC-12 (US-12.2
business-hours clock — this story makes the interface country-aware and crash-safe for NZ, but
the actual NZ public-holiday calendar is a separate follow-up story tracked against EPIC-12) ·
**Status:** 📋 Ready

### Background

On 2026-07-08 a one-off vendor CSV import (`ImportVendorStorePlayers`, SRA §16 Q3) created 4 real
stores for Dior and Sephora that are actually in Auckland, NZ, but the system had no non-AU state,
so they were saved with `state = NSW`, `country = "Australia"`, and
`store_timezone = "Australia/Sydney"`, flagged only in a free-text note. This is not just a
data-quality gap: `SlaClockService` passes `Store::$state` straight into
`BusinessHoursCalculator` / `PublicHolidayProvider`, so any fault job raised against these 4
stores today silently computes its SLA breach clock against the **Sydney public-holiday
calendar**, not Auckland's.

### Acceptance criteria

- **Given** the store create/edit form, **when** a PM opens it, **then** `Country` is a visible,
  user-editable field (not a hardcoded hidden input) offering at minimum Australia and New
  Zealand, defaulting to Australia for new stores.
- **Given** a PM selects a `Country`, **when** the form re-renders (client-side, no round-trip),
  **then** the `State`/`Region` dropdown repopulates with that country's own list — the 8 existing
  AU states/territories for Australia, or New Zealand's regions for New Zealand — and any prior
  selection that no longer applies is cleared.
- **Given** a PM selects `Country = New Zealand`, **when** the `StoreTimezone` dropdown renders,
  **then** it offers NZ IANA zones (at minimum `Pacific/Auckland`) instead of the Australian zone
  list.
- **Given** a store's `Country` and `State`/`Region`, **when** submitted, **then** server-side
  validation rejects any state/region value that does not belong to the selected country's own
  list — a PM cannot submit `Country = New Zealand` with `State = NSW`, or vice versa, even by
  tampering with the request.
- **Given** the store list and the Asset Register / Open Faults report filters, **when** a PM
  filters by state/region, **then** New Zealand stores and regions are selectable and returned
  correctly, clearly distinguished from Australian ones (e.g. a `Country` filter alongside the
  existing State filter, or a country-qualified label).
- **Given** a store whose `Country` is not Australia, **when** its SLA clock computes business
  hours (EPIC-12), **then** the system does not crash and does not silently apply the wrong
  country's holiday calendar — it either applies a documented placeholder rule (weekends-only, no
  public holidays) or clearly surfaces "SLA holiday calendar not yet available for this country"
  until the dedicated NZ holiday-provider story ships; the fallback is logged, not swallowed.
- **Given** the 4 existing Auckland stores imported with the AU placeholder, **when** this story
  ships, **then** a one-off backfill command (following the `ImportVendorStorePlayers` idempotent
  pattern) corrects their `country`, `state`/region (Auckland), and `store_timezone`
  (`Pacific/Auckland`), and removes the "state stored as an Australian placeholder only" note.
- **Given** an existing store whose `country` is already `Australia` (the overwhelming majority),
  **when** this story ships, **then** its behaviour is completely unchanged — no re-validation
  failure, no forced re-save, no visual change to its form.

### Engineering Bar checklist

- **Secure:** state/region validation stays server-side and authoritative regardless of what the
  client-side Alpine cascade shows — a tampered request submitting a mismatched country/state pair
  is rejected by the Form Request, not just hidden in the UI; `$fillable` unchanged (no new
  mass-assignment surface); the backfill command runs once, is idempotent, and only touches the
  known affected store codes/notes pattern — no blanket `UPDATE`.
- **Clean:** introduce one `Country` enum reused everywhere `'Australia'` is currently hardcoded
  (`StoreController`, `StoreFactory`, both Form Requests, both Blade views); keep the existing
  `AustralianState` enum as-is and add a parallel region enum for New Zealand rather than merging
  incompatible value sets into one enum; `state` remains a plain string column — no schema
  migration needed since `country` already exists (`alter_stores_table_sprint2`); reuse the
  existing Store CRUD/Form Request/policy from US-03.1, extend rather than duplicate.
- **UX:** country → state/region → timezone is an obviously-cascading control (Alpine.js, no
  round-trip, consistent with the ADR-001 mobile-first pattern already used elsewhere) so a
  non-technical PM understands why the second dropdown changed; inline `@error` messaging for a
  mismatched country/state pair names both fields, not a generic error; Australian English
  throughout except NZ proper nouns.
- **No guessing:** confirm with the user which New Zealand regions to hard-code (all 16 official
  regions, or just the ones seen in real data today, i.e. Auckland) before writing the enum; read
  `BusinessHoursCalculator` / `PublicHolidayProvider` / `SlaClockService` fully before touching
  the interface, since it sits on a live SLA path, not just the store form; verify the backfill
  target rows directly in production data (e.g. `Store::where('notes', 'like',
  '%Australian placeholder only%')`) before writing the command — do not assume the 4 store codes
  from the import command's source constant still match current data.

### Definition of Done

`Country` enum introduced and used everywhere the literal `'Australia'` was hardcoded; store
create/edit forms expose an editable, cascading Country → State/Region → Timezone flow with no
server round-trip; Form Request validates state/region against the selected country's own enum;
store list and report filters support New Zealand stores; SLA business-hours computation degrades
safely (no crash, no silent wrong-country calendar) for non-AU stores pending the EPIC-12 NZ
holiday-provider follow-up; one-off idempotent backfill command corrects the 4 known Auckland
stores' `country` / `state` / `store_timezone` and clears their placeholder note; existing
Australian stores unaffected (regression-tested); Pint + Larastan clean; happy-path integration
test for creating/editing a New Zealand store; regression test confirming an Australian store's
flow is unchanged; test confirming SLA computation does not throw for a New Zealand store.

---

## US-03.6 — Store document library (floor plans, invoices, misc files)

**As** Yeis (PM)
**I want** to upload and later retrieve arbitrary reference documents against a store — elevation
plans, invoices, and other store-specific paperwork
**So that** this information doesn't live only in email/local folders and can be pulled up again
from the platform when needed (raised 2026-07-20 during Asset Registry work; no such capability
exists today — confirmed no `store_attachments`-style table, no MediaLibrary dependency).

**Estimate:** 5 · **Priority:** P1 (backlog — next priority in this epic after US-04.8's
Infrastructure/router fields) · **Depends on:** US-03.1 · **Status:** 📋 Ready

> **Reuse, don't reinvent:** `job_attachments` (EPIC-08) already solves this exact shape —
> `id`, owning FK, `original_filename`, `stored_path`, `mime_type`, `file_size`, timestamps —
> served through `Storage::disk('local')` behind an authenticated, scoped download route (see
> `AssetController::downloadHistoryPhoto` and the `jobs.attachments.download` route for the
> pattern to copy: never a public disk URL for anything that isn't already a signed/QR asset).

### Acceptance criteria
- **Given** a store detail/dashboard page, **when** a PM uploads a file, **then** it is validated
  against a MIME + extension allow-list and a size limit (§14.3; reuse the upload-size config
  already raised for technician job photos) before being written to a **private** disk — never
  the public disk — with the original filename and MIME type recorded.
- **Given** the upload, **when** it succeeds, **then** the PM optionally tags it with a document
  type (e.g. Floor Plan, Invoice, Other) and an optional note, to make later retrieval faster.
- **Given** a store's document list, **when** a PM opens the store, **then** all its documents are
  listed (filename, type, size, uploaded-by, date), scoped to that store only.
- **Given** a document row, **when** a PM downloads it, **then** the file is streamed through an
  authenticated, `client_id`-scoped route — the same guard as `StorePolicy::view` — never a raw
  public URL; a PM outside the store's client scope gets a 403, not the file.
- **Given** a document, **when** a PM deletes it, **then** it is removed from storage and the DB
  row deleted (hard delete is acceptable here — these are reference files, not an audit-relevant
  history record) and the deletion is audited.
- **Given** no documents exist for a store, **when** the section renders, **then** a designed
  empty state appears with an upload prompt — not a blank section.

### Engineering Bar checklist
- **Secure:** MIME + extension allow-list and size cap enforced server-side before write (never
  trust the client-declared MIME type); private disk only; download route authorises via
  `StorePolicy` (role + `client_id`), matching the existing `jobs.attachments.download` /
  `assets.service-history.photo` pattern rather than inventing a new authorisation shape;
  `$fillable` explicit; FK `store_id` NOT NULL, cascade on store delete.
- **Clean:** copy the `job_attachments` table/model shape (EPIC-08) — do not introduce Spatie
  MediaLibrary (not currently a dependency) or a JSON column; one `StoreAttachment` model, one
  migration, reuse the `Storage`-disk convention already used by `QrCodeService` and
  `ServiceJobController`'s attachment handlers.
- **UX:** upload control with progress feedback (non-blocking, §14.1); document type is a simple
  optional tag, not a mandatory taxonomy; inline `@error` on upload failure in plain language;
  designed empty/loading/error states; en-AU; ≥44px targets.
- **No guessing:** read `job_attachments`' migration and `JobAttachment`/`ServiceJobController`
  attachment methods in full before writing the store equivalent — reuse the exact validation
  rules and disk configuration rather than re-deriving them; confirm with Yeis whether "document
  type" needs to be a fixed enum or free-text tag before locking the column.

### Definition of Done
`store_attachments` table + `StoreAttachment` model following the `job_attachments` shape; upload
validated (MIME/extension/size) to a private disk; scoped list + authenticated scoped download on
the store page; delete removes file + row and is audited; empty state designed; Pint + Larastan
clean; one happy-path integration test; cross-tenant download-deny test.
