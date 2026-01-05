<?php
$page_title = 'Leaderboard';
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();

// Get filter parameters
$game_filter = isset($_GET['game']) ? intval($_GET['game']) : 0;
$view_type = isset($_GET['view']) ? $_GET['view'] : 'global';

// Get all games for filter
$games = $conn->query("SELECT game_id, name FROM games WHERE is_active = 1 ORDER BY name");

// Get leaderboard data based on view type
if ($view_type === 'global') {
    // Global player rankings
    $leaderboard = $conn->query("SELECT * FROM player_rankings ORDER BY rank_position ASC, experience_points DESC LIMIT 100");
    $my_rank = $conn->query("SELECT * FROM player_rankings WHERE player_id = {$_SESSION['player_id']}")->fetch_assoc();
} else {
    // Game-specific leaderboard
    if ($game_filter > 0) {
        $leaderboard = $conn->query("SELECT * FROM game_leaderboard_details 
                                     WHERE game_id = $game_filter 
                                     ORDER BY rank_position ASC, score DESC LIMIT 100");
        $my_rank = $conn->query("SELECT * FROM game_leaderboard_details 
                                 WHERE game_id = $game_filter AND player_id = {$_SESSION['player_id']}")->fetch_assoc();
    } else {
        $leaderboard = null;
        $my_rank = null;
    }
}

// Get player comparison with top players
$comparison = $conn->query("SELECT * FROM player_vs_top_comparison WHERE player_id = {$_SESSION['player_id']}")->fetch_assoc();

require_once '../includes/header.php';
?>

<div class="leaderboard-container">
    <div class="page-header">
        <h1>🏆 Leaderboard</h1>
        <p>Compete with the best players worldwide!</p>
    </div>
    
    <!-- Filter Controls -->
    <div class="filter-section">
        <div class="filter-tabs">
            <a href="?view=global" class="filter-tab <?php echo $view_type === 'global' ? 'active' : ''; ?>">
                🌍 Global Rankings
            </a>
            <a href="?view=game" class="filter-tab <?php echo $view_type === 'game' ? 'active' : ''; ?>">
                🎮 Game Leaderboards
            </a>
        </div>
        
        <?php if ($view_type === 'game'): ?>
            <div class="game-filter">
                <form method="GET" action="">
                    <input type="hidden" name="view" value="game">
                    <select name="game" onchange="this.form.submit()" class="game-select">
                        <option value="0">Select a game...</option>
                        <?php while ($game = $games->fetch_assoc()): ?>
                            <option value="<?php echo $game['game_id']; ?>" 
                                    <?php echo $game_filter == $game['game_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($game['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- My Rank Card -->
    <?php if ($my_rank): ?>
        <div class="my-rank-card">
            <h3>📊 Your Position</h3>
            <div class="rank-details">
                <?php if ($view_type === 'global'): ?>
                    <div class="rank-item">
                        <span class="rank-label">Global Rank:</span>
                        <span class="rank-value">#<?php echo ($my_rank['rank_position'] && $my_rank['rank_position'] > 0) ? $my_rank['rank_position'] : 'Unranked'; ?></span>
                    </div>
                    <div class="rank-item">
                        <span class="rank-label">Experience:</span>
                        <span class="rank-value"><?php echo number_format($my_rank['experience_points'] ?? 0); ?> XP</span>
                    </div>
                    <div class="rank-item">
                        <span class="rank-label">Win Rate:</span>
                        <span class="rank-value"><?php echo number_format($my_rank['win_percentage'] ?? 0, 1); ?>%</span>
                    </div>
                    <div class="rank-item">
                        <span class="rank-label">Level:</span>
                        <span class="rank-value">Lv. <?php echo $my_rank['level'] ?? 1; ?></span>
                    </div>
                <?php else: ?>
                    <div class="rank-item">
                        <span class="rank-label">Rank:</span>
                        <span class="rank-value">#<?php echo ($my_rank['rank_position'] && $my_rank['rank_position'] > 0) ? $my_rank['rank_position'] : 'Unranked'; ?></span>
                    </div>
                    <div class="rank-item">
                        <span class="rank-label">Score:</span>
                        <span class="rank-value"><?php echo number_format($my_rank['score'] ?? 0); ?></span>
                    </div>
                    <div class="rank-item">
                        <span class="rank-label">Percentile:</span>
                        <span class="rank-value">Top <?php echo number_format($my_rank['percentile'] ?? 100, 1); ?>%</span>
                    </div>
                    <div class="rank-item">
                        <span class="rank-label">Level Reached:</span>
                        <span class="rank-value"><?php echo $my_rank['level_reached'] ?? 'N/A'; ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Comparison with Top Players (Global view only) -->
    <?php if ($view_type === 'global' && $comparison): ?>
        <div class="comparison-section">
            <h3>📈 Compare with Top Players</h3>
            <div class="comparison-grid">
                <div class="comparison-card">
                    <h4>Your Experience</h4>
                    <p class="big-number"><?php echo number_format($comparison['experience_points']); ?></p>
                    <p class="comparison-text">
                        Top 10 Avg: <?php echo number_format($comparison['top10_avg_exp']); ?>
                        <span class="<?php echo $comparison['exp_vs_average'] >= 0 ? 'positive' : 'negative'; ?>">
                            (<?php echo $comparison['exp_vs_average'] >= 0 ? '+' : ''; ?><?php echo number_format($comparison['exp_vs_average']); ?>)
                        </span>
                    </p>
                </div>
                
                <div class="comparison-card">
                    <h4>Your Wins</h4>
                    <p class="big-number"><?php echo number_format($comparison['total_wins']); ?></p>
                    <p class="comparison-text">
                        Top 10 Avg: <?php echo number_format($comparison['top10_avg_wins']); ?>
                    </p>
                </div>
                
                <div class="comparison-card">
                    <h4>Highest on Server</h4>
                    <p class="big-number"><?php echo number_format($comparison['highest_exp']); ?> XP</p>
                    <p class="comparison-text">
                        Wins: <?php echo number_format($comparison['highest_wins']); ?>
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Leaderboard Table -->
    <div class="leaderboard-table">
        <?php if ($leaderboard && $leaderboard->num_rows > 0): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Player</th>
                        <?php if ($view_type === 'global'): ?>
                            <th>Level</th>
                            <th>Experience</th>
                            <th>Games Played</th>
                            <th>Wins</th>
                            <th>Win Rate</th>
                            <th>Status</th>
                        <?php else: ?>
                            <th>Score</th>
                            <th>Level Reached</th>
                            <th>Time Played</th>
                            <th>Achievements</th>
                            <th>Last Played</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $position = 1;
                    while ($row = $leaderboard->fetch_assoc()): 
                        $is_current_player = ($row['player_id'] == $_SESSION['player_id']);
                    ?>
                        <tr class="<?php echo $is_current_player ? 'highlight-row' : ''; ?>">
                            <td>
                                <span class="rank-badge rank-<?php echo $position; ?>">
                                    <?php 
                                    if ($position === 1) echo '🥇';
                                    elseif ($position === 2) echo '🥈';
                                    elseif ($position === 3) echo '🥉';
                                    else echo '#' . $position;
                                    ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['user_name']); ?></strong>
                                <?php if ($is_current_player): ?>
                                    <span class="badge-you">YOU</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($view_type === 'global'): ?>
                                <td>Lv. <?php echo $row['level']; ?></td>
                                <td><?php echo number_format($row['experience_points']); ?> XP</td>
                                <td><?php echo $row['total_games_played']; ?></td>
                                <td><?php echo $row['total_wins']; ?></td>
                                <td><?php echo number_format($row['win_percentage'], 1); ?>%</td>
                                <td>
                                    <span class="status-badge status-<?php echo $row['status']; ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                </td>
                            <?php else: ?>
                                <td><?php echo number_format($row['score']); ?></td>
                                <td><?php echo $row['level_reached']; ?></td>
                                <td><?php echo $row['time_played_minutes']; ?> min</td>
                                <td><?php echo $row['achievements_unlocked']; ?></td>
                                <td><?php echo $row['last_played'] ? date('M j, Y', strtotime($row['last_played'])) : 'N/A'; ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php 
                        $position++;
                    endwhile; 
                    ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-data">
                <?php echo $view_type === 'game' && $game_filter === 0 ? 
                    'Please select a game to view its leaderboard.' : 
                    'No leaderboard data available yet.'; ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<?php
$conn->close();
require_once '../includes/footer.php';
?>
