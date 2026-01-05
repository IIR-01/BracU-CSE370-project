<?php
// ===== DB CONFIG =====
$DB_HOST = "localhost";
$DB_USER = "root";   // change
$DB_PASS = "";       // change
$DB_NAME = "neon_arena";

session_start();

// ===== AJAX API (SAVE / LOAD) =====
if (isset($_GET['api']) || isset($_POST['api'])) {
    header('Content-Type: application/json');
    $action = $_GET['api'] ?? $_POST['api'] ?? '';

    if ($action === 'save_score') {
        $name  = trim($_POST['player'] ?? '');
        $score = (int)($_POST['score'] ?? 0);
        $diff  = trim($_POST['difficulty'] ?? 'normal');

        if ($name === '' || $score < 0) {
            echo json_encode(['ok' => false, 'error' => 'Invalid data']); exit;
        }

        try {
            $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
            if ($db->connect_error) throw new Exception($db->connect_error);
            $db->set_charset("utf8mb4");

            $stmt = $db->prepare("INSERT INTO arena_scores (player_name, score) VALUES (?, ?)");
            if (!$stmt) throw new Exception($db->error);
            $stmt->bind_param("si", $name, $score);
            $stmt->execute();
            $stmt->close();
            $db->close();

            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'top_scores') {
        try {
            $db = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
            if ($db->connect_error) throw new Exception($db->connect_error);
            $db->set_charset("utf8mb4");

            $sql = "SELECT player_name, score 
                    FROM arena_scores 
                    ORDER BY score DESC, created_at ASC 
                    LIMIT 5";
            $res = $db->query($sql);
            $rows = [];
            if ($res) while ($row = $res->fetch_assoc()) $rows[] = $row;
            $db->close();

            echo json_encode(['ok' => true, 'scores' => $rows]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action']); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Neon Grid Arena</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;background:#020617;color:#e5e7eb;font-family:"Courier New",monospace;overflow:hidden}
body::before{
  content:"";position:fixed;inset:0;z-index:-2;
  background:
    linear-gradient(#0ff2 1px,transparent 1px),
    linear-gradient(90deg,#0ff2 1px,transparent 1px);
  background-size:40px 40px;
  transform-origin:center;
  transform:perspective(600px) rotateX(70deg) translateY(25%);
  opacity:.35;filter:blur(.2px);
}
body::after{
  content:"";position:fixed;inset:0;z-index:-3;
  background:radial-gradient(circle at top,#0ff5 0%,#000 60%);
  opacity:.8;
}
.neon-blue{color:#4fd1ff;text-shadow:0 0 4px #4fd1ff,0 0 8px #00e5ff,0 0 16px #00e5ff}
.neon-orange{color:#fb923c;text-shadow:0 0 4px #fb923c,0 0 8px #f97316,0 0 16px #f97316}
.neon-cyan{color:#00f6ff;text-shadow:0 0 4px #00f6ff,0 0 8px #00b7ff,0 0 16px #00b7ff}
.frame{position:relative;width:100%;height:100%;padding:16px;display:flex;flex-direction:column;align-items:center;justify-content:space-between}
header{text-align:center;margin-bottom:4px}
header h1{font-size:1.4rem;letter-spacing:.25em;text-transform:uppercase}
header h1 span{display:inline-block;transform:skewX(-10deg)}
header p{margin-top:4px;font-size:.8rem;opacity:.9}
.top-bar{display:flex;width:100%;max-width:980px;align-items:flex-start;justify-content:space-between;margin:4px auto 6px;padding:6px 12px;border-radius:10px;border:1px solid #1f2937;background:linear-gradient(90deg,#020617dd,#020617aa);box-shadow:0 0 12px #0ff3,inset 0 0 18px #0ff1}
.stats{font-size:.7rem;display:flex;flex-direction:column;gap:2px}
.controls{text-align:right;font-size:.65rem;line-height:1.4}
.player-name{font-size:.7rem;margin-top:3px}
.player-name input{padding:2px 6px;border-radius:999px;border:1px solid #0ff9;background:#020617;color:#e5e7eb;outline:none}
.player-name input:focus{box-shadow:0 0 6px #0ff}
.diff-select{margin-top:2px;font-size:.7rem}
.diff-select select{font-size:.7rem;padding:1px 4px;margin-left:4px;background:#020617;color:#e5e7eb;border-radius:999px;border:1px solid #0ff9}
.scoreboard{font-size:.7rem;margin-left:16px}
.scoreboard h3{font-size:.75rem;margin-bottom:2px}
.scoreboard ul{margin:0;padding-left:16px}
.scoreboard li{margin-bottom:2px}
.game-shell{position:relative;flex:1;display:flex;align-items:center;justify-content:center;width:100%}
.game-frame{position:relative;border-radius:16px;padding:10px;background:radial-gradient(circle at top,#0ff3 0%,#020617 45%,#000 100%);border:2px solid #0ff4;box-shadow:0 0 24px #0ff6,0 0 60px #0ff2}
canvas#game{display:block;background:radial-gradient(circle at center,#020617 0%,#000 60%);box-shadow:0 0 40px #00e5ff66,inset 0 0 32px #020617;border-radius:10px;border:1px solid #0ff2}
.bottom-bar{margin-top:6px;width:100%;max-width:980px;display:flex;justify-content:space-between;align-items:center;font-size:.7rem;color:#9ca3af}
.bottom-bar span{opacity:.9}
.pulse-dot{width:6px;height:6px;border-radius:50%;background:#0ff;box-shadow:0 0 10px #0ff,0 0 20px #0ff;margin-right:4px;display:inline-block;animation:pulse 1.2s infinite alternate}
@keyframes pulse{from{opacity:.4;transform:scale(1)}to{opacity:1;transform:scale(1.35)}}
.overlay{position:absolute;inset:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;background:radial-gradient(circle at center,#020617f7,#000a);border-radius:10px;text-align:center;backdrop-filter:blur(2px);color:#e5e7eb}
.overlay h2{font-size:1.2rem;margin-bottom:8px}
.overlay p{font-size:.8rem;margin-bottom:10px;opacity:.9}
.overlay small{font-size:.65rem;opacity:.85}
.btn{cursor:pointer;padding:6px 14px;margin:4px;border-radius:999px;border:1px solid #0ff9;background:radial-gradient(circle at top,#0ff3,#020617);color:#e5f2ff;font-size:.8rem;text-transform:uppercase;letter-spacing:.09em;text-shadow:0 0 4px #0ff,0 0 12px #00f6ff;box-shadow:0 0 12px #0ff7,inset 0 0 12px #0ff2;transition:transform .15s,box-shadow .15s,background .15s}
.btn:hover{transform:translateY(-1px) scale(1.03);box-shadow:0 0 16px #0ffb,inset 0 0 16px #0ff4;background:radial-gradient(circle at top,#0ff6,#020617)}
.btn:active{transform:translateY(0) scale(.99);box-shadow:0 0 8px #0ff9,inset 0 0 20px #0ff5}
@media(max-width:720px){
  header h1{font-size:1.1rem}
  .top-bar{flex-direction:column;align-items:stretch;gap:4px}
  .controls{text-align:left}
}
</style>
</head>
<body>
<div class="frame">
  <header>
    <h1 class="neon-cyan"><span>NEON</span> <span>GRID ARENA</span></h1>
    <p>Top‑down retro shooter • power‑ups • friend drone • high‑score grid</p>
  </header>

  <div class="top-bar">
    <div class="stats">
      <div>Health: <span id="hudHealth" class="neon-blue">100</span></div>
      <div>Energy: <span id="hudEnergy" class="neon-blue">100</span></div>
      <div>Score: <span id="hudScore" class="neon-blue">0</span></div>
      <div>Wave: <span id="hudWave" class="neon-blue">1</span></div>
      <div class="player-name">
        Name: <input type="text" id="playerName" maxlength="16" value="USER">
      </div>
      <div class="diff-select">
        Difficulty:
        <select id="difficulty">
          <option value="easy">Easy</option>
          <option value="normal" selected>Normal</option>
          <option value="hard">Hard</option>
        </select>
      </div>
    </div>
    <div class="controls">
      <div>Move: <span class="neon-blue">W A S D</span> or arrows</div>
      <div>Fire: <span class="neon-orange">SPACE = toggle auto‑fire</span></div>
      <div>Power‑ups: energy, smart bomb, circular burst, missile, friend drone.</div>
      <button class="btn" id="startBtn">Start / Restart</button>
    </div>
    <div class="scoreboard">
      <h3>Top 5 Programs</h3>
      <ul id="scoreList">
        <li>Loading…</li>
      </ul>
    </div>
  </div>

  <div class="game-shell">
    <div class="game-frame">
      <canvas id="game" width="720" height="420"></canvas>

      <div id="startOverlay" class="overlay">
        <h2 class="neon-cyan">ENTER THE ARENA</h2>
        <p>Collect neon power‑ups: clear the grid, unleash circular fire, launch missiles, and call in a drone ally. Survive as waves escalate.</p>
        <button class="btn" id="startBtn2">Start Run</button>
        <small>Wave milestones trigger full clears; power‑ups spawn randomly in the grid.</small>
      </div>

      <div id="gameOverOverlay" class="overlay" style="display:none;">
        <h2 class="neon-cyan">YOU DEREZZED</h2>
        <p id="finalText">Final score: 0</p>
        <button class="btn" id="restartBtn">Run Grid Again</button>
        <small>Your score is saved; climb into the top‑5 glowing list.</small>
      </div>
    </div>
  </div>

  <div class="bottom-bar">
    <span><span class="pulse-dot"></span>Neon arena shooter • power‑ups & ally • PHP/MySQL leaderboard.</span>
    <span>All in one file, arcade ready.</span>
  </div>
</div>

<script>
(function(){
  const canvas = document.getElementById("game");
  const ctx    = canvas.getContext("2d");

  const hudHealth = document.getElementById("hudHealth");
  const hudEnergy = document.getElementById("hudEnergy");
  const hudScore  = document.getElementById("hudScore");
  const hudWave   = document.getElementById("hudWave");
  const playerName= document.getElementById("playerName");
  const diffSelect= document.getElementById("difficulty");
  const scoreList = document.getElementById("scoreList");

  const startOverlay  = document.getElementById("startOverlay");
  const gameOverOverlay = document.getElementById("gameOverOverlay");
  const startBtn      = document.getElementById("startBtn");
  const startBtn2     = document.getElementById("startBtn2");
  const restartBtn    = document.getElementById("restartBtn");
  const finalText     = document.getElementById("finalText");

  const W = canvas.width;
  const H = canvas.height;
  const CELL = 18;
  const GRID_COLS = Math.floor(W / CELL);
  const GRID_ROWS = Math.floor(H / CELL);

  const keys = {};
  let shootToggle = false;

  window.addEventListener("keydown",e=>{
    if (e.key === " ") {
      e.preventDefault();
      shootToggle = !shootToggle;
      return;
    }
    keys[e.key] = true;
  });
  window.addEventListener("keyup",e=>{keys[e.key]=false;});

  const player = {x:W/2,y:H/2,r:8,speed:210,hp:100,energy:100,fireCooldown:0};
  let bullets = [];      // normal bullets
  let missiles = [];     // strong shots
  let enemies = [];
  let powerUps = [];     // {x,y,type}
  let friend = null;     // drone

  let spawnTimer = 0;
  let spawnRate  = 2;
  let enemySpeedBase = 60;
  let score = 0;
  let wave  = 1;
  let running = false;
  let lastTime = 0;

  startBtn .addEventListener("click", startGame);
  startBtn2.addEventListener("click", startGame);
  restartBtn.addEventListener("click", startGame);

  function loadScores(){
    fetch("?api=top_scores&_=" + Date.now())
      .then(r=>r.json())
      .then(data=>{
        scoreList.innerHTML = "";
        if (!data.ok) { scoreList.innerHTML = "<li>Error loading scores</li>"; return; }
        if (!data.scores.length) { scoreList.innerHTML = "<li>No scores yet</li>"; return; }
        data.scores.forEach(row=>{
          const li = document.createElement("li");
          li.textContent = row.player_name + " – " + row.score;
          scoreList.appendChild(li);
        });
      })
      .catch(()=>{scoreList.innerHTML="<li>Error loading scores</li>";});
  }
  loadScores();

  function applyDifficulty(){
    const d = diffSelect.value;
    if (d === "easy"){
      spawnRate = 2.5;
      enemySpeedBase = 45;
      player.hp = 130;
    } else if (d === "hard"){
      spawnRate = 1.2;
      enemySpeedBase = 80;
      player.hp = 80;
    } else {
      spawnRate = 2.0;
      enemySpeedBase = 60;
      player.hp = 100;
    }
  }

  function startGame(){
    running = true;
    score = 0;
    wave  = 1;
    player.x = W/2;
    player.y = H/2;
    player.energy = 100;
    player.fireCooldown = 0;
    bullets = [];
    missiles = [];
    enemies = [];
    powerUps = [];
    friend = null;
    spawnTimer = 0;
    shootToggle = false;
    applyDifficulty();
    startOverlay.style.display = "none";
    gameOverOverlay.style.display = "none";
    lastTime = performance.now();
    requestAnimationFrame(loop);
  }

  function loop(ts){
    if (!running) return;
    const dt = (ts - lastTime) / 1000;
    lastTime = ts;
    update(dt);
    render();
    requestAnimationFrame(loop);
  }

  function update(dt){
    // movement
    let dx = 0, dy = 0;
    if (keys["ArrowUp"] || keys["w"]) dy -= 1;
    if (keys["ArrowDown"] || keys["s"]) dy += 1;
    if (keys["ArrowLeft"] || keys["a"]) dx -= 1;
    if (keys["ArrowRight"] || keys["d"]) dx += 1;
    const len = Math.hypot(dx,dy) || 1;
    dx /= len; dy /= len;
    player.x += dx * player.speed * dt;
    player.y += dy * player.speed * dt;
    player.x = Math.max(player.r, Math.min(W - player.r, player.x));
    player.y = Math.max(player.r, Math.min(H - player.r, player.y));

    // auto‑fire (normal bullets)
    player.fireCooldown -= dt;
    if (shootToggle && player.fireCooldown <= 0 && player.energy > 0){
      fireBullet();
      player.fireCooldown = 0.15;
      player.energy = Math.max(0, player.energy - 1);
    }
    player.energy = Math.min(100, player.energy + dt * 8);

    // friend drone orbit + shooting
    if (friend){
      friend.angle += dt * 1.5;
      friend.x = player.x + Math.cos(friend.angle) * 40;
      friend.y = player.y + Math.sin(friend.angle) * 40;
      friend.cooldown -= dt;
      if (friend.cooldown <= 0){
        fireFriendShot();
        friend.cooldown = 0.4;
      }
    }

    // bullets
    bullets.forEach(b=>{
      b.x += b.vx * dt;
      b.y += b.vy * dt;
      b.life -= dt;
    });
    bullets = bullets.filter(b=>b.life>0 && b.x>=0 && b.x<=W && b.y>=0 && b.y<=H);

    // missiles
    missiles.forEach(m=>{
      m.x += m.vx * dt;
      m.y += m.vy * dt;
      m.life -= dt;
    });
    missiles = missiles.filter(m=>m.life>0 && m.x>=0 && m.x<=W && m.y>=0 && m.y<=H);

    // spawn enemies & power‑ups
    spawnTimer -= dt;
    if (spawnTimer <= 0){
      spawnWave();
      spawnTimer = spawnRate;
      spawnRate = Math.max(0.6, spawnRate * 0.96);
      wave++;

      // level‑up wave clear every 5th wave
      if (wave % 5 === 0){
        clearEnemies();
        score += 200;
        spawnRandomPowerUp(); // reward
      }
    }

    // enemies
    enemies.forEach(e=>{
      const dx = player.x - e.x;
      const dy = player.y - e.y;
      const d  = Math.hypot(dx,dy) || 1;
      const sx = dx / d, sy = dy / d;
      e.x += sx * e.speed * dt;
      e.y += sy * e.speed * dt;

      // hit player
      if (Math.hypot(player.x - e.x, player.y - e.y) < player.r + e.r){
        player.hp -= 20 * dt;
        e.hitTimer = 0.2;
      }

      // hit by bullets
      bullets.forEach(b=>{
        if (!b.dead && Math.hypot(b.x - e.x, b.y - e.y) < e.r + 3){
          b.dead = true;
          e.hp  -= 40;
          score += 50;
        }
      });

      // hit by missiles (bigger damage + splash)
      missiles.forEach(m=>{
        if (!m.dead && Math.hypot(m.x - e.x, m.y - e.y) < e.r + 6){
          m.dead = true;
          e.hp  -= 100;
          score += 80;
          enemies.forEach(e2=>{
            if (Math.hypot(m.x - e2.x, m.y - e2.y) < 40){
              e2.hp -= 40;
            }
          });
        }
      });

      e.hitTimer = Math.max(0, e.hitTimer - dt);
    });
    enemies = enemies.filter(e=>e.hp>0);
    bullets = bullets.filter(b=>!b.dead);
    missiles = missiles.filter(m=>!m.dead);

    // power‑up pickup
    powerUps.forEach(p=>{
      if (Math.hypot(player.x - p.x, player.y - p.y) < player.r + 6){
        applyPowerUp(p.type);
        p.collected = true;
      }
    });
    powerUps = powerUps.filter(p=>!p.collected);

    if (player.hp <= 0){
      running = false;
      gameOver();
    }

    hudHealth.textContent = Math.max(0, Math.floor(player.hp));
    hudEnergy.textContent = Math.floor(player.energy);
    hudScore.textContent  = Math.floor(score);
    hudWave.textContent   = wave;
  }

  function fireBullet(){
    let vx=0,vy=0;
    if (keys["ArrowRight"] || keys["d"]) vx = 1;
    else if (keys["ArrowLeft"] || keys["a"]) vx = -1;
    if (keys["ArrowDown"] || keys["s"]) vy = 1;
    else if (keys["ArrowUp"] || keys["w"]) vy = -1;
    if (vx===0 && vy===0){vx=1;vy=0;}
    const len = Math.hypot(vx,vy) || 1;
    vx /= len; vy /= len;
    bullets.push({x:player.x,y:player.y,vx:vx*480,vy:vy*480,life:0.8,dead:false});
  }

  function fireFriendShot(){
    // friend fires toward nearest enemy
    if (!friend) return;
    let target=null,minD=Infinity;
    enemies.forEach(e=>{
      const d=Math.hypot(e.x-friend.x,e.y-friend.y);
      if(d<minD){minD=d;target=e;}
    });
    if(!target) return;
    const dx=target.x-friend.x,dy=target.y-friend.y;
    const len=Math.hypot(dx,dy)||1;
    const vx=dx/len,vy=dy/len;
    bullets.push({x:friend.x,y:friend.y,vx:vx*520,vy:vy*520,life:0.7,dead:false});
  }

  function spawnWave(){
    const count = 2 + Math.floor(wave * 0.7);
    for (let i=0;i<count;i++){
      const side = Math.floor(Math.random()*4);
      let x,y;
      if (side===0){x=Math.random()*W;y=-10;}
      else if (side===1){x=Math.random()*W;y=H+10;}
      else if (side===2){x=-10;y=Math.random()*H;}
      else {x=W+10;y=Math.random()*H;}
      enemies.push({
        x,y,
        r:6,
        hp:60,
        speed:enemySpeedBase + wave*4,
        hitTimer:0
      });
    }
    // small chance to spawn a power‑up each wave
    if (Math.random() < 0.35){
      spawnRandomPowerUp();
    }
  }

  function spawnRandomPowerUp(){
    const types = ["energy","bomb","circle","missile","friend"];
    const type = types[Math.floor(Math.random()*types.length)];
    powerUps.push({
      x: Math.random()*W*0.8 + W*0.1,
      y: Math.random()*H*0.8 + H*0.1,
      type,
      collected:false
    });
  }

  function clearEnemies(){
    enemies = [];
  }

  function applyPowerUp(type){
    if (type === "energy"){
      player.energy = 100;
      player.hp = Math.min(player.hp + 30, 140);
      score += 50;
    } else if (type === "bomb"){
      clearEnemies();
      score += 200;
    } else if (type === "circle"){
      // circular fire burst
      const bulletsCount = 16;
      for (let i=0;i<bulletsCount;i++){
        const angle = (Math.PI*2*i)/bulletsCount;
        bullets.push({
          x:player.x,y:player.y,
          vx:Math.cos(angle)*420,
          vy:Math.sin(angle)*420,
          life:0.9,
          dead:false
        });
      }
      score += 100;
    } else if (type === "missile"){
      // single strong missile in facing dir
      let vx=0,vy=0;
      if (keys["ArrowRight"] || keys["d"]) vx = 1;
      else if (keys["ArrowLeft"] || keys["a"]) vx = -1;
      if (keys["ArrowDown"] || keys["s"]) vy = 1;
      else if (keys["ArrowUp"] || keys["w"]) vy = -1;
      if (vx===0 && vy===0){vx=1;vy=0;}
      const len=Math.hypot(vx,vy)||1;
      vx/=len;vy/=len;
      missiles.push({
        x:player.x,y:player.y,
        vx:vx*300,vy:vy*300,
        life:1.2,dead:false
      });
    } else if (type === "friend"){
      if (!friend){
        friend = {x:player.x+40,y:player.y,angle:0,cooldown:0.4};
      } else {
        score += 80;
      }
    }
  }

  function gameOver(){
    const finalScore = Math.floor(score);
    finalText.textContent = "Final score: " + finalScore + " • Wave " + wave;
    gameOverOverlay.style.display = "flex";
    saveScore(finalScore);
  }

  function render(){
    ctx.fillStyle = "#020617";
    ctx.fillRect(0,0,W,H);

    ctx.strokeStyle = "#0f172a";
    ctx.lineWidth   = 1;
    ctx.globalAlpha = 0.4;
    for (let x=0;x<=GRID_COLS;x++){
      ctx.beginPath();
      ctx.moveTo(x*CELL,0);
      ctx.lineTo(x*CELL,H);
      ctx.stroke();
    }
    for (let y=0;y<=GRID_ROWS;y++){
      ctx.beginPath();
      ctx.moveTo(0,y*CELL);
      ctx.lineTo(W,y*CELL);
      ctx.stroke();
    }
    ctx.globalAlpha = 1;

    // player
    ctx.save();
    ctx.shadowBlur  = 18;
    ctx.shadowColor = "#22d3ee";
    ctx.fillStyle   = "#22d3ee";
    ctx.beginPath();
    ctx.arc(player.x,player.y,player.r,0,Math.PI*2);
    ctx.fill();
    ctx.restore();

    // friend drone
    if (friend){
      ctx.save();
      ctx.shadowBlur  = 14;
      ctx.shadowColor = "#a855f7";
      ctx.fillStyle   = "#a855f7";
      ctx.beginPath();
      ctx.arc(friend.x,friend.y,5,0,Math.PI*2);
      ctx.fill();
      ctx.restore();
    }

    // bullets
    bullets.forEach(b=>{
      ctx.save();
      ctx.shadowBlur  = 10;
      ctx.shadowColor = "#eab308";
      ctx.fillStyle   = "#facc15";
      ctx.beginPath();
      ctx.arc(b.x,b.y,3,0,Math.PI*2);
      ctx.fill();
      ctx.restore();
    });

    // missiles
    missiles.forEach(m=>{
      ctx.save();
      ctx.shadowBlur  = 12;
      ctx.shadowColor = "#f97316";
      ctx.fillStyle   = "#fed7aa";
      ctx.beginPath();
      ctx.arc(m.x,m.y,4,0,Math.PI*2);
      ctx.fill();
      ctx.restore();
    });

    // enemies
    enemies.forEach(e=>{
      const base = e.hitTimer>0 ? "#f97316" : "#fb923c";
      ctx.save();
      ctx.shadowBlur  = 14;
      ctx.shadowColor = base;
      ctx.fillStyle   = base;
      ctx.beginPath();
      ctx.arc(e.x,e.y,e.r,0,Math.PI*2);
      ctx.fill();
      ctx.restore();
    });

    // power‑ups
    powerUps.forEach(p=>{
      if (p.type==="energy")      ctx.fillStyle="#22c55e";
      else if (p.type==="bomb")   ctx.fillStyle="#f97316";
      else if (p.type==="circle") ctx.fillStyle="#0ea5e9";
      else if (p.type==="missile")ctx.fillStyle="#e11d48";
      else if (p.type==="friend") ctx.fillStyle="#a855f7";
      ctx.save();
      ctx.shadowBlur=10;ctx.shadowColor=ctx.fillStyle;
      ctx.beginPath();
      ctx.arc(p.x,p.y,6,0,Math.PI*2);
      ctx.fill();
      ctx.restore();
    });
  }

  function saveScore(finalScore){
    const name = playerName.value.trim() || "USER";
    const diff = diffSelect.value;
    fetch("",{
      method:"POST",
      headers:{"Content-Type":"application/x-www-form-urlencoded"},
      body:new URLSearchParams({
        api:"save_score",
        player:name,
        score:String(finalScore),
        difficulty:diff
      })
    }).then(r=>r.json()).then(data=>{
      if (!data.ok) console.error("Score save failed", data.error);
      loadScores();
    }).catch(console.error);
  }

  // initial static frame
  render();
})();
</script>
</body>
</html>

