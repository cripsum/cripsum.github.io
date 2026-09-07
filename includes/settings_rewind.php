<?php

/**
 * Pannello impostazioni: statistiche e Cripsum Rewind.
 *
 * Incluso da it/impostazioni.php e en/impostazioni.php, che preparano
 * $settingsLanguage e $userId. La gestione del POST sta in
 * rewind_settings_handle_post(), chiamata dalla pagina insieme alle altre.
 *
 * Il markup riusa le classi di assets/auth/auth.css già in uso dagli altri
 * pannelli (`settings-panel`, `auth-check`, `auth-btn`, `account-data-box`):
 * questa pagina non ha un foglio di stile proprio, quindi qualsiasi classe
 * inventata qui resterebbe semplicemente senza stile.
 *
 * Le tre voci sono separate di proposito: disattivare il tracciamento è una
 * cosa, non voler comparire nel Rewind di altri è un'altra, e nessuna delle
 * due deve costringere a rinunciare all'altra.
 */

require_once __DIR__ . '/rewind_helpers.php';

$rwLang = ($settingsLanguage ?? 'it') === 'en' ? 'en' : 'it';
$rwIsEn = $rwLang === 'en';

$rwReady = isset($mysqli) && $mysqli instanceof mysqli && rewind_available($mysqli);
$rwPrefs = $rwReady ? rewind_get_prefs($mysqli, (int)$userId) : rewind_default_prefs();

$rwCopy = $rwIsEn
    ? [
        'title'         => 'Stats and Rewind',
        'desc'          => 'Cripsum Rewind turns your time on the site into a story. These settings decide what goes into it.',
        'open'          => 'Open my Rewind',
        'soon'          => 'Cripsum Rewind is still being built. Your stats are already being collected, so there will be something to show when it opens.',
        'unavailable'   => 'This feature is not available yet.',

        'prefs_title'   => 'What gets collected',
        'tracking'      => 'Detailed stats',
        'tracking_hint' => 'Time spent, sections visited and what you do around the site. Turning this off stops new stats: your Rewind keeps working on what other pages already store — pulls, achievements, messages — but time and favourite sections stop updating.',
        'partner'       => 'Let friends name me in their Rewind',
        'partner_hint'  => 'Your name can show up as "most chatted friend" for people you are actually friends with. Never on a public link, and never with message counts.',
        'save'          => 'Save',

        'purge_title'   => 'Delete my stats',
        'purge_hint'    => 'Erases time, sections, sessions and the generated Rewind.',
        'purge_keeps'   => 'Achievements, pulls and messages are untouched: they live in their own tables.',
        'purge_warn'    => 'This cannot be undone.',
        'purge_ack'     => 'I understand this cannot be undone.',
        'purge_btn'     => 'Delete my stats',
    ]
    : [
        'title'         => 'Statistiche e Rewind',
        'desc'          => 'Cripsum Rewind racconta il tuo tempo sul sito. Da qui decidi cosa ci finisce dentro.',
        'open'          => 'Apri il mio Rewind',
        'soon'          => 'Cripsum Rewind è ancora in lavorazione. Le tue statistiche vengono già raccolte, così all\'apertura ci sarà qualcosa da guardare.',
        'unavailable'   => 'Questa funzione non è ancora disponibile.',

        'prefs_title'   => 'Cosa viene raccolto',
        'tracking'      => 'Statistiche dettagliate',
        'tracking_hint' => 'Tempo passato, sezioni visitate e cosa fai in giro per il sito. Disattivandolo non vengono più raccolte nuove statistiche: il Rewind continua a funzionare su quello che altre pagine già salvano — pull, achievement, messaggi — ma tempo e sezioni preferite smettono di aggiornarsi.',
        'partner'       => 'Fai comparire il mio nome nel Rewind degli amici',
        'partner_hint'  => 'Il tuo nome può apparire come "amico più chattato" nel Rewind di chi è davvero tuo amico. Mai su un link pubblico, e mai col numero di messaggi.',
        'save'          => 'Salva',

        'purge_title'   => 'Cancella le mie statistiche',
        'purge_hint'    => 'Elimina tempo, sezioni, sessioni e il Rewind generato.',
        'purge_keeps'   => 'Achievement, pull e messaggi restano dove sono: vivono nelle loro tabelle.',
        'purge_warn'    => 'L\'operazione non è annullabile.',
        'purge_ack'     => 'Ho capito che non si può annullare.',
        'purge_btn'     => 'Cancella le statistiche',
    ];

/** Scorciatoia locale: auth_h() è definita in security_helpers.php. */
$rwH = static fn($v): string => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>

<!-- Tab: Statistiche e Rewind -->
<div class="settings-tab-content" id="tab-rewind">
    <article class="settings-panel auth-reveal">
        <div class="settings-panel__head">
            <h2><?php echo $rwH($rwCopy['title']); ?></h2>
            <p><?php echo $rwH($rwCopy['desc']); ?></p>
        </div>

        <?php if (!$rwReady): ?>

            <div class="auth-alert auth-alert--error">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><?php echo $rwH($rwCopy['unavailable']); ?></span>
            </div>

        <?php else: ?>

            <?php if (rewind_user_can_view()): ?>
                <a class="auth-btn auth-btn--primary" href="/<?php echo $rwLang; ?>/rewind"
                   style="width:auto;padding:10px 22px;">
                    <i class="fa-solid fa-play"></i>
                    <span><?php echo $rwH($rwCopy['open']); ?></span>
                </a>
            <?php else: ?>
                <div class="auth-alert">
                    <i class="fa-solid fa-hourglass-half"></i>
                    <span><?php echo $rwH($rwCopy['soon']); ?></span>
                </div>
            <?php endif; ?>

            <form method="post" class="auth-form" style="margin-top:1.25rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="update_rewind_prefs">

                <div class="account-data-box">
                    <h3><i class="fa-solid fa-chart-simple"></i> <?php echo $rwH($rwCopy['prefs_title']); ?></h3>

                    <label class="auth-check">
                        <input type="checkbox" name="rewind_tracking" value="1"
                               <?php echo $rwPrefs['tracking_enabled'] ? 'checked' : ''; ?>>
                        <span><?php echo $rwH($rwCopy['tracking']); ?></span>
                    </label>
                    <p class="account-data-note account-data-note--muted">
                        <?php echo $rwH($rwCopy['tracking_hint']); ?>
                    </p>

                    <label class="auth-check" style="margin-top:.4rem;">
                        <input type="checkbox" name="rewind_share_partner" value="1"
                               <?php echo $rwPrefs['share_top_friend'] ? 'checked' : ''; ?>>
                        <span><?php echo $rwH($rwCopy['partner']); ?></span>
                    </label>
                    <p class="account-data-note account-data-note--muted">
                        <?php echo $rwH($rwCopy['partner_hint']); ?>
                    </p>
                </div>

                <button class="auth-btn auth-btn--primary" type="submit"
                        style="width:auto;padding:10px 22px;">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span><?php echo $rwH($rwCopy['save']); ?></span>
                </button>
            </form>

            <form method="post" class="auth-form" style="margin-top:1.5rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="purge_rewind_stats">

                <div class="account-data-box account-data-box--danger">
                    <h3><i class="fa-solid fa-eraser"></i> <?php echo $rwH($rwCopy['purge_title']); ?></h3>

                    <ul class="account-data-list">
                        <li><?php echo $rwH($rwCopy['purge_hint']); ?></li>
                        <li><?php echo $rwH($rwCopy['purge_keeps']); ?></li>
                        <li><?php echo $rwH($rwCopy['purge_warn']); ?></li>
                    </ul>

                    <label class="auth-check" style="margin-top:.9rem;">
                        <input type="checkbox" name="rewind_purge_ack" value="1" required>
                        <span><?php echo $rwH($rwCopy['purge_ack']); ?></span>
                    </label>

                    <button class="auth-btn auth-btn--danger" type="submit"
                            style="width:auto;padding:10px 22px;margin-top:1rem;">
                        <i class="fa-solid fa-trash"></i>
                        <span><?php echo $rwH($rwCopy['purge_btn']); ?></span>
                    </button>
                </div>
            </form>

        <?php endif; ?>
    </article>
</div>
