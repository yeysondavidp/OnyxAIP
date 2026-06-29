# EPIC-04 — Asset Registry

> The central register of every deployed asset, client- and store-scoped, with type-specific
> detail per hardware category (SRA §4). This is the "single source of truth" Yeis depends on;
> the asset **detail page becomes the hub** later linked from QR lookup (EPIC-07) and service
> history (EPIC-06). The base + type-extension data design is **locked in US-04.1** and every
> later story in this epic builds on it.

Related: ADR-001 (PM Livewire CRUD), US-00.4 (`client_id` scoping), US-00.5 (audit), US-01.3
(policies). Depends on EPIC-03 (Stores). Sprint: 3.

---

## US-04.1 — CRUD asset base model

**As** Yeis (PM)
**I want** to create, view, edit and decommission assets with all shared base fields, scoped to a
client and store
**So that** ONYX has one trustworthy register of every deployed asset (SRA §4.2).

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-00.4, US-00.5, US-01.3, EPIC-03 · **Status:** 📋 Ready

> **Design decision to lock here:** type-specific fields (US-04.2–04.5) are modelled as a
> **shared `assets` base table + one related detail table per asset type** (related-detail
> tables keyed by `asset_id`, selected via the base `asset_type` enum) — chosen over a single
> wide table (sparse nullable columns) or pure STI. This keeps base queries lean, lets each type
> own its constraints at the DB, and is the pattern every later story in this epic must follow.

### Acceptance criteria
- **Given** the asset form, **when** a PM creates an asset, **then** all §4.2 base fields are
  captured: `asset_code`, `asset_type`, `client_id`, `store_id`, `asset_name`, `manufacturer`,
  `model`, `serial_number` (nullable), `purchase_date`/`warranty_expiry`/`install_date`
  (nullable dates), `asset_status`, `location_notes` (nullable), `parent_asset_id` (nullable),
  `notes`.
- **Given** the DB, **when** the migration runs, **then** `asset_code` has a **unique index**,
  `asset_type` and `asset_status` are constrained enums, `client_id` + `store_id` are **NOT NULL
  FKs**, and `parent_asset_id` is a **nullable self-referencing FK** (with a sensible on-delete,
  e.g. `nullOnDelete`).
- **Given** the base+type-extension design above, **when** an asset of a given type is created,
  **then** its type detail row is created in the same transaction and the chosen approach is
  documented as the locked decision for the epic.
- **Given** a `store_id`, **when** an asset is saved, **then** the store must belong to the same
  `client_id` (cross-tenant store assignment rejected at Request **and** enforceable at DB via the
  scoped FK), never trusting `client_id`/`store_id` from request input.
- **Given** create or edit, **when** it succeeds, **then** an **audit entry** is written
  asynchronously (US-00.5) with actor, before/after values and target.
- **Given** the list/edit surfaces, **when** any action is attempted, **then** the asset policy
  authorises by **role + `client_id` scope** (US-01.3), and a `client_user` fixture is denied.
- **Given** an asset, **when** decommissioned, **then** it is soft-handled per the status
  lifecycle (status flips to `Decommissioned`, §4.5) rather than hard-deleted, preserving history.

### Engineering Bar checklist
- **Secure:** `$fillable` only (never `$guarded = []`); `client_id`/`store_id` set from authorised
  context; unique `asset_code` + NOT NULL FKs + enum columns at the DB so guarantees hold even if a
  policy were bypassed — the exact "guard in controller **and** DB" pattern. Self-FK on
  `parent_asset_id` prevents orphaned topology references.
- **Clean:** reuse the US-00.4 scoping trait + migration recipe and the US-01.3 policy pattern;
  base table + per-type detail tables, no wide sparse table, no per-type copy-paste.
- **UX:** PM desktop shell; clear hierarchy with one obvious primary action (Save); inline
  `@error` per field; plain non-blaming errors; designed loading/empty/error states; en-AU copy;
  ≥44px targets.
- **No guessing:** verify the `stores` table columns/relations and the status enum values with
  tinker/reading the migration before writing the asset migration and FKs.

### Definition of Done
Base CRUD works scoped by client/store; migration has unique `asset_code`, enum + NOT NULL FK +
nullable self-FK constraints; base+type-extension design documented and locked; audit on
create/edit; policy cross-tenant deny tested; happy-path integration test; Pint + Larastan clean.

---

## US-04.2 — Type-specific fields: Digital Screen

**As** Yeis (PM)
**I want** to capture screen-specific detail when an asset is a Digital Screen
**So that** the register holds the spec that matters for displays (SRA §4.3 Digital Screen).

**Estimate:** 3 · **Priority:** P0 · **Depends on:** US-04.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** an asset of type Digital Screen, **when** created/edited, **then** the form captures
  `screen_size_inches` (decimal), `resolution_width`/`resolution_height` (integer),
  `orientation` (enum: Landscape, Portrait), `mount_type` (string, e.g. Floor Totem, Wall Mount,
  Window Flush) and `totem_supplied_by` (enum: Client, ONYX).
- **Given** the DB, **when** the migration runs, **then** screen detail lives in its own
  per-type table keyed by a **NOT NULL FK `asset_id`** (cascade on delete of the parent asset),
  with `orientation` and `totem_supplied_by` as constrained enums.
- **Given** a non-screen asset type, **when** saved, **then** no screen detail row is created
  (fields shown only for the matching type).
- **Given** create/edit of screen detail, **when** it succeeds, **then** the change is audited as
  part of the asset's audit trail (US-00.5).

### Engineering Bar checklist
- **Secure:** `$fillable` only; enum columns + NOT NULL FK `asset_id` at the DB; detail authorised
  through the parent asset's policy (role + `client_id`), never independently reachable cross-tenant.
- **Clean:** follows the locked base+detail pattern from US-04.1; one detail model/migration, no
  duplication of base fields.
- **UX:** type-specific fields revealed inline when type = Digital Screen; inline `@error`; clear
  numeric inputs (size/resolution) with plain validation; en-AU; ≥44px targets.
- **No guessing:** confirm decimal/precision and enum values against §4.3 before the migration.

### Definition of Done
Screen detail saved/edited via the parent asset form; per-type table with FK + enum constraints;
audited; cross-tenant deny inherited from US-04.1 policy tested; Pint + Larastan clean.

---

## US-04.3 — Type-specific fields: Media Player

**As** Yeis (PM)
**I want** to capture player-specific detail when an asset is a Media Player
**So that** ONYX knows what is driving each store's screens (SRA §4.3 Media Player).

**Estimate:** 3 · **Priority:** P0 · **Depends on:** US-04.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** an asset of type Media Player, **when** created/edited, **then** the form captures
  `player_type` (enum: Standalone Hardware, SoC App), `cms_platform` (nullable string, e.g.
  Navori QL, Samsung MagicInfo, Beat CMS), `ip_address` (nullable), `mac_address` (nullable) and
  `firmware_version` (nullable).
- **Given** the DB, **when** the migration runs, **then** player detail lives in its own per-type
  table keyed by a **NOT NULL FK `asset_id`** (cascade on parent delete) with `player_type` as a
  constrained enum.
- **Given** `ConnectedScreenIDs` (§4.3), **when** modelling player → screen links, **then** this
  relationship is **deferred to / owned by the Display Group (EPIC-05)** and **not** duplicated as
  a free-form list here — note this overlap explicitly so EPIC-05 is the single source of
  player-to-screen topology.
- **Given** create/edit of player detail, **when** it succeeds, **then** it is audited as part of
  the asset's trail (US-00.5).

### Engineering Bar checklist
- **Secure:** `$fillable` only; NOT NULL FK `asset_id` + enum at DB; `ip_address`/`mac_address`
  validated to format; authorised via the parent asset policy (role + `client_id`).
- **Clean:** locked base+detail pattern; **do not** re-implement screen connectivity here — defer
  to EPIC-05 to avoid two sources of truth (YAGNI/DRY).
- **UX:** player fields revealed inline when type = Media Player; inline `@error`; plain
  validation messages for IP/MAC format; en-AU; ≥44px targets.
- **No guessing:** confirm with EPIC-05 owner that connected-screen topology belongs there before
  building any link UI; verify enum values against §4.3.

### Definition of Done
Player detail saved/edited via the parent asset form; per-type table with FK + enum; connected-
screen topology explicitly deferred to EPIC-05 and noted; audited; Pint + Larastan clean.

---

## US-04.4 — Type-specific fields: Lightbox

**As** Yeis (PM)
**I want** to capture lightbox-specific detail when an asset is a Lightbox
**So that** illuminated fixtures are tracked with their relevant attributes (SRA §4.3 Lightbox).

**Estimate:** 2 · **Priority:** P1 · **Depends on:** US-04.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** an asset of type Lightbox, **when** created/edited, **then** the form captures
  `lightbox_dimensions` (string, W x H x D in mm), `light_type` (enum: LED, Fluorescent, Other)
  and `content_change_frequency` (enum: Static, Weekly, Monthly, Campaign-based).
- **Given** the DB, **when** the migration runs, **then** lightbox detail lives in its own
  per-type table keyed by a **NOT NULL FK `asset_id`** (cascade on parent delete) with
  `light_type` and `content_change_frequency` as constrained enums.
- **Given** a non-lightbox type, **when** saved, **then** no lightbox detail row is created.
- **Given** create/edit, **when** it succeeds, **then** it is audited as part of the asset's
  trail (US-00.5).

### Engineering Bar checklist
- **Secure:** `$fillable` only; NOT NULL FK `asset_id` + enums at DB; authorised via the parent
  asset policy (role + `client_id`).
- **Clean:** locked base+detail pattern; one detail model/migration; no base-field duplication.
- **UX:** lightbox fields revealed inline when type = Lightbox; inline `@error`; plain dimension
  helper text; en-AU; ≥44px targets.
- **No guessing:** confirm enum value sets against §4.3 before the migration.

### Definition of Done
Lightbox detail saved/edited via the parent asset form; per-type table with FK + enum constraints;
audited; cross-tenant deny inherited from US-04.1 tested; Pint + Larastan clean.

---

## US-04.5 — Type-specific fields: Window Fixture & Infrastructure

**As** Yeis (PM)
**I want** to capture detail for Window Fixtures and Infrastructure assets
**So that** custom fixtures and cabling/ancillary hardware are registered too (SRA §4.3
Infrastructure; §4.1 Window Fixture).

**Estimate:** 3 · **Priority:** P1 · **Depends on:** US-04.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** an asset of type Infrastructure, **when** created/edited, **then** the form captures
  `cable_type` (nullable string), `length` (nullable decimal), and `connected_from_asset_id` /
  `connected_to_asset_id` (**nullable FKs** to other assets).
- **Given** the DB, **when** the migration runs, **then** Infrastructure detail lives in its own
  per-type table keyed by a **NOT NULL FK `asset_id`** (cascade on parent delete), and
  `connected_from_asset_id` / `connected_to_asset_id` are **nullable FKs** with a safe on-delete
  (e.g. `nullOnDelete`) so deleting a connected asset never orphans the link.
- **Given** an asset of type Window Fixture, **when** created/edited, **then** it is supported on
  the base model (custom-fabricated structure that may house a screen/lightbox); any minimal
  fixture-specific attributes follow the same per-type detail pattern, and the **custom-fabricated
  nature is captured** (no rigid spec fields are mandated by §4.3, so keep it lean — YAGNI).
- **Given** the connected-from/to FKs, **when** selected, **then** both referenced assets must be
  within the same `client_id` scope (validated at Request and constrained by scoped FKs).
- **Given** create/edit, **when** it succeeds, **then** it is audited as part of the asset's
  trail (US-00.5).

### Engineering Bar checklist
- **Secure:** `$fillable` only; NOT NULL FK `asset_id`; nullable connection FKs with safe
  on-delete to prevent orphans; cross-tenant connection rejected; authorised via the parent asset
  policy (role + `client_id`).
- **Clean:** locked base+detail pattern; don't invent speculative Window Fixture columns §4.3
  doesn't require (YAGNI); reuse the asset selector used elsewhere for the connection FKs.
- **UX:** Infrastructure/Window Fixture fields revealed inline by type; asset-picker for
  connections shows only same-client assets; inline `@error`; en-AU; ≥44px targets.
- **No guessing:** confirm whether Window Fixture needs any dedicated columns with the PM before
  adding them; verify the asset relation used by the connection FKs via tinker.

### Definition of Done
Infrastructure detail (cable type, length, connection FKs) and Window Fixture support saved/edited
via the parent asset form; per-type table with FK + nullable connection FKs + safe on-delete;
cross-tenant connection denied; audited; Pint + Larastan clean.

---

## US-04.6 — Asset list & detail with filter by type and status

**As** Yeis (PM)
**I want** a scoped, paginated, filterable list of assets and a rich detail page per asset
**So that** I can find any asset fast and the detail page can serve as the hub for QR and history
(SRA §4.6, §8).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-04.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the asset list, **when** a PM opens it, **then** it shows assets **scoped to their
  permitted `client_id`(s)** (US-00.4), paginated, with columns for code, name, type, store and
  status.
- **Given** the filters, **when** a PM filters by **asset type** and/or **status**, **then** the
  list updates to matching assets only, with the filter state reflected in the UI.
- **Given** no matching assets, **when** the list renders, **then** the shared **empty** state
  shows; while loading, the shared **loading** state shows; on failure, the shared **error** state
  shows (US-00.3).
- **Given** an asset row, **when** opened, **then** the **detail page** shows base fields plus the
  matching type-specific detail (US-04.2–04.5) and is structured to be the **hub** later linked
  from QR lookup (EPIC-07) and to display service history (EPIC-06).
- **Given** any list/detail access, **when** attempted, **then** the asset policy authorises by
  role + `client_id`, and a `client_user` fixture cannot see another tenant's assets.

### Engineering Bar checklist
- **Secure:** list/detail queries go through the scoping global scope + policy; no `client_id`
  accepted from query string to widen scope; filter inputs validated against the enums.
- **Clean:** reuse the US-00.3 empty/loading/error components and the PM shell; Livewire for the
  PM CRUD list per ADR-001; one detail view reused by the QR hub later (no duplicate page).
- **UX:** clear hierarchy, prominent filter controls, obvious primary action (New Asset); fast
  filtering for Yeis's dense-but-legible need; designed empty/loading/error; en-AU; ≥44px targets.
- **No guessing:** verify the type-detail relations load correctly (avoid N+1) with tinker before
  finalising the detail eager-loads.

### Definition of Done
Scoped paginated list with type+status filters; empty/loading/error states; detail page rendering
base + type-specific detail and structured as the future hub; cross-tenant deny tested; no N+1 on
detail; happy-path integration test; Pint + Larastan clean.

---

## US-04.7 — CSV bulk import with field-mapping UI

**As** Yeis (PM)
**I want** to bulk-import existing client assets from CSV with a field-mapping step
**So that** Pandora/Dior/Sephora's existing deployments can be loaded without hand-entering every
asset (SRA §16 Q3, §17).

**Estimate:** 8 · **Priority:** P2 · **Depends on:** US-04.1, US-04.2, US-04.3, US-04.4, US-04.5 · **Status:** 🧊 Deferred (post-v1)

> **Decision (ONYX, 2026-06-24, resolving SRA §16 Q3): v1 is manual entry only — no CSV import.**
> Existing client assets are entered by hand via US-04.1–04.5. This story is **out of v1 scope**
> and parked for a future version. The full ACs below are retained so it can be picked up later
> without re-analysis; do **not** build it in v1.

### Acceptance criteria
- **Given** a CSV upload, **when** a PM uploads a file, **then** the file is validated (MIME +
  extension allow-list, size limit, §14.3) and parsed for headers before any write.
- **Given** the parsed headers, **when** the mapping UI shows, **then** the PM maps each CSV
  column to an asset base/type field, with required base fields (code, type, client, store)
  flagged and the target `client_id` chosen from the PM's permitted clients (never trusted from
  the file).
- **Given** a confirmed mapping, **when** the import runs, **then** **every row is validated**
  (enum values, FK existence within the target `client_id` scope, unique `asset_code`) before
  insert, and the matching per-type detail row is created for each asset.
- **Given** valid rows, **when** written, **then** the import uses **bulk `DB::table()->insert()`
  in a transaction** on this hot path (Engineering Bar #2) — base and per-type detail inserted
  efficiently, not one Eloquent save per row.
- **Given** a partial failure, **when** some rows are invalid, **then** the import reports
  **per-row results** (which rows imported, which were skipped and why) in plain language, and
  invalid rows do **not** silently corrupt the register (failed rows rejected, valid rows
  committed per the agreed transaction boundary).
- **Given** the import completes, **when** assets are created, **then** the action is **audited**
  (US-00.5) — a summary audit entry plus per-asset creation captured.
- **Given** any import, **when** attempted, **then** it is authorised by role (PM only) +
  `client_id` scope; a technician/`client_user` cannot import, and no row can be written outside
  the actor's permitted client.

### Engineering Bar checklist
- **Secure:** PM-only + `client_id`-scoped; file MIME/extension/size validated before parse;
  target `client_id` from authorised context not the CSV; **every row validated**; unique
  `asset_code` enforced at DB so duplicates fail even under bulk insert; rate-limit the upload
  endpoint (§14.3).
- **Clean:** reuse the validation rules from US-04.1–04.5 (don't re-derive); bulk insert in a
  transaction on the hot path; one importer service, no copy-paste per type.
- **UX:** stepped flow (upload → map → preview/validate → confirm) with clear primary action each
  step; inline `@error`; plain non-blaming per-row failure report; designed loading/progress and
  empty/error states; en-AU; ≥44px targets.
- **No guessing:** **confirm the import-vs-manual decision with ONYX (§16 Q3) before building**;
  verify column/enum/FK names against the US-04.1 schema before writing the importer.

### Definition of Done
Scope confirmed with ONYX (unblocks the story); CSV validated and mapped via UI; every row
validated; bulk insert in a transaction; partial-failure per-row report; PM-only + client-scoped
authorisation tested; import audited; happy-path integration test; Pint + Larastan clean.
