<?php
// sections/guild-hall-business-data.php
// Provides guild business data (bank alts, combined auction activity)

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

// Handle POST requests (add/remove bank alts)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handlePostRequest($user_id);
    exit;
}

// Handle GET requests (fetch business data)
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
        'bank_alts' => [],
        'available_characters' => [],
        'auction_stats' => [
            'active_count' => 0,
            'total_value' => 0,
            'sold_count' => 0,
            'sold_value' => 0
        ],
        'recent_auctions' => []
    ];

    // Get bank alts for this guild (table is created by sql_guild_setup.php)
    try {
        $stmt = $pdo->prepare("
            SELECT
                c.id as character_id,
                c.name as character_name,
                c.class_local as class,
                c.class_file
            FROM guild_bank_alts gba
            INNER JOIN characters c ON gba.character_id = c.id
            WHERE gba.guild_id = ?
            ORDER BY c.name
        ");
        $stmt->execute([$guild_id]);
        $payload['bank_alts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table not yet created — admin needs to run sql_guild_setup.php
        error_log('[GuildHall] guild_bank_alts missing: ' . $e->getMessage());
        $payload['bank_alts'] = [];
    }

    // Get available characters (user's characters in this guild that aren't already bank alts)
    $stmt = $pdo->prepare("
        SELECT 
            c.id as character_id,
            c.name as character_name
        FROM characters c
        INNER JOIN character_guilds cg ON c.id = cg.character_id
        WHERE c.user_id = ? 
        AND cg.guild_id = ? 
        AND cg.is_current = TRUE
        AND c.id NOT IN (
            SELECT character_id FROM guild_bank_alts WHERE guild_id = ?
        )
        ORDER BY c.name
    ");
    $stmt->execute([$user_id, $guild_id, $guild_id]);
    $payload['available_characters'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If we have bank alts, get their combined auction data
    if (!empty($payload['bank_alts'])) {
        $bankAltIds = array_column($payload['bank_alts'], 'character_id');

        // Get character keys for bank alts to query auction data
        $placeholders = str_repeat('?,', count($bankAltIds) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT character_key 
            FROM characters 
            WHERE id IN ($placeholders)
        ");
        $stmt->execute($bankAltIds);
        $charKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($charKeys)) {
            // Convert character_key format to rf_char_key format (Realm:Name:Class -> Realm-Faction:Name)
            $rfCharKeys = [];
            foreach ($charKeys as $charKey) {
                // Get realm and name from character_key
                $parts = explode(':', $charKey);
                if (count($parts) >= 2) {
                    $stmt = $pdo->prepare("
                        SELECT realm, name, faction 
                        FROM characters 
                        WHERE character_key = ?
                    ");
                    $stmt->execute([$charKey]);
                    $char = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($char) {
                        $rfCharKeys[] = $char['realm'] . '-' . $char['faction'] . ':' . $char['name'];
                    }
                }
            }

            if (!empty($rfCharKeys)) {
                $placeholders = str_repeat('?,', count($rfCharKeys) - 1) . '?';

                // Get active auctions (not sold, not expired)
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(price_stack), 0) as total_value
                    FROM auction_owner_rows
                    WHERE rf_char_key IN ($placeholders)
                    AND sold = 0
                    AND expired = 0
                    AND ts + (CASE duration_bucket
                        WHEN 1 THEN 43200
                        WHEN 2 THEN 86400
                        WHEN 3 THEN 172800
                        WHEN 4 THEN 172800
                        ELSE 172800
                    END) > UNIX_TIMESTAMP()
                ");
                $stmt->execute($rfCharKeys);
                $activeStats = $stmt->fetch(PDO::FETCH_ASSOC);

                // Get sold auctions
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count, COALESCE(SUM(sold_price), 0) as total_value
                    FROM auction_owner_rows
                    WHERE rf_char_key IN ($placeholders)
                    AND sold = 1
                ");
                $stmt->execute($rfCharKeys);
                $soldStats = $stmt->fetch(PDO::FETCH_ASSOC);

                $payload['auction_stats'] = [
                    'active_count' => (int) ($activeStats['count'] ?? 0),
                    'total_value' => (int) ($activeStats['total_value'] ?? 0),
                    'sold_count' => (int) ($soldStats['count'] ?? 0),
                    'sold_value' => (int) ($soldStats['total_value'] ?? 0)
                ];

                // Get recent auctions (active + recently sold/expired, limit 20)
                $stmt = $pdo->prepare("
                    SELECT 
                        rf_char_key,
                        name as item_name,
                        link as item_link,
                        stack_size,
                        price_stack as buyout,
                        duration_bucket,
                        sold,
                        expired,
                        ts
                    FROM auction_owner_rows
                    WHERE rf_char_key IN ($placeholders)
                    ORDER BY ts DESC
                    LIMIT 20
                ");
                $stmt->execute($rfCharKeys);
                $auctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Add character name and status to each auction
                foreach ($auctions as &$auction) {
                    // Extract character name from rf_char_key
                    $keyParts = explode(':', $auction['rf_char_key']);
                    $auction['character_name'] = $keyParts[1] ?? 'Unknown';

                    // Determine status
                    if ($auction['sold']) {
                        $auction['status'] = 'Sold';
                    } elseif ($auction['expired']) {
                        $auction['status'] = 'Expired';
                    } else {
                        // Check if auction has time-expired based on ts + duration
                        $bucketHoursMap = [1 => 12, 2 => 24, 3 => 48, 4 => 48];
                        $hours = $bucketHoursMap[(int) ($auction['duration_bucket'] ?? 0)] ?? 48;
                        $hasTimeExpired = ((int) $auction['ts'] + ($hours * 3600)) <= time();
                        $auction['status'] = $hasTimeExpired ? 'Expired' : 'Active';
                    }

                    // Format time left for active auctions
                    if (!$auction['sold'] && !$auction['expired']) {
                        $duration = $auction['duration_bucket'] ?? 0;
                        $auction['time_left'] = match ($duration) {
                            1 => 'Short',
                            2 => 'Medium',
                            3 => 'Long',
                            4 => 'Very Long',
                            default => 'Unknown'
                        };
                    } else {
                        $auction['time_left'] = '-';
                    }
                }
                unset($auction);

                $payload['recent_auctions'] = $auctions;
            } else {
                // No matching rf_char_keys found
                $payload['auction_stats'] = [
                    'active_count' => 0,
                    'total_value' => 0,
                    'sold_count' => 0,
                    'sold_value' => 0
                ];
                $payload['recent_auctions'] = [];
            }
        } else {
            // No character keys found
            $payload['auction_stats'] = [
                'active_count' => 0,
                'total_value' => 0,
                'sold_count' => 0,
                'sold_value' => 0
            ];
            $payload['recent_auctions'] = [];
        }
    } else {
        // No bank alts
        $payload['auction_stats'] = [
            'active_count' => 0,
            'total_value' => 0,
            'sold_count' => 0,
            'sold_value' => 0
        ];
        $payload['recent_auctions'] = [];
    }

    // ============================================================================
    // CHART DATA — daily auction sales & postings from auction_owner_rows.
    // Uses rf_char_keys already resolved above for daily granularity.
    // Each character gets its own series; Total line added only for 2+ characters.
    // points[] carries per-character breakdown for JS hover tooltips.
    // ============================================================================
    if (!empty($rfCharKeys ?? [])) {
        $placeholders = str_repeat('?,', count($rfCharKeys) - 1) . '?';
        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        // Short display label for a date: "Jan 3 '26"
        $fmtLabel = function (string $date) use ($monthNames): string {
            [$y, $m, $d] = explode('-', $date);
            return $monthNames[(int) $m - 1] . ' ' . (int) $d . " '" . substr($y, 2);
        };

        // Map rf_char_key -> short character name for series labels
        $nameFor = [];
        foreach ($rfCharKeys as $k) {
            $parts = explode(':', $k);
            $nameFor[$k] = $parts[1] ?? $k;
        }

        // ── INCOMING: daily sold revenue per character ──
        $stmt = $pdo->prepare("
            SELECT
                DATE(FROM_UNIXTIME(sold_ts)) AS day,
                rf_char_key,
                SUM(sold_price) AS total
            FROM auction_owner_rows
            WHERE rf_char_key IN ($placeholders)
              AND sold = 1
              AND sold_ts IS NOT NULL
            GROUP BY DATE(FROM_UNIXTIME(sold_ts)), rf_char_key
            ORDER BY day ASC
        ");
        $stmt->execute($rfCharKeys);
        $salesRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── OUTGOING: daily posted buyout value per character ──
        $stmt = $pdo->prepare("
            SELECT
                DATE(FROM_UNIXTIME(ts)) AS day,
                rf_char_key,
                SUM(price_stack) AS total
            FROM auction_owner_rows
            WHERE rf_char_key IN ($placeholders)
            GROUP BY DATE(FROM_UNIXTIME(ts)), rf_char_key
            ORDER BY day ASC
        ");
        $stmt->execute($rfCharKeys);
        $postRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ── Shared builder: rows -> {series, labels, points} ──
        $buildSeriesData = function (array $rows) use ($rfCharKeys, $nameFor, $fmtLabel): array {
            $byDay = []; // day -> charName -> total
            $allDays = [];

            // Pre-seed all bank alt characters so zero-activity alts still appear as flat lines
            $chars = [];
            foreach ($rfCharKeys as $k) {
                $chars[$nameFor[$k] ?? $k] = true;
            }

            foreach ($rows as $row) {
                $day = $row['day'];
                $charName = $nameFor[$row['rf_char_key']] ?? $row['rf_char_key'];
                $total = (int) $row['total'];

                $byDay[$day][$charName] = ($byDay[$day][$charName] ?? 0) + $total;
                $allDays[$day] = true;
            }

            ksort($allDays);
            $allDays = array_keys($allDays);
            $chars = array_keys($chars);

            // Per-character series (always kept, name never replaced with "Total")
            $series = [];
            foreach ($chars as $char) {
                $values = [];
                foreach ($allDays as $day) {
                    $values[] = $byDay[$day][$char] ?? 0;
                }
                $series[] = ['label' => $char, 'values' => $values];
            }

            // Total line only when 2+ characters
            if (count($chars) > 1) {
                $totals = [];
                foreach ($allDays as $day) {
                    $t = 0;
                    foreach ($chars as $char) {
                        $t += $byDay[$day][$char] ?? 0;
                    }
                    $totals[] = $t;
                }
                $series[] = ['label' => 'Total', 'values' => $totals];
            }

            // points: one entry per day with full per-character breakdown for tooltips
            $points = [];
            foreach ($allDays as $day) {
                $breakdown = [];
                foreach ($chars as $char) {
                    $v = $byDay[$day][$char] ?? 0;
                    if ($v > 0)
                        $breakdown[$char] = $v;
                }
                $points[] = ['date' => $day, 'breakdown' => $breakdown];
            }

            return [
                'series' => $series,
                'labels' => array_map($fmtLabel, $allDays),
                'points' => $points,
            ];
        };

        $payload['chart_data'] = [
            'incoming' => $buildSeriesData($salesRows),
            'outgoing' => $buildSeriesData($postRows),
        ];
    } else {
        $payload['chart_data'] = null;
    }

    echo json_encode($payload);

} catch (PDOException $e) {
    error_log("Business data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

/**
 * Handle POST requests for adding/removing bank alts
 */
function handlePostRequest($user_id)
{
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        return;
    }

    $action = $input['action'] ?? null;
    $guild_id = $input['guild_id'] ?? null;
    $character_id = $input['character_id'] ?? null;

    if (!$action || !$guild_id || !$character_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required parameters']);
        return;
    }

    // Ensure database connection is available
    if (!isset($GLOBALS['pdo'])) {
        require_once __DIR__ . '/../db.php';
    }
    $pdo = $GLOBALS['pdo'];

    try {

        // Verify user owns the character
        $stmt = $pdo->prepare("
            SELECT 1 FROM characters WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$character_id, $user_id]);

        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied']);
            return;
        }

        if ($action === 'add_bank_alt') {
            // Add bank alt
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO guild_bank_alts (guild_id, character_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$guild_id, $character_id]);

            echo json_encode(['success' => true, 'message' => 'Bank alt added']);

        } else if ($action === 'remove_bank_alt') {
            // Remove bank alt
            $stmt = $pdo->prepare("
                DELETE FROM guild_bank_alts
                WHERE guild_id = ? AND character_id = ?
            ");
            $stmt->execute([$guild_id, $character_id]);

            echo json_encode(['success' => true, 'message' => 'Bank alt removed']);

        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action']);
        }

    } catch (PDOException $e) {
        error_log("Bank alt operation error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}