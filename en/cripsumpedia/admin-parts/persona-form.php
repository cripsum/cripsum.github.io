<?php
// admin-parts/persona-form.php
require_once '../../../config/session_init.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

$persona = null;
if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_persone WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $persona = $result->fetch_assoc();
    $stmt->close();
}
?>

<h2><i class="fas fa-user"></i> <?= $id ? 'Modifica' : 'Nuova' ?> Persona</h2>

<form method="POST" action="">
    <input type="hidden" name="tipo" value="persona">
    <?php if ($id): ?>
        <input type="hidden" name="id" value="<?= $id ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-label">Nome Completo *</label>
                <input type="text" name="nome" class="form-control" required
                    value="<?= htmlspecialchars($persona['nome'] ?? '') ?>">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="form-label">Slug (URL) *</label>
                <input type="text" name="slug" class="form-control" required
                    value="<?= htmlspecialchars($persona['slug'] ?? '') ?>"
                    placeholder="mario-rossi">
                <small class="help-text">Usato nell'URL: persona-dettaglio.php?id=mario-rossi</small>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Ruolo</label>
                <input type="text" name="ruolo" class="form-control"
                    value="<?= htmlspecialchars($persona['ruolo'] ?? '') ?>"
                    placeholder="Co-Fondatore">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Anno Ingresso</label>
                <input type="text" name="anno_ingresso" class="form-control"
                    value="<?= htmlspecialchars($persona['anno_ingresso'] ?? '') ?>"
                    placeholder="2020">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Città</label>
                <input type="text" name="citta" class="form-control"
                    value="<?= htmlspecialchars($persona['citta'] ?? '') ?>"
                    placeholder="Milano">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-label">Specialità</label>
                <input type="text" name="specialita" class="form-control"
                    value="<?= htmlspecialchars($persona['specialita'] ?? '') ?>"
                    placeholder="Organizzazione Eventi">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="form-label">Badge (separati da virgola)</label>
                <input type="text" name="badges" class="form-control"
                    value="<?= htmlspecialchars(implode(',', json_decode($persona['badges'] ?? '[]', true))) ?>"
                    placeholder="Fondatore,Membro Storico">
                <small class="help-text">Es: Fondatore,Membro Storico,Creatore di Meme</small>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Descrizione Breve *</label>
        <textarea name="descrizione_breve" class="form-control" required rows="3"><?= htmlspecialchars($persona['descrizione_breve'] ?? '') ?></textarea>
        <small class="help-text">Usata nella lista e nel preview</small>
    </div>

    <div class="form-group">
        <label class="form-label">Introduzione</label>
        <textarea name="intro" class="form-control" rows="4"><?= htmlspecialchars($persona['intro'] ?? '') ?></textarea>
        <small class="help-text">Testo introduttivo nella pagina dettaglio</small>
    </div>

    <div class="form-group">
        <label class="form-label">Storia e Background</label>
        <textarea name="storia" class="form-control" rows="8"><?= htmlspecialchars($persona['storia'] ?? '') ?></textarea>
        <small class="help-text">Supporta HTML: &lt;h3&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;</small>
    </div>

    <div class="form-group">
        <label class="form-label">Contributi Principali</label>
        <textarea name="contributi" class="form-control" rows="8"><?= htmlspecialchars($persona['contributi'] ?? '') ?></textarea>
        <small class="help-text">Supporta HTML</small>
    </div>

    <div class="form-group">
        <label class="form-label">Momenti Memorabili</label>
        <textarea name="momenti_memorabili" class="form-control" rows="8"><?= htmlspecialchars($persona['momenti_memorabili'] ?? '') ?></textarea>
        <small class="help-text">Supporta HTML</small>
    </div>

    <div class="form-group">
        <label class="form-label">Personalità e Caratteristiche</label>
        <textarea name="personalita" class="form-control" rows="8"><?= htmlspecialchars($persona['personalita'] ?? '') ?></textarea>
        <small class="help-text">Supporta HTML</small>
    </div>

    <div class="form-group">
        <label class="form-label">Visibile</label>
        <select name="visibile" class="form-control">
            <option value="1" <?= ($persona['visibile'] ?? 1) == 1 ? 'selected' : '' ?>>Sì</option>
            <option value="0" <?= ($persona['visibile'] ?? 1) == 0 ? 'selected' : '' ?>>No</option>
        </select>
    </div>

    <div style="margin-top: 2rem;">
        <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i> Salva Persona
        </button>
        <a href="?sezione=persone" class="btn-primary" style="margin-left: 1rem; text-decoration: none;">
            <i class="fas fa-times"></i> Annulla
        </a>
    </div>
</form>

<script>
    // Auto-genera slug dal nome
    document.querySelector('input[name="nome"]').addEventListener('input', function(e) {
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