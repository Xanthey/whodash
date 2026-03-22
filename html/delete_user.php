<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/db.php'; // provides $pdo with ERRMODE_EXCEPTION

header('Content-Type: application/json');

function json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_ok(array $payload = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => true] + $payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Optional: enforce POST + CSRF if called from a browser form.
// if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
//     json_error('Method not allowed.', 405);
// }
// if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) {
//     json_error('CSRF token mismatch.', 403);
// }

$userId = $_SESSION['user_id'] ?? null;
if (!$userId || !is_numeric($userId)) {
    json_error('Not authenticated.', 401);
}

try {
    $pdo->beginTransaction();

    // Delete all characters for this user (cascades will purge dependent data)
    $stmt = $pdo->prepare('DELETE FROM characters WHERE user_id = ?');
    $stmt->execute([(int) $userId]);
    $deletedChars = $stmt->rowCount();

    // Optional: Delete the user account entirely.
    // NOTE: Must happen AFTER character delete; otherwise characters get orphaned (SET NULL).
    // Uncomment if full account purge is desired:
    // $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    // $stmt->execute([(int)$userId]);
    // $deletedUser = $stmt->rowCount();

    $pdo->commit();

    json_ok([
        'message' => 'All character data for your user has been deleted.',
        'characters_deleted' => $deletedChars,
        // 'user_deleted'  => $deletedUser ?? 0,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    json_error('Server error while deleting data.', 500);
}
