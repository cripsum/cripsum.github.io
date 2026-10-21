<?php
/**
 * Pannello admin: Duelli (fase 7 dei Duelli 2.0, tavola 7).
 *
 * I dati stanno in includes/duel/admin.php; qui ci sono i permessi (solo
 * staff, CSRF da bootstrap.php), le risposte e il registro admin: ogni
 * modifica a kit, valori, interruttori, stagioni e partite ci finisce.
 *
 * GET  action=kits | kit (id) | values | balance (days) | seasons | matches (filter, mode, page)
 * POST action=kit_preview | kit_save | kit_reset | kit_restore | kit_traits | kit_sim
 *      | values_save | values_sim | balance_sim_start | balance_sim_chunk
 *      | switch | season_save | season_rules | season_close | match_cancel
 */
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../../includes/duel/admin.php';

$input = $_SERVER['REQUEST_METHOD'] === 'GET' ? $_GET : admin_input();
$action = (string)($input['action'] ?? ($_GET['action'] ?? ''));
$adminId = (int)$adminUser['id'];
$m = $mysqli;

if (!duel_db_ready($m)) {
    admin_fail('Le tabelle dei duelli nuovi non ci sono: esegui migrations/2026_10_19_duelli2.sql.', 503);
}

/** Il kit mandato dal pannello come array (arriva come oggetto JSON). */
$kitFrom = static function (array $in): array {
    $kit = $in['kit'] ?? null;
    if (!is_array($kit)) {
        admin_fail('Kit mancante.', 422);
    }
    return $kit;
};

/** Numeri piccoli per il registro: la prova, non tutto il kit. */
$simOut = static function (array $r): array {
    $played = $r['games'] + $r['draws'];
    return [
        'games' => $r['games'], 'draws' => $r['draws'], 'wins' => $r['wins'], 'turns' => $r['turns'], 'played' => $played, 'limit' => $r['limit'],
        'skill_uses' => $r['skill_uses'], 'skill_dmg' => $r['skill_dmg'], 'roles' => $r['roles'],
    ];
};

try {
    switch ($action) {
        // ── Kit personaggi ───────────────────────────────────────────────
        case 'kits':
            admin_ok(['characters' => duel_admin_kit_list($m)]);
            break;

        case 'kit':
            admin_ok(duel_admin_kit($m, (int)($input['id'] ?? 0)));
            break;

        case 'kit_preview':
            admin_ok(duel_admin_kit_preview($m, (int)($input['id'] ?? 0), $kitFrom($input)));
            break;

        case 'kit_sim':
            // Un blocco della prova da 2.000 partite: il browser ne chiede piu' d'uno e somma.
            @set_time_limit(120);
            $id = (int)($input['id'] ?? 0);
            $kit = isset($input['kit']) ? duel_admin_kit_check($kitFrom($input)) : null;
            admin_ok($simOut(duel_admin_sim($m, ['focus' => $id, 'kit' => $kit, 'n' => (int)($input['n'] ?? 250),
                'seed' => (int)($input['seed'] ?? 1), 'level' => 'difficile'])));
            break;

        case 'kit_save':
            $id = (int)($input['id'] ?? 0);
            $note = (string)($input['note'] ?? '');
            $version = duel_admin_kit_save($m, $adminId, $id, $kitFrom($input), $note);
            admin_log($m, $adminId, 'duel_kit_save', null, ['personaggio' => duel_admin_name((string)duel_admin_char($m, $id)['nome']), 'versione' => $version] + ($note !== '' ? ['nota' => $note] : []));
            admin_ok(['version' => $version, 'message' => 'Kit salvato: vale dalla prossima partita.'] + duel_admin_kit($m, $id));
            break;

        case 'kit_reset':
            $id = (int)($input['id'] ?? 0);
            if (!duel_admin_kit_reset($m, $adminId, $id)) {
                admin_fail('Questo personaggio usa già il kit del codice.', 409);
            }
            admin_log($m, $adminId, 'duel_kit_reset', null, ['personaggio' => duel_admin_name((string)duel_admin_char($m, $id)['nome'])]);
            admin_ok(['message' => 'Tornato al kit del codice.'] + duel_admin_kit($m, $id));
            break;

        case 'kit_restore':
            $id = (int)($input['id'] ?? 0);
            $version = duel_admin_kit_restore($m, $adminId, $id, (int)($input['version_id'] ?? 0));
            admin_log($m, $adminId, 'duel_kit_save', null, ['personaggio' => duel_admin_name((string)duel_admin_char($m, $id)['nome']), 'versione' => $version, 'nota' => 'versione vecchia rimessa in uso']);
            admin_ok(['version' => $version, 'message' => 'Versione rimessa in uso.'] + duel_admin_kit($m, $id));
            break;

        case 'kit_traits':
            $id = (int)($input['id'] ?? 0);
            [$added, $dropped, $name] = duel_admin_traits_set($m, $id, array_values((array)($input['traits'] ?? [])));
            if ($added || $dropped) {
                admin_log($m, $adminId, 'duel_traits', null, ['personaggio' => duel_admin_name($name), 'aggiunti' => implode(', ', $added), 'tolti' => implode(', ', $dropped)]);
            }
            admin_ok(['message' => 'Legami salvati.'] + duel_admin_kit($m, $id));
            break;

        // ── Stati e regole ───────────────────────────────────────────────
        case 'values':
            admin_ok(duel_admin_values($m));
            break;

        case 'values_sim':
            @set_time_limit(120);
            $values = isset($input['values']) ? duel_admin_values_check((array)$input['values']) : null;
            admin_ok($simOut(duel_admin_sim($m, ['focus' => 0, 'values' => $values, 'n' => (int)($input['n'] ?? 250),
                'seed' => (int)($input['seed'] ?? 1), 'level' => 'difficile'])));
            break;

        case 'values_save':
            $changes = duel_admin_values_save($m, $adminId, duel_admin_values_check((array)($input['values'] ?? [])));
            if ($changes) {
                admin_log($m, $adminId, 'duel_values', null, $changes);
            }
            admin_ok(['changed' => count($changes), 'message' => $changes ? 'Valori salvati: valgono dalla prossima partita.' : 'Niente da salvare.'] + duel_admin_values($m));
            break;

        // ── Bilanciamento ────────────────────────────────────────────────
        case 'balance':
            admin_ok(duel_admin_balance($m, max(1, min(365, (int)($input['days'] ?? 30)))));
            break;

        case 'balance_sim_start':
            duel_admin_balance_sim_start($m);
            admin_log($m, $adminId, 'duel_balance_sim', null, []);
            admin_ok([]);
            break;

        case 'balance_sim_chunk':
            @set_time_limit(120);
            admin_ok(duel_admin_balance_sim_chunk($m, (int)($input['seed'] ?? 1), max(2, min(300, (int)($input['n'] ?? 200)))));
            break;

        // ── Stagioni ─────────────────────────────────────────────────────
        case 'seasons':
            admin_ok(duel_admin_seasons($m));
            break;

        case 'season_save':
            $changes = duel_admin_season_save($m, (int)($input['id'] ?? 0), (string)($input['name'] ?? ''), isset($input['ends_at']) ? (string)$input['ends_at'] : null);
            if ($changes) {
                admin_log($m, $adminId, 'duel_season', null, $changes);
            }
            admin_ok(['message' => $changes ? 'Stagione salvata.' : 'Niente da salvare.'] + duel_admin_seasons($m));
            break;

        case 'season_rules':
            $changes = duel_admin_season_rules($m, (array)($input['rewards'] ?? []),
                isset($input['daily_win']) ? (int)$input['daily_win'] : null, isset($input['days']) ? (int)$input['days'] : null);
            if ($changes) {
                admin_log($m, $adminId, 'duel_season_rules', null, $changes);
            }
            admin_ok(['message' => $changes ? 'Premi salvati.' : 'Niente da salvare.'] + duel_admin_seasons($m));
            break;

        case 'season_close':
            $id = (int)($input['id'] ?? 0);
            $season = duel_row($m, "SELECT number FROM game_seasons WHERE id = ? AND status = 'active'", 'i', [$id]);
            if (!$season) {
                admin_fail('Nessuna stagione in corso con questo numero.', 404);
            }
            if (!duel_season_close($m, $id)) {
                admin_fail('La stagione si sta già chiudendo: riprova tra qualche secondo.', 409);
            }
            admin_log($m, $adminId, 'duel_season_close', null, ['stagione' => (int)$season['number']]);
            // Se le stagioni sono accese parte subito la prossima.
            duel_season_tick($m);
            admin_ok(['message' => 'Stagione ' . (int)$season['number'] . ' chiusa: premi pagati e punti verso 1.000.'] + duel_admin_seasons($m));
            break;

        // ── Partite e interruttori ───────────────────────────────────────
        case 'matches':
            admin_ok(duel_admin_matches($m, (string)($input['filter'] ?? 'live'), (string)($input['mode'] ?? ''), (int)($input['page'] ?? 1)));
            break;

        case 'match_cancel':
            $id = (int)($input['id'] ?? 0);
            $row = duel_admin_match_cancel($m, $id);
            admin_log($m, $adminId, 'duel_match_cancel', (int)$row['player1_id'], ['partita' => $id, 'modalita' => (string)$row['mode']]);
            admin_ok(['message' => "Partita #$id annullata."]);
            break;

        case 'switch':
            $key = (string)($input['key'] ?? '');
            $out = duel_admin_switch($m, $key, !empty($input['on']));
            admin_log($m, $adminId, 'duel_switch', null, ['interruttore' => $key, 'acceso' => $out['on'] ? 'sì' : 'no']);
            $labels = ['public' => ['Duelli aperti a tutti.', 'Duelli di nuovo solo per lo staff.'], 'discord' => ['Avvisi su Discord accesi.', 'Avvisi su Discord spenti.'],
                'seasons' => ['Stagioni accese.', 'Stagioni spente: quella in corso arriva alla fine, poi non ne parte un\'altra.']];
            admin_ok($out + ['message' => $labels[$key][$out['on'] ? 0 : 1]]);
            break;

        default:
            admin_fail('Azione non valida.', 400);
    }
} catch (DuelKitInvalid $e) {
    // Un errore per riga: il pannello le mostra in elenco.
    admin_fail(implode("\n", $e->errors) ?: 'Kit non valido.', 422, ['errors' => $e->errors]);
} catch (DuelUserError $e) {
    admin_fail($e->getMessage(), $e->status);
} catch (Throwable $e) {
    error_log('admin duels: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    admin_fail('Errore del server: ' . $e->getMessage(), 500);
}
