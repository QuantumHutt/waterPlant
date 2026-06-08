<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $invoice->invoice_number }}</title>
    @php
        $width = $size === 58 ? '58mm' : '80mm';
        $sym = $company['symbol'] ?? 'Rs';
        $money = fn ($v) => 'Rs '.number_format((float) $v, 2);
    @endphp
    <style>
        @page { size: {{ $width }} auto; margin: 0; }
        * { box-sizing: border-box; }
        body {
            width: {{ $width }};
            margin: 0 auto;
            padding: 6px 8px;
            font-family: "Courier New", monospace;
            font-size: {{ $size === 58 ? '11px' : '12px' }};
            color: #000;
            background: #fff;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .logo { width: 42px; height: 42px; margin: 0 auto 4px; display: block; }
        .brand { font-size: {{ $size === 58 ? '15px' : '17px' }}; font-weight: 700; letter-spacing: .5px; }
        .muted { color: #333; }
        hr { border: none; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 1px 0; vertical-align: top; }
        .items td { font-size: {{ $size === 58 ? '10px' : '11px' }}; }
        .totals td { padding: 2px 0; }
        .totline { font-size: {{ $size === 58 ? '13px' : '15px' }}; font-weight: 700; }
        .thanks { margin-top: 8px; }
        @media screen { body { box-shadow: 0 0 0 1px #ddd; margin-top: 16px; } }
    </style>
</head>
<body onload="window.print()">
    <div class="center">
        <svg class="logo" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
            <rect width="64" height="64" rx="14" fill="#000"/>
            <path d="M32 14c7 9 12 15 12 22a12 12 0 0 1-24 0c0-7 5-13 12-22z" fill="#fff"/>
        </svg>
        <div class="brand">BlueRoot</div>
        <div class="muted">{{ $company['name'] ?? 'AL-Noor Water Plant & Filter House' }}</div>
        @if(!empty($company['phone']))<div class="muted">{{ $company['phone'] }}</div>@endif
        @if(!empty($company['address']))<div class="muted">{{ $company['address'] }}</div>@endif
    </div>

    <hr>

    <table>
        <tr><td>Invoice</td><td class="right bold">{{ $invoice->invoice_number }}</td></tr>
        <tr><td>Date</td><td class="right">{{ $invoice->created_at->format('d-M-Y h:i A') }}</td></tr>
        <tr><td>Cashier</td><td class="right">{{ $cashier }}</td></tr>
        <tr><td>Customer</td><td class="right">{{ $invoice->customer?->name ?? 'Walk-in' }}</td></tr>
        <tr><td>Type</td><td class="right">{{ ucfirst(str_replace('_',' ', $invoice->type)) }}</td></tr>
    </table>

    <hr>

    <table class="items">
        <tr class="bold">
            <td>Item</td>
            <td class="right">Qty</td>
            <td class="right">Price</td>
            <td class="right">Total</td>
        </tr>
        @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->product?->name ?? 'Item' }}</td>
                <td class="right">{{ rtrim(rtrim(number_format($item->quantity,3),'0'),'.') }}</td>
                <td class="right">{{ number_format($item->unit_price,0) }}</td>
                <td class="right">{{ number_format($item->line_total,0) }}</td>
            </tr>
        @endforeach
    </table>

    <hr>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">{{ $money($invoice->subtotal) }}</td></tr>
        @if($invoice->discount > 0)<tr><td>Discount</td><td class="right">- {{ $money($invoice->discount) }}</td></tr>@endif
        @if($invoice->tax_total > 0)<tr><td>Tax</td><td class="right">{{ $money($invoice->tax_total) }}</td></tr>@endif
        <tr class="totline"><td>TOTAL</td><td class="right">{{ $money($invoice->grand_total) }}</td></tr>
        @if($invoice->deposit_total > 0)
            <tr><td>Bottle Deposit</td><td class="right">{{ $money($invoice->deposit_total) }}</td></tr>
            <tr class="totline"><td>COLLECTED</td><td class="right">{{ $money($invoice->grand_total + $invoice->deposit_total) }}</td></tr>
        @endif
        <tr><td>Paid</td><td class="right">{{ $money($invoice->paid_total) }}</td></tr>
        @if($invoice->balance > 0)<tr><td>Balance</td><td class="right">{{ $money($invoice->balance) }}</td></tr>@endif
        <tr><td>Status</td><td class="right">{{ strtoupper($invoice->status) }}</td></tr>
    </table>

    <hr>

    <div class="center thanks">
        @if(!empty($company['ntn']))<div class="muted">NTN: {{ $company['ntn'] }}</div>@endif
        <div class="bold">Thank you for choosing BlueRoot!</div>
        <div class="muted">Software by BlueRoot ERP</div>
    </div>

    <script>
        // Close the print tab after printing (or cancel).
        window.onafterprint = function () { window.close(); };
    </script>
</body>
</html>
