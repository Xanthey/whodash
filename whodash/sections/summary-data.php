<?php
require_once __DIR__ . '/../db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['character_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing character_id']);
    exit;
}

$cid = (int) $_GET['character_id'];

// Validate ownership
$own = $pdo->prepare('
    SELECT id, name, guild_name, class_file, class_local, race, race_file, sex, faction, realm 
    FROM characters 
    WHERE id = ? AND user_id = ?
');
$own->execute([$cid, $_SESSION['user_id'] ?? null]);
$character = $own->fetch(PDO::FETCH_ASSOC);
if (!$character) {
    http_response_code(403);
    echo json_encode(['error' => 'Character not found or not yours']);
    exit;
}

// Initialize payload
$payload = [
    'identity' => [
        'name' => $character['name'],
        'guild' => $character['guild_name'],
        'level' => null,
        'className' => $character['class_local'] ?? $character['class_file'],
        'race' => $character['race'],
        'raceFile' => $character['race_file'],
        'sex' => isset($character['sex']) ? (int) $character['sex'] : null,
        'faction' => $character['faction'],
        'realm' => $character['realm']
    ],
    'player' => [
        'guild' => $character['guild_name'],
        'class' => $character['class_local'] ?? $character['class_file']
    ],
    'timeseries' => [
        'money' => [],
        'rested' => [],
        'level' => [],
        'honor' => [],
        'base_stats' => [],
    ],
    'items' => ['history' => []],
    'zones' => ['history' => []],
    'reputation' => ['history' => []],
    'sessions' => [],
    'stats' => [
        'deaths' => 0,
        'kills' => 0
    ],
    'events' => [
        'death' => []
    ],
    'equipment' => []
];

// ========================================================================
// Timeseries Data
// ========================================================================
try {
    // Money (last 400 points, ascending)
    $stmt = $pdo->prepare('SELECT ts, value FROM series_money WHERE character_id = ? ORDER BY ts ASC LIMIT 400');
    $stmt->execute([$cid]);
    $payload['timeseries']['money'] = array_map(
        function ($r) {
            return ['ts' => (int) $r['ts'], 'value' => (int) $r['value']]; },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    // Rested XP
    $stmt = $pdo->prepare('SELECT ts, value FROM series_rested WHERE character_id = ? ORDER BY ts ASC LIMIT 400');
    $stmt->execute([$cid]);
    $payload['timeseries']['rested'] = array_map(
        function ($r) {
            return ['ts' => (int) $r['ts'], 'value' => (int) $r['value']]; },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    // Level progression
    $stmt = $pdo->prepare('SELECT ts, value FROM series_level WHERE character_id = ? ORDER BY ts ASC LIMIT 400');
    $stmt->execute([$cid]);
    $payload['timeseries']['level'] = array_map(
        function ($r) {
            return ['ts' => (int) $r['ts'], 'value' => (int) $r['value']]; },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    // Honor points
    $stmt = $pdo->prepare('SELECT ts, value FROM series_honor WHERE character_id = ? ORDER BY ts ASC LIMIT 400');
    $stmt->execute([$cid]);
    $payload['timeseries']['honor'] = array_map(
        function ($r) {
            return ['ts' => (int) $r['ts'], 'value' => (int) $r['value']]; },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

    // Base stats (strength, agility, etc.)
    $stmt = $pdo->prepare('
        SELECT ts, strength, agility, stamina, intellect, spirit, armor, defense,
               resist_arcane, resist_fire, resist_frost, resist_holy, resist_nature, resist_shadow
        FROM series_base_stats 
        WHERE character_id = ? 
        ORDER BY ts ASC 
        LIMIT 400
    ');
    $stmt->execute([$cid]);
    $payload['timeseries']['base_stats'] = array_map(
        function ($r) {
            return [
                'ts' => (int) $r['ts'],
                'strength' => (int) ($r['strength'] ?? 0),
                'agility' => (int) ($r['agility'] ?? 0),
                'stamina' => (int) ($r['stamina'] ?? 0),
                'intellect' => (int) ($r['intellect'] ?? 0),
                'spirit' => (int) ($r['spirit'] ?? 0),
                'armor' => (int) ($r['armor'] ?? 0),
                'defense' => (int) ($r['defense'] ?? 0),
                'resist_arcane' => (int) ($r['resist_arcane'] ?? 0),
                'resist_fire' => (int) ($r['resist_fire'] ?? 0),
                'resist_frost' => (int) ($r['resist_frost'] ?? 0),
                'resist_holy' => (int) ($r['resist_holy'] ?? 0),
                'resist_nature' => (int) ($r['resist_nature'] ?? 0),
                'resist_shadow' => (int) ($r['resist_shadow'] ?? 0),
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

} catch (Throwable $e) {
    error_log("Timeseries error: " . $e->getMessage());
}

// ========================================================================
// Zones History
// ========================================================================
try {
    $stmt = $pdo->prepare('
        SELECT ts, zone, subzone 
        FROM series_zones 
        WHERE character_id = ? 
        ORDER BY ts DESC 
        LIMIT 100
    ');
    $stmt->execute([$cid]);
    $payload['zones']['history'] = array_map(
        function ($r) {
            return [
                'ts' => (int) $r['ts'],
                'zone' => $r['zone'],
                'subzone' => $r['subzone']
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
} catch (Throwable $e) {
    error_log("Zones error: " . $e->getMessage());
}

// ========================================================================
// Items History
// ========================================================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            ts,
            action,
            name,
            item_string,
            link,
            COALESCE(count, 1) AS count,
            context_json AS context
        FROM item_events
        WHERE character_id = ?
        ORDER BY ts DESC
        LIMIT 100
    ");
    $stmt->execute([$cid]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payload['items']['history'] = array_map(function ($r) {
        $name = $r['name'];
        if (!$name && !empty($r['item_string'])) {
            if (preg_match('/\[(.*?)\]/', $r['item_string'], $m)) {
                $name = $m[1];
            }
        }
        if (!$name) {
            $name = 'Unknown Item';
        }

        $link = $r['link'] ?: ($r['item_string'] ?? null);

        return [
            'ts' => (int) $r['ts'],
            'action' => $r['action'],
            'itemName' => $name,
            'link' => $link,
            'count' => (int) $r['count'],
            'context' => $r['context'],
        ];
    }, $rows);

} catch (Throwable $e) {
    error_log("Items error: " . $e->getMessage());
}

// ========================================================================
// Reputation
// ========================================================================
try {
    $stmt = $pdo->prepare('
        SELECT ts AS timestamp, faction_name AS name, value, standing_id
        FROM series_reputation
        WHERE character_id = ?
        ORDER BY ts DESC
        LIMIT 500
    ');
    $stmt->execute([$cid]);
    $payload['reputation']['history'] = array_map(
        function ($r) {
            return [
                'timestamp' => (int) $r['timestamp'],
                'name' => $r['name'],
                'value' => (int) $r['value'],
                'standingID' => isset($r['standing_id']) ? (int) $r['standing_id'] : 4,
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
} catch (Throwable $e) {
    error_log("Reputation error: " . $e->getMessage());
}

// ========================================================================
// Sessions (for activity heatmap)
// ========================================================================
try {
    $stmt = $pdo->prepare('
        SELECT 
            DATE(FROM_UNIXTIME(ts)) as date,
            COUNT(*) as count,
            SUM(total_time) as duration
        FROM sessions
        WHERE character_id = ?
          AND ts > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY))
        GROUP BY DATE(FROM_UNIXTIME(ts))
        ORDER BY date DESC
    ');
    $stmt->execute([$cid]);
    $payload['sessions'] = array_map(
        function ($r) {
            return [
                'date' => $r['date'],
                'count' => (int) $r['count'],
                'duration' => (int) ($r['duration'] ?? 0)
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
} catch (Throwable $e) {
    // Sessions table might not exist yet
    error_log("Sessions error: " . $e->getMessage());
}

// ========================================================================
// Stats (Deaths, Kills)
// ========================================================================
try {
    // Deaths
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM deaths WHERE character_id = ?');
    $stmt->execute([$cid]);
    $payload['stats']['deaths'] = (int) $stmt->fetchColumn();

    // Kills (boss kills only - general kills not tracked)
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM boss_kills WHERE character_id = ?');
    $stmt->execute([$cid]);
    $payload['stats']['kills'] = (int) $stmt->fetchColumn();
} catch (Throwable $e) {
    error_log("Stats error: " . $e->getMessage());
}

// ========================================================================
// Death Events (for activity feed)
// ========================================================================
try {
    $stmt = $pdo->prepare('
        SELECT ts, zone, subzone, killer_name
        FROM deaths
        WHERE character_id = ?
        ORDER BY ts DESC
        LIMIT 50
    ');
    $stmt->execute([$cid]);
    $payload['events']['death'] = array_map(
        function ($r) {
            return [
                'ts' => (int) $r['ts'],
                'zone' => $r['zone'] ?? 'Unknown',
                'subzone' => $r['subzone'],
                'killer' => $r['killer_name']
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );
} catch (Throwable $e) {
    error_log("Death events error: " . $e->getMessage());
}

// ========================================================================
// Equipment (for gear display and ilvl calculation)
// ========================================================================
try {
    $stmt = $pdo->prepare('
        SELECT slot_name, item_id, name, link, icon, ilvl, count
        FROM equipment_snapshot
        WHERE character_id = ?
        ORDER BY slot_name
    ');
    $stmt->execute([$cid]);
    $equipment = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payload['equipment'] = array_map(
        function ($r) {
            return [
                'slot' => $r['slot_name'],
                'itemId' => (int) ($r['item_id'] ?? 0),
                'name' => $r['name'],
                'link' => $r['link'],
                'icon' => $r['icon'],
                'ilvl' => (int) ($r['ilvl'] ?? 0),
                'count' => (int) ($r['count'] ?? 1)
            ];
        },
        $equipment
    );

    // Calculate average item level
    $validItems = array_filter($equipment, function ($e) {
        return !empty($e['ilvl']) && $e['ilvl'] > 0;
    });

    if (count($validItems) > 0) {
        $totalIlvl = array_sum(array_map(fn($e) => (int) $e['ilvl'], $validItems));
        $payload['avgIlvl'] = round($totalIlvl / count($validItems));
    } else {
        $payload['avgIlvl'] = 0;
    }

} catch (Throwable $e) {
    error_log("Equipment error: " . $e->getMessage());
}

// ========================================================================
// Talents (for determining specialization)
// ========================================================================
try {
    // Get the most recent talent group for this character
    $stmt = $pdo->prepare('
        SELECT tg.id as group_id
        FROM talents_groups tg
        WHERE tg.character_id = ?
        ORDER BY tg.id DESC
        LIMIT 1
    ');
    $stmt->execute([$cid]);
    $talentGroup = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($talentGroup) {
        // Get tabs for this group, ordered by points spent
        $stmt = $pdo->prepare('
            SELECT name, points_spent
            FROM talents_tabs
            WHERE talents_group_id = ?
            ORDER BY points_spent DESC
            LIMIT 1
        ');
        $stmt->execute([$talentGroup['group_id']]);
        $primarySpec = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($primarySpec && $primarySpec['points_spent'] > 0) {
            $payload['identity']['spec'] = $primarySpec['name'];
        }
    }
} catch (Throwable $e) {
    error_log("Talents error: " . $e->getMessage());
}

// Emit payload
echo json_encode($payload);