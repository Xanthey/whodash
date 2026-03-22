<?php
// tooltip.php — WhoDASH Tooltip Proxy
// Extracts tooltip_enus HTML and reagent data from Wowhead WotLK item/spell pages.
//
// Usage:
//   /tooltip.php?type=item&id=30422
//   /tooltip.php?type=enchant&id=7454
//   /tooltip.php?debug=1&type=item&id=30422

declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

$cacheDir = __DIR__ . '/ux/tooltips';
if (!is_dir($cacheDir))
    @mkdir($cacheDir, 0755, true);

define('TOOLTIP_CACHE_TTL', 86400);
define('WH_VER', 'wotlk');

// Icon cache dir — shared with icon.php so both can read/write the same files
define('ICON_CACHE_DIR', __DIR__ . '/ux/cache');

/**
 * After tooltip.php resolves an icon name, pre-download the image into icon.php's
 * cache so icon.php gets a local file hit and never needs to fetch zamimg itself.
 * Also writes the _iconname.txt only after confirming the image downloaded OK.
 */
function prewarmIconCache(int $id, string $whEp, ?string $iconName): void
{
    if (!$iconName || $iconName === '')
        return;

    $dir = ICON_CACHE_DIR;
    if (!is_dir($dir))
        @mkdir($dir, 0755, true);

    $prefix = ($whEp === 'item') ? 'item' : 'spell';
    $iconNameFile = $dir . '/' . $prefix . $id . '_iconname.txt';

    // Download all three sizes that icon.php may serve
    $sizes = ['small', 'medium', 'large'];
    $anySuccess = false;

    foreach ($sizes as $size) {
        $localFile = $dir . '/' . $iconName . '_' . $size . '.jpg';
        if (file_exists($localFile)) {
            $anySuccess = true;
            continue;
        } // already cached

        $url = 'https://wow.zamimg.com/images/wow/icons/' . $size . '/' . $iconName . '.jpg';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => ['Accept: image/*,*/*;q=0.8'],
        ]);
        $data = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code >= 200 && $code < 300 && $data && strlen($data) > 0) {
            @file_put_contents($localFile, $data);
            $anySuccess = true;
        }
    }

    // Only write the name cache file if at least one image was confirmed good
    if ($anySuccess && !file_exists($iconNameFile)) {
        @file_put_contents($iconNameFile, $iconName);
    }
}

// ---------------------------------------------------------------------------
// Request
// ---------------------------------------------------------------------------
$debug = !empty($_GET['debug']);
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'item';
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$characterId = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

if (!in_array($type, ['item', 'enchant', 'spell'], true))
    jsonOut(['error' => 'Invalid type'], 400);
if ($id <= 0)
    jsonOut(['error' => 'Provide ?id=N'], 400);

// Enchants live on /spell= pages; items on /item=
$whEp = ($type === 'item') ? 'item' : 'spell';
$whType = ($type === 'item') ? 3 : 6;   // gatherer type numbers

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function jsonOut(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function wh_get(string $url, array &$log): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*', 'Accept-Language: en-US,en;q=0.9'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    $bytes = (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);

    $e = ['url' => $url, 'code' => $code, 'bytes' => $bytes];
    if ($curlErr) {
        $e['curl_error'] = $curlErr;
        $log[] = $e;
        return null;
    }
    if ($code >= 200 && $code < 300 && $body) {
        $e['ok'] = true;
        $log[] = $e;
        return $body;
    }
    $e['error'] = "HTTP {$code}";
    $log[] = $e;
    return null;
}

function extractBalancedJson(string $str, int $start): ?string
{
    $len = strlen($str);
    $depth = 0;
    $inStr = false;
    $escape = false;
    for ($i = $start; $i < $len; $i++) {
        $c = $str[$i];
        if ($escape) {
            $escape = false;
            continue;
        }
        if ($c === '\\' && $inStr) {
            $escape = true;
            continue;
        }
        if ($c === '"') {
            $inStr = !$inStr;
            continue;
        }
        if ($inStr) {
            continue;
        }
        if ($c === '{')
            $depth++;
        elseif ($c === '}') {
            $depth--;
            if ($depth === 0)
                return substr($str, $start, $i - $start + 1);
        }
    }
    return null;
}

/**
 * Extract all useful data from the Wowhead item/spell page:
 *   - tooltip_enus : full tooltip HTML (the gold standard)
 *   - gatherer entry: name_enus, icon, quality
 *   - reagents array: [[itemId, count], ...]
 *   - g_items extended: slot, level, source info
 */
function extractPageData(string $html, int $id, int $whType, array &$log): array
{
    $result = [
        'tooltip_html' => null,
        'gatherer' => null,
        'reagents' => null,
        'extended' => null,
    ];

    // 1. tooltip_enus — the full rendered tooltip HTML
    // Pattern: g_items[30422].tooltip_enus = "...HTML...";
    //      or: g_spells[7454].tooltip_enus = "...";
    $varName = ($whType === 3) ? 'g_items' : 'g_spells';
    $tooltipPattern = '#' . preg_quote($varName) . '\[' . $id . '\]\.tooltip_enus\s*=\s*"((?:[^"\\\\]|\\\\.)*)"\s*;#s';
    if (preg_match($tooltipPattern, $html, $m)) {
        $result['tooltip_html'] = stripslashes($m[1]);
        $log[] = ['found' => 'tooltip_enus', 'len' => strlen($result['tooltip_html'])];
    } else {
        $log[] = ['not_found' => 'tooltip_enus'];
    }

    // 2. Gatherer entry — name_enus, icon, quality, description_enus
    $blockPat = '#WH\.Gatherer\.addData\s*\(\s*' . $whType . '\s*,\s*\d+\s*,\s*\{#';
    if (preg_match($blockPat, $html, $bm, PREG_OFFSET_CAPTURE)) {
        $blockStart = $bm[0][1];
        $needle = '"' . $id . '":{';
        $pos = strpos($html, $needle, $blockStart);
        if ($pos !== false) {
            $bracePos = $pos + strlen((string) $id) + 3;
            $json = extractBalancedJson($html, $bracePos);
            if ($json) {
                $entry = json_decode($json, true);
                if (is_array($entry)) {
                    $result['gatherer'] = $entry;
                    $log[] = ['found' => 'gatherer', 'keys' => array_keys($entry)];
                }
            }
        }
    }
    if (!$result['gatherer'])
        $log[] = ['not_found' => 'gatherer'];

    // 3. Reagents — extract if item is craftable, skip if item is just used as a reagent
    // Strategy: Look for context clues in the HTML around the "reagents" pattern
    // - If "reagents" appears near "Reagent for" → SKIP (these are recipes using this item)
    // - If "reagents" appears in normal context → EXTRACT (these are crafting materials)

    if (preg_match('#"reagents"\s*:\s*(\[\[.+?\]\])#s', $html, $m, PREG_OFFSET_CAPTURE)) {
        $reagentsJson = $m[1][0];
        $matchPos = $m[0][1];

        // Look at 500 chars before the match to check context
        $contextBefore = substr($html, max(0, $matchPos - 500), 500);

        // Check if this is in a "Reagent for" context (bad - means recipes that USE this item)
        $isReagentFor = (
            stripos($contextBefore, 'reagent-for') !== false ||
            stripos($contextBefore, '"rforspell"') !== false ||
            stripos($contextBefore, 'reagentFor') !== false
        );

        if (!$isReagentFor) {
            // Safe to extract - this is likely a crafting recipe
            $reagents = json_decode($reagentsJson, true);
            if (is_array($reagents) && count($reagents) > 0) {
                $result['reagents'] = $reagents;
                $log[] = ['found' => 'reagents', 'count' => count($reagents)];
            } else {
                $log[] = ['not_found' => 'reagents', 'reason' => 'invalid_json'];
            }
        } else {
            $log[] = ['not_found' => 'reagents', 'reason' => 'reagent_for_context'];
        }
    } else {
        $log[] = ['not_found' => 'reagents', 'reason' => 'no_pattern_match'];
    }

    // 3b. Reagent For — NOTE: Wowhead doesn't include "createdby" in JavaScript for WotLK
    // We'll need to extract this from tooltip HTML or use a different approach
    // For now, this data is not available

    // 3c. Drop Info — NOTE: Wowhead doesn't include "droppedbynpc" in JavaScript for WotLK  
    // We extract this from the tooltip HTML instead (see parseTooltipToLines section)

    // 4b. Reagent names — the item page embeds gatherer data for all reagent items too.
    // Extract name_enus and icon for each reagent ID directly from the page HTML.
    $reagentNames = [];
    if (!empty($result['reagents'])) {
        foreach ($result['reagents'] as $r) {
            $rId = (int) ($r[0] ?? 0);
            if ($rId <= 0)
                continue;
            $rNeedle = '"' . $rId . '":{';
            // Search in the item gatherer block (type 3)
            $rPos = strpos($html, $rNeedle);
            if ($rPos !== false) {
                $rBrace = $rPos + strlen((string) $rId) + 3;
                $rJson = extractBalancedJson($html, $rBrace);
                if ($rJson) {
                    $rEntry = json_decode($rJson, true);
                    if (is_array($rEntry) && !empty($rEntry['name_enus'])) {
                        $reagentNames[$rId] = [
                            'name' => $rEntry['name_enus'],
                            'icon' => isset($rEntry['icon']) ? strtolower((string) $rEntry['icon']) : null,
                        ];
                    }
                }
            }
        }
    }
    $result['reagent_names'] = $reagentNames;

    // 4. $.extend(g_items[id], {...}) — has slot, level, source, subclass etc
    $extPattern = '#\$\.extend\s*\(\s*' . preg_quote($varName) . '\[' . $id . '\]\s*,\s*(\{.+?\})\s*\)\s*;#s';
    if (preg_match($extPattern, $html, $m)) {
        $ext = json_decode($m[1], true);
        if (is_array($ext)) {
            $result['extended'] = $ext;
            $log[] = ['found' => 'extended', 'keys' => array_keys($ext)];
        }
    }

    return $result;
}

/**
 * Clean Wowhead tooltip HTML for display:
 * - Remove HTML comments (<!--nstart-->, <!--ilvl-->, etc.)
 * - Strip anchor tags but keep text
 * - Keep <br>, bold, span for rendering
 */
function parseTooltipToLines(string $html, string $itemName): array
{
    if (trim($html) === '')
        return [];

    // Remove HTML comments
    $html = preg_replace('/<!--.*?-->/s', '', $html);

    // Strip the item name block: <!--nstart-->...<b class="qN">Name</b>...<!--nend-->
    $html = preg_replace('#<!--nstart-->.*?<!--nend-->#s', '', $html);
    // Strip phase/extra info: <th>...</th>
    $html = preg_replace('#<th[^>]*>.*?</th>#s', '', $html);

    // Convert sell price div to a readable line
    $html = preg_replace_callback(
        '#<div[^>]*class="[^"]*whtt-sellprice[^"]*"[^>]*>(.*?)<\/div>#is',
        fn($m) => "\n" . strip_tags($m[1]),
        $html
    );

    // Block tags → newlines
    $html = preg_replace('#<(?:br|tr|p|div|li)[\s/>][^>]*>#i', "\n", $html);
    $html = preg_replace('#<br\s*/?>|<br>#i', "\n", $html);
    $html = preg_replace('#</(?:tr|p|div|li)>#i', "\n", $html);

    // Strip remaining tags and decode entities
    $text = strip_tags($html);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $lowerName = strtolower(trim($itemName));
    $lines = [];
    foreach (preg_split('/[\r\n]+/', $text) as $line) {
        $line = trim($line);
        if (strlen($line) < 2)
            continue;
        if (strtolower($line) === $lowerName)
            continue;
        if (preg_match('/^Phase\s+\d+$/i', $line))
            continue;
        $lines[] = $line;
    }

    return array_values($lines);
}
// ---------------------------------------------------------------------------
// Cache check
// ---------------------------------------------------------------------------
// Note: we cache without character_id so tooltip data is shared; availability is added fresh each time
$cacheKey = 'v5_' . $type . '_' . $id;
$cacheFile = $cacheDir . '/' . $cacheKey . '.json';

if (!$debug && !$characterId && file_exists($cacheFile)) {
    // Only serve cache when no character_id — availability data must be fresh per character
    $age = time() - (int) filemtime($cacheFile);
    if ($age < TOOLTIP_CACHE_TTL) {
        // Pre-warm icon.php's image cache if missing (e.g. after icon cache cleared)
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached) && !empty($cached['icon'])) {
            prewarmIconCache($id, $whEp, $cached['icon']);
        }
        header('Content-Type: application/json; charset=utf-8');
        header('X-Tooltip-Cache: HIT');
        header('Cache-Control: public, max-age=' . (TOOLTIP_CACHE_TTL - $age));
        readfile($cacheFile);
        exit;
    }
}

// ---------------------------------------------------------------------------
// Fetch & extract
// ---------------------------------------------------------------------------
$log = [];
$pageUrl = 'https://www.wowhead.com/' . WH_VER . '/' . $whEp . '=' . $id;
$html = wh_get($pageUrl, $log);

if (!$html) {
    if ($debug)
        jsonOut(['status' => 'FAILED: fetch', 'url' => $pageUrl, 'log' => $log]);
    jsonOut(['error' => 'Could not fetch Wowhead page', 'id' => $id], 502);
}

$data = extractPageData($html, $id, $whType, $log);

// Need at least the gatherer entry for name/icon/quality
if (!$data['gatherer'] && !$data['tooltip_html']) {
    if ($debug)
        jsonOut(['status' => 'FAILED: no data extracted', 'log' => $log]);
    jsonOut(['error' => 'Could not extract data', 'id' => $id], 502);
}

// ---------------------------------------------------------------------------
// Build response
// ---------------------------------------------------------------------------
$qualityMap = [
    0 => 'q-poor',
    1 => 'q-common',
    2 => 'q-uncommon',
    3 => 'q-rare',
    4 => 'q-epic',
    5 => 'q-legendary',
    6 => 'q-artifact',
    7 => 'q-heirloom',
];

$g = $data['gatherer'] ?? [];
$ext = $data['extended'] ?? [];
$quality = (int) ($g['quality'] ?? $ext['quality'] ?? 1);

// Item name for deduplication in stat lines
$itemName = $g['name_enus'] ?? $g['name'] ?? $ext['name'] ?? '';

// Parse tooltip_enus HTML into clean text stat lines (never send raw HTML to browser)
$rawTooltipHtml = $data['tooltip_html'] ?? '';
if ($rawTooltipHtml) {
    $stats = parseTooltipToLines($rawTooltipHtml, $itemName);
} elseif ($type !== 'item') {
    // Spells/enchants: use description_enus
    $desc = $g['description_enus'] ?? $g['description'] ?? '';
    $stats = $desc ? [$desc] : [];
} else {
    $stats = [];
}

// Extract "Dropped by" info from stats (it's in the tooltip HTML, not JavaScript data)
$droppedByInfo = [];
for ($i = 0; $i < count($stats); $i++) {
    $line = $stats[$i];
    // Look for "Dropped by: [NPC Name]" followed by "Drop Chance: X%"
    if (preg_match('/^Dropped by:\s*(.+)$/i', $line, $m)) {
        $npcName = trim($m[1]);
        $dropChance = null;

        // Check next line for drop chance
        if ($i + 1 < count($stats) && preg_match('/^Drop Chance:\s*([\d.]+)%/i', $stats[$i + 1], $cm)) {
            $dropChance = (float) $cm[1];
        }

        $droppedByInfo[] = [
            'name' => $npcName,
            'drop_chance' => $dropChance,
        ];
    }
}

// Remove "Dropped by" and "Drop Chance" lines from stats (we'll show them separately)
$stats = array_values(array_filter($stats, function ($line) {
    return !preg_match('/^(Dropped by:|Drop Chance:)/i', $line);
}));

// Reagents: resolve names from tradeskill_reagents table in our own DB
$reagents = [];
if (!empty($data['reagents'])) {
    // Build a map of reagent item IDs we need names for
    $reagentIds = [];
    foreach ($data['reagents'] as $r) {
        $rId = (int) ($r[0] ?? 0);
        if ($rId > 0)
            $reagentIds[$rId] = (int) ($r[1] ?? 1);
    }

    if (!empty($reagentIds)) {
        try {
            require_once __DIR__ . '/db.php';
            // $pdo is created by db.php

            // Match reagents by looking for Hitem:ID in the link column.
            // Run one query per reagent ID using LIKE — simple and reliable.
            $nameMap = [];
            foreach (array_keys($reagentIds) as $rId) {
                $stmt = $pdo->prepare(
                    "SELECT name, icon
                     FROM tradeskill_reagents
                     WHERE link LIKE ?
                     LIMIT 1"
                );
                $stmt->execute(['%Hitem:' . $rId . ':%']);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $nameMap[$rId] = [
                        'name' => $row['name'],
                        'icon' => $row['icon'] ?? null,
                    ];
                }
            }
        } catch (\Throwable $e) {
            error_log("tooltip.php: reagent DB lookup failed: " . $e->getMessage());
            $nameMap = [];
        }

        $pageNames = $data['reagent_names'] ?? [];

        // Check character inventory for each reagent
        $inventoryMap = [];
        if ($characterId > 0) {
            try {
                $ids = array_keys($reagentIds);
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $invStmt = $pdo->prepare(
                    "SELECT item_id,
                            COALESCE(quantity_bag, 0) + COALESCE(quantity_bank, 0) AS total_qty
                     FROM items_catalog
                     WHERE character_id = ? AND item_id IN ({$placeholders})"
                );
                $invStmt->execute(array_merge([$characterId], $ids));
                while ($row = $invStmt->fetch(PDO::FETCH_ASSOC)) {
                    $inventoryMap[(int) $row['item_id']] = (int) $row['total_qty'];
                }
            } catch (\Throwable $e) {
                error_log("tooltip.php: inventory check failed: " . $e->getMessage());
            }
        }

        foreach ($reagentIds as $rId => $rQty) {
            $rName = $pageNames[$rId]['name'] ?? $nameMap[$rId]['name'] ?? null;
            $rIcon = $pageNames[$rId]['icon'] ?? (isset($nameMap[$rId]['icon']) ? strtolower((string) $nameMap[$rId]['icon']) : null);
            $haveQty = $inventoryMap[$rId] ?? 0;
            $reagents[] = [
                'id' => $rId,
                'qty' => $rQty,
                'name' => $rName,
                'icon' => $rIcon,
                'have' => $haveQty,
                'available' => $haveQty >= $rQty,
            ];
        }
    }
}

// Reagent For - Not available from Wowhead JavaScript data for WotLK
// Would need to scrape from "Reagent for" section of rendered HTML page
$reagentForList = [];

// Use dropped_by info extracted from tooltip HTML stats
$droppedByList = $droppedByInfo;

$response = [
    'id' => $id,
    'type' => $type,
    'name' => $itemName,
    'icon' => isset($g['icon']) ? strtolower((string) $g['icon']) : null,
    'quality' => $quality,
    'quality_class' => $qualityMap[$quality] ?? 'q-common',
    'stats' => $stats,
    'reagents' => $reagents,
    'reagent_for' => $reagentForList,
    'dropped_by' => $droppedByList,
    'item_level' => (int) ($ext['level'] ?? 0) ?: null,
    'slot' => (int) ($ext['slot'] ?? 0) ?: null,
    'source' => 'wowhead/' . WH_VER,
    'cached_at' => time(),
    'wowhead_url' => 'https://www.wowhead.com/' . WH_VER . '/' . $whEp . '=' . $id,
];

if ($debug) {
    $response['_debug'] = [
        'page_url' => $pageUrl,
        'log' => $log,
        'raw_tooltip' => substr($rawTooltipHtml, 0, 500),
        'gatherer' => $g,
        'extended' => $ext,
        'cache_file' => $cacheFile,
        'dir_writable' => is_writable($cacheDir),
        'raw_reagent_for' => $data['reagent_for'] ?? null,
        'raw_dropped_by' => $data['dropped_by'] ?? null,
        'reagent_for_list' => $reagentForList,
        'dropped_by_list' => $droppedByList,
    ];
    jsonOut($response);
}

// ---------------------------------------------------------------------------
// Cache & respond
// ---------------------------------------------------------------------------
// Pre-warm icon.php's image cache with this item's icon
if (!empty($response['icon'])) {
    prewarmIconCache($id, $whEp, $response['icon']);
}

$json = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
$written = file_put_contents($cacheFile, $json);
if ($written === false)
    error_log("tooltip.php: failed to write cache: {$cacheFile}");

header('Content-Type: application/json; charset=utf-8');
header('X-Tooltip-Cache: MISS');
header('X-Cache-Written: ' . ($written !== false ? 'yes' : 'no'));
header('Cache-Control: public, max-age=' . TOOLTIP_CACHE_TTL);
echo $json;
exit;