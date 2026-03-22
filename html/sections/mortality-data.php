<?php
// sections/mortality-data.php - Fresh start based on actual database schema
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$character_id = $_GET['character_id'] ?? null;
if (!$character_id || !is_numeric($character_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid character ID']);
    exit;
}

$character_id = (int) $character_id;

try {
    // Verify character access
    $character = null;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT name FROM characters WHERE id = ? AND user_id = ?');
        $stmt->execute([$character_id, $_SESSION['user_id']]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$character) {
        $stmt = $pdo->prepare('SELECT name FROM characters WHERE id = ? AND visibility = "PUBLIC"');
        $stmt->execute([$character_id]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not accessible']);
        exit;
    }

    // ===== GET TOTAL DEATH COUNT =====
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM deaths WHERE character_id = ?');
    $stmt->execute([$character_id]);
    $totalDeaths = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ===== OVERVIEW STATISTICS =====
    $overview = [
        'total_deaths' => (int) $totalDeaths,
        'pve_deaths' => 0,
        'pvp_deaths' => 0,
        'environmental_deaths' => 0,
        'deaths_by_type' => []
    ];

    // Death type breakdown
    $stmt = $pdo->prepare('
        SELECT killer_type, COUNT(*) as count
        FROM deaths 
        WHERE character_id = ?
        GROUP BY killer_type
        ORDER BY count DESC
    ');
    $stmt->execute([$character_id]);
    $typeBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($typeBreakdown as $type) {
        $count = (int) $type['count'];
        $killerType = $type['killer_type'];

        $overview['deaths_by_type'][] = [
            'type' => $killerType,
            'count' => $count
        ];

        // Categorize deaths
        if ($killerType === 'player') {
            $overview['pvp_deaths'] = $count;
        } elseif ($killerType === 'environmental') {
            $overview['environmental_deaths'] = $count;
        } else {
            $overview['pve_deaths'] += $count;
        }
    }

    // Most dangerous zones
    $stmt = $pdo->prepare('
        SELECT zone, COUNT(*) as death_count
        FROM deaths 
        WHERE character_id = ? AND zone IS NOT NULL
        GROUP BY zone
        ORDER BY death_count DESC
        LIMIT 5
    ');
    $stmt->execute([$character_id]);
    $dangerousZones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Most lethal killers (excluding Unknown)
    $stmt = $pdo->prepare('
        SELECT killer_name, killer_type, COUNT(*) as kill_count
        FROM deaths 
        WHERE character_id = ? AND killer_name IS NOT NULL AND killer_name != "" AND LOWER(killer_name) != "unknown"
        GROUP BY killer_name, killer_type
        ORDER BY kill_count DESC
        LIMIT 10
    ');
    $stmt->execute([$character_id]);
    $lethalKillers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== DETAILED PVE DEATHS =====
    $stmt = $pdo->prepare('
        SELECT 
            killer_name,
            killer_type,
            killer_spell,
            killer_damage,
            zone,
            subzone,
            x,
            y,
            ts,
            level,
            durability_loss,
            rez_time,
            combat_duration,
            attacker_count,
            killer_confidence,
            killer_method
        FROM deaths
        WHERE character_id = ? AND killer_type != "player"
            AND NOT (killer_name IS NULL OR killer_name = "" OR LOWER(killer_name) = "unknown")
        ORDER BY ts DESC
        LIMIT 50
    ');
    $stmt->execute([$character_id]);
    $pveDeaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== DETAILED PVP DEATHS =====
    $stmt = $pdo->prepare('
        SELECT 
            id,
            killer_name,
            killer_type,
            killer_spell,
            killer_damage,
            zone,
            subzone,
            x,
            y,
            ts,
            level,
            durability_loss,
            rez_time,
            combat_duration,
            attacker_count,
            killer_confidence,
            killer_method
        FROM deaths
        WHERE character_id = ? AND killer_type = "player"
            AND NOT (killer_name IS NULL OR killer_name = "" OR LOWER(killer_name) = "unknown")
        ORDER BY ts DESC
        LIMIT 50
    ');
    $stmt->execute([$character_id]);
    $pvpDeaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== PVP ANALYSIS =====
    $pvpStats = [
        'dangerous_zones' => [],
        'frequent_killers' => [],
        'lethal_spells' => []
    ];

    if ($overview['pvp_deaths'] > 0) {
        // Most dangerous PVP zones
        $stmt = $pdo->prepare('
            SELECT zone, subzone, COUNT(*) as death_count
            FROM deaths 
            WHERE character_id = ? AND killer_type = "player"
            GROUP BY zone, subzone
            ORDER BY death_count DESC
            LIMIT 5
        ');
        $stmt->execute([$character_id]);
        $pvpStats['dangerous_zones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Most frequent PVP killers
        $stmt = $pdo->prepare('
            SELECT killer_name, COUNT(*) as kill_count
            FROM deaths 
            WHERE character_id = ? AND killer_type = "player" AND killer_name IS NOT NULL
                AND killer_name != "" AND LOWER(killer_name) != "unknown"
            GROUP BY killer_name
            ORDER BY kill_count DESC
            LIMIT 10
        ');
        $stmt->execute([$character_id]);
        $pvpStats['frequent_killers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Most lethal PVP spells
        $stmt = $pdo->prepare('
            SELECT killer_spell, COUNT(*) as kill_count
            FROM deaths 
            WHERE character_id = ? AND killer_type = "player" AND killer_spell IS NOT NULL
            GROUP BY killer_spell
            ORDER BY kill_count DESC
            LIMIT 10
        ');
        $stmt->execute([$character_id]);
        $pvpStats['lethal_spells'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ===== RECENT DEATH TIMELINE =====
    $stmt = $pdo->prepare('
        SELECT 
            DATE(FROM_UNIXTIME(ts)) as date,
            COUNT(*) as deaths
        FROM deaths
        WHERE character_id = ?
        GROUP BY date
        ORDER BY date DESC
        LIMIT 30
    ');
    $stmt->execute([$character_id]);
    $deathTimeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== FORMAT RESPONSE =====
    $response = [
        'overview' => array_merge($overview, [
            'dangerous_zones' => $dangerousZones,
            'lethal_killers' => $lethalKillers
        ]),
        'pve_deaths' => array_map(function ($death) {
            return [
                'killer' => $death['killer_name'] ?? 'Unknown',
                'killer_type' => $death['killer_type'],
                'spell' => $death['killer_spell'],
                'damage' => $death['killer_damage'] ? (int) $death['killer_damage'] : null,
                'zone' => $death['zone'] ?? 'Unknown Zone',
                'subzone' => $death['subzone'] ?? '',
                'x' => $death['x'] ? round((float) $death['x'], 3) : null,
                'y' => $death['y'] ? round((float) $death['y'], 3) : null,
                'timestamp' => (int) $death['ts'],
                'level' => (int) ($death['level'] ?? 1),
                'durability_loss' => $death['durability_loss'] ? round((float) $death['durability_loss'], 1) : 0,
                'rez_time' => $death['rez_time'] ? (int) $death['rez_time'] : null,
                'combat_duration' => $death['combat_duration'] ? round((float) $death['combat_duration'], 1) : null,
                'attacker_count' => $death['attacker_count'] ? (int) $death['attacker_count'] : 1,
                'confidence' => $death['killer_confidence'] ?? 'unknown',
                'method' => $death['killer_method'] ?? 'unknown'
            ];
        }, $pveDeaths),
        'pvp_deaths' => array_map(function ($death) {
            return [
                'id' => (int) $death['id'],
                'killer' => $death['killer_name'] ?? 'Unknown Player',
                'killer_type' => 'player',
                'spell' => $death['killer_spell'],
                'damage' => $death['killer_damage'] ? (int) $death['killer_damage'] : null,
                'zone' => $death['zone'] ?? 'Unknown Zone',
                'subzone' => $death['subzone'] ?? '',
                'x' => $death['x'] ? round((float) $death['x'], 3) : null,
                'y' => $death['y'] ? round((float) $death['y'], 3) : null,
                'timestamp' => (int) $death['ts'],
                'level' => (int) ($death['level'] ?? 1),
                'durability_loss' => $death['durability_loss'] ? round((float) $death['durability_loss'], 1) : 0,
                'rez_time' => $death['rez_time'] ? (int) $death['rez_time'] : null,
                'combat_duration' => $death['combat_duration'] ? round((float) $death['combat_duration'], 1) : null,
                'attacker_count' => $death['attacker_count'] ? (int) $death['attacker_count'] : 1,
                'confidence' => $death['killer_confidence'] ?? 'unknown',
                'method' => $death['killer_method'] ?? 'unknown'
            ];
        }, $pvpDeaths),
        'pvp_stats' => $pvpStats,
        'timeline' => $deathTimeline
    ];

    echo json_encode($response, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Mortality data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>