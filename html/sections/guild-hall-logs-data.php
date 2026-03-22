<?php
// sections/guild-hall-logs-data.php
// Provides guild bank logs (items and money) for Guild Hall

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
        'item_logs' => [],
        'money_logs' => []
    ];

    // Get item transaction logs (last 500)
    $stmt = $pdo->prepare("
        SELECT 
            ts,
            type,
            player_name,
            item_id,
            item_name,
            item_link,
            count,
            tab,
            tab_from,
            tab_to,
            year,
            month,
            day,
            hour
        FROM guild_bank_transaction_logs
        WHERE guild_id = ?
        ORDER BY ts DESC
        LIMIT 500
    ");
    $stmt->execute([$guild_id]);
    $payload['item_logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get money transaction logs (last 500)
    $stmt = $pdo->prepare("
        SELECT 
            ts,
            type,
            player_name,
            amount_copper,
            year,
            month,
            day,
            hour
        FROM guild_bank_money_logs
        WHERE guild_id = ?
        ORDER BY ts DESC
        LIMIT 500
    ");
    $stmt->execute([$guild_id]);
    $payload['money_logs'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($payload);

} catch (PDOException $e) {
    error_log("Logs data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}