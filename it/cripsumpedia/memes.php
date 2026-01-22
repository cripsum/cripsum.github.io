<?php
// meme.php - Versione dinamica che legge dal database
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Recupera tutti i meme visibili con info creatore
$stmt = $mysqli->prepare("
    SELECT m.*, p.nome as creatore_nome 
    FROM cripsumpedia_meme m 
    LEFT JOIN cripsumpedia_persone p ON m.creatore_id = p.id 
    WHERE m.visibile = 1 
    ORDER BY m.anno DESC, m.titolo ASC
");
$stmt->execute();
$result = $stmt->get_result();
$meme = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meme - Cripsumpedia™</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style-dark.css">
    <style>
        body {
            font-family: "Poppins", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #0a0a0a;
            color: #ffffff;
            min-height: 100vh;
            padding-top: 5rem;
        }

        .main-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem 4rem;
        }

        .page-header {
            margin-bottom: 3rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #ffd764;
            transform: translateX(-3px);
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-icon {
            color: #ffd764;
            font-size: 2rem;
        }

        .page-description {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
        }

        .filter-section {
            margin-bottom: 2.5rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-label {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 500;
        }

        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 0.5rem 1.25rem;
            border-radius: 20px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .filter-btn:hover,
        .filter-btn.active {
            background: linear-gradient(135deg, rgba(255, 215, 100, 0.2), rgba(255, 184, 68, 0.15));
            border-color: rgba(255, 215, 100, 0.4);
            color: #ffd764;
        }

        .memes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
        }

        .meme-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(15px);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .meme-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 215, 100, 0.05), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 0;
        }

        .meme-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
            border-color: rgba(255, 215, 100, 0.3);
        }

        .meme-card:hover::before {
            opacity: 1;
        }

        .meme-image {
            width: 100%;
            height: 220px;
            background: linear-gradient(135deg, rgba(255, 215, 100, 0.15), rgba(255, 184, 68, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: #ffd764;
            position: relative;
            z-index: 1;
        }

        .meme-content {
            padding: 1.75rem;
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .meme-header {
            margin-bottom: 1rem;
        }

        .meme-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .meme-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
            margin-bottom: 0.75rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .meta-item i {
            color: rgba(255, 255, 255, 0.4);
        }

        .meme-description {
            font-size: 0.95rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            flex: 1;
        }

        .meme-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .meme-category {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 12px;
            background: rgba(255, 215, 100, 0.15);
            color: #ffd764;
            border: 1px solid rgba(255, 215, 100, 0.3);
            font-weight: 500;
        }

        .meme-arrow {
            color: rgba(255, 255, 255, 0.3);
            font-size: 1.25rem;
            transition: all 0.3s ease;
        }

        .meme-card:hover .meme-arrow {
            color: #ffd764;
            transform: translateX(5px);
        }

        .popularity-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            color: #ffd764;
            border: 1px solid rgba(255, 215, 100, 0.3);
            z-index: 2;
        }

        .popularity-badge i {
            font-size: 0.9rem;
        }

        @media (max-width: 992px) {
            .memes-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 1.5rem;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 2rem 1.5rem 3rem;
            }

            .page-title {
                font-size: 2rem;
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .memes-grid {
                grid-template-columns: 1fr;
            }

            .filter-section {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem 1rem 2rem;
            }

            .memes-grid {
                grid-template-columns: minmax(280px, 1fr);
            }

            .meme-content {
                padding: 1.5rem;
            }

            .meme-image {
                height: 180px;
                font-size: 3rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content" style="max-width: 1400px; margin: 0 auto; padding: 3rem 2rem 4rem;">
        <div class="page-header" style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <a href="home" style="display: inline-flex; align-items: center; gap: 0.5rem; color: rgba(255, 255, 255, 0.6); text-decoration: none; font-size: 0.95rem; margin-bottom: 1.5rem; transition: all 0.3s ease;">
                <i class="fas fa-arrow-left"></i>
                Torna alla home
            </a>
            <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.75rem; color: #ffffff; display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-face-grin-tears" style="color: #ffd764; font-size: 2rem;"></i>
                Meme
            </h1>
            <p style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.7); line-height: 1.7;">
                La raccolta definitiva di meme, inside jokes e momenti iconici del gruppo. Ogni meme racconta
                una storia e rappresenta un pezzo della nostra cultura condivisa che solo noi possiamo veramente apprezzare.
            </p>
        </div>

        <div style="margin-bottom: 2.5rem; display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
            <span style="font-size: 0.95rem; color: rgba(255, 255, 255, 0.6); font-weight: 500;">Filtra per categoria:</span>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <button class="filter-btn active" data-filter="all" style="padding: 0.5rem 1.25rem; border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04)); border: 1px solid rgba(255, 255, 255, 0.12); color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease; font-weight: 500;">
                    Tutti
                </button>
                <button class="filter-btn" data-filter="Citazioni" style="padding: 0.5rem 1.25rem; border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04)); border: 1px solid rgba(255, 255, 255, 0.12); color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease; font-weight: 500;">
                    Citazioni
                </button>
                <button class="filter-btn" data-filter="Situazioni" style="padding: 0.5rem 1.25rem; border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04)); border: 1px solid rgba(255, 255, 255, 0.12); color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease; font-weight: 500;">
                    Situazioni
                </button>
                <button class="filter-btn" data-filter="Inside Jokes" style="padding: 0.5rem 1.25rem; border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04)); border: 1px solid rgba(255, 255, 255, 0.12); color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease; font-weight: 500;">
                    Inside Jokes
                </button>
                <button class="filter-btn" data-filter="Leggende" style="padding: 0.5rem 1.25rem; border-radius: 20px; background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04)); border: 1px solid rgba(255, 255, 255, 0.12); color: rgba(255, 255, 255, 0.7); font-size: 0.9rem; cursor: pointer; transition: all 0.3s ease; font-weight: 500;">
                    Leggende
                </button>
            </div>
        </div>

        <?php if (empty($meme)): ?>
            <div style="text-align: center; padding: 4rem 2rem; color: rgba(255, 255, 255, 0.5);">
                <i class="fas fa-face-meh" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>Nessun meme ancora presente. Torna più tardi!</p>
            </div>
        <?php else: ?>
            <div class="memes-grid">
                <?php foreach ($meme as $m):
                    $badges = json_decode($m['badges'], true) ?? [];
                ?>
                    <a href="meme-dettaglio.php?id=<?= htmlspecialchars($m['slug']) ?>" class="meme-card" data-category="<?= htmlspecialchars($m['categoria']) ?>">
                        <div style="width: 100%; height: 220px; background: linear-gradient(135deg, rgba(255, 215, 100, 0.15), rgba(255, 184, 68, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 4rem; color: #ffd764; position: relative; z-index: 1;">
                            <i class="fas <?= htmlspecialchars($m['icona'] ?? 'fa-face-grin-tears') ?>"></i>
                        </div>

                        <?php if ($m['popolarita']): ?>
                            <div style="position: absolute; top: 1rem; right: 1rem; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(10px); padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; display: flex; align-items: center; gap: 0.4rem; color: #ffd764; border: 1px solid rgba(255, 215, 100, 0.3); z-index: 2;">
                                <i class="fas fa-fire" style="font-size: 0.9rem;"></i>
                                <?= htmlspecialchars($m['popolarita']) ?>
                            </div>
                        <?php endif; ?>

                        <div style="padding: 1.75rem; position: relative; z-index: 1; flex: 1; display: flex; flex-direction: column;">
                            <div style="margin-bottom: 1rem;">
                                <h3 style="font-size: 1.35rem; font-weight: 600; color: #ffffff; margin-bottom: 0.5rem; line-height: 1.3;">
                                    <?= htmlspecialchars($m['titolo']) ?>
                                </h3>
                                <div style="display: flex; gap: 1rem; font-size: 0.85rem; color: rgba(255, 255, 255, 0.5); margin-bottom: 0.75rem;">
                                    <?php if ($m['creatore_nome']): ?>
                                        <span style="display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-user" style="color: rgba(255, 255, 255, 0.4);"></i>
                                            <?= htmlspecialchars($m['creatore_nome']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($m['anno']): ?>
                                        <span style="display: flex; align-items: center; gap: 0.4rem;">
                                            <i class="fas fa-calendar" style="color: rgba(255, 255, 255, 0.4);"></i>
                                            <?= htmlspecialchars($m['anno']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <p style="font-size: 0.95rem; color: rgba(255, 255, 255, 0.7); line-height: 1.6; flex: 1;">
                                <?= htmlspecialchars($m['descrizione_breve']) ?>
                            </p>
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px solid rgba(255, 255, 255, 0.08);">
                                <?php if ($m['categoria']): ?>
                                    <span style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 12px; background: rgba(255, 215, 100, 0.15); color: #ffd764; border: 1px solid rgba(255, 215, 100, 0.3); font-weight: 500;">
                                        <?= htmlspecialchars($m['categoria']) ?>
                                    </span>
                                <?php endif; ?>
                                <i class="fas fa-chevron-right" style="color: rgba(255, 255, 255, 0.3); font-size: 1.25rem; transition: all 0.3s ease;"></i>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Filtro per categoria
        const filterButtons = document.querySelectorAll('.filter-btn');
        const memeCards = document.querySelectorAll('.meme-card');

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                filterButtons.forEach(btn => {
                    btn.classList.remove('active');
                    btn.style.background = 'linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04))';
                    btn.style.borderColor = 'rgba(255, 255, 255, 0.12)';
                    btn.style.color = 'rgba(255, 255, 255, 0.7)';
                });

                button.classList.add('active');
                button.style.background = 'linear-gradient(135deg, rgba(255, 215, 100, 0.2), rgba(255, 184, 68, 0.15))';
                button.style.borderColor = 'rgba(255, 215, 100, 0.4)';
                button.style.color = '#ffd764';

                const filterValue = button.dataset.filter;

                memeCards.forEach(card => {
                    if (filterValue === 'all') {
                        card.style.display = 'flex';
                    } else {
                        if (card.dataset.category === filterValue) {
                            card.style.display = 'flex';
                        } else {
                            card.style.display = 'none';
                        }
                    }
                });
            });
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>

</html>