<?php
// ---------- PHP BACKEND ----------
$DB_HOST = 'localhost';
$DB_NAME = 'gamehub';   // must match your DB
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

// --- AJAX: leaderboard (Top 5 by total score for main_player) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['leaderboard'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $pdo = get_pdo();
        $stmt = $pdo->query(
            "SELECT main_player,
                    SUM(round_score) AS total_score,
                    COUNT(*)         AS games
             FROM heads_tails_rounds
             GROUP BY main_player
             ORDER BY total_score DESC, games DESC, MIN(created_at) ASC
             LIMIT 5"
        );
        $rows = $stmt->fetchAll();
        echo json_encode(['ok' => true, 'rows' => $rows]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- AJAX: save one round ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON', 'raw' => $raw]);
        exit;
    }

    $mainPlayer = trim($data['main_player'] ?? '');
    $p1Name     = trim($data['player1_name'] ?? '');
    $p2Name     = trim($data['player2_name'] ?? '');
    $mode       = $data['mode'] ?? 'single';
    $flip       = $data['flip'] ?? '';
    $p1Choice   = $data['p1_choice'] ?? '';
    $p2Choice   = $data['p2_choice'] ?? 'None';
    $roundScore = (int)($data['score'] ?? 0);
    $livesAfter = (int)($data['lives_after_round'] ?? 3);
    $detail     = trim($data['detail'] ?? '');

    if ($mainPlayer === '' || $p1Name === '' || $flip === '' || $p1Choice === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing required data', 'data' => $data]);
        exit;
    }

    try {
        $pdo = get_pdo();

        $stmt = $pdo->prepare(
            "INSERT INTO heads_tails_rounds
             (main_player, player1_name, player2_name, mode, flip_result,
              p1_choice, p2_choice, round_score, lives_after_round)
             VALUES (:main_player, :p1, :p2, :mode, :flip, :c1, :c2, :score, :lives)"
        );
        $stmt->execute([
            ':main_player' => $mainPlayer,
            ':p1'          => $p1Name,
            ':p2'          => $p2Name,
            ':mode'        => $mode,
            ':flip'        => $flip,
            ':c1'          => $p1Choice,
            ':c2'          => $p2Choice,
            ':score'       => $roundScore,
            ':lives'       => $livesAfter,
        ]);

        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'ok'    => false,
            'error' => 'DB error',
            'detail'=> $e->getMessage(),
            'data'  => $data
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>🎰 Heads / Tails Duel – Neon Arcade</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pixelify+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#020617;
  --card-bg:#020617;
  --card-border:#1d4ed8;
  --accent:#38bdf8;
  --accent2:#e7dc0c;
  --btn-yellow:#facc15;
  --btn-black:#000000;
  --text-main:#e5e7eb;
  --text-muted:#9ca3af;
}

*{box-sizing:border-box;font-family:"Pixelify Sans",system-ui,sans-serif;image-rendering:pixelated;}

body{
  margin:0;min-height:100vh;background:
    radial-gradient(circle at 0 0,#0b1120, #020617 55%),
    radial-gradient(circle at 100% 100%,#1d4ed8,#020617 45%);
  color:var(--text-main);
  display:flex;justify-content:center;align-items:flex-start;
  padding:20px 12px;
  position:relative;overflow:hidden;
}
body::before{
  content:"";position:fixed;inset:0;pointer-events:none;
  background:repeating-linear-gradient(
    to bottom,
    rgba(15,23,42,0.4) 0px,
    rgba(15,23,42,0.4) 1px,
    transparent 2px,
    transparent 3px
  );
  mix-blend-mode:soft-light;opacity:0.5;
}
body::after{
  content:"";position:fixed;inset:0;pointer-events:none;
  box-shadow:0 0 80px rgba(56,189,248,0.25) inset;
}

.shell{max-width:1120px;width:100%;}
.container{
  padding:16px 20px 20px;border-radius:20px;background:rgba(15,23,42,0.96);
  border:1px solid rgba(31,41,55,0.9);
  box-shadow:0 18px 40px rgba(0,0,0,0.9);
  position:relative;overflow:hidden;
}
.container::before{
  content:"";position:absolute;inset:-40%;background:
    radial-gradient(circle at 0 0,rgba(56,189,248,0.25),transparent 60%),
    radial-gradient(circle at 100% 100%,rgba(248,113,113,0.25),transparent 55%);
  opacity:0.9;pointer-events:none;z-index:-1;
}

h1{
  margin:0 0 4px;font-size:1.5rem;letter-spacing:.18em;text-transform:uppercase;
  text-shadow:0 0 10px rgba(56,189,248,0.9);
}
.subtitle{
  font-size:.8rem;color:var(--text-muted);margin-bottom:8px;letter-spacing:.08em;
}
.status-line{
  font-size:.82rem;color:var(--text-muted);margin-bottom:12px;
}

.top-row{
  display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:10px;
  flex-wrap:wrap;
}
.name-stack{
  display:flex;flex-direction:column;gap:5px;
}
.name-pill{
  padding:4px 10px;border-radius:999px;background:#020617;
  border:1px solid #4b5563;font-size:.78rem;display:flex;align-items:center;gap:6px;
}
.name-pill span{color:var(--text-muted);text-transform:uppercase;letter-spacing:.08em;}
.name-pill input{
  background:transparent;border:none;color:var(--text-main);outline:none;
  font-size:.9rem;min-width:140px;
}

.mode-pill{
  padding:4px 10px;border-radius:999px;background:#020617;
  border:1px solid #4b5563;font-size:.78rem;display:flex;align-items:center;gap:6px;
}
.mode-pill label{
  display:flex;align-items:center;gap:3px;font-size:.75rem;color:var(--text-muted);
}
.mode-pill input{accent-color:#4f46e5;}

.main-layout{
  display:grid;grid-template-columns:2.1fr 0.9fr;gap:12px;
}
@media(max-width:900px){
  .main-layout{grid-template-columns:1fr;}
}

.game-layout{
  display:grid;grid-template-columns:1fr auto 1fr;gap:10px;align-items:flex-start;
}
@media(max-width:780px){
  .game-layout{grid-template-columns:1fr;justify-items:center;}
}

.card-outer{
  background:linear-gradient(135deg,#38bdf8,#a855f7,#f97316);
  border-radius:18px;padding:2px;
  box-shadow:0 0 24px rgba(56,189,248,0.7);
}
.card{
  background:radial-gradient(circle at 0 0,#020617,#020617);
  border-radius:16px;padding:9px 11px 10px;
  border:1px solid rgba(148,163,184,0.6);min-width:220px;
  position:relative;overflow:hidden;
}
.card::after{
  content:"";position:absolute;inset:0;
  background:radial-gradient(circle at 0 -20%,rgba(248,250,252,0.05),transparent 60%);
  pointer-events:none;opacity:.8;
}
.card-title{
  margin:0 0 2px;font-size:.95rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;
}
.card-title.p1{color:var(--accent);}
.card-title.p2{color:var(--accent2);}
.card-sub{
  font-size:.76rem;color:var(--text-muted);margin-bottom:6px;
}
.choice-label{
  font-size:.8rem;color:var(--text-main);margin-bottom:6px;
}
.btn-row{
  display:flex;gap:6px;flex-wrap:wrap;
}
.choice-btn{
  border:none;border-radius:9px;padding:5px 11px;font-size:.8rem;
  background:#020617;color:#e5e7eb;cursor:pointer;
  border:1px solid rgba(55,65,81,0.9);
  transition:transform .12s,background .12s,box-shadow .12s,border-color .12s;
}
.choice-btn:hover{
  transform:translateY(-1px);
  background:#111827;
  box-shadow:0 0 12px rgba(148,163,184,0.7);
}
.choice-btn.selected{
  background:#facc15;color:#020617;
  box-shadow:0 0 16px rgba(250,204,21,0.9);
  border-color:#facc15;
}

.coin-card{text-align:center;min-width:220px;}
.coin-title{
  margin:0 0 2px;font-size:.9rem;color:#e5e7eb;
}
.coin-sub{
  font-size:.78rem;color:var(--text-muted);margin-bottom:4px;
}
.coin-canvas{
  width:135px;height:135px;border-radius:50%;margin:0 auto 6px;
  position:relative;background:#020617;
}
.coin-ring{
  position:absolute;inset:8px;border-radius:50%;
  background:radial-gradient(circle at 30% 20%,#facc15,#b45309);
  box-shadow:0 0 20px rgba(250,204,21,0.95);
}
.coin-inner{
  position:absolute;inset:16px;border-radius:50%;
  background:radial-gradient(circle at 40% 20%,#0f172a,#020617);
  border:2px solid #1f2937;
}
.coin-text{
  position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  font-size:1.4rem;font-weight:700;color:#e5e7eb;text-shadow:0 0 8px rgba(248,250,252,0.9);
}
.coin-card button{
  border:none;border-radius:999px;padding:6px 16px;font-size:.8rem;font-weight:600;
  cursor:pointer;background:#4b5563;color:#e5e7eb;
  text-transform:uppercase;letter-spacing:.08em;
}
.coin-card button:hover{
  background:#6b7280;box-shadow:0 0 14px rgba(148,163,184,0.8);
}
.coin-spin{animation:coin-spin 0.75s ease-out;}
@keyframes coin-spin{
  0%{transform:rotateY(0deg) scale(1);opacity:1;}
  50%{transform:rotateY(220deg) scale(1.1);opacity:.8;}
  100%{transform:rotateY(360deg) scale(1);opacity:1;}
}

.bottom-row{
  margin-top:10px;display:flex;justify-content:space-between;align-items:center;
  gap:10px;flex-wrap:wrap;font-size:.82rem;
}
.result-text{color:#e5e7eb;}
.score-text{color:#facc15;font-weight:600;}
.newgame-btn{
  border:none;border-radius:999px;padding:6px 13px;font-size:.78rem;font-weight:600;
  cursor:pointer;background:#000000;color:#facc15;
  text-transform:uppercase;letter-spacing:.08em;
}
.newgame-btn:hover{
  background:#111827;box-shadow:0 0 12px rgba(250,204,21,0.8);
}

.lb-card{
  background:radial-gradient(circle at 0 0,#020617,#020617);
  border-radius:16px;padding:10px 10px 12px;
  border:1px solid rgba(148,163,184,0.65);
  position:relative;overflow:hidden;
}
.lb-card::before{
  content:"🏆";position:absolute;right:10px;top:4px;font-size:1.3rem;opacity:.12;
}
.lb-title{
  font-size:.78rem;text-transform:uppercase;letter-spacing:.16em;
  color:var(--text-muted);margin:0 0 6px;
}
.lb-list{font-size:.78rem;max-height:210px;overflow-y:auto;padding-right:4px;}
.lb-item{
  display:flex;justify-content:space-between;align-items:center;
  padding:3px 4px;border-radius:8px;margin-bottom:4px;
  background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);
}
.lb-rank{
  min-width:20px;text-align:center;font-size:.76rem;
}
.lb-name{
  flex:1;margin:0 6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.lb-score{
  color:#facc15;font-weight:600;
}
.lb-foot{
  margin-top:4px;font-size:.72rem;color:var(--text-muted);line-height:1.4;
}
.small-text{font-size:.75rem;color:var(--text-muted);}
</style>
</head>
<body>
<div class="shell">
  <div class="container">
    <h1>🎰 Heads / Tails Duel</h1>
    <div class="subtitle">Arcade coin flip with 3 lives, streak scoring, and a glowing leaderboard. 💥</div>

    <div class="top-row">
      <div class="name-stack">
        <div class="name-pill">
          <span>P1</span>
          <input id="player1-name" type="text" placeholder="Player 1 name" />
        </div>
        <div class="name-pill">
          <span>P2</span>
          <input id="player2-name" type="text" placeholder="Player 2 / CPU name" />
        </div>
      </div>
      <div class="mode-pill">
        <span>Mode</span>
        <label><input type="radio" name="mode" value="single" checked> Single 🧠 vs CPU</label>
        <label><input type="radio" name="mode" value="multi"> Two Players 🤝</label>
      </div>
    </div>

    <div class="status-line" id="status-line">
      Set names, choose Heads/Tails, then flip 🪙. You have 3 lives.
    </div>

    <div class="main-layout">
      <div>
        <div class="game-layout">
          <div class="card-outer">
            <div class="card">
              <h2 class="card-title p1">Player 1</h2>
              <div class="card-sub" id="p1-name-label">Name: (set above)</div>
              <div class="choice-label" id="p1-choice-label">Choice: -</div>
              <div class="btn-row">
                <button class="choice-btn" data-player="1" data-choice="Heads">😄 Heads</button>
                <button class="choice-btn" data-player="1" data-choice="Tails">😈 Tails</button>
              </div>
            </div>
          </div>

          <div class="card-outer">
            <div class="card coin-card">
              <h2 class="coin-title">Arcade Coin 🪙</h2>
              <div class="coin-sub" id="coin-sub">Lock choices, then flip for glory.</div>
              <div class="coin-canvas" id="coin">
                <div class="coin-ring"></div>
                <div class="coin-inner"></div>
                <div class="coin-text" id="coin-text">-</div>
              </div>
              <button id="flip-btn">Flip Coin!</button>
            </div>
          </div>

          <div class="card-outer">
            <div class="card">
              <h2 class="card-title p2" id="p2-title">Player 2</h2>
              <div class="card-sub" id="p2-name-label">Name: (set above or CPU)</div>
              <div class="choice-label" id="p2-choice-label">Choice: - (CPU)</div>
              <div class="btn-row" id="p2-buttons">
                <button class="choice-btn" data-player="2" data-choice="Heads">🤖 Heads</button>
                <button class="choice-btn" data-player="2" data-choice="Tails">🤖 Tails</button>
              </div>
            </div>
          </div>
        </div>

        <div class="bottom-row">
          <div>
            <span class="result-text" id="result-text">Result: waiting for a flip...</span>
            <span class="score-text" id="score-text">Score: 0</span>
            <span class="score-text" id="lives-text" style="margin-left:8px;">Lives: ❤️❤️❤️</span>
          </div>
          <button class="newgame-btn" id="newgame-btn">Reset Streak ♻️</button>
        </div>
      </div>

      <div class="card-outer">
        <div class="lb-card">
          <h2 class="lb-title">Top 5 Coin Masters</h2>
          <div class="lb-list" id="lb-list">
            <div class="small-text">No scores yet. Win some rounds to enter the board. 🔓</div>
          </div>
          <div class="lb-foot">
            Tips:  
            • P1 name is used for ranking.  
            • Each miss costs 1 life (3 total).  
            • Long streaks with few misses climb fastest. 🔥
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
let lastRoundData = {
  main_player: '',
  player1_name: '',
  player2_name: '',
  mode: 'single',
  flip: '',
  p1_choice: '',
  p2_choice: 'None',
  score: 0,
  detail: '',
  lives_after_round: 3
};

async function saveScore(data){
  try{
    const res = await fetch(window.location.href,{
      method:"POST",
      headers:{"Content-Type":"application/json; charset=UTF-8"},
      body:JSON.stringify(data)
    });
    const json = await res.json();
    console.log('saveScore response:', json);
    await loadLeaderboard();
  }catch(e){
    console.error("DB save error",e);
  }
}

async function loadLeaderboard(){
  const box = document.getElementById('lb-list');
  try{
    const res = await fetch(window.location.href + '?leaderboard=1');
    const data = await res.json();
    console.log('leaderboard response:', data);
    if(!data.ok || !data.rows || data.rows.length===0){
      box.innerHTML = '<div class="small-text">No scores yet. Win some rounds to enter the board. 🔓</div>';
      return;
    }
    box.innerHTML = '';
    data.rows.forEach((row, idx)=>{
      const div = document.createElement('div');
      div.className = 'lb-item';
      let medal = '';
      if(idx===0) medal = '🥇';
      else if(idx===1) medal = '🥈';
      else if(idx===2) medal = '🥉';
      const rankSpan = document.createElement('span');
      rankSpan.className = 'lb-rank';
      rankSpan.textContent = idx+1;
      const nameSpan = document.createElement('span');
      nameSpan.className = 'lb-name';
      nameSpan.textContent = (medal ? medal+' ' : '') + row.main_player;
      const scoreSpan = document.createElement('span');
      scoreSpan.className = 'lb-score';
      scoreSpan.textContent = row.total_score;
      div.appendChild(rankSpan);
      div.appendChild(nameSpan);
      div.appendChild(scoreSpan);
      box.appendChild(div);
    });
  }catch(e){
    console.error('leaderboard error:',e);
    box.innerHTML = '<div class="small-text">Leaderboard error (see console). 😵‍💫</div>';
  }
}

const p1Input = document.getElementById('player1-name');
const p2Input = document.getElementById('player2-name');
const p1NameLabel = document.getElementById('p1-name-label');
const p2NameLabel = document.getElementById('p2-name-label');
const p2Title = document.getElementById('p2-title');
const modeRadios = document.querySelectorAll('input[name="mode"]');

const p1ChoiceLabel = document.getElementById('p1-choice-label');
const p2ChoiceLabel = document.getElementById('p2-choice-label');
const coinEl = document.getElementById('coin');
const coinText = document.getElementById('coin-text');
const coinSub = document.getElementById('coin-sub');
const flipBtn = document.getElementById('flip-btn');
const statusLine = document.getElementById('status-line');
const resultText = document.getElementById('result-text');
const scoreText = document.getElementById('score-text');
const newGameBtn = document.getElementById('newgame-btn');
const livesText = document.getElementById('lives-text');

let p1Choice = null;
let p2Choice = null;
let scoreTotal = 0;
let flipping = false;
let lives = 3;

function renderLives(){
  const hearts = lives <= 0 ? '💀' : '❤️'.repeat(lives);
  livesText.textContent = 'Lives: ' + hearts;
}
renderLives();

function currentMode(){
  for(const r of modeRadios){
    if(r.checked) return r.value;
  }
  return 'single';
}

p1Input.addEventListener('input',()=>{
  const name = p1Input.value.trim() || 'Player 1';
  p1NameLabel.textContent = 'Name: ' + name;
});

p2Input.addEventListener('input',()=>{
  const mode = currentMode();
  let name = p2Input.value.trim();
  if(name === '') name = (mode === 'single') ? 'CPU' : 'Player 2';
  p2NameLabel.textContent = 'Name: ' + name;
});

modeRadios.forEach(r=>{
  r.addEventListener('change',applyModeUI);
});

function applyModeUI(){
  const mode = currentMode();
  lastRoundData.mode = mode;
  const p2Buttons = document.getElementById('p2-buttons');
  let p2Name = p2Input.value.trim();
  if(mode === 'single'){
    if(p2Name === '') p2Name = 'CPU';
    p2Title.textContent = 'CPU';
    p2NameLabel.textContent = 'Name: ' + p2Name;
    p2ChoiceLabel.textContent = 'Choice: - (CPU)';
    Array.from(p2Buttons.querySelectorAll('.choice-btn')).forEach(b=>{
      b.disabled = true;
      b.classList.remove('selected');
    });
    statusLine.textContent = 'Single: P1 vs CPU. You have 3 lives. 🔥';
  }else{
    if(p2Name === '') p2Name = 'Player 2';
    p2Title.textContent = 'Player 2';
    p2NameLabel.textContent = 'Name: ' + p2Name;
    p2ChoiceLabel.textContent = 'Choice: -';
    Array.from(p2Buttons.querySelectorAll('.choice-btn')).forEach(b=>{
      b.disabled = false;
      b.classList.remove('selected');
    });
    statusLine.textContent = 'Two Players: P1 has 3 lives, see who carries the streak.';
  }
  resetRound(false);
}

document.querySelectorAll('.choice-btn').forEach(btn=>{
  btn.addEventListener('click',()=>{
    if(flipping) return;
    const player = btn.getAttribute('data-player');
    const choice = btn.getAttribute('data-choice');
    setChoice(player, choice, btn);
  });
});

function setChoice(player, choice, btn){
  const buttons = document.querySelectorAll(`.choice-btn[data-player="${player}"]`);
  buttons.forEach(b=>b.classList.remove('selected'));
  btn.classList.add('selected');

  if(player === '1'){
    p1Choice = choice;
    p1ChoiceLabel.textContent = 'Choice: ' + choice;
  }else{
    p2Choice = choice;
    p2ChoiceLabel.textContent = 'Choice: ' + choice;
  }
}

flipBtn.addEventListener('click', async ()=>{
  if(flipping) return;
  const mode = currentMode();
  const p1Name = p1Input.value.trim() || 'Player 1';
  let p2Name = p2Input.value.trim();
  if(mode === 'single'){
    if(p2Name === '') p2Name = 'CPU';
  }else{
    if(p2Name === '') p2Name = 'Player 2';
  }

  if(lives <= 0){
    alert('You are out of lives. Reset streak to play again.');
    return;
  }
  if(!p1Choice){
    alert('Player 1 must choose Heads or Tails first.');
    return;
  }
  if(mode === 'multi' && !p2Choice){
    alert('Player 2 must choose Heads or Tails first.');
    return;
  }

  flipping = true;
  coinEl.classList.add('coin-spin');
  coinText.textContent = '🎲';
  coinSub.textContent = 'Flipping...';
  resultText.textContent = 'Result: ...';

  await new Promise(res=>setTimeout(res,750));

  const rand = Math.random()<0.5 ? 'Heads' : 'Tails';
  coinText.textContent = rand === 'Heads' ? 'H' : 'T';
  coinSub.textContent = 'Result: ' + rand;
  coinEl.classList.remove('coin-spin');

  if(mode === 'single'){
    if(!p2Choice){
      p2Choice = Math.random()<0.5 ? 'Heads' : 'Tails';
      p2ChoiceLabel.textContent = 'Choice: ' + p2Choice + ' (CPU)';
    }
  }

  let text;
  let roundScore = 0;
  let p1Win = false;

  if(mode === 'single'){
    p1Win = (p1Choice === rand);
    const cpuWin = (p2Choice === rand);
    if(p1Win && !cpuWin){
      text = `${p1Name} guessed correctly! +10 ⭐`;
      roundScore = 10;
    }else if(!p1Win && cpuWin){
      text = `${p2Name} (CPU) guessed correctly. +0 🤖`;
      roundScore = 0;
    }else if(p1Win && cpuWin){
      text = `${p1Name} & CPU guessed correctly. +5 🤝`;
      roundScore = 5;
    }else{
      text = 'No one guessed correctly. +0 😵';
      roundScore = 0;
    }
  }else{
    p1Win = (p1Choice === rand);
    const p2Win = (p2Choice === rand);
    if(p1Win && !p2Win){
      text = `${p1Name} guessed correctly! +10 🚀`;
      roundScore = 10;
    }else if(!p1Win && p2Win){
      text = `${p2Name} guessed correctly! +10 ⚡`;
      roundScore = 10;
    }else if(p1Win && p2Win){
      text = `${p1Name} & ${p2Name} guessed correctly! +5 🎉`;
      roundScore = 5;
    }else{
      text = 'No one guessed correctly. +0 😬';
      roundScore = 0;
    }
  }

  if(!p1Win){
    lives = Math.max(0, lives - 1);
    renderLives();
    if(lives === 0){
      text += ' | Out of lives! 💀';
    }
  }

  scoreTotal += roundScore;
  resultText.textContent = 'Result: ' + text;
  scoreText.textContent = 'Score: ' + scoreTotal;

  lastRoundData.main_player       = p1Name;
  lastRoundData.player1_name      = p1Name;
  lastRoundData.player2_name      = p2Name;
  lastRoundData.mode              = mode;
  lastRoundData.flip              = rand;
  lastRoundData.p1_choice         = p1Choice;
  lastRoundData.p2_choice         = p2Choice || 'None';
  lastRoundData.score             = roundScore;
  lastRoundData.detail            = text + ` (flip=${rand}, P1=${p1Choice}, P2=${p2Choice||'None'})`;
  lastRoundData.lives_after_round = lives;

  saveScore(lastRoundData);
  flipping = false;
});

newGameBtn.addEventListener('click',()=>resetRound(true));

function resetRound(resetScore){
  p1Choice = null;
  p2Choice = null;
  document.querySelectorAll('.choice-btn').forEach(b=>b.classList.remove('selected'));
  p1ChoiceLabel.textContent = 'Choice: -';
  const mode = currentMode();
  if(mode === 'single'){
    p2ChoiceLabel.textContent = 'Choice: - (CPU)';
  }else{
    p2ChoiceLabel.textContent = 'Choice: -';
  }
  coinText.textContent = '-';
  coinSub.textContent = 'Lock choices, then flip for glory.';
  resultText.textContent = 'Result: waiting for a flip...';
  if(resetScore){
    scoreTotal = 0;
    scoreText.textContent = 'Score: 0';
    lives = 3;
    renderLives();
  }
}

applyModeUI();
loadLeaderboard();
</script>
</body>
</html>
