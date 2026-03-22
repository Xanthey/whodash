<?php
// wps_images.php - Returns matching wallpaper images from /ux/wps/ for a given race/sex
// Called by sections_character.js to populate the paperdoll background
//
// Matching priority:
//   Tier 1 — file contains the requested race AND (requested sex OR no sex keyword in filename)
//   Tier 2 — generic files (no race keyword AND no sex keyword) — always mixed with tier 1
//   Tier 3 — sex-only files (has sex keyword, no race keyword) — FALLBACK only when tiers 1+2 empty
//
// A "female draenei" image will NEVER appear for a gnome character.
// A generic (no tags) image CAN appear for any character.
// A "female" (sex only, no race) image only appears if there are zero gnome images at all.

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$wpsDir = __DIR__ . '/ux/wps/';
$wpsUrl = '/ux/wps/';

$race = strtolower(trim($_GET['race'] ?? ''));
$sex = strtolower(trim($_GET['sex'] ?? '')); // 'male' or 'female'

if (!is_dir($wpsDir)) {
    echo json_encode(['images' => [], 'error' => 'Image directory not found']);
    exit;
}

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

// Normalise spaced race names: "night elf" <-> "nightelf"
$raceAlt = str_replace(' ', '', $race);

$tier1 = []; // race-matched (primary)
$tier2 = []; // generic — no race, no sex (always with tier1)
$tier3 = []; // sex-only, no race (fallback only)

foreach (scandir($wpsDir) as $file) {
    if ($file === '.' || $file === '..')
        continue;
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']))
        continue;

    $name = strtolower($file);

    // Race detection
    $hasRaceKeyword = false;
    foreach ($knownRaces as $r) {
        if (str_contains($name, $r)) {
            $hasRaceKeyword = true;
            break;
        }
    }

    // Sex detection — "female" must be checked before "male" since "female" contains "male"
    $hasFemale = str_contains($name, 'female');
    // Word-boundary male: present as standalone word, not just inside "female"
    $hasMale = (bool) preg_match('/\bmale\b/', $name);
    $hasSexKeyword = $hasFemale || $hasMale;

    // TIER 2: no race, no sex → generic, eligible for everyone
    if (!$hasRaceKeyword && !$hasSexKeyword) {
        $tier2[] = $wpsUrl . $file;
        continue;
    }

    // TIER 3: sex-only, no race → fallback
    if (!$hasRaceKeyword && $hasSexKeyword) {
        $sexOk = empty($sex)
            || ($sex === 'female' && $hasFemale)
            || ($sex === 'male' && $hasMale);
        if ($sexOk)
            $tier3[] = $wpsUrl . $file;
        continue;
    }

    // TIER 1: has a race keyword — check if it matches the requested race
    if (empty($race)) {
        $raceMatch = true;
    } else {
        $raceMatch = str_contains($name, $race) || str_contains($name, $raceAlt);
    }
    if (!$raceMatch)
        continue; // wrong race, skip entirely

    // Sex check for this race-matched file
    if (empty($sex) || !$hasSexKeyword) {
        $sexMatch = true; // no sex filter, or file has no sex tag → serves either sex
    } else {
        $sexMatch = ($sex === 'female' && $hasFemale)
            || ($sex === 'male' && $hasMale);
    }

    if ($sexMatch)
        $tier1[] = $wpsUrl . $file;
}

// Tier1 + Tier2 form the normal pool. Tier3 is only used if that pool is empty.
$pool = array_merge($tier1, $tier2);
if (empty($pool))
    $pool = $tier3;

echo json_encode(['images' => array_values($pool)]);