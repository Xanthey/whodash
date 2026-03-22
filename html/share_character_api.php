<?php
/**
 * Character Sharing API
 * 
 * Handles POST requests to share/unshare characters
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

try {
    require_once __DIR__ . '/db.php';
    session_start();

    // Auth check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Get JSON input
    $rawInput = file_get_contents('php://input');
    error_log("Share character request: " . $rawInput);

    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }

    $characterId = isset($input['character_id']) ? (int) $input['character_id'] : 0;
    $action = isset($input['action']) ? trim($input['action']) : '';

    if (!$characterId) {
        http_response_code(400);
        echo json_encode(['error' => 'No character_id provided']);
        exit;
    }

    if (!in_array($action, ['share', 'unshare'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action. Must be "share" or "unshare"']);
        exit;
    }

    // Verify ownership
    $stmt = $pdo->prepare('SELECT id, name, realm, public_slug FROM characters WHERE id = ? AND user_id = ?');
    $stmt->execute([$characterId, $_SESSION['user_id']]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not yours']);
        exit;
    }

    if ($action === 'share') {
        // Get settings
        $showCurrencies = isset($input['show_currencies']) ? (bool) $input['show_currencies'] : false;
        $showItems = isset($input['show_items']) ? (bool) $input['show_items'] : false;
        $showSocial = isset($input['show_social']) ? (bool) $input['show_social'] : false;

        // Generate slug if needed
        $slug = $character['public_slug'];
        if (empty($slug)) {
            // Create slug from realm and character name
            $realm = strtolower(preg_replace('/[^a-z0-9]+/i', '', $character['realm']));
            $name = strtolower(preg_replace('/[^a-z0-9]+/i', '', $character['name']));
            $slug = $realm . '-' . $name;

            // Make sure it's unique
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM characters WHERE public_slug = ?');
            $stmt->execute([$slug]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                // Add a suffix if needed
                $originalSlug = $slug;
                $suffix = 1;
                do {
                    $slug = $originalSlug . $suffix;
                    $stmt->execute([$slug]);
                    $count = $stmt->fetchColumn();
                    $suffix++;
                } while ($count > 0);
            }
        }

        // Update character
        $stmt = $pdo->prepare('
            UPDATE characters 
            SET visibility = "PUBLIC", 
                public_slug = ?, 
                show_currencies = ?, 
                show_items = ?,
                show_social = ?
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$slug, $showCurrencies ? 1 : 0, $showItems ? 1 : 0, $showSocial ? 1 : 0, $characterId, $_SESSION['user_id']]);

        // Build public URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        // Parse slug into realm and name for pretty URL
        $slugParts = explode('-', $slug, 2);
        if (count($slugParts) === 2) {
            $urlRealm = $slugParts[0];
            $urlName = $slugParts[1];
            // Use pretty URL format: /server/character
            $publicUrl = "$protocol://$host/$urlRealm/$urlName";
        } else {
            // Fallback to slug-based URL
            $publicUrl = "$protocol://$host/share_character.php?slug=$slug";
        }

        echo json_encode([
            'success' => true,
            'message' => 'Character is now public',
            'public_url' => $publicUrl,
            'slug' => $slug,
            'show_currencies' => $showCurrencies,
            'show_items' => $showItems,
            'show_social' => $showSocial
        ]);

    } else { // unshare
        // Update character to private
        $stmt = $pdo->prepare('
            UPDATE characters 
            SET visibility = "PRIVATE"
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$characterId, $_SESSION['user_id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Character is now private'
        ]);
    }

} catch (Throwable $e) {
    error_log("Error in share_character_api.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}