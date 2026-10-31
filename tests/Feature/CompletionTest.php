<?php

use App\Enums\CollectionPaymentMethod;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\LabService;
use App\Services\PaymentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->lab = User::factory()->create(['role' => UserRole::LabStaff]);
    $this->customer = User::factory()->create();
});

/** An order at the lab with one item per patient name. */
function atLab(array $attributes = [], array $patients = ['Asha Rao']): Order
{
    $order = Order::factory()->for(test()->customer)->create(['status' => OrderStatus::ReceivedAtLab, ...$attributes]);
    foreach ($patients as $patient) {
        $order->items()->create([
            'patient_name' => $patient, 'patient_date_of_birth' => '1990-01-01', 'patient_sex' => 'female',
            'item_type' => 'test', 'name' => 'Complete Blood Count', 'code' => 'CBC', 'price_amount' => 40000,
        ]);
    }

    return $order;
}

function uploadReportFor(OrderItem $item): void
{
    app(LabService::class)->uploadReport($item, UploadedFile::fake()->create('r.pdf', 10, 'application/pdf'), test()->lab);
}

describe('completion', function () {
    it('completes a paid online order when its last report arrives', function () {
        $order = atLab(['payment_method' => PaymentMethod::Online], ['Asha Rao', 'Ravi Rao']);
        Payment::factory()->paid()->for($order)->create();

        uploadReportFor($order->items[0]);
        expect($order->fresh()->status)->toBe(OrderStatus::ReceivedAtLab);

        uploadReportFor($order->items[1]);
        expect($order->fresh()->status)->toBe(OrderStatus::Completed)
            ->and($order->fresh()->completed_at)->not->toBeNull();
    });

    it('waits for a pay-at-collection payment, then completes', function () {
        $order = atLab();
        uploadReportFor($order->items[0]);
        expect($order->fresh()->status)->toBe(OrderStatus::ReportReady);

        app(PaymentService::class)->recordCollectedPayment($order->fresh(), CollectionPaymentMethod::Cash);
        expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    });

    it('completes when the reports arrive after the payment', function () {
        $order = atLab(['payment_collected_at' => now(), 'payment_collected_method' => CollectionPaymentMethod::Upi]);

        uploadReportFor($order->items[0]);

        expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    });

    it('lets the lab correct reports after completion, and finds completed orders', function () {
        $order = atLab(['payment_collected_at' => now()]);
        uploadReportFor($order->items[0]);
        $first = $order->items[0]->fresh()->report_path;

        uploadReportFor($order->items[0]->fresh());
        expect($order->items[0]->fresh()->report_path)->not->toBe($first)
            ->and($order->fresh()->status)->toBe(OrderStatus::Completed);

        $this->actingAs($this->lab)->get(route('admin.lab.index', ['status' => 'completed']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
        $this->actingAs($this->lab)->get(route('admin.lab.show', $order))->assertOk();
    });
});

describe('my reports', function () {
    it('lists the customer\'s reports across orders, newest first, with a patient filter', function () {
        $older = atLab(['payment_collected_at' => now()], ['Asha Rao']);
        uploadReportFor($older->items[0]);
        $this->travel(1)->day();
        $newer = atLab([], ['Asha Rao', 'Ravi Rao']);
        uploadReportFor($newer->items[1]);
        // Someone else's report isn't listed.
        $other = Order::factory()->create(['status' => OrderStatus::ReceivedAtLab]);
        $other->items()->create(['patient_name' => 'Zed', 'patient_date_of_birth' => '1990-01-01', 'patient_sex' => 'male', 'item_type' => 'test', 'name' => 'X', 'code' => 'X', 'price_amount' => 1]);
        uploadReportFor($other->items[0]);

        $this->actingAs($this->customer)->get(route('reports.index'))
            ->assertInertia(fn (Assert $page) => $page->component('reports/index')
                ->where('patients', ['Asha Rao', 'Ravi Rao'])
                ->has('reports', 2)
                ->where('reports.0.patient_name', 'Ravi Rao')
                ->where('reports.0.order_number', $newer->number)
                ->where('reports.0.url', route('orders.reports.show', [$newer, $newer->items[1]]))
                ->where('reports.1.order_number', $older->number));

        $this->actingAs($this->customer)->get(route('reports.index', ['patient' => 'Asha Rao']))
            ->assertInertia(fn (Assert $page) => $page->has('reports', 1)->where('reports.0.patient_name', 'Asha Rao')->where('patient', 'Asha Rao'));
    });

    it('needs sign-in and shows an empty state', function () {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
        $this->actingAs($this->customer)->get(route('reports.index'))->assertInertia(fn (Assert $page) => $page->has('reports', 0));
    });
});
