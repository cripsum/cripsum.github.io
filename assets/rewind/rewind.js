/**
 * Cripsum™ — Rewind
 *
 * Motore delle schermate in formato storia e disegno dei contenuti.
 *
 * Il payload arriva già calcolato da /api/rewind/get.php: qui non si fanno
 * conti, si decide solo come mostrarli. Ogni schermata ha una sua palette,
 * i numeri salgono da zero e gli elementi entrano scaglionati.
 *
 * Tutto ciò che proviene dal database passa per esc(): nomi di personaggi,
 * titoli di post e nomi utente sono scritti dalle persone e non devono mai
 * finire nel DOM come markup.
 */
(() => {
    'use strict';

    const lang = (window.CRIPSUM_LANG === 'en') ? 'en' : 'it';
    const isPublic = !!window.CRIPSUM_REWIND_PUBLIC;
    const preloaded = window.CRIPSUM_REWIND_DATA || null;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const SLIDE_MS = 7000;

    /**
     * Schermate che hanno bisogno di piu' tempo del solito.
     * La sequenza dei personaggi e' costruita a tappe: con sette secondi
     * l'ultimo non farebbe in tempo a comparire.
     */
    const SLIDE_DURATIONS = { cast: 14000, best_pull: 9000, persona: 10000 };

    // ─────────────────────────────────────────────────────────
    //  TESTI
    // ─────────────────────────────────────────────────────────

    const T = {
        it: {
            loading: 'Sto ripercorrendo la tua storia...',
            error: 'Non riesco a caricare il tuo Rewind.',
            retry: 'Riprova',
            unavailable: 'Il Rewind non è ancora attivo su questo account.',
            hint: 'Tocca per continuare',
            hintKeys: 'Usa le frecce o clicca per continuare',

            introKicker: 'Cripsum Rewind™',
            introTitle: 'Tutto quello<br>che hai fatto',
            introLead: n => `Sei con noi da <strong>${n}</strong> giorni. Vediamo come li hai spesi.`,
            introSince: y => `Dal ${y} a oggi.`,
            introStart: 'Comincia',
            introHint: 'Premi per iniziare',

            timeKicker: 'Tempo passato qui',
            timeUnitH: 'ore',
            timeUnitM: 'minuti',
            timeLead: (h, d) => `Hai passato <strong>${h}</strong> su Cripsum, distribuite su <strong>${d}</strong> giorni.`,
            timeEstimate: 'Una parte viene dal vecchio contatore del browser, quindi è una stima generosa.',
            timeStreak: 'Streak record',
            timeSessions: 'Sessioni',
            timeLongest: 'Più lunga',
            timeDays: 'Giorni attivi',

            hoursKicker: 'Il tuo orario',
            hoursTitle: h => `Le tue ore migliori sono<br>attorno alle ${h}`,
            chrono: {
                nottambulo: 'Sei un <strong>nottambulo</strong>.',
                mattiniero: 'Sei un <strong>mattiniero</strong>.',
                diurno: 'Sei un tipo <strong>diurno</strong>.',
                serale: 'Sei un tipo <strong>serale</strong>.',
                equilibrato: 'Il tuo orario è <strong>equilibrato</strong>.'
            },
            hoursShare: (p, label) => `Il <strong>${p}%</strong> del tuo tempo se ne va ${label}.`,
            bandNight: 'tra mezzanotte e le 6', bandMorning: 'tra le 6 e mezzogiorno',
            bandDay: 'tra mezzogiorno e le 18', bandEvening: 'tra le 18 e mezzanotte',

            calKicker: 'Il tuo calendario',
            calTitle: n => `${n} giorni<br>con noi`,
            calLead: 'Ogni quadratino è un giorno. Più è chiaro, più ci sei stato.',

            pagesKicker: 'Dove sei stato',
            pagesTitle: 'Le tue zone preferite',
            pagesLead: name => `Il tuo posto è <strong>${name}</strong>.`,
            pageNames: {
                home: 'Home', profilo: 'Profili', rewind: 'Rewind', chat: 'Chat',
                'global-chat': 'Chat globale', inbox: 'Inbox', amici: 'Amici',
                lootbox: 'Lootbox', gacha: 'Shop gacha', negozio: 'Negozio',
                inventario: 'Inventario', achievements: 'Achievement', missions: 'Missioni',
                subway: 'Subway', pullspot: 'Pullspot', animespot: 'Animespot', game: 'Duelli', gambling: 'Gambling', goonland: 'GoonLand',
                shitpost: 'Shitpost', rimasti: 'Top Rimasti', cripsumpedia: 'CripsumPedia',
                edits: 'Edits', download: 'Download', tiktokpedia: 'TikTokPedia',
                merch: 'Merch', donazioni: 'Donazioni', impostazioni: 'Impostazioni', altro: 'Altro'
            },

            gachaKicker: 'Lootbox e gacha',
            gachaTitle: 'pull',
            gachaLead: n => `Hai aperto <strong>${n}</strong> lootbox in tutto.`,
            gachaNew: 'Nuovi', gachaPity: 'Pity max', gacha5050: '50/50 vinti', gachaSpent: 'Godos spesi',

            bestKicker: 'Il colpo grosso',
            bestLead: (name, pity) => `<strong>${name}</strong> arrivato con solo <strong>${pity}</strong> di pity.`,
            bestLeadNoPity: name => `<strong>${name}</strong>, il pezzo più raro che hai tirato.`,

            collKicker: 'La tua collezione',
            collTitle: 'personaggi',
            collLead: (owned, total, pct) => `Ne hai <strong>${owned}</strong> su <strong>${total}</strong>. Sei al <strong>${pct}%</strong>.`,
            collRarest: 'Il più raro', collDupes: n => `Ne hai ${n} copie`,

            chrKicker: 'Chi hai visto di più',
            chrTitle: 'Il tuo compagno fisso',
            chrMost: 'Personaggio più trovato', chrLeast: 'Personaggio meno trovato',
            chrTimes: n => n === 1 ? '1 volta' : `${n} volte`,
            chrLead: (name, n) => `<strong>${name}</strong> è uscito <strong>${n}</strong> volte. Ormai siete parenti.`,

            collProgress: p => p >= 100
                ? 'Li hai tutti. Non manca più niente.'
                : (p >= 75 ? 'Ci sei quasi.' : (p >= 40 ? 'Sei a metà strada.' : 'C\'è ancora parecchio da trovare.')),
            rarestKicker: 'Il pezzo pregiato',
            rarestLead: 'Il più raro che sia mai finito nella tua collezione.',
            rarestWhen: d => `Arrivato il ${d}.`,
            rarestPity: p => `Ed è uscito con solo <strong>${p}</strong> di pity.`,
            postRimasto: 'Top Rimasto più votato',
            postVotesN: n => `${n} voti`,
            conShitposts: 'Shitpost', conRimasti: 'Top Rimasti',
            conViews: 'Visualizzazioni', conVotesIn: 'Voti ricevuti',
            castKicker: 'In scena',
            castTitle: 'I due estremi della tua collezione',
            castMost: 'Personaggio più trovato',
            castLeast: 'Personaggio meno trovato',
            castRarest: 'Il gioiello',

            cardTheme: 'Tema della card',

            postKicker: 'I tuoi primati',
            postTitle: 'Il meglio che hai pubblicato',
            postLikes: 'Più apprezzato', postViews: 'Più visto', postComments: 'Più commentato',
            postLikesN: n => `${n} like`, postViewsN: n => `${n} visualizzazioni`, postCommentsN: n => `${n} commenti`,
            postViewsTotal: n => `In tutto i tuoi post hanno raccolto <strong>${n}</strong> visualizzazioni.`,

            cardKicker: 'La tua card',
            cardTitle: 'Portala con te',
            cardLead: 'Salvala e mettila dove vuoi.',
            cardDownload: 'Scarica immagine',
            cardSaved: 'Immagine scaricata!',
            cardBusy: 'Preparo l\'immagine...',
            cardMember: 'Membro dal',

            audioToggle: 'Attiva o disattiva la musica',
            audioVolume: 'Volume',
            playPause: 'Metti in pausa lo scorrimento',
            playResume: 'Riprendi lo scorrimento',

            achKicker: 'Achievement',
            achTitle: 'sbloccati',
            achLead: p => `E <strong>${p}</strong> punti guadagnati.`,
            achRarestLabel: 'Il tuo più raro',
            achRarest: pct => `Ce l'ha solo il <strong>${pct}%</strong> degli utenti.`,

            misKicker: 'Missioni',
            misTitle: 'completate',
            misLead: p => `Ne hai riscattate il <strong>${p}%</strong>.`,
            misForgot: 'Qualche ricompensa te la sei dimenticata.',

            socKicker: 'Social',
            socTitle: 'messaggi',
            socLead: 'Ecco quanto hai parlato.',
            socGlobal: 'Chat globale', socPrivate: 'Privati', socGroup: 'Gruppi', socFriends: 'Amici',
            socPartner: name => `Con <strong>${name}</strong> più che con chiunque altro.`,
            socBusiest: (d, n) => `Il ${d} ne hai mandati ${n} in un giorno solo.`,

            proKicker: 'Il tuo profilo',
            proTitle: 'visite',
            proLead: n => `E l'hai ritoccato <strong>${n}</strong> volte.`,
            proNeverHappy: 'Non sei mai contento del risultato, eh?',

            gamKicker: 'Giochi',
            gamTitle: 'duelli',
            gamWin: 'Vinti', gamLoss: 'Persi', gamRate: 'Winrate',
            subKicker: 'Subway Surfers',
            subTitle: 'il tuo record',
            subRank: 'In classifica', subRuns: 'Partite', subMap: 'Mappa',
            subPodium: r => r === 1
                ? 'Sei primo in classifica. Nessuno ti ha ancora preso.'
                : `Sei sul podio, ${r}° posto assoluto.`,

            conKicker: 'Contenuti',
            conTitle: 'like ricevuti',
            conLead: (p, c) => `Su <strong>${p}</strong> post e <strong>${c}</strong> commenti.`,
            conBest: (title, n) => `Il migliore: "${title}" con ${n} like.`,

            ecoKicker: 'Economia',
            ecoTitle: 'Godos spesi',
            ecoEarned: 'Guadagnati', ecoSpent: 'Spesi', ecoBalance: 'Saldo',

            busyKicker: 'Il giorno più intenso',
            busyLead: (d, m, a) => `Il <strong>${d}</strong>: ${m} minuti e ${a} azioni.`,

            perKicker: 'E quindi tu sei...',
            rankTop: (p, n) => `Sei nel <strong>top ${p}%</strong> degli utenti più attivi, su ${n}.`,

            quipTime: h => h >= 500 ? 'A questo punto ti conviene prendere la residenza.'
                : (h >= 200 ? 'Praticamente ci abiti.'
                : (h >= 50 ? 'Un rapporto sano, o quasi.' : 'Passi di rado, ma passi.')),

            quipGacha: (pulls, rate) => pulls >= 1000 ? 'Il banco ringrazia.'
                : (rate !== null && rate < 40 ? 'Il 50/50 ti odia, e i numeri lo confermano.'
                : (pulls >= 200 ? 'Una dipendenza gestita male ma con stile.' : 'Prudente. Per ora.')),

            quipGames: (played, rate) => played === 0 ? ''
                : (rate >= 70 ? 'Qualcuno dovrebbe controllarti il mazzo.'
                : (rate >= 45 ? 'Vinci quanto perdi. Equilibrio perfetto.'
                : 'La partecipazione è quello che conta, giusto?')),

            quipSubway: runs => runs >= 100 ? 'Il treno ormai lo guidi tu.'
                : 'Un altro giro e poi basta, dicevi.',

            psKicker: 'Pullspot',
            psTitle: 'tracce indovinate',
            psPlayed: 'Partite', psRate: '% vinte', psFirst: 'Al primo colpo',
            psBest: n => n === 1
                ? 'Il colpo migliore: presa al primo tentativo.'
                : `Il colpo migliore: presa al ${n}° tentativo.`,
            psBestWho: who => `Era ${who}.`,

            asKicker: 'Animespot',
            asTitle: 'sigle indovinate',
            asPlayed: 'Partite', asRate: '% vinte', asFirst: 'Al primo colpo', asPoints: 'Punti',
            asBest: (n, lvl) => (n === 1 ? 'Il colpo migliore: presa al primo tentativo' : `Il colpo migliore: presa al ${n}° tentativo`)
                + (lvl ? ` in ${['', 'facile', 'media', 'difficile', 'esperto', 'impossibile'][lvl] || ''}.` : '.'),
            asBestWho: who => `Era ${who}.`,
            quipAnimespot: (won, first) => first > 0
                ? 'Un decimo di secondo di opening e sapevi già di che anime era.'
                : (won >= 20 ? 'Ti bastano due note per sapere che stagione stavi guardando.'
                    : 'Ancora qualche sigla e le riconoscerai dal primo accordo.'),
            quipPullspot: (won, first) => first > 0
                ? 'Riconosci un personaggio da un decimo di secondo. Un po\' inquietante.'
                : (won >= 20 ? 'Orecchio fine: le musiche di pull non hanno più segreti.'
                    : 'Ancora qualche giro e le riconoscerai dal primo respiro.'),

            quipContent: likes => likes >= 500 ? 'Il pubblico ti ama, e si vede.'
                : (likes >= 50 ? 'Non male per uno che dice di postare a caso.'
                : 'Nicchia. Molto nicchia.'),

            quipEconomy: net => net > 0 ? 'Guadagni più di quanto spendi. Sospetto.'
                : (net < -5000 ? 'I Godos ti passano fra le dita.'
                : 'Entrate e uscite più o meno in pari.'),

            quipMissions: rate => rate >= 95 ? 'Non ne lasci indietro una.'
                : (rate >= 60 ? 'Qualcuna ti scappa, ma tieni il ritmo.'
                : 'Le inizi tutte, le finisci quasi mai.'),

            quipSocial: total => total >= 5000 ? 'Il tuo pollice merita un riposo.'
                : (total >= 500 ? 'Ti fai sentire.' : 'Uno che ascolta più di quanto parla.'),

            quipCollection: pct => pct >= 100 ? 'Non ti resta più niente da desiderare.'
                : (pct >= 60 ? 'Manca poco, e lo sai.' : 'C\'è ancora molto là fuori.'),

            quipCalendar: days => days >= 300 ? 'Non salti quasi mai.'
                : (days >= 100 ? 'Una presenza costante.' : 'Vai e vieni, come le stagioni.'),
            sumKicker: 'La tua storia in breve',
            sumTitle: 'Ecco tutto',
            share: 'Condividi',
            shareOn: 'Link attivo',
            replay: 'Rivedi',
            copied: 'Link copiato!',
            shareOff: 'Link disattivato',
            shareErr: 'Non è riuscito, riprova.',
            close: 'Chiudi',
            back: 'Torna al sito'
        },
        en: {
            loading: 'Replaying your story...',
            error: 'I could not load your Rewind.',
            retry: 'Try again',
            unavailable: 'Rewind is not active on this account yet.',
            hint: 'Tap to continue',
            hintKeys: 'Use the arrows or click to continue',

            introKicker: 'Cripsum Rewind™',
            introTitle: 'Everything<br>you have done',
            introLead: n => `You have been with us for <strong>${n}</strong> days. Let us see how you spent them.`,
            introSince: y => `From ${y} until today.`,
            introStart: 'Start',
            introHint: 'Press to begin',

            timeKicker: 'Time spent here',
            timeUnitH: 'hours',
            timeUnitM: 'minutes',
            timeLead: (h, d) => `You spent <strong>${h}</strong> on Cripsum, across <strong>${d}</strong> days.`,
            timeEstimate: 'Part of this comes from the old browser counter, so it is a generous estimate.',
            timeStreak: 'Best streak',
            timeSessions: 'Sessions',
            timeLongest: 'Longest',
            timeDays: 'Active days',

            hoursKicker: 'Your schedule',
            hoursTitle: h => `Your best hours are<br>around ${h}`,
            chrono: {
                nottambulo: 'You are a <strong>night owl</strong>.',
                mattiniero: 'You are an <strong>early bird</strong>.',
                diurno: 'You are a <strong>daytime</strong> type.',
                serale: 'You are an <strong>evening</strong> type.',
                equilibrato: 'Your schedule is <strong>balanced</strong>.'
            },
            hoursShare: (p, label) => `<strong>${p}%</strong> of your time goes ${label}.`,
            bandNight: 'between midnight and 6am', bandMorning: 'between 6am and noon',
            bandDay: 'between noon and 6pm', bandEvening: 'between 6pm and midnight',

            calKicker: 'Your calendar',
            calTitle: n => `${n} days<br>with us`,
            calLead: 'Every square is a day. The brighter it is, the more you were around.',

            pagesKicker: 'Where you went',
            pagesTitle: 'Your favourite corners',
            pagesLead: name => `Your spot is <strong>${name}</strong>.`,
            pageNames: {
                home: 'Home', profilo: 'Profiles', rewind: 'Rewind', chat: 'Chat',
                'global-chat': 'Global chat', inbox: 'Inbox', amici: 'Friends',
                lootbox: 'Lootbox', gacha: 'Gacha shop', negozio: 'Store',
                inventario: 'Inventory', achievements: 'Achievements', missions: 'Missions',
                subway: 'Subway', pullspot: 'Pullspot', animespot: 'Animespot', game: 'Duels', gambling: 'Gambling', goonland: 'GoonLand',
                shitpost: 'Shitpost', rimasti: 'Top Rimasti', cripsumpedia: 'CripsumPedia',
                edits: 'Edits', download: 'Downloads', tiktokpedia: 'TikTokPedia',
                merch: 'Merch', donazioni: 'Donations', impostazioni: 'Settings', altro: 'Other'
            },

            gachaKicker: 'Lootboxes and gacha',
            gachaTitle: 'pulls',
            gachaLead: n => `You opened <strong>${n}</strong> lootboxes in total.`,
            gachaNew: 'New', gachaPity: 'Max pity', gacha5050: '50/50 won', gachaSpent: 'Godos spent',

            bestKicker: 'The big one',
            bestLead: (name, pity) => `<strong>${name}</strong> landed at only <strong>${pity}</strong> pity.`,
            bestLeadNoPity: name => `<strong>${name}</strong>, the rarest thing you pulled.`,

            collKicker: 'Your collection',
            collTitle: 'characters',
            collLead: (owned, total, pct) => `You own <strong>${owned}</strong> of <strong>${total}</strong>. That is <strong>${pct}%</strong>.`,
            collRarest: 'The rarest', collDupes: n => `You have ${n} copies`,

            chrKicker: 'Who you saw the most',
            chrTitle: 'Your constant companion',
            chrMost: 'Most pulled character', chrLeast: 'Least pulled character',
            chrTimes: n => n === 1 ? 'once' : `${n} times`,
            chrLead: (name, n) => `<strong>${name}</strong> showed up <strong>${n}</strong> times. You are practically related.`,

            collProgress: p => p >= 100
                ? 'You have them all. Nothing left to find.'
                : (p >= 75 ? 'Almost there.' : (p >= 40 ? 'You are halfway.' : 'Still plenty out there.')),
            rarestKicker: 'The prize piece',
            rarestLead: 'The rarest thing that ever landed in your collection.',
            rarestWhen: d => `Landed on ${d}.`,
            rarestPity: p => `And it dropped at only <strong>${p}</strong> pity.`,
            postRimasto: 'Most voted Top Rimasto',
            postVotesN: n => `${n} votes`,
            conShitposts: 'Shitposts', conRimasti: 'Top Rimasti',
            conViews: 'Views', conVotesIn: 'Votes received',
            castKicker: 'On stage',
            castTitle: 'The two extremes of your collection',
            castMost: 'Most pulled character',
            castLeast: 'Least pulled character',
            castRarest: 'The gem',

            cardTheme: 'Card theme',

            postKicker: 'Your records',
            postTitle: 'The best you posted',
            postLikes: 'Most liked', postViews: 'Most viewed', postComments: 'Most commented',
            postLikesN: n => `${n} likes`, postViewsN: n => `${n} views`, postCommentsN: n => `${n} comments`,
            postViewsTotal: n => `Your posts collected <strong>${n}</strong> views in total.`,

            cardKicker: 'Your card',
            cardTitle: 'Take it with you',
            cardLead: 'Save it and put it wherever you like.',
            cardDownload: 'Download image',
            cardSaved: 'Image downloaded!',
            cardBusy: 'Preparing the image...',
            cardMember: 'Member since',

            audioToggle: 'Turn the music on or off',
            audioVolume: 'Volume',
            playPause: 'Pause the story',
            playResume: 'Resume the story',

            achKicker: 'Achievements',
            achTitle: 'unlocked',
            achLead: p => `And <strong>${p}</strong> points earned.`,
            achRarestLabel: 'Your rarest',
            achRarest: pct => `Only <strong>${pct}%</strong> of users have it.`,

            misKicker: 'Missions',
            misTitle: 'completed',
            misLead: p => `You claimed <strong>${p}%</strong> of them.`,
            misForgot: 'You forgot a few rewards along the way.',

            socKicker: 'Social',
            socTitle: 'messages',
            socLead: 'Here is how much you talked.',
            socGlobal: 'Global chat', socPrivate: 'Private', socGroup: 'Groups', socFriends: 'Friends',
            socPartner: name => `With <strong>${name}</strong> more than anyone else.`,
            socBusiest: (d, n) => `On ${d} you sent ${n} in a single day.`,

            proKicker: 'Your profile',
            proTitle: 'views',
            proLead: n => `And you tweaked it <strong>${n}</strong> times.`,
            proNeverHappy: 'Never quite happy with it, are you?',

            gamKicker: 'Games',
            gamTitle: 'duels',
            gamWin: 'Won', gamLoss: 'Lost', gamRate: 'Winrate',
            subKicker: 'Subway Surfers',
            subTitle: 'your record',
            subRank: 'Leaderboard', subRuns: 'Runs', subMap: 'Map',
            subPodium: r => r === 1
                ? 'You are first on the leaderboard. Nobody has caught you yet.'
                : `You are on the podium, #${r} overall.`,

            conKicker: 'Content',
            conTitle: 'likes received',
            conLead: (p, c) => `Across <strong>${p}</strong> posts and <strong>${c}</strong> comments.`,
            conBest: (title, n) => `The best one: "${title}" with ${n} likes.`,

            ecoKicker: 'Economy',
            ecoTitle: 'Godos spent',
            ecoEarned: 'Earned', ecoSpent: 'Spent', ecoBalance: 'Balance',

            busyKicker: 'Your busiest day',
            busyLead: (d, m, a) => `On <strong>${d}</strong>: ${m} minutes and ${a} actions.`,

            perKicker: 'And so you are...',
            rankTop: (p, n) => `You are in the <strong>top ${p}%</strong> of the most active users, out of ${n}.`,

            quipTime: h => h >= 500 ? 'At this point you should register as a resident.'
                : (h >= 200 ? 'You basically live here.'
                : (h >= 50 ? 'A healthy relationship. Almost.' : 'You drop by rarely, but you drop by.')),

            quipGacha: (pulls, rate) => pulls >= 1000 ? 'The house thanks you.'
                : (rate !== null && rate < 40 ? 'The 50/50 hates you, and the numbers agree.'
                : (pulls >= 200 ? 'A badly managed habit, but a stylish one.' : 'Careful. For now.')),

            quipGames: (played, rate) => played === 0 ? ''
                : (rate >= 70 ? 'Somebody should check your deck.'
                : (rate >= 45 ? 'You win as much as you lose. Perfect balance.'
                : 'Taking part is what counts, right?')),

            quipSubway: runs => runs >= 100 ? 'You practically drive that train now.'
                : 'One more run and then you stop, you said.',

            psKicker: 'Pullspot',
            psTitle: 'tracks guessed',
            psPlayed: 'Rounds', psRate: 'Win %', psFirst: 'First try',
            psBest: n => n === 1
                ? 'Your best call: got it on the first try.'
                : `Your best call: got it on try ${n}.`,
            psBestWho: who => `It was ${who}.`,

            asKicker: 'Animespot',
            asTitle: 'themes guessed',
            asPlayed: 'Rounds', asRate: 'Win %', asFirst: 'First try', asPoints: 'Points',
            asBest: (n, lvl) => (n === 1 ? 'Best call: got it on the first try' : `Best call: got it on guess ${n}`)
                + (lvl ? ` on ${['', 'easy', 'medium', 'hard', 'expert', 'impossible'][lvl] || ''}.` : '.'),
            asBestWho: who => `It was ${who}.`,
            quipAnimespot: (won, first) => first > 0
                ? 'A tenth of a second of an opening and you already knew the show.'
                : (won >= 20 ? 'Two notes are enough for you to name the season.'
                    : 'A few more themes and you will know them from the first chord.'),
            quipPullspot: (won, first) => first > 0
                ? 'You can name a character from a tenth of a second. Slightly unsettling.'
                : (won >= 20 ? 'Sharp ear: pull tracks have no secrets left for you.'
                    : 'A few more rounds and you will know them from the first breath.'),

            quipContent: likes => likes >= 500 ? 'The crowd loves you, and it shows.'
                : (likes >= 50 ? 'Not bad for someone who claims to post at random.'
                : 'Niche. Very niche.'),

            quipEconomy: net => net > 0 ? 'You earn more than you spend. Suspicious.'
                : (net < -5000 ? 'Godos slip right through your fingers.'
                : 'Roughly break-even.'),

            quipMissions: rate => rate >= 95 ? 'You never leave one behind.'
                : (rate >= 60 ? 'A few slip away, but you keep the pace.'
                : 'You start them all, you finish almost none.'),

            quipSocial: total => total >= 5000 ? 'Your thumb deserves a break.'
                : (total >= 500 ? 'You make yourself heard.' : 'More of a listener than a talker.'),

            quipCollection: pct => pct >= 100 ? 'Nothing left to want.'
                : (pct >= 60 ? 'Not far now, and you know it.' : 'Still a lot out there.'),

            quipCalendar: days => days >= 300 ? 'You hardly ever skip.'
                : (days >= 100 ? 'A steady presence.' : 'You come and go, like the seasons.'),
            sumKicker: 'Your story at a glance',
            sumTitle: 'That is all',
            share: 'Share',
            shareOn: 'Link is live',
            replay: 'Replay',
            copied: 'Link copied!',
            shareOff: 'Link disabled',
            shareErr: 'That did not work, try again.',
            close: 'Close',
            back: 'Back to the site'
        }
    }[lang];

    // Palette per schermata: [colore principale, colore secondario].
    const THEMES = {
        intro:       ['#2f6bff', '#0b2a6b'],
        time:        ['#7c5cff', '#2b1a5e'],
        hours:       ['#4c1d95', '#0b1020'],
        calendar:    ['#0ea5e9', '#083344'],
        pages:       ['#06b6d4', '#083344'],
        gacha:       ['#fbbf24', '#5c3a00'],
        best_pull:   ['#f59e0b', '#4a1d00'],
        collection:  ['#a78bfa', '#3b1e75'],
        characters:  ['#c084fc', '#4c1d95'],
        cast:        ['#7c3aed', '#1e0a3c'],
        rarest:      ['#fbbf24', '#4a2c00'],
        achievements:['#f472b6', '#5c1140'],
        missions:    ['#34d399', '#064e3b'],
        social:      ['#22d3ee', '#083344'],
        profile:     ['#38bdf8', '#0c4a6e'],
        games:       ['#f87171', '#5c1414'],
        subway:      ['#fb7185', '#4c0519'],
        pullspot:    ['#a78bfa', '#2e1065'],
        animespot:   ['#34d399', '#052e1b'],
        content:     ['#fb923c', '#5c2a00'],
        top_post:    ['#f97316', '#431407'],
        economy:     ['#facc15', '#4a3a00'],
        busiest_day: ['#8b5cf6', '#2e1065'],
        persona:     ['#2f6bff', '#0b2a6b'],
        summary:     ['#1e293b', '#05070d'],
        card:        ['#0f172a', '#05070d']
    };

    // ─────────────────────────────────────────────────────────
    //  UTILITÀ
    // ─────────────────────────────────────────────────────────

    /** Neutralizza il markup nei dati scritti dagli utenti. */
    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, ch => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[ch]));
    }

    function num(value) {
        return Number(value || 0).toLocaleString(lang === 'en' ? 'en-US' : 'it-IT');
    }

    function formatDate(iso) {
        if (!iso) return '';
        const date = new Date(String(iso).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return String(iso).slice(0, 10);
        return date.toLocaleDateString(lang === 'en' ? 'en-GB' : 'it-IT', {
            day: 'numeric', month: 'long'
        });
    }

    function humanDuration(seconds) {
        const hours = Math.round(Number(seconds || 0) / 3600);
        if (hours >= 1) return `${num(hours)} ${T.timeUnitH}`;
        return `${num(Math.round(Number(seconds || 0) / 60))} ${T.timeUnitM}`;
    }

    function el(html) {
        const wrap = document.createElement('div');
        wrap.innerHTML = html.trim();
        return wrap.firstElementChild;
    }

    /** Marca gli elementi in ordine, così l'entrata è scaglionata. */
    function stagger(root) {
        root.querySelectorAll('.rw-in').forEach((node, index) => {
            node.style.setProperty('--rw-i', String(index));
        });
    }

    // ─────────────────────────────────────────────────────────
    //  MUSICA
    //
    //  Dieci tracce in /audio/rewind, mescolate a ogni apertura e
    //  suonate in sequenza. I browser bloccano la riproduzione finché
    //  non c'è un gesto dell'utente, quindi si tenta subito e, se viene
    //  rifiutata, si riprova al primo tocco o tasto premuto.
    // ─────────────────────────────────────────────────────────

    const AUDIO_TRACKS = 10;
    const AUDIO_DIR = '/audio/rewind/';
    const AUDIO_STORE = 'cripsum.rewind.volume';
    const AUDIO_DEFAULT_VOLUME = 0.45;

    /** Ogni quante schermate parte una canzone nuova. */
    const MUSIC_EVERY_SLIDES = 5;

    class Soundtrack {
        constructor() {
            this.order = this.shuffle();
            this.position = 0;
            this.muted = false;
            this.started = false;
            this.failed = false;
            this.fadeRaf = null;
            this.volume = this.readVolume();

            this.audio = new Audio();
            this.audio.preload = 'auto';
            this.audio.volume = 0;

            // A fine traccia si passa alla successiva; esaurita la lista si
            // rimescola, così due ascolti di fila non sono mai identici.
            this.audio.addEventListener('ended', () => {
                this.position += 1;
                if (this.position >= this.order.length) {
                    this.order = this.shuffle();
                    this.position = 0;
                }
                this.load();
                this.play();
            });

            // Un file mancante non deve interrompere la musica: si salta.
            this.audio.addEventListener('error', () => {
                if (this.position < this.order.length - 1) {
                    this.position += 1;
                    this.load();
                    this.play();
                } else {
                    this.failed = true;
                }
            });

            this.load();
        }

        shuffle() {
            const list = Array.from({ length: AUDIO_TRACKS }, (_, i) => i + 1);
            for (let i = list.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [list[i], list[j]] = [list[j], list[i]];
            }
            return list;
        }

        readVolume() {
            try {
                const stored = parseFloat(localStorage.getItem(AUDIO_STORE));
                if (Number.isFinite(stored) && stored >= 0 && stored <= 1) return stored;
            } catch (_) { /* private browsing */ }
            return AUDIO_DEFAULT_VOLUME;
        }

        saveVolume() {
            try { localStorage.setItem(AUDIO_STORE, String(this.volume)); } catch (_) {}
        }

        load() {
            this.audio.src = `${AUDIO_DIR}${this.order[this.position]}.mp3`;
        }

        /** Sale gradualmente al volume scelto: entrare a piena potenza stona. */
        /**
         * Sfuma il volume verso un valore.
         *
         * L'interpolazione non e' lineare: l'orecchio percepisce il volume
         * in modo logaritmico, quindi una rampa dritta suona come uno
         * scatto a meta' strada. Con una curva morbida in entrata e in
         * uscita il passaggio non si nota.
         *
         * Una sola dissolvenza per volta: se ne parte un'altra mentre la
         * prima e' in corso, la vecchia va fermata o si azzuffano sul
         * volume dello stesso elemento.
         */
        fadeTo(target, ms = 1600) {
            if (this.fadeRaf) cancelAnimationFrame(this.fadeRaf);

            const from = this.audio.volume;
            if (Math.abs(target - from) < 0.005) { this.audio.volume = target; return; }

            const started = performance.now();
            const step = now => {
                const p = Math.min(1, (now - started) / ms);
                // Accelera e decelera: niente spigoli agli estremi.
                const eased = p < 0.5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2;
                this.audio.volume = Math.max(0, Math.min(1, from + (target - from) * eased));
                this.fadeRaf = p < 1 ? requestAnimationFrame(step) : null;
            };
            this.fadeRaf = requestAnimationFrame(step);
        }

        /**
         * Passa alla traccia successiva sfumando fra le due.
         *
         * Il volume scende, si cambia sorgente e si risale: senza questo il
         * salto fra due brani si sente come un taglio netto proprio mentre
         * cambia la schermata.
         */
        next(fadeMs = 2000) {
            if (!this.started || this.failed) return;

            const target = this.muted ? 0 : this.volume;

            this.fadeTo(0, fadeMs);
            window.setTimeout(() => {
                this.position += 1;
                if (this.position >= this.order.length) {
                    this.order = this.shuffle();
                    this.position = 0;
                }
                this.load();
                this.audio.volume = 0;
                const p = this.audio.play();
                if (p && p.catch) p.catch(() => {});
                this.fadeTo(target, fadeMs + 600);
                this.onState?.();
            }, fadeMs);
        }
        play() {
            const promise = this.audio.play();
            if (promise && typeof promise.catch === 'function') {
                promise
                    .then(() => {
                        this.started = true;
                        this.audio.volume = 0;
                        this.fadeTo(this.muted ? 0 : this.volume, 2400);
                        this.onState?.();
                    })
                    .catch(() => { this.started = false; this.onState?.(); });
            }
        }

        toggle() {
            if (!this.started) { this.play(); return; }
            this.muted = !this.muted;
            this.fadeTo(this.muted ? 0 : this.volume, 520);
            this.onState?.();
        }

        setVolume(value) {
            this.volume = Math.max(0, Math.min(1, value));
            this.muted = this.volume === 0;
            this.audio.volume = this.volume;
            this.saveVolume();
            if (!this.started) this.play();
            this.onState?.();
        }

        get isPlaying() {
            return this.started && !this.muted && !this.audio.paused;
        }
    }

    /** Costruisce il controllo audio e lo collega alla colonna sonora. */
    function mountAudioControl(container, track) {
        const node = el(`
            <div class="rw-audio" data-rw-audio>
                <button type="button" class="rw-audio__toggle" data-rw-audio-toggle
                        aria-label="${esc(T.audioToggle)}">
                    <i class="fa-solid fa-music"></i>
                </button>
                <span class="rw-audio__viz" aria-hidden="true"><span></span><span></span><span></span></span>
                <input type="range" class="rw-audio__slider" data-rw-audio-volume
                       min="0" max="1" step="0.01" value="${track.volume}"
                       aria-label="${esc(T.audioVolume)}">
            </div>`);

        container.prepend(node);

        const toggle = node.querySelector('[data-rw-audio-toggle]');
        const slider = node.querySelector('[data-rw-audio-volume]');
        const icon = toggle.querySelector('i');

        const paint = () => {
            const pct = Math.round(track.volume * 100);
            slider.style.setProperty('--rw-vol', pct + '%');
            node.classList.toggle('is-playing', track.isPlaying);
            icon.className = track.isPlaying
                ? 'fa-solid fa-music'
                : (track.started ? 'fa-solid fa-volume-xmark' : 'fa-solid fa-play');
        };

        track.onState = paint;
        paint();

        toggle.addEventListener('click', event => {
            event.stopPropagation();
            track.toggle();
        });

        slider.addEventListener('input', event => {
            event.stopPropagation();
            track.setVolume(parseFloat(event.target.value));
        });

        // Il pannello si apre al passaggio del mouse; su touch lo apre il
        // primo tocco sull'icona, senza rubare spazio al racconto.
        node.addEventListener('pointerenter', () => node.classList.add('is-open'));
        node.addEventListener('pointerleave', () => node.classList.remove('is-open'));
        toggle.addEventListener('focus', () => node.classList.add('is-open'));
        node.addEventListener('focusout', () => {
            if (!node.contains(document.activeElement)) node.classList.remove('is-open');
        });

        // I controlli non devono far avanzare la storia né metterla in pausa.
        ['pointerdown', 'pointerup', 'click'].forEach(evt => {
            node.addEventListener(evt, e => e.stopPropagation());
        });

        return paint;
    }

    // ─────────────────────────────────────────────────────────
    //  DISEGNO DELLE SCHERMATE
    // ─────────────────────────────────────────────────────────

    const RENDER = {
        intro(d) {
            const u = d.user || {};
            const days = u.days_on_site || 0;
            const since = u.member_since ? new Date(String(u.member_since).replace(' ', 'T')) : null;
            const year = since && !Number.isNaN(since.getTime()) ? since.getFullYear() : null;

            // Questa schermata non avanza da sola: è la copertina. Le lettere
            // del titolo entrano una alla volta, l'avatar sale dall'anello e
            // solo alla fine compare il pulsante.
            const title = T.introTitle.split('<br>').map((line, lineIndex) => {
                const letters = [...line].map((ch, i) =>
                    `<span class="rw-letter" style="--rw-l:${lineIndex * 14 + i}">${ch === ' ' ? '&nbsp;' : esc(ch)}</span>`
                ).join('');
                return `<span class="rw-titleline">${letters}</span>`;
            }).join('');

            return `
                <div class="rw-cover">
                    <div class="rw-cover__avatar rw-in">
                        <span class="rw-cover__ring" aria-hidden="true"></span>
                        <img src="${esc(u.avatar || '/img/Susremaster.png')}" alt="${esc(u.display_name || '')}"
                             onerror="this.src='/img/Susremaster.png'">
                    </div>
                    <p class="rw-kicker rw-in">${esc(T.introKicker)}</p>
                    <h1 class="rw-title rw-cover__title">${title}</h1>
                    <p class="rw-lead rw-in">${T.introLead(num(days))}</p>
                    ${year ? `<p class="rw-note rw-in">${esc(T.introSince(year))}</p>` : ''}
                    <div class="rw-actions rw-in">
                        <button type="button" class="rw-btn rw-btn--cta" data-rw-next>
                            <span>${esc(T.introStart)}</span>
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                    <p class="rw-cover__hint rw-in">${esc(T.introHint)}</p>
                </div>`;
        },

        time(d) {
            const t = d.time || {};
            const value = t.hours >= 1 ? t.hours : Math.round((t.seconds || 0) / 60);
            const unit = t.hours >= 1 ? T.timeUnitH : T.timeUnitM;

            return `
                <p class="rw-kicker rw-in">${esc(T.timeKicker)}</p>
                <p class="rw-big rw-in" data-count="${value}">0<small>${esc(unit)}</small></p>
                <p class="rw-lead rw-in">${T.timeLead(humanDuration(t.seconds), num(t.days_active))}</p>
                <div class="rw-stats rw-in">
                    ${stat(t.longest_streak, T.timeStreak)}
                    ${stat(t.days_active, T.timeDays)}
                    ${stat(t.sessions, T.timeSessions)}
                    ${stat(Math.round((t.longest_session || 0) / 60) + ' min', T.timeLongest)}
                </div>
                <p class="rw-quip rw-in">${esc(T.quipTime(t.hours || 0))}</p>
`;
        },

        hours(d) {
            const h = d.hours || {};
            const buckets = h.buckets || [];
            const max = Math.max(1, ...buckets);
            const peak = String(h.peak_hour ?? 0).padStart(2, '0') + ':00';

            const bands = [
                [h.share_night, T.bandNight],
                [h.share_morning, T.bandMorning],
                [h.share_day, T.bandDay],
                [h.share_evening, T.bandEvening]
            ].sort((a, b) => b[0] - a[0])[0];

            const bars = buckets.map((value, hour) => {
                const pct = Math.max(3, Math.round(value / max * 100));
                const isPeak = hour === h.peak_hour ? ' is-peak' : '';
                return `<span class="rw-clock__bar${isPeak}" data-h="${pct}"></span>`;
            }).join('');

            return `
                <p class="rw-kicker rw-in">${esc(T.hoursKicker)}</p>
                <h2 class="rw-title rw-in">${T.hoursTitle(peak)}</h2>
                <div class="rw-clock rw-in">${bars}</div>
                <div class="rw-clock__axis rw-in"><span>00</span><span>06</span><span>12</span><span>18</span><span>23</span></div>
                <p class="rw-lead rw-in" style="margin-top:18px">
                    ${T.chrono[h.chronotype] || T.chrono.equilibrato}
                    ${bands && bands[0] > 0 ? ' ' + T.hoursShare(bands[0], bands[1]) : ''}
                </p>`;
        },

        calendar(d) {
            const cal = d.calendar || {};
            const days = cal.days || {};
            const entries = Object.entries(days);
            const maxSeconds = Math.max(1, ...entries.map(([, v]) => v.seconds || 0));

            // La griglia copre l'intero periodo, non solo i giorni presenti:
            // i buchi sono l'informazione più leggibile di una heatmap.
            // Il periodo copre tutta la vita dell'account, la heatmap solo
            // l'ultimo anno: il server decide la finestra, qui si disegna.
            const start = new Date((cal.heatmap_start || d.period.start) + 'T00:00:00');
            const end = new Date((cal.heatmap_end || d.period.end) + 'T00:00:00');
            const cells = [];

            for (let cursor = new Date(start); cursor <= end; cursor.setDate(cursor.getDate() + 1)) {
                const key = cursor.toISOString().slice(0, 10);
                const entry = days[key];
                let level = 0;
                if (entry) {
                    const ratio = (entry.seconds || 0) / maxSeconds;
                    level = entry.seconds === 0 ? 1 : Math.min(4, Math.max(1, Math.ceil(ratio * 4)));
                }
                cells.push(`<span class="rw-heat__cell" data-level="${level}" title="${key}"></span>`);
            }

            return `
                <p class="rw-kicker rw-in">${esc(T.calKicker)}</p>
                <h2 class="rw-title rw-in">${T.calTitle(num(cal.total_days))}</h2>
                <div class="rw-heat rw-in">${cells.join('')}</div>
                <p class="rw-note rw-in">${esc(T.calLead)}</p>
                <p class="rw-quip rw-in">${esc(T.quipCalendar(cal.total_days || 0))}</p>`;
        },

        pages(d) {
            const top = (d.pages?.top || []).slice(0, 5);
            if (!top.length) return '';
            const max = Math.max(1, ...top.map(p => p.seconds));

            const bars = top.map(page => {
                const name = T.pageNames[page.key] || page.key;
                return `
                    <div class="rw-in">
                        <div class="rw-bar__head">
                            <span class="rw-bar__name">${esc(name)}</span>
                            <span class="rw-bar__value">${esc(humanDuration(page.seconds))}</span>
                        </div>
                        <span class="rw-bar__track">
                            <span class="rw-bar__fill" data-w="${Math.round(page.seconds / max * 100)}"></span>
                        </span>
                    </div>`;
            }).join('');

            return `
                <p class="rw-kicker rw-in">${esc(T.pagesKicker)}</p>
                <h2 class="rw-title rw-in">${esc(T.pagesTitle)}</h2>
                <p class="rw-lead rw-in">${T.pagesLead(esc(T.pageNames[top[0].key] || top[0].key))}</p>
                <div class="rw-bars">${bars}</div>`;
        },

        gacha(d) {
            const g = d.gacha || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.gachaKicker)}</p>
                <p class="rw-big rw-in" data-count="${g.pulls || 0}">0<small>${esc(T.gachaTitle)}</small></p>
                <p class="rw-lead rw-in">${T.gachaLead(num(g.pulls))}</p>
                <div class="rw-stats rw-in">
                    ${stat(g.new_chars, T.gachaNew)}
                    ${stat(g.max_pity, T.gachaPity)}
                    ${g.rate_5050 !== null && g.rate_5050 !== undefined ? stat(g.rate_5050 + '%', T.gacha5050) : ''}
                    ${g.godos_spent ? stat(num(g.godos_spent), T.gachaSpent) : ''}
                </div>
                <p class="rw-quip rw-in">${esc(T.quipGacha(g.pulls || 0, g.rate_5050 ?? null))}</p>`;
        },

        best_pull(d) {
            const p = d.gacha?.best_pull;
            if (!p) return '';
            const img = p.img_url ? `<img class="rw-hero__img" src="${esc(p.img_url)}" alt="${esc(p.nome)}" loading="eager">` : '';
            const pity = Number(p.pity_al_momento || 0);

            return `
                <p class="rw-kicker rw-in">${esc(T.bestKicker)}</p>
                <div class="rw-hero rw-in">
                    ${img}
                    <span class="rw-chip rw-chip--gold">${esc(p.rarita || '')}</span>
                </div>
                <h2 class="rw-title rw-in" style="font-size:clamp(1.4rem,5.4vw,2rem)">${esc(p.nome)}</h2>
                <p class="rw-lead rw-in">${pity > 0 ? T.bestLead(esc(p.nome), pity) : T.bestLeadNoPity(esc(p.nome))}</p>
                <p class="rw-note rw-in">${esc(formatDate(p.created_at))}</p>`;
        },

        collection(d) {
            const c = d.collection || {};

            // Il piu' raro NON sta qui: ha la sua schermata piu' avanti.
            // Prima compariva anche in questa e nella sequenza in scena, cioe'
            // tre volte nello stesso racconto.
            const pct = Math.max(0, Math.min(100, Number(c.completion || 0)));

            return `
                <p class="rw-kicker rw-in">${esc(T.collKicker)}</p>
                <p class="rw-big rw-in" data-count="${c.owned || 0}">0<small>${esc(T.collTitle)}</small></p>
                <p class="rw-lead rw-in">${T.collLead(num(c.owned), num(c.catalogue), c.completion)}</p>
                <div class="rw-meter rw-in" role="img" aria-label="${pct}%">
                    <span class="rw-meter__fill" data-w="${pct}"></span>
                </div>
                <p class="rw-note rw-in">${esc(T.collProgress(pct))}</p>
                <p class="rw-quip rw-in">${esc(T.quipCollection(pct))}</p>`;
        },

        /**
         * Sequenza cinematica dei tre personaggi che contano.
         *
         * Non e' una schermata con tre riquadri: entrano uno alla volta, con
         * il titolo che sfuma prima e un riflettore che scorre. Per questo
         * dura piu' del doppio delle altre (vedi SLIDE_DURATIONS).
         */
        cast(d) {
            const c = d.collection || {};

            const acts = [];
            // Solo i due estremi: il piu' raro ha la schermata dopo.
            if (c.most_pulled)  acts.push(['most',  T.castMost,  c.most_pulled,  T.chrTimes(Number(c.most_pulled.pulls || 0))]);
            if (c.least_pulled) acts.push(['least', T.castLeast, c.least_pulled, T.chrTimes(Number(c.least_pulled.pulls || 0))]);

            if (acts.length < 2) return '';

            // Ogni atto entra dopo il precedente: il ritardo e' il suo posto
            // nella sequenza, non un numero scelto a caso.
            const scene = acts.map(([kind, label, item, meta], i) => `
                <figure class="rw-act rw-act--${esc(kind)}" style="--rw-act:${i}">
                    <span class="rw-act__beam" aria-hidden="true"></span>
                    <span class="rw-act__label">${esc(label)}</span>
                    ${item.img_url
                        ? `<img class="rw-act__img" src="${esc(item.img_url)}" alt="${esc(item.nome)}" loading="eager" onerror="this.remove()">`
                        : '<span class="rw-act__img rw-act__img--empty"></span>'}
                    <figcaption>
                        <span class="rw-act__name">${esc(item.nome || '')}</span>
                        <span class="rw-act__meta">${esc(meta)}</span>
                    </figcaption>
                </figure>`).join('');

            return `
                <div class="rw-cast">
                    <p class="rw-kicker rw-cast__kicker">${esc(T.castKicker)}</p>
                    <h2 class="rw-title rw-cast__title">${esc(T.castTitle)}</h2>
                    <div class="rw-cast__stage">${scene}</div>
                </div>`;
        },
        characters(d) {
            const c = d.collection || {};
            const most = c.most_pulled;
            const least = c.least_pulled;
            if (!most || !least) return '';

            const side = (entry, tag) => `
                <div class="rw-duo__side">
                    <span class="rw-duo__tag">${esc(tag)}</span>
                    ${entry.img_url ? `<img class="rw-duo__img" src="${esc(entry.img_url)}" alt="${esc(entry.nome)}" loading="lazy">` : ''}
                    <span class="rw-duo__name">${esc(entry.nome)}</span>
                    <span class="rw-duo__count">${esc(T.chrTimes(Number(entry.pulls || 0)))}</span>
                </div>`;

            return `
                <p class="rw-kicker rw-in">${esc(T.chrKicker)}</p>
                <h2 class="rw-title rw-in">${esc(T.chrTitle)}</h2>
                <div class="rw-duo rw-in">
                    ${side(most, T.chrMost)}
                    ${side(least, T.chrLeast)}
                </div>
                <p class="rw-lead rw-in" style="margin-top:18px">${T.chrLead(esc(most.nome), num(most.pulls))}</p>`;
        },

        top_post(d) {
            const c = d.content || {};
            const rows = [];

            if (c.best_post) {
                rows.push(['fa-solid fa-heart', T.postLikes, c.best_post.titolo,
                    T.postLikesN(num(c.best_post.likes)), c.best_post.media_url, c.best_post.descrizione]);
            }
            if (c.most_viewed) {
                rows.push(['fa-solid fa-eye', T.postViews, c.most_viewed.titolo,
                    T.postViewsN(num(c.most_viewed.views)), c.most_viewed.media_url, c.most_viewed.descrizione]);
            }
            if (c.most_commented) {
                rows.push(['fa-solid fa-comment', T.postComments, c.most_commented.titolo,
                    T.postCommentsN(num(c.most_commented.comments)), c.most_commented.media_url, c.most_commented.descrizione]);
            }
            if (c.top_rimasto) {
                rows.push(['fa-solid fa-star', T.postRimasto, c.top_rimasto.titolo,
                    T.postVotesN(num(c.top_rimasto.votes)), c.top_rimasto.media_url, c.top_rimasto.descrizione]);
            }
            if (!rows.length) return '';

            const list = rows.map(([icon, label, title, meta, media, desc]) => `
                <div class="rw-record rw-in">
                    ${media
                        ? `<img class="rw-record__media" src="${esc(media)}" alt="" loading="lazy"
                                onerror="this.outerHTML='<span class=&quot;rw-record__icon&quot;><i class=&quot;${esc(icon)}&quot;></i></span>'">`
                        : `<span class="rw-record__icon"><i class="${esc(icon)}"></i></span>`}
                    <span class="rw-record__body">
                        <span class="rw-record__label">${esc(label)}</span>
                        <span class="rw-record__title">${esc(title || '—')}</span>
                        ${desc ? `<span class="rw-record__desc">${esc(desc)}</span>` : ''}
                        <span class="rw-record__meta">${esc(meta)}</span>
                    </span>
                </div>`).join('');

            return `
                <p class="rw-kicker rw-in">${esc(T.postKicker)}</p>
                <h2 class="rw-title rw-in">${esc(T.postTitle)}</h2>
                <div class="rw-records rw-records--${rows.length}">${list}</div>
                ${c.views_received ? `<p class="rw-note rw-in">${T.postViewsTotal(num(c.views_received))}</p>` : ''}`;
        },

        /**
         * Il pezzo piu' raro della collezione, da solo.
         *
         * Ha bisogno di spazio suo: messo accanto agli altri due si perdeva,
         * e ripetuto in tre schermate diverse diventava rumore.
         */
        rarest(d) {
            const r = d.collection?.rarest;
            if (!r) return '';

            return `
                <p class="rw-kicker rw-in">${esc(T.rarestKicker)}</p>
                <div class="rw-relic rw-in">
                    <span class="rw-relic__halo" aria-hidden="true"></span>
                    ${r.img_url
                        ? `<img class="rw-relic__img" src="${esc(r.img_url)}" alt="${esc(r.nome)}" loading="eager" onerror="this.remove()">`
                        : ''}
                </div>
                <h2 class="rw-title rw-in" style="font-size:clamp(1.5rem,5vw,2.4rem)">${esc(r.nome)}</h2>
                <p class="rw-in"><span class="rw-chip rw-chip--gold">${esc(r.rarita || '')}</span></p>
                <p class="rw-lead rw-in" style="margin-top:14px">${esc(T.rarestLead)}</p>
                ${r.was_lucky_pull && r.pity !== null && r.pity !== undefined
                    ? `<p class="rw-lead rw-in">${T.rarestPity(r.pity)}</p>` : ''}
                ${r.data ? `<p class="rw-note rw-in">${esc(T.rarestWhen(formatDate(r.data)))}</p>` : ''}`;
        },
        achievements(d) {
            const a = d.achievements || {};
            const rarest = a.rarest;
            return `
                <p class="rw-kicker rw-in">${esc(T.achKicker)}</p>
                <p class="rw-big rw-in" data-count="${a.unlocked_in_period || 0}">0<small>${esc(T.achTitle)}</small></p>
                <p class="rw-lead rw-in">${T.achLead(num(a.points_in_period))}</p>
                ${rarest ? `
                    <div class="rw-medal rw-in">
                        <span class="rw-medal__glow" aria-hidden="true"></span>
                        ${rarest.img_url
                            ? `<img class="rw-medal__img" src="${esc(rarest.img_url)}" alt="${esc(rarest.nome)}" loading="eager" onerror="this.onerror=null;this.src='/img/achievement-default.png'">`
                            : '<span class="rw-medal__img rw-medal__img--empty"><i class="fa-solid fa-trophy"></i></span>'}
                        <span class="rw-medal__body">
                            <span class="rw-medal__label">${esc(T.achRarestLabel)}</span>
                            <strong class="rw-medal__name">${esc(lang === 'en' ? (rarest.nome_en || rarest.nome) : rarest.nome)}</strong>
                            ${rarest.owners_pct !== undefined
                                ? `<span class="rw-medal__meta">${T.achRarest(rarest.owners_pct)}</span>` : ''}
                        </span>
                    </div>` : ''}`;
        },

        missions(d) {
            const m = d.missions || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.misKicker)}</p>
                <p class="rw-big rw-in" data-count="${m.completed || 0}">0<small>${esc(T.misTitle)}</small></p>
                <p class="rw-lead rw-in">${T.misLead(m.claim_rate)}</p>

                <p class="rw-quip rw-in">${esc(T.quipMissions(m.claim_rate || 0))}</p>`;
        },

        social(d) {
            const s = d.social || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.socKicker)}</p>
                <p class="rw-big rw-in" data-count="${s.msg_total || 0}">0<small>${esc(T.socTitle)}</small></p>
                <div class="rw-stats rw-in">
                    ${stat(num(s.msg_global), T.socGlobal)}
                    ${stat(num(s.msg_private), T.socPrivate)}
                    ${stat(num(s.msg_group), T.socGroup)}
                    ${stat(num(s.friends_total), T.socFriends)}
                </div>
                ${s.top_partner ? `
                    <div class="rw-person rw-in">
                        <div class="rw-avatar">
                            <img src="${esc(s.top_partner.avatar || '/img/Susremaster.png')}"
                                 alt="${esc(s.top_partner.display_name)}"
                                 onerror="this.src='/img/Susremaster.png'">
                        </div>
                        <span class="rw-person__text">${T.socPartner(esc(s.top_partner.display_name))}</span>
                    </div>` : ''}
                ${s.busiest_day ? `<p class="rw-note rw-in">${esc(T.socBusiest(formatDate(s.busiest_day.giorno), s.busiest_day.n))}</p>` : ''}
                <p class="rw-quip rw-in">${esc(T.quipSocial(s.msg_total || 0))}</p>`;
        },

        profile(d) {
            const p = d.profile || {};
            const u = d.user || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.proKicker)}</p>
                <div class="rw-avatar rw-avatar--lg rw-in">
                    <img src="${esc(u.avatar || '/img/Susremaster.png')}" alt="${esc(u.display_name || '')}"
                         onerror="this.src='/img/Susremaster.png'">
                </div>
                <p class="rw-username rw-in">@${esc(u.username || '')}</p>
                <p class="rw-big rw-in" data-count="${p.views_lifetime || 0}">0<small>${esc(T.proTitle)}</small></p>
                <p class="rw-lead rw-in">${T.proLead(num(p.changes))}</p>
                ${p.changes > 20 ? `<p class="rw-note rw-in">${esc(T.proNeverHappy)}</p>` : ''}`;
        },

        games(d) {
            const g = d.games || {};
            const hasDuels = (g.duels_played || 0) > 0;

            return `
                <p class="rw-kicker rw-in">${esc(T.gamKicker)}</p>
                <p class="rw-big rw-in" data-count="${g.duels_played || 0}">0<small>${esc(T.gamTitle)}</small></p>
                ${hasDuels ? `
                    <div class="rw-stats rw-in">
                        ${stat(num(g.duels_won), T.gamWin)}
                        ${stat(num(g.duels_lost), T.gamLoss)}
                        ${g.winrate !== null && g.winrate !== undefined ? stat(g.winrate + '%', T.gamRate) : ''}
                    </div>` : ''}
                <p class="rw-quip rw-in">${esc(T.quipGames(g.duels_played || 0, g.winrate ?? 0))}</p>`;
        },

        /**
         * Subway ha una schermata sua: il record è un tempo, non un
         * conteggio, e affogato in fondo a quella dei duelli non si notava.
         */
        subway(d) {
            const g = d.games || {};
            if (!g.subway_best_ms) return '';

            const seconds = (g.subway_best_ms / 1000);
            const mins = Math.floor(seconds / 60);
            const secs = (seconds % 60).toFixed(1);
            const pretty = mins > 0 ? `${mins}:${String(secs).padStart(4, '0')}` : `${secs}s`;

            const mapName = g.subway_map
                ? g.subway_map.charAt(0).toUpperCase() + g.subway_map.slice(1)
                : null;

            return `
                <p class="rw-kicker rw-in">${esc(T.subKicker)}</p>
                <p class="rw-big rw-in">${esc(pretty)}<small>${esc(T.subTitle)}</small></p>
                <div class="rw-stats rw-in">
                    ${g.subway_rank ? stat('#' + g.subway_rank, T.subRank) : ''}
                    ${g.subway_runs ? stat(num(g.subway_runs), T.subRuns) : ''}
                    ${mapName ? stat(mapName, T.subMap) : ''}
                </div>
                ${g.subway_rank && g.subway_rank <= 3
                    ? `<p class="rw-lead rw-in" style="margin-top:18px">${T.subPodium(g.subway_rank)}</p>`
                    : ''}
                <p class="rw-quip rw-in">${esc(T.quipSubway(g.subway_runs || 0))}</p>`;
        },

        /**
         * Pullspot: il numero che conta è quante tracce hai riconosciuto, non
         * quante partite hai fatto. Il colpo migliore si prende la riga sotto.
         */
        pullspot(d) {
            const g = d.games || {};
            if (!(g.pullspot_played > 0)) return '';

            return `
                <p class="rw-kicker rw-in">${esc(T.psKicker)}</p>
                <p class="rw-big rw-in" data-count="${g.pullspot_won || 0}">0<small>${esc(T.psTitle)}</small></p>
                <div class="rw-stats rw-in">
                    ${stat(num(g.pullspot_played), T.psPlayed)}
                    ${g.pullspot_rate !== null && g.pullspot_rate !== undefined ? stat(g.pullspot_rate + '%', T.psRate) : ''}
                    ${g.pullspot_first ? stat(num(g.pullspot_first), T.psFirst) : ''}
                </div>
                ${g.pullspot_best ? `<p class="rw-lead rw-in" style="margin-top:18px">${esc(T.psBest(g.pullspot_best))}${
                    g.pullspot_best_who ? ' ' + esc(T.psBestWho(g.pullspot_best_who)) : ''}</p>` : ''}
                <p class="rw-quip rw-in">${esc(T.quipPullspot(g.pullspot_won || 0, g.pullspot_first || 0))}</p>`;
        },

        /*
         * Animespot: come il Pullspot, ma il colpo migliore vale il doppio se
         * è arrivato in una difficoltà alta — indovinare una sigla che non
         * conosce nessuno non è la stessa cosa che indovinare l'opening di
         * Attack on Titan.
         */
        animespot(d) {
            const g = d.games || {};
            if (!(g.animespot_played > 0)) return '';

            return `
                <p class="rw-kicker rw-in">${esc(T.asKicker)}</p>
                <p class="rw-big rw-in" data-count="${g.animespot_won || 0}">0<small>${esc(T.asTitle)}</small></p>
                <div class="rw-stats rw-in">
                    ${stat(num(g.animespot_played), T.asPlayed)}
                    ${g.animespot_rate !== null && g.animespot_rate !== undefined ? stat(g.animespot_rate + '%', T.asRate) : ''}
                    ${g.animespot_first ? stat(num(g.animespot_first), T.asFirst) : ''}
                    ${g.animespot_points ? stat(num(g.animespot_points), T.asPoints) : ''}
                </div>
                ${g.animespot_best ? `<p class="rw-lead rw-in" style="margin-top:18px">${esc(T.asBest(g.animespot_best, g.animespot_best_lvl))}${
                    g.animespot_best_who ? ' ' + esc(T.asBestWho(g.animespot_best_who)) : ''}</p>` : ''}
                <p class="rw-quip rw-in">${esc(T.quipAnimespot(g.animespot_won || 0, g.animespot_first || 0))}</p>`;
        },

        content(d) {
            const c = d.content || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.conKicker)}</p>
                <p class="rw-big rw-in" data-count="${c.likes_received || 0}">0<small>${esc(T.conTitle)}</small></p>
                <p class="rw-lead rw-in">${T.conLead(num(c.posts_total ?? c.shitposts), num(c.comments))}</p>
                <div class="rw-stats rw-in">
                    ${c.shitposts ? stat(num(c.shitposts), T.conShitposts) : ''}
                    ${c.rimasti ? stat(num(c.rimasti), T.conRimasti) : ''}
                    ${c.views_received ? stat(num(c.views_received), T.conViews) : ''}
                    ${c.votes_received ? stat(num(c.votes_received), T.conVotesIn) : ''}
                </div>
                <p class="rw-quip rw-in">${esc(T.quipContent(c.likes_received || 0))}</p>`;
        },

        economy(d) {
            const e = d.economy || {};
            return `
                <p class="rw-kicker rw-in">${esc(T.ecoKicker)}</p>
                <img class="rw-coin rw-in" src="/img/godos.png" alt="Godos" onerror="this.remove()">
                <p class="rw-big rw-in" data-count="${e.spent || 0}">0<small>${esc(T.ecoTitle)}</small></p>
                <div class="rw-stats rw-in">
                    ${stat(num(e.earned), T.ecoEarned)}
                    ${stat(num(e.spent), T.ecoSpent)}
                    ${e.balance !== undefined ? stat(num(e.balance), T.ecoBalance) : ''}
                </div>
                <p class="rw-quip rw-in">${esc(T.quipEconomy(e.net || 0))}</p>`;
        },

        busiest_day(d) {
            const b = d.calendar?.busiest_day;
            if (!b) return '';
            return `
                <p class="rw-kicker rw-in">${esc(T.busyKicker)}</p>
                <h2 class="rw-title rw-in">${esc(formatDate(b.date))}</h2>
                <p class="rw-lead rw-in">${T.busyLead(esc(formatDate(b.date)), Math.round(b.seconds / 60), b.actions)}</p>`;
        },

        persona(d) {
            const p = d.persona || {};
            const rank = d.ranking || {};
            const name = lang === 'en' ? p.name_en : p.name_it;
            const desc = lang === 'en' ? p.desc_en : p.desc_it;

            return `
                <p class="rw-kicker rw-in">${esc(T.perKicker)}</p>
                <div class="rw-persona__icon rw-in">${p.image ? `<img src="${esc(p.image)}" alt="" onerror="this.outerHTML='<i class=&quot;${esc(p.icon || 'fa-solid fa-star')}&quot;></i>'">` : `<i class="${esc(p.icon || 'fa-solid fa-star')}"></i>`}</div>
                <h2 class="rw-persona__name rw-in">${esc(name || '')}</h2>
                <p class="rw-lead rw-in">${esc(desc || '')}</p>
                ${rank.has_data ? `<p class="rw-note rw-in">${T.rankTop(rank.top_percent, num(rank.total_users))}</p>` : ''}`;
        },

        /**
         * L'ultima schermata: la card vera, disegnata su canvas e
         * scaricabile. Il canvas e' anche cio' che si vede, quindi quello che
         * si salva e' esattamente quello che si guarda.
         */
        card(d) {
            return `
                <p class="rw-kicker rw-in">${esc(T.cardKicker)}</p>
                <h2 class="rw-title rw-in">${esc(T.cardTitle)}</h2>
                <div class="rw-cardwrap rw-in">
                    <canvas class="rw-cardcanvas" data-rw-card
                            width="1080" height="1350"
                            role="img" aria-label="${esc(T.cardTitle)}"></canvas>
                </div>
                <div class="rw-swatches rw-in" role="group" aria-label="${esc(T.cardTheme)}">
                    ${CARD_THEMES.map((t, i) => `
                        <button type="button" class="rw-swatch${i === 0 ? ' is-on' : ''}"
                                data-rw-theme="${esc(t.id)}" aria-label="${esc(t.id)}"
                                style="--rw-sw1:${esc(t.from || (d.persona?.color || '#2f6bff'))};--rw-sw2:${esc(t.to || (d.persona?.color_2 || '#0b2a6b'))}"></button>`).join('')}
                </div>
                <p class="rw-note rw-in">${esc(T.cardLead)}</p>
                <div class="rw-actions rw-in">
                    <button type="button" class="rw-btn" data-rw-download>
                        <i class="fa-solid fa-download"></i> <span>${esc(T.cardDownload)}</span>
                    </button>
                    <button type="button" class="rw-btn rw-btn--ghost" data-rw-replay>
                        <i class="fa-solid fa-rotate-left"></i> ${esc(T.replay)}
                    </button>
                </div>`;
        },
        summary(d) {
            const t = d.time || {};
            const g = d.gacha || {};
            const s = d.social || {};
            const a = d.achievements || {};
            const p = d.persona || {};

            const shareBtn = isPublic ? '' : `
                <button type="button" class="rw-btn" data-rw-share>
                    <i class="fa-solid fa-link"></i> <span data-rw-share-label>${esc(T.share)}</span>
                </button>`;

            return `
                <p class="rw-kicker rw-in">${esc(T.sumKicker)}</p>
                <h2 class="rw-title rw-in">${esc(p[lang === 'en' ? 'name_en' : 'name_it'] || T.sumTitle)}</h2>
                <div class="rw-summary rw-in">
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${esc(humanDuration(t.seconds))}</div>
                        <div class="rw-summary__label">${esc(T.timeKicker)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(t.days_active)}</div>
                        <div class="rw-summary__label">${esc(T.timeDays)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(g.pulls)}</div>
                        <div class="rw-summary__label">${esc(T.gachaTitle)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(s.msg_total)}</div>
                        <div class="rw-summary__label">${esc(T.socTitle)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(a.unlocked_in_period)}</div>
                        <div class="rw-summary__label">${esc(T.achKicker)}</div>
                    </div>
                    <div class="rw-summary__item">
                        <div class="rw-summary__value">${num(t.longest_streak)}</div>
                        <div class="rw-summary__label">${esc(T.timeStreak)}</div>
                    </div>
                </div>
                <div class="rw-actions rw-in">
                    ${shareBtn}
                    <button type="button" class="rw-btn rw-btn--ghost" data-rw-replay>
                        <i class="fa-solid fa-rotate-left"></i> ${esc(T.replay)}
                    </button>
                    <a class="rw-btn rw-btn--ghost" href="/${lang}/home">
                        <i class="fa-solid fa-house"></i> ${esc(T.back)}
                    </a>
                </div>`;
        }
    };

    function stat(value, label) {
        return `<div class="rw-stat">
            <span class="rw-stat__value">${esc(value ?? 0)}</span>
            <span class="rw-stat__label">${esc(label)}</span>
        </div>`;
    }

    // ─────────────────────────────────────────────────────────
    //  CARD FINALE
    //
    //  Disegnata su <canvas> invece che generata dal server: non
    //  dipende da GD, non serve un Rewind gia' condiviso per ottenerla, e
    //  soprattutto il file esce direttamente dal browser di chi la vuole.
    //  Tutte le immagini coinvolte (avatar, personaggi) sono dello stesso
    //  dominio, quindi il canvas non viene contaminato e toBlob funziona.
    // ─────────────────────────────────────────────────────────

    /**
     * Temi della card.
     *
     * Il primo prende i colori dell'archetipo, gli altri sono fissi. Sono
     * coppie [colore alto, colore basso]: la card e' una sfumatura fra i
     * due piu' il nero del fondo, quindi bastano questi per cambiarne
     * completamente l'aria.
     */
    const CARD_THEMES = [
        { id: 'persona', from: null,      to: null      },
        { id: 'notte',   from: '#4338ca', to: '#0b1026' },
        { id: 'tramonto',from: '#f97316', to: '#4a1d00' },
        { id: 'menta',   from: '#10b981', to: '#04352a' },
        { id: 'rosa',    from: '#ec4899', to: '#4a0d2e' },
        { id: 'ghiaccio',from: '#38bdf8', to: '#0b2b45' },
        { id: 'oro',     from: '#f59e0b', to: '#3b2600' },
        { id: 'carbone', from: '#475569', to: '#05070d' }
    ];

    const CARD_W = 1080;
    const CARD_H = 1350;

    /**
     * Impaginazione della card.
     *
     * CARD_PAD è l'unico margine orizzontale: griglia, riquadri e testi
     * lo usano tutti, così i bordi restano allineati fra loro. Le
     * posizioni verticali non sono scritte a mano ma calcolate a
     * cascata: ogni elemento parte dove finisce il precedente più uno
     * spazio. Prima erano coordinate fisse e bastava spostarne una per
     * farla finire sopra a un'altra.
     */
    const CARD_PAD = 70;
    const CARD_CELL_H = 150;
    const CARD_CELL_GAP = 15;
    const CARD_CHIP_H = 110;
    const CARD_FOOTER = 52;

    /** Carica un'immagine, o restituisce null se non arriva. */
    function loadImage(src) {
        return new Promise(resolve => {
            if (!src) return resolve(null);
            const img = new Image();
            img.onload = () => resolve(img);
            img.onerror = () => resolve(null);
            img.src = src;
        });
    }

    function hexToRgb(hex) {
        const clean = String(hex || '').replace('#', '');
        if (!/^[0-9a-f]{6}$/i.test(clean)) return [47, 107, 255];
        return [
            parseInt(clean.slice(0, 2), 16),
            parseInt(clean.slice(2, 4), 16),
            parseInt(clean.slice(4, 6), 16)
        ];
    }

    /** Testo troncato con i puntini se supera la larghezza data. */
    function fitText(ctx, text, maxWidth) {
        let value = String(text ?? '');
        if (ctx.measureText(value).width <= maxWidth) return value;
        while (value.length > 1 && ctx.measureText(value + '…').width > maxWidth) {
            value = value.slice(0, -1);
        }
        return value + '…';
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    /**
     * Disegna la card. Le sei statistiche sono quelle che raccontano di piu'
     * a colpo d'occhio; se una manca il riquadro resta comunque riempito.
     */
    async function drawShareCard(canvas, d, themeId = 'persona') {
        const ctx = canvas.getContext('2d');
        canvas.width = CARD_W;
        canvas.height = CARD_H;

        const u = d.user || {};
        const p = d.persona || {};
        const theme = CARD_THEMES.find(t => t.id === themeId) || CARD_THEMES[0];
        const [r1, g1, b1] = hexToRgb(theme.from || p.color || '#2f6bff');
        const [r2, g2, b2] = hexToRgb(theme.to || p.color_2 || '#0b2a6b');

        // Sfondo: sfumatura diagonale piu' un alone dietro all'avatar.
        const bg = ctx.createLinearGradient(0, 0, CARD_W, CARD_H);
        bg.addColorStop(0, `rgb(${r1},${g1},${b1})`);
        bg.addColorStop(0.55, `rgb(${Math.round(r2 * 0.9)},${Math.round(g2 * 0.9)},${Math.round(b2 * 0.9)})`);
        bg.addColorStop(1, '#05070d');
        ctx.fillStyle = bg;
        ctx.fillRect(0, 0, CARD_W, CARD_H);

        const glow = ctx.createRadialGradient(CARD_W / 2, 400, 40, CARD_W / 2, 400, 520);
        glow.addColorStop(0, `rgba(255,255,255,0.20)`);
        glow.addColorStop(1, 'rgba(255,255,255,0)');
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, CARD_W, CARD_H);

        // Poppins e' caricato dalla pagina: senza attendere i font il canvas
        // ripiegherebbe sul sans-serif di sistema.
        try { await document.fonts.ready; } catch (_) {}

        ctx.textAlign = 'center';

        const cx = CARD_W / 2;

        // Scorciatoie sulle sezioni del payload usate qui sotto.
        const t = d.time || {};
        const g = d.gacha || {};
        const so = d.social || {};
        const a = d.achievements || {};
        const co = d.collection || {};

        // Le due parti opzionali vanno note prima di impaginare: la loro
        // presenza decide gli spazi verticali di tutto il resto.
        const personaIcon = await loadImage(p.image);
        const chip = co.most_pulled;

        // Icona dell'archetipo e riquadro del personaggio possono mancare.
        // Quando succede, lo spazio che avrebbero occupato si ridistribuisce
        // fra gli stacchi principali: altrimenti si accumulerebbe tutto in
        // fondo e la card resterebbe sbilanciata verso l'alto.
        const slack = (personaIcon ? 0 : 130) + (chip ? 0 : 132);
        const share = Math.round(slack / 3);

        let y = 104 + share;

        // Intestazione
        ctx.fillStyle = 'rgba(255,255,255,0.72)';
        ctx.font = '600 30px Poppins, sans-serif';
        ctx.letterSpacing = '6px';
        ctx.fillText('CRIPSUM REWIND\u2122', cx, y);
        ctx.letterSpacing = '0px';

        // Avatar, ritagliato in cerchio con anello
        const radius = 126;
        y += 64 + radius;                       // centro del cerchio
        const avatar = await loadImage(u.avatar);

        ctx.save();
        ctx.beginPath();
        ctx.arc(cx, y, radius, 0, Math.PI * 2);
        ctx.closePath();
        ctx.clip();
        if (avatar) {
            ctx.drawImage(avatar, cx - radius, y - radius, radius * 2, radius * 2);
        } else {
            ctx.fillStyle = 'rgba(255,255,255,0.16)';
            ctx.fillRect(cx - radius, y - radius, radius * 2, radius * 2);
        }
        ctx.restore();

        ctx.beginPath();
        ctx.arc(cx, y, radius + 6, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(255,255,255,0.85)';
        ctx.lineWidth = 5;
        ctx.stroke();

        y += radius;                            // bordo inferiore dell'avatar

        // Nome e username
        y += 76 + share;
        ctx.fillStyle = '#ffffff';
        ctx.font = '800 58px Poppins, sans-serif';
        ctx.fillText(fitText(ctx, u.display_name || u.username || '', CARD_W - CARD_PAD * 2), cx, y);

        y += 46;
        ctx.fillStyle = 'rgba(255,255,255,0.66)';
        ctx.font = '500 32px Poppins, sans-serif';
        ctx.fillText('@' + (u.username || ''), cx, y);

        // Icona dell'archetipo, se ne esiste una disegnata
        if (personaIcon) {
            const iconSize = 100;
            y += 30;
            ctx.drawImage(personaIcon, cx - iconSize / 2, y, iconSize, iconSize);
            y += iconSize;
        }

        // Nome dell'archetipo. Senza l'icona sopra serve piu' respiro:
        // il carattere e' grosso e appiccicato all'username stava stretto.
        y += personaIcon ? 66 : 86;
        const persona = (lang === 'en' ? p.name_en : p.name_it) || '';
        ctx.fillStyle = '#ffffff';
        ctx.font = '900 66px Poppins, sans-serif';
        ctx.fillText(fitText(ctx, persona.toUpperCase(), CARD_W - CARD_PAD * 2), cx, y);

        // Sei riquadri di statistiche
        const cells = [
            [humanDuration(t.seconds), T.timeKicker],
            [num(t.days_active), T.timeDays],
            [num(t.longest_streak), T.timeStreak],
            [num(g.pulls), T.gachaTitle],
            [num(so.msg_total), T.socTitle],
            [num(a.total_unlocked || a.unlocked_in_period), T.achKicker]
        ];

        const cellW = (CARD_W - CARD_PAD * 2 - CARD_CELL_GAP * 2) / 3;
        const gridY = y + 44 + share;

        cells.forEach((cell, i) => {
            const col = i % 3;
            const row = Math.floor(i / 3);
            const x = CARD_PAD + col * (cellW + CARD_CELL_GAP);
            const cellY = gridY + row * (CARD_CELL_H + CARD_CELL_GAP);

            ctx.fillStyle = 'rgba(255,255,255,0.10)';
            roundRect(ctx, x, cellY, cellW, CARD_CELL_H, 26);
            ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,0.18)';
            ctx.lineWidth = 2;
            ctx.stroke();

            ctx.fillStyle = '#ffffff';
            ctx.font = '800 44px Poppins, sans-serif';
            ctx.fillText(fitText(ctx, cell[0], cellW - 24), x + cellW / 2, cellY + 72);

            ctx.fillStyle = 'rgba(255,255,255,0.62)';
            ctx.font = '600 20px Poppins, sans-serif';
            ctx.fillText(fitText(ctx, String(cell[1]).toUpperCase(), cellW - 20), x + cellW / 2, cellY + 110);
        });

        y = gridY + CARD_CELL_H * 2 + CARD_CELL_GAP;   // fondo della griglia

        // Personaggio più trovato, se c'è
        if (chip) {
            const chipImg = await loadImage(chip.img_url);
            const boxY = y + 22;

            ctx.fillStyle = 'rgba(255,255,255,0.10)';
            roundRect(ctx, CARD_PAD, boxY, CARD_W - CARD_PAD * 2, CARD_CHIP_H, 30);
            ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,0.18)';
            ctx.lineWidth = 2;
            ctx.stroke();

            const imgSize = 80;
            const imgX = CARD_PAD + 20;
            if (chipImg) {
                ctx.save();
                roundRect(ctx, imgX, boxY + (CARD_CHIP_H - imgSize) / 2, imgSize, imgSize, 18);
                ctx.clip();
                ctx.drawImage(chipImg, imgX, boxY + (CARD_CHIP_H - imgSize) / 2, imgSize, imgSize);
                ctx.restore();
            }

            const textX = imgX + imgSize + 22;
            ctx.textAlign = 'left';
            ctx.fillStyle = 'rgba(255,255,255,0.6)';
            ctx.font = '600 19px Poppins, sans-serif';
            ctx.fillText(String(T.chrMost).toUpperCase(), textX, boxY + 46);
            ctx.fillStyle = '#ffffff';
            ctx.font = '700 34px Poppins, sans-serif';
            ctx.fillText(fitText(ctx, chip.nome || '', CARD_W - CARD_PAD - textX - 20), textX, boxY + 84);
            ctx.textAlign = 'center';
        }

        // Piede
        ctx.fillStyle = 'rgba(255,255,255,0.55)';
        ctx.font = '600 26px Poppins, sans-serif';
        ctx.fillText('cripsum.com', cx, CARD_H - CARD_FOOTER);
    }
    // ─────────────────────────────────────────────────────────
    //  MOTORE
    // ─────────────────────────────────────────────────────────

    class Story {
        constructor(data, root) {
            this.data = data;
            this.root = root;
            this.index = 0;
            this.paused = false;
            this.timer = null;
            this.startedAt = 0;
            this.remaining = SLIDE_MS;
            this.lastMusicSlide = -1;
            this.cardTheme = 'persona';
            this.fillRaf = null;
            this.autoplayOff = false;

            // Le schermate senza contenuto vengono scartate qui: il piano
            // arriva dal server, ma un renderer può comunque restituire
            // stringa vuota se un campo atteso manca.
            this.slides = (data.slides || ['intro', 'summary'])
                .map(name => ({ name, html: (RENDER[name] || (() => ''))(data) }))
                .filter(slide => slide.html !== '');

            this.build();
            this.mountAudio();
            this.bind();
            this.go(0);
        }

        /**
         * Avvia la colonna sonora.
         *
         * Il primo tentativo parte subito e quasi sempre viene rifiutato dal
         * browser: senza un gesto dell'utente la riproduzione automatica è
         * bloccata. Per questo restiamo in ascolto del primo tocco o della
         * prima pressione di un tasto e riproviamo allora, una volta sola.
         */
        mountAudio() {
            const slot = this.root.querySelector('[data-rw-audio-slot]');
            if (!slot) return;

            this.track = new Soundtrack();
            this.repaintAudio = mountAudioControl(slot, this.track);
            this.track.play();

            const kickstart = () => {
                if (!this.track.started && !this.track.failed) this.track.play();
                document.removeEventListener('pointerdown', kickstart);
                document.removeEventListener('keydown', kickstart);
            };
            document.addEventListener('pointerdown', kickstart, { once: false });
            document.addEventListener('keydown', kickstart, { once: false });

            // Uscendo dalla scheda la musica si ferma; tornando riprende.
            document.addEventListener('visibilitychange', () => {
                if (!this.track.started) return;
                if (document.visibilityState === 'visible') {
                    if (!this.track.muted) this.track.audio.play().catch(() => {});
                } else {
                    this.track.audio.pause();
                }
                this.repaintAudio?.();
            });

            window.addEventListener('pagehide', () => this.track.audio.pause());
        }

        build() {
            this.progress = this.root.querySelector('[data-rw-progress]');
            this.container = this.root.querySelector('[data-rw-slides]');
            this.bg = this.root.querySelector('[data-rw-bg]');
            this.hint = this.root.querySelector('[data-rw-hint]');

            this.progress.innerHTML = this.slides
                .map(() => '<span class="rw-progress__seg"><span class="rw-progress__fill"></span></span>')
                .join('');

            this.container.innerHTML = this.slides
                .map(slide => `<section class="rw-slide" data-slide="${esc(slide.name)}">${slide.html}</section>`)
                .join('');

            this.nodes = Array.from(this.container.querySelectorAll('.rw-slide'));
            this.segments = Array.from(this.progress.querySelectorAll('.rw-progress__seg'));
            this.nodes.forEach(stagger);
        }

        bind() {
            this.root.querySelector('[data-rw-prev]')?.addEventListener('click', () => this.go(this.index - 1));
            this.root.querySelector('[data-rw-playpause]')?.addEventListener('click', event => {
                event.stopPropagation();
                this.toggleAutoplay();
            });
            this.root.querySelector('[data-rw-next]')?.addEventListener('click', () => this.go(this.index + 1));

            this.container.addEventListener('click', event => {
                if (event.target.closest('[data-rw-next]')) this.go(this.index + 1);
                if (event.target.closest('[data-rw-replay]')) this.go(0);
                if (event.target.closest('[data-rw-share]')) this.share(event.target.closest('[data-rw-share]'));
                if (event.target.closest('[data-rw-download]')) this.download(event.target.closest('[data-rw-download]'));
                if (event.target.closest('[data-rw-theme]')) this.setCardTheme(event.target.closest('[data-rw-theme]'));
            });

            document.addEventListener('keydown', event => {
                if (event.key === 'ArrowRight' || event.key === ' ') { event.preventDefault(); this.go(this.index + 1); }
                if (event.key === 'ArrowLeft') { event.preventDefault(); this.go(this.index - 1); }
                if (event.key === 'Escape') window.location.href = `/${lang}/home`;
            });

            // Tenere premuto mette in pausa, come nelle storie.
            const hold = () => this.pause();
            const release = () => this.resume();
            this.root.addEventListener('pointerdown', hold);
            this.root.addEventListener('pointerup', release);
            this.root.addEventListener('pointercancel', release);

            // Swipe orizzontale.
            let startX = 0;
            this.root.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
            this.root.addEventListener('touchend', e => {
                const delta = e.changedTouches[0].clientX - startX;
                if (Math.abs(delta) > 60) this.go(this.index + (delta < 0 ? 1 : -1));
            }, { passive: true });

            document.addEventListener('visibilitychange', () => {
                document.visibilityState === 'visible' ? this.resume() : this.pause();
            });
        }

        go(index) {
            if (index < 0) index = 0;
            if (index >= this.slides.length) index = this.slides.length - 1;

            this.index = index;
            const slide = this.slides[index];

            this.nodes.forEach((node, i) => node.classList.toggle('is-active', i === index));

            // Una schermata con dei pulsanti non scorre via da sola: sarebbe
            // il modo migliore per far sparire "Condividi" mentre qualcuno lo
            // sta per premere. Vale per la copertina, per il riepilogo e per
            // la card, senza doverle elencare una per una.
            const isLast = index === this.slides.length - 1;
            const hasActions = !!this.nodes[index].querySelector('.rw-actions');
            const willBeStatic = hasActions || isLast || reduceMotion || this.autoplayOff;

            // Il segmento corrente va sempre riazzerato, anche tornando
            // indietro: altrimenti si porta dietro il 100% guadagnato al
            // primo passaggio e la barra mostra due posizioni piene, come se
            // ci fossero due schermate correnti.
            //
            // Su una schermata che non avanza da sola non c'è niente da
            // scandire, quindi il suo segmento resta pieno: sei arrivato.
            const willTick = !willBeStatic;

            // Prima di ridipingere: qualsiasi riempimento ancora in coda
            // appartiene alla schermata precedente.
            if (this.fillRaf) { cancelAnimationFrame(this.fillRaf); this.fillRaf = null; }

            this.segments.forEach((seg, i) => {
                const fill = seg.querySelector('.rw-progress__fill');
                seg.classList.toggle('is-done', i < index);

                fill.style.transition = 'none';
                if (i < index) {
                    fill.style.width = '100%';
                } else if (i > index) {
                    fill.style.width = '0';
                } else {
                    fill.style.width = willTick ? '0' : '100%';
                }
            });

            const theme = THEMES[slide.name] || THEMES.intro;
            this.bg.style.setProperty('--rw-slide-1', theme[0]);
            this.bg.style.setProperty('--rw-slide-2', theme[1]);

            this.animate(this.nodes[index]);
            this.paintCard(this.nodes[index]);
            this.hint?.classList.toggle('is-hidden', index > 0);

            if (willBeStatic) {
                this.stopTimer();
            } else {
                this.startTimer();
            }

            this.rotateMusic(index);

            if (navigator.vibrate && !reduceMotion) navigator.vibrate(8);
        }

        /** Fa partire i numeri, le barre e l'istogramma della schermata. */
        animate(node) {
            node.querySelectorAll('[data-count]').forEach(target => {
                const final = Number(target.dataset.count || 0);
                const small = target.querySelector('small');
                const suffix = small ? small.outerHTML : '';

                if (reduceMotion || final <= 0) {
                    target.innerHTML = num(final) + suffix;
                    return;
                }

                const duration = 1400;
                const started = performance.now();
                const step = now => {
                    const progress = Math.min(1, (now - started) / duration);
                    // Decelerazione: i numeri grossi sembrano più "pesanti".
                    const eased = 1 - Math.pow(1 - progress, 3);
                    target.innerHTML = num(Math.round(final * eased)) + suffix;
                    if (progress < 1) requestAnimationFrame(step);
                };
                requestAnimationFrame(step);
            });

            node.querySelectorAll('.rw-bar__fill').forEach(bar => {
                bar.style.width = '0';
                requestAnimationFrame(() => { bar.style.width = bar.dataset.w + '%'; });
            });

            node.querySelectorAll('.rw-clock__bar').forEach(bar => {
                bar.style.height = '0';
                requestAnimationFrame(() => { bar.style.height = bar.dataset.h + '%'; });
            });
        }

        /**
         * Ferma o riprende l'avanzamento automatico.
         *
         * Diverso dalla pausa che si ottiene tenendo premuto: quella dura
         * quanto il dito, questa resta finche' non la si toglie. Serve a chi
         * vuole guardarsi una schermata con calma senza dover tenere premuto.
         */
        toggleAutoplay() {
            this.autoplayOff = !this.autoplayOff;

            const button = this.root.querySelector('[data-rw-playpause]');
            if (button) {
                button.classList.toggle('is-paused', this.autoplayOff);
                button.setAttribute('aria-pressed', this.autoplayOff ? 'true' : 'false');
                button.setAttribute('aria-label', this.autoplayOff ? T.playResume : T.playPause);
                const icon = button.querySelector('i');
                if (icon) icon.className = this.autoplayOff ? 'fa-solid fa-play' : 'fa-solid fa-pause';
            }

            if (this.autoplayOff) {
                this.stopTimer();
                // Il segmento resta dov'e': congelarlo dice a colpo d'occhio
                // che il racconto e' fermo, non che e' finito.
                const fill = this.segments[this.index]?.querySelector('.rw-progress__fill');
                if (fill) {
                    const width = getComputedStyle(fill).width;
                    fill.style.transition = 'none';
                    fill.style.width = width;
                }
            } else {
                this.go(this.index);
            }
        }
        startTimer() {
            this.stopTimer();
            const duration = SLIDE_DURATIONS[this.slides[this.index]?.name] || SLIDE_MS;
            this.duration = duration;
            this.remaining = duration;
            this.startedAt = performance.now();

            const fill = this.segments[this.index]?.querySelector('.rw-progress__fill');
            if (fill) {
                fill.style.transition = 'none';
                fill.style.width = '0';
                // Il riempimento parte al frame successivo, altrimenti il
                // browser accorpa le due scritture e la transizione non si
                // vede. Il frame va pero' annullabile: cambiando schermata
                // in fretta, uno rimasto in coda tornerebbe a riempire il
                // segmento di prima dopo che go() lo ha gia' azzerato, ed e'
                // esattamente cosi' che la barra finiva per mostrare due
                // posizioni piene insieme.
                this.fillRaf = requestAnimationFrame(() => {
                    this.fillRaf = null;
            this.autoplayOff = false;
                    fill.style.transition = `width ${duration}ms linear`;
                    fill.style.width = '100%';
                });
            }

            this.timer = window.setTimeout(() => this.go(this.index + 1), duration);
        }

        stopTimer() {
            if (this.timer) { window.clearTimeout(this.timer); this.timer = null; }
            if (this.fillRaf) { cancelAnimationFrame(this.fillRaf); this.fillRaf = null; }
        }

        pause() {
            if (this.paused || !this.timer) return;
            this.paused = true;
            this.remaining -= performance.now() - this.startedAt;
            this.stopTimer();

            const fill = this.segments[this.index]?.querySelector('.rw-progress__fill');
            if (fill) {
                const width = getComputedStyle(fill).width;
                fill.style.transition = 'none';
                fill.style.width = width;
            }
        }

        resume() {
            if (!this.paused) return;
            this.paused = false;
            // Stessa regola di go(): dove ci sono pulsanti non si riparte.
            if (this.autoplayOff) return;
            if (this.index === this.slides.length - 1 || reduceMotion) return;
            if (this.nodes[this.index]?.querySelector('.rw-actions')) return;

            this.startedAt = performance.now();
            const left = Math.max(400, this.remaining);

            const fill = this.segments[this.index]?.querySelector('.rw-progress__fill');
            if (fill) {
                this.fillRaf = requestAnimationFrame(() => {
                    this.fillRaf = null;
            this.autoplayOff = false;
                    fill.style.transition = `width ${left}ms linear`;
                    fill.style.width = '100%';
                });
            }

            this.timer = window.setTimeout(() => this.go(this.index + 1), left);
        }

        /**
         * Fa cambiare canzone ogni tot schermate, all'inizio di una nuova.
         *
         * Con un brano solo per tutto il racconto la seconda meta' diventa
         * monotona; cambiando qui il taglio coincide con il cambio di
         * schermata e si nota molto meno.
         */
        rotateMusic(index) {
            if (!this.track || index <= this.lastMusicSlide) {
                // Tornando indietro non si cambia: sarebbe un salto continuo.
                if (index <= this.lastMusicSlide) this.lastMusicSlide = Math.max(index, this.lastMusicSlide);
                return;
            }

            this.lastMusicSlide = index;

            if (index > 0 && index % MUSIC_EVERY_SLIDES === 0) {
                this.track.next();
            }
        }
        /** Cambia i colori della card e la ridisegna. */
        setCardTheme(button) {
            const id = button.dataset.rwTheme;
            if (!id || id === this.cardTheme) return;

            this.cardTheme = id;

            button.parentElement.querySelectorAll('[data-rw-theme]')
                .forEach(b => b.classList.toggle('is-on', b === button));

            const canvas = this.root.querySelector('[data-rw-card]');
            if (!canvas) return;

            canvas.dataset.painted = '1';
            drawShareCard(canvas, this.data, id).catch(() => {
                canvas.dataset.painted = '';
            });
        }
        /**
         * Disegna la card la prima volta che la sua schermata si apre.
         * Ridisegnarla a ogni passaggio sarebbe lavoro sprecato: i dati non
         * cambiano mentre si guarda.
         */
        paintCard(node) {
            const canvas = node.querySelector('[data-rw-card]');
            if (!canvas || canvas.dataset.painted === '1') return;
            canvas.dataset.painted = '1';

            drawShareCard(canvas, this.data, this.cardTheme).catch(err => {
                console.warn('[Rewind] card non disegnata:', err);
                canvas.dataset.painted = '';
            });
        }

        /** Salva la card come PNG. */
        async download(button) {
            const canvas = this.root.querySelector('[data-rw-card]');
            if (!canvas) return;

            const label = button.querySelector('span');
            const original = label ? label.textContent : '';
            if (label) label.textContent = T.cardBusy;
            button.disabled = true;

            try {
                // Se la schermata e' stata aperta di corsa il disegno
                // potrebbe non essere ancora partito.
                if (canvas.dataset.painted !== '1') {
                    canvas.dataset.painted = '1';
                    await drawShareCard(canvas, this.data, this.cardTheme);
                }

                // JPEG e non PNG: su una sfumatura come questa il PNG pesa
                // circa un megabyte e mezzo contro i cento kilobyte del JPEG,
                // e a qualita' 0.92 la differenza non si vede. Conta, per
                // un'immagine pensata per finire in una chat.
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.92));
                if (!blob) throw new Error('toBlob vuoto');

                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `cripsum-rewind-${(this.data.user?.username || 'card')}.jpg`;
                document.body.appendChild(link);
                link.click();
                link.remove();
                // Il rilascio immediato interromperebbe il salvataggio su
                // qualche browser: meglio lasciargli un istante.
                setTimeout(() => URL.revokeObjectURL(url), 10000);

                toast(T.cardSaved);
            } catch (err) {
                toast(T.shareErr);
            } finally {
                if (label) label.textContent = original;
                button.disabled = false;
            }
        }
        async share(button) {
            const label = button.querySelector('[data-rw-share-label]');
            const token = window.CRIPSUM_CSRF || '';

            try {
                const response = await fetch('/api/rewind/share.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
                    body: JSON.stringify({ action: 'enable', period: this.data.period.key, lang })
                });

                const result = await response.json();
                if (!response.ok || !result.ok) throw new Error(result.error || 'fallito');

                await navigator.clipboard?.writeText(result.url).catch(() => {});
                if (label) label.textContent = T.shareOn;
                toast(T.copied);
            } catch (err) {
                toast(T.shareErr);
            }
        }
    }

    function toast(message) {
        let node = document.querySelector('.rw-toast');
        if (!node) {
            node = el('<div class="rw-toast" role="status" aria-live="polite"></div>');
            document.body.appendChild(node);
        }
        node.textContent = message;
        node.classList.add('is-visible');
        window.clearTimeout(toast.timer);
        toast.timer = window.setTimeout(() => node.classList.remove('is-visible'), 2600);
    }

    // ─────────────────────────────────────────────────────────
    //  AVVIO
    // ─────────────────────────────────────────────────────────

    async function boot() {
        const root = document.querySelector('[data-rw-root]');
        const bootScreen = document.querySelector('[data-rw-boot]');
        if (!root) return;

        let data = preloaded;

        if (!data) {
            try {
                const response = await fetch('/api/rewind/get.php?lang=' + lang, { credentials: 'include' });
                const result = await response.json();

                if (response.status === 503) throw new Error(T.unavailable);
                if (!response.ok || !result.ok) throw new Error(result.error || T.error);

                data = result.rewind;
            } catch (err) {
                if (bootScreen) {
                    bootScreen.innerHTML = `
                        <div>
                            <p class="rw-title" style="font-size:1.4rem">${esc(err.message || T.error)}</p>
                            <div class="rw-actions">
                                <button type="button" class="rw-btn" onclick="location.reload()">${esc(T.retry)}</button>
                                <a class="rw-btn rw-btn--ghost" href="/${lang}/home">${esc(T.back)}</a>
                            </div>
                        </div>`;
                }
                return;
            }
        }

        // Le immagini della schermata "colpo dell'anno" arrivano prima che
        // serva, così non compare un riquadro vuoto a metà racconto.
        const preloadUrl = data?.gacha?.best_pull?.img_url;
        if (preloadUrl) { const img = new Image(); img.src = preloadUrl; }

        new Story(data, root);
        bootScreen?.classList.add('is-gone');
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
