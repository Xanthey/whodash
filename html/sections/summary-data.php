<?php
// sections/summary-data.php - Summary dashboard data for summary.js
// Serves: timeseries, sessions, stats, items, events, zones, reputation, identity
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!isset($_GET['character_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'missing character_id']);
    exit;
}

$cid = (int) $_GET['character_id'];

// Validate ownership OR public access
$character = null;

if (isset($_SESSION['user_id'])) {
    $own = $pdo->prepare('
        SELECT id, name, guild_name, class_file, class_local, race, race_file, sex, faction, realm
        FROM characters
        WHERE id = ? AND user_id = ?
    ');
    $own->execute([$cid, $_SESSION['user_id']]);
    $character = $own->fetch(PDO::FETCH_ASSOC);
}

if (!$character) {
    $pub = $pdo->prepare('
        SELECT id, name, guild_name, class_file, class_local, race, race_file, sex, faction, realm
        FROM characters
        WHERE id = ? AND visibility = "PUBLIC"
    ');
    $pub->execute([$cid]);
    $character = $pub->fetch(PDO::FETCH_ASSOC);
}

if (!$character) {
    http_response_code(403);
    echo json_encode(['error' => 'Character not found or not accessible']);
    exit;
}

$character_id = $cid;

try {
    $data = [
        'identity' => [],
        'timeseries' => [],
        'sessions' => [],
        'stats' => [],
        'items' => [],
        'events' => [],
        'zones' => [],
        'reputation' => [],
    ];

    // ─── IDENTITY ────────────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('SELECT value FROM series_level WHERE character_id = ? ORDER BY ts DESC LIMIT 1');
        $stmt->execute([$character_id]);
        $lvlRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentLevel = $lvlRow ? (int) $lvlRow['value'] : 1;
    } catch (Throwable $e) {
        $currentLevel = 1;
    }

    $data['identity'] = [
        'name' => $character['name'],
        'level' => $currentLevel,
        'class' => $character['class_local'] ?? '',
        'race' => $character['race'] ?? '',
        'guild' => $character['guild_name'] ?? null,
        'faction' => $character['faction'] ?? '',
        'realm' => $character['realm'] ?? '',
    ];

    // ─── TIMESERIES: level ────────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('SELECT ts, value FROM series_level WHERE character_id = ? ORDER BY ts ASC');
        $stmt->execute([$character_id]);
        $data['timeseries']['level'] = array_map(fn($r) => [
            'ts' => (int) $r['ts'],
            'value' => (int) $r['value'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        $data['timeseries']['level'] = [];
    }

    // ─── TIMESERIES: money ────────────────────────────────────────────────────
    try {
        $moneyRows = [];
        foreach (['series_money', 'series_gold'] as $tbl) {
            try {
                $stmt = $pdo->prepare("SELECT ts, value FROM {$tbl} WHERE character_id = ? ORDER BY ts ASC");
                $stmt->execute([$character_id]);
                $moneyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($moneyRows))
                    break;
            } catch (Throwable $e) { /* try next table */
            }
        }
        $data['timeseries']['money'] = array_map(fn($r) => [
            'ts' => (int) $r['ts'],
            'value' => (int) $r['value'],
        ], $moneyRows);
    } catch (Throwable $e) {
        $data['timeseries']['money'] = [];
    }

    // ─── SESSIONS ────────────────────────────────────────────────────────────
    // summary.js uses sessions for: activity heatmap (date + duration + count),
    // total playtime, and weekly time played.
    try {
        $stmt = $pdo->prepare("
            SELECT
                DATE(FROM_UNIXTIME(ts)) as date,
                COUNT(*)               as count,
                SUM(total_time)        as duration
            FROM sessions
            WHERE character_id = ?
            GROUP BY DATE(FROM_UNIXTIME(ts))
            ORDER BY date ASC
        ");
        $stmt->execute([$character_id]);
        $data['sessions'] = array_map(fn($r) => [
            'date' => $r['date'],
            'count' => (int) $r['count'],
            'duration' => (int) $r['duration'],
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        $data['sessions'] = [];
    }

    // ─── STATS: deaths + kills ────────────────────────────────────────────────
    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) as total FROM deaths WHERE character_id = ?');
        $stmt->execute([$character_id]);
        $data['stats']['deaths'] = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    } catch (Throwable $e) {
        $data['stats']['deaths'] = 0;
    }

    $data['stats']['kills'] = 0; // Not tracked yet

    // ─── ITEMS: recent loot history ──────────────────────────────────────────
    // summary.js uses items.history for top loot (last 7 days) and activity feed.
    // Extracts item name from WoW link format: |h[Item Name]|h
    try {
        $stmt = $pdo->prepare("
            SELECT ts, item_id, link, quality, ilvl, zone, source_type
            FROM loot
            WHERE character_id = ?
            ORDER BY ts DESC
            LIMIT 200
        ");
        $stmt->execute([$character_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $data['items']['history'] = array_map(function ($r) {
            $link = $r['link'] ?? '';
            $name = '';
            if (preg_match('/\|h\[(.+?)\]\|h/', $link, $m)) {
                $name = $m[1];
            }
            return [
                'ts' => (int) $r['ts'],
                'action' => 'obtained',
                'itemName' => $name,
                'link' => $link,
                'item_id' => (int) ($r['item_id'] ?? 0),
                'ilvl' => (int) ($r['ilvl'] ?? 0),
                'quality' => (int) ($r['quality'] ?? 0),
                'zone' => $r['zone'] ?? '',
                'count' => 1,
            ];
        }, $rows);
    } catch (Throwable $e) {
        $data['items']['history'] = [];
    }

    // ─── EVENTS: recent deaths ────────────────────────────────────────────────
    // summary.js uses events.death[] with ts + zone for activity feed + weekly deaths.
    try {
        $stmt = $pdo->prepare("
            SELECT ts, zone, subzone, killer_name, killer_type
            FROM deaths
            WHERE character_id = ?
            ORDER BY ts DESC
            LIMIT 100
        ");
        $stmt->execute([$character_id]);
        $data['events']['death'] = array_map(fn($r) => [
            'ts' => (int) $r['ts'],
            'zone' => $r['zone'] ?? '',
            'subzone' => $r['subzone'] ?? '',
            'killer_name' => $r['killer_name'] ?? '',
            'killer_type' => $r['killer_type'] ?? '',
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        $data['events']['death'] = [];
    }

    // ─── ZONES: history ───────────────────────────────────────────────────────
    // summary.js uses zones.history[].zone + ts for weekly unique zones count.
    try {
        $stmt = $pdo->prepare("
            SELECT ts, zone, subzone
            FROM series_zones
            WHERE character_id = ?
            ORDER BY ts DESC
            LIMIT 500
        ");
        $stmt->execute([$character_id]);
        $data['zones']['history'] = array_map(fn($r) => [
            'ts' => (int) $r['ts'],
            'zone' => $r['zone'] ?? '',
            'subzone' => $r['subzone'] ?? '',
        ], $stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Throwable $e) {
        $data['zones']['history'] = [];
    }

    // ─── REPUTATION ───────────────────────────────────────────────────────────
    // summary.js uses reputation.history[] for the reputation list widget.
    try {
        $standingNames = ['Unknown', 'Hated', 'Hostile', 'Unfriendly', 'Neutral', 'Friendly', 'Honored', 'Revered', 'Exalted'];
        $stmt = $pdo->prepare("
            SELECT faction_name, standing_id, value, min, max, ts
            FROM series_reputation
            WHERE character_id = ?
            ORDER BY ts DESC
        ");
        $stmt->execute([$character_id]);
        $repRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Deduplicate to latest entry per faction
        $latestByFaction = [];
        foreach ($repRows as $row) {
            $name = $row['faction_name'];
            if (!isset($latestByFaction[$name])) {
                $latestByFaction[$name] = $row;
            }
        }

        $data['reputation']['history'] = array_values(array_map(function ($r) use ($standingNames) {
            return [
                'faction' => $r['faction_name'],
                'standing' => $standingNames[(int) ($r['standing_id'] ?? 0)] ?? 'Unknown',
                'standing_id' => (int) ($r['standing_id'] ?? 0),
                'value' => (int) $r['value'],
                'min' => (int) $r['min'],
                'max' => (int) $r['max'],
                'ts' => (int) $r['ts'],
            ];
        }, $latestByFaction));
    } catch (Throwable $e) {
        $data['reputation']['history'] = [];
    }

    echo json_encode($data, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    error_log('Summary data error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}