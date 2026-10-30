<?php

use App\Enums\CollectionType;
use App\Enums\Currency;
use App\Enums\OrderStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\ServiceArea;
use App\Models\User;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->settings = app(Settings::class);
    $this->settings->setCurrency(Currency::INR);
    $this->settings->setTimezone('Asia/Kolkata');
    $this->settings->setAdminNotificationEmail('ops@example.com');
    $this->settings->setCancellationCutoffHours(12);
    // Thursday 29 Oct 2026, 10:00 in Kolkata.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 10:00', 'Asia/Kolkata'));
    $this->admin = User::factory()->admin()->create();
    $this->area = ServiceArea::factory()->scheduled()->create();
});

/** An order with its slot at a Kolkata time. */
function orderAt(string $kolkata, array $attributes = []): Order
{
    return Order::factory()->for(test()->area)->at(CarbonImmutable::parse($kolkata, 'Asia/Kolkata')->utc())->create($attributes);
}

function dashboard(array $query = [])
{
    return test()->actingAs(test()->admin)->get(route('admin.dashboard', $query));
}

it('counts today\'s work', function () {
    orderAt('2026-10-29 11:00');
    orderAt('2026-10-29 12:00', ['status' => OrderStatus::Cancelled]);
    orderAt('2026-10-29 13:00', ['collection_type' => CollectionType::LabVisit, 'service_area_id' => null, 'branch_id' => Branch::factory()->create()->id]);
    // Tomorrow, unassigned; the day after doesn't count.
    orderAt('2026-10-30 09:00');
    orderAt('2026-10-31 09:00');
    orderAt('2026-10-29 15:00', ['status' => OrderStatus::CollectorAssigned, 'collector_id' => User::factory()->create(['role' => UserRole::Collector])->id, 'visit_failed_at' => now()]);
    orderAt('2026-10-28 09:00', ['status' => OrderStatus::ReceivedAtLab]);

    dashboard()->assertInertia(fn (Assert $page) => $page->component('admin/dashboard')
        ->where('today', ['home_collections' => 2, 'lab_visits' => 1, 'unassigned' => 2, 'failed_visits' => 1, 'awaiting_reports' => 1])
        ->where('statuses.1', ['status' => 'confirmed', 'label' => 'Confirmed', 'count' => 4])
        ->where('statuses.7', ['status' => 'cancelled', 'label' => 'Cancelled', 'count' => 1]));
});

it('adds up money for the chosen period in the site currency', function () {
    $paid = fn (string $at, int $amount, string $currency = 'INR') => Payment::factory()->paid()->create(['paid_at' => CarbonImmutable::parse($at, 'Asia/Kolkata'), 'amount' => $amount, 'currency' => $currency]);
    $today = $paid('2026-10-29 08:00', 50000);
    $paid('2026-10-25 08:00', 30000);
    $paid('2026-09-01 08:00', 99900);
    $paid('2026-10-29 08:00', 77700, 'USD');
    orderAt('2026-10-29 09:00', ['payment_collected_at' => now(), 'total_amount' => 40000]);
    Refund::factory()->for($today)->create(['amount' => 10000, 'created_at' => now()]);
    Refund::factory()->for($today)->create(['amount' => 5000, 'status' => RefundStatus::Failed, 'created_at' => now()]);

    dashboard()->assertInertia(fn (Assert $page) => $page->where('period', 'today')
        ->where('money', ['currency' => 'INR', 'paid_online' => 50000, 'paid_at_collection' => 40000, 'refunded' => 10000, 'net' => 80000]));
    dashboard(['period' => '7d'])->assertInertia(fn (Assert $page) => $page->where('money.paid_online', 80000));
    dashboard(['period' => 'year'])->assertSessionHasErrors('period');
});

it('flags what needs attention', function () {
    dashboard()->assertInertia(fn (Assert $page) => $page->where('alerts', []));

    $this->settings->setAdminNotificationEmail(null);
    $this->area->update(['slot_minutes' => null]);
    $cancelled = Order::factory()->status(OrderStatus::Cancelled)->create();
    Refund::factory()->for(Payment::factory()->paid()->for($cancelled)->create(['amount' => 50000]))->create(['status' => RefundStatus::Failed, 'amount' => 50000]);
    orderAt('2026-10-30 09:00', ['status' => OrderStatus::PendingPayment, 'payment_expires_at' => now()->addMinutes(5)]);

    dashboard()->assertInertia(fn (Assert $page) => $page
        ->where('alerts.0.message', 'No published service area or branch has a schedule, so there are no slots to book.')
        ->where('alerts.1.message', '1 cancelled order still needs a refund: the gateway rejected it.')
        ->where('alerts.2.message', '1 unpaid order will be cancelled within 10 minutes.')
        ->where('alerts.3.tone', 'info')
        ->has('alerts', 4));
});

it('is for admins only', function () {
    $this->actingAs(User::factory()->create(['role' => UserRole::LabStaff]))->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
});
