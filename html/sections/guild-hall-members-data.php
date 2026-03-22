<?php
// sections/guild-hall-members-data.php
// Provides guild member roster data for Guild Hall

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../guild_helpers.php';

header('Content-Type: application/json');
session_start();

// Ensure database connection is available
if (!isset($pdo)) {
    require_once __DIR__ . '/../db.php';
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$guild_id = $_GET['guild_id'] ?? null;
if (!$guild_id) {
    http_response_code(400);
    echo json_encode(['error' => 'guild_id parameter required']);
    exit;
}

try {

    // Verify user has access to this guild
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM character_guilds cg
        INNER JOIN characters c ON cg.character_id = c.id
        WHERE c.user_id = ? AND cg.guild_id = ? AND cg.is_current = TRUE
        LIMIT 1
    ");
    $stmt->execute([$user_id, $guild_id]);

    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this guild']);
        exit;
    }

    $payload = [
        'members' => []
    ];

    // Get most recent roster snapshot for this guild
    $stmt = $pdo->prepare("
        SELECT snapshot_ts
        FROM guild_roster_snapshots
        WHERE guild_id = ?
        ORDER BY snapshot_ts DESC
        LIMIT 1
    ");
    $stmt->execute([$guild_id]);
    $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$snapshot) {
        // No roster data available
        echo json_encode($payload);
        exit;
    }

    $snapshot_ts = $snapshot['snapshot_ts'];

    // Get all members from the most recent snapshot
    $stmt = $pdo->prepare("
        SELECT 
            member_full_name as name,
            `rank`,
            rank_index,
            `level`,
            `class`,
            class_file,
            zone,
            note,
            officer_note,
            online,
            `status`,
            achievement_points
        FROM guild_roster_members
        WHERE guild_id = ? AND snapshot_ts = ?
        ORDER BY rank_index ASC, member_full_name ASC
    ");
    $stmt->execute([$guild_id, $snapshot_ts]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert online status to boolean
    foreach ($members as &$member) {
        $member['online'] = (bool) $member['online'];
        $member['level'] = (int) $member['level'];
        $member['rank_index'] = (int) $member['rank_index'];
        $member['achievement_points'] = (int) $member['achievement_points'];
    }

    $payload['members'] = $members;

    echo json_encode($payload);

} catch (PDOException $e) {
    error_log("Members data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}