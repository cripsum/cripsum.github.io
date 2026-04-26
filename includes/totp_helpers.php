<?php
if (!defined('CRIPSUM_TOTP_HELPERS')) {
    define('CRIPSUM_TOTP_HELPERS', true);
}

function totp_base32_chars(): string
{
    return 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
}

function totp_base32_encode(string $binary): string
{
    $alphabet = totp_base32_chars();
    $bits = '';

    for ($i = 0; $i < strlen($binary); $i++) {
        $bits .= str_pad(decbin(ord($binary[$i])), 8, '0', STR_PAD_LEFT);
    }

    $encoded = '';
    for ($i = 0; $i < strlen($bits); $i += 5) {
        $chunk = substr($bits, $i, 5);
        if (strlen($chunk) < 5) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        }
        $encoded .= $alphabet[bindec($chunk)];
    }

    return $encoded;
}

function totp_base32_decode(string $base32): string
{
    $alphabet = totp_base32_chars();
    $base32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $base32));
    $bits = '';

    for ($i = 0; $i < strlen($base32); $i++) {
        $value = strpos($alphabet, $base32[$i]);
        if ($value === false) {
            continue;
        }
        $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
    }

    $binary = '';
    for ($i = 0; $i + 8 <= strlen($bits); $i += 8) {
        $binary .= chr(bindec(substr($bits, $i, 8)));
    }

    return $binary;
}

function totp_generate_secret(int $bytes = 20): string
{
    return totp_base32_encode(random_bytes($bytes));
}

function totp_code(string $secret, ?int $timeSlice = null, int $digits = 6, int $period = 30): string
{
    if ($timeSlice === null) {
        $timeSlice = (int)floor(time() / $period);
    }

    $secretBinary = totp_base32_decode($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $secretBinary, true);
    $offset = ord(substr($hash, -1)) & 0x0F;

    $value =
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF);

    $modulo = 10 ** $digits;
    return str_pad((string)($value % $modulo), $digits, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code, int $window = 1, int $digits = 6, int $period = 30): bool
{
    $code = preg_replace('/\s+/', '', $code);

    if (!preg_match('/^\d{' . $digits . '}$/', $code)) {
        return false;
    }

    $timeSlice = (int)floor(time() / $period);

    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secret, $timeSlice + $i, $digits, $period), $code)) {
            return true;
        }
    }

    return false;
}

function totp_otpauth_uri(string $issuer, string $accountName, string $secret): string
{
    $issuer = rawurlencode($issuer);
    $label = rawurlencode('Cripsum™:' . $accountName);

    return "otpauth://totp/{$label}?secret={$secret}&issuer={$issuer}&algorithm=SHA1&digits=6&period=30";
}

function totp_qr_url(string $otpauthUri, int $size = 220): string
{
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . rawurlencode($otpauthUri);
}
