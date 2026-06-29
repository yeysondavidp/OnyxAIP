# EPIC-05 — Display Group Topology

> Maps the player-to-screen wiring inside a store: one media player drives one or more screens,
> forming a Display Group. Critical invariants — a player belongs to exactly one group, a screen
> belongs to exactly one group — are enforced at the DB, not just in app code (Engineering Bar
> #1). The topology diagram on the store dashboard (§8) is the read-only surface that feeds
> reporting (US-14.5).

Related: US-00.4 (client_id scoping), US-00.5 (audit), US-01.3 (policies), EPIC-04 (assets).
Sprint: 4.

---

## US-05.1 — CRUD Display Group with exclusive player/screen membership

**As** Yeis (PM)
**I want** to create, view, edit and delete Display Groups for a store — each grouping exactly
one media player with one or more screens — where both the player and each screen can belong to
only one Display Group at a time
**So that** the platform has an accurate, conflict-free record of the player-to-screen wiring at
every store.

**Estimate:** 8 · **Priority:** P1 · **Depends on:** US-00.4, US-00.5, US-01.3, EPIC-04 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** Yeis is on the Display Groups page for a store, **when** the store has no groups,
  **then** a designed empty state is shown with a clear "Add Display Group" primary action.
- **Given** the create form, **when** Yeis opens it, **then** the player dropdown lists only
  assets of `AssetType = media_player` that belong to the same store and are not already assigned
  to any other Display Group; the screen multi-select lists only assets of `AssetType =
  digital_screen` at the same store that are not already assigned to any other Display Group.
- **Given** Yeis submits a valid new Display Group (group name, player, one or more screens,
  optional layout description and notes), **when** saved, **then** the group is created and the
  selected player and screens are each exclusively linked to it; the store's topology diagram
  (US-05.2) reflects the change immediately.
- **Given** Yeis tries to assign a player that is already assigned to another Display Group,
  **when** the form is submitted, **then** a clear inline validation error is shown and the
  record is not saved.
- **Given** Yeis tries to assign a screen that is already assigned to another Display Group,
  **when** the form is submitted, **then** a clear inline validation error is shown and the
  record is not saved.
- **Given** the DB, **when** any code path — including a direct DB write bypassing app logic —
  attempts to assign the same player or the same screen to a second Display Group, **then** the
  **unique constraint on the pivot/FK columns rejects the insert or update** (enforced at DB
  level, not only in the Form Request).
- **Given** the create and edit forms, **when** a required field is blank or invalid, **then**
  inline `@error` feedback appears next to the offending field; no field is silently skipped.
- **Given** Yeis edits an existing Display Group, **when** she changes the player or the screen
  list, **then** the exclusivity rules above are re-enforced for the new values; the old links
  are released and the new links are atomically applied in a single DB transaction.
- **Given** Yeis deletes a Display Group, **when** confirmed, **then** the group record is
  removed (soft-delete to preserve audit history), the linked player and screens are no longer
  assigned to any group, and they immediately re-appear in the dropdown for future groups at
  that store.
- **Given** any create, update or delete action, **when** it succeeds, **then** an async audit
  entry is written with actor id + role, action type, target (`DisplayGroup` + id), before/after
  values, IP, user agent and UTC timestamp (§14.5).
- **Given** a request to create or edit a Display Group for a store that belongs to a different
  client than the acting PM's authorised scope, **when** the request reaches the controller,
  **then** the policy denies it with a 403 — the store's `client_id` is verified against the
  actor's scope (US-01.3).
- **Given** an unauthenticated visitor or a technician, **when** they attempt any write action
  on Display Groups, **then** they are denied (no route, no form, no API).

### Engineering Bar checklist

- **Secure:** Defence in depth across all four layers.
  - **UI:** player and screen dropdowns only surface eligible (unassigned, correct type, same
    store) assets; no hint of another client's assets.
  - **Form Request:** validate `group_name` (required, max 255), `player_asset_id` (required,
    exists in `assets` with `asset_type = media_player`, same `store_id`, not already assigned
    to another group *at request time*), `screen_asset_ids` (required, array, min 1, each exists
    with `asset_type = digital_screen`, same `store_id`, not already assigned); validate
    `layout_description` (nullable, max 500) and `notes` (nullable, max 1000). Authorise via
    `DisplayGroupPolicy` (role `pm` + `client_id` scope) — never trust `client_id` from the
    request body.
  - **Model/Policy:** `$fillable` declared explicitly; no `$guarded = []`. `DisplayGroupPolicy`
    combines role and `client_id` scope. Business invariant (exclusive membership) enforced in
    the service layer inside a DB transaction.
  - **DB:** `display_groups` table: `id` UUID PK, `store_id` FK → `stores` (`on delete cascade`),
    `group_name` NOT NULL, `layout_description` nullable, `notes` nullable, `deleted_at` nullable
    (soft delete), timestamps. Player FK: `player_asset_id` UNIQUE NOT NULL → `assets`
    (`on delete restrict`) — the UNIQUE constraint enforces one-group-per-player at DB level.
    Screens pivot table `display_group_screens`: `display_group_id` FK + `asset_id` FK, composite
    PK, **UNIQUE constraint on `asset_id` alone** — enforces one-group-per-screen at DB level.
    Index `display_groups.store_id` (§14.4).
  - `client_id` for scope checks is derived from the store relation, never passed directly in
    the request.
- **Clean:** one `DisplayGroupService` encapsulates the transaction (detach old links, attach
  new, update FKs) — no transaction logic in the controller. Reuse the existing `ClientScope`
  trait (US-00.4) and audit hook (US-00.5). Match the naming and comment density of existing
  EPIC-03/04 controllers. No dead code; no speculative abstraction.
- **UX:** clear page hierarchy with store name as breadcrumb context; "Add Display Group" is
  the unambiguous primary action on the index; player and screen dropdowns show asset code +
  name + model so Yeis can identify hardware at a glance; inline `@error` on every field (no
  bulk-summary-only); designed empty state (no groups yet) and loading state on form submit;
  delete requires a plain-language confirmation modal naming the group; all interactive targets
  ≥ 44px; copy in Australian English.
- **No guessing:** before writing any migration or model, read the `assets` table schema (from
  EPIC-04 migration files) to confirm `asset_type` enum values, `store_id` column name and FK
  conventions. Run `php artisan tinker` to verify the relation between `Store → Asset` before
  coding the eligibility query. Confirm the soft-delete convention used in EPIC-04 before
  applying the same pattern.

### Definition of Done

- Migration: `display_groups` table + `display_group_screens` pivot, with all FKs, NOT NULLs,
  UNIQUE constraints and indexes as specified above.
- `DisplayGroup` model, `DisplayGroupService`, `DisplayGroupPolicy`, `DisplayGroupController`,
  `DisplayGroupRequest` (create + update variants), and Blade/Livewire views (index, create,
  edit, show) implemented.
- Exclusivity enforced at the DB (unique constraints proven by a test that attempts a duplicate
  insert directly via `DB::table()` and expects a `QueryException`).
- Cross-client deny test: PM scoped to Client A cannot create/edit/delete a group for Client B's
  store.
- Technician and unauthenticated deny tests pass.
- Audit entries written on create, update and delete; verified by test.
- Form validation: inline `@error` renders for each invalid field; tested.
- Pint + Larastan clean; Pest tests passing (unit: service transaction logic; feature: happy-path
  integration test for create).
- Australian English throughout UI copy.

---

## US-05.2 — Topology diagram on the store dashboard

**As** Yeis (PM)
**I want** to see a visual topology diagram on each store's dashboard showing which player drives
which screen(s) in each Display Group
**So that** I can instantly understand the store's AV wiring without reading a data table, and
this diagram also feeds the Display Group report (US-14.5).

**Estimate:** 3 · **Priority:** P1 · **Depends on:** US-05.1, EPIC-03 (store dashboard) · **Status:** 📋 Ready

### Acceptance criteria

- **Given** Yeis is on a store's dashboard, **when** the store has one or more Display Groups,
  **then** each group is rendered as a labelled topology card showing: the group name; the player
  asset (asset code, asset name, model, current status badge); and each connected screen (asset
  code, asset name, model, current status badge); with a visual connector (arrow or line) from
  player to screen(s).
- **Given** a store with multiple Display Groups, **when** the dashboard loads, **then** each
  group appears as a distinct card; groups are ordered by group name alphabetically.
- **Given** a store with no Display Groups, **when** the dashboard section renders, **then** a
  designed empty state is shown — "No display groups configured for this store" — with a link to
  create one (PM only; the link is not rendered for read-only contexts).
- **Given** any asset in a group has a status of `Faulty`, `Offline` or `Under Maintenance`,
  **when** the diagram renders, **then** that asset's status badge is visually distinct
  (colour-coded or icon-labelled) so Yeis can spot problems at a glance.
- **Given** the diagram, **when** Yeis clicks a player or screen card, **then** she navigates to
  that asset's detail page (linking back into EPIC-04).
- **Given** the diagram data, **when** Yeis views a store belonging to Client A, **then** only
  that store's Display Group data is returned — no cross-client data leaks (enforced by the same
  `client_id` scope as US-05.1).
- **Given** the diagram, **when** rendered, **then** it is accessible: all status information
  conveyed by colour is also conveyed by a visible text label or icon label (not colour alone);
  player-to-screen relationships are comprehensible without visual rendering (i.e. a screen
  reader reads "Player: Beat MIB 02 (PAN-PLY-001, Active) drives Screen: Samsung QH98C
  (PAN-SCR-001, Active)" for each group).
- **Given** this diagram component, **when** reused for the Display Group report (US-14.5),
  **then** it can be rendered standalone (e.g., as a Blade partial or Livewire component) without
  the rest of the store dashboard — the component is self-contained and takes a `DisplayGroup`
  collection as its input.

### Engineering Bar checklist

- **Secure:** the controller or Livewire component loading diagram data must scope the query to
  the store's `client_id` (via the `ClientScope` trait / policy) — never query `display_groups`
  without a `store_id` + `client_id` constraint. Read-only surface: no mutation routes exposed
  here. The "Add Display Group" link is guarded by `@can` so it never renders for non-PM actors.
- **Clean:** implement as a self-contained Blade partial (or Livewire component) that accepts a
  `$displayGroups` collection — no ad-hoc queries inside the view. Reuse the asset status badge
  component if one exists from EPIC-04; otherwise create one reusable badge partial (not
  inline per-view colour logic). No duplicate query paths between the dashboard and the report.
- **UX:** clear section heading "Display Groups" with a hierarchy that does not compete with the
  asset inventory table also on the dashboard (§8); each group card has a group name as its
  primary label; player and screen assets are visually distinguished (e.g., player left / screens
  right with a connector, or player above / screens below in a tree); status badges use both
  colour and text; empty state is informative and non-blaming; no horizontal scroll on the
  diagram at 768px (PM minimum width); tap/click targets on asset links ≥ 44px; copy in
  Australian English.
- **No guessing:** before building the component, read the store dashboard controller/view from
  EPIC-03 to understand the data already passed to that view and the existing section layout —
  do not assume the dashboard structure; verify column names (`player_asset_id`,
  `display_group_screens.asset_id`) on the actual migrations from US-05.1 before writing the
  Eloquent eager-load query (`with(['player', 'screens'])`).

### Definition of Done

- Blade partial (or Livewire component) `display-group-topology` implemented; accepts a
  `DisplayGroup` collection; renders group name, player card, screen card(s) and connector.
- Asset status badges visually distinguish all five statuses; colour is supplemented by text.
- Empty state renders when no groups exist.
- Accessible: screen-reader traversal communicates player → screen relationships; no information
  conveyed by colour alone.
- Cross-client scope tested: PM scoped to Client A cannot see Client B's store topology data
  (assert the query is constrained).
- Component is demonstrably reusable standalone (a Pest test or a Blade preview that renders it
  with a fixture `DisplayGroup` collection, independent of the full store dashboard view).
- Renders correctly at 768px and 1280px without horizontal overflow.
- Pint + Larastan clean; tests passing.
- Australian English throughout UI copy.
