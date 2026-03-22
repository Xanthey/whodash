<?php
/**
 * Bank Alt Public Profile
 *
 * Shown when a character is:
 *  a) Publicly visible (visibility = 'PUBLIC'), AND
 *  b) Designated as a bank alt (row exists in guild_bank_alts)
 *
 * Routing: share_character.php detects the bank-alt flag and
 * redirects here, or this page can be linked directly with the same
 * ?slug= / ?realm=&name= query string format.
 *
 * Style: Stone Citadel / Dark Portal theme — NO character-specific
 * stats. Shows "Hello Azeroth" bio, Auction House, and Trading Post.
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/db.php';

// -----------------------------------------------------------------------
// Parameter parsing (same slug logic as share_character.php)
// -----------------------------------------------------------------------
$realm = isset($_GET['realm']) ? trim($_GET['realm']) : '';
$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (!empty($realm) && !empty($name)) {
  $slug = $realm . '-' . $name;
} elseif (empty($slug)) {
  http_response_code(400);
  die('Invalid character link.');
}

// -----------------------------------------------------------------------
// Verify character exists, is PUBLIC, and is a bank alt
// -----------------------------------------------------------------------
try {
  // Ensure the guild_bank_alts table exists (safe to run each load)
  $pdo->exec("
        CREATE TABLE IF NOT EXISTS guild_bank_alts (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            guild_id     INT NOT NULL,
            character_id BIGINT UNSIGNED NOT NULL,
            created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY   unique_bank_alt (guild_id, character_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

  $stmt = $pdo->prepare("
    SELECT
        c.id,
        c.name,
        c.realm,
        c.faction,
        c.class_local,
        c.class_file,
        c.race,
        c.race_file,        -- ← add this
        c.sex,              -- ← and this
        c.guild_name,
        c.guild_rank,
        c.last_login_ts,
        c.visibility,
        c.bank_alt_screenshot   -- ← and this
    FROM characters c
    INNER JOIN guild_bank_alts gba ON gba.character_id = c.id
    WHERE c.public_slug = ?
      AND c.visibility  = 'PUBLIC'
    LIMIT 1
");
  $stmt->execute([$slug]);
  $character = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$character) {
    // Not a bank alt or not public — redirect to standard profile
    $redirectTo = '/share_character.php?slug=' . urlencode($slug) . '&skipBankAltCheck=1';
    header('Location: ' . $redirectTo, true, 302);
    exit;
  }

} catch (Exception $e) {
  error_log('[BankAlt] Error: ' . $e->getMessage());
  http_response_code(500);
  die('Server error loading bank alt profile.');
}

$charName = htmlspecialchars($character['name'], ENT_QUOTES, 'UTF-8');
$charRealm = htmlspecialchars($character['realm'], ENT_QUOTES, 'UTF-8');
$charClass = htmlspecialchars($character['class_local'] ?? $character['class_file'] ?? '', ENT_QUOTES, 'UTF-8');
$charRace = htmlspecialchars($character['race'] ?? '', ENT_QUOTES, 'UTF-8');
// ── Hero image: custom screenshot or random race/sex default ──────────────
$heroImageUrl = null;   // null = placeholder (no image at all — shouldn't happen)

if (!empty($character['bank_alt_screenshot'])) {
  // Owner uploaded a custom screenshot
  $heroImageUrl = '/' . ltrim($character['bank_alt_screenshot'], '/');
} else {
  // Fall back to random artwork from /ux/vendors/
  // Files are named:  {sex_slug}_{race_slug}{1|2}.webp
  // sex:  0 = male, 1 = female (WoW convention)
  // race_file: e.g. "Tauren", "BloodElf", "Orc", "NightElf" …

  $sex = (int) ($character['sex'] ?? 0);
  $sexSlug = match ($sex) {
    3 => 'female',
    2 => 'male',
    default => 'male',
  };
  $raceSlug = strtolower(preg_replace('/\s+/', '', $character['race_file'] ?? $character['race'] ?? 'human'));

  // Try to pick a random existing file (1 or 2)
  $vendorBase = __DIR__ . '/ux/vendors/';
  $candidates = [];
  foreach ([1, 2] as $n) {
    $filename = $sexSlug . '_' . $raceSlug . $n . '.webp';
    if (file_exists($vendorBase . $filename)) {
      $candidates[] = '/ux/vendors/' . $filename;
    }
  }

  if ($candidates) {
    $heroImageUrl = $candidates[array_rand($candidates)];
  }
  // If no candidates found (e.g. rare race not yet converted), $heroImageUrl stays null
}
$charGuild = htmlspecialchars($character['guild_name'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en" class="bankalt-html">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $charName ?> — Bank Alt · WhoDASH</title>

  <!-- Stone / Dark Portal theme -->
  <link rel="stylesheet" href="/sections/bank-alt-styles.css">

  <style>
    /* Page-level reset so the full-page vortex works */
    html,
    body {
      margin: 0;
      padding: 0;
      min-height: 100vh;
      background: #000;
    }

    /* Hide any inherited dashboard chrome */
    .topbar,
    .sidebar,
    #app-nav {
      display: none !important;
    }

    /* Tab panel visibility */
    .bankalt-tab-panel {
      display: block;
    }

    .bankalt-tab-panel[hidden] {
      display: none !important;
    }

    /* ---- Wowhead-style tooltip ---- */
    .wd-tooltip {
      position: fixed;
      z-index: 9999;
      pointer-events: none;
      max-width: 320px;
      min-width: 180px;
      background: #0f1d35;
      border: 1px solid #2456a5;
      border-radius: 10px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.55), 0 2px 8px rgba(36, 86, 165, 0.25);
      padding: 0;
      overflow: hidden;
      font-size: 0.88rem;
      font-family: inherit;
      color: #c9d8f0;
      line-height: 1.45;
    }

    .wd-tooltip-loading {
      padding: 12px 16px;
      color: #6e9bd4;
      font-style: italic;
      font-size: 0.85rem;
    }

    .wd-tooltip-header {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px 10px;
      border-bottom: 1px solid #1e3a5f;
      background: #162b4a;
    }

    .wd-tooltip-icon {
      width: 36px;
      height: 36px;
      border-radius: 5px;
      border: 1px solid #2456a5;
      flex-shrink: 0;
      object-fit: cover;
    }

    .wd-tooltip-name {
      font-weight: 700;
      font-size: 0.95rem;
      line-height: 1.25;
    }

    .wd-tooltip-name.q-poor {
      color: #9d9d9d;
    }

    .wd-tooltip-name.q-common {
      color: #ffffff;
    }

    .wd-tooltip-name.q-uncommon {
      color: #1eff00;
    }

    .wd-tooltip-name.q-rare {
      color: #0070dd;
    }

    .wd-tooltip-name.q-epic {
      color: #a335ee;
    }

    .wd-tooltip-name.q-legendary {
      color: #ff8000;
    }

    .wd-tooltip-name.q-artifact {
      color: #e6cc80;
    }

    .wd-tooltip-name.q-heirloom {
      color: #00ccff;
    }

    .wd-tooltip-stats {
      padding: 10px 14px 8px;
      display: flex;
      flex-direction: column;
      gap: 3px;
    }

    .wd-tooltip-stat {
      color: #c9d8f0;
      font-size: 0.84rem;
    }

    .wd-tooltip-stat--bonus {
      color: #58d68d;
      font-weight: 600;
    }

    .wd-tooltip-stat--req {
      color: #e57373;
      font-size: 0.82rem;
    }

    .wd-tooltip-stat--use {
      color: #5dade2;
    }

    .wd-tooltip-stat--flavor {
      color: #9b8ea0;
      font-style: italic;
      font-size: 0.82rem;
    }

    .wd-tooltip-footer {
      padding: 8px 14px;
      border-top: 1px solid #1e3a5f;
      background: #0d1929;
    }

    .wd-tooltip-link {
      color: #4a90d9;
      font-size: 0.78rem;
      text-decoration: none;
      pointer-events: auto;
    }

    .wd-tooltip-link:hover {
      text-decoration: underline;
      color: #7ab3e8;
    }
  </style>
</head>

<body>

  <!-- ======================== VORTEX BACKGROUND ======================== -->
  <div class="bankalt-vortex-bg" aria-hidden="true">
    <!-- Layers are purely CSS via ::before / ::after -->
  </div>

  <!-- Dust layer -->
  <div class="bankalt-dust" aria-hidden="true">
    <div class="bankalt-dust-inner"></div>
  </div>

  <!-- Spiral spark canvas (replaces DOM glints) -->
  <canvas id="bankalt-sparks-canvas" aria-hidden="true"></canvas>

  <!-- ======================== MAIN CONTENT ======================== -->
  <div class="bankalt-root">
    <div class="bankalt-content">

      <!-- ---- HEADER ---- -->
      <header class="bankalt-header">
        <!-- 16:9 hero image -->
        <div class="bankalt-hero-image">
          <?php if ($heroImageUrl): ?>
            <img src="<?= htmlspecialchars($heroImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $charName ?> — Banner"
              class="bankalt-hero-img" loading="eager" decoding="async">
          <?php else: ?>
            <div class="bankalt-hero-placeholder">Banner Image Coming Soon</div>
          <?php endif; ?>
        </div>

        <!-- Name bar -->
        <div class="bankalt-namebar">
          <div>
            <h1 class="bankalt-charname"><?= $charName ?></h1>
            <?php if ($charGuild): ?>
              <div style="font-size:0.85rem;color:var(--stone-rune);margin-top:4px">
                🛡️ <?= $charGuild ?>
                <?php if ($character['guild_rank']): ?>
                  &nbsp;·&nbsp; <?= htmlspecialchars($character['guild_rank'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            <div class="bankalt-realm-tag"><?= $charRealm ?></div>
            <div class="bankalt-badge">🏦 Bank Alt</div>
            <?php if ($charClass): ?>
              <div class="bankalt-realm-tag"><?= $charClass ?></div>
            <?php endif; ?>
            <?php if ($charRace): ?>
              <div class="bankalt-realm-tag"><?= $charRace ?></div>
            <?php endif; ?>
          </div>
        </div>
      </header>

      <!-- ---- TAB NAV ---- -->
      <nav class="bankalt-tabs" role="tablist" aria-label="Bank Alt Sections">
        <button class="stone-btn bankalt-tab-btn active" role="tab" data-tab="hello" aria-selected="true">
          🌍 Hello Azeroth
        </button>
        <button class="stone-btn bankalt-tab-btn" role="tab" data-tab="ah" aria-selected="false">
          🏛️ Auction House
        </button>
        <button class="stone-btn bankalt-tab-btn" role="tab" data-tab="trading" aria-selected="false">
          🤝 Trading Post
        </button>
      </nav>

      <!-- ====== HELLO AZEROTH TAB ====== -->
      <section id="bankalt-panel-hello" class="bankalt-tab-panel" role="tabpanel">
        <div id="bankalt-hello-content">
          <div class="bankalt-loading">
            <div class="bankalt-spinner"></div>
            Loading character info…
          </div>
        </div>
      </section>

      <!-- ====== AUCTION HOUSE TAB ====== -->
      <section id="bankalt-panel-ah" class="bankalt-tab-panel" role="tabpanel" hidden>
        <div id="bankalt-ah-content">
          <div class="bankalt-loading">
            <div class="bankalt-spinner"></div>
            Loading auction data…
          </div>
        </div>
      </section>

      <!-- ====== TRADING POST TAB ====== -->
      <section id="bankalt-panel-trading" class="bankalt-tab-panel" role="tabpanel" hidden>
        <div class="stone-panel bankalt-coming-soon" style="padding:40px">
          <div class="bankalt-rune-border">
            <div class="bankalt-coming-soon-icon">🤝</div>
            <div class="bankalt-coming-soon-title">Trading Post</div>
            <div class="bankalt-coming-soon-sub">
              Direct player-to-player trading, wishlists, and trade offers — coming soon.
            </div>
            <div style="margin-top:20px;font-size:0.75rem;letter-spacing:0.15em;
                    text-transform:uppercase;color:var(--stone-text-dim)">
              The stonecutters are still at work…
            </div>
          </div>
        </div>
      </section>

      <!-- Footer -->
      <footer style="text-align:center;margin-top:40px;font-size:0.75rem;
                 color:var(--stone-text-dim);letter-spacing:0.1em">
        <a href="/" style="color:var(--stone-text-dim);text-decoration:none">
          ← WhoDASH
        </a>
        &nbsp;·&nbsp;
        Bank Alt profile &mdash; data updates automatically when the owner syncs
      </footer>

    </div><!-- /bankalt-content -->
  </div><!-- /bankalt-root -->

  <!-- Pass slug to JS -->
  <script>
    window.BANKALT_SLUG = <?= json_encode($slug, JSON_HEX_TAG | JSON_HEX_APOS) ?>;
  </script>
  <!-- Tooltip engine inlined to avoid MIME issues on this standalone page -->
  <script><?php readfile(__DIR__ . '/tooltip-engine.js'); ?></script>
  <script src="/sections/bank-alt-public.js" defer></script>

</body>

</html>