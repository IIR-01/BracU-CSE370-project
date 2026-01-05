<?php
require_once '../includes/config.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    $session_id = isset($_POST['session_id']) ? intval($_POST['session_id']) : 0;
    $result = isset($_POST['result']) ? $_POST['result'] : ''; // 'win', 'loss', 'draw'
    $score = isset($_POST['score']) ? intval($_POST['score']) : 0;
    
    if ($session_id === 0 || empty($result)) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit();
    }
    
    // Get game_id from session
    $session_stmt = $conn->prepare("SELECT game_id FROM game_sessions WHERE session_id = ?");
    $session_stmt->bind_param("i", $session_id);
    $session_stmt->execute();
    $session_data = $session_stmt->get_result()->fetch_assoc();
    
    if (!$session_data) {
        echo json_encode(['success' => false, 'message' => 'Session not found']);
        exit();
    }
    
    $game_id = $session_data['game_id'];
    
    // Calculate experience based on result
    $experience_gained = 0;
    if ($result === 'win') $experience_gained = 100;
    elseif ($result === 'draw') $experience_gained = 50;
    elseif ($result === 'loss') $experience_gained = 10;
    
    // Insert into game_history
    $history_stmt = $conn->prepare("INSERT INTO game_history (game_id, session_id, player_id, result, score, experience_gained, created_at) 
                                     VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $history_stmt->bind_param("iiisii", $game_id, $session_id, $_SESSION['player_id'], $result, $score, $experience_gained);
    $history_stmt->execute();
    
    // Update player stats
    if ($result === 'win') {
        $update_player = $conn->prepare("UPDATE players SET total_games_played = total_games_played + 1, total_wins = total_wins + 1, experience_points = experience_points + ? WHERE player_id = ?");
    } elseif ($result === 'loss') {
        $update_player = $conn->prepare("UPDATE players SET total_games_played = total_games_played + 1, total_losses = total_losses + 1, experience_points = experience_points + ? WHERE player_id = ?");
    } else {
        $update_player = $conn->prepare("UPDATE players SET total_games_played = total_games_played + 1, experience_points = experience_points + ? WHERE player_id = ?");
    }
    $update_player->bind_param("ii", $experience_gained, $_SESSION['player_id']);
    $update_player->execute();
    
    // Update or insert into leaderboard
    $check_leaderboard = $conn->prepare("SELECT leaderboard_id, score FROM leaderboards WHERE game_id = ? AND player_id = ?");
    $check_leaderboard->bind_param("ii", $game_id, $_SESSION['player_id']);
    $check_leaderboard->execute();
    $leaderboard_result = $check_leaderboard->get_result();
    
    if ($leaderboard_result->num_rows > 0) {
        // Update existing record
        $existing = $leaderboard_result->fetch_assoc();
        $new_score = $existing['score'] + $score;
        
        $update_leaderboard = $conn->prepare("UPDATE leaderboards SET score = ?, last_played = NOW(), updated_at = NOW() WHERE leaderboard_id = ?");
        $update_leaderboard->bind_param("ii", $new_score, $existing['leaderboard_id']);
        $update_leaderboard->execute();
    } else {
        // Insert new record
        $insert_leaderboard = $conn->prepare("INSERT INTO leaderboards (game_id, player_id, score, last_played) VALUES (?, ?, ?, NOW())");
        $insert_leaderboard->bind_param("iii", $game_id, $_SESSION['player_id'], $score);
        $insert_leaderboard->execute();
    }
    
    // Recalculate player rankings based on experience points
    $conn->query("SET @rank = 0");
    $conn->query("UPDATE players p
                  JOIN (
                      SELECT player_id, (@rank := @rank + 1) as new_rank
                      FROM players
                      WHERE total_games_played > 0
                      ORDER BY experience_points DESC, total_wins DESC
                  ) ranked ON p.player_id = ranked.player_id
                  SET p.rank_position = ranked.new_rank");
    
    // Recalculate game-specific leaderboard rankings
    $conn->query("SET @game_rank = 0");
    $conn->query("UPDATE leaderboards l
                  JOIN (
                      SELECT leaderboard_id, (@game_rank := @game_rank + 1) as new_rank
                      FROM leaderboards
                      WHERE game_id = $game_id
                      ORDER BY score DESC, last_played ASC
                  ) ranked ON l.leaderboard_id = ranked.leaderboard_id
                  SET l.rank_position = ranked.new_rank
                  WHERE l.game_id = $game_id");
    
    $conn->close();
    
    echo json_encode(['success' => true, 'message' => 'Game result saved', 'experience' => $experience_gained]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
