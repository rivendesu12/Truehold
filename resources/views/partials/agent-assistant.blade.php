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
                <span><strong>Sigou</strong><small>Finds the room. Judges the budget.</small></span>
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

        <datalist id="thAskAgents">
            @foreach (config('truehold.agents', []) as $agent)
                <option value="{{ $agent }}"></option>
            @endforeach
        </datalist>

        <form class="th-ask__form" id="thAskForm">
            <button type="button" class="th-ask__new" id="thAskNew" hidden title="Forget the last search and start a new one" aria-label="New search"><span class="th-ask__newlong">New search</span><span class="th-ask__newshort" aria-hidden="true">&#8634;</span></button>
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
.th-ask__new{background:none;border:1px solid #d5dbe5;border-radius:8px;color:#42536b;
    padding:0 12px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap}
.th-ask__new[hidden]{display:none}
.th-ask__newshort{display:none}
@media (max-width:640px){.th-ask__newshort{display:inline}}
.th-ask__new:hover{background:#f4f6fa}
.th-ask__refined{display:inline-block;background:#eef3ff;color:#2c4a8a;border-radius:4px;
    padding:1px 6px;font-size:11px;font-weight:700;margin-right:6px;vertical-align:1px}
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

/* Result bands: green best, yellow other options, light red wildcards */
.th-ask__band{border-radius:12px;padding:6px 12px 2px;margin:10px 0}
.th-ask__band .th-ask__sec{margin:8px 0 2px}
.th-ask__band--best{background:#effaf2;border:1px solid #cdebd6}
.th-ask__band--best .th-ask__sec{color:#1d6b38}
.th-ask__band--other{background:#fffaeb;border:1px solid #f3e2b0}
.th-ask__band--other .th-ask__sec{color:#8a6d1f}
.th-ask__band--wild{background:#fff4f4;border:1px solid #f3cccc}
.th-ask__band--wild .th-ask__sec{color:#9b2c2c}
.th-ask__band .th-ask__res{border-bottom-color:rgba(0,0,0,.06)}
.th-ask__band .th-ask__res:hover{background:rgba(255,255,255,.6)}
.th-ask__none{background:#f4f6fa;border-radius:8px;padding:9px 12px;font-size:13.5px;color:#34445c;margin:0 0 6px}
.th-ask__wildwho{display:block;color:#9b2c2c;font-size:12px;font-weight:600;margin-top:2px}

/* Sourcing agreement card */
.th-ask__deal{border:1px solid #e3e8f0;border-radius:12px;padding:14px 16px;background:#fbfcfe;margin:4px 0 8px}
.th-ask__deal h4{margin:0 0 10px;font-size:15px}
.th-ask__dealrow{display:block;margin:0 0 10px}
.th-ask__dealrow span{display:block;font-size:12px;font-weight:700;color:#42536b;margin-bottom:4px}
.th-ask__dealrow input{width:100%;box-sizing:border-box;border:1px solid #d5dbe5;border-radius:8px;padding:9px 11px;font-size:14px;background:#fff}
.th-ask__dealrow small{display:block;color:#7b8598;font-size:12px;margin-top:3px}
.th-ask__dealrow.is-missing input{border-color:#e0a526;background:#fffaf0}
.th-ask__dealrow.is-missing span::after{content:" — Sigou needs this";color:#b7791f;font-weight:600}
.th-ask__dealnote{font-size:12px;color:#7b8598;margin:2px 0 12px}
.th-ask__dealbtns{display:flex;align-items:center;gap:14px;flex-wrap:wrap}
.th-ask__dealbtn{display:inline-block;background:var(--navy,#152c4e);color:#fff;border:0;border-radius:8px;
    padding:10px 16px;font-weight:700;font-size:14px;cursor:pointer;text-decoration:none}
.th-ask__deallink{font-size:13px;color:#42536b}

/* Office WiFi card: the QR is the point */
.th-ask__wifi{text-align:center}
.th-ask__qr{background:#fff;border:1px solid #e3e8f0;border-radius:14px;padding:12px;width:240px;margin:0 auto}
.th-ask__qr svg{display:block;width:100%;height:auto}
.th-ask__qrhint{font-size:12.5px;color:#5b6472;margin:8px 0 2px}
.th-ask__wifiname{font-family:ui-monospace,Menlo,monospace;font-size:13px;color:#42536b;margin:0 0 8px}
.th-ask__pw{text-align:left;margin:0 0 8px}
.th-ask__pw summary{cursor:pointer;font-size:13px;color:#42536b;text-align:center}
.th-ask__pw .th-ask__wifirow{margin-top:8px}
.th-ask__invno{font-weight:400;color:#7b8598;font-size:13px}
.th-ask__check{display:flex;align-items:center;gap:8px;font-size:13px;color:#42536b;margin:2px 0 12px}
.th-ask__dealbtn--alt{background:#fff;color:#152c4e;border:1px solid #c9d2e0}

/* (older WiFi rows, still used for the password) */
.th-ask__wifirow{display:flex;align-items:center;gap:10px;background:#fff;border:1px solid #e3e8f0;border-radius:10px;padding:9px 12px;margin:0 0 8px}
.th-ask__wifirow div{flex:1;min-width:0}
.th-ask__wifirow span{display:block;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#7b8598}
.th-ask__wifirow b{display:block;font-size:15px;word-break:break-all;font-family:ui-monospace,Menlo,monospace}
.th-ask__money{position:absolute;inset:0;pointer-events:none;overflow:hidden;z-index:5}
.th-ask__money span{position:absolute;top:-40px;font-size:24px;animation:th-money-fall linear forwards}
@keyframes th-money-fall{to{transform:translateY(110vh) rotate(540deg);opacity:.2}}
@media (prefers-reduced-motion:reduce){.th-ask__money{display:none}}
.th-ask__copy{flex:none;border:0;border-radius:8px;background:#0b5d4e;color:#fff;font-weight:700;font-size:13px;padding:8px 12px;cursor:pointer}
.th-ask__wifirow + .th-ask__dealbtn{margin-top:6px}

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
    .th-ask__newlong{display:none}
    .th-ask__new{padding:0 11px;font-size:19px}
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

    // Follow-ups refine the last search on the server ("max 650" keeps the
    // area and zone). "New search" forgets it for the next message.
    let startFresh = false;
    const newBtn = document.getElementById('thAskNew');
    newBtn.addEventListener('click', () => {
        startFresh = true;
        newBtn.hidden = true;
        body.innerHTML = '';
        stage.classList.remove('is-compact');
        Sigou.set('idle');
        say(SigouLines.fresh());
        input.focus();
    });
    input.addEventListener('keydown', (e) => {
        // Enter sends, on every keyboard (not mid-way through an IME word).
        if (e.key === 'Enter' && !e.isComposing && !e.shiftKey) {
            e.preventDefault();
            if (!send.disabled) form.requestSubmit();
            return;
        }
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

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    // The QR is the point: the client scans the agent's screen and is on.
    // The password stays one tap away for a laptop that cannot scan.
    const wifiCard = (w) =>
        '<div class="th-ask__deal th-ask__wifi"><h4>Office WiFi</h4>'
        + (w.qr ? '<div class="th-ask__qr">' + w.qr + '</div><p class="th-ask__qrhint">Client: open the camera and point it here</p>' : '')
        + '<p class="th-ask__wifiname">' + esc(w.ssid) + '</p>'
        + '<details class="th-ask__pw"><summary>Show password</summary><div class="th-ask__wifirow"><div><b>' + esc(w.password) + '</b></div>'
        + '<button type="button" class="th-ask__copy" data-copy="' + esc(w.password) + '">Copy</button></div></details>'
        + (w.qr_url ? '<a class="th-ask__deallink" href="' + esc(w.qr_url) + '" target="_blank" rel="noopener">Open full screen</a>' : '')
        + '</div>';

    // Joy Homes' Zoopla login: each part with its own Copy button.
    const zooplaCard = (z) => {
        const row = (k, v) => '<div class="th-ask__wifirow"><div><span>' + k + '</span><b>' + esc(v) + '</b></div>'
            + '<button type="button" class="th-ask__copy" data-copy="' + esc(v) + '">Copy</button></div>';
        return '<div class="th-ask__deal"><h4>Zoopla login</h4>'
            + row('Email', z.email)
            + '<details class="th-ask__pw"><summary>Show password</summary>' + row('Password', z.password) + '</details>'
            + (z.url ? '<a class="th-ask__dealbtn" href="' + esc(z.url) + '" target="_blank" rel="noopener">Open Zoopla</a>' : '')
            + '</div>';
    };

    const agencyCard = (a) => {
        // Our own sheet: just the link.
        if (a.office) return '<div class="th-ask__deal"><h4>' + esc(a.name) + '</h4>'
            + '<a class="th-ask__dealbtn" href="' + esc(a.link) + '" target="_blank" rel="noopener">Open ' + esc(a.name) + '</a></div>';
        const fact = (k, v) => v ? '<div class="th-ask__wifirow"><div><span>' + k + '</span><b style="font-family:inherit">' + esc(v) + '</b></div></div>' : '';
        return '<div class="th-ask__deal"><h4>' + esc(a.name) + '</h4>'
            + (a.link ? '<a class="th-ask__dealbtn" href="' + esc(a.link) + '" target="_blank" rel="noopener">Open ' + esc(a.name) + '\'s list</a>' : '<p class="th-ask__hint">No link in the agencies sheet yet.</p>')
            + '<div style="margin-top:12px">'
            + fact('Max age', a.max_age ? String(a.max_age) : '')
            + fact('Commission', a.commission)
            + fact('Agent share', a.agent_share)
            + fact('Post on SpareRoom', a.post_on_spareroom)
            + '</div>'
            + (a.rooms ? '<button type="button" class="th-ask__dealbtn th-ask__dealbtn--alt" data-ask="show me all ' + esc(a.name) + ' rooms">Show their ' + a.rooms + ' rooms here</button>'
                : '<p class="th-ask__hint">We hold none of their rooms right now.</p>')
            + '</div>';
    };

    // Money makes it rain for a couple of seconds.
    const moneyRain = () => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const box = document.createElement('div');
        box.className = 'th-ask__money';
        for (let i = 0; i < 22; i++) {
            const n = document.createElement('span');
            n.textContent = ['\ud83d\udcb7', '\ud83d\udcb8', '\ud83d\udcb0', '\ud83e\ude99'][i % 4];
            n.style.left = (Math.random() * 95) + '%';
            n.style.animationDelay = (Math.random() * 0.9) + 's';
            n.style.animationDuration = (1.6 + Math.random() * 1.4) + 's';
            box.appendChild(n);
        }
        panel.appendChild(box);
        setTimeout(() => box.remove(), 4200);
    };

    // Bank details: every account the agency takes payment on, each figure
    // with its own Copy button, and their tenant reference forms.
    const bankCard = (b) => {
        const row = (k, v) => v ? '<div class="th-ask__wifirow"><div><span>' + k + '</span><b>' + esc(v) + '</b></div>'
            + '<button type="button" class="th-ask__copy" data-copy="' + esc(v) + '">Copy</button></div>' : '';
        const several = new Set((b.accounts || []).map(a => a.company)).size > 1;
        return '<div class="th-ask__deal"><h4>' + esc(b.name) + ' \u00b7 bank details</h4>'
            + (b.accounts || []).map(a => '<div style="margin-top:12px"><p class="th-ask__hint" style="margin:0 0 4px"><b>'
                + esc([several ? a.company : null, a.bank].filter(Boolean).join(' \u00b7 ')) + '</b></p>'
                + row('Account name', a.account_name) + row('Sort code', a.sort_code) + row('Account number', a.account_number)
                + row('BIC', a.bic) + row('IBAN', a.iban) + row('Reference', a.reference) + '</div>').join('')
            + (b.forms || []).map(f => '<a class="th-ask__deallink" style="display:block;margin-top:10px" href="' + esc(f.url) + '" target="_blank" rel="noopener">' + esc(f.label) + '</a>'
                + (f.note ? '<small class="th-ask__hint" style="display:block">' + esc(f.note) + '</small>' : '')).join('')
            + '</div>';
    };

    // Buttons that ask Sigou something on the agent's behalf.
    body.addEventListener('click', (e) => {
        const b = e.target.closest('[data-ask]');
        if (!b) return;
        input.value = b.dataset.ask;
        form.requestSubmit();
    });

    const invoiceCard = (a) => {
        const missing = a.missing || [];
        const field = (name, label, control, hint) =>
            '<label class="th-ask__dealrow' + (missing.includes(name) ? ' is-missing' : '') + '">'
            + '<span>' + label + '</span>' + control + (hint ? '<small>' + hint + '</small>' : '') + '</label>';
        return '<form class="th-ask__deal" method="POST" action="' + esc(a.pdf_url) + '">'
            + '<h4>Invoice <small class="th-ask__invno">#' + esc(a.next_number) + '</small></h4>'
            + '<input type="hidden" name="_token" value="' + esc(csrf()) + '">'
            + field('client_name', 'Bill to (client\'s full name)',
                '<input name="client_name" required maxlength="120" placeholder="As written on their ID" value="' + esc(a.client_name || '') + '">')
            + field('amount', 'Amount (£)',
                '<input name="amount" type="number" min="1" step="1" required value="' + esc(a.amount == null ? '' : a.amount) + '">', 'Sourcing agreement · £220 cash · £250 transfer')
            + field('date', 'Date', '<input name="date" type="date" value="' + esc(a.date || '') + '">')
            + '<input type="hidden" name="paid" value="0">'
            + '<label class="th-ask__check"><input type="checkbox" name="paid" value="1"' + (a.paid === false ? '' : ' checked') + '> Paid already (balance due £0)</label>'
            + '<div class="th-ask__dealbtns"><button type="submit" class="th-ask__dealbtn">Download invoice</button></div>'
            + '<p class="th-ask__err" data-deal-error hidden></p>'
            + '</form>';
    };

    // Which result an agent opens, so we learn which band is useful.
    let logId = null;
    body.addEventListener('click', (e) => {
        const a = e.target.closest('.th-ask__res');
        if (!a || !logId || !navigator.sendBeacon) return;
        const f = new FormData();
        f.append('_token', csrf());
        f.append('log', logId);
        f.append('id', a.dataset.id || '');
        f.append('band', a.dataset.band || 'best');
        f.append('pos', a.dataset.pos || 0);
        navigator.sendBeacon(@json(route('agent.search.click')), f);
    });

    body.addEventListener('input', (e) => {
        const row = e.target.closest('.th-ask__dealrow');
        if (row) row.classList.toggle('is-missing', !e.target.value.trim());
    });

    body.addEventListener('click', async (e) => {
        const btn = e.target.closest('.th-ask__copy');
        if (!btn) return;
        try {
            await navigator.clipboard.writeText(btn.dataset.copy);
            btn.textContent = 'Copied';
            setTimeout(() => { btn.textContent = 'Copy'; }, 1600);
        } catch (err) {
            btn.textContent = 'Select it';
        }
    });

    const agreementCard = (a) => {
        if (a.template) {
            return '<div class="th-ask__deal"><h4>Sourcing agreement — blank template</h4>'
                + '<a class="th-ask__dealbtn" href="' + esc(a.template_url) + '" download>Download blank template</a></div>';
        }
        const missing = a.missing || [];
        const field = (name, label, control, hint) =>
            '<label class="th-ask__dealrow' + (missing.includes(name) ? ' is-missing' : '') + '">'
            + '<span>' + label + '</span>' + control + (hint ? '<small>' + hint + '</small>' : '') + '</label>';
        return '<form class="th-ask__deal" method="POST" action="' + esc(a.pdf_url) + '">'
            + '<h4>Sourcing agreement</h4>'
            + '<input type="hidden" name="_token" value="' + esc(csrf()) + '">'
            + field('client_name', 'Client\'s full name',
                '<input name="client_name" required maxlength="120" placeholder="As written on their ID" value="' + esc(a.client_name || '') + '">')
            + field('fee', 'Sourcing fee (£)',
                '<input name="fee" type="number" min="1" step="1" required value="' + esc(a.fee == null ? '' : a.fee) + '">', '£220 cash · £250 transfer')
            + field('date', 'Date', '<input name="date" type="date" value="' + esc(a.date || '') + '">')
            + field('sign_as', 'Your name (signs as the Sourcer)', '<input name="sign_as" list="thAskAgents" autocomplete="off" required maxlength="60" placeholder="Pick or type your name" value="' + esc(a.sign_as || '') + '">')
            + '<p class="th-ask__dealnote">Referral bonus £' + esc(a.referral) + ' per referred client, filled in for you. The client signs by hand.</p>'
            + '<div class="th-ask__dealbtns"><button type="submit" class="th-ask__dealbtn">Download PDF</button>'
            + '<button type="submit" class="th-ask__dealbtn th-ask__dealbtn--alt" formaction="' + esc(a.invoice_url || '/tools/invoice/pdf') + '">Invoice too</button>'
            + '<a class="th-ask__deallink" href="' + esc(a.template_url) + '" download>Blank template</a></div>'
            + '<p class="th-ask__err" data-deal-error hidden></p>'
            + '</form>';
    };

    // Fetch the PDF and save it, rather than posting the form into a new tab:
    // in-app browsers (WhatsApp, this one) turn that into a GET and lose it.
    body.addEventListener('submit', async (e) => {
        const deal = e.target.closest('.th-ask__deal');
        if (!deal) return;
        e.preventDefault();
        const btn = e.submitter || deal.querySelector('button[type=submit]');
        const label = btn.textContent;
        const err = deal.querySelector('[data-deal-error]');
        err.hidden = true;
        btn.disabled = true;
        btn.textContent = 'Making it…';
        try {
            const res = await fetch(btn.getAttribute('formaction') || deal.action, {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json'},
                body: new FormData(deal),
            });
            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                throw new Error(Object.values(data.errors || {}).flat()[0] || 'Could not make the agreement.');
            }
            const blob = await res.blob();
            const name = (res.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/);
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = name ? name[1] : 'Sourcing Agreement.pdf';
            document.body.appendChild(link);
            link.click();
            setTimeout(() => { URL.revokeObjectURL(link.href); link.remove(); }, 4000);
            Sigou.set('happy', 2600);
            say(link.download.startsWith('Invoice')
                ? pick(['Invoice done bro. ' + link.download.split(' - ')[0] + ', check the downloads', 'There, invoiced. Now make sure he actually paid 😂'])
                : pick(['Done bro, check your downloads. Now get the signature', 'Ready. Print it before he change mind 😂', 'There. Check the name with the ID malaka']));
        } catch (ex) {
            err.textContent = ex.message;
            err.hidden = false;
            Sigou.set('sad', 3000);
        } finally {
            btn.disabled = false;
            btn.textContent = label;
        }
    });

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
                body: JSON.stringify({q, fresh: startFresh}),
            });
            const data = await res.json();

            if (!res.ok) {
                body.innerHTML = '<p class="th-ask__err">' + esc(data.error || 'Something went wrong.') + '</p>';
                Sigou.set('sad', 4000);
                say(SigouLines.error());
                return;
            }

            // A partner agency: their list, their terms, their rooms here.
            if ('agency_asked' in data) {
                const found = data.agency || data.agency_bank;
                body.innerHTML = data.agency_bank ? bankCard(data.agency_bank) : (data.agency ? agencyCard(data.agency) : '');
                if (data.agency_bank) {
                    // Rubs his hands while it rains.
                    moneyRain();
                    Sigou.set('happy', 3200);
                    say(Math.random() < 0.5 && (data.sigou || '').trim() ? data.sigou : SigouLines.bank(data.agency_bank.name));
                } else if ('agency_bank' in data) {
                    Sigou.set('shocked', 2000);
                    say(SigouLines.noBank(data.agency_asked));
                } else {
                    Sigou.set(found ? 'happy' : 'shocked', 2000);
                    say((data.sigou || '').trim() || (found ? 'Here ' + found.name + ' bro' : 'Who is that? Not in our list'));
                }
                lastQ = q;
                input.value = '';
                return;
            }

            // A sourcing-fee invoice: same idea as the agreement card.
            if (data.invoice) {
                body.innerHTML = invoiceCard(data.invoice);
                const gap = body.querySelector('.is-missing input');
                Sigou.set(data.invoice.ready ? 'happy' : 'typing', 2400);
                say((data.sigou || '').trim() || SigouLines.invoice(data.invoice));
                lastQ = q;
                input.value = '';
                if (gap && !phone.matches) gap.focus();
                return;
            }

            // The office WiFi: a QR for the client to scan.
            if ('wifi' in data) {
                body.innerHTML = data.wifi ? wifiCard(data.wifi) : '';
                Sigou.set('happy', 2000);
                say(data.wifi ? SigouLines.wifi() : data.sigou); // his own lines: the model's wander off topic
                lastQ = q;
                input.value = '';
                return;
            }

            // The Zoopla login.
            if ('zoopla_login' in data) {
                body.innerHTML = data.zoopla_login ? zooplaCard(data.zoopla_login) : '';
                Sigou.set('happy', 2000);
                say(data.zoopla_login ? SigouLines.zoopla() : data.sigou);
                lastQ = q;
                input.value = '';
                return;
            }

            // A sourcing agreement: a card with what he has, the gaps marked,
            // and the download. The agent can fill a gap here or tell him.
            if (data.agreement) {
                body.innerHTML = agreementCard(data.agreement);
                const firstGap = body.querySelector('.is-missing input');
                const a = data.agreement;
                Sigou.set(a.template || a.ready ? 'happy' : 'typing', 2400);
                say((data.sigou || '').trim() || SigouLines.agreement(a));
                lastQ = q;
                input.value = '';
                if (firstGap && !phone.matches) firstGap.focus();
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

            const row = (r, i, bandName) => '<a class="th-ask__res" href="' + esc(r.url) + '" target="_blank" rel="noopener"'
                + ' data-id="' + esc(r.id || '') + '" data-band="' + esc(bandName || '') + '" data-pos="' + (i || 0) + '">'
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
                    r.agent && !r.market ? esc(r.agent) : '',
                ].filter(Boolean).join(' \u00b7 ') + '</small>'
                + (r.station ? '<small class="th-ask__stn">' + esc(r.station)
                    + (r.walk ? ', ' + r.walk + ' min walk' : '')
                    + (r.lines && r.lines.length ? ' \u00b7 ' + esc(r.lines.join(', ')) : '')
                    + '</small>' : '')
                + (r.journey ? '<small class="th-ask__jny">' + r.journey.minutes + ' min door to door'
                    + (r.journey.changes === 0 ? ', direct' : ', ' + r.journey.changes
                        + (r.journey.changes === 1 ? ' change' : ' changes')) + '</small>' : '')
                + (r.why ? '<small class="th-ask__why">' + esc(r.why) + '</small>' : '')
                + (r.household ? '<small class="th-ask__stn">\ud83d\udc65 ' + esc(r.household) + '</small>' : '')
                + (r.note ? '<small class="th-ask__stn">\u2139\ufe0f ' + esc(r.note) + '</small>' : '')
                + (r.market ? '<small class="th-ask__wildwho">' + esc(r.agent || 'Agency not named')
                    + ' \u00b7 \ud83d\udcde number on the ad</small>' : '')
                + '</span></a>';

            // Three bands, in the order an agent works through them.
            const band = (cls, title, items, note) => {
                if (!items || !items.length) return '';
                return '<section class="th-ask__band th-ask__band--' + cls + '">'
                    + '<h4 class="th-ask__sec">' + title + ' <span>' + items.length + '</span></h4>'
                    + (note ? '<p class="th-ask__secnote">' + esc(note) + '</p>' : '')
                    + items.map((r, i) => row(r, i, cls)).join('') + '</section>';
            };

            logId = data.log_id || null;
            const g = data.groups || {commission: [], standard: [], alternatives: [], wildcards: []};
            const best = (g.commission || []).concat(g.standard || []);
            const others = g.alternatives || [];
            const wild = g.wildcards || [];
            const onBrief = best.length;

            let html = '';
            if (data.explanation) html += '<p class="th-ask__note">' + esc(data.explanation) + '</p>';

            if (data.refined) html += '<p class="th-ask__hint"><span class="th-ask__refined" title="Your last search, with this change">REFINED</span> your last search</p>';

            if (data.unplaced) {
                html += '<p class="th-ask__warn">We could not place <strong>'
                     + esc(data.unplaced) + '</strong>, so the results below ignore it. '
                     + 'Try a station name, or a postcode.</p>';
            }

            // Nothing exact: say so plainly, then the closest options.
            if (!onBrief && (others.length || wild.length)) {
                html += '<p class="th-ask__none">Nothing matches that exactly'
                     + (data.area ? ' in <strong>' + esc(data.area) + '</strong>' : '')
                     + '. Here are the closest options.</p>';
            }

            html += band('best', '👍 Best matches', best,
                data.hub && data.max_journey ? 'Within ' + data.max_journey + ' minutes of ' + data.hub
                    : (data.area ? 'In ' + data.area : ''));
            html += band('other', 'Other options', others, 'Close to the brief; how each one differs is noted underneath.');
            html += band('wild', 'Wildcards', wild, 'SpareRoom letting agents, free to contact, not our partners. Call and agree terms before offering.');

            if (!onBrief && !others.length && !wild.length) {
                html += (data.why_none || []).length
                    ? '<p class="th-ask__warn">Nothing matched. In the whole feed, ' + esc(data.why_none.join('; ')) + '.</p>'
                    : '<p class="th-ask__hint">Nothing matched, even stretched. Try widening the area or the budget.</p>';
            }

            body.innerHTML = html;

            // Sent: clear the box, like any chat. Up-arrow brings it back.
            lastQ = q;
            input.value = '';
            startFresh = false;
            newBtn.hidden = false;

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
