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

      <!-- Share Character Card -->
      <div class="management-item">
        <div class="management-icon">🔗</div>
        <h3>Share Character</h3>
        <p class="character-name-display"><?php echo $char_name_safe; ?></p>
        <p>Create a public link to share your character profile</p>
        <button id="shareCharacterBtn" class="secondary-btn">Share Character</button>
      </div>

      <div class="management-item disabled">
        <div class="management-icon">📊</div>
        <h3>Export Data</h3>
        <p>Download your character data in various formats</p>
        <button class="secondary-btn" disabled>Coming Soon</button>
      </div>
<!-- Bank Alt Card (inside .management-grid) -->
<div class="management-item" id="bankAltCard">
  <div class="management-icon">🏦</div>
  <h3>Bank Alt</h3>
  <p class="character-name-display"><?php echo $char_name_safe; ?>
  </p>
  <p>Designate this character as a Bank Alt and manage their public profile banner.</p>
  <button id="bankAltBtn" class="secondary-btn">Bank Alt Settings</button>
</div>

  </div>

  <!-- API Keys Full Section (Hidden until Phase 4) -->
  <div id="api-keys-section" class="management-card api-keys-card" style="display: none;">
    <h2>🔑 API Keys</h2>
    <p class="section-subtitle">Manage API keys for automated uploads via WhoDATUploader</p>

    <div class="api-keys-info">
      <p>
        <strong>📱 Windows App:</strong> Use the WhoDATUploader desktop application to automatically
        upload your WhoDAT.lua files.
      </p>
      <p>
        Generate an API key below, then configure it in the WhoDATUploader app to enable automatic uploads.
      </p>
    </div>

    <!-- Generate New Key Button -->
    <div class="api-key-actions">
      <button id="generateApiKeyBtn" class="primary-btn">
        ➕ Generate New API Key
      </button>
    </div>

    <!-- API Keys List -->
    <div id="apiKeysList" class="api-keys-list">
      <div class="loading-keys">
        <div class="spinner"></div>
        <p>Loading API keys...</p>
      </div>
    </div>
  </div>
</section>
<!-- Generate API Key Modal -->
<div id="generateApiKeyModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>🔑 Generate New API Key</h2>
    </div>

    <div class="modal-body">
      <div class="form-group">
        <label for="apiKeyName">
          <strong>Key Name</strong>
          <span class="label-hint">Give this key a descriptive name (e.g., "Home PC", "Work Laptop")</span>
        </label>
        <input type="text" id="apiKeyName" class="form-input" placeholder="My Desktop PC" maxlength="100">
      </div>

      <div class="form-group">
        <label for="apiKeyExpiry">
          <strong>Expiration (Optional)</strong>
          <span class="label-hint">Leave empty for no expiration</span>
        </label>
        <select id="apiKeyExpiry" class="form-select">
          <option value="">Never expires</option>
          <option value="30">30 days</option>
          <option value="90">90 days</option>
          <option value="180">180 days</option>
          <option value="365">1 year</option>
        </select>
      </div>
    </div>

    <div class="modal-footer">
      <button id="cancelGenerateBtn" class="secondary-btn">Cancel</button>
      <button id="confirmGenerateBtn" class="primary-btn">Generate Key</button>
    </div>
  </div>
</div>
<div id="bankAltModal" class="modal">
  <div class="modal-content modal-large">
    <div class="modal-header">
      <h2>🏦 Bank Alt Settings</h2>
    </div>

    <div class="modal-body">

      <!-- ── Status banner ───────────────────────────────────────────── -->
      <div class="share-info-box" id="bankAltStatusBox">
        <p>
          Character: <strong id="bankAltCharName"></strong>
        </p>
        <p id="bankAltStatusLine" style="margin:0;font-size:0.9rem;color:var(--text-muted,#888)">
          Checking status…
        </p>
      </div>

      <!-- ── Toggle row ──────────────────────────────────────────────── -->
      <div style="display:flex;align-items:center;justify-content:space-between;
                  padding:14px 0;border-bottom:1px solid var(--border-color,#333)">
        <div>
          <strong>🏦 Mark as Bank Alt</strong>
          <p style="margin:4px 0 0;font-size:0.85rem;color:var(--text-muted,#888)">
            Enables the Bank Alt public profile page. Works even without a guild.
          </p>
        </div>
        <button id="bankAltToggleBtn" class="secondary-btn" style="min-width:130px">
          Loading…
        </button>
      </div>

      <!-- ── Shared status (read-only, go to Share modal to change) ─── -->
      <div style="padding:12px 0;border-bottom:1px solid var(--border-color,#333)">
        <strong>🔗 Public Profile</strong>
        <p id="bankAltShareInfo" style="margin:6px 0 0;font-size:0.85rem;color:var(--text-muted,#888)">
          Checking share status…
        </p>
        <div id="bankAltShareUrlRow" style="display:none;margin-top:8px">
          <div class="url-display-group">
            <input type="text" id="bankAltShareUrl" class="form-input" readonly
                   style="font-size:0.8rem">
            <button id="bankAltCopyUrlBtn" class="copy-btn">Copy</button>
          </div>
          <div id="bankAltCopySuccess"
               style="display:none;color:#16a34a;font-size:0.8rem;margin-top:4px">
            ✅ Copied!
          </div>
        </div>
      </div>

      <!-- ── Screenshot upload ───────────────────────────────────────── -->
      <div style="padding:14px 0">
        <strong>📸 Banner Screenshot</strong>
        <p style="margin:4px 0 8px;font-size:0.85rem;color:var(--text-muted,#888)">
          Upload a screenshot (WebP · JPEG · PNG — max 4 MB, 16:9 recommended).
          When set, this replaces the random race/sex artwork on your public page.
          <strong>We strongly recommend converting to WebP</strong> before uploading
          — WebP is typically 60–80% smaller than PNG at equivalent quality.
        </p>

        <!-- Current screenshot preview -->
        <div id="bankAltScreenshotPreview" style="display:none;margin-bottom:10px">
          <img id="bankAltScreenshotImg"
               src="" alt="Current banner"
               style="width:100%;max-height:220px;object-fit:cover;
                      border-radius:6px;border:1px solid var(--border-color,#333)">
          <div style="margin-top:6px;display:flex;gap:8px;align-items:center">
            <span style="font-size:0.8rem;color:var(--text-muted,#888)">
              ✅ Custom screenshot active
            </span>
            <button id="bankAltRemoveScreenshotBtn" class="danger-btn"
                    style="padding:4px 12px;font-size:0.8rem">
              Remove
            </button>
          </div>
        </div>

        <!-- No screenshot message -->
        <div id="bankAltNoScreenshot"
             style="font-size:0.85rem;color:var(--text-muted,#888);
                    margin-bottom:10px;display:none">
          No custom screenshot — random race/sex artwork will be shown.
        </div>

        <!-- Upload input -->
        <div class="file-input-wrapper" id="bankAltUploadWrapper">
          <input type="file" id="bankAltFileInput"
                 accept="image/webp,image/jpeg,image/png,.webp,.jpg,.jpeg,.png">
          <label for="bankAltFileInput" class="file-label">Choose Screenshot</label>
          <span class="file-name" id="bankAltFileName">No file chosen</span>
        </div>

        <div id="bankAltUploadProgress"
             style="display:none;margin-top:8px;font-size:0.85rem;color:var(--text-muted,#888)">
          Uploading…
        </div>
        <div id="bankAltUploadError"
             style="display:none;color:#dc2626;font-size:0.85rem;margin-top:6px"></div>
      </div>

    </div><!-- /modal-body -->

    <div class="modal-footer">
      <button id="bankAltUploadBtn" class="primary-btn" style="display:none">
        Upload Screenshot
      </button>
      <button id="bankAltCloseBtn" class="secondary-btn">Close</button>
    </div>
  </div>
</div>
<!-- Show API Key Modal -->
<div id="showApiKeyModal" class="modal">
  <div class="modal-content modal-large">
    <div class="modal-header">
      <h2>✅ API Key Generated Successfully!</h2>
    </div>

    <div class="modal-body">
      <div class="success-box">
        <p>⚠️ <strong>IMPORTANT:</strong> Copy this key now! It won't be shown again.</p>
      </div>

      <div class="api-key-display">
        <label for="generatedApiKey"><strong>Your API Key:</strong></label>
        <div class="key-copy-group">
          <input type="text" id="generatedApiKey" class="key-input" readonly>
          <button id="copyApiKeyBtn" class="copy-btn">📋 Copy</button>
        </div>
        <div id="apiKeyCopySuccess" class="copy-success" style="display: none;">
          ✅ Copied to clipboard!
        </div>
      </div>

      <div class="next-steps-box">
        <h3>📋 Next Steps:</h3>
        <ol>
          <li>Copy the API key above</li>
          <li>Open WhoDATUploader on your computer</li>
          <li>Go to Settings and paste the API key</li>
          <li>The app will now automatically upload your WhoDAT.lua file</li>
        </ol>
      </div>
    </div>

    <div class="modal-footer">
      <button id="closeShowKeyBtn" class="primary-btn">I've Copied the Key</button>
    </div>
  </div>
</div>

<!-- Revoke API Key Modal -->
<div id="revokeApiKeyModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>⚠️ Revoke API Key</h2>
    </div>

    <div class="modal-body">
      <p>Are you sure you want to revoke this API key?</p>
      <p class="key-name-display" id="revokeKeyName"></p>
      <p><strong>This will:</strong></p>
      <ul>
        <li>Immediately stop this key from working</li>
        <li>Disconnect any apps using this key</li>
        <li>You can reactivate it later if needed</li>
      </ul>
    </div>

    <div class="modal-footer">
      <button id="cancelRevokeBtn" class="secondary-btn">Cancel</button>
      <button id="confirmRevokeBtn" class="danger-btn">Revoke Key</button>
    </div>
  </div>
</div>

<!-- Delete API Key Modal -->
<div id="deleteApiKeyModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>🗑️ Delete API Key</h2>
    </div>

    <div class="modal-body">
      <p>Are you sure you want to permanently delete this API key?</p>
      <p class="key-name-display" id="deleteKeyName"></p>
      <p><strong>Warning:</strong></p>
      <ul>
        <li>This action cannot be undone</li>
        <li>The key will be permanently deleted</li>
        <li>Any apps using this key will stop working immediately</li>
      </ul>
    </div>

    <div class="modal-footer">
      <button id="cancelDeleteApiKeyBtn" class="secondary-btn">Cancel</button>
      <button id="confirmDeleteApiKeyBtn" class="danger-btn">Delete Forever</button>
    </div>
  </div>
</div>
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
      <h2>ðŸš¨ Final Confirmation</h2>
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

<!-- Share Character Modal -->
<div id="shareModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2>🔗 Share Character</h2>
    </div>

    <div class="modal-body">
      <div class="share-info-box">
        <p>Create a public link to share <strong id="shareCharName"></strong> with your friends!</p>
        <p class="share-note">Your character will be visible to anyone with the link.</p>
      </div>

      <div class="share-options">
        <h3>Privacy Options</h3>
        <div class="checkbox-group">
          <label class="checkbox-label">
            <input type="checkbox" id="showCurrencies">
            <span>💰 Show Currencies Tab</span>
            <p class="option-description">Display your currency counts publicly</p>
          </label>
          <label class="checkbox-label">
            <input type="checkbox" id="showItems">
            <span>🎒 Show Items Tab</span>
            <p class="option-description">Display your item inventory publicly</p>
          </label>
          <label class="checkbox-label">
            <input type="checkbox" id="showSocial">
            <span>👥 Show Social Tab</span>
            <p class="option-description">Display your social and group data publicly</p>
          </label>
        </div>
      </div>

      <div id="shareUrlBox" class="share-url-box" style="display: none;">
        <label>📋 Public Link:</label>
        <div class="url-display-group">
          <input type="text" id="shareUrl" readonly>
          <button id="copyUrlBtn" class="copy-btn">Copy Link</button>
        </div>
        <p class="copy-success" id="copySuccess" style="display: none;">✅ Link copied to clipboard!</p>
      </div>
    </div>

    <div class="modal-footer">
      <button id="cancelShareBtn" class="cancel-btn">Cancel</button>
      <button id="confirmShareBtn" class="confirm-share-btn">Create Public Link</button>
      <button id="unshareBtn" class="unshare-btn" style="display: none;">Make Private</button>
    </div>
  </div>
</div>
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