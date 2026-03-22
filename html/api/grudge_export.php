<?php
/**
 * api/grudge_export.php
 * ─────────────────────────────────────────────────────────────────────────────
 * WhoDASH API — The Grudge Lua Export
 *
 * Returns a WoW-style SavedVariables Lua file containing the grudge list for
 * ALL characters belonging to the authenticated API key owner.  The Uploader
 * tool can GET this endpoint and write the response to:
 *   WTF\Account\<ACCOUNT>\Interface\AddOns\TheGrudge\TheGrudgeDB.lua
 * so the addon can read it on the next login / /reload.
 *
 * Authentication
 * ──────────────
 * Same API key mechanism as api/index.php:
 *   • Header:  X-API-Key: <key>
 *   • Header:  Authorization: Bearer <key>
 *
 * Request
 * ───────
 * GET /api/grudge_export.php
 *   (optional) ?format=lua   → default, returns TheGrudgeDB.lua as text/plain
 *   (optional) ?format=json  → returns raw JSON (useful for debugging / future uploader UI)
 *
 * Response (lua)
 * ──────────────
 * Content-Type: text/plain; charset=utf-8
 * Content-Disposition: attachment; filename="TheGrudgeDB.lua"
 *
 * TheGrudgeData = {
 *     ["version"] = 1,
 *     ["exported_at"] = 1740000000,
 *     ["exported_by"] = "WhoDASH",
 *     ["characters"] = {
 *         ["Whitemane:Xanthey"] = {
 *             ["name"]   = "Xanthey",
 *             ["realm"]  = "Whitemane",
 *             ["class"]  = "PALADIN",
 *             ["grudge_list"] = {
 *                 {
 *                     ["name"]        = "Gankerface",
 *                     ["added_at"]    = 1739000000,
 *                     ["kill_count"]  = 3,
 *                     ["last_killed_at"] = 1739500000,
 *                     ["incidents"]   = {
 *                         {
 *                             ["ts"]    = 1739500000,
 *                             ["zone"]  = "Alterac Valley",
 *                             ["subzone"] = "",
 *                             ["spell"] = "Mortal Strike",
 *                             ["damage"] = 4821,
 *                         },
 *                         ...
 *                     },
 *                 },
 *                 ...
 *             },
 *         },
 *     },
 * }
 */

declare(strict_types=1);

// ─── Bootstrap ───────────────────────────────────────────────────────────────

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ─── API Key Authentication ───────────────────────────────────────────────────

$api_key = null;

if (!empty($_SERVER['HTTP_X_API_KEY'])) {
    $api_key = trim($_SERVER['HTTP_X_API_KEY']);
} elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
    if (preg_match('/Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
        $api_key = trim($m[1]);
    }
}

if (!$api_key) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'API key required (X-API-Key header or Authorization: Bearer <key>)']);
    exit;
}

// Validate the key and resolve owner
$stmt = $pdo->prepare("
    SELECT user_id
    FROM user_api_keys
    WHERE api_key = ?
      AND is_active = 1
      AND (expires_at IS NULL OR expires_at > NOW())
");
$stmt->execute([$api_key]);
$keyRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$keyRow) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired API key']);
    exit;
}

$userId = (int) $keyRow['user_id'];

// Update last_used_at (fire-and-forget, non-fatal)
try {
    $pdo->prepare("UPDATE user_api_keys SET last_used_at = NOW() WHERE api_key = ?")
        ->execute([$api_key]);
} catch (Throwable) { /* ignore */
}

// ─── Determine output format ──────────────────────────────────────────────────

$format = strtolower(trim($_GET['format'] ?? 'lua'));
if (!in_array($format, ['lua', 'json'], true)) {
    $format = 'lua';
}

// ─── Fetch all characters owned by this user ─────────────────────────────────

try {
    $charStmt = $pdo->prepare("
        SELECT id, name, realm, faction, class_file, class_local
        FROM characters
        WHERE user_id = ?
        ORDER BY realm ASC, name ASC
    ");
    $charStmt->execute([$userId]);
    $characters = $charStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Failed to load characters: ' . $e->getMessage()]);
    exit;
}

// ─── Build grudge data per character ─────────────────────────────────────────

$exportData = [];

foreach ($characters as $char) {
    $charId = (int) $char['id'];

    // ── 1. Fetch grudge list entries ──────────────────────────────────────────
    try {
        $grudgeStmt = $pdo->prepare("
            SELECT
                gl.player_name,
                gl.added_at,
                COUNT(d.id)      AS kill_count,
                MAX(d.ts)        AS last_killed_at
            FROM character_grudge_list gl
            LEFT JOIN deaths d
                ON  d.character_id = gl.character_id
                AND d.killer_name  = gl.player_name
                AND d.killer_type  = 'player'
            WHERE gl.character_id = ?
            GROUP BY gl.player_name, gl.added_at
            ORDER BY gl.added_at DESC
        ");
        $grudgeStmt->execute([$charId]);
        $grudgeRows = $grudgeStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("grudge_export: grudge list fetch failed for char {$charId}: " . $e->getMessage());
        $grudgeRows = [];
    }

    // Skip characters with no grudge entries
    if (empty($grudgeRows)) {
        continue;
    }

    // ── 2. Fetch full incident history for each grudge target ─────────────────
    // We pull all PvP deaths from this attacker in one query per character,
    // then distribute them to the matching grudge entry.

    // Collect the player names we care about for this character
    $grudgeNames = array_column($grudgeRows, 'player_name');
    $placeholders = implode(',', array_fill(0, count($grudgeNames), '?'));

    try {
        $incidentStmt = $pdo->prepare("
            SELECT
                killer_name,
                ts,
                zone,
                subzone,
                killer_spell,
                killer_damage
            FROM deaths
            WHERE character_id = ?
              AND killer_type  = 'player'
              AND killer_name IN ({$placeholders})
            ORDER BY ts DESC
        ");
        $incidentStmt->execute(array_merge([$charId], $grudgeNames));
        $allIncidents = $incidentStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        error_log("grudge_export: incident fetch failed for char {$charId}: " . $e->getMessage());
        $allIncidents = [];
    }

    // Index incidents by killer name for fast lookup
    $incidentMap = [];
    foreach ($allIncidents as $inc) {
        $incidentMap[$inc['killer_name']][] = $inc;
    }

    // ── 3. Assemble grudge list entries ───────────────────────────────────────
    $grudgeList = [];

    foreach ($grudgeRows as $row) {
        $name = $row['player_name'];
        $incidents = [];

        foreach ($incidentMap[$name] ?? [] as $inc) {
            $incidents[] = [
                'ts' => (int) $inc['ts'],
                'zone' => $inc['zone'] ?? '',
                'subzone' => $inc['subzone'] ?? '',
                'spell' => $inc['killer_spell'] ?? '',
                'damage' => $inc['killer_damage'] !== null ? (int) $inc['killer_damage'] : 0,
            ];
        }

        $grudgeList[] = [
            'name' => $name,
            'added_at' => (int) $row['added_at'],
            'kill_count' => (int) $row['kill_count'],
            'last_killed_at' => $row['last_killed_at'] !== null ? (int) $row['last_killed_at'] : 0,
            'incidents' => $incidents,
        ];
    }

    // ── 4. Build character key (Realm:Name — matches WoW convention) ──────────
    $charKey = $char['realm'] . ':' . $char['name'];

    $exportData[$charKey] = [
        'name' => $char['name'],
        'realm' => $char['realm'],
        'class' => $char['class_file'] ?? $char['class_local'] ?? 'UNKNOWN',
        'faction' => $char['faction'] ?? 'Unknown',
        'grudge_list' => $grudgeList,
    ];
}

// ─── JSON output (debug / future use) ────────────────────────────────────────

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'ok',
        'version' => 1,
        'exported_at' => time(),
        'characters' => $exportData,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// ─── Lua output ───────────────────────────────────────────────────────────────

header('Content-Type: text/plain; charset=utf-8');
header('Content-Disposition: attachment; filename="TheGrudgeDB.lua"');
header('Cache-Control: no-store, no-cache, must-revalidate');

// ── Lua helper: escape a string for Lua double-quoted string literals ─────────
function luaStr(string $s): string
{
    // Escape backslashes first, then double-quotes, then newlines/returns
    $s = str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $s);
    return '"' . $s . '"';
}

// ── Lua helper: write an integer or 0 ────────────────────────────────────────
function luaInt(int $n): string
{
    return (string) $n;
}

// ── Build the Lua file ────────────────────────────────────────────────────────

$ts = time();
$exportedAt = luaInt($ts);
$version = 1;

$lua = "-- TheGrudgeDB.lua\n";
$lua .= "-- Generated by WhoDASH on " . date('Y-m-d H:i:s T', $ts) . "\n";
$lua .= "-- DO NOT EDIT MANUALLY — this file is overwritten by SyncDAT addon sync.\n";
$lua .= "\n";
$lua .= "TheGrudgeData = {\n";
$lua .= "    [\"version\"] = {$version},\n";
$lua .= "    [\"exported_at\"] = {$exportedAt},\n";
$lua .= "    [\"exported_by\"] = \"WhoDASH\",\n";
$lua .= "    [\"characters\"] = {\n";

foreach ($exportData as $charKey => $charData) {
    $luaKey = luaStr($charKey);
    $luaName = luaStr($charData['name']);
    $luaRealm = luaStr($charData['realm']);
    $luaClass = luaStr($charData['class']);
    $luaFact = luaStr($charData['faction']);

    $lua .= "        [{$luaKey}] = {\n";
    $lua .= "            [\"name\"]    = {$luaName},\n";
    $lua .= "            [\"realm\"]   = {$luaRealm},\n";
    $lua .= "            [\"class\"]   = {$luaClass},\n";
    $lua .= "            [\"faction\"] = {$luaFact},\n";
    $lua .= "            [\"grudge_list\"] = {\n";

    foreach ($charData['grudge_list'] as $entry) {
        $luaEntryName = luaStr($entry['name']);
        $luaAddedAt = luaInt($entry['added_at']);
        $luaKillCount = luaInt($entry['kill_count']);
        $luaLastKilledAt = luaInt($entry['last_killed_at']);

        $lua .= "                {\n";
        $lua .= "                    [\"name\"]           = {$luaEntryName},\n";
        $lua .= "                    [\"added_at\"]       = {$luaAddedAt},\n";
        $lua .= "                    [\"kill_count\"]     = {$luaKillCount},\n";
        $lua .= "                    [\"last_killed_at\"] = {$luaLastKilledAt},\n";
        $lua .= "                    [\"incidents\"] = {\n";

        foreach ($entry['incidents'] as $inc) {
            $luaIncTs = luaInt($inc['ts']);
            $luaIncZone = luaStr($inc['zone']);
            $luaIncSubzone = luaStr($inc['subzone']);
            $luaIncSpell = luaStr($inc['spell']);
            $luaIncDamage = luaInt($inc['damage']);

            $lua .= "                        {\n";
            $lua .= "                            [\"ts\"]      = {$luaIncTs},\n";
            $lua .= "                            [\"zone\"]    = {$luaIncZone},\n";
            $lua .= "                            [\"subzone\"] = {$luaIncSubzone},\n";
            $lua .= "                            [\"spell\"]   = {$luaIncSpell},\n";
            $lua .= "                            [\"damage\"]  = {$luaIncDamage},\n";
            $lua .= "                        },\n";
        }

        $lua .= "                    },\n"; // incidents
        $lua .= "                },\n";     // grudge entry
    }

    $lua .= "            },\n"; // grudge_list
    $lua .= "        },\n";     // character
}

$lua .= "    },\n"; // characters
$lua .= "}\n";      // TheGrudgeData

echo $lua;
exit;