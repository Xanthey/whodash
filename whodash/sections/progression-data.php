<?php
// sections/progression-data.php
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
    // OVERVIEW TAB - Current Tier Raid Progression
    // ============================================================================

    // Define WotLK raid tiers
    $currentTierRaids = ['Icecrown Citadel', 'Ruby Sanctum', 'Trial of the Crusader', 'Ulduar'];

    // Get progression for current tier raids
    $stmt = $pdo->prepare('
        SELECT  
            instance, 
            difficulty_name, 
            COUNT(DISTINCT boss_name) as bosses_killed
        FROM boss_kills
        WHERE character_id = ? 
            AND instance IN (?, ?, ?, ?)
        GROUP BY instance, difficulty_name
        ORDER BY instance, difficulty_name
    ');
    $stmt->execute(array_merge([$cid], $currentTierRaids));
    $payload['raid_progression'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add total boss counts for each raid (hardcoded for WotLK)
    $raidBossCounts = [
        'Icecrown Citadel' => 12,
        'Ruby Sanctum' => 1,
        'Trial of the Crusader' => 5,
        'Ulduar' => 14,
        'Naxxramas' => 15,
        'Eye of Eternity' => 1,
        'Obsidian Sanctum' => 1
    ];

    foreach ($payload['raid_progression'] as &$prog) {
        $prog['total_bosses'] = $raidBossCounts[$prog['instance']] ?? 0;
        $prog['progress_pct'] = $prog['total_bosses'] > 0
            ? round(($prog['bosses_killed'] / $prog['total_bosses']) * 100, 1)
            : 0;
    }
    unset($prog);

    // Boss Kill Statistics
    $stmt = $pdo->prepare('SELECT COUNT(DISTINCT boss_name) as unique_bosses FROM boss_kills WHERE character_id = ?');
    $stmt->execute([$cid]);
    $payload['unique_bosses'] = (int) ($stmt->fetchColumn() ?? 0);

    $stmt = $pdo->prepare('SELECT COUNT(*) as total_kills FROM boss_kills WHERE character_id = ?');
    $stmt->execute([$cid]);
    $payload['total_boss_kills'] = (int) ($stmt->fetchColumn() ?? 0);

    // Most killed boss
    $stmt = $pdo->prepare('
        SELECT boss_name, instance, COUNT(*) as kill_count
        FROM boss_kills
        WHERE character_id = ?
        GROUP BY boss_name, instance
        ORDER BY kill_count DESC
        LIMIT 1
    ');
    $stmt->execute([$cid]);
    $mostKilled = $stmt->fetch(PDO::FETCH_ASSOC);
    $payload['most_killed_boss'] = $mostKilled;

    // Difficulty breakdown
    $stmt = $pdo->prepare('
        SELECT difficulty_name, COUNT(*) as kill_count
        FROM boss_kills
        WHERE character_id = ?
        GROUP BY difficulty_name
        ORDER BY kill_count DESC
    ');
    $stmt->execute([$cid]);
    $payload['difficulty_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================================
    // LOCKOUTS TAB - Active and Historical Lockouts
    // ============================================================================

    // Active lockouts (future resets)
    $stmt = $pdo->prepare('
        SELECT  
            instance_name, 
            difficulty_name, 
            bosses_killed, 
            total_bosses, 
            reset_time,
            extended
        FROM instance_lockouts 
        WHERE character_id = ? 
            AND reset_time > UNIX_TIMESTAMP(NOW()) 
        ORDER BY reset_time
    ');
    $stmt->execute([$cid]);
    $activeLockouts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate days until reset
    foreach ($activeLockouts as &$lockout) {
        $lockout['days_until_reset'] = ceil(($lockout['reset_time'] - time()) / 86400);
    }
    unset($lockout);

    $payload['active_lockouts'] = $activeLockouts;

    // Historical lockout statistics (last 6 months)
    $stmt = $pdo->prepare('
        SELECT  
            instance_name, 
            COUNT(*) as weeks_raided, 
            AVG(bosses_killed) as avg_bosses_per_week, 
            AVG(bosses_killed / NULLIF(total_bosses, 0) * 100) as avg_clear_pct, 
            MAX(bosses_killed) as best_week,
            SUM(CASE WHEN bosses_killed = total_bosses THEN 1 ELSE 0 END) as full_clears
        FROM instance_lockouts 
        WHERE character_id = ? 
            AND ts > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 180 DAY)) 
        GROUP BY instance_name 
        ORDER BY weeks_raided DESC
    ');
    $stmt->execute([$cid]);
    $payload['lockout_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Round averages
    foreach ($payload['lockout_history'] as &$hist) {
        $hist['avg_bosses_per_week'] = round($hist['avg_bosses_per_week'], 1);
        $hist['avg_clear_pct'] = round($hist['avg_clear_pct'], 1);
    }
    unset($hist);

    // ============================================================================
    // BOSS KILLS TAB - All Boss Kills with Details
    // ============================================================================

    // All boss kills
    $stmt = $pdo->prepare('
        SELECT 
            boss_name,
            instance,
            difficulty_name,
            group_size,
            group_type,
            ts
        FROM boss_kills
        WHERE character_id = ?
        ORDER BY ts DESC
    ');
    $stmt->execute([$cid]);
    $payload['all_boss_kills'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Boss kill frequency (for farming analysis)
    $stmt = $pdo->prepare('
        SELECT 
            boss_name,
            instance,
            COUNT(*) as total_kills,
            MIN(ts) as first_kill,
            MAX(ts) as last_kill,
            COUNT(DISTINCT difficulty_name) as difficulties_killed
        FROM boss_kills
        WHERE character_id = ?
        GROUP BY boss_name, instance
        ORDER BY total_kills DESC
    ');
    $stmt->execute([$cid]);
    $payload['boss_frequency'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

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