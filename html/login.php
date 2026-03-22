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

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$remember_me = !empty($_POST['remember_me']);

if ($username === '' || $password === '') {
    json_error('Username and password required.', 400);
}

try {
    // Look up user
    $stmt = $pdo->prepare('SELECT id, password_hash FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_error('Invalid username or password.', 401);
    }

    // Upgrade hash if needed
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([$newHash, (int) $user['id']]);
    }

    // Successful login
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['username'] = $username;

    // ── Remember-me cookie ──────────────────────────────────────────────────
    if ($remember_me) {
        $selector = bin2hex(random_bytes(16));   // 32 hex chars — safe to store plain
        $validator = bin2hex(random_bytes(32));   // 64 hex chars — only hash stored in DB
        $hash = hash('sha256', $validator);

        // Ensure table exists (safe no-op if already created)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS remember_tokens (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id     INT UNSIGNED NOT NULL,
                selector    VARCHAR(64)  NOT NULL UNIQUE,
                token_hash  VARCHAR(64)  NOT NULL,
                expires_at  DATETIME     NOT NULL,
                created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                KEY idx_rt_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Prune any old tokens for this user (keep it tidy)
        $del = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $del->execute([(int) $user['id']]);

        // Insert new token
        $ins = $pdo->prepare(
            'INSERT INTO remember_tokens (user_id, selector, token_hash, expires_at)
             VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))'
        );
        $ins->execute([(int) $user['id'], $selector, $hash]);

        setcookie('remember_me', $selector . ':' . $validator, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            // 'secure' => true,  // Uncomment on HTTPS
        ]);
    }

    json_ok(['message' => 'Logged in.']);

} catch (Throwable $e) {
    json_error('Server error.', 500);
}