<?php

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\EmailTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * A PM-customised subject/body for one EmailTemplateSlot (US-16.2). Both fields
 * null means "using default" — the slot's built-in defaultSubject()/defaultBody()
 * apply. Plain Eloquent updateOrCreate() is used to write these (never
 * DB::table()) so the Auditable trait's automatic before/after diff captures
 * exactly what the story asks for: old subject+body, new subject+body.
 */
class EmailTemplate extends BaseModel
{
    /** @use HasFactory<EmailTemplateFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'slot',
        'subject',
        'body',
    ];
}
