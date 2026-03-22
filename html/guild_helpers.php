<?php
/**
 * Guild Helper Functions
 * 
 * Use after running the guild architecture migration
 * Include this file in upload_whodat.php and guild hall sections
 */

/**
 * Get guild_id for a character
 * 
 * @param PDO $pdo Database connection
 * @param int $character_id Character ID
 * @return int|null Guild ID or null if not in a guild
 */
function getGuildIdForCharacter($pdo, $character_id)
{
    $stmt = $pdo->prepare("
        SELECT guild_id 
        FROM character_guilds 
        WHERE character_id = ? AND is_current = TRUE
        LIMIT 1
    ");
    $stmt->execute([$character_id]);
    return $stmt->fetchColumn() ?: null;
}

/**
 * Get or create guild record
 * 
 * @param PDO $pdo Database connection
 * @param string $guild_name Guild name
 * @param string|null $realm Realm name
 * @param string|null $faction Faction (Alliance/Horde)
 * @return int Guild ID
 */
function getOrCreateGuild($pdo, $guild_name, $realm = null, $faction = null)
{
    // Try to find existing guild
    $stmt = $pdo->prepare("
        SELECT guild_id 
        FROM guilds 
        WHERE guild_name = ? AND (realm = ? OR (realm IS NULL AND ? IS NULL))
        LIMIT 1
    ");
    $stmt->execute([$guild_name, $realm, $realm]);
    $guildId = $stmt->fetchColumn();

    if ($guildId) {
        return $guildId;
    }

    // Create new guild
    $stmt = $pdo->prepare("
        INSERT INTO guilds (guild_name, realm, faction)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$guild_name, $realm, $faction]);

    return $pdo->lastInsertId();
}

/**
 * Link character to guild
 * 
 * @param PDO $pdo Database connection
 * @param int $character_id Character ID
 * @param int $guild_id Guild ID
 * @return bool Success
 */
function linkCharacterToGuild($pdo, $character_id, $guild_id)
{
    // Mark all previous guilds as not current
    $stmt = $pdo->prepare("
        UPDATE character_guilds 
        SET is_current = FALSE 
        WHERE character_id = ?
    ");
    $stmt->execute([$character_id]);

    // Add/update current guild
    $stmt = $pdo->prepare("
        INSERT INTO character_guilds (character_id, guild_id, is_current)
        VALUES (?, ?, TRUE)
        ON DUPLICATE KEY UPDATE is_current = TRUE, joined_at = NOW()
    ");
    $stmt->execute([$character_id, $guild_id]);

    return true;
}

/**
 * Get guild info for a character
 * 
 * @param PDO $pdo Database connection
 * @param int $character_id Character ID
 * @return array|null Guild info (guild_id, guild_name, faction, realm) or null
 */
function getGuildInfoForCharacter($pdo, $character_id)
{
    $stmt = $pdo->prepare("
        SELECT g.guild_id, g.guild_name, g.faction, g.realm
        FROM character_guilds cg
        INNER JOIN guilds g ON cg.guild_id = g.guild_id
        WHERE cg.character_id = ? AND cg.is_current = TRUE
        LIMIT 1
    ");
    $stmt->execute([$character_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Check if tables have been migrated to guild-based architecture
 * 
 * @param PDO $pdo Database connection
 * @return bool True if migrated, false if still character-based
 */
function isGuildArchitectureMigrated($pdo)
{
    try {
        // Check if guilds table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'guilds'");
        if ($stmt->rowCount() === 0) {
            return false;
        }

        // Check if guild_bank_money_logs has guild_id column
        $stmt = $pdo->query("SHOW COLUMNS FROM guild_bank_money_logs LIKE 'guild_id'");
        if ($stmt->rowCount() === 0) {
            return false;
        }

        return true;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get all characters in a guild
 * 
 * @param PDO $pdo Database connection
 * @param int $guild_id Guild ID
 * @return array Array of character records
 */
function getGuildCharacters($pdo, $guild_id)
{
    $stmt = $pdo->prepare("
        SELECT c.character_id, c.character_name, c.class, c.level
        FROM character_guilds cg
        INNER JOIN characters c ON cg.character_id = c.character_id
        WHERE cg.guild_id = ? AND cg.is_current = TRUE
        ORDER BY c.character_name
    ");
    $stmt->execute([$guild_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}