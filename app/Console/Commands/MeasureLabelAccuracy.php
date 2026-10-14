<?php

namespace App\Console\Commands;

use App\Ai\AiUnavailableException;
use App\Ai\LabelReader;
use App\Ai\SanitisedImage;
use App\Models\Client;
use Illuminate\Console\Command;

/**
 * Measures label reading against labels whose values are known (US-21.2 DoD) before it is
 * switched on for technicians. A wrong value is the failure that matters — a missed field is safe
 * (the technician types it). The manifest and photos stay outside the repo (client material).
 */
class MeasureLabelAccuracy extends Command
{
    protected $signature = 'ai:label-accuracy
        {manifest : JSON file: [{"image": "/path/close-up.jpg", "expected": {"serial_number": "...", ...}}]}
        {--client= : Client code whose AI mode allows label crops (calls are audited against it)}';

    protected $description = 'Measure AI label reading against labels with known values (US-21.2)';

    public function handle(LabelReader $reader): int
    {
        $manifest = (string) $this->argument('manifest');
        $entries  = is_file($manifest) ? json_decode((string) file_get_contents($manifest), true) : null;
        $client   = Client::where('client_code', (string) $this->option('client'))->first();

        if (! is_array($entries) || $client === null) {
            $this->error('Pass a readable JSON manifest and an existing --client code.');

            return self::FAILURE;
        }

        $totals = array_fill_keys(LabelReader::FIELDS, ['correct' => 0, 'wrong' => 0, 'missing' => 0]);

        foreach ($entries as $i => $entry) {
            try {
                $read = $reader->read($client, SanitisedImage::fromFile((string) $entry['image']))['fields'];
            } catch (AiUnavailableException|\InvalidArgumentException $e) {
                $this->warn('Label '.($i + 1).': '.$e->getMessage());

                continue;
            }

            foreach ((array) $entry['expected'] as $field => $expected) {
                if (! isset($totals[$field])) {
                    continue;
                }

                $got    = $read[$field] ?? null;
                $result = $got === null ? 'missing' : (self::same($field, $got, (string) $expected) ? 'correct' : 'wrong');
                $totals[$field][$result]++;

                if ($result === 'wrong') {
                    $this->warn('Label '.($i + 1)." {$field}: read \"{$got}\", expected \"{$expected}\"");
                }
            }
        }

        $this->table(['Field', 'Correct', 'Wrong', 'Not read'], array_map(
            fn (string $field) => [$field, ...array_values($totals[$field])],
            array_keys($totals),
        ));

        return self::SUCCESS;
    }

    private static function same(string $field, string $got, string $expected): bool
    {
        $normalise = fn (string $v) => $field === 'mac_address'
            ? strtoupper((string) preg_replace('/[^0-9A-Fa-f]/', '', $v))
            : mb_strtolower(trim($v));

        return $normalise($got) === $normalise($expected);
    }
}
