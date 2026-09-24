<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>WhatsApp photos · Truehold</title>
<style>body{font:14px system-ui,sans-serif;margin:24px;color:#1f2d3d}b{color:#0b5d4e}</style>
</head>
<body>
<h3>Copying WhatsApp room photos to Truehold</h3>
<p id="status">Waiting for WhatsApp…</p>
<script>
// Only WhatsApp Web may hand photos over, and only for our agencies.
const FROM = 'https://web.whatsapp.com';
const AGENCIES = @json($agencies);
const status = document.getElementById('status');
let saved = 0, skipped = 0;
window.addEventListener('message', async (e) => {
    if (e.origin !== FROM || !e.data || !Array.isArray(e.data.items)) return;
    for (const it of e.data.items) {
        if (!AGENCIES.includes(it.agency)) { skipped++; continue; }
        for (const image of (it.images || []).slice(0, 10)) {
            try {
                const r = await fetch(@json(route('whatsapp.photos.store')), {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                    body: JSON.stringify({agency: it.agency, postcode: it.postcode, street: it.street, room: it.room, image}),
                });
                ((await r.json()).saved ? saved++ : skipped++);
            } catch (err) { skipped++; }
        }
        status.innerHTML = '<b>' + saved + '</b> photos saved, ' + skipped + ' skipped';
    }
    e.source.postMessage({done: true, saved, skipped, batch: e.data.batch}, FROM);
});
if (window.opener) window.opener.postMessage({ready: true}, FROM);
</script>
</body>
</html>
