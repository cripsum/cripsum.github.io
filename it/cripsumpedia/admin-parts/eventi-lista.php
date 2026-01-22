<?php
// admin-parts/eventi-lista.php
require_once '../../../config/session_init.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

$result = $mysqli->query("SELECT * FROM cripsumpedia_eventi ORDER BY anno DESC, data_inizio DESC");
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
    <h2><i class="fas fa-calendar-star"></i> Gestione Eventi</h2>
    <a href="?sezione=eventi&azione=nuovo" class="btn-primary" style="text-decoration: none;">
        <i class="fas fa-plus"></i> Aggiungi Evento
    </a>
</div>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th>Titolo</th>
                <th>Data</th>
                <th>Luogo</th>
                <th>Partecipanti</th>
                <th>Anno</th>
                <th>Visibile</th>
                <th>Azioni</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows === 0): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem; color: rgba(255,255,255,0.5);">
                        <i class="fas fa-calendar-xmark" style="font-size: 3rem; margin-bottom: 1rem;"></i><br>
                        Nessun evento ancora. Clicca "Aggiungi Evento" per iniziare!
                    </td>
                </tr>
            <?php endif; ?>

            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($row['titolo']) ?></strong><br>
                        <small style="color: rgba(255,255,255,0.5);"><?= htmlspecialchars($row['slug']) ?></small>
                    </td>
                    <td>
                        <?php if ($row['data_inizio']): ?>
                            <?= date('d/m/Y', strtotime($row['data_inizio'])) ?>
                            <?php if ($row['data_fine'] && $row['data_fine'] != $row['data_inizio']): ?>
                                <br><small style="color: rgba(255,255,255,0.5);">→ <?= date('d/m/Y', strtotime($row['data_fine'])) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($row['luogo'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($row['num_partecipanti'] ?? '-') ?></td>
                    <td>
                        <span style="background: rgba(255,100,200,0.2); padding: 0.25rem 0.75rem; border-radius: 12px; color: #ff64c8; font-weight: 600;">
                            <?= htmlspecialchars($row['anno'] ?? '-') ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($row['visibile']): ?>
                            <span style="color: #64ff64;"><i class="fas fa-check-circle"></i> Sì</span>
                        <?php else: ?>
                            <span style="color: #ff6464;"><i class="fas fa-times-circle"></i> No</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?sezione=eventi&azione=modifica&id=<?= $row['id'] ?>" class="btn-small btn-edit">
                            <i class="fas fa-edit"></i> Modifica
                        </a>
                        <a href="evento-dettaglio.php?id=<?= $row['slug'] ?>" class="btn-small btn-edit" target="_blank">
                            <i class="fas fa-eye"></i> Vedi
                        </a>
                        <a href="javascript:void(0)" onclick="eliminaEvento(<?= $row['id'] ?>)" class="btn-small btn-delete">
                            <i class="fas fa-trash"></i> Elimina
                        </a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<script>
    function eliminaEvento(id) {
        if (confirm('Sei sicuro di voler eliminare questo evento? Questa azione non può essere annullata.')) {
            fetch('api-delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        tipo: 'evento',
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