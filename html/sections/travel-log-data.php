<?php
// sections/travel-log-data.php
declare(strict_types=1);

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

try {
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

  $payload = [];

  // zone_log_only=1 is set by the JS load-more buttons — skip all the expensive
  // stats/timeline/heatmap queries and only return recent_zones + total_zone_rows
  $zoneLogOnly = isset($_GET['zone_log_only']) && (int) $_GET['zone_log_only'] === 1;

  if ($zoneLogOnly) {
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 2000;
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $hasSearch = $search !== '';

    if ($hasSearch) {
      // Full-history search — ignore limit, filter by zone/subzone LIKE
      $like = '%' . $search . '%';
      $stmt = $pdo->prepare("
              SELECT zone, subzone, ts
              FROM series_zones
              WHERE character_id = ?
                AND zone IS NOT NULL AND zone != '' AND zone != 'Unknown'
                AND (zone LIKE ? OR subzone LIKE ?)
              ORDER BY ts DESC
            ");
      $stmt->execute([$character_id, $like, $like]);
      $payload['recent_zones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $payload['recent_zones_limit'] = 0; // all matching rows
      $payload['search_active'] = true;
      $payload['search_term'] = $search;
      $payload['search_result_count'] = count($payload['recent_zones']);
    } else {
      // Normal chunk load
      $limitSql = $limit > 0 ? "LIMIT $limit" : '';
      $stmt = $pdo->prepare("
              SELECT zone, subzone, ts
              FROM series_zones
              WHERE character_id = ?
                AND zone IS NOT NULL AND zone != '' AND zone != 'Unknown'
              ORDER BY ts DESC
              $limitSql
            ");
      $stmt->execute([$character_id]);
      $payload['recent_zones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
      $payload['recent_zones_limit'] = $limit;
      $payload['search_active'] = false;
    }

    // Always include the full unfiltered count
    $stmt = $pdo->prepare("
          SELECT COUNT(*) FROM series_zones
          WHERE character_id = ?
            AND zone IS NOT NULL AND zone != '' AND zone != 'Unknown'
        ");
    $stmt->execute([$character_id]);
    $payload['total_zone_rows'] = (int) $stmt->fetchColumn();

    echo json_encode($payload);
    exit;
  }

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
  $stmt->execute([$character_id]);
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
  $stmt->execute([$character_id]);
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
  $stmt->execute([$character_id]);
  $topZone = $stmt->fetch(PDO::FETCH_ASSOC);
  $payload['favorite_zone'] = $topZone ? $topZone['zone'] : null;
  $payload['favorite_zone_visits'] = $topZone ? (int) $topZone['visits'] : 0;

  // Calculate "wanderlust index" (zone changes per hour played)
  $stmt = $pdo->prepare('
      SELECT SUM(total_time) as total_seconds
      FROM sessions
      WHERE character_id = ?
    ');
  $stmt->execute([$character_id]);
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
  $stmt->execute([$character_id]);
  $payload['dungeons_visited'] = (int) ($stmt->fetchColumn() ?? 0);

  // ============================================================================
  // Zone Log (ALL visits with filtering support)
  // ============================================================================

  // Limit: default 2000, pass limit=0 for all rows (slow — user's choice)
  $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 2000;
  $limitSql = $limit > 0 ? "LIMIT $limit" : '';

  // Return zone visits up to the requested limit
  $stmt = $pdo->prepare("
      SELECT zone, subzone, ts
      FROM series_zones
      WHERE character_id = ?
        AND zone IS NOT NULL 
        AND zone != ''
        AND zone != 'Unknown'
      ORDER BY ts DESC
      $limitSql
    ");
  $stmt->execute([$character_id]);
  $payload['recent_zones'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $payload['recent_zones_limit'] = $limit; // 0 = all was requested

  // Total qualifying rows (for "X of Y" display without fetching everything)
  $stmt = $pdo->prepare('
      SELECT COUNT(*) FROM series_zones
      WHERE character_id = ?
        AND zone IS NOT NULL AND zone != \'\' AND zone != \'Unknown\'
    ');
  $stmt->execute([$character_id]);
  $payload['total_zone_rows'] = (int) $stmt->fetchColumn();

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
  $stmt->execute([$character_id]);
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
  $stmt->execute([$character_id]);
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

  // Zone transitions — use a self-join on adjacent rows via a subquery that assigns
  // row numbers, avoiding the N+1 correlated subquery which is catastrophically slow.
  $stmt = $pdo->prepare('
      SELECT
        a.zone AS from_zone,
        b.zone AS to_zone,
        COUNT(*) AS transition_count
      FROM (
        SELECT zone, ts,
               @rn := @rn + 1 AS rn
        FROM series_zones, (SELECT @rn := 0) AS init
        WHERE character_id = ?
          AND zone IS NOT NULL AND zone != "" AND zone != "Unknown"
        ORDER BY ts ASC
      ) a
      JOIN (
        SELECT zone, ts,
               @rn2 := @rn2 + 1 AS rn
        FROM series_zones, (SELECT @rn2 := 0) AS init2
        WHERE character_id = ?
          AND zone IS NOT NULL AND zone != "" AND zone != "Unknown"
        ORDER BY ts ASC
      ) b ON b.rn = a.rn + 1
      WHERE a.zone != b.zone
      GROUP BY a.zone, b.zone
      ORDER BY transition_count DESC
      LIMIT 20
    ');
  $stmt->execute([$character_id, $character_id]);
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
  $stmt->execute([$character_id]);
  $payload['subzone_breakdown'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode($payload);

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