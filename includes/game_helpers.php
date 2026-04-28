<?php
declare(strict_types=1);

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
function gd_mode(string $mode): string { return in_array($mode, ['casual','ranked','private'], true) ? $mode : 'casual'; }
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
    $sql = 'SELECT '.implode(',', $fields).' FROM personaggi p WHERE p.'.gd_qcol($c['id']).'=? LIMIT 1';
    $st = $m->prepare($sql); if (!$st) return null;
    $st->bind_param('i', $pid); $st->execute(); $row = $st->get_result()->fetch_assoc(); $st->close();
    return $row ?: null;
}
function gd_stats(mysqli $m, int $pid): array {
    $ch = gd_character($m, $pid);
    $st = gd_base_stats($ch['rarita'] ?? 'comune');
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
    while ($r = $res->fetch_assoc()) { $r['character'] = gd_character($m, (int)$r['personaggio_id']) ?: ['id'=>(int)$r['personaggio_id'],'nome'=>'Personaggio','img_url'=>'','rarita'=>'comune','categoria'=>'']; $out[] = $r; }
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

function gd_state(mysqli $m, array $match, int $viewer): array {
    $mid = (int)$match['id'];
    $cards = gd_cards($m, $mid);
    $st = $m->prepare('SELECT a.*, u.username FROM game_match_actions a LEFT JOIN utenti u ON u.id=a.user_id WHERE a.match_id=? ORDER BY a.id DESC LIMIT 24');
    $actions = [];
    if ($st) { $st->bind_param('i',$mid); $st->execute(); $res=$st->get_result(); while($r=$res->fetch_assoc()) $actions[]=$r; $st->close(); }
    $p1 = gd_user_public($m, (int)$match['player1_id']);
    $p2 = !empty($match['player2_id']) ? gd_user_public($m, (int)$match['player2_id']) : null;
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
    return ['id'=>$mid,'room_code'=>$match['room_code'],'status'=>$match['status'],'mode'=>$match['mode'],'player1_id'=>(int)$match['player1_id'],'player2_id'=>$match['player2_id']?(int)$match['player2_id']:null,'players'=>['player1'=>$p1,'player2'=>$p2],'player1_ready'=>(int)$match['player1_ready'],'player2_ready'=>(int)$match['player2_ready'],'current_turn_user_id'=>$match['current_turn_user_id']?(int)$match['current_turn_user_id']:null,'winner_id'=>$match['winner_id']?(int)$match['winner_id']:null,'loser_id'=>$match['loser_id']?(int)$match['loser_id']:null,'turn_number'=>(int)$match['turn_number'],'viewer_id'=>$viewer,'viewer_role'=>gd_is_player($match,$viewer)?'player':'spectator','cards'=>$cards,'actions'=>array_reverse($actions),'chat'=>gd_chat_messages($m,$mid),'ranked_result'=>$rankedResult];
}
function gd_start_if_ready(mysqli $m, int $mid): void {
    $match = gd_match($m, $mid); if (!$match) return;
    if ((int)$match['player1_ready'] !== 1 || (int)$match['player2_ready'] !== 1 || empty($match['player2_id'])) return;
    $p1 = (int)$match['player1_id']; $p2 = (int)$match['player2_id'];
    foreach ([$p1,$p2] as $p) { if (!gd_active($m,$mid,$p)) { $c=gd_first_alive($m,$mid,$p); if($c) gd_set_active($m,$mid,$p,(int)$c['id']); } }
    $st = $m->prepare("UPDATE game_matches SET status='active', current_turn_user_id=player1_id, started_at=IFNULL(started_at,NOW()), updated_at=NOW() WHERE id=? AND status IN ('waiting','team_select')");
    $st->bind_param('i',$mid); $st->execute(); $st->close();
    gd_log($m,$mid,0,0,'system',null,null,0,'La partita è iniziata.');
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

