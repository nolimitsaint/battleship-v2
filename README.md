# Battleship V2+ (CPSC 3750)

Single-player Battleship web app built using “vibe coding” (AI-assisted development) with intentional architecture decisions.  
**Server owns truth**: ship placement, turn logic, win conditions, and state transitions live on the PHP server.

---

## ✅ Major Iterations Implemented

### Iteration 1 — New Game vs Restart Current Game (Server-Controlled)
- **New Game** generates a fresh ship layout for both sides.
- **Restart Current Game** keeps the existing ship layouts but resets shots/hits/stats.
- This is enforced on the **server** (client cannot “fake” state with refresh or UI tricks).

### Iteration 2 — Computer Fires Back (Turn-Based Logic)
- After each player shot, the server automatically executes a **computer shot**.
- The client renders both results and logs the turn history.
- Win conditions are enforced on the server:
  - Player wins if all computer ship cells are hit.
  - Computer wins if all player ship cells are hit.

---

## 🧠 Architecture Snapshot (Client vs Server)

**Client (HTML/CSS/JS)**
- Renders both boards (computer board + player board)
- Sends actions to server: `state`, `new`, `restart`, `fire`
- Updates UI strictly from server JSON response (no client-side hit logic)

**Server (PHP / Session State)**
- Owns all game state in `$_SESSION['game']`
- Places ships for both sides
- Validates shots (prevents duplicates)
- Applies turn logic (player shot → computer shot)
- Tracks hits/shots and determines `GAME_OVER` + `winner`

**State transitions (server-enforced)**
- `new` → fresh ships + reset tracking → `PLAYER_TURN`
- `restart` → keep ships, reset tracking → `PLAYER_TURN`
- `fire` (PLAYER_TURN) → resolve player shot → resolve computer shot → check winner  
  - stays `PLAYER_TURN` OR transitions to `GAME_OVER`

---

## ▶️ How to Run Locally
From the project folder:

```bash
php -S localhost:8000
