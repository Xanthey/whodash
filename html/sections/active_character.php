<?php
/**
 * Resolve or set the user's active character.
 *
 * GET:
 *   → Returns { ok: true, character_id, name } for the current user's active/default character.
 *     Priority:
 *       1) Session-stored active character (if valid and belongs to user)
 *       2) DB users.active_character_id (if column exists and is valid)
 *       3) "Last updated" character: ORDER BY (updated_at IS NULL), updated_at DESC, id DESC
 * POST:
 *   → Accepts application/x-www-form-urlencoded with character_id=<id>
 *     Validates ownership, stores in session, and (optionally) persists to users.active_character_id.
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

//////////////////// Helpers ////////////////////
/** Check if a character id belongs to this user and return minimal info */
function getCharacterForUser(PDO $pdo, int $userId, int $charId): ?array
{
    $stmt = $pdo->prepare("SELECT id, name FROM characters WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$charId, $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Try to read users.active_character_id if that column exists */
function getUsersActiveCharacterId(PDO $pdo, int $userId): ?int
{
    try {
        // If the column doesn't exist, this SELECT will throw—catch and return null.
        $stmt = $pdo->prepare("SELECT active_character_id FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $val = $stmt->fetchColumn();
        if ($val === false || $val === null)
            return null;
        $cid = (int) $val;
        return $cid > 0 ? $cid : null;
    } catch (Throwable $e) {
        return null;
    }
}

/** Persist users.active_character_id (optional) */
function setUsersActiveCharacterId(PDO $pdo, int $userId, int $charId): void
{
    try {
        $stmt = $pdo->prepare("UPDATE users SET active_character_id = ? WHERE id = ? LIMIT 1");
        $stmt->execute([$charId, $userId]);
    } catch (Throwable $e) {
        // Silently ignore if the column/table doesn't exist.
    }
}

//////////////////// POST: set active character ////////////////////
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw = $_POST['character_id'] ?? '';
    $characterId = is_numeric($raw) ? (int) $raw : 0;

    if ($characterId <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'character_id required']);
        exit;
    }

    // Validate ownership
    $row = getCharacterForUser($pdo, $userId, $characterId);
    if (!$row) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Character does not belong to user']);
        exit;
    }

    // Store in session and optionally in users.active_character_id
    $_SESSION['active_character_id'] = $characterId;
    setUsersActiveCharacterId($pdo, $userId, $characterId);

    echo json_encode(['ok' => true, 'character_id' => $characterId, 'name' => $row['name'] ?? null]);
    exit;
}

//////////////////// GET: resolve active/default ////////////////////
// 1) Session preference
$sessionActive = isset($_SESSION['active_character_id']) ? (int) $_SESSION['active_character_id'] : 0;
if ($sessionActive > 0) {
    $row = getCharacterForUser($pdo, $userId, $sessionActive);
    if ($row) {
        echo json_encode(['ok' => true, 'character_id' => (int) $row['id'], 'name' => $row['name'] ?? null]);
        exit;
    }
}

// 2) users.active_character_id (optional)
$dbActive = getUsersActiveCharacterId($pdo, $userId);
if ($dbActive) {
    $row = getCharacterForUser($pdo, $userId, $dbActive);
    if ($row) {
        // Also sync session for faster subsequent requests
        $_SESSION['active_character_id'] = (int) $row['id'];
        echo json_encode(['ok' => true, 'character_id' => (int) $row['id'], 'name' => $row['name'] ?? null]);
        exit;
    }
}

// 3) Last updated character for the user
try {
    $stmt = $pdo->prepare("
    SELECT id, name
    FROM characters
    WHERE user_id = ?
    ORDER BY (updated_at IS NULL), updated_at DESC, id DESC
    LIMIT 1
  ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        echo json_encode(['ok' => false, 'message' => 'No characters']);
        exit;
    }

    // Sync session
    $_SESSION['active_character_id'] = (int) $row['id'];

    echo json_encode([
        'ok' => true,
        'character_id' => (int) $row['id'],
        'name' => $row['name'] ?? null
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Server error']);
}
