<?php
// sections/bazaar-auction.php - Multi-character auction house aggregator (FIXED)
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

// Initialize response payload
$payload = [
    'summary' => [
        'total_gold' => 0,
        'active_auctions' => 0,
        'sold_24h' => 0,
        'earnings_7d' => 0,
        'canceled_7d' => 0
    ],
    'character_gold' => [],
    'active_auctions' => [],
    'recent_sales' => []
];

try {
    // Get all user's characters
    $stmt = $pdo->prepare('SELECT id, name, realm, faction FROM characters WHERE user_id = ? ORDER BY name');
    $stmt->execute([$user_id]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($characters)) {
        echo json_encode($payload);
        exit;
    }

    $character_ids = array_column($characters, 'id');
    $character_names = [];
    $character_keys = [];

    foreach ($characters as $char) {
        $character_names[$char['id']] = $char['name'];
        // Build rf_char_key pattern like realm-faction:charname
        $charKey = '%' . $char['realm'] . '-' . ($char['faction'] ?? '') . ':' . $char['name'];
        $character_keys[] = $charKey;
    }

    // ============================================================================
    // TOTAL GOLD ACROSS ALL CHARACTERS (from series_money)
    // ============================================================================
    try {
        $placeholders = implode(',', array_fill(0, count($character_ids), '?'));

        // Get latest gold value for each character
        $stmt = $pdo->prepare("
            SELECT 
                sm.character_id,
                sm.value as gold
            FROM series_money sm
            INNER JOIN (
                SELECT character_id, MAX(ts) as max_ts
                FROM series_money
                WHERE character_id IN ($placeholders)
                GROUP BY character_id
            ) latest ON sm.character_id = latest.character_id AND sm.ts = latest.max_ts
            WHERE sm.character_id IN ($placeholders)
            ORDER BY sm.value DESC
        ");
        // Need to pass character_ids twice (once for subquery, once for outer query)
        $stmt->execute(array_merge($character_ids, $character_ids));

        $total_gold = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $gold = (int) ($row['gold'] ?? 0);
            $total_gold += $gold;

            $char_id = (int) $row['character_id'];
            $payload['character_gold'][] = [
                'character_id' => $char_id,
                'name' => $character_names[$char_id] ?? 'Unknown',
                'gold' => $gold
            ];
        }

        $payload['summary']['total_gold'] = $total_gold;
    } catch (Throwable $e) {
        error_log("Bazaar auction - gold data error: " . $e->getMessage());
    }

    // ============================================================================
    // ACTIVE AUCTIONS (from auction_owner_rows where not sold/expired)
    // ============================================================================
    try {
        // Build WHERE clause for all character keys
        $keyConditions = [];
        foreach ($character_keys as $idx => $key) {
            $keyConditions[] = "aor.rf_char_key LIKE :char_key_$idx";
        }
        $keyWhere = implode(' OR ', $keyConditions);

        $stmt = $pdo->prepare("
            SELECT 
                aor.item_id,
                aor.link,
                aor.name as item_name,
                aor.stack_size as quantity,
                FLOOR(aor.price_stack / aor.stack_size) as unit_price,
                aor.price_stack as total_price,
                aor.duration_bucket,
                aor.ts,
                aor.rf_char_key
            FROM auction_owner_rows aor
            WHERE ($keyWhere)
                AND aor.sold = 0
                AND aor.expired = 0
                AND aor.ts + (CASE aor.duration_bucket
                    WHEN 1 THEN 43200
                    WHEN 2 THEN 86400
                    WHEN 3 THEN 172800
                    WHEN 4 THEN 172800
                    ELSE 172800
                END) > UNIX_TIMESTAMP()
            ORDER BY aor.ts DESC
            LIMIT 100
        ");

        // Bind all character key parameters
        $params = [];
        foreach ($character_keys as $idx => $key) {
            $params[":char_key_$idx"] = $key;
        }
        $stmt->execute($params);

        $active_count = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $active_count++;

            // Extract character name from rf_char_key (format: realm-faction:charname)
            $charName = 'Unknown';
            if (preg_match('/:([^:]+)$/', $row['rf_char_key'], $matches)) {
                $charName = $matches[1];
            }

            // Calculate expires_at based on duration_bucket and ts
            // Bucket: 1=12h, 2=24h, 3=48h, 4=48h, fallback=48h
            $bucketHoursMap = [1 => 12, 2 => 24, 3 => 48, 4 => 48];
            $duration_hours = $bucketHoursMap[(int) ($row['duration_bucket'] ?? 0)] ?? 48;
            $expires_at = date('Y-m-d H:i:s', $row['ts'] + ($duration_hours * 3600));

            $payload['active_auctions'][] = [
                'item_id' => (int) ($row['item_id'] ?? 0),
                'link' => $row['link'] ?? null,
                'item_name' => $row['item_name'],
                'quantity' => (int) ($row['quantity'] ?? 1),
                'unit_price' => (int) ($row['unit_price'] ?? 0),
                'total_price' => (int) ($row['total_price'] ?? 0),
                'expires_at' => $expires_at,
                'character_name' => $charName,
            ];
        }

        $payload['summary']['active_auctions'] = $active_count;
    } catch (Throwable $e) {
        error_log("Bazaar auction - active auctions error: " . $e->getMessage());
    }

    // ============================================================================
    // RECENT SALES (last 24 hours from auction_owner_rows)
    // ============================================================================
    try {
        // Build WHERE clause for all character keys
        $keyConditions = [];
        foreach ($character_keys as $idx => $key) {
            $keyConditions[] = "aor.rf_char_key LIKE :char_key_$idx";
        }
        $keyWhere = implode(' OR ', $keyConditions);

        $stmt = $pdo->prepare("
            SELECT 
                aor.item_id,
                aor.link,
                aor.name as item_name,
                aor.stack_size as quantity,
                FLOOR(COALESCE(aor.sold_price, aor.price_stack) / aor.stack_size) as unit_price,
                aor.sold_price as total_price,
                aor.sold_ts,
                aor.rf_char_key
            FROM auction_owner_rows aor
            WHERE ($keyWhere)
                AND aor.sold = 1
                AND aor.sold_ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR))
            ORDER BY aor.sold_ts DESC
            LIMIT 50
        ");

        // Bind all character key parameters
        $params = [];
        foreach ($character_keys as $idx => $key) {
            $params[":char_key_$idx"] = $key;
        }
        $stmt->execute($params);

        $sold_24h = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $sold_24h++;

            // Extract character name from rf_char_key
            $charName = 'Unknown';
            if (preg_match('/:([^:]+)$/', $row['rf_char_key'], $matches)) {
                $charName = $matches[1];
            }

            $sold_at = date('Y-m-d H:i:s', $row['sold_ts']);

            $payload['recent_sales'][] = [
                'item_id' => (int) ($row['item_id'] ?? 0),
                'link' => $row['link'] ?? null,
                'item_name' => $row['item_name'],
                'quantity' => (int) ($row['quantity'] ?? 1),
                'unit_price' => (int) ($row['unit_price'] ?? 0),
                'total_price' => (int) ($row['total_price'] ?? 0),
                'sold_at' => $sold_at,
                'character_name' => $charName,
            ];
        }

        $payload['summary']['sold_24h'] = $sold_24h;
    } catch (Throwable $e) {
        error_log("Bazaar auction - recent sales error: " . $e->getMessage());
    }

    // ============================================================================
    // EARNINGS (last 7 days)
    // ============================================================================
    try {
        // Build WHERE clause for all character keys
        $keyConditions = [];
        foreach ($character_keys as $idx => $key) {
            $keyConditions[] = "aor.rf_char_key LIKE :char_key_$idx";
        }
        $keyWhere = implode(' OR ', $keyConditions);

        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(aor.sold_price), 0) as total_earnings
            FROM auction_owner_rows aor
            WHERE ($keyWhere)
                AND aor.sold = 1
                AND aor.sold_ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
        ");

        // Bind all character key parameters
        $params = [];
        foreach ($character_keys as $idx => $key) {
            $params[":char_key_$idx"] = $key;
        }
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $payload['summary']['earnings_7d'] = (int) ($row['total_earnings'] ?? 0);
    } catch (Throwable $e) {
        error_log("Bazaar auction - earnings error: " . $e->getMessage());
    }

    // ============================================================================
    // EXPIRED AUCTIONS (last 7 days)
    // ============================================================================
    try {
        // Build WHERE clause for all character keys
        $keyConditions = [];
        foreach ($character_keys as $idx => $key) {
            $keyConditions[] = "aor.rf_char_key LIKE :char_key_$idx";
        }
        $keyWhere = implode(' OR ', $keyConditions);

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as expired_count
            FROM auction_owner_rows aor
            WHERE ($keyWhere)
                AND aor.expired = 1
                AND aor.ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
        ");

        // Bind all character key parameters
        $params = [];
        foreach ($character_keys as $idx => $key) {
            $params[":char_key_$idx"] = $key;
        }
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $payload['summary']['canceled_7d'] = (int) ($row['expired_count'] ?? 0);
    } catch (Throwable $e) {
        error_log("Bazaar auction - expired count error: " . $e->getMessage());
    }

} catch (Throwable $e) {
    error_log("Bazaar auction - general error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error loading auction data']);
    exit;
}

echo json_encode($payload);