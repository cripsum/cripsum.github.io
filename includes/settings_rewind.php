<?php

/**
 * Pannello impostazioni: statistiche e Cripsum Rewind.
 *
 * Incluso da it/impostazioni.php e en/impostazioni.php, che preparano
 * $settingsLanguage e $userId. La gestione del POST sta in
 * rewind_settings_handle_post(), chiamata dalla pagina insieme alle altre.
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
        'title'        => 'Stats and Rewind',
        'desc'         => 'Cripsum Rewind turns your year on the site into a story. These settings decide what goes into it.',
        'open'         => 'Open my Rewind',
        'unavailable'  => 'This feature is not available yet.',

        'tracking'      => 'Detailed stats',
        'tracking_hint' => 'Records time spent, sections visited and what you do on the site. Turning this off stops new stats: your Rewind keeps working on what other pages already store (pulls, achievements, messages), but time and favourite sections stop updating.',

        'partner'      => 'Let friends name me in their Rewind',
        'partner_hint' => 'Your name can appear as "most chatted friend" in the Rewind of people you are actually friends with. Never on a public link, and never with message counts.',

        'purge'        => 'Delete my stats',
        'purge_hint'   => 'Erases time, sections, sessions and the generated Rewind. Achievements, pulls and messages are untouched: they live in their own tables. This cannot be undone.',
        'purge_btn'    => 'Delete my stats',
        'purge_ack'    => 'I understand this cannot be undone.',

        'save'         => 'Save',
        'saved'        => 'Settings saved.',
        'purged'       => 'Your stats have been deleted.',
    ]
    : [
        'title'        => 'Statistiche e Rewind',
        'desc'         => 'Cripsum Rewind racconta il tuo anno sul sito. Da qui decidi cosa ci finisce dentro.',
        'open'         => 'Apri il mio Rewind',
        'unavailable'  => 'Questa funzione non è ancora disponibile.',

        'tracking'      => 'Statistiche dettagliate',
        'tracking_hint' => 'Registra tempo passato, sezioni visitate e cosa fai sul sito. Disattivandolo non vengono più raccolte nuove statistiche: il Rewind continua a funzionare su quello che altre pagine già salvano (pull, achievement, messaggi), ma tempo e sezioni preferite smettono di aggiornarsi.',

        'partner'      => 'Fai comparire il mio nome nel Rewind degli amici',
        'partner_hint' => 'Il tuo nome può apparire come "amico più chattato" nel Rewind di chi è davvero tuo amico. Mai su un link pubblico, e mai col numero di messaggi.',

        'purge'        => 'Cancella le mie statistiche',
        'purge_hint'   => 'Elimina tempo, sezioni, sessioni e il Rewind generato. Achievement, pull e messaggi restano dove sono: vivono nelle loro tabelle. L\'operazione non è annullabile.',
        'purge_btn'    => 'Cancella le statistiche',
        'purge_ack'    => 'Ho capito che non si può annullare.',

        'save'         => 'Salva',
        'saved'        => 'Impostazioni salvate.',
        'purged'       => 'Le tue statistiche sono state cancellate.',
    ];
?>

<div class="col-12">
    <article class="settings-card">
        <header class="settings-card__head">
            <h2 class="settings-card__title">
                <i class="fa-solid fa-clock-rotate-left"></i>
                <?php echo htmlspecialchars($rwCopy['title'], ENT_QUOTES, 'UTF-8'); ?>
            </h2>
            <p class="settings-card__desc"><?php echo htmlspecialchars($rwCopy['desc'], ENT_QUOTES, 'UTF-8'); ?></p>
        </header>

        <div class="settings-card__body">
            <?php if (!$rwReady): ?>

                <p class="settings-note"><?php echo htmlspecialchars($rwCopy['unavailable'], ENT_QUOTES, 'UTF-8'); ?></p>

            <?php else: ?>

                <p>
                    <a class="btn btn-primary" href="/<?php echo $rwLang; ?>/rewind">
                        <i class="fa-solid fa-play me-2"></i><?php echo htmlspecialchars($rwCopy['open'], ENT_QUOTES, 'UTF-8'); ?>
                    </a>
                </p>

                <form method="post" class="settings-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="update_rewind_prefs">

                    <label class="settings-toggle">
                        <input type="checkbox" name="rewind_tracking" value="1"
                               <?php echo $rwPrefs['tracking_enabled'] ? 'checked' : ''; ?>>
                        <span>
                            <strong><?php echo htmlspecialchars($rwCopy['tracking'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small><?php echo htmlspecialchars($rwCopy['tracking_hint'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </span>
                    </label>

                    <label class="settings-toggle">
                        <input type="checkbox" name="rewind_share_partner" value="1"
                               <?php echo $rwPrefs['share_top_friend'] ? 'checked' : ''; ?>>
                        <span>
                            <strong><?php echo htmlspecialchars($rwCopy['partner'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <small><?php echo htmlspecialchars($rwCopy['partner_hint'], ENT_QUOTES, 'UTF-8'); ?></small>
                        </span>
                    </label>

                    <button type="submit" class="btn btn-primary">
                        <?php echo htmlspecialchars($rwCopy['save'], ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </form>

                <hr>

                <form method="post" class="settings-form settings-form--danger"
                      onsubmit="return this.querySelector('[name=rewind_purge_ack]').checked;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="purge_rewind_stats">

                    <h3><?php echo htmlspecialchars($rwCopy['purge'], ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="settings-note"><?php echo htmlspecialchars($rwCopy['purge_hint'], ENT_QUOTES, 'UTF-8'); ?></p>

                    <label class="settings-check">
                        <input type="checkbox" name="rewind_purge_ack" value="1" required>
                        <span><?php echo htmlspecialchars($rwCopy['purge_ack'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </label>

                    <button type="submit" class="btn btn-danger">
                        <?php echo htmlspecialchars($rwCopy['purge_btn'], ENT_QUOTES, 'UTF-8'); ?>
                    </button>
                </form>

            <?php endif; ?>
        </div>
    </article>
</div>
