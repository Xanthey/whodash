<?php
/**
 * WhoDASH API Upload Endpoint - DEBUG VERSION
 * Use this temporarily to see what's being received
 */

declare(strict_types=1);

set_time_limit(300);
ini_set('max_execution_time', '600');
ini_set('memory_limit', '512M');

header('Content-Type: application/json');

// Log everything for debugging
error_log("=== API Upload Debug ===");
error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("HTTP_X_API_KEY present: " . (isset($_SERVER['HTTP_X_API_KEY']) ? 'YES' : 'NO'));
error_log("POST data: " . print_r($_POST, true));
error_log("FILES data: " . print_r($_FILES, true));

// Check for API key
$api_key = null;
if (isset($_SERVER['HTTP_X_API_KEY'])) {
    $api_key = $_SERVER['HTTP_X_API_KEY'];
} elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $matches = [];
    if (preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $api_key = $matches[1];
    }
}

if (!$api_key) {
    error_log("No API key found");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'API key required']);
    exit;
}

session_start();
require_once __DIR__ . '/../db.php';

// Validate API key
$stmt = $pdo->prepare("
    SELECT user_id FROM user_api_keys 
    WHERE api_key = ? AND is_active = 1 
    AND (expires_at IS NULL OR expires_at > NOW())
");
$stmt->execute([$api_key]);
$api_key_data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$api_key_data) {
    error_log("Invalid API key");
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid or expired API key']);
    exit;
}

$_SESSION['user_id'] = $api_key_data['user_id'];
$_SESSION['authenticated_via'] = 'api_key';

// Update last_used_at
$update_stmt = $pdo->prepare("UPDATE user_api_keys SET last_used_at = NOW() WHERE api_key = ?");
$update_stmt->execute([$api_key]);

error_log("API key validated for user_id: " . $api_key_data['user_id']);

// ============================================
// CHECK FILE UPLOAD - WITH DETAILED LOGGING
// ============================================

// Check all possible field names
$possible_fields = ['whodat_lua', 'file', 'upload', 'lua_file', 'whodatFile'];
$found_field = null;

foreach ($possible_fields as $field) {
    if (isset($_FILES[$field])) {
        $found_field = $field;
        error_log("Found file in field: $field");
        break;
    }
}

if (!$found_field) {
    error_log("No file found in any expected field");
    error_log("Available fields: " . implode(', ', array_keys($_FILES)));
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'No file uploaded',
        'debug' => [
            'expected_fields' => $possible_fields,
            'received_fields' => array_keys($_FILES),
            'post_keys' => array_keys($_POST)
        ]
    ]);
    exit;
}

$file = $_FILES[$found_field];

// Log file details
error_log("File details: " . print_r($file, true));

// Check for upload errors
if ($file['error'] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload stopped by extension'
    ];

    $error_msg = $error_messages[$file['error']] ?? 'Upload error: ' . $file['error'];
    error_log("File upload error: $error_msg");

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $error_msg,
        'debug' => [
            'error_code' => $file['error'],
            'file_size' => $file['size'] ?? 0,
            'tmp_name' => $file['tmp_name'] ?? 'none'
        ]
    ]);
    exit;
}

// Validate file type
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
if ($extension !== 'lua') {
    error_log("Invalid file type: $extension");
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid file type. Expected .lua file',
        'debug' => [
            'filename' => $file['name'],
            'extension' => $extension
        ]
    ]);
    exit;
}

error_log("File validation passed, proceeding to process upload");

// ============================================
// PROCESS UPLOAD
// ============================================

try {
    // Normalize the field name to what upload_whodat.php expects
    if ($found_field !== 'whodat_lua') {
        $_FILES['whodat_lua'] = $_FILES[$found_field];
        unset($_FILES[$found_field]);
    }

    // Set flag for JSON response
    $GLOBALS['API_REQUEST'] = true;

    // Capture output
    ob_start();

    // Include the upload handler
    include __DIR__ . '/../sections/upload_whodat.php';

    $output = ob_get_clean();

    error_log("Upload handler output length: " . strlen($output));
    error_log("Output sample: " . substr($output, 0, 500));

    // Check for success
    if (stripos($output, 'Upload Successful') !== false || stripos($output, '✅') !== false) {
        preg_match('/<strong>Character:<\/strong>\s*([^<]+)/', $output, $matches);
        $character = $matches[1] ?? 'Unknown';

        echo json_encode([
            'status' => 'success',
            'message' => 'Upload processed successfully',
            'character' => trim($character),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        preg_match('/<p><strong>Error:<\/strong>\s*([^<]+)/', $output, $matches);
        $error = $matches[1] ?? 'Upload processing failed';

        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => trim($error),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

} catch (Throwable $e) {
    ob_end_clean();

    error_log("API upload exception: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

exit;