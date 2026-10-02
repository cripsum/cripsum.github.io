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
 * I motivi pronti per rifiutare o rimuovere un post. La chiave arriva dal
 * pannello; il testo e' quello che legge l'autore.
 *
 * @return array<string, array{it: string, en: string}>
 */
function cripsum_rejection_reasons(): array
{
    return [
        'duplicate' => ['it' => 'È un doppione di un post già presente.', 'en' => 'It duplicates a post that is already there.'],
        'quality' => ['it' => 'La qualità è troppo bassa (illeggibile, tagliato o senza senso).', 'en' => 'The quality is too low (unreadable, cropped or meaningless).'],
        'offtopic' => ['it' => 'Non è adatto a questa sezione.', 'en' => 'It does not fit this section.'],
        'rules' => ['it' => 'Viola le linee guida della community.', 'en' => 'It breaks the community guidelines.'],
    ];
}

/**
 * Dal motivo scelto (chiave pronta o testo libero) alle due frasi per l'autore.
 *
 * @return array{it: string, en: string}|null
 */
function cripsum_rejection_text(string $reason): ?array
{
    $reason = trim($reason);
    if ($reason === '') {
        return null;
    }

    $ready = cripsum_rejection_reasons();
    if (isset($ready[$reason])) {
        return $ready[$reason];
    }

    $reason = mb_substr(preg_replace('/\s+/u', ' ', $reason) ?? '', 0, 300, 'UTF-8');
    return ['it' => $reason, 'en' => $reason];
}

/**
 * Toglie tutto quello che sta attaccato a un post: commenti, reazioni o voti,
 * salvataggi, visualizzazioni, media aggiuntivi. Le segnalazioni restano come
 * storico, ma quelle ancora aperte si chiudono: il contenuto non c'e' piu'.
 */
function cripsum_purge_community_post(mysqli $mysqli, string $type, int $postId, ?int $reviewerId = null): void
{
    $own = $type === 'shitpost'
        ? [['commenti_shitpost', 'id_shitpost'], ['shitpost_likes', 'id_shitpost']]
        : [['voti_toprimasti', 'id_post'], ['votes_toprimasti', 'post_id']];

    foreach ($own as [$table, $column]) {
        if (!auth_table_exists($mysqli, $table) || !auth_column_exists($mysqli, $table, $column)) {
            continue;
        }
        $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE `$column` = ?");
        if ($stmt) {
            $stmt->bind_param('i', $postId);
            $stmt->execute();
            $stmt->close();
        }
    }

    foreach (['content_comments', 'content_saves', 'content_views', 'content_media', 'content_azioni'] as $table) {
        if (!auth_table_exists($mysqli, $table)) {
            continue;
        }
        $stmt = $mysqli->prepare("DELETE FROM `$table` WHERE content_type = ? AND post_id = ?");
        if ($stmt) {
            $stmt->bind_param('si', $type, $postId);
            $stmt->execute();
            $stmt->close();
        }
    }

    cripsum_set_reports_status_for_target($mysqli, 'content', $postId, 'reviewed', $reviewerId, $type);
}

/**
 * Elimina uno shitpost o un post dei Top Rimasti con tutto quello che gli
 * sta attaccato, e avvisa l'autore nella posta del sito.
 *
 * Opzioni:
 *   - reason:      motivo (chiave di cripsum_rejection_reasons() o testo libero)
 *   - notify:      false quando a eliminare e' l'autore stesso
 *   - reviewer_id: chi ha deciso, per chiudere le segnalazioni aperte
 *
 * @return array{ok: bool, deleted: bool, author_id: int|null, title: string|null, error: string|null}
 */
function cripsum_delete_community_post(mysqli $mysqli, string $type, int $postId, array $options = []): array
{
    $types = cripsum_community_post_types();
    $result = ['ok' => false, 'deleted' => false, 'author_id' => null, 'title' => null, 'error' => null];

    if (!isset($types[$type]) || $postId <= 0) {
        $result['error'] = 'Tipo di contenuto o identificativo non valido.';
        return $result;
    }

    $table = $types[$type]['table'];
    $notify = ($options['notify'] ?? true) !== false;
    $reviewerId = isset($options['reviewer_id']) ? (int)$options['reviewer_id'] : null;

    try {
        $mysqli->begin_transaction();

        $stmt = $mysqli->prepare("SELECT id_utente, titolo, approvato FROM `$table` WHERE id = ?");
        if (!$stmt) {
            throw new RuntimeException('Query di lettura del contenuto non valida.');
        }
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $postData = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        cripsum_purge_community_post($mysqli, $type, $postId, $reviewerId);

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

        // La copia su file dell'immagine non deve sopravvivere al post, e la
        // pagina non deve annunciarlo fra i «nuovi».
        require_once __DIR__ . '/content_media_cache.php';
        content_media_cache_forget($type, $postId);
        require_once __DIR__ . '/community/community.php';
        cm_pulse_drop($type, $postId);

        $result['ok'] = true;
        $result['deleted'] = $deleted > 0;

        if ($postData && $deleted > 0) {
            $result['author_id'] = (int)$postData['id_utente'];
            $result['title'] = (string)$postData['titolo'];

            if ($notify) {
                $when = date('d/m/Y H:i');
                $labelIt = $types[$type]['label_it'];
                $labelEn = $types[$type]['label_en'];
                $reason = cripsum_rejection_text((string)($options['reason'] ?? ''));
                $wasOnline = (int)$postData['approvato'] === 1;

                if ($reason === null) {
                    $titleIt = 'Contenuto rimosso: Violazione linee guida';
                    $titleEn = 'Content removed: Guidelines violation';
                    $bodyIt = "Il tuo $labelIt intitolato \"{$result['title']}\" è stato rimosso dai moderatori in data "
                        . $when . " per violazione delle linee guida della community.\n\n"
                        . 'Ti invitiamo a rispettare le regole per evitare ulteriori provvedimenti sul tuo account.';
                    $bodyEn = "Your $labelEn titled \"{$result['title']}\" has been removed by moderators on "
                        . $when . " for violation of the community guidelines.\n\n"
                        . 'Please follow the rules to avoid further action on your account.';
                } else {
                    $titleIt = $wasOnline ? 'Il tuo post è stato rimosso' : 'Il tuo post non è stato approvato';
                    $titleEn = $wasOnline ? 'Your post was removed' : 'Your post was not approved';
                    $bodyIt = "Il tuo $labelIt intitolato \"{$result['title']}\" "
                        . ($wasOnline ? 'è stato rimosso dai moderatori' : 'non è stato approvato dai moderatori')
                        . " in data $when.\n\nMotivo: {$reason['it']}";
                    $bodyEn = "Your $labelEn titled \"{$result['title']}\" "
                        . ($wasOnline ? 'has been removed by moderators' : 'was not approved by moderators')
                        . " on $when.\n\nReason: {$reason['en']}";
                }

                sendSecurityInboxMessage($mysqli, $result['author_id'], $titleIt, $titleEn, $bodyIt, $bodyEn, 'system');
            }
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
 * Mette online un post, o lo rimette in attesa. E' l'unico punto che lo fa:
 * pagina, pannello e bottoni su Discord passano tutti di qui, cosi' annuncio,
 * avviso all'autore e copia su file si comportano allo stesso modo.
 *
 * L'annuncio su Discord parte una volta sola per post: chi viene nascosto e
 * poi riapprovato non viene annunciato di nuovo (colonna `annunciato_il`;
 * finche' manca, si annuncia a ogni approvazione come prima).
 *
 * Opzioni:
 *   - actor_id: chi ha deciso; l'autore non riceve avvisi sulle proprie azioni
 *   - notify:   false per non scrivere all'autore
 *   - announce: false per non annunciare su Discord
 *
 * @return array{ok: bool, changed: bool, announced: bool, author_id: int|null, title: string|null, error: string|null}
 */
function cripsum_set_community_post_approval(mysqli $mysqli, string $type, int $postId, bool $approved, array $options = []): array
{
    $types = cripsum_community_post_types();
    $result = ['ok' => false, 'changed' => false, 'announced' => false, 'author_id' => null, 'title' => null, 'error' => null];

    if (!isset($types[$type]) || $postId <= 0) {
        $result['error'] = 'Tipo di contenuto o identificativo non valido.';
        return $result;
    }

    $table = $types[$type]['table'];
    if (!auth_table_exists($mysqli, $table) || !auth_column_exists($mysqli, $table, 'approvato')) {
        $result['error'] = 'Questo tipo di contenuto non ha una colonna di approvazione.';
        return $result;
    }

    try {
        $stmt = $mysqli->prepare("SELECT id_utente, titolo, approvato FROM `$table` WHERE id = ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('Query di lettura del contenuto non valida.');
        }
        $stmt->bind_param('i', $postId);
        $stmt->execute();
        $post = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$post) {
            $result['error'] = 'Contenuto non trovato.';
            return $result;
        }

        $result['author_id'] = (int)$post['id_utente'];
        $result['title'] = (string)$post['titolo'];
        $value = $approved ? 1 : 0;

        $sets = 'approvato = ?';
        if ($approved && auth_column_exists($mysqli, $table, 'approvato_il')) {
            $sets .= ', approvato_il = COALESCE(approvato_il, NOW())';
        }

        // «AND approvato <> ?»: due moderatori che premono insieme non
        // fanno partire due avvisi.
        $stmt = $mysqli->prepare("UPDATE `$table` SET $sets WHERE id = ? AND approvato <> ? LIMIT 1");
        if (!$stmt) {
            throw new RuntimeException('Query di approvazione non valida.');
        }
        $stmt->bind_param('iii', $value, $postId, $value);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new RuntimeException('Non sono riuscito ad aggiornare lo stato.');
        }
        $result['changed'] = $stmt->affected_rows > 0;
        $stmt->close();
        $result['ok'] = true;

        // Un post nascosto non deve restare leggibile dalla copia su file.
        require_once __DIR__ . '/content_media_cache.php';
        content_media_cache_forget($type, $postId);
        require_once __DIR__ . '/community/community.php';

        if (!$result['changed']) {
            return $result;
        }

        $actorId = (int)($options['actor_id'] ?? 0);
        $notify = ($options['notify'] ?? true) !== false && $actorId !== $result['author_id'];
        $link = static fn(string $lang): string => 'https://cripsum.com' . cm_post_url($type, $postId, $lang);
        $title = cm_clip($result['title'], 80);

        if (!$approved) {
            cm_pulse_drop($type, $postId);

            if ($notify) {
                cm_notify(
                    $mysqli,
                    $result['author_id'],
                    'Il tuo post è tornato in attesa',
                    'Your post is back in review',
                    "I moderatori hanno rimesso in attesa «{$title}»: per ora non è visibile agli altri.\n\n" . $link('it'),
                    "Moderators put \"{$title}\" back in review: for now others cannot see it.\n\n" . $link('en'),
                    'system'
                );
            }
            return $result;
        }

        // Prima volta online?
        $first = true;
        if (auth_column_exists($mysqli, $table, 'annunciato_il')) {
            $stmt = $mysqli->prepare("UPDATE `$table` SET annunciato_il = NOW() WHERE id = ? AND annunciato_il IS NULL LIMIT 1");
            $stmt->bind_param('i', $postId);
            $stmt->execute();
            $first = $stmt->affected_rows > 0;
            $stmt->close();
        }

        if ($first) {
            cm_pulse_push($type, $postId, $result['author_id']);

            if (($options['announce'] ?? true) !== false) {
                try {
                    require_once __DIR__ . '/discord_notify.php';
                    $result['announced'] = (bool)notifyDiscordNewPost($mysqli, $postId, $type);
                } catch (Throwable $e) {
                    error_log('[Discord announce] ' . $e->getMessage());
                }

                // Bot spento o irraggiungibile: alla prossima approvazione si riprova.
                if (!$result['announced'] && auth_column_exists($mysqli, $table, 'annunciato_il')) {
                    $stmt = $mysqli->prepare("UPDATE `$table` SET annunciato_il = NULL WHERE id = ? LIMIT 1");
                    $stmt->bind_param('i', $postId);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        if ($notify) {
            cm_notify(
                $mysqli,
                $result['author_id'],
                'Il tuo post è online',
                'Your post is live',
                "«{$title}» è stato approvato ed è visibile a tutti.\n\n" . $link('it'),
                "\"{$title}\" was approved and everyone can see it.\n\n" . $link('en'),
                'system'
            );
        }

        return $result;
    } catch (Throwable $e) {
        $result['ok'] = false;
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
