<?php
// sections/items-data.php - Backend for Items/Inventory page
declare(strict_types=1);

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
$own->execute([$cid, $_SESSION['user_id'] ?? null]);
$character = $own->fetch(PDO::FETCH_ASSOC);
if (!$character) {
    http_response_code(403);
    echo json_encode(['error' => 'Character not found or not yours']);
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
    'bags' => [],
    'bank' => [],
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
    $stmt->execute([':cid' => $cid]);

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
// BANK DATA
// ============================================================================
try {
    $stmt = $pdo->prepare('
        SELECT 
            inv_slot,
            item_id,
            name,
            link,
            count,
            ilvl,
            icon
        FROM containers_bank
        WHERE character_id = :cid
        ORDER BY inv_slot
    ');
    $stmt->execute([':cid' => $cid]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['bank'][] = [
            'slot' => (int) $row['inv_slot'],
            'item_id' => (int) $row['item_id'],
            'name' => $row['name'],
            'link' => $row['link'],
            'count' => (int) $row['count'],
            'ilvl' => (int) ($row['ilvl'] ?? 0),
            'icon' => $row['icon'],
            'quality' => 1, // Bank doesn't store quality, default to common
            'quality_name' => 'Common',
            'location' => 'Bank',
        ];
    }
} catch (Throwable $e) {
    error_log("Bank data error: " . $e->getMessage());
}

// ============================================================================
// MAIL DATA (Attachments)
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
    $stmt->execute([':cid' => $cid]);

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

} catch (Throwable $e) {
    error_log("Vault stats error: " . $e->getMessage());
}

// Emit payload
echo json_encode($payload);