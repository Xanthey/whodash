<?php
// sections/dashboard.php - REDESIGNED WIDGET DASHBOARD v2
declare(strict_types=1);

// ============================================
// PUBLIC VIEW SUPPORT - AJAX Compatible
// ============================================
if (defined('PUBLIC_VIEW')) {
  if (!isset($character_id) || !$character_id) {
    echo "No character selected.";
    exit;
  }
  $charName = htmlspecialchars((string) ($character['name'] ?? ''), ENT_QUOTES, 'UTF-8');
  $userName = '';

} else {
  require_once __DIR__ . '/../db.php';

  if (session_status() === PHP_SESSION_NONE) {
    session_start();
  }

  $character_id = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

  if (!$character_id) {
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
    if (!isset($_SESSION['user_id'])) {
      http_response_code(401);
      echo "Not authorized.";
      exit;
    }
    http_response_code(400);
    echo "No character selected.";
    exit;
  }

  $character = null;

  if (isset($_SESSION['user_id'])) {
    try {
      $own = $pdo->prepare('SELECT id, name, guild_name FROM characters WHERE id = ? AND user_id = ?');
      $own->execute([$character_id, $_SESSION['user_id']]);
      $character = $own->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      $character = null;
    }
  }

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
<section id="tab-dashboard" class="tab-content db2-section" data-character-id="<?php echo (int) $character_id; ?>"
  data-char-name="<?php echo $charName; ?>" data-user-name="<?php echo $userName; ?>">

  <div class="db2-loading">
    <div class="db2-spinner"></div>
    <div>Loading dashboard…</div>
  </div>

</section>