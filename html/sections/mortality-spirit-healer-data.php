<?php
// sections/mortality-spirit-healer-data.php - Full death archive search for Spirit Healer tab
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$character_id = $_GET['character_id'] ?? null;
if (!$character_id || !is_numeric($character_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid character ID']);
    exit;
}

$character_id = (int) $character_id;

// Optional search/filter params
$search = trim($_GET['search'] ?? '');
$date_from = $_GET['date_from'] ?? '';   // YYYY-MM-DD
$date_to = $_GET['date_to'] ?? '';     // YYYY-MM-DD
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

try {
    // Verify character access
    $character = null;
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT name FROM characters WHERE id = ? AND user_id = ?');
        $stmt->execute([$character_id, $_SESSION['user_id']]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$character) {
        $stmt = $pdo->prepare('SELECT name FROM characters WHERE id = ? AND visibility = "PUBLIC"');
        $stmt->execute([$character_id]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not accessible']);
        exit;
    }

    // ===== BUILD DYNAMIC WHERE CLAUSE =====
    $where = ['character_id = ?'];
    $params = [$character_id];

    // Full-text search across all meaningful string fields
    if ($search !== '') {
        $like = '%' . $search . '%';
        $where[] = '(
            killer_name    LIKE ? OR
            killer_spell   LIKE ? OR
            killer_type    LIKE ? OR
            killer_method  LIKE ? OR
            zone           LIKE ? OR
            subzone        LIKE ?
        )';
        // One param per LIKE placeholder
        array_push($params, $like, $like, $like, $like, $like, $like);
    }

    // Date range filter (inclusive)
    if ($date_from !== '') {
        $where[] = 'ts >= ?';
        $params[] = strtotime($date_from . ' 00:00:00');
    }
    if ($date_to !== '') {
        $where[] = 'ts <= ?';
        $params[] = strtotime($date_to . ' 23:59:59');
    }

    $whereSQL = implode(' AND ', $where);

    // ===== COUNT TOTAL MATCHES =====
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM deaths WHERE $whereSQL");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ===== FETCH PAGINATED RESULTS =====
    $dataParams = array_merge($params, [$per_page, $offset]);
    $stmt = $pdo->prepare("
        SELECT
            id,
            killer_name,
            killer_type,
            killer_spell,
            killer_damage,
            killer_confidence,
            killer_method,
            zone,
            subzone,
            x,
            y,
            ts,
            level,
            durability_loss,
            rez_time,
            combat_duration,
            attacker_count
        FROM deaths
        WHERE $whereSQL
        ORDER BY ts DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($dataParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ===== FORMAT DEATHS =====
    $deaths = array_map(function ($d) {
        return [
            'id' => (int) $d['id'],
            'killer' => $d['killer_name'] ?? null,
            'killer_type' => $d['killer_type'] ?? 'unknown',
            'spell' => $d['killer_spell'] ?? null,
            'damage' => $d['killer_damage'] !== null ? (int) $d['killer_damage'] : null,
            'confidence' => $d['killer_confidence'] ?? 'unknown',
            'method' => $d['killer_method'] ?? null,
            'zone' => $d['zone'] ?? null,
            'subzone' => $d['subzone'] ?? null,
            'x' => $d['x'] !== null ? round((float) $d['x'], 3) : null,
            'y' => $d['y'] !== null ? round((float) $d['y'], 3) : null,
            'timestamp' => (int) $d['ts'],
            'level' => (int) ($d['level'] ?? 1),
            'durability_loss' => $d['durability_loss'] !== null ? round((float) $d['durability_loss'], 1) : null,
            'rez_time' => $d['rez_time'] !== null ? (int) $d['rez_time'] : null,
            'combat_duration' => $d['combat_duration'] !== null ? round((float) $d['combat_duration'], 1) : null,
            'attacker_count' => $d['attacker_count'] !== null ? (int) $d['attacker_count'] : 1,
        ];
    }, $rows);

    echo json_encode([
        'deaths' => $deaths,
        'total' => $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => (int) ceil($total / $per_page),
        'search' => $search,
        'date_from' => $date_from,
        'date_to' => $date_to,
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log('Spirit Healer data error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>