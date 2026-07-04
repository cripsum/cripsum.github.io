<?php
require_once __DIR__ . '/config/database.php';

$ids = [13, 20];
foreach ($ids as $id) {
    echo "=== USER ID $id ===\n";
    $stmt = $mysqli->prepare("SELECT username, is_premium, profile_show_audio_player, profile_show_audio_btn, profile_audio_btn_position, profile_audio_default_volume, profile_music_url, profile_music_mime, profile_bg_use_video_audio, profile_banner_type, (profile_music_blob IS NOT NULL) as has_music_blob FROM utenti WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        print_r($res);
        $stmt->close();
    } else {
        echo "Error: " . $mysqli->error . "\n";
    }
}
$mysqli->close();
