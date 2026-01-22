<?php
// persone.php - Versione dinamica che legge dal database
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Recupera tutte le persone visibili
$stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_persone WHERE visibile = 1 ORDER BY ordine ASC, anno_ingresso ASC, nome ASC");
$stmt->execute();
$result = $stmt->get_result();
$persone = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Persone - Cripsumpedia™</title>
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
            max-width: 1200px;
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
            color: #64c8ff;
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
            color: #64c8ff;
            font-size: 2rem;
        }

        .page-description {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
        }

        .search-section {
            margin-bottom: 2.5rem;
        }

        .search-box {
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            color: #ffffff;
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .search-input:focus {
            outline: none;
            border-color: #64c8ff;
            box-shadow: 0 0 0 3px rgba(100, 200, 255, 0.1);
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.06));
        }

        .search-input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }

        .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.4);
            font-size: 1.1rem;
        }

        .people-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .person-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 2rem;
            display: flex;
            gap: 2rem;
            align-items: flex-start;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
        }

        .person-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.05), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .person-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.3);
            border-color: rgba(100, 200, 255, 0.3);
        }

        .person-card:hover::before {
            opacity: 1;
        }

        .person-avatar {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.2), rgba(74, 158, 255, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #64c8ff;
            flex-shrink: 0;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
        }

        .person-card:hover .person-avatar {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(100, 200, 255, 0.2);
        }

        .person-info {
            flex: 1;
            position: relative;
            z-index: 1;
        }

        .person-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 0.75rem;
        }

        .person-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ffffff;
            margin: 0;
        }

        .person-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 12px;
            background: rgba(100, 200, 255, 0.15);
            color: #64c8ff;
            border: 1px solid rgba(100, 200, 255, 0.3);
            font-weight: 500;
        }

        .person-meta {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-item i {
            color: rgba(255, 255, 255, 0.4);
        }

        .person-description {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            margin: 0;
        }

        .person-arrow {
            margin-left: auto;
            color: rgba(255, 255, 255, 0.3);
            font-size: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            align-self: center;
        }

        .person-card:hover .person-arrow {
            color: #64c8ff;
            transform: translateX(5px);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
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

            .person-card {
                flex-direction: column;
                gap: 1.5rem;
                padding: 1.5rem;
            }

            .person-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }

            .person-arrow {
                position: absolute;
                bottom: 1.5rem;
                right: 1.5rem;
            }

            .person-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem 1rem 2rem;
            }

            .person-card {
                padding: 1.25rem;
            }

            .person-name {
                font-size: 1.3rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content" style="max-width: 1200px; margin: 0 auto; padding: 3rem 2rem 4rem;">
        <div class="page-header" style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <a href="cripsumpedia.php" class="back-link" style="display: inline-flex; align-items: center; gap: 0.5rem; color: rgba(255, 255, 255, 0.6); text-decoration: none; font-size: 0.95rem; margin-bottom: 1.5rem; transition: all 0.3s ease;">
                <i class="fas fa-arrow-left"></i>
                Torna alla home
            </a>
            <h1 class="page-title" style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.75rem; color: #ffffff; display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-users" style="color: #64c8ff; font-size: 2rem;"></i>
                Persone
            </h1>
            <p class="page-description" style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.7); line-height: 1.7;">
                Esplora i profili di tutti i membri del gruppo. Ogni persona ha la propria storia,
                personalità e contributi unici che hanno aiutato a costruire la nostra community.
            </p>
        </div>

        <div class="search-section" style="margin-bottom: 2.5rem;">
            <div class="search-box" style="position: relative;">
                <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: rgba(255, 255, 255, 0.4); font-size: 1.1rem;"></i>
                <input type="text" class="search-input" placeholder="Cerca una persona..." id="searchInput" style="width: 100%; padding: 1rem 1rem 1rem 3rem; background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04)); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 12px; color: #ffffff; font-size: 1rem;">
            </div>
        </div>

        <div class="people-list" id="peopleList" style="display: flex; flex-direction: column; gap: 1.5rem;">
            <?php foreach ($persone as $persona):
                $badges = json_decode($persona['badges'], true) ?? [];
            ?>
                <a href="persona-dettaglio.php?id=<?= htmlspecialchars($persona['slug']) ?>" class="person-card" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04)); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 16px; padding: 2rem; display: flex; gap: 2rem; align-items: flex-start; text-decoration: none; color: inherit; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); backdrop-filter: blur(15px); position: relative; overflow: hidden;">
                    <div class="person-avatar" style="width: 100px; height: 100px; border-radius: 12px; background: linear-gradient(135deg, rgba(100, 200, 255, 0.2), rgba(74, 158, 255, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; color: #64c8ff; flex-shrink: 0;">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="person-info" style="flex: 1; position: relative; z-index: 1;">
                        <div class="person-header" style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.75rem; flex-wrap: wrap;">
                            <h2 class="person-name" style="font-size: 1.5rem; font-weight: 600; color: #ffffff; margin: 0;">
                                <?= htmlspecialchars($persona['nome']) ?>
                            </h2>
                            <?php if (!empty($badges[0])): ?>
                                <span class="person-badge" style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 12px; background: rgba(100, 200, 255, 0.15); color: #64c8ff; border: 1px solid rgba(100, 200, 255, 0.3); font-weight: 500;">
                                    <?= htmlspecialchars($badges[0]) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="person-meta" style="display: flex; gap: 1.5rem; margin-bottom: 1rem; font-size: 0.9rem; color: rgba(255, 255, 255, 0.5); flex-wrap: wrap;">
                            <?php if ($persona['anno_ingresso']): ?>
                                <span class="meta-item" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-calendar" style="color: rgba(255, 255, 255, 0.4);"></i>
                                    Nel gruppo dal <?= htmlspecialchars($persona['anno_ingresso']) ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($persona['citta']): ?>
                                <span class="meta-item" style="display: flex; align-items: center; gap: 0.5rem;">
                                    <i class="fas fa-map-marker-alt" style="color: rgba(255, 255, 255, 0.4);"></i>
                                    <?= htmlspecialchars($persona['citta']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="person-description" style="font-size: 1rem; color: rgba(255, 255, 255, 0.7); line-height: 1.6; margin: 0;">
                            <?= htmlspecialchars($persona['descrizione_breve']) ?>
                        </p>
                    </div>
                    <i class="fas fa-chevron-right person-arrow" style="margin-left: auto; color: rgba(255, 255, 255, 0.3); font-size: 1.5rem; position: relative; z-index: 1; align-self: center;"></i>
                </a>
            <?php endforeach; ?>

            <?php if (empty($persone)): ?>
                <div class="empty-state" style="text-align: center; padding: 4rem 2rem; color: rgba(255, 255, 255, 0.5);">
                    <i class="fas fa-users" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                    <p>Nessuna persona ancora presente. Torna più tardi!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Funzione di ricerca
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.person-card');

            cards.forEach(card => {
                const name = card.querySelector('.person-name').textContent.toLowerCase();
                const description = card.querySelector('.person-description').textContent.toLowerCase();

                if (name.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        });
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>

</html>