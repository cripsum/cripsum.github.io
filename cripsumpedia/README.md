# Cripsumpedia

Sezione lore moderna per cripsum.com: persone, eventi, meme, relazioni wiki, autolink, ricerca live, timeline, admin/editor e API MySQLi.

## File

- `index.php`: pagina d'ingresso: ricerca, voce in evidenza, accadde oggi, persone, eventi, meme, ultime modifiche.
- `category.php`: persone (annuario), eventi (linea del tempo per anno), meme (galleria), con filtro per tag e due ordini.
- `entry.php`: una voce: testo con indice, citazioni, collegamenti, «ne parlano anche», foto e fatti a destra; i nomi delle altre voci si collegano da soli alla prima occorrenza.
- `search.php`: risultati di ricerca su titoli, descrizioni, testo, tag, soprannomi e citazioni, divisi per tipo.
- `admin.php`: pannello dello staff (com'era; dalla fase 4 passa al Pannello admin del sito).
- `editor.php`: editor dello staff (com'era fino alla fase 4); `?type=` e `?title=` precompilano una voce nuova.
- `_bootstrap.php`: helper condivisi per routing bilingue, query MySQLi, rendering, markdown, sicurezza admin e componenti UI.
- `cripsumpedia.css`, `cripsumpedia.js`: aspetto e comportamento delle pagine da leggere (fase 2).
- `staff.css`, `staff.js`: il foglio e lo script di prima, solo per editor e pannello fino alla fase 4.
- `../api/cripsumpedia_search.php`: endpoint pubblico per ricerca, preview hover, tag e voce random.
- `../api/cripsumpedia_save.php`: salvataggio dello staff (con cronologia), upload delle foto (ripulite), eliminazione (nasconde), preferiti.
- `../api/cripsumpedia_relations.php`: endpoint admin per cercare le voci da collegare (i collegamenti si salvano con la voce).
- `../includes/cripsumpedia/`: cronologia delle voci, testo salvato già in HTML, chi cita chi, indirizzi vecchi, letture per giorno, pulizia delle foto caricate, liste e pezzi delle pagine (`lists.php`, `ui.php`).

## Database

Lo schema sta in `migrations/2026_10_17_cripsumpedia_schema.sql` (le tabelle di sempre) e
`migrations/2026_10_17_cripsumpedia_v2.sql` (cronologia e il resto della fase 1). Le tabelle principali sono:

- `cripsumpedia_entries`
- `cripsumpedia_relations`
- `cripsumpedia_tags`
- `cripsumpedia_entry_tags`
- `cripsumpedia_views`
- `cripsumpedia_aliases`
- `cripsumpedia_quotes`

Extra premium:

- `cripsumpedia_reactions` (non si scrive più)
- `cripsumpedia_favorites`

Dalla v2: `cripsumpedia_revisions`, `cripsumpedia_mentions`, `cripsumpedia_slug_redirects`,
`cripsumpedia_views_daily`, `cripsumpedia_settings`, `cripsumpedia_blocked`. Finché la v2 manca
la Cripsumpedia funziona come prima, senza cronologia.

Tutti i campi testuali principali hanno versione italiana e inglese con suffisso `_en`.

## Installazione

1. Esegui `migrations/2026_10_17_cripsumpedia_schema.sql` e poi `migrations/2026_10_17_cripsumpedia_v2.sql`.
2. Verifica che `secure/config.php` punti al DB corretto.
3. Assicurati che gli utenti admin abbiano `ruolo = 'admin'` oppure `ruolo = 'owner'`.
4. Apri `/it/cripsumpedia/admin` e crea la prima voce.
5. Usa `/it/cripsumpedia`, `/en/cripsumpedia` oppure `/cripsumpedia/`.

## Test

- Apri homepage, categorie, ricerca e una voce pubblicata.
- Crea una persona, un evento e un meme dall'editor.
- Aggiungi alias e verifica che nel contenuto vengano trasformati in link lore.
- Collega due voci in relazioni e verifica la sidebar su entrambe.
- Carica immagine da drag-and-drop e controlla che venga salvata in `/img/cripsumpedia/YYYY/MM/`.
- Prova ricerca live con titolo, tag, alias e citazione.
- Controlla responsive mobile sotto 430px.

## Problemi Comuni

- `Cripsumpedia non installata`: manca almeno una tabella dello schema.
- Errore foreign key su `utenti`: controlla engine InnoDB e tipo della colonna `utenti.id`.
- Upload non funziona: verifica permessi scrittura su `/img/cripsumpedia`.
- Preferiti non disponibili: manca la tabella `cripsumpedia_favorites` (è nel file `schema`).
- Link `/it/cripsumpedia` non risolve: verifica le regole `.htaccess`.

## Fix Consigliati

- Se il sito usa un prefisso diverso, aggiorna `cp_base_url()` in `_bootstrap.php`.
- Se vuoi routing senza `.htaccess`, usa direttamente `/cripsumpedia/index.php?lang=it`.
- Se la ricerca diventa grande, passa da `LIKE` a `MATCH AGAINST` usando gli indici FULLTEXT gia presenti.
- Se vuoi OG image realmente generata, aggiungi un endpoint dedicato che componga immagine da `banner_url`, `title` e `accent_color`.

