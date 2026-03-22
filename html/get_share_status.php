<?php
/**
 * Get Share Status
 * 
 * Returns the current sharing status of a character
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/db.php';
    session_start();

    // Auth check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    $characterId = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

    if (!$characterId) {
        http_response_code(400);
        echo json_encode(['error' => 'No character_id provided']);
        exit;
    }

    // Verify ownership and get share status
    $stmt = $pdo->prepare('
        SELECT visibility, public_slug, show_currencies, show_items, show_social 
        FROM characters 
        WHERE id = ? AND user_id = ?
    ');
    $stmt->execute([$characterId, $_SESSION['user_id']]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not yours']);
        exit;
    }

    $isShared = $character['visibility'] === 'PUBLIC';
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    $publicUrl = null;
    if ($isShared && $character['public_slug']) {
        // Parse slug into realm and name
        $slugParts = explode('-', $character['public_slug'], 2);
        if (count($slugParts) === 2) {
            $urlRealm = $slugParts[0];
            $urlName = $slugParts[1];
            // Use pretty URL format: /server/character
            $publicUrl = "$protocol://$host/$urlRealm/$urlName";
        } else {
            // Fallback to slug-based URL
            $publicUrl = "$protocol://$host/sections/public_character.php?slug=" . $character['public_slug'];
        }
    }

    echo json_encode([
        'is_shared' => $isShared,
        'public_url' => $publicUrl,
        'slug' => $character['public_slug'],
        'show_currencies' => (bool) $character['show_currencies'],
        'show_items' => (bool) $character['show_items'],
        'show_social' => (bool) $character['show_social']
    ]);

} catch (Throwable $e) {
    error_log("Error in get_share_status.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}