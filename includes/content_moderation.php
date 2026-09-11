<?php
declare(strict_types=1);

/**
 * Moderazione dei contenuti della community, condivisa fra il pannello admin
 * del sito e i bottoni delle segnalazioni su Discord.
 *
 * Qui non c'e' nessun controllo di permessi: quello spetta al chiamante
 * (sessione admin sul sito, chiave + account admin collegato dal bot).
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security_helpers.php';

/**
 * @return array<string, array<string, string>>
 */
function cripsum_community_post_types(): array
{
    return [
        'shitpost' => [
            'table' => 'shitposts',
            'label_it' => 'shitpost',
            'label_en' => 'shitpost',
        ],
        'rimasto' => [
            'table' => 'toprimasti',
            'label_it' => 'post dei Top Rimasti',
            'label_en' => 'Top Rimasti post',
        ],
    ];
}

/**
 * Elimina uno shitpost o un post dei Top Rimasti, con i suoi commenti o voti,
 * e avvisa l'autore nella posta del sito.
 *
 * @return array{ok: bool, deleted: bool, author_id: int|null, title: string|null, error: string|null}
 */
function cripsum_delete_community_post(mysqli $mysqli, string $type, int $postId): array
{
    $types = cripsum_community_post_types();
    $result = ['ok' => false, 'deleted' => false, 'author_id' => null, 'title' => null, 'error' => null];

    if (!isset($types[$type]) || $postId <= 0) {
        $result['error'] = 'Tipo di contenuto o identificativo non valido.';
        return $result;
    }

    $table = $types[$type]['table'];

    try {
        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare("SELECT id_utente, titolo FROM `$table` WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Query di lettura del contenuto non valida.');
        }
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $postData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Dipendenze da ripulire prima del contenuto.
        $dependencies = $type === 'shitpost'
            ? [['commenti_shitpost', 'id_shitpost']]
            : [['voti_toprimasti', 'id_post'], ['votes_toprimasti', 'post_id']];

        foreach ($dependencies as [$depTable, $depColumn]) {
            if (!auth_table_exists($mysqli, $depTable) || !auth_column_exists($mysqli, $depTable, $depColumn)) {
                continue;
            }

            $stmtDep = $mysqli->prepare("DELETE FROM `$depTable` WHERE `$depColumn` = ?");
            if ($stmtDep) {
                $stmtDep->bind_param('i', $postId);
                $stmtDep->execute();
                $stmtDep->close();
            }
        }

        $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('Query di eliminazione non valida.');
        }

        $stmt->bind_param('i', $postId);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Non sono riuscito a eliminare il contenuto.');
        }

        $deleted = $stmt->affected_rows;
        $stmt->close();

        $mysqli->commit();

        $result['ok'] = true;
        $result['deleted'] = $deleted > 0;

        if ($postData && $deleted > 0) {
            $result['author_id'] = (int)$postData['id_utente'];
            $result['title'] = (string)$postData['titolo'];

            $currentTime = date('d/m/Y H:i:s');
            $labelIt = $types[$type]['label_it'];
            $labelEn = $types[$type]['label_en'];

            sendSecurityInboxMessage(
                $mysqli,
                $result['author_id'],
                'Contenuto rimosso: Violazione linee guida',
                'Content removed: Guidelines violation',
                "Il tuo $labelIt intitolato \"{$result['title']}\" è stato rimosso dai moderatori in data "
                    . $currentTime . " per violazione delle linee guida della community.\n\n"
                    . 'Ti invitiamo a rispettare le regole per evitare ulteriori provvedimenti sul tuo account.',
                "Your $labelEn titled \"{$result['title']}\" has been removed by moderators on "
                    . $currentTime . " for violation of the community guidelines.\n\n"
                    . 'Please follow the rules to avoid further action on your account.',
                'system'
            );
        }

        return $result;
    } catch (Throwable $e) {
        if ($mysqli->errno) {
            @$mysqli->rollback();
        }

        $result['error'] = $e->getMessage();
        return $result;
    }
}

/**
 * Le tre tabelle di segnalazione e la colonna che le collega al contenuto.
 *
 * @return array<string, string>
 */
function cripsum_report_tables(): array
{
    return [
        'content' => 'content_reports',
        'chat' => 'chat_reports',
        'profile' => 'profile_reports',
    ];
}

/**
 * Cambia lo stato di una segnalazione (open, reviewed, dismissed).
 *
 * @return array{ok: bool, error: string|null}
 */
function cripsum_set_report_status(mysqli $mysqli, string $source, int $reportId, string $status, ?int $reviewerId = null): array
{
    $tables = cripsum_report_tables();

    if (!isset($tables[$source])) {
        return ['ok' => false, 'error' => 'Tipo segnalazione non valido.'];
    }

    if (!in_array($status, ['open', 'reviewed', 'dismissed'], true)) {
        return ['ok' => false, 'error' => 'Stato non valido.'];
    }

    if ($reportId <= 0) {
        return ['ok' => false, 'error' => 'ID segnalazione non valido.'];
    }

    $table = $tables[$source];
    if (!auth_table_exists($mysqli, $table)) {
        return ['ok' => false, 'error' => "Tabella $table mancante."];
    }

    $sets = ['status = ?'];
    $types = 's';
    $params = [$status];

    if (auth_column_exists($mysqli, $table, 'reviewed_at')) {
        $sets[] = 'reviewed_at = ' . ($status === 'open' ? 'NULL' : 'NOW()');
    }

    if (auth_column_exists($mysqli, $table, 'reviewed_by')) {
        $sets[] = 'reviewed_by = ?';
        $types .= 'i';
        $params[] = $status === 'open' ? null : $reviewerId;
    }

    $params[] = $reportId;
    $types .= 'i';

    $stmt = $mysqli->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return ['ok' => false, 'error' => 'Query aggiornamento segnalazione non valida.'];
    }

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $stmt->close();

    return ['ok' => $ok, 'error' => $ok ? null : 'Non sono riuscito ad aggiornare la segnalazione.'];
}

/**
 * Colonna che collega ogni tabella di segnalazione al contenuto segnalato.
 *
 * @return array<string, string>
 */
function cripsum_report_target_columns(): array
{
    return [
        'content' => 'post_id',
        'chat' => 'message_id',
        'profile' => 'reported_user_id',
    ];
}

/**
 * Cambia lo stato di tutte le segnalazioni aperte su un contenuto.
 *
 * Serve ai bottoni su Discord, dove si agisce sul contenuto segnalato e non
 * sulla singola riga: se in dieci hanno segnalato lo stesso post, gestirlo
 * chiude tutte e dieci le segnalazioni.
 *
 * @return array{ok: bool, affected: int, error: string|null}
 */
function cripsum_set_reports_status_for_target(
    mysqli $mysqli,
    string $source,
    int $targetId,
    string $status,
    ?int $reviewerId = null,
    string $contentType = ''
): array {
    $tables = cripsum_report_tables();
    $columns = cripsum_report_target_columns();

    if (!isset($tables[$source], $columns[$source])) {
        return ['ok' => false, 'affected' => 0, 'error' => 'Tipo segnalazione non valido.'];
    }

    if (!in_array($status, ['open', 'reviewed', 'dismissed'], true)) {
        return ['ok' => false, 'affected' => 0, 'error' => 'Stato non valido.'];
    }

    if ($targetId <= 0) {
        return ['ok' => false, 'affected' => 0, 'error' => 'Contenuto non valido.'];
    }

    $table = $tables[$source];
    if (!auth_table_exists($mysqli, $table)) {
        return ['ok' => false, 'affected' => 0, 'error' => "Tabella $table mancante."];
    }

    $sets = ['status = ?'];
    $types = 's';
    $params = [$status];

    if (auth_column_exists($mysqli, $table, 'reviewed_at')) {
        $sets[] = 'reviewed_at = ' . ($status === 'open' ? 'NULL' : 'NOW()');
    }

    if (auth_column_exists($mysqli, $table, 'reviewed_by')) {
        $sets[] = 'reviewed_by = ?';
        $types .= 'i';
        $params[] = $status === 'open' ? null : $reviewerId;
    }

    $where = '`' . $columns[$source] . '` = ?';
    $types .= 'i';
    $params[] = $targetId;

    // content_reports tiene shitpost e Top Rimasti nella stessa tabella.
    if ($source === 'content' && $contentType !== '' && auth_column_exists($mysqli, $table, 'content_type')) {
        $where .= ' AND content_type = ?';
        $types .= 's';
        $params[] = $contentType;
    }

    if (auth_column_exists($mysqli, $table, 'status')) {
        $where .= " AND status = 'open'";
    }

    $stmt = $mysqli->prepare('UPDATE `' . $table . '` SET ' . implode(', ', $sets) . ' WHERE ' . $where);
    if (!$stmt) {
        return ['ok' => false, 'affected' => 0, 'error' => 'Query aggiornamento segnalazioni non valida.'];
    }

    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $affected = $ok ? $stmt->affected_rows : 0;
    $stmt->close();

    return [
        'ok' => $ok,
        'affected' => max(0, $affected),
        'error' => $ok ? null : 'Non sono riuscito ad aggiornare le segnalazioni.',
    ];
}
