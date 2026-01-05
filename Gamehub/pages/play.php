<?php
$page_title = 'Play Game';
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();

// Get game details
$game_id = isset($_GET['game']) ? intval($_GET['game']) : 0;
$mode = isset($_GET['mode']) ? $_GET['mode'] : 'solo';

if ($game_id === 0) {
    header('Location: ../index.php');
    exit();
}

$game_stmt = $conn->prepare("SELECT * FROM games WHERE game_id = ? AND is_active = 1");
$game_stmt->bind_param("i", $game_id);
$game_stmt->execute();
$game = $game_stmt->get_result()->fetch_assoc();

if (!$game) {
    header('Location: ../index.php');
    exit();
}

$message = '';
$session_created = false;
$session_code = '';

// Debug: Check if player_id exists
if (!isset($_SESSION['player_id'])) {
    $message = "Error: Player session not found. Please log in again.";
    // Redirect to homepage
    header('Location: ../index.php?error=session_required');
    exit();
}

// Handle Solo Mode
if (isset($_POST['start_solo'])) {
    try {
        // Get selected difficulty
        $difficulty = isset($_POST['difficulty']) ? $_POST['difficulty'] : 'medium';
        
        // Validate difficulty
        $valid_difficulties = ['easy', 'medium', 'hard', 'expert'];
        if (!in_array($difficulty, $valid_difficulties)) {
            $difficulty = 'medium';
        }
        
        // Create solo game session
        $session_code_gen = strtoupper(substr(md5(uniqid()), 0, 6));
        
        $stmt = $conn->prepare("INSERT INTO game_sessions (game_id, host_player_id, session_code, status, max_players, is_private) 
                                VALUES (?, ?, ?, 'in-progress', 1, 1)");
        $stmt->bind_param("iis", $game_id, $_SESSION['player_id'], $session_code_gen);
        
        if ($stmt->execute()) {
            $session_id = $conn->insert_id;
            
            // Insert into single_games table with session_id and difficulty
            $single_stmt = $conn->prepare("INSERT INTO single_games (session_id, difficulty) VALUES (?, ?)");
            $single_stmt->bind_param("is", $session_id, $difficulty);
            
            if ($single_stmt->execute()) {
                // Success - redirect to game room
                header("Location: game_room.php?session=$session_id");
                exit();
            } else {
                // Error in single_games insert - likely table structure issue
                $error_msg = $single_stmt->error;
                
                // Check if it's a foreign key error
                if (strpos($error_msg, 'foreign key') !== false || strpos($error_msg, 'Duplicate') !== false) {
                    $message = "<strong>Database Error:</strong> Please update your database structure. Run this in phpMyAdmin SQL tab:<br><br>
                    <code style='background:#f0f0f0;padding:10px;display:block;margin:10px 0;'>
                    DROP TABLE IF EXISTS single_games;<br>
                    CREATE TABLE single_games (<br>
                    &nbsp;&nbsp;session_id int(11) NOT NULL,<br>
                    &nbsp;&nbsp;difficulty enum('easy','medium','hard','expert') DEFAULT 'easy',<br>
                    &nbsp;&nbsp;bot_id int(11) DEFAULT NULL,<br>
                    &nbsp;&nbsp;PRIMARY KEY (session_id),<br>
                    &nbsp;&nbsp;CONSTRAINT single_games_ibfk_1 FOREIGN KEY (session_id) REFERENCES game_sessions (session_id) ON DELETE CASCADE<br>
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                    </code>";
                } else {
                    $message = "Database Error: " . htmlspecialchars($error_msg);
                }
            }
        } else {
            // Error in game_sessions insert
            $message = "Error creating game session: " . htmlspecialchars($stmt->error);
        }
    } catch (Exception $e) {
        $message = "Unexpected error: " . htmlspecialchars($e->getMessage());
    }
}

// Handle Duo Mode - Create Room
if (isset($_POST['create_room'])) {
    // Generate unique room code
    $session_code = strtoupper(substr(md5(uniqid()), 0, 6));
    
    $stmt = $conn->prepare("INSERT INTO game_sessions (game_id, host_player_id, session_code, status, max_players, current_players, is_private) 
                            VALUES (?, ?, ?, 'waiting', 2, 1, 1)");
    $stmt->bind_param("iis", $game_id, $_SESSION['player_id'], $session_code);
    
    if ($stmt->execute()) {
        $session_id = $conn->insert_id;
        $session_created = true;
        $message = "Room created! Share code: <strong>$session_code</strong>";
        
        // Redirect to game room
        header("Location: game_room.php?session=$session_id");
        exit();
    }
}

// Handle Duo Mode - Join Room
if (isset($_POST['join_room'])) {
    $join_code = strtoupper(trim($_POST['room_code']));
    
    $stmt = $conn->prepare("SELECT session_id, current_players, max_players FROM game_sessions 
                            WHERE session_code = ? AND game_id = ? AND status = 'waiting'");
    $stmt->bind_param("si", $join_code, $game_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $session = $result->fetch_assoc();
        
        if ($session['current_players'] < $session['max_players']) {
            // Update session
            $update_stmt = $conn->prepare("UPDATE game_sessions SET current_players = current_players + 1, status = 'in-progress' 
                                          WHERE session_id = ?");
            $update_stmt->bind_param("i", $session['session_id']);
            $update_stmt->execute();
            
            // Redirect to game room
            header("Location: game_room.php?session=" . $session['session_id']);
            exit();
        } else {
            $message = "Room is full!";
        }
    } else {
        $message = "Invalid room code or room already started!";
    }
}

require_once '../includes/header.php';
?>

<?php if ($message): ?>
    <script>
        alert('ERROR: <?php echo addslashes(strip_tags($message)); ?>');
    </script>
    <div class="play-container">
        <div class="alert alert-error" style="background:#ff4444;color:white;padding:20px;margin:20px;border-radius:8px;font-size:16px;">
            <strong>⚠️ ERROR:</strong><br>
            <?php echo $message; ?>
        </div>
    </div>
<?php endif; ?>

<div class="play-container">
    <div class="game-header-play">
        <a href="../index.php" class="back-link">← Back to Games</a>
        <h1>🎮 <?php echo htmlspecialchars($game['name']); ?></h1>
        <p><?php echo htmlspecialchars($game['description'] ?? ''); ?></p>
        <span class="game-category-badge"><?php echo htmlspecialchars($game['category'] ?? 'General'); ?></span>
    </div>
    
    <div class="mode-selection">
        <?php if ($mode === 'solo'): ?>
            <!-- Solo Mode -->
            <div class="mode-card active-mode">
                <div class="mode-icon">🤖</div>
                <h2>Solo Mode</h2>
                <p>Play against AI opponent</p>
                
                <div class="difficulty-selection">
                    <h3>Select Difficulty:</h3>
                    <div class="difficulty-buttons">
                        <button class="difficulty-btn" data-difficulty="easy">Easy</button>
                        <button class="difficulty-btn active" data-difficulty="medium">Medium</button>
                        <button class="difficulty-btn" data-difficulty="hard">Hard</button>
                        <button class="difficulty-btn" data-difficulty="expert">Expert</button>
                    </div>
                </div>
                
                <form method="POST" action="">
                    <input type="hidden" name="difficulty" id="selected_difficulty" value="medium">
                    <input type="hidden" name="start_solo" value="1">
                    <button type="submit" class="btn btn-primary btn-large">
                        Start Solo Game
                    </button>
                </form>
                
                <a href="?game=<?php echo $game_id; ?>&mode=duo" class="switch-mode-link">
                    Or try Duo Mode →
                </a>
            </div>
            
        <?php else: ?>
            <!-- Duo Mode -->
            <div class="mode-cards-duo">
                <div class="mode-card">
                    <div class="mode-icon">🎯</div>
                    <h2>Create Room</h2>
                    <p>Start a new game and invite a friend</p>
                    
                    <form method="POST" action="">
                        <button type="submit" name="create_room" class="btn btn-primary btn-large">
                            Create Private Room
                        </button>
                    </form>
                    
                    <div class="mode-info">
                        <p>📌 A unique 6-digit code will be generated</p>
                        <p>📌 Share it with your friend to join</p>
                    </div>
                </div>
                
                <div class="mode-divider">OR</div>
                
                <div class="mode-card">
                    <div class="mode-icon">🚪</div>
                    <h2>Join Room</h2>
                    <p>Enter your friend's room code</p>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="room_code">Room Code</label>
                            <input type="text" 
                                   id="room_code" 
                                   name="room_code" 
                                   placeholder="Enter 6-digit code" 
                                   maxlength="6"
                                   style="text-transform: uppercase; text-align: center; font-size: 24px; letter-spacing: 5px;"
                                   required>
                        </div>
                        <button type="submit" name="join_room" class="btn btn-secondary btn-large">
                            Join Game
                        </button>
                    </form>
                    
                    <div class="mode-info">
                        <p>📌 Get the code from your friend</p>
                        <p>📌 Room must be waiting for players</p>
                    </div>
                </div>
            </div>
            
            <a href="?game=<?php echo $game_id; ?>&mode=solo" class="switch-mode-link">
                ← Or play Solo Mode
            </a>
        <?php endif; ?>
    </div>
    
    <!-- Active Public Rooms (Optional) -->
    <div class="public-rooms-section">
        <h2>🌐 Public Rooms Waiting for Players</h2>
        <?php
        $public_rooms = $conn->query("SELECT gs.*, p.user_name 
                                      FROM game_sessions gs
                                      JOIN players p ON gs.host_player_id = p.player_id
                                      WHERE gs.game_id = $game_id 
                                      AND gs.status = 'waiting' 
                                      AND gs.is_private = 0
                                      ORDER BY gs.created_at DESC
                                      LIMIT 5");
        
        if ($public_rooms && $public_rooms->num_rows > 0):
        ?>
            <div class="public-rooms-list">
                <?php while ($room = $public_rooms->fetch_assoc()): ?>
                    <div class="public-room-card">
                        <div class="room-info">
                            <strong>Host: <?php echo htmlspecialchars($room['user_name']); ?></strong>
                            <span>Players: <?php echo $room['current_players']; ?>/<?php echo $room['max_players']; ?></span>
                        </div>
                        <a href="game_room.php?session=<?php echo $room['session_id']; ?>" class="btn btn-small">
                            Join →
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="no-data">No public rooms available. Create one!</p>
        <?php endif; ?>
    </div>
</div>

<script>
// Difficulty selection (solo mode only)
const difficultyButtons = document.querySelectorAll('.difficulty-btn');
if (difficultyButtons.length > 0) {
    difficultyButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.difficulty-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            document.getElementById('selected_difficulty').value = this.dataset.difficulty;
            console.log('Difficulty selected:', this.dataset.difficulty);
        });
    });
}

// Debug form submission
const soloForm = document.querySelector('form[method="POST"]');
if (soloForm) {
    soloForm.addEventListener('submit', function(e) {
        const difficulty = document.getElementById('selected_difficulty').value;
        console.log('Form submitting with difficulty:', difficulty);
        // Don't prevent default - let it submit
    });
}

// Auto-uppercase room code input (duo mode only)
const roomCodeInput = document.getElementById('room_code');
if (roomCodeInput) {
    roomCodeInput.addEventListener('input', function(e) {
        this.value = this.value.toUpperCase();
    });
}
</script>

<?php
$conn->close();
require_once '../includes/footer.php';
?>
