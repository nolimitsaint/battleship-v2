const API_URL = "api.php";
const letters = ["A","B","C","D","E","F","G","H","I","J"];

const gridComputer = document.getElementById("gridComputer");
const gridPlayer = document.getElementById("gridPlayer");

const colLabels1 = document.getElementById("colLabels1");
const rowLabels1 = document.getElementById("rowLabels1");
const colLabels2 = document.getElementById("colLabels2");
const rowLabels2 = document.getElementById("rowLabels2");

const pShotsEl = document.getElementById("pShots");
const pHitsEl = document.getElementById("pHits");
const pAccEl = document.getElementById("pAcc");
const cShotsEl = document.getElementById("cShots");
const cHitsEl = document.getElementById("cHits");

const pRemainEl = document.getElementById("pRemain");
const cRemainEl = document.getElementById("cRemain");
const phaseEl = document.getElementById("phase");
const winnerEl = document.getElementById("winner");

const statusEl = document.getElementById("status");
const logEl = document.getElementById("log");

const restartBtn = document.getElementById("restartBtn");
const newBtn = document.getElementById("newBtn");

const tilesComputer = new Map(); // you click here
const tilesPlayer = new Map();   // computer marks here

function setStatus(msg){ statusEl.textContent = msg; }

function pushLog(text, muted=false){
  const li = document.createElement("li");
  li.textContent = text;
  if(muted) li.classList.add("muted");
  logEl.prepend(li);
}

function coordFromRC(r,c){ return `${letters[r]}${c+1}`; }

function buildLabels(colEl, rowEl){
  colEl.innerHTML = "";
  rowEl.innerHTML = "";
  for(let c=1;c<=10;c++){
    const d=document.createElement("div");
    d.textContent = c;
    colEl.appendChild(d);
  }
  for(let r=0;r<10;r++){
    const d=document.createElement("div");
    d.textContent = letters[r];
    rowEl.appendChild(d);
  }
}

function buildGrid(el, map, clickable){
  el.innerHTML = "";
  map.clear();

  for(let r=0;r<10;r++){
    for(let c=0;c<10;c++){
      const coord = coordFromRC(r,c);
      const btn = document.createElement("button");
      btn.type="button";
      btn.className="tile";
      btn.dataset.coord = coord;

      if(clickable){
        btn.setAttribute("aria-label", `Fire at ${coord}`);
        btn.addEventListener("click", () => fire(coord));
      }else{
        btn.setAttribute("aria-label", `Your board ${coord}`);
        btn.disabled = true;
      }

      el.appendChild(btn);
      map.set(coord, btn);
    }
  }
}

function mark(map, coord, result){
  const btn = map.get(coord);
  if(!btn) return;
  btn.classList.remove("hit","miss");
  btn.classList.add(result === "hit" ? "hit" : "miss");
  if(map === tilesComputer) btn.disabled = true;
}

function clearBoards(){
  for(const btn of tilesComputer.values()){
    btn.disabled = false;
    btn.classList.remove("hit","miss");
  }
  for(const btn of tilesPlayer.values()){
    btn.classList.remove("hit","miss");
  }
  logEl.innerHTML = "";
}

function lockIfGameOver(state){
  const locked = state.phase === "GAME_OVER";
  for(const btn of tilesComputer.values()){
    btn.disabled = locked || btn.classList.contains("hit") || btn.classList.contains("miss");
  }
}

function updateStats(state){
  pShotsEl.textContent = state.player.shots;
  pHitsEl.textContent = state.player.hits;
  const acc = state.player.shots === 0 ? 0 : Math.round((state.player.hits / state.player.shots) * 100);
  pAccEl.textContent = `${acc}%`;

  cShotsEl.textContent = state.computer.shots;
  cHitsEl.textContent = state.computer.hits;

  pRemainEl.textContent = state.player.remainingShipCells;
  cRemainEl.textContent = state.computer.remainingShipCells;

  phaseEl.textContent = state.phase;
  winnerEl.textContent = state.winner ?? "—";

  lockIfGameOver(state);
}

async function api(action, payload){
  const res = await fetch(`${API_URL}?action=${encodeURIComponent(action)}`, {
    method: "POST",
    headers: { "Content-Type":"application/json" },
    body: JSON.stringify(payload || {})
  });
  const data = await res.json().catch(() => null);
  if(!data || !data.ok) throw new Error(data?.error || "Server error / invalid JSON");
  return data;
}

async function load(){
  const data = await api("state");
  updateStats(data.state);
  setStatus("Fire at the Computer Board (A1–J10).");
  pushLog("Loaded game state.", true);

  // Rehydrate marks
  for (const [coord, result] of Object.entries(data.marks.playerShotsOnComputer)){
    mark(tilesComputer, coord, result);
  }
  for (const [coord, result] of Object.entries(data.marks.computerShotsOnPlayer)){
    mark(tilesPlayer, coord, result);
  }
}

async function restartCurrent(){
  clearBoards();
  const data = await api("restart");
  updateStats(data.state);
  setStatus("Restarted current game (same ship layout). Fire again.");
  pushLog("Restarted current game (ships unchanged).", true);
}

async function newGame(){
  clearBoards();
  const data = await api("new");
  updateStats(data.state);
  setStatus("New game started (new ship layout). Fire again.");
  pushLog("New game started (ships re-randomized).", true);
}

async function fire(coord){
  const btn = tilesComputer.get(coord);
  if(!btn || btn.disabled) return;

  setStatus(`Firing at ${coord}...`);

  try{
    const data = await api("fire", { coord });
    updateStats(data.state);

    // Player shot result
    if(data.playerShot){
      mark(tilesComputer, data.playerShot.coord, data.playerShot.result);
      pushLog(`You: ${data.playerShot.coord} → ${data.playerShot.result.toUpperCase()}`);
    }

    // Computer shot result
    if(data.computerShot){
      mark(tilesPlayer, data.computerShot.coord, data.computerShot.result);
      pushLog(`Computer: ${data.computerShot.coord} → ${data.computerShot.result.toUpperCase()}`);
    }

    if(data.state.phase === "GAME_OVER"){
      const who = data.state.winner === "player" ? "You win! 🎉" : "Computer wins! 🤖";
      setStatus(`GAME OVER — ${who}`);
      pushLog(`GAME OVER — winner: ${data.state.winner}`, true);
    } else {
      setStatus("Your turn: fire again.");
    }
  }catch(err){
    setStatus(`Error: ${err.message}`);
    pushLog(`Error: ${err.message}`);
  }
}

restartBtn.addEventListener("click", restartCurrent);
newBtn.addEventListener("click", newGame);

buildLabels(colLabels1, rowLabels1);
buildLabels(colLabels2, rowLabels2);
buildGrid(gridComputer, tilesComputer, true);
buildGrid(gridPlayer, tilesPlayer, false);

load().catch(e => setStatus(`Load error: ${e.message}`));
