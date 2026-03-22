<?php
// sections/onboarding.php - New user onboarding wizard
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username = htmlspecialchars($_SESSION['username'] ?? 'Adventurer', ENT_QUOTES, 'UTF-8');
?>
<link rel="stylesheet" href="/sections/onboarding-styles.css">

<div id="tab-onboarding" class="onboarding-root">

  <!-- Step tracker -->
  <div class="onboarding-steps">
    <div class="onboarding-step active" data-step="0">
      <div class="step-dot">1</div>
      <span class="step-label">Welcome</span>
    </div>
    <div class="onboarding-step-line"></div>
    <div class="onboarding-step" data-step="1">
      <div class="step-dot">2</div>
      <span class="step-label">Get the Addon</span>
    </div>
    <div class="onboarding-step-line"></div>
    <div class="onboarding-step" data-step="2">
      <div class="step-dot">3</div>
      <span class="step-label">Upload Data</span>
    </div>
    <div class="onboarding-step-line"></div>
    <div class="onboarding-step" data-step="3">
      <div class="step-dot">4</div>
      <span class="step-label">Next Steps</span>
    </div>
  </div>

  <!-- Slide viewport -->
  <div class="onboarding-viewport">

    <!-- Step 0: Welcome -->
    <div class="onboarding-slide active" data-slide="0">
      <div class="onboarding-card">
        <div class="onboarding-icon">⚔️</div>
        <h1 class="onboarding-title">Welcome to WhoDASH, <?= $username ?>!</h1>
        <p class="onboarding-body">
          WhoDASH is your personal WoW: Wrath of the Lich King (3.3.5a) character dashboard. It tracks your gear,
          currencies, professions, guild activity, auction house data, and much more — all
          pulled directly from your private server client with no third-party servers involved.
        </p>
        <p class="onboarding-body">
          Getting set up takes just a few minutes. This wizard will walk you through
          installing the <strong>WhoDAT</strong> addon and uploading your first character data.
        </p>
        <div class="onboarding-actions">
          <button class="onboarding-btn-primary" data-next="1">Let's Get Started →</button>
        </div>
      </div>
    </div>

    <!-- Step 1: Get the Addon -->
    <div class="onboarding-slide" data-slide="1">
      <div class="onboarding-card">
        <div class="onboarding-icon">🧩</div>
        <h2 class="onboarding-title">Step 1 — Install the WhoDAT Addon for WotLK 3.3.5a</h2>
        <p class="onboarding-body">
          WhoDASH gets its data from <strong>WhoDAT</strong>, a lightweight addon for
          World of Warcraft: Wrath of the Lich King (3.3.5a) on private servers
          that captures your character data while you play. You'll need to install it before
          you can upload anything.
        </p>

        <div class="onboarding-info-box">
          <div class="onboarding-info-icon">📁</div>
          <div>
            <strong>Where to install it:</strong>
            <div class="onboarding-path">World of Warcraft\3.3.5\Interface\AddOns\WhoDAT\</div>
            <p class="onboarding-path-note">
                Your WoW folder location depends on your private server client installation.
                Common locations include <code>C:\WoW\</code>, <code>C:\Games\WoW 3.3.5\</code>,
                or wherever you extracted your client. Check with your server's install guide if unsure.
              </p>
          </div>
        </div>

        <p class="onboarding-body">
          After installing, log into the game and play for a bit. WhoDAT will begin collecting
          your character data automatically in the background.
        </p>

        <div class="onboarding-actions">
          <a class="onboarding-btn-secondary" href="https://www.belmontlabs.dev/whodat.html" target="_blank" rel="noopener">
            ⬇ Download WhoDAT Addon
          </a>
          <button class="onboarding-btn-primary" data-next="2">I Have the Addon →</button>
        </div>
        <button class="onboarding-btn-back" data-prev="0">← Back</button>
      </div>
    </div>

    <!-- Step 2: Upload -->
    <div class="onboarding-slide" data-slide="2">
      <div class="onboarding-card">
        <div class="onboarding-icon">📤</div>
        <h2 class="onboarding-title">Step 2 — Upload Your First Data File</h2>
        <p class="onboarding-body">
          After playing with WhoDAT installed, it saves your character data to a file called
          <strong>WhoDAT.lua</strong>. Find it at the path below and upload it here.
        </p>

        <div class="onboarding-info-box">
          <div class="onboarding-info-icon">🗂️</div>
          <div>
            <strong>Where to find WhoDAT.lua:</strong>
            <div class="onboarding-path">World of Warcraft\3.3.5\WTF\Account\YOUR_ACCOUNT\SavedVariables\WhoDAT.lua</div>
            <p class="onboarding-path-note">
                Replace <code>YOUR_ACCOUNT</code> with your WoW account name as it appears
                in your WTF folder. The file is usually a few hundred KB after a play session.
              </p>
          </div>
        </div>

        <!-- Upload form - handled by conf.js -->
        <form id="whodatUploadForm" method="POST" action="/sections/upload_whodat.php" enctype="multipart/form-data">
          <div class="onboarding-upload-area" id="onboardingDropZone">
            <div class="upload-drop-icon">📂</div>
            <p class="upload-drop-text">Drag &amp; drop your <strong>WhoDAT.lua</strong> here</p>
            <p class="upload-drop-sub">or</p>
            <label for="whodatFileInput" class="onboarding-file-label">Browse Files</label>
            <input type="file" name="whodat_lua" id="whodatFileInput" accept=".lua" required style="display:none;">
            <p class="upload-selected-name" id="onboardingFileName"></p>
          </div>
          <button type="submit" class="onboarding-btn-primary onboarding-upload-submit" id="onboardingUploadBtn" disabled>
            Upload Character Data
          </button>
        </form>

        <div class="onboarding-actions" style="margin-top: 12px;">
          <button class="onboarding-btn-ghost" data-next="3">Skip for now →</button>
        </div>
        <button class="onboarding-btn-back" data-prev="1">← Back</button>
      </div>
    </div>

    <!-- Step 3: Next Steps -->
    <div class="onboarding-slide" data-slide="3">
      <div class="onboarding-card">
        <div class="onboarding-icon">🎉</div>
        <h2 class="onboarding-title">You're All Set!</h2>
        <p class="onboarding-body">
          Your character data is loaded and WhoDASH is ready to go. Here are a couple of
          optional things you can do to get even more out of the dashboard:
        </p>

        <div class="onboarding-next-cards">

          <div class="onboarding-next-card">
            <div class="next-card-icon">🔄</div>
            <div class="next-card-body">
              <h3>Automate Uploads with SyncDAT</h3>
              <p>
                Instead of manually uploading your <code>WhoDAT.lua</code> every session,
                <strong>SyncDAT</strong> is a companion desktop app that watches your
                SavedVariables folder and uploads automatically whenever the file changes.
              </p>
              <a class="onboarding-btn-secondary" href="https://www.belmontlabs.dev/uploader.html" target="_blank" rel="noopener">
                ⬇ Download SyncDAT
              </a>
            </div>
          </div>

          <div class="onboarding-next-card">
            <div class="next-card-icon">🔑</div>
            <div class="next-card-body">
              <h3>Set Up an API Key</h3>
              <p>
                SyncDAT uses an API key to authenticate uploads. You can generate one
                in the <strong>Config</strong> section of WhoDASH. Head there after
                installing SyncDAT to connect them together.
              </p>
              <button class="onboarding-btn-secondary" data-goto="conf">
                Go to Config
              </button>
            </div>
          </div>

        </div>

        <div class="onboarding-actions" style="margin-top: 28px;">
          <button class="onboarding-btn-primary" data-goto="dashboard">
            Go to My Dashboard →
          </button>
        </div>
      </div>
    </div>

  </div><!-- /viewport -->
</div>
