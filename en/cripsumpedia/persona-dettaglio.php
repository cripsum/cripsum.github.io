<?php
// persona-dettaglio.php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$slug = $_GET['id'] ?? '';

if (!$slug) {
    header('Location: persone.php');
    exit;
}

// Recupera dati persona
$stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_persone WHERE slug = ? AND visibile = 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$persona = $result->fetch_assoc();
$stmt->close();

if (!$persona) {
    header('Location: persone.php');
    exit;
}

$badges = json_decode($persona['badges'], true) ?? [];

// Recupera contenuti correlati
$correlati_query = "
    SELECT 'evento' as tipo, e.slug, e.titolo as nome, e.icona
    FROM cripsumpedia_relazioni r
    JOIN cripsumpedia_eventi e ON r.id_a = e.id
    WHERE r.tipo_da = 'persona' AND r.id_da = ? AND r.tipo_a = 'evento'
    UNION
    SELECT 'meme' as tipo, m.slug, m.titolo as nome, m.icona
    FROM cripsumpedia_relazioni r
    JOIN cripsumpedia_meme m ON r.id_a = m.id
    WHERE r.tipo_da = 'persona' AND r.id_da = ? AND r.tipo_a = 'meme'
    LIMIT 6
";
$stmt = $mysqli->prepare($correlati_query);
$stmt->bind_param("ii", $persona['id'], $persona['id']);
$stmt->execute();
$correlati = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($persona['nome']) ?> - Cripsumpedia™</title>
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 3rem 2rem 4rem;
        }

        .breadcrumb-nav {
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.5);
        }

        .breadcrumb-link {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .breadcrumb-link:hover {
            color: #64c8ff;
        }

        .breadcrumb-separator {
            color: rgba(255, 255, 255, 0.3);
        }

        .article-header {
            margin-bottom: 3rem;
        }

        .profile-section {
            display: flex;
            gap: 2.5rem;
            margin-bottom: 2.5rem;
            align-items: flex-start;
        }

        .profile-avatar {
            width: 180px;
            height: 180px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.2), rgba(74, 158, 255, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 5rem;
            color: #64c8ff;
            flex-shrink: 0;
            border: 2px solid rgba(100, 200, 255, 0.3);
            box-shadow: 0 8px 32px rgba(100, 200, 255, 0.2);
        }

        .profile-info {
            flex: 1;
        }

        .profile-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: #ffffff;
        }

        .profile-badges {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            flex-wrap: wrap;
        }

        .badge-item {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            background: rgba(100, 200, 255, 0.15);
            color: #64c8ff;
            border: 1px solid rgba(100, 200, 255, 0.3);
            font-size: 0.85rem;
            font-weight: 500;
        }

        .profile-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.08);
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .meta-label {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .meta-value {
            font-size: 1rem;
            color: #ffffff;
            font-weight: 500;
        }

        .intro-text {
            font-size: 1.15rem;
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.8;
            margin-bottom: 3rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.05), transparent);
            border-left: 3px solid #64c8ff;
            border-radius: 4px;
        }

        .content-section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .section-icon {
            color: #64c8ff;
            font-size: 1.5rem;
        }

        .section-content {
            font-size: 1.05rem;
            color: rgba(255, 255, 255, 0.75);
            line-height: 1.8;
        }

        .section-content p {
            margin-bottom: 1.25rem;
        }

        .section-content h3 {
            font-size: 1.35rem;
            font-weight: 600;
            color: #ffffff;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .section-content ul,
        .section-content ol {
            margin-left: 1.5rem;
            margin-bottom: 1.25rem;
        }

        .section-content li {
            margin-bottom: 0.75rem;
            line-height: 1.7;
        }

        .highlight-box {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
            backdrop-filter: blur(10px);
        }

        .highlight-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #64c8ff;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quote-box {
            border-left: 4px solid #64c8ff;
            padding: 1.25rem 1.5rem;
            margin: 1.5rem 0;
            background: rgba(100, 200, 255, 0.05);
            border-radius: 4px;
            font-style: italic;
            color: rgba(255, 255, 255, 0.85);
        }

        .related-section {
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid rgba(255, 255, 255, 0.1);
        }

        .related-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #ffffff;
        }

        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.25rem;
        }

        .related-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 12px;
            padding: 1.25rem;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .related-card:hover {
            transform: translateY(-4px);
            border-color: rgba(100, 200, 255, 0.3);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
        }

        .related-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.15), rgba(74, 158, 255, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #64c8ff;
            flex-shrink: 0;
        }

        .related-info {
            flex: 1;
        }

        .related-name {
            font-size: 1rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .related-type {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 2rem 1.5rem 3rem;
            }

            .profile-section {
                flex-direction: column;
                gap: 1.5rem;
                align-items: center;
                text-align: center;
            }

            .profile-avatar {
                width: 140px;
                height: 140px;
                font-size: 4rem;
            }

            .profile-title {
                font-size: 2rem;
            }

            .profile-badges {
                justify-content: center;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .related-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1.5rem 1rem 2rem;
            }

            .profile-meta {
                grid-template-columns: 1fr;
            }

            .breadcrumb-nav {
                flex-wrap: wrap;
            }
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content" style="max-width: 1000px; margin: 0 auto; padding: 3rem 2rem 4rem;">
        <nav class="breadcrumb-nav" style="margin-bottom: 2rem;">
            <a href="home" class="breadcrumb-link">Home</a>
            <span class="breadcrumb-separator">›</span>
            <a href="persone" class="breadcrumb-link">Persone</a>
            <span class="breadcrumb-separator">›</span>
            <span><?= htmlspecialchars($persona['nome']) ?></span>
        </nav>

        <article class="article-header" style="margin-bottom: 3rem;">
            <div class="profile-section" style="display: flex; gap: 2.5rem; margin-bottom: 2.5rem; align-items: flex-start;">
                <div class="profile-avatar" style="width: 180px; height: 180px; border-radius: 16px; background: linear-gradient(135deg, rgba(100, 200, 255, 0.2), rgba(74, 158, 255, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 5rem; color: #64c8ff; flex-shrink: 0; border: 2px solid rgba(100, 200, 255, 0.3); box-shadow: 0 8px 32px rgba(100, 200, 255, 0.2);">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info" style="flex: 1;">
                    <h1 class="profile-title" style="font-size: 2.5rem; font-weight: 700; margin-bottom: 0.75rem;">
                        <?= htmlspecialchars($persona['nome']) ?>
                    </h1>

                    <?php if (!empty($badges)): ?>
                        <div class="profile-badges" style="display: flex; gap: 0.75rem; margin-bottom: 1.25rem; flex-wrap: wrap;">
                            <?php foreach ($badges as $badge): ?>
                                <span class="badge-item" style="padding: 0.5rem 1rem; border-radius: 20px; background: rgba(100, 200, 255, 0.15); color: #64c8ff; border: 1px solid rgba(100, 200, 255, 0.3); font-size: 0.85rem; font-weight: 500;">
                                    <i class="fas fa-star"></i> <?= htmlspecialchars($badge) ?>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="profile-meta" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; padding: 1.5rem; background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02)); border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.08);">
                        <?php if ($persona['anno_ingresso']): ?>
                            <div class="meta-item">
                                <span class="meta-label" style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Nel gruppo dal</span>
                                <span class="meta-value" style="font-size: 1rem; color: #ffffff; font-weight: 500;"><?= htmlspecialchars($persona['anno_ingresso']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($persona['citta']): ?>
                            <div class="meta-item">
                                <span class="meta-label" style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Provenienza</span>
                                <span class="meta-value" style="font-size: 1rem; color: #ffffff; font-weight: 500;"><?= htmlspecialchars($persona['citta']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($persona['ruolo']): ?>
                            <div class="meta-item">
                                <span class="meta-label" style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Ruolo</span>
                                <span class="meta-value" style="font-size: 1rem; color: #ffffff; font-weight: 500;"><?= htmlspecialchars($persona['ruolo']) ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($persona['specialita']): ?>
                            <div class="meta-item">
                                <span class="meta-label" style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; display: block; margin-bottom: 0.25rem;">Specialità</span>
                                <span class="meta-value" style="font-size: 1rem; color: #ffffff; font-weight: 500;"><?= htmlspecialchars($persona['specialita']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($persona['intro']): ?>
                <div class="intro-text" style="font-size: 1.15rem; color: rgba(255, 255, 255, 0.8); line-height: 1.8; margin-bottom: 3rem; padding: 1.5rem; background: linear-gradient(135deg, rgba(100, 200, 255, 0.05), transparent); border-left: 3px solid #64c8ff; border-radius: 4px;">
                    <?= nl2br(htmlspecialchars($persona['intro'])) ?>
                </div>
            <?php endif; ?>
        </article>

        <?php if ($persona['storia']): ?>
            <section class="content-section" style="margin-bottom: 3rem;">
                <h2 class="section-title" style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-book-open" style="color: #64c8ff; font-size: 1.5rem;"></i>
                    Storia e Background
                </h2>
                <div class="section-content" style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $persona['storia'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($persona['contributi']): ?>
            <section class="content-section" style="margin-bottom: 3rem;">
                <h2 class="section-title" style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-star" style="color: #64c8ff; font-size: 1.5rem;"></i>
                    Contributi Principali
                </h2>
                <div class="section-content" style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $persona['contributi'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($persona['momenti_memorabili']): ?>
            <section class="content-section" style="margin-bottom: 3rem;">
                <h2 class="section-title" style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-trophy" style="color: #64c8ff; font-size: 1.5rem;"></i>
                    Momenti Memorabili
                </h2>
                <div class="section-content" style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $persona['momenti_memorabili'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($persona['personalita']): ?>
            <section class="content-section" style="margin-bottom: 3rem;">
                <h2 class="section-title" style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-heart" style="color: #64c8ff; font-size: 1.5rem;"></i>
                    Personalità e Caratteristiche
                </h2>
                <div class="section-content" style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $persona['personalita'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($correlati)): ?>
            <section class="related-section" style="margin-top: 3rem; padding-top: 2rem; border-top: 2px solid rgba(255, 255, 255, 0.1);">
                <h3 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 1.5rem;">Contenuti Correlati</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.25rem;">
                    <?php foreach ($correlati as $item): ?>
                        <a href="<?= $item['tipo'] ?>-dettaglio.php?id=<?= htmlspecialchars($item['slug']) ?>" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04)); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 12px; padding: 1.25rem; text-decoration: none; color: inherit; transition: all 0.3s ease; display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 50px; height: 50px; border-radius: 10px; background: linear-gradient(135deg, rgba(100, 200, 255, 0.15), rgba(74, 158, 255, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #64c8ff; flex-shrink: 0;">
                                <i class="fas <?= htmlspecialchars($item['icona']) ?>"></i>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-size: 1rem; font-weight: 600; color: #ffffff; margin-bottom: 0.25rem;">
                                    <?= htmlspecialchars($item['nome']) ?>
                                </div>
                                <div style="font-size: 0.85rem; color: rgba(255, 255, 255, 0.5);">
                                    <?= ucfirst($item['tipo']) ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>

</html>