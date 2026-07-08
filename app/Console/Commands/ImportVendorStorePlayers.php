<?php

namespace App\Console\Commands;

use App\Enums\AssetStatus;
use App\Enums\AssetType;
use App\Enums\AustralianState;
use App\Enums\PlayerType;
use App\Enums\StoreType;
use App\Models\Asset;
use App\Models\Client;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time bulk import of stores + media player assets from a vendor CSV
 * export (SRA §16 Q3 — "Asset import"). Columns: Group,Player,Model, where
 * Group is "Client/Store" or "Client/Store/Sublocation".
 *
 * The CSV carries no address/manufacturer data, so several fields are
 * necessarily placeholders flagged in the store/asset notes for PM review —
 * see STATE_MAP below and the manufacturer/player-type inference in
 * createAssetIfMissing() for exactly what's inferred vs. placeholdered,
 * agreed with the client before running this against prod.
 */
class ImportVendorStorePlayers extends Command
{
    protected $signature = 'import:vendor-store-players
        {csv : Path to the vendor CSV export}
        {--dry-run : Preview what would be created without writing anything}';

    protected $description = 'Import stores and media player assets from a vendor CSV export (Group,Player,Model)';

    /** Store names that are not real retail locations — skipped, not imported. */
    private const SKIP_STORES = [
        'SEPHORA AU|Available', // unassigned/spare inventory pool, not a store
    ];

    /**
     * client|store => AustralianState. Inferred from well-known shopping
     * centre / suburb names. NZ stores are placeholdered to NSW (flagged in
     * notes) since the system has no non-AU state — see the store's notes.
     */
    private const STATE_MAP = [
        'DIOR|BOUTIQUE BONDI'             => 'NSW',
        'DIOR|BOUTIQUE BURNSIDE'          => 'SA',
        'DIOR|BOUTIQUE CHADSTONE'         => 'VIC',
        'DIOR|BOUTIQUE CHATSWOOD CHASE'   => 'NSW',
        'DIOR|BOUTIQUE HIGHPOINT'         => 'VIC',
        'DIOR|BOUTIQUE MELB CENTRAL'      => 'VIC',
        'DIOR|BOUTIQUE MIRANDA'           => 'NSW',
        'DIOR|BOUTIQUE PACIFIC FAIR'      => 'QLD',
        'DIOR|BOUTIQUE PARRAMATTA'        => 'NSW',
        'DIOR|DJs BST'                    => 'VIC', // Bourke St Mall, Melbourne
        'DIOR|DJs Bondi'                  => 'NSW',
        'DIOR|DJs Burwood'                => 'NSW',
        'DIOR|DJs Chadstone'              => 'VIC',
        'DIOR|MACQUARIE'                  => 'NSW',
        'DIOR|MYER Bondi'                 => 'NSW', // Westfield Bondi Junction
        'DIOR|MYER CHERMSIDE'             => 'QLD',
        'DIOR|MYER MELBOURNE'             => 'VIC',
        'DIOR|MYER PERTH'                 => 'WA',
        'DIOR|MYER PITT ST'               => 'NSW',
        'DIOR|NZ-BOUTIQUE COMMERCIAL BAY' => 'NSW', // placeholder — actually Auckland, NZ
        'DIOR|NZ-DJs NEWMARKET'           => 'NSW', // placeholder — actually Auckland, NZ
        'DIOR|NZ-S&C QUEEN ST'            => 'NSW', // placeholder — actually Auckland, NZ
        'DIOR|POP-UP BURWOOD'             => 'NSW',
        'DIOR|POP-UP QVB'                 => 'NSW',
        'DIOR|SYD AIRPORT (LCP/LED)'      => 'NSW',
        'DIOR|SYD AIRPORT Heinemann'      => 'NSW',
        'DIOR|SYD T2'                     => 'NSW',
        'SEPHORA AU|BONDI'                => 'NSW',
        'SEPHORA AU|CANBERRA'             => 'ACT',
        'SEPHORA AU|CASTLE TOWERS'        => 'NSW',
        'SEPHORA AU|Chatswood'            => 'NSW',
        'SEPHORA AU|Chermside'            => 'QLD',
        'SEPHORA AU|Doncaster'            => 'VIC',
        'SEPHORA AU|Eastland'             => 'VIC',
        'SEPHORA AU|Highpoint'            => 'VIC',
        'SEPHORA AU|INDOOROOPILLY'        => 'QLD',
        'SEPHORA AU|KARRINYUP'            => 'WA',
        'SEPHORA AU|MACQUARIE'            => 'NSW',
        'SEPHORA AU|MELB CENTRAL'         => 'VIC',
        'SEPHORA AU|MT GRAVATT'           => 'QLD',
        'SEPHORA AU|Miranda'              => 'NSW',
        'SEPHORA AU|PAC FAIR'             => 'QLD',
        'SEPHORA AU|PARRAMATTA'           => 'NSW',
        'SEPHORA AU|PERTH'                => 'WA',
        'SEPHORA AU|PERTH CAROUSELL'      => 'WA',
        'SEPHORA AU|PITT ST'              => 'NSW',
        'SEPHORA AU|Rundle Mall'          => 'SA',
        'SEPHORA AU|SEPHORA HEADQUARTERS' => 'NSW', // placeholder — HQ location unconfirmed
        'SEPHORA AU|SUNSHINE PLAZA'       => 'QLD',
        'SEPHORA AU|SYLVIA PARK - NZ'     => 'NSW', // placeholder — actually Auckland, NZ
        'SEPHORA AU|Southland'            => 'VIC',
    ];

    /** Stores whose true location is outside Australia — flagged in notes, not just placeholdered silently. */
    private const NZ_STORES = [
        'DIOR|NZ-BOUTIQUE COMMERCIAL BAY' => 'Auckland, New Zealand (Commercial Bay)',
        'DIOR|NZ-DJs NEWMARKET'           => 'Auckland, New Zealand (Newmarket)',
        'DIOR|NZ-S&C QUEEN ST'            => 'Auckland, New Zealand (Queen St)',
        'SEPHORA AU|SYLVIA PARK - NZ'     => 'Auckland, New Zealand (Sylvia Park)',
    ];

    private const HQ_STORES = [
        'SEPHORA AU|SEPHORA HEADQUARTERS' => true,
    ];

    private const CLIENT_CODES = [
        'DIOR'       => 'DIO',
        'SEPHORA AU' => 'SEP',
    ];

    private const STATE_TIMEZONES = [
        'NSW' => 'Australia/Sydney',
        'VIC' => 'Australia/Melbourne',
        'QLD' => 'Australia/Brisbane',
        'WA'  => 'Australia/Perth',
        'SA'  => 'Australia/Adelaide',
        'TAS' => 'Australia/Hobart',
        'ACT' => 'Australia/Sydney',
        'NT'  => 'Australia/Darwin',
    ];

    public function handle(): int
    {
        $path = $this->argument('csv');

        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $rows   = $this->parseCsv($path);

        $stats = [
            'clients_created' => 0,
            'stores_created'  => 0,
            'stores_existing' => 0,
            'assets_created'  => 0,
            'assets_skipped'  => 0,
        ];
        $skippedRows = [];
        $clientCache = [];
        $storeCache  = [];

        DB::beginTransaction();

        try {
            foreach ($rows as $row) {
                [$clientName, $storeName, $sublocation] = $this->splitGroup($row['group']);
                $key                                    = "{$clientName}|{$storeName}";

                if (in_array($key, self::SKIP_STORES, true)) {
                    $skippedRows[] = $row;

                    continue;
                }

                $client = $clientCache[$clientName] ??= $this->findOrCreateClient($clientName, $stats);
                $store  = $storeCache[$key]         ??= $this->findOrCreateStore($client, $key, $storeName, $stats);

                $created = $this->createAssetIfMissing($client, $store, $row, $sublocation);
                $stats[$created ? 'assets_created' : 'assets_skipped']++;
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed, rolled back: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->printSummary($stats, $skippedRows, $dryRun);

        return self::SUCCESS;
    }

    /** @return array<int, array{group: string, player: string, model: string}> */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        fgetcsv($handle); // header — column order is fixed (Group,Player,Model)
        $rows = [];

        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = [
                'group'  => trim($line[0]),
                'player' => trim($line[1]),
                'model'  => trim($line[2]),
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Split "Client/Store[/Sublocation]" into its parts. One row in the
     * known export has a literal "/" inside the store name's parentheses
     * ("SYD AIRPORT (LCP/LED)") — handled as an explicit exception rather
     * than a general parser, since it's the only one and guessing a general
     * rule for embedded slashes risks silently mis-splitting a future export.
     *
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function splitGroup(string $group): array
    {
        if (str_starts_with($group, 'DIOR/SYD AIRPORT (LCP/LED)')) {
            return ['DIOR', 'SYD AIRPORT (LCP/LED)', null];
        }

        $parts  = explode('/', $group);
        $client = $parts[0];
        $store  = $parts[1];
        $sub    = isset($parts[2]) ? implode('/', array_slice($parts, 2)) : null;

        return [$client, $store, $sub];
    }

    /** @param array<string, int> $stats */
    private function findOrCreateClient(string $name, array &$stats): Client
    {
        $existing = Client::where('client_name', $name)->first();

        if ($existing) {
            return $existing;
        }

        $stats['clients_created']++;

        return Client::create([
            'client_name' => $name,
            'client_code' => self::CLIENT_CODES[$name] ?? strtoupper(substr($name, 0, 3)),
            'is_active'   => true,
        ]);
    }

    /** @param array<string, int> $stats */
    private function findOrCreateStore(Client $client, string $key, string $storeName, array &$stats): Store
    {
        $existing = Store::allClients()->where('client_id', $client->id)->where('store_name', $storeName)->first();

        if ($existing) {
            $stats['stores_existing']++;

            return $existing;
        }

        $state = self::STATE_MAP[$key] ?? null;

        if ($state === null) {
            throw new \RuntimeException("No state mapping for store \"{$key}\" — add it to STATE_MAP before importing.");
        }

        $notes = 'Imported from vendor CSV export — address, suburb and postcode need PM confirmation.';

        if (isset(self::NZ_STORES[$key])) {
            $notes .= ' Actual location: '.self::NZ_STORES[$key].' — state stored as an Australian placeholder only; the system does not yet support non-AU stores.';
        }

        if (isset(self::HQ_STORES[$key])) {
            $notes .= ' Corporate HQ, not a retail location — imported for reception screen asset tracking only. Address unconfirmed.';
        }

        $storeType = match (true) {
            str_contains(strtoupper($storeName), 'POP-UP') => StoreType::PopUp,
            str_contains(strtoupper($storeName), 'MYER')   => StoreType::DepartmentStoreConcession,
            str_contains(strtoupper($storeName), 'DJS')    => StoreType::DepartmentStoreConcession,
            isset(self::HQ_STORES[$key])                   => StoreType::Other,
            default                                        => StoreType::ConceptStore,
        };

        $stats['stores_created']++;

        $store = new Store([
            'client_id'      => $client->id,
            'store_name'     => $storeName,
            'store_type'     => $storeType,
            'address_line1'  => 'TBC',
            'suburb'         => 'TBC',
            'state'          => AustralianState::from($state),
            'postcode'       => 'TBC',
            'country'        => 'Australia',
            'store_timezone' => self::STATE_TIMEZONES[$state],
            'notes'          => $notes,
            'is_active'      => true,
        ]);
        // generateCode()'s $suburb param becomes the code's middle segment (e.g.
        // "SYD" in "PAN-SYD-001") — suburb itself is a TBC placeholder here, so
        // state produces a more useful code (e.g. "DIO-NSW-001") in the meantime.
        $store->store_code = Store::generateCode($client, $state);
        $store->save();

        return $store;
    }

    private function createAssetIfMissing(Client $client, Store $store, array $row, ?string $sublocation): bool
    {
        $playerName = $row['player'];
        $model      = $row['model'];

        $exists = Asset::allClients()
            ->where('store_id', $store->id)
            ->where('asset_name', $playerName)
            ->where('model', $model)
            ->exists();

        if ($exists) {
            return false;
        }

        $manufacturer = str_starts_with(strtolower($model), 'stix') ? 'Navori' : 'Unknown';

        $playerType = match (true) {
            str_starts_with(strtolower($model), 'stix')  => PlayerType::StandaloneHardware,
            str_starts_with(strtolower($model), 'rk')    => PlayerType::SocApp,
            preg_match('/^(QH|QM|PM)\d/i', $model) === 1 => PlayerType::SocApp,
            default                                      => PlayerType::StandaloneHardware,
        };

        Asset::createWithDetail([
            'asset_code'     => $this->nextAssetCode($client),
            'asset_type'     => AssetType::MediaPlayer,
            'client_id'      => $client->id,
            'store_id'       => $store->id,
            'asset_name'     => $playerName,
            'manufacturer'   => $manufacturer,
            'model'          => $model,
            'asset_status'   => AssetStatus::Active,
            'location_notes' => $sublocation,
            'notes'          => 'Imported from vendor CSV export.',
        ], [
            'player_type' => $playerType,
        ]);

        return true;
    }

    private function nextAssetCode(Client $client): string
    {
        $prefix = strtoupper($client->client_code);

        $sequence = 1;
        do {
            $candidate = sprintf('%s-PLY-%04d', $prefix, $sequence);
            $sequence++;
        } while (Asset::allClients()->where('asset_code', $candidate)->exists());

        return $candidate;
    }

    /** @param array<string, int> $stats  @param array<int, array<string, string>> $skippedRows */
    private function printSummary(array $stats, array $skippedRows, bool $dryRun): void
    {
        $this->newLine();
        $this->info($dryRun ? 'DRY RUN — nothing was written.' : 'Import complete.');
        $this->table(['Metric', 'Count'], [
            ['Clients created', $stats['clients_created']],
            ['Stores created', $stats['stores_created']],
            ['Stores already existing', $stats['stores_existing']],
            ['Assets created', $stats['assets_created']],
            ['Assets skipped (already existed)', $stats['assets_skipped']],
            ['Rows skipped (not a real store)', count($skippedRows)],
        ]);

        if ($skippedRows !== []) {
            $this->newLine();
            $this->warn('Skipped rows (not imported — not a real store):');
            foreach ($skippedRows as $row) {
                $this->line("  {$row['group']} | {$row['player']} | {$row['model']}");
            }
        }
    }
}
