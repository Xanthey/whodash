<?php
// sections/character-data.php - Character stats and progression data (CORRECTED)
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$character_id = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

if (!$character_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No character_id provided']);
    exit;
}

try {
    // Verify ownership OR public access
    $character = null;

    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare('SELECT id, realm, name, faction, class_file, class_local, race, sex, guild_name, visibility FROM characters WHERE id = ? AND user_id = ?');
        $stmt->execute([$character_id, $_SESSION['user_id']]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$character) {
        $stmt = $pdo->prepare('SELECT id, realm, name, faction, class_file, class_local, race, sex, guild_name, visibility FROM characters WHERE id = ? AND visibility = "PUBLIC"');
        $stmt->execute([$character_id]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not accessible']);
        exit;
    }

    $data = [
        'identity' => [],
        'overview' => ['stats' => [], 'timeseries' => []],
        'gear' => [],
        'stats' => [],
        'currencies' => []
    ];

    // ─── IDENTITY ─────────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('SELECT value FROM series_level WHERE character_id = ? ORDER BY ts DESC LIMIT 1');
        $stmt->execute([$character_id]);
        $lvlRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentLevel = $lvlRow ? (int) $lvlRow['value'] : 1;
    } catch (Throwable $e) {
        $currentLevel = 1;
    }

    $spec = null;
    try {
        $stmt = $pdo->prepare('SELECT tt.name FROM talents_groups tg JOIN talents_tabs tt ON tt.talents_group_id = tg.id WHERE tg.character_id = ? ORDER BY tg.ts DESC, tt.points_spent DESC LIMIT 1');
        $stmt->execute([$character_id]);
        $specRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $spec = $specRow ? $specRow['name'] : null;
    } catch (Throwable $e) {
    }

    $sexMap = [0 => 'Unknown', 1 => 'Unknown', 2 => 'Male', 3 => 'Female'];
    $data['identity'] = [
        'name' => $character['name'],
        'level' => $currentLevel,
        'class' => $character['class_local'] ?? '',
        'race' => $character['race'] ?? '',
        'sex' => $sexMap[(int) ($character['sex'] ?? 0)] ?? 'Unknown',
        'spec' => $spec,
        'guild' => $character['guild_name'] ?? null,
        'faction' => $character['faction'] ?? '',
        'realm' => $character['realm'] ?? '',
    ];

    // ─── OVERVIEW - Item Level ────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare("SELECT ts, AVG(ilvl) as avg_ilvl FROM equipment_snapshot WHERE character_id = ? AND ilvl > 0 GROUP BY ts ORDER BY ts ASC");
        $stmt->execute([$character_id]);
        $ilvlData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($ilvlData)) {
            $currentIlvl = end($ilvlData)['avg_ilvl'];
            $firstIlvl = reset($ilvlData)['avg_ilvl'];
            $data['overview']['stats'][] = ['label' => 'Item Level', 'current' => (int) round($currentIlvl), 'change' => (int) round($currentIlvl - $firstIlvl), 'icon' => '⚔️'];
            $data['overview']['timeseries']['ilvl'] = array_map(function ($row) {
                return ['ts' => (int) $row['ts'], 'value' => (int) round($row['avg_ilvl'])];
            }, $ilvlData);
        }
    } catch (Throwable $e) {
    }

    // ─── OVERVIEW - Health ────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare("SELECT ts, hp as value FROM series_resource_max WHERE character_id = ? AND hp > 0 ORDER BY ts ASC");
        $stmt->execute([$character_id]);
        $healthData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($healthData)) {
            $currentHealth = end($healthData)['value'];
            $firstHealth = reset($healthData)['value'];
            $data['overview']['stats'][] = ['label' => 'Max Health', 'current' => (int) $currentHealth, 'change' => (int) ($currentHealth - $firstHealth), 'icon' => '❤️'];
            $data['overview']['timeseries']['health'] = array_map(function ($row) {
                return ['ts' => (int) $row['ts'], 'value' => (int) $row['value']];
            }, $healthData);
        }
    } catch (Throwable $e) {
    }

    // ─── OVERVIEW - Mana ──────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare("SELECT ts, mp as value FROM series_resource_max WHERE character_id = ? AND mp > 0 ORDER BY ts ASC");
        $stmt->execute([$character_id]);
        $manaData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($manaData)) {
            $currentMana = end($manaData)['value'];
            $firstMana = reset($manaData)['value'];
            $data['overview']['stats'][] = ['label' => 'Max Mana', 'current' => (int) $currentMana, 'change' => (int) ($currentMana - $firstMana), 'icon' => '💙'];
            $data['overview']['timeseries']['mana'] = array_map(function ($row) {
                return ['ts' => (int) $row['ts'], 'value' => (int) $row['value']];
            }, $manaData);
        }
    } catch (Throwable $e) {
    }

    // ─── OVERVIEW - Armor ─────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('SELECT ts, armor as value FROM series_base_stats WHERE character_id = ? AND armor > 0 ORDER BY ts ASC');
        $stmt->execute([$character_id]);
        $armorData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($armorData)) {
            $currentArmor = end($armorData)['value'];
            $firstArmor = reset($armorData)['value'];
            $data['overview']['stats'][] = ['label' => 'Armor', 'current' => (int) $currentArmor, 'change' => (int) ($currentArmor - $firstArmor), 'icon' => '🛡️'];
            $data['overview']['timeseries']['armor'] = array_map(function ($row) {
                return ['ts' => (int) $row['ts'], 'value' => (int) $row['value']];
            }, $armorData);
        }
    } catch (Throwable $e) {
    }

    // ─── OVERVIEW - Attack Power ──────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare("SELECT ts, (COALESCE(ap_base, 0) + COALESCE(ap_pos, 0) + COALESCE(ap_neg, 0)) as value FROM series_attack WHERE character_id = ? ORDER BY ts ASC");
        $stmt->execute([$character_id]);
        $attackData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $attackData = array_filter($attackData, function ($r) {
            return $r['value'] > 0;
        });

        if (!empty($attackData)) {
            $currentAttack = end($attackData)['value'];
            $firstAttack = reset($attackData)['value'];
            $data['overview']['stats'][] = ['label' => 'Attack Power', 'current' => (int) $currentAttack, 'change' => (int) ($currentAttack - $firstAttack), 'icon' => '⚡'];
            $data['overview']['timeseries']['attack'] = array_map(function ($row) {
                return ['ts' => (int) $row['ts'], 'value' => (int) $row['value']];
            }, array_values($attackData));
        }
    } catch (Throwable $e) {
    }

    // ─── OVERVIEW - Spell Power ───────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare("SELECT ts, GREATEST(COALESCE(school_arcane_dmg, 0), COALESCE(school_fire_dmg, 0), COALESCE(school_frost_dmg, 0), COALESCE(school_holy_dmg, 0), COALESCE(school_nature_dmg, 0), COALESCE(school_shadow_dmg, 0), COALESCE(heal_bonus, 0)) as value FROM series_spell_ranged WHERE character_id = ? ORDER BY ts ASC");
        $stmt->execute([$character_id]);
        $spellData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $spellData = array_filter($spellData, function ($r) {
            return $r['value'] > 0;
        });

        if (!empty($spellData)) {
            $currentSpell = end($spellData)['value'];
            $firstSpell = reset($spellData)['value'];
            $data['overview']['stats'][] = ['label' => 'Spell Power', 'current' => (int) $currentSpell, 'change' => (int) ($currentSpell - $firstSpell), 'icon' => '✨'];
            $data['overview']['timeseries']['spell'] = array_map(function ($row) {
                return ['ts' => (int) $row['ts'], 'value' => (int) $row['value']];
            }, array_values($spellData));
        }
    } catch (Throwable $e) {
    }

    // ─── GEAR TAB ─────────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare("SELECT slot_name as slot, item_id, name as item_name, quality, ilvl as item_level, link, icon, ts FROM equipment_snapshot WHERE character_id = ? ORDER BY CASE slot_name WHEN 'head' THEN 1 WHEN 'neck' THEN 2 WHEN 'shoulder' THEN 3 WHEN 'back' THEN 4 WHEN 'chest' THEN 5 WHEN 'wrist' THEN 6 WHEN 'hands' THEN 7 WHEN 'waist' THEN 8 WHEN 'legs' THEN 9 WHEN 'feet' THEN 10 WHEN 'finger1' THEN 11 WHEN 'finger2' THEN 12 WHEN 'trinket1' THEN 13 WHEN 'trinket2' THEN 14 WHEN 'mainhand' THEN 15 WHEN 'offhand' THEN 16 WHEN 'ranged' THEN 17 ELSE 99 END");
        $stmt->execute([$character_id]);
        $gearRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $qualityMap = [0 => 'Poor', 1 => 'Common', 2 => 'Uncommon', 3 => 'Rare', 4 => 'Epic', 5 => 'Legendary', 6 => 'Artifact', 7 => 'Heirloom'];
        $data['gear'] = array_map(function ($item) use ($qualityMap) {
            $item['quality'] = $qualityMap[$item['quality']] ?? 'Common';
            return $item;
        }, $gearRaw);
    } catch (Throwable $e) {
    }

    // ─── STATS TAB ────────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('SELECT strength, agility, stamina, intellect, spirit FROM series_base_stats WHERE character_id = ? ORDER BY ts DESC LIMIT 1');
        $stmt->execute([$character_id]);
        $baseStats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($baseStats) {
            $statMapping = ['strength' => ['label' => 'Strength', 'icon' => '💪'], 'agility' => ['label' => 'Agility', 'icon' => '🏃'], 'stamina' => ['label' => 'Stamina', 'icon' => '🏋️'], 'intellect' => ['label' => 'Intellect', 'icon' => '🧠'], 'spirit' => ['label' => 'Spirit', 'icon' => '✨']];
            foreach ($statMapping as $key => $info) {
                if (isset($baseStats[$key]) && $baseStats[$key] > 0) {
                    $data['stats'][] = ['label' => $info['label'], 'value' => (int) $baseStats[$key], 'icon' => $info['icon']];
                }
            }
        }
    } catch (Throwable $e) {
    }

    try {
        $stmt = $pdo->prepare('SELECT crit, dodge, parry, block FROM series_attack WHERE character_id = ? ORDER BY ts DESC LIMIT 1');
        $stmt->execute([$character_id]);
        $combatStats = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($combatStats) {
            $combatMapping = ['crit' => ['label' => 'Crit %', 'icon' => '🎯'], 'dodge' => ['label' => 'Dodge %', 'icon' => '🤸'], 'parry' => ['label' => 'Parry %', 'icon' => '🛡️'], 'block' => ['label' => 'Block %', 'icon' => '🚧']];
            foreach ($combatMapping as $key => $info) {
                if (isset($combatStats[$key]) && $combatStats[$key] > 0) {
                    $data['stats'][] = ['label' => $info['label'], 'value' => (float) $combatStats[$key], 'icon' => $info['icon']];
                }
            }
        }
    } catch (Throwable $e) {
    }

    // ─── CURRENCIES TAB ───────────────────────────────────────────────────────
    try {
        // Get all currency time-series data for this character
        $stmt = $pdo->prepare('
            SELECT currency_name, ts, count
            FROM series_currency 
            WHERE character_id = ?
            ORDER BY currency_name, ts ASC
        ');
        $stmt->execute([$character_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['currencies'] = [];

        // Currency metadata with descriptions and categories
        $currencyMeta = [
            'Stone Keeper\'s Shard' => [
                'category' => 'Player vs. Player',
                'description' => 'Earned by winning Wintergrasp battles',
            ],
            'Emblem of Triumph' => [
                'category' => 'Dungeon and Raid',
                'description' => 'Earned from Trial of the Crusader raids',
            ],
            'Emblem of Conquest' => [
                'category' => 'Dungeon and Raid',
                'description' => 'Earned from Ulduar raids and heroic dungeons',
            ],
            'Emblem of Valor' => [
                'category' => 'Dungeon and Raid',
                'description' => 'Earned from heroic dungeons',
            ],
            'Emblem of Heroism' => [
                'category' => 'Dungeon and Raid',
                'description' => 'Earned from Naxxramas raids and heroic dungeons',
            ],
            'Champion\'s Seal' => [
                'category' => 'Player vs. Player',
                'description' => 'Earned from Argent Tournament dailies',
            ],
            'Wintergrasp Mark of Honor' => [
                'category' => 'Player vs. Player',
                'description' => 'Earned by participating in Wintergrasp',
            ],
            'Honor Points' => [
                'category' => 'Player vs. Player',
                'description' => 'Earned by killing enemy players',
            ],
            'Arena Points' => [
                'category' => 'Player vs. Player',
                'description' => 'Earned from arena matches',
            ],
            'Badge of Justice' => [
                'category' => 'Dungeon and Raid',
                'description' => 'Earned from Burning Crusade raids and heroics',
            ],
        ];

        // Group rows by currency name
        $currencyData = [];
        foreach ($rows as $row) {
            $name = $row['currency_name'];
            if (!isset($currencyData[$name])) {
                $currencyData[$name] = [];
            }
            $currencyData[$name][] = [
                'ts' => (int) $row['ts'],
                'count' => (int) $row['count'],
            ];
        }

        // WoW category labels that get stored as currency names — skip them
        $currencyNameBlocklist = [
            'Dungeon and Raid',
            'Player vs. Player',
            'Miscellaneous',
            'Arena',
            'Seasonal',
        ];

        // Build currency objects with metadata
        foreach ($currencyData as $currencyName => $timeSeries) {
            if (empty($timeSeries))
                continue;

            if (in_array($currencyName, $currencyNameBlocklist, true))
                continue;

            // Get current value (last entry)
            $current = end($timeSeries)['count'] ?? 0;

            // Get metadata or use defaults
            $meta = $currencyMeta[$currencyName] ?? [
                'category' => 'Other',
                'description' => 'In-game currency',
            ];

            $data['currencies'][] = [
                'name' => $currencyName,
                'count' => $current,
                'timeseries' => array_values($timeSeries),
                'category' => $meta['category'],
                'description' => $meta['description'],
            ];
        }

        // Sort by category then by count (descending)
        usort($data['currencies'], function ($a, $b) {
            if ($a['category'] !== $b['category']) {
                return strcmp($a['category'], $b['category']);
            }
            return $b['count'] - $a['count'];
        });
    } catch (Throwable $e) {
        error_log('Currency data error: ' . $e->getMessage());
    }

    echo json_encode($data, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log('Fatal error in character-data.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}
?>