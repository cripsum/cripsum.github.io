<?php
declare(strict_types=1);
require_once __DIR__ . '/game_config.php';




function gd_json(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function gd_ok(array $data = []): void { gd_json(['success' => true] + $data); }
function gd_fail(string $msg, int $status = 400): void { gd_json(['success' => false, 'message' => $msg], $status); }
function gd_input(): array {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    return is_array($json) ? $json : ($_POST ?: $_GET ?: []);
}
function gd_user_id(): int {
    foreach (['user_id','utente_id','id'] as $k) if (!empty($_SESSION[$k])) return (int)$_SESSION[$k];
    if (!empty($_SESSION['user']['id'])) return (int)$_SESSION['user']['id'];
    return 0;
}
function gd_require_login(): int {
    if (!function_exists('isLoggedIn') || !isLoggedIn()) gd_fail('Devi essere loggato.', 401);
    $id = gd_user_id();
    if ($id <= 0) gd_fail('Sessione non valida.', 401);
    return $id;
}
function gd_qcol(string $c): string { return '`'.str_replace('`','``',$c).'`'; }
function gd_cols(mysqli $mysqli, string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $res = $mysqli->query("SHOW COLUMNS FROM `$safe`");
    $cols = [];
    if ($res) while ($r = $res->fetch_assoc()) $cols[] = (string)$r['Field'];
    return $cache[$table] = $cols;
}
function gd_has_col(mysqli $m, string $table, string $col): bool { return in_array($col, gd_cols($m, $table), true); }
function gd_first(array $cols, array $names): ?string { foreach ($names as $n) if (in_array($n, $cols, true)) return $n; return null; }
function gd_char_cols(mysqli $m): array {
    $c = gd_cols($m, 'personaggi');
    return [
        'id' => gd_first($c, ['id']),
        'name' => gd_first($c, ['nome','name','title']),
        'image' => gd_first($c, ['img_url','image_url','img','image']),
        'rarity' => gd_first($c, ['rarità','rarita','rarity']),
        'category' => gd_first($c, ['categoria','category'])
    ];
}
function gd_inv_cols(mysqli $m): array {
    $c = gd_cols($m, 'utenti_personaggi');
    return [
        'user' => gd_first($c, ['utente_id','user_id']),
        'character' => gd_first($c, ['personaggio_id','character_id']),
        'qty' => gd_first($c, ['quantità','quantita','quantity','qty'])
    ];
}
function gd_room_code(): string { return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)); }
function gd_mode(string $mode): string { return in_array($mode, ['casual','ranked','private','bot'], true) ? $mode : 'casual'; }
function gd_bot_id(): int { return 0; }
function gd_is_bot_match(array $match): bool { return (string)($match['mode'] ?? '') === 'bot'; }
function gd_bot_public(): array {
    return [
        'id' => gd_bot_id(),
        'username' => 'Cripsum Bot',
        'pfp_url' => '/img/Susremaster.png',
        'rating' => 1000,
        'rank' => gd_rank_from_rating(1000),
        'wins' => 0,
        'losses' => 0,
        'season_points' => 0,
        'best_streak' => 0,
        'games_played' => 0,
    ];
}
function gd_opponent_id(array $match, int $uid): int {
    if (gd_is_bot_match($match)) {
        return ((int)$match['player1_id'] === $uid) ? gd_bot_id() : (int)$match['player1_id'];
    }
    return ((int)$match['player1_id'] === $uid) ? (int)$match['player2_id'] : (int)$match['player1_id'];
}
function gd_rarity_key(?string $r): string {
    $v = strtolower(trim((string)$r));
    $v = str_replace(['à','á',' ','-'], ['a','a','','_'], $v);
    $map = ['comune'=>'comune','raro'=>'raro','epico'=>'epico','leggendario'=>'leggendario','mitico'=>'leggendario','speciale'=>'speciale','segreto'=>'segreto','theone'=>'theone','the_one'=>'theone'];
    return $map[$v] ?? 'comune';
}
function gd_rank_from_rating(int $rating): array {
    if ($rating >= 1900) return ['key'=>'leggenda','label'=>'Leggenda','next'=>null,'min'=>1900,'max'=>null,'range'=>'1900+'];
    if ($rating >= 1600) return ['key'=>'campione','label'=>'Campione','next'=>1900,'min'=>1600,'max'=>1899,'range'=>'1600-1899'];
    if ($rating >= 1400) return ['key'=>'diamante','label'=>'Diamante','next'=>1600,'min'=>1400,'max'=>1599,'range'=>'1400-1599'];
    if ($rating >= 1200) return ['key'=>'oro','label'=>'Oro','next'=>1400,'min'=>1200,'max'=>1399,'range'=>'1200-1399'];
    if ($rating >= 1000) return ['key'=>'argento','label'=>'Argento','next'=>1200,'min'=>1000,'max'=>1199,'range'=>'1000-1199'];
    return ['key'=>'bronzo','label'=>'Bronzo','next'=>1000,'min'=>0,'max'=>999,'range'=>'0-999'];
}
function gd_base_stats(string $rarity): array {
    $s = [
        'comune'=>[92,18,8,10,4,2,2], 'raro'=>[100,21,10,11,4,2,2], 'epico'=>[108,24,12,12,5,2,2],
        'leggendario'=>[116,27,14,13,5,3,2], 'speciale'=>[122,29,15,14,5,3,3],
        'segreto'=>[128,31,16,15,6,3,3], 'theone'=>[134,33,17,16,6,4,3]
    ];
    $v = $s[gd_rarity_key($rarity)] ?? $s['comune'];
    return ['hp'=>$v[0], 'attack'=>$v[1], 'defense'=>$v[2], 'speed'=>$v[3], 'max_energy'=>$v[4], 'special_cost'=>$v[5], 'special_cooldown'=>$v[6], 'special_name'=>'Colpo speciale'];
}
function gd_character(mysqli $m, int $pid): ?array {
    $c = gd_char_cols($m);
    if (!$c['id'] || !$c['name']) return null;
    $fields = ['p.'.gd_qcol($c['id']).' id','p.'.gd_qcol($c['name']).' nome'];
    $fields[] = $c['image'] ? 'p.'.gd_qcol($c['image']).' img_url' : "'' img_url";
    $fields[] = $c['rarity'] ? 'p.'.gd_qcol($c['rarity']).' rarita' : "'comune' rarita";
    $fields[] = $c['category'] ? 'p.'.gd_qcol($c['category']).' categoria' : "'' categoria";
    $fields[] = "p.ruolo AS ruolo";
    
    $sql = 'SELECT '.implode(',', $fields).' FROM personaggi p WHERE p.'.gd_qcol($c['id']).'=? LIMIT 1';
    $st = $m->prepare($sql); if (!$st) return null;
    $st->bind_param('i', $pid); $st->execute(); $row = $st->get_result()->fetch_assoc(); $st->close();
    return $row ?: null;
}
function gd_stats(mysqli $m, int $pid): array {
    $ch = gd_character($m, $pid);
    $nome = $ch['nome'] ?? 'Personaggio';
    $rarity = $ch['rarita'] ?? 'comune';
    $role = $ch['ruolo'] ?? 'DPS';
    
    $cfg = gd_get_character_config($pid, $rarity, $nome, $role);
    $st = gd_calculate_character_stats($rarity, $role);
    
    $rKey = strtolower(trim($rarity));
    $max_energy = 5;
    if (strpos($rKey, 'one') !== false) $max_energy = 6;
    elseif (strpos($rKey, 'secret') !== false || strpos($rKey, 'segreto') !== false) $max_energy = 6;
    elseif (strpos($rKey, 'comune') !== false) $max_energy = 4;
    elseif (strpos($rKey, 'raro') !== false) $max_energy = 4;
    $st['max_energy'] = $max_energy;
    
    $st['role'] = $role;
    $st['special_name'] = $cfg['special_name'];
    $st['special_cost'] = $cfg['special_cost'];
    $st['special_cooldown'] = $cfg['special_cooldown'];
    $st['passive_name'] = $cfg['passive_name'];
    $st['passive_desc'] = $cfg['passive_desc'];
    
    $q = $m->prepare('SELECT hp,attack,defense,speed,max_energy,special_name,special_cost,special_cooldown FROM game_card_stats WHERE personaggio_id=? LIMIT 1');
    if ($q) {
        $q->bind_param('i', $pid); $q->execute(); $row = $q->get_result()->fetch_assoc(); $q->close();
        if ($row) {
            foreach (['hp','attack','defense','speed','max_energy'] as $k) if ($row[$k] !== null) $st[$k] = max(1, (int)$row[$k]);
            if ($row['special_cost'] !== null) $st['special_cost'] = max(1, (int)$row['special_cost']);
            if ($row['special_cooldown'] !== null) $st['special_cooldown'] = max(1, (int)$row['special_cooldown']);
            if (!empty($row['special_name'])) $st['special_name'] = $row['special_name'];
        }
    }
    return $st;
}
function gd_owns(mysqli $m, int $uid, int $pid): bool {
    $i = gd_inv_cols($m);
    if (!$i['user'] || !$i['character']) return false;
    $qty = $i['qty'] ? gd_qcol($i['qty']) : '1';
    $sql = 'SELECT '.$qty.' qty FROM utenti_personaggi WHERE '.gd_qcol($i['user']).'=? AND '.gd_qcol($i['character']).'=? LIMIT 1';
    $st = $m->prepare($sql); if (!$st) return false;
    $st->bind_param('ii', $uid, $pid); $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close();
    return $r && (int)($r['qty'] ?? 1) > 0;
}
function gd_match(mysqli $m, int $mid): ?array {
    $st = $m->prepare('SELECT * FROM game_matches WHERE id=? LIMIT 1'); if (!$st) gd_fail('Query match non valida.', 500);
    $st->bind_param('i', $mid); $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close(); return $r ?: null;
}
function gd_is_player(array $match, int $uid): bool { return (int)$match['player1_id'] === $uid || (int)($match['player2_id'] ?? 0) === $uid; }
function gd_require_match(mysqli $m, int $mid, int $uid): array {
    $match = gd_match($m, $mid); if (!$match) gd_fail('Partita non trovata.', 404);
    if (!gd_is_player($match, $uid)) gd_fail('Non fai parte della partita.', 403);
    return $match;
}
function gd_cards(mysqli $m, int $mid): array {
    $st = $m->prepare('SELECT * FROM game_match_cards WHERE match_id=? ORDER BY user_id, slot_index');
    if (!$st) gd_fail('Query carte non valida.', 500);
    $st->bind_param('i', $mid); $st->execute(); $res = $st->get_result(); $out = [];
    while ($r = $res->fetch_assoc()) {
        $r['character'] = gd_character($m, (int)$r['personaggio_id']) ?: ['id'=>(int)$r['personaggio_id'],'nome'=>'Personaggio','img_url'=>'','rarita'=>'comune','categoria'=>''];
        $r['shield'] = (int)($r['shield'] ?? 0);
        $r['crit_rate'] = (int)($r['crit_rate'] ?? 5);
        $r['crit_dmg'] = (int)($r['crit_dmg'] ?? 150);
        $r['status_effects'] = json_decode($r['status_effects'] ?: '[]', true);
        $out[] = $r;
    }
    $st->close(); return $out;
}
function gd_active(mysqli $m, int $mid, int $uid): ?array {
    $st = $m->prepare('SELECT * FROM game_match_cards WHERE match_id=? AND user_id=? AND is_active=1 AND is_ko=0 LIMIT 1');
    $st->bind_param('ii', $mid, $uid); $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close(); return $r ?: null;
}
function gd_first_alive(mysqli $m, int $mid, int $uid): ?array {
    $st = $m->prepare('SELECT * FROM game_match_cards WHERE match_id=? AND user_id=? AND is_ko=0 ORDER BY slot_index LIMIT 1');
    $st->bind_param('ii', $mid, $uid); $st->execute(); $r = $st->get_result()->fetch_assoc(); $st->close(); return $r ?: null;
}
function gd_alive_count(mysqli $m, int $mid, int $uid): int {
    $st = $m->prepare('SELECT COUNT(*) total FROM game_match_cards WHERE match_id=? AND user_id=? AND is_ko=0');
    $st->bind_param('ii', $mid, $uid); $st->execute(); $n = (int)($st->get_result()->fetch_assoc()['total'] ?? 0); $st->close(); return $n;
}
function gd_set_active(mysqli $m, int $mid, int $uid, int $cid): void {
    $st = $m->prepare('UPDATE game_match_cards SET is_active=0 WHERE match_id=? AND user_id=?'); $st->bind_param('ii',$mid,$uid); $st->execute(); $st->close();
    $st = $m->prepare('UPDATE game_match_cards SET is_active=1 WHERE id=? AND match_id=? AND user_id=? AND is_ko=0'); $st->bind_param('iii',$cid,$mid,$uid); $st->execute(); $st->close();
}
function gd_log(mysqli $m, int $mid, int $uid, int $turn, string $type, ?int $actor, ?int $target, int $dmg, string $msg): void {
    $st = $m->prepare('INSERT INTO game_match_actions (match_id,user_id,turn_number,action_type,actor_card_id,target_card_id,damage,message) VALUES (?,?,?,?,?,?,?,?)');
    if ($st) { $st->bind_param('iiisiiis',$mid,$uid,$turn,$type,$actor,$target,$dmg,$msg); $st->execute(); $st->close(); }
}
function gd_ensure_stats_row(mysqli $m, int $uid): array {
    $q = $m->prepare('INSERT IGNORE INTO game_player_stats (user_id) VALUES (?)');
    if ($q) { $q->bind_param('i',$uid); $q->execute(); $q->close(); }
    $q = $m->prepare('SELECT * FROM game_player_stats WHERE user_id=? LIMIT 1');
    $q->bind_param('i',$uid); $q->execute(); $row = $q->get_result()->fetch_assoc(); $q->close();
    if (!$row) $row = ['rating'=>1000,'wins'=>0,'losses'=>0,'season_points'=>0,'current_streak'=>0,'best_streak'=>0,'games_played'=>0];
    return $row;
}
function gd_rating_delta(int $winnerRating, int $loserRating): array {
    $expected = 1 / (1 + pow(10, ($loserRating - $winnerRating) / 400));
    $win = (int)round(18 + (1 - $expected) * 22);
    $win = max(18, min(40, $win));
    $loss = -max(12, min(32, (int)round($win * 0.72)));
    return [$win, $loss];
}
function gd_finish(mysqli $m, array $match, int $winner, int $loser): void {
    $mid = (int)$match['id'];
    $wDelta = 0; $lDelta = 0; $wAfter = null; $lAfter = null;
    if ($match['mode'] === 'ranked') {
        $ws = gd_ensure_stats_row($m, $winner);
        $ls = gd_ensure_stats_row($m, $loser);
        $wr = (int)($ws['rating'] ?? 1000);
        $lr = (int)($ls['rating'] ?? 1000);
        [$wDelta, $lDelta] = gd_rating_delta($wr, $lr);
        $wAfter = $wr + $wDelta;
        $lAfter = max(100, $lr + $lDelta);
        $q=$m->prepare('UPDATE game_player_stats SET wins=wins+1,games_played=games_played+1,rating=?,season_points=season_points+?,current_streak=current_streak+1,best_streak=GREATEST(best_streak,current_streak+1) WHERE user_id=?');
        $q->bind_param('iii',$wAfter,$wDelta,$winner); $q->execute(); $q->close();
        $q=$m->prepare('UPDATE game_player_stats SET losses=losses+1,games_played=games_played+1,rating=?,current_streak=0 WHERE user_id=?');
        $q->bind_param('ii',$lAfter,$loser); $q->execute(); $q->close();
    }
    if (gd_has_col($m, 'game_matches', 'winner_rating_delta')) {
        $st = $m->prepare("UPDATE game_matches SET status='finished', winner_id=?, loser_id=?, winner_rating_delta=?, loser_rating_delta=?, winner_rating_after=?, loser_rating_after=?, finished_at=NOW(), updated_at=NOW() WHERE id=?");
        $st->bind_param('iiiiiii',$winner,$loser,$wDelta,$lDelta,$wAfter,$lAfter,$mid);
    } else {
        $st = $m->prepare("UPDATE game_matches SET status='finished', winner_id=?, loser_id=?, finished_at=NOW(), updated_at=NOW() WHERE id=?");
        $st->bind_param('iii',$winner,$loser,$mid);
    }
    $st->execute(); $st->close();
}
function gd_user_public(mysqli $m, int $uid): array {
    if ($uid === gd_bot_id()) return gd_bot_public();
    $username = 'Utente';
    $q = $m->prepare('SELECT username FROM utenti WHERE id=? LIMIT 1');
    if ($q) { $q->bind_param('i',$uid); $q->execute(); $row=$q->get_result()->fetch_assoc(); $q->close(); if($row && !empty($row['username'])) $username=$row['username']; }
    $stats = gd_ensure_stats_row($m, $uid);
    $rating = (int)($stats['rating'] ?? 1000);
    return ['id'=>$uid,'username'=>$username,'pfp_url'=>'/includes/get_pfp.php?id='.$uid,'rating'=>$rating,'rank'=>gd_rank_from_rating($rating),'wins'=>(int)($stats['wins']??0),'losses'=>(int)($stats['losses']??0),'season_points'=>(int)($stats['season_points']??0),'best_streak'=>(int)($stats['best_streak']??0),'games_played'=>(int)($stats['games_played']??0)];
}

function gd_chat_messages(mysqli $m, int $mid): array {
    $out = [];
    if (!gd_has_col($m, 'game_match_chat', 'message')) {
        return $out;
    }

    $st = $m->prepare('
        SELECT c.id, c.match_id, c.user_id, c.message, c.created_at, u.username
        FROM game_match_chat c
        LEFT JOIN utenti u ON u.id = c.user_id
        WHERE c.match_id = ?
        ORDER BY c.id DESC
        LIMIT 40
    ');

    if (!$st) {
        return $out;
    }

    $st->bind_param('i', $mid);
    $st->execute();
    $res = $st->get_result();

    while ($r = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int)$r['id'],
            'user_id' => (int)$r['user_id'],
            'username' => $r['username'] ?: 'Utente',
            'message' => (string)$r['message'],
            'created_at' => (string)$r['created_at'],
        ];
    }

    $st->close();

    return array_reverse($out);
}


function gd_spectator_seen_col(mysqli $m): ?string {
    $cols = gd_cols($m, 'game_spectators');
    return gd_first($cols, ['last_seen_at', 'last_seen', 'updated_at']);
}

function gd_touch_spectator(mysqli $m, int $mid, int $uid): void {
    if (!gd_has_col($m, 'game_spectators', 'match_id') || !gd_has_col($m, 'game_spectators', 'user_id')) {
        return;
    }

    $seenCol = gd_spectator_seen_col($m);
    if ($seenCol) {
        $qc = gd_qcol($seenCol);
        $sql = "INSERT INTO game_spectators (match_id, user_id, {$qc}) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE {$qc}=NOW()";
        $st = $m->prepare($sql);
        if ($st) {
            $st->bind_param('ii', $mid, $uid);
            $st->execute();
            $st->close();
        }
        return;
    }

    $st = $m->prepare('INSERT IGNORE INTO game_spectators (match_id, user_id) VALUES (?, ?)');
    if ($st) {
        $st->bind_param('ii', $mid, $uid);
        $st->execute();
        $st->close();
    }
}

function gd_spectator_count(mysqli $m, int $mid): int {
    if (!gd_has_col($m, 'game_spectators', 'match_id')) {
        return 0;
    }

    $seenCol = gd_spectator_seen_col($m);
    if ($seenCol) {
        $qc = gd_qcol($seenCol);
        $st = $m->prepare("SELECT COUNT(*) total FROM game_spectators WHERE match_id = ? AND {$qc} >= DATE_SUB(NOW(), INTERVAL 35 SECOND)");
    } else {
        $st = $m->prepare('SELECT COUNT(*) total FROM game_spectators WHERE match_id = ?');
    }

    if (!$st) {
        return 0;
    }

    $st->bind_param('i', $mid);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();

    return (int)($row['total'] ?? 0);
}

function gd_reactions(mysqli $m, int $mid): array {
    $out = [];

    if (!gd_has_col($m, 'game_match_reactions', 'reaction')) {
        return $out;
    }

    $st = $m->prepare('
        SELECT r.id, r.user_id, r.reaction, r.created_at, u.username
        FROM game_match_reactions r
        LEFT JOIN utenti u ON u.id = r.user_id
        WHERE r.match_id = ?
        ORDER BY r.id DESC
        LIMIT 18
    ');

    if (!$st) {
        return $out;
    }

    $st->bind_param('i', $mid);
    $st->execute();
    $res = $st->get_result();

    while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id' => (int)$row['id'],
            'user_id' => (int)$row['user_id'],
            'username' => $row['username'] ?: 'Spettatore',
            'reaction' => (string)$row['reaction'],
            'created_at' => (string)$row['created_at'],
        ];
    }

    $st->close();

    return array_reverse($out);
}

function gd_state(mysqli $m, array $match, int $viewer): array {
    $mid = (int)$match['id'];
    $cards = gd_cards($m, $mid);
    $st = $m->prepare('SELECT a.*, u.username FROM game_match_actions a LEFT JOIN utenti u ON u.id=a.user_id WHERE a.match_id=? ORDER BY a.id DESC LIMIT 24');
    $actions = [];
    if ($st) { $st->bind_param('i',$mid); $st->execute(); $res=$st->get_result(); while($r=$res->fetch_assoc()) $actions[]=$r; $st->close(); }
    $isBot = gd_is_bot_match($match);
    $p1 = gd_user_public($m, (int)$match['player1_id']);
    $p2 = $isBot ? gd_bot_public() : (!empty($match['player2_id']) ? gd_user_public($m, (int)$match['player2_id']) : null);
    $rankedResult = null;
    if ($match['status'] === 'finished' && $match['mode'] === 'ranked') {
        $isWinner = (int)$match['winner_id'] === $viewer;
        $rankedResult = [
            'viewer_delta' => $isWinner ? (int)($match['winner_rating_delta'] ?? 0) : (int)($match['loser_rating_delta'] ?? 0),
            'opponent_delta' => $isWinner ? (int)($match['loser_rating_delta'] ?? 0) : (int)($match['winner_rating_delta'] ?? 0),
            'viewer_rating_after' => $isWinner ? (int)($match['winner_rating_after'] ?? 0) : (int)($match['loser_rating_after'] ?? 0),
            'opponent_rating_after' => $isWinner ? (int)($match['loser_rating_after'] ?? 0) : (int)($match['winner_rating_after'] ?? 0),
        ];
        $rankedResult['viewer_rank_after'] = gd_rank_from_rating((int)$rankedResult['viewer_rating_after']);
        $rankedResult['opponent_rank_after'] = gd_rank_from_rating((int)$rankedResult['opponent_rating_after']);
    }
    return ['id'=>$mid,'room_code'=>$match['room_code'],'status'=>$match['status'],'mode'=>$match['mode'],'player1_id'=>(int)$match['player1_id'],'player2_id'=>$isBot?gd_bot_id():($match['player2_id']?(int)$match['player2_id']:null),'players'=>['player1'=>$p1,'player2'=>$p2],'player1_ready'=>(int)$match['player1_ready'],'player2_ready'=>$isBot?1:(int)$match['player2_ready'],'current_turn_user_id'=>$match['current_turn_user_id']!==null?(int)$match['current_turn_user_id']:null,'winner_id'=>$match['winner_id']!==null?(int)$match['winner_id']:null,'loser_id'=>$match['loser_id']!==null?(int)$match['loser_id']:null,'turn_number'=>(int)$match['turn_number'],'viewer_id'=>$viewer,'viewer_role'=>gd_is_player($match,$viewer)?'player':'spectator','cards'=>$cards,'actions'=>array_reverse($actions),'chat'=>gd_chat_messages($m,$mid),'reactions'=>gd_reactions($m,$mid),'spectator_count'=>$isBot?0:gd_spectator_count($m,$mid),'ranked_result'=>$rankedResult];
}
// Modificatori di statistiche basati sugli effetti attivi
function gd_get_modified_stats(array $card): array {
    $atk_mult = 1.0;
    $def_mult = 1.0;
    $spd_mult = 1.0;
    $def_flat_bonus = 0;
    
    // Nauz u Trichecu (87) - Spesso Strato di Grasso (+15% degli HP max come difesa flat)
    if ((int)($card['personaggio_id'] ?? 0) === 87) {
        $def_flat_bonus = (int)round((int)$card['max_hp'] * 0.15);
    }
    
    $effects = is_array($card['status_effects']) ? $card['status_effects'] : json_decode($card['status_effects'] ?: '[]', true);
    if (is_array($effects)) {
        foreach ($effects as $eff) {
            switch ($eff['type']) {
                case 'buff_atk':
                    $atk_mult += (int)$eff['value'] / 100;
                    break;
                case 'debuff_atk':
                    $atk_mult -= (int)$eff['value'] / 100;
                    break;
                case 'buff_def':
                    $def_mult += (int)$eff['value'] / 100;
                    break;
                case 'debuff_def':
                    $def_mult -= (int)$eff['value'] / 100;
                    break;
                case 'buff_spd':
                    $spd_mult += (int)$eff['value'] / 100;
                    break;
                case 'debuff_spd':
                    $spd_mult -= (int)$eff['value'] / 100;
                    break;
                case 'freeze':
                    $def_mult -= 0.30; // Congelamento riduce la difesa del 30%
                    break;
            }
        }
    }
    
    return [
        'attack' => max(1, (int)round((int)$card['attack'] * $atk_mult)),
        'defense' => max(0, (int)round((int)$card['defense'] * $def_mult) + $def_flat_bonus),
        'speed' => max(1, (int)round((int)$card['speed'] * $spd_mult)),
    ];
}

// Controlla e attiva la resurrezione passiva o la rianimazione di The One
function gd_check_and_trigger_resurrect(mysqli $m, int $mid, int $uid, array $dead_card): bool {
    $dead_id = (int)$dead_card['id'];
    
    // Prima verifichiamo la rianimazione di "The One" (rianimazione personale)
    $dead_char = gd_character($m, (int)$dead_card['personaggio_id']) ?? ['nome' => 'Personaggio', 'rarita' => 'comune'];
    $dead_cfg = gd_get_character_config((int)$dead_card['personaggio_id'], $dead_char['rarita'], $dead_char['nome']);
    if (isset($dead_cfg['passive_effect']['type']) && $dead_cfg['passive_effect']['type'] === 'the_one_passive') {
        $effects = is_array($dead_card['status_effects']) ? $dead_card['status_effects'] : json_decode($dead_card['status_effects'] ?: '[]', true);
        $used = false;
        foreach ($effects as $eff) {
            if ($eff['type'] === 'the_one_resurrect_used') {
                $used = true;
                break;
            }
        }
        if (!$used) {
            $heal = (int)$dead_card['max_hp']; // 100% HP
            $m->query("UPDATE game_match_cards SET current_hp={$heal}, is_ko=0, is_active=1 WHERE id={$dead_id}");
            
            $effects[] = ['type' => 'the_one_resurrect_used', 'value' => 1, 'duration' => 99, 'name' => 'Rianimazione Usata'];
            $status_json = json_encode($effects);
            $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string($status_json) . "' WHERE id={$dead_id}");
            
            gd_log($m, $mid, $uid, 0, 'system', $dead_id, $dead_id, 0, "Orgoglio Divino! {$dead_char['nome']} si rianima con il 100% di HP!");
            return true;
        }
    }

    // Se non si è rianimato da solo, controlliamo se un alleato ha la Sinfonia del Destino
    $cards = gd_cards($m, $mid);
    foreach ($cards as $c) {
        if ((int)$c['user_id'] === $uid && !(int)$c['is_ko']) {
            $c_char = $c['character'] ?? gd_character($m, (int)$c['personaggio_id']) ?? ['nome' => 'Personaggio', 'rarita' => 'comune'];
            $cfg = gd_get_character_config((int)$c['personaggio_id'], $c_char['rarita'], $c_char['nome']);
            if (isset($cfg['passive_effect']['type']) && $cfg['passive_effect']['type'] === 'destiny_resurrect') {
                $effects = is_array($c['status_effects']) ? $c['status_effects'] : json_decode($c['status_effects'] ?: '[]', true);
                $used = false;
                foreach ($effects as $eff) {
                    if ($eff['type'] === 'resurrect_used') {
                        $used = true;
                        break;
                    }
                }
                if (!$used) {
                    $heal = (int)round($dead_card['max_hp'] * 0.35);
                    $m->query("UPDATE game_match_cards SET current_hp={$heal}, is_ko=0, is_active=1 WHERE id={$dead_id}");
                    
                    $effects[] = ['type' => 'resurrect_used', 'value' => 1, 'duration' => 99, 'name' => 'Resurrezione Usata'];
                    $status_json = json_encode($effects);
                    $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string($status_json) . "' WHERE id={$c['id']}");
                    
                    gd_log($m, $mid, $uid, 0, 'system', (int)$c['id'], $dead_id, 0, "Sinfonia del Destino! {$c['character']['nome']} risuscita {$dead_card['character']['nome']} con {$heal} HP!");
                    return true;
                }
            }
        }
    }
    return false;
}

// Esegue il passaggio del turno applicando i tick degli effetti ad inizio turno
function gd_transition_turn(mysqli $m, array $match, int $next_uid): void {
    $mid = (int)$match['id'];
    $turn = (int)$match['turn_number'];
    
    $stunned_this_turn = false;
    $ko = 0;
    $double_turn = false;
    
    $active = gd_active($m, $mid, $next_uid);
    if (!$active) {
        $first = gd_first_alive($m, $mid, $next_uid);
        if ($first) {
            gd_set_active($m, $mid, $next_uid, (int)$first['id']);
            $active = $first;
        }
    }
    
    $stunned_this_turn = false;
    $double_turn = false;
    
    if ($active) {
        $active_id = (int)$active['id'];
        $effects = is_array($active['status_effects']) ? $active['status_effects'] : json_decode($active['status_effects'] ?: '[]', true);
        $new_effects = [];
        $hp_change = 0;
        
        $active_char = gd_character($m, (int)$active['personaggio_id']) ?? ['nome' => 'Personaggio', 'rarita' => 'comune'];
        $char_name = $active_char['nome'];
        $ch_cfg = gd_get_character_config((int)$active['personaggio_id'], $active_char['rarita'], $active_char['nome']);
        
        // Nauz Principessa Cosmica - Polvere di Stelle (cura l'alleato con meno HP)
        if (isset($ch_cfg['passive_effect']['type']) && $ch_cfg['passive_effect']['type'] === 'nauz_cosmic_passive') {
            $allies = gd_cards($m, $mid);
            $lowest_ally = null;
            foreach ($allies as $ally) {
                if ((int)$ally['user_id'] === $next_uid && !(int)$ally['is_ko']) {
                    if ($lowest_ally === null || (int)$ally['current_hp'] < (int)$lowest_ally['current_hp']) {
                        $lowest_ally = $ally;
                    }
                }
            }
            if ($lowest_ally) {
                $heal = (int)round($active['max_hp'] * 0.10);
                $new_hp = min((int)$lowest_ally['max_hp'], (int)$lowest_ally['current_hp'] + $heal);
                $m->query("UPDATE game_match_cards SET current_hp={$new_hp} WHERE id={$lowest_ally['id']}");
                gd_log($m, $mid, $next_uid, $turn, 'system', $active_id, (int)$lowest_ally['id'], 0, "Polvere di Stelle cura {$lowest_ally['character']['nome']} di {$heal} HP.");
            }
        }
        
        // 1. Aura Curativa (Healer passive)
        if (isset($ch_cfg['passive_effect']['type']) && ($ch_cfg['passive_effect']['type'] === 'regen_all_allies' || $ch_cfg['passive_effect']['type'] === 'regen_all_allies_heavy')) {
            $allies = gd_cards($m, $mid);
            $pct = ($ch_cfg['passive_effect']['type'] === 'regen_all_allies_heavy') ? 0.08 : 0.05;
            foreach ($allies as $ally) {
                if ((int)$ally['user_id'] === $next_uid && !(int)$ally['is_ko']) {
                    $heal = (int)round($ally['max_hp'] * $pct);
                    $new_hp = min((int)$ally['max_hp'], (int)$ally['current_hp'] + $heal);
                    $m->query("UPDATE game_match_cards SET current_hp={$new_hp} WHERE id={$ally['id']}");
                    gd_log($m, $mid, $next_uid, $turn, 'system', $active_id, (int)$ally['id'], 0, "Aura Curativa cura {$ally['character']['nome']} di {$heal} HP.");
                }
            }
        }
        
        // 2. Presenza Eterea (Controller passive)
        if (isset($ch_cfg['passive_effect']['type']) && $ch_cfg['passive_effect']['type'] === 'ethereal_double_turn') {
            if (random_int(1, 100) <= 30) {
                $double_turn = true;
                gd_log($m, $mid, $next_uid, $turn, 'system', $active_id, null, 0, "Presenza Eterea risuona! {$char_name} ottiene un turno extra.");
            }
        }
        
        // 3. Processo gli effetti di stato
        foreach ($effects as &$eff) {
            switch ($eff['type']) {
                case 'poison':
                    $dmg = (int)round($active['max_hp'] * ($eff['value'] / 100));
                    $hp_change -= $dmg;
                    gd_log($m, $mid, $next_uid, $turn, 'system', null, $active_id, $dmg, "{$char_name} subisce {$dmg} danni da Veleno.");
                    break;
                case 'bleed':
                    $dmg = (int)round($active['max_hp'] * ($eff['value'] / 100));
                    $hp_change -= $dmg;
                    gd_log($m, $mid, $next_uid, $turn, 'system', null, $active_id, $dmg, "{$char_name} perde {$dmg} HP per Sanguinamento.");
                    break;
                case 'regen':
                    $heal = (int)round($active['max_hp'] * ($eff['value'] / 100));
                    $hp_change += $heal;
                    gd_log($m, $mid, $next_uid, $turn, 'system', null, $active_id, 0, "Rigenerazione cura {$char_name} di {$heal} HP.");
                    break;
                case 'stun':
                case 'freeze':
                    $stunned_this_turn = true;
                    break;
            }
            
            $eff['duration']--;
            if ($eff['duration'] > 0) {
                $new_effects[] = $eff;
            } else {
                gd_log($m, $mid, $next_uid, $turn, 'system', null, $active_id, 0, "L'effetto {$eff['name']} su {$char_name} è svanito.");
            }
        }
        unset($eff);
        
        if ($double_turn) {
            $new_effects[] = ['type' => 'double_turn', 'value' => 1, 'duration' => 1, 'name' => 'Turno Extra'];
        }
        
        $current_hp = (int)$active['current_hp'];
        $max_hp = (int)$active['max_hp'];
        
        if ($hp_change !== 0) {
            $current_hp = max(0, min($max_hp, $current_hp + $hp_change));
        }
        
        $ko = $current_hp <= 0 ? 1 : 0;
        $status_json = json_encode($new_effects);
        
        $q = $m->prepare("UPDATE game_match_cards SET current_hp=?, is_ko=?, is_active=IF(?=1,0,is_active), status_effects=? WHERE id=?");
        $q->bind_param('iiisi', $current_hp, $ko, $ko, $status_json, $active_id);
        $q->execute();
        $q->close();
        
        if ($ko) {
            gd_log($m, $mid, $next_uid, $turn, 'system', null, $active_id, 0, "{$char_name} è andato KO per gli effetti di stato.");
            $resurrected = gd_check_and_trigger_resurrect($m, $mid, $next_uid, $active);
            if (!$resurrected) {
                $n = gd_first_alive($m, $mid, $next_uid);
                if ($n) gd_set_active($m, $mid, $next_uid, (int)$n['id']);
            }
        }
    }
    
    if (gd_alive_count($m, $mid, $next_uid) <= 0) {
        $opp = gd_opponent_id($match, $next_uid);
        gd_finish($m, $match, $opp, $next_uid);
        return;
    }
    
    if ($stunned_this_turn && !$ko) {
        $opp = gd_opponent_id($match, $next_uid);
        gd_log($m, $mid, $next_uid, $turn, 'system', (int)$active['id'], null, 0, "{$char_name} è stordito/congelato e salta il turno!");
        
        $q = $m->prepare('UPDATE game_matches SET current_turn_user_id=?, turn_number=turn_number+1, updated_at=NOW() WHERE id=?');
        $q->bind_param('ii', $opp, $mid);
        $q->execute();
        $q->close();
        
        $updated_match = gd_match($m, $mid);
        gd_transition_turn($m, $updated_match, $opp);
        return;
    }
    
    $q = $m->prepare('UPDATE game_matches SET current_turn_user_id=?, updated_at=NOW() WHERE id=?');
    $q->bind_param('ii', $next_uid, $mid);
    $q->execute();
    $q->close();
}

function gd_insert_team_cards(mysqli $m, int $mid, int $uid, array $team): void {
    $ins = $m->prepare('INSERT INTO game_match_cards (match_id,user_id,personaggio_id,slot_index,current_hp,max_hp,attack,defense,speed,energy,max_energy,special_name,special_cost,special_cooldown_max,special_cooldown,is_active,role,shield,crit_rate,crit_dmg,status_effects) VALUES (?,?,?,?,?,?,?,?,?,1,?,?,?,?,0,?,?,?,?,?,?)');
    if (!$ins) throw new Exception($m->error);
    foreach ($team as $idx => $pid) {
        $pid = (int)$pid;
        $s = gd_stats($m, $pid);
        $slot = $idx + 1;
        $active = $idx === 0 ? 1 : 0;
        $hp = (int)$s['hp'];
        $atk = (int)$s['attack'];
        $def = (int)$s['defense'];
        $spd = (int)$s['speed'];
        $en = (int)$s['max_energy'];
        $name = (string)$s['special_name'];
        $cost = (int)$s['special_cost'];
        $cd = (int)$s['special_cooldown'];
        
        $role = (string)$s['role'];
        
        // Controlla scudo passivo iniziale
        $ch = gd_character($m, $pid);
        $cfg = gd_get_character_config($pid, $ch['rarita'] ?? 'comune', $ch['nome'] ?? '');
        $shield = 0;
        if (isset($cfg['passive_effect']['type']) && $cfg['passive_effect']['type'] === 'shield_at_start') {
            $shield = (int)round($hp * ($cfg['passive_effect']['pct'] / 100));
        }
        
        $crit_rate = (int)$s['crit_rate'];
        $crit_dmg = (int)$s['crit_dmg'];
        $status_effects = json_encode([]);
        
        $ins->bind_param('iiiiiiiiiisiiisiiis', $mid, $uid, $pid, $slot, $hp, $hp, $atk, $def, $spd, $en, $name, $cost, $cd, $active, $role, $shield, $crit_rate, $crit_dmg, $status_effects);
        $ins->execute();
    }
    $ins->close();

    // Applica la passiva Support: scudo a inizio match a tutto il team
    $team_cards = gd_cards($m, $mid);
    $user_cards = array_filter($team_cards, function($c) use ($uid) {
        return (int)$c['user_id'] === $uid;
    });
    $total_support_shield_pct = 0;
    foreach ($user_cards as $c) {
        $c_char = $c['character'];
        $c_cfg = gd_get_character_config((int)$c['personaggio_id'], $c_char['rarita'] ?? 'comune', $c_char['nome'] ?? '');
        if (isset($c_cfg['passive_effect']['type']) && $c_cfg['passive_effect']['type'] === 'shield_team_at_start') {
            $total_support_shield_pct += (int)$c_cfg['passive_effect']['pct'];
        }
    }
    if ($total_support_shield_pct > 0) {
        foreach ($user_cards as $c) {
            $extra_shield = (int)round((int)$c['max_hp'] * ($total_support_shield_pct / 100));
            $new_shield = (int)$c['shield'] + $extra_shield;
            $m->query("UPDATE game_match_cards SET shield={$new_shield} WHERE id={$c['id']}");
        }
    }
}

function gd_random_bot_team(mysqli $m, array $fallback = []): array {
    $c = gd_char_cols($m);
    if (!$c['id']) return array_slice(array_values(array_unique(array_map('intval', $fallback))), 0, 3);
    $sql = 'SELECT '.gd_qcol($c['id']).' id FROM personaggi ORDER BY RAND() LIMIT 3';
    $res = $m->query($sql);
    $team = [];
    if ($res) while ($r = $res->fetch_assoc()) $team[] = (int)$r['id'];
    $team = array_values(array_unique($team));
    if (count($team) < 3 && count($fallback) >= 3) $team = array_slice(array_values(array_unique(array_map('intval', $fallback))), 0, 3);
    if (count($team) !== 3) gd_fail('Non ci sono abbastanza personaggi per creare il bot.', 500);
    return $team;
}

function gd_prepare_bot_team(mysqli $m, int $mid, array $fallback = []): void {
    $bot = gd_bot_id();
    $q = $m->prepare('DELETE FROM game_match_cards WHERE match_id=? AND user_id=?');
    $q->bind_param('ii', $mid, $bot);
    $q->execute();
    $q->close();
    $team = gd_random_bot_team($m, $fallback);
    gd_insert_team_cards($m, $mid, $bot, $team);
}

function gd_apply_battle_action(mysqli $m, array $match, int $uid, string $act, int $target = 0): array {
    if (!in_array($act, ['basic_attack','special_attack','defend','charge','switch'], true)) gd_fail('Azione non valida.');
    $mid = (int)$match['id'];
    $turn = (int)$match['turn_number'];
    $opp = gd_opponent_id($match, $uid);
    
    $actor = gd_active($m, $mid, $uid);
    if (!$actor) gd_fail('Nessuna carta attiva.');
    $actorId = (int)$actor['id'];
    
    $actor_char = gd_character($m, (int)$actor['personaggio_id']) ?? ['nome' => 'Personaggio', 'rarita' => 'comune'];
    $char_name = $actor_char['nome'];
    $actor_cfg = gd_get_character_config((int)$actor['personaggio_id'], $actor_char['rarita'] ?? 'comune', $actor_char['nome'] ?? '');
    
    $dmg = 0;
    $msg = '';
    $is_crit = false;
    $double_turn_active = false;
    $dmg_already_applied = false;
    
    // Controlla se il turno extra è attivo
    $actor_effects = is_array($actor['status_effects']) ? $actor['status_effects'] : json_decode($actor['status_effects'] ?: '[]', true);
    foreach ($actor_effects as $idx => $eff) {
        if ($eff['type'] === 'double_turn') {
            $double_turn_active = true;
            unset($actor_effects[$idx]);
            $actor_effects = array_values($actor_effects);
            $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($actor_effects)) . "' WHERE id={$actorId}");
            break;
        }
    }
    
    if ($act === 'switch') {
        $q = $m->prepare('SELECT * FROM game_match_cards WHERE id=? AND match_id=? AND user_id=? AND is_ko=0 LIMIT 1');
        $q->bind_param('iii', $target, $mid, $uid);
        $q->execute();
        $new = $q->get_result()->fetch_assoc();
        $q->close();
        if (!$new) gd_fail('Cambio non valido.');
        gd_set_active($m, $mid, $uid, (int)$new['id']);
        
        // Rimuove provocazione sul vecchio attivo
        $old_effects = is_array($actor['status_effects']) ? $actor['status_effects'] : json_decode($actor['status_effects'] ?: '[]', true);
        $old_effects = array_filter($old_effects, function($e) { return $e['type'] !== 'taunt'; });
        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode(array_values($old_effects))) . "', is_defending=0 WHERE id={$actorId}");
        
        $actorId = (int)$new['id'];
        $msg = $uid === gd_bot_id() ? 'Il bot cambia personaggio.' : 'Cambio personaggio.';
        gd_log($m, $mid, $uid, $turn, 'switch', $actorId, null, 0, $msg);
    } elseif ($act === 'defend') {
        $en = min((int)$actor['max_energy'], (int)$actor['energy'] + 1);
        $shield_val = (int)round($actor['max_hp'] * 0.20);
        $new_shield = (int)$actor['shield'] + $shield_val;
        
        $q = $m->prepare('UPDATE game_match_cards SET is_defending=1, energy=?, shield=? WHERE id=?');
        $q->bind_param('iii', $en, $new_shield, $actorId);
        $q->execute();
        $q->close();
        
        $msg = $uid === gd_bot_id() ? 'Il bot si difende e ottiene uno scudo.' : 'Difesa attiva! Ottieni uno scudo.';
        gd_log($m, $mid, $uid, $turn, 'defend', $actorId, null, 0, $msg);
    } elseif ($act === 'charge') {
        $en = min((int)$actor['max_energy'], (int)$actor['energy'] + 2);
        
        $effects = is_array($actor['status_effects']) ? $actor['status_effects'] : json_decode($actor['status_effects'] ?: '[]', true);
        $effects[] = ['type' => 'buff_spd', 'value' => 20, 'duration' => 1, 'name' => 'Velocità +20%'];
        $status_json = json_encode($effects);
        
        $q = $m->prepare('UPDATE game_match_cards SET energy=?, status_effects=? WHERE id=?');
        $q->bind_param('isi', $en, $status_json, $actorId);
        $q->execute();
        $q->close();
        
        $msg = $uid === gd_bot_id() ? 'Il bot carica energia e aumenta la velocità.' : 'Carica energia! Velocità aumentata per il prossimo turno.';
        gd_log($m, $mid, $uid, $turn, 'charge', $actorId, null, 0, $msg);
    } else {
        $t = null;
        
        // Controlla Taunt nemico
        $enemies = gd_cards($m, $mid);
        $taunting_enemy = null;
        foreach ($enemies as $e) {
            if ((int)$e['user_id'] === $opp && !(int)$e['is_ko']) {
                $e_effects = is_array($e['status_effects']) ? $e['status_effects'] : json_decode($e['status_effects'] ?: '[]', true);
                foreach ($e_effects as $eff) {
                    if ($eff['type'] === 'taunt') {
                        $taunting_enemy = $e;
                        break 2;
                    }
                }
            }
        }
        
        if ($taunting_enemy) {
            $t = $taunting_enemy;
        } elseif ($target > 0) {
            $q = $m->prepare('SELECT * FROM game_match_cards WHERE id=? AND match_id=? AND user_id=? AND is_ko=0 LIMIT 1');
            $q->bind_param('iii', $target, $mid, $opp);
            $q->execute();
            $t = $q->get_result()->fetch_assoc();
            $q->close();
        }
        
        if (!$t) $t = gd_active($m, $mid, $opp);
        if (!$t) gd_fail('Target non valido.');
        $target = (int)$t['id'];
        
        $a_stats = gd_get_modified_stats($actor);
        $t_stats = gd_get_modified_stats($t);
        
        $target_char = gd_character($m, (int)$t['personaggio_id']) ?? ['nome' => 'Personaggio', 'rarita' => 'comune'];
        $target_name = $target_char['nome'];
        
        $target_cfg = gd_get_character_config((int)$t['personaggio_id'], $target_char['rarita'] ?? 'comune', $target_char['nome'] ?? '');
        
        // Charlie Kirk - Fatti e Logica (ignora il 30% della difesa nemica)
        if ((int)($actor['personaggio_id'] ?? 0) === 64) {
            $t_stats['defense'] = (int)round($t_stats['defense'] * 0.70);
        }
        
        // Cripsum Disoccupato - Sussidio di Disoccupazione (+40% Difesa se a 0 energia)
        if ((int)($actor['personaggio_id'] ?? 0) === 46 && (int)$actor['energy'] === 0) {
            $a_stats['defense'] = (int)round($a_stats['defense'] * 1.40);
        }
        if ((int)($t['personaggio_id'] ?? 0) === 46 && (int)$t['energy'] === 0) {
            $t_stats['defense'] = (int)round($t_stats['defense'] * 1.40);
        }
        
        // Christian (Gooner Final Boss) - Stato di Edging (Attacco raddoppiato, Velocità -20% se sotto 50% HP)
        if ((int)($actor['personaggio_id'] ?? 0) === 76) {
            if ((int)$actor['max_hp'] > 0 && ((int)$actor['current_hp'] / (int)$actor['max_hp']) < 0.5) {
                $a_stats['attack'] = (int)round($a_stats['attack'] * 2.0);
                $a_stats['speed'] = (int)round($a_stats['speed'] * 0.8);
            }
        }
        if ((int)($t['personaggio_id'] ?? 0) === 76) {
            if ((int)$t['max_hp'] > 0 && ((int)$t['current_hp'] / (int)$t['max_hp']) < 0.5) {
                $t_stats['attack'] = (int)round($t_stats['attack'] * 2.0);
                $t_stats['speed'] = (int)round($t_stats['speed'] * 0.8);
            }
        }
        
        // Controlla Immunità (per immunizzazioni totali temporanee da mosse o ruoli)
        $target_immune = false;
        $t_effects = is_array($t['status_effects']) ? $t['status_effects'] : json_decode($t['status_effects'] ?: '[]', true);
        foreach ($t_effects as $eff) {
            if ($eff['type'] === 'immunity') {
                $target_immune = true;
                break;
            }
        }
        
        $atk_mult = 1.0;
        $crit_chance = (int)$actor['crit_rate'];
        $crit_dmg_mult = (int)$actor['crit_dmg'] / 100;
        
        // Aggiunge i buff di crit_rate e crit_dmg dagli effetti di stato attivi dell'attore
        foreach ($actor_effects as $eff) {
            if ($eff['type'] === 'buff_crit_rate') {
                $crit_chance += (int)$eff['value'];
            }
            if ($eff['type'] === 'buff_crit_dmg') {
                $crit_dmg_mult += (int)$eff['value'] / 100;
            }
        }
        
        // Passiva Orgoglio Divino di "The One": +25% Crit Rate e +50% Crit Dmg a tutto il team
        $has_the_one_passive = false;
        $team_cards = gd_cards($m, $mid);
        foreach ($team_cards as $tc) {
            if ((int)$tc['user_id'] === $uid && !(int)$tc['is_ko']) {
                $tc_char = $tc['character'] ?? gd_character($m, (int)$tc['personaggio_id']) ?? ['nome' => 'Personaggio', 'rarita' => 'comune'];
                $tc_cfg = gd_get_character_config((int)$tc['personaggio_id'], $tc_char['rarita'], $tc_char['nome']);
                if (isset($tc_cfg['passive_effect']['type']) && $tc_cfg['passive_effect']['type'] === 'the_one_passive') {
                    $has_the_one_passive = true;
                    break;
                }
            }
        }
        if ($has_the_one_passive) {
            $crit_chance += 25;
            $crit_dmg_mult += 0.50;
        }
        
        // Nebulosa Aurea (Cripsum Principessa Cosmica ID 139): +15% Crit Rate a tutto il team
        $has_nebula = false;
        foreach ($team_cards as $tc) {
            if ((int)$tc['user_id'] === $uid && !(int)$tc['is_ko'] && (int)$tc['personaggio_id'] === 139) {
                $has_nebula = true;
                break;
            }
        }
        if ($has_nebula) {
            $crit_chance += 15;
        }
        
        // Dante - Stile Elegantissimo (moltiplicatore di danno basato sullo stile accumulato)
        if ((int)($actor['personaggio_id'] ?? 0) === 141) {
            $style_stacks = 0;
            foreach ($actor_effects as $eff) {
                if ($eff['type'] === 'dante_style') {
                    $style_stacks = (int)$eff['value'];
                }
            }
            if ($style_stacks > 0) {
                $atk_mult += ($style_stacks * 0.05); // +5% danno per stack
            }
        }
        
        // Passiva Buffer: +10% o +15% ATK a tutto il team se il Buffer è attivo in campo
        $has_buffer_passive = false;
        $buffer_bonus = 0;
        foreach ($team_cards as $tc) {
            if ((int)$tc['user_id'] === $uid && !(int)$tc['is_ko'] && (int)$tc['is_active'] === 1) {
                $tc_char = $tc['character'] ?? gd_character($m, (int)$tc['personaggio_id']) ?? ['nome' => 'Personaggio', 'rarita' => 'comune'];
                $tc_cfg = gd_get_character_config((int)$tc['personaggio_id'], $tc_char['rarita'], $tc_char['nome']);
                if (isset($tc_cfg['passive_effect']['type']) && ($tc_cfg['passive_effect']['type'] === 'team_atk_buff' || $tc_cfg['passive_effect']['type'] === 'team_atk_buff_heavy')) {
                    $has_buffer_passive = true;
                    $buffer_bonus = ($tc_cfg['passive_effect']['type'] === 'team_atk_buff_heavy') ? 15 : 10;
                    break;
                }
            }
        }
        if ($has_buffer_passive) {
            $atk_mult += $buffer_bonus / 100;
        }
        
        // Passiva Bruiser
        if ($actor['role'] === 'Bruiser') {
            $lost_hp_pct = ((int)$actor['max_hp'] - (int)$actor['current_hp']) / (int)$actor['max_hp'];
            $pct_per_stack = (isset($actor_cfg['passive_effect']['type']) && $actor_cfg['passive_effect']['type'] === 'atk_scale_lost_hp_heavy') ? 8 : 5;
            $bruiser_buff = (int)floor($lost_hp_pct * 10) * $pct_per_stack;
            if ($bruiser_buff > 0) {
                $atk_mult += $bruiser_buff / 100;
            }
        }
        
        // Passiva Burst DPS
        if ($actor['role'] === 'Burst DPS') {
            $crit_dmg_mult += 0.40;
        }
        
        // Passiva Sub DPS
        if ($actor['role'] === 'Sub DPS') {
            $has_debuff = false;
            foreach ($t_effects as $eff) {
                if (in_array($eff['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true)) {
                    $has_debuff = true;
                    break;
                }
            }
            if ($has_debuff) {
                $bonus = (isset($actor_cfg['passive_effect']['type']) && $actor_cfg['passive_effect']['type'] === 'bonus_dmg_on_debuffed_heavy') ? 35 : 20;
                $atk_mult += $bonus / 100;
            }
        }
        
        // Passiva DPS: Gestione Crit Ramp on Non-Crit
        $ramp_val = 0;
        $ramp_type = $actor_cfg['passive_effect']['type'] ?? '';
        $is_ramp_passive = ($ramp_type === 'crit_ramp_on_non_crit' || $ramp_type === 'crit_ramp_on_non_crit_heavy');
        foreach ($actor_effects as $eff) {
            if ($eff['type'] === 'crit_ramp') {
                $ramp_val = (int)$eff['value'];
            }
        }
        $crit_chance += $ramp_val;
        

        if ($act === 'basic_attack') {
            if ($target_immune) {
                $dmg = 0;
                $msg = "{$char_name} attacca {$target_name}, ma l'attacco viene annullato dall'Immunità!";
            } else {
                $base_dmg = $a_stats['attack'] * $atk_mult;
                $def_reduction = $t_stats['defense'] * 0.45;
                $dmg = max(5, (int)round($base_dmg - $def_reduction));
                
                if (random_int(1, 100) <= $crit_chance) {
                    $dmg = (int)round($dmg * $crit_dmg_mult);
                    $is_crit = true;
                }
                $en = min((int)$actor['max_energy'], (int)$actor['energy'] + 1);
                $m->query("UPDATE game_match_cards SET energy={$en} WHERE id={$actorId}");
                
                $msg = $is_crit ? "Colpo Critico! {$char_name} infligge {$dmg} danni a {$target_name}." : "{$char_name} attacca {$target_name} infliggendo {$dmg} danni.";
                
                // Passiva Debuffer
                if ($actor['role'] === 'Debuffer') {
                    $val = (isset($cfg['passive_effect']['type']) && $cfg['passive_effect']['type'] === 'poison_on_hit_heavy') ? 15 : 10;
                    $t_effects[] = ['type' => 'poison', 'value' => $val, 'duration' => 2, 'name' => 'Veleno'];
                    $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                    $msg .= " Applicato Veleno per 2 turni.";
                }
                // Passiva Controller
                if ($actor['role'] === 'Controller') {
                    if (random_int(1, 100) <= 25) {
                        $t_effects[] = ['type' => 'freeze', 'value' => 1, 'duration' => 1, 'name' => 'Congelamento'];
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        if ((int)($t['personaggio_id'] ?? 0) === 87) {
                            $t_effects = array_filter($t_effects, function($e) { return $e['type'] !== 'freeze'; });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        $msg .= " **[Controllo]** {$char_name} congela {$target_name}!";
                    }
                }
            }
        } else {
            // SPECIALE
            if ((int)$actor['energy'] < (int)$actor['special_cost']) gd_fail('Energia insufficiente per la mossa speciale.');
            if ((int)$actor['special_cooldown'] > 0) gd_fail('Mossa speciale in ricarica.');
            
            // Controlla Silenzio
            foreach ($actor_effects as $eff) {
                if ($eff['type'] === 'silence') {
                    gd_fail('Il tuo personaggio è silenziato e non può usare mosse speciali!');
                }
            }
            
            $eff_type = $actor_cfg['special_effect']['type'] ?? '';
            
            $en = max(0, (int)$actor['energy'] - (int)$actor['special_cost']);
            $cd = max(1, (int)$actor['special_cooldown_max']);
            $m->query("UPDATE game_match_cards SET energy={$en}, special_cooldown={$cd} WHERE id={$actorId}");
            
            $msg = "{$char_name} usa la speciale: **{$actor_cfg['special_name']}**! ";
            switch ($eff_type) {
                case 'taunt_self':
                case 'taunt_self_heavy':
                    $mult = ($eff_type === 'taunt_self_heavy') ? 0.75 : 0.40;
                    $shield_val = (int)round($actor['max_hp'] * $mult);
                    $new_shield = (int)$actor['shield'] + $shield_val;
                    
                    $actor_effects[] = ['type' => 'taunt', 'value' => 1, 'duration' => 2, 'name' => 'Provocazione'];
                    $status_json = json_encode($actor_effects);
                    
                    $m->query("UPDATE game_match_cards SET shield={$new_shield}, status_effects='" . $m->escape_string($status_json) . "' WHERE id={$actorId}");
                    $msg .= "Ottiene uno Scudo di {$shield_val} HP e attiva la Provocazione per 2 turni.";
                    break;
                    
                case 'apply_bleed':
                case 'apply_bleed_heavy':
                    $dur = ($eff_type === 'apply_bleed_heavy') ? 3 : 2;
                    $val = 15;
                    $mult = ($eff_type === 'apply_bleed_heavy') ? 1.8 : 1.5;
                    
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * $mult;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $t_effects[] = ['type' => 'bleed', 'value' => $val, 'duration' => $dur, 'name' => 'Sanguinamento'];
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $msg .= "Colpisce {$target_name} infliggendo {$dmg} danni e applica Sanguinamento per {$dur} turni.";
                    }
                    break;
                    
                case 'flurry_of_blows':
                case 'flurry_of_blows_heavy':
                    $mult = ($eff_type === 'flurry_of_blows_heavy') ? 2.1 : 1.8;
                    $energy_gain = ($eff_type === 'flurry_of_blows_heavy') ? 2 : 1;
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * $mult;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                            $new_energy = min((int)$actor['max_energy'], $en + $energy_gain);
                            $m->query("UPDATE game_match_cards SET energy={$new_energy} WHERE id={$actorId}");
                        }
                        $msg .= "Colpisce {$target_name} infliggendo {$dmg} danni" . ($is_crit ? " (Critico! Ricarica {$energy_gain} Energia)" : "") . ".";
                    }
                    break;
                    
                case 'deadly_strike':
                case 'deadly_strike_heavy':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $mult = ($eff_type === 'deadly_strike_heavy') ? 2.5 : 2.2;
                        $base_dmg = $a_stats['attack'] * $mult;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        if ($eff_type === 'deadly_strike') {
                            $actor_effects[] = ['type' => 'debuff_def', 'value' => 25, 'duration' => 1, 'name' => 'Difesa -25%'];
                            $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($actor_effects)) . "' WHERE id={$actorId}");
                            $msg .= "Colpisce {$target_name} con {$dmg} danni, ma riduce la propria Difesa per 1 turno.";
                        } else {
                            $msg .= "Colpisce {$target_name} infliggendo un colpo devastante da {$dmg} danni!";
                        }
                    }
                    break;
                    
                case 'distracting_strike':
                case 'distracting_strike_heavy':
                    $mult = ($eff_type === 'distracting_strike_heavy') ? 1.7 : 1.4;
                    $spd_red = ($eff_type === 'distracting_strike_heavy') ? 35 : 25;
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * $mult;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $t_effects[] = ['type' => 'debuff_spd', 'value' => $spd_red, 'duration' => 2, 'name' => "Velocità -{$spd_red}%"];
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $msg .= "Infligge {$dmg} danni a {$target_name} e riduce la sua Velocità del {$spd_red}% per 2 turni.";
                    }
                    break;
                    
                case 'shield_all_allies':
                case 'shield_all_allies_heavy':
                    $mult = ($eff_type === 'shield_all_allies_heavy') ? 0.40 : 0.25;
                    $shield_val = (int)round($actor['max_hp'] * $mult);
                    $allies = gd_cards($m, $mid);
                    foreach ($allies as $ally) {
                        if ((int)$ally['user_id'] === $uid && !(int)$ally['is_ko']) {
                            $new_shield = (int)$ally['shield'] + $shield_val;
                            $m->query("UPDATE game_match_cards SET shield={$new_shield} WHERE id={$ally['id']}");
                        }
                    }
                    $msg .= "Crea una barriera protettiva di {$shield_val} HP per tutti gli alleati.";
                    break;
                    
                case 'heal_active_regen':
                case 'heal_active_regen_heavy':
                    $mult_heal = ($eff_type === 'heal_active_regen_heavy') ? 0.55 : 0.40;
                    $mult_regen = ($eff_type === 'heal_active_regen_heavy') ? 20 : 15;
                    
                    $heal_val = (int)round($actor['max_hp'] * $mult_heal);
                    $new_hp = min((int)$actor['max_hp'], (int)$actor['current_hp'] + $heal_val);
                    
                    $actor_effects[] = ['type' => 'regen', 'value' => $mult_regen, 'duration' => 2, 'name' => 'Rigenerazione'];
                    $status_json = json_encode($actor_effects);
                    
                    $m->query("UPDATE game_match_cards SET current_hp={$new_hp}, status_effects='" . $m->escape_string($status_json) . "' WHERE id={$actorId}");
                    $msg .= "Rigenera {$heal_val} HP sul personaggio attivo e applica Rigenerazione per 2 turni.";
                    break;
                    
                case 'stun_target':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 1.2;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        
                        $stunned = false;
                        if (random_int(1, 100) <= 75) {
                            $t_effects[] = ['type' => 'stun', 'value' => 1, 'duration' => 1, 'name' => 'Stordimento'];
                            $stunned = true;
                        }
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $msg .= "Infligge {$dmg} danni a {$target_name}" . ($stunned ? " e lo Stordisce per 1 turno!" : ".");
                    }
                    break;
                    
                case 'toxic_mist':
                case 'toxic_mist_heavy':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $def_red = ($eff_type === 'toxic_mist_heavy') ? 40 : 30;
                        $poison_val = ($eff_type === 'toxic_mist_heavy') ? 15 : 12;
                        
                        $t_effects[] = ['type' => 'debuff_def', 'value' => $def_red, 'duration' => 2, 'name' => "Difesa -{$def_red}%"];
                        $t_effects[] = ['type' => 'poison', 'value' => $poison_val, 'duration' => 3, 'name' => 'Veleno'];
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $msg .= "Avvolge {$target_name} in una nebbia tossica che ne riduce la Difesa e lo Avvelena per 3 turni.";
                    }
                    break;
                    
                case 'battle_cry':
                    $allies = gd_cards($m, $mid);
                    foreach ($allies as $ally) {
                        if ((int)$ally['user_id'] === $uid && !(int)$ally['is_ko']) {
                            $a_effects = is_array($ally['status_effects']) ? $ally['status_effects'] : json_decode($ally['status_effects'] ?: '[]', true);
                            $a_effects[] = ['type' => 'buff_atk', 'value' => 25, 'duration' => 2, 'name' => 'Attacco +25%'];
                            $a_effects[] = ['type' => 'buff_spd', 'value' => 20, 'duration' => 2, 'name' => 'Velocità +20%'];
                            
                            $status_json = json_encode(array_values($a_effects));
                            $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string($status_json) . "' WHERE id={$ally['id']}");
                        }
                    }
                    $new_energy = min((int)$actor['max_energy'], $en + 1);
                    $m->query("UPDATE game_match_cards SET energy={$new_energy} WHERE id={$actorId}");
                    
                    $msg .= "Aumenta l'Attacco e la Velocità di tutti gli alleati e ricarica 1 Energia.";
                    break;

                // SPECIALI PERSONALIZZATE DEI 18 PERSONAGGI
                case 'cripsum_unemployed_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 1.5;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $absorb = (int)round($t['current_hp'] * 0.15);
                        $dmg += $absorb;
                        
                        $new_hp = min((int)$actor['max_hp'], (int)$actor['current_hp'] + $absorb);
                        $m->query("UPDATE game_match_cards SET current_hp={$new_hp} WHERE id={$actorId}");
                        
                        $t_effects[] = ['type' => 'debuff_atk', 'value' => 30, 'duration' => 2, 'name' => 'Attacco -30%'];
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $msg .= "Colpisce {$target_name} con {$dmg} danni (assorbendo {$absorb} HP) e riduce il suo Attacco del 30% per 2 turni.";
                    }
                    break;

                case 'the_one_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 2.2;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        $msg .= "Colpisce {$target_name} con {$dmg} danni. ";
                    }
                    
                    $allies = gd_cards($m, $mid);
                    foreach ($allies as $ally) {
                        if ((int)$ally['user_id'] === $uid && !(int)$ally['is_ko']) {
                            $heal = (int)round($ally['max_hp'] * 0.40);
                            $new_hp = min((int)$ally['max_hp'], (int)$ally['current_hp'] + $heal);
                            
                            $ally_effs = is_array($ally['status_effects']) ? $ally['status_effects'] : json_decode($ally['status_effects'] ?: '[]', true);
                            $ally_effs[] = ['type' => 'buff_crit_rate', 'value' => 25, 'duration' => 3, 'name' => 'Crit Rate +25%'];
                            $ally_effs[] = ['type' => 'buff_crit_dmg', 'value' => 50, 'duration' => 3, 'name' => 'Crit Dmg +50%'];
                            
                            $m->query("UPDATE game_match_cards SET current_hp={$new_hp}, status_effects='" . $m->escape_string(json_encode($ally_effs)) . "' WHERE id={$ally['id']}");
                        }
                    }
                    $msg .= "Cura il team del 40% HP max e infonde energia cosmica (+25% Crit Rate, +50% Crit Dmg per 3 turni)!";
                    break;

                case 'sossio_trash_special':
                    $heal = (int)round($actor['max_hp'] * 0.30);
                    $new_hp = min((int)$actor['max_hp'], (int)$actor['current_hp'] + $heal);
                    $new_en = min((int)$actor['max_energy'], $en + 1);
                    
                    $actor_effects[] = ['type' => 'buff_atk', 'value' => 35, 'duration' => 2, 'name' => 'Attacco +35%'];
                    $status_json = json_encode($actor_effects);
                    
                    $m->query("UPDATE game_match_cards SET current_hp={$new_hp}, energy={$new_en}, status_effects='" . $m->escape_string($status_json) . "' WHERE id={$actorId}");
                    $msg .= "Sossio si esibisce: cura se stesso di {$heal} HP, ottiene +35% Attacco per 2 turni e guadagna 1 Energia!";
                    break;

                case 'manuel_beatboxer_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 1.4;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $t_effects[] = ['type' => 'silence', 'value' => 1, 'duration' => 2, 'name' => 'Silenzio'];
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $msg .= "Colpisce {$target_name} con {$dmg} danni e lo Silenzia per 2 turni con un drop di cassa asfissiante!";
                    }
                    break;

                case 'charlie_kirk_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 1.6;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $t_effects[] = ['type' => 'debuff_def', 'value' => 35, 'duration' => 2, 'name' => 'Difesa -35%'];
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $actor_effects[] = ['type' => 'taunt', 'value' => 1, 'duration' => 2, 'name' => 'Provocazione'];
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($actor_effects)) . "' WHERE id={$actorId}");
                        
                        $msg .= "Colpisce {$target_name} con {$dmg} danni, riduce la sua Difesa del 35% e attiva la Provocazione su se stesso per 2 turni.";
                    }
                    break;

                case 'zakator_opsec_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 2.4;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $target_hp = (int)$t['current_hp'];
                        $new_target_hp = max(0, $target_hp - $dmg);
                        $target_ko = $new_target_hp <= 0 ? 1 : 0;
                        
                        $m->query("UPDATE game_match_cards SET current_hp={$new_target_hp}, is_ko={$target_ko}, is_active=IF({$target_ko}=1,0,is_active) WHERE id={$target}");
                        $dmg_already_applied = true;
                        
                        $msg .= "Bypassa completamente gli scudi e colpisce direttamente gli HP di {$target_name} per {$dmg} danni!";
                    }
                    break;

                case 'christian_gooner_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 1.8;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $heal = (int)round($dmg * 0.50);
                        $new_hp = min((int)$actor['max_hp'], (int)$actor['current_hp'] + $heal);
                        $shield_val = (int)round($actor['max_hp'] * 0.20);
                        $new_shield = (int)$actor['shield'] + $shield_val;
                        
                        $m->query("UPDATE game_match_cards SET current_hp={$new_hp}, shield={$new_shield} WHERE id={$actorId}");
                        
                        $msg .= "Colpisce {$target_name} con {$dmg} danni, si rigenera di {$heal} HP e ottiene uno Scudo di {$shield_val} HP.";
                    }
                    break;

                case 'aldrich_mercenary_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 2.1;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $t_effects[] = ['type' => 'bleed', 'value' => 15, 'duration' => 2, 'name' => 'Sanguinamento'];
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $msg .= "Spara una Raffica di Piombo su {$target_name} infliggendo {$dmg} danni e applicando Sanguinamento per 2 turni.";
                    }
                    break;

                case 'nauz_trichecu_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = ($a_stats['attack'] * 1.2) + ($a_stats['defense'] * 0.15);
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $congelato = false;
                        if (random_int(1, 100) <= 50) {
                            $t_effects[] = ['type' => 'freeze', 'value' => 1, 'duration' => 1, 'name' => 'Congelamento'];
                            $congelato = true;
                        }
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $actor_effects[] = ['type' => 'taunt', 'value' => 1, 'duration' => 2, 'name' => 'Provocazione'];
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($actor_effects)) . "' WHERE id={$actorId}");
                        
                        $msg .= "Colpisce {$target_name} con {$dmg} danni (scalandoli con la sua Difesa), attiva la Provocazione su se stesso per 2 turni" . ($congelato ? " e lo Congela!" : "") . ".";
                    }
                    break;

                case 'carmelo_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $is_crowd_controlled = false;
                        foreach ($t_effects as $eff) {
                            if ($eff['type'] === 'stun' || $eff['type'] === 'freeze') {
                                $is_crowd_controlled = true;
                                break;
                            }
                        }
                        
                        $mult = $is_crowd_controlled ? 3.2 : 1.6;
                        $base_dmg = $a_stats['attack'] * $mult;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $msg .= "Colpisce {$target_name} con " . ($is_crowd_controlled ? "un colpo raddoppiato da " : "") . "{$dmg} danni!";
                    }
                    break;

                case 'nauz_cosmic_special':
                    $shield_val = (int)round($actor['max_hp'] * 0.30);
                    $allies = gd_cards($m, $mid);
                    foreach ($allies as $ally) {
                        if ((int)$ally['user_id'] === $uid && !(int)$ally['is_ko']) {
                            $new_shield = (int)$ally['shield'] + $shield_val;
                            
                            $ally_effs = is_array($ally['status_effects']) ? $ally['status_effects'] : json_decode($ally['status_effects'] ?: '[]', true);
                            $ally_effs = array_filter($ally_effs, function($e) {
                                return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true);
                            });
                            $ally_effs[] = ['type' => 'buff_def', 'value' => 25, 'duration' => 2, 'name' => 'Difesa +25%'];
                            
                            $m->query("UPDATE game_match_cards SET shield={$new_shield}, status_effects='" . $m->escape_string(json_encode(array_values($ally_effs))) . "' WHERE id={$ally['id']}");
                        }
                    }
                    $msg .= "Attiva la Costellazione Protettiva: rimuove tutti i debuff dal team, fornisce uno Scudo di {$shield_val} HP e +25% Difesa per 2 turni!";
                    break;

                case 'cripsum_cosmic_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 1.5;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        $msg .= "Colpisce {$target_name} con {$dmg} danni. ";
                    }
                    
                    $allies = gd_cards($m, $mid);
                    foreach ($allies as $ally) {
                        if ((int)$ally['user_id'] === $uid && !(int)$ally['is_ko']) {
                            $heal = (int)round($ally['max_hp'] * 0.25);
                            $new_hp = min((int)$ally['max_hp'], (int)$ally['current_hp'] + $heal);
                            $new_energy = min((int)$ally['max_energy'], (int)$ally['energy'] + 2);
                            
                            $m->query("UPDATE game_match_cards SET current_hp={$new_hp}, energy={$new_energy} WHERE id={$ally['id']}");
                        }
                    }
                    $msg .= "Cura l'intero team del 25% degli HP max e fornisce 2 Energia a tutti!";
                    break;

                case 'flight_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 1.7;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $t_effects[] = ['type' => 'debuff_spd', 'value' => 30, 'duration' => 2, 'name' => 'Velocità -30%'];
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $actor_effects[] = ['type' => 'buff_spd', 'value' => 30, 'duration' => 2, 'name' => 'Velocità +30%'];
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($actor_effects)) . "' WHERE id={$actorId}");
                        
                        $msg .= "Colpisce {$target_name} con {$dmg} danni, aumenta la sua Velocità del 30% e riduce quella del bersaglio del 30% per 2 turni.";
                    }
                    break;

                case 'dante_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 2.3;
                        $def_reduction = ($t_stats['defense'] * 0.70) * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $heal = (int)round($dmg * 0.25);
                        $new_hp = min((int)$actor['max_hp'], (int)$actor['current_hp'] + $heal);
                        $m->query("UPDATE game_match_cards SET current_hp={$new_hp} WHERE id={$actorId}");
                        
                        $msg .= "Attiva il Devil Trigger! Colpisce {$target_name} con {$dmg} danni e si cura di {$heal} HP.";
                    }
                    break;

                case 'vergil_special':
                    if ($target_immune) {
                        $msg .= "Ma l'attacco viene annullato dall'Immunità di {$target_name}!";
                    } else {
                        $base_dmg = $a_stats['attack'] * 2.0;
                        $def_reduction = $t_stats['defense'] * 0.45;
                        $dmg = max(10, (int)round($base_dmg - $def_reduction));
                        if (random_int(1, 100) <= $crit_chance) {
                            $dmg = (int)round($dmg * $crit_dmg_mult);
                            $is_crit = true;
                        }
                        
                        $congelato = false;
                        if (random_int(1, 100) <= 35) {
                            $t_effects[] = ['type' => 'freeze', 'value' => 1, 'duration' => 1, 'name' => 'Congelamento'];
                            $congelato = true;
                        }
                        if ((int)($t['personaggio_id'] ?? 0) === 75) {
                            $t_effects = array_filter($t_effects, function($e) { return !in_array($e['type'], ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'], true); });
                        }
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                        
                        $actor_effects[] = ['type' => 'buff_crit_rate', 'value' => 30, 'duration' => 2, 'name' => 'Crit Rate +30%'];
                        $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($actor_effects)) . "' WHERE id={$actorId}");
                        
                        $msg .= "Scatena la Judgement Cut End su {$target_name} infliggendo {$dmg} danni" . ($congelato ? " e Congelandolo!" : "") . ". Vergil guadagna +30% Crit Rate per 2 turni.";
                    }
                    break;
                    
                default:
                    $base_dmg = $a_stats['attack'] * 1.65;
                    $def_reduction = $t_stats['defense'] * 0.35;
                    $dmg = max(10, (int)round($base_dmg - $def_reduction));
                    $msg .= "Colpisce {$target_name} infliggendo {$dmg} danni.";
                    break;
            }
        }
        
        // Cripsum Disoccupato - Sussidio di Disoccupazione (Ruba 1 energia all'attacco base)
        if ((int)($actor['personaggio_id'] ?? 0) === 46 && $act === 'basic_attack' && !$target_immune) {
            if (random_int(1, 100) <= 35) {
                $stolen_en = min(1, (int)$t['energy']);
                if ($stolen_en > 0) {
                    $new_t_en = max(0, (int)$t['energy'] - 1);
                    $new_a_en = min((int)$actor['max_energy'], (int)$actor['energy'] + 1);
                    $m->query("UPDATE game_match_cards SET energy={$new_t_en} WHERE id={$target}");
                    $m->query("UPDATE game_match_cards SET energy={$new_a_en} WHERE id={$actorId}");
                    $msg .= " **[Sussidio]** Ruba 1 Energia a {$target_name}!";
                }
            }
        }
        
        // Manuel Beatboxer - Ritmo Asfissiante (25% probabilità di stordire all'attacco base)
        if ((int)($actor['personaggio_id'] ?? 0) === 50 && $act === 'basic_attack' && !$target_immune) {
            if (random_int(1, 100) <= 25) {
                $t_effects[] = ['type' => 'stun', 'value' => 1, 'duration' => 1, 'name' => 'Stordimento'];
                $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                $msg .= " **[Ritmo Asfissiante]** Manuel stordisce {$target_name} per 1 turno!";
            }
        }
        
        // Vergil - Concentrazione Pura (l'attacco base colpisce due volte)
        if ((int)($actor['personaggio_id'] ?? 0) === 142 && $act === 'basic_attack' && !$target_immune) {
            $second_hit = (int)round($dmg * 0.40);
            $dmg += $second_hit;
            $msg .= " (Vergil esegue un secondo colpo da {$second_hit} danni!)";
        }
        
        // Dante - Stile Elegantissimo (accumulo stile all'attacco)
        if ((int)($actor['personaggio_id'] ?? 0) === 141) {
            $style_stacks = 0;
            $a_effects = is_array($actor['status_effects']) ? $actor['status_effects'] : json_decode($actor['status_effects'] ?: '[]', true);
            foreach ($a_effects as $e) {
                if ($e['type'] === 'dante_style') $style_stacks = (int)$e['value'];
            }
            if ($style_stacks < 5) {
                $style_stacks++;
                $a_effects = array_filter($a_effects, function($e) { return $e['type'] !== 'dante_style'; });
                $a_effects[] = ['type' => 'dante_style', 'value' => $style_stacks, 'duration' => 3, 'name' => "Stile Stacks: {$style_stacks}"];
                $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode(array_values($a_effects))) . "' WHERE id={$actorId}");
            }
        }
        
        // Iron Dome (Netanyahu ID 143): riduce del 25% i danni speciali subiti dal team
        if ($act === 'special_attack' && $dmg > 0) {
            $has_netanyahu = false;
            $opp_cards = gd_cards($m, $mid);
            foreach ($opp_cards as $oc) {
                if ((int)$oc['user_id'] === $opp && !(int)$oc['is_ko'] && (int)$oc['personaggio_id'] === 143) {
                    $has_netanyahu = true;
                    break;
                }
            }
            if ($has_netanyahu) {
                $dmg = (int)round($dmg * 0.75);
                $msg .= " (Danno speciale ridotto del 25% dall'Iron Dome!)";
            }
        }
        
        // Flight - Evitamento Aereo (25% di schivare completamente)
        if ((int)($t['personaggio_id'] ?? 0) === 140 && !$target_immune && $dmg > 0) {
            if (random_int(1, 100) <= 25) {
                $msg .= " **[Evitamento Aereo]** Flight schiva completamente l'attacco volando in alto!";
                $dmg = 0;
            }
        }
        
        // Risoluzione danno effettivo
        if ($dmg > 0 && !$target_immune && !$dmg_already_applied) {
            $dmg_taken = $dmg;
            $current_shield = (int)$t['shield'];
            
            if ($current_shield > 0) {
                if ($current_shield >= $dmg) {
                    $current_shield -= $dmg;
                    $dmg_taken = 0;
                } else {
                    $dmg_taken -= $current_shield;
                    $current_shield = 0;
                }
            }
            
            if ((int)$t['is_defending'] === 1 && $dmg_taken > 0) {
                $dmg_taken = max(2, (int)floor($dmg_taken * 0.6));
            }
            
            $hp = max(0, (int)$t['current_hp'] - $dmg_taken);
            $ko = $hp <= 0 ? 1 : 0;
            
            // Il Protagonista - Scudo di Trama (Plot Armor)
            if ($ko === 1 && (int)($t['personaggio_id'] ?? 0) === 144) {
                $used_plot_armor = false;
                foreach ($t_effects as $e) {
                    if ($e['type'] === 'plot_armor_used') $used_plot_armor = true;
                }
                if (!$used_plot_armor) {
                    $hp = 1;
                    $ko = 0;
                    $plot_shield = (int)round($t['max_hp'] * 0.50);
                    $current_shield = $plot_shield;
                    $t_effects[] = ['type' => 'plot_armor_used', 'value' => 1, 'duration' => 99, 'name' => 'Scudo di Trama Attivo'];
                    $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode($t_effects)) . "' WHERE id={$target}");
                    $msg .= " **[Scudo di Trama]** Il Protagonista sopravvive con 1 HP e ottiene uno Scudo di {$plot_shield} HP!";
                }
            }
            
            // Tung God - Lega di Tungsteno (Riflette il 30% dei danni subiti)
            if ((int)($t['personaggio_id'] ?? 0) === 88 && $dmg_taken > 0) {
                $reflected_dmg = (int)round($dmg_taken * 0.30);
                $actor_new_hp = max(1, (int)$actor['current_hp'] - $reflected_dmg);
                $m->query("UPDATE game_match_cards SET current_hp={$actor_new_hp} WHERE id={$actorId}");
                $msg .= " **[Lega di Tungsteno]** Riflette {$reflected_dmg} danni a {$char_name}!";
            }
            
            // Sossio - Ballo del Trash (Velocità +10% a tutto il team quando subisce danni)
            if ((int)($t['personaggio_id'] ?? 0) === 49 && $dmg_taken > 0) {
                $allies = gd_cards($m, $mid);
                foreach ($allies as $ally) {
                    if ((int)$ally['user_id'] === $opp && !(int)$ally['is_ko']) {
                        $a_effects = is_array($ally['status_effects']) ? $ally['status_effects'] : json_decode($ally['status_effects'] ?: '[]', true);
                        $sossio_buffs = 0;
                        foreach ($a_effects as $e) {
                            if ($e['type'] === 'sossio_speed') $sossio_buffs++;
                        }
                        if ($sossio_buffs < 3) {
                            $a_effects[] = ['type' => 'sossio_speed', 'value' => 10, 'duration' => 2, 'name' => 'Ballo del Trash +10% Spd'];
                            $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode(array_values($a_effects))) . "' WHERE id={$ally['id']}");
                        }
                    }
                }
                $msg .= " **[Ballo del Trash]** Il ritmo di Sossio aumenta la Velocità del suo team!";
            }
            
            // Dante - Stile Elegantissimo (Perde lo stile se subisce danni)
            if ((int)($t['personaggio_id'] ?? 0) === 141 && $dmg_taken > 0) {
                $t_effects = array_filter($t_effects, function($e) { return $e['type'] !== 'dante_style'; });
                $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode(array_values($t_effects))) . "' WHERE id={$target}");
                $msg .= " **[Stile Resettato]** Dante perde lo stile accumulato!";
            }
            
            // Aldrich - Contratto Lucroso (Cura 40% HP e +2 Energia se sconfigge un nemico)
            if ($ko === 1 && (int)($actor['personaggio_id'] ?? 0) === 86) {
                $heal = (int)round($actor['max_hp'] * 0.40);
                $new_hp = min((int)$actor['max_hp'], (int)$actor['current_hp'] + $heal);
                $new_energy = min((int)$actor['max_energy'], (int)$actor['energy'] + 2);
                $m->query("UPDATE game_match_cards SET current_hp={$new_hp}, energy={$new_energy} WHERE id={$actorId}");
                $msg .= " **[Contratto Lucroso]** Aldrich riscatta la taglia: si cura di {$heal} HP e ottiene 2 Energia!";
            }
            
            // Gestione Crit Ramp della passiva DPS
            if ($is_ramp_passive) {
                if ($is_crit) {
                    $actor_effects = array_filter($actor_effects, function($e) { return $e['type'] !== 'crit_ramp'; });
                    $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode(array_values($actor_effects))) . "' WHERE id={$actorId}");
                } else {
                    $ramp_gain = ($ramp_type === 'crit_ramp_on_non_crit_heavy') ? 15 : 10;
                    $new_ramp = min(100, $ramp_val + $ramp_gain);
                    $actor_effects = array_filter($actor_effects, function($e) { return $e['type'] !== 'crit_ramp'; });
                    $actor_effects[] = ['type' => 'crit_ramp', 'value' => $new_ramp, 'duration' => 99, 'name' => "Crit Ramp +{$new_ramp}%"];
                    $m->query("UPDATE game_match_cards SET status_effects='" . $m->escape_string(json_encode(array_values($actor_effects))) . "' WHERE id={$actorId}");
                }
            }

            $q = $m->prepare('UPDATE game_match_cards SET current_hp=?, shield=?, is_ko=?, is_active=IF(?=1,0,is_active), is_defending=0 WHERE id=?');
            $q->bind_param('iiiii', $hp, $current_shield, $ko, $ko, $target);
            $q->execute();
            $q->close();
            
            // Contrattacco
            $has_counter = false;
            foreach ($t_effects as $eff) {
                if ($eff['type'] === 'counter') {
                    $has_counter = true;
                    break;
                }
            }
            if ($has_counter && $dmg_taken > 0) {
                $counter_dmg = (int)round($dmg_taken * 0.40);
                
                $actor_shield = (int)$actor['shield'];
                $actor_dmg_taken = $counter_dmg;
                if ($actor_shield > 0) {
                    if ($actor_shield >= $counter_dmg) {
                        $actor_shield -= $counter_dmg;
                        $actor_dmg_taken = 0;
                    } else {
                        $actor_dmg_taken -= $actor_shield;
                        $actor_shield = 0;
                    }
                }
                
                $actor_hp = max(0, (int)$actor['current_hp'] - $actor_dmg_taken);
                $actor_ko = $actor_hp <= 0 ? 1 : 0;
                
                $q = $m->prepare('UPDATE game_match_cards SET current_hp=?, shield=?, is_ko=?, is_active=IF(?=1,0,is_active) WHERE id=?');
                $q->bind_param('iiii', $actor_hp, $actor_shield, $actor_ko, $actorId);
                $q->execute();
                $q->close();
                
                gd_log($m, $mid, $opp, $turn, 'system', $target, $actorId, $counter_dmg, "Contrattacco! {$target_name} restituisce {$counter_dmg} danni a {$char_name}.");
                
                if ($actor_ko) {
                    gd_log($m, $mid, $uid, $turn, 'system', null, $actorId, 0, "{$char_name} è andato KO per il contrattacco.");
                    $resurrected = gd_check_and_trigger_resurrect($m, $mid, $uid, $actor);
                    if (!$resurrected) {
                        $n = gd_first_alive($m, $mid, $uid);
                        if ($n) gd_set_active($m, $mid, $uid, (int)$n['id']);
                    }
                }
            }
            
            if ($ko) {
                gd_log($m, $mid, $uid, $turn, 'system', null, $target, 0, "{$target_name} è andato KO.");
                $resurrected = gd_check_and_trigger_resurrect($m, $mid, $opp, $t);
                if (!$resurrected) {
                    $n = gd_first_alive($m, $mid, $opp);
                    if ($n) gd_set_active($m, $mid, $opp, (int)$n['id']);
                }
            }
            
            gd_log($m, $mid, $uid, $turn, $act, $actorId, $target, $dmg_taken, $msg);
        } else {
            gd_log($m, $mid, $uid, $turn, $act, $actorId, $target, 0, $msg);
        }
    }
    
    if ($act !== 'special_attack') {
        $m->query("UPDATE game_match_cards SET special_cooldown=GREATEST(0,special_cooldown-1) WHERE match_id={$mid} AND user_id={$uid} AND special_cooldown>0");
    }
    
    if (gd_alive_count($m, $mid, $opp) <= 0) {
        gd_finish($m, $match, $uid, $opp);
        return ['finished' => true, 'winner' => $uid, 'loser' => $opp];
    }
    if (gd_alive_count($m, $mid, $uid) <= 0) {
        gd_finish($m, $match, $opp, $uid);
        return ['finished' => true, 'winner' => $opp, 'loser' => $uid];
    }
    
    if ($double_turn_active && gd_alive_count($m, $mid, $uid) > 0) {
        gd_log($m, $mid, $uid, $turn, 'system', $actorId, null, 0, "{$char_name} agisce di nuovo grazie al turno extra!");
        $m->query("UPDATE game_matches SET turn_number=turn_number+1, updated_at=NOW() WHERE id={$mid}");
    } else {
        $updated_match = gd_match($m, $mid);
        gd_transition_turn($m, $updated_match, $opp);
    }
    
    return ['finished' => false, 'winner' => null, 'loser' => null];
}

function gd_choose_bot_action(mysqli $m, int $mid): array {
    $bot = gd_bot_id();
    $active = gd_active($m, $mid, $bot);
    if (!$active) {
        $first = gd_first_alive($m, $mid, $bot);
        if ($first) return ['switch', (int)$first['id']];
        return ['basic_attack', 0];
    }
    
    $hpPct = (int)$active['max_hp'] > 0 ? (int)$active['current_hp'] / (int)$active['max_hp'] : 1.0;
    $energy = (int)$active['energy'];
    $spec_cost = (int)$active['special_cost'];
    $spec_cooldown = (int)$active['special_cooldown'];
    
    // 1. Se ha poca vita, prova a curarsi o a cambiare
    if ($hpPct < 0.35) {
        if ($energy >= $spec_cost && $spec_cooldown <= 0 && in_array($active['role'], ['Healer', 'Support', 'Tank'], true)) {
            return ['special_attack', 0];
        }
        
        $cards = gd_cards($m, $mid);
        $best_swap = null;
        foreach ($cards as $c) {
            if ((int)$c['user_id'] === $bot && !(int)$c['is_ko'] && (int)$c['id'] !== (int)$active['id']) {
                if ($c['role'] === 'Tank') {
                    $best_swap = $c;
                    break;
                }
                if ($c['role'] === 'Bruiser' && (!$best_swap || $best_swap['role'] !== 'Tank')) {
                    $best_swap = $c;
                }
                if (!$best_swap) {
                    $best_swap = $c;
                }
            }
        }
        if ($best_swap && random_int(1, 100) <= 70) {
            return ['switch', (int)$best_swap['id']];
        }
    }
    
    // 2. Se ha la speciale pronta ed è offensivo, la usa
    if ($energy >= $spec_cost && $spec_cooldown <= 0) {
        if (random_int(1, 100) <= 80) {
            return ['special_attack', 0];
        }
    }
    
    // 3. Se è un Tank senza scudi, usa difesa o speciale
    if ($active['role'] === 'Tank' && (int)$active['shield'] <= 0) {
        if ($energy >= $spec_cost && $spec_cooldown <= 0) {
            return ['special_attack', 0];
        }
        if (random_int(1, 100) <= 50) {
            return ['defend', 0];
        }
    }
    
    // 4. Se ha poca energia, carica con probabilità del 40%
    if ($energy < $spec_cost) {
        if ($energy <= 1 && random_int(1, 100) <= 40) {
            return ['charge', 0];
        }
    }
    
    return ['basic_attack', 0];
}

function gd_bot_take_turn(mysqli $m, array $match): array {
    if (!gd_is_bot_match($match) || (int)($match['current_turn_user_id'] ?? -1) !== gd_bot_id()) return ['acted' => false, 'finished' => false];
    [$act, $target] = gd_choose_bot_action($m, (int)$match['id']);
    $result = gd_apply_battle_action($m, $match, gd_bot_id(), $act, (int)$target);
    if (!empty($result['finished'])) return ['acted' => true, 'finished' => true];
    return ['acted' => true, 'finished' => false];
}




function gd_start_if_ready(mysqli $m, int $mid): void {
    $match = gd_match($m, $mid); if (!$match) return;
    if (gd_is_bot_match($match)) {
        if ((int)$match['player1_ready'] !== 1) return;
        $p1 = (int)$match['player1_id'];
        $bot = gd_bot_id();
        foreach ([$p1, $bot] as $p) { if (!gd_active($m,$mid,$p)) { $c=gd_first_alive($m,$mid,$p); if($c) gd_set_active($m,$mid,$p,(int)$c['id']); } }
        
        // Calcola chi ha la velocità maggiore per il primo turno contro il bot
        $first_turn_user = $p1;
        $act1 = gd_active($m, $mid, $p1);
        $act2 = gd_active($m, $mid, $bot);
        if ($act1 && $act2 && (int)($act2['speed'] ?? 0) > (int)($act1['speed'] ?? 0)) {
            $first_turn_user = $bot;
        }
        
        $st = $m->prepare("UPDATE game_matches SET status='active', player2_ready=1, current_turn_user_id=?, started_at=IFNULL(started_at,NOW()), updated_at=NOW() WHERE id=? AND status IN ('waiting','team_select')");
        $st->bind_param('ii', $first_turn_user, $mid); $st->execute(); $st->close();
        gd_log($m,$mid,0,0,'system',null,null,0,"La partita offline contro il bot è iniziata. Comincia il turno grazie alla maggiore Velocità.");
        return;
    }
    if ((int)$match['player1_ready'] !== 1 || (int)$match['player2_ready'] !== 1 || empty($match['player2_id'])) return;
    $p1 = (int)$match['player1_id']; $p2 = (int)$match['player2_id'];
    foreach ([$p1,$p2] as $p) { if (!gd_active($m,$mid,$p)) { $c=gd_first_alive($m,$mid,$p); if($c) gd_set_active($m,$mid,$p,(int)$c['id']); } }
    
    // Calcola chi ha la velocità maggiore per il primo turno tra i due giocatori
    $first_turn_user = $p1;
    $act1 = gd_active($m, $mid, $p1);
    $act2 = gd_active($m, $mid, $p2);
    if ($act1 && $act2 && (int)($act2['speed'] ?? 0) > (int)($act1['speed'] ?? 0)) {
        $first_turn_user = $p2;
    }
    
    $st = $m->prepare("UPDATE game_matches SET status='active', current_turn_user_id=?, started_at=IFNULL(started_at,NOW()), updated_at=NOW() WHERE id=? AND status IN ('waiting','team_select')");
    $st->bind_param('ii', $first_turn_user, $mid); $st->execute(); $st->close();
    gd_log($m,$mid,0,0,'system',null,null,0,"La partita è iniziata. Comincia il giocatore con maggiore Velocità.");
}
function gd_inventory_count(mysqli $m, int $uid): array {
    $i = gd_inv_cols($m);
    if (!$i['user'] || !$i['character']) return ['unique'=>0,'total'=>0];
    $qty = $i['qty'] ? 'SUM('.gd_qcol($i['qty']).')' : 'COUNT(*)';
    $sql = 'SELECT COUNT(DISTINCT '.gd_qcol($i['character']).') u, '.$qty.' t FROM utenti_personaggi WHERE '.gd_qcol($i['user']).'=?';
    $st = $m->prepare($sql); if(!$st) return ['unique'=>0,'total'=>0];
    $st->bind_param('i',$uid); $st->execute(); $r=$st->get_result()->fetch_assoc(); $st->close();
    return ['unique'=>(int)($r['u']??0),'total'=>(int)($r['t']??0)];
}

