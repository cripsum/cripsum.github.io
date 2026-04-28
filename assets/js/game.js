(() => {
    'use strict';
    if (window.__cripsumDuelV15) return;
    window.__cripsumDuelV15 = true;

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
    function rankBadge(rank){if(!rank)return'';return `<span class="game-rank-badge" data-rank="${esc(rank.key)}">${esc(rank.label)}</span>`}
    function goArena(matchId){window.location.href=`/it/game/arena.php?match_id=${encodeURIComponent(matchId)}`;}

    async function loadProfile(){const box=$('#profileSummary'); if(!box)return; try{const d=await api('/api/game/profile_summary.php',{},'GET'); const u=d.profile.user, inv=d.profile.inventory; box.innerHTML=`<div class="game-profile-top"><img src="${esc(u.pfp_url)}" alt="${esc(u.username)}" onerror="this.src='/img/Susremaster.png'"><div><strong>${esc(u.username)}</strong>${rankBadge(u.rank)}</div></div><div class="game-profile-stats"><div><b>${u.rating}</b><small>Punti ranked</small></div><div><b>${inv.unique}</b><small>Personaggi</small></div><div><b>${u.wins}/${u.losses}</b><small>W/L</small></div></div>`}catch(e){box.innerHTML='<p class="game-hint">Profilo non caricato.</p>'}}
    async function loadRanking(){const wrap=$('#rankingList'); if(!wrap)return; try{const d=await api('/api/game/get_ranking.php',{},'GET'); const rows=d.ranking||[]; if(!rows.length){wrap.innerHTML='<p class="game-hint">Classifica vuota.</p>';return} wrap.innerHTML=rows.map((r,i)=>`<div class="game-rank-row"><strong>#${i+1}</strong><span class="game-rank-name">${esc(r.username)}</span><span class="game-rank-meta">${rankBadge(r.rank)} <b>${r.rating}</b></span></div>`).join('')}catch(e){wrap.innerHTML='<p class="game-hint">Classifica non caricata.</p>';}}
    
    async function loadLiveMatches(){
        const wrap = $('#liveMatchesList');
        if (!wrap) return;

        try {
            const d = await api('/api/game/live_matches.php', {}, 'GET');
            const rows = (d.matches || []).filter(m => m.status === 'active' && m.id);

            if (!rows.length) {
                wrap.innerHTML = '<p class="game-hint">Nessuna partita live da guardare.</p>';
                return;
            }

            wrap.innerHTML = rows.map(m => `
                <a class="game-live-row" href="/it/game/arena.php?match_id=${encodeURIComponent(m.id)}">
                    <div>
                        <strong>${esc(m.player1)} vs ${esc(m.player2)}</strong>
                        <span>${esc(m.mode)} · turno ${m.turn_number}</span>
                    </div>
                    <em><i class="fas fa-eye"></i> ${m.spectator_count}</em>
                </a>
            `).join('');
        } catch(e) {
            wrap.innerHTML = '<p class="game-hint">Partite live non caricate.</p>';
        }
    }

    async function findMatch(mode){setMatchmakingLoading(true, mode==='ranked'?'Cerco ranked...':'Cerco casual...');try{const d=await api('/api/game/find_match.php',{mode}); goArena(d.match_id)}catch(e){setMatchmakingLoading(false);showToast(e.message)}}
    async function createPrivate(){const password=($('#privatePasswordInput')?.value||'').trim(); if(password.length<3){showToast('Inserisci una password da almeno 3 caratteri');return} setMatchmakingLoading(true,'Creo stanza privata...'); try{const d=await api('/api/game/create_match.php',{mode:'private',password}); goArena(d.match_id)}catch(e){setMatchmakingLoading(false);showToast(e.message)}}
    async function joinCode(){const code=($('#roomCodeInput')?.value||'').trim(); const password=($('#joinPasswordInput')?.value||'').trim(); if(!code){showToast('Inserisci codice stanza');return} try{const d=await api('/api/game/join_match.php',{room_code:code,password}); goArena(d.match_id)}catch(e){showToast(e.message)}}
    async function activeMatch(){try{const d=await api('/api/game/active_match.php',{},'GET'); if(!d.match){showToast('Nessuna partita attiva');return} goArena(d.match.id)}catch(e){showToast(e.message)}}

    async function loadInventory(){const d=await api('/api/game/get_inventory_cards.php',{},'GET'); state.inventory=d.cards||[]; renderInventory();}
    function renderInventory(){const grid=$('#inventoryGrid'); if(!grid)return; const q=($('#cardSearch')?.value||'').toLowerCase(); const list=state.inventory.filter(c=>`${c.nome} ${c.rarita} ${c.categoria}`.toLowerCase().includes(q)); if(!list.length){grid.innerHTML='<p class="game-hint">Nessun personaggio trovato.</p>';return} grid.innerHTML=''; list.forEach(card=>{const selected=state.selectedTeam.includes(card.id); const el=document.createElement('button'); el.type='button'; el.className=`game-card-option ${selected?'is-selected':''}`; el.innerHTML=`${cardImg(card.img_url,card.nome)}<strong>${esc(card.nome)}</strong><div class="game-card-stats"><span>HP ${card.stats.hp}</span><span>ATK ${card.stats.attack}</span><span>DEF ${card.stats.defense}</span><span>EN ${card.stats.max_energy}</span></div>`; el.addEventListener('click',()=>toggleTeam(card.id)); grid.appendChild(el);}); renderSelectedTeam();}
    function toggleTeam(id){const i=state.selectedTeam.indexOf(id); if(i>=0)state.selectedTeam.splice(i,1); else{if(state.selectedTeam.length>=3){showToast('Puoi scegliere solo 3 personaggi');return} state.selectedTeam.push(id)} renderInventory();}
    function renderSelectedTeam(){const wrap=$('#selectedTeam'), c=$('#teamCounter'); if(c)c.textContent=`${state.selectedTeam.length}/3`; if(!wrap)return; wrap.innerHTML=state.selectedTeam.map(id=>`<span class="game-selected-pill">${esc(state.inventory.find(x=>x.id===id)?.nome||'Carta')}</span>`).join('')}
    async function submitTeam(){if(state.selectedTeam.length!==3){showToast('Scegli 3 personaggi');return} try{await api('/api/game/select_team.php',{match_id:state.matchId,team:state.selectedTeam}); showToast('Team confermato'); pollState()}catch(e){showToast(e.message)}}
    function showOnly(id){['#waitingPanel','#teamPanel','#arenaPanel'].forEach(s=>{const el=$(s); if(el)el.hidden=(s!==id)})}
    function startPolling(){stopPolling(); pollState(true); state.poll=setInterval(()=>{if(!document.hidden)pollState(false)},1500)}
    function stopPolling(){if(state.poll)clearInterval(state.poll); state.poll=null;}
    async function pollState(first=false){if(!state.matchId)return; try{const d=await api('/api/game/get_match_state.php',{match_id:state.matchId},'GET'); const oldLast=state.lastActionId; state.match=d.match; renderMatch(first); const actions=state.match.actions||[]; const newest=actions.length?Number(actions[actions.length-1].id):0; if(!first && newest>oldLast){actions.filter(a=>Number(a.id)>oldLast).forEach((a,i)=>setTimeout(()=>animateAction(a),i*220));} state.lastActionId=Math.max(oldLast,newest);}catch(e){showToast(e.message)}}
    function myId(){return state.match?.viewer_id}
    function isSpectator(){return state.match?.viewer_role === 'spectator'}
    function leftId(){const m=state.match; return !m?null:(isSpectator()?m.player1_id:enemyId())}
    function rightId(){const m=state.match; return !m?null:(isSpectator()?m.player2_id:myId())}
    function playerById(uid){const m=state.match;if(!m)return null; if(Number(uid)===Number(m.player1_id))return m.players?.player1; if(Number(uid)===Number(m.player2_id))return m.players?.player2; return null}
    function enemyId(){const m=state.match; return !m?null:(m.player1_id===myId()?m.player2_id:m.player1_id)}
    function cardsOf(uid){return (state.match?.cards||[]).filter(c=>Number(c.user_id)===Number(uid))}
    function activeOf(uid){return cardsOf(uid).find(c=>Number(c.is_active)&&!Number(c.is_ko))||cardsOf(uid).find(c=>!Number(c.is_ko))}
    function pct(c,m){return Math.max(0,Math.min(100,Math.round((Number(c)/Number(m))*100)||0))}
    function renderMatch(first=false){const m=state.match;if(!m)return; $('#arenaRoomCode') && ($('#arenaRoomCode').textContent=m.room_code); $('#roomCodeLabel') && ($('#roomCodeLabel').textContent=m.room_code); if(m.status==='waiting'){showOnly('#waitingPanel');return} if(m.status==='team_select'){showOnly('#teamPanel'); if(!state.inventory.length)loadInventory(); return} showOnly('#arenaPanel'); const oppPlayer=m.player1_id===myId()?m.players.player2:m.players.player1; if($('#opponentName'))$('#opponentName').textContent=oppPlayer?.username||'Avversario'; const myTurn=Number(m.current_turn_user_id)===Number(myId()); $('#matchStatus').textContent=m.status==='finished'?'Conclusa':`${m.mode==='ranked'?'Ranked':'Partita'} · Turno ${m.turn_number}`; $('#turnLabel').textContent=m.status==='finished'?(Number(m.winner_id)===Number(myId())?'Hai vinto':'Hai perso'):(myTurn?'È il tuo turno':'Turno avversario'); renderActive('#playerActive',activeOf(myId())); renderActive('#opponentActive',activeOf(enemyId())); renderTeam('#playerTeam',cardsOf(myId()),true); renderTeam('#opponentTeam',cardsOf(enemyId()),false); renderLog(m.actions||[]); renderChat(m.chat||[]); renderReactions(m.reactions||[]); renderSpectators(); const specBox = $('#spectatorMode');
        if (specBox) specBox.hidden = !spectator;
        const reactionPanel = $('#reactionPanel');
        if (reactionPanel) reactionPanel.hidden = !spectator;
        const chatForm = $('#chatForm');
        if (chatForm) chatForm.hidden = spectator;
        $$('[data-battle-action]').forEach(b=>b.disabled=spectator||!myTurn||m.status!=='active');
        if(m.status==='finished' && !spectator)showResult();}
    function renderActive(sel,card){const el=$(sel); if(!el)return; if(!card){el.innerHTML='<p class="game-hint">Nessuna carta.</p>';return} const ch=card.character||{}; el.dataset.cardId=card.id; el.innerHTML=`${cardImg(ch.img_url,ch.nome)}<div class="game-active-name"><strong>${esc(ch.nome||'Carta')}</strong><span>${esc(ch.rarita||'comune')}</span></div><div class="game-hpbar"><span style="--value:${pct(card.current_hp,card.max_hp)}%"></span></div><small>HP ${card.current_hp}/${card.max_hp}</small><div class="game-energybar"><span style="--value:${pct(card.energy,card.max_energy)}%"></span></div><small>Energia ${card.energy}/${card.max_energy} · CD ${card.special_cooldown}</small><div class="game-card-stats"><span>ATK ${card.attack}</span><span>DEF ${card.defense}</span><span>SPD ${card.speed}</span><span>${Number(card.is_defending)?'Difesa':'Pronto'}</span></div>`}
    function renderTeam(sel,cards,mine){const el=$(sel); if(!el)return; el.innerHTML=cards.map(c=>{const ch=c.character||{};return `<button class="game-mini-card ${Number(c.is_active)?'is-active':''} ${Number(c.is_ko)?'is-ko':''}" data-card-id="${c.id}" type="button" ${mine&&!Number(c.is_ko)?'':'disabled'}>${cardImg(ch.img_url,ch.nome)}<strong>${esc(ch.nome||'Carta')}</strong><small>${c.current_hp}/${c.max_hp} HP</small></button>`}).join(''); if(mine && state.match?.viewer_role !== 'spectator')$$('.game-mini-card',el).forEach(btn=>btn.addEventListener('click',()=>submitBattle('switch',Number(btn.dataset.cardId))))}
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

    function renderReactions(reactions){
        const arena = $('#arenaPanel');
        if (!arena || !reactions.length) return;

        const newest = Number(reactions[reactions.length - 1].id || 0);

        reactions
            .filter(r => Number(r.id) > Number(state.lastReactionId || 0))
            .forEach((r, i) => {
                setTimeout(() => showReactionFloat(r.reaction, r.username), i * 140);
            });

        if (newest > Number(state.lastReactionId || 0)) {
            state.lastReactionId = newest;
        }
    }

    function showReactionFloat(reaction, username){
        const arena = $('#arenaPanel');
        if (!arena) return;

        const bubble = document.createElement('div');
        bubble.className = 'game-reaction-float';
        bubble.innerHTML = `<span>${esc(reaction)}</span><small>${esc(username || 'Spettatore')}</small>`;
        bubble.style.left = `${20 + Math.random() * 60}%`;
        arena.appendChild(bubble);

        setTimeout(() => bubble.remove(), 1700);
    }

    async function sendReaction(reaction){
        if (!state.matchId) return;

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
    function animateAction(a){const actor=Number(a.actor_card_id), target=Number(a.target_card_id); const type=a.action_type; const actorCls=type==='special_attack'?'fx-special':type==='defend'?'fx-defend':type==='charge'?'fx-charge':type==='switch'?'fx-switch':'fx-attack'; const label=type==='charge'?'+ energia':type==='defend'?'difesa':type==='special_attack'?'speciale':type==='switch'?'cambio':'attacco'; showActionBanner(a, label); if(actor)flashCard(actor,actorCls,label); if(target&&Number(a.damage)>0)flashCard(target,'fx-hit',`-${a.damage}`,true); const fx=$('#gameFx'); if(fx&&type==='special_attack'){fx.classList.remove('is-special');void fx.offsetWidth;fx.classList.add('is-special');setTimeout(()=>fx.classList.remove('is-special'),700)}}
    
    function showActionBanner(a,label){
        const arena = $('#arenaPanel');
        if (!arena) return;
        const old = arena.querySelector('.game-action-banner');
        if (old) old.remove();

        const mine = Number(a.user_id) === Number(myId());
        const banner = document.createElement('div');
        banner.className = `game-action-banner ${mine ? 'is-mine' : 'is-enemy'}`;
        banner.innerHTML = `<strong>${mine ? 'Tu' : 'Avversario'}</strong><span>${esc(label)}${Number(a.damage)>0 ? ` · ${Number(a.damage)} danni` : ''}</span>`;
        arena.appendChild(banner);

        setTimeout(() => banner.remove(), 1300);
    }

    function flashCard(id,cls,label,damage=false){$$(`[data-card-id="${id}"]`).forEach(el=>{el.classList.remove(cls);void el.offsetWidth;el.classList.add(cls); const tag=document.createElement('span'); tag.className=`game-floating-feedback ${damage?'is-damage':'is-buff'}`; tag.textContent=label; el.appendChild(tag); setTimeout(()=>{el.classList.remove(cls);tag.remove()},1050)})}
    function showResult(){if(state.resultShown)return; state.resultShown=true; const m=state.match, modal=$('#resultModal'); if(!modal)return; const win=Number(m.winner_id)===Number(myId()); $('#resultKicker').textContent=m.mode==='ranked'?'Ranked conclusa':'Partita conclusa'; $('#resultTitle').textContent=win?'Hai vinto':'Hai perso'; $('#resultText').textContent=win?'Team avversario KO.':'Il tuo team è andato KO.'; const box=$('#rankedFeedback'); if(m.mode==='ranked'&&m.ranked_result&&box){const rr=m.ranked_result; box.hidden=false; box.innerHTML=`<div class="${rr.viewer_delta>=0?'is-plus':'is-minus'}"><strong>Tu</strong><b>${rr.viewer_delta>=0?'+':''}${rr.viewer_delta}</b>${rankBadge(rr.viewer_rank_after)}</div><div class="${rr.opponent_delta>=0?'is-plus':'is-minus'}"><strong>Avversario</strong><b>${rr.opponent_delta>=0?'+':''}${rr.opponent_delta}</b>${rankBadge(rr.opponent_rank_after)}</div>`} modal.hidden=false;}
    async function forfeit(){if(!state.matchId){window.location.href='/it/game/lobby.php';return} if(!confirm('Vuoi abbandonare?'))return; try{await api('/api/game/forfeit_match.php',{match_id:state.matchId}); window.location.href='/it/game/lobby.php'}catch(e){showToast(e.message)}}

    function bindCommon(){ $$('[data-action="find-match"]').forEach(b=>b.addEventListener('click',()=>findMatch(b.dataset.mode||'casual'))); $('[data-action="create-private"]')?.addEventListener('click',createPrivate); $('[data-action="join-code"]')?.addEventListener('click',joinCode); $('[data-action="active-match"]')?.addEventListener('click',activeMatch); $('[data-action="load-ranking"]')?.addEventListener('click',loadRanking); $('[data-action="load-live"]')?.addEventListener('click',loadLiveMatches); $$('[data-action="forfeit"]').forEach(b=>b.addEventListener('click',forfeit)); }
    document.addEventListener('DOMContentLoaded',()=>{bindCommon(); if(page==='duel-lobby'){loadProfile();loadRanking();loadLiveMatches();setInterval(loadRanking,30000);setInterval(loadLiveMatches,10000)} if(page==='duel-arena'){if(!state.matchId){showToast('Match mancante');return} $('#cardSearch')?.addEventListener('input',renderInventory); $('[data-action="submit-team"]')?.addEventListener('click',submitTeam); $$('[data-battle-action]').forEach(b=>b.addEventListener('click',()=>submitBattle(b.dataset.battleAction))); $('#chatForm')?.addEventListener('submit',(e)=>{e.preventDefault();sendChat();}); $$('[data-reaction]').forEach(b=>b.addEventListener('click',()=>sendReaction(b.dataset.reaction))); startPolling();}});
})();
