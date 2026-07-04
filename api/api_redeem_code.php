<?php
require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/redeem_codes.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Non autenticato.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

$body   = json_decode(file_get_contents('php://input'), true);
$codice = strtolower(trim($body['codice'] ?? ''));
$lang   = ($body['lang'] ?? 'it') === 'en' ? 'en' : 'it';

if ($codice === '') {
    echo json_encode(['status' => 'error', 'message' => $lang === 'en' ? 'Missing code.' : 'Codice mancante.']);
    exit;
}

$t = [
    'it' => [
        'err_invalid'      => 'Codice non valido, skill issue!',
        'err_expired'      => 'Questo codice è scaduto.',
        'err_unavailable'  => 'Questo codice non è disponibile al momento.',
        'err_already_used' => 'Codice già riscattato!',
        'err_char_missing' => 'Personaggio non trovato nel database.',
        'err_char_owned'   => 'Hai già questo personaggio!',
        'err_bad_config'   => 'Codice non configurato correttamente.',
        'err_unknown_type' => 'Tipo codice non riconosciuto.',
        'pts_suffix'       => 'punti',
    ],
    'en' => [
        'err_invalid'      => 'Invalid code, skill issue!',
        'err_expired'      => 'This code has expired.',
        'err_unavailable'  => 'This code is not available right now.',
        'err_already_used' => 'Code already redeemed!',
        'err_char_missing' => 'Character not found in the database.',
        'err_char_owned'   => 'You already have this character!',
        'err_bad_config'   => 'Code is not configured correctly.',
        'err_unknown_type' => 'Unknown code type.',
        'pts_suffix'       => 'points',
    ],
][$lang];

$codici = cripsum_redeem_codes();


if (!isset($codici[$codice])) {
    echo json_encode(['status' => 'error', 'message' => $t['err_invalid']]);
    exit;
}

$entry = $codici[$codice];
$codeStatus = cripsum_redeem_code_status($entry);
if ($codeStatus !== 'active') {
    echo json_encode([
        'status' => 'error',
        'message' => $codeStatus === 'expired' ? $t['err_expired'] : $t['err_unavailable'],
        'code' => strtoupper($codeStatus),
    ]);
    exit;
}

$stmtCheck = $mysqli->prepare(
    'SELECT id FROM codici_riscattati WHERE codice = ? AND user_id = ? LIMIT 1'
);
$stmtCheck->bind_param('si', $codice, $userId);
$stmtCheck->execute();
$stmtCheck->store_result();

if ($stmtCheck->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => $t['err_already_used']]);
    exit;
}
$stmtCheck->close();

if ($entry['tipo'] === 'personaggio') {

    $stmtP = $mysqli->prepare('SELECT * FROM personaggi WHERE nome = ? LIMIT 1');
    $stmtP->bind_param('s', $entry['nome']);
    $stmtP->execute();
    $personaggio = $stmtP->get_result()->fetch_assoc();
    $stmtP->close();

    if (!$personaggio) {
        echo json_encode(['status' => 'error', 'message' => $t['err_char_missing']]);
        exit;
    }

    $stmtChk = $mysqli->prepare(
        'SELECT 1 FROM utenti_personaggi WHERE utente_id = ? AND personaggio_id = ? LIMIT 1'
    );
    $stmtChk->bind_param('ii', $userId, $personaggio['id']);
    $stmtChk->execute();
    $stmtChk->store_result();
    $haChar = $stmtChk->num_rows > 0;
    $stmtChk->close();

    if ($haChar) {
        echo json_encode(['status' => 'error', 'message' => $t['err_char_owned']]);
        exit;
    }

    $stmtAdd = $mysqli->prepare(
        'INSERT INTO utenti_personaggi (utente_id, personaggio_id, data, quantità)
         VALUES (?, ?, NOW(), 1)
         ON DUPLICATE KEY UPDATE quantità = quantità + 1'
    );
    $stmtAdd->bind_param('ii', $userId, $personaggio['id']);
    $stmtAdd->execute();
    $stmtAdd->close();

    $stmtLog = $mysqli->prepare(
        'INSERT INTO codici_riscattati (codice, user_id) VALUES (?, ?)'
    );
    $stmtLog->bind_param('si', $codice, $userId);
    $stmtLog->execute();
    $stmtLog->close();

    echo json_encode([
        'status'      => 'success',
        'tipo'        => 'personaggio',
        'personaggio' => $personaggio,
        'is_new'      => true,
    ]);
} elseif ($entry['tipo'] === 'punti') {

    $puntiDaAggiungere = (int)($entry['punti'] ?? 0);
    if ($puntiDaAggiungere <= 0) {
        echo json_encode(['status' => 'error', 'message' => $t['err_bad_config']]);
        exit;
    }

    $stmtUpd = $mysqli->prepare(
        'UPDATE utenti SET soldi = soldi + ? WHERE id = ?'
    );
    $stmtUpd->bind_param('ii', $puntiDaAggiungere, $userId);
    $stmtUpd->execute();
    $stmtUpd->close();

    $stmtSoldi = $mysqli->prepare('SELECT soldi FROM utenti WHERE id = ? LIMIT 1');
    $stmtSoldi->bind_param('i', $userId);
    $stmtSoldi->execute();
    $stmtSoldi->bind_result($soldiRimasti);
    $stmtSoldi->fetch();
    $stmtSoldi->close();

    $stmtLog = $mysqli->prepare(
        'INSERT INTO codici_riscattati (codice, user_id) VALUES (?, ?)'
    );
    $stmtLog->bind_param('si', $codice, $userId);
    $stmtLog->execute();
    $stmtLog->close();

    $desc = $entry['descrizione'] ?? null;
    if (is_array($desc)) {
        $desc = $desc[$lang] ?? $desc['it'] ?? "+{$puntiDaAggiungere} {$t['pts_suffix']}!";
    } elseif ($desc === null) {
        $desc = "+{$puntiDaAggiungere} {$t['pts_suffix']}!";
    }

    echo json_encode([
        'status'        => 'success',
        'tipo'          => 'punti',
        'punti'         => $puntiDaAggiungere,
        'soldi_rimasti' => (int)$soldiRimasti,
        'descrizione'   => $desc,
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => $t['err_unknown_type']]);
}
