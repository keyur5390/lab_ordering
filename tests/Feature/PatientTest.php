<?php

use App\Enums\PatientRelationship;
use App\Enums\Sex;
use App\Models\Patient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => $this->user = User::factory()->create(['name' => 'Asha Patel', 'email' => 'asha@example.com']));

function validPatient(array $overrides = []): array
{
    return [
        'name' => 'Ravi Patel',
        'relationship' => 'parent',
        'date_of_birth' => '1956-09-30',
        'sex' => 'male',
        'phone' => '',
        'email' => '',
        ...$overrides,
    ];
}

function selfPatient(User $user): Patient
{
    return $user->patients()->where('relationship', PatientRelationship::Self)->sole();
}

it('gives every new account an incomplete "Self" profile', function () {
    $self = selfPatient($this->user);

    expect($self->name)->toBe('Asha Patel')
        ->and($self->email)->toBe('asha@example.com')
        ->and($self->isComplete())->toBeFalse()
        ->and($this->user->patients()->count())->toBe(1);
});

it('creates the "Self" profile when a customer registers', function () {
    $this->post(route('register.store'), [
        'name' => 'Meera Shah',
        'email' => 'meera@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect(selfPatient(User::where('email', 'meera@example.com')->sole())->name)->toBe('Meera Shah');
});

it('lists only the customer\'s own patients, "Self" first', function () {
    $this->user->patients()->create(validPatient(['name' => 'Aaron Patel', 'relationship' => 'child']));
    Patient::factory()->create(['name' => 'Someone Else']);

    $this->actingAs($this->user)
        ->get(route('patients.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/index')
            ->has('patients', 2)
            ->where('patients.0.is_self', true)
            ->where('patients.0.is_complete', false)
            ->where('patients.1.name', 'Aaron Patel')
        );
});

it('adds a family member', function () {
    $this->actingAs($this->user)
        ->post(route('patients.store'), validPatient(['email' => ' Ravi@Example.com ', 'phone' => '+91 98765 43210']))
        ->assertRedirect(route('patients.index'))
        ->assertSessionHas('status', 'Patient added.');

    $patient = $this->user->patients()->where('name', 'Ravi Patel')->sole();
    expect($patient->relationship)->toBe(PatientRelationship::Parent)
        ->and($patient->date_of_birth->toDateString())->toBe('1956-09-30')
        ->and($patient->sex)->toBe(Sex::Male)
        ->and($patient->email)->toBe('ravi@example.com')
        ->and($patient->phone)->toBe('+91 98765 43210')
        ->and($patient->isComplete())->toBeTrue();
});

it('rejects invalid patient details', function (array $data, string $field) {
    $this->actingAs($this->user)
        ->post(route('patients.store'), validPatient($data))
        ->assertSessionHasErrors($field);

    expect($this->user->patients()->count())->toBe(1);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'second "Self"' => [['relationship' => 'self'], 'relationship'],
    'unknown relationship' => [['relationship' => 'cousin'], 'relationship'],
    'missing date of birth' => [['date_of_birth' => ''], 'date_of_birth'],
    'future date of birth' => [['date_of_birth' => now()->addDay()->toDateString()], 'date_of_birth'],
    'badly formatted date' => [['date_of_birth' => '30/09/1956'], 'date_of_birth'],
    'missing sex' => [['sex' => ''], 'sex'],
    'unknown sex' => [['sex' => 'unknown'], 'sex'],
    'bad phone' => [['phone' => 'call me'], 'phone'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
]);

it('completes the "Self" profile without changing its relationship', function () {
    $self = selfPatient($this->user);

    $this->actingAs($this->user)
        ->put(route('patients.update', $self), validPatient(['name' => 'Asha P.', 'relationship' => null, 'sex' => 'female', 'date_of_birth' => '1988-04-12']))
        ->assertRedirect(route('patients.index'));

    $self->refresh();
    expect($self->name)->toBe('Asha P.')
        ->and($self->relationship)->toBe(PatientRelationship::Self)
        ->and($self->isComplete())->toBeTrue();
});

it('does not let the "Self" profile change relationship', function () {
    $this->actingAs($this->user)
        ->put(route('patients.update', selfPatient($this->user)), validPatient(['relationship' => 'child']))
        ->assertSessionHasErrors('relationship');
});

it('edits a family member', function () {
    $patient = Patient::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->get(route('patients.edit', $patient))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('patients/form')
            ->where('patient.id', $patient->id)
            ->where('relationships', fn ($options) => collect($options)->doesntContain('value', 'self'))
            ->has('sexes', 3)
        );

    $this->actingAs($this->user)
        ->put(route('patients.update', $patient), validPatient(['name' => 'New Name', 'relationship' => 'spouse']))
        ->assertRedirect(route('patients.index'));

    expect($patient->fresh()->name)->toBe('New Name')
        ->and($patient->fresh()->relationship)->toBe(PatientRelationship::Spouse);
});

it('removes a family member but never the "Self" profile', function () {
    $patient = Patient::factory()->for($this->user)->create();

    $this->actingAs($this->user)
        ->delete(route('patients.destroy', $patient))
        ->assertRedirect(route('patients.index'));
    $this->assertModelMissing($patient);

    $this->actingAs($this->user)
        ->delete(route('patients.destroy', selfPatient($this->user)))
        ->assertForbidden();
    expect($this->user->patients()->count())->toBe(1);
});

it("does not let customers see or change someone else's patients", function (string $method, string $route) {
    $other = Patient::factory()->create();

    $this->actingAs($this->user)
        ->{$method}(route($route, $other), validPatient(['name' => 'Hijacked']))
        ->assertForbidden();

    $this->assertModelExists($other);
    expect($other->fresh()->name)->not->toBe('Hijacked');
})->with([
    'edit' => ['get', 'patients.edit'],
    'update' => ['put', 'patients.update'],
    'destroy' => ['delete', 'patients.destroy'],
]);

it('requires a signed-in, verified customer', function () {
    $this->get(route('patients.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('patients.index'))
        ->assertRedirect(route('verification.notice'));
});

it('deletes patients with the account', function () {
    Patient::factory()->for($this->user)->create();

    $this->user->delete();

    expect(Patient::count())->toBe(0);
});
