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

// Array di persone del gruppo (aggiungi/modifica secondo necessità)
$persone = [
    [
        'id' => 'persona1',
        'nome' => 'Nome Persona 1',
        'nickname' => 'Nickname1',
        'ruolo' => 'Fondatore',
        'descrizione_breve' => 'Breve descrizione della persona e del suo ruolo nel gruppo.',
        'immagine' => 'path/to/image1.jpg'
    ],
    [
        'id' => 'persona2',
        'nome' => 'Nome Persona 2',
        'nickname' => 'Nickname2',
        'ruolo' => 'Membro',
        'descrizione_breve' => 'Un altro membro importante con caratteristiche uniche.',
        'immagine' => 'path/to/image2.jpg'
    ],
    // Aggiungi altre persone qui
];
?>

<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>Persone - Cripsumpedia™</title>
    <style>
        body {
            padding-top: 5rem;
        }

        .breadcrumb-nav {
            max-width: 1400px;
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

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 2rem 4rem;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-icon {
            font-size: 3.5rem;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #64c8ff, #4a9eff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #ffffff;
        }

        .section-description {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.7);
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .item-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            padding: 1.5rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            display: flex;
            gap: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .item-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.05), rgba(74, 158, 255, 0.02));
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .item-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.2);
        }

        .item-card:hover::before {
            opacity: 1;
        }

        .item-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            object-fit: cover;
            border: 2px solid rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .item-card:hover .item-image {
            border-color: rgba(100, 200, 255, 0.4);
            box-shadow: 0 4px 16px rgba(100, 200, 255, 0.2);
        }

        .item-content {
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .item-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .item-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .item-subtitle {
            font-size: 0.9rem;
            color: rgba(100, 200, 255, 0.8);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .item-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 12px;
            background: rgba(100, 200, 255, 0.1);
            color: #64c8ff;
            border: 1px solid rgba(100, 200, 255, 0.2);
            white-space: nowrap;
        }

        .item-description {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.65);
            line-height: 1.5;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .item-arrow {
            position: absolute;
            bottom: 1.5rem;
            right: 1.5rem;
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            z-index: 1;
        }

        .item-card:hover .item-arrow {
            color: rgba(100, 200, 255, 0.8);
            transform: translateX(3px);
        }

        @media (max-width: 992px) {
            .items-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 1.25rem;
            }

            .section-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 4rem;
            }

            .breadcrumb-nav {
                padding: 1.25rem 1.5rem 0.75rem;
            }

            .main-content {
                padding: 1.5rem 1.5rem 3rem;
            }

            .items-grid {
                grid-template-columns: 1fr;
            }

            .item-card {
                flex-direction: column;
            }

            .item-image {
                width: 100%;
                height: 200px;
            }

            .item-arrow {
                bottom: 1rem;
                right: 1rem;
            }
        }

        @media (max-width: 576px) {
            .breadcrumb-nav {
                padding: 1rem 1rem 0.5rem;
            }

            .main-content {
                padding: 1rem 1rem 2rem;
            }

            .section-icon {
                font-size: 2.5rem;
            }

            .section-title {
                font-size: 1.75rem;
            }

            .section-description {
                font-size: 1rem;
            }

            .item-card {
                padding: 1.25rem;
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
        <span class="breadcrumb-item active">Persone</span>
    </nav>

    <div class="main-content">
        <div class="section-header">
            <div class="section-icon">
                <i class="fas fa-users"></i>
            </div>
            <h1 class="section-title">Persone</h1>
            <p class="section-description">
                Conosci i membri del gruppo, le loro storie e il loro contributo alla community.
                Ogni persona ha reso unico questo gruppo con la propria personalità.
            </p>
        </div>

        <div class="items-grid">
            <?php foreach ($persone as $persona): ?>
                <a href="persona?id=<?php echo $persona['id']; ?>" class="item-card">
                    <img src="<?php echo htmlspecialchars($persona['immagine']); ?>"
                        alt="<?php echo htmlspecialchars($persona['nome']); ?>"
                        class="item-image">

                    <div class="item-content">
                        <div class="item-header">
                            <div>
                                <h3 class="item-title"><?php echo htmlspecialchars($persona['nome']); ?></h3>
                                <div class="item-subtitle"><?php echo htmlspecialchars($persona['nickname']); ?></div>
                            </div>
                            <span class="item-badge"><?php echo htmlspecialchars($persona['ruolo']); ?></span>
                        </div>
                        <p class="item-description">
                            <?php echo htmlspecialchars($persona['descrizione_breve']); ?>
                        </p>
                    </div>

                    <i class="fas fa-arrow-right item-arrow"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php include '../../includes/scroll_indicator.php'; ?>
    <?php include '../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>