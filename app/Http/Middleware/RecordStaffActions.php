<?php

namespace App\Http\Middleware;

use App\Services\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds every change a staff member makes to the audit log (B-26), once the request is handled.
 */
class RecordStaffActions
{
    public function __construct(private AuditLogger $audit) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // A failed write (e.g. a database error) must never break the request itself.
        rescue(fn () => $this->audit->request($request, $response), report: true);

        return $response;
    }
}
