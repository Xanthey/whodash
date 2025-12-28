<?php
// sections/graphs-data.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    require_once __DIR__ . '/../db.php';
    session_start();

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=60');

    // Auth check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    // Get character ID
    $cid = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;
    if (!$cid) {
        http_response_code(400);
        echo json_encode(['error' => 'No character_id']);
        exit;
    }

    // Validate ownership
    $own = $pdo->prepare('SELECT id, name FROM characters WHERE id = ? AND user_id = ?');
    $own->execute([$cid, $_SESSION['user_id']]);
    $character = $own->fetch(PDO::FETCH_ASSOC);
    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not yours']);
        exit;
    }

    $payload = [];

    // ============================================================================
    // LEVEL PROGRESSION - from series_level table
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT ts, value
            FROM series_level
            WHERE character_id = ?
            ORDER BY ts ASC
            LIMIT 1000
        ');
        $stmt->execute([$cid]);
        $payload['level_progression'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $payload['level_progression'] = [];
    }

    // ============================================================================
    // GOLD OVER TIME - from series_money table
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT ts, value
            FROM series_money
            WHERE character_id = ?
            ORDER BY ts ASC
            LIMIT 1000
        ');
        $stmt->execute([$cid]);
        $payload['gold_over_time'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $payload['gold_over_time'] = [];
    }

    // ============================================================================
    // HONOR POINTS - from series_honor table
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT ts, value
            FROM series_honor
            WHERE character_id = ?
            ORDER BY ts ASC
            LIMIT 1000
        ');
        $stmt->execute([$cid]);
        $payload['honor_points'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $payload['honor_points'] = [];
    }

    // ============================================================================
    // ARENA POINTS - from series_arena table
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT ts, value
            FROM series_arena
            WHERE character_id = ?
            ORDER BY ts ASC
            LIMIT 1000
        ');
        $stmt->execute([$cid]);
        $payload['arena_points'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $payload['arena_points'] = [];
    }

    // ============================================================================
    // DEATHS PER DAY - from deaths table
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT 
                DATE(FROM_UNIXTIME(ts)) as day,
                COUNT(*) as count
            FROM deaths
            WHERE character_id = ?
            GROUP BY day
            ORDER BY day ASC
            LIMIT 180
        ');
        $stmt->execute([$cid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Convert to ts/value format
        $payload['deaths_per_day'] = array_map(function ($r) {
            return [
                'ts' => strtotime($r['day']),
                'value' => (int) $r['count']
            ];
        }, $rows);
    } catch (Throwable $e) {
        $payload['deaths_per_day'] = [];
    }

    // ============================================================================
    // BOSS KILLS PER DAY - from boss_kills table
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT 
                DATE(FROM_UNIXTIME(ts)) as day,
                COUNT(*) as count
            FROM boss_kills
            WHERE character_id = ?
            GROUP BY day
            ORDER BY day ASC
            LIMIT 180
        ');
        $stmt->execute([$cid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payload['boss_kills_per_day'] = array_map(function ($r) {
            return [
                'ts' => strtotime($r['day']),
                'value' => (int) $r['count']
            ];
        }, $rows);
    } catch (Throwable $e) {
        $payload['boss_kills_per_day'] = [];
    }

    // ============================================================================
    // REPUTATION GAINS PER DAY - from series_reputation table
    // ============================================================================
    try {
        // Get daily reputation changes across all factions
        $stmt = $pdo->prepare('
            SELECT 
                DATE(FROM_UNIXTIME(ts)) as day,
                SUM(ABS(value)) as total_rep
            FROM series_reputation
            WHERE character_id = ?
            GROUP BY day
            ORDER BY day ASC
            LIMIT 180
        ');
        $stmt->execute([$cid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payload['reputation_gains'] = array_map(function ($r) {
            return [
                'ts' => strtotime($r['day']),
                'value' => (int) ($r['total_rep'] ?? 0)
            ];
        }, $rows);
    } catch (Throwable $e) {
        $payload['reputation_gains'] = [];
    }

    // ============================================================================
    // ACHIEVEMENTS PER DAY - from series_achievements table
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT 
                DATE(FROM_UNIXTIME(ts)) as day,
                COUNT(DISTINCT achievement_id) as count
            FROM series_achievements
            WHERE character_id = ? AND earned = 1
            GROUP BY day
            ORDER BY day ASC
            LIMIT 365
        ');
        $stmt->execute([$cid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payload['achievements_per_day'] = array_map(function ($r) {
            return [
                'ts' => strtotime($r['day']),
                'value' => (int) $r['count']
            ];
        }, $rows);
    } catch (Throwable $e) {
        $payload['achievements_per_day'] = [];
    }

    // ============================================================================
    // QUEST COMPLETION PER DAY - from quest_events table
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT 
                DATE(FROM_UNIXTIME(ts)) as day,
                COUNT(*) as count
            FROM quest_events
            WHERE character_id = ? AND kind = ?
            GROUP BY day
            ORDER BY day ASC
            LIMIT 180
        ');
        $stmt->execute([$cid, 'completed']);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payload['quest_completion'] = array_map(function ($r) {
            return [
                'ts' => strtotime($r['day']),
                'value' => (int) $r['count']
            ];
        }, $rows);
    } catch (Throwable $e) {
        $payload['quest_completion'] = [];
    }

    // ============================================================================
    // ZONE ACTIVITY - Count zone changes per day as activity indicator
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT 
                DATE(FROM_UNIXTIME(ts)) as day,
                COUNT(*) as changes
            FROM series_zones
            WHERE character_id = ?
            GROUP BY day
            ORDER BY day ASC
            LIMIT 180
        ');
        $stmt->execute([$cid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $payload['zone_activity_hours'] = array_map(function ($r) {
            // Convert zone changes to a rough "activity" metric
            // More zone changes = more active
            return [
                'ts' => strtotime($r['day']),
                'value' => round((int) $r['changes'] / 10, 1) // Normalize to reasonable scale
            ];
        }, $rows);
    } catch (Throwable $e) {
        $payload['zone_activity_hours'] = [];
    }

    echo json_encode($payload, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}