<?php
/*
 * Immagini delle card delle mappe: prende da una cartella i file chiamati
 * <slug>.png|jpg|jpeg|webp (slug come nel catalogo: london, hongkong,
 * saintpetersburg...) e li salva in img/subway/<slug>.webp, ridotti a stare
 * in 480x480 senza ritagli e con la trasparenza conservata. La pagina mostra
 * da sola le immagini presenti in img/subway/.
 *
 * Uso (serve GD): php -d extension=gd city-images.php <cartella-sorgente> [<radice-del-repo>]
 */

[$script, $srcDir, $root] = $argv + [null, null, dirname(__DIR__, 2)];
if (!$srcDir || !is_dir($srcDir)) {
    fwrite(STDERR, "Uso: php -d extension=gd {$script} <cartella-sorgente> [<radice-del-repo>]\n");
    exit(1);
}
if (!function_exists('imagewebp')) {
    fwrite(STDERR, "GD con WebP non disponibile: lancia php con -d extension=gd\n");
    exit(1);
}

require $root . '/includes/subway/catalog.php';
$slugs = subway_catalog_slugs();
$outDir = $root . '/img/subway';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

foreach (glob(rtrim($srcDir, '/\\') . '/*.{png,jpg,jpeg,webp,PNG,JPG,JPEG,WEBP}', GLOB_BRACE) as $file) {
    $slug = strtolower(pathinfo($file, PATHINFO_FILENAME));
    if (!in_array($slug, $slugs, true)) {
        echo "saltato {$file}: '{$slug}' non e' una mappa del catalogo\n";
        continue;
    }
    $img = @imagecreatefromstring((string)file_get_contents($file));
    if (!$img) {
        echo "saltato {$file}: immagine non leggibile\n";
        continue;
    }
    $w = imagesx($img);
    $h = imagesy($img);
    $scale = min(1, 480 / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $out = imagecreatetruecolor($nw, $nh);
    imagealphablending($out, false);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagewebp($out, "{$outDir}/{$slug}.webp", 82);
    printf("%-16s %dx%d -> %dx%d, %d KB\n", $slug, $w, $h, $nw, $nh, round(filesize("{$outDir}/{$slug}.webp") / 1024));
}
