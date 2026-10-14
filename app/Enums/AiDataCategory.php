<?php

namespace App\Enums;

/**
 * What kind of client data an AI request carries (ADR-003 §6). The client's AiMode decides which
 * categories may leave ONYX; the audit log records the category, never the content.
 */
enum AiDataCategory: string
{
    /** Text with codes instead of names — no addresses, contacts, secrets or tokens. */
    case Text = 'text';

    /** A technician-framed crop of a device label (never a router/network label). */
    case LabelCrop = 'label_crop';

    /** A PM-confirmed photo of a public store area (shopfront, windows, sales floor). */
    case PublicPhoto = 'public_photo';
}
