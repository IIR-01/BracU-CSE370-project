<?php
// ---------- PHP BACKEND (MySQL gamehub) ----------
$DB_HOST = 'localhost';
$DB_NAME = 'gamehub';   // must match your actual DB name
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

// --- AJAX: get scoreboard for a difficulty ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['scores'])) {
    header('Content-Type: application/json; charset=utf-8');

    $difficulty = $_GET['difficulty'] ?? 'easy';
    if (!in_array($difficulty, ['easy','medium','hard'], true)) {
        $difficulty = 'easy';
    }

    try {
        $pdo = get_pdo();
        $stmt = $pdo->prepare(
            "SELECT player_name, score, distance, coins, achievements, created_at
             FROM neon_racer_scores
             WHERE difficulty = :diff
             ORDER BY score DESC, distance DESC, coins DESC, created_at ASC
             LIMIT 10"
        );
        $stmt->execute([':diff' => $difficulty]);
        $rows = $stmt->fetchAll();

        echo json_encode(['ok' => true, 'rows' => $rows]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// --- AJAX: save one run ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $playerName   = trim($data['name'] ?? '');
    $score        = $data['score'] ?? null;
    $detail       = trim($data['detail'] ?? '');
    $difficulty   = $data['difficulty'] ?? 'easy';
    $laneCount    = (int)($data['lane_count'] ?? 3);
    $distance     = (int)($data['distance'] ?? 0);
    $coins        = (int)($data['coins'] ?? 0);
    $achievements = trim($data['achievements'] ?? '');

    if ($playerName === '' || !is_numeric($score)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    if (!in_array($difficulty, ['easy','medium','hard'], true)) {
        $difficulty = 'easy';
    }

    try {
        $pdo = get_pdo();

        // dedicated scoreboard table
        $stmt = $pdo->prepare(
            "INSERT INTO neon_racer_scores
             (player_name, difficulty, lane_count, score, distance, coins, achievements)
             VALUES (:name, :difficulty, :lanes, :score, :distance, :coins, :achievements)"
        );
        $stmt->execute([
            ':name'        => $playerName,
            ':difficulty'  => $difficulty,
            ':lanes'       => $laneCount,
            ':score'       => (int)$score,
            ':distance'    => $distance,
            ':coins'       => $coins,
            ':achievements'=> $achievements,
        ]);

        // keep compatibility with global game schema
        $pdo->exec("INSERT IGNORE INTO themes (theme_id, theme_name, primary_color)
                    VALUES (1, 'Neon', '#38bdf8')");

        $pdo->exec("INSERT IGNORE INTO users (user_id, email, password)
                    VALUES (1, 'race@example.com', 'dummy')");

        $userId    = 1;
        $gameTitle = 'Neon Street Racer';

        // ensure game row exists
        $stmt = $pdo->prepare("SELECT game_id FROM games WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $gameTitle]);
        $row = $stmt->fetch();

        if ($row) {
            $gameId = (int)$row['game_id'];
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO games (name, description, category, max_players, min_players, is_active)
                 VALUES (:name, :desc, 'Racing', 1, 1, 1)"
            );
            $stmt->execute([
                ':name' => $gameTitle,
                ':desc' => 'Neon street racer with 3/6/10 lanes, difficulties and scoreboard',
            ]);
            $gameId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO single_games (session_id, difficulty, bot_id)
                 VALUES (:session_id, :diff, :bot_id)"
            );
            // will attach real session_id after session creation; here kept minimal for compatibility
            $stmt->execute([
                ':session_id' => 1,
                ':diff'       => 'easy',
                ':bot_id'     => null,
            ]);
        }

        // create session
        $stmt = $pdo->prepare(
            "INSERT INTO game_sessions (game_id, host_player_id, status, max_players, current_players)
             VALUES (:game_id, :host_player_id, 'completed', 1, 1)"
        );
        $stmt->execute([
            ':game_id'        => $gameId,
            ':host_player_id' => $userId,
        ]);
        $sessionId = (int)$pdo->lastInsertId();

        // game history
        $stmt = $pdo->prepare(
            "INSERT INTO game_history (game_id, session_id, player_id, result, score)
             VALUES (:game_id, :session_id, :player_id, :result, :score)"
        );
        $stmt->execute([
            ':game_id'   => $gameId,
            ':session_id'=> $sessionId,
            ':player_id' => $userId,
            ':result'    => $detail,
            ':score'     => (int)$score,
        ]);
        $historyId = (int)$pdo->lastInsertId();

        // leaderboards
        $stmt = $pdo->prepare(
            "INSERT INTO leaderboards (game_id, player_id, score, level_reached)
             VALUES (:game_id, :player_id, :score, :level)"
        );
        $stmt->execute([
            ':game_id'   => $gameId,
            ':player_id' => $userId,
            ':score'     => (int)$score,
            ':level'     => 1,
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
<title>Neon Street Racer – GameHub</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pixelify+Sans:wght@400;600&display=swap" rel="stylesheet">
<style>
:root {
  --bg:#020617;
  --bg2:#020617;
  --accent:#38bdf8;
  --accent2:#f97316;
  --danger:#f43f5e;
  --success:#22c55e;
  --text-main:#e5e7eb;
  --text-muted:#9ca3af;
  --glass:#020617f0;
  --road:#020617;
  --lane:#1f2937;
  --line:#e5e7eb;
}

* {
  box-sizing:border-box;
  font-family:"Pixelify Sans",system-ui,sans-serif;
}

body {
  margin:0;
  min-height:100vh;
  color:var(--text-main);
  background:
    radial-gradient(circle at 0 0,#0b1120,#020617 55%),
    radial-gradient(circle at 100% 100%,#1d4ed8,#020617 45%);
  background-attachment:fixed;
  display:flex;
  justify-content:center;
  align-items:flex-start;
  padding:18px 10px;
  overflow:hidden;
  position:relative;
}
body::before{
  content:"";
  position:fixed;
  inset:0;
  pointer-events:none;
  background:repeating-linear-gradient(
    to bottom,
    rgba(15,23,42,0.5) 0px,
    rgba(15,23,42,0.5) 1px,
    transparent 2px,
    transparent 3px
  );
  mix-blend-mode:soft-light;
  opacity:0.5;
}
body::after{
  content:"";
  position:fixed;
  inset:0;
  pointer-events:none;
  box-shadow:0 0 80px rgba(56,189,248,0.25) inset;
  animation:pulseGlow 4s ease-in-out infinite;
}
@keyframes pulseGlow {
  0%,100% { box-shadow:0 0 80px rgba(56,189,248,0.25) inset; }
  50%     { box-shadow:0 0 130px rgba(248,113,113,0.3) inset; }
}

.shell{max-width:1120px;width:100%;}
.container{
  padding:14px 16px 18px;
  border-radius:18px;
  background:var(--glass);
  border:1px solid rgba(31,41,55,0.9);
  box-shadow:0 18px 40px rgba(0,0,0,0.9);
  position:relative;
  overflow:hidden;
}
.container::before{
  content:"";
  position:absolute;
  inset:-40%;
  pointer-events:none;
  background:
    radial-gradient(circle at 0 0,rgba(56,189,248,0.2),transparent 60%),
    radial-gradient(circle at 100% 100%,rgba(248,113,113,0.25),transparent 55%);
  opacity:0.9;
  z-index:-1;
}

.header{
  display:flex;
  justify-content:space-between;
  align-items:flex-start;
  gap:10px;
}
h1{
  margin:0;
  font-size:1.6rem;
  letter-spacing:.16em;
  text-transform:uppercase;
  text-shadow:0 0 10px rgba(56,189,248,0.9),0 0 22px rgba(56,189,248,0.6);
}
.subtitle{
  margin-top:4px;
  font-size:.8rem;
  color:var(--text-muted);
  letter-spacing:.08em;
}
.badge-row{
  display:flex;
  align-items:center;
  gap:8px;
  margin-top:8px;
  font-size:.75rem;
}
.badge{
  padding:3px 10px;
  border-radius:999px;
  background:rgba(22,163,74,0.1);
  border:1px solid rgba(22,163,74,0.7);
  text-transform:uppercase;
  letter-spacing:.1em;
  color:#bbf7d0;
  display:inline-flex;
  align-items:center;
  gap:6px;
}
.badge .dot{
  width:8px;
  height:8px;
  border-radius:50%;
  background:#22c55e;
  box-shadow:0 0 8px rgba(34,197,94,0.9);
}
.right-top{
  text-align:right;
  font-size:.78rem;
  color:var(--text-muted);
}
.label-row{
  display:flex;
  gap:10px;
  justify-content:flex-end;
  flex-wrap:wrap;
  margin-top:4px;
}
.pill{
  padding:4px 8px;
  border-radius:999px;
  border:1px solid rgba(55,65,81,0.9);
  background:rgba(15,23,42,0.9);
  display:flex;
  align-items:center;
  gap:6px;
}
.pill span:first-child{
  text-transform:uppercase;
  letter-spacing:.1em;
  font-size:.7rem;
}
.pill input{
  background:transparent;
  border:none;
  color:var(--text-main);
  font-size:.8rem;
  outline:none;
  text-align:center;
  min-width:60px;
}
.pill select{
  background:transparent;
  border:none;
  color:var(--text-main);
  font-size:.8rem;
  outline:none;
}

.main-layout{
  display:grid;
  grid-template-columns:3fr 1.4fr;
  gap:12px;
  margin-top:12px;
}
@media(max-width:900px){
  .main-layout{grid-template-columns:1fr;}
}

/* Track */
.track-shell{display:flex;justify-content:center;}
.track{
  width:320px;
  height:460px;
  border-radius:18px;
  border:1px solid rgba(55,65,81,0.9);
  background:linear-gradient(#020617,#020617);
  position:relative;
  overflow:hidden;
  box-shadow:0 0 24px rgba(15,23,42,0.9);
}
.road{
  position:absolute;
  top:0;bottom:0;left:18px;right:18px;
  background:var(--road);
  overflow:hidden;
  border-radius:16px;
  border:1px solid rgba(31,41,55,0.9);
}
.road::before{
  content:"";
  position:absolute;
  inset:0;
  background:linear-gradient(to right,
    rgba(56,189,248,0.25),transparent 24%,
    transparent 76%,rgba(248,113,113,0.25));
  pointer-events:none;
}
.lane-line{
  position:absolute;
  width:6px;
  height:54px;
  background:var(--line);
  left:50%;
  transform:translateX(-50%);
  opacity:0.75;
  border-radius:999px;
  animation:laneMove 0.7s linear infinite;
}
.lane-line:nth-child(2){animation-delay:.23s;}
.lane-line:nth-child(3){animation-delay:.46s;}
@keyframes laneMove{
  0%{top:-60px;}
  100%{top:460px;}
}

/* Cars */
.car{
  position:absolute;
  width:42px;
  height:74px;
  border-radius:12px;
  background:linear-gradient(180deg,#38bdf8,#0ea5e9);
  box-shadow:0 0 18px rgba(56,189,248,0.9);
  display:flex;
  flex-direction:column;
  align-items:center;
  overflow:hidden;
}
.car::before{
  content:"";
  width:26px;
  height:22px;
  border-radius:10px 10px 8px 8px;
  background:linear-gradient(180deg,#020617,#1f2937);
  margin-top:4px;
  border:1px solid rgba(15,23,42,0.9);
}
.car::after{
  content:"";
  position:absolute;
  inset:0;
  border-radius:inherit;
  background:linear-gradient(180deg,rgba(248,250,252,0.1),transparent 55%);
  pointer-events:none;
}
.car.player{
  background:linear-gradient(180deg,#22c55e,#16a34a);
  box-shadow:0 0 22px rgba(34,197,94,0.9);
}
.car.player::after{
  background:
    linear-gradient(180deg,rgba(248,250,252,0.15),transparent 55%),
    repeating-linear-gradient(to bottom,
      rgba(34,197,94,0.5) 0px,
      rgba(34,197,94,0.5) 2px,
      transparent 3px,
      transparent 6px);
}
.car.enemy{
  background:linear-gradient(180deg,#f97316,#b91c1c);
  box-shadow:0 0 22px rgba(248,113,113,0.9);
}
.car.enemy.enemy-blue{
  background:linear-gradient(180deg,#38bdf8,#1d4ed8);
}
.car.enemy.enemy-red{
  background:linear-gradient(180deg,#f97316,#b91c1c);
}
.car.enemy.enemy-purple{
  background:linear-gradient(180deg,#a855f7,#7c3aed);
}
.car span{
  width:9px;
  height:9px;
  border-radius:4px;
  margin-top:auto;
  margin-bottom:6px;
  background:#facc15;
  box-shadow:0 0 10px rgba(250,204,21,0.9);
}
.car.enemy span{
  background:#f97316;
  box-shadow:0 0 10px rgba(248,113,113,0.9);
}
.car.player.crash{
  animation:crashShake .35s ease-out;
}
@keyframes crashShake{
  0%,100%{transform:translateX(0) translateY(0);}
  20%{transform:translateX(-6px);}
  40%{transform:translateX(6px);}
  60%{transform:translateX(-4px);}
  80%{transform:translateX(4px);}
}

/* Coins */
.coin-chip{
  position:absolute;
  width:24px;
  height:24px;
  border-radius:50%;
  border:1px solid rgba(250,204,21,0.9);
  background:radial-gradient(circle,#facc15,#f97316);
  box-shadow:0 0 14px rgba(250,204,21,0.9);
  display:flex;
  align-items:center;
  justify-content:center;
  font-size:0.75rem;
  color:#111827;
}

/* HUD */
.hud-box{
  border-radius:14px;
  padding:8px 10px 10px;
  background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);
  margin-bottom:8px;
}
.hud-title{
  font-size:.76rem;
  text-transform:uppercase;
  letter-spacing:.14em;
  color:var(--text-muted);
  margin:0 0 4px;
}
.hud-row{
  display:flex;
  align-items:center;
  justify-content:space-between;
  font-size:.85rem;
}
.hud-label{
  color:var(--text-muted);
  font-size:.8rem;
}
.hud-value{
  font-weight:600;
  color:#facc15;
  text-shadow:0 0 10px rgba(250,204,21,0.8);
}
.speed-bar{
  width:100%;
  height:8px;
  border-radius:999px;
  background:#020617;
  border:1px solid rgba(55,65,81,0.9);
  overflow:hidden;
  margin-top:4px;
}
.speed-fill{
  height:100%;
  width:30%;
  border-radius:inherit;
  background:linear-gradient(90deg,#22c55e,#eab308,#f97316);
  transition:width .12s ease-out;
}
.btn-row{
  display:flex;
  gap:8px;
  flex-wrap:wrap;
  margin-top:8px;
}
.btn{
  border:none;
  border-radius:999px;
  padding:7px 16px;
  font-size:.8rem;
  font-weight:600;
  cursor:pointer;
  display:inline-flex;
  align-items:center;
  gap:7px;
  background:rgba(15,23,42,0.95);
  color:var(--text-main);
  border:1px solid rgba(55,65,81,0.9);
  text-transform:uppercase;
  letter-spacing:.08em;
  position:relative;
  overflow:hidden;
  transition:transform .12s,box-shadow .12s,border-color .12s;
}
.btn:hover{
  transform:translateY(-1px);
  box-shadow:0 0 14px rgba(56,189,248,0.6);
  border-color:rgba(56,189,248,0.9);
}
.btn-primary{
  background:radial-gradient(circle at 0 0,#facc15,#f97316);
  color:#020617;
  box-shadow:0 0 18px rgba(248,181,0,0.8);
}
.toast{
  margin-top:4px;
  font-size:.75rem;
  color:var(--text-muted);
}
.toast span{color:#f97316;}
.achievements{
  margin-top:6px;
  font-size:.76rem;
  color:var(--text-muted);
}
.lives-row{
  margin-top:4px;
  font-size:.8rem;
  color:var(--text-muted);
}
.lives-hearts{
  font-size:1rem;
  margin-left:4px;
}

/* Overlay */
.overlay{
  position:absolute;
  inset:0;
  background:rgba(15,23,42,0.93);
  display:flex;
  justify-content:center;
  align-items:center;
  visibility:hidden;
  opacity:0;
  transition:opacity .2s ease-out;
}
.overlay.show{
  visibility:visible;
  opacity:1;
}
.overlay-card{
  background:#020617;
  border-radius:14px;
  padding:14px 18px 16px;
  border:1px solid rgba(248,113,113,0.7);
  max-width:280px;
  text-align:center;
  box-shadow:0 0 20px rgba(248,113,113,0.7);
}
.overlay-card h2{
  margin:0 0 6px;
  color:#f97316;
  text-transform:uppercase;
  letter-spacing:.14em;
  font-size:.9rem;
}
.overlay-card p{
  margin:4px 0 10px;
  font-size:.82rem;
  color:var(--text-muted);
}
.key-row{
  margin-top:6px;
  font-size:.76rem;
  color:var(--text-muted);
}
.key-tag{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:18px;
  height:18px;
  border-radius:4px;
  border:1px solid rgba(55,65,81,0.9);
  font-size:.7rem;
  margin-right:4px;
}

/* Scoreboard */
.score-box{
  border-radius:14px;
  padding:8px 10px;
  background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);
  margin-top:8px;
  font-size:.78rem;
}
.score-box table{
  width:100%;
  border-collapse:collapse;
  font-size:.76rem;
}
.score-box th,.score-box td{
  padding:3px 4px;
  border-bottom:1px solid rgba(31,41,55,0.8);
}
.score-box th{
  text-align:left;
  color:var(--text-muted);
  text-transform:uppercase;
  letter-spacing:.08em;
  font-size:.7rem;
}
.score-box tr:nth-child(odd){
  background:rgba(15,23,42,0.9);
}

@media(max-width:520px){
  .track{width:260px;height:420px;}
}
</style>
</head>
<body>
<div class="shell">
  <div class="container">
    <div class="header">
      <div>
        <h1>Neon Street Racer</h1>
        <div class="subtitle">
          3 / 6 / 10 lanes. Easy, Medium, Hard. Dodge traffic, grab coins, chase neon glory.
        </div>
        <div class="badge-row">
          <div class="badge">
            <div class="dot"></div>
            LIVE RUN
          </div>
        </div>
      </div>
      <div class="right-top">
        <div class="label-row">
          <div class="pill">
            <span>Name</span>
            <input id="player-name" type="text" placeholder="Racer" value="Racer">
          </div>
          <div class="pill">
            <span>Difficulty</span>
            <select id="difficulty-select">
              <option value="easy">Easy (3 lanes)</option>
              <option value="medium">Medium (6 lanes)</option>
              <option value="hard">Hard (10 lanes)</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="main-layout">
      <div class="track-shell">
        <div class="track">
          <div class="road" id="road">
            <!-- center lane lines -->
            <div class="lane-line"></div>
            <div class="lane-line"></div>
            <div class="lane-line"></div>

            <!-- player car -->
            <div class="car player" id="player-car">
              <span></span>
            </div>
            <!-- enemies and coins injected by JS -->
          </div>

          <!-- overlay -->
          <div class="overlay" id="game-over-overlay">
            <div class="overlay-card">
              <h2>Crash!</h2>
              <p id="game-over-text">You hit a car.</p>
              <div id="achievements-summary" style="font-size:.78rem;margin-bottom:8px;"></div>
              <button class="btn btn-primary" id="restart-btn">Restart race</button>
            </div>
          </div>
        </div>
      </div>

      <div>
        <div class="hud-box">
          <p class="hud-title">Race stats</p>
          <div class="hud-row">
            <div>
              <div class="hud-label">Distance</div>
              <div class="hud-value"><span id="distance-label">0</span> m</div>
            </div>
            <div>
              <div class="hud-label">Coins</div>
              <div class="hud-value"><span id="coins-label">0</span></div>
            </div>
          </div>
          <div class="hud-row" style="margin-top:6px;">
            <div>
              <div class="hud-label">Speed</div>
              <div style="font-size:.8rem;"><span id="speed-label">120</span> km/h</div>
            </div>
            <div>
              <div class="hud-label">Best score</div>
              <div style="font-size:.8rem;"><span id="best-label">0</span></div>
            </div>
          </div>
          <div class="speed-bar">
            <div class="speed-fill" id="speed-fill"></div>
          </div>
          <div class="lives-row">
            Lives
            <span class="lives-hearts" id="lives-hearts"></span>
          </div>
        </div>

        <div class="hud-box">
          <p class="hud-title">Controls</p>
          <div class="key-row">
            <span class="key-tag">A</span><span class="key-tag">←</span> lane left
          </div>
          <div class="key-row">
            <span class="key-tag">D</span><span class="key-tag">→</span> lane right
          </div>
          <div class="key-row">
            <span class="key-tag">W</span><span class="key-tag">↑</span> hold to boost
          </div>
          <div class="btn-row">
            <button class="btn btn-primary" id="start-btn">Start race</button>
            <button class="btn" id="reset-btn">Reset stats</button>
          </div>
          <div class="toast">
            Tip: <span>Distance × difficulty multiplier + coins × 20</span> = score.
            Hard hits harder.
          </div>
          <div class="achievements" id="achievements-live">
            Achievements: -
          </div>
        </div>

        <div class="score-box">
          <div class="hud-title" style="margin-bottom:4px;">
            Scoreboard <span id="scoreboard-diff-label">Easy</span>
          </div>
          <div id="scoreboard-table-wrap">
            <div class="small-text" style="font-size:.75rem;color:var(--text-muted);">
              No runs yet. Finish a race to appear here.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const difficultyConfig = {
  easy:   { lanes:3,  baseSpeed:120, spawn:1.2, coinSpawn:1.5, scoreMult:1.0 },
  medium: { lanes:6,  baseSpeed:140, spawn:1.0, coinSpawn:1.3, scoreMult:1.3 },
  hard:   { lanes:10, baseSpeed:160, spawn:0.8, coinSpawn:1.1, scoreMult:1.7 },
};

const roadEl = document.getElementById('road');
const playerEl = document.getElementById('player-car');
const distanceLabel = document.getElementById('distance-label');
const coinsLabel = document.getElementById('coins-label');
const speedLabel = document.getElementById('speed-label');
const bestLabel = document.getElementById('best-label');
const speedFill = document.getElementById('speed-fill');
const startBtn = document.getElementById('start-btn');
const resetBtn = document.getElementById('reset-btn');
const restartBtn = document.getElementById('restart-btn');
const overlay = document.getElementById('game-over-overlay');
const gameOverText = document.getElementById('game-over-text');
const nameInput = document.getElementById('player-name');
const livesHearts = document.getElementById('lives-hearts');
const achievementsLive = document.getElementById('achievements-live');
const achievementsSummary = document.getElementById('achievements-summary');
const difficultySelect = document.getElementById('difficulty-select');
const scoreboardDiffLabel = document.getElementById('scoreboard-diff-label');
const scoreboardTableWrap = document.getElementById('scoreboard-table-wrap');

let lanes = [];
let currentLaneIndex = 0;
let currentDifficulty = 'easy';
let running = false;
let lastTime = 0;
let distance = 0;
let coins = 0;
let baseSpeed = 120;
let boost = false;
let bestScore = 0;
let lives = 3;

let enemies = [];
let coinsObjs = [];
let spawnTimer = 0;
let coinTimer = 0;

let maxSpeedSeen = 0;
let flawlessRun = true;
let coinsThisRun = 0;

function getRoadBounds() {
  const rect = roadEl.getBoundingClientRect();
  return { width:rect.width, height:rect.height };
}

function buildLanes(count) {
  const arr = [];
  for (let i=0;i<count;i++) {
    arr.push((i+0.5)/count);
  }
  lanes = arr;
  currentLaneIndex = Math.floor(count/2);
}

function placePlayer() {
  const { width, height } = getRoadBounds();
  const laneX = lanes[currentLaneIndex] * width;
  playerEl.style.left = (laneX - playerEl.offsetWidth/2) + 'px';
  playerEl.style.top  = (height - playerEl.offsetHeight - 10) + 'px';
}

function renderLives() {
  livesHearts.textContent = lives <= 0 ? '💀' : '❤️'.repeat(lives);
}

const enemyTypes = ['enemy-blue','enemy-red','enemy-purple'];

function createEnemy(laneIndex, y) {
  const el = document.createElement('div');
  el.className = 'car enemy ' + enemyTypes[Math.floor(Math.random()*enemyTypes.length)];
  const light = document.createElement('span');
  el.appendChild(light);
  roadEl.appendChild(el);

  const { width } = getRoadBounds();
  const laneX = lanes[laneIndex] * width;
  el.style.left = (laneX - el.offsetWidth/2) + 'px';
  el.style.top = y + 'px';
  return { el, laneIndex, y };
}

function createCoin(laneIndex, y) {
  const el = document.createElement('div');
  el.className = 'coin-chip';
  el.textContent = '¢';
  roadEl.appendChild(el);

  const { width } = getRoadBounds();
  const laneX = lanes[laneIndex] * width;
  el.style.left = (laneX - 12) + 'px';
  el.style.top = y + 'px';
  return { el, laneIndex, y };
}

function resetGame() {
  const cfg = difficultyConfig[currentDifficulty];
  distance = 0;
  coins = 0;
  coinsThisRun = 0;
  baseSpeed = cfg.baseSpeed;

  enemies.forEach(e => e.el.remove());
  coinsObjs.forEach(c => c.el.remove());
  enemies = [];
  coinsObjs = [];

  distanceLabel.textContent = '0';
  coinsLabel.textContent = '0';
  speedLabel.textContent = baseSpeed.toFixed(0);
  speedFill.style.width = '30%';

  overlay.classList.remove('show');
  playerEl.classList.remove('crash');

  lives = 3;
  flawlessRun = true;
  maxSpeedSeen = baseSpeed;
  renderLives();

  achievementsLive.textContent = 'Achievements: -';

  spawnTimer = 0;
  coinTimer = 0;
  buildLanes(cfg.lanes);
  placePlayer();
}

function isColliding(a, b) {
  const ar = a.getBoundingClientRect();
  const br = b.getBoundingClientRect();
  return !(
    ar.right < br.left ||
    ar.left > br.right ||
    ar.bottom < br.top ||
    ar.top > br.bottom
  );
}

function buildAchievements(finalScore) {
  const badges = [];
  if (distance >= 500) badges.push('Long Haul (500m)');
  if (distance >= 800) badges.push('Night Runner (800m)');
  if (coinsThisRun >= 5) badges.push('Coin Hoarder (5+ coins)');
  if (coinsThisRun >= 10) badges.push('Bank Robber (10+ coins)');
  if (flawlessRun && lives === 3) badges.push('Untouchable (no crashes)');
  if (maxSpeedSeen >= 220) badges.push('Speed Demon (220km/h)');
  if (finalScore >= 1000) badges.push('Neon Legend (1000+ score)');

  achievementsLive.textContent = 'Achievements: ' + (badges.length ? badges.join(', ') : '-');
  return badges;
}

function loop(timestamp) {
  if (!running) return;
  if (!lastTime) lastTime = timestamp;
  const dt = (timestamp - lastTime) / 1000;
  lastTime = timestamp;

  const cfg = difficultyConfig[currentDifficulty];
  const speed = baseSpeed + (boost ? 120 : 0);
  maxSpeedSeen = Math.max(maxSpeedSeen, speed);

  const pxPerSec = 170 * (speed / 120);
  const move = pxPerSec * dt;
  const { height } = getRoadBounds();

  enemies.forEach(e => {
    e.y += move * 0.7;
    e.el.style.top = e.y + 'px';
  });
  enemies = enemies.filter(e => {
    if (e.y > height + 80) {
      e.el.remove();
      return false;
    }
    return true;
  });

  coinsObjs.forEach(c => {
    c.y += move * 0.7;
    c.el.style.top = c.y + 'px';
  });
  coinsObjs = coinsObjs.filter(c => {
    if (c.y > height + 40) {
      c.el.remove();
      return false;
    }
    return true;
  });

  spawnTimer += dt;
  if (spawnTimer >= cfg.spawn) {
    spawnTimer = 0;
    const laneIndex = Math.floor(Math.random() * lanes.length);
    enemies.push(createEnemy(laneIndex, -100));
  }

  coinTimer += dt;
  if (coinTimer >= cfg.coinSpawn) {
    coinTimer = 0;
    const laneIndex = Math.floor(Math.random() * lanes.length);
    coinsObjs.push(createCoin(laneIndex, -40));
  }

  distance += speed * dt * 0.1;
  distanceLabel.textContent = distance.toFixed(0);
  speedLabel.textContent = speed.toFixed(0);
  const fillPct = Math.min(100, (speed / 260) * 100);
  speedFill.style.width = fillPct.toFixed(0) + '%';

  for (const e of enemies) {
    if (isColliding(playerEl, e.el)) {
      handleCrash();
      break;
    }
  }

  coinsObjs = coinsObjs.filter(c => {
    if (isColliding(playerEl, c.el)) {
      coins++;
      coinsThisRun++;
      coinsLabel.textContent = coins;
      c.el.style.transform = 'scale(1.3)';
      c.el.style.transition = 'transform .15s, opacity .2s';
      c.el.style.opacity = '0';
      setTimeout(() => c.el.remove(), 180);
      return false;
    }
    return true;
  });

  if (running) requestAnimationFrame(loop);
}

function handleCrash() {
  lives = Math.max(0, lives - 1);
  renderLives();
  playerEl.classList.add('crash');
  flawlessRun = false;

  livesHearts.style.transform = 'scale(1.2)';
  livesHearts.style.transition = 'transform .15s';
  setTimeout(() => {
    livesHearts.style.transform = 'scale(1)';
  }, 160);

  if (lives > 0) {
    baseSpeed = Math.max(difficultyConfig[currentDifficulty].baseSpeed - 20, 90);
    setTimeout(() => playerEl.classList.remove('crash'), 200);
    return;
  }

  running = false;
  const cfg = difficultyConfig[currentDifficulty];
  const score = Math.round(distance * cfg.scoreMult + coins * 20);

  if (score > bestScore) {
    bestScore = score;
    bestLabel.textContent = bestScore;
  }

  const badges = buildAchievements(score);
  gameOverText.textContent =
    `Out of lives! ${currentDifficulty.toUpperCase()} | ` +
    `Distance ${distance.toFixed(0)}m, coins ${coins}. Score ${score}.`;

  achievementsSummary.textContent =
    badges.length ? `Achievements: ${badges.join(', ')}` : 'Achievements: keep pushing for medals!';

  overlay.classList.add('show');

  const name = nameInput.value.trim() || 'Racer';
  const detail = `Neon Street Racer diff=${currentDifficulty}, lanes=${lanes.length}, distance=${distance.toFixed(0)}m, coins=${coins}, score=${score}, badges=${badges.join(',')}`;
  const achievementsStr = badges.join(', ');

  saveScore(name, score, detail, currentDifficulty, lanes.length, Math.round(distance), coins, achievementsStr);
}

async function saveScore(name, score, detail, difficulty, laneCount, distanceVal, coinsVal, achievements) {
  try {
    const res = await fetch(window.location.href, {
      method: "POST",
      headers: {"Content-Type": "application/json; charset=UTF-8"},
      body: JSON.stringify({
        name,
        score,
        detail,
        difficulty,
        lane_count: laneCount,
        distance: distanceVal,
        coins: coinsVal,
        achievements
      })
    });
    const json = await res.json();
    console.log('Score save:', json);
    await loadScores(difficulty);
  } catch (err) {
    console.error('Score save error:', err);
  }
}

async function loadScores(diff) {
  scoreboardDiffLabel.textContent =
    diff === 'easy' ? 'Easy' : diff === 'medium' ? 'Medium' : 'Hard';

  scoreboardTableWrap.innerHTML =
    '<div style="font-size:.75rem;color:#9ca3af;">Loading scores...</div>';

  try {
    const res = await fetch(window.location.href + '?scores=1&difficulty=' + encodeURIComponent(diff));
    const data = await res.json();
    console.log('Scores:', data);

    if (!data.ok || !data.rows || data.rows.length === 0) {
      scoreboardTableWrap.innerHTML =
        '<div style="font-size:.75rem;color:#9ca3af;">No runs yet for this difficulty.</div>';
      return;
    }

    const rows = data.rows;
    let html = '<table><thead><tr>' +
      '<th>#</th><th>Player</th><th>Score</th><th>Dist</th><th>Coins</th>' +
      '</tr></thead><tbody>';

    rows.forEach((r, i) => {
      html += `<tr>
        <td>${i+1}</td>
        <td>${r.player_name}</td>
        <td>${r.score}</td>
        <td>${r.distance}</td>
        <td>${r.coins}</td>
      </tr>`;
    });

    html += '</tbody></table>';
    scoreboardTableWrap.innerHTML = html;
  } catch (e) {
    console.error('Scoreboard error:', e);
    scoreboardTableWrap.innerHTML =
      '<div style="font-size:.75rem;color:#9ca3af;">Scoreboard error – check console.</div>';
  }
}

window.addEventListener('keydown', e => {
  if (!running) return;
  if (e.key === 'a' || e.key === 'A' || e.key === 'ArrowLeft') {
    currentLaneIndex = Math.max(0, currentLaneIndex - 1);
    placePlayer();
  } else if (e.key === 'd' || e.key === 'D' || e.key === 'ArrowRight') {
    currentLaneIndex = Math.min(lanes.length - 1, currentLaneIndex + 1);
    placePlayer();
  } else if (e.key === 'w' || e.key === 'W' || e.key === 'ArrowUp') {
    boost = true;
  }
});
window.addEventListener('keyup', e => {
  if (e.key === 'w' || e.key === 'W' || e.key === 'ArrowUp') {
    boost = false;
  }
});

difficultySelect.addEventListener('change', () => {
  currentDifficulty = difficultySelect.value;
  if (running) running = false;
  resetGame();
  loadScores(currentDifficulty);
});

startBtn.addEventListener('click', () => {
  if (running) return;
  resetGame();
  running = true;
  lastTime = 0;
  requestAnimationFrame(loop);
});

resetBtn.addEventListener('click', () => {
  running = false;
  resetGame();
});

restartBtn.addEventListener('click', () => {
  running = true;
  overlay.classList.remove('show');
  playerEl.classList.remove('crash');
  lastTime = 0;
  requestAnimationFrame(loop);
});

window.addEventListener('load', () => {
  currentDifficulty = difficultySelect.value;
  resetGame();
  loadScores(currentDifficulty);
});
window.addEventListener('resize', () => {
  placePlayer();
});
</script>
</body>
</html>
