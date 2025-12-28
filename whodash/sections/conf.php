<?php
// Character Configuration - Upload WhoDAT & Character Management

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Ensure database connection is available
if (!isset($pdo)) {
  require_once __DIR__ . '/../db.php';
}

$character_id = isset($_GET['character_id']) ? (int) $_GET['character_id'] : 0;

// Get the actual character name from the database
$char_name = 'Character'; // Default fallback
if ($character_id > 0 && isset($pdo) && isset($_SESSION['user_id'])) {
  try {
    $stmt = $pdo->prepare('SELECT name FROM characters WHERE id = ? AND user_id = ?');
    $stmt->execute([$character_id, $_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result && !empty($result['name'])) {
      $char_name = $result['name'];
    }
  } catch (Exception $e) {
    error_log("Error fetching character name: " . $e->getMessage());
    // Fallback to GET parameter if database query fails
    $char_name = isset($_GET['char_name']) ? $_GET['char_name'] : 'Character';
  }
} else {
  // Fallback to GET parameter
  $char_name = isset($_GET['char_name']) ? $_GET['char_name'] : 'Character';
}

$char_name_safe = htmlspecialchars($char_name, ENT_QUOTES, 'UTF-8');

// Debug logging
error_log("conf.php - Character ID: $character_id, Session User ID: " . ($_SESSION['user_id'] ?? 'not set') . ", Name: $char_name");
?>

<section id="tab-conf" class="tab-content" data-character-id="<?php echo $character_id; ?>"
  data-character-name="<?php echo $char_name_safe; ?>">
  <!-- Upload Section -->
  <div class="upload-card">
    <h2>📤 Upload WhoDAT.lua</h2>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'adventurer', ENT_QUOTES, 'UTF-8'); ?>!</p>

    <form id="whodatUploadForm" method="POST" action="/sections/upload_whodat.php" enctype="multipart/form-data">
      <div class="file-input-wrapper">
        <input type="file" name="whodat_lua" id="whodatFileInput" accept=".lua" required>
        <label for="whodatFileInput" class="file-label">Choose File</label>
        <span class="file-name" id="fileName">WhoDAT.lua</span>
      </div>

      <button type="submit" class="upload-btn">Upload Character Data</button>
    </form>
  </div>

  <!-- Character Management Section -->
  <div class="management-card">
    <h2>⚙️ Character Management</h2>
    <p class="section-subtitle">Manage your character data and settings</p>

    <div class="management-grid">
      <!-- Delete Character Card -->
      <div class="management-item danger-zone">
        <div class="management-icon">🗑️</div>
        <h3>Delete Character</h3>
        <p class="character-name-display"><?php echo $char_name_safe; ?></p>
        <p>Permanently remove this character and all associated data</p>
        <button id="deleteCharacterBtn" class="danger-btn">Delete Character</button>
      </div>

      <!-- Placeholder for future features -->
      <div class="management-item disabled">
        <div class="management-icon">🔗</div>
        <h3>Share Character</h3>
        <p>Create a public link to share your character profile</p>
        <button class="secondary-btn" disabled>Coming Soon</button>
      </div>

      <div class="management-item disabled">
        <div class="management-icon">📊</div>
        <h3>Export Data</h3>
        <p>Download your character data in various formats</p>
        <button class="secondary-btn" disabled>Coming Soon</button>
      </div>

      <div class="management-item disabled">
        <div class="management-icon">🔄</div>
        <h3>Sync Settings</h3>
        <p>Configure automatic data synchronization</p>
        <button class="secondary-btn" disabled>Coming Soon</button>
      </div>
    </div>
  </div>
</section>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>⚠️ Delete Character</h2>
    </div>

    <div class="modal-body">
      <div class="warning-box">
        <strong>⚠️ WARNING: This action cannot be undone!</strong>
        <p>You are about to permanently delete <strong id="deleteCharName"></strong> and ALL associated data including:
        </p>
        <ul>
          <li>Character progression and statistics</li>
          <li>Achievement history</li>
          <li>Item and equipment data</li>
          <li>Auction house records</li>
          <li>Reputation and companion data</li>
          <li>All historical timeseries data</li>
        </ul>
        <p class="emphasis">This data will be <strong>permanently erased</strong> and cannot be recovered.</p>
      </div>

      <div class="confirmation-input">
        <label for="confirmText">To confirm, type <strong>PERMANENT</strong> below:</label>
        <input type="text" id="confirmText" placeholder="Type PERMANENT" autocomplete="off">
        <p class="input-hint" id="confirmHint"></p>
      </div>
    </div>

    <div class="modal-footer">
      <button id="cancelDeleteBtn" class="cancel-btn">Cancel</button>
      <button id="confirmDeleteBtn" class="confirm-delete-btn" disabled>Continue to Final Confirmation</button>
    </div>
  </div>
</div>

<!-- Final Confirmation Modal -->
<div id="finalConfirmModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>🚨 Final Confirmation</h2>
    </div>

    <div class="modal-body">
      <div class="final-warning">
        <p class="large-text">Are you absolutely sure?</p>
        <p>Once you click "Delete Character", <strong id="finalCharName"></strong> will be <strong>permanently
            deleted</strong>.</p>
        <p class="emphasis">There is no way to undo this action.</p>
      </div>
    </div>

    <div class="modal-footer">
      <button id="saveCharacterBtn" class="save-btn">Save my character!</button>
      <button id="finalDeleteBtn" class="final-delete-btn">Delete Character</button>
    </div>
  </div>
</div>

<style>
  .upload-card,
  .management-card {
    max-width: 900px;
    margin: 40px auto;
    background: #fff;
    border-radius: 16px;
    padding: 40px;
    box-shadow: 0 4px 20px rgba(30, 60, 114, 0.12);
  }

  .upload-card h2,
  .management-card h2 {
    color: #2456a5;
    margin-bottom: 8px;
    font-size: 1.75rem;
  }

  .section-subtitle {
    color: #6e7f9b;
    margin-bottom: 32px;
    font-size: 0.95rem;
  }

  .upload-card p {
    color: #6e7f9b;
    margin-bottom: 32px;
  }

  .file-input-wrapper {
    position: relative;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .file-input-wrapper input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
  }

  .file-label {
    display: inline-block;
    padding: 10px 20px;
    background: #f0f4f8;
    color: #2456a5;
    border: 2px solid #d7e3fb;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.2s ease;
  }

  .file-label:hover {
    background: #e6eefb;
    border-color: #2456a5;
  }

  .file-name {
    color: #6e7f9b;
    font-size: 0.95rem;
  }

  .upload-btn {
    width: 100%;
    padding: 14px 28px;
    background: linear-gradient(135deg, #2456a5 0%, #1e4a8f 100%);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: 0 2px 8px rgba(36, 86, 165, 0.3);
  }

  .upload-btn:hover {
    background: linear-gradient(135deg, #1e4a8f 0%, #2456a5 100%);
    box-shadow: 0 4px 12px rgba(36, 86, 165, 0.4);
    transform: translateY(-1px);
  }

  .upload-btn:active {
    transform: translateY(0);
  }

  /* Management Grid */
  .management-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 24px;
  }

  .management-item {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    transition: all 0.2s ease;
  }

  .management-item.danger-zone {
    border-color: #fee2e2;
    background: #fef2f2;
  }

  .management-item.disabled {
    opacity: 0.6;
  }

  .management-icon {
    font-size: 2.5rem;
    margin-bottom: 12px;
  }

  .management-item h3 {
    color: #1e293b;
    font-size: 1.1rem;
    margin-bottom: 8px;
  }

  .character-name-display {
    font-weight: 700;
    color: #dc2626;
    font-size: 1.1rem;
    margin: 8px 0 12px 0;
  }

  .management-item p {
    color: #64748b;
    font-size: 0.875rem;
    margin-bottom: 16px;
    line-height: 1.4;
  }

  .danger-btn,
  .secondary-btn,
  .cancel-btn,
  .save-btn,
  .confirm-delete-btn,
  .final-delete-btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.95rem;
  }

  .danger-btn {
    background: #dc2626;
    color: white;
  }

  .danger-btn:hover {
    background: #b91c1c;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
  }

  .secondary-btn {
    background: #e2e8f0;
    color: #64748b;
  }

  .secondary-btn:disabled {
    cursor: not-allowed;
  }

  /* Modal Styles */
  .modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(4px);
  }

  .modal.active {
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .modal-content {
    background: white;
    border-radius: 16px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: modalSlideIn 0.3s ease;
  }

  @keyframes modalSlideIn {
    from {
      opacity: 0;
      transform: translateY(-20px);
    }

    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  .modal-header {
    padding: 24px 32px;
    border-bottom: 2px solid #f1f5f9;
  }

  .modal-header h2 {
    color: #dc2626;
    margin: 0;
    font-size: 1.5rem;
  }

  .modal-body {
    padding: 32px;
  }

  .warning-box {
    background: #fef2f2;
    border: 2px solid #fecaca;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
  }

  .warning-box strong {
    color: #dc2626;
    display: block;
    margin-bottom: 12px;
    font-size: 1.1rem;
  }

  .warning-box p {
    color: #7f1d1d;
    margin: 8px 0;
    line-height: 1.6;
  }

  .warning-box ul {
    color: #991b1b;
    margin: 12px 0 12px 20px;
    line-height: 1.8;
  }

  .warning-box .emphasis {
    font-weight: 600;
    color: #dc2626;
    margin-top: 16px;
  }

  .confirmation-input {
    margin-top: 24px;
  }

  .confirmation-input label {
    display: block;
    color: #1e293b;
    margin-bottom: 12px;
    font-weight: 500;
  }

  .confirmation-input strong {
    color: #dc2626;
    font-family: monospace;
    font-size: 1.05rem;
  }

  .confirmation-input input {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 1rem;
    font-family: monospace;
    transition: border-color 0.2s ease;
  }

  .confirmation-input input:focus {
    outline: none;
    border-color: #2456a5;
  }

  .confirmation-input input.error {
    border-color: #dc2626;
  }

  .confirmation-input input.success {
    border-color: #16a34a;
  }

  .input-hint {
    margin-top: 8px;
    font-size: 0.875rem;
    min-height: 20px;
  }

  .input-hint.error {
    color: #dc2626;
  }

  .input-hint.success {
    color: #16a34a;
  }

  .final-warning {
    text-align: center;
    padding: 20px;
  }

  .final-warning .large-text {
    font-size: 1.5rem;
    font-weight: 700;
    color: #dc2626;
    margin-bottom: 20px;
  }

  .final-warning p {
    color: #1e293b;
    margin: 12px 0;
    font-size: 1.05rem;
    line-height: 1.6;
  }

  .final-warning .emphasis {
    font-weight: 600;
    color: #dc2626;
    font-size: 1.1rem;
    margin-top: 20px;
  }

  .modal-footer {
    padding: 20px 32px;
    border-top: 2px solid #f1f5f9;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
  }

  .cancel-btn,
  .save-btn {
    background: #f1f5f9;
    color: #475569;
  }

  .cancel-btn:hover,
  .save-btn:hover {
    background: #e2e8f0;
  }

  .save-btn {
    background: #16a34a;
    color: white;
  }

  .save-btn:hover {
    background: #15803d;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
  }

  .confirm-delete-btn {
    background: #dc2626;
    color: white;
  }

  .confirm-delete-btn:disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
  }

  .confirm-delete-btn:not(:disabled):hover {
    background: #b91c1c;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
  }

  .final-delete-btn {
    background: #dc2626;
    color: white;
  }

  .final-delete-btn:hover {
    background: #b91c1c;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
  }

  .final-delete-btn:disabled {
    background: #e2e8f0;
    color: #94a3b8;
    cursor: not-allowed;
  }

  /* Upload Progress Modal */
  .upload-modal-content {
    max-width: 500px;
  }

  .upload-progress-wrapper {
    padding: 20px 0;
  }

  .progress-bar-container {
    width: 100%;
    height: 32px;
    background: #e2e8f0;
    border-radius: 16px;
    overflow: hidden;
    position: relative;
    margin-bottom: 16px;
  }

  .progress-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #2456a5 0%, #3b6fd9 100%);
    border-radius: 16px;
    transition: width 0.3s ease;
    position: relative;
    animation: progressShimmer 2s infinite;
  }

  @keyframes progressShimmer {
    0% {
      background-position: -100% 0;
    }

    100% {
      background-position: 200% 0;
    }
  }

  .progress-bar-fill {
    background: linear-gradient(90deg,
        #2456a5 0%,
        #3b6fd9 25%,
        #5a8fef 50%,
        #3b6fd9 75%,
        #2456a5 100%);
    background-size: 200% 100%;
  }

  .progress-stats {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
  }

  .progress-text {
    font-size: 2rem;
    font-weight: 700;
    color: #2456a5;
  }

  .progress-message {
    font-size: 1rem;
    color: #64748b;
    font-style: italic;
  }
</style>

<script>
  // Update file name display when file is selected
  document.getElementById('whodatFileInput')?.addEventListener('change', function (e) {
    const fileName = e.target.files[0]?.name || 'WhoDAT.lua';
    const fileNameEl = document.getElementById('fileName');
    if (fileNameEl) {
      fileNameEl.textContent = fileName;
    }
  });
</script>