<?php
// ============== ERROR DISPLAY FOR DEV ==============
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============== DB CONNECTION (gamehub) ==============
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

// ============== ENSURE GAME + DEMO USER ==============
function ensure_chess_game(PDO $pdo): array {
    $title = 'Retro Chess Arena';

    // Users table in your dump looks like "users" with integer PK; use generic names. [file:1]
    $pdo->exec("
        INSERT IGNORE INTO users (userid, email, password)
        VALUES (2, 'chess_arcade@example.com', 'dummy')
    ");
    $userId = 2;

    // Games table: gameid, name, description, maxplayers, minplayers, isactive, createdat. [file:1]
    $stmt = $pdo->prepare("SELECT gameid FROM games WHERE name = :n LIMIT 1");
    $stmt->execute([':n' => $title]);
    $row = $stmt->fetch();

    if ($row) {
        $gameId = (int)$row['gameid'];
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO games (name, description, maxplayers, minplayers, isactive, createdat)
            VALUES (:n, :d, 2, 1, 1, NOW())
        ");
        $stmt->execute([
            ':n' => $title,
            ':d' => 'Tron-style retro chess with neon scoreboard',
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
        [$gameId, $userId, $title] = ensure_chess_game($pdo);

        // Last 5 matches from gamehistory. [file:1]
        $stmt = $pdo->prepare("
            SELECT result, scores, durationminutes, createdat
            FROM gamehistory
            WHERE gameid = :g
            ORDER BY createdat DESC
            LIMIT 5
        ");
        $stmt->execute([':g' => $gameId]);
        $matches = $stmt->fetchAll();

        // Top 5 leaderboard by total scores per player. [file:1][web:66]
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

        echo json_encode(['ok' => true, 'matches' => $matches, 'leaders' => $leaders]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============== API: POST ?move=1 (server-side state, optional) ==============
// For simplicity, all move logic is handled in JS in this version, so this endpoint is omitted.
// If you later want server-validated moves, you can add a similar handler like in the Snake example. [web:121]

// ============== API: POST ?finish=1 (save match) ==============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['finish'])) {
    header('Content-Type: application/json; charset=utf-8');

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $winner    = $data['winner'] ?? 'draw';      // 'white','black','draw'
    $minutes   = (int)($data['minutes'] ?? 0);   // duration in minutes
    $moves     = (int)($data['moves']   ?? 0);   // total half-moves
    $note      = trim($data['note'] ?? '');
    $whiteName = trim($data['whiteName'] ?? 'WHITE');
    $blackName = trim($data['blackName'] ?? 'BLACK');
    $mode      = trim($data['mode'] ?? 'local');

    if (!in_array($winner, ['white','black','draw'], true) || $moves <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid payload']);
        exit;
    }

    try {
        $pdo = get_pdo();
        [$gameId, $userId, $title] = ensure_chess_game($pdo);

        // gamesessions: sessionid, gameid, hostplayerid, status, ... [file:1]
        $stmt = $pdo->prepare("
            INSERT INTO gamesessions (
                gameid, hostplayerid, status, maxplayers, currentplayers,
                isprivate, starttime, createdat
            )
            VALUES (:g, :u, 'completed', 2, 2, 0, NOW(), NOW())
        ");
        $stmt->execute([':g' => $gameId, ':u' => $userId]);
        $sessionId = (int)$pdo->lastInsertId();

        $vsText = "White: {$whiteName} vs Black: {$blackName}";
        $resultText = strtoupper($winner) . ' WINS';
        if ($winner === 'draw') $resultText = 'DRAW';
        if ($note !== '') $resultText .= " - {$note}";
        $resultText .= " | {$vsText} | MODE: {$mode}";

        $score = ($winner === 'draw') ? 0.5 : 1.0;

        // gamehistory: historyid, gameid, sessionid, playerid, result, scores, durationminutes, createdat. [file:1]
        $stmt = $pdo->prepare("
            INSERT INTO gamehistory (
                gameid, sessionid, playerid, result, scores, durationminutes, createdat
            )
            VALUES (:g, :s, :p, :r, :sc, :dur, NOW())
        ");
        $stmt->execute([
            ':g'   => $gameId,
            ':s'   => $sessionId,
            ':p'   => $userId,
            ':r'   => $resultText,
            ':sc'  => $score,
            ':dur' => $moves,
        ]);

        // leaderboards: leaderboardid, gameid, playerid, scores, levelreached, ... [file:1]
        $stmt = $pdo->prepare("
            INSERT INTO leaderboards (
                gameid, playerid, scores, levelreached, timeplayedminutes,
                achievementsunlocked, lastplayed, bestscoredate, updatedat
            )
            VALUES (:g, :p, :sc, 1, :mins, 0, NOW(), NOW(), NOW())
        ");
        $stmt->execute([
            ':g'    => $gameId,
            ':p'    => $userId,
            ':sc'   => $score,
            ':mins' => $minutes,
        ]);

        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============== FALL THROUGH: HTML + JS UI ==============
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Retro Chess Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<style>
body {
    background: radial-gradient(circle at top, #061018 0, #020308 60%);
    color: #00f5ff;
    font-family: 'Press Start 2P', system-ui, sans-serif;
    display: flex;
    flex-direction: row;
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
#board-wrapper {
    perspective: 1200px;
}
#board {
    display: grid;
    grid-template-columns: repeat(8, 64px);
    grid-template-rows: repeat(8, 64px);
    border: 3px solid #00f5ff;
    box-shadow: 0 0 30px #00f5ff;
    background: radial-gradient(circle at center, #050910 0, #020308 80%);
    transition: transform 0.6s ease;
    transform-style: preserve-3d;
}
#board.rotating {
    transform: rotateY(360deg);
}
.square {
    width: 64px;
    height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30px;
    cursor: pointer;
    border: 1px solid rgba(0, 245, 255, 0.15);
    transition: background 0.1s, box-shadow 0.1s;
}
.dark  { background: #071824; }
.light { background: #0b2233; }
.selected    { box-shadow: inset 0 0 0 3px #ff00ff; }
.move-target { box-shadow: inset 0 0 0 3px #ffae00; }
.panel {
    border: 2px solid #00f5ff;
    box-shadow: 0 0 16px #00f5ff;
    padding: 10px;
    max-width: 280px;
    font-size: 11px;
    color: #e5ffff;
    background: rgba(2, 5, 12, 0.9);
}
button {
    background: #020308;
    color: #ff00e6;
    border: 2px solid #ff00e6;
    padding: 6px 8px;
    cursor: pointer;
    margin: 2px 0;
    text-transform: uppercase;
    font-size: 10px;
}
button:hover {
    background: #ff00e6;
    color: #020308;
}
input, select {
    background: #020308;
    color: #00f5ff;
    border: 1px solid #00f5ff;
    padding: 3px;
    margin: 3px 0;
    width: 100%;
    box-sizing: border-box;
    font-family: inherit;
    font-size: 10px;
}
h2, h3 {
    margin: 4px 0;
    text-shadow: 0 0 8px #00f5ff;
    font-size: 14px;
}
ul {
    padding-left: 18px;
    font-size: 10px;
}
label {
    font-size: 10px;
}
#status {
    margin-top: 8px;
    font-size: 10px;
    color: #ffae00;
}
</style>
</head>
<body>
<div id="game-container">
    <h2>Retro Chess Arena</h2>
    <div id="board-wrapper">
        <div id="board"></div>
    </div>
    <div id="status">TURN: WHITE</div>
</div>

<div class="panel">
    <h3>MODE / NAMES</h3>
    <label>
        TYPE:
        <select id="mode">
            <option value="local">LOCAL 2P</option>
        </select>
    </label>
    <label>
        WHITE NAME:
        <input type="text" id="white-name" placeholder="PLAYER 1">
    </label>
    <label>
        BLACK NAME:
        <input type="text" id="black-name" placeholder="PLAYER 2">
    </label>
    <button id="btn-new">NEW GAME</button>

    <h3 style="margin-top:10px;">MATCH RESULT</h3>
    <p id="match-state">IN PROGRESS</p>
    <label>
        WINNER:
        <select id="winner">
            <option value="white">WHITE</option>
            <option value="black">BLACK</option>
            <option value="draw">DRAW</option>
        </select>
    </label>
    <label>
        MOVES (PLIES):
        <input type="number" id="moves" min="1" value="1">
    </label>
    <label>
        MINUTES:
        <input type="number" id="minutes" min="1" value="10">
    </label>
    <label>
        NOTE:
        <input type="text" id="note" placeholder="CHECKMATE, RESIGN, TIMEOUT...">
    </label>
    <button id="btn-save">SAVE MATCH</button>

    <h3 style="margin-top:10px;">LAST 5 MATCHES</h3>
    <ul id="match-list"></ul>

    <h3 style="margin-top:10px;">TOP 5 LEADERBOARD</h3>
    <ul id="leader-list"></ul>

    <h3 style="margin-top:10px;">LEGEND</h3>
    <p>WHITE: ♔ ♕ ♖ ♗ ♘ ♙</p>
    <p>BLACK: ♚ ♛ ♜ ♝ ♞ ♟</p>
</div>

<script>
(function() {
    const boardEl  = document.getElementById('board');
    const wrapper  = document.getElementById('board-wrapper');
    const statusEl = document.getElementById('status');

    // boardState[r][c] = null or {c:'white'|'black', t:'k','q','r','b','n','p'}
    let boardState = [];
    let turn = 'white';
    let selected = null;
    let moveCount = 0;
    let gameOver = false;

    function initialBoard() {
        const emptyRow = Array(8).fill(null);
        const board = [];
        board.push([
            {c:'black', t:'r'},
            {c:'black', t:'n'},
            {c:'black', t:'b'},
            {c:'black', t:'q'},
            {c:'black', t:'k'},
            {c:'black', t:'b'},
            {c:'black', t:'n'},
            {c:'black', t:'r'},
        ]);
        board.push(Array(8).fill({c:'black', t:'p'}));
        board.push([...emptyRow]);
        board.push([...emptyRow]);
        board.push([...emptyRow]);
        board.push([...emptyRow]);
        board.push(Array(8).fill({c:'white', t:'p'}));
        board.push([
            {c:'white', t:'r'},
            {c:'white', t:'n'},
            {c:'white', t:'b'},
            {c:'white', t:'q'},
            {c:'white', t:'k'},
            {c:'white', t:'b'},
            {c:'white', t:'n'},
            {c:'white', t:'r'},
        ]);
        return board;
    }

    function glyph(piece) {
        if (!piece) return '';
        const c = piece.c, t = piece.t;
        if (c === 'white') {
            if (t === 'k') return '♔';
            if (t === 'q') return '♕';
            if (t === 'r') return '♖';
            if (t === 'b') return '♗';
            if (t === 'n') return '♘';
            if (t === 'p') return '♙';
        } else {
            if (t === 'k') return '♚';
            if (t === 'q') return '♛';
            if (t === 'r') return '♜';
            if (t === 'b') return '♝';
            if (t === 'n') return '♞';
            if (t === 'p') return '♟';
        }
        return '';
    }

    let squares = [];

    function renderBoard() {
        boardEl.innerHTML = '';
        squares = [];
        for (let r = 0; r < 8; r++) {
            for (let c = 0; c < 8; c++) {
                const sq = document.createElement('div');
                sq.classList.add('square');
                const dark = (r + c) % 2 === 1;
                sq.classList.add(dark ? 'dark' : 'light');
                sq.dataset.row = r;
                sq.dataset.col = c;
                sq.textContent = glyph(boardState[r][c]);
                sq.addEventListener('click', () => onSquareClick(r, c));
                squares.push(sq);
                boardEl.appendChild(sq);
            }
        }
    }

    function clearHighlights() {
        squares.forEach(sq => sq.classList.remove('selected', 'move-target'));
    }

    function generateMoves(piece, r, c) {
        const moves = [];
        const dir = piece.c === 'white' ? -1 : 1;

        function inside(rr, cc) {
            return rr >= 0 && rr < 8 && cc >= 0 && cc < 8;
        }
        function slide(drs, dcs) {
            for (let i = 0; i < drs.length; i++) {
                const dr = drs[i];
                const dc = dcs[i];
                let rr = r + dr;
                let cc = c + dc;
                while (inside(rr, cc)) {
                    const t = boardState[rr][cc];
                    if (!t) {
                        moves.push({r: rr, c: cc});
                    } else {
                        if (t.c !== piece.c) moves.push({r: rr, c: cc});
                        break;
                    }
                    rr += dr;
                    cc += dc;
                }
            }
        }

        switch (piece.t) {
            case 'p': {
                const fr = r + dir;
                if (inside(fr, c) && !boardState[fr][c]) {
                    moves.push({r: fr, c: c});
                    const startRank = piece.c === 'white' ? 6 : 1;
                    const fr2 = r + 2 * dir;
                    if (r === startRank && !boardState[fr2][c]) {
                        moves.push({r: fr2, c: c});
                    }
                }
                [[dir,-1],[dir,1]].forEach(([dr, dc]) => {
                    const rr = r + dr;
                    const cc = c + dc;
                    if (!inside(rr, cc)) return;
                    const t = boardState[rr][cc];
                    if (t && t.c !== piece.c) moves.push({r: rr, c: cc});
                });
                break;
            }
            case 'n': {
                const knightMoves = [
                    [2,1],[2,-1],[-2,1],[-2,-1],
                    [1,2],[1,-2],[-1,2],[-1,-2]
                ];
                knightMoves.forEach(([dr, dc]) => {
                    const rr = r + dr;
                    const cc = c + dc;
                    if (rr < 0 || rr > 7 || cc < 0 || cc > 7) return;
                    const t = boardState[rr][cc];
                    if (!t || t.c !== piece.c) moves.push({r: rr, c: cc});
                });
                break;
            }
            case 'b':
                slide([1,1,1,-1,-1,-1,1,-1], [1,-1,1,1,-1,-1,-1,1]);
                break;
            case 'r':
                slide([1,-1,0,0], [0,0,1,-1]);
                break;
            case 'q':
                slide([1,-1,0,0,1,1,-1,-1], [0,0,1,-1,1,-1,1,-1]);
                break;
            case 'k': {
                const kingMoves = [
                    [1,0],[-1,0],[0,1],[0,-1],
                    [1,1],[1,-1],[-1,1],[-1,-1]
                ];
                kingMoves.forEach(([dr, dc]) => {
                    const rr = r + dr;
                    const cc = c + dc;
                    if (rr < 0 || rr > 7 || cc < 0 || cc > 7) return;
                    const t = boardState[rr][cc];
                    if (!t || t.c !== piece.c) moves.push({r: rr, c: cc});
                });
                break;
            }
        }

        return moves;
    }

    function onSquareClick(r, c) {
        if (gameOver) return;
        const piece = boardState[r][c];

        if (!selected) {
            if (!piece || piece.c !== turn) return;
            selected = {r, c};
            clearHighlights();
            const idx = r * 8 + c;
            squares[idx].classList.add('selected');
            const moves = generateMoves(piece, r, c);
            moves.forEach(m => {
                const id = m.r * 8 + m.c;
                squares[id].classList.add('move-target');
            });
            return;
        }

        if (selected.r === r && selected.c === c) {
            selected = null;
            clearHighlights();
            return;
        }

        const idx = r * 8 + c;
        const isTarget = squares[idx].classList.contains('move-target');
        if (!isTarget) {
            if (piece && piece.c === turn) {
                selected = {r, c};
                clearHighlights();
                squares[idx].classList.add('selected');
                const moves = generateMoves(piece, r, c);
                moves.forEach(m => {
                    const id = m.r * 8 + m.c;
                    squares[id].classList.add('move-target');
                });
            } else {
                selected = null;
                clearHighlights();
            }
            return;
        }

        const fromPiece = boardState[selected.r][selected.c];
        const target    = boardState[r][c];

        boardState[r][c] = fromPiece;
        boardState[selected.r][selected.c] = null;

        if (fromPiece.t === 'p') {
            if (fromPiece.c === 'white' && r === 0) boardState[r][c] = {c:'white', t:'q'};
            if (fromPiece.c === 'black' && r === 7) boardState[r][c] = {c:'black', t:'q'};
        }

        moveCount++;
        if (target && target.t === 'k') {
            gameOver = true;
            statusEl.textContent = 'GAME OVER – ' + fromPiece.c.toUpperCase() + ' WINS';
        } else {
            turn = (turn === 'white') ? 'black' : 'white';
            statusEl.textContent = 'TURN: ' + turn.toUpperCase();
        }

        selected = null;
        clearHighlights();
        renderBoard();
    }

    document.getElementById('btn-new').addEventListener('click', () => {
        wrapper.classList.add('rotating');
        setTimeout(() => wrapper.classList.remove('rotating'), 600);
        boardState = initialBoard();
        turn = 'white';
        moveCount = 0;
        gameOver = false;
        statusEl.textContent = 'TURN: WHITE';
        renderBoard();
    });

    document.getElementById('btn-save').addEventListener('click', async () => {
        const winner    = document.getElementById('winner').value;
        const minutes   = parseInt(document.getElementById('minutes').value, 10) || 1;
        const note      = document.getElementById('note').value.trim();
        const whiteName = document.getElementById('white-name').value.trim() || 'WHITE';
        const blackName = document.getElementById('black-name').value.trim() || 'BLACK';
        const mode      = document.getElementById('mode').value;

        const movesInput = document.getElementById('moves');
        if (!movesInput.value || parseInt(movesInput.value,10) <= 0) {
            movesInput.value = String(moveCount || 1);
        }
        const movesVal = parseInt(movesInput.value, 10) || 1;

        const payload = { winner, minutes, note, whiteName, blackName, mode, moves: movesVal };

        try {
            const res = await fetch(location.pathname + '?finish=1', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.ok) {
                console.error(data);
                alert('FAILED TO SAVE MATCH');
                return;
            }
            alert('MATCH SAVED');
            gameOver = true;
            document.getElementById('match-state').textContent = 'SAVED TO ARCADE DB';
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
            const matchList  = document.getElementById('match-list');
            const leaderList = document.getElementById('leader-list');
            matchList.innerHTML = '';
            leaderList.innerHTML = '';

            if (!data.ok) {
                matchList.innerHTML = '<li>FAILED TO LOAD</li>';
                leaderList.innerHTML = '<li>FAILED TO LOAD</li>';
                return;
            }

            if (!data.matches.length) {
                matchList.innerHTML = '<li>NO MATCHES YET</li>';
            } else {
                data.matches.forEach(row => {
                    const li = document.createElement('li');
                    li.textContent = `${row.result} | SCORE ${row.scores} | MOVES ${row.durationminutes} | ${row.createdat}`;
                    matchList.appendChild(li);
                });
            }

            if (!data.leaders.length) {
                leaderList.innerHTML = '<li>NO ENTRIES YET</li>';
            } else {
                data.leaders.forEach((row, i) => {
                    const li = document.createElement('li');
                    li.textContent = `#${i+1} PLAYERID ${row.playerid} | SCORE ${row.total}`;
                    leaderList.appendChild(li);
                });
            }
        } catch (e) {
            console.error(e);
        }
    }

    boardState = initialBoard();
    renderBoard();
    loadStats();
})();
</script>
</body>
</html>
