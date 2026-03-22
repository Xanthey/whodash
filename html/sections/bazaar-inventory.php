<?php
// sections/bazaar-inventory.php - Multi-character + Guild Bank Inventory Browser
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

// Helper function to get quality name and color
function getQualityInfo($quality)
{
    $map = [
        0 => ['name' => 'Poor', 'color' => '#9d9d9d'],
        1 => ['name' => 'Common', 'color' => '#ffffff'],
        2 => ['name' => 'Uncommon', 'color' => '#1eff00'],
        3 => ['name' => 'Rare', 'color' => '#0070dd'],
        4 => ['name' => 'Epic', 'color' => '#a335ee'],
        5 => ['name' => 'Legendary', 'color' => '#ff8000'],
        6 => ['name' => 'Artifact', 'color' => '#e6cc80'],
        7 => ['name' => 'Heirloom', 'color' => '#00ccff'],
    ];
    return $map[$quality] ?? ['name' => 'Common', 'color' => '#ffffff'];
}

// Initialize response payload
$payload = [
    'characters' => [],
    'guilds' => [],
    'summary' => [
        'total_characters' => 0,
        'total_guilds' => 0,
        'total_items' => 0,
        'unique_items' => 0,
        'total_count' => 0
    ]
];

try {
    // ============================================================================
    // GET CHARACTERS
    // ============================================================================
    $stmt = $pdo->prepare('
        SELECT 
            c.id,
            c.name,
            c.realm,
            c.faction,
            c.class_file
        FROM characters c
        WHERE c.user_id = ?
        ORDER BY c.name
    ');
    $stmt->execute([$user_id]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $character_ids = array_column($characters, 'id');
    $character_map = [];
    foreach ($characters as $char) {
        $character_map[$char['id']] = $char;
    }

    // ============================================================================
    // GET CHARACTER ITEMS
    // ============================================================================
    if (!empty($character_ids)) {
        $placeholders = implode(',', array_fill(0, count($character_ids), '?'));

        foreach ($characters as &$char) {
            $char['items'] = [];
            $char['item_count'] = 0;
            $char['unique_items'] = 0;
            $items_by_name = [];

            // Get level for character
            $stmt = $pdo->prepare('
                SELECT value as level
                FROM series_level
                WHERE character_id = ?
                ORDER BY ts DESC
                LIMIT 1
            ');
            $stmt->execute([$char['id']]);
            $levelRow = $stmt->fetch(PDO::FETCH_ASSOC);
            $char['level'] = $levelRow ? (int) $levelRow['level'] : 0;

            // BAGS
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        bag_id,
                        slot,
                        item_id,
                        name,
                        link,
                        count,
                        ilvl,
                        icon,
                        quality
                    FROM containers_bag
                    WHERE character_id = ?
                    ORDER BY bag_id, slot
                ");
                $stmt->execute([$char['id']]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $qualityInfo = getQualityInfo((int) ($row['quality'] ?? 1));
                    $item = [
                        'item_id' => (int) $row['item_id'],
                        'name' => $row['name'],
                        'link' => $row['link'],
                        'count' => (int) $row['count'],
                        'ilvl' => (int) ($row['ilvl'] ?? 0),
                        'icon' => $row['icon'],
                        'quality' => (int) ($row['quality'] ?? 1),
                        'quality_name' => $qualityInfo['name'],
                        'quality_color' => $qualityInfo['color'],
                        'location' => 'Bags',
                        'location_detail' => "Bag {$row['bag_id']}, Slot {$row['slot']}"
                    ];

                    $items_by_name[$row['name']][] = $item;
                    $char['item_count'] += (int) $row['count'];
                }
            } catch (Throwable $e) {
                error_log("Error loading bags for character {$char['id']}: " . $e->getMessage());
            }

            // BANK
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        inv_slot,
                        item_id,
                        name,
                        link,
                        count,
                        ilvl,
                        icon,
                        quality
                    FROM containers_bank
                    WHERE character_id = ?
                    ORDER BY inv_slot
                ");
                $stmt->execute([$char['id']]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $qualityInfo = getQualityInfo((int) ($row['quality'] ?? 1));
                    $item = [
                        'item_id' => (int) $row['item_id'],
                        'name' => $row['name'],
                        'link' => $row['link'],
                        'count' => (int) $row['count'],
                        'ilvl' => (int) ($row['ilvl'] ?? 0),
                        'icon' => $row['icon'],
                        'quality' => (int) ($row['quality'] ?? 1),
                        'quality_name' => $qualityInfo['name'],
                        'quality_color' => $qualityInfo['color'],
                        'location' => 'Bank',
                        'location_detail' => "Slot {$row['inv_slot']}"
                    ];

                    $items_by_name[$row['name']][] = $item;
                    $char['item_count'] += (int) $row['count'];
                }
            } catch (Throwable $e) {
                error_log("Error loading bank for character {$char['id']}: " . $e->getMessage());
            }

            // MAIL
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        ma.item_id,
                        ma.name,
                        ma.link,
                        ma.count,
                        ma.ilvl,
                        ma.icon,
                        ma.quality,
                        m.sender,
                        m.subject
                    FROM mailbox_attachments ma
                    JOIN mailbox m ON m.id = ma.mailbox_id
                    WHERE m.character_id = ?
                    ORDER BY m.mail_index, ma.a_index
                ");
                $stmt->execute([$char['id']]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $qualityInfo = getQualityInfo((int) ($row['quality'] ?? 1));
                    $item = [
                        'item_id' => (int) $row['item_id'],
                        'name' => $row['name'],
                        'link' => $row['link'],
                        'count' => (int) $row['count'],
                        'ilvl' => (int) ($row['ilvl'] ?? 0),
                        'icon' => $row['icon'],
                        'quality' => (int) ($row['quality'] ?? 1),
                        'quality_name' => $qualityInfo['name'],
                        'quality_color' => $qualityInfo['color'],
                        'location' => 'Mail',
                        'location_detail' => "From {$row['sender']}"
                    ];

                    $items_by_name[$row['name']][] = $item;
                    $char['item_count'] += (int) $row['count'];
                }
            } catch (Throwable $e) {
                error_log("Error loading mail for character {$char['id']}: " . $e->getMessage());
            }

            // Aggregate items by name
            foreach ($items_by_name as $name => $item_list) {
                $total_count = array_sum(array_column($item_list, 'count'));
                $locations = [];
                foreach ($item_list as $item) {
                    $locations[] = [
                        'location' => $item['location'],
                        'location_detail' => $item['location_detail'],
                        'count' => $item['count']
                    ];
                }

                // Use first item for base info
                $first_item = $item_list[0];
                $char['items'][] = [
                    'item_id' => $first_item['item_id'],
                    'name' => $name,
                    'link' => $first_item['link'],
                    'total_count' => $total_count,
                    'ilvl' => $first_item['ilvl'],
                    'icon' => $first_item['icon'],
                    'quality' => $first_item['quality'],
                    'quality_name' => $first_item['quality_name'],
                    'quality_color' => $first_item['quality_color'],
                    'locations' => $locations
                ];
            }

            $char['unique_items'] = count($char['items']);

            // Sort items by name
            usort($char['items'], function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }
        unset($char);
    }

    // ============================================================================
    // GET GUILDS
    // ============================================================================
    if (!empty($character_ids)) {
        $placeholders = implode(',', array_fill(0, count($character_ids), '?'));

        // Get guilds the user's characters belong to
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                g.guild_id,
                g.guild_name,
                g.faction,
                g.realm
            FROM guilds g
            INNER JOIN character_guilds cg ON g.guild_id = cg.guild_id
            WHERE cg.character_id IN ($placeholders)
                AND cg.is_current = 1
            ORDER BY g.guild_name
        ");
        $stmt->execute($character_ids);
        $guilds = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($guilds as &$guild) {
            $guild['items'] = [];
            $guild['item_count'] = 0;
            $guild['unique_items'] = 0;
            $items_by_name = [];
            $guild['type'] = 'guild'; // Mark as guild for frontend

            // Get latest guild bank snapshot
            $stmt = $pdo->prepare("
                SELECT id
                FROM guild_bank_snapshots
                WHERE guild_id = ?
                ORDER BY snapshot_ts DESC
                LIMIT 1
            ");
            $stmt->execute([$guild['guild_id']]);
            $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($snapshot) {
                // Get guild bank items from latest snapshot
                $stmt = $pdo->prepare("
                    SELECT 
                        tab_index,
                        tab_name,
                        slot_index,
                        item_id,
                        item_name,
                        item_link,
                        quality,
                        ilvl,
                        `count`,
                        icon
                    FROM guild_bank_items
                    WHERE snapshot_id = ?
                    ORDER BY tab_index, slot_index
                ");
                $stmt->execute([$snapshot['id']]);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $qualityInfo = getQualityInfo((int) ($row['quality'] ?? 1));
                    $tabName = $row['tab_name'] ?? 'Tab ' . $row['tab_index'];
                    $item = [
                        'item_id' => (int) $row['item_id'],
                        'name' => $row['item_name'],
                        'link' => $row['item_link'],
                        'count' => (int) $row['count'],
                        'ilvl' => (int) ($row['ilvl'] ?? 0),
                        'icon' => $row['icon'],
                        'quality' => (int) ($row['quality'] ?? 1),
                        'quality_name' => $qualityInfo['name'],
                        'quality_color' => $qualityInfo['color'],
                        'location' => 'Guild Bank',
                        'location_detail' => "{$tabName} (Slot {$row['slot_index']})"
                    ];

                    $items_by_name[$row['item_name']][] = $item;
                    $guild['item_count'] += (int) $row['count'];
                }
            }

            // Aggregate items by name
            foreach ($items_by_name as $name => $item_list) {
                $total_count = array_sum(array_column($item_list, 'count'));
                $locations = [];
                foreach ($item_list as $item) {
                    $locations[] = [
                        'location' => $item['location'],
                        'location_detail' => $item['location_detail'],
                        'count' => $item['count']
                    ];
                }

                // Use first item for base info
                $first_item = $item_list[0];
                $guild['items'][] = [
                    'item_id' => $first_item['item_id'],
                    'name' => $name,
                    'link' => $first_item['link'],
                    'total_count' => $total_count,
                    'ilvl' => $first_item['ilvl'],
                    'icon' => $first_item['icon'],
                    'quality' => $first_item['quality'],
                    'quality_name' => $first_item['quality_name'],
                    'quality_color' => $first_item['quality_color'],
                    'locations' => $locations
                ];
            }

            $guild['unique_items'] = count($guild['items']);

            // Sort items by name
            usort($guild['items'], function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }
        unset($guild);

        $payload['guilds'] = $guilds;
    }

    $payload['characters'] = $characters;

    // ============================================================================
    // SUMMARY
    // ============================================================================
    $payload['summary']['total_characters'] = count($characters);
    $payload['summary']['total_guilds'] = count($guilds);

    $all_items = [];
    $unique_items_set = [];

    foreach ($characters as $char) {
        $payload['summary']['total_items'] += count($char['items']);
        $payload['summary']['total_count'] += $char['item_count'];
        foreach ($char['items'] as $item) {
            $unique_items_set[$item['name']] = true;
        }
    }

    foreach ($guilds as $guild) {
        $payload['summary']['total_items'] += count($guild['items']);
        $payload['summary']['total_count'] += $guild['item_count'];
        foreach ($guild['items'] as $item) {
            $unique_items_set[$item['name']] = true;
        }
    }

    $payload['summary']['unique_items'] = count($unique_items_set);

    echo json_encode($payload, JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    error_log("Bazaar inventory error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error loading inventory data',
        'message' => $e->getMessage()
    ]);
}