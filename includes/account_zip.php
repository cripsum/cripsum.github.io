<?php
// Minimal ZIP writer.
//
// The hosting may not ship the `zip` extension, and a data export that silently
// degrades to "one big JSON blob" would drop the user's uploaded media. This
// writes a spec-compliant archive with nothing but core PHP: deflate when zlib
// is available, stored otherwise.

/** Converts a unix timestamp to the DOS date/time pair ZIP headers use. */
function account_zip_dos_time(int $timestamp): array
{
    $parts = getdate($timestamp);
    // The DOS epoch starts in 1980; anything older is clamped to it.
    if ($parts['year'] < 1980) {
        return [0, 33]; // 1980-01-01 00:00:00
    }

    $time = ($parts['hours'] << 11) | ($parts['minutes'] << 5) | ($parts['seconds'] >> 1);
    $date = (($parts['year'] - 1980) << 9) | ($parts['mon'] << 5) | $parts['mday'];

    return [$time, $date];
}

/**
 * Writes a ZIP archive to $destination.
 *
 * Each entry is ['name' => 'path/in/zip', 'data' => string] or
 * ['name' => ..., 'file' => '/absolute/path']. Entries whose file cannot be
 * read are skipped rather than corrupting the archive.
 *
 * Returns true on success.
 */
function account_zip_write(string $destination, array $entries): bool
{
    $handle = @fopen($destination, 'wb');
    if (!$handle) {
        return false;
    }

    $canDeflate = function_exists('gzdeflate');
    $central = '';
    $count = 0;
    $offset = 0;

    foreach ($entries as $entry) {
        $name = (string)($entry['name'] ?? '');
        if ($name === '') {
            continue;
        }
        // ZIP paths always use forward slashes and must stay relative.
        $name = ltrim(str_replace('\\', '/', $name), '/');
        if ($name === '' || str_contains($name, '..')) {
            continue;
        }

        if (isset($entry['file'])) {
            $raw = @file_get_contents($entry['file']);
            if ($raw === false) {
                continue;
            }
            $mtime = @filemtime($entry['file']) ?: time();
        } else {
            $raw = (string)($entry['data'] ?? '');
            $mtime = time();
        }

        $crc = crc32($raw);
        $uncompressed = strlen($raw);

        $method = 0;
        $payload = $raw;
        if ($canDeflate && $uncompressed > 0) {
            $deflated = @gzdeflate($raw, 6);
            // Incompressible data can come back larger; store it as-is then.
            if ($deflated !== false && strlen($deflated) < $uncompressed) {
                $method = 8;
                $payload = $deflated;
            }
        }
        $compressed = strlen($payload);

        [$dosTime, $dosDate] = account_zip_dos_time($mtime);
        $nameLength = strlen($name);

        // Local file header. Bit 11 of the flags marks the name as UTF-8.
        $localHeader = pack('V', 0x04034b50)
            . pack('v', 20)
            . pack('v', 0x0800)
            . pack('v', $method)
            . pack('v', $dosTime)
            . pack('v', $dosDate)
            . pack('V', $crc)
            . pack('V', $compressed)
            . pack('V', $uncompressed)
            . pack('v', $nameLength)
            . pack('v', 0);

        if (fwrite($handle, $localHeader . $name . $payload) === false) {
            fclose($handle);
            @unlink($destination);
            return false;
        }

        $central .= pack('V', 0x02014b50)
            . pack('v', 20)
            . pack('v', 20)
            . pack('v', 0x0800)
            . pack('v', $method)
            . pack('v', $dosTime)
            . pack('v', $dosDate)
            . pack('V', $crc)
            . pack('V', $compressed)
            . pack('V', $uncompressed)
            . pack('v', $nameLength)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('v', 0)
            . pack('V', 32) // FILE_ATTRIBUTE_ARCHIVE
            . pack('V', $offset)
            . $name;

        $offset += strlen($localHeader) + $nameLength + $compressed;
        $count++;
    }

    $centralSize = strlen($central);
    $eocd = pack('V', 0x06054b50)
        . pack('v', 0)
        . pack('v', 0)
        . pack('v', $count)
        . pack('v', $count)
        . pack('V', $centralSize)
        . pack('V', $offset)
        . pack('v', 0);

    $ok = fwrite($handle, $central . $eocd) !== false;
    fclose($handle);

    if (!$ok) {
        @unlink($destination);
    }

    return $ok;
}
