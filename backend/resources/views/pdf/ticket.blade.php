<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Ticket {{ $reference }}</title>
<style>
  @page { margin: 28pt; }
  body { font-family: DejaVu Sans, sans-serif; color: #111; font-size: 10pt; }
  .brand { font-size: 9pt; letter-spacing: 2pt; text-transform: uppercase; color: #555; }
  h1 { font-size: 20pt; font-weight: normal; margin: 6pt 0 2pt; }
  .under { color: #555; margin: 0 0 14pt; }
  table.facts { width: 100%; border-collapse: collapse; margin-bottom: 16pt; }
  table.facts td { padding: 5pt 0; border-top: 0.5pt solid #ccc; vertical-align: top; }
  table.facts td.label { width: 34%; color: #555; }
  .qr-wrap { text-align: center; margin-top: 6pt; }
  table.qr { border-collapse: collapse; margin: 0 auto; }
  table.qr td { width: 3.6pt; height: 3.6pt; padding: 0; }
  table.qr td.d { background: #000; }
  .code { text-align: center; font-family: DejaVu Sans Mono, monospace; font-size: 13pt; letter-spacing: 2pt; margin-top: 6pt; }
  .note { text-align: center; color: #555; font-size: 8.5pt; margin-top: 4pt; }
</style>
</head>
<body>
  <div class="brand">Gold Coast Tokota · Ticket</div>
  <h1>{{ $event ?? 'Workshop' }}</h1>
  @if ($under)
    <p class="under">{{ $under }}</p>
  @else
    <p class="under">&nbsp;</p>
  @endif

  <table class="facts">
    @if ($date)
      <tr><td class="label">Date</td><td>{{ $date }}</td></tr>
    @endif
    @if ($slot)
      <tr><td class="label">Time</td><td>{{ $slot }}</td></tr>
    @endif
    @if ($place)
      <tr><td class="label">Where</td><td>{{ $place }}</td></tr>
    @endif
    <tr><td class="label">For</td><td>{{ $people }} {{ $people === 1 ? 'person' : 'people' }}</td></tr>
    @if ($name)
      <tr><td class="label">Booked by</td><td>{{ $name }}</td></tr>
    @endif
    <tr><td class="label">Booking</td><td>{{ $reference }}</td></tr>
  </table>

  <div class="qr-wrap">
    <table class="qr">
      @foreach ($qr as $row)
        <tr>@foreach ($row as $dark)<td @if ($dark) class="d" @endif></td>@endforeach</tr>
      @endforeach
    </table>
  </div>
  <div class="code">{{ $code }}</div>
  <p class="note">Show this at the door. One ticket covers everyone on the booking.</p>
</body>
</html>
