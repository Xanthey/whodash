<?php
// sections/combat-data.php
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
        'avg_dps' => 0,
        'highest_dps' => 0,
        'highest_dps_target' => null,
        'highest_dps_date' => null,
        'total_damage' => 0,
        'total_encounters' => 0,
        'combat_uptime_seconds' => 0,
        'avg_encounter_duration' => 0,
    ],
    'dps_timeseries' => [],
    'performance_by_instance' => [],
    'combat_breakdown' => [
        'solo' => 0,
        'party' => 0,
        'raid' => 0,
    ],
    'boss_encounters' => [],
    'dps_distribution' => [],
    'burst_analysis' => [
        'burst_dps' => 0,
        'sustained_dps' => 0,
        'burst_count' => 0,
        'sustained_count' => 0,
    ],
    'target_analysis' => [
        'boss_dps' => 0,
        'adds_dps' => 0,
        'boss_count' => 0,
        'adds_count' => 0,
        'max_boss_dps' => 0,
        'max_adds_dps' => 0,
    ],
    'consistency_metrics' => [
        'mean_dps' => 0,
        'std_deviation' => 0,
        'coefficient_of_variation' => 0,
        'consistency_rating' => 'Unknown',
    ],
];

// ========================================================================
// OVERVIEW STATS
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_encounters,
            AVG(dps) as avg_dps,
            MAX(dps) as highest_dps,
            SUM(total_damage) as total_damage,
            AVG(duration) as avg_duration,
            SUM(duration) as combat_uptime_seconds
        FROM combat_encounters
        WHERE character_id = ?
          AND dps > 0
    ');
    $stmt->execute([$cid]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        $payload['overview']['avg_dps'] = round((float) ($stats['avg_dps'] ?? 0));
        $payload['overview']['highest_dps'] = round((float) ($stats['highest_dps'] ?? 0));
        $payload['overview']['total_damage'] = (int) ($stats['total_damage'] ?? 0);
        $payload['overview']['total_encounters'] = (int) ($stats['total_encounters'] ?? 0);
        $payload['overview']['avg_encounter_duration'] = round((float) ($stats['avg_duration'] ?? 0), 1);
        $payload['overview']['combat_uptime_seconds'] = round((float) ($stats['combat_uptime_seconds'] ?? 0));
    }

    // Get highest DPS encounter details
    $highStmt = $pdo->prepare('
        SELECT target, ts
        FROM combat_encounters
        WHERE character_id = ? AND dps IS NOT NULL AND dps > 0
        ORDER BY dps DESC
        LIMIT 1
    ');
    $highStmt->execute([$cid]);
    $highestDps = $highStmt->fetch(PDO::FETCH_ASSOC);

    if ($highestDps) {
        $payload['overview']['highest_dps_target'] = $highestDps['target'];
        $payload['overview']['highest_dps_date'] = date('M j, Y', (int) $highestDps['ts']);
    }
} catch (Throwable $e) {
    error_log("Combat overview error: " . $e->getMessage());
}

// ========================================================================
// DPS OVER TIME (Last 30 days)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT ts, dps, target
        FROM combat_encounters
        WHERE character_id = ?
          AND ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
          AND dps IS NOT NULL
          AND dps > 0
        ORDER BY ts ASC
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['dps_timeseries'][] = [
            'ts' => (int) $row['ts'],
            'dps' => round((float) $row['dps']),
            'target' => $row['target'],
        ];
    }
} catch (Throwable $e) {
    error_log("DPS timeseries error: " . $e->getMessage());
}

// ========================================================================
// PERFORMANCE BY INSTANCE
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            instance,
            AVG(dps) as avg_dps,
            COUNT(*) as encounter_count,
            AVG(duration) as avg_duration
        FROM combat_encounters
        WHERE character_id = ? 
          AND instance IS NOT NULL
          AND dps IS NOT NULL
          AND dps > 0
        GROUP BY instance
        ORDER BY avg_dps DESC
        LIMIT 15
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['performance_by_instance'][] = [
            'instance' => $row['instance'],
            'avg_dps' => round((float) $row['avg_dps']),
            'encounter_count' => (int) $row['encounter_count'],
            'avg_duration' => round((float) $row['avg_duration'], 1),
        ];
    }
} catch (Throwable $e) {
    error_log("Instance performance error: " . $e->getMessage());
}

// ========================================================================
// COMBAT BREAKDOWN (Solo vs Party vs Raid time)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            group_type,
            SUM(duration) as total_duration
        FROM combat_encounters
        WHERE character_id = ?
          AND dps > 0
        GROUP BY group_type
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $row['group_type'] ?? 'solo';
        $payload['combat_breakdown'][$type] = round((float) $row['total_duration']);
    }
} catch (Throwable $e) {
    error_log("Combat breakdown error: " . $e->getMessage());
}

// ========================================================================
// BOSS ENCOUNTERS TABLE
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            target,
            instance,
            instance_difficulty,
            dps,
            duration,
            ts,
            total_damage,
            group_type,
            group_size,
            is_boss
        FROM combat_encounters
        WHERE character_id = ? 
          AND dps > 0
        ORDER BY ts DESC
        LIMIT 100
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['boss_encounters'][] = [
            'target' => $row['target'],
            'instance' => $row['instance'],
            'difficulty' => $row['instance_difficulty'],
            'dps' => round((float) ($row['dps'] ?? 0)),
            'duration' => round((float) $row['duration'], 1),
            'ts' => (int) $row['ts'],
            'date' => date('M j, Y', (int) $row['ts']),
            'total_damage' => (int) $row['total_damage'],
            'group_type' => $row['group_type'],
            'group_size' => (int) $row['group_size'],
            'is_boss' => (bool) $row['is_boss'],
        ];
    }
} catch (Throwable $e) {
    error_log("Boss encounters error: " . $e->getMessage());
}

// ========================================================================
// DPS DISTRIBUTION (Histogram data)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT dps
        FROM combat_encounters
        WHERE character_id = ? AND dps IS NOT NULL AND dps > 0
    ');
    $stmt->execute([$cid]);

    $dpsValues = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dpsValues[] = (float) $row['dps'];
    }

    if (!empty($dpsValues)) {
        sort($dpsValues);

        // Create histogram buckets
        $min = min($dpsValues);
        $max = max($dpsValues);
        $bucketSize = ($max - $min) / 10;

        $buckets = array_fill(0, 10, 0);

        foreach ($dpsValues as $dps) {
            $bucketIndex = min(9, floor(($dps - $min) / $bucketSize));
            $buckets[$bucketIndex]++;
        }

        for ($i = 0; $i < 10; $i++) {
            $rangeStart = round($min + ($i * $bucketSize));
            $rangeEnd = round($min + (($i + 1) * $bucketSize));

            $payload['dps_distribution'][] = [
                'range' => "{$rangeStart}-{$rangeEnd}",
                'count' => $buckets[$i],
                'rangeStart' => $rangeStart,
                'rangeEnd' => $rangeEnd,
            ];
        }

        // Calculate percentiles
        $count = count($dpsValues);
        $payload['dps_percentiles'] = [
            'p50' => round($dpsValues[floor($count * 0.5)]),
            'p75' => round($dpsValues[floor($count * 0.75)]),
            'p90' => round($dpsValues[floor($count * 0.9)]),
        ];
    }
} catch (Throwable $e) {
    error_log("DPS distribution error: " . $e->getMessage());
}

// ========================================================================
// BURST VS SUSTAINED DPS ANALYSIS
// ========================================================================

try {
    // Burst = short fights (<30s), Sustained = long fights (>2min)
    $stmt = $pdo->prepare('
        SELECT 
            AVG(CASE WHEN duration < 30 THEN dps ELSE NULL END) as burst_dps,
            AVG(CASE WHEN duration > 120 THEN dps ELSE NULL END) as sustained_dps,
            COUNT(CASE WHEN duration < 30 THEN 1 ELSE NULL END) as burst_count,
            COUNT(CASE WHEN duration > 120 THEN 1 ELSE NULL END) as sustained_count
        FROM combat_encounters
        WHERE character_id = ? AND dps IS NOT NULL AND dps > 0
    ');
    $stmt->execute([$cid]);
    $burstStats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($burstStats) {
        $payload['burst_analysis'] = [
            'burst_dps' => round((float) ($burstStats['burst_dps'] ?? 0)),
            'sustained_dps' => round((float) ($burstStats['sustained_dps'] ?? 0)),
            'burst_count' => (int) ($burstStats['burst_count'] ?? 0),
            'sustained_count' => (int) ($burstStats['sustained_count'] ?? 0),
        ];
    }
} catch (Throwable $e) {
    error_log("Burst analysis error: " . $e->getMessage());
}

// ========================================================================
// TARGET TYPE PERFORMANCE (Boss vs Adds)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            AVG(CASE WHEN is_boss = TRUE THEN dps ELSE NULL END) as boss_dps,
            AVG(CASE WHEN is_boss = FALSE THEN dps ELSE NULL END) as adds_dps,
            COUNT(CASE WHEN is_boss = TRUE THEN 1 ELSE NULL END) as boss_count,
            COUNT(CASE WHEN is_boss = FALSE THEN 1 ELSE NULL END) as adds_count,
            MAX(CASE WHEN is_boss = TRUE THEN dps ELSE NULL END) as max_boss_dps,
            MAX(CASE WHEN is_boss = FALSE THEN dps ELSE NULL END) as max_adds_dps
        FROM combat_encounters
        WHERE character_id = ? AND dps IS NOT NULL AND dps > 0
    ');
    $stmt->execute([$cid]);
    $targetStats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($targetStats) {
        $payload['target_analysis'] = [
            'boss_dps' => round((float) ($targetStats['boss_dps'] ?? 0)),
            'adds_dps' => round((float) ($targetStats['adds_dps'] ?? 0)),
            'boss_count' => (int) ($targetStats['boss_count'] ?? 0),
            'adds_count' => (int) ($targetStats['adds_count'] ?? 0),
            'max_boss_dps' => round((float) ($targetStats['max_boss_dps'] ?? 0)),
            'max_adds_dps' => round((float) ($targetStats['max_adds_dps'] ?? 0)),
        ];
    }
} catch (Throwable $e) {
    error_log("Target analysis error: " . $e->getMessage());
}

// ========================================================================
// DPS CONSISTENCY (Variance Analysis)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT dps
        FROM combat_encounters
        WHERE character_id = ? AND dps IS NOT NULL AND dps > 0
    ');
    $stmt->execute([$cid]);

    $dpsValues = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dpsValues[] = (float) $row['dps'];
    }

    if (!empty($dpsValues)) {
        $mean = array_sum($dpsValues) / count($dpsValues);
        $variance = 0;
        foreach ($dpsValues as $val) {
            $variance += pow($val - $mean, 2);
        }
        $variance = $variance / count($dpsValues);
        $stdDev = sqrt($variance);
        $coefficientOfVariation = $mean > 0 ? ($stdDev / $mean) * 100 : 0;

        $payload['consistency_metrics'] = [
            'mean_dps' => round($mean),
            'std_deviation' => round($stdDev),
            'coefficient_of_variation' => round($coefficientOfVariation, 1),
            'consistency_rating' => $coefficientOfVariation < 20 ? 'Excellent' :
                ($coefficientOfVariation < 35 ? 'Good' :
                    ($coefficientOfVariation < 50 ? 'Average' : 'Inconsistent')),
        ];
    }
} catch (Throwable $e) {
    error_log("Consistency metrics error: " . $e->getMessage());
}

// Emit payload
echo json_encode($payload);