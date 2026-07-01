<?php

namespace App\Http\Controllers;

use App\Enums\EmailTemplateSlot;
use App\Http\Requests\UpdateEmailTemplateRequest;
use App\Jobs\WriteAuditLog;
use App\Models\EmailTemplate;
use App\Services\Emails\EmailTemplateRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmailTemplateController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', EmailTemplate::class);

        $existing = EmailTemplate::whereNotNull('subject')->get()->keyBy('slot');

        $rows = collect(EmailTemplateSlot::cases())->map(fn (EmailTemplateSlot $slot) => [
            'slot'     => $slot,
            'template' => $existing->get($slot->value),
        ]);

        return view('settings.email-templates.index', compact('rows'));
    }

    public function edit(EmailTemplateSlot $slot): View
    {
        $this->authorize('update', EmailTemplate::class);

        return view('settings.email-templates.edit', [
            'slot'     => $slot,
            'template' => EmailTemplate::where('slot', $slot->value)->first(),
        ]);
    }

    public function update(UpdateEmailTemplateRequest $request, EmailTemplateSlot $slot): RedirectResponse
    {
        $validated = $request->validated();

        $missingRequired = array_values(array_filter(
            $slot->requiredVariables(),
            fn (string $var) => ! str_contains($validated['subject'], '{{'.$var.'}}')
                && ! str_contains($validated['body'], '{{'.$var.'}}'),
        ));

        $confirmed = $request->boolean('confirm_missing_required');

        if ($missingRequired !== [] && ! $confirmed) {
            return back()->withInput()->with('missingRequiredWarning', $missingRequired);
        }

        $template = EmailTemplate::updateOrCreate(
            ['slot' => $slot->value],
            ['subject' => $validated['subject'], 'body' => $validated['body']],
        );

        if ($missingRequired !== []) {
            $actor = $request->user();

            WriteAuditLog::dispatch(
                userId: $actor->id,
                userRole: $actor->role->value,
                action: 'email_template.required_var_override',
                auditableType: EmailTemplate::class,
                auditableId: $template->id,
                before: null,
                after: ['slot' => $slot->value, 'missing_required' => $missingRequired],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            );
        }

        return redirect()
            ->route('email-templates.index')
            ->with('success', "Template '{$slot->label()}' has been updated.");
    }

    public function preview(EmailTemplateSlot $slot, EmailTemplateRenderer $renderer): View
    {
        $this->authorize('viewAny', EmailTemplate::class);

        return view('settings.email-templates.preview', [
            'slot'     => $slot,
            'rendered' => $renderer->render($slot, $slot->dummyVariables()),
        ]);
    }
}
