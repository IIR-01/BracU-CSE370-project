<?php
// Single-file Neon Tic Tac Toe (frontend only)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Neon Tic Tac Toe</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Pixelify+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<style>
:root{
  --bg1:#020617;--accent:#38bdf8;--accent2:#f97316;
  --danger:#f43f5e;--success:#22c55e;--text-main:#e5e7eb;--text-muted:#9ca3af;
  --glass:#020617f0;
}
*{box-sizing:border-box;font-family:"Pixelify Sans",system-ui,sans-serif;}

body{
  margin:0;min-height:100vh;color:var(--text-main);
  background:
    radial-gradient(circle at 0 0,#0b1120,#020617 55%),
    radial-gradient(circle at 100% 100%,#1d4ed8,#020617 45%);
  display:flex;justify-content:center;align-items:flex-start;padding:18px 10px;
}
.shell{max-width:900px;width:100%;}
.container{
  padding:16px;border-radius:18px;background:var(--glass);
  border:1px solid rgba(31,41,55,0.9);box-shadow:0 18px 40px rgba(0,0,0,0.9);
  position:relative;overflow:hidden;
}
.container::before{
  content:"";position:absolute;inset:-40%;pointer-events:none;
  background:
    radial-gradient(circle at 0 0,rgba(56,189,248,0.25),transparent 60%),
    radial-gradient(circle at 100% 100%,rgba(248,113,113,0.25),transparent 55%);
  opacity:0.9;z-index:-1;
}

.header{
  display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;
}
h1{
  margin:0;font-size:1.8rem;letter-spacing:.18em;text-transform:uppercase;
  text-shadow:0 0 14px rgba(56,189,248,0.9),0 0 26px rgba(56,189,248,0.6);
}
.subtitle{
  margin-top:5px;font-size:.84rem;color:var(--text-muted);letter-spacing:.06em;
}
.badge-row{display:flex;align-items:center;gap:8px;margin-top:8px;font-size:.75rem;flex-wrap:wrap;}
.badge{
  padding:4px 11px;border-radius:999px;background:rgba(22,163,74,0.12);
  border:1px solid rgba(22,163,74,0.7);text-transform:uppercase;
  letter-spacing:.1em;color:#bbf7d0;display:inline-flex;align-items:center;gap:6px;
  font-size:.72rem;
}
.dot{
  width:8px;height:8px;border-radius:50%;background:#22c55e;
  box-shadow:0 0 8px rgba(34,197,94,0.9);
}
.right-top{
  text-align:right;font-size:.78rem;color:var(--text-muted);
}
.pill{
  padding:5px 10px;border-radius:999px;border:1px solid rgba(55,65,81,0.9);
  background:rgba(15,23,42,0.9);display:flex;align-items:center;gap:7px;
}
.pill span:first-child{text-transform:uppercase;letter-spacing:.1em;font-size:.72rem;}
.pill input{
  background:transparent;border:none;color:var(--text-main);
  font-size:.82rem;outline:none;text-align:center;min-width:60px;
}

/* layout */
.main{
  display:grid;grid-template-columns:1.4fr 1fr;gap:14px;margin-top:16px;
}
@media(max-width:800px){.main{grid-template-columns:1fr;}}

/* board */
.board{
  border-radius:16px;padding:14px;background:rgba(15,23,42,0.95);
  border:1px solid rgba(55,65,81,0.9);box-shadow:0 0 20px rgba(15,23,42,0.9);
}
.grid{
  --cell-size:110px;
  display:grid;grid-template-columns:repeat(3,var(--cell-size));gap:10px;
  margin:16px 0;justify-content:center;
}
@media(max-width:600px){
  .grid{--cell-size:90px;}
}

.cell{
  width:var(--cell-size);height:var(--cell-size);
  border-radius:18px;border:3px solid rgba(148,163,184,0.9);
  background:#020617;display:flex;align-items:center;justify-content:center;
  font-size:3.2rem;font-weight:700;cursor:pointer;
  box-shadow:0 0 20px rgba(15,23,42,0.9);
  position:relative;
  transition:transform 0.15s, box-shadow 0.15s, border-color 0.15s, background 0.15s;
}
.cell::before{
  content:"";position:absolute;inset:12%;border-radius:14px;
  border:1px solid rgba(31,41,55,0.9);
}
.cell span{
  position:relative;z-index:1;
}
.cell.x span{
  color:#38bdf8;
  text-shadow:0 0 12px rgba(56,189,248,0.9),0 0 24px rgba(56,189,248,0.9);
}
.cell.o span{
  color:#f97316;
  text-shadow:0 0 12px rgba(249,115,22,0.9),0 0 24px rgba(249,115,22,0.9);
}
.cell:hover{
  transform:translateY(-4px) scale(1.03);
  box-shadow:0 0 24px rgba(56,189,248,0.9);
  border-color:#38bdf8;
  background:radial-gradient(circle at 30% 0,#1d4ed8,#020617);
}
.cell.disabled{cursor:not-allowed;opacity:0.65;}
.cell.win{
  border-color:#facc15;
  box-shadow:0 0 30px rgba(250,204,21,1);
  animation:winPulse 0.65s ease-in-out infinite alternate;
}
@keyframes winPulse{
  from{transform:scale(1.03);}
  to{transform:scale(1.06);}
}

/* buttons */
.btn-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;}
.btn{
  border:none;border-radius:999px;padding:7px 14px;font-size:.8rem;font-weight:600;
  cursor:pointer;display:inline-flex;align-items:center;gap:7px;
  background:rgba(15,23,42,0.95);color:var(--text-main);
  border:1px solid rgba(55,65,81,0.9);text-transform:uppercase;letter-spacing:.08em;
}
.btn-primary{
  background:linear-gradient(135deg,#facc15,#f97316);color:#020617;
  box-shadow:0 0 20px rgba(248,181,0,0.8);
}
.btn:hover{
  transform:translateY(-2px);box-shadow:0 0 18px rgba(56,189,248,0.7);
  border-color:rgba(56,189,248,0.9);
}

/* status / scores */
.status-text{
  font-size:.82rem;color:var(--text-muted);margin-top:8px;
  padding:8px 10px;background:rgba(15,23,42,0.7);border-radius:8px;
  border-left:3px solid var(--accent);
}
.score-box{
  border-radius:14px;padding:10px 12px;background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);font-size:.78rem;
}
.score-row{
  display:flex;justify-content:space-between;margin-top:4px;font-size:.86rem;
}
.score-label{color:var(--text-muted);}
.score-value{color:#facc15;font-weight:700;}

.toggle-group{
  margin-top:4px;font-size:.78rem;color:var(--text-muted);
}
.toggle-group label{margin-right:10px;cursor:pointer;}
.toggle-group input{margin-right:3px;}
</style>
</head>
<body>
<div class="shell">
  <div class="container">
    <div class="header">
      <div>
        <h1>Neon Tic Tac Toe</h1>
        <div class="subtitle">
          3×3 cyber grid. Glowing ❌ and ⭕, PvP or AI duel.[web:626]
        </div>
        <div class="badge-row">
          <div class="badge"><div class="dot"></div>Neon Board</div>
          <div class="badge">Vs Human / AI</div>
        </div>
      </div>
      <div class="right-top">
        <div class="pill">
          <span>Name</span>
          <input id="player-name" value="Player" maxlength="16">
        </div>
      </div>
    </div>

    <div class="main">
      <div class="board">
        <div class="toggle-group">
          <label><input type="radio" name="mode" value="pvp" checked> 2 Players (same device)</label>
          <label><input type="radio" name="mode" value="ai"> vs AI (you are X)</label>
        </div>

        <div class="grid" id="grid"></div>

        <div class="btn-row">
          <button class="btn btn-primary" id="new-round-btn">New Round</button>
          <button class="btn" id="reset-scores-btn">Reset Scores</button>
        </div>

        <div class="status-text" id="status-text">
          X starts. Click a cell to place your mark. Three in a row wins.[web:632]
        </div>
      </div>

      <div class="score-box">
        <div style="font-size:.85rem;font-weight:600;margin-bottom:4px;">Scoreboard</div>
        <div class="score-row">
          <div class="score-label" id="score-x-label">X (Player)</div>
          <div class="score-value" id="score-x">0</div>
        </div>
        <div class="score-row">
          <div class="score-label" id="score-o-label">O (Player 2)</div>
          <div class="score-value" id="score-o">0</div>
        </div>
        <div class="score-row">
          <div class="score-label">Draws</div>
          <div class="score-value" id="score-d">0</div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const gridEl = document.getElementById('grid');
const statusText = document.getElementById('status-text');
const scoreXEl = document.getElementById('score-x');
const scoreOEl = document.getElementById('score-o');
const scoreDEl = document.getElementById('score-d');
const scoreXLabel = document.getElementById('score-x-label');
const scoreOLabel = document.getElementById('score-o-label');
const newRoundBtn = document.getElementById('new-round-btn');
const resetScoresBtn = document.getElementById('reset-scores-btn');

let board = Array(9).fill(null);
let current = 'X';
let finished = false;
let mode = 'pvp';
let scores = {X:0,O:0,D:0};

const winPatterns = [
  [0,1,2],[3,4,5],[6,7,8],
  [0,3,6],[1,4,7],[2,5,8],
  [0,4,8],[2,4,6]
];

function renderGrid(){
  gridEl.innerHTML='';
  board.forEach((val,idx)=>{
    const cell=document.createElement('button');
    cell.className='cell';
    cell.dataset.index=idx;
    if(val){
      cell.classList.add(val.toLowerCase());
      const span=document.createElement('span');
      span.textContent=val;
      cell.appendChild(span);
    }
    if(finished || val) cell.classList.add('disabled');
    cell.addEventListener('click',()=>handleCellClick(idx));
    gridEl.appendChild(cell);
  });
}

function checkWinner(){
  for(const [a,b,c] of winPatterns){
    if(board[a] && board[a]===board[b] && board[b]===board[c]){
      return {winner: board[a], line:[a,b,c]};
    }
  }
  if(board.every(v=>v)) return {winner:null,line:[]};
  return null;
}

function updateStatus(){
  if(finished){
    const res=checkWinner();
    if(res && res.winner){
      statusText.textContent=`${res.winner} wins this round.`;
    }else{
      statusText.textContent='Round ends in a draw.';
    }
  }else{
    statusText.textContent=`Turn: ${current}`;
  }
}

function applyWinHighlight(line){
  const cells=gridEl.querySelectorAll('.cell');
  line.forEach(i=>cells[i].classList.add('win'));
}

function handleCellClick(idx){
  if(finished || board[idx]) return;
  board[idx]=current;
  renderGrid();
  const result=checkWinner();
  if(result){
    finished=true;
    if(result.winner){
      scores[result.winner]++;
      applyWinHighlight(result.line);
    }else{
      scores.D++;
    }
    scoreXEl.textContent=scores.X;
    scoreOEl.textContent=scores.O;
    scoreDEl.textContent=scores.D;
    updateStatus();
    return;
  }
  current = current==='X' ? 'O' : 'X';
  updateStatus();
  if(mode==='ai' && current==='O'){
    setTimeout(aiMove,250);
  }
}

function aiMove(){
  if(finished) return;
  let move=null;
  // try winning
  for(let i=0;i<9;i++){
    if(!board[i]){
      board[i]='O';
      if(checkWinner()?.winner==='O'){move=i;break;}
      board[i]=null;
    }
  }
  // block X
  if(move===null){
    for(let i=0;i<9;i++){
      if(!board[i]){
        board[i]='X';
        if(checkWinner()?.winner==='X'){move=i;}
        board[i]=null;
        if(move!==null) break;
      }
    }
  }
  // random
  if(move===null){
    const empties=board.map((v,i)=>v?null:i).filter(v=>v!==null);
    move=empties[Math.floor(Math.random()*empties.length)];
  }
  handleCellClick(move);
}

function newRound(){
  board=Array(9).fill(null);
  finished=false;
  current='X';
  renderGrid();
  updateStatus();
}

newRoundBtn.addEventListener('click',newRound);
resetScoresBtn.addEventListener('click',()=>{
  scores={X:0,O:0,D:0};
  scoreXEl.textContent='0';
  scoreOEl.textContent='0';
  scoreDEl.textContent='0';
  newRound();
});

document.querySelectorAll('input[name="mode"]').forEach(r=>{
  r.addEventListener('change',()=>{
    mode=r.value;
    const name=(document.getElementById('player-name').value.trim()||'Player');
    if(mode==='ai'){
      scoreXLabel.textContent=`X (${name})`;
      scoreOLabel.textContent='O (AI)';
    }else{
      scoreXLabel.textContent='X (Player 1)';
      scoreOLabel.textContent='O (Player 2)';
    }
    newRound();
  });
});

renderGrid();
updateStatus();
</script>
</body>
</html>
