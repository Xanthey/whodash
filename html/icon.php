<?php
// icon.php — WhoDASH Icon Proxy
// Serves item/spell icons, caching locally in ux/cache/.
// Resolution order:
//   1. ?icon= param (raw WoW texture path from addon data) → zamimg CDN → serve
//   2. _iconname.txt in shared cache (written by tooltip.php or previous run) → local jpg → serve
//   3. tooltip JSON cache cross-read → local jpg → serve
//   4. Wowhead gatherer scrape → zamimg CDN → serve
//   5. Question mark fallback

ini_set('display_errors', '0');
error_reporting(E_ALL);

// ---------------------------------------------------------------------------
// Config
// ---------------------------------------------------------------------------
$cacheDir = __DIR__ . '/ux/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

define('TOOLTIP_CACHE_DIR', __DIR__ . '/ux/tooltips');

// ---------------------------------------------------------------------------
// Request params
// ---------------------------------------------------------------------------
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'item'; // item | spell
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$name = isset($_GET['name']) ? trim((string) $_GET['name']) : '';
$size = isset($_GET['size']) ? preg_replace('/[^a-z]/', '', $_GET['size']) : 'large';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function serveIcon(string $path): void
{
    // Clear any stray output, disable server-side compression, force binary response
    if (ob_get_level())
        ob_end_clean();
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($path));
    header('Content-Encoding: identity');
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}

function is_pure_int_string($s): bool
{
    return is_string($s) && $s !== '' && ctype_digit($s);
}

function httpGetBinary(string $url, int $timeout = 10): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: image/*,*/*;q=0.8'],
    ]);
    $bin = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300 && $bin && strlen($bin) > 0) ? $bin : null;
}

function curlGet(string $url, int $timeout = 15): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => ['Accept: text/html,application/xhtml+xml,*/*', 'Accept-Language: en-US,en;q=0.9'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ($code >= 200 && $code < 300 && is_string($body) && $body !== '') ? $body : null;
}

function curlGetJson(string $url, int $timeout = 10): ?array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json,text/json;q=0.9,*/*;q=0.8', 'Accept-Language: en-US,en;q=0.9'],
    ]);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300 && is_string($body) && $body !== '') {
        $j = json_decode($body, true);
        return is_array($j) ? $j : null;
    }
    return null;
}

/**
 * Try to serve an icon by name. Checks local cache first, then fetches from zamimg.
 * Writes the jpg and the _iconname.txt ONLY on successful image fetch.
 * Calls exit() on success. Returns false if image could not be fetched.
 */
function tryServeIcon(string $iconName, string $size, string $cacheDir, int $id, string $typePrefix): bool
{
    $iconFile = $cacheDir . '/' . $iconName . '_' . $size . '.jpg';
    $nameFile = $id > 0 ? ($cacheDir . '/' . $typePrefix . $id . '_iconname.txt') : null;

    // Local cache hit
    if (file_exists($iconFile) && filesize($iconFile) > 0) {
        if ($nameFile && !file_exists($nameFile)) {
            @file_put_contents($nameFile, $iconName);
        }
        header('X-Icon-Source: local-cache');
        header('X-Icon-Name: ' . $iconName);
        serveIcon($iconFile);
    }

    // zamimg CDN
    $data = httpGetBinary('https://wow.zamimg.com/images/wow/icons/' . $size . '/' . $iconName . '.jpg');
    if ($data !== null) {
        file_put_contents($iconFile, $data);
        if ($nameFile)
            @file_put_contents($nameFile, $iconName);
        header('X-Icon-Source: zamimg-fresh');
        header('X-Icon-Name: ' . $iconName);
        serveIcon($iconFile);
    }

    // Mirror fallback
    $data = httpGetBinary('https://wotlkdb.com/static/images/icons/' . $size . '/' . $iconName . '.jpg');
    if ($data !== null) {
        file_put_contents($iconFile, $data);
        if ($nameFile)
            @file_put_contents($nameFile, $iconName);
        serveIcon($iconFile);
    }

    return false;
}

// ---------------------------------------------------------------------------
// Wowhead scraping helpers
// ---------------------------------------------------------------------------

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
        if ($c === '{') {
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            if ($depth === 0)
                return substr($str, $start, $i - $start + 1);
        }
    }
    return null;
}

function extractIconFromGatherer(string $html, int $id, string $type): ?string
{
    $whType = ($type === 'item') ? 3 : 6;
    $blockPat = '#WH\.Gatherer\.addData\s*\(\s*' . $whType . '\s*,\s*\d+\s*,\s*\{#';
    if (!preg_match($blockPat, $html, $bm, PREG_OFFSET_CAPTURE))
        return null;
    $needle = '"' . $id . '":{';
    $pos = strpos($html, $needle, $bm[0][1]);
    if ($pos === false)
        return null;
    $json = extractBalancedJson($html, $pos + strlen((string) $id) + 3);
    if (!$json)
        return null;
    $entry = json_decode($json, true);
    return (is_array($entry) && !empty($entry['icon']) && !is_pure_int_string($entry['icon']))
        ? strtolower((string) $entry['icon']) : null;
}

function parseIconFromHtml(string $html): ?string
{
    if (preg_match('#"icon"\s*:\s*"([a-z0-9_]+)"#i', $html, $m))
        return strtolower($m[1]);
    if (preg_match('#/images/wow/icons/(?:small|medium|large)/([^.]+)\.jpg#i', $html, $m))
        return strtolower($m[1]);
    return null;
}

function getSpellIconFromTooltip(int $id): ?string
{
    foreach ([
        'https://www.wowhead.com/tooltip/spell/' . $id,
        'https://www.wowhead.com/tooltip/spell/' . $id . '?dataEnv=wotlk'
    ] as $url) {
        $j = curlGetJson($url);
        if (is_array($j) && !empty($j['icon']) && !is_pure_int_string($j['icon'])) {
            return strtolower((string) $j['icon']);
        }
    }
    return null;
}

function scrapeIconFromWowhead(int $id, string $type, string $name): ?string
{
    if ($type === 'spell') {
        $url = $id > 0 ? "https://www.wowhead.com/wotlk/spell=$id"
            : ($name ? "https://www.wowhead.com/wotlk/search?q=" . urlencode($name) : null);
    } else {
        $url = $id > 0 ? "https://www.wowhead.com/wotlk/item=$id"
            : ($name ? "https://www.wowhead.com/wotlk/search?q=" . urlencode($name) : null);
    }
    if (!$url)
        return null;

    $html = curlGet($url);
    if (!$html)
        return null;

    if (stripos($url, '/search?') !== false) {
        $pat = ($type === 'spell') ? '#href="(/wotlk/spell=\d+[^"]*)"#i' : '#href="(/wotlk/item=\d+[^"]*)"#i';
        if (preg_match($pat, $html, $m)) {
            $html2 = curlGet('https://www.wowhead.com' . $m[1]);
            if ($html2)
                $html = $html2;
        }
    }

    if ($id > 0) {
        $icon = extractIconFromGatherer($html, $id, $type);
        if ($icon)
            return $icon;
    }
    return parseIconFromHtml($html);
}

// ---------------------------------------------------------------------------
// typePrefix needed by both probe and resolution
// ---------------------------------------------------------------------------
$typePrefix = ($type === 'spell') ? 'spell' : 'item';

// ---------------------------------------------------------------------------
// Probe mode — before resolution so it always fires
// Usage: /icon.php?type=item&id=39816&size=medium&probe=1
// ---------------------------------------------------------------------------
if (isset($_GET['probe'])) {
    $nf = $id > 0 ? ($cacheDir . '/' . $typePrefix . $id . '_iconname.txt') : null;
    $tf = $id > 0 ? (TOOLTIP_CACHE_DIR . '/v5_' . $typePrefix . '_' . $id . '.json') : null;
    $iconTxt = ($nf && file_exists($nf)) ? trim(file_get_contents($nf)) : null;
    $iconTooltip = null;
    if ($tf && file_exists($tf)) {
        $tj = json_decode(file_get_contents($tf), true);
        $iconTooltip = $tj['icon'] ?? null;
    }
    $iconParam = isset($_GET['icon']) ? trim($_GET['icon']) : null;
    $iconParamClean = $iconParam
        ? strtolower(ltrim(preg_replace('#^Interface[/\\\\]+Icons[/\\\\]+#i', '', $iconParam), '/\\'))
        : null;

    $fileChecks = [];
    foreach (['small', 'medium', 'large'] as $sz) {
        foreach (array_unique(array_filter([$iconParamClean, $iconTxt, $iconTooltip])) as $c) {
            $p = $cacheDir . '/' . $c . '_' . $sz . '.jpg';
            $fileChecks[$c . '_' . $sz] = [
                'path' => $p,
                'exists' => file_exists($p),
                'bytes' => file_exists($p) ? filesize($p) : null,
            ];
        }
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'type' => $type,
        'id' => $id,
        'size' => $size,
        'icon_param_raw' => $iconParam,
        'icon_param_clean' => $iconParamClean,
        'iconNameFile' => $nf,
        'iconNameExists' => $nf ? file_exists($nf) : false,
        'iconFromTxt' => $iconTxt,
        'tooltipFile' => $tf,
        'tooltipExists' => $tf ? file_exists($tf) : false,
        'iconFromTooltip' => $iconTooltip,
        'fileChecks' => $fileChecks,
        'cacheWritable' => is_writable($cacheDir),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------
// Resolution
// ---------------------------------------------------------------------------

// STEP 1: ?icon= fast path (addon supplies raw WoW texture name)
// Handles all variants: "Interface\Icons\Inv_helmet_124", "\Icons\foo", "inv_helmet_124"
if (!empty($_GET['icon'])) {
    $rawIcon = trim($_GET['icon']);
    $rawIcon = preg_replace('#^Interface[/\\\\]+Icons[/\\\\]+#i', '', $rawIcon);
    $rawIcon = ltrim($rawIcon, '/\\');
    $rawIcon = strtolower($rawIcon);
    if ($rawIcon !== '' && preg_match('#^[a-z0-9_]+$#', $rawIcon)) {
        tryServeIcon($rawIcon, $size, $cacheDir, $id, $typePrefix);
        // Falls through only if zamimg doesn't have that name
    }
}

// STEP 2: Shared _iconname.txt (written by tooltip.php pre-warming or prior success)
$iconNameFile = $id > 0 ? ($cacheDir . '/' . $typePrefix . $id . '_iconname.txt') : null;
if ($iconNameFile && file_exists($iconNameFile)) {
    $cached = strtolower(trim((string) file_get_contents($iconNameFile)));
    if ($cached !== '' && !is_pure_int_string($cached)) {
        $ok = tryServeIcon($cached, $size, $cacheDir, $id, $typePrefix);
        if (!$ok)
            @unlink($iconNameFile); // name is bad — clear it
    } else {
        @unlink($iconNameFile);
    }
}

// STEP 3: tooltip.php JSON cache cross-read
if ($id > 0) {
    $tooltipFile = TOOLTIP_CACHE_DIR . '/v5_' . $typePrefix . '_' . $id . '.json';
    if (file_exists($tooltipFile)) {
        $tj = json_decode((string) file_get_contents($tooltipFile), true);
        if (is_array($tj) && !empty($tj['icon']) && !is_pure_int_string($tj['icon'])) {
            tryServeIcon(strtolower((string) $tj['icon']), $size, $cacheDir, $id, $typePrefix);
        }
    }
}

// STEP 4: Spell tooltip JSON endpoint
if ($type === 'spell' && $id > 0) {
    $spellIcon = getSpellIconFromTooltip($id);
    if ($spellIcon)
        tryServeIcon($spellIcon, $size, $cacheDir, $id, $typePrefix);
}

// STEP 5: Full Wowhead page scrape
if ($id > 0 || $name !== '') {
    $scraped = scrapeIconFromWowhead($id, $type, $name);
    if ($scraped && !is_pure_int_string($scraped)) {
        tryServeIcon($scraped, $size, $cacheDir, $id, $typePrefix);
    }
}

// STEP 6: ?name= looks like a bare icon name
if ($name !== '' && preg_match('#^[a-z0-9_]+$#i', $name)) {
    tryServeIcon(strtolower($name), $size, $cacheDir, $id, $typePrefix);
}

// ---------------------------------------------------------------------------
// Fallback: question mark
// ---------------------------------------------------------------------------
$qmFile = $cacheDir . '/inv_misc_questionmark_' . $size . '.jpg';
if (!file_exists($qmFile)) {
    $qmData = httpGetBinary("https://wow.zamimg.com/images/wow/icons/{$size}/inv_misc_questionmark.jpg");
    if ($qmData !== null)
        file_put_contents($qmFile, $qmData);
}
if (file_exists($qmFile)) {
    if (ob_get_level())
        ob_end_clean();
    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($qmFile));
    readfile($qmFile);
    exit;
}

http_response_code(404);
echo 'Icon not found.';
exit;