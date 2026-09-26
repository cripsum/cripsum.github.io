/**
 * lootbox-modal.js — i pop-up della lootbox.
 *
 * Ogni pop-up e' un <dialog class="lm"> con dentro .lm__scrim (lo sfondo) e
 * .lm__panel (la finestra). showModal() da' gratis focus, Esc e il resto
 * della pagina inerte; qui ci sono le animazioni di entrata e uscita, le
 * schede con l'indicatore che scorre e, sul telefono, il foglio dal basso
 * che si chiude trascinandolo giu'.
 *
 *   LootboxModal.open('gachaHistoryModal', { onClose })
 *   LootboxModal.close('gachaHistoryModal')
 *   LootboxModal.tab(dialog, 'chars')        // cambia scheda
 *   dialog.addEventListener('lm:tab', e => e.detail.tab)
 */

'use strict';

(function () {
    const OUT_MS = 260;
    const reduced = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isSheet = () => window.matchMedia('(max-width: 767px)').matches;
    const state = new WeakMap();

    const resolve = (target) => (typeof target === 'string' ? document.getElementById(target) : target);
    const openCount = () => document.querySelectorAll('dialog.lm[open]').length;

    function open(target, opts = {}) {
        const dlg = resolve(target);
        if (!dlg || dlg.open) return dlg;
        const st = { onClose: opts.onClose || null, returnTo: document.activeElement, closing: false };
        state.set(dlg, st);

        dlg.classList.remove('is-closing', 'is-open');
        dlg.showModal();
        document.documentElement.classList.add('lm-lock');

        // Prima il frame "chiuso", poi la classe che fa partire la transizione.
        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                dlg.classList.add('is-open');
                syncInk(dlg, false);
            });
        });

        // Il focus va alla finestra, non al primo pulsante: niente anello
        // di focus sulla X appena si apre.
        const panel = dlg.querySelector('.lm__panel');
        if (panel) {
            panel.setAttribute('tabindex', '-1');
            panel.focus({ preventScroll: true });
        }
        return dlg;
    }

    function close(target, reason) {
        const dlg = resolve(target);
        if (!dlg || !dlg.open) return;
        const st = state.get(dlg) || {};
        if (st.closing) return;
        st.closing = true;

        dlg.classList.remove('is-open');
        dlg.classList.add('is-closing');
        const panel = dlg.querySelector('.lm__panel');

        const finish = () => {
            dlg.classList.remove('is-closing', 'is-dragging');
            if (panel) panel.style.transform = '';
            dlg.close();
            if (!openCount()) document.documentElement.classList.remove('lm-lock');
            st.closing = false;
            if (st.returnTo && document.contains(st.returnTo)) {
                try { st.returnTo.focus({ preventScroll: true }); } catch (e) { /* niente */ }
            }
            st.onClose?.(reason);
            dlg.dispatchEvent(new CustomEvent('lm:close', { detail: { reason } }));
        };

        if (reduced()) finish();
        else setTimeout(finish, OUT_MS);
    }

    /* ── Schede ─────────────────────────────────────────────────────────── */

    function syncInk(dlg, animate = true) {
        const tabs = dlg.querySelector('[data-lm-tabs]');
        if (!tabs) return;
        const ink = tabs.querySelector('.lm__ink');
        const on = tabs.querySelector('[data-lm-tab][aria-selected="true"]');
        if (!ink || !on) return;
        if (!animate) ink.style.transition = 'none';
        ink.style.width = `${on.offsetWidth}px`;
        ink.style.transform = `translateX(${on.offsetLeft - 4}px)`;
        if (!animate) {
            void ink.offsetWidth;
            ink.style.transition = '';
        }
    }

    function tab(target, name) {
        const dlg = resolve(target);
        if (!dlg) return;
        const buttons = [...dlg.querySelectorAll('[data-lm-tab]')];
        const from = buttons.findIndex((b) => b.getAttribute('aria-selected') === 'true');
        const to = buttons.findIndex((b) => b.dataset.lmTab === name);
        if (to < 0) return;

        buttons.forEach((b, i) => {
            b.setAttribute('aria-selected', i === to ? 'true' : 'false');
            b.tabIndex = i === to ? 0 : -1;
        });
        syncInk(dlg);

        const panes = [...dlg.querySelectorAll('[data-lm-pane]')];
        if (panes.length) {
            const dir = to >= from ? 'next' : 'prev';
            panes.forEach((p) => {
                const on = p.dataset.lmPane === name;
                if (on) {
                    p.hidden = false;
                    p.dataset.dir = dir;
                    p.classList.remove('is-active');
                    void p.offsetWidth;
                    p.classList.add('is-active');
                } else {
                    p.classList.remove('is-active');
                    p.hidden = true;
                }
            });
            const body = dlg.querySelector('.lm__body');
            if (body) body.scrollTop = 0;
        }
        dlg.dispatchEvent(new CustomEvent('lm:tab', { detail: { tab: name, dir: to >= from ? 'next' : 'prev' } }));
    }

    /* ── Trascina giu' per chiudere (solo foglio sul telefono) ──────────── */

    function initDrag(dlg) {
        const panel = dlg.querySelector('.lm__panel');
        const handle = dlg.querySelector('.lm__head');
        if (!panel || !handle) return;
        let startY = 0;
        let dy = 0;
        let t0 = 0;
        let dragging = false;

        handle.addEventListener('pointerdown', (e) => {
            if (!isSheet() || e.button !== 0 || e.target.closest('button, a, input')) return;
            dragging = true;
            startY = e.clientY;
            dy = 0;
            t0 = performance.now();
            dlg.classList.add('is-dragging');
            handle.setPointerCapture(e.pointerId);
        });
        handle.addEventListener('pointermove', (e) => {
            if (!dragging) return;
            dy = Math.max(0, e.clientY - startY);
            // Oltre il bordo alto resiste un po'.
            panel.style.transform = `translateY(${dy}px)`;
            dlg.style.setProperty('--lm-drag', String(Math.min(1, dy / 320)));
        });
        const end = () => {
            if (!dragging) return;
            dragging = false;
            dlg.classList.remove('is-dragging');
            const speed = dy / Math.max(1, performance.now() - t0);
            dlg.style.removeProperty('--lm-drag');
            if (dy > 120 || speed > 0.6) {
                panel.style.transform = '';
                close(dlg, 'drag');
            } else {
                panel.style.transform = '';
            }
        };
        handle.addEventListener('pointerup', end);
        handle.addEventListener('pointercancel', end);
    }

    /* ── Collegamenti ───────────────────────────────────────────────────── */

    function bind(dlg) {
        if (dlg.dataset.lmBound) return;
        dlg.dataset.lmBound = '1';

        // Esc: chiusura animata invece di quella secca del browser.
        dlg.addEventListener('cancel', (e) => {
            e.preventDefault();
            close(dlg, 'esc');
        });
        dlg.addEventListener('click', (e) => {
            if (e.target.closest('[data-lm-close]')) close(dlg, 'button');
            const t = e.target.closest('[data-lm-tab]');
            if (t && t.getAttribute('aria-selected') !== 'true') tab(dlg, t.dataset.lmTab);
        });
        // Frecce tra le schede, come vuole il ruolo tablist.
        dlg.querySelector('[data-lm-tabs]')?.addEventListener('keydown', (e) => {
            if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
            const buttons = [...dlg.querySelectorAll('[data-lm-tab]')];
            const i = buttons.findIndex((b) => b.getAttribute('aria-selected') === 'true');
            const next = buttons[(i + (e.key === 'ArrowRight' ? 1 : buttons.length - 1)) % buttons.length];
            tab(dlg, next.dataset.lmTab);
            next.focus();
        });
        initDrag(dlg);
    }

    document.querySelectorAll('dialog.lm').forEach(bind);
    window.addEventListener('resize', () => document.querySelectorAll('dialog.lm[open]').forEach((d) => syncInk(d, false)));

    window.LootboxModal = {
        open: (t, o) => { const d = resolve(t); if (d) bind(d); return open(d, o); },
        close,
        tab,
        isOpen: (t) => !!resolve(t)?.open,
        anyOpen: () => openCount() > 0,
    };
})();
