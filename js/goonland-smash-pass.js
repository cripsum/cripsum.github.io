;(() => {
    "use strict";

    if (window.__goonlandSmashPassLoaded) return;
    window.__goonlandSmashPassLoaded = true;

    const $ = (selector, root = document) => root.querySelector(selector);

    let isLoading = false;
    let currentMode = "waifu_sfw";
    let currentCard = null;

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
            if (data.postUrl) items.push(`<a href="${escapeHtml(data.postUrl)}" target="_blank" rel="noopener noreferrer">Apri post</a>`);
            if (data.source) items.push(`<a href="${escapeHtml(data.source)}" target="_blank" rel="noopener noreferrer">Fonte</a>`);
            links.innerHTML = items.join("");
        }

        if (image && data.image) {
            const preload = new Image();
            preload.onload = () => {
                image.src = data.image;
                image.hidden = false;
                image.classList.add("is-visible");
                if (placeholder) placeholder.style.display = "none";
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

        const image = $("#smashPassImage");
        const spinner = $("#smashPassSpinner");
        const placeholder = $("#smashPassPlaceholder");
        const modeSelect = $("#smashPassMode");
        const smashBtn = $("#spSmashBtn");
        const passBtn = $("#spPassBtn");
        const nextBtn = $("#spNextBtn");

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
        if (modeSelect) modeSelect.disabled = true;
        if (smashBtn) smashBtn.disabled = true;
        if (passBtn) passBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
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
            if (modeSelect) modeSelect.disabled = false;
            if (smashBtn) smashBtn.disabled = false;
            if (passBtn) passBtn.disabled = false;
            if (nextBtn) nextBtn.disabled = false;
        }
    }

    
function vote(type) {
        if (isLoading || !currentCard) return;

        const stats = getStats(currentMode);
        if (type === "smash") {
            stats.smash += 1;
        } else {
            stats.pass += 1;
        }

        saveStats(currentMode, stats);
        updateStatsUi();
        showToast(type === "smash" ? "Smash" : "Pass");
        loadNextCard();
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

        if (smashBtn) smashBtn.addEventListener("click", () => vote("smash"));
        if (passBtn) passBtn.addEventListener("click", () => vote("pass"));
        if (nextBtn) nextBtn.addEventListener("click", loadNextCard);

        document.addEventListener("keydown", (event) => {
            if (document.body.dataset.goonlandPage !== "smash-pass") return;
            if (event.target && /input|textarea|select/i.test(event.target.tagName)) return;

            if (event.key === "ArrowLeft") {
                event.preventDefault();
                vote("pass");
            } else if (event.key === "ArrowRight") {
                event.preventDefault();
                vote("smash");
            } else if (event.key === " ") {
                event.preventDefault();
                loadNextCard();
            }
        });

        updateStatsUi();
        loadNextCard();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initSmashPass, { once: true });
    } else {
        initSmashPass();
    }
})();
