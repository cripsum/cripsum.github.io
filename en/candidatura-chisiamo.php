<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "To apply for the About us page you need to be logged in.";

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
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Cripsum™ - About us application</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="stylesheet" href="/assets/forms/forms.css?v=1.0-unified">
    <script src="/assets/forms/forms.js?v=1.0-unified" defer></script>
</head>

<body class="form-page">
    <?php include '../includes/navbar.php'; ?>



    <div class="form-bg" aria-hidden="true">
        <span class="form-orb form-orb--one"></span>
        <span class="form-orb form-orb--two"></span>
        <span class="form-grid-bg"></span>
    </div>


    <main class="form-shell form-shell--medium">
        <section class="form-card form-reveal">
            <div class="form-card__header">
                <span class="form-pill">About us</span>
                <h1>Application</h1>
                <p>Send your details to appear on the About us page.</p>
            </div>

            <?php if ($result_candidatura): ?>
                <div class="form-alert form-alert--info">
                    <i class="fa-solid fa-circle-info"></i>
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
                    <span>Description</span>
                    <textarea name="descrizione" placeholder="Write a brief description" rows="4" maxlength="700" required></textarea>
                    <small>Brief, readable, in the style of the About us page.</small>
                </label>

                <label class="form-field">
                    <span>Profile picture</span>
                    <input type="file" id="pfp_chisiamo" name="pfp_chisiamo" accept="image/*" required>
                </label>

                <div class="form-grid form-grid--2">
                    <label class="form-field">
                        <span>Social username</span>
                        <input type="text" name="social_username" placeholder="Optional">
                    </label>

                    <label class="form-field">
                        <span>Social link</span>
                        <input type="url" name="social_link" placeholder="https://...">
                    </label>
                </div>

                <div class="form-actions">
                    <button class="form-btn form-btn--primary form-btn--wide" type="submit" data-loading-text="Submitting application...">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Submit application</span>
                    </button>
                </div>
            </form>

            <div class="form-links">
                <a href="../"><i class="fa-solid fa-arrow-left"></i> Back to home</a>
            </div>
        </section>
    </main>

    <?php include '../includes/footer-en.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>