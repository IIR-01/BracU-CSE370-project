<?php
require_once '../includes/config.php';
requireLogin();

$conn = getDBConnection();
$session_id = isset($_GET['session']) ? intval($_GET['session']) : 0;

if ($session_id > 0) {
    // Get session details
    $session_stmt = $conn->prepare("SELECT host_player_id, status FROM game_sessions WHERE session_id = ?");
    $session_stmt->bind_param("i", $session_id);
    $session_stmt->execute();
    $session_result = $session_stmt->get_result();
    
    if ($session_result->num_rows > 0) {
        $session = $session_result->fetch_assoc();
        
        // If user is host or session is solo, mark as completed
        if ($session['host_player_id'] == $_SESSION['player_id'] || $session['status'] == 'in-progress') {
            $update_stmt = $conn->prepare("UPDATE game_sessions SET status = 'completed', end_time = NOW() WHERE session_id = ?");
            $update_stmt->bind_param("i", $session_id);
            $update_stmt->execute();
        }
    }
}

// Update player status to online
$status_stmt = $conn->prepare("UPDATE players SET status = 'online' WHERE player_id = ?");
$status_stmt->bind_param("i", $_SESSION['player_id']);
$status_stmt->execute();

$conn->close();
header('Location: ../index.php');
exit();
?>
