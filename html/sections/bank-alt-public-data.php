<?php
/**
 * Bank Alt Public Profile Data API
 * 
 * Returns JSON data for a publicly shared bank alt character profile.
 * Includes: character identity, known mounts, online schedule,
 * active auctions, and best-selling items.
 */

declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing slug']);
    exit;
}

try {
    // -----------------------------------------------------------------
    // 1. Fetch character + verify it is public & is a bank alt
    // -----------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.name,
            c.realm,
            c.faction,
            c.class_local,
            c.class_file,
            c.race,
            c.sex,
            c.guild_name,
            c.guild_rank,
            c.last_login_ts,
            c.character_key
        FROM characters c
        INNER JOIN guild_bank_alts gba ON gba.character_id = c.id
        WHERE c.public_slug = ?
          AND c.visibility = 'PUBLIC'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(404);
        echo json_encode(['error' => 'Bank alt not found or not public']);
        exit;
    }

    $cid = (int) $character['id'];
    $rfKey = $character['character_key'] ?? null;

    // Build rf_char_key format used in auction tables: "Realm-Faction:CharName"
    // The auction table uses rf_char_key like "Realm-Faction:CharName"
    // But character_key is "Realm:Name:Class" — derive rf_char_key
    $realm = $character['realm'] ?? '';
    $faction = $character['faction'] ?? '';
    $name = $character['name'] ?? '';
    $rfCharKey = $realm . '-' . $faction . ':' . $name;

    // -----------------------------------------------------------------
    // 2. Determine current level from series_level
    // -----------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT value FROM series_level
        WHERE character_id = ?
        ORDER BY ts DESC
        LIMIT 1
    ");
    $stmt->execute([$cid]);
    $levelRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentLevel = $levelRow ? (int) $levelRow['value'] : null;

    // -----------------------------------------------------------------
    // 3. Determine current location from series_zones
    // -----------------------------------------------------------------
    $stmt = $pdo->prepare("
        SELECT zone, subzone FROM series_zones
        WHERE character_id = ?
        ORDER BY ts DESC
        LIMIT 1
    ");
    $stmt->execute([$cid]);
    $zoneRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $currentZone = $zoneRow['zone'] ?? null;
    $currentSubzone = $zoneRow['subzone'] ?? null;

    // -----------------------------------------------------------------
    // 4. Online schedule — derive from session timestamps
    //    We look at the last 90 days of logins and count by day-of-week
    //    and rough hour bucket to report typical online windows.
    // -----------------------------------------------------------------
    $ninetyAgo = time() - (90 * 86400);
    $stmt = $pdo->prepare("
        SELECT ts FROM series_zones
        WHERE character_id = ? AND ts >= ?
        GROUP BY ts
        ORDER BY ts ASC
    ");
    $stmt->execute([$cid, $ninetyAgo]);
    $tsList = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Build day-of-week and hour distribution
    $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    $dayCounts = array_fill(0, 7, 0);
    $hourBuckets = [];

    foreach ($tsList as $ts) {
        $dow = (int) date('w', (int) $ts);
        $hour = (int) date('G', (int) $ts);
        $dayCounts[$dow]++;
        $hourBuckets[$hour] = ($hourBuckets[$hour] ?? 0) + 1;
    }

    // Normalise: only list days with at least 20% of max day activity
    $maxDay = max($dayCounts) ?: 1;
    $activeDays = [];
    foreach ($dayCounts as $d => $cnt) {
        if ($cnt / $maxDay >= 0.20) {
            $activeDays[] = $dayNames[$d];
        }
    }

    // Hour buckets: find peak 4-hour window
    $peakHour = null;
    $peakVal = 0;
    foreach ($hourBuckets as $h => $v) {
        if ($v > $peakVal) {
            $peakVal = $v;
            $peakHour = $h;
        }
    }
    $onlineWindow = null;
    if ($peakHour !== null) {
        $startH = $peakHour;
        $endH = ($peakHour + 4) % 24;
        $fmt = fn(int $h): string => date('g A', mktime($h, 0, 0));
        $onlineWindow = $fmt($startH) . ' – ' . $fmt($endH) . ' (server time)';
    }

    // -----------------------------------------------------------------
    // 5. Known mounts — from the companions table (type = "MOUNT")
    //    This is the same source used by sections_achievements-data.php
    // -----------------------------------------------------------------
    $mounts = [];
    try {
        $stmt = $pdo->prepare("
            SELECT name, icon, spell_id
            FROM companions
            WHERE character_id = ?
              AND type = 'MOUNT'
            ORDER BY name ASC
        ");
        $stmt->execute([$cid]);
        $mounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mounts = [];
    }

    // -----------------------------------------------------------------
    // 6. Active auctions for this character
    //    Mirrors the logic in sections_currencies-data.php:
    //    - Skip rows already marked sold or expired in the DB
    //    - Also skip rows where enough wall-clock time has passed that
    //      the auction must have lapsed, even if the addon never scanned
    //      after it expired (bucket hours: 1=>12h, 2=>24h, 3=>48h, 4=>48h)
    // -----------------------------------------------------------------
    $activeAuctions = [];
    try {
        $stmt = $pdo->prepare("
            SELECT
                item_id,
                link,
                name,
                stack_size,
                price_stack,
                duration_bucket,
                ts
            FROM auction_owner_rows
            WHERE rf_char_key = ?
              AND sold    = 0
              AND expired = 0
            ORDER BY ts DESC
            LIMIT 500
        ");
        $stmt->execute([$rfCharKey]);
        $rawAuctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Duration bucket -> maximum auction lifetime in seconds
        $bucketHoursMap = [1 => 12, 2 => 24, 3 => 48, 4 => 48];
        $now = time();

        // Duration labels that match the bucket values above
        $durationMap = [1 => '~12 hours', 2 => '~24 hours', 3 => '~48 hours', 4 => '~48 hours'];
        $durationClass = [1 => 'medium', 2 => 'medium', 3 => 'long', 4 => 'long'];

        foreach ($rawAuctions as $row) {
            $bucket = (int) ($row['duration_bucket'] ?? 0);
            $maxHours = $bucketHoursMap[$bucket] ?? 48;
            $expiresAt = (int) $row['ts'] + ($maxHours * 3600);

            // Skip if the auction window has already elapsed
            if ($expiresAt <= $now) {
                continue;
            }

            $priceStack = (int) $row['price_stack'];
            $stackSize = (int) $row['stack_size'];
            $priceEach = $stackSize > 0 ? (int) ($priceStack / $stackSize) : $priceStack;

            // Calculate approximate time remaining for display
            $secsLeft = $expiresAt - $now;
            $hoursLeft = floor($secsLeft / 3600);
            $minsLeft = floor(($secsLeft % 3600) / 60);
            $timeRemaining = $hoursLeft > 0
                ? "{$hoursLeft}h {$minsLeft}m remaining"
                : "{$minsLeft}m remaining";

            $activeAuctions[] = [
                'item_id' => (int) $row['item_id'],
                'link' => $row['link'],
                'name' => $row['name'],
                'stack_size' => $stackSize,
                'price_stack' => $priceStack,
                'price_each' => $priceEach,
                'duration_label' => $durationMap[$bucket] ?? 'Unknown',
                'duration_class' => $durationClass[$bucket] ?? 'medium',
                'time_remaining' => $timeRemaining,
                'expires_at' => $expiresAt,
            ];
        }
    } catch (PDOException $e) {
        $activeAuctions = [];
    }

    // -----------------------------------------------------------------
    // 7. Best-selling (popular) items — group sold auctions by item name
    // -----------------------------------------------------------------
    $popularItems = [];
    try {
        $stmt = $pdo->prepare("
            SELECT
                name,
                MAX(item_id)         AS item_id,
                MAX(link)            AS link,
                SUM(stack_size)      AS total_sold_qty,
                COUNT(*)             AS auction_count,
                AVG(stack_size)      AS avg_stack
            FROM auction_owner_rows
            WHERE rf_char_key = ?
              AND sold = 1
            GROUP BY name
            ORDER BY total_sold_qty DESC
            LIMIT 20
        ");
        $stmt->execute([$rfCharKey]);
        $popularItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($popularItems as &$item) {
            $item['item_id'] = (int) $item['item_id'];
            $item['total_sold_qty'] = (int) $item['total_sold_qty'];
            $item['auction_count'] = (int) $item['auction_count'];
            $item['avg_stack'] = round((float) $item['avg_stack'], 1);
        }
        unset($item);
    } catch (PDOException $e) {
        $popularItems = [];
    }

    // -----------------------------------------------------------------
    // 8. Build and return payload
    // -----------------------------------------------------------------
    echo json_encode([
        'identity' => [
            'name' => $character['name'],
            'realm' => $character['realm'],
            'faction' => $character['faction'],
            'class' => $character['class_local'] ?? $character['class_file'],
            'race' => $character['race'],
            'sex' => isset($character['sex']) ? (int) $character['sex'] : null,
            'guild' => $character['guild_name'],
            'level' => $currentLevel,
            'zone' => $currentZone,
            'subzone' => $currentSubzone,
        ],
        'schedule' => [
            'active_days' => $activeDays,
            'online_window' => $onlineWindow,
        ],
        'mounts' => $mounts,
        'active_auctions' => $activeAuctions,
        'popular_items' => $popularItems,
    ]);

} catch (PDOException $e) {
    error_log('[BankAlt Public Data] DB error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}