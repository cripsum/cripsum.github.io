<?php
declare(strict_types=1);

function gd_api_create_match(mysqli $mysqli): void {
    $uid = gd_require_login();
    $in = gd_input();
    $mode = gd_mode((string)($in['mode'] ?? 'casual'));
    $code = gd_room_code();

    $passwordHash = null;
    if ($mode === 'private') {
        $password = trim((string)($in['password'] ?? ''));
        if (mb_strlen($password) < 3 || mb_strlen($password) > 64) {
            gd_fail('Per una stanza privata serve una password da 3 a 64 caratteri.');
        }
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    }

    $status = $mode === 'bot' ? 'team_select' : 'waiting';
    $spectatorAllowed = $mode === 'bot' ? 0 : 1;

    if (gd_has_col($mysqli, 'game_matches', 'private_password_hash')) {
        $q = $mysqli->prepare('INSERT INTO game_matches (room_code, private_password_hash, status, mode, player1_id, spectator_allowed) VALUES (?, ?, ?, ?, ?, ?)');
        if (!$q) gd_fail('Non riesco a creare la partita.', 500);
        $q->bind_param('ssssii', $code, $passwordHash, $status, $mode, $uid, $spectatorAllowed);
    } else {
        $q = $mysqli->prepare('INSERT INTO game_matches (room_code, status, mode, player1_id, spectator_allowed) VALUES (?, ?, ?, ?, ?)');
        if (!$q) gd_fail('Non riesco a creare la partita.', 500);
        $q->bind_param('sssii', $code, $status, $mode, $uid, $spectatorAllowed);
    }

    $q->execute();
    $id = $q->insert_id;
    $q->close();

    gd_ok(['match_id' => $id, 'room_code' => $code]);
}
function gd_api_find_match(mysqli $mysqli): void {
    $uid = gd_require_login();
    $mode = gd_mode((string)(gd_input()['mode'] ?? 'casual'));

    if ($mode === 'bot') {
        $code = gd_room_code();
        $q = $mysqli->prepare("INSERT INTO game_matches (room_code,status,mode,player1_id,spectator_allowed) VALUES (?,'team_select','bot',?,0)");
        if (!$q) gd_fail('Non riesco a creare la partita offline.', 500);
        $q->bind_param('si', $code, $uid);
        $q->execute();
        $mid = $q->insert_id;
        $q->close();
        gd_ok(['match_id' => $mid, 'room_code' => $code, 'joined' => true]);
    }

    $mysqli->begin_transaction();
    try {
        $q = $mysqli->prepare("SELECT * FROM game_matches WHERE status='waiting' AND mode=? AND player1_id<>? AND player2_id IS NULL ORDER BY created_at ASC LIMIT 1 FOR UPDATE");
        if (!$q) throw new Exception('prepare');
        $q->bind_param('si', $mode, $uid);
        $q->execute();
        $match = $q->get_result()->fetch_assoc();
        $q->close();

        if ($match) {
            $mid = (int)$match['id'];
            $u = $mysqli->prepare("UPDATE game_matches SET player2_id=?,status='team_select',updated_at=NOW() WHERE id=? AND player2_id IS NULL");
            $u->bind_param('ii', $uid, $mid);
            $u->execute();
            $u->close();
            $mysqli->commit();
            gd_ok(['match_id'=>$mid,'room_code'=>$match['room_code'],'joined'=>true]);
        }

        $code = gd_room_code();
        $i = $mysqli->prepare("INSERT INTO game_matches (room_code,status,mode,player1_id,spectator_allowed) VALUES (?,'waiting',?,?,1)");
        $i->bind_param('ssi', $code, $mode, $uid);
        $i->execute();
        $mid = $i->insert_id;
        $i->close();
        $mysqli->commit();
        gd_ok(['match_id'=>$mid,'room_code'=>$code,'joined'=>false]);
    } catch(Throwable $e) {
        $mysqli->rollback();
        error_log('gd_find_match: '.$e->getMessage());
        gd_fail('Matchmaking fallito.',500);
    }
}
function gd_api_join_match(mysqli $mysqli): void {
    $uid = gd_require_login();
    $input = gd_input();

    $code = strtoupper(trim((string)($input['room_code'] ?? '')));
    if (!preg_match('/^[A-Z0-9_-]{1,16}$/', $code)) {
        gd_fail('Codice stanza non valido.');
    }

    $mysqli->begin_transaction();

    try {
        $q = $mysqli->prepare('SELECT * FROM game_matches WHERE room_code=? LIMIT 1 FOR UPDATE');
        if (!$q) throw new Exception('prepare');
        $q->bind_param('s', $code);
        $q->execute();
        $match = $q->get_result()->fetch_assoc();
        $q->close();

        if (!$match) {
            throw new RuntimeException('Partita non trovata.');
        }

        if ((int)$match['player1_id'] === $uid) {
            $mysqli->commit();
            gd_ok(['match_id' => (int)$match['id'], 'room_code' => $code]);
        }

        if ($match['status'] !== 'waiting' || !empty($match['player2_id'])) {
            throw new RuntimeException('Stanza non disponibile.');
        }

        if (($match['mode'] ?? '') === 'private' && gd_has_col($mysqli, 'game_matches', 'private_password_hash')) {
            $password = (string)($input['password'] ?? '');
            $hash = (string)($match['private_password_hash'] ?? '');

            if ($hash !== '' && !password_verify($password, $hash)) {
                throw new RuntimeException('Password stanza errata.');
            }
        }

        $mid = (int)$match['id'];
        $u = $mysqli->prepare("UPDATE game_matches SET player2_id=?, status='team_select', updated_at=NOW() WHERE id=?");
        if (!$u) throw new Exception('update');
        $u->bind_param('ii', $uid, $mid);
        $u->execute();
        $u->close();

        $mysqli->commit();
        gd_ok(['match_id' => $mid, 'room_code' => $code]);
    } catch (RuntimeException $e) {
        $mysqli->rollback();
        gd_fail($e->getMessage());
    } catch (Throwable $e) {
        $mysqli->rollback();
        error_log('gd_join: '.$e->getMessage());
        gd_fail('Join fallito.', 500);
    }
}
function gd_api_inventory(mysqli $mysqli): void {
    $uid=gd_require_login(); $c=gd_char_cols($mysqli); $i=gd_inv_cols($mysqli); if(!$c['id']||!$c['name']||!$i['user']||!$i['character']) gd_fail('Schema inventario non compatibile.',500);
    $fields=['p.'.gd_qcol($c['id']).' id','p.'.gd_qcol($c['name']).' nome']; $fields[]=$c['image']?'p.'.gd_qcol($c['image']).' img_url':"'' img_url"; $fields[]=$c['rarity']?'p.'.gd_qcol($c['rarity']).' rarita':"'comune' rarita"; $fields[]=$c['category']?'p.'.gd_qcol($c['category']).' categoria':"'' categoria"; $fields[]=$i['qty']?'up.'.gd_qcol($i['qty']).' quantita':'1 quantita';
    $sql='SELECT '.implode(',',$fields).' FROM utenti_personaggi up INNER JOIN personaggi p ON p.'.gd_qcol($c['id']).'=up.'.gd_qcol($i['character']).' WHERE up.'.gd_qcol($i['user']).'=? ORDER BY p.'.gd_qcol($c['name']);
    $st=$mysqli->prepare($sql); if(!$st){error_log($mysqli->error.' SQL '.$sql);gd_fail('Query inventario non valida.',500);} $st->bind_param('i',$uid); $st->execute(); $res=$st->get_result(); $cards=[]; while($r=$res->fetch_assoc()){ $s=gd_stats($mysqli,(int)$r['id']); $r['id']=(int)$r['id']; $r['quantita']=(int)($r['quantita']??1); $r['stats']=$s; $cards[]=$r; } $st->close(); gd_ok(['cards'=>$cards]);
}
function gd_api_select_team(mysqli $mysqli): void {
    $uid = gd_require_login();
    $in = gd_input();
    $mid = (int)($in['match_id'] ?? 0);
    $team = array_values(array_unique(array_map('intval', $in['team'] ?? [])));
    if (count($team) !== 3) gd_fail('Scegli 3 personaggi diversi.');

    $match = gd_require_match($mysqli, $mid, $uid);
    $isBot = gd_is_bot_match($match);

    if (!in_array($match['status'], ['waiting','team_select'], true)) gd_fail('Non puoi cambiare team ora.');
    if (!$isBot && empty($match['player2_id'])) gd_fail('Aspetta un avversario.');
    foreach ($team as $pid) if (!gd_owns($mysqli, $uid, $pid)) gd_fail('Hai scelto un personaggio non posseduto.');

    $mysqli->begin_transaction();
    try {
        $q = $mysqli->prepare('DELETE FROM game_match_cards WHERE match_id=? AND user_id=?');
        $q->bind_param('ii', $mid, $uid);
        $q->execute();
        $q->close();

        gd_insert_team_cards($mysqli, $mid, $uid, $team);

        if ($isBot) {
            gd_prepare_bot_team($mysqli, $mid, $team);
            $q = $mysqli->prepare("UPDATE game_matches SET player1_ready=1, player2_ready=1, status='team_select', updated_at=NOW() WHERE id=?");
            $q->bind_param('i', $mid);
        } else {
            $col = ((int)$match['player1_id'] === $uid) ? 'player1_ready' : 'player2_ready';
            $q = $mysqli->prepare("UPDATE game_matches SET $col=1,status='team_select',updated_at=NOW() WHERE id=?");
            $q->bind_param('i', $mid);
        }

        $q->execute();
        $q->close();
        $mysqli->commit();

        gd_start_if_ready($mysqli, $mid);
        gd_ok(['message' => $isBot ? 'Team scelto. Il bot è pronto.' : 'Team scelto.']);
    } catch(Throwable $e) {
        $mysqli->rollback();
        error_log('gd_select_team: '.$e->getMessage());
        gd_fail('Selezione team fallita.',500);
    }
}
function gd_api_state(mysqli $mysqli): void { $uid=gd_require_login(); $mid=(int)(gd_input()['match_id'] ?? $_GET['match_id'] ?? 0); $match=gd_match($mysqli,$mid); if(!$match) gd_fail('Partita non trovata.',404); $spectator=!gd_is_player($match,$uid); if($spectator && ((int)$match['spectator_allowed']!==1 || (string)$match['mode']==='bot')) gd_fail('Non puoi vedere questa partita.',403); if($spectator && !in_array((string)$match['status'], ['active','finished'], true)) gd_fail('Questa partita non è live.',403); if($spectator) gd_touch_spectator($mysqli,$mid,$uid); gd_ok(['match'=>gd_state($mysqli,$match,$uid)]); }
function gd_api_action(mysqli $mysqli): void {
    $uid = gd_require_login();
    $in = gd_input();
    $mid = (int)($in['match_id'] ?? 0);
    $act = (string)($in['action'] ?? '');
    $target = (int)($in['target_card_id'] ?? 0);
    if (!in_array($act, ['basic_attack','special_attack','defend','charge','switch'], true)) gd_fail('Azione non valida.');

    $mysqli->begin_transaction();
    try {
        $st = $mysqli->prepare('SELECT * FROM game_matches WHERE id=? LIMIT 1 FOR UPDATE');
        $st->bind_param('i', $mid);
        $st->execute();
        $match = $st->get_result()->fetch_assoc();
        $st->close();

        if (!$match) gd_fail('Partita non trovata.',404);
        if (!gd_is_player($match, $uid)) gd_fail('Non fai parte della partita.',403);
        if ($match['status'] !== 'active') gd_fail('Partita non attiva.');
        if ((int)$match['current_turn_user_id'] !== $uid) gd_fail('Non è il tuo turno.');

        $opp = gd_opponent_id($match, $uid);
        $result = gd_apply_battle_action($mysqli, $match, $uid, $act, $target);

        if (!empty($result['finished'])) {
            $mysqli->commit();
            gd_ok(['finished'=>true,'message'=>'Hai vinto.']);
        }

        if (gd_is_bot_match($match)) {
            $bot = gd_bot_id();
            $q = $mysqli->prepare('UPDATE game_matches SET current_turn_user_id=?, turn_number=turn_number+1, updated_at=NOW() WHERE id=?');
            $q->bind_param('ii', $bot, $mid);
            $q->execute();
            $q->close();

            $st = $mysqli->prepare('SELECT * FROM game_matches WHERE id=? LIMIT 1 FOR UPDATE');
            $st->bind_param('i', $mid);
            $st->execute();
            $botMatch = $st->get_result()->fetch_assoc();
            $st->close();

            if ($botMatch && $botMatch['status'] === 'active') {
                gd_bot_take_turn($mysqli, $botMatch);
            }

            $mysqli->commit();
            gd_ok(['finished'=>false,'message'=>'Azione inviata.']);
        }

        $q = $mysqli->prepare('UPDATE game_matches SET current_turn_user_id=?, turn_number=turn_number+1, updated_at=NOW() WHERE id=?');
        $q->bind_param('ii', $opp, $mid);
        $q->execute();
        $q->close();
        $mysqli->commit();
        gd_ok(['finished'=>false,'message'=>'Azione inviata.']);
    } catch(Throwable $e) {
        $mysqli->rollback();
        if (http_response_code() !== 200) exit;
        error_log('gd_action: '.$e->getMessage());
        gd_fail('Azione fallita.',500);
    }
}
function gd_api_forfeit(mysqli $mysqli): void { $uid=gd_require_login(); $mid=(int)(gd_input()['match_id']??0); $match=gd_require_match($mysqli,$mid,$uid); if(in_array($match['status'],['finished','cancelled'],true)) gd_fail('Partita già conclusa.'); $opp=(int)$match['player1_id']===$uid?(int)$match['player2_id']:(int)$match['player1_id']; if($opp>0){gd_log($mysqli,$mid,$uid,(int)$match['turn_number'],'forfeit',null,null,0,'Abbandona la partita.'); gd_finish($mysqli,$match,$opp,$uid);} else {$q=$mysqli->prepare("UPDATE game_matches SET status='cancelled',finished_at=NOW() WHERE id=?");$q->bind_param('i',$mid);$q->execute();$q->close();} gd_ok(['message'=>'Partita abbandonata.']); }
function gd_api_ranking(mysqli $mysqli): void { gd_require_login(); $q=$mysqli->prepare('SELECT s.*, u.username FROM game_player_stats s INNER JOIN utenti u ON u.id=s.user_id ORDER BY s.rating DESC, s.wins DESC LIMIT 50'); if(!$q) gd_fail('Query ranking non valida.',500); $q->execute(); $res=$q->get_result(); $out=[]; while($r=$res->fetch_assoc()){ $rating=(int)$r['rating']; $out[]=['username'=>$r['username'],'wins'=>(int)$r['wins'],'losses'=>(int)$r['losses'],'rating'=>$rating,'rank'=>gd_rank_from_rating($rating),'season_points'=>(int)$r['season_points'],'best_streak'=>(int)$r['best_streak']];} $q->close(); gd_ok(['ranking'=>$out]); }

function gd_api_send_chat(mysqli $mysqli): void {
    $uid = gd_require_login();
    $input = gd_input();

    $mid = (int)($input['match_id'] ?? 0);
    $message = trim((string)($input['message'] ?? ''));

    if ($message === '') {
        gd_fail('Scrivi un messaggio.');
    }

    if (mb_strlen($message) > 220) {
        gd_fail('Messaggio troppo lungo.');
    }

    $match = gd_require_match($mysqli, $mid, $uid);

    if (in_array($match['status'], ['cancelled'], true)) {
        gd_fail('Chat non disponibile.');
    }

    $message = preg_replace('/\s+/', ' ', $message);

    $stmt = $mysqli->prepare('INSERT INTO game_match_chat (match_id, user_id, message) VALUES (?, ?, ?)');
    if (!$stmt) {
        gd_fail('Query chat non valida.', 500);
    }

    $stmt->bind_param('iis', $mid, $uid, $message);
    $stmt->execute();
    $stmt->close();

    gd_ok(['message' => 'Messaggio inviato.']);
}

function gd_api_active_match(mysqli $mysqli): void { $uid=gd_require_login(); $q=$mysqli->prepare("SELECT id,room_code,status,mode FROM game_matches WHERE (player1_id=? OR player2_id=?) AND status IN ('waiting','team_select','active') ORDER BY updated_at DESC LIMIT 1"); $q->bind_param('ii',$uid,$uid); $q->execute(); $m=$q->get_result()->fetch_assoc(); $q->close(); gd_ok(['match'=>$m ?: null]); }

function gd_api_live_matches(mysqli $mysqli): void {
    $uid = gd_require_login();

    $sql = "
        SELECT 
            m.id,
            m.room_code,
            m.mode,
            m.status,
            m.turn_number,
            m.player1_id,
            m.player2_id,
            u1.username AS player1_username,
            u2.username AS player2_username,
            (
                SELECT COUNT(*) 
                FROM game_spectators s 
                WHERE s.match_id = m.id
            ) AS spectator_count
        FROM game_matches m
        LEFT JOIN utenti u1 ON u1.id = m.player1_id
        LEFT JOIN utenti u2 ON u2.id = m.player2_id
        WHERE m.status = 'active'
          AND m.mode <> 'bot'
          AND m.player2_id IS NOT NULL
          AND m.spectator_allowed = 1
          AND m.player1_id <> ?
          AND (m.player2_id IS NULL OR m.player2_id <> ?)
        ORDER BY m.updated_at DESC
        LIMIT 12
    ";

    $st = $mysqli->prepare($sql);
    if (!$st) {
        gd_fail('Query partite live non valida.', 500);
    }

    $st->bind_param('ii', $uid, $uid);
    $st->execute();
    $res = $st->get_result();
    $matches = [];

    while ($row = $res->fetch_assoc()) {
        $matches[] = [
            'id' => (int)$row['id'],
            'room_code' => $row['room_code'],
            'mode' => $row['mode'],
            'status' => $row['status'],
            'turn_number' => (int)$row['turn_number'],
            'player1' => $row['player1_username'] ?: 'Player 1',
            'player2' => $row['player2_username'] ?: 'In attesa',
            'spectator_count' => (int)$row['spectator_count'],
        ];
    }

    $st->close();

    gd_ok(['matches' => $matches]);
}

function gd_api_send_reaction(mysqli $mysqli): void {
    $uid = gd_require_login();
    $input = gd_input();

    $mid = (int)($input['match_id'] ?? 0);
    $reaction = trim((string)($input['reaction'] ?? ''));

    $allowed = ['🔥', '💀', '👏', '😳', '⚡', '👀'];

    if (!in_array($reaction, $allowed, true)) {
        gd_fail('Reazione non valida.');
    }

    $match = gd_match($mysqli, $mid);
    if (!$match) {
        gd_fail('Partita non trovata.', 404);
    }

    if ((int)$match['spectator_allowed'] !== 1) {
        gd_fail('Spectator non disponibile.', 403);
    }

    if (gd_is_player($match, $uid)) {
        gd_fail('Le reazioni sono solo per gli spettatori.');
    }

    if (!in_array($match['status'], ['team_select', 'active', 'finished'], true)) {
        gd_fail('Non puoi reagire a questa partita.');
    }

    $ins = $mysqli->prepare('INSERT IGNORE INTO game_spectators (match_id, user_id) VALUES (?, ?)');
    if ($ins) {
        $ins->bind_param('ii', $mid, $uid);
        $ins->execute();
        $ins->close();
    }

    $st = $mysqli->prepare('
        SELECT COUNT(*) total
        FROM game_match_reactions
        WHERE match_id = ?
          AND user_id = ?
          AND created_at >= DATE_SUB(NOW(), INTERVAL 5 SECOND)
    ');
    if ($st) {
        $st->bind_param('ii', $mid, $uid);
        $st->execute();
        $recent = (int)($st->get_result()->fetch_assoc()['total'] ?? 0);
        $st->close();

        if ($recent >= 3) {
            gd_fail('Aspetta un attimo prima di reagire ancora.');
        }
    }

    $st = $mysqli->prepare('INSERT INTO game_match_reactions (match_id, user_id, reaction) VALUES (?, ?, ?)');
    if (!$st) {
        gd_fail('Query reazione non valida.', 500);
    }

    $st->bind_param('iis', $mid, $uid, $reaction);
    $st->execute();
    $st->close();

    gd_ok(['message' => 'Reazione inviata.']);
}

function gd_api_profile_summary(mysqli $mysqli): void { $uid=gd_require_login(); $u=gd_user_public($mysqli,$uid); $inv=gd_inventory_count($mysqli,$uid); gd_ok(['profile'=>['user'=>$u,'inventory'=>$inv]]); }
