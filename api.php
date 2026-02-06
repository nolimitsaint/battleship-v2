<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json');

function respond(int $code, array $payload): void {
  http_response_code($code);
  echo json_encode($payload);
  exit;
}
function ok(array $payload = []): void { respond(200, array_merge(['ok' => true], $payload)); }
function fail(int $code, string $msg): void { respond($code, ['ok' => false, 'error' => $msg]); }

function read_json(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || trim($raw) === '') return [];
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function parse_coord(string $coord): array {
  $coord = strtoupper(trim($coord));
  if (!preg_match('/^[A-J](10|[1-9])$/', $coord)) fail(400, "Invalid coord. Use A1–J10.");
  $letter = $coord[0];
  $num = (int) substr($coord, 1);
  $r = ord($letter) - ord('A');
  $c = $num - 1;
  return [$r, $c];
}

function coord_from_rc(int $r, int $c): string {
  $letters = "ABCDEFGHIJ";
  return $letters[$r] . (string)($c + 1);
}

function place_ships(array $sizes): array {
  $ships = [];
  $occupied = [];

  foreach ($sizes as $size) {
    while (true) {
      $horizontal = (bool) random_int(0, 1);
      $r = random_int(0, 9);
      $c = random_int(0, 9);

      $cells = [];
      $okPlace = true;

      if ($horizontal) {
        if ($c + $size - 1 > 9) continue;
        for ($i=0; $i<$size; $i++){
          $key = $r . "," . ($c+$i);
          if (isset($occupied[$key])) { $okPlace = false; break; }
          $cells[] = [$r, $c+$i];
        }
      } else {
        if ($r + $size - 1 > 9) continue;
        for ($i=0; $i<$size; $i++){
          $key = ($r+$i) . "," . $c;
          if (isset($occupied[$key])) { $okPlace = false; break; }
          $cells[] = [$r+$i, $c];
        }
      }

      if (!$okPlace) continue;
      foreach ($cells as $cell) $occupied[$cell[0].",".$cell[1]] = true;
      $ships[] = $cells;
      break;
    }
  }
  return $ships;
}

function new_game_state(): array {
  $sizes = [2,3,5];

  return [
    'playerShips' => place_ships($sizes),
    'computerShips' => place_ships($sizes),

    'playerShotsOnComputer' => [], // "r,c" => hit/miss
    'computerShotsOnPlayer' => [],

    'playerHitCount' => 0,
    'computerHitCount' => 0,
    'playerShotCount' => 0,
    'computerShotCount' => 0,

    // Explicit state machine
    'phase' => 'PLAYER_TURN', // PLAYER_TURN | GAME_OVER
    'winner' => null,
  ];
}

function ensure_game(): void {
  if (
    !isset($_SESSION['game']) ||
    !is_array($_SESSION['game']) ||
    !isset($_SESSION['game']['playerShips']) ||
    !isset($_SESSION['game']['computerShips']) ||
    !isset($_SESSION['game']['phase'])
  ) {
    $_SESSION['game'] = new_game_state();
  }
}

function total_ship_cells(array $ships): int {
  $t = 0;
  foreach ($ships as $ship) $t += count($ship);
  return $t;
}

function is_ship_cell(array $ships, int $r, int $c): bool {
  foreach ($ships as $ship) {
    foreach ($ship as $cell) {
      if ($cell[0] === $r && $cell[1] === $c) return true;
    }
  }
  return false;
}

function remaining_cells(array $ships, int $hitCount): int {
  return total_ship_cells($ships) - $hitCount;
}

function maybe_end_game(array $g): array {
  $computerRemain = remaining_cells($g['computerShips'], (int)$g['playerHitCount']);
  $playerRemain   = remaining_cells($g['playerShips'], (int)$g['computerHitCount']);

  if ($computerRemain <= 0) {
    $g['phase'] = 'GAME_OVER';
    $g['winner'] = 'player';
  } elseif ($playerRemain <= 0) {
    $g['phase'] = 'GAME_OVER';
    $g['winner'] = 'computer';
  }
  return $g;
}

function state_payload(array $g): array {
  return [
    'player' => [
      'shots' => (int)$g['playerShotCount'],
      'hits'  => (int)$g['playerHitCount'],
      'remainingShipCells' => remaining_cells($g['playerShips'], (int)$g['computerHitCount']),
    ],
    'computer' => [
      'shots' => (int)$g['computerShotCount'],
      'hits'  => (int)$g['computerHitCount'],
      'remainingShipCells' => remaining_cells($g['computerShips'], (int)$g['playerHitCount']),
    ],
    'phase' => $g['phase'],
    'winner' => $g['winner'],
  ];
}

function marks_payload(array $g): array {
  $player = [];
  foreach ($g['playerShotsOnComputer'] as $key => $res) {
    [$r,$c] = array_map('intval', explode(',', $key));
    $player[coord_from_rc($r,$c)] = $res;
  }
  $computer = [];
  foreach ($g['computerShotsOnPlayer'] as $key => $res) {
    [$r,$c] = array_map('intval', explode(',', $key));
    $computer[coord_from_rc($r,$c)] = $res;
  }
  return [
    'playerShotsOnComputer' => $player,
    'computerShotsOnPlayer' => $computer
  ];
}

// Iteration 1: Restart Current Game = keep ships, reset shots/state
function restart_current_game(array $g): array {
  $g['playerShotsOnComputer'] = [];
  $g['computerShotsOnPlayer'] = [];
  $g['playerHitCount'] = 0;
  $g['computerHitCount'] = 0;
  $g['playerShotCount'] = 0;
  $g['computerShotCount'] = 0;
  $g['phase'] = 'PLAYER_TURN';
  $g['winner'] = null;
  return $g;
}

// pick random un-shot coord
function random_unshot_coord(array $shotsMap): array {
  while (true) {
    $r = random_int(0, 9);
    $c = random_int(0, 9);
    $key = $r . "," . $c;
    if (!isset($shotsMap[$key])) return [$r, $c];
  }
}

$action = $_GET['action'] ?? 'state';
ensure_game();
$g = $_SESSION['game'];

if ($action === 'new') {
  $_SESSION['game'] = new_game_state();
  $g = $_SESSION['game'];
  ok(['state' => state_payload($g), 'marks' => marks_payload($g)]);
}

if ($action === 'restart') {
  $g = restart_current_game($g);
  $_SESSION['game'] = $g;
  ok(['state' => state_payload($g), 'marks' => marks_payload($g)]);
}

if ($action === 'state') {
  ok(['state' => state_payload($g), 'marks' => marks_payload($g)]);
}

if ($action === 'fire') {
  if ($g['phase'] === 'GAME_OVER') {
    ok([
      'state' => state_payload($g),
      'marks' => marks_payload($g),
      'playerShot' => null,
      'computerShot' => null
    ]);
  }

  $data = read_json();
  $coord = $data['coord'] ?? '';
  if (!is_string($coord) || $coord === '') fail(400, "Missing coord.");

  [$r, $c] = parse_coord($coord);
  $key = $r . "," . $c;

  // prevent repeat fires
  if (isset($g['playerShotsOnComputer'][$key])) {
    ok([
      'state' => state_payload($g),
      'marks' => marks_payload($g),
      'playerShot' => ['coord' => $coord, 'result' => $g['playerShotsOnComputer'][$key]],
      'computerShot' => null
    ]);
  }

  // Player fires at computer
  $g['playerShotCount']++;
  $playerResult = is_ship_cell($g['computerShips'], $r, $c) ? 'hit' : 'miss';
  $g['playerShotsOnComputer'][$key] = $playerResult;
  if ($playerResult === 'hit') $g['playerHitCount']++;

  // check win after player shot
  $g = maybe_end_game($g);

  $computerShotPayload = null;

  // Iteration 2: Computer fires back if game not over
  if ($g['phase'] !== 'GAME_OVER') {
    $g['computerShotCount']++;

    [$cr, $cc] = random_unshot_coord($g['computerShotsOnPlayer']);
    $ckey = $cr . "," . $cc;

    $compResult = is_ship_cell($g['playerShips'], $cr, $cc) ? 'hit' : 'miss';
    $g['computerShotsOnPlayer'][$ckey] = $compResult;
    if ($compResult === 'hit') $g['computerHitCount']++;

    $computerShotPayload = [
      'coord' => coord_from_rc($cr, $cc),
      'result' => $compResult
    ];

    // check win after computer shot
    $g = maybe_end_game($g);
  }

  $_SESSION['game'] = $g;

  ok([
    'state' => state_payload($g),
    'marks' => marks_payload($g),
    'playerShot' => ['coord' => $coord, 'result' => $playerResult],
    'computerShot' => $computerShotPayload
  ]);
}

fail(404, "Unknown action.");
