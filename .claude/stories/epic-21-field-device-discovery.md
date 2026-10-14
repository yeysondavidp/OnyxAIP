# EPIC-21 — Field Device Discovery (AI-assisted, two-step verification)

> When ONYX sends a technician to a store it has never serviced, the register has nothing for that
> store. Today the technician photographs labels and someone re-types serials, MACs and models
> later (exactly what happened for Pandora Miranda, 12 Oct 2026). This epic lets the technician
> register the devices they find **on site**, with the label photo pre-filling the fields, the
> technician confirming them, and the PM doing a second check before anything counts as part of the
> register.
>
> Flow: **photo of the label → AI pre-fills → technician confirms or corrects → PM verifies →
> asset is live in the register.** Nothing the AI reads is ever saved without a human confirming it.

Related: EPIC-04 (asset registry and form rules), EPIC-10 (technician flow, signed links),
ADR-003 (provider-agnostic AI gateway — first provider **Claude Haiku 5.5**, `claude-haiku-5-5`,
vision-capable, low cost), US-18.5 (fabrication guardrail), US-19.6 (photo captions and asset
tagging), EPIC-20 (the same verification queue is reusable for agent-proposed records).
Sprint: proposed for 16 (see `sprints/sprint-plan.md`).

---

## US-21.1 — Technician registers a device found on site (pending verification)

**As** a technician on a first visit to a store
**I want** to add a device I am looking at that is not in the job's list
**So that** it is recorded while I am in front of it, instead of someone re-typing it from photos
later.

**Estimate:** 8 · **Priority:** P1 · **Depends on:** US-04.1, US-10.3 · **Status:** ✅ Done

### Acceptance criteria
- **Given** the technician brief (Screen 3), **when** they tap "Add a device I found", **then** a
  short mobile form opens: device type, name/location in store, manufacturer, model, serial, MAC
  (players), screen size and orientation (screens), at least one photo — all optional except type
  and one photo, because a technician may not be able to read everything.
- **Given** the form is submitted, **then** an asset is created at the job's store with status
  **`pending_verification`** (D7) and linked to the job, recording who found it, when and from which
  job; it is **not** counted in dashboards, reports or SLA until a PM verifies it (US-21.3).
- **Given** a device with the same serial or MAC already exists anywhere in the client's register,
  **then** the form warns "This device may already be registered as <code> at <store>" and lets the
  technician link it instead of creating a duplicate.
- **Given** a guest technician with a signed link, **then** they can only add devices to **that
  job's store**; the link's scope and expiry apply as for every other technician action.

### Engineering Bar checklist
- **Secure:** signed-URL middleware + job/technician match as in `JobFlowController`; store and
  client come from the job, never from input; request validated server-side; photos through the
  existing upload path (MIME allow-list, size limit); rate-limited (`photo.upload` / a new
  `device.discovery` limiter); DB: new status value accepted by the enum **and** the column
  constraint; `found_by_technician_profile_id` / `found_on_job_id` FKs.
- **Clean:** reuse `Asset::createWithDetail` with relaxed, explicitly listed rules for discovery
  (a separate FormRequest, not `CreateAssetRequest` with flags); asset code generated server-side.
- **UX:** one screen, big tap targets, camera first, plain words ("What is it?", "Where is it?"),
  works one-handed; no native dialogs.
- **No guessing:** confirm the asset-code convention (`PAN-PLY-0001`…) with the PM before
  generating codes.

### Definition of Done
Technician adds a device on site, it lands as pending and linked to the job, duplicates are
flagged, scope is enforced; feature tests incl. cross-store and expired-link denial; Pint +
Larastan + Pest green.

---

## US-21.2 — Read the label photo and pre-fill the device (AI, technician confirms)

**As** a technician
**I want** to photograph a device's label and have the fields filled in for me
**So that** I only check and correct, instead of typing long serials and MACs on a phone.

**Estimate:** 8 · **Priority:** P1 · **Depends on:** US-21.1, US-18.3 (AI gateway) · **Status:** 🚧
Code done; switch on for technicians only after `ai:label-accuracy` on the Miranda label crops

### Acceptance criteria
- **Given** a label photo in the US-21.1 form, **when** the technician taps "Read label", **then**
  they frame **just the label** (crop step), and only that crop — re-encoded server-side with all
  metadata removed and downscaled (ADR-003 §6) — is sent through the ADR-003 gateway (first
  provider Claude Haiku 5.5, vision); the full photo stays in ONYX. The form is pre-filled with manufacturer, model, serial, MAC and any other printed identifiers, each
  marked **"Read from photo — please check"**.
- **Given** the model is unsure or a field is not visible, **then** that field stays empty — the
  prompt and output schema allow "not visible", and nothing is guessed (US-18.5 guardrail: every
  value must appear in the photo's text).
- **Given** the technician edits or confirms the fields and saves, **then** the asset stores which
  fields came from the AI and whether the technician changed them (shown to the PM in US-21.3).
- **Given** the AI call fails, times out (> 10 s) or declines, **then** the form stays usable for
  manual entry with a plain message; the visit never blocks on the AI.
- **Given** the job's client has AI set to *off*, the device is a router/modem/network device
  (its label prints passwords), or the crop shows a person, **then** "Read label" is not offered
  (or the crop is not sent) and the technician types the fields.
- **Given** a call is made, **then** the audit records `label_crop` as the data category, the
  provider and model — never the image, the prompt or the response.

### Engineering Bar checklist
- **Secure:** only the sanitised label crop goes to the provider, only from the server, never from
  the browser; no API key in the client; per-job and per-link rate limit; calls audited (US-18.8);
  per-client AI setting enforced in the request **and** in the sanitiser (ADR-003 §6).
- **Privacy:** a test proves the outbound image has no EXIF/XMP/IPTC (including GPS) and is a
  crop, not the original; no names, emails, phones, addresses or tokens in the outbound text.
- **Clean:** one `LabelReader` behind the ADR-003 contract with a structured output schema;
  provider/model in config only.
- **UX:** pre-filled fields visibly different until confirmed; one tap to accept all.
- **No guessing:** measure accuracy on the 7 Miranda label photos (serials and MACs known) before
  enabling it for technicians.

### Definition of Done
Label photo pre-fills the form behind the gateway, uncertain fields stay empty, provenance stored,
graceful fallback; unit tests with a fake provider; accuracy check on real labels recorded; Pint +
Larastan + Pest green.

---

## US-21.3 — PM verification queue for devices found in the field

**As** Yeis (PM)
**I want** one list of devices technicians (or agents) added, with the photo next to the fields
**So that** I verify each one once before it becomes part of the register.

**Estimate:** 5 · **Priority:** P1 · **Depends on:** US-21.1 · **Status:** ✅ Done

### Acceptance criteria
- **Given** pending devices exist, **then** the PM dashboard shows a "Devices to verify" count and a
  queue listing each with its photos, store, job, who found it, and which fields came from the AI
  and whether the technician changed them.
- **Given** a pending device, **when** the PM corrects fields and taps "Verify", **then** it is
  validated with the full asset form rules and moves to the status the PM chooses (normally
  `active`); **"Reject"** (with a reason) removes it from the register; **"Merge into existing"**
  links the photos to an existing asset instead.
- **Given** any of these actions, **then** it is audited with the PM as actor and the original
  values kept in the audit `before`.

### Engineering Bar checklist
- **Secure:** PM-only (policy), client scope; verification goes through `UpdateAssetRequest` rules
  and `AssetTransitionService` (pending → chosen status is a permitted transition added explicitly).
- **Clean:** pending devices are ordinary assets with a status, not a parallel table.
- **UX:** photo and fields side by side; keyboard-friendly; empty state "Nothing to verify".
- **No guessing:** confirm how pending assets must be excluded from each report/dashboard query.

### Definition of Done
Queue with verify / reject / merge, audited, pending assets excluded everywhere until verified;
feature tests; Pint + Larastan + Pest green.
