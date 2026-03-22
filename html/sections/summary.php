<?php
// sections/summary.php - PATCHED FOR PUBLIC VIEW (v2 - AJAX compatible)
declare(strict_types=1);

// ============================================
// PUBLIC VIEW SUPPORT - AJAX Compatible
// ============================================
if (defined('PUBLIC_VIEW')) {
  // Included by public_character.php
  if (!isset($character_id) || !$character_id) {
    echo "No character selected.";
    exit;
  }

  // Character validation already done by parent
  $charName = htmlspecialchars((string) ($character['name'] ?? ''), ENT_QUOTES, 'UTF-8');
  $userName = ''; // No username in public view

} else {
  // Normal mode (authenticated OR public character via AJAX)
  require_once __DIR__ . '/../db.php';

  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  // Get character_id
  $character_id = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

  if (!$character_id) {
    // Only try to get from session if user is authenticated
    if (isset($_SESSION['user_id'])) {
      try {
        $stmt = $pdo->prepare('
                SELECT c.id, c.name
                FROM characters c
                WHERE c.user_id = ?
                ORDER BY c.updated_at DESC NULLS LAST, c.id DESC
                LIMIT 1
            ');
        $stmt->execute([$_SESSION['user_id']]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $character_id = (int) $row['id'];
        }
      } catch (Throwable $e) {
        $character_id = 0;
      }
    }
  }

  if (!$character_id) {
    // If user is not authenticated, return 401 (not 400!)
    if (!isset($_SESSION['user_id'])) {
      http_response_code(401);
      echo "Not authorized.";
      exit;
    }
    // User IS authenticated but has no characters - return 400
    http_response_code(400);
    echo "No character selected.";
    exit;
  }

  // Validate ownership OR public access
  $character = null;

  // First try: owned character (authenticated)
  if (isset($_SESSION['user_id'])) {
    try {
      $own = $pdo->prepare('SELECT id, name, guild_name FROM characters WHERE id = ? AND user_id = ?');
      $own->execute([$character_id, $_SESSION['user_id']]);
      $character = $own->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      $character = null;
    }
  }

  // Second try: public character (no authentication required)
  if (!$character) {
    try {
      $pub = $pdo->prepare('SELECT id, name, guild_name FROM characters WHERE id = ? AND visibility = "PUBLIC"');
      $pub->execute([$character_id]);
      $character = $pub->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      $character = null;
    }
  }

  if (!$character) {
    http_response_code(403);
    echo "Character not found or not accessible.";
    exit;
  }

  // Headers (only set in direct access mode, not when included)
  if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: private, max-age=60, must-revalidate');
  }

  $charName = htmlspecialchars((string) ($character['name'] ?? ''), ENT_QUOTES, 'UTF-8');
  $userName = htmlspecialchars((string) ($_SESSION['username'] ?? ''), ENT_QUOTES, 'UTF-8');
}
// ============================================
// END PUBLIC VIEW SUPPORT
// ============================================
?>
<section id="tab-summary" class="tab-content" data-character-id="<?php echo (int) $character_id; ?>"
  data-char-name="<?php echo $charName; ?>" data-user-name="<?php echo $userName; ?>">

  <div class="muted" style="text-align: center; padding: 40px 0;">
    <div style="font-size: 2rem; margin-bottom: 16px;">⏳</div>
    <div>Loading Dashboard...</div>
  </div>

</section>