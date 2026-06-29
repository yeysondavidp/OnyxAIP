# EPIC-10 — Technician Mobile Workflow (5 screens)

> The product's beating heart (SRA §6). Michael (guest, 4G, signal-poor back-of-house, no login)
> does the visit, proves it with photos + GPS, records per-asset outcomes, and leaves — with
> **no surprise data loss**. Per **ADR-001** this flow is **Alpine-first**: camera, GPS, photo
> preview/removal, wizard navigation and client-side validation run **locally with no server
> round-trip**; the server is contacted **only at business checkpoints** (Start Job, photo submit,
> Complete Job) through a small set of **signed POST routes** (US-01.4). Keep it extractable to a
> future PWA. Even guest checkpoints authorise, validate every input and rely on DB constraints —
> never trust the client.

Related: ADR-001 (Alpine-first technician contract), US-01.4 (signed-URL guest access), EPIC-08
(jobs + status machine), EPIC-09 (invitations + calendar/ICS), EPIC-06 (asset status transitions),
EPIC-11 (PM validation, downstream), US-00.5 (async audit), US-00.3 (mobile shell baseline).
Sprint: 7.

---

## US-10.1 — Screen 1: Job Overview (pre-start)

**As** Michael (guest technician)
**I want** to open my signed job link and see exactly what, where and when before I start
**So that** I arrive prepared and only start the job once I'm actually on site.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-01.4, EPIC-08, EPIC-09 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a valid signed job link, **when** Michael opens it on a 320px phone, **then** he sees
  JobName, JobDescription, store name + full address, and the scheduled date/time **rendered in the
  job's IANA timezone** (UTC stored, displayed per job tz — SRA §6, §2.3).
- **Given** the job has affected assets, **when** Screen 1 loads, **then** each asset is listed with
  **AssetCode, AssetType, current status and location notes**, each row a **≥44px tappable** target
  that opens the asset detail (Screen 3 reference / asset detail).
- **Given** the page, **when** rendered, **then** the **Start Job** CTA is **full-width and
  high-contrast** as the single obvious primary action, with the callout: *"Start Job only when you
  are on site. Your GPS location will be recorded."*
- **Given** the scheduled visit, **when** Michael wants it in his calendar, **then** **Save to
  Google Calendar (URL)** and **Download .ics** options are offered with correct tz-aware times.
- **Given** an **early-start window** configured on the job (Anytime / 30 min / 1 hr / 2 hr / 4 hr),
  **when** the current time is **outside** the window, **then** the Start Job button is **disabled**
  with a plain-language message stating when he may start (client-side gate; the Start checkpoint
  re-checks server-side in US-10.2).
- **Given** an expired/tampered/cancelled job link, **when** opened, **then** the friendly
  denial/closed-job page from US-01.4 / EPIC-08 shows instead of the overview.

### Engineering Bar checklist
- **Secure:** access only via the signed URL validated **server-side every request** (US-01.4);
  the page exposes **only this job's** permitted data (no other job/asset/tenant); GPS not yet
  touched; no asset id or status trusted from the client. Early-start gate is UI convenience — the
  authoritative check lives at the Start checkpoint.
- **Clean:** Alpine-first island per ADR-001 in the technician namespace; reuse the mobile shell
  (US-00.3) and the signed-link service (US-01.4); no PM Livewire components leak in. Tz display via
  the documented UTC→tz helper (US-00.1).
- **UX:** clear hierarchy, single full-width high-contrast CTA, ≥44px targets, designed
  loading/empty (no assets) states, Australian English, plain non-blaming early-start message.
- **No guessing:** confirm early-start window enum + job/store timezone field names against EPIC-08
  before wiring; verify the .ics/Google URL produce the same instant as the displayed local time.

### Definition of Done
Screen 1 renders tz-correct details + tappable asset list from a signed link; full-width Start CTA
with on-site/GPS callout; Google + .ics calendar saves correct; early-start disabling works client
-side and is re-enforced server-side at Start; expired/cancelled links route to the friendly page;
mobile-first 320px + ≥44px verified; happy-path feature test.

---

## US-10.2 — Screen 2: Before-photos + GPS capture

**As** Michael (guest technician)
**I want** to capture before-photos and have my location recorded when I start
**So that** there's proof of the asset's condition and that I was on site — without laggy forms.

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-10.1, US-01.4, EPIC-08 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** Screen 1, **when** Michael taps **Start Job**, **then** the **Start checkpoint** fires a
  single signed POST that records **StartTimestampUTC server-side**, captures **start GPS**, and
  flips job/technician status to **In Progress / Started** (EPIC-08), then Screen 2 opens.
- **Given** Screen 2, **when** he adds photos, **then** capture uses the **native camera**
  (`input[type=file][capture=environment]`) entirely **client-side** (Alpine) with **thumbnail
  previews** and **individual photo removal** — **no server round-trip per photo action**.
- **Given** fewer than 1 photo, **when** he tries to continue, **then** he is blocked client-side
  with *"Add at least one before-photo to continue"* (min 1 required).
- **Given** the **Geolocation API**, **when** start GPS is requested, **then** success attaches
  lat/long; **failure is non-blocking and logged** (SRA §14.6) — if **denied**, a warning shows and
  Michael **confirms to proceed** without GPS.
- **Given** captured photos, **when** he submits before-photos, **then** they upload via the signed
  **photo-submit** endpoint (US-10.6 handles resilience); files pass **MIME + extension allow-list**
  and are stored via the **Storage abstraction**, never publicly served (§14.3).
- **Given** Screen 2, **when** he taps **Cancel**, **then** locally-held photos are **discarded**,
  he returns to Screen 1, and job status **reverts to Accepted** (server checkpoint).

### Engineering Bar checklist
- **Secure (defence in depth):** the Start and photo-submit endpoints are **signed + rate-limited**
  (US-01.4/US-01.5) and **authorise** the actor against this job; **StartTimestampUTC + start GPS
  are written server-side**, never accepted from the client; uploads validated by MIME **and**
  extension against the allow-list with size limits even though the client pre-checks; GPS readable
  only by the assigned technician + PM (§14.3). DB enforces NN/FK on the job-technician + photo rows.
- **Clean:** camera/GPS/preview/removal are **client-side Alpine, never Livewire actions** (ADR-001
  guardrail); reuse the signed-link + upload services; thin checkpoint contract.
- **UX:** big high-contrast capture + continue actions, ≥44px, thumbnail grid with clear remove
  affordance, designed uploading/progress + error states, plain non-blaming GPS-denied copy,
  Australian English.
- **No guessing:** confirm the In Progress/Accepted transition names and the Start checkpoint
  payload shape against EPIC-08 before building; verify the upload field/route contract from
  US-10.6.

### Definition of Done
Start checkpoint writes StartTimestampUTC + start GPS server-side and flips status; native camera
capture + thumbnail preview + individual removal all client-side; min-1-photo gate; GPS failure
non-blocking + logged, denial confirm-to-proceed; before-photos upload through the signed allow-
listed endpoint; Cancel discards photos and reverts to Accepted; audit entries for start + uploads
(US-00.5); 320px + ≥44px verified; happy-path feature test of the Start checkpoint.

---

## US-10.3 — Screen 3: Briefing & asset reference

**As** Michael (guest technician)
**I want** the full brief and a reference panel for each asset, with the ability to update status
**So that** I know exactly what to do and can record what I find as I inspect.

**Estimate:** 5 · **Priority:** P0 · **Depends on:** US-10.2, EPIC-06, EPIC-08 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** Screen 3, **when** it loads, **then** it shows the **full JobDescription**, store
  address, and **all PM attachments read-only** (briefs/diagrams/photos), served only via signed
  URLs (§14.3).
- **Given** the affected assets, **when** Michael opens the **asset panel**, **then** each asset is
  an **expandable** row showing **AssetCode, model, serial number, current status, location notes
  and its QR code**.
- **Given** an asset he inspects, **when** he updates its status (e.g. mark **Faulty** during
  inspection), **then** the change goes through an **EPIC-06 status transition via a signed
  checkpoint endpoint** — validated against the allowed lifecycle, not a free-text write.
- **Given** an invalid transition, **when** attempted, **then** it is **rejected** with a plain
  message and the displayed status is unchanged.
- **Given** Screen 3, **when** scrolled, **then** a **sticky bottom bar** presents **Complete Job**
  as the only forward action (full-width, high-contrast).

### Engineering Bar checklist
- **Secure:** PM attachments and QR assets reachable **only via signed URLs**; the status-update
  endpoint is **signed, rate-limited and authorises** that this technician is assigned to a job
  covering that asset; transition validity enforced **server-side via EPIC-06** (never trust a
  client-supplied target status); DB FK/enum constraints hold. Status changes are **audited**
  (US-00.5).
- **Clean:** reuse the EPIC-06 transition service rather than re-implementing lifecycle rules;
  Alpine for expand/collapse + optimistic UI, server checkpoint for the actual write (ADR-001).
- **UX:** clear expandable rows, ≥44px controls, sticky single primary CTA, designed empty (no
  attachments) + error (rejected transition) states, plain non-blaming copy, Australian English.
- **No guessing:** confirm the EPIC-06 allowed-transitions API and asset/job linkage columns before
  wiring the status update; verify attachment storage paths are signed-only.

### Definition of Done
Full brief + read-only signed attachments render; expandable per-asset panel with QR; in-flow
status update routes through the EPIC-06 transition checkpoint with server-side validity + audit;
invalid transitions rejected cleanly; sticky Complete Job CTA; 320px + ≥44px verified; happy-path
feature test for an in-flow status change.

---

## US-10.4 — Screen 4: After-photos, asset outcomes + completion

**As** Michael (guest technician)
**I want** to capture after-photos, record each asset's outcome and submit the job
**So that** the visit is fully documented and handed to the PM for validation.

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-10.3, EPIC-06, EPIC-08, EPIC-11 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** Screen 4, **when** Michael captures after-photos, **then** the **native camera** flow
  (client-side Alpine, thumbnail preview + individual removal) works as on Screen 2, with **min 1
  after-photo required**.
- **Given** each affected asset, **when** he records the outcome, **then** he selects a **post-
  service status** from **Active / Still Faulty / Decommissioned / Replaced** and may add an
  **optional per-asset note (max 500 chars)**.
- **Given** general notes, **when** he completes, **then** an **optional Completion Notes** field
  (max 1000 chars) is available.
- **Given** the **Submit Job** button, **when** there is no after-photo, **then** it is **disabled**
  until **≥1 after-photo** is added.
- **Given** Submit, **when** tapped, **then** the **Complete checkpoint** fires a single signed POST
  that records **EndTimestampUTC + end GPS server-side**, persists **per-asset outcomes + notes +
  completion notes**, applies the resulting EPIC-06 status changes, and moves the job toward
  **Completed** (EPIC-08) for PM validation (EPIC-11).
- **Given** Screen 4, **when** he taps **Cancel**, **then** after-photos and notes are **discarded**
  locally and he returns to Screen 3 (no completion written).
- **Given** end GPS, **when** unavailable, **then** failure is **non-blocking and logged** (§14.6),
  mirroring Screen 2.

### Engineering Bar checklist
- **Secure (defence in depth):** the Complete endpoint is **signed, rate-limited, authorises** the
  technician for this job; **EndTimestampUTC + end GPS written server-side**, never from the client;
  per-asset outcome status validated against the **EPIC-06 lifecycle**; note lengths validated
  **server-side** (500 / 1000) in addition to client maxlength; after-photos pass MIME + extension
  allow-list; GPS access restricted to assigned technician + PM. DB enforces FK/enum/NN; the whole
  completion writes in a **transaction** so partial completions can't persist.
- **Clean:** camera/GPS/preview stay **client-side Alpine** (ADR-001); single Complete checkpoint
  writes everything; reuse EPIC-06 transition + Storage services; bulk-insert outcomes via
  `DB::table()->insert()` in the transaction on this hot path.
- **UX:** clear per-asset outcome selectors, live char counters, ≥44px targets, disabled-until-photo
  Submit with explanation, designed uploading/error states, plain non-blaming GPS copy, Australian
  English.
- **No guessing:** confirm the outcome→EPIC-06 status mapping (Still Faulty/Replaced) and the
  Completed transition name against EPIC-06/08 before building; verify EPIC-11 expects this payload.

### Definition of Done
After-photo capture (client-side, min 1) + per-asset outcome select with optional 500-char notes +
optional 1000-char completion notes; Submit disabled until ≥1 after-photo; Complete checkpoint
writes EndTimestampUTC + end GPS + outcomes + notes server-side in a transaction and advances to
Completed; Cancel discards locally; end-GPS failure non-blocking + logged; all writes audited
(US-00.5); 320px + ≥44px verified; happy-path feature test of the Complete checkpoint.

---

## US-10.5 — Screen 5: Job summary (post-completion)

**As** Michael (guest technician) — and Sneider (account technician)
**I want** a read-only summary of the completed visit
**So that** I can confirm what was recorded, and (if I have an account) jump to my history.

**Estimate:** 3 · **Priority:** P1 · **Depends on:** US-10.4 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a completed job, **when** Screen 5 loads, **then** it shows a **read-only summary**:
  JobReference, store, **scheduled vs actual** times, **duration**, before/after photos, completion
  notes, and **each affected asset's new status** post-service — times displayed in the job tz.
- **Given** an **account holder** (Sneider), **when** viewing the summary, **then** a link to the
  **job-history dashboard** is shown.
- **Given** a **guest** (Michael), **when** viewing the summary, **then** a **prompt to create an
  account** is shown instead of the history link.
- **Given** the photos/attachments, **when** displayed, **then** they are served **only via signed
  URLs** with expiry (§14.3).
- **Given** Rosie (future client_user), **when** v1 ships, **then** this screen exposes **no** client
  -portal surface (inert per US-01.2).

### Engineering Bar checklist
- **Secure:** summary reachable only via the signed link / authorised session; **read-only** (no
  mutation surface); GPS-derived data and photos restricted to the assigned technician + PM (§14.3);
  no other job/tenant data exposed.
- **Clean:** reuse summary/photo partials + the tz-display helper; account-vs-guest branch is a
  single role check (US-01.2), not duplicated views.
- **UX:** scannable read-only layout, clear duration/times, account prompt vs create-account prompt
  obvious, ≥44px, designed empty (e.g. no end GPS) state, Australian English.
- **No guessing:** confirm the job-history dashboard route (Sneider) exists or is stubbed before
  linking; verify actual-time/duration come from the server-recorded Start/End timestamps.

### Definition of Done
Read-only summary with scheduled vs actual times, duration, before/after photos, completion notes
and per-asset new status; account holder sees history link, guest sees create-account prompt;
signed-URL photo serving; no v1 client-portal surface; tz-correct; 320px verified; happy-path
feature test.

---

## US-10.6 — Resilient photo upload (poor in-store signal)

**As** Michael (guest technician on flaky 4G back-of-house)
**I want** photo uploads to survive a dropped connection without me redoing the visit
**So that** a signal hiccup never costs me my photos or my progress.

**Estimate:** 8 · **Priority:** P0 · **Depends on:** US-10.2, US-10.4, US-01.4 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a photo upload fails mid-flow, **when** the connection recovers, **then** it **retries
  automatically without restarting the workflow** and without losing already-captured photos or
  entered notes (SRA §14.6).
- **Given** uploads in progress, **when** they run, **then** progress is **non-blocking** — Michael
  can keep capturing/reviewing while photos upload, with **per-photo progress feedback** (§14.1).
- **Given** a connection hiccup, **when** it occurs, **then** **no data is lost**: captured photos
  and form state are held client-side (Alpine) and reconciled with the server on recovery — the
  flow stays usable, never wiping the screen.
- **Given** the **same photo** is retried/re-sent, **when** the server receives it, **then**
  handling is **idempotent** (no duplicate stored files for the same client upload id).
- **Given** **multiple technicians** uploading to the **same job concurrently**, **when** their
  uploads land, **then** there are **no race conditions** — each technician's photos attach to the
  correct (technician, job) record without clobbering the other (§14.6).
- **Given** repeated upload attempts, **when** they hit the endpoint, **then** the **photo-upload
  rate limiter** (US-01.5) still applies and retries back off gracefully rather than hammering.

### Engineering Bar checklist
- **Secure:** the upload endpoint stays **signed, rate-limited and authorising** under retry; every
  retried file re-validated (MIME + extension allow-list, size) server-side — retries are **not** a
  bypass; idempotency key bound to the (technician, job) scope so it can't attach to another's job;
  DB unique constraint on (job, technician, client_upload_id) backs the app-level idempotency.
- **Clean:** hand-written Alpine/JS upload + retry queue per ADR-001 (the exact logic that must not
  round-trip via Livewire); one reusable uploader used by Screens 2 and 4; thin signed endpoint.
  This is the **offline-resilience seam ADR-001's architecture exists to support** — keep it
  extractable to a future PWA service worker.
- **UX:** clear per-photo states (queued / uploading / done / retrying / failed-with-retry), plain
  non-blaming "we'll retry automatically" messaging, no full-screen blocking spinner, ≥44px manual
  retry control as a fallback, Australian English.
- **No guessing:** confirm the upload contract + idempotency-key shape shared with US-10.2/US-10.4;
  verify the concurrency model (separate per-technician rows) against EPIC-08's job-technician pivot
  before relying on it.

### Definition of Done
Auto-retry on recovery without restarting the flow or losing photos/notes; non-blocking per-photo
progress; idempotent re-send (DB unique constraint + app key); concurrent multi-technician uploads
proven race-free against the correct (technician, job) rows; rate limiter + backoff intact;
client-side queue kept PWA-extractable per ADR-001; tests cover retry, idempotency and concurrent
upload paths.
