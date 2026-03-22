<?php
require_once __DIR__ . '/../db.php';

// Start session first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['character_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing character_id']);
    exit;
}

$cid = (int) $_GET['character_id'];

// Validate ownership OR public access
$character = null;

// First try: owned character (authenticated)
if (isset($_SESSION['user_id'])) {
    $own = $pdo->prepare('
        SELECT id, name, guild_name, class_file, class_local, race, race_file, sex, faction, realm 
        FROM characters 
        WHERE id = ? AND user_id = ?
    ');
    $own->execute([$cid, $_SESSION['user_id']]);
    $character = $own->fetch(PDO::FETCH_ASSOC);
}

// Second try: public character (no authentication required)
if (!$character) {
    $pub = $pdo->prepare('
        SELECT id, name, guild_name, class_file, class_local, race, race_file, sex, faction, realm 
        FROM characters 
        WHERE id = ? AND visibility = "PUBLIC"
    ');
    $pub->execute([$cid]);
    $character = $pub->fetch(PDO::FETCH_ASSOC);
}

// If neither worked, deny access
if (!$character) {
    http_response_code(403);
    echo json_encode(['error' => 'Character not found or not accessible']);
    exit;
}

// Use $cid consistently throughout the file
$character_id = $cid;

try {
    // Initialize response
    $data = [
        'overview' => [],
        'groups' => [],
        'friends' => [],
        'timeline' => []
    ];

    // ===== OVERVIEW STATS =====
    // Total unique players grouped with
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT member_name) as unique_players
        FROM (
            SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(member.value, '$.name')) as member_name
            FROM group_compositions gc,
                JSON_TABLE(gc.members, '$[*]' COLUMNS(
                    value JSON PATH '$'
                )) as member
            WHERE gc.character_id = ?
            AND JSON_UNQUOTE(JSON_EXTRACT(member.value, '$.name')) != ?
        ) as all_members
    ");
    $stmt->execute([$character_id, $character['name']]);
    $uniquePlayers = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['unique_players'] ?? 0);

    // Total groups formed
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM group_compositions WHERE character_id = ?");
    $stmt->execute([$character_id]);
    $totalGroups = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    // Party vs Raid breakdown
    $stmt = $pdo->prepare("
        SELECT 
            type,
            COUNT(*) as count,
            AVG(size) as avg_size,
            MAX(size) as max_size
        FROM group_compositions 
        WHERE character_id = ? 
        GROUP BY type
    ");
    $stmt->execute([$character_id]);
    $groupTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Corrected Instance Runs Query
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_runs,
                COUNT(DISTINCT instance) as unique_instances
        FROM group_compositions
        WHERE character_id = ?
        AND (instance IS NOT NULL OR instance != '')
        ");

    // Execute and fetch the results
    $stmt->execute([$character['id']]);
    $instanceStats = $stmt->fetch(PDO::FETCH_ASSOC);


    // Friend stats
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN action = 'added' THEN 1 ELSE 0 END) as friends_added,
            SUM(CASE WHEN action = 'removed' THEN 1 ELSE 0 END) as friends_removed
        FROM friend_list_changes 
        WHERE character_id = ?
    ");
    $stmt->execute([$character_id]);
    $friendStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Time-based stats
    $stmt = $pdo->prepare("
        SELECT 
            MIN(ts) as first_group,
            MAX(ts) as last_group
        FROM group_compositions 
        WHERE character_id = ?
    ");
    $stmt->execute([$character_id]);
    $timeStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $data['overview'] = [
        'unique_players' => $uniquePlayers,
        'total_groups' => $totalGroups,
        'group_types' => $groupTypes,
        'instance_stats' => $instanceStats,
        'friend_stats' => $friendStats,
        'time_stats' => $timeStats
    ];

    // ===== PARTY ANIMALS (Most Grouped With) =====
    $stmt = $pdo->prepare("
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(member.value, '$.name')) as player_name,
            JSON_UNQUOTE(JSON_EXTRACT(member.value, '$.class')) as class,
            COUNT(*) as times_grouped,
            MAX(gc.ts) as last_grouped
        FROM group_compositions gc,
            JSON_TABLE(gc.members, '$[*]' COLUMNS(
                value JSON PATH '$'
            )) as member
        WHERE gc.character_id = ?
        AND JSON_UNQUOTE(JSON_EXTRACT(member.value, '$.name')) != ?
        GROUP BY player_name, class
        ORDER BY times_grouped DESC
        LIMIT 50
    ");
    $stmt->execute([$character_id, $character['name']]);
    $data['groups']['most_grouped_with'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group size distribution
    $stmt = $pdo->prepare("
        SELECT 
            size,
            COUNT(*) as count
        FROM group_compositions 
        WHERE character_id = ? 
        GROUP BY size
        ORDER BY size ASC
    ");
    $stmt->execute([$character_id]);
    $data['groups']['size_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Class composition (what classes do you group with most)
    $stmt = $pdo->prepare("
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(member.value, '$.class')) as class,
            COUNT(*) as count
        FROM group_compositions gc,
            JSON_TABLE(gc.members, '$[*]' COLUMNS(
                value JSON PATH '$'
            )) as member
        WHERE gc.character_id = ?
        AND JSON_UNQUOTE(JSON_EXTRACT(member.value, '$.class')) IS NOT NULL
        AND JSON_UNQUOTE(JSON_EXTRACT(member.value, '$.name')) != ?
        GROUP BY class
        ORDER BY count DESC
    ");
    $stmt->execute([$character_id, $character['name']]);
    $data['groups']['class_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Most popular instances
    $stmt = $pdo->prepare("
        SELECT 
            instance,
            instance_difficulty,
            COUNT(*) as runs,
            MAX(ts) as last_run,
            AVG(size) as avg_group_size
        FROM group_compositions 
        WHERE character_id = ? 
        AND instance IS NOT NULL 
        AND instance != ''
        GROUP BY instance, instance_difficulty
        ORDER BY runs DESC
        LIMIT 20
    ");
    $stmt->execute([$character_id]);
    $data['groups']['popular_instances'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== FRIEND NETWORK =====
    // Friend list changes over time
    $stmt = $pdo->prepare("
        SELECT 
            DATE(FROM_UNIXTIME(ts)) as date,
            SUM(CASE WHEN action = 'added' THEN 1 ELSE 0 END) as added,
            SUM(CASE WHEN action = 'removed' THEN 1 ELSE 0 END) as removed
        FROM friend_list_changes
        WHERE character_id = ?
        GROUP BY DATE(FROM_UNIXTIME(ts))
        ORDER BY date ASC
    ");
    $stmt->execute([$character_id]);
    $data['friends']['changes_over_time'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Most recent friend additions
    $stmt = $pdo->prepare("
        SELECT 
            friend_name,
            friend_class,
            friend_level,
            ts,
            action
        FROM friend_list_changes
        WHERE character_id = ?
        AND action = 'added'
        ORDER BY ts DESC
        LIMIT 20
    ");
    $stmt->execute([$character_id]);
    $data['friends']['recent_additions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Friend removals (if any)
    $stmt = $pdo->prepare("
        SELECT 
            friend_name,
            ts
        FROM friend_list_changes
        WHERE character_id = ?
        AND action = 'removed'
        ORDER BY ts DESC
        LIMIT 10
    ");
    $stmt->execute([$character_id]);
    $data['friends']['recent_removals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== TIMELINE =====
    // Recent group formations with full details
    $stmt = $pdo->prepare("
        SELECT 
            ts,
            type,
            size,
            instance,
            instance_difficulty,
            zone,
            subzone,
            members
        FROM group_compositions 
        WHERE character_id = ? 
        ORDER BY ts DESC
        LIMIT 100
    ");
    $stmt->execute([$character_id]);
    $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Decode JSON members for each group
    foreach ($timeline as &$group) {
        if ($group['members']) {
            $decoded = json_decode($group['members'], true);
            $group['members'] = is_array($decoded) ? $decoded : [];
        } else {
            $group['members'] = [];
        }
    }
    $data['timeline']['recent_groups'] = $timeline;

    // Activity by hour of day
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(FROM_UNIXTIME(ts)) as hour,
            COUNT(*) as count
        FROM group_compositions
        WHERE character_id = ?
        GROUP BY HOUR(FROM_UNIXTIME(ts))
        ORDER BY hour ASC
    ");
    $stmt->execute([$character_id]);
    $data['timeline']['activity_by_hour'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Activity by day of week
    $stmt = $pdo->prepare("
        SELECT 
            DAYOFWEEK(FROM_UNIXTIME(ts)) as day_of_week,
            COUNT(*) as count
        FROM group_compositions
        WHERE character_id = ?
        GROUP BY DAYOFWEEK(FROM_UNIXTIME(ts))
        ORDER BY day_of_week ASC
    ");
    $stmt->execute([$character_id]);
    $data['timeline']['activity_by_day'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Output JSON
    echo json_encode($data, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Social data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}