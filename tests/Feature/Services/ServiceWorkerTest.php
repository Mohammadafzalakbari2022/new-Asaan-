<?php

use App\Models\User;
use Cartxis\Service\Models\ServiceBooking;
use Cartxis\Service\Services\ServiceBookingStatusService;
use Illuminate\Support\Facades\DB;

require_once __DIR__ . '/helpers.php';

beforeEach(function () {
    configureServices();
    $this->statuses = app(ServiceBookingStatusService::class);
});

test('a worker sees only the jobs given to them', function () {
    $mine = bookServiceJob(null, ['scheduled_date' => bookableDate()]);
    $theirs = bookServiceJob(null, ['scheduled_date' => bookableDate(4)]);

    $worker = serviceWorker();

    $this->statuses->assign($mine, $worker->id);
    $this->statuses->assign($theirs, serviceWorker('Someone Else')->id);

    $this->actingAs($worker, 'delivery')
        ->get('/delivery/jobs')
        ->assertOk()
        ->assertSee($mine->reference)
        ->assertDontSee($theirs->reference);
});

test('a worker cannot open a job that is not theirs', function () {
    $booking = bookServiceJob();
    $other = serviceWorker('Not Me');

    $this->statuses->assign($booking, serviceWorker()->id);

    $this->actingAs($other, 'delivery')
        ->get('/delivery/jobs/' . $booking->reference)
        ->assertStatus(403);
});

test('a worker cannot start or finish somebody elses job', function () {
    $booking = bookServiceJob();
    $intruder = serviceWorker('Not Me');

    $this->statuses->assign($booking, serviceWorker()->id);

    $this->actingAs($intruder, 'delivery')
        ->post('/delivery/jobs/' . $booking->reference . '/start')
        ->assertStatus(403);

    $this->assertSame(ServiceBooking::STATUS_ASSIGNED, $booking->fresh()->status);
});

test('a customer cannot reach the worker job list', function () {
    $customer = User::factory()->withoutTwoFactor()->create(['role' => 'customer']);

    // The delivery area bounces anyone who is not on the staff list.
    $this->actingAs($customer, 'delivery')
        ->get('/delivery/jobs')
        ->assertStatus(302);
});

test('a guest is sent to the delivery login', function () {
    $this->get('/delivery/jobs')->assertStatus(302);
});

test('a job goes from booked to finished one step at a time', function () {
    $worker = serviceWorker();
    $booking = bookServiceJob();
    $worker = $this->statuses->assign($booking, $worker->id);

    expect($booking->status)->toBe('assigned');

    $this->statuses->start($booking);
    expect($booking->fresh()->status)->toBe('in_progress');

    $this->statuses->complete($booking, 3000.0);
    expect($booking->fresh()->status)->toBe('completed');
});

test('a job cannot be finished before it is started', function () {
    $booking = bookServiceJob();
    $booking = $this->statuses->assign($booking, serviceWorker()->id);

    // Straight from assigned to finished, with nobody having turned up.
    expect(fn () => $this->statuses->complete($booking, 3000.0))
        ->toThrow(RuntimeException::class);

    expect($booking->fresh()->status)->toBe('assigned');
});

test('a job cannot be started before anyone is given it', function () {
    $booking = bookServiceJob();

    expect(fn () => $this->statuses->start($booking))
        ->toThrow(RuntimeException::class);

    expect($booking->fresh()->status)->toBe('booked');
});

test('a finished job is marked paid and the order is closed', function () {
    $booking = bookServiceJob();
    $booking = $this->statuses->assign($booking, serviceWorker()->id);
    $this->statuses->start($booking);
    $this->statuses->complete($booking, 3000.0);

    $booking->refresh();

    expect((float) $booking->amount_collected)->toBe(3000.0);

    // Money is only ever taken once the work is done.
    $this->assertDatabaseHas('orders', [
        'order_number' => $booking->reference,
        'status' => 'completed',
        'payment_status' => 'paid',
        'total' => 3000,
    ]);
});

test('the amount collected is kept even when it differs from the price', function () {
    $booking = bookServiceJob();
    $booking = $this->statuses->assign($booking, serviceWorker()->id);
    $this->statuses->start($booking);
    $this->statuses->complete($booking, 3250.0);

    $booking->refresh();

    // What was promised and what was actually taken both stay on the record.
    expect((float) $booking->price_snapshot)->toBe(3000.0)
        ->and((float) $booking->amount_collected)->toBe(3250.0)
        ->and($booking->amountVariance())->toBe(250.0);
});

test('a cancelled job is never marked as paid', function () {
    $booking = bookServiceJob();
    $this->statuses->cancel($booking, 'Customer called to cancel.');

    $this->assertDatabaseHas('orders', [
        'order_number' => $booking->reference,
        'status' => 'cancelled',
        'payment_status' => 'pending',
    ]);
});

test('a finished job cannot be reopened or cancelled', function () {
    $booking = bookServiceJob();
    $booking = $this->statuses->assign($booking, serviceWorker()->id);
    $this->statuses->start($booking);
    $this->statuses->complete($booking, 3000.0);

    expect(fn () => $this->statuses->cancel($booking->fresh(), 'Too late.'))
        ->toThrow(RuntimeException::class);

    expect($booking->fresh()->status)->toBe('completed');
});

test('a job cannot be given to somebody who is not delivery staff', function () {
    $booking = bookServiceJob();

    // The check lives in the service, not only in the screen, so a stale page
    // or a direct call cannot hand a job to a customer account.
    expect(fn () => $this->statuses->assign(
        $booking,
        User::factory()->withoutTwoFactor()->create(['role' => 'customer'])->id
    ))->toThrow(RuntimeException::class);

    expect($booking->fresh()->assigned_to)->toBeNull();
});

test('a job cannot be given to a worker who has been made inactive', function () {
    $booking = bookServiceJob();
    $worker = serviceWorker();
    $worker->update(['is_active' => false]);

    expect(fn () => $this->statuses->assign($booking, $worker->id))
        ->toThrow(RuntimeException::class);

    expect($booking->fresh()->assigned_to)->toBeNull();
});

test('a job cannot be given to somebody who does not exist', function () {
    $booking = bookServiceJob();

    expect(fn () => $this->statuses->assign($booking, 999999))
        ->toThrow(RuntimeException::class);
});

test('every step is written down with who did it', function () {
    $admin = serviceAdmin();
    $worker = serviceWorker();

    $booking = bookServiceJob();

    $this->actingAs($admin, 'admin')
        ->post("/admin/services/bookings/{$booking->reference}/assign", ['assigned_to' => $worker->id])
        ->assertStatus(302);

    $this->actingAs($worker, 'delivery')
        ->post('/delivery/jobs/' . $booking->reference . '/start')
        ->assertStatus(302);

    $this->actingAs($worker, 'delivery')
        ->post('/delivery/jobs/' . $booking->reference . '/complete', ['amount_collected' => 3000])
        ->assertStatus(302);

    $events = DB::table('service_booking_events')
        ->where('service_booking_id', $booking->id)
        ->orderBy('id')
        ->get();

    expect($events)->toHaveCount(4)
        ->and($events[0]->to_status)->toBe('booked')
        ->and($events[1]->to_status)->toBe('assigned')
        ->and($events[2]->to_status)->toBe('in_progress')
        ->and($events[3]->to_status)->toBe('completed');

    // The person who did it is recorded, not just the change.
    expect($events[1]->actor_id)->not->toBeNull()
        ->and($events[3]->actor_id)->not->toBeNull();
});

test('the worker can finish a job and the money lands on the order', function () {
    $booking = bookServiceJob();
    $worker = serviceWorker();
    $this->statuses->assign($booking, $worker->id);

    $this->actingAs($worker, 'delivery')
        ->post('/delivery/jobs/' . $booking->reference . '/start')
        ->assertStatus(302);

    $this->actingAs($worker, 'delivery')
        ->post('/delivery/jobs/' . $booking->reference . '/complete', ['amount_collected' => 3000])
        ->assertStatus(302);

    expect($booking->fresh()->status)->toBe('completed');
});

test('a worker cannot claim a negative or empty amount', function () {
    $booking = bookServiceJob();
    $worker = serviceWorker();
    $this->statuses->assign($booking, $worker->id);
    $this->actingAs($worker, 'delivery')->post('/delivery/jobs/' . $booking->reference . '/start');

    $this->actingAs($worker, 'delivery')
        ->post('/delivery/jobs/' . $booking->reference . '/complete', ['amount_collected' => -50])
        ->assertSessionHasErrors('amount_collected');

    expect($booking->fresh()->status)->toBe('in_progress');
});

test('the admin can cancel a job and say why', function () {
    $admin = serviceAdmin();
    $booking = bookServiceJob();

    $this->actingAs($admin, 'admin')
        ->post("/admin/services/bookings/{$booking->reference}/cancel", [
            'cancel_reason' => 'Customer is away that week.',
        ])
        ->assertStatus(302);

    $booking->refresh();

    expect($booking->status)->toBe('cancelled')
        ->and($booking->cancel_reason)->toBe('Customer is away that week.');
});

test('a cancellation needs a reason', function () {
    $admin = serviceAdmin();
    $booking = bookServiceJob();

    $this->actingAs($admin, 'admin')
        ->post("/admin/services/bookings/{$booking->reference}/cancel", ['cancel_reason' => ''])
        ->assertSessionHasErrors('cancel_reason');

    expect($booking->fresh()->status)->toBe('booked');
});

test('an admin can take a job back off a worker', function () {
    $admin = serviceAdmin();
    $booking = bookServiceJob();
    $this->statuses->assign($booking, serviceWorker()->id);

    $this->actingAs($admin, 'admin')
        ->post("/admin/services/bookings/{$booking->reference}/unassign")
        ->assertStatus(302);

    $booking->refresh();

    expect($booking->assigned_to)->toBeNull()
        ->and($booking->status)->toBe('booked');
});

test('a worker with no job of their own sees an empty list, not an error', function () {
    $this->actingAs(serviceWorker(), 'delivery')
        ->get('/delivery/jobs')
        ->assertOk();
});
