<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if(!isLoggedIn()) {
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    $_SESSION['login_message'] = "Per poter mandare la tua candidatura devi avere un account Cripsum™";

    header('Location: accedi');
    exit();
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$userRole = $_SESSION['ruolo'] ?? 'utente';
$profilePic = "/includes/get_pfp.php?id=$userId";

$result_candidatura = $_SESSION['result_candidatura'] ?? '';

?>

<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <meta charset="UTF-8">
    <title>Cripsum™ - Candidatura Chi siamo</title>
    <style>
        body {
            background: #0e0e0e;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .box {
            background: #1a1a1a;
            padding: 30px;
            border: 1px solid #ffffff22;
            border-radius: 12px;
            box-shadow: 0 0 10px #ffffff11;
            text-align: center;
            width: 100%;
            max-width: 400px;
        }
        input {
            padding: 12px;
            width: 100%;
            margin-bottom: 15px;
            border: 1px solid #333;
            border-radius: 6px;
            background: #111;
            color: white;
        }
        button {
            background: #ffffff;
            color: black;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            transition: 0.2s;
        }
        button:hover {
            background: #ddd;
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>

    <?php if ($result_candidatura): ?>
        <div class="alert alert-info" role="alert" style="margin-top: 7rem;">
            <?php echo htmlspecialchars($result_candidatura); ?>
        </div>
    <?php endif; ?>

    <div class="box" style="padding-top: 7rem;">
        <h2>Candidatura Chi siamo</h2>
        <form method="POST" action="invio_candidatura" id="candidaturaForm"></form>
            <input type="text" name="username" value="<?php echo htmlspecialchars($username); ?>" placeholder="Username" required>
            <input type="email" name="email" placeholder="Inserisci la tua email" required>
            <textarea name="descrizione" placeholder="Descrizione personaggio" rows="4" style="padding: 12px; width: 100%; margin-bottom: 15px; border: 1px solid #333; border-radius: 6px; background: #111; color: white; resize: vertical;" required></textarea>
            <div style="margin-bottom: 15px;">
                <label for="pfp_chisiamo" style="display: block; margin-bottom: 5px; text-align: left;">Carica foto profilo:</label>
                <input type="file" id="pfp_chisiamo" name="pfp_chisiamo" accept="image/*" style="padding: 8px; background: #111; border: 1px solid #333; border-radius: 6px;" required>
                <div id="imagePreview" style="margin-top: 10px; display: none;">
                    <img id="previewImg" style="max-width: 150px; max-height: 150px; border-radius: 6px;">
                </div>
            </div>
            <input type="text" name="social_username" placeholder="Username social (opzionale)">
            <input type="url" name="social_link" placeholder="Link social (opzionale)">
            <button class="btn btn-secondary bottone" type="submit" id="submitBtn" disabled>Carica immagine per continuare</button>
        </form>
        <a class="nav-link" href="../"><i class="fas fa-arrow-left"></i> Torna alla home</a>
    </div>

    <script>
        document.getElementById('pfp_chisiamo').addEventListener('change', function(event) {
            const file = event.target.files[0];
            const submitBtn = document.getElementById('submitBtn');
            const imagePreview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imagePreview.style.display = 'block';
                    
                    previewImg.onload = function() {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Invia candidatura';
                    };
                    
                    previewImg.onerror = function() {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Errore nel caricamento immagine';
                        imagePreview.style.display = 'none';
                    };
                };
                reader.readAsDataURL(file);
            } else {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Carica immagine per continuare';
                imagePreview.style.display = 'none';
            }
        });
    </script>
</body>
</html>
