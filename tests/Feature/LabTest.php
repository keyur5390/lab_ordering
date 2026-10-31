<?php

use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Enums\UserRole;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\OrderNotification;
use App\Services\LabService;
use App\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('local');
    app(Settings::class)->setTimezone('Asia/Kolkata');
    $this->travelTo(CarbonImmutable::parse('2026-10-29 06:00', 'Asia/Kolkata'));

    $this->lab = User::factory()->create(['role' => UserRole::LabStaff]);
    $this->customer = User::factory()->create();
    $this->order = Order::factory()->for($this->customer)->create();
    // Two patients: one test each.
    $this->first = labItem($this->order, 'Asha Rao', 'Complete Blood Count');
    $this->second = labItem($this->order, 'Ravi Rao', 'Lipid Profile');
});

function labItem(Order $order, string $patient, string $name): OrderItem
{
    return $order->items()->create([
        'patient_name' => $patient,
        'patient_date_of_birth' => '1980-01-01',
        'patient_sex' => 'female',
        'item_type' => 'test',
        'name' => $name,
        'code' => strtoupper(substr($name, 0, 3)),
        'price_amount' => 40000,
    ]);
}

function pdf(string $name = 'report.pdf', int $kilobytes = 50): UploadedFile
{
    return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
}

function upload(OrderItem $item, ?UploadedFile $file = null)
{
    return test()->actingAs(test()->lab)->post(route('admin.lab.reports.store', [$item->order, $item]), ['report' => $file ?? pdf()]);
}

describe('access', function () {
    it('gives lab staff the lab and read-only orders, and nothing else in the admin area', function () {
        $as = $this->actingAs($this->lab);

        $as->get(route('admin.lab.index'))->assertOk();
        $as->get(route('admin.lab.show', $this->order))->assertOk();
        $as->get(route('admin.orders.index'))->assertOk();
        $as->get(route('admin.orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page->where('order.can_cancel', false)->where('order.can_reschedule', false)->where('order.can_refund', false));

        $as->get(route('admin.dashboard'))->assertForbidden();
        $as->get(route('admin.settings.edit'))->assertForbidden();
        $as->get(route('admin.tests.index'))->assertForbidden();
        $as->post(route('admin.orders.cancel', $this->order))->assertForbidden();
        $as->post(route('admin.orders.refunds.store', $this->order), ['amount' => '1'])->assertForbidden();
    });

    it('keeps customers out of the lab and lets admins in', function () {
        $this->actingAs($this->customer)->get(route('admin.lab.index'))->assertForbidden();
        $this->actingAs($this->customer)->post(route('admin.lab.collect', $this->order))->assertForbidden();
        upload($this->first)->assertRedirect();
        $this->actingAs($this->customer)->post(route('admin.lab.reports.store', [$this->order, $this->first]), ['report' => pdf()])->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())->get(route('admin.lab.index'))->assertOk();
    });

    it('tells the frontend who may use the lab', function () {
        $this->actingAs($this->lab)->get(route('account'))
            ->assertInertia(fn (Assert $page) => $page->where('auth.user.can_access_lab', true)->where('auth.user.can_access_admin', false));
    });

    it('can be granted with the user:role command', function () {
        $this->artisan('user:role', ['email' => $this->customer->email, 'role' => 'lab_staff'])->assertSuccessful();

        expect($this->customer->fresh()->role)->toBe(UserRole::LabStaff);
    });
});

describe('lab pages', function () {
    it('lists only orders the lab works on, with filters and search', function () {
        Order::factory()->status(OrderStatus::PendingPayment)->create();
        Order::factory()->status(OrderStatus::Cancelled)->create();
        $received = Order::factory()->status(OrderStatus::ReceivedAtLab)->create();

        $this->actingAs($this->lab)->get(route('admin.lab.index'))
            ->assertInertia(fn (Assert $page) => $page->component('lab/index')->has('orders.data', 2)->has('orders.meta')
                ->where('orders.data.0.items_count', 2)->where('orders.data.0.reports_count', 0));
        $this->actingAs($this->lab)->get(route('admin.lab.index', ['status' => 'received_at_lab']))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.number', $received->number));
        $this->actingAs($this->lab)->get(route('admin.lab.index', ['q' => strtolower($this->order->number)]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.number', $this->order->number));
        $this->actingAs($this->lab)->get(route('admin.lab.index', ['status' => 'cancelled']))->assertSessionHasErrors('status');
    });

    it('shows patients and tests but no address or payment', function () {
        $this->actingAs($this->lab)->get(route('admin.lab.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page->component('lab/show')
                ->has('order.items', 2)
                ->where('order.items.0.patient_name', 'Asha Rao')
                ->where('order.can_mark_collected', true)
                ->where('order.can_upload_reports', false)
                ->missing('order.address')
                ->missing('order.total_amount')
                ->missing('order.customer'));
    });

    it('does not show unpaid or cancelled orders', function (OrderStatus $status) {
        $this->actingAs($this->lab)->get(route('admin.lab.show', Order::factory()->status($status)->create()))->assertNotFound();
    })->with([OrderStatus::PendingPayment, OrderStatus::Cancelled]);
});

describe('status steps', function () {
    it('moves from confirmed to collected to received, one step at a time', function () {
        $this->actingAs($this->lab)->post(route('admin.lab.receive', $this->order))->assertSessionHasErrors('status');

        $this->actingAs($this->lab)->post(route('admin.lab.collect', $this->order))->assertRedirect(route('admin.lab.show', $this->order));
        expect($this->order->fresh()->status)->toBe(OrderStatus::SampleCollected)
            ->and($this->order->fresh()->collected_at)->not->toBeNull();

        $this->actingAs($this->lab)->post(route('admin.lab.collect', $this->order))->assertSessionHasErrors('status');
        $this->actingAs($this->lab)->post(route('admin.lab.receive', $this->order));
        expect($this->order->fresh()->status)->toBe(OrderStatus::ReceivedAtLab)
            ->and($this->order->fresh()->received_at_lab_at)->not->toBeNull();
    });

    it('stops customers cancelling once the sample is collected', function () {
        app(Settings::class)->setCancellationCutoffHours(1);
        app(LabService::class)->markCollected($this->order);

        $this->actingAs($this->customer)->post(route('orders.cancel', $this->order))->assertForbidden();
    });
});

describe('reports', function () {
    beforeEach(fn () => $this->order->update(['status' => OrderStatus::ReceivedAtLab]));

    it('only takes PDFs, and only once the sample is at the lab', function () {
        upload($this->first, UploadedFile::fake()->create('report.docx', 10))->assertSessionHasErrors('report');
        upload($this->first, pdf(kilobytes: 20481))->assertSessionHasErrors('report');

        $this->order->update(['status' => OrderStatus::SampleCollected]);
        upload($this->first)->assertSessionHasErrors('report');
        expect($this->first->fresh()->report_path)->toBeNull();
    });

    it('stores reports privately, replaces them, and marks the order ready when all are in', function () {
        Notification::fake();

        upload($this->first, pdf('cbc.pdf'))->assertSessionHasNoErrors();
        $old = $this->first->fresh()->report_path;
        expect($this->order->fresh()->status)->toBe(OrderStatus::ReceivedAtLab)
            ->and($this->first->fresh()->report_uploaded_by)->toBe($this->lab->id);
        Storage::disk('local')->assertExists($old);
        Notification::assertNothingSent();

        upload($this->first, pdf('cbc-v2.pdf'));
        Storage::disk('local')->assertMissing($old);
        expect($this->first->fresh()->report_name)->toBe('cbc-v2.pdf');

        upload($this->second)->assertSessionHas('status', fn (string $status) => str_contains($status, 'customer has been told'));
        expect($this->order->fresh()->status)->toBe(OrderStatus::ReportReady)
            ->and($this->order->fresh()->report_ready_at)->not->toBeNull();
        Notification::assertSentToTimes($this->customer, OrderNotification::class, 1);
        Notification::assertSentTo($this->customer, OrderNotification::class, fn (OrderNotification $n) => $n->event === NotificationEvent::ReportReady);

        // Corrections after that don't notify again.
        upload($this->second, pdf('lipid-corrected.pdf'))->assertSessionHasNoErrors();
        Notification::assertSentToTimes($this->customer, OrderNotification::class, 1);
    });

    it('lets the customer, lab staff and admins download reports, and nobody else', function () {
        upload($this->first);
        $url = route('orders.reports.show', [$this->order, $this->first]);

        $this->actingAs($this->customer)->get(route('orders.show', $this->order))
            ->assertInertia(fn (Assert $page) => $page->where('order.patients.0.items.0.report_url', $url)
                ->where('order.patients.1.items.0.report_url', null));
        $this->actingAs($this->customer)->get($url)->assertOk()->assertDownload('Asha Rao - Complete Blood Count.pdf');
        $this->actingAs($this->lab)->get($url)->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($this->customer)->get(route('orders.reports.show', [$this->order, $this->second]))->assertNotFound();
        $other = Order::factory()->for($this->customer)->create();
        $this->actingAs($this->customer)->get(route('orders.reports.show', [$other, $this->first]))->assertNotFound();
    });

    it('lets lab staff open the prescription', function () {
        Storage::disk('local')->put('prescriptions/rx.pdf', 'pdf');
        $this->order->update(['prescription_path' => 'prescriptions/rx.pdf', 'prescription_name' => 'rx.pdf']);

        $this->actingAs($this->lab)->get(route('orders.prescription', $this->order))->assertOk();
    });

    it('attaches the reports to the "ready" email within the size limit', function () {
        upload($this->first);
        upload($this->second);
        $mail = fn () => (new OrderNotification(NotificationEvent::ReportReady, $this->order->fresh()))->toMail($this->customer);

        expect($mail()->subject)->toBe("Your reports for order {$this->order->number} are ready")
            ->and(collect($mail()->attachments)->pluck('options.as')->all())->toBe(['Asha Rao - Complete Blood Count.pdf', 'Ravi Rao - Lipid Profile.pdf']);

        // Too big to attach: the email keeps its link to the order.
        upload($this->second, UploadedFile::fake()->createWithContent('big.pdf', str_repeat('a', OrderNotification::REPORT_ATTACHMENT_LIMIT_BYTES + 1)));
        expect($mail()->attachments)->toBe([])
            ->and($mail()->actionUrl)->toBe(route('orders.show', $this->order));
    });
});
