<?php
declare(strict_types=1);

http_response_code(410);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo json_encode([
    'ok' => false,
    'error' => 'ENDPOINT_RETIRED',
    'message' => 'Endpoint obsoleto e disabilitato. Usa l’API corrente.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
