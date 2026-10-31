<?php

use App\Enums\Currency;
use App\Support\Money;

it('converts decimal strings to minor units', function (string $decimal, int $minor) {
    expect(Money::toMinor($decimal, Currency::INR))->toBe($minor);
})->with([
    ['0', 0],
    ['499', 49900],
    ['499.5', 49950],
    ['499.50', 49950],
    ['0.05', 5],
    ['1234567.89', 123456789],
]);

it('converts minor units to decimal strings', function (int $minor, string $decimal) {
    expect(Money::toDecimal($minor, Currency::USD))->toBe($decimal);
})->with([
    [0, '0.00'],
    [5, '0.05'],
    [49950, '499.50'],
    [123456789, '1234567.89'],
]);

it('rejects badly formatted amounts', function (string $decimal) {
    Money::toMinor($decimal, Currency::INR);
})->throws(InvalidArgumentException::class)->with(['', '-1', '1.234', '1,000', 'abc', '.5']);

it('treats a missing currency as 2 decimal places', function () {
    expect(Money::toMinor('10.25', null))->toBe(1025);
});
