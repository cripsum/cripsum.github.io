<?php
/** Area "Musica": brano, riproduzione e schermata d'ingresso. */

$musicUrl = trim((string)($profile['profile_music_url'] ?? ''));
$musicSource = $hasUploadedMusic || $musicUrl === '' ? 'file' : 'link';
$musicThemeOptions = array_map(static fn($o) => $o + ['art' => '<span class="art-player art-player--' . $o['value'] . '"><i></i><b></b></span>'], $catalog['music_themes']);
?>

<?php pe_group('grp-song', $tt('Brano', 'Song'), null, ['keywords' => 'musica canzone mp3 audio']); ?>
<?php pe_choice('music_source', $musicSource, [
    ['value' => 'file', 'label' => $tt('Carica un MP3', 'Upload an MP3'), 'icon' => 'fa-solid fa-file-audio'],
    ['value' => 'link', 'label' => $tt('Link a un file audio', 'Link to an audio file'), 'icon' => 'fa-solid fa-link'],
], []); ?>

<div class="pe-music-file" data-show-if="music_source=file">
    <div class="pe-file-card" id="peMusicCard" data-has-file="<?php echo $hasUploadedMusic ? '1' : '0'; ?>">
        <span class="pe-file-icon"><i class="fa-solid fa-music" aria-hidden="true"></i></span>
        <span class="pe-file-text">
            <strong id="peMusicName"><?php echo $hasUploadedMusic ? pe_h($tt('MP3 caricato', 'Uploaded MP3')) : pe_h($tt('Nessun file', 'No file')); ?></strong>
            <small><?php echo pe_h($tt('Solo MP3, fino a ', 'MP3 only, up to ') . profile_format_bytes($uploadLimits['music'])); ?></small>
        </span>
        <label class="pe-btn pe-btn-secondary pe-btn-sm" for="peMusicInput"><?php echo pe_h($hasUploadedMusic ? $tt('Sostituisci', 'Replace') : $tt('Scegli', 'Choose')); ?></label>
        <input type="file" name="profile_music_file" id="peMusicInput" accept="audio/mpeg,.mp3" hidden>
    </div>
    <?php if ($hasUploadedMusic): ?>
        <?php pe_toggle('remove_profile_music_upload', false, ['label' => $tt('Rimuovi l\'MP3 caricato', 'Remove the uploaded MP3'), 'input_id' => 'peRemoveMusic']); ?>
    <?php endif; ?>
</div>

<div data-show-if="music_source=link">
    <?php pe_text('profile_music_url', $musicUrl, [
        'label' => $tt('Link al file audio', 'Audio file link'),
        'type' => 'url',
        'maxlength' => 255,
        'placeholder' => 'https://…/song.mp3',
        'help' => pe_h($tt('Deve essere un link diretto al file (non una pagina di Spotify o YouTube: per quelli usa gli Embed).', 'It must be a direct link to the file (not a Spotify or YouTube page: use Embeds for those).')),
    ]); ?>
</div>

<div class="pe-row-2">
    <?php pe_text('profile_music_title', $pv('profile_music_title'), ['label' => $tt('Titolo', 'Title'), 'maxlength' => 80, 'placeholder' => $tt('Nome della canzone', 'Song name')]); ?>
    <?php pe_text('profile_music_artist', $pv('profile_music_artist'), ['label' => $tt('Artista', 'Artist'), 'maxlength' => 80]); ?>
</div>
<?php pe_group_end(); ?>

<?php pe_group('grp-playback', $tt('Riproduzione', 'Playback'), null, ['keywords' => 'player volume pulsante audio']); ?>
<?php pe_toggle('profile_show_audio_player', $pflag('profile_show_audio_player'), [
    'label' => $tt('Mostra il player nella card', 'Show the player in the card'),
    'description' => $tt('Con titolo, barra di avanzamento e volume.', 'With title, progress bar and volume.'),
]); ?>
<?php pe_choice('profile_music_theme', (string)($profile['profile_music_theme'] ?? 'default'), $musicThemeOptions, [
    'label' => $tt('Stile del player', 'Player style'),
    'variant' => 'tiles',
    'columns' => 4,
    'show_if' => 'profile_show_audio_player',
]); ?>
<?php pe_toggle('profile_show_audio_btn', $pflag('profile_show_audio_btn'), [
    'label' => $tt('Pulsante del volume', 'Volume button'),
    'description' => $tt('Un piccolo pulsante fisso nell\'angolo, quando il player è nascosto.', 'A small button pinned to a corner, when the player is hidden.'),
    'show_if' => 'profile_show_audio_player=0',
]); ?>
<?php pe_choice('profile_audio_btn_position', (string)($profile['profile_audio_btn_position'] ?? 'bottom-right'), [
    ['value' => 'top-left', 'label' => $tt('In alto a sinistra', 'Top left'), 'art' => '<span class="art-corner" data-corner="top-left"></span>'],
    ['value' => 'top-right', 'label' => $tt('In alto a destra', 'Top right'), 'art' => '<span class="art-corner" data-corner="top-right"></span>'],
    ['value' => 'bottom-left', 'label' => $tt('In basso a sinistra', 'Bottom left'), 'art' => '<span class="art-corner" data-corner="bottom-left"></span>'],
    ['value' => 'bottom-right', 'label' => $tt('In basso a destra', 'Bottom right'), 'art' => '<span class="art-corner" data-corner="bottom-right"></span>'],
], ['label' => $tt('Posizione del pulsante', 'Button position'), 'variant' => 'tiles', 'columns' => 4, 'show_if' => 'profile_show_audio_btn', 'class' => 'pe-corners']); ?>
<?php pe_slider('profile_audio_default_volume', (float)($profile['profile_audio_default_volume'] ?? 0.18), ['label' => $tt('Volume iniziale', 'Starting volume'), 'min' => 0, 'max' => 1, 'step' => 0.01, 'format' => 'ratio', 'default' => 0.18]); ?>
<?php pe_toggle('profile_bg_use_video_audio', $pflag('profile_bg_use_video_audio', 0), [
    'label' => $tt('Usa l\'audio del video di sfondo', 'Use the background video audio'),
    'description' => $tt('Al posto di un brano, se lo sfondo è un video.', 'Instead of a song, when the background is a video.'),
]); ?>
<?php pe_group_end(); ?>

<?php pe_group('grp-intro', $tt('Schermata d\'ingresso', 'Entry screen'), $tt('I browser bloccano l\'audio finché la pagina non viene cliccata: una schermata d\'ingresso fa partire la musica al primo clic.', 'Browsers block audio until the page is clicked: an entry screen starts the music on the first click.'), ['keywords' => 'click to enter ingresso intro']); ?>
<?php pe_toggle('profile_click_to_enter', $pflag('profile_click_to_enter', 0), ['label' => $tt('Mostra la schermata d\'ingresso', 'Show the entry screen')]); ?>
<?php pe_text('profile_enter_text', $pv('profile_enter_text'), [
    'label' => $tt('Testo da cliccare', 'Text to click'),
    'maxlength' => 80,
    'placeholder' => 'Click to enter',
    'show_if' => 'profile_click_to_enter',
]); ?>
<?php pe_group_end(); ?>
