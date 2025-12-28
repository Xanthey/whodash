<?php
// sections/role.php
declare(strict_types=1);

require_once __DIR__ . '/../db.php';
session_start();

// Require auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "Not authorized.";
    exit;
}

// Character resolution
$character_id = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

if (!$character_id) {
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

if (!$character_id) {
    http_response_code(400);
    echo "No character selected.";
    exit;
}

// Validate ownership
try {
    $own = $pdo->prepare('SELECT id, name FROM characters WHERE id = ? AND user_id = ?');
    $own->execute([$character_id, $_SESSION['user_id']]);
    $character = $own->fetch(PDO::FETCH_ASSOC);
    if (!$character) {
        http_response_code(403);
        echo "Character not found or not yours.";
        exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error validating character.";
    exit;
}

// Headers
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, max-age=60, must-revalidate');

$charName = htmlspecialchars((string) ($character['name'] ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section id="tab-role" class="tab-content" data-character-id="<?php echo (int) $character_id; ?>"
    data-char-name="<?php echo $charName; ?>">

    <!-- Main Role Tabs -->
    <div class="role-main-tabs">
        <button class="role-main-tab active" data-role="overview">
            <span class="tab-icon">📊</span>
            <span class="tab-label">Role Performance</span>
        </button>
        <button class="role-main-tab" data-role="damage">
            <span class="tab-icon">⚔️</span>
            <span class="tab-label">Damage</span>
        </button>
        <button class="role-main-tab" data-role="tanking">
            <span class="tab-icon">🛡️</span>
            <span class="tab-label">Tanking</span>
        </button>
        <button class="role-main-tab" data-role="healing">
            <span class="tab-icon">💚</span>
            <span class="tab-label">Healing</span>
        </button>
    </div>

    <!-- Role Tab Panes -->
    <div class="role-tab-panes">

        <!-- Overview Pane -->
        <div id="role-pane-overview" class="role-pane active">
            <div class="muted" style="text-align: center; padding: 40px 0;">
                <div style="font-size: 2rem; margin-bottom: 16px;">📊</div>
                <div>Loading Role Performance Overview...</div>
            </div>
        </div>

        <!-- Damage Pane (Combat) -->
        <div id="role-pane-damage" class="role-pane">
            <div id="tab-combat" data-character-id="<?php echo (int) $character_id; ?>"
                data-char-name="<?php echo $charName; ?>">
                <div class="muted" style="text-align: center; padding: 40px 0;">
                    <div style="font-size: 2rem; margin-bottom: 16px;">⚔️</div>
                    <div>Loading Damage Analytics...</div>
                </div>
            </div>
        </div>

        <!-- Tanking Pane -->
        <div id="role-pane-tanking" class="role-pane">
            <div id="tab-tanking" data-character-id="<?php echo (int) $character_id; ?>"
                data-char-name="<?php echo $charName; ?>">
                <div class="muted" style="text-align: center; padding: 40px 0;">
                    <div style="font-size: 2rem; margin-bottom: 16px;">🛡️</div>
                    <div>Loading Tanking Analytics...</div>
                </div>
            </div>
        </div>

        <!-- Healing Pane -->
        <div id="role-pane-healing" class="role-pane">
            <div id="tab-healing" data-character-id="<?php echo (int) $character_id; ?>"
                data-char-name="<?php echo $charName; ?>">
                <div class="muted" style="text-align: center; padding: 40px 0;">
                    <div style="font-size: 2rem; margin-bottom: 16px;">💚</div>
                    <div>Loading Healing Analytics...</div>
                </div>
            </div>
        </div>

    </div>

</section>