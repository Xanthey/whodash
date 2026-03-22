<?php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

session_start();

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

$quality_names = [0 => 'Poor', 1 => 'Common', 2 => 'Uncommon', 3 => 'Rare', 4 => 'Epic', 5 => 'Legendary'];

try {
    $stmt = $pdo->prepare('SELECT id, name, realm, faction, class_file AS class FROM characters WHERE user_id = ?');
    $stmt->execute([$user_id]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($characters)) {
        echo json_encode(['summary' => ['total_items' => 0, 'total_characters' => 0], 'characters' => [], 'quality_breakdown' => [], 'top_items' => []]);
        exit;
    }

    $char_ids = array_column($characters, 'id');
    $placeholders = implode(',', array_fill(0, count($char_ids), '?'));

    $stmt = $pdo->prepare("SELECT character_id, quality, COUNT(*) AS item_count FROM containers_bag WHERE character_id IN ($placeholders) AND item_id IS NOT NULL GROUP BY character_id, quality");
    $stmt->execute($char_ids);
    $bag_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT character_id, quality, COUNT(*) AS item_count FROM containers_bank WHERE character_id IN ($placeholders) AND item_id IS NOT NULL GROUP BY character_id, quality");
    $stmt->execute($char_ids);
    $bank_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT name, item_id, quality, icon, SUM(count) AS total_count, COUNT(DISTINCT character_id) AS held_by FROM containers_bag WHERE character_id IN ($placeholders) AND name IS NOT NULL AND item_id IS NOT NULL GROUP BY item_id, name, quality, icon ORDER BY total_count DESC LIMIT 20");
    $stmt->execute($char_ids);
    $top_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $char_map = [];
    foreach ($characters as $c) {
        $char_map[$c['id']] = ['id' => (int) $c['id'], 'name' => $c['name'], 'realm' => $c['realm'], 'class' => $c['class'], 'faction' => $c['faction'], 'bag' => ['total' => 0, 'by_quality' => []], 'bank' => ['total' => 0, 'by_quality' => []], 'bag_fill_pct' => 0];
    }

    $global_quality = [];
    foreach ($bag_rows as $row) {
        $cid = (int) $row['character_id'];
        if (!isset($char_map[$cid]))
            continue;
        $q = (int) $row['quality'];
        $cnt = (int) $row['item_count'];
        $char_map[$cid]['bag']['total'] += $cnt;
        $char_map[$cid]['bag']['by_quality'][$q] = ($char_map[$cid]['bag']['by_quality'][$q] ?? 0) + $cnt;
        $global_quality[$q] = ($global_quality[$q] ?? 0) + $cnt;
    }
    foreach ($bank_rows as $row) {
        $cid = (int) $row['character_id'];
        if (!isset($char_map[$cid]))
            continue;
        $q = (int) $row['quality'];
        $cnt = (int) $row['item_count'];
        $char_map[$cid]['bank']['total'] += $cnt;
        $char_map[$cid]['bank']['by_quality'][$q] = ($char_map[$cid]['bank']['by_quality'][$q] ?? 0) + $cnt;
        $global_quality[$q] = ($global_quality[$q] ?? 0) + $cnt;
    }

    $total_items = 0;
    foreach ($char_map as &$c) {
        $c['bag_fill_pct'] = min(100, (int) round(($c['bag']['total'] / 80) * 100));
        $nb = $nk = [];
        foreach ($c['bag']['by_quality'] as $q => $n)
            $nb[$quality_names[$q] ?? "Q$q"] = $n;
        foreach ($c['bank']['by_quality'] as $q => $n)
            $nk[$quality_names[$q] ?? "Q$q"] = $n;
        $c['bag']['by_quality'] = $nb;
        $c['bank']['by_quality'] = $nk;
        $total_items += $c['bag']['total'] + $c['bank']['total'];
    }
    unset($c);

    $qb = [];
    foreach ($global_quality as $q => $n)
        $qb[] = ['quality' => $quality_names[$q] ?? ("Q$q"), 'quality_id' => $q, 'count' => $n];
    usort($qb, fn($a, $b) => $b['quality_id'] - $a['quality_id']);

    foreach ($top_items as &$item) {
        $item['item_id'] = (int) $item['item_id'];
        $item['quality'] = (int) $item['quality'];
        $item['total_count'] = (int) $item['total_count'];
        $item['held_by'] = (int) $item['held_by'];
        $item['quality_name'] = $quality_names[$item['quality']] ?? 'Unknown';
    }
    unset($item);

    echo json_encode(['summary' => ['total_items' => $total_items, 'total_characters' => count($characters), 'bag_slot_baseline' => 80], 'characters' => array_values($char_map), 'quality_breakdown' => $qb, 'top_items' => $top_items], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'file' => basename($e->getFile()), 'line' => $e->getLine()]);
}