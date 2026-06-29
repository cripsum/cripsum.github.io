<?php
require_once __DIR__ . '/bootstrap.php';

// Disabilita il limite di tempo per l'esecuzione dello script
set_time_limit(0);

// Disabilita l'output buffering per vedere l'avanzamento in tempo reale
if (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: text/html; charset=utf-8');
header('X-Accel-Buffering: no'); // Per server Nginx

echo "<!DOCTYPE html>
<html>
<head>
    <title>Invio Email Aggiornamento Termini</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #0f172a; color: #f8fafc; padding: 20px; line-height: 1.6; }
        h1 { color: #c084fc; border-bottom: 2px solid #334155; padding-bottom: 10px; }
        .log-container { background: #1e293b; border: 1px solid #334155; padding: 15px; border-radius: 8px; max-height: 500px; overflow-y: auto; font-family: monospace; font-size: 14px; }
        .success { color: #4ade80; }
        .error { color: #f87171; font-weight: bold; }
        .summary { margin-top: 20px; background: #1e293b; padding: 15px; border-radius: 8px; border-left: 4px solid #c084fc; }
    </style>
</head>
<body>
    <h1>Cripsum™ - Invio Massivo Email Termini & Privacy</h1>";

// Query per recuperare tutti gli utenti con email
$query = "SELECT id, username, email FROM utenti WHERE email IS NOT NULL AND email != ''";
$result = $mysqli->query($query);

if (!$result) {
    echo "<div class='error'>Errore nel recupero degli utenti dal database: " . htmlspecialchars($mysqli->error) . "</div></body></html>";
    exit();
}

$totalUsers = $result->num_rows;
echo "<p>Trovati <strong>$totalUsers</strong> utenti a cui inviare la comunicazione.</p>";
echo "<div class='log-container'>";
flush();

$sentCount = 0;
$errorCount = 0;

// Template HTML per l'email (stile Premium Dark/Purple)
function getTosEmailTemplate($username) {
    $siteUrl = SITE_URL;
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Aggiornamento Termini di Servizio e Privacy Policy</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #09090b; color: #e4e4e7; margin: 0; padding: 0; -webkit-font-smoothing: antialiased; }
            .wrapper { width: 100%; table-layout: fixed; background-color: #09090b; padding: 40px 0; }
            .container { max-width: 600px; margin: 0 auto; background-color: #121214; border: 1px solid #27272a; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5); }
            .header { background: linear-gradient(135deg, #4c1d95 0%, #7c3aed 100%); padding: 30px 20px; text-align: center; }
            .header h1 { margin: 0; color: #ffffff; font-size: 24px; font-weight: 800; letter-spacing: 0.5px; }
            .content { padding: 40px 30px; }
            .content p { font-size: 16px; line-height: 1.6; color: #a1a1aa; margin-top: 0; margin-bottom: 20px; }
            .content strong { color: #f4f4f5; }
            .highlight-box { background-color: #18181b; border: 1px dashed #3f3f46; border-radius: 8px; padding: 20px; margin-bottom: 25px; }
            .highlight-box h3 { margin-top: 0; color: #c084fc; font-size: 16px; margin-bottom: 10px; }
            .highlight-box ul { margin: 0; padding-left: 20px; color: #a1a1aa; font-size: 14px; }
            .highlight-box li { margin-bottom: 8px; }
            .btn-container { text-align: center; margin: 30px 0; }
            .btn { display: inline-block; background-color: #8b5cf6; color: #ffffff !important; text-decoration: none; padding: 12px 30px; font-weight: 700; border-radius: 8px; transition: background-color 0.2s ease; }
            .footer { background-color: #0e0e11; padding: 25px 20px; text-align: center; border-top: 1px solid #1e1e22; }
            .footer p { margin: 0; font-size: 12px; color: #71717a; line-height: 1.5; }
            .footer a { color: #a78bfa; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='wrapper'>
            <div class='container'>
                <div class='header'>
                    <h1>Cripsum™</h1>
                </div>
                <div class='content'>
                    <p>Ciao <strong>" . htmlspecialchars($username) . "</strong>,</p>
                    <p>Ti informiamo che abbiamo apportato importanti aggiornamenti ai nostri <strong>Termini e Condizioni</strong> e alla nostra <strong>Informativa sulla Privacy</strong>.</p>
                    <p>Questi cambiamenti riflettono le nuove funzionalità recentemente introdotte sulla piattaforma e servono a garantire una maggiore trasparenza e sicurezza per tutti i nostri utenti.</p>
                    
                    <div class='highlight-box'>
                        <h3>Cosa è cambiato in sintesi?</h3>
                        <ul>
                            <li><strong>Pagamenti e Abbonamenti:</strong> Regolamentazione degli acquisti di Godo Shards e dello status Premium elaborati in sicurezza tramite PayPal, con relative esclusioni di rimborso per i beni digitali.</li>
                            <li><strong>Integrazione Discord:</strong> Trasparenza sul collegamento opzionale del tuo account Discord e sulla visualizzazione dello stato online e di gioco tramite il nostro Bot di Presenza.</li>
                            <li><strong>Meccaniche di Gioco (Gacha, Lootbox, Duelli):</strong> Chiarimento della natura puramente virtuale e ludica di tali attività, che non costituiscono gioco d'azzardo reale e non hanno valore monetario.</li>
                            <li><strong>Contenuti Generati dagli Utenti (Shitpost):</strong> Definizione delle responsabilità relative ai contenuti caricati dagli utenti e dei diritti di moderazione dello staff.</li>
                            <li><strong>Ticket e Supporto:</strong> Regole sull'uso corretto e civile delle chat di supporto e sull'invio di allegati.</li>
                        </ul>
                    </div>
                    
                    <p>Ti invitiamo a prendere visione dei documenti completi cliccando sul pulsante sottostante. L'utilizzo continuato del nostro sito costituisce l'accettazione dei nuovi termini.</p>
                    
                    <div class='btn-container'>
                        <a href='{$siteUrl}/it/tos' class='btn'>Leggi i nuovi Termini</a>
                    </div>
                </div>
                <div class='footer'>
                    <p>Questa è una comunicazione di servizio obbligatoria riguardante il tuo account su <a href='{$siteUrl}'>Cripsum™</a>.</p>
                    <p>Si prega di non rispondere a questa email, la casella non è monitorata.</p>
                </div>
            </div>
        </div>
    </body>
    </html>";
}

// Loop e invio
while ($row = $result->fetch_assoc()) {
    $email = $row['email'];
    $username = $row['username'];
    
    $subject = 'Aggiornamento dei Termini di Servizio e Privacy Policy - Cripsum™';
    $htmlBody = getTosEmailTemplate($username);
    
    // Headers per email HTML
    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=utf-8';
    $headers[] = 'From: ' . FROM_NAME . ' <' . FROM_EMAIL . '>';
    $headers[] = 'Reply-To: ' . FROM_EMAIL;
    $headers[] = 'X-Mailer: PHP/' . phpversion();
    
    $success = @mail($email, $subject, $htmlBody, implode("\r\n", $headers));
    
    if ($success) {
        $sentCount++;
        echo "Inviata a <span class='success'>$username</span> ($email)... OK<br>";
    } else {
        $errorCount++;
        echo "Inviata a <span class='error'>$username</span> ($email)... <span class='error'>FALLITA</span><br>";
    }
    
    // Forza lo svuotamento del buffer verso il browser
    flush();
    
    // Un piccolo delay (50ms) per non sovraccaricare il server di posta locale
    usleep(50000);
}

echo "</div>"; // Chiude log-container

echo "
<div class='summary'>
    <h2>Risultato dell'invio</h2>
    <p>Email inviate con successo: <strong class='success'>$sentCount</strong></p>
    <p>Invii falliti: <strong class='error'>$errorCount</strong></p>
    <p>Totale elaborati: <strong>" . ($sentCount + $errorCount) . "</strong> su <strong>$totalUsers</strong></p>
</div>
</body>
</html>";
flush();
?>
