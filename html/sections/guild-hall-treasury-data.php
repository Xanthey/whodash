<?php
// sections/guild-hall-treasury-data.php
// Treasury stats derived from balance deltas in guild_bank_snapshots (not logs).
// Increase in balance = deposit inferred. Decrease = withdrawal inferred.

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../guild_helpers.php';

header('Content-Type: application/json');
session_start();

if (!isset($pdo)) {
    require_once __DIR__ . '/../db.php';
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

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
        'current_balance' => 0,
        'total_deposits' => 0,
        'total_withdrawals' => 0,
        'net_change' => 0,
        'timeline' => []
    ];

    // ============================================================================
    // LOAD ALL SNAPSHOTS IN TIME ORDER
    // ============================================================================
    $stmt = $pdo->prepare("
        SELECT 
            snapshot_ts AS ts,
            money_copper AS balance,
            DATE(FROM_UNIXTIME(snapshot_ts)) AS date
        FROM guild_bank_snapshots
        WHERE guild_id = ?
        ORDER BY snapshot_ts ASC
    ");
    $stmt->execute([$guild_id]);
    $snapshots = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($snapshots)) {
        echo json_encode($payload);
        exit;
    }

    // ============================================================================
    // DEDUPLICATE: Keep only the last snapshot per real-world day
    // ============================================================================
    $byDay = [];
    foreach ($snapshots as $snap) {
        $byDay[$snap['date']] = $snap; // last one of the day wins
    }
    $dailySnaps = array_values($byDay);

    // ============================================================================
    // CURRENT BALANCE = latest snapshot
    // ============================================================================
    $latest = end($dailySnaps);
    $payload['current_balance'] = (int) $latest['balance'];

    // ============================================================================
    // CALCULATE DEPOSITS & WITHDRAWALS FROM BALANCE DELTAS
    // A balance increase between two snapshots = deposit(s) occurred
    // A balance decrease between two snapshots = withdrawal(s) occurred
    // ============================================================================
    $totalDeposits = 0;
    $totalWithdrawals = 0;
    $timeline = [];

    // First point has no delta
    $timeline[] = [
        'ts' => (int) $dailySnaps[0]['ts'],
        'balance' => (int) $dailySnaps[0]['balance'],
        'date' => $dailySnaps[0]['date'],
        'deposits' => 0,
        'withdrawals' => 0,
    ];

    for ($i = 1; $i < count($dailySnaps); $i++) {
        $prev = (int) $dailySnaps[$i - 1]['balance'];
        $curr = (int) $dailySnaps[$i]['balance'];
        $delta = $curr - $prev;

        $dep = 0;
        $with = 0;

        if ($delta > 0) {
            $dep = $delta;
            $totalDeposits += $delta;
        } elseif ($delta < 0) {
            $with = abs($delta);
            $totalWithdrawals += abs($delta);
        }

        $timeline[] = [
            'ts' => (int) $dailySnaps[$i]['ts'],
            'balance' => $curr,
            'date' => $dailySnaps[$i]['date'],
            'deposits' => $dep,
            'withdrawals' => $with,
        ];
    }

    $payload['total_deposits'] = $totalDeposits;
    $payload['total_withdrawals'] = $totalWithdrawals;
    $payload['net_change'] = $totalDeposits - $totalWithdrawals;
    $payload['timeline'] = $timeline;

    if (!empty($timeline)) {
        $payload['timeline_start_date'] = $timeline[0]['date'];
        $payload['timeline_end_date'] = $timeline[count($timeline) - 1]['date'];
        $payload['timeline_days'] = count($timeline);
    }

    echo json_encode($payload);

} catch (PDOException $e) {
    error_log("Treasury data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}