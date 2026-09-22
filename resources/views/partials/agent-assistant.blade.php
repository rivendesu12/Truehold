@auth
<div id="thAsk" class="th-ask" aria-live="polite">
    <button type="button" class="th-ask__fab" id="thAskFab" aria-expanded="false" aria-controls="thAskPanel"
            title="Ask Sigou to find a room">
        @include('partials.sigou', ['size' => 34, 'class' => 'th-ask__avatar'])
        <span>Ask Sigou</span>
    </button>

    <section class="th-ask__panel" id="thAskPanel" hidden>
        <header class="th-ask__head">
            <span class="th-ask__who">
                @include('partials.sigou', ['size' => 36])
                <span><strong>Sigou</strong><small>Finds the room. Doesn't judge the budget.</small></span>
            </span>
            <button type="button" class="th-ask__close" id="thAskClose" aria-label="Close">&times;</button>
        </header>

        <div class="th-ask__body" id="thAskBody">
            <div class="th-ask__stage" id="thAskStage">
                <button type="button" class="th-ask__poke" id="thAskPoke" aria-label="Poke Sigou">
                    @include('partials.sigou', ['size' => 120, 'class' => 'th-sigou--big'])
                </button>
                <p class="th-ask__bubble" id="thAskSay">Tell me what the client wants, in your own words: budget, area, commute, room type. I'll dig through every listing.</p>
            </div>
            <div id="thAskResults"></div>
        </div>

        <form class="th-ask__form" id="thAskForm">
            <input type="search" id="thAskInput" class="th-ask__input" autocomplete="off"
                   enterkeyhint="search" aria-label="Describe what the client wants"
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
/* An empty <img> renders as a blank grey box that reads like a broken page.
   Say there is no photo instead, so the agent knows to check the source. */
.th-ask__noimg{width:92px;height:68px;border-radius:7px;background:#f2f4f8;border:1px dashed #cfd6e4;
  flex:none;display:flex;align-items:center;justify-content:center;color:#8b93a4;font-size:11px}
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
.th-ask__jny{display:block;color:#1f6d4a;font-size:12px;margin-top:2px}
.th-ask__warn{background:#fff6e0;border:1px solid #f0d89a;color:#7a5c12;padding:8px 10px;border-radius:6px;font-size:13px;margin:0 0 10px}
.th-ask__stn{display:block;color:#7b8598;font-size:12px}

/* Sigou */
.th-ask__fab{padding:6px 18px 6px 6px}
.th-ask__avatar{flex:none;border-radius:50%;box-shadow:0 0 0 2px rgba(255,255,255,.85)}
.th-ask__who{display:flex;align-items:center;gap:10px}
.th-ask__who small{display:block;font-size:12px;color:#6b7280;font-weight:400;margin-top:1px}
.th-ask__stage{display:flex;flex-direction:column;align-items:center;text-align:center;padding:22px 8px 6px;transition:padding .25s}
.th-ask__poke{background:none;border:0;padding:0;cursor:pointer;border-radius:50%;line-height:0}
.th-ask__stage .th-sigou{width:120px;height:120px;transition:width .25s,height .25s}
.th-ask__bubble{position:relative;margin:16px 0 0;max-width:400px;background:#f4f6fa;border-radius:14px;
    padding:11px 14px;color:#34445c;font-size:14px;line-height:1.45;text-align:center}
.th-ask__bubble::before{content:"";position:absolute;top:-8px;left:50%;margin-left:-8px;
    border:8px solid transparent;border-top:0;border-bottom-color:#f4f6fa}
.th-ask__bubble.is-new{animation:thPop .22s ease-out}
/* After the first search he moves aside and keeps commenting. */
.th-ask__stage.is-compact{flex-direction:row;align-items:center;gap:12px;padding:4px 0 10px;text-align:left}
.th-ask__stage.is-compact .th-sigou{width:62px;height:62px}
.th-ask__stage.is-compact .th-ask__bubble{margin:0;flex:1;text-align:left}
.th-ask__stage.is-compact .th-ask__bubble::before{top:50%;left:-8px;margin:-8px 0 0;
    border:8px solid transparent;border-left:0;border-right-color:#f4f6fa}

/* The character. Parts scale and turn about their own centre. */
.th-sigou .sg-eyes,.th-sigou .sg-white,.th-sigou .sg-dot,.th-sigou .sg-brow,.th-sigou .sg-brows,
.th-sigou .sg-hand,.th-sigou .sg-cloud,.th-sigou .sg-sweat,.th-sigou .sg-led{transform-box:fill-box;transform-origin:center}
.th-sigou .sg-m,.th-sigou .sg-vape,.th-sigou .sg-rub,.th-sigou .sg-sweat{display:none}
.th-sigou .sg-head{animation:sgBreathe 3.4s ease-in-out infinite}
.th-sigou .sg-eyes{transition:transform .07s}
.th-sigou .sg-eyes.is-blink{transform:scaleY(.08)}
.th-sigou .sg-pupils{transition:transform .16s ease-out}
.th-sigou .sg-brows,.th-sigou .sg-brow,.th-sigou .sg-white,.th-sigou .sg-dot{transition:transform .2s ease}
.th-sigou .sg-brows.is-waggle{animation:sgWaggle .7s ease-in-out}
.th-sigou.is-nod{animation:sgNod .16s ease-out}

.th-sigou[data-state=idle] .sg-m--flat,
.th-sigou[data-state=typing] .sg-m--flat{display:inline}

/* typing: leans in with the wide-eyed stare */
.th-sigou[data-state=typing] .sg-brows{transform:translateY(-5px)}
.th-sigou[data-state=typing] .sg-white{transform:scale(1.14)}

/* Vaping: "thinking" loops while a search runs, "vape" is a single puff he
   sneaks in while idle. One 3.2s cycle: arm up, a long drag (chest rises,
   light glows, eyes go heavy), arm down, head back, blow. */
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-vape{display:inline}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-vape-arm{animation:sgVapeArm 3.2s cubic-bezier(.45,.05,.35,1) infinite}
/* The arm swings from the shoulder; between drags the vape rests in his
   hand in front of his chest, never out of shot. */
.th-sigou .sg-vape-arm{transform-box:view-box;transform-origin:156px 230px}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-led{animation:sgLed 3.2s linear infinite}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-eyes{animation:sgChill 3.2s ease-in-out infinite}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-brows{animation:sgBlowBrows 3.2s ease-in-out infinite}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-head{transform-box:view-box;transform-origin:100px 150px;animation:sgVapeHead 3.2s ease-in-out infinite}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-body{animation:sgChest 3.2s ease-in-out infinite}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-m--flat{display:inline;animation:sgMouthShut 3.2s linear infinite}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-m--o{display:inline;animation:sgMouthBlow 3.2s linear infinite}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-cloud{opacity:0;animation:sgCloud1 3.2s ease-out infinite}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-cloud--2{animation-name:sgCloud2}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-cloud--3{animation-name:sgCloud3}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-cloud--4{animation-name:sgCloud4}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-cloud--5{animation-name:sgCloud5}
.th-sigou:is([data-state=thinking],[data-state=vape]) .sg-cloud--6{animation-name:sgCloud6}
.th-sigou[data-state=vape] .sg-m--flat,.th-sigou[data-state=vape] .sg-m--o{animation-iteration-count:1}

/* happy: rubs his hands */
.th-sigou[data-state=happy] .sg-m--grin,.th-sigou[data-state=happy] .sg-rub{display:inline}
.th-sigou[data-state=happy] .sg-hand--l{animation:sgRubL .22s ease-in-out infinite alternate}
.th-sigou[data-state=happy] .sg-hand--r{animation:sgRubR .22s ease-in-out infinite alternate}
.th-sigou[data-state=happy] .sg-brows{animation:sgWaggle .7s ease-in-out 2}

/* shocked: the stare */
.th-sigou[data-state=shocked]{animation:sgShake .4s ease-in-out}
.th-sigou[data-state=shocked] .sg-m--o{display:inline}
.th-sigou[data-state=shocked] .sg-brows{transform:translateY(-9px)}
.th-sigou[data-state=shocked] .sg-white{transform:scale(1.32)}
.th-sigou[data-state=shocked] .sg-dot{transform:scale(.55)}

/* sad: something broke */
.th-sigou[data-state=sad] .sg-m--sad,.th-sigou[data-state=sad] .sg-sweat{display:inline}
.th-sigou[data-state=sad] .sg-brow--l{transform:rotate(14deg) translateY(3px)}
.th-sigou[data-state=sad] .sg-brow--r{transform:rotate(-14deg) translateY(3px)}
.th-sigou[data-state=sad] .sg-sweat{animation:sgDrip 1.6s ease-in infinite}

@keyframes sgBreathe{0%,100%{transform:translateY(0)}50%{transform:translateY(-2px)}}
@keyframes sgWaggle{0%,100%{transform:translateY(0)}25%,75%{transform:translateY(-8px)}50%{transform:translateY(0)}}
@keyframes sgNod{50%{transform:translateY(2px)}}
@keyframes sgVapeArm{0%,62%,100%{transform:translateY(10px) rotate(-20deg)}15%{transform:translateY(1px) rotate(-1deg)}18%,46%{transform:translateY(0) rotate(0)}}
@keyframes sgLed{0%,18%,48%,100%{opacity:.25;transform:scale(1)}24%,44%{opacity:1;transform:scale(1.7)}}
@keyframes sgChill{0%,16%,54%,100%{transform:scaleY(1)}26%,46%{transform:scaleY(.42)}}
@keyframes sgBlowBrows{0%,52%,100%{transform:translateY(0)}64%,82%{transform:translateY(-5px)}}
@keyframes sgVapeHead{0%,100%{transform:rotate(0) translateY(0)}20%,46%{transform:rotate(1.5deg) translateY(1px)}60%,84%{transform:rotate(-6deg) translateY(-3px)}}
@keyframes sgChest{0%,16%,100%{transform:translateY(0)}44%{transform:translateY(-3px)}58%{transform:translateY(1px)}}
@keyframes sgMouthShut{0%,56%{opacity:1}58%,90%{opacity:0}92%,100%{opacity:1}}
@keyframes sgMouthBlow{0%,56%{opacity:0}58%,90%{opacity:1}92%,100%{opacity:0}}
@keyframes sgCloud1{0%,57%{opacity:0;transform:translate(0,0) scale(.3)}62%{opacity:1;transform:translate(-3px,-6px) scale(.9)}100%{opacity:0;transform:translate(-28px,-78px) scale(2.8)}}
@keyframes sgCloud2{0%,59%{opacity:0;transform:translate(0,0) scale(.3)}65%{opacity:.95;transform:translate(-12px,-4px) scale(1)}100%{opacity:0;transform:translate(-58px,-50px) scale(3)}}
@keyframes sgCloud3{0%,61%{opacity:0;transform:translate(0,0) scale(.3)}67%{opacity:.95;transform:translate(6px,-10px) scale(.9)}100%{opacity:0;transform:translate(22px,-86px) scale(2.6)}}
/* the big one swallows his face for a moment */
@keyframes sgCloud4{0%,63%{opacity:0;transform:translate(0,0) scale(.3)}77%{opacity:.96;transform:translate(0,-32px) scale(4.2)}90%{opacity:.6;transform:translate(0,-46px) scale(4.9)}100%{opacity:0;transform:translate(0,-60px) scale(5.3)}}
@keyframes sgCloud5{0%,66%{opacity:0;transform:translate(0,0) scale(.3)}72%{opacity:.9;transform:translate(-6px,-12px) scale(1)}100%{opacity:0;transform:translate(-38px,-104px) scale(2.4)}}
@keyframes sgCloud6{0%,69%{opacity:0;transform:translate(0,0) scale(.3)}75%{opacity:.85;transform:translate(8px,-14px) scale(1)}100%{opacity:0;transform:translate(40px,-96px) scale(2.2)}}
@keyframes sgShake{0%,100%{transform:translateX(0)}25%{transform:translateX(-3px)}75%{transform:translateX(3px)}}
@keyframes sgDrip{0%{transform:translateY(0);opacity:1}100%{transform:translateY(14px);opacity:0}}
@keyframes thPop{from{transform:scale(.96);opacity:.4}to{transform:scale(1);opacity:1}}
@media (prefers-reduced-motion:reduce){.th-sigou *,.th-sigou{animation:none!important;transition:none!important}}

/* Phones: a full-screen sheet with the search box at the top. At the bottom
   the keyboard covered it and the panel's fixed height fought the viewport. */
@media (max-width:640px){
    .th-ask{right:16px;bottom:calc(16px + env(safe-area-inset-bottom,0px))}
    .th-ask__fab{padding:5px 16px 5px 5px}
    .th-ask--open .th-ask__fab{display:none}
    .th-ask__panel{position:fixed;inset:0;width:auto;height:100%;height:100dvh;border-radius:0;box-shadow:none}
    .th-ask__head{order:-2;padding:10px 8px 10px 16px;padding-top:calc(10px + env(safe-area-inset-top,0px))}
    .th-ask__close{width:44px;height:44px;font-size:28px}
    .th-ask__form{order:-1;border-top:none;border-bottom:1px solid #e6e9ef;padding:10px 12px}
    .th-ask__input{font-size:16px;padding:11px 12px;min-width:0}
    .th-ask__send{padding:11px 16px}
    .th-ask__body{padding:12px 16px calc(24px + env(safe-area-inset-bottom,0px));-webkit-overflow-scrolling:touch;overscroll-behavior:contain}
    .th-ask__res{padding:12px 0}
    .th-ask__res img,.th-ask__noimg{width:84px;height:64px}
    body.th-ask-lock{overflow:hidden}
}
</style>

@include('partials.sigou-lines')
<script>
(function () {
    const fab = document.getElementById('thAskFab');
    const panel = document.getElementById('thAskPanel');
    const close = document.getElementById('thAskClose');
    const form = document.getElementById('thAskForm');
    const input = document.getElementById('thAskInput');
    const send = document.getElementById('thAskSend');
    const body = document.getElementById('thAskResults');
    const stage = document.getElementById('thAskStage');
    const sayEl = document.getElementById('thAskSay');
    if (!fab) return;

    const phone = window.matchMedia('(max-width: 640px)');

    const toggle = (open) => {
        panel.hidden = !open;
        fab.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.getElementById('thAsk').classList.toggle('th-ask--open', open);
        // On a phone the panel covers the page; stop the page scrolling behind it.
        document.body.classList.toggle('th-ask-lock', open && phone.matches);
        if (open) input.focus();
        if (open && !stage.classList.contains('is-compact')) {
            say(SigouLines.greeting());
            armNudge();
        }
    };

    fab.addEventListener('click', () => toggle(panel.hidden));
    close.addEventListener('click', () => toggle(false));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !panel.hidden) toggle(false); });

    /* ---- Sigou ---------------------------------------------------------
       Every .th-sigou on the page shares one mood. He blinks, looks around,
       follows the pointer, leans in while you type, gets on the phone while
       the search runs, and reacts to what comes back. */
    const pick = a => a[Math.floor(Math.random() * a.length)];
    const rand = (a, b) => a + Math.random() * (b - a);
    const sigous = () => Array.from(document.querySelectorAll('.th-sigou'))
        .filter(s => s.getBoundingClientRect().width > 0);
    let moodTimer = null;
    let lastPointer = 0;

    const Sigou = {
        mood: 'idle',
        set(state, ms) {
            this.mood = state;
            document.querySelectorAll('.th-sigou').forEach(s => { s.dataset.state = state; });
            clearTimeout(moodTimer);
            if (ms) moodTimer = setTimeout(() => this.set(this.resting()), ms);
        },
        resting() {
            return document.activeElement === input && input.value.trim() ? 'typing' : 'idle';
        },
        look(dx, dy) { // dx, dy in -1..1
            const x = Math.max(-1, Math.min(1, dx)) * 3.6;
            const y = Math.max(-1, Math.min(1, dy)) * 2.8;
            sigous().forEach(s => { s.querySelector('.sg-pupils').style.transform = `translate(${x}px, ${y}px)`; });
        },
        lookAt(px, py) {
            sigous().forEach(s => {
                const r = s.getBoundingClientRect();
                const dx = (px - (r.left + r.width / 2)) / 160;
                const dy = (py - (r.top + r.height * 0.45)) / 160;
                s.querySelector('.sg-pupils').style.transform =
                    `translate(${Math.max(-1, Math.min(1, dx)) * 3.6}px, ${Math.max(-1, Math.min(1, dy)) * 2.8}px)`;
            });
        },
        blink() {
            sigous().forEach(s => {
                const eyes = s.querySelector('.sg-eyes');
                eyes.classList.add('is-blink');
                setTimeout(() => eyes.classList.remove('is-blink'), 110);
            });
        },
        waggle() {
            sigous().forEach(s => {
                const b = s.querySelector('.sg-brows');
                b.classList.remove('is-waggle'); void b.offsetWidth; b.classList.add('is-waggle');
            });
        },
        nod() {
            sigous().forEach(s => { s.classList.remove('is-nod'); void s.offsetWidth; s.classList.add('is-nod'); });
        },
    };

    const say = (text) => {
        sayEl.textContent = text;
        sayEl.classList.remove('is-new'); void sayEl.offsetWidth; sayEl.classList.add('is-new');
    };

    // Life: blinks (sometimes twice), idle glances, the odd eyebrow.
    (function blinkLoop() {
        Sigou.blink();
        if (Math.random() < 0.2) setTimeout(() => Sigou.blink(), 230);
        setTimeout(blinkLoop, rand(2200, 5600));
    })();
    (function glanceLoop() {
        if (Sigou.mood === 'idle' && Date.now() - lastPointer > 2500) {
            Math.random() < 0.3 ? Sigou.look(0, 0) : Sigou.look(rand(-1, 1), rand(-0.8, 0.8));
        }
        if (Sigou.mood === 'idle' && Math.random() < 0.18) Sigou.waggle();
        setTimeout(glanceLoop, rand(1600, 4200));
    })();

    // He is always vaping: a sneaky puff every so often while idle.
    (function puffLoop() {
        setTimeout(() => {
            if (Sigou.mood === 'idle') Sigou.set('vape', 3200);
            puffLoop();
        }, rand(16000, 32000));
    })();

    let raf = 0;
    document.addEventListener('pointermove', (e) => {
        lastPointer = Date.now();
        if (raf || Sigou.mood === 'thinking') return;
        raf = requestAnimationFrame(() => { raf = 0; Sigou.lookAt(e.clientX, e.clientY); });
    }, {passive: true});

    input.addEventListener('focus', () => { Sigou.look(-0.4, 1); });
    input.addEventListener('input', () => {
        if (Sigou.mood === 'thinking') return;
        Sigou.set(input.value.trim() ? 'typing' : 'idle');
        Sigou.nod();
        Sigou.look(-0.6 + Math.min(input.value.length / 40, 1.2), 1);
    });
    input.addEventListener('blur', () => { if (Sigou.mood === 'typing') Sigou.set('idle'); });

    // The last search, so it can be recalled with the up arrow and tweaked.
    let lastQ = '';
    input.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowUp' && !input.value && lastQ) {
            e.preventDefault();
            input.value = lastQ;
            input.setSelectionRange(lastQ.length, lastQ.length);
        }
    });

    document.getElementById('thAskPoke').addEventListener('click', () => {
        if (Sigou.mood === 'thinking') return;
        Sigou.set(pick(['shocked', 'happy']), 1400);
        Sigou.waggle();
        say(SigouLines.poke());
    });

    // While a search runs he keeps talking: a new line every vape cycle.
    let chatter = null;
    const stopChatter = () => { clearInterval(chatter); chatter = null; };

    // Greets when the panel opens; nudges if nobody types for a while.
    let nudged = false;
    let nudgeTimer = null;
    const armNudge = () => {
        clearTimeout(nudgeTimer);
        nudgeTimer = setTimeout(() => {
            if (!panel.hidden && !nudged && !input.value.trim() && !stage.classList.contains('is-compact')) {
                nudged = true;
                say(SigouLines.nudge());
                Sigou.waggle();
            }
        }, 22000);
    };

    const money = n => n ? '£' + Number(n).toLocaleString('en-GB', {maximumFractionDigits: 0}) : '—';
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const q = input.value.trim();
        if (!q) return;

        send.disabled = true;
        if (phone.matches) input.blur(); // drop the keyboard so the results are visible
        body.innerHTML = '';
        stage.classList.remove('is-compact'); // centre stage while he vapes
        Sigou.set('thinking');
        Sigou.look(-0.7, -0.9);
        const lines = SigouLines.thinking(q);
        let li = 0;
        say(lines[li++]);
        stopChatter();
        chatter = setInterval(() => { if (li < lines.length) say(lines[li++]); }, 3200);

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
                Sigou.set('sad', 4000);
                say(SigouLines.error());
                return;
            }

            // Just chatting with him: he answers, no result list.
            if (data.chat) {
                body.innerHTML = '';
                Sigou.set(pick(['happy', 'shocked', 'typing']), 2400);
                Sigou.waggle();
                say(SigouLines.result(data, q));
                lastQ = q;
                input.value = '';
                return;
            }

            // "3 bed" for a whole flat, "room in a 4-bed" for a share.
            const size = (r) => {
                if (r.beds === 0) return 'studio';
                if (r.beds) return r.beds + ' bed';
                if (r.house_size) return (r.room_type ? r.room_type + ' ' : '')
                    + 'room in a ' + r.house_size + '-bed';
                return r.room_type || '';
            };

            const commissionTag = (r) => {
                if (!r.commission) return '';
                const fee = r.fee ? ' \u00b7 ~' + money(r.fee) : '';
                return ' <span class="th-ask__tag" title="'
                    + (r.fee_estimated ? 'Estimated: no rate set for this agency yet' : 'From the agreed rate')
                    + '">COMMISSION' + fee + '</span>';
            };

            const row = (r) => '<a class="th-ask__res" href="' + esc(r.url) + '" target="_blank" rel="noopener">'
                + (r.photo
                    ? '<img src="' + esc(r.photo) + '" alt="" loading="lazy"'
                        + ' onerror="this.replaceWith(Object.assign(document.createElement(\'span\'),'
                        + '{className:\'th-ask__noimg\',textContent:\'no photo\'}))">'
                    : '<span class="th-ask__noimg">no photo</span>')
                + '<span><b>' + esc(r.title)
                + commissionTag(r)
                + '</b><small>' + [
                    r.location ? esc(r.location) : '',
                    money(r.price),
                    size(r) ? esc(size(r)) : '',
                    r.zone ? 'zone ' + r.zone : '',
                    r.agent ? esc(r.agent) : '',
                ].filter(Boolean).join(' \u00b7 ') + '</small>'
                + (r.station ? '<small class="th-ask__stn">' + esc(r.station)
                    + (r.walk ? ', ' + r.walk + ' min walk' : '')
                    + (r.lines && r.lines.length ? ' \u00b7 ' + esc(r.lines.join(', ')) : '')
                    + '</small>' : '')
                + (r.journey ? '<small class="th-ask__jny">' + r.journey.minutes + ' min door to door'
                    + (r.journey.changes === 0 ? ', direct' : ', ' + r.journey.changes
                        + (r.journey.changes === 1 ? ' change' : ' changes')) + '</small>' : '')
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
                 + (data.hub && data.max_journey
                        ? ' within <strong>' + data.max_journey + ' minutes</strong> of ' + esc(data.hub)
                        : (data.radius ? ' within <strong>' + data.radius + ' miles</strong> straight-line' : ''))
                 + (data.widened ? ' <em>(nothing exactly there — showing nearby)</em>' : '')
                 + '</p>';

            const dropped = (data.relaxed || []).filter(function (r) { return r !== 'bedrooms'; });
            if (dropped.length) {
                html += '<p class="th-ask__warn">Nothing matched everything, so these were set aside: <strong>'
                     + esc(dropped.map(function (d) { return d.replace(/_/g, ' '); }).join(', '))
                     + '</strong>. Check each listing before you send it.</p>';
            }
            if ((data.relaxed || []).includes('bedrooms')) {
                html += '<p class="th-ask__warn">Bedroom count set aside \u2014 too few listings state one.</p>';
            }
            if ((data.unanswerable || []).length) {
                html += '<p class="th-ask__warn">We hold no data on <strong>'
                     + esc(data.unanswerable.join(', '))
                     + '</strong>, so that part of the brief was ignored. You will need to ask the landlord.</p>';
            }

            if (data.unplaced) {
                html += '<p class="th-ask__warn">We could not place <strong>'
                     + esc(data.unplaced) + '</strong>, so the results below ignore it. '
                     + 'Try a station name, or a postcode.</p>';
            }

            html += section('Commission — meets the brief', g.commission);
            html += section('No commission — meets the brief', g.standard);
            html += section('Other options worth offering', g.alternatives,
                            'Each of these misses the brief in one way, noted underneath.');

            if (!onBrief && (data.why_none || []).length) {
                html += '<p class="th-ask__warn">Nothing matched. In the whole feed, '
                     + esc(data.why_none.join('; ')) + '.</p>';
            } else if (!onBrief && !g.alternatives.length) {
                html += '<p class="th-ask__hint">Nothing matched, even stretched. Try widening the area or the budget.</p>';
            }

            body.innerHTML = html;

            // Sent: clear the box, like any chat. Up-arrow brings it back.
            lastQ = q;
            input.value = '';

            Sigou.look(0, 0.6);
            if (g.commission.length) Sigou.set('happy', 3200);
            else if (onBrief) Sigou.set('happy', 2600);
            else Sigou.set('shocked', g.alternatives.length ? 1800 : 2600);
            say(SigouLines.result(data, q));
        } catch (err) {
            body.innerHTML = '<p class="th-ask__err">Network error — try again.</p>';
            Sigou.set('sad', 4000);
            say(SigouLines.offline());
        } finally {
            stopChatter();
            stage.classList.add('is-compact'); // then step aside for the results
            send.disabled = false;
        }
    });
})();
</script>
@endauth
