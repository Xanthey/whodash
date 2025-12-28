<?php
declare(strict_types=1);

/*
 * WhoDASH / WhoDAT Database Setup (Production-ready)
 * - Includes original tables
 * - Adds sharing infrastructure: account policy, per-character visibility, token links, public snapshots, audit events
 * - Portable column/index/view helpers for MySQL 5.7/8.0/MariaDB
 * - CLI mode: creates all, backfills normalized names
 * - Web UI: button-driven; no actions on load; CSRF protected
 */

// ───────────────────────────────────────────────────────────────────────────────
// Environment & PDO bootstrap
// ───────────────────────────────────────────────────────────────────────────────
$debug = getenv('APP_DEBUG') === '1';
ini_set('display_errors', $debug ? '1' : '0');
error_reporting($debug ? E_ALL : E_ERROR);

$host = getenv('DB_HOST') ?: 'localhost';
$db = getenv('DB_NAME') ?: 'whodat';
$user = getenv('DB_USER') ?: 'whodatuser';
$pass = getenv('DB_PASSWORD') ?: 'whodatpass';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$opt = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  PDO::ATTR_EMULATE_PREPARES => false,
];

try {
  $pdo = new PDO($dsn, $user, $pass, $opt);
} catch (Throwable $e) {
  http_response_code(500);
  echo "Database connection error: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  exit;
}

// ───────────────────────────────────────────────────────────────────────────────
// Utilities
// ───────────────────────────────────────────────────────────────────────────────

/**
 * One-statement DDL executor with a standard log line.
 * IMPORTANT: Pass only one SQL statement per call.
 */
function run_ddl(PDO $pdo, string $sql, string $what): void
{
  $pdo->exec($sql);
  echo "✅ Created/Updated: {$what}\n";
}

/** Portable index creator: checks INFORMATION_SCHEMA.STATISTICS then creates index when missing. */
function ensure_index(PDO $pdo, string $table, string $indexName, string $createIndexSql): void
{
  $q = "
    SELECT 1
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = :t
      AND INDEX_NAME   = :i
    LIMIT 1";
  $stmt = $pdo->prepare($q);
  $stmt->execute([':t' => $table, ':i' => $indexName]);
  $exists = (bool) $stmt->fetchColumn();
  if (!$exists) {
    $pdo->exec($createIndexSql);
    echo "✅ Created index {$indexName} on {$table}\n";
  } else {
    echo "ℹ️ Index {$indexName} already exists on {$table}\n";
  }
}

/** Check column existence and add via ALTER TABLE when missing (portable for MySQL/MariaDB). */
function ensure_column(PDO $pdo, string $table, string $column, string $definitionSqlFragment): void
{
  $q = "
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = :t
      AND COLUMN_NAME  = :c
    LIMIT 1";
  $stmt = $pdo->prepare($q);
  $stmt->execute([':t' => $table, ':c' => $column]);
  $exists = (bool) $stmt->fetchColumn();
  if (!$exists) {
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN {$definitionSqlFragment}");
    echo "✅ Added column {$table}.{$column}\n";
  } else {
    echo "ℹ️ Column {$table}.{$column} already exists\n";
  }
}

/** Create or replace a view (portable approach: DROP IF EXISTS then CREATE VIEW). */
function ensure_view(PDO $pdo, string $viewName, string $createViewSql): void
{
  $pdo->exec("DROP VIEW IF EXISTS `{$viewName}`");
  $pdo->exec($createViewSql);
  echo "✅ (Re)created view {$viewName}\n";
}

/** PHP 7/8-safe starts_with helper. */
function starts_with(string $haystack, string $prefix): bool
{
  return strncmp($haystack, $prefix, strlen($prefix)) === 0;
}

/** Normalize strings for realm/name URL components: lowercase, trim, spaces/underscores → hyphen, restrict charset. */
function norm_str(string $s): string
{
  $s = mb_strtolower(trim($s), 'UTF-8');
  $s = preg_replace('/[ _]+/u', '-', $s);
  $s = preg_replace('/[^a-z0-9\-]/u', '', $s); // keep ASCII a-z0-9 and hyphen
  return $s;
}

/** Backfill normalized columns for characters (realm_norm, name_norm) when empty or NULL. */
function backfill_normalized_names(PDO $pdo): void
{
  $stmt = $pdo->query("SELECT id, realm, name, realm_norm, name_norm FROM characters");
  $upd = $pdo->prepare("UPDATE characters SET realm_norm = :rn, name_norm = :nn WHERE id = :id");
  $count = 0;
  while ($row = $stmt->fetch()) {
    $rn = $row['realm_norm'] ?? '';
    $nn = $row['name_norm'] ?? '';
    if ($rn === '' || $rn === null || $nn === '' || $nn === null) {
      $realmNorm = norm_str((string) $row['realm']);
      $nameNorm = norm_str((string) $row['name']);
      $upd->execute([':rn' => $realmNorm, ':nn' => $nameNorm, ':id' => $row['id']]);
      $count++;
    }
  }
  echo "✅ Backfilled normalized names for {$count} character(s)\n";
}

// ───────────────────────────────────────────────────────────────────────────────
// Tables (original set, intact)
// ───────────────────────────────────────────────────────────────────────────────

// Optional: Users (for gated WhoDASH access)
function create_table_users(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'users');
}

// Characters + Identity
function create_table_characters(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS characters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_key VARCHAR(200) NOT NULL UNIQUE COMMENT 'Format: Realm:Name:Class (e.g., Icecrown:Belmont:WARRIOR)',
  user_id INT UNSIGNED NULL,
  realm VARCHAR(64) NOT NULL,
  name VARCHAR(64) NOT NULL,
  faction VARCHAR(24),
  class_local VARCHAR(32),
  class_file VARCHAR(32),
  race VARCHAR(32),                               -- NEW
  race_file VARCHAR(32),                          -- NEW
  sex TINYINT UNSIGNED,                           -- NEW
  locale VARCHAR(16),
  has_relic_slot TINYINT(1),
  last_login_ts INT UNSIGNED,
  addon_name VARCHAR(32),
  addon_author VARCHAR(64),
  addon_version VARCHAR(32),
  guild_name VARCHAR(64),
  guild_rank VARCHAR(64),
  guild_rank_index INT UNSIGNED,
  guild_members INT UNSIGNED,
  schema_version INT UNSIGNED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_char_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'characters');
}

/** Indexes for characters (portable). */
function create_table_character_profile_indexes(PDO $pdo): void
{
  ensure_index(
    $pdo,
    'characters',
    'idx_char_realm_name',
    'CREATE INDEX idx_char_realm_name ON characters (realm, name)'
  );
  ensure_index(
    $pdo,
    'characters',
    'idx_char_updated_at',
    'CREATE INDEX idx_char_updated_at ON characters (updated_at)'
  );
}

// Catalog: normalized items catalog (export.lua EnsureItemCatalog)
function create_table_items_catalog(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS items_catalog (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  item_id INT UNSIGNED NOT NULL,
  item_string TEXT,
  name VARCHAR(128),
  quality TINYINT UNSIGNED,
  stack_size INT UNSIGNED,
  equip_loc VARCHAR(32),
  icon VARCHAR(128),
  ilvl INT UNSIGNED,
  quantity_bag INT UNSIGNED DEFAULT 0,
  quantity_bank INT UNSIGNED DEFAULT 0,
  quantity_keyring INT UNSIGNED DEFAULT 0,
  quantity_mail INT UNSIGNED DEFAULT 0,
  UNIQUE KEY uq_char_item (character_id, item_id),
  CONSTRAINT fk_cat_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'items_catalog');
}

// Containers snapshot: bags, bank, keyring, mailbox
function create_table_containers_bag(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS containers_bag (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  bag_id INT NOT NULL, -- 0..4
  slot INT NOT NULL,
  ts INT UNSIGNED,
  item_id INT UNSIGNED,
  item_string TEXT,
  name VARCHAR(128),
  link TEXT,
  count INT UNSIGNED DEFAULT 1,
  ilvl INT UNSIGNED,
  icon VARCHAR(128),
  quality TINYINT UNSIGNED,
  UNIQUE KEY uq_char_bag_slot (character_id, bag_id, slot),
  CONSTRAINT fk_bag_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'containers_bag');
}

function create_table_containers_bank(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS containers_bank (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  inv_slot INT NOT NULL,
  ts INT UNSIGNED,
  item_id INT UNSIGNED,
  item_string TEXT,
  name VARCHAR(128),
  link TEXT,
  count INT UNSIGNED DEFAULT 1,
  ilvl INT UNSIGNED,
  icon VARCHAR(128),
  UNIQUE KEY uq_char_invslot (character_id, inv_slot),
  CONSTRAINT fk_bank_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'containers_bank');
}

function create_table_containers_keyring(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS containers_keyring (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  slot INT NOT NULL, -- 1..32
  ts INT UNSIGNED,
  item_id INT UNSIGNED,
  item_string TEXT,
  name VARCHAR(128),
  link TEXT,
  count INT UNSIGNED DEFAULT 1,
  ilvl INT UNSIGNED,
  icon VARCHAR(128),
  UNIQUE KEY uq_char_key_slot (character_id, slot),
  CONSTRAINT fk_key_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'containers_keyring');
}

function create_table_mailbox(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS mailbox (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  mail_index INT NOT NULL,
  sender VARCHAR(128),
  subject VARCHAR(255),
  money_copper INT UNSIGNED DEFAULT 0,
  cod_copper INT UNSIGNED DEFAULT 0,
  days_left FLOAT,
  was_read TINYINT(1) DEFAULT 0,
  package_icon VARCHAR(128),
  stationery_icon VARCHAR(128),
  ts INT UNSIGNED,
  UNIQUE KEY uq_char_mail_index (character_id, mail_index),
  CONSTRAINT fk_mail_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'mailbox');
}

function create_table_mailbox_attachments(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS mailbox_attachments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  mailbox_id BIGINT UNSIGNED NOT NULL,
  a_index INT NOT NULL, -- 1..12 per mail
  ts INT UNSIGNED,
  item_id INT UNSIGNED,
  item_string TEXT,
  name VARCHAR(128),
  link TEXT,
  count INT UNSIGNED DEFAULT 1,
  ilvl INT UNSIGNED,
  icon VARCHAR(128),
  UNIQUE KEY uq_mail_att_index (mailbox_id, a_index),
  CONSTRAINT fk_mail_att_mail FOREIGN KEY (mailbox_id)
    REFERENCES mailbox(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'mailbox_attachments');
}

// Equipment snapshot
function create_table_equipment_snapshot(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS equipment_snapshot (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED,
  slot_name VARCHAR(32) NOT NULL,
  item_id INT UNSIGNED,
  link TEXT,
  icon VARCHAR(128),
  ilvl INT UNSIGNED,
  count INT UNSIGNED DEFAULT 1,
  name VARCHAR(128),
  UNIQUE KEY uq_char_slot (character_id, slot_name),
  CONSTRAINT fk_equip_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'equipment_snapshot');
}

// Item lifecycle events
function create_table_item_events(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS item_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  action VARCHAR(24) NOT NULL, -- obtained/sold/destroyed/mailed_...
  source VARCHAR(24),
  location VARCHAR(24), -- bags/vendor/mail/auction_house
  item_id INT UNSIGNED,
  item_string TEXT,
  name VARCHAR(128),
  link TEXT,
  count INT UNSIGNED DEFAULT 1,
  ilvl INT UNSIGNED,
  icon VARCHAR(128),
  sale_price INT UNSIGNED,
  context_json JSON NULL,
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_item_ts_action (character_id, item_id, ts, action),
  CONSTRAINT fk_itemev_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - item obtained/sold/destroyed';
SQL, 'item_events');
}

// Time-series tables
function create_table_series_money(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_money (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  value BIGINT UNSIGNED NOT NULL,
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_sm_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - gold progression';
SQL, 'series_money');
}

function create_table_series_xp(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_xp (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  value INT UNSIGNED NOT NULL,
  max INT UNSIGNED NOT NULL,
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_sx_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - XP progression';
SQL, 'series_xp');
}

function create_table_series_rested(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_rested (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  value INT UNSIGNED NOT NULL,
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_sr_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - rested XP';
SQL, 'series_rested');
}

function create_table_series_level(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_level (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  value INT UNSIGNED NOT NULL,
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_sl_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - level progression';
SQL, 'series_level');
}

function create_table_series_honor(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_honor (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  value INT UNSIGNED NOT NULL,
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_sh_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - honor points';
SQL, 'series_honor');
}

function create_table_series_zones(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_zones (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  zone VARCHAR(128),
  subzone VARCHAR(128),
  hearth VARCHAR(128),
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_sz_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - zone/location tracking';
SQL, 'series_zones');
}

function create_table_series_resource_max(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_resource_max (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  hp INT UNSIGNED,
  mp INT UNSIGNED,
  power_type INT,
  KEY idx_char_ts (character_id, ts),
  CONSTRAINT fk_srm_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'series_resource_max');
}

// Base stats
function create_table_series_base_stats(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_base_stats (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  strength INT,
  agility INT,
  stamina INT,
  intellect INT,
  spirit INT,
  armor INT,
  defense INT,
  resist_arcane INT,
  resist_fire INT,
  resist_frost INT,
  resist_holy INT,
  resist_nature INT,
  resist_shadow INT,
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_sbs_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - base stats and resistances';
SQL, 'series_base_stats');
}

// Spell/Ranged ratings
function create_table_series_spell_ranged(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_spell_ranged (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  ranged_min INT, ranged_max INT, ranged_speed FLOAT, ranged_ap INT, ranged_crit FLOAT,
  heal_bonus INT, spell_penetration INT, mp5_base FLOAT, mp5_cast FLOAT,
  school_arcane_dmg INT, school_arcane_crit FLOAT,
  school_fire_dmg INT, school_fire_crit FLOAT,
  school_frost_dmg INT, school_frost_crit FLOAT,
  school_holy_dmg INT, school_holy_crit FLOAT,
  school_nature_dmg INT, school_nature_crit FLOAT,
  school_shadow_dmg INT, school_shadow_crit FLOAT,
  KEY idx_char_ts (character_id, ts),
  CONSTRAINT fk_ssr_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'series_spell_ranged');
}

// Buffs / Debuffs
function create_table_buffs(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS buffs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  name VARCHAR(128),
  count INT,
  dtype VARCHAR(32),
  duration INT,
  expires_ts INT,
  caster VARCHAR(64),
  KEY idx_char_ts (character_id, ts),
  CONSTRAINT fk_buffs_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'buffs');
}

function create_table_debuffs(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS debuffs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  name VARCHAR(128),
  count INT,
  dtype VARCHAR(32),
  duration INT,
  expires_ts INT,
  caster VARCHAR(64),
  KEY idx_char_ts (character_id, ts),
  CONSTRAINT fk_debuffs_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'debuffs');
}

// Reputation
function create_table_series_reputation(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_reputation (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  faction_name VARCHAR(128),
  standing_id INT,
  value INT,
  min INT, max INT,
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_faction_ts (character_id, faction_name, ts),
  CONSTRAINT fk_srep_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - reputation with factions';
SQL, 'series_reputation');
}

// Skills snapshot
function create_table_skills(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS skills (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(128),
  `rank` INT,
  max_rank INT,
  ts INT UNSIGNED,
  UNIQUE KEY uq_char_skill (character_id, name),
  CONSTRAINT fk_skill_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'skills');
}

// Spellbook / Glyphs / Companions / Pets
function create_table_spellbook_tabs(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS spellbook_tabs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED,
  tab_name VARCHAR(64),
  UNIQUE KEY uq_char_tab (character_id, tab_name),
  CONSTRAINT fk_sbt_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'spellbook_tabs');
}

function create_table_spells(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS spells (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  spellbook_tab_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(128),
  `rank` VARCHAR(32),
  CONSTRAINT fk_spells_tab FOREIGN KEY (spellbook_tab_id)
    REFERENCES spellbook_tabs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'spells');
}

function create_table_glyphs(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS glyphs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED,
  socket INT,
  name VARCHAR(128),
  type VARCHAR(64),
  icon VARCHAR(128),
  spell_id INT,
  UNIQUE KEY uq_char_socket (character_id, socket),
  CONSTRAINT fk_glyphs_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'glyphs');
}

function create_table_companions(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS companions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED,
  type ENUM('MOUNT','CRITTER'),
  name VARCHAR(128),
  icon VARCHAR(128),
  creature_id INT UNSIGNED,
  spell_id INT UNSIGNED,
  active TINYINT(1),
  CONSTRAINT fk_comp_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE,
  KEY idx_char_type (character_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'companions');
}

function create_table_pet_stable(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS pet_stable (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED,
  slot INT,
  name VARCHAR(128),
  level INT,
  icon VARCHAR(128),
  family VARCHAR(64),
  UNIQUE KEY uq_char_slot (character_id, slot),
  CONSTRAINT fk_petstable_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'pet_stable');
}

function create_table_pet_info(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS pet_info (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED,
  name VARCHAR(128),
  family VARCHAR(64),
  xp INT,
  next_xp INT,
  UNIQUE KEY uq_char_pet (character_id, name),
  CONSTRAINT fk_petinfo_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'pet_info');
}

function create_table_pet_spells(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS pet_spells (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED,
  name VARCHAR(128),
  CONSTRAINT fk_petspells_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE,
  KEY idx_char_name (character_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'pet_spells');
}


// Tradeskills + reagents
function create_table_tradeskills(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS tradeskills (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED,
  name VARCHAR(128),
  type VARCHAR(64),
  link TEXT,
  icon VARCHAR(128),
  num_made_min INT,
  num_made_max INT,
  cooldown INT,
  cooldown_text VARCHAR(64),
  profession VARCHAR(64), -- <-- Added column for profession context
  CONSTRAINT fk_ts_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'tradeskills');
}

function create_table_tradeskill_reagents(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS tradeskill_reagents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tradeskill_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(128),
  count_required INT,
  have_count INT,
  link TEXT,
  icon VARCHAR(128),
  CONSTRAINT fk_tsr_ts FOREIGN KEY (tradeskill_id)
    REFERENCES tradeskills(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'tradeskill_reagents');
}


// Talents / dual-spec
function create_table_talents_groups(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS talents_groups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  group_index TINYINT UNSIGNED NOT NULL,
  ts INT UNSIGNED,
  active TINYINT(1) DEFAULT 0,
  UNIQUE KEY uq_char_group (character_id, group_index),
  CONSTRAINT fk_tg_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'talents_groups');
}

function create_table_talents_tabs(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS talents_tabs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  talents_group_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(64),
  icon VARCHAR(128),
  points_spent INT,
  CONSTRAINT fk_tt_group FOREIGN KEY (talents_group_id)
    REFERENCES talents_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'talents_tabs');
}

function create_table_talents(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS talents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  talents_tab_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(128),
  `rank` INT,
  max_rank INT,
  link TEXT,
  talent_id INT,
  CONSTRAINT fk_talent_tab FOREIGN KEY (talents_tab_id)
    REFERENCES talents_tabs(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'talents');
}

// "Time played" session events
function create_table_sessions(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  total_time INT UNSIGNED,
  level_time INT UNSIGNED,
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_sess_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - play sessions';
SQL, 'sessions');
}

// Auction SVs
function create_table_auction_owner_rows(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS auction_owner_rows (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rf_char_key VARCHAR(160) NOT NULL COMMENT 'Format: Realm-Faction:CharName',
  ts INT UNSIGNED NOT NULL COMMENT 'Timestamp when auction was posted',
  item_id INT UNSIGNED NOT NULL COMMENT 'WoW item ID',
  link TEXT COMMENT 'Item link string',
  name VARCHAR(128) NOT NULL COMMENT 'Item name',
  stack_size INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Number of items in stack',
  price_stack BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total price for the stack',
  duration_bucket TINYINT UNSIGNED COMMENT 'Duration code from WoW (1/2/3/4)',
  seller VARCHAR(64) COMMENT 'Character name of seller',
  sold TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Was auction sold',
  sold_ts INT UNSIGNED COMMENT 'Timestamp when sold',
  sold_price BIGINT UNSIGNED COMMENT 'Actual sale price from mail',
  expired TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Did auction expire',
  expired_ts INT UNSIGNED COMMENT 'Timestamp when expired',
  UNIQUE KEY uq_rf_item_ts_stack (rf_char_key, item_id, ts, stack_size),
  KEY idx_rf_ts (rf_char_key, ts),
  KEY idx_sold (rf_char_key, sold, sold_ts),
  KEY idx_expired (rf_char_key, expired, expired_ts),
  KEY idx_active (rf_char_key, sold, expired)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Player auction history from WhoDAT addon';
SQL, 'auction_owner_rows');
}

function create_table_auction_market_ts(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS auction_market_ts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rf_key VARCHAR(128) NOT NULL COMMENT 'Realm-Faction key',
  item_key VARCHAR(64) NOT NULL COMMENT 'ItemID:StackSize',
  ts INT UNSIGNED NOT NULL COMMENT 'Snapshot timestamp',
  my_price_stack BIGINT UNSIGNED COMMENT 'Your price for this stack',
  my_price_item BIGINT UNSIGNED COMMENT 'Your price per item',
  UNIQUE KEY uq_rf_item_ts (rf_key, item_key, ts),
  KEY idx_rf_item (rf_key, item_key),
  KEY idx_ts (ts)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Market snapshot timestamps for auction price tracking';
SQL, 'auction_market_ts');
}

function create_table_auction_market_bands(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS auction_market_bands (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  market_ts_id BIGINT UNSIGNED NOT NULL COMMENT 'FK to auction_market_ts',
  band_type ENUM('LOW', 'HIGH') NOT NULL COMMENT 'Price band category',
  price_stack BIGINT UNSIGNED COMMENT 'Competitor stack price',
  price_item BIGINT UNSIGNED COMMENT 'Competitor per-item price',
  seller VARCHAR(64) COMMENT 'Competitor seller name',
  link TEXT COMMENT 'Item link',
  KEY idx_market_ts (market_ts_id, band_type),
  KEY idx_seller (seller),
  CONSTRAINT fk_market_bands_ts 
    FOREIGN KEY (market_ts_id) 
    REFERENCES auction_market_ts(id) 
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Competitor price bands (LOW/HIGH) for market analysis';
SQL, 'auction_market_bands');
}
// Quest Events Table
function create_table_quest_events(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS quest_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  -- Event type: accepted, abandoned, completed, objective
  kind ENUM('accepted', 'abandoned', 'completed', 'objective') NOT NULL,
  
  -- Quest details
  quest_id INT UNSIGNED,  -- May be quest log index on some servers
  quest_title VARCHAR(255),
  
  -- For objective events
  objective_text TEXT,
  objective_progress SMALLINT UNSIGNED,
  objective_total SMALLINT UNSIGNED,
  objective_complete TINYINT(1),
  
  KEY idx_char_ts (character_id, ts),
  -- UNIQUE constraint: Prevents duplicate events
  -- For objectives: Uses first 255 chars of objective_text
  -- For other events: objective_text is NULL, making the combination unique
  UNIQUE KEY uniq_quest_event (character_id, ts, kind, quest_title, objective_text(255)),

  KEY idx_quest_id (quest_id),
  KEY idx_kind (kind),
  CONSTRAINT fk_qevt_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'quest_events');
}

// Quest Log Snapshots Table
function create_table_quest_log_snapshots(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS quest_log_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  -- Quest details
  quest_id INT UNSIGNED,
  quest_title VARCHAR(255),
  quest_complete TINYINT(1) DEFAULT 0,
  
  -- Objectives as JSON array
  objectives JSON,
  -- UNIQUE constraint: One snapshot per quest per timestamp
  UNIQUE KEY uniq_quest_snapshot (character_id, ts, quest_title),

  
  KEY idx_char_ts (character_id, ts),
  KEY idx_quest_id (quest_id),
  CONSTRAINT fk_qlog_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'quest_log_snapshots');
}
// ───────────────────────────────────────────────────────────────────────────────
// NEW: Sharing infrastructure (account policy, per-character vis, tokens, snapshots, audit)
// ───────────────────────────────────────────────────────────────────────────────

// A. Account-level sharing policy (defaulting and discoverability)
function create_table_account_sharing_policy(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS account_sharing_policy (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  default_visibility ENUM('PRIVATE','UNLISTED','PUBLIC') NOT NULL DEFAULT 'PRIVATE',
  discoverable TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT fk_asp_user FOREIGN KEY (user_id)
    REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_asp_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'account_sharing_policy');
}

// B. Add character-level columns & indexes (visibility, allow_search, normalized lookups)

function add_char_sharing_columns_and_indexes(PDO $pdo): void
{
  // Strict ASCII DDL strings (no smart quotes, no HTML-escaped content)
  $ddlVisibility = "ALTER TABLE `characters` ADD COLUMN `visibility` ENUM('PRIVATE','UNLISTED','PUBLIC') NOT NULL DEFAULT 'PRIVATE'";
  $ddlAllowSearch = "ALTER TABLE `characters` ADD COLUMN `allow_search` TINYINT(1) NOT NULL DEFAULT 0";
  $ddlRealmNorm = "ALTER TABLE `characters` ADD COLUMN `realm_norm` VARCHAR(64) NULL";
  $ddlNameNorm = "ALTER TABLE `characters` ADD COLUMN `name_norm` VARCHAR(64) NULL";

  // Column existence checks (ASCII, no user-provided fragments)
  $colCheck = "
    SELECT 1
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'characters'
      AND COLUMN_NAME  = :col
    LIMIT 1";

  $check = $pdo->prepare($colCheck);

  foreach ([
    ['visibility', $ddlVisibility],
    ['allow_search', $ddlAllowSearch],
    ['realm_norm', $ddlRealmNorm],
    ['name_norm', $ddlNameNorm],
  ] as [$col, $ddl]) {
    $check->execute([':col' => $col]);
    if (!$check->fetchColumn()) {
      $pdo->exec($ddl);
      echo "✅ Added column characters.{$col}\n";
    } else {
      echo "ℹ️ Column characters.{$col} already exists\n";
    }
  }

  // Indexes (same approach—strict ASCII CREATE INDEX)
  ensure_index(
    $pdo,
    'characters',
    'idx_char_public_lookup',
    'CREATE INDEX idx_char_public_lookup ON `characters` (`realm_norm`, `name_norm`, `visibility`, `allow_search`)'
  );

  ensure_index(
    $pdo,
    'characters',
    'idx_char_visibility',
    'CREATE INDEX idx_char_visibility ON `characters` (`visibility`)'
  );

  ensure_index(
    $pdo,
    'characters',
    'idx_char_class',
    'CREATE INDEX idx_char_class ON `characters` (`class_file`)'
  );

  ensure_index(
    $pdo,
    'characters',
    'idx_char_guild',
    'CREATE INDEX idx_char_guild ON `characters` (`guild_name`)'
  );
}

// C. Tokenized share links (unguessable links for non-public/any scope)
function create_table_share_links(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS share_links (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  token CHAR(32) NOT NULL,                 -- hex(random_bytes(16))
  state ENUM('ACTIVE','REVOKED') NOT NULL DEFAULT 'ACTIVE',
  scope_json JSON NOT NULL,                -- whitelist of sections/series
  expires_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  CONSTRAINT fk_share_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE,
  UNIQUE KEY uq_share_token (token),
  KEY idx_share_char (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'share_links');
}

// D. Public snapshot cache (fast render, single lookup)
function create_table_public_snapshots(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS public_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  snapshot_json JSON NOT NULL,
  schema_version INT UNSIGNED NOT NULL,
  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_ps_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE,
  UNIQUE KEY uq_ps_char (character_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'public_snapshots');
}

// E. Optional: audit telemetry (created/view/revoked/rotated/expired)
function create_table_share_events(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS share_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  share_link_id BIGINT UNSIGNED NOT NULL,
  event_type ENUM('CREATED','VIEW','REVOKED','ROTATED','EXPIRED') NOT NULL,
  actor_ip VARBINARY(16) NULL,          -- IPv4/IPv6 packed (optional)
  user_agent VARCHAR(255) NULL,
  at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_se_link FOREIGN KEY (share_link_id)
    REFERENCES share_links(id) ON DELETE CASCADE,
  KEY idx_se_type_time (event_type, at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'share_events');
}

// F. Public discovery view (only PUBLIC + allow_search)
function create_view_v_public_characters(PDO $pdo): void
{
  ensure_view($pdo, 'v_public_characters', <<<SQL
CREATE VIEW v_public_characters AS
SELECT
  c.id, c.realm, c.name, c.realm_norm, c.name_norm,
  c.faction, c.class_file, c.guild_name, c.updated_at
FROM characters AS c
WHERE c.visibility = 'PUBLIC' AND c.allow_search = 1;
SQL);
}

// G. Achievements
function create_table_series_achievements(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_achievements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  achievement_id INT UNSIGNED NOT NULL,
  name VARCHAR(128) NOT NULL,
  description TEXT NULL,
  points INT UNSIGNED NOT NULL DEFAULT 0,
  earned TINYINT(1) NOT NULL DEFAULT 0,
  earned_date INT UNSIGNED NULL,
  UNIQUE KEY uq_char_ach_ts (character_id, achievement_id, ts),
  KEY idx_char_ts (character_id, ts),
  CONSTRAINT fk_sach_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'series_achievements');
}

// H. Attack Metrics
function create_table_series_attack(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_attack (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  ap_base INT,
  ap_pos INT,
  ap_neg INT,
  parry FLOAT,
  mh_speed FLOAT,
  dodge FLOAT,
  block FLOAT,
  crit FLOAT,
  UNIQUE KEY uq_char_ts (character_id, ts),
  KEY idx_char_ts (character_id, ts),
  CONSTRAINT fk_satk_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'series_attack');
}

// I. Currency Counds
function create_table_series_currency(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_currency (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  currency_name VARCHAR(128) NOT NULL,
  count INT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_char_name_ts (character_id, currency_name, ts),
  KEY idx_char_ts (character_id, ts),
  CONSTRAINT fk_sc_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'series_currency');
}

// J. Arena Points
function create_table_series_arena(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS series_arena (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  value INT UNSIGNED NOT NULL,
  UNIQUE KEY uq_char_ts (character_id, ts),
  KEY idx_char_ts (character_id, ts),
  CONSTRAINT fk_sarena_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'series_arena');
}

// ───────────────────────────────────────────────────────────────────────────────
// Orchestrator
// ───────────────────────────────────────────────────────────────────────────────
function create_all_tables(PDO $pdo): void
{
  // Optional auth
  create_table_users($pdo);

  // Identity & characters
  create_table_characters($pdo);
  create_table_character_profile_indexes($pdo);

  // Add sharing columns/indexes on characters
  add_char_sharing_columns_and_indexes($pdo);

  // Catalog & snapshots
  create_table_items_catalog($pdo);
  create_table_containers_bag($pdo);
  create_table_containers_bank($pdo);
  create_table_containers_keyring($pdo);
  create_table_mailbox($pdo);
  create_table_mailbox_attachments($pdo);
  create_table_equipment_snapshot($pdo);

  // Item events
  create_table_item_events($pdo);

  // Core series
  create_table_series_money($pdo);
  create_table_series_xp($pdo);
  create_table_series_rested($pdo);
  create_table_series_level($pdo);
  create_table_series_honor($pdo);
  create_table_series_zones($pdo);
  create_table_series_resource_max($pdo);
  create_table_series_base_stats($pdo);
  create_table_series_spell_ranged($pdo);

  // Buffs / Debuffs
  create_table_buffs($pdo);
  create_table_debuffs($pdo);

  // Reputation & Skills
  create_table_series_reputation($pdo);
  create_table_skills($pdo);

  // Spellbook / Glyphs / Companions / Pets
  create_table_spellbook_tabs($pdo);
  create_table_spells($pdo);
  create_table_glyphs($pdo);
  create_table_companions($pdo);
  create_table_pet_stable($pdo);
  create_table_pet_info($pdo);
  create_table_pet_spells($pdo);

  // Tradeskills
  create_table_tradeskills($pdo);
  create_table_tradeskill_reagents($pdo);

  // Talents & dual-spec
  create_table_talents_groups($pdo);
  create_table_talents_tabs($pdo);
  create_table_talents($pdo);

  // Time played sessions
  create_table_sessions($pdo);

  // Auction
  create_table_auction_owner_rows($pdo);
  create_table_auction_market_ts($pdo);
  create_table_auction_market_bands($pdo);

  // NEW: Sharing infra
  create_table_account_sharing_policy($pdo);
  create_table_share_links($pdo);
  create_table_public_snapshots($pdo);
  create_table_share_events($pdo);

  // View for public discovery
  create_view_v_public_characters($pdo);

  // Backfill normalized names (safe, idempotent)
  backfill_normalized_names($pdo);

  // NEW: Attack, Currency, Achievements, Arena
  create_table_series_attack($pdo);
  create_table_series_currency($pdo);
  create_table_series_achievements($pdo);
  create_table_series_arena($pdo);

  // NEW: Session 5 tracking features
  create_table_deaths($pdo);
  create_table_combat_encounters($pdo);
  create_table_loot_history($pdo);
  create_table_boss_kills($pdo);
  create_table_instance_lockouts($pdo);
  create_table_group_compositions($pdo);
  create_table_friend_list_changes($pdo);
  create_table_ignore_list_changes($pdo);
  create_table_quest_rewards($pdo);
  create_table_quest_events($pdo);
  create_table_quest_log_snapshots($pdo);
  create_table_aura_snapshots($pdo);

  try {
    $pdo->exec("ALTER TABLE `series_base_stats` ADD COLUMN `data_json` JSON NULL");
    echo "✅ Added column series_base_stats.data_json\n";
  } catch (Throwable $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') === false) {
      echo "ℹ️ Could not add column series_base_stats.data_json: " . $e->getMessage() . "\n";
    } else {
      echo "ℹ️ Column series_base_stats.data_json already exists\n";
    }
  }

}

/**
 * Create the character_events table for generic event logging (future-proof, extensible).
 * - Logs arbitrary character events (e.g., spell learned, talent changed, etc.)
 * - Uses JSON for flexible event payloads.
 */
function create_table_character_events(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS character_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    character_id BIGINT UNSIGNED NOT NULL,
    ts INT UNSIGNED NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    event_json JSON,
    KEY idx_char_ts (character_id, ts),
    CONSTRAINT fk_ce_char FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'character_events');
}

// ============================================================================
// NEW TABLES - Session 5 Features (Deaths, Combat, Loot, Social, etc.)
// ============================================================================

// ===========================================================================

function create_table_deaths(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS deaths (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  -- Location
  zone VARCHAR(128),
  subzone VARCHAR(128),
  x FLOAT,
  y FLOAT,
  level TINYINT UNSIGNED,
  
  -- Killer
  killer_name VARCHAR(128),
  killer_type ENUM('npc', 'player', 'pet', 'unknown') DEFAULT 'unknown',
  killer_guid VARCHAR(64),
  
  -- Instance
  in_instance BOOLEAN DEFAULT FALSE,
  instance_name VARCHAR(128),
  instance_type VARCHAR(32),
  instance_difficulty VARCHAR(64),
  
  -- Group
  group_size TINYINT UNSIGNED DEFAULT 1,
  group_type ENUM('solo', 'party', 'raid') DEFAULT 'solo',
  
  -- Combat
  combat_duration FLOAT,
  
  -- Durability
  durability_before FLOAT,
  durability_after FLOAT,
  durability_loss FLOAT,
  
  -- Resurrection
  rez_type ENUM('spirit', 'corpse', 'soulstone', 'class_rez', 'unknown') DEFAULT 'unknown',
  rez_time INT UNSIGNED,
  rez_ts INT UNSIGNED,
  
  KEY idx_char_ts (character_id, ts),
  KEY idx_zone (zone),
  KEY idx_killer (killer_name),
  KEY idx_instance (instance_name),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_death_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - character deaths';
SQL, 'deaths');
}

// ===========================================================================
// COMBAT ENCOUNTERS
// ===========================================================================

function create_table_combat_encounters(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS combat_encounters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  duration FLOAT NOT NULL,
  
  -- Performance metrics
  dps FLOAT,
  hps FLOAT,
  dtps FLOAT,
  overheal_pct FLOAT,
  
  -- Totals
  total_damage BIGINT UNSIGNED,
  total_healing BIGINT UNSIGNED,
  total_overheal BIGINT UNSIGNED,
  total_damage_taken BIGINT UNSIGNED,
  
  -- Context
  target VARCHAR(128),
  target_level TINYINT UNSIGNED,
  is_boss BOOLEAN DEFAULT FALSE,
  
  instance VARCHAR(128),
  instance_difficulty VARCHAR(64),
  
  group_type ENUM('solo', 'party', 'raid') DEFAULT 'solo',
  group_size TINYINT UNSIGNED DEFAULT 1,
  
  zone VARCHAR(128),
  subzone VARCHAR(128),
  
  KEY idx_char_ts (character_id, ts),
  KEY idx_target (target),
  KEY idx_instance (instance),
  KEY idx_boss (is_boss, dps DESC),
  CONSTRAINT fk_combat_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'combat_encounters');
}

// ===========================================================================
// LOOT HISTORY
// ===========================================================================

function create_table_loot_history(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS loot_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  -- Item details
  item_id INT UNSIGNED,
  item_link TEXT,
  item_name VARCHAR(128),
  quality TINYINT UNSIGNED,
  ilvl SMALLINT UNSIGNED,
  icon VARCHAR(128),
  count SMALLINT UNSIGNED DEFAULT 1,
  
  -- Source details
  source_type ENUM('mob', 'boss', 'chest', 'quest', 'vendor', 'craft', 'roll', 'unknown') DEFAULT 'unknown',
  source_name VARCHAR(128),
  source_level TINYINT UNSIGNED,
  is_boss BOOLEAN DEFAULT FALSE,
  
  -- Instance context
  instance VARCHAR(128),
  instance_difficulty VARCHAR(64),
  
  -- Group context
  group_type ENUM('solo', 'party', 'raid') DEFAULT 'solo',
  group_size TINYINT UNSIGNED DEFAULT 1,
  
  -- Location
  zone VARCHAR(128),
  subzone VARCHAR(128),
  
  -- Roll details (if applicable)
  roll_type TINYINT,  -- 1=need, 2=greed, 3=DE
  roll_value TINYINT,
  competitors TINYINT UNSIGNED,
  
  KEY idx_char_ts (character_id, ts),
  KEY idx_item (item_id),
  KEY idx_source (source_name),
  KEY idx_boss (is_boss, quality DESC),
  KEY idx_instance (instance),
  CONSTRAINT fk_loot_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'loot_history');
}

// ===========================================================================
// BOSS KILLS
// ===========================================================================

function create_table_boss_kills(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS boss_kills (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  boss_name VARCHAR(128),
  boss_guid VARCHAR(64),
  
  instance VARCHAR(128),
  instance_type VARCHAR(32),
  difficulty TINYINT UNSIGNED,
  difficulty_name VARCHAR(64),
  
  group_type ENUM('solo', 'party', 'raid') DEFAULT 'raid',
  group_size TINYINT UNSIGNED,
  
  KEY idx_char_ts (character_id, ts),
  KEY idx_boss (boss_name),
  KEY idx_instance (instance, difficulty),
  UNIQUE KEY unique_char_boss_ts (character_id, boss_name, instance, ts),
  CONSTRAINT fk_kill_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - boss kills';
SQL, 'boss_kills');
}

// ===========================================================================
// INSTANCE LOCKOUTS
// ===========================================================================

function create_table_instance_lockouts(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS instance_lockouts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  instance_name VARCHAR(128),
  instance_id INT UNSIGNED,
  difficulty TINYINT UNSIGNED,
  difficulty_name VARCHAR(64),
  is_raid BOOLEAN DEFAULT TRUE,
  max_players TINYINT UNSIGNED,
  
  total_bosses TINYINT UNSIGNED,
  bosses_killed TINYINT UNSIGNED,
  
  reset_time INT UNSIGNED,
  extended BOOLEAN DEFAULT FALSE,
  
  KEY idx_char_ts (character_id, ts),
  KEY idx_instance (instance_name, difficulty),
  KEY idx_reset (reset_time),
  CONSTRAINT fk_lockout_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'instance_lockouts');
}

// ===========================================================================
// GROUP COMPOSITIONS
// ===========================================================================

function create_table_group_compositions(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS group_compositions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  type ENUM('party', 'raid') DEFAULT 'party',
  size TINYINT UNSIGNED,
  
  instance VARCHAR(128),
  instance_difficulty VARCHAR(64),
  
  zone VARCHAR(128),
  subzone VARCHAR(128),
  
  -- Members stored as JSON array
  members JSON,
  
  KEY idx_char_ts (character_id, ts),
  KEY idx_instance (instance),
  UNIQUE KEY unique_char_ts (character_id, ts),
  CONSTRAINT fk_group_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - group/raid compositions';
SQL, 'group_compositions');
}

// ===========================================================================
// FRIEND LIST CHANGES
// ===========================================================================

function create_table_friend_list_changes(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS friend_list_changes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  action ENUM('added', 'removed') NOT NULL,
  friend_name VARCHAR(64),
  friend_level TINYINT UNSIGNED,
  friend_class VARCHAR(32),
  note TEXT,
  
  KEY idx_char_ts (character_id, ts),
  KEY idx_friend (friend_name),
  UNIQUE KEY unique_char_friend_ts (character_id, friend_name, ts),
  CONSTRAINT fk_friend_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - friend list additions/removals';
SQL, 'friend_list_changes');
}

// ===========================================================================
// IGNORE LIST CHANGES
// ===========================================================================

function create_table_ignore_list_changes(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS ignore_list_changes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  action ENUM('added', 'removed') NOT NULL,
  ignored_name VARCHAR(64),
  
  KEY idx_char_ts (character_id, ts),
  UNIQUE KEY unique_char_ignored_ts (character_id, ignored_name, ts),
  CONSTRAINT fk_ignore_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - ignore list additions/removals';
SQL, 'ignore_list_changes');
}

// ===========================================================================
// QUEST REWARDS
// ===========================================================================

function create_table_quest_rewards(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS quest_rewards (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  quest_id INT UNSIGNED,
  quest_title VARCHAR(255),
  quest_level TINYINT UNSIGNED,
  
  -- Chosen reward
  reward_chosen_link TEXT,
  reward_chosen_name VARCHAR(128),
  reward_chosen_quantity SMALLINT UNSIGNED,
  reward_chosen_quality TINYINT UNSIGNED,
  
  -- Other rewards (JSON arrays)
  reward_choices JSON,
  reward_required JSON,
  
  -- Currency/Rep rewards
  money INT UNSIGNED,
  xp INT UNSIGNED,
  honor INT UNSIGNED,
  arena INT UNSIGNED,
  reputation JSON,
  
  -- Context
  zone VARCHAR(128),
  subzone VARCHAR(128),
  -- UNIQUE constraint: One reward per quest completion
  -- ts is the completion timestamp, ensuring no duplicate rewards
  UNIQUE KEY uniq_quest_reward (character_id, ts, quest_title),

  
  KEY idx_char_ts (character_id, ts),
  KEY idx_quest_id (quest_id),
  CONSTRAINT fk_qr_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'quest_rewards');
}

// ===========================================================================
// AURA SNAPSHOTS
// ===========================================================================

function create_table_aura_snapshots(PDO $pdo): void
{
  run_ddl($pdo, <<<SQL
CREATE TABLE IF NOT EXISTS aura_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id BIGINT UNSIGNED NOT NULL,
  ts INT UNSIGNED NOT NULL,
  
  context ENUM('zone_change', 'combat_start', 'combat_end', 'manual') DEFAULT 'manual',
  
  buff_count TINYINT UNSIGNED DEFAULT 0,
  debuff_count TINYINT UNSIGNED DEFAULT 0,
  
  -- Actual auras stored as JSON
  buffs JSON,
  debuffs JSON,
  
  KEY idx_char_ts (character_id, ts),
  CONSTRAINT fk_aura_char FOREIGN KEY (character_id)
    REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL, 'aura_snapshots');
}


// CLI convenience
if (PHP_SAPI === 'cli') {
  create_all_tables($pdo);
  echo "🎉 Schema initialization complete.\n";
}

// ───────────────────────────────────────────────────────────────────────────────
// Web UI (button-driven; no actions on load)
// ───────────────────────────────────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
  // Minimal CSRF
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
  }
  if (empty($_SESSION['sql_csrf'])) {
    $_SESSION['sql_csrf'] = bin2hex(random_bytes(16));
  }
  // Start output buffering to avoid "headers already sent"
  ob_start();

  // Drop-all helper (nuclear; drops every base table and views)
  function drop_all_tables(PDO $pdo): void
  {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    // Drop views first to avoid dependencies
    $stmtV = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
    $stmtV->setFetchMode(PDO::FETCH_NUM);
    foreach ($stmtV as $row) {
      $view = $row[0];
      $pdo->exec("DROP VIEW IF EXISTS `{$view}`");
    }
    // Then drop base tables
    $stmtT = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $stmtT->setFetchMode(PDO::FETCH_NUM);
    foreach ($stmtT as $row) {
      $table = $row[0];
      $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
    }
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
  }

  // Handle POST
  $message = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $csrf = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['sql_csrf'], $csrf)) {
      $message = 'CSRF token mismatch.';
    } else {
      try {
        if ($action === 'create') {
          create_all_tables($pdo);
          $message = '✅ Schema initialized successfully.';
        } elseif ($action === 'drop') {
          drop_all_tables($pdo);
          $message = '🗑️ All tables and views dropped (FOREIGN_KEY_CHECKS respected).';
        } elseif ($action === 'backfill_norm') {
          backfill_normalized_names($pdo);
          $message = '✅ Normalized names backfilled.';
        } else {
          $message = 'No action taken.';
        }
      } catch (Throwable $e) {
        $message = '❌ Error: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
      }
    }
  }
  ?>
  <!doctype html>
  <html lang="en">

  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>WhoDASH DB Setup</title>
    <style>
      :root {
        color-scheme: light dark;
      }

      body {
        font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
        margin: 2rem;
      }

      .card {
        max-width: 800px;
        border: 1px solid #8882;
        border-radius: 10px;
        padding: 1.25rem;
      }

      h1 {
        margin: 0 0 0.5rem 0;
      }

      form {
        display: inline-block;
        margin-right: 1rem;
      }

      button {
        padding: 0.6rem 1rem;
        border-radius: 8px;
        border: 1px solid #aaa7;
        cursor: pointer;
      }

      .danger {
        background: #ffebe9;
        border-color: #d32f2f;
        color: #8b0000;
      }

      .ok {
        background: #e9f7ef;
        border-color: #2e7d32;
        color: #1b5e20;
      }

      .msg {
        margin-top: 1rem;
        white-space: pre-wrap;
      }

      .muted {
        color: #666;
        font-size: 0.9rem;
        margin-top: 0.25rem;
      }

      .grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.75rem;
        margin-top: 1rem;
      }

      code {
        font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
      }
    </style>
  </head>

  <body>
    <div class="card">
      <h1>WhoDASH Database Setup</h1>
      <div class="muted">This page only runs actions when you click a button. Nothing runs on load.</div>
      <div class="grid">
        <!-- Create Schema -->
        <form method="post">
          <input type="hidden" name="csrf"
            value="<?php echo htmlspecialchars($_SESSION['sql_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="create">
          <button type="submit">🚀 Create Schema</button>
          <div class="muted">Calls <code>create_all_tables($pdo)</code>, adds sharing infra, creates view, backfills
            normalization.</div>
        </form>
        <!-- Drop All Tables -->
        <form method="post" onsubmit="return confirm('This will DROP ALL TABLES and VIEWS in the database. Continue?');">
          <input type="hidden" name="csrf"
            value="<?php echo htmlspecialchars($_SESSION['sql_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="drop">
          <button type="submit" class="danger">🗑️ Drop All Tables</button>
          <div class="muted">Disables FK checks, drops everything, re-enables FK.</div>
        </form>
        <!-- Backfill Normalized Names -->
        <form method="post">
          <input type="hidden" name="csrf"
            value="<?php echo htmlspecialchars($_SESSION['sql_csrf'], ENT_QUOTES, 'UTF-8'); ?>">
          <input type="hidden" name="action" value="backfill_norm">
          <button type="submit">🛠️ Backfill Normalized Names</button>
          <div class="muted">Updates <code>realm_norm</code> / <code>name_norm</code> for characters lacking values.</div>
        </form>
      </div>

      <?php if ($message): ?>
        <?php
        $cls = starts_with($message, '✅') ? 'ok' :
          (starts_with($message, '🗑️') ? 'danger' : '');
        ?>
        <div class="msg <?php echo $cls; ?>">
          <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <div class="muted" style="margin-top:1rem">
        DB: <code><?php echo htmlspecialchars($db, ENT_QUOTES, 'UTF-8'); ?></code>
        on host <code><?php echo htmlspecialchars($host, ENT_QUOTES, 'UTF-8'); ?></code>
      </div>
    </div>
  </body>

  </html>
  <?php
  // Flush buffered output at the end of the web UI block
  ob_end_flush();
}