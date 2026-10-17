<?php
/**
 * Cripsumpedia — c'è la migrazione v2?
 *
 * Tutto quello che è nuovo dalla fase 1 (cronologia, letture per giorno,
 * testo pronto, citazioni, indirizzi vecchi) vive in tabelle e colonne create
 * da migrations/2026_10_17_cripsumpedia_v2.sql. Finché quel file non è stato
 * eseguito la Cripsumpedia deve comportarsi come prima: ogni funzione nuova
 * chiede qui e, se la risposta è no, non fa niente.
 */

const CP_V2_TABLES = [
    'cripsumpedia_revisions',
    'cripsumpedia_mentions',
    'cripsumpedia_slug_redirects',
    'cripsumpedia_views_daily',
    'cripsumpedia_settings',
    'cripsumpedia_blocked',
];

const CP_V2_ENTRY_COLUMNS = ['profile_user_id', 'revision_id', 'content_html', 'content_html_en', 'deleted_at'];

function cp_column_exists(mysqli $mysqli, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (isset($cache[$key])) return $cache[$key];

    $stmt = $mysqli->prepare("SELECT 1 FROM information_schema.COLUMNS
                              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    if (!$stmt) return $cache[$key] = false;
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $exists = $stmt->get_result()->num_rows > 0;
    $stmt->close();
    return $cache[$key] = $exists;
}

function cp_v2_ready(mysqli $mysqli): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    if (!cp_schema_ready($mysqli)) return $ready = false;

    foreach (CP_V2_TABLES as $table) {
        if (!cp_table_exists($mysqli, $table)) return $ready = false;
    }
    foreach (CP_V2_ENTRY_COLUMNS as $column) {
        if (!cp_column_exists($mysqli, 'cripsumpedia_entries', $column)) return $ready = false;
    }
    return $ready = true;
}

/**
 * La condizione che tiene fuori le voci eliminate dallo staff, da aggiungere
 * dopo un WHERE. Vuota prima della migrazione: allora eliminare cancellava.
 */
function cp_not_deleted_sql(mysqli $mysqli, string $alias = ''): string
{
    if (!cp_v2_ready($mysqli)) return '';
    $prefix = $alias !== '' ? $alias . '.' : '';
    return " AND {$prefix}deleted_at IS NULL";
}
