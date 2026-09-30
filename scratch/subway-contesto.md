# Subway Surfers: contesto per continuare il lavoro

Riepilogo dell'analisi fatta il 30/09/2026. **Codice non ancora scritto**: esistono il piano, le build scaricate e la riga nuova nel `.gitignore`.

## Regole del progetto da rispettare
- I commit li fa l'utente: lasciare le modifiche non committate e non aggiungere mai la riga `Co-Authored-By`.
- Le migration SQL si applicano a mano: niente DDL nel PHP, lo schema si verifica a runtime.
- A ogni modifica vanno alzate le versioni degli asset in `it/subway.php` ed `en/subway.php` (oggi `subway.css?v=22.0`, `subway.js?v=22.0`, `subway-profile.js?v=2.0`).
- Le pagine IT ed EN sono due file quasi identici: ogni modifica va fatta su entrambe.
- **Nessun riferimento a tavvkkj nel sito.** L'utente ha il suo permesso di usare le build, ma non vuole credit né link.
- **Reborn** (la versione "enhanced" di tavvkkj, con più mappe, reboard e skin custom): è dietro un controllo di accesso sul suo server. Non si scarica aggirandolo. Si integra solo se tavvkkj passa i file, da mettere in `subway-builds/reborn/`. Per ora è accantonata.

## File coinvolti
- `it/subway.php`, `en/subway.php`: 1466 righe ciascuna.
- `assets/js/subway/subway.js`: circa 2170 righe, tutta la logica: launcher, timer No-Coin, HUD, classifica.
- `assets/js/subway/subway-profile.js`: inietta nel file system IDBFS il profilo di salvataggio "completo".
- `assets/css/subway.css`
- `api/subway/start_run.php`, `api/subway/save_score.php`, `api/subway/get_leaderboard.php`
- Tabella `subway_leaderboard` (`utente_id`, `best_time_ms`, `map_slug`, `created_at`, `updated_at`). Lo schema non è nel repo: va verificato in produzione se c'è `UNIQUE(utente_id)`.
- File morti, non referenziati da nessuna parte: `assets/js/subway/UnityLoader.js`, `assets/js/subway/poki.js`, `assets/js/subway/runtime/` (1,5 MB).

## Decisioni dell'utente
1. **Hosting delle build:** su Hostinger (più di 10 GB liberi), caricate **a mano dall'utente**, mai tramite git. Il sito è Hostinger + LiteSpeed con Cloudflare davanti. Proposta: un sottodominio o una cartella dedicata, servita con `.htaccess` (brotli precompresso, `application/wasm`, `Cache-Control: immutable`) e una Cache Rule su Cloudflare per `.unityweb`/`.br`. jsDelivr resta come riserva.
2. **Una sola classifica globale.** Niente classifica per mappa.
3. **Modalità allenamento: sì.** Le run di allenamento NON entrano in classifica, e il controllo va fatto lato server.

## Build scaricate: `subway-builds/` (in `.gitignore`, 1,5 GB, 186 file verificati)
Contengono le build di tavvkkj (release `unity-builds-20260607` e cartelle `game-assets/builds` dei repo `therealoness-builds-1/2/3`), più `_training/training-patches.json` (80 MB). **La cartella `_training/` non va caricata su Hostinger.**

| Mappa | Dimensione | Unity | Wasm | Varianti JSON |
|---|---|---|---|---|
| beijing, buenosaires, havana, iceland, london, neworleans, saintpetersburg | circa 28–29 MB | 2019.2.0f1 | `shared/wasm_code_shared` | default, only1rowObs, only3rows, only3rows1rowObs |
| hongkong, newyork, rio, tokyo, venice | 55–80 MB | 2019.2.0f1 | `shared/wasm_code_shared` | only1rowObs, only3rows, only3rows1rowObs |
| barcelona, mexico | circa 28 MB | 2019.4.18f1 / 2019.2 | `shared/wasm_code_shared_2` | tutte |
| monaco, sanfrancisco | circa 28 MB | 2019.4.18f1 | `shared/wasm_code_shared_3` | tutte |
| berlin, houston, winterholiday | circa 55 MB | 2019.4.18f1 | wasm proprio | tutte |
| miami | 57 MB | da verificare | wasm e framework propri | tutte |
| cairo, paris | circa 122 MB | 2019.2.0f1 | wasm proprio + fallback asm.js | nessuna |
| moscow | 78 MB | 2019.2.0f1 | wasm proprio | nessuna |
| bangkok | 168 MB | **2018.4.36f1** (serve un loader diverso) | wasm proprio + asm | nessuna |
| zurich | 81 MB | da verificare (probabilmente 2019.4) | `zurich.wasm.code` / `.4399` | nessuna |

- Il `shared/wasm_framework.unityweb` **contiene il bridge Poki** (`_JS_PokiSDK_roundEnd`, `gameplayStop`…). Con queste build tutte le mappe rilevano la morte, cosa che oggi non succede per Mexico e Winter Holiday.
- I `.data` di allenamento **non esistono come file**. tavvkkj li compone nel browser a partire da `<mappa>.alt.data.unityweb` + `training-patches.json`. Le patch sostituiscono TextAsset (classId 49) e, nella famiglia 2019.4, Transform (classId 4) dentro `resources.assets`. Le varianti sono `training-default`, `training-3rows`, `training-obstacles`. Per alcune mappe (berlin, houston, sanfrancisco) la sua diagnostica riporta `parseError`.
- Piano per l'allenamento: generare i `.data` **offline, una volta sola** (script Node, oppure Python con UnityPy) e caricarli già pronti. Si parte da london, beijing e iceland.
- I `.alt.data` sono modificati: vanno **riverificate** le impronte audio usate dalla sfida (`runStartAudioDurations`, `coinAudioDurations`, `coinDecodedFrames` in `subway.js`), l'auto-boost e l'iniezione del profilo.

## Bug: il record a volte non viene salvato
Cause trovate, dalla più probabile:

- **A. Run in pausa scartata.** `gameplayStop` porta il timer in pausa. Poi l'audio di inizio run o `roundStart` chiama `startNewRound()`, che esegue `resetTimer()` senza inviare il tempo. Anche il tasto **R** in pausa esegue `resetTimer()` (dovrebbe finalizzare la run). Riferimenti in `subway.js`: `startNewRound` ~1297, gestione di R ~1626, `classifyAudio` ~1161.
- **B. Uscita o reload.** "Torna alla selezione" esegue `location.reload()`: il `fetch` di `save_score` in volo viene annullato (manca `keepalive`) e non c'è nessun salvataggio su `pagehide`.
- **C. Gara del token.** `requestRunSession()` non viene aspettato: il salvataggio può partire con `run_token: ''` o con un token vecchio, e riceve 403. Anche le risposte fuori ordine e due schede aperte danno lo stesso effetto.
- **D. Controllo dei 5 s in `save_score.php` (~107).** Il timer del client parte prima dell'orario del server. Se `start_run` è in ritardo (rete, lock di sessione PHP) la run viene rifiutata con 400. `get_leaderboard.php` non chiama `cripsum_release_session()`.
- **E. Il client ignora gli errori.** Solo un `console.warn`: nessun nuovo tentativo, nessun toast, nessuna coda. `response.json()` su una pagina di errore HTML va in eccezione.
- **F. Server con SELECT e poi INSERT (~128).** Una gara tra richieste concorrenti. Serve un upsert atomico con `GREATEST`.
- **G. Altro:**
  - senza token in sessione, `save_score` accetta qualsiasi tempo;
  - il timer parte anche premendo WASD nel menu;
  - la whitelist mappe è diversa tra `start_run` e `save_score`;
  - sulle mappe senza bridge Poki i tempi risultano gonfiati.

### Correzioni previste
- **Fase 0 (diagnosi, 1–2 giorni in produzione):** log della sequenza di eventi Poki, dell'audio, di ogni reset con la sua causa e dell'esito HTTP del salvataggio. Lato server, `error_log` di ogni rifiuto con il motivo.
- **Client:**
  - un unico `finalizeRun(reason)` chiamato prima di ogni `resetTimer`;
  - una coda persistente in `localStorage` con un id per ogni run, reinvii con backoff, `keepalive` e `sendBeacon` su `pagehide`;
  - l'uscita aspetta al massimo circa 1,5 s che la coda si svuoti;
  - il salvataggio aspetta la Promise di `start_run`, con un numero di sequenza per scartare le risposte vecchie;
  - un toast per ogni esito (salvato / non è un record / errore con nuovo tentativo / sessione scaduta);
  - il timer parte solo da `roundStart`, `gameplayStart` o dall'audio di inizio.
- **Server:**
  - upsert `INSERT … ON DUPLICATE KEY UPDATE best_time_ms = GREATEST(…)`, con una migration per `UNIQUE(utente_id)` se manca;
  - token obbligatorio;
  - salvataggio idempotente sull'id della run (così stats e missioni non contano due volte);
  - `cripsum_release_session()` in `get_leaderboard.php` e in `save_score.php`;
  - lista mappe in un unico include condiviso;
  - la modalità scritta nel token, con rifiuto delle run di allenamento;
  - risposte JSON con un campo `code`.

## Prestazioni (misurate)
- Oggi ogni mappa scarica circa **55 MB non compressi** da jsDelivr: `application/vnd.unity`, senza `Content-Encoding`. Niente compilazione in streaming: il fallback in `installWasmMimeFallback` usa `instantiate(ArrayBuffer)`. Il wasm è diverso per ogni mappa.
- Piano:
  1. misure con `performance.mark` (MB scaricati, tempo a freddo e a caldo, FPS medi e 1% low);
  2. **FPS unlock vero** con `Module._emscripten_set_main_loop_timing(1, 1)`, anche via `asmLibraryArg` (è così che lo fa ashuni.lol);
  3. build su Hostinger in brotli, con i wasm condivisi;
  4. `instantiateStreaming` per avere la cache del codice compilato;
  5. cache persistente, con lo stato "in cache" mostrato sulle card;
  6. `desynchronized: true` e `antialias: false` sui dispositivi deboli;
  7. blur degli overlay a 0 per default;
  8. eliminazione dei file morti.

## Pagina
- Eliminare il pannello impostazioni della lobby (`hidden`, righe 75–672, duplica il modale) e le 12 card statiche (righe ~686–799, con immagini da `raw.githubusercontent.com/tavvkkj`). Passare a un unico template IT/EN.
- **Bug:** l'immagine del timer viene salvata come data URL in `localStorage` e `saveSettings()` non ha try/catch. Superata la quota, non si salva più nessuna impostazione.
- Card mappa con anteprima, dimensione, stato della cache, record personale, e selettore di modalità (Originale / allenamento).
- Schermata di fine run con lo stato del salvataggio. Barra di caricamento con MB e velocità. Modale chiudibile con ESC e con il focus bloccato. Avviso per i dispositivi touch.
- Classifica: evidenziare la propria riga, escapare `avatar_url`, togliere l'`onerror` inline.

## Ordine delle fasi
0. Log di diagnosi.
1. Correzione del bug del record (client + server + eventuale migration UNIQUE).
2. Interventi rapidi sulle prestazioni (misure, FPS unlock, blur, file morti).
3. Pulizia della pagina, bug di `localStorage`, schermata di fine run.
4. Integrazione delle nuove build: `maps` in `subway.js` punta alla cartella su Hostinger, file preparati in brotli, `.htaccess`, regola di cache, jsDelivr come riserva, loader 2018 per bangkok.
5. Modalità allenamento: generazione offline dei `.data`, selettore, modalità nel token.

## Stato attuale del repo (01/10/2026, notte)
- Fasi 0-1 committate dall'utente (7fcc51e6). Fasi 3-4-5 fatte ma NON committate.
- **Build**: `subway-builds-br/` (in .gitignore) = build brotli q11 con stessi nomi + `.htaccess` (Content-Encoding: br), 1497 -> 561 MB, ~21 MB a mappa. L'utente le carica in `public_html/subway-builds/`. Script in `scripts/subway/` (README).
- **Etichetta "by tavvkkj"** era DENTRO il gioco in bangkok cairo hongkong moscow newyork paris rio tokyo venice (.alt.data): sostituita con 10 spazi nei file di subway-builds-br.
- **Allenamento**: 33 file (11 mappe x training / training3rows / trainingobstacles), verificati con gli SHA-1 delle patch. Regola: oggetti in ordine di tabella, allineati a 8, nessuna coda. Escluse: berlin houston monaco sanfrancisco zurich (core8-inplace) e newyork (base diversa). Il permesso di eseguire il codice del sito di tavvkkj e di avviare altre build nel browser di prova e' stato NEGATO dal classificatore: non riprovare.
- Verificato in browser solo London (originale e training3rows arrivano al menu del gioco). Le altre famiglie usano la stessa coppia 4399.js + UnityLoader.2019.2.js (da jsDelivr, pacchetto subwaylondon@1.0.0), da provare sul sito. Bangkok (Unity 2018) marcata Beta.
- **Pagina**: template unico `includes/subway/page.php` (it/en/subway.php sono involucri), catalogo `includes/subway/catalog.php` (usato anche dalla whitelist API). Asset `subway.css/js?v=24.0`. Lobby con modalita' Classifica/Allenamento, ricerca, regioni, "gioca di nuovo"; modale impostazioni a schede con anteprima live; scheda di fine run; riga propria in classifica; ripiego jsDelivr per le 14 mappe vecchie.
- Da fare: fase 2 (FPS unlock, misure, file morti `assets/js/subway/UnityLoader.js`, `poki.js`, `runtime/`), Cache Rule Cloudflare, prova di tutte le mappe in produzione.
- Questo file (`scratch/subway-contesto.md`) non e' tracciato: va cancellato quando non serve piu'.
