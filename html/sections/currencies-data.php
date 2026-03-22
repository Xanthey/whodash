<?php
// sections/currencies-data.php (ENHANCED - PRESERVES ALL EXISTING DATA)
declare(strict_types=1);

// Suppress any PHP errors/warnings from breaking JSON
error_reporting(0);
ini_set('display_errors', '0');

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
    $stmt->execute([$character_id]);
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

        $payload['timeseries'] = array_values(array_map(
            fn($p) => ['ts' => (int) $p['ts'], 'value' => (int) $p['value']],
            $moneyData
        ));
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
    $stmt->execute([$character_id]);
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
    $stmt->execute([$character_id]);
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
    $stmt->execute([$character_id]);

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
// Initialize debug array
$payload['debug']['queries_check'] = [
    'top_items_ran' => false,
    'deposit_margin_ran' => false,
];

try {
    // Active Auctions - simple query first
    $stmt = $pdo->prepare('
        SELECT 
            name,
            stack_size,
            price_stack,
            duration_bucket,
            ts,
            sold,
            expired,
            sold_ts,
            sold_price,
            item_id
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
        ORDER BY ts DESC
        LIMIT 100
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);
    $allAuctions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Add to debug
    $payload['debug']['total_auctions'] = count($allAuctions);
    $payload['debug']['char_key_used'] = '%:' . $character['name'];

    // Filter for active only
    $activeAuctions = array_filter($allAuctions, function ($a) {
        if ($a['sold'] != 0 || $a['expired'] != 0)
            return false;
        // Treat as expired if enough time has passed based on duration_bucket
        $bucketHoursMap = [1 => 12, 2 => 24, 3 => 48, 4 => 48];
        $hours = $bucketHoursMap[(int) ($a['duration_bucket'] ?? 0)] ?? 48;
        return ((int) $a['ts'] + ($hours * 3600)) > time();
    });

    $payload['debug']['active_count'] = count($activeAuctions);
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
        array_values($activeAuctions)
    );
    // Auction stats
    $payload['auctions']['stats']['active_count'] = count($activeAuctions);
    $payload['auctions']['stats']['active_value'] = array_sum(array_column($activeAuctions, 'price_stack'));

    // Sold auctions
    $soldAuctions = array_filter($allAuctions, function ($a) {
        return $a['sold'] == 1;
    });
    $payload['auctions']['stats']['sold_count'] = count($soldAuctions);
    $soldValue = 0;
    foreach ($soldAuctions as $auction) {
        $soldValue += (int) ($auction['sold_price'] ?? $auction['price_stack'] ?? 0);
    }
    $payload['auctions']['stats']['sold_value'] = $soldValue;

    // Expired count
    $expiredAuctions = array_filter($allAuctions, function ($a) {
        return $a['expired'] == 1;
    });
    $payload['auctions']['stats']['expired_count'] = count($expiredAuctions);

    // Total auctions historical
    $payload['auctions']['stats']['total_auctions_historical'] = count($allAuctions);
    $payload['auctions']['stats']['total_value_historical'] = array_sum(array_column($allAuctions, 'price_stack'));

    // Best sellers
    $soldByName = [];
    foreach ($soldAuctions as $auction) {
        $name = $auction['name'];
        if (!$name)
            continue; // Skip if no name

        if (!isset($soldByName[$name])) {
            $soldByName[$name] = ['units_sold' => 0, 'total_revenue' => 0, 'name' => $name];
        }
        $soldByName[$name]['units_sold']++;
        $soldByName[$name]['total_revenue'] += (int) ($auction['sold_price'] ?? $auction['price_stack'] ?? 0);
    }

    foreach ($soldByName as $name => $stats) {
        $soldByName[$name]['avg_price'] = $stats['units_sold'] > 0 ?
            (int) ($stats['total_revenue'] / $stats['units_sold']) : 0;
    }

    uasort($soldByName, function ($a, $b) {
        return $b['total_revenue'] - $a['total_revenue'];
    });

    $bestSellers = [];
    foreach ($soldByName as $name => $stats) {
        $bestSellers[] = [
            'name' => $name,
            'units_sold' => $stats['units_sold'],
            'total_revenue' => $stats['total_revenue'],
            'avg_price' => $stats['avg_price'],
        ];
    }
    $payload['auctions']['best_sellers'] = array_slice($bestSellers, 0, 10);
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
    $payload['debug']['queries_check']['deposit_margin_ran'] = true;
    $stmt = $pdo->prepare('
        SELECT 
            name,
            -- sold_price is net-of-AH-cut; price_stack is gross listing price.
            -- Real margin = sold_price - (price_stack * 0.95) is wrong (double-counts).
            -- Use: revenue = sold_price, cost basis = 0 (we only care about gross revenue vs expiry cost).
            -- Margin here = total sold revenue minus estimated deposit burn on expired listings.
            SUM(CASE WHEN sold = 1 THEN COALESCE(sold_price, price_stack) ELSE 0 END) as total_revenue,
            SUM(CASE WHEN sold = 1 THEN 1 ELSE 0 END) as sold_count,
            COUNT(CASE WHEN expired = 1 THEN 1 END) as expire_count,
            AVG(duration_bucket) as avg_duration_bucket,
            AVG(price_stack) as avg_listing_price,
            COUNT(*) as total_count
        FROM auction_owner_rows
        WHERE rf_char_key LIKE :char_key
        GROUP BY name
        HAVING total_count >= 2
        ORDER BY total_count DESC, sold_count DESC
        LIMIT 25
    ');
    $stmt->execute([':char_key' => '%:' . $character['name']]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $avgPrice = (int) $row['avg_listing_price'];
        $expireCount = (int) $row['expire_count'];
        $soldCount = (int) $row['sold_count'];
        $totalCount = (int) $row['total_count'];
        $avgDuration = round((float) ($row['avg_duration_bucket'] ?? 2));

        // Estimate deposit burn: vendor price ~10% of listing, deposit is fraction of that
        $estimatedVendorPrice = (int) ($avgPrice * 0.1);
        $depositMultiplier = [1 => 0.15, 2 => 0.30, 3 => 0.60][(int) $avgDuration] ?? 0.15;
        $depositPerListing = (int) ($estimatedVendorPrice * $depositMultiplier);
        $totalDepositBurn = $depositPerListing * $expireCount;

        $totalRevenue = (int) $row['total_revenue'];

        $payload['auctions']['deposit_vs_margin'][] = [
            'name' => $row['name'],
            'total_margin' => $totalRevenue,   // renamed for JS compat: revenue as proxy for margin
            'total_revenue' => $totalRevenue,
            'deposit_burn' => $totalDepositBurn,
            'expire_count' => $expireCount,
            'sold_count' => $soldCount,
            'total_count' => $totalCount,
            'sell_rate' => $totalCount > 0 ? round(($soldCount / $totalCount) * 100, 1) : 0,
            'avg_listing_price' => $avgPrice,
        ];
    }
} catch (Throwable $e) {
    error_log("Deposit burn vs margin error: " . $e->getMessage());
}

// NEW FEATURE 4: Price History (ENHANCED - Shows Market Prices)
try {
    $rfKey = $character['realm'] . '-' . ($character['faction'] ?? '');

    // Get the character's most-listed items (by total listing count).
    // Market data is stored per-item (item_id:1) regardless of actual stack size,
    // so we match on item_id:1 in auction_market_ts — NOT on item_id:stack_size.
    $stmt = $pdo->prepare('
        SELECT 
            aor.item_id,
            MAX(aor.name) as name,
            COUNT(*) as listing_count,
            CONCAT(aor.item_id, ":1") as market_key
        FROM auction_owner_rows aor
        WHERE aor.rf_char_key LIKE :char_key
        GROUP BY aor.item_id
        ORDER BY listing_count DESC
        LIMIT 10
    ');
    $stmt->execute([
        ':char_key' => '%:' . $character['name'],
    ]);
    $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($topItems as $item) {
        // Get market price history for this item (market uses item_id:1 keys)
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
            ':item_key' => $item['market_key']
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

        // Fallback: if no market data, use sold prices from auction_owner_rows
        if (empty($history)) {
            $stmt3 = $pdo->prepare('
                SELECT 
                    DATE(FROM_UNIXTIME(sold_ts)) as date,
                    AVG(sold_price / stack_size) as avg_price,
                    MIN(sold_price / stack_size) as min_price,
                    MAX(sold_price / stack_size) as max_price
                FROM auction_owner_rows
                WHERE item_id = :item_id
                  AND sold = 1
                  AND sold_ts IS NOT NULL
                  AND rf_char_key = :char_key
                GROUP BY date
                ORDER BY date ASC
                LIMIT 30
            ');
            $stmt3->execute([':item_id' => (int) $item['item_id'], ':char_key' => $charKey]);
            foreach ($stmt3->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $history[] = [
                    'date' => $row['date'],
                    'min_price' => (int) $row['min_price'],
                    'max_price' => (int) $row['max_price'],
                    'avg_price' => (int) $row['avg_price'],
                    'my_price' => (int) $row['avg_price'],
                    'competitor_count' => 0,
                    'source' => 'sold',
                ];
            }
        }

        if (!empty($history)) {
            $payload['auctions']['price_history'][] = [
                'item_name' => $item['name'],
                'item_id' => (int) $item['item_id'],
                'history' => $history,
            ];
        }
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
    $payload['debug']['queries_check']['top_items_ran'] = true;
    $stmt = $pdo->prepare('
        SELECT 
            name,
            COUNT(*) as total_listings,
            SUM(CASE WHEN sold = 1 THEN 1 ELSE 0 END) as sold_count,
            SUM(CASE WHEN expired = 1 THEN 1 ELSE 0 END) as expired_count,
            AVG(CASE 
                WHEN sold = 1 THEN (sold_ts - ts) / 3600.0 
                ELSE NULL 
            END) as avg_hours_to_sell,
            AVG(CASE WHEN sold = 1 THEN sold_price ELSE NULL END) as avg_sale_price,
            SUM(CASE WHEN sold = 1 THEN sold_price - price_stack ELSE 0 END) as total_profit
        FROM auction_owner_rows
        WHERE rf_char_key = :char_key
        GROUP BY name
        HAVING total_listings >= 1
        ORDER BY total_listings DESC, sold_count DESC
        LIMIT 20
    ');
    $stmt->execute([':char_key' => $charKey]);

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

    // Extended to 90-day window so characters with sparse market data still show results
    $stmt = $pdo->prepare("
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
          AND amt.ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 90 DAY))
          AND amb.seller IS NOT NULL
          AND amb.seller > ''
          AND amb.seller != 'Unknown'
          AND amb.seller != :char_name
        GROUP BY amb.seller, amt.item_key
        ORDER BY snapshot_count DESC
    ");
    $stmt->execute([
        ':rf_key' => $rfKey,
        ':char_name' => $character['name']
    ]);

    // Group by seller — resolve item names from auction_owner_rows cache
    $nameCache = [];
    $sellerItems = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $seller = $row['seller'];
        list($itemId, $stackSize) = explode(':', $row['item_key']);
        $itemId = (int) $itemId;

        if (!array_key_exists($itemId, $nameCache)) {
            $nameStmt = $pdo->prepare('SELECT name FROM auction_owner_rows WHERE item_id = ? LIMIT 1');
            $nameStmt->execute([$itemId]);
            $nameRow = $nameStmt->fetch(PDO::FETCH_ASSOC);
            $nameCache[$itemId] = $nameRow['name'] ?? "Item #$itemId";
        }

        if (!isset($sellerItems[$seller])) {
            $sellerItems[$seller] = ['items' => [], 'total_snapshots' => 0];
        }

        $snaps = (int) $row['snapshot_count'];
        $sellerItems[$seller]['total_snapshots'] += $snaps;
        $sellerItems[$seller]['items'][] = [
            'item_name' => $nameCache[$itemId],
            'item_id' => $itemId,
            'stack_size' => (int) $stackSize,
            'lowest_price' => (int) $row['lowest_price'],
            'highest_price' => (int) $row['highest_price'],
            'avg_price' => (int) $row['avg_price'],
            'snapshot_count' => $snaps,
        ];
    }

    // Sort by total activity descending, then take top 10
    uasort($sellerItems, fn($a, $b) => $b['total_snapshots'] - $a['total_snapshots']);
    $topSellers = array_slice($sellerItems, 0, 10, true);

    foreach ($topSellers as $seller => $data) {
        $payload['auctions']['competitor_analysis'][] = [
            'seller' => $seller,
            'items' => $data['items'],
            'total_items' => count($data['items']),
            'total_snapshots' => $data['total_snapshots'],
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
// DEBUG: Check what's actually in the payload
$payload['debug']['best_sellers_count'] = count($payload['auctions']['best_sellers']);
$payload['debug']['top_items_perf_count'] = count($payload['auctions']['top_items_performance']);
$payload['debug']['undercut_count'] = count($payload['auctions']['undercut_opportunities']);
$payload['debug']['price_history_count'] = count($payload['auctions']['price_history']);
$payload['debug']['sold_auctions_sample'] = array_slice($soldAuctions, 0, 2);
$payload['debug']['market_data_count'] = $pdo->query("SELECT COUNT(*) FROM auction_market_ts WHERE rf_key LIKE '{$character['realm']}-{$character['faction']}%'")->fetchColumn();
$payload['debug']['sample_market_keys'] = $pdo->query("SELECT item_key FROM auction_market_ts WHERE rf_key = '{$character['realm']}-{$character['faction']}' LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
// Emit payload
echo json_encode($payload);