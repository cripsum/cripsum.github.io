;(() => {
    "use strict";

    if (window.__goonlandAnimeQuizLoaded) return;
    window.__goonlandAnimeQuizLoaded = true;

    const lang = location.pathname.split("/").find(s => s === "it" || s === "en") || "it";

    const t = {
        it: {
            question_step:  (cur, tot) => `Domanda ${cur} / ${tot}`,
            btn_next:       '<i class="fas fa-arrow-right"></i> Avanti',
            btn_finish:     '<i class="fas fa-star"></i> Vedi risultato',
            toast_answer:   "Scegli una risposta",
            toast_all:      "Rispondi a tutte le domande",
            toast_copied:   "Link copiato",
            toast_copy_err: "Copia non riuscita",
            toast_found:    "Risultato trovato",
            toast_not_found:"Non ho trovato risultati. Riprova.",
            status_loading: "Caricamento",
            status_done:    "Completato",
            status_error:   "Errore",
            ideal_type:     "Il tuo tipo ideale",
            default_result: "Risultato generato.",
            err_result:     "Errore nel risultato",
            no_result:      "Nessun risultato",
            retry_answers:  "Riprova con altre risposte.",
            img_missing:    "Immagine mancante",
            img_failed:     "Immagine non caricata",
            share_text:     (title) => `Il mio tipo anime ideale su GoonLand è: ${title || "misterioso"}`,
            meta_artist:    "Artista",
            meta_post:      "Post",
            meta_source:    "Fonte",
            meta_unknown:   "non indicato",
            meta_post_link: (url) => `<a href="${url}" target="_blank" rel="noopener noreferrer">Apri post su Danbooru</a>`,
            meta_post_none: "non disponibile",
            meta_src_link:  (url) => `<a href="${url}" target="_blank" rel="noopener noreferrer">Fonte originale</a>`,
            meta_src_none:  "non disponibile",
        },
        en: {
            question_step:  (cur, tot) => `Question ${cur} / ${tot}`,
            btn_next:       '<i class="fas fa-arrow-right"></i> Next',
            btn_finish:     '<i class="fas fa-star"></i> See result',
            toast_answer:   "Choose an answer",
            toast_all:      "Answer all questions",
            toast_copied:   "Link copied",
            toast_copy_err: "Copy failed",
            toast_found:    "Result found",
            toast_not_found:"No results found. Try again.",
            status_loading: "Loading",
            status_done:    "Done",
            status_error:   "Error",
            ideal_type:     "Your ideal type",
            default_result: "Result generated.",
            err_result:     "Result error",
            no_result:      "No result",
            retry_answers:  "Try again with different answers.",
            img_missing:    "Image missing",
            img_failed:     "Image failed to load",
            share_text:     (title) => `My ideal anime type on GoonLand is: ${title || "mysterious"}`,
            meta_artist:    "Artist",
            meta_post:      "Post",
            meta_source:    "Source",
            meta_unknown:   "unknown",
            meta_post_link: (url) => `<a href="${url}" target="_blank" rel="noopener noreferrer">Open post on Danbooru</a>`,
            meta_post_none: "not available",
            meta_src_link:  (url) => `<a href="${url}" target="_blank" rel="noopener noreferrer">Original source</a>`,
            meta_src_none:  "not available",
        },
    }[lang];

    const questionsData = {
        it: [
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
                    { value: "dominated_soft", label: "Un po', ma non troppo", desc: "Ti piace il teasing, non il trauma.", icon: "fa-face-grin-wink", scores: { tease: 3, tsundere: 1 } },
                    { value: "control_me", label: "No, controllo io", desc: "Preferisci guidare tu il gioco.", icon: "fa-chess-king", scores: { sweet: 1, elegant: 1 } },
                    { value: "depends", label: "Dipende da lei", desc: "Se ha abbastanza aura, sì.", icon: "fa-dice", scores: { chaotic: 1, tease: 2 } },
                ],
            },
            {
                id: "personality",
                title: "Che personalità preferisci?",
                hint: "Qui scegli proprio l'archetipo anime.",
                answers: [
                    { value: "shy", label: "Timida e dolce", desc: "Parla piano e arrossisce.", icon: "fa-seedling", scores: { sweet: 3, cute: 2 } },
                    { value: "tsundere", label: "Tsundere", desc: "Fa la dura, ma si scioglie.", icon: "fa-bolt", scores: { tsundere: 4 } },
                    { value: "yandere", label: "Yandere", desc: "Troppo attaccata, forse troppo.", icon: "fa-eye", scores: { yandere: 4, chaotic: 2 } },
                    { value: "dommy", label: "Dommy mommy", desc: "Ti guarda dall'alto e tu capisci.", icon: "fa-crown", scores: { dommy: 4, dominant: 2 } },
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
                hint: "La dinamica conta quanto l'aspetto.",
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
                    { value: "goth", label: "Goth", desc: "Scura, elegante, un po' fredda.", icon: "fa-cross", scores: { goth: 4, cold: 1 } },
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
                    { value: "white_hair", label: "Bianchi / argento", desc: "Misteriosa e un po' divina.", icon: "fa-snowflake", scores: { elegant: 1, fantasy: 1 } },
                    { value: "colored_hair", label: "Colorati", desc: "Più caos, più personalità.", icon: "fa-palette", scores: { chaotic: 1, cute: 1 } },
                ],
            },
            {
                id: "outfit",
                title: "Che outfit ti attira di più?",
                hint: "Ultimo tocco visivo prima dell'intensità.",
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
        ],
        en: [
            {
                id: "type",
                title: "What type of girl attracts you most?",
                hint: "Let's start with the main vibe.",
                answers: [
                    { value: "sweet", label: "Sweet and caring", desc: "The one who treats you well.", icon: "fa-heart", scores: { sweet: 3, cute: 1 } },
                    { value: "cold", label: "Cold and mysterious", desc: "Few words, deadly gaze.", icon: "fa-snowflake", scores: { cold: 3, elegant: 1 } },
                    { value: "tsundere", label: "Prickly but warm", desc: "She insults you, then blushes.", icon: "fa-fire", scores: { tsundere: 3, chaotic: 1 } },
                    { value: "dominant", label: "Confident and dominant", desc: "She walks in and takes charge.", icon: "fa-crown", scores: { dommy: 3, dominant: 2 } },
                ],
            },
            {
                id: "control",
                title: "Do you like being dominated?",
                hint: "Key question: soft girl or someone who keeps you in line?",
                answers: [
                    { value: "dominated_yes", label: "Yes, a lot", desc: "She's in charge, period.", icon: "fa-hand", scores: { dommy: 3, dominant: 3 } },
                    { value: "dominated_soft", label: "A little, not too much", desc: "You enjoy teasing, not trauma.", icon: "fa-face-grin-wink", scores: { tease: 3, tsundere: 1 } },
                    { value: "control_me", label: "No, I'm in control", desc: "You prefer to lead the game.", icon: "fa-chess-king", scores: { sweet: 1, elegant: 1 } },
                    { value: "depends", label: "Depends on her", desc: "If she has enough aura, sure.", icon: "fa-dice", scores: { chaotic: 1, tease: 2 } },
                ],
            },
            {
                id: "personality",
                title: "What personality do you prefer?",
                hint: "Pick your anime archetype.",
                answers: [
                    { value: "shy", label: "Shy and sweet", desc: "Speaks softly and blushes.", icon: "fa-seedling", scores: { sweet: 3, cute: 2 } },
                    { value: "tsundere", label: "Tsundere", desc: "Acts tough but melts inside.", icon: "fa-bolt", scores: { tsundere: 4 } },
                    { value: "yandere", label: "Yandere", desc: "Way too attached — maybe.", icon: "fa-eye", scores: { yandere: 4, chaotic: 2 } },
                    { value: "dommy", label: "Dommy mommy", desc: "She looks down at you and you get it.", icon: "fa-crown", scores: { dommy: 4, dominant: 2 } },
                ],
            },
            {
                id: "vibe",
                title: "What vibe should she have?",
                hint: "More aesthetic than personality.",
                answers: [
                    { value: "cute", label: "Cute", desc: "Soft, colourful, adorable.", icon: "fa-star", scores: { cute: 3, sweet: 1 } },
                    { value: "dark", label: "Dark", desc: "Black, heavy eyes, weird energy.", icon: "fa-moon", scores: { goth: 2, cold: 2 } },
                    { value: "elegant", label: "Elegant", desc: "Class, presence, control.", icon: "fa-gem", scores: { elegant: 3, dommy: 1 } },
                    { value: "chaotic", label: "Chaotic", desc: "You never know what she'll do.", icon: "fa-wand-sparkles", scores: { chaotic: 3, yandere: 1 } },
                ],
            },
            {
                id: "relationship",
                title: "What kind of dynamic do you want?",
                hint: "Dynamic matters as much as looks.",
                answers: [
                    { value: "wholesome", label: "Romantic and wholesome", desc: "Zero stress, just comfort.", icon: "fa-heart-circle-check", scores: { sweet: 3, cute: 1 } },
                    { value: "tease", label: "She teases you", desc: "She provokes you because she likes it.", icon: "fa-face-laugh-squint", scores: { tease: 3, tsundere: 1 } },
                    { value: "command", label: "She commands you", desc: "You don't ask, you obey.", icon: "fa-gavel", scores: { dommy: 3, dominant: 2 } },
                    { value: "toxic", label: "Toxic but irresistible", desc: "Red flag, but with style.", icon: "fa-skull", scores: { yandere: 3, chaotic: 2 } },
                ],
            },
            {
                id: "style",
                title: "What style do you like most?",
                hint: "Pick your aesthetic package.",
                answers: [
                    { value: "maid", label: "Maid", desc: "Classic, clean, iconic.", icon: "fa-broom", scores: { maid: 4, sweet: 1 } },
                    { value: "goth", label: "Goth", desc: "Dark, elegant, slightly cold.", icon: "fa-cross", scores: { goth: 4, cold: 1 } },
                    { value: "gyaru", label: "Gyaru", desc: "Sunny, teasy, zero shame.", icon: "fa-sun", scores: { gyaru: 4, tease: 1 } },
                    { value: "fantasy", label: "Fantasy / demonic", desc: "Horns, magic, problems.", icon: "fa-dragon", scores: { fantasy: 4, chaotic: 1 } },
                ],
            },
            {
                id: "body",
                title: "What body type do you prefer?",
                hint: "Physical appearance, no beating around the bush.",
                answers: [
                    { value: "tall", label: "Tall", desc: "Strong presence, boss aura.", icon: "fa-up-long", scores: { dommy: 1, elegant: 1 } },
                    { value: "short_adult", label: "Short/petite", desc: "Small but not a kid.", icon: "fa-down-long", scores: { cute: 1, sweet: 1 } },
                    { value: "curvy", label: "Curvy", desc: "Visible curves, spicy energy.", icon: "fa-heart", scores: { dommy: 1, tease: 1 } },
                    { value: "athletic", label: "Athletic", desc: "Lean and decisive physique.", icon: "fa-dumbbell", scores: { elegant: 1, dominant: 1 } },
                ],
            },
            {
                id: "hair",
                title: "What hair do you prefer?",
                hint: "Simple choice, but it weighs a lot on the result.",
                answers: [
                    { value: "black_hair", label: "Black", desc: "Classic, cold, strong.", icon: "fa-circle", scores: { cold: 1, goth: 1 } },
                    { value: "blonde_hair", label: "Blonde", desc: "Gyaru or queen energy.", icon: "fa-sun", scores: { gyaru: 1, tease: 1 } },
                    { value: "white_hair", label: "White / silver", desc: "Mysterious and a bit divine.", icon: "fa-snowflake", scores: { elegant: 1, fantasy: 1 } },
                    { value: "colored_hair", label: "Coloured", desc: "More chaos, more personality.", icon: "fa-palette", scores: { chaotic: 1, cute: 1 } },
                ],
            },
            {
                id: "outfit",
                title: "What outfit attracts you most?",
                hint: "Last visual touch before intensity.",
                answers: [
                    { value: "uniform", label: "Uniform", desc: "Tidy, clean, controlled.", icon: "fa-user-tie", scores: { elegant: 1, cold: 1 } },
                    { value: "dress", label: "Elegant dress", desc: "Refined and composed.", icon: "fa-gem", scores: { elegant: 2 } },
                    { value: "dark_outfit", label: "Dark outfit", desc: "Black, gothic, heavy mood.", icon: "fa-moon", scores: { goth: 2 } },
                    { value: "provocative", label: "Provocative outfit", desc: "Less subtle, more direct.", icon: "fa-fire-flame-curved", scores: { tease: 2, dommy: 1 } },
                ],
            },
            {
                id: "intensity",
                title: "How explicit do you want it?",
                hint: "Pick the level. The site will use the closest Danbooru rating.",
                answers: [
                    { value: "safe", label: "Safe", desc: "Clean, no explicit content.", icon: "fa-shield-heart", scores: { sweet: 1 } },
                    { value: "suggestive", label: "Suggestive", desc: "Mild, but with a more adult vibe.", icon: "fa-eye", scores: { tease: 1 } },
                    { value: "nsfw_soft", label: "NSFW soft", desc: "More explicit, but not extreme.", icon: "fa-temperature-half", scores: { tease: 1, dommy: 1 } },
                    { value: "explicit", label: "Explicit adult-only", desc: "Full 18+ mode.", icon: "fa-triangle-exclamation", scores: { dommy: 1, yandere: 1 } },
                ],
            },
        ],
    };

    const profilesData = {
        it: {
            sweet:    { title: "Sweet Waifu", desc: "Ti piace una ragazza dolce, calma e presente. Poco casino, tanta comfort zone." },
            tsundere: { title: "Tsundere Bully", desc: "Ti attira quella che fa la cattiva, ma poi si tradisce al primo blush." },
            yandere:  { title: "Obsessive Yandere", desc: "Ti piacciono le red flag con gli occhi grandi. Scelta rischiosa." },
            dommy:    { title: "Goth Dommy Mommy", desc: "Ti piace una ragazza sicura, dominante e con abbastanza aura da farti stare zitto." },
            goth:     { title: "Dark Gothic Queen", desc: "Nero, mistero e sguardo freddo. La tua rovina, ma con estetica." },
            gyaru:    { title: "Gyaru Teaser", desc: "Vuoi energia forte, teasing continuo e zero paura di esagerare." },
            maid:     { title: "Shy Maid", desc: "Classica, cute e un po' servizievole. Una scelta pulita, ma sempre efficace." },
            fantasy:  { title: "Demon Fantasy Girl", desc: "Vuoi qualcosa di meno normale. Corna, magia, problemi e tanta presenza." },
            elegant:  { title: "Elegant Mommy", desc: "Ti piace una ragazza composta, curata e superiore senza nemmeno provarci." },
            chaotic:  { title: "Chaotic Gremlin Girl", desc: "La pace non ti interessa. Vuoi una che trasformi ogni giorno in un evento strano." },
            cold:     { title: "Cold Black-Haired Queen", desc: "Fredda, distante, difficile da leggere. Ti ignora e funziona comunque." },
            cute:     { title: "Cute Soft Girl", desc: "Ti piace la vibe tenera, leggera e piena di piccoli segnali affettuosi." },
            tease:    { title: "Flirty Teaser", desc: "Ti piace essere provocato. Non troppo caos, ma abbastanza da perdere lucidità." },
            dominant: { title: "Confident Boss Girl", desc: "Vuoi una ragazza decisa, diretta e con più controllo di te." },
        },
        en: {
            sweet:    { title: "Sweet Waifu", desc: "You like a sweet, calm and present girl. Low drama, high comfort zone." },
            tsundere: { title: "Tsundere Bully", desc: "You're drawn to the one who plays tough but gives herself away at the first blush." },
            yandere:  { title: "Obsessive Yandere", desc: "You like red flags with big eyes. Risky choice." },
            dommy:    { title: "Goth Dommy Mommy", desc: "You like a confident, dominant girl with enough aura to make you stay quiet." },
            goth:     { title: "Dark Gothic Queen", desc: "Black, mystery and a cold gaze. Your downfall, but with aesthetic." },
            gyaru:    { title: "Gyaru Teaser", desc: "You want strong energy, constant teasing and zero fear of going too far." },
            maid:     { title: "Shy Maid", desc: "Classic, cute and a little doting. A clean choice, but always effective." },
            fantasy:  { title: "Demon Fantasy Girl", desc: "You want something less ordinary. Horns, magic, problems and lots of presence." },
            elegant:  { title: "Elegant Mommy", desc: "You like a composed, polished girl who's superior without even trying." },
            chaotic:  { title: "Chaotic Gremlin Girl", desc: "Peace doesn't interest you. You want one who turns every day into a weird event." },
            cold:     { title: "Cold Black-Haired Queen", desc: "Cold, distant, hard to read. She ignores you and it works anyway." },
            cute:     { title: "Cute Soft Girl", desc: "You like the tender, light vibe full of small affectionate signals." },
            tease:    { title: "Flirty Teaser", desc: "You like being provoked. Not too much chaos, but enough to lose your mind." },
            dominant: { title: "Confident Boss Girl", desc: "You want a decisive, direct girl with more control than you." },
        },
    };

    const questions = questionsData[lang];
    const profiles  = profilesData[lang];
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
        const progress = $("#glQuizProgress")[0];
        const title = $("#glQuizQuestion");
        const hint = $("#glQuizHint");
        const grid = $("#glAnswerGrid");
        const backBtn = $("#glQuizBack");
        const nextBtn = $("#glQuizNext");

        if (!question || !grid || !title || !hint) return;

        if (step) step.textContent = t.question_step(currentIndex + 1, questions.length);
        if (progress) {
            progress.style.width = `${((currentIndex + 1) / questions.length) * 100}%`;
        }

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
                ? t.btn_finish
                : t.btn_next;
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
        return t.share_text(profile?.title);
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
                    showToast(t.toast_copied);
                } catch {
                    showToast(t.toast_copy_err);
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

        setStatus(t.status_loading);

        try {
            const response = await fetch(`${window.location.pathname}?quiz_api=danbooru_result`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ answers }),
            });

            const data = await response.json();

            if (!response.ok || !data.ok) {
                throw new Error(data.message || t.err_result);
            }

            await loadImage(data.image);
            renderResultData(data);
            setStatus(t.status_completed);
            showToast(t.toast_result_found);
        } catch (error) {
            console.error("[GoonLand Quiz]", error);
            if (placeholder) {
                placeholder.style.display = "grid";
                placeholder.innerHTML = `
                    <i class="fas fa-triangle-exclamation"></i>
                    <strong>${t.no_results}</strong>
                    <span>${error.message || t.retry_answers}</span>
                `;
            }
            setStatus(t.status_error);
            showToast(t.toast_no_results);
        } finally {
            isLoading = false;
        }
    }

    function loadImage(src) {
        return new Promise((resolve, reject) => {
            const placeholder = $("#glResultPlaceholder");
            const image = $("#glResultImage");

            if (!image || !src) {
                reject(new Error(t.img_missing));
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
            preload.onerror = () => reject(new Error(t.img_failed));
            preload.src = src;
        });
    }

    function renderResultData(data) {
        updateShareBox(lastProfile);

        const title = $("#glResultTitle");
        const desc = $("#glResultDescription");
        const tags = $("#glResultTags");
        const meta = $("#glResultMeta");

        if (title) title.textContent = lastProfile?.title || t.ideal_type;
        if (desc) desc.textContent = lastProfile?.desc || t.default_result;

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
            const artists = (data.artistTags || []).join(", ") || t.meta_unknown;
            const postLink = data.postUrl ? t.meta_post_link(escapeAttr(data.postUrl)) : t.meta_post_none;
            const sourceLink = data.source ? t.meta_src_link(escapeAttr(data.source)) : t.meta_src_none;

            meta.innerHTML = `
                <div class="gl-meta-row"><strong>${t.meta_artist}</strong><span>${escapeHtml(artists)}</span></div>
                <div class="gl-meta-row"><strong>${t.meta_post}</strong><span>${postLink}</span></div>
                <div class="gl-meta-row"><strong>${t.meta_source}</strong><span>${sourceLink}</span></div>
            `;
        }
    }

    function finishQuiz() {
        const missing = questions.find((question) => !answers[question.id]);
        if (missing) {
            showToast(t.toast_all);
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

        const backBtn = $("#glQuizBack")[0];
        const nextBtn = $("#glQuizNext")[0];
        const restartBtn = $("#glQuizRestart")[0];
        const rerollBtn = $("#glQuizReroll")[0];
        const shareBtn = $("#glQuizShare")[0];

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
                    showToast(t.toast_answer);
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
                    showToast(t.toast_copied);
                } catch {
                    try {
                        await navigator.clipboard.writeText(text);
                        showToast(t.toast_copied);
                    } catch {
                        showToast(t.toast_copy_err);
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
                    showToast(t.toast_copied);
                } catch {
                    showToast(t.toast_copy_err);
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