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

// GET: scoreboard
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['scores'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo = get_pdo();
        $stmt = $pdo->query(
            "SELECT player_name, score, rounds, detail, created_at
             FROM uno_scores
             ORDER BY score DESC, rounds ASC, created_at ASC
             LIMIT 50"
        );
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
    $score      = $data['score'] ?? null;
    $rounds     = $data['rounds'] ?? 0;
    $detail     = trim($data['detail'] ?? '');

    if ($playerName === '' || !is_numeric($score)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    try {
        $pdo = get_pdo();

        $stmt = $pdo->prepare(
            "INSERT INTO uno_scores
             (player_name, score, rounds, detail)
             VALUES (:name, :score, :rounds, :detail)"
        );
        $stmt->execute([
            ':name'   => $playerName,
            ':score'  => (int)$score,
            ':rounds' => (int)$rounds,
            ':detail' => $detail,
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
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Neon UNO Arcade v2</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pixelify+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg1:#020617;--accent:#38bdf8;--accent2:#f97316;
  --danger:#f43f5e;--success:#22c55e;--text-main:#e5e7eb;--text-muted:#9ca3af;
  --glass:#020617f0;--warning:#fbbf24;
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

.shell{max-width:1200px;width:100%;}
.container{
  padding:16px 18px 20px;border-radius:18px;background:var(--glass);
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
  display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;
}
h1{
  margin:0;font-size:1.8rem;letter-spacing:.16em;text-transform:uppercase;
  text-shadow:0 0 12px rgba(56,189,248,0.9),0 0 24px rgba(56,189,248,0.6);
}
.subtitle{
  margin-top:5px;font-size:.85rem;color:var(--text-muted);letter-spacing:.06em;
}
.badge-row{display:flex;align-items:center;gap:8px;margin-top:10px;font-size:.75rem;flex-wrap:wrap;}
.badge{
  padding:4px 11px;border-radius:999px;background:rgba(22,163,74,0.12);
  border:1px solid rgba(22,163,74,0.7);text-transform:uppercase;
  letter-spacing:.1em;color:#bbf7d0;display:inline-flex;align-items:center;gap:6px;
  font-size:.72rem;
}
.dot{
  width:8px;height:8px;border-radius:50%;background:#22c55e;
  box-shadow:0 0 8px rgba(34,197,94,0.9);
}

.right-top{
  text-align:right;font-size:.78rem;color:var(--text-muted);
}
.label-row{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;margin-top:6px;}
.pill{
  padding:5px 10px;border-radius:999px;border:1px solid rgba(55,65,81,0.9);
  background:rgba(15,23,42,0.9);display:flex;align-items:center;gap:7px;
}
.pill span:first-child{text-transform:uppercase;letter-spacing:.1em;font-size:.72rem;}
.pill input, .pill select{
  background:transparent;border:none;color:var(--text-main);
  font-size:.82rem;outline:none;text-align:center;min-width:60px;
  font-family:inherit;cursor:pointer;
}

.main-layout{
  display:grid;grid-template-columns:1.75fr 1.25fr;gap:14px;margin-top:14px;
}
@media(max-width:960px){.main-layout{grid-template-columns:1fr;}}

.board{
  border-radius:16px;padding:12px 12px 14px;background:rgba(15,23,42,0.95);
  border:1px solid rgba(55,65,81,0.9);
  box-shadow:0 0 20px rgba(15,23,42,0.9);
  min-height:360px;
}
.zone-title{
  font-size:.8rem;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;
  letter-spacing:.08em;font-weight:600;
}
.row{
  display:flex;gap:7px;flex-wrap:wrap;margin-bottom:7px;align-items:center;
}

.card-uno{
  width:62px;height:90px;border-radius:10px;
  border:2px solid #ffffff;
  position:relative;display:flex;align-items:center;justify-content:center;
  font-size:1.3rem;color:#fff;font-weight:700;
  box-shadow:0 0 14px rgba(15,23,42,0.9);
  transition:transform 0.15s, box-shadow 0.15s;
}
.card-uno[data-color='red']{background:#dc2626;}
.card-uno[data-color='green']{background:#16a34a;}
.card-uno[data-color='blue']{background:#2563eb;}
.card-uno[data-color='yellow']{background:#eab308;}
.card-uno[data-color='wild']{
  background:conic-gradient(from 45deg,#dc2626,#16a34a,#2563eb,#eab308,#dc2626);
}
.card-uno .label{
  font-size:1rem;font-weight:700;text-shadow:0 2px 4px rgba(0,0,0,0.5);
}
.card-uno .small{
  position:absolute;top:4px;left:5px;font-size:.7rem;font-weight:600;
}
.card-uno.clickable{cursor:pointer;}
.card-uno.clickable:hover{
  transform:translateY(-8px) scale(1.05);
  box-shadow:0 8px 24px rgba(56,189,248,0.7);
  border-color:#38bdf8;
}
.card-uno.disabled{opacity:0.4;pointer-events:none;}

.pile{
  display:flex;align-items:center;gap:12px;
}
.pile-stack{
  display:flex;position:relative;width:72px;height:98px;
}
.pile-stack .card-uno{
  position:absolute;top:0;left:0;
}
.pile-stack .shadow{
  width:62px;height:90px;border-radius:10px;border:2px dashed rgba(148,163,184,0.7);
  position:absolute;top:5px;left:5px;
}
.pile-label{
  font-size:.76rem;color:var(--text-muted);font-weight:600;
}

.btn-row{
  display:flex;gap:9px;flex-wrap:wrap;margin-top:6px;margin-bottom:6px;
}
.btn{
  border:none;border-radius:999px;padding:7px 14px;font-size:.8rem;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:7px;
  background:rgba(15,23,42,0.95);color:var(--text-main);
  border:1px solid rgba(55,65,81,0.9);
  text-transform:uppercase;letter-spacing:.08em;
  transition:all 0.2s;
}
.btn:hover:not(:disabled){
  transform:translateY(-2px);box-shadow:0 0 16px rgba(56,189,248,0.7);
  border-color:rgba(56,189,248,0.9);
}
.btn:disabled{
  opacity:0.5;cursor:not-allowed;
}
.btn-primary{
  background:linear-gradient(135deg,#facc15,#f97316);color:#020617;
  box-shadow:0 0 20px rgba(248,181,0,0.8);
  font-weight:700;
}

.status-text{
  font-size:.82rem;color:var(--text-muted);margin-top:6px;
  padding:8px 10px;background:rgba(15,23,42,0.7);border-radius:8px;
  border-left:3px solid var(--accent);
}
.score-text{
  font-size:.84rem;color:#facc15;margin-top:6px;font-weight:600;
  padding:8px 10px;background:rgba(234,179,8,0.1);border-radius:8px;
  border-left:3px solid #facc15;
}

.score-box{
  border-radius:14px;padding:10px 12px;background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);margin-top:10px;
  font-size:.78rem;
}
.score-box table{
  width:100%;border-collapse:collapse;font-size:.76rem;margin-top:8px;
}
.score-box th,.score-box td{
  padding:5px 6px;border-bottom:1px solid rgba(31,41,55,0.8);text-align:left;
}
.score-box th{
  color:var(--text-muted);text-transform:uppercase;
  letter-spacing:.08em;font-size:.7rem;font-weight:600;
}
.score-box tr:hover{
  background:rgba(56,189,248,0.1);
}
.small-text{font-size:.76rem;color:var(--text-muted);}
.badge-turn{
  padding:4px 10px;border-radius:999px;border:1px solid rgba(59,130,246,0.8);
  font-size:.72rem;color:#bae6fd;background:rgba(37,99,235,0.15);
  font-weight:600;letter-spacing:.06em;
}

.bot-panel{
  display:flex;flex-direction:column;align-items:center;padding:6px 8px;
  border-radius:10px;background:rgba(15,23,42,0.7);
  border:1px solid rgba(55,65,81,0.7);min-width:90px;
}
.bot-name{
  font-size:.78rem;color:var(--text-muted);font-weight:600;margin-bottom:3px;
}
.bot-cards-count{
  font-size:.8rem;color:var(--text-main);margin-bottom:3px;
}
.bot-cards-preview{
  display:flex;gap:2px;flex-wrap:wrap;justify-content:center;
}
.mini-card{
  width:16px;height:24px;border-radius:3px;border:1px solid #fff;
  background:#1e40af;
}

.modal{
  display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);
  z-index:1000;align-items:center;justify-content:center;
}
.modal.show{display:flex;}
.modal-content{
  background:var(--glass);border-radius:16px;padding:24px;
  max-width:400px;width:90%;border:1px solid rgba(55,65,81,0.9);
}
.modal-title{
  font-size:1.3rem;margin-bottom:12px;color:var(--accent);font-weight:700;
  text-transform:uppercase;letter-spacing:.1em;
}
.color-grid{
  display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:14px;
}
.color-btn{
  padding:16px;border-radius:12px;border:2px solid #fff;
  cursor:pointer;font-size:.9rem;font-weight:700;color:#fff;
  text-transform:uppercase;letter-spacing:.08em;
}
.color-btn[data-color='red']{background:#dc2626;}
.color-btn[data-color='green']{background:#16a34a;}
.color-btn[data-color='blue']{background:#2563eb;}
.color-btn[data-color='yellow']{background:#eab308;}

.notification{
  position:fixed;top:20px;right:20px;padding:12px 18px;
  border-radius:10px;background:rgba(15,23,42,0.95);
  border:1px solid rgba(56,189,248,0.8);color:var(--text-main);
  font-size:.85rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.8);
  z-index:999;max-width:300px;
}
.notification.success{border-color:#16a34a;background:rgba(22,163,74,0.2);}
.notification.warning{border-color:#eab308;background:rgba(234,179,8,0.2);}
</style>
</head>
<body>
<div class="shell">
  <div class="container">
    <div class="header">
      <div>
        <h1>Neon UNO Arcade v2</h1>
        <div class="subtitle">
          Variable players (2–10), 1 human + smart bots, classic 500‑point UNO scoring. 🎨
        </div>
        <div class="badge-row">
          <div class="badge"><div class="dot"></div>Dynamic Player Count</div>
          <div class="badge">Stacking Draws</div>
        </div>
      </div>
      <div class="right-top">
        <div class="label-row">
          <div class="pill">
            <span>Name</span>
            <input id="player-name" type="text" value="Player" maxlength="20" />
          </div>
          <div class="pill">
            <span>Players</span>
            <select id="player-count">
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4" selected>4</option>
              <option value="5">5</option>
              <option value="6">6</option>
              <option value="7">7</option>
              <option value="8">8</option>
              <option value="9">9</option>
              <option value="10">10</option>
            </select>
          </div>
          <div class="pill">
            <span>Target</span>
            <select id="win-score">
              <option value="300">300</option>
              <option value="500" selected>500</option>
              <option value="1000">1000</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="main-layout">
      <div class="board">
        <div class="row" style="justify-content:space-between;">
          <div class="zone-title">Game Table</div>
          <div class="badge-turn" id="turn-label">Waiting...</div>
        </div>

        <div class="row">
          <div class="pile">
            <div class="pile-stack">
              <div class="card-uno" data-color="blue" style="cursor:pointer;" id="draw-pile-card">
                <div class="label">UNO</div>
              </div>
              <div class="shadow"></div>
            </div>
            <div class="pile-label">Draw Pile<br><span id="deck-count" style="color:var(--accent);">0</span></div>
          </div>
          <div class="pile">
            <div class="pile-stack" id="discard-pile">
              <div class="shadow"></div>
            </div>
            <div class="pile-label">Discard Pile<br><span id="discard-count" style="color:var(--accent);">0</span></div>
          </div>
        </div>

        <div class="zone-title" style="margin-top:10px;">Opponents</div>
        <div class="row" id="bots-row"></div>

        <div class="zone-title" style="margin-top:10px;">Your Hand</div>
        <div class="row" id="player-hand-row"></div>

        <div class="btn-row">
          <button class="btn btn-primary" id="new-game-btn">New Match</button>
          <button class="btn" id="draw-card-btn" disabled>Draw Card</button>
          <button class="btn" id="uno-btn" disabled>Call UNO!</button>
          <button class="btn" id="sort-btn">Sort Hand</button>
        </div>

        <div class="status-text" id="status-text">
          Set player count, then click "New Match" to start. You are always Player 0.
        </div>
        <div class="score-text" id="score-text">
          First to reach the target score wins the match. Scoring follows classic UNO rules.[web:592]
        </div>
      </div>

      <div>
        <div class="score-box">
          <div class="small-text" style="margin-bottom:6px;font-weight:600;font-size:.82rem;">
            Hall of Champions
          </div>
          <div id="scoreboard-wrap">
            <div class="small-text">No champions yet. Win a match to enter the board.</div>
          </div>
        </div>

        <div class="score-box" style="margin-top:12px;">
          <div class="small-text" style="margin-bottom:6px;font-weight:600;font-size:.82rem;">
            Current Match Scores
          </div>
          <div id="match-scores">
            <div class="small-text">Start a match to see scores.</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<div class="modal" id="color-modal">
  <div class="modal-content">
    <div class="modal-title">Choose Wild Color</div>
    <div class="small-text" style="margin-bottom:10px;">Select the color you want to continue with:</div>
    <div class="color-grid">
      <div class="color-btn" data-color="red" onclick="selectWildColor('red')">Red</div>
      <div class="color-btn" data-color="green" onclick="selectWildColor('green')">Green</div>
      <div class="color-btn" data-color="blue" onclick="selectWildColor('blue')">Blue</div>
      <div class="color-btn" data-color="yellow" onclick="selectWildColor('yellow')">Yellow</div>
    </div>
  </div>
</div>

<script>
// ===== CONSTANTS =====
const COLORS = ['red','green','blue','yellow'];
const VALUES = ['0','1','2','3','4','5','6','7','8','9','skip','reverse','draw2'];
const BOT_ICONS = ['🤖','🎯','⚡','🛰','🧠','🕹','📡','💾','🧮'];

// ===== DOM =====
const playerNameInput = document.getElementById('player-name');
const playerCountSelect = document.getElementById('player-count');
const winScoreSelect    = document.getElementById('win-score');
const playerHandRow     = document.getElementById('player-hand-row');
const botsRow           = document.getElementById('bots-row');
const discardPile       = document.getElementById('discard-pile');
const drawPileCard      = document.getElementById('draw-pile-card');
const statusText        = document.getElementById('status-text');
const scoreText         = document.getElementById('score-text');
const turnLabel         = document.getElementById('turn-label');
const newGameBtn        = document.getElementById('new-game-btn');
const drawCardBtn       = document.getElementById('draw-card-btn');
const unoBtn            = document.getElementById('uno-btn');
const sortBtn           = document.getElementById('sort-btn');
const colorModal        = document.getElementById('color-modal');
const scoreboardWrap    = document.getElementById('scoreboard-wrap');
const matchScoresDiv    = document.getElementById('match-scores');
const deckCountSpan     = document.getElementById('deck-count');
const discardCountSpan  = document.getElementById('discard-count');

// ===== STATE =====
let deck = [];
let discard = [];
let players = []; // [{name,isHuman,hand:[],score:0}]
let currentPlayerIndex = 0;
let direction = 1;
let targetScore = 500;
let roundNumber = 0;
let pendingDrawCount = 0;
let lastPlayedCard = null;
let unoCalled = false;
let gameStarted = false;
let matchOver = false;
let wildColorCallback = null;

// ===== UTIL =====
function shuffleDeck(d) {
  for (let i = d.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random()*(i+1));
    [d[i],d[j]]=[d[j],d[i]];
  }
  return d;
}
function buildDeck() {
  const d=[];
  COLORS.forEach(color=>{
    d.push({color,value:'0'});
    for(let i=1;i<VALUES.length;i++){
      d.push({color,value:VALUES[i]});
      d.push({color,value:VALUES[i]});
    }
  });
  for(let i=0;i<4;i++){
    d.push({color:'wild',value:'wild'});
    d.push({color:'wild',value:'wild4'});
  }
  return shuffleDeck(d);
}
function cardLabel(card){
  if(card.value==='skip') return '⏭';
  if(card.value==='reverse') return '🔁';
  if(card.value==='draw2') return '+2';
  if(card.value==='wild') return 'W';
  if(card.value==='wild4') return '+4';
  return card.value;
}
function cardPoints(card){
  if(card.value>='0' && card.value<='9') return parseInt(card.value,10);
  if(card.value==='skip'||card.value==='reverse'||card.value==='draw2') return 20;
  if(card.value==='wild'||card.value==='wild4') return 50; // official[web:592]
  return 0;
}
function canPlayOn(top,card,enforcedColor=null){
  const colorToMatch=enforcedColor||top.color;
  return card.color==='wild' || card.color===colorToMatch || card.value===top.value;
}
function nextIndex(i){
  const n=players.length;
  return (i + direction + n) % n;
}

// ===== UI =====
function renderCardElement(card, clickable, onClick, disabled=false){
  const div=document.createElement('div');
  div.className='card-uno';
  div.dataset.color=card.color==='wild'?'wild':card.color;
  if(disabled) div.classList.add('disabled');
  if(clickable) div.classList.add('clickable');
  const label=document.createElement('div');
  label.className='label';
  label.textContent=cardLabel(card);
  const small=document.createElement('div');
  small.className='small';
  small.textContent=card.color==='wild'?'WILD':card.color[0].toUpperCase();
  div.appendChild(label);div.appendChild(small);
  if(clickable && onClick) div.addEventListener('click',onClick);
  return div;
}
function showNotification(text,type='success'){
  const n=document.createElement('div');
  n.className='notification '+type;
  n.textContent=text;
  document.body.appendChild(n);
  setTimeout(()=>n.remove(),2600);
}
function renderState(){
  // discard top
  discardPile.innerHTML='';
  const shadow=document.createElement('div');
  shadow.className='shadow';
  discardPile.appendChild(shadow);
  if(discard.length>0){
    const top=discard[discard.length-1];
    discardPile.appendChild(renderCardElement(top,false,null,false));
  }
  discardCountSpan.textContent=discard.length;
  deckCountSpan.textContent=deck.length;

  // player hand (index 0 is human)
  const human=players[0];
  playerHandRow.innerHTML='';
  if(human){
    const top=discard[discard.length-1] || {color:'red',value:'0'};
    const enforcedColor=lastPlayedCard && lastPlayedCard.color && lastPlayedCard.color!=='wild'
      ? lastPlayedCard.color : null;
    human.hand.forEach((card,idx)=>{
      const playable = gameStarted && !matchOver &&
        currentPlayerIndex===0 &&
        pendingDrawCount===0 &&
        canPlayOn(top,card,enforcedColor);
      playerHandRow.appendChild(
        renderCardElement(card,playable,()=>playCard(0,idx),!playable)
      );
    });
  }

  // bots
  botsRow.innerHTML='';
  players.forEach((p,idx)=>{
    if(idx===0) return;
    const panel=document.createElement('div');
    panel.className='bot-panel';
    const name=document.createElement('div');
    name.className='bot-name';
    name.textContent=p.name;
    const count=document.createElement('div');
    count.className='bot-cards-count';
    count.textContent=`${p.hand.length} cards`;
    const preview=document.createElement('div');
    preview.className='bot-cards-preview';
    p.hand.forEach(()=>preview.appendChild(Object.assign(document.createElement('div'),{className:'mini-card'})));
    panel.appendChild(name);panel.appendChild(count);panel.appendChild(preview);
    botsRow.appendChild(panel);
  });

  if(!gameStarted){
    turnLabel.textContent='Waiting...';
  }else if(matchOver){
    turnLabel.textContent='Match over';
  }else if(currentPlayerIndex===0){
    turnLabel.textContent='Your turn';
  }else{
    turnLabel.textContent=`${players[currentPlayerIndex].name} thinking...`;
  }

  drawCardBtn.disabled = !(gameStarted && !matchOver && currentPlayerIndex===0);
  unoBtn.disabled      = !(gameStarted && !matchOver && currentPlayerIndex===0 && players[0].hand.length===2);
}

function openColorModal(cb){
  wildColorCallback=cb;
  colorModal.classList.add('show');
}
function selectWildColor(color){
  colorModal.classList.remove('show');
  if(wildColorCallback){
    wildColorCallback(color);
    wildColorCallback=null;
  }
}

// ===== GAME FLOW =====
function reshuffleIfNeeded(){
  if(deck.length===0 && discard.length>1){
    const top=discard.pop();
    deck=shuffleDeck(discard);
    discard=[top];
  }
}
function drawCards(playerIdx,count){
  for(let i=0;i<count;i++){
    reshuffleIfNeeded();
    if(deck.length===0) break;
    players[playerIdx].hand.push(deck.pop());
  }
}
function startMatch(){
  const total=parseInt(playerCountSelect.value,10);
  const humanName=playerNameInput.value.trim() || 'You';
  targetScore=parseInt(winScoreSelect.value,10) || 500;
  players=[];
  players.push({name:humanName,isHuman:true,hand:[],score:0});
  for(let i=1;i<total;i++){
    const botName=`Bot ${i} ${BOT_ICONS[(i-1)%BOT_ICONS.length]}`;
    players.push({name:botName,isHuman:false,hand:[],score:0});
  }
  roundNumber=0;
  matchOver=false;
  statusText.textContent=`Match started with ${total} players. First to ${targetScore} points wins.`;
  startRound();
}
function startRound(){
  deck=buildDeck();
  discard=[];
  players.forEach(p=>p.hand=[]);
  roundNumber++;
  pendingDrawCount=0;
  lastPlayedCard=null;
  unoCalled=false;
  // deal 7 each[web:592]
  for(let i=0;i<7;i++){
    players.forEach(p=>p.hand.push(deck.pop()));
  }
  // flip first non-wild
  let first;
  do{
    first=deck.pop();
    if(first.color==='wild') deck.unshift(first);
    else break;
  }while(true);
  discard.push(first);
  currentPlayerIndex=0;
  direction=1;
  gameStarted=true;
  scoreText.textContent=`Round ${roundNumber}. Scores: `+players.map(p=>`${p.name}: ${p.score}`).join(' | ');
  renderState();
}

function handleDrawAction(){
  if(!gameStarted || matchOver || currentPlayerIndex!==0) return;
  const top=discard[discard.length-1];
  const human=players[0];
  if(pendingDrawCount===0 && human.hand.some(c=>canPlayOn(top,c,null))){
    showNotification('You have a playable card. Click it instead.','warning');
    return;
  }
  const drawAmount = pendingDrawCount>0 ? pendingDrawCount : 1;
  drawCards(0,drawAmount);
  pendingDrawCount=0;
  statusText.textContent=`You draw ${drawAmount} card(s).`;
  currentPlayerIndex=nextIndex(currentPlayerIndex);
  renderState();
  if(!players[currentPlayerIndex].isHuman && !matchOver) setTimeout(botTurn,700);
}

function handleRoundWin(winnerIdx){
  let gained=0;
  players.forEach((p,idx)=>{
    if(idx===winnerIdx) return;
    p.hand.forEach(c=>gained+=cardPoints(c));
  });
  players[winnerIdx].score+=gained;
  const winnerName=players[winnerIdx].name;
  statusText.textContent=`${winnerName} wins the round and gains ${gained} points.`;
  scoreText.textContent=`Scores: `+players.map(p=>`${p.name}: ${p.score}`).join(' | ')+` (target ${targetScore})`;

  if(players[winnerIdx].score>=targetScore){
    matchOver=true;
    const detail=`players=${players.length},winner=${winnerName},scores=`+
      players.map(p=>`${p.name}:${p.score}`).join(',');
    if(players[0].name===winnerName){
      saveScore(players[0].score, roundNumber, detail);
      showNotification('You win the match!','success');
    }else{
      showNotification(`${winnerName} wins the match.`,'warning');
    }
  }else{
    showNotification(`${winnerName} wins the round +${gained}.`,'success');
    startRound();
  }
}

function playCard(playerIdx,cardIdx){
  if(matchOver || !gameStarted) return;
  if(playerIdx!==currentPlayerIndex) return;
  const player=players[playerIdx];
  const card=player.hand[cardIdx];
  const top=discard[discard.length-1];
  const enforcedColor=lastPlayedCard && lastPlayedCard.color && lastPlayedCard.color!=='wild'
    ? lastPlayedCard.color : null;

  if(pendingDrawCount>0 && !(card.value==='draw2'||card.value==='wild4')) return;
  if(!canPlayOn(top,card,enforcedColor)) return;

  if(player.isHuman && player.hand.length===2 && !unoCalled){
    showNotification('You forgot UNO! Draw 2 penalty.','warning'); // standard penalty[web:599]
    drawCards(0,2);
  }

  player.hand.splice(cardIdx,1);
  function resolveCard(finalCard){
    discard.push(finalCard);
    lastPlayedCard={color:finalCard.color,value:finalCard.value};
    statusText.textContent=`${player.name} played ${cardLabel(finalCard)} ${finalCard.color.toUpperCase()}.`;
    unoCalled=false;
    let skipNext=false;
    if(finalCard.value==='skip') skipNext=true;
    else if(finalCard.value==='reverse') direction*=-1;
    else if(finalCard.value==='draw2') pendingDrawCount+=2;
    else if(finalCard.value==='wild4') pendingDrawCount+=4;

    renderState();

    if(player.hand.length===0){
      handleRoundWin(playerIdx);
      renderState();
      return;
    }

    let next=nextIndex(currentPlayerIndex);
    if(skipNext) next=nextIndex(next);
    currentPlayerIndex=next;
    renderState();
    if(!players[currentPlayerIndex].isHuman && !matchOver) setTimeout(botTurn,700);
  }

  if(card.color==='wild'){
    if(player.isHuman){
      openColorModal(color=>{
        card.color=color;
        resolveCard(card);
      });
    }else{
      card.color=bestBotColor(playerIdx);
      resolveCard(card);
    }
  }else{
    resolveCard(card);
  }
}

// ===== BOT =====
function bestBotColor(idx){
  const counts={red:0,green:0,blue:0,yellow:0};
  players[idx].hand.forEach(c=>{
    if(COLORS.includes(c.color)) counts[c.color]++;
  });
  let best='red',max=-1;
  COLORS.forEach(c=>{
    if(counts[c]>max){max=counts[c];best=c;}
  });
  return best;
}
function botTurn(){
  if(matchOver || !gameStarted) return;
  const idx=currentPlayerIndex;
  const bot=players[idx];
  if(bot.isHuman) return;
  const hand=bot.hand;
  const top=discard[discard.length-1];
  const enforcedColor=lastPlayedCard && lastPlayedCard.color && lastPlayedCard.color!=='wild'
    ? lastPlayedCard.color : null;

  if(pendingDrawCount>0){
    const drawIdx=hand.findIndex(c=>c.value==='draw2'||c.value==='wild4');
    if(drawIdx>=0){
      playCard(idx,drawIdx);
      return;
    }
    drawCards(idx,pendingDrawCount);
    pendingDrawCount=0;
    statusText.textContent=`${bot.name} draws penalty cards.`;
    currentPlayerIndex=nextIndex(currentPlayerIndex);
    renderState();
    if(!players[currentPlayerIndex].isHuman && !matchOver) setTimeout(botTurn,700);
    return;
  }

  const playableIndices=[];
  hand.forEach((c,i)=>{if(canPlayOn(top,c,enforcedColor)) playableIndices.push(i);});
  if(playableIndices.length===0){
    drawCards(idx,1);
    statusText.textContent=`${bot.name} draws a card.`;
    currentPlayerIndex=nextIndex(currentPlayerIndex);
    renderState();
    if(!players[currentPlayerIndex].isHuman && !matchOver) setTimeout(botTurn,700);
    return;
  }
  let choice=playableIndices.find(i=>hand[i].color!=='wild');
  if(choice===undefined) choice=playableIndices[0];
  playCard(idx,choice);
}

// ===== SCOREBOARD PERSISTENCE =====
async function saveScore(score,rounds,detail){
  const payload={
    name: playerNameInput.value.trim() || 'Player',
    score, rounds, detail
  };
  try{
    const res=await fetch(window.location.href,{
      method:'POST',
      headers:{'Content-Type':'application/json; charset=UTF-8'},
      body:JSON.stringify(payload)
    });
    const json=await res.json();
    console.log('saveScore',json);
    loadScores();
  }catch(e){console.error(e);}
}
async function loadScores(){
  scoreboardWrap.innerHTML='<div class="small-text">Loading scores...</div>';
  try{
    const res=await fetch(window.location.href+'?scores=1');
    const data=await res.json();
    if(!data.ok || !data.rows || data.rows.length===0){
      scoreboardWrap.innerHTML='<div class="small-text">No UNO matches recorded yet.</div>';
      return;
    }
    let html='<table><thead><tr><th>#</th><th>Name</th><th>Score</th><th>Rounds</th></tr></thead><tbody>';
    data.rows.forEach((r,i)=>{
      html+=`<tr><td>${i+1}</td><td>${r.player_name}</td><td>${r.score}</td><td>${r.rounds}</td></tr>`;
    });
    html+='</tbody></table>';
    scoreboardWrap.innerHTML=html;
  }catch(e){
    console.error(e);
    scoreboardWrap.innerHTML='<div class="small-text">Scoreboard error.</div>';
  }
}

// ===== EVENTS =====
newGameBtn.addEventListener('click',startMatch);
drawCardBtn.addEventListener('click',handleDrawAction);
drawPileCard.addEventListener('click',handleDrawAction);
unoBtn.addEventListener('click',()=>{
  if(!gameStarted || matchOver || currentPlayerIndex!==0) return;
  if(players[0].hand.length===2){
    unoCalled=true;
    showNotification('You yell "UNO!"','success');
  }
});
sortBtn.addEventListener('click',()=>{
  if(!gameStarted) return;
  const hand=players[0].hand;
  hand.sort((a,b)=>{
    const c1=COLORS.indexOf(a.color),c2=COLORS.indexOf(b.color);
    if(c1!==c2) return c1-c2;
    const v1=VALUES.indexOf(a.value),v2=VALUES.indexOf(b.value);
    return v1-v2;
  });
  renderState();
});

// init
loadScores();
renderState();
</script>
</body>
</html>

