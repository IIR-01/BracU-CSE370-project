<?php
// ---------- PHP BACKEND ----------
$DB_HOST = 'localhost';
$DB_NAME = 'gamehub_database';
$DB_USER = 'root';
$DB_PASS = '';

function get_pdo() {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    return new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

// GET: scoreboard by mode
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['scores'])) {
    header('Content-Type: application/json; charset=utf-8');
    $mode = $_GET['mode'] ?? 'draw5';
    $allowed = ['draw5','texas','highwar'];
    if (!in_array($mode, $allowed, true)) $mode = 'draw5';
    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            "SELECT player_name, mode, score, hands_played, detail, created_at
             FROM poker_universe_scores
             WHERE mode = :m
             ORDER BY score DESC, hands_played DESC, created_at ASC
             LIMIT 20"
        );
        $stmt->execute([':m' => $mode]);
        $rows = $stmt->fetchAll();
        echo json_encode(['ok' => true, 'rows' => $rows]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// POST: save run
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $playerName = trim($data['name'] ?? '');
    $mode       = $data['mode'] ?? 'draw5';
    $score      = $data['score'] ?? null;
    $hands      = $data['hands_played'] ?? 0;
    $detail     = trim($data['detail'] ?? '');

    $allowed = ['draw5','texas','highwar'];
    if (!in_array($mode, $allowed, true)) $mode = 'draw5';

    if ($playerName === '' || !is_numeric($score)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    try {
        $pdo = get_pdo();

        $stmt = $pdo->prepare(
            "INSERT INTO poker_universe_scores
             (player_name, mode, score, hands_played, detail)
             VALUES (:name, :mode, :score, :hands, :detail)"
        );
        $stmt->execute([
            ':name'  => $playerName,
            ':mode'  => $mode,
            ':score' => (int)$score,
            ':hands' => (int)$hands,
            ':detail'=> $detail,
        ]);

        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'DB error','detail'=>$e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Neon Poker Universe</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pixelify+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg1:#020617;--accent:#38bdf8;--accent2:#f97316;
  --danger:#f43f5e;--success:#22c55e;--text-main:#e5e7eb;--text-muted:#9ca3af;
  --glass:#020617f0;
}
*{box-sizing:border-box;font-family:"Pixelify Sans",system-ui,sans-serif;}

body{
  margin:0;min-height:100vh;color:var(--text-main);
  background:
    radial-gradient(circle at 0 0,#0b1120,#020617 55%),
    radial-gradient(circle at 100% 100%,#1d4ed8,#020617 45%);
  display:flex;justify-content:center;align-items:flex-start;padding:18px 10px;
  overflow-x:hidden;position:relative;
}
body::before{
  content:"";position:fixed;inset:0;pointer-events:none;
  background:repeating-linear-gradient(
    to bottom,
    rgba(15,23,42,0.5) 0px,
    rgba(15,23,42,0.5) 1px,
    transparent 2px,
    transparent 3px
  );
  mix-blend-mode:soft-light;opacity:0.5;
}
body::after{
  content:"";position:fixed;inset:0;pointer-events:none;
  box-shadow:0 0 80px rgba(56,189,248,0.25) inset;
}

.shell{max-width:1240px;width:100%;}
.container{
  padding:14px 16px 18px;border-radius:18px;background:var(--glass);
  border:1px solid rgba(31,41,55,0.9);box-shadow:0 18px 40px rgba(0,0,0,0.9);
  position:relative;overflow:hidden;
}
.container::before{
  content:"";position:absolute;inset:-40%;pointer-events:none;
  background:
    radial-gradient(circle at 0 0,rgba(56,189,248,0.25),transparent 60%),
    radial-gradient(circle at 100% 100%,rgba(248,113,113,0.25),transparent 55%);
  opacity:0.9;z-index:-1;
}

.header{
  display:flex;justify-content:space-between;align-items:flex-start;gap:10px;
}
h1{
  margin:0;font-size:1.7rem;letter-spacing:.16em;text-transform:uppercase;
  text-shadow:0 0 10px rgba(56,189,248,0.9),0 0 22px rgba(56,189,248,0.6);
}
.subtitle{
  margin-top:4px;font-size:.82rem;color:var(--text-muted);letter-spacing:.08em;
}
.badge-row{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:.75rem;}
.badge{
  padding:3px 10px;border-radius:999px;background:rgba(22,163,74,0.1);
  border:1px solid rgba(22,163,74,0.7);text-transform:uppercase;
  letter-spacing:.1em;color:#bbf7d0;display:inline-flex;align-items:center;gap:6px;
}
.dot{
  width:8px;height:8px;border-radius:50%;background:#22c55e;
  box-shadow:0 0 8px rgba(34,197,94,0.9);
}
.right-top{
  text-align:right;font-size:.78rem;color:var(--text-muted);
}
.label-row{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:4px;}
.pill{
  padding:4px 8px;border-radius:999px;border:1px solid rgba(55,65,81,0.9);
  background:rgba(15,23,42,0.9);display:flex;align-items:center;gap:6px;
}
.pill span:first-child{text-transform:uppercase;letter-spacing:.1em;font-size:.7rem;}
.pill input,.pill select{
  background:transparent;border:none;color:var(--text-main);
  font-size:.8rem;outline:none;text-align:center;min-width:60px;
}

/* layout */
.main-layout{
  display:grid;grid-template-columns:2fr 1.1fr;gap:12px;margin-top:12px;
}
@media(max-width:980px){.main-layout{grid-template-columns:1fr;}}

/* tabs */
.game-tabs{
  display:flex;gap:8px;margin-bottom:8px;flex-wrap:wrap;
}
.tab-btn{
  border:none;border-radius:999px;padding:6px 14px;font-size:.8rem;font-weight:600;
  cursor:pointer;background:#020617;color:var(--text-main);
  border:1px solid rgba(55,65,81,0.9);
  text-transform:uppercase;letter-spacing:.08em;
}
.tab-btn.active{
  background:radial-gradient(circle at 0 0,#facc15,#f97316);
  color:#020617;box-shadow:0 0 14px rgba(250,204,21,0.9);
}

/* boards */
.board{
  border-radius:16px;padding:10px 10px 12px;background:rgba(15,23,42,0.95);
  border:1px solid rgba(55,65,81,0.9);
  box-shadow:0 0 20px rgba(15,23,42,0.9);
  min-height:320px;margin-bottom:10px;
}

/* cards */
.cards-row{
  display:flex;gap:6px;flex-wrap:wrap;margin-bottom:6px;
}
.card{
  width:56px;height:78px;border-radius:7px;
  background:#020617;border:1px solid rgba(148,163,184,0.9);
  position:relative;display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;box-shadow:0 0 12px rgba(15,23,42,0.8);
}
.card-rank{
  position:absolute;top:3px;left:5px;font-size:.7rem;
}
.card-suit{
  font-size:1rem;
}
.card-back .card-suit{
  font-size:1.2rem;
}
.card-selectable{cursor:pointer;}
.card-selected{
  border-color:#facc15;
  box-shadow:0 0 14px rgba(250,204,21,0.9);
  transform:translateY(-4px);
}

/* buttons */
.btn-row{
  display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;margin-bottom:4px;
}
.btn{
  border:none;border-radius:999px;padding:6px 12px;font-size:.78rem;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:6px;
  background:rgba(15,23,42,0.95);color:var(--text-main);
  border:1px solid rgba(55,65,81,0.9);
  text-transform:uppercase;letter-spacing:.08em;
}
.btn:hover{
  transform:translateY(-1px);box-shadow:0 0 12px rgba(56,189,248,0.7);
  border-color:rgba(56,189,248,0.9);
}
.btn-primary{
  background:radial-gradient(circle at 0 0,#facc15,#f97316);color:#020617;
  box-shadow:0 0 18px rgba(248,181,0,0.8);
}

/* texts */
.status-text{
  font-size:.8rem;color:var(--text-muted);margin-top:4px;
}
.score-text{
  font-size:.82rem;color:#facc15;margin-top:4px;
}

/* scoreboard */
.score-box{
  border-radius:14px;padding:8px 10px;background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);margin-top:8px;
  font-size:.78rem;
}
.score-box table{
  width:100%;border-collapse:collapse;font-size:.76rem;
}
.score-box th,.score-box td{
  padding:3px 4px;border-bottom:1px solid rgba(31,41,55,0.8);
}
.score-box th{
  text-align:left;color:var(--text-muted);text-transform:uppercase;
  letter-spacing:.08em;font-size:.7rem;
}
.score-box tr:nth-child(odd){
  background:rgba(15,23,42,0.9);
}
.small-text{font-size:.75rem;color:var(--text-muted);}
</style>
</head>
<body>
<div class="shell">
  <div class="container">
    <div class="header">
      <div>
        <h1>Neon Poker Universe</h1>
        <div class="subtitle">
          5‑Card Draw · Texas Hold’em · High‑Card War – one poker cabinet, many variants. ♠♥♦♣
        </div>
        <div class="badge-row">
          <div class="badge">
            <div class="dot"></div>
            POKER ARCADE
          </div>
        </div>
      </div>
      <div class="right-top">
        <div class="label-row">
          <div class="pill">
            <span>Name</span>
            <input id="player-name" type="text" value="Player" />
          </div>
          <div class="pill">
            <span>Mode</span>
            <select id="scoreboard-mode-select">
              <option value="draw5">5‑Card Draw</option>
              <option value="texas">Texas Hold’em</option>
              <option value="highwar">High‑Card War</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="main-layout">
      <!-- Left: games -->
      <div>
        <div class="game-tabs">
          <button class="tab-btn active" data-mode="draw5">5‑Card Draw</button>
          <button class="tab-btn" data-mode="texas">Texas Hold’em</button>
          <button class="tab-btn" data-mode="highwar">High‑Card War</button>
        </div>

        <!-- 5-Card Draw -->
        <div class="board" id="board-draw5">
          <div class="cards-row" id="draw5-player-row"></div>
          <div class="cards-row" id="draw5-dealer-row"></div>
          <div class="btn-row">
            <button class="btn btn-primary" id="draw5-deal-btn">Deal 🃏</button>
            <button class="btn" id="draw5-draw-btn">Draw / Stand ⭐</button>
          </div>
          <div class="status-text" id="draw5-status">Deal, click cards to HOLD, then Draw / Stand to score hand vs dealer.</div>
          <div class="score-text" id="draw5-score">Hands played: 0 | Bankroll: 0</div>
        </div>

        <!-- Texas Hold'em (heads-up vs bot) -->
        <div class="board" id="board-texas" style="display:none;">
          <div class="cards-row" id="texas-table-row"></div>
          <div class="cards-row" id="texas-player-row"></div>
          <div class="cards-row" id="texas-bot-row"></div>
          <div class="btn-row">
            <button class="btn btn-primary" id="texas-start-btn">New Hand ▶</button>
            <button class="btn" id="texas-bet-btn">Bet / Call 💰</button>
            <button class="btn" id="texas-fold-btn">Fold 🚫</button>
          </div>
          <div class="status-text" id="texas-status">New Hand will deal hole cards and community cards step by step.</div>
          <div class="score-text" id="texas-score">Hands: 0 | Chips: 0</div>
        </div>

        <!-- High-Card War (quick) -->
        <div class="board" id="board-highwar" style="display:none;">
          <div class="cards-row" id="highwar-player-row"></div>
          <div class="cards-row" id="highwar-bot-row"></div>
          <div class="btn-row">
            <button class="btn btn-primary" id="highwar-play-btn">Flip Card ⚡</button>
          </div>
          <div class="status-text" id="highwar-status">Flip cards; highest rank wins the round, 3 points win bonus.</div>
          <div class="score-text" id="highwar-score">Rounds: 0 | Score: 0</div>
        </div>
      </div>

      <!-- Right: scoreboard -->
      <div>
        <div class="score-box">
          <div class="small-text" style="margin-bottom:4px;">
            Leaderboard (<span id="scoreboard-label">5‑Card Draw</span>)
          </div>
          <div id="scoreboard-wrap">
            <div class="small-text">No runs yet. Finish a session to enter the board. 🏆</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
// ===== Shared helpers =====
const suits = ['♠','♥','♦','♣'];
const ranks = ['2','3','4','5','6','7','8','9','10','J','Q','K','A'];

function rankValue(r){return ranks.indexOf(r);}
function createDeck(){
  const deck=[];
  for(const s of suits){
    for(const r of ranks){
      deck.push({rank:r,suit:s});
    }
  }
  for(let i=deck.length-1;i>0;i--){
    const j=Math.floor(Math.random()*(i+1));
    [deck[i],deck[j]]=[deck[j],deck[i]];
  }
  return deck;
}
function renderCard(el, card, faceUp=true){
  el.className='card';
  if(!faceUp){
    el.classList.add('card-back');
    el.innerHTML='<div class="card-suit">🂠</div>';
    return;
  }
  const rankEl=document.createElement('div');
  rankEl.className='card-rank';
  rankEl.textContent=card.rank;
  const suitEl=document.createElement('div');
  suitEl.className='card-suit';
  suitEl.textContent=card.suit;
  el.appendChild(rankEl);
  el.appendChild(suitEl);
}

// basic 5‑card hand evaluator (scores and label)[web:566][web:574]
function buildRankCounts(hand){
  const cnt={};
  hand.forEach(c=>{cnt[c.rank]=(cnt[c.rank]||0)+1;});
  return cnt;
}
function isFlush(hand){return hand.every(c=>c.suit===hand[0].suit);}
function isStraight(hand){
  const vals=hand.map(c=>rankValue(c.rank)).sort((a,b)=>a-b);
  if(JSON.stringify(vals)==='[0,1,2,3,12]') return true;
  for(let i=1;i<vals.length;i++){
    if(vals[i]!==vals[0]+i) return false;
  }
  return true;
}
function scoreHand5(hand){
  const flush=isFlush(hand);
  const straight=isStraight(hand);
  const cnt=buildRankCounts(hand);
  const counts=Object.values(cnt).sort((a,b)=>b-a);
  if(flush && straight) return {name:'Straight Flush',score:9};
  if(counts[0]===4) return {name:'Four of a Kind',score:8};
  if(counts[0]===3 && counts[1]===2) return {name:'Full House',score:7};
  if(flush) return {name:'Flush',score:6};
  if(straight) return {name:'Straight',score:5};
  if(counts[0]===3) return {name:'Three of a Kind',score:4};
  if(counts[0]===2 && counts[1]===2) return {name:'Two Pair',score:3};
  if(counts[0]===2) return {name:'One Pair',score:2};
  return {name:'High Card',score:1};
}

// scoreboard
const playerNameInput=document.getElementById('player-name');
const scoreboardModeSelect=document.getElementById('scoreboard-mode-select');
const scoreboardLabel=document.getElementById('scoreboard-label');
const scoreboardWrap=document.getElementById('scoreboard-wrap');

async function saveScore(mode, score, hands, detail){
  const payload={
    name: playerNameInput.value.trim() || 'Player',
    mode, score, hands_played:hands, detail
  };
  try{
    const res=await fetch(window.location.href,{
      method:'POST',
      headers:{'Content-Type':'application/json; charset=UTF-8'},
      body:JSON.stringify(payload)
    });
    const json=await res.json();
    console.log('saveScore', json);
    await loadScores(scoreboardModeSelect.value);
  }catch(e){console.error(e);}
}
async function loadScores(mode){
  scoreboardLabel.textContent =
    mode==='draw5' ? '5‑Card Draw' :
    mode==='texas' ? 'Texas Hold’em' : 'High‑Card War';
  scoreboardWrap.innerHTML='<div class="small-text">Loading scores...</div>';
  try{
    const res=await fetch(window.location.href+'?scores=1&mode='+encodeURIComponent(mode));
    const data=await res.json();
    if(!data.ok || !data.rows || data.rows.length===0){
      scoreboardWrap.innerHTML='<div class="small-text">No runs yet in this mode.</div>';
      return;
    }
    let html='<table><thead><tr><th>#</th><th>Name</th><th>Score</th><th>Hands</th></tr></thead><tbody>';
    data.rows.forEach((r,i)=>{
      html+=`<tr>
        <td>${i+1}</td>
        <td>${r.player_name}</td>
        <td>${r.score}</td>
        <td>${r.hands_played}</td>
      </tr>`;
    });
    html+='</tbody></table>';
    scoreboardWrap.innerHTML=html;
  }catch(e){
    console.error(e);
    scoreboardWrap.innerHTML='<div class="small-text">Scoreboard error.</div>';
  }
}
scoreboardModeSelect.addEventListener('change',()=>{
  loadScores(scoreboardModeSelect.value);
});

// tabs
const tabButtons=document.querySelectorAll('.tab-btn');
const boards={
  draw5:document.getElementById('board-draw5'),
  texas:document.getElementById('board-texas'),
  highwar:document.getElementById('board-highwar')
};
let currentMode='draw5';
tabButtons.forEach(btn=>{
  btn.addEventListener('click',()=>{
    const m=btn.getAttribute('data-mode');
    if(m===currentMode) return;
    currentMode=m;
    tabButtons.forEach(b=>b.classList.remove('active'));
    btn.classList.add('active');
    Object.keys(boards).forEach(k=>{
      boards[k].style.display = (k===m)?'block':'none';
    });
    scoreboardModeSelect.value=m;
    loadScores(m);
  });
});

// ===== 5-Card Draw =====
const draw5PlayerRow=document.getElementById('draw5-player-row');
const draw5DealerRow=document.getElementById('draw5-dealer-row');
const draw5Status=document.getElementById('draw5-status');
const draw5ScoreLabel=document.getElementById('draw5-score');
const draw5DealBtn=document.getElementById('draw5-deal-btn');
const draw5DrawBtn=document.getElementById('draw5-draw-btn');

let draw5Deck=[];
let draw5PlayerHand=[];
let draw5DealerHand=[];
let draw5Holds=[false,false,false,false,false];
let draw5Bankroll=0;
let draw5HandsPlayed=0;
let draw5Phase='idle'; // idle, dealed, finished

function renderDrawRow(rowEl, hand, allowHold=false){
  rowEl.innerHTML='';
  hand.forEach((c,idx)=>{
    const div=document.createElement('div');
    div.className='card';
    if(allowHold){
      div.classList.add('card-selectable');
      if(draw5Holds[idx]) div.classList.add('card-selected');
      div.addEventListener('click',()=>{
        if(draw5Phase!=='dealed') return;
        draw5Holds[idx]=!draw5Holds[idx];
        renderDrawRow(draw5PlayerRow, draw5PlayerHand, true);
      });
    }
    const r=document.createElement('div');
    r.className='card-rank'; r.textContent=c.rank;
    const s=document.createElement('div');
    s.className='card-suit'; s.textContent=c.suit;
    div.appendChild(r);div.appendChild(s);
    rowEl.appendChild(div);
  });
}

draw5DealBtn.addEventListener('click',()=>{
  draw5Deck=createDeck();
  draw5PlayerHand=draw5Deck.splice(0,5);
  draw5DealerHand=draw5Deck.splice(0,5);
  draw5Holds=[false,false,false,false,false];
  draw5Phase='dealed';
  renderDrawRow(draw5PlayerRow, draw5PlayerHand, true);
  renderDrawRow(draw5DealerRow, draw5DealerHand, false);
  draw5Status.textContent='Choose which cards to HOLD, then Draw / Stand.';
});
draw5DrawBtn.addEventListener('click',()=>{
  if(draw5Phase!=='dealed'){
    draw5Status.textContent='Deal first.';
    return;
  }
  for(let i=0;i<draw5PlayerHand.length;i++){
    if(!draw5Holds[i] && draw5Deck.length>0){
      draw5PlayerHand[i]=draw5Deck.pop();
    }
  }
  renderDrawRow(draw5PlayerRow, draw5PlayerHand, false);
  const playerEval=scoreHand5(draw5PlayerHand);
  const dealerEval=scoreHand5(draw5DealerHand);
  let msg=`You: ${playerEval.name} vs Dealer: ${dealerEval.name}. `;
  if(playerEval.score>dealerEval.score){
    draw5Bankroll+=10;
    msg+='You win +10 chips.';
  }else if(playerEval.score<dealerEval.score){
    draw5Bankroll-=10;
    msg+='Dealer wins −10 chips.';
  }else{
    msg+='Tie hand.';
  }
  draw5HandsPlayed++;
  draw5Status.textContent=msg;
  draw5ScoreLabel.textContent=`Hands played: ${draw5HandsPlayed} | Bankroll: ${draw5Bankroll}`;
  draw5Phase='finished';
  if(draw5HandsPlayed%10===0){
    saveScore('draw5', draw5Bankroll, draw5HandsPlayed, `5‑Card Draw session, bankroll ${draw5Bankroll}`);
  }
});

// ===== Texas Hold'em (very simplified heads-up, auto-betting) =====
const texasTableRow=document.getElementById('texas-table-row');
const texasPlayerRow=document.getElementById('texas-player-row');
const texasBotRow=document.getElementById('texas-bot-row');
const texasStatus=document.getElementById('texas-status');
const texasScoreLabel=document.getElementById('texas-score');
const texasStartBtn=document.getElementById('texas-start-btn');
const texasBetBtn=document.getElementById('texas-bet-btn');
const texasFoldBtn=document.getElementById('texas-fold-btn');

let texasDeck=[];
let texasPlayerCards=[];
let texasBotCards=[];
let texasBoardCards=[];
let texasPhase='idle'; // preflop, flop, turn, river, showdown
let texasPot=0;
let texasChips=0;
let texasHands=0;

function renderTexasCards(){
  texasPlayerRow.innerHTML='';
  texasBotRow.innerHTML='';
  texasTableRow.innerHTML='';
  texasPlayerCards.forEach(c=>{
    const div=document.createElement('div');
    renderCard(div,c,true);
    texasPlayerRow.appendChild(div);
  });
  texasBotCards.forEach(()=>{
    const div=document.createElement('div');
    renderCard(div,{rank:'',suit:''},false);
    texasBotRow.appendChild(div);
  });
  texasBoardCards.forEach(c=>{
    const div=document.createElement('div');
    renderCard(div,c,true);
    texasTableRow.appendChild(div);
  });
}
function best5Score7(cards){
  let best={score:0};
  const idx=[0,1,2,3,4,5,6];
  for(let a=0;a<7;a++){
    for(let b=a+1;b<7;b++){
      const h=[];
      for(let k=0;k<7;k++){
        if(k===a||k===b) continue;
        h.push(cards[k]);
      }
      const r=scoreHand5(h);
      if(r.score>best.score) best=r;
    }
  }
  return best;
}

texasStartBtn.addEventListener('click',()=>{
  texasDeck=createDeck();
  texasPlayerCards=texasDeck.splice(0,2);
  texasBotCards=texasDeck.splice(0,2);
  texasBoardCards=[];
  texasPhase='preflop';
  texasPot=0;
  texasHands++;
  renderTexasCards();
  texasStatus.textContent='Pre‑flop: click Bet / Call to see the flop, or Fold.';
});
texasBetBtn.addEventListener('click',()=>{
  if(texasPhase==='idle') return;
  if(texasPhase==='preflop'){
    texasPot+=10;
    texasBoardCards=texasDeck.splice(0,3);
    texasPhase='flop';
    renderTexasCards();
    texasStatus.textContent='Flop shown. Bet / Call to see Turn.';
  }else if(texasPhase==='flop'){
    texasPot+=10;
    texasBoardCards.push(texasDeck.pop());
    texasPhase='turn';
    renderTexasCards();
    texasStatus.textContent='Turn shown. Bet / Call to see River.';
  }else if(texasPhase==='turn'){
    texasPot+=10;
    texasBoardCards.push(texasDeck.pop());
    texasPhase='river';
    renderTexasCards();
    texasStatus.textContent='River shown. Bet / Call for showdown.';
  }else if(texasPhase==='river'){
    texasPot+=10;
    texasPhase='showdown';
    // showdown: reveal bot, evaluate 7‑card hands[web:570][web:572]
    texasBotRow.innerHTML='';
    texasBotCards.forEach(c=>{
      const div=document.createElement('div');
      renderCard(div,c,true);
      texasBotRow.appendChild(div);
    });
    const player7=[...texasPlayerCards,...texasBoardCards];
    const bot7=[...texasBotCards,...texasBoardCards];
    const pBest=best5Score7(player7);
    const bBest=best5Score7(bot7);
    let msg=`Showdown: You ${pBest.name} vs Bot ${bBest.name}. `;
    if(pBest.score>bBest.score){
      texasChips+=texasPot;
      msg+=`You win pot ${texasPot}.`;
    }else if(pBest.score<bBest.score){
      texasChips-=texasPot;
      msg+=`Bot wins pot ${texasPot}.`;
    }else{
      msg+='Pot split (ignored in chips for simplicity).';
    }
    texasStatus.textContent=msg;
    texasScoreLabel.textContent=`Hands: ${texasHands} | Chips: ${texasChips}`;
    if(texasHands%5===0){
      saveScore('texas', texasChips, texasHands, `Texas Hold’em mini session, chips ${texasChips}`);
    }
  }
});
texasFoldBtn.addEventListener('click',()=>{
  if(texasPhase==='idle') return;
  texasChips-=5;
  texasStatus.textContent='You folded. −5 chips.';
  texasScoreLabel.textContent=`Hands: ${texasHands} | Chips: ${texasChips}`;
  texasPhase='idle';
});

// ===== High-Card War =====
const highwarPlayerRow=document.getElementById('highwar-player-row');
const highwarBotRow=document.getElementById('highwar-bot-row');
const highwarStatus=document.getElementById('highwar-status');
const highwarScoreLabel=document.getElementById('highwar-score');
const highwarPlayBtn=document.getElementById('highwar-play-btn');

let highwarDeck=[];
let highwarRounds=0;
let highwarScore=0;

highwarPlayBtn.addEventListener('click',()=>{
  if(highwarDeck.length<2){
    highwarDeck=createDeck();
  }
  const p=highwarDeck.pop();
  const b=highwarDeck.pop();
  highwarPlayerRow.innerHTML='';
  highwarBotRow.innerHTML='';
  const pDiv=document.createElement('div');
  renderCard(pDiv,p,true);
  highwarPlayerRow.appendChild(pDiv);
  const bDiv=document.createElement('div');
  renderCard(bDiv,b,true);
  highwarBotRow.appendChild(bDiv);
  highwarRounds++;
  const pv=rankValue(p.rank);
  const bv=rankValue(b.rank);
  let msg=`Round ${highwarRounds}: You ${p.rank}${p.suit} vs Bot ${b.rank}${b.suit}. `;
  if(pv>bv){
    highwarScore+=1;
    msg+='You win (+1).';
  }else if(pv<bv){
    highwarScore-=1;
    msg+='Bot wins (−1).';
  }else{
    msg+='Tie.';
  }
  if(Math.abs(highwarScore)>=3){
    const bonus=5;
    msg+=` Streak reached: bonus ${bonus}.`;
    highwarScore+=highwarScore>0?bonus:-bonus;
    saveScore('highwar', highwarScore, highwarRounds, `High‑Card War streak score ${highwarScore}`);
    highwarRounds=0;
    highwarScore=0;
  }
  highwarStatus.textContent=msg;
  highwarScoreLabel.textContent=`Rounds: ${highwarRounds} | Score: ${highwarScore}`;
});

// init
window.addEventListener('load',()=>{
  loadScores('draw5');
});
</script>
</body>
</html>
