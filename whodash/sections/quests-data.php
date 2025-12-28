<?php
// sections/quests-data.php - ENHANCED VERSION with Quest Events & Quest Log
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
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
    $own = $pdo->prepare('SELECT id, name FROM characters WHERE id = ? AND user_id = ?');
    $own->execute([$cid, $_SESSION['user_id']]);
    $character = $own->fetch(PDO::FETCH_ASSOC);
    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not yours']);
        exit;
    }

    $payload = [];

    // ============================================================================
    // OVERVIEW TAB - Enhanced Quest Statistics
    // ============================================================================

    // Total quests completed
    $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM quest_rewards WHERE character_id = ?');
    $stmt->execute([$cid]);
    $payload['total_quests'] = (int) ($stmt->fetchColumn() ?? 0);

    // Check if quest_events table exists
    $hasQuestEvents = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'quest_events'");
        $hasQuestEvents = (bool) $stmt->fetch();
    } catch (Throwable $e) {
        $hasQuestEvents = false;
    }

    if ($hasQuestEvents) {
        // Total quest events
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM quest_events WHERE character_id = ?');
        $stmt->execute([$cid]);
        $payload['total_quest_events'] = (int) ($stmt->fetchColumn() ?? 0);

        // Quest acceptances
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM quest_events WHERE character_id = ? AND kind = "accepted"');
        $stmt->execute([$cid]);
        $payload['total_accepted'] = (int) ($stmt->fetchColumn() ?? 0);

        // Quest abandonments
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM quest_events WHERE character_id = ? AND kind = "abandoned"');
        $stmt->execute([$cid]);
        $payload['total_abandoned'] = (int) ($stmt->fetchColumn() ?? 0);

        // Completion rate
        if ($payload['total_accepted'] > 0) {
            $payload['completion_rate'] = round(($payload['total_quests'] / $payload['total_accepted']) * 100, 1);
        } else {
            $payload['completion_rate'] = 0;
        }

        // Quest event timeline (last 90 days)
        $stmt = $pdo->prepare('
            SELECT  
                DATE(FROM_UNIXTIME(ts)) as date, 
                kind,
                COUNT(*) as count
            FROM quest_events 
            WHERE character_id = ? 
                AND ts > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY))
                AND kind IN ("accepted", "completed", "abandoned")
            GROUP BY DATE(FROM_UNIXTIME(ts)), kind
            ORDER BY date
        ');
        $stmt->execute([$cid]);
        $payload['quest_event_timeline'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Quest event history (last 100 events for display)
        $stmt = $pdo->prepare('
            SELECT 
                ts,
                kind,
                quest_id,
                quest_title,
                objective_text,
                objective_progress,
                objective_total,
                objective_complete
            FROM quest_events
            WHERE character_id = ?
            ORDER BY ts DESC
            LIMIT 100
        ');
        $stmt->execute([$cid]);
        $payload['quest_event_history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $payload['total_quest_events'] = 0;
        $payload['total_accepted'] = 0;
        $payload['total_abandoned'] = 0;
        $payload['completion_rate'] = 0;
        $payload['quest_event_timeline'] = [];
        $payload['quest_event_history'] = [];
    }

    // Check if quest_log_snapshots table exists
    $hasQuestLog = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'quest_log_snapshots'");
        $hasQuestLog = (bool) $stmt->fetch();
    } catch (Throwable $e) {
        $hasQuestLog = false;
    }


    if ($hasQuestLog) {
        // Current quest log (most recent snapshot ONLY)
        // First, get the most recent snapshot timestamp
        $stmt = $pdo->prepare('
            SELECT MAX(ts) as latest_ts
            FROM quest_log_snapshots
            WHERE character_id = ?
        ');
        $stmt->execute([$cid]);
        $latestTs = $stmt->fetchColumn();

        if ($latestTs) {
            // Now get ALL quests from that specific timestamp
            $stmt = $pdo->prepare('
                SELECT 
                    quest_id,
                    quest_title,
                    quest_complete,
                    objectives,
                    ts
                FROM quest_log_snapshots
                WHERE character_id = ? AND ts = ?
                ORDER BY quest_id
            ');
            $stmt->execute([$cid, $latestTs]);
            $currentQuestLog = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $currentQuestLog = [];
        }


        // Parse objectives JSON
        foreach ($currentQuestLog as &$quest) {
            if (!empty($quest['objectives'])) {
                $objectives = json_decode($quest['objectives'], true) ?? [];

                // Fix: If objectives is an object with numeric keys (from Lua parser),
                // convert it to a proper array
                if (is_array($objectives) && !empty($objectives)) {
                    // Check if it's an associative array with numeric string keys
                    $keys = array_keys($objectives);
                    $isNumericKeys = true;
                    foreach ($keys as $key) {
                        if (!is_numeric($key)) {
                            $isNumericKeys = false;
                            break;
                        }
                    }

                    // If all keys are numeric, convert to indexed array
                    if ($isNumericKeys) {
                        $objectives = array_values($objectives);
                    }
                }

                $quest['objectives'] = $objectives;
            } else {
                $quest['objectives'] = [];
            }
        }
        $payload['current_quest_log'] = $currentQuestLog;
    } else {
        $payload['current_quest_log'] = [];
    }

    // Total XP from quests
    $stmt = $pdo->prepare('SELECT SUM(xp) as total_xp FROM quest_rewards WHERE character_id = ?');
    $stmt->execute([$cid]);
    $payload['total_xp'] = (int) ($stmt->fetchColumn() ?? 0);

    // Total gold from quests (convert copper to gold)
    $stmt = $pdo->prepare('SELECT SUM(money) as total_money FROM quest_rewards WHERE character_id = ?');
    $stmt->execute([$cid]);
    $totalCopper = (int) ($stmt->fetchColumn() ?? 0);
    $payload['total_gold'] = round($totalCopper / 10000, 2);

    // Quests per day average (last 90 days)
    $stmt = $pdo->prepare('
        SELECT 
            DATEDIFF(MAX(FROM_UNIXTIME(ts)), MIN(FROM_UNIXTIME(ts))) as days_active
        FROM quest_rewards 
        WHERE character_id = ?
            AND ts > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY))
    ');
    $stmt->execute([$cid]);
    $daysActive = (int) ($stmt->fetchColumn() ?? 1);

    $stmt = $pdo->prepare('
        SELECT COUNT(*) as recent_quests
        FROM quest_rewards 
        WHERE character_id = ?
            AND ts > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY))
    ');
    $stmt->execute([$cid]);
    $recentQuests = (int) ($stmt->fetchColumn() ?? 0);

    $payload['quests_per_day'] = $daysActive > 0 ? round($recentQuests / max($daysActive, 1), 1) : 0;

    // Most rewarding quest
    $stmt = $pdo->prepare('
        SELECT quest_title, money, xp, reward_chosen_name, reward_chosen_quality
        FROM quest_rewards
        WHERE character_id = ?
        ORDER BY (money + xp * 5) DESC
        LIMIT 1
    ');
    $stmt->execute([$cid]);
    $payload['most_rewarding_quest'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Quest completion trends (last 90 days, daily)
    $stmt = $pdo->prepare('
        SELECT  
            DATE(FROM_UNIXTIME(ts)) as date, 
            COUNT(*) as quests_completed, 
            SUM(xp) as total_xp, 
            SUM(money) as total_gold 
        FROM quest_rewards 
        WHERE character_id = ? 
            AND ts > UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY)) 
        GROUP BY DATE(FROM_UNIXTIME(ts)) 
        ORDER BY date
    ');
    $stmt->execute([$cid]);
    $payload['quest_trends'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Quest completion by zone (top 10)
    $stmt = $pdo->prepare('
        SELECT zone, COUNT(*) as quest_count
        FROM quest_rewards
        WHERE character_id = ?
            AND zone IS NOT NULL
            AND zone != ""
        GROUP BY zone
        ORDER BY quest_count DESC
        LIMIT 10
    ');
    $stmt->execute([$cid]);
    $payload['quests_by_zone'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Quest level distribution
    $stmt = $pdo->prepare('
        SELECT 
            CASE 
                WHEN quest_level < 10 THEN "1-9"
                WHEN quest_level < 20 THEN "10-19"
                WHEN quest_level < 30 THEN "20-29"
                WHEN quest_level < 40 THEN "30-39"
                WHEN quest_level < 50 THEN "40-49"
                WHEN quest_level < 60 THEN "50-59"
                WHEN quest_level < 70 THEN "60-69"
                WHEN quest_level < 80 THEN "70-79"
                ELSE "80+"
            END as level_range,
            COUNT(*) as quest_count
        FROM quest_rewards
        WHERE character_id = ?
            AND quest_level IS NOT NULL
        GROUP BY level_range
        ORDER BY MIN(quest_level)
    ');
    $stmt->execute([$cid]);
    $payload['quest_level_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================================
    // QUEST LOG TAB - All Quest Completions
    // ============================================================================

    // All quest completions
    $stmt = $pdo->prepare('
        SELECT 
            quest_title,
            zone,
            quest_level,
            reward_chosen_name,
            reward_chosen_quality,
            xp,
            money,
            honor,
            ts
        FROM quest_rewards
        WHERE character_id = ?
        ORDER BY ts DESC
    ');
    $stmt->execute([$cid]);
    $payload['all_quests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Notable quests - Highest XP
    $stmt = $pdo->prepare('
        SELECT quest_title, zone, xp, ts
        FROM quest_rewards
        WHERE character_id = ?
            AND xp > 0
        ORDER BY xp DESC
        LIMIT 5
    ');
    $stmt->execute([$cid]);
    $payload['highest_xp_quests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Highest gold
    $stmt = $pdo->prepare('
        SELECT quest_title, zone, money, ts
        FROM quest_rewards
        WHERE character_id = ?
            AND money > 0
        ORDER BY money DESC
        LIMIT 5
    ');
    $stmt->execute([$cid]);
    $payload['highest_gold_quests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Epic reward quests
    $stmt = $pdo->prepare('
        SELECT quest_title, zone, reward_chosen_name, ts
        FROM quest_rewards
        WHERE character_id = ?
            AND reward_chosen_quality >= 4
        ORDER BY reward_chosen_quality DESC, ts DESC
        LIMIT 10
    ');
    $stmt->execute([$cid]);
    $payload['epic_reward_quests'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================================
    // REWARDS TAB - Reward Preferences
    // ============================================================================

    // Quality distribution of chosen rewards
    $stmt = $pdo->prepare('
        SELECT 
            CASE reward_chosen_quality
                WHEN 0 THEN "Poor"
                WHEN 1 THEN "Common"
                WHEN 2 THEN "Uncommon"
                WHEN 3 THEN "Rare"
                WHEN 4 THEN "Epic"
                WHEN 5 THEN "Legendary"
                ELSE "Unknown"
            END as quality_name,
            reward_chosen_quality as quality_id,
            COUNT(*) as times_chosen
        FROM quest_rewards
        WHERE character_id = ?
            AND reward_chosen_name IS NOT NULL
        GROUP BY reward_chosen_quality
        ORDER BY reward_chosen_quality DESC
    ');
    $stmt->execute([$cid]);
    $payload['reward_quality_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Item slot preference
    $stmt = $pdo->prepare('
        SELECT 
            CASE  
                WHEN reward_chosen_name LIKE "%Boots%" OR reward_chosen_name LIKE "%Shoes%" THEN "Feet" 
                WHEN reward_chosen_name LIKE "%Helm%" OR reward_chosen_name LIKE "%Hood%" THEN "Head" 
                WHEN reward_chosen_name LIKE "%Chest%" OR reward_chosen_name LIKE "%Robe%" OR reward_chosen_name LIKE "%Vest%" THEN "Chest" 
                WHEN reward_chosen_name LIKE "%Gloves%" OR reward_chosen_name LIKE "%Gauntlets%" THEN "Hands" 
                WHEN reward_chosen_name LIKE "%Legs%" OR reward_chosen_name LIKE "%Pants%" OR reward_chosen_name LIKE "%Leggings%" THEN "Legs"
                WHEN reward_chosen_name LIKE "%Shoulder%" OR reward_chosen_name LIKE "%Pauldron%" THEN "Shoulders"
                WHEN reward_chosen_name LIKE "%Belt%" OR reward_chosen_name LIKE "%Girdle%" THEN "Waist"
                WHEN reward_chosen_name LIKE "%Bracers%" OR reward_chosen_name LIKE "%Wrist%" THEN "Wrists"
                WHEN reward_chosen_name LIKE "%Cloak%" OR reward_chosen_name LIKE "%Cape%" THEN "Back"
                WHEN reward_chosen_name LIKE "%Ring%" THEN "Finger"
                WHEN reward_chosen_name LIKE "%Neck%" OR reward_chosen_name LIKE "%Amulet%" THEN "Neck"
                WHEN reward_chosen_name LIKE "%Trinket%" THEN "Trinket"
                WHEN reward_chosen_name LIKE "%Sword%" OR reward_chosen_name LIKE "%Axe%" OR reward_chosen_name LIKE "%Mace%" OR reward_chosen_name LIKE "%Dagger%" THEN "Weapon"
                WHEN reward_chosen_name LIKE "%Staff%" OR reward_chosen_name LIKE "%Wand%" THEN "Weapon"
                WHEN reward_chosen_name LIKE "%Bow%" OR reward_chosen_name LIKE "%Gun%" OR reward_chosen_name LIKE "%Crossbow%" THEN "Ranged"
                WHEN reward_chosen_name LIKE "%Shield%" THEN "Off-Hand"
                ELSE "Other" 
            END as slot, 
            COUNT(*) as times_chosen
        FROM quest_rewards
        WHERE character_id = ?
            AND reward_chosen_name IS NOT NULL
        GROUP BY slot
        ORDER BY times_chosen DESC
    ');
    $stmt->execute([$cid]);
    $payload['reward_slot_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Top chosen rewards
    $stmt = $pdo->prepare('
        SELECT 
            reward_chosen_name,
            reward_chosen_quality,
            COUNT(*) as times_chosen
        FROM quest_rewards
        WHERE character_id = ?
            AND reward_chosen_name IS NOT NULL
        GROUP BY reward_chosen_name, reward_chosen_quality
        ORDER BY times_chosen DESC
        LIMIT 20
    ');
    $stmt->execute([$cid]);
    $payload['top_chosen_rewards'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}