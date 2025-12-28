<?php
// sections/professions-data.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');

try {
    require_once __DIR__ . '/../db.php';
    session_start();

    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, max-age=60');

    // Auth check
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated']);
        exit;
    }

    // Get character ID
    $cid = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;
    if (!$cid) {
        http_response_code(400);
        echo json_encode(['error' => 'No character_id']);
        exit;
    }

    // Validate ownership
    $own = $pdo->prepare('SELECT id, name FROM characters WHERE id = ? AND user_id = ?');
    $own->execute([$cid, $_SESSION['user_id']]);
    $character = $own->fetch(PDO::FETCH_ASSOC);
    if (!$character) {
        http_response_code(403);
        echo json_encode(['error' => 'Character not found or not yours']);
        exit;
    }

    $payload = [];

    // ============================================================================
    // OVERVIEW TAB - All Skills
    // ============================================================================

    // Define profession categories
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
        'Tailoring'
    ];

    $secondarySkills = ['Cooking', 'First Aid', 'Fishing'];

    // Get all current skills
    $stmt = $pdo->prepare('
        SELECT name, `rank`, max_rank, ts
        FROM skills
        WHERE character_id = ?
        ORDER BY `rank` DESC
    ');
    $stmt->execute([$cid]);
    $allSkills = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Separate into categories
    $payload['primary_professions'] = [];
    $payload['secondary_skills'] = [];
    $payload['other_skills'] = [];

    foreach ($allSkills as $skill) {
        if (in_array($skill['name'], $primaryProfessions)) {
            $payload['primary_professions'][] = $skill;
        } elseif (in_array($skill['name'], $secondarySkills)) {
            $payload['secondary_skills'][] = $skill;
        } else {
            $payload['other_skills'][] = $skill;
        }
    }

    // Calculate profession mastery (recipes known)
    foreach ($payload['primary_professions'] as &$prof) {
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as recipe_count
            FROM tradeskills
            WHERE character_id = ?
                AND profession = ?
        ');
        $stmt->execute([$cid, $prof['name']]);
        $prof['recipes_known'] = (int) ($stmt->fetchColumn() ?? 0);
    }
    unset($prof);

    // ============================================================================
    // RECIPES TAB - All Tradeskills/Recipes
    // ============================================================================

    // All known recipes
    $stmt = $pdo->prepare('
        SELECT 
            name,
            type,
            profession,
            icon,
            num_made_min,
            num_made_max,
            cooldown,
            cooldown_text,
            ts
        FROM tradeskills
        WHERE character_id = ?
        ORDER BY profession, name
    ');
    $stmt->execute([$cid]);
    $payload['all_recipes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recipe counts by profession
    $stmt = $pdo->prepare('
        SELECT 
            profession,
            COUNT(*) as recipe_count,
            COUNT(CASE WHEN type LIKE "%Epic%" THEN 1 END) as epic_recipes,
            COUNT(CASE WHEN type LIKE "%Rare%" THEN 1 END) as rare_recipes
        FROM tradeskills
        WHERE character_id = ?
            AND profession IS NOT NULL
        GROUP BY profession
        ORDER BY recipe_count DESC
    ');
    $stmt->execute([$cid]);
    $payload['recipe_counts_by_profession'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recipe difficulty distribution (by color/type)
    $stmt = $pdo->prepare('
        SELECT 
            CASE 
                WHEN type LIKE "%Trivial%" THEN "Trivial"
                WHEN type LIKE "%Easy%" THEN "Easy"
                WHEN type LIKE "%Medium%" THEN "Medium"
                WHEN type LIKE "%Optimal%" THEN "Optimal"
                WHEN type LIKE "%Difficult%" THEN "Difficult"
                ELSE "Unknown"
            END as difficulty,
            COUNT(*) as count
        FROM tradeskills
        WHERE character_id = ?
        GROUP BY difficulty
        ORDER BY 
            CASE difficulty
                WHEN "Trivial" THEN 1
                WHEN "Easy" THEN 2
                WHEN "Medium" THEN 3
                WHEN "Optimal" THEN 4
                WHEN "Difficult" THEN 5
                ELSE 6
            END
    ');
    $stmt->execute([$cid]);
    $payload['recipe_difficulty_distribution'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Rare/Epic recipes
    $stmt = $pdo->prepare('
        SELECT name, profession, type
        FROM tradeskills
        WHERE character_id = ?
            AND (type LIKE "%Epic%" OR type LIKE "%Rare%")
        ORDER BY profession, name
        LIMIT 50
    ');
    $stmt->execute([$cid]);
    $payload['rare_recipes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recipes with cooldowns
    $stmt = $pdo->prepare('
        SELECT name, profession, cooldown_text
        FROM tradeskills
        WHERE character_id = ?
            AND cooldown > 0
        ORDER BY cooldown DESC
    ');
    $stmt->execute([$cid]);
    $payload['cooldown_recipes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ============================================================================
    // STATISTICS
    // ============================================================================

    // Total recipes known
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tradeskills WHERE character_id = ?');
    $stmt->execute([$cid]);
    $payload['total_recipes'] = (int) ($stmt->fetchColumn() ?? 0);

    // Total professions learned
    $payload['total_professions'] = count($payload['primary_professions']);
    $payload['total_secondary_skills'] = count($payload['secondary_skills']);

    // Highest skill level
    $highestSkill = null;
    $highestRank = 0;
    foreach ($allSkills as $skill) {
        if ($skill['rank'] > $highestRank) {
            $highestRank = $skill['rank'];
            $highestSkill = $skill['name'];
        }
    }
    $payload['highest_skill'] = [
        'name' => $highestSkill,
        'rank' => $highestRank
    ];

    echo json_encode($payload, JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}