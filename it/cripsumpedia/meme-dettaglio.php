<?php
// meme-dettaglio.php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$slug = $_GET['id'] ?? '';

if (!$slug) {
    header('Location: meme.php');
    exit;
}

// Recupera dati meme con info creatore
$stmt = $mysqli->prepare("
    SELECT m.*, p.nome as creatore_nome, p.slug as creatore_slug 
    FROM cripsumpedia_meme m 
    LEFT JOIN cripsumpedia_persone p ON m.creatore_id = p.id 
    WHERE m.slug = ? AND m.visibile = 1
");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$meme = $result->fetch_assoc();
$stmt->close();

if (!$meme) {
    header('Location: meme.php');
    exit;
}

$badges = json_decode($meme['badges'], true) ?? [];
$esempi = json_decode($meme['esempi'], true) ?? [];
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($meme['titolo']) ?> - Cripsumpedia™</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style-dark.css">
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="main-content" style="max-width: 1000px; margin: 0 auto; padding: 3rem 2rem 4rem;">
        <nav style="margin-bottom: 2rem; display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; color: rgba(255, 255, 255, 0.5); flex-wrap: wrap;">
            <a href="cripsumpedia.php" style="color: rgba(255, 255, 255, 0.6); text-decoration: none;">Home</a>
            <span>›</span>
            <a href="meme.php" style="color: rgba(255, 255, 255, 0.6); text-decoration: none;">Meme</a>
            <span>›</span>
            <span><?= htmlspecialchars($meme['titolo']) ?></span>
        </nav>

        <header style="margin-bottom: 3rem;">
            <div style="background: linear-gradient(135deg, rgba(255, 215, 100, 0.15), rgba(255, 184, 68, 0.1)); border: 1px solid rgba(255, 215, 100, 0.3); border-radius: 20px; padding: 3rem; margin-bottom: 2rem; text-align: center;">
                <div style="width: 140px; height: 140px; margin: 0 auto 1.5rem; border-radius: 20px; background: linear-gradient(135deg, rgba(255, 215, 100, 0.3), rgba(255, 184, 68, 0.2)); display: flex; align-items: center; justify-content: center; font-size: 5rem; color: #ffd764; box-shadow: 0 8px 32px rgba(255, 215, 100, 0.3);">
                    <i class="fas <?= htmlspecialchars($meme['icona'] ?? 'fa-face-grin-tears') ?>"></i>
                </div>
                <h1 style="font-size: 3rem; font-weight: 700; margin-bottom: 1rem;"><?= htmlspecialchars($meme['titolo']) ?></h1>
                <?php if ($meme['sottotitolo']): ?>
                    <p style="font-size: 1.15rem; color: rgba(255, 255, 255, 0.7); margin-bottom: 1.5rem; font-style: italic;">
                        <?= htmlspecialchars($meme['sottotitolo']) ?>
                    </p>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
                    <?php if ($meme['creatore_nome']): ?>
                        <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.25rem; text-align: center;">
                            <div style="font-size: 1.5rem; color: #ffd764; margin-bottom: 0.5rem;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; margin-bottom: 0.25rem;">Creatore</div>
                            <div style="font-size: 1.05rem; font-weight: 600;"><?= htmlspecialchars($meme['creatore_nome']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($meme['data_nascita']): ?>
                        <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.25rem; text-align: center;">
                            <div style="font-size: 1.5rem; color: #ffd764; margin-bottom: 0.5rem;">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; margin-bottom: 0.25rem;">Data di Nascita</div>
                            <div style="font-size: 1.05rem; font-weight: 600;"><?= date('d/m/Y', strtotime($meme['data_nascita'])) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($meme['categoria']): ?>
                        <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.25rem; text-align: center;">
                            <div style="font-size: 1.5rem; color: #ffd764; margin-bottom: 0.5rem;">
                                <i class="fas fa-hashtag"></i>
                            </div>
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; margin-bottom: 0.25rem;">Categoria</div>
                            <div style="font-size: 1.05rem; font-weight: 600;"><?= htmlspecialchars($meme['categoria']) ?></div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($badges)): ?>
                    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; margin-top: 1.5rem;">
                        <?php foreach ($badges as $badge): ?>
                            <span style="padding: 0.5rem 1.25rem; border-radius: 20px; background: rgba(255, 215, 100, 0.2); color: #ffd764; border: 1px solid rgba(255, 215, 100, 0.4); font-size: 0.9rem; font-weight: 500; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-star"></i> <?= htmlspecialchars($badge) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($meme['citazione_originale']): ?>
            <section style="margin-bottom: 3rem;">
                <div style="border-left: 4px solid #ffd764; padding: 1.5rem 2rem; margin: 2rem 0; background: rgba(255, 215, 100, 0.05); border-radius: 4px; position: relative;">
                    <div style="font-size: 1.5rem; font-weight: 600; color: #ffd764; font-style: italic; line-height: 1.6; margin-bottom: 1rem;">
                        <?= htmlspecialchars($meme['citazione_originale']) ?>
                    </div>
                    <div style="font-size: 1rem; color: rgba(255, 255, 255, 0.6); text-align: right;">
                        — <?= htmlspecialchars($meme['creatore_nome'] ?? 'Gruppo') ?>,
                        <?php if ($meme['data_nascita']): ?>
                            <?= date('F Y', strtotime($meme['data_nascita'])) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($meme['anno'] ?? '') ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($meme['origine']): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-book-open" style="color: #ffd764;"></i>
                    Origine e Storia
                </h2>
                <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $meme['origine'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($meme['evoluzione']): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-chart-line" style="color: #ffd764;"></i>
                    Evoluzione e Diffusione
                </h2>
                <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $meme['evoluzione'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($meme['usi_contesti'] || !empty($esempi)): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-comments" style="color: #ffd764;"></i>
                    Usi e Contesti
                </h2>

                <?php if ($meme['usi_contesti']): ?>
                    <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8; margin-bottom: 1.5rem;">
                        <?= $meme['usi_contesti'] ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($esempi)): ?>
                    <div style="background: linear-gradient(135deg, rgba(255, 215, 100, 0.08), rgba(255, 184, 68, 0.05)); border: 1px solid rgba(255, 215, 100, 0.2); border-radius: 12px; padding: 1.5rem; margin: 1.5rem 0;">
                        <h3 style="color: #ffd764; margin-bottom: 1.5rem; font-size: 1.1rem;">Esempi di Utilizzo</h3>
                        <?php foreach ($esempi as $esempio): ?>
                            <div style="padding: 1rem; margin-bottom: 1rem; background: rgba(0, 0, 0, 0.3); border-radius: 8px; border-left: 3px solid #ffd764;">
                                <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.5); margin-bottom: 0.5rem; font-style: italic;">
                                    <?= htmlspecialchars($esempio['contesto'] ?? '') ?>
                                </div>
                                <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.85);">
                                    <?= htmlspecialchars($esempio['esempio'] ?? '') ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($meme['impatto_culturale']): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-heart" style="color: #ffd764;"></i>
                    Impatto Culturale
                </h2>
                <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $meme['impatto_culturale'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($meme['curiosita']): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-lightbulb" style="color: #ffd764;"></i>
                    Curiosità e Aneddoti
                </h2>
                <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $meme['curiosita'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($meme['utilizzi_estimati']): ?>
            <section style="margin-bottom: 3rem;">
                <div style="background: linear-gradient(135deg, rgba(255, 215, 100, 0.1), rgba(255, 184, 68, 0.05)); border: 1px solid rgba(255, 215, 100, 0.2); border-radius: 12px; padding: 2rem; text-align: center;">
                    <div style="font-size: 3rem; font-weight: 700; color: #ffd764; margin-bottom: 0.5rem;">
                        <?= htmlspecialchars($meme['utilizzi_estimati']) ?>
                    </div>
                    <div style="font-size: 1.1rem; color: rgba(255, 255, 255, 0.7);">
                        Utilizzi Stimati nelle Chat del Gruppo
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>

</html>