<?php

use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Address;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Page;
use App\Models\Patient;
use App\Models\User;
use App\Services\InvoiceService;
use App\Settings\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    app(Settings::class)->setCurrency(Currency::INR);
    $this->customer = User::factory()->create(['name' => 'Asha Rao', 'email' => 'asha@example.com']);
    Patient::factory()->self()->for($this->customer)->create(['name' => 'Asha Rao', 'phone' => '+919800000001']);
    Address::factory()->for($this->customer)->create(['line1' => '12 MG Road', 'city' => 'Pune']);
    $this->order = Order::factory()->for($this->customer)->status(OrderStatus::Completed)->create([
        'address' => ['label' => 'Home', 'line1' => '12 MG Road', 'line2' => 'Flat 4', 'city' => 'Pune', 'region' => 'MH', 'postcode' => '411001', 'country' => 'IN'],
        'prescription_path' => 'prescriptions/rx.pdf', 'prescription_name' => 'rx.pdf',
    ]);
    Storage::disk('local')->put('prescriptions/rx.pdf', 'pdf');
    $this->item = $this->order->items()->create([
        'patient_name' => 'Asha Rao', 'patient_date_of_birth' => '1990-06-15', 'patient_sex' => 'female',
        'item_type' => 'test', 'name' => 'Complete Blood Count', 'code' => 'CBC', 'price_amount' => 40000,
        'report_path' => 'reports/cbc.pdf', 'report_name' => 'cbc.pdf',
    ]);
    NotificationLog::create(['order_id' => $this->order->id, 'event' => 'order_confirmed', 'channel' => 'mail', 'recipient' => 'asha@example.com', 'status' => 'sent']);
});

function deleteAccount(string $password = 'password')
{
    return test()->actingAs(test()->customer)->delete(route('account.destroy'), ['password' => $password]);
}

describe('consent and pages', function () {
    it('shows the privacy policy and terms, written in Markdown, with raw HTML removed', function () {
        Page::where('slug', 'privacy')->first()->update(['body' => "## Your data\n\nWe keep it **safe**.<script>alert(1)</script>\n\n<div onclick=\"steal()\">hi</div>"]);
        app(Settings::class)->setPrivacyContact(['name' => 'Grievance Officer', 'email' => 'privacy@example.com']);

        $this->get(route('pages.show', 'privacy'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('page')
                ->where('page.title', 'Privacy policy')
                ->where('page.html', fn (string $html) => str_contains($html, '<h2>Your data</h2>') && str_contains($html, '<strong>safe</strong>') && ! str_contains($html, '<script>') && ! str_contains($html, 'onclick'))
                ->where('privacyContact', ['name' => 'Grievance Officer', 'email' => 'privacy@example.com']));
        $this->get(route('pages.show', 'terms'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('privacyContact', null));
        $this->get('/nothing-here')->assertNotFound();
    });

    it('falls back to the admin alert address for the privacy contact', function () {
        app(Settings::class)->setAdminNotificationEmail('ops@example.com');

        expect(app(Settings::class)->privacyContact())->toBe(['name' => null, 'email' => 'ops@example.com']);
    });

    it('lets admins, and only admins, edit pages and the privacy contact', function () {
        $admin = User::factory()->admin()->create();
        $page = Page::where('slug', 'terms')->first();

        $this->actingAs($admin)->get(route('admin.pages.index'))->assertInertia(fn (Assert $p) => $p->component('admin/pages/index')->has('pages', 4));
        $this->actingAs($admin)->put(route('admin.pages.update', $page), ['title' => 'Terms', 'body' => ''])->assertSessionHasErrors('body');
        $this->actingAs($admin)->put(route('admin.pages.update', $page), ['title' => 'Terms', 'body' => 'New terms'])->assertRedirect(route('admin.pages.index'));
        expect($page->fresh()->body)->toBe('New terms');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'currency' => 'INR', 'timezone' => 'UTC', 'privacy_contact_name' => 'DPO', 'privacy_contact_email' => 'DPO@Example.com',
        ])->assertSessionHasNoErrors();
        expect(app(Settings::class)->privacyContact())->toBe(['name' => 'DPO', 'email' => 'dpo@example.com']);

        $this->actingAs(User::factory()->create(['role' => UserRole::LabStaff]))->put(route('admin.pages.update', $page), ['title' => 'x', 'body' => 'x'])->assertForbidden();
        $this->actingAs($this->customer)->get(route('admin.pages.index'))->assertForbidden();
    });
});

describe('download your data', function () {
    it('gives the customer everything we hold about them, and nobody else\'s', function () {
        Order::factory()->create(); // someone else's
        $response = $this->actingAs($this->customer)->get(route('account.privacy.export'))->assertOk();

        expect($response->headers->get('Content-Disposition'))->toContain('attachment; filename="my-data-');
        $data = $response->json();
        expect($data['account']['email'])->toBe('asha@example.com')
            ->and(collect($data['patients'])->pluck('phone'))->toContain('+919800000001')
            ->and($data['addresses'][0]['line1'])->toBe('12 MG Road')
            ->and($data['orders'])->toHaveCount(1)
            ->and($data['orders'][0]['items'][0]['patient'])->toBe('Asha Rao')
            ->and($data['messages'][0]['recipient'])->toBe('asha@example.com');
    });

    it('needs sign-in', function () {
        $this->get(route('account.privacy.export'))->assertRedirect(route('login'));
    });
});

describe('delete your account', function () {
    it('asks for the password', function () {
        deleteAccount('wrong')->assertSessionHasErrors('password');

        expect($this->customer->fresh()->anonymised_at)->toBeNull();
    });

    it('removes personal details and keeps orders, invoices and results without them', function () {
        $invoice = app(InvoiceService::class)->issue($this->order->fresh());

        deleteAccount()->assertRedirect(route('home'))->assertSessionHas('status');
        $this->assertGuest();

        $user = $this->customer->fresh();
        expect($user->name)->toBe('Deleted customer')
            ->and($user->email)->toBe("deleted-{$user->id}@deleted.invalid")
            ->and($user->anonymised_at)->not->toBeNull()
            ->and($user->disabled_at)->not->toBeNull()
            ->and(Patient::where('user_id', $user->id)->count())->toBe(0)
            ->and(Address::where('user_id', $user->id)->count())->toBe(0);

        $order = $this->order->fresh();
        expect($order->address)->toEqual(['label' => null, 'line1' => 'Removed', 'line2' => null, 'city' => 'Pune', 'region' => 'MH', 'postcode' => '', 'country' => 'IN'])
            ->and($order->prescription_path)->toBeNull();
        Storage::disk('local')->assertMissing('prescriptions/rx.pdf');

        $item = $this->item->fresh();
        expect($item->patient_name)->toBe('Removed')
            ->and($item->patient_date_of_birth->toDateString())->toBe('1990-01-01')
            ->and($item->report_path)->toBe('reports/cbc.pdf');

        $invoice = Invoice::find($invoice->id);
        expect($invoice->buyer['name'])->toBe('Removed')
            ->and($invoice->buyer['email'])->toBeNull()
            ->and($invoice->lines[0]['patient'])->toBe('Removed')
            ->and(NotificationLog::sole()->recipient)->toBeNull();

        // The old details no longer sign in.
        $this->post(route('login.store'), ['email' => 'asha@example.com', 'password' => 'password'])->assertSessionHasErrors();
        $this->assertGuest();
    });

    it('removes the email from the audit log of staff actions on the account', function () {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->create(['email' => 'someone@example.com']);
        $this->actingAs($admin)->post(route('admin.users.disable', $this->customer));
        $this->actingAs($admin)->post(route('admin.users.enable', $this->customer));
        $this->actingAs($admin)->post(route('admin.users.disable', $other));
        auth()->guard('web')->logout();

        deleteAccount();

        $subjects = AuditLog::where('action', 'like', 'admin.users.%')->orderBy('id')->pluck('subject')->all();
        expect($subjects)->toBe([
            "deleted-{$this->customer->id}@deleted.invalid",
            "deleted-{$this->customer->id}@deleted.invalid",
            'someone@example.com',
        ]);
    });

    it('frees the email for a new account', function () {
        deleteAccount();

        $this->post(route('register.store'), ['name' => 'Asha', 'email' => 'asha@example.com', 'password' => 'password', 'password_confirmation' => 'password', 'terms' => '1'])
            ->assertSessionHasNoErrors();
    });

    it('waits until orders in progress are finished', function () {
        Order::factory()->for($this->customer)->status(OrderStatus::SampleCollected)->create();

        $this->actingAs($this->customer)->get(route('account.privacy'))
            ->assertInertia(fn (Assert $page) => $page->component('account-privacy')->where('cannotDelete', fn (string $reason) => str_contains($reason, 'order in progress')));
        deleteAccount()->assertSessionHasErrors('password');
        expect($this->customer->fresh()->anonymised_at)->toBeNull();
    });

    it('is not for staff accounts', function () {
        $this->customer->forceFill(['role' => UserRole::Collector])->save();

        deleteAccount()->assertSessionHasErrors('password');
        expect($this->customer->fresh()->anonymised_at)->toBeNull();
    });

    it('lets the customer see the page and when they agreed to the policy', function () {
        $this->customer->forceFill(['privacy_accepted_at' => now()])->save();

        $this->actingAs($this->customer)->get(route('account.privacy'))
            ->assertInertia(fn (Assert $page) => $page->where('cannotDelete', null)->where('privacyAcceptedAt', fn ($at) => $at !== null));
        expect(AuditLog::count())->toBe(0);
    });
});
