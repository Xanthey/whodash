<?php
// sections/bazaar.php - Enhanced Multi-character Darkmoon Bazaar
// Features: Prize Tickets, Trading Post, Fortune Teller, Comparison, Heatmap, Alerts, Workshop
// Theme: Day/Night mode with Faire Lights
declare(strict_types=1);

// ============================================
// PUBLIC VIEW SUPPORT - AJAX Compatible
// ============================================
if (defined('PUBLIC_VIEW')) {
    if (!isset($user_id) || !$user_id) {
        echo "No user selected.";
        exit;
    }
} else {
    require_once __DIR__ . '/../db.php';

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo "Not authorized.";
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: private, max-age=60, must-revalidate');
    }

    // Get user's character count
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM characters WHERE user_id = ?');
        $stmt->execute([$user_id]);
        $charCount = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
    } catch (Throwable $e) {
        $charCount = 0;
    }
}
?>
<head>
<link rel="stylesheet" href="/sections/bazaar-styles.css">
</head>
<section id="tab-bazaar" class="tab-content" data-user-id="<?php echo (int) $user_id; ?>">

    <!-- Day/Night Detection Script -->
    <script>
        // Detect time of day and set theme
        (function () {
            const hour = new Date().getHours();
            const isNight = hour >= 18 || hour < 6; // 6 PM to 6 AM is night
            document.documentElement.setAttribute('data-dmf-theme', isNight ? 'night' : 'day');

            // Store for reference
            window.DMF_IS_NIGHT = isNight;
        })();
    </script>

    <!-- Darkmoon Faire Header with Day/Night Variants -->
    <div class="dmf-header">
        <div class="dmf-banner">
            <!-- Faire Lights (only show at night) -->
            <div class="dmf-faire-lights">
                <span class="dmf-light" style="--delay: 0s"></span>
                <span class="dmf-light" style="--delay: 0.2s"></span>
                <span class="dmf-light" style="--delay: 0.4s"></span>
                <span class="dmf-light" style="--delay: 0.6s"></span>
                <span class="dmf-light" style="--delay: 0.8s"></span>
                <span class="dmf-light" style="--delay: 1s"></span>
                <span class="dmf-light" style="--delay: 1.2s"></span>
                <span class="dmf-light" style="--delay: 1.4s"></span>
                <span class="dmf-light" style="--delay: 1.6s"></span>
                <span class="dmf-light" style="--delay: 1.8s"></span>
            </div>

            <div class="dmf-banner-content">
                <div class="dmf-icon">🎪</div>
                <div>
                    <h2 class="dmf-title">Darkmoon Bazaar</h2>
                    <div class="dmf-subtitle">
                        <span class="dmf-subtitle-day">Step right up! Trade across <?php echo $charCount; ?>
                            character<?php echo $charCount !== 1 ? 's' : ''; ?>!</span>
                        <span class="dmf-subtitle-night">The faire glows eternal! Manage <?php echo $charCount; ?>
                            character<?php echo $charCount !== 1 ? 's' : ''; ?> by moonlight.</span>
                    </div>
                </div>
            </div>

            <div class="dmf-decorations">
                <span class="dmf-balloon">🎈</span>
                <span class="dmf-balloon">🎈</span>
                <span class="dmf-balloon">🎈</span>
            </div>

            <!-- Time indicator -->
            <div class="dmf-time-indicator">
                <span class="dmf-time-day">☀️ Day Mode</span>
                <span class="dmf-time-night">🌙 Night Mode</span>
            </div>
        </div>
    </div>

    <!-- Faction Tab Navigation (Top Row) -->
    <div class="faction-tabs-container">
        <div class="faction-tabs" role="tablist">
            <button class="faction-tab active" role="tab" aria-selected="true" data-faction="xfaction">
                <span class="faction-icon">⚔️</span>
                <span>Cross-Faction</span>
            </button>
            <button class="faction-tab" role="tab" aria-selected="false" data-faction="alliance">
                <span class="faction-icon alliance-icon">🛡️</span>
                <span>Alliance</span>
            </button>
            <button class="faction-tab" role="tab" aria-selected="false" data-faction="horde">
                <span class="faction-icon horde-icon">⚔️</span>
                <span>Horde</span>
            </button>
        </div>
    </div>

    <!-- Content Tab Navigation (Bottom Row) -->
    <div class="bazaar-tabs-container">
        <div class="bazaar-tabs" role="tablist">
            <!-- Core Features -->
            <button class="bazaar-tab active" role="tab" aria-selected="true" data-tab="auction">
                <span class="tab-icon">🔨</span>
                <span>Auction House</span>
            </button>
            <button class="bazaar-tab" role="tab" aria-selected="false" data-tab="inventory">
                <span class="tab-icon">🎒</span>
                <span>Inventory</span>
            </button>
            <button class="bazaar-tab" role="tab" aria-selected="false" data-tab="progression">
                <span class="tab-icon">🏆</span>
                <span>Progression</span>
            </button>
            <button class="bazaar-tab" role="tab" aria-selected="false" data-tab="social">
                <span class="tab-icon">👥</span>
                <span>Social</span>
            </button>

            <!-- Separator -->
            <div class="tab-separator"></div>

            <!-- New Features -->

            <button class="bazaar-tab" role="tab" aria-selected="false" data-tab="timeline">
                <span class="tab-icon">📖</span>
                <span>Character Journey</span>
            </button>
            <button class="bazaar-tab" role="tab" aria-selected="false" data-tab="fortune">
                <span class="tab-icon">🔮</span>
                <span>Fortune Teller</span>
            </button>
            <button class="bazaar-tab" role="tab" aria-selected="false" data-tab="comparison">
                <span class="tab-icon">⚖️</span>
                <span>Compare</span>
            </button>
            <button class="bazaar-tab" role="tab" aria-selected="false" data-tab="heatmap">
                <span class="tab-icon">🗺️</span>
                <span>Heatmap</span>
            </button>

            <button class="bazaar-tab" role="tab" aria-selected="false" data-tab="workshop">
                <span class="tab-icon">🔨</span>
                <span>Workshop</span>
            </button>
        </div>
    </div>

    <!-- Tab Panels with Vendor Stall Styling -->
    <div class="bazaar-panels">

        <!-- AUCTION HOUSE PANEL -->
        <div id="bazaar-auction-panel" class="bazaar-panel active vendor-stall" data-stall-color="red" role="tabpanel">
            <div class="stall-header">
                <div class="stall-sign">🔨 Auction House</div>
                <div class="stall-awning"></div>
            </div>
            <div class="stall-content">
                <div class="dmf-loading">
                    <div class="dmf-spinner">🎠</div>
                    <div>Loading Auction House Data...</div>
                </div>
            </div>
        </div>

        <!-- INVENTORY PANEL -->
        <div id="bazaar-inventory-panel" class="bazaar-panel vendor-stall" data-stall-color="green" role="tabpanel"
            hidden>
            <div class="stall-header">
                <div class="stall-sign">🎒 Inventory</div>
                <div class="stall-awning"></div>
            </div>
            <div class="stall-content">
                <div class="dmf-loading">
                    <div class="dmf-spinner">🎠</div>
                    <div>Loading Inventory Data...</div>
                </div>
            </div>
        </div>

        <!-- PROGRESSION PANEL -->
        <div id="bazaar-progression-panel" class="bazaar-panel vendor-stall" data-stall-color="purple" role="tabpanel"
            hidden>
            <div class="stall-header">
                <div class="stall-sign">🏆 Progression</div>
                <div class="stall-awning"></div>
            </div>
            <div class="stall-content">
                <div class="dmf-loading">
                    <div class="dmf-spinner">🎠</div>
                    <div>Loading Progression Data...</div>
                </div>
            </div>
        </div>

        <!-- SOCIAL PANEL -->
        <div id="bazaar-social-panel" class="bazaar-panel vendor-stall" data-stall-color="blue" role="tabpanel" hidden>
            <div class="stall-header">
                <div class="stall-sign">👥 Social</div>
                <div class="stall-awning"></div>
            </div>
            <div class="stall-content">
                <div class="dmf-loading">
                    <div class="dmf-spinner">🎠</div>
                    <div>Loading Social Data...</div>
                </div>
            </div>
        </div>



        <!-- CHARACTER JOURNEY TIMELINE PANEL -->
        <div id="bazaar-timeline-panel" class="bazaar-panel vendor-stall" data-stall-color="mystical" role="tabpanel"
            hidden>
            <div class="stall-header">
                <div class="stall-sign">📖 Character Journey</div>
                <div class="stall-awning"></div>
            </div>
            <div class="stall-content">
                <div class="dmf-loading">
                    <div class="dmf-spinner">🎠</div>
                    <div>Weaving your epic tale...</div>
                </div>
            </div>
        </div>

        <!-- FORTUNE TELLER PANEL -->
        <div id="bazaar-fortune-panel" class="bazaar-panel vendor-stall" data-stall-color="mystical" role="tabpanel"
            hidden>
            <div class="stall-header">
                <div class="stall-sign">🔮 Fortune Teller</div>
                <div class="stall-awning"></div>
            </div>
            <div class="stall-content">
                <div class="dmf-loading">
                    <div class="dmf-spinner">🎠</div>
                    <div>Gazing into the crystal ball...</div>
                </div>
            </div>
        </div>

        <!-- COMPARISON PANEL -->
        <div id="bazaar-comparison-panel" class="bazaar-panel vendor-stall" data-stall-color="orange" role="tabpanel"
            hidden>
            <div class="stall-header">
                <div class="stall-sign">⚖️ Character Comparison</div>
                <div class="stall-awning"></div>
            </div>
            <div class="stall-content">
                <div class="dmf-loading">
                    <div class="dmf-spinner">🎠</div>
                    <div>Loading Comparison Tool...</div>
                </div>
            </div>
        </div>

        <!-- HEATMAP PANEL -->
        <div id="bazaar-heatmap-panel" class="bazaar-panel vendor-stall" data-stall-color="teal" role="tabpanel" hidden>
            <div class="stall-header">
                <div class="stall-sign">🗺️ Inventory Heatmap</div>
                <div class="stall-awning"></div>
            </div>
            <div class="stall-content">
                <div class="dmf-loading">
                    <div class="dmf-spinner">🎠</div>
                    <div>Mapping your treasures...</div>
                </div>
            </div>
        </div>



        <!-- WORKSHOP PANEL -->
        <div id="bazaar-workshop-panel" class="bazaar-panel vendor-stall" data-stall-color="copper" role="tabpanel"
            hidden>
            <div class="stall-header">
                <div class="stall-sign">🔨 Profession Workshop</div>
                <div class="stall-awning"></div>
            </div>
            <div class="stall-content">
                <div class="dmf-loading">
                    <div class="dmf-spinner">🎠</div>
                    <div>Opening the workshop...</div>
                </div>
            </div>
        </div>

    </div>

</section>
</section>