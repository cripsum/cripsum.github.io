<?php
/**
 * Cripsum™ — Email di conferma degli acquisti (Premium e Godo Shards).
 *
 * Il Codice del Consumo (art. 51, comma 7) chiede di confermare su un
 * "supporto durevole" un acquisto di contenuti digitali e, quando la
 * fornitura parte subito, anche la rinuncia al recesso data nel checkout.
 * Questa email e' quella conferma: cosa, quanto, quando, con quale ID
 * ordine, e la frase della spunta.
 *
 * Parte dopo che il pagamento e' gia' registrato: se l'invio fallisce si
 * scrive nel log e basta, l'acquisto resta valido. Il testo e' in italiano e
 * in inglese insieme, perche' nel webhook non si sa in che lingua stava
 * navigando chi ha pagato.
 *
 * @package Cripsum\Shop
 */

require_once __DIR__ . '/../config/email_config.php';

/**
 * Invia la conferma a chi ha pagato.
 *
 * $order:
 *   'product_it', 'product_en'  nome del prodotto ("Cripsum™ Premium", "80 Godo Shards")
 *   'detail_it', 'detail_en'    riga facoltativa (regalo, bonus primo acquisto)
 *   'amount_cents'              importo pagato, in centesimi
 *   'currency'                  "EUR"
 *   'gateway'                   "PayPal" o "Stripe"
 *   'order_id'                  ID dell'ordine o della sessione di pagamento
 */
function cripsum_send_purchase_receipt(mysqli $mysqli, int $buyerId, array $order): bool
{
    try {
        if ($buyerId <= 0) {
            return false;
        }

        $stmt = $mysqli->prepare("SELECT username, email FROM utenti WHERE id = ? LIMIT 1");
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('i', $buyerId);
        $stmt->execute();
        $buyer = $stmt->get_result()->fetch_assoc() ?: null;
        $stmt->close();

        $email = trim((string)($buyer['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $subject = 'Conferma acquisto / Purchase confirmation - ' . SITE_NAME;
        $body = cripsum_purchase_receipt_html((string)($buyer['username'] ?? ''), $order);

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . cripsum_mime_header(FROM_NAME) . ' <' . FROM_EMAIL . '>',
            'Reply-To: tos@cripsum.com',
            'Return-Path: ' . FROM_EMAIL,
        ];

        if (!mail($email, cripsum_mime_header($subject), $body, implode("\r\n", $headers))) {
            error_log('[purchase_receipt] invio non riuscito per l\'utente ' . $buyerId);
            return false;
        }

        return true;
    } catch (Throwable $e) {
        error_log('[purchase_receipt] ' . $e->getMessage());
        return false;
    }
}

/** Intestazione MIME in UTF-8 (serve per "™" e le lettere accentate). */
function cripsum_mime_header(string $text): string
{
    return '=?UTF-8?B?' . base64_encode($text) . '?=';
}

function cripsum_purchase_receipt_html(string $username, array $order): string
{
    $h = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

    $cents = (int)($order['amount_cents'] ?? 0);
    $currency = strtoupper((string)($order['currency'] ?? 'EUR'));
    $amountIt = number_format($cents / 100, 2, ',', '.') . ' ' . $currency;
    $amountEn = number_format($cents / 100, 2, '.', ',') . ' ' . $currency;
    $date = date('d/m/Y H:i');

    $productIt = $h($order['product_it'] ?? '');
    $productEn = $h($order['product_en'] ?? ($order['product_it'] ?? ''));
    $detailIt = trim((string)($order['detail_it'] ?? ''));
    $detailEn = trim((string)($order['detail_en'] ?? ''));
    $gateway = $h($order['gateway'] ?? '');
    $orderId = $h($order['order_id'] ?? '');
    $user = $h($username);
    $siteName = $h(SITE_NAME);
    $siteUrl = $h(SITE_URL);

    $detailRowIt = $detailIt !== '' ? '<tr><td style="padding:4px 0;color:#94a3b8;">Dettagli</td><td style="padding:4px 0;color:#f8fafc;">' . $h($detailIt) . '</td></tr>' : '';
    $detailRowEn = $detailEn !== '' ? '<tr><td style="padding:4px 0;color:#94a3b8;">Details</td><td style="padding:4px 0;color:#f8fafc;">' . $h($detailEn) . '</td></tr>' : '';

    return <<<HTML
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conferma acquisto - {$siteName}</title>
</head>
<body style="margin:0;padding:0;background-color:#0b0f19;font-family:'Segoe UI',Arial,sans-serif;color:#f8fafc;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#0b0f19;padding:32px 10px;">
        <tr>
            <td align="center">
                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;background-color:#121829;border-radius:16px;border:1px solid #1e294b;">
                    <tr>
                        <td style="padding:28px 28px 8px;">
                            <div style="font-size:22px;font-weight:800;color:#ffffff;">{$siteName}</div>
                            <h1 style="margin:14px 0 6px;font-size:20px;color:#ffffff;">Conferma del tuo acquisto</h1>
                            <p style="margin:0 0 16px;color:#94a3b8;font-size:14px;line-height:1.6;">Ciao {$user}, abbiamo ricevuto il tuo pagamento. Ecco il riepilogo.</p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px;">
                                <tr><td style="padding:4px 0;color:#94a3b8;width:40%;">Prodotto</td><td style="padding:4px 0;color:#f8fafc;">{$productIt}</td></tr>
                                {$detailRowIt}
                                <tr><td style="padding:4px 0;color:#94a3b8;">Importo</td><td style="padding:4px 0;color:#f8fafc;">{$amountIt}</td></tr>
                                <tr><td style="padding:4px 0;color:#94a3b8;">Data</td><td style="padding:4px 0;color:#f8fafc;">{$date}</td></tr>
                                <tr><td style="padding:4px 0;color:#94a3b8;">Pagamento</td><td style="padding:4px 0;color:#f8fafc;">{$gateway}</td></tr>
                                <tr><td style="padding:4px 0;color:#94a3b8;">ID ordine</td><td style="padding:4px 0;color:#f8fafc;word-break:break-all;">{$orderId}</td></tr>
                            </table>
                            <p style="margin:16px 0 0;color:#cbd5e1;font-size:13px;line-height:1.6;">Al momento dell'acquisto hai chiesto di ricevere subito il contenuto digitale e hai preso atto che, per questo, perdi il diritto di recesso di 14 giorni (art. 59, lett. o, Codice del Consumo). Restano validi i tuoi diritti se il contenuto non arriva o non funziona: in quel caso scrivici.</p>
                            <p style="margin:12px 0 0;color:#94a3b8;font-size:13px;line-height:1.6;">Venditore: il team di {$siteName} ({$siteUrl}) · Assistenza: tos@cripsum.com · <a href="{$siteUrl}/it/tos" style="color:#adc2ff;">Termini di servizio</a></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 28px 28px;">
                            <hr style="border:0;border-top:1px solid #1e294b;margin:16px 0;">
                            <h2 style="margin:0 0 6px;font-size:17px;color:#ffffff;">Purchase confirmation</h2>
                            <p style="margin:0 0 12px;color:#94a3b8;font-size:14px;line-height:1.6;">Hi {$user}, we received your payment. Here is the summary.</p>
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="font-size:14px;">
                                <tr><td style="padding:4px 0;color:#94a3b8;width:40%;">Product</td><td style="padding:4px 0;color:#f8fafc;">{$productEn}</td></tr>
                                {$detailRowEn}
                                <tr><td style="padding:4px 0;color:#94a3b8;">Amount</td><td style="padding:4px 0;color:#f8fafc;">{$amountEn}</td></tr>
                                <tr><td style="padding:4px 0;color:#94a3b8;">Date</td><td style="padding:4px 0;color:#f8fafc;">{$date}</td></tr>
                                <tr><td style="padding:4px 0;color:#94a3b8;">Payment</td><td style="padding:4px 0;color:#f8fafc;">{$gateway}</td></tr>
                                <tr><td style="padding:4px 0;color:#94a3b8;">Order ID</td><td style="padding:4px 0;color:#f8fafc;word-break:break-all;">{$orderId}</td></tr>
                            </table>
                            <p style="margin:16px 0 0;color:#cbd5e1;font-size:13px;line-height:1.6;">When you bought it, you asked to receive the digital content right away and acknowledged that you therefore lose your 14-day right of withdrawal. Your rights still apply if the content is not delivered or does not work: in that case, write to us.</p>
                            <p style="margin:12px 0 0;color:#94a3b8;font-size:13px;line-height:1.6;">Seller: the {$siteName} team ({$siteUrl}) · Support: tos@cripsum.com · <a href="{$siteUrl}/en/tos" style="color:#adc2ff;">Terms of Service</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
