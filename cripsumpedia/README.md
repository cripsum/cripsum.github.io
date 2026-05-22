# Cripsumpedia

Sezione lore moderna per cripsum.com: persone, eventi, meme, relazioni wiki, autolink, ricerca live, timeline, admin/editor e API MySQLi.

## File

- `index.php`: homepage immersiva con statistiche live, ricerca globale, categorie, ultimi elementi, trending, evento del giorno e citazione random.
- `category.php`: liste per persone/eventi/meme con ricerca, tag, ordinamenti e timeline animata per gli eventi.
- `entry.php`: pagina premium di una voce lore con hero, markdown, autolink, hover preview, sidebar, relazioni, quote, share, focus mode, reaction e preferiti.
- `search.php`: motore ricerca globale con suggerimenti live e risultati su titoli, descrizioni, contenuti, tag, alias e citazioni.
- `admin.php`: dashboard interna moderna per filtrare, aprire, modificare ed eliminare voci.
- `editor.php`: editor con markdown, preview live, upload immagini, campi IT/EN, SEO, tag, alias, citazioni e relazioni AJAX.
- `_bootstrap.php`: helper condivisi per routing bilingue, query MySQLi, rendering, markdown, sicurezza admin e componenti UI.
- `cripsumpedia.css`: stile dark cyber glassmorphism responsive, senza Bootstrap.
- `cripsumpedia.js`: ricerca live, hover preview, editor, upload, relazioni, focus mode, share e azioni AJAX.
- `../api/cripsumpedia_search.php`: endpoint pubblico per ricerca, preview hover, tag e voce random.
- `../api/cripsumpedia_save.php`: endpoint per salvataggio admin, upload, delete, reaction e preferiti.
- `../api/cripsumpedia_relations.php`: endpoint admin per ricerca e gestione relazioni.
- `install.sql`: schema completo MySQL con foreign key, indici e fulltext.

## Database

Importa `install.sql` nel database del sito. Le tabelle principali sono:

- `cripsumpedia_entries`
- `cripsumpedia_relations`
- `cripsumpedia_tags`
- `cripsumpedia_entry_tags`
- `cripsumpedia_views`
- `cripsumpedia_aliases`
- `cripsumpedia_quotes`

Extra premium:

- `cripsumpedia_reactions`
- `cripsumpedia_favorites`

Tutti i campi testuali principali hanno versione italiana e inglese con suffisso `_en`.

## Installazione

1. Importa `cripsumpedia/install.sql`.
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
- Preferiti non disponibili: importa anche le tabelle extra `cripsumpedia_favorites` e `cripsumpedia_reactions`.
- Link `/it/cripsumpedia` non risolve: verifica le regole `.htaccess`.

## Fix Consigliati

- Se il sito usa un prefisso diverso, aggiorna `cp_base_url()` in `_bootstrap.php`.
- Se vuoi routing senza `.htaccess`, usa direttamente `/cripsumpedia/index.php?lang=it`.
- Se la ricerca diventa grande, passa da `LIKE` a `MATCH AGAINST` usando gli indici FULLTEXT gia presenti.
- Se vuoi OG image realmente generata, aggiungi un endpoint dedicato che componga immagine da `banner_url`, `title` e `accent_color`.

