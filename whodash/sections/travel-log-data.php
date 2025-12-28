<?php
// sections/travel-log-data.php
declare(strict_types=1);

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Don't display, we'll catch them

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
    // Journal Overview Stats
    // ============================================================================

    // Total unique zones visited (exclude unknown/empty)
    $stmt = $pdo->prepare('
      SELECT COUNT(DISTINCT zone) as unique_zones
      FROM series_zones
      WHERE character_id = ?
        AND zone IS NOT NULL 
        AND zone != ""
        AND zone != "Unknown"
    ');
    $stmt->execute([$cid]);
    $payload['unique_zones'] = (int) ($stmt->fetchColumn() ?? 0);

    // Total zone changes (exclude unknown/empty)
    $stmt = $pdo->prepare('
      SELECT COUNT(*) as total_changes
      FROM series_zones
      WHERE character_id = ?
        AND zone IS NOT NULL 
        AND zone != ""
        AND zone != "Unknown"
    ');
    $stmt->execute([$cid]);
    $payload['total_zone_changes'] = (int) ($stmt->fetchColumn() ?? 0);

    // Zone with most time spent (exclude unknown/empty)
    $stmt = $pdo->prepare('
      SELECT zone, COUNT(*) as visits
      FROM series_zones
      WHERE character_id = ?
        AND zone IS NOT NULL 
        AND zone != ""
        AND zone != "Unknown"
      GROUP BY zone
      ORDER BY visits DESC
      LIMIT 1
    ');
    $stmt->execute([$cid]);
    $topZone = $stmt->fetch(PDO::FETCH_ASSOC);
    $payload['favorite_zone'] = $topZone ? $topZone['zone'] : null;
    $payload['favorite_zone_visits'] = $topZone ? (int) $topZone['visits'] : 0;

    // Calculate "wanderlust index" (zone changes per hour played)
    $stmt = $pdo->prepare('
      SELECT SUM(total_time) as total_seconds
      FROM sessions
      WHERE character_id = ?
    ');
    $stmt->execute([$cid]);
    $totalSeconds = (int) ($stmt->fetchColumn() ?? 0);
    $hoursPlayed = $totalSeconds > 0 ? $totalSeconds / 3600.0 : 0;
    $payload['hours_played'] = round($hoursPlayed, 1);
    $payload['wanderlust_index'] = $hoursPlayed > 0
        ? round($payload['total_zone_changes'] / $hoursPlayed, 2)
        : 0;

    // Exploration Score (unique zones out of ~80 total WotLK zones)
    $totalWotlkZones = 80; // Approximate total explorable zones in 3.3.5a
    $payload['exploration_score'] = $payload['unique_zones'] > 0
        ? round(($payload['unique_zones'] / $totalWotlkZones) * 100, 1)
        : 0;

    // Dungeons/Raids Visited (count unique instance zones, exclude unknown)
    $stmt = $pdo->prepare('
      SELECT COUNT(DISTINCT zone) as dungeon_count
      FROM series_zones
      WHERE character_id = ?
        AND zone IS NOT NULL 
        AND zone != ""
        AND zone != "Unknown"
        AND (
          zone LIKE "%Citadel%" OR
          zone LIKE "%Sanctum%" OR
          zone LIKE "%Vault%" OR
          zone LIKE "%Chamber%" OR
          zone LIKE "%Trial%" OR
          zone LIKE "%Naxxramas%" OR
          zone LIKE "%Ulduar%" OR
          zone LIKE "%Ruby%" OR
          zone LIKE "%Obsidian%" OR
          zone LIKE "%Pit%" OR
          zone LIKE "%Nexus%" OR
          zone LIKE "%Keep%" OR
          zone LIKE "%Hall%" OR
          zone LIKE "%Violet Hold%" OR
          zone LIKE "%Forge%" OR
          zone LIKE "%Culling%" OR
          zone LIKE "%Utgarde%" OR
          zone LIKE "%Gundrak%" OR
          zone LIKE "%Azjol%" OR
          zone LIKE "%Ahn\'kahet%" OR
          zone LIKE "%Drak\'Tharon%"
        )
    ');
    $stmt->execute([$cid]);
    $payload['dungeons_visited'] = (int) ($stmt->fetchColumn() ?? 0);

    // ============================================================================
    // Zone Log (ALL visits with filtering support)
    // ============================================================================

    // Return ALL zone visits (not limited to 100) - filtering will be done client-side
    $stmt = $pdo->prepare('
      SELECT zone, subzone, ts
      FROM series_zones
      WHERE character_id = ?
        AND zone IS NOT NULL 
        AND zone != ""
        AND zone != "Unknown"
      ORDER BY ts DESC
    ');
    $stmt->execute([$cid]);
    $payload['recent_zones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================================
    // Zone Timeline Data (for broken line graph)
    // ============================================================================

    // Get daily zone distribution for timeline graph
    // Group by day and zone, count visits
    $stmt = $pdo->prepare('
      SELECT 
        DATE(FROM_UNIXTIME(ts)) as date,
        zone,
        COUNT(*) as visit_count
      FROM series_zones
      WHERE character_id = ?
        AND zone IS NOT NULL 
        AND zone != ""
        AND zone != "Unknown"
      GROUP BY DATE(FROM_UNIXTIME(ts)), zone
      ORDER BY date ASC, visit_count DESC
    ');
    $stmt->execute([$cid]);
    $payload['zone_timeline'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================================
    // Heatmap Data (Zone Time Spent, exclude unknown)
    // ============================================================================

    // Zone visit counts (exclude unknown/empty)
    $stmt = $pdo->prepare('
      SELECT zone, COUNT(*) as visit_count
      FROM series_zones
      WHERE character_id = ?
        AND zone IS NOT NULL 
        AND zone != ""
        AND zone != "Unknown"
      GROUP BY zone
      ORDER BY visit_count DESC
    ');
    $stmt->execute([$cid]);
    $payload['zone_heatmap'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate percentages
    $totalVisits = array_sum(array_column($payload['zone_heatmap'], 'visit_count'));
    if ($totalVisits > 0) {
        foreach ($payload['zone_heatmap'] as &$zone) {
            $zone['percentage'] = round(($zone['visit_count'] / $totalVisits) * 100, 1);
        }
        unset($zone); // Break reference
    }

    // ============================================================================
    // Zone Transitions (Most Common Routes, exclude unknown)
    // ============================================================================

    $stmt = $pdo->prepare('
      SELECT  
        z1.zone as from_zone, 
        z2.zone as to_zone, 
        COUNT(*) as transition_count 
      FROM series_zones z1 
      JOIN series_zones z2 ON z2.character_id = z1.character_id  
        AND z2.ts = ( 
          SELECT MIN(ts)  
          FROM series_zones  
          WHERE character_id = z1.character_id  
            AND ts > z1.ts 
        ) 
      WHERE z1.character_id = ? 
        AND z1.zone != z2.zone
        AND z1.zone IS NOT NULL AND z1.zone != "" AND z1.zone != "Unknown"
        AND z2.zone IS NOT NULL AND z2.zone != "" AND z2.zone != "Unknown"
      GROUP BY z1.zone, z2.zone 
      ORDER BY transition_count DESC 
      LIMIT 20
    ');
    $stmt->execute([$cid]);
    $payload['zone_transitions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================================
    // Subzone Breakdown (Top 20, both zone and subzone must be valid)
    // ============================================================================

    $stmt = $pdo->prepare('
      SELECT zone, subzone, COUNT(*) as visit_count
      FROM series_zones
      WHERE character_id = ? 
        AND zone IS NOT NULL AND zone != "" AND zone != "Unknown"
        AND subzone IS NOT NULL AND subzone != "" AND subzone != "Unknown"
      GROUP BY zone, subzone
      ORDER BY visit_count DESC
      LIMIT 20
    ');
    $stmt->execute([$cid]);
    $payload['subzone_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($payload, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    // Catch any errors and return as JSON
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}