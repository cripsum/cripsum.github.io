<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
ini_set('log_errors', 1);
error_reporting(1);
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: home');
    exit();
}

if (!isAdmin() && !isOwner()) {
    http_response_code(403);
    exit('Non autorizzato, scemo');
}

$user_id = $_SESSION['user_id'];

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Search and filter parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_banned = isset($_GET['filter_banned']) ? $_GET['filter_banned'] : '';

// Build query conditions
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter_banned === 'banned') {
    $where_conditions[] = "isBannato = 1";
} elseif ($filter_banned === 'active') {
    $where_conditions[] = "isBannato = 0";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total users for pagination
$count_query = "SELECT COUNT(*) as total FROM utenti $where_clause";
$count_stmt = $mysqli->prepare($count_query);
if (!empty($params)) {
    $count_stmt->bind_param(str_repeat('s', count($params)), ...$params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_users / $limit);

// Get users with stats
$query = "SELECT u.id, u.username, u.email, u.data_creazione, u.ruolo, u.isBannato,
          COUNT(DISTINCT c.personaggio_id) as character_count,
          COUNT(DISTINCT ua.achievement_id) as achievement_count
          FROM utenti u
          LEFT JOIN utenti_personaggi c ON u.id = c.utente_id
          LEFT JOIN utenti_achievement ua ON u.id = ua.utente_id
          $where_clause
          GROUP BY u.id
          ORDER BY u.data_creazione DESC
          LIMIT ? OFFSET ?";

$stmt = $mysqli->prepare($query);
$bind_params = array_merge($params, [$limit, $offset]);
$types = str_repeat('s', count($params)) . 'ii';
$stmt->bind_param($types, ...$bind_params);
$stmt->execute();
$result = $stmt->get_result();

// Get all characters for dropdown
$characters_query = "SELECT id, nome FROM personaggi ORDER BY nome";
$characters_result = $mysqli->query($characters_query);

// Get all achievements for dropdown
$achievements_query = "SELECT id, nome FROM achievement ORDER BY nome";
$achievements_result = $mysqli->query($achievements_query);
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <?php include '../includes/head-import.php'; ?>
    <title>Cripsum™ - Admin Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <style>
        .admin-tabs {
            margin-bottom: 20px;
        }
        .tab-content {
            background: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .user-stats {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .search-filters {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .pagination-wrapper {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .modal-body .form-group {
            margin-bottom: 15px;
        }
        .nav-link {
            color: white;
        }

        .nav-link:active{
            color:rgb(0, 0, 0);
        }

        .nav-link:hover {
            color: white;
        }

        textarea{
            color: black
        }
        
        /* Mobile responsive styles */
        @media (max-width: 768px) {
            .container-pannello {
                padding: 10px;
            }
            
            h1 {
                font-size: 1.5rem;
            }
            
            .tab-content {
                padding: 10px;
            }
            
            .search-filters {
                padding: 10px;
            }
            
            /* Mobile table styles */
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .mobile-table {
                display: none;
            }
            
            .desktop-table {
                display: table;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 3px;
            }
            
            .action-buttons .btn {
                font-size: 0.75rem;
                padding: 0.25rem 0.5rem;
            }
            
            /* Card layout for mobile */
            .user-card {
                background: #fff;
                border: 1px solid #dee2e6;
                border-radius: 8px;
                padding: 15px;
                margin-bottom: 15px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .user-card-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                border-bottom: 1px solid #eee;
                padding-bottom: 10px;
            }
            
            .user-card-body {
                margin-bottom: 15px;
            }
            
            .user-card-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }
            
            .user-card-actions .btn {
                font-size: 0.75rem;
                padding: 0.375rem 0.5rem;
            }
            
            .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .nav-tabs .nav-link {
                white-space: nowrap;
                font-size: 0.9rem;
                padding: 0.5rem 0.75rem;
            }
            
            .pagination {
                font-size: 0.8rem;
            }
            
            .pagination .page-link {
                padding: 0.25rem 0.5rem;
            }
        }
        
        @media (min-width: 769px) {
            .mobile-table {
                display: none;
            }
            
            .desktop-table {
                display: table;
            }
        }
        
        @media (max-width: 576px) {
            .search-filters .row {
                gap: 10px;
            }
            
            .search-filters .col-md-2,
            .search-filters .col-md-3,
            .search-filters .col-md-4 {
                width: 100%;
                margin-bottom: 10px;
            }
            
            .user-card-actions {
                grid-template-columns: 1fr;
            }
            
            .tab-content {
                padding: 8px;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="container-fluid container-pannello mt-3" style="margin-top: 7rem;">
        <h1 class="text-center mb-4 testobianco" style="margin-top: 7rem;">Pannello Admin</h1>
    
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs admin-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button">Gestione Utenti</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="characters-tab" data-bs-toggle="tab" data-bs-target="#characters" type="button">Gestione Personaggi</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="achievements-tab" data-bs-toggle="tab" data-bs-target="#achievements" type="button">Gstione Achievement</button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabContent">
            <!-- Users Management Tab -->
            <div class="tab-pane fade show active" id="users" role="tabpanel">
                <!-- Search and Filters -->
                <div class="search-filters">
                    <form method="GET" class="row g-3">
                        <div class="col-12 col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Cerca per username o email" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-12 col-md-3">
                            <select class="form-select" name="filter_banned">
                                <option value="">Tutti gli utenti</option>
                                <option value="active" <?php echo $filter_banned === 'active' ? 'selected' : ''; ?>>Solo attivi</option>
                                <option value="banned" <?php echo $filter_banned === 'banned' ? 'selected' : ''; ?>>Solo bannati</option>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filtra</button>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="?" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Desktop Table -->
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-striped desktop-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Data</th>
                                <th>Ruolo</th>
                                <th>Stato</th>
                                <th>Stats</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td class="d-none d-lg-table-cell"><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['data_creazione'])); ?></td>
                                <td><span class="badge bg-info"><?php echo $row['ruolo']; ?></span></td>
                                <td>
                                    <?php if ($row['isBannato']): ?>
                                        <span class="badge bg-danger">Bannato</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Attivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <small>
                                        Personaggi: <?php echo $row['character_count']; ?><br>
                                        Achievements: <?php echo $row['achievement_count']; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-info btn-sm" onclick="viewUserDetails(<?php echo $row['id']; ?>)">Dettagli</button>
                                        <button class="btn btn-warning btn-sm" onclick="editUser(<?php echo $row['id']; ?>)">modifica</button>
                                        
                                        <?php if (!$row['isBannato']): ?>
                                            <form method="POST" action="../api/ban_user.php" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Bannare utente?')">Banna</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="../api/unban_user.php" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm">Sbanna</button>
                                            </form>
                                        <?php endif; ?>
                                        <button class="btn btn-primary btn-sm" onclick="addCharacterToUser(<?php echo $row['id']; ?>)">+ Personaggio</button>
                                        <button class="btn btn-secondary btn-sm" onclick="addAchievementToUser(<?php echo $row['id']; ?>)">+ Achievement</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Cards -->
                <div class="d-md-none">
                    <?php $result->data_seek(0); while ($row = $result->fetch_assoc()): ?>
                    <div class="user-card">
                        <div class="user-card-header">
                            <div>
                                <strong><?php echo htmlspecialchars($row['username']); ?></strong>
                                <span class="badge bg-info ms-2"><?php echo $row['ruolo']; ?></span>
                            </div>
                            <div>
                                <?php if ($row['isBannato']): ?>
                                    <span class="badge bg-danger">Bannato</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Attivo</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="user-card-body">
                            <div><small class="text-muted">ID:</small> <?php echo $row['id']; ?></div>
                            <div><small class="text-muted">Email:</small> <?php echo htmlspecialchars($row['email']); ?></div>
                            <div><small class="text-muted">Data:</small> <?php echo date('d/m/Y', strtotime($row['data_creazione'])); ?></div>
                            <div><small class="text-muted">Personaggi:</small> <?php echo $row['character_count']; ?> | <small class="text-muted">Achievement:</small> <?php echo $row['achievement_count']; ?></div>
                        </div>
                        
                        <div class="user-card-actions">
                            <button class="btn btn-info btn-sm" onclick="viewUserDetails(<?php echo $row['id']; ?>)">Dettagli</button>
                            <button class="btn btn-warning btn-sm" onclick="editUser(<?php echo $row['id']; ?>)">Modifica</button>
                            
                            <?php if (!$row['isBannato']): ?>
                                <form method="POST" action="../api/ban_user.php" class="d-inline w-100">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Bannare utente?')">Banna</button>
                                </form>
                            <?php else: ?>
                                <form method="POST" action="../api/unban_user.php" class="d-inline w-100">
                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                    <button type="submit" class="btn btn-success btn-sm w-100">Sbanna</button>
                                </form>
                            <?php endif; ?>
                            
                            <button class="btn btn-primary btn-sm" onclick="addCharacterToUser(<?php echo $row['id']; ?>)">+ Personaggio</button>
                            <button class="btn btn-secondary btn-sm" onclick="addAchievementToUser(<?php echo $row['id']; ?>)">+ Achievement</button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <nav>
                        <ul class="pagination pagination-sm">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&filter_banned=<?php echo $filter_banned; ?>">‹</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-1); $i <= min($total_pages, $page+1); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_banned=<?php echo $filter_banned; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&filter_banned=<?php echo $filter_banned; ?>">›</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

            <!-- Characters Management Tab -->
            <div class="tab-pane fade" id="characters" role="tabpanel">
                <div class="row">
                    <div class="col-12 col-lg-6 mb-4">
                        <h3>Aggiungi Nuovo Personaggio</h3>
                        <form id="addCharacterForm">
                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" class="form-control" name="nome" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrizione</label>
                                <textarea class="form-control" name="descrizione" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Immagine URL</label>
                                <input type="url" class="form-control" name="immagine">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rarità</label>
                                <select class="form-select" name="rarita">
                                    <option value="comune">Comune</option>
                                    <option value="raro">Raro</option>
                                    <option value="epico">Epico</option>
                                    <option value="leggendario">Leggendario</option>
                                    <option value="mitico">Mitico</option>
                                    <option value="speciale">Speciale</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success w-100">Aggiungi Personaggio</button>
                        </form>
                    </div>
                    <div class="col-12 col-lg-6">
                        <h3>Personaggi Esistenti</h3>
                        <div id="charactersList" class="table-responsive">
                            <!-- Characters list will be loaded here -->
                            <?php
                            $characters_list_query = "SELECT id, nome, categoria, img_url, rarità FROM personaggi ORDER BY nome";
                            $characters_list_result = $mysqli->query($characters_list_query);
                            if ($characters_list_result && $characters_list_result->num_rows > 0): ?>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Categoria</th>
                                            <th>Rarità</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($character = $characters_list_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $character['id']; ?></td>
                                            <td>
                                                <?php if (!empty($character['immagine'])): ?>
                                                    <img src="/img/<?php echo htmlspecialchars($character['immagine']); ?>" alt="Immagine" style="width: 20px; height: 20px; margin-right: 5px;">
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($character['nome']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($character['descrizione']); ?></td>
                                            <td><span class="badge bg-info"><?php echo htmlspecialchars($character['rarita']); ?></span></td>
                                            <td>
                                                <button class="btn btn-warning btn-sm" onclick="editCharacter(<?php echo $character['id']; ?>)">Modifica</button>
                                                <button class="btn btn-danger btn-sm" onclick="deleteCharacter(<?php echo $character['id']; ?>)">Elimina</button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted">Nessun personaggio trovato.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Achievements Management Tab -->
            <div class="tab-pane fade" id="achievements" role="tabpanel">
                <div class="row">
                    <div class="col-12 col-lg-6 mb-4">
                        <h3>Aggiungi Nuovo Achievement</h3>
                        <form id="addAchievementForm">
                            <div class="mb-3">
                                <label class="form-label">Nome</label>
                                <input type="text" class="form-control" name="nome" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descrizione</label>
                                <textarea class="form-control" name="descrizione" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Icona URL</label>
                                <input type="url" class="form-control" name="icona">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Punti</label>
                                <input type="number" class="form-control" name="punti" min="0" value="10">
                            </div>
                            <button type="submit" class="btn btn-success w-100">Aggiungi Achievement</button>
                        </form>
                    </div>
                    <div class="col-12 col-lg-6">
                        <h3>Achievement Esistenti</h3>
                        <div id="achievementsList" class="table-responsive">
                            <!-- Achievements list will be loaded here -->
                            <?php
                            // Load achievements from database
                            $achievements_list_query = "SELECT id, nome, descrizione, img_url, punti FROM achievement ORDER BY nome";
                            $achievements_list_result = $mysqli->query($achievements_list_query);

                            if ($achievements_list_result && $achievements_list_result->num_rows > 0): ?>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nome</th>
                                            <th>Descrizione</th>
                                            <th>Punti</th>
                                            <th>Azioni</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($achievement = $achievements_list_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $achievement['id']; ?></td>
                                            <td>
                                                <?php if (!empty($achievement['icona'])): ?>
                                                    <img src="/img/<?php echo htmlspecialchars($achievement['icona']); ?>" alt="Icona" style="width: 20px; height: 20px; margin-right: 5px;">
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($achievement['nome']); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($achievement['descrizione']); ?></td>
                                            <td><span class="badge bg-info"><?php echo $achievement['punti']; ?> pts</span></td>
                                            <td>
                                                <button class="btn btn-warning btn-sm" onclick="editAchievement(<?php echo $achievement['id']; ?>)">Modifica</button>
                                                <button class="btn btn-danger btn-sm" onclick="deleteAchievement(<?php echo $achievement['id']; ?>)">Elimina</button>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="text-muted">Nessun achievement trovato.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals remain the same -->
    <!-- User Details Modal -->
    <div class="modal fade" id="userDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Dettagli Utente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailsContent">
                    <!-- User details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Modifica Utente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" name="user_id" id="editUserId">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="editUsername" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Ruolo</label>
                            <select class="form-select" name="ruolo" id="editRuolo">
                                <option value="utente">Utente</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                    <button type="button" class="btn btn-primary" onclick="saveUserChanges()">Salva Modifiche</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Character to User Modal -->
    <div class="modal fade" id="addCharacterModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Personaggio all'Utente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addCharacterToUserForm">
                        <input type="hidden" name="user_id" id="characterUserId">
                        <div class="mb-3">
                            <label class="form-label">Personaggio</label>
                            <select class="form-select" name="character_id" required>
                                <option value="">Seleziona un personaggio</option>
                                <?php 
                                $characters_result->data_seek(0);
                                while ($char = $characters_result->fetch_assoc()): ?>
                                    <option value="<?php echo $char['id']; ?>"><?php echo htmlspecialchars($char['nome']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                    <button type="button" class="btn btn-primary" onclick="saveCharacterToUser()">Aggiungi</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Achievement to User Modal -->
    <div class="modal fade" id="addAchievementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Aggiungi Achievement all'Utente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addAchievementToUserForm">
                        <input type="hidden" name="user_id" id="achievementUserId">
                        <div class="mb-3">
                            <label class="form-label">Achievement</label>
                            <select class="form-select" name="achievement_id" required>
                                <option value="">Seleziona un achievement</option>
                                <?php 
                                $achievements_result->data_seek(0);
                                while ($ach = $achievements_result->fetch_assoc()): ?>
                                    <option value="<?php echo $ach['id']; ?>"><?php echo htmlspecialchars($ach['nome']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Chiudi</button>
                    <button type="button" class="btn btn-primary" onclick="saveAchievementToUser()">Aggiungi</button>
                </div>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    <script src="../js/admin.js"></script>
        <footer class="my-5 pt-5 text-muted text-center text-small fadeup">
            <p class="mb-1 testobianco">Copyright © 2021-2025 Cripsum™. Tutti i diritti riservati.</p>
            <ul class="list-inline">
                <li class="list-inline-item"><a href="privacy"class="linkbianco">Privacy</a></li>
                <li class="list-inline-item"><a href="tos"class="linkbianco">Termini</a></li>
                <li class="list-inline-item"><a href="supporto"class="linkbianco">Supporto</a></li>
            </ul>
        </footer>
        <script
            src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"
        ></script>

</body>
</html>