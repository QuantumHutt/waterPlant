<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    @php
        $money = fn ($v) => 'Rs '.number_format((float) $v, 2);
        $brand = '#7c3aed'; $brand2 = '#a855f7';
    @endphp
    <style>
        @page { size: A4; margin: 14mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; color: #1f2430; font-size: 13px; background: #fff; }
        .sheet { max-width: 800px; margin: 0 auto; }
        .head { display: flex; justify-content: space-between; align-items: flex-start;
                background: linear-gradient(135deg, {{ $brand }}, {{ $brand2 }}); color: #fff;
                padding: 22px 26px; border-radius: 16px; }
        .brand { display: flex; align-items: center; gap: 14px; }
        .logo { width: 52px; height: 52px; background: rgba(255,255,255,.18); border-radius: 14px;
                display: flex; align-items: center; justify-content: center; }
        .logo svg { width: 30px; height: 30px; }
        .brand h1 { font-size: 24px; letter-spacing: .5px; }
        .brand p { opacity: .85; font-size: 12px; }
        .inv-meta { text-align: right; }
        .inv-meta .tag { font-size: 11px; text-transform: uppercase; letter-spacing: 2px; opacity: .8; }
        .inv-meta .num { font-size: 18px; font-weight: 700; }
        .parties { display: flex; justify-content: space-between; gap: 20px; margin: 22px 4px; }
        .parties .box { flex: 1; }
        .parties h3 { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: {{ $brand }}; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        thead th { background: #f3effe; color: {{ $brand }}; text-align: left; padding: 10px 12px; font-size: 12px; text-transform: uppercase; letter-spacing: .5px; }
        thead th.r, tbody td.r { text-align: right; }
        tbody td { padding: 10px 12px; border-bottom: 1px solid #eee; }
        tbody tr:nth-child(even) { background: #faf8ff; }
        .totals { margin-top: 14px; margin-left: auto; width: 300px; }
        .totals .row { display: flex; justify-content: space-between; padding: 6px 4px; font-size: 13px; }
        .totals .grand { background: linear-gradient(135deg, {{ $brand }}, {{ $brand2 }}); color: #fff;
                         border-radius: 10px; padding: 10px 14px; font-size: 16px; font-weight: 700; margin-top: 6px; }
        .totals .deposit { color: #b45309; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge.paid { background: #dcfce7; color: #166534; }
        .badge.partial { background: #fef3c7; color: #92400e; }
        .badge.unpaid, .badge.returned { background: #fee2e2; color: #991b1b; }
        .footer { margin-top: 26px; padding-top: 14px; border-top: 2px dashed #e5e0f5; text-align: center; color: #6b7280; font-size: 12px; }
        .footer strong { color: {{ $brand }}; }
        .print-btn { display: inline-block; margin: 16px auto; background: {{ $brand }}; color: #fff; border: 0; padding: 10px 22px; border-radius: 999px; font-size: 14px; cursor: pointer; }
        @media print { .no-print { display: none !important; } body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="head">
            <div class="brand">
                <img src="{{ asset('images/blueroot-logo-white.png') }}" alt="BlueRoot" style="height:44px;width:auto;">
                <div>
                    <p style="font-size:13px;opacity:.9">{{ $company['name'] ?? 'AL-Noor Water Plant & Filter House' }}</p>
                    @if(!empty($company['phone']))<p style="font-size:12px;opacity:.8">{{ $company['phone'] }}</p>@endif
                </div>
            </div>
            <div class="inv-meta">
                <div class="tag">Invoice</div>
                <div class="num">{{ $invoice->invoice_number }}</div>
                <div style="opacity:.85;font-size:12px;margin-top:4px">{{ $invoice->created_at->format('d M Y, h:i A') }}</div>
            </div>
        </div>

        <div class="parties">
            <div class="box">
                <h3>Billed To</h3>
                <div style="font-weight:600">{{ $invoice->customer?->name ?? 'Walk-in Customer' }}</div>
                @if($invoice->customer?->phone)<div>{{ $invoice->customer->phone }}</div>@endif
                @if($invoice->customer?->address)<div>{{ $invoice->customer->address }}</div>@endif
            </div>
            <div class="box" style="text-align:right">
                <h3>Details</h3>
                <div>Cashier: {{ $cashier }}</div>
                <div>Type: {{ ucfirst(str_replace('_',' ', $invoice->type)) }}</div>
                <div style="margin-top:4px"><span class="badge {{ $invoice->status }}">{{ $invoice->status }}</span></div>
            </div>
        </div>

        <table>
            <thead>
                <tr><th>#</th><th>Item</th><th class="r">Qty</th><th class="r">Price</th><th class="r">Total</th></tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $i => $item)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ $item->product?->name ?? 'Item' }}</td>
                        <td class="r">{{ rtrim(rtrim(number_format($item->quantity,3),'0'),'.') }}</td>
                        <td class="r">{{ $money($item->unit_price) }}</td>
                        <td class="r">{{ $money($item->line_total) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="row"><span>Subtotal</span><span>{{ $money($invoice->subtotal) }}</span></div>
            @if($invoice->discount > 0)<div class="row"><span>Discount</span><span>- {{ $money($invoice->discount) }}</span></div>@endif
            @if($invoice->tax_total > 0)<div class="row"><span>Tax</span><span>{{ $money($invoice->tax_total) }}</span></div>@endif
            <div class="row grand"><span>TOTAL</span><span>{{ $money($invoice->grand_total) }}</span></div>
            @if($invoice->deposit_total > 0)
                <div class="row deposit"><span>Bottle Deposit</span><span>{{ $money($invoice->deposit_total) }}</span></div>
                <div class="row" style="font-weight:700"><span>Collected</span><span>{{ $money($invoice->grand_total + $invoice->deposit_total) }}</span></div>
            @endif
            <div class="row"><span>Paid</span><span>{{ $money($invoice->paid_total) }}</span></div>
            @if($invoice->balance > 0)<div class="row" style="color:#b91c1c;font-weight:700"><span>Balance (Udhaar)</span><span>{{ $money($invoice->balance) }}</span></div>@endif
        </div>

        <div class="footer">
            <p><strong>Thank you for choosing BlueRoot!</strong></p>
            @if(!empty($company['address']))<p>{{ $company['address'] }}</p>@endif
            @if(!empty($invoice->note))<p style="margin-top:6px;font-style:italic">{{ $invoice->note }}</p>@endif
        </div>

        <div class="no-print" style="text-align:center">
            <button class="print-btn" onclick="window.print()">🖨 Print / Save PDF</button>
        </div>
    </div>
</body>
</html>
