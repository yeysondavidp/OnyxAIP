<x-layouts.technician title="Link Expired">

    <div style="
        min-height: 100dvh;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: var(--space-8) var(--space-5);
        text-align: center;
    ">
        <div style="
            width: 64px;
            height: 64px;
            border-radius: var(--radius-full);
            background: rgba(202, 138, 4, 0.12);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--space-6);
            font-size: 28px;
            line-height: 1;
        ">
            ⏱
        </div>

        <h1 style="font-size: var(--fs-20); font-weight: var(--weight-bold); color: var(--text-primary); margin-bottom: var(--space-3);">
            This link has expired
        </h1>

        <p style="font-size: var(--fs-15); color: var(--text-secondary); max-width: 320px; line-height: 1.6; margin-bottom: var(--space-6);">
            Job links are valid for 72 hours. This one has passed its expiry time. Please ask your ONYX project manager to send you a fresh invitation link.
        </p>

        <p style="font-size: var(--fs-13); color: var(--text-secondary);">
            If you believe this is a mistake, contact ONYX directly.
        </p>
    </div>

</x-layouts.technician>
