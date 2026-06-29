# EPIC-09 — Technician Directory & Invitation

> The bridge between a job and the person who works it. Yeis curates a directory of technicians
> (§11) and dispatches them to jobs via **signed, expiring, single-purpose** invitation links
> (US-01.4) — so Michael works with no login and Sneider gets the same flow plus history. Every
> invite and acceptance is an audited event (US-00.5).

Related: §11, §12.2, §5.4; US-01.4 (signed URLs), US-00.4 (scoping), US-00.5 (audit), EPIC-08
(jobs to invite to). Sprint: 6.

---

## US-09.1 — CRUD technician profiles

**As** Yeis (PM)
**I want** to create, read, update and deactivate technician profiles in a directory
**So that** I have a trustworthy roster — with specialties, certs, preferred clients and asset
competency — to dispatch the right person to each job (§11).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-01.3 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a PM, **when** they open the technician directory, **then** they can create a profile
  with: name, contact (email + phone), **specialty categories** (multi-select from a fixed set:
  AV Installation, Digital Signage, Electrical, Retail Fit-out, Lightbox Service,
  Network/Connectivity), **certifications** (multi-select free-text, e.g. White Card, EWP,
  Working at Heights), **preferred clients** (multi-select of ONYX clients), and an
  **asset-competency note** (free text, e.g. "Samsung commercial displays, Beat MIB 02") (§11).
- **Given** a profile, **when** created, **then** it records the **account vs guest distinction** —
  a guest technician (Michael) has no login; an account holder (Sneider) is linkable to a
  `technician` user (US-01.2). Linking a profile to an account is optional and explicit.
- **Given** a non-PM actor, **when** they hit any technician-directory route, **then** access is
  **denied** — directory CRUD is **PM-only** (policy + middleware).
- **Given** a profile in use by jobs, **when** the PM "deletes" it, **then** it is **soft-deleted /
  deactivated** (not hard-deleted) so historical job/invite linkage survives.
- **Given** any create/update/deactivate, **when** it succeeds, **then** an **audit entry** is
  written (US-00.5) with actor, action and before/after.
- **Given** the create/edit form, **when** a field is invalid (e.g. malformed email, empty name),
  **then** an inline `@error` message shows the offending field, in plain Australian English.

### Engineering Bar checklist
- **Secure:** PM-only via policy **and** route middleware (defence in depth), not UI-hiding alone;
  Form Request validates every field — specialty categories validated against the **fixed enum**,
  preferred clients validated as **real ONYX client ids** (never trusted from input); `$fillable`
  only, never `$guarded = []`; DB enforces NOT NULL on name + at least one contact, FK on the
  account-link with correct on-delete, indexes for lookup. Multi-value fields stored so they can't
  reference another tenant's client.
- **Clean:** reuse the EPIC-02 client list for the preferred-clients picker; one Form Request for
  create/update; specialty set defined once as a typed enum; no copy-paste validation.
- **UX:** clear hierarchy, one obvious primary action ("Add technician"); multi-selects are
  keyboard-accessible with ≥44px targets; designed empty (no technicians yet), loading and error
  states; inline field-level errors; en-AU copy.
- **No guessing:** confirm the technician/user relation and client relation with tinker before
  writing the account-link and preferred-clients scope; the specialty list is fixed per §11 — do
  not invent categories.

### Definition of Done
PM-only CRUD with all §11 fields; account-vs-guest distinction modelled; soft-delete preserves
history; multi-value validation against fixed enum + real clients; DB constraints present;
create/update/deactivate audited; inline validation + designed states; tests prove non-PM denial
and that preferred clients can't reference foreign data; Pint + Larastan clean.

---

## US-09.2 — Send a signed job invitation email to one or more technicians

**As** Yeis (PM)
**I want** to invite one or more technicians to a job by email, each with a secure link and a
calendar save
**So that** Michael and Sneider receive everything they need to attend, with no login friction
(§5.4, §12.2, US-01.4).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-09.1, US-01.4, EPIC-08 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a job and the directory, **when** the PM selects **one or more** technicians and sends,
  **then** each selected technician is emailed an invitation — supporting the multi-technician
  model (§5.4) where each invite has its **own** lifecycle.
- **Given** an invitation, **when** the link is generated, **then** it is a **signed, expiring URL
  scoped to that one technician + that one job**, validated server-side on every request, per the
  US-01.4 contract — never a shared or job-wide link.
- **Given** the email, **when** dispatched, **then** it is **queued** (§2.3), not sent inline, so
  the PM's request returns immediately even for many recipients.
- **Given** the email, **when** received, **then** it includes a **calendar save**: a **Google
  Calendar URL** and an **.ics download**, populated with the job time in the **store's timezone**
  (displayed) while stored UTC (§3.2).
- **Given** the send action, **when** triggered repeatedly, **then** invitation sending is
  **rate-limited** (§14.3) to prevent abuse/accidental flooding.
- **Given** a successful send, **when** it completes, **then** each invitation is recorded with an
  **invited** lifecycle state and an **audit entry** is written per recipient (US-00.5).
- **Given** a recipient already invited to this job, **when** the PM re-sends, **then** the system
  does not silently duplicate the lifecycle — it routes to the resend path (US-09.4) rather than
  creating a second competing invite.

### Engineering Bar checklist
- **Secure:** links generated **only** through the US-01.4 signed-link service (expiring,
  per-(technician,job) scope, server-validated every request); Form Request authorises the PM
  against the **job's `client_id` scope** before any invite is created — a PM cannot invite to a
  job outside ONYX's data; rate-limited send (§14.3); DB enforces a **unique (job, technician)
  invitation** index so duplicates can't exist even if a guard is bypassed; recipient list
  validated against real directory ids only.
- **Clean:** reuse the signed-link service from US-01.4 and the queued-mail pattern from §2.3 /
  US-01.1; one invitation model/service; .ics + Google URL built by a single reusable helper; no
  bespoke link signing here.
- **UX:** multi-select recipient picker with clear primary "Send invitations" action; per-recipient
  confirmation feedback; plain-language success/partial-failure messaging; ≥44px targets; en-AU.
- **No guessing:** confirm the job→client relation and store timezone field with tinker before
  building scope checks and the .ics; confirm token lifetime/scope against §14.3 + US-01.4 before
  generating links.

### Definition of Done
PM can invite one or many technicians; each gets a signed/expiring/single-purpose link via the
US-01.4 service; email is queued; calendar save (Google URL + .ics) correct in store timezone;
send rate-limited; unique (job, technician) invite enforced in DB; each invite created in
**invited** state and audited; tests cover multi-recipient send, cross-tenant job denial and the
duplicate-invite guard; Pint + Larastan clean.

---

## US-09.3 — Technician accepts or declines via the signed link

**As** Michael (guest technician)
**I want** to open my invitation link and accept or decline the job, with no login
**So that** ONYX knows whether I'm attending and the job's multi-technician status stays accurate
(§5.4, US-01.4).

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-09.2 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a valid signed link, **when** Michael (guest, no login) opens it, **then** he sees a
  **mobile-first** invitation screen for **only** his job — store, scheduled date/time in the
  store timezone, and **Accept** / **Decline** actions.
- **Given** Michael, **when** he taps **Accept**, **then** his **per-technician** invite moves
  `invited → accepted`; **when** he taps **Decline**, **then** it moves `invited → declined` — each
  technician's lifecycle is independent of the others on the same job (§5.4).
- **Given** the multi-technician job logic (§5.4), **when** a per-technician state changes, **then**
  the job-level rollup reflects it (e.g. the job is ready to progress once acceptances are in) —
  this story owns the **invite** transitions, the in-progress/completed rollup belongs to
  EPIC-10.
- **Given** Sneider (account holder), **when** he opens his link, **then** he gets the same
  accept/decline flow; if logged in, his acceptance is also associated with his `technician`
  account for history (Sneider) — but **login is never required** to accept.
- **Given** a tampered or expired link, **when** opened, **then** access is denied with the plain,
  friendly page from US-01.4 (resend path in US-09.4) — never a stack trace.
- **Given** an accept/decline POST, **when** submitted, **then** it is **server-validated against
  the signed scope** every request, **rate-limited** (§14.3), CSRF-handled per the ADR-001
  contract, and the transition is **audited** (US-00.5).
- **Given** an already-actioned invite, **when** the same link is reopened, **then** the current
  state is shown (idempotent) rather than allowing a conflicting re-transition.

### Engineering Bar checklist
- **Secure:** the **hardest case** — an unauthenticated actor; scope is enforced from the signed
  link, never from request input, so Michael can only ever touch his own invite for his own job;
  POSTs validated + rate-limited + CSRF-handled (ADR-001); invite state machine enforces only legal
  transitions at the model **and** a DB check/constraint so an out-of-order POST can't corrupt
  state; no other technician's or tenant's data is reachable.
- **Clean:** reuse the US-01.4 signed-guest middleware and the EPIC-08 job read; one invite
  state-machine reused by US-09.2/09.4 and EPIC-10's rollup; thin signed POST endpoints per
  ADR-001, not Livewire actions.
- **UX:** mobile-first from 320px; two clear, high-contrast ≥44px actions (Accept / Decline);
  obvious single primary action; plain non-blaming copy; designed loading/error states; en-AU.
- **No guessing:** confirm the per-technician pivot/columns and the legal transition set with
  tinker before writing the state machine; reuse the US-01.4 expired/invalid page, don't re-create
  it.

### Definition of Done
Guest can accept/decline on a mobile-first screen with no login; per-technician
`invited→accepted`/`invited→declined` transitions enforced at model + DB; independent lifecycles
feed the §5.4 rollup; expired/tampered links hit the US-01.4 friendly page; POSTs scoped,
rate-limited, CSRF-handled and audited; idempotent on re-open; tests prove cross-invite/cross-tenant
denial and illegal-transition rejection; Pint + Larastan clean.

---

## US-09.4 — Link expiry handling and resend

**As** Michael (guest technician) and Yeis (PM)
**I want** an expired link to fail gracefully and the PM to be able to send me a fresh one
**So that** a lapsed or lost invitation never blocks the visit (§12.2, US-01.4).

**Estimate:** 3 · **Priority:** P1 · **Depends on:** US-09.2, US-09.3 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** an invitation approaching expiry, **when** the configured **link-expiry warning**
  threshold is reached, **then** the technician is notified (§12.2) via the queued email channel,
  prompting them to act before the link lapses.
- **Given** an **expired** link, **when** Michael opens it, **then** he sees the plain, friendly,
  mobile-first **expired-link page from US-01.4** with a clear next step (e.g. "ask ONYX to resend")
  — no stack trace, no leak of job data.
- **Given** an invitation, **when** Yeis chooses **Resend**, **then** a **fresh signed, expiring,
  single-purpose link** is generated via the US-01.4 service and **re-emailed (queued)** to that
  one technician.
- **Given** a resend, **when** it completes, **then** the **previous link is invalidated** so only
  the latest link works (single live link per (technician, job)), and the technician's invite
  lifecycle state is **preserved** (an accepted technician stays accepted; a pending one stays
  invited).
- **Given** the resend action, **when** triggered repeatedly, **then** it is **rate-limited**
  (§14.3) like the original send.
- **Given** a warning sent or a link resent, **when** it completes, **then** the event is **audited**
  (US-00.5).

### Engineering Bar checklist
- **Secure:** resend authorised against the PM's role **and** the job's `client_id` scope; the new
  link comes **only** from the US-01.4 signed-link service; the old link is invalidated server-side
  (e.g. token version / single-live-link) so an old link can't be replayed; resend rate-limited
  (§14.3); the expired-link page reveals **no** job/tenant data to an unauthenticated viewer.
- **Clean:** reuse the US-01.4 expired-link page and signed-link service and the §12.2 queued
  notification pattern; the expiry-warning is a scheduled job reusing the EPIC-00 scheduler — no
  bespoke cron; one resend path also used by US-09.2's "already invited" branch.
- **UX:** expired page is plain-language, non-blaming, mobile-first with an obvious next step;
  PM resend is a single clear action with confirmation feedback; warning email copy is actionable;
  en-AU.
- **No guessing:** confirm the link-invalidation mechanism and warning threshold against §14.3 +
  US-01.4 before building; confirm the scheduler hook from EPIC-00 rather than assuming a cron.

### Definition of Done
Expiry-warning notification fires at the configured threshold (queued); expired links show the
US-01.4 friendly page with no data leak; PM resend issues a fresh scoped link, invalidates the old
one and preserves lifecycle state; resend rate-limited; warning + resend audited; tests cover
old-link invalidation, cross-tenant resend denial and the expired-page no-leak; Pint + Larastan
clean.
