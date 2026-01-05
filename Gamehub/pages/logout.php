<?php
require_once '../includes/config.php';

if (isLoggedIn()) {
    $conn = getDBConnection();
    
    // Update player status to offline
    $stmt = $conn->prepare("UPDATE players SET status = 'offline' WHERE player_id = ?");
    $stmt->bind_param("i", $_SESSION['player_id']);
    $stmt->execute();
    $stmt->close();
    $conn->close();
}

// Destroy session
session_destroy();
header('Location: ../index.php');
exit();
?>
