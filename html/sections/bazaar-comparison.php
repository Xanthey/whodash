<?php
// sections/bazaar-comparison.php - Character comparison tool
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=30');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Parse requested character IDs from ?ids=1,2,3
$compare_ids = [];
if (!empty($_GET['ids'])) {
    $compare_ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
}

$payload = [
    'characters' => [],
    'comparison' => null,
];

try {
    // All characters for the selection UI
    $stmt = $pdo->prepare('
        SELECT id, name, realm, faction, class_file, guild_name
        FROM characters
        WHERE user_id = ?
        ORDER BY name
    ');
    $stmt->execute([$user_id]);
    $all_characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($all_characters)) {
        echo json_encode($payload);
        exit;
    }

    foreach ($all_characters as $char) {
        $payload['characters'][] = [
            'id' => (int) $char['id'],
            'name' => $char['name'],
            'realm' => $char['realm'],
            'faction' => $char['faction'] ?? 'Unknown',
            'class_file' => $char['class_file'] ?? '',
            'guild_name' => $char['guild_name'] ?? '',
        ];
    }

    // Only build comparison data if IDs were requested
    if (empty($compare_ids)) {
        echo json_encode($payload);
        exit;
    }

    // Validate requested IDs belong to this user
    $valid_ids = array_column($all_characters, 'id');
    $compare_ids = array_values(array_intersect($compare_ids, $valid_ids));

    if (empty($compare_ids)) {
        echo json_encode($payload);
        exit;
    }

    $char_map = [];
    foreach ($all_characters as $c) {
        $char_map[(int) $c['id']] = $c;
    }

    $placeholders = implode(',', array_fill(0, count($compare_ids), '?'));

    // --- Level (latest value from series_level) ---
    $stmt = $pdo->prepare("
        SELECT sl.character_id, sl.value AS level
        FROM series_level sl
        INNER JOIN (
            SELECT character_id, MAX(ts) AS max_ts
            FROM series_level
            WHERE character_id IN ($placeholders)
            GROUP BY character_id
        ) latest ON sl.character_id = latest.character_id AND sl.ts = latest.max_ts
    ");
    $stmt->execute($compare_ids);
    $levels = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $levels[(int) $row['character_id']] = (int) $row['level'];
    }

    // --- Gold (latest value from series_money) ---
    $stmt = $pdo->prepare("
        SELECT sm.character_id, sm.value AS gold
        FROM series_money sm
        INNER JOIN (
            SELECT character_id, MAX(ts) AS max_ts
            FROM series_money
            WHERE character_id IN ($placeholders)
            GROUP BY character_id
        ) latest ON sm.character_id = latest.character_id AND sm.ts = latest.max_ts
    ");
    $stmt->execute($compare_ids);
    $gold = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $gold[(int) $row['character_id']] = (int) $row['gold'];
    }

    // --- Avg equipped ilvl ---
    $stmt = $pdo->prepare("
        SELECT character_id,
               ROUND(AVG(ilvl)) AS avg_ilvl,
               MAX(ilvl)        AS max_ilvl,
               COUNT(*)         AS gear_slots
        FROM equipment_snapshot
        WHERE character_id IN ($placeholders)
          AND ilvl > 0
        GROUP BY character_id
    ");
    $stmt->execute($compare_ids);
    $gear = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $gear[(int) $row['character_id']] = [
            'avg_ilvl' => (int) $row['avg_ilvl'],
            'max_ilvl' => (int) $row['max_ilvl'],
            'gear_slots' => (int) $row['gear_slots'],
        ];
    }

    // --- Achievement points ---
    $stmt = $pdo->prepare("
        SELECT character_id,
               COUNT(*)       AS total_achievements,
               SUM(points)    AS total_points
        FROM series_achievements
        WHERE character_id IN ($placeholders)
          AND earned = 1
        GROUP BY character_id
    ");
    $stmt->execute($compare_ids);
    $achievements = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $achievements[(int) $row['character_id']] = [
            'count' => (int) $row['total_achievements'],
            'points' => (int) $row['total_points'],
        ];
    }

    // --- Deaths ---
    $stmt = $pdo->prepare("
        SELECT character_id, COUNT(*) AS death_count
        FROM deaths
        WHERE character_id IN ($placeholders)
        GROUP BY character_id
    ");
    $stmt->execute($compare_ids);
    $deaths = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $deaths[(int) $row['character_id']] = (int) $row['death_count'];
    }

    // --- Boss kills ---
    $stmt = $pdo->prepare("
        SELECT character_id,
               COUNT(*)                    AS total_kills,
               COUNT(DISTINCT boss_name)   AS unique_bosses,
               COUNT(DISTINCT instance)    AS instances
        FROM boss_kills
        WHERE character_id IN ($placeholders)
        GROUP BY character_id
    ");
    $stmt->execute($compare_ids);
    $boss_kills = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $boss_kills[(int) $row['character_id']] = [
            'total' => (int) $row['total_kills'],
            'unique' => (int) $row['unique_bosses'],
            'instances' => (int) $row['instances'],
        ];
    }

    // --- Quests completed ---
    $stmt = $pdo->prepare("
        SELECT character_id, COUNT(DISTINCT quest_id) AS quests_completed
        FROM quest_events
        WHERE character_id IN ($placeholders)
          AND kind = 'completed'
        GROUP BY character_id
    ");
    $stmt->execute($compare_ids);
    $quests = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $quests[(int) $row['character_id']] = (int) $row['quests_completed'];
    }

    // --- Bag item count ---
    $stmt = $pdo->prepare("
        SELECT character_id, COUNT(*) AS bag_items, SUM(count) AS bag_stacks
        FROM containers_bag
        WHERE character_id IN ($placeholders)
          AND item_id IS NOT NULL
        GROUP BY character_id
    ");
    $stmt->execute($compare_ids);
    $bag = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $bag[(int) $row['character_id']] = [
            'slots' => (int) $row['bag_items'],
            'stacks' => (int) $row['bag_stacks'],
        ];
    }

    // --- Auction earnings (30d) ---
    $compare_data = [];
    foreach ($compare_ids as $char_id) {
        $char = $char_map[$char_id] ?? null;
        if (!$char)
            continue;

        $earnings_30d = 0;
        $sales_30d = 0;
        try {
            $char_key = $char['realm'] . '-' . ($char['faction'] ?? '') . ':' . $char['name'];
            $stmt = $pdo->prepare("
                SELECT COUNT(*) AS sales, COALESCE(SUM(sold_price), 0) AS earnings
                FROM auction_owner_rows
                WHERE rf_char_key = ?
                  AND sold = 1
                  AND sold_ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            ");
            $stmt->execute([$char_key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $earnings_30d = (int) ($row['earnings'] ?? 0);
            $sales_30d = (int) ($row['sales'] ?? 0);
        } catch (Throwable $e) { /* skip */
        }

        $compare_data[] = [
            'character_id' => $char_id,
            'name' => $char['name'],
            'realm' => $char['realm'],
            'faction' => $char['faction'] ?? 'Unknown',
            'class_file' => $char['class_file'] ?? '',
            'guild_name' => $char['guild_name'] ?? '',
            'level' => $levels[$char_id] ?? 0,
            'gold' => $gold[$char_id] ?? 0,
            'avg_ilvl' => $gear[$char_id]['avg_ilvl'] ?? 0,
            'max_ilvl' => $gear[$char_id]['max_ilvl'] ?? 0,
            'gear_slots' => $gear[$char_id]['gear_slots'] ?? 0,
            'achievements' => $achievements[$char_id]['count'] ?? 0,
            'ach_points' => $achievements[$char_id]['points'] ?? 0,
            'deaths' => $deaths[$char_id] ?? 0,
            'boss_kills' => $boss_kills[$char_id]['total'] ?? 0,
            'unique_bosses' => $boss_kills[$char_id]['unique'] ?? 0,
            'instances' => $boss_kills[$char_id]['instances'] ?? 0,
            'quests' => $quests[$char_id] ?? 0,
            'bag_slots' => $bag[$char_id]['slots'] ?? 0,
            'bag_stacks' => $bag[$char_id]['stacks'] ?? 0,
            'earnings_30d' => $earnings_30d,
            'sales_30d' => $sales_30d,
        ];
    }

    $payload['comparison'] = $compare_data;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    error_log('Bazaar comparison error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}