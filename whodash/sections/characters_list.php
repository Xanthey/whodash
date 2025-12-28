<?php
/**
 * List all characters for the signed-in user in a sort that matches "last updated first".
 * Response:
 *   { ok: true, characters: [ { id, name, updated_at }, ... ] }
 */

declare(strict_types=1);
session_start();
header('Content-Type: application/json');

//////////////////// DB bootstrap ////////////////////
$host = getenv('DB_HOST') ?: 'backend';
$db = getenv('DB_NAME') ?: 'whodat';
$user = getenv('DB_USER') ?: 'whodatuser';
$pass = getenv('DB_PASSWORD') ?: 'whodatpass';
$charset = 'utf8mb4';
$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Database connection error']);
    exit;
}

//////////////////// Auth ////////////////////
if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Unauthorized']);
    exit;
}
$userId = (int) $_SESSION['user_id'];

//////////////////// Fetch list ////////////////////
try {
    $stmt = $pdo->prepare("
    SELECT id, name, updated_at
    FROM characters
    WHERE user_id = ?
    ORDER BY (updated_at IS NULL), updated_at DESC, id DESC
  ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    // Ensure types (id as int); updated_at left as string (MySQL DATETIME/NULL)
    $characters = array_map(function (array $r) {
        return [
            'id' => (int) $r['id'],
            'name' => $r['name'] ?? null,
            'updated_at' => $r['updated_at'] ?? null,
        ];
    }, $rows ?: []);

    echo json_encode(['ok' => true, 'characters' => $characters]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error']);
}
