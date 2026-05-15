(function () {
    "use strict";

    const $ = (selector) => document.querySelector(selector);
    const $$ = (selector) => Array.from(document.querySelectorAll(selector));

    const state = {
        activeBanner: "standard",
        data: null,
        isPulling: false,
        lastPayload: null,
    };

    const rarityLabels = {
        comune: "Comune",
        raro: "Raro",
        epico: "Epico",
        leggendario: "Leggendario",
        speciale: "Speciale",
        segreto: "Segreto",
        theone: "The One",
    };

    const rarityMessages = {
        comune: "bravo fra hai pullato un personaggio comune, skill issue xd",
        raro: "buono dai, hai pullato un personaggio raro!",
        epico: "hai pullato un personaggio epico, tanta roba, ma poteva andare meglio",
        leggendario: "che fortuna, hai pullato un personaggio leggendario!",
        speciale: "COM'E POSSIBILE? HAI PULLATO UN PERSONAGGIO SPECIALE!",
        segreto: "COSA? HAI PULLATO UN PERSONAGGIO SEGRETO? aura.",
        theone: "INCREDBILE! HAI PULLATO IL PERSONAGGIO PIU RARO DI TUTTI!!!",
    };

    function esc(value) {
        return String(value ?? "")
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function normalizeRarity(value) {
        return String(value || "comune")
            .toLowerCase()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/[\s_-]+/g, "");
    }

    function mediaUrl(path, base) {
        const value = String(path || "").trim();
        if (!value) return "";
        if (/^https?:\/\//i.test(value) || value.startsWith("/")) return value;
        return `${base}/${value.split("/").map(encodeURIComponent).join("/")}`;
    }

    function characterImage(character) {
        return character?.image_url || mediaUrl(character?.img_url || "Susremaster.png", "/img");
    }

    function characterVideo(character) {
        const direct = character?.video_src || mediaUrl(character?.video_url || "", "/vid");
        if (direct) return direct;
        return normalizeRarity(character?.rarita || character?.rarità) === "theone" ? "/vid/shorekeeperpull.mp4" : "";
    }

    function characterAudio(character) {
        return mediaUrl(character?.audio_url || "", "/audio");
    }

    function playCharacterAudio(character) {
        const src = characterAudio(character);
        const audio = $("#suonoCassa");
        if (!src || !audio) return;

        audio.pause();
        audio.src = src;
        audio.currentTime = 0;
        audio.volume = 1;
        audio.muted = false;
        audio.play().catch(() => {});
    }

    function stopCharacterAudio() {
        const audio = $("#suonoCassa");
        if (!audio) return;

        audio.pause();
        audio.removeAttribute("src");
        audio.load?.();
    }

    async function api(path, options = {}) {
        const response = await fetch(path, {
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                ...(options.body ? { "Content-Type": "application/json" } : {}),
                ...(options.headers || {}),
            },
            ...options,
        });

        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.ok === false || data.status === "error" || data.status === "schema_missing") {
            const error = new Error(data.message || "Errore di connessione.");
            error.payload = data;
            throw error;
        }
        return data;
    }

    async function loadState() {
        const payload = await api("/api/api_gacha_state.php");
        state.data = payload.state;
        state.activeBanner = payload.state?.active || "standard";
        render();
    }

    function activeBanner() {
        return state.data?.banners?.[state.activeBanner] || state.data?.banners?.standard || null;
    }

    function render() {
        renderTabs();
        renderBanner();
        renderHud();
        renderAction();
        renderSchemaWarning();
    }

    function renderTabs() {
        $$(".gacha-tab").forEach((tab) => {
            const bannerId = tab.dataset.banner;
            const banner = state.data?.banners?.[bannerId];
            tab.classList.toggle("is-active", bannerId === state.activeBanner);
            tab.disabled = bannerId === "evento" && !banner?.available;
        });
    }

    function renderBanner() {
        const banner = activeBanner();
        const card = $("#gacha-banner-card");
        if (!banner || !card) return;

        const rateup = banner.rateup;
        card.style.setProperty("--gacha-banner-image", `url("${banner.image || "/img/cassa.png"}")`);
        $("#gacha-banner-kicker").innerHTML = `<i class="fas fa-${banner.type === "evento" ? "star" : "box-open"}"></i><span>${banner.type === "evento" ? "Evento" : "Standard"}</span>`;
        $("#gacha-banner-title").textContent = banner.nome || "Banner";
        $("#gacha-banner-description").textContent = banner.descrizione || "";

        const rateupBox = $("#gacha-rateup-panel");
        if (!rateupBox) return;

        if (rateup) {
            rateupBox.hidden = false;
            rateupBox.innerHTML = `
                <img src="${esc(characterImage(rateup))}" alt="${esc(rateup.nome)}" onerror="this.onerror=null;this.src='/img/Susremaster.png';" draggable="false">
                <span class="gacha-rateup-copy">
                    <span>Rate Up</span>
                    <strong>${esc(rateup.nome)}</strong>
                    <small>${esc(rateup.caratteristiche || rateup.descrizione || rarityLabels[normalizeRarity(rateup.rarita)] || "Limitato")}</small>
                </span>
            `;
        } else {
            rateupBox.hidden = false;
            rateupBox.innerHTML = `
                <img src="/img/cassa.png" alt="Banner Standard" draggable="false">
                <span class="gacha-rateup-copy">
                    <span>Pool Base</span>
                    <strong>Personaggi Standard</strong>
                    <small>Pity a 80 pull, eventi esclusi.</small>
                </span>
            `;
        }
    }

    function renderHud() {
        if (!state.data) return;

        const points = state.data.user?.punti ?? 0;
        const pity = state.data.pity || {};
        const banner = activeBanner();
        const currentPity = banner?.type === "evento" ? pity.evento : pity.standard;
        const guarantee = banner?.type === "evento" && pity.garantito_evento;

        $("#gacha-points-value").textContent = String(points);
        $("#gacha-pity-value").textContent = `${currentPity || 0}/${pity.max || 80}`;
        $("#gacha-pity-label").textContent = guarantee ? "Garantito" : banner?.type === "evento" ? "50/50" : "Standard";
    }

    function renderAction() {
        const button = $("#gacha-open-button");
        const banner = activeBanner();
        if (!button || !banner || !state.data) return;

        const cost = Number(banner.costo || 0);
        const points = Number(state.data.user?.punti || 0);
        const schemaReady = Boolean(state.data.schema?.core_ready);
        const disabled = state.isPulling || !schemaReady || !banner.available || (cost > 0 && points < cost);

        button.disabled = disabled;
        $("#gacha-open-label").textContent = state.isPulling ? "Apertura..." : "Apri 1x";
        $("#gacha-open-cost").textContent = cost > 0 ? `${cost} Punti` : "Gratis";

        if (!schemaReady) {
            button.title = "Applica prima la migrazione SQL gacha.";
        } else if (!banner.available) {
            button.title = "Banner non disponibile.";
        } else if (cost > 0 && points < cost) {
            button.title = "Punti insufficienti.";
        } else {
            button.title = "";
        }
    }

    function renderSchemaWarning() {
        const warning = $("#gacha-schema-warning");
        if (!warning || !state.data) return;

        const missing = state.data.schema?.missing || [];
        if (missing.length === 0) {
            warning.classList.remove("is-visible");
            warning.textContent = "";
            return;
        }

        warning.classList.add("is-visible");
        warning.textContent = `Database da aggiornare: ${missing.slice(0, 4).join(", ")}${missing.length > 4 ? "..." : ""}`;
    }

    function setActiveBanner(bannerId) {
        const banner = state.data?.banners?.[bannerId];
        if (!banner || (bannerId === "evento" && !banner.available)) return;
        state.activeBanner = bannerId;
        render();
    }

    function debugPullParams() {
        const debugEnabled = window.gachaV2Debug === true || new URLSearchParams(window.location.search).get("gacha_debug") === "1";
        if (!debugEnabled || typeof getCookie !== "function") return {};

        const preferences = getCookie("preferences") || {};
        const rarityMap = [
            ["SoloTheOne", "theone"],
            ["SoloSegreti", "segreto"],
            ["SoloSpeciali", "speciale"],
            ["SoloLeggendari", "leggendario"],
            ["SoloEpici", "epico"],
            ["SoloRari", "raro"],
            ["SoloComuni", "comune"],
        ];

        const params = {};
        const forced = rarityMap.find(([key]) => preferences[key] === true);
        if (forced) params.debug_rarity = forced[1];
        if (preferences.SoloPoppy === true) params.debug_category = "poppy";
        return params;
    }

    function openOverlay() {
        const overlay = $("#gacha-overlay");
        if (!overlay) return;

        overlay.classList.add("is-open");
        document.body.classList.add("gacha-overlay-open", "overflow-hidden");
        requestAnimationFrame(() => overlay.classList.add("is-visible"));
    }

    function resetOverlay() {
        $("#gacha-overlay-error")?.classList.remove("is-visible");
        $("#gacha-overlay-actions")?.classList.remove("is-visible");
        $("#gacha-result")?.classList.remove("is-visible");
        $("#gacha-result").innerHTML = "";
        $("#gacha-overlay-video").innerHTML = "";
        $("#gacha-loader")?.classList.remove("is-hidden");
    }

    function closeOverlay() {
        const overlay = $("#gacha-overlay");
        if (!overlay) return;

        overlay.classList.remove("is-visible");
        setTimeout(() => {
            overlay.classList.remove("is-open");
            resetOverlay();
            document.body.classList.remove("gacha-overlay-open", "overflow-hidden");
            stopCharacterAudio();
        }, 240);
    }

    async function startPull(bannerId = state.activeBanner) {
        if (state.isPulling) return false;
        const banner = state.data?.banners?.[bannerId];
        if (!banner || !banner.available) return false;

        state.isPulling = true;
        renderAction();
        stopCharacterAudio();
        resetOverlay();
        openOverlay();

        try {
            const payload = await api("/api/api_gacha_pull.php", {
                method: "POST",
                body: JSON.stringify({
                    banner_id: bannerId,
                    ...debugPullParams(),
                }),
            });

            state.lastPayload = payload;
            state.data = payload.state || state.data;
            state.activeBanner = bannerId;

            const character = payload.character;
            const rarity = normalizeRarity(character?.rarita || character?.rarità);
            if (typeof applyLootboxRarityVisual === "function") applyLootboxRarityVisual(rarity);
            if (typeof setComuniDiFila === "function") setComuniDiFila(rarity);
            if (typeof setLastCharacterFound === "function") setLastCharacterFound(character?.nome || "");
            if (typeof getInventory === "function") getInventory().catch(() => {});
            if (typeof apriCassa === "function") apriCassa().catch(() => {});
            playCharacterAudio(character);

            await showReveal(payload);
            render();
            return true;
        } catch (error) {
            showError(error.message || "Errore durante la pull.");
            return false;
        } finally {
            state.isPulling = false;
            renderAction();
        }
    }

    async function showReveal(payload) {
        const character = payload.character;
        const videoSrc = characterVideo(character);
        const rarity = normalizeRarity(character?.rarita || character?.rarità);
        $("#gacha-loader-text").textContent = rarityLabels[rarity] ? `${rarityLabels[rarity]} in arrivo` : "Apertura";

        if (videoSrc) {
            await playRevealVideo(videoSrc);
            showResult(payload);
            return;
        }

        await new Promise((resolve) => setTimeout(resolve, rarity === "segreto" || rarity === "theone" ? 1400 : 850));
        showResult(payload);
    }

    function playRevealVideo(src) {
        return new Promise((resolve) => {
            const stage = $("#gacha-overlay-video");
            const loader = $("#gacha-loader");
            if (!stage) {
                resolve();
                return;
            }

            const video = document.createElement("video");
            video.src = src;
            video.autoplay = true;
            video.muted = false;
            video.playsInline = true;
            video.setAttribute("playsinline", "");
            video.preload = "auto";

            let done = false;
            const finish = () => {
                if (done) return;
                done = true;
                loader?.classList.add("is-hidden");
                resolve();
            };

            video.addEventListener("canplay", () => loader?.classList.add("is-hidden"), { once: true });
            video.addEventListener("ended", finish, { once: true });
            video.addEventListener("error", finish, { once: true });

            stage.innerHTML = "";
            stage.appendChild(video);
            video.play().catch(() => {
                video.muted = true;
                video.play().catch(() => setTimeout(finish, 900));
            });
            setTimeout(finish, 18000);
        });
    }

    function showResult(payload) {
        const character = payload.character;
        const rarity = normalizeRarity(character?.rarita || character?.rarità);
        const result = $("#gacha-result");
        const actions = $("#gacha-overlay-actions");
        const loader = $("#gacha-loader");
        if (!result) return;

        loader?.classList.add("is-hidden");
        result.innerHTML = `
            <article class="gacha-result-card lootbox-rarity-${esc(rarity)}">
                <div class="gacha-result-image-wrap">
                    <img src="${esc(characterImage(character))}" alt="${esc(character?.nome || "Personaggio")}" onerror="this.onerror=null;this.src='/img/Susremaster.png';" draggable="false">
                    ${payload.is_new ? '<span class="gacha-new-label">NEW!</span>' : ""}
                </div>
                <p class="gacha-result-meta">
                    <i class="fas fa-star"></i>
                    <span>${esc(rarityLabels[rarity] || rarity)}</span>
                </p>
                <h2 class="gacha-result-name">${esc(character?.nome || "Personaggio")}</h2>
                <p class="lootbox-rarity-message">${esc(rarityMessages[rarity] || "Personaggio ottenuto.")}</p>
            </article>
        `;
        result.classList.add("is-visible");
        actions?.classList.add("is-visible");
    }

    function showError(message) {
        $("#gacha-loader")?.classList.add("is-hidden");
        const errorBox = $("#gacha-overlay-error");
        const actions = $("#gacha-overlay-actions");
        if (errorBox) {
            errorBox.textContent = message;
            errorBox.classList.add("is-visible");
        }
        actions?.classList.add("is-visible");
    }

    async function redeemCode() {
        const input = $("#codiceSegreto");
        const code = input?.value?.trim() || "";
        if (!code) {
            alert("Inserisci un codice.");
            return;
        }

        state.isPulling = true;
        resetOverlay();
        openOverlay();
        renderAction();

        try {
            const payload = await api("/api/api_redeem_gacha_code.php", {
                method: "POST",
                body: JSON.stringify({ code }),
            });

            state.lastPayload = payload;
            state.data = payload.state || state.data;
            const character = payload.character;
            const rarity = normalizeRarity(character?.rarita || character?.rarità);
            if (typeof applyLootboxRarityVisual === "function") applyLootboxRarityVisual(rarity);
            if (typeof setLastCharacterFound === "function") setLastCharacterFound(character?.nome || "");
            if (typeof getInventory === "function") getInventory().catch(() => {});
            playCharacterAudio(character);
            await showReveal(payload);
            render();
        } catch (error) {
            showError(error.message || "Codice non valido.");
        } finally {
            state.isPulling = false;
            renderAction();
        }
    }

    function hookEvents() {
        $$(".gacha-tab").forEach((tab) => {
            tab.addEventListener("click", () => setActiveBanner(tab.dataset.banner));
        });

        $("#gacha-open-button")?.addEventListener("click", () => startPull(state.activeBanner));
        $("#gacha-close-overlay")?.addEventListener("click", closeOverlay);
        $("#gacha-pull-again")?.addEventListener("click", () => startPull(state.activeBanner));

        const settingsModal = $("#impostazioniModal");
        settingsModal?.addEventListener("show.bs.modal", () => {
            const overlay = $("#gacha-overlay");
            overlay?.classList.remove("is-visible", "is-open");
            resetOverlay();
            document.body.classList.remove("gacha-overlay-open", "overflow-hidden");
            state.isPulling = false;
            stopCharacterAudio();
            renderAction();
        });

        settingsModal?.addEventListener("hidden.bs.modal", () => {
            if (!$("#gacha-overlay")?.classList.contains("is-open")) {
                document.body.classList.remove("gacha-overlay-open", "overflow-hidden");
                document.body.style.overflow = "auto";
            }
        });

        const chest = $("#cassa");
        if (chest) {
            chest.onclick = (event) => {
                event.preventDefault();
                startPull(state.activeBanner);
            };
            chest.ondblclick = (event) => {
                event.preventDefault();
                startPull(state.activeBanner);
            };
        }

        document.addEventListener(
            "keydown",
            (event) => {
                if (event.repeat) return;
                const modalOpen = document.body.classList.contains("modal-open") || $("#impostazioniModal")?.classList.contains("show");
                const focusedControl = document.activeElement?.closest?.("input, textarea, select, button, [contenteditable='true']");
                if (modalOpen || focusedControl) return;

                if (event.code === "Space") {
                    event.preventDefault();
                    startPull(state.activeBanner);
                }
                if (event.code === "Enter" && $("#gacha-overlay")?.classList.contains("is-open") && !state.isPulling) {
                    event.preventDefault();
                    startPull(state.activeBanner);
                }
                if (event.code === "Escape" && $("#gacha-overlay")?.classList.contains("is-open") && !state.isPulling) {
                    closeOverlay();
                }
            },
            true
        );
    }

    function overrideLegacyEntrypoints() {
        window.handleChestClick = (event) => {
            event?.preventDefault?.();
            return startPull(state.activeBanner);
        };
        window.handleDoubleClick = (event) => {
            event?.preventDefault?.();
            return startPull(state.activeBanner);
        };
        window.startLootboxPull = () => startPull(state.activeBanner);
        window.refresh = () => startPull(state.activeBanner);
        window.riscattaCodice = redeemCode;
        window.gachaV2 = {
            state,
            reload: loadState,
            pull: startPull,
            close: closeOverlay,
        };
    }

    async function init() {
        hookEvents();
        overrideLegacyEntrypoints();
        if (typeof createStars === "function" && !document.querySelector(".star")) createStars();

        try {
            await loadState();
        } catch (error) {
            state.data = {
                schema: { core_ready: false, missing: ["api_gacha_state"] },
                user: { punti: 0 },
                pity: { standard: 0, evento: 0, garantito_evento: false, max: 80 },
                banners: {
                    standard: { type: "standard", nome: "Banner Standard", descrizione: "Errore caricamento stato.", costo: 0, available: false, image: "/img/cassa.png" },
                    evento: { type: "evento", nome: "Banner Evento", descrizione: "Errore caricamento stato.", costo: 100, available: false, image: "/img/cassa.png" },
                },
                active: "standard",
            };
            render();
            showError(error.message || "Errore caricamento gacha.");
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
