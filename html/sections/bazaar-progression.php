<?php
// sections/bazaar-progression.php - Multi-character dungeon/raid progression aggregator (FIXED)
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Suppress PHP warnings that could corrupt JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Initialize response payload
$payload = [
    'lockouts' => [],
    'recent_dungeons' => []
];

try {
    // Get all user's characters
    $stmt = $pdo->prepare('SELECT id, name FROM characters WHERE user_id = ? ORDER BY name');
    $stmt->execute([$user_id]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($characters)) {
        echo json_encode($payload);
        exit;
    }

    $character_ids = array_column($characters, 'id');
    $character_names = [];
    foreach ($characters as $char) {
        $character_names[$char['id']] = $char['name'];
    }

    $placeholders = implode(',', array_fill(0, count($character_ids), '?'));

    // ============================================================================
    // INSTANCE LOCKOUTS (from instance_lockouts table)
    // ============================================================================
    try {
        $stmt = $pdo->prepare("
            SELECT 
                il.character_id,
                il.instance_name,
                il.difficulty_name as difficulty,
                il.bosses_killed,
                il.reset_time
            FROM instance_lockouts il
            WHERE il.character_id IN ($placeholders)
                AND il.reset_time > UNIX_TIMESTAMP(NOW())
            ORDER BY il.reset_time ASC
            LIMIT 50
        ");
        $stmt->execute($character_ids);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $locked_until = date('Y-m-d H:i:s', $row['reset_time']);

            $payload['lockouts'][] = [
                'character_id' => (int) $row['character_id'],
                'character_name' => $character_names[$row['character_id']] ?? 'Unknown',
                'instance_name' => $row['instance_name'],
                'difficulty' => $row['difficulty'] ?? 'Normal',
                'locked_until' => $locked_until,
                'bosses_killed' => (int) ($row['bosses_killed'] ?? 0)
            ];
        }
    } catch (Throwable $e) {
        error_log("Bazaar progression - lockouts error: " . $e->getMessage());
    }

    // ============================================================================
    // RECENT DUNGEONS/RAIDS (from boss_kills)
    // FIXED: Changed bk.instance_name to bk.instance (the correct column name)
    // ============================================================================
    try {
        // Get unique instances from recent boss kills
        $stmt = $pdo->prepare("
            SELECT 
                bk.character_id,
                bk.instance as instance_name,
                bk.difficulty,
                COUNT(DISTINCT bk.boss_name) as bosses_killed,
                MAX(bk.ts) as completed_at,
                MIN(bk.ts) as started_at
            FROM boss_kills bk
            WHERE bk.character_id IN ($placeholders)
                AND bk.ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            GROUP BY bk.character_id, bk.instance, bk.difficulty
            ORDER BY completed_at DESC
            LIMIT 100
        ");
        $stmt->execute($character_ids);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $completed_at = date('Y-m-d H:i:s', $row['completed_at']);
            $duration_seconds = $row['completed_at'] - $row['started_at'];

            $payload['recent_dungeons'][] = [
                'character_id' => (int) $row['character_id'],
                'character_name' => $character_names[$row['character_id']] ?? 'Unknown',
                'instance_name' => $row['instance_name'],
                'difficulty' => $row['difficulty'] ?? 'Normal',
                'bosses_killed' => (int) ($row['bosses_killed'] ?? 0),
                'duration_seconds' => (int) $duration_seconds,
                'group_size' => 5, // We don't track this in boss_kills, default to 5
                'completed_at' => $completed_at
            ];
        }
    } catch (Throwable $e) {
        error_log("Bazaar progression - recent dungeons error: " . $e->getMessage());
    }

} catch (Throwable $e) {
    error_log("Bazaar progression - general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error loading progression data']);
    exit;
}

echo json_encode($payload);
