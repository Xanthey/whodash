<?php
// sections/bazaar-alerts.php - Auction alert management
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Suppress PHP warnings that could corrupt JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$payload = [
    'summary' => [
        'total_alerts' => 0,
        'enabled_alerts' => 0,
        'unread_notifications' => 0,
        'alerts_triggered_today' => 0
    ],
    'active_alerts' => [],
    'recent_notifications' => [],
    'price_watch_list' => []
];

try {
    // Summary stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN enabled = TRUE THEN 1 ELSE 0 END) as enabled
        FROM bazaar_auction_alerts
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $payload['summary']['total_alerts'] = (int) ($row['total'] ?? 0);
    $payload['summary']['enabled_alerts'] = (int) ($row['enabled'] ?? 0);

    // Unread notifications
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as unread
        FROM bazaar_alert_notifications
        WHERE user_id = ? AND is_read = FALSE
    ");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $payload['summary']['unread_notifications'] = (int) ($row['unread'] ?? 0);

    // Alerts triggered today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as today_count
        FROM bazaar_alert_notifications
        WHERE user_id = ?
            AND DATE(created_at) = CURDATE()
    ");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $payload['summary']['alerts_triggered_today'] = (int) ($row['today_count'] ?? 0);

    // Active alerts
    $stmt = $pdo->prepare("
        SELECT 
            id,
            alert_type,
            item_name,
            character_id,
            threshold_value,
            enabled,
            last_triggered,
            created_at
        FROM bazaar_auction_alerts
        WHERE user_id = ?
        ORDER BY enabled DESC, created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['active_alerts'][] = [
            'id' => (int) $row['id'],
            'alert_type' => $row['alert_type'],
            'item_name' => $row['item_name'],
            'character_id' => $row['character_id'] ? (int) $row['character_id'] : null,
            'threshold_value' => (int) ($row['threshold_value'] ?? 0),
            'enabled' => (bool) $row['enabled'],
            'last_triggered' => $row['last_triggered'],
            'created_at' => $row['created_at']
        ];
    }

    // Recent notifications
    $stmt = $pdo->prepare("
        SELECT 
            id,
            alert_id,
            message,
            alert_data,
            is_read,
            created_at
        FROM bazaar_alert_notifications
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['recent_notifications'][] = [
            'id' => (int) $row['id'],
            'alert_id' => (int) $row['alert_id'],
            'message' => $row['message'],
            'alert_data' => $row['alert_data'] ? json_decode($row['alert_data'], true) : null,
            'is_read' => (bool) $row['is_read'],
            'created_at' => $row['created_at']
        ];
    }

    // Price watch list
    $stmt = $pdo->prepare("
        SELECT 
            id,
            item_name,
            target_price,
            notify_on_drop,
            notify_on_rise,
            enabled,
            created_at
        FROM bazaar_price_watch
        WHERE user_id = ?
        ORDER BY enabled DESC, created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$user_id]);
    
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['price_watch_list'][] = [
            'id' => (int) $row['id'],
            'item_name' => $row['item_name'],
            'target_price' => (int) ($row['target_price'] ?? 0),
            'notify_on_drop' => (bool) $row['notify_on_drop'],
            'notify_on_rise' => (bool) $row['notify_on_rise'],
            'enabled' => (bool) $row['enabled'],
            'created_at' => $row['created_at']
        ];
    }

} catch (Throwable $e) {
    error_log("Bazaar alerts error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error loading alerts data']);
    exit;
}

echo json_encode($payload);
