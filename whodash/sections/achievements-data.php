<?php
// sections/achievements-data.php
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
    // OVERVIEW TAB - Achievement Statistics
    // ============================================================================

    // Total achievement points (sum of earned achievements)
    $stmt = $pdo->prepare('
        SELECT SUM(points) as total_points
        FROM series_achievements
        WHERE character_id = ?
            AND earned = 1
    ');
    $stmt->execute([$cid]);
    $payload['total_points'] = (int) ($stmt->fetchColumn() ?? 0);

    // Total achievements earned
    $stmt = $pdo->prepare('
        SELECT COUNT(DISTINCT achievement_id) as total_earned
        FROM series_achievements
        WHERE character_id = ?
            AND earned = 1
    ');
    $stmt->execute([$cid]);
    $payload['total_achievements'] = (int) ($stmt->fetchColumn() ?? 0);

    // Recent achievements (last 10)
    $stmt = $pdo->prepare('
        SELECT name, points, earned_date, description
        FROM series_achievements
        WHERE character_id = ?
            AND earned = 1
            AND earned_date IS NOT NULL
        ORDER BY earned_date DESC
        LIMIT 10
    ');
    $stmt->execute([$cid]);
    $payload['recent_achievements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Achievement categories (extract from achievement name patterns)
    // This is a heuristic approach since we don't have a category field
    $stmt = $pdo->prepare('
        SELECT 
            CASE 
                WHEN name LIKE "%Dungeon%" OR name LIKE "%Heroic%" THEN "Dungeons & Raids"
                WHEN name LIKE "%PvP%" OR name LIKE "%Arena%" OR name LIKE "%Battleground%" THEN "Player vs Player"
                WHEN name LIKE "%Quest%" THEN "Quests"
                WHEN name LIKE "%Exploration%" OR name LIKE "%Explore%" THEN "Exploration"
                WHEN name LIKE "%Reputation%" THEN "Reputation"
                WHEN name LIKE "%Profession%" OR name LIKE "%Cooking%" OR name LIKE "%Fishing%" THEN "Professions"
                WHEN name LIKE "%Pet%" OR name LIKE "%Mount%" THEN "Collections"
                ELSE "General"
            END as category,
            COUNT(*) as count,
            SUM(points) as points
        FROM series_achievements
        WHERE character_id = ?
            AND earned = 1
        GROUP BY category
        ORDER BY points DESC
    ');
    $stmt->execute([$cid]);
    $payload['achievements_by_category'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Highest point achievements
    $stmt = $pdo->prepare('
        SELECT name, points, earned_date
        FROM series_achievements
        WHERE character_id = ?
            AND earned = 1
        ORDER BY points DESC
        LIMIT 10
    ');
    $stmt->execute([$cid]);
    $payload['highest_point_achievements'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================================
    // COLLECTIONS TAB - Mounts & Pets
    // ============================================================================

    // Mounts
    $stmt = $pdo->prepare('
        SELECT name, icon, creature_id, spell_id, active
        FROM companions
        WHERE character_id = ?
            AND type = "MOUNT"
        ORDER BY name
    ');
    $stmt->execute([$cid]);
    $payload['mounts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Mount count
    $payload['mount_count'] = count($payload['mounts']);

    // Pets (Critters)
    $stmt = $pdo->prepare('
        SELECT name, icon, creature_id, spell_id, active
        FROM companions
        WHERE character_id = ?
            AND type = "CRITTER"
        ORDER BY name
    ');
    $stmt->execute([$cid]);
    $payload['pets'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pet count
    $payload['pet_count'] = count($payload['pets']);

    // ============================================================================
    // TIMELINE TAB - All Achievements Chronologically
    // ============================================================================

    // All earned achievements with dates
    $stmt = $pdo->prepare('
        SELECT 
            name, 
            description,
            points, 
            earned_date,
            DATE(FROM_UNIXTIME(earned_date)) as date_only
        FROM series_achievements
        WHERE character_id = ?
            AND earned = 1
            AND earned_date IS NOT NULL
        ORDER BY earned_date DESC
    ');
    $stmt->execute([$cid]);
    $allAchievements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $payload['achievement_timeline'] = $allAchievements;

    // Achievement clusters (days with 3+ achievements)
    $stmt = $pdo->prepare('
        SELECT 
            DATE(FROM_UNIXTIME(earned_date)) as achievement_date,
            COUNT(*) as achievement_count,
            SUM(points) as points_earned
        FROM series_achievements
        WHERE character_id = ?
            AND earned = 1
            AND earned_date IS NOT NULL
        GROUP BY achievement_date
        HAVING achievement_count >= 3
        ORDER BY achievement_count DESC, points_earned DESC
        LIMIT 10
    ');
    $stmt->execute([$cid]);
    $payload['achievement_spam_days'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Achievements per month (for timeline graph)
    $stmt = $pdo->prepare('
        SELECT 
            DATE_FORMAT(FROM_UNIXTIME(earned_date), "%Y-%m") as month,
            COUNT(*) as achievement_count,
            SUM(points) as points_earned
        FROM series_achievements
        WHERE character_id = ?
            AND earned = 1
            AND earned_date IS NOT NULL
        GROUP BY month
        ORDER BY month
    ');
    $stmt->execute([$cid]);
    $payload['achievements_per_month'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

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