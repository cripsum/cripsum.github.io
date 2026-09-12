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
