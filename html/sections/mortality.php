<?php
// sections/mortality.php - Fresh start based on actual database schema
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Start session for auth
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$character_id = $_GET['character_id'] ?? null;
if (!$character_id || !is_numeric($character_id)) {
    echo '<p>Invalid character ID</p>';
    exit;
}

$character_id = (int) $character_id;

// Verify character access
$character = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare('SELECT name, class_file FROM characters WHERE id = ? AND user_id = ?');
    $stmt->execute([$character_id, $_SESSION['user_id']]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$character) {
    $stmt = $pdo->prepare('SELECT name, class_file FROM characters WHERE id = ? AND visibility = "PUBLIC"');
    $stmt->execute([$character_id]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$character) {
    echo '<p>Character not found or not accessible</p>';
    exit;
}
?>

<div id="tab-mortality" data-character-id="<?= $character_id ?>"
    data-char-name="<?= htmlspecialchars($character['name']) ?>">
    <div class="mortality-loading">
        <div class="loading-spinner">💀</div>
        <p>Loading mortality analysis...</p>
    </div>
</div>

<script>
    // Load the mortality JavaScript module
    if (typeof window.loadSection === 'function') {
        window.loadSection('mortality');
    }
</script>