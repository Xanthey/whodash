<?php
/**
 * API Key Management Endpoint
 * Handles CRUD operations for user API keys
 */

header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Database connection
require_once __DIR__ . '/../db.php';

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);
$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            listApiKeys($pdo, $user_id);
            break;
            
        case 'generate':
            generateApiKey($pdo, $user_id, $data);
            break;
            
        case 'revoke':
            revokeApiKey($pdo, $user_id, $data);
            break;
            
        case 'delete':
            deleteApiKey($pdo, $user_id, $data);
            break;
            
        case 'update':
            updateApiKey($pdo, $user_id, $data);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("API Key Management Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

function listApiKeys($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            id,
            key_name,
            LEFT(api_key, 10) as key_preview,
            api_key,
            is_active,
            created_at,
            last_used_at,
            expires_at,
            CASE 
                WHEN expires_at IS NOT NULL AND expires_at < NOW() THEN 1
                ELSE 0
            END as is_expired
        FROM user_api_keys
        WHERE user_id = ?
        ORDER BY created_at DESC
    ");
    
    $stmt->execute([$user_id]);
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'keys' => $keys
    ]);
}

function generateApiKey($pdo, $user_id, $data) {
    $key_name = $data['key_name'] ?? 'Default Key';
    $expires_days = isset($data['expires_days']) ? (int)$data['expires_days'] : null;
    
    // Validate key name
    if (empty(trim($key_name)) || strlen($key_name) > 100) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid key name']);
        return;
    }
    
    // Call stored procedure to generate key
    $stmt = $pdo->prepare("CALL sp_generate_api_key(?, ?, @new_key)");
    $stmt->execute([$user_id, $key_name]);
    
    // Get the generated key
    $result = $pdo->query("SELECT @new_key as api_key")->fetch(PDO::FETCH_ASSOC);
    $api_key = $result['api_key'];
    
    // Set expiration if provided
    if ($expires_days !== null && $expires_days > 0) {
        $stmt = $pdo->prepare("
            UPDATE user_api_keys 
            SET expires_at = DATE_ADD(NOW(), INTERVAL ? DAY)
            WHERE api_key = ?
        ");
        $stmt->execute([$expires_days, $api_key]);
    }
    
    // Get the full key details
    $stmt = $pdo->prepare("
        SELECT id, key_name, api_key, created_at, expires_at, is_active
        FROM user_api_keys
        WHERE api_key = ?
    ");
    $stmt->execute([$api_key]);
    $key_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'API key generated successfully',
        'key' => $key_data
    ]);
}

function revokeApiKey($pdo, $user_id, $data) {
    $key_id = (int)($data['key_id'] ?? 0);
    
    if ($key_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid key ID']);
        return;
    }
    
    // Update key to inactive
    $stmt = $pdo->prepare("
        UPDATE user_api_keys 
        SET is_active = 0
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->execute([$key_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'API key revoked successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API key not found']);
    }
}

function deleteApiKey($pdo, $user_id, $data) {
    $key_id = (int)($data['key_id'] ?? 0);
    
    if ($key_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid key ID']);
        return;
    }
    
    // Delete the key
    $stmt = $pdo->prepare("
        DELETE FROM user_api_keys 
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->execute([$key_id, $user_id]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'API key deleted successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API key not found']);
    }
}

function updateApiKey($pdo, $user_id, $data) {
    $key_id = (int)($data['key_id'] ?? 0);
    $key_name = $data['key_name'] ?? null;
    $is_active = isset($data['is_active']) ? (int)$data['is_active'] : null;
    
    if ($key_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid key ID']);
        return;
    }
    
    $updates = [];
    $params = [];
    
    if ($key_name !== null) {
        $updates[] = "key_name = ?";
        $params[] = $key_name;
    }
    
    if ($is_active !== null) {
        $updates[] = "is_active = ?";
        $params[] = $is_active;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['error' => 'No updates provided']);
        return;
    }
    
    $params[] = $key_id;
    $params[] = $user_id;
    
    $sql = "UPDATE user_api_keys SET " . implode(', ', $updates) . " WHERE id = ? AND user_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'API key updated successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API key not found or no changes made']);
    }
}
?>
