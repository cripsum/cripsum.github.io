<?php
ini_set('session.gc_maxlifetime', 604800);
session_set_cookie_params(604800);
session_start();

require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isLoggedIn()) {
    header('Location: home');
    exit();
}

if (!isAdmin()) {
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
          COUNT(DISTINCT c.id) as character_count,
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
    <style>
        .admin-tabs {
            margin-bottom: 20px;
        }
        .tab-content {
            background: #fff;
            padding: 20px;
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
    </style>
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <?php include '../includes/impostazioni.php'; ?>

    <div class="container-fluid mt-5">
        <h1 class="text-center mb-4">Admin Panel</h1>
        
        <!-- Navigation Tabs -->
        <ul class="nav nav-tabs admin-tabs" id="adminTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button">Gestione Utenti</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="characters-tab" data-bs-toggle="tab" data-bs-target="#characters" type="button">Gestione Personaggi</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="achievements-tab" data-bs-toggle="tab" data-bs-target="#achievements" type="button">Gestione Achievement</button>
            </li>
        </ul>

        <div class="tab-content" id="adminTabContent">
            <!-- Users Management Tab -->
            <div class="tab-pane fade show active" id="users" role="tabpanel">
                <!-- Search and Filters -->
                <div class="search-filters">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Cerca per username o email" value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="filter_banned">
                                <option value="">Tutti gli utenti</option>
                                <option value="active" <?php echo $filter_banned === 'active' ? 'selected' : ''; ?>>Solo attivi</option>
                                <option value="banned" <?php echo $filter_banned === 'banned' ? 'selected' : ''; ?>>Solo bannati</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary">Filtra</button>
                        </div>
                        <div class="col-md-3">
                            <a href="?" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>

                <!-- Users Table -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Data Creazione</th>
                                <th>Ruolo</th>
                                <th>Stato</th>
                                <th>Stats</th>
                                <th>Azioni</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['username']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($row['data_creazione'])); ?></td>
                                <td><span class="badge bg-info"><?php echo $row['ruolo']; ?></span></td>
                                <td>
                                    <?php if ($row['isBannato']): ?>
                                        <span class="badge bg-danger">Bannato</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Attivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small>
                                        Personaggi: <?php echo $row['character_count']; ?><br>
                                        Achievement: <?php echo $row['achievement_count']; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-info btn-sm" onclick="viewUserDetails(<?php echo $row['id']; ?>)">Dettagli</button>
                                        <button class="btn btn-warning btn-sm" onclick="editUser(<?php echo $row['id']; ?>)">Modifica</button>
                                        
                                        <?php if (!$row['isBannato']): ?>
                                            <form method="POST" action="../api/ban_user.php" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Sei sicuro di voler bannare questo utente?')">Banna</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="../api/unban_user.php" style="display:inline;">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-success btn-sm">Sbanna</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-primary btn-sm" onclick="addCharacterToUser(<?php echo $row['id']; ?>)">+ Personaggio</button>
                                        <button class="btn btn-secondary btn-sm" onclick="addAchievementToUser(<?php echo $row['id']; ?>)">+ Achievement</button>
                                        <button class="btn btn-danger btn-sm" onclick="deleteUser(<?php echo $row['id']; ?>)">Elimina</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination-wrapper">
                    <nav>
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&filter_banned=<?php echo $filter_banned; ?>">Precedente</a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page-2); $i <= min($total_pages, $page+2); $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_banned=<?php echo $filter_banned; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&filter_banned=<?php echo $filter_banned; ?>">Successivo</a>
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
                    <div class="col-md-6">
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
                            <button type="submit" class="btn btn-success">Aggiungi Personaggio</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h3>Personaggi Esistenti</h3>
                        <div id="charactersList" class="table-responsive">
                            <!-- Characters list will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Achievements Management Tab -->
            <div class="tab-pane fade" id="achievements" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
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
                            <button type="submit" class="btn btn-success">Aggiungi Achievement</button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <h3>Achievement Esistenti</h3>
                        <div id="achievementsList" class="table-responsive">
                            <!-- Achievements list will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
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
</body>
</html></div></form></li></div></small></tbody>