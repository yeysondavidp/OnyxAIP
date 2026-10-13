<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetJob;
use App\Mcp\Tools\GetStore;
use App\Mcp\Tools\SearchAssets;
use App\Mcp\Tools\SearchStores;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The ONYX AIP agent endpoint (ADR-004). Only tools the calling key has the
 * ability for are listed; every tool re-checks the ability and the Policy.
 */
#[Name('ONYX AIP')]
#[Version('1.0.0')]
#[Instructions('ONYX Asset Intelligence Platform: a register of digital display assets (screens, media players, lightboxes, fixtures) deployed in retail stores, and the service jobs performed on them. Look things up with search_stores, get_store, search_assets and get_job before proposing changes. Store codes look like PAN-MIR-001, asset codes like PAN-PLY-0001, job references like JOB-2026-004. You only see the clients your access key is scoped to.')]
class OnyxServer extends Server
{
    protected array $tools = [
        SearchStores::class,
        GetStore::class,
        SearchAssets::class,
        GetJob::class,
    ];
}
