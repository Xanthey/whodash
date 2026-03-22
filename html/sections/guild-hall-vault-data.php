<?php
// sections/guild-hall-vault-data.php
// Provides guild bank vault data for Guild Hall

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../guild_helpers.php';

header('Content-Type: application/json');
session_start();

// Ensure database connection is available
if (!isset($pdo)) {
    require_once __DIR__ . '/../db.php';
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$guild_id = $_GET['guild_id'] ?? null;
if (!$guild_id) {
    http_response_code(400);
    echo json_encode(['error' => 'guild_id parameter required']);
    exit;
}

try {

    // Verify user has access to this guild
    $stmt = $pdo->prepare("
        SELECT 1 
        FROM character_guilds cg
        INNER JOIN characters c ON cg.character_id = c.id
        WHERE c.user_id = ? AND cg.guild_id = ? AND cg.is_current = TRUE
        LIMIT 1
    ");
    $stmt->execute([$user_id, $guild_id]);

    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this guild']);
        exit;
    }

    $payload = [
        'tabs' => []
    ];

    // Get most recent guild bank snapshot
    $stmt = $pdo->prepare("
        SELECT id, snapshot_ts, num_tabs
        FROM guild_bank_snapshots
        WHERE guild_id = ?
        ORDER BY snapshot_ts DESC
        LIMIT 1
    ");
    $stmt->execute([$guild_id]);
    $snapshot = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$snapshot) {
        // No bank data available
        echo json_encode($payload);
        exit;
    }

    $snapshot_id = $snapshot['id'];
    $num_tabs = $snapshot['num_tabs'] ?? 0;

    // Get all items for this snapshot, grouped by tab
    $stmt = $pdo->prepare("
        SELECT 
            tab_index,
            tab_name,
            tab_icon,
            slot_index,
            item_id,
            item_name,
            item_link,
            quality,
            ilvl,
            `count`,
            icon,
            locked
        FROM guild_bank_items
        WHERE snapshot_id = ?
        ORDER BY tab_index, slot_index
    ");
    $stmt->execute([$snapshot_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group items by tab
    $tabs = [];
    foreach ($items as $item) {
        $tab_index = (int) $item['tab_index'];

        if (!isset($tabs[$tab_index])) {
            // Use stored tab icon if available, otherwise use default
            $tabIconName = null;
            if (!empty($item['tab_icon'])) {
                // Already in correct format from database (e.g., "inv_misc_head_elf_02")
                $tabIconName = $item['tab_icon'];
            } else {
                // Fallback to default based on tab index
                $tabIconName = getDefaultTabIconName($tab_index);
            }

            $tabs[$tab_index] = [
                'index' => $tab_index,
                'name' => $item['tab_name'] ?? "Tab " . $tab_index,
                'icon' => '/icon.php?type=item&name=' . urlencode($tabIconName) . '&size=medium',
                'items' => []
            ];
        }

        // Extract icon name from texture path if present
        $iconName = extractIconName($item['icon']);

        // Build icon URL using icon.php
        $itemIconUrl = '/icon.php?type=item&size=medium';
        if (!empty($item['item_id'])) {
            // Prefer using item ID
            $itemIconUrl .= '&id=' . $item['item_id'];
        } elseif (!empty($iconName)) {
            // Fallback to icon name
            $itemIconUrl .= '&name=' . urlencode($iconName);
        } else {
            // Ultimate fallback
            $itemIconUrl = '/icon.php?type=item&name=inv_misc_questionmark&size=medium';
        }

        $tabs[$tab_index]['items'][] = [
            'slot' => (int) $item['slot_index'],
            'item_id' => (int) $item['item_id'],
            'name' => $item['item_name'],
            'link' => $item['item_link'],
            'quality' => (int) $item['quality'],
            'ilvl' => (int) $item['ilvl'],
            'count' => (int) $item['count'],
            'icon' => $itemIconUrl,
            'locked' => (bool) $item['locked']
        ];
    }

    // Ensure we have entries for all tabs (even empty ones)
    for ($i = 1; $i <= $num_tabs; $i++) {
        if (!isset($tabs[$i])) {
            $tabIconName = getDefaultTabIconName($i);
            $tabs[$i] = [
                'index' => $i,
                'name' => "Tab " . $i,
                'icon' => '/icon.php?type=item&name=' . urlencode($tabIconName) . '&size=medium',
                'items' => []
            ];
        }
    }

    // Sort tabs by index and reindex to 0-based array for JSON
    ksort($tabs);
    $payload['tabs'] = array_values($tabs);

    echo json_encode($payload);

} catch (PDOException $e) {
    error_log("Vault data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

/**
 * Extract icon name from WoW texture path
 * Converts "Interface\\Icons\\INV_Pants_11" to "inv_pants_11"
 */
function extractIconName($iconPath)
{
    if (empty($iconPath)) {
        return null;
    }

    // Extract filename from path (last part after backslashes)
    if (preg_match('/\\\\([^\\\\]+)$/', $iconPath, $matches)) {
        return strtolower($matches[1]);
    }

    // If no backslashes, might already be just the name
    if (!str_contains($iconPath, '\\')) {
        return strtolower($iconPath);
    }

    return null;
}

/**
 * Get default tab icon name based on tab index
 * WoW uses standard bag icons for guild bank tabs
 */
function getDefaultTabIconName($tabIndex)
{
    $icons = [
        1 => 'inv_misc_bag_07',
        2 => 'inv_misc_bag_09',
        3 => 'inv_misc_bag_10',
        4 => 'inv_misc_bag_11',
        5 => 'inv_misc_bag_12',
        6 => 'inv_misc_bag_13',
        7 => 'inv_misc_bag_14',
        8 => 'inv_misc_bag_15',
    ];

    return $icons[$tabIndex] ?? 'inv_misc_bag_07';
}