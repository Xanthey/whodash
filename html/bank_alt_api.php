<?php
/**
 * Bank Alt API
 *
 * Handles:
 *   GET  ?action=status&character_id=X   → returns is_bank_alt, shared status, screenshot path
 *   POST action=toggle                   → toggle is_bank_alt on/off for a character
 *   POST action=upload_screenshot        → upload a custom banner screenshot (multipart)
 *   POST action=remove_screenshot        → delete the custom screenshot, revert to default
 *
 * All write operations require the character to belong to the logged-in user.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/db.php';

// ── Auth guard ───────────────────────────────────────────────────────────────
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}
$userId = (int) $_SESSION['user_id'];

// ── Route ────────────────────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];
$action = '';

if ($method === 'GET') {
    $action = trim($_GET['action'] ?? '');
} else {
    // Support both JSON body and multipart/form-data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (str_contains($contentType, 'application/json')) {
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = trim($body['action'] ?? '');
    } else {
        $action = trim($_POST['action'] ?? '');
        $body   = $_POST;
    }
}

// ── GET: status ──────────────────────────────────────────────────────────────
if ($method === 'GET' && $action === 'status') {
    $charId = (int) ($_GET['character_id'] ?? 0);
    if ($charId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing character_id']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT c.is_bank_alt, c.bank_alt_screenshot, c.visibility, c.public_slug,
               (SELECT COUNT(*) FROM guild_bank_alts gba WHERE gba.character_id = c.id) AS guild_flagged
        FROM characters c
        WHERE c.id = ? AND c.user_id = ?
        LIMIT 1
    ");
    $stmt->execute([$charId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Character not found']);
        exit;
    }

    $isShared  = ($row['visibility'] === 'PUBLIC') && !empty($row['public_slug']);
    $shareUrl  = $isShared
        ? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
          . '://' . $_SERVER['HTTP_HOST'] . '/public_bank_alt.php?slug=' . urlencode($row['public_slug'])
        : null;

    echo json_encode([
        'success'         => true,
        'is_bank_alt'     => (bool) $row['is_bank_alt'],
        'guild_flagged'   => (bool) $row['guild_flagged'],
        'is_shared'       => $isShared,
        'share_url'       => $shareUrl,
        'has_screenshot'  => !empty($row['bank_alt_screenshot']),
        'screenshot_url'  => !empty($row['bank_alt_screenshot'])
            ? '/' . ltrim($row['bank_alt_screenshot'], '/')
            : null,
    ]);
    exit;
}

// ── POST: toggle ─────────────────────────────────────────────────────────────
if ($method === 'POST' && $action === 'toggle') {
    $charId = (int) ($body['character_id'] ?? 0);
    if ($charId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing character_id']);
        exit;
    }

    // Verify ownership
    $stmt = $pdo->prepare("SELECT id, is_bank_alt FROM characters WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$charId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Character not found or access denied']);
        exit;
    }

    $newValue = $row['is_bank_alt'] ? 0 : 1;
    $pdo->prepare("UPDATE characters SET is_bank_alt = ? WHERE id = ? AND user_id = ?")
        ->execute([$newValue, $charId, $userId]);

    echo json_encode([
        'success'     => true,
        'is_bank_alt' => (bool) $newValue,
        'message'     => $newValue
            ? '🏦 Bank Alt flag enabled.'
            : '🏦 Bank Alt flag removed.',
    ]);
    exit;
}

// ── POST: upload_screenshot ──────────────────────────────────────────────────
if ($method === 'POST' && $action === 'upload_screenshot') {
    $charId = (int) ($body['character_id'] ?? $_POST['character_id'] ?? 0);
    if ($charId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing character_id']);
        exit;
    }

    // Verify ownership
    $stmt = $pdo->prepare("SELECT id, bank_alt_screenshot FROM characters WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$charId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Character not found or access denied']);
        exit;
    }

    // Validate upload
    if (empty($_FILES['screenshot']) || $_FILES['screenshot']['error'] !== UPLOAD_ERR_OK) {
        $errMap = [
            UPLOAD_ERR_INI_SIZE   => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE  => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL    => 'Upload was interrupted',
            UPLOAD_ERR_NO_FILE    => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Server temp directory missing',
            UPLOAD_ERR_CANT_WRITE => 'Server write error',
        ];
        $errCode = $_FILES['screenshot']['error'] ?? UPLOAD_ERR_NO_FILE;
        echo json_encode(['success' => false, 'error' => $errMap[$errCode] ?? 'Upload error ' . $errCode]);
        exit;
    }

    $file = $_FILES['screenshot'];

    // ── Size limit: 4 MB ──────────────────────────────────────────────────
    $maxBytes = 4 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum 4 MB.']);
        exit;
    }

    // ── Validate MIME type via finfo ───────────────────────────────────────
    $allowedMimes = [
        'image/webp' => 'webp',
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
    ];

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($file['tmp_name']);
    if (!array_key_exists($mime, $allowedMimes)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload WebP, JPEG, or PNG.']);
        exit;
    }
    $ext = $allowedMimes[$mime];

    // ── Confirm it's a real image ──────────────────────────────────────────
    if (!@getimagesize($file['tmp_name'])) {
        echo json_encode(['success' => false, 'error' => 'File does not appear to be a valid image.']);
        exit;
    }

    // ── Build destination path ─────────────────────────────────────────────
    $uploadDir = __DIR__ . '/ux/bank_alt_screenshots/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $filename = 'char_' . $charId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    $destPath = $uploadDir . $filename;
    $relPath  = 'ux/bank_alt_screenshots/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        echo json_encode(['success' => false, 'error' => 'Could not save file. Check server permissions.']);
        exit;
    }

    // ── Delete old screenshot if it exists ────────────────────────────────
    if (!empty($row['bank_alt_screenshot'])) {
        $oldPath = __DIR__ . '/' . ltrim($row['bank_alt_screenshot'], '/');
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
    }

    // ── Persist to DB ─────────────────────────────────────────────────────
    $pdo->prepare("UPDATE characters SET bank_alt_screenshot = ? WHERE id = ? AND user_id = ?")
        ->execute([$relPath, $charId, $userId]);

    echo json_encode([
        'success'        => true,
        'message'        => '📸 Screenshot uploaded successfully!',
        'screenshot_url' => '/' . $relPath,
    ]);
    exit;
}

// ── POST: remove_screenshot ──────────────────────────────────────────────────
if ($method === 'POST' && $action === 'remove_screenshot') {
    $charId = (int) ($body['character_id'] ?? 0);
    if ($charId <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing character_id']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, bank_alt_screenshot FROM characters WHERE id = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$charId, $userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Character not found or access denied']);
        exit;
    }

    if (!empty($row['bank_alt_screenshot'])) {
        $oldPath = __DIR__ . '/' . ltrim($row['bank_alt_screenshot'], '/');
        if (is_file($oldPath)) {
            @unlink($oldPath);
        }
        $pdo->prepare("UPDATE characters SET bank_alt_screenshot = NULL WHERE id = ? AND user_id = ?")
            ->execute([$charId, $userId]);
    }

    echo json_encode(['success' => true, 'message' => 'Screenshot removed. Default image will be used.']);
    exit;
}

// ── Fallback ──────────────────────────────────────────────────────────────────
http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action or method']);
