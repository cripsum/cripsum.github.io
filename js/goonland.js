(()=>{
    "use strict";

    if(window.__goonlandV2Loaded) return;
    window.__goonlandV2Loaded = true;

    let isLoading = false;
    let cooldownActive = false;
    let currentImageUrl = "";
    let toastTimer = null;

    const $ = (s, r = document) => r.querySelector(s);
    const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

    function safeUnlockAchievement(id){
        if(typeof window.unlockAchievement !== "function") return;
        try {
            window.unlockAchievement(id);
        } catch(e){
            console.warn("[GoonLand] Achievement non disponibile:", e);
        }
    }

    function getCookie(name){
        try {
            const cookies = document.cookie ? document.cookie.split("; ") : [];
            for(const c of cookies){
                const [k, ...rest] = c.split("=");
                if(k !== name) continue;
                const raw = rest.join("=");
                const decoded = decodeURIComponent(raw || "");
                try {
                    return JSON.parse(decoded);
                } catch {
                    return decoded;
                }
            }
        } catch {
            return null;
        }
        return null;
    }

    function setCookie(name, value){
        document.cookie = `${name}=${encodeURIComponent(JSON.stringify(value))}; path=/; expires=Fri, 31 Dec 9999 23:59:59 GMT; SameSite=Lax`;
    }

    function showToast(message){
        const toast = $("#goonlandToast");
        if(!toast) return;
        const text = toast.querySelector("span");
        if(text) text.textContent = message;
        toast.hidden = false;
        requestAnimationFrame(() => toast.classList.add("is-visible"));
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => {
            toast.classList.remove("is-visible");
            setTimeout(() => {
                toast.hidden = true;
            }, 180);
        }, 2200);
    }

    function setStatus(message){
        const status = $("#generatorStatus");
        if(status) status.textContent = message;
    }

    function initReveal(){
        const items = $$(".gl-reveal");
        if(!("IntersectionObserver" in window)){
            items.forEach(i => i.classList.add("is-visible"));
            return;
        }
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if(!entry.isIntersecting) return;
                entry.target.classList.add("is-visible");
                observer.unobserve(entry.target);
            });
        }, { threshold: 0.12 });

        items.forEach(item => observer.observe(item));
    }

    function initTopButton(){
        const topBtn = $("[data-gl-top]");
        if(!topBtn) return;
        window.addEventListener("scroll", () => {
            topBtn.classList.toggle("is-visible", window.scrollY > 650);
        }, { passive: true });
        topBtn.addEventListener("click", () => window.scrollTo({ top: 0, behavior: "smooth" }));
    }

    function initFallbackImages(){
        $$('[data-gl-fallback]').forEach((img) => {
            img.addEventListener('error', () => {
                img.onerror = null;
                img.src = '/img/Susremaster.png';
            });
        });
    }

    function initHome(){
        if(document.body.dataset.goonlandPage !== "home") return;
        safeUnlockAchievement(15);
    }

    function checkDaysVisitedGoon(){
        let days = getCookie("daysVisitedGoon");
        if(!Array.isArray(days)) days = [];

        const today = new Date().toISOString().slice(0, 10);
        if(!days.includes(today)){
            days.push(today);
            setCookie("daysVisitedGoon", days);
        }

        if(days.length >= 10) safeUnlockAchievement(20);
    }

    function startCooldown(){
        cooldownActive = true;
        const btn = $("#generateBtn");
        const countdown = $("#countdown");
        let timeLeft = 2;

        const tick = () => {
            if(countdown) countdown.textContent = `Attendi ${timeLeft} secondi...`;
            if(btn) btn.innerHTML = `<i class="fas fa-clock"></i><span>Attendi (${timeLeft}s)</span>`;

            if(timeLeft <= 0){
                cooldownActive = false;
                if(btn){
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-shuffle"></i><span>Genera nuova foto</span>';
                }
                if(countdown) countdown.textContent = "";
                setStatus("Pronto");
                return;
            }

            timeLeft -= 1;
            setTimeout(tick, 1000);
        };

        tick();
    }

    async function resolveImageUrl(contentType){
        const endpoint = new URL(window.location.href);
        endpoint.search = "";
        endpoint.searchParams.set("generate_image", "1");
        endpoint.searchParams.set("contentType", contentType);
        endpoint.searchParams.set("_", Date.now().toString());

        const response = await fetch(endpoint.toString(), {
            cache: "no-store",
            credentials: "same-origin",
            headers: {
                "Accept": "application/json"
            }
        });

        let data = null;
        try {
            data = await response.json();
        } catch {
            data = null;
        }

        if (!response.ok) {
            throw new Error(data?.error || `Proxy immagini HTTP ${response.status}`);
        }

        if (!data || typeof data.url !== "string" || !data.url.trim()) {
            throw new Error("URL immagine mancante");
        }

        return data.url;
    }

    window.generateImage = async function(){
        if(isLoading || cooldownActive) return;

        const btn = $("#generateBtn");
        const spinner = $("#loadingSpinner");
        const container = $("#imageContainer");
        const contentType = $("#contentType")?.value || "sfw/waifu";
        const downloadBtn = $("#downloadBtn");

        if(!container || !btn) return;

        isLoading = true;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i><span>Caricamento...</span>';
        if(spinner) spinner.style.display = "block";
        if(downloadBtn) downloadBtn.hidden = true;
        setStatus("Caricamento");

        const oldImg = container.querySelector(".generated-image");
        if(oldImg){
            oldImg.classList.remove("is-visible");
            setTimeout(() => oldImg.remove(), 220);
        }

        const placeholder = container.querySelector(".gl-placeholder");
        if(placeholder) placeholder.style.display = "none";

        try {
            const imageUrl = await resolveImageUrl(contentType);

            await new Promise((resolve, reject) => {
                const preload = new Image();
                preload.onload = resolve;
                preload.onerror = () => reject(new Error("Errore nel caricamento immagine"));
                preload.src = imageUrl;
            });

            const displayImg = document.createElement("img");
            displayImg.src = imageUrl;
            displayImg.className = "generated-image";
            displayImg.alt = "Immagine generata da GoonLand";
            displayImg.loading = "eager";
            container.appendChild(displayImg);
            requestAnimationFrame(() => displayImg.classList.add("is-visible"));

            currentImageUrl = imageUrl;
            if(downloadBtn) downloadBtn.hidden = false;
            if(spinner) spinner.style.display = "none";
            isLoading = false;
            setStatus("Generata");

            try {
                await fetch("/api/incrementa_counter_goon", { cache: "no-store" });
                const clickResponse = await fetch("/api/get_clickgoon", { cache: "no-store" });
                if(clickResponse.ok){
                    const clickData = await clickResponse.json();
                    const click = Number(clickData.total || 0);
                    if(click === 100) safeUnlockAchievement(19);
                }
            } catch(err){
                console.warn("[GoonLand] Counter non aggiornato:", err);
            }

            showToast("Immagine generata");
            startCooldown();
        } catch(error){
            console.error("[GoonLand] Errore:", error);
            if(spinner) spinner.style.display = "none";
            isLoading = false;
            cooldownActive = false;
            setStatus("Errore");
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-shuffle"></i><span>Genera nuova foto</span>';
            if(placeholder) placeholder.style.display = "grid";
            showToast("Errore nel caricamento. Riprova.");
        }
    };

    window.downloadImage = function(){
        const img = document.querySelector(".generated-image");
        const imageUrl = currentImageUrl || img?.src;

        if(!imageUrl){
            showToast("Genera prima un’immagine");
            return;
        }

        try {
            const proxyUrl = `${window.location.pathname}?download_image=1&url=${encodeURIComponent(imageUrl)}`;
            const link = document.createElement("a");
            link.href = proxyUrl;
            link.download = "";
            link.target = "_blank";
            document.body.appendChild(link);
            link.click();
            link.remove();
            showToast("Download avviato");
        } catch(error){
            console.error("[GoonLand] Download fallito:", error);
            showToast("Download non riuscito");
        }
    };

    document.addEventListener("DOMContentLoaded", () => {
        initReveal();
        initTopButton();
        initFallbackImages();
        initHome();
        if(document.body.dataset.goonlandPage === "generator") checkDaysVisitedGoon();
    });
})();

;(() => {
    "use strict";

    if (window.__goonlandCustomSelectV21) return;
    window.__goonlandCustomSelectV21 = true;

    function initCustomSelect() {
        document.querySelectorAll("[data-gl-custom-select]").forEach((wrap) => {
            const select = wrap.querySelector("select");
            const trigger = wrap.querySelector(".gl-select-trigger");
            const current = wrap.querySelector(".gl-select-current");
            const options = Array.from(wrap.querySelectorAll(".gl-select-menu [data-value]"));

            if (!select || !trigger || !current || !options.length) return;

            const sync = (value, emitChange = false) => {
                const realOption = Array.from(select.options).find((option) => option.value === value) || select.options[0];
                if (!realOption) return;

                const oldValue = select.value;
                select.value = realOption.value;
                current.textContent = realOption.textContent.trim();

                options.forEach((button) => {
                    const active = button.dataset.value === realOption.value;
                    button.classList.toggle("is-active", active);
                    button.setAttribute("aria-selected", active ? "true" : "false");
                });

                if (emitChange && oldValue !== realOption.value) {
                    select.dispatchEvent(new Event("change", { bubbles: true }));
                }
            };

            trigger.addEventListener("click", (event) => {
                event.stopPropagation();
                const isOpen = wrap.classList.toggle("is-open");
                trigger.setAttribute("aria-expanded", isOpen ? "true" : "false");
            });

            options.forEach((button) => {
                button.addEventListener("click", (event) => {
                    event.stopPropagation();
                    sync(button.dataset.value, true);
                    wrap.classList.remove("is-open");
                    trigger.setAttribute("aria-expanded", "false");
                });
            });

            select.addEventListener("change", () => sync(select.value, false));

            sync(select.value || options[0].dataset.value, false);
        });

        document.addEventListener("click", () => {
            document.querySelectorAll("[data-gl-custom-select].is-open").forEach((wrap) => {
                wrap.classList.remove("is-open");
                const trigger = wrap.querySelector(".gl-select-trigger");
                if (trigger) trigger.setAttribute("aria-expanded", "false");
            });
        });

        document.addEventListener("keydown", (event) => {
            if (event.key !== "Escape") return;

            document.querySelectorAll("[data-gl-custom-select].is-open").forEach((wrap) => {
                wrap.classList.remove("is-open");
                const trigger = wrap.querySelector(".gl-select-trigger");
                if (trigger) trigger.setAttribute("aria-expanded", "false");
            });
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initCustomSelect, { once: true });
    } else {
        initCustomSelect();
    }
})();
