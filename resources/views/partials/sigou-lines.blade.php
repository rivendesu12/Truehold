{{-- What Sigou says. Kept apart from the panel code so the jokes can be
     edited without touching anything that works.

     The voice comes from two years of his WhatsApp: short, typed fast,
     Greek-English ("is fine", "iam gonna", "are y", "let m check"), "man"
     and "bro" at the end, "ela", "malaka", "ffs", "crazy tragic", 😂😂😂.

     Two sources: the model writes one line about the brief in his voice
     (data.sigou), and these canned lines cover everything the model cannot
     see: the time of day, the wait, and what the search actually found.
     Output is set with textContent. --}}
<script>
window.SigouLines = (function () {
    const pick = a => a[Math.floor(Math.random() * a.length)];
    const money = n => '£' + Number(n).toLocaleString('en-GB', {maximumFractionDigits: 0});
    const has = (q, re) => re.test(q || '');

    const greeting = () => {
        const h = new Date().getHours();
        const day = new Date().getDay();
        // Late: they are at home by now, and so is he, still on it.
        if (h >= 21 || h < 5) return pick([
            'Still working at this time? Respect bro. Ok tell me',
            'Iam in bed vaping and you send me clients. Ok go 😂',
            'Is late malaka, the client cant wait till tomorrow? Ok ok tell me',
            'Night shift. Quick one and we sleep',
        ]);
        if (h < 11) return pick([
            'Kalimera malaka. What the client want?',
            'Ela, morning. Let m finish my vape and tell me',
            'Too early for this man. Ok go',
        ]);
        if (h >= 12 && h < 14) return pick([
            'Iam hangry bro, be quick',
            'Lunch time malaka. One search then chicken',
        ]);
        if (h >= 18) return pick([
            'Evening man. One more client and I switch off. Go',
            'Iam on the sofa already but ok, tell me',
            'After 6 is overtime bro 😂 what we need?',
        ]);
        if (day === 5) return pick([
            'Is Friday re. One more deal and we go out find girls',
            'Friday man. Close smth and tonight we find me a girlfriend',
            'Friday. Iam single and ready. Oh you mean the client? Ok go',
        ]);
        return pick([
            'Ela malaka. Budget, area, what the client want?',
            'Tell me what the client want. Budget, area, commute',
            'What do we need today?',
            'Ela. Who is the client and how much he have?',
            '?',
        ]);
    };

    const pokes = [
        '?',
        'What',
        'What do you mean',
        'On sec',
        'Let m check… no, you check',
        'Oi. Iam working',
        'Iam not lazy iam on vape break',
        'Triple mango. Best flavour dont argue',
        'I have 17 blood pressure man dont stress me',
        'No trains westbound again ffs',
        'Where are y? Ah here',
        'Stop poking me or iam gonna sent the police',
        'Crazy tragic day today',
        'Who has the keys? Always the keys ffs',
        'Single, Greek, with a vape. Ladies the queue is here 😂😂😂',
        'Tinder is dead today so iam all yours bro',
        'Find me a girlfriend and I give you my commission 😂😂😂',
        'After this we go out find girls ok? Deal',
        'Which area has the most single girls? For research bro',
        'Iam not bored, iam single. Is different',
        'Stop poking me, poke a girl for me 😂',
        'Budget first then we talk',
        'Commission rooms first. Obviously',
        '7 for the chicken or tomorrow on you 🤣',
    ];

    const nudges = ['Ela, type smth malaka', '…?', 'Iam waiting man *puff*', 'Hello? The client is waiting re', 'You fell asleep?'];

    // While the search runs: something about the brief first, then filler.
    // A new one comes up with every puff.
    const thinking = (q) => {
        const lines = [];
        const budget = (q || '').match(/£?\s?(\d{3,4})(?!\s?(min|mins|minutes))/i);
        if (budget) {
            const b = Number(budget[1]);
            if (b < 650) lines.push(money(b) + '? Malaka this is London not Athens…');
            else if (b > 1400) lines.push(money(b) + '? Who is the client, a footballer? *puff*');
            else lines.push(money(b) + '… ok let m do the maths *puff*');
        }
        if (has(q, /en.?suite/i)) lines.push('Ensuite of course. Everybody want his own toilet…');
        if (has(q, /\b(asap|urgent|today|tomorrow|now)\b/i)) lines.push('ASAP. Everybody want it yesterday ffs');
        if (has(q, /\bcheap/i)) lines.push('Cheap. My favourite word *puff*');
        if (has(q, /\b(pet|dog|cat)s?\b/i)) lines.push('A pet? Ffs… ok iam looking');
        if (has(q, /\bmin(s|utes)?\b/i)) lines.push('Checking TfL. If is westbound we are finished');
        if (has(q, /clapham|shoreditch|soho|brixton|camden|hackney/i)) lines.push('Good area. Full of girls bro. For the client I mean 😂');
        const filler = [
            'On sec… *puff*',
            'Let m check',
            'Triple mango. Ok where were we…',
            'Iam reading all of them man, relax',
            'Is coming, is coming',
            'Hold on, one puff…',
            'Checking the rooms… and if there are girls in the house *puff*',
            'Let m see if any girls live there. For the client I mean 😂',
        ];
        return lines.concat(filler.sort(() => Math.random() - 0.5));
    };

    // What the search found. Short, because his line about the brief follows.
    const found = (data) => {
        const g = data.groups || {commission: [], standard: [], alternatives: []};
        const paying = g.commission.length;
        const onBrief = paying + g.standard.length;

        if (data.unplaced) return 'Where is ' + data.unplaced + '? Never heard man. Try a station';
        if (!onBrief && !g.alternatives.length) return pick([
            'Nothing. Zero. Crazy tragic',
            'What do you mean zero? …Zero yeah',
            'Nothing man. The client need to open the wallet',
        ]);
        if (!onBrief) return pick(['Nothing exact but these are close. Sell it bro', 'Not perfect but close. The client will survive']);
        if (onBrief > 15) return onBrief + '?? Too many re, be more picky.' + (paying ? ' ' + paying + ' pay commission tho' : '');
        if (paying) return pick([
            'Ela! ' + paying + ' with commission 🤑',
            paying + (paying === 1 ? ' pays' : ' pay') + ' commission. Tonight we eat',
            paying + ' with commission. Send before someone else do',
        ]);
        if (onBrief === 1) return 'One. Only one. Send it quick before it goes';
        return pick([
            onBrief + ' rooms, no commission. We work for free now? Ok',
            'Found ' + onBrief + '. No commission but a deal is a deal',
        ]);
    };

    // Used only when the model did not send a line of its own.
    const aboutBrief = (data, q) => {
        const b = data.brief || {};
        const extra = [];
        if (b.max_price && b.max_price < 650) extra.push(money(b.max_price) + ' in London. Brave man');
        if (b.max_price && b.max_price > 1400) extra.push('With ' + money(b.max_price) + ' I would rent it myself');
        if (b.ensuite_only) extra.push('Ensuite. Everybody want his toilet nobody want to pay');
        if (b.couples === true) extra.push('Couple in one room, romantic. Cheaper as well');
        if (b.pets === true) extra.push('Landlords love pets like I love the Northern line 😂😂😂');
        if (b.no_deposit) extra.push('No deposit. The client want everything free malaka');
        if (b.parking) extra.push('Parking in London. Crazy');
        if (b.garden) extra.push('A garden. Sure re, and a swimming pool?');
        if (b.max_zone === 1 || b.region === 'central') extra.push('Central with this budget? 😂😂😂');
        if (b.minutes_from_landmark) extra.push(b.minutes_from_landmark + ' min with TfL? Optimistic');
        return extra.length ? pick(extra) : '';
    };

    // The model wrote one line for each outcome, about this exact brief; show
    // the one that happened. The canned lines only fill in when it did not.
    const result = (data, q) => {
        if (data.chat) return data.sigou || pick(pokes);
        const g = data.groups || {commission: [], standard: [], alternatives: []};
        const paying = g.commission.length;
        const onBrief = paying + g.standard.length;
        const said = s => (s || '').trim();

        if (onBrief && said(data.sigou_found)) {
            return said(data.sigou_found) + (paying ? ' · ' + paying + ' with commission 🤑' : '');
        }
        if (!onBrief && !data.unplaced && said(data.sigou_none)) {
            return said(data.sigou_none) + (g.alternatives.length ? ' · These are close tho, have a look' : '');
        }
        const lead = found(data);
        const quip = said(data.sigou) || aboutBrief(data, q);
        return quip ? lead + '. ' + quip : lead;
    };

    const error = () => pick(['Smth broke. Not me. Probably Giaco', 'Ffs is crashed. Try again', 'Piece of shit computer. Again']);
    const offline = () => pick(['WiFi is dead. Like Central line on Monday', 'Internet ate it. Try again man']);

    const fresh = () => pick([
        'Ok, new client. Tell me',
        'Forget the last one. Who is next?',
        'Clean page bro. Budget, area, go',
        'New one? Ela, tell me',
    ]);

    // Fallback when the model sent no line of its own for an agreement.
    const agreement = (a) => {
        if (a.template) return pick(['Here the blank one bro. Print and go', 'Empty template, ready. Dont lose it like the keys']);
        const m = a.missing || [];
        if (m.includes('client_name') && m.includes('fee')) return 'Ela, full name as on the ID? And cash 220 or transfer 250?';
        if (m.includes('client_name')) return 'Full name as on the ID, malaka. Spell it right';
        if (m.includes('fee')) return 'Cash 220 or transfer 250?';
        return pick(['Ready bro. Check the name with the ID and download', 'Agreement ready. Now get the signature before he change mind 😂']);
    };

    const wifi = () => pick([
        'Here the WiFi bro. Dont give it to the whole building',
        'WiFi for the client. Fast one, 10 gig, better than my love life 😂',
        'Copy and send. And tell the client no Netflix in the office',
    ]);

    return {greeting, fresh, agreement, wifi, poke: () => pick(pokes), nudge: () => pick(nudges), thinking, result, error, offline};
})();
</script>
