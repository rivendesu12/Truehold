{{-- A wildcard (a SpareRoom letting agent's room) on our own page. Agents see
     who advertises it; a client opening a shared link sees only the room. --}}
@php
    $photos = array_values(array_filter((array) ($p['photos'] ?? [])));
    $price = is_numeric($p['price'] ?? null) ? '£' . number_format((float) $p['price']) : null;
    $label = \App\Support\AvailableLabel::for($p['available_date'] ?? null) ?? 'Available now';
    $place = \App\Support\PropertyPlace::describe((object) $p);
    $facts = array_filter([
        'Room' => $p['room_type'] ?? null,
        'Bills included' => $p['bills_included'] ?? null,
        'Deposit' => isset($p['deposit']) && is_numeric($p['deposit']) ? ((float) $p['deposit'] > 0 ? '£' . number_format((float) $p['deposit']) : 'None') : null,
        'Minimum term' => $p['min_term'] ?? null,
        'Furnishings' => $p['furnishings'] ?? null,
        'Couples' => $p['couples_ok'] ?? null,
        'Housemates' => $p['housemates'] ?? null,
        'Zone' => $p['zone'] ?? null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $p['title'] ?? 'Room' }} - TrueHold</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --navy: #1e3a5f; --gold: #d4af37; --ink: #1f2937; --muted: #6b7280; --line: #e5e7eb; --bg: #f5f7fa; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Inter, system-ui, sans-serif; color: var(--ink); background: var(--bg); }
        .bar { background: #fff; border-bottom: 1px solid var(--line); position: sticky; top: 0; z-index: 10; }
        .bar .in { max-width: 1040px; margin: 0 auto; padding: 10px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .logo { display: flex; align-items: center; gap: 10px; font-weight: 700; letter-spacing: .5px; color: var(--navy); text-decoration: none; }
        .logo i { width: 36px; height: 36px; border-radius: 9px; background: var(--navy); display: inline-flex; align-items: center; justify-content: center; color: var(--gold); font-style: normal; font-family: Georgia, serif; font-size: 20px; }
        .back { color: var(--muted); text-decoration: none; font-size: 14px; }
        main { max-width: 1040px; margin: 0 auto; padding: 18px 16px 60px; }
        .gallery { display: grid; grid-template-columns: 2fr 1fr; gap: 8px; border-radius: 16px; overflow: hidden; }
        .gallery img { width: 100%; height: 100%; object-fit: cover; display: block; background: #e8ecf2; }
        .gallery .main { aspect-ratio: 4 / 3; }
        .gallery .side { display: grid; grid-template-rows: 1fr 1fr; gap: 8px; }
        .nophoto { aspect-ratio: 16 / 7; border-radius: 16px; background: #eef1f6; display: flex; align-items: center; justify-content: center; color: var(--muted); }
        .strip { display: flex; gap: 8px; overflow-x: auto; margin-top: 8px; padding-bottom: 4px; }
        .strip img { width: 96px; height: 72px; object-fit: cover; border-radius: 8px; flex: none; }
        .grid { display: grid; grid-template-columns: 1fr 320px; gap: 22px; margin-top: 20px; align-items: start; }
        h1 { font-size: 24px; line-height: 1.25; margin: 0 0 6px; color: var(--navy); }
        .place { color: var(--muted); margin: 0 0 14px; }
        .price { font-size: 30px; font-weight: 700; color: var(--navy); }
        .price small { font-size: 14px; font-weight: 500; color: var(--muted); }
        .pill { display: inline-block; background: #fff7df; color: #8a6d1f; border-radius: 999px; padding: 4px 10px; font-size: 12px; font-weight: 600; margin-top: 6px; }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 16px 18px; }
        .facts { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; margin: 16px 0 0; }
        .facts dt { font-size: 12px; color: var(--muted); }
        .facts dd { margin: 2px 0 0; font-weight: 600; font-size: 14px; }
        .desc { white-space: pre-line; line-height: 1.6; font-size: 15px; margin-top: 18px; }
        .wild { border-color: #f3c2c2; background: #fff7f7; }
        .wild h3 { margin: 0 0 6px; font-size: 14px; color: #9b2c2c; text-transform: uppercase; letter-spacing: .06em; }
        .wild p { margin: 6px 0; font-size: 14px; }
        .wild .agency { font-size: 18px; font-weight: 700; color: var(--ink); }
        .btn { display: inline-block; background: var(--navy); color: #fff; text-decoration: none; border-radius: 9px; padding: 10px 14px; font-weight: 600; font-size: 14px; margin-top: 8px; }
        .muted { color: var(--muted); font-size: 13px; }
        @media (max-width: 760px) {
            .gallery { grid-template-columns: 1fr; }
            .gallery .side { display: none; }
            .grid { grid-template-columns: 1fr; }
            h1 { font-size: 20px; }
            .price { font-size: 26px; }
        }
    </style>
    @include('partials.mobile')
</head>
<body>
<header class="bar"><div class="in">
    <a class="logo" href="{{ route('properties.index') }}"><i>T</i> TRUEHOLD</a>
    <a class="back" href="javascript:history.back()">&larr; Back</a>
</div></header>

<main>
    @if ($photos)
        <div class="gallery">
            <img class="main" src="{{ $photos[0] }}" alt="{{ $p['title'] ?? 'Room' }}">
            <div class="side">
                @foreach (array_slice($photos, 1, 2) as $src)
                    <img src="{{ $src }}" alt="" loading="lazy">
                @endforeach
            </div>
        </div>
        @if (count($photos) > 3)
            <div class="strip">
                @foreach (array_slice($photos, 3) as $src)
                    <img src="{{ $src }}" alt="" loading="lazy" decoding="async">
                @endforeach
            </div>
        @endif
    @else
        <div class="nophoto">No photo</div>
    @endif

    <div class="grid">
        <section>
            <h1>{{ $p['title'] ?? 'Room' }}</h1>
            <p class="place">{{ $place }}</p>
            @if ($price)<div class="price">{{ $price }}<small>/month</small></div>@endif
            <span class="pill">{{ $label }}</span>

            @if ($facts)
                <dl class="facts">
                    @foreach ($facts as $k => $v)
                        <div><dt>{{ $k }}</dt><dd>{{ is_scalar($v) ? $v : '' }}</dd></div>
                    @endforeach
                </dl>
            @endif

            @if (! empty($p['description']))
                <div class="desc">{{ $p['description'] }}</div>
            @endif
        </section>

        <aside>
            @if ($isAgent)
                <div class="card wild">
                    <h3>Wildcard · not a partner</h3>
                    <p class="agency">{{ $p['agency'] ?? 'Agency not named' }}</p>
                    @if (! empty($p['phone']))
                        <a class="btn" href="tel:{{ preg_replace('/\s+/', '', $p['phone']) }}">Call {{ $p['phone'] }}</a>
                    @else
                        <p class="muted">No number on the advert. Look the agency up, or message them from the ad.</p>
                    @endif
                    <p class="muted">A SpareRoom agent, free to contact. Call before promising the room to a client, and agree the terms first.</p>
                    <p class="muted">Ad ref {{ $p['spareroom_id'] ?? '' }} · <a href="https://www.spareroom.co.uk/{{ $p['spareroom_id'] ?? '' }}" target="_blank" rel="noopener nofollow">original ad</a></p>
                </div>
            @else
                <div class="card">
                    <p style="margin:0;font-weight:600">Interested?</p>
                    <p class="muted">Contact your Truehold agent to arrange a viewing.</p>
                </div>
            @endif
        </aside>
    </div>
</main>
</body>
</html>
