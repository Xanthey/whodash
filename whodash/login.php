<?php
declare(strict_types=1);

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/db.php'; // provides $pdo with ERRMODE_EXCEPTION

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Method not allowed.', 405);
}

// Optional CSRF check if this endpoint is called from a browser form
// $csrf = $_POST['csrf'] ?? '';
// if (!isset($_SESSION['login_csrf']) || !hash_equals($_SESSION['login_csrf'], $csrf)) {
//     json_error('CSRF token mismatch.', 403);
// }

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    json_error('Username and password required.', 400);
}

try {
    // Look up user
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch(); // associative if DB default fetch mode is set

    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Optional: usleep(200000); // 200ms delay to slow brute force
        json_error('Invalid username or password.', 401);
    }

    // Optional: upgrade hash if algorithm/options changed
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$newHash, (int) $user['id']]);
    }

    // Successful login: regenerate the session ID to prevent fixation
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $username;

    json_ok(['message' => 'Logged in.']);

} catch (Throwable $e) {
    json_error('Server error.', 500);
}
