<?php

/**
 * Quello che succede intorno a una pull, dopo il commit: achievement,
 * annuncio su Discord delle pull piu' rare, wishlist.
 *
 * Nessuna di queste funzioni lancia: una pull gia' salvata non deve mai
 * fallire per un premio o un messaggio che non parte.
 */

require_once __DIR__ . '/schema.php';

/* ── Achievement ──────────────────────────────────────────────────────── */

/**
 * Assegna un achievement se esiste e l'utente non ce l'ha. Come faceva
 * set_achievement dal browser: niente Godos, solo l'achievement.
 */
function gacha_grant_achievement(mysqli $mysqli, int $userId, int $achievementId): bool
{
    try {
        $stmt = $mysqli->prepare(
            'INSERT INTO utenti_achievement (utente_id, achievement_id, data)
             SELECT ?, ?, NOW() FROM DUAL
             WHERE EXISTS (SELECT 1 FROM achievement WHERE id = ?)
               AND NOT EXISTS (SELECT 1 FROM utenti_achievement WHERE utente_id = ? AND achievement_id = ?)'
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('iiiii', $userId, $achievementId, $achievementId, $userId, $achievementId);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();
        return $ok;
    } catch (Throwable $e) {
        error_log('[gacha achievement] ' . $e->getMessage());
        return false;
    }
}

/**
 * Gli achievement del gacha, con le soglie che prima controllava gacha.js:
 * prima pull, 100 e 500 casse aperte, 10 comuni di fila, 100 personaggi.
 * Restituisce gli id appena sbloccati, per il popup.
 */
function gacha_award_achievements(mysqli $mysqli, int $userId): array
{
    $ids = [GACHA_ACH_FIRST_PULL, GACHA_ACH_100_BOXES, GACHA_ACH_500_BOXES, GACHA_ACH_10_COMMONS, GACHA_ACH_100_CHARACTERS];
    $granted = [];

    try {
        $have = [];
        $in = implode(',', array_map('intval', $ids));
        $stmt = $mysqli->prepare("SELECT achievement_id FROM utenti_achievement WHERE utente_id = ? AND achievement_id IN ($in)");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_row()) {
            $have[(int)$row[0]] = true;
        }
        $stmt->close();

        $missing = array_values(array_filter($ids, static fn($id) => !isset($have[$id])));
        if (!$missing) {
            return [];
        }

        $want = [];
        if (in_array(GACHA_ACH_FIRST_PULL, $missing, true)) {
            $want[] = GACHA_ACH_FIRST_PULL;
        }

        if (array_intersect([GACHA_ACH_100_BOXES, GACHA_ACH_500_BOXES, GACHA_ACH_100_CHARACTERS], $missing)) {
            $stmt = $mysqli->prepare('SELECT COALESCE(SUM(`quantità`), 0) AS casse, COUNT(*) AS unici FROM utenti_personaggi WHERE utente_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc() ?: [];
            $stmt->close();
            $boxes = (int)($row['casse'] ?? 0);
            $unique = (int)($row['unici'] ?? 0);
            if ($boxes >= 100) $want[] = GACHA_ACH_100_BOXES;
            if ($boxes >= 500) $want[] = GACHA_ACH_500_BOXES;
            if ($unique >= 100) $want[] = GACHA_ACH_100_CHARACTERS;
        }

        if (in_array(GACHA_ACH_10_COMMONS, $missing, true) && gacha_schema($mysqli)['history']) {
            $stmt = $mysqli->prepare('SELECT `rarità` FROM gacha_pull_history WHERE utente_id = ? ORDER BY id DESC LIMIT 10');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            $commons = 0;
            while ($row = $res->fetch_row()) {
                if (gacha_rarity_key($row[0]) !== 'comune') {
                    break;
                }
                $commons++;
            }
            $stmt->close();
            if ($commons >= 10) $want[] = GACHA_ACH_10_COMMONS;
        }

        foreach (array_intersect($want, $missing) as $id) {
            if (gacha_grant_achievement($mysqli, $userId, (int)$id)) {
                $granted[] = (int)$id;
            }
        }
    } catch (Throwable $e) {
        error_log('[gacha achievement] ' . $e->getMessage());
    }

    return $granted;
}

/* ── Wishlist ─────────────────────────────────────────────────────────── */

/** Chi ottiene un personaggio lo toglie dalla sua wishlist. */
function gacha_wishlist_remove(mysqli $mysqli, int $userId, array $characterIds): void
{
    $characterIds = array_values(array_unique(array_filter(array_map('intval', $characterIds))));
    if (!$characterIds || !gacha_schema($mysqli)['wishlist']) {
        return;
    }
    try {
        $in = implode(',', $characterIds);
        $stmt = $mysqli->prepare("DELETE FROM utenti_wishlist WHERE utente_id = ? AND personaggio_id IN ($in)");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        error_log('[gacha wishlist] ' . $e->getMessage());
    }
}

/**
 * Avvisa chi ha in wishlist un personaggio featured di un banner appena
 * partito. Non c'e' un cron: la funzione gira quando qualcuno apre la
 * lootbox o l'admin salva un banner, e ogni banner si avvisa una volta
 * sola (il flag avvisi_inviati si prende con un UPDATE atomico, cosi' due
 * richieste insieme non mandano il messaggio due volte).
 */
function gacha_wishlist_dispatch(mysqli $mysqli): void
{
    $schema = gacha_schema($mysqli);
    if (!$schema['v2'] || !$schema['wishlist'] || !gacha_has_col($mysqli, 'gacha_banner', 'avvisi_inviati')) {
        return;
    }

    try {
        $res = $mysqli->query(
            "SELECT id FROM gacha_banner
             WHERE avvisi_inviati = 0 AND attivo = 1
               AND (data_inizio IS NULL OR data_inizio <= NOW())
               AND (data_fine IS NULL OR data_fine >= NOW())
             LIMIT 5"
        );
        $pending = [];
        while ($res && ($row = $res->fetch_row())) {
            $pending[] = (int)$row[0];
        }
        if (!$pending) {
            return;
        }

        require_once __DIR__ . '/../reward_mail.php';
        require_once __DIR__ . '/banners.php';
        $banners = gacha_banners($mysqli);
        $chars = gacha_characters($mysqli);

        foreach ($pending as $bannerId) {
            $claim = $mysqli->prepare('UPDATE gacha_banner SET avvisi_inviati = 1 WHERE id = ? AND avvisi_inviati = 0');
            $claim->bind_param('i', $bannerId);
            $claim->execute();
            $mine = $claim->affected_rows > 0;
            $claim->close();
            if (!$mine) {
                continue;
            }

            $banner = null;
            foreach ($banners as $b) {
                if ((int)($b['id'] ?? 0) === $bannerId) {
                    $banner = $b;
                }
            }
            if (!$banner || !$banner['featured']) {
                continue;
            }

            foreach ($banner['featured'] as $entry) {
                $character = $chars[(int)$entry['id']] ?? null;
                if (!$character) {
                    continue;
                }

                $stmt = $mysqli->prepare(
                    'SELECT w.utente_id FROM utenti_wishlist w
                     LEFT JOIN utenti_personaggi up ON up.utente_id = w.utente_id AND up.personaggio_id = w.personaggio_id
                     WHERE w.personaggio_id = ? AND up.utente_id IS NULL'
                );
                $characterId = (int)$character['id'];
                $stmt->bind_param('i', $characterId);
                $stmt->execute();
                $recipients = array_map(static fn($r) => (int)$r[0], $stmt->get_result()->fetch_all(MYSQLI_NUM));
                $stmt->close();
                if (!$recipients) {
                    continue;
                }

                $name = $character['nome'];
                $bannerIt = $banner['nome'];
                $bannerEn = $banner['nome_en'] ?: $banner['nome'];
                $key = rawurlencode($banner['key']);
                cripsum_send_reward_mail(
                    $mysqli,
                    null,
                    $recipients,
                    "$name è nel gacha!",
                    "$name is in the gacha!",
                    "Un personaggio della tua wishlist, $name, è appena arrivato come rate-up nel banner «$bannerIt». Lo trovi nella lootbox: /it/lootbox?banner=$key",
                    "A character from your wishlist, $name, just arrived as a rate-up in the «$bannerEn» banner. Find it in the lootbox: /en/lootbox?banner=$key",
                    [],
                    'system'
                );
            }
        }
    } catch (Throwable $e) {
        error_log('[gacha wishlist dispatch] ' . $e->getMessage());
    }
}

/* ── Annunci su Discord ───────────────────────────────────────────────── */

/**
 * Le pull segreto/theone si annunciano nel canale #gacha del server, tramite
 * il bot (endpoint /v1/announce, target logico "gacha"). Solo per chi ha il
 * profilo pubblico: un profilo privato non finisce in un canale pubblico.
 *
 * Gli annunci si mettono in coda e partono con gacha_flush_announcements()
 * dopo aver mandato la risposta, cosi' la pull non aspetta il bot.
 */
function gacha_queue_announcements(mysqli $mysqli, int $userId, array $banner, array $pulls, string $source = 'web'): void
{
    $top = array_values(array_filter($pulls, static fn($p) => gacha_rarity_tier($p['character']['rarita']) >= 6));
    if (!$top) {
        return;
    }

    try {
        $schema = gacha_schema($mysqli);
        $cols = 'username' . ($schema['visibility'] ? ', profile_visibility' : '') . (gacha_has_col($mysqli, 'utenti', 'discord_id') ? ', discord_id' : '');
        $stmt = $mysqli->prepare("SELECT $cols FROM utenti WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user || (string)($user['profile_visibility'] ?? 'public') !== 'public') {
            return;
        }

        foreach ($top as $pull) {
            $GLOBALS['__gacha_announcements'][] = [
                'username' => (string)$user['username'],
                'discord_id' => (string)($user['discord_id'] ?? ''),
                'character' => $pull['character'],
                'banner' => $banner['nome'],
                'featured' => $pull['featured'],
                'is_new' => $pull['is_new'],
                'source' => $source,
            ];
        }
    } catch (Throwable $e) {
        error_log('[gacha announce] ' . $e->getMessage());
    }
}

/**
 * Chiude la risposta al browser (se il server lo permette) e poi manda gli
 * annunci in coda. Va chiamata come ultima cosa dagli endpoint di pull.
 */
function gacha_flush_announcements(): void
{
    $queue = $GLOBALS['__gacha_announcements'] ?? [];
    $GLOBALS['__gacha_announcements'] = [];
    if (!$queue || !function_exists('curl_init')) {
        return;
    }

    if (function_exists('fastcgi_finish_request')) {
        @fastcgi_finish_request();
    } elseif (function_exists('litespeed_finish_request')) {
        @litespeed_finish_request();
    }

    require_once __DIR__ . '/../bot_client.php';

    foreach (array_slice($queue, 0, 5) as $item) {
        $c = $item['character'];
        $rarity = gacha_rarity_label($c['rarita'], 'it');
        $image = $c['img_url'] ? 'https://cripsum.com' . gacha_media($c['img_url'], '/img/') : null;
        $mention = $item['discord_id'] !== '' ? '<@' . $item['discord_id'] . '>' : '@' . $item['username'];
        $lines = [
            "$mention ha trovato **{$c['nome']}** ($rarity) nel banner **{$item['banner']}**!",
        ];
        if ($item['featured']) {
            $lines[] = '🌟 Rate-up vinto';
        }
        if ($item['is_new']) {
            $lines[] = '✨ Prima copia';
        }

        $payload = [
            'target' => 'gacha',
            'title' => '✦ ' . strtoupper($rarity) . ' ✦',
            'description' => implode("\n", $lines),
            'url' => 'https://cripsum.com/u/' . rawurlencode($item['username']),
            'color' => gacha_rarity_defs()[$c['rarita']]['color'] ?? '#a855f7',
            'image' => $image,
            'footer_text' => 'Cripsum Lootbox • ' . date('d/m/Y H:i'),
        ];

        $ch = curl_init(cripsum_bot_endpoint('/v1/announce'));
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => cripsum_bot_headers(),
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status < 200 || $status >= 300) {
            error_log('[gacha announce] non pubblicato (' . $status . '): ' . (is_string($response) ? substr($response, 0, 200) : 'nessuna risposta'));
        }
    }
}
