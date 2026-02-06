# AI Prompt Log — Battleship V2+

1) **Prompt:** “Add a server endpoint to restart the game but keep the same ship layout.”
   - **Why:** Needed a major iteration that changes behavior while keeping server as source of truth.
   - **Accepted:** Reset shots/hits/stats on server, preserve ship arrays.
   - **Rejected:** Client-only reset (would violate server-owned truth).

2) **Prompt:** “How should I structure game state in PHP session for both player and computer?”
   - **Why:** Needed clear state ownership and tracking for two boards.
   - **Accepted:** Separate maps for playerShotsOnComputer and computerShotsOnPlayer + hit/shot counts.
   - **Rejected:** Storing state only in JS/localStorage.

3) **Prompt:** “Implement computer firing back after the player fires, preventing repeat shots.”
   - **Why:** Major gameplay iteration (turn-based logic).
   - **Accepted:** Random un-shot coordinate selection on server and returning both shot results in one response.
   - **Rejected:** Computer firing from client-side (easy to cheat/inconsistent).

4) **Prompt:** “Add an explicit phase/state field and block actions after GAME_OVER.”
   - **Why:** Rubric requires explicit game state handling, not just UI disabling.
   - **Accepted:** `phase` and `winner` stored on server, enforced in `fire`.
   - **Rejected:** Only disabling buttons in JS.

5) **Prompt:** “Update the client UI to display two boards and show computer hits/misses.”
   - **Why:** Needed clear feedback for Iteration 2 and easy Loom demo.
   - **Accepted:** Two-board layout + turn history log.
   - **Rejected:** One-board only (harder to demonstrate computer firing back).

6) **Prompt:** “What’s the best way to rehydrate board state after refresh?”
   - **Why:** Ensure no refresh hacks; server state should render correctly.
   - **Accepted:** `state` endpoint returns mark maps and client draws them.
   - **Rejected:** Assuming a fresh UI each load.
