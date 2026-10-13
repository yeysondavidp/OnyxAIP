# EPIC-19 — Field Findings: Content Faults & Player Swaps (Pandora Miranda, 12 Oct 2026)

> Raised from the first real Pandora visit after go-live: **Pandora, Westfield Miranda (NSW),
> 12 October 2026**. Source: `.claude/files/reports/Technical_Service_Report_Pandora_Miranda_
> 12Oct2026.pdf` (+ `.docx`, local only: `.claude/files/` is gitignored because this repo is
> public) and 25 site photos kept in ONYX's `Pandora/03 - SERVICE REPORTS/2026-10-12 Miranda`
> folder. Serial numbers and MAC addresses live in the report and the production register, never
> in this file.
>
> **What happened on site.** Two display issues, reported by the Store Manager as ongoing for more
> than four weeks. Neither was a hardware or installation fault — both were **content-level**:
> 1. **Front window:** portrait screen in a totem, driven by a Beat MIB Model 19 player,
>    online and playing — but scheduled **landscape 16:9**
>    content. Rotating the output −90° makes it upright but stretched. Needs **portrait 9:16,
>    1080 × 1920 px** content.
> 2. **Feature wall + store audio:** one player drives both the feature-wall screen **and the
>    store audio** (via an amplifier). The old unit works. A
>    **new replacement player** was already in store but
>    **not provisioned** (Beat logo only, no audio, no content/audio schedule assigned). The old
>    player was left in service.
>
> Next steps had three **different owners**, none of them a technician return visit: push portrait
> content (content team) → assign content + audio schedule to the new player (content team) →
> tell the Store Manager whether to swap (PM), who can do the swap in store himself.
> **No parts required.**
>
> **What the platform could not express today** (verified against the code, 2026-10-13):
> - `PostServiceStatus` only has Active / Still Faulty / Decommissioned / Replaced — no way to say
>   "hardware fine, **content** action required" (`app/Enums/PostServiceStatus.php`).
> - No structured **next steps / follow-ups** with an owner; the only follow-up mechanism is a
>   remediation sub-job, which implies a site visit (EPIC-08.5 / US-11.2).
> - No way to register a **replacement unit that is on site but not yet in service**, nor a
>   one-step **player swap** in a Display Group (`DisplayGroup` has a single `player_asset_id`).
> - **Audio** is invisible in the topology even though Infrastructure already has
>   `connected_from_asset_id` / `connected_to_asset_id` (`AssetInfrastructureDetail`).
> - The screen's required **content spec** (orientation + resolution) is stored
>   (`asset_screen_details.resolution_width/height`, `orientation`) but never surfaced as a
>   content requirement — which is exactly what the content team got wrong.
> - `job_photos` has no **caption** and no **asset link**, so the report's numbered, captioned
>   "Site Photographs" page cannot be built from platform data (EPIC-18).
> - No **fault reported date / reporter** on a job ("ongoing for more than four weeks").

Related: EPIC-04 (assets), EPIC-05 (display groups), EPIC-06 (lifecycle), EPIC-08 (jobs),
EPIC-10 (technician flow), EPIC-11 (validation), EPIC-12 (SLA), EPIC-18 (service reports).
Sprint: 12 (see `sprints/sprint-plan.md`).

---

## US-19.1 — Screen content specification (derived)

**As** Yeis (PM) and the content team
**I want** every Digital Screen to show the exact content it needs — e.g. "Portrait · 9:16 ·
1080 × 1920 px"
**So that** content is produced to the right format first time, and a technician on site can tell
in seconds whether a fault is content or hardware (Miranda front window).

**Estimate:** 2 · **Priority:** P0 · **Depends on:** US-04.2 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a screen with resolution and orientation, **when** its detail page, the technician
  Screen 3 asset panel, a Display Group topology card or the Display Group report is shown,
  **then** it displays orientation, simplified aspect ratio and the pixel size in the
  orientation's order (portrait → `1080 × 1920`, landscape → `1920 × 1080`).
- **Given** a screen whose stored resolution contradicts its orientation (e.g. portrait with
  width > height), **when** the PM saves it, **then** the form normalises the order and says so
  inline (`@error`-style hint, non-blaming); existing rows render a caution badge, never crash.
- **Given** resolution or orientation is missing, **then** the spec reads "Not recorded" with a
  link to edit — no guessed values.

### Engineering Bar checklist
- **Secure:** read-only derivation; reuses existing asset policies and `client_id` scope.
- **Clean:** one derivation method on `AssetScreenDetail` (no new columns) reused by every view.
- **UX:** spec shown in plain words, large enough to read on a 320px phone.
- **No guessing:** confirm the `Orientation` enum cases and current resolution data quality in
  production with tinker before writing the normalisation rule.

### Definition of Done
Spec derived from existing columns, shown on the four surfaces above; contradiction handled on
save and on render; unit test for the derivation (portrait, landscape, missing, contradictory);
Pint + Larastan + Pest green.

---

## US-19.2 — Asset outcome "Content action required"

**As** a technician on Screen 4
**I want** to record that an asset is working but showing the wrong content
**So that** the PM sees a content problem (no parts, no return visit) instead of being forced to
choose between "Active (resolved)" and "Still Faulty".

**Estimate:** 3 · **Priority:** P0 · **Depends on:** US-10.4, US-11.1 · **Status:** ⏸ Needs
decision D2 (see sprint plan)

### Acceptance criteria
- **Given** Screen 4, **when** the technician picks an outcome, **then** "Content action
  required" is offered alongside the existing four, and **a per-asset note becomes mandatory**
  for it (max 500 chars, inline validation).
- **Given** a job is validated with that outcome, **then** the asset's status transitions per
  decision D2 (recommended: stays **Active**, because the hardware works) and an **open follow-up**
  (US-19.3) is created automatically, pre-filled from the note, owner "ONYX content".
- **Given** the asset service history and reports (US-11.3, EPIC-14), **then** the outcome shows
  with its label; existing outcomes and history rows are unchanged.

### Engineering Bar checklist
- **Secure:** the new value is accepted only through the existing signed-URL completion
  checkpoint; validated with `Rule::enum`; the DB column (enum/varchar + check) accepts the new
  value via migration — app and DB agree.
- **Clean:** add the case to `PostServiceStatus` and its `toAssetStatus()` match; no parallel
  enum.
- **UX:** the option is explained in one line ("Screen/player works, but the content is wrong").
- **No guessing:** verify the `job_asset_outcomes` column type before the migration.

### Definition of Done
New outcome end-to-end (Screen 4 → validation → history → reports); note required; follow-up
auto-created; feature test for the validation path; Pint + Larastan + Pest green.

---

## US-19.3 — Job follow-up actions ("Next steps") with an owner

**As** Yeis (PM)
**I want** to record the next steps from a visit as an ordered checklist, each with an owner
(ONYX content, ONYX PM, client, store manager, technician) and an open/done state
**So that** nothing agreed on site is lost in an email, and work that needs no visit (Miranda's
three steps) doesn't have to become a remediation sub-job.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-08.1, US-11.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a job in Completed or Validated status, **when** the PM adds a follow-up
  (description ≤ 500 chars, owner, optional affected asset from the job's assets), **then** it
  is saved in order and can be reordered, edited while open and marked done (who + when).
- **Given** open follow-ups, **then** they appear on the job page, on the store dashboard next to
  open faults, and in the Open Faults report (US-14.3) as a separate section.
- **Given** a follow-up needs a site visit, **then** a "Create remediation sub-job" action links
  it to the existing US-11.2 flow instead of duplicating it.
- **Given** a technician or `client_user`, **then** they cannot create, edit or close follow-ups.

### Engineering Bar checklist
- **Secure:** PM role + `client_id` scope via policy; FormRequest validates owner with
  `Rule::enum` and the asset id **exists on this job**; DB: FK `job_id` cascade, FK `asset_id`
  null-on-delete, `client_id` NOT NULL + index, `owner`/`status` NOT NULL; `$fillable` only.
- **Clean:** `ClientScoped` + `Auditable` traits like `ServiceJob`; create/close are audited.
- **UX:** checklist UI with obvious "Add next step"; empty state; done items visibly struck
  through, not hidden.
- **No guessing:** confirm the US-14.3 report query structure before extending it.

### Definition of Done
Follow-ups CRUD (no hard delete once done), visible on job/store/report, scoped + audited,
feature tests for create/close/cross-tenant denial; Pint + Larastan + Pest green.

---

## US-19.4 — Replacement unit on site + one-step player swap

**As** Yeis (PM)
**I want** to register a replacement player that is already at the store but not in service,
and later swap it in with one action
**So that** the register always matches reality (Miranda had two feature-wall players on site)
and a swap done by the Store Manager is recorded without a technician visit.

**Estimate:** 8 · **Priority:** P1 · **Depends on:** US-04.3, US-05.1, US-06.1 · **Status:** ⏸
Needs decision D1 (see sprint plan)

### Acceptance criteria
- **Given** a player asset, **when** the PM registers a replacement, **then** the new asset is
  created at the same client/store with `replaces_asset_id` pointing at the old one and a
  not-in-service status (per D1), and both asset pages show the link.
- **Given** a pending replacement, **when** the PM confirms "Swap now" (with an optional note and
  who did it, e.g. "Store Manager"), **then**, in **one DB transaction**, the new player takes the
  old one's Display Group slot (screens and connected infrastructure unchanged), the new player
  becomes Active, the old one moves to Decommissioned (or Spare per D1), and both transitions are
  audited with the reason.
- **Given** a swap fails part-way, **then** nothing changes (transaction) and the PM sees a
  plain-language error.
- **Given** a replacement of a different type, client or store, **then** it is rejected at
  request, model and DB (FK + check) level.

### Engineering Bar checklist
- **Secure:** PM + `client_id` scope; type/client/store equality enforced in FormRequest, model
  invariant and DB; the existing "a player belongs to exactly one Display Group" unique index
  still holds during the swap.
- **Clean:** transitions go through the EPIC-06 status service; swap logic in one service class.
- **UX:** a single, confirmable "Swap now" action that shows exactly what will change before it
  happens.
- **No guessing:** read the `display_groups` unique indexes and the EPIC-06 transition map before
  coding; D1 must be answered first.

### Definition of Done
Replacement registration + atomic swap, audited, invariant tests (wrong type/store, rollback,
display-group uniqueness); Pint + Larastan + Pest green.

---

## US-19.5 — Audio path in the Display Group topology

**As** Yeis (PM) and the technician
**I want** the topology to show that a player also drives the store audio (and through which
amplifier)
**So that** nobody swaps or reboots the feature-wall player without knowing the store will lose
its music (Miranda).

**Estimate:** 3 · **Priority:** P1 · **Depends on:** US-05.2, US-04.5 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** an Infrastructure asset (e.g. amplifier) with `connected_from_asset_id` = the group's
  player, **then** the topology card, the technician Screen 3 asset panel and the Display Group
  report (US-14.5) list it under the player ("Audio: <asset name> · <model>").
- **Given** a player that drives audio, **when** a swap (US-19.4) or Decommission is requested,
  **then** the confirmation warns "This player also drives the store audio".
- **Given** no connected infrastructure, **then** nothing extra is shown (no empty noise).

### Engineering Bar checklist
- **Secure:** read-only; existing scopes. **Clean:** reuse the existing connected-asset FKs — no
  new table. **UX:** clear icon + text, not colour alone. **No guessing:** check how
  `connected_from/to` are populated in production before relying on direction.

### Definition of Done
Audio/infrastructure shown on the three surfaces, warning on swap/decommission; feature test;
Pint + Larastan + Pest green.

---

## US-19.6 — Photo captions and asset tagging

**As** a technician (and the PM reviewing)
**I want** to caption each photo and link it to the asset it shows
**So that** the service report's numbered "Site Photographs" page (e.g. "2. Front window player
label. Beat MIB Model 19, serial …") and each asset's service history can be built
from platform data.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-10.2, US-10.4, US-11.3 · **Status:** 📋
Ready

### Acceptance criteria
- **Given** Screens 2 and 4, **when** a photo is taken, **then** the technician can optionally add
  a caption (≤ 200 chars) and pick one of the job's affected assets — client-side in Alpine, sent
  with the existing upload checkpoint (no extra round-trip).
- **Given** a Completed job, **when** the PM reviews it, **then** captions/asset tags can be edited
  and photos reordered; after **Validation** they are read-only (service history is
  append-only, §7).
- **Given** an asset's service history, **then** its tagged photos appear with captions.

### Engineering Bar checklist
- **Secure:** the asset id must belong to **this job's** affected assets (request + model check;
  FK null-on-delete); caption escaped on output; signed-URL upload unchanged.
- **Clean:** add nullable `caption`, `asset_id`, `sort_order` to `job_photos` (`$fillable`
  updated); reuse the existing photo endpoints.
- **UX:** caption is optional and never blocks the flow; 44px tap targets on the asset picker.
- **No guessing:** confirm the upload payload shape in `JobFlowController::uploadPhoto` first.

### Definition of Done
Caption/tag/reorder end-to-end, locked after validation, shown in history; feature tests incl.
"asset not on job" rejection; Pint + Larastan + Pest green.

---

## US-19.7 — Fault reported date and reporter on a job

**As** Yeis (PM)
**I want** to record when and by whom a fault was first reported (e.g. "Store Manager, about
four weeks before the visit")
**So that** reports and the client conversation reflect how long the store was affected, not
just when the job was created.

**Estimate:** 3 · **Priority:** P2 · **Depends on:** US-08.1, US-12.2 · **Status:** 📋 Ready
(SLA impact is decision D4)

### Acceptance criteria
- **Given** a Fault Repair job, **when** the PM creates or edits it, **then** they can enter an
  optional reported date (not in the future, store timezone, stored UTC) and reporter (≤ 120
  chars).
- **Given** a reported date, **then** the job page and the job's service history entry show
  "Reported N days before the visit".
- **Given** decision D4, **then** the SLA clock either keeps starting at job creation (default,
  no change) or starts at the reported date.

### Engineering Bar checklist
- **Secure:** FormRequest validates `before_or_equal:now`; nullable columns; PM-only.
- **Clean:** two nullable columns on `service_jobs`; Tz helper for display.
- **UX:** date picker with plain helper text. **No guessing:** read `SlaClockService` before D4.

### Definition of Done
Fields captured, displayed, audited on change; tests; Pint + Larastan + Pest green.

---

## US-19.8 — PM back-entry of a visit done without the technician link

**As** Yeis (PM)
**I want** to record a visit that really happened but was not run through the technician link
(no checkpoints, no in-app GPS) and close the job honestly
**So that** jobs like JOB-2026-004 (Miranda, 12 Oct 2026: link never opened, evidence in a PDF
and phone photos) reach Validated and feed service history without fake replay times or GPS.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-08.3, US-11.1 · **Status:** 📋 Ready
(decided with ONYX 2026-10-13: preferred over replaying the technician flow)

### Acceptance criteria
- **Given** a job in Invited or Accepted status, **when** the PM chooses "Record offline visit",
  **then** they must enter the actual visit date and start/end time (store timezone, stored UTC,
  not in the future), the technician(s) who attended, a reason (≤ 1000 chars), at least one
  photo or attachment as evidence, and a per-asset outcome + note for each affected asset.
- **Given** a valid back-entry, **then** the job moves to **Completed** through
  `JobTransitionService` (a new, PM-only `Invited/Accepted → Completed` path that requires the
  back-entry record), each assigned technician's status is closed, and normal Validation
  (US-11.1) follows unchanged.
- **Given** a back-entered job, **then** job page, service history and reports label it
  "Recorded by PM — no on-site GPS", and no GPS or checkpoint rows are fabricated.
- **Given** a technician link for that job, **then** it stops working once back-entry is saved.
- **Given** a non-PM user or a job of another tenant, **then** the action is denied.

### Engineering Bar checklist
- **Secure:** PM + `client_id` scope via policy; FormRequest validates dates, technicians
  (must be assigned to the job), assets (must be on the job) and evidence MIME (reuse the
  `UploadJobAttachmentRequest` allow-list); DB: back-entry columns or table with NOT NULL actor,
  visit times and reason; the new transition is allowed only when that record exists.
- **Clean:** reuse `JobTransitionService`, `JobAssetOutcome` and the attachment storage path; one
  service class for the back-entry; fully audited.
- **UX:** one form with a clear warning that this replaces the technician flow; inline errors.
- **No guessing:** read `JobTransitionService` guards and the `job_technicians` status enum
  before adding the path.

### Definition of Done
Back-entry form → Completed → Validated works end-to-end with evidence and outcomes, labelled
everywhere, link revoked, audited; feature tests incl. cross-tenant and non-PM denial; Pint +
Larastan + Pest green. Then close JOB-2026-004 with it.
