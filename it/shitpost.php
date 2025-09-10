<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();
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
        <title>Cripsum™ - shitpost</title>
        <style>
        img {
            border-radius: 10px;
        }

        .posts-section {
            max-width: 80%;
            margin: 0 auto;
        }

        .posts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(600px, 1fr));
            gap: 2rem;
            padding: 2rem 0;
        }

        .post-card {
            background: linear-gradient(135deg, rgba(30, 30, 30, 0.95), rgba(20, 20, 20, 0.98));
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(20px);
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            opacity: 0;
            transform: translateY(20px);
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            padding: 2rem;
        }

        .post-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4), 0 0 40px rgba(100, 200, 255, 0.1);
            border-color: rgba(100, 200, 255, 0.3);
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
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.15), rgba(255, 215, 0, 0.08));
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
            left: 1rem;
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

        .post-image img {
            width: 100%;
            height: auto;
            border-radius: 12px;
        }

        .post-details h2 {
            color: white;
            margin-bottom: 1rem;
            font-size: 1.8rem;
        }

        .post-author {
            color: rgba(255, 255, 255, 0.7);
            font-style: italic;
            margin-bottom: 1rem;
        }

        .post-description {
            color: rgba(255, 255, 255, 0.85);
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .post-stats {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
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

        /* Legacy shitpost styles for original content */
        .legacy-shitpost {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 0;
        }

        .shitposttext {
            padding: 2rem;
            text-align: center;
        }

        @media (max-width: 1200px) {
            .post-card {
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1.5rem;
            }

            .posts-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1rem 0;
            }
        }

        @media (max-width: 768px) {
            .posts-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                padding: 1rem 0;
            }

            .post-card {
                margin: 0 1rem;
                grid-template-columns: 1fr;
                gap: 1rem;
                padding: 1rem;
            }

            .add-post-card {
                margin: 0 1rem;
                padding: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
                align-items: center;
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
                padding: 1rem;
            }

            .post-details h2 {
                font-size: 1.4rem;
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
                    <h1 class="chisiamo-title">Shitpost</h1>
                    <p class="chisiamo-subtitle">
                        Hey, hai dei meme o contenuti shitpost che sarebbero perfetti per questa pagina? 
                        Condividi i tuoi contenuti più assurdi e divertenti con la community di Cripsum™!
                    </p>
                </div>

                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="add-post-section fadeup">
                    <div class="add-post-card">
                        <h3 class="add-post-title">Condividi il tuo shitpost con la community!</h3>
                        <button class="bottone" onclick="toggleAddPostForm()">
                            <span id="toggleButtonText">Aggiungi nuovo shitpost</span>
                        </button>
                        
                    <div id="addPostForm" class="add-post-form" style="display: none;">
                        <form id="newPostForm" enctype="multipart/form-data">
                            <div class="form-group">
                                <input type="text" name="titolo" placeholder="Titolo del shitpost" required>
                            </div>
                                <div class="form-group">
                                    <textarea name="descrizione" placeholder="Descrizione o contesto del meme..." rows="4" required></textarea>
                                </div>
                                <div class="form-group">
                                    <input type="file" name="foto_shitpost" accept="image/*" required>
                                    <small style="color: rgba(255,255,255,0.7);">Carica il tuo meme o shitpost (JPEG, PNG, GIF o WebP)</small>
                                </div>
                                <div class="form-actions">
                                    <button type="submit" class="bottone">Invia Shitpost</button>
                                    <button type="button" class="bottone2" onclick="toggleAddPostForm()">Annulla</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div id="loadingState" class="loading-container">
                    <div class="loading_white">
                        <div class="loading__dot_white"></div>
                        <div class="loading__dot_white"></div>
                        <div class="loading__dot_white"></div>
                    </div>
                    <p class="testobianco" style="text-align: center; margin-top: 1rem;">Caricamento dei shitpost...</p>
                </div>

                <div id="postsContainer" class="posts-section" style="display: none;">
                    <div id="postsGrid" class="posts-grid">

                    </div>
                </div>

                <div class="legacy-shitpost" style="max-width: 80%">
                    <div class="posts-grid" style="margin: 1rem 0;">
                        <div class="post-card fadeup">
                            <?php if ($isAdmin): ?>
                            <div class="admin-controls">
                                <span class="admin-btn" style="background: rgba(100, 100, 100, 0.8); cursor: default;">Legacy</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="post-image">
                                <img src="../img/beans.jpg" alt="Magia del Natale">
                            </div>
                            <div class="post-details">
                                <h2>Questa è la magia</h2>
                                <p class="post-author">by cripsum</p>
                                <p class="post-description">La magia del natale, quella vera</p>
                                <div class="post-stats">
                                    <span class="post-date">Shitpost originale</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="posts-grid" style="margin: 1rem 0;">
                        <div class="post-card fadeup">
                            <?php if ($isAdmin): ?>
                            <div class="admin-controls">
                                <span class="admin-btn" style="background: rgba(100, 100, 100, 0.8); cursor: default;">Legacy</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="post-image">
                                <img src="../img/tengodiarrea.jpg" alt="Le mie palle quando">
                            </div>
                            <div class="post-details">
                                <h2>Le mie palle quando</h2>
                                <p class="post-author">by sk8ing ray</p>
                                <p class="post-description">Ma soprattutto il mio culetto quando...</p>
                                <div class="post-stats">
                                    <span class="post-date">Shitpost originale</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="posts-grid" style="margin: 1rem 0;">
                        <div class="post-card fadeup">
                            <?php if ($isAdmin): ?>
                            <div class="admin-controls">
                                <span class="admin-btn" style="background: rgba(100, 100, 100, 0.8); cursor: default;">Legacy</span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="post-image">
                                <img src="../img/sossio.png" alt="Sossio">
                            </div>
                            <div class="post-details">
                                <h2>SOSSIOOOHH</h2>
                                <p class="post-author">by lacly</p>
                                <p class="post-description">Lui è sossio, uno sviluppatore di mod per mario kart. È anche un grande giocatore di wuthering waves e si fa tante seghe, qui avete una sua immagine mentre se la chilla con i piedi all'aria</p>
                                <div class="post-stats">
                                    <span class="post-date">Shitpost originale</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                </div>

                <div id="emptyState" class="empty-state fadeup" style="display: none;">
                    <div class="empty-card">
                        <div class="empty-icon">🤷‍♂️</div>
                        <h3>Nessun shitpost trovato</h3>
                        <p>Non ci sono ancora shitpost da mostrare. Sii il primo a condividere un meme!</p>
                        <?php if (isset($_SESSION['user_id'])): ?>
                        <button class="bottone" onclick="toggleAddPostForm()">Aggiungi il primo shitpost</button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isAdmin): ?>
                <div id="adminSection" class="admin-section" style="display: none;">
                    <h2 class="admin-title">👑 Pannello Admin - Shitpost in Attesa</h2>
                    <p class="admin-subtitle">Gestisci i shitpost in attesa di approvazione</p>
                    
                    <div id="loadingPendingState" class="loading-container">
                        <div class="loading_white">
                            <div class="loading__dot_white"></div>
                            <div class="loading__dot_white"></div>
                            <div class="loading__dot_white"></div>
                        </div>
                        <p class="testobianco" style="text-align: center; margin-top: 1rem;">Caricamento shitpost in attesa...</p>
                    </div>

                    <div id="pendingPostsContainer" class="posts-section" style="display: none;">
                        <div id="pendingPostsGrid" class="posts-grid">

                        </div>
                    </div>

                    <div id="noPendingPosts" class="empty-state" style="display: none;">
                        <div class="empty-card">
                            <div class="empty-icon">✅</div>
                            <h3>Nessun shitpost in attesa</h3>
                            <p>Tutti i shitpost sono stati gestiti.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php include '../includes/footer.php'; ?>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../js/modeChanger.js"></script>

        <script>
        let allPosts = [];
        let pendingPosts = [];
        const isAdmin = <?php echo json_encode($isAdmin); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            loadPosts();
            if (isAdmin) {
                loadPendingPosts();
            }
        });

        async function loadPosts() {
            try {
                const response = await fetch('../api/get_shitpost.php');
                const data = await response.json();
                
                if (Array.isArray(data) && data.length > 0) {
                    // Sort by date (newest first)
                    allPosts = data.sort((a, b) => new Date(b.data_creazione) - new Date(a.data_creazione));
                    displayPosts(allPosts);
                } else {
                    showEmptyState();
                }
            } catch (error) {
                console.error('Errore nel caricamento dei shitpost:', error);
                showEmptyState();
            }
        }

        async function loadPendingPosts() {
            if (!isAdmin) return;
            
            try {
                const response = await fetch('../api/get_pending_shitposts.php');
                const data = await response.json();
                
                document.getElementById('loadingPendingState').style.display = 'none';
                
                if (Array.isArray(data) && data.length > 0) {
                    pendingPosts = data.sort((a, b) => new Date(b.data_creazione) - new Date(a.data_creazione));
                    displayPendingPosts(pendingPosts);
                    document.getElementById('adminSection').style.display = 'block';
                } else {
                    document.getElementById('noPendingPosts').style.display = 'block';
                    document.getElementById('adminSection').style.display = 'block';
                }
            } catch (error) {
                console.error('Errore nel caricamento dei shitpost in attesa:', error);
                document.getElementById('loadingPendingState').style.display = 'none';
                document.getElementById('noPendingPosts').style.display = 'block';
                document.getElementById('adminSection').style.display = 'block';
            }
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
                return `
                    <div class="post-card fadeup" style="animation-delay: ${index * 0.1}s">
                        ${isAdmin ? `
                        <div class="admin-controls">
                            <button class="admin-btn delete" onclick="deletePost(${post.id}, false)" title="Elimina shitpost">
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
                        
                        <div class="post-image">
                            <img src="data:${post.tipo_foto_shitpost};base64,${post.foto_shitpost}" 
                                 alt="${post.titolo}">
                        </div>
                        <div class="post-details">
                            <h2>${post.titolo}</h2>
                            <p class="post-author">di ${post.username || 'Utente anonimo'}</p>
                            <p class="post-description">${post.descrizione}</p>
                            <div class="post-stats">
                                <span class="post-date">${formatDate(post.data_creazione)}</span>
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
                            <button class="admin-btn approve" onclick="changeApproval(${post.id}, 1)" title="Approva shitpost">
                                ✅
                            </button>
                            <button class="admin-btn delete" onclick="deletePost(${post.id}, true)" title="Elimina shitpost">
                                🗑️
                            </button>
                        </div>
                        <div class="approval-status pending">
                            ⏳ In attesa
                        </div>
                        
                        <div class="post-image">
                            <img src="data:${post.tipo_foto_shitpost};base64,${post.foto_shitpost}" 
                                 alt="${post.titolo}">
                        </div>
                        <div class="post-details">
                            <h2>${post.titolo}</h2>
                            <p class="post-author">di ${post.username || 'Utente anonimo'}</p>
                            <p class="post-description">${post.descrizione}</p>
                            <div class="post-stats">
                                <span class="post-date">${formatDate(post.data_creazione)}</span>
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
            if (!confirm(`Sei sicuro di voler ${action} questo shitpost?`)) {
                return;
            }
            
            try {
                const response = await fetch('../api/manage_shitpost_approval.php', {
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
                    alert(`Shitpost ${approval === 1 ? 'approvato' : 'disapprovato'} con successo!`);
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
            
            if (!confirm('Sei sicuro di voler eliminare questo shitpost? Questa azione non può essere annullata.')) {
                return;
            }
            
            try {
                const response = await fetch('../api/delete_shitpost.php', {
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
                    alert('Shitpost eliminato con successo!');
                } else {
                    alert('Errore: ' + (result.error || 'Errore sconosciuto'));
                }
            } catch (error) {
                console.error('Errore nell\'eliminazione del shitpost:', error);
                alert('Errore nella richiesta');
            }
        }

        function showEmptyState() {
            document.getElementById('loadingState').style.display = 'none';
            document.getElementById('postsContainer').style.display = 'none';
            document.getElementById('emptyState').style.display = 'block';
        }

        function toggleAddPostForm() {
            const form = document.getElementById('addPostForm');
            const button = document.getElementById('toggleButtonText');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                button.textContent = 'Annulla';
            } else {
                form.style.display = 'none';
                button.textContent = 'Aggiungi nuovo shitpost';
                document.getElementById('newPostForm').reset();
            }
        }

        document.getElementById('newPostForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            
            console.log('Invio nuovo shitpost...');
            for (let [key, value] of formData.entries()) {
                if (key === 'foto_shitpost') {
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
                const response = await fetch('../api/set_new_shitpost.php', {
                    method: 'POST',
                    body: formData
                });
                
                console.log('Response status:', response.status);
                console.log('Response headers:', Object.fromEntries(response.headers.entries()));
                
                const result = await response.json();
                console.log('Response data:', result);
                
                if (result.success) {
                    alert('Shitpost inviato con successo! Sarà visibile dopo l\'approvazione dell\'admin.');
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
                console.error('Errore nell\'invio del shitpost:', error);
                alert('Errore nell\'invio del shitpost. Controlla la console per dettagli.');
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
        </script>
    </body>
</html>
