<?php
// ============== DEV ERRORS ==============
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============== PDO ==============
function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;

    $host = '127.0.0.1';
    $db   = 'gamehub';
    $user = 'root';
    $pass = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host=$host;dbname=$db;charset=$charset";
    $opts = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $user, $pass, $opts);
    return $pdo;
}

// ============== ENSURE GAME ROW + DEMO USER ==============
function ensure_sudoku_game(PDO $pdo): array {
    $title = 'Neon Sudoku Grid';

    // demo user
    $pdo->exec("
        INSERT IGNORE INTO users (userid, email, password)
        VALUES (6, 'sudoku_arcade@example.com', 'dummy')
    ");
    $userId = 6;

    // games table: gameid, names, descriptions, category, maxplayers, minplayers, isactives, createdat [file:1]
    $stmt = $pdo->prepare("SELECT gameid FROM games WHERE names = :n LIMIT 1");
    $stmt->execute([':n' => $title]);
    $row = $stmt->fetch();

    if ($row) {
        $gameId = (int)$row['gameid'];
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO games (names, descriptions, category, maxplayers, minplayers, isactives, createdat)
            VALUES (:n, :d, :c, 1, 1, 1, NOW())
        ");
        $stmt->execute([
            ':n' => $title,
            ':d' => 'Tron-style 9x9 Sudoku board with validation and arcade leaderboard.',
            ':c' => 'Puzzle',
        ]);
        $gameId = (int)$pdo->lastInsertId();
    }

    return [$gameId, $userId, $title];
}

// ============== API: GET ?stats=1 (last 5 + leaderboard) ==============
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stats'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $pdo = get_pdo();
        [$gameId, $userId, $title] = ensure_sudoku_game($pdo);

        $stmt = $pdo->prepare("
            SELECT result, scores, durationminutes, createdat
            FROM gamehistory
            WHERE gameid = :g
            ORDER BY createdat DESC
            LIMIT 5
        ");
        $stmt->execute([':g' => $gameId]);
        $runs = $stmt->fetchAll();

        $stmt = $pdo->prepare("
            SELECT playerid, SUM(scores) AS total
            FROM leaderboards
            WHERE gameid = :g
            GROUP BY playerid
            ORDER BY total DESC
            LIMIT 5
        ");
        $stmt->execute([':g' => $gameId]);
        $leaders = $stmt->fetchAll();

        echo json_encode(['ok' => true, 'runs' => $runs, 'leaders' => $leaders]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============== API: POST ?finish=1 (save solved game) ==============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['finish'])) {
    header('Content-Type: application/json; charset=utf-8');

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $playerName = trim($data['playerName'] ?? 'PLAYER');
    $minutes    = (int)($data['minutes'] ?? 10);
    $mistakes   = (int)($data['mistakes'] ?? 0);

    // solved is boolean; here only called when solved=true from front end
    $solved = (bool)($data['solved'] ?? false);
    if (!$solved) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Puzzle not solved']);
        exit;
    }

    try {
        $pdo = get_pdo();
        [$gameId, $userId, $title] = ensure_sudoku_game($pdo);

        // gamesessions [file:1]
        $stmt = $pdo->prepare("
            INSERT INTO gamesessions (
                gameid, hostplayerid, status, maxplayers, currentplayers,
                isprivate, starttime, createdat
            )
            VALUES (:g, :u, 'completed', 1, 1, 0, NOW(), NOW())
        ");
        $stmt->execute([':g' => $gameId, ':u' => $userId]);
        $sessionId = (int)$pdo->lastInsertId();

        // scoring: base 500 - mistakes*25 - minutes*5, min 0 (arcade style) [web:224]
        $score = 500 - $mistakes * 25 - $minutes * 5;
        if ($score < 0) $score = 0;

        $detail = sprintf(
            '%s: SUDOKU SOLVED | TIME %d MIN | MISTAKES %d',
            $playerName,
            $minutes,
            $mistakes
        );

        // gamehistory [file:1]
        $stmt = $pdo->prepare("
            INSERT INTO gamehistory (
                gameid, sessionid, playerid, result, scores, experiencegained,
                durationminutes, createdat
            )
            VALUES (:g, :s, :p, :r, :sc, :exp, :dur, NOW())
        ");
        $stmt->execute([
            ':g'   => $gameId,
            ':s'   => $sessionId,
            ':p'   => $userId,
            ':r'   => 'win',
            ':sc'  => $score,
            ':exp' => max(5, (int)($score / 10)),
            ':dur' => $minutes,
        ]);

        // leaderboards [file:1]
        $stmt = $pdo->prepare("
            INSERT INTO leaderboards (
                gameid, playerid, scores, levelreached, rankposition,
                completionpercentage, timeplayedminutes, achievementsunlocked,
                lastplayed, bestscoredate, updatedat
            )
            VALUES (:g, :p, :sc, :lvl, NULL, 100, :mins, :ach, NOW(), NOW(), NOW())
        ");
        $stmt->execute([
            ':g'    => $gameId,
            ':p'    => $userId,
            ':sc'   => $score,
            ':lvl'  => 1,
            ':mins' => $minutes,
            ':ach'  => ($mistakes === 0 ? 1 : 0),
        ]);

        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============== FRONTEND ==============
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Neon Sudoku Grid</title>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<style>
body {
    background: radial-gradient(circle at top, #031017 0, #000000 70%);
    color: #00E5FE;
    font-family: 'Press Start 2P', system-ui, sans-serif;
    display: flex;
    justify-content: center;
    align-items: flex-start;
    gap: 24px;
    padding: 20px;
}
#game-container {
    display: flex;
    flex-direction: column;
    align-items: center;
}
h2, h3 {
    margin: 4px 0;
    text-shadow: 0 0 10px #00A3E0;
    font-size: 14px;
}
#sudoku {
    display: grid;
    grid-template-columns: repeat(9, 40px);
    grid-template-rows: repeat(9, 40px);
    border: 3px solid #00A3E0;
    box-shadow: 0 0 30px #00A3E0;
    background: #010409;
}
.cell {
    width: 40px;
    height: 40px;
    box-sizing: border-box;
    border: 1px solid #02202E;
}
.cell input {
    width: 100%;
    height: 100%;
    background: transparent;
    border: none;
    text-align: center;
    color: #00E5FE;
    font-size: 18px;
    font-family: 'Press Start 2P', system-ui, sans-serif;
}
.cell input:focus {
    outline: none;
    background: rgba(0,163,224,0.25);
}
.block-border-top {
    border-top: 3px solid #FF6C2D;
}
.block-border-left {
    border-left: 3px solid #FF6C2D;
}
.block-border-right {
    border-right: 3px solid #FF6C2D;
}
.block-border-bottom {
    border-bottom: 3px solid #FF6C2D;
}
.prefilled input {
    color: #FFD300;
}
.error input {
    background: rgba(255, 60, 60, 0.35);
}
.panel {
    border: 2px solid #00A3E0;
    box-shadow: 0 0 16px #00A3E0;
    padding: 10px;
    max-width: 260px;
    font-size: 11px;
    color: #E0F8FF;
    background: rgba(1, 5, 14, 0.9);
}
button {
    background: #010409;
    color: #FF6C2D;
    border: 2px solid #FF6C2D;
    padding: 6px 8px;
    cursor: pointer;
    margin: 2px 0;
    text-transform: uppercase;
    font-size: 10px;
}
button:hover {
    background: #FF6C2D;
    color: #010409;
}
input.info {
    background: #010409;
    color: #00E5FE;
    border: 1px solid #00E5FE;
    padding: 3px;
    margin: 3px 0;
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
    font-size: 10px;
}
ul {
    padding-left: 18px;
    font-size: 10px;
}
#stats {
    margin-top: 6px;
    font-size: 10px;
}
</style>
</head>
<body>
<div id="game-container">
    <h2>Neon Sudoku Grid</h2>
    <div id="sudoku"></div>
    <div id="stats">
        MISTAKES: <span id="mistakes">0</span>
    </div>
    <div style="margin-top:6px;font-size:9px;">
        FILL 1–9 • CLICK VALIDATE • SOLVE TO SAVE
    </div>
</div>

<div class="panel">
    <h3>PLAYER & SAVE</h3>
    <label>
        NAME:
        <input type="text" id="player-name" class="info" placeholder="SUDOKU HERO">
    </label>
    <label>
        MINUTES:
        <input type="number" id="minutes" class="info" min="1" value="10">
    </label>
    <button id="btn-reset">RESET PUZZLE</button>
    <button id="btn-validate">VALIDATE</button>
    <button id="btn-save">SAVE GAME</button>

    <h3 style="margin-top:10px;">LAST 5 GAMES</h3>
    <ul id="run-list"></ul>

    <h3 style="margin-top:10px;">TOP 5 PLAYERS</h3>
    <ul id="leader-list"></ul>

    <h3 style="margin-top:10px;">RULES</h3>
    <ul>
        <li>1–9 each row.</li>
        <li>1–9 each column.</li>
        <li>1–9 each 3×3 box.</li>
    </ul>
</div>

<script>
(function() {
    const sudokuEl   = document.getElementById('sudoku');
    const mistakesEl = document.getElementById('mistakes');
    const btnReset   = document.getElementById('btn-reset');
    const btnValidate= document.getElementById('btn-validate');
    const btnSave    = document.getElementById('btn-save');

    // A valid Sudoku solution we use as "answer". [web:224]
    const solution = [
        [5,3,4,6,7,8,9,1,2],
        [6,7,2,1,9,5,3,4,8],
        [1,9,8,3,4,2,5,6,7],
        [8,5,9,7,6,1,4,2,3],
        [4,2,6,8,5,3,7,9,1],
        [7,1,3,9,2,4,8,5,6],
        [9,6,1,5,3,7,2,8,4],
        [2,8,7,4,1,9,6,3,5],
        [3,4,5,2,8,6,1,7,9]
    ];

    // Puzzle: zeros mean empty, other numbers pre-filled.
    const puzzle = [
        [5,3,0,0,7,0,0,0,0],
        [6,0,0,1,9,5,0,0,0],
        [0,9,8,0,0,0,0,6,0],
        [8,0,0,0,6,0,0,0,3],
        [4,0,0,8,0,3,0,0,1],
        [7,0,0,0,2,0,0,0,6],
        [0,6,0,0,0,0,2,8,0],
        [0,0,0,4,1,9,0,0,5],
        [0,0,0,0,8,0,0,7,9]
    ];

    let cells = [];
    let mistakes = 0;
    let solved = false;

    function buildGrid() {
        sudokuEl.innerHTML = '';
        cells = [];
        for (let r=0; r<9; r++) {
            for (let c=0; c<9; c++) {
                const cell = document.createElement('div');
                cell.classList.add('cell');

                if (r % 3 === 0) cell.classList.add('block-border-top');
                if (c % 3 === 0) cell.classList.add('block-border-left');
                if (c === 8)     cell.classList.add('block-border-right');
                if (r === 8)     cell.classList.add('block-border-bottom');

                const input = document.createElement('input');
                input.maxLength = 1;
                input.dataset.row = r;
                input.dataset.col = c;

                if (puzzle[r][c] !== 0) {
                    input.value = String(puzzle[r][c]);
                    input.readOnly = true;
                    cell.classList.add('prefilled');
                } else {
                    input.value = '';
                }

                input.addEventListener('input', () => {
                    let v = input.value.replace(/[^1-9]/g,'');
                    input.value = v;
                    cell.classList.remove('error');
                    solved = false;
                });

                cell.appendChild(input);
                sudokuEl.appendChild(cell);
                cells.push(cell);
            }
        }
    }

    function getGridValues() {
        const grid = [];
        for (let r=0;r<9;r++) {
            const row = [];
            for (let c=0;c<9;c++) {
                const idx = r*9+c;
                const input = cells[idx].querySelector('input');
                const v = input.value;
                row.push(v ? parseInt(v,10) : 0);
            }
            grid.push(row);
        }
        return grid;
    }

    function validateSudoku() {
        const grid = getGridValues();
        mistakes = 0;
        cells.forEach(cell => cell.classList.remove('error'));

        // highlight wrong cells vs solution
        for (let r=0;r<9;r++) {
            for (let c=0;c<9;c++) {
                const idx = r*9+c;
                const val = grid[r][c];
                if (val !== 0 && val !== solution[r][c]) {
                    cells[idx].classList.add('error');
                    mistakes++;
                }
            }
        }

        mistakesEl.textContent = String(mistakes);

        // check completion (no zero and no mismatch)
        let complete = true;
        outer:
        for (let r=0;r<9;r++) {
            for (let c=0;c<9;c++) {
                if (grid[r][c] === 0 || grid[r][c] !== solution[r][c]) {
                    complete = false;
                    break outer;
                }
            }
        }

        if (complete) {
            solved = true;
            alert('SUDOKU SOLVED!');
        } else {
            alert('CHECK HIGHLIGHTED CELLS.');
        }
    }

    btnReset.addEventListener('click', () => {
        mistakes = 0;
        mistakesEl.textContent = '0';
        solved = false;
        buildGrid();
    });

    btnValidate.addEventListener('click', validateSudoku);

    btnSave.addEventListener('click', async () => {
        if (!solved) {
            alert('SOLVE THE PUZZLE BEFORE SAVING.');
            return;
        }
        const name = document.getElementById('player-name').value.trim() || 'PLAYER';
        const mins = parseInt(document.getElementById('minutes').value,10) || 10;

        const payload = {
            playerName: name,
            minutes: mins,
            mistakes: mistakes,
            solved: true
        };

        try {
            const res = await fetch(location.pathname + '?finish=1', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.ok) {
                console.error(data);
                alert('FAILED TO SAVE GAME');
                return;
            }
            alert('GAME SAVED TO ARCADE DB');
            loadStats();
        } catch (e) {
            console.error(e);
            alert('ERROR WHILE SAVING');
        }
    });

    async function loadStats() {
        try {
            const res = await fetch(location.pathname + '?stats=1');
            const data = await res.json();
            const runList    = document.getElementById('run-list');
            const leaderList = document.getElementById('leader-list');
            runList.innerHTML = '';
            leaderList.innerHTML = '';

            if (!data.ok) {
                runList.innerHTML = '<li>FAILED TO LOAD</li>';
                leaderList.innerHTML = '<li>FAILED TO LOAD</li>';
                return;
            }

            if (!data.runs.length) {
                runList.innerHTML = '<li>NO GAMES YET</li>';
            } else {
                data.runs.forEach(row => {
                    const li = document.createElement('li');
                    li.textContent = `${row.result} | SCORE ${row.scores} | MINS ${row.durationminutes} | ${row.createdat}`;
                    runList.appendChild(li);
                });
            }

            if (!data.leaders.length) {
                leaderList.innerHTML = '<li>NO PLAYERS YET</li>';
            } else {
                data.leaders.forEach((row,i) => {
                    const li = document.createElement('li');
                    li.textContent = `#${i+1} PLAYERID ${row.playerid} | SCORE ${row.total}`;
                    leaderList.appendChild(li);
                });
            }
        } catch (e) {
            console.error(e);
        }
    }

    // init
    buildGrid();
    loadStats();
})();
</script>
</body>
</html>
