<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Pages → FAQs (B-28): questions shown on the home and FAQ pages, in order.
 */
class FaqController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/pages/faqs', [
            'faqs' => Faq::ordered()->get()->map->only(['id', 'question', 'answer']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Faq::create([...$this->validated($request), 'position' => (int) Faq::max('position') + 1]);

        return to_route('admin.pages.faqs.index')->with('status', 'Question added.');
    }

    public function update(Request $request, Faq $faq): RedirectResponse
    {
        $faq->update($this->validated($request));

        return to_route('admin.pages.faqs.index')->with('status', 'Question saved.');
    }

    /** Swaps the question with its neighbour above or below. */
    public function move(Request $request, Faq $faq): RedirectResponse
    {
        $direction = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]])['direction'];

        DB::transaction(function () use ($faq, $direction) {
            // Renumber first so positions are distinct, then swap.
            $faqs = Faq::ordered()->lockForUpdate()->get()->values();
            $faqs->each(fn (Faq $item, int $index) => $item->position === $index ? null : $item->update(['position' => $index]));

            $index = $faqs->search(fn (Faq $item) => $item->is($faq));
            $other = $faqs->get($direction === 'up' ? $index - 1 : $index + 1);
            if ($other) {
                [$a, $b] = [$faqs[$index]->position, $other->position];
                $faqs[$index]->update(['position' => $b]);
                $other->update(['position' => $a]);
            }
        });

        return to_route('admin.pages.faqs.index');
    }

    public function destroy(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return to_route('admin.pages.faqs.index')->with('status', 'Question deleted.');
    }

    /**
     * @return array{question: string, answer: string}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:5000'],
        ]);
    }
}
