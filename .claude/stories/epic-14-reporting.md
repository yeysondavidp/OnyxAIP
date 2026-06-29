# EPIC-14 — Reporting

> Turns the platform's data into the client-facing and operational exports Yeis builds by hand
> today (SRA §13.1, §13.2). Every report is **scoped by `client_id`** so no export can leak across
> tenants — and so a future Rosie (v2) read-only client view is config, not rework.

Related: US-00.4 (scoping), US-00.5 (audit), US-01.3 (policies + scope), ADR-002 (queue/efficiency).
Depends on data from EPIC-04/05/06/07/08/11/12. Sprint: 10.

---

## US-14.1 — Asset Register report (CSV + PDF) + Asset Status Summary (CSV)

**As** Yeis (PM)
**I want** to export an asset register (CSV and PDF) filtered by client, state, store and type,
plus a per-client/state asset status summary (CSV)
**So that** I can hand a client (e.g. Pandora) a complete, accurate inventory of their deployed
assets and a roll-up of how many are Active/Faulty/Offline.

**Estimate:** 8 · **Priority:** P1 · **Depends on:** US-00.4, US-01.3, EPIC-04, EPIC-06 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the report params (client required; state, store, type optional), **when** Yeis runs
  the Asset Register report, **then** the export lists every matching asset with AssetCode,
  AssetType, AssetName, Manufacturer, Model, SerialNumber, store, state, AssetStatus and key dates
  (SRA §4.2) — in **both CSV and PDF** (§13.1).
- **Given** the same params, **when** the Asset Status Summary is run, **then** a **CSV** returns
  asset counts grouped by type and status for the selected client/state (§13.1).
- **Given** any report run, **when** rows are gathered, **then** **only rows for the actor's
  permitted `client_id`** appear — no cross-tenant rows in any export, proven by a failing-without-
  scope test (Engineering Bar #1; US-00.4).
- **Given** a client with up to 1,000 assets (§14.1), **when** the export is generated, **then** it
  completes efficiently via **chunked** reads (and is **queued** for large datasets with a download
  made available when ready), without exhausting memory.
- **Given** no assets match the filters, **when** the report runs, **then** a **designed empty
  state** explains it (not a blank file or error).
- **Given** the PDF, **when** opened, **then** it carries the client name, filter summary and
  generated-at timestamp in the store/job-appropriate display timezone (UTC stored, §2.3).

### Engineering Bar checklist
- **Secure:** report query runs through the `client_id` global scope **and** an authorising policy
  (US-01.3) — defence in depth, never trust a `client_id` from request input; the export of client
  data is **audit-logged** (US-00.5, §14.5). Generated files served only via signed URL (US-01.5).
- **Clean:** one reusable report/export service + a shared CSV and PDF writer reused by every
  EPIC-14 story; chunked queries, no N+1 (eager-load store/type relations).
- **UX:** clear report parameter form with obvious primary action; designed loading/empty/error
  states; Australian English; column headers human-readable.
- **No guessing:** verify Asset / Store / type-specific column + relation names with tinker before
  querying; confirm the status enum values against EPIC-06.

### Definition of Done
Asset Register (CSV+PDF) and Asset Status Summary (CSV) run filtered by client/state/store/type;
scope-leak test passes; chunked/queued for large sets; empty/loading/error states designed; export
audit-logged; files signed-URL only; Pint + Larastan + Pest green.

---

## US-14.2 — Service history reports: per asset (PDF) + per store (PDF + CSV)

**As** Yeis (PM)
**I want** to export the service history of a single asset (PDF) and of a whole store (PDF + CSV)
**So that** I can give a client a documented maintenance record per asset and per location, built
from the append-only service log rather than assembled by hand.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-14.1, EPIC-11 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** an asset, **when** Yeis runs Service History per Asset, **then** a **PDF** lists each
  service record chronologically with ServiceDate, JobType, technician(s), StatusBefore/After and
  per-asset technician notes (SRA §7, §13.1).
- **Given** a store, **when** Yeis runs Service History per Store, **then** **PDF and CSV** export
  every service record across that store's assets, grouped/sortable by asset and date (§13.1).
- **Given** any history export, **when** rows are gathered, **then** **only the actor's permitted
  `client_id`** is included — the asset's/store's client is authorised first (no cross-tenant rows).
- **Given** the append-only service log (US-00.5/EPIC-11), **when** read for the report, **then**
  records are **read-only** — the report never mutates history.
- **Given** a store with many service records, **when** the per-store report runs, **then** it uses
  **chunked** reads and is **queued** for large datasets.
- **Given** an asset or store with no service history yet, **when** the report runs, **then** a
  designed empty state explains it.

### Engineering Bar checklist
- **Secure:** authorise the asset/store against role **and** `client_id` (US-01.3) before reading;
  scope enforced in the query too (US-00.4); photo/PDF links are **signed URLs only**; the export
  is **audit-logged** (§14.5). Before/after photo URLs in the PDF must not bypass signed access.
- **Clean:** reuse the shared CSV/PDF writers from US-14.1; one service-history query path reused by
  both the asset and store variants; eager-load technicians + asset to avoid N+1.
- **UX:** clear params (asset picker / store picker); designed loading/empty/error; Australian
  English; readable chronological layout suitable for a client pack.
- **No guessing:** verify the service-history table columns/relations (EPIC-11) and the asset↔store
  relation with tinker before writing the query.

### Definition of Done
Per-asset PDF and per-store PDF+CSV generate from EPIC-11 data; scope-leak test passes; history is
read-only; chunked/queued for large stores; empty/loading/error states designed; export
audit-logged; signed-URL photo access verified; Pint + Larastan + Pest green.

---

## US-14.3 — Open Faults report (CSV) + SLA Compliance report (CSV)

**As** Yeis (PM)
**I want** to export current open faults (CSV) and SLA compliance for a client over a date range
(CSV)
**So that** I can see every unresolved fault across a client's estate and prove to the client how
service performed against their SLA.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-14.1, EPIC-06, EPIC-12 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** client (and optional state) params, **when** Yeis runs Open Faults, **then** a **CSV**
  lists every asset currently Faulty/Offline with store, state, AssetCode, status, last service
  date and any open job reference (SRA §13.1, §8).
- **Given** client **and a date range**, **when** Yeis runs SLA Compliance, **then** a **CSV**
  reports each in-scope service job's SLA outcome — acknowledgement/response/resolution against the
  client's SLA profile and whether it **breached** (§10, §13.1).
- **Given** business-hours SLA logic, **when** compliance is computed, **then** it reflects the
  EPIC-12 SLA clock (weekends + state public holidays excluded) — the report **reads** the computed
  result, it does not re-implement the clock.
- **Given** either report, **when** rows are gathered, **then** **only the actor's permitted
  `client_id`** is included (no cross-tenant rows).
- **Given** an invalid date range (end before start, or missing), **when** SLA Compliance is run,
  **then** inline `@error` validation blocks it with a plain-language message.
- **Given** no open faults / no jobs in range, **when** the report runs, **then** a designed empty
  state explains it.

### Engineering Bar checklist
- **Secure:** authorise by role + `client_id` and enforce scope in-query (US-01.3, US-00.4); never
  trust `client_id`/date params beyond validation; export **audit-logged** (§14.5); served via
  signed URL.
- **Clean:** reuse the shared CSV writer; **consume** the EPIC-12 SLA computation rather than
  duplicating breach/business-hours logic (DRY); chunked reads.
- **UX:** clear client + date-range params with validation; designed loading/empty/error;
  Australian English; breach rows clearly distinguishable in the export.
- **No guessing:** verify the SLA-profile + job/breach columns and the public-holiday source
  (EPIC-12) with tinker before querying; confirm the Faulty/Offline enum values (EPIC-06).

### Definition of Done
Open Faults CSV and SLA Compliance CSV (client + date range) generate from EPIC-06/12 data; SLA
logic reused not reimplemented; date-range validated; scope-leak test passes; chunked; empty/
loading/error states designed; export audit-logged; signed-URL only; Pint + Larastan + Pest green.

---

## US-14.4 — Technician Hours (CSV) + Warranty Expiry Forecast (CSV)

**As** Yeis (PM)
**I want** to export technician worked hours per technician over a date range (CSV) and a warranty
expiry forecast per client over a date range (CSV)
**So that** I can review field effort (Michael's / Sneider's recorded hours) and plan ahead for
assets whose warranty is about to lapse.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-14.1, EPIC-04, EPIC-11 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a technician (or all) and a **date range**, **when** Yeis runs Technician Hours,
  **then** a **CSV** lists worked hours derived from each job's start/end timestamps per technician
  (SRA §13.1, §5.4) — totalled per technician.
- **Given** client and a **date range**, **when** Yeis runs Warranty Expiry Forecast, **then** a
  **CSV** lists assets whose WarrantyExpiry falls in the range, with store, AssetCode, model,
  serial and days-until-expiry (SRA §4.2, §13.1).
- **Given** durations from timestamps, **when** hours are computed, **then** they use the
  **UTC-stored** start/end times (§2.3) so totals are correct regardless of store timezone.
- **Given** the Warranty Forecast, **when** rows are gathered, **then** **only the actor's permitted
  `client_id`** appears (no cross-tenant rows). Technician Hours is scoped to ONYX's jobs only.
- **Given** an invalid/missing date range, **when** either report runs, **then** inline `@error`
  validation blocks it with a plain-language message.
- **Given** no matching hours / no expiring assets, **when** the report runs, **then** a designed
  empty state explains it.

### Engineering Bar checklist
- **Secure:** authorise by role + `client_id` (Warranty Forecast) and scope queries (US-01.3,
  US-00.4); technician hours expose only ONYX's own data; GPS/timestamp access restricted (§14.3);
  export **audit-logged** (§14.5); signed-URL only.
- **Clean:** reuse the shared CSV writer; one duration helper reused (not recomputed inline per
  row); chunked reads; eager-load technician/asset relations.
- **UX:** clear technician/client + date-range params with validation; designed loading/empty/error;
  Australian English; per-technician subtotals legible.
- **No guessing:** verify the job start/end timestamp columns + technician pivot (EPIC-11/§5.4) and
  the WarrantyExpiry column (EPIC-04) with tinker before querying.

### Definition of Done
Technician Hours CSV (per technician + date range) and Warranty Expiry Forecast CSV (client + date
range) generate from EPIC-04/11 data; durations use UTC timestamps; date-range validated; scope-leak
test passes; chunked; empty/loading/error states designed; export audit-logged; signed-URL only;
Pint + Larastan + Pest green.

---

## US-14.5 — Display Group topology report per store (for client reporting packs)

**As** Yeis (PM)
**I want** a per-store Display Group topology report (player → screen(s) with models, serials,
component status and last service date)
**So that** I can include a clear player-to-screen map of each store in a client's reporting pack
(SRA §13.2).

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-14.1, EPIC-05, EPIC-11 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a store, **when** Yeis runs the Display Group report, **then** it renders each Display
  Group at that store as **player (model + serial) → screen(s) (model + serial)** with each
  component's current status and last service date (SRA §13.2, §4.4).
- **Given** a store with multiple Display Groups, **when** the report runs, **then** every group is
  shown distinctly, plus any assets not in a group are noted (so the pack is complete).
- **Given** the report, **when** generated, **then** **only the store's authorised `client_id`** is
  read (no cross-tenant rows) — the store's client is authorised first.
- **Given** the format, **when** produced, **then** it is suitable for a **client reporting pack**
  (PDF), carrying client/store name, generated-at timestamp and ONYX branding placeholder.
- **Given** a store with no Display Groups configured, **when** the report runs, **then** a designed
  empty state explains it.
- **Given** last-service-date per component, **when** shown, **then** it reads from the append-only
  service history (EPIC-11) — read-only, never mutated by the report.

### Engineering Bar checklist
- **Secure:** authorise the store against role + `client_id` (US-01.3) and scope the topology query
  (US-00.4); export **audit-logged** (§14.5); served via signed URL only.
- **Clean:** reuse the shared PDF writer from US-14.1; one query that eager-loads player + screens +
  last-service to avoid N+1 across groups; no duplication of topology logic that EPIC-05 owns.
- **UX:** clear store picker; designed loading/empty/error; Australian English; a legible
  player→screen layout a client can read at a glance.
- **No guessing:** verify the DisplayGroup, PlayerAssetID and ScreenAssetIDs relations (EPIC-05) and
  the last-service lookup (EPIC-11) with tinker before building the report.

### Definition of Done
Per-store Display Group topology PDF renders player→screen(s) with model/serial/status/last-service;
all groups + ungrouped assets covered; scope-leak test passes; service history read-only;
empty/loading/error states designed; export audit-logged; signed-URL only; Pint + Larastan + Pest
green.
