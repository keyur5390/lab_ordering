@php
    use App\Support\Money;
    $invoice = $note->invoice;
@endphp
@extends('pdf.layout', ['title' => "Credit note {$note->number}"])

@section('content')
    <table class="row">
        <tr>
            <td>
                <h1>Credit note</h1>
                <div>{{ $note->number }}</div>
                <div class="muted">Issued {{ $note->issued_at->setTimezone($timezone)->format('j M Y') }}</div>
                <div class="muted">For invoice {{ $invoice->number }} (order {{ $invoice->order->number }})</div>
            </td>
            <td style="text-align: right">
                <strong>{{ $invoice->seller['name'] }}</strong>
                @if ($invoice->seller['address'])<div style="white-space: pre-line">{{ $invoice->seller['address'] }}</div>@endif
                @if ($invoice->seller['tax_id'])<div>Tax ID: {{ $invoice->seller['tax_id'] }}</div>@endif
            </td>
        </tr>
    </table>

    <p style="margin-top: 16pt">
        <span class="muted">Issued to</span><br>
        <strong>{{ $invoice->buyer['name'] }}</strong><br>
        {{ $invoice->buyer['email'] }}
    </p>

    <table class="totals" style="margin-left: 0">
        <tr class="total"><td style="padding-left: 0">Amount refunded</td><td class="num">{{ Money::format($note->amount, $invoice->currency) }}</td></tr>
    </table>
    @if ($note->reason)<p>Reason: {{ $note->reason }}</p>@endif
    <p class="muted">Refunded to the original payment method.</p>
    @if ($invoice->seller['footer'])<p class="footer">{{ $invoice->seller['footer'] }}</p>@endif
@endsection
