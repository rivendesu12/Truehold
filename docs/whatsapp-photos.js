/*
 * Copy room photos from an open WhatsApp Web group to Truehold.
 *
 * Paste into WhatsApp Web (the group open), then run:
 *     thPhotos.button('fausto')   // or 'vic', 'fab'
 * and click the green "Send photos to Truehold" button it adds. The click
 * opens a small Truehold page (you must be logged in there) that saves the
 * photos. Scroll the chat and click again to send more; photos already sent
 * are skipped.
 *
 * Pairing: each album goes to the room text next to it, posted in the same
 * minute. Fausto posts the text, then the photos; Vic and Fab the photos,
 * then the text. An album with no room text beside it is left out rather
 * than guessed.
 */
(() => {
    const TRUEHOLD = 'https://truehold.yaenlinea.co/tools/whatsapp-photos';
    const SIDE = { fausto: 'prev', vic: 'next', fab: 'next' };
    const PC = /\b([A-Z]{1,2}\d[A-Z\d]?)\s*(\d)\s*([A-Z])\s*([A-Z])\b/i;

    const state = window.__thPhotos = window.__thPhotos || { sent: new Set(), win: null };

    const minuteOf = (row) => {
        const m = (row.innerText || '').match(/\b(\d{1,2}:\d{2})\b(?![\s\S]*\b\d{1,2}:\d{2}\b)/);
        return m ? m[1] : null;
    };

    // "FOREST GATE / Derby Road, E7 8 NH / £800 ... / Room B" -> the room.
    const roomOf = (text) => {
        const lines = text.split('\n').map((l) => l.trim()).filter(Boolean);
        const i = lines.findIndex((l) => PC.test(l));
        if (i < 0 || !/£\s?\d/.test(text)) return null;
        const m = lines[i].match(PC);
        const street = lines[i].split(',')[0].replace(PC, '').replace(/^\d+\)\s*/, '').trim();
        const room = (text.match(/\bRoom\s+([A-Z0-9]{1,3})\b/i) || [])[1] || '';
        return { postcode: (m[1] + ' ' + m[2] + m[3] + m[4]).toUpperCase(), street, room };
    };

    const rows = () => [...document.querySelectorAll('#main [role="row"]')].map((row) => {
        const imgs = [...row.querySelectorAll('img[src^="blob:"]')].filter((i) => i.naturalWidth >= 200);
        const text = [...row.querySelectorAll('[data-pre-plain-text]')].map((e) => e.innerText).join('\n');
        return { row, imgs, room: text ? roomOf(text) : null, minute: minuteOf(row) };
    });

    const jpeg = (img) => {
        const scale = Math.min(1, 1600 / Math.max(img.naturalWidth, img.naturalHeight));
        const c = document.createElement('canvas');
        c.width = Math.round(img.naturalWidth * scale);
        c.height = Math.round(img.naturalHeight * scale);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        return c.toDataURL('image/jpeg', 0.85);
    };

    // Albums paired with their room, photos not sent before.
    const collect = (agency) => {
        const list = rows();
        const out = [];
        list.forEach((r, i) => {
            if (!r.imgs.length || r.room) return;
            const near = (step) => {
                for (let j = i + step, k = 0; k < 2 && list[j]; j += step, k++) {
                    if (list[j].room) return list[j].minute === r.minute ? list[j] : null;
                    if (list[j].imgs.length) return null;
                }
                return null;
            };
            const prev = near(-1), next = near(1);
            const pick = prev && next ? (SIDE[agency] === 'prev' ? prev : next) : (prev || next);
            if (!pick) return;
            const images = r.imgs.filter((img) => !state.sent.has(img.src));
            if (!images.length) return;
            out.push({ agency, ...pick.room, images: images.map(jpeg), srcs: images.map((i) => i.src) });
        });
        return out;
    };

    const send = (agency) => {
        const items = collect(agency);
        if (!items.length) return alert('No new photos next to a room here. Scroll to more rooms and try again.');
        const go = () => {
            state.win.postMessage({ batch: Date.now(), items: items.map(({ srcs, ...it }) => it) }, 'https://truehold.yaenlinea.co');
            items.forEach((it) => it.srcs.forEach((s) => state.sent.add(s)));
            state.last = { rooms: items.length, photos: items.reduce((n, it) => n + it.images.length, 0) };
        };
        if (state.win && !state.win.closed && state.ready) return go();
        state.win = window.open(TRUEHOLD, 'truehold-photos', 'width=520,height=320');
        state.ready = false;
        const onReady = (e) => {
            if (e.origin !== 'https://truehold.yaenlinea.co') return;
            if (e.data && e.data.ready) { state.ready = true; go(); }
            if (e.data && e.data.done) state.result = e.data;
        };
        window.addEventListener('message', onReady);
    };

    window.thPhotos = {
        collect,
        button(agency) {
            document.getElementById('th-photos')?.remove();
            const b = document.createElement('button');
            b.id = 'th-photos';
            b.textContent = 'Send ' + agency + ' photos to Truehold';
            b.style.cssText = 'position:fixed;right:24px;bottom:96px;z-index:99999;padding:12px 16px;border:0;border-radius:10px;background:#0b5d4e;color:#fff;font:600 14px system-ui;cursor:pointer';
            b.onclick = () => send(agency);
            document.body.appendChild(b);
            return 'Button added: click it to send.';
        },
    };
})();
