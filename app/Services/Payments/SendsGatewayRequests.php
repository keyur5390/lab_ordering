<?php

namespace App\Services\Payments;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

trait SendsGatewayRequests
{
    /**
     * Sends a request and turns HTTP and connection errors into PaymentGatewayException.
     *
     * @param  Closure(): Response  $request
     * @return array<string, mixed>
     *
     * @throws PaymentGatewayException
     */
    private function send(string $gateway, Closure $request): array
    {
        try {
            return $request()->throw()->json() ?? [];
        } catch (RequestException|ConnectionException $e) {
            Log::warning("{$gateway} request failed", ['error' => $e->getMessage()]);

            throw new PaymentGatewayException("{$gateway} request failed.", previous: $e);
        }
    }
}
