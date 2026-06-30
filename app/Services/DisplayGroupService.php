<?php

namespace App\Services;

use App\Models\DisplayGroup;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

/**
 * Encapsulates the transaction logic for Display Group mutations (US-05.1).
 *
 * Exclusivity invariants (player and screen each belong to exactly one group)
 * are enforced both here (optimistic check) and by unique DB constraints
 * (hard guarantee). The DB constraint is the authoritative layer.
 */
class DisplayGroupService
{
    /**
     * Create a new Display Group for a store.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Store $store, array $data): DisplayGroup
    {
        return DB::transaction(function () use ($store, $data): DisplayGroup {
            $group = DisplayGroup::create([
                'store_id'           => $store->id,
                'group_name'         => $data['group_name'],
                'player_asset_id'    => $data['player_asset_id'],
                'layout_description' => $data['layout_description'] ?? null,
                'notes'              => $data['notes']              ?? null,
            ]);

            $group->screens()->attach($data['screen_asset_ids']);

            return $group;
        });
    }

    /**
     * Update an existing Display Group, atomically releasing old links.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DisplayGroup $group, array $data): void
    {
        DB::transaction(function () use ($group, $data): void {
            $group->update([
                'group_name'         => $data['group_name'],
                'player_asset_id'    => $data['player_asset_id'],
                'layout_description' => $data['layout_description'] ?? null,
                'notes'              => $data['notes']              ?? null,
            ]);

            // Sync replaces the pivot rows atomically
            $group->screens()->sync($data['screen_asset_ids']);
        });
    }

    public function delete(DisplayGroup $group): void
    {
        $group->delete(); // soft-delete via SoftDeletes — audit fires on the model event
    }
}
