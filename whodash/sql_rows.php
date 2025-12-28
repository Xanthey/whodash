<?php
declare(strict_types=1);
/**
 * WhoDAT Database Printout (Dracula Theme, Collapsible + Lazy-Loaded Sections)
 * ---------------------------------------------------------------------------
 * Requirements:
 *   - A valid PDO connection in db.php:  $pdo = new PDO(...);
 *   - This file is intended for read-only printouts (no mutations).
 *
 * Key features:
 *   - All sections collapsed by default using <details>.
 *   - Lazy-load: tables are fetched (server-side rendered) only when a section
 *     is first opened, dramatically reducing initial DOM size and memory.
 *   - Dracula palette, accessible colors, sticky table headers, JSON pretty-print.
 */

require_once __DIR__ . '/db.php'; // must define $pdo = new PDO(...)

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo "<!doctype html><html><body style='background:#282a36;color:#f8f8f2;font-family:system-ui'>";
    echo "<h1>Database connection not initialized</h1>";
    echo "<p style='color:#ff5555'>Expected <code>$pdo</code> from <code>db.php</code>.</p>";
    echo "</body></html>";
    exit;
}

/* --------------------------- Shared helper functions ---------------------- */

/** Fetch rows with parameterized query. */
function fetch_rows(PDO $pdo, string $sql, int $character_id): array
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$character_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Pretty-print a single table as HTML. */
function pretty_print(string $title, array $rows, ?string $db_query = null): void
{
    echo "<section class='group'>\n";
    echo "  <h3 class='group-title'>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h3>\n";

    if ($db_query) {
        echo "  <details class='db-ref'>\n";
        echo "    <summary>Database Reference (SQL)</summary>\n";
        echo "    <pre class='code'><code>" . htmlspecialchars($db_query, ENT_QUOTES, 'UTF-8') . "</code></pre>\n";
        echo "  </details>\n";
    }

    if (empty($rows)) {
        echo "  <p class='empty'>No data found.</p>\n</section>\n";
        return;
    }

    echo "  <div class='table-wrap'>\n";
    echo "    <table class='dracula-table'>\n";
    echo "      <thead><tr>\n";
    foreach (array_keys($rows[0]) as $col) {
        echo "        <th>" . htmlspecialchars((string) $col, ENT_QUOTES, 'UTF-8') . "</th>\n";
    }
    echo "      </tr></thead>\n      <tbody>\n";

    foreach ($rows as $row) {
        echo "        <tr>\n";
        foreach ($row as $val) {
            $is_json = false;
            $cell = '';

            if (is_array($val)) {
                $cell = json_encode($val, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $is_json = true;
            } elseif (is_string($val) && $val !== '') {
                $decoded = json_decode($val, true);
                if (json_last_error() === JSON_ERROR_NONE && $decoded !== null) {
                    $cell = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    $is_json = true;
                }
            }

            if ($is_json) {
                echo "          <td class='json'><pre class='code'><code>" .
                    htmlspecialchars($cell, ENT_QUOTES, 'UTF-8') .
                    "</code></pre></td>\n";
            } else {
                echo "          <td>" .
                    htmlspecialchars($val === null ? '(null)' : (string) $val, ENT_QUOTES, 'UTF-8') .
                    "</td>\n";
            }
        }
        echo "        </tr>\n";
    }

    echo "      </tbody>\n    </table>\n";
    echo "  </div>\n</section>\n";
}

/** Return a set (assoc array) of available tables in the current DB (lowercased). */
function get_available_tables(PDO $pdo): array
{
    $dbNameStmt = $pdo->query("SELECT DATABASE() AS db");
    $dbRow = $dbNameStmt->fetch(PDO::FETCH_ASSOC);
    $dbName = $dbRow && !empty($dbRow['db']) ? $dbRow['db'] : null;

    $available = [];
    if ($dbName) {
        $q = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = :db";
        $stmt = $pdo->prepare($q);
        $stmt->execute([':db' => $dbName]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $available[strtolower($r['TABLE_NAME'])] = true;
        }
    } else {
        foreach ($pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $available[strtolower((string) $name)] = true;
        }
    }
    return $available;
}

/** Case-insensitive table existence check. */
function table_exists(array $available, string $name): bool
{
    return isset($available[strtolower($name)]);
}

/* --------------------------- AJAX section renderer ------------------------ */
/**
 * For lazy-loading: when a section is opened, the browser requests:
 *   GET sql_rows.php?ajax=1&char=<id>&group=<name>
 * We render only that group's tables and exit.
 */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: text/html; charset=utf-8');

    $character_id = isset($_GET['char']) ? (int) $_GET['char'] : 0;
    $group_name = isset($_GET['group']) ? (string) $_GET['group'] : '';

    if ($character_id <= 0 || $group_name === '') {
        http_response_code(400);
        echo "<p class='empty'>Invalid request.</p>";
        exit;
    }

    // --- Define logical groups and queries (aligned with your sql_setup) ---
    $logical_groups = [
        "Character Info" => ["characters"],
        "Inventory" => ["containers_bag", "containers_bank", "containers_keyring"],
        "Items" => ["items_catalog", "item_events"],
        "Stats / Timeseries" => [
            "series_xp",
            "series_money",
            "series_honor",
            "series_zones",
            "series_reputation",
            "series_base_stats",
            "series_spell_ranged",
            "series_resource_max",
            "series_level",
            "series_rested",
            "series_attack",
            "series_currency",
            "series_achievements",
            "series_arena"
        ],
        "Buffs / Debuffs" => ["buffs", "debuffs"],
        "Skills" => ["skills"],
        "Spellbook / Glyphs / Companions / Pets" => [
            "spellbook_tabs",
            "spells",
            "glyphs",
            "companions",
            "pet_stable",
            "pet_info",
            "pet_spells"
        ],
        "Mail" => ["mailbox", "mailbox_attachments"],
        "Equipment" => ["equipment_snapshot"],
        "Tradeskills" => ["tradeskills", "tradeskill_reagents"],
        "Talents" => ["talents_groups", "talents_tabs", "talents"],
        "Sessions" => ["sessions"],
        "Auction" => ["auction_owner_rows", "auction_market_ts", "auction_market_bands"],
        "Sharing" => ["account_sharing_policy", "share_links", "public_snapshots", "share_events"],
        "Other" => ["character_events"],
    ];

    $table_queries = [
        // Character & Identity
        "characters" => "SELECT * FROM characters WHERE id = ?",

        // Inventory
        "containers_bag" => "SELECT * FROM containers_bag WHERE character_id = ?",
        "containers_bank" => "SELECT * FROM containers_bank WHERE character_id = ?",
        "containers_keyring" => "SELECT * FROM containers_keyring WHERE character_id = ?",

        // Items
        "items_catalog" => "SELECT * FROM items_catalog WHERE character_id = ?",
        "item_events" => "SELECT * FROM item_events WHERE character_id = ? ORDER BY ts DESC",

        // Stats / Timeseries
        "series_xp" => "SELECT * FROM series_xp WHERE character_id = ? ORDER BY ts DESC",
        "series_money" => "SELECT * FROM series_money WHERE character_id = ? ORDER BY ts DESC",
        "series_honor" => "SELECT * FROM series_honor WHERE character_id = ? ORDER BY ts DESC",
        "series_zones" => "SELECT * FROM series_zones WHERE character_id = ? ORDER BY ts DESC",
        "series_reputation" => "SELECT * FROM series_reputation WHERE character_id = ? ORDER BY ts DESC",
        "series_base_stats" => "SELECT * FROM series_base_stats WHERE character_id = ? ORDER BY ts DESC",
        "series_spell_ranged" => "SELECT * FROM series_spell_ranged WHERE character_id = ? ORDER BY ts DESC",
        "series_resource_max" => "SELECT * FROM series_resource_max WHERE character_id = ? ORDER BY ts DESC",
        "series_level" => "SELECT * FROM series_level WHERE character_id = ? ORDER BY ts DESC",
        "series_rested" => "SELECT * FROM series_rested WHERE character_id = ? ORDER BY ts DESC",
        "series_attack" => "SELECT * FROM series_attack WHERE character_id = ? ORDER BY ts DESC",
        "series_currency" => "SELECT * FROM series_currency WHERE character_id = ? ORDER BY ts DESC",
        "series_achievements" => "SELECT * FROM series_achievements WHERE character_id = ? ORDER BY ts DESC",
        "series_arena" => "SELECT * FROM series_arena WHERE character_id = ? ORDER BY ts DESC",

        // Buffs / Debuffs
        "buffs" => "SELECT * FROM buffs WHERE character_id = ? ORDER BY ts DESC",
        "debuffs" => "SELECT * FROM debuffs WHERE character_id = ? ORDER BY ts DESC",

        // Skills
        "skills" => "SELECT * FROM skills WHERE character_id = ?",

        // Spellbook / Glyphs / Companions / Pets
        "spellbook_tabs" => "SELECT * FROM spellbook_tabs WHERE character_id = ?",
        "spells" => "SELECT s.* FROM spells s JOIN spellbook_tabs t ON s.spellbook_tab_id = t.id WHERE t.character_id = ?",
        "glyphs" => "SELECT * FROM glyphs WHERE character_id = ?",
        "companions" => "SELECT * FROM companions WHERE character_id = ?",
        "pet_stable" => "SELECT * FROM pet_stable WHERE character_id = ?",
        "pet_info" => "SELECT * FROM pet_info WHERE character_id = ?",
        "pet_spells" => "SELECT * FROM pet_spells WHERE character_id = ?",

        // Mail
        "mailbox" => "SELECT * FROM mailbox WHERE character_id = ?",
        "mailbox_attachments" => "SELECT * FROM mailbox_attachments WHERE mailbox_id IN (SELECT id FROM mailbox WHERE character_id = ?)",

        // Equipment
        "equipment_snapshot" => "SELECT * FROM equipment_snapshot WHERE character_id = ? ORDER BY ts DESC",

        // Tradeskills
        "tradeskills" => "SELECT * FROM tradeskills WHERE character_id = ?",
        "tradeskill_reagents" => "SELECT * FROM tradeskill_reagents WHERE tradeskill_id IN (SELECT id FROM tradeskills WHERE character_id = ?)",

        // Talents
        "talents_groups" => "SELECT * FROM talents_groups WHERE character_id = ?",
        "talents_tabs" => "SELECT * FROM talents_tabs WHERE talents_group_id IN (SELECT id FROM talents_groups WHERE character_id = ?)",
        "talents" => "SELECT * FROM talents WHERE talents_tab_id IN (SELECT id FROM talents_tabs WHERE talents_group_id IN (SELECT id FROM talents_groups WHERE character_id = ?))",

        // Sessions
        "sessions" => "SELECT * FROM sessions WHERE character_id = ? ORDER BY ts DESC",

        // Auction (schema: owner rows use "<Realm>-<Faction>:<Char>", market ts use "<Realm>-<Faction>")
        "auction_owner_rows" => "SELECT * FROM auction_owner_rows WHERE rf_char_key = (SELECT CONCAT(realm, '-', faction, ':', name) FROM characters WHERE id = ?)",
        "auction_market_ts" => "SELECT * FROM auction_market_ts  WHERE rf_key = (SELECT CONCAT(realm, '-', faction) FROM characters WHERE id = ?)",
        "auction_market_bands" => "SELECT * FROM auction_market_bands WHERE market_ts_id IN (SELECT id FROM auction_market_ts WHERE rf_key = (SELECT CONCAT(realm, '-', faction) FROM characters WHERE id = ?))",

        // Sharing
        "account_sharing_policy" => "SELECT * FROM account_sharing_policy WHERE user_id = (SELECT user_id FROM characters WHERE id = ?)",
        "share_links" => "SELECT * FROM share_links WHERE character_id = ?",
        "public_snapshots" => "SELECT * FROM public_snapshots WHERE character_id = ?",
        "share_events" => "SELECT * FROM share_events WHERE share_link_id IN (SELECT id FROM share_links WHERE character_id = ?)",

        // Other
        "character_events" => "SELECT * FROM character_events WHERE character_id = ? ORDER BY ts DESC",
    ];

    // Guard: only known groups
    if (!isset($logical_groups[$group_name])) {
        http_response_code(404);
        echo "<p class='empty'>Unknown group: " . htmlspecialchars($group_name, ENT_QUOTES, 'UTF-8') . "</p>";
        exit;
    }

    // Skip missing tables gracefully
    $available_tables = get_available_tables($pdo);

    foreach ($logical_groups[$group_name] as $table) {
        if (!isset($table_queries[$table]))
            continue;

        // If table doesn't exist, emit an empty section with reference
        if (!table_exists($available_tables, $table)) {
            $title = ucwords(str_replace('_', ' ', $table));
            pretty_print($title, [], $table_queries[$table]);
            continue;
        }

        $rows = fetch_rows($pdo, $table_queries[$table], $character_id);
        $title = ucwords(str_replace('_', ' ', $table));
        pretty_print($title, $rows, $table_queries[$table]);
    }
    exit;
}

/* ------------------------------ Page render ------------------------------- */

// Fetch characters list for the top-level sections
$stmt = $pdo->prepare("SELECT * FROM characters ORDER BY id ASC");
$stmt->execute();
$characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>WhoDAT Database Printout</title>
    <style>
        /* Dracula Palette */
        :root {
            --bg: #282a36;
            --current: #44475a;
            --fg: #f8f8f2;
            --comment: #6272a4;
            --cyan: #8be9fd;
            --green: #50fa7b;
            --orange: #ffb86c;
            --pink: #ff79c6;
            --purple: #bd93f9;
            --red: #ff5555;
            --yellow: #f1fa8c;
        }

        html,
        body {
            background: var(--bg);
            color: var(--fg);
            font: 14px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            margin: 0;
            padding: 0;
        }

        .page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }

        h1,
        h2,
        h3 {
            margin: 0 0 12px;
            line-height: 1.25;
            color: var(--purple);
        }

        h1 {
            font-size: 1.6rem;
            color: var(--pink);
        }

        h2 {
            font-size: 1.3rem;
            color: var(--purple);
            border-bottom: 1px solid var(--current);
            padding-bottom: 6px;
        }

        h3.group-title {
            font-size: 1.15rem;
            color: var(--cyan);
            margin-top: 20px;
        }

        .character {
            border: 1px solid var(--current);
            border-radius: 8px;
            padding: 16px;
            margin: 18px 0;
            background: #2b2d3a;
            box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15) inset;
        }

        .group {
            margin: 16px 0;
        }

        .empty {
            color: var(--comment);
            font-style: italic;
        }

        .db-ref {
            margin: 8px 0 12px;
            border: 1px solid var(--current);
            border-radius: 6px;
            overflow: hidden;
        }

        .db-ref>summary {
            cursor: pointer;
            background: var(--current);
            color: var(--fg);
            padding: 8px 12px;
            font-weight: 600;
        }

        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--current);
            border-radius: 6px;
        }

        table.dracula-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
            background: #303245;
        }

        thead th {
            position: sticky;
            top: 0;
            background: #3a3d55;
            color: var(--yellow);
            text-align: left;
            font-weight: 700;
            padding: 10px 12px;
            border-bottom: 2px solid var(--current);
        }

        tbody td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--current);
            vertical-align: top;
            color: var(--fg);
        }

        tbody tr:nth-child(even) td {
            background: #2e3044;
        }

        tbody tr:hover td {
            background: #353852;
        }

        pre.code {
            margin: 0;
            padding: 10px 12px;
            background: #1e1f29;
            color: var(--fg);
            border-top: 1px solid var(--current);
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace;
            font-size: 12px;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-word;
        }

        td.json pre.code {
            border: 1px solid var(--current);
            border-radius: 4px;
        }

        /* Collapsible sections (default collapsed) */
        .collapsible {
            border: 1px solid var(--current);
            border-radius: 8px;
            margin: 10px 0;
            background: #2b2d3a;
        }

        .collapsible>summary {
            padding: 10px 14px;
            cursor: pointer;
            font-weight: 700;
            color: var(--cyan);
            list-style: none;
        }

        .collapsible>summary::marker {
            display: none;
        }

        .collapsible[open]>summary {
            color: var(--green);
        }

        .lazy-target {
            padding: 8px 12px;
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--comment);
            border-top-color: var(--pink);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            vertical-align: middle;
            margin-right: 6px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        a {
            color: var(--green);
        }

        ::selection {
            background: var(--purple);
            color: var(--bg);
        }
    </style>
</head>

<body>
    <div class="page">
        <h1>WhoDAT Database Printout</h1>

        <?php
        foreach ($characters as $char) {
            $character_id = (int) $char['id'];
            $char_name = isset($char['name']) ? (string) $char['name'] : ('Character #' . (string) $character_id);

            echo "<section class='character'>\n";
            echo "  <h2>Character: " . htmlspecialchars($char_name, ENT_QUOTES, 'UTF-8') .
                " (ID " . htmlspecialchars((string) $character_id, ENT_QUOTES, 'UTF-8') . ")</h2>\n";

            // Top-level display groups (collapsible; content loaded via AJAX when opened)
            $display_groups = [
                "Character Info",
                "Inventory",
                "Items",
                "Stats / Timeseries",
                "Buffs / Debuffs",
                "Skills",
                "Spellbook / Glyphs / Companions / Pets",
                "Mail",
                "Equipment",
                "Tradeskills",
                "Talents",
                "Sessions",
                "Auction",
                "Sharing",
                "Other"
            ];

            foreach ($display_groups as $group) {
                $gid = 'g_' . $character_id . '_' . preg_replace('/[^a-z0-9]+/i', '_', $group);
                echo "  <details class='collapsible' data-char='{$character_id}' data-group=\"" .
                    htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . "\" id='{$gid}'>\n";
                echo "    <summary>" . htmlspecialchars($group, ENT_QUOTES, 'UTF-8') . " — click to load</summary>\n";
                echo "    <div class='lazy-target' aria-live='polite'>\n";
                echo "      <noscript><p class='empty' style='color: var(--red)'>JavaScript required to load this section.</p></noscript>\n";
                echo "    </div>\n";
                echo "  </details>\n";
            }

            echo "</section>\n";
        }
        ?>

    </div>
    <script>
        // Lazy-load group content on first open; keep it cached after load
        document.querySelectorAll('details.collapsible').forEach(det => {
            let loaded = false;
            det.addEventListener('toggle', async () => {
                if (!det.open || loaded) return;
                loaded = true;

                const tgt = det.querySelector('.lazy-target');
                const char = det.dataset.char;
                const group = det.dataset.group;

                tgt.innerHTML = "<div><span class='spinner'></span>Loading " + group + "...</div>";

                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('ajax', '1');
                    url.searchParams.set('char', char);
                    url.searchParams.set('group', group);

                    const resp = await fetch(url.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                    if (!resp.ok) throw new Error('HTTP ' + resp.status);
                    const html = await resp.text();
                    tgt.innerHTML = html;
                } catch (e) {
                    tgt.innerHTML = "<p class='empty' style='color: var(--red)'>Failed to load: " +
                        (e && e.message ? e.message : 'Unknown error') + "</p>";
                }
            }, { passive: true });
        });
    </script>
</body>

</html>