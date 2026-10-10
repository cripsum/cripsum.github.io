<?php
/**
 * Novita' del sito: la finestra «News & Changelog» della homepage.
 *
 * Le notizie stanno nella tabella cripsum_news. Le legge api/get_news.php e
 * le scrive il pannello admin (api/admin/news.php). Qui c'e' quello che serve
 * prima di salvarne una: la pulizia del testo e il controllo dell'immagine.
 *
 * Il testo di una notizia e' HTML, e la finestra lo mette nella pagina cosi'
 * com'e' per chiunque apra la homepage. Per questo nel database entra gia'
 * pulito: restano solo i tag di CRIPSUM_NEWS_HTML_TAGS, senza attributi. Fa
 * eccezione l'indirizzo dei link, che puo' essere solo una pagina del sito,
 * un indirizzo http(s) o un mailto.
 */

const CRIPSUM_NEWS_HTML_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a', 'code'];

/** Il testo di una notizia, gia' pulito: la colonna e' TEXT (65.535 byte). */
const CRIPSUM_NEWS_CONTENT_MAX = 20000;

/** L'indirizzo di un link, preso dagli attributi del tag. Null se non e' uno di quelli ammessi. */
function cripsum_news_link(string $attributes): ?string
{
    if (!preg_match('~\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))~i', $attributes, $m)) {
        return null;
    }

    $href = ($m[1] ?? '') !== '' ? $m[1] : ((($m[2] ?? '') !== '') ? $m[2] : ($m[3] ?? ''));
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($href === '' || strlen($href) > 500 || preg_match('~[\x00-\x20\x7f"\'<>`\\\\]~', $href)) {
        return null;
    }

    // Una pagina del sito (/it/...): non //host, che sembra interno e porta fuori.
    if (preg_match('~^/(?!/)~', $href)) {
        return $href;
    }
    if (preg_match('~^https?://[^/]~i', $href) || preg_match('~^mailto:[^@]+@[^@]+$~i', $href)) {
        return $href;
    }

    return null;
}

/**
 * Tiene i tag ammessi e scrive come testo tutto il resto.
 *
 * Non corregge l'HTML che riceve: lo riscrive. In uscita ci sono solo testo
 * con i caratteri speciali scritti per esteso e tag composti qui, quindi
 * quello che arriva non puo' portare dentro attributi, script o stili. I tag
 * rimasti aperti vengono chiusi.
 */
function cripsum_news_clean_html(string $html): string
{
    $html = str_replace("\0", '', $html);
    $html = preg_replace('~<!--.*?(?:-->|$)~s', '', $html) ?? '';
    // Di questi non deve restare nemmeno quello che contengono.
    $html = preg_replace('~<(script|style|iframe|object|embed|svg|math|template|noscript|textarea|select|title|head)\b[^>]*>.*?</\1\s*>~is', '', $html) ?? '';

    $text = static fn(string $raw): string => htmlspecialchars(
        html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ENT_NOQUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $out = '';
    $open = [];

    foreach (preg_split('~(<[^<>]*>)~', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $index => $part) {
        if ($index % 2 === 0) {
            $out .= $text($part);
            continue;
        }

        if (!preg_match('~^<(/?)([a-zA-Z][a-zA-Z0-9]*)\b([^<>]*)>$~s', $part, $m)) {
            // «<3>» o «< b >» sono testo; dichiarazioni e istruzioni (<!…>, <?…>) spariscono.
            if (!preg_match('~^<[!?]~', $part)) {
                $out .= $text($part);
            }
            continue;
        }

        $closing = $m[1] === '/';
        $tag = strtolower($m[2]);

        // Un tag non ammesso sparisce, il testo che conteneva resta.
        if (!in_array($tag, CRIPSUM_NEWS_HTML_TAGS, true)) {
            continue;
        }

        if ($tag === 'br') {
            $out .= $closing ? '' : '<br>';
            continue;
        }

        if ($closing) {
            $at = null;
            for ($i = count($open) - 1; $i >= 0; $i--) {
                if ($open[$i] === $tag) {
                    $at = $i;
                    break;
                }
            }
            // Si chiude anche quello che era rimasto aperto dentro.
            while ($at !== null && count($open) > $at) {
                $out .= '</' . array_pop($open) . '>';
            }
            continue;
        }

        if ($tag === 'a') {
            $href = cripsum_news_link($m[3]);
            if ($href === null) {
                continue;
            }
            $out .= '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
                . (preg_match('~^https?://~i', $href) ? ' target="_blank" rel="noopener noreferrer"' : '') . '>';
            $open[] = 'a';
            continue;
        }

        $out .= '<' . $tag . '>';
        $open[] = $tag;
    }

    while ($open) {
        $out .= '</' . array_pop($open) . '>';
    }

    // Gli spazi rimasti ai bordi di paragrafi e voci, poi quelli rimasti vuoti
    // dopo aver tolto quello che contenevano.
    $out = preg_replace(['~<(p|li)>[ \t]+~', '~[ \t]+</(p|li)>~'], ['<$1>', '</$1>'], $out) ?? $out;
    do {
        $out = preg_replace('~<(p|li|ul|ol)>\s*</\1>\n?~', '', $out, -1, $removed) ?? $out;
    } while ($removed > 0);

    return trim($out);
}

/**
 * Dal testo scritto nel pannello a quello che si salva.
 *
 * Chi scrive puo' non usare i tag: se nel testo non ci sono paragrafi o
 * elenchi, le righe vuote separano i paragrafi e le righe che cominciano con
 * «- » diventano un elenco. Poi passa tutto da cripsum_news_clean_html().
 */
function cripsum_news_format(string $text): string
{
    $text = trim(str_replace(["\r\n", "\r"], "\n", $text));
    if ($text === '') {
        return '';
    }

    if (!preg_match('~</?(?:p|ul|ol|li|br)\b~i', $text)) {
        $html = '';
        $lines = [];
        $items = [];

        $flush = static function () use (&$html, &$lines, &$items): void {
            if ($lines) {
                $html .= '<p>' . implode('<br>', $lines) . "</p>\n";
            }
            if ($items) {
                $html .= "<ul>\n<li>" . implode("</li>\n<li>", $items) . "</li>\n</ul>\n";
            }
            $lines = [];
            $items = [];
        };

        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                $flush();
            } elseif (preg_match('~^[-*•]\s+(.+)$~u', $line, $m)) {
                if ($lines) {
                    $flush();
                }
                $items[] = $m[1];
            } else {
                if ($items) {
                    $flush();
                }
                $lines[] = $line;
            }
        }
        $flush();
        $text = $html;
    }

    return cripsum_news_clean_html($text);
}

/**
 * L'immagine di una notizia: un file del sito (/img/...) o un indirizzo
 * http(s). Stringa vuota = nessuna immagine; null = valore non valido.
 */
function cripsum_news_image(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (strlen($value) > 300) {
        return null;
    }

    if (preg_match('~^/(?!/)[A-Za-z0-9_\-./%]+\.(?:jpe?g|png|gif|webp|avif)$~i', $value) && !str_contains($value, '..')) {
        return $value;
    }
    if (preg_match('~^https?://~i', $value) && filter_var($value, FILTER_VALIDATE_URL) && !preg_match('~["\'<>\s]~', $value)) {
        return $value;
    }

    return null;
}
