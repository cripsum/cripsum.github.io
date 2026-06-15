/**
 * Cripsum™ — Missions JS
 * Gestisce: fetch API, render card, tab switcher, countdown timer, claim reward, toast.
 */

(() => {
    'use strict';

    // ─────────────────────────────────────────────────────────
    //  CONFIG
    // ─────────────────────────────────────────────────────────


    const lang    = window.CRIPSUM_LANG || 'it';
    const API_GET = '/api/missions/get.php?lang=' + lang;
    const API_CLAIM = '/api/missions/claim.php';

    const t = {
        it: {
            err_unknown:      'Errore sconosciuto',
            err_claim:        'Claim fallito',
            empty_title:      'Nessuna missione disponibile',
            empty_sub:        'Torna domani per nuove missioni.',
            diff_facile:      'Facile',
            diff_media:       'Media',
            diff_difficile:   'Difficile',
            diff_epica:       'Epica',
            btn_claimed:      'Riscattata',
            btn_claim:        'Riscatta!',
            btn_pending:      'In corso',
            btn_loading:      '<i class="fa-solid fa-spinner fa-spin"></i> Riscatto...',
            btn_claim_icon:   '<i class="fa-solid fa-gift"></i> Riscatta!',
            btn_claimed_icon: '<i class="fa-solid fa-check"></i> Riscattata',
            aria_claim:       (title) => `Riscatta missione ${title}`,
            progress:         'Progresso',
            countdown_reset:  'Aggiornamento...',
            days_suffix:      'g',
            toast_done:       'Missione completata!',
            toast_pts:        (n) => `+${n} punti`,
        },
        en: {
            err_unknown:      'Unknown error',
            err_claim:        'Claim failed',
            empty_title:      'No missions available',
            empty_sub:        'Come back tomorrow for new missions.',
            diff_facile:      'Easy',
            diff_media:       'Medium',
            diff_difficile:   'Hard',
            diff_epica:       'Epic',
            btn_claimed:      'Claimed',
            btn_claim:        'Claim!',
            btn_pending:      'In progress',
            btn_loading:      '<i class="fa-solid fa-spinner fa-spin"></i> Claiming...',
            btn_claim_icon:   '<i class="fa-solid fa-gift"></i> Claim!',
            btn_claimed_icon: '<i class="fa-solid fa-check"></i> Claimed',
            aria_claim:       (title) => `Claim mission ${title}`,
            progress:         'Progress',
            countdown_reset:  'Updating...',
            days_suffix:      'd',
            toast_done:       'Mission completed!',
            toast_pts:        (n) => `+${n} pts`,
        },
    }[lang];

    // ─────────────────────────────────────────────────────────
    //  STATE
    // ─────────────────────────────────────────────────────────

    let state = {
        daily:        [],
        weekly:       [],
        dailyReset:   0,
        weeklyReset:  0,
        activeTab:    'daily',
        timerHandle:  null,
        claimingIds:  new Set(),
    };

    // ─────────────────────────────────────────────────────────
    //  DOM REFS
    // ─────────────────────────────────────────────────────────

    const $  = (sel, ctx = document) => ctx.querySelector(sel);
    const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

    let els = {};

    function cacheEls() {
        els = {
            loading:         $('#msnLoading'),
            error:           $('#msnError'),
            content:         $('#msnContent'),
            dailyGrid:       $('#msnDailyGrid'),
            weeklyGrid:      $('#msnWeeklyGrid'),
            dailyPanel:      $('#msnDailyPanel'),
            weeklyPanel:     $('#msnWeeklyPanel'),
            tabDaily:        $('#msnTabDaily'),
            tabWeekly:       $('#msnTabWeekly'),
            tabDailyCount:   $('#msnTabDailyCount'),
            tabWeeklyCount:  $('#msnTabWeeklyCount'),
            dailyCountdown:  $('#msnDailyCountdown'),
            weeklyCountdown: $('#msnWeeklyCountdown'),
            statDaily:       $('#msnStatDaily'),
            statWeekly:      $('#msnStatWeekly'),
            statPoints:      $('#msnStatPoints'),
            toast:           $('#msnToast'),
            toastMsg:        $('#msnToastMsg'),
            toastPts:        $('#msnToastPts'),
        };
    }

    // ─────────────────────────────────────────────────────────
    //  INIT
    // ─────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', () => {
        cacheEls();
        bindTabs();
        loadMissions();
        revealOnScroll();
    });

    // ─────────────────────────────────────────────────────────
    //  FETCH
    // ─────────────────────────────────────────────────────────

    async function loadMissions() {
        showLoading(true);

        try {
            const res  = await fetch(API_GET, { credentials: 'same-origin' });
            const json = await res.json();

            if (!res.ok || !json.success) {
                throw new Error(json.error || t.err_unknown);
            }

            state.daily       = json.data.daily.missions  || [];
            state.weekly      = json.data.weekly.missions || [];
            state.dailyReset  = json.data.daily.reset_at  || 0;
            state.weeklyReset = json.data.weekly.reset_at || 0;

            renderAll();
            startCountdowns();
            updateHeroStats();

        } catch (err) {
            console.error('[Missions]', err);
            showLoading(false);
            if (els.error) {
                els.error.hidden = false;
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    //  RENDER
    // ─────────────────────────────────────────────────────────

    function renderAll() {
        showLoading(false);
        if (els.content) els.content.hidden = false;

        renderGrid(state.daily,  els.dailyGrid,  false);
        renderGrid(state.weekly, els.weeklyGrid, true);

        updateTabCounts();
        animateProgressBars();
    }

    function renderGrid(missions, container, isWeekly) {
        if (!container) return;
        container.innerHTML = '';

        if (!missions.length) {
            container.innerHTML = `
                <div class="msn-empty">
                    <i class="fa-solid fa-scroll"></i>
                    <strong>${t.empty_title}</strong>
                    <span>${t.empty_sub}</span>
                </div>`;
            return;
        }

        missions.forEach(m => {
            container.appendChild(buildCard(m, isWeekly));
        });
    }

    function buildCard(m, isWeekly) {
        const pct        = Math.round((m.progresso / m.obiettivo) * 100);
        const completed  = parseInt(m.completata) === 1;
        const claimed    = parseInt(m.riscattata) === 1;
        const diff       = m.difficolta || 'facile';
        const diffLabel  = {
            facile:    t.diff_facile,
            media:     t.diff_media,
            difficile: t.diff_difficile,
            epica:     t.diff_epica,
        }[diff] || diff;
        const diffIcon   = { facile: 'fa-seedling', media: 'fa-bolt', difficile: 'fa-skull', epica: 'fa-crown' }[diff] || 'fa-star';

        // Stato bottone claim
        let btnClass, btnIcon, btnText;
        if (claimed) {
            btnClass = 'msn-btn-claim--claimed';
            btnIcon  = 'fa-check';
            btnText  = t.btn_claimed;
        } else if (completed) {
            btnClass = 'msn-btn-claim--ready';
            btnIcon  = 'fa-gift';
            btnText  = t.btn_claim;
        } else {
            btnClass = 'msn-btn-claim--pending';
            btnIcon  = 'fa-lock';
            btnText  = t.btn_pending;
        }

        // Classi stato card
        let cardState = '';
        if (claimed)   cardState = 'is-claimed';
        else if (completed) cardState = 'is-completed';

        const art = document.createElement('article');
        art.className = `msn-card msn-reveal ${cardState}`;
        art.dataset.diff          = diff;
        art.dataset.userMissionId = m.user_mission_id;

        art.innerHTML = `
            <div class="msn-card__top">
                <div class="msn-icon">
                    <i class="fa-solid ${m.icona || 'fa-star'}"></i>
                </div>
                <div class="msn-card__meta">
                    <h3 class="msn-card__title">${esc(m.titolo)}</h3>
                    <p class="msn-card__desc">${esc(m.descrizione)}</p>
                    <span class="msn-badge">
                        <i class="fa-solid ${diffIcon}"></i>
                        ${diffLabel}
                    </span>
                </div>
            </div>

            <div class="msn-card__progress-wrap">
                <div class="msn-progress-header">
                    <span>Progresso</span>
                    <span class="msn-progress-count">${m.progresso} / ${m.obiettivo}</span>
                </div>
                <div class="msn-progress-track">
                    <div class="msn-progress-fill" data-target-pct="${pct}" style="width:0%"></div>
                </div>
            </div>

            <div class="msn-card__footer">
                <div class="msn-reward">
                    <i class="fa-solid fa-coins"></i>
                    +${m.punti_reward} pt
                </div>
                <button
                    type="button"
                    class="msn-btn-claim ${btnClass}"
                    data-claim-id="${m.user_mission_id}"
                    aria-label="Riscatta missione ${esc(m.titolo)}"
                    ${(claimed || !completed) ? 'disabled' : ''}
                >
                    <i class="fa-solid ${btnIcon}"></i>
                    ${btnText}
                </button>
            </div>
        `;

        if (completed && !claimed) {
            art.querySelector('[data-claim-id]').addEventListener('click', handleClaim);
        }

        return art;
    }

    // ─────────────────────────────────────────────────────────
    //  PROGRESS BAR ANIMATION
    // ─────────────────────────────────────────────────────────

    function animateProgressBars() {
        // rAF su ogni fill con delay per effetto cascata
        $$('.msn-progress-fill').forEach((el, i) => {
            const target = el.dataset.targetPct || '0';
            setTimeout(() => {
                el.style.width = target + '%';
            }, 120 + i * 60);
        });
    }

    // ─────────────────────────────────────────────────────────
    //  CLAIM
    // ─────────────────────────────────────────────────────────

    async function handleClaim(e) {
        const btn            = e.currentTarget;
        const userMissionId  = parseInt(btn.dataset.claimId, 10);

        if (!userMissionId || state.claimingIds.has(userMissionId)) return;

        state.claimingIds.add(userMissionId);
        setClaimLoading(btn, true);

        try {
            const res  = await fetch(API_CLAIM, {
                method:      'POST',
                credentials: 'same-origin',
                headers:     { 'Content-Type': 'application/json' },
                body:        JSON.stringify({ user_mission_id: userMissionId }),
            });
            const json = await res.json();

            if (!res.ok || !json.success) {
                throw new Error(json.error || 'Claim fallito');
            }

            // Aggiorna la card
            markAsClaimed(btn, userMissionId);

            // Toast
            showToast(json.punti_earned, json.missione);

            // Aggiorna hero stats
            updateHeroStatsAfterClaim(json.punti_earned);

        } catch (err) {
            console.error('[Missions claim]', err);
            setClaimLoading(btn, false);
            showToast(null, null, err.message);
        } finally {
            state.claimingIds.delete(userMissionId);
        }
    }

    function setClaimLoading(btn, loading) {
        if (loading) {
            btn.classList.add('msn-btn-claim--loading');
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Riscatto...`;
        } else {
            btn.classList.remove('msn-btn-claim--loading');
            btn.innerHTML = `<i class="fa-solid fa-gift"></i> Riscatta!`;
        }
    }

    function markAsClaimed(btn, userMissionId) {
        const card = document.querySelector(`[data-user-mission-id="${userMissionId}"]`);

        // Aggiorna bottone
        btn.className   = 'msn-btn-claim msn-btn-claim--claimed';
        btn.disabled    = true;
        btn.innerHTML   = `<i class="fa-solid fa-check"></i> Riscattata`;
        btn.removeEventListener('click', handleClaim);

        // Aggiorna card
        if (card) {
            card.classList.remove('is-completed');
            card.classList.add('is-claimed');

            // Micro animazione di completamento
            card.style.transition = 'transform 0.25s ease, opacity 0.6s ease 0.4s';
            card.style.transform  = 'scale(1.025)';
            setTimeout(() => { card.style.transform = ''; }, 250);
        }

        // Aggiorna state
        const mission = [...state.daily, ...state.weekly]
            .find(m => m.user_mission_id == userMissionId);
        if (mission) mission.riscattata = 1;

        updateTabCounts();
    }

    function updateHeroStatsAfterClaim(pts) {
        // Aggiorna visivamente il contatore punti nell'hero
        if (!els.statPoints) return;
        const current = parseInt(els.statPoints.textContent.replace(/\D/g, ''), 10) || 0;
        animateNumber(els.statPoints, current, current + pts, 600);
    }

    // ─────────────────────────────────────────────────────────
    //  HERO STATS
    // ─────────────────────────────────────────────────────────

    function updateHeroStats() {
        const completedDaily  = state.daily.filter(m => m.completata == 1).length;
        const completedWeekly = state.weekly.filter(m => m.completata == 1).length;
        const claimedPts = [...state.daily, ...state.weekly]
            .filter(m => m.riscattata == 1)
            .reduce((sum, m) => sum + parseInt(m.punti_reward || 0, 10), 0);

        if (els.statDaily)  els.statDaily.textContent  = `${completedDaily}/${state.daily.length}`;
        if (els.statWeekly) els.statWeekly.textContent = `${completedWeekly}/${state.weekly.length}`;
        if (els.statPoints) els.statPoints.textContent = `${claimedPts}`;
    }

    function updateTabCounts() {
        const completedDaily  = state.daily.filter(m => m.completata == 1 && m.riscattata == 0).length;
        const completedWeekly = state.weekly.filter(m => m.completata == 1 && m.riscattata == 0).length;
        if (els.tabDailyCount)  els.tabDailyCount.textContent  = completedDaily  || state.daily.length  || '0';
        if (els.tabWeeklyCount) els.tabWeeklyCount.textContent = completedWeekly || state.weekly.length || '0';
    }

    // ─────────────────────────────────────────────────────────
    //  TABS
    // ─────────────────────────────────────────────────────────

    function bindTabs() {
        document.addEventListener('click', e => {
            const tab = e.target.closest('[data-msn-tab]');
            if (!tab) return;
            const target = tab.dataset.msnTab;
            switchTab(target);
        });
    }

    function switchTab(tab) {
        state.activeTab = tab;
        $$('[data-msn-tab]').forEach(t => t.classList.toggle('is-active', t.dataset.msnTab === tab));
        $$('[data-msn-panel]').forEach(p => p.classList.toggle('is-active', p.dataset.msnPanel === tab));
        // Re-trigger progress animations per il panel appena visibile
        setTimeout(animateProgressBars, 80);
    }

    // ─────────────────────────────────────────────────────────
    //  COUNTDOWN TIMER
    // ─────────────────────────────────────────────────────────

    function startCountdowns() {
        if (state.timerHandle) clearInterval(state.timerHandle);

        tick(); // subito
        state.timerHandle = setInterval(tick, 1000);
    }

    function tick() {
        const now = Math.floor(Date.now() / 1000);

        if (els.dailyCountdown) {
            const diff = state.dailyReset - now;
            els.dailyCountdown.textContent = diff > 0 ? formatCountdown(diff) : 'Aggiornamento...';
        }
        if (els.weeklyCountdown) {
            const diff = state.weeklyReset - now;
            els.weeklyCountdown.textContent = diff > 0 ? formatCountdown(diff) : 'Aggiornamento...';
        }
    }

    function formatCountdown(seconds) {
        const d = Math.floor(seconds / 86400);
        const h = Math.floor((seconds % 86400) / 3600);
        const m = Math.floor((seconds % 3600) / 60);
        const s = seconds % 60;

        if (d > 0) {
            return `${d}g ${pad(h)}:${pad(m)}:${pad(s)}`;
        }
        return `${pad(h)}:${pad(m)}:${pad(s)}`;
    }

    function pad(n) { return String(n).padStart(2, '0'); }

    // ─────────────────────────────────────────────────────────
    //  TOAST
    // ─────────────────────────────────────────────────────────

    let toastTimeout = null;

    function showToast(pts, titolo, errorMsg = null) {
        if (!els.toast) return;

        clearTimeout(toastTimeout);

        if (errorMsg) {
            els.toast.style.borderColor = 'rgba(248,113,113,0.4)';
            els.toast.innerHTML = `
                <i class="fa-solid fa-triangle-exclamation msn-toast__icon" style="color:var(--msn-red)"></i>
                <div>
                    <div>${esc(errorMsg)}</div>
                </div>`;
        } else {
            els.toast.style.borderColor = '';
            els.toast.innerHTML = `
                <i class="fa-solid fa-circle-check msn-toast__icon"></i>
                <div>
                    <div>Missione completata!</div>
                    ${pts ? `<div class="msn-toast__pts">+${pts} punti</div>` : ''}
                </div>`;
        }

        els.toast.classList.add('is-visible');
        toastTimeout = setTimeout(() => els.toast.classList.remove('is-visible'), 3800);
    }

    // ─────────────────────────────────────────────────────────
    //  LOADING STATE
    // ─────────────────────────────────────────────────────────

    function showLoading(show) {
        if (els.loading) els.loading.hidden = !show;
        if (els.content) els.content.hidden =  show;
    }

    // ─────────────────────────────────────────────────────────
    //  REVEAL ON SCROLL
    // ─────────────────────────────────────────────────────────

    function revealOnScroll() {
        if (!('IntersectionObserver' in window)) {
            $$('.msn-reveal').forEach(el => el.classList.add('is-visible'));
            return;
        }

        const obs = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.08 });

        // Osserva elementi già esistenti + usa MutationObserver per i nuovi
        $$('.msn-reveal').forEach(el => obs.observe(el));

        new MutationObserver(mutations => {
            mutations.forEach(m => {
                m.addedNodes.forEach(node => {
                    if (node.nodeType !== 1) return;
                    if (node.classList.contains('msn-reveal')) obs.observe(node);
                    node.querySelectorAll?.('.msn-reveal').forEach(el => obs.observe(el));
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    }

    // ─────────────────────────────────────────────────────────
    //  UTILS
    // ─────────────────────────────────────────────────────────

    function esc(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function animateNumber(el, from, to, duration) {
        const start = performance.now();
        const step  = (now) => {
            const progress = Math.min((now - start) / duration, 1);
            el.textContent = Math.round(from + (to - from) * progress);
            if (progress < 1) requestAnimationFrame(step);
        };
        requestAnimationFrame(step);
    }

})();