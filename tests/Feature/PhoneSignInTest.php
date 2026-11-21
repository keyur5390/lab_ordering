<?php

use App\Enums\SmsDriver;
use App\Enums\UserRole;
use App\Models\PhoneCode;
use App\Models\User;
use App\Services\PhoneCodeService;
use App\Services\PrivacyService;
use App\Services\Sms\LogSmsSender;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsSender;
use App\Settings\Settings;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

/** Collects texts instead of sending them. */
class FakeSms implements SmsSender
{
    /** @var list<array{to: string, message: string}> */
    public array $sent = [];

    public function send(string $to, string $message): void
    {
        $this->sent[] = ['to' => $to, 'message' => $message];
    }

    public function lastCode(): ?string
    {
        $last = end($this->sent);

        return $last && preg_match('/\b(\d{6})\b/', $last['message'], $m) ? $m[1] : null;
    }
}

beforeEach(function () {
    app(Settings::class)->setSmsDriver(SmsDriver::Log);
    $this->sms = new FakeSms;
    app()->instance(LogSmsSender::class, $this->sms);
    $this->user = User::factory()->create();
    $this->user->forceFill(['phone' => '+919800000001', 'phone_verified_at' => now()])->save();
});

function phoneLogin(string $phone = '+91 98000 00001')
{
    return test()->post(route('login.phone.send'), ['phone' => $phone]);
}

describe('signing in with a phone', function () {
    it('texts a code to a confirmed number and signs in with it', function () {
        phoneLogin()->assertRedirect(route('login'))->assertSessionHas('status', fn (string $s) => str_contains($s, '+91•••••••001'));

        expect($this->sms->sent)->toHaveCount(1)
            ->and($this->sms->sent[0]['to'])->toBe('+919800000001')
            ->and(PhoneCode::sole()->code)->not->toBe($this->sms->lastCode());

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('phoneLogin', true)->where('phonePending', '+91•••••••001'));

        $this->post(route('login.phone.verify'), ['code' => $this->sms->lastCode()])->assertRedirect('/account');
        $this->assertAuthenticatedAs($this->user);
        expect(PhoneCode::count())->toBe(0);
    });

    it('answers the same for unknown, unconfirmed and disabled numbers, and sends nothing', function () {
        $unconfirmed = User::factory()->create(['email' => 'u@example.com']);
        $unconfirmed->forceFill(['phone' => '+919800000002'])->save();
        $disabled = User::factory()->create();
        $disabled->forceFill(['phone' => '+919800000003', 'phone_verified_at' => now(), 'disabled_at' => now()])->save();

        foreach (['+919800000009', '+919800000002', '+919800000003'] as $phone) {
            phoneLogin($phone)->assertRedirect(route('login'))->assertSessionHas('status', fn (string $s) => str_contains($s, 'If '));
        }

        expect($this->sms->sent)->toBe([]);
        $this->post(route('login.phone.verify'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest();
    });

    it('rejects wrong, expired and overused codes', function () {
        phoneLogin();
        $code = $this->sms->lastCode();
        $wrong = $code === '111111' ? '222222' : '111111';

        foreach (range(1, PhoneCodeService::MAX_ATTEMPTS) as $try) {
            $this->post(route('login.phone.verify'), ['code' => $wrong])->assertSessionHasErrors('code');
        }
        // Even the right code no longer works.
        $this->post(route('login.phone.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();

        RateLimiter::clear('phone-code:+919800000001');
        phoneLogin();
        $this->travel(PhoneCodeService::TTL_MINUTES + 1)->minutes();
        $this->post(route('login.phone.verify'), ['code' => $this->sms->lastCode()])->assertSessionHasErrors('code');
        $this->assertGuest();
    });

    it('limits how many codes go to one number', function () {
        foreach (range(1, PhoneCodeService::SENDS_PER_PHONE) as $try) {
            phoneLogin()->assertSessionHasNoErrors();
        }
        phoneLogin()->assertSessionHasErrors('phone');

        expect($this->sms->sent)->toHaveCount(PhoneCodeService::SENDS_PER_PHONE);
    });

    it('only replaces the code with a newer one', function () {
        phoneLogin();
        $first = $this->sms->lastCode();
        phoneLogin();

        expect(PhoneCode::count())->toBe(1);
        if ($first !== $this->sms->lastCode()) {
            $this->post(route('login.phone.verify'), ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post(route('login.phone.verify'), ['code' => $this->sms->lastCode()]);
        $this->assertAuthenticatedAs($this->user);
    });

    it('checks the number format', function () {
        phoneLogin('98000 00001')->assertSessionHasErrors('phone');
    });

    it('is not offered, and sends nothing, while SMS is off', function () {
        app(Settings::class)->setSmsDriver(null);

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('phoneLogin', false));
        phoneLogin()->assertSessionHasErrors('phone');
        expect($this->sms->sent)->toBe([]);
    });

    it('answers as usual, without a server error, when the text cannot be sent', function () {
        Exceptions::fake();
        app()->instance(LogSmsSender::class, failingSms());

        // A sign-in error here would show the number has an account, so the answer is the usual one.
        phoneLogin()->assertRedirect(route('login'))->assertSessionHasNoErrors()->assertSessionHas('status');
        expect(PhoneCode::count())->toBe(0);
        // The failure is reported for us, and nothing else goes wrong after the response.
        Exceptions::assertReported(SmsException::class);
        Exceptions::assertNotReported(ValidationException::class);
    });

    it('sends the code after the response, so known numbers answer no slower than unknown ones', function () {
        // Handle the request without the after-response step: no code is made or texted yet.
        $request = Request::create(route('login.phone.send'), 'POST', ['phone' => '+919800000001']);
        $request->setLaravelSession($this->app['session.store']);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $response = $this->app->make(Kernel::class)->handle($request);

        expect($response->isRedirect())->toBeTrue()
            ->and(PhoneCode::count())->toBe(0)
            ->and($this->sms->sent)->toBe([]);

        app(DeferredCallbackCollection::class)->invoke();
        expect(PhoneCode::count())->toBe(1)->and($this->sms->sent)->toHaveCount(1);
    });
});

function failingSms(): SmsSender
{
    return new class implements SmsSender
    {
        public function send(string $to, string $message): void
        {
            throw new SmsException('Twilio request failed.');
        }
    };
}

describe('adding a number to the account', function () {
    beforeEach(function () {
        $this->customer = User::factory()->create();
    });

    it('confirms a new number with a code, then uses it to sign in and for texts', function () {
        $as = $this->actingAs($this->customer);
        $as->post(route('account.phone.send'), ['phone' => '+44 7700 900123'])->assertRedirect(route('account.settings'));
        $as->get(route('account.settings'))->assertInertia(fn (Assert $page) => $page->component('account-settings')
            ->where('phone', ['available' => true, 'number' => null, 'pending' => '+447700900123']));

        $as->post(route('account.phone.verify'), ['code' => $this->sms->lastCode() === '000000' ? '999999' : '000000'])->assertSessionHasErrors('code');
        $as->post(route('account.phone.verify'), ['code' => $this->sms->lastCode()])->assertSessionHas('status', 'phone-verified');

        $customer = $this->customer->fresh();
        expect($customer->phone)->toBe('+447700900123')
            ->and($customer->phone_verified_at)->not->toBeNull()
            ->and($customer->routeNotificationForSms())->toBe('+447700900123');
        $this->actingAs($customer)->get(route('account.settings'))->assertInertia(fn (Assert $page) => $page->where('phone.number', '+447700900123')->where('phone.pending', null));
    });

    it('shows a form error, not a server error, when the confirmation text cannot be sent', function () {
        app()->instance(LogSmsSender::class, failingSms());

        $this->actingAs($this->customer)->post(route('account.phone.send'), ['phone' => '+44 7700 900123'])->assertSessionHasErrors('phone');
        expect(PhoneCode::count())->toBe(0);
    });

    it('does not take a number that is on another account', function () {
        $this->actingAs($this->customer)->post(route('account.phone.send'), ['phone' => '+919800000001'])->assertSessionHasErrors('phone');
        expect($this->sms->sent)->toBe([]);
    });

    it('does not let a sign-in code confirm a number', function () {
        phoneLogin();

        $this->actingAs($this->user)->withSession(['phone_verify' => '+919800000001'])
            ->post(route('account.phone.verify'), ['code' => $this->sms->lastCode()])->assertSessionHasErrors('code');
    });

    it('does not let one account confirm a number with the code another account asked for', function () {
        $other = User::factory()->create();
        $this->actingAs($this->customer)->post(route('account.phone.send'), ['phone' => '+447700900123']);
        // Someone else asks for the same number; the owner of the phone gets their code.
        $this->actingAs($other)->post(route('account.phone.send'), ['phone' => '+447700900123']);

        $this->actingAs($this->customer)->withSession(['phone_verify' => '+447700900123'])
            ->post(route('account.phone.verify'), ['code' => $this->sms->lastCode()])->assertSessionHasErrors('code');
        expect($this->customer->fresh()->phone)->toBeNull()->and($other->fresh()->phone)->toBeNull();
    });

    it('removes the number, which stops phone sign-in', function () {
        $this->actingAs($this->user)->delete(route('account.phone.destroy'))->assertSessionHas('status', 'phone-removed');
        expect($this->user->fresh()->phone)->toBeNull();

        auth()->guard('web')->logout();
        phoneLogin();
        expect($this->sms->sent)->toBe([]);
    });

    it('drops the number when the account is deleted', function () {
        app(PrivacyService::class)->anonymise($this->user);

        expect($this->user->fresh()->phone)->toBeNull()->and($this->user->fresh()->phone_verified_at)->toBeNull();
    });
});

it('lets staff sign in by phone too', function () {
    $this->user->forceFill(['role' => UserRole::Admin])->save();
    phoneLogin();
    $this->post(route('login.phone.verify'), ['code' => $this->sms->lastCode()]);

    $this->assertAuthenticatedAs($this->user);
});
