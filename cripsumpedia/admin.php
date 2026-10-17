<?php

declare(strict_types=1);

/**
 * Il vecchio pannello della Cripsumpedia. Dalla fase 4 le voci, la coda delle
 * proposte e le impostazioni stanno nel Pannello admin, sezione «Cripsumpedia».
 */

require_once __DIR__ . '/_bootstrap.php';

header('Location: ' . cp_url('admin', [], cp_detect_lang()), true, 301);
exit;
