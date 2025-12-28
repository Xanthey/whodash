<?php
// -------------------------------------------------
// DB Connection Setup
// -------------------------------------------------
ini_set('display_errors', 1);
error_reporting(E_ALL);
$host = getenv('DB_HOST') ?: 'backend';
$db = getenv('DB_NAME') ?: 'whodat';
$user = getenv('DB_USER') ?: 'whodatuser';
$pass = getenv('DB_PASSWORD') ?: 'whodatpass';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

date_default_timezone_set('America/Chicago');
$timestamp = date("F j Y g:i:s A");
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    die("Database connection error: " . htmlspecialchars($e->getMessage()));
}

// -------------------------------------------------
// Preset Functions (updated for new schema)
// -------------------------------------------------
function showCharacterCount($pdo)
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM characters");
    return "Characters count: " . $stmt->fetchColumn();
}
function showAllTables($pdo)
{
    $stmt = $pdo->query("SHOW TABLES");
    $stmt->setFetchMode(PDO::FETCH_NUM);
    $tables = [];
    foreach ($stmt as $row) {
        $tables[] = $row[0];
    }
    return implode("\n", $tables);
}
function dropAllTables($pdo)
{
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $stmt = $pdo->query("SHOW TABLES");
    $stmt->setFetchMode(PDO::FETCH_NUM);
    $tables = [];
    foreach ($stmt as $row) {
        $tables[] = $row[0];
    }
    foreach ($tables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    return "✅ All tables dropped!";
}
function showSnapshots($pdo)
{
    $stmt = $pdo->query("SELECT * FROM snapshots ORDER BY id DESC LIMIT 50");
    $rows = $stmt->fetchAll();
    return json_encode($rows, JSON_PRETTY_PRINT);
}
function showProfessionsRaw($pdo)
{
    $stmt = $pdo->query("SELECT * FROM professions ORDER BY character_id, profession_name");
    return json_encode($stmt->fetchAll(), JSON_PRETTY_PRINT);
}
function showProfessionsByCharacter($pdo)
{
    $sql = "
    SELECT c.name AS character_name, p.profession_name, p.level
    FROM professions p
    JOIN characters c ON p.character_id = c.id
    ORDER BY c.name, p.profession_name";
    $rows = $pdo->query($sql)->fetchAll();
    return json_encode($rows, JSON_PRETTY_PRINT);
}
function showProfessionSpells($pdo)
{
    $sql = "
    SELECT c.name AS character_name, p.profession_name, ps.spell_name, ps.spell_id, ps.num_made
    FROM profession_spells ps
    JOIN professions p ON ps.profession_id = p.id
    JOIN characters c ON p.character_id = c.id
    ORDER BY c.name, p.profession_name, ps.spell_name";
    $rows = $pdo->query($sql)->fetchAll();
    return json_encode($rows, JSON_PRETTY_PRINT);
}
function showRecipeReagents($pdo)
{
    $sql = "
    SELECT ps.spell_name, rr.reagent_name, rr.reagent_count
    FROM recipe_reagents rr
    JOIN profession_spells ps ON rr.profession_spell_id = ps.id
    ORDER BY ps.spell_name, rr.reagent_name";
    return json_encode($pdo->query($sql)->fetchAll(), JSON_PRETTY_PRINT);
}
function showLatestEquippedGear($pdo)
{
    // prefer new flattened latest view if present
    try {
        $rows = $pdo->query("SELECT * FROM equipped_gear_latest_flat ORDER BY character_id, slot")->fetchAll();
        if ($rows)
            return json_encode($rows, JSON_PRETTY_PRINT);
    } catch (Throwable $e) { /* fall back below */
    }
    try {
        $rows = $pdo->query("SELECT * FROM equipped_gear_latest")->fetchAll();
        return json_encode($rows, JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        return "No equipped gear latest view found.";
    }
}
function showInventoryTotals($pdo)
{
    $sql = "SELECT * FROM player_item_totals ORDER BY character_id, item_id";
    try {
        return json_encode($pdo->query($sql)->fetchAll(), JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        return "View player_item_totals not available.";
    }
}
function showMailbox($pdo)
{
    $sql = "SELECT * FROM mailbox ORDER BY character_id, received_ts DESC";
    return json_encode($pdo->query($sql)->fetchAll(), JSON_PRETTY_PRINT);
}
function showMailItems($pdo)
{
    try {
        $sql = "SELECT * FROM mail_items_all ORDER BY character_id, mail_id";
        return json_encode($pdo->query($sql)->fetchAll(), JSON_PRETTY_PRINT);
    } catch (Throwable $e) {
        return "View mail_items_all not available.";
    }
}
function showDbInfo($pdo, $db, $host, $timestamp)
{
    return "DB: $db\nHost: $host\nTime: $timestamp";
}

// -------------------------------------------------
// Preset Mapping
// -------------------------------------------------
$presetFunctions = [
    "Show Character Count" => fn() => showCharacterCount($pdo),
    "Show All Tables" => fn() => showAllTables($pdo),
    "Drop All Tables" => fn() => dropAllTables($pdo),
    "Show Snapshots" => fn() => showSnapshots($pdo),
    "Show Professions (Raw)" => fn() => showProfessionsRaw($pdo),
    "Show Professions by Character" => fn() => showProfessionsByCharacter($pdo),
    "Show Profession Spells" => fn() => showProfessionSpells($pdo),
    "Show Recipe Reagents" => fn() => showRecipeReagents($pdo),
    "Show Latest Equipped Gear" => fn() => showLatestEquippedGear($pdo),
    "Show Inventory Totals" => fn() => showInventoryTotals($pdo),
    "Show Mailbox" => fn() => showMailbox($pdo),
    "Show Mail Items" => fn() => showMailItems($pdo),
    "Show DB Info" => fn() => showDbInfo($pdo, $db, $host, $timestamp),
];

// -------------------------------------------------
// Helper: Run arbitrary SQL (DML / DDL supported)
// -------------------------------------------------
function runArbitrarySql(PDO $pdo, string $sql): array
{
    // Allow multi-statements: split on semicolons not within quotes (simple parser)
    $stmts = [];
    $buffer = '';
    $inSingle = false;
    $inDouble = false;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];
        if ($ch === "'" && !$inDouble) {
            $inSingle = !$inSingle;
            $buffer .= $ch;
            continue;
        }
        if ($ch === '"' && !$inSingle) {
            $inDouble = !$inDouble;
            $buffer .= $ch;
            continue;
        }
        if ($ch === ';' && !$inSingle && !$inDouble) {
            if (trim($buffer) !== '') {
                $stmts[] = trim($buffer);
            }
            $buffer = '';
        } else {
            $buffer .= $ch;
        }
    }
    if (trim($buffer) !== '') {
        $stmts[] = trim($buffer);
    }

    $results = [];
    foreach ($stmts as $idx => $stmtSql) {
        $first = strtolower(strtok($stmtSql, " \n\r\t"));
        // Query-type statements -> fetch rows
        if (in_array($first, ['select', 'show', 'describe', 'explain'])) {
            $stmt = $pdo->query($stmtSql);
            $rows = $stmt->fetchAll();
            $results[] = [
                'statement' => $stmtSql,
                'type' => 'resultset',
                'rowcount' => count($rows),
                'rows' => $rows,
            ];
        } else {
            // DML/DDL -> exec returns affected rows; include lastInsertId when relevant
            $affected = $pdo->exec($stmtSql);
            $lastId = null;
            try {
                $lastId = $pdo->lastInsertId();
            } catch (Throwable $e) { /* ignore */
            }
            $results[] = [
                'statement' => $stmtSql,
                'type' => 'ack',
                'affected' => $affected,
                'lastInsertId' => $lastId,
            ];
        }
    }
    return $results;
}

// -------------------------------------------------
// Handle Form Submission
// -------------------------------------------------
$output = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['preset'])) {
        $preset = $_POST['preset'];
        if (isset($presetFunctions[$preset])) {
            try {
                $output = $presetFunctions[$preset]();
            } catch (Throwable $e) {
                $output = "Error: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $output = "Invalid preset selected.";
        }
    } elseif (!empty($_POST['sql_query'])) {
        $sql = trim($_POST['sql_query']);
        try {
            $results = runArbitrarySql($pdo, $sql);
            $output = json_encode($results, JSON_PRETTY_PRINT);
        } catch (Throwable $e) {
            $output = "SQL Error: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>SQL Admin Runner (Local)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        textarea {
            width: 100%;
            height: 140px;
            font-family: monospace;
        }

        .output {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ccc;
            background: #f9f9f9;
            white-space: pre-wrap;
        }

        .presets {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            width: 100%;
        }

        .presets button {
            padding: 8px 12px;
        }

        h1 {
            margin-bottom: 6px;
        }

        .danger {
            background-color: #d9534f;
            color: white;
            font-weight: bold;
            border: none;
            padding: 8px 12px;
        }

        .note {
            color: #a94442;
        }
    </style>
</head>

<body>
    <h1>SQL Admin Runner</h1>
    <p>Database: <strong><?php echo htmlspecialchars($db); ?></strong> — Host:
        <strong><?php echo htmlspecialchars($host); ?></strong></p>
    <p>Current Time: <strong><?php echo $timestamp; ?></strong></p>

    <!-- Free-form SQL Runner (ALL statements allowed) -->
    <h2>Run Custom SQL</h2>
    <p class="note">⚠️ This runs any SQL you provide (SELECT/INSERT/UPDATE/DELETE/DDL). Intended for local admin use
        only.</p>
    <form method="post">
        <textarea name="sql_query"
            placeholder="BEGIN;\nUPDATE items_catalog SET quality = 5 WHERE item_id = 123;\nCOMMIT;\nSELECT * FROM items_catalog WHERE item_id = 123;\n"></textarea><br><br>
        <button type="submit">Run SQL</button>
    </form>

    <!-- Preset Commands -->
    <h2>Preset Commands</h2>
    <div class="presets" style="justify-content: space-between; align-items: center;">
        <div style="display: flex; flex-wrap: wrap; gap: 10px;">
            <?php foreach (array_keys($presetFunctions) as $label): ?>
                <?php if ($label !== "Drop All Tables"): ?>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="preset" value="<?php echo htmlspecialchars($label); ?>">
                        <button type="submit"><?php echo htmlspecialchars($label); ?></button>
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <!-- Dangerous button isolated on the far right -->
        <div>
            <form method="post" style="display:inline;">
                <input type="hidden" name="preset" value="Drop All Tables">
                <button type="submit" class="danger"
                    onclick="return confirm('⚠ Are you sure you want to DROP ALL TABLES? This action cannot be undone!');">
                    Drop All Tables
                </button>
            </form>
        </div>
    </div>

    <?php if ($output !== ''): ?>
        <div class="output">
            <h3>Output:</h3>
            <pre><?php echo htmlspecialchars($output); ?></pre>
        </div>
    <?php endif; ?>
</body>

</html>