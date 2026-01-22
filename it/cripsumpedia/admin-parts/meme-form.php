<?php
// admin-parts/meme-form.php
require_once '../../../config/session_init.php';
require_once '../../../config/database.php';
require_once '../../../includes/functions.php';

$meme = null;
if ($id) {
    $stmt = $mysqli->prepare("SELECT * FROM cripsumpedia_meme WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $meme = $result->fetch_assoc();
    $stmt->close();
}

// Recupera lista persone per il dropdown creatore
$persone_result = $mysqli->query("SELECT id, nome FROM cripsumpedia_persone ORDER BY nome ASC");
?>

<h2><i class="fas fa-face-grin-tears"></i> <?= $id ? 'Modifica' : 'Nuovo' ?> Meme</h2>

<form method="POST" action="">
    <input type="hidden" name="tipo" value="meme">
    <?php if ($id): ?>
        <input type="hidden" name="id" value="<?= $id ?>">
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="form-group">
                <label class="form-label">Titolo Meme *</label>
                <input type="text" name="titolo" class="form-control" required
                    value="<?= htmlspecialchars($meme['titolo'] ?? '') ?>"
                    placeholder='"Godo!"'>
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Slug (URL) *</label>
                <input type="text" name="slug" class="form-control" required
                    value="<?= htmlspecialchars($meme['slug'] ?? '') ?>"
                    placeholder="godo">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Sottotitolo</label>
        <input type="text" name="sottotitolo" class="form-control"
            value="<?= htmlspecialchars($meme['sottotitolo'] ?? '') ?>"
            placeholder="La citazione più iconica del gruppo">
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Creatore</label>
                <select name="creatore_id" class="form-control">
                    <option value="">-- Seleziona --</option>
                    <?php while ($persona = $persone_result->fetch_assoc()): ?>
                        <option value="<?= $persona['id'] ?>" <?= ($meme['creatore_id'] ?? '') == $persona['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($persona['nome']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Data di Nascita</label>
                <input type="date" name="data_nascita" class="form-control"
                    value="<?= htmlspecialchars($meme['data_nascita'] ?? '') ?>">
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Anno</label>
                <input type="text" name="anno" class="form-control"
                    value="<?= htmlspecialchars($meme['anno'] ?? '') ?>"
                    placeholder="2020">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Categoria</label>
                <select name="categoria" class="form-control">
                    <option value="">-- Seleziona --</option>
                    <option value="Citazioni" <?= ($meme['categoria'] ?? '') == 'Citazioni' ? 'selected' : '' ?>>Citazioni</option>
                    <option value="Situazioni" <?= ($meme['categoria'] ?? '') == 'Situazioni' ? 'selected' : '' ?>>Situazioni</option>
                    <option value="Inside Jokes" <?= ($meme['categoria'] ?? '') == 'Inside Jokes' ? 'selected' : '' ?>>Inside Jokes</option>
                    <option value="Leggende" <?= ($meme['categoria'] ?? '') == 'Leggende' ? 'selected' : '' ?>>Leggende</option>
                </select>
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Popolarità</label>
                <select name="popolarita" class="form-control">
                    <option value="">-- Seleziona --</option>
                    <option value="Iconico" <?= ($meme['popolarita'] ?? '') == 'Iconico' ? 'selected' : '' ?>>Iconico</option>
                    <option value="Leggendario" <?= ($meme['popolarita'] ?? '') == 'Leggendario' ? 'selected' : '' ?>>Leggendario</option>
                    <option value="Virale" <?= ($meme['popolarita'] ?? '') == 'Virale' ? 'selected' : '' ?>>Virale</option>
                    <option value="Emergente" <?= ($meme['popolarita'] ?? '') == 'Emergente' ? 'selected' : '' ?>>Emergente</option>
                </select>
            </div>
        </div>

        <div class="col-md-4">
            <div class="form-group">
                <label class="form-label">Icona Font Awesome</label>
                <input type="text" name="icona" class="form-control"
                    value="<?= htmlspecialchars($meme['icona'] ?? 'fa-face-grin-tears') ?>"
                    placeholder="fa-quote-left">
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label class="form-label">Badge (separati da virgola)</label>
                <input type="text" name="badges" class="form-control"
                    value="<?= htmlspecialchars(implode(',', json_decode($meme['badges'] ?? '[]', true))) ?>"
                    placeholder="Iconico,Uso Quotidiano">
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group">
                <label class="form-label">Utilizzi Stimati</label>
                <input type="text" name="utilizzi_estimati" class="form-control"
                    value="<?= htmlspecialchars($meme['utilizzi_estimati'] ?? '') ?>"
                    placeholder="999+">
            </div>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Citazione Originale</label>
        <textarea name="citazione_originale" class="form-control" rows="2"><?= htmlspecialchars($meme['citazione_originale'] ?? '') ?></textarea>
        <small class="help-text">La frase esatta del meme (es: "GODO!")</small>
    </div>

    <div class="form-group">
        <label class="form-label">Descrizione Breve *</label>
        <textarea name="descrizione_breve" class="form-control" required rows="3"><?= htmlspecialchars($meme['descrizione_breve'] ?? '') ?></textarea>
        <small class="help-text">Usata nella griglia dei meme</small>
    </div>

    <div class="form-group">
        <label class="form-label">Origine e Storia</label>
        <textarea name="origine" class="form-control" rows="8"><?= htmlspecialchars($meme['origine'] ?? '') ?></textarea>
        <small class="help-text">Come è nato il meme, supporta HTML</small>
    </div>

    <div class="form-group">
        <label class="form-label">Evoluzione e Diffusione</label>
        <textarea name="evoluzione" class="form-control" rows="8"><?= htmlspecialchars($meme['evoluzione'] ?? '') ?></textarea>
        <small class="help-text">Come si è evoluto nel tempo</small>
    </div>

    <div class="form-group">
        <label class="form-label">Usi e Contesti</label>
        <textarea name="usi_contesti" class="form-control" rows="8"><?= htmlspecialchars($meme['usi_contesti'] ?? '') ?></textarea>
        <small class="help-text">In quali situazioni viene usato</small>
    </div>

    <div class="form-group">
        <label class="form-label">Impatto Culturale</label>
        <textarea name="impatto_culturale" class="form-control" rows="8"><?= htmlspecialchars($meme['impatto_culturale'] ?? '') ?></textarea>
        <small class="help-text">Che impatto ha avuto sul gruppo</small>
    </div>

    <div class="form-group">
        <label class="form-label">Curiosità</label>
        <textarea name="curiosita" class="form-control" rows="6"><?= htmlspecialchars($meme['curiosita'] ?? '') ?></textarea>
        <small class="help-text">Aneddoti e fatti interessanti</small>
    </div>

    <div class="form-group">
        <label class="form-label">Visibile</label>
        <select name="visibile" class="form-control">
            <option value="1" <?= ($meme['visibile'] ?? 1) == 1 ? 'selected' : '' ?>>Sì</option>
            <option value="0" <?= ($meme['visibile'] ?? 1) == 0 ? 'selected' : '' ?>>No</option>
        </select>
    </div>

    <div style="margin-top: 2rem;">
        <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i> Salva Meme
        </button>
        <a href="?sezione=meme" class="btn-primary" style="margin-left: 1rem; text-decoration: none;">
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