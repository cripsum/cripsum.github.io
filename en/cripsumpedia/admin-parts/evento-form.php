<?php
// admin-parts/evento-form.php
require_once '../../../config/session_init.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

$evento = null;
if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_eventi WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $evento = $result->fetch_assoc();
    $stmt->close();
}

$stats = $evento ? json_decode($evento['stats'], true) : [];
?>

<h2><i class="fas fa-calendar-star"></i> <?= $id ? 'Modifica' : 'Nuovo' ?> Evento</h2>

<form method="POST" action="">
    <input type="hidden" name="tipo" value="evento">
    <?php if ($id): ?>
        <input type="hidden" name="id" value="<?= $id ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="form-group">
                <label class="form-label">Titolo Evento *</label>
                <input type="text" name="titolo" class="form-control" required
                    value="<?= htmlspecialchars($evento['titolo'] ?? '') ?>">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Slug (URL) *</label>
                <input type="text" name="slug" class="form-control" required
                    value="<?= htmlspecialchars($evento['slug'] ?? '') ?>"
                    placeholder="capodanno-2024">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Sottotitolo</label>
        <input type="text" name="sottotitolo" class="form-control"
            value="<?= htmlspecialchars($evento['sottotitolo'] ?? '') ?>"
            placeholder="La notte più epica del gruppo">
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Data Inizio</label>
                <input type="date" name="data_inizio" class="form-control"
                    value="<?= htmlspecialchars($evento['data_inizio'] ?? '') ?>">
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Data Fine</label>
                <input type="date" name="data_fine" class="form-control"
                    value="<?= htmlspecialchars($evento['data_fine'] ?? '') ?>">
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Anno</label>
                <input type="text" name="anno" class="form-control"
                    value="<?= htmlspecialchars($evento['anno'] ?? '') ?>"
                    placeholder="2024">
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">N° Partecipanti</label>
                <input type="number" name="num_partecipanti" class="form-control"
                    value="<?= htmlspecialchars($evento['num_partecipanti'] ?? '') ?>">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-label">Luogo</label>
                <input type="text" name="luogo" class="form-control"
                    value="<?= htmlspecialchars($evento['luogo'] ?? '') ?>"
                    placeholder="Villa sul Lago di Como">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="form-label">Icona Font Awesome</label>
                <input type="text" name="icona" class="form-control"
                    value="<?= htmlspecialchars($evento['icona'] ?? 'fa-calendar-star') ?>"
                    placeholder="fa-champagne-glasses">
                <small class="help-text">Es: fa-champagne-glasses, fa-gamepad, fa-mountain</small>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Tags (separati da virgola)</label>
        <input type="text" name="tags" class="form-control"
            value="<?= htmlspecialchars(implode(',', json_decode($evento['tags'] ?? '[]', true))) ?>"
            placeholder="Epico,Festa,Memorabile">
    </div>

    <div class="form-group">
        <label class="form-label">Descrizione Breve *</label>
        <textarea name="descrizione_breve" class="form-control" required rows="3"><?= htmlspecialchars($evento['descrizione_breve'] ?? '') ?></textarea>
        <small class="help-text">Usata nella timeline degli eventi</small>
    </div>

    <div class="form-group">
        <label class="form-label">Panoramica</label>
        <textarea name="panoramica" class="form-control" rows="6"><?= htmlspecialchars($evento['panoramica'] ?? '') ?></textarea>
        <small class="help-text">Introduzione dettagliata dell'evento</small>
    </div>

    <h3 style="margin-top: 2rem; margin-bottom: 1rem; color: #ff64c8;">
        <i class="fas fa-chart-bar"></i> Statistiche Evento
    </h3>

    <div class="row">
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Ore di Festa</label>
                <input type="text" name="stat_ore" class="form-control"
                    value="<?= htmlspecialchars($stats['ore'] ?? '') ?>"
                    placeholder="14">
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Meme Generati</label>
                <input type="text" name="stat_meme" class="form-control"
                    value="<?= htmlspecialchars($stats['meme'] ?? '') ?>"
                    placeholder="3">
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Ricordi</label>
                <input type="text" name="stat_ricordi" class="form-control"
                    value="<?= htmlspecialchars($stats['ricordi'] ?? '') ?>"
                    placeholder="∞">
            </div>
        </div>

        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">Divertimento %</label>
                <input type="text" name="stat_divertimento" class="form-control"
                    value="<?= htmlspecialchars($stats['divertimento'] ?? '') ?>"
                    placeholder="100%">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Momenti Iconici</label>
        <textarea name="momenti_iconici" class="form-control" rows="8"><?= htmlspecialchars($evento['momenti_iconici'] ?? '') ?></textarea>
        <small class="help-text">Supporta HTML: &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;</small>
    </div>

    <div class="form-group">
        <label class="form-label">Impatto e Legacy</label>
        <textarea name="impatto" class="form-control" rows="8"><?= htmlspecialchars($evento['impatto'] ?? '') ?></textarea>
        <small class="help-text">Supporta HTML</small>
    </div>

    <div class="form-group">
        <label class="form-label">Curiosità e Aneddoti</label>
        <textarea name="curiosita" class="form-control" rows="8"><?= htmlspecialchars($evento['curiosita'] ?? '') ?></textarea>
        <small class="help-text">Supporta HTML</small>
    </div>

    <div class="form-group">
        <label class="form-label">Visibile</label>
        <select name="visibile" class="form-control">
            <option value="1" <?= ($evento['visibile'] ?? 1) == 1 ? 'selected' : '' ?>>Sì</option>
            <option value="0" <?= ($evento['visibile'] ?? 1) == 0 ? 'selected' : '' ?>>No</option>
        </select>
    </div>

    <div style="margin-top: 2rem;">
        <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i> Salva Evento
        </button>
        <a href="?sezione=eventi" class="btn-primary" style="margin-left: 1rem; text-decoration: none;">
            <i class="fas fa-times"></i> Annulla
        </a>
    </div>
</form>

<script>
    // Auto-genera slug dal titolo
    document.querySelector('input[name="titolo"]').addEventListener('input', function(e) {
        const slugInput = document.querySelector('input[name="slug"]');
        if (!slugInput.value || slugInput.dataset.auto !== 'false') {
            const slug = e.target.value
                .toLowerCase()
                .replace(/[àáâãäå]/g, 'a')
                .replace(/[èéêë]/g, 'e')
                .replace(/[ìíîï]/g, 'i')
                .replace(/[òóôõö]/g, 'o')
                .replace(/[ùúûü]/g, 'u')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-|-$/g, '');
            slugInput.value = slug;
            slugInput.dataset.auto = 'true';
        }
    });

    document.querySelector('input[name="slug"]').addEventListener('input', function() {
        this.dataset.auto = 'false';
    });
</script>