<?php

/**
 * Chi siamo: il team dal database (team_membri), le candidature e i dati
 * pronti per la pagina /it/chisiamo.
 *
 * Riusa i pezzi comuni dello shop (escape, testi che ricadono
 * sull'italiano, percorsi delle immagini, testi della testata in
 * shop_pagine): niente HTML dal database.
 */

require_once __DIR__ . '/../shop/catalog.php';
require_once __DIR__ . '/candidature_files.php';

/** Social mostrati sotto le card, in quest'ordine. */
const CHISIAMO_SOCIALS = ['instagram', 'tiktok', 'youtube', 'twitch', 'discord', 'x', 'sito'];

const CHISIAMO_CANDIDATURA_MAX_FOTO = 5 * 1024 * 1024;

function chisiamo_ready(?mysqli $mysqli): bool
{
    return shop_tables_ready($mysqli, ['team_membri']);
}

function chisiamo_candidature_ready(?mysqli $mysqli): bool
{
    return shop_tables_ready($mysqli, ['team_candidature']);
}

function chisiamo_social_networks(): array
{
    return [
        'instagram' => ['label' => 'Instagram', 'icon' => 'fa-brands fa-instagram'],
        'tiktok'    => ['label' => 'TikTok',    'icon' => 'fa-brands fa-tiktok'],
        'youtube'   => ['label' => 'YouTube',   'icon' => 'fa-brands fa-youtube'],
        'twitch'    => ['label' => 'Twitch',    'icon' => 'fa-brands fa-twitch'],
        'discord'   => ['label' => 'Discord',   'icon' => 'fa-brands fa-discord'],
        'x'         => ['label' => 'X',         'icon' => 'fa-brands fa-x-twitter'],
        'sito'      => ['label' => 'Sito',      'icon' => 'fa-solid fa-globe'],
    ];
}

/**
 * Il testo di una card: prima si neutralizza tutto, poi **grassetto**,
 * [testo](link) e gli a capo. I link accettati sono quelli https e i
 * percorsi del sito (/it/...), che seguono la lingua di chi guarda.
 */
function chisiamo_rich_text(string $text, string $lang): string
{
    $safe = shop_h(trim($text));

    $safe = preg_replace_callback(
        '~\[([^\]\n]{1,120})\]\(([^)\s]{1,300})\)~u',
        static function (array $m) use ($lang): string {
            $url = html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (!shop_valid_link($url)) {
                return $m[0];
            }
            $url = shop_localize_link($url, $lang);
            $external = !str_starts_with($url, '/');
            return '<a href="' . shop_h($url) . '"' . ($external ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . $m[1] . '</a>';
        },
        $safe
    ) ?? $safe;

    $safe = preg_replace('~\*\*(.+?)\*\*~u', '<strong>$1</strong>', $safe) ?? $safe;

    return nl2br($safe, false);
}

/** {"instagram": "https://..."} → le icone da mostrare, gia' controllate. */
function chisiamo_socials(?string $json): array
{
    $data = json_decode((string)$json, true);
    if (!is_array($data)) {
        return [];
    }

    $networks = chisiamo_social_networks();
    $out = [];
    foreach (CHISIAMO_SOCIALS as $key) {
        $url = trim((string)($data[$key] ?? ''));
        if ($url === '' || !preg_match('~^https://[^\s]+$~i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            continue;
        }
        $out[] = ['key' => $key, 'url' => $url] + $networks[$key];
    }

    return $out;
}

/** Il social di un link (per la candidatura approvata): 'sito' se non e' uno dei noti. */
function chisiamo_social_key_from_url(string $url): string
{
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $host = preg_replace('~^(?:www|m|vm)\.~', '', $host) ?? $host;

    return match (true) {
        str_ends_with($host, 'instagram.com') => 'instagram',
        str_ends_with($host, 'tiktok.com') => 'tiktok',
        str_ends_with($host, 'youtube.com') || $host === 'youtu.be' => 'youtube',
        str_ends_with($host, 'twitch.tv') => 'twitch',
        str_ends_with($host, 'discord.gg') || str_ends_with($host, 'discord.com') => 'discord',
        $host === 'x.com' || str_ends_with($host, 'twitter.com') => 'x',
        default => 'sito',
    };
}

/**
 * I membri con il loro account (username e Premium). La colonna is_premium
 * c'e' in produzione, ma una copia vecchia del database non deve rompere
 * la pagina.
 */
function chisiamo_member_rows(mysqli $mysqli, bool $onlyVisible = true): array
{
    $premium = auth_column_exists($mysqli, 'utenti', 'is_premium') ? 'u.is_premium' : '0';

    return shop_fetch_all(
        $mysqli,
        "SELECT m.*, u.username AS profilo_username, $premium AS profilo_premium
         FROM team_membri m
         LEFT JOIN utenti u ON u.id = m.profilo_utente_id
         " . ($onlyVisible ? 'WHERE m.visibile = 1' : '') . "
         ORDER BY m.posizione ASC, m.id ASC"
    );
}

function chisiamo_member_view(array $row, string $lang): array
{
    $username = trim((string)($row['profilo_username'] ?? ''));
    $profileId = (int)($row['profilo_utente_id'] ?? 0);
    $profileUrl = $username !== '' ? '/u/' . rawurlencode($username) : '';
    $link = trim((string)($row['link'] ?? ''));
    $link = $link !== '' && shop_valid_link($link) ? shop_localize_link($link, $lang) : '';

    $photo = shop_asset_url($row['foto'] ?? '');
    if ($photo === '' && $username !== '' && $profileId > 0) {
        $photo = '/includes/get_pfp.php?id=' . $profileId;
    }

    $name = trim((string)$row['nome']);

    return [
        'id' => (int)$row['id'],
        'name' => $name,
        'initials' => mb_strtoupper(mb_substr($name, 0, 1)),
        'photo' => $photo,
        'name_url' => $link !== '' ? $link : $profileUrl,
        'name_external' => $link !== '' && !str_starts_with($link, '/'),
        'profile_url' => $profileUrl,
        'premium' => $username !== '' && (int)($row['profilo_premium'] ?? 0) === 1,
        'tag' => shop_pick($row, 'tag', $lang),
        'description' => chisiamo_rich_text(shop_pick($row, 'descrizione', $lang), $lang),
        'socials' => chisiamo_socials($row['social'] ?? null),
    ];
}

/** Testata e riquadro della candidatura, con i testi di scorta se il pannello li lascia vuoti. */
function chisiamo_page_texts(?mysqli $mysqli, string $lang, array $S): array
{
    $texts = shop_page_texts($mysqli, 'chisiamo', $lang);

    return [
        'title' => $texts['title'] !== '' ? $texts['title'] : $S['title'],
        'subtitle' => $texts['subtitle'] !== '' ? $texts['subtitle'] : $S['subtitle'],
        'join_title' => $texts['note_title'] !== '' ? $texts['note_title'] : $S['join_title'],
        'join_text' => $texts['note'] !== '' ? $texts['note'] : $S['join_text'],
        'join_button' => $texts['link_text'] !== '' ? $texts['link_text'] : $S['join_button'],
    ];
}

/**
 * Il titolo della testata con l'ultima parola colorata, come nella bozza:
 * "Il nostro team di <em>sviluppo</em>".
 */
function chisiamo_title_html(string $title): string
{
    $title = trim($title);
    $pos = mb_strrpos($title, ' ');
    if ($pos === false) {
        return shop_h($title);
    }

    return shop_h(mb_substr($title, 0, $pos)) . ' <em>' . shop_h(mb_substr($title, $pos + 1)) . '</em>';
}

/* ── Candidature ────────────────────────────────────────────────────── */

/**
 * Salva la foto caricata con la candidatura. Torna il nome del file, o un
 * messaggio d'errore come stringa che inizia con "!".
 */
function chisiamo_candidatura_store_foto(array $file, int $userId): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int)($file['size'] ?? 0) <= 0) {
        return '!photo_missing';
    }
    if ((int)$file['size'] > CHISIAMO_CANDIDATURA_MAX_FOTO) {
        return '!photo_too_big';
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if ($ext === null || @getimagesize($file['tmp_name']) === false) {
        return '!photo_type';
    }

    if (!is_dir(CHISIAMO_CANDIDATURE_DIR) && !@mkdir(CHISIAMO_CANDIDATURE_DIR, 0755, true)) {
        return '!server';
    }

    $name = 'u' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], CHISIAMO_CANDIDATURE_DIR . '/' . $name)) {
        return '!server';
    }

    return $name;
}

/** Vero se l'utente ha gia' una candidatura in attesa: se ne tiene una sola alla volta. */
function chisiamo_candidatura_pending(mysqli $mysqli, int $userId): bool
{
    return shop_fetch_one(
        $mysqli,
        "SELECT id FROM team_candidature WHERE utente_id = ? AND stato = 'nuova' LIMIT 1",
        'i',
        [$userId]
    ) !== null;
}
