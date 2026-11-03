<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Hisaab — {{ $to }}</title>
<style>
  body { font-family: Arial, sans-serif; max-width: 520px; margin: 24px auto; color: #111; font-size: 14px; }
  h1 { font-size: 18px; margin: 0; } .muted { color: #666; font-size: 12px; }
  table { width: 100%; border-collapse: collapse; margin-top: 14px; }
  td { padding: 6px 0; border-bottom: 1px solid #eee; } td.r { text-align: right; }
  .in { color: #15803d; } .out { color: #b91c1c; } .big td { font-size: 17px; font-weight: bold; border-top: 2px solid #111; }
  .box { margin-top: 16px; padding: 12px; border: 2px solid #111; border-radius: 8px; }
  @media print { button { display: none; } }
</style>
</head>
<body>
<h1>{{ $company['name'] ?? 'BlueRoot' }} — Hisaab</h1>
<p class="muted">{{ $hisaab['since'] ? 'Since last deposit '.$hisaab['since'] : 'Since start' }} → {{ $to }} · by {{ $user }} · {{ now()->format('d-M-Y H:i') }}</p>
<table>
  <tr><td>Drawer mein pehle se (after last deposit)</td><td class="r">Rs {{ number_format($hisaab['opening'], 0) }}</td></tr>
  @foreach ($hisaab['lines'] as $l)
    <tr><td class="{{ $l['direction'] === 'in' ? 'in' : 'out' }}">{{ $l['direction'] === 'in' ? '+' : '−' }} {{ $l['label'] }}</td><td class="r">Rs {{ number_format($l['amount'], 0) }}</td></tr>
  @endforeach
  <tr class="big"><td>Drawer mein abhi</td><td class="r">Rs {{ number_format($hisaab['drawer_now'], 0) }}</td></tr>
</table>
<div class="box">
  <table>
    <tr><td>Bank mein jama karein</td><td class="r"><b>Rs {{ number_format($hisaab['suggested_deposit'], 0) }}</b></td></tr>
    <tr><td>Drawer mein rakhein (change)</td><td class="r"><b>Rs {{ number_format($hisaab['float'], 0) }}</b></td></tr>
  </table>
</div>
<p class="muted" style="margin-top:24px">Signature: ______________________</p>
<button onclick="window.print()">Print</button>
</body>
</html>
