<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';
checkBan($mysqli);
if (!isLoggedIn() || !isOwner()) {
    $_SESSION['error_message'] = "mi dispiace, ma la pagina Cripsumpedia™ è ancora in fase di sviluppo.";
    header('Location: ../home');
    exit();
}

// Ottieni ID dalla query string
$persona_id = isset($_GET['id']) ? $_GET['id'] : null;

// Database persone (sostituisci con i tuoi dati reali)
$persone = [
    'persona1' => [
        'nome' => 'Nome Persona 1',
        'nickname' => 'Nickname1',
        'ruolo' => 'Fondatore',
        'immagine' => 'path/to/image1.jpg',
        'data_ingresso' => '2020',
        'biografia' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Questa è la biografia completa della persona, con dettagli sulla sua storia, personalità e contributi al gruppo.',
        'contributi' => [
            'Ha fondato il gruppo nel 2020',
            'Organizzatore di eventi principali',
            'Creatore di meme leggendari'
        ],
        'curiosita' => [
            'Ama la pizza con l\'ananas',
            'Ha visto Breaking Bad 5 volte',
            'Colleziona Funko Pop'
        ],
        'citazioni' => [
            '"Questa è una citazione famosa" - 2021',
            '"Un\'altra frase iconica" - 2022'
        ]
    ],
    // Aggiungi altre persone
];

// Verifica se la persona esiste
if (!$persona_id || !isset($persone[$persona_id])) {
    header('Location: utenti');
    exit();
}

$persona = $persone[$persona_id];
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../../includes/head-import.php'; ?>
    <title><?php echo htmlspecialchars($persona['nome']); ?> - Cripsumpedia™</title>
    <style>
        body {
            padding-top: 5rem;
        }

        .breadcrumb-nav {
            max-width: 1200px;
            margin: 0 auto;
            padding: 1.5rem 2rem 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .breadcrumb-item {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .breadcrumb-item:hover {
            color: #ffffff;
        }

        .breadcrumb-separator {
            color: rgba(255, 255, 255, 0.4);
            margin: 0 0.25rem;
        }

        .breadcrumb-item.active {
            color: #ffffff;
        }

        .detail-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 2rem 4rem;
        }

        .detail-header {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            padding: 2.5rem;
        }

        .detail-image-wrapper {
            position: relative;
        }

        .detail-image {
            width: 100%;
            aspect-ratio: 1;
            object-fit: cover;
            border-radius: 16px;
            border: 3px solid rgba(255, 255, 255, 0.15);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .detail-info {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .detail-nickname {
            font-size: 1rem;
            color: rgba(100, 200, 255, 0.8);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .detail-title {
            font-size: 3rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.75rem;
            line-height: 1.2;
        }

        .detail-meta {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-top: 1.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .meta-icon {
            color: rgba(100, 200, 255, 0.8);
            font-size: 1.1rem;
        }

        .meta-text {
            color: rgba(255, 255, 255, 0.85);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .content-section {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.06), rgba(255, 255, 255, 0.03));
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-icon {
            color: rgba(100, 200, 255, 0.8);
            font-size: 1.5rem;
        }

        .section-text {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.8;
            margin-bottom: 0;
        }

        .list-items {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .list-item {
            padding: 1rem 1.25rem;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 10px;
            border-left: 3px solid rgba(100, 200, 255, 0.5);
            margin-bottom: 0.75rem;
            color: rgba(255, 255, 255, 0.8);
            transition: all 0.3s ease;
        }

        .list-item:hover {
            background: rgba(255, 255, 255, 0.06);
            border-left-color: rgba(100, 200, 255, 0.8);
            transform: translateX(5px);
        }

        .list-item:last-child {
            margin-bottom: 0;
        }

        .quote-item {
            padding: 1.25rem;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.08), rgba(100, 200, 255, 0.04));
            border-radius: 12px;
            border-left: 4px solid rgba(100, 200, 255, 0.6);
            margin-bottom: 1rem;
            font-style: italic;
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.6;
        }

        .quote-item:last-child {
            margin-bottom: 0;
        }

        @media (max-width: 992px) {
            .detail-header {
                grid-template-columns: 200px 1fr;
                gap: 2rem;
                padding: 2rem;
            }

            .detail-title {
                font-size: 2.25rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 4rem;
            }

            .breadcrumb-nav {
                padding: 1.25rem 1.5rem 0.75rem;
            }

            .detail-container {
                padding: 1.5rem 1.5rem 3rem;
            }

            .detail-header {
                grid-template-columns: 1fr;
                gap: 2rem;
                padding: 1.75rem;
                text-align: center;
            }

            .detail-image-wrapper {
                max-width: 250px;
                margin: 0 auto;
            }

            .detail-title {
                font-size: 2rem;
            }

            .detail-meta {
                justify-content: center;
            }

            .content-section {
                padding: 1.5rem;
            }

            .section-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .breadcrumb-nav {
                padding: 1rem 1rem 0.5rem;
            }

            .detail-container {
                padding: 1rem 1rem 2rem;
            }

            .detail-header {
                padding: 1.5rem;
            }

            .detail-title {
                font-size: 1.75rem;
            }

            .content-section {
                padding: 1.25rem;
            }

            .section-title {
                font-size: 1.35rem;
            }

            .section-text {
                font-size: 1rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>
    <?php include '../../includes/impostazioni.php'; ?>

    <nav class="breadcrumb-nav">
        <a href="../home" class="breadcrumb-item">Home</a>
        <span class="breadcrumb-separator">›</span>
        <a href="home" class="breadcrumb-item">Cripsumpedia™</a>
        <span class="breadcrumb-separator">›</span>
        <a href="utenti" class="breadcrumb-item">Persone</a>
        <span class="breadcrumb-separator">›</span>
        <span class="breadcrumb-item active"><?php echo htmlspecialchars($persona['nome']); ?></span>
    </nav>

    <div class="detail-container">
        <div class="detail-header">
            <div class="detail-image-wrapper">
                <img src="<?php echo htmlspecialchars($persona['immagine']); ?>"
                    alt="<?php echo htmlspecialchars($persona['nome']); ?>"
                    class="detail-image">
            </div>

            <div class="detail-info">
                <div class="detail-nickname"><?php echo htmlspecialchars($persona['nickname']); ?></div>
                <h1 class="detail-title"><?php echo htmlspecialchars($persona['nome']); ?></h1>

                <div class="detail-meta">
                    <div class="meta-item">
                        <i class="fas fa-star meta-icon"></i>
                        <span class="meta-text"><?php echo htmlspecialchars($persona['ruolo']); ?></span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-calendar meta-icon"></i>
                        <span class="meta-text">Dal <?php echo htmlspecialchars($persona['data_ingresso']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-section">
            <h2 class="section-title">
                <i class="fas fa-user section-icon"></i>
                Biografia
            </h2>
            <p class="section-text"><?php echo nl2br(htmlspecialchars($persona['biografia'])); ?></p>
        </div>

        <?php if (!empty($persona['contributi'])): ?>
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-trophy section-icon"></i>
                    Contributi
                </h2>
                <ul class="list-items">
                    <?php foreach ($persona['contributi'] as $contributo): ?>
                        <li class="list-item"><?php echo htmlspecialchars($contributo); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($persona['curiosita'])): ?>
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-lightbulb section-icon"></i>
                    Curiosità
                </h2>
                <ul class="list-items">
                    <?php foreach ($persona['curiosita'] as $curiosita): ?>
                        <li class="list-item"><?php echo htmlspecialchars($curiosita); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($persona['citazioni'])): ?>
            <div class="content-section">
                <h2 class="section-title">
                    <i class="fas fa-quote-left section-icon"></i>
                    Citazioni Memorabili
                </h2>
                <div>
                    <?php foreach ($persona['citazioni'] as $citazione): ?>
                        <div class="quote-item"><?php echo htmlspecialchars($citazione); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../../includes/scroll_indicator.php'; ?>
    <?php include '../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>