<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = '127.0.0.1';  // or 'localhost'
    $db   = 'gamehub';    // from your SQL dump
    $user = 'root';       // change if different
    $pass = '';           // change if you use a password
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);
    return $pdo;
}

// ---------- API: GET /?scores=1 -> top 5 leaderboard ----------
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['scores'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $pdo = get_pdo();
        $gameTitle = 'Snake Modes Arcade';

        // find game_id in existing `games`
        $stmt = $pdo->prepare("SELECT game_id FROM games WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $gameTitle]);
        $row = $stmt->fetch();

        if (!$row) {
            echo json_encode(['ok' => true, 'rows' => []]);
            exit;
        }

        $gameId = (int)$row['game_id'];

        // join leaderboards + game_history from your schema
        $stmt = $pdo->prepare("
            SELECT 
                l.score,
                l.level_reached AS level,
                gh.result,
                gh.created_at
            FROM leaderboards l
            JOIN game_history gh ON gh.history_id = l.history_id
            WHERE l.game_id = :game_id
            ORDER BY l.score DESC, gh.created_at ASC
            LIMIT 5
        ");
        $stmt->execute([':game_id' => $gameId]);
        $rows = $stmt->fetchAll();

        echo json_encode(['ok' => true, 'rows' => $rows]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ---------- API: POST JSON -> save one run ----------
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
    $length     = $data['length'] ?? null;
    $speedLevel = $data['speed'] ?? null;
    $mode       = trim($data['mode'] ?? '');

    if (
        $playerName === '' ||
        !is_numeric($score) ||
        !is_numeric($length) ||
        !is_numeric($speedLevel) ||
        $mode === ''
    ) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    try {
        $pdo = get_pdo();

        // 1) ensure demo user + theme exist (tables from dump)
        $pdo->exec("
            INSERT IGNORE INTO users (user_id, email, password)
            VALUES (1, 'snake_modes@example.com', 'dummy')
        ");

        $pdo->exec("
            INSERT IGNORE INTO themes (theme_id, theme_name, primary_color)
            VALUES (1, 'snake-modes-retro', '#33ffcc')
        ");

        $userId    = 1;
        $gameTitle = 'Snake Modes Arcade';

        // 2) ensure game row in games
        $stmt = $pdo->prepare("SELECT game_id FROM games WHERE name = :name LIMIT 1");
        $stmt->execute([':name' => $gameTitle]);
        $row = $stmt->fetch();

        if ($row) {
            $gameId = (int)$row['game_id'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO games (name, description, max_players, min_players, is_active)
                VALUES (:name, :desc, 1, 1, 1)
            ");
            $stmt->execute([
                ':name' => $gameTitle,
                ':desc' => 'Retro snake with Box / No Box modes, speeds and neon scoreboard',
            ]);
            $gameId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO single_games (game_id, difficulty, bot_id)
                VALUES (:game_id, :diff, :bot_id)
            ");
            $stmt->execute([
                ':game_id' => $gameId,
                ':diff'    => 'normal',
                ':bot_id'  => null,
            ]);
        }

        // 3) session in game_sessions
        $stmt = $pdo->prepare("
            INSERT INTO game_sessions (
                game_id,
                host_player_id,
                status,
                max_players,
                current_players,
                is_private,
                start_time,
                created_at
            )
            VALUES (:game_id, :host_player_id, 'completed', 1, 1, 0, NOW(), NOW())
        ");
        $stmt->execute([
            ':game_id'        => $gameId,
            ':host_player_id' => $userId,
        ]);
        $sessionId = (int)$pdo->lastInsertId();

        // 4) game_history
        $detail = sprintf(
            'Player %s, Score %d, length %d, speed %d, mode %s',
            $playerName,
            (int)$score,
            (int)$length,
            (int)$speedLevel,
            $mode
        );

        $stmt = $pdo->prepare("
            INSERT INTO game_history (
                game_id,
                session_id,
                player_id,
                result,
                score,
                duration_minutes,
                created_at
            )
            VALUES (:game_id, :session_id, :player_id, :result, :score, :duration, NOW())
        ");
        $stmt->execute([
            ':game_id'   => $gameId,
            ':session_id'=> $sessionId,
            ':player_id' => $userId,
            ':result'    => $detail,
            ':score'     => (int)$score,
            ':duration'  => 0,
        ]);
        $historyId = (int)$pdo->lastInsertId();

        // 5) leaderboards
        $stmt = $pdo->prepare("
            INSERT INTO leaderboards (
                history_id,
                game_id,
                player_id,
                score,
                level_reached,
                time_played_minutes,
                achievements_unlocked,
                updated_at
            )
            VALUES (:history_id, :game_id, :player_id, :score, :level, 0, 0, NOW())
        ");
        $stmt->execute([
            ':history_id' => $historyId,
            ':game_id'    => $gameId,
            ':player_id'  => $userId,
            ':score'      => (int)$score,
            ':level'      => (int)$speedLevel,
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

// If not API, output the game page.
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Snake Modes Arcade</title>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<style>
    body {
        background: #050612;
        color: #ffdd33;
        font-family: 'Press Start 2P', system-ui, sans-serif;
        display: flex;
        flex-direction: row;
        justify-content: center;
        align-items: flex-start;
        gap: 20px;
        padding: 20px;
    }
    #game-container {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    canvas {
        background: #050612;
        border: 3px solid #33ffcc;
        box-shadow: 0 0 20px #33ffcc;
    }
    .panel {
        border: 2px solid #33ffcc;
        box-shadow: 0 0 12px #33ffcc;
        padding: 10px;
        max-width: 260px;
    }
    button {
        background: #050612;
        color: #ff3366;
        border: 2px solid #ff3366;
        padding: 6px 10px;
        cursor: pointer;
        margin: 2px;
        text-transform: uppercase;
        font-size: 10px;
    }
    button:hover {
        background: #ff3366;
        color: #050612;
    }
    input, select {
        background: #050612;
        color: #33ffcc;
        border: 1px solid #33ffcc;
        padding: 3px;
        margin: 3px 0;
        width: 100%;
        box-sizing: border-box;
        font-family: inherit;
        font-size: 10px;
    }
    h2, h3 {
        margin: 4px 0;
        text-shadow: 0 0 6px #ff3366;
        font-size: 14px;
    }
    ul {
        padding-left: 18px;
        font-size: 10px;
    }
    label {
        font-size: 10px;
    }
</style>
</head>
<body>
<div id="game-container">
    <h2>Snake Modes Arcade</h2>
    <canvas id="game" width="400" height="400"></canvas>
    <div style="margin-top:10px;">
        <button id="btn-start">Start</button>
        <button id="btn-pause">Pause</button>
        <button id="btn-reset">Reset</button>
    </div>
    <div style="margin-top:10px; font-size:10px;">
        SCORE: <span id="score">0</span> |
        LENGTH: <span id="length">1</span> |
        SPEED: <span id="speed-label">1</span>
    </div>
</div>

<div class="panel">
    <h3>INSERT NAME</h3>
    <label>
        PLAYER:
        <input type="text" id="player-name" placeholder="TYPE NAME">
    </label>
    <label>
        MODE:
        <select id="mode">
            <option value="box">BOX (WALLS)</option>
            <option value="no-box">NO BOX (WRAP)</option>
        </select>
    </label>
    <label>
        SPEED:
        <select id="speed">
            <option value="1">SLOW</option>
            <option value="2">MEDIUM</option>
            <option value="3">FAST</option>
            <option value="4">INSANE</option>
        </select>
    </label>
    <button id="btn-save-score">SAVE SCORE</button>

    <h3 style="margin-top:10px;">HIGH SCORES</h3>
    <ul id="top-list"></ul>
</div>

<script>
(function() {
    const canvas = document.getElementById('game');
    const ctx    = canvas.getContext('2d');

    const gridSize = 20;
    const tileCount = canvas.width / gridSize;

    let snake = [{ x: 10, y: 10 }];
    let vx = 0, vy = 0;
    let food = { x: 5, y: 5 };
    let score = 0;
    let speedLevel = 1;
    let tickInterval = null;
    let running = false;
    let mode = 'box';

    const scoreEl  = document.getElementById('score');
    const lengthEl = document.getElementById('length');
    const speedLabelEl = document.getElementById('speed-label');

    function resetGame() {
        snake = [{ x: 10, y: 10 }];
        vx = 0; vy = 0;
        score = 0;
        placeFood();
        updateHUD();
    }

    function startGame() {
        if (running) return;
        running = true;
        if (vx === 0 && vy === 0) {
            vx = 1; vy = 0;
        }
        if (tickInterval) clearInterval(tickInterval);
        const baseSpeed = 140;
        const interval = baseSpeed - (speedLevel - 1) * 25;
        tickInterval = setInterval(tick, Math.max(40, interval));
    }

    function pauseGame() {
        running = false;
        if (tickInterval) {
            clearInterval(tickInterval);
            tickInterval = null;
        }
    }

    function tick() {
        if (!running) return;

        const head = { x: snake[0].x + vx, y: snake[0].y + vy };

        if (mode === 'box') {
            if (head.x < 0 || head.y < 0 || head.x >= tileCount || head.y >= tileCount) {
                gameOver();
                return;
            }
        } else {
            if (head.x < 0) head.x = tileCount - 1;
            if (head.x >= tileCount) head.x = 0;
            if (head.y < 0) head.y = tileCount - 1;
            if (head.y >= tileCount) head.y = 0;
        }

        for (let i = 0; i < snake.length; i++) {
            if (snake[i].x === head.x && snake[i].y === head.y) {
                gameOver();
                return;
            }
        }

        snake.unshift(head);

        if (head.x === food.x && head.y === food.y) {
            score += 10;
            placeFood();
        } else {
            snake.pop();
        }

        updateHUD();
        draw();
    }

    function updateHUD() {
        scoreEl.textContent  = score;
        lengthEl.textContent = snake.length;
        speedLabelEl.textContent = String(speedLevel);
    }

    function placeFood() {
        food.x = Math.floor(Math.random() * tileCount);
        food.y = Math.floor(Math.random() * tileCount);
    }

    function draw() {
        ctx.fillStyle = '#050612';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        ctx.fillStyle = '#33ffcc';
        snake.forEach((seg) => {
            ctx.fillRect(seg.x * gridSize, seg.y * gridSize, gridSize, gridSize);
        });

        ctx.fillStyle = '#ff3366';
        ctx.fillRect(food.x * gridSize, food.y * gridSize, gridSize, gridSize);
    }

    function gameOver() {
        pauseGame();
        alert('GAME OVER\nSCORE: ' + score);
    }

    document.addEventListener('keydown', (e) => {
        switch (e.key) {
            case 'ArrowLeft':
                if (vx !== 1) { vx = -1; vy = 0; }
                break;
            case 'ArrowRight':
                if (vx !== -1) { vx = 1; vy = 0; }
                break;
            case 'ArrowUp':
                if (vy !== 1) { vx = 0; vy = -1; }
                break;
            case 'ArrowDown':
                if (vy !== -1) { vx = 0; vy = 1; }
                break;
        }
    });

    document.getElementById('btn-start').addEventListener('click', () => {
        startGame();
    });

    document.getElementById('btn-pause').addEventListener('click', () => {
        pauseGame();
    });

    document.getElementById('btn-reset').addEventListener('click', () => {
        pauseGame();
        resetGame();
        draw();
    });

    const modeSelect  = document.getElementById('mode');
    const speedSelect = document.getElementById('speed');

    modeSelect.addEventListener('change', () => {
        mode = modeSelect.value;
    });

    speedSelect.addEventListener('change', () => {
        speedLevel = parseInt(speedSelect.value, 10) || 1;
        if (running) {
            pauseGame();
            startGame();
        } else {
            speedLabelEl.textContent = String(speedLevel);
        }
    });

    // Save score -> POST JSON to same file
    document.getElementById('btn-save-score').addEventListener('click', async () => {
        const name = document.getElementById('player-name').value.trim();
        if (!name) {
            alert('ENTER PLAYER NAME');
            return;
        }
        const payload = {
            name,
            score,
            length: snake.length,
            speed: speedLevel,
            mode: mode
        };

        try {
            const res = await fetch(location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!res.ok || !data.ok) {
                console.error(data);
                alert('FAILED TO SAVE SCORE');
                return;
            }
            alert('SCORE SAVED');
            loadTopScores();
        } catch (err) {
            console.error(err);
            alert('ERROR WHILE SAVING');
        }
    });

    // Load top 5 scores
    async function loadTopScores() {
        try {
            const url = location.pathname + '?scores=1';
            const res = await fetch(url);
            const data = await res.json();
            const list = document.getElementById('top-list');
            list.innerHTML = '';

            if (!data.ok) {
                list.innerHTML = '<li>FAILED TO LOAD SCORES</li>';
                return;
            }

            if (!data.rows.length) {
                list.innerHTML = '<li>NO SCORES YET</li>';
                return;
            }

            data.rows.forEach((row) => {
                const li = document.createElement('li');
                li.textContent =
                    `SCORE ${row.score} | LV ${row.level} | ` +
                    (row.result || '').replace('Player ', '');
                list.appendChild(li);
            });
        } catch (err) {
            console.error(err);
            document.getElementById('top-list').innerHTML =
                '<li>ERROR LOADING SCORES</li>';
        }
    }

    // initial
    resetGame();
    draw();
    loadTopScores();
})();
</script>
</body>
</html>

