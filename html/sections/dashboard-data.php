<?php
// sections/dashboard-data.php - REDESIGNED WIDGET DASHBOARD
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

    // ─── helpers ─────────────────────────────────────────────────────────────

    $data = [
        'identity' => [],
        'achievements' => ['total' => 0, 'points' => 0, 'last' => null],
        'currency' => ['net_30' => 0, 'current' => 0, 'history' => []],
        'auctions' => ['active' => 0, 'expired' => 0],
        'bags' => ['used' => 0, 'total' => 0, 'bank_used' => 0, 'bank_total' => 0],
        'mail' => ['unread' => 0],
        'grudge' => ['total' => 0, 'last_killer' => null],
        'professions' => [],
        'reputation' => [],
        'role' => ['primary' => null, 'stats' => []],
        'playtime' => 0,
        'tips' => [],
        'zone' => ['zone' => null, 'subzone' => null],
        'sharing' => ['shared_character' => false, 'shared_bank_alt' => false],
    ];

    // ─── IDENTITY ─────────────────────────────────────────────────────────────
    // Level from series_level
    try {
        $stmt = $pdo->prepare('SELECT value FROM series_level WHERE character_id = ? ORDER BY ts DESC LIMIT 1');
        $stmt->execute([$character_id]);
        $lvlRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentLevel = $lvlRow ? (int) $lvlRow['value'] : 1;
    } catch (Throwable $e) {
        $currentLevel = 1;
    }

    // Spec from talents
    $spec = null;
    try {
        $stmt = $pdo->prepare('
            SELECT tt.name, tt.points_spent
            FROM talents_groups tg
            JOIN talents_tabs tt ON tt.talents_group_id = tg.id
            WHERE tg.character_id = ?
            ORDER BY tg.ts DESC, tt.points_spent DESC
            LIMIT 1
        ');
        $stmt->execute([$character_id]);
        $specRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $spec = $specRow ? $specRow['name'] : null;
    } catch (Throwable $e) { /* no talents */
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

    // ─── ACHIEVEMENTS ─────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as total, COALESCE(SUM(points), 0) as points
            FROM series_achievements
            WHERE character_id = ? AND earned = 1
        ');
        $stmt->execute([$character_id]);
        $ach = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ach) {
            $data['achievements']['total'] = (int) $ach['total'];
            $data['achievements']['points'] = (int) $ach['points'];
        }

        // Last earned achievement — uses earned_date (Unix timestamp) as stored
        $stmt = $pdo->prepare('
            SELECT name, points, earned_date
            FROM series_achievements
            WHERE character_id = ? AND earned = 1 AND earned_date IS NOT NULL
            ORDER BY earned_date DESC
            LIMIT 1
        ');
        $stmt->execute([$character_id]);
        $lastAch = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($lastAch) {
            $data['achievements']['last'] = [
                'name' => $lastAch['name'],
                'points' => (int) ($lastAch['points'] ?? 0),
                'earned_ts' => (int) ($lastAch['earned_date'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        error_log('achievements: ' . $e->getMessage());
    }

    // ─── CURRENCY / GOLD ──────────────────────────────────────────────────────
    try {
        // Current gold
        foreach (['series_money', 'series_gold'] as $tbl) {
            try {
                $stmt = $pdo->prepare("SELECT value FROM {$tbl} WHERE character_id = ? ORDER BY ts DESC LIMIT 1");
                $stmt->execute([$character_id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row !== false) {
                    $data['currency']['current'] = (int) $row['value'];
                    break;
                }
            } catch (Throwable $e) { /* try next */
            }
        }

        // 30-day net change + sparkline history
        $now30 = time() - 30 * 86400;
        foreach (['series_money', 'series_gold'] as $tbl) {
            try {
                // Net change: earliest value in window
                $stmt = $pdo->prepare("SELECT value, ts FROM {$tbl} WHERE character_id = ? AND ts >= ? ORDER BY ts ASC LIMIT 1");
                $stmt->execute([$character_id, $now30]);
                $first = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($first !== false) {
                    $data['currency']['net_30'] = $data['currency']['current'] - (int) $first['value'];
                }

                // Sparkline: one data point per day (last reading of the day), non-zero only
                $stmt = $pdo->prepare("
                    SELECT DATE(FROM_UNIXTIME(ts)) as day, value
                    FROM {$tbl}
                    WHERE character_id = ? AND ts >= ? AND value > 0
                    ORDER BY ts ASC
                ");
                $stmt->execute([$character_id, $now30]);
                $histRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Keep last reading per calendar day
                $byDay = [];
                foreach ($histRows as $r) {
                    $byDay[$r['day']] = (int) $r['value'];
                }
                if (!empty($byDay)) {
                    $data['currency']['history'] = array_values($byDay);
                    break;
                }
                break;
            } catch (Throwable $e) { /* try next */
            }
        }
    } catch (Throwable $e) {
        error_log('currency: ' . $e->getMessage());
    }

    // ─── AUCTIONS ─────────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('
            SELECT
                SUM(CASE WHEN sold = 0 AND expired = 0 THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN expired = 1 THEN 1 ELSE 0 END) as expired
            FROM auction_owner_rows
            WHERE character_id = ?
        ');
        $stmt->execute([$character_id]);
        $auc = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($auc) {
            $data['auctions']['active'] = (int) ($auc['active'] ?? 0);
            $data['auctions']['expired'] = (int) ($auc['expired'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('auctions: ' . $e->getMessage());
    }

    // ─── BAGS ─────────────────────────────────────────────────────────────────
    try {
        // Step 1: Count used slots per bag_id from containers_bag
        $stmt = $pdo->prepare('
            SELECT
                bag_id,
                COUNT(*) as used_slots,
                MAX(slot) as max_slot
            FROM containers_bag
            WHERE character_id = ?
            GROUP BY bag_id
        ');
        $stmt->execute([$character_id]);
        $bagRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Index by bag_id for quick lookup
        $bagDataById = [];
        foreach ($bagRows as $b) {
            $bagDataById[(int) $b['bag_id']] = $b;
        }

        $totalUsed = 0;
        $totalSlots = 0;

        // Step 2: Backpack (bag_id = 0) always exists.
        // Its capacity = MAX(slot)+1 from containers_bag, or 16 if empty.
        $backpack = $bagDataById[0] ?? null;
        if ($backpack) {
            $totalUsed += (int) $backpack['used_slots'];
            $totalSlots += (int) $backpack['max_slot'] + 1;
        } else {
            // Backpack exists but is empty — default WoW backpack is 16 slots
            $totalSlots += 16;
        }

        // Step 3: For equipped bag slots (Bag1Slot–Bag4Slot in equipment_snapshot),
        // determine capacity from containers_bag MAX(slot)+1 for that bag_id.
        // This correctly handles empty bags (no rows → 0 used, but capacity known
        // from the equipped bag's own slot count if it ever had items, else we skip).
        try {
            $eqStmt = $pdo->prepare("
                SELECT slot_name
                FROM equipment_snapshot
                WHERE character_id = ? AND slot_name LIKE 'Bag%Slot'
                ORDER BY slot_name
            ");
            $eqStmt->execute([$character_id]);
            $equippedSlots = $eqStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($equippedSlots as $slotName) {
                if (!preg_match('/Bag(\d+)Slot/', $slotName, $m))
                    continue;
                $bagNum = (int) $m[1];
                if ($bagNum === 0)
                    continue; // backpack already handled above

                if (isset($bagDataById[$bagNum])) {
                    // Bag has items — derive capacity and used count from data
                    $totalUsed += (int) $bagDataById[$bagNum]['used_slots'];
                    $totalSlots += (int) $bagDataById[$bagNum]['max_slot'] + 1;
                } else {
                    // Bag is equipped but completely empty — we know it exists
                    // but cannot determine its size without a slot record.
                    // We include it as 0 used / unknown size, so we skip adding
                    // to total (avoids inflating capacity with a guess).
                }
            }
        } catch (Throwable $e) { /* equipment snapshot optional */
        }

        $data['bags']['used'] = $totalUsed;
        $data['bags']['total'] = $totalSlots;

    } catch (Throwable $e) {
        error_log('bags: ' . $e->getMessage());
    }

    try {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as used
            FROM containers_bank
            WHERE character_id = ?
        ');
        $stmt->execute([$character_id]);
        $bankRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($bankRow) {
            $data['bags']['bank_used'] = (int) ($bankRow['used'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('bank: ' . $e->getMessage());
    }

    // ─── MAIL ─────────────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) as unread FROM mailbox WHERE character_id = ? AND was_read = 0');
        $stmt->execute([$character_id]);
        $mailRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($mailRow) {
            $data['mail']['unread'] = (int) ($mailRow['unread'] ?? 0);
        }
    } catch (Throwable $e) {
        error_log('mail: ' . $e->getMessage());
    }

    // ─── GRUDGE LIST (curated nemesis list) ──────────────────────────────────
    try {
        // The Grudge is a user-curated list stored in character_grudge_list,
        // not all player killers from the deaths table.
        $stmt = $pdo->prepare("
            SELECT gl.player_name, gl.added_at,
                   COUNT(d.id) as kills, MAX(d.ts) as last_kill_ts
            FROM character_grudge_list gl
            LEFT JOIN deaths d
                ON d.character_id = gl.character_id
               AND d.killer_name  = gl.player_name
               AND d.killer_type  = 'player'
            WHERE gl.character_id = ?
            GROUP BY gl.player_name, gl.added_at
            ORDER BY gl.added_at DESC
        ");
        $stmt->execute([$character_id]);
        $grudgeRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $data['grudge']['total'] = count($grudgeRows);
        if (!empty($grudgeRows)) {
            $top = $grudgeRows[0];
            $data['grudge']['last_killer'] = [
                'name' => $top['player_name'],
                'kills' => (int) ($top['kills'] ?? 0),
                'last_ts' => (int) ($top['last_kill_ts'] ?? $top['added_at'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        error_log('grudge: ' . $e->getMessage());
    }

    // ─── PROFESSIONS ──────────────────────────────────────────────────────────
    try {
        $primaryProfessions = [
            'Alchemy',
            'Blacksmithing',
            'Enchanting',
            'Engineering',
            'Herbalism',
            'Inscription',
            'Jewelcrafting',
            'Leatherworking',
            'Mining',
            'Skinning',
            'Tailoring',
            'Mining',
            'Fishing',
            'Cooking',
            'First Aid'
        ];

        $stmt = $pdo->prepare('
            SELECT name, `rank`, max_rank
            FROM skills
            WHERE character_id = ? AND name IN (' . implode(',', array_fill(0, count($primaryProfessions), '?')) . ')
            ORDER BY `rank` DESC
            LIMIT 10
        ');
        $stmt->execute(array_merge([$character_id], $primaryProfessions));
        $profs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // WotLK skill tier brackets — derive the correct max from current rank
        // when the DB stores 0 or a stale value.
        // Tiers: Apprentice 1-75 | Journeyman 76-150 | Expert 151-225 |
        //        Artisan 226-300 | Master 301-375 | Grand Master 376-450
        $wrathMaxFromRank = function (int $rank): int {
            if ($rank <= 75)
                return 75;
            if ($rank <= 150)
                return 150;
            if ($rank <= 225)
                return 225;
            if ($rank <= 300)
                return 300;
            if ($rank <= 375)
                return 375;
            return 450;
        };

        $data['professions'] = array_map(function ($p) use ($wrathMaxFromRank) {
            $rank = (int) $p['rank'];
            $dbMax = (int) $p['max_rank'];
            // Use DB value only if valid (> 0 and >= current rank); else use WotLK bracket
            $maxRank = ($dbMax > 0 && $dbMax >= $rank) ? $dbMax : $wrathMaxFromRank($rank);
            return [
                'name' => $p['name'],
                'rank' => $rank,
                'max_rank' => $maxRank,
            ];
        }, $profs);
    } catch (Throwable $e) {
        error_log('professions: ' . $e->getMessage());
    }

    // ─── REPUTATION (2 closest to Exalted, not yet Exalted) ──────────────────
    try {
        // Get latest standing per faction (series_reputation uses min/max columns)
        $stmt = $pdo->prepare('
            SELECT faction_name, standing_id, value, min, max
            FROM series_reputation
            WHERE character_id = ?
            ORDER BY ts DESC
        ');
        $stmt->execute([$character_id]);
        $repRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $factions = [];
        foreach ($repRows as $row) {
            $name = $row['faction_name'];
            if (!isset($factions[$name])) {
                $factions[$name] = $row;
            }
        }

        // standing_id 8 = Exalted; sort by standing DESC, then progress DESC
        $nonExalted = array_filter($factions, fn($f) => (int) $f['standing_id'] < 8);
        usort($nonExalted, function ($a, $b) {
            $sidDiff = (int) $b['standing_id'] - (int) $a['standing_id'];
            if ($sidDiff !== 0)
                return $sidDiff;
            // Within same standing, rank by progress (value - min) / (max - min)
            $aRange = max(1, (int) $a['max'] - (int) $a['min']);
            $bRange = max(1, (int) $b['max'] - (int) $b['min']);
            $aPct = ((int) $a['value'] - (int) $a['min']) / $aRange;
            $bPct = ((int) $b['value'] - (int) $b['min']) / $bRange;
            return $bPct <=> $aPct;
        });

        $standingNames = [1 => 'Hated', 2 => 'Hostile', 3 => 'Unfriendly', 4 => 'Neutral', 5 => 'Friendly', 6 => 'Honored', 7 => 'Revered', 8 => 'Exalted'];

        $topTwo = array_slice(array_values($nonExalted), 0, 2);
        $data['reputation'] = array_map(function ($f) use ($standingNames) {
            $sid = (int) $f['standing_id'];
            $val = (int) ($f['value'] ?? 0);
            $minV = (int) ($f['min'] ?? 0);
            $maxV = (int) ($f['max'] ?? 0);
            // Progress within the current standing tier
            $range = max(1, $maxV - $minV);
            $progress = $val - $minV;
            return [
                'name' => $f['faction_name'],
                'standing_id' => $sid,
                'standing_name' => $standingNames[$sid] ?? 'Unknown',
                'value' => $progress,
                'max_value' => $range,
            ];
        }, $topTwo);
    } catch (Throwable $e) {
        error_log('reputation: ' . $e->getMessage());
    }

    // ─── ROLE STATS ───────────────────────────────────────────────────────────
    try {
        // Determine primary role from combat encounter times
        $stmt = $pdo->prepare('
            SELECT
                SUM(CASE WHEN dps > 0 THEN duration ELSE 0 END) as dmg_time,
                SUM(CASE WHEN dtps > 0 THEN duration ELSE 0 END) as tank_time,
                SUM(CASE WHEN hps > 0 THEN duration ELSE 0 END) as heal_time
            FROM combat_encounters
            WHERE character_id = ?
        ');
        $stmt->execute([$character_id]);
        $roleRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $dmgT = (float) ($roleRow['dmg_time'] ?? 0);
        $tankT = (float) ($roleRow['tank_time'] ?? 0);
        $healT = (float) ($roleRow['heal_time'] ?? 0);
        $maxT = max($dmgT, $tankT, $healT);

        if ($maxT > 0) {
            if ($dmgT === $maxT) {
                $role = 'damage';
                $stmt2 = $pdo->prepare('SELECT AVG(dps) as avg, MAX(dps) as max, SUM(total_damage) as total, COUNT(*) as encounters FROM combat_encounters WHERE character_id = ? AND dps > 0');
                $stmt2->execute([$character_id]);
                $rs = $stmt2->fetch(PDO::FETCH_ASSOC);
                $data['role']['stats'] = [
                    ['label' => 'Avg DPS', 'value' => number_format((float) ($rs['avg'] ?? 0), 0)],
                    ['label' => 'Peak DPS', 'value' => number_format((float) ($rs['max'] ?? 0), 0)],
                    ['label' => 'Total Damage', 'value' => number_format((int) ($rs['total'] ?? 0))],
                    ['label' => 'Encounters', 'value' => (int) ($rs['encounters'] ?? 0)],
                ];
            } elseif ($tankT === $maxT) {
                $role = 'tanking';
                $stmt2 = $pdo->prepare('SELECT AVG(dtps) as avg, MAX(dtps) as max, SUM(total_damage_taken) as total, COUNT(*) as encounters FROM combat_encounters WHERE character_id = ? AND dtps > 0');
                $stmt2->execute([$character_id]);
                $rs = $stmt2->fetch(PDO::FETCH_ASSOC);
                $data['role']['stats'] = [
                    ['label' => 'Avg DTPS', 'value' => number_format((float) ($rs['avg'] ?? 0), 0)],
                    ['label' => 'Peak DTPS', 'value' => number_format((float) ($rs['max'] ?? 0), 0)],
                    ['label' => 'Total Taken', 'value' => number_format((int) ($rs['total'] ?? 0))],
                    ['label' => 'Encounters', 'value' => (int) ($rs['encounters'] ?? 0)],
                ];
            } else {
                $role = 'healing';
                $stmt2 = $pdo->prepare('SELECT AVG(hps) as avg, MAX(hps) as max, SUM(total_healing) as total, COUNT(*) as encounters FROM combat_encounters WHERE character_id = ? AND hps > 0');
                $stmt2->execute([$character_id]);
                $rs = $stmt2->fetch(PDO::FETCH_ASSOC);
                $data['role']['stats'] = [
                    ['label' => 'Avg HPS', 'value' => number_format((float) ($rs['avg'] ?? 0), 0)],
                    ['label' => 'Peak HPS', 'value' => number_format((float) ($rs['max'] ?? 0), 0)],
                    ['label' => 'Total Healing', 'value' => number_format((int) ($rs['total'] ?? 0))],
                    ['label' => 'Encounters', 'value' => (int) ($rs['encounters'] ?? 0)],
                ];
            }
            $data['role']['primary'] = $role;
        }
    } catch (Throwable $e) {
        error_log('role: ' . $e->getMessage());
    }

    // ─── PLAYTIME ─────────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('SELECT SUM(total_time) as total FROM sessions WHERE character_id = ?');
        $stmt->execute([$character_id]);
        $pt = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['playtime'] = (int) ($pt['total'] ?? 0);
    } catch (Throwable $e) {
        error_log('playtime: ' . $e->getMessage());
    }

    // ─── TIPS ─────────────────────────────────────────────────────────────────
    try {
        $tipsPath = __DIR__ . '/../tips.json';
        if (file_exists($tipsPath)) {
            $tips = json_decode(file_get_contents($tipsPath), true);
            if ($tips) {
                // Find tips for the current level range
                $allNotes = [];
                foreach ($tips as $entry) {
                    $range = $entry['level_range'] ?? '';
                    if ($range) {
                        [$lo, $hi] = explode('-', $range . '-0');
                        if ($currentLevel >= (int) $lo && ($hi === '0' || $currentLevel <= (int) $hi)) {
                            foreach (($entry['notes'] ?? []) as $note) {
                                $allNotes[] = $note;
                            }
                        }
                    }
                }
                // If no level-specific notes, grab from all
                if (empty($allNotes)) {
                    foreach ($tips as $entry) {
                        foreach (($entry['notes'] ?? []) as $note) {
                            $allNotes[] = $note;
                        }
                    }
                }
                // Pick 3 random tips
                if (!empty($allNotes)) {
                    shuffle($allNotes);
                    $data['tips'] = array_slice($allNotes, 0, 3);
                }
            }
        }
    } catch (Throwable $e) {
        error_log('tips: ' . $e->getMessage());
    }

    // ─── ZONE ─────────────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('SELECT zone, subzone FROM series_zones WHERE character_id = ? ORDER BY ts DESC LIMIT 1');
        $stmt->execute([$character_id]);
        $zoneRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($zoneRow) {
            $data['zone']['zone'] = $zoneRow['zone'];
            $data['zone']['subzone'] = $zoneRow['subzone'];
        }
    } catch (Throwable $e) {
        error_log('zone: ' . $e->getMessage());
    }

    // ─── SHARING SETTINGS ─────────────────────────────────────────────────────
    try {
        // visibility = PUBLIC means shared character
        $data['sharing']['shared_character'] = ($character['visibility'] === 'PUBLIC');

        // Check if this character is marked as a bank alt via character_key or a user-set flag
        // Currently no dedicated bank_alt column — check if class_file is a typical bank alt indicator
        // We'll check the characters table for a is_bank_alt flag added via migration
        try {
            $stmt = $pdo->prepare('SELECT is_bank_alt FROM characters WHERE id = ?');
            $stmt->execute([$character_id]);
            $flag = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($flag !== false) {
                $data['sharing']['shared_bank_alt'] = (bool) ($flag['is_bank_alt'] ?? false);
            }
        } catch (Throwable $e) {
            // Column doesn't exist yet — that's fine
        }
    } catch (Throwable $e) {
        error_log('sharing: ' . $e->getMessage());
    }

    echo json_encode($data, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log('Fatal error in dashboard-data.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}