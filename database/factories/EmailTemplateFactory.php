<?php

namespace Database\Factories;

use App\Enums\EmailTemplateSlot;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        return [
            'slot'    => EmailTemplateSlot::JobInvitation->value,
            'subject' => null,
            'body'    => null,
        ];
    }
}
