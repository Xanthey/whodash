<?php
// sections/dashboard-data.php
// Provides data specifically for the dashboard tabs
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

// Enable error logging (don't display)
ini_set('display_errors', '0');
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

    // Get current level from character record
    $currentLevel = $character['level'] ?? 1;

    // Initialize response
    $data = [
        'identity' => [
            'level' => $currentLevel,
            'className' => $character['class_local'],
            'spec' => $character['spec'],
            'race' => $character['race'],
            'sex' => $character['sex'],
            'guild' => $character['guild_name']
        ],
        'player' => [
            'class' => $character['class_local'],
            'guild' => $character['guild_name']
        ],
        'timeseries' => [
            'level' => [],
            'money' => [],
            'health' => [],
            'mana' => [],
            'played' => []
        ],
        'equipment' => [],
        'avgIlvl' => 0,
        'achievements' => [
            'total' => 0,
            'points' => 0
        ],
        'sessions' => [],
        'talents' => [
            'totalPoints' => 0,
            'trees' => new stdClass()  // Force empty object in JSON
        ]
    ];

    // ===== TIMESERIES DATA =====
    // Only query tables that exist
    try {
        $stmt = $pdo->prepare("SELECT ts, value FROM series_level WHERE character_id = ? ORDER BY ts ASC");
        $stmt->execute([$character_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['timeseries']['level'] = array_map(function ($row) {
            return [
                'ts' => (int) $row['ts'],
                'value' => (int) $row['value']
            ];
        }, $rows);
    } catch (Exception $e) {
        error_log("Error loading series_level: " . $e->getMessage());
    }

    // Try to find money/gold table (could be named differently)
    foreach (['series_money', 'series_gold', 'timeseries_money'] as $tableName) {
        try {
            $stmt = $pdo->prepare("SELECT ts, value FROM {$tableName} WHERE character_id = ? ORDER BY ts ASC");
            $stmt->execute([$character_id]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $data['timeseries']['money'] = array_map(function ($row) {
                return [
                    'ts' => (int) $row['ts'],
                    'value' => (int) $row['value']
                ];
            }, $rows);
            break; // Found it, stop looking
        } catch (Exception $e) {
            // Table doesn't exist, try next
        }
    }

    // ===== HEALTH AND MANA =====
    try {
        $stmt = $pdo->prepare("SELECT ts, hp, mp, power_type FROM series_resource_max WHERE character_id = ? ORDER BY ts ASC");
        $stmt->execute([$character_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $row) {
            if ($row['hp'] !== null) {
                $data['timeseries']['health'][] = [
                    'ts' => (int) $row['ts'],
                    'value' => (int) $row['hp']
                ];
            }
            if ($row['mp'] !== null) {
                $data['timeseries']['mana'][] = [
                    'ts' => (int) $row['ts'],
                    'value' => (int) $row['mp'],
                    'powerType' => (int) ($row['power_type'] ?? 0)
                ];
            }
        }

        // Add power type to identity for UI labeling
        if (!empty($rows)) {
            $latestRow = end($rows);
            $data['identity']['powerType'] = (int) ($latestRow['power_type'] ?? 0);
        }
    } catch (Exception $e) {
        error_log("Error loading health/mana: " . $e->getMessage());
    }

    // ===== ACHIEVEMENTS =====
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total, COALESCE(SUM(points), 0) as points 
            FROM series_achievements 
            WHERE character_id = ? AND earned = 1
        ');
        $stmt->execute([$character_id]);
        $achData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($achData) {
            $data['achievements'] = [
                'total' => (int) $achData['total'],
                'points' => (int) $achData['points']
            ];
        }
    } catch (Exception $e) {
        error_log("Error loading achievements: " . $e->getMessage());
    }

    // ===== SESSIONS =====
    try {
        $stmt = $pdo->prepare('
            SELECT ts, total_time as duration 
            FROM sessions 
            WHERE character_id = ? 
            ORDER BY ts DESC
        ');
        $stmt->execute([$character_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['sessions'] = array_map(function ($row) {
            return [
                'ts' => (int) $row['ts'],
                'duration' => (int) $row['duration']
            ];
        }, $rows);
    } catch (Exception $e) {
        error_log("Error loading sessions: " . $e->getMessage());
    }

    // ===== EQUIPMENT =====
    try {
        $stmt = $pdo->prepare('
            SELECT * FROM equipment_snapshot 
            WHERE character_id = ? 
            ORDER BY ts DESC
        ');
        $stmt->execute([$character_id]);
        $equipRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($equipRows) {
            // Map slot names to slot IDs (WoW standard)
            $slotNameToId = [
                'Head' => 1,
                'Neck' => 2,
                'Shoulder' => 3,
                'Shirt' => 4,
                'Chest' => 5,
                'Waist' => 6,
                'Legs' => 7,
                'Feet' => 8,
                'Wrist' => 9,
                'Hands' => 10,
                'Finger1' => 11,
                'Finger2' => 12,
                'Trinket1' => 13,
                'Trinket2' => 14,
                'Back' => 15,
                'MainHand' => 16,
                'SecondaryHand' => 17,
                'OffHand' => 17,
                'Ranged' => 18,
                'Tabard' => 19
            ];

            $totalIlvl = 0;
            $itemCount = 0;
            $seenSlots = [];

            foreach ($equipRows as $row) {
                $slotName = $row['slot_name'];

                // Only take most recent item per slot
                if (isset($seenSlots[$slotName]))
                    continue;
                $seenSlots[$slotName] = true;

                $slotId = $slotNameToId[$slotName] ?? 0;
                if ($slotId === 0)
                    continue;

                // Parse quality from WoW color codes
                $quality = 1; // Default: Common (white)
                $link = $row['link'] ?? '';

                if (strpos($link, '|cff9d9d9d') !== false)
                    $quality = 0; // Poor (gray)
                else if (strpos($link, '|cffffffff') !== false)
                    $quality = 1; // Common (white)
                else if (strpos($link, '|cff1eff00') !== false)
                    $quality = 2; // Uncommon (green)
                else if (strpos($link, '|cff0070dd') !== false)
                    $quality = 3; // Rare (blue)
                else if (strpos($link, '|cffa335ee') !== false)
                    $quality = 4; // Epic (purple)
                else if (strpos($link, '|cffff8000') !== false)
                    $quality = 5; // Legendary (orange)

                $ilvl = (int) ($row['ilvl'] ?? 0);

                $data['equipment'][$slotId] = [
                    'item_id' => (int) ($row['item_id'] ?? 0),
                    'name' => $row['name'] ?? 'Unknown',
                    'ilvl' => $ilvl,
                    'quality' => $quality,
                    'icon' => $row['icon'] ?? null,
                    'link' => $link
                ];

                // Don't count cosmetic slots in average ilvl
                if ($slotName !== 'Shirt' && $slotName !== 'Tabard' && $ilvl > 0) {
                    $totalIlvl += $ilvl;
                    $itemCount++;
                }
            }

            $data['avgIlvl'] = $itemCount > 0 ? round($totalIlvl / $itemCount) : 0;
        }
    } catch (Exception $e) {
        error_log("Error loading equipment: " . $e->getMessage());
    }

    // ===== TALENTS =====
    try {
        // Get most recent talent group
        $stmt = $pdo->prepare('
            SELECT id, ts, group_index 
            FROM talents_groups 
            WHERE character_id = ? 
            ORDER BY ts DESC 
            LIMIT 1
        ');
        $stmt->execute([$character_id]);
        $group = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("Talent group found: " . ($group ? "YES (id={$group['id']})" : "NO"));

        if ($group) {
            $groupId = $group['id'];

            // Get talent tabs (trees)
            $stmt = $pdo->prepare('
                SELECT id, name, icon, points_spent
                FROM talents_tabs 
                WHERE talents_group_id = ?
                ORDER BY id ASC
            ');
            $stmt->execute([$groupId]);
            $tabs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("Talent tabs found: " . count($tabs));

            $totalPoints = 0;
            $trees = [];

            foreach ($tabs as $tab) {
                $tabId = $tab['id'];
                $treeName = $tab['name'] ?? 'Unknown';
                $points = (int) ($tab['points_spent'] ?? 0);
                $totalPoints += $points;

                error_log("Processing tree: {$treeName} with {$points} points");

                // Get individual talents in this tree
                $stmt = $pdo->prepare('SELECT name, talent_id, max_rank, `rank`, link FROM talents WHERE talents_tab_id = ? AND `rank` > 0 ORDER BY id ASC');
                $stmt->execute([$tabId]);
                $talentRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                error_log("Talents in {$treeName}: " . count($talentRows));

                $talents = [];
                foreach ($talentRows as $talent) {
                    // Extract spell ID from talent link
                    $spellId = (int) ($talent['talent_id'] ?? 0);
                    if (!empty($talent['link'])) {
                        if (preg_match('/\|Htalent:(\d+):/', $talent['link'], $matches)) {
                            $spellId = (int) $matches[1];
                        }
                    }

                    $talents[] = [
                        'name' => $talent['name'] ?? 'Unknown',
                        'spell_id' => $spellId,
                        'rank' => (int) ($talent['rank'] ?? 0),
                        'max_rank' => (int) ($talent['max_rank'] ?? 1),
                        'description' => null
                    ];
                }

                $trees[$treeName] = [
                    'points' => $points,
                    'talents' => $talents
                ];
            }

            error_log("Total points: {$totalPoints}, Trees built: " . count($trees));

            $data['talents'] = [
                'totalPoints' => $totalPoints,
                'trees' => $trees
            ];
        }
    } catch (Exception $e) {
        error_log("Error loading talents: " . $e->getMessage());
    }

    // Output JSON
    echo json_encode($data, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log("Fatal error in dashboard-data.php: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => 'An error occurred while loading dashboard data'
    ]);
}