<?php
/**
 * Chat globale — la parte che non tocca il database.
 *
 * I messaggi della chat globale viaggiano nel timbro (includes/realtime.php)
 * in forma «neutra», uguale per tutti. Qui diventano quello che vede un
 * utente preciso: quali sono suoi, cosa può modificare o eliminare, se è
 * stato menzionato, quali reazioni ha messo lui.
 *
 * Questo file lo include anche api/rt/poll.php, che risponde senza aprire
 * MySQL: per questo non deve richiedere config/database.php né fare query.
 */

require_once __DIR__ . '/realtime.php';
require_once __DIR__ . '/../config/chat_config.php';

if (!defined('GC_PRESENCE_TTL')) {
    define('GC_PRESENCE_TTL', 75);     // secondi di validità di una presenza in chat
    define('GC_ONLINE_FRESH', 30);     // ogni quanto si riconta chi è online sul sito
}

if (!function_exists('gc_view')) {

    /** Dalla forma neutra a quella vista da un utente. */
    function gc_view(array $message, int $viewerId, string $viewerRole, string $viewerName): array
    {
        $mine = (int)($message['user_id'] ?? 0) === $viewerId;
        $deleted = !empty($message['is_deleted']);
        $isMod = in_array($viewerRole, ['admin', 'owner'], true);
        $age = time() - (int)($message['ts'] ?? 0);

        $reactions = [];
        foreach (($message['reactions'] ?? []) as $reaction) {
            $users = isset($reaction['users']) && is_array($reaction['users']) ? $reaction['users'] : [];
            $reactions[] = [
                'emoji' => (string)$reaction['emoji'],
                'count' => (int)($reaction['count'] ?? count($users)),
                'mine' => in_array($viewerId, $users, true),
            ];
        }

        $mentions = isset($message['mentions']) && is_array($message['mentions']) ? $message['mentions'] : [];
        unset($message['mentions']);

        $message['reactions'] = $reactions;
        $message['is_mine'] = $mine;
        $message['can_edit'] = !$deleted && $mine && ($message['message_type'] ?? 'text') === 'text'
            && ($isMod || $age <= (int)CHAT_EDIT_WINDOW_SECONDS);
        $message['can_delete'] = !$deleted && ($mine || $isMod);
        $message['can_report'] = !$deleted && !$mine;
        $message['can_moderate'] = $isMod && !$mine;
        // Una risposta a un proprio messaggio vale come una menzione.
        $message['mentions_me'] = !$deleted && !$mine && (
            ($viewerName !== '' && in_array(strtolower($viewerName), $mentions, true))
            || (int)($message['reply']['user_id'] ?? 0) === $viewerId
        );

        return $message;
    }

    /** Nomi menzionati in un testo (@nome), in minuscolo e senza doppioni. */
    function gc_mentions(string $text): array
    {
        if ($text === '' || strpos($text, '@') === false) {
            return [];
        }
        preg_match_all('/(?<![A-Za-z0-9_])@([A-Za-z0-9_]{3,20})(?![A-Za-z0-9_])/', $text, $found);
        $names = array_values(array_unique(array_map('strtolower', $found[1] ?? [])));
        return array_slice($names, 0, 8);
    }

    /**
     * Stato «di contorno» della chat globale per un utente: chi scrive, chi
     * è in chat, quanti sono online, le impostazioni dello staff.
     */
    function gc_aux_view(array $stamp, int $viewerId, array $hidden): array
    {
        $now = time();
        $hiddenMap = array_flip($hidden);

        $typing = [];
        foreach (($stamp['typing'] ?? []) as $id => $row) {
            $id = (int)$id;
            if ($id === $viewerId || isset($hiddenMap[$id]) || (int)($row['until'] ?? 0) < $now) {
                continue;
            }
            $typing[] = ['id' => $id, 'username' => (string)($row['name'] ?? '')];
        }

        $present = [];
        foreach (($stamp['present'] ?? []) as $id => $row) {
            $id = (int)$id;
            if (isset($hiddenMap[$id]) || (int)($row['until'] ?? 0) < $now) {
                continue;
            }
            $present[] = [
                'id' => $id,
                'username' => (string)($row['u'] ?? ''),
                'display_name' => (string)($row['d'] ?? ($row['u'] ?? '')),
                'role' => (string)($row['r'] ?? 'utente'),
                'is_premium' => !empty($row['p']),
            ];
        }
        usort($present, static function (array $a, array $b): int {
            $rank = static fn($r) => $r === 'owner' ? 0 : ($r === 'admin' ? 1 : 2);
            return [$rank($a['role']), strtolower($a['username'])] <=> [$rank($b['role']), strtolower($b['username'])];
        });

        $settings = $stamp['settings'] ?? [];
        $pinned = $settings['pinned'] ?? null;
        if (is_array($pinned) && isset($hiddenMap[(int)($pinned['user_id'] ?? 0)])) {
            $pinned = null;
        }

        return [
            'typing' => array_slice($typing, 0, 4),
            'present' => array_slice($present, 0, 80),
            'present_count' => count($present),
            'online_count' => (int)($stamp['online']['count'] ?? 0),
            'slow' => max(0, (int)($settings['slow'] ?? (int)MESSAGE_TIMEOUT)),
            'pinned' => $pinned,
        ];
    }

    /** Vero se il conteggio degli online è vecchio e qualcuno deve rifarlo. */
    function gc_online_stale(array $stamp): bool
    {
        return (time() - (int)($stamp['online']['at'] ?? 0)) > GC_ONLINE_FRESH;
    }

    /**
     * Segna un utente come presente nella chat globale. Con `$entry` null
     * si limita a prolungare una presenza già registrata (lo fa il battito
     * senza database, che non conosce nome visualizzato e premium).
     * Restituisce falso se non c'era nulla da prolungare.
     */
    function gc_presence_touch(int $userId, ?array $entry = null): bool
    {
        $found = false;
        rt_update('g', static function (array $data) use ($userId, $entry, &$found): ?array {
            $now = time();
            $present = isset($data['present']) && is_array($data['present']) ? $data['present'] : [];
            $expired = 0;
            foreach ($present as $id => $row) {
                if ((int)($row['until'] ?? 0) < $now) {
                    unset($present[$id]);
                    $expired++;
                }
            }

            $key = (string)$userId;
            $wasThere = isset($present[$key]);
            if ($entry !== null) {
                $present[$key] = $entry;
            } elseif (!$wasThere) {
                return null;
            }
            $present[$key]['until'] = $now + GC_PRESENCE_TTL;
            $found = true;

            $data['present'] = $present;
            // La lista cambia davvero solo se qualcuno è entrato o uscito:
            // un semplice prolungamento non deve svegliare tutti i browser.
            if (!$wasThere || $expired > 0) {
                $data['aux'] = (int)($data['aux'] ?? 0) + 1;
            }
            return $data;
        });
        return $found;
    }

    function gc_presence_leave(int $userId): void
    {
        rt_update('g', static function (array $data) use ($userId): ?array {
            if (!isset($data['present'][(string)$userId])) {
                return null;
            }
            unset($data['present'][(string)$userId]);
            $data['aux'] = (int)($data['aux'] ?? 0) + 1;
            return $data;
        });
    }
}
