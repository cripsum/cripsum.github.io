/*
 * Le pagine di bio.cripsum.com (includes/bio_pages.php): il nome che si prova
 * sull'ingresso, i salvataggi di «La tua pagina», il QR, la stellina della
 * vetrina in Esplora e l'anteprima nel telefono. Senza JavaScript le pagine
 * funzionano lo stesso: il nome si controlla su /prendi, il resto si cambia
 * dall'editor.
 */
(() => {
    'use strict';

    const cfg = window.BioHub || {};
    const t = cfg.t || {};
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const esc = (value) => String(value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

    let toastTimer = null;
    const toast = (message) => {
        const el = $('[data-bh-toast]');
        if (!el || !message) return;
        el.textContent = message;
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { el.hidden = true; }, 2200);
    };

    const post = async (url, body) => {
        const res = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf || '' },
            body: JSON.stringify(body),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.ok) throw new Error(data.message || t.failed || '');
        return data;
    };

    // ── Il telefono: la pagina vera larga 390, rimpicciolita ──────────
    const screens = $$('[data-bh-screen]');
    const fit = (screen) => screen.style.setProperty('--s', String(screen.clientWidth / 390));
    if (screens.length && 'ResizeObserver' in window) {
        const ro = new ResizeObserver((entries) => entries.forEach((entry) => fit(entry.target)));
        screens.forEach((screen) => { fit(screen); ro.observe(screen); });
    } else {
        screens.forEach(fit);
    }

    // ── Il nome sull'ingresso ─────────────────────────────────────────
    const claim = $('[data-bh-claim]');
    if (claim) {
        const input = $('[data-bh-claim-input]', claim);
        const submit = $('[data-bh-claim-submit]', claim);
        const state = $('[data-bh-claim-state]');
        // Le regole degli username (auth_is_valid_username): il nome diventa lo username.
        const valid = { test: (n) => /^[a-zA-Z0-9_]{3,20}$/.test(n) && !n.startsWith('_') && !n.endsWith('_') };
        let timer = null;
        let seq = 0;

        const show = (ok, html) => {
            state.innerHTML = `<i class="bh-dot${ok ? '' : ' bh-dot--no'}" aria-hidden="true"></i><span>${html}</span>`;
            state.hidden = false;
            submit.disabled = !ok;
        };

        const check = async () => {
            const name = input.value.trim();
            const mine = ++seq;
            if (name === '') {
                state.hidden = true;
                submit.disabled = false;
                return;
            }
            if (!valid.test(name)) {
                show(false, esc(t.invalid || ''));
                return;
            }
            const bold = `<b>${esc(name)}</b>`;
            try {
                const res = await fetch(`/api/bio/nome.php?nome=${encodeURIComponent(name)}`, { credentials: 'same-origin' });
                const data = await res.json();
                if (mine !== seq) return;
                if (!data.ok) { show(false, esc(data.message || '')); return; }
                const fill = (text) => esc(text || '').replace('%s', bold);
                if (data.state === 'free') show(true, fill(t.free));
                else if (data.state === 'taken') show(false, `${fill(t.taken)} <a href="${esc(data.url)}">${esc(t.taken_link || '')}</a>`);
                else if (data.state === 'yours') show(false, fill(t.yours));
                else if (data.state === 'reserved') show(false, fill(t.reserved));
                else show(false, esc(t.invalid || ''));
            } catch (_) {
                if (mine === seq) { state.hidden = true; submit.disabled = false; }
            }
        };

        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(check, 350);
        });
        if (input.value.trim() !== '') check();
    }

    // ── Copia link e QR ───────────────────────────────────────────────
    $$('[data-bh-copy]').forEach((btn) => btn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(btn.dataset.bhCopy);
            toast(t.copied);
        } catch (_) { /* il link resta scritto sotto il titolo */ }
    }));

    const qr = $('[data-bh-qr]');
    if (qr) {
        $('[data-bh-qr-open]')?.addEventListener('click', () => {
            const img = $('img', qr);
            if (!img.getAttribute('src')) img.src = img.dataset.src;
            qr.showModal();
        });
        $('[data-bh-qr-close]', qr).addEventListener('click', () => qr.close());
        qr.addEventListener('click', (event) => { if (event.target === qr) qr.close(); });
    }

    // ── Chi può vederla ───────────────────────────────────────────────
    const visPanel = $('[data-bh-vis-panel]');
    if (visPanel) {
        const now = $('[data-bh-vis-now]', visPanel);
        const note = $('[data-bh-explore-note]', visPanel);
        const tabs = $$('[data-bh-vis]', visPanel);
        const select = (value) => {
            tabs.forEach((tab) => {
                const on = tab.dataset.bhVis === value;
                tab.classList.toggle('is-active', on);
                tab.setAttribute('aria-checked', on ? 'true' : 'false');
            });
            if (t.vis_now && t.vis_now[value]) now.textContent = t.vis_now[value];
            if (note) note.textContent = value === 'public' ? note.dataset.on : note.dataset.off;
        };

        tabs.forEach((tab) => tab.addEventListener('click', async () => {
            const before = tabs.find((x) => x.classList.contains('is-active'))?.dataset.bhVis;
            const value = tab.dataset.bhVis;
            if (value === before) return;
            select(value);
            try {
                await post('/api/bio/visibilita.php', { visibility: value });
                toast(t.saved);
            } catch (error) {
                select(before);
                toast(error.message || t.failed);
            }
        }));

        const sw = $('[data-bh-discoverable]', visPanel);
        sw?.addEventListener('change', async () => {
            try {
                await post('/api/bio/visibilita.php', { discoverable: sw.checked ? 1 : 0 });
                toast(t.saved);
            } catch (error) {
                sw.checked = !sw.checked;
                toast(error.message || t.failed);
            }
        });
    }

    // ── «Vuoi anche bio.cripsum.com/nome?» ────────────────────────────
    $('[data-bh-claim-alias]')?.addEventListener('click', async (event) => {
        const btn = event.currentTarget;
        btn.disabled = true;
        try {
            const data = await post('/api/bio/alias.php', { name: btn.dataset.bhClaimAlias });
            toast(data.message);
            setTimeout(() => window.location.reload(), 900);
        } catch (error) {
            btn.disabled = false;
            toast(error.message || t.failed);
        }
    });

    // ── La stellina della vetrina (staff) ─────────────────────────────
    $$('[data-bh-feature]').forEach((btn) => btn.addEventListener('click', async () => {
        const on = !btn.classList.contains('is-on');
        btn.disabled = true;
        try {
            await post('/api/bio/vetrina.php', { user_id: Number(btn.dataset.bhFeature), on: on ? 1 : 0 });
            btn.classList.toggle('is-on', on);
            btn.setAttribute('aria-pressed', on ? 'true' : 'false');
            btn.setAttribute('aria-label', on ? btn.dataset.on : btn.dataset.off);
            const icon = $('i', btn);
            icon.classList.toggle('fa-solid', on);
            icon.classList.toggle('fa-regular', !on);
        } catch (error) {
            toast(error.message || t.failed);
        } finally {
            btn.disabled = false;
        }
    }));
})();
