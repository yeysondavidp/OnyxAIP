# ADR-003 — AI-assisted narrative generation: provider-agnostic gateway

- **Status:** Accepted (ONYX, 2026-10-14)
- **Date:** 2026-07-24
- **Deciders:** ONYX Visual (engineering)
- **Context source:** Two real ONYX deliverables reviewed this session — `Technical Service
  Report — Pandora, Pitt Street Mall (30 Jun 2026)` and the raw technician notes for `Dior,
  Boutique Pacific Fair (15 Jul 2026)`, both in `.claude/files/`. Feeds EPIC-18. Related: §2.3
  (cloud-swappable Storage precedent), §14.3 (rate limiting), §14.5 (audit), §14.6 (reliability).

## Context

ONYX already produces a **Technical Service Report** per visit by hand: a short, client-facing
document (kicker, a one-line "Corrective Action" title, a Site/Asset/Visit-date/Prepared-by box,
Executive Summary, Findings and Action Taken, Risk & Mitigation, Next Steps). It is not one of
the SRA §13.1 exports (those are tabular CSV/PDF); it is **prose**, written by a PM from the
technician's on-site notes and photos. This is a real, recurring cost the platform can absorb —
EPIC-18 turns the data already captured by EPIC-10/EPIC-11 into a first draft of that prose.

The open question this ADR resolves is **not whether to use AI, but how to avoid coupling the
feature to one vendor.** ONYX wants to be able to use a paid frontier model (Claude, GPT) for
quality, and/or a **self-hosted model** (Ollama running Gemma, Llama, etc.) for cost control or
for clients sensitive about their site data leaving ONYX's own infrastructure — and to switch
between them without touching the feature's code, the same way §2.3/§14.4 already made file
storage cloud-swappable via config.

## Decision

1. **A narrow contract, not a vendor SDK, is what the rest of the app talks to.**
   `App\Contracts\NarrativeGenerator` exposes one method — `generate(ReportContext $context):
   ReportDraft`. No provider-specific class (an Anthropic message, an OpenAI chat completion)
   is ever visible outside its own driver.

2. **Driver selection is a config value, resolved by a Manager**, exactly mirroring how
   `Storage`/`Mail`/`Queue` already swap implementations in this codebase:
   `config('ai.default')` / `AI_PROVIDER` env → `AiNarrativeManager extends
   Illuminate\Support\Manager` resolves the driver. Swapping provider is a `.env` change (or a
   Settings toggle, US-18.7), never a deploy.

3. **Two concrete drivers cover both "paid" and "self-hosted", not one driver per vendor:**
   - **`OpenAiCompatibleDriver`** speaks the OpenAI `chat/completions` wire format. This one
     driver serves **paid OpenAI**, and **any self-hosted OpenAI-compatible endpoint** —
     including **Ollama**, which exposes `/v1/chat/completions` unmodified, serving a local
     **Gemma**, Llama, or Mistral model. Only `base_url` and `api_key` (blank for local Ollama)
     differ between "paid" and "self-hosted" — there is no separate Ollama driver to maintain.
   - **`AnthropicDriver`** speaks the Anthropic Messages API for Claude models — the one wire
     format genuinely different enough to warrant its own driver.
   - Both drivers accept the same normalised request (system prompt, structured JSON context,
     temperature, max tokens) and return the same normalised `ReportDraft` (one string per
     report section). Provider quirks — message shape, streaming, context-window limits — stay
     inside the driver.

4. **The prompt/parsing logic (US-18.4) is tested against a `FakeNarrativeGenerator`**, not a
   live model. CI never depends on a network call, an API key, or a running Ollama instance.

5. **AI is an enhancement, never a hard dependency.** Every consumer of a `ReportDraft` must work
   when generation fails, times out, or is administratively disabled — the PM can always type or
   edit the narrative by hand (US-18.6). The deterministic template renderer (US-18.2) doesn't
   know or care whether a section's text came from a model or a keyboard.

6. **Data minimisation on every outbound call (decision D8, ONYX 2026-10-14).** Only what the
   task strictly needs ever leaves ONYX. One `OutboundAiPayload` sanitiser sits in front of every
   driver that calls an external provider, so no feature can skip it:
   - **Images:** only the photos or **crops** the task needs, **re-encoded** so all metadata is
     gone (EXIF/XMP/IPTC: GPS, device, timestamps, owner), downscaled to the smallest size that
     keeps them legible.
   - **What counts as confidential in a store** (client agreements cover trade secrets and
     confidential information; public-facing areas are not confidential — ONYX lead, 2026-10-14).
     A photo may be sent only if it shows **public areas** (shopfront, windows, sales floor as a
     customer sees it). Never sent:
     - **back-of-house** — stockrooms, comms/rack cupboards, offices, staff areas;
     - **non-public content** — unreleased campaign artwork, screens showing anything other than
       what customers see (POS, back-office, CMS, schedules), documents, price lists or plans not
       on display;
     - **credentials** — router/modem/network labels (they print Wi-Fi and admin passwords), so
       network devices are always entered by hand;
     - **people** — staff or customers (no faces; a photo or crop with a person is not sent);
     - **customer data** of any kind.
   - **People:** nothing that identifies a person — no faces (a crop that shows a person is not
     sent), no technician or store-manager names, emails or phone numbers.
   - **Text context:** codes instead of names where the task allows (asset/store codes), no street
     addresses, no credentials or router secrets, no signed URLs or tokens, no free-text the task
     doesn't use.
   - **Provider account:** commercial API only (no training on our data) with **zero data
     retention** requested; the provider is listed as a subprocessor where client contracts ask.
   - **Per-client control:** each client has an AI setting — *off*, *label crops only*, or
     *public-area photos* (label crops plus public-area photos for reports). Pandora, Dior and
     Sephora agreements were checked on 2026-10-14: no restriction beyond trade secrets and
     confidential information, so *public-area photos* is allowed for them. A future client whose
     contract forbids third-party processing uses the self-hosted driver only.
   - **A human decides what is public.** Before a photo is used by an AI feature, the PM (or the
     technician on site) confirms it shows only public areas and none of the never-sent items;
     unconfirmed photos are not sent.
   - **Audit:** each call records which data categories were sent (e.g. `label_crop`), the
     provider and the model — never the content itself.

## Consequences

**Positive**
- No vendor lock-in. ONYX can trial a self-hosted Gemma/Ollama model for cost, or keep a
  data-sensitive client's report generation entirely on-premises, by changing config alone.
- Two driver classes cover an open set of providers today (OpenAI, Ollama, LM Studio, vLLM, and
  any other OpenAI-compatible endpoint) plus Anthropic — not N driver classes for N vendors.
- Prompt-shape and section-parsing logic is fully unit-tested without cost, flakiness, or a live
  key, using the fake driver.

**Negative / costs**
- The normalised request/response is a least-common-denominator: a provider-specific feature
  (e.g. Anthropic prompt caching, an Ollama-only sampling parameter) isn't exposed through the
  generic contract. Accepted for v1 — extend the contract deliberately if a real need appears,
  rather than leaking provider types into calling code to reach for it.
- A capable self-hosted model (Gemma-class, 9B+) needs real RAM/CPU or a GPU — it does not belong
  on the lean ADR-002 app host. Treat a self-hosted Ollama instance as a **separate network
  service** reachable by `base_url`, not a container squeezed onto the small server.

## Guardrails for implementers

- Never import a vendor SDK/HTTP client (Anthropic's client, OpenAI's client) outside its own
  driver file — everything else depends on `NarrativeGenerator` only.
- Raw model output never reaches the docx/PDF renderer directly — it always passes through the
  US-18.4 section parser and the US-18.5 fact-check guardrail first.
- API keys are encrypted at rest and never written to logs; audit entries (US-18.8) record
  provider/model and latency, never the raw prompt or response body.
- A generation call gets a timeout and a single retry, then fails soft to "manual entry" — it
  must never block Job Validation (EPIC-11) or the report review screen.

## Related

- ADR-002 (deployment) — a self-hosted Ollama instance is a separate service, not part of the
  small app host's footprint.
- EPIC-11 (source data for the report), EPIC-14 (reporting infrastructure), EPIC-16 (settings —
  provider/model configuration).
- Stories: EPIC-18 (Technical Service Report Generation).
