<?php
// sections/currencies-data.php (ENHANCED - PRESERVES ALL EXISTING DATA)
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
$own = $pdo->prepare('SELECT id, name, realm, faction FROM characters WHERE id = ? AND user_id = ?');
$own->execute([$cid, $_SESSION['user_id'] ?? null]);
$character = $own->fetch(PDO::FETCH_ASSOC);
if (!$character) {
    http_response_code(403);
    echo json_encode(['error' => 'Character not found or not yours']);
    exit;
}

// Initialize payload - KEEP ALL EXISTING STRUCTURE
$payload = [
    'current' => [
        'gold' => 0,
        'change_24h' => 0,
        'change_7d' => 0,
        'change_30d' => 0,
    ],
    'timeseries' => [],
    'income_breakdown' => [],
    'expense_breakdown' => [],
    'milestones' => [],
    'gold_per_hour' => [],
    'loot_stats' => [
        'total_items' => 0,
        'quality_distribution' => [],
        'epic_count' => 0,
    ],
    'epic_loot' => [],
    'auctions' => [
        'stats' => [
            'active_count' => 0,
            'active_value' => 0,
            'sold_count' => 0,
            'sold_value' => 0,
            'expired_count' => 0,
        ],
        'active' => [],
        'best_sellers' => [],

        // NEW: Enhanced auction analytics (added below existing data)
        'sell_through_by_duration' => [],
        'market_seasonality' => [
            'by_day' => [],
            'by_hour' => [],
        ],
        'deposit_vs_margin' => [],
        'price_history' => [],
        'undercut_opportunities' => [],
        'top_items_performance' => [],
        'competitor_analysis' => [],
        'repost_tracker' => [],
        'auction_history' => [],
    ],
];

// Build character key for auction queries
$charKey = $character['realm'] . '-' . ($character['faction'] ?? '') . ':' . $character['name'];

// ========================================================================
// EXISTING CODE - KEEP EVERYTHING EXACTLY AS IS
// ========================================================================

// Current Gold & Changes
try {
    $stmt = $pdo->prepare('
        SELECT ts, value 
        FROM series_money 
        WHERE character_id = ? 
        ORDER BY ts ASC
    ');
    $stmt->execute([$cid]);
    $moneyData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($moneyData)) {
        $current = (int) $moneyData[count($moneyData) - 1]['value'];
        $payload['current']['gold'] = $current;

        $now = time();
        $day_ago = $now - (24 * 3600);
        $week_ago = $now - (7 * 24 * 3600);
        $month_ago = $now - (30 * 24 * 3600);

        foreach ($moneyData as $point) {
            $ts = (int) $point['ts'];
            $val = (int) $point['value'];

            if ($ts >= $day_ago && !isset($gold_24h)) {
                $gold_24h = $val;
            }
            if ($ts >= $week_ago && !isset($gold_7d)) {
                $gold_7d = $val;
            }
            if ($ts >= $month_ago && !isset($gold_30d)) {
                $gold_30d = $val;
            }
        }

        $payload['current']['change_24h'] = $current - ($gold_24h ?? $current);
        $payload['current']['change_7d'] = $current - ($gold_7d ?? $current);
        $payload['current']['change_30d'] = $current - ($gold_30d ?? $current);

        $payload['timeseries'] = array_map(
            fn($p) => ['ts' => (int) $p['ts'], 'value' => (int) $p['value']],
            array_filter($moneyData, fn($p) => (int) $p['ts'] >= $month_ago)
        );
    }
} catch (Throwable $e) {
    error_log("Money timeseries error: " . $e->getMessage());
}

// Gold Per Hour
try {
    $stmt = $pdo->prepare('
        SELECT 
            DATE(FROM_UNIXTIME(s.ts)) as date,
            s.ts,
            s.total_time
        FROM sessions s
        WHERE s.character_id = ?
          AND s.ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
        ORDER BY s.ts ASC
    ');
    $stmt->execute([$cid]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $dailyData = [];
    foreach ($sessions as $session) {
        $date = $session['date'];
        $ts = (int) $session['ts'];
        $duration = (int) ($session['total_time'] ?? 0);

        if (!isset($dailyData[$date])) {
            $dailyData[$date] = [
                'date' => $date,
                'total_time' => 0,
                'start_gold' => 0,
                'end_gold' => 0,
                'min_ts' => $ts,
                'max_ts' => $ts,
            ];
        }

        $dailyData[$date]['total_time'] += $duration;
        if ($ts < $dailyData[$date]['min_ts'])
            $dailyData[$date]['min_ts'] = $ts;
        if ($ts > $dailyData[$date]['max_ts'])
            $dailyData[$date]['max_ts'] = $ts;
    }

    foreach ($dailyData as $date => &$day) {
        $start_gold = 0;
        $end_gold = 0;

        foreach ($moneyData ?? [] as $point) {
            $ts = (int) $point['ts'];
            $val = (int) $point['value'];

            if ($ts <= $day['min_ts']) {
                $start_gold = $val;
            }
            if ($ts <= $day['max_ts']) {
                $end_gold = $val;
            }
        }

        $gold_earned = $end_gold - $start_gold;
        $hours = $day['total_time'] > 0 ? $day['total_time'] / 3600.0 : 0;

        $day['gold_earned'] = $gold_earned;
        $day['hours'] = $hours;
        $day['gold_per_hour'] = $hours > 0 ? round($gold_earned / $hours) : 0;
    }

    $payload['gold_per_hour'] = array_values($dailyData);

} catch (Throwable $e) {
    error_log("Gold per hour error: " . $e->getMessage());
}

// Wealth Milestones
try {
    $milestones_targets = [1000, 10000, 100000, 1000000, 5000000, 10000000, 50000000];

    foreach ($milestones_targets as $target) {
        foreach ($moneyData ?? [] as $point) {
            if ((int) $point['value'] >= $target) {
                $g = floor($target / 10000);
                $s = floor(($target % 10000) / 100);
                $label = $g > 0 ? "{$g}g" : "{$s}s";

                $payload['milestones'][] = [
                    'amount' => $target,
                    'label' => $label,
                    'ts' => (int) $point['ts'],
                    'date' => date('M j, Y', (int) $point['ts']),
                ];
                break;
            }
        }
    }
} catch (Throwable $e) {
    error_log("Milestones error: " . $e->getMessage());
}

// Loot Statistics
try {
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as total
        FROM item_events
        WHERE character_id = ? AND action = "obtained"
    ');
    $stmt->execute([$cid]);
    $payload['loot_stats']['total_items'] = (int) ($stmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    error_log("Loot stats error: " . $e->getMessage());
}

// Epic Loot Timeline
try {
    $stmt = $pdo->prepare('
        SELECT 
            ie.ts,
            ie.name,
            ie.link,
            ie.icon,
            ie.source,
            ie.location,
            ie.count
        FROM item_events ie
        WHERE ie.character_id = ?
          AND ie.action = "obtained"
        ORDER BY ie.ts DESC
        LIMIT 50
    ');
    $stmt->execute([$cid]);

    $payload['epic_loot'] = array_map(
        function ($r) {
            return [
                'ts' => (int) $r['ts'],
                'name' => $r['name'],
                'link' => $r['link'],
                'icon' => $r['icon'],
                'source' => $r['source'],
                'location' => $r['location'],
                'count' => (int) ($r['count'] ?? 1),
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

} catch (Throwable $e) {
    error_log("Epic loot error: " . $e->getMessage());
}

// EXISTING Auction House Data
try {
    // Active auctions
    $stmt = $pdo->prepare('
        SELECT 
            name,
            stack_size,
            price_stack,
            duration_bucket,
            ts
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
          AND sold = 0
          AND expired = 0
        ORDER BY ts DESC
        LIMIT 20
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);
    $activeAuctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $payload['auctions']['active'] = array_map(
        function ($r) {
            return [
                'name' => $r['name'],
                'stack_size' => (int) ($r['stack_size'] ?? 1),
                'price_stack' => (int) ($r['price_stack'] ?? 0),
                'duration_bucket' => (int) ($r['duration_bucket'] ?? 0),
                'ts' => (int) $r['ts'],
            ];
        },
        $activeAuctions
    );

    // Auction stats
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as active_count,
            SUM(price_stack) as active_value
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
          AND sold = 0
          AND expired = 0
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);
    $activeStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $payload['auctions']['stats']['active_count'] = (int) ($activeStats['active_count'] ?? 0);
    $payload['auctions']['stats']['active_value'] = (int) ($activeStats['active_value'] ?? 0);

    // Sold auctions
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as sold_count,
            SUM(sold_price) as sold_value
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
          AND sold = 1
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);
    $soldStats = $stmt->fetch(PDO::FETCH_ASSOC);

    $payload['auctions']['stats']['sold_count'] = (int) ($soldStats['sold_count'] ?? 0);
    $payload['auctions']['stats']['sold_value'] = (int) ($soldStats['sold_value'] ?? 0);

    // Expired count
    $stmt = $pdo->prepare('
        SELECT COUNT(*) as expired_count
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
          AND expired = 1
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);
    $payload['auctions']['stats']['expired_count'] = (int) ($stmt->fetchColumn() ?: 0);

    // Best sellers
    $stmt = $pdo->prepare('
        SELECT 
            name,
            COUNT(*) as units_sold,
            SUM(sold_price) as total_revenue,
            AVG(sold_price) as avg_price
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
          AND sold = 1
        GROUP BY name
        ORDER BY total_revenue DESC
        LIMIT 10
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);

    $payload['auctions']['best_sellers'] = array_map(
        function ($r) {
            return [
                'name' => $r['name'],
                'units_sold' => (int) $r['units_sold'],
                'total_revenue' => (int) $r['total_revenue'],
                'avg_price' => (int) $r['avg_price'],
            ];
        },
        $stmt->fetchAll(PDO::FETCH_ASSOC)
    );

} catch (Throwable $e) {
    error_log("Auction data error: " . $e->getMessage());
}

// ========================================================================
// NEW ANALYTICS - ADD BELOW EXISTING DATA
// ========================================================================

// NEW FEATURE 1: Sell-Through by Duration
try {
    $stmt = $pdo->prepare('
        SELECT 
            duration_bucket,
            COUNT(*) as total,
            SUM(CASE WHEN sold = 1 THEN 1 ELSE 0 END) as sold_count,
            SUM(CASE WHEN expired = 1 THEN 1 ELSE 0 END) as expired_count
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
          AND (sold = 1 OR expired = 1)
        GROUP BY duration_bucket
        ORDER BY duration_bucket
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);

    $durationLabels = [1 => '12h', 2 => '24h', 3 => '48h'];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $total = (int) $row['total'];
        $sold = (int) $row['sold_count'];
        $expired = (int) $row['expired_count'];
        $bucket = (int) $row['duration_bucket'];

        $payload['auctions']['sell_through_by_duration'][] = [
            'duration_bucket' => $bucket,
            'duration_label' => $durationLabels[$bucket] ?? 'Unknown',
            'total' => $total,
            'sold_count' => $sold,
            'expired_count' => $expired,
            'success_rate' => $total > 0 ? round(($sold / $total) * 100, 1) : 0,
        ];
    }
} catch (Throwable $e) {
    error_log("Sell-through by duration error: " . $e->getMessage());
}

// NEW FEATURE 2: Market Seasonality
try {
    // By day of week
    $stmt = $pdo->prepare('
        SELECT 
            DAYOFWEEK(FROM_UNIXTIME(sold_ts)) as day_of_week,
            COUNT(*) as sale_count
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
          AND sold = 1
          AND sold_ts IS NOT NULL
        GROUP BY day_of_week
        ORDER BY day_of_week
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);

    $dayNames = [1 => 'Sun', 2 => 'Mon', 3 => 'Tue', 4 => 'Wed', 5 => 'Thu', 6 => 'Fri', 7 => 'Sat'];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dow = (int) $row['day_of_week'];
        $payload['auctions']['market_seasonality']['by_day'][] = [
            'day_of_week' => $dow,
            'day_name' => $dayNames[$dow] ?? 'Unknown',
            'sale_count' => (int) $row['sale_count'],
        ];
    }

    // By hour of day
    $stmt = $pdo->prepare('
        SELECT 
            HOUR(FROM_UNIXTIME(sold_ts)) as hour,
            COUNT(*) as sale_count
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
          AND sold = 1
          AND sold_ts IS NOT NULL
        GROUP BY hour
        ORDER BY hour
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['auctions']['market_seasonality']['by_hour'][] = [
            'hour' => (int) $row['hour'],
            'sale_count' => (int) $row['sale_count'],
        ];
    }
} catch (Throwable $e) {
    error_log("Market seasonality error: " . $e->getMessage());
}

// NEW FEATURE 3: Deposit Burn vs. Margin
try {
    $stmt = $pdo->prepare('
        SELECT 
            name,
            SUM(CASE WHEN sold = 1 THEN sold_price - price_stack ELSE 0 END) as total_margin,
            COUNT(CASE WHEN expired = 1 THEN 1 END) as expire_count,
            duration_bucket,
            AVG(price_stack) as avg_listing_price
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
          AND (sold = 1 OR expired = 1)
        GROUP BY name
        HAVING COUNT(*) >= 3
        ORDER BY expire_count DESC
        LIMIT 20
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $avgPrice = (int) $row['avg_listing_price'];
        $expireCount = (int) $row['expire_count'];

        $estimatedVendorPrice = (int) ($avgPrice * 0.1);
        $depositMultiplier = [1 => 0.15, 2 => 0.30, 3 => 0.60][(int) $row['duration_bucket']] ?? 0.15;
        $depositPerListing = (int) ($estimatedVendorPrice * $depositMultiplier);
        $totalDepositBurn = $depositPerListing * $expireCount;

        $payload['auctions']['deposit_vs_margin'][] = [
            'name' => $row['name'],
            'total_margin' => (int) $row['total_margin'],
            'deposit_burn' => $totalDepositBurn,
            'expire_count' => $expireCount,
            'avg_listing_price' => $avgPrice,
        ];
    }
} catch (Throwable $e) {
    error_log("Deposit burn vs margin error: " . $e->getMessage());
}

// NEW FEATURE 4: Price History (ENHANCED - Shows Market Prices)
try {
    $rfKey = $character['realm'] . '-' . ($character['faction'] ?? '');

    // Get items you've listed that also have market data
    $stmt = $pdo->prepare('
        SELECT DISTINCT 
            CONCAT(aor.item_id, ":", aor.stack_size) as item_key,
            aor.name,
            aor.item_id
        FROM auction_owner_rows aor
        WHERE aor.rf_char_key LIKE :char_key
          AND EXISTS (
              SELECT 1 
              FROM auction_market_ts amt
              WHERE amt.rf_key = :rf_key
                AND amt.item_key = CONCAT(aor.item_id, ":", aor.stack_size)
          )
        GROUP BY aor.item_id, aor.stack_size
        ORDER BY COUNT(*) DESC
        LIMIT 5
    ');
    $stmt->execute([
        ':char_key' => '%:' . $character['name'],
        ':rf_key' => $rfKey
    ]);
    $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($topItems as $item) {
        // Get market price history for this item
        $stmt2 = $pdo->prepare('
            SELECT 
                DATE(FROM_UNIXTIME(amt.ts)) as date,
                MIN(amb.price_item) as min_competitor_price,
                MAX(amb.price_item) as max_competitor_price,
                AVG(amb.price_item) as avg_competitor_price,
                COUNT(DISTINCT amb.seller) as competitor_count,
                AVG(amt.my_price_item) as my_price
            FROM auction_market_ts amt
            LEFT JOIN auction_market_bands amb ON amb.market_ts_id = amt.id
            WHERE amt.rf_key = :rf_key
              AND amt.item_key = :item_key
              AND amt.ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            GROUP BY date
            ORDER BY date ASC
        ');
        $stmt2->execute([
            ':rf_key' => $rfKey,
            ':item_key' => $item['item_key']
        ]);

        $history = [];
        foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $history[] = [
                'date' => $row['date'],
                'min_price' => (int) ($row['min_competitor_price'] ?? 0),
                'max_price' => (int) ($row['max_competitor_price'] ?? 0),
                'avg_price' => (int) ($row['avg_competitor_price'] ?? 0),
                'my_price' => (int) ($row['my_price'] ?? 0),
                'competitor_count' => (int) ($row['competitor_count'] ?? 0),
            ];
        }

        $payload['auctions']['price_history'][] = [
            'item_name' => $item['name'],
            'item_id' => (int) $item['item_id'],
            'history' => $history,
        ];
    }
} catch (Throwable $e) {
    error_log("Price history error: " . $e->getMessage());
}

// NEW FEATURE 5: Undercut Opportunities
try {
    $rfKey = $character['realm'] . '-' . ($character['faction'] ?? '');

    $stmt = $pdo->prepare('
        SELECT 
            aor.name,
            aor.price_stack,
            aor.stack_size,
            aor.item_id
        FROM auction_owner_rows aor
        WHERE aor.rf_char_key LIKE :char_key
          AND aor.sold = 0
          AND aor.expired = 0
        LIMIT 10
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);
    $activeListings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($activeListings as $listing) {
        $itemKey = $listing['item_id'] . ':' . $listing['stack_size'];

        $stmt2 = $pdo->prepare('
            SELECT 
                amb.price_item,
                amb.seller
            FROM auction_market_ts amt
            JOIN auction_market_bands amb ON amb.market_ts_id = amt.id
            WHERE amt.rf_key = :rf_key
              AND amt.item_key = :item_key
              AND amb.band_type = "LOW"
            ORDER BY amt.ts DESC
            LIMIT 3
        ');
        $stmt2->execute([
            ':rf_key' => $rfKey,
            ':item_key' => $itemKey
        ]);
        $competitorPrices = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($competitorPrices)) {
            $myPricePerItem = (int) ($listing['price_stack'] / $listing['stack_size']);
            $lowestCompetitor = (int) $competitorPrices[0]['price_item'];

            $payload['auctions']['undercut_opportunities'][] = [
                'name' => $listing['name'],
                'my_price' => $myPricePerItem,
                'competitor_low' => $lowestCompetitor,
                'undercut_1pct' => (int) ($lowestCompetitor * 0.99),
                'undercut_2pct' => (int) ($lowestCompetitor * 0.98),
                'undercut_5pct' => (int) ($lowestCompetitor * 0.95),
                'my_position' => $myPricePerItem <= $lowestCompetitor ? 'competitive' : 'overpriced',
                'competitors' => array_map(fn($c) => [
                    'seller' => $c['seller'],
                    'price' => (int) $c['price_item']
                ], $competitorPrices),
            ];
        }
    }
} catch (Throwable $e) {
    error_log("Undercut opportunities error: " . $e->getMessage());
}

// NEW FEATURE 6: Top Items Performance
try {
    $stmt = $pdo->prepare('
        SELECT 
            name,
            COUNT(*) as total_listings,
            SUM(CASE WHEN sold = 1 THEN 1 ELSE 0 END) as sold_count,
            AVG(CASE 
                WHEN sold = 1 THEN (sold_ts - ts) / 3600.0 
                ELSE NULL 
            END) as avg_hours_to_sell,
            AVG(CASE WHEN sold = 1 THEN sold_price ELSE NULL END) as avg_sale_price,
            SUM(CASE WHEN sold = 1 THEN sold_price - price_stack ELSE 0 END) as total_profit
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
        GROUP BY name
        HAVING total_listings >= 3
        ORDER BY sold_count DESC
        LIMIT 10
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $totalListings = (int) $row['total_listings'];
        $soldCount = (int) $row['sold_count'];

        $payload['auctions']['top_items_performance'][] = [
            'name' => $row['name'],
            'total_listings' => $totalListings,
            'sold_count' => $soldCount,
            'success_rate' => $totalListings > 0 ? round(($soldCount / $totalListings) * 100, 1) : 0,
            'avg_hours_to_sell' => $row['avg_hours_to_sell'] ? round((float) $row['avg_hours_to_sell'], 1) : 0,
            'avg_sale_price' => (int) ($row['avg_sale_price'] ?? 0),
            'total_profit' => (int) $row['total_profit'],
        ];
    }
} catch (Throwable $e) {
    error_log("Top items performance error: " . $e->getMessage());
}

// NEW FEATURE 7: Competitor Analysis (ENHANCED - Shows Items)
try {
    $rfKey = $character['realm'] . '-' . ($character['faction'] ?? '');

    $stmt = $pdo->prepare('
        SELECT 
            amb.seller,
            amt.item_key,
            MIN(amb.price_item) as lowest_price,
            MAX(amb.price_item) as highest_price,
            AVG(amb.price_item) as avg_price,
            COUNT(*) as snapshot_count
        FROM auction_market_ts amt
        JOIN auction_market_bands amb ON amb.market_ts_id = amt.id
        WHERE amt.rf_key = :rf_key
          AND amt.ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 7 DAY))
          AND amb.seller IS NOT NULL
          AND amb.seller != :char_name
        GROUP BY amb.seller, amt.item_key
        ORDER BY amb.seller, snapshot_count DESC
    ');
    $stmt->execute([
        ':rf_key' => $rfKey,
        ':char_name' => $character['name']
    ]);

    // Group by seller
    $sellerItems = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $seller = $row['seller'];

        // Try to get item name from your auction history
        list($itemId, $stackSize) = explode(':', $row['item_key']);
        $itemName = null;

        $nameStmt = $pdo->prepare('
            SELECT name FROM auction_owner_rows 
            WHERE item_id = ? LIMIT 1
        ');
        $nameStmt->execute([(int) $itemId]);
        $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
        $itemName = $nameRow['name'] ?? "Item #$itemId";

        if (!isset($sellerItems[$seller])) {
            $sellerItems[$seller] = [];
        }

        $sellerItems[$seller][] = [
            'item_name' => $itemName,
            'item_id' => (int) $itemId,
            'stack_size' => (int) $stackSize,
            'lowest_price' => (int) $row['lowest_price'],
            'highest_price' => (int) $row['highest_price'],
            'avg_price' => (int) $row['avg_price'],
            'snapshot_count' => (int) $row['snapshot_count'],
        ];
    }

    // Format for output - top 10 sellers
    $topSellers = array_slice($sellerItems, 0, 10, true);
    foreach ($topSellers as $seller => $items) {
        $payload['auctions']['competitor_analysis'][] = [
            'seller' => $seller,
            'items' => $items,
            'total_items' => count($items),
            'total_snapshots' => array_sum(array_column($items, 'snapshot_count')),
        ];
    }
} catch (Throwable $e) {
    error_log("Competitor analysis error: " . $e->getMessage());
}

// NEW FEATURE 8: Repost Tracker
try {
    $stmt = $pdo->prepare('
        SELECT 
            name,
            DATE(FROM_UNIXTIME(ts)) as listing_date,
            COUNT(*) as repost_count,
            MAX(CASE WHEN sold = 1 THEN 1 ELSE 0 END) as eventually_sold
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
        GROUP BY name, listing_date
        HAVING repost_count >= 3
        ORDER BY repost_count DESC
        LIMIT 15
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['auctions']['repost_tracker'][] = [
            'name' => $row['name'],
            'date' => $row['listing_date'],
            'repost_count' => (int) $row['repost_count'],
            'eventually_sold' => (int) $row['eventually_sold'] === 1,
        ];
    }
} catch (Throwable $e) {
    error_log("Repost tracker error: " . $e->getMessage());
}

// NEW FEATURE 9: Complete Auction History
try {
    $rfKey = $character['realm'] . '-' . ($character['faction'] ?? '');

    $stmt = $pdo->prepare('
        SELECT 
            aor.name,
            MAX(aor.ts) as last_seen,
            AVG(aor.price_stack / aor.stack_size) as avg_price_per_item,
            MAX(aor.duration_bucket) as duration,
            COUNT(DISTINCT CASE WHEN aor.sold = 1 THEN aor.id END) as sold_count,
            COUNT(DISTINCT CASE WHEN aor.expired = 1 THEN aor.id END) as expired_count,
            AVG(CASE 
                WHEN aor.sold = 1 AND aor.sold_price IS NOT NULL AND aor.sold_price >= aor.price_stack 
                THEN CAST(aor.sold_price AS SIGNED) - CAST(aor.price_stack AS SIGNED)
                WHEN aor.sold = 1 AND aor.sold_price IS NOT NULL AND aor.sold_price < aor.price_stack
                THEN -(CAST(aor.price_stack AS SIGNED) - CAST(aor.sold_price AS SIGNED))
                ELSE NULL 
            END) as avg_profit
        FROM auction_owner_rows aor
        WHERE aor.rf_char_key LIKE :char_key
        GROUP BY aor.name
        ORDER BY last_seen DESC
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $itemName = $row['name'];

        // Get top competitor for this item from market data
        $compStmt = $pdo->prepare('
            SELECT 
                amb.seller,
                AVG(amb.price_item) as avg_comp_price,
                COUNT(*) as snapshot_count
            FROM auction_market_ts amt
            JOIN auction_market_bands amb ON amb.market_ts_id = amt.id
            JOIN auction_owner_rows aor ON aor.item_id = CAST(SUBSTRING_INDEX(amt.item_key, ":", 1) AS UNSIGNED)
            WHERE amt.rf_key = :rf_key
              AND aor.name = :item_name
              AND amb.seller IS NOT NULL
              AND amb.seller != :char_name
            GROUP BY amb.seller
            ORDER BY snapshot_count DESC
            LIMIT 1
        ');
        $compStmt->execute([
            ':rf_key' => $rfKey,
            ':item_name' => $itemName,
            ':char_name' => $character['name']
        ]);
        $comp = $compStmt->fetch(PDO::FETCH_ASSOC);

        $payload['auctions']['auction_history'][] = [
            'name' => $itemName,
            'last_seen' => (int) $row['last_seen'],
            'price' => (int) $row['avg_price_per_item'],
            'duration' => (int) $row['duration'],
            'competitor' => $comp['seller'] ?? null,
            'competitor_price' => $comp ? (int) $comp['avg_comp_price'] : null,
            'sold_count' => (int) $row['sold_count'],
            'expired_count' => (int) $row['expired_count'],
            'avg_profit' => $row['avg_profit'] !== null ? (int) $row['avg_profit'] : null,
        ];
    }
} catch (Throwable $e) {
    error_log("Auction history error: " . $e->getMessage());
}

// Emit payload
echo json_encode($payload);