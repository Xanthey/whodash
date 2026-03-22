<?php
// sections/bazaar-timeline.php
// Character Journey Timeline endpoint - Returns unified milestone data as JSON
declare(strict_types=1);

// Suppress PHP warnings that could corrupt JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

require_once __DIR__ . '/../db.php';

// Start session and check authentication
session_start();

header('Content-Type: application/json');
header('Cache-Control: private, max-age=120');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

try {
    // Get user's characters with basic info
    $stmt = $pdo->prepare("
        SELECT 
            id as character_id,
            name,
            realm,
            faction,
            class_file,
            race,
            created_at,
            updated_at
        FROM characters
        WHERE user_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$user_id]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($characters)) {
        echo json_encode([
            'characters' => [],
            'timeline' => [],
            'summary' => [
                'total_characters' => 0,
                'journey_started' => null,
                'latest_activity' => null,
                'total_milestones' => 0
            ]
        ]);
        exit;
    }

    $character_ids = array_column($characters, 'character_id');
    $character_map = [];
    foreach ($characters as $char) {
        $character_map[$char['character_id']] = $char;
    }

    $timeline_events = [];

    // Helper function to add timeline event
    function addTimelineEvent(&$timeline, $timestamp, $type, $character_info, $details)
    {
        if (!$timestamp)
            return;

        $timeline[] = [
            'timestamp' => $timestamp,
            'date' => date('Y-m-d H:i:s', $timestamp),
            'type' => $type,
            'character' => $character_info,
            'details' => $details
        ];
    }

    // 1. CHARACTER CREATION EVENTS
    foreach ($characters as $char) {
        $creation_time = strtotime($char['created_at'] ?? 'now');
        addTimelineEvent(
            $timeline_events,
            $creation_time,
            'character_created',
            [
                'id' => $char['character_id'],
                'name' => $char['name'],
                'realm' => $char['realm'],
                'faction' => $char['faction'],
                'class' => $char['class_file'],
                'race' => $char['race']
            ],
            [
                'title' => 'A New Adventure Begins',
                'description' => "Created {$char['race']} {$char['class_file']} on {$char['realm']}",
                'icon' => '✨'
            ]
        );
    }

    if (!empty($character_ids)) {
        $placeholders = implode(',', array_fill(0, count($character_ids), '?'));

        // 2. LEVEL MILESTONES (Major levels: 10, 20, 30, 40, 50, 60, 70, 80, 90, 100, etc.)
        try {
            $stmt = $pdo->prepare("
                SELECT character_id, ts, value
                FROM series_level
                WHERE character_id IN ($placeholders)
                    AND value IN (10, 20, 30, 40, 50, 60, 70, 80, 90, 100, 110, 120)
                ORDER BY ts ASC
            ");
            $stmt->execute($character_ids);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!isset($character_map[$row['character_id']]))
                    continue;
                $char = $character_map[$row['character_id']];

                addTimelineEvent(
                    $timeline_events,
                    (int) $row['ts'],
                    'level_milestone',
                    [
                        'id' => $char['character_id'],
                        'name' => $char['name'],
                        'realm' => $char['realm'],
                        'faction' => $char['faction'],
                        'class' => $char['class_file']
                    ],
                    [
                        'title' => 'Level Milestone Reached',
                        'description' => "Reached level {$row['value']}",
                        'icon' => '🏆',
                        'level' => (int) $row['value']
                    ]
                );
            }
        } catch (Exception $e) {
            // Table might not exist, skip
        }

        // 3. GUILD MILESTONES
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    cg.character_id,
                    UNIX_TIMESTAMP(cg.joined_at) as ts,
                    g.guild_name,
                    g.faction,
                    g.realm
                FROM character_guilds cg
                JOIN guilds g ON cg.guild_id = g.guild_id
                JOIN characters c ON cg.character_id = c.id
                WHERE c.user_id = ? AND cg.joined_at IS NOT NULL
                ORDER BY cg.joined_at ASC
            ");
            $stmt->execute([$user_id]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!isset($character_map[$row['character_id']]))
                    continue;
                $char = $character_map[$row['character_id']];

                addTimelineEvent(
                    $timeline_events,
                    (int) $row['ts'],
                    'guild_joined',
                    [
                        'id' => $char['character_id'],
                        'name' => $char['name'],
                        'realm' => $char['realm'],
                        'faction' => $char['faction'],
                        'class' => $char['class_file']
                    ],
                    [
                        'title' => 'Joined a Guild',
                        'description' => "Joined <{$row['guild_name']}> on {$row['realm']}",
                        'icon' => '🏰',
                        'guild_name' => $row['guild_name']
                    ]
                );
            }
        } catch (Exception $e) {
            // Table might not exist, skip
        }

        // 4. FIRST DUNGEON/RAID ENCOUNTERS (from combat logs)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    character_id,
                    MIN(ts) as first_encounter,
                    target as encounter_name,
                    instance_difficulty as difficulty,
                    instance as zone_name
                FROM combat_encounters
                WHERE character_id IN ($placeholders)
                    AND ts > 0
                    AND is_boss = 1
                GROUP BY character_id, target, instance_difficulty
                ORDER BY first_encounter ASC
                LIMIT 20
            ");
            $stmt->execute($character_ids);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!isset($character_map[$row['character_id']]))
                    continue;
                $char = $character_map[$row['character_id']];

                addTimelineEvent(
                    $timeline_events,
                    (int) $row['first_encounter'],
                    'first_encounter',
                    [
                        'id' => $char['character_id'],
                        'name' => $char['name'],
                        'realm' => $char['realm'],
                        'faction' => $char['faction'],
                        'class' => $char['class_file']
                    ],
                    [
                        'title' => 'First Boss Encounter',
                        'description' => "First attempt at {$row['encounter_name']} ({$row['difficulty']})",
                        'icon' => '⚔️',
                        'encounter' => $row['encounter_name'],
                        'zone' => $row['zone_name']
                    ]
                );
            }
        } catch (Exception $e) {
            // Table might not exist, skip
        }

        // 5. ACHIEVEMENT MILESTONES (Major achievements)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    character_id,
                    earned_date as ts,
                    achievement_id,
                    name as title
                FROM series_achievements
                WHERE character_id IN ($placeholders)
                    AND earned = 1
                    AND earned_date IS NOT NULL
                    AND points >= 10
                ORDER BY earned_date ASC
                LIMIT 50
            ");
            $stmt->execute($character_ids);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!isset($character_map[$row['character_id']]))
                    continue;
                $char = $character_map[$row['character_id']];

                addTimelineEvent(
                    $timeline_events,
                    (int) $row['ts'],
                    'achievement_unlocked',
                    [
                        'id' => $char['character_id'],
                        'name' => $char['name'],
                        'realm' => $char['realm'],
                        'faction' => $char['faction'],
                        'class' => $char['class_file']
                    ],
                    [
                        'title' => 'Achievement Unlocked',
                        'description' => $row['title'] ?? "Achievement #{$row['achievement_id']}",
                        'icon' => '🎖️',
                        'achievement_id' => (int) $row['achievement_id']
                    ]
                );
            }
        } catch (Exception $e) {
            // Table might not exist, skip
        }

        // 6. PROFESSION MILESTONES (Skill maxed)
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    character_id,
                    ts,
                    name as profession,
                    rank as skill_value
                FROM skills
                WHERE character_id IN ($placeholders)
                    AND rank >= 300
                    AND name IN ('Alchemy', 'Blacksmithing', 'Enchanting', 'Engineering', 
                                'Herbalism', 'Leatherworking', 'Mining', 'Skinning', 
                                'Tailoring', 'Jewelcrafting', 'Inscription')
                ORDER BY ts ASC
            ");
            $stmt->execute($character_ids);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!isset($character_map[$row['character_id']]))
                    continue;
                $char = $character_map[$row['character_id']];

                addTimelineEvent(
                    $timeline_events,
                    (int) $row['ts'],
                    'profession_milestone',
                    [
                        'id' => $char['character_id'],
                        'name' => $char['name'],
                        'realm' => $char['realm'],
                        'faction' => $char['faction'],
                        'class' => $char['class_file']
                    ],
                    [
                        'title' => 'Profession Mastery',
                        'description' => "Reached {$row['skill_value']} in {$row['profession']}",
                        'icon' => '🔨',
                        'profession' => $row['profession'],
                        'skill_level' => (int) $row['skill_value']
                    ]
                );
            }
        } catch (Exception $e) {
            // Table might not exist, skip
        }
    }

    // Sort all events by timestamp
    usort($timeline_events, function ($a, $b) {
        return $a['timestamp'] - $b['timestamp'];
    });

    // Calculate summary stats
    $journey_started = !empty($timeline_events) ? $timeline_events[0]['timestamp'] : null;
    $latest_activity = !empty($timeline_events) ? end($timeline_events)['timestamp'] : null;

    // Group events by character for character summaries
    $character_summaries = [];
    foreach ($characters as $char) {
        $character_events = array_filter($timeline_events, function ($event) use ($char) {
            return $event['character']['id'] == $char['character_id'];
        });

        $character_summaries[] = [
            'character' => [
                'id' => $char['character_id'],
                'name' => $char['name'],
                'realm' => $char['realm'],
                'faction' => $char['faction'],
                'class' => $char['class_file'],
                'race' => $char['race']
            ],
            'milestone_count' => count($character_events),
            'first_milestone' => !empty($character_events) ? min(array_column($character_events, 'timestamp')) : null,
            'latest_milestone' => !empty($character_events) ? max(array_column($character_events, 'timestamp')) : null
        ];
    }

    // Sort character summaries by milestone count descending
    usort($character_summaries, function ($a, $b) {
        return $b['milestone_count'] - $a['milestone_count'];
    });

    $response = [
        'characters' => $character_summaries,
        'timeline' => array_values($timeline_events),
        'summary' => [
            'total_characters' => count($characters),
            'journey_started' => $journey_started,
            'latest_activity' => $latest_activity,
            'total_milestones' => count($timeline_events)
        ]
    ];

    echo json_encode($response, JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    error_log("Timeline error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error loading timeline data',
        'message' => $e->getMessage()
    ]);
}
?>