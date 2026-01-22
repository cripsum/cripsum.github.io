<?php
require_once '../../config/session_init.php';
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// IMPORTANTE: Aggiungi controllo amministratore
// checkAdmin($mysqli); // Decommentare quando hai il sistema di ruoli

$sezione = $_GET['sezione'] ?? 'dashboard';
$azione = $_GET['azione'] ?? 'lista';
$id = $_GET['id'] ?? null;

// Gestione form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? '';

    if ($tipo === 'persona') {
        salvaPersona($mysqli, $_POST);
    } elseif ($tipo === 'evento') {
        salvaEvento($mysqli, $_POST);
    } elseif ($tipo === 'meme') {
        salvaMeme($mysqli, $_POST);
    }

    header("Location: cripsumpedia-admin.php?sezione={$sezione}&msg=success");
    exit;
}

function salvaPersona($mysqli, $data)
{
    $id = $data['id'] ?? null;
    $badges = json_encode(explode(',', $data['badges'] ?? ''));

    if ($id) {
        // Update
        $stmt = $mysqli->prepare("UPDATE cripsumpedia_persone SET slug=?, nome=?, ruolo=?, badges=?, anno_ingresso=?, citta=?, specialita=?, descrizione_breve=?, intro=?, storia=?, contributi=?, momenti_memorabili=?, personalita=?, visibile=? WHERE id=?");
        $stmt->bind_param(
            "sssssssssssssii",
            $data['slug'],
            $data['nome'],
            $data['ruolo'],
            $badges,
            $data['anno_ingresso'],
            $data['citta'],
            $data['specialita'],
            $data['descrizione_breve'],
            $data['intro'],
            $data['storia'],
            $data['contributi'],
            $data['momenti_memorabili'],
            $data['personalita'],
            $data['visibile'],
            $id
        );
    } else {
        // Insert
        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_persone (slug, nome, ruolo, badges, anno_ingresso, citta, specialita, descrizione_breve, intro, storia, contributi, momenti_memorabili, personalita, visibile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssssssssi",
            $data['slug'],
            $data['nome'],
            $data['ruolo'],
            $badges,
            $data['anno_ingresso'],
            $data['citta'],
            $data['specialita'],
            $data['descrizione_breve'],
            $data['intro'],
            $data['storia'],
            $data['contributi'],
            $data['momenti_memorabili'],
            $data['personalita'],
            $data['visibile']
        );
    }

    $stmt->execute();
    $stmt->close();
}

function salvaEvento($mysqli, $data)
{
    $id = $data['id'] ?? null;
    $tags = json_encode(explode(',', $data['tags'] ?? ''));
    $stats = json_encode([
        'ore' => $data['stat_ore'] ?? '',
        'meme' => $data['stat_meme'] ?? '',
        'ricordi' => $data['stat_ricordi'] ?? '',
        'divertimento' => $data['stat_divertimento'] ?? ''
    ]);

    // Gestione cronologia (semplificata - puoi espandere)
    $cronologia = json_encode([]);

    if ($id) {
        $stmt = $mysqli->prepare("UPDATE cripsumpedia_eventi SET slug=?, titolo=?, sottotitolo=?, icona=?, data_inizio=?, data_fine=?, luogo=?, num_partecipanti=?, tags=?, descrizione_breve=?, panoramica=?, cronologia=?, momenti_iconici=?, impatto=?, curiosita=?, stats=?, anno=?, visibile=? WHERE id=?");
        $stmt->bind_param(
            "sssssssisssssssssii",
            $data['slug'],
            $data['titolo'],
            $data['sottotitolo'],
            $data['icona'],
            $data['data_inizio'],
            $data['data_fine'],
            $data['luogo'],
            $data['num_partecipanti'],
            $tags,
            $data['descrizione_breve'],
            $data['panoramica'],
            $cronologia,
            $data['momenti_iconici'],
            $data['impatto'],
            $data['curiosita'],
            $stats,
            $data['anno'],
            $data['visibile'],
            $id
        );
    } else {
        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_eventi (slug, titolo, sottotitolo, icona, data_inizio, data_fine, luogo, num_partecipanti, tags, descrizione_breve, panoramica, cronologia, momenti_iconici, impatto, curiosita, stats, anno, visibile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssissssssssi",
            $data['slug'],
            $data['titolo'],
            $data['sottotitolo'],
            $data['icona'],
            $data['data_inizio'],
            $data['data_fine'],
            $data['luogo'],
            $data['num_partecipanti'],
            $tags,
            $data['descrizione_breve'],
            $data['panoramica'],
            $cronologia,
            $data['momenti_iconici'],
            $data['impatto'],
            $data['curiosita'],
            $stats,
            $data['anno'],
            $data['visibile']
        );
    }

    $stmt->execute();
    $stmt->close();
}

function salvaMeme($mysqli, $data)
{
    $id = $data['id'] ?? null;
    $badges = json_encode(explode(',', $data['badges'] ?? ''));
    $esempi = json_encode([]);

    if ($id) {
        $stmt = $mysqli->prepare("UPDATE cripsumpedia_meme SET slug=?, titolo=?, sottotitolo=?, icona=?, creatore_id=?, data_nascita=?, categoria=?, badges=?, popolarita=?, citazione_originale=?, descrizione_breve=?, origine=?, evoluzione=?, usi_contesti=?, impatto_culturale=?, curiosita=?, esempi=?, utilizzi_estimati=?, anno=?, visibile=? WHERE id=?");
        $stmt->bind_param(
            "ssssisssssssssssssii",
            $data['slug'],
            $data['titolo'],
            $data['sottotitolo'],
            $data['icona'],
            $data['creatore_id'],
            $data['data_nascita'],
            $data['categoria'],
            $badges,
            $data['popolarita'],
            $data['citazione_originale'],
            $data['descrizione_breve'],
            $data['origine'],
            $data['evoluzione'],
            $data['usi_contesti'],
            $data['impatto_culturale'],
            $data['curiosita'],
            $esempi,
            $data['utilizzi_estimati'],
            $data['anno'],
            $data['visibile'],
            $id
        );
    } else {
        $stmt = $mysqli->prepare("INSERT INTO cripsumpedia_meme (slug, titolo, sottotitolo, icona, creatore_id, data_nascita, categoria, badges, popolarita, citazione_originale, descrizione_breve, origine, evoluzione, usi_contesti, impatto_culturale, curiosita, esempi, utilizzi_estimati, anno, visibile) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param(
            "sssssssssssssssssssi",
            $data['slug'],
            $data['titolo'],
            $data['sottotitolo'],
            $data['icona'],
            $data['creatore_id'],
            $data['data_nascita'],
            $data['categoria'],
            $badges,
            $data['popolarita'],
            $data['citazione_originale'],
            $data['descrizione_breve'],
            $data['origine'],
            $data['evoluzione'],
            $data['usi_contesti'],
            $data['impatto_culturale'],
            $data['curiosita'],
            $esempi,
            $data['utilizzi_estimati'],
            $data['anno'],
            $data['visibile']
        );
    }

    $stmt->execute();
    $stmt->close();
}

?>
<!DOCTYPE html>
<html lang="it">

<head>
    <?php include '../../includes/head-import.php'; ?>
    <title>Admin - Cripsumpedia™</title>
    <style>
        body {
            background-color: #0a0a0a;
            color: #ffffff;
            padding-top: 5rem;
        }

        .admin-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .admin-nav {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .nav-btn {
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 10px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .nav-btn:hover,
        .nav-btn.active {
            background: linear-gradient(135deg, rgba(100, 200, 255, 0.2), rgba(74, 158, 255, 0.15));
            border-color: rgba(100, 200, 255, 0.4);
            color: #64c8ff;
        }

        .content-box {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08), rgba(255, 255, 255, 0.04));
            border-radius: 16px;
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.12);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, rgba(51, 51, 51, 0.9), rgba(40, 40, 40, 0.95));
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-family: 'Poppins', sans-serif;
        }

        textarea.form-control {
            min-height: 150px;
            resize: vertical;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3a3a3d, #2a2a2d);
            color: #fff;
            padding: 0.75rem 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15), rgba(255, 255, 255, 0.08));
            transform: translateY(-2px);
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th,
        td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        th {
            background: rgba(255, 255, 255, 0.05);
            font-weight: 600;
        }

        .btn-small {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
            border-radius: 6px;
            text-decoration: none;
            display: inline-block;
            margin-right: 0.5rem;
        }

        .btn-edit {
            background: rgba(100, 200, 255, 0.2);
            color: #64c8ff;
            border: 1px solid rgba(100, 200, 255, 0.4);
        }

        .btn-delete {
            background: rgba(255, 100, 100, 0.2);
            color: #ff6464;
            border: 1px solid rgba(255, 100, 100, 0.4);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: rgba(100, 255, 100, 0.1);
            border: 1px solid rgba(100, 255, 100, 0.3);
            color: #64ff64;
        }

        .help-text {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.5);
            margin-top: 0.25rem;
        }
    </style>
</head>

<body>
    <?php include '../../includes/navbar.php'; ?>

    <div class="admin-container">
        <h1 style="font-size: 2.5rem; margin-bottom: 2rem;">
            <i class="fas fa-cog"></i> Pannello Admin - Cripsumpedia
        </h1>

        <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Salvato con successo!
            </div>
        <?php endif; ?>

        <nav class="admin-nav">
            <a href="?sezione=dashboard" class="nav-btn <?= $sezione === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-home"></i> Dashboard
            </a>
            <a href="?sezione=persone" class="nav-btn <?= $sezione === 'persone' ? 'active' : '' ?>">
                <i class="fas fa-users"></i> Persone
            </a>
            <a href="?sezione=eventi" class="nav-btn <?= $sezione === 'eventi' ? 'active' : '' ?>">
                <i class="fas fa-calendar-star"></i> Eventi
            </a>
            <a href="?sezione=meme" class="nav-btn <?= $sezione === 'meme' ? 'active' : '' ?>">
                <i class="fas fa-face-grin-tears"></i> Meme
            </a>
        </nav>

        <div class="content-box">
            <?php
            if ($sezione === 'dashboard') {
                include 'admin-parts/dashboard.php';
            } elseif ($sezione === 'persone') {
                if ($azione === 'modifica' || $azione === 'nuovo') {
                    include 'admin-parts/persona-form.php';
                } else {
                    include 'admin-parts/persone-lista.php';
                }
            } elseif ($sezione === 'eventi') {
                if ($azione === 'modifica' || $azione === 'nuovo') {
                    include 'admin-parts/evento-form.php';
                } else {
                    include 'admin-parts/eventi-lista.php';
                }
            } elseif ($sezione === 'meme') {
                if ($azione === 'modifica' || $azione === 'nuovo') {
                    include 'admin-parts/meme-form.php';
                } else {
                    include 'admin-parts/meme-lista.php';
                }
            }
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>