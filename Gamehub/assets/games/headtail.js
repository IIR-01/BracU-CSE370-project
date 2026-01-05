// Head or Tail (Coin Flip) Game Logic
let playerChoice = null;
let playerWins = 0;
let opponentWins = 0;
let isFlipping = false;

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
function initHeadTail(aiMode) {
    window.isAI = aiMode;
    resetHeadTailGame();
}

function selectChoice(choice) {
    if (isFlipping) return;
    
    playerChoice = choice;
    document.querySelectorAll('.choice-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    event.target.classList.add('selected');
    document.getElementById('gameStatus').textContent = `You chose ${choice}! Click Flip!`;
}

function flipCoin() {
    if (!playerChoice || isFlipping) {
        alert('Please select Heads or Tails first!');
        return;
    }
    
    isFlipping = true;
    const coin = document.getElementById('coin');
    const flipBtn = document.getElementById('flipButton');
    
    coin.classList.add('flipping');
    flipBtn.disabled = true;
    
    // Random result
    const result = Math.random() < 0.5 ? 'heads' : 'tails';
    
    setTimeout(() => {
        coin.classList.remove('flipping');
        
        if (result === playerChoice.toLowerCase()) {
            playerWins++;
            document.getElementById('player-score').textContent = playerWins;
            document.getElementById('gameStatus').textContent = `🎉 ${result.toUpperCase()}! You Win!`;
            saveGameResult('win', 1);
            if (typeof saveScores === 'function') saveScores();
        } else {
            opponentWins++;
            document.getElementById('opponent-score').textContent = opponentWins;
            document.getElementById('gameStatus').textContent = `${result.toUpperCase()}! You Lose!`;
            saveGameResult('loss', 0);
            if (typeof saveScores === 'function') saveScores();
        }
        
        isFlipping = false;
        flipBtn.disabled = false;
        playerChoice = null;
        document.querySelectorAll('.choice-btn').forEach(btn => {
            btn.classList.remove('selected');
        });
    }, 1000);
}

function resetHeadTailGame() {
    playerWins = 0;
    opponentWins = 0;
    playerChoice = null;
    isFlipping = false;
    document.getElementById('player-score').textContent = '0';
    document.getElementById('opponent-score').textContent = '0';
    document.getElementById('gameStatus').textContent = 'Choose Heads or Tails!';
    document.querySelectorAll('.choice-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
}

function initHeadTail(isAIMode) {
    window.isAI = isAIMode;
    resetHeadTailGame();
}
