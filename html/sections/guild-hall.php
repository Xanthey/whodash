<?php
// sections/guild-hall.php - Guild Hall (Tavern Theme)
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../guild_helpers.php';

session_start();

// This section uses guild_id from the user's character selection
// It doesn't require character_id parameter directly
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo '<div class="muted">Please log in to view the Guild Hall.</div>';
    exit;
}

// Ensure database connection is available
if (!isset($pdo)) {
    require_once __DIR__ . '/../db.php';
}

// Check if user has any characters with guild membership
try {

    // Get all guilds this user's characters belong to
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            g.guild_id,
            g.guild_name,
            g.faction,
            g.realm
        FROM guilds g
        INNER JOIN character_guilds cg ON g.guild_id = cg.guild_id
        INNER JOIN characters c ON cg.character_id = c.id
        WHERE c.user_id = ? AND cg.is_current = TRUE
        ORDER BY g.guild_name ASC
    ");
    $stmt->execute([$user_id]);
    $guilds = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($guilds)) {
        echo '<div class="muted">No guild memberships found. Upload character data with guild information to access the Guild Hall.</div>';
        exit;
    }

} catch (PDOException $e) {
    error_log("Guild Hall error: " . $e->getMessage());
    http_response_code(500);
    echo '<div class="muted">Error loading guild data.</div>';
    exit;
}
?>

<!-- Link to Guild Hall Styles -->
<link rel="stylesheet" href="/sections/guild-hall-styles.css">

<?php
// For single guild mode, store guild_id in section data attribute
$primaryGuildId = $guilds[0]['guild_id'] ?? null;
$guildIdAttr = '';
if (count($guilds) === 1 && $primaryGuildId) {
    $guildIdAttr = ' data-guild-id="' . htmlspecialchars($primaryGuildId, ENT_QUOTES, 'UTF-8') . '"';
}
?>
<head>
    <link rel="stylesheet" href="/sections/guild-hall-styles.css">
</head>
<section id="tab-guild-hall" class="tab-pane guild-hall-active" <?php echo $guildIdAttr; ?>>
    <!-- Guild Hall Header -->
    <div class="tavern-header">
        <div class="tavern-sign">
            <div class="sign-post"></div>
            <div class="sign-board">
                <div class="tavern-icon">🏰</div>
                <h2 class="tavern-title">The Guild Hall</h2>
                <div class="tavern-subtitle">Where Heroes Gather</div>
            </div>
        </div>

        <!-- Tavern Decorations -->
        <div class="tavern-decorations">
            <div class="tavern-torch">🔥</div>
            <div class="tavern-banner">⚔️</div>
            <div class="tavern-torch">🔥</div>
        </div>

        <!-- Time indicator (like The Bazaar) -->
        <div class="tavern-time-indicator">
            <span class="tavern-time-day">☀️ Day Mode</span>
            <span class="tavern-time-night">🌙 Night Mode</span>
        </div>
    </div>

    <!-- Day/Night Toggle -->
    <div class="day-night-controls">
        <button id="guild-hall-day-night-toggle" class="day-night-button" aria-label="Toggle Day/Night Mode">
            <span class="day-icon">☀️</span>
            <span class="night-icon">🌙</span>
        </button>
    </div>

    <!-- Guild Selection Tabs (if multiple guilds) -->
    <?php if (count($guilds) > 1): ?>
        <div class="guild-tabs" role="tablist">
            <?php foreach ($guilds as $index => $guild): ?>
                <?php
                $factionClass = strtolower($guild['faction'] ?? 'neutral');
                $isActive = $index === 0 ? 'active' : '';
                ?>
                <button class="guild-tab <?= $isActive ?>" role="tab" aria-selected="<?= $index === 0 ? 'true' : 'false' ?>"
                    data-guild-id="<?= htmlspecialchars($guild['guild_id']) ?>"
                    data-faction="<?= htmlspecialchars($factionClass) ?>">
                    <span class="guild-faction-icon">
                        <?= $factionClass === 'horde' ? '🔴' : ($factionClass === 'alliance' ? '🔵' : '⚪') ?>
                    </span>
                    <span class="guild-name"><?= htmlspecialchars($guild['guild_name']) ?></span>
                    <span class="guild-realm">(<?= htmlspecialchars($guild['realm']) ?>)</span>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Content Tabs (different rooms in the tavern) -->
    <div class="hall-navigation">
        <div class="hall-tabs" role="tablist">
            <button class="hall-tab active" role="tab" aria-selected="true" data-tab="treasury">
                <span class="tab-icon">💰</span>
                <span>Treasury</span>
            </button>
            <button class="hall-tab" role="tab" aria-selected="false" data-tab="vault">
                <span class="tab-icon">🏦</span>
                <span>Vault</span>
            </button>
            <button class="hall-tab" role="tab" aria-selected="false" data-tab="logs">
                <span class="tab-icon">📜</span>
                <span>Logs</span>
            </button>
            <button class="hall-tab" role="tab" aria-selected="false" data-tab="business">
                <span class="tab-icon">📊</span>
                <span>Guild Business</span>
            </button>
            <button class="hall-tab" role="tab" aria-selected="false" data-tab="members">
                <span class="tab-icon">👥</span>
                <span>Members</span>
            </button>
        </div>
    </div>

    <!-- Tab Panels (different tavern rooms) -->
    <div class="hall-panels">

        <!-- TREASURY PANEL -->
        <div id="hall-treasury-panel" class="hall-panel active tavern-room" data-tavern-style="human" role="tabpanel">
            <div class="room-header">
                <div class="room-sign">💰 Treasury</div>
                <div class="room-awning"></div>
            </div>
            <div class="room-content">
                <div class="tavern-loading">
                    <div class="tavern-spinner">🍺</div>
                    <div>Loading Treasury Data...</div>
                </div>
            </div>
        </div>

        <!-- VAULT PANEL -->
        <div id="hall-vault-panel" class="hall-panel tavern-room" data-tavern-style="dwarf" role="tabpanel" hidden>
            <div class="room-header">
                <div class="room-sign">🏦 Vault</div>
                <div class="room-awning"></div>
            </div>
            <div class="room-content">
                <div class="tavern-loading">
                    <div class="tavern-spinner">🍺</div>
                    <div>Loading Vault Data...</div>
                </div>
            </div>
        </div>

        <!-- LOGS PANEL -->
        <div id="hall-logs-panel" class="hall-panel tavern-room" data-tavern-style="undead" role="tabpanel" hidden>
            <div class="room-header">
                <div class="room-sign">📜 Logs</div>
                <div class="room-awning"></div>
            </div>
            <div class="room-content">
                <div class="tavern-loading">
                    <div class="tavern-spinner">🍺</div>
                    <div>Loading Log Data...</div>
                </div>
            </div>
        </div>

        <!-- GUILD BUSINESS PANEL -->
        <div id="hall-business-panel" class="hall-panel tavern-room" data-tavern-style="tauren" role="tabpanel" hidden>
            <div class="room-header">
                <div class="room-sign">📊 Guild Business</div>
                <div class="room-awning"></div>
            </div>
            <div class="room-content">
                <div class="tavern-loading">
                    <div class="tavern-spinner">🍺</div>
                    <div>Loading Business Data...</div>
                </div>
            </div>
        </div>

        <!-- MEMBERS PANEL -->
        <div id="hall-members-panel" class="hall-panel tavern-room" data-tavern-style="night-elf" role="tabpanel"
            hidden>
            <div class="room-header">
                <div class="room-sign">👥 Members</div>
                <div class="room-awning"></div>
            </div>
            <div class="room-content">
                <div class="tavern-loading">
                    <div class="tavern-spinner">🍺</div>
                    <div>Loading Member Data...</div>
                </div>
            </div>
        </div>

    </div>

</section>

<!-- Include Guild Hall JavaScript -->
<script src="/sections/guild-hall.js" defer></script>