<?php
// ---------- PHP BACKEND (MySQL + gamehub_database) ----------
$DB_HOST = 'localhost';
$DB_NAME = 'gamehub_database';
$DB_USER = 'root';
$DB_PASS = '';

$topScores = [];

try {
    $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Fetch last 5 highest scoring entries (by score then created_at)
    $stmt = $pdo->query(
        "SELECT player_name, score, detail, created_at
         FROM dice_scores
         ORDER BY score DESC, created_at DESC
         LIMIT 5"
    );
    $topScores = $stmt->fetchAll();
} catch (PDOException $e) {
    $pdo = null;
}

// ---------- AJAX POST: save round result ----------
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
    $detail     = trim($data['detail'] ?? '');

    if ($playerName === '' || !is_numeric($score)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    try {
        $dsn = "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4";
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Ensure theme + dummy user
        $pdo->exec(
            "INSERT IGNORE INTO themes (theme_id, color)
             VALUES (1, 'neon')"
        );
        $pdo->exec(
            "INSERT IGNORE INTO users (user_id, email, password)
             VALUES (1, 'dice@example.com', 'dummy')"
        );
        $userId = 1;

        // Ensure game row
        $gameTitle = 'Roll the Dice – Neon';
        $stmt = $pdo->prepare("SELECT game_id FROM games WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $gameTitle]);
        $row = $stmt->fetch();

        if ($row) {
            $gameId = (int)$row['game_id'];
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO games (name, description)
                 VALUES (:name, :desc)"
            );
            $stmt->execute([
                ':name' => $gameTitle,
                ':desc' => 'Neon Guess the Dice multiplayer with lives and scores',
            ]);
            $gameId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO single_games (game_id, difficulty, bot_id)
                 VALUES (:game_id, :diff, :bot_id)"
            );
            $stmt->execute([
                ':game_id' => $gameId,
                ':diff'    => 'normal',
                ':bot_id'  => null,
            ]);
        }

        // Session
        $stmt = $pdo->prepare(
            "INSERT INTO game_sessions (game_id, host_player_id, start_time, end_time)
             VALUES (:game_id, :host_player_id, NOW(), '0000-00-00 00:00:00')"
        );
        $stmt->execute([
            ':game_id'        => $gameId,
            ':host_player_id' => 1,
        ]);
        $sessionId = (int)$pdo->lastInsertId();

        // History
        $stmt = $pdo->prepare(
            "INSERT INTO game_history (game_id, session_id, result, time_stamp)
             VALUES (:game_id, :session_id, :result, NOW())"
        );
        $stmt->execute([
            ':game_id'    => $gameId,
            ':session_id' => $sessionId,
            ':result'     => $detail,
        ]);
        $historyId = (int)$pdo->lastInsertId();

        // Existing leaderboards table
        $stmt = $pdo->prepare(
            "INSERT INTO leaderboards (history_id, game_id, player_id, score, level)
             VALUES (:history_id, :game_id, :player_id, :score, :level)"
        );
        $stmt->execute([
            ':history_id' => $historyId,
            ':game_id'    => $gameId,
            ':player_id'  => 1,
            ':score'      => (int)$score,
            ':level'      => 1,
        ]);

        // NEW scoreboard table
        $stmt = $pdo->prepare(
            "INSERT INTO dice_scores (player_name, score, detail)
             VALUES (:player_name, :score, :detail)"
        );
        $stmt->execute([
            ':player_name' => $playerName,
            ':score'       => (int)$score,
            ':detail'      => $detail,
        ]);

        echo json_encode([
            'ok'         => true,
            'session_id' => $sessionId,
            'history_id' => $historyId,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'error'  => 'DB error',
            'detail' => $e->getMessage(),
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Roll the Dice – Neon</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pixelify+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg1:#020617;--bg2:#020617;--accent:#38bdf8;--accent2:#f97316;
  --danger:#f43f5e;--success:#22c55e;--text-main:#e5e7eb;--text-muted:#9ca3af;
  --glass-bg:rgba(15,23,42,0.92);--border-glass:rgba(31,41,55,0.9);
  --surface:#020617;--surface-soft:#020617;--btn-yellow:#facc15;
}

*{box-sizing:border-box;font-family:"Pixelify Sans",system-ui,sans-serif;}

body{
  margin:0;min-height:100vh;color:var(--text-main);
  background:
    radial-gradient(circle at 0 0,#0b1120, #020617 55%),
    radial-gradient(circle at 100% 100%,#1d4ed8,#020617 45%);
  background-attachment:fixed;
  display:flex;justify-content:center;align-items:flex-start;padding:20px 12px;
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
  animation:pulse-glow 4s ease-in-out infinite;
}
@keyframes pulse-glow{
  0%,100%{box-shadow:0 0 80px rgba(56,189,248,0.25) inset;}
  50%{box-shadow:0 0 130px rgba(248,113,113,0.35) inset;}
}

.shell{max-width:1100px;width:100%;}
.container{
  padding:16px 20px 20px;border-radius:20px;background:var(--glass-bg);
  border:1px solid var(--border-glass);
  box-shadow:0 18px 40px rgba(0,0,0,0.9);
  position:relative;overflow:hidden;
}
.container::before{
  content:"";position:absolute;inset:-40%;background:
    radial-gradient(circle at 0 0,rgba(56,189,248,0.1),transparent 60%),
    radial-gradient(circle at 100% 100%,rgba(248,113,113,0.15),transparent 55%);
  opacity:0.9;pointer-events:none;z-index:-1;
}

h1{
  margin:0 0 4px;font-size:1.7rem;letter-spacing:.14em;text-transform:uppercase;
  text-shadow:0 0 10px rgba(56,189,248,0.8),0 0 20px rgba(56,189,248,0.5);
}
.subtitle{
  font-size:.8rem;color:var(--text-muted);margin-bottom:8px;letter-spacing:.08em;
}
.status-line{
  font-size:.8rem;color:var(--text-muted);margin-bottom:12px;
}

.controls{
  display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;
}
.pill{
  display:inline-flex;align-items:center;gap:6px;padding:4px 10px;
  border-radius:999px;background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);font-size:.78rem;
}
.pill span{letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);}
.pill select{
  padding:2px 6px;border-radius:999px;border:1px solid rgba(55,65,81,0.9);
  background:#020617;color:var(--text-main);font-size:.78rem;
}

.dice-layout{
  display:grid;grid-template-columns:2.2fr 1.1fr;gap:10px;
}
@media(max-width:900px){.dice-layout{grid-template-columns:1fr;}}

.players-grid{
  display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;
}
@media(max-width:700px){.players-grid{grid-template-columns:repeat(2,minmax(0,1fr));}}
@media(max-width:480px){.players-grid{grid-template-columns:1fr;}}

.panel-box{
  border-radius:14px;padding:10px 10px 12px;background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);margin-bottom:10px;
}
.panel-title{
  font-size:.78rem;text-transform:uppercase;letter-spacing:.16em;
  color:var(--text-muted);margin:0 0 6px;
}

.player-card-outer{
  border-radius:14px;padding:1px;
  background:linear-gradient(135deg,#38bdf8,#a855f7,#f97316);
  box-shadow:0 0 18px rgba(56,189,248,0.6);
}
.player-card{
  border-radius:13px;padding:8px 8px 10px;
  background:radial-gradient(circle at 0 0,#020617,#020617);
  border:1px solid rgba(148,163,184,0.5);position:relative;overflow:hidden;
}
.player-card::after{
  content:"";position:absolute;inset:0;
  background:radial-gradient(circle at 30% -20%,rgba(248,250,252,0.08),transparent 60%);
  opacity:0.6;pointer-events:none;
}
.player-card h2{
  margin:0 0 4px;font-size:.8rem;letter-spacing:.1em;text-transform:uppercase;
}
.player-card.dead{filter:grayscale(.9);opacity:.55;}
.player-card.winner{
  box-shadow:0 0 18px rgba(34,197,94,0.9);
}
.guess-label{font-size:.8rem;margin-bottom:4px;}

.lives-row{display:flex;align-items:center;gap:6px;margin-bottom:4px;}
.lives-label{font-size:.75rem;color:var(--btn-yellow);}
.lives-bar{
  flex:1;height:4px;border-radius:999px;background:rgba(31,41,55,0.9);
  overflow:hidden;position:relative;
}
.lives-fill{
  position:absolute;left:0;top:0;bottom:0;width:100%;
  background:linear-gradient(90deg,#22c55e,#eab308,#f97316);
  transition:width .25s ease-out;
}

.guess-buttons{display:flex;flex-wrap:wrap;gap:3px;}
.guess-buttons button{
  border:none;border-radius:7px;padding:3px 0;font-size:.78rem;width:26px;
  background:#020617;color:#e5e7eb;border:1px solid rgba(55,65,81,0.9);
  cursor:pointer;transition:transform .14s,background .14s,box-shadow .14s;
}
.guess-buttons button:hover:not(:disabled){
  transform:translateY(-1px);background:#111827;
}
.guess-buttons button.selected{
  background:#facc15;color:#020617;box-shadow:0 0 12px rgba(250,204,21,0.8);
}
.guess-buttons button:disabled{opacity:.35;cursor:default;}

.dice-value{
  font-size:2rem;font-weight:800;color:var(--accent2);
  text-align:center;min-height:2.2rem;text-shadow:0 0 14px rgba(248,113,113,0.9);
}
.dice-value.roll-anim{animation:dice-pop .25s ease-out;}
@keyframes dice-pop{
  0%{transform:scale(.2) rotate(-20deg);opacity:0;}
  100%{transform:scale(1) rotate(0);opacity:1;}
}
.result-text{text-align:center;font-size:.85rem;margin-top:4px;}

.history{max-height:190px;overflow-y:auto;font-size:.78rem;padding-right:4px;}
.history-item{
  display:flex;justify-content:space-between;align-items:center;
  padding:3px 4px;border-radius:8px;background:rgba(15,23,42,0.95);
  border:1px solid rgba(55,65,81,0.8);margin-bottom:4px;
}
.history-score{color:var(--btn-yellow);font-weight:600;}

.btn{
  border:none;border-radius:999px;padding:7px 16px;font-size:.85rem;
  font-weight:600;cursor:pointer;display:inline-flex;align-items:center;
  gap:7px;background:var(--surface-soft);color:var(--text-main);
  border:1px solid rgba(55,65,81,0.9);
  text-transform:uppercase;letter-spacing:.08em;
  position:relative;overflow:hidden;
  transition:transform .14s,box-shadow .14s,border-color .14s;
}
.btn::before{
  content:"";position:absolute;top:-120%;left:-40%;width:40%;height:300%;
  background:linear-gradient(120deg,transparent,rgba(248,250,252,0.6),transparent);
  opacity:0;transform:translateX(-60%);
}
.btn:hover{
  transform:translateY(-1px) scale(1.02);
  box-shadow:0 0 18px rgba(56,189,248,0.4);
  border-color:rgba(56,189,248,0.8);
}
.btn:hover::before{
  opacity:1;animation:button-shine .7s ease-out;
}
@keyframes button-shine{
  0%{transform:translateX(-60%);}
  100%{transform:translateX(260%);}
}
.btn-primary{
  background:radial-gradient(circle at 0 0,#facc15,#f97316);
  color:#020617;
  box-shadow:0 0 18px rgba(248,181,0,0.7);
}

/* floating score label */
.dice-float-score{
  position:absolute;right:8px;top:4px;font-size:.75rem;font-weight:600;
  color:#facc15;text-shadow:0 0 8px rgba(250,204,21,0.9);
  animation:float-score .9s ease-out forwards;
}
@keyframes float-score{
  0%{transform:translateY(0);opacity:1;}
  100%{transform:translateY(-14px);opacity:0;}
}

/* name modal */
.name-modal-backdrop{
  position:fixed;inset:0;display:none;justify-content:center;align-items:center;
  background:rgba(15,23,42,0.9);z-index:50;
}
.name-modal{
  background:#020617;border-radius:12px;padding:16px 18px 18px;
  border:1px solid rgba(148,163,184,0.7);min-width:260px;text-align:center;
}
.name-modal label{
  display:block;margin-bottom:8px;font-size:.9rem;color:#e5e7eb;
}
.name-modal input{
  width:100%;padding:7px;border-radius:999px;border:1px solid rgba(55,65,81,0.9);
  background:#020617;color:#e5e7eb;font-size:.85rem;text-align:center;
}
.name-modal button{
  margin-top:10px;padding:6px 20px;border-radius:999px;border:none;
  cursor:pointer;background:linear-gradient(135deg,#38bdf8,#a855f7);color:#fff;
  font-size:.85rem;font-weight:600;
}

/* scoreboard panel */
.scoreboard{
  margin-top:8px;
}
.scoreboard-title{
  font-size:.78rem;text-transform:uppercase;letter-spacing:.16em;
  color:var(--text-muted);margin-bottom:4px;
}
.score-item{
  display:flex;justify-content:space-between;align-items:center;
  padding:3px 4px;border-radius:8px;background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);margin-bottom:4px;font-size:.78rem;
}
.score-item span.name{
  max-width:120px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
}
.score-item span.val{
  color:var(--btn-yellow);font-weight:600;
}
.small-text{font-size:.75rem;color:var(--text-muted);}
</style>
</head>
<body>
<div class="shell">
  <div class="container">
    <h1>Roll the Dice</h1>
    <div class="subtitle">Neon multiplayer. Correct guess +10, wrong +0. Highest score wins when you decide to stop.</div>
    <div class="status-line">
      Choose players and difficulty (recommended target). Names are asked once per session. Scores keep stacking until reset.
    </div>

    <div class="controls">
      <div class="pill">
        <span>Players</span>
        <select id="player-count">
          <option value="1" selected>1</option>
          <option value="2">2</option>
          <option value="3">3</option>
          <option value="4">4</option>
          <option value="5">5</option>
          <option value="6">6</option>
        </select>
      </div>
      <div class="pill">
        <span>Difficulty</span>
        <select id="difficulty-select">
          <option value="50">Easy (50)</option>
          <option value="75" selected>Medium (75)</option>
          <option value="100">Hard (100)</option>
        </select>
      </div>
      <div class="pill">
        <span>Target</span>
        <span id="target-label">75 pts (hint)</span>
      </div>
    </div>

    <div class="dice-layout">
      <div>
        <div class="players-grid" id="players-grid"></div>
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
          <button class="btn btn-primary" id="dice-roll-btn">🎲 Roll</button>
          <button class="btn" id="dice-reset-btn">Reset Session</button>
        </div>
      </div>
      <div>
        <div class="panel-box">
          <div class="panel-title">Round status</div>
          <div class="dice-value" id="dice-value">–</div>
          <div class="result-text" id="dice-result-text">Everyone picks a number, then roll.</div>
        </div>
        <div class="panel-box">
          <div class="panel-title">Recent rounds</div>
          <div class="history" id="dice-history"></div>
        </div>

        <div class="panel-box scoreboard">
          <div class="scoreboard-title">Top 5 scores 🏆</div>
          <div class="small-text">Last 5 highest scorers stored in database.</div>
          <div id="top5-list">
            <?php if (empty($topScores)): ?>
              <div class="small-text">No scores yet. Play a round!</div>
            <?php else: ?>
              <?php foreach ($topScores as $i => $row): ?>
                <div class="score-item">
                  <span><?php echo $i+1; ?>.</span>
                  <span class="name"><?php echo htmlspecialchars($row['player_name']); ?></span>
                  <span class="val"><?php echo (int)$row['score']; ?></span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- name modal -->
<div class="name-modal-backdrop" id="name-modal-backdrop">
  <div class="name-modal">
    <label id="name-modal-label">Enter name for Player 1</label>
    <input id="name-modal-input" type="text" placeholder="Player 1" />
    <button id="name-modal-ok">OK</button>
  </div>
</div>

<script>
// helper: save to PHP
async function saveScore(playerName, score, detail){
  try{
    await fetch(window.location.href,{
      method:"POST",
      headers:{"Content-Type":"application/json; charset=UTF-8"},
      body:JSON.stringify({name:playerName,score:score,detail:detail})
    });
    // refresh scoreboard after each save
    await refreshTop5();
  }catch(e){console.error("DB save error",e);}
}

// refresh scoreboard (simple reload of this page section)
async function refreshTop5(){
  try{
    const res = await fetch(window.location.href);
    const html = await res.text();
    const parser = new DOMParser();
    const doc = parser.parseFromString(html,"text/html");
    const newList = doc.getElementById('top5-list');
    if(newList){
      document.getElementById('top5-list').innerHTML = newList.innerHTML;
    }
  }catch(e){
    console.error("refresh top5 error",e);
  }
}

const playerCountSelect=document.getElementById('player-count');
const difficultySelect=document.getElementById('difficulty-select');
const targetLabel=document.getElementById('target-label');
const playersGrid=document.getElementById('players-grid');
const diceRollBtn=document.getElementById('dice-roll-btn');
const diceResetBtn=document.getElementById('dice-reset-btn');
const diceValueEl=document.getElementById('dice-value');
const diceResultText=document.getElementById('dice-result-text');
const diceHistoryBox=document.getElementById('dice-history');

const nameModalBackdrop=document.getElementById('name-modal-backdrop');
const nameModalLabel=document.getElementById('name-modal-label');
const nameModalInput=document.getElementById('name-modal-input');
const nameModalOk=document.getElementById('name-modal-ok');

let playerCount=1;
let names=["Player 1","Player 2","Player 3","Player 4","Player 5","Player 6"];
let scores=[0,0,0,0,0,0];
let guesses=[null,null,null,null,null,null];
let lives=[3,3,3,3,3,3];

let cards=[],guessLabels=[],livesLabels=[],lifeFills=[],guessButtons=[];

let nameIndexToAsk=0;

// difficulty text
function updateTargetLabel(){
  const v=parseInt(difficultySelect.value,10);
  targetLabel.textContent=v+" pts (hint)";
}
updateTargetLabel();
difficultySelect.addEventListener('change',updateTargetLabel);

// modal for names
function openNameModalFor(idx){
  nameIndexToAsk=idx;
  nameModalLabel.textContent="Enter name for Player "+(idx+1);
  nameModalInput.value="";
  nameModalInput.placeholder="Player "+(idx+1);
  nameModalBackdrop.style.display="flex";
  nameModalInput.focus();
}
function closeNameModal(){
  nameModalBackdrop.style.display="none";
}
function confirmName(){
  const value=nameModalInput.value.trim();
  const idx=nameIndexToAsk;
  names[idx]= value!=="" ? value : ("Player "+(idx+1));
  if(cards[idx]){
    cards[idx].title.textContent=names[idx]+" (Score: "+scores[idx]+")";
  }
  closeNameModal();
  for(let j=idx+1;j<playerCount;j++){
    if(names[j]==="Player "+(j+1)){
      openNameModalFor(j);
      return;
    }
  }
}
nameModalOk.addEventListener('click',confirmName);
nameModalInput.addEventListener('keydown',e=>{if(e.key==="Enter")confirmName();});

// build players UI
function buildPlayers(){
  playersGrid.innerHTML="";
  cards=[];guessLabels=[];livesLabels=[];lifeFills=[];guessButtons=[];
  for(let i=0;i<6;i++){
    const outer=document.createElement('div');
    outer.className="player-card-outer";
    const card=document.createElement('div');
    card.className="player-card";
    const title=document.createElement('h2');
    title.textContent=names[i]+" (Score: "+scores[i]+")";
    const gLabel=document.createElement('div');
    gLabel.className="guess-label";
    const livesRow=document.createElement('div');
    livesRow.className="lives-row";
    const lLabel=document.createElement('div');
    lLabel.className="lives-label";
    const bar=document.createElement('div');
    bar.className="lives-bar";
    const fill=document.createElement('div');
    fill.className="lives-fill";
    bar.appendChild(fill);
    livesRow.appendChild(lLabel);
    livesRow.appendChild(bar);
    const btnRow=document.createElement('div');
    btnRow.className="guess-buttons";
    const btnList=[];
    for(let n=1;n<=6;n++){
      const b=document.createElement('button');
      b.textContent=String(n);
      b.addEventListener('click',()=>{
        if(i>=playerCount || lives[i]<=0) return;
        guesses[i]=n;
        gLabel.textContent="Guess: "+n;
        btnList.forEach(x=>x.classList.remove('selected'));
        b.classList.add('selected');
      });
      btnRow.appendChild(b);
      btnList.push(b);
    }

    card.appendChild(title);
    card.appendChild(gLabel);
    card.appendChild(livesRow);
    card.appendChild(btnRow);
    outer.appendChild(card);
    playersGrid.appendChild(outer);

    cards.push({el:card,title:title});
    guessLabels.push(gLabel);
    livesLabels.push(lLabel);
    lifeFills.push(fill);
    guessButtons.push(btnList);
  }
  applyPlayerCount();
}

function applyPlayerCount(){
  for(let i=0;i<6;i++){
    const active=i<playerCount;
    guesses[i]=null;
    lives[i]=active?3:0;
    const c=cards[i];const g=guessLabels[i];const l=livesLabels[i];const f=lifeFills[i];
    if(active){
      c.el.classList.remove('dead','winner');
      c.title.textContent=names[i]+" (Score: "+scores[i]+")";
      g.textContent="Guess: -";
      l.textContent="Lives: 3";
      f.style.width="100%";
      guessButtons[i].forEach(b=>{b.disabled=false;b.classList.remove('selected');});
    }else{
      c.el.classList.remove('dead','winner');
      c.title.textContent=names[i]+" (unused)";
      g.textContent="(unused)";
      l.textContent="Lives: -";
      f.style.width="0%";
      guessButtons[i].forEach(b=>{b.disabled=true;b.classList.remove('selected');});
    }
  }
  diceValueEl.textContent="–";
  diceResultText.textContent="Everyone picks a number, then roll.";
}

buildPlayers();
openNameModalFor(0);

playerCountSelect.addEventListener('change',()=>{
  playerCount=parseInt(playerCountSelect.value,10);
  scores=[0,0,0,0,0,0];
  lives=[3,3,3,3,3,3];
  guesses=[null,null,null,null,null,null];
  diceHistoryBox.innerHTML="";
  buildPlayers();
  for(let i=0;i<playerCount;i++){
    names[i]="Player "+(i+1);
  }
  openNameModalFor(0);
});

function anyAlive(){
  for(let i=0;i<playerCount;i++){ if(lives[i]>0) return true; }
  return false;
}

diceRollBtn.addEventListener('click',async ()=>{
  if(!anyAlive()){
    diceResultText.textContent="No lives left. Reset session to play again.";
    return;
  }
  for(let i=0;i<playerCount;i++){
    if(lives[i]>0 && guesses[i]===null){
      alert("All alive players must pick a number.");
      return;
    }
  }

  const val=Math.floor(Math.random()*6)+1;
  diceValueEl.textContent=val;
  diceValueEl.classList.add('roll-anim');
  setTimeout(()=>diceValueEl.classList.remove('roll-anim'),260);

  let winnersIndices=[];
  for(let i=0;i<playerCount;i++){
    const c=cards[i].el;
    c.classList.remove('winner');
    if(lives[i]<=0) continue;
    if(guesses[i]===val){
      winnersIndices.push(i);
      lives[i]+=1;
      scores[i]+=10;
      livesLabels[i].textContent="Lives: "+lives[i];
      lifeFills[i].style.width=Math.min(lives[i],6)/6*100+"%";
      cards[i].title.textContent=names[i]+" (Score: "+scores[i]+")";
      c.classList.add('winner');
      spawnFloatScore(c,"+10");
      await saveScore(names[i],10,"Guess the Dice – correct");
    }else{
      lives[i]=Math.max(0,lives[i]-1);
      livesLabels[i].textContent="Lives: "+lives[i];
      lifeFills[i].style.width=Math.min(lives[i],6)/6*100+"%";
      if(lives[i]===0){
        c.classList.add('dead');
        guessButtons[i].forEach(b=>b.disabled=true);
        guessLabels[i].textContent="Guess: (out)";
      }
      await saveScore(names[i],0,"Guess the Dice – miss");
    }
  }

  let text;
  const hadWinner=winnersIndices.length>0;
  if(hadWinner){
    const nameList=winnersIndices.map(i=>names[i]).join(", ");
    text=nameList+" hit the roll!";
  }else{
    text="No one guessed correctly.";
  }
  diceResultText.textContent=text;
  addHistoryItem(val,text,hadWinner);

  guesses=[null,null,null,null,null,null];
  for(let i=0;i<playerCount;i++){
    if(lives[i]>0){
      guessLabels[i].textContent="Guess: -";
      guessButtons[i].forEach(b=>b.classList.remove('selected'));
    }
  }
});

diceResetBtn.addEventListener('click',()=>{
  scores=[0,0,0,0,0,0];
  lives=[3,3,3,3,3,3];
  guesses=[null,null,null,null,null,null];
  diceHistoryBox.innerHTML="";
  buildPlayers();
  for(let i=0;i<playerCount;i++){
    names[i]="Player "+(i+1);
  }
  openNameModalFor(0);
});

// history row
function addHistoryItem(val,text,hadWinner){
  const item=document.createElement('div');
  item.className="history-item";
  const left=document.createElement('div');
  left.textContent="🎲 "+val+" – "+text;
  const right=document.createElement('div');
  right.className="history-score";
  right.textContent=hadWinner?"+10":"+0";
  item.appendChild(left);item.appendChild(right);
  diceHistoryBox.insertBefore(item,diceHistoryBox.firstChild);
  while(diceHistoryBox.children.length>8){
    diceHistoryBox.removeChild(diceHistoryBox.lastChild);
  }
}

function spawnFloatScore(cardEl,text){
  const span=document.createElement('div');
  span.className="dice-float-score";
  span.textContent=text;
  cardEl.appendChild(span);
  setTimeout(()=>span.remove(),900);
}
</script>
</body>
</html>
