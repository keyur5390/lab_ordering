<?php

namespace App\Http\Controllers;

use App\Services\PrivacyService;
use App\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Your account → Privacy and data (B-27): download your data, delete your account.
 */
class AccountPrivacyController extends Controller
{
    public function __construct(private PrivacyService $privacy) {}

    public function show(Request $request, Settings $settings): Response
    {
        return Inertia::render('account-privacy', [
            'privacyAcceptedAt' => $request->user()->privacy_accepted_at?->toIso8601String(),
            'cannotDelete' => $this->privacy->cannotDeleteReason($request->user()),
            'privacyContact' => $settings->privacyContact(),
            'timezone' => $settings->timezone(),
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        return response()->json($this->privacy->export($request->user()), options: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', 'attachment; filename="my-data-'.now()->format('Y-m-d').'.json"');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password:web']]);
        $user = $request->user();
        if ($reason = $this->privacy->cannotDeleteReason($user)) {
            throw ValidationException::withMessages(['password' => $reason]);
        }

        $this->privacy->anonymise($user);
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home')->with('status', 'Your account has been deleted.');
    }
}
