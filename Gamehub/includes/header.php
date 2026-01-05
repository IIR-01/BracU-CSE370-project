<?php
if (!isset($conn)) {
    require_once __DIR__ . '/config.php';
    $conn = getDBConnection();
}

// Auto-detect base URL
if (!isset($base_url)) {
    $base_url = strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '../' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>GameHub</title>
    <link rel="stylesheet" href="<?php echo $base_url; ?>assets/css/style.css">
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '../' : ''; ?>index.php"><h2>🎮 GameHub</h2></a>
            </div>
            <ul class="nav-menu">
                <li><a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '../' : ''; ?>index.php" class="nav-link">Home</a></li>
                <li><a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>leaderboard.php" class="nav-link">Leaderboard</a></li>
                <li><a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>game_history.php" class="nav-link">My History</a></li>
                <li><span class="nav-username">👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></span></li>
                <li><a href="<?php echo strpos($_SERVER['PHP_SELF'], '/pages/') !== false ? '' : 'pages/'; ?>logout.php" class="nav-link logout-btn">Logout</a></li>
            </ul>
        </div>
    </nav>
    <?php endif; ?>
    
    <main class="main-content">
