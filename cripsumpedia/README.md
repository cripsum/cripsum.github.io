# Cripsumpedia

La wiki della lore di cripsum.com: persone, eventi, meme. Chi ha un account propone voci e modifiche, lo staff
(owner e admin) le approva; ogni voce ha la sua cronologia.

## File

- `index.php`: pagina d'ingresso: ricerca, voce in evidenza, accadde oggi, persone, eventi, meme, ultime modifiche, invito a scrivere.
- `category.php`: persone (annuario), eventi (linea del tempo per anno), meme (galleria), con filtro per tag e due ordini.
- `entry.php`: una voce: testo con indice, citazioni, collegamenti, «ne parlano anche», foto e fatti a destra, «Cronologia (n)» in fondo; i nomi delle altre voci si collegano da soli alla prima occorrenza.
- `search.php`: risultati di ricerca su titoli, descrizioni, testo, tag, soprannomi e citazioni, divisi per tipo.
- `scrivi.php` + `scrivi.js`: scrivere una voce nuova o modificarne una, per tutti (fase 4). La bozza si salva da sola nel database; lo staff pubblica subito, gli altri mandano allo staff. `?voce=`, `?bozza=`, `?riprendi=` (proposta rifiutata), `?sistema=` (staff: sistema e approva).
- `revisione.php`: «Da approvare», solo staff: differenze campo per campo, approva, rifiuta con un motivo, sistema e approva.
- `cronologia.php`: le versioni di una voce, cosa è cambiato in ognuna, «Ripristina» per lo staff.
- `admin.php`, `editor.php`: indirizzi vecchi, portano al Pannello admin (`/it/admin#cripsumpedia`) e a Scrivi.
- `_bootstrap.php`: helper condivisi per routing bilingue, query MySQLi, markdown, pezzi delle pagine.
- `cripsumpedia.css`, `cripsumpedia.js`: aspetto e comportamento di tutte le pagine (anche approva, rifiuta, ripristina).
- `../api/cripsumpedia_search.php`: pubblico, sola lettura: ricerca, finestra di ricerca (`action=palette`), anteprima, tag, voce a caso.
- `../api/cripsumpedia_save.php`: scrivere: bozza, invio, foto, anteprima del testo, controllo del titolo, preferiti.
- `../api/cripsumpedia_review.php`: staff: approva, rifiuta, ripristina, nascondi.
- `../api/admin/cripsumpedia.php` + `../assets/admin-v2/admin-cripsumpedia.js`: la sezione «Cripsumpedia» del Pannello admin (coda, voci, impostazioni, utenti bloccati, import da Chi siamo).
- `../includes/cripsumpedia/`: cronologia (`revisions.php`), testo salvato già in HTML e `[[collegamenti]]` (`content.php`), scrivere (`write.php`), bozze e permessi (`proposals.php`), approvazione e import (`review.php`), differenze (`diff.php`), testi delle pagine per scrivere (`write_ui.php`), liste e pezzi delle pagine da leggere (`lists.php`, `ui.php`), ricerca sopra la pagina (`palette.php`).

## Database

Migrazioni, in quest'ordine:

1. `migrations/2026_10_17_cripsumpedia_schema.sql`: le tabelle di sempre (voci, collegamenti, tag, soprannomi, citazioni, preferiti).
2. `migrations/2026_10_17_cripsumpedia_v2.sql`: cronologia (`cripsumpedia_revisions`), chi cita chi, indirizzi vecchi, letture per giorno, impostazioni, utenti bloccati.
3. `migrations/2026_10_17_cripsumpedia_fase4.sql`: il registro delle foto caricate (`cripsumpedia_uploads`). Senza, chi non è staff può scrivere ma non caricare foto.

Finché manca la v2 la Cripsumpedia si legge come prima ma non si scrive.

Le proposte partono chiuse: si aprono dal Pannello admin → Cripsumpedia → Impostazioni.

## Regole

- Scrivere: login, CSRF del sito, account di almeno N giorni (dal pannello), non bloccati, proposte aperte. Lo staff sempre.
- Limiti: proposte in attesa per persona (dal pannello), una proposta ogni 30 secondi, 10 bozze aperte, 10 foto al giorno.
- Le foto si ridisegnano sul server (niente dati GPS, lato lungo 1600 px). Nel testo solo foto del sito.
- Approvare applica solo i campi cambiati dalla proposta: quello che lo staff ha cambiato intanto in altri campi resta.
- Account cancellato: voci e modifiche approvate restano senza nome, bozze e proposte se ne vanno (`includes/account_data_helpers.php`).

## Test

- Scrivi una voce da staff: va online, compare in cronologia.
- Apri le proposte dal pannello, scrivi da un account normale: bozza salvata, «Invia allo staff», la coda nel pannello e il numero rosso nella barra.
- Approva, rifiuta (il motivo arriva nella Posta), sistema e approva, ripristina una versione dalla cronologia.
- `[[Titolo]]` e `[[Titolo|testo]]` nel testo; una voce che non esiste resta testo (per chi può scrivere: link per crearla).
- Controlla il telefono a 390 e 320 px.
