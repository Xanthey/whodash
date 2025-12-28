<?php
// icon.php  (items + spells)
// Caches in ux/cache. Supports Wowhead spell icons with tooltip and HTML fallbacks.

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// --- Config / cache dir
$cacheDir = __DIR__ . '/ux/cache';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// --- Request params
$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'item'; // 'item' | 'spell'
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$name = isset($_GET['name']) ? trim((string) $_GET['name']) : '';
$size = isset($_GET['size']) ? preg_replace('/[^a-z]/', '', $_GET['size']) : 'large'; // small|medium|large

// --- Helpers
function serveIcon($path)
{
    header('Content-Type: image/jpeg');
    readfile($path);
    exit;
}

// Simple HTML GET (text)
function curlGet($url, $timeout = 10)
{
    $ch = curl_init($url);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'WhoDAT-IconProxy/1.3',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array('Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', 'Accept-Language: en-US,en;q=0.9'),
    );
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300 && is_string($body) && $body !== '') {
        return $body;
    }
    return null;
}

// Binary GET (images)
function httpGetBinary($url, $timeout = 10)
{
    $ch = curl_init($url);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'WhoDAT-IconProxy/1.3',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,   // set false only to debug broken CA
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array('Accept: image/*,*/*;q=0.8', 'Accept-Language: en-US,en;q=0.9'),
    );
    curl_setopt_array($ch, $opts);
    $bin = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300 && $bin !== false && strlen($bin) > 0) {
        return $bin;
    }
    return null;
}

// JSON GET
function curlGetJson($url, $timeout = 10)
{
    $ch = curl_init($url);
    $opts = array(
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT => 'WhoDAT-IconProxy/1.3',
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => array('Accept: application/json,text/json;q=0.9,*/*;q=0.8', 'Accept-Language: en-US,en;q=0.9'),
    );
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code >= 200 && $code < 300 && is_string($body) && $body !== '') {
        $j = json_decode($body, true);
        return is_array($j) ? $j : null;
    }
    return null;
}

// Utility: detect numeric string e.g. "136192"
function is_pure_int_string($s)
{
    return is_string($s) && $s !== '' && ctype_digit($s);
}

// Parse icon base (inv_*) from HTML
function parseIconFromHtml($html)
{
    // 1) JSON-ish: "icon":"inv_misc_bandage_18"
    if (preg_match('#"icon"\s*:\s*"([a-z0-9_]+)"#i', $html, $m1)) {
        return strtolower($m1[1]);
    }
    // 2) Any icon URL in the page
    if (preg_match('#/images/wow/icons/(?:small|medium|large)/([^.]+)\.jpg#i', $html, $m2)) {
        return strtolower($m2[1]);
    }
    // 3) data-icon="inv_misc_bandage_18"
    if (preg_match('#data-icon=[a-z0-9_]+["\']#i', $html, $m3)) {
        return strtolower($m3[1]);
    }
    return null;
}

// Resolve a Wowhead URL for item or spell
function resolveWowheadUrl($type, $id, $name)
{
    if ($type === 'spell') {
        if ($id > 0)
            return "https://www.wowhead.com/wotlk/spell=$id";
        if ($name !== '')
            return "https://www.wowhead.com/search?q=" . urlencode($name);
    } else { // item
        if ($id > 0)
            return "https://www.wowhead.com/item=$id";
        if ($name !== '')
            return "https://www.wowhead.com/search?q=" . urlencode($name);
    }
    return null;
}

// If a search page was fetched, follow the first concrete result
function followFirstResultIfSearch($html, $type)
{
    if (!$html)
        return null;
    if ($type === 'spell' && preg_match('#href="(/wotlk/spell=(\d+)[^"]*)"#i', $html, $m)) {
        return "https://www.wowhead.com" . $m[1];
    }
    if ($type !== 'spell' && preg_match('#href="(/item=(\d+)[^"]*)"#i', $html, $m2)) {
        return "https://www.wowhead.com" . $m2[1];
    }
    return null;
}

// Tooltip-based icon for spells (reject numeric icons)
function getSpellIconFromTooltip($id)
{
    $j = curlGetJson('https://www.wowhead.com/tooltip/spell/' . $id);
    if (is_array($j) && isset($j['icon'])) {
        $icon = (string) $j['icon'];
        if ($icon !== '' && !is_pure_int_string($icon))
            return strtolower($icon);
    }
    $j = curlGetJson('https://www.wowhead.com/tooltip/spell/' . $id . '?dataEnv=wotlk');
    if (is_array($j) && isset($j['icon'])) {
        $icon = (string) $j['icon'];
        if ($icon !== '' && !is_pure_int_string($icon))
            return strtolower($icon);
    }
    return null; // force HTML fallback
}

// Resolve icon base (inv_*) with caching
function getIconName($type, $id, $name, $cacheDir)
{
    $key = ($type === 'spell' ? 'spell' : 'item') . ($id > 0 ? strval($id) : '_' . md5($name));
    $iconNameCacheFile = $cacheDir . '/' . $key . '_iconname.txt';

    // 1) Cache read (ignore numeric/empty)
    if ($id > 0 && file_exists($iconNameCacheFile)) {
        $val = trim((string) file_get_contents($iconNameCacheFile));
        if ($val !== '' && !is_pure_int_string($val))
            return $val;
        @unlink($iconNameCacheFile); // clear bad cache
    }

    // 2) Spells by id: tooltip first
    if ($type === 'spell' && $id > 0) {
        $icon = getSpellIconFromTooltip($id);
        if ($icon && !is_pure_int_string($icon)) {
            file_put_contents($iconNameCacheFile, $icon);
            return $icon;
        }
    }

    // 3) Fallback: fetch page and parse HTML
    $url = resolveWowheadUrl($type, $id, $name);
    if (!$url)
        return null;

    $html = curlGet($url);
    if (!$html)
        return null;

    if (stripos($url, '/search?') !== false) {
        $resultUrl = followFirstResultIfSearch($html, $type);
        if ($resultUrl) {
            $html2 = curlGet($resultUrl);
            if ($html2)
                $html = $html2;
        }
    }

    $icon = parseIconFromHtml($html); // e.g., inv_misc_bandage_18
    if ($icon && !is_pure_int_string($icon) && $id > 0) {
        file_put_contents($iconNameCacheFile, $icon);
    }
    return $icon;
}

// ---------- Main ----------
$icon = getIconName($type, $id, $name, $cacheDir);

// Numeric→base safety net (optional small map)
$numericToIcon = array(
    '136192' => 'inv_misc_bandage_18', // Heavy Linen Bandage
);
if (isset($icon) && is_pure_int_string($icon) && isset($numericToIcon[$icon])) {
    $icon = $numericToIcon[$icon];
}

// Derived paths/URLs
$localQuestionMark = $cacheDir . '/inv_misc_questionmark_' . $size . '.jpg';
$cdn = $icon ? ("https://wow.zamimg.com/images/wow/icons/{$size}/{$icon}.jpg") : null;
$mirror = $icon ? ("https://wotlkdb.com/static/images/icons/{$size}/{$icon}.jpg") : null;

// --- Debug probe FIRST ---
$debug = (isset($_GET['probe']) && $_GET['probe'] === '1');
if ($debug) {
    $probe = array(
        'type' => $type,
        'id' => $id,
        'name' => $name,
        'size' => $size,
        'iconBase' => $icon ?: null,
        'cdn' => $cdn,
        'mirror' => $mirror,
        'cacheDir' => $cacheDir,
        'allow_url_fopen' => ini_get('allow_url_fopen'),
    );
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($probe, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// If we have a resolved icon base, try to serve or fetch it
if ($icon) {
    $iconFile = $cacheDir . '/' . $icon . '_' . $size . '.jpg';

    // 1) serve from local cache
    if (file_exists($iconFile)) {
        serveIcon($iconFile);
    }

    // 2) ZAM CDN
    if ($cdn) {
        $imageData = httpGetBinary($cdn);
        if ($imageData !== null) {
            file_put_contents($iconFile, $imageData);
            serveIcon($iconFile);
        }
    }

    // 3) Mirror
    if ($mirror) {
        $imageData = httpGetBinary($mirror);
        if ($imageData !== null) {
            file_put_contents($iconFile, $imageData);
            serveIcon($iconFile);
        }
    }
}

// 4) Final fallback: ensure local question mark exists, then serve
if (!file_exists($localQuestionMark)) {
    $qmUrl = "https://wow.zamimg.com/images/wow/icons/{$size}/inv_misc_questionmark.jpg";
    $qmData = httpGetBinary($qmUrl);
    if ($qmData !== null) {
        file_put_contents($localQuestionMark, $qmData);
    }
}
if (file_exists($localQuestionMark)) {
    header('Content-Type: image/jpeg');
    readfile($localQuestionMark);
    exit;
}

// Nothing worked
http_response_code(404);
echo "Icon not found.";
exit;