-- MySQL dump 10.13  Distrib 8.0.43, for Linux (x86_64)
--
-- Host: localhost    Database: whodat
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `account_sharing_policy`
--

DROP TABLE IF EXISTS `account_sharing_policy`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_sharing_policy` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `default_visibility` enum('PRIVATE','UNLISTED','PUBLIC') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRIVATE',
  `discoverable` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asp_user` (`user_id`),
  CONSTRAINT `fk_asp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `api_keys_active`
--

DROP TABLE IF EXISTS `api_keys_active`;
/*!50001 DROP VIEW IF EXISTS `api_keys_active`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `api_keys_active` AS SELECT 
 1 AS `id`,
 1 AS `user_id`,
 1 AS `key_preview`,
 1 AS `key_name`,
 1 AS `created_at`,
 1 AS `last_used_at`,
 1 AS `expires_at`,
 1 AS `status`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `auction_market_bands`
--

DROP TABLE IF EXISTS `auction_market_bands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auction_market_bands` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `market_ts_id` bigint unsigned NOT NULL,
  `band_type` enum('LOW','HIGH') COLLATE utf8mb4_unicode_ci NOT NULL,
  `price_stack` bigint unsigned DEFAULT NULL,
  `price_item` bigint unsigned DEFAULT NULL,
  `seller` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `fk_market_band` (`market_ts_id`),
  CONSTRAINT `fk_market_band` FOREIGN KEY (`market_ts_id`) REFERENCES `auction_market_ts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=68763 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auction_market_ts`
--

DROP TABLE IF EXISTS `auction_market_ts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auction_market_ts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rf_key` varchar(96) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ts` int unsigned NOT NULL,
  `my_price_stack` bigint unsigned DEFAULT NULL,
  `my_price_item` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rf_item_ts` (`rf_key`,`item_key`,`ts`)
) ENGINE=InnoDB AUTO_INCREMENT=19209 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auction_owner_rows`
--

DROP TABLE IF EXISTS `auction_owner_rows`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `auction_owner_rows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `rf_char_key` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stack_size` int unsigned DEFAULT NULL,
  `price_stack` bigint unsigned DEFAULT NULL,
  `duration_bucket` tinyint unsigned DEFAULT NULL,
  `seller` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sold` tinyint(1) DEFAULT '0',
  `sold_ts` int unsigned DEFAULT NULL,
  `sold_price` bigint unsigned DEFAULT NULL COMMENT 'Actual sale price from mail',
  `expired` tinyint(1) DEFAULT '0' COMMENT 'Did auction expire',
  `expired_ts` int unsigned DEFAULT NULL COMMENT 'When auction expired',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rf_item_ts_stack` (`rf_char_key`,`item_id`,`ts`,`stack_size`),
  KEY `idx_rf_ts` (`rf_char_key`,`ts`),
  KEY `idx_sold` (`rf_char_key`,`sold`,`sold_ts`)
) ENGINE=InnoDB AUTO_INCREMENT=7744 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `aura_snapshots`
--

DROP TABLE IF EXISTS `aura_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aura_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `context` enum('zone_change','combat_start','combat_end','manual') COLLATE utf8mb4_unicode_ci DEFAULT 'manual',
  `buff_count` tinyint unsigned DEFAULT '0',
  `debuff_count` tinyint unsigned DEFAULT '0',
  `buffs` json DEFAULT NULL,
  `debuffs` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_aura_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_alert_notifications`
--

DROP TABLE IF EXISTS `bazaar_alert_notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_alert_notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `alert_id` bigint unsigned NOT NULL,
  `user_id` int unsigned NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `alert_data` json DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_created` (`created_at`),
  KEY `alert_id` (`alert_id`),
  CONSTRAINT `bazaar_alert_notifications_ibfk_1` FOREIGN KEY (`alert_id`) REFERENCES `bazaar_auction_alerts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bazaar_alert_notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_auction_alerts`
--

DROP TABLE IF EXISTS `bazaar_auction_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_auction_alerts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `alert_type` enum('price_drop','expiring_soon','undercut','deal_found','bidding_war') COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `character_id` bigint unsigned DEFAULT NULL,
  `threshold_value` int DEFAULT NULL,
  `enabled` tinyint(1) DEFAULT '1',
  `last_triggered` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_enabled` (`user_id`,`enabled`),
  KEY `idx_item` (`item_name`),
  KEY `idx_user_type_enabled` (`user_id`,`alert_type`,`enabled`),
  KEY `character_id` (`character_id`),
  CONSTRAINT `bazaar_auction_alerts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bazaar_auction_alerts_ibfk_2` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_craft_notes`
--

DROP TABLE IF EXISTS `bazaar_craft_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_craft_notes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `recipe_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `is_favorite` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_recipe` (`user_id`,`recipe_name`),
  CONSTRAINT `bazaar_craft_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_price_history`
--

DROP TABLE IF EXISTS `bazaar_price_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_price_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avg_price` int NOT NULL,
  `min_price` int NOT NULL,
  `max_price` int NOT NULL,
  `sale_count` int NOT NULL DEFAULT '0',
  `recorded_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_item` (`user_id`,`item_name`),
  KEY `idx_date` (`recorded_date`),
  KEY `idx_item_date` (`item_name`,`recorded_date`),
  CONSTRAINT `bazaar_price_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_price_watch`
--

DROP TABLE IF EXISTS `bazaar_price_watch`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_price_watch` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_price` int NOT NULL,
  `notify_on_drop` tinyint(1) DEFAULT '1',
  `notify_on_rise` tinyint(1) DEFAULT '0',
  `enabled` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_item` (`user_id`,`item_name`),
  CONSTRAINT `bazaar_price_watch_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_profession_cooldowns`
--

DROP TABLE IF EXISTS `bazaar_profession_cooldowns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_profession_cooldowns` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `profession_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cooldown_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cooldown_expires` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_char_prof` (`character_id`,`profession_name`),
  KEY `idx_expires` (`cooldown_expires`),
  CONSTRAINT `bazaar_profession_cooldowns_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_saved_comparisons`
--

DROP TABLE IF EXISTS `bazaar_saved_comparisons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_saved_comparisons` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `comparison_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `character_ids` json NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_viewed` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `bazaar_saved_comparisons_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_ticket_goals`
--

DROP TABLE IF EXISTS `bazaar_ticket_goals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_ticket_goals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `goal_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_tickets` int NOT NULL,
  `current_tickets` int NOT NULL DEFAULT '0',
  `goal_description` text COLLATE utf8mb4_unicode_ci,
  `completed` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `bazaar_ticket_goals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_tickets`
--

DROP TABLE IF EXISTS `bazaar_tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `character_id` bigint unsigned NOT NULL,
  `activity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tickets_earned` int NOT NULL DEFAULT '0',
  `activity_description` text COLLATE utf8mb4_unicode_ci,
  `earned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_char` (`user_id`,`character_id`),
  KEY `idx_earned_at` (`earned_at`),
  KEY `idx_activity_type` (`activity_type`),
  KEY `character_id` (`character_id`),
  CONSTRAINT `bazaar_tickets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bazaar_tickets_ibfk_2` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_transfer_queue`
--

DROP TABLE IF EXISTS `bazaar_transfer_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_transfer_queue` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `from_character_id` bigint unsigned NOT NULL,
  `to_character_id` bigint unsigned NOT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` bigint unsigned DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `priority` int NOT NULL DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` enum('planned','in_transit','completed','cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'planned',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_status` (`user_id`,`status`),
  KEY `idx_from_char` (`from_character_id`),
  KEY `idx_to_char` (`to_character_id`),
  CONSTRAINT `bazaar_transfer_queue_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bazaar_transfer_queue_ibfk_2` FOREIGN KEY (`from_character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bazaar_transfer_queue_ibfk_3` FOREIGN KEY (`to_character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_user_preferences`
--

DROP TABLE IF EXISTS `bazaar_user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_user_preferences` (
  `user_id` int unsigned NOT NULL,
  `default_faction` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'xfaction',
  `theme_mode` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'auto',
  `faire_lights_enabled` tinyint(1) DEFAULT '1',
  `notifications_enabled` tinyint(1) DEFAULT '1',
  `auto_refresh_interval` int DEFAULT '300',
  `preferences` json DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `bazaar_user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bazaar_wealth_history`
--

DROP TABLE IF EXISTS `bazaar_wealth_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bazaar_wealth_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `character_id` bigint unsigned DEFAULT NULL,
  `total_wealth` int NOT NULL DEFAULT '0',
  `recorded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`user_id`,`recorded_at`),
  KEY `idx_char_time` (`character_id`,`recorded_at`),
  CONSTRAINT `bazaar_wealth_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bazaar_wealth_history_ibfk_2` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `boss_kills`
--

DROP TABLE IF EXISTS `boss_kills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `boss_kills` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `boss_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `boss_guid` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instance` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instance_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `difficulty` tinyint unsigned DEFAULT NULL,
  `difficulty_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_type` enum('solo','party','raid') COLLATE utf8mb4_unicode_ci DEFAULT 'raid',
  `group_size` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_boss_ts` (`character_id`,`boss_name`,`instance`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_boss` (`boss_name`),
  KEY `idx_instance` (`instance`,`difficulty`),
  CONSTRAINT `fk_kill_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=506 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - boss kills';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `buffs`
--

DROP TABLE IF EXISTS `buffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `buffs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count` int DEFAULT NULL,
  `dtype` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` int DEFAULT NULL,
  `expires_ts` int DEFAULT NULL,
  `caster` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_buffs_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `character_grudge_list`
--

DROP TABLE IF EXISTS `character_grudge_list`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `character_grudge_list` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `player_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `added_at` int unsigned NOT NULL COMMENT 'Unix timestamp when added to grudge list',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_player` (`character_id`,`player_name`),
  KEY `idx_char_added` (`character_id`,`added_at`),
  CONSTRAINT `fk_grudge_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Persistent grudge list — players the user wants to track per character';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `character_guilds`
--

DROP TABLE IF EXISTS `character_guilds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `character_guilds` (
  `character_id` bigint unsigned NOT NULL,
  `guild_id` int NOT NULL,
  `joined_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `is_current` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`character_id`,`guild_id`),
  KEY `guild_id` (`guild_id`),
  CONSTRAINT `character_guilds_ibfk_1` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `character_guilds_ibfk_2` FOREIGN KEY (`guild_id`) REFERENCES `guilds` (`guild_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `characters`
--

DROP TABLE IF EXISTS `characters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `characters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_key` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Format: Realm:Name:Class (e.g., Icecrown:Belmont:WARRIOR)',
  `user_id` int unsigned DEFAULT NULL,
  `realm` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `faction` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class_local` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class_file` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `race` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `race_file` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sex` tinyint unsigned DEFAULT NULL,
  `locale` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_relic_slot` tinyint(1) DEFAULT NULL,
  `last_login_ts` int unsigned DEFAULT NULL,
  `addon_name` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addon_author` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `addon_version` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guild_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guild_rank` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guild_rank_index` int unsigned DEFAULT NULL,
  `guild_members` int unsigned DEFAULT NULL,
  `schema_version` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  `visibility` enum('PRIVATE','UNLISTED','PUBLIC') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'PRIVATE',
  `allow_search` tinyint(1) NOT NULL DEFAULT '0',
  `realm_norm` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name_norm` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_slug` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Human-readable URL slug for public sharing (e.g., icecrown-belmont)',
  `show_currencies` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether to show currencies tab on public profile',
  `show_items` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether to show items tab on public profile',
  `show_social` tinyint(1) DEFAULT '0',
  `guild_id` bigint unsigned DEFAULT NULL,
  `is_bank_alt` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Owner-set bank alt flag (independent of guild)',
  `bank_alt_screenshot` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Relative path to uploaded bank alt banner screenshot',
  PRIMARY KEY (`id`),
  UNIQUE KEY `character_key` (`character_key`),
  UNIQUE KEY `public_slug` (`public_slug`),
  KEY `fk_char_user` (`user_id`),
  KEY `idx_char_realm_name` (`realm`,`name`),
  KEY `idx_char_updated_at` (`updated_at`),
  KEY `idx_char_public_lookup` (`realm_norm`,`name_norm`,`visibility`,`allow_search`),
  KEY `idx_char_visibility` (`visibility`),
  KEY `idx_char_class` (`class_file`),
  KEY `idx_char_guild` (`guild_name`),
  KEY `idx_public_slug` (`public_slug`),
  KEY `idx_guild_id` (`guild_id`),
  CONSTRAINT `fk_char_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=673 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `combat_encounters`
--

DROP TABLE IF EXISTS `combat_encounters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `combat_encounters` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `duration` float NOT NULL,
  `dps` float DEFAULT NULL,
  `hps` float DEFAULT NULL,
  `dtps` float DEFAULT NULL,
  `overheal_pct` float DEFAULT NULL,
  `total_damage` bigint unsigned DEFAULT NULL,
  `total_healing` bigint unsigned DEFAULT NULL,
  `total_overheal` bigint unsigned DEFAULT NULL,
  `total_damage_taken` bigint unsigned DEFAULT NULL,
  `target` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `target_level` tinyint unsigned DEFAULT NULL,
  `is_boss` tinyint(1) DEFAULT '0',
  `instance` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instance_difficulty` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_type` enum('solo','party','raid') COLLATE utf8mb4_unicode_ci DEFAULT 'solo',
  `group_size` tinyint unsigned DEFAULT '1',
  `zone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subzone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_target` (`target`),
  KEY `idx_instance` (`instance`),
  KEY `idx_boss` (`is_boss`,`dps` DESC),
  CONSTRAINT `fk_combat_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36308 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `companions`
--

DROP TABLE IF EXISTS `companions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `companions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `type` enum('MOUNT','CRITTER') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creature_id` int unsigned DEFAULT NULL,
  `spell_id` int unsigned DEFAULT NULL,
  `active` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_type` (`character_id`,`type`),
  CONSTRAINT `fk_comp_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2251 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `containers_bag`
--

DROP TABLE IF EXISTS `containers_bag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `containers_bag` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `bag_id` int NOT NULL,
  `slot` int NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `item_string` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `count` int unsigned DEFAULT '1',
  `ilvl` int unsigned DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quality` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_bag_slot` (`character_id`,`bag_id`,`slot`),
  KEY `idx_bag_quality` (`character_id`,`quality`),
  CONSTRAINT `fk_bag_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8801 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `containers_bank`
--

DROP TABLE IF EXISTS `containers_bank`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `containers_bank` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `bag_id` int DEFAULT '0' COMMENT 'Bank container ID: -1=main bank, 5-11=bank bags',
  `inv_slot` int NOT NULL,
  `container_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT 'Bank' COMMENT 'Container display name',
  `ts` int unsigned DEFAULT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `item_string` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `count` int unsigned DEFAULT '1',
  `ilvl` int unsigned DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quality` tinyint unsigned DEFAULT NULL COMMENT 'Item quality (0-5)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_bag_invslot` (`character_id`,`bag_id`,`inv_slot`),
  KEY `idx_bank_container` (`character_id`,`bag_id`),
  CONSTRAINT `fk_bank_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4256 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `containers_keyring`
--

DROP TABLE IF EXISTS `containers_keyring`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `containers_keyring` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `slot` int NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `item_string` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `count` int unsigned DEFAULT '1',
  `ilvl` int unsigned DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quality` tinyint unsigned DEFAULT NULL COMMENT 'Item quality (0-5)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_key_slot` (`character_id`,`slot`),
  CONSTRAINT `fk_key_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `deaths`
--

DROP TABLE IF EXISTS `deaths`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `deaths` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `zone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subzone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `x` float DEFAULT NULL,
  `y` float DEFAULT NULL,
  `level` tinyint unsigned DEFAULT NULL,
  `killer_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `killer_type` enum('npc','player','pet','environmental','unknown') COLLATE utf8mb4_unicode_ci DEFAULT 'unknown',
  `killer_guid` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `killer_method` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Detection method from tracker',
  `killer_confidence` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT 'none' COMMENT 'Confidence in killer identification',
  `killer_spell` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Spell or ability that killed player',
  `killer_damage` int unsigned DEFAULT NULL COMMENT 'Damage from killing blow',
  `total_damage` int unsigned DEFAULT NULL COMMENT 'Total damage from primary killer (weighted analysis)',
  `hits` int unsigned DEFAULT NULL COMMENT 'Number of hits from primary killer (weighted analysis)',
  `primary_spell` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Most used spell by killer (weighted analysis)',
  `killing_blow` json DEFAULT NULL COMMENT 'Detailed killing blow: {attacker, spell, damage, overkill}',
  `recent_attackers` json DEFAULT NULL COMMENT 'All attackers in last 30s with damage/spell details',
  `attacker_count` tinyint unsigned DEFAULT '0' COMMENT 'Number of different attackers',
  `in_instance` tinyint(1) DEFAULT '0',
  `instance_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instance_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instance_difficulty` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_size` tinyint unsigned DEFAULT '1',
  `group_type` enum('solo','party','raid') COLLATE utf8mb4_unicode_ci DEFAULT 'solo',
  `combat_duration` float DEFAULT NULL,
  `in_combat_at_death` tinyint(1) DEFAULT '1' COMMENT 'Was player in combat when they died',
  `durability_before` float DEFAULT NULL,
  `durability_after` float DEFAULT NULL,
  `durability_loss` float DEFAULT NULL,
  `active_debuffs` json DEFAULT NULL COMMENT 'Debuffs active at time of death: {name: spellId}',
  `rez_type` enum('spirit','corpse','soulstone','class_rez','unknown') COLLATE utf8mb4_unicode_ci DEFAULT 'unknown',
  `rez_time` int unsigned DEFAULT NULL,
  `rez_ts` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_zone` (`zone`),
  KEY `idx_killer` (`killer_name`),
  KEY `idx_instance` (`instance_name`),
  KEY `idx_confidence` (`killer_confidence`),
  KEY `idx_method` (`killer_method`),
  KEY `idx_killer_type` (`killer_type`),
  KEY `idx_attacker_count` (`attacker_count`),
  CONSTRAINT `fk_death_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=750 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - character deaths';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `debuffs`
--

DROP TABLE IF EXISTS `debuffs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `debuffs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count` int DEFAULT NULL,
  `dtype` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration` int DEFAULT NULL,
  `expires_ts` int DEFAULT NULL,
  `caster` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_debuffs_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `equipment_snapshot`
--

DROP TABLE IF EXISTS `equipment_snapshot`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipment_snapshot` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `slot_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ilvl` int unsigned DEFAULT NULL,
  `quality` tinyint unsigned DEFAULT NULL COMMENT 'Item quality (0-7: Poor, Common, Uncommon, Rare, Epic, Legendary, Artifact, Heirloom)',
  `count` int unsigned DEFAULT '1',
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_slot` (`character_id`,`slot_name`),
  KEY `idx_equipment_quality` (`quality`),
  CONSTRAINT `fk_equip_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5385 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `friend_list_changes`
--

DROP TABLE IF EXISTS `friend_list_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `friend_list_changes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `action` enum('added','removed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `friend_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `friend_level` tinyint unsigned DEFAULT NULL,
  `friend_class` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_friend_ts` (`character_id`,`friend_name`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_friend` (`friend_name`),
  CONSTRAINT `fk_friend_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - friend list additions/removals';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `glyphs`
--

DROP TABLE IF EXISTS `glyphs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `glyphs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `socket` int DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spell_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_socket` (`character_id`,`socket`),
  CONSTRAINT `fk_glyphs_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_compositions`
--

DROP TABLE IF EXISTS `group_compositions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_compositions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `type` enum('party','raid') COLLATE utf8mb4_unicode_ci DEFAULT 'party',
  `size` tinyint unsigned DEFAULT NULL,
  `instance` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instance_difficulty` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subzone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `members` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_instance` (`instance`),
  CONSTRAINT `fk_group_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1219 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - group/raid compositions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `group_compositions_backup`
--

DROP TABLE IF EXISTS `group_compositions_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `group_compositions_backup` (
  `id` bigint unsigned NOT NULL DEFAULT '0',
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `type` enum('party','raid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'party',
  `size` tinyint unsigned DEFAULT NULL,
  `instance` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instance_difficulty` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subzone` varchar(128) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `members` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_bank_alts`
--

DROP TABLE IF EXISTS `guild_bank_alts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_bank_alts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `guild_id` int NOT NULL,
  `character_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bank_alt` (`guild_id`,`character_id`),
  KEY `idx_guild_id` (`guild_id`),
  KEY `idx_character_id` (`character_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_bank_items`
--

DROP TABLE IF EXISTS `guild_bank_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_bank_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `snapshot_id` bigint unsigned NOT NULL,
  `guild_id` int DEFAULT NULL,
  `tab_index` tinyint unsigned DEFAULT NULL,
  `tab_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tab_icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `slot_index` tinyint unsigned DEFAULT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_link` text COLLATE utf8mb4_unicode_ci,
  `quality` tinyint unsigned DEFAULT '0',
  `ilvl` smallint unsigned DEFAULT '0',
  `count` smallint unsigned DEFAULT '1',
  `icon` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `locked` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_snapshot` (`snapshot_id`),
  KEY `idx_item` (`item_id`),
  KEY `idx_tab` (`tab_index`),
  KEY `idx_quality` (`quality`),
  KEY `idx_item_name` (`item_name`),
  KEY `idx_guild_id` (`guild_id`),
  KEY `idx_tab_icon` (`tab_icon`),
  CONSTRAINT `fk_bank_items_snap` FOREIGN KEY (`snapshot_id`) REFERENCES `guild_bank_snapshots` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2223 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_bank_money_logs`
--

DROP TABLE IF EXISTS `guild_bank_money_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_bank_money_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `guild_id` int DEFAULT NULL,
  `ts` int unsigned NOT NULL,
  `type` enum('deposit','withdraw','repair','withdrawForTab') COLLATE utf8mb4_unicode_ci NOT NULL,
  `player_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_copper` bigint unsigned NOT NULL,
  `year` smallint unsigned DEFAULT NULL,
  `month` tinyint unsigned DEFAULT NULL,
  `day` tinyint unsigned DEFAULT NULL,
  `hour` tinyint unsigned DEFAULT NULL,
  `transaction_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_ts` int unsigned DEFAULT NULL COMMENT 'Unix timestamp when scan was performed',
  `years_ago` smallint unsigned DEFAULT NULL COMMENT 'Years ago value from WoW API',
  `months_ago` smallint unsigned DEFAULT NULL COMMENT 'Months ago value from WoW API',
  `days_ago` smallint unsigned DEFAULT NULL COMMENT 'Days ago value from WoW API',
  `hours_ago` smallint unsigned DEFAULT NULL COMMENT 'Hours ago value from WoW API',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_money_transaction` (`guild_id`,`transaction_hash`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_player` (`player_name`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`year`,`month`,`day`),
  KEY `idx_ts` (`ts`),
  KEY `idx_player_ts` (`player_name`,`ts`),
  KEY `idx_type_ts` (`type`,`ts`),
  KEY `idx_guild_id` (`guild_id`),
  KEY `idx_hash` (`transaction_hash`),
  CONSTRAINT `fk_money_log_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=495 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_bank_snapshots`
--

DROP TABLE IF EXISTS `guild_bank_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_bank_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `guild_id` int DEFAULT NULL,
  `snapshot_ts` int unsigned NOT NULL,
  `money_copper` bigint unsigned DEFAULT '0',
  `num_tabs` tinyint unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`snapshot_ts`),
  KEY `idx_snapshot_ts` (`snapshot_ts`),
  KEY `idx_guild_id` (`guild_id`),
  CONSTRAINT `fk_bank_snap_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_bank_transaction_logs`
--

DROP TABLE IF EXISTS `guild_bank_transaction_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_bank_transaction_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `guild_id` int DEFAULT NULL,
  `ts` int unsigned NOT NULL,
  `type` enum('deposit','withdraw','move','repair') COLLATE utf8mb4_unicode_ci NOT NULL,
  `player_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_link` text COLLATE utf8mb4_unicode_ci,
  `count` smallint unsigned DEFAULT '1',
  `tab` tinyint unsigned DEFAULT NULL,
  `tab_from` tinyint unsigned DEFAULT NULL,
  `tab_to` tinyint unsigned DEFAULT NULL,
  `year` smallint unsigned DEFAULT NULL,
  `month` tinyint unsigned DEFAULT NULL,
  `day` tinyint unsigned DEFAULT NULL,
  `hour` tinyint unsigned DEFAULT NULL,
  `transaction_hash` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `scan_ts` int unsigned DEFAULT NULL COMMENT 'Unix timestamp when scan was performed',
  `years_ago` smallint unsigned DEFAULT NULL COMMENT 'Years ago value from WoW API',
  `months_ago` smallint unsigned DEFAULT NULL COMMENT 'Months ago value from WoW API',
  `days_ago` smallint unsigned DEFAULT NULL COMMENT 'Days ago value from WoW API',
  `hours_ago` smallint unsigned DEFAULT NULL COMMENT 'Hours ago value from WoW API',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_item_transaction` (`character_id`,`player_name`,`type`,`item_id`,`count`,`tab`,`year`,`month`,`day`,`hour`),
  UNIQUE KEY `unique_transaction` (`guild_id`,`transaction_hash`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_player` (`player_name`),
  KEY `idx_item` (`item_id`),
  KEY `idx_type` (`type`),
  KEY `idx_date` (`year`,`month`,`day`),
  KEY `idx_ts` (`ts`),
  KEY `idx_player_ts` (`player_name`,`ts`),
  KEY `idx_type_ts` (`type`,`ts`),
  KEY `idx_guild_id` (`guild_id`),
  KEY `idx_hash` (`transaction_hash`),
  CONSTRAINT `fk_trans_log_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1033 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_events`
--

DROP TABLE IF EXISTS `guild_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `guild_id` int DEFAULT NULL,
  `event_ts` int unsigned NOT NULL,
  `event_type` enum('roster_change','money_change','bank_change') COLLATE utf8mb4_unicode_ci NOT NULL,
  `num_added` smallint unsigned DEFAULT NULL,
  `num_removed` smallint unsigned DEFAULT NULL,
  `added_members` json DEFAULT NULL,
  `removed_members` json DEFAULT NULL,
  `old_amount` bigint unsigned DEFAULT NULL,
  `new_amount` bigint unsigned DEFAULT NULL,
  `amount_delta` bigint DEFAULT NULL,
  `items_changed` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`event_ts`),
  KEY `idx_type` (`event_type`),
  KEY `idx_char_type_ts` (`character_id`,`event_type`,`event_ts`),
  KEY `idx_event_ts` (`event_ts`),
  KEY `idx_guild_id` (`guild_id`),
  CONSTRAINT `fk_guild_events_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_info_old`
--

DROP TABLE IF EXISTS `guild_info_old`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_info_old` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `guild_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_rank` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_rank_index` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_guild_name` (`guild_name`),
  CONSTRAINT `fk_guild_info_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_info_snapshots`
--

DROP TABLE IF EXISTS `guild_info_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_info_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `snapshot_ts` int unsigned NOT NULL,
  `guild_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_rank` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `player_rank_index` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`snapshot_ts`),
  KEY `idx_guild` (`guild_name`),
  KEY `idx_snapshot_ts` (`snapshot_ts`),
  CONSTRAINT `fk_guild_info_snapshots_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_roster_members`
--

DROP TABLE IF EXISTS `guild_roster_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_roster_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `guild_id` int DEFAULT NULL,
  `snapshot_ts` int unsigned NOT NULL,
  `member_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `member_full_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rank` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rank_index` tinyint unsigned DEFAULT NULL,
  `level` tinyint unsigned DEFAULT NULL,
  `class` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `class_file` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `zone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `officer_note` text COLLATE utf8mb4_unicode_ci,
  `online` tinyint(1) DEFAULT '0',
  `status` tinyint unsigned DEFAULT '0',
  `achievement_points` int unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_char_snapshot` (`character_id`,`snapshot_ts`),
  KEY `idx_member` (`member_name`),
  KEY `idx_rank` (`rank_index`),
  KEY `idx_class` (`class_file`),
  KEY `idx_online` (`online`,`snapshot_ts`),
  KEY `idx_snapshot_ts` (`snapshot_ts`),
  KEY `idx_guild_id` (`guild_id`),
  CONSTRAINT `fk_roster_members_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=477 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guild_roster_snapshots`
--

DROP TABLE IF EXISTS `guild_roster_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guild_roster_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `guild_id` int DEFAULT NULL,
  `snapshot_ts` int unsigned NOT NULL,
  `num_members` smallint unsigned DEFAULT NULL,
  `num_online` smallint unsigned DEFAULT NULL,
  `members_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`snapshot_ts`),
  KEY `idx_snapshot_ts` (`snapshot_ts`),
  KEY `idx_guild_id` (`guild_id`),
  CONSTRAINT `fk_roster_snap_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `guilds`
--

DROP TABLE IF EXISTS `guilds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `guilds` (
  `guild_id` int NOT NULL AUTO_INCREMENT,
  `guild_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `faction` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `realm` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`guild_id`),
  UNIQUE KEY `unique_guild` (`guild_name`,`realm`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ignore_list_changes`
--

DROP TABLE IF EXISTS `ignore_list_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ignore_list_changes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `action` enum('added','removed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ignored_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ignored_ts` (`character_id`,`ignored_name`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_ignore_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=591 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - ignore list additions/removals';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `instance_lockouts`
--

DROP TABLE IF EXISTS `instance_lockouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `instance_lockouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `instance_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instance_id` int unsigned DEFAULT NULL,
  `difficulty` tinyint unsigned DEFAULT NULL,
  `difficulty_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_raid` tinyint(1) DEFAULT '1',
  `max_players` tinyint unsigned DEFAULT NULL,
  `total_bosses` tinyint unsigned DEFAULT NULL,
  `bosses_killed` tinyint unsigned DEFAULT NULL,
  `reset_time` int unsigned DEFAULT NULL,
  `extended` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_instance` (`instance_name`,`difficulty`),
  KEY `idx_reset` (`reset_time`),
  CONSTRAINT `fk_lockout_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_events`
--

DROP TABLE IF EXISTS `item_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `item_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `action` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `source` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `item_string` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `count` int unsigned DEFAULT '1',
  `ilvl` int unsigned DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sale_price` int unsigned DEFAULT NULL,
  `context_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_item_ts_action` (`character_id`,`item_id`,`ts`,`action`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_itemev_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=365962 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event log - item obtained/sold/destroyed';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `items_catalog`
--

DROP TABLE IF EXISTS `items_catalog`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `items_catalog` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `item_id` int unsigned NOT NULL,
  `item_string` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quality` tinyint unsigned DEFAULT NULL,
  `stack_size` int unsigned DEFAULT NULL,
  `equip_loc` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ilvl` int unsigned DEFAULT NULL,
  `quantity_bag` int unsigned DEFAULT '0',
  `quantity_bank` int unsigned DEFAULT '0',
  `quantity_keyring` int unsigned DEFAULT '0',
  `quantity_mail` int unsigned DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_item` (`character_id`,`item_id`),
  CONSTRAINT `fk_cat_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `loot_history`
--

DROP TABLE IF EXISTS `loot_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `loot_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `item_link` text COLLATE utf8mb4_unicode_ci,
  `item_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quality` tinyint unsigned DEFAULT NULL,
  `ilvl` smallint unsigned DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count` smallint unsigned DEFAULT '1',
  `source_type` enum('mob','boss','chest','quest','vendor','craft','roll','unknown') COLLATE utf8mb4_unicode_ci DEFAULT 'unknown',
  `source_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `source_level` tinyint unsigned DEFAULT NULL,
  `is_boss` tinyint(1) DEFAULT '0',
  `instance` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instance_difficulty` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `group_type` enum('solo','party','raid') COLLATE utf8mb4_unicode_ci DEFAULT 'solo',
  `group_size` tinyint unsigned DEFAULT '1',
  `zone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subzone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `roll_type` tinyint DEFAULT NULL,
  `roll_value` tinyint DEFAULT NULL,
  `competitors` tinyint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_item` (`item_id`),
  KEY `idx_source` (`source_name`),
  KEY `idx_boss` (`is_boss`,`quality` DESC),
  KEY `idx_instance` (`instance`),
  CONSTRAINT `fk_loot_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mailbox`
--

DROP TABLE IF EXISTS `mailbox`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mailbox` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `mail_index` int NOT NULL,
  `sender` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `money` int unsigned DEFAULT '0' COMMENT 'Attached gold in copper',
  `cod` int unsigned DEFAULT '0' COMMENT 'COD amount in copper',
  `days_left` float DEFAULT NULL,
  `was_read` tinyint(1) DEFAULT '0',
  `is_auction` tinyint(1) DEFAULT '0' COMMENT 'True if mail is from Auction House',
  `package_icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stationery_icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ts` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_mail_index` (`character_id`,`mail_index`),
  KEY `idx_mail_sender` (`character_id`,`sender`),
  KEY `idx_mail_auction` (`character_id`,`is_auction`),
  CONSTRAINT `fk_mail_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=465 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `mailbox_attachments`
--

DROP TABLE IF EXISTS `mailbox_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `mailbox_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `mailbox_id` bigint unsigned NOT NULL,
  `a_index` int NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `item_id` int unsigned DEFAULT NULL,
  `item_string` text COLLATE utf8mb4_unicode_ci,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `count` int unsigned DEFAULT '1',
  `ilvl` int unsigned DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quality` tinyint unsigned DEFAULT NULL COMMENT 'Item quality (0-7: Poor, Common, Uncommon, Rare, Epic, Legendary, Artifact, Heirloom)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mail_att_index` (`mailbox_id`,`a_index`),
  KEY `idx_mail_quality` (`quality`),
  CONSTRAINT `fk_mail_att_mail` FOREIGN KEY (`mailbox_id`) REFERENCES `mailbox` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=253 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pet_info`
--

DROP TABLE IF EXISTS `pet_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pet_info` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `family` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `xp` int DEFAULT NULL,
  `next_xp` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_pet` (`character_id`,`name`),
  CONSTRAINT `fk_petinfo_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pet_spells`
--

DROP TABLE IF EXISTS `pet_spells`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pet_spells` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_name` (`character_id`,`name`),
  CONSTRAINT `fk_petspells_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pet_stable`
--

DROP TABLE IF EXISTS `pet_stable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pet_stable` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `slot` int DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `level` int DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `family` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_slot` (`character_id`,`slot`),
  CONSTRAINT `fk_petstable_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `public_snapshots`
--

DROP TABLE IF EXISTS `public_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `public_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `snapshot_json` json NOT NULL,
  `schema_version` int unsigned NOT NULL,
  `generated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ps_char` (`character_id`),
  CONSTRAINT `fk_ps_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quest_events`
--

DROP TABLE IF EXISTS `quest_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `kind` enum('accepted','abandoned','completed','objective') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quest_id` int unsigned DEFAULT NULL,
  `quest_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objective_text` text COLLATE utf8mb4_unicode_ci,
  `objective_progress` smallint unsigned DEFAULT NULL,
  `objective_total` smallint unsigned DEFAULT NULL,
  `objective_complete` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_quest_event_v2` (`character_id`,`quest_id`,`kind`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_quest_id` (`quest_id`),
  KEY `idx_kind` (`kind`),
  KEY `idx_char_quest` (`character_id`,`quest_id`),
  CONSTRAINT `fk_qevt_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=87945 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quest_log_snapshots`
--

DROP TABLE IF EXISTS `quest_log_snapshots`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_log_snapshots` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `quest_id` int unsigned DEFAULT NULL,
  `quest_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quest_complete` tinyint(1) DEFAULT '0',
  `objectives` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_quest_snapshot` (`character_id`,`ts`,`quest_title`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_quest_id` (`quest_id`),
  KEY `idx_char_quest` (`character_id`,`quest_id`),
  CONSTRAINT `fk_qlog_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1171 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `quest_rewards`
--

DROP TABLE IF EXISTS `quest_rewards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quest_rewards` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `quest_id` int unsigned DEFAULT NULL,
  `quest_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quest_level` tinyint unsigned DEFAULT NULL,
  `reward_chosen_link` text COLLATE utf8mb4_unicode_ci,
  `reward_chosen_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reward_chosen_quantity` smallint unsigned DEFAULT NULL,
  `reward_chosen_quality` tinyint unsigned DEFAULT NULL,
  `reward_choices` json DEFAULT NULL,
  `reward_required` json DEFAULT NULL,
  `money` int unsigned DEFAULT NULL,
  `xp` int unsigned DEFAULT NULL,
  `honor` int unsigned DEFAULT NULL,
  `arena` int unsigned DEFAULT NULL,
  `reputation` json DEFAULT NULL,
  `zone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subzone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_quest_reward` (`character_id`,`ts`,`quest_title`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_quest_id` (`quest_id`),
  CONSTRAINT `fk_qr_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3975 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `remember_tokens`
--

DROP TABLE IF EXISTS `remember_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `remember_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `selector` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Public part of token, stored plain',
  `token_hash` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 hash of the private validator',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `idx_rt_user` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Persistent remember-me tokens for 30-day login sessions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `schema_version`
--

DROP TABLE IF EXISTS `schema_version`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schema_version` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `version` int unsigned NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `applied_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`),
  KEY `idx_version` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks applied database migrations';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_achievements`
--

DROP TABLE IF EXISTS `series_achievements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_achievements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `achievement_id` int unsigned NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `points` int unsigned NOT NULL DEFAULT '0',
  `earned` tinyint(1) NOT NULL DEFAULT '0',
  `earned_date` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_ach` (`character_id`,`achievement_id`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_earned` (`earned`),
  CONSTRAINT `fk_sach_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5880 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_arena`
--

DROP TABLE IF EXISTS `series_arena`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_arena` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `value` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sarena_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_attack`
--

DROP TABLE IF EXISTS `series_attack`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_attack` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `ap_base` int DEFAULT NULL,
  `ap_pos` int DEFAULT NULL,
  `ap_neg` int DEFAULT NULL,
  `parry` float DEFAULT NULL,
  `mh_speed` float DEFAULT NULL,
  `dodge` float DEFAULT NULL,
  `block` float DEFAULT NULL,
  `crit` float DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_satk_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_base_stats`
--

DROP TABLE IF EXISTS `series_base_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_base_stats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `strength` int DEFAULT NULL,
  `agility` int DEFAULT NULL,
  `stamina` int DEFAULT NULL,
  `intellect` int DEFAULT NULL,
  `spirit` int DEFAULT NULL,
  `armor` int DEFAULT NULL,
  `defense` int DEFAULT NULL,
  `resist_arcane` int DEFAULT NULL,
  `resist_fire` int DEFAULT NULL,
  `resist_frost` int DEFAULT NULL,
  `resist_holy` int DEFAULT NULL,
  `resist_nature` int DEFAULT NULL,
  `resist_shadow` int DEFAULT NULL,
  `data_json` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sbs_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11032 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - base stats and resistances';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_currency`
--

DROP TABLE IF EXISTS `series_currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_currency` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `currency_name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count` int unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_name_ts` (`character_id`,`currency_name`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sc_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=747 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_honor`
--

DROP TABLE IF EXISTS `series_honor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_honor` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `value` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sh_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12673 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - honor points';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_level`
--

DROP TABLE IF EXISTS `series_level`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_level` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `value` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_level` (`character_id`,`value`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sl_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8905 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - level progression';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_money`
--

DROP TABLE IF EXISTS `series_money`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_money` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `value` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sm_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=128866 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - gold progression';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_reputation`
--

DROP TABLE IF EXISTS `series_reputation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_reputation` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `faction` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `faction_name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `standing_id` int DEFAULT NULL,
  `value` int DEFAULT NULL,
  `min` int DEFAULT NULL,
  `max` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_faction_ts` (`character_id`,`faction_name`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  KEY `idx_faction` (`faction`),
  CONSTRAINT `fk_srep_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=77966 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - reputation with factions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_resource_max`
--

DROP TABLE IF EXISTS `series_resource_max`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_resource_max` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `hp` int unsigned DEFAULT NULL,
  `mp` int unsigned DEFAULT NULL,
  `power_type` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_srm_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=134472 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_rested`
--

DROP TABLE IF EXISTS `series_rested`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_rested` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `value` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sr_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=133288 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - rested XP';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_spell_ranged`
--

DROP TABLE IF EXISTS `series_spell_ranged`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_spell_ranged` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `ranged_min` int DEFAULT NULL,
  `ranged_max` int DEFAULT NULL,
  `ranged_speed` float DEFAULT NULL,
  `ranged_ap` int DEFAULT NULL,
  `ranged_crit` float DEFAULT NULL,
  `heal_bonus` int DEFAULT NULL,
  `spell_penetration` int DEFAULT NULL,
  `mp5_base` float DEFAULT NULL,
  `mp5_cast` float DEFAULT NULL,
  `school_arcane_dmg` int DEFAULT NULL,
  `school_arcane_crit` float DEFAULT NULL,
  `school_fire_dmg` int DEFAULT NULL,
  `school_fire_crit` float DEFAULT NULL,
  `school_frost_dmg` int DEFAULT NULL,
  `school_frost_crit` float DEFAULT NULL,
  `school_holy_dmg` int DEFAULT NULL,
  `school_holy_crit` float DEFAULT NULL,
  `school_nature_dmg` int DEFAULT NULL,
  `school_nature_crit` float DEFAULT NULL,
  `school_shadow_dmg` int DEFAULT NULL,
  `school_shadow_crit` float DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_ssr_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_xp`
--

DROP TABLE IF EXISTS `series_xp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_xp` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `value` int unsigned NOT NULL,
  `max` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sx_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=134225 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - XP progression';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `series_zones`
--

DROP TABLE IF EXISTS `series_zones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `series_zones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `zone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subzone` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hearth` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sz_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=74472 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - zone/location tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned NOT NULL,
  `total_time` int unsigned DEFAULT NULL,
  `level_time` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_char_ts` (`character_id`,`ts`),
  KEY `idx_char_ts` (`character_id`,`ts`),
  CONSTRAINT `fk_sess_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5553 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Time series data - play sessions';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `share_events`
--

DROP TABLE IF EXISTS `share_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `share_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `share_link_id` bigint unsigned NOT NULL,
  `event_type` enum('CREATED','VIEW','REVOKED','ROTATED','EXPIRED') COLLATE utf8mb4_unicode_ci NOT NULL,
  `actor_ip` varbinary(16) DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_se_link` (`share_link_id`),
  KEY `idx_se_type_time` (`event_type`,`at`),
  CONSTRAINT `fk_se_link` FOREIGN KEY (`share_link_id`) REFERENCES `share_links` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `share_links`
--

DROP TABLE IF EXISTS `share_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `share_links` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `token` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `state` enum('ACTIVE','REVOKED') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ACTIVE',
  `scope_json` json NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_share_token` (`token`),
  KEY `idx_share_char` (`character_id`),
  CONSTRAINT `fk_share_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `skills`
--

DROP TABLE IF EXISTS `skills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skills` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rank` int DEFAULT NULL,
  `max_rank` int DEFAULT NULL,
  `ts` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_skill` (`character_id`,`name`),
  CONSTRAINT `fk_skill_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5311 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spellbook_tabs`
--

DROP TABLE IF EXISTS `spellbook_tabs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spellbook_tabs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `tab_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_tab` (`character_id`,`tab_name`),
  CONSTRAINT `fk_sbt_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `spells`
--

DROP TABLE IF EXISTS `spells`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spells` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spellbook_tab_id` bigint unsigned NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rank` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_spells_tab` (`spellbook_tab_id`),
  CONSTRAINT `fk_spells_tab` FOREIGN KEY (`spellbook_tab_id`) REFERENCES `spellbook_tabs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `talents`
--

DROP TABLE IF EXISTS `talents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `talents_tab_id` bigint unsigned NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rank` int DEFAULT NULL,
  `max_rank` int DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `talent_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_talent_tab` (`talents_tab_id`),
  CONSTRAINT `fk_talent_tab` FOREIGN KEY (`talents_tab_id`) REFERENCES `talents_tabs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7219 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `talents_groups`
--

DROP TABLE IF EXISTS `talents_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talents_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `group_index` tinyint unsigned NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `active` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_group` (`character_id`,`group_index`),
  CONSTRAINT `fk_tg_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=599 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `talents_tabs`
--

DROP TABLE IF EXISTS `talents_tabs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `talents_tabs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `talents_group_id` bigint unsigned NOT NULL,
  `name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points_spent` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_tt_group` (`talents_group_id`),
  CONSTRAINT `fk_tt_group` FOREIGN KEY (`talents_group_id`) REFERENCES `talents_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1795 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tradeskill_reagents`
--

DROP TABLE IF EXISTS `tradeskill_reagents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tradeskill_reagents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tradeskill_id` bigint unsigned NOT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `count_required` int DEFAULT NULL,
  `have_count` int DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_tsr_ts` (`tradeskill_id`),
  CONSTRAINT `fk_tsr_ts` FOREIGN KEY (`tradeskill_id`) REFERENCES `tradeskills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tradeskills`
--

DROP TABLE IF EXISTS `tradeskills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tradeskills` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `character_id` bigint unsigned NOT NULL,
  `ts` int unsigned DEFAULT NULL,
  `name` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `link` text COLLATE utf8mb4_unicode_ci,
  `icon` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_made_min` int DEFAULT NULL,
  `num_made_max` int DEFAULT NULL,
  `cooldown` int DEFAULT NULL,
  `cooldown_text` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `profession` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_char_name` (`character_id`,`name`),
  CONSTRAINT `fk_ts_char` FOREIGN KEY (`character_id`) REFERENCES `characters` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=43489 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_api_keys`
--

DROP TABLE IF EXISTS `user_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_api_keys` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `api_key` varchar(128) NOT NULL,
  `key_name` varchar(100) DEFAULT 'Default Key',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_key` (`api_key`),
  KEY `idx_api_key` (`api_key`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active_keys` (`is_active`,`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `v_bazaar_active_alerts`
--

DROP TABLE IF EXISTS `v_bazaar_active_alerts`;
/*!50001 DROP VIEW IF EXISTS `v_bazaar_active_alerts`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_bazaar_active_alerts` AS SELECT 
 1 AS `user_id`,
 1 AS `total_alerts`,
 1 AS `enabled_alerts`,
 1 AS `alert_types`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_bazaar_character_tickets`
--

DROP TABLE IF EXISTS `v_bazaar_character_tickets`;
/*!50001 DROP VIEW IF EXISTS `v_bazaar_character_tickets`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_bazaar_character_tickets` AS SELECT 
 1 AS `character_id`,
 1 AS `total_tickets`,
 1 AS `activity_count`,
 1 AS `last_earned`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_public_characters`
--

DROP TABLE IF EXISTS `v_public_characters`;
/*!50001 DROP VIEW IF EXISTS `v_public_characters`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_public_characters` AS SELECT 
 1 AS `id`,
 1 AS `realm`,
 1 AS `name`,
 1 AS `realm_norm`,
 1 AS `name_norm`,
 1 AS `faction`,
 1 AS `class_file`,
 1 AS `guild_name`,
 1 AS `updated_at`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary view structure for view `v_safe_progression`
--

DROP TABLE IF EXISTS `v_safe_progression`;
/*!50001 DROP VIEW IF EXISTS `v_safe_progression`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_safe_progression` AS SELECT 
 1 AS `character_id`,
 1 AS `name`,
 1 AS `realm`,
 1 AS `ts`,
 1 AS `duration`,
 1 AS `dps`,
 1 AS `zone`,
 1 AS `seconds_ago`,
 1 AS `event_datetime`*/;
SET character_set_client = @saved_cs_client;

--
-- Final view structure for view `api_keys_active`
--

/*!50001 DROP VIEW IF EXISTS `api_keys_active`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`whodatuser`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `api_keys_active` AS select `user_api_keys`.`id` AS `id`,`user_api_keys`.`user_id` AS `user_id`,left(`user_api_keys`.`api_key`,10) AS `key_preview`,`user_api_keys`.`key_name` AS `key_name`,`user_api_keys`.`created_at` AS `created_at`,`user_api_keys`.`last_used_at` AS `last_used_at`,`user_api_keys`.`expires_at` AS `expires_at`,(case when ((`user_api_keys`.`expires_at` is not null) and (`user_api_keys`.`expires_at` < now())) then 'Expired' when (`user_api_keys`.`is_active` = 1) then 'Active' else 'Inactive' end) AS `status` from `user_api_keys` where ((`user_api_keys`.`is_active` = 1) and ((`user_api_keys`.`expires_at` is null) or (`user_api_keys`.`expires_at` > now()))) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_bazaar_active_alerts`
--

/*!50001 DROP VIEW IF EXISTS `v_bazaar_active_alerts`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`whodatuser`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_bazaar_active_alerts` AS select `bazaar_auction_alerts`.`user_id` AS `user_id`,count(0) AS `total_alerts`,sum((case when (`bazaar_auction_alerts`.`enabled` = true) then 1 else 0 end)) AS `enabled_alerts`,count(distinct `bazaar_auction_alerts`.`alert_type`) AS `alert_types` from `bazaar_auction_alerts` group by `bazaar_auction_alerts`.`user_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_bazaar_character_tickets`
--

/*!50001 DROP VIEW IF EXISTS `v_bazaar_character_tickets`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`whodatuser`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_bazaar_character_tickets` AS select `bazaar_tickets`.`character_id` AS `character_id`,sum(`bazaar_tickets`.`tickets_earned`) AS `total_tickets`,count(0) AS `activity_count`,max(`bazaar_tickets`.`earned_at`) AS `last_earned` from `bazaar_tickets` group by `bazaar_tickets`.`character_id` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_public_characters`
--

/*!50001 DROP VIEW IF EXISTS `v_public_characters`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`whodatuser`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_public_characters` AS select `c`.`id` AS `id`,`c`.`realm` AS `realm`,`c`.`name` AS `name`,`c`.`realm_norm` AS `realm_norm`,`c`.`name_norm` AS `name_norm`,`c`.`faction` AS `faction`,`c`.`class_file` AS `class_file`,`c`.`guild_name` AS `guild_name`,`c`.`updated_at` AS `updated_at` from `characters` `c` where ((`c`.`visibility` = 'PUBLIC') and (`c`.`allow_search` = 1)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_safe_progression`
--

/*!50001 DROP VIEW IF EXISTS `v_safe_progression`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`whodatuser`@`%` SQL SECURITY DEFINER */
/*!50001 VIEW `v_safe_progression` AS select `c`.`id` AS `character_id`,`c`.`name` AS `name`,`c`.`realm` AS `realm`,`ce`.`ts` AS `ts`,`ce`.`duration` AS `duration`,`ce`.`dps` AS `dps`,`ce`.`zone` AS `zone`,(cast(unix_timestamp() as signed) - cast(`ce`.`ts` as signed)) AS `seconds_ago`,from_unixtime(`ce`.`ts`) AS `event_datetime` from (`characters` `c` left join `combat_encounters` `ce` on((`c`.`id` = `ce`.`character_id`))) order by `ce`.`ts` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-05  0:27:50
