<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The audit log (B-26): every change made by staff (admins, lab staff, collectors), and staff
 * sign-ins. Customers' own actions aren't logged. TODO(client): confirm scope and retention (kept forever).
 */
class AuditLogger
{
    /** Fields never stored: secrets, and Laravel's own form fields. */
    private const HIDDEN = ['password', 'password_confirmation', 'current_password', '_token', '_method', 'token'];

    /** Requests the auth events already record. */
    private const AUTH_ROUTES = ['login.store', 'logout', 'two-factor.login.store'];

    /** Readable names for actions whose route name doesn't say it well enough. */
    private const LABELS = [
        'admin.orders.cancel' => 'Cancelled an order',
        'admin.orders.reschedule' => 'Rescheduled an order',
        'admin.orders.refunds.store' => 'Refunded an order',
        'admin.orders.payment.store' => 'Recorded a payment',
        'admin.orders.collector.update' => 'Changed an order\'s collector',
        'admin.lab.collect' => 'Marked a sample collected',
        'admin.lab.receive' => 'Marked a sample received at the lab',
        'admin.lab.reports.store' => 'Uploaded a report',
        'admin.notifications.resend' => 'Resent a message',
        'admin.users.role' => 'Changed a user\'s role',
        'admin.users.disable' => 'Disabled an account',
        'admin.users.enable' => 'Enabled an account',
        'admin.users.store' => 'Invited a staff member',
        'admin.settings.update' => 'Changed settings',
        'admin.pages.update' => 'Edited a page',
        'admin.pages.home.update' => 'Edited the home page',
        'admin.pages.faqs.store' => 'Added a question',
        'admin.pages.faqs.update' => 'Edited a question',
        'admin.pages.faqs.move' => 'Reordered questions',
        'admin.pages.faqs.destroy' => 'Deleted a question',
        'collector.claim' => 'Took a home visit',
        'collector.collected' => 'Marked a sample collected',
        'collector.failed' => 'Reported a failed visit',
        'collector.payment' => 'Recorded a payment',
        'auth.login' => 'Signed in',
        'auth.failed' => 'Failed to sign in',
        'auth.logout' => 'Signed out',
    ];

    public static function isStaff(?User $user): bool
    {
        return $user !== null && $user->role !== UserRole::Customer;
    }

    /** Logs a staff member's change request once it has been handled. */
    public function request(Request $request, Response $response): void
    {
        $user = $request->user();
        // Sign-in and sign-out come from the auth events instead (see auth()).
        if (! self::isStaff($user) || $request->isMethodSafe() || $request->routeIs(...self::AUTH_ROUTES)) {
            return;
        }

        $session = $request->hasSession() ? $request->session() : null;
        $outcome = match (true) {
            $response->getStatusCode() === 403 => 'forbidden',
            $response->getStatusCode() >= 500 => 'error',
            $response->getStatusCode() === 422, (bool) $session?->get('errors')?->any() => 'rejected',
            default => 'done',
        };

        $route = $request->route();
        $this->write($user, $route?->getName() ?? $request->path(), [
            'subject' => $this->subject($route?->parameters() ?? []),
            'method' => $request->method(),
            'path' => Str::limit('/'.ltrim($request->path(), '/'), 250, ''),
            'input' => $this->input($request->all()),
            'outcome' => $outcome,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);
    }

    /** Sign-in events for staff accounts. */
    public function auth(string $action, ?User $user, Request $request, ?string $email = null): void
    {
        if ($user && ! self::isStaff($user)) {
            return;
        }

        $this->write($user, $action, [
            'subject' => $email,
            'outcome' => $action === 'auth.failed' ? 'rejected' : 'done',
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ], $email);
    }

    public static function label(string $action): string
    {
        if (isset(self::LABELS[$action])) {
            return self::LABELS[$action];
        }

        // e.g. admin.coupons.update → "Updated coupons"
        $parts = explode('.', $action);
        $verb = array_pop($parts);
        $thing = str_replace('-', ' ', (string) end($parts));

        return match ($verb) {
            'store' => "Created {$thing}",
            'update' => "Updated {$thing}",
            'destroy' => "Deleted {$thing}",
            default => Str::ucfirst(str_replace(['-', '_'], ' ', $verb)).' '.$thing,
        };
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function write(?User $user, string $action, array $attributes, ?string $email = null): void
    {
        AuditLog::create([
            'user_id' => $user?->id,
            'user_name' => $user?->name,
            'user_email' => $user?->email ?? $email,
            'role' => $user?->role->value,
            'action' => Str::limit($action, 100, ''),
            'created_at' => now(),
            ...$attributes,
        ]);
    }

    /**
     * What the action was about, from the route's models: order number, a user's email, or Model #id.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function subject(array $parameters): ?string
    {
        $names = collect($parameters)->map(function (mixed $value) {
            if (! $value instanceof Model) {
                return is_scalar($value) ? (string) $value : null;
            }

            return $value->getAttribute('number') ?? $value->getAttribute('email') ?? $value->getAttribute('code')
                ?? $value->getAttribute('name') ?? class_basename($value).' #'.$value->getKey();
        })->filter()->implode(' / ');

        return $names === '' ? null : Str::limit($names, 250, '');
    }

    /**
     * Submitted fields without secrets; uploaded files as their names; long values shortened.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>|null
     */
    private function input(array $input): ?array
    {
        $clean = Arr::except($input, self::HIDDEN);
        array_walk_recursive($clean, function (mixed &$value) {
            $value = match (true) {
                $value instanceof UploadedFile => 'file: '.$value->getClientOriginalName(),
                is_string($value) => Str::limit($value, 500),
                default => $value,
            };
        });

        return $clean === [] ? null : $clean;
    }
}
