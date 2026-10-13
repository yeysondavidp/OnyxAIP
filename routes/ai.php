<?php

use App\Mcp\Servers\OnyxServer;
use Laravel\Mcp\Facades\Mcp;

// Agent endpoint (ADR-004, US-20.2): bearer token only (Sanctum guard has no
// session fallback), agent accounts only, rate-limited per access key.
Mcp::web('/mcp', OnyxServer::class)->middleware(['auth:sanctum', 'agent', 'throttle:agent']);
