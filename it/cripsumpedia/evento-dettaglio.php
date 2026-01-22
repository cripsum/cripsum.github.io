<?php
// evento-dettaglio.php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$slug = $_GET['id'] ?? '';

if (!$slug) {
    header('Location: eventi.php');
    exit;
}

// Recupera dati evento
$stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_eventi WHERE slug = ? AND visibile = 1");
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$evento = $result->fetch_assoc();
$stmt->close();

if (!$evento) {
    header('Location: eventi.php');
    exit;
}

$tags = json_decode($evento['tags'], true) ?? [];
$stats = json_decode($evento['stats'], true) ?? [];
$cronologia = json_decode($evento['cronologia'], true) ?? [];

// Recupera partecipanti
$stmt = $mysqli->prepare("
    SELECT p.id, p.nome, p.slug 
    FROM cripsumpedia_partecipanti_eventi pe
    JOIN cripsumpedia_persone p ON pe.persona_id = p.id
    WHERE pe.evento_id = ?
    LIMIT 20
");
$stmt->bind_param("i", $evento['id']);
$stmt->execute();
$partecipanti = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="it">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($evento['titolo']) ?> - Cripsumpedia™</title>
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
            <a href="eventi.php" style="color: rgba(255, 255, 255, 0.6); text-decoration: none;">Eventi</a>
            <span>›</span>
            <span><?= htmlspecialchars($evento['titolo']) ?></span>
        </nav>

        <header style="margin-bottom: 3rem;">
            <div style="background: linear-gradient(135deg, rgba(255, 100, 200, 0.15), rgba(255, 74, 169, 0.1)); border: 1px solid rgba(255, 100, 200, 0.3); border-radius: 20px; padding: 3rem; margin-bottom: 2rem; text-align: center;">
                <div style="width: 120px; height: 120px; margin: 0 auto 1.5rem; border-radius: 20px; background: linear-gradient(135deg, rgba(255, 100, 200, 0.3), rgba(255, 74, 169, 0.2)); display: flex; align-items: center; justify-content: center; font-size: 4rem; color: #ff64c8; box-shadow: 0 8px 32px rgba(255, 100, 200, 0.3);">
                    <i class="fas <?= htmlspecialchars($evento['icona'] ?? 'fa-calendar-star') ?>"></i>
                </div>
                <h1 style="font-size: 2.5rem; font-weight: 700; margin-bottom: 1rem;"><?= htmlspecialchars($evento['titolo']) ?></h1>
                <?php if ($evento['sottotitolo']): ?>
                    <p style="font-size: 1.15rem; color: rgba(255, 255, 255, 0.7); margin-bottom: 1.5rem;">
                        <?= htmlspecialchars($evento['sottotitolo']) ?>
                    </p>
                <?php endif; ?>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-top: 2rem;">
                    <?php if ($evento['data_inizio']): ?>
                        <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.25rem; text-align: center;">
                            <div style="font-size: 1.5rem; color: #ff64c8; margin-bottom: 0.5rem;">
                                <i class="fas fa-calendar"></i>
                            </div>
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; margin-bottom: 0.25rem;">Data</div>
                            <div style="font-size: 1.05rem; font-weight: 600;">
                                <?= date('d/m/Y', strtotime($evento['data_inizio'])) ?>
                                <?php if ($evento['data_fine'] && $evento['data_fine'] != $evento['data_inizio']): ?>
                                    - <?= date('d/m/Y', strtotime($evento['data_fine'])) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($evento['luogo']): ?>
                        <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.25rem; text-align: center;">
                            <div style="font-size: 1.5rem; color: #ff64c8; margin-bottom: 0.5rem;">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; margin-bottom: 0.25rem;">Luogo</div>
                            <div style="font-size: 1.05rem; font-weight: 600;"><?= htmlspecialchars($evento['luogo']) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($evento['num_partecipanti']): ?>
                        <div style="background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 1.25rem; text-align: center;">
                            <div style="font-size: 1.5rem; color: #ff64c8; margin-bottom: 0.5rem;">
                                <i class="fas fa-users"></i>
                            </div>
                            <div style="font-size: 0.8rem; color: rgba(255, 255, 255, 0.5); text-transform: uppercase; margin-bottom: 0.25rem;">Partecipanti</div>
                            <div style="font-size: 1.05rem; font-weight: 600;"><?= htmlspecialchars($evento['num_partecipanti']) ?> Persone</div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($tags)): ?>
                    <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap; margin-top: 1.5rem;">
                        <?php foreach ($tags as $tag): ?>
                            <span style="padding: 0.5rem 1.25rem; border-radius: 20px; background: rgba(255, 100, 200, 0.2); color: #ff64c8; border: 1px solid rgba(255, 100, 200, 0.4); font-size: 0.9rem; font-weight: 500;">
                                <?= htmlspecialchars($tag) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($evento['panoramica']): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-book-open" style="color: #ff64c8;"></i>
                    Panoramica
                </h2>
                <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= nl2br(htmlspecialchars($evento['panoramica'])) ?>
                </div>

                <?php if (!empty($stats)): ?>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
                        <?php foreach ($stats as $key => $value): ?>
                            <?php if ($value): ?>
                                <div style="background: linear-gradient(135deg, rgba(255, 100, 200, 0.1), rgba(255, 74, 169, 0.05)); border: 1px solid rgba(255, 100, 200, 0.2); border-radius: 12px; padding: 1.5rem; text-align: center;">
                                    <div style="font-size: 2.5rem; font-weight: 700; color: #ff64c8; margin-bottom: 0.5rem;"><?= htmlspecialchars($value) ?></div>
                                    <div style="font-size: 0.9rem; color: rgba(255, 255, 255, 0.7);"><?= ucfirst(str_replace('_', ' ', $key)) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if ($evento['momenti_iconici']): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-star" style="color: #ff64c8;"></i>
                    Momenti Iconici
                </h2>
                <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $evento['momenti_iconici'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (!empty($partecipanti)): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-users" style="color: #ff64c8;"></i>
                    Partecipanti
                </h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 1rem;">
                    <?php foreach ($partecipanti as $p): ?>
                        <a href="persona-dettaglio.php?id=<?= htmlspecialchars($p['slug']) ?>" style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04)); border: 1px solid rgba(255, 255, 255, 0.12); border-radius: 12px; padding: 1rem; text-align: center; transition: all 0.3s ease; text-decoration: none; color: inherit; display: block;">
                            <div style="width: 60px; height: 60px; margin: 0 auto 0.75rem; border-radius: 12px; background: linear-gradient(135deg, rgba(255, 100, 200, 0.2), rgba(255, 74, 169, 0.1)); display: flex; align-items: center; justify-content: center; font-size: 1.75rem; color: #ff64c8;">
                                <i class="fas fa-user"></i>
                            </div>
                            <div style="font-size: 0.95rem; font-weight: 500;"><?= htmlspecialchars($p['nome']) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($evento['impatto']): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-heart" style="color: #ff64c8;"></i>
                    Impatto e Legacy
                </h2>
                <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $evento['impatto'] ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($evento['curiosita']): ?>
            <section style="margin-bottom: 3rem;">
                <h2 style="font-size: 1.75rem; font-weight: 600; margin-bottom: 1.5rem; color: #ffffff; display: flex; align-items: center; gap: 0.75rem; padding-bottom: 0.75rem; border-bottom: 2px solid rgba(255, 255, 255, 0.1);">
                    <i class="fas fa-lightbulb" style="color: #ff64c8;"></i>
                    Curiosità e Aneddoti
                </h2>
                <div style="font-size: 1.05rem; color: rgba(255, 255, 255, 0.75); line-height: 1.8;">
                    <?= $evento['curiosita'] ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <?php include '../../includes/footer.php'; ?>
</body>

</html>