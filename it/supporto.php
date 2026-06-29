<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$isLogged = function_exists('isLoggedIn') && isLoggedIn();
$username = $_SESSION['username'] ?? '';

$message_sent = false;
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_ticket') {
    $topic = trim($_POST['topic'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    
    if (empty($topic) || empty($message)) {
        $error_message = 'Per favore, compila tutti i campi obbligatori.';
    } else {
        $ticketData = [
            'username' => $isLogged ? $username : 'Ospite',
            'contact' => $isLogged ? ($_SESSION['email'] ?? 'Loggato') : $contact,
            'topic' => $topic,
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
        
        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.cripsum.com/v1/tickets');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($ticketData),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($response && $statusCode === 200) {
                $decoded = json_decode($response, true);
                if (!empty($decoded['success'])) {
                    $message_sent = true;
                } else {
                    $error_message = 'Errore del server di supporto: ' . ($decoded['error'] ?? 'Impossibile inviare.');
                }
            } else {
                $error_message = 'Il server di supporto è offline al momento. Riprova più tardi o usa i contatti diretti qui sotto.';
            }
        } else {
            $error_message = 'Libreria cURL non disponibile sul server.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Supporto</title>

    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/static/static.css?v=1.0-static">
    <script src="/assets/static/static.js?v=1.0-static" defer></script>

</head>

<body class="static-page">
    <?php include '../includes/navbar.php'; ?>
    


    <div class="static-bg" aria-hidden="true">
        <span class="static-orb static-orb--one"></span>
        <span class="static-orb static-orb--two"></span>
        <span class="static-grid-bg"></span>
    </div>


    <main class="static-shell">
        <section class="static-hero static-hero--split static-reveal">
            <div>
                <span class="static-pill">Supporto</span>
                <h1>Serve aiuto?</h1>
                <p>Qui trovi contatti e risposte rapide ai problemi più comuni.</p>

                <?php if ($isLogged): ?>
                    <div class="static-alert static-alert--success" style="margin-top:1rem;">
                        <i class="fa-solid fa-user-check"></i>
                        <p>Stai scrivendo come <strong><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong>. Se segnali un problema, includi anche il tuo username.</p>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fa-solid fa-clock"></i> Risposta non immediata</span>
                <p>Scrivi in modo chiaro. Aiuta a risolvere prima.</p>
            </aside>
        </section>

        <section class="static-grid static-grid--2" style="margin-top:1rem;">
            <article class="static-contact-card static-reveal">
                <h2>Contatti</h2>
                <div class="static-grid">
                    <a href="mailto:sburra@cripsum.com" class="static-contact-link">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Email: sburra@cripsum.com</span>
                    </a>
                    <a href="#" class="static-contact-link">
                        <i class="fa-brands fa-telegram"></i>
                        <span>Telegram: @cripsum</span>
                    </a>
                    <a href="#" class="static-contact-link">
                        <i class="fa-brands fa-discord"></i>
                        <span>Discord: @cripsum</span>
                    </a>
                    <a href="#" class="static-contact-link">
                        <i class="fa-brands fa-instagram"></i>
                        <span>Instagram: @cripsum</span>
                    </a>
                </div>
            </article>

            <article class="static-card static-reveal">
                <h2>Prima di scrivere</h2>
                <ul>
                    <li>Spiega cosa stavi facendo.</li>
                    <li>Allega uno screenshot se serve.</li>
                    <li>Scrivi browser, dispositivo e pagina coinvolta.</li>
                    <li>Se hai un account, indica lo username.</li>
                </ul>
            </article>
        </section>

        <section class="static-card static-reveal" style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
            <h2>Invia un ticket di supporto</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">Il tuo messaggio verrà recapitato istantaneamente allo staff su Discord.</p>
            
            <?php if ($message_sent): ?>
                <div style="background: rgba(35, 165, 90, 0.1); border: 1px solid rgba(35, 165, 90, 0.2); padding: 1rem; border-radius: 8px; color: #23a55a; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
                    <p><strong>Ticket inviato con successo!</strong> Lo staff ha ricevuto la tua richiesta su Discord e ti risponderà al più presto.</p>
                </div>
            <?php else: ?>
                <?php if ($error_message): ?>
                    <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); padding: 1rem; border-radius: 8px; color: #ef4444; display: flex; align-items: center; gap: 10px; margin-bottom: 1.2rem;">
                        <i class="fa-solid fa-circle-exclamation" style="font-size: 1.2rem;"></i>
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php endif; ?>
                
                <form action="" method="POST" style="display: flex; flex-direction: column; gap: 1.2rem;">
                    <input type="hidden" name="action" value="send_ticket">
                    
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label for="topic" style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Argomento *</label>
                        <select name="topic" id="topic" required style="padding: 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; outline: none; font-family: inherit; cursor: pointer;">
                            <option value="Segnalazione Bug" style="background: #0f172a;">Segnalazione Bug / Errore grafico</option>
                            <option value="Problema Account" style="background: #0f172a;">Problema di Accesso / Account</option>
                            <option value="Segnalazione Utente" style="background: #0f172a;">Segnalazione Utente</option>
                            <option value="Altro" style="background: #0f172a;">Altro / Domanda generica</option>
                        </select>
                    </div>

                    <?php if (!$isLogged): ?>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label for="contact" style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Come possiamo ricontattarti? *</label>
                            <input type="text" name="contact" id="contact" required placeholder="Inserisci la tua Email, @username Telegram o Discord" style="padding: 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; outline: none; font-family: inherit;">
                        </div>
                    <?php endif; ?>

                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label for="message" style="font-weight: 600; font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Messaggio *</label>
                        <textarea name="message" id="message" required rows="5" placeholder="Fornisci quanti più dettagli possibili..." style="padding: 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; outline: none; resize: vertical; font-family: inherit; line-height: 1.5;"></textarea>
                    </div>

                    <button type="submit" style="padding: 1rem; background: var(--accent, #8b5cf6); border: none; border-radius: 8px; color: white; font-weight: 600; font-family: inherit; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2);" onmouseover="this.style.background='#7c3aed'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='var(--accent, #8b5cf6)'; this.style.transform='translateY(0)';">
                        <i class="fa-solid fa-paper-plane"></i> Invia Segnalazione
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <section class="static-faq static-reveal" id="supportFaq" style="margin-top:1rem;">
            <h2>Domande rapide</h2>

            <label class="static-faq-search">
                <i class="fa-solid fa-search"></i>
                <input type="search" placeholder="Cerca nelle FAQ..." data-static-faq-search="#supportFaq">
            </label>

            <details class="static-faq-item">
                <summary>Non riesco ad accedere</summary>
                <p>Prova il recupero password. Se non ricevi email, controlla spam o scrivi al supporto.</p>
            </details>

            <details class="static-faq-item">
                <summary>La lootbox non salva qualcosa</summary>
                <p>Controlla di essere loggato. Poi prova refresh e verifica che la sessione non sia scaduta.</p>
            </details>

            <details class="static-faq-item">
                <summary>Vedo un bug grafico</summary>
                <p>Fai refresh forzato e svuota la cache. Se resta, manda screenshot e nome pagina.</p>
            </details>

            <details class="static-faq-item">
                <summary>Come segnalo un utente?</summary>
                <p>Usa gli strumenti presenti nella pagina, se disponibili. Altrimenti scrivi al supporto con username e motivo.</p>
            </details>

            <p class="static-empty" data-static-faq-empty>Nessun risultato trovato.</p>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
