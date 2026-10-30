<?php
/** Immagini dichiarate nell'evento o aggiunte alla sua cartella in img/lore. */
function lore_image_view(array $image, string $fallbackAlt): ?array
{
    $url = lore_local_url((string)($image['src'] ?? ''));
    if ($url === '') {
        return null;
    }

    $path = rawurldecode((string)parse_url($url, PHP_URL_PATH));
    if (str_contains($path, "\0") || str_contains($path, '\\')) {
        return null;
    }

    $root = realpath(dirname(__DIR__, 2));
    $file = $root !== false ? realpath($root . $path) : false;
    if (!$file || !str_starts_with($file, $root . DIRECTORY_SEPARATOR) || !is_file($file)) {
        return null;
    }
    if (!in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'avif', 'gif'], true)) {
        return null;
    }

    $size = @getimagesize($file);
    if ($size === false) {
        return null;
    }

    $relative = str_replace('\\', '/', substr($file, strlen($root)));
    return [
        'src' => implode('/', array_map('rawurlencode', explode('/', $relative))),
        'alt' => (string)($image['alt'] ?? $fallbackAlt),
        'caption' => (string)($image['caption'] ?? ''),
        'width' => $size[0],
        'height' => $size[1],
    ];
}

function lore_event_images(array $event): array
{
    $images = [];
    $fallbackAlt = (string)($event['title'] ?? '');
    foreach ($event['images'] ?? [] as $image) {
        $view = lore_image_view(is_string($image) ? ['src' => $image] : $image, $fallbackAlt);
        if ($view !== null) {
            $images[$view['src']] = $view;
        }
    }

    $id = (string)($event['id'] ?? '');
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $id)) {
        return array_values($images);
    }
    $directory = dirname(__DIR__, 2) . '/img/lore/' . $id;
    if (!is_dir($directory)) {
        return array_values($images);
    }

    $files = @scandir($directory);
    if ($files === false) {
        return array_values($images);
    }
    natsort($files);
    foreach ($files as $filename) {
        if ($filename === '' || $filename[0] === '.') {
            continue;
        }
        $view = lore_image_view(['src' => '/img/lore/' . $id . '/' . rawurlencode($filename)], $fallbackAlt);
        if ($view !== null && !isset($images[$view['src']])) {
            $images[$view['src']] = $view;
        }
    }
    return array_values($images);
}
