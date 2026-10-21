<?php
// I duelli di prima sono diventati i Duelli 2.0 (fase 8): l'arena vecchia porta a quella nuova
// (le partite del vecchio motore non ci sono piu': l'arena nuova rimanda alla lobby).
$id = (int)($_GET['match_id'] ?? $_GET['m'] ?? 0);
header('Location: /it/duelli/' . ($id > 0 ? 'arena?m=' . $id : ''), true, 301);
exit;
