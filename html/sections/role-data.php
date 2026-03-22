<?php
// sections/role-data.php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');



// Get character_id
$character_id = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

if (!$character_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No character_id provided']);
    exit;
}

// Verify ownership OR public access
$character = null;

// First try: owned character (authenticated user)
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, realm, name, faction, class_file, visibility FROM characters WHERE id = ? AND user_id = ?');
    $stmt->execute([$character_id, $_SESSION['user_id']]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Second try: public character (no authentication required)
if (!$character) {
    $stmt = $pdo->prepare('SELECT id, realm, name, faction, class_file, visibility FROM characters WHERE id = ? AND visibility = "PUBLIC"');
    $stmt->execute([$character_id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$character) {
    http_response_code(403);
    echo json_encode(['error' => 'Character not found or not accessible']);
    exit;
}

// Initialize payload
$payload = [
    'damage_stats' => [
        'avg_dps' => 0,
        'max_dps' => 0,
        'total_damage' => 0,
        'encounters' => 0,
        'uptime_hours' => 0,
    ],
    'tanking_stats' => [
        'avg_dtps' => 0,
        'max_dtps' => 0,
        'total_damage_taken' => 0,
        'encounters' => 0,
        'deaths' => 0,
        'survival_rate' => 0,
    ],
    'healing_stats' => [
        'avg_hps' => 0,
        'max_hps' => 0,
        'total_healing' => 0,
        'encounters' => 0,
        'avg_overheal_pct' => 0,
    ],
    'role_distribution' => [
        'damage_time' => 0,
        'tanking_time' => 0,
        'healing_time' => 0,
    ],
    'primary_role' => null,
];

// ========================================================================
// DAMAGE STATS
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as encounters,
            AVG(dps) as avg_dps,
            MAX(dps) as max_dps,
            SUM(total_damage) as total_damage,
            SUM(duration) as uptime_seconds
        FROM combat_encounters
        WHERE character_id = ? AND dps > 0
    ');
    $stmt->execute([$character_id]);
    $damageStats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($damageStats) {
        $payload['damage_stats'] = [
            'avg_dps' => round((float) ($damageStats['avg_dps'] ?? 0)),
            'max_dps' => round((float) ($damageStats['max_dps'] ?? 0)),
            'total_damage' => (int) ($damageStats['total_damage'] ?? 0),
            'encounters' => (int) ($damageStats['encounters'] ?? 0),
            'uptime_hours' => round((float) ($damageStats['uptime_seconds'] ?? 0) / 3600, 1),
        ];

        $payload['role_distribution']['damage_time'] = (float) ($damageStats['uptime_seconds'] ?? 0);
    }
} catch (Throwable $e) {
    error_log("Damage stats error: " . $e->getMessage());
}

// ========================================================================
// TANKING STATS
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as encounters,
            AVG(dtps) as avg_dtps,
            MAX(dtps) as max_dtps,
            SUM(total_damage_taken) as total_damage_taken,
            SUM(duration) as uptime_seconds
        FROM combat_encounters
        WHERE character_id = ? AND dtps > 0
    ');
    $stmt->execute([$character_id]);
    $tankStats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get death count
    $deathStmt = $pdo->prepare('SELECT COUNT(*) as death_count FROM deaths WHERE character_id = ?');
    $deathStmt->execute([$character_id]);
    $deathCount = $deathStmt->fetch(PDO::FETCH_ASSOC);

    if ($tankStats) {
        $encounters = (int) ($tankStats['encounters'] ?? 0);
        $deaths = (int) ($deathCount['death_count'] ?? 0);
        $survivalRate = $encounters > 0 ? round((($encounters - $deaths) / $encounters) * 100, 1) : 100;

        $payload['tanking_stats'] = [
            'avg_dtps' => round((float) ($tankStats['avg_dtps'] ?? 0)),
            'max_dtps' => round((float) ($tankStats['max_dtps'] ?? 0)),
            'total_damage_taken' => (int) ($tankStats['total_damage_taken'] ?? 0),
            'encounters' => $encounters,
            'deaths' => $deaths,
            'survival_rate' => $survivalRate,
        ];

        $payload['role_distribution']['tanking_time'] = (float) ($tankStats['uptime_seconds'] ?? 0);
    }
} catch (Throwable $e) {
    error_log("Tanking stats error: " . $e->getMessage());
}

// ========================================================================
// HEALING STATS
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as encounters,
            AVG(hps) as avg_hps,
            MAX(hps) as max_hps,
            SUM(total_healing) as total_healing,
            AVG(overheal_pct) as avg_overheal_pct,
            SUM(duration) as uptime_seconds
        FROM combat_encounters
        WHERE character_id = ? AND hps > 0
    ');
    $stmt->execute([$character_id]);
    $healStats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($healStats) {
        $payload['healing_stats'] = [
            'avg_hps' => round((float) ($healStats['avg_hps'] ?? 0)),
            'max_hps' => round((float) ($healStats['max_hps'] ?? 0)),
            'total_healing' => (int) ($healStats['total_healing'] ?? 0),
            'encounters' => (int) ($healStats['encounters'] ?? 0),
            'avg_overheal_pct' => round((float) ($healStats['avg_overheal_pct'] ?? 0), 1),
        ];

        $payload['role_distribution']['healing_time'] = (float) ($healStats['uptime_seconds'] ?? 0);
    }
} catch (Throwable $e) {
    error_log("Healing stats error: " . $e->getMessage());
}

// ========================================================================
// DETERMINE PRIMARY ROLE
// ========================================================================

$times = $payload['role_distribution'];
$maxTime = max($times['damage_time'], $times['tanking_time'], $times['healing_time']);

if ($maxTime > 0) {
    if ($times['damage_time'] === $maxTime) {
        $payload['primary_role'] = 'damage';
    } elseif ($times['tanking_time'] === $maxTime) {
        $payload['primary_role'] = 'tanking';
    } else {
        $payload['primary_role'] = 'healing';
    }
}

// Emit payload
echo json_encode($payload);