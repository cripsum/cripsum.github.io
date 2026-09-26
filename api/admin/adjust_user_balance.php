<?php
require_once __DIR__ . '/bootstrap.php';

/*
 * Saldo di una valuta dell'utente (Godos, Godo Shards, Frammenti): aggiungi,
 * togli o imposta. Il valore di partenza si legge con la riga bloccata dentro
 * la transazione, cosi' una pull o un acquisto arrivati mentre la scheda era
 * aperta non vengono sovrascritti da un numero vecchio.
 */

const ADMIN_BALANCE_MAX = 2000000000;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') admin_fail('Metodo non consentito.', 405);

    $input = admin_input();
    $userId = (int)($input['id'] ?? 0);
    $currency = (string)($input['currency'] ?? '');
    $mode = (string)($input['mode'] ?? 'add');
    $note = trim(preg_replace('/\s+/u', ' ', (string)($input['note'] ?? '')) ?? '');
    $amount = filter_var($input['amount'] ?? null, FILTER_VALIDATE_INT);

    if ($userId <= 0) admin_fail('ID utente non valido.');
    if (!in_array($mode, ['add', 'subtract', 'set'], true)) admin_fail('Operazione non valida.');
    if ($amount === false) admin_fail('Inserisci un numero intero.');
    if ($amount < 0 || $amount > ADMIN_BALANCE_MAX) admin_fail('Importo fuori dai limiti (0 - 2.000.000.000).');
    if ($mode !== 'set' && $amount === 0) admin_fail('Inserisci un importo maggiore di zero.');
    if (mb_strlen($note, 'UTF-8') > 200) admin_fail('Nota troppo lunga (massimo 200 caratteri).');

    $currencies = admin_user_currencies($mysqli);
    if (!isset($currencies[$currency])) admin_fail('Valuta non disponibile su questo database.');

    $target = admin_fetch_user($mysqli, $userId);
    if (!$target) admin_fail('Utente non trovato.', 404);
    $isSelf = (int)$adminUser['id'] === $userId;
    if (!admin_can_manage_user($adminUser, $target, $isSelf)) admin_fail('Non puoi modificare il saldo di questo utente.', 403);

    $column = admin_qcol($currency);

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("SELECT COALESCE($column, 0) AS saldo FROM utenti WHERE id = ? FOR UPDATE");
        if (!$stmt) throw new RuntimeException(admin_prepare_error($mysqli, 'Lettura saldo non riuscita.'));
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) throw new RuntimeException('Utente non trovato.');

        $before = (int)$row['saldo'];
        $after = match ($mode) {
            'add' => min(ADMIN_BALANCE_MAX, $before + $amount),
            'subtract' => max(0, $before - $amount),
            'set' => $amount,
        };

        $sql = "UPDATE utenti SET $column = ?" . admin_update_user_timestamp_sql($mysqli) . ' WHERE id = ? LIMIT 1';
        $stmt = $mysqli->prepare($sql);
        if (!$stmt) throw new RuntimeException(admin_prepare_error($mysqli, 'Aggiornamento saldo non valido.'));
        $stmt->bind_param('ii', $after, $userId);
        if (!$stmt->execute()) throw new RuntimeException('Aggiornamento saldo non riuscito.');
        $stmt->close();

        $mysqli->commit();
    } catch (Throwable $e) {
        $mysqli->rollback();
        throw $e;
    }

    $label = $currencies[$currency]['label'];
    admin_log($mysqli, (int)$adminUser['id'], 'adjust_balance', $userId, array_filter([
        'valuta' => $label,
        'operazione' => $mode,
        'importo' => $amount,
        'da' => $before,
        'a' => $after,
        'nota' => $note !== '' ? $note : null,
    ], static fn($v) => $v !== null));

    $diff = $after - $before;
    admin_ok([
        'message' => $diff === 0
            ? "Saldo $label invariato."
            : ($diff > 0 ? '+' : '−') . number_format(abs($diff), 0, ',', '.') . " $label",
        'currency' => $currency,
        'before' => $before,
        'balance' => $after,
    ]);
} catch (Throwable $e) {
    admin_fail('Errore modifica saldo. Dettaglio: ' . $e->getMessage(), 500);
}
