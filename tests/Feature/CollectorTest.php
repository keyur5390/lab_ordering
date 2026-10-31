<?php

use App\Enums\CollectionPaymentMethod;
use App\Enums\NotificationChannel;
use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Enums\VisitFailureReason;
use App\Models\Order;
use App\Models\ServiceArea;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\CollectorService;
use App\Services\NotificationTemplateService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Notification::fake();
    $settings = app(Settings::class);
    $settings->setTimezone('Asia/Kolkata');
    $settings->setAdminNotificationEmail('ops@example.com');
    $settings->setCancellationCutoffHours(2);
    // Thursday 29 Oct 2026, 06:00 in Kolkata.
    $this->travelTo(CarbonImmutable::parse('2026-10-29 06:00', 'Asia/Kolkata'));

    $this->area = ServiceArea::factory()->scheduled(capacity: 3)->create(['name' => 'Pune']);
    $this->otherArea = ServiceArea::factory()->scheduled()->create(['name' => 'Mumbai']);
    $this->collector = collectorIn($this->area, 'Ravi Kumar');
    $this->outsider = collectorIn($this->otherArea, 'Sam Shah');

    $this->customer = User::factory()->create(['name' => 'Asha Rao']);
    $this->customer->patients()->sole()->update(['phone' => '+91 98123 45678']);
    // Friday 30 Oct, 09:00 Kolkata.
    $this->order = Order::factory()->for($this->customer)->for($this->area)
        ->at(CarbonImmutable::parse('2026-10-30 03:30', 'UTC'))
        ->create(['address' => ['line1' => '4 FC Road', 'city' => 'Pune', 'postcode' => '411004', 'country' => 'IN', 'latitude' => 18.52, 'longitude' => 73.84]]);
    $this->order->items()->create([
        'patient_name' => 'Asha Rao', 'patient_date_of_birth' => '1990-01-01', 'patient_sex' => 'female',
        'item_type' => 'test', 'name' => 'Complete Blood Count', 'code' => 'CBC', 'price_amount' => 50000,
    ]);
    $this->admin = User::factory()->admin()->create();
});

function collectorIn(ServiceArea $area, string $name = 'Collector'): User
{
    $collector = User::factory()->create(['name' => $name, 'role' => UserRole::Collector]);
    $collector->collectorAreas()->attach($area);

    return $collector;
}

function assignTo(User $collector): void
{
    app(CollectorService::class)->assign(test()->order, $collector);
}

function collectorEvents(object $notifiable): array
{
    return Notification::sent($notifiable, OrderNotification::class)->map(fn (OrderNotification $n) => $n->event)->all();
}

describe('access', function () {
    it('gives collectors their visits screen and nothing else', function () {
        $this->actingAs($this->collector)->get(route('collector.index'))->assertOk();
        $this->actingAs($this->collector)->get(route('admin.orders.index'))->assertForbidden();
        $this->actingAs($this->collector)->get(route('admin.lab.index'))->assertForbidden();
        $this->actingAs($this->collector)->get(route('orders.show', $this->order))->assertForbidden();
        $this->actingAs($this->collector)->get(route('account'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.can_access_collector', true)->where('auth.user.can_access_lab', false));

        foreach ([$this->customer, $this->admin, User::factory()->create(['role' => UserRole::LabStaff])] as $user) {
            $this->actingAs($user)->get(route('collector.index'))->assertForbidden();
        }
    });

    it('lets admins choose each collector\'s areas', function () {
        $this->actingAs($this->admin)->get(route('admin.collectors.index'))
            ->assertInertia(fn (Assert $page) => $page->component('admin/collectors/index')->has('collectors', 2)->has('areas', 2)
                ->where('collectors.0.name', 'Ravi Kumar')->where('collectors.0.area_ids', [$this->area->id]));

        $this->actingAs($this->admin)->put(route('admin.collectors.update', $this->collector), ['area_ids' => [$this->area->id, $this->otherArea->id]])
            ->assertRedirect(route('admin.collectors.index'));
        expect($this->collector->collectorAreas()->pluck('service_areas.id')->sort()->values()->all())->toBe([$this->area->id, $this->otherArea->id]);

        $this->actingAs($this->admin)->put(route('admin.collectors.update', $this->collector), ['area_ids' => []]);
        expect($this->collector->collectorAreas()->count())->toBe(0);

        $this->actingAs($this->admin)->put(route('admin.collectors.update', $this->customer), ['area_ids' => []])->assertNotFound();
        $this->actingAs($this->collector)->get(route('admin.collectors.index'))->assertForbidden();
    });
});

describe('assigning', function () {
    it('lets admins assign a collector from the order\'s area and tells the customer', function () {
        $this->actingAs($this->admin)->get(route('admin.orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page->where('collector.can_assign', true)->where('collector.options', [['id' => $this->collector->id, 'name' => 'Ravi Kumar']]));

        $this->actingAs($this->admin)->put(route('admin.orders.collector.update', $this->order), ['collector_id' => $this->outsider->id])
            ->assertSessionHasErrors('collector_id');
        $this->actingAs($this->admin)->put(route('admin.orders.collector.update', $this->order), ['collector_id' => $this->customer->id])
            ->assertSessionHasErrors('collector_id');

        $this->actingAs($this->admin)->put(route('admin.orders.collector.update', $this->order), ['collector_id' => $this->collector->id])
            ->assertRedirect(route('admin.orders.show', $this->order));

        expect($this->order->fresh()->status)->toBe(OrderStatus::CollectorAssigned)
            ->and($this->order->fresh()->collector_id)->toBe($this->collector->id)
            ->and(collectorEvents($this->customer))->toBe([NotificationEvent::CollectorAssigned]);
        $this->actingAs($this->customer)->get(route('orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page->where('order.collector_first_name', 'Ravi'));
    });

    it('tells the customer again only when the collector changes, and can remove the collector', function () {
        $second = collectorIn($this->area, 'Meera Iyer');
        assignTo($this->collector);
        assignTo($this->collector);
        assignTo($second);

        expect(collectorEvents($this->customer))->toBe([NotificationEvent::CollectorAssigned, NotificationEvent::CollectorAssigned]);

        $this->actingAs($this->admin)->put(route('admin.orders.collector.update', $this->order), ['collector_id' => null]);
        expect($this->order->fresh()->only(['status', 'collector_id']))->toBe(['status' => OrderStatus::Confirmed, 'collector_id' => null]);
    });

    it('stops assignment once the sample is collected, and keeps it admin-only', function () {
        $this->order->update(['status' => OrderStatus::SampleCollected]);
        $this->actingAs($this->admin)->put(route('admin.orders.collector.update', $this->order), ['collector_id' => $this->collector->id])
            ->assertSessionHasErrors('collector_id');

        $this->order->update(['status' => OrderStatus::Confirmed]);
        $this->actingAs(User::factory()->create(['role' => UserRole::LabStaff]))
            ->put(route('admin.orders.collector.update', $this->order), ['collector_id' => $this->collector->id])->assertForbidden();
        $this->actingAs($this->collector)
            ->put(route('admin.orders.collector.update', $this->order), ['collector_id' => $this->collector->id])->assertForbidden();
    });
});

describe('the collector\'s day', function () {
    it('lists unassigned visits in their areas without who or where, and lets them take one', function () {
        $page = fn (User $user) => $this->actingAs($user)->get(route('collector.index', ['date' => '2026-10-30']));

        $page($this->collector)->assertInertia(fn (Assert $page) => $page->component('collector/index')
            ->has('visits', 0)
            ->has('claimable', 1)
            ->where('claimable.0.number', $this->order->number)
            ->where('claimable.0.area', 'Pune')
            ->missing('claimable.0.address')
            ->missing('claimable.0.phone'));
        $page($this->outsider)->assertInertia(fn (Assert $page) => $page->has('claimable', 0));

        $this->actingAs($this->outsider)->post(route('collector.claim', $this->order))->assertSessionHasErrors('order');
        $this->actingAs($this->collector)->post(route('collector.claim', $this->order))
            ->assertRedirect(route('collector.index', ['date' => '2026-10-30']));
        expect($this->order->fresh()->collector_id)->toBe($this->collector->id)
            ->and(collectorEvents($this->customer))->toBe([NotificationEvent::CollectorAssigned]);

        // Already taken.
        $this->actingAs(collectorIn($this->area))->post(route('collector.claim', $this->order))->assertSessionHasErrors('order');
    });

    it('shows their own visits with the address, map, phone and tests', function () {
        assignTo($this->collector);

        $this->actingAs($this->collector)->get(route('collector.index', ['date' => '2026-10-30']))
            ->assertInertia(fn (Assert $page) => $page->has('visits', 1)->has('claimable', 0)
                ->where('visits.0.address.line1', '4 FC Road')
                ->where('visits.0.map_url', 'https://www.openstreetmap.org/?mlat=18.52&mlon=73.84#map=18/18.52/73.84')
                ->where('visits.0.phone', '+919812345678')
                ->where('visits.0.patients.0.tests', ['Complete Blood Count'])
                ->where('visits.0.pay_at_collection', true));

        // Other days are empty.
        $this->actingAs($this->collector)->get(route('collector.index'))->assertInertia(fn (Assert $page) => $page->where('date', '2026-10-29')->has('visits', 0));
        $this->actingAs($this->outsider)->get(route('collector.index', ['date' => '2026-10-30']))->assertInertia(fn (Assert $page) => $page->has('visits', 0));
    });

    it('lets only the assigned collector mark the sample collected', function () {
        assignTo($this->collector);

        $this->actingAs($this->outsider)->post(route('collector.collected', $this->order))->assertForbidden();
        $this->actingAs($this->collector)->post(route('collector.collected', $this->order))->assertSessionHasNoErrors();

        expect($this->order->fresh()->status)->toBe(OrderStatus::SampleCollected)
            ->and($this->order->fresh()->collected_at)->not->toBeNull();
    });

    it('records a failed visit, alerts admins, and clears it when the order is rescheduled', function () {
        assignTo($this->collector);

        $this->actingAs($this->collector)->post(route('collector.failed', $this->order), ['reason' => 'other'])->assertSessionHasErrors('note');
        $this->actingAs($this->collector)->post(route('collector.failed', $this->order), ['reason' => 'not_home', 'note' => 'Rang twice'])
            ->assertSessionHasNoErrors();

        expect($this->order->fresh()->visit_failure_reason)->toBe(VisitFailureReason::NotHome)
            ->and($this->order->fresh()->visit_failure_note)->toBe('Rang twice');
        Notification::assertSentOnDemand(OrderNotification::class, fn (OrderNotification $n) => $n->event === NotificationEvent::AdminVisitFailed);
        $this->actingAs($this->admin)->get(route('admin.orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page->where('collector.visit_failed.reason', 'Nobody at home')->where('collector.visit_failed.note', 'Rang twice'));

        $this->actingAs($this->admin)->put(route('admin.orders.reschedule', $this->order), ['slot_starts_at' => '2026-10-31T10:00:00+05:30']);
        expect($this->order->fresh()->visit_failed_at)->toBeNull();
    });

    it('records payment for pay-at-collection orders, once', function () {
        assignTo($this->collector);

        $this->actingAs($this->collector)->post(route('collector.payment', $this->order), ['method' => 'cheque'])->assertSessionHasErrors('method');
        $this->actingAs($this->collector)->post(route('collector.payment', $this->order), ['method' => 'upi'])->assertSessionHasNoErrors();
        expect($this->order->fresh()->payment_collected_method)->toBe(CollectionPaymentMethod::Upi);

        $this->actingAs($this->collector)->post(route('collector.payment', $this->order), ['method' => 'cash'])->assertSessionHasErrors('method');
        $this->actingAs($this->admin)->get(route('admin.orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page->where('order.payment_collected.method', 'UPI'));
    });

    it('has no payment to collect for orders paid online', function () {
        $this->order->update(['payment_method' => PaymentMethod::Online]);
        assignTo($this->collector);

        $this->actingAs($this->collector)->post(route('collector.payment', $this->order), ['method' => 'cash'])->assertSessionHasErrors('method');
    });
});

it('fills the collector placeholders in the wording', function () {
    assignTo($this->collector);
    $this->order->update(['visit_failure_reason' => VisitFailureReason::Refused, 'visit_failure_note' => 'Not fasting']);
    $render = fn (NotificationEvent $event) => app(NotificationTemplateService::class)->render($event, NotificationChannel::Mail, $this->order->fresh())['body'];

    expect($render(NotificationEvent::CollectorAssigned))->toContain('Ravi will visit 4 FC Road')
        ->and($render(NotificationEvent::AdminVisitFailed))->toContain("Reason: Patient refused or wasn't ready. Not fasting");
});
