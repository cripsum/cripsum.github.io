;(() => {
    "use strict";

    if (window.__goonlandSmashPassLoaded) return;
    window.__goonlandSmashPassLoaded = true;

    const $ = (selector, root = document) => root.querySelector(selector);

    let isLoading = false;
    let isVoting = false;
    let currentMode = "waifu_sfw";
    let currentCard = null;
    let pointerStart = null;
    let rafId = 0;

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
        }, 2200);
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

    function resetCardMotion() {
        const card = $("#smashPassCard");
        const area = $("#smashPassSwipeArea");
        if (!card) return;

        card.classList.remove("is-dragging", "is-resetting", "is-throw-smash", "is-throw-pass", "is-loading-next");
        card.style.transform = "";
        card.style.setProperty("--sp-smash-alpha", "0");
        card.style.setProperty("--sp-pass-alpha", "0");
        if (area) area.classList.remove("is-touching");
    }

    function setButtonsDisabled(disabled) {
        const modeSelect = $("#smashPassMode");
        const smashBtn = $("#spSmashBtn");
        const passBtn = $("#spPassBtn");
        const nextBtn = $("#spNextBtn");

        if (modeSelect) modeSelect.disabled = disabled;
        if (smashBtn) smashBtn.disabled = disabled;
        if (passBtn) passBtn.disabled = disabled;
        if (nextBtn) nextBtn.disabled = disabled;
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

        const niceTitle = characters.length ? characters.slice(0, 2).join(", ").replaceAll("_", " ") : "Personaggio random";
        const niceSeries = copyrights.length ? copyrights.slice(0, 2).join(", ").replaceAll("_", " ") : "Serie non indicata";
        const niceArtists = artists.length ? artists.slice(0, 2).join(", ").replaceAll("_", " ") : "Artista non indicato";

        if (modeBadge) modeBadge.textContent = modeLabel || "Modalità";
        if (title) title.textContent = niceTitle;
        if (subtitle) subtitle.textContent = `${niceSeries} · ${niceArtists}`;
        if (rating) rating.textContent = String(data.rating || "-").toUpperCase();

        if (tags) {
            const allTags = [...characters, ...copyrights, ...generals].slice(0, 10);
            tags.innerHTML = allTags.length
                ? allTags.map((tag) => `<span class="gl-chip">#${escapeHtml(tag.replaceAll("_", " "))}</span>`).join("")
                : '<span class="gl-chip">#random</span>';
        }

        if (links) {
            const items = [];
            if (data.postUrl) items.push(`<a href="${escapeHtml(data.postUrl)}" target="_blank" rel="noopener noreferrer"><i class="fas fa-arrow-up-right-from-square"></i> Apri post</a>`);
            if (data.source) items.push(`<a href="${escapeHtml(data.source)}" target="_blank" rel="noopener noreferrer"><i class="fas fa-link"></i> Fonte</a>`);
            links.innerHTML = items.join("");
        }

        if (image && data.image) {
            const preload = new Image();
            preload.onload = () => {
                image.src = data.image;
                image.hidden = false;
                image.classList.add("is-visible");
                if (placeholder) placeholder.style.display = "none";
                resetCardMotion();
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
        if (isLoading) return;
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

    function animateDecision(type) {
        return new Promise((resolve) => {
            const card = $("#smashPassCard");
            if (!card) {
                resolve();
                return;
            }

            card.classList.remove("is-dragging", "is-resetting", "is-throw-smash", "is-throw-pass");
            card.style.transform = "";
            card.style.setProperty("--sp-smash-alpha", type === "smash" ? "1" : "0");
            card.style.setProperty("--sp-pass-alpha", type === "pass" ? "1" : "0");

            requestAnimationFrame(() => {
                card.classList.add(type === "smash" ? "is-throw-smash" : "is-throw-pass");
            });

            window.setTimeout(() => {
                resetCardMotion();
                resolve();
            }, 430);
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

    function applyDrag(dx, dy) {
        const card = $("#smashPassCard");
        if (!card) return;

        const width = Math.max(1, window.innerWidth);
        const limitedX = Math.max(-width * 0.48, Math.min(width * 0.48, dx));
        const rotate = limitedX / 18;
        const lift = Math.min(18, Math.abs(limitedX) / 18);
        const smashAlpha = Math.max(0, Math.min(1, limitedX / 130));
        const passAlpha = Math.max(0, Math.min(1, -limitedX / 130));

        card.style.transform = `translate3d(${limitedX}px, ${Math.max(-22, Math.min(22, dy * 0.08)) - lift}px, 0) rotate(${rotate}deg)`;
        card.style.setProperty("--sp-smash-alpha", String(smashAlpha));
        card.style.setProperty("--sp-pass-alpha", String(passAlpha));
    }

    function resetDrag() {
        const card = $("#smashPassCard");
        if (!card) return;

        card.classList.remove("is-dragging");
        card.classList.add("is-resetting");
        card.style.transform = "translate3d(0, 0, 0) rotate(0deg)";
        card.style.setProperty("--sp-smash-alpha", "0");
        card.style.setProperty("--sp-pass-alpha", "0");

        window.setTimeout(() => {
            card.classList.remove("is-resetting");
            card.style.transform = "";
        }, 220);
    }

    function initSwipe() {
        const area = $("#smashPassSwipeArea");
        const card = $("#smashPassCard");
        if (!area || !card) return;

        area.addEventListener("pointerdown", (event) => {
            if (isLoading || isVoting || !currentCard) return;
            if (event.pointerType === "mouse" && event.button !== 0) return;

            pointerStart = {
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
            if (!pointerStart || pointerStart.id !== event.pointerId) return;

            const dx = event.clientX - pointerStart.x;
            const dy = event.clientY - pointerStart.y;
            pointerStart.dx = dx;
            pointerStart.dy = dy;

            if (!pointerStart.locked && Math.abs(dx) + Math.abs(dy) > 10) {
                pointerStart.locked = Math.abs(dx) > Math.abs(dy) * 0.8;
            }

            if (!pointerStart.locked) return;
            event.preventDefault();

            cancelAnimationFrame(rafId);
            rafId = requestAnimationFrame(() => applyDrag(dx, dy));
        }, { passive: false });

        const endDrag = (event) => {
            if (!pointerStart || pointerStart.id !== event.pointerId) return;

            const { dx, dy, locked } = pointerStart;
            pointerStart = null;
            area.classList.remove("is-touching");
            area.releasePointerCapture?.(event.pointerId);

            const threshold = Math.min(130, Math.max(86, area.clientWidth * 0.24));
            const fastEnough = Math.abs(dx) > threshold && Math.abs(dx) > Math.abs(dy) * 0.75;

            if (locked && fastEnough) {
                vote(dx > 0 ? "smash" : "pass", true);
            } else {
                resetDrag();
            }
        };

        area.addEventListener("pointerup", endDrag, { passive: true });
        area.addEventListener("pointercancel", endDrag, { passive: true });
        area.addEventListener("lostpointercapture", () => {
            if (!pointerStart) return;
            pointerStart = null;
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
