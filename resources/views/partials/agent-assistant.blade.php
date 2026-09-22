@auth
<div id="thAsk" class="th-ask" aria-live="polite">
    <button type="button" class="th-ask__fab" id="thAskFab" aria-expanded="false" aria-controls="thAskPanel"
            title="Ask the search assistant">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>Ask</span>
    </button>

    <section class="th-ask__panel" id="thAskPanel" hidden>
        <header class="th-ask__head">
            <strong>Search assistant</strong>
            <button type="button" class="th-ask__close" id="thAskClose" aria-label="Close">&times;</button>
        </header>

        <div class="th-ask__body" id="thAskBody">
            <p class="th-ask__hint">Describe what the client wants, in your own words.</p>
        </div>

        <form class="th-ask__form" id="thAskForm">
            <input type="text" id="thAskInput" class="th-ask__input" autocomplete="off"
                   placeholder="e.g. ensuite 30 min from Bond Street under £900">
            <button type="submit" class="th-ask__send" id="thAskSend">Search</button>
        </form>
    </section>
</div>

<style>
.th-ask{position:fixed;right:20px;bottom:20px;z-index:9000;font-family:inherit}
.th-ask__fab{display:flex;align-items:center;gap:8px;background:var(--navy,#152c4e);color:#fff;border:none;
    border-radius:999px;padding:13px 20px;font-size:15px;font-weight:600;cursor:pointer;
    box-shadow:0 8px 24px rgba(0,0,0,.28)}
.th-ask__fab:hover{filter:brightness(1.12)}
.th-ask__panel{position:absolute;right:0;bottom:64px;width:min(620px,calc(100vw - 32px));
    background:#fff;color:#152c4e;border-radius:14px;box-shadow:0 18px 50px rgba(0,0,0,.3);
    display:flex;flex-direction:column;height:min(78vh,760px);overflow:hidden}
/* A class rule beats the UA stylesheet's [hidden]{display:none}, so the panel
   would open on page load without this. */
.th-ask__panel[hidden]{display:none}
.th-ask__head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;
    border-bottom:1px solid #e6e9ef;background:#f7f9fc}
.th-ask__close{background:none;border:none;font-size:24px;line-height:1;cursor:pointer;color:#6b7280}
.th-ask__body{padding:14px 18px;overflow-y:auto;flex:1;font-size:14px}
.th-ask__hint{margin:0 0 10px;color:#5b6472}
.th-ask__form{display:flex;gap:8px;padding:12px 16px;border-top:1px solid #e6e9ef;background:#fff}
.th-ask__input{flex:1;border:1px solid #d5dbe5;border-radius:8px;padding:10px 12px;font-size:14px}
.th-ask__send{background:var(--gold,#c9a227);color:#1b2a41;border:none;border-radius:8px;
    padding:10px 16px;font-weight:700;cursor:pointer}
.th-ask__send[disabled]{opacity:.55;cursor:default}
.th-ask__res{display:flex;gap:12px;padding:11px 0;border-bottom:1px solid #eef1f6;text-decoration:none;color:inherit;align-items:center}
.th-ask__res:hover{background:#fafbfe}
.th-ask__res img{width:92px;height:68px;object-fit:cover;border-radius:7px;background:#eef1f6;flex:none}
.th-ask__res b{display:block;font-size:14px;line-height:1.35;margin-bottom:3px}
.th-ask__res small{color:#687180;font-size:12.5px}
.th-ask__tag{display:inline-block;background:#e7f6ec;color:#1d6b38;border-radius:4px;
    padding:1px 5px;font-size:11px;font-weight:700;margin-left:5px}
.th-ask__note{background:#f2f5fa;border-left:3px solid var(--gold,#c9a227);padding:8px 10px;
    border-radius:0 6px 6px 0;font-size:13px;margin:0 0 10px}
.th-ask__err{color:#b42318;font-size:13px}
.th-ask__sec{margin:16px 0 4px;font-size:12px;letter-spacing:.06em;text-transform:uppercase;
    color:#42536b;display:flex;align-items:center;gap:7px}
.th-ask__sec:first-child{margin-top:4px}
.th-ask__sec span{background:#eef1f7;color:#42536b;border-radius:10px;padding:1px 7px;
    font-size:11px;letter-spacing:0}
.th-ask__secnote{margin:0 0 6px;font-size:12px;color:#7b8598}
.th-ask__why{display:block;color:#8a6d1f;font-size:12px;margin-top:2px}
.th-ask__stn{display:block;color:#7b8598;font-size:12px}
</style>

<script>
(function () {
    const fab = document.getElementById('thAskFab');
    const panel = document.getElementById('thAskPanel');
    const close = document.getElementById('thAskClose');
    const form = document.getElementById('thAskForm');
    const input = document.getElementById('thAskInput');
    const send = document.getElementById('thAskSend');
    const body = document.getElementById('thAskBody');
    if (!fab) return;

    const toggle = (open) => {
        panel.hidden = !open;
        fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) input.focus();
    };

    fab.addEventListener('click', () => toggle(panel.hidden));
    close.addEventListener('click', () => toggle(false));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) toggle(false); });

    const money = n => n ? '£' + Number(n).toLocaleString('en-GB', {maximumFractionDigits: 0}) : '—';
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const q = input.value.trim();
        if (!q) return;

        send.disabled = true;
        body.innerHTML = '<p class="th-ask__hint">Searching…</p>';

        try {
            const res = await fetch(@json(route('agent.search')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({q}),
            });
            const data = await res.json();

            if (!res.ok) {
                body.innerHTML = '<p class="th-ask__err">' + esc(data.error || 'Something went wrong.') + '</p>';
                return;
            }

            const row = (r) => '<a class="th-ask__res" href="' + esc(r.url) + '" target="_blank" rel="noopener">'
                + (r.photo ? '<img src="' + esc(r.photo) + '" alt="" loading="lazy">' : '<img alt="">')
                + '<span><b>' + esc(r.title)
                + (r.commission ? ' <span class="th-ask__tag">COMMISSION</span>' : '')
                + '</b><small>' + esc(r.location || '') + ' \u00b7 ' + money(r.price)
                + (r.zone ? ' \u00b7 zone ' + r.zone : '')
                + (r.agent ? ' \u00b7 ' + esc(r.agent) : '') + '</small>'
                + (r.station ? '<small class="th-ask__stn">' + esc(r.station)
                    + (r.walk ? ', ' + r.walk + ' min walk' : '') + '</small>' : '')
                + (r.why ? '<small class="th-ask__why">' + esc(r.why) + '</small>' : '')
                + '</span></a>';

            const section = (title, items, note) => {
                if (!items || !items.length) return '';
                return '<h4 class="th-ask__sec">' + esc(title)
                    + ' <span>' + items.length + '</span></h4>'
                    + (note ? '<p class="th-ask__secnote">' + esc(note) + '</p>' : '')
                    + items.map(row).join('');
            };

            const g = data.groups || {commission: [], standard: [], alternatives: []};
            const onBrief = g.commission.length + g.standard.length;

            let html = '';
            if (data.explanation) html += '<p class="th-ask__note">' + esc(data.explanation) + '</p>';

            html += '<p class="th-ask__hint"><strong>' + onBrief + '</strong> match'
                 + (onBrief === 1 ? '' : 'es')
                 + (data.radius ? ' within <strong>' + data.radius + ' miles</strong> straight-line' : '')
                 + (data.widened ? ' <em>(nothing exactly there — showing nearby)</em>' : '')
                 + ((data.relaxed || []).includes('bedrooms') ? ' <em>(we don\'t hold bedroom counts for these)</em>' : '')
                 + '</p>';

            html += section('Commission — meets the brief', g.commission);
            html += section('No commission — meets the brief', g.standard);
            html += section('Other options worth offering', g.alternatives,
                            'Each of these misses the brief in one way, noted underneath.');

            if (!onBrief && !g.alternatives.length) {
                html += '<p class="th-ask__hint">Nothing matched, even stretched. Try widening the area or the budget.</p>';
            }

            body.innerHTML = html;
        } catch (err) {
            body.innerHTML = '<p class="th-ask__err">Network error — try again.</p>';
        } finally {
            send.disabled = false;
        }
    });
})();
</script>
@endauth
