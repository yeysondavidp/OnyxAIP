# EPIC-18 — Technical Service Report Generation (AI-assisted)

> Turns a validated job's evidence (technician notes, asset outcomes, photos, GPS/timestamps —
> EPIC-10/EPIC-11) into the **Technical Service Report** ONYX already hand-writes per visit: a
> short, professional, client-facing document — kicker, one-line corrective-action title, a
> Site/Asset/Visit-date box, Executive Summary, Findings and Action Taken, Risk & Mitigation, Next
> Steps. Two real examples anchor this epic: `Technical_Service_Report_Pandora_PittStreetMall_
> 30Jun2026 copy.docx` (the target format) and the raw technician notes for Dior, Boutique Pacific
> Fair (15 Jul 2026), both in `.claude/files/`.
>
> AI drafts the prose; the PM always reviews and can edit before anything is finalised. The AI
> layer is provider-agnostic by design (ADR-003) — a paid model (Claude, GPT) and a self-hosted
> model (Ollama running Gemma or similar) are both first-class, swappable by config, never by
> code change.

> **Data rule (D8, 2026-10-14).** Report drafting may use photos of **public store areas only**
> (shopfront, windows, sales floor), each confirmed by the PM before generation — never
> back-of-house, non-public screen content, documents, router labels or people (ADR-003 §6).
> Text uses codes instead of names; no addresses, contacts or secrets. Clients set to *label crops
> only* or *off* get text-only drafting or none.
>
> **Exemplars (updated 2026-10-13).** A third real report — **Pandora, Westfield Miranda,
> 12 Oct 2026** (`.claude/files/reports/Technical_Service_Report_Pandora_Miranda_12Oct2026.pdf`)
> — changes the template assumptions for US-18.2/US-18.4:
> - **Risk & Mitigation is optional** — a content-only fault has none; the renderer must omit the
>   section cleanly rather than print an empty heading, and the prompt must not invent risks.
> - The header box adds a **Status** line (e.g. "Content action required") next to Site / Asset /
>   Visit date — sourced from the US-19.2 outcome, not free-typed by the AI.
> - **Next Steps** are ordered and owned — source them from US-19.3 follow-ups, not from prose.
> - A second page, **Site Photographs**, shows numbered photos with captions and a subtitle
>   (site · date · asset models and serials) — needs US-19.6 captions/asset tags.
> - States facts like "No parts are required at this stage" only when the context says so
>   (fabrication guardrail, US-18.5).

Related: ADR-003 (AI provider abstraction), ADR-002 (deployment — self-hosted model is a separate
service), US-00.4/US-00.5 (scoping/audit), US-01.3 (policies), EPIC-11 (source data), EPIC-14
(reporting infra, signed-URL export pattern), EPIC-16 (settings). Depends on EPIC-08/10/11.
Sprint: 11 (proposed — after Sprint 10's reporting/settings epics).

---

## US-18.1 — Report context assembly from a validated job

**As** the report generation feature
**I want** a single, deterministic service that gathers a validated job's client, store, affected
assets, per-asset outcomes/notes, technicians, timestamps and photo references into one immutable
`ReportContext` object
**So that** every downstream consumer — the AI prompt builder and the docx renderer — reads
**exactly the same facts** and can never drift apart or disagree about what happened on site.

**Estimate:** 5 · **Priority:** P2 · **Depends on:** US-11.1, US-11.3 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a job in `Validated` status, **when** `ReportContext::fromJob($job)` runs, **then** it
  returns client name, store name/address, visit date (in the store's timezone, §2.3), affected
  assets (`AssetCode`, type, model, serial where present), each asset's Screen-4 outcome + note,
  assigned technician(s), and before/after photo references — read-only, no mutation.
- **Given** a job that is **not** `Validated`, **when** context assembly is attempted, **then** it
  is rejected — a report is only built from a closed, trustworthy record (mirrors EPIC-11's
  append-only guarantee), not from a Draft/In-Progress job that could still change.
- **Given** the assembled context, **when** inspected, **then** it is a plain, serialisable value
  object (no Eloquent model leakage) so it can be fed to a prompt builder, a fake in tests, or a
  future non-AI consumer without a DB round-trip.
- **Given** a job affecting multiple assets, **when** context is built, **then** every asset's
  outcome/note is included — none silently dropped — so the AI/manual author never has to go
  back to the source data.

### Engineering Bar checklist
- **Secure:** authorise the source job by role **and** `client_id` scope (US-01.3) before reading;
  photo references are signed-URL identifiers, never raw storage paths (§14.3, EPIC-11); the
  read is scoped exactly like the EPIC-14 report queries (US-14.1 pattern) — no cross-tenant rows.
- **Clean:** one `ReportContext` builder reused by US-18.2 (renderer) and US-18.4 (prompt
  builder) — never two code paths reading the same job differently; reuse the EPIC-11 service
  history / asset relations rather than re-querying ad hoc.
- **UX:** N/A (backend service) — but its rejection message (non-`Validated` job) must be
  plain-language when surfaced in the US-18.6 UI.
- **No guessing:** verify the exact EPIC-11 service-history column names and the Screen-4
  outcome/note field shape (US-10.4/US-11.1) with tinker before mapping them into the DTO — do
  not assume field names.

### Definition of Done
`ReportContext::fromJob()` builds a complete, read-only DTO from a `Validated` job only; rejects
non-Validated jobs; scoped by role + `client_id`; photo refs are signed identifiers; reused
identically by US-18.2 and US-18.4; unit-tested against a fixture job (no live AI call needed);
Pint + Larastan + Pest green.

---

## US-18.2 — Deterministic report document renderer (docx + PDF)

**As** Yeis (PM)
**I want** a renderer that fills a fixed Technical Service Report template from a `ReportContext`
plus a set of final section texts (however authored)
**So that** every report looks like the Pandora exemplar — same layout, brand colours, kicker
labels — regardless of whether the prose came from AI or was typed by hand, and regardless of
which AI provider was used.

**Estimate:** 8 · **Priority:** P2 · **Depends on:** US-18.1 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** a `ReportContext` and final texts for Corrective Action title, Executive Summary,
  Findings and Action Taken, Risk & Mitigation and Next Steps, **when** the renderer runs, **then**
  it produces a **docx** matching the Pandora template's structure: kicker label, title, a
  Site/Asset/Visit-date/Prepared-by box, the section headers in the established brand style, and
  the standard footer note — with **no image/diagram placeholders** (photos are not embedded in
  this narrative doc; they remain accessible via the existing EPIC-11/EPIC-14 signed-URL views).
- **Given** the same inputs, **when** a PDF is requested, **then** an equivalent PDF is produced
  (reusing the EPIC-14 PDF-writer pattern, US-14.1) for a client that just wants a PDF.
- **Given** any section text is missing (not yet authored), **when** rendering is attempted,
  **then** it is rejected with a clear message identifying the missing section — never silently
  emits a report with a blank section to a client.
- **Given** the renderer, **when** unit-tested, **then** it runs **entirely offline** — it takes
  already-finalised strings, never calls an AI provider — so its tests never depend on network
  access or an API key.

### Engineering Bar checklist
- **Secure:** the template file itself is a versioned, read-only asset (not user-editable); the
  generated file is stored via Storage and served **only via signed URL** (§14.3, matches EPIC-14
  pattern); generation is **audit-logged** (§14.5) as a report-created event scoped by `client_id`.
- **Clean:** the docx/PDF writers are the **same shared writers** EPIC-14 already established
  (US-14.1) — no second, parallel PDF pipeline; the template placeholders are named to match the
  `ReportContext` fields 1:1, no ad hoc string concatenation scattered through the renderer.
- **UX:** N/A directly (backend), but the missing-section rejection message flows into the US-18.6
  review screen in plain language.
- **No guessing:** before wiring placeholders, re-derive the exact template structure (kicker
  colour `#B8963E`, title font, section-header style, footer note) from the Pandora exemplar
  docx in `.claude/files/` rather than approximating it from memory.

### Definition of Done
Renderer produces docx + PDF matching the Pandora template's layout/branding from a `ReportContext`
+ finalised section texts; rejects incomplete input; no diagrams/photo embeds; runs with zero
network calls; output served signed-URL only; generation audit-logged; reuses EPIC-14 writers;
Pint + Larastan + Pest green.

---

## US-18.3 — AI narrative generation gateway (provider-agnostic)

**As** the platform (and, indirectly, ONYX's ability to choose or change AI vendor freely)
**I want** a single `NarrativeGenerator` contract with a config-selected driver, backed by exactly
two concrete implementations — one OpenAI-compatible HTTP driver (serving both **paid** OpenAI and
**self-hosted** Ollama/Gemma) and one Anthropic driver
**So that** switching AI provider is a configuration change, never a code change, per ADR-003.

**Estimate:** 8 · **Priority:** P2 · **Depends on:** — (self-contained) · **Status:** 📋 Ready

### Acceptance criteria
- **Given** `config('ai.default')` (env `AI_PROVIDER`), **when** the app requests a narrative
  generator, **then** `AiNarrativeManager` resolves the matching driver — no calling code ever
  instantiates a vendor client directly.
- **Given** the `OpenAiCompatibleDriver` configured with OpenAI's `base_url`, **when** it runs,
  **then** it calls the paid OpenAI API; **given** the same driver configured with a local
  Ollama's `base_url` (e.g. `http://ollama.internal:11434/v1`) serving a Gemma model, **when** it
  runs, **then** it works **unmodified** against that endpoint — proving one driver truly serves
  both a paid and a self-hosted target.
- **Given** the `AnthropicDriver` configured with a Claude model, **when** it runs, **then** it
  returns the same normalised `ReportDraft` shape as the OpenAI-compatible driver — no
  provider-specific type ever crosses the contract boundary.
- **Given** any driver, **when** the provider call fails or times out, **then** it raises a single
  normalised exception (not a leaked vendor exception type) so callers (US-18.4/US-18.6) have one
  failure mode to handle.
- **Given** the contract, **when** tested, **then** a `FakeNarrativeGenerator` implementing it is
  available for use by every other story's tests — no story's test suite needs a live key or a
  running Ollama instance.

### Engineering Bar checklist
- **Secure:** API keys read from encrypted config/env only, never logged, never returned in any
  API response; outbound calls are rate-limited server-side (§14.3) so a runaway loop can't blow
  out a paid provider's bill; timeouts + a single retry on every driver.
- **Clean:** exactly two driver classes per ADR-003 (not one per vendor); the Manager pattern
  mirrors the existing Storage/Mail driver-resolution idiom already used in this codebase — no new
  architectural idiom introduced; vendor SDK imports confined to their own driver file.
- **UX:** N/A (backend gateway).
- **No guessing:** confirm the exact Ollama `/v1/chat/completions` request/response shape and any
  divergence from OpenAI's spec by reading Ollama's own API docs before finalising the driver, not
  by assuming full parity.

### Definition of Done
`NarrativeGenerator` contract + `AiNarrativeManager` resolve a driver from config; OpenAI-compatible
driver verified against both a paid-OpenAI-shaped and an Ollama-shaped endpoint; Anthropic driver
returns the same normalised shape; failures normalised to one exception type; `FakeNarrativeGenerator`
available for other stories' tests; keys encrypted + never logged; rate-limited; Pint + Larastan +
Pest green.

---

## US-18.4 — Structured prompt builder + section-mapped output parser

**As** Yeis (PM)
**I want** the AI call to receive the job's facts (via `ReportContext`) plus the Pandora report as
a style exemplar, and to return distinctly-identifiable text for each report section
**So that** whatever the model produces can be mapped **deterministically** into the fixed
template (US-18.2) — free-form AI prose never breaks the report's layout.

**Estimate:** 5 · **Priority:** P2 · **Depends on:** US-18.1, US-18.3 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a `ReportContext`, **when** the prompt is built, **then** it includes the job facts as
  structured data (not free prose) plus a short style exemplar derived from the Pandora report's
  tone/structure — never the exemplar's own facts (no risk of Pandora content leaking into a Dior
  report).
- **Given** a provider that supports a structured/JSON response mode, **when** generation runs,
  **then** the driver requests it and the parser reads five named fields (title, executive
  summary, findings, risk, next steps) directly.
- **Given** a provider without JSON mode (e.g. some self-hosted models via Ollama), **when**
  generation runs, **then** the prompt requires clearly tagged section delimiters and a
  deterministic parser extracts the same five fields — same downstream contract either way.
- **Given** a response missing an expected section or containing extra/malformed content, **when**
  parsed, **then** it is rejected with a specific "section X missing" error rather than silently
  passed through with a gap.
- **Given** the parser, **when** unit-tested, **then** it is tested against the `FakeNarrativeGenerator`
  (US-18.3) with both a well-formed and a malformed sample — no live model call in tests.

### Engineering Bar checklist
- **Secure:** the prompt includes only data already authorised for the requesting PM to view
  (the same `ReportContext` scope, US-18.1) — no extra client data reaches the provider beyond
  what's in context; provider requests logged with **no PII beyond what's already in the job**
  (US-18.8).
- **Clean:** one prompt-building service + one parser reused regardless of which driver answers —
  no per-provider prompt variants beyond the JSON-mode-vs-tagged-delimiter branch already covered
  in ADR-003.
- **UX:** N/A directly (backend), but section-missing errors surface in plain language in US-18.6.
- **No guessing:** verify which configured models actually support a JSON/structured response mode
  before assuming it's available — do not silently fall back without a passing test proving the
  tagged-delimiter path works too.

### Definition of Done
Prompt includes structured facts + style exemplar (no exemplar-fact leakage); JSON-mode and
tagged-delimiter parsing both implemented and tested; malformed/missing sections rejected with a
specific error; fully testable via the fake driver; Pint + Larastan + Pest green.

---

## US-18.5 — Fabrication guardrail: cross-check AI output against the source context

**As** Yeis (PM)
**I want** every asset code, serial number, date and status word the AI's draft mentions to be
checked against the `ReportContext` it was given
**So that** a hallucinated detail (a wrong serial, an invented date, an asset that wasn't on the
job) is flagged before it ever reaches a client, not discovered after the report is sent.

**Estimate:** 5 · **Priority:** P2 · **Depends on:** US-18.4 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** an AI draft and the `ReportContext` it was built from, **when** the guardrail runs,
  **then** every `AssetCode`/serial number token found in the draft text is checked for presence
  in the context's asset list — unmatched tokens are flagged with the offending sentence.
- **Given** a date mentioned in the draft, **when** checked, **then** it must match the job's
  visit date (or a date otherwise present in context) — a fabricated date is flagged.
- **Given** a flagged draft, **when** it reaches the PM review screen (US-18.6), **then** flagged
  spans are visibly highlighted with the reason, so the PM's attention goes exactly where it's
  needed rather than re-reading the whole draft blind.
- **Given** a draft with no flags, **when** checked, **then** it proceeds to review with no false
  warnings — the guardrail must not cry wolf on ordinary phrasing.

### Engineering Bar checklist
- **Secure:** the guardrail runs server-side only, before any draft is persisted as reviewable
  content — a flagged draft is never auto-finalised regardless of role.
- **Clean:** one guardrail service reused for every provider's output (it operates on the parsed
  `ReportDraft` from US-18.4, not on provider-specific response shapes).
- **UX:** flags are plain-language ("this serial number doesn't match any asset on this job"), not
  a raw diff — designed for a non-technical PM.
- **No guessing:** confirm the exact `AssetCode`/serial format regexes against real EPIC-04 data
  before writing the matcher — false positives (flagging correct data) erode trust in the feature
  as fast as false negatives.

### Definition of Done
Asset codes/serials/dates in a draft are checked against `ReportContext`; unmatched items flagged
with the offending sentence; flags surface in the review UI; no false positives on a clean sample
set; tested against both a clean and a deliberately-fabricated fixture draft; Pint + Larastan +
Pest green.

---

## US-18.6 — PM draft, review and finalise workflow

**As** Yeis (PM)
**I want** an AI-generated report to land as an editable Draft that I review (with any US-18.5
flags visible), edit section-by-section, and explicitly finalise
**So that** nothing AI-authored ever reaches a client without my sign-off, and I retain a fully
manual path if AI is disabled or unavailable.

**Estimate:** 8 · **Priority:** P2 · **Depends on:** US-18.2, US-18.4, US-18.5 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** a `Validated` job with no report yet, **when** Yeis opens "Generate Report", **then**
  he can trigger AI generation (if enabled) or start from a blank manual form — both produce the
  same editable Draft structure.
- **Given** an AI-generated Draft, **when** Yeis reviews it, **then** each of the five sections is
  independently editable, any US-18.5 flags are visible inline, and a **"regenerate this section"**
  action re-runs generation for just that section without discarding the others.
- **Given** a Draft with all sections present and no unresolved flags acknowledged, **when** Yeis
  clicks **Finalise**, **then** the docx/PDF is rendered (US-18.2) and stored — and the report
  becomes **read-only** (matches the append-only spirit of EPIC-11's service history: a finalised
  report is not silently rewritten).
- **Given** AI is disabled or the provider call fails, **when** Yeis opens "Generate Report",
  **then** the manual entry path is immediately available with no error blocking him — AI is an
  accelerator, never a gate (§14.6).
- **Given** the finalised report, **when** viewed later, **then** it is accessible from the job
  and from the asset/store service history views, via signed URL only.

### Engineering Bar checklist
- **Secure:** authorise report generation/finalisation by role (`pm`) **and** the job's `client_id`
  scope (US-01.3); finalised reports are immutable at the application level (same append-only
  pattern as US-11.3, enforced in the model, not just hidden in the UI); finalisation is
  **audit-logged** (§14.5) with actor, job id and provider/model used (if AI-assisted).
- **Clean:** this Livewire surface is the **only** place a `ReportDraft` is mutated; it calls
  US-18.3/18.4/18.5 as services, never re-implements prompt or parsing logic inline.
- **UX:** PM desktop surface; the Finalise action is clearly the primary, deliberate action;
  section-level editing with inline `@error` validation; flags rendered as plain-language inline
  warnings, not raw JSON; designed loading (generation in progress) / empty (no report yet) /
  error (AI unavailable) states; Australian English.
- **No guessing:** verify the exact `Validated`-job → report relation and where the finalised
  file should be linked from (job detail, asset service history, store service history) by
  reading EPIC-11's existing surfaces before wiring the review screen's navigation.

### Definition of Done
Draft workflow supports AI-assisted and fully manual entry; per-section edit + regenerate; flags
visible; Finalise renders + stores the docx/PDF and locks the report (append-only); AI outage never
blocks manual completion; authorised by role + `client_id`; finalisation audit-logged; accessible
via signed URL from job/asset/store views; designed loading/empty/error states; Pint + Larastan +
Pest green.

---

## US-18.7 — AI provider configuration (Settings)

**As** an ONYX administrator
**I want** to choose the active AI provider/model (and, for self-hosted, the endpoint URL) from
Settings, with credentials stored encrypted
**So that** ONYX can switch from a paid model to a self-hosted Ollama/Gemma deployment — or back —
without a code change or a deploy, per ADR-003.

**Estimate:** 3 · **Priority:** P2 · **Depends on:** US-18.3, EPIC-16 · **Status:** 📋 Ready

### Acceptance criteria
- **Given** the Settings area (EPIC-16), **when** an admin opens AI configuration, **then** they
  can select the active provider (`openai_compatible` / `anthropic`), the model name, and — for
  `openai_compatible` — the `base_url` (defaults to OpenAI's; overridable to a self-hosted Ollama
  endpoint) and an optional API key.
- **Given** a saved configuration, **when** the next report generation runs, **then** it uses the
  saved settings immediately — no deploy or restart required.
- **Given** an API key field, **when** saved, **then** it is stored **encrypted** and never
  displayed back in plaintext (mask on redisplay, matching standard credential-field UX).
- **Given** an invalid/unreachable endpoint saved, **when** the next generation attempt runs and
  fails, **then** the US-18.6 manual-entry fallback is still available (never a hard outage).
- **Given** a non-admin PM, **when** they attempt to reach this Settings surface, **then** access
  is denied (role-gated, matching the rest of EPIC-16).

### Engineering Bar checklist
- **Secure:** role-gated to admin only; API key stored via an encrypted cast, never logged, never
  serialised into any audit entry in plaintext; base URL validated as a well-formed URL before
  save (defence in depth against SSRF-style misconfiguration — validate, don't trust free text).
- **Clean:** reuses the EPIC-16 Settings surface/pattern rather than introducing a parallel
  configuration screen.
- **UX:** clear labels distinguishing "paid provider" vs "self-hosted endpoint" fields; inline
  `@error` validation; plain-language helper text; Australian English.
- **No guessing:** confirm the EPIC-16 settings storage mechanism (single settings table vs.
  per-feature config rows) before adding these fields — follow the existing pattern rather than
  inventing a new one.

### Definition of Done
Admin-only AI provider/model/endpoint/key configuration in Settings; key encrypted at rest and
never shown in plaintext; changes apply without a deploy; base URL validated; non-admin access
denied; unreachable-endpoint failure still allows manual entry; Pint + Larastan + Pest green.

---

## US-18.8 — Audit logging and rate limiting of AI generation calls

**As** ONYX (accountable for both cost and client trust)
**I want** every AI narrative generation call recorded (actor, job, provider/model, outcome,
latency) and rate-limited
**So that** usage is auditable per §14.5, a runaway loop or malicious use can't blow out a paid
provider's bill, and any dispute about a report's origin ("was this AI-drafted?") is answerable
from the log.

**Estimate:** 3 · **Priority:** P2 · **Depends on:** US-18.3 · **Status:** ⏸ Blocked

### Acceptance criteria
- **Given** any call through `NarrativeGenerator` (full draft or section regenerate, US-18.6),
  **when** it completes (success or failure), **then** an audit entry is written asynchronously
  (§14.5) with actor, job id, provider/model, outcome, and latency — **never** the raw prompt or
  response body.
- **Given** a finalised report (US-18.6), **when** later viewed, **then** it is clear from the
  audit trail whether it was AI-assisted and with which provider/model, without exposing the
  underlying content.
- **Given** repeated generation requests from one actor in a short window, **when** a configured
  threshold is exceeded, **then** further requests are rate-limited (§14.3) with a plain-language
  message — the manual-entry path remains unaffected by the limit.
- **Given** the audit log viewer (EPIC-17), **when** an admin filters by job or actor, **then** AI
  generation events appear alongside other audited actions, not in a separate, undiscoverable log.

### Engineering Bar checklist
- **Secure:** log entries never contain the raw prompt/response (which could include client site
  details) — only the metadata needed for accountability; rate limiting applied server-side,
  never trusting a client-side throttle.
- **Clean:** reuses the US-00.5 async audit-writer concern and the EPIC-17 viewer — no second,
  bespoke logging path for AI events.
- **UX:** rate-limit messaging is plain-language, non-blaming, and explicitly points to the
  still-available manual-entry path.
- **No guessing:** confirm the US-00.5 audit-entry schema can carry provider/model/latency fields
  without a bespoke table before assuming a new model is needed.

### Definition of Done
Every generation call (full or per-section) audit-logged async with actor/job/provider/model/
outcome/latency, no raw content logged; rate-limited per actor with manual entry unaffected;
events visible in the EPIC-17 audit viewer; Pint + Larastan + Pest green.
