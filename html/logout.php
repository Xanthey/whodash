<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear remember-me cookie and DB token if present
if (!empty($_COOKIE['remember_me'])) {
    $parts = explode(':', $_COOKIE['remember_me'], 2);
    if (count($parts) === 2) {
        $selector = $parts[0];
        try {
            require_once __DIR__ . '/db.php';
            $del = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = ?');
            $del->execute([$selector]);
        } catch (Throwable $e) {
            // Best effort
        }
    }
    setcookie('remember_me', '', ['expires' => time() - 3600, 'path' => '/']);
}

session_destroy();
echo json_encode(['success' => true]);