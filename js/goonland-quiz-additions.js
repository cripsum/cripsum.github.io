;(() => {
    "use strict";

    if (window.__goonlandAnimeQuizLoaded) return;
    window.__goonlandAnimeQuizLoaded = true;

    const $ = (selector, root = document) => root.querySelector(selector);

    const questions = [
        {
            id: "type",
            title: "Che tipo di ragazza ti attira di più?",
            hint: "Partiamo dalla vibe principale.",
            answers: [
                { value: "sweet", label: "Dolce e affettuosa", desc: "Quella che ti tratta bene.", icon: "fa-heart", scores: { sweet: 3, cute: 1 } },
                { value: "cold", label: "Fredda e misteriosa", desc: "Poche parole, sguardo letale.", icon: "fa-snowflake", scores: { cold: 3, elegant: 1 } },
                { value: "tsundere", label: "Scontrosa ma tenera", desc: "Ti insulta, poi arrossisce.", icon: "fa-fire", scores: { tsundere: 3, chaotic: 1 } },
                { value: "dominant", label: "Sicura e dominante", desc: "Entra e decide lei.", icon: "fa-crown", scores: { dommy: 3, dominant: 2 } },
            ],
        },
        {
            id: "control",
            title: "Ti piace essere dominato?",
            hint: "Domanda chiave per capire se vuoi una soft girl o una che ti mette in riga.",
            answers: [
                { value: "dominated_yes", label: "Sì, molto", desc: "Comanda lei, fine.", icon: "fa-hand", scores: { dommy: 3, dominant: 3 } },
                { value: "dominated_soft", label: "Un po’, ma non troppo", desc: "Ti piace il teasing, non il trauma.", icon: "fa-face-grin-wink", scores: { tease: 3, tsundere: 1 } },
                { value: "control_me", label: "No, controllo io", desc: "Preferisci guidare tu il gioco.", icon: "fa-chess-king", scores: { sweet: 1, elegant: 1 } },
                { value: "depends", label: "Dipende da lei", desc: "Se ha abbastanza aura, sì.", icon: "fa-dice", scores: { chaotic: 1, tease: 2 } },
            ],
        },
        {
            id: "personality",
            title: "Che personalità preferisci?",
            hint: "Qui scegli proprio l’archetipo anime.",
            answers: [
                { value: "shy", label: "Timida e dolce", desc: "Parla piano e arrossisce.", icon: "fa-seedling", scores: { sweet: 3, cute: 2 } },
                { value: "tsundere", label: "Tsundere", desc: "Fa la dura, ma si scioglie.", icon: "fa-bolt", scores: { tsundere: 4 } },
                { value: "yandere", label: "Yandere", desc: "Troppo attaccata, forse troppo.", icon: "fa-eye", scores: { yandere: 4, chaotic: 2 } },
                { value: "dommy", label: "Dommy mommy", desc: "Ti guarda dall’alto e tu capisci.", icon: "fa-crown", scores: { dommy: 4, dominant: 2 } },
            ],
        },
        {
            id: "vibe",
            title: "Che vibe deve avere?",
            hint: "Più estetica che carattere.",
            answers: [
                { value: "cute", label: "Cute", desc: "Morbida, colorata, adorabile.", icon: "fa-star", scores: { cute: 3, sweet: 1 } },
                { value: "dark", label: "Dark", desc: "Nero, occhi pesanti, energia strana.", icon: "fa-moon", scores: { goth: 2, cold: 2 } },
                { value: "elegant", label: "Elegante", desc: "Classe, presenza, controllo.", icon: "fa-gem", scores: { elegant: 3, dommy: 1 } },
                { value: "chaotic", label: "Caotica", desc: "Non sai mai cosa farà.", icon: "fa-wand-sparkles", scores: { chaotic: 3, yandere: 1 } },
            ],
        },
        {
            id: "relationship",
            title: "Che tipo di rapporto vuoi?",
            hint: "La dinamica conta quanto l’aspetto.",
            answers: [
                { value: "wholesome", label: "Romantico e wholesome", desc: "Zero stress, solo comfort.", icon: "fa-heart-circle-check", scores: { sweet: 3, cute: 1 } },
                { value: "tease", label: "Lei ti prende in giro", desc: "Ti provoca perché le piace.", icon: "fa-face-laugh-squint", scores: { tease: 3, tsundere: 1 } },
                { value: "command", label: "Lei ti comanda", desc: "Non chiedi, obbedisci.", icon: "fa-gavel", scores: { dommy: 3, dominant: 2 } },
                { value: "toxic", label: "Tossica ma irresistibile", desc: "Red flag, ma con stile.", icon: "fa-skull", scores: { yandere: 3, chaotic: 2 } },
            ],
        },
        {
            id: "style",
            title: "Che stile ti piace di più?",
            hint: "Qui scegli il pacchetto estetico.",
            answers: [
                { value: "maid", label: "Maid", desc: "Classica, pulita, iconica.", icon: "fa-broom", scores: { maid: 4, sweet: 1 } },
                { value: "goth", label: "Goth", desc: "Scura, elegante, un po’ fredda.", icon: "fa-cross", scores: { goth: 4, cold: 1 } },
                { value: "gyaru", label: "Gyaru", desc: "Solare, teasing, zero vergogna.", icon: "fa-sun", scores: { gyaru: 4, tease: 1 } },
                { value: "fantasy", label: "Fantasy / demoniaca", desc: "Corna, magia, problemi.", icon: "fa-dragon", scores: { fantasy: 4, chaotic: 1 } },
            ],
        },
        {
            id: "body",
            title: "Che fisico preferisci?",
            hint: "Aspetto fisico, senza girarci troppo intorno.",
            answers: [
                { value: "tall", label: "Alta", desc: "Presenza forte, aura da boss.", icon: "fa-up-long", scores: { dommy: 1, elegant: 1 } },
                { value: "short_adult", label: "Bassa/minuta", desc: "Piccola, ma non una bambina", icon: "fa-down-long", scores: { cute: 1, sweet: 1 } },
                { value: "curvy", label: "Curvy", desc: "Forme evidenti, energia più spicy.", icon: "fa-heart", scores: { dommy: 1, tease: 1 } },
                { value: "athletic", label: "Atletica", desc: "Fisico asciutto e deciso.", icon: "fa-dumbbell", scores: { elegant: 1, dominant: 1 } },
            ],
        },
        {
            id: "hair",
            title: "Che capelli preferisci?",
            hint: "Scelta semplice, ma pesa molto sul risultato.",
            answers: [
                { value: "black_hair", label: "Neri", desc: "Classici, freddi, forti.", icon: "fa-circle", scores: { cold: 1, goth: 1 } },
                { value: "blonde_hair", label: "Biondi", desc: "Gyaru o queen energy.", icon: "fa-sun", scores: { gyaru: 1, tease: 1 } },
                { value: "white_hair", label: "Bianchi / argento", desc: "Misteriosa e un po’ divina.", icon: "fa-snowflake", scores: { elegant: 1, fantasy: 1 } },
                { value: "colored_hair", label: "Colorati", desc: "Più caos, più personalità.", icon: "fa-palette", scores: { chaotic: 1, cute: 1 } },
            ],
        },
        {
            id: "outfit",
            title: "Che outfit ti attira di più?",
            hint: "Ultimo tocco visivo prima dell’intensità.",
            answers: [
                { value: "uniform", label: "Uniforme", desc: "Ordinata, pulita, controllata.", icon: "fa-user-tie", scores: { elegant: 1, cold: 1 } },
                { value: "dress", label: "Vestito elegante", desc: "Raffinata e composta.", icon: "fa-gem", scores: { elegant: 2 } },
                { value: "dark_outfit", label: "Outfit dark", desc: "Nero, gotico, mood pesante.", icon: "fa-moon", scores: { goth: 2 } },
                { value: "provocative", label: "Outfit provocante", desc: "Meno sottile, più diretto.", icon: "fa-fire-flame-curved", scores: { tease: 2, dommy: 1 } },
            ],
        },
        {
            id: "intensity",
            title: "Quanto vuoi che sia spinta?",
            hint: "Scegli il livello. Il sito userà il rating Danbooru più vicino.",
            answers: [
                { value: "safe", label: "Safe", desc: "Pulita, niente contenuto spinto.", icon: "fa-shield-heart", scores: { sweet: 1 } },
                { value: "suggestive", label: "Suggestiva", desc: "Leggera, ma con vibe più adulta.", icon: "fa-eye", scores: { tease: 1 } },
                { value: "nsfw_soft", label: "NSFW soft", desc: "Più spinta, ma non estrema.", icon: "fa-temperature-half", scores: { tease: 1, dommy: 1 } },
                { value: "explicit", label: "Explicit adult-only", desc: "Modalità 18+ piena.", icon: "fa-triangle-exclamation", scores: { dommy: 1, yandere: 1 } },
            ],
        },
    ];

    const profiles = {
        sweet: {
            title: "Sweet Waifu",
            desc: "Ti piace una ragazza dolce, calma e presente. Poco casino, tanta comfort zone.",
        },
        tsundere: {
            title: "Tsundere Bully",
            desc: "Ti attira quella che fa la cattiva, ma poi si tradisce al primo blush.",
        },
        yandere: {
            title: "Obsessive Yandere",
            desc: "Ti piacciono le red flag con gli occhi grandi. Scelta rischiosa.",
        },
        dommy: {
            title: "Goth Dommy Mommy",
            desc: "Ti piace una ragazza sicura, dominante e con abbastanza aura da farti stare zitto.",
        },
        goth: {
            title: "Dark Gothic Queen",
            desc: "Nero, mistero e sguardo freddo. La tua rovina, ma con estetica.",
        },
        gyaru: {
            title: "Gyaru Teaser",
            desc: "Vuoi energia forte, teasing continuo e zero paura di esagerare.",
        },
        maid: {
            title: "Shy Maid",
            desc: "Classica, cute e un po’ servizievole. Una scelta pulita, ma sempre efficace.",
        },
        fantasy: {
            title: "Demon Fantasy Girl",
            desc: "Vuoi qualcosa di meno normale. Corna, magia, problemi e tanta presenza.",
        },
        elegant: {
            title: "Elegant Mommy",
            desc: "Ti piace una ragazza composta, curata e superiore senza nemmeno provarci.",
        },
        chaotic: {
            title: "Chaotic Gremlin Girl",
            desc: "La pace non ti interessa. Vuoi una che trasformi ogni giorno in un evento strano.",
        },
        cold: {
            title: "Cold Black-Haired Queen",
            desc: "Fredda, distante, difficile da leggere. Ti ignora e funziona comunque.",
        },
        cute: {
            title: "Cute Soft Girl",
            desc: "Ti piace la vibe tenera, leggera e piena di piccoli segnali affettuosi.",
        },
        tease: {
            title: "Flirty Teaser",
            desc: "Ti piace essere provocato. Non troppo caos, ma abbastanza da perdere lucidità.",
        },
        dominant: {
            title: "Confident Boss Girl",
            desc: "Vuoi una ragazza decisa, diretta e con più controllo di te.",
        },
    };

    let currentIndex = 0;
    let answers = {};
    let lastProfile = null;
    let isLoading = false;

    function showToast(message) {
        const toast = $("#goonlandToast");
        if (!toast) return;

        const text = toast.querySelector("span");
        if (text) text.textContent = message;

        toast.hidden = false;
        requestAnimationFrame(() => toast.classList.add("is-visible"));
        clearTimeout(window.__goonQuizToastTimer);
        window.__goonQuizToastTimer = setTimeout(() => {
            toast.classList.remove("is-visible");
            setTimeout(() => (toast.hidden = true), 180);
        }, 2200);
    }

    function setStatus(text) {
        const status = $("#glQuizStatus");
        if (status) status.textContent = text;
    }

    function renderQuestion() {
        const question = questions[currentIndex];
        const step = $("#glQuizStep");
        const progress = $("#glQuizProgress");
        const title = $("#glQuizQuestion");
        const hint = $("#glQuizHint");
        const grid = $("#glAnswerGrid");
        const backBtn = $("#glQuizBack");
        const nextBtn = $("#glQuizNext");

        if (!question || !grid || !title || !hint) return;

        if (step) step.textContent = `Domanda ${currentIndex + 1} / ${questions.length}`;
        if (progress) progress.style.width = `${((currentIndex + 1) / questions.length) * 100}%`;

        title.textContent = question.title;
        hint.textContent = question.hint;
        grid.innerHTML = "";

        question.answers.forEach((answer) => {
            const button = document.createElement("button");
            button.type = "button";
            button.className = "gl-answer-card";
            button.dataset.value = answer.value;

            if (answers[question.id] === answer.value) {
                button.classList.add("is-selected");
            }

            button.innerHTML = `
                <span class="gl-answer-icon"><i class="fas ${answer.icon}"></i></span>
                <span>
                    <strong>${answer.label}</strong>
                    <span>${answer.desc}</span>
                </span>
            `;

            button.addEventListener("click", () => {
                answers[question.id] = answer.value;
                renderQuestion();

                setTimeout(() => {
                    if (currentIndex < questions.length - 1) {
                        currentIndex++;
                        renderQuestion();
                    } else {
                        finishQuiz();
                    }
                }, 180);
            });

            grid.appendChild(button);
        });

        if (backBtn) backBtn.disabled = currentIndex === 0;
        if (nextBtn) {
            nextBtn.innerHTML = currentIndex === questions.length - 1
                ? '<i class="fas fa-star"></i> Vedi risultato'
                : '<i class="fas fa-arrow-right"></i> Avanti';
        }
    }

    function getAnswerObject(questionId, value) {
        const question = questions.find((item) => item.id === questionId);
        return question?.answers.find((answer) => answer.value === value) || null;
    }

    function getProfile() {
        const scores = {};

        Object.entries(answers).forEach(([questionId, value]) => {
            const answer = getAnswerObject(questionId, value);
            if (!answer?.scores) return;

            Object.entries(answer.scores).forEach(([key, amount]) => {
                scores[key] = (scores[key] || 0) + amount;
            });
        });

        const sorted = Object.entries(scores).sort((a, b) => b[1] - a[1]);
        const topKey = sorted[0]?.[0] || "sweet";
        const total = Object.values(scores).reduce((sum, value) => sum + value, 0) || 1;
        const percent = Math.min(99, Math.max(68, Math.round(((sorted[0]?.[1] || 1) / total) * 100 + 55)));

        return {
            key: topKey,
            match: percent,
            ...(profiles[topKey] || profiles.sweet),
        };
    }

    function buildShareUrl(profile) {
        const url = new URL(window.location.href);
        url.search = "";
        url.hash = "";
        url.searchParams.set("result", profile?.key || "sweet");
        if (profile?.match) {
            url.searchParams.set("match", String(profile.match));
        }
        return url.toString();
    }

    function getShareText(profile) {
        return `Il mio tipo anime ideale su GoonLand è: ${profile?.title || "misterioso"}`;
    }

    function updateShareBox(profile) {
        const box = $("#glShareBox");
        const text = $("#glShareText");
        const urlText = $("#glShareUrlText");
        const urlButton = $("#glShareUrlButton");

        if (!box || !profile) return;

        const shareText = getShareText(profile);
        const shareUrl = buildShareUrl(profile);

        box.hidden = false;
        if (text) text.textContent = shareText;
        if (urlText) urlText.textContent = shareUrl;

        if (urlButton && !urlButton.dataset.boundCopy) {
            urlButton.dataset.boundCopy = "1";
            urlButton.addEventListener("click", async () => {
                const currentProfile = lastProfile || profile;
                const finalText = `${getShareText(currentProfile)}\n${buildShareUrl(currentProfile)}`;

                try {
                    await navigator.clipboard.writeText(finalText);
                    showToast("Link copiato");
                } catch {
                    showToast("Copia non riuscita");
                }
            });
        }
    }

    async function fetchResult(profile) {
        if (isLoading) return;
        isLoading = true;

        const result = $("#glQuizResult");
        const placeholder = $("#glResultPlaceholder");
        const image = $("#glResultImage");
        const tags = $("#glResultTags");
        const meta = $("#glResultMeta");
        const match = $("#glResultMatch");

        if (result) result.hidden = false;
        if (placeholder) placeholder.style.display = "grid";
        if (image) {
            image.hidden = true;
            image.classList.remove("is-visible");
            image.removeAttribute("src");
        }
        if (tags) tags.innerHTML = "";
        if (meta) meta.innerHTML = "";
        if (match) match.textContent = `Match ${profile.match}%`;

        setStatus("Caricamento");

        try {
            const response = await fetch(`${window.location.pathname}?quiz_api=danbooru_result`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ answers }),
            });

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.message || "Errore nel risultato");
            }

            await loadImage(data.image);
            renderResultData(data);
            setStatus("Completato");
            showToast("Risultato trovato");
        } catch (error) {
            console.error("[GoonLand Quiz]", error);
            if (placeholder) {
                placeholder.style.display = "grid";
                placeholder.innerHTML = `
                    <i class="fas fa-triangle-exclamation"></i>
                    <strong>Nessun risultato</strong>
                    <span>${error.message || "Riprova con altre risposte."}</span>
                `;
            }
            setStatus("Errore");
            showToast("Non ho trovato risultati. Riprova.");
        } finally {
            isLoading = false;
        }
    }

    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const placeholder = $("#glResultPlaceholder");
            const image = $("#glResultImage");

            if (!image || !src) {
                reject(new Error("Immagine mancante"));
                return;
            }

            const preload = new Image();
            preload.onload = () => {
                image.src = src;
                image.hidden = false;
                if (placeholder) placeholder.style.display = "none";
                requestAnimationFrame(() => image.classList.add("is-visible"));
                resolve();
            };
            preload.onerror = () => reject(new Error("Immagine non caricata"));
            preload.src = src;
        });
    }

    function renderResultData(data) {
        updateShareBox(lastProfile);

        const title = $("#glResultTitle");
        const desc = $("#glResultDescription");
        const tags = $("#glResultTags");
        const meta = $("#glResultMeta");

        if (title) title.textContent = lastProfile?.title || "Il tuo tipo ideale";
        if (desc) desc.textContent = lastProfile?.desc || "Risultato generato.";

        const tagList = [
            ...(data.matchedTags || []),
            ...(data.characterTags || []),
            ...(data.copyrightTags || []),
        ].filter(Boolean).slice(0, 10);

        if (tags) {
            tags.innerHTML = tagList.length
                ? tagList.map((tag) => `<span class="gl-chip">#${escapeHtml(tag)}</span>`).join("")
                : '<span class="gl-chip">#random_match</span>';
        }

        if (meta) {
            const artists = (data.artistTags || []).join(", ") || "non indicato";
            const postLink = data.postUrl ? `<a href="${escapeAttr(data.postUrl)}" target="_blank" rel="noopener noreferrer">Apri post su Danbooru</a>` : "non disponibile";
            const sourceLink = data.source ? `<a href="${escapeAttr(data.source)}" target="_blank" rel="noopener noreferrer">Fonte originale</a>` : "non disponibile";

            meta.innerHTML = `
                <div class="gl-meta-row"><strong>Artista</strong><span>${escapeHtml(artists)}</span></div>
                <div class="gl-meta-row"><strong>Post</strong><span>${postLink}</span></div>
                <div class="gl-meta-row"><strong>Fonte</strong><span>${sourceLink}</span></div>
            `;
        }
    }

    function finishQuiz() {
        const missing = questions.find((question) => !answers[question.id]);
        if (missing) {
            showToast("Rispondi a tutte le domande");
            return;
        }

        lastProfile = getProfile();
        const quizBox = $("#glQuizBox");
        if (quizBox) quizBox.scrollIntoView({ behavior: "smooth", block: "start" });
        fetchResult(lastProfile);

        setTimeout(() => {
            const result = $("#glQuizResult");
            if (result) result.scrollIntoView({ behavior: "smooth", block: "start" });
        }, 220);
    }

    function escapeHtml(value) {
        return String(value)
            .replaceAll("&", "&amp;")
            .replaceAll("<", "&lt;")
            .replaceAll(">", "&gt;")
            .replaceAll('"', "&quot;")
            .replaceAll("'", "&#039;");
    }

    function escapeAttr(value) {
        return escapeHtml(value).replaceAll("`", "&#096;");
    }

    function initAnimeQuiz() {
        if (document.body.dataset.goonlandPage !== "anime-quiz") return;

        const backBtn = $("#glQuizBack");
        const nextBtn = $("#glQuizNext");
        const restartBtn = $("#glQuizRestart");
        const rerollBtn = $("#glQuizReroll");
        const shareBtn = $("#glQuizShare");

        if (backBtn) {
            backBtn.addEventListener("click", () => {
                if (currentIndex <= 0) return;
                currentIndex--;
                renderQuestion();
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener("click", () => {
                const question = questions[currentIndex];
                if (!answers[question.id]) {
                    showToast("Scegli una risposta");
                    return;
                }

                if (currentIndex < questions.length - 1) {
                    currentIndex++;
                    renderQuestion();
                } else {
                    finishQuiz();
                }
            });
        }

        if (restartBtn) {
            restartBtn.addEventListener("click", () => {
                currentIndex = 0;
                answers = {};
                lastProfile = null;
                const result = $("#glQuizResult");
                const shareBox = $("#glShareBox");
                if (result) result.hidden = true;
                if (shareBox) shareBox.hidden = true;
                renderQuestion();
                const quiz = $("#glAnimeQuiz");
                if (quiz) quiz.scrollIntoView({ behavior: "smooth", block: "start" });
            });
        }

        if (rerollBtn) {
            rerollBtn.addEventListener("click", () => {
                if (!lastProfile) lastProfile = getProfile();
                fetchResult(lastProfile);
            });
        }

        if (shareBtn) {
            shareBtn.addEventListener("click", async () => {
                const profile = lastProfile || getProfile();
                const text = `${getShareText(profile)}
${buildShareUrl(profile)}`;

                try {
                    if (navigator.share) {
                        await navigator.share({
                            title: getShareText(profile),
                            text: getShareText(profile),
                            url: buildShareUrl(profile),
                        });
                        return;
                    }

                    await navigator.clipboard.writeText(text);
                    showToast("Link copiato");
                } catch {
                    try {
                        await navigator.clipboard.writeText(text);
                        showToast("Link copiato");
                    } catch {
                        showToast("Copia non riuscita");
                    }
                }
            });
        }

        document.querySelectorAll("[data-copy-current-url]").forEach((button) => {
            if (button.dataset.boundCopy) return;
            button.dataset.boundCopy = "1";
            button.addEventListener("click", async () => {
                try {
                    await navigator.clipboard.writeText(window.location.href);
                    showToast("Link copiato");
                } catch {
                    showToast("Copia non riuscita");
                }
            });
        });

        renderQuestion();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAnimeQuiz, { once: true });
    } else {
        initAnimeQuiz();
    }
})();
