<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/rules.php';
require_once 'includes/predefined_rules.php';
$active_rules = $_SESSION['custom_rules'] ?? [];



$conn = getDBConnection();

// Get all active games
$games = $conn->query("SELECT * FROM games WHERE is_active = 1 ORDER BY category, name");

// Get total players
$total_players = $conn->query("SELECT COUNT(*) as count FROM players")->fetch_assoc()['count'];

// Get online players
$online_players = $conn->query("SELECT COUNT(*) as count FROM players WHERE status = 'online'")->fetch_assoc()['count'];

// Get active sessions
$active_sessions = $conn->query("SELECT COUNT(*) as count FROM game_sessions WHERE status IN ('waiting', 'in-progress')")->fetch_assoc()['count'];

// Get custom rules from session
$active_rules = getRules();

$error = '';
$success = '';

// Handle Login
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT u.user_id, u.email, u.password, p.player_id, p.user_name, p.level, p.status 
                            FROM users u 
                            JOIN players p ON u.user_id = p.user_id 
                            WHERE u.email = ? AND u.is_active = 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['player_id'] = $user['player_id'];
            $_SESSION['user_name'] = $user['user_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['level'] = $user['level'];
            
            // Update last login and status
            $update_stmt = $conn->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE user_id = ?");
            $update_stmt->bind_param("i", $user['user_id']);
            $update_stmt->execute();
            
            $status_stmt = $conn->prepare("UPDATE players SET status = 'online' WHERE player_id = ?");
            $status_stmt->bind_param("i", $user['player_id']);
            $status_stmt->execute();
            
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid email or password';
        }
    } else {
        $error = 'Invalid email or password';
    }
    
    $stmt->close();
}

// Handle Signup
if (isset($_POST['signup'])) {
    $email = trim($_POST['signup_email']);
    $password = $_POST['signup_password'];
    $confirm_password = $_POST['confirm_password'];
    $username = trim($_POST['username']);
    
    // Validation
    if (empty($email) || empty($password) || empty($username)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Check if email exists
        $check_email = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $error = 'Email already registered';
        } else {
            // Check if username exists
            $check_username = $conn->prepare("SELECT player_id FROM players WHERE user_name = ?");
            $check_username->bind_param("s", $username);
            $check_username->execute();
            if ($check_username->get_result()->num_rows > 0) {
                $error = 'Username already taken';
            } else {
                // Create user account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                $stmt->bind_param("ss", $email, $hashed_password);
                
                if ($stmt->execute()) {
                    $user_id = $conn->insert_id;
                    
                    // Create player profile
                    $player_stmt = $conn->prepare("INSERT INTO players (user_id, user_name) VALUES (?, ?)");
                    $player_stmt->bind_param("is", $user_id, $username);
                    
                    if ($player_stmt->execute()) {
                        $success = 'Account created successfully! Please login.';
                    } else {
                        $error = 'Error creating player profile';
                    }
                    $player_stmt->close();
                } else {
                    $error = 'Error creating account';
                }
                $stmt->close();
            }
            $check_username->close();
        }
        $check_email->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameHub - Play Games Online</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Public Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <h2>🎮 GameHub</h2>
            </div>
            <ul class="nav-menu">
                <li><a href="#games" class="nav-link">Games</a></li>
                <li><a href="#features" class="nav-link">Features</a></li>
                <?php if (isLoggedIn()): ?>
                    <li><a href="pages/leaderboard.php" class="nav-link">Leaderboard</a></li>
                    <li><span class="nav-username">👤 <?php echo htmlspecialchars($_SESSION['user_name']); ?></span></li>
                    <li><a href="pages/logout.php" class="nav-link logout-btn">Logout</a></li>
                <?php else: ?>
                    <li><a href="#login" class="nav-link" onclick="showLoginModal()">Login</a></li>
                    <li><a href="#signup" class="nav-link btn-primary" onclick="showSignupModal()">Sign Up</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>Welcome to GameHub 🎮</h1>
            <p class="hero-subtitle">Play Amazing Games Online - Solo or with Friends!</p>
            <div class="hero-stats">
                <div class="hero-stat">
                    <h3><?php echo $total_players; ?></h3>
                    <p>Total Players</p>
                </div>
                <div class="hero-stat">
                    <h3><?php echo $online_players; ?></h3>
                    <p>Online Now</p>
                </div>
                <div class="hero-stat">
                    <h3><?php echo $active_sessions; ?></h3>
                    <p>Active Games</p>
                </div>
            </div>
        </div>
    </section>

	<!-- Games Section -->
	<section id="games" class="games-section">
		<div class="section-container">
			<h2 class="section-title">🎯 Available Games</h2>
			<p class="section-subtitle">Choose your game and start playing!</p>
			
			<div class="games-grid-home">
				<?php if ($games && $games->num_rows > 0): ?>
					<?php while ($game = $games->fetch_assoc()): ?>
						<div class="game-card-home">
							<div class="game-actions">
								<?php if (!empty($active_rules[$game['name']])): ?>
									<button class="btn btn-play" onclick="playGame(<?php echo $game['game_id']; ?>, 'solo')">🤖 Play Solo</button>
									<button class="btn btn-play-duo" onclick="playGame(<?php echo $game['game_id']; ?>, 'duo')">👥 Play Duo</button>
									<ul>
										<?php foreach ($active_rules[$game['name']] as $rule): ?>
											<li><?php echo htmlspecialchars($rule); ?></li>
										<?php endforeach; ?>
									</ul>
								<?php else: ?>
									<a href="pages/custom_rules.php" class="btn btn-secondary">Go to Rules</a>
								<?php endif; ?>
							</div>
							<div class="game-icon">
								<?php echo $game['category'] == 'Puzzle' ? '🧩' : ($game['category'] == 'Action' ? '⚔️' : '🎲'); ?>
							</div>
							<h3><?php echo htmlspecialchars($game['name']); ?></h3>
							<p class="game-description"><?php echo htmlspecialchars($game['description'] ?? 'No description'); ?></p>
							<div class="game-meta">
								<span class="game-category"><?php echo htmlspecialchars($game['category'] ?? 'General'); ?></span>
								<span class="game-players"><?php echo $game['min_players']; ?>-<?php echo $game['max_players']; ?> players</span>
							</div>
							<div class="game-actions">
								<button class="btn btn-play" onclick="playGame(<?php echo $game['game_id']; ?>, 'solo')">🤖 Play Solo</button>
								<button class="btn btn-play-duo" onclick="playGame(<?php echo $game['game_id']; ?>, 'duo')">👥 Play Duo</button>
							</div>
						</div>
					<?php endwhile; ?>
				<?php endif; ?>
				<?php
				$staticGames = [
					["Cards", "♠️", "assets/games/cards.php", "Classic card game."],
					["Chess", "♟️", "assets/games/chess.php", "Strategic board game."],
					["Classic Snake", "🐍", "assets/games/clasicSnake.php", "Eat and grow the snake!"],
					["D&D", "⚔️", "assets/games/d&d.php", "Role-playing adventure."],
					["Neon Racer", "🚗", "assets/games/neonRacer.php", "Fast-paced racing game."],
					["Poker", "🃏", "assets/games/poker.php", "Card strategy game."],
					["Shooter", "🔫", "assets/games/shooter.php", "Action shooting game."],
					["Sudoku", "🧩", "assets/games/sudoko.php", "Number puzzle challenge."],
					["UNO", "🎴", "assets/games/uno.php", "Popular card game."]
				];

				foreach ($staticGames as $g) {
					echo '<div class="game-card-home">
							<div class="game-icon">'.$g[1].'</div>
							<h3>'.$g[0].'</h3>
							<p class="game-description">'.$g[3].'</p>
							<div class="game-actions">
								<a href="'.$g[2].'" class="btn btn-play">▶ Play</a>
							</div>
						  </div>';
				}
				?>
			</div>
		</div>
	</section>




	<!-- Features Section -->
	<section id="features" class="features-section">
		<div class="section-container">
			<h2 class="section-title">✨ Features</h2>
			<p class="section-subtitle">Everything you need for an amazing gaming experience</p>
			
			<div class="features-grid">
				<div class="feature-card">
					<div class="feature-icon">🎮</div>
					<h3>Multiple Games</h3>
					<p>Play various games from puzzles to strategy</p>
				</div>
				<div class="feature-card">
					<div class="feature-icon">🤖</div>
					<h3>Solo Mode</h3>
					<p>Practice against AI with adjustable difficulty</p>
				</div>
				<div class="feature-card">
					<div class="feature-icon">👥</div>
					<h3>Private Rooms</h3>
					<p>Create private rooms and invite friends with a code</p>
				</div>
				<div class="feature-card">
					<div class="feature-icon">💬</div>
					<h3>Live Chat</h3>
					<p>Chat with your opponent during gameplay</p>
				</div>
				<div class="feature-card">
					<div class="feature-icon">🎨</div>
					<h3>Custom Themes</h3>
					<p>Personalize your gaming experience with themes</p>
				</div>
				<!-- NEW Custom Rules Feature -->
				<div class="feature-card">
					<div class="feature-icon">📜</div>
					<h3>Custom Rules</h3>
					<p>Select predefined rules for each game (Dice Roll, Head or Tail, Stone Paper Scissors, Tic Tac Toe) to change how the game is played.</p>
				</div>
				<div class="feature-card">
					<div class="feature-icon">🏆</div>
					<h3>Leaderboards</h3>
					<p>Compete for the top spot on global rankings</p>
				</div>
				<div class="feature-card">
					<div class="feature-icon">📊</div>
					<h3>Progress Tracking</h3>
					<p>Track your stats, achievements, and progress</p>
				</div>
			</div>
		</div>
	</section>


    <!-- Login/Signup Modal -->
    <div id="authModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            
            <h1 class="auth-title">🎮 GameHub</h1>
            <p class="auth-subtitle">Join the Ultimate Gaming Experience</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <div class="tabs">
                <button class="tab-btn active" onclick="switchTab('login')">Login</button>
                <button class="tab-btn" onclick="switchTab('signup')">Sign Up</button>
            </div>
            
            <!-- Login Form -->
            <form id="login-form" class="auth-form active" method="POST" action="">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" name="login" class="btn btn-primary">Login</button>
            </form>
            
            <!-- Signup Form -->
            <form id="signup-form" class="auth-form" method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="signup_email">Email</label>
                    <input type="email" id="signup_email" name="signup_email" required>
                </div>
                
                <div class="form-group">
                    <label for="signup_password">Password</label>
                    <input type="password" id="signup_password" name="signup_password" minlength="6" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
                </div>
                
                <button type="submit" name="signup" class="btn btn-primary">Create Account</button>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-content">
            <p>&copy; <?php echo date('Y'); ?> GameHub. All rights reserved.</p>
            <p>Play. Compete. Win.</p>
        </div>
    </footer>
    
    <script>
        function switchTab(tab) {
            const loginForm = document.getElementById('login-form');
            const signupForm = document.getElementById('signup-form');
            const tabs = document.querySelectorAll('.tab-btn');
            
            tabs.forEach(t => t.classList.remove('active'));
            
            if (tab === 'login') {
                loginForm.classList.add('active');
                signupForm.classList.remove('active');
                tabs[0].classList.add('active');
            } else {
                signupForm.classList.add('active');
                loginForm.classList.remove('active');
                tabs[1].classList.add('active');
            }
        }

        function showLoginModal() {
            document.getElementById('authModal').style.display = 'block';
            switchTab('login');
        }

        function showSignupModal() {
            document.getElementById('authModal').style.display = 'block';
            switchTab('signup');
        }

        function closeModal() {
            document.getElementById('authModal').style.display = 'none';
        }

        function playGame(gameId, mode) {
            <?php if (!isLoggedIn()): ?>
                alert('Please login to play games!');
                showLoginModal();
            <?php else: ?>
                window.location.href = 'pages/play.php?game=' + gameId + '&mode=' + mode;
            <?php endif; ?>
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('authModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        // Smooth scroll
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>
