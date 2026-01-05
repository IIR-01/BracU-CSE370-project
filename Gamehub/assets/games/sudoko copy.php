<?php
// Neon Sudoku - single file (frontend only)
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Neon Sudoku</title>
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
.shell{max-width:1200px;width:100%;}
.container{
  padding:16px;border-radius:18px;background:var(--glass);
  border:1px solid rgba(31,41,55,0.95);box-shadow:0 18px 40px rgba(0,0,0,0.95);
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
.pill select{
  background:transparent;border:none;color:var(--text-main);
  font-size:.82rem;outline:none;text-align:center;min-width:80px;cursor:pointer;
}

/* layout */
.main{
  display:grid;grid-template-columns:1.6fr 1.2fr;gap:14px;margin-top:16px;
}
@media(max-width:960px){.main{grid-template-columns:1fr;}}

/* board */
.board{
  border-radius:16px;padding:12px;background:rgba(15,23,42,0.96);
  border:1px solid rgba(55,65,81,0.95);box-shadow:0 0 22px rgba(15,23,42,0.95);
}
.zone-title{
  font-size:.8rem;color:var(--text-muted);margin-bottom:6px;text-transform:uppercase;
  letter-spacing:.08em;font-weight:600;
}

.sudoku-wrap{
  display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap;
}
.grid{
  --cell-size:44px;
  display:grid;grid-template-columns:repeat(9,var(--cell-size));
  grid-template-rows:repeat(9,var(--cell-size));
  gap:2px;
  border-radius:18px;padding:6px;
  background:radial-gradient(circle at 0 0,#0f172a,#020617);
  box-shadow:0 0 24px rgba(15,23,42,0.9);
}
@media(max-width:700px){
  .grid{--cell-size:34px;}
}
.cell{
  width:var(--cell-size);height:var(--cell-size);
  border-radius:10px;border:1px solid rgba(55,65,81,0.9);
  background:#020617;display:flex;align-items:center;justify-content:center;
  position:relative;
}
.cell input{
  width:100%;height:100%;border:none;outline:none;
  text-align:center;font-size:1.2rem;color:var(--text-main);
  background:transparent;caret-color:#facc15;
}
.cell.given input{color:#38bdf8;font-weight:700;}
.cell.user input{color:#fbbf24;}
.cell.invalid input{color:#f97316;}
.cell.highlight{box-shadow:0 0 14px rgba(56,189,248,0.9);border-color:#38bdf8;}

.cell::before{
  content:"";position:absolute;inset:0;border-radius:10px;
  border:1px solid rgba(15,23,42,0.9);opacity:.65;
}

/* thicker box borders */
.cell[data-box-right="1"]{border-right:2px solid #64748b;}
.cell[data-box-bottom="1"]{border-bottom:2px solid #64748b;}

/* side panel */
.side-panel{
  border-radius:16px;padding:10px 12px;background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);min-width:230px;
}
.side-section-title{
  font-size:.78rem;color:var(--text-muted);text-transform:uppercase;
  letter-spacing:.08em;margin-bottom:4px;font-weight:600;
}
.status-text{
  font-size:.82rem;color:var(--text-muted);margin-top:8px;
  padding:8px 10px;background:rgba(15,23,42,0.8);border-radius:8px;
  border-left:3px solid var(--accent);
}

/* buttons */
.btn-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px;}
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
.btn:disabled{opacity:.6;cursor:not-allowed;}

/* guide */
.guide-box{
  border-radius:14px;padding:10px 12px;background:rgba(15,23,42,0.9);
  border:1px solid rgba(55,65,81,0.9);font-size:.8rem;margin-top:10px;
}
.guide-step{margin-bottom:6px;}
.guide-step span{color:#facc15;font-weight:600;}

/* notifications */
.notification{
  position:fixed;top:18px;right:18px;padding:10px 16px;
  border-radius:10px;background:rgba(15,23,42,0.95);
  border:1px solid rgba(56,189,248,0.8);color:var(--text-main);
  font-size:.82rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,0.85);
  z-index:999;max-width:280px;
}
.notification.success{border-color:#22c55e;background:rgba(22,163,74,0.18);}
.notification.warning{border-color:#fbbf24;background:rgba(234,179,8,0.2);}
.notification.danger{border-color:#f97316;background:rgba(248,113,113,0.18);}
</style>
</head>
<body>
<div class="shell">
  <div class="container">
    <div class="header">
      <div>
        <h1>Neon Sudoku</h1>
        <div class="subtitle">
          9×9 neon puzzle grid with hint logic, beginner guide, and auto‑complete solver. 🔢
        </div>
        <div class="badge-row">
          <div class="badge"><div class="dot"></div>Logic‑Driven</div>
          <div class="badge">Auto Solve</div>
          <div class="badge">Guided Play</div>
        </div>
      </div>
      <div class="right-top">
        <div class="pill">
          <span>Difficulty</span>
          <select id="difficulty">
            <option value="easy" selected>Easy 😊</option>
            <option value="medium">Medium 😼</option>
            <option value="hard">Hard 🤯</option>
          </select>
        </div>
      </div>
    </div>

    <div class="main">
      <div class="board">
        <div class="zone-title">Sudoku Grid (9×9)</div>
        <div class="sudoku-wrap">
          <div class="grid" id="sudoku-grid"></div>

          <div class="side-panel">
            <div class="side-section-title">Controls</div>
            <div class="btn-row">
              <button class="btn btn-primary" id="new-puzzle-btn">New Puzzle</button>
              <button class="btn" id="check-btn">Check</button>
              <button class="btn" id="hint-btn">Hint</button>
              <button class="btn" id="solve-btn">Auto Complete</button>
              <button class="btn" id="clear-btn">Clear</button>
            </div>

            <div class="side-section-title" style="margin-top:10px;">Status</div>
            <div class="status-text" id="status-text">
              Fill each row, column, and 3×3 box with digits 1–9 without repeats.[web:685][web:691]
            </div>
          </div>
        </div>
      </div>

      <div>
        <div class="guide-box">
          <div style="font-size:.85rem;font-weight:600;margin-bottom:4px;">Quick Logic Guide 🧠</div>
          <div class="guide-step">
            <span>1.</span> Start with rows/columns that already have many numbers. The remaining digits have fewer possible positions.[web:688][web:694]
          </div>
          <div class="guide-step">
            <span>2.</span> Each 3×3 box must contain 1–9 exactly once. Use this to eliminate impossible cells.[web:685][web:691]
          </div>
          <div class="guide-step">
            <span>3.</span> Use “Check” to highlight conflicts. Use “Hint” to fill a logically valid cell. “Auto Complete” will run a backtracking solver to finish the puzzle.[web:684][web:695]
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<script>
// -------- STATE --------
const gridEl = document.getElementById('sudoku-grid');
const statusText = document.getElementById('status-text');
const difficultySelect = document.getElementById('difficulty');
const newPuzzleBtn = document.getElementById('new-puzzle-btn');
const checkBtn = document.getElementById('check-btn');
const hintBtn = document.getElementById('hint-btn');
const solveBtn = document.getElementById('solve-btn');
const clearBtn = document.getElementById('clear-btn');

let board = [];       // 9x9 numbers (0 for empty)
let given = [];       // bool matrix: true if cell is part of puzzle
let solving = false;

// -------- HELPERS --------
function showNotification(msg,type='success'){
  const n=document.createElement('div');
  n.className='notification '+type;
  n.textContent=msg;
  document.body.appendChild(n);
  setTimeout(()=>n.remove(),2600);
}

function createEmptyBoard(){
  board = Array.from({length:9},()=>Array(9).fill(0));
  given = Array.from({length:9},()=>Array(9).fill(false));
}

function renderBoard(){
  gridEl.innerHTML='';
  for(let r=0;r<9;r++){
    for(let c=0;c<9;c++){
      const cell=document.createElement('div');
      cell.className='cell';
      if((c+1)%3===0 && c!==8) cell.dataset.boxRight='1';
      if((r+1)%3===0 && r!==8) cell.dataset.boxBottom='1';

      const input=document.createElement('input');
      input.type='text';
      input.maxLength='1';
      input.dataset.row=r;
      input.dataset.col=c;

      const val = board[r][c];
      if(val!==0) input.value=val.toString();

      if(given[r][c]){
        cell.classList.add('given');
        input.readOnly=true;
      }else{
        cell.classList.add('user');
        input.addEventListener('input',onInput);
        input.addEventListener('focus',()=>highlightRelated(r,c));
        input.addEventListener('blur',clearHighlights);
      }

      cell.appendChild(input);
      gridEl.appendChild(cell);
    }
  }
}

function onInput(e){
  const v=e.target.value.replace(/[^1-9]/g,'');
  e.target.value=v;
  const r=parseInt(e.target.dataset.row,10);
  const c=parseInt(e.target.dataset.col,10);
  board[r][c] = v ? parseInt(v,10) : 0;
}

function clearHighlights(){
  document.querySelectorAll('.cell').forEach(c=>{
    c.classList.remove('highlight');
    c.classList.remove('invalid');
  });
}

function highlightRelated(row,col){
  clearHighlights();
  const cells = gridEl.querySelectorAll('.cell');
  cells.forEach((cell,idx)=>{
    const r=Math.floor(idx/9), c=idx%9;
    const sameRow=r===row;
    const sameCol=c===col;
    const sameBox=(Math.floor(r/3)===Math.floor(row/3) && Math.floor(c/3)===Math.floor(col/3));
    if(sameRow || sameCol || sameBox) cell.classList.add('highlight');
  });
}

// -------- LOGIC CHECKS --------
function isValid(board,r,c,num){
  // row
  for(let j=0;j<9;j++){
    if(board[r][j]===num && j!==c) return false;
  }
  // col
  for(let i=0;i<9;i++){
    if(board[i][c]===num && i!==r) return false;
  }
  // box
  const br=Math.floor(r/3)*3;
  const bc=Math.floor(c/3)*3;
  for(let i=0;i<3;i++){
    for(let j=0;j<3;j++){
      const rr=br+i, cc=bc+j;
      if(board[rr][cc]===num && (rr!==r || cc!==c)) return false;
    }
  }
  return true;
}

function checkConflicts(){
  clearHighlights();
  let hasConflict=false;
  const cells = gridEl.querySelectorAll('.cell');
  for(let r=0;r<9;r++){
    for(let c=0;c<9;c++){
      const val=board[r][c];
      if(val===0) continue;
      if(!isValid(board,r,c,val)){
        hasConflict=true;
        const idx=r*9+c;
        cells[idx].classList.add('invalid');
      }
    }
  }
  if(hasConflict){
    statusText.textContent='There are conflicts (highlighted in orange). Fix them before solving.';
    showNotification('⚠️ Conflicts found – check orange cells.','warning');
  }else{
    statusText.textContent='No conflicts detected so far. Keep going.';
    showNotification('✅ No conflicts detected.','success');
  }
}

// -------- SOLVER (BACKTRACKING) --------
// classic 9×9 backtracking as in standard solvers[web:695][web:689]
function findEmpty(board){
  for(let r=0;r<9;r++){
    for(let c=0;c<9;c++){
      if(board[r][c]===0) return {r,c};
    }
  }
  return null;
}

function solveSudoku(board){
  const empty=findEmpty(board);
  if(!empty) return true;
  const {r,c}=empty;

  for(let num=1;num<=9;num++){
    if(isValid(board,r,c,num)){
      board[r][c]=num;
      if(solveSudoku(board)) return true;
      board[r][c]=0;
    }
  }
  return false;
}

function autoSolve(){
  if(solving) return;
  solving=true;
  checkConflicts();
  // copy board
  const copy=board.map(row=>row.slice());
  if(!solveSudoku(copy)){
    showNotification('❌ This puzzle has no solution (given current entries).','danger');
    solving=false;
    return;
  }
  board = copy;
  // convert all user cells to "given" style (locked solved board)
  given = given.map((row,r)=>row.map((g,c)=>g || board[r][c]!==0));
  renderBoard();
  statusText.textContent='Puzzle auto‑completed using backtracking search.[web:684][web:695]';
  showNotification('✨ Auto complete finished.','success');
  solving=false;
}

// -------- HINT (single cell) --------
function giveHint(){
  const copy=board.map(row=>row.slice());
  if(!solveSudoku(copy)){
    showNotification('❌ Cannot hint – puzzle unsolvable in current state.','danger');
    return;
  }
  // find first empty cell in current board, fill from solved copy
  for(let r=0;r<9;r++){
    for(let c=0;c<9;c++){
      if(board[r][c]===0){
        board[r][c]=copy[r][c];
        given[r][c]=false; // still editable but colored as user
        renderBoard();
        statusText.textContent=`Hint filled one cell at row ${r+1}, col ${c+1}.`;
        showNotification('💡 Hint applied.','success');
        return;
      }
    }
  }
  showNotification('Puzzle already complete – no hint needed.','success');
}

// -------- PUZZLE GENERATION (SIMPLE PRESETS) --------
// To keep it fully client‑side and deterministic, use a known valid solution + remove cells per difficulty.[web:681][web:690]
const BASE_SOLUTION = [
  [5,3,4,6,7,8,9,1,2],
  [6,7,2,1,9,5,3,4,8],
  [1,9,8,3,4,2,5,6,7],
  [8,5,9,7,6,1,4,2,3],
  [4,2,6,8,5,3,7,9,1],
  [7,1,3,9,2,4,8,5,6],
  [9,6,1,5,3,7,2,8,4],
  [2,8,7,4,1,9,6,3,5],
  [3,4,5,2,8,6,1,7,9]
]; // valid solved grid[web:695]

function shuffleArray(arr){
  for(let i=arr.length-1;i>0;i--){
    const j=Math.floor(Math.random()*(i+1));
    [arr[i],arr[j]]=[arr[j],arr[i]];
  }
  return arr;
}

// Simple randomization: permute digits, maybe swap some rows in band/stack (still valid).[web:696]
function randomizeSolution(){
  // copy base
  let grid=BASE_SOLUTION.map(row=>row.slice());
  // permute numbers 1-9
  const digits=[1,2,3,4,5,6,7,8,9];
  shuffleArray(digits);
  const mapDigit = d => digits[d-1];
  grid = grid.map(row=>row.map(mapDigit));
  return grid;
}

function generatePuzzle(diff){
  const full=randomizeSolution();
  board = full.map(row=>row.slice());
  given = Array.from({length:9},()=>Array(9).fill(true));

  let removeCount;
  if(diff==='easy')   removeCount=35;
  else if(diff==='medium') removeCount=45;
  else removeCount=55; // hard

  let positions=[];
  for(let r=0;r<9;r++) for(let c=0;c<9;c++) positions.push({r,c});
  shuffleArray(positions);

  let removed=0;
  for(const {r,c} of positions){
    if(removed>=removeCount) break;
    board[r][c]=0;
    given[r][c]=false;
    removed++;
  }
}

// -------- CLEAR / NEW --------
function clearUserEntries(){
  for(let r=0;r<9;r++){
    for(let c=0;c<9;c++){
      if(!given[r][c]) board[r][c]=0;
    }
  }
  renderBoard();
  statusText.textContent='Cleared your entries, given clues kept.';
}

// -------- EVENTS --------
newPuzzleBtn.addEventListener('click',()=>{
  createEmptyBoard();
  generatePuzzle(difficultySelect.value);
  renderBoard();
  statusText.textContent=`New ${difficultySelect.value.toUpperCase()} puzzle loaded. Use logic first, then hints/auto‑complete if stuck.`;
  showNotification('🧩 New puzzle generated.','success');
});

checkBtn.addEventListener('click',checkConflicts);
hintBtn.addEventListener('click',giveHint);
solveBtn.addEventListener('click',autoSolve);
clearBtn.addEventListener('click',clearUserEntries);

// init first puzzle
createEmptyBoard();
generatePuzzle('easy');
renderBoard();
statusText.textContent='Welcome to Neon Sudoku. Start with the easiest rows/columns and use the guide on the right.';
</script>
</body>
</html>
