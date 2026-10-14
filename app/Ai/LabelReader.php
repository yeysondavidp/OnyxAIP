<?php

namespace App\Ai;

use App\Enums\AiDataCategory;
use App\Models\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Reads a device label from a technician's close-up and suggests form values (US-21.2).
 * Suggestions are never saved as such: the technician confirms them, the PM verifies the device,
 * and what the AI proposed is kept server-side so provenance can't be forged by the browser.
 */
class LabelReader
{
    public const FIELDS = ['manufacturer', 'model', 'serial_number', 'mac_address'];

    private const SYSTEM = <<<'TXT'
        You read printed text from close-up photos of labels on digital-signage hardware (screens,
        media players, lightboxes). Return only text that is clearly printed on the label. Never
        guess, infer, translate or complete partial text — if a field is not clearly readable,
        return null for it.
        - manufacturer: the brand printed on the label (e.g. "Samsung", "Beat").
        - model: the model name or number as printed.
        - serial_number: the value marked serial, S/N or SN, or the text under the main barcode.
        - mac_address: the MAC address if printed, as on the label.
        TXT;

    private const SCHEMA = [
        'type'       => 'object',
        'properties' => [
            'manufacturer'  => ['type' => ['string', 'null']],
            'model'         => ['type' => ['string', 'null']],
            'serial_number' => ['type' => ['string', 'null']],
            'mac_address'   => ['type' => ['string', 'null']],
        ],
        'required'             => self::FIELDS,
        'additionalProperties' => false,
    ];

    public function __construct(private readonly AiGateway $gateway) {}

    /** True when this client's AI mode allows label crops and a provider is configured. */
    public static function availableFor(Client $client): bool
    {
        return config('ai.default') !== 'disabled' && $client->ai_mode->allows(AiDataCategory::LabelCrop);
    }

    /**
     * @return array{fields: array<string, string>, token: string}
     *
     * @throws AiUnavailableException
     */
    public function read(Client $client, SanitisedImage $closeUp): array
    {
        $reply = $this->gateway->complete(new AiRequest(
            client: $client,
            category: AiDataCategory::LabelCrop,
            system: self::SYSTEM,
            prompt: 'Read this device label.',
            images: [$closeUp],
            jsonSchema: self::SCHEMA,
            maxTokens: 2048,
        ))->json();

        $fields = self::clean($reply);
        $token  = Str::random(40);
        Cache::put('label-read:'.$token, $fields, now()->addHour());

        return ['fields' => $fields, 'token' => $token];
    }

    /**
     * Which saved fields came from the AI, and which of those the technician changed. Reads the
     * suggestions this server stored for the token (once), never values sent by the browser.
     *
     * @param  array<string, mixed>  $saved
     * @return array{fields: list<string>, changed: list<string>}|null
     */
    public static function provenance(?string $token, array $saved): ?array
    {
        $suggested = $token === null || $token === '' ? null : Cache::pull('label-read:'.$token);

        if (! is_array($suggested) || $suggested === []) {
            return null;
        }

        $changed = array_values(array_filter(array_keys($suggested), fn (string $field) => strcasecmp(trim((string) ($saved[$field] ?? '')), (string) $suggested[$field]) !== 0));

        return ['fields' => array_keys($suggested), 'changed' => $changed];
    }

    /**
     * Keep only well-formed values: anything that doesn't look like what the field holds is
     * dropped rather than shown as a suggestion (US-18.5-style guardrail).
     *
     * @param  array<string, mixed>  $reply
     * @return array<string, string>
     */
    private static function clean(array $reply): array
    {
        $fields = [];

        foreach (self::FIELDS as $field) {
            $value = is_string($reply[$field] ?? null) ? trim($reply[$field]) : '';

            $value = match ($field) {
                'mac_address'   => self::normaliseMac($value),
                'serial_number' => preg_match('/^[A-Za-z0-9][A-Za-z0-9\-\/.]{2,99}$/', $value)                      === 1 ? $value : '',
                default         => mb_strlen($value) <= 100 && preg_match('/^[\pL\pN][\pL\pN\s\-\/.+]*$/u', $value) === 1 ? $value : '',
            };

            if ($value !== '') {
                $fields[$field] = $value;
            }
        }

        return $fields;
    }

    private static function normaliseMac(string $value): string
    {
        $hex = strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $value));

        return strlen($hex) === 12 ? implode(':', str_split($hex, 2)) : '';
    }
}
