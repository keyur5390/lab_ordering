<?php

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Every string the code translates: t(), tx() and plural() in the frontend; __() and
 * trans_choice() on the server.
 *
 * @return list<string>
 */
function translatableKeys(): array
{
    $string = <<<'RE'
    (?:'((?:[^'\\]|\\.)*)'|"((?:[^"\\]|\\.)*)")
    RE;
    $value = fn (array $m, int $i) => stripcslashes(($m[$i] ?? '') !== '' ? $m[$i] : ($m[$i + 1] ?? ''));
    $keys = [];

    foreach (File::allFiles(resource_path('js')) as $file) {
        $source = $file->getContents();
        preg_match_all('/\b(?:t|tx)\(\s*'.$string.'/', $source, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $keys[] = $value($m, 1);
        }
        preg_match_all('/\bplural\(\s*[^,]+,\s*'.$string.'\s*,\s*'.$string.'/', $source, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $keys[] = $value($m, 1);
            $keys[] = $value($m, 3);
        }
    }
    foreach (File::allFiles(app_path()) as $file) {
        preg_match_all('/\b(?:__|trans_choice)\(\s*'.$string.'/', $file->getContents(), $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $keys[] = $value($m, 1);
        }
    }

    return array_values(array_unique($keys));
}

describe('choosing the language', function () {
    it('uses English by default and the browser\'s language when we have it', function () {
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en')->where('i18n.messages', null)->has('i18n.locales', 3));

        $this->withHeader('Accept-Language', 'pl-PL,pl;q=0.9,en;q=0.5')->get(route('home'))
            ->assertSee('<html lang="pl">', false)
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'pl')->where('i18n.messages.Your cart', 'Twój koszyk'));
        $this->withHeader('Accept-Language', 'fr-FR,fr;q=0.9')->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en'));
    });

    it('remembers a chosen language for the session and on the account', function () {
        $this->from(route('catalogue.index'))->post(route('locale.update'), ['locale' => 'es'])->assertRedirect(route('catalogue.index'));
        $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'es'));

        $user = User::factory()->create();
        $this->actingAs($user)->post(route('locale.update'), ['locale' => 'pl']);
        expect($user->fresh()->locale)->toBe('pl');

        // A new session (another device) picks up the account's choice.
        $this->flushSession();
        $this->actingAs($user->fresh())->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'pl'));
    });

    it('only accepts the languages we have', function () {
        $this->post(route('locale.update'), ['locale' => 'fr'])->assertSessionHasErrors('locale');
    });

    it('ignores a saved language that is no longer offered', function () {
        $user = User::factory()->create();
        $user->forceFill(['locale' => 'de'])->save();

        $this->actingAs($user->fresh())->get(route('account'))->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en'));
    });

    it('keeps staff screens in English, with no picker', function () {
        $admin = User::factory()->admin()->create();
        $admin->forceFill(['locale' => 'es'])->save();
        $collector = User::factory()->create(['role' => UserRole::Collector]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en')->where('i18n.locales', [])->where('i18n.messages', null));
        $this->actingAs($collector)->withSession(['locale' => 'pl'])->get(route('collector.index'))
            ->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'en'));
        // The customer side still follows their choice.
        $this->flushSession();
        $this->actingAs($admin)->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('i18n.locale', 'es'));
    });
});

describe('translated server text', function () {
    it('shows error pages in the visitor\'s language', function () {
        $this->withHeader('Accept-Language', 'es')->get('/no-such-page')->assertNotFound()
            ->assertInertia(fn (Assert $page) => $page->component('error')->where('i18n.locale', 'es')->where('i18n.messages.Page not found', 'Página no encontrada'));
    });

    it('drops errors from the last submit when the language changes', function () {
        $this->withSession(['errors' => (new ViewErrorBag)->put('default', new MessageBag(['name' => 'Old']))])
            ->post(route('locale.update'), ['locale' => 'pl'])->assertSessionMissing('errors');
    });

    it('translates flash messages, validation errors and labels', function () {
        $user = User::factory()->create();
        $user->forceFill(['locale' => 'es'])->save();
        $as = $this->actingAs($user->fresh());

        $as->post(route('patients.store'), [])->assertSessionHasErrors(['name' => 'El campo nombre es obligatorio.']);
        $as->post(route('patients.store'), ['name' => 'Ana', 'relationship' => 'child', 'date_of_birth' => '2015-01-01', 'sex' => 'female'])
            ->assertSessionHas('status', 'Paciente añadido.');

        $order = Order::factory()->for($user)->create();
        $as->get(route('orders.show', $order))->assertInertia(fn (Assert $page) => $page->where('order.status_label', 'Confirmado'));
    });

    it('translates the home page\'s launch copy until an admin writes their own', function () {
        $this->withHeader('Accept-Language', 'es')->get(route('home'))
            ->assertInertia(fn (Assert $page) => $page->where('hero.title', 'Reserva pruebas de laboratorio en línea')->where('hero.button', 'Ver pruebas'));
    });

    it('translates the sign-in failure in Polish', function () {
        $this->withSession(['locale' => 'pl'])->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'Nieprawidłowy e-mail lub hasło.']);
    });
});

describe('translations', function () {
    it('has a Spanish and a Polish translation for every string, keeping its placeholders', function (string $locale) {
        $messages = json_decode(File::get(lang_path("{$locale}.json")), true);
        $keys = translatableKeys();
        expect(count($keys))->toBeGreaterThan(300);

        $missing = array_values(array_filter($keys, fn (string $key) => ! isset($messages[$key])));
        expect($missing)->toBe([]);

        foreach ($keys as $key) {
            preg_match_all('/:[a-z_]+/', $key, $placeholders);
            foreach (array_unique($placeholders[0]) as $placeholder) {
                expect($messages[$key])->toContain($placeholder);
            }
        }
    })->with(['es', 'pl']);

    it('gives plurals every form the language needs', function () {
        $es = json_decode(File::get(lang_path('es.json')), true);
        $pl = json_decode(File::get(lang_path('pl.json')), true);

        foreach ([':count days', ':count hours', ':count tests', ':count results', ':count years', 'Cart, :count items', ':count items are no longer available and not included.'] as $many) {
            expect(explode('|', $es[$many]))->toHaveCount(2)
                ->and(explode('|', $pl[$many]))->toHaveCount(3);
        }
    });

    it('has no translations left over from removed text', function (string $locale) {
        $messages = json_decode(File::get(lang_path("{$locale}.json")), true);

        expect(array_values(array_diff(array_keys($messages), translatableKeys())))->toBe([]);
    })->with(['es', 'pl']);
});
