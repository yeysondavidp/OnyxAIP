# EPIC-13 — Notifications

> Keeps Yeis informed of everything that needs her attention (job completions, faults, SLA risks,
> warranty expirations) and keeps technicians on track (invitations, reminders, link-expiry warnings)
> — all via queued email and in-app alerts, scoped strictly to `client_id`.

Related: EPIC-08 (jobs), EPIC-09 (technician invites), EPIC-12 (SLA), EPIC-06 (asset status),
EPIC-16 (settings/thresholds), US-00.5 (audit foundation), ADR-002 (queue/scheduler containers).
Sprint: 10.

---

## US-13.1 — PM notifications for job status changes, asset status changes, and new fault reported

**As** Yeis (PM)
**I want** in-app and queued email notifications when a job changes status to Completed or Requires
Remediation, when an asset's status changes (manual or system-triggered), and when a technician
reports a new fault against an asset
**So that** I can respond to field outcomes immediately without polling the job board.

**Estimate:** 8 · **Priority:** P1 · **Depends on:** US-00.5, EPIC-06, EPIC-08, EPIC-16 ·
**Status:** 📋 Ready

### Acceptance criteria

- **Given** a job transitions to **Completed** or **Requires Remediation**, **when** the transition
  is persisted, **then** a `PM_JOB_STATUS_CHANGED` notification is dispatched to the queue
  targeting every PM whose `client_id` matches the job's `client_id` — no other tenant's PMs
  receive it.
- **Given** an asset's `AssetStatus` changes (any transition from §4.5, manual or system), **when**
  the change is persisted, **then** a `PM_ASSET_STATUS_CHANGED` notification is dispatched to the
  queue, scoped to the asset's `client_id`.
- **Given** a technician marks an asset as **Faulty** during Screen 3 or records a **Still Faulty**
  outcome in Screen 4, **when** the report is persisted, **then** a `PM_NEW_FAULT_REPORTED`
  notification is dispatched, scoped to the affected asset's `client_id`.
- **Given** a dispatched notification, **when** the queue worker processes it, **then** (a) an
  in-app notification record is written, readable from the PM notification bell/drawer, and (b) a
  plain-text HTML email is sent via Laravel Mail using a Blade template.
- **Given** PM notification preferences (configured in EPIC-16), **when** a PM has disabled a
  notification type, **then** neither the in-app record nor the email is sent for that type.
- **Given** an email delivery failure, **when** the Mailer throws or the job exhausts retries,
  **then** the failure is logged with: notification type, target PM user ID, job/asset reference,
  timestamp, and the exception message — sufficient context for PM to manually resend (§14.6).
- **Given** the in-app notification drawer, **when** a PM views it, **then** each item shows:
  notification type label, affected entity name (job reference or asset code), store name, time
  elapsed, and a direct link to the entity — unread items are visually distinct.
- **Given** a notification, **when** its email body is rendered, **then** it contains: plain-
  language Australian English subject and body, the affected entity name and direct URL, and the
  ONYX logo — no technical jargon, no blaming language.

### Engineering Bar checklist

- **Secure:** notification dispatch is scoped to `client_id` at query time before enqueuing —
  a PM notification must never include data from another tenant; the notification model carries
  `client_id` (NOT NULL, FK, indexed) and the policy denies reads to mismatched actors; email
  addresses are taken from the authenticated user record, never from request input.
- **Clean:** a single reusable `NotificationDispatcher` service dispatches all PM notification
  types; notification types are a typed PHP enum (no magic strings); Blade email templates are
  shared partials with variable injection; no dead notification listeners; delivery-failure
  logging reuses the existing log channel (§14.6) — not a new mechanism.
- **UX:** in-app bell shows an unread count badge; drawer has a clear "mark all as read" action
  with a 44px tap target; empty state is plain-language ("You're all caught up"); email subject
  lines are short and descriptive; all notifications written in Australian English.
- **No guessing:** read the `jobs` and `assets` migrations before writing the notification queries
  to confirm column names (`client_id`, `job_status`, `asset_status`); confirm the queue driver
  and Redis eviction caveat (ADR-002) before relying on queued dispatch.

### Definition of Done

All three PM notification types (job status change, asset status change, new fault) dispatched
asynchronously via queue; in-app notification records created and rendered in the drawer;
emails rendered from Blade templates in Australian English; delivery failures logged with full
context; PM preference suppression respected; `client_id` scope tested — cross-tenant emission
impossible by test; happy-path integration test per notification type.

---

## US-13.2 — SLA breach warning and SLA breach event notifications to PM

**As** Yeis (PM)
**I want** an in-app and email notification when a fault-type service job approaches its SLA
window (configurable threshold, e.g. 80% elapsed) and a second notification when the SLA is
actually breached
**So that** I can act before a client's SLA is violated, not after.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-13.1, EPIC-12, EPIC-16 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** a fault-type job with an SLA clock running (EPIC-12), **when** the scheduler runs
  its SLA check, **then** jobs where elapsed business-hours time has reached the configured warning
  threshold (default 80%, configurable in EPIC-16 per client) emit a `PM_SLA_WARNING`
  notification to the queue, scoped to the job's `client_id`.
- **Given** a fault-type job, **when** elapsed business-hours time exceeds the SLA window,
  **then** a `PM_SLA_BREACHED` notification is dispatched, scoped to the job's `client_id`, and
  the job's `SLABreached` flag is set to `true` in the same database transaction.
- **Given** a `PM_SLA_WARNING` or `PM_SLA_BREACHED` notification, **when** it is processed,
  **then** an in-app notification record is created and a Blade email is sent — following the same
  delivery-failure-logging pattern as US-13.1.
- **Given** a job that has already emitted a warning, **when** the scheduler runs again, **then**
  a duplicate `PM_SLA_WARNING` is **not** emitted for the same job in the same window — idempotent
  scheduling.
- **Given** a job that has already emitted a breach notification, **when** the scheduler runs
  again, **then** a duplicate `PM_SLA_BREACHED` is **not** emitted.
- **Given** the in-app notification, **when** Yeis views a `PM_SLA_WARNING` or `PM_SLA_BREACHED`
  item, **then** it shows: job reference, store name, client name, time elapsed vs SLA target,
  percentage elapsed, and a direct link to the job detail — severity is visually distinguished
  (warning vs breach).
- **Given** PM notification preferences (EPIC-16), **when** a PM has disabled SLA warnings or
  breach notifications, **then** neither in-app nor email is sent for that type.

### Engineering Bar checklist

- **Secure:** the scheduler query that identifies at-risk jobs is constrained by `client_id` at
  the DB layer — no full-table scan across tenants; the `SLABreached` flag update and the
  notification dispatch happen in a DB transaction so the flag is never set without a notification
  being queued (and vice versa rolled back); no SLA data from one tenant is visible to another.
- **Clean:** SLA check logic lives in a dedicated `SLAMonitorJob` scheduled command, not inline
  in a controller; idempotency is enforced by a `sla_notifications_sent` bitmask or a pivot table
  record — not by hoping the scheduler runs once; reuses the `NotificationDispatcher` from
  US-13.1; business-hours calculation reuses EPIC-12's `BusinessHoursCalculator`.
- **UX:** warning vs breach items are visually distinct (e.g. amber vs red) in the in-app drawer
  and in email subject lines; percentage elapsed is displayed as plain English ("Your SLA for this
  job is 80% elapsed — 4 hours remaining") — no raw timestamps; Australian English throughout.
- **No guessing:** read the EPIC-12 SLA schema (SLAProfile, SLA clock columns) before writing
  the scheduler query; confirm the scheduler container is running per ADR-002; confirm business-
  hours calculator state-aware public holiday handling covers all eight Australian states before
  relying on it.

### Definition of Done

`SLAMonitorJob` runs on the scheduler; `PM_SLA_WARNING` emits at the configurable threshold;
`PM_SLA_BREACHED` emits on breach and sets `SLABreached`; both are idempotent (tested by running
scheduler twice against the same job); in-app and email rendered; delivery failures logged;
`client_id` scope tested; cross-tenant emission impossible by test.

---

## US-13.3 — Warranty-expiry approaching notifications (scheduler-driven, configurable)

**As** Yeis (PM)
**I want** in-app and email notifications when an asset's `WarrantyExpiry` is approaching, at
configurable intervals (30, 60, and 90 days before expiry)
**So that** I can plan warranty renewals or replacements before assets go out of coverage.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-13.1, EPIC-04 (Asset Registry),
EPIC-16 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** an asset with a non-null `WarrantyExpiry` date and `AssetStatus` not Decommissioned,
  **when** the scheduler's daily warranty check runs, **then** assets whose `WarrantyExpiry` falls
  within a configured threshold window (90, 60, or 30 days from today, configurable in EPIC-16)
  emit a `PM_WARRANTY_EXPIRY_APPROACHING` notification to the queue, scoped to the asset's
  `client_id`.
- **Given** an asset that triggered a 90-day warning, **when** the scheduler runs daily and the
  asset crosses the 60-day boundary, **then** a fresh `PM_WARRANTY_EXPIRY_APPROACHING` (60-day)
  notification is emitted; the 30-day notification fires independently at that boundary — each
  threshold fires once per asset per threshold.
- **Given** an asset that has already fired a notification for a given threshold (e.g. 60 days),
  **when** the scheduler runs again the next day, **then** a duplicate for that threshold is
  **not** emitted — idempotent.
- **Given** an asset with `WarrantyExpiry` null or with `AssetStatus = Decommissioned`, **when**
  the scheduler runs, **then** no warranty notification is emitted for that asset.
- **Given** a `PM_WARRANTY_EXPIRY_APPROACHING` notification, **when** processed, **then** an
  in-app notification record is created and a Blade email is sent — following the delivery-failure-
  logging pattern of US-13.1.
- **Given** the in-app notification, **when** Yeis views it, **then** it shows: asset code, asset
  name, store name, client name, warranty expiry date in Australian date format (DD/MM/YYYY), days
  remaining, and a direct link to the asset detail page.
- **Given** PM notification preferences (EPIC-16), **when** a PM has disabled warranty expiry
  notifications, **then** no in-app record nor email is sent.

### Engineering Bar checklist

- **Secure:** the scheduler query filters by `client_id` at the DB layer and only returns assets
  belonging to clients whose PM is the notification target — no cross-tenant asset data ever flows
  into a notification; `WarrantyExpiry` is read from the DB and not trusted from any request input;
  the idempotency record (per asset per threshold) is stored server-side.
- **Clean:** a `WarrantyExpiryCheckJob` scheduled command owns this logic — it is not bolted onto
  the SLA checker or the asset controller; idempotency tracked via a `warranty_notification_log`
  table (asset_id, threshold_days, notified_at) with a unique index on (asset_id, threshold_days)
  so duplicate rows are rejected at the DB level, not just in application logic; reuses
  `NotificationDispatcher` from US-13.1.
- **UX:** the in-app item and email are written in plain Australian English — "The warranty for
  Samsung QH98C (PAN-SCR-001) at Pandora Pitt St Mall expires in 30 days (15/07/2026). Plan ahead
  to avoid coverage gaps."; no technical field names exposed; date in DD/MM/YYYY format; Australian
  English throughout.
- **No guessing:** read the `assets` migration before writing the scheduler query to confirm
  `warranty_expiry` column name and nullable behaviour; confirm the `scheduler` container is active
  per ADR-002; verify `AssetStatus` enum values against the EPIC-06 migration before filtering.

### Definition of Done

`WarrantyExpiryCheckJob` scheduled daily; fires once per asset per configured threshold; idempotency
enforced at DB level (unique index on `warranty_notification_log`); skips null expiry and
Decommissioned assets; in-app and email rendered in Australian English with DD/MM/YYYY dates;
delivery failures logged; `client_id` scope tested; duplicate-suppression tested by running the job
twice against the same asset.

---

## US-13.4 — Technician notifications: invitation, job reminder, and link-expiry warning

**As** Michael (guest technician) and Sneider (account technician)
**I want** to receive a job invitation email with a secure link, a reminder before the scheduled
visit, and a warning if my access link is about to expire
**So that** I never miss a job or arrive to find I can no longer open the link.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** EPIC-09 (technician invites, signed URL
service US-01.4), EPIC-08, EPIC-16 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** a PM sends a job invitation (EPIC-09), **when** the invitation is persisted, **then**
  a `TECH_JOB_INVITATION` notification is dispatched to the queue and an email is sent to the
  technician's address containing: job reference, job name, store name and address (in the job's
  timezone), scheduled date and time in the store's local timezone, and the signed job URL.
- **Given** a `TECH_JOB_INVITATION` email, **when** the technician opens it on a smartphone,
  **then** the email renders correctly at 320px viewport with a single prominent "Open Job" button
  (≥44px tap target in the email client).
- **Given** a job with a scheduled date and time, **when** the scheduler runs and the scheduled
  time is within the configurable reminder window (default 24 hours before, configurable in
  EPIC-16), **then** a `TECH_JOB_REMINDER` notification is dispatched to the queue for each
  technician assigned to that job whose status is not yet Completed.
- **Given** a `TECH_JOB_REMINDER` email, **when** it is sent, **then** it contains: job reference,
  store name and address, scheduled date/time in the store's local timezone, and the signed job URL
  — the same URL previously issued (it must still be valid at reminder time).
- **Given** a `TECH_JOB_REMINDER`, **when** it would be sent to a technician whose job status is
  already Completed or who has already started (In Progress), **then** the reminder is **not** sent.
- **Given** a job reminder, **when** the scheduler runs a second time for the same job and
  technician within the same reminder window, **then** a duplicate reminder is **not** sent —
  idempotent.
- **Given** a signed job URL approaching its expiry, **when** the scheduler runs and the URL will
  expire within a configurable warning window (configurable in EPIC-16, default 6 hours before
  expiry), and the job is not yet Completed, **then** a `TECH_LINK_EXPIRY_WARNING` notification is
  dispatched containing a **freshly re-issued signed URL** and a plain-language explanation.
- **Given** any technician email delivery failure, **when** the Mailer throws or the job exhausts
  retries, **then** the failure is logged with: notification type, technician user ID or email,
  job reference, timestamp, and exception message — enabling PM to manually resend (§14.6).
- **Given** a `TECH_JOB_INVITATION` or `TECH_JOB_REMINDER`, **when** it is sent to a
  guest technician (no account), **then** the email does not expose any PM-only data (e.g. other
  technicians' details, other jobs, other clients' information).

### Engineering Bar checklist

- **Secure:** signed URLs are generated by the signed-URL service from US-01.4 — never hand-
  constructed; re-issued URLs for `TECH_LINK_EXPIRY_WARNING` are scoped to the same single
  technician + single job as the original; the scheduler query for reminders and link-expiry
  warnings filters only on jobs scoped to their respective technician records — a guest
  technician's email never includes data from another job or another tenant; technician email
  addresses are taken from the `technicians` table, never from request input; rate limiting on
  invitation sending (US-01.5) applies before this queue dispatch.
- **Clean:** three notification types are distinct typed PHP enum values; reminder and link-expiry
  idempotency tracked in a `technician_notification_log` table (job_id, technician_id,
  notification_type, sent_at) with a unique index on (job_id, technician_id, notification_type)
  per window; reminder logic lives in a `TechnicianReminderJob` scheduled command; re-issuance of
  signed URLs reuses the US-01.4 service — no inline URL construction; reuses Blade email
  templates and the delivery-failure logger from US-13.1.
- **UX:** all technician emails are written in plain Australian English, non-technical, non-blaming;
  the "Open Job" CTA is the single prominent action per email; scheduled date/time is always shown
  in the store's local timezone with the timezone name spelled out (e.g. "Wednesday 1 July 2026 at
  9:00 am AEST") — never UTC; the link-expiry email explains simply why the link changed ("Your
  job link was renewed — use this new link to access your job") without technical jargon; email
  layout functional at 320px.
- **No guessing:** read the `job_technician` pivot and `technicians` migrations before writing the
  scheduler query to confirm column names (technician status, assigned_at, etc.); confirm signed
  URL token lifetime against US-01.4 before computing the link-expiry warning window; confirm the
  scheduler container is active per ADR-002.

### Definition of Done

`TECH_JOB_INVITATION` dispatched on PM invite action via queue; `TECH_JOB_REMINDER` dispatched by
scheduler within configured window; `TECH_LINK_EXPIRY_WARNING` dispatched with fresh signed URL;
all three idempotent (tested); reminders suppressed for Completed/In Progress technicians (tested);
no guest-technician email leaks PM-only or cross-tenant data (tested); delivery failures logged;
all email templates render at 320px; scheduled date/time shown in store-local timezone; Australian
English throughout; happy-path integration test for invitation dispatch.
