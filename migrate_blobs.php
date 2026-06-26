<?php
/**
 * migrate_blobs.php
 * One-off migration script to move existing avatars and background banners
 * from raw database binary BLOB columns to server files on disk.
 */

require_once __DIR__ . '/config/database.php';

// 1. Add the profile_bg_use_video_audio column if not exists
$mysqli->query("
    ALTER TABLE `utenti` 
    ADD COLUMN IF NOT EXISTS `profile_bg_use_video_audio` TINYINT(1) NOT NULL DEFAULT 0
");

// 2. Fetch all users having avatar or banner
$res = $mysqli->query("SELECT id, profile_pic, profile_pic_type, profile_banner, profile_banner_type FROM utenti");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $userId = (int)$row['id'];
        $pic = $row['profile_pic'];
        $picType = $row['profile_pic_type'];
        $banner = $row['profile_banner'];
        $bannerType = $row['profile_banner_type'];

        $uploadDir = __DIR__ . '/uploads/profile_media/user_' . $userId;

        // Process profile_pic (avatar)
        if ($pic && !empty($pic) && !str_starts_with($pic, '/uploads/')) {
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = 'png';
            if ($picType === 'image/jpeg' || $picType === 'image/jpg') $ext = 'jpg';
            elseif ($picType === 'image/webp') $ext = 'webp';
            elseif ($picType === 'image/gif') $ext = 'gif';

            $randomHash = bin2hex(random_bytes(16));
            $fileName = 'avatar_' . $randomHash . '.' . $ext;
            $filePath = $uploadDir . '/' . $fileName;

            if (file_put_contents($filePath, $pic) !== false) {
                $relativeUrl = '/uploads/profile_media/user_' . $userId . '/' . $fileName;
                $stmt = $mysqli->prepare("UPDATE utenti SET profile_pic = ? WHERE id = ?");
                $stmt->bind_param('si', $relativeUrl, $userId);
                $stmt->execute();
                $stmt->close();
                echo "User $userId: Avatar migrated to $relativeUrl\n";
            } else {
                echo "User $userId: Failed to migrate avatar to file.\n";
            }
        }

        // Process profile_banner (background)
        if ($banner && !empty($banner) && !str_starts_with($banner, '/uploads/')) {
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $ext = 'png';
            if ($bannerType === 'image/jpeg' || $bannerType === 'image/jpg') $ext = 'jpg';
            elseif ($bannerType === 'image/webp') $ext = 'webp';
            elseif ($bannerType === 'image/gif') $ext = 'gif';
            elseif ($bannerType === 'video/mp4') $ext = 'mp4';
            elseif ($bannerType === 'video/webm') $ext = 'webm';

            $randomHash = bin2hex(random_bytes(16));
            $fileName = 'banner_' . $randomHash . '.' . $ext;
            $filePath = $uploadDir . '/' . $fileName;

            if (file_put_contents($filePath, $banner) !== false) {
                $relativeUrl = '/uploads/profile_media/user_' . $userId . '/' . $fileName;
                $stmt = $mysqli->prepare("UPDATE utenti SET profile_banner = ? WHERE id = ?");
                $stmt->bind_param('si', $relativeUrl, $userId);
                $stmt->execute();
                $stmt->close();
                echo "User $userId: Banner migrated to $relativeUrl\n";
            } else {
                echo "User $userId: Failed to migrate banner to file.\n";
            }
        }
    }
}

// 3. Alter the database columns to VARCHAR(255)
$mysqli->query("ALTER TABLE utenti MODIFY COLUMN profile_pic VARCHAR(255) DEFAULT NULL");
$mysqli->query("ALTER TABLE utenti MODIFY COLUMN profile_banner VARCHAR(255) DEFAULT NULL");

echo "Migration finished: PFP/Banner columns modified to VARCHAR(255) successfully!\n";
