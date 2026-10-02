<?php
/**
 * «Ci sono post nuovi?» — senza database e senza sessione.
 *
 * La pagina aperta lo chiede ogni tanto per mostrare «N nuovi post». La
 * risposta sta in un timbro su file (includes/realtime.php) aggiornato quando
 * un post va online: gli ultimi id, chi li ha scritti e quando.
 *
 * GET type=shitpost|rimasto
 */
require_once __DIR__ . '/../../includes/realtime.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$type = in_array((string)($_GET['type'] ?? ''), ['rimasto', 'toprimasti', 'rimasti'], true) ? 'rimasto' : 'shitpost';
$posts = [];

if (rt_dir() !== '') {
    foreach ((array)(rt_read('cp-' . $type)['posts'] ?? []) as $post) {
        $posts[] = ['id' => (int)($post['id'] ?? 0), 'u' => (int)($post['u'] ?? 0), 'at' => (int)($post['at'] ?? 0)];
    }
}

echo json_encode(['ok' => true, 'now' => time(), 'posts' => $posts]);
