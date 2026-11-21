<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Pages: the site copy admins write (privacy policy, terms).
 */
class PageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/pages/index', [
            'pages' => Page::orderBy('title')->get()->map(fn (Page $page) => [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->title,
                'url' => route('pages.show', $page->slug),
                'updated_at' => $page->updated_at?->toIso8601String(),
            ]),
        ]);
    }

    public function edit(Page $page): Response
    {
        return Inertia::render('admin/pages/edit', [
            'page' => ['id' => $page->id, 'title' => $page->title, 'body' => $page->body, 'url' => route('pages.show', $page->slug)],
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $page->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:100000'],
        ]));

        return to_route('admin.pages.index')->with('status', "“{$page->title}” saved.");
    }
}
