<?php
/**
 * Storia editoriale, in ordine cronologico, con id uguali nelle due lingue.
 * I ricordi del 2023 e la migrazione di giugno 2025 provengono dall'autore.
 * Le altre date indicano le tracce di sviluppo conservate in Git: non sono
 * conferme indipendenti della data di pubblicazione in produzione.
 * sources conserva i riferimenti per aggiornare il racconto; non viene mostrato.
 *
 * Capitolo: id, title, events. Evento: id, paragraphs.
 * Facoltativi: title, date (YYYY-MM-DD, YYYY-MM o YYYY), period, images, links.
 * Immagine: src, alt, caption, width, height. Link: href, label.
 * Testi senza HTML; URL locali con / iniziale. Aggiungere solo date documentate.
 */
return [
    'it' => [
        [
            'id' => 'year-2023',
            'title' => '2023',
            'events' => [
                [
                    'id' => 'google-sites',
                    'title' => 'Google Sites e la “subcultura”',
                    'paragraphs' => [
                        'Nel 2023 il sito era fatto su Google Sites. Nella pagina “Chi siamo” avevo messo, come foto profilo di Samarpreet, l’immagine di due uomini che si baciavano. La mia professoressa vide il sito e iniziò a dire che avevo creato una “subcultura”.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                    ],
                ],
                [
                    'id' => 'dark-shop-and-ban',
                    'title' => 'Il Dark Shop, TikTok e il ban',
                    'paragraphs' => [
                        'Poi aggiunsi il Dark Shop: un negozio finto dove, per il meme, si potevano “comprare” bambini, schiavi e tool strani. Pubblicai il sito su TikTok, il video diventò virale e il sito venne bannato.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                    ],
                ],
                [
                    'id' => 'github-pages',
                    'date' => '2023-11',
                    'period' => 'Novembre',
                    'title' => 'Il ritorno su GitHub Pages',
                    'paragraphs' => [
                        'Dopo qualche mese tornai, questa volta scrivendo il sito a mano in HTML, CSS e JavaScript e pubblicandolo su GitHub Pages. Ricreai le pagine della vecchia versione su Google Sites e iniziai ad aggiungere giochi, meme e nuove idee. Fu il primo vero inizio del sito scritto da me.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                        'git:b85318b0',
                        'git:af014d0f',
                    ],
                ],
            ],
        ],
        [
            'id' => 'year-2024',
            'title' => '2024',
            'events' => [
                [
                    'id' => 'tiktokpedia',
                    'date' => '2024-06',
                    'period' => 'Giugno',
                    'title' => 'Arriva la TikTokPedia',
                    'paragraphs' => [
                        'A giugno compare la TikTokPedia, inizialmente come pagina ancora da riempire. Nei giorni successivi cominciano ad arrivare immagini, descrizioni e personaggi: anche i meme di TikTok trovano il loro posto nel sito.',
                    ],
                    'sources' => [
                        'git:5db0b659',
                        'git:7a0760c9',
                        'git:217d9a60',
                    ],
                ],
                [
                    'id' => 'gambling',
                    'date' => '2024-07',
                    'period' => 'Luglio',
                    'title' => 'Il gambling',
                    'paragraphs' => [
                        'A luglio prende forma il gambling, con la slot machine e i suoi simboli.',
                    ],
                    'sources' => [
                        'git:8502ab42',
                        'git:9e992fc6',
                    ],
                ],
                [
                    'id' => 'first-redesign',
                    'date' => '2024-10',
                    'period' => 'Ottobre–novembre',
                    'title' => 'Una nuova grafica',
                    'paragraphs' => [
                        'Tra ottobre e novembre rifaccio la grafica di home, Edits, Chi siamo, negozio e altre pagine. Il sito continua a essere statico, ma comincia ad avere un aspetto più uniforme.',
                    ],
                    'sources' => [
                        'git:42571726',
                        'git:4267555b',
                        'git:f9b2704d',
                        'git:f8b9399a',
                    ],
                ],
            ],
        ],
        [
            'id' => 'year-2025',
            'title' => '2025',
            'events' => [
                [
                    'id' => 'first-lootboxes',
                    'date' => '2025-02-03',
                    'period' => '3 febbraio',
                    'title' => 'Le prime lootbox',
                    'paragraphs' => [
                        'Arrivano le lootbox: casse da aprire, personaggi da trovare e rarità diverse. Nei giorni successivi aggiungo l’inventario, nuovi personaggi, musiche e codici da riscattare. All’inizio la collezione viene salvata nel browser.',
                    ],
                    'sources' => [
                        'git:c3c24a78',
                        'git:79252848',
                        'git:70c0896e',
                        'git:b65bf441',
                    ],
                ],
                [
                    'id' => 'first-achievements',
                    'date' => '2025-02-22',
                    'period' => '22 febbraio',
                    'title' => 'Gli achievement',
                    'paragraphs' => [
                        'Iniziano i primi test degli achievement, seguiti dai popup di sblocco e dagli obiettivi collegati alle varie pagine. Visitare il sito, aprire casse e trovare personaggi diventa anche un modo per completare una collezione di traguardi.',
                    ],
                    'sources' => [
                        'git:f2d1358c',
                        'git:d325431c',
                        'git:1135d26c',
                    ],
                ],
                [
                    'id' => 'hostinger-php',
                    'date' => '2025-06',
                    'period' => 'Giugno',
                    'title' => 'Il passaggio a Hostinger',
                    'paragraphs' => [
                        'A giugno arriva il cambiamento più importante: passo da GitHub Pages a Hostinger e introduco PHP e database. Riscrivo completamente il sito. Da qui inizia la trasformazione che, aggiunta dopo aggiunta, lo porterà a diventare quello che è oggi.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                    ],
                ],
                [
                    'id' => 'accounts-and-chat',
                    'date' => '2025-07',
                    'period' => 'Luglio',
                    'title' => 'Account, profili e chat globale',
                    'paragraphs' => [
                        'Con la riscrittura arrivano registrazione, accesso, verifica email e profili modificabili con foto personali. Nei primi giorni di luglio aggiungo anche la ricerca utenti e la chat globale, con messaggi e risposte. Lootbox, inventario e achievement vengono progressivamente collegati agli account.',
                    ],
                    'sources' => [
                        'git:a1886bf0',
                        'git:871fdfe7',
                        'git:bd439fd7',
                        'git:1fa569b7',
                        'git:104f37f4',
                        'git:ed890696',
                        'git:bee903eb',
                    ],
                ],
                [
                    'id' => 'goonland',
                    'date' => '2025-07',
                    'period' => 'Luglio',
                    'title' => 'GoonLand',
                    'paragraphs' => [
                        'Nasce GoonLand, con il generatore di immagini e una sezione dedicata. Seguono classifiche e altre pagine; a dicembre si aggiungono archivio, museo e test.',
                    ],
                    'sources' => [
                        'git:548f90e2',
                        'git:2f4ddc53',
                        'git:f3ccfa69',
                    ],
                ],
                [
                    'id' => 'first-cripsumpedia',
                    'date' => '2025-12',
                    'period' => 'Dicembre',
                    'title' => 'La prima Cripsumpedia',
                    'paragraphs' => [
                        'A dicembre compare il nome Cripsumpedia nella navigazione e iniziano le prime pagine della wiki. Persone, eventi e meme del sito cominciano ad avere un archivio dedicato.',
                    ],
                    'sources' => [
                        'git:6f98bd2e',
                        'git:80f247f8',
                    ],
                ],
            ],
        ],
        [
            'id' => 'year-2026',
            'title' => '2026',
            'events' => [
                [
                    'id' => 'community-v2',
                    'date' => '2026-04',
                    'period' => 'Aprile',
                    'title' => 'Chat e contenuti della community',
                    'paragraphs' => [
                        'La chat globale viene rifatta, con GIF e reazioni. Anche Shitpost e Top Rimasti ricevono un nuovo sistema per pubblicare contenuti, commentare e reagire. Si allarga lo spazio per quello che condividono gli utenti.',
                    ],
                    'sources' => [
                        'git:89678ede',
                        'git:00dbd891',
                        'git:85e477c9',
                    ],
                ],
                [
                    'id' => 'first-duels',
                    'date' => '2026-04',
                    'period' => 'Aprile',
                    'title' => 'I primi Duelli',
                    'paragraphs' => [
                        'Arrivano i primi Duelli, con partite, azioni di gioco e classifica. Poco dopo aggiungo la possibilità di seguire le partite dal vivo e reagire da spettatore.',
                    ],
                    'sources' => [
                        'git:b5a84399',
                        'git:c95923ee',
                    ],
                ],
                [
                    'id' => 'gacha-missions',
                    'date' => '2026-05',
                    'period' => 'Maggio',
                    'title' => 'Gacha 2.0 e missioni',
                    'paragraphs' => [
                        'Rifaccio il sistema gacha e lootbox, aggiungendo anche cronologia e pull multipli. Arrivano le missioni giornaliere e settimanali, con progressi e ricompense. Nello stesso periodo aggiungo l’accesso con Google.',
                    ],
                    'sources' => [
                        'git:25ca7c00',
                        'git:31225904',
                        'git:cd2fd788',
                        'git:b5c8dd6e',
                        'git:8f95b8de',
                    ],
                ],
                [
                    'id' => 'custom-profiles-premium',
                    'date' => '2026-06',
                    'period' => 'Giugno',
                    'title' => 'Profili custom e Premium',
                    'paragraphs' => [
                        'I profili diventano sempre più personalizzabili: musica, embed, sfondi, effetti, nomi animati e alias. L’editor riceve anteprima e bozze. Arriva anche Premium, con funzioni aggiuntive, regali e ricompense giornaliere.',
                    ],
                    'sources' => [
                        'git:0d4bdc81',
                        'git:306b7f4c',
                        'git:daec6fcd',
                        'git:aeb1d44d',
                        'git:38823832',
                        'git:68b27d4c',
                        'git:c1de90bd',
                        'git:fd32eb15',
                    ],
                ],
                [
                    'id' => 'friends-and-inbox',
                    'date' => '2026-06',
                    'period' => 'Giugno',
                    'title' => 'Posta, amici e follower',
                    'paragraphs' => [
                        'Aggiungo la posta interna e il sistema social: richieste di amicizia, amici, follower e utenti seguiti. I profili diventano anche il punto da cui gestire i rapporti con gli altri utenti.',
                    ],
                    'sources' => [
                        'git:ccc36520',
                        'git:693507dc',
                    ],
                ],
                [
                    'id' => 'subway-surfers',
                    'date' => '2026-08',
                    'period' => 'Agosto',
                    'title' => 'Subway Surfers',
                    'paragraphs' => [
                        'Subway Surfers entra nel sito con selezione delle mappe e configurazione delle sfide. Seguono impostazioni personalizzabili, classifiche e la No-Coin Challenge.',
                    ],
                    'sources' => [
                        'git:cd51a54e',
                        'git:a9fcf7dc',
                        'git:464945fb',
                    ],
                ],
                [
                    'id' => 'rewind',
                    'date' => '2026-09-07',
                    'period' => '7 settembre',
                    'title' => 'Cripsum Rewind',
                    'paragraphs' => [
                        'Arriva Cripsum Rewind: un riepilogo dell’attività sul sito, con statistiche e una scheda da condividere.',
                    ],
                    'sources' => [
                        'git:de65c452',
                    ],
                ],
                [
                    'id' => 'pullspot-animespot',
                    'date' => '2026-09',
                    'period' => '8–9 settembre',
                    'title' => 'Pullspot e Animespot',
                    'paragraphs' => [
                        'In due giorni arrivano Pullspot e Animespot, altri due giochi con audio da riconoscere e statistiche dedicate.',
                    ],
                    'sources' => [
                        'git:e6ce0999',
                        'git:f8851762',
                    ],
                ],
                [
                    'id' => 'discord-bot',
                    'date' => '2026-09',
                    'period' => 'Settembre',
                    'title' => 'Il sito e Discord',
                    'paragraphs' => [
                        'L’integrazione con Discord si amplia: il bot può accedere a profili, inventario e achievement del sito. Si aggiungono ticket, moderazione e giveaway; a ottobre arriva anche un pannello dedicato per gestirlo dal sito.',
                    ],
                    'sources' => [
                        'git:33be3ab3',
                        'git:da231e38',
                        'git:f19f235f',
                    ],
                ],
                [
                    'id' => 'shop-gacha-esports',
                    'date' => '2026-09',
                    'period' => 'Settembre',
                    'title' => 'Shop, gacha e Team OHPY',
                    'paragraphs' => [
                        'Riorganizzo shop, prodotti e download. Il gacha riceve una nuova gestione di banner, collezione e frammenti. Anche il Team OHPY ha pagine dedicate alla squadra e ai player, con spazio per le clip.',
                    ],
                    'sources' => [
                        'git:0a38cb23',
                        'git:70c6e465',
                        'git:f22405f6',
                        'git:90b6b33c',
                        'git:25dcabe9',
                    ],
                ],
                [
                    'id' => 'site-theme',
                    'date' => '2026-10',
                    'period' => 'Ottobre',
                    'title' => 'La grafica attuale',
                    'paragraphs' => [
                        'Introduco un nuovo tema e lo estendo alle varie sezioni. Fondo scuro neutro, meno effetti decorativi e componenti condivisi danno al sito l’aspetto che ha adesso.',
                    ],
                    'sources' => [
                        'git:bce1dd85',
                        'git:89d2f601',
                        'git:4c96faef',
                        'git:2a1e0087',
                    ],
                ],
                [
                    'id' => 'wiki-and-bio',
                    'date' => '2026-10',
                    'period' => '17–18 ottobre',
                    'title' => 'Cripsumpedia si apre, nasce Bio',
                    'paragraphs' => [
                        'La Cripsumpedia riceve cronologia delle revisioni, ricerca e un sistema per proporre voci e modifiche da approvare. In parallelo prende forma Bio, con pagine personali, editor e accesso collegato all’account Cripsum.',
                    ],
                    'sources' => [
                        'git:a68e8b71',
                        'git:44266b63',
                        'git:65d419e1',
                        'git:951dde34',
                        'git:9295be84',
                        'git:3d998fd6',
                    ],
                ],
                [
                    'id' => 'duels-2',
                    'date' => '2026-10',
                    'period' => '19–22 ottobre',
                    'title' => 'Duelli 2.0 e la Torre',
                    'paragraphs' => [
                        'Riscrivo i Duelli con ruoli, abilità e regole dei personaggi. Arrivano squadra, arena, stagioni e sfide tra amici. Il 22 ottobre si aggiunge la Torre, con scalata, ricompense e badge.',
                    ],
                    'sources' => [
                        'git:b34502f8',
                        'git:04a8d174',
                        'git:39fa7d92',
                        'git:4a04dba3',
                        'git:85c1e520',
                    ],
                ],
            ],
        ],
    ],
    'en' => [
        [
            'id' => 'year-2023',
            'title' => '2023',
            'events' => [
                [
                    'id' => 'google-sites',
                    'title' => 'Google Sites and the “subculture”',
                    'paragraphs' => [
                        'In 2023, the site was built on Google Sites. On the “About us” page, I had used a picture of two men kissing as Samarpreet’s profile picture. My teacher saw the site and started saying that I had created a “subculture”.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                    ],
                ],
                [
                    'id' => 'dark-shop-and-ban',
                    'title' => 'The Dark Shop, TikTok and the ban',
                    'paragraphs' => [
                        'Then I added the Dark Shop: a fake store where, as a meme, you could “buy” children, slaves and weird tools. I posted the site on TikTok, the video went viral and the site got banned.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                    ],
                ],
                [
                    'id' => 'github-pages',
                    'date' => '2023-11',
                    'period' => 'November',
                    'title' => 'Back on GitHub Pages',
                    'paragraphs' => [
                        'A few months later I came back, this time writing the site by hand in HTML, CSS and JavaScript and hosting it on GitHub Pages. I recreated the pages from the old Google Sites version and began adding games, memes and new ideas. This was the real beginning of the site I coded myself.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                        'git:b85318b0',
                        'git:af014d0f',
                    ],
                ],
            ],
        ],
        [
            'id' => 'year-2024',
            'title' => '2024',
            'events' => [
                [
                    'id' => 'tiktokpedia',
                    'date' => '2024-06',
                    'period' => 'June',
                    'title' => 'TikTokPedia arrives',
                    'paragraphs' => [
                        'TikTokPedia appears in June, initially as a placeholder. Images, descriptions and characters follow over the next few days, giving TikTok memes their own place on the site.',
                    ],
                    'sources' => [
                        'git:5db0b659',
                        'git:7a0760c9',
                        'git:217d9a60',
                    ],
                ],
                [
                    'id' => 'gambling',
                    'date' => '2024-07',
                    'period' => 'July',
                    'title' => 'Gambling',
                    'paragraphs' => [
                        'In July, the gambling section takes shape, with a slot machine and its symbols.',
                    ],
                    'sources' => [
                        'git:8502ab42',
                        'git:9e992fc6',
                    ],
                ],
                [
                    'id' => 'first-redesign',
                    'date' => '2024-10',
                    'period' => 'October–November',
                    'title' => 'A new look',
                    'paragraphs' => [
                        'Between October and November, I redesign the home page, Edits, About us, the store and other pages. The site is still static, but its appearance becomes more consistent.',
                    ],
                    'sources' => [
                        'git:42571726',
                        'git:4267555b',
                        'git:f9b2704d',
                        'git:f8b9399a',
                    ],
                ],
            ],
        ],
        [
            'id' => 'year-2025',
            'title' => '2025',
            'events' => [
                [
                    'id' => 'first-lootboxes',
                    'date' => '2025-02-03',
                    'period' => '3 February',
                    'title' => 'The first lootboxes',
                    'paragraphs' => [
                        'Lootboxes arrive: crates to open, characters to find and different rarities. Over the following days, I add the inventory, more characters, music and redeem codes. At first, the collection is saved in the browser.',
                    ],
                    'sources' => [
                        'git:c3c24a78',
                        'git:79252848',
                        'git:70c0896e',
                        'git:b65bf441',
                    ],
                ],
                [
                    'id' => 'first-achievements',
                    'date' => '2025-02-22',
                    'period' => '22 February',
                    'title' => 'Achievements',
                    'paragraphs' => [
                        'The first achievement tests begin, followed by unlock popups and goals tied to different pages. Visiting the site, opening crates and finding characters also become ways to build a collection of milestones.',
                    ],
                    'sources' => [
                        'git:f2d1358c',
                        'git:d325431c',
                        'git:1135d26c',
                    ],
                ],
                [
                    'id' => 'hostinger-php',
                    'date' => '2025-06',
                    'period' => 'June',
                    'title' => 'Moving to Hostinger',
                    'paragraphs' => [
                        'June brings the biggest change: I move from GitHub Pages to Hostinger and introduce PHP and a database. I rewrite the entire site. This starts the transformation that, one addition at a time, will turn it into what it is today.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                    ],
                ],
                [
                    'id' => 'accounts-and-chat',
                    'date' => '2025-07',
                    'period' => 'July',
                    'title' => 'Accounts, profiles and global chat',
                    'paragraphs' => [
                        'The rewrite brings registration, login, email verification and editable profiles with personal pictures. In early July, I also add user search and global chat, with messages and replies. Lootboxes, inventory and achievements are gradually connected to accounts.',
                    ],
                    'sources' => [
                        'git:a1886bf0',
                        'git:871fdfe7',
                        'git:bd439fd7',
                        'git:1fa569b7',
                        'git:104f37f4',
                        'git:ed890696',
                        'git:bee903eb',
                    ],
                ],
                [
                    'id' => 'goonland',
                    'date' => '2025-07',
                    'period' => 'July',
                    'title' => 'GoonLand',
                    'paragraphs' => [
                        'GoonLand begins, with an image generator and its own section. Leaderboards and more pages follow; an archive, museum and test are added in December.',
                    ],
                    'sources' => [
                        'git:548f90e2',
                        'git:2f4ddc53',
                        'git:f3ccfa69',
                    ],
                ],
                [
                    'id' => 'first-cripsumpedia',
                    'date' => '2025-12',
                    'period' => 'December',
                    'title' => 'The first Cripsumpedia',
                    'paragraphs' => [
                        'In December, Cripsumpedia appears in the navigation and the first wiki pages take shape. People, events and memes from the site begin to have a dedicated archive.',
                    ],
                    'sources' => [
                        'git:6f98bd2e',
                        'git:80f247f8',
                    ],
                ],
            ],
        ],
        [
            'id' => 'year-2026',
            'title' => '2026',
            'events' => [
                [
                    'id' => 'community-v2',
                    'date' => '2026-04',
                    'period' => 'April',
                    'title' => 'Chat and community content',
                    'paragraphs' => [
                        'Global chat is rebuilt, with GIFs and reactions. Shitpost and Top Rimasti also get a new system for publishing content, commenting and reacting. There is more room for what users share.',
                    ],
                    'sources' => [
                        'git:89678ede',
                        'git:00dbd891',
                        'git:85e477c9',
                    ],
                ],
                [
                    'id' => 'first-duels',
                    'date' => '2026-04',
                    'period' => 'April',
                    'title' => 'The first Duels',
                    'paragraphs' => [
                        'The first Duels arrive, with matches, game actions and a leaderboard. Soon after, I add live spectating and spectator reactions.',
                    ],
                    'sources' => [
                        'git:b5a84399',
                        'git:c95923ee',
                    ],
                ],
                [
                    'id' => 'gacha-missions',
                    'date' => '2026-05',
                    'period' => 'May',
                    'title' => 'Gacha 2.0 and missions',
                    'paragraphs' => [
                        'I rebuild the gacha and lootbox system, adding pull history and multi-pulls. Daily and weekly missions arrive, with progress tracking and rewards. Around the same time, I add Google login.',
                    ],
                    'sources' => [
                        'git:25ca7c00',
                        'git:31225904',
                        'git:cd2fd788',
                        'git:b5c8dd6e',
                        'git:8f95b8de',
                    ],
                ],
                [
                    'id' => 'custom-profiles-premium',
                    'date' => '2026-06',
                    'period' => 'June',
                    'title' => 'Custom profiles and Premium',
                    'paragraphs' => [
                        'Profiles become more customizable, with music, embeds, backgrounds, effects, animated names and aliases. The editor gets previews and drafts. Premium also arrives, with extra features, gifts and daily rewards.',
                    ],
                    'sources' => [
                        'git:0d4bdc81',
                        'git:306b7f4c',
                        'git:daec6fcd',
                        'git:aeb1d44d',
                        'git:38823832',
                        'git:68b27d4c',
                        'git:c1de90bd',
                        'git:fd32eb15',
                    ],
                ],
                [
                    'id' => 'friends-and-inbox',
                    'date' => '2026-06',
                    'period' => 'June',
                    'title' => 'Inbox, friends and followers',
                    'paragraphs' => [
                        'I add an internal inbox and social features: friend requests, friends, followers and following. Profiles also become a place to manage connections with other users.',
                    ],
                    'sources' => [
                        'git:ccc36520',
                        'git:693507dc',
                    ],
                ],
                [
                    'id' => 'subway-surfers',
                    'date' => '2026-08',
                    'period' => 'August',
                    'title' => 'Subway Surfers',
                    'paragraphs' => [
                        'Subway Surfers joins the site with map selection and challenge settings. Custom settings, leaderboards and the No-Coin Challenge follow.',
                    ],
                    'sources' => [
                        'git:cd51a54e',
                        'git:a9fcf7dc',
                        'git:464945fb',
                    ],
                ],
                [
                    'id' => 'rewind',
                    'date' => '2026-09-07',
                    'period' => '7 September',
                    'title' => 'Cripsum Rewind',
                    'paragraphs' => [
                        'Cripsum Rewind arrives: a recap of activity on the site, with statistics and a shareable card.',
                    ],
                    'sources' => [
                        'git:de65c452',
                    ],
                ],
                [
                    'id' => 'pullspot-animespot',
                    'date' => '2026-09',
                    'period' => '8–9 September',
                    'title' => 'Pullspot and Animespot',
                    'paragraphs' => [
                        'Pullspot and Animespot arrive over two days, adding two more games with audio to identify and their own statistics.',
                    ],
                    'sources' => [
                        'git:e6ce0999',
                        'git:f8851762',
                    ],
                ],
                [
                    'id' => 'discord-bot',
                    'date' => '2026-09',
                    'period' => 'September',
                    'title' => 'The site and Discord',
                    'paragraphs' => [
                        'Discord integration expands: the bot can access site profiles, inventories and achievements. Tickets, moderation and giveaways are added; in October, a dedicated panel makes it possible to manage the bot from the site.',
                    ],
                    'sources' => [
                        'git:33be3ab3',
                        'git:da231e38',
                        'git:f19f235f',
                    ],
                ],
                [
                    'id' => 'shop-gacha-esports',
                    'date' => '2026-09',
                    'period' => 'September',
                    'title' => 'Shop, gacha and Team OHPY',
                    'paragraphs' => [
                        'I reorganize the shop, products and downloads. Gacha gets new systems for banners, collections and fragments. Team OHPY also gets dedicated team and player pages, with room for clips.',
                    ],
                    'sources' => [
                        'git:0a38cb23',
                        'git:70c6e465',
                        'git:f22405f6',
                        'git:90b6b33c',
                        'git:25dcabe9',
                    ],
                ],
                [
                    'id' => 'site-theme',
                    'date' => '2026-10',
                    'period' => 'October',
                    'title' => 'The current look',
                    'paragraphs' => [
                        'I introduce a new theme and extend it across the sections. A neutral dark background, fewer decorative effects and shared components give the site its current appearance.',
                    ],
                    'sources' => [
                        'git:bce1dd85',
                        'git:89d2f601',
                        'git:4c96faef',
                        'git:2a1e0087',
                    ],
                ],
                [
                    'id' => 'wiki-and-bio',
                    'date' => '2026-10',
                    'period' => '17–18 October',
                    'title' => 'Cripsumpedia opens up, Bio begins',
                    'paragraphs' => [
                        'Cripsumpedia gets revision history, search and a system for submitting entries and edits for approval. At the same time, Bio takes shape, with personal pages, an editor and login connected to the Cripsum account.',
                    ],
                    'sources' => [
                        'git:a68e8b71',
                        'git:44266b63',
                        'git:65d419e1',
                        'git:951dde34',
                        'git:9295be84',
                        'git:3d998fd6',
                    ],
                ],
                [
                    'id' => 'duels-2',
                    'date' => '2026-10',
                    'period' => '19–22 October',
                    'title' => 'Duels 2.0 and the Tower',
                    'paragraphs' => [
                        'I rewrite Duels with character roles, abilities and rules. Teams, the arena, seasons and friend challenges arrive. On 22 October, the Tower is added, with climbing, rewards and badges.',
                    ],
                    'sources' => [
                        'git:b34502f8',
                        'git:04a8d174',
                        'git:39fa7d92',
                        'git:4a04dba3',
                        'git:85c1e520',
                    ],
                ],
            ],
        ],
    ],
];
