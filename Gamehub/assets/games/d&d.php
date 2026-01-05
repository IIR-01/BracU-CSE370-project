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
function ensure_neon_dnd(PDO $pdo): array {
    $title = 'Neon D&D Board';

    // demo user (unique id)
    $pdo->exec("
        INSERT IGNORE INTO users (userid, email, password)
        VALUES (7, 'neon_dnd@example.com', 'dummy')
    ");
    $userId = 7;

    // games uses `names`, `descriptions`, `category`, `isactives` [file:1]
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
            ':d' => 'Grid‑based Neon Dungeons & Dragons combat: move, attack, loot, escape.',
            ':c' => 'Board',
        ]);
        $gameId = (int)$pdo->lastInsertId();
    }

    return [$gameId, $userId, $title];
}

// ============== API: GET ?stats=1 ==============
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stats'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $pdo = get_pdo();
        [$gameId, $userId, $title] = ensure_neon_dnd($pdo);

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

// ============== API: POST ?finish=1 ==============
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['finish'])) {
    header('Content-Type: application/json; charset=utf-8');

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $playerName  = trim($data['playerName'] ?? 'HERO');
    $result      = $data['result'] ?? '';        // 'win','death'
    $turns       = (int)($data['turns'] ?? 1);
    $kills       = (int)($data['kills'] ?? 0);
    $damageTaken = (int)($data['damageTaken'] ?? 0);
    $minutes     = (int)($data['minutes'] ?? 5);

    if (!in_array($result, ['win','death'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid result']);
        exit;
    }

    try {
        $pdo = get_pdo();
        [$gameId, $userId, $title] = ensure_neon_dnd($pdo);

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

        // Arcade score: kills*40 + (result win ? 200 : 0) - damageTaken - turns
        $score = $kills * 40 + ($result === 'win' ? 200 : 0) - $damageTaken - $turns;
        if ($score < 0) $score = 0;

        $detail = sprintf(
            '%s: %s | TURNS %d | KILLS %d | DMG %d',
            $playerName,
            strtoupper($result),
            $turns,
            $kills,
            $damageTaken
        );

        // gamehistory [file:1]
        $stmt = $pdo->prepare("
            INSERT INTO gamehistory (
                gameid, sessionid, playerid, result, scores, experiencegained,
                durationminutes, createdat
            )
            VALUES (:g, :s, :p, :r, :sc, :exp, :dur, NOW())
        ");
        $exp = ($result === 'win') ? 35 : 15;
        $enumResult = ($result === 'win') ? 'win' : 'loss';
        $stmt->execute([
            ':g'   => $gameId,
            ':s'   => $sessionId,
            ':p'   => $userId,
            ':r'   => $enumResult,
            ':sc'  => $score,
            ':exp' => $exp,
            ':dur' => $minutes,
        ]);

        // leaderboards [file:1]
        $stmt = $pdo->prepare("
            INSERT INTO leaderboards (
                gameid, playerid, scores, levelreached, rankposition,
                completionpercentage, timeplayedminutes, achievementsunlocked,
                lastplayed, bestscoredate, updatedat
            )
            VALUES (:g, :p, :sc, :lvl, NULL, 0, :mins, :ach, NOW(), NOW(), NOW())
        ");
        $ach = ($result === 'win') ? 1 : 0;
        $stmt->execute([
            ':g'    => $gameId,
            ':p'    => $userId,
            ':sc'   => $score,
            ':lvl'  => $kills,
            ':mins' => $minutes,
            ':ach'  => $ach,
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
<title>Neon D&amp;D Board</title>
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
#board {
    display: grid;
    grid-template-columns: repeat(8, 56px);
    grid-template-rows: repeat(8, 56px);
    border: 3px solid #00A3E0;
    box-shadow: 0 0 30px #00A3E0;
    background: #010409;
}
.tile {
    width: 56px;
    height: 56px;
    box-sizing: border-box;
    border: 1px solid #021721;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
}
.floor {
    background: radial-gradient(circle at center, #031821 0, #010409 80%);
}
.wall {
    background: #020913;
}
.player {
    color: #00FF7F;
    text-shadow: 0 0 6px #00FF7F;
}
.monster {
    color: #FF6C2D;
    text-shadow: 0 0 6px #FF6C2D;
}
.exit {
    color: #A500FF;
    text-shadow: 0 0 6px #A500FF;
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
#log {
    margin-top: 8px;
    height: 90px;
    overflow-y: auto;
    font-size: 9px;
    border-top: 1px solid #00A3E0;
    padding-top: 4px;
}
</style>
</head>
<body>
<div id="game-container">
    <h2>Neon D&amp;D Board ⚔️</h2>
    <div id="board"></div>
    <div id="stats">
        HP ❤️: <span id="hp">24</span> |
        ATK ⚔️: <span id="atk">4</span> |
        KILLS 💀: <span id="kills">0</span> |
        DMG 💢: <span id="dmg">0</span> |
        TURNS ⏱️: <span id="turns">0</span>
    </div>
    <div style="margin-top:6px;font-size:9px;">MOVE: WASD / ARROWS • ATTACK WHEN YOU STEP ON M</div>
    <div id="log"></div>
</div>

<div class="panel">
    <h3>HERO & SAVE 🧙‍♂️</h3>
    <label>
        HERO NAME:
        <input type="text" id="player-name" class="info" placeholder="NEON HERO">
    </label>
    <label>
        MINUTES:
        <input type="number" id="minutes" class="info" min="1" value="5">
    </label>
    <button id="btn-new">NEW QUEST</button>
    <button id="btn-save">SAVE QUEST</button>

    <h3 style="margin-top:10px;">LAST 5 QUESTS</h3>
    <ul id="run-list"></ul>

    <h3 style="margin-top:10px;">TOP 5 HEROES 🏆</h3>
    <ul id="leader-list"></ul>

    <h3 style="margin-top:10px;">SYMBOLS</h3>
    <ul>
        <li>@ You</li>
        <li>M Monster</li>
        <li>Ω Exit Portal</li>
        <li># Wall</li>
    </ul>
</div>

<script>
(function() {
    const boardEl = document.getElementById('board');
    const hpEl    = document.getElementById('hp');
    const atkEl   = document.getElementById('atk');
    const killsEl = document.getElementById('kills');
    const dmgEl   = document.getElementById('dmg');
    const turnsEl = document.getElementById('turns');
    const logEl   = document.getElementById('log');

    const btnNew  = document.getElementById('btn-new');
    const btnSave = document.getElementById('btn-save');

    const size = 8;
    let grid = [];
    let player = {x:0, y:0, hp:24, atk:4};
    let monsters = [];
    let exitPos = {x:size-1, y:size-1};
    let kills = 0;
    let damageTaken = 0;
    let turns = 0;
    let gameOver = false;
    let lastResult = null; // 'win' or 'death'

    function log(msg) {
        const line = document.createElement('div');
        line.textContent = msg;
        logEl.appendChild(line);
        logEl.scrollTop = logEl.scrollHeight;
    }

    function randInt(min, max) {
        return Math.floor(Math.random() * (max - min + 1)) + min;
    }

    function d20() { return randInt(1,20); }
    function d6()  { return randInt(1,6); }

    function genBoard() {
        grid = [];
        monsters = [];
        exitPos = {x:size-1, y:size-1};

        for (let y=0;y<size;y++) {
            const row = [];
            for (let x=0;x<size;x++) row.push('.');
            grid.push(row);
        }

        // walls
        for (let i=0;i<8;i++) {
            const wx = randInt(0,size-1);
            const wy = randInt(0,size-1);
            if ((wx===0 && wy===0) || (wx===exitPos.x && wy===exitPos.y)) continue;
            grid[wy][wx] = '#';
        }

        // monsters
        for (let i=0;i<5;i++) {
            let x,y;
            do {
                x = randInt(0,size-1);
                y = randInt(0,size-1);
            } while (
                grid[y][x] === '#' ||
                (x===0 && y===0) ||
                (x===exitPos.x && y===exitPos.y)
            );
            monsters.push({x,y,hp:10,ac:11,atkBonus:3, dmgDice:6});
        }

        player.x = 0;
        player.y = 0;
        player.hp = 24;
        player.atk = 4;
        kills = 0;
        damageTaken = 0;
        turns = 0;
        gameOver = false;
        lastResult = null;
        logEl.innerHTML = '';
        log('A NEW NEON QUEST BEGINS. ✨');
        render();
    }

    function render() {
        boardEl.innerHTML = '';
        for (let y=0;y<size;y++) {
            for (let x=0;x<size;x++) {
                const div = document.createElement('div');
                div.classList.add('tile');
                if (grid[y][x] === '#') div.classList.add('wall');
                else div.classList.add('floor');

                if (x===player.x && y===player.y) {
                    div.textContent = '@';
                    div.classList.add('player');
                } else if (monsters.some(m => m.x===x && m.y===y && m.hp>0)) {
                    div.textContent = 'M';
                    div.classList.add('monster');
                } else if (x===exitPos.x && y===exitPos.y) {
                    div.textContent = 'Ω';
                    div.classList.add('exit');
                }

                boardEl.appendChild(div);
            }
        }

        hpEl.textContent    = String(player.hp);
        atkEl.textContent   = String(player.atk);
        killsEl.textContent = String(kills);
        dmgEl.textContent   = String(damageTaken);
        turnsEl.textContent = String(turns);
    }

    function move(dx, dy) {
        if (gameOver) return;

        const nx = player.x + dx;
        const ny = player.y + dy;
        if (nx<0 || ny<0 || nx>=size || ny>=size) return;
        if (grid[ny][nx] === '#') {
            log('WALL BLOCKS YOUR PATH. 🧱');
            return;
        }
        turns++;

        const monster = monsters.find(m => m.x===nx && m.y===ny && m.hp>0);
        if (monster) {
            // D&D‑flavored: roll to hit vs AC, then damage [web:229]
            const heroRoll = d20() + player.atk;
            log('YOU ROLL TO HIT: ' + heroRoll + ' vs AC ' + monster.ac + '.');
            if (heroRoll >= monster.ac) {
                const dmg = d6() + 2;
                monster.hp -= dmg;
                log('HIT! YOU DEAL ' + dmg + ' DAMAGE. 💥');
                if (monster.hp <= 0) {
                    kills++;
                    log('MONSTER FALLS. 💀');
                    player.x = nx;
                    player.y = ny;
                }
            } else {
                log('YOU MISS. 😵');
            }

            if (monster.hp > 0) {
                const monRoll = d20() + monster.atkBonus;
                log('MONSTER STRIKES: ' + monRoll + ' vs YOUR AC 12.');
                if (monRoll >= 12) {
                    const mdmg = randInt(1, monster.dmgDice);
                    player.hp -= mdmg;
                    damageTaken += mdmg;
                    log('YOU TAKE ' + mdmg + ' DAMAGE. 💢');
                } else {
                    log('MONSTER MISSES. 😅');
                }
            }
        } else {
            player.x = nx;
            player.y = ny;
        }

        if (player.x===exitPos.x && player.y===exitPos.y) {
            gameOver = true;
            lastResult = 'win';
            log('YOU REACH THE NEON PORTAL. VICTORY! 🏆');
        }

        if (player.hp <= 0) {
            gameOver = true;
            lastResult = 'death';
            log('YOU FALL IN THE DUNGEON. ☠️');
        }

        render();
    }

    document.addEventListener('keydown', (e) => {
        switch (e.key) {
            case 'ArrowUp':
            case 'w':
            case 'W':
                move(0,-1); break;
            case 'ArrowDown':
            case 's':
            case 'S':
                move(0,1); break;
            case 'ArrowLeft':
            case 'a':
            case 'A':
                move(-1,0); break;
            case 'ArrowRight':
            case 'd':
            case 'D':
                move(1,0); break;
        }
    });

    btnNew.addEventListener('click', genBoard);

    btnSave.addEventListener('click', async () => {
        if (!gameOver) {
            alert('FINISH THE QUEST (WIN OR DIE) BEFORE SAVING. ⚠️');
            return;
        }
        const name = document.getElementById('player-name').value.trim() || 'HERO';
        const mins = parseInt(document.getElementById('minutes').value,10) || 5;

        const payload = {
            playerName:  name,
            result:      lastResult,
            turns:       turns,
            kills:       kills,
            damageTaken: damageTaken,
            minutes:     mins
        };

        try {
            const res = await fetch(location.pathname + '?finish=1', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.ok) {
                console.error(data);
                alert('FAILED TO SAVE QUEST. 💣');
                return;
            }
            alert('QUEST SAVED TO ARCADE DB. 💾');
            loadStats();
        } catch (err) {
            console.error(err);
            alert('ERROR WHILE SAVING. 🔧');
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
                runList.innerHTML = '<li>NO QUESTS YET</li>';
            } else {
                data.runs.forEach(row => {
                    const li = document.createElement('li');
                    li.textContent = `${row.result} | SCORE ${row.scores} | MINS ${row.durationminutes} | ${row.createdat}`;
                    runList.appendChild(li);
                });
            }

            if (!data.leaders.length) {
                leaderList.innerHTML = '<li>NO HEROES YET</li>';
            } else {
                data.leaders.forEach((row,i) => {
                    const li = document.createElement('li');
                    li.textContent = `#${i+1} PLAYERID ${row.playerid} | SCORE ${row.total}`;
                    leaderList.appendChild(li);
                });
            }
        } catch (err) {
            console.error(err);
        }
    }

    // init
    genBoard();
    loadStats();
})();
</script>
</body>
</html>
