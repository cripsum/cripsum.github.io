<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per poter mandare la tua candidatura devi avere un account Cripsum™";

    header('Location: accedi');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userRole = $_SESSION['ruolo'] ?? 'utente';
$profilePic = "/includes/get_pfp.php?id=$user_id";

$result_candidatura = $_SESSION['result_candidatura'] ?? '';
unset($_SESSION['result_candidatura']);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Cripsum™ - Candidatura Chi siamo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/forms/forms.css?v=1.0-unified">
    <script src="/assets/forms/forms.js?v=1.0-unified" defer></script>
</head>

<body class="form-page">
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>


    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>


    <main class="form-shell form-shell--medium">
        <section class="form-card form-reveal">
            <div class="form-card__header">
                <span class="form-pill">Chi siamo</span>
                <h1>Candidatura</h1>
                <p>Manda i dati per apparire nella pagina Chi siamo.</p>
            </div>

            <?php if ($result_candidatura): ?>
                <div class="form-alert form-alert--info">
                    <i class="fas fa-circle-info"></i>
                    <span><?php echo htmlspecialchars($result_candidatura, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="invio_candidatura" enctype="multipart/form-data" id="candidaturaForm" data-form-loading>
                <div class="form-grid form-grid--2">
                    <label class="form-field">
                        <span>Username</span>
                        <input type="text" name="username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Username" required>
                    </label>

                    <label class="form-field">
                        <span>Email</span>
                        <input type="email" name="email" placeholder="email@esempio.com" required>
                    </label>
                </div>

                <label class="form-field">
                    <span>Descrizione personaggio</span>
                    <textarea name="descrizione" placeholder="Scrivi una descrizione breve" rows="4" maxlength="700" required></textarea>
                    <small>Breve, leggibile, in stile pagina Chi siamo.</small>
                </label>

                <label class="form-field">
                    <span>Foto profilo</span>
                    <input type="file" id="pfp_chisiamo" name="pfp_chisiamo" accept="image/*" required>
                </label>

                <div class="form-grid form-grid--2">
                    <label class="form-field">
                        <span>Username social</span>
                        <input type="text" name="social_username" placeholder="Opzionale">
                    </label>

                    <label class="form-field">
                        <span>Link social</span>
                        <input type="url" name="social_link" placeholder="https://...">
                    </label>
                </div>

                <div class="form-actions">
                    <button class="form-btn form-btn--primary form-btn--wide" type="submit" data-loading-text="Invio candidatura...">
                        <i class="fas fa-paper-plane"></i>
                        <span>Invia candidatura</span>
                    </button>
                </div>
            </form>

            <div class="form-links">
                <a href="../"><i class="fas fa-arrow-left"></i> Torna alla home</a>
            </div>
        </section>
    </main>

    <?php include '../includes/footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
