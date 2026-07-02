<?php

namespace App\Http\Requests\Reports\Concerns;

use Illuminate\Validation\Validator;

/**
 * Shared "date_to must be on or after date_from" check, reused by every
 * EPIC-14 report that takes a required date range (US-14.3/14.4) — one
 * plain-language error message, not copy-pasted per Form Request.
 */
trait ValidatesDateRange
{
    private function validateDateRange(Validator $v): void
    {
        $from = $this->input('date_from');
        $to   = $this->input('date_to');

        if ($from && $to && $to < $from) {
            $v->errors()->add('date_to', 'End date must be on or after the start date.');
        }
    }
}
