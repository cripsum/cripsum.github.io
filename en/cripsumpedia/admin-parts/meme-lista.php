<?php
// admin-parts/meme-lista.php
require_once '../../../config/session_init.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';
$result = $mysqli->query("SELECT m.*, p.nome as creatore_nome FROM cripsumpedia_meme m LEFT JOIN cripsumpedia_persone p ON m.creatore_id = p.id ORDER BY m.anno DESC, m.titolo ASC");
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h2><i class="fas fa-face-grin-tears"></i> Gestione Meme</h2>
    <a href="?sezione=meme&azione=nuovo" class="btn-primary" style="text-decoration: none;">
        <i class="fas fa-plus"></i> Aggiungi Meme
    </a>
</div>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Titolo</th>
                <th>Creatore</th>
                <th>Categoria</th>
                <th>Popolarità</th>
                <th>Anno</th>
                <th>Visibile</th>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.5);">
                        <i class="fas fa-face-meh" style="font-size: 3rem; margin-bottom: 1rem;"></i><br>
                        Nessun meme ancora. Clicca "Aggiungi Meme" per iniziare!
                    </td>
                </tr>
            <?php endif; ?>

            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($row['titolo']) ?></strong><br>
                        <small style="color: rgba(255,255,255,0.5);"><?= htmlspecialchars($row['slug']) ?></small>
                    </td>
                    <td><?= htmlspecialchars($row['creatore_nome'] ?? '-') ?></td>
                    <td>
                        <?php if ($row['categoria']): ?>
                            <span style="background: rgba(255,215,100,0.2); padding: 0.25rem 0.75rem; border-radius: 12px; color: #ffd764; font-size: 0.85rem;">
                                <?= htmlspecialchars($row['categoria']) ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($row['popolarita']): ?>
                            <?php
                            $color = '#ffd764';
                            $icon = 'fa-star';
                            if ($row['popolarita'] === 'Iconico') {
                                $color = '#ff64c8';
                                $icon = 'fa-fire';
                            } elseif ($row['popolarita'] === 'Leggendario') {
                                $color = '#64c8ff';
                                $icon = 'fa-crown';
                            }
                            ?>
                            <span style="color: <?= $color ?>;">
                                <i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($row['popolarita']) ?>
                            </span>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['anno'] ?? '-') ?></td>
                    <td>
                        <?php if ($row['visibile']): ?>
                            <span style="color: #64ff64;"><i class="fas fa-check-circle"></i> Sì</span>
                        <?php else: ?>
                            <span style="color: #ff6464;"><i class="fas fa-times-circle"></i> No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?sezione=meme&azione=modifica&id=<?= $row['id'] ?>" class="btn-small btn-edit">
                            <i class="fas fa-edit"></i> Modifica
                        </a>
                        <a href="meme-dettaglio.php?id=<?= $row['slug'] ?>" class="btn-small btn-edit" target="_blank">
                            <i class="fas fa-eye"></i> Vedi
                        </a>
                        <a href="javascript:void(0)" onclick="eliminaMeme(<?= $row['id'] ?>)" class="btn-small btn-delete">
                            <i class="fas fa-trash"></i> Elimina
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    function eliminaMeme(id) {
        if (confirm('Sei sicuro di voler eliminare questo meme? Questa azione non può essere annullata.')) {
            fetch('api-delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        tipo: 'meme',
                        id: id
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert('Errore durante l\'eliminazione: ' + data.message);
                    }
                });
        }
    }
</script>