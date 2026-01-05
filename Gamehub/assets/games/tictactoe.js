// Tic Tac Toe Game Logic
let board = ['', '', '', '', '', '', '', '', ''];
let currentPlayer = 'X';
let gameActive = true;
let playerWins = 0;
let opponentWins = 0;
let draws = 0;

// Save game result to database
function saveGameResult(result, score) {
    const sessionId = window.sessionId || document.querySelector('[data-session-id]')?.dataset.sessionId;
    if (!sessionId) return;
    
    const formData = new FormData();
    formData.append('session_id', sessionId);
    formData.append('result', result);
    formData.append('score', score);
    
    fetch('save_game_result.php', {
        method: 'POST',
        body: formData
    }).then(response => response.json())
      .then(data => {
          if (data.success) {
              console.log('Game result saved:', data.message);
          }
      }).catch(error => console.error('Error saving game:', error));
}

// Initialize game
function initTicTacToe(aiMode) {
    window.isAI = aiMode;
    if (aiMode) {
        document.getElementById('playerO').textContent = 'Computer AI';
    }
    resetGame();
}

const winningConditions = [
    [0, 1, 2], [3, 4, 5], [6, 7, 8], // Rows
    [0, 3, 6], [1, 4, 7], [2, 5, 8], // Columns
    [0, 4, 8], [2, 4, 6]             // Diagonals
];

function makeMove(cellIndex) {
    if (!gameActive || board[cellIndex] !== '') return;
    
    board[cellIndex] = currentPlayer;
    updateCell(cellIndex, currentPlayer);
    
    if (checkWin()) {
        gameActive = false;
        if (currentPlayer === 'X') {
            playerWins++;
            document.getElementById('player-score').textContent = playerWins;
            document.getElementById('gameStatus').textContent = '🎉 You Win!';
            saveGameResult('win', 1);
        } else {
            opponentWins++;
            document.getElementById('opponent-score').textContent = opponentWins;
            document.getElementById('gameStatus').textContent = '😔 You Lose!';
            saveGameResult('loss', 0);
        }
        if (typeof saveScores === 'function') saveScores();
        return;
    }
    
    if (checkDraw()) {
        gameActive = false;
        draws++;
        document.getElementById('draw-score').textContent = draws;
        document.getElementById('gameStatus').textContent = '🤝 Draw!';
        saveGameResult('draw', 0);
        if (typeof saveScores === 'function') saveScores();
        return;
    }
    
    currentPlayer = currentPlayer === 'X' ? 'O' : 'X';
    document.getElementById('gameStatus').textContent = currentPlayer === 'X' ? 'Your Turn (✕)' : 'Opponent Turn (◯)';
    
    // AI move for solo mode
    if (window.isAI && currentPlayer === 'O' && gameActive) {
        setTimeout(() => makeAIMove(), 500);
    }
}

function makeAIMove() {
    let move = findBestMove();
    if (move !== -1) {
        makeMove(move);
    }
}

function findBestMove() {
    // Try to win
    for (let condition of winningConditions) {
        const [a, b, c] = condition;
        if (board[a] === 'O' && board[b] === 'O' && board[c] === '') return c;
        if (board[a] === 'O' && board[c] === 'O' && board[b] === '') return b;
        if (board[b] === 'O' && board[c] === 'O' && board[a] === '') return a;
    }
    
    // Block player
    for (let condition of winningConditions) {
        const [a, b, c] = condition;
        if (board[a] === 'X' && board[b] === 'X' && board[c] === '') return c;
        if (board[a] === 'X' && board[c] === 'X' && board[b] === '') return b;
        if (board[b] === 'X' && board[c] === 'X' && board[a] === '') return a;
    }
    
    // Take center
    if (board[4] === '') return 4;
    
    // Take corner
    const corners = [0, 2, 6, 8];
    for (let corner of corners) {
        if (board[corner] === '') return corner;
    }
    
    // Take any available
    for (let i = 0; i < 9; i++) {
        if (board[i] === '') return i;
    }
    
    return -1;
}

function updateCell(index, player) {
    const cell = document.querySelector(`[data-cell="${index}"]`);
    cell.textContent = player === 'X' ? '✕' : '◯';
    cell.classList.add(player === 'X' ? 'x' : 'o');
}

function checkWin() {
    for (let condition of winningConditions) {
        const [a, b, c] = condition;
        if (board[a] && board[a] === board[b] && board[a] === board[c]) {
            highlightWinningCells([a, b, c]);
            return true;
        }
    }
    return false;
}

function checkDraw() {
    return board.every(cell => cell !== '');
}

function highlightWinningCells(cells) {
    cells.forEach(index => {
        document.querySelector(`[data-cell="${index}"]`).classList.add('winner');
    });
}

function resetGame() {
    board = ['', '', '', '', '', '', '', '', ''];
    currentPlayer = 'X';
    gameActive = true;
    
    document.querySelectorAll('.cell').forEach(cell => {
        cell.textContent = '';
        cell.className = 'cell';
    });
    
    document.getElementById('gameStatus').textContent = 'Your Turn (✕)';
}

// Initialize game
function initTicTacToe(isAIMode) {
    window.isAI = isAIMode;
    
    if (isAIMode) {
        document.getElementById('playerO').textContent = '🤖 Computer';
        document.getElementById('gameStatus').textContent = 'Your Turn (✕)';
    } else {
        document.getElementById('gameStatus').textContent = 'Waiting for opponent...';
    }
}
