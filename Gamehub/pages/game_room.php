<?php
$page_title = 'Game Room';
require_once '../includes/config.php';
requireLogin();

// Verify session has required data
if (!isset($_SESSION['player_id']) || !isset($_SESSION['user_name'])) {
    header('Location: ../index.php?error=session_invalid');
    exit();
}

$conn = getDBConnection();

// Get session details
$session_id = isset($_GET['session']) ? intval($_GET['session']) : 0;

if ($session_id === 0) {
    header('Location: ../index.php');
    exit();
}

$session_stmt = $conn->prepare("SELECT gs.*, g.name as game_name, g.category, p.user_name as host_name
                                FROM game_sessions gs
                                JOIN games g ON gs.game_id = g.game_id
                                JOIN players p ON gs.host_player_id = p.player_id
                                WHERE gs.session_id = ?");
$session_stmt->bind_param("i", $session_id);
$session_stmt->execute();
$session = $session_stmt->get_result()->fetch_assoc();

if (!$session) {
    header('Location: ../index.php');
    exit();
}

// Send chat message
if (isset($_POST['send_message'])) {
    $message = trim($_POST['message']);
    $message_type = 'text';
    
    if (!empty($message)) {
        $stmt = $conn->prepare("INSERT INTO chat (session_id, player_id, message, message_type) 
                                VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $session_id, $_SESSION['player_id'], $message, $message_type);
        $stmt->execute();
        $stmt->close();
        
        // DON'T redirect - just continue to show the page with new message
        // The message will appear when the page renders
    }
}

// Get chat messages - use prepared statement for security
$chat_stmt = $conn->prepare("SELECT c.*, p.user_name, p.level,
                               DATE_FORMAT(c.created_at, '%H:%i:%s') as sent_time
                               FROM chat c
                               JOIN players p ON c.player_id = p.player_id
                               WHERE c.session_id = ?
                               ORDER BY c.created_at ASC");
$chat_stmt->bind_param("i", $session_id);
$chat_stmt->execute();
$chat_messages = $chat_stmt->get_result();

// Get leaderboard for this game - use prepared statement for security
$leaderboard_stmt = $conn->prepare("SELECT * FROM game_leaderboard_details 
                                     WHERE game_id = ? 
                                     ORDER BY score DESC 
                                     LIMIT 10");
$leaderboard_stmt->bind_param("i", $session['game_id']);
$leaderboard_stmt->execute();
$leaderboard = $leaderboard_stmt->get_result();

require_once '../includes/header.php';

// Map game names to file names
$gameFiles = [
    'Tic Tac Toe' => 'tictactoe',
    'Dice Roll' => 'diceroll',
    'Head or Tail' => 'headtail',
    'Stone Paper Scissors' => 'szr'
];

$gameFile = isset($gameFiles[$session['game_name']]) ? $gameFiles[$session['game_name']] : null;
?>

<?php if ($gameFile): ?>
    <link rel="stylesheet" href="../assets/games/<?php echo $gameFile; ?>.css">
<?php endif; ?>

<div class="game-room-container">
    <!-- Game Room Header -->
    <div class="room-header">
        <div class="room-info">
            <h1>🎮 <?php echo htmlspecialchars($session['game_name']); ?></h1>
            <div class="room-meta">
                <span class="room-code">Room: <strong><?php echo $session['session_code'] ?? 'N/A'; ?></strong></span>
                <span class="room-host">Host: <?php echo htmlspecialchars($session['host_name']); ?></span>
                <span class="room-status status-badge status-<?php echo $session['status']; ?>">
                    <?php echo ucfirst($session['status']); ?>
                </span>
                <span class="room-players">
                    Players: <?php echo $session['current_players']; ?>/<?php echo $session['max_players']; ?>
                </span>
            </div>
        </div>
        <div class="room-actions">
            <button onclick="toggleLeaderboard()" class="btn btn-small">🏆 Leaderboard</button>
            <button onclick="toggleChatTheme()" class="btn btn-small">🎨 Chat Theme</button>
            <a href="leave_game.php?session=<?php echo $session_id; ?>" class="btn btn-small logout-btn" onclick="return confirm('Are you sure you want to leave this game?')">Leave Room</a>
        </div>
    </div>

    <!-- Main Game Area -->
    <div class="game-layout">
        <!-- Game Canvas -->
        <div class="game-canvas-area">
            <?php if ($session['game_name'] === 'Tic Tac Toe'): ?>
                <!-- Tic Tac Toe Game -->
                <div class="tic-tac-toe-game">
                    <div class="game-status">
                        <h3 id="gameStatus">Waiting for players...</h3>
                        <div class="player-indicators">
                            <div class="player-indicator">
                                <span class="symbol-x">✕</span> 
                                <span id="playerX"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                            </div>
                            <div class="player-indicator">
                                <span class="symbol-o">◯</span> 
                                <span id="playerO">Waiting...</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tic-tac-toe-board" id="ticTacToeBoard">
                        <div class="cell" data-cell="0" onclick="makeMove(0)"></div>
                        <div class="cell" data-cell="1" onclick="makeMove(1)"></div>
                        <div class="cell" data-cell="2" onclick="makeMove(2)"></div>
                        <div class="cell" data-cell="3" onclick="makeMove(3)"></div>
                        <div class="cell" data-cell="4" onclick="makeMove(4)"></div>
                        <div class="cell" data-cell="5" onclick="makeMove(5)"></div>
                        <div class="cell" data-cell="6" onclick="makeMove(6)"></div>
                        <div class="cell" data-cell="7" onclick="makeMove(7)"></div>
                        <div class="cell" data-cell="8" onclick="makeMove(8)"></div>
                    </div>
                    
                    <div class="game-controls">
                        <button class="btn btn-primary" onclick="resetGame()">Reset Game</button>
                        <button class="btn btn-secondary" onclick="location.reload()">Refresh</button>
                    </div>
                </div>
            
            <?php elseif ($session['game_name'] === 'Dice Roll'): ?>
                <!-- Dice Roll Game -->
                <div class="dice-roll-game">
                    <div class="game-status">
                        <h3 id="gameStatus">Roll the dice!</h3>
                    </div>
                    
                    <div class="dice-container">
                        <div class="dice" id="dice1">?</div>
                        <div class="dice" id="dice2">?</div>
                    </div>
                    
                    <div class="game-controls">
                        <button class="btn btn-primary roll-button" id="rollButton" onclick="rollDice()">🎲 Roll Dice</button>
                        <button class="btn btn-secondary" onclick="resetDiceGame()">Reset</button>
                    </div>
                </div>
            
            <?php elseif ($session['game_name'] === 'Head or Tail'): ?>
                <!-- Head or Tail Game -->
                <div class="headtail-game">
                    <div class="game-status">
                        <h3 id="gameStatus">Choose Heads or Tails!</h3>
                    </div>
                    
                    <div class="choice-buttons">
                        <button class="choice-btn" onclick="selectChoice('Heads')">👑 Heads</button>
                        <button class="choice-btn" onclick="selectChoice('Tails')">🦅 Tails</button>
                    </div>
                    
                    <div class="coin-container">
                        <div class="coin" id="coin">
                            <div class="coin-face coin-heads">👑</div>
                            <div class="coin-face coin-tails">🦅</div>
                        </div>
                    </div>
                    
                    <div class="game-controls">
                        <button class="btn btn-primary" id="flipButton" onclick="flipCoin()">🪙 Flip Coin</button>
                        <button class="btn btn-secondary" onclick="resetHeadTailGame()">Reset</button>
                    </div>
                </div>
            
            <?php elseif ($session['game_name'] === 'Stone Paper Scissors'): ?>
                <!-- Stone Paper Scissors Game -->
                <div class="szr-game">
                    <div class="game-status">
                        <h3 id="gameStatus">Make your choice!</h3>
                    </div>
                    
                    <div class="szr-choices">
                        <div class="szr-choice" onclick="selectSZRChoice('stone')">
                            🪨
                            <div class="szr-choice-label">Stone</div>
                        </div>
                        <div class="szr-choice" onclick="selectSZRChoice('paper')">
                            📄
                            <div class="szr-choice-label">Paper</div>
                        </div>
                        <div class="szr-choice" onclick="selectSZRChoice('scissors')">
                            ✂️
                            <div class="szr-choice-label">Scissors</div>
                        </div>
                    </div>
                    
                    <div class="szr-battle">
                        <div class="szr-player-choice" id="playerChoiceDisplay">?</div>
                        <div class="szr-vs">VS</div>
                        <div class="szr-opponent-choice" id="opponentChoiceDisplay">?</div>
                    </div>
                    
                    <div id="szrResult" class="szr-result" style="display: none;"></div>
                    
                    <div class="game-controls">
                        <button class="btn btn-primary" onclick="playSZR()">⚔️ Play</button>
                        <button class="btn btn-secondary" onclick="resetSZRGame()">Reset</button>
                    </div>
                </div>
            
            <?php else: ?>
                <div class="game-placeholder">
                    <h2>🎮 <?php echo htmlspecialchars($session['game_name']); ?></h2>
                    <p>Game interface for this game is under development</p>
                </div>
            <?php endif; ?>
            
            <!-- Game Progress Bar -->
            <div class="game-progress">
                <div class="progress-info">
                    <span>Your Wins: <strong id="player-score">0</strong></span>
                    <span>Opponent: <strong id="opponent-score">0</strong></span>
                    <span>Draws: <strong id="draw-score">0</strong></span>
                </div>
            </div>
        </div>

        <!-- Chat Sidebar -->
        <div class="chat-sidebar-game" id="chatSidebar">
            <div class="chat-header-game">
                <h3>💬 Game Chat</h3>
                <button onclick="toggleChat()" class="btn-close">−</button>
            </div>
            
            <div class="chat-messages-game" id="chatMessagesGame">
                <?php if ($chat_messages && $chat_messages->num_rows > 0): ?>
                    <?php while ($msg = $chat_messages->fetch_assoc()): ?>
                        <div class="message-game <?php echo $msg['player_id'] == $_SESSION['player_id'] ? 'message-own' : 'message-other'; ?>">
                            <div class="message-header-game">
                                <strong><?php echo htmlspecialchars($msg['user_name']); ?></strong>
                                <span class="message-time"><?php echo $msg['sent_time']; ?></span>
                            </div>
                            <div class="message-content">
                                <?php echo htmlspecialchars($msg['message']); ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-messages">No messages yet. Say hi! 👋</p>
                <?php endif; ?>
            </div>
            
            <div class="chat-input-game">
                <form method="POST" action="" id="chatFormGame">
                    <input type="hidden" name="send_message" value="1">
                    <input type="text" 
                           name="message" 
                           id="messageInputGame" 
                           placeholder="Type message..." 
                           autocomplete="off"
                           required>
                    <button type="submit" class="btn btn-primary">➤</button>
                </form>
                <div class="quick-emojis-game">
                    <button onclick="insertEmojiGame('👍')" class="emoji-btn">👍</button>
                    <button onclick="insertEmojiGame('😂')" class="emoji-btn">😂</button>
                    <button onclick="insertEmojiGame('GG!')" class="emoji-btn">GG</button>
                    <button onclick="insertEmojiGame('Nice!')" class="emoji-btn">Nice!</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaderboard Modal -->
    <div id="leaderboardModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="toggleLeaderboard()">&times;</span>
            <h2>🏆 <?php echo htmlspecialchars($session['game_name']); ?> Leaderboard</h2>
            
            <div class="leaderboard-table-modal">
                <?php if ($leaderboard && $leaderboard->num_rows > 0): ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Player</th>
                                <th>Score</th>
                                <th>Level</th>
                                <th>Wins</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            while ($row = $leaderboard->fetch_assoc()): 
                                $is_you = ($row['player_id'] == $_SESSION['player_id']);
                            ?>
                                <tr class="<?php echo $is_you ? 'highlight-row' : ''; ?>">
                                    <td>
                                        <?php 
                                        if ($rank === 1) echo '🥇';
                                        elseif ($rank === 2) echo '🥈';
                                        elseif ($rank === 3) echo '🥉';
                                        else echo '#' . $rank;
                                        ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($row['user_name']); ?>
                                        <?php if ($is_you): ?><span class="badge-you">YOU</span><?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($row['score']); ?></td>
                                    <td>Lv. <?php echo $row['level_reached']; ?></td>
                                    <td><?php echo $row['total_wins']; ?></td>
                                </tr>
                            <?php 
                                $rank++;
                            endwhile; 
                            ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="no-data">No leaderboard data yet. Be the first to score!</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chat Theme Modal -->
    <div id="chatThemeModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="toggleChatTheme()">&times;</span>
            <h2>🎨 Customize Chat Theme</h2>
            
            <div class="chat-themes-grid">
                <div class="chat-theme-option" onclick="applyChatTheme('default')">
                    <div class="theme-preview-small" style="background: linear-gradient(135deg, #4A90E2, #7B68EE);"></div>
                    <p>Default</p>
                </div>
                <div class="chat-theme-option" onclick="applyChatTheme('dark')">
                    <div class="theme-preview-small" style="background: linear-gradient(135deg, #1a1a1a, #333);"></div>
                    <p>Dark</p>
                </div>
                <div class="chat-theme-option" onclick="applyChatTheme('ocean')">
                    <div class="theme-preview-small" style="background: linear-gradient(135deg, #006994, #00A8E8);"></div>
                    <p>Ocean</p>
                </div>
                <div class="chat-theme-option" onclick="applyChatTheme('forest')">
                    <div class="theme-preview-small" style="background: linear-gradient(135deg, #2D6A4F, #52B788);"></div>
                    <p>Forest</p>
                </div>
                <div class="chat-theme-option" onclick="applyChatTheme('sunset')">
                    <div class="theme-preview-small" style="background: linear-gradient(135deg, #F77F00, #FCBF49);"></div>
                    <p>Sunset</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Game session ID for localStorage and database
window.sessionId = <?php echo $session_id; ?>;
const sessionId = window.sessionId;
const storageKey = `game_scores_${sessionId}`;

// Load scores from localStorage on page load
function loadScores() {
    const saved = localStorage.getItem(storageKey);
    if (saved) {
        const scores = JSON.parse(saved);
        document.getElementById('player-score').textContent = scores.player || 0;
        document.getElementById('opponent-score').textContent = scores.opponent || 0;
        document.getElementById('draw-score').textContent = scores.draws || 0;
    }
}

// Save scores to localStorage
function saveScores() {
    const scores = {
        player: parseInt(document.getElementById('player-score').textContent) || 0,
        opponent: parseInt(document.getElementById('opponent-score').textContent) || 0,
        draws: parseInt(document.getElementById('draw-score').textContent) || 0
    };
    localStorage.setItem(storageKey, JSON.stringify(scores));
}

// Auto-scroll chat
function scrollChatToBottom() {
    const chatMessages = document.getElementById('chatMessagesGame');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

// Insert emoji
function insertEmojiGame(emoji) {
    const input = document.getElementById('messageInputGame');
    input.value += emoji;
    input.focus();
}

// Toggle chat visibility
function toggleChat() {
    const sidebar = document.getElementById('chatSidebar');
    sidebar.classList.toggle('minimized');
}

// Toggle leaderboard modal
function toggleLeaderboard() {
    const modal = document.getElementById('leaderboardModal');
    modal.style.display = modal.style.display === 'block' ? 'none' : 'block';
}

// Toggle chat theme modal
function toggleChatTheme() {
    const modal = document.getElementById('chatThemeModal');
    modal.style.display = modal.style.display === 'block' ? 'none' : 'block';
}

// Apply chat theme
function applyChatTheme(theme) {
    const chatSidebar = document.getElementById('chatSidebar');
    chatSidebar.className = 'chat-sidebar-game chat-theme-' + theme;
    toggleChatTheme();
    alert('Chat theme "' + theme + '" applied!');
}

// Close modals when clicking outside
window.onclick = function(event) {
    const leaderboardModal = document.getElementById('leaderboardModal');
    const themeModal = document.getElementById('chatThemeModal');
    
    if (event.target == leaderboardModal) {
        leaderboardModal.style.display = 'none';
    }
    if (event.target == themeModal) {
        themeModal.style.display = 'none';
    }
}

// Auto-scroll on load and load saved scores
window.addEventListener('load', function() {
    scrollChatToBottom();
    loadScores();
});

// Save scores before page unload
window.addEventListener('beforeunload', saveScores);
</script>

<?php if ($gameFile): ?>
    <script src="../assets/games/<?php echo $gameFile; ?>.js"></script>
    <script>
        // Initialize the game
        const isAI = <?php echo ($session['max_players'] == 1 || strpos($session['session_code'], 'SOLO') !== false) ? 'true' : 'false'; ?>;
        
        <?php if ($session['game_name'] === 'Tic Tac Toe'): ?>
            initTicTacToe(isAI);
        <?php elseif ($session['game_name'] === 'Dice Roll'): ?>
            initDiceRoll(isAI);
        <?php elseif ($session['game_name'] === 'Head or Tail'): ?>
            initHeadTail(isAI);
        <?php elseif ($session['game_name'] === 'Stone Paper Scissors'): ?>
            initSZR(isAI);
        <?php endif; ?>
    </script>
<?php endif; ?>

<?php
$conn->close();
require_once '../includes/footer.php';
?>
