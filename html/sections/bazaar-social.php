<?php
// sections/bazaar-social.php - Multi-character social data aggregator (FIXED)
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Suppress PHP warnings that could corrupt JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Initialize response payload
$payload = [
    'friends' => [],
    'most_played_with' => []
];

try {
    // Get all user's characters
    $stmt = $pdo->prepare('SELECT id, name FROM characters WHERE user_id = ? ORDER BY name');
    $stmt->execute([$user_id]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($characters)) {
        echo json_encode($payload);
        exit;
    }

    $character_ids = array_column($characters, 'id');
    $character_names = [];
    foreach ($characters as $char) {
        $character_names[$char['id']] = $char['name'];
    }

    $placeholders = implode(',', array_fill(0, count($character_ids), '?'));

    // ============================================================================
    // FRIENDS LIST (from friend_list_changes)
    // ============================================================================
    try {
        // Get the most recent state of each friend (added or removed)
        $stmt = $pdo->prepare("
            SELECT 
                flc.character_id,
                flc.friend_name,
                flc.friend_class,
                flc.friend_level,
                flc.action,
                flc.ts
            FROM friend_list_changes flc
            INNER JOIN (
                SELECT character_id, friend_name, MAX(ts) as max_ts
                FROM friend_list_changes
                WHERE character_id IN ($placeholders)
                GROUP BY character_id, friend_name
            ) latest ON flc.character_id = latest.character_id 
                AND flc.friend_name = latest.friend_name 
                AND flc.ts = latest.max_ts
            WHERE flc.character_id IN ($placeholders)
                AND flc.action = 'added'
            ORDER BY flc.friend_name ASC
        ");
        // Need to pass character_ids twice
        $stmt->execute(array_merge($character_ids, $character_ids));

        // Deduplicate friends across characters
        $friends_map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $friend_key = strtolower($row['friend_name']);

            if (!isset($friends_map[$friend_key])) {
                $added_at = date('Y-m-d H:i:s', $row['ts']);

                $friends_map[$friend_key] = [
                    'friend_name' => $row['friend_name'],
                    'class_name' => $row['friend_class'] ?? null,
                    'level' => (int) ($row['friend_level'] ?? 0),
                    'added_at' => $added_at,
                    'character_name' => $character_names[$row['character_id']] ?? 'Unknown',
                    'character_id' => (int) $row['character_id'],
                    'appearances' => 1,
                    'last_grouped' => null
                ];
            } else {
                // Friend appears on multiple characters
                $friends_map[$friend_key]['appearances']++;
            }
        }

        $payload['friends'] = array_values($friends_map);
    } catch (Throwable $e) {
        error_log("Bazaar social - friends error: " . $e->getMessage());
    }

    // ============================================================================
    // MOST PLAYED WITH (from group_compositions)
    // ============================================================================
    try {
        $stmt = $pdo->prepare("
            SELECT 
                gc.character_id,
                gc.type as group_type,
                gc.members,
                gc.ts,
                gc.instance
            FROM group_compositions gc
            WHERE gc.character_id IN ($placeholders)
                AND gc.members IS NOT NULL
                AND gc.ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            ORDER BY gc.ts DESC
        ");
        $stmt->execute($character_ids);

        // Parse JSON members and count occurrences
        $player_stats = [];

        // Build a set of own character names (lowercase) for fast exclusion
        $ownNames = array_map('strtolower', array_values($character_names));

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $members = json_decode($row['members'], true);
            if (!is_array($members))
                continue;

            foreach ($members as $member) {
                // Support both flat string and object format
                $memberName = is_string($member) ? $member : ($member['name'] ?? null);
                if (!$memberName)
                    continue;

                // Skip own characters
                if (in_array(strtolower($memberName), $ownNames, true))
                    continue;

                $memberClass = is_array($member) ? ($member['class'] ?? null) : null;
                $playerKey = strtolower($memberName);

                if (!isset($player_stats[$playerKey])) {
                    $player_stats[$playerKey] = [
                        'player_name' => $memberName,
                        'class_name' => $memberClass,
                        'groups_together' => 0,
                        'party_count' => 0,
                        'raid_count' => 0,
                    ];
                }

                $player_stats[$playerKey]['groups_together']++;

                if ($row['group_type'] === 'party') {
                    $player_stats[$playerKey]['party_count']++;
                } else {
                    $player_stats[$playerKey]['raid_count']++;
                }
            }
        }

        // Calculate percentages and sort by groups_together
        uasort($player_stats, function ($a, $b) {
            return $b['groups_together'] - $a['groups_together'];
        });

        // Take top 50 and format
        $top_players = array_slice($player_stats, 0, 50, true);
        foreach ($top_players as $stats) {
            if ($stats['groups_together'] < 3)
                continue; // Filter out single occurrences

            $total_groups = $stats['groups_together'];
            $party_pct = $total_groups > 0 ? round(($stats['party_count'] / $total_groups) * 100) : 0;
            $raid_pct = $total_groups > 0 ? round(($stats['raid_count'] / $total_groups) * 100) : 0;

            $payload['most_played_with'][] = [
                'player_name' => $stats['player_name'],
                'class_name' => $stats['class_name'],
                'groups_together' => $total_groups,
                'hours_together' => 0, // We don't track duration per member
                'party_pct' => (int) $party_pct,
                'raid_pct' => (int) $raid_pct
            ];
        }
    } catch (Throwable $e) {
        error_log("Bazaar social - most played with error: " . $e->getMessage());
    }

} catch (Throwable $e) {
    error_log("Bazaar social - general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error loading social data']);
    exit;
}

echo json_encode($payload);