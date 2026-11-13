<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The language picker (B-32): remembered for the session, and on the account when signed in.
 */
class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate(['locale' => ['required', Rule::in(SetLocale::supported())]])['locale'];

        $request->session()->put(SetLocale::SESSION_KEY, $locale);
        // Errors from the page's last submit would stay in the old language.
        $request->session()->forget('errors');
        $request->user()?->forceFill(['locale' => $locale])->save();

        return back();
    }
}
