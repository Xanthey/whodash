<?php
// WhoDASH — Summary (SPA-compatible + full-page fallback)
// - Fragment-only for XHR (?fragment=1) so SPA shell/topbar remain intact.
// - Full-page fallback for direct navigation (links style.css).
// - POST/GET handlers to set active character (ownership-checked, CSRF for POST).
// - Correlated subqueries pick single latest level/gold sample per character (no duplicates).

declare(strict_types=1);

// ----------------------------------------------------------------------------
// DB bootstrap (mirrors sql_setup.php)
// ----------------------------------------------------------------------------
$debug = getenv('APP_DEBUG') === '1';
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ERROR);

$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'whodat';
$user = getenv('DB_USER') ?: 'whodatuser';
$pass = getenv('DB_PASSWORD') ?: 'whodatpass';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$opt = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opt);
} catch (Throwable $e) {
    http_response_code(500);
    echo "Database connection error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

// ----------------------------------------------------------------------------
// Session, CSRF, helpers
// ----------------------------------------------------------------------------
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (empty($_SESSION['dashboard_csrf'])) {
    $_SESSION['dashboard_csrf'] = bin2hex(random_bytes(16));
}
function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function formatCopper(?int $c): string
{
    if ($c === null)
        return '—';
    $g = intdiv($c, 10000);
    $s = intdiv($c % 10000, 100);
    $cp = $c % 100;
    return sprintf('%dg %02ds %02dc', $g, $s, $cp);
}

// ----------------------------------------------------------------------------
// Auth guard (expects $_SESSION['user_id'])
// ----------------------------------------------------------------------------
$userId = $_SESSION['user_id'] ?? null;
$message = '';

// ----------------------------------------------------------------------------
// SPA detection: fragment-only vs full-page fallback
// ----------------------------------------------------------------------------
$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
$isFragment = $isAjax || isset($_GET['fragment']);

// ----------------------------------------------------------------------------
// Set active character: POST (CSRF) or GET (fallback from dropdown links)
// ----------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['dashboard_csrf'], $csrf)) {
        $message = 'CSRF token mismatch.';
    } elseif ($action === 'set_active') {
        $cid = isset($_POST['character_id']) ? (int) $_POST['character_id'] : 0;
        if ($cid > 0 && $userId !== null) {
            $check = $pdo->prepare('SELECT 1 FROM characters WHERE id = :id AND user_id = :uid');
            $check->execute([':id' => $cid, ':uid' => $userId]);
            if ((bool) $check->fetchColumn()) {
                $_SESSION['active_character_id'] = $cid;
                $message = '✅ Active character updated.';
            } else {
                $message = '❌ That character does not belong to your account.';
            }
        }
    }
} elseif (isset($_GET['character_id'])) {
    // Ownership-checked GET (fallback when top dropdown still uses anchors)
    $cid = (int) $_GET['character_id'];
    if ($cid > 0 && $userId !== null) {
        $check = $pdo->prepare('SELECT 1 FROM characters WHERE id = :id AND user_id = :uid');
        $check->execute([':id' => $cid, ':uid' => $userId]);
        if ((bool) $check->fetchColumn()) {
            $_SESSION['active_character_id'] = $cid;
            $message = '✅ Active character updated.';
        }
    }
}

// ----------------------------------------------------------------------------
// Query characters: latest Level & Gold via correlated subqueries (no duplicates)
// ----------------------------------------------------------------------------
$characters = [];
$totalCopper = 0;

if ($userId !== null) {
    $sql = <<<SQL
SELECT
  c.id,
  c.name,
  c.realm,
  c.faction,
  c.class_local,
  c.class_file,
  c.guild_name,
  c.updated_at,
  (
    SELECT l.value
    FROM series_level AS l
    WHERE l.character_id = c.id
    ORDER BY l.ts DESC, l.id DESC
    LIMIT 1
  ) AS level,
  (
    SELECT m.value
    FROM series_money AS m
    WHERE m.character_id = c.id
    ORDER BY m.ts DESC, m.id DESC
    LIMIT 1
  ) AS money_copper
FROM characters AS c
WHERE c.user_id = :uid
ORDER BY COALESCE(c.updated_at, c.created_at) DESC
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uid' => $userId]);
    while ($row = $stmt->fetch()) {
        $characters[] = $row;
        if (isset($row['money_copper'])) {
            $totalCopper += (int) $row['money_copper'];
        }
    }
}

// ----------------------------------------------------------------------------
// Render: fragment HTML to inject into #sectionContent
// ----------------------------------------------------------------------------
function render_fragment(array $characters, int $totalCopper, string $message, ?string $db, ?string $host): void
{ ?>
    <h1>Your Characters</h1>
    <div class="muted">Select one to set it as your active character. This mirrors the top dropdown.</div>

    <?php if ($message): ?>
        <div class="tab-content" style="margin-top:12px;"><?php echo h($message); ?></div>
    <?php endif; ?>

    <?php if (empty($characters)): ?>
        <div class="tab-content">
            <p class="muted">No characters found yet. Upload a <code>WhoDAT.lua</code> to populate your dashboard.</p>
        </div>
    <?php else: ?>
        <?php foreach ($characters as $row): ?>
            <?php $isActive = (($_SESSION['active_character_id'] ?? null) === (int) $row['id']); ?>
            <div class="dash-card">
                <div class="dash-portrait">PORTRAIT</div>
                <div class="dash-main">
                    <div class="dash-name"><?php echo h($row['name']); ?></div>
                    <div class="dash-meta">
                        Level <?php echo (int) ($row['level'] ?? 0); ?>
                        <?php if (!empty($row['class_local'])): ?>
                            <?php echo h($row['class_local']); ?>
                        <?php elseif (!empty($row['class_file'])): ?>
                            <?php echo h($row['class_file']); ?>
                        <?php endif; ?>
                        <?php if (!empty($row['faction'])): ?> · <?php echo h($row['faction']); ?><?php endif; ?>
                        <?php if (!empty($row['realm'])): ?> · <?php echo h($row['realm']); ?><?php endif; ?>
                        <?php if (!empty($row['guild_name'])): ?> · Guild: <?php echo h($row['guild_name']); ?><?php endif; ?>
                    </div>
                    <div class="dash-gold">Current Gold:
                        <?php echo formatCopper($row['money_copper'] !== null ? (int) $row['money_copper'] : null); ?>
                    </div>
                </div>
                <div class="dash-actions">
                    <?php if ($isActive): ?>
                        <button class="btn" disabled>Active</button>
                    <?php else: ?>
                        <form method="post" class="js-set-active" data-character-id="<?php echo (int) $row['id']; ?>"
                            data-character-name="<?php echo h($row['name']); ?>"
                            data-character-realm="<?php echo h($row['realm'] ?? ''); ?>"
                            data-character-faction="<?php echo h($row['faction'] ?? ''); ?>"
                            data-character-class="<?php echo h($row['class_local'] ?? $row['class_file'] ?? ''); ?>">
                            <input type="hidden" name="csrf" value="<?php echo h($_SESSION['dashboard_csrf']); ?>">
                            <input type="hidden" name="action" value="set_active">
                            <input type="hidden" name="character_id" value="<?php echo (int) $row['id']; ?>">
                            <!-- Inline XHR to avoid full page refresh -->
                            <button type="button" class="btn" onclick="(function(btn){
                  var form = btn.closest('form');
                  var fd   = new FormData(form);
                  var name = form.dataset.characterName || '';
                  var meta = [form.dataset.characterClass, form.dataset.characterFaction, form.dataset.characterRealm]
                              .filter(Boolean).join(' · ');

                  fetch('summary.php?fragment=1', {
                    method: 'POST',
                    headers: {'X-Requested-With': 'XMLHttpRequest'},
                    body: fd
                  })
                  .then(function(r){ return r.text(); })
                  .then(function(html){
                    // Replace the fragment
                    var section = document.getElementById('sectionContent');
                    if (section) { section.innerHTML = html; }

                    // Update the topbar dropdown label if present
                    var label = document.querySelector('.charselect-label') || document.getElementById('charselectLabel');
                    if (label) { label.textContent = name || label.textContent; }

                    // Broadcast to the SPA (shell can listen and update its own state)
                    var detail = {
                      id: form.dataset.characterId,
                      name: name,
                      realm: form.dataset.characterRealm,
                      faction: form.dataset.characterFaction,
                      className: form.dataset.characterClass
                    };
                    document.dispatchEvent(new CustomEvent('whodash:activeCharacterChanged', { detail: detail }));
                  })
                  .catch(function(err){ console.error(err); });
                })(this)">Make Active</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div class="dash-total">Total Gold from combined characters: <?php echo formatCopper($totalCopper); ?></div>
    <?php endif; ?>

    <div class="muted" style="margin-top:12px">
        DB: <code><?php echo h($db ?? ''); ?></code> on host <code><?php echo h($host ?? ''); ?></code>
    </div>
<?php }

// ----------------------------------------------------------------------------
// Output: fragment for SPA; otherwise full-page fallback with Progressive Enhancement
// ----------------------------------------------------------------------------
if ($isFragment) {
    // Fragment-only for SPA: inject into #sectionContent
    render_fragment($characters, $totalCopper, $message, $db, $host);
    exit;
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>WhoDASH · Summary</title>
    <!-- If this file sits in /sections, your style may be one level up -->
    <link rel="stylesheet" href="/../style.css" />
</head>

<body>
    <div class="container">
        <div id="sectionContent" class="tab-content">
            <?php render_fragment($characters, $totalCopper, $message, $db, $host); ?>
        </div>
    </div>

    <script>
        // Progressive enhancement for direct-load fallback: rewire any standard submits
        (function () {
            function wire(scope) {
                scope.querySelectorAll('form.js-set-active').forEach(function (form) {
                    form.addEventListener('submit', function (ev) {
                        ev.preventDefault();
                        var fd = new FormData(form);
                        var name = form.dataset.characterName || '';
                        var meta = [form.dataset.characterClass, form.dataset.characterFaction, form.dataset.characterRealm]
                            .filter(Boolean).join(' · ');
                        fetch('summary.php?fragment=1', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' },
                            body: fd
                        }).then(function (r) { return r.text(); }).then(function (html) {
                            var section = document.getElementById('sectionContent');
                            if (section) { section.innerHTML = html; wire(section); }
                            var label = document.querySelector('.charselect-label') || document.getElementById('charselectLabel');
                            if (label) { label.textContent = name || label.textContent; }
                            var detail = {
                                id: form.dataset.characterId,
                                name: name,
                                realm: form.dataset.characterRealm,
                                faction: form.dataset.characterFaction,
                                className: form.dataset.characterClass
                            };
                            document.dispatchEvent(new CustomEvent('whodash:activeCharacterChanged', { detail: detail }));
                        }).catch(console.error);
                    });
                });
            }
            wire(document);
        })();
    </script>
</body>

</html>