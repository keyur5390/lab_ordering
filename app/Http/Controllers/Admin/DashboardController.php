<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin home (client, 2026-10-30): today's work, orders by status, money and alerts.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard): Response
    {
        $period = $request->validate(['period' => ['nullable', Rule::in(array_keys(DashboardService::PERIODS))]])['period'] ?? 'today';

        return Inertia::render('admin/dashboard', [
            'today' => $dashboard->today(),
            'statuses' => $dashboard->statusCounts(),
            'period' => $period,
            'money' => $dashboard->money($period),
            'alerts' => $dashboard->alerts(),
        ]);
    }
}
