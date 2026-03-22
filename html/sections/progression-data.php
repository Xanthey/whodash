<?php
// sections/progression-data.php
// Returns boss kill stats, raid progression, lockouts, and difficulty breakdown
// for the Progression tab in WhoDASH.
declare(strict_types=1);

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

try {
    require_once __DIR__ . '/../db.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=60');

    // ── Auth ──────────────────────────────────────────────────────────────────
    $character_id = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;
    if (!$character_id) {
        http_response_code(400);
        echo json_encode(['error' => 'No character_id provided']);
        exit;
    }

    $character = null;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT id, name, realm, faction, class_file FROM characters WHERE id = ? AND user_id = ?');
        $stmt->execute([$character_id, $_SESSION['user_id']]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$character) {
        $stmt = $pdo->prepare('SELECT id, name, realm, faction, class_file FROM characters WHERE id = ? AND visibility = "PUBLIC"');
        $stmt->execute([$character_id]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not accessible']);
        exit;
    }

    $payload = [
        'unique_bosses' => 0,
        'total_boss_kills' => 0,
        'most_killed_boss' => null,
        'boss_combat_stats' => null,
        'best_boss_performance' => null,
        'raid_progression' => [],
        'difficulty_breakdown' => [],
        'all_boss_kills' => [],
        'active_lockouts' => [],
        'lockout_history' => [],
    ];

    // ============================================================================
    // OVERVIEW STATS
    // ============================================================================

    // Total unique bosses killed
    try {
        $stmt = $pdo->prepare('SELECT COUNT(DISTINCT boss_name) FROM boss_kills WHERE character_id = ?');
        $stmt->execute([$character_id]);
        $payload['unique_bosses'] = (int) $stmt->fetchColumn();
    } catch (Throwable $e) { /* table may not exist yet */
    }

    // Total kill count
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM boss_kills WHERE character_id = ?');
        $stmt->execute([$character_id]);
        $payload['total_boss_kills'] = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
    }

    // Most-killed boss
    try {
        $stmt = $pdo->prepare('
            SELECT boss_name, COUNT(*) as kill_count
            FROM boss_kills
            WHERE character_id = ?
            GROUP BY boss_name
            ORDER BY kill_count DESC
            LIMIT 1
        ');
        $stmt->execute([$character_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $payload['most_killed_boss'] = [
                'boss_name' => $row['boss_name'],
                'kill_count' => (int) $row['kill_count'],
            ];
        }
    } catch (Throwable $e) {
    }

    // ============================================================================
    // RAID PROGRESSION
    // Aggregate bosses killed per instance/difficulty and compare against known
    // boss counts so we can show a progress bar.
    // ============================================================================

    // Known WotLK raid boss counts — used to calculate progress_pct.
    // Covers the major instances available on 3.3.5a private servers.
    $raidBossCounts = [
        'Naxxramas' => ['10 Player' => 15, '25 Player' => 15],
        'The Eye of Eternity' => ['10 Player' => 1, '25 Player' => 1],
        'The Obsidian Sanctum' => ['10 Player' => 4, '25 Player' => 4],
        'Vault of Archavon' => ['10 Player' => 4, '25 Player' => 4],
        'Ulduar' => ['10 Player' => 14, '25 Player' => 14],
        'Trial of the Crusader' => [
            '10 Player' => 5,
            '25 Player' => 5,
            '10 Player (Heroic)' => 5,
            '25 Player (Heroic)' => 5
        ],
        'Onyxia\'s Lair' => ['10 Player' => 1, '25 Player' => 1],
        'Icecrown Citadel' => [
            '10 Player' => 12,
            '25 Player' => 12,
            '10 Player (Heroic)' => 12,
            '25 Player (Heroic)' => 12
        ],
        'The Ruby Sanctum' => [
            '10 Player' => 1,
            '25 Player' => 1,
            '10 Player (Heroic)' => 1,
            '25 Player (Heroic)' => 1
        ],
    ];

    try {
        $stmt = $pdo->prepare('
            SELECT
                instance,
                difficulty_name,
                COUNT(DISTINCT boss_name) as bosses_killed,
                COUNT(*) as total_kills,
                MAX(ts) as last_kill
            FROM boss_kills
            WHERE character_id = ?
              AND instance IS NOT NULL
              AND instance != ""
            GROUP BY instance, difficulty_name
            ORDER BY instance, difficulty_name
        ');
        $stmt->execute([$character_id]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $inst = $row['instance'];
            $diff = $row['difficulty_name'] ?: 'Normal';
            $killed = (int) $row['bosses_killed'];
            $totalBosses = $raidBossCounts[$inst][$diff] ?? $killed; // fallback: use what we have
            $pct = $totalBosses > 0 ? min(100, round(($killed / $totalBosses) * 100)) : 0;

            $payload['raid_progression'][] = [
                'instance' => $inst,
                'difficulty_name' => $diff,
                'bosses_killed' => $killed,
                'total_bosses' => $totalBosses,
                'progress_pct' => $pct,
                'total_kills' => (int) $row['total_kills'],
                'last_kill' => (int) $row['last_kill'],
            ];
        }
    } catch (Throwable $e) {
        error_log('Progression - raid_progression error: ' . $e->getMessage());
    }

    // ============================================================================
    // DIFFICULTY BREAKDOWN
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT
                COALESCE(NULLIF(difficulty_name, ""), "Normal") as difficulty_name,
                COUNT(*) as kill_count
            FROM boss_kills
            WHERE character_id = ?
            GROUP BY difficulty_name
            ORDER BY kill_count DESC
        ');
        $stmt->execute([$character_id]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payload['difficulty_breakdown'][] = [
                'difficulty_name' => $row['difficulty_name'],
                'kill_count' => (int) $row['kill_count'],
            ];
        }
    } catch (Throwable $e) {
    }

    // ============================================================================
    // ALL BOSS KILLS (for the Boss Kills tab table)
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT
                bk.boss_name,
                bk.instance,
                bk.difficulty_name,
                bk.group_size,
                bk.group_type,
                bk.ts,
                -- combat log data joined from boss_kill_stats if available
                bks.dps,
                bks.hps,
                bks.dtps,
                bks.duration,
                bks.total_damage,
                bks.total_healing,
                bks.total_damage_taken,
                bks.overheal_pct,
                bks.target_level
            FROM boss_kills bk
            LEFT JOIN boss_kill_stats bks ON bks.boss_kill_id = bk.id
            WHERE bk.character_id = ?
            ORDER BY bk.ts DESC
            LIMIT 500
        ');
        $stmt->execute([$character_id]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $payload['all_boss_kills'][] = [
                'boss_name' => $row['boss_name'],
                'instance' => $row['instance'],
                'difficulty_name' => $row['difficulty_name'] ?: 'Normal',
                'group_size' => (int) ($row['group_size'] ?? 0),
                'group_type' => $row['group_type'] ?? 'raid',
                'ts' => (int) $row['ts'],
                'dps' => $row['dps'] ? (float) $row['dps'] : null,
                'hps' => $row['hps'] ? (float) $row['hps'] : null,
                'dtps' => $row['dtps'] ? (float) $row['dtps'] : null,
                'duration' => $row['duration'] ? (int) $row['duration'] : null,
                'total_damage' => $row['total_damage'] ? (int) $row['total_damage'] : null,
                'total_healing' => $row['total_healing'] ? (int) $row['total_healing'] : null,
                'total_damage_taken' => $row['total_damage_taken'] ? (int) $row['total_damage_taken'] : null,
                'overheal_pct' => $row['overheal_pct'] ? (float) $row['overheal_pct'] : null,
                'target_level' => $row['target_level'] ? (int) $row['target_level'] : null,
            ];
        }
    } catch (Throwable $e) {
        // boss_kill_stats may not exist — fall back to kills without combat data
        try {
            $stmt = $pdo->prepare('
                SELECT boss_name, instance, difficulty_name, group_size, group_type, ts
                FROM boss_kills
                WHERE character_id = ?
                ORDER BY ts DESC
                LIMIT 500
            ');
            $stmt->execute([$character_id]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $payload['all_boss_kills'][] = [
                    'boss_name' => $row['boss_name'],
                    'instance' => $row['instance'],
                    'difficulty_name' => $row['difficulty_name'] ?: 'Normal',
                    'group_size' => (int) ($row['group_size'] ?? 0),
                    'group_type' => $row['group_type'] ?? 'raid',
                    'ts' => (int) $row['ts'],
                ];
            }
        } catch (Throwable $e2) {
            error_log('Progression - all_boss_kills fallback error: ' . $e2->getMessage());
        }
    }

    // ============================================================================
    // BOSS COMBAT STATS AGGREGATE (for overview cards)
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT
                COUNT(*) as bosses_with_combat_data,
                AVG(bks.dps)      as avg_boss_dps,
                MAX(bks.dps)      as max_boss_dps,
                AVG(bks.duration) as avg_boss_fight_duration
            FROM boss_kill_stats bks
            JOIN boss_kills bk ON bk.id = bks.boss_kill_id
            WHERE bk.character_id = ?
              AND bks.dps IS NOT NULL
              AND bks.dps > 0
        ');
        $stmt->execute([$character_id]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($stats && (int) $stats['bosses_with_combat_data'] > 0) {
            $payload['boss_combat_stats'] = [
                'bosses_with_combat_data' => (int) $stats['bosses_with_combat_data'],
                'avg_boss_dps' => (int) round((float) $stats['avg_boss_dps']),
                'max_boss_dps' => (int) round((float) $stats['max_boss_dps']),
                'avg_boss_fight_duration' => (int) round((float) $stats['avg_boss_fight_duration']),
            ];

            // Best single-boss DPS performance
            $stmt2 = $pdo->prepare('
                SELECT bk.boss_name, bks.dps
                FROM boss_kill_stats bks
                JOIN boss_kills bk ON bk.id = bks.boss_kill_id
                WHERE bk.character_id = ?
                  AND bks.dps IS NOT NULL
                ORDER BY bks.dps DESC
                LIMIT 1
            ');
            $stmt2->execute([$character_id]);
            $best = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($best) {
                $payload['best_boss_performance'] = [
                    'boss_name' => $best['boss_name'],
                    'dps' => (int) round((float) $best['dps']),
                ];
            }
        }
    } catch (Throwable $e) {
        // boss_kill_stats table may not exist — combat stats stay null
    }

    // ============================================================================
    // ACTIVE LOCKOUTS (from instance_lockouts table)
    // ============================================================================
    try {
        $stmt = $pdo->prepare('
            SELECT
                il.instance_name,
                il.difficulty_name,
                il.bosses_killed,
                il.reset_time
            FROM instance_lockouts il
            WHERE il.character_id = ?
              AND il.reset_time > UNIX_TIMESTAMP(NOW())
            ORDER BY il.reset_time ASC
        ');
        $stmt->execute([$character_id]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $inst = $row['instance_name'];
            $diff = $row['difficulty_name'] ?: 'Normal';
            $killed = (int) $row['bosses_killed'];
            $totalBosses = $raidBossCounts[$inst][$diff] ?? max($killed, 1);
            $daysLeft = max(0, (int) ceil(((int) $row['reset_time'] - time()) / 86400));

            $payload['active_lockouts'][] = [
                'instance_name' => $inst,
                'difficulty_name' => $diff,
                'bosses_killed' => $killed,
                'total_bosses' => $totalBosses,
                'reset_time' => (int) $row['reset_time'],
                'days_until_reset' => $daysLeft,
                'extended' => false,
            ];
        }
    } catch (Throwable $e) {
        // instance_lockouts may not exist yet
    }

    echo json_encode($payload);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
}