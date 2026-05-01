;(() => {
    "use strict";

    if (window.__goonlandSmashPassLoaded) return;
    window.__goonlandSmashPassLoaded = true;

    const $ = (selector, root = document) => root.querySelector(selector);

    let isLoading = false;
    let isVoting = false;
    let currentMode = "waifu_sfw";
    let currentCard = null;
    let pointerState = null;
    let dragFrame = 0;
    let nextDrag = { dx: 0, dy: 0 };

    const MOTION = {
        throwDuration: 320,
        resetDuration: 210,
        enterDuration: 260,
        maxRotate: 14,
        swipeLimit: 0.46,
    };

    function showToast(message) {
        const toast = $("#goonlandToast");
        if (!toast) return;

        const text = toast.querySelector("span");
        if (text) text.textContent = message;

        toast.hidden = false;
        requestAnimationFrame(() => toast.classList.add("is-visible"));
        clearTimeout(window.__goonSmashPassToast);
        window.__goonSmashPassToast = setTimeout(() => {
            toast.classList.remove("is-visible");
            setTimeout(() => { toast.hidden = true; }, 180);
        }, 1900);
    }

    function setStatus(message) {
        const status = $("#smashPassStatus");
        if (status) status.textContent = message;
    }

    function getStorageKey(mode) {
        return `goonland_smashpass_${mode}`;
    }

    function getStats(mode) {
        try {
            const raw = localStorage.getItem(getStorageKey(mode));
            const parsed = raw ? JSON.parse(raw) : null;
            return {
                smash: Number(parsed?.smash || 0),
                pass: Number(parsed?.pass || 0)
            };
        } catch {
            return { smash: 0, pass: 0 };
        }
    }

    function saveStats(mode, stats) {
        localStorage.setItem(getStorageKey(mode), JSON.stringify(stats));
    }

    function updateStatsUi() {
        const stats = getStats(currentMode);
        const total = stats.smash + stats.pass;
        const rate = total > 0 ? Math.round((stats.smash / total) * 100) : 0;

        const totalEl = $("#spTotalCount");
        const smashEl = $("#spSmashCount");
        const passEl = $("#spPassCount");
        const rateEl = $("#spRateCount");

        if (totalEl) totalEl.textContent = total;
        if (smashEl) smashEl.textContent = stats.smash;
        if (passEl) passEl.textContent = stats.pass;
        if (rateEl) rateEl.textContent = `${rate}%`;
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function niceTag(value) {
        return String(value || "").replaceAll("_", " ").trim();
    }

    function setButtonsDisabled(disabled) {
        ["#spPassBtn", "#spSmashBtn", "#spNextBtn"].forEach((selector) => {
            const btn = $(selector);
            if (btn) btn.disabled = disabled;
        });

        const nativeSelect = $("#smashPassMode");
        const trigger = $(".gl-sp-mode-select .gl-select-trigger");
        if (nativeSelect) nativeSelect.disabled = disabled;
        if (trigger) trigger.disabled = disabled;
    }

    function setDecisionAlphas(smashAlpha = 0, passAlpha = 0) {
        const card = $("#smashPassCard");
        if (!card) return;
        card.style.setProperty("--sp-smash-alpha", String(Math.max(0, Math.min(1, smashAlpha))));
        card.style.setProperty("--sp-pass-alpha", String(Math.max(0, Math.min(1, passAlpha))));
    }

    function resetCardMotion() {
        const card = $("#smashPassCard");
        if (!card) return;

        card.getAnimations().forEach((animation) => animation.cancel());
        card.classList.remove("is-dragging", "is-resetting", "is-throw-smash", "is-throw-pass", "is-loading-next", "is-entering");
        card.style.transform = "";
        card.style.opacity = "";
        setDecisionAlphas(0, 0);
    }

    function animateCardEnter() {
        const card = $("#smashPassCard");
        if (!card || !card.animate) return;

        card.classList.add("is-entering");
        card.animate([
            { transform: "translate3d(0, 18px, 0) scale(0.975)", opacity: 0.01, filter: "blur(8px)" },
            { transform: "translate3d(0, 0, 0) scale(1)", opacity: 1, filter: "blur(0)" }
        ], {
            duration: MOTION.enterDuration,
            easing: "cubic-bezier(.16, 1, .3, 1)",
            fill: "both"
        }).finished.finally(() => {
            card.classList.remove("is-entering");
            card.style.opacity = "";
            card.style.transform = "";
        }).catch(() => {});
    }

    function renderCard(data, modeLabel) {
        const image = $("#smashPassImage");
        const placeholder = $("#smashPassPlaceholder");
        const title = $("#smashPassTitle");
        const subtitle = $("#smashPassSubtitle");
        const tags = $("#smashPassTags");
        const links = $("#smashPassLinks");
        const rating = $("#smashPassRating");
        const modeBadge = $("#smashPassModeLabel");

        const characters = (data.characterTags || []).filter(Boolean);
        const copyrights = (data.copyrightTags || []).filter(Boolean);
        const artists = (data.artistTags || []).filter(Boolean);
        const generals = (data.generalTags || []).filter(Boolean).slice(0, 8);

        const niceTitle = characters.length ? characters.slice(0, 2).map(niceTag).join(", ") : "Personaggio random";
        const niceSeries = copyrights.length ? copyrights.slice(0, 2).map(niceTag).join(", ") : "Serie non indicata";
        const niceArtists = artists.length ? artists.slice(0, 2).map(niceTag).join(", ") : "Artista non indicato";

        if (modeBadge) modeBadge.textContent = modeLabel || "Modalità";
        if (title) title.textContent = niceTitle;
        if (subtitle) subtitle.textContent = `${niceSeries} · ${niceArtists}`;
        if (rating) rating.textContent = String(data.rating || "-").toUpperCase();

        if (tags) {
            const allTags = [...characters, ...copyrights, ...generals].slice(0, 10);
            tags.innerHTML = allTags.length
                ? allTags.map((tag) => `<span class="gl-chip">#${escapeHtml(niceTag(tag))}</span>`).join("")
                : '<span class="gl-chip">#random</span>';
        }

        if (links) {
            const items = [];
            if (data.postUrl) items.push(`<a href="${escapeHtml(data.postUrl)}" target="_blank" rel="noopener noreferrer"><i class="fas fa-up-right-from-square"></i> Apri post</a>`);
            if (data.source) items.push(`<a href="${escapeHtml(data.source)}" target="_blank" rel="noopener noreferrer"><i class="fas fa-link"></i> Fonte</a>`);
            links.innerHTML = items.join("");
        }

        if (!image || !data.image) return;

        const preload = new Image();
        preload.onload = () => {
            image.src = data.image;
            image.hidden = false;
            image.classList.add("is-visible");
            if (placeholder) placeholder.style.display = "none";
            animateCardEnter();
        };
        preload.onerror = () => {
            if (placeholder) {
                placeholder.style.display = "grid";
                placeholder.innerHTML = '<i class="fas fa-triangle-exclamation"></i><strong>Immagine non caricata</strong><span>Riprova con un altro roll.</span>';
            }
            image.hidden = true;
        };
        preload.src = data.image;
    }

    async function readJsonResponse(response) {
        const text = await response.text();

        try {
            return JSON.parse(text);
        } catch {
            const clean = text.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
            throw new Error(clean ? clean.slice(0, 160) : `Risposta non valida HTTP ${response.status}`);
        }
    }

    async function loadNextCard() {
        if (isLoading || isVoting) return;
        isLoading = true;
        currentCard = null;

        const image = $("#smashPassImage");
        const spinner = $("#smashPassSpinner");
        const placeholder = $("#smashPassPlaceholder");
        const card = $("#smashPassCard");

        resetCardMotion();
        if (card) card.classList.add("is-loading-next");

        if (image) {
            image.hidden = true;
            image.classList.remove("is-visible");
            image.removeAttribute("src");
        }

        if (placeholder) {
            placeholder.style.display = "grid";
            placeholder.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i><strong>Caricamento...</strong><span>Sto cercando il prossimo personaggio.</span>';
        }

        if (spinner) spinner.style.display = "block";
        setButtonsDisabled(true);
        setStatus("Caricamento");

        try {
            const response = await fetch(`${window.location.pathname}?sop_api=1&mode=${encodeURIComponent(currentMode)}&_=${Date.now()}`, {
                cache: "no-store",
                headers: { "Accept": "application/json" }
            });

            const data = await readJsonResponse(response);

            if (!data.ok) {
                console.warn("[GoonLand SmashPass debug]", data.debug || null);
                throw new Error(data.error || `Errore HTTP ${response.status}`);
            }

            currentCard = data.data || null;
            renderCard(data.data, data.modeLabel);
            setStatus("Pronto");
        } catch (error) {
            console.error("[GoonLand SmashPass]", error);
            currentCard = null;
            if (placeholder) {
                placeholder.style.display = "grid";
                placeholder.innerHTML = `<i class="fas fa-triangle-exclamation"></i><strong>Errore</strong><span>${escapeHtml(error.message || "Riprova")}</span>`;
            }
            showToast(error.message || "Errore");
            setStatus("Errore");
        } finally {
            isLoading = false;
            if (spinner) spinner.style.display = "none";
            if (card) card.classList.remove("is-loading-next");
            setButtonsDisabled(false);
        }
    }

    function getCurrentTransform() {
        const card = $("#smashPassCard");
        return card?.style.transform || "translate3d(0, 0, 0) rotate(0deg)";
    }

    function animateDecision(type) {
        return new Promise((resolve) => {
            const card = $("#smashPassCard");
            if (!card) {
                resolve();
                return;
            }

            const direction = type === "smash" ? 1 : -1;
            const start = getCurrentTransform();
            const endX = direction * Math.min(window.innerWidth * 1.22, 980);
            const endRotate = direction * 24;
            const midX = direction * 42;
            const midRotate = direction * 5;

            card.getAnimations().forEach((animation) => animation.cancel());
            card.classList.remove("is-dragging", "is-resetting");
            setDecisionAlphas(type === "smash" ? 1 : 0, type === "pass" ? 1 : 0);

            const animation = card.animate([
                { transform: start, opacity: 1, offset: 0 },
                { transform: `translate3d(${midX}px, -10px, 0) rotate(${midRotate}deg) scale(1.006)`, opacity: 1, offset: 0.28 },
                { transform: `translate3d(${endX}px, -86px, 0) rotate(${endRotate}deg) scale(0.985)`, opacity: 0.08, offset: 1 }
            ], {
                duration: MOTION.throwDuration,
                easing: "cubic-bezier(.2, .9, .24, 1)",
                fill: "both"
            });

            animation.finished.finally(() => {
                resetCardMotion();
                resolve();
            }).catch(() => {
                resetCardMotion();
                resolve();
            });
        });
    }

    async function vote(type, withAnimation = true) {
        if (isLoading || isVoting) return;

        if (!currentCard) {
            loadNextCard();
            return;
        }

        isVoting = true;
        setButtonsDisabled(true);

        const stats = getStats(currentMode);
        if (type === "smash") {
            stats.smash += 1;
        } else {
            stats.pass += 1;
        }

        saveStats(currentMode, stats);
        updateStatsUi();
        showToast(type === "smash" ? "Smash" : "Pass");

        if (withAnimation) {
            await animateDecision(type);
        }

        isVoting = false;
        await loadNextCard();
    }

    function applyDragNow(dx, dy) {
        const card = $("#smashPassCard");
        if (!card) return;

        const width = Math.max(1, window.innerWidth);
        const limitedX = Math.max(-width * MOTION.swipeLimit, Math.min(width * MOTION.swipeLimit, dx));
        const vertical = Math.max(-26, Math.min(26, dy * 0.08));
        const rotate = Math.max(-MOTION.maxRotate, Math.min(MOTION.maxRotate, limitedX / 16));
        const lift = Math.min(20, Math.abs(limitedX) / 17);
        const smashAlpha = Math.max(0, Math.min(1, limitedX / 120));
        const passAlpha = Math.max(0, Math.min(1, -limitedX / 120));
        const scale = 1 + Math.min(0.012, Math.abs(limitedX) / 26000);

        card.style.transform = `translate3d(${limitedX}px, ${vertical - lift}px, 0) rotate(${rotate}deg) scale(${scale})`;
        setDecisionAlphas(smashAlpha, passAlpha);
    }

    function scheduleDrag(dx, dy) {
        nextDrag.dx = dx;
        nextDrag.dy = dy;

        if (dragFrame) return;
        dragFrame = requestAnimationFrame(() => {
            dragFrame = 0;
            applyDragNow(nextDrag.dx, nextDrag.dy);
        });
    }

    function resetDrag() {
        const card = $("#smashPassCard");
        if (!card) return;

        card.classList.remove("is-dragging");
        card.classList.add("is-resetting");

        const start = card.style.transform || "translate3d(0, 0, 0) rotate(0deg) scale(1)";
        const animation = card.animate([
            { transform: start, opacity: 1 },
            { transform: "translate3d(0, 0, 0) rotate(0deg) scale(1)", opacity: 1 }
        ], {
            duration: MOTION.resetDuration,
            easing: "cubic-bezier(.18, .9, .2, 1)",
            fill: "both"
        });

        setDecisionAlphas(0, 0);
        animation.finished.finally(() => {
            card.classList.remove("is-resetting");
            card.style.transform = "";
        }).catch(() => {
            card.classList.remove("is-resetting");
            card.style.transform = "";
        });
    }

    function initSwipe() {
        const area = $("#smashPassSwipeArea");
        const card = $("#smashPassCard");
        if (!area || !card) return;

        area.addEventListener("pointerdown", (event) => {
            if (isLoading || isVoting || !currentCard) return;
            if (event.pointerType === "mouse" && event.button !== 0) return;

            card.getAnimations().forEach((animation) => animation.cancel());

            pointerState = {
                id: event.pointerId,
                x: event.clientX,
                y: event.clientY,
                dx: 0,
                dy: 0,
                locked: false,
            };

            area.classList.add("is-touching");
            card.classList.add("is-dragging");
            card.classList.remove("is-resetting");
            area.setPointerCapture?.(event.pointerId);
        }, { passive: true });

        area.addEventListener("pointermove", (event) => {
            if (!pointerState || pointerState.id !== event.pointerId) return;

            const dx = event.clientX - pointerState.x;
            const dy = event.clientY - pointerState.y;
            pointerState.dx = dx;
            pointerState.dy = dy;

            if (!pointerState.locked && Math.abs(dx) + Math.abs(dy) > 8) {
                pointerState.locked = Math.abs(dx) > Math.abs(dy) * 0.75;
            }

            if (!pointerState.locked) return;
            event.preventDefault();
            scheduleDrag(dx, dy);
        }, { passive: false });

        const endDrag = (event) => {
            if (!pointerState || pointerState.id !== event.pointerId) return;

            const { dx, dy, locked } = pointerState;
            pointerState = null;
            area.classList.remove("is-touching");
            area.releasePointerCapture?.(event.pointerId);

            const threshold = Math.min(128, Math.max(78, area.clientWidth * 0.22));
            const fastEnough = Math.abs(dx) > threshold && Math.abs(dx) > Math.abs(dy) * 0.72;

            if (locked && fastEnough) {
                vote(dx > 0 ? "smash" : "pass", true);
            } else {
                resetDrag();
            }
        };

        area.addEventListener("pointerup", endDrag, { passive: true });
        area.addEventListener("pointercancel", endDrag, { passive: true });
        area.addEventListener("lostpointercapture", () => {
            if (!pointerState) return;
            pointerState = null;
            area.classList.remove("is-touching");
            resetDrag();
        });
    }

    function initSmashPass() {
        if (document.body.dataset.goonlandPage !== "smash-pass") return;

        const modeSelect = $("#smashPassMode");
        const smashBtn = $("#spSmashBtn");
        const passBtn = $("#spPassBtn");
        const nextBtn = $("#spNextBtn");

        if (modeSelect) {
            currentMode = modeSelect.value || "waifu_sfw";
            modeSelect.addEventListener("change", () => {
                currentMode = modeSelect.value || "waifu_sfw";
                updateStatsUi();
                loadNextCard();
            });
        }

        if (smashBtn) smashBtn.addEventListener("click", () => vote("smash", true));
        if (passBtn) passBtn.addEventListener("click", () => vote("pass", true));
        if (nextBtn) nextBtn.addEventListener("click", loadNextCard);

        document.addEventListener("keydown", (event) => {
            if (document.body.dataset.goonlandPage !== "smash-pass") return;
            if (event.target && /input|textarea|select|button/i.test(event.target.tagName)) return;

            if (event.key === "ArrowLeft") {
                event.preventDefault();
                vote("pass", true);
            } else if (event.key === "ArrowRight") {
                event.preventDefault();
                vote("smash", true);
            } else if (event.key === " ") {
                event.preventDefault();
                loadNextCard();
            }
        });

        initSwipe();
        updateStatsUi();
        loadNextCard();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initSmashPass, { once: true });
    } else {
        initSmashPass();
    }
})();
