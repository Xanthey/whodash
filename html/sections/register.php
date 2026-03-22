<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php'; // expects $pdo (PDO) with ERRMODE_EXCEPTION
header('Content-Type: application/json');

function json_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function json_ok(array $payload = [], int $status = 201): void
{
    http_response_code($status);
    echo json_encode(['success' => true] + $payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_error('Invalid request.', 405);
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$confirm = $_POST['confirm_password'] ?? '';

if ($username === '' || $password === '' || $confirm === '') {
    json_error('All fields required.', 400);
}
if ($password !== $confirm) {
    json_error('Passwords do not match.', 400);
}

// Optional: enforce username constraints to match schema & routing expectations
if (mb_strlen($username, 'UTF-8') > 64) {
    json_error('Username must be 64 characters or fewer.', 400);
}
// Allow letters, numbers, underscore, hyphen, dot; adjust as needed
if (!preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
    json_error('Username contains invalid characters.', 400);
}

// Optional: password policy (adjust to your product requirements)
if (strlen($password) < 10) {
    json_error('Password must be at least 10 characters.', 400);
}

try {
    // Check for duplicate username (collation is case-insensitive)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        json_error('Username already exists.', 409);
    }

    // Create user & account policy atomically
    $pdo->beginTransaction();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (username, password_hash) VALUES (?, ?)");
    $stmt->execute([$username, $hash]);
    $userId = (int) $pdo->lastInsertId();

    // Create default account_sharing_policy row:
    // default_visibility: PRIVATE, discoverable: 0 (adjust if you want UNLISTED/PUBLIC or discoverable=1)
    $stmt = $pdo->prepare("
        INSERT INTO account_sharing_policy (user_id, default_visibility, discoverable)
        VALUES (?, 'PRIVATE', 0)
    ");
    $stmt->execute([$userId]);

    $pdo->commit();

    // Regenerate session ID to prevent fixation, then set session
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;

    json_ok(['message' => 'Account created!', 'user_id' => $userId, 'username' => $username], 201);

} catch (Throwable $e) {
    // If something failed, rollback and return sanitized error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    // Unique violation race condition protection:
    if (strpos($e->getMessage(), 'Duplicate') !== false) {
        json_error('Username already exists.', 409);
    }

    json_error('Server error while creating account.', 500);
}
