# Immagini della Lore

Ogni evento può avere più immagini. Non servono modifiche al database.

## Aggiunta rapida

Crea una cartella con l'identificativo dell'evento e aggiungi le foto:

```text
img/lore/google-sites/01-home.png
img/lore/google-sites/02-chi-siamo.jpg
img/lore/dark-shop-and-ban/01-commenti.webp
```

Le immagini compaiono automaticamente sotto l'evento, in entrambe le lingue.
I file seguono l'ordine naturale dei nomi: 01, 02, 03…
Formati: JPG, JPEG, PNG, WebP, AVIF e GIF.
Il clic apre l'immagine originale. Cartelle vuote non aggiungono elementi alla pagina.

## Didascalie e testo alternativo

Per una didascalia, usa il campo `images` dell'evento in
`includes/lore/content.php` (nella versione italiana e in quella inglese):

```php
'images' => [
    [
        'src' => '/img/lore/google-sites/01-home.png',
        'alt' => 'La homepage della versione Google Sites',
        'caption' => 'La prima versione del sito, 2023.',
    ],
    '/img/lore/google-sites/02-chi-siamo.jpg',
],
```

Puoi anche indicare immagini già presenti altrove nel sito.
Larghezza e altezza vengono rilevate dal file; non occorre inserirle.
Un'immagine dichiarata e presente nella cartella compare una sola volta.
File mancanti o non validi vengono ignorati.

## Cartelle degli eventi

| Evento | Cartella |
| --- | --- |
| Google Sites e la “subcultura” | `google-sites/` |
| Il Dark Shop, TikTok e il ban | `dark-shop-and-ban/` |
| Il ritorno su GitHub Pages | `github-pages/` |
| Italiano, inglese, osu! e Fortnite | `early-downloads/` |
| Il Wise Mystical Tree e lo Spinjitzu | `wise-tree-home/` |
| Quandel57 | `quandel57/` |
| Il primo Shitpost | `first-shitpost/` |
| Il negozio e il checkout finto | `fake-store/` |
| La home in alfabeto Minecraft | `minecraft-alphabet/` |
| Nasce la pagina Edits | `first-edits/` |
| Il merch | `first-merch/` |
| Download con la musica | `download-music/` |
| Il caricamento con la musica da ascensore | `elevator-loading/` |
| La TikTokPedia si riempie | `tiktokpedia/` |
| La vecchia Top Rimasti | `first-top-rimasti/` |
| Il gambling | `gambling/` |
| Una nuova grafica | `first-redesign/` |
| Le pagine personali di prima | `old-personal-pages/` |
| Il primo stato Discord nella bio | `bio-discord-status/` |
| Le prime lootbox | `first-lootboxes/` |
| Musiche, speciali e codici segreti | `lootbox-specials-and-codes/` |
| Gli achievement | `first-achievements/` |
| Le impostazioni del tema | `theme-settings/` |
| Opendata e le ZTL di Cremona | `school-opendata/` |
| La Rich Presence | `rich-presence/` |
| Il passaggio a Hostinger | `hostinger-php/` |
| Account, profili e chat globale | `accounts-and-chat/` |
| GoonLand | `goonland/` |
| UWU e Aura | `uwu-and-aura/` |
| Top Rimasti con invii e voti | `top-rimasti-votes/` |
| La prima Cripsumpedia | `first-cripsumpedia/` |
| Chat e contenuti della community | `community-v2/` |
| I primi Duelli | `first-duels/` |
| Gacha 2.0 e missioni | `gacha-missions/` |
| Quiz e Smash or Pass | `goonland-minigames/` |
| Profili custom e Premium | `custom-profiles-premium/` |
| Posta, amici e follower | `friends-and-inbox/` |
| Chat private e gruppi | `private-and-group-chat/` |
| Subway Surfers | `subway-surfers/` |
| Pullspot e Animespot | `pullspot-animespot/` |
| Il sito e Discord | `discord-bot/` |
| Shop, gacha e Team OHPY | `shop-gacha-esports/` |
| Esportare i dati e cancellare l’account | `account-export/` |
| Cripsum Rewind | `rewind/` |
| La grafica attuale | `site-theme/` |
| Cripsumpedia si apre, nasce Bio | `wiki-and-bio/` |
| Duelli 2.0 e la Torre | `duels-2/` |
| Le notifiche push | `push-notifications/` |
