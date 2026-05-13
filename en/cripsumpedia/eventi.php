<?php
// eventi.php - Versione dinamica che legge dal database
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Recupera tutti gli eventi visibili, raggruppati per anno
$stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_eventi WHERE visibile = 1 ORDER BY anno DESC, data_inizio DESC");
$stmt->execute();
$result = $stmt->get_result();

// Raggruppa eventi per anno
$eventi_per_anno = [];
while ($evento = $result->fetch_assoc()) {
    $anno = $evento['anno'] ?? 'N/A';
    if (!isset($eventi_per_anno[$anno])) {
        $eventi_per_anno[$anno] = [];
    }
    $eventi_per_anno[$anno][] = $evento;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eventi - Cripsumpedia™</title>
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
            color: #ff64c8;
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
            color: #ff64c8;
            font-size: 2rem;
        }

        .page-description {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.7;
        }

        .timeline-section {
            position: relative;
            padding-left: 3rem;
        }

        .timeline-line {
            position: absolute;
            left: 1.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg,
                    rgba(255, 100, 200, 0.5),
                    rgba(255, 100, 200, 0.2),
                    rgba(255, 100, 200, 0.1));
        }

        .timeline-year {
            margin-bottom: 2.5rem;
        }

        .year-label {
            font-size: 1.5rem;
            font-weight: 600;
            color: #ff64c8;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .year-label::before {
            content: '';
            position: absolute;
            left: 1.1rem;
            width: 0.8rem;
            height: 0.8rem;
            background: #ff64c8;
            border-radius: 50%;
            box-shadow: 0 0 0 4px rgba(255, 100, 200, 0.2);
        }

        .events-grid {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .event-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            padding: 2rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
            display: block;
        }

        .event-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.05), transparent);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .event-card:hover {
            transform: translateX(8px);
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 100, 200, 0.3);
        }

        .event-card:hover::before {
            opacity: 1;
        }

        .event-header {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .event-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(255, 100, 200, 0.2), rgba(255, 74, 169, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #ff64c8;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }

        .event-card:hover .event-icon {
            transform: scale(1.05);
            box-shadow: 0 8px 24px rgba(255, 100, 200, 0.2);
        }

        .event-info {
            flex: 1;
        }

        .event-title {
            font-size: 1.35rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.5rem;
        }

        .event-date {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .event-date i {
            color: rgba(255, 255, 255, 0.4);
        }

        .event-description {
            font-size: 1rem;
            color: rgba(255, 255, 255, 0.7);
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }

        .event-tags {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            margin-top: 1rem;
            position: relative;
            z-index: 1;
        }

        .event-tag {
            font-size: 0.75rem;
            padding: 0.35rem 0.75rem;
            border-radius: 12px;
            background: rgba(255, 100, 200, 0.15);
            color: #ff64c8;
            border: 1px solid rgba(255, 100, 200, 0.3);
        }

        .event-arrow {
            position: absolute;
            top: 2rem;
            right: 2rem;
            color: rgba(255, 255, 255, 0.3);
            font-size: 1.5rem;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .event-card:hover .event-arrow {
            color: #ff64c8;
            transform: translateX(5px);
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

            .timeline-section {
                padding-left: 2rem;
            }

            .timeline-line {
                left: 0.75rem;
            }

            .year-label::before {
                left: 0.35rem;
            }

            .event-card {
                padding: 1.5rem;
            }

            .event-header {
                gap: 1rem;
            }

            .event-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }

            .event-arrow {
                position: static;
                margin-top: 1rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem 1rem 2rem;
            }

            .event-card {
                padding: 1.25rem;
            }

            .event-title {
                font-size: 1.2rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content" style="max-width: 1200px; margin: 0 auto; padding: 3rem 2rem 4rem;">
        <div class="page-header" style="margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
            <a href="home" style="display: inline-flex; align-items: center; gap: 0.5rem; color: rgba(255, 255, 255, 0.6); text-decoration: none; font-size: 0.95rem; margin-bottom: 1.5rem; transition: all 0.3s ease;">
                <i class="fas fa-arrow-left"></i>
                Torna alla home
            </a>
            <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.75rem; color: #ffffff; display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-calendar-star" style="color: #ff64c8; font-size: 2rem;"></i>
                Eventi
            </h1>
            <p style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.7); line-height: 1.7;">
                Una timeline completa di tutti gli eventi memorabili che hanno segnato la storia del gruppo.
                Dalle avventure epiche ai momenti di pura follia, ogni evento ha contribuito a creare i ricordi che condividiamo.
            </p>
        </div>

        <?php if (empty($eventi_per_anno)): ?>
            <div style="text-align: center; padding: 4rem 2rem; color: rgba(255, 255, 255, 0.5);">
                <i class="fas fa-calendar-xmark" style="font-size: 4rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                <p>Nessun evento ancora presente. Torna più tardi!</p>
            </div>
        <?php else: ?>
            <div class="timeline-section">
                <div class="timeline-line"></div>

                <?php foreach ($eventi_per_anno as $anno => $eventi): ?>
                    <div class="timeline-year">
                        <h2 class="year-label"><?= htmlspecialchars($anno) ?></h2>
                        <div class="events-grid">
                            <?php foreach ($eventi as $evento):
                                $tags = json_decode($evento['tags'], true) ?? [];
                            ?>
                                <a href="evento-dettaglio.php?id=<?= htmlspecialchars($evento['slug']) ?>" class="event-card">
                                    <div style="display: flex; align-items: flex-start; gap: 1.5rem; margin-bottom: 1rem; position: relative; z-index: 1;">
                                        <div style="width: 60px; height: 60px; border-radius: 12px; background: linear-gradient(135deg, rgba(255, 100, 200, 0.2), rgba(255, 74, 169, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #ff64c8; flex-shrink: 0; transition: all 0.3s ease;">
                                            <i class="fas <?= htmlspecialchars($evento['icona'] ?? 'fa-calendar-star') ?>"></i>
                                        </div>
                                        <div style="flex: 1;">
                                            <h3 style="font-size: 1.35rem; font-weight: 600; color: #ffffff; margin-bottom: 0.5rem;">
                                                <?= htmlspecialchars($evento['titolo']) ?>
                                            </h3>
                                            <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.5); display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                                                <i class="fas fa-calendar" style="color: rgba(255, 255, 255, 0.4);"></i>
                                                <?php
                                                if ($evento['data_inizio']) {
                                                    echo date('d/m/Y', strtotime($evento['data_inizio']));
                                                    if ($evento['data_fine'] && $evento['data_fine'] != $evento['data_inizio']) {
                                                        echo ' - ' . date('d/m/Y', strtotime($evento['data_fine']));
                                                    }
                                                } else {
                                                    echo 'Data non specificata';
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <i class="fas fa-chevron-right" style="position: absolute; top: 2rem; right: 2rem; color: rgba(255, 255, 255, 0.3); font-size: 1.5rem; transition: all 0.3s ease; z-index: 1;"></i>
                                    </div>
                                    <p style="font-size: 1rem; color: rgba(255, 255, 255, 0.7); line-height: 1.6; position: relative; z-index: 1; margin-bottom: 1rem;">
                                        <?= htmlspecialchars($evento['descrizione_breve']) ?>
                                    </p>
                                    <?php if (!empty($tags)): ?>
                                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 1rem; position: relative; z-index: 1;">
                                            <?php foreach ($tags as $tag): ?>
                                                <span style="font-size: 0.75rem; padding: 0.35rem 0.75rem; border-radius: 12px; background: rgba(255, 100, 200, 0.15); color: #ff64c8; border: 1px solid rgba(255, 100, 200, 0.3);">
                                                    <?= htmlspecialchars($tag) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>

</html>