<?php
// sections/bazaar-workshop.php - Profession Workshop Data Provider
declare(strict_types=1);

require_once __DIR__ . '/../db.php';

// Suppress PHP warnings that could corrupt JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', '0');

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=60');

// Require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authorized']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Initialize response payload
$payload = [
    'characters' => [],
    'summary' => [
        'total_characters' => 0,
        'total_professions' => 0,
        'total_recipes' => 0,
        'professions_breakdown' => []
    ]
];

try {
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

    // Get all characters
    $stmt = $pdo->prepare('
        SELECT 
            c.id,
            c.name,
            c.realm,
            c.faction,
            c.class_file
        FROM characters c
        WHERE c.user_id = ?
        ORDER BY c.name
    ');
    $stmt->execute([$user_id]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($characters as &$char) {
        // Get current level from series_level
        $stmt = $pdo->prepare('
            SELECT value as level
            FROM series_level
            WHERE character_id = ?
            ORDER BY ts DESC
            LIMIT 1
        ');
        $stmt->execute([$char['id']]);
        $levelRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $char['level'] = $levelRow ? (int) $levelRow['level'] : 0;

        // Get character's professions
        $stmt = $pdo->prepare('
            SELECT name, `rank`, max_rank
            FROM skills
            WHERE character_id = ? AND name IN ("' . implode('","', $primaryProfessions) . '")
            ORDER BY `rank` DESC
        ');
        $stmt->execute([$char['id']]);
        $char['professions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all recipes for this character
        $stmt = $pdo->prepare('
            SELECT 
                name,
                type,
                profession,
                icon,
                num_made_min,
                num_made_max,
                cooldown,
                cooldown_text
            FROM tradeskills
            WHERE character_id = ?
            ORDER BY profession, name
        ');
        $stmt->execute([$char['id']]);
        $char['recipes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organize recipes by profession
        $char['recipes_by_profession'] = [];
        foreach ($char['recipes'] as $recipe) {
            if ($recipe['profession']) {
                if (!isset($char['recipes_by_profession'][$recipe['profession']])) {
                    $char['recipes_by_profession'][$recipe['profession']] = [];
                }
                $char['recipes_by_profession'][$recipe['profession']][] = $recipe;
            }
        }

        // Only include characters with professions
        if (!empty($char['professions'])) {
            $payload['characters'][] = $char;
            $payload['summary']['total_professions'] += count($char['professions']);
            $payload['summary']['total_recipes'] += count($char['recipes']);

            // Count profession breakdown
            foreach ($char['professions'] as $prof) {
                $profName = $prof['name'];
                if (!isset($payload['summary']['professions_breakdown'][$profName])) {
                    $payload['summary']['professions_breakdown'][$profName] = 0;
                }
                $payload['summary']['professions_breakdown'][$profName]++;
            }
        }
    }
    unset($char);

    $payload['summary']['total_characters'] = count($payload['characters']);

    echo json_encode($payload, JSON_THROW_ON_ERROR);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error',
        'message' => $e->getMessage()
    ]);
}