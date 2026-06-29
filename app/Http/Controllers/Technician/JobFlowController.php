<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobFlowController extends Controller
{
    public function overview(Request $request, string $job): View
    {
        // TODO: load job model by UUID, authorise via signed URL technician token
        abort(404, 'Job not found.');
    }

    public function start(Request $request, string $job): RedirectResponse
    {
        abort(404);
    }

    public function complete(Request $request, string $job): RedirectResponse
    {
        abort(404);
    }
}
