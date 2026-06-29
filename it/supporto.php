<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$isLogged = function_exists('isLoggedIn') && isLoggedIn();
$username = $_SESSION['username'] ?? '';
$userId = $_SESSION['user_id'] ?? 'N/A';
$userRole = $_SESSION['ruolo'] ?? 'utente';

// Recuperiamo il Discord ID se l'utente è loggato
$discordId = '';
if ($isLogged) {
    $stmt = $mysqli->prepare("SELECT discord_id FROM utenti WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $discordId = $row['discord_id'] ?? '';
        }
        $stmt->close();
    }
}

$message_sent = false;
$error_message = '';
$ticketId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_ticket') {
    $title = trim($_POST['title'] ?? '');
    $topic = trim($_POST['topic'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    
    if (empty($title) || empty($topic) || empty($message)) {
        $error_message = 'Per favore, compila tutti i campi obbligatori.';
    } else {
        // Genera un ID univoco per il ticket
        $ticketId = 'TK-' . strtoupper(bin2hex(random_bytes(3)));
        
        // Elaborazione dell'allegato (immagine)
        $attachmentData = null;
        if (!empty($_FILES['attachment']['tmp_name']) && is_uploaded_file($_FILES['attachment']['tmp_name'])) {
            $check = getimagesize($_FILES['attachment']['tmp_name']);
            if ($check !== false) {
                if ($_FILES['attachment']['size'] <= 5 * 1024 * 1024) {
                    $imgData = file_get_contents($_FILES['attachment']['tmp_name']);
                    $attachmentData = [
                        'base64' => base64_encode($imgData),
                        'name' => $_FILES['attachment']['name'],
                        'type' => $_FILES['attachment']['type']
                    ];
                } else {
                    $error_message = 'L\'immagine allegata supera il limite massimo di 5MB.';
                }
            } else {
                $error_message = 'Il file caricato non è un\'immagine valida.';
            }
        }

        if (empty($error_message)) {
            $ticketData = [
                'ticket_id' => $ticketId,
                'username' => $isLogged ? $username : 'Ospite',
                'user_id' => $isLogged ? $userId : 'N/A',
                'role' => $isLogged ? $userRole : 'Ospite',
                'contact' => $isLogged ? ($_SESSION['email'] ?? 'Loggato sul sito') : $contact,
                'discord_id' => $discordId,
                'title' => $title,
                'topic' => $topic,
                'message' => $message,
                'attachment' => $attachmentData,
                'ip' => $_SERVER['REMOTE_ADDR']
            ];
            
            if (function_exists('curl_init')) {
                $ch = curl_init('https://api.cripsum.com/v1/tickets');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => json_encode($ticketData),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_CONNECTTIMEOUT => 3,
                    CURLOPT_TIMEOUT => 6,
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
    <style>
        /* Custom Dropdown */
        .custom-select-wrapper {
            position: relative;
            width: 100%;
            user-select: none;
        }
        .custom-select-trigger {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.8rem 1.2rem;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            color: #fff;
            cursor: pointer;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        .custom-select-trigger:hover, .custom-select-trigger.open {
            border-color: rgba(139, 92, 246, 0.4);
            background: rgba(255, 255, 255, 0.05);
            box-shadow: 0 0 10px rgba(139, 92, 246, 0.1);
        }
        .custom-select-trigger i {
            font-size: 0.8rem;
            transition: transform 0.2s ease;
            color: var(--text-muted);
        }
        .custom-select-trigger.open i {
            transform: rotate(180deg);
        }
        .custom-options {
            position: absolute;
            top: calc(100% + 5px);
            left: 0;
            right: 0;
            background: #0d121f;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 10px;
            z-index: 100;
            display: none;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
        }
        .custom-option {
            padding: 0.8rem 1.2rem;
            color: #9ca3af;
            cursor: pointer;
            transition: all 0.15s ease;
            font-size: 0.95rem;
        }
        .custom-option:hover {
            background: rgba(139, 92, 246, 0.15);
            color: #fff;
        }
        
        /* Custom File Upload & Preview */
        .file-upload-wrapper {
            position: relative;
        }
        .file-upload-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.02);
            border: 1px dashed rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }
        .file-upload-label:hover {
            border-color: rgba(139, 92, 246, 0.4);
            background: rgba(255, 255, 255, 0.04);
        }
        .file-upload-label i {
            font-size: 1.2rem;
            color: #a78bfa;
        }
        .file-upload-preview {
            margin-top: 0.8rem;
            display: none;
            max-height: 180px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            position: relative;
        }
        .file-upload-preview img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            max-height: 180px;
            background: rgba(0,0,0,0.2);
        }
        .remove-preview-btn {
            position: absolute;
            top: 8px;
            right: 8px;
            background: rgba(239, 68, 68, 0.8);
            border: none;
            color: white;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            transition: background 0.2s;
        }
        .remove-preview-btn:hover {
            background: rgba(239, 68, 68, 1);
        }
    </style>
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
                        <p>Stai scrivendo come <strong><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></strong> (ID: <?php echo $userId; ?>).</p>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="static-hero__side">
                <span class="static-chip"><i class="fa-solid fa-clock"></i> Risposta non immediata</span>
                <p>Invia un ticket qui sotto per ricevere supporto in tempo reale sul nostro canale Discord.</p>
            </aside>
        </section>

        <!-- Form Ticket di Supporto -->
        <section class="static-card static-reveal" style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
            <h2>Invia un ticket di supporto</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.2rem;">Il tuo messaggio verrà inviato direttamente allo staff di Cripsum su Discord.</p>
            
            <?php if ($message_sent): ?>
                <div style="background: rgba(35, 165, 90, 0.08); border: 1px solid rgba(35, 165, 90, 0.15); padding: 1.2rem; border-radius: 12px; color: var(--color-green, #23a55a); display: flex; flex-direction: column; gap: 6px;">
                    <span style="display: flex; align-items: center; gap: 8px; font-weight: 700;">
                        <i class="fa-solid fa-circle-check" style="font-size: 1.2rem;"></i>
                        Ticket Inviato!
                    </span>
                    <p style="font-size: 0.95rem; opacity: 0.9;">
                        La tua richiesta è stata registrata con il codice <strong><?php echo htmlspecialchars($ticketId); ?></strong>. 
                        Lo staff l'ha ricevuta nel canale Discord e ti risponderà al più presto.
                    </p>
                </div>
            <?php else: ?>
                <?php if ($error_message): ?>
                    <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.15); padding: 1rem; border-radius: 10px; color: #ef4444; display: flex; align-items: center; gap: 10px; margin-bottom: 1.2rem;">
                        <i class="fa-solid fa-circle-exclamation" style="font-size: 1.2rem;"></i>
                        <p><?php echo htmlspecialchars($error_message); ?></p>
                    </div>
                <?php endif; ?>
                
                <form action="" method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.2rem;">
                    <input type="hidden" name="action" value="send_ticket">
                    
                    <!-- Campo Titolo -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label for="title" style="font-weight: 600; font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Titolo della segnalazione *</label>
                        <input type="text" name="title" id="title" required placeholder="Es. Errore caricamento profilo, Bug lootbox..." style="padding: 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; outline: none; font-family: inherit; font-size: 0.95rem;">
                    </div>

                    <!-- Custom Dropdown per Argomento -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 600; font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Argomento *</label>
                        
                        <div class="custom-select-wrapper">
                            <div class="custom-select-trigger">
                                <span>Segnalazione Bug / Errore grafico</span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </div>
                            <div class="custom-options">
                                <div class="custom-option" data-value="Segnalazione Bug">Segnalazione Bug / Errore grafico</div>
                                <div class="custom-option" data-value="Problema Account">Problema di Accesso / Account</div>
                                <div class="custom-option" data-value="Segnalazione Utente">Segnalazione Utente</div>
                                <div class="custom-option" data-value="Altro">Altro / Domanda generica</div>
                            </div>
                            <!-- Input nascosto per passare il valore reale nel form -->
                            <input type="hidden" name="topic" id="real-topic" value="Segnalazione Bug">
                        </div>
                    </div>

                    <?php if (!$isLogged): ?>
                        <!-- Campo Contatto (Solo Ospiti) -->
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label for="contact" style="font-weight: 600; font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Come possiamo ricontattarti? *</label>
                            <input type="text" name="contact" id="contact" required placeholder="Inserisci la tua Email, @username Telegram o Discord" style="padding: 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; outline: none; font-family: inherit; font-size: 0.95rem;">
                        </div>
                    <?php endif; ?>

                    <!-- Campo Messaggio -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label for="message" style="font-weight: 600; font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Messaggio *</label>
                        <textarea name="message" id="message" required rows="5" placeholder="Fornisci quanti più dettagli possibili riguardo al problema..." style="padding: 0.8rem; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 8px; color: white; outline: none; resize: vertical; font-family: inherit; line-height: 1.5; font-size: 0.95rem;"></textarea>
                    </div>

                    <!-- Allegato Immagine con Preview -->
                    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 600; font-size: 0.82rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Allega uno screenshot (Opzionale, max 5MB)</label>
                        
                        <div class="file-upload-wrapper">
                            <label for="attachment" class="file-upload-label">
                                <i class="fa-solid fa-image"></i>
                                <span id="file-label">Trascina o seleziona un'immagine...</span>
                            </label>
                            <input type="file" name="attachment" id="attachment" accept="image/*" style="display: none;">
                            
                            <div class="file-upload-preview" id="preview-container">
                                <button type="button" class="remove-preview-btn" id="remove-preview"><i class="fa-solid fa-xmark"></i></button>
                                <img id="image-preview" src="" alt="Anteprima allegato">
                            </div>
                        </div>
                    </div>

                    <button type="submit" style="padding: 1rem; background: #8b5cf6; border: none; border-radius: 8px; color: white; font-weight: 600; font-family: inherit; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; box-shadow: 0 4px 12px rgba(139, 92, 246, 0.2); font-size: 0.95rem; margin-top: 0.5rem;" onmouseover="this.style.background='#7c3aed'; this.style.transform='translateY(-1px)';" onmouseout="this.style.background='#8b5cf6'; this.style.transform='translateY(0)';">
                        <i class="fa-solid fa-paper-plane"></i> Invia Ticket
                    </button>
                </form>
            <?php endif; ?>
        </section>

        <!-- Contatti Alternativi -->
        <section class="static-grid static-grid--2" style="margin-top:1rem;">
            <article class="static-contact-card static-reveal">
                <h2>Contatti Alternativi</h2>
                <div class="static-grid">
                    <a href="mailto:sburra@cripsum.com" class="static-contact-link">
                        <i class="fa-solid fa-envelope"></i>
                        <span>Email: sburra@cripsum.com</span>
                    </a>
                    <a href="https://t.me/cripsum" class="static-contact-link" target="_blank" rel="noopener">
                        <i class="fa-brands fa-telegram"></i>
                        <span>Telegram: @cripsum</span>
                    </a>
                    <a href="https://discord.gg/XdheJHVURw" class="static-contact-link" target="_blank" rel="noopener">
                        <i class="fa-brands fa-discord"></i>
                        <span>Discord: Cripsum Server</span>
                    </a>
                    <a href="https://www.instagram.com/cripsum/" class="static-contact-link" target="_blank" rel="noopener">
                        <i class="fa-brands fa-instagram"></i>
                        <span>Instagram: @cripsum</span>
                    </a>
                </div>
            </article>

            <article class="static-card static-reveal">
                <h2>Prima di scrivere</h2>
                <ul style="padding-left: 1.2rem; display: flex; flex-direction: column; gap: 6px; font-size: 0.95rem; color: #d1d5db;">
                    <li>Spiega cosa stavi facendo quando si è verificato l'errore.</li>
                    <li>Allega uno screenshot (consigliato per bug grafici).</li>
                    <li>Indica quale browser e dispositivo stai utilizzando.</li>
                    <li>Se riscontri un problema di acquisto, inserisci il tuo username.</li>
                </ul>
            </article>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestione Dropdown Custom
            const trigger = document.querySelector('.custom-select-trigger');
            const optionsContainer = document.querySelector('.custom-options');
            const hiddenInput = document.querySelector('#real-topic');
            const options = document.querySelectorAll('.custom-option');
            
            if (trigger && optionsContainer) {
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const isOpen = trigger.classList.contains('open');
                    if (isOpen) {
                        optionsContainer.style.display = 'none';
                        trigger.classList.remove('open');
                    } else {
                        optionsContainer.style.display = 'block';
                        trigger.classList.add('open');
                    }
                });
                
                options.forEach(opt => {
                    opt.addEventListener('click', function() {
                        const val = this.getAttribute('data-value');
                        const text = this.textContent;
                        trigger.querySelector('span').textContent = text;
                        hiddenInput.value = val;
                        optionsContainer.style.display = 'none';
                        trigger.classList.remove('open');
                    });
                });
                
                document.addEventListener('click', function() {
                    optionsContainer.style.display = 'none';
                    trigger.classList.remove('open');
                });
            }

            // Gestione Allegato Immagine e Preview
            const fileInput = document.getElementById('attachment');
            const fileLabel = document.getElementById('file-label');
            const previewContainer = document.getElementById('preview-container');
            const imagePreview = document.getElementById('image-preview');
            const removePreview = document.getElementById('remove-preview');

            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const file = this.files[0];
                    if (file) {
                        // Verifica che sia un'immagine
                        if (!file.type.startsWith('image/')) {
                            alert('Per favore seleziona solo file di tipo immagine.');
                            this.value = '';
                            return;
                        }
                        // Verifica dimensione (5MB)
                        if (file.size > 5 * 1024 * 1024) {
                            alert('L\'immagine non può superare i 5MB.');
                            this.value = '';
                            return;
                        }

                        fileLabel.textContent = file.name;

                        // Mostra anteprima
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            previewContainer.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    }
                });
            }

            if (removePreview) {
                removePreview.addEventListener('click', function(e) {
                    e.preventDefault();
                    fileInput.value = '';
                    fileLabel.textContent = "Trascina o seleziona un'immagine...";
                    previewContainer.style.display = 'none';
                    imagePreview.src = '';
                });
            }
        });
    </script>
</body>
</html>
