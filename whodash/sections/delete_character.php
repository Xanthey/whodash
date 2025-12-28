<?php
// sections/delete_character.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../db.php';
    session_start();

    // Auth check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    // Only allow POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    // Get JSON input
    $rawInput = file_get_contents('php://input');
    error_log("Delete character request: " . $rawInput);

    $input = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }

    $characterId = isset($input['character_id']) ? (int) $input['character_id'] : 0;
    $confirmation = isset($input['confirmation']) ? trim($input['confirmation']) : '';

    error_log("Character ID: $characterId, Confirmation: $confirmation");

    if (!$characterId) {
        http_response_code(400);
        echo json_encode(['error' => 'No character_id provided']);
        exit;
    }

    if ($confirmation !== 'PERMANENT') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid confirmation']);
        exit;
    }

    // Verify ownership
    $stmt = $pdo->prepare('SELECT id, name, class_local FROM characters WHERE id = ? AND user_id = ?');
    $stmt->execute([$characterId, $_SESSION['user_id']]);
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not yours']);
        exit;
    }

    error_log("Deleting character: " . $character['name']);

    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Delete all character data in order (respecting foreign keys)
        // IMPORTANT: Delete child tables before parent tables

        $tables = [
            // Quest system
            'quest_events',
            'quest_log_snapshots',
            'quest_rewards',

            // Combat & Deaths
            'combat_encounters',
            'deaths',
            'lockouts',

            // Tradeskills (delete reagents first - child table)
            'tradeskill_reagents',
            'tradeskills',

            // Snapshots
            'aura_snapshots',
            'achievements_snapshot',
            'glyphs_snapshot',
            'skills_snapshot',
            'spellbook_snapshot',
            'companions_snapshot',
            'pet_stable_snapshot',
            'professions_snapshot',

            // Series data
            'series_gold',
            'series_xp',
            'series_health',
            'series_mana',
            'series_played',
            'series_achievements',
            'series_level',
            'series_reputation',

            // Events and other data
            'events',
            'items_catalog',
            'containers',
            'reputation',
            'companions',
            'sessions',
            'equipment',
            'talents_snapshot',

            // Auction House - COMMENTED OUT to preserve for server-wide AH page
            // 'auction_owner_rows',
        ];

        $totalDeleted = 0;
        foreach ($tables as $table) {
            try {
                $stmt = $pdo->prepare("DELETE FROM $table WHERE character_id = ?");
                $stmt->execute([$characterId]);
                $deleted = $stmt->rowCount();
                if ($deleted > 0) {
                    error_log("Deleted $deleted rows from $table");
                    $totalDeleted += $deleted;
                }
            } catch (PDOException $e) {
                // Table might not exist, log but continue
                error_log("Could not delete from $table: " . $e->getMessage());
            }
        }

        // Finally, delete the character itself
        $stmt = $pdo->prepare('DELETE FROM characters WHERE id = ?');
        $stmt->execute([$characterId]);

        error_log("Character record deleted. Total data rows deleted: $totalDeleted");

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => "Character '{$character['name']}' has been permanently deleted",
            'character_name' => $character['name'],
            'rows_deleted' => $totalDeleted
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Transaction error: " . $e->getMessage());
        throw $e;
    }

} catch (Throwable $e) {
    error_log("Fatal error in delete_character.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine()
    ]);
}