// Dice Roll Game Logic
let playerScore = 0;
let opponentScore = 0;
let isRolling = false;

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
function initDiceRoll(aiMode) {
    window.isAI = aiMode;
    resetDiceGame();
}

function rollDice() {
    if (isRolling) return;
    
    isRolling = true;
    const dice1 = document.getElementById('dice1');
    const dice2 = document.getElementById('dice2');
    const rollBtn = document.getElementById('rollButton');
    
    dice1.classList.add('rolling');
    dice2.classList.add('rolling');
    rollBtn.disabled = true;
    
    // Simulate rolling animation
    let rolls = 0;
    const rollInterval = setInterval(() => {
        dice1.textContent = Math.floor(Math.random() * 6) + 1;
        dice2.textContent = Math.floor(Math.random() * 6) + 1;
        rolls++;
        
        if (rolls > 10) {
            clearInterval(rollInterval);
            dice1.classList.remove('rolling');
            dice2.classList.remove('rolling');
            
            const total = parseInt(dice1.textContent) + parseInt(dice2.textContent);
            playerScore += total;
            document.getElementById('player-score').textContent = playerScore;
            document.getElementById('gameStatus').textContent = `You rolled ${total}!`;
            
            // Check win condition (first to 50)
            if (playerScore >= 50) {
                document.getElementById('gameStatus').textContent = '🎉 You Win!';
                saveGameResult('win', playerScore);
                if (typeof saveScores === 'function') saveScores();
                rollBtn.disabled = true;
                return;
            }
            
            // AI turn in solo mode
            if (window.isAI) {
                setTimeout(() => aiRoll(), 1000);
            } else {
                isRolling = false;
                rollBtn.disabled = false;
            }
        }
    }, 100);
}

function aiRoll() {
    const aiDice1 = Math.floor(Math.random() * 6) + 1;
    const aiDice2 = Math.floor(Math.random() * 6) + 1;
    const aiTotal = aiDice1 + aiDice2;
    
    opponentScore += aiTotal;
    document.getElementById('opponent-score').textContent = opponentScore;
    document.getElementById('gameStatus').textContent = `Computer rolled ${aiTotal}. Your turn!`;
    
    // Check AI win condition
    if (opponentScore >= 50) {
        document.getElementById('gameStatus').textContent = '😔 You Lose!';
        saveGameResult('loss', playerScore);
        if (typeof saveScores === 'function') saveScores();
        document.getElementById('rollButton').disabled = true;
        return;
    }
    
    isRolling = false;
    document.getElementById('rollButton').disabled = false;
}

function resetDiceGame() {
    playerScore = 0;
    opponentScore = 0;
    document.getElementById('player-score').textContent = '0';
    document.getElementById('opponent-score').textContent = '0';
    document.getElementById('dice1').textContent = '?';
    document.getElementById('dice2').textContent = '?';
    document.getElementById('gameStatus').textContent = 'Roll the dice!';
}

function initDiceRoll(isAIMode) {
    window.isAI = isAIMode;
    resetDiceGame();
}
