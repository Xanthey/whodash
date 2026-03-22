<?php
// sections/reputation-data.php - PATCHED FOR PUBLIC VIEW
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Start session only if not in public view
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON header
header('Content-Type: application/json; charset=utf-8');

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

// Standing names (1-based indexing to match WoW API)
$standingNames = [
    1 => 'Hated',
    2 => 'Hostile',
    3 => 'Unfriendly',
    4 => 'Neutral',
    5 => 'Friendly',
    6 => 'Honored',
    7 => 'Revered',
    8 => 'Exalted'
];

// Standing colors (1-based indexing to match WoW API)
$standingColors = [
    1 => '#CC0000', // Hated - Dark Red
    2 => '#FF0000', // Hostile - Red
    3 => '#EE6622', // Unfriendly - Orange
    4 => '#FFCC00', // Neutral - Yellow
    5 => '#00FF00', // Friendly - Green
    6 => '#00FF88', // Honored - Light Green
    7 => '#00FFCC', // Revered - Cyan
    8 => '#FF00FF'  // Exalted - Purple
];

try {
    // Get all reputation data for this character
    $stmt = $pdo->prepare('
        SELECT 
            faction_name,
            standing_id,
            value,
            min,
            max,
            ts
        FROM series_reputation
        WHERE character_id = ?
        ORDER BY ts DESC, faction_name ASC
    ');
    $stmt->execute([$character_id]);
    $allReps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by faction to get current and historical data
    $factionData = [];
    $factionHistory = [];

    foreach ($allReps as $row) {
        $faction = $row['faction_name'];

        // Store historical data
        if (!isset($factionHistory[$faction])) {
            $factionHistory[$faction] = [];
        }
        $factionHistory[$faction][] = [
            'ts' => (int) $row['ts'],
            'standing_id' => (int) $row['standing_id'],
            'value' => (int) $row['value'],
            'min' => (int) $row['min'],
            'max' => (int) $row['max']
        ];

        // First entry (most recent) becomes current standing
        if (!isset($factionData[$faction])) {
            $standing_id = (int) $row['standing_id'];
            $value = (int) $row['value'];
            $min = (int) $row['min'];
            $max = (int) $row['max'];

            // Calculate progress within current standing
            $range = $max - $min;
            $current = $value - $min;
            $progress = $range > 0 ? ($current / $range) * 100 : 0;

            $factionData[$faction] = [
                'faction_name' => $faction,
                'standing_id' => $standing_id,
                'standing_name' => $standingNames[$standing_id] ?? 'Unknown',
                'standing_color' => $standingColors[$standing_id] ?? '#999999',
                'value' => $value,
                'min' => $min,
                'max' => $max,
                'progress' => round($progress, 1),
                'is_exalted' => $standing_id === 8,
                'last_updated' => (int) $row['ts']
            ];
        }
    }

    // Calculate change over time for each faction
    foreach ($factionData as $faction => $data) {
        $history = $factionHistory[$faction] ?? [];

        if (count($history) >= 2) {
            $latest = $history[0];
            $previous = $history[1];

            $valueDiff = $latest['value'] - $previous['value'];
            $standingDiff = $latest['standing_id'] - $previous['standing_id'];
            $timeDiff = $latest['ts'] - $previous['ts'];

            $factionData[$faction]['recent_change'] = $valueDiff;
            $factionData[$faction]['standing_change'] = $standingDiff;
            $factionData[$faction]['change_time_ago'] = $timeDiff;

            if ($valueDiff > 0) {
                $factionData[$faction]['trend'] = 'up';
            } elseif ($valueDiff < 0) {
                $factionData[$faction]['trend'] = 'down';
            } else {
                $factionData[$faction]['trend'] = 'stable';
            }
        } else {
            $factionData[$faction]['recent_change'] = 0;
            $factionData[$faction]['standing_change'] = 0;
            $factionData[$faction]['change_time_ago'] = 0;
            $factionData[$faction]['trend'] = 'stable';
        }

        // Add full history
        $factionData[$faction]['history'] = $history;
    }

    // Calculate summary statistics
    $totalFactions = count($factionData);
    $exaltedCount = 0;
    $reveredCount = 0;
    $honoredCount = 0;
    $friendlyCount = 0;
    $neutralOrWorse = 0;

    // Initialize 0-indexed array for JSON serialization
    $standingDistribution = [0, 0, 0, 0, 0, 0, 0, 0];

    foreach ($factionData as $data) {
        $standing = isset($data['standing_id']) ? (int) $data['standing_id'] : 4;

        // Populate distribution array (standing_id 1-8 maps to index 0-7)
        if ($standing >= 1 && $standing <= 8) {
            $standingDistribution[$standing - 1]++;
        }

        // Count by standing level (1-based standing IDs)
        if ($standing === 8) {
            $exaltedCount++;
        } elseif ($standing === 7) {
            $reveredCount++;
        } elseif ($standing === 6) {
            $honoredCount++;
        } elseif ($standing === 5) {
            $friendlyCount++;
        } elseif ($standing <= 4) {
            $neutralOrWorse++;
        }
    }

    // Three-tier sorting:
// 1. Recent changes (by gain amount)
// 2. No changes (by total rep)
// 3. Hostile at bottom
    uasort($factionData, function ($a, $b) {
        // Priority 1: Factions with recent changes
        $aHasChange = ($a['recent_change'] ?? 0) != 0;
        $bHasChange = ($b['recent_change'] ?? 0) != 0;

        if ($aHasChange && !$bHasChange)
            return -1;
        if (!$aHasChange && $bHasChange)
            return 1;

        // Both have changes: sort by gain (highest first)
        if ($aHasChange && $bHasChange) {
            return $b['recent_change'] - $a['recent_change'];
        }

        // Priority 2: Hostile factions go to bottom
        $aIsHostile = $a['standing_id'] <= 2;
        $bIsHostile = $b['standing_id'] <= 2;

        if ($aIsHostile && !$bIsHostile)
            return 1;
        if (!$aIsHostile && $bIsHostile)
            return -1;

        // Priority 3: Sort by total rep value
        return $b['value'] - $a['value'];
    });

    // Build response
    $response = [
        'success' => true,
        'summary' => [
            'total_factions' => $totalFactions,
            'exalted' => $exaltedCount,
            'revered' => $reveredCount,
            'honored' => $honoredCount,
            'friendly' => $friendlyCount,
            'neutral_or_worse' => $neutralOrWorse,
            'exalted_percentage' => $totalFactions > 0 ? round(($exaltedCount / $totalFactions) * 100, 1) : 0,
            'standing_distribution' => $standingDistribution
        ],
        'factions' => array_values($factionData),
        'standing_names' => $standingNames,
        'standing_colors' => $standingColors
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to load reputation data',
        'message' => $e->getMessage()
    ]);
}