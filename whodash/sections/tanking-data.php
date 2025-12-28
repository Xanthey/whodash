<?php
// sections/tanking-data.php
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
        'avg_dtps' => 0,
        'highest_dtps' => 0,
        'highest_dtps_target' => null,
        'highest_dtps_date' => null,
        'total_damage_taken' => 0,
        'total_encounters' => 0,
        'total_deaths' => 0,
        'survival_rate' => 0,
        'avg_encounter_duration' => 0,
        'tanking_uptime_seconds' => 0,
    ],
    'dtps_timeseries' => [],
    'performance_by_instance' => [],
    'tanking_breakdown' => [
        'solo' => 0,
        'party' => 0,
        'raid' => 0,
    ],
    'tanking_encounters' => [],
    'dtps_distribution' => [],
    'death_analysis' => [
        'by_zone' => [],
        'by_killer_type' => [],
        'recent_deaths' => [],
        'death_hotspots' => [],
    ],
    'survivability_metrics' => [
        'longest_survived' => [],
        'most_dangerous' => [],
    ],
];

// ========================================================================
// OVERVIEW STATS
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_encounters,
            AVG(dtps) as avg_dtps,
            MAX(dtps) as highest_dtps,
            SUM(total_damage_taken) as total_damage_taken,
            AVG(duration) as avg_duration,
            SUM(duration) as tanking_uptime_seconds
        FROM combat_encounters
        WHERE character_id = ?
          AND dtps > 0
    ');
    $stmt->execute([$cid]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        $payload['overview']['avg_dtps'] = round((float) ($stats['avg_dtps'] ?? 0));
        $payload['overview']['highest_dtps'] = round((float) ($stats['highest_dtps'] ?? 0));
        $payload['overview']['total_damage_taken'] = (int) ($stats['total_damage_taken'] ?? 0);
        $payload['overview']['total_encounters'] = (int) ($stats['total_encounters'] ?? 0);
        $payload['overview']['avg_encounter_duration'] = round((float) ($stats['avg_duration'] ?? 0), 1);
        $payload['overview']['tanking_uptime_seconds'] = round((float) ($stats['tanking_uptime_seconds'] ?? 0));
    }

    // Get highest DTPS encounter details
    $highStmt = $pdo->prepare('
        SELECT target, ts
        FROM combat_encounters
        WHERE character_id = ? AND dtps IS NOT NULL AND dtps > 0
        ORDER BY dtps DESC
        LIMIT 1
    ');
    $highStmt->execute([$cid]);
    $highestDtps = $highStmt->fetch(PDO::FETCH_ASSOC);

    if ($highestDtps) {
        $payload['overview']['highest_dtps_target'] = $highestDtps['target'];
        $payload['overview']['highest_dtps_date'] = date('M j, Y', (int) $highestDtps['ts']);
    }

    // Get death count
    $deathStmt = $pdo->prepare('
        SELECT COUNT(*) as death_count
        FROM deaths
        WHERE character_id = ?
    ');
    $deathStmt->execute([$cid]);
    $deathStats = $deathStmt->fetch(PDO::FETCH_ASSOC);

    $totalDeaths = (int) ($deathStats['death_count'] ?? 0);
    $totalEncounters = (int) ($stats['total_encounters'] ?? 0);

    $payload['overview']['total_deaths'] = $totalDeaths;
    $payload['overview']['survival_rate'] = $totalEncounters > 0
        ? round((($totalEncounters - $totalDeaths) / $totalEncounters) * 100, 1)
        : 100;

} catch (Throwable $e) {
    error_log("Tanking overview error: " . $e->getMessage());
}

// ========================================================================
// DTPS OVER TIME (Last 30 days)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT ts, dtps, target, total_damage_taken
        FROM combat_encounters
        WHERE character_id = ?
          AND ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
          AND dtps IS NOT NULL
          AND dtps > 0
        ORDER BY ts ASC
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['dtps_timeseries'][] = [
            'ts' => (int) $row['ts'],
            'dtps' => round((float) $row['dtps']),
            'target' => $row['target'],
            'total_damage_taken' => (int) $row['total_damage_taken'],
        ];
    }
} catch (Throwable $e) {
    error_log("DTPS timeseries error: " . $e->getMessage());
}

// ========================================================================
// PERFORMANCE BY INSTANCE
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            instance,
            AVG(dtps) as avg_dtps,
            COUNT(*) as encounter_count,
            AVG(duration) as avg_duration,
            SUM(total_damage_taken) as total_damage
        FROM combat_encounters
        WHERE character_id = ? 
          AND instance IS NOT NULL
          AND dtps IS NOT NULL
          AND dtps > 0
        GROUP BY instance
        ORDER BY avg_dtps DESC
        LIMIT 15
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['performance_by_instance'][] = [
            'instance' => $row['instance'],
            'avg_dtps' => round((float) $row['avg_dtps']),
            'encounter_count' => (int) $row['encounter_count'],
            'avg_duration' => round((float) $row['avg_duration'], 1),
            'total_damage' => (int) $row['total_damage'],
        ];
    }
} catch (Throwable $e) {
    error_log("Instance performance error: " . $e->getMessage());
}

// ========================================================================
// TANKING BREAKDOWN (Solo vs Party vs Raid time)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            group_type,
            SUM(duration) as total_duration
        FROM combat_encounters
        WHERE character_id = ?
          AND dtps > 0
        GROUP BY group_type
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = $row['group_type'] ?? 'solo';
        $payload['tanking_breakdown'][$type] = round((float) $row['total_duration']);
    }
} catch (Throwable $e) {
    error_log("Tanking breakdown error: " . $e->getMessage());
}

// ========================================================================
// TANKING ENCOUNTERS TABLE
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT 
            ce.target,
            ce.instance,
            ce.instance_difficulty,
            ce.dtps,
            ce.duration,
            ce.ts,
            ce.total_damage_taken,
            ce.group_type,
            ce.group_size,
            ce.is_boss,
            d.id as death_id
        FROM combat_encounters ce
        LEFT JOIN deaths d ON d.character_id = ce.character_id 
            AND ABS(d.ts - ce.ts) < 10
        WHERE ce.character_id = ? 
          AND ce.dtps > 0
        ORDER BY ce.ts DESC
        LIMIT 100
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['tanking_encounters'][] = [
            'target' => $row['target'],
            'instance' => $row['instance'],
            'difficulty' => $row['instance_difficulty'],
            'dtps' => round((float) ($row['dtps'] ?? 0)),
            'duration' => round((float) $row['duration'], 1),
            'ts' => (int) $row['ts'],
            'date' => date('M j, Y', (int) $row['ts']),
            'total_damage_taken' => (int) $row['total_damage_taken'],
            'group_type' => $row['group_type'],
            'group_size' => (int) $row['group_size'],
            'is_boss' => (bool) $row['is_boss'],
            'died' => !is_null($row['death_id']),
        ];
    }
} catch (Throwable $e) {
    error_log("Tanking encounters error: " . $e->getMessage());
}

// ========================================================================
// DTPS DISTRIBUTION (Histogram data)
// ========================================================================

try {
    $stmt = $pdo->prepare('
        SELECT dtps
        FROM combat_encounters
        WHERE character_id = ? AND dtps IS NOT NULL AND dtps > 0
    ');
    $stmt->execute([$cid]);

    $dtpsValues = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dtpsValues[] = (float) $row['dtps'];
    }

    if (!empty($dtpsValues)) {
        sort($dtpsValues);

        // Create histogram buckets
        $min = min($dtpsValues);
        $max = max($dtpsValues);
        $bucketSize = ($max - $min) / 10;

        $buckets = array_fill(0, 10, 0);

        foreach ($dtpsValues as $dtps) {
            $bucketIndex = min(9, floor(($dtps - $min) / $bucketSize));
            $buckets[$bucketIndex]++;
        }

        for ($i = 0; $i < 10; $i++) {
            $rangeStart = round($min + ($i * $bucketSize));
            $rangeEnd = round($min + (($i + 1) * $bucketSize));

            $payload['dtps_distribution'][] = [
                'range' => "{$rangeStart}-{$rangeEnd}",
                'count' => $buckets[$i],
                'rangeStart' => $rangeStart,
                'rangeEnd' => $rangeEnd,
            ];
        }

        // Calculate percentiles
        $count = count($dtpsValues);
        $payload['dtps_percentiles'] = [
            'p50' => round($dtpsValues[floor($count * 0.5)]),
            'p75' => round($dtpsValues[floor($count * 0.75)]),
            'p90' => round($dtpsValues[floor($count * 0.9)]),
        ];
    }
} catch (Throwable $e) {
    error_log("DTPS distribution error: " . $e->getMessage());
}

// ========================================================================
// DEATH ANALYSIS
// ========================================================================

try {
    // Deaths by zone
    $stmt = $pdo->prepare('
        SELECT 
            zone,
            COUNT(*) as death_count
        FROM deaths
        WHERE character_id = ?
        GROUP BY zone
        ORDER BY death_count DESC
        LIMIT 10
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['death_analysis']['by_zone'][] = [
            'zone' => $row['zone'],
            'death_count' => (int) $row['death_count'],
        ];
    }

    // Deaths by killer type
    $stmt = $pdo->prepare('
        SELECT 
            killer_type,
            COUNT(*) as death_count
        FROM deaths
        WHERE character_id = ?
        GROUP BY killer_type
        ORDER BY death_count DESC
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['death_analysis']['by_killer_type'][] = [
            'killer_type' => $row['killer_type'],
            'death_count' => (int) $row['death_count'],
        ];
    }

    // Recent deaths
    $stmt = $pdo->prepare('
        SELECT 
            ts,
            zone,
            subzone,
            killer_name,
            killer_type,
            durability_loss,
            combat_duration
        FROM deaths
        WHERE character_id = ?
        ORDER BY ts DESC
        LIMIT 20
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['death_analysis']['recent_deaths'][] = [
            'ts' => (int) $row['ts'],
            'date' => date('M j, Y', (int) $row['ts']),
            'zone' => $row['zone'],
            'subzone' => $row['subzone'],
            'killer_name' => $row['killer_name'],
            'killer_type' => $row['killer_type'],
            'durability_loss' => round((float) ($row['durability_loss'] ?? 0), 1),
            'combat_duration' => round((float) ($row['combat_duration'] ?? 0), 1),
        ];
    }

    // Death hotspots (zone + subzone combination)
    $stmt = $pdo->prepare('
        SELECT 
            zone,
            subzone,
            COUNT(*) as death_count,
            MAX(ts) as last_death
        FROM deaths
        WHERE character_id = ?
        GROUP BY zone, subzone
        HAVING death_count >= 2
        ORDER BY death_count DESC, last_death DESC
        LIMIT 10
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['death_analysis']['death_hotspots'][] = [
            'zone' => $row['zone'],
            'subzone' => $row['subzone'],
            'death_count' => (int) $row['death_count'],
            'last_death' => date('M j', (int) $row['last_death']),
        ];
    }

} catch (Throwable $e) {
    error_log("Death analysis error: " . $e->getMessage());
}

// ========================================================================
// SURVIVABILITY METRICS
// ========================================================================

try {
    // Longest survived encounters (high DTPS, long duration, didn't die)
    $stmt = $pdo->prepare('
        SELECT 
            ce.target,
            ce.instance,
            ce.dtps,
            ce.duration,
            ce.total_damage_taken,
            ce.ts
        FROM combat_encounters ce
        LEFT JOIN deaths d ON d.character_id = ce.character_id 
            AND ABS(d.ts - ce.ts) < 10
        WHERE ce.character_id = ?
          AND ce.dtps > 0
          AND ce.duration > 60
          AND d.id IS NULL
        ORDER BY (ce.dtps * ce.duration) DESC
        LIMIT 5
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['survivability_metrics']['longest_survived'][] = [
            'target' => $row['target'],
            'instance' => $row['instance'],
            'dtps' => round((float) $row['dtps']),
            'duration' => round((float) $row['duration'], 1),
            'total_damage_taken' => (int) $row['total_damage_taken'],
            'date' => date('M j', (int) $row['ts']),
        ];
    }

    // Most dangerous encounters (died quickly, high DTPS)
    $stmt = $pdo->prepare('
        SELECT 
            d.killer_name as target,
            d.zone,
            d.combat_duration as duration,
            d.ts
        FROM deaths d
        WHERE d.character_id = ?
          AND d.combat_duration IS NOT NULL
          AND d.combat_duration < 60
        ORDER BY d.combat_duration ASC
        LIMIT 5
    ');
    $stmt->execute([$cid]);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $payload['survivability_metrics']['most_dangerous'][] = [
            'target' => $row['target'],
            'zone' => $row['zone'],
            'duration' => round((float) $row['duration'], 1),
            'date' => date('M j', (int) $row['ts']),
        ];
    }

} catch (Throwable $e) {
    error_log("Survivability metrics error: " . $e->getMessage());
}

// Emit payload
echo json_encode($payload);