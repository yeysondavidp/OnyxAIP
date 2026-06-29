# EPIC-02 — Client Management

> The first tenant-scoped CRUD surface. Proves the security spine from EPIC-01 in practice:
> every mutation in this epic layers UI + Controller/Form Request + Policy + DB constraints.
> Get it right here and every later CRUD epic has a proven reference to copy.

Related: US-00.4 (scoping trait), US-00.5 (audit), US-01.3 (policy pattern), US-12.1 (SLA
profile CRUD — placeholder dependency). SRA: §3.1 (Client model), §10.1 (SLA profile),
§9 (client dashboard context). Sprint: 2.

---

## US-02.1 — CRUD clients

**As** Yeis (PM)
**I want** to create, view, edit, and deactivate client records (Pandora, Sephora, Dior, etc.)
**So that** ONYX has an accurate register of every brand account and all downstream records
(stores, assets, jobs) are correctly attributed.

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-00.3, US-00.4, US-00.5, US-01.3 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** Yeis is on the Create Client form, **when** she submits valid data, **then** the
  client is saved with a system-generated UUID, all required fields present, `is_active = true`
  by default, and she sees a success flash; the action is async audit-logged (US-00.5) with
  actor, before/after values, and UTC timestamp.
- **Given** the Create Client form, **when** Yeis submits a `client_code` already taken, **then**
  she sees an inline `@error` field-level message ("Client code is already in use") before the
  form submits — and the DB unique constraint rejects a duplicate even if the validation layer
  were bypassed.
- **Given** Yeis is editing a client, **when** she saves valid changes, **then** the record
  updates and the audit log captures the before/after diff; `client_code` uniqueness is still
  enforced against all other clients (excluding the current record).
- **Given** Yeis deactivates a client (`is_active = false`), **then** the client is **not**
  hard-deleted — the record and all its relational history are preserved; the deactivation is
  audit-logged; deactivated clients are visually distinguished in list views.
- **Given** an unauthenticated visitor or a `technician`/`client_user`, **when** they hit any
  client CRUD route, **then** they are denied (403 or redirect to login) — enforced by the
  `ClientPolicy` (role: `pm` only), not just by UI hiding.
- **Given** the edit form, **when** a field contains invalid input, **then** inline `@error`
  validation appears on the failing field without losing the rest of the form state; errors use
  plain, non-blaming Australian English.
- **Given** the Create/Edit form, **when** submitted, **then** all inputs are validated in the
  `StoreClientRequest` / `UpdateClientRequest` Form Request before reaching the controller; the
  controller calls `authorize()` before any DB write.

### Engineering Bar checklist

- **Secure (defence in depth — this is the reference pattern for EPIC-02):**
  - UI: Create/Edit links hidden for non-`pm` roles; deactivate action hidden for
    `technician`/`client_user`.
  - Controller: `authorize()` via `ClientPolicy` on every action; `StoreClientRequest` /
    `UpdateClientRequest` Form Requests validate and reject unknown fields (never `$request->all()`
    into a write).
  - Model: `$fillable` explicitly lists every writable column (`client_name`, `client_code`,
    `primary_contact`, `primary_email`, `notes`, `is_active`); `$guarded = []` is **banned**.
    `client_id` on child models is set from authorised context, never from request input.
  - DB: `client_code` has a `UNIQUE` index; `client_name` and `client_code` are `NOT NULL`;
    `is_active` has a DB default of `1`. ONYX's recurring audit failure is "guard in controller
    but not DB" — the unique index here is mandatory, not optional.
  - Audit: create, edit, and deactivate events dispatched async to the audit log (US-00.5) with
    before/after values.

- **Clean:** reuse the `ClientPolicy` pattern from US-01.3 as the documented template; Livewire
  component (PM desktop) for the form, reusing the base PM shell (US-00.3); no duplicate
  validation logic between Form Request and controller; inline methods only if used once.

- **UX:** clear form hierarchy with an obvious "Save Client" primary action; inline `@error` on
  every field (not a bulk summary at the top); deactivate uses a confirmation dialog, not an
  immediate action; loading state on submit; empty state on the clients list; all error copy in
  Australian English; no jargon like "HTTP 422".

- **No guessing:** read the `clients` migration before writing the model to confirm column names
  (`client_name`, `client_code`, `primary_contact`, `primary_email`, `sla_profile_id`, `notes`,
  `is_active`); verify the `ClientPolicy` wires up via `AuthServiceProvider` using tinker before
  any feature work.

### Definition of Done

Full CRUD (create/read/update/deactivate, no hard delete) working for `pm` role; denied cleanly
for all other roles at the policy layer; `client_code` unique at DB and validation layers; all
four Engineering Bar layers verified (UI + Request + Policy + DB); async audit logs on create,
edit, and deactivate; inline `@error` validation on all fields; loading/empty/error states
designed; one happy-path integration test (`pm` creates a client) and one deny test
(`technician` blocked); Pint + Larastan clean.

---

## US-02.2 — Assign and edit SLA profile on a client

**As** Yeis (PM)
**I want** to select and update the SLA profile linked to a client record
**So that** SLA clock logic (EPIC-10/12) knows which response and resolution windows apply to
each brand, and the linkage survives changes to either side.

**Estimate:** 3 · **Priority:** P1 · **Depends on:** US-02.1, US-12.1 (SLA profile CRUD — ⏸ Blocked: US-12.1 not yet written; implement placeholder FK + null selection in the interim) · **Status:** ⏸ Blocked (US-12.1 must exist or a seed placeholder SLA profile must be present before the selection UI is meaningful; story is Ready to implement the linkage and UI now, but full acceptance requires at least one SLA profile record)

### Acceptance criteria

- **Given** at least one SLA profile exists (seeded or created via US-12.1), **when** Yeis
  creates or edits a client, **then** she can select an SLA profile from a dropdown and the
  `sla_profile_id` FK is saved on the `clients` table.
- **Given** no SLA profiles have been created yet, **when** the dropdown renders, **then** it
  shows a plain-language empty state ("No SLA profiles configured yet — add one in Settings")
  rather than an empty `<select>` with no explanation.
- **Given** Yeis selects a profile, **when** she saves, **then** the FK is validated to confirm
  the selected `sla_profile_id` exists in the `sla_profiles` table (no orphan FKs); the DB FK
  constraint enforces this at the storage layer too.
- **Given** Yeis clears the SLA profile (sets to null), **when** she saves, **then**
  `sla_profile_id` is stored as `NULL` (nullable FK); downstream SLA logic must handle a null
  profile gracefully (no 500; warn Yeis visually that this client has no SLA profile).
- **Given** an SLA profile is deleted (future US-12.x), **when** the deletion is attempted,
  **then** the system either prevents deletion if clients reference it, or cascades/nullifies
  appropriately — the FK `on delete` behaviour is decided at migration authoring time and
  documented here: **use `SET NULL`** so client records survive SLA profile removal.
- **Given** a `technician` or `client_user`, **when** they attempt to reach the client edit
  route, **then** the `ClientPolicy` denies them (inherited from US-02.1 — no additional route
  needed).

### Engineering Bar checklist

- **Secure:** `sla_profile_id` validated in the `UpdateClientRequest` as `nullable|exists:sla_profiles,id`
  — the controller never trusts raw ID input; `ClientPolicy` already enforces `pm`-only (US-02.1).
  DB FK with `ON DELETE SET NULL` is defined in the migration — not just a rule in code.

- **Clean:** selection UI is a single `<select>` (or Livewire combobox if the profile list grows)
  reused in both Create and Edit forms; no duplicate FK validation logic; `sla_profile_id`
  already on the `clients` `$fillable` list from US-02.1 — no model change required if US-02.1
  was written correctly.

- **UX:** dropdown label is "SLA Profile"; empty-profile warning shows inline on the client
  detail page ("This client has no SLA profile — SLA tracking is disabled"); null is a permitted
  and explicitly labelled option ("— None —"), not a hidden default; Australian English copy.

- **No guessing:** confirm `sla_profiles` table name and primary key column (`id` vs `uuid`)
  against the US-12.1 migration before writing the `exists:` validation rule; confirm nullable
  FK behaviour with a tinker test before declaring done.

### Definition of Done

`sla_profile_id` FK present in `clients` table with `ON DELETE SET NULL`; nullable selection
works in both Create and Edit forms; `exists:` validation enforced in Form Request; empty-state
message when no profiles exist; null-profile warning on client detail; no hard dependency on
US-12.1 being Done (story ships with null allowed and an informative empty state); Pint +
Larastan clean; one test confirming the FK validates correctly and one confirming null is
accepted.

---

## US-02.3 — Client list with search, filter, and active/inactive toggle

**As** Yeis (PM)
**I want** a paginated, searchable, filterable list of all client records scoped to ONYX
**So that** I can quickly find any brand account, see its active/inactive state at a glance,
and navigate to its detail or edit it — without wading through stale or irrelevant records.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-02.1 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** Yeis navigates to the Clients index, **when** the page loads, **then** she sees a
  paginated table (page size: 25) of all clients ordered by `client_name` ascending; each row
  shows: `client_name`, `client_code`, `primary_contact`, `primary_email`, `is_active` badge,
  and action links (View, Edit).
- **Given** the search input, **when** Yeis types a partial name or code, **then** the list
  filters in real-time (Livewire) to clients whose `client_name` or `client_code` contains the
  query (case-insensitive); the URL reflects the search state so the filtered view is
  bookmarkable/shareable.
- **Given** the active/inactive toggle, **when** Yeis sets it to "Active only" (the default),
  **then** only `is_active = true` clients appear; when set to "All", both active and inactive
  appear; inactive rows are visually muted (e.g., reduced opacity or a "Deactivated" badge)
  so Yeis does not mistake them for live accounts.
- **Given** no clients exist yet, **when** the page loads, **then** a designed empty state is
  shown: plain-language message ("No clients added yet") and a prominent "Add Client" CTA.
- **Given** a search that matches nothing, **when** results render, **then** a "No clients match
  your search" message appears — not a blank table with no explanation.
- **Given** a `technician` or unauthenticated user, **when** they hit the clients index route,
  **then** they are denied by the `ClientPolicy`/middleware (403 / redirect); the list is
  **PM-only** and this is enforced at the route/policy layer, not just via nav hiding.
- **Given** a large number of clients (future-proofing), **when** the list renders, **then**
  the query uses `WHERE is_active = ?` and indexed columns only; a DB index on `is_active` is
  documented (the broader index set is defined in US-00.4's migration recipe).

### Engineering Bar checklist

- **Secure:** index route guarded by `ClientPolicy@viewAny` (or equivalent) and the `pm`
  middleware group — no client data reachable by unauthenticated or non-`pm` users. The
  multi-client scoping trait (US-00.4) applies even here: even though PMs see all ONYX clients,
  the global scope ensures future-proofing against a `client_user` accidentally reaching this
  route.

- **Clean:** Livewire component (`ClientList`) handles search + filter state; pagination via
  Laravel's `paginate()` (not `get()` + manual slice); search and filter are query-string
  persisted (`#[Url]` on Livewire properties); no N+1 queries — eager-load any displayed
  relations (e.g., `slaProfile` if shown in the table).

- **UX:** search input has a visible label ("Search clients") and a clear/reset affordance;
  active/inactive toggle is a clear segmented control or checkbox, not a hidden filter;
  designed loading state while Livewire re-renders; pagination controls are accessible and
  labelled; "Add Client" button is the obvious primary action on the empty state and in the
  page header; all copy in Australian English.

- **No guessing:** confirm the Livewire `#[Url]` query-string binding works with the pagination
  component in Laravel 11 before implementing (known to require `withQueryString()` on the
  paginator); verify index exists on `client_name`, `client_code`, and `is_active` in the
  migration before claiming performance is acceptable.

### Definition of Done

Paginated, searchable, filterable client list working for `pm`; denied for all other roles at
policy layer; active/inactive toggle defaults to active-only; empty state and no-results state
designed; URL reflects filter/search state; no N+1 queries (confirmed via Laravel Debugbar or
query logging in tests); Pint + Larastan clean; one happy-path integration test (PM sees
clients, filtered correctly) and one deny test (technician blocked).
