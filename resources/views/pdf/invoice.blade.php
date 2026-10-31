@php
    use App\Support\Money;
    $money = fn (int $minor) => Money::format($minor, $invoice->currency);
    $totals = $invoice->totals;
    $address = $invoice->buyer['address'] ?? [];
    $rate = $totals['tax_rate_basis_points'] !== null ? rtrim(rtrim(number_format($totals['tax_rate_basis_points'] / 100, 2), '0'), '.') : null;
@endphp
@extends('pdf.layout', ['title' => "Invoice {$invoice->number}"])

@section('content')
    <table class="row">
        <tr>
            <td>
                <h1>Invoice</h1>
                <div>{{ $invoice->number }}</div>
                <div class="muted">Issued {{ $invoice->issued_at->setTimezone($timezone)->format('j M Y') }}</div>
                <div class="muted">Order {{ $invoice->order->number }}</div>
            </td>
            <td style="text-align: right">
                <strong>{{ $invoice->seller['name'] }}</strong>
                @if ($invoice->seller['address'])<div style="white-space: pre-line">{{ $invoice->seller['address'] }}</div>@endif
                @if ($invoice->seller['tax_id'])<div>Tax ID: {{ $invoice->seller['tax_id'] }}</div>@endif
            </td>
        </tr>
    </table>

    <p style="margin-top: 16pt">
        <span class="muted">Billed to</span><br>
        <strong>{{ $invoice->buyer['name'] }}</strong><br>
        {{ $invoice->buyer['email'] }}<br>
        {{ collect([$address['line1'] ?? null, $address['line2'] ?? null, $address['city'] ?? null, $address['postcode'] ?? null])->filter()->implode(', ') }}
    </p>

    <table class="lines">
        <thead>
            <tr><th>Test or package</th><th>Patient</th><th class="num">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line['description'] }}</td>
                    <td>{{ $line['patient'] }}</td>
                    <td class="num">{{ $money($line['amount']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Tests</td><td class="num">{{ $money($totals['subtotal']) }}</td></tr>
        @if ($totals['discount'] > 0)
            <tr><td>Code {{ $totals['coupon_code'] }}</td><td class="num">−{{ $money($totals['discount']) }}</td></tr>
        @endif
        @if ($totals['collection_fee'] > 0)
            <tr><td>Home collection</td><td class="num">{{ $money($totals['collection_fee']) }}</td></tr>
        @endif
        @if ($rate !== null && ! $totals['tax_inclusive'])
            <tr><td>Tax ({{ $rate }}%)</td><td class="num">{{ $money($totals['tax']) }}</td></tr>
        @endif
        <tr class="total"><td>Total</td><td class="num">{{ $money($totals['total']) }}</td></tr>
        @if ($rate !== null && $totals['tax_inclusive'])
            <tr><td colspan="2" class="muted">Includes {{ $money($totals['tax']) }} tax ({{ $rate }}%)</td></tr>
        @endif
    </table>

    <p style="margin-top: 16pt">{{ $invoice->payment_note }}</p>
    @if ($invoice->seller['footer'])<p class="footer">{{ $invoice->seller['footer'] }}</p>@endif
@endsection
