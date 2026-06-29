<?php

namespace App\Http\Controllers\Technician;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class JobFlowController extends Controller
{
    public function overview(Request $request, string $job): \Illuminate\View\View
    {
        // TODO: load job model by UUID, authorise via signed URL technician token
        abort(404, 'Job not found.');
    }

    public function start(Request $request, string $job): \Illuminate\Http\RedirectResponse
    {
        abort(404);
    }

    public function complete(Request $request, string $job): \Illuminate\Http\RedirectResponse
    {
        abort(404);
    }
}
