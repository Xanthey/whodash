<?php
// sections/mortality-data.php
// Provides comprehensive death analysis data
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Enable error logging
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$character_id = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

if (!$character_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No character_id provided']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare('SELECT * FROM characters WHERE id = ? AND user_id = ?');
    $stmt->execute([$character_id, $_SESSION['user_id']]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found']);
        exit;
    }

    // Initialize response
    $data = [
        'overview' => [
            'total_deaths' => 0,
            'deaths_per_hour' => 0,
            'arch_nemesis' => null,
            'most_dangerous_zone' => null,
            'avg_rez_time' => 0,
            'total_repair_cost' => 0,
            'longest_alive_streak' => 0,
            'worst_death_streak' => 0
        ],
        'trends' => [
            'deaths_over_time' => [],
            'deaths_by_level' => [],
            'deaths_by_difficulty' => []
        ],
        'death_log' => [],
        'boss_deaths' => [
            'by_boss' => [],
            'hardest_boss' => null
        ]
    ];

    // ===== OVERVIEW STATISTICS =====

    // Total deaths
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM deaths WHERE character_id = ?');
        $stmt->execute([$character_id]);
        $total = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['overview']['total_deaths'] = (int) ($total['total'] ?? 0);
    } catch (Exception $e) {
        error_log("Error getting total deaths: " . $e->getMessage());
    }

    // Deaths per hour
    try {
        $stmt = $pdo->prepare('SELECT SUM(total_time) as total_time FROM sessions WHERE character_id = ?');
        $stmt->execute([$character_id]);
        $playtime = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalHours = ($playtime['total_time'] ?? 0) ? $playtime['total_time'] / 3600 : 0;

        if ($totalHours > 0 && $data['overview']['total_deaths'] > 0) {
            $data['overview']['deaths_per_hour'] = round($data['overview']['total_deaths'] / $totalHours, 2);
        }
    } catch (Exception $e) {
        error_log("Error calculating deaths per hour: " . $e->getMessage());
    }

    // Arch-nemesis
    try {
        $stmt = $pdo->prepare('
            SELECT 
                killer_name,
                COUNT(*) as death_count,
                AVG(COALESCE(durability_loss, 0)) as avg_durability_loss,
                SUM(COALESCE(durability_loss, 0)) as total_durability_loss,
                AVG(COALESCE(combat_duration, 0)) as avg_combat_duration
            FROM deaths
            WHERE character_id = ? AND killer_name IS NOT NULL
            GROUP BY killer_name
            ORDER BY death_count DESC
            LIMIT 1
        ');
        $stmt->execute([$character_id]);
        $archNemesis = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($archNemesis && $archNemesis['killer_name']) {
            $data['overview']['arch_nemesis'] = [
                'name' => $archNemesis['killer_name'],
                'kills' => (int) $archNemesis['death_count'],
                'avg_durability_loss' => round((float) ($archNemesis['avg_durability_loss'] ?? 0), 2),
                'total_durability_loss' => round((float) ($archNemesis['total_durability_loss'] ?? 0), 2),
                'avg_combat_duration' => round((float) ($archNemesis['avg_combat_duration'] ?? 0), 2)
            ];
        }
    } catch (Exception $e) {
        error_log("Error getting arch nemesis: " . $e->getMessage());
    }

    // Most dangerous zone
    try {
        $stmt = $pdo->prepare('
            SELECT 
                zone,
                COUNT(*) as death_count,
                AVG(COALESCE(durability_loss, 0)) as avg_durability_loss
            FROM deaths
            WHERE character_id = ? AND zone IS NOT NULL
            GROUP BY zone
            ORDER BY death_count DESC
            LIMIT 1
        ');
        $stmt->execute([$character_id]);
        $dangerZone = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dangerZone && $dangerZone['zone']) {
            $data['overview']['most_dangerous_zone'] = [
                'name' => $dangerZone['zone'],
                'deaths' => (int) $dangerZone['death_count'],
                'avg_durability_loss' => round((float) ($dangerZone['avg_durability_loss'] ?? 0), 2)
            ];
        }
    } catch (Exception $e) {
        error_log("Error getting danger zone: " . $e->getMessage());
    }

    // Average resurrection time
    try {
        $stmt = $pdo->prepare('
            SELECT AVG(rez_time) as avg_rez_time 
            FROM deaths 
            WHERE character_id = ? AND rez_time IS NOT NULL AND rez_time > 0
        ');
        $stmt->execute([$character_id]);
        $rezTime = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['overview']['avg_rez_time'] = round((float) ($rezTime['avg_rez_time'] ?? 0), 0);
    } catch (Exception $e) {
        error_log("Error getting avg rez time: " . $e->getMessage());
    }

    // Total repair cost
    try {
        $stmt = $pdo->prepare('
            SELECT SUM(COALESCE(durability_loss, 0)) as total_repair 
            FROM deaths 
            WHERE character_id = ?
        ');
        $stmt->execute([$character_id]);
        $repair = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['overview']['total_repair_cost'] = round((float) ($repair['total_repair'] ?? 0), 2);
    } catch (Exception $e) {
        error_log("Error getting repair cost: " . $e->getMessage());
    }

    // ===== DEATH TRENDS =====

    // Deaths over time
    try {
        $stmt = $pdo->prepare('
            SELECT 
                FROM_UNIXTIME(ts, "%Y-%m-%d") as date,
                COUNT(*) as deaths
            FROM deaths
            WHERE character_id = ?
            GROUP BY date
            ORDER BY date ASC
        ');
        $stmt->execute([$character_id]);
        $deathsByDate = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['trends']['deaths_over_time'] = array_map(function ($row) {
            return [
                'date' => $row['date'],
                'count' => (int) $row['deaths']
            ];
        }, $deathsByDate);
    } catch (Exception $e) {
        error_log("Error getting deaths over time: " . $e->getMessage());
    }

    // Deaths by level bracket
    try {
        $stmt = $pdo->prepare('
            SELECT 
                FLOOR(COALESCE(level, 1) / 10) * 10 as level_bracket,
                COUNT(*) as deaths
            FROM deaths
            WHERE character_id = ?
            GROUP BY level_bracket
            ORDER BY level_bracket ASC
        ');
        $stmt->execute([$character_id]);
        $deathsByLevel = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['trends']['deaths_by_level'] = array_map(function ($row) {
            $bracket = (int) $row['level_bracket'];
            return [
                'bracket' => $bracket . '-' . ($bracket + 9),
                'count' => (int) $row['deaths']
            ];
        }, $deathsByLevel);
    } catch (Exception $e) {
        error_log("Error getting deaths by level: " . $e->getMessage());
    }

    // Deaths by instance difficulty
    try {
        $stmt = $pdo->prepare('
            SELECT 
                COALESCE(instance_difficulty, "Normal/World") as difficulty,
                COUNT(*) as deaths
            FROM deaths
            WHERE character_id = ?
            GROUP BY difficulty
            ORDER BY deaths DESC
        ');
        $stmt->execute([$character_id]);
        $deathsByDifficulty = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['trends']['deaths_by_difficulty'] = array_map(function ($row) {
            return [
                'difficulty' => $row['difficulty'],
                'count' => (int) $row['deaths']
            ];
        }, $deathsByDifficulty);
    } catch (Exception $e) {
        error_log("Error getting deaths by difficulty: " . $e->getMessage());
    }

    // ===== DEATH LOG =====
    try {
        $stmt = $pdo->prepare('
            SELECT 
                killer_name,
                zone,
                instance_name,
                COALESCE(durability_loss, 0) as durability_loss,
                rez_type,
                ts,
                COALESCE(level, 1) as level,
                group_type,
                COALESCE(combat_duration, 0) as combat_duration,
                subzone,
                x,
                y
            FROM deaths
            WHERE character_id = ?
            ORDER BY ts DESC
            LIMIT 50
        ');
        $stmt->execute([$character_id]);
        $deathLog = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['death_log'] = array_map(function ($row) {
            return [
                'killer' => $row['killer_name'],
                'zone' => $row['zone'],
                'instance' => $row['instance_name'],
                'durability_loss' => round((float) $row['durability_loss'], 2),
                'rez_type' => $row['rez_type'],
                'timestamp' => (int) $row['ts'],
                'level' => (int) $row['level'],
                'group_type' => $row['group_type'],
                'combat_duration' => round((float) $row['combat_duration'], 1),
                'subzone' => $row['subzone'],
                'x' => $row['x'],
                'y' => $row['y']
            ];
        }, $deathLog);
    } catch (Exception $e) {
        error_log("Error getting death log: " . $e->getMessage());
    }

    // ===== BOSS DEATHS =====
    try {
        $stmt = $pdo->prepare('
            SELECT 
                d.killer_name as boss,
                COUNT(*) as death_count,
                AVG(COALESCE(d.durability_loss, 0)) as avg_repair_cost,
                MIN(FROM_UNIXTIME(d.ts)) as first_death,
                (
                    SELECT MIN(FROM_UNIXTIME(bk.ts))
                    FROM boss_kills bk
                    WHERE bk.character_id = d.character_id
                    AND bk.boss_name = d.killer_name
                ) as first_kill
            FROM deaths d
            WHERE d.character_id = ?
            AND d.killer_name IN (
                SELECT DISTINCT boss_name FROM boss_kills WHERE character_id = ?
            )
            GROUP BY d.killer_name
            ORDER BY death_count DESC
        ');
        $stmt->execute([$character_id, $character_id]);
        $bossDeaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['boss_deaths']['by_boss'] = array_map(function ($row) {
            return [
                'boss' => $row['boss'],
                'deaths' => (int) $row['death_count'],
                'avg_repair_cost' => round((float) $row['avg_repair_cost'], 2),
                'first_death' => $row['first_death'],
                'first_kill' => $row['first_kill'],
                'deaths_before_kill' => null
            ];
        }, $bossDeaths);
    } catch (Exception $e) {
        error_log("Error getting boss deaths: " . $e->getMessage());
    }

    // Hardest boss
    if (!empty($data['boss_deaths']['by_boss'])) {
        $hardest = null;
        $maxDeaths = 0;

        foreach ($data['boss_deaths']['by_boss'] as &$boss) {
            if ($boss['first_kill']) {
                try {
                    $stmt = $pdo->prepare('
                        SELECT COUNT(*) as pre_kill_deaths
                        FROM deaths
                        WHERE character_id = ?
                        AND killer_name = ?
                        AND ts < (
                            SELECT MIN(ts) FROM boss_kills 
                            WHERE character_id = ? AND boss_name = ?
                        )
                    ');
                    $stmt->execute([
                        $character_id,
                        $boss['boss'],
                        $character_id,
                        $boss['boss']
                    ]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $boss['deaths_before_kill'] = (int) ($result['pre_kill_deaths'] ?? 0);

                    if ($boss['deaths_before_kill'] > $maxDeaths) {
                        $maxDeaths = $boss['deaths_before_kill'];
                        $hardest = $boss;
                    }
                } catch (Exception $e) {
                    error_log("Error calculating deaths before kill: " . $e->getMessage());
                }
            }
        }

        $data['boss_deaths']['hardest_boss'] = $hardest;
    }

    // Longest alive streak
    try {
        $stmt = $pdo->prepare('SELECT ts FROM deaths WHERE character_id = ? ORDER BY ts ASC');
        $stmt->execute([$character_id]);
        $deaths = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($deaths) > 1) {
            $maxStreak = 0;
            for ($i = 1; $i < count($deaths); $i++) {
                $streak = $deaths[$i] - $deaths[$i - 1];
                if ($streak > $maxStreak) {
                    $maxStreak = $streak;
                }
            }
            $data['overview']['longest_alive_streak'] = $maxStreak;
        }
    } catch (Exception $e) {
        error_log("Error calculating alive streak: " . $e->getMessage());
    }

    // Output JSON
    echo json_encode($data, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log("Fatal error in mortality-data.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage()
    ]);
}