<?php
/**
 * List all characters for the signed-in user in a sort that matches "last updated first".
 * Response:
 *   { ok: true, characters: [ { id, name, updated_at }, ... ] }
 */

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

//////////////////// DB bootstrap ////////////////////
$host = getenv('DB_HOST') ?: 'backend';
$db = getenv('DB_NAME') ?: 'whodat';
$user = getenv('DB_USER') ?: 'whodatuser';
$pass = getenv('DB_PASSWORD') ?: 'whodatpass';
$charset = 'utf8mb4';
$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database connection error']);
    exit;
}

//////////////////// Auth ////////////////////
if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}
$userId = (int) $_SESSION['user_id'];

//////////////////// Fetch list ////////////////////
try {
    $stmt = $pdo->prepare("
    SELECT 
        c.id, 
        c.name, 
        c.faction, 
        c.updated_at,
        g.player_rank,
        g.player_rank_index,
        g.guild_name
    FROM characters c
    LEFT JOIN (
        SELECT character_id, player_rank, player_rank_index, guild_name
        FROM guild_info_snapshots gis
        WHERE (character_id, snapshot_ts) IN (
            SELECT character_id, MAX(snapshot_ts)
            FROM guild_info_snapshots
            GROUP BY character_id
        )
    ) g ON c.id = g.character_id
    WHERE c.user_id = ?
    ORDER BY (c.updated_at IS NULL), c.updated_at DESC, c.id DESC
  ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    // Ensure types (id as int); updated_at left as string (MySQL DATETIME/NULL)
    $characters = array_map(function (array $r) {
        $rankIndex = isset($r['player_rank_index']) ? (int) $r['player_rank_index'] : null;
        $hasGuildAccess = ($rankIndex !== null && $rankIndex <= 2); // GM=0, Officers=1-2

        return [
            'id' => (int) $r['id'],
            'name' => $r['name'] ?? null,
            'faction' => $r['faction'] ?? 'Unknown',
            'updated_at' => $r['updated_at'] ?? null,
            'guild_name' => $r['guild_name'] ?? null,
            'guild_rank' => $r['player_rank'] ?? null,
            'has_guild_access' => $hasGuildAccess,
        ];
    }, $rows ?: []);

    echo json_encode(['ok' => true, 'characters' => $characters]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error']);
}
