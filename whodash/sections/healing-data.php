<?php
// sections/healing-data.php
declare(strict_types=1);

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
$own = $pdo->prepare('SELECT id, name, realm, faction FROM characters WHERE id = ? AND user_id = ?');
$own->execute([$cid, $_SESSION['user_id'] ?? null]);
$character = $own->fetch(PDO::FETCH_ASSOC);
if (!$character) {
    http_response_code(403);
    echo json_encode(['error' => 'Character not found or not yours']);
    exit;
}

// Initialize payload
$payload = [
    'overview' => [
        'avg_hps' => 0,
        'highest_hps' => 0,
        'highest_hps_target' => null,
        'highest_hps_date' => null,
        'total_healing_done' => 0,
        'total_overhealing' => 0,
        'avg_overheal_pct' => 0,
        'total_encounters' => 0,
        'healing_uptime_seconds' => 0,
        'effective_healing_pct' => 0,
    ],
    'hps_timeseries' => [],
    'performance_by_instance' => [],
    'healing_breakdown' => [
        'solo' => 0,
        'party' => 0,
        'raid' => 0,
    ],
    'healing_encounters' => [],
    'hps_distribution' => [],
    'overheal_analysis' => [
        'by_encounter_type' => [],
        'improvement_opportunities' => [],
    ],
    'efficiency_metrics' => [
        'best_efficiency' => [],
        'worst_efficiency' => [],
    ],
];

// ========================================================================
// OVERVIEW STATS
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_encounters,
            AVG(hps) as avg_hps,
            MAX(hps) as highest_hps,
            SUM(total_healing) as total_healing_done,
            SUM(total_overheal) as total_overhealing,
            AVG(overheal_pct) as avg_overheal_pct,
            SUM(duration) as healing_uptime_seconds
        FROM combat_encounters
        WHERE character_id = ?
          AND hps > 0
    ');
    $stmt->execute([$cid]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        $totalHealing = (int) ($stats['total_healing_done'] ?? 0);
        $totalOverheal = (int) ($stats['total_overhealing'] ?? 0);
        $effectiveHealing = $totalHealing - $totalOverheal;
        $effectivePct = $totalHealing > 0 ? (($effectiveHealing / $totalHealing) * 100) : 0;

        $payload['overview']['avg_hps'] = round((float) ($stats['avg_hps'] ?? 0));
        $payload['overview']['highest_hps'] = round((float) ($stats['highest_hps'] ?? 0));
        $payload['overview']['total_healing_done'] = $totalHealing;
        $payload['overview']['total_overhealing'] = $totalOverheal;
        $payload['overview']['avg_overheal_pct'] = round((float) ($stats['avg_overheal_pct'] ?? 0), 1);
        $payload['overview']['total_encounters'] = (int) ($stats['total_encounters'] ?? 0);
        $payload['overview']['healing_uptime_seconds'] = round((float) ($stats['healing_uptime_seconds'] ?? 0));
        $payload['overview']['effective_healing_pct'] = round($effectivePct, 1);
    }

    // Get highest HPS encounter details
    $highStmt = $pdo->prepare('
        SELECT target, ts
        FROM combat_encounters
        WHERE character_id = ? AND hps IS NOT NULL AND hps > 0
        ORDER BY hps DESC
        LIMIT 1
    ');
    $highStmt->execute([$cid]);
    $highestHps = $highStmt->fetch(PDO::FETCH_ASSOC);

    if ($highestHps) {
        $payload['overview']['highest_hps_target'] = $highestHps['target'];
        $payload['overview']['highest_hps_date'] = date('M j, Y', (int) $highestHps['ts']);
    }
} catch (Throwable $e) {
    error_log("Healing overview error: " . $e->getMessage());
}

// ========================================================================
// HPS OVER TIME (Last 30 days)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT ts, hps, target, overheal_pct
        FROM combat_encounters
        WHERE character_id = ?
          AND ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
          AND hps IS NOT NULL
          AND hps > 0
        ORDER BY ts ASC
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['hps_timeseries'][] = [
            'ts' => (int) $row['ts'],
            'hps' => round((float) $row['hps']),
            'target' => $row['target'],
            'overheal_pct' => round((float) $row['overheal_pct'], 1),
        ];
    }
} catch (Throwable $e) {
    error_log("HPS timeseries error: " . $e->getMessage());
}

// ========================================================================
// PERFORMANCE BY INSTANCE
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            instance,
            AVG(hps) as avg_hps,
            COUNT(*) as encounter_count,
            AVG(duration) as avg_duration,
            AVG(overheal_pct) as avg_overheal_pct
        FROM combat_encounters
        WHERE character_id = ? 
          AND instance IS NOT NULL
          AND hps IS NOT NULL
          AND hps > 0
        GROUP BY instance
        ORDER BY avg_hps DESC
        LIMIT 15
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['performance_by_instance'][] = [
            'instance' => $row['instance'],
            'avg_hps' => round((float) $row['avg_hps']),
            'encounter_count' => (int) $row['encounter_count'],
            'avg_duration' => round((float) $row['avg_duration'], 1),
            'avg_overheal_pct' => round((float) $row['avg_overheal_pct'], 1),
        ];
    }
} catch (Throwable $e) {
    error_log("Instance performance error: " . $e->getMessage());
}

// ========================================================================
// HEALING BREAKDOWN (Solo vs Party vs Raid time)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            group_type,
            SUM(duration) as total_duration
        FROM combat_encounters
        WHERE character_id = ?
          AND hps > 0
        GROUP BY group_type
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $row['group_type'] ?? 'solo';
        $payload['healing_breakdown'][$type] = round((float) $row['total_duration']);
    }
} catch (Throwable $e) {
    error_log("Healing breakdown error: " . $e->getMessage());
}

// ========================================================================
// HEALING ENCOUNTERS TABLE
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            target,
            instance,
            instance_difficulty,
            hps,
            overheal_pct,
            duration,
            ts,
            total_healing,
            total_overheal,
            group_type,
            group_size,
            is_boss
        FROM combat_encounters
        WHERE character_id = ? 
          AND hps > 0
        ORDER BY ts DESC
        LIMIT 100
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $totalHeal = (int) $row['total_healing'];
        $overheal = (int) $row['total_overheal'];
        $effectiveHeal = $totalHeal - $overheal;

        $payload['healing_encounters'][] = [
            'target' => $row['target'],
            'instance' => $row['instance'],
            'difficulty' => $row['instance_difficulty'],
            'hps' => round((float) ($row['hps'] ?? 0)),
            'overheal_pct' => round((float) ($row['overheal_pct'] ?? 0), 1),
            'duration' => round((float) $row['duration'], 1),
            'ts' => (int) $row['ts'],
            'date' => date('M j, Y', (int) $row['ts']),
            'total_healing' => $totalHeal,
            'total_overheal' => $overheal,
            'effective_healing' => $effectiveHeal,
            'group_type' => $row['group_type'],
            'group_size' => (int) $row['group_size'],
            'is_boss' => (bool) $row['is_boss'],
        ];
    }
} catch (Throwable $e) {
    error_log("Healing encounters error: " . $e->getMessage());
}

// ========================================================================
// HPS DISTRIBUTION (Histogram data)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT hps
        FROM combat_encounters
        WHERE character_id = ? AND hps IS NOT NULL AND hps > 0
    ');
    $stmt->execute([$cid]);

    $hpsValues = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $hpsValues[] = (float) $row['hps'];
    }

    if (!empty($hpsValues)) {
        sort($hpsValues);

        // Create histogram buckets
        $min = min($hpsValues);
        $max = max($hpsValues);
        $bucketSize = ($max - $min) / 10;

        $buckets = array_fill(0, 10, 0);

        foreach ($hpsValues as $hps) {
            $bucketIndex = min(9, floor(($hps - $min) / $bucketSize));
            $buckets[$bucketIndex]++;
        }

        for ($i = 0; $i < 10; $i++) {
            $rangeStart = round($min + ($i * $bucketSize));
            $rangeEnd = round($min + (($i + 1) * $bucketSize));

            $payload['hps_distribution'][] = [
                'range' => "{$rangeStart}-{$rangeEnd}",
                'count' => $buckets[$i],
                'rangeStart' => $rangeStart,
                'rangeEnd' => $rangeEnd,
            ];
        }

        // Calculate percentiles
        $count = count($hpsValues);
        $payload['hps_percentiles'] = [
            'p50' => round($hpsValues[floor($count * 0.5)]),
            'p75' => round($hpsValues[floor($count * 0.75)]),
            'p90' => round($hpsValues[floor($count * 0.9)]),
        ];
    }
} catch (Throwable $e) {
    error_log("HPS distribution error: " . $e->getMessage());
}

// ========================================================================
// OVERHEAL ANALYSIS
// ========================================================================

try {
    // Overheal by encounter type
    $stmt = $pdo->prepare('
        SELECT 
            group_type,
            AVG(overheal_pct) as avg_overheal_pct,
            COUNT(*) as encounter_count
        FROM combat_encounters
        WHERE character_id = ?
          AND hps > 0
        GROUP BY group_type
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['overheal_analysis']['by_encounter_type'][] = [
            'type' => $row['group_type'],
            'avg_overheal_pct' => round((float) $row['avg_overheal_pct'], 1),
            'encounter_count' => (int) $row['encounter_count'],
        ];
    }

    // Find encounters with high overheal (improvement opportunities)
    $stmt = $pdo->prepare('
        SELECT 
            target,
            instance,
            overheal_pct,
            hps,
            ts
        FROM combat_encounters
        WHERE character_id = ?
          AND hps > 0
          AND overheal_pct > 30
        ORDER BY overheal_pct DESC
        LIMIT 10
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['overheal_analysis']['improvement_opportunities'][] = [
            'target' => $row['target'],
            'instance' => $row['instance'],
            'overheal_pct' => round((float) $row['overheal_pct'], 1),
            'hps' => round((float) $row['hps']),
            'date' => date('M j, Y', (int) $row['ts']),
        ];
    }
} catch (Throwable $e) {
    error_log("Overheal analysis error: " . $e->getMessage());
}

// ========================================================================
// EFFICIENCY METRICS (Best/Worst Efficiency)
// ========================================================================

try {
    // Best efficiency (high HPS, low overheal)
    $stmt = $pdo->prepare('
        SELECT 
            target,
            instance,
            hps,
            overheal_pct,
            duration,
            ts
        FROM combat_encounters
        WHERE character_id = ?
          AND hps > 0
          AND overheal_pct < 20
        ORDER BY hps DESC
        LIMIT 5
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['efficiency_metrics']['best_efficiency'][] = [
            'target' => $row['target'],
            'instance' => $row['instance'],
            'hps' => round((float) $row['hps']),
            'overheal_pct' => round((float) $row['overheal_pct'], 1),
            'duration' => round((float) $row['duration'], 1),
            'date' => date('M j', (int) $row['ts']),
        ];
    }

    // Worst efficiency (low effective healing ratio)
    $stmt = $pdo->prepare('
        SELECT 
            target,
            instance,
            hps,
            overheal_pct,
            duration,
            ts
        FROM combat_encounters
        WHERE character_id = ?
          AND hps > 0
          AND duration > 30
        ORDER BY overheal_pct DESC
        LIMIT 5
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['efficiency_metrics']['worst_efficiency'][] = [
            'target' => $row['target'],
            'instance' => $row['instance'],
            'hps' => round((float) $row['hps']),
            'overheal_pct' => round((float) $row['overheal_pct'], 1),
            'duration' => round((float) $row['duration'], 1),
            'date' => date('M j', (int) $row['ts']),
        ];
    }
} catch (Throwable $e) {
    error_log("Efficiency metrics error: " . $e->getMessage());
}

// Emit payload
echo json_encode($payload);