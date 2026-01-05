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
function ensure_cards_game(PDO $pdo): array {
    $title = 'Neon Blackjack Arena'; // name in games table

    // simple demo user (userid=3 reserved for cards)
    $pdo->exec("
        INSERT IGNORE INTO users (userid, email, password)
        VALUES (3, 'cards_arcade@example.com', 'dummy')
    ");
    $userId = 3;

    // games table is `games` (gameid, name, descriptions, ...). [file:1]
    $stmt = $pdo->prepare("SELECT gameid FROM games WHERE name = :n LIMIT 1");
    $stmt->execute([':n' => $title]);
    $row = $stmt->fetch();

    if ($row) {
        $gameId = (int)$row['gameid'];
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO games (name, descriptions, category, maxplayers, minplayers, isactives, createdat)
            VALUES (:n, :d, :c, 1, 1, 1, NOW())
        ");
        $stmt->execute([
            ':n' => $title,
            ':d' => 'Retro Tron-style Blackjack vs Dealer, 21 wins.',
            ':c' => 'Cards',
        ]);
        $gameId = (int)$pdo->lastInsertId();
    }

    return [$gameId, $userId, $title];
}

// ============== API: GET ?stats=1 (last 5 rounds + leaderboard) ==============
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stats'])) {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $pdo = get_pdo();
        [$gameId, $userId, $title] = ensure_cards_game($pdo);

        // last 5 rounds from gamehistory. [file:1]
        $stmt = $pdo->prepare("
            SELECT result, scores, durationminutes, createdat
            FROM gamehistory
            WHERE gameid = :g
            ORDER BY createdat DESC
            LIMIT 5
        ");
        $stmt->execute([':g' => $gameId]);
        $rounds = $stmt->fetchAll();

        // top 5 for this game from leaderboards (sum of scores). [file:1]
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

        echo json_encode(['ok' => true, 'rounds' => $rounds, 'leaders' => $leaders]);
    } catch (PDOException $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============== API: POST ?finish=1 (save finished round) ==============
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
    $result     = $data['result'] ?? '';      // 'win','loss','draw'
    $score      = (int)($data['score'] ?? 0); // player total or points
    $dealer     = (int)($data['dealer'] ?? 0);
    $rounds     = (int)($data['rounds'] ?? 1);  // how many hits
    $minutes    = (int)($data['minutes'] ?? 1);

    if (!in_array($result, ['win','loss','draw'], true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid result']);
        exit;
    }

    try {
        $pdo = get_pdo();
        [$gameId, $userId, $title] = ensure_cards_game($pdo);

        // gamesessions (sessionid, gameid, hostplayerid, ...). [file:1]
        $stmt = $pdo->prepare("
            INSERT INTO gamesessions (
                gameid, hostplayerid, status, maxplayers, currentplayers,
                isprivates, starttimes, createdat
            )
            VALUES (:g, :u, 'completed', 1, 1, 0, NOW(), NOW())
        ");
        $stmt->execute([
            ':g' => $gameId,
            ':u' => $userId
        ]);
        $sessionId = (int)$pdo->lastInsertId();

        // gamehistory (historyid, gameid, sessionid, playerid, result, scores, durationminutes). [file:1]
        $detail = sprintf(
            '%s: %s | PLAYER %d vs DEALER %d | HITS %d',
            $playerName,
            strtoupper($result),
            $score,
            $dealer,
            $rounds
        );

        $stmt = $pdo->prepare("
            INSERT INTO gamehistory (
                gameid, sessionid, playerid, result, scores, experiencegained,
                durationminutes, createdat
            )
            VALUES (:g, :s, :p, :r, :sc, :exp, :dur, NOW())
        ");
        $expGain = ($result === 'win') ? 10 : (($result === 'draw') ? 5 : 2);
        $stmt->execute([
            ':g'   => $gameId,
            ':s'   => $sessionId,
            ':p'   => $userId,
            ':r'   => $result,
            ':sc'  => $score,
            ':exp' => $expGain,
            ':dur' => $minutes,
        ]);

        // leaderboards (gameid, playerid, scores, ...). [file:1]
        $lbScore = ($result === 'win') ? 2 : (($result === 'draw') ? 1 : 0);
        $stmt = $pdo->prepare("
            INSERT INTO leaderboards (
                gameid, playerid, scores, levelreached, rankpositions,
                completionpercentage, timeplayedminutes, achievementsunlocked,
                lastplayed, bestscoredate, updatedat
            )
            VALUES (:g, :p, :sc, 1, NULL, 0, :mins, 0, NOW(), NOW(), NOW())
        ");
        $stmt->execute([
            ':g'    => $gameId,
            ':p'    => $userId,
            ':sc'   => $lbScore,
            ':mins' => $minutes,
        ]);

        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ============== FRONTEND: HTML + JS (Tron Blackjack) ==============
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Neon Blackjack Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
<style>
body {
    background: radial-gradient(circle at top, #041019 0, #020308 60%);
    color: #00f5ff;
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
    text-shadow: 0 0 10px #00f5ff;
    font-size: 14px;
}
#table {
    border: 3px solid #00f5ff;
    box-shadow: 0 0 30px #00f5ff;
    padding: 16px;
    border-radius: 10px;
    background: radial-gradient(circle at center, #05101b 0, #020308 80%);
    width: 420px;
}
.row {
    margin-bottom: 10px;
}
.label {
    font-size: 10px;
    margin-bottom: 4px;
    color: #b0f9ff;
}
.cards {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    min-height: 60px;
}
.card {
    width: 44px;
    height: 60px;
    border-radius: 6px;
    border: 2px solid #00f5ff;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #020308;
    box-shadow: 0 0 12px #00f5ff;
    font-size: 16px;
}
.card.red {
    color: #ff6cab;
}
.card.black {
    color: #ffffff;
}
#controls {
    margin-top: 12px;
    display: flex;
    justify-content: center;
    gap: 8px;
}
button {
    background: #020308;
    color: #ff00e6;
    border: 2px solid #ff00e6;
    padding: 6px 10px;
    cursor: pointer;
    text-transform: uppercase;
    font-size: 10px;
}
button:hover {
    background: #ff00e6;
    color: #020308;
}
.panel {
    border: 2px solid #00f5ff;
    box-shadow: 0 0 16px #00f5ff;
    padding: 10px;
    max-width: 260px;
    font-size: 11px;
    color: #e5ffff;
    background: rgba(2, 5, 12, 0.9);
}
input {
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
ul {
    padding-left: 18px;
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
    <h2>Neon Blackjack Arena</h2>
    <div id="table">
        <div class="row">
            <div class="label">DEALER</div>
            <div class="cards" id="dealer-cards"></div>
            <div class="label">TOTAL: <span id="dealer-total">0</span></div>
        </div>
        <hr style="border:1px solid #00f5ff; opacity:0.3; margin:8px 0;">
        <div class="row">
            <div class="label">PLAYER</div>
            <div class="cards" id="player-cards"></div>
            <div class="label">TOTAL: <span id="player-total">0</span></div>
        </div>
        <div id="controls">
            <button id="btn-new">DEAL</button>
            <button id="btn-hit">HIT</button>
            <button id="btn-stand">STAND</button>
        </div>
        <div id="status">PRESS DEAL TO START</div>
    </div>
</div>

<div class="panel">
    <h3>PLAYER & SAVE</h3>
    <label>
        NAME:
        <input type="text" id="player-name" placeholder="TYPE NAME">
    </label>
    <label>
        ROUND MINUTES:
        <input type="number" id="minutes" min="1" value="1">
    </label>
    <button id="btn-save">SAVE RESULT</button>

    <h3 style="margin-top:10px;">LAST 5 ROUNDS</h3>
    <ul id="round-list"></ul>

    <h3 style="margin-top:10px;">TOP 5 LEADERBOARD</h3>
    <ul id="leader-list"></ul>

    <h3 style="margin-top:10px;">RULES</h3>
    <ul>
        <li>Beat dealer without going over 21.</li>
        <li>Dealer hits to 17+, then stands.</li>
        <li>Aces count as 1 or 11.</li>
    </ul>
</div>

<script>
(function() {
    const dealerCardsEl = document.getElementById('dealer-cards');
    const playerCardsEl = document.getElementById('player-cards');
    const dealerTotalEl = document.getElementById('dealer-total');
    const playerTotalEl = document.getElementById('player-total');
    const statusEl      = document.getElementById('status');

    const btnNew   = document.getElementById('btn-new');
    const btnHit   = document.getElementById('btn-hit');
    const btnStand = document.getElementById('btn-stand');
    const btnSave  = document.getElementById('btn-save');

    let deck = [];
    let dealer = [];
    let player = [];
    let roundOver = false;
    let lastResult = null;   // 'win','loss','draw'
    let lastDealerTotal = 0;
    let lastPlayerTotal = 0;
    let hitCount = 0;

    function buildDeck() {
        const suits = ['♠','♥','♦','♣'];
        const ranks = ['A','2','3','4','5','6','7','8','9','10','J','Q','K'];
        const d = [];
        for (const s of suits) {
            for (const r of ranks) {
                d.push({rank:r, suit:s});
            }
        }
        // simple shuffle
        for (let i = d.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i+1));
            [d[i], d[j]] = [d[j], d[i]];
        }
        return d;
    }

    function value(hand) {
        let total = 0;
        let aces = 0;
        for (const card of hand) {
            const r = card.rank;
            if (r === 'A') {
                total += 11;
                aces++;
            } else if (['K','Q','J'].includes(r)) {
                total += 10;
            } else {
                total += parseInt(r, 10);
            }
        }
        while (total > 21 && aces > 0) {
            total -= 10;
            aces--;
        }
        return total;
    }

    function render() {
        dealerCardsEl.innerHTML = '';
        playerCardsEl.innerHTML = '';

        dealer.forEach((c, idx) => {
            const el = document.createElement('div');
            el.className = 'card ' + ((c.suit === '♥' || c.suit === '♦') ? 'red':'black');
            el.textContent = c.rank + c.suit;
            dealerCardsEl.appendChild(el);
        });

        player.forEach((c) => {
            const el = document.createElement('div');
            el.className = 'card ' + ((c.suit === '♥' || c.suit === '♦') ? 'red':'black');
            el.textContent = c.rank + c.suit;
            playerCardsEl.appendChild(el);
        });

        const dTotal = value(dealer);
        const pTotal = value(player);
        dealerTotalEl.textContent = String(dTotal);
        playerTotalEl.textContent = String(pTotal);
    }

    function newRound() {
        deck = buildDeck();
        dealer = [];
        player = [];
        roundOver = false;
        lastResult = null;
        hitCount = 0;

        player.push(deck.pop());
        dealer.push(deck.pop());
        player.push(deck.pop());
        dealer.push(deck.pop());

        render();
        statusEl.textContent = 'HIT OR STAND';
    }

    function hit() {
        if (roundOver || deck.length === 0) return;
        player.push(deck.pop());
        hitCount++;
        render();
        const pTotal = value(player);
        if (pTotal > 21) {
            endRound();
        }
    }

    function dealerPlay() {
        let dTotal = value(dealer);
        while (dTotal < 17 && deck.length > 0) {
            dealer.push(deck.pop());
            dTotal = value(dealer);
        }
    }

    function endRound() {
        if (roundOver) return;
        roundOver = true;

        dealerPlay();
        render();

        const dTotal = value(dealer);
        const pTotal = value(player);
        lastDealerTotal = dTotal;
        lastPlayerTotal = pTotal;

        if (pTotal > 21) {
            lastResult = 'loss';
            statusEl.textContent = 'BUST! YOU LOSE';
        } else if (dTotal > 21) {
            lastResult = 'win';
            statusEl.textContent = 'DEALER BUST! YOU WIN';
        } else if (pTotal > dTotal) {
            lastResult = 'win';
            statusEl.textContent = 'YOU WIN';
        } else if (pTotal < dTotal) {
            lastResult = 'loss';
            statusEl.textContent = 'YOU LOSE';
        } else {
            lastResult = 'draw';
            statusEl.textContent = 'PUSH (DRAW)';
        }
    }

    btnNew.addEventListener('click', newRound);
    btnHit.addEventListener('click', hit);
    btnStand.addEventListener('click', endRound);

    btnSave.addEventListener('click', async () => {
        if (!lastResult) {
            alert('PLAY A ROUND FIRST');
            return;
        }
        const name = document.getElementById('player-name').value.trim() || 'PLAYER';
        const mins = parseInt(document.getElementById('minutes').value, 10) || 1;

        const payload = {
            playerName: name,
            result: lastResult,
            score: lastPlayerTotal,
            dealer: lastDealerTotal,
            rounds: hitCount + 2, // rough action count
            minutes: mins
        };

        try {
            const res = await fetch(location.pathname + '?finish=1', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.ok) {
                console.error(data);
                alert('FAILED TO SAVE');
                return;
            }
            alert('ROUND SAVED TO ARCADE DB');
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
            const roundList  = document.getElementById('round-list');
            const leaderList = document.getElementById('leader-list');
            roundList.innerHTML = '';
            leaderList.innerHTML = '';

            if (!data.ok) {
                roundList.innerHTML = '<li>FAILED TO LOAD</li>';
                leaderList.innerHTML = '<li>FAILED TO LOAD</li>';
                return;
            }

            if (!data.rounds.length) {
                roundList.innerHTML = '<li>NO ROUNDS YET</li>';
            } else {
                data.rounds.forEach(row => {
                    const li = document.createElement('li');
                    li.textContent = `${row.result.toUpperCase()} | SCORE ${row.scores} | MINS ${row.durationminutes} | ${row.createdat}`;
                    roundList.appendChild(li);
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

    // initial load stats, wait for user to Deal
    loadStats();
})();
</script>
</body>
</html>
