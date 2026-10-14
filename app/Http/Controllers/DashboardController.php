<?php

namespace App\Http\Controllers;

use App\Services\Finance\DashboardSummary;
use App\Services\Memberships\ActiveWorkspace;
use App\Services\Memberships\Permissions;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, ActiveWorkspace $context): View
    {
        $request->validate(['month' => 'nullable|date_format:Y-m']);
        $workspace = $context->resolve($request);
        $summary = $workspace ? app(DashboardSummary::class)->forMonth($request->user(), $workspace, $request->input('month') ?: now($workspace->timezone)->format('Y-m')) : null;

        $allowed = $workspace ? app(Permissions::class)->snapshot($request->user(), $workspace) : [];

        return view('dashboard', compact('workspace', 'summary', 'allowed'));
    }
}
