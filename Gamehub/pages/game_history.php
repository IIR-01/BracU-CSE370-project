<?php
require_once '../includes/config.php';
requireLogin();

$page_title = 'Game History';
$conn = getDBConnection();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Get user's game history with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get total count
$count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM game_history WHERE player_id = ?");
if (!$count_stmt) {
    die("Prepare failed: " . $conn->error);
}
$count_stmt->bind_param("i", $_SESSION['player_id']);
$count_stmt->execute();
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get game history - adjusted for actual table structure
$history_stmt = $conn->prepare("SELECT gh.history_id, gh.game_id, gh.session_id, gh.player_id, 
                                       gh.result, gh.score, gh.experience_gained,
                                       g.name as game_name, gs.session_code,
                                       DATE_FORMAT(gh.created_at, '%b %d, %Y %h:%i %p') as played_time
                                FROM game_history gh
                                JOIN games g ON gh.game_id = g.game_id
                                LEFT JOIN game_sessions gs ON gh.session_id = gs.session_id
                                WHERE gh.player_id = ?
                                ORDER BY gh.created_at DESC
                                LIMIT ? OFFSET ?");
if (!$history_stmt) {
    die("Prepare failed: " . $conn->error);
}
$history_stmt->bind_param("iii", $_SESSION['player_id'], $per_page, $offset);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

// Game icon mapping
$game_icons = [
    'Tic Tac Toe' => '❌',
    'Dice Roll' => '🎲',
    'Head or Tail' => '🪙',
    'Stone Paper Scissors' => '✂️'
];

// Get player stats
$stats_stmt = $conn->prepare("SELECT total_games_played, total_wins, total_losses, experience_points FROM players WHERE player_id = ?");
if (!$stats_stmt) {
    die("Prepare failed: " . $conn->error);
}
$stats_stmt->bind_param("i", $_SESSION['player_id']);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

$win_rate = $stats['total_games_played'] > 0 ? round(($stats['total_wins'] / $stats['total_games_played']) * 100, 1) : 0;

require_once '../includes/header.php';
?>

<style>
.history-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    border-radius: 10px;
    color: white;
    text-align: center;
}

.stat-card.wins {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
}

.stat-card.losses {
    background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
}

.stat-card.winrate {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    text-transform: uppercase;
    opacity: 0.9;
}

.stat-card .stat-value {
    font-size: 36px;
    font-weight: bold;
}

.history-table {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.history-table h2 {
    margin-top: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

thead {
    background: #667eea;
    color: white;
}

th, td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

th {
    font-weight: 600;
}

tbody tr:hover {
    background: #f5f5f5;
}

.result-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.result-win {
    background: #d4edda;
    color: #155724;
}

.result-loss {
    background: #f8d7da;
    color: #721c24;
}

.result-draw {
    background: #fff3cd;
    color: #856404;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}

.pagination a, .pagination span {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 5px;
    text-decoration: none;
    color: #667eea;
}

.pagination a:hover {
    background: #667eea;
    color: white;
}

.pagination .current {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.no-history {
    text-align: center;
    padding: 40px;
    color: #999;
}

.game-icon {
    font-size: 20px;
}
</style>

<div class="history-container">
    <h1>📊 Game History</h1>
    
    <!-- Stats Overview -->
    <div class="stats-overview">
        <div class="stat-card">
            <h3>Total Games</h3>
            <div class="stat-value"><?php echo $stats['total_games_played']; ?></div>
        </div>
        <div class="stat-card wins">
            <h3>Total Wins</h3>
            <div class="stat-value"><?php echo $stats['total_wins']; ?></div>
        </div>
        <div class="stat-card losses">
            <h3>Total Losses</h3>
            <div class="stat-value"><?php echo $stats['total_losses']; ?></div>
        </div>
        <div class="stat-card winrate">
            <h3>Win Rate</h3>
            <div class="stat-value"><?php echo $win_rate; ?>%</div>
        </div>
    </div>

    <!-- History Table -->
    <div class="history-table">
        <h2>Recent Games</h2>
        
        <?php if ($history_result->num_rows > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Game</th>
                    <th>Result</th>
                    <th>Score</th>
                    <th>XP Gained</th>
                    <th>Session</th>
                    <th>Played At</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $history_result->fetch_assoc()): 
                    $icon = isset($game_icons[$row['game_name']]) ? $game_icons[$row['game_name']] : '🎮';
                ?>
                <tr>
                    <td>
                        <span class="game-icon"><?php echo $icon; ?></span>
                        <?php echo htmlspecialchars($row['game_name']); ?>
                    </td>
                    <td>
                        <span class="result-badge result-<?php echo $row['result']; ?>">
                            <?php 
                            if ($row['result'] === 'win') echo '🏆 Win';
                            elseif ($row['result'] === 'loss') echo '❌ Loss';
                            else echo '🤝 Draw';
                            ?>
                        </span>
                    </td>
                    <td><?php echo $row['score']; ?></td>
                    <td>+<?php echo $row['experience_gained']; ?> XP</td>
                    <td><?php echo $row['session_code'] ?? 'N/A'; ?></td>
                    <td><?php echo $row['played_time']; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">← Previous</a>
            <?php endif; ?>
            
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <div class="no-history">
            <p>No game history yet. Start playing to see your records here!</p>
            <a href="../index.php" class="btn">Play Now</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$conn->close();
require_once '../includes/footer.php';
?>
