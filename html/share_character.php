<?php
/**
 * Public Character Profile
 * 
 * Displays a read-only public view of a shared character.
 * No authentication required - accessible to anyone with the link.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// IMPORTANT: Set this flag BEFORE including any section files
// This tells section files they're being included in a public context
define('PUBLIC_VIEW', true);

require_once __DIR__ . '/db.php';

// Get parameters from URL - support both formats:
// New format: ?realm=icecrown&name=belmont
// Old format: ?slug=icecrown-belmont (backwards compatibility)
$realm = isset($_GET['realm']) ? trim($_GET['realm']) : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

// If realm and name provided, construct the slug
if (!empty($realm) && !empty($name)) {
    $slug = $realm . '-' . $name;
} elseif (empty($slug)) {
    // No valid parameters provided
    http_response_code(400);
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Invalid Character Link - WhoDASH</title>
        <link rel="stylesheet" href="/style.css">
    </head>

    <body>
        <div style="text-align: center; padding: 100px 20px;">
            <h1>⚠️ Invalid Character Link</h1>
            <p>The character link you're looking for is invalid or incomplete.</p>
        </div>
    </body>

    </html>
    <?php
    exit;
}

// Fetch character data
try {
    $stmt = $pdo->prepare('
        SELECT 
            id, name, realm, faction, class_local, class_file, 
            race, race_file, sex, guild_name, guild_rank,
            last_login_ts, updated_at, visibility, 
            show_currencies, show_items, show_social
        FROM characters 
        WHERE public_slug = ? AND visibility = "PUBLIC"
    ');
    $stmt->execute([$slug]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(404);
        ?>
        <!DOCTYPE html>
        <html lang="en">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Character Not Found - WhoDASH</title>
            <link rel="stylesheet" href="/style.css">
        </head>

        <body>
            <div style="text-align: center; padding: 100px 20px;">
                <h1>🔍 Character Not Found</h1>
                <p>This character is either private or doesn't exist.</p>
                <p style="margin-top: 20px;"><a href="/">← Return to WhoDASH</a></p>
            </div>
        </body>

        </html>
        <?php
        exit;
    }

    // Set character_id for the section files to use
    $character_id = $character['id'];

    // Check privacy settings
    $showCurrencies = (bool) $character['show_currencies'];
    $showItems = (bool) $character['show_items'];
    $showSocial = (bool) $character['show_social'];

    // Bank alt detection: if this character is a guild bank alt, redirect to the
    // bank alt profile page instead of the standard character profile.
    if (empty($_GET['skipBankAltCheck'])) {
        try {
            $baStmt = $pdo->prepare("
                SELECT 1 FROM guild_bank_alts WHERE character_id = ? LIMIT 1
            ");
            $baStmt->execute([$character['id']]);
            if ($baStmt->fetchColumn()) {
                header('Location: /public_bank_alt.php?slug=' . urlencode($slug), true, 302);
                exit;
            }
        } catch (PDOException $e) {
            // guild_bank_alts table may not exist yet — continue with standard profile
            error_log('[share_character] Bank alt check failed: ' . $e->getMessage());
        }
    }

} catch (Exception $e) {
    error_log("Error fetching public character: " . $e->getMessage());
    http_response_code(500);
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Error - WhoDASH</title>
        <link rel="stylesheet" href="/style.css">
    </head>

    <body>
        <div style="text-align: center; padding: 100px 20px;">
            <h1>⚠️ Error Loading Character</h1>
            <p>An error occurred while loading this character profile.</p>
        </div>
    </body>

    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($character['name'], ENT_QUOTES, 'UTF-8'); ?> - WhoDASH</title>

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/style.css">

    <!-- Character-specific styles -->
    <style>
        .public-notice {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            border: 2px solid #2196f3;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
            font-size: 1rem;
        }

        .readonly-notice {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 8px 16px;
            border-radius: 6px;
            margin-bottom: 16px;
            text-align: center;
            font-weight: 500;
            color: #856404;
        }

        .public-header {
            background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .public-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.5rem;
        }

        .public-header .subtitle {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .character-badges {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .character-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 16px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>

<body>
    <div class="container">
        <!-- Public Character Header -->
        <div class="public-header">
            <h1><?php echo htmlspecialchars($character['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
            <div class="subtitle">
                <?php echo htmlspecialchars($character['realm'], ENT_QUOTES, 'UTF-8'); ?> •
                <?php echo htmlspecialchars($character['class_local'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?> •
                <?php echo htmlspecialchars($character['race'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php if ($character['guild_name']): ?>
                <div class="character-badges">
                    <div class="character-badge">
                        🛡️ <?php echo htmlspecialchars($character['guild_name'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <?php if ($character['guild_rank']): ?>
                        <div class="character-badge">
                            📊 <?php echo htmlspecialchars($character['guild_rank'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Public Notice -->
        <div class="public-notice">
            📊 This is a public character profile shared by its owner. Data is read-only and updates automatically when
            the owner uploads new data.
        </div>

        <!-- Tab Navigation -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="tab-summary">📊 Summary</button>
            <button class="tab-btn" data-tab="tab-progression">📈 Progression</button>
            <button class="tab-btn" data-tab="tab-achievements">🏆 Achievements</button>
            <button class="tab-btn" data-tab="tab-combat">⚔️ Combat</button>
            <button class="tab-btn" data-tab="tab-professions">🔨 Professions</button>
            <?php if ($showCurrencies): ?>
                <button class="tab-btn" data-tab="tab-currencies">💰 Currencies</button>
            <?php endif; ?>
            <?php if ($showItems): ?>
                <button class="tab-btn" data-tab="tab-items">🎒 Items</button>
            <?php endif; ?>
            <?php if ($showSocial): ?>
                <button class="tab-btn" data-tab="tab-social">👥 Social</button>
            <?php endif; ?>
            <button class="tab-btn" data-tab="tab-quests">📜 Quests</button>
            <button class="tab-btn" data-tab="tab-reputation">🤝 Reputation</button>
        </div>

        <!-- Tab Content Containers -->
        <div id="tab-summary" class="tab-content active">
            <div class="readonly-notice">👁️ Read-only view</div>
            <?php include __DIR__ . '/sections/summary.php'; ?>
        </div>

        <div id="tab-progression" class="tab-content">
            <div class="readonly-notice">👁️ Read-only view</div>
            <?php include __DIR__ . '/sections/progression.php'; ?>
        </div>

        <div id="tab-achievements" class="tab-content">
            <div class="readonly-notice">👁️ Read-only view</div>
            <?php include __DIR__ . '/sections/achievements.php'; ?>
        </div>

        <div id="tab-combat" class="tab-content">
            <div class="readonly-notice">👁️ Read-only view</div>
            <div id="combat-tabs">
                <button class="subtab-btn active" data-subtab="healing">💚 Healing</button>
                <button class="subtab-btn" data-subtab="tanking">🛡️ Tanking</button>
                <button class="subtab-btn" data-subtab="role">⚔️ Role</button>
                <button class="subtab-btn" data-subtab="mortality">💀 Deaths</button>
            </div>
            <div id="subtab-healing" class="subtab-content active">
                <?php include __DIR__ . '/sections/healing.php'; ?>
            </div>
            <div id="subtab-tanking" class="subtab-content">
                <?php include __DIR__ . '/sections/tanking.php'; ?>
            </div>
            <div id="subtab-role" class="subtab-content">
                <?php include __DIR__ . '/sections/role.php'; ?>
            </div>
            <div id="subtab-mortality" class="subtab-content">
                <?php include __DIR__ . '/sections/mortality.php'; ?>
            </div>
        </div>

        <div id="tab-professions" class="tab-content">
            <div class="readonly-notice">👁️ Read-only view</div>
            <?php include __DIR__ . '/sections/professions.php'; ?>
        </div>

        <?php if ($showCurrencies): ?>
            <div id="tab-currencies" class="tab-content">
                <div class="readonly-notice">👁️ Read-only view</div>
                <?php include __DIR__ . '/sections/currencies.php'; ?>
            </div>
        <?php endif; ?>

        <?php if ($showItems): ?>
            <div id="tab-items" class="tab-content">
                <div class="readonly-notice">👁️ Read-only view</div>
                <?php include __DIR__ . '/sections/items.php'; ?>
            </div>
        <?php endif; ?>

        <?php if ($showSocial): ?>
            <div id="tab-social" class="tab-content">
                <div class="readonly-notice">👁️ Read-only view</div>
                <?php include __DIR__ . '/sections/social.php'; ?>
            </div>
        <?php endif; ?>

        <div id="tab-quests" class="tab-content">
            <div class="readonly-notice">👁️ Read-only view</div>
            <?php include __DIR__ . '/sections/quests.php'; ?>
        </div>

        <div id="tab-reputation" class="tab-content">
            <div class="readonly-notice">👁️ Read-only view</div>
            <?php include __DIR__ . '/sections/reputation.php'; ?>
        </div>
    </div>

    <!-- Load main JavaScript for tab switching -->
    <script type="module" src="/main.js"></script>

    <!-- Load all section JavaScript files for data fetching/rendering -->
    <script src="/sections/summary.js" defer></script>
    <script src="/sections/dashboard.js" defer></script>
    <script src="/sections/progression.js" defer></script>
    <script src="/sections/achievements.js"></script>
    <script src="/sections/professions.js"></script>
    <script src="/sections/currencies.js" defer></script>
    <script src="/sections/items.js"></script>
    <script src="/sections/quests.js" defer></script>
    <script src="/sections/combat.js"></script>
    <script src="/sections/healing.js"></script>
    <script src="/sections/tanking.js"></script>
    <script src="/sections/role.js"></script>
    <script src="/sections/mortality.js"></script>
    <script src="/sections/social.js" defer></script>

    <!-- Additional public view JavaScript -->

    <!-- Additional public view JavaScript -->
    <script>
        // Disable any edit functionality in public view
        document.addEventListener('DOMContentLoaded', () => {
            // Remove any edit buttons or forms
            const editButtons = document.querySelectorAll('[data-action="edit"], .edit-btn, .delete-btn');
            editButtons.forEach(btn => btn.remove());

            // Make all inputs readonly
            const inputs = document.querySelectorAll('input, textarea, select');
            inputs.forEach(input => {
                input.setAttribute('readonly', true);
                input.setAttribute('disabled', true);
            });

            console.log('Public character view loaded:', '<?php echo htmlspecialchars($character['name'], ENT_QUOTES); ?>');
        });
    </script>
</body>

</html>