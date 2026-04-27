<?php
$token = $_GET['token'] ?? '';
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Reset password</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/forms/forms.css?v=1.0-unified">
    <script src="/assets/forms/forms.js?v=1.0-unified" defer></script>
</head>

<body class="form-page">
    <?php include '../includes/navbar-morta.php'; ?>


    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>


    <main class="form-shell form-shell--narrow">
        <section class="form-card form-reveal">
            <div class="form-card__header">
                <span class="form-pill">Reset</span>
                <h1>Nuova password</h1>
                <p>Scegli una nuova password per il tuo account.</p>
            </div>

            <form method="POST" action="salva_nuova_password.php" data-form-loading>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                <label class="form-field">
                    <span>Nuova password</span>
                    <div class="password-wrap" data-password-wrap>
                        <input type="password" name="nuova_password" placeholder="Nuova password" required autocomplete="new-password">
                        <button type="button" class="password-toggle" data-toggle-password aria-label="Mostra password">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                    <small>Usa almeno 8 caratteri, se possibile.</small>
                </label>

                <div class="form-actions">
                    <button class="form-btn form-btn--primary form-btn--wide" type="submit" data-loading-text="Salvataggio...">
                        <i class="fas fa-check"></i>
                        <span>Salva password</span>
                    </button>
                </div>
            </form>

            <div class="form-links">
                <a href="accedi"><i class="fas fa-arrow-left"></i> Torna al login</a>
            </div>
        </section>
    </main>
</body>
</html>
