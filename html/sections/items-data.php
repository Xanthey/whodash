<?php
// sections/items-data.php - Backend for Items/Inventory page
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');



// Get character_id
$character_id = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

if (!$character_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No character_id provided']);
    exit;
}

// Verify ownership OR public access
$character = null;

// First try: owned character (authenticated user)
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT id, realm, name, faction, class_file, visibility FROM characters WHERE id = ? AND user_id = ?');
    $stmt->execute([$character_id, $_SESSION['user_id']]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Second try: public character (no authentication required)
if (!$character) {
    $stmt = $pdo->prepare('SELECT id, realm, name, faction, class_file, visibility FROM characters WHERE id = ? AND visibility = "PUBLIC"');
    $stmt->execute([$character_id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$character) {
    http_response_code(403);
    echo json_encode(['error' => 'Character not found or not accessible']);
    exit;
}

// Initialize payload
$payload = [
    'vault_stats' => [
        'total_items' => 0,
        'total_value' => 0,
        'unique_items' => 0,
        'by_quality' => [],
        'by_type' => [],
        'most_valuable' => [],
        'rarest_items' => [],
    ],
    'bag_equipment' => [],
    'bags' => [],
    'bank_equipment' => [],
    'bank' => [],
    'mail_messages' => [],
    'mail' => [],
    'combined' => [],
];

// Helper function to get quality name
function getQualityName($quality)
{
    $map = [
        0 => 'Poor',
        1 => 'Common',
        2 => 'Uncommon',
        3 => 'Rare',
        4 => 'Epic',
        5 => 'Legendary',
    ];
    return $map[$quality] ?? 'Unknown';
}

// ============================================================================
// BAG EQUIPMENT (the bags themselves equipped in bag slots)
// ============================================================================
try {
    // Check equipment_snapshot for bag items (slots like "Bag0Slot" to "Bag4Slot")
    $stmt = $pdo->prepare('
        SELECT 
            slot_name,
            item_id,
            name,
            link,
            icon,
            ilvl,
            count
        FROM equipment_snapshot
        WHERE character_id = :cid
        AND slot_name LIKE "Bag%Slot"
        ORDER BY slot_name
    ');
    $stmt->execute([':cid' => $character_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        // Extract bag number from slot name (e.g., "Bag0Slot" -> 0)
        preg_match('/Bag(\d+)Slot/', $row['slot_name'], $matches);
        $bagNum = isset($matches[1]) ? (int) $matches[1] : 0;

        // Get the max slot number for this bag to determine bag size
        $slotStmt = $pdo->prepare('
            SELECT MAX(slot) as max_slot
            FROM containers_bag
            WHERE character_id = :cid AND bag_id = :bag_id
        ');
        $slotStmt->execute([':cid' => $character_id, ':bag_id' => $bagNum]);
        $slotResult = $slotStmt->fetch(PDO::FETCH_ASSOC);
        $slots = $slotResult && $slotResult['max_slot'] !== null ? (int) $slotResult['max_slot'] + 1 : 0;

        $payload['bag_equipment'][] = [
            'bag_id' => $bagNum,
            'slot_name' => $row['slot_name'],
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'],
            'link' => $row['link'],
            'icon' => $row['icon'],
            'ilvl' => (int) ($row['ilvl'] ?? 0),
            'slots' => $slots,
        ];
    }
} catch (Throwable $e) {
    error_log("Bag equipment error: " . $e->getMessage());
}

// ============================================================================
// BAGS DATA
// ============================================================================
try {
    $stmt = $pdo->prepare('
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
        WHERE character_id = :cid
        ORDER BY bag_id, slot
    ');
    $stmt->execute([':cid' => $character_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['bags'][] = [
            'bag_id' => (int) $row['bag_id'],
            'slot' => (int) $row['slot'],
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'],
            'link' => $row['link'],
            'count' => (int) $row['count'],
            'ilvl' => (int) ($row['ilvl'] ?? 0),
            'icon' => $row['icon'],
            'quality' => (int) ($row['quality'] ?? 1),
            'quality_name' => getQualityName((int) ($row['quality'] ?? 1)),
            'location' => 'Bags',
        ];
    }
} catch (Throwable $e) {
    error_log("Bags data error: " . $e->getMessage());
}

// ============================================================================
// BANK EQUIPMENT (the bank bags themselves)
// ============================================================================
try {
    // Bank bags are stored in equipment_snapshot slots "BankSlot1" through "BankSlot7"
    $stmt = $pdo->prepare('
        SELECT 
            slot_name,
            item_id,
            name,
            link,
            icon,
            ilvl,
            count
        FROM equipment_snapshot
        WHERE character_id = :cid
        AND slot_name LIKE "BankSlot%"
        ORDER BY slot_name
    ');
    $stmt->execute([':cid' => $character_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        // Extract bank slot number (e.g., "BankSlot1" -> 1, maps to bag_id 5)
        preg_match('/BankSlot(\d+)/', $row['slot_name'], $matches);
        $slotNum = isset($matches[1]) ? (int) $matches[1] : 0;
        $bagId = $slotNum + 4; // BankSlot1 = bag_id 5, BankSlot2 = bag_id 6, etc.

        // Get the max slot number for this bank bag to determine bag size
        $slotStmt = $pdo->prepare('
            SELECT MAX(inv_slot) as max_slot
            FROM containers_bank
            WHERE character_id = :cid AND bag_id = :bag_id
        ');
        $slotStmt->execute([':cid' => $character_id, ':bag_id' => $bagId]);
        $slotResult = $slotStmt->fetch(PDO::FETCH_ASSOC);
        $slots = $slotResult && $slotResult['max_slot'] !== null ? (int) $slotResult['max_slot'] + 1 : 0;

        $payload['bank_equipment'][] = [
            'bag_id' => $bagId,
            'slot_name' => $row['slot_name'],
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'],
            'link' => $row['link'],
            'icon' => $row['icon'],
            'ilvl' => (int) ($row['ilvl'] ?? 0),
            'slots' => $slots,
        ];
    }

    // Also get the main bank slot count (bag_id = -1)
    $mainBankStmt = $pdo->prepare('
        SELECT MAX(inv_slot) as max_slot
        FROM containers_bank
        WHERE character_id = :cid AND bag_id = -1
    ');
    $mainBankStmt->execute([':cid' => $character_id]);
    $mainBankResult = $mainBankStmt->fetch(PDO::FETCH_ASSOC);
    $mainBankSlots = $mainBankResult && $mainBankResult['max_slot'] !== null ? (int) $mainBankResult['max_slot'] + 1 : 28; // Default to 28 slots

    // Add main bank as a special entry at the beginning
    if ($mainBankSlots > 0) {
        array_unshift($payload['bank_equipment'], [
            'bag_id' => -1,
            'slot_name' => 'MainBank',
            'item_id' => 0,
            'name' => 'Main Bank',
            'link' => '',
            'icon' => '',
            'ilvl' => 0,
            'slots' => $mainBankSlots,
        ]);
    }
} catch (Throwable $e) {
    error_log("Bank equipment error: " . $e->getMessage());
}

// ============================================================================
// BANK DATA (contents of bank containers)
// ============================================================================
try {
    $stmt = $pdo->prepare('
        SELECT 
            bag_id,
            inv_slot,
            container_name,
            item_id,
            name,
            link,
            count,
            ilvl,
            icon,
            quality
        FROM containers_bank
        WHERE character_id = :cid
        ORDER BY bag_id, inv_slot
    ');
    $stmt->execute([':cid' => $character_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['bank'][] = [
            'bag_id' => (int) $row['bag_id'],
            'slot' => (int) $row['inv_slot'],
            'container_name' => $row['container_name'] ?? 'Bank',
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'],
            'link' => $row['link'],
            'count' => (int) $row['count'],
            'ilvl' => (int) ($row['ilvl'] ?? 0),
            'icon' => $row['icon'],
            'quality' => (int) ($row['quality'] ?? 1),
            'quality_name' => getQualityName((int) ($row['quality'] ?? 1)),
            'location' => 'Bank',
        ];
    }
} catch (Throwable $e) {
    error_log("Bank data error: " . $e->getMessage());
}

// ============================================================================
// MAIL MESSAGES (full mail data, not just attachments)
// ============================================================================
try {
    $stmt = $pdo->prepare('
        SELECT 
            id,
            mail_index,
            sender,
            subject,
            money,
            cod,
            days_left,
            was_read,
            is_auction,
            package_icon,
            stationery_icon
        FROM mailbox
        WHERE character_id = :cid
        ORDER BY mail_index
    ');
    $stmt->execute([':cid' => $character_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['mail_messages'][] = [
            'mailbox_id' => (int) $row['id'],
            'mail_index' => (int) $row['mail_index'],
            'sender' => $row['sender'],
            'subject' => $row['subject'],
            'money' => (int) $row['money'],
            'cod' => (int) $row['cod'],
            'days_left' => (float) $row['days_left'],
            'was_read' => (bool) $row['was_read'],
            'is_auction' => (bool) $row['is_auction'],
            'package_icon' => $row['package_icon'],
            'stationery_icon' => $row['stationery_icon'],
            'attachments' => [], // Will be populated below
        ];
    }

    // Now fetch attachments for each mail
    if (count($payload['mail_messages']) > 0) {
        $mailIds = array_column($payload['mail_messages'], 'mailbox_id');
        $placeholders = implode(',', array_fill(0, count($mailIds), '?'));

        $stmt = $pdo->prepare("
            SELECT 
                mailbox_id,
                a_index,
                item_id,
                name,
                link,
                count,
                ilvl,
                icon
            FROM mailbox_attachments
            WHERE mailbox_id IN ($placeholders)
            ORDER BY mailbox_id, a_index
        ");
        $stmt->execute($mailIds);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $attach) {
            // Find the corresponding mail message
            foreach ($payload['mail_messages'] as &$mail) {
                if ($mail['mailbox_id'] === (int) $attach['mailbox_id']) {
                    $mail['attachments'][] = [
                        'a_index' => (int) $attach['a_index'],
                        'item_id' => (int) $attach['item_id'],
                        'name' => $attach['name'],
                        'link' => $attach['link'],
                        'count' => (int) $attach['count'],
                        'ilvl' => (int) ($attach['ilvl'] ?? 0),
                        'icon' => $attach['icon'],
                    ];
                    break;
                }
            }
        }
    }
} catch (Throwable $e) {
    error_log("Mail messages error: " . $e->getMessage());
}

// ============================================================================
// MAIL DATA (Attachments - for backwards compatibility)
// ============================================================================
try {
    $stmt = $pdo->prepare('
        SELECT 
            ma.item_id,
            ma.name,
            ma.link,
            ma.count,
            ma.ilvl,
            ma.icon,
            m.sender,
            m.subject
        FROM mailbox_attachments ma
        JOIN mailbox m ON m.id = ma.mailbox_id
        WHERE m.character_id = :cid
        ORDER BY m.mail_index, ma.a_index
    ');
    $stmt->execute([':cid' => $character_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['mail'][] = [
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'],
            'link' => $row['link'],
            'count' => (int) $row['count'],
            'ilvl' => (int) ($row['ilvl'] ?? 0),
            'icon' => $row['icon'],
            'quality' => 1, // Mail doesn't store quality
            'quality_name' => 'Common',
            'sender' => $row['sender'],
            'subject' => $row['subject'],
            'location' => 'Mail',
        ];
    }
} catch (Throwable $e) {
    error_log("Mail data error: " . $e->getMessage());
}

// ============================================================================
// COMBINED DATA (All items aggregated)
// ============================================================================
$payload['combined'] = array_merge($payload['bags'], $payload['bank'], $payload['mail']);

// ============================================================================
// VAULT STATS
// ============================================================================
try {
    $allItems = $payload['combined'];

    // Total items
    $payload['vault_stats']['total_items'] = array_sum(array_column($allItems, 'count'));

    // Unique items
    $payload['vault_stats']['unique_items'] = count(array_unique(array_column($allItems, 'name')));

    // By location
    $locationCounts = [];
    foreach ($allItems as $item) {
        $location = $item['location'];
        if (!isset($locationCounts[$location])) {
            $locationCounts[$location] = 0;
        }
        $locationCounts[$location] += $item['count'];
    }

    $payload['vault_stats']['by_location'] = [];
    foreach ($locationCounts as $location => $count) {
        $payload['vault_stats']['by_location'][] = [
            'location' => $location,
            'count' => $count,
        ];
    }

    // By quality
    $qualityCounts = [];
    foreach ($allItems as $item) {
        $quality = $item['quality_name'];
        if (!isset($qualityCounts[$quality])) {
            $qualityCounts[$quality] = 0;
        }
        $qualityCounts[$quality] += $item['count'];
    }

    foreach ($qualityCounts as $quality => $count) {
        $payload['vault_stats']['by_quality'][] = [
            'quality' => $quality,
            'count' => $count,
        ];
    }

    // By type (placeholder - could categorize by item type if available)
    $payload['vault_stats']['by_type'][] = [
        'type' => 'All Items',
        'count' => $payload['vault_stats']['total_items'],
    ];

    // Most valuable (by item level)
    usort($allItems, function ($a, $b) {
        return $b['ilvl'] - $a['ilvl'];
    });
    $payload['vault_stats']['most_valuable'] = array_slice(array_map(function ($item) {
        return [
            'name' => $item['name'],
            'ilvl' => $item['ilvl'],
            'quality' => $item['quality_name'],
            'location' => $item['location'],
        ];
    }, $allItems), 0, 10);

    // Rarest items (Epic and Legendary)
    $rareItems = array_filter($allItems, function ($item) {
        return in_array($item['quality'], [4, 5]); // Epic or Legendary
    });
    $payload['vault_stats']['rarest_items'] = array_slice(array_map(function ($item) {
        return [
            'name' => $item['name'],
            'quality' => $item['quality_name'],
            'ilvl' => $item['ilvl'],
            'location' => $item['location'],
        ];
    }, $rareItems), 0, 10);

    // Largest stacks
    usort($allItems, function ($a, $b) {
        return $b['count'] - $a['count'];
    });
    $payload['vault_stats']['largest_stacks'] = array_slice(array_map(function ($item) {
        return [
            'name' => $item['name'],
            'count' => $item['count'],
            'quality' => $item['quality_name'],
            'location' => $item['location'],
        ];
    }, $allItems), 0, 10);

    // Stack statistics
    $stackCounts = [];
    foreach ($allItems as $item) {
        $name = $item['name'];
        if (!isset($stackCounts[$name])) {
            $stackCounts[$name] = ['total' => 0, 'stacks' => 0];
        }
        $stackCounts[$name]['total'] += $item['count'];
        $stackCounts[$name]['stacks']++;
    }

    $totalStacks = array_sum(array_column($stackCounts, 'stacks'));
    $maxStack = 0;
    $totalItems = 0;
    foreach ($stackCounts as $counts) {
        $maxStack = max($maxStack, $counts['total']);
        $totalItems += $counts['total'];
    }
    $avgStack = $totalStacks > 0 ? round($totalItems / $totalStacks, 1) : 0;

    $payload['vault_stats']['stack_stats'] = [
        'total_stacks' => $totalStacks,
        'max_stack' => $maxStack,
        'avg_stack' => $avgStack,
    ];

} catch (Throwable $e) {
    error_log("Vault stats error: " . $e->getMessage());
}

// Emit payload
echo json_encode($payload);