// Stone Paper Scissors (SZR) Game Logic
let playerChoice = null;
let playerWins = 0;
let opponentWins = 0;
let draws = 0;
let isPlaying = false;

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
function initSZR(aiMode) {
    window.isAI = aiMode;
    resetSZRGame();
}

const choices = {
    stone: { beats: 'scissors', emoji: '🪨' },
    paper: { beats: 'stone', emoji: '📄' },
    scissors: { beats: 'paper', emoji: '✂️' }
};

function selectSZRChoice(choice) {
    if (isPlaying) return;
    
    playerChoice = choice;
    document.querySelectorAll('.szr-choice').forEach(btn => {
        btn.classList.remove('selected');
    });
    event.target.closest('.szr-choice').classList.add('selected');
    document.getElementById('gameStatus').textContent = `You selected ${choice}! Click Play!`;
}

function playSZR() {
    if (!playerChoice || isPlaying) {
        alert('Please select Stone, Paper, or Scissors first!');
        return;
    }
    
    isPlaying = true;
    
    // Random opponent choice
    const opponentChoices = Object.keys(choices);
    const opponentChoice = opponentChoices[Math.floor(Math.random() * opponentChoices.length)];
    
    // Show choices
    document.getElementById('playerChoiceDisplay').textContent = choices[playerChoice].emoji;
    document.getElementById('opponentChoiceDisplay').textContent = choices[opponentChoice].emoji;
    
    // Determine winner
    let result;
    if (playerChoice === opponentChoice) {
        result = 'draw';
        draws++;
        document.getElementById('draw-score').textContent = draws;
        document.getElementById('gameStatus').textContent = '🤝 Draw!';
        saveGameResult('draw', 0);
        if (typeof saveScores === 'function') saveScores();
    } else if (choices[playerChoice].beats === opponentChoice) {
        result = 'win';
        playerWins++;
        document.getElementById('player-score').textContent = playerWins;
        document.getElementById('gameStatus').textContent = '🎉 You Win!';
        saveGameResult('win', 1);
        if (typeof saveScores === 'function') saveScores();
    } else {
        result = 'lose';
        opponentWins++;
        document.getElementById('opponent-score').textContent = opponentWins;
        document.getElementById('gameStatus').textContent = '😔 You Lose!';
        saveGameResult('loss', 0);
        if (typeof saveScores === 'function') saveScores();
    }
    
    // Show result
    const resultDiv = document.getElementById('szrResult');
    resultDiv.className = `szr-result ${result}`;
    resultDiv.style.display = 'block';
    
    setTimeout(() => {
        isPlaying = false;
        playerChoice = null;
        document.querySelectorAll('.szr-choice').forEach(btn => {
            btn.classList.remove('selected');
        });
        resultDiv.style.display = 'none';
        document.getElementById('playerChoiceDisplay').textContent = '?';
        document.getElementById('opponentChoiceDisplay').textContent = '?';
        document.getElementById('gameStatus').textContent = 'Make your choice!';
    }, 2000);
}

function resetSZRGame() {
    playerWins = 0;
    opponentWins = 0;
    draws = 0;
    playerChoice = null;
    isPlaying = false;
    document.getElementById('player-score').textContent = '0';
    document.getElementById('opponent-score').textContent = '0';
    document.getElementById('draw-score').textContent = '0';
    document.getElementById('gameStatus').textContent = 'Make your choice!';
    document.getElementById('playerChoiceDisplay').textContent = '?';
    document.getElementById('opponentChoiceDisplay').textContent = '?';
    document.getElementById('szrResult').style.display = 'none';
}

function initSZR(isAIMode) {
    window.isAI = isAIMode;
    resetSZRGame();
}
