{{-- What Sigou says. Kept apart from the panel code so the jokes can be
     edited without touching anything that works. Everything here is chosen
     from the brief the agent typed, what the assistant understood from it,
     what came back, and the time of day. Output is set with textContent. --}}
<script>
window.SigouLines = (function () {
    const pick = a => a[Math.floor(Math.random() * a.length)];
    const money = n => '£' + Number(n).toLocaleString('en-GB', {maximumFractionDigits: 0});
    const has = (q, re) => re.test(q || '');

    // Places he has an opinion on. Matched against the brief and the location.
    const places = [
        [/canning town/i, ['Canning Town. My second home, malaka.', 'Canning Town, I know every door there.']],
        [/stratford/i, ['Stratford. Westfield, the mall of dreams.', 'Stratford, good. Central line, when it works.']],
        [/shoreditch/i, ['Shoreditch. Coffee is £6 there, bro.', 'Shoreditch. Beards and oat milk. I fit in.']],
        [/canary wharf/i, ['Canary Wharf. Bankers. Tell them to pay commission.', 'Canary Wharf, everybody in suits. Not me.']],
        [/brixton/i, ['Brixton. Good food, crazy nights.']],
        [/hackney/i, ['Hackney. Everybody is a DJ there.']],
        [/whitechapel/i, ['Whitechapel. Elizabeth line, we are fancy now.']],
        [/camden/i, ['Camden. Tourists everywhere, ffs.']],
        [/(king'?s cross|kings cross)/i, ['King\'s Cross. Nice, but the prices, re…']],
        [/(bond street|oxford circus|soho|mayfair)/i, ['Central central. Hahaha. OK, I look anyway.']],
        [/(ealing|acton|shepherd|hammersmith|westbound|west london)/i, ['West? Hope the trains go westbound today. They never do.']],
        [/(greenwich|lewisham|deptford)/i, ['South east. The DLR, my old friend.']],
        [/(wembley|harrow)/i, ['Wembley. Far, but the rooms are big.']],
    ];

    const greetings = () => {
        const h = new Date().getHours();
        const day = new Date().getDay();
        if (h < 11) return pick(['Kalimera malaka. What does the client want?', 'Too early re. Let me finish my vape. OK, go.', 'Morning. Coffee first? No? OK, tell me.']);
        if (h >= 12 && h < 14) return pick(['I\'m hangry, make it quick. What do they want?', 'Lunch time, malaka. One search, then food.']);
        if (h >= 19) return pick(['Why are you still working? OK, tell me.', 'Late one, eh. What do we need?']);
        if (day === 5) return pick(['Friday re. One more deal and we go. Tell me.', 'Friday. Close something and we celebrate.']);
        return pick([
            'Ela malaka. Tell me what the client wants: budget, area, commute, room type.',
            'Tell me what the client wants, in your own words. I\'ll dig through every listing.',
            'What do we need today? Budget, area, commute. Go.',
            'Ela. Who is the client and how much do they have?',
        ]);
    };

    const pokes = [
        'Ela malaka, what do you need?',
        'On sec… OK, I\'m listening.',
        'What do you mean?',
        'Stop poking me, I\'m not a lift button.',
        'Let m check… no, you check.',
        'I\'m not lazy. I\'m on vape break.',
        'Mango ice. Best flavour. Don\'t argue.',
        'My blood pressure is 17, don\'t stress me.',
        'No trains westbound again, ffs.',
        'Bro. Type something.',
        'Where are y? Ah, here.',
        'Budget first, then we talk.',
        'I know a guy in Zone 2.',
        'Commission rooms first. Obviously.',
        'Single, Greek, owns a vape. Ladies, form a queue.',
        'Tinder is quiet today, so I\'m all yours.',
        'Oi. I\'m working.',
    ];

    const nudges = ['Ela, type something malaka.', 'I\'m waiting… *puff*', 'Hello? The client is waiting, re.', 'You fell asleep?'];

    // While the search runs: something about the brief first, then filler.
    const thinking = (q) => {
        const lines = [];
        const budget = (q || '').match(/£?\s?(\d{3,4})(?!\s?(min|mins|minutes))/i);
        if (budget) {
            const b = Number(budget[1]);
            if (b < 650) lines.push(money(b) + '? Malaka, this is London, not Athens…');
            else if (b > 1400) lines.push(money(b) + '? Who is this client, a footballer? *puff*');
            else lines.push(money(b) + '… OK, let me do the maths. *puff*');
        }
        if (has(q, /en.?suite/i)) lines.push('Ensuite, of course. Everyone wants their own toilet…');
        if (has(q, /\b(asap|urgent|today|tomorrow|now)\b/i)) lines.push('ASAP. Everyone wants it yesterday, ffs.');
        if (has(q, /\bcheap/i)) lines.push('Cheap. My favourite word. *puff*');
        if (has(q, /\bmin(s|utes)?\b/i)) lines.push('Checking the tube times. TfL, don\'t embarrass me…');
        for (const [re, says] of places) if (re.test(q)) { lines.push(pick(says)); break; }
        const filler = [
            'Hold on. One puff…',
            'Mango ice. Right, where were we…',
            'Reading every single listing between puffs…',
            'Let m check… *puff*',
            'On sec…',
            'Thinking cloud incoming…',
        ];
        return lines.concat(filler.sort(() => Math.random() - 0.5));
    };

    // After the results: first what came back, then a remark on the brief.
    const result = (data, q) => {
        const g = data.groups || {commission: [], standard: [], alternatives: []};
        const paying = g.commission.length;
        const onBrief = paying + g.standard.length;
        const b = data.brief || {};
        let lead;

        if (data.unplaced) {
            lead = 'Where is ' + data.unplaced + '? Never heard of it, malaka. Try a station.';
        } else if (!onBrief && !g.alternatives.length) {
            lead = pick([
                'Nothing. Zero. Nada. The client needs to open the wallet.',
                'What do you mean zero? …Zero, yeah. Stretch the budget or the area.',
                'Rooms like this don\'t exist, malaka. Not in London.',
            ]);
        } else if (!onBrief) {
            lead = pick(['Nothing exact, but these are close. Sell it, you can do it.', 'Not perfect, but close. The client will survive.']);
        } else if (paying) {
            lead = pick([
                'Ela! ' + paying + ' with commission. Now we\'re talking, malaka.',
                paying + (paying === 1 ? ' pays' : ' pay') + ' commission. Tonight we eat.',
                'Commission 🤑 ' + paying + ' of them. Send before someone else does.',
            ]);
        } else if (onBrief === 1) {
            lead = 'One. Only one. Send it quick before it goes.';
        } else {
            lead = pick([
                onBrief + ' rooms, no commission. We work for free now? Fine.',
                'Found ' + onBrief + '. No commission, but a deal is a deal.',
            ]);
        }
        if (onBrief > 15) lead = onBrief + '?? Too many, re. Be more picky. ' + (paying ? paying + ' pay commission though.' : '');

        const extra = [];
        if (data.widened) extra.push('I looked a bit further out. Don\'t tell the client.');
        if ((data.relaxed || []).length) extra.push('I had to bend the rules a bit. Check each one, eh.');
        if (b.max_price && b.max_price < 650) extra.push(money(b.max_price) + ' in London. Brave.');
        else if (b.max_price && b.max_price > 1400) extra.push('With ' + money(b.max_price) + ' they could date a model. Or rent a room.');
        if (b.ensuite_only) extra.push('Ensuite. Everyone wants their own toilet, nobody wants to pay for it.');
        if (b.couples === true) extra.push('A couple in one room. Romantic. Also cheaper, smart.');
        if (b.pets === true) extra.push('A pet? Landlords love pets like I love the Northern line.');
        if (b.no_deposit) extra.push('No deposit. The client wants everything free, malaka.');
        if (b.parking) extra.push('Parking in London. Crazy.');
        if (b.garden) extra.push('A garden. Sure re, and a swimming pool?');
        if (b.max_zone === 1 || b.region === 'central') extra.push('Central on this budget? Hahaha.');
        if (b.minutes_from_landmark) extra.push(b.minutes_from_landmark + ' min, with TfL? Optimistic.');
        if (b.sort === 'cheapest') extra.push('Cheapest first. You know me too well.');
        for (const [re, says] of places) if (re.test((b.location || '') + ' ' + (b.near_landmark || '') + ' ' + q)) { extra.push(pick(says)); break; }

        // One extra remark at most, and not every time: a joke on every search gets old.
        if (extra.length && Math.random() < 0.75) lead += ' ' + pick(extra);
        return lead;
    };

    const error = () => pick(['Something broke. Not me. Probably Giaco.', 'ffs, it crashed. Try again.', 'Error. Malaka computer.']);
    const offline = () => pick(['WiFi is dead. Like the Central line on a Monday.', 'The internet ate it. Try again?']);

    return {greeting: greetings, poke: () => pick(pokes), nudge: () => pick(nudges), thinking, result, error, offline};
})();
</script>
