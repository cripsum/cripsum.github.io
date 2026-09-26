<?php

/**
 * Motore delle pull.
 *
 * Una sola funzione, gacha_pull(), per la pull singola, la multi e il bot
 * Discord: prima erano tre copie dello stesso algoritmo con piccole
 * differenze. Tutto il caso e' lato server.
 *
 * Il comportamento dei banner di sempre non cambia: stessi pesi, stesso soft
 * e hard pity (standard 70/90, evento 65/80), stesso 50/50 con garantito
 * condiviso fra tutti i banner evento, e se perdi il 50/50 arriva un segreto
 * del pool standard. In piu' ci sono i banner con piu' rate-up, con pool
 * esclusivi o per categoria, con pesi e quote per rarita', con un pity
 * dedicato, pull gratuite e limiti, e la garanzia sulla multi.
 */

require_once __DIR__ . '/banners.php';
require_once __DIR__ . '/notify.php';
require_once __DIR__ . '/../mission_tracker.php';

class GachaPullException extends RuntimeException
{
    public int $http;
    public string $codeName;
    public array $extra;

    public function __construct(string $message, int $http = 400, string $codeName = 'SERVER_ERROR', array $extra = [])
    {
        parent::__construct($message, $http);
        $this->http = $http;
        $this->codeName = $codeName;
        $this->extra = $extra;
    }
}

/** Esegue uno statement e lancia se fallisce (anche con mysqli_report spento). */
function gacha_exec(mysqli_stmt $stmt, string $what): void
{
    if (!$stmt->execute()) {
        throw new RuntimeException('Query ' . $what . ' fallita: ' . $stmt->error);
    }
}

/* ── Caso ─────────────────────────────────────────────────────────────── */

function gacha_rand_float(): float
{
    return random_int(0, PHP_INT_MAX - 1) / PHP_INT_MAX;
}

/** Estrae una chiave da una mappa chiave => peso. Null se i pesi sono tutti zero. */
function gacha_pick_key(array $weights): ?string
{
    $total = 0.0;
    foreach ($weights as $weight) {
        $total += max(0.0, (float)$weight);
    }
    if ($total <= 0) {
        return null;
    }

    $roll = gacha_rand_float() * $total;
    $last = null;
    foreach ($weights as $key => $weight) {
        $weight = max(0.0, (float)$weight);
        if ($weight <= 0) {
            continue;
        }
        $last = (string)$key;
        $roll -= $weight;
        if ($roll <= 0) {
            return (string)$key;
        }
    }
    return $last;
}

/** Estrae una voce del pool usando il suo `peso`. */
function gacha_pick_entry(array $entries): ?array
{
    if (!$entries) {
        return null;
    }
    $weights = [];
    foreach ($entries as $i => $entry) {
        $weights[$i] = max(0.0, (float)($entry['peso'] ?? 1.0));
    }
    $key = gacha_pick_key($weights);
    return $key === null ? $entries[array_key_first($entries)] : $entries[(int)$key];
}

/* ── Rarita' ──────────────────────────────────────────────────────────── */

/**
 * I pesi di una pull con il pity applicato.
 *
 * Restituisce ['hard' => bool, 'weights' => [...]]. Oltre il soft pity i
 * pesi delle rarita' alte salgono di un tanto a pull; all'hard pity si
 * estrae solo fra le rarita' dalla soglia in su, con la ripartizione del
 * profilo. Le rarita' che il pool non ha restano a zero.
 *
 * $pity sono le pull gia' fatte senza la soglia: con hard 80 la pull
 * garantita e' l'ottantesima, cioe' quella che parte da pity 79.
 */
function gacha_pull_weights(array $banner, array $pool, array $profile, int $pity): array
{
    $weights = gacha_banner_weights($banner, $pool);

    if ($pity + 1 >= $profile['hard']) {
        $hard = [];
        foreach ($profile['hard_pesi'] as $key => $weight) {
            if (!empty($pool[$key])) {
                $hard[$key] = (float)$weight;
            }
        }
        if (array_sum($hard) > 0) {
            return ['hard' => true, 'weights' => $hard];
        }
        // Il pool non ha le rarita' del profilo: si prende la migliore che ha
        // dalla soglia in su, se c'e'.
        $above = [];
        foreach ($weights as $key => $weight) {
            if ($weight > 0 && gacha_rarity_rank($key) >= gacha_rarity_rank($profile['soglia'])) {
                $above[$key] = $weight;
            }
        }
        if ($above) {
            return ['hard' => true, 'weights' => $above];
        }
    }

    if ($pity >= $profile['soft']) {
        $step = $pity - $profile['soft'] + 1;
        foreach ($profile['soft_bonus'] as $key => $bonus) {
            if (isset($weights[$key]) && !empty($pool[$key])) {
                $weights[$key] += $step * (float)$bonus;
            }
        }
    }

    return ['hard' => false, 'weights' => $weights];
}

/**
 * Estrae la rarita' di una pull. `$minRarity` limita l'estrazione alle
 * rarita' da quella in su (garanzia della multi), mantenendo i pesi
 * relativi; se il pool non ne ha, non limita nulla.
 */
function gacha_roll_rarity(array $banner, array $pool, array $profile, int $pity, ?string $minRarity = null): string
{
    $calc = gacha_pull_weights($banner, $pool, $profile, $pity);
    $weights = $calc['weights'];

    if ($minRarity !== null && !$calc['hard']) {
        $limited = [];
        foreach ($weights as $key => $weight) {
            if (gacha_rarity_rank($key) >= gacha_rarity_rank($minRarity)) {
                $limited[$key] = $weight;
            }
        }
        if (array_sum($limited) > 0) {
            $weights = $limited;
        }
    }

    $key = gacha_pick_key($weights);
    if ($key !== null) {
        return $key;
    }
    foreach (gacha_rarity_keys() as $fallback) {
        if (!empty($pool[$fallback])) {
            return $fallback;
        }
    }
    return 'comune';
}

/* ── Personaggio ──────────────────────────────────────────────────────── */

/**
 * Sceglie il personaggio per la rarita' uscita.
 *
 * Se nella fascia della rarita' ci sono featured:
 *  - nella fascia del pity (segreto+theone per i banner evento) vale il
 *    50/50 col garantito del gruppo: vinto = featured, perso = un personaggio
 *    non featured della rarita' piu' bassa della fascia (il "segreto a caso
 *    dello standard" di sempre) e scatta il garantito;
 *  - nelle altre fasce e' un rate-up senza garantito: con la quota esce un
 *    featured, altrimenti uno degli altri della rarita'.
 * Senza featured si estrae fra i personaggi della rarita' col loro peso.
 *
 * $state contiene 'garantito' e la scelta del destino, e viene aggiornato.
 */
function gacha_pick_character(array $banner, array $pool, string $rarity, array &$state): array
{
    $pityTier = gacha_banner_pity_tier($banner);
    $tier = gacha_rarity_tier($rarity);
    $featured = gacha_pool_featured_for($pool, $rarity);
    $outcome = ['entry' => null, 'featured' => false, 'esito' => null];

    if ($featured) {
        $quota = gacha_banner_featured_quota($banner, $rarity);
        $isPityTier = $tier === $pityTier;
        $guaranteed = $isPityTier && !empty($state['garantito']);
        $win = $guaranteed || random_int(1, 100) <= $quota;

        $others = [];
        if (!$win) {
            // Chi perde prende un non featured: prima della rarita' piu' bassa
            // della fascia (come sempre), poi della rarita' uscita.
            $lowest = gacha_tier_rarities($tier)[0] ?? $rarity;
            foreach ([$lowest, $rarity] as $key) {
                $others = array_values(array_filter($pool[$key] ?? [], static fn($e) => !$e['featured']));
                if ($others) {
                    break;
                }
            }
            if (!$others) {
                // Non c'e' nient'altro in quella fascia: vince per forza.
                $win = true;
            }
        }

        if ($win) {
            $outcome['entry'] = gacha_pick_featured($featured, $state);
            $outcome['featured'] = true;
            if ($isPityTier) {
                $outcome['esito'] = 1;
                $state['garantito'] = 0;
            }
        } else {
            $outcome['entry'] = gacha_pick_entry($others);
            if ($isPityTier) {
                $outcome['esito'] = 0;
                $state['garantito'] = 1;
            }
        }

        return $outcome;
    }

    $outcome['entry'] = gacha_pick_entry($pool[$rarity] ?? []);
    if (!$outcome['entry']) {
        foreach (gacha_rarity_keys() as $fallback) {
            if (!empty($pool[$fallback])) {
                $outcome['entry'] = gacha_pick_entry($pool[$fallback]);
                break;
            }
        }
    }
    return $outcome;
}

/**
 * Fra piu' featured vinti: se l'utente ha scelto un bersaglio (destino) e ha
 * accumulato abbastanza punti, esce il bersaglio; altrimenti si estrae col
 * peso, e ogni featured diverso dal bersaglio vale un punto.
 */
function gacha_pick_featured(array $featured, array &$state): array
{
    $target = (int)($state['destino_bersaglio'] ?? 0);
    $max = (int)($state['destino_max'] ?? 0);

    if (count($featured) > 1 && $target > 0 && $max > 0) {
        $targetEntry = null;
        foreach ($featured as $entry) {
            if ((int)$entry['id'] === $target) {
                $targetEntry = $entry;
            }
        }
        if ($targetEntry) {
            if ((int)($state['destino_punti'] ?? 0) >= $max) {
                $state['destino_punti'] = 0;
                $state['destino_cambiato'] = true;
                return $targetEntry;
            }
            $picked = gacha_pick_entry($featured);
            $state['destino_punti'] = (int)$picked['id'] === $target ? 0 : (int)($state['destino_punti'] ?? 0) + 1;
            $state['destino_cambiato'] = true;
            return $picked;
        }
    }

    return gacha_pick_entry($featured);
}

/* ── Stato utente ─────────────────────────────────────────────────────── */

/**
 * Pity e garantito di un gruppo. Standard ed evento stanno nelle colonne
 * storiche di `utenti` (le legge anche chi non conosce i gruppi); gli altri
 * nella tabella gacha_pity.
 */
function gacha_pity_read(mysqli $mysqli, int $userId, string $group, array $userRow, bool $forUpdate = false): array
{
    $legacy = gacha_legacy_pity_groups();
    if (isset($legacy[$group])) {
        $cols = $legacy[$group];
        return [
            'contatore' => (int)($userRow[$cols['contatore']] ?? 0),
            'garantito' => $cols['garantito'] ? (int)($userRow[$cols['garantito']] ?? 0) : 0,
        ];
    }

    if (!gacha_schema($mysqli)['pity']) {
        return ['contatore' => 0, 'garantito' => 0];
    }

    $stmt = $mysqli->prepare('SELECT contatore, garantito FROM gacha_pity WHERE utente_id = ? AND gruppo = ? LIMIT 1' . ($forUpdate ? ' FOR UPDATE' : ''));
    $stmt->bind_param('is', $userId, $group);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return ['contatore' => (int)($row['contatore'] ?? 0), 'garantito' => (int)($row['garantito'] ?? 0)];
}

function gacha_pity_write(mysqli $mysqli, int $userId, string $group, int $counter, int $guaranteed): void
{
    $legacy = gacha_legacy_pity_groups();
    if (isset($legacy[$group])) {
        $cols = $legacy[$group];
        if ($cols['garantito']) {
            $stmt = $mysqli->prepare('UPDATE utenti SET `' . $cols['contatore'] . '` = ?, `' . $cols['garantito'] . '` = ? WHERE id = ?');
            $stmt->bind_param('iii', $counter, $guaranteed, $userId);
        } else {
            $stmt = $mysqli->prepare('UPDATE utenti SET `' . $cols['contatore'] . '` = ? WHERE id = ?');
            $stmt->bind_param('ii', $counter, $userId);
        }
        $stmt->execute();
        $stmt->close();
        return;
    }

    if (!gacha_schema($mysqli)['pity']) {
        return;
    }
    $stmt = $mysqli->prepare(
        'INSERT INTO gacha_pity (utente_id, gruppo, contatore, garantito) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE contatore = VALUES(contatore), garantito = VALUES(garantito)'
    );
    $stmt->bind_param('isii', $userId, $group, $counter, $guaranteed);
    $stmt->execute();
    $stmt->close();
}

/** Pity di tutti i gruppi dei banner visibili, per la pagina. */
function gacha_pity_all(mysqli $mysqli, int $userId, array $groups): array
{
    $userRow = [];
    try {
        $stmt = $mysqli->prepare('SELECT pity_standard, pity_evento, garantito_evento FROM utenti WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $userRow = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
    } catch (Throwable $e) {
        $userRow = [];
    }

    $out = [];
    foreach (array_unique($groups) as $group) {
        try {
            $out[$group] = gacha_pity_read($mysqli, $userId, $group, $userRow);
        } catch (Throwable $e) {
            $out[$group] = ['contatore' => 0, 'garantito' => 0];
        }
    }
    return $out;
}

/** Pull fatte da un utente su un banner (in totale, oggi, gratuite oggi). */
function gacha_banner_usage(mysqli $mysqli, int $userId, string $bannerKey): array
{
    $usage = ['totale' => 0, 'oggi' => 0, 'gratis_oggi' => 0];
    if (!gacha_schema($mysqli)['history']) {
        return $usage;
    }

    $free = gacha_schema($mysqli)['history_v2'] ? 'COALESCE(SUM(gratuita = 1 AND created_at >= CURDATE()), 0)' : '0';
    try {
        $stmt = $mysqli->prepare(
            "SELECT COUNT(*) AS totale, COALESCE(SUM(created_at >= CURDATE()), 0) AS oggi, $free AS gratis_oggi
             FROM gacha_pull_history WHERE utente_id = ? AND banner_id = ?"
        );
        $stmt->bind_param('is', $userId, $bannerKey);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        foreach ($usage as $k => $_) {
            $usage[$k] = (int)($row[$k] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('[gacha] uso banner: ' . $e->getMessage());
    }

    return $usage;
}

/**
 * Banner con un limite fisso di pull per utente (es. il principiante) che
 * l'utente ha gia' finito: non gli si mostra piu'. Il limite giornaliero
 * non conta, il giorno dopo si ricomincia.
 */
function gacha_banner_exhausted(array $banner, ?array $usage): bool
{
    return $banner['limite_pull_utente'] !== null
        && $usage !== null
        && $usage['totale'] >= $banner['limite_pull_utente'];
}

/** Bersaglio del destino scelto su un banner, se c'e'. */
function gacha_destino_read(mysqli $mysqli, int $userId, int $bannerId): ?array
{
    if ($bannerId <= 0 || !gacha_schema($mysqli)['destino']) {
        return null;
    }
    $stmt = $mysqli->prepare('SELECT personaggio_id, punti FROM gacha_destino WHERE utente_id = ? AND banner_id = ? LIMIT 1');
    $stmt->bind_param('ii', $userId, $bannerId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? ['bersaglio' => (int)$row['personaggio_id'], 'punti' => (int)$row['punti']] : null;
}

/* ── Pull ─────────────────────────────────────────────────────────────── */

/**
 * Esegue $qty pull (1-10) su un banner, in un'unica transazione.
 *
 * Opzioni: force_rarity / force_character_id (solo admin, gia' verificati da
 * chi chiama), source ('web' | 'bot').
 *
 * Restituisce ['banner', 'pulls' => [...], 'user' => saldi e pity, ...]; la
 * forma che le API storiche mandano al client la costruiscono
 * gacha_response_single() e gacha_response_multi().
 */
function gacha_pull(mysqli $mysqli, int $userId, $bannerKey, int $qty, array $opts = []): array
{
    $qty = max(1, min(GACHA_MULTI_SIZE, $qty));
    $schema = gacha_schema($mysqli);

    $banner = gacha_banner_get($mysqli, $bannerKey);
    if (!$banner || gacha_banner_status($banner) !== 'attivo') {
        throw new GachaPullException('Banner non trovato o scaduto', 400, 'BANNER_NOT_FOUND');
    }

    $pool = gacha_banner_pool($mysqli, $banner);
    if (gacha_pool_size($pool) === 0) {
        throw new GachaPullException('Il banner non contiene personaggi estraibili.', 500, 'SERVER_ERROR');
    }

    $chars = gacha_characters($mysqli);
    $profile = gacha_banner_pity_profile($banner);
    $group = $banner['pity_gruppo'];
    $pityTier = gacha_banner_pity_tier($banner);
    $bannerKey = $banner['key'];

    $forceRarity = gacha_rarity_key($opts['force_rarity'] ?? null) ?: null;
    $forceCharacter = null;
    if (!empty($opts['force_character_id'])) {
        $forceCharacter = $chars[(int)$opts['force_character_id']] ?? null;
        if (!$forceCharacter) {
            throw new GachaPullException('Personaggio forzato non trovato.', 500, 'FORCED_CHARACTER_NOT_FOUND');
        }
    }

    $mysqli->begin_transaction();

    try {
        $userCols = ['soldi', 'pity_standard', 'pity_evento', 'garantito_evento'];
        if ($schema['shards']) $userCols[] = 'godoshards_balance';
        if ($schema['premium']) $userCols[] = 'is_premium';

        $stmt = $mysqli->prepare('SELECT ' . implode(', ', $userCols) . ' FROM utenti WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$user) {
            throw new GachaPullException('Utente non trovato', 404, 'NOT_FOUND');
        }

        if ($banner['solo_premium'] && (int)($user['is_premium'] ?? 0) !== 1) {
            throw new GachaPullException('Questo banner è riservato agli utenti Premium.', 403, 'PREMIUM_REQUIRED');
        }

        $soldi = (int)$user['soldi'];
        $shards = (int)($user['godoshards_balance'] ?? 0);
        $pity = gacha_pity_read($mysqli, $userId, $group, $user, true);

        // Limiti e pull gratuite del giorno.
        $usage = gacha_banner_usage($mysqli, $userId, $bannerKey);
        if ($banner['limite_pull_utente'] !== null && $usage['totale'] + $qty > $banner['limite_pull_utente']) {
            $left = max(0, $banner['limite_pull_utente'] - $usage['totale']);
            throw new GachaPullException(
                $left > 0 ? "Su questo banner ti restano solo {$left} pull." : 'Hai già fatto tutte le pull di questo banner.',
                403, 'LIMIT_REACHED', ['rimaste' => $left]
            );
        }
        if ($banner['limite_pull_giorno'] !== null && $usage['oggi'] + $qty > $banner['limite_pull_giorno']) {
            $left = max(0, $banner['limite_pull_giorno'] - $usage['oggi']);
            throw new GachaPullException(
                $left > 0 ? "Oggi su questo banner ti restano solo {$left} pull." : 'Per oggi hai finito le pull di questo banner.',
                403, 'DAILY_LIMIT_REACHED', ['rimaste' => $left]
            );
        }
        $freeLeft = $schema['history_v2'] ? max(0, $banner['pull_gratis_giorno'] - $usage['gratis_oggi']) : 0;
        $freeCount = min($freeLeft, $qty);

        // Costo: prima le Shards, il resto in Godos al cambio attuale.
        $godosPerShard = gacha_godos_per_shard($mysqli);
        $costGodos = $banner['costo'] * ($qty - $freeCount);
        $shardsToUse = 0;
        $godosToUse = 0;
        if ($costGodos > 0) {
            $costShards = (int)ceil($costGodos / $godosPerShard);
            $shardsToUse = min($costShards, $shards);
            $godosToUse = ($costShards - $shardsToUse) * $godosPerShard;
            if ($soldi < $godosToUse) {
                throw new GachaPullException(
                    "Valute insufficienti! Hai {$soldi} Godos e {$shards} Godo Shards, ne servono {$godosToUse} Godos e {$shardsToUse} Godo Shards per questa pull.",
                    402, 'NO_POINTS'
                );
            }
        }

        // Destino: conta solo se il banner ha piu' featured nella fascia del pity.
        $state = ['garantito' => $pity['garantito']];
        $topFeatured = gacha_pool_featured_for($pool, gacha_tier_rarities($pityTier)[0] ?? 'segreto');
        if (count($topFeatured) > 1 && $banner['destino_max'] > 0 && $banner['id']) {
            $destino = gacha_destino_read($mysqli, $userId, (int)$banner['id']);
            if ($destino) {
                $state['destino_bersaglio'] = $destino['bersaglio'];
                $state['destino_punti'] = $destino['punti'];
                $state['destino_max'] = $banner['destino_max'];
            }
        }

        // Copie gia' possedute, per sapere cosa e' nuovo senza rileggere.
        $owned = [];
        $stmt = $mysqli->prepare('SELECT personaggio_id, `quantità` AS q FROM utenti_personaggi WHERE utente_id = ?');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $owned[(int)$row['personaggio_id']] = (int)$row['q'];
        }
        $stmt->close();

        $invSql = $schema['inv_visto']
            ? 'INSERT INTO utenti_personaggi (utente_id, personaggio_id, `quantità`, data' . ($schema['inv_ultima'] ? ', ultima_copia_il' : '') . ', visto)
               VALUES (?, ?, 1, NOW()' . ($schema['inv_ultima'] ? ', NOW()' : '') . ', 0)
               ON DUPLICATE KEY UPDATE `quantità` = `quantità` + 1' . ($schema['inv_ultima'] ? ', ultima_copia_il = NOW()' : '')
            : 'INSERT INTO utenti_personaggi (utente_id, personaggio_id, `quantità`, data) VALUES (?, ?, 1, NOW())
               ON DUPLICATE KEY UPDATE `quantità` = `quantità` + 1';
        $stmtInv = $mysqli->prepare($invSql);

        $stmtHist = null;
        if ($schema['history']) {
            $stmtHist = $schema['history_v2']
                ? $mysqli->prepare('INSERT INTO gacha_pull_history (utente_id, banner_id, personaggio_id, `rarità`, pity_al_momento, esito_50_50, is_new, featured, gratuita, pity_gruppo, costo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                : $mysqli->prepare('INSERT INTO gacha_pull_history (utente_id, banner_id, personaggio_id, `rarità`, pity_al_momento, esito_50_50, is_new) VALUES (?, ?, ?, ?, ?, ?, ?)');
        }

        $pulls = [];
        $sawGuaranteeRarity = false;
        $multiGuaranteeUsed = false;

        for ($i = 0; $i < $qty; $i++) {
            $pityBefore = $pity['contatore'];
            $outcome = ['entry' => null, 'featured' => false, 'esito' => null];
            $multiGuarantee = null;

            if ($forceCharacter) {
                $rolled = $forceCharacter['rarita'];
                $outcome['entry'] = ['id' => $forceCharacter['id']];
            } else {
                if ($qty === GACHA_MULTI_SIZE && $banner['garanzia_multi'] && $i === $qty - 1 && !$sawGuaranteeRarity) {
                    $multiGuarantee = GACHA_MULTI_GUARANTEE_RARITY;
                }
                $rolled = $forceRarity ?? gacha_roll_rarity($banner, $pool, $profile, $pityBefore, $multiGuarantee);
                if ($forceRarity && empty($pool[$rolled])) {
                    $rolled = gacha_roll_rarity($banner, $pool, $profile, $pityBefore);
                }
                $outcome = gacha_pick_character($banner, $pool, $rolled, $state);
            }

            if (!$outcome['entry'] || !isset($chars[(int)$outcome['entry']['id']])) {
                throw new GachaPullException("Nessun personaggio trovato (pull $i, rarità: $rolled)", 500, 'SERVER_ERROR');
            }

            $character = $chars[(int)$outcome['entry']['id']];
            $rolledRank = gacha_rarity_rank($rolled);
            if ($rolledRank >= gacha_rarity_rank(GACHA_MULTI_GUARANTEE_RARITY)) {
                $sawGuaranteeRarity = true;
                if ($multiGuarantee !== null) {
                    $multiGuaranteeUsed = true;
                }
            }

            // Pity: +1 a ogni pull, torna a zero quando esce la soglia o piu'.
            $pity['contatore']++;
            if ($rolledRank >= gacha_rarity_rank($profile['soglia'])) {
                $pity['contatore'] = 0;
            }
            if ($outcome['esito'] !== null) {
                $pity['garantito'] = (int)$state['garantito'];
            }

            $characterId = $character['id'];
            $stmtInv->bind_param('ii', $userId, $characterId);
            gacha_exec($stmtInv, 'inventario');
            $isNew = !isset($owned[$characterId]);
            $owned[$characterId] = ($owned[$characterId] ?? 0) + 1;

            $isFree = $i < $freeCount;
            if ($stmtHist) {
                $esito = $outcome['esito'];
                $isNewInt = $isNew ? 1 : 0;
                if ($schema['history_v2']) {
                    $featuredInt = $outcome['featured'] ? 1 : 0;
                    $freeInt = $isFree ? 1 : 0;
                    $cost = $isFree ? 0 : $banner['costo'];
                    $stmtHist->bind_param('isisiiiiisi', $userId, $bannerKey, $characterId, $character['rarita'], $pityBefore, $esito, $isNewInt, $featuredInt, $freeInt, $group, $cost);
                } else {
                    $stmtHist->bind_param('isisiii', $userId, $bannerKey, $characterId, $character['rarita'], $pityBefore, $esito, $isNewInt);
                }
                gacha_exec($stmtHist, 'cronologia');
            }

            $pulls[] = [
                'character' => $character,
                'rolled' => $rolled,
                'is_new' => $isNew,
                'copie' => $owned[$characterId],
                'esito' => $outcome['esito'],
                'featured' => $outcome['featured'],
                'gratuita' => $isFree,
                'pity_snapshot' => $pityBefore,
                'garanzia_multi' => $multiGuarantee !== null && $rolledRank >= gacha_rarity_rank(GACHA_MULTI_GUARANTEE_RARITY),
            ];
        }

        $stmtInv->close();
        if ($stmtHist) {
            $stmtHist->close();
        }

        if ($shardsToUse > 0) {
            $stmt = $mysqli->prepare('UPDATE utenti SET godoshards_balance = godoshards_balance - ? WHERE id = ? AND godoshards_balance >= ?');
            $stmt->bind_param('iii', $shardsToUse, $userId, $shardsToUse);
            $stmt->execute();
            $ok = $stmt->affected_rows > 0;
            $stmt->close();
            if (!$ok) {
                throw new GachaPullException('Shards insufficienti (race condition).', 402, 'NO_POINTS');
            }
        }
        if ($godosToUse > 0) {
            $stmt = $mysqli->prepare('UPDATE utenti SET soldi = soldi - ? WHERE id = ? AND soldi >= ?');
            $stmt->bind_param('iii', $godosToUse, $userId, $godosToUse);
            $stmt->execute();
            $ok = $stmt->affected_rows > 0;
            $stmt->close();
            if (!$ok) {
                throw new GachaPullException('Godos insufficienti (race condition).', 402, 'NO_POINTS');
            }
        }

        gacha_pity_write($mysqli, $userId, $group, $pity['contatore'], $pity['garantito']);

        if (!empty($state['destino_cambiato']) && $banner['id']) {
            $points = (int)$state['destino_punti'];
            $stmt = $mysqli->prepare('UPDATE gacha_destino SET punti = ? WHERE utente_id = ? AND banner_id = ?');
            $bannerIdInt = (int)$banner['id'];
            $stmt->bind_param('iii', $points, $userId, $bannerIdInt);
            $stmt->execute();
            $stmt->close();
        }

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }

    $newIds = [];
    foreach ($pulls as $pull) {
        if ($pull['is_new']) {
            $newIds[] = $pull['character']['id'];
        }
    }

    // Da qui in poi niente puo' annullare la pull: tracciamenti e premi non
    // devono mai rompere la risposta.
    gacha_after_pull($mysqli, $userId, $banner, $pulls, $godosToUse, $shardsToUse, $qty > 1);
    $achievements = gacha_award_achievements($mysqli, $userId);
    gacha_wishlist_remove($mysqli, $userId, $newIds);
    gacha_queue_announcements($mysqli, $userId, $banner, $pulls, $opts['source'] ?? 'web');

    $balances = ['soldi' => $soldi - $godosToUse, 'shards' => $shards - $shardsToUse];
    try {
        $stmt = $mysqli->prepare('SELECT soldi' . ($schema['shards'] ? ', godoshards_balance' : '') . ', pity_standard, pity_evento, garantito_evento FROM utenti WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc() ?: [];
        $stmt->close();
        $balances = [
            'soldi' => (int)($row['soldi'] ?? $balances['soldi']),
            'shards' => (int)($row['godoshards_balance'] ?? $balances['shards']),
        ];
        $user = array_merge($user, $row);
    } catch (Throwable $e) {
        // Si rimanda il saldo calcolato.
    }

    $usage['totale'] += $qty;
    $usage['oggi'] += $qty;
    $usage['gratis_oggi'] += $freeCount;

    return [
        'banner' => $banner,
        'pulls' => $pulls,
        'balances' => $balances,
        'pity_standard' => $group === 'standard' ? $pity['contatore'] : (int)($user['pity_standard'] ?? 0),
        'pity_evento' => $group === 'evento' ? $pity['contatore'] : (int)($user['pity_evento'] ?? 0),
        'pity' => [
            'gruppo' => $group,
            'contatore' => $pity['contatore'],
            'garantito' => (bool)$pity['garantito'],
            'soft' => $profile['soft'],
            'hard' => $profile['hard'],
        ],
        'garantito' => (bool)$pity['garantito'],
        'godos_spesi' => $godosToUse,
        'shards_spese' => $shardsToUse,
        'gratuite' => $freeCount,
        'usage' => gacha_usage_public($banner, $usage),
        'destino' => isset($state['destino_bersaglio']) ? ['bersaglio' => $state['destino_bersaglio'], 'punti' => (int)$state['destino_punti'], 'max' => $banner['destino_max']] : null,
        'garanzia_multi' => $multiGuaranteeUsed,
        'achievements' => $achievements,
    ];
}

/** Limiti e pull gratuite di un banner, per il client. */
function gacha_usage_public(array $banner, array $usage): array
{
    return [
        'totale' => $usage['totale'],
        'oggi' => $usage['oggi'],
        'limite' => $banner['limite_pull_utente'],
        'limite_giorno' => $banner['limite_pull_giorno'],
        'gratis_max' => $banner['pull_gratis_giorno'],
        'gratis_rimaste' => max(0, $banner['pull_gratis_giorno'] - $usage['gratis_oggi']),
    ];
}

/** Missioni e statistiche, dopo il commit. */
function gacha_after_pull(mysqli $mysqli, int $userId, array $banner, array $pulls, int $godos, int $shards, bool $isMulti): void
{
    try {
        $missions = ['lootbox_open' => count($pulls)];
        $thresholds = [
            'get_rarity_rare' => 'raro',
            'get_rarity_epic' => 'epico',
            'get_rarity_special' => 'speciale',
            'get_rarity_secret' => 'segreto',
        ];
        $stats = ['gacha_pulls' => count($pulls)];
        $maxPity = 0;

        foreach ($pulls as $pull) {
            $rank = gacha_rarity_rank($pull['character']['rarita']);
            foreach ($thresholds as $event => $min) {
                if ($rank >= gacha_rarity_rank($min)) {
                    $missions[$event] = ($missions[$event] ?? 0) + 1;
                }
            }
            if ($pull['is_new']) {
                $missions['gacha_new_char'] = ($missions['gacha_new_char'] ?? 0) + 1;
                $stats['gacha_new_chars'] = ($stats['gacha_new_chars'] ?? 0) + 1;
            }
            if ($pull['esito'] === 1) {
                $stats['gacha_5050_won'] = ($stats['gacha_5050_won'] ?? 0) + 1;
            } elseif ($pull['esito'] === 0) {
                $stats['gacha_5050_lost'] = ($stats['gacha_5050_lost'] ?? 0) + 1;
            }
            $maxPity = max($maxPity, (int)$pull['pity_snapshot']);
        }
        if ($isMulti) {
            $missions['gacha_multi_pull'] = 1;
        }

        foreach ($missions as $event => $quantity) {
            trackMissionProgress($mysqli, $userId, $event, $quantity);
        }

        if ($godos > 0) {
            $stats['godos_spent'] = $godos;
            $stats['gacha_spent'] = $godos;
        }
        if ($shards > 0) {
            $stats['shards_spent'] = $shards;
        }
        if ($maxPity > 0) {
            $stats['max_pity_hit'] = $maxPity;
        }
        if (function_exists('stats_track_many')) {
            stats_track_many($mysqli, $userId, $stats);
        }
    } catch (Throwable $e) {
        error_log('[MissionTracking gacha] ' . $e->getMessage());
    }
}

/* ── Risposte nella forma storica ─────────────────────────────────────── */

function gacha_response_common(array $result): array
{
    $banner = $result['banner'];
    $godos = $result['godos_spesi'];
    $shards = $result['shards_spese'];

    return [
        'soldi_rimasti' => $result['balances']['soldi'],
        'shards_rimaste' => $result['balances']['shards'],
        'valuta_usata' => ($shards > 0 && $godos > 0) ? 'mixed' : ($shards > 0 ? 'shards' : 'points'),
        'pity_standard' => $result['pity_standard'],
        'pity_evento' => $result['pity_evento'],
        'garantito' => $result['garantito'],
        'shards_spese' => $shards,
        'punti_spesi' => $godos,
        'tipo_banner' => $banner['tipo'] === 'standard' ? 'standard' : 'evento',
        'banner_id' => $banner['key'],
        'pity' => $result['pity'],
        'uso' => $result['usage'],
        'gratuite' => $result['gratuite'],
        'destino' => $result['destino'],
        'garanzia_multi' => $result['garanzia_multi'],
        'achievements' => $result['achievements'],
    ];
}

function gacha_pull_public(array $pull, bool $full = false): array
{
    $character = gacha_character_public($pull['character']);
    if (!$full) {
        unset($character['caratteristiche']);
    }
    return [
        'personaggio' => $character,
        'is_new' => $pull['is_new'],
        'vinto_50_50' => $pull['esito'],
        'featured' => $pull['featured'],
        'gratuita' => $pull['gratuita'],
        'copie' => $pull['copie'],
        'garanzia_multi' => $pull['garanzia_multi'],
        'pity_snapshot' => $pull['pity_snapshot'],
    ];
}

/** Risposta di api_gacha_pull: la stessa di sempre, con qualche campo in piu'. */
function gacha_response_single(array $result): array
{
    $pull = $result['pulls'][0];
    return array_merge(
        ['status' => 'success'],
        gacha_pull_public($pull, true),
        ['costo_scalato' => $result['godos_spesi']],
        gacha_response_common($result)
    );
}

/** Risposta di api_gacha_multi_pull. */
function gacha_response_multi(array $result): array
{
    return array_merge(
        ['status' => 'success', 'pulls' => array_map(static fn($p) => gacha_pull_public($p), $result['pulls'])],
        ['costo_totale' => $result['godos_spesi']],
        gacha_response_common($result)
    );
}

/** Errore nella forma storica ({status, message, code}). */
function gacha_response_error(Throwable $e): array
{
    if ($e instanceof GachaPullException) {
        return [$e->http, array_merge(['status' => 'error', 'message' => $e->getMessage(), 'code' => $e->codeName], $e->extra)];
    }
    error_log('[gacha pull] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    return [500, ['status' => 'error', 'message' => 'Errore interno del server. Riprova.', 'code' => 'INTERNAL_ERROR']];
}
