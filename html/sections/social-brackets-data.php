<?php
/* WhoDASH Social Brackets Data API */
require_once __DIR__ . '/../db.php';

// Start session first, before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_GET['character_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing character_id']);
    exit;
}

$character_id = (int) $_GET['character_id'];

// Validate ownership OR public access
$character = null;

// First try: owned character (authenticated)
if (isset($_SESSION['user_id'])) {
    $own = $pdo->prepare('
        SELECT id, name, guild_name, class_file, class_local, race, race_file, sex, faction, realm 
        FROM characters 
        WHERE id = ? AND user_id = ?
    ');
    $own->execute([$character_id, $_SESSION['user_id']]);
    $character = $own->fetch(PDO::FETCH_ASSOC);
}

// Second try: public character (no authentication required)
if (!$character) {
    $pub = $pdo->prepare('
        SELECT id, name, guild_name, class_file, class_local, race, race_file, sex, faction, realm 
        FROM characters 
        WHERE id = ? AND visibility = "PUBLIC"
    ');
    $pub->execute([$character_id]);
    $character = $pub->fetch(PDO::FETCH_ASSOC);
}

// If neither worked, deny access
if (!$character) {
    http_response_code(403);
    echo json_encode(['error' => 'Character not found or not accessible']);
    exit;
}

try {
    $data = [];

    // ===== DUNGEON & RAID GROUPS =====
    // Groups formed in instances (dungeons/raids)
    $stmt = $pdo->prepare("
        SELECT 
            ts,
            type,
            size,
            instance,
            instance_difficulty,
            zone,
            subzone,
            members
        FROM group_compositions 
        WHERE character_id = ? 
        AND instance IS NOT NULL 
        AND instance != ''
        ORDER BY ts DESC
        LIMIT 200
    ");
    $stmt->execute([$character_id]);
    $dungeonGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each group and decode members
    foreach ($dungeonGroups as &$group) {
        if ($group['members']) {
            $decoded = json_decode($group['members'], true);
            $group['members'] = is_array($decoded) ? $decoded : [];

            // Try to determine roles based on class if not provided
            foreach ($group['members'] as &$member) {
                if (empty($member['role']) && !empty($member['class'])) {
                    $member['role'] = inferRoleFromClass($member['class']);
                }
            }
        } else {
            $group['members'] = [];
        }
    }

    $data['dungeon_groups'] = $dungeonGroups;

    // ===== MISCELLANEOUS PARTIES =====
    // Groups formed outside instances (world groups, manual parties)
    $stmt = $pdo->prepare("
        SELECT 
            ts,
            type,
            size,
            zone,
            subzone,
            members
        FROM group_compositions 
        WHERE character_id = ? 
        AND (instance IS NULL OR instance = '')
        ORDER BY ts DESC
        LIMIT 200
    ");
    $stmt->execute([$character_id]);
    $miscGroups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process each group and decode members
    foreach ($miscGroups as &$group) {
        if ($group['members']) {
            $decoded = json_decode($group['members'], true);
            $group['members'] = is_array($decoded) ? $decoded : [];

            // Try to determine roles based on class if not provided
            foreach ($group['members'] as &$member) {
                if (empty($member['role']) && !empty($member['class'])) {
                    $member['role'] = inferRoleFromClass($member['class']);
                }
            }
        } else {
            $group['members'] = [];
        }
    }

    $data['misc_groups'] = $miscGroups;

    // Output JSON
    echo json_encode($data, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log("Social brackets data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

// Helper function to infer role from class
function inferRoleFromClass($class)
{
    $tankClasses = ['WARRIOR', 'PALADIN', 'DEATHKNIGHT', 'DRUID'];
    $healerClasses = ['PRIEST', 'PALADIN', 'SHAMAN', 'DRUID'];

    $class = strtoupper($class);

    // Note: Some classes can fill multiple roles, so this is just a best guess
    // In a real implementation, you'd want to store the actual role data
    if (in_array($class, $tankClasses)) {
        // For multi-role classes, default to DPS unless we have better logic
        if ($class === 'WARRIOR' || $class === 'DEATHKNIGHT')
            return 'tank';
        return 'dps'; // Paladin/Druid could be anything
    }

    if (in_array($class, $healerClasses)) {
        if ($class === 'PRIEST')
            return 'healer';
        return 'dps'; // Multi-role classes default to DPS
    }

    return 'dps';
}
?>