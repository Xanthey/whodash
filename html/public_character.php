<?php
/**
 * Public Character Profile
 *
 * Displays a read-only public view of a shared character.
 * Uses the Stone Citadel / Dark Portal theme from public_bank_alt.php.
 * Hero image is resolved directly from /ux/wps/ (same logic as wps_images.php).
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

define('PUBLIC_VIEW', true);

require_once __DIR__ . '/db.php';

// ── Parameter parsing ─────────────────────────────────────────────────────────
$realm = isset($_GET['realm']) ? trim($_GET['realm']) : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (!empty($realm) && !empty($name)) {
    $slug = $realm . '-' . $name;
} elseif (empty($slug)) {
    http_response_code(400);
    ?><!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <title>Invalid Link — WhoDASH</title>
        <link rel="stylesheet" href="/sections/bank-alt-styles.css">
        <style>
            html,
            body {
                margin: 0;
                padding: 0;
                min-height: 100vh;
                background: #04040a;
                color: #d4cfc5;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Segoe UI', sans-serif;
                text-align: center
            }
        </style>
    </head>

    <body>
        <div>
            <h1 style="color:#f0ebe0">⚠️ Invalid Character Link</h1>
            <p>The character link is invalid or incomplete.</p>
            <p><a href="/" style="color:#3a9fff">← Return to WhoDASH</a></p>
        </div>
    </body>

    </html><?php
    exit;
}

// ── Fetch character ───────────────────────────────────────────────────────────
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
            <title>Not Found — WhoDASH</title>
            <link rel="stylesheet" href="/sections/bank-alt-styles.css">
            <style>
                html,
                body {
                    margin: 0;
                    padding: 0;
                    min-height: 100vh;
                    background: #04040a;
                    color: #d4cfc5;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-family: 'Segoe UI', sans-serif;
                    text-align: center
                }
            </style>
        </head>

        <body>
            <div>
                <h1 style="color:#f0ebe0">🔍 Character Not Found</h1>
                <p>This character is either private or doesn't exist.</p>
                <p><a href="/" style="color:#3a9fff">← Return to WhoDASH</a></p>
            </div>
        </body>

        </html>
        <?php
        exit;
    }

    $character_id = $character['id'];
    $_GET['character_id'] = $character_id;

    $showCurrencies = (bool) $character['show_currencies'];
    $showItems = (bool) $character['show_items'];
    $showSocial = (bool) $character['show_social'];

    // Bank alt redirect
    if (empty($_GET['skipBankAltCheck'])) {
        try {
            $baStmt = $pdo->prepare("SELECT 1 FROM guild_bank_alts WHERE character_id = ? LIMIT 1");
            $baStmt->execute([$character['id']]);
            if ($baStmt->fetchColumn()) {
                header('Location: /public_bank_alt.php?slug=' . urlencode($slug), true, 302);
                exit;
            }
        } catch (PDOException $e) {
            error_log('[public_character] Bank alt check failed: ' . $e->getMessage());
        }
    }

} catch (Exception $e) {
    error_log("Error fetching public character: " . $e->getMessage());
    http_response_code(500);
    echo '<h1>Server Error</h1>';
    exit;
}

// ── Hero image: direct filesystem scan (same logic as wps_images.php) ────────
function wps_pick_image(string $race, string $sex): ?string
{
    $wpsDir = __DIR__ . '/ux/wps/';
    $wpsUrl = '/ux/wps/';
    if (!is_dir($wpsDir))
        return null;

    $knownRaces = [
        'human',
        'orc',
        'dwarf',
        'nightelf',
        'night elf',
        'undead',
        'forsaken',
        'tauren',
        'gnome',
        'troll',
        'bloodelf',
        'blood elf',
        'draenei',
        'worgen',
        'goblin'
    ];

    $race = strtolower(trim($race));
    $sex = strtolower(trim($sex));
    $raceAlt = str_replace(' ', '', $race);

    $tier1 = [];
    $tier2 = [];
    $tier3 = [];

    foreach (scandir($wpsDir) as $file) {
        if ($file === '.' || $file === '..')
            continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']))
            continue;

        $n = strtolower($file);

        $hasRace = false;
        foreach ($knownRaces as $r) {
            if (str_contains($n, $r)) {
                $hasRace = true;
                break;
            }
        }

        $hasFemale = str_contains($n, 'female');
        $hasMale = (bool) preg_match('/\bmale\b/', $n);
        $hasSex = $hasFemale || $hasMale;

        if (!$hasRace && !$hasSex) {
            $tier2[] = $wpsUrl . $file;
            continue;
        }

        if (!$hasRace && $hasSex) {
            $ok = empty($sex)
                || ($sex === 'female' && $hasFemale)
                || ($sex === 'male' && $hasMale);
            if ($ok)
                $tier3[] = $wpsUrl . $file;
            continue;
        }

        // Tier 1: race-tagged
        $raceMatch = empty($race)
            || str_contains($n, $race)
            || str_contains($n, $raceAlt);
        if (!$raceMatch)
            continue;

        $sexMatch = empty($sex) || !$hasSex
            || ($sex === 'female' && $hasFemale)
            || ($sex === 'male' && $hasMale);
        if ($sexMatch)
            $tier1[] = $wpsUrl . $file;
    }

    $pool = array_merge($tier1, $tier2);
    if (empty($pool))
        $pool = $tier3;
    if (empty($pool))
        return null;
    return $pool[array_rand($pool)];
}

$sexInt = (int) ($character['sex'] ?? 0);
$sexKey = match ($sexInt) { 3 => 'female', 2 => 'male', default => ''};
$heroImageUrl = wps_pick_image($character['race'] ?? '', $sexKey);

// ── Display strings ───────────────────────────────────────────────────────────
$charName = htmlspecialchars($character['name'], ENT_QUOTES, 'UTF-8');
$charRealm = htmlspecialchars($character['realm'], ENT_QUOTES, 'UTF-8');
$charClass = htmlspecialchars($character['class_local']
    ?? $character['class_file'] ?? '', ENT_QUOTES, 'UTF-8');
$charRace = htmlspecialchars($character['race'] ?? '', ENT_QUOTES, 'UTF-8');
$charGuild = htmlspecialchars($character['guild_name'] ?? '', ENT_QUOTES, 'UTF-8');
$charRank = htmlspecialchars($character['guild_rank'] ?? '', ENT_QUOTES, 'UTF-8');
$sexLabel = match ($sexInt) { 3 => 'Female', 2 => 'Male', default => ''};
$faction = strtolower($character['faction'] ?? '');
$factionIcon = $faction === 'horde' ? '🔴' : '🔵';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $charName ?> — WhoDASH</title>

    <!-- Section styles (same set as index.html) -->
    <link rel="stylesheet" href="/style.css">
    <link rel="stylesheet" href="/sections/achievements-styles.css">
    <link rel="stylesheet" href="/sections/bank-alt-styles.css">
    <link rel="stylesheet" href="/sections/character-styles.css">
    <link rel="stylesheet" href="/sections/combat-styles.css">
    <link rel="stylesheet" href="/sections/currencies-styles.css">
    <link rel="stylesheet" href="/sections/dashboard-styles.css">
    <link rel="stylesheet" href="/sections/graphs-styles.css">
    <link rel="stylesheet" href="/sections/healing-styles.css">
    <link rel="stylesheet" href="/sections/items-styles.css">
    <link rel="stylesheet" href="/sections/mortality-styles.css">
    <link rel="stylesheet" href="/sections/professions-styles.css">
    <link rel="stylesheet" href="/sections/progression-styles.css">
    <link rel="stylesheet" href="/sections/quests-styles.css">
    <link rel="stylesheet" href="/sections/reputation-styles.css">
    <link rel="stylesheet" href="/sections/role-styles.css">
    <link rel="stylesheet" href="/sections/social-styles.css">
    <link rel="stylesheet" href="/sections/summary-styles.css">
    <link rel="stylesheet" href="/sections/tanking-styles.css">
    <link rel="stylesheet" href="/sections/travel-log-styles.css">

    <style>
        /* ── Kill style.css blue gradient — vortex bg shows through instead ── */
        html,
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            background: transparent !important;
        }

        /* style.css .container is a white card — neutralise it */
        .container {
            background: transparent !important;
            box-shadow: none !important;
            border-radius: 0 !important;
            padding: 0 !important;
            margin: 0 !important;
            max-width: none !important;
        }

        /* Hide dashboard chrome */
        .topbar,
        .sidebar,
        #app-nav {
            display: none !important;
        }

        #publicNav {
            display: none !important;
        }

        /* ── Tab panels ── */
        .pubchar-tab-panel {
            display: block;
        }

        .pubchar-tab-panel[hidden] {
            display: none !important;
        }

        /* ── Tab nav ── */
        .pubchar-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 20px;
        }

        /* ── Character stat pills ── */
        .pubchar-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .pubchar-pill {
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--stone-border);
            font-size: 0.8rem;
            color: var(--stone-text-dim);
        }

        .pubchar-pill.highlight {
            background: rgba(var(--glow-rgb), 0.12);
            border-color: var(--glow-color);
            color: var(--stone-text-hi);
            box-shadow: 0 0 6px rgba(var(--glow-rgb), 0.25);
        }

        /* ── Public notice strip ── */
        .pubchar-notice {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            background: rgba(58, 159, 255, 0.07);
            border: 1px solid rgba(58, 159, 255, 0.22);
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.82rem;
            color: var(--stone-text-dim);
        }

        /* ── Section content wrapper ──
           Normal WhoDASH white card inside, animated glow border outside.
           Sections render exactly as they do on the main dashboard.        ── */
        .pubchar-section-wrap {
            background: #fff;
            border-radius: 12px;
            border: 2px solid rgba(var(--glow-rgb), 0.6);
            box-shadow:
                0 0 0 1px rgba(var(--glow-rgb), 0.15),
                0 0 20px rgba(var(--glow-rgb), 0.22),
                0 6px 40px rgba(0, 0, 0, 0.6);
            padding: 28px 32px;
            margin-bottom: 8px;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #1a1a2e;
            /* Spinner placeholder sits above the white bg */
            min-height: 80px;
        }

        /* ── Suppress dashboard.js body class that adds a blue gradient bg ── */
        body.db2-active {
            background: transparent !important;
        }

        /* ── Dashboard frosted glass: strip hardcoded navy, go colour-neutral ──
           Cards become dark-neutral glass so they pick up the shifting vortex
           hue behind them rather than being locked to blue.                  ── */
        #pubchar-panel-dashboard .db2-card {
            background: rgba(0, 0, 0, 0.32) !important;
            backdrop-filter: blur(18px) saturate(160%) !important;
            -webkit-backdrop-filter: blur(18px) saturate(160%) !important;
            box-shadow:
                0 4px 24px rgba(0, 0, 0, 0.40),
                inset 0 1px 0 rgba(255, 255, 255, 0.08) !important;
            border-color: rgba(255, 255, 255, 0.10) !important;
        }

        #pubchar-panel-dashboard .db2-card:hover {
            background: rgba(0, 0, 0, 0.42) !important;
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.55),
                inset 0 1px 0 rgba(255, 255, 255, 0.12) !important;
        }

        /* Neutral text — white/light-grey instead of blue-tinted */
        #pubchar-panel-dashboard .db2-stat-label,
        #pubchar-panel-dashboard .db2-zone-name,
        #pubchar-panel-dashboard .db2-zone-time,
        #pubchar-panel-dashboard .db2-heatmap-label,
        #pubchar-panel-dashboard .db2-tip-meta,
        #pubchar-panel-dashboard .db2-highlight-label,
        #pubchar-panel-dashboard .db2-empty {
            color: rgba(220, 220, 220, 0.65) !important;
        }

        #pubchar-panel-dashboard .db2-stat-value,
        #pubchar-panel-dashboard .db2-widget-title,
        #pubchar-panel-dashboard .db2-char-name,
        #pubchar-panel-dashboard .db2-highlight-value,
        #pubchar-panel-dashboard .db2-tip-text {
            color: rgba(255, 255, 255, 0.92) !important;
        }

        /* Heatmap cells: strip blue tint from inactive state */
        #pubchar-panel-dashboard .db2-heatmap-cell {
            background: rgba(255, 255, 255, 0.06) !important;
        }

        /* XP bar: neutral instead of blue gradient */
        #pubchar-panel-dashboard .db2-xp-fill {
            background: linear-gradient(90deg,
                    rgba(var(--glow-rgb), 0.7),
                    rgba(var(--glow-rgb), 1.0)) !important;
        }

        /* ── Dashboard panel: no white card — frosted glass widgets sit
           directly on the vortex background for maximum visual effect ── */
        .pubchar-section-wrap--transparent {
            background: transparent !important;
            border-color: transparent !important;
            box-shadow: none !important;
            padding: 0 !important;
        }

        /* ── Hide The Grudge tab in public view ── */

        /* ── Hide "Send to The Grudge" button on PVP death cards ──
           Covers every likely class name the button might carry.
           If you sync a newer mortality.js and the class differs,
           add it here.                                            ── */
        #pubchar-panel-mortality .grudge-add-btn,
        #pubchar-panel-mortality .add-to-grudge,
        #pubchar-panel-mortality .send-grudge-btn,
        #pubchar-panel-mortality .btn-grudge,
        #pubchar-panel-mortality .pvp-grudge-btn,
        #pubchar-panel-mortality [class*="grudge-btn"],
        #pubchar-panel-mortality [class*="add-grudge"],
        #pubchar-panel-mortality [class*="send-grudge"] {
            display: none !important;
        }

        [data-tab="grudge"],
        #grudge-tab {
            display: none !important;
        }

        /* ── Responsive ── */
        @media (max-width: 760px) {
            .bankalt-namebar {
                flex-direction: column;
                align-items: flex-start;
            }

            .bankalt-content {
                padding: 12px 12px 40px;
            }

            .pubchar-tabs {
                gap: 4px;
            }

            .stone-btn {
                padding: 8px 12px;
                font-size: 0.8rem;
            }

            .pubchar-section-wrap {
                padding: 16px;
            }
        }

        .pubchar-tab-panel {
            display: block;
        }

        .pubchar-tab-panel[hidden] {
            display: none !important;
        }

        /* ── Tab nav ── */
        .pubchar-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 20px;
        }

        /* ── Character stat pills ── */
        .pubchar-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
        }

        .pubchar-pill {
            padding: 4px 12px;
            border-radius: 20px;
            background: rgba(0, 0, 0, 0.35);
            border: 1px solid var(--stone-border);
            font-size: 0.8rem;
            color: var(--stone-text-dim);
        }

        .pubchar-pill.highlight {
            background: rgba(var(--glow-rgb), 0.12);
            border-color: var(--glow-color);
            color: var(--stone-text-hi);
            box-shadow: 0 0 6px rgba(var(--glow-rgb), 0.25);
        }

        /* ── Public notice strip ── */
        .pubchar-notice {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            background: rgba(58, 159, 255, 0.07);
            border: 1px solid rgba(58, 159, 255, 0.22);
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.82rem;
            color: var(--stone-text-dim);
        }

        /* ── Responsive ── */
        @media (max-width: 760px) {
            .bankalt-namebar {
                flex-direction: column;
                align-items: flex-start;
            }

            .bankalt-content {
                padding: 12px 12px 40px;
            }

            .pubchar-tabs {
                gap: 4px;
            }

            .stone-btn {
                padding: 8px 12px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>

    <!-- ======================== VORTEX BACKGROUND ======================== -->
    <div class="bankalt-vortex-bg" aria-hidden="true"></div>
    <div class="bankalt-dust" aria-hidden="true">
        <div class="bankalt-dust-inner"></div>
    </div>
    <canvas id="bankalt-sparks-canvas" aria-hidden="true"></canvas>

    <!-- Set globals BEFORE any section script runs -->
    <script>
        window.WhoDAT_skipInit = true;
        window.WhoDAT_publicView = true;
        window.WhoDAT_currentCharacterId = <?= json_encode((string) $character_id) ?>;
        window.WhoDAT_characterName = <?= json_encode($character['name']) ?>;
        window.WhoDAT_showCurrencies = <?= $showCurrencies ? 'true' : 'false' ?>;
        window.WhoDAT_showItems = <?= $showItems ? 'true' : 'false' ?>;
        window.WhoDAT_showSocial = <?= $showSocial ? 'true' : 'false' ?>;
    </script>

    <!-- ======================== MAIN CONTENT ======================== -->
    <div class="bankalt-root">
        <div class="bankalt-content">

            <!-- ── HEADER ── -->
            <header class="bankalt-header">
                <div class="bankalt-hero-image">
                    <?php if ($heroImageUrl): ?>
                        <img src="<?= htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $charName ?>"
                            class="bankalt-hero-img" loading="eager" decoding="async">
                    <?php else: ?>
                        <div class="bankalt-hero-placeholder"><?= $charName ?></div>
                    <?php endif; ?>
                </div>

                <div class="bankalt-namebar">
                    <div>
                        <h1 class="bankalt-charname"><?= $charName ?></h1>
                        <?php if ($charGuild): ?>
                            <div style="font-size:0.85rem;color:var(--stone-rune);margin-top:4px">
                                🛡️ <?= $charGuild ?>
                                <?php if ($charRank): ?>&nbsp;·&nbsp; <?= $charRank ?><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <div class="pubchar-pills">
                            <?php if ($charRace): ?>
                                <div class="pubchar-pill highlight"><?= $charRace ?></div><?php endif; ?>
                            <?php if ($charClass): ?>
                                <div class="pubchar-pill highlight"><?= $charClass ?></div><?php endif; ?>
                            <?php if ($sexLabel): ?>
                                <div class="pubchar-pill"><?= $sexLabel ?></div><?php endif; ?>
                            <div class="pubchar-pill"><?= $factionIcon ?> <?= ucfirst($faction ?: 'unknown') ?></div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                        <div class="bankalt-realm-tag"><?= $charRealm ?></div>
                        <div class="bankalt-badge">⚔️ Character</div>
                    </div>
                </div>
            </header>

            <!-- ── PUBLIC NOTICE ── -->
            <div class="pubchar-notice">
                <span>📡</span>
                Public character profile — read-only. Data updates automatically when the owner syncs.
            </div>

            <!-- ── TAB NAV ── -->
            <nav class="pubchar-tabs" role="tablist" aria-label="Character Sections">
                <button class="stone-btn pubchar-tab-btn active" role="tab" data-tab="dashboard" aria-selected="true">🎮
                    Dashboard</button>
                <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="summary" aria-selected="false">📋
                    Summary</button>
                <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="progression" aria-selected="false">⚔️
                    Progression</button>
                <?php if ($showItems): ?>
                    <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="items" aria-selected="false">🗡️
                        Items</button>
                <?php endif; ?>
                <?php if ($showCurrencies): ?>
                    <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="currencies" aria-selected="false">💰
                        Currencies</button>
                <?php endif; ?>
                <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="achievements" aria-selected="false">🏆
                    Achievements</button>
                <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="professions" aria-selected="false">⚒️
                    Professions</button>
                <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="quests" aria-selected="false">📜
                    Quests</button>
                <?php if ($showSocial): ?>
                    <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="social" aria-selected="false">👥
                        Social</button>
                <?php endif; ?>
                <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="mortality" aria-selected="false">💀
                    Mortality</button>
                <button class="stone-btn pubchar-tab-btn" role="tab" data-tab="travel" aria-selected="false">🗺️ Travel
                    Log</button>
            </nav>

            <!-- ── TAB PANELS ── -->
            <div id="sectionContent">

                <!-- Dashboard: pre-rendered server-side, self-inits via dashboard.js line 401 -->
                <section id="pubchar-panel-dashboard" class="pubchar-tab-panel" role="tabpanel">
                    <div class="pubchar-section-wrap pubchar-section-wrap--transparent">
                        <?php include __DIR__ . '/sections/dashboard.php'; ?>
                    </div>
                </section>

                <!-- Summary: lazy loaded on first click -->
                <section id="pubchar-panel-summary" class="pubchar-tab-panel" role="tabpanel" hidden>
                    <div class="pubchar-section-wrap">
                        <div class="bankalt-loading">
                            <div class="bankalt-spinner"></div>Loading summary…
                        </div>
                    </div>
                </section>

                <!-- Lazy panels: spinner placeholder, replaced by fetch on first click -->
                <section id="pubchar-panel-progression" class="pubchar-tab-panel" role="tabpanel" hidden>
                    <div class="pubchar-section-wrap">
                        <div class="bankalt-loading">
                            <div class="bankalt-spinner"></div>Loading progression…
                        </div>
                    </div>
                </section>

                <?php if ($showItems): ?>
                    <section id="pubchar-panel-items" class="pubchar-tab-panel" role="tabpanel" hidden>
                        <div class="pubchar-section-wrap">
                            <div class="bankalt-loading">
                                <div class="bankalt-spinner"></div>Loading items…
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($showCurrencies): ?>
                    <section id="pubchar-panel-currencies" class="pubchar-tab-panel" role="tabpanel" hidden>
                        <div class="pubchar-section-wrap">
                            <div class="bankalt-loading">
                                <div class="bankalt-spinner"></div>Loading currencies…
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <section id="pubchar-panel-achievements" class="pubchar-tab-panel" role="tabpanel" hidden>
                    <div class="pubchar-section-wrap">
                        <div class="bankalt-loading">
                            <div class="bankalt-spinner"></div>Loading achievements…
                        </div>
                    </div>
                </section>

                <section id="pubchar-panel-professions" class="pubchar-tab-panel" role="tabpanel" hidden>
                    <div class="pubchar-section-wrap">
                        <div class="bankalt-loading">
                            <div class="bankalt-spinner"></div>Loading professions…
                        </div>
                    </div>
                </section>

                <section id="pubchar-panel-quests" class="pubchar-tab-panel" role="tabpanel" hidden>
                    <div class="pubchar-section-wrap">
                        <div class="bankalt-loading">
                            <div class="bankalt-spinner"></div>Loading quests…
                        </div>
                    </div>
                </section>

                <?php if ($showSocial): ?>
                    <section id="pubchar-panel-social" class="pubchar-tab-panel" role="tabpanel" hidden>
                        <div class="pubchar-section-wrap">
                            <div class="bankalt-loading">
                                <div class="bankalt-spinner"></div>Loading social data…
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <section id="pubchar-panel-mortality" class="pubchar-tab-panel" role="tabpanel" hidden>
                    <div class="pubchar-section-wrap">
                        <div class="bankalt-loading">
                            <div class="bankalt-spinner"></div>Loading mortality data…
                        </div>
                    </div>
                </section>

                <section id="pubchar-panel-travel" class="pubchar-tab-panel" role="tabpanel" hidden>
                    <div class="pubchar-section-wrap">
                        <div class="bankalt-loading">
                            <div class="bankalt-spinner"></div>Loading travel log…
                        </div>
                    </div>
                </section>

            </div><!-- /#sectionContent -->

            <footer style="text-align:center;margin-top:40px;font-size:0.75rem;
                   color:var(--stone-text-dim);letter-spacing:0.1em">
                <a href="/" style="color:var(--stone-text-dim);text-decoration:none">← WhoDASH</a>
                &nbsp;·&nbsp;Public profile — data updates when the owner syncs
            </footer>

        </div><!-- /.bankalt-content -->
    </div><!-- /.bankalt-root -->

    <!-- Section scripts (unchanged from original) -->
    <script type="module" src="/sections/travel-log.js"></script>
    <script type="module" src="/sections/items.js"></script>
    <script type="module" src="/sections/achievements.js"></script>
    <script type="module" src="/sections/professions.js"></script>
    <script src="/sections/summary.js" defer></script>
    <script src="/sections/dashboard.js" defer></script>
    <script src="/sections/currencies.js" defer></script>
    <script src="/sections/progression.js" defer></script>
    <script src="/sections/quests.js" defer></script>
    <script src="/sections/mortality.js"></script>
    <script src="/sections/combat.js"></script>
    <script src="/sections/healing.js"></script>
    <script src="/sections/tanking.js"></script>
    <script src="/sections/role.js"></script>
    <script src="/sections/reputation.js"></script>
    <script src="/sections/social.js" defer></script>

    <!-- No-op shim — replaces old public-nav.js -->
    <script src="/public-nav.js"></script>

    <!-- ==================== STONE CITADEL ANIMATION + TAB LOGIC ==================== -->
    <script>
        (function () {
            'use strict';

            /* ── Hue animation ── */
            const HUE_STOPS = [
                { hue: 200 }, { hue: 120 }, { hue: 0 }, { hue: 280 }, { hue: 205 }, { hue: 200 }
            ];
            const CYCLE_MS = 240_000;
            const ROOT = document.documentElement;

            function lerpHue(a, b, t) {
                let d = b - a;
                if (d > 180) d -= 360;
                if (d < -180) d += 360;
                return a + d * t;
            }

            function hslToRgb(h, s, l) {
                if (s === 0) { const v = Math.round(l * 255); return [v, v, v]; }
                const q = l < 0.5 ? l * (1 + s) : l + s - l * s, p = 2 * l - q;
                const h2r = (p, q, t) => {
                    if (t < 0) t += 1; if (t > 1) t -= 1;
                    if (t < 1 / 6) return p + (q - p) * 6 * t;
                    if (t < 1 / 2) return q;
                    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
                    return p;
                };
                return [h2r(p, q, h + 1 / 3), h2r(p, q, h), h2r(p, q, h - 1 / 3)].map(v => Math.round(v * 255));
            }

            function tickHue() {
                const now = (performance.now() % CYCLE_MS) / CYCLE_MS;
                const seg = 1 / (HUE_STOPS.length - 1);
                const idx = Math.min(Math.floor(now / seg), HUE_STOPS.length - 2);
                const hue = Math.round(lerpHue(HUE_STOPS[idx].hue, HUE_STOPS[idx + 1].hue, (now - idx * seg) / seg));
                const [r, g, b] = hslToRgb(hue / 360, 0.85, 0.55);
                ROOT.style.setProperty('--vortex-hue', hue);
                ROOT.style.setProperty('--glow-color', `hsl(${hue},85%,60%)`);
                ROOT.style.setProperty('--glow-rgb', `${r},${g},${b}`);
                ROOT.style.setProperty('--glow-soft', `rgba(${r},${g},${b},0.18)`);
                ROOT.style.setProperty('--glow-mid', `rgba(${r},${g},${b},0.45)`);
                ROOT.style.setProperty('--glow-hard', `rgba(${r},${g},${b},0.9)`);
                setTimeout(() => requestAnimationFrame(tickHue), 250);
            }

            /* ── Spiral sparks canvas ── */
            function initSpiralSparks() {
                const canvas = document.getElementById('bankalt-sparks-canvas');
                if (!canvas) return;
                const ctx = canvas.getContext('2d');
                const resize = () => { canvas.width = window.innerWidth; canvas.height = window.innerHeight; };
                resize(); window.addEventListener('resize', resize);

                const ZONES = [
                    { min: 0.00, max: 0.33, speedMult: 1.000, count: 14, spinScale: 1.00 },
                    { min: 0.33, max: 0.66, speedMult: 0.500, count: 30, spinScale: 0.70 },
                    { min: 0.66, max: 0.98, speedMult: 0.250, count: 38, spinScale: 0.45 },
                    { min: 0.98, max: 1.03, speedMult: 0.125, count: 6, spinScale: 0.20 },
                ];
                const maxR = () => Math.hypot(canvas.width / 2, canvas.height / 2);
                const spawn = z => {
                    const mr = maxR(), dist = z.min * mr + Math.random() * (z.max - z.min) * mr;
                    return {
                        angle: Math.random() * Math.PI * 2, dist, speedMult: z.speedMult,
                        speed: (0.45 + Math.random() * 0.55) * 0.205 * z.speedMult,
                        spin: (0.008 + Math.random() * 0.018) * z.spinScale * 0.20 + (Math.random() < 0.08 ? (Math.random() - 0.5) * 0.014 : 0),
                        size: 0.5 + Math.random() * (z === ZONES[3] ? 0.6 : 1.0),
                        alpha: 0.5 + Math.random() * 0.5,
                        hueOff: Math.floor(Math.random() * 60) - 30,
                        zoneIdx: ZONES.indexOf(z),
                    };
                };
                const sparks = ZONES.flatMap(z => Array.from({ length: z.count }, () => spawn(z)));
                let lastHue = 200;

                (function frame() {
                    const h = ROOT.style.getPropertyValue('--vortex-hue').trim();
                    if (h) lastHue = parseInt(h) || 200;
                    ctx.clearRect(0, 0, canvas.width, canvas.height);
                    const cx = canvas.width / 2, cy = canvas.height / 2, mr = maxR();
                    sparks.forEach((s, i) => {
                        if (s.dist < ZONES[s.zoneIdx].min * mr && s.zoneIdx > 0) {
                            s.zoneIdx--;
                            const z = ZONES[s.zoneIdx];
                            s.speedMult = z.speedMult;
                            s.speed = (0.45 + Math.random() * 0.55) * z.speedMult * 0.15;
                            s.spin = (0.008 + Math.random() * 0.018) * z.spinScale * 0.15;
                        }
                        s.angle += s.spin + 0.0028 * s.speedMult / Math.max(s.dist / mr, 0.05);
                        s.dist -= s.speed * (1 + mr * 0.028 / Math.max(s.dist, 15));
                        const x = cx + Math.cos(s.angle) * s.dist, y = cy + Math.sin(s.angle) * s.dist;
                        const fade = Math.min(1, s.dist < 60 ? s.dist / 60 : 1);
                        const dim = s.zoneIdx === 3 ? 0.50 : s.zoneIdx === 2 ? 0.72 : 1.0;
                        const a = (s.alpha * fade * dim).toFixed(2), sh = lastHue + s.hueOff;
                        ctx.beginPath(); ctx.arc(x, y, s.size, 0, Math.PI * 2);
                        ctx.fillStyle = `hsla(${sh},90%,75%,${a})`;
                        ctx.shadowColor = `hsla(${sh},85%,65%,${(s.alpha * fade * dim * 0.7).toFixed(2)})`;
                        ctx.shadowBlur = 4; ctx.fill();
                        if (s.dist <= 6 || x < -20 || x > canvas.width + 20 || y < -20 || y > canvas.height + 20) {
                            const z = ZONES[s.zoneIdx]; sparks[i] = spawn(z);
                            sparks[i].dist = z.max * mr * (0.85 + Math.random() * 0.15);
                        }
                    });
                    requestAnimationFrame(frame);
                })();
            }

            /* ── Tab switching with lazy fetch ── */
            const activated = new Set(['dashboard']); // dashboard pre-rendered server-side

            const FILE_MAP = {
                dashboard: 'dashboard.php', summary: 'summary.php',
                progression: 'progression.php', items: 'items.php',
                currencies: 'currencies.php', achievements: 'achievements.php',
                professions: 'professions.php', quests: 'quests.php',
                social: 'social.php', mortality: 'mortality.php',
                travel: 'travel-log.php',
            };

            function activateTab(id) {
                document.querySelectorAll('.pubchar-tab-btn').forEach(b => {
                    const on = b.dataset.tab === id;
                    b.classList.toggle('active', on);
                    b.setAttribute('aria-selected', on ? 'true' : 'false');
                });
                document.querySelectorAll('.pubchar-tab-panel').forEach(p => {
                    p.hidden = (p.id !== 'pubchar-panel-' + id);
                });

                if (activated.has(id)) return;
                activated.add(id);

                const panel = document.getElementById('pubchar-panel-' + id);
                const file = FILE_MAP[id];
                if (!panel || !file) return;

                const charId = window.WhoDAT_currentCharacterId;
                fetch(`/sections/${file}?character_id=${encodeURIComponent(charId)}`, {
                    headers: { 'HX-Request': 'true' },
                    credentials: 'include',
                })
                    .then(r => r.ok ? r.text() : Promise.reject(r.status))
                    .then(html => {
                        // Wrap in a white card with animated glow border — normal WhoDASH colours inside
                        panel.innerHTML = `<div class="pubchar-section-wrap">${html}</div>`;
                        // Each section JS listens for whodat:section-loaded with its own canonical name.
                        // Our tab key 'travel' differs from travel-log.js which expects 'travel-log'.
                        const SECTION_EVENT_NAME = { travel: 'travel-log', dashboard: 'dashboard', summary: 'summary' };
                        const sectionName = SECTION_EVENT_NAME[id] || id;
                        document.dispatchEvent(new CustomEvent('whodat:section-loaded', {
                            detail: { section: sectionName, character_id: charId }
                        }));
                        document.dispatchEvent(new CustomEvent('whodat:public-tab-activate', {
                            detail: { tab: id, character_id: charId }
                        }));
                    })
                    .catch(err => {
                        panel.innerHTML = `<div class="bankalt-empty">⚠️ Failed to load section (${err}). Try refreshing.</div>`;
                    });
            }

            /* ── Boot ── */
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('.pubchar-tab-btn').forEach(btn => {
                    btn.addEventListener('click', () => activateTab(btn.dataset.tab));
                });

                requestAnimationFrame(tickHue);
                initSpiralSparks();

                // Strip edit chrome
                document.querySelectorAll('[data-action="edit"],.edit-btn,.delete-btn').forEach(el => el.remove());
                document.querySelectorAll('input,textarea,select').forEach(el => {
                    el.setAttribute('readonly', true);
                    el.setAttribute('disabled', true);
                });

                document.dispatchEvent(new CustomEvent('whodat:public-view-ready', {
                    detail: {
                        character_id: window.WhoDAT_currentCharacterId,
                        character_name: window.WhoDAT_characterName,
                    }
                }));
            });
        })();
    </script>

</body>

</html>