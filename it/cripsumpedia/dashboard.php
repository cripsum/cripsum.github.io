<?php
// admin-parts/dashboard.php

// Conta elementi
$count_persone = $mysqli->query("SELECT COUNT(*) as count FROM cripsumpedia_persone")->fetch_assoc()['count'];
$count_eventi = $mysqli->query("SELECT COUNT(*) as count FROM cripsumpedia_eventi")->fetch_assoc()['count'];
$count_meme = $mysqli->query("SELECT COUNT(*) as count FROM cripsumpedia_meme")->fetch_assoc()['count'];

// Ultimi elementi aggiunti
$ultime_persone = $mysqli->query("SELECT id, nome, data_creazione FROM cripsumpedia_persone ORDER BY data_creazione DESC LIMIT 5");
$ultimi_eventi = $mysqli->query("SELECT id, titolo, data_creazione FROM cripsumpedia_eventi ORDER BY data_creazione DESC LIMIT 5");
$ultimi_meme = $mysqli->query("SELECT id, titolo, data_creazione FROM cripsumpedia_meme ORDER BY data_creazione DESC LIMIT 5");
?>

<h2><i class="fas fa-home"></i> Dashboard Cripsumpedia</h2>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0;">
    <!-- Card Persone -->
    <div style="background: linear-gradient(135deg, rgba(100, 200, 255, 0.15), rgba(74, 158, 255, 0.1)); border: 1px solid rgba(100, 200, 255, 0.3); border-radius: 16px; padding: 2rem; text-align: center;">
        <i class="fas fa-users" style="font-size: 3rem; color: #64c8ff; margin-bottom: 1rem;"></i>
        <h3 style="font-size: 2.5rem; font-weight: 700; color: #64c8ff; margin-bottom: 0.5rem;"><?= $count_persone ?></h3>
        <p style="color: rgba(255,255,255,0.7); margin-bottom: 1rem;">Persone</p>
        <a href="?sezione=persone" style="color: #64c8ff; text-decoration: none; font-weight: 500;">
            Gestisci <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <!-- Card Eventi -->
    <div style="background: linear-gradient(135deg, rgba(255, 100, 200, 0.15), rgba(255, 74, 169, 0.1)); border: 1px solid rgba(255, 100, 200, 0.3); border-radius: 16px; padding: 2rem; text-align: center;">
        <i class="fas fa-calendar-star" style="font-size: 3rem; color: #ff64c8; margin-bottom: 1rem;"></i>
        <h3 style="font-size: 2.5rem; font-weight: 700; color: #ff64c8; margin-bottom: 0.5rem;"><?= $count_eventi ?></h3>
        <p style="color: rgba(255,255,255,0.7); margin-bottom: 1rem;">Eventi</p>
        <a href="?sezione=eventi" style="color: #ff64c8; text-decoration: none; font-weight: 500;">
            Gestisci <i class="fas fa-arrow-right"></i>
        </a>
    </div>

    <!-- Card Meme -->
    <div style="background: linear-gradient(135deg, rgba(255, 215, 100, 0.15), rgba(255, 184, 68, 0.1)); border: 1px solid rgba(255, 215, 100, 0.3); border-radius: 16px; padding: 2rem; text-align: center;">
        <i class="fas fa-face-grin-tears" style="font-size: 3rem; color: #ffd764; margin-bottom: 1rem;"></i>
        <h3 style="font-size: 2.5rem; font-weight: 700; color: #ffd764; margin-bottom: 0.5rem;"><?= $count_meme ?></h3>
        <p style="color: rgba(255,255,255,0.7); margin-bottom: 1rem;">Meme</p>
        <a href="?sezione=meme" style="color: #ffd764; text-decoration: none; font-weight: 500;">
            Gestisci <i class="fas fa-arrow-right"></i>
        </a>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; margin-top: 3rem;">
    <!-- Ultime Persone -->
    <div>
        <h3 style="margin-bottom: 1rem; color: #64c8ff;">
            <i class="fas fa-users"></i> Ultime Persone Aggiunte
        </h3>
        <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem;">
            <?php if ($ultime_persone->num_rows === 0): ?>
                <p style="color: rgba(255,255,255,0.5); text-align: center; padding: 1rem;">Nessuna persona ancora</p>
            <?php else: ?>
                <?php while ($persona = $ultime_persone->fetch_assoc()): ?>
                    <div style="padding: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?= htmlspecialchars($persona['nome']) ?></strong><br>
                            <small style="color: rgba(255,255,255,0.5);">
                                <?= date('d/m/Y H:i', strtotime($persona['data_creazione'])) ?>
                            </small>
                        </div>
                        <a href="?sezione=persone&azione=modifica&id=<?= $persona['id'] ?>" style="color: #64c8ff; text-decoration: none;">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ultimi Eventi -->
    <div>
        <h3 style="margin-bottom: 1rem; color: #ff64c8;">
            <i class="fas fa-calendar-star"></i> Ultimi Eventi Aggiunti
        </h3>
        <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem;">
            <?php if ($ultimi_eventi->num_rows === 0): ?>
                <p style="color: rgba(255,255,255,0.5); text-align: center; padding: 1rem;">Nessun evento ancora</p>
            <?php else: ?>
                <?php while ($evento = $ultimi_eventi->fetch_assoc()): ?>
                    <div style="padding: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?= htmlspecialchars($evento['titolo']) ?></strong><br>
                            <small style="color: rgba(255,255,255,0.5);">
                                <?= date('d/m/Y H:i', strtotime($evento['data_creazione'])) ?>
                            </small>
                        </div>
                        <a href="?sezione=eventi&azione=modifica&id=<?= $evento['id'] ?>" style="color: #ff64c8; text-decoration: none;">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Ultimi Meme -->
    <div>
        <h3 style="margin-bottom: 1rem; color: #ffd764;">
            <i class="fas fa-face-grin-tears"></i> Ultimi Meme Aggiunti
        </h3>
        <div style="background: rgba(255,255,255,0.03); border-radius: 12px; padding: 1rem;">
            <?php if ($ultimi_meme->num_rows === 0): ?>
                <p style="color: rgba(255,255,255,0.5); text-align: center; padding: 1rem;">Nessun meme ancora</p>
            <?php else: ?>
                <?php while ($meme = $ultimi_meme->fetch_assoc()): ?>
                    <div style="padding: 0.75rem; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?= htmlspecialchars($meme['titolo']) ?></strong><br>
                            <small style="color: rgba(255,255,255,0.5);">
                                <?= date('d/m/Y H:i', strtotime($meme['data_creazione'])) ?>
                            </small>
                        </div>
                        <a href="?sezione=meme&azione=modifica&id=<?= $meme['id'] ?>" style="color: #ffd764; text-decoration: none;">
                            <i class="fas fa-edit"></i>
                        </a>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="margin-top: 3rem; padding: 2rem; background: linear-gradient(135deg, rgba(255,255,255,0.05), rgba(255,255,255,0.02)); border-radius: 16px; border: 1px solid rgba(255,255,255,0.08);">
    <h3 style="margin-bottom: 1rem;"><i class="fas fa-info-circle"></i> Guida Rapida</h3>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
        <div>
            <h4 style="color: #64c8ff; margin-bottom: 0.5rem;">
                <i class="fas fa-users"></i> Persone
            </h4>
            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; line-height: 1.6;">
                Aggiungi membri del gruppo con biografia, contributi e momenti memorabili. Ogni persona può essere collegata a eventi e meme.
            </p>
        </div>
        <div>
            <h4 style="color: #ff64c8; margin-bottom: 0.5rem;">
                <i class="fas fa-calendar-star"></i> Eventi
            </h4>
            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; line-height: 1.6;">
                Documenta gli eventi più importanti del gruppo con date, luoghi, partecipanti e cronologia dettagliata.
            </p>
        </div>
        <div>
            <h4 style="color: #ffd764; margin-bottom: 0.5rem;">
                <i class="fas fa-face-grin-tears"></i> Meme
            </h4>
            <p style="color: rgba(255,255,255,0.7); font-size: 0.9rem; line-height: 1.6;">
                Archivia i meme e le citazioni iconiche con origine, evoluzione e impatto culturale sul gruppo.
            </p>
        </div>
    </div>
</div>