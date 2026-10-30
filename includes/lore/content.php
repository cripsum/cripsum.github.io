<?php
/**
 * Storia editoriale in ordine cronologico, con id uguali fra IT ed EN.
 * Ricordi di Google Sites e migrazione di giugno 2025: racconto dell'autore.
 * Altre date: tracce di sviluppo in Git, non conferme di pubblicazione.
 * Il ramo origin/backup-disastro-pre-php conserva i vecchi esperimenti HTML.
 * sources conserva i riferimenti senza mostrarli nella pagina.
 *
 * Ogni evento accetta images: percorsi locali o array con src, alt, caption.
 * Le dimensioni delle immagini vengono rilevate dal file.
 * In alternativa, aggiungere foto in img/lore/ID-EVENTO/: appaiono da sole.
 * Vedi img/lore/README.md per esempi e identificativi degli eventi.
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
                    'images' => [],
                ],
                [
                    'id' => 'dark-shop-and-ban',
                    'title' => 'Il Dark Shop, TikTok e il ban',
                    'paragraphs' => [
                        'Poi aggiunsi il Dark Shop: un negozio finto dove, per il meme, si potevano “comprare” bambini, schiavi e tool strani. Pubblicai su TikTok un video del sito e diventò virale.',
                        'Nei commenti alcuni iniziarono ad accusarmi di aver creato un honeypot, cioè un sito-esca per attirare le persone e raccogliere informazioni su di loro. Alcuni segnalarono il sito, che venne poi bannato.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                    ],
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'early-downloads',
                    'date' => '2023-11-27',
                    'period' => '27 novembre',
                    'title' => 'Italiano, inglese, osu! e Fortnite',
                    'paragraphs' => [
                        'Sistemo il cambio lingua e aggiungo le prime pagine dedicate a osu! e Fortnite. La versione scritta a mano comincia ad avere le sue sezioni, anche se molte sono ancora vuote.',
                    ],
                    'sources' => [
                        'git:d4633565',
                        'git:2f62cec2',
                        'git:7000de36',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'wise-tree-home',
                    'date' => '2023-11-28',
                    'period' => '28 novembre',
                    'title' => 'Il Wise Mystical Tree e lo Spinjitzu',
                    'paragraphs' => [
                        'Completo la prima home: Cripsum re del Congo, il Wise Mystical Tree, Felicità, Tristezza e Stupore. C’è anche Jay che propone di imparare lo Spinjitzu, insieme al link per i “V-bucks gratis” che porta a un rickroll.',
                    ],
                    'sources' => [
                        'git:0a9e2e2c',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'quandel57',
                    'date' => '2023-12-02',
                    'period' => '2 dicembre',
                    'title' => 'Quandel57',
                    'paragraphs' => [
                        'Quandel57 ottiene una pagina tutta sua, con il meme di TikTok e l’audio “Screaming in Public Restrooms”. È uno dei primi personaggi ad avere una sezione dedicata.',
                    ],
                    'sources' => [
                        'git:56c676eb',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'first-shitpost',
                    'date' => '2023-12-05',
                    'period' => '5 dicembre',
                    'title' => 'Il primo Shitpost',
                    'paragraphs' => [
                        'Riempio Shitpost di immagini e GIF: beans, scheletri, Dame Tu Cosita e altri meme. È ancora una raccolta scelta e inserita a mano, molto prima dei post pubblicati dagli utenti.',
                    ],
                    'sources' => [
                        'git:e60a5ce3',
                        'git:d7340ba9',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'fake-store',
                    'date' => '2023-12-15',
                    'period' => '15–20 dicembre',
                    'title' => 'Il negozio e il checkout finto',
                    'paragraphs' => [
                        'Aggiungo un negozio con prodotti come iPhone 20, PS6, RTX 4090, Renegade Raider e la tastiera a due tasti per osu!. Il 20 dicembre arriva anche il checkout: fa parte dello scherzo, non di un vero servizio di vendita.',
                    ],
                    'sources' => [
                        'git:8650fc12',
                        'git:51325314',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'minecraft-alphabet',
                    'date' => '2023-12-16',
                    'period' => '16 dicembre',
                    'title' => 'La home in alfabeto Minecraft',
                    'paragraphs' => [
                        'Creo una versione della home scritta con i simboli dell’alfabeto del tavolo degli incantesimi di Minecraft. Anche il titolo, i meme e i collegamenti vengono trasformati.',
                    ],
                    'sources' => [
                        'git:04f063e7',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'first-edits',
                    'date' => '2023-12-23',
                    'period' => '23 dicembre',
                    'title' => 'Nasce la pagina Edits',
                    'paragraphs' => [
                        'Gli edit ricevono una pagina dedicata, con video incorporati da TikTok. La raccolta continuerà a crescere e a cambiare piattaforma quando i vecchi embed smetteranno di funzionare.',
                    ],
                    'sources' => [
                        'git:5917a53b',
                        'git:e9b73fda',
                        'git:1b3dbdda',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'first-merch',
                    'date' => '2023-12-26',
                    'period' => '26 dicembre',
                    'title' => 'Il merch',
                    'paragraphs' => [
                        'La pagina merch prende forma con vestiti e accessori: magliette, felpe, cappellini e altri prodotti. Anche questa sezione fa parte dei primi esperimenti del sito statico.',
                    ],
                    'sources' => [
                        'git:ec239033',
                    ],
                    'images' => [],
                ],
            ],
        ],
        [
            'id' => 'year-2024',
            'title' => '2024',
            'events' => [
                [
                    'id' => 'download-music',
                    'date' => '2024-01-07',
                    'period' => '7 gennaio',
                    'title' => 'Download con la musica',
                    'paragraphs' => [
                        'Aggiorno le pagine di download per Fortnite e osu! e aggiungo la riproduzione musicale alla sezione. Anche scaricare qualcosa diventa una scusa per mettere audio e pulsanti nel sito.',
                    ],
                    'sources' => [
                        'git:a4da46d8',
                        'git:3937918c',
                        'git:965b1e88',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'elevator-loading',
                    'date' => '2024-05-19',
                    'period' => '19 maggio',
                    'title' => 'Il caricamento con la musica da ascensore',
                    'paragraphs' => [
                        'La pagina TikTokPedia ha un’attesa di caricamento con musica da ascensore. Porto il tempo a dieci secondi: anche prima di entrare nella pagina c’è già un troll.',
                    ],
                    'sources' => [
                        'git:6b93ead2',
                        'git:db47e71c',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'tiktokpedia',
                    'date' => '2024-06',
                    'period' => 'Giugno',
                    'title' => 'La TikTokPedia si riempie',
                    'paragraphs' => [
                        'A giugno la TikTokPedia passa dai placeholder ai primi personaggi, con immagini e descrizioni. I meme di TikTok iniziano ad avere una raccolta dedicata.',
                    ],
                    'sources' => [
                        'git:5db0b659',
                        'git:7a0760c9',
                        'git:217d9a60',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'first-top-rimasti',
                    'date' => '2024-06',
                    'period' => 'Giugno',
                    'title' => 'La vecchia Top Rimasti',
                    'paragraphs' => [
                        'Top Rimasti è già presente nella versione HTML. A giugno aggiorno immagini e contenuti: è una pagina compilata a mano, prima del sistema con invii e voti degli utenti.',
                    ],
                    'sources' => [
                        'git:1148aef8',
                        'git:f2f4a194',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'old-personal-pages',
                    'date' => '2024-10',
                    'period' => 'Ottobre',
                    'title' => 'Le pagine personali di prima',
                    'paragraphs' => [
                        'Prima dei profili modificabili esistono pagine HTML dedicate alle singole persone. A ottobre ritocco bio, collegamenti e pagine personali come quella di Zakator e aggiungo quella di Simone Tussi.',
                    ],
                    'sources' => [
                        'git:51660b89',
                        'git:58715353',
                        'git:4605875b',
                    ],
                    'images' => [],
                ],
            ],
        ],
        [
            'id' => 'year-2025',
            'title' => '2025',
            'events' => [
                [
                    'id' => 'bio-discord-status',
                    'date' => '2025-01-23',
                    'period' => '23 gennaio',
                    'title' => 'Il primo stato Discord nella bio',
                    'paragraphs' => [
                        'Provo a mostrare nella bio lo stato e l’attività di Discord tramite Lanyard. L’integrazione viene modificata già nello stesso giorno: è uno dei primi esperimenti che collegano il sito a Discord.',
                    ],
                    'sources' => [
                        'git:693be2f0',
                        'git:c288ffea',
                        'git:c5f36325',
                    ],
                    'images' => [],
                ],
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
                    'images' => [],
                ],
                [
                    'id' => 'lootbox-specials-and-codes',
                    'date' => '2025-02-04',
                    'period' => '4–10 febbraio',
                    'title' => 'Musiche, speciali e codici segreti',
                    'paragraphs' => [
                        'Ogni personaggio delle lootbox riceve la propria musica. Il 7 febbraio aggiungo la rarità speciale e venti personaggi; arrivano poi il filtro SoloPoppy, i codici segreti e il conteggio della collezione.',
                    ],
                    'sources' => [
                        'git:69d7cb8e',
                        'git:ebf2ed87',
                        'git:fde4acbf',
                        'git:70c0896e',
                        'git:d4f0dd8f',
                    ],
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'theme-settings',
                    'date' => '2025-03-05',
                    'period' => '5 marzo',
                    'title' => 'Le impostazioni del tema',
                    'paragraphs' => [
                        'Aggiungo il controllo del tema alle impostazioni insieme alla lingua. Le preferenze della grafica iniziano a essere gestite e salvate dal browser.',
                    ],
                    'sources' => [
                        'git:8826aad3',
                        'git:7b0596a9',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'school-opendata',
                    'date' => '2025-05-23',
                    'period' => '23 maggio',
                    'title' => 'Opendata e le ZTL di Cremona',
                    'paragraphs' => [
                        'Sul sito compare anche Opendata, una pagina sulle ZTL di Cremona con elenco delle vie e mappa. Tra i meme trova posto pure un lavoro per la scuola, aperto da “Buongiorno prof!” e “Ci dia 10 pls :P”.',
                    ],
                    'sources' => [
                        'git:58699171',
                        'git:80a4c617',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'rich-presence',
                    'date' => '2025-05-24',
                    'period' => '24 maggio',
                    'title' => 'La Rich Presence',
                    'paragraphs' => [
                        'Aggiungo uno script di Rich Presence alle pagine HTML. Nei giorni successivi lo stato si aggiorna in base alla pagina, agli edit e alla collezione delle lootbox.',
                    ],
                    'sources' => [
                        'git:3b3ab610',
                        'git:9fecbaa6',
                        'git:51daf566',
                        'git:d11cea8a',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'uwu-and-aura',
                    'date' => '2025-09',
                    'period' => 'Settembre–ottobre',
                    'title' => 'UWU e Aura',
                    'paragraphs' => [
                        'Continuano a comparire pagine nate per il meme: UWU, con sfondo animato e scritte lampeggianti, e poi Aura, con immagini e animazioni che rimbalzano nello schermo.',
                    ],
                    'sources' => [
                        'git:b7675f1f',
                        'git:ac7ee63d',
                        'git:b3a20813',
                        'git:c7302866',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'top-rimasti-votes',
                    'date' => '2025-09-07',
                    'period' => '7 settembre',
                    'title' => 'Top Rimasti con invii e voti',
                    'paragraphs' => [
                        'Top Rimasti viene rifatta con invio dei contenuti e votazioni. La vecchia raccolta HTML comincia a diventare una sezione alimentata anche dagli utenti.',
                    ],
                    'sources' => [
                        'git:d4b24ff5',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'goonland-minigames',
                    'date' => '2026-05-01',
                    'period' => '1 maggio',
                    'title' => 'Quiz e Smash or Pass',
                    'paragraphs' => [
                        'GoonLand si allarga con Anime Girl Quiz e Smash or Pass. Alla sezione del generatore si affiancano altri giochi.',
                    ],
                    'sources' => [
                        'git:6c72e1c3',
                        'git:27140694',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'private-and-group-chat',
                    'date' => '2026-06-29',
                    'period' => '29–30 giugno',
                    'title' => 'Chat private e gruppi',
                    'paragraphs' => [
                        'Alla chat globale si affiancano le chat private e i gruppi, con gestione dei membri, permessi e reazioni. Si può parlare direttamente con altri utenti e creare conversazioni separate.',
                    ],
                    'sources' => [
                        'git:b2731c5d',
                        'git:59f64771',
                        'git:ac81ce82',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'account-export',
                    'date' => '2026-09-05',
                    'period' => '5 settembre',
                    'title' => 'Esportare i dati e cancellare l’account',
                    'paragraphs' => [
                        'Aggiungo alle impostazioni l’esportazione dei propri dati e la possibilità di avviare la cancellazione dell’account.',
                    ],
                    'sources' => [
                        'git:4705a65f',
                        'git:568b87cd',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'push-notifications',
                    'date' => '2026-10-02',
                    'period' => '2 ottobre',
                    'title' => 'Le notifiche push',
                    'paragraphs' => [
                        'Arrivano le notifiche push e i nuovi pannelli per notifiche e posta. Anche le menzioni nella chat globale vengono collegate agli avvisi.',
                    ],
                    'sources' => [
                        'git:77f53d80',
                        'git:dc3108e6',
                        'git:dbc26cfa',
                    ],
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'dark-shop-and-ban',
                    'title' => 'The Dark Shop, TikTok and the ban',
                    'paragraphs' => [
                        'Then I added the Dark Shop: a fake store where, as a meme, you could “buy” children, slaves and weird tools. I posted a video of the site on TikTok and it went viral.',
                        'In the comments, some people started accusing me of creating a honeypot: a decoy site to attract people and collect information about them. Some reported the site, which was later banned.',
                    ],
                    'sources' => [
                        'author:2026-10-30',
                    ],
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'early-downloads',
                    'date' => '2023-11-27',
                    'period' => '27 November',
                    'title' => 'Italian, English, osu! and Fortnite',
                    'paragraphs' => [
                        'I fix the language switch and add the first pages for osu! and Fortnite. The hand-coded version begins to have its own sections, although many are still empty.',
                    ],
                    'sources' => [
                        'git:d4633565',
                        'git:2f62cec2',
                        'git:7000de36',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'wise-tree-home',
                    'date' => '2023-11-28',
                    'period' => '28 November',
                    'title' => 'The Wise Mystical Tree and Spinjitzu',
                    'paragraphs' => [
                        'I finish the first home page: Cripsum, king of Congo, the Wise Mystical Tree, Happiness, Sadness and Astonishment. Jay offers to teach Spinjitzu, alongside a “free V-bucks” link leading to a rickroll.',
                    ],
                    'sources' => [
                        'git:0a9e2e2c',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'quandel57',
                    'date' => '2023-12-02',
                    'period' => '2 December',
                    'title' => 'Quandel57',
                    'paragraphs' => [
                        'Quandel57 gets a page of its own, with the TikTok meme and the “Screaming in Public Restrooms” audio. It is one of the first characters to have a dedicated section.',
                    ],
                    'sources' => [
                        'git:56c676eb',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'first-shitpost',
                    'date' => '2023-12-05',
                    'period' => '5 December',
                    'title' => 'The first Shitpost',
                    'paragraphs' => [
                        'I fill Shitpost with images and GIFs: beans, skeletons, Dame Tu Cosita and other memes. It is still a manually selected collection, long before users can publish their own posts.',
                    ],
                    'sources' => [
                        'git:e60a5ce3',
                        'git:d7340ba9',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'fake-store',
                    'date' => '2023-12-15',
                    'period' => '15–20 December',
                    'title' => 'The store and the fake checkout',
                    'paragraphs' => [
                        'I add a store with products such as the iPhone 20, PS6, RTX 4090, Renegade Raider and a two-key osu! keyboard. The checkout arrives on 20 December: it is part of the joke, rather than a real sales service.',
                    ],
                    'sources' => [
                        'git:8650fc12',
                        'git:51325314',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'minecraft-alphabet',
                    'date' => '2023-12-16',
                    'period' => '16 December',
                    'title' => 'The home page in the Minecraft alphabet',
                    'paragraphs' => [
                        'I create a version of the home page written in the Minecraft enchanting-table alphabet. The title, memes and links are all transformed.',
                    ],
                    'sources' => [
                        'git:04f063e7',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'first-edits',
                    'date' => '2023-12-23',
                    'period' => '23 December',
                    'title' => 'The Edits page begins',
                    'paragraphs' => [
                        'Edits get a dedicated page, with embedded TikTok videos. The collection keeps growing and changes platforms when old embeds stop working.',
                    ],
                    'sources' => [
                        'git:5917a53b',
                        'git:e9b73fda',
                        'git:1b3dbdda',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'first-merch',
                    'date' => '2023-12-26',
                    'period' => '26 December',
                    'title' => 'Merch',
                    'paragraphs' => [
                        'The merch page takes shape with clothing and accessories: shirts, hoodies, caps and other products. It is another of the early experiments on the static site.',
                    ],
                    'sources' => [
                        'git:ec239033',
                    ],
                    'images' => [],
                ],
            ],
        ],
        [
            'id' => 'year-2024',
            'title' => '2024',
            'events' => [
                [
                    'id' => 'download-music',
                    'date' => '2024-01-07',
                    'period' => '7 January',
                    'title' => 'Downloads with music',
                    'paragraphs' => [
                        'I update the Fortnite and osu! download pages and add music playback to the section. Even downloading something becomes a reason to put audio and buttons on the site.',
                    ],
                    'sources' => [
                        'git:a4da46d8',
                        'git:3937918c',
                        'git:965b1e88',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'elevator-loading',
                    'date' => '2024-05-19',
                    'period' => '19 May',
                    'title' => 'Loading with elevator music',
                    'paragraphs' => [
                        'TikTokPedia gets a loading wait with elevator music. I set it to ten seconds: there is already a troll before you even reach the page.',
                    ],
                    'sources' => [
                        'git:6b93ead2',
                        'git:db47e71c',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'tiktokpedia',
                    'date' => '2024-06',
                    'period' => 'June',
                    'title' => 'TikTokPedia fills up',
                    'paragraphs' => [
                        'In June, TikTokPedia moves from placeholders to its first characters, with images and descriptions. TikTok memes begin to have a dedicated collection.',
                    ],
                    'sources' => [
                        'git:5db0b659',
                        'git:7a0760c9',
                        'git:217d9a60',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'first-top-rimasti',
                    'date' => '2024-06',
                    'period' => 'June',
                    'title' => 'The old Top Rimasti',
                    'paragraphs' => [
                        'Top Rimasti is already part of the HTML version. In June I update its images and content: it is a manually maintained page, before user submissions and voting.',
                    ],
                    'sources' => [
                        'git:1148aef8',
                        'git:f2f4a194',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'old-personal-pages',
                    'date' => '2024-10',
                    'period' => 'October',
                    'title' => 'The old personal pages',
                    'paragraphs' => [
                        'Before editable profiles, there are individual HTML pages for different people. In October I update the bio, links and personal pages such as Zakator’s, and add Simone Tussi’s page.',
                    ],
                    'sources' => [
                        'git:51660b89',
                        'git:58715353',
                        'git:4605875b',
                    ],
                    'images' => [],
                ],
            ],
        ],
        [
            'id' => 'year-2025',
            'title' => '2025',
            'events' => [
                [
                    'id' => 'bio-discord-status',
                    'date' => '2025-01-23',
                    'period' => '23 January',
                    'title' => 'The first Discord status in the bio',
                    'paragraphs' => [
                        'I try showing Discord status and activity in the bio through Lanyard. The integration changes again that same day: it is one of the first experiments connecting the site to Discord.',
                    ],
                    'sources' => [
                        'git:693be2f0',
                        'git:c288ffea',
                        'git:c5f36325',
                    ],
                    'images' => [],
                ],
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
                    'images' => [],
                ],
                [
                    'id' => 'lootbox-specials-and-codes',
                    'date' => '2025-02-04',
                    'period' => '4–10 February',
                    'title' => 'Music, specials and secret codes',
                    'paragraphs' => [
                        'Lootbox characters get their own music. On 7 February I add special rarity and twenty characters; the SoloPoppy filter, secret codes and collection counts follow.',
                    ],
                    'sources' => [
                        'git:69d7cb8e',
                        'git:ebf2ed87',
                        'git:fde4acbf',
                        'git:70c0896e',
                        'git:d4f0dd8f',
                    ],
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'theme-settings',
                    'date' => '2025-03-05',
                    'period' => '5 March',
                    'title' => 'Theme settings',
                    'paragraphs' => [
                        'I add theme controls to the settings alongside language selection. Appearance preferences begin to be managed and saved in the browser.',
                    ],
                    'sources' => [
                        'git:8826aad3',
                        'git:7b0596a9',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'school-opendata',
                    'date' => '2025-05-23',
                    'period' => '23 May',
                    'title' => 'Opendata and Cremona’s restricted traffic zones',
                    'paragraphs' => [
                        'Opendata appears on the site: a page about Cremona’s restricted traffic zones, with a street list and a map. Among the memes there is also room for a school project, opening with “Good morning, teacher!” and a request for a top grade.',
                    ],
                    'sources' => [
                        'git:58699171',
                        'git:80a4c617',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'rich-presence',
                    'date' => '2025-05-24',
                    'period' => '24 May',
                    'title' => 'Rich Presence',
                    'paragraphs' => [
                        'I add a Rich Presence script to the HTML pages. Over the following days, the status updates according to the page, edits and lootbox collection.',
                    ],
                    'sources' => [
                        'git:3b3ab610',
                        'git:9fecbaa6',
                        'git:51daf566',
                        'git:d11cea8a',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'uwu-and-aura',
                    'date' => '2025-09',
                    'period' => 'September–October',
                    'title' => 'UWU and Aura',
                    'paragraphs' => [
                        'Pages made for the meme keep appearing: UWU, with an animated background and flashing text, followed by Aura, with images and animations bouncing around the screen.',
                    ],
                    'sources' => [
                        'git:b7675f1f',
                        'git:ac7ee63d',
                        'git:b3a20813',
                        'git:c7302866',
                    ],
                    'images' => [],
                ],
                [
                    'id' => 'top-rimasti-votes',
                    'date' => '2025-09-07',
                    'period' => '7 September',
                    'title' => 'Top Rimasti gets submissions and voting',
                    'paragraphs' => [
                        'Top Rimasti is rebuilt with content submissions and voting. The old HTML collection begins becoming a section that users can contribute to.',
                    ],
                    'sources' => [
                        'git:d4b24ff5',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'goonland-minigames',
                    'date' => '2026-05-01',
                    'period' => '1 May',
                    'title' => 'Quizzes and Smash or Pass',
                    'paragraphs' => [
                        'GoonLand expands with Anime Girl Quiz and Smash or Pass, adding more games alongside the generator.',
                    ],
                    'sources' => [
                        'git:6c72e1c3',
                        'git:27140694',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'private-and-group-chat',
                    'date' => '2026-06-29',
                    'period' => '29–30 June',
                    'title' => 'Private chats and groups',
                    'paragraphs' => [
                        'Private chats and groups join global chat, with member management, permissions and reactions. Users can talk directly and create separate conversations.',
                    ],
                    'sources' => [
                        'git:b2731c5d',
                        'git:59f64771',
                        'git:ac81ce82',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'account-export',
                    'date' => '2026-09-05',
                    'period' => '5 September',
                    'title' => 'Data export and account deletion',
                    'paragraphs' => [
                        'I add options in settings to export your data and start the account deletion process.',
                    ],
                    'sources' => [
                        'git:4705a65f',
                        'git:568b87cd',
                    ],
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
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
                    'images' => [],
                ],
                [
                    'id' => 'push-notifications',
                    'date' => '2026-10-02',
                    'period' => '2 October',
                    'title' => 'Push notifications',
                    'paragraphs' => [
                        'Push notifications and new notification and inbox panels arrive. Global chat mentions are also connected to alerts.',
                    ],
                    'sources' => [
                        'git:77f53d80',
                        'git:dc3108e6',
                        'git:dbc26cfa',
                    ],
                    'images' => [],
                ],
            ],
        ],
    ],
];
