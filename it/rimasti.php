<?php
require_once '../config/session_init.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
checkBan($mysqli);

$isAdmin = false;
if (isset($_SESSION['user_id'])) {
    $stmt = $mysqli->prepare("SELECT ruolo FROM utenti WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $isAdmin = in_array($row['ruolo'], ['admin', 'owner']);
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - rimasti</title>
    <style>
        .posts-section {
            max-width: 1400px;
            margin: 0 auto;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
            padding: 2rem 0;
        }

        .post-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            opacity: 0;
            transform: translateY(20px);
            max-width: 900px;
        }

        .post-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 40px rgba(100, 200, 255, 0.1);
            border-color: rgba(100, 200, 255, 0.2);
        }

        .post-card.position-1 {
            border: 2px solid rgba(255, 215, 0, 0.5);
            background: linear-gradient(135deg, rgba(255, 215, 0, 0.15) 0%, rgba(255, 193, 7, 0.08) 100%);
        }

        .post-card.position-2 {
            border: 2px solid rgba(192, 192, 192, 0.5);
            background: linear-gradient(135deg, rgba(192, 192, 192, 0.15) 0%, rgba(169, 169, 169, 0.08) 100%);
        }

        .post-card.position-3 {
            border: 2px solid rgba(205, 127, 50, 0.5);
            background: linear-gradient(135deg, rgba(205, 127, 50, 0.15) 0%, rgba(184, 115, 51, 0.08) 100%);
        }

        /* Admin section styles */
        .admin-section {
            max-width: 1400px;
            margin: 0 auto;
            margin-top: 3rem;
            padding-top: 2rem;
            border-top: 2px solid rgba(255, 215, 0, 0.3);
        }

        .admin-title {
            color: #FFD700;
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
            text-align: center;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .admin-subtitle {
            color: rgba(255, 215, 0, 0.8);
            text-align: center;
            margin-bottom: 2rem;
            font-style: italic;
        }

        .pending-post {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.15) 0%, rgba(255, 215, 0, 0.08) 100%);
            border: 2px solid rgba(255, 215, 0, 0.4);
        }

        .admin-controls {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 10;
            display: flex;
            gap: 0.5rem;
        }

        .admin-btn {
            background: rgba(0, 0, 0, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .admin-btn.approve {
            background: linear-gradient(135deg, #2ed573, #1e90ff);
        }

        .admin-btn.approve:hover {
            background: linear-gradient(135deg, #1e90ff, #2ed573);
            transform: scale(1.05);
        }

        .admin-btn.delete {
            background: linear-gradient(135deg, #ff4757, #ff3838);
        }

        .admin-btn.delete:hover {
            background: linear-gradient(135deg, #ff3838, #ff2f2f);
            transform: scale(1.05);
        }

        .admin-btn.disapprove {
            background: linear-gradient(135deg, #ffa502, #ff6348);
        }

        .admin-btn.disapprove:hover {
            background: linear-gradient(135deg, #ff6348, #ffa502);
            transform: scale(1.05);
        }

        .approval-status {
            position: absolute;
            top: 1rem;
            left: 5rem;
            z-index: 10;
            background: rgba(255, 215, 0, 0.9);
            color: black;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .approval-status.pending {
            background: rgba(255, 193, 7, 0.9);
        }

        .approval-status.approved {
            background: rgba(46, 213, 115, 0.9);
            color: white;
        }

        .post-rank {
            position: absolute;
            top: 1rem;
            left: 1rem;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(20, 20, 20, 0.9));
            padding: 0.5rem 1rem;
            border-radius: 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .rank-number {
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .rank-crown {
            font-size: 1.2rem;
        }

        .post-image-container {
            position: relative;
            height: 300px;
            overflow: hidden;
            cursor: pointer;
            background: linear-gradient(135deg, rgba(30, 32, 42, 0.8) 0%, rgba(40, 45, 60, 0.8) 100%);
        }

        .post-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
            filter: brightness(0.9) contrast(1.1);
        }

        .post-card:hover .post-image {
            transform: scale(1.05);
            filter: brightness(1) contrast(1.2);
        }

        .post-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.6), rgba(30, 32, 42, 0.7));
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
            backdrop-filter: blur(2px);
        }

        .post-card:hover .post-overlay {
            opacity: 1;
        }

        .overlay-content {
            text-align: center;
            color: white;
            font-weight: 500;
        }

        .overlay-icon {
            display: block;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            -webkit-background-clip: inherit;
            -webkit-text-fill-color: inherit;
            background-clip: inherit;
            background: transparent;
        }

        .post-content {
            padding: 1.5rem;
        }

        .post-title {
            color: white;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            line-height: 1.3;
        }

        .post-author {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
            margin-bottom: 1rem;
            font-style: italic;
        }

        .post-description {
            color: rgba(255, 255, 255, 0.85);
            font-size: 1rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .post-motivation {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
        }

        .post-motivation strong {
            color: rgba(255, 100, 100, 0.9);
            display: block;
            margin-bottom: 0.5rem;
        }

        .post-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .vote-section {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.5rem;
        }

        .vote-btn {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #ff4757, #ff3838);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            ;
            border-radius: 25px;
            ;
            cursor: pointer;
            ;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .vote-btn:hover {
            background: linear-gradient(135deg, #ff3838, #ff2f2f);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 71, 87, 0.4);
        }

        .vote-btn.voted {
            background: linear-gradient(135deg, #2ed573, #1e90ff);
            box-shadow: 0 4px 15px rgba(46, 213, 115, 0.3);
        }

        .vote-btn.voted:hover {
            background: linear-gradient(135deg, #1e90ff, #2ed573);
        }

        .vote-btn.voting {
            opacity: 0.7;
            pointer-events: none;
        }

        .vote-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
        }

        .vote-icon {
            font-size: 1.2rem;
        }

        .vote-count {
            font-weight: bold;
            font-size: 1.1rem;
        }

        .login-prompt {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.8rem;
        }

        .post-date {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        .add-post-section {
            max-width: 800px;
            margin: 0 auto;
        }

        .add-post-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .add-post-title {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .add-post-form {
            margin-top: 2rem;
            text-align: left;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 1rem;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(51, 51, 51, 0.9), rgba(40, 40, 40, 0.95));
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 1rem;
        }

        .form-group small {
            display: block;
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .modal-content {
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.95), rgba(20, 20, 20, 0.98));
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            max-width: 900px;
            width: 100%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            backdrop-filter: blur(20px);
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            font-size: 2rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            z-index: 10001;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .modal-post {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
        }

        .modal-image img {
            width: 100%;
            height: auto;
            border-radius: 12px;
        }

        .modal-details h2 {
            color: white;
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }

        .modal-author {
            color: rgba(255, 255, 255, 0.7);
            font-style: italic;
            margin-bottom: 1rem;
        }

        .modal-description {
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .modal-motivation {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.02));
            padding: 1rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            color: white;
        }

        .modal-motivation h4 {
            color: rgba(255, 100, 100, 0.9);
            margin-bottom: 0.5rem;
        }

        .modal-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        .modal-votes {
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.08) 0%, rgba(255, 255, 255, 0.04) 100%);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(15px);
            padding: 3rem;
            max-width: 500px;
            margin: 0 auto;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .empty-card h3 {
            color: white;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }

        .empty-card p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .loading-container {
            text-align: center;
            padding: 4rem 2rem;
        }

        @media (max-width: 768px) {
            .posts-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1rem 0;
            }

            .post-card {
                margin: 0 1rem;
            }

            .modal-post {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1rem;
            }

            .modal-content {
                margin: 1rem;
                max-height: 90vh;
            }

            .add-post-card {
                margin: 0 1rem;
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
                align-items: center;
            }

            .post-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .chisiamo-section {
                padding: 4rem 1rem 2rem;
            }

            .admin-controls {
                top: 0.5rem;
                right: 0.5rem;
                flex-direction: column;
                gap: 0.3rem;
            }

            .admin-btn {
                padding: 0.3rem 0.5rem;
                font-size: 0.7rem;
            }
        }

        @media (max-width: 480px) {
            .posts-grid {
                grid-template-columns: minmax(280px, 1fr);
                padding: 0.5rem;
            }

            .post-card {
                margin: 0;
            }

            .post-image-container {
                height: 250px;
            }

            .post-content {
                padding: 1rem;
            }

            .post-title {
                font-size: 1.1rem;
            }

            .vote-btn {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="paginaprincipale">
        <div class="main-container">

            <div class="chisiamo-section fadeup">
                <h1 class="chisiamo-title">Top Rimasti</h1>
                <p class="chisiamo-subtitle">
                    Scopri la classifica dei soggetti più imbarazzanti mai incontrati dalla community di Cripsum™.
                    Vota i tuoi preferiti e contribuisci a determinare chi si aggiudica il titolo di "più rimasto"!
                </p>
            </div>

            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="add-post-section fadeup" style="margin-bottom: 3rem;">
                    <div class="add-post-card">
                        <h3 class="add-post-title">Hai incontrato un rimasto? condivi qui la tua esperienza :P</h3>
                        <button class="bottone" onclick="toggleAddPostForm()">
                            <span id="toggleButtonText">Aggiungi nuovo post</span>
                        </button>

                        <div id="addPostForm" class="add-post-form" style="display: none;">
                            <form id="newPostForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <input type="text" name="titolo" placeholder="Titolo del post" required>
                                </div>
                                <div class="form-group">
                                    <textarea name="descrizione" placeholder="Descrizione..." rows="3" required></textarea>
                                </div>
                                <div class="form-group">
                                    <textarea name="motivazione" placeholder="Perché questa persona è rimasta?" rows="2" required></textarea>
                                </div>
                                <div class="form-group">
                                    <input type="file" name="foto_rimasto" accept="image/*" required>
                                    <small style="color: rgba(255,255,255,0.7);">Carica una foto della persona o che dimostri quanto è un rimasto</small>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="bottone">Invia Post</button>
                                    <button type="button" class="bottone2" onclick="toggleAddPostForm()">Annulla</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div id="loadingState" class="loading-container fadeup">
                <div class="loading_white">
                    <div class="loading__dot_white"></div>
                    <div class="loading__dot_white"></div>
                    <div class="loading__dot_white"></div>
                </div>
                <p class="testobianco" style="text-align: center; margin-top: 1rem;">Caricamento dei post...</p>
            </div>

            <div id="postsContainer" class="posts-section" style="display: none;"></div>
            <div id="postsGrid" class="posts-grid">

            </div>
        </div>

        <?php if ($isAdmin): ?>
            <div id="adminSection" class="admin-section" style="display: none;">
                <h2 class="admin-title">Pannello Admin - Post in Attesa</h2>
                <p class="admin-subtitle">Gestisci i post in attesa di approvazione</p>

                <div id="loadingPendingState" class="loading-container">
                    <div class="loading_white">
                        <div class="loading__dot_white"></div>
                        <div class="loading__dot_white"></div>
                        <div class="loading__dot_white"></div>
                    </div>
                    <p class="testobianco" style="text-align: center; margin-top: 1rem;">Caricamento post in attesa...</p>
                </div>

                <div id="pendingPostsContainer" class="posts-section" style="display: none;">
                    <div id="pendingPostsGrid" class="posts-grid">

                    </div>
                </div>

                <div id="noPendingPosts" class="empty-state" style="display: none;">
                    <div class="empty-card">
                        <div class="empty-icon">✅</div>
                        <h3>Nessun post in attesa</h3>
                        <p>Tutti i post sono stati gestiti.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div id="emptyState" class="empty-state fadeup" style="display: none;">
            <div class="empty-card">
                <div class="empty-icon">🤷‍♂️</div>
                <h3>Nessun post trovato</h3>
                <p>Non ci sono ancora post da mostrare. Sii il primo a postare un rimasto!</p>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <button class="bottone" onclick="toggleAddPostForm()">Aggiungi il primo post</button>
                <?php endif; ?>
            </div>
        </div>

        <div id="postModal" class="modal-overlay" style="display: none;">
            <div class="modal-content">
                <button class="modal-close" onclick="closePostModal()" style="line-height: 1; padding-top: 0;">&times;</button>
                <div id="modalContent">
                </div>
            </div>
        </div>
    </div>
    </div>

    <?php include '../includes/scroll_indicator.php'; ?>

    <?php include '../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/modeChanger.js"></script>

    <script>
        let allPosts = [];
        let pendingPosts = [];
        let userVotes = {};
        const isAdmin = <?php echo json_encode($isAdmin); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            loadPosts();
            if (isAdmin) {
                loadPendingPosts();
            }
        });

        async function loadPosts() {
            try {
                const response = await fetch('../api/get_toprimasti.php');
                const data = await response.json();

                if (Array.isArray(data)) {
                    allPosts = data.sort((a, b) => parseInt(b.reazioni) - parseInt(a.reazioni));
                    displayPosts(allPosts);
                    loadUserVotes();
                } else {
                    showEmptyState();
                }
            } catch (error) {
                console.error('Errore nel caricamento dei post:', error);
                showEmptyState();
            }
        }

        async function loadPendingPosts() {
            if (!isAdmin) return;

            try {
                const response = await fetch('../api/get_pending_toprimasti.php');
                const data = await response.json();

                document.getElementById('loadingPendingState').style.display = 'none';

                if (Array.isArray(data) && data.length > 0) {
                    pendingPosts = data;
                    displayPendingPosts(pendingPosts);
                    document.getElementById('adminSection').style.display = 'block';
                } else {
                    document.getElementById('noPendingPosts').style.display = 'block';
                    document.getElementById('adminSection').style.display = 'block';
                }
            } catch (error) {
                console.error('Errore nel caricamento dei post in attesa:', error);
                document.getElementById('loadingPendingState').style.display = 'none';
                document.getElementById('noPendingPosts').style.display = 'block';
                document.getElementById('adminSection').style.display = 'block';
            }
        }

        async function loadUserVotes() {
            <?php if (isset($_SESSION['user_id'])): ?>
                try {
                    const response = await fetch('../api/get_user_votes.php');
                    const votes = await response.json();
                    if (votes.success) {
                        userVotes = votes.votes;
                        updateVoteButtons();
                    }
                } catch (error) {
                    console.error('Errore nel caricamento dei voti:', error);
                }
            <?php endif; ?>
        }

        function displayPosts(posts) {
            const loadingState = document.getElementById('loadingState');
            const postsContainer = document.getElementById('postsContainer');
            const emptyState = document.getElementById('emptyState');
            const postsGrid = document.getElementById('postsGrid');

            loadingState.style.display = 'none';

            if (posts.length === 0) {
                showEmptyState();
                return;
            }

            emptyState.style.display = 'none';
            postsContainer.style.display = 'block';

            postsGrid.innerHTML = posts.map((post, index) => {
                const position = index + 1;
                const positionClass = position <= 3 ? `position-${position}` : '';

                return `
                    <div class="post-card ${positionClass} fadeup" style="animation-delay: ${index * 0.1}s">
                        ${isAdmin ? `
                        <div class="admin-controls">
                            <button class="admin-btn delete" onclick="deletePost(${post.id}, false)" title="Elimina post">
                                🗑️
                            </button>
                            <button class="admin-btn disapprove" onclick="changeApproval(${post.id}, 0)" title="Rimuovi approvazione">
                                ❌
                            </button>
                        </div>
                        <div class="approval-status approved">
                            ✅ Approvato
                        </div>
                        ` : ''}
                        
                        <div class="post-rank">
                            <span class="rank-number">#${position}</span>
                            ${position <= 3 ? `<span class="rank-crown">${position === 1 ? '👑' : position === 2 ? '🥈' : '🥉'}</span>` : ''}
                        </div>
                        
                        <div class="post-image-container" onclick="openPostModal(${post.id}, false)">
                            <img src="data:${post.tipo_foto_rimasto};base64,${post.foto_rimasto}" 
                                 alt="${post.titolo}" class="post-image">
                            <div class="post-overlay">
                                <div class="overlay-content">
                                    <span class="overlay-icon">👁️</span>
                                    <span class="overlay-text">Visualizza dettagli</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="post-content">
                            <h3 class="post-title">${post.titolo}</h3>
                            <p class="post-author">di ${post.username || 'Utente anonimo'}</p>
                            <p class="post-description">${post.descrizione}</p>
                            <div class="post-motivation">
                                <strong>Perchè è un rimasto:</strong> ${post.motivazione}
                            </div>
                            
                            <div class="post-actions">
                                <div class="vote-section">
                                    <?php if (isset($_SESSION['user_id'])): ?>
                                    <button class="vote-btn" onclick="toggleVote(${post.id})" data-post-id="${post.id}">
                                        <span class="vote-icon">🔥</span>
                                        <span class="vote-count">${post.reazioni}</span>
                                        <span class="vote-text">Vota</span>
                                    </button>
                                    <?php else: ?>
                                    <div class="vote-display">
                                        <span class="vote-icon">🔥</span>
                                        <span class="vote-count">${post.reazioni}</span>
                                        <span class="vote-text">voti</span>
                                    </div>
                                    <small class="login-prompt">
                                        <a href="accedi" class="linkbianco">Accedi</a> per votare
                                    </small>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="post-date">
                                    ${formatDate(post.data_creazione)}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            const cards = document.querySelectorAll('.post-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }

        function displayPendingPosts(posts) {
            const pendingPostsContainer = document.getElementById('pendingPostsContainer');
            const pendingPostsGrid = document.getElementById('pendingPostsGrid');

            if (posts.length === 0) {
                document.getElementById('noPendingPosts').style.display = 'block';
                return;
            }

            pendingPostsContainer.style.display = 'block';

            pendingPostsGrid.innerHTML = posts.map((post, index) => {
                return `
                    <div class="post-card pending-post fadeup" style="animation-delay: ${index * 0.1}s">
                        <div class="admin-controls">
                            <button class="admin-btn approve" onclick="changeApproval(${post.id}, 1)" title="Approva post">
                                ✅
                            </button>
                            <button class="admin-btn delete" onclick="deletePost(${post.id}, true)" title="Elimina post">
                                🗑️
                            </button>
                        </div>
                        <div class="approval-status pending">
                            ⏳ In attesa
                        </div>
                        
                        <div class="post-image-container" onclick="openPostModal(${post.id}, true)">
                            <img src="data:${post.tipo_foto_rimasto};base64,${post.foto_rimasto}" 
                                 alt="${post.titolo}" class="post-image">
                            <div class="post-overlay">
                                <div class="overlay-content">
                                    <span class="overlay-icon">👁️</span>
                                    <span class="overlay-text">Visualizza dettagli</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="post-content">
                            <h3 class="post-title">${post.titolo}</h3>
                            <p class="post-author">di ${post.username || 'Utente anonimo'}</p>
                            <p class="post-description">${post.descrizione}</p>
                            <div class="post-motivation">
                                <strong>Perchè è un rimasto:</strong> ${post.motivazione}
                            </div>
                            
                            <div class="post-actions">
                                <div class="vote-section">
                                    <div class="vote-display">
                                        <span class="vote-icon">🔥</span>
                                        <span class="vote-count">${post.reazioni}</span>
                                        <span class="vote-text">voti</span>
                                    </div>
                                </div>
                                
                                <div class="post-date">
                                    ${formatDate(post.data_creazione)}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');

            const cards = document.querySelectorAll('#pendingPostsGrid .post-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        }

        async function changeApproval(postId, approval) {
            if (!isAdmin) return;

            const action = approval === 1 ? 'approvare' : 'rimuovere approvazione da';
            if (!confirm(`Sei sicuro di voler ${action} questo post?`)) {
                return;
            }

            try {
                const response = await fetch('../api/manage_post_approval.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        post_id: postId,
                        approved: approval
                    })
                });

                const result = await response.json();

                if (result.success) {
                    if (approval === 1) {
                        pendingPosts = pendingPosts.filter(p => p.id != postId);
                        displayPendingPosts(pendingPosts);
                        loadPosts();
                    } else {
                        loadPosts();
                        loadPendingPosts();
                    }
                    alert(`Post ${approval === 1 ? 'approvato' : 'disapprovato'} con successo!`);
                } else {
                    alert('Errore: ' + (result.error || 'Errore sconosciuto'));
                }
            } catch (error) {
                console.error('Errore nella gestione dell\'approvazione:', error);
                alert('Errore nella richiesta');
            }
        }

        async function deletePost(postId, isPending) {
            if (!isAdmin) return;

            if (!confirm('Sei sicuro di voler eliminare questo post? Questa azione non può essere annullata.')) {
                return;
            }

            try {
                const response = await fetch('../api/delete_toprimasti_post.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        post_id: postId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    if (isPending) {
                        pendingPosts = pendingPosts.filter(p => p.id != postId);
                        displayPendingPosts(pendingPosts);
                    } else {
                        loadPosts();
                    }
                    alert('Post eliminato con successo!');
                } else {
                    alert('Errore: ' + (result.error || 'Errore sconosciuto'));
                }
            } catch (error) {
                console.error('Errore nell\'eliminazione del post:', error);
                alert('Errore nella richiesta');
            }
        }

        function showEmptyState() {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('postsContainer').style.display = 'none';
            document.getElementById('emptyState').style.display = 'block';
        }

        async function toggleVote(postId) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = 'accedi';
                return;
            <?php endif; ?>

            const voteBtn = document.querySelector(`[data-post-id="${postId}"]`);
            const isVoted = userVotes[postId] || false;

            try {
                voteBtn.classList.add('voting');
                voteBtn.disabled = true;

                const response = await fetch('../api/toggle_vote_toprimasti.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        post_id: postId,
                        action: isVoted ? 'remove' : 'add'
                    })
                });

                const result = await response.json();

                if (result.success) {
                    userVotes[postId] = !isVoted;

                    const postIndex = allPosts.findIndex(p => p.id == postId);
                    if (postIndex !== -1) {
                        allPosts[postIndex].reazioni = result.newVoteCount;
                    }

                    allPosts.sort((a, b) => parseInt(b.reazioni) - parseInt(a.reazioni));
                    displayPosts(allPosts);
                } else {
                    console.error('Errore nel voto:', result.message);
                }
            } catch (error) {
                console.error('Errore nella richiesta di voto:', error);
            } finally {
                voteBtn.classList.remove('voting');
                voteBtn.disabled = false;
            }
        }

        function updateVoteButtons() {
            Object.keys(userVotes).forEach(postId => {
                const voteBtn = document.querySelector(`[data-post-id="${postId}"]`);
                if (voteBtn && userVotes[postId]) {
                    voteBtn.classList.add('voted');
                    voteBtn.querySelector('.vote-text').textContent = 'Votato';
                }
            });
        }

        function openPostModal(postId, isPending) {
            const posts = isPending ? pendingPosts : allPosts;
            const post = posts.find(p => p.id == postId);
            if (!post) return;

            const modalContent = document.getElementById('modalContent');
            modalContent.innerHTML = `
                <div class="modal-post">
                    <div class="modal-image">
                        <img src="data:${post.tipo_foto_rimasto};base64,${post.foto_rimasto}" 
                             alt="${post.titolo}">
                    </div>
                    <div class="modal-details">
                        <h2>${post.titolo}</h2>
                        <p class="modal-author">di ${post.username || 'Utente anonimo'}</p>
                        <p class="modal-description">${post.descrizione}</p>
                        <div class="modal-motivation">
                            <h4>Perchè è un rimasto:</h4>
                            <p>${post.motivazione}</p>
                        </div>
                        <div class="modal-stats">
                            <span class="modal-votes">🔥 ${post.reazioni} voti</span>
                            <span class="modal-date">${formatDate(post.data_creazione)}</span>
                        </div>
                        ${isPending ? '<p style="color: #FFD700; font-weight: bold; margin-top: 1rem;">⏳ Post in attesa di approvazione</p>' : ''}
                    </div>
                </div>
            `;

            document.getElementById('postModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closePostModal() {
            document.getElementById('postModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        function toggleAddPostForm() {
            const form = document.getElementById('addPostForm');
            const button = document.getElementById('toggleButtonText');

            if (form.style.display === 'none') {
                form.style.display = 'block';
                button.textContent = 'Annulla';
            } else {
                form.style.display = 'none';
                button.textContent = 'Aggiungi nuovo post';
                document.getElementById('newPostForm').reset();
            }
        }

        document.getElementById('newPostForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;

            console.log('Invio nuovo post...');
            for (let [key, value] of formData.entries()) {
                if (key === 'foto_rimasto') {
                    console.log(key + ':', value.name, value.size + ' bytes', value.type);
                } else {
                    console.log(key + ':', value);
                }
            }

            const fileInput = this.querySelector('input[type="file"]');
            if (!fileInput.files[0]) {
                alert('Per favore seleziona un\'immagine');
                return;
            }

            const file = fileInput.files[0];
            console.log('File selezionato:', file.name, file.size + ' bytes', file.type);

            if (file.size > 5 * 1024 * 1024) {
                alert('File troppo grande. Massimo 5MB');
                return;
            }

            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Tipo di file non consentito. Usa solo JPEG, PNG, GIF o WebP');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Invio in corso...';

            try {
                const response = await fetch('../api/set_new_toprimasti_post.php', {
                    method: 'POST',
                    body: formData
                });

                console.log('Response status:', response.status);
                console.log('Response headers:', Object.fromEntries(response.headers.entries()));

                const result = await response.json();
                console.log('Response data:', result);

                if (result.success) {
                    alert('Post inviato con successo! Sarà visibile dopo l\'approvazione dell\'admin.');
                    toggleAddPostForm();
                    this.reset();
                    if (isAdmin) {
                        loadPendingPosts();
                    }
                } else {
                    alert('Errore: ' + (result.error || 'Errore sconosciuto'));
                    console.error('Errore dal server:', result.error);
                }
            } catch (error) {
                console.error('Errore nell\'invio del post:', error);
                alert('Errore nell\'invio del post. Controlla la console per dettagli.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        });

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('it-IT', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
        }

        document.getElementById('postModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePostModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('postModal').style.display === 'flex') {
                closePostModal();
            }
        });
    </script>
</body>

</html>