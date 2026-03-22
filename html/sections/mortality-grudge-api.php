<?php
/**
 * sections/mortality-grudge-api.php
 * CRUD API for The Grudge persistent list.
 *
 * Actions (POST body JSON or GET params):
 *   GET  ?character_id=X&action=get              — fetch grudge list for character
 *   GET  ?character_id=X&action=export           — download TheGrudgeDB.lua for this character
 *   POST {action:"add",    character_id, player_name}  — add or re-pin to top
 *   POST {action:"remove", character_id, player_name}  — remove from list
 */
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// ----------------------------------------------------------------
// Parse request
// ----------------------------------------------------------------
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    $character_id = $body['character_id'] ?? null;
    $player_name = trim($body['player_name'] ?? '');
} else {
    $action = $_GET['action'] ?? 'get';
    $character_id = $_GET['character_id'] ?? null;
    $player_name = '';
}

// ----------------------------------------------------------------
// Validate character_id
// ----------------------------------------------------------------
if (!$character_id || !is_numeric($character_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid character_id']);
    exit;
}
$character_id = (int) $character_id;

// ----------------------------------------------------------------
// Auth: verify the session user owns this character,
//       or the character is public (read-only).
// ----------------------------------------------------------------
function resolveCharacter(PDO $pdo, int $character_id, bool $requireOwner = false): bool
{
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT id FROM characters WHERE id = ? AND user_id = ?');
        $stmt->execute([$character_id, $_SESSION['user_id']]);
        if ($stmt->fetch())
            return true;
    }

    if (!$requireOwner) {
        $stmt = $pdo->prepare('SELECT id FROM characters WHERE id = ? AND visibility = "PUBLIC"');
        $stmt->execute([$character_id]);
        if ($stmt->fetch())
            return true;
    }

    return false;
}

// ----------------------------------------------------------------
// Route actions
// ----------------------------------------------------------------
try {

    // ---- GET: return full grudge list ----
    if ($action === 'get') {
        if (!resolveCharacter($pdo, $character_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Character not accessible']);
            exit;
        }

        $stmt = $pdo->prepare('
            SELECT player_name, added_at
            FROM character_grudge_list
            WHERE character_id = ?
            ORDER BY added_at DESC
        ');
        $stmt->execute([$character_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'grudge_list' => array_map(fn($r) => [
                'name' => $r['player_name'],
                'addedAt' => (int) $r['added_at'],
            ], $rows)
        ]);
        exit;
    }

    // ---- ADD: insert or update (re-pins to top via updated added_at) ----
    if ($action === 'add') {
        if (!resolveCharacter($pdo, $character_id, requireOwner: true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorised']);
            exit;
        }

        if ($player_name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'player_name required']);
            exit;
        }

        if (mb_strlen($player_name) > 64) {
            http_response_code(400);
            echo json_encode(['error' => 'player_name too long']);
            exit;
        }

        $now = time();

        // INSERT ... ON DUPLICATE KEY UPDATE — atomically adds or re-pins
        $stmt = $pdo->prepare('
            INSERT INTO character_grudge_list (character_id, player_name, added_at)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE added_at = VALUES(added_at)
        ');
        $stmt->execute([$character_id, $player_name, $now]);

        echo json_encode(['ok' => true, 'player_name' => $player_name, 'added_at' => $now]);
        exit;
    }

    // ---- REMOVE ----
    if ($action === 'remove') {
        if (!resolveCharacter($pdo, $character_id, requireOwner: true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorised']);
            exit;
        }

        if ($player_name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'player_name required']);
            exit;
        }

        $stmt = $pdo->prepare('
            DELETE FROM character_grudge_list
            WHERE character_id = ? AND player_name = ?
        ');
        $stmt->execute([$character_id, $player_name]);

        echo json_encode(['ok' => true]);
        exit;
    }

    // ---- EXPORT: download TheGrudgeDB.lua for this character ----
    if ($action === 'export') {
        // Owner-only — grudge list is personal data
        if (!resolveCharacter($pdo, $character_id, requireOwner: true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Not authorised']);
            exit;
        }

        // Fetch character identity for the Lua header
        $charStmt = $pdo->prepare('SELECT name, realm, faction, class_file, class_local FROM characters WHERE id = ?');
        $charStmt->execute([$character_id]);
        $char = $charStmt->fetch(PDO::FETCH_ASSOC);

        if (!$char) {
            http_response_code(404);
            echo json_encode(['error' => 'Character not found']);
            exit;
        }

        // Fetch grudge list with kill aggregates
        $grudgeStmt = $pdo->prepare('
            SELECT
                gl.player_name,
                gl.added_at,
                COUNT(d.id)  AS kill_count,
                MAX(d.ts)    AS last_killed_at
            FROM character_grudge_list gl
            LEFT JOIN deaths d
                ON  d.character_id = gl.character_id
                AND d.killer_name  = gl.player_name
                AND d.killer_type  = \'player\'
            WHERE gl.character_id = ?
            GROUP BY gl.player_name, gl.added_at
            ORDER BY gl.added_at DESC
        ');
        $grudgeStmt->execute([$character_id]);
        $grudgeRows = $grudgeStmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch all PvP death incidents for grudge targets in one query
        $incidentMap = [];
        if (!empty($grudgeRows)) {
            $grudgeNames = array_column($grudgeRows, 'player_name');
            $placeholders = implode(',', array_fill(0, count($grudgeNames), '?'));
            $incStmt = $pdo->prepare("
                SELECT killer_name, ts, zone, subzone, killer_spell, killer_damage
                FROM deaths
                WHERE character_id = ?
                  AND killer_type  = 'player'
                  AND killer_name IN ({$placeholders})
                ORDER BY ts DESC
            ");
            $incStmt->execute(array_merge([$character_id], $grudgeNames));
            foreach ($incStmt->fetchAll(PDO::FETCH_ASSOC) as $inc) {
                $incidentMap[$inc['killer_name']][] = $inc;
            }
        }

        // ── Lua helpers ───────────────────────────────────────────────────────
        $luaStr = function (string $s): string {
            $s = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $s);
            return '"' . $s . '"';
        };

        // ── Build Lua output ──────────────────────────────────────────────────
        $ts = time();
        $charName = $char['name'];
        $realm = $char['realm'];
        $charKey = $realm . ':' . $charName;
        $class = $char['class_file'] ?? $char['class_local'] ?? 'UNKNOWN';
        $faction = $char['faction'] ?? 'Unknown';

        $lua = "-- TheGrudgeDB.lua\n";
        $lua .= "-- Generated by WhoDASH on " . date('Y-m-d H:i:s T', $ts) . "\n";
        $lua .= "-- Character: {$charKey}\n";
        $lua .= "-- DO NOT EDIT MANUALLY — overwritten by SyncDAT addon sync.\n\n";
        $lua .= "TheGrudgeData = {\n";
        $lua .= "    [\"version\"] = 1,\n";
        $lua .= "    [\"exported_at\"] = {$ts},\n";
        $lua .= "    [\"exported_by\"] = \"WhoDASH\",\n";
        $lua .= "    [\"characters\"] = {\n";
        $lua .= "        [" . $luaStr($charKey) . "] = {\n";
        $lua .= "            [\"name\"]    = " . $luaStr($charName) . ",\n";
        $lua .= "            [\"realm\"]   = " . $luaStr($realm) . ",\n";
        $lua .= "            [\"class\"]   = " . $luaStr($class) . ",\n";
        $lua .= "            [\"faction\"] = " . $luaStr($faction) . ",\n";
        $lua .= "            [\"grudge_list\"] = {\n";

        foreach ($grudgeRows as $row) {
            $name = $row['player_name'];
            $addedAt = (int) $row['added_at'];
            $kills = (int) $row['kill_count'];
            $lastKill = $row['last_killed_at'] !== null ? (int) $row['last_killed_at'] : 0;

            $lua .= "                {\n";
            $lua .= "                    [\"name\"]           = " . $luaStr($name) . ",\n";
            $lua .= "                    [\"added_at\"]       = {$addedAt},\n";
            $lua .= "                    [\"kill_count\"]     = {$kills},\n";
            $lua .= "                    [\"last_killed_at\"] = {$lastKill},\n";
            $lua .= "                    [\"incidents\"] = {\n";

            foreach ($incidentMap[$name] ?? [] as $inc) {
                $incTs = (int) $inc['ts'];
                $incZone = $luaStr($inc['zone'] ?? '');
                $incSubzone = $luaStr($inc['subzone'] ?? '');
                $incSpell = $luaStr($inc['killer_spell'] ?? '');
                $incDamage = $inc['killer_damage'] !== null ? (int) $inc['killer_damage'] : 0;

                $lua .= "                        {\n";
                $lua .= "                            [\"ts\"]      = {$incTs},\n";
                $lua .= "                            [\"zone\"]    = {$incZone},\n";
                $lua .= "                            [\"subzone\"] = {$incSubzone},\n";
                $lua .= "                            [\"spell\"]   = {$incSpell},\n";
                $lua .= "                            [\"damage\"]  = {$incDamage},\n";
                $lua .= "                        },\n";
            }

            $lua .= "                    },\n"; // incidents
            $lua .= "                },\n";     // entry
        }

        $lua .= "            },\n"; // grudge_list
        $lua .= "        },\n";     // character
        $lua .= "    },\n";         // characters
        $lua .= "}\n";              // TheGrudgeData

        // Swap JSON header for plain text and trigger download
        header_remove('Content-Type');
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="TheGrudgeDB.lua"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $lua;
        exit;
    }

    // Unknown action
    http_response_code(400);
    echo json_encode(['error' => "Unknown action: {$action}"]);

} catch (Exception $e) {
    error_log('Grudge API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>