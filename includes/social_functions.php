<?php
/**
 * Funzioni social storiche.
 *
 * Le regole vere stanno in includes/social_core.php; qui restano i nomi che
 * il resto del sito già usa (profile.php, gli endpoint dei gruppi), così chi
 * li chiama ottiene le stesse risposte degli endpoint nuovi.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/social_core.php';

/**
 * Stato della relazione tra chi guarda e un altro utente, con i permessi
 * calcolati su blocchi e impostazioni di privacy.
 */
function getRelationshipStatus($mysqli, $viewerId, $targetId)
{
    return sc_relationship($mysqli, (int)$viewerId, (int)$targetId);
}

/** Amici in comune tra due utenti. */
function getMutualFriends($mysqli, $userOneId, $userTwoId)
{
    return sc_mutual_friends($mysqli, (int)$userOneId, (int)$userTwoId, 50)['users'];
}

/** Notifica nella posta interna, categoria «social». */
function sendSocialNotification($mysqli, $recipientId, $titleIt, $titleEn, $contentIt, $contentEn)
{
    if (function_exists('sendSecurityInboxMessage')) {
        return sendSecurityInboxMessage($mysqli, $recipientId, $titleIt, $titleEn, $contentIt, $contentEn, 'social');
    }
    return false;
}
