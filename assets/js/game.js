(() => {
    'use strict';
    if (window.__cripsumDuelV16) return;
    window.__cripsumDuelV16 = true;

    const page = document.body?.dataset.page || '';
    const state = { matchId: Number(document.body?.dataset.matchId || 0) || null, roomCode:null, inventory:[], selectedTeam:[], match:null, poll:null, lastActionId:0, resultShown:false, lastChatId:0, lastReactionId:0 };
    const $ = (s,r=document)=>r.querySelector(s);
    const $$ = (s,r=document)=>Array.from(r.querySelectorAll(s));
    let toastTimer=null;

    function esc(v){return String(v ?? '').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
    function showToast(msg){const t=$('#gameToast'); if(!t)return; t.querySelector('span').textContent=msg; t.hidden=false; requestAnimationFrame(()=>t.classList.add('is-visible')); clearTimeout(toastTimer); toastTimer=setTimeout(()=>{t.classList.remove('is-visible');setTimeout(()=>t.hidden=true,180)},2200)}
    function setMatchmakingLoading(on, text='Cerco avversario...'){
        const box = $('#matchmakingWait');
        if (!box) return;
        const strong = box.querySelector('strong');
        if (strong) strong.textContent = text;
        box.hidden = !on;
    }
    async function api(path,payload={},method='POST'){const opts={method,headers:{'Content-Type':'application/json'},credentials:'same-origin'}; if(method!=='GET')opts.body=JSON.stringify(payload); const url=method==='GET'?`${path}?${new URLSearchParams(payload)}`:path; const r=await fetch(url,opts); const txt=await r.text(); let data; try{data=JSON.parse(txt)}catch{throw new Error(txt||'Risposta non valida')} if(!r.ok||!data.success)throw new Error(data.message||'Errore richiesta'); return data;}
    function img(src){if(!src)return'/img/Susremaster.png'; if(/^https?:\/\//i.test(src)||src.startsWith('/'))return src; return `/img/${src}`;}
    function cardImg(src,alt){return `<img src="${esc(img(src))}" alt="${esc(alt)}" onerror="this.onerror=null;this.src='/img/Susremaster.png';">`;}
    function rankIcon(rank){const key=typeof rank==='string'?rank:(rank?.key||''); const icons={bronzo:'fa-shield',argento:'fa-medal',oro:'fa-crown',diamante:'fa-gem',campione:'fa-trophy',leggenda:'fa-dragon'}; return icons[key]||'fa-shield';}
    function rankBadge(rank){if(!rank)return'';return `<span class="game-rank-badge" data-rank="${esc(rank.key)}"><i class="fa-solid ${rankIcon(rank)}"></i>${esc(rank.label)}</span>`}
    function playerTitle(player,fallback='Player'){return `${esc(player?.username||fallback)} ${rankBadge(player?.rank)}`;}
    function goArena(matchId){const lang=window.location.pathname.includes('/en/')?'en':'it';window.location.href=`/${lang}/game/arena.php?match_id=${encodeURIComponent(matchId)}`;}
    function modeLabel(mode){return mode==='ranked'?'Ranked':mode==='bot'?'Offline vs Bot':'Partita';}

    async function loadProfile(){
        const box=$('#profileSummary'); 
        if(!box)return; 
        try{
            const d=await api('/api/game/profile_summary.php',{},'GET'); 
            const u=d.profile.user, inv=d.profile.inventory;
            const isEn = window.location.pathname.includes('/en/');
            
            const totalGames = Number(u.games_played || (Number(u.wins) + Number(u.losses)));
            const winRate = totalGames > 0 ? Math.round((Number(u.wins) / totalGames) * 100) : 0;
            
            box.innerHTML=`
                <div class="game-profile-top">
                    <img src="${esc(u.pfp_url)}" alt="${esc(u.username)}" onerror="this.src='/img/Susremaster.png'">
                    <div>
                        <strong>${esc(u.username)}</strong>
                        <div style="margin-top: 4px;">${rankBadge(u.rank)}</div>
                    </div>
                </div>
                <div class="game-profile-stats">
                    <div><b>${u.rating}</b><small>${isEn ? 'Ranked Points' : 'Punti ranked'}</small></div>
                    <div><b>${inv.unique} <span style="font-size:0.8rem;opacity:0.75">/ 141</span></b><small>${isEn ? 'Characters' : 'Personaggi'}</small></div>
                    <div><b>${u.wins} - ${u.losses}</b><small>${isEn ? 'W/L Record' : 'Record W/L'}</small></div>
                    <div><b>${winRate}%</b><small>${isEn ? 'Win Rate' : 'Win Rate'}</small></div>
                    <div><b>${u.best_streak}</b><small>${isEn ? 'Best Streak' : 'Miglior Streak'}</small></div>
                    <div><b>${totalGames}</b><small>${isEn ? 'Games Played' : 'Partite giocate'}</small></div>
                </div>
            `;
        }catch(e){
            box.innerHTML='<p class="game-hint">Profilo non caricato.</p>'
        }
    }
    async function loadRanking(){const wrap=$('#rankingList'); if(!wrap)return; try{const d=await api('/api/game/get_ranking.php',{},'GET'); const rows=d.ranking||[]; if(!rows.length){wrap.innerHTML='<p class="game-hint">Classifica vuota.</p>';return} wrap.innerHTML=rows.map((r,i)=>`<div class="game-rank-row"><strong>#${i+1}</strong><span class="game-rank-name">${rankBadge(r.rank)} ${esc(r.username)}</span><span class="game-rank-meta"><b>${r.rating}</b></span></div>`).join('')}catch(e){wrap.innerHTML='<p class="game-hint">Classifica non caricata.</p>';}}
    
    async function loadLiveMatches(){
        const wrap = $('#liveMatchesList');
        if (!wrap) return;

        try {
            const d = await api('/api/game/live_matches.php', {}, 'GET');
            const rows = d.matches || [];

            if (!rows.length) {
                wrap.innerHTML = '<p class="game-hint">Nessuna partita live da guardare.</p>';
                return;
            }

            const lang = window.location.pathname.includes('/en/') ? 'en' : 'it';
            wrap.innerHTML = rows.map(m => `
                <a class="game-live-row" href="/${lang}/game/arena.php?match_id=${encodeURIComponent(m.id)}">
                    <div>
                        <strong>${esc(m.player1)} vs ${esc(m.player2)}</strong>
                        <span>${esc(m.mode)} · turno ${m.turn_number}</span>
                    </div>
                    <em><i class="fa-solid fa-eye"></i> ${m.spectator_count}</em>
                </a>
            `).join('');
        } catch(e) {
            wrap.innerHTML = '<p class="game-hint">Partite live non caricate.</p>';
        }
    }

    async function findMatch(mode){setMatchmakingLoading(true, mode==='ranked'?'Cerco ranked...':'Cerco casual...');try{const d=await api('/api/game/find_match.php',{mode}); goArena(d.match_id)}catch(e){setMatchmakingLoading(false);showToast(e.message)}}
    async function createBotMatch(){setMatchmakingLoading(true,'Creo partita offline...');try{const d=await api('/api/game/create_match.php',{mode:'bot'}); goArena(d.match_id)}catch(e){setMatchmakingLoading(false);showToast(e.message)}}
    async function createPrivate(){const password=($('#privatePasswordInput')?.value||'').trim(); if(password.length<3){showToast('Inserisci una password da almeno 3 caratteri');return} setMatchmakingLoading(true,'Creo stanza privata...'); try{const d=await api('/api/game/create_match.php',{mode:'private',password}); goArena(d.match_id)}catch(e){setMatchmakingLoading(false);showToast(e.message)}}
    async function joinCode(){const code=($('#roomCodeInput')?.value||'').trim(); const password=($('#joinPasswordInput')?.value||'').trim(); if(!code){showToast('Inserisci codice stanza');return} try{const d=await api('/api/game/join_match.php',{room_code:code,password}); goArena(d.match_id)}catch(e){showToast(e.message)}}
    async function activeMatch(){try{const d=await api('/api/game/active_match.php',{},'GET'); if(!d.match){showToast('Nessuna partita attiva');return} goArena(d.match.id)}catch(e){showToast(e.message)}}

    async function loadInventory(){const d=await api('/api/game/get_inventory_cards.php',{},'GET'); state.inventory=d.cards||[]; renderInventory();}
    function initInventoryFilters() {
        ['#cardSearch', '#filterRarity', '#filterRole', '#sortInventory'].forEach(selector => {
            const el = $(selector);
            if (el && !el.dataset.listenerBound) {
                el.dataset.listenerBound = '1';
                el.addEventListener('input', renderInventory);
                el.addEventListener('change', renderInventory);
            }
        });
    }
    function renderInventory(){
        const grid=$('#inventoryGrid'); if(!grid)return;
        initInventoryFilters();
        
        const q=($('#cardSearch')?.value||'').toLowerCase();
        const rarity = ($('#filterRarity')?.value||'').toLowerCase();
        const role = ($('#filterRole')?.value||'');
        const sortBy = ($('#sortInventory')?.value||'nome');
        
        let list = state.inventory.filter(c => {
            const matchQ = `${c.nome} ${c.rarita} ${c.categoria}`.toLowerCase().includes(q);
            const matchRarity = !rarity || c.rarita.toLowerCase() === rarity;
            const matchRole = !role || (c.stats && c.stats.role === role);
            return matchQ && matchRarity && matchRole;
        });
        
        list.sort((a, b) => {
            if (sortBy === 'nome') return a.nome.localeCompare(b.nome);
            const valA = a.stats ? (Number(a.stats[sortBy]) || 0) : 0;
            const valB = b.stats ? (Number(b.stats[sortBy]) || 0) : 0;
            return valB - valA;
        });
        
        if(!list.length){grid.innerHTML='<p class="game-hint">Nessun personaggio trovato.</p>';return}
        grid.innerHTML='';
        list.forEach(card=>{
            const selected=state.selectedTeam.includes(card.id);
            const el=document.createElement('button');
            el.type='button';
            el.className=`game-card-option ${selected?'is-selected':''}`;
            
            const stats = card.stats || {};
            const defValue = stats.defense !== undefined ? stats.defense : (stats.def !== undefined ? stats.def : 0);
            const levelVal = card.livello || card.level || 1;
            const levelText = levelVal === 6 ? 'MAX' : levelVal;
            
            el.innerHTML=`
                ${cardImg(card.img_url,card.nome)}
                <strong>${esc(card.nome)} <small style="color:var(--inv-gold);font-weight:normal;">Lv.${levelText}</small></strong>
                <span class="game-card-role-badge">${esc(stats.role || 'DPS')}</span>
                <div class="game-card-stats">
                    <span>HP ${stats.hp || 0}</span>
                    <span>ATK ${stats.attack || 0}</span>
                    <span>DEF ${defValue}</span>
                    <span>SPD ${stats.speed || 0}</span>
                </div>
                <div class="game-card-details-hover">
                    <div class="game-detail-section">
                        <strong>Passiva: ${esc(stats.passive_name || 'Nessuna')}</strong>
                        <p>${esc(stats.passive_desc || 'Nessun effetto passivo speciale.')}</p>
                    </div>
                    <div class="game-detail-section">
                        <strong>Speciale: ${esc(stats.special_name || 'Colpo')} (E: ${stats.special_cost || 0})</strong>
                        <p>${esc(stats.special_desc || 'Un potente attacco speciale.')}</p>
                    </div>
                    ${stats.ultimate_name ? `
                    <div class="game-detail-section" style="border-top: 1px dashed rgba(255, 255, 255, 0.08); padding-top: 0.35rem; margin-top: 0.35rem;">
                        <strong style="color: #fbbf24;">Ultimate: ${esc(stats.ultimate_name)}</strong>
                        <p>${esc(stats.ultimate_desc || 'Una mossa finale devastante.')}</p>
                    </div>
                    ` : ''}
                </div>
            `;
            el.addEventListener('click',()=>toggleTeam(card.id));
            grid.appendChild(el);
        });
        renderSelectedTeam();
    }
    function toggleTeam(id){const i=state.selectedTeam.indexOf(id); if(i>=0)state.selectedTeam.splice(i,1); else{if(state.selectedTeam.length>=3){showToast('Puoi scegliere solo 3 personaggi');return} state.selectedTeam.push(id)} renderInventory();}
    function renderSelectedTeam(){const wrap=$('#selectedTeam'), c=$('#teamCounter'); if(c)c.textContent=`${state.selectedTeam.length}/3`; if(!wrap)return; wrap.innerHTML=state.selectedTeam.map(id=>`<span class="game-selected-pill">${esc(state.inventory.find(x=>x.id===id)?.nome||'Carta')}</span>`).join('')}
    async function submitTeam(){if(state.selectedTeam.length!==3){showToast('Scegli 3 personaggi');return} try{await api('/api/game/select_team.php',{match_id:state.matchId,team:state.selectedTeam}); showToast('Team confermato'); pollState()}catch(e){showToast(e.message)}}
    function showOnly(id){['#waitingPanel','#teamPanel','#arenaPanel'].forEach(s=>{const el=$(s); if(el)el.hidden=(s!==id)})}
    function startPolling(){stopPolling(); pollState(true); state.poll=setInterval(()=>{if(!document.hidden)pollState(false)},1500)}
    function stopPolling(){if(state.poll)clearInterval(state.poll); state.poll=null;}
    async function pollState(first=false){if(!state.matchId)return; try{const d=await api('/api/game/get_match_state.php',{match_id:state.matchId},'GET'); const oldLast=state.lastActionId; state.match=d.match; renderMatch(first); const actions=state.match.actions||[]; const newest=actions.length?Number(actions[actions.length-1].id):0; if(!first && newest>oldLast){actions.filter(a=>Number(a.id)>oldLast).forEach((a,i)=>setTimeout(()=>animateAction(a),i*220));} state.lastActionId=Math.max(oldLast,newest);}catch(e){showToast(e.message)}}
    function myId(){return state.match?.viewer_id}
    function isSpectator(){return state.match?.viewer_role === 'spectator'}
    function playerById(uid){const m=state.match;if(!m||uid===null||uid===undefined)return null; if(Number(m.player1_id)===Number(uid))return m.players?.player1||null; if(Number(m.player2_id)===Number(uid))return m.players?.player2||null; return null;}
    function enemyId(){const m=state.match; return !m?null:(Number(m.player1_id)===Number(myId())?m.player2_id:m.player1_id)}
    function battleSides(){const m=state.match;if(!m)return {leftUid:null,rightUid:null,leftPlayer:null,rightPlayer:null}; const spectator=isSpectator(); const leftUid=spectator?m.player1_id:enemyId(); const rightUid=spectator?m.player2_id:myId(); return {leftUid,rightUid,leftPlayer:playerById(leftUid),rightPlayer:playerById(rightUid)};}
    function cardsOf(uid){return (state.match?.cards||[]).filter(c=>Number(c.user_id)===Number(uid))}
    function activeOf(uid){return cardsOf(uid).find(c=>Number(c.is_active)&&!Number(c.is_ko))||cardsOf(uid).find(c=>!Number(c.is_ko))}
    function pct(c,m){return Math.max(0,Math.min(100,Math.round((Number(c)/Number(m))*100)||0))}
    function renderMatch(first=false){
        const m=state.match;
        if(!m)return;
        $('#arenaRoomCode') && ($('#arenaRoomCode').textContent=m.room_code);
        $('#roomCodeLabel') && ($('#roomCodeLabel').textContent=m.room_code);
        if(m.status==='waiting'){showOnly('#waitingPanel');return}
        if(m.status==='team_select'){showOnly('#teamPanel'); if(!state.inventory.length)loadInventory(); return}

        showOnly('#arenaPanel');
        const spectator = isSpectator();
        const sides = battleSides();
        const myTurn = Number(m.current_turn_user_id)===Number(myId());
        const turnPlayer = playerById(m.current_turn_user_id);

        if($('#opponentName')) $('#opponentName').innerHTML = playerTitle(sides.leftPlayer,'Player 1');
        if($('#playerName')) $('#playerName').innerHTML = playerTitle(sides.rightPlayer, spectator ? 'Player 2' : 'Tu');

        $('#matchStatus').textContent=m.status==='finished'?'Conclusa':`${modeLabel(m.mode)} · Turno ${m.turn_number}`;
        $('#turnLabel').textContent=m.status==='finished'
            ? (spectator ? 'Partita conclusa' : (Number(m.winner_id)===Number(myId())?'Hai vinto':'Hai perso'))
            : (spectator ? `Turno di ${turnPlayer?.username || 'Player'}` : (myTurn?'È il tuo turno':(m.mode==='bot'?'Turno bot':'Turno avversario')));

        renderActive('#playerActive',activeOf(sides.rightUid));
        renderActive('#opponentActive',activeOf(sides.leftUid));
        renderTeam('#playerTeam',cardsOf(sides.rightUid),!spectator && Number(sides.rightUid)===Number(myId()));
        renderTeam('#opponentTeam',cardsOf(sides.leftUid),false);
        renderLog(m.actions||[]);
        renderChat(m.chat||[]);
        renderReactions(m.reactions||[], first);
        renderSpectators();

        const specBox = $('#spectatorMode');
        if (specBox) specBox.hidden = !spectator;
        const reactionPanel = $('#reactionPanel');
        if (reactionPanel) {
            reactionPanel.hidden = !spectator;
            if (spectator && !reactionPanel.dataset.populated && m.available_emojis) {
                reactionPanel.innerHTML = '';
                
                // Emoji standard
                const standard = ['🔥', '💀', '👏', '😳', '⚡', '👀'];
                standard.forEach(emoji => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.dataset.reaction = emoji;
                    btn.textContent = emoji;
                    btn.addEventListener('click', () => sendReaction(emoji));
                    reactionPanel.appendChild(btn);
                });
                
                // Emoji custom
                m.available_emojis.forEach(emoji => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.dataset.reaction = emoji.code;
                    btn.className = 'custom-emoji-btn';
                    
                    const img = document.createElement('img');
                    img.src = emoji.url;
                    img.alt = emoji.code;
                    img.title = emoji.code;
                    img.loading = 'lazy';
                    
                    btn.appendChild(img);
                    btn.addEventListener('click', () => sendReaction(emoji.code));
                    reactionPanel.appendChild(btn);
                });
                
                reactionPanel.dataset.populated = 'true';
            }
        }
        const chatForm = $('#chatForm');
        if (chatForm) chatForm.hidden = spectator;
        const specBtn = $('[data-battle-action="special_attack"]');
        const ultBtn = $('[data-battle-action="ultimate"]');
        const myActive = activeOf(sides.rightUid);
        const isEn = window.location.pathname.includes('/en/');

        // 1. Applica disabilitazione generica per il turno e lo stato del match
        $$('[data-battle-action]').forEach(b => {
            b.disabled = spectator || !myTurn || m.status !== 'active';
        });

        // 2. Gestione specifica pulsante Speciale
        if (specBtn && myActive) {
            const cost = Number(myActive.special_cost || 2);
            const span = specBtn.querySelector('span');
            if (span) {
                span.textContent = isEn 
                    ? `More dmg · costs ${cost} energy` 
                    : `Più danno · costa ${cost} energia`;
            }
            // Disabilita se manca energia o è in cooldown
            if (Number(myActive.energy) < cost || Number(myActive.special_cooldown) > 0) {
                specBtn.disabled = true;
            }
        }

        // 3. Gestione specifica pulsante Ultimate
        if (ultBtn) {
            if (myActive && myActive.ultimate_name) {
                ultBtn.style.display = '';
                const strong = ultBtn.querySelector('strong');
                const span = ultBtn.querySelector('span');
                if (strong) strong.textContent = myActive.ultimate_name;
                if (span) span.textContent = myActive.ultimate_desc || (isEn ? 'Devastating final move' : 'Mossa finale devastante');
                
                // Condizioni Ultimate
                const canUseUlt = !spectator && myTurn && m.status === 'active' && 
                                  Number(m.turn_number) >= 6 && 
                                  Number(myActive.energy) >= Number(myActive.max_energy) && 
                                  Number(myActive.ultimate_used || 0) === 0;
                                  
                ultBtn.disabled = !canUseUlt;
                if (!canUseUlt) {
                    ultBtn.classList.add('is-disabled');
                } else {
                    ultBtn.classList.remove('is-disabled');
                }
            } else {
                ultBtn.style.display = 'none';
            }
        }

        if(m.status==='finished' && !spectator)showResult();
    }
    function roleBadge(role) {
        const emojis = {
            'Tank': '🛡️ Tank',
            'Bruiser': '⚔️🛡️ Bruiser',
            'DPS': '⚔️ DPS',
            'Burst DPS': '💥 Burst',
            'Sub DPS': '🗡️ Sub DPS',
            'Support': '🔮 Support',
            'Healer': '💚 Healer',
            'Controller': '🌀 Control',
            'Debuffer': '💀 Debuff',
            'Buffer': '✨ Buffer'
        };
        return `<span class="game-role-badge" data-role="${role || 'DPS'}">${emojis[role] || role || 'DPS'}</span>`;
    }
    function renderActive(sel,card){
        const el=$(sel); if(!el)return;
        if(!card){el.innerHTML='<p class="game-hint">Nessuna carta.</p>';return}
        const ch=card.character||{};
        el.dataset.cardId=card.id;
        
        const hpPct = pct(card.current_hp, card.max_hp);
        const shieldPct = Math.min(100, Math.round(((card.shield || 0) / card.max_hp) * 100));
        
        const effectsHtml = (card.status_effects || []).map(eff => {
            const icons = {
                'poison': '🤢',
                'bleed': '🩸',
                'regen': '🩹',
                'stun': '⚡',
                'freeze': '❄️',
                'buff_atk': '⚔️+',
                'debuff_atk': '⚔️-',
                'buff_def': '🛡️+',
                'debuff_def': '🛡️-',
                'buff_spd': '👟+',
                'debuff_spd': '👟-',
                'buff_crit_rate': '💥+',
                'buff_crit_dmg': '🔥+',
                'taunt': '🎯',
                'immunity': '🌟',
                'counter': '🔄',
                'silence': '🔇',
                'the_one_resurrect_used': '👼',
                'resurrect_used': '👼',
                'plot_armor_used': '🎬',
                'crit_ramp': '📈'
            };
            const icon = icons[eff.type] || '❓';
            const isDebuff = ['poison', 'bleed', 'stun', 'freeze', 'debuff_atk', 'debuff_def', 'debuff_spd', 'silence'].includes(eff.type);
            const cls = isDebuff ? 'is-debuff' : 'is-buff';
            const valSuffix = eff.value ? ` (${eff.value > 0 ? '+' : ''}${eff.value}%)` : '';
            const tooltipText = `${esc(eff.name)}${valSuffix} · ${eff.duration} turn${eff.duration > 1 ? 'i' : 'o'}`;
            return `<span class="game-status-badge ${cls}" data-type="${eff.type}" data-tooltip="${tooltipText}">${icon} <small>${eff.duration}t</small></span>`;
        }).join('');

        const lvlVal = card.livello || card.level || 1;
        const lvlText = lvlVal === 6 ? 'MAX' : lvlVal;

        el.innerHTML=`
            ${cardImg(ch.img_url,ch.nome)}
            <div class="game-active-name">
                <strong>${esc(ch.nome||'Carta')} <small style="color:var(--inv-gold);font-weight:normal;">Lv.${lvlText}</small></strong>
                <div class="game-active-meta">
                    <span>${esc(ch.rarita||'comune')}</span>
                    ${roleBadge(card.role)}
                </div>
            </div>
            <div class="game-hpbar">
                <span class="hp-fill" style="--value:${hpPct}%"></span>
                ${card.shield > 0 ? `<span class="shield-fill" style="--value:${shieldPct}%"></span>` : ''}
            </div>
            <small>HP ${card.current_hp}/${card.max_hp} ${card.shield > 0 ? ` (+${card.shield})` : ''}</small>
            <div class="game-energybar">
                <span style="--value:${pct(card.energy,card.max_energy)}%"></span>
            </div>
            <small>Energia ${card.energy}/${card.max_energy} · CD ${card.special_cooldown}</small>
            <div class="game-active-effects">${effectsHtml}</div>
            <div class="game-card-stats">
                <span>ATK ${card.attack}</span>
                <span>DEF ${card.defense}</span>
                <span>SPD ${card.speed}</span>
                <span>${Number(card.is_defending)?'Difesa':'Pronto'}</span>
            </div>
        `;
    }
    function renderTeam(sel,cards,mine){const el=$(sel); if(!el)return; el.innerHTML=cards.map(c=>{const ch=c.character||{}; const lvlVal = c.livello || c.level || 1; const lvlText = lvlVal === 6 ? 'MAX' : lvlVal; return `<button class="game-mini-card ${Number(c.is_active)?'is-active':''} ${Number(c.is_ko)?'is-ko':''}" data-card-id="${c.id}" type="button" ${mine&&!Number(c.is_ko)?'':'disabled'}>${cardImg(ch.img_url,ch.nome)}<strong>${esc(ch.nome||'Carta')} <small style="color:var(--inv-gold);font-weight:normal;">Lv.${lvlText}</small></strong><small>${c.current_hp}/${c.max_hp} HP</small></button>`}).join(''); if(mine && state.match?.viewer_role !== 'spectator')$$('.game-mini-card',el).forEach(btn=>btn.addEventListener('click',()=>submitBattle('switch',Number(btn.dataset.cardId))))}
    function renderLog(actions){const log=$('#battleLog'); if(!log)return; if(!actions.length){log.innerHTML='<p class="game-hint">Il log apparirà qui.</p>';return} log.innerHTML=actions.map(a=>`<div class="game-log-row"><strong>T${a.turn_number}</strong> ${esc(a.message)} ${Number(a.damage)>0?`· ${a.damage} danni`:''}</div>`).join(''); log.scrollTop=log.scrollHeight;}
    
    function renderChat(messages){
        const wrap = $('#chatMessages');
        if (!wrap) return;

        if (!messages.length) {
            wrap.innerHTML = '<p class="game-hint">Nessun messaggio.</p>';
            return;
        }

        wrap.innerHTML = messages.map(m => {
            const mine = Number(m.user_id) === Number(myId());
            return `<div class="game-chat-msg ${mine?'is-mine':''}" data-chat-id="${m.id}">
                <strong>${esc(m.username || (mine ? 'Tu' : 'Avversario'))}</strong>
                <span>${esc(m.message)}</span>
            </div>`;
        }).join('');

        const newest = messages.length ? Number(messages[messages.length - 1].id) : 0;
        if (newest > state.lastChatId) {
            wrap.scrollTop = wrap.scrollHeight;
            state.lastChatId = newest;
        }
    }

    
    function renderSpectators(){
        const count = Number(state.match?.spectator_count || 0);
        const pill = $('#spectatorPill');
        const num = $('#spectatorCount');

        if (!pill || !num) return;

        num.textContent = count;
        pill.hidden = count <= 0;

        if (count > 0) {
            pill.classList.remove('is-pulse');
            void pill.offsetWidth;
            pill.classList.add('is-pulse');
        }
    }

    function renderReactions(reactions, first){
        const arena = $('.game-board');
        if (!arena || !reactions.length) return;

        const newest = Number(reactions[reactions.length - 1].id || 0);

        if (!first) {
            reactions
                .filter(r => Number(r.id) > Number(state.lastReactionId || 0))
                .forEach((r, i) => {
                    setTimeout(() => showReactionFloat(r.reaction, r.username), i * 140);
                });
        }

        if (newest > Number(state.lastReactionId || 0)) {
            state.lastReactionId = newest;
        }
    }

    function showReactionFloat(reaction, username){
        const arena = $('.game-board');
        if (!arena) return;

        const bubble = document.createElement('div');
        bubble.className = 'game-reaction-float';

        const custom = state.match && state.match.available_emojis
            ? state.match.available_emojis.find(e => e.code === reaction)
            : null;

        let content = `<span>${esc(reaction)}</span>`;
        if (custom) {
            content = `<img src="${custom.url}" class="float-emoji-img" alt="${esc(reaction)}" />`;
        }

        bubble.innerHTML = `${content}<small>${esc(username || 'Spettatore')}</small>`;
        bubble.style.left = `${15 + Math.random() * 70}%`;
        arena.appendChild(bubble);

        setTimeout(() => bubble.remove(), 1700);
    }

    let lastReactionSent = 0;
    async function sendReaction(reaction){
        if (!state.matchId) return;

        const now = Date.now();
        if (now - lastReactionSent < 500) {
            return;
        }
        lastReactionSent = now;

        const panel = $('#reactionPanel');
        if (panel) {
            panel.classList.add('cooldown-active');
            setTimeout(() => panel.classList.remove('cooldown-active'), 500);
        }

        try {
            await api('/api/game/send_reaction.php', { match_id: state.matchId, reaction });
            await pollState(false);
        } catch(e) {
            showToast(e.message);
        }
    }

    async function sendChat(){
        const input = $('#chatInput');
        if (!input || !state.matchId) return;
        const message = input.value.trim();
        if (!message) return;

        input.value = '';

        try {
            await api('/api/game/send_chat.php', { match_id: state.matchId, message });
            await pollState(false);
        } catch(e) {
            showToast(e.message);
        }
    }

    async function submitBattle(action,target=null){try{const p={match_id:state.matchId,action}; if(target)p.target_card_id=target; await api('/api/game/submit_action.php',p); await pollState(false)}catch(e){showToast(e.message)}}
    function animateAction(a){
        injectUltimateStyles();
        console.log('animateAction triggered:', a.action_type || a.action, a);
        const actor=Number(a.actor_card_id), target=Number(a.target_card_id);
        const type=a.action_type || a.action;
        const isCrit = (a.message || '').toLowerCase().includes('critico');

        if (type === 'ultimate') {
            const arena = $('#arenaPanel');
            let actorCard = actor ? document.querySelector(`[data-card-id="${actor}"]`) : null;
            if (!actorCard) {
                const isMyAction = Number(a.user_id) === Number(state.match?.viewer_id);
                actorCard = isMyAction ? $('#playerActive') : $('#opponentActive');
            }
            
            if (arena && actorCard) {
                // Trova l'ID, nome, immagine e ruolo del personaggio per la regia
                const cardObj = state.match && state.match.cards
                    ? state.match.cards.find(c => Number(c.id) === actor)
                    : null;
                
                const charId = cardObj ? Number(cardObj.personaggio_id) : 0;
                const charName = cardObj && cardObj.character ? cardObj.character.nome : 'Eroe';
                const imgUrl = cardObj && cardObj.character ? cardObj.character.img_url : '';
                const role = cardObj ? cardObj.role : 'DPS';
                
                let label = 'ULTIMATE';
                if (a.message && a.message.includes('**')) {
                    const parts = a.message.split('**');
                    if (parts.length >= 3) {
                        label = parts[2].replace(/[!:]/g, '').trim();
                    }
                }

                // Configurazione temi per ruolo/personaggio
                const themes = {
                    'Tank': { glow: '#fbbf24', bg: 'radial-gradient(circle, rgba(45, 35, 10, 0.95) 0%, rgba(10, 5, 0, 0.98) 100%)', symbol: '🛡️' },
                    'Bruiser': { glow: '#f97316', bg: 'radial-gradient(circle, rgba(50, 20, 5, 0.95) 0%, rgba(15, 5, 0, 0.98) 100%)', symbol: '🔥' },
                    'DPS': { glow: '#ef4444', bg: 'radial-gradient(circle, rgba(60, 10, 10, 0.95) 0%, rgba(15, 0, 0, 0.98) 100%)', symbol: '⚔️' },
                    'Burst DPS': { glow: '#ec4899', bg: 'radial-gradient(circle, rgba(60, 10, 40, 0.95) 0%, rgba(20, 0, 10, 0.98) 100%)', symbol: '⚡' },
                    'Healer': { glow: '#10b981', bg: 'radial-gradient(circle, rgba(10, 45, 30, 0.95) 0%, rgba(0, 10, 5, 0.98) 100%)', symbol: '✨' },
                    'Support': { glow: '#8b5cf6', bg: 'radial-gradient(circle, rgba(35, 15, 60, 0.95) 0%, rgba(10, 0, 20, 0.98) 100%)', symbol: '🔮' },
                    'Controller': { glow: '#06b6d4', bg: 'radial-gradient(circle, rgba(10, 40, 50, 0.95) 0%, rgba(0, 10, 15, 0.98) 100%)', symbol: '❄️' }
                };
                
                // Temi specifici per ID
                const charThemes = {
                    48: { glow: '#38bdf8', bg: 'radial-gradient(circle, rgba(15, 23, 42, 0.96) 0%, rgba(2, 6, 23, 0.98) 100%)', symbol: '🌌' }, // Shorekeeper
                    87: { glow: '#0ea5e9', bg: 'radial-gradient(circle, rgba(3, 45, 76, 0.96) 0%, rgba(1, 10, 25, 0.98) 100%)', symbol: '🌊' },  // Nauz Tricheco
                    88: { glow: '#a855f7', bg: 'radial-gradient(circle, rgba(40, 10, 65, 0.96) 0%, rgba(10, 2, 20, 0.98) 100%)', symbol: '🪐' },  // Nauz Cosmic
                    141: { glow: '#ef4444', bg: 'radial-gradient(circle, rgba(80, 5, 5, 0.96) 0%, rgba(10, 0, 0, 0.98) 100%)', symbol: '😈' },    // Dante
                    142: { glow: '#60a5fa', bg: 'radial-gradient(circle, rgba(15, 23, 42, 0.96) 0%, rgba(3, 7, 18, 0.98) 100%)', symbol: '🌀' },   // Vergil
                    144: { glow: '#f97316', bg: 'radial-gradient(circle, rgba(85, 25, 0, 0.96) 0%, rgba(15, 5, 0, 0.98) 100%)', symbol: '☄️' }    // Protagonista
                };

                const theme = charThemes[charId] || themes[role] || themes['DPS'];

                // 1. Crea il contenitore del Cut-In cinematografico a tutto schermo
                const cutin = document.createElement('div');
                cutin.className = `ult-cutin-container ult-cutin-${charId}`;
                cutin.style.setProperty('--theme-glow', theme.glow);
                
                cutin.innerHTML = `
                    <div class="ult-cutin-bg" style="background: ${theme.bg};"></div>
                    <div class="ult-cutin-particles"></div>
                    <div class="ult-cutin-slash"></div>
                    ${imgUrl ? `<img src="${imgUrl}" class="ult-cutin-char-img" alt="${charName}">` : ''}
                    <div class="ult-cutin-banner">
                        <div class="ult-cutin-char-name">${esc(charName)}</div>
                        <div class="ult-cutin-ult-name">${esc(label)}</div>
                    </div>
                `;
                
                document.body.appendChild(cutin);
                
                // Generazione particelle animate
                const partContainer = cutin.querySelector('.ult-cutin-particles');
                if (partContainer) {
                    for (let i = 0; i < 35; i++) {
                        const p = document.createElement('div');
                        p.className = 'ult-particle';
                        p.innerText = theme.symbol;
                        p.style.fontSize = `${Math.random() * 20 + 15}px`;
                        p.style.left = `${Math.random() * 100}%`;
                        p.style.top = `${Math.random() * 100}%`;
                        p.style.setProperty('--dx', `${(Math.random() - 0.5) * 600}px`);
                        p.style.setProperty('--dy', `${(Math.random() - 0.5) * 600}px`);
                        p.style.animationDelay = `${Math.random() * 0.5}s`;
                        p.style.animationDuration = `${Math.random() * 1.0 + 0.8}s`;
                        partContainer.appendChild(p);
                    }
                }
                
                // Attiva la transizione di ingresso
                setTimeout(() => cutin.classList.add('active'), 20);
                
                // Avvio dello zoom cinematografico sulla carta in background
                arena.classList.add('fx-ultimate-bg');
                arena.classList.add('active-cinema');
                actorCard.classList.add('fx-ultimate-cinema');

                // Riferimento al flash overlay
                let flash = $('.fx-ultimate-flash');
                if (!flash) {
                    flash = document.createElement('div');
                    flash.className = 'fx-ultimate-flash';
                    document.body.appendChild(flash);
                }

                // Impatto cinematografico (1400ms): Flash, Terremoto, Rimozione Cut-In e Danni
                setTimeout(() => {
                    // Rimozione fluida del Cut-In
                    cutin.classList.remove('active');
                    setTimeout(() => cutin.remove(), 400);

                    // Flash dello schermo ad alto impatto
                    flash.classList.remove('flash-active');
                    void flash.offsetWidth;
                    flash.classList.add('flash-active');
                    
                    // Camera Shake violento
                    arena.classList.add('fx-camera-shake');
                    
                    // Ripristino camera e telecamera
                    actorCard.classList.remove('fx-ultimate-cinema');
                    arena.classList.remove('active-cinema');
                    arena.classList.remove('fx-ultimate-bg');
                    
                    // Mostra il banner del danno/cura
                    showActionBanner(a, label);

                    // Esecuzione effetti di danno/cura
                    if (target && Number(a.damage) > 0) {
                        flashCard(target, isCrit ? 'fx-crit-hit' : 'fx-hit', `-${a.damage}`, true, isCrit);
                    } else if (target) {
                        flashCard(target, 'fx-charge', 'UPGRADE!', false, false);
                    }
                }, 1400);
                
                // Cleanup del camera shake
                setTimeout(() => {
                    arena.classList.remove('fx-camera-shake');
                    flash.classList.remove('flash-active');
                }, 2000);
                
                return;
            }
        }

        const actorCls=type==='special_attack'?'fx-special':type==='defend'?'fx-defend':type==='charge'?'fx-charge':type==='switch'?'fx-switch':'fx-attack';
        const label=type==='charge'?'+ energia':type==='defend'?'difesa':type==='special_attack'?'speciale':type==='switch'?'cambio':'attacco';
        showActionBanner(a, label);
        if(actor) flashCard(actor,actorCls,label);
        if(target&&Number(a.damage)>0) {
            flashCard(target, isCrit ? 'fx-crit-hit' : 'fx-hit', `-${a.damage}`, true, isCrit);
        }
        const fx=$('#gameFx');
        if(fx&&type==='special_attack'){
            fx.classList.remove('is-special');void fx.offsetWidth;fx.classList.add('is-special');
            setTimeout(()=>fx.classList.remove('is-special'),700);
        }
    }

    function injectUltimateStyles() {
        if ($('#ult-injected-styles')) return;
        const style = document.createElement('style');
        style.id = 'ult-injected-styles';
        style.innerHTML = `
            .ult-cutin-container {
                position: fixed;
                inset: 0;
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.3s cubic-bezier(0.25, 1, 0.5, 1);
            }
            .ult-cutin-container.active {
                opacity: 1;
            }
            .ult-cutin-bg {
                position: absolute;
                inset: 0;
                z-index: 1;
                animation: cutinBgFade 1.4s cubic-bezier(0.25, 1, 0.5, 1) forwards;
            }
            @keyframes cutinBgFade {
                0% { opacity: 0; }
                15% { opacity: 1; }
                85% { opacity: 1; }
                100% { opacity: 0; }
            }
            .ult-cutin-particles {
                position: absolute;
                inset: 0;
                z-index: 2;
                pointer-events: none;
            }
            .ult-cutin-slash {
                position: absolute;
                width: 250%;
                height: 150px;
                background: linear-gradient(90deg, transparent, #fff 30%, #fff 70%, transparent);
                box-shadow: 0 0 50px var(--theme-glow, #d4af37), 0 0 100px var(--theme-glow, #d4af37);
                transform: rotate(-12deg) translateY(-600px);
                z-index: 3;
                opacity: 0;
            }
            .ult-cutin-container.active .ult-cutin-slash {
                animation: cutinSlash 1.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }
            @keyframes cutinSlash {
                0% { transform: rotate(-12deg) translateY(-500px); opacity: 0; }
                15% { opacity: 0.9; }
                45% { opacity: 1; }
                80% { opacity: 0.9; }
                100% { transform: rotate(-12deg) translateY(500px); opacity: 0; }
            }
            .ult-cutin-char-img {
                position: absolute;
                height: 115%;
                max-height: 900px;
                object-fit: contain;
                right: 5%;
                bottom: -50px;
                z-index: 4;
                opacity: 0;
                transform: scale(1.25) translateX(250px) rotate(4deg);
                filter: drop-shadow(0 0 35px var(--theme-glow, #d4af37)) blur(10px);
            }
            .ult-cutin-container.active .ult-cutin-char-img {
                animation: cutinImg 1.4s cubic-bezier(0.16, 1, 0.3, 1) 0.05s forwards;
            }
            @keyframes cutinImg {
                0% { opacity: 0; transform: scale(1.25) translateX(250px) rotate(4deg); filter: drop-shadow(0 0 35px var(--theme-glow, #d4af37)) blur(15px); }
                20% { opacity: 1; filter: drop-shadow(0 0 35px var(--theme-glow, #d4af37)) blur(0); }
                80% { opacity: 1; filter: drop-shadow(0 0 35px var(--theme-glow, #d4af37)) blur(0); }
                100% { opacity: 0; transform: scale(1.1) translateX(-80px) rotate(1deg); filter: drop-shadow(0 0 35px var(--theme-glow, #d4af37)) blur(10px); }
            }
            .ult-cutin-banner {
                position: absolute;
                left: 10%;
                top: 50%;
                transform: translateY(-50%) skewX(-12deg) translateX(-150px);
                z-index: 5;
                opacity: 0;
                display: flex;
                flex-direction: column;
            }
            .ult-cutin-container.active .ult-cutin-banner {
                animation: cutinText 1.4s cubic-bezier(0.16, 1, 0.3, 1) 0.1s forwards;
            }
            @keyframes cutinText {
                0% { opacity: 0; transform: translateY(-50%) skewX(-12deg) translateX(-150px); }
                20% { opacity: 1; transform: translateY(-50%) skewX(-12deg) translateX(0); }
                80% { opacity: 1; transform: translateY(-50%) skewX(-12deg) translateX(0); }
                100% { opacity: 0; transform: translateY(-50%) skewX(-12deg) translateX(80px); }
            }
            .ult-cutin-char-name {
                font-size: 2.8rem;
                font-weight: 900;
                text-transform: uppercase;
                color: #ffffff;
                text-shadow: 0 0 15px rgba(255, 255, 255, 0.5), 0 0 30px rgba(255, 255, 255, 0.3);
                letter-spacing: 5px;
                line-height: 1;
            }
            .ult-cutin-ult-name {
                font-size: 4.5rem;
                font-weight: 900;
                text-transform: uppercase;
                color: var(--theme-glow, #d4af37);
                text-shadow: 0 0 25px var(--theme-glow, #d4af37), 0 0 50px rgba(0, 0, 0, 0.5);
                letter-spacing: 7px;
                margin-top: 15px;
                line-height: 1.1;
            }
            .ult-particle {
                position: absolute;
                pointer-events: none;
                user-select: none;
                z-index: 6;
                animation: particleFly 1.4s cubic-bezier(0.1, 0.8, 0.3, 1) forwards;
            }
            @keyframes particleFly {
                0% { transform: translate(0, 0) rotate(0deg) scale(0.2); opacity: 0; }
                15% { opacity: 0.9; }
                80% { opacity: 0.9; }
                100% { transform: translate(var(--dx), var(--dy)) rotate(360deg) scale(1.5); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    }

    function showActionBanner(a,label){
        const arena = $('#arenaPanel');
        if (!arena) return;
        const old = arena.querySelector('.game-action-banner');
        if (old) old.remove();

        const mine = Number(a.user_id) === Number(myId());
        const actorPlayer = playerById(a.user_id);
        const actorName = mine ? 'Tu' : (actorPlayer?.username || (Number(a.user_id)===0 ? 'Bot' : 'Player'));
        const banner = document.createElement('div');
        banner.className = `game-action-banner ${mine ? 'is-mine' : 'is-enemy'}`;
        banner.innerHTML = `<strong>${esc(actorName)}</strong><span>${esc(label)}${Number(a.damage)>0 ? ` · ${Number(a.damage)} danni` : ''}</span>`;
        arena.appendChild(banner);

        setTimeout(() => banner.remove(), 1300);
    }

    function flashCard(id,cls,label,damage=false,isCrit=false){
        $$(`[data-card-id="${id}"]`).forEach(el=>{
            el.classList.remove(cls);void el.offsetWidth;el.classList.add(cls);
            const tag=document.createElement('span');
            tag.className=`game-floating-feedback ${damage?'is-damage':'is-buff'} ${isCrit?'is-crit':''}`;
            tag.textContent=label;
            el.appendChild(tag);
            setTimeout(()=>{el.classList.remove(cls);tag.remove()},1050);
        });
    }
    function showResult(){if(state.resultShown)return; state.resultShown=true; const m=state.match, modal=$('#resultModal'); if(!modal)return; const win=Number(m.winner_id)===Number(myId()); $('#resultKicker').textContent=m.mode==='ranked'?'Ranked conclusa':(m.mode==='bot'?'Offline conclusa':'Partita conclusa'); $('#resultTitle').textContent=win?'Hai vinto':'Hai perso'; $('#resultText').textContent=m.mode==='bot'?(win?'Hai battuto il bot.':'Il bot ti ha mandato KO.'):(win?'Team avversario KO.':'Il tuo team è andato KO.'); const box=$('#rankedFeedback'); if(m.mode==='ranked'&&m.ranked_result&&box){const rr=m.ranked_result; box.hidden=false; box.innerHTML=`<div class="${rr.viewer_delta>=0?'is-plus':'is-minus'}"><strong>Tu</strong><b>${rr.viewer_delta>=0?'+':''}${rr.viewer_delta}</b>${rankBadge(rr.viewer_rank_after)}</div><div class="${rr.opponent_delta>=0?'is-plus':'is-minus'}"><strong>Avversario</strong><b>${rr.opponent_delta>=0?'+':''}${rr.opponent_delta}</b>${rankBadge(rr.opponent_rank_after)}</div>`} modal.hidden=false;}
    async function forfeit(){const lang=window.location.pathname.includes('/en/')?'en':'it';if(!state.matchId){window.location.href=`/${lang}/game/lobby.php`;return} if(!confirm('Vuoi abbandonare?'))return; try{await api('/api/game/forfeit_match.php',{match_id:state.matchId}); window.location.href=`/${lang}/game/lobby.php`}catch(e){showToast(e.message)}}

    function bindCommon(){ $$('[data-action="find-match"]').forEach(b=>b.addEventListener('click',()=>findMatch(b.dataset.mode||'casual'))); $('[data-action="create-bot"]')?.addEventListener('click',createBotMatch); $('[data-action="create-private"]')?.addEventListener('click',createPrivate); $('[data-action="join-code"]')?.addEventListener('click',joinCode); $('[data-action="active-match"]')?.addEventListener('click',activeMatch); $('[data-action="load-ranking"]')?.addEventListener('click',loadRanking); $('[data-action="load-live"]')?.addEventListener('click',loadLiveMatches); $$('[data-action="forfeit"]').forEach(b=>b.addEventListener('click',forfeit)); }
    document.addEventListener('DOMContentLoaded',()=>{bindCommon(); if(page==='duel-lobby'){loadProfile();loadRanking();loadLiveMatches();setInterval(loadRanking,30000);setInterval(loadLiveMatches,10000)} if(page==='duel-arena'){if(!state.matchId){showToast('Match mancante');return} $('#cardSearch')?.addEventListener('input',renderInventory); $('[data-action="submit-team"]')?.addEventListener('click',submitTeam); $$('[data-battle-action]').forEach(b=>b.addEventListener('click',()=>submitBattle(b.dataset.battleAction))); $('#chatForm')?.addEventListener('submit',(e)=>{e.preventDefault();sendChat();}); $$('[data-reaction]').forEach(b=>b.addEventListener('click',()=>sendReaction(b.dataset.reaction))); startPolling();}});
})();
