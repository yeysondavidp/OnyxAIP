# EPIC-16 — Settings & Email Templates

> Gives Yeis a single, PM-only control panel for platform-wide configurable values and
> email templates. Consumed by EPIC-12 (SLA) and EPIC-13 (notifications) at runtime.

Related: US-00.5 (audit foundation), EPIC-12 (SLA), EPIC-13 (notifications). Sprint: 10.

---

## US-16.1 — Configurable platform settings

**As** Yeis (PM)
**I want** a single settings screen where I can adjust platform-wide thresholds — SLA breach-warning percentage, early-start-window options, warranty-alert lead times, and technician reminder timing
**So that** EPIC-12 (SLA clock) and EPIC-13 (notifications) consume live, audited values instead of hard-coded constants.

**Estimate:** 5 · **Priority:** P2 · **Depends on:** US-00.5, EPIC-12, EPIC-13 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** a PM on the settings screen, **when** they navigate to Settings → Platform, **then** they see labelled fields for each configurable value; no setting is visible or editable by a technician.
- **Given** the SLA breach-warning threshold field, **when** saved, **then** the value is an integer between 1 and 99 (representing percentage of SLA window elapsed); EPIC-12's breach-risk computation reads this value at runtime.
- **Given** the early-start-window field, **when** saved, **then** the accepted options are the enum defined in SRA §5.1 (`Anytime`, `30 min`, `1 hr`, `2 hr`, `4 hr`); any other value is rejected with a plain inline error.
- **Given** the warranty-alert lead-time field, **when** saved, **then** one or more values from `[30, 60, 90]` days can be toggled; at least one must remain selected; EPIC-13's warranty scheduler reads these values.
- **Given** the technician-reminder timing field, **when** saved, **then** the value is a positive integer number of hours before scheduled time (minimum 1, maximum 168); EPIC-13's reminder job reads this value.
- **Given** any invalid input, **when** the form is submitted, **then** inline `@error` messages appear per field in plain Australian English; the form does not submit.
- **Given** any setting change, **when** saved, **then** an audit entry is written asynchronously (via US-00.5) recording: actor, action `settings.updated`, key, old value, new value, UTC timestamp.
- **Given** the settings page, **when** accessed by any non-PM user or an unauthenticated visitor, **then** a 403 is returned; technicians cannot reach the route even if they guess the URL.
- **Given** a successful save, **when** the page reloads, **then** a plain-language success confirmation is shown and persisted values are pre-filled.

### Engineering Bar checklist

- **Secure:** route behind `auth` + PM role middleware (UI hidden and route denied); `SettingsRequest` Form Request validates every key against its allow-list and type before any write; settings values are stored in a dedicated `platform_settings` table with a typed `value` column — never in `.env` or config files that bypass auditing; `$fillable` on the model (never `$guarded = []`); all changes audited via US-00.5; no setting key or value is trusted raw from request input (cast and validate in the Form Request).
- **Clean:** one `PlatformSetting` model keyed by `setting_key` (string, unique) with a `value` column; a `Settings` facade or service wraps reads so EPIC-12/13 never query the table directly; no duplicated threshold constants elsewhere in the codebase; use `DB::table()->updateOrInsert()` on upsert path.
- **UX:** settings form uses clear section headings; each field has a label, a helper note explaining the effect, and inline `@error`; plain-language success/error; form is functional at 768px+ (PM-only surface per §14.2); primary action (Save) is visually obvious; loading state on submit.
- **No guessing:** read the `platform_settings` migration and `PlatformSetting` model before writing the Form Request; verify the column types with tinker; confirm EPIC-12 and EPIC-13 consume settings via the service/facade rather than hard-coded values before closing this story.

### Definition of Done

- PM-only route + middleware verified by tests (non-PM → 403, unauthenticated → redirect).
- Form Request rejects every invalid combination and passes valid ones.
- `PlatformSetting` model with `$fillable`, unique key index, and auditing via US-00.5.
- Settings service/facade tested: returns live DB values; EPIC-12/13 integration points documented.
- Audit entries written on every save; verified by a feature test.
- Inline `@error` UX and success state rendered; Australian English copy.
- Pint + Larastan clean; happy-path feature test passes.

---

## US-16.2 — Email templates (PM-editable, safe variable substitution)

**As** Yeis (PM)
**I want** to edit the subject line and body of each system email template through the platform, using a documented set of safe variables
**So that** EPIC-09 (job invitations) and EPIC-13 (notifications) send correctly branded, on-message emails without requiring a code deployment, while no injection or XSS is possible.

**Estimate:** 8 · **Priority:** P2 · **Depends on:** US-00.5, EPIC-09, EPIC-13 · **Status:** 📋 Ready

### Acceptance criteria

- **Given** a PM on the settings screen, **when** they navigate to Settings → Email Templates, **then** they see a list of all system template slots (one per notification type defined in §12.1 and §12.2); no template is editable by a technician.
- **Given** a template slot, **when** opened for editing, **then** the PM sees: a subject-line field (plain text, max 200 chars) and a body field (plain text / minimal markdown — no raw HTML input accepted); a sidebar listing the allow-listed variables for that template with descriptions.
- **Given** the allow-listed variables for a template (e.g. `{{job_reference}}`, `{{store_name}}`, `{{technician_name}}`, `{{scheduled_date}}`, `{{signed_url}}`), **when** the PM saves a body containing one of them, **then** the variable is preserved and substituted safely at send time; each variable is defined per template slot and documented in the UI sidebar.
- **Given** any input containing HTML tags or script content, **when** saved, **then** the content is stripped of all HTML tags before storage; on render, all substituted values are HTML-escaped before injection into the Blade email template; the stored body is treated as plain text at all times.
- **Given** a variable not on the allow-list for that template (e.g. `{{password_reset_token}}` in a job-invitation template), **when** the PM saves a body containing it, **then** a plain inline error names the disallowed variable and the form does not save.
- **Given** a template with at least one required variable (e.g. `{{signed_url}}` in the invitation template), **when** the PM saves a body that omits it, **then** a plain inline warning alerts them; they may override and save, but the warning is logged to the audit trail.
- **Given** a saved template, **when** a PM clicks Preview, **then** the system renders the template with representative dummy values (not real tenant data) and displays the result in a read-only panel — no email is sent.
- **Given** the queued mailers in EPIC-09 and EPIC-13, **when** they send an email, **then** they retrieve the active template from the DB, perform allow-list variable substitution with HTML-escaped values, and wrap the result in the standard Blade email layout; falling back to a safe built-in default if no PM-defined template exists for that slot.
- **Given** any template save, **when** it succeeds, **then** an audit entry is written (via US-00.5): actor, action `email_template.updated`, template slot, old subject + body, new subject + body, UTC timestamp.
- **Given** the templates list, **when** no custom template has been saved for a slot, **then** a "Using default" badge appears and the default content is shown read-only, so Yeis knows what will go out before she customises it.
- **Given** an unauthenticated visitor or a technician, **when** they attempt to reach any template route, **then** a 403 is returned.

### Engineering Bar checklist

- **Secure:** route behind `auth` + PM role middleware at both UI and HTTP layers; `EmailTemplateRequest` Form Request validates: subject ≤ 200 chars (plain text), body ≤ 5,000 chars (plain text); HTML stripped from body and subject before storage using `strip_tags()` — never trust the client to omit HTML; variable substitution at send time uses an explicit allow-list per template slot and `e()` (Laravel's `htmlspecialchars`) on every substituted value — never `{!! !!}` with user-derived content; `$fillable` on `EmailTemplate` model; audit every save via US-00.5; the preview endpoint uses dummy data only — never a real technician's signed URL or real tenant record.
- **Clean:** one `EmailTemplate` model keyed by `slot` (enum of template slot names); one `EmailTemplateRenderer` service handles substitution and fallback — imported by EPIC-09/13 mailers, not duplicated; allow-list of variables per slot defined as a single PHP constant/enum so PM UI sidebar and server-side validation share the same source of truth; no variable substitution via `eval()`, `preg_replace_callback` with untrusted patterns, or any dynamic template engine that evaluates PHP; use simple `str_replace()` over an iterated allow-list.
- **UX:** template list page shows slot name, last edited timestamp, and "Using default" / "Customised" badge; edit page has clear subject + body fields, the variable sidebar, inline `@error`, and a prominent Preview button; preview renders in a styled panel within the page (no new tab, no email sent); save confirmation is plain Australian English; error messages name the specific disallowed variable; accessible keyboard/focus order; functional at 768px+.
- **No guessing:** read the mailers in EPIC-09 and EPIC-13 to confirm the variable names they pass before defining the allow-list; verify the `email_templates` table columns with tinker before writing the Form Request; do not invent variable names that do not match what the mailers actually provide.

### Definition of Done

- PM-only routes protected; non-PM → 403 and unauthenticated → redirect verified by tests.
- `EmailTemplate` model with `$fillable`, slot enum, and auditing via US-00.5.
- `EmailTemplateRenderer` service: allow-list substitution, HTML-escape on every value, `strip_tags()` on input, fallback to built-in default — covered by unit tests including an XSS-attempt test.
- Form Request rejects HTML, over-length input, and disallowed variables; required-variable warning logged.
- Preview endpoint uses dummy data only; verified by test that no real tenant record is accessed.
- EPIC-09 and EPIC-13 mailers updated to consume templates via the renderer service.
- Audit entries written on every template save; verified by a feature test.
- Inline `@error` UX, variable sidebar, "Using default" badge, and save confirmation rendered.
- Australian English copy throughout.
- Pint + Larastan clean; happy-path feature test and XSS-prevention unit test both pass.
