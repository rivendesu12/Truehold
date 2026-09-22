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
            <ul class="th-ask__examples">
                <li><button type="button" class="th-ask__eg">ensuite within 20 minutes of Bond Street</button></li>
                <li><button type="button" class="th-ask__eg">up to zone 3, max £700 a month</button></li>
                <li><button type="button" class="th-ask__eg">room in a max 3 bed flat, only commission agencies</button></li>
                <li><button type="button" class="th-ask__eg">studio in Canary Wharf under £1500</button></li>
            </ul>
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
.th-ask__panel{position:absolute;right:0;bottom:60px;width:min(420px,calc(100vw - 40px));
    background:#fff;color:#152c4e;border-radius:14px;box-shadow:0 18px 50px rgba(0,0,0,.3);
    display:flex;flex-direction:column;max-height:min(70vh,620px);overflow:hidden}
.th-ask__head{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;
    border-bottom:1px solid #e6e9ef;background:#f7f9fc}
.th-ask__close{background:none;border:none;font-size:24px;line-height:1;cursor:pointer;color:#6b7280}
.th-ask__body{padding:14px 16px;overflow-y:auto;flex:1;font-size:14px}
.th-ask__hint{margin:0 0 10px;color:#5b6472}
.th-ask__examples{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px}
.th-ask__eg{width:100%;text-align:left;background:#f2f5fa;border:1px solid #e2e8f0;border-radius:8px;
    padding:8px 10px;font-size:13px;cursor:pointer;color:#22364f}
.th-ask__eg:hover{background:#e8eef8}
.th-ask__form{display:flex;gap:8px;padding:12px 16px;border-top:1px solid #e6e9ef;background:#fff}
.th-ask__input{flex:1;border:1px solid #d5dbe5;border-radius:8px;padding:10px 12px;font-size:14px}
.th-ask__send{background:var(--gold,#c9a227);color:#1b2a41;border:none;border-radius:8px;
    padding:10px 16px;font-weight:700;cursor:pointer}
.th-ask__send[disabled]{opacity:.55;cursor:default}
.th-ask__res{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid #eef1f6;text-decoration:none;color:inherit}
.th-ask__res img{width:62px;height:48px;object-fit:cover;border-radius:6px;background:#eef1f6;flex:none}
.th-ask__res b{display:block;font-size:13px;line-height:1.3}
.th-ask__res small{color:#687180;font-size:12px}
.th-ask__tag{display:inline-block;background:#e7f6ec;color:#1d6b38;border-radius:4px;
    padding:1px 5px;font-size:11px;font-weight:700;margin-left:5px}
.th-ask__note{background:#f2f5fa;border-left:3px solid var(--gold,#c9a227);padding:8px 10px;
    border-radius:0 6px 6px 0;font-size:13px;margin:0 0 10px}
.th-ask__err{color:#b42318;font-size:13px}
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

    document.querySelectorAll('.th-ask__eg').forEach(b =>
        b.addEventListener('click', () => { input.value = b.textContent.trim(); form.requestSubmit(); }));

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

            let html = '';
            if (data.explanation) html += '<p class="th-ask__note">' + esc(data.explanation) + '</p>';
            html += '<p class="th-ask__hint"><strong>' + data.matched + '</strong> match'
                 + (data.matched === 1 ? '' : 'es')
                 + (data.widened ? ' <em>(nothing exactly there — showing nearby)</em>' : '')
                 + (data.commission_only ? ', commission-paying only' : '')
                 + (data.matched > 12 ? ' — showing the first 12' : '') + '</p>';

            if (!data.results.length) {
                html += '<p class="th-ask__hint">Nothing matched. Try widening the area or the budget.</p>';
            } else {
                for (const r of data.results) {
                    html += '<a class="th-ask__res" href="' + esc(r.url) + '" target="_blank" rel="noopener">'
                        + (r.photo ? '<img src="' + esc(r.photo) + '" alt="" loading="lazy">' : '<img alt="">')
                        + '<span><b>' + esc(r.title)
                        + (r.commission ? '<span class="th-ask__tag">COMMISSION</span>' : '')
                        + '</b><small>' + esc(r.location || '') + ' · ' + money(r.price)
                        + (r.agent ? ' · ' + esc(r.agent) : '') + '</small></span></a>';
                }
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
