<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if (isLoggedIn()) {
    header('Location: home');
    exit();
}

$error = '';

if ($_POST) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Compila tutti i campi';
    } else {
      if (loginUser($mysqli, $email, $password)) {
          header('Location: home');
          exit();
      } else {
          $error = 'Email o password errati';
      }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
    <head>
      <!-- Google tag (gtag.js) -->
      <script async src="https://www.googletagmanager.com/gtag/js?id=G-T0CTM2SBJJ"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          gtag('config', 'G-T0CTM2SBJJ');
        </script>
        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
            rel="stylesheet"
            integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
            crossorigin="anonymous"
        />
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" />
        <link rel="icon" href="../img/Susremaster.png" type="image/png" />
        <link rel="shortcut icon" href="../img/Susremaster.png" type="image/png" />
        <link href="https://fonts.googleapis.com/css?family=Poppins" rel="stylesheet" />
        <link rel="stylesheet" href="../css/style.css" />
        <link rel="stylesheet" href="../css/style-dark.css" />
        <link rel="stylesheet" href="../css/animations.css" />
        <script src="../js/animations.js"></script>
<script src="../js/richpresence.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/3.1.2/rollups/aes.js" integrity="sha256-/H4YS+7aYb9kJ5OKhFYPUjSJdrtV6AeyJOtTkw6X72o=" crossorigin="anonymous"></script>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Cripsum™ - accedi</title>
    </head>
    <body>
      <?php include '../includes/navbar.php'; ?>
      <div style="max-width: 1920px; margin: auto; padding-top: 7rem" class="testobianco">
      <div class="loginpagege text-center mt-5">
  <!-- Pills content -->
  <div class="tab-content">
    <div
      class="tab-pane fade show active"
      id="pills-login"
      role="tabpanel"
      aria-labelledby="tab-login"
    >
      <form method="post">
        <p class="fs-1 text mb-5 fadeup" style="font-weight: bold;">Accedi</p>

        <?php if ($error): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
  
        <!-- Email input -->
        <div data-mdb-input-init class="form-outline mb-4 fadeup">
            <label class="form-label" for="loginName">Email o username</label>
                                <input type="email" class="form-control" id="email" name="email" required>
        </div>
  
        <!-- Password input -->
        <div data-mdb-input-init class="form-outline mb-4 fadeup">
            <label class="form-label" for="password">Password</label>
<input type="password" class="form-control" id="password" name="password" required>
          
        </div>

        <p class="text-center fadeup ">Oppure:</p>

        <div class="text-center mb-3 fadeup">
            <button data-mdb-ripple-init type="button" class="btn btn-floating mx-1">
              <i class="bi bi-facebook" style="color: #ffffff"></i>
            </button>
    
            <button data-mdb-ripple-init type="button" class="btn btn-floating mx-1">
              <i class="bi bi-google" style="color: #ffffff"></i>
            </button>
    
            <button data-mdb-ripple-init type="button" class="btn btn-floating mx-1">
              <i class="bi bi-twitter" style="color: #ffffff"></i>
            </button>
    
            <button data-mdb-ripple-init type="button" class="btn btn-floating mx-1">
              <i class="bi bi-github" style="color: #ffffff"></i>
            </button>
          </div>
  
        <!-- 2 column grid layout -->
        <div class="row mb-4 fadeup">
          <div class="col-md-6 d-flex justify-content-center">
            <!-- Checkbox -->
            <div class="form-check mb-3 mb-md-0">
              <input
                class="form-check-input checco"
                type="checkbox"
                value=""
                id="loginCheck"
                checked
              />
              <label class="form-check-label" for="loginCheck">Resta connesso</label>
            </div>
          </div>
  
          <div class="col-md-6 d-flex justify-content-center ">
            <!-- Simple link -->
            <a href="supporto" style="font-weight: bold;" class="linkbianco">Password dimenticata?</a>
          </div>
        </div>
  
        <!-- Submit button -->
         
        <div class="button-container mb-3 fadeup" style="text-align: center; margin-top: 3%;">
          <button class="btn btn-secondary bottone" type="submit">
            <span class="testobianco">Accedi</span>
        </button>
        <p id="decrypt"></p>
          
          

        </div>
  
        <!-- Register buttons -->
        <div class="text-center fadeup">
          <p>Non hai un account? <a href="registrati" style="font-weight: bold;" class="linkbianco">Registrati</a></p>
        </div>
      </form>
    </div>
    <div
      class="tab-pane fade"
      id="pills-register"
      role="tabpanel"
      aria-labelledby="tab-register"
    >
    </div>
  </div>
</div>
  <!-- Pills content -->
  <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
    <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
    <ul class="list-inline">
      <li class="list-inline-item"><a href="privacy" class="linkbianco">Privacy</a></li>
      <li class="list-inline-item"><a href="tos" class="linkbianco">Termini</a></li>
      <li class="list-inline-item"><a href="supporto" class="linkbianco">Supporto</a></li>
  </ul>
</footer>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>
        <script src="../js/modeChanger.js"></script>

        <script src="../js/login.js"></script>
        <script>
            function isValidHttpUrl(string) {
                let url;

                try {
                    url = new URL(string);
                } catch (_) {
                    return false;
                }

                return url.protocol === "http:" || url.protocol === "https:";
            }

            function decrypt() {
                var password = document.getElementById("password").value;
                var encryptedAES = "U2FsdGVkX18MMbOfpiGk56QnaalMw+amgTDSmwZn9pa7VG9tMBjgn/Stdl3UmaJmj7WdUPH9so6nShqPpsGhxA==";
                    var decryptedBytes = CryptoJS.AES.decrypt(encryptedAES, password);
                    if(isValidHttpUrl(decryptedBytes.toString(CryptoJS.enc.Utf8))) {
                        window.location.href = decryptedBytes.toString(CryptoJS.enc.Utf8);
                    } else {
                        document.getElementById("decrypt").innerHTML = "Wrong password";
                    }
            }
        </script>
    </body>
</html>
