<?php
declare(strict_types=1);

/**
 * Slide della sezione "Cosa puoi fare su Cripsum" della homepage.
 *
 * Stanno in `home_slides` e si gestiscono dal pannello admin. Qui vengono
 * lette e passate alla pagina gia' nella lingua giusta: lo slider le riceve
 * dentro il documento, senza una chiamata in piu' e senza lo sfarfallio di una
 * sezione che si riempie dopo.
 *
 * Se la tabella non esiste ancora — migrazione non applicata — o e' vuota, la
 * funzione restituisce un array vuoto e lo slider ricade sulle slide scritte
 * dentro home.js: la homepage non resta mai senza quella sezione.
 */

/**
 * @return array<int, array{media:string, title:string, description:string, buttonText:string, link:string}>
 */
function home_slides_load(?mysqli $mysqli, string $lang = 'it'): array
{
    if (!$mysqli instanceof mysqli) {
        return [];
    }

    if (!function_exists('auth_table_exists') || !auth_table_exists($mysqli, 'home_slides')) {
        return [];
    }

    $result = $mysqli->query(
        'SELECT media, link, titolo, titolo_en, descrizione, descrizione_en,
                testo_bottone, testo_bottone_en
         FROM home_slides
         WHERE attiva = 1
         ORDER BY posizione ASC, id ASC
         LIMIT 40'
    );

    if (!$result) {
        return [];
    }

    $english = $lang === 'en';
    $slides = [];

    while ($row = $result->fetch_assoc()) {
        // Una slide senza titolo non ha niente da mostrare, e una senza
        // immagine lascerebbe un buco al posto della figura: si salta invece
        // di pubblicare mezza scheda.
        $title = home_slides_pick($row, 'titolo', $english);
        $media = trim((string)$row['media']);

        if ($title === '' || $media === '') {
            continue;
        }

        $slides[] = [
            'media' => $media,
            'title' => $title,
            'description' => home_slides_pick($row, 'descrizione', $english),
            'buttonText' => home_slides_pick($row, 'testo_bottone', $english),
            'link' => home_slides_link((string)$row['link'], $lang),
        ];
    }

    $result->free();

    return $slides;
}

/**
 * Le slide di riserva, per quando home_slides_load() torna vuota.
 *
 * La homepage stampa la sezione dal server invece di costruirla nel browser,
 * quindi la riserva sta qui. La copia scritta dentro assets/home-v5/home.js
 * serviva alla home di prima: non la legge piu' nessuno.
 *
 * @return array<int, array{media:string, title:string, description:string, buttonText:string, link:string}>
 */
function home_slides_fallback(string $lang = 'it'): array
{
    $english = $lang === 'en';

    $slides = [
        ['/img/profili2.png', '/profile', 'Profili custom!', 'Custom profiles!', 'Personalizza il tuo profilo creando una bio o un portfolio clean.', 'Customise your profile by creating a clean bio or portfolio.', 'Modifica il tuo profilo', 'Edit your profile'],
        ['/img/jay-quadrato.png', 'download', 'Ciao! Sono Jay!', 'Hi! I\'m Jay!', 'Vuoi imparare l’arte dello Spinjitzu?', 'Want to learn the art of Spinjitzu?', 'Scarica il videocorso', 'Download the video course'],
        ['/img/chinese-essay-2.jpg', 'download/yoshukai', 'Hey! Mi chiamo 優希!', 'Hey! My name is 優希!', 'Vuoi imparare l’arte dello Yoshukai?', 'Want to learn the art of Yoshukai?', 'Scarica la guida', 'Download the guide'],
        ['/img/segone4.png', 'achievements', 'Achievements', 'Achievements', 'Sblocca gli achievement del sito e guarda i tuoi progressi.', 'Unlock site achievements and track your progress.', 'Vedi achievement', 'View achievements'],
        ['/img/waguri.jpeg', 'lootbox', 'Lootbox', 'Lootbox', 'Apri lootbox e aggiungi personaggi alla tua collezione.', 'Open lootboxes and add characters to your collection.', 'Apri lootbox', 'Open lootbox'],
        ['/img/pfp choso2 cc.png', 'edits', 'I miei Edit', 'My Edits', 'Guarda gli ultimi edit e video caricati sul sito.', 'Watch the latest edits and videos uploaded to the site.', 'Guarda gli edit', 'Watch edits'],
        ['/img/mentone.jpg', 'goonland/home', 'GoonLand', 'GoonLand', 'La parte più interna e strana del sito.', 'The most internal and "special" part of the site.', 'Entra', 'Enter'],
        ['/img/abdul.jpg', 'global-chat', 'Chat Globale', 'Global Chat', 'Chatta con gli altri utenti del sito.', 'Chat with other users on the site.', 'Apri chat', 'Open chat'],
        ['/img/dukedennis.jpg', 'download', 'Downloads', 'Downloads', 'Scarica contenuti, file e robe del sito.', 'Download content, files and stuff from the site.', 'Vai ai download', 'Go to downloads'],
    ];

    return array_map(static fn(array $slide): array => [
        'media' => $slide[0],
        'title' => $english ? $slide[3] : $slide[2],
        'description' => $english ? $slide[5] : $slide[4],
        'buttonText' => $english ? $slide[7] : $slide[6],
        'link' => $slide[1],
    ], $slides);
}

/**
 * Il campo nella lingua chiesta, con ritorno all'italiano quando la traduzione
 * non e' stata scritta: meglio una slide in italiano che una slide monca.
 */
function home_slides_pick(array $row, string $field, bool $english): string
{
    if ($english) {
        $translated = trim((string)($row[$field . '_en'] ?? ''));
        if ($translated !== '') {
            return $translated;
        }
    }

    return trim((string)($row[$field] ?? ''));
}

/**
 * I link interni si salvano una volta sola, in italiano, e vengono girati alla
 * lingua di chi guarda. Quelli esterni e le ancore restano come sono.
 */
function home_slides_link(string $link, string $lang): string
{
    $link = trim($link);

    if ($link === '' || $lang === 'it') {
        return $link;
    }

    if (preg_match('~^(https?:)?//~i', $link) || str_starts_with($link, '#')) {
        return $link;
    }

    return preg_replace('~^/it(/|$)~', '/' . $lang . '$1', $link) ?? $link;
}
