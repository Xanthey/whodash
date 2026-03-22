<?php
// sections/bazaar-fortune.php - Mystical Fortune Teller analytics and predictions
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Suppress PHP warnings that could corrupt JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Mystical fortune teller messages
$mystical_messages = [
    'welcome' => [
        "Ahh, welcome traveler... the spirits have been expecting you...",
        "Step closer to the candlelight... let me peer into your financial future...",
        "The cards reveal much about your journey through the Bazaar...",
        "The crystal ball grows dim, but your fortune burns bright..."
    ],
    'wealth_rising' => [
        "The fates smile upon your ventures! I see gold flowing like a river...",
        "Your coin purse grows heavy with promise...",
        "Fortuitous winds blow gold into your coffers!",
        "The stars align in your favor, merchant!"
    ],
    'wealth_falling' => [
        "Dark clouds gather over your treasury... beware lean times ahead...",
        "The spirits whisper of challenges to come...",
        "Your gold ebbs like the tide... conserve your resources...",
        "Ill winds may test your purse strings..."
    ],
    'wealth_stable' => [
        "Your fortune holds steady, like the ancient oak...",
        "The cosmic balance favors stability in your endeavors...",
        "Neither fortune nor misfortune clouds your path...",
        "The spirits see equilibrium in your financial affairs..."
    ]
];

$payload = [
    'forecast' => [
        'current_wealth' => 0,
        'predicted_7d' => 0,
        'predicted_30d' => 0,
        'growth_rate_daily' => 0,
        'trend' => 'stable',
        'mystical_message' => $mystical_messages['welcome'][array_rand($mystical_messages['welcome'])]
    ],
    'wealth_history' => [],
    'auction_trends' => [],
    'top_items' => [],
    'character_fortunes' => [],
    'market_opportunities' => [],
    'price_trends' => [],
    'cosmic_insights' => []
];

try {
    // Get user's characters
    $stmt = $pdo->prepare('SELECT id, name FROM characters WHERE user_id = ? ORDER BY name');
    $stmt->execute([$user_id]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($characters)) {
        echo json_encode($payload);
        exit;
    }

    $character_ids = array_column($characters, 'id');
    $character_names = array_column($characters, 'name', 'id');
    $placeholders = implode(',', array_fill(0, count($character_ids), '?'));

    // ============================================================================
    // WEALTH FORECAST - The Crystal Ball Reveals...
    // ============================================================================

    // Get current total wealth
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
    ");
    $stmt->execute($character_ids);

    $current_total = 0;
    $char_wealth = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $gold = (int) ($row['gold'] ?? 0);
        $current_total += $gold;
        $char_id = (int) $row['character_id'];
        $char_wealth[$char_id] = $gold;
    }
    $payload['forecast']['current_wealth'] = $current_total;

    // Get wealth history (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(FROM_UNIXTIME(ts)) as date,
            SUM(value) as total_gold
        FROM series_money
        WHERE character_id IN ($placeholders)
            AND ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
        GROUP BY DATE(FROM_UNIXTIME(ts))
        ORDER BY date ASC
    ");
    $stmt->execute($character_ids);

    $history_data = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $date = $row['date'];
        $gold = (int) ($row['total_gold'] ?? 0);
        $history_data[$date] = $gold;

        $payload['wealth_history'][] = [
            'date' => $date,
            'total_gold' => $gold
        ];
    }

    // Calculate growth rate and predictions
    if (count($history_data) >= 2) {
        $dates = array_keys($history_data);
        $first_date = reset($dates);
        $last_date = end($dates);
        $first_value = $history_data[$first_date];
        $last_value = $history_data[$last_date];

        $days_diff = max(1, count($dates) - 1);
        $total_growth = $last_value - $first_value;
        $daily_growth = $total_growth / $days_diff;

        $payload['forecast']['growth_rate_daily'] = round($daily_growth, 2);
        $payload['forecast']['predicted_7d'] = round($current_total + ($daily_growth * 7));
        $payload['forecast']['predicted_30d'] = round($current_total + ($daily_growth * 30));

        // Determine trend and add mystical message
        if ($daily_growth > 1000) {
            $payload['forecast']['trend'] = 'rising';
            $payload['forecast']['mystical_message'] = $mystical_messages['wealth_rising'][array_rand($mystical_messages['wealth_rising'])];
        } elseif ($daily_growth < -1000) {
            $payload['forecast']['trend'] = 'falling';
            $payload['forecast']['mystical_message'] = $mystical_messages['wealth_falling'][array_rand($mystical_messages['wealth_falling'])];
        } else {
            $payload['forecast']['trend'] = 'stable';
            $payload['forecast']['mystical_message'] = $mystical_messages['wealth_stable'][array_rand($mystical_messages['wealth_stable'])];
        }
    }

    // ============================================================================
    // AUCTION TRENDS
    // ============================================================================

    // Get auction sales trends (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(FROM_UNIXTIME(sold_ts)) as date,
            COUNT(*) as sales_count,
            SUM(sold_price) as total_sales
        FROM auction_owner_rows aor
        INNER JOIN characters c ON SUBSTRING_INDEX(aor.rf_char_key, ':', -1) = c.name
        WHERE c.user_id = ?
            AND aor.sold = 1
            AND aor.sold_ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
        GROUP BY DATE(FROM_UNIXTIME(sold_ts))
        ORDER BY date ASC
    ");
    $stmt->execute([$user_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['auction_trends'][] = [
            'date' => $row['date'],
            'sales_count' => (int) ($row['sales_count'] ?? 0),
            'total_sales' => (int) ($row['total_sales'] ?? 0)
        ];
    }

    // ============================================================================
    // TOP EARNING ITEMS - The Cards of Fortune
    // ============================================================================

    $stmt = $pdo->prepare("
        SELECT 
            aor.name as item_name,
            COUNT(*) as sales_count,
            SUM(aor.sold_price) as total_earnings,
            AVG(aor.sold_price / NULLIF(aor.stack_size, 0)) as avg_unit_price
        FROM auction_owner_rows aor
        INNER JOIN characters c ON SUBSTRING_INDEX(aor.rf_char_key, ':', -1) = c.name
        WHERE c.user_id = ?
            AND aor.sold = 1
            AND aor.sold_ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
        GROUP BY aor.name
        ORDER BY total_earnings DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $payload['top_items'][] = [
            'item_name' => $row['item_name'],
            'sales_count' => (int) ($row['sales_count'] ?? 0),
            'total_earnings' => (int) ($row['total_earnings'] ?? 0),
            'avg_unit_price' => round((float) ($row['avg_unit_price'] ?? 0), 2)
        ];
    }

    // ============================================================================
    // CHARACTER FORTUNES - Each Soul's Destiny
    // ============================================================================

    $stmt = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            COUNT(aor.id) as sales_count,
            COALESCE(SUM(aor.sold_price), 0) as total_earned
        FROM characters c
        LEFT JOIN auction_owner_rows aor ON SUBSTRING_INDEX(aor.rf_char_key, ':', -1) = c.name
            AND aor.sold = 1
            AND aor.sold_ts >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
        WHERE c.user_id = ?
        GROUP BY c.id, c.name
        ORDER BY total_earned DESC
    ");
    $stmt->execute([$user_id]);

    $max_earned = 0;
    $char_earnings = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $earned = (int) ($row['total_earned'] ?? 0);
        $sales = (int) ($row['sales_count'] ?? 0);
        $char_id = (int) $row['id'];
        $char_earnings[$char_id] = $earned;

        if ($earned > $max_earned) {
            $max_earned = $earned;
        }
    }

    // Now create character fortunes with mystical descriptions
    $fortune_descriptions = [
        'champion' => "The spirits favor this one above all others! A champion of commerce!",
        'strong' => "This soul walks a prosperous path...",
        'moderate' => "Fortune smiles, though not with her brightest countenance...",
        'struggling' => "Dark clouds linger, but dawn approaches...",
        'dormant' => "This one rests... awaiting fortune's call..."
    ];

    foreach ($char_earnings as $char_id => $earned) {
        $char_name = $character_names[$char_id] ?? 'Unknown';
        $sales = 0; // We'll need to recalculate this

        // Determine fortune level
        if ($max_earned > 0) {
            $ratio = $earned / $max_earned;
            if ($ratio >= 0.8) {
                $fortune_level = 'champion';
            } elseif ($ratio >= 0.5) {
                $fortune_level = 'strong';
            } elseif ($ratio >= 0.2) {
                $fortune_level = 'moderate';
            } elseif ($earned > 0) {
                $fortune_level = 'struggling';
            } else {
                $fortune_level = 'dormant';
            }
        } else {
            $fortune_level = 'dormant';
        }

        $payload['character_fortunes'][] = [
            'character_name' => $char_name,
            'total_earned' => $earned,
            'current_wealth' => $char_wealth[$char_id] ?? 0,
            'fortune_level' => $fortune_level,
            'mystical_reading' => $fortune_descriptions[$fortune_level]
        ];
    }

    // ============================================================================
    // PRICE TRENDS (items with price history)
    // ============================================================================

    $stmt = $pdo->prepare("
        SELECT 
            item_name,
            avg_price,
            min_price,
            max_price,
            sale_count,
            recorded_date
        FROM bazaar_price_history
        WHERE user_id = ?
            AND recorded_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ORDER BY recorded_date DESC, item_name ASC
        LIMIT 100
    ");
    $stmt->execute([$user_id]);

    $price_map = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $item = $row['item_name'];
        if (!isset($price_map[$item])) {
            $price_map[$item] = [];
        }
        $price_map[$item][] = [
            'date' => $row['recorded_date'],
            'avg_price' => (int) ($row['avg_price'] ?? 0),
            'min_price' => (int) ($row['min_price'] ?? 0),
            'max_price' => (int) ($row['max_price'] ?? 0),
            'sale_count' => (int) ($row['sale_count'] ?? 0)
        ];
    }

    foreach ($price_map as $item_name => $history) {
        // Calculate trend
        if (count($history) >= 2) {
            $first_price = $history[count($history) - 1]['avg_price'];
            $last_price = $history[0]['avg_price'];
            $price_change = $last_price - $first_price;
            $percent_change = $first_price > 0 ? ($price_change / $first_price) * 100 : 0;

            $trend = 'stable';
            if ($percent_change > 10)
                $trend = 'rising';
            elseif ($percent_change < -10)
                $trend = 'falling';

            $payload['price_trends'][] = [
                'item_name' => $item_name,
                'current_avg' => $last_price,
                'price_change' => $price_change,
                'percent_change' => round($percent_change, 2),
                'trend' => $trend,
                'history' => $history
            ];
        }
    }

    // ============================================================================
    // MARKET OPPORTUNITIES - The Spirits Whisper of Opportunities
    // ============================================================================

    // Find items with significant price changes
    foreach ($payload['price_trends'] as $trend) {
        if ($trend['trend'] === 'falling' && $trend['percent_change'] < -15) {
            $payload['market_opportunities'][] = [
                'type' => 'buy',
                'item_name' => $trend['item_name'],
                'reason' => 'Price dropped ' . abs(round($trend['percent_change'])) . '%',
                'current_price' => $trend['current_avg'],
                'potential' => 'good_buy',
                'mystical_advice' => '✨ The spirits suggest you acquire this treasure while it is undervalued...'
            ];
        } elseif ($trend['trend'] === 'rising' && $trend['percent_change'] > 15) {
            $payload['market_opportunities'][] = [
                'type' => 'sell',
                'item_name' => $trend['item_name'],
                'reason' => 'Price increased ' . round($trend['percent_change']) . '%',
                'current_price' => $trend['current_avg'],
                'potential' => 'good_sell',
                'mystical_advice' => '🌟 Strike while the iron is hot! The market favors sellers of this item...'
            ];
        }
    }

    // ============================================================================
    // COSMIC INSIGHTS - The Universe Reveals Patterns
    // ============================================================================

    // Add mystical insights based on data patterns
    $insights = [];

    // Insight about total wealth
    if ($current_total > 0) {
        if ($current_total >= 100000) {
            $insights[] = [
                'type' => 'wealth',
                'icon' => '💰',
                'message' => 'Your treasury overflows with riches! You stand among the Bazaar\'s elite merchants.'
            ];
        } elseif ($current_total >= 50000) {
            $insights[] = [
                'type' => 'wealth',
                'icon' => '💰',
                'message' => 'A comfortable fortune rests in your coffers. The path to greatness lies before you.'
            ];
        } else {
            $insights[] = [
                'type' => 'wealth',
                'icon' => '💫',
                'message' => 'Every grand fortune begins with a single copper. Your journey has only just begun...'
            ];
        }
    }

    // Insight about selling activity
    $total_sales = array_reduce($payload['top_items'], function ($carry, $item) {
        return $carry + $item['sales_count'];
    }, 0);

    if ($total_sales >= 100) {
        $insights[] = [
            'type' => 'activity',
            'icon' => '⚡',
            'message' => 'The Bazaar buzzes with your activity! ' . $total_sales . ' successful transactions speak of your industry.'
        ];
    } elseif ($total_sales >= 50) {
        $insights[] = [
            'type' => 'activity',
            'icon' => '📈',
            'message' => 'Your presence grows stronger in the Bazaar. ' . $total_sales . ' sales - a respectable showing!'
        ];
    } elseif ($total_sales > 0) {
        $insights[] = [
            'type' => 'activity',
            'icon' => '🌱',
            'message' => 'First steps into commerce. With ' . $total_sales . ' sales, your reputation begins to form...'
        ];
    }

    // Insight about diversity
    $unique_items = count($payload['top_items']);
    if ($unique_items >= 10) {
        $insights[] = [
            'type' => 'diversity',
            'icon' => '🎭',
            'message' => 'Your diverse portfolio speaks of wisdom! ' . $unique_items . ' different items traded shows adaptability.'
        ];
    }

    // Insight about opportunities
    $opportunities = count($payload['market_opportunities']);
    if ($opportunities > 0) {
        $insights[] = [
            'type' => 'opportunity',
            'icon' => '🔮',
            'message' => 'The winds of fortune shift! I see ' . $opportunities . ' market opportunities awaiting the clever merchant...'
        ];
    }

    // Add time-based mystical message
    $hour = (int) date('H');
    if ($hour >= 0 && $hour < 6) {
        $insights[] = [
            'type' => 'time',
            'icon' => '🌙',
            'message' => 'The witching hour favors bold ventures... what secrets will dawn reveal?'
        ];
    } elseif ($hour >= 6 && $hour < 12) {
        $insights[] = [
            'type' => 'time',
            'icon' => '🌅',
            'message' => 'Morning light illuminates new opportunities. Seize the day, merchant!'
        ];
    } elseif ($hour >= 12 && $hour < 18) {
        $insights[] = [
            'type' => 'time',
            'icon' => '☀️',
            'message' => 'The sun reaches its zenith. Your fortunes bloom in the light of day.'
        ];
    } else {
        $insights[] = [
            'type' => 'time',
            'icon' => '🌆',
            'message' => 'As dusk approaches, reflect on the day\'s dealings. Tomorrow brings fresh fortune...'
        ];
    }

    $payload['cosmic_insights'] = $insights;

} catch (Throwable $e) {
    error_log("Bazaar fortune error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error loading fortune teller data']);
    exit;
}

echo json_encode($payload);