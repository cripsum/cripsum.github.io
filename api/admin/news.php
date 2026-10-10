<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/news.php';

/**
 * Novita' del sito (pannello: Sito > Novita').
 *
 * GET                  tutte le notizie, anche quelle nascoste.
 * POST action=preview  i due testi come verrebbero salvati, senza salvare:
 *                      e' l'anteprima del modulo.
 * POST action=save     crea (senza id) o modifica (con id).
 * POST action=visible  mostra o nasconde una notizia.
 * POST action=delete   elimina.
 *
 * Le notizie stanno nella tabella cripsum_news; alla homepage le da'
 * api/get_news.php. Il testo si salva gia' pulito (includes/news.php), perche'
 * la finestra delle novita' lo mette nella pagina cosi' com'e'.
 */

const NEWS_ADMIN_TABLE = 'cripsum_news';
/** Quante ne mostra la finestra della homepage (il LIMIT di api/get_news.php). */
const NEWS_ADMIN_PUBLIC_LIMIT = 30;

$newsMe = (int)($adminUser['id'] ?? 0);

function news_admin_len(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

/** Una riga della tabella come la vuole il pannello. */
function news_admin_row(array $row): array
{
    return [
        'id' => (int)$row['id'],
        'versione' => (string)($row['versione'] ?? ''),
        'titolo' => (string)$row['titolo'],
        'titolo_en' => (string)($row['titolo_en'] ?? ''),
        'tag' => (string)($row['tag'] ?? ''),
        'tag_en' => (string)($row['tag_en'] ?? ''),
        'contenuto' => (string)$row['contenuto'],
        'contenuto_en' => (string)($row['contenuto_en'] ?? ''),
        'immagine' => (string)($row['immagine'] ?? ''),
        'pinned' => (int)$row['pinned'] === 1,
        'visibile' => (int)$row['visibile'] === 1,
        'data' => (string)$row['data_news'],
    ];
}

function news_admin_find(mysqli $mysqli, int $id): ?array
{
    $stmt = $mysqli->prepare('SELECT * FROM ' . NEWS_ADMIN_TABLE . ' WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ? news_admin_row($row) : null;
}

/** L'elenco nello stesso ordine della finestra: prima quelle in evidenza, poi dalla piu' recente. */
function news_admin_list(mysqli $mysqli): array
{
    $news = [];
    $result = $mysqli->query('SELECT * FROM ' . NEWS_ADMIN_TABLE . ' ORDER BY pinned DESC, data_news DESC, id DESC LIMIT 300');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $news[] = news_admin_row($row);
        }
        $result->free();
    }

    return $news;
}

/** Un campo di testo corto: tagliato agli spazi, vuoto = null, troppo lungo = errore. */
function news_admin_text(array $input, string $key, string $label, int $max, bool $required = false): ?string
{
    $value = trim(preg_replace('~\s+~u', ' ', (string)($input[$key] ?? '')) ?? '');

    if ($value === '') {
        if ($required) {
            admin_fail($label . ': campo obbligatorio.', 422, ['field' => $key]);
        }
        return null;
    }
    if (news_admin_len($value) > $max) {
        admin_fail($label . ': al massimo ' . $max . ' caratteri.', 422, ['field' => $key]);
    }

    return $value;
}

/** Il testo di una notizia, pulito. */
function news_admin_body(array $input, string $key, string $label, bool $required = false): ?string
{
    $value = cripsum_news_format((string)($input[$key] ?? ''));

    // Solo tag e spazi (un paragrafo vuoto) non e' un testo.
    if (preg_replace('~[\s\x{00A0}]+~u', '', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') {
        if ($required) {
            admin_fail($label . ': scrivi il testo della notizia.', 422, ['field' => $key]);
        }
        return null;
    }
    if (news_admin_len($value) > CRIPSUM_NEWS_CONTENT_MAX) {
        admin_fail($label . ': troppo lungo (al massimo ' . CRIPSUM_NEWS_CONTENT_MAX . ' caratteri).', 422, ['field' => $key]);
    }

    return $value;
}

try {
    $ready = admin_table_exists($mysqli, NEWS_ADMIN_TABLE);

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        admin_ok([
            'news' => $ready ? news_admin_list($mysqli) : [],
            // Falso se manca la tabella: migrations/2026_10_10_cripsum_news.sql la crea.
            'ready' => $ready,
            'public_limit' => NEWS_ADMIN_PUBLIC_LIMIT,
            'limits' => ['contenuto' => CRIPSUM_NEWS_CONTENT_MAX],
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        admin_fail('Metodo non valido.', 405);
    }

    $input = admin_input();
    $action = (string)($input['action'] ?? '');

    if ($action === 'preview') {
        admin_ok([
            'contenuto' => cripsum_news_format((string)($input['contenuto'] ?? '')),
            'contenuto_en' => cripsum_news_format((string)($input['contenuto_en'] ?? '')),
        ]);
    }

    if (!$ready) {
        admin_fail('Manca la tabella delle novità: applica la migrazione 2026_10_10_cripsum_news.sql.', 409);
    }

    if ($action === 'save') {
        $id = (int)($input['id'] ?? 0);
        $before = $id > 0 ? news_admin_find($mysqli, $id) : null;
        if ($id > 0 && !$before) {
            admin_fail('Questa notizia non esiste più.', 404);
        }

        $titolo = news_admin_text($input, 'titolo', 'Titolo', 200, true);
        $titoloEn = news_admin_text($input, 'titolo_en', 'Titolo in inglese', 200);
        $versione = news_admin_text($input, 'versione', 'Versione', 20);
        $tag = news_admin_text($input, 'tag', 'Tipo', 50);
        $tagEn = news_admin_text($input, 'tag_en', 'Tipo in inglese', 50);
        $contenuto = news_admin_body($input, 'contenuto', 'Testo', true);
        $contenutoEn = news_admin_body($input, 'contenuto_en', 'Testo in inglese');

        $immagine = cripsum_news_image((string)($input['immagine'] ?? ''));
        if ($immagine === null) {
            admin_fail('Immagine: usa un file del sito (/img/...) o un indirizzo https completo.', 422, ['field' => 'immagine']);
        }
        $immagine = $immagine === '' ? null : $immagine;

        $data = trim((string)($input['data'] ?? ''));
        if ($data === '') {
            $data = date('Y-m-d');
        }
        $parsed = DateTime::createFromFormat('!Y-m-d', $data);
        if (!$parsed || $parsed->format('Y-m-d') !== $data) {
            admin_fail('Data non valida.', 422, ['field' => 'data']);
        }

        $pinned = !empty($input['pinned']) && $input['pinned'] !== '0' ? 1 : 0;
        $visibile = !empty($input['visibile']) && $input['visibile'] !== '0' ? 1 : 0;

        if ($before) {
            $stmt = $mysqli->prepare('UPDATE ' . NEWS_ADMIN_TABLE . ' SET versione = ?, titolo = ?, titolo_en = ?, tag = ?, tag_en = ?, contenuto = ?, contenuto_en = ?, immagine = ?, pinned = ?, visibile = ?, data_news = ? WHERE id = ?');
            if (!$stmt) {
                admin_fail('Non riesco a salvare la notizia.', 500);
            }
            $stmt->bind_param('ssssssssiisi', $versione, $titolo, $titoloEn, $tag, $tagEn, $contenuto, $contenutoEn, $immagine, $pinned, $visibile, $data, $id);
        } else {
            $stmt = $mysqli->prepare('INSERT INTO ' . NEWS_ADMIN_TABLE . ' (versione, titolo, titolo_en, tag, tag_en, contenuto, contenuto_en, immagine, pinned, visibile, data_news) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            if (!$stmt) {
                admin_fail('Non riesco a salvare la notizia.', 500);
            }
            $stmt->bind_param('ssssssssiis', $versione, $titolo, $titoloEn, $tag, $tagEn, $contenuto, $contenutoEn, $immagine, $pinned, $visibile, $data);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            admin_fail('Non riesco a salvare la notizia.', 500);
        }
        if (!$before) {
            $id = (int)$stmt->insert_id;
        }
        $stmt->close();

        // L'immagine di prima, se e' stata cambiata e non serve piu' a nessuno, va via.
        if ($before && $before['immagine'] !== '' && $before['immagine'] !== (string)$immagine) {
            admin_media_cleanup($mysqli, [$before['immagine']], $newsMe);
        }

        admin_log($mysqli, $newsMe, $before ? 'news_update' : 'news_create', null, [
            'news_id' => $id,
            'titolo' => $titolo,
            'visibile' => $visibile,
        ]);

        admin_ok(['news' => news_admin_list($mysqli), 'id' => $id, 'created' => !$before]);
    }

    if ($action === 'visible' || $action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $before = $id > 0 ? news_admin_find($mysqli, $id) : null;
        if (!$before) {
            admin_fail('Questa notizia non esiste più.', 404);
        }

        if ($action === 'visible') {
            $visibile = !empty($input['visibile']) && $input['visibile'] !== '0' ? 1 : 0;
            $stmt = $mysqli->prepare('UPDATE ' . NEWS_ADMIN_TABLE . ' SET visibile = ? WHERE id = ?');
            if (!$stmt) {
                admin_fail('Non riesco a cambiare la notizia.', 500);
            }
            $stmt->bind_param('ii', $visibile, $id);
        } else {
            $stmt = $mysqli->prepare('DELETE FROM ' . NEWS_ADMIN_TABLE . ' WHERE id = ?');
            if (!$stmt) {
                admin_fail('Non riesco a eliminare la notizia.', 500);
            }
            $stmt->bind_param('i', $id);
        }

        if (!$stmt->execute()) {
            $stmt->close();
            admin_fail($action === 'delete' ? 'Non riesco a eliminare la notizia.' : 'Non riesco a cambiare la notizia.', 500);
        }
        $stmt->close();

        if ($action === 'delete') {
            if ($before['immagine'] !== '') {
                admin_media_cleanup($mysqli, [$before['immagine']], $newsMe);
            }
            admin_log($mysqli, $newsMe, 'news_delete', null, ['news_id' => $id, 'titolo' => $before['titolo']]);
        } else {
            admin_log($mysqli, $newsMe, 'news_visible', null, ['news_id' => $id, 'titolo' => $before['titolo'], 'visibile' => $visibile]);
        }

        admin_ok(['news' => news_admin_list($mysqli)]);
    }

    admin_fail('Azione non valida.');
} catch (Throwable $e) {
    error_log('[admin/news] ' . $e->getMessage());
    admin_fail('Errore nelle novità.', 500);
}
