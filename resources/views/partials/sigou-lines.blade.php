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
        if (h < 11) return pick(@json(\App\Support\SigouOffice::lines('morning')).concat([
            'Office is open 🕴️ What the client want?',
            'Kalimera. Who is coming and what time? Ok you. Tell me',
            'Ela, morning. Let m finish my vape and tell me',
            'Too early for this man. Ok go',
        ]));
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

    // Lines about the office crew live in a private file on the server
    // (App\Support\SigouOffice), not in this public repository.
    const pokes = @json(\App\Support\SigouOffice::lines('pokes')).concat([
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
        'LIST UPDATED @all. Nobody reads it. Crazy tragic',
        'Single, Greek, with a vape. Ladies the queue is here 😂😂😂',
        'Tinder is dead today so iam all yours bro',
        'Find me a girlfriend and I give you my commission 😂😂😂',
        'After this we go out find girls ok? Deal',
        'Iam not bored, iam single. Is different',
        'Who is coming and what time?? 🕴️',
        'Why you are tagging me? Ah, poking. Same thing',
        'Reply Yes Sir 🫡',
        'Who reset the Zoopla password again? Confess',
        '8 pounds each for the fridge. Iam still waiting for some of you',
        'Cleaning day. We need to respect the place we are working',
        'Leave a review on Google. And Trustpilot. Now pls',
        'Budget first then we talk',
        'Commission rooms first. Obviously',
        '7 for the chicken or tomorrow on you 🤣',
    ]);

    const nudges = ['Ela, type smth malaka', '…?', 'Iam waiting man *puff*', 'Hello? The client is waiting re', 'You fell asleep?'];

    // While the search runs: something about what was typed first, then
    // filler. A new one comes up with every puff. Room jokes only for a room
    // search: "girls in the house" under a password request makes no sense.
    const thinking = (q) => {
        const lines = [];
        const neutral = ['On sec… *puff*', 'Let m check', 'Is coming, is coming', 'Hold on, one puff…'];
        const errand = [
            [/\bwi-?fi\b|internet/i, ['The WiFi, one sec', 'Let m find the code *puff*']],
            [/\b(bank|sort code|account number|iban|deposit form|how (do i|to) pay)\b/i, ['Money talk. Let m check the numbers twice', 'Bank details, one sec *puff*']],
            [/\b(log ?in|logins?|password|credentials?|acc(ount)?)\b/i, ['Password… where Giacomo put it this time', 'Let m find it. Dont tell nobody *puff*']],
            [/\b(agreement|contract|invoice)\b/i, ['Paperwork. My favourite 🙄', 'Let m do the papers *puff*']],
            [/\b(link|list|sheet|vacancy|who lives|flatmates)\b/i, ['Let m find it', 'One sec, looking *puff*']],
        ].find(([re]) => has(q, re));
        if (errand) return errand[1].concat(neutral);
        const roomSearch = has(q, /\b(room|flat|studio|double|single|en.?suite|house|bed|pcm|pw|budget|zone|under|max|near|couple|client)\b|\d{3,4}/i);
        if (!roomSearch) return neutral.sort(() => Math.random() - 0.5);

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
        const filler = neutral.concat([
            'Triple mango. Ok where were we…',
            'Iam reading all of them man, relax',
            'Checking the rooms… and if there are girls in the house *puff*',
        ]);
        return lines.concat(filler.sort(() => Math.random() - 0.5));
    };

    // What the search found. Short, because his line about the brief follows.
    const found = (data) => {
        const g = data.groups || {commission: [], standard: [], alternatives: []};
        const paying = g.commission.length;
        const onBrief = paying + g.standard.length;

        if (data.unplaced) return 'Where is ' + data.unplaced + '? Never heard man. Try a station';
        const wild = (g.wildcards || []).length;
        if (!onBrief && !g.alternatives.length && wild) return pick([
            'Nothing from our people. Only wildcards from SpareRoom agents, call them first bro',
            'Our partners have nothing. ' + wild + ' wildcards, risky but agents always want to do business',
        ]);
        if (!onBrief && !g.alternatives.length) return pick([
            'Nothing. Zero. Crazy tragic',
            'What do you mean zero? …Zero yeah',
            'Nothing man. The client need to open the wallet',
        ]);
        if (!onBrief) return pick(['Nothing exactly like that malaka. Here the closest ones', 'Not exact but close. The client will survive', 'Nothing exact. Look the yellow ones bro']);
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

    const error = () => pick(['Smth broke. Not me. Probably Giacomo', 'Ffs is crashed. Try again', 'Piece of shit computer. Again']);
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
        if (m.includes('sign_as')) return 'And who signs, you? Put your name bro';
        return pick(['Ready bro. Check the name with the ID and download', 'Agreement ready. Now get the signature before he change mind 😂']);
    };

    const wifi = () => pick([
        'Here bro. Client points the camera and is in',
        'Scan and go. 10 gig, faster than my love life 😂',
        'Camera on the code, done. And no Netflix in the office',
    ]);

    const zoopla = () => pick([
        'Zoopla login bro. Dont change the password or Giacomo kills us both',
        'Here. Log in, post, log out. No touching the settings malaka',
        'Zoopla, here. Copy paste and dont share it with nobody bro',
        'Email and password, done. If it asks for a code, ask Giacomo',
        'Viewing ads only bro. Sourcing on Zoopla and Giacomo sends the police 😂',
    ]);

    const invoice = (a) => {
        const m = a.missing || [];
        if (m.includes('client_name') && m.includes('amount')) return 'Invoice for who? And how much, cash 220 or transfer 250?';
        if (m.includes('client_name')) return 'Invoice for who, malaka? Full name';
        if (m.includes('amount')) return 'How much? Cash 220 or transfer 250?';
        return pick(['Invoice ready bro. Number ' + a.next_number + ', dont lose it', 'Ready. Money first, then invoice, you know the rules 😂']);
    };

    // Bank details: money is involved, so he gets excited and paranoid.
    const bank = (name) => pick([
        'Money money money 💸 Check every number twice malaka, banks dont give back',
        'Here the account. One wrong digit and the deposit goes on holiday without us',
        'Sort code, account, done. Now where is my commission 😂',
        'Pay them and screenshot it. No screenshot, no room bro',
        'Bank details for ' + name + '. If the money disappears is not Sigou fault',
        'Rich people stuff 💷 Copy, paste, pray',
        'Holding deposit first, form after. You know the drill malaka',
        'Send it, then buy me a Lost Mary. Triple mango 😂',
        'Ooh money. I feel it in my hands already 🤑',
    ]);
    const noBank = (name) => pick([
        'No bank details for ' + name + ' yet malaka. Ask Giacomo, he has everything',
        'I dont have their account bro. Dont send money to random people pls',
    ]);

    return {greeting, fresh, agreement, wifi, zoopla, invoice, bank, noBank, poke: () => pick(pokes), nudge: () => pick(nudges), thinking, result, error, offline};
})();
</script>
