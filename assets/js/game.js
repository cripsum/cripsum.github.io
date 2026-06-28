(() => {
    'use strict';
    if (window.__cripsumDuelV16) return;
    window.__cripsumDuelV16 = true;
    const lang = window.location.pathname.includes('/en/') ? 'en' : 'it';
    const gt = {
        it: {
            not_loaded: 'Profilo non caricato.',
            empty_rank: 'Classifica vuota.',
            rank_error: 'Classifica non caricata.',
            no_live: 'Nessuna partita live da guardare.',
            live_error: 'Partite live non caricate.',
            finding_ranked: 'Cerco ranked...',
            finding_casual: 'Cerco casual...',
            creating_bot: 'Creo partita offline...',
            creating_private: 'Creo stanza privata...',
            pw_error: 'Inserisci una password da almeno 3 caratteri',
            enter_code: 'Inserisci codice stanza',
            no_active: 'Nessuna partita attiva',
            no_chars_found: 'Nessun personaggio trovato.',
            three_chars_only: 'Puoi scegliere solo 3 personaggi',
            choose_three: 'Scegli 3 personaggi',
            team_confirmed: 'Team confermato',
            no_card: 'Nessuna carta.',
            defending: 'Difesa',
            ready: 'Pronto',
            finished_status: 'Conclusa',
            spectator_finished: 'Partita conclusa',
            viewer_win: 'Hai vinto',
            viewer_loss: 'Hai perso',
            turn_prefix: 'Turno di',
            your_turn: 'È il tuo turno',
            bot_turn: 'Turno bot',
            opponent_turn: 'Turno avversario',
            log_empty: 'Il log apparirà qui.',
            forfeit_confirm: 'Vuoi abbandonare?',
            passive_label: 'Passiva',
            special_label: 'Speciale',
            ultimate_label: 'Ultimate',
            no_passive_effect: 'Nessun effetto passivo speciale.',
            default_special_desc: 'Un potente attacco speciale.',
            default_ultimate_desc: 'Una mossa finale devastante.',
            action_energy: '+ energia',
            action_defense: 'difesa',
            action_special: 'speciale',
            action_switch: 'cambio',
            action_attack: 'attacco',
            searching_opponent: 'Cerco avversario...',
            ranked_finished: 'Ranked conclusa',
            offline_finished: 'Offline conclusa',
            match_finished: 'Partita conclusa',
            you: 'Tu',
            opponent: 'Avversario',
            bot_win: 'Hai battuto il bot.',
            bot_loss: 'Il bot ti ha mandato KO.',
            pvp_win: 'Team avversario KO.',
            pvp_loss: 'Il tuo team è andato KO.',
            match_missing: 'Match mancante'
        },
        en: {
            not_loaded: 'Profile not loaded.',
            empty_rank: 'Leaderboard empty.',
            rank_error: 'Leaderboard not loaded.',
            no_live: 'No live matches to watch.',
            live_error: 'Live matches not loaded.',
            finding_ranked: 'Searching ranked...',
            finding_casual: 'Searching casual...',
            creating_bot: 'Creating offline match...',
            creating_private: 'Creating private room...',
            pw_error: 'Enter a password with at least 3 characters',
            enter_code: 'Enter room code',
            no_active: 'No active match',
            no_chars_found: 'No character found.',
            three_chars_only: 'You can only choose 3 characters',
            choose_three: 'Choose 3 characters',
            team_confirmed: 'Team confirmed',
            no_card: 'No card.',
            defending: 'Defending',
            ready: 'Ready',
            finished_status: 'Finished',
            spectator_finished: 'Match finished',
            viewer_win: 'You won',
            viewer_loss: 'You lost',
            turn_prefix: 'Turn of',
            your_turn: "It's your turn",
            bot_turn: 'Bot turn',
            opponent_turn: 'Opponent turn',
            log_empty: 'Log will appear here.',
            forfeit_confirm: 'Do you want to forfeit?',
            passive_label: 'Passive',
            special_label: 'Special',
            ultimate_label: 'Ultimate',
            no_passive_effect: 'No special passive effect.',
            default_special_desc: 'A powerful special attack.',
            default_ultimate_desc: 'A devastating finishing move.',
            action_energy: '+ energy',
            action_defense: 'defense',
            action_special: 'special',
            action_switch: 'switch',
            action_attack: 'attack',
            searching_opponent: 'Searching for opponent...',
            ranked_finished: 'Ranked finished',
            offline_finished: 'Offline finished',
            match_finished: 'Match finished',
            you: 'You',
            opponent: 'Opponent',
            bot_win: 'You beat the bot.',
            bot_loss: 'The bot knocked you out.',
            pvp_win: 'Opponent team knocked out.',
            pvp_loss: 'Your team was knocked out.',
            match_missing: 'Match missing'
        }
    }[lang];

    const page = document.body?.dataset.page || '';
    const state = { matchId: Number(document.body?.dataset.matchId || 0) || null, roomCode:null, inventory:[], selectedTeam:[], match:null, poll:null, lastActionId:0, resultShown:false, lastChatId:0, lastReactionId:0 };
    const $ = (s,r=document)=>r.querySelector(s);
    const $$ = (s,r=document)=>Array.from(r.querySelectorAll(s));
    let toastTimer=null;

    function esc(v){return String(v ?? '').replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));}
    function showToast(msg){const t=$('#gameToast'); if(!t)return; t.querySelector('span').textContent=msg; t.hidden=false; requestAnimationFrame(()=>t.classList.add('is-visible')); clearTimeout(toastTimer); toastTimer=setTimeout(()=>{t.classList.remove('is-visible');setTimeout(()=>t.hidden=true,180)},2200)}
    function setMatchmakingLoading(on, text = gt.searching_opponent){
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
            box.innerHTML='<p class="game-hint">' + gt.not_loaded + '</p>'
        }
     }
    async function loadRanking(){const wrap=$('#rankingList'); if(!wrap)return; try{const d=await api('/api/game/get_ranking.php',{},'GET'); const rows=d.ranking||[]; if(!rows.length){wrap.innerHTML='<p class="game-hint">' + gt.empty_rank + '</p>';return} wrap.innerHTML=rows.map((r,i)=>`<div class="game-rank-row"><strong>#${i+1}</strong><span class="game-rank-name">${rankBadge(r.rank)} ${esc(r.username)}</span><span class="game-rank-meta"><b>${r.rating}</b></span></div>`).join('')}catch(e){wrap.innerHTML='<p class="game-hint">' + gt.rank_error + '</p>';}}
    
    async function loadLiveMatches(){
        const wrap = $('#liveMatchesList');
        if (!wrap) return;

        try {
            const d = await api('/api/game/live_matches.php', {}, 'GET');
            const rows = d.matches || [];

            if (!rows.length) {
                wrap.innerHTML = '<p class="game-hint">' + gt.no_live + '</p>';
                return;
            }

            const lang = window.location.pathname.includes('/en/') ? 'en' : 'it';
            wrap.innerHTML = rows.map(m => `
                <a class="game-live-row" href="/${lang}/game/arena.php?match_id=${encodeURIComponent(m.id)}">
                    <div>
                        <strong>${esc(m.player1)} vs ${esc(m.player2)}</strong>
                        <span>${esc(m.mode)} · ${lang === 'en' ? 'turn' : 'turno'} ${m.turn_number}</span>
                    </div>
                    <em><i class="fa-solid fa-eye"></i> ${m.spectator_count}</em>
                </a>
            `).join('');
        } catch(e) {
            wrap.innerHTML = '<p class="game-hint">' + gt.live_error + '</p>';
        }
    }

    async function findMatch(mode){setMatchmakingLoading(true, mode==='ranked'?gt.finding_ranked:gt.finding_casual);try{const d=await api('/api/game/find_match.php',{mode}); goArena(d.match_id)}catch(e){setMatchmakingLoading(false);showToast(e.message)}}
    async function createBotMatch(){setMatchmakingLoading(true,gt.creating_bot);try{const d=await api('/api/game/create_match.php',{mode:'bot'}); goArena(d.match_id)}catch(e){setMatchmakingLoading(false);showToast(e.message)}}
    async function createPrivate(){const password=($('#privatePasswordInput')?.value||'').trim(); if(password.length<3){showToast(gt.pw_error);return} const max_level=$('#privateMaxLevelCheckbox')?.checked?1:0; setMatchmakingLoading(true,gt.creating_private); try{const d=await api('/api/game/create_match.php',{mode:'private',password,max_level}); goArena(d.match_id)}catch(e){setMatchmakingLoading(false);showToast(e.message)}}
    async function joinCode(){const code=($('#roomCodeInput')?.value||'').trim(); const password=($('#joinPasswordInput')?.value||'').trim(); if(!code){showToast(gt.enter_code);return} try{const d=await api('/api/game/join_match.php',{room_code:code,password}); goArena(d.match_id)}catch(e){showToast(e.message)}}
    async function activeMatch(){try{const d=await api('/api/game/active_match.php',{},'GET'); if(!d.match){showToast(gt.no_active);return} goArena(d.match.id)}catch(e){showToast(e.message)}}

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
        
        if(!list.length){grid.innerHTML='<p class="game-hint">' + gt.no_chars_found + '</p>';return}
        grid.innerHTML='';
        $$('.game-card-details-hover').forEach(m=>m.remove());
        list.forEach(card=>{
            const selected=state.selectedTeam.includes(card.id);
            const el=document.createElement('div');
            const rarKey = card.rarita || 'comune';
            el.className=`game-card-wrapper rarity-${rarKey} ${selected?'is-selected':''}`;
            
            const stats = card.stats || {};
            const defValue = stats.defense !== undefined ? stats.defense : (stats.def !== undefined ? stats.def : 0);
            const levelVal = card.livello || card.level || 1;
            const levelText = levelVal === 6 ? 'MAX' : levelVal;
            
            el.innerHTML=`
                <button type="button" class="game-card-option" aria-label="${esc(card.nome)}">
                    ${cardImg(card.img_url,card.nome)}
                    <div class="game-card-main-info">
                        <strong>${esc(card.nome)} <span class="card-level-badge">Lv.${levelText}</span></strong>
                        <div class="game-card-badges">
                            <span class="game-card-rarity-badge">${esc(rarKey)}</span>
                            <span class="game-card-role-badge" data-role="${esc(stats.role || 'DPS')}">${esc(stats.role || 'DPS')}</span>
                        </div>
                    </div>
                    <div class="game-card-stats">
                        <span>HP ${stats.hp || 0}</span>
                        <span>ATK ${stats.attack || 0}</span>
                        <span>DEF ${defValue}</span>
                        <span>SPD ${stats.speed || 0}</span>
                    </div>
                </button>
                <button type="button" class="game-card-info-btn" aria-label="Info"><i class="fa-solid fa-circle-info"></i></button>
                <div class="game-card-details-hover rarity-${rarKey}">
                    <div class="game-hover-body">
                        <div class="game-hover-header">
                            <h4>${esc(card.nome)}</h4>
                            <button type="button" class="game-hover-close-btn">&times;</button>
                            <div class="game-hover-badges">
                                <span class="game-card-rarity-badge">${esc(rarKey)}</span>
                                <span class="game-card-role-badge" data-role="${esc(stats.role || 'DPS')}">${esc(stats.role || 'DPS')}</span>
                            </div>
                        </div>
                        <div class="game-detail-section">
                            <div class="game-detail-header">
                                <span class="game-detail-label passive">${gt.passive_label}</span>
                                <strong>${esc(stats.passive_name || (lang === 'en' ? 'None' : 'Nessuna'))}</strong>
                            </div>
                            <p>${esc(stats.passive_desc || gt.no_passive_effect)}</p>
                        </div>
                        <div class="game-detail-section">
                            <div class="game-detail-header">
                                <span class="game-detail-label special">${gt.special_label}</span>
                                <strong>${esc(stats.special_name || (lang === 'en' ? 'Skill' : 'Colpo'))}</strong>
                                <span class="game-detail-cost">E: ${stats.special_cost || 0}</span>
                            </div>
                            <p>${esc(stats.special_desc || gt.default_special_desc)}</p>
                        </div>
                        ${stats.ultimate_name ? `
                        <div class="game-detail-section ultimate">
                            <div class="game-detail-header">
                                <span class="game-detail-label ultimate">${gt.ultimate_label}</span>
                                <strong>${esc(stats.ultimate_name)}</strong>
                            </div>
                            <p>${esc(stats.ultimate_desc || gt.default_ultimate_desc)}</p>
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
            el.querySelector('.game-card-option').addEventListener('click',()=>toggleTeam(card.id));
            
            const infoBtn = el.querySelector('.game-card-info-btn');
            const hover = el.querySelector('.game-card-details-hover');
            if (infoBtn && hover) {
                infoBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    $$('.game-card-details-hover.is-active').forEach(h => {
                        if (h !== hover) h.classList.remove('is-active');
                    });
                    hover.classList.toggle('is-active');
                });
            }
            
            if (hover) {
                hover.addEventListener('click', (e) => {
                    if (e.target === hover) {
                        e.stopPropagation();
                        hover.classList.remove('is-active');
                    }
                });
            }
            
            const closeBtn = hover ? hover.querySelector('.game-hover-close-btn') : null;
            if (closeBtn && hover) {
                closeBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    hover.classList.remove('is-active');
                });
            }
            
            if (hover) document.body.appendChild(hover);
            grid.appendChild(el);
        });
        renderSelectedTeam();
    }
    function toggleTeam(id){const i=state.selectedTeam.indexOf(id); if(i>=0)state.selectedTeam.splice(i,1); else{if(state.selectedTeam.length>=3){showToast(gt.three_chars_only);return} state.selectedTeam.push(id)} renderInventory();}
    function renderSelectedTeam(){const wrap=$('#selectedTeam'), c=$('#teamCounter'); if(c)c.textContent=`${state.selectedTeam.length}/3`; if(!wrap)return; wrap.innerHTML=state.selectedTeam.map(id=>`<span class="game-selected-pill">${esc(state.inventory.find(x=>x.id===id)?.nome||'Carta')}</span>`).join('')}
    async function submitTeam(){if(state.selectedTeam.length!==3){showToast(gt.choose_three);return} try{await api('/api/game/select_team.php',{match_id:state.matchId,team:state.selectedTeam}); showToast(gt.team_confirmed); pollState()}catch(e){showToast(e.message)}}
    function showOnly(id){['#waitingPanel','#teamPanel','#arenaPanel'].forEach(s=>{const el=$(s); if(el)el.hidden=(s!==id)})}
    function startPolling(){stopPolling(); pollState(true); state.poll=setInterval(()=>{if(!document.hidden)pollState(false)},1500)}
    function stopPolling(){if(state.poll)clearInterval(state.poll); state.poll=null;}
    async function pollState(first=false){
        if(!state.matchId)return; 
        try{
            const d=await api('/api/game/get_match_state.php',{match_id:state.matchId},'GET'); 
            const oldLast=state.lastActionId; 
            state.match=d.match; 
            renderMatch(first); 
            const actions=state.match.actions||[]; 
            const newest=actions.length?Number(actions[actions.length-1].id):0; 
            if(!first && newest>oldLast){
                const newActions=actions.filter(a=>Number(a.id)>oldLast); 
                for(const act of newActions){
                    await animateAction(act);
                }
            } 
            state.lastActionId=Math.max(oldLast,newest);
            if(state.match.status==='finished' && state.match.viewer_role !== 'spectator'){
                showResult();
            }
        }catch(e){
            showToast(e.message);
        }
    }
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
        if($('#playerName')) $('#playerName').innerHTML = playerTitle(sides.rightPlayer, spectator ? 'Player 2' : (lang === 'en' ? 'You' : 'Tu'));

        $('#matchStatus').textContent=m.status==='finished'?gt.finished_status:`${modeLabel(m.mode)} · ${lang === 'en' ? 'Turn' : 'Turno'} ${m.turn_number}`;
        $('#turnLabel').textContent=m.status==='finished'
            ? (spectator ? gt.spectator_finished : (Number(m.winner_id)===Number(myId())?gt.viewer_win:gt.viewer_loss))
            : (spectator ? `${gt.turn_prefix} ${turnPlayer?.username || 'Player'}` : (myTurn?gt.your_turn:(m.mode==='bot'?gt.bot_turn:gt.opponent_turn)));

        renderActive('#playerActive',activeOf(sides.rightUid));
        renderActive('#opponentActive',activeOf(sides.leftUid));
        renderCombatKit(activeOf(sides.rightUid));
        renderTeam('#playerTeam',cardsOf(sides.rightUid),!spectator && Number(sides.rightUid)===Number(myId()) && myTurn);
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
        if(!card){el.innerHTML='<p class="game-hint">' + gt.no_card + '</p>';return}
        const ch=card.character||{};
        el.dataset.cardId=card.id;
        
        const hpPct = pct(card.current_hp, card.max_hp);
        const shieldPct = Math.min(100, Math.round(((card.shield || 0) / card.max_hp) * 100));
        
        const textTurn = lang === 'en' ? 'turn' : 'turni';
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
            const suffixTurn = lang === 'en' ? (eff.duration > 1 ? 's' : '') : (eff.duration > 1 ? 'i' : 'o');
            const tooltipText = `${esc(eff.name)}${valSuffix} · ${eff.duration} ${lang === 'en' ? 'turn' : 'turn'}${suffixTurn}`;
            return `<span class="game-status-badge ${cls}" data-type="${eff.type}" data-tooltip="${tooltipText}">${icon} <small>${eff.duration}t</small></span>`;
        }).join('');

        const lvlVal = card.livello || card.level || 1;
        const lvlText = lvlVal === 6 ? 'MAX' : lvlVal;

        el.innerHTML=`
            ${cardImg(ch.img_url,ch.nome)}
            <div class="game-active-name">
                <strong>${esc(ch.nome||'Carta')} <small style="color:var(--inv-gold);font-weight:normal;">Lv.${lvlText}</small></strong>
                <div class="game-active-meta">
                    <span>${esc(ch.rarita||(lang === 'en' ? 'common' : 'comune'))}</span>
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
            <small>${lang === 'en' ? 'Energy' : 'Energia'} ${card.energy}/${card.max_energy} · CD ${card.special_cooldown}</small>
            <div class="game-active-effects">${effectsHtml}</div>
            <div class="game-card-stats">
                <span>ATK ${card.attack}</span>
                <span>DEF ${card.defense}</span>
                <span>SPD ${card.speed}</span>
                <span>${Number(card.is_defending)?gt.defending:gt.ready}</span>
            </div>
        `;
    }
    function renderCombatKit(card) {
        const panel = $('#combatKitPanel');
        if (!panel) return;
        if (!card) {
            panel.innerHTML = `
                <div class="game-log-title">
                    <i class="fa-solid fa-address-card"></i> ${lang === 'en' ? 'Active Character Kit' : 'Kit Personaggio in Campo'}
                </div>
                <div class="game-combat-kit-empty" style="padding: 1rem; color: var(--game-muted); font-size: 0.9rem;">
                    ${lang === 'en' ? 'No active character' : 'Nessun personaggio attivo'}
                </div>
            `;
            return;
        }
        
        const ch = card.character || {};
        const stats = card.stats || {};
        
        // Cooldown and Energy Cost details
        const specCost = card.special_cost || 0;
        const specCdMax = card.special_cooldown_max || 0;
        const specCdCur = card.special_cooldown !== undefined ? card.special_cooldown : 0;
        
        // Status effects mapping
        const effectsHtml = (card.status_effects || []).map(eff => {
            const valSuffix = eff.value ? ` (${eff.value > 0 ? '+' : ''}${eff.value}%)` : '';
            return `<div class="game-kit-effect-item"><strong>${esc(eff.name)}${valSuffix}</strong>: ${eff.duration} ${lang === 'en' ? (eff.duration > 1 ? 'turns left' : 'turn left') : (eff.duration > 1 ? 'turni rimasti' : 'turno rimasto')}</div>`;
        }).join('');
        
        panel.innerHTML = `
            <div class="game-log-title">
                <i class="fa-solid fa-address-card"></i> ${lang === 'en' ? 'Active Character Kit' : 'Kit Personaggio in Campo'}
            </div>
            
            <div class="game-kit-body">
                <div class="game-kit-profile">
                    <strong>${esc(ch.nome || 'Carta')}</strong>
                    <span class="game-kit-badge-role" data-role="${esc(card.role || 'DPS')}">${esc(card.role || 'DPS')}</span>
                </div>
                
                <div class="game-kit-stats-grid">
                    <div class="game-kit-stat-box">HP: <span>${card.current_hp}/${card.max_hp}</span></div>
                    <div class="game-kit-stat-box">ATK: <span>${card.attack}</span></div>
                    <div class="game-kit-stat-box">DEF: <span>${card.defense}</span></div>
                    <div class="game-kit-stat-box">SPD: <span>${card.speed}</span></div>
                    <div class="game-kit-stat-box">${lang === 'en' ? 'Crit Rate' : 'Critico'}: <span>${card.crit_rate}%</span></div>
                    <div class="game-kit-stat-box">${lang === 'en' ? 'Crit Dmg' : 'Danno Crit'}: <span>${card.crit_dmg}%</span></div>
                </div>
                
                <div class="game-kit-skills">
                    <div class="game-kit-skill-section">
                        <div class="game-kit-skill-header">
                            <span class="game-kit-skill-type passive">${lang === 'en' ? 'PASSIVE' : 'PASSIVA'}</span>
                            <strong>${esc(card.passive_name || (lang === 'en' ? 'None' : 'Nessuna'))}</strong>
                        </div>
                        <p class="game-kit-skill-desc">${esc(card.passive_desc || gt.no_passive_effect)}</p>
                    </div>
                    
                    <div class="game-kit-skill-section">
                        <div class="game-kit-skill-header">
                            <span class="game-kit-skill-type special">${lang === 'en' ? 'SPECIAL' : 'SPECIALE'}</span>
                            <strong>${esc(card.special_name || (lang === 'en' ? 'Skill' : 'Colpo'))}</strong>
                        </div>
                        <div class="game-kit-skill-meta">
                            <span>E: ${specCost}</span>
                            <span>CD: ${specCdCur}/${specCdMax}</span>
                        </div>
                        <p class="game-kit-skill-desc">${esc(card.special_desc || gt.default_special_desc)}</p>
                    </div>
                    
                    ${card.ultimate_name ? `
                    <div class="game-kit-skill-section ultimate">
                        <div class="game-kit-skill-header">
                            <span class="game-kit-skill-type ultimate">${lang === 'en' ? 'ULTIMATE' : 'ULTIMATE'}</span>
                            <strong>${esc(card.ultimate_name)}</strong>
                        </div>
                        <p class="game-kit-skill-desc">${esc(card.ultimate_desc || gt.default_ultimate_desc)}</p>
                    </div>
                    ` : ''}
                </div>
                
                ${effectsHtml ? `
                <div class="game-kit-effects">
                    <div class="game-kit-effects-title">${lang === 'en' ? 'Active Effects' : 'Effetti Attivi'}</div>
                    <div class="game-kit-effects-list">${effectsHtml}</div>
                </div>
                ` : ''}
            </div>
        `;
    }
    function renderTeam(sel,cards,mine){const el=$(sel); if(!el)return; el.innerHTML=cards.map(c=>{const ch=c.character||{}; const lvlVal = c.livello || c.level || 1; const lvlText = lvlVal === 6 ? 'MAX' : lvlVal; return `<button class="game-mini-card ${Number(c.is_active)?'is-active':''} ${Number(c.is_ko)?'is-ko':''}" data-card-id="${c.id}" type="button" ${mine&&!Number(c.is_ko)&&!Number(c.is_active)?'':'disabled'}>${cardImg(ch.img_url,ch.nome)}<strong>${esc(ch.nome||'Carta')} <small style="color:var(--inv-gold);font-weight:normal;">Lv.${lvlText}</small></strong><small>${c.current_hp}/${c.max_hp} HP</small></button>`}).join(''); if(mine && state.match?.viewer_role !== 'spectator')$$('.game-mini-card',el).forEach(btn=>btn.addEventListener('click',()=>submitBattle('switch',Number(btn.dataset.cardId))))}
    function renderLog(actions){
        const log=$('#battleLog');
        if(!log)return;
        if(!actions.length){log.innerHTML='<p class="game-hint">' + gt.log_empty + '</p>';return}
        const isAtBottom = (!log.dataset.initialized) || (log.scrollHeight - log.clientHeight - log.scrollTop < 40);
        log.innerHTML=actions.map(a=>`<div class="game-log-row"><strong>T${a.turn_number}</strong> ${esc(a.message)} ${Number(a.damage)>0?`· ${a.damage} ${lang === 'en' ? 'damage' : 'danni'}`:''}</div>`).join('');
        if(isAtBottom){
            log.scrollTop=log.scrollHeight;
        }
        log.dataset.initialized = 'true';
    }
    
    function renderChat(messages){
        const wrap = $('#chatMessages');
        if (!wrap) return;

        if (!messages.length) {
            wrap.innerHTML = '<p class="game-hint">' + (lang === 'en' ? 'No messages.' : 'Nessun messaggio.') + '</p>';
            return;
        }

        const isAtBottom = (!wrap.dataset.initialized) || (wrap.scrollHeight - wrap.clientHeight - wrap.scrollTop < 40);

        wrap.innerHTML = messages.map(m => {
            const mine = Number(m.user_id) === Number(myId());
            return `<div class="game-chat-msg ${mine?'is-mine':''}" data-chat-id="${m.id}">
                <strong>${esc(m.username || (mine ? gt.you : gt.opponent))}</strong>
                <span>${esc(m.message)}</span>
            </div>`;
        }).join('');

        const newest = messages.length ? Number(messages[messages.length - 1].id) : 0;
        if (newest > state.lastChatId) {
            if (isAtBottom) {
                wrap.scrollTop = wrap.scrollHeight;
            }
            state.lastChatId = newest;
        }
        wrap.dataset.initialized = 'true';
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

    function playUltimateAudio(charId) {
        const defaultSrc = '/audio/ultimates/default.mp3';
        const customSrc = `/audio/ultimates/${charId}.mp3`;
        const audio = new Audio();
        audio.src = customSrc;
        audio.volume = 0.85;
        audio.onerror = () => {
            if (audio.src !== window.location.origin + defaultSrc) {
                audio.src = defaultSrc;
                audio.play().catch(err => console.log('Fallback audio play failed:', err));
            }
        };
        audio.play().catch(err => {
            console.log('Audio play failed, waiting for fallback...', err);
        });
        return audio;
    }

    function fadeOutAudio(audio, durationMs) {
        if (!audio) return;
        const startVolume = audio.volume;
        const steps = 30;
        const stepTime = durationMs / steps;
        let currentStep = 0;
        const interval = setInterval(() => {
            currentStep++;
            const progress = currentStep / steps;
            audio.volume = Math.max(0, startVolume * (1 - progress));
            if (currentStep >= steps) {
                clearInterval(interval);
                audio.pause();
            }
        }, stepTime);
    }

    async function submitBattle(action,target=null){try{const p={match_id:state.matchId,action}; if(target)p.target_card_id=target; await api('/api/game/submit_action.php',p); await pollState(false)}catch(e){showToast(e.message)}}
    function animateAction(a){
        return new Promise((resolve) => {
            let type = a.action_type || a.action || '';
            if (!type && a.message && a.message.includes("l'**ULTIMATE**")) {
                type = 'ultimate';
            }
            const actor = Number(a.actor_card_id);
            const target = Number(a.target_card_id);
            const isCrit = (a.message || '').toLowerCase().includes('critico');

            console.log('[ANIM] animateAction called. type=' + type, 'actor=' + actor, 'full action:', JSON.stringify(a));

            if (type === 'ultimate') {
                console.log('[ANIM] >>> ULTIMATE DETECTED! Starting cinematic...');
                
                stopPolling();

                const arena = $('#arenaPanel');
                let actorCard = actor ? document.querySelector('[data-card-id="' + actor + '"]') : null;
                if (!actorCard) {
                    const isMyAction = Number(a.user_id) === Number(state.match?.viewer_id);
                    actorCard = isMyAction ? $('#playerActive') : $('#opponentActive');
                }

                const cardObj = (state.match && state.match.cards)
                    ? state.match.cards.find(c => Number(c.id) === actor)
                    : null;
                const charName = (cardObj && cardObj.character) ? cardObj.character.nome : 'Eroe';
                const imgUrl = (cardObj && cardObj.character) ? img(cardObj.character.img_url) : '';
                const role = cardObj ? (cardObj.role || 'DPS') : 'DPS';
                const charId = cardObj ? Number(cardObj.personaggio_id) : 0;

                const ultName = (cardObj && cardObj.ultimate_name) ? cardObj.ultimate_name : 'ULTIMATE';

                // Play audio
                const ultAudio = playUltimateAudio(charId);

                // ============ PER-CHARACTER CUSTOM THEMES ============
                const charThemes = {
                    46:  { glow:'#ffd700', bg:'radial-gradient(ellipse at 30% 40%,rgba(60,45,0,0.95),rgba(10,8,0,0.98))', flash:'#ffd700' },
                    48:  { glow:'#38bdf8', bg:'radial-gradient(ellipse at 70% 30%,rgba(10,30,60,0.96),rgba(2,6,23,0.98))', flash:'#38bdf8' },
                    49:  { glow:'#facc15', bg:'radial-gradient(ellipse at 50% 50%,rgba(50,40,5,0.95),rgba(8,6,0,0.98))', flash:'#facc15' },
                    50:  { glow:'#a855f7', bg:'radial-gradient(ellipse at 40% 60%,rgba(40,10,70,0.95),rgba(10,2,20,0.98))', flash:'#a855f7' },
                    64:  { glow:'#ef4444', bg:'radial-gradient(ellipse at 60% 40%,rgba(50,5,5,0.96),rgba(10,0,0,0.98))', flash:'#ef4444' },
                    75:  { glow:'#22c55e', bg:'radial-gradient(ellipse at 50% 50%,rgba(0,30,10,0.96),rgba(0,5,2,0.98))', flash:'#22c55e' },
                    76:  { glow:'#ec4899', bg:'radial-gradient(ellipse at 40% 50%,rgba(50,5,30,0.96),rgba(15,0,10,0.98))', flash:'#ec4899' },
                    86:  { glow:'#f97316', bg:'radial-gradient(ellipse at 60% 30%,rgba(50,20,0,0.96),rgba(10,5,0,0.98))', flash:'#f97316' },
                    87:  { glow:'#06b6d4', bg:'radial-gradient(ellipse at 50% 60%,rgba(0,30,50,0.96),rgba(0,8,15,0.98))', flash:'#06b6d4' },
                    88:  { glow:'#94a3b8', bg:'radial-gradient(ellipse at 50% 40%,rgba(30,30,35,0.96),rgba(5,5,8,0.98))', flash:'#e2e8f0' },
                    98:  { glow:'#f8fafc', bg:'radial-gradient(ellipse at 50% 50%,rgba(60,60,65,0.96),rgba(15,15,18,0.98))', flash:'#f8fafc' },
                    138: { glow:'#a78bfa', bg:'radial-gradient(ellipse at 30% 30%,rgba(30,10,60,0.96),rgba(5,2,15,0.98))', flash:'#a78bfa' },
                    139: { glow:'#f472b6', bg:'radial-gradient(ellipse at 70% 40%,rgba(60,10,40,0.96),rgba(15,2,10,0.98))', flash:'#f472b6' },
                    140: { glow:'#38bdf8', bg:'radial-gradient(ellipse at 50% 20%,rgba(10,35,60,0.96),rgba(2,8,18,0.98))', flash:'#38bdf8' },
                    141: { glow:'#dc2626', bg:'radial-gradient(ellipse at 40% 60%,rgba(80,5,5,0.96),rgba(15,0,0,0.98))', flash:'#dc2626' },
                    142: { glow:'#60a5fa', bg:'radial-gradient(ellipse at 60% 40%,rgba(10,20,50,0.96),rgba(2,5,15,0.98))', flash:'#60a5fa' },
                    143: { glow:'#a3a3a3', bg:'radial-gradient(ellipse at 50% 50%,rgba(25,25,25,0.97),rgba(5,5,5,0.99))', flash:'#a3a3a3' },
                    144: { glow:'#f97316', bg:'radial-gradient(ellipse at 50% 70%,rgba(60,20,0,0.96),rgba(12,4,0,0.98))', flash:'#f97316' }
                };
                const roleDefaults = {
                    'Tank':      { glow:'#fbbf24', flash:'#fbbf24' },
                    'Bruiser':   { glow:'#f97316', flash:'#f97316' },
                    'DPS':       { glow:'#ef4444', flash:'#ef4444' },
                    'Burst DPS': { glow:'#ec4899', flash:'#ec4899' },
                    'Healer':    { glow:'#10b981', flash:'#10b981' },
                    'Support':   { glow:'#8b5cf6', flash:'#8b5cf6' },
                    'Controller':{ glow:'#06b6d4', flash:'#06b6d4' }
                };
                const t = charThemes[charId] || roleDefaults[role] || roleDefaults['DPS'];
                const glowColor = t.glow;
                const bgGrad = t.bg || 'radial-gradient(ellipse at 50% 50%,rgba(0,0,0,0.95),rgba(0,0,0,0.98))';
                const flashColor = t.flash || '#fff';

                // ============ BUILD CUTIN OVERLAY ============
                const overlay = document.createElement('div');
                overlay.id = 'ult-cinematic-overlay';

                // Dark themed background
                const bg = document.createElement('div');
                bg.className = 'ult-bg';
                bg.style.background = bgGrad;
                overlay.appendChild(bg);

                // Animated border glow (top + bottom lines)
                const lineTop = document.createElement('div');
                lineTop.className = 'ult-line-glow top';
                lineTop.style.background = 'linear-gradient(90deg,transparent,' + glowColor + ',transparent)';
                overlay.appendChild(lineTop);

                const lineBot = document.createElement('div');
                lineBot.className = 'ult-line-glow bottom';
                lineBot.style.background = 'linear-gradient(90deg,transparent,' + glowColor + ',transparent)';
                overlay.appendChild(lineBot);

                // Diagonal slash
                const slash = document.createElement('div');
                slash.className = 'ult-slash';
                slash.style.background = 'linear-gradient(90deg,transparent,rgba(255,255,255,0.85) 30%,#fff 50%,rgba(255,255,255,0.85) 70%,transparent)';
                slash.style.boxShadow = '0 0 60px ' + glowColor + ',0 0 120px ' + glowColor;
                overlay.appendChild(slash);

                // Character image (centered-right, big, with glow pulse)
                if (imgUrl) {
                    const imgEl = document.createElement('img');
                    imgEl.src = imgUrl;
                    imgEl.alt = charName;
                    imgEl.className = 'ult-character-img';
                    imgEl.style.filter = 'drop-shadow(0 0 60px ' + glowColor + ') drop-shadow(0 0 120px ' + glowColor + ') blur(15px)';
                    overlay.appendChild(imgEl);
                }

                // Character name - TOP LEFT
                const nameTag = document.createElement('div');
                nameTag.className = 'ult-name-container';
                nameTag.innerHTML = '<div class="ult-name-subtitle" style="color:' + glowColor + ';text-shadow:0 0 15px ' + glowColor + ';">Ultimate Ability</div>'
                    + '<div class="ult-char-name">' + esc(charName) + '</div>';
                overlay.appendChild(nameTag);

                // Ultimate name - BOTTOM LEFT
                const ultTag = document.createElement('div');
                ultTag.className = 'ult-ability-container';
                ultTag.innerHTML = '<div class="ult-ability-name" style="color:' + glowColor + ';text-shadow:0 0 30px ' + glowColor + ',0 0 60px ' + glowColor + '55,0 6px 30px rgba(0,0,0,0.7);">' + esc(ultName) + '</div>';
                overlay.appendChild(ultTag);

                // Particles container
                const particles = document.createElement('div');
                particles.className = 'ult-particles-wrap';
                overlay.appendChild(particles);

                document.body.appendChild(overlay);

                // ============ ANIMATION TIMELINE ============

                // T=0: BG + border lines fade in
                requestAnimationFrame(() => {
                    bg.style.opacity = '1';
                    lineTop.style.opacity = '1';
                    lineBot.style.opacity = '1';
                });

                // T=80ms: Character name slides in from left
                setTimeout(() => {
                    nameTag.style.opacity = '1';
                    nameTag.style.transform = 'translateX(0)';
                }, 80);

                // T=100ms: Slash sweeps across
                setTimeout(() => {
                    slash.style.transition = 'transform 1.2s cubic-bezier(0.16,1,0.3,1), opacity 0.25s ease';
                    slash.style.opacity = '1';
                    slash.style.transform = 'rotate(-15deg) translateY(0px)';
                }, 100);

                // T=150ms: Character image slides in with deblur
                if (imgUrl) {
                    const imgEl = overlay.querySelector('.ult-character-img');
                    setTimeout(() => {
                        imgEl.style.opacity = '1';
                        imgEl.style.transform = 'translateY(-50%) scale(1.02) translateX(0px) rotate(0deg)';
                        imgEl.style.filter = 'drop-shadow(0 0 60px ' + glowColor + ') drop-shadow(0 0 120px ' + glowColor + ') blur(0px)';
                    }, 150);
                    // T=600ms: Pulsing glow on character
                    setTimeout(() => {
                        imgEl.style.transition = 'filter 0.4s ease';
                        imgEl.style.filter = 'drop-shadow(0 0 80px ' + glowColor + ') drop-shadow(0 0 160px ' + glowColor + ') blur(0px)';
                    }, 600);
                    setTimeout(() => {
                        imgEl.style.filter = 'drop-shadow(0 0 50px ' + glowColor + ') drop-shadow(0 0 100px ' + glowColor + ') blur(0px)';
                    }, 950);
                }

                // T=250ms: Ultimate name rises from bottom
                setTimeout(() => {
                    ultTag.style.opacity = '1';
                    ultTag.style.transform = 'translateY(0)';
                }, 250);

                // T=100-500ms: Spawn premium particles (NO EMOJIS, CSS GLOW)
                for (let i = 0; i < 40; i++) {
                    setTimeout(() => {
                        const p = document.createElement('div');
                        const shapeType = Math.floor(Math.random() * 3);
                        const size = 6 + Math.random() * 14;
                        p.style.cssText = 'position:absolute;left:' + (Math.random() * 100) + '%;top:' + (Math.random() * 100) + '%;width:' + size + 'px;height:' + size + 'px;opacity:0;pointer-events:none;user-select:none;background:' + (Math.random() > 0.5 ? '#fff' : glowColor) + ';box-shadow:0 0 ' + size + 'px ' + glowColor + ', 0 0 ' + (size * 2) + 'px ' + glowColor + ';transition:transform 1.2s cubic-bezier(0.1, 0.8, 0.3, 1), opacity 0.8s ease;transform:translate(0,0) scale(0.2) rotate(0deg);';
                        if (shapeType === 1) {
                            p.style.borderRadius = '50%';
                        } else if (shapeType === 2) {
                            p.style.transform += ' rotate(45deg)';
                            p.style.borderRadius = '2px';
                        } else {
                            p.style.width = (size * 3) + 'px';
                            p.style.height = '2px';
                            p.style.borderRadius = '999px';
                        }
                        particles.appendChild(p);
                        const dx = (Math.random() - 0.5) * 800;
                        const dy = (Math.random() - 0.5) * 800;
                        const rotate = Math.random() * 720 - 360;
                        requestAnimationFrame(() => {
                            p.style.opacity = '1';
                            p.style.transform = 'translate(' + dx + 'px,' + dy + 'px) scale(1.3) rotate(' + rotate + 'deg)';
                        });
                        setTimeout(() => { p.style.opacity = '0'; }, 900);
                        setTimeout(() => { p.remove(); }, 2000);
                    }, 80 + Math.random() * 500);
                }

                // T=700ms: Camera shake
                setTimeout(() => {
                    if (arena) {
                        let ct = 0;
                        const si = setInterval(() => {
                            arena.style.transform = 'translate(' + ((Math.random()-0.5)*14) + 'px,' + ((Math.random()-0.5)*14) + 'px)';
                            if (++ct > 16) { clearInterval(si); arena.style.transform = ''; }
                        }, 35);
                    }
                }, 700);

                // T=1200ms: Flash screen
                setTimeout(() => {
                    const fl = document.createElement('div');
                    fl.style.cssText = 'position:fixed;inset:0;z-index:9999999;background:' + flashColor + ';opacity:0.8;pointer-events:none;transition:opacity 0.4s ease;';
                    document.body.appendChild(fl);
                    setTimeout(() => { fl.style.opacity = '0'; }, 60);
                    setTimeout(() => { fl.remove(); }, 500);
                }, 1200);

                // T=1400ms: Everything fades out
                setTimeout(() => {
                    bg.style.opacity = '0';
                    slash.style.opacity = '0';
                    nameTag.style.opacity = '0';
                    nameTag.style.transform = 'translateX(50px)';
                    ultTag.style.opacity = '0';
                    ultTag.style.transform = 'translateY(40px)';
                    lineTop.style.opacity = '0';
                    lineBot.style.opacity = '0';
                    if (imgUrl) {
                        const imgEl = overlay.querySelector('.ult-character-img');
                        if (imgEl) {
                            imgEl.style.opacity = '0';
                            imgEl.style.transform = 'translateY(-50%) scale(1.15) translateX(-120px) rotate(-2deg)';
                            imgEl.style.filter = 'drop-shadow(0 0 50px ' + glowColor + ') blur(12px)';
                        }
                    }
                }, 1400);

                // T=1800ms: Remove overlay, show damage
                setTimeout(() => {
                    overlay.remove();
                    showActionBanner(a, ultName);
                    if (target && Number(a.damage) > 0) {
                        flashCard(target, isCrit ? 'fx-crit-hit' : 'fx-hit', '-' + a.damage, true, isCrit);
                    } else if (target) {
                        flashCard(target, 'fx-charge', 'UPGRADE!', false, false);
                    }
                }, 1800);

                // T=2200ms: Resume polling
                setTimeout(() => {
                    startPolling();
                    resolve();
                }, 2200);

                return;
            }

            // ============ NORMAL ANIMATION (non-ultimate) ============
            const actorCls=type==='special_attack'?'fx-special':type==='defend'?'fx-defend':type==='charge'?'fx-charge':type==='switch'?'fx-switch':'fx-attack';
            const label=type==='charge'?gt.action_energy:type==='defend'?gt.action_defense:type==='special_attack'?gt.action_special:type==='switch'?gt.action_switch:gt.action_attack;
            showActionBanner(a, label);
            if(actor) flashCard(actor,actorCls,label);
            if(target&&Number(a.damage)>0) {
                flashCard(target, isCrit ? 'fx-crit-hit' : 'fx-hit', '-' + a.damage, true, isCrit);
            }
            const fx=$('#gameFx');
            if(fx&&type==='special_attack'){
                fx.classList.remove('is-special');void fx.offsetWidth;fx.classList.add('is-special');
                setTimeout(()=>fx.classList.remove('is-special'),700);
            }
            setTimeout(resolve, 600);
        });
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
        const actorName = mine ? (lang === 'en' ? 'You' : 'Tu') : (actorPlayer?.username || (Number(a.user_id)===0 ? 'Bot' : 'Player'));
        const banner = document.createElement('div');
        banner.className = `game-action-banner ${mine ? 'is-mine' : 'is-enemy'}`;
        banner.innerHTML = `<strong>${esc(actorName)}</strong><span>${esc(label)}${Number(a.damage)>0 ? ` · ${Number(a.damage)} ${lang === 'en' ? 'damage' : 'danni'}` : ''}</span>`;
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
    function showResult(){if(state.resultShown)return; state.resultShown=true; const m=state.match, modal=$('#resultModal'); if(!modal)return; const win=Number(m.winner_id)===Number(myId()); $('#resultKicker').textContent=m.mode==='ranked'?gt.ranked_finished:(m.mode==='bot'?gt.offline_finished:gt.match_finished); $('#resultTitle').textContent=win?gt.viewer_win:gt.viewer_loss; $('#resultText').textContent=m.mode==='bot'?(win?gt.bot_win:gt.bot_loss):(win?gt.pvp_win:gt.pvp_loss); const box=$('#rankedFeedback'); if(m.mode==='ranked'&&m.ranked_result&&box){const rr=m.ranked_result; box.hidden=false; box.innerHTML=`<div class="${rr.viewer_delta>=0?'is-plus':'is-minus'}"><strong>${gt.you}</strong><b>${rr.viewer_delta>=0?'+':''}${rr.viewer_delta}</b>${rankBadge(rr.viewer_rank_after)}</div><div class="${rr.opponent_delta>=0?'is-plus':'is-minus'}"><strong>${gt.opponent}</strong><b>${rr.opponent_delta>=0?'+':''}${rr.opponent_delta}</b>${rankBadge(rr.opponent_rank_after)}</div>`} modal.hidden=false;}
    async function forfeit(){const lang=window.location.pathname.includes('/en/')?'en':'it';if(!state.matchId){window.location.href=`/${lang}/game/lobby.php`;return} if(!confirm(gt.forfeit_confirm))return; try{await api('/api/game/forfeit_match.php',{match_id:state.matchId}); window.location.href=`/${lang}/game/lobby.php`}catch(e){showToast(e.message)}}

    function bindCommon(){ $$('[data-action="find-match"]').forEach(b=>b.addEventListener('click',()=>findMatch(b.dataset.mode||'casual'))); $('[data-action="create-bot"]')?.addEventListener('click',createBotMatch); $('[data-action="create-private"]')?.addEventListener('click',createPrivate); $('[data-action="join-code"]')?.addEventListener('click',joinCode); $('[data-action="active-match"]')?.addEventListener('click',activeMatch); $('[data-action="load-ranking"]')?.addEventListener('click',loadRanking); $('[data-action="load-live"]')?.addEventListener('click',loadLiveMatches); $$('[data-action="forfeit"]').forEach(b=>b.addEventListener('click',forfeit)); }
    document.addEventListener('DOMContentLoaded',()=>{bindCommon(); if(page==='duel-lobby'){loadProfile();loadRanking();loadLiveMatches();setInterval(loadRanking,30000);setInterval(loadLiveMatches,10000)} if(page==='duel-arena'){if(!state.matchId){showToast(gt.match_missing);return} $('#cardSearch')?.addEventListener('input',renderInventory); $('[data-action="submit-team"]')?.addEventListener('click',submitTeam); $$('[data-battle-action]').forEach(b=>b.addEventListener('click',()=>submitBattle(b.dataset.battleAction))); $('#chatForm')?.addEventListener('submit',(e)=>{e.preventDefault();sendChat();}); $$('[data-reaction]').forEach(b=>b.addEventListener('click',()=>sendReaction(b.dataset.reaction))); startPolling();
    document.addEventListener('click', (e) => { if (!e.target.closest('.game-card-wrapper')) { $$('.game-card-details-hover.is-active').forEach(h => h.classList.remove('is-active')); } });}});
})();
