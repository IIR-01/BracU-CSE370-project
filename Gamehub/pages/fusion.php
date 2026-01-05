<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Fusion Mode</title>
  <!-- Load CSS for all games -->
  <link rel="stylesheet" href="../assets/games/diceroll.css">
  <link rel="stylesheet" href="../assets/games/headtail.css">
  <link rel="stylesheet" href="../assets/games/szr.css">
  <link rel="stylesheet" href="../assets/games/tictactoe.css">
</head>
<body>
  <h2>⚡ Fusion Mode</h2>
  <p id="fusionStatus">Get ready to fuse games!</p>

  <!-- Dice Roll -->
  <div id="diceGame" class="fusion-game">
    <div id="dice1">?</div>
    <div id="dice2">?</div>
    <p>Player Score: <span id="player-score">0</span></p>
    <p>Opponent Score: <span id="opponent-score">0</span></p>
    <button id="rollButton" onclick="rollDice()">Roll</button>
    <p id="gameStatus">Roll the dice!</p>
  </div>

  <!-- Head or Tail -->
  <div id="headtailGame" class="fusion-game" style="display:none;">
    <p>Choose your side:</p>
    <button onclick="chooseSide('head')">Head</button>
    <button onclick="chooseSide('tail')">Tail</button>
    <button id="flipButton" onclick="flipCoin()">Flip Coin</button>
    <p id="coinResult">Waiting for flip...</p>
  </div>

  <!-- Stone Paper Scissors -->
  <div id="spsGame" class="fusion-game" style="display:none;">
    <p>Choose your move:</p>
    <button onclick="playSPS('stone')">✊ Stone</button>
    <button onclick="playSPS('paper')">✋ Paper</button>
    <button onclick="playSPS('scissors')">✌️ Scissors</button>
    <p id="spsResult">Make your choice!</p>
  </div>

  <!-- Tic Tac Toe -->
  <div id="tictactoeGame" class="fusion-game" style="display:none;">
    <div id="ticTacToeBoard" class="board">
      <div class="row">
        <div class="cell" data-cell="0"></div>
        <div class="cell" data-cell="1"></div>
        <div class="cell" data-cell="2"></div>
      </div>
      <div class="row">
        <div class="cell" data-cell="3"></div>
        <div class="cell" data-cell="4"></div>
        <div class="cell" data-cell="5"></div>
      </div>
      <div class="row">
        <div class="cell" data-cell="6"></div>
        <div class="cell" data-cell="7"></div>
        <div class="cell" data-cell="8"></div>
      </div>
    </div>
    <p id="tttStatus">Your move!</p>
  </div>

  <button id="nextGame">Next Game</button>

  <!-- Load JS for all games -->
  <script src="../assets/games/diceroll.js"></script>
  <script src="../assets/games/headtail.js"></script>
  <script src="../assets/games/szr.js"></script>
  <script src="../assets/games/tictactoe.js"></script>

  <script>
    const fusionGames = ["diceGame", "headtailGame", "spsGame", "tictactoeGame"];
    let currentGame = 0;

    function showGame(index) {
      fusionGames.forEach(id => document.getElementById(id).style.display = "none");
      document.getElementById(fusionGames[index]).style.display = "block";
      document.getElementById("fusionStatus").textContent = "Playing " + fusionGames[index];
      // Call init functions if they exist
      switch(fusionGames[index]) {
        case "diceGame": if (typeof initDiceRoll === "function") initDiceRoll(true); break;
        case "headtailGame": if (typeof initHeadOrTail === "function") initHeadOrTail(true); break;
        case "spsGame": if (typeof initSPS === "function") initSPS(true); break;
        case "tictactoeGame": if (typeof initTicTacToe === "function") initTicTacToe(true); break;
      }
    }

    document.getElementById("nextGame").addEventListener("click", () => {
      currentGame++;
      if (currentGame < fusionGames.length) {
        showGame(currentGame);
      } else {
        document.getElementById("fusionStatus").textContent = "Fusion complete!";
        document.getElementById("nextGame").style.display = "none";
      }
    });

    // Start with Dice Roll
    showGame(currentGame);
  </script>
</body>
</html>
