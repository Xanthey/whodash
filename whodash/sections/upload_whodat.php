<?php
declare(strict_types=1);

// TIMEOUT FIX - Increase limits for large uploads
set_time_limit(300);  // 5 minutes
ini_set('max_execution_time', '600');
ini_set('memory_limit', '512M');

session_start();

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lua.php';

// Start output buffering for progress updates
ob_start();

header('Content-Type: text/html; charset=utf-8');

// Output progress bar HTML and CSS immediately
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: #f7fafc;
            margin: 0;
            padding: 20px;
        }

        .upload-container {
            max-width: 600px;
            margin: 40px auto;
            background: #fff;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 4px 12px rgba(30, 60, 114, 0.08);
        }

        .progress-wrapper {
            margin: 24px 0;
        }

        .progress-bar-container {
            height: 40px;
            background: #e6eefb;
            border-radius: 20px;
            overflow: hidden;
            position: relative;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            height: 100%;
            width: 0%;
            background: linear-gradient(45deg,
                    #2456a5 25%,
                    #ffffff 25%,
                    #ffffff 50%,
                    #2456a5 50%,
                    #2456a5 75%,
                    #ffffff 75%,
                    #ffffff);
            background-size: 40px 40px;
            border-radius: 20px;
            transition: width 0.3s ease;
            animation: candy-cane 1s linear infinite;
            box-shadow: 0 2px 8px rgba(36, 86, 165, 0.3);
        }

        @keyframes candy-cane {
            0% {
                background-position: 0 0;
            }

            100% {
                background-position: 40px 40px;
            }
        }

        .progress-text {
            text-align: center;
            margin-top: 16px;
            font-size: 1rem;
            color: #2456a5;
            font-weight: 600;
            min-height: 24px;
        }

        .progress-message {
            text-align: center;
            margin-top: 8px;
            font-size: 0.9rem;
            color: #6e7f9b;
            font-style: italic;
            min-height: 20px;
        }

        .success-message {
            background: linear-gradient(135deg, #e9f7ef 0%, #c8e6d0 100%);
            border: 2px solid #2e7d32;
            border-radius: 12px;
            padding: 20px;
            margin-top: 24px;
            color: #1b5e20;
        }

        .success-message h3 {
            margin: 0 0 12px 0;
            color: #2e7d32;
            font-size: 1.25rem;
        }

        .error-message {
            background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
            border: 2px solid #d32f2f;
            border-radius: 12px;
            padding: 20px;
            margin-top: 24px;
            color: #b71c1c;
        }

        .error-message h3 {
            margin: 0 0 12px 0;
            color: #d32f2f;
            font-size: 1.25rem;
        }
    </style>
</head>

<body>
    <div class="upload-container">
        <h2 style="margin: 0 0 24px 0; color: #2456a5;">ÃƒÂ¢Ã…Â¡Ã¢â‚¬ÂÃƒÂ¯Ã‚Â¸Ã‚Â Uploading WhoDAT Data</h2>
        <div class="progress-wrapper">
            <div class="progress-bar-container">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div class="progress-text" id="progressText">0%</div>
            <div class="progress-message" id="progressMessage">Preparing upload...</div>
        </div>
        <div id="resultContainer"></div>
    </div>

    <script>
        function updateProgress(percent, message) {
            document.getElementById('progressBar').style.width = percent + '%';
            document.getElementById('progressText').textContent = percent + '%';
            document.getElementById('progressMessage').textContent = message;
        }

        function showResult(html, isError = false) {
            const container = document.getElementById('resultContainer');
            container.innerHTML = html;
            document.getElementById('progressBar').style.width = '100%';
            document.getElementById('progressText').textContent = '100%';
            document.getElementById('progressMessage').textContent = isError ? 'Upload failed' : 'Complete!';
        }
    </script>
    <?php
    // Flush output so progress bar shows immediately
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();

    // WoW-themed loading messages
    $loadingMessages = [
        5 => "Whipping the peons...",
        10 => "Summoning your character...",
        15 => "Consulting the spirits...",
        20 => "Polishing your armor...",
        25 => "Counting your gold...",
        30 => "Reading ancient runes...",
        35 => "Brewing a health potion...",
        40 => "Taming your mount...",
        45 => "Sharpening your blade...",
        50 => "Deciphering talent trees...",
        55 => "Organizing your bags...",
        60 => "Calculating DPS...",
        65 => "Enchanting your gear...",
        70 => "Updating quest log...",
        75 => "Checking auction house...",
        77 => "Processing market data...",
        80 => "Syncing with the servers...",
        85 => "Applying buffs...",
        90 => "Finalizing character data...",
        95 => "Almost there...",
    ];

    function sendProgress(int $percent, string $message = '')
    {
        echo "<script>updateProgress($percent, " . json_encode($message) . ");</script>\n";
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
        // Small delay to ensure updates are visible
        usleep(50000); // 50ms
    }

    // ============================================================================
// Utility Functions
// ============================================================================
    
    function i($v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    function f($v): ?float
    {
        return is_numeric($v) ? (float) $v : null;
    }

    function j($v): string
    {
        return json_encode($v ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    function norm_str(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = preg_replace('/[ _]+/u', '-', $s);
        return preg_replace('/[^a-z0-9\-]/u', '', $s);
    }

    function parseItemIdFromLink(?string $link): ?int
    {
        if (!$link)
            return null;
        if (preg_match('/Hitem:(\-?\d+)/', $link, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    // ============================================================================
// Character Upsert
// ============================================================================
    
    function upsertCharacter(PDO $pdo, int $userId, array $identity, ?int $schemaVersion): int
    {
        $realm = (string) ($identity['realm'] ?? '');
        $name = (string) ($identity['player_name'] ?? '');
        $faction = $identity['faction'] ?? null;
        $classLocal = $identity['class_local'] ?? null;
        $classFile = $identity['class_file'] ?? null;
        $race = $identity['race_local'] ?? null;
        $raceFile = $identity['race_file'] ?? null;
        $sex = isset($identity['sex']) ? (int) $identity['sex'] : null;
        $locale = $identity['locale'] ?? null;
        $hasRelic = isset($identity['has_relic_slot']) ? (int) $identity['has_relic_slot'] : null;
        $lastLoginTs = $identity['last_login_ts'] ?? null;

        $guild = $identity['guild']['name'] ?? null;
        $guildRank = $identity['guild']['rank'] ?? null;
        $guildRankIx = $identity['guild']['rank_index'] ?? null;
        $guildMembers = $identity['guild']['members'] ?? null;

        $addonName = $identity['addon']['name'] ?? null;
        $addonAuthor = $identity['addon']['author'] ?? null;
        $addonVer = $identity['addon']['version'] ?? null;

        // Character key format: "Realm:Name" (matching export.lua)
        $characterKey = "{$realm}:{$name}";
        $realmNorm = norm_str($realm);
        $nameNorm = norm_str($name);

        $sql = "INSERT INTO characters
        (user_id, character_key, realm, name, faction, class_local, class_file, race, race_file, sex, locale, 
         has_relic_slot, last_login_ts, addon_name, addon_author, addon_version, 
         guild_name, guild_rank, guild_rank_index, guild_members, schema_version, 
         realm_norm, name_norm, updated_at)
        VALUES
        (:uid, :ck, :realm, :name, :faction, :class_local, :class_file, :race, :race_file, :sex, :locale, 
         :has_relic_slot, :last_login_ts, :addon_name, :addon_author, :addon_version, 
         :guild_name, :guild_rank, :guild_rank_index, :guild_members, :schema_version, 
         :realm_norm, :name_norm, NOW())
        ON DUPLICATE KEY UPDATE
          user_id         = VALUES(user_id),
          faction         = VALUES(faction),
          class_local     = VALUES(class_local),
          class_file      = VALUES(class_file),
          race            = VALUES(race),
          race_file       = VALUES(race_file),
          sex             = VALUES(sex),
          locale          = VALUES(locale),
          has_relic_slot  = VALUES(has_relic_slot),
          last_login_ts   = VALUES(last_login_ts),
          addon_name      = VALUES(addon_name),
          addon_author    = VALUES(addon_author),
          addon_version   = VALUES(addon_version),
          guild_name      = VALUES(guild_name),
          guild_rank      = VALUES(guild_rank),
          guild_rank_index= VALUES(guild_rank_index),
          guild_members   = VALUES(guild_members),
          schema_version  = VALUES(schema_version),
          realm_norm      = VALUES(realm_norm),
          name_norm       = VALUES(name_norm),
          updated_at      = NOW()";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uid' => $userId,
            ':ck' => $characterKey,
            ':realm' => $realm,
            ':name' => $name,
            ':faction' => $faction,
            ':class_local' => $classLocal,
            ':class_file' => $classFile,
            ':race' => $race,
            ':race_file' => $raceFile,
            ':sex' => $sex,
            ':locale' => $locale,
            ':has_relic_slot' => $hasRelic,
            ':last_login_ts' => i($lastLoginTs),
            ':addon_name' => $addonName,
            ':addon_author' => $addonAuthor,
            ':addon_version' => $addonVer,
            ':guild_name' => $guild,
            ':guild_rank' => $guildRank,
            ':guild_rank_index' => i($guildRankIx),
            ':guild_members' => i($guildMembers),
            ':schema_version' => i($schemaVersion),
            ':realm_norm' => $realmNorm,
            ':name_norm' => $nameNorm,
        ]);

        $idStmt = $pdo->prepare("SELECT id FROM characters WHERE character_key = :ck LIMIT 1");
        $idStmt->execute([':ck' => $characterKey]);
        $characterId = (int) ($idStmt->fetchColumn() ?: 0);

        if (!$characterId) {
            throw new RuntimeException("Character upsert failed.");
        }

        return $characterId;
    }

    // ============================================================================
// Import Series Data
// ============================================================================
    
    function importSeries(PDO $pdo, int $characterId, array $series): void
    {
        // Money series - UPSERT instead of DELETE+INSERT
        if (!empty($series['money'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO series_money (character_id, ts, value) 
             VALUES (:cid, :ts, :val)
             ON DUPLICATE KEY UPDATE value = VALUES(value)"
            );

            foreach ($series['money'] as $point) {
                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => i($point['ts'] ?? null),
                    ':val' => i($point['value'] ?? null),
                ]);
            }
        }

        // XP series - UPSERT
        if (!empty($series['xp'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO series_xp (character_id, ts, value, max) 
             VALUES (:cid, :ts, :val, :max)
             ON DUPLICATE KEY UPDATE value = VALUES(value), max = VALUES(max)"
            );

            foreach ($series['xp'] as $point) {
                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => i($point['ts'] ?? null),
                    ':val' => i($point['value'] ?? null),
                    ':max' => i($point['max'] ?? null),
                ]);
            }
        }

        // Level series - UPSERT
        if (!empty($series['level'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO series_level (character_id, ts, value) 
             VALUES (:cid, :ts, :val)
             ON DUPLICATE KEY UPDATE value = VALUES(value)"
            );

            foreach ($series['level'] as $point) {
                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => i($point['ts'] ?? null),
                    ':val' => i($point['value'] ?? null),
                ]);
            }
        }

        // Rested series - UPSERT
        if (!empty($series['rested'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO series_rested (character_id, ts, value) 
             VALUES (:cid, :ts, :val)
             ON DUPLICATE KEY UPDATE value = VALUES(value)"
            );

            foreach ($series['rested'] as $point) {
                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => i($point['ts'] ?? null),
                    ':val' => i($point['value'] ?? null),
                ]);
            }
        }

        // Honor series - UPSERT
        if (!empty($series['honor'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO series_honor (character_id, ts, value) 
             VALUES (:cid, :ts, :val)
             ON DUPLICATE KEY UPDATE value = VALUES(value)"
            );

            foreach ($series['honor'] as $point) {
                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => i($point['ts'] ?? null),
                    ':val' => i($point['value'] ?? null),
                ]);
            }
        }

        // Zone series - UPSERT
        if (!empty($series['zones'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO series_zones (character_id, ts, zone, subzone, hearth) 
             VALUES (:cid, :ts, :zone, :subzone, :hearth)
             ON DUPLICATE KEY UPDATE 
               zone = VALUES(zone), 
               subzone = VALUES(subzone), 
               hearth = VALUES(hearth)"
            );

            foreach ($series['zones'] as $point) {
                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => i($point['ts'] ?? null),
                    ':zone' => $point['zone'] ?? null,
                    ':subzone' => $point['subzone'] ?? null,
                    ':hearth' => $point['hearth'] ?? null,
                ]);
            }
        }

        // Base stats series - UPSERT
        if (!empty($series['base_stats'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO series_base_stats 
             (character_id, ts, strength, agility, stamina, intellect, spirit, armor, defense,
              resist_arcane, resist_fire, resist_frost, resist_holy, resist_nature, resist_shadow) 
             VALUES 
             (:cid, :ts, :str, :agi, :sta, :int, :spi, :armor, :def,
              :r_arc, :r_fire, :r_frost, :r_holy, :r_nat, :r_sha)
             ON DUPLICATE KEY UPDATE
               strength = VALUES(strength),
               agility = VALUES(agility),
               stamina = VALUES(stamina),
               intellect = VALUES(intellect),
               spirit = VALUES(spirit),
               armor = VALUES(armor),
               defense = VALUES(defense),
               resist_arcane = VALUES(resist_arcane),
               resist_fire = VALUES(resist_fire),
               resist_frost = VALUES(resist_frost),
               resist_holy = VALUES(resist_holy),
               resist_nature = VALUES(resist_nature),
               resist_shadow = VALUES(resist_shadow)"
            );

            foreach ($series['base_stats'] as $point) {
                // Stats are directly on the point object in v3 format
                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => i($point['ts'] ?? null),
                    ':str' => i($point['strength'] ?? null),
                    ':agi' => i($point['agility'] ?? null),
                    ':sta' => i($point['stamina'] ?? null),
                    ':int' => i($point['intellect'] ?? null),
                    ':spi' => i($point['spirit'] ?? null),
                    ':armor' => i($point['armor'] ?? null),
                    ':def' => i($point['defense'] ?? null),
                    ':r_arc' => i($point['resist_arcane'] ?? null),
                    ':r_fire' => i($point['resist_fire'] ?? null),
                    ':r_frost' => i($point['resist_frost'] ?? null),
                    ':r_holy' => i($point['resist_holy'] ?? null),
                    ':r_nat' => i($point['resist_nature'] ?? null),
                    ':r_sha' => i($point['resist_shadow'] ?? null),
                ]);
            }
        }

        // Skills (Professions) - REPLACE strategy OK here since it's current state
        if (!empty($series['skills'])) {
            $pdo->prepare("DELETE FROM skills WHERE character_id = :cid")
                ->execute([':cid' => $characterId]);

            $stmt = $pdo->prepare(
                "INSERT INTO skills (character_id, name, `rank`, max_rank) 
             VALUES (:cid, :name, :rank, :max)"
            );

            foreach ($series['skills'] as $skill) {
                $stmt->execute([
                    ':cid' => $characterId,
                    ':name' => $skill['name'] ?? null,
                    ':rank' => i($skill['rank'] ?? $skill['`rank`'] ?? null),
                    ':max' => i($skill['max'] ?? $skill['max_rank'] ?? null),
                ]);
            }
        }

        // Resource Max (Health and Mana) series - UPSERT
        if (!empty($series['resource_max'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO series_resource_max (character_id, ts, hp, mp, power_type) 
             VALUES (:cid, :ts, :hp, :mp, :power_type)
             ON DUPLICATE KEY UPDATE 
               hp = VALUES(hp),
               mp = VALUES(mp),
               power_type = VALUES(power_type)"
            );

            foreach ($series['resource_max'] as $point) {
                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => i($point['ts'] ?? null),
                    ':hp' => i($point['hp'] ?? null),
                    ':mp' => i($point['mp'] ?? null),
                    ':power_type' => i($point['powerType'] ?? null),
                ]);
            }
        }
    }

    // ============================================================================
// Import Events
// ============================================================================
    // ============================================================================
// Import Quest Events
// ============================================================================
    function importQuestEvents(PDO $pdo, int $characterId, array $questEvents): void
    {
        if (empty($questEvents)) {
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO quest_events (character_id, ts, kind, quest_id, quest_title, 
         objective_text, objective_progress, objective_total, objective_complete)
         VALUES (:cid, :ts, :kind, :quest_id, :quest_title, 
         :obj_text, :obj_progress, :obj_total, :obj_complete)
         ON DUPLICATE KEY UPDATE
           kind = VALUES(kind),
           quest_title = VALUES(quest_title),
           objective_text = VALUES(objective_text),
           objective_progress = VALUES(objective_progress),
           objective_total = VALUES(objective_total),
           objective_complete = VALUES(objective_complete)"
        );

        $count = 0;
        foreach ($questEvents as $event) {
            // Handle both old format (direct data) and new format (with meta)
            $data = $event['data'] ?? $event;
            $kind = $event['kind'] ?? 'accepted';
            $ts = isset($event['ts']) ? (int) $event['ts'] : null;

            // Skip if no timestamp
            if (!$ts) {
                continue;
            }

            // Handle objective_complete - convert to proper TINYINT (0 or 1)
            $objComplete = null;
            if (isset($data['complete'])) {
                $objComplete = ($data['complete'] === true || $data['complete'] === 1 || $data['complete'] === '1') ? 1 : 0;
            }

            $stmt->execute([
                ':cid' => $characterId,
                ':ts' => $ts,
                ':kind' => $kind,
                ':quest_id' => isset($data['id']) ? (int) $data['id'] : null,
                ':quest_title' => $data['title'] ?? null,
                ':obj_text' => $data['objective'] ?? null,
                ':obj_progress' => isset($data['progress']) ? (int) $data['progress'] : null,
                ':obj_total' => isset($data['total']) ? (int) $data['total'] : null,
                ':obj_complete' => $objComplete,
            ]);

            $count++;
        }

        if ($count > 0) {
            error_log("WhoDAT Import: Imported $count quest events");
        }
    }

    // ============================================================================
// Import Quest Log Snapshot
// ============================================================================
    function importQuestLog(PDO $pdo, int $characterId, array $questLogData): void
    {
        if (empty($questLogData)) {
            return;
        }

        $ts = isset($questLogData['ts']) ? (int) $questLogData['ts'] : time();
        $quests = $questLogData['quests'] ?? $questLogData; // Handle both wrapped and unwrapped
    
        // If it's not an array of quests, return
        if (!is_array($quests) || empty($quests)) {
            return;
        }

        // Delete old snapshots for this timestamp (in case of re-import)
        $pdo->prepare("DELETE FROM quest_log_snapshots WHERE character_id = ? AND ts = ?")
            ->execute([$characterId, $ts]);

        $stmt = $pdo->prepare(
            "INSERT INTO quest_log_snapshots (character_id, ts, quest_id, quest_title, 
         quest_complete, objectives)
         VALUES (:cid, :ts, :quest_id, :quest_title, :complete, :objectives)"
        );

        $count = 0;
        foreach ($quests as $quest) {
            // Skip if not a proper quest object
            if (!is_array($quest) || empty($quest['title'])) {
                continue;
            }

            // FIXED: Properly handle quest_complete as TINYINT (0 or 1)
            // Handle various truthy/falsy values from Lua parser
            $questComplete = 0; // Default to not complete
            if (isset($quest['complete'])) {
                // Explicit check for true values
                if ($quest['complete'] === true || $quest['complete'] === 1 || $quest['complete'] === '1' || $quest['complete'] === 'true') {
                    $questComplete = 1;
                }
                // Everything else (false, 0, '0', 'false', null, empty string) = 0
            }

            $stmt->execute([
                ':cid' => $characterId,
                ':ts' => $ts,
                ':quest_id' => isset($quest['id']) ? (int) $quest['id'] : null,
                ':quest_title' => $quest['title'] ?? null,
                ':complete' => $questComplete,
                ':objectives' => json_encode($quest['objectives'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            $count++;
        }

        if ($count > 0) {
            error_log("WhoDAT Import: Imported $count quests from quest log snapshot");
        }
    }

    // ============================================================================
    // Quest Rewards Import
    // ============================================================================
    function importQuestRewards(PDO $pdo, int $characterId, array $questRewards): void
    {
        if (empty($questRewards)) {
            return;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO quest_rewards 
             (character_id, ts, quest_id, quest_title, quest_level,
              reward_chosen_link, reward_chosen_name, reward_chosen_quantity, reward_chosen_quality,
              reward_choices, reward_required,
              money, xp, honor, arena, reputation,
              zone, subzone)
             VALUES 
             (:cid, :ts, :quest_id, :quest_title, :quest_level,
              :chosen_link, :chosen_name, :chosen_qty, :chosen_quality,
              :choices, :required,
              :money, :xp, :honor, :arena, :reputation,
              :zone, :subzone)
             ON DUPLICATE KEY UPDATE
               quest_title = VALUES(quest_title),
               quest_level = VALUES(quest_level),
               reward_chosen_link = VALUES(reward_chosen_link),
               reward_chosen_name = VALUES(reward_chosen_name),
               reward_chosen_quantity = VALUES(reward_chosen_quantity),
               reward_chosen_quality = VALUES(reward_chosen_quality),
               reward_choices = VALUES(reward_choices),
               reward_required = VALUES(reward_required),
               money = VALUES(money),
               xp = VALUES(xp),
               honor = VALUES(honor),
               arena = VALUES(arena),
               reputation = VALUES(reputation),
               zone = VALUES(zone),
               subzone = VALUES(subzone)"
        );

        $count = 0;
        foreach ($questRewards as $reward) {
            $ts = isset($reward['ts']) ? (int) $reward['ts'] : null;
            if (!$ts)
                continue;

            // Extract chosen reward
            $chosenLink = null;
            $chosenName = null;
            $chosenQty = null;
            $chosenQuality = null;

            if (!empty($reward['reward_choices'])) {
                foreach ($reward['reward_choices'] as $choice) {
                    if (!empty($choice['link'])) {
                        $chosenLink = $choice['link'];
                        $chosenName = $choice['name'] ?? null;
                        $chosenQty = isset($choice['quantity']) ? (int) $choice['quantity'] : 1;
                        $chosenQuality = isset($choice['quality']) ? (int) $choice['quality'] : 0;
                        break;
                    }
                }
            }

            $stmt->execute([
                ':cid' => $characterId,
                ':ts' => $ts,
                ':quest_id' => isset($reward['quest_id']) ? (int) $reward['quest_id'] : null,
                ':quest_title' => $reward['quest_title'] ?? null,
                ':quest_level' => isset($reward['quest_level']) ? (int) $reward['quest_level'] : null,
                ':chosen_link' => $chosenLink,
                ':chosen_name' => $chosenName,
                ':chosen_qty' => $chosenQty,
                ':chosen_quality' => $chosenQuality,
                ':choices' => json_encode($reward['reward_choices'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':required' => json_encode($reward['reward_required'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':money' => isset($reward['money']) ? (int) $reward['money'] : 0,
                ':xp' => isset($reward['xp']) ? (int) $reward['xp'] : 0,
                ':honor' => isset($reward['honor']) ? (int) $reward['honor'] : 0,
                ':arena' => isset($reward['arena']) ? (int) $reward['arena'] : 0,
                ':reputation' => json_encode($reward['reputation'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ':zone' => $reward['zone'] ?? null,
                ':subzone' => $reward['subzone'] ?? null,
            ]);

            $count++;
        }

        if ($count > 0) {
            error_log("WhoDAT Import: Imported $count quest rewards");
        }
    }

    function importEvents(PDO $pdo, int $characterId, array $events): array
    {
        $stats = [
            'deaths' => 0,
            'boss_kills' => 0,
            'items' => 0,
            'tradeskills' => 0,
            'skipped' => 0,
            'combat' => 0,
        ];

        // Tradeskill events (recipe_learned)
        if (!empty($events['tradeskill'])) {
            // Clear existing
            $pdo->prepare("DELETE FROM tradeskill_reagents WHERE tradeskill_id IN 
                       (SELECT id FROM tradeskills WHERE character_id = :cid)")
                ->execute([':cid' => $characterId]);
            $pdo->prepare("DELETE FROM tradeskills WHERE character_id = :cid")
                ->execute([':cid' => $characterId]);

            $insTs = $pdo->prepare(
                "INSERT INTO tradeskills (character_id, ts, name, link, icon, profession)
             VALUES (:cid, :ts, :name, :link, :icon, :profession)"
            );
            $insRg = $pdo->prepare(
                "INSERT INTO tradeskill_reagents (tradeskill_id, name, count_required, have_count, link, icon)
             VALUES (:tsid, :name, :req, :have, :link, :icon)"
            );

            foreach ($events['tradeskill'] as $event) {
                if (($event['kind'] ?? '') !== 'recipe_learned')
                    continue;

                $data = $event['data'] ?? [];
                $profession = $data['profession'] ?? null;
                $ts = i($event['ts'] ?? null);

                // Skip if no timestamp
                if (!$ts) {
                    $stats['skipped']++;
                    continue;
                }

                $insTs->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':name' => $data['recipe'] ?? null,
                    ':link' => $data['link'] ?? null,
                    ':icon' => $data['icon'] ?? null,
                    ':profession' => $profession,
                ]);

                $stats['tradeskills']++;
                $tsId = (int) $pdo->lastInsertId();

                // Reagents
                if (!empty($data['reagents'])) {
                    foreach ($data['reagents'] as $rg) {
                        $insRg->execute([
                            ':tsid' => $tsId,
                            ':name' => $rg['name'] ?? null,
                            ':req' => i($rg['count'] ?? null),
                            ':have' => i($rg['have'] ?? null),
                            ':link' => $rg['link'] ?? null,
                            ':icon' => $rg['icon'] ?? null,
                        ]);
                    }
                }
            }
        }

        // Combat Encounters - UPSERT instead of DELETE
        if (!empty($events['combat'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO combat_encounters 
         (character_id, ts, duration, dps, hps, dtps, overheal_pct,
          total_damage, total_healing, total_overheal, total_damage_taken,
          target, target_level, is_boss, instance, instance_difficulty,
          group_type, group_size, zone, subzone)
         VALUES 
         (:cid, :ts, :duration, :dps, :hps, :dtps, :overheal_pct,
          :total_dmg, :total_heal, :total_overheal, :total_dmg_taken,
          :target, :target_lvl, :is_boss, :instance, :instance_diff,
          :group_type, :group_size, :zone, :subzone)
         ON DUPLICATE KEY UPDATE
           duration = VALUES(duration),
           dps = VALUES(dps),
           hps = VALUES(hps),
           dtps = VALUES(dtps),
           overheal_pct = VALUES(overheal_pct),
           total_damage = VALUES(total_damage),
           total_healing = VALUES(total_healing),
           total_overheal = VALUES(total_overheal),
           total_damage_taken = VALUES(total_damage_taken),
           target = VALUES(target),
           target_level = VALUES(target_level),
           is_boss = VALUES(is_boss),
           instance = VALUES(instance),
           instance_difficulty = VALUES(instance_difficulty),
           group_type = VALUES(group_type),
           group_size = VALUES(group_size),
           zone = VALUES(zone),
           subzone = VALUES(subzone)"
            );

            $skipped = 0;
            foreach ($events['combat'] as $combat) {
                // Only process ended encounters
                if (($combat['_action'] ?? '') !== 'ended') {
                    continue;
                }

                // Get timestamp - try both 'ts' and '_ts'
                $ts = i($combat['ts'] ?? $combat['_ts'] ?? null);

                // Skip if no timestamp
                if (!$ts) {
                    $skipped++;
                    $stats['skipped']++;
                    continue;
                }

                // Execute insert/update
                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':duration' => f($combat['duration'] ?? null),
                    ':dps' => f($combat['dps'] ?? null),
                    ':hps' => f($combat['hps'] ?? null),
                    ':dtps' => f($combat['dtps'] ?? null),
                    ':overheal_pct' => f($combat['overheal_pct'] ?? null),
                    ':total_dmg' => i($combat['total_damage'] ?? null),
                    ':total_heal' => i($combat['total_healing'] ?? null),
                    ':total_overheal' => i($combat['total_overheal'] ?? null),
                    ':total_dmg_taken' => i($combat['total_damage_taken'] ?? null),
                    ':target' => $combat['target'] ?? null,
                    ':target_lvl' => i($combat['target_level'] ?? null),
                    ':is_boss' => ($combat['is_boss'] ?? false) ? 1 : 0,
                    ':instance' => $combat['instance'] ?? null,
                    ':instance_diff' => $combat['instance_difficulty'] ?? null,
                    ':group_type' => $combat['group_type'] ?? 'solo',
                    ':group_size' => i($combat['group_size'] ?? 1),
                    ':zone' => $combat['zone'] ?? null,
                    ':subzone' => $combat['subzone'] ?? null,
                ]);

                $stats['combat']++;
            }

            if ($skipped > 0) {
                error_log("WhoDAT Import: Skipped $skipped combat events without timestamps");
            }
        }

        // Death events - UPSERT instead of DELETE
        if (!empty($events['deaths'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO deaths (character_id, ts, zone, subzone, killer_name, killer_type, 
             level, durability_loss, rez_type, rez_time, combat_duration, group_size, group_type)
             VALUES (:cid, :ts, :zone, :subzone, :killer_name, :killer_type, 
             :level, :dur_loss, :rez_type, :rez_time, :combat_dur, :group_size, :group_type)
             ON DUPLICATE KEY UPDATE
               zone = VALUES(zone),
               subzone = VALUES(subzone),
               killer_name = VALUES(killer_name),
               killer_type = VALUES(killer_type),
               level = VALUES(level),
               durability_loss = VALUES(durability_loss),
               rez_type = VALUES(rez_type),
               rez_time = VALUES(rez_time),
               combat_duration = VALUES(combat_duration),
               group_size = VALUES(group_size),
               group_type = VALUES(group_type)"
            );

            $skipped = 0;
            foreach ($events['deaths'] as $death) {
                $ts = i($death['ts'] ?? $death['_ts'] ?? null);

                // Skip if no timestamp
                if (!$ts) {
                    $skipped++;
                    $stats['skipped']++;
                    continue;
                }

                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':zone' => $death['zone'] ?? null,
                    ':subzone' => $death['subzone'] ?? null,
                    ':killer_name' => $death['killer_name'] ?? null,
                    ':killer_type' => $death['killer_type'] ?? 'unknown',
                    ':level' => i($death['level'] ?? null),
                    ':dur_loss' => f($death['durability_loss'] ?? null),
                    ':rez_type' => $death['rez_type'] ?? 'unknown',
                    ':rez_time' => i($death['rez_time'] ?? null),
                    ':combat_dur' => f($death['combat_duration'] ?? null),
                    ':group_size' => i($death['group_size'] ?? 0),
                    ':group_type' => $death['group_type'] ?? 'solo',
                ]);

                $stats['deaths']++;
            }
            if ($skipped > 0) {
                error_log("WhoDAT Import: Skipped $skipped death events without timestamps");
            }
        }

        // Combat events - extract boss kills from combat encounters with is_boss = true
        if (!empty($events['combat'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO boss_kills (character_id, ts, boss_name, instance, difficulty, difficulty_name, group_size, group_type)
             VALUES (:cid, :ts, :name, :instance, :difficulty, :difficulty_name, :group_size, :group_type)
             ON DUPLICATE KEY UPDATE
               difficulty = VALUES(difficulty),
               difficulty_name = VALUES(difficulty_name),
               group_size = VALUES(group_size),
               group_type = VALUES(group_type)"
            );

            foreach ($events['combat'] as $combat) {
                // Only process boss encounters
                if (empty($combat['is_boss']) || $combat['is_boss'] !== true) {
                    continue;
                }

                // Only process ended encounters
                if (($combat['_action'] ?? '') !== 'ended') {
                    continue;
                }

                $ts = i($combat['ts'] ?? $combat['_ts'] ?? null);

                // Skip if no timestamp
                if (!$ts) {
                    $stats['skipped']++;
                    continue;
                }

                // Extract difficulty (0 = normal, 1 = heroic, etc)
                $difficulty = 0;
                $difficultyName = $combat['instance_difficulty'] ?? '';

                // Map difficulty names to numeric values
                if (stripos($difficultyName, 'heroic') !== false) {
                    $difficulty = 1;
                } elseif (stripos($difficultyName, '10') !== false) {
                    $difficulty = 10;
                } elseif (stripos($difficultyName, '25') !== false) {
                    $difficulty = 25;
                }

                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':name' => $combat['target'] ?? null,
                    ':instance' => $combat['instance'] ?? $combat['zone'] ?? null,
                    ':difficulty' => $difficulty,
                    ':difficulty_name' => $difficultyName ?: 'Normal',
                    ':group_size' => i($combat['group_size'] ?? 1),
                    ':group_type' => $combat['group_type'] ?? 'party',
                ]);

                $stats['boss_kills']++;
            }
        }

        // Boss kills (legacy format - keeping for backwards compatibility)
        if (!empty($events['boss_kills'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO boss_kills (character_id, ts, boss_name, instance, difficulty, group_size, group_type)
             VALUES (:cid, :ts, :name, :instance, :difficulty, :group_size, :group_type)
             ON DUPLICATE KEY UPDATE
               difficulty = VALUES(difficulty),
               group_size = VALUES(group_size),
               group_type = VALUES(group_type)"
            );

            foreach ($events['boss_kills'] as $kill) {
                $ts = i($kill['ts'] ?? $kill['_ts'] ?? null);

                // Skip if no timestamp
                if (!$ts) {
                    $stats['skipped']++;
                    continue;
                }

                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':name' => $kill['boss_name'] ?? $kill['name'] ?? null,
                    ':instance' => $kill['instance_name'] ?? $kill['instance'] ?? null,
                    ':difficulty' => i($kill['difficulty'] ?? ($kill['is_heroic'] ? 2 : 1)),
                    ':group_size' => i($kill['group_size'] ?? $kill['raid_size'] ?? null),
                    ':group_type' => $kill['group_type'] ?? 'raid',
                ]);

                $stats['boss_kills']++;
            }
        }

        // Item events (loot/obtained) - UPSERT instead of DELETE
        if (!empty($events['items'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO item_events (character_id, ts, action, source, location, 
             item_id, item_string, name, link, count, icon, context_json)
             VALUES (:cid, :ts, :action, :source, :location, 
             :item_id, :item_string, :name, :link, :count, :icon, :context)
             ON DUPLICATE KEY UPDATE
               source = VALUES(source),
               location = VALUES(location),
               item_string = VALUES(item_string),
               name = VALUES(name),
               link = VALUES(link),
               count = VALUES(count),
               icon = VALUES(icon),
               context_json = VALUES(context_json)"
            );

            foreach ($events['items'] as $item) {
                $data = $item['data'] ?? $item;
                $kind = $item['kind'] ?? 'obtained';

                $ts = i($item['ts'] ?? $data['ts'] ?? null);

                // Skip if no timestamp
                if (!$ts) {
                    $stats['skipped']++;
                    continue;
                }

                $itemId = i($data['item_id'] ?? parseItemIdFromLink($data['link'] ?? null));
                $context = isset($data['context']) ? j($data['context']) : null;

                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':action' => $kind,
                    ':source' => $data['source'] ?? null,
                    ':location' => $data['location'] ?? null,
                    ':item_id' => $itemId,
                    ':item_string' => $data['link'] ?? null,
                    ':name' => $data['name'] ?? null,
                    ':link' => $data['link'] ?? null,
                    ':count' => i($data['count'] ?? 1),
                    ':icon' => $data['icon'] ?? null,
                    ':context' => $context,
                ]);

                $stats['items']++;
            }
        }

        // Group composition events - UPSERT instead of DELETE
        $stats['groups'] = 0;
        if (!empty($events['groups'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO group_compositions (character_id, ts, type, size, instance, zone, subzone, members)
             VALUES (:cid, :ts, :type, :size, :instance, :zone, :subzone, :members)
             ON DUPLICATE KEY UPDATE
               type = VALUES(type),
               size = VALUES(size),
               instance = VALUES(instance),
               zone = VALUES(zone),
               subzone = VALUES(subzone),
               members = VALUES(members)"
            );

            foreach ($events['groups'] as $group) {
                $ts = i($group['ts'] ?? null);

                if (!$ts) {
                    $stats['skipped']++;
                    continue;
                }

                // Extract instance name from instance object if present
                $instanceName = null;
                if (!empty($group['instance'])) {
                    $instanceName = $group['instance']['name'] ?? null;
                }

                // Convert members array to JSON
                $membersJson = isset($group['members']) ? j($group['members']) : null;

                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':type' => $group['type'] ?? 'party',
                    ':size' => i($group['size'] ?? 0),
                    ':instance' => $instanceName,
                    ':zone' => $group['zone'] ?? null,
                    ':subzone' => $group['subzone'] ?? null,
                    ':members' => $membersJson,
                ]);

                $stats['groups']++;
            }
        }

        // Friend list changes - UPSERT instead of DELETE
        $stats['friends'] = 0;
        if (!empty($events['friends'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO friend_list_changes (character_id, ts, action, friend_name, friend_level, friend_class, note)
             VALUES (:cid, :ts, :action, :name, :level, :class, :note)
             ON DUPLICATE KEY UPDATE
               action = VALUES(action),
               friend_level = VALUES(friend_level),
               friend_class = VALUES(friend_class),
               note = VALUES(note)"
            );

            foreach ($events['friends'] as $friend) {
                $ts = i($friend['ts'] ?? null);

                if (!$ts) {
                    $stats['skipped']++;
                    continue;
                }

                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':action' => $friend['action'] ?? 'added',
                    ':name' => $friend['name'] ?? null,
                    ':level' => i($friend['level'] ?? null),
                    ':class' => $friend['class'] ?? null,
                    ':note' => $friend['note'] ?? null,
                ]);

                $stats['friends']++;
            }
        }

        // Ignore list changes - UPSERT instead of DELETE
        $stats['ignored'] = 0;
        if (!empty($events['ignored'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO ignore_list_changes (character_id, ts, action, ignored_name)
             VALUES (:cid, :ts, :action, :name)
             ON DUPLICATE KEY UPDATE
               action = VALUES(action)"
            );

            foreach ($events['ignored'] as $ignored) {
                $ts = i($ignored['ts'] ?? null);

                if (!$ts) {
                    $stats['skipped']++;
                    continue;
                }

                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':action' => $ignored['action'] ?? 'added',
                    ':name' => $ignored['name'] ?? null,
                ]);

                $stats['ignored']++;
            }
        }

        return $stats;
    }

    // ============================================================================
// Import Items Catalog
// ============================================================================
    
    function importItemsCatalog(PDO $pdo, int $characterId, array $catalog): void
    {
        if (empty($catalog))
            return;

        // Clear existing catalog
        $pdo->prepare("DELETE FROM items_catalog WHERE character_id = :cid")
            ->execute([':cid' => $characterId]);

        $stmt = $pdo->prepare(
            "INSERT INTO items_catalog 
         (character_id, item_id, item_string, name, quality, stack_size, equip_loc, 
          icon, ilvl, quantity_bag, quantity_bank, quantity_keyring, quantity_mail)
         VALUES 
         (:cid, :item_id, :item_string, :name, :quality, :stack, :equip, 
          :icon, :ilvl, :qty_bag, :qty_bank, :qty_key, :qty_mail)"
        );

        foreach ($catalog as $item) {
            $stmt->execute([
                ':cid' => $characterId,
                ':item_id' => i($item['id'] ?? null),
                ':item_string' => $item['item_string'] ?? null,
                ':name' => $item['name'] ?? null,
                ':quality' => i($item['quality'] ?? null),
                ':stack' => i($item['stack_size'] ?? null),
                ':equip' => $item['equip_loc'] ?? null,
                ':icon' => $item['icon'] ?? null,
                ':ilvl' => i($item['ilvl'] ?? null),
                ':qty_bag' => i($item['quantity_bag'] ?? 0),
                ':qty_bank' => i($item['quantity_bank'] ?? 0),
                ':qty_key' => i($item['quantity_keyring'] ?? 0),
                ':qty_mail' => i($item['quantity_mail'] ?? 0),
            ]);
        }
    }

    // ============================================================================
// Import Containers
// ============================================================================
    
    function importContainers(PDO $pdo, int $characterId, array $containers): void
    {
        // Clear existing containers
        $pdo->prepare("DELETE FROM containers_bag WHERE character_id = :cid")
            ->execute([':cid' => $characterId]);

        // Bags (flat structure: one row per slot)
        if (!empty($containers['bags'])) {
            $stmt = $pdo->prepare(
                "INSERT INTO containers_bag 
             (character_id, bag_id, slot, ts, item_id, item_string, name, link, count, ilvl, icon, quality)
             VALUES (:cid, :bag_id, :slot, :ts, :item_id, :item_string, :name, :link, :count, :ilvl, :icon, :quality)"
            );

            foreach ($containers['bags'] as $bagIdx => $bag) {
                if (!empty($bag['contents'])) {
                    foreach ($bag['contents'] as $slotIdx => $item) {
                        $itemId = $item['id'] ?? parseItemIdFromLink($item['link'] ?? $item['item_string'] ?? null);

                        $stmt->execute([
                            ':cid' => $characterId,
                            ':bag_id' => $bagIdx,
                            ':slot' => $slotIdx,
                            ':ts' => i($bag['ts'] ?? null),
                            ':item_id' => i($itemId),
                            ':item_string' => $item['item_string'] ?? $item['link'] ?? null,
                            ':name' => $item['name'] ?? null,
                            ':link' => $item['link'] ?? $item['item_string'] ?? null,
                            ':count' => i($item['count'] ?? 1),
                            ':ilvl' => i($item['ilvl'] ?? null),
                            ':icon' => $item['icon'] ?? null,
                            ':quality' => i($item['quality'] ?? null),
                        ]);
                    }
                }
            }
        }

        // Bank (similar flat structure)
        if (!empty($containers['bank'])) {
            $pdo->prepare("DELETE FROM containers_bank WHERE character_id = :cid")
                ->execute([':cid' => $characterId]);

            $stmt = $pdo->prepare(
                "INSERT INTO containers_bank 
             (character_id, inv_slot, ts, item_id, item_string, name, link, count, ilvl, icon)
             VALUES (:cid, :inv_slot, :ts, :item_id, :item_string, :name, :link, :count, :ilvl, :icon)"
            );

            foreach ($containers['bank'] as $slotIdx => $item) {
                if (!empty($item)) {
                    $itemId = $item['id'] ?? parseItemIdFromLink($item['link'] ?? $item['item_string'] ?? null);

                    $stmt->execute([
                        ':cid' => $characterId,
                        ':inv_slot' => $slotIdx,
                        ':ts' => i($item['ts'] ?? null),
                        ':item_id' => i($itemId),
                        ':item_string' => $item['item_string'] ?? $item['link'] ?? null,
                        ':name' => $item['name'] ?? null,
                        ':link' => $item['link'] ?? $item['item_string'] ?? null,
                        ':count' => i($item['count'] ?? 1),
                        ':ilvl' => i($item['ilvl'] ?? null),
                        ':icon' => $item['icon'] ?? null,
                    ]);
                }
            }
        }

        // Keyring
        if (!empty($containers['keyring'])) {
            $pdo->prepare("DELETE FROM containers_keyring WHERE character_id = :cid")
                ->execute([':cid' => $characterId]);

            $stmt = $pdo->prepare(
                "INSERT INTO containers_keyring 
             (character_id, slot, ts, item_id, item_string, name, link, count, ilvl, icon)
             VALUES (:cid, :slot, :ts, :item_id, :item_string, :name, :link, :count, :ilvl, :icon)"
            );

            foreach ($containers['keyring'] as $slotIdx => $item) {
                if (!empty($item)) {
                    $itemId = $item['id'] ?? parseItemIdFromLink($item['link'] ?? $item['item_string'] ?? null);

                    $stmt->execute([
                        ':cid' => $characterId,
                        ':slot' => $slotIdx,
                        ':ts' => i($item['ts'] ?? null),
                        ':item_id' => i($itemId),
                        ':item_string' => $item['item_string'] ?? $item['link'] ?? null,
                        ':name' => $item['name'] ?? null,
                        ':link' => $item['link'] ?? $item['item_string'] ?? null,
                        ':count' => i($item['count'] ?? 1),
                        ':ilvl' => i($item['ilvl'] ?? null),
                        ':icon' => $item['icon'] ?? null,
                    ]);
                }
            }
        }
    }

    // ============================================================================
// Import Reputation
// ============================================================================
    
    function importReputation(PDO $pdo, int $characterId, array $charData): void
    {
        // Reputation can be in series or events
        $repData = $charData['series']['reputation'] ?? $charData['reputation'] ?? [];

        if (empty($repData)) {
            return;
        }

        // UPSERT instead of DELETE+INSERT
        $stmt = $pdo->prepare(
            "INSERT INTO series_reputation (character_id, ts, faction_name, value, standing_id, 
         min, max)
         VALUES (:cid, :ts, :name, :value, :standing, :min, :max)
         ON DUPLICATE KEY UPDATE
           value = VALUES(value),
           standing_id = VALUES(standing_id),
           min = VALUES(min),
           max = VALUES(max)"
        );

        foreach ($repData as $rep) {
            $stmt->execute([
                ':cid' => $characterId,
                ':ts' => i($rep['ts'] ?? time()),
                ':name' => $rep['name'] ?? null,
                ':value' => i($rep['value'] ?? 0),
                ':standing' => i($rep['standing_id'] ?? 4),
                ':min' => i($rep['min'] ?? 0),
                ':max' => i($rep['max'] ?? 0),
            ]);
        }
    }

    // ============================================================================
// Import Achievements
// ============================================================================
    
    function importAchievements(PDO $pdo, int $characterId, array $db): void
    {
        // Achievements are at WhoDatDB.timeseries.achievements (root level, not character level)
        $achievementsData = $db['timeseries']['achievements'] ?? $db['series']['achievements'] ?? $db['achievements'] ?? [];

        if (empty($achievementsData)) {
            return;
        }

        // UPSERT instead of DELETE+INSERT
        $stmt = $pdo->prepare(
            "INSERT INTO series_achievements 
             (character_id, ts, achievement_id, name, description, points, earned, earned_date)
             VALUES (:cid, :ts, :achievement_id, :name, :description, :points, :earned, :earned_date)
             ON DUPLICATE KEY UPDATE
               name = VALUES(name),
               description = VALUES(description),
               points = VALUES(points),
               earned = VALUES(earned),
               earned_date = VALUES(earned_date)"
        );

        foreach ($achievementsData as $achievement) {
            $stmt->execute([
                ':cid' => $characterId,
                ':ts' => i($achievement['ts'] ?? time()),
                ':achievement_id' => i($achievement['id'] ?? 0),
                ':name' => $achievement['name'] ?? 'Unknown Achievement',
                ':description' => $achievement['description'] ?? null,
                ':points' => i($achievement['points'] ?? 0),
                ':earned' => ($achievement['earned'] ?? false) ? 1 : 0,
                ':earned_date' => i($achievement['earnedDate'] ?? null),
            ]);
        }
    }

    // ============================================================================
// Import Companions (Mounts & Pets)
// ============================================================================
    
    function importCompanions(PDO $pdo, int $characterId, array $db, array $charData): void
    {
        // Companions are in characters[name].snapshots.companions
        $companionsData = $charData['snapshots']['companions'] ?? [];

        if (empty($companionsData)) {
            return;
        }

        // Clear existing companions for this character
        $pdo->prepare("DELETE FROM companions WHERE character_id = ?")
            ->execute([$characterId]);

        $stmt = $pdo->prepare(
            "INSERT INTO companions 
             (character_id, ts, type, name, icon, creature_id, spell_id, active)
             VALUES (:cid, :ts, :type, :name, :icon, :creature_id, :spell_id, :active)"
        );

        // Get timestamp from companions data
        $ts = i($companionsData['ts'] ?? time());

        // Process mounts
        if (!empty($companionsData['mount']) && is_array($companionsData['mount'])) {
            foreach ($companionsData['mount'] as $mount) {
                if (!is_array($mount))
                    continue;

                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':type' => 'MOUNT',
                    ':name' => $mount['name'] ?? 'Unknown Mount',
                    ':icon' => $mount['icon'] ?? null,
                    ':creature_id' => i($mount['creature_id'] ?? null),
                    ':spell_id' => i($mount['spell_id'] ?? null),
                    ':active' => 0,
                ]);
            }
        }

        // Process pets/critters
        if (!empty($companionsData['critter']) && is_array($companionsData['critter'])) {
            foreach ($companionsData['critter'] as $pet) {
                if (!is_array($pet))
                    continue;

                $stmt->execute([
                    ':cid' => $characterId,
                    ':ts' => $ts,
                    ':type' => 'CRITTER',
                    ':name' => $pet['name'] ?? 'Unknown Pet',
                    ':icon' => $pet['icon'] ?? null,
                    ':creature_id' => i($pet['creature_id'] ?? null),
                    ':spell_id' => i($pet['spell_id'] ?? null),
                    ':active' => 0,
                ]);
            }
        }
    }

    // ============================================================================
// Import Sessions
// ============================================================================
    
    function importSessions(PDO $pdo, int $characterId, array $sessions): void
    {
        if (empty($sessions)) {
            return;
        }

        // UPSERT instead of DELETE+INSERT
        $stmt = $pdo->prepare(
            "INSERT INTO sessions (character_id, ts, total_time, level_time)
         VALUES (:cid, :ts, :total_time, :level_time)
         ON DUPLICATE KEY UPDATE
           total_time = VALUES(total_time),
           level_time = VALUES(level_time)"
        );

        foreach ($sessions as $session) {
            $startTs = i($session['start_ts'] ?? $session['login_ts'] ?? $session['ts'] ?? null);
            $endTs = i($session['end_ts'] ?? $session['logout_ts'] ?? null);
            $totalTime = $endTs && $startTs ? ($endTs - $startTs) : i($session['total_time'] ?? $session['duration'] ?? null);

            // Skip if no timestamp
            if (!$startTs)
                continue;

            $stmt->execute([
                ':cid' => $characterId,
                ':ts' => $startTs,
                ':total_time' => $totalTime,
                ':level_time' => i($session['level_time'] ?? null),
            ]);
        }
    }

    // ============================================================================
// Import Talents Snapshot
// ============================================================================
    
    function importTalentsSnapshot(PDO $pdo, int $characterId, array $snapshots): void
    {
        $talentsData = $snapshots['talents'] ?? [];

        if (empty($talentsData)) {
            return;
        }

        // Clear existing talent data for this character
        $pdo->prepare("DELETE FROM talents WHERE talents_tab_id IN 
                   (SELECT id FROM talents_tabs WHERE talents_group_id IN 
                   (SELECT id FROM talents_groups WHERE character_id = :cid))")
            ->execute([':cid' => $characterId]);

        $pdo->prepare("DELETE FROM talents_tabs WHERE talents_group_id IN 
                   (SELECT id FROM talents_groups WHERE character_id = :cid)")
            ->execute([':cid' => $characterId]);

        $pdo->prepare("DELETE FROM talents_groups WHERE character_id = :cid")
            ->execute([':cid' => $characterId]);

        // Insert talent group
        $groupStmt = $pdo->prepare(
            "INSERT INTO talents_groups (character_id, ts, group_index)
         VALUES (:cid, :ts, :group_idx)"
        );

        $groupStmt->execute([
            ':cid' => $characterId,
            ':ts' => i($talentsData['ts'] ?? time()),
            ':group_idx' => i($talentsData['group'] ?? 1),
        ]);

        $groupId = (int) $pdo->lastInsertId();

        // Insert talent tabs (trees)
        $tabs = $talentsData['tabs'] ?? [];
        $tabStmt = $pdo->prepare(
            "INSERT INTO talents_tabs (talents_group_id, name, icon, points_spent)
         VALUES (:group_id, :name, :icon, :points)"
        );

        foreach ($tabs as $tab) {
            $tabStmt->execute([
                ':group_id' => $groupId,
                ':name' => $tab['name'] ?? null,
                ':icon' => $tab['icon'] ?? null,
                ':points' => i($tab['points'] ?? 0),
            ]);

            $tabId = (int) $pdo->lastInsertId();

            // Insert individual talents in this tab
            $talents = $tab['talents'] ?? [];
            $talentStmt = $pdo->prepare(
                "INSERT INTO talents (talents_tab_id, name, `rank`, max_rank, link, talent_id)
             VALUES (:tab_id, :name, :rank, :max_rank, :link, :talent_id)"
            );

            foreach ($talents as $talent) {
                // Only insert if talent has points
                $rank = i($talent['rank'] ?? 0);
                if ($rank > 0) {
                    $talentStmt->execute([
                        ':tab_id' => $tabId,
                        ':name' => $talent['name'] ?? null,
                        ':rank' => $rank,
                        ':max_rank' => i($talent['maxRank'] ?? 0),
                        ':link' => $talent['link'] ?? null,
                        ':talent_id' => i($talent['talentId'] ?? null),
                    ]);
                }
            }
        }
    }

    // ============================================================================
// Import Equipment
// ============================================================================
    
    function importEquipment(PDO $pdo, int $characterId, array $equipment): void
    {
        $slots = $equipment['slots'] ?? [];
        $ts = $equipment['ts'] ?? time();

        if (empty($slots)) {
            return;
        }

        // Clear existing equipment for this character
        $pdo->prepare("DELETE FROM equipment_snapshot WHERE character_id = :cid")
            ->execute([':cid' => $characterId]);

        $stmt = $pdo->prepare(
            "INSERT INTO equipment_snapshot 
         (character_id, ts, slot_name, item_id, link, icon, ilvl, count, name)
         VALUES (:cid, :ts, :slot, :item_id, :link, :icon, :ilvl, :count, :name)"
        );

        foreach ($slots as $slotName => $item) {
            if (empty($item))
                continue;

            $itemId = $item['item_id'] ?? parseItemIdFromLink($item['link'] ?? null);

            $stmt->execute([
                ':cid' => $characterId,
                ':ts' => i($ts),
                ':slot' => $slotName,
                ':item_id' => i($itemId),
                ':link' => $item['link'] ?? null,
                ':icon' => $item['icon'] ?? null,
                ':ilvl' => i($item['ilvl'] ?? null),
                ':count' => i($item['count'] ?? 1),
                ':name' => $item['name'] ?? null,
            ]);
        }
    }

    // ============================================================================
// Import Auction Data
// ============================================================================
    
    function importAuctions(PDO $pdo, int $characterId, array $auctionDB, string $charName, string $realm, string $faction, array $mailbox = []): void
    {
        if (empty($auctionDB)) {
            return;
        }

        // Build the key to look for this character's auctions
        $charKey = "{$realm}-{$faction}:{$charName}";

        if (!isset($auctionDB[$charKey]) || empty($auctionDB[$charKey])) {
            return;
        }

        $auctions = $auctionDB[$charKey];

        // Build mailbox lookup for sold/expired status
        $soldItems = [];
        $expiredItems = [];

        foreach ($mailbox as $mail) {
            $subject = $mail['subject'] ?? '';

            if (str_contains($subject, 'Auction successful:')) {
                $itemName = trim(str_replace('Auction successful:', '', $subject));
                $soldItems[$itemName] = [
                    'sold_ts' => time(),
                    'sold_price' => i($mail['money'] ?? 0),
                ];
            } elseif (str_contains($subject, 'Auction expired:')) {
                $itemName = trim(str_replace('Auction expired:', '', $subject));
                if (!isset($expiredItems[$itemName])) {
                    $expiredItems[$itemName] = 0;
                }
                $expiredItems[$itemName]++;
            }
        }

        // UPSERT auctions (don't delete existing data)
        $stmt = $pdo->prepare("
            INSERT INTO auction_owner_rows 
            (rf_char_key, ts, item_id, link, name, stack_size, price_stack, duration_bucket, seller, sold, sold_ts, sold_price, expired, expired_ts)
            VALUES 
            (:rf_char_key, :ts, :item_id, :link, :name, :stack_size, :price_stack, :duration_bucket, :seller, :sold, :sold_ts, :sold_price, :expired, :expired_ts)
            ON DUPLICATE KEY UPDATE
              item_id = VALUES(item_id),
              link = VALUES(link),
              name = VALUES(name),
              stack_size = VALUES(stack_size),
              price_stack = VALUES(price_stack),
              duration_bucket = VALUES(duration_bucket),
              seller = VALUES(seller),
              sold = VALUES(sold),
              sold_ts = VALUES(sold_ts),
              sold_price = VALUES(sold_price),
              expired = VALUES(expired),
              expired_ts = VALUES(expired_ts)
        ");

        foreach ($auctions as $auction) {
            $itemName = $auction['name'] ?? '';

            // Check if this item was sold (from LUA data OR mailbox)
            $wasSold = ($auction['sold'] ?? false) ? 1 : 0;
            $soldTs = isset($auction['sold_ts']) ? i($auction['sold_ts']) : null;
            $soldPrice = isset($auction['sold_price']) ? i($auction['sold_price']) : null;

            // Override with mailbox data if available
            if (!$wasSold && isset($soldItems[$itemName])) {
                $wasSold = 1;
                $soldTs = $soldItems[$itemName]['sold_ts'];
                $soldPrice = $soldItems[$itemName]['sold_price'];
            }

            // Check if this item expired (from mailbox only)
            $wasExpired = 0;
            $expiredTs = null;
            if (isset($expiredItems[$itemName]) && $expiredItems[$itemName] > 0) {
                $wasExpired = 1;
                $expiredTs = time();
                $expiredItems[$itemName]--;
            }

            // Validate duration_bucket (TINYINT UNSIGNED: 0-255)
            $durationBucket = i($auction['duration'] ?? null);
            if ($durationBucket !== null) {
                // Clamp to valid range for TINYINT UNSIGNED (0-255)
                $durationBucket = max(0, min(255, $durationBucket));
            }

            $stmt->execute([
                ':rf_char_key' => $charKey,
                ':ts' => i($auction['ts'] ?? time()),
                ':item_id' => i($auction['itemId'] ?? null),
                ':link' => $auction['link'] ?? null,
                ':name' => $itemName,
                ':stack_size' => i($auction['stackSize'] ?? 1),
                ':price_stack' => i($auction['price'] ?? 0),
                ':duration_bucket' => $durationBucket,
                ':seller' => $auction['seller'] ?? null,
                ':sold' => $wasSold,
                ':sold_ts' => $soldTs,
                ':sold_price' => $soldPrice,
                ':expired' => $wasExpired,
                ':expired_ts' => $expiredTs,
            ]);
        }
    }

    // ============================================================================
// NEW: Import Market Data (WhoDAT_AuctionMarketTS)
// ============================================================================
    
    function importMarketData(PDO $pdo, string $realm, string $faction, array $marketTS): void
    {
        if (empty($marketTS)) {
            return;
        }

        $rfKey = "$realm-$faction";

        // UPSERT market snapshots (don't delete existing data)
        $tsStmt = $pdo->prepare("
            INSERT INTO auction_market_ts 
            (rf_key, item_key, ts, my_price_stack, my_price_item)
            VALUES 
            (:rf_key, :item_key, :ts, :my_price_stack, :my_price_item)
            ON DUPLICATE KEY UPDATE
              my_price_stack = VALUES(my_price_stack),
              my_price_item = VALUES(my_price_item)
        ");

        $bandStmt = $pdo->prepare("
            INSERT INTO auction_market_bands 
            (market_ts_id, band_type, price_stack, price_item, seller, link)
            VALUES 
            (:market_ts_id, :band_type, :price_stack, :price_item, :seller, :link)
            ON DUPLICATE KEY UPDATE
              price_stack = VALUES(price_stack),
              price_item = VALUES(price_item),
              seller = VALUES(seller),
              link = VALUES(link)
        ");

        foreach ($marketTS as $key => $snapshots) {
            // Parse key: "Icecrown-Alliance\n7070:0:1:stouthearted:252450"
            $parts = explode("\n", $key);
            if (count($parts) < 2)
                continue;

            $itemKeyParts = explode(':', $parts[1]);
            if (count($itemKeyParts) < 2)
                continue;

            // item_key format: "itemId:stackSize"
            $itemId = $itemKeyParts[0];
            $stackSize = $itemKeyParts[2] ?? 1;
            $itemKey = "$itemId:$stackSize";

            foreach ($snapshots as $snapshot) {
                $ts = i($snapshot['ts'] ?? time());
                $myPriceStack = i($snapshot['my']['priceStack'] ?? null);
                $myPriceItem = i($snapshot['my']['priceItem'] ?? null);

                // UPSERT market snapshot
                $tsStmt->execute([
                    ':rf_key' => $rfKey,
                    ':item_key' => $itemKey,
                    ':ts' => $ts,
                    ':my_price_stack' => $myPriceStack,
                    ':my_price_item' => $myPriceItem,
                ]);

                // Get the market_ts_id (either just inserted or existing)
                $marketTsId = (int) $pdo->lastInsertId();

                // If lastInsertId is 0, it means we updated an existing row
                if ($marketTsId === 0) {
                    $getIdStmt = $pdo->prepare("
                        SELECT id FROM auction_market_ts 
                        WHERE rf_key = :rf_key AND item_key = :item_key AND ts = :ts
                    ");
                    $getIdStmt->execute([
                        ':rf_key' => $rfKey,
                        ':item_key' => $itemKey,
                        ':ts' => $ts,
                    ]);
                    $marketTsId = (int) $getIdStmt->fetchColumn();
                }

                // Clear existing bands for this snapshot before inserting new ones
                $pdo->prepare("DELETE FROM auction_market_bands WHERE market_ts_id = :id")
                    ->execute([':id' => $marketTsId]);

                // Insert LOW bands
                if (isset($snapshot['low']) && is_array($snapshot['low'])) {
                    foreach ($snapshot['low'] as $band) {
                        $bandStmt->execute([
                            ':market_ts_id' => $marketTsId,
                            ':band_type' => 'LOW',
                            ':price_stack' => i($band['priceStack'] ?? null),
                            ':price_item' => i($band['priceItem'] ?? null),
                            ':seller' => $band['seller'] ?? null,
                            ':link' => $band['link'] ?? null,
                        ]);
                    }
                }

                // Insert HIGH bands
                if (isset($snapshot['high']) && is_array($snapshot['high'])) {
                    foreach ($snapshot['high'] as $band) {
                        $bandStmt->execute([
                            ':market_ts_id' => $marketTsId,
                            ':band_type' => 'HIGH',
                            ':price_stack' => i($band['priceStack'] ?? null),
                            ':price_item' => i($band['priceItem'] ?? null),
                            ':seller' => $band['seller'] ?? null,
                            ':link' => $band['link'] ?? null,
                        ]);
                    }
                }
            }
        }
    }

    // ============================================================================
// Main Upload Handler
// ============================================================================
    
    function handleWhoDatUpload(PDO $pdo, int $userId, array $file): void
    {
        global $loadingMessages;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("Upload failed with error code {$file['error']}");
        }

        $uploadDir = __DIR__ . '/../uploads';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        sendProgress(5, $loadingMessages[5]);

        $tmpPath = $uploadDir . '/' . basename($file['name']);
        if (!move_uploaded_file($file['tmp_name'], $tmpPath)) {
            throw new RuntimeException("Failed to move uploaded file.");
        }

        try {
            sendProgress(10, $loadingMessages[10]);

            // Parse Lua file
            $parser = new LUAParser();
            $parser->parseFile($tmpPath);
            $root = $parser->data;

            sendProgress(15, $loadingMessages[15]);

            // Get WhoDatDB root
            $db = $root['WhoDatDB'] ?? $root;

            // Extract identity
            $identity = $db['identity'] ?? [];
            if (empty($identity)) {
                throw new RuntimeException("No identity data found in export.");
            }

            sendProgress(20, $loadingMessages[20]);

            // Extract schema version
            $schemaVersion = i($db['schema']['version'] ?? null);

            // Upsert character
            $characterId = upsertCharacter($pdo, $userId, $identity, $schemaVersion);

            sendProgress(25, $loadingMessages[25]);

            // Get character data (first character in the characters table)
            $chars = $db['characters'] ?? [];
            if (empty($chars)) {
                throw new RuntimeException("No character data found in export.");
            }

            $charKey = array_keys($chars)[0];
            $charData = $chars[$charKey];

            sendProgress(30, $loadingMessages[30]);

            // Begin transaction for bulk import
            $pdo->beginTransaction();

            // Import series data
            if (!empty($charData['series'])) {
                sendProgress(35, $loadingMessages[35]);
                importSeries($pdo, $characterId, $charData['series']);
            }

            // Import events
            if (!empty($charData['events'])) {
                sendProgress(40, $loadingMessages[40]);
                $eventStats = importEvents($pdo, $characterId, $charData['events']);
            }

            // Import items catalog
            if (!empty($charData['catalogs']['items_catalog'])) {
                sendProgress(45, $loadingMessages[45]);
                importItemsCatalog($pdo, $characterId, $charData['catalogs']['items_catalog']);
            }

            // Import quest rewards
            if (!empty($charData['events']['quest_rewards'])) {
                sendProgress(71, "Importing quest rewards...");
                importQuestRewards($pdo, $characterId, $charData['events']['quest_rewards']);
            }
            // Import quest events
            if (!empty($charData['events']['quests'])) {
                sendProgress(72, "Importing quest events...");
                importQuestEvents($pdo, $characterId, $charData['events']['quests']);
            }

            // Import quest log snapshot
            if (!empty($charData['snapshots']['quest_log'])) {
                sendProgress(73, "Importing quest log...");
                importQuestLog($pdo, $characterId, $charData['snapshots']['quest_log']);
            }
            // Import containers (including mailbox) and extract mailbox for auction processing
            $mailbox = [];
            if (!empty($charData['containers'])) {
                sendProgress(50, $loadingMessages[50]);
                $mailbox = $charData['containers']['mailbox'] ?? [];
                importContainers($pdo, $characterId, $charData['containers']);
            }

            // Import reputation
            sendProgress(55, $loadingMessages[55]);
            importReputation($pdo, $characterId, $charData);

            // Import achievements
            sendProgress(57, "Importing achievements...");
            importAchievements($pdo, $characterId, $db);

            // Import companions (mounts & pets)
            sendProgress(58, "Importing mounts & pets...");
            importCompanions($pdo, $characterId, $db, $charData);

            // Import sessions
            if (!empty($charData['sessions'])) {
                sendProgress(60, $loadingMessages[60]);
                importSessions($pdo, $characterId, $charData['sessions']);
            }

            // Import equipment (from snapshots)
            if (!empty($charData['snapshots']['equipment'])) {
                sendProgress(65, $loadingMessages[65]);
                importEquipment($pdo, $characterId, $charData['snapshots']['equipment']);
            }

            // Import talents snapshot
            if (!empty($charData['snapshots'])) {
                sendProgress(70, $loadingMessages[70]);
                importTalentsSnapshot($pdo, $characterId, $charData['snapshots']);
            }

            // Import auction data WITH mailbox processing
            if (!empty($root['WhoDAT_AuctionDB'])) {
                sendProgress(75, $loadingMessages[75]);
                $charName = $identity['player_name'] ?? '';
                $realm = $identity['realm'] ?? 'Unknown';
                $faction = $identity['faction'] ?? 'Unknown';
                if ($charName) {
                    importAuctions($pdo, $characterId, $root['WhoDAT_AuctionDB'], $charName, $realm, $faction, $mailbox);
                }
            }

            // NEW: Import market data
            if (!empty($root['WhoDAT_AuctionMarketTS'])) {
                sendProgress(77, $loadingMessages[77]);
                $realm = $identity['realm'] ?? 'Unknown';
                $faction = $identity['faction'] ?? 'Unknown';
                importMarketData($pdo, $realm, $faction, $root['WhoDAT_AuctionMarketTS']);
            }

            sendProgress(85, $loadingMessages[85]);

            $pdo->commit();

            sendProgress(95, $loadingMessages[95]);

            // Success message with stats
            $auctionCount = 0;
            $marketCount = 0;

            if (!empty($root['WhoDAT_AuctionDB'])) {
                $charName = $identity['player_name'] ?? '';
                $realm = $identity['realm'] ?? '';
                $faction = $identity['faction'] ?? '';
                $charKey = "$realm-$faction:$charName";
                $auctionCount = count($root['WhoDAT_AuctionDB'][$charKey] ?? []);
            }

            if (!empty($root['WhoDAT_AuctionMarketTS'])) {
                $marketCount = count($root['WhoDAT_AuctionMarketTS']);
            }

            $successHtml = "
            <div class='success-message'>
<h3>âœ“ Upload Successful!</h3>
                <div style='text-align: left; max-width: 500px; margin: 20px auto;'>
                <p style='margin: 8px 0;'><strong>Character:</strong> " . htmlspecialchars($identity['player_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . "</p>
                <p style='margin: 8px 0;'><strong>Realm:</strong> " . htmlspecialchars($identity['realm'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . "</p>
                <p style='margin: 8px 0;'><strong>Class:</strong> " . htmlspecialchars($identity['class_local'] ?? 'Unknown', ENT_QUOTES, 'UTF-8') . "</p>";

            // Add event stats if available
            if (!empty($eventStats)) {
                if (($eventStats['combat'] ?? 0) > 0) {
                    $successHtml .= "<p style='margin: 8px 0;'><strong>Combat Encounters:</strong> " . $eventStats['combat'] . "</p>";
                }
                if (($eventStats['deaths'] ?? 0) > 0) {
                    $successHtml .= "<p style='margin: 8px 0;'><strong>Deaths Logged:</strong> " . $eventStats['deaths'] . "</p>";
                }
                if (($eventStats['boss_kills'] ?? 0) > 0) {
                    $successHtml .= "<p style='margin: 8px 0;'><strong>Boss Kills:</strong> " . $eventStats['boss_kills'] . "</p>";
                }
            }

            $successHtml .= "<p style='margin: 8px 0;'><strong>Auctions Imported:</strong> $auctionCount</p>
                <p style='margin: 8px 0;'><strong>Market Snapshots:</strong> $marketCount</p>
                <p style='margin: 8px 0;'><strong>Schema Version:</strong> v" . ($schemaVersion ?? 'unknown') . "</p>
                </div>
            </div>
        ";

            // Output for both streaming progress bar view AND AJAX fetch
            echo "<script>showResult(" . json_encode($successHtml) . ", false);</script>\n";
            echo "<div style='display:none;'>" . $successHtml . "</div>\n"; // For AJAX parsing
    
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errorHtml = "
            <div class='error-message'>
<h3>âœ— Upload Failed</h3>
                <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>
            </div>
        ";

            // Output for both streaming progress bar view AND AJAX fetch
            echo "<script>showResult(" . json_encode($errorHtml) . ", true);</script>\n";
            echo "<div style='display:none;'>" . $errorHtml . "</div>\n"; // For AJAX parsing
            throw $e;
        } finally {
            if (file_exists($tmpPath)) {
                @unlink($tmpPath);
            }
        }
    }

    // ============================================================================
// Entry Point
// ============================================================================
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo "<div style='color:#b00;'>Not logged in.</div>";
        echo "</div></body></html>";
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['whodat_lua'])) {
        try {
            handleWhoDatUpload($pdo, (int) $_SESSION['user_id'], $_FILES['whodat_lua']);
        } catch (Throwable $e) {
            // Error already displayed in handleWhoDatUpload
        }
    } else {
        http_response_code(400);
        $errorHtml = "
        <div class='error-message'>
            <h3>ÃƒÂ¢Ã‚ÂÃ…â€™ No File Uploaded</h3>
            <p>Please select a WhoDAT.lua file to upload.</p>
        </div>
    ";
        // Output for both streaming progress bar view AND AJAX fetch
        echo "<script>showResult(" . json_encode($errorHtml) . ", true);</script>\n";
        echo "<div style='display:none;'>" . $errorHtml . "</div>\n"; // For AJAX parsing
    }
    ?>
    </div>
</body>

</html>