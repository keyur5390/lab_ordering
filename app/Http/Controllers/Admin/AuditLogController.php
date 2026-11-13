<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Audit log (B-26): what staff did, newest first.
 */
class AuditLogController extends Controller
{
    public function index(Request $request, Settings $settings): Response
    {
        $filters = $request->validate([
            'user' => ['nullable', 'integer'],
            'action' => ['nullable', 'string', 'max:100'],
            'outcome' => ['nullable', Rule::in(['done', 'rejected', 'forbidden', 'error'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $timezone = $settings->timezone();
        $day = fn (string $date) => CarbonImmutable::parse($date, $timezone);

        return Inertia::render('admin/audit-log', [
            'entries' => JsonResource::collection(AuditLog::query()
                ->when($filters['user'] ?? null, fn ($query, $user) => $query->where('user_id', $user))
                ->when($filters['action'] ?? null, fn ($query, $action) => $query->where('action', $action))
                ->when($filters['outcome'] ?? null, fn ($query, $outcome) => $query->where('outcome', $outcome))
                ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', $day($from)->startOfDay()->utc()))
                ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', $day($to)->endOfDay()->utc()))
                ->latest('created_at')->latest('id')
                ->paginate(50)
                ->withQueryString()
                ->through(fn (AuditLog $entry) => [
                    'id' => $entry->id,
                    'at' => $entry->created_at->setTimezone($timezone)->toIso8601String(),
                    'who' => $entry->user_name ?? $entry->user_email ?? 'Unknown',
                    'role' => $entry->role ? UserRole::tryFrom($entry->role)?->label() : null,
                    'action' => $entry->action,
                    'label' => AuditLogger::label($entry->action),
                    'subject' => $entry->subject,
                    'outcome' => $entry->outcome,
                    'input' => $entry->input,
                    'ip' => $entry->ip,
                    'path' => $entry->method ? "{$entry->method} {$entry->path}" : null,
                ])),
            'filters' => [
                'user' => isset($filters['user']) ? (int) $filters['user'] : null,
                'action' => $filters['action'] ?? null,
                'outcome' => $filters['outcome'] ?? null,
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'staff' => User::where('role', '!=', UserRole::Customer)->orderBy('name')->get(['id', 'name'])->map->only(['id', 'name']),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action')
                ->map(fn (string $action) => ['value' => $action, 'label' => AuditLogger::label($action)]),
            'timezone' => $timezone,
        ]);
    }
}
