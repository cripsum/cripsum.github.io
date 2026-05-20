<?php

/**
 * api_redeem_code.php
 * POST /api/api_redeem_code
 *
 * Body JSON: { "codice": "string", "lang": "it"|"en" }
 *
 * Risponde con JSON:
 *   { status: 'success', tipo: 'personaggio', personaggio: {...}, is_new: true }
 *   { status: 'success', tipo: 'punti', punti: 500, soldi_rimasti: 1200, descrizione: '...' }
 *   { status: 'error',   message: '...' }
 */

require_once __DIR__ . '/../config/session_init.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

// ── Auth ──────────────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Non autenticato.']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// ── Input ─────────────────────────────────────────────────────────────────────
$body   = json_decode(file_get_contents('php://input'), true);
$codice = strtolower(trim($body['codice'] ?? ''));
$lang   = ($body['lang'] ?? 'it') === 'en' ? 'en' : 'it';

if ($codice === '') {
    echo json_encode(['status' => 'error', 'message' => $lang === 'en' ? 'Missing code.' : 'Codice mancante.']);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// TRADUZIONI
// ══════════════════════════════════════════════════════════════════════════════
$t = [
    'it' => [
        'err_invalid'      => 'Codice non valido, skill issue!',
        'err_already_used' => 'Codice già riscattato!',
        'err_char_missing' => 'Personaggio non trovato nel database.',
        'err_char_owned'   => 'Hai già questo personaggio!',
        'err_bad_config'   => 'Codice non configurato correttamente.',
        'err_unknown_type' => 'Tipo codice non riconosciuto.',
        'pts_suffix'       => 'punti',
    ],
    'en' => [
        'err_invalid'      => 'Invalid code, skill issue!',
        'err_already_used' => 'Code already redeemed!',
        'err_char_missing' => 'Character not found in the database.',
        'err_char_owned'   => 'You already have this character!',
        'err_bad_config'   => 'Code is not configured correctly.',
        'err_unknown_type' => 'Unknown code type.',
        'pts_suffix'       => 'points',
    ],
][$lang];

// ══════════════════════════════════════════════════════════════════════════════
// CATALOGO CODICI
//
// Ogni voce è un array con:
//   tipo         => 'personaggio' | 'punti'
//   --- se personaggio ---
//   nome         => nome esatto nella colonna `nome` della tabella `personaggi`
//   --- se punti ---
//   punti        => (int) quanti punti aggiungere
//   descrizione  => [ 'it' => '...', 'en' => '...' ]  testo toast (opzionale)
// ══════════════════════════════════════════════════════════════════════════════
$codici = [
    // Personaggi
    'signortoki' => ['tipo' => 'personaggio', 'nome' => 'TOKI'],
    'cripsum'    => ['tipo' => 'personaggio', 'nome' => 'CRIPSUM'],
    'peak'       => ['tipo' => 'personaggio', 'nome' => 'MAOMAO'],
    'sburevole'  => ['tipo' => 'personaggio', 'nome' => 'ZIO DANILO SBUREVOLE'],

    // Punti / pull
    '67'    => [
        'tipo'        => 'punti',
        'punti'       => 67,
        'descrizione' => ['it' => '+67, aura', 'en' => '+67, aura'],
    ],
    'godo' => [
        'tipo'        => 'punti',
        'punti'       => 1000,
        'descrizione' => ['it' => '+1000, tieni, prenditi sta multi', 'en' => '+1000, here, take these 10 pulls'],
    ],
    'nauzterrone'     => [
        'tipo'        => 'punti',
        'punti'       => 6767,
        'descrizione' => ['it' => 'xd xd 67 xd nauz terrone', 'en' => 'xd xd 67 xd nauz terrone'],
    ],
    'update30' => [
        'tipo'        => 'punti',
        'punti'       => 3000,
        'descrizione' => ['it' => '+3000 punti per l\'aggiornamento!', 'en' => '+3000 points for the update!'],
    ],
    'cripsumgift' => [
        'tipo'        => 'punti',
        'punti'       => 500,
        'descrizione' => ['it' => '5 pull uwu', 'en' => '5 pulls uwu'],
    ],
    '5050loser' => [
        'tipo'        => 'punti',
        'punti'       => 5000,
        'descrizione' => ['it' => 'Ci dispiaceva per la tua sfiga, quindi beccati queste 50 pull.', 'en' => 'We felt bad for your terrible luck, so take these 50 pulls',],
    ],

];



// ── Codice valido? ────────────────────────────────────────────────────────────
if (!isset($codici[$codice])) {
    echo json_encode(['status' => 'error', 'message' => $t['err_invalid']]);
    exit;
}

$entry = $codici[$codice];

// ── Già riscattato da questo utente? ─────────────────────────────────────────
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

// ══════════════════════════════════════════════════════════════════════════════
// GESTIONE PER TIPO
// ══════════════════════════════════════════════════════════════════════════════

if ($entry['tipo'] === 'personaggio') {

    // 1. Recupera personaggio (SELECT * come in get_character_from_nome.php)
    $stmtP = $mysqli->prepare('SELECT * FROM personaggi WHERE nome = ? LIMIT 1');
    $stmtP->bind_param('s', $entry['nome']);
    $stmtP->execute();
    $personaggio = $stmtP->get_result()->fetch_assoc();
    $stmtP->close();

    if (!$personaggio) {
        echo json_encode(['status' => 'error', 'message' => $t['err_char_missing']]);
        exit;
    }

    // 2. Già in inventario? (tabella utenti_personaggi, come in add_character_to_inventory.php)
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

    // 3. Aggiungi all'inventario (stessa query di add_character_to_inventory.php)
    $stmtAdd = $mysqli->prepare(
        'INSERT INTO utenti_personaggi (utente_id, personaggio_id, data, quantità)
         VALUES (?, ?, NOW(), 1)
         ON DUPLICATE KEY UPDATE quantità = quantità + 1'
    );
    $stmtAdd->bind_param('ii', $userId, $personaggio['id']);
    $stmtAdd->execute();
    $stmtAdd->close();

    // 4. Segna come riscattato
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

    // Aggiorna i punti nella tabella utenti
    $stmtUpd = $mysqli->prepare(
        'UPDATE utenti SET soldi = soldi + ? WHERE id = ?'
    );
    $stmtUpd->bind_param('ii', $puntiDaAggiungere, $userId);
    $stmtUpd->execute();
    $stmtUpd->close();

    // Leggi i punti aggiornati
    $stmtSoldi = $mysqli->prepare('SELECT soldi FROM utenti WHERE id = ? LIMIT 1');
    $stmtSoldi->bind_param('i', $userId);
    $stmtSoldi->execute();
    $stmtSoldi->bind_result($soldiRimasti);
    $stmtSoldi->fetch();
    $stmtSoldi->close();

    // Segna come riscattato
    $stmtLog = $mysqli->prepare(
        'INSERT INTO codici_riscattati (codice, user_id) VALUES (?, ?)'
    );
    $stmtLog->bind_param('si', $codice, $userId);
    $stmtLog->execute();
    $stmtLog->close();

    // Descrizione localizzata: array it/en oppure stringa legacy
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
