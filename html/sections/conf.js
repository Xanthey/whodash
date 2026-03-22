/* WhoDASH Configuration Upload Handler */
(() => {
  const log = (...args) => console.log('[conf]', ...args);

  // WoW-themed loading messages
  const loadingMessages = {
    0: "Preparing upload...",
    5: "Whipping the peons...",
    10: "Summoning your character...",
    15: "Consulting the spirits...",
    20: "Polishing your armor...",
    25: "Counting your gold...",
    30: "Reading ancient runes...",
    35: "Brewing a health potion...",
    40: "Taming your mount...",
    45: "Sharpening your blade...",
    50: "Deciphering talent trees...",
    55: "Organizing your bags...",
    60: "Calculating DPS...",
    65: "Enchanting your gear...",
    70: "Updating quest log...",
    75: "Checking auction house...",
    80: "Syncing with the servers...",
    85: "Applying buffs...",
    90: "Finalizing character data...",
    95: "Almost there...",
    100: "Complete!"
  };

  function getMessageForProgress(percent) {
    const keys = Object.keys(loadingMessages).map(Number).sort((a, b) => b - a);
    for (const key of keys) {
      if (percent >= key) return loadingMessages[key];
    }
    return loadingMessages[0];
  }

  function showProgressModal() {
    // Create modal overlay
    const modal = document.createElement('div');
    modal.id = 'uploadProgressModal';
    modal.className = 'modal active';
    
    modal.innerHTML = `
      <div class="modal-content upload-modal-content">
        <div class="modal-header">
          <h2>📤 Uploading WhoDAT Data</h2>
        </div>
        
        <div class="modal-body">
          <div class="upload-progress-wrapper">
            <div class="progress-bar-container">
              <div class="progress-bar-fill" id="uploadProgressBar"></div>
            </div>
            <div class="progress-stats">
              <div class="progress-text" id="uploadProgressText">0%</div>
              <div class="progress-message" id="uploadProgressMessage">Preparing upload...</div>
            </div>
          </div>
        </div>
      </div>
    `;
    
    document.body.appendChild(modal);
    
    return {
      modal: modal,
      bar: document.getElementById('uploadProgressBar'),
      text: document.getElementById('uploadProgressText'),
      message: document.getElementById('uploadProgressMessage')
    };
  }

  function updateProgress(elements, percent) {
    if (!elements) return;
    
    elements.bar.style.width = percent + '%';
    elements.text.textContent = percent + '%';
    elements.message.textContent = getMessageForProgress(percent);
  }

function simulateProgress(elements, duration = 30000) {
  let progress = 0;
  const interval = 200;
  const increment = 90 / (duration / interval);
  
  // Track when we started
  const startTime = Date.now();

  const timer = setInterval(() => {
    progress = Math.min(90, progress + increment);
    updateProgress(elements, Math.round(progress));
    
    if (progress >= 90) {
      clearInterval(timer);
      // Pass the start time so we can be adaptive
      startAdaptiveFinish(elements, startTime);
    }
  }, interval);

  return timer;
}

// NEW: Adaptive finish that adjusts to actual server processing time
function startAdaptiveFinish(elements, startTime) {
  const { bar, text, message } = elements;
  
  let percent = 90;
  let lastUpdate = Date.now();
  
  const finishMessages = [
    'Finalizing character data...',
    'Processing events...',
    'Almost there...',
    'Wrapping up...',
    'Just a moment longer...'
  ];
  let messageIndex = 0;
  
  function animate() {
    if (percent < 99) {
      const now = Date.now();
      const elapsed = now - startTime;
      
      // Adaptive speed: if it's been a long time, move slower
      // If it's been quick, move faster
      let delay;
      if (elapsed < 10000) {
        // Fast server (< 10s): move slowly to avoid hitting 99% too fast
        delay = 3000;
      } else if (elapsed < 30000) {
        // Medium server (10-30s): moderate speed
        delay = 2000;
      } else {
        // Slow server (30s+): move slowly but with visual feedback
        delay = 4000;
      }
      
      percent++;
      updateProgress(elements, percent);
      
      // Update message every 2 seconds
      if (now - lastUpdate > 2000) {
        if (message) {
          message.textContent = finishMessages[messageIndex % finishMessages.length];
          messageIndex++;
        }
        lastUpdate = now;
      }
      
      // Color transition: blue -> cyan -> green
      if (bar) {
        const progress = (percent - 90) / 9;
        
        // Smooth color interpolation
        const r = Math.round(36 - (36 * progress));      // 36 -> 0
        const g = Math.round(86 + (89 * progress));      // 86 -> 175
        const b = Math.round(165 - (85 * progress));     // 165 -> 80
        
        bar.style.background = `linear-gradient(90deg, 
          rgb(${r}, ${g}, ${b}), 
          rgb(${Math.max(0, r-20)}, ${Math.min(255, g+20)}, ${b})
        )`;
        bar.style.boxShadow = `0 2px 8px rgba(${r}, ${g}, ${b}, 0.4)`;
      }
      
      setTimeout(animate, delay);
    } else {
      // At 99%, add a subtle pulse
      if (bar) {
        bar.style.animation = 'pulse 1.5s ease-in-out infinite';
      }
      if (message) {
        message.textContent = 'Completing upload...';
      }
    }
  }
  
  setTimeout(animate, 1000);
}

  function showResultModal(success, message) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    
    
const iconAndColor = success
  ? {
      icon: '✔', // or '\2714'
      color: '#16a34a',
      bgGradient: 'linear-gradient(135deg, #e9f7ef 0%, #c8e6d0 100%)',
      borderColor: '#2e7d32'
    }
  : {
      icon: '✖', // or '\2716'
      color: '#dc2626',
      bgGradient: 'linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%)',
      borderColor: '#d32f2f'
    };

    modal.innerHTML = `
      <div class="modal-content">
        <div class="modal-body">
          <div style="background: ${iconAndColor.bgGradient}; border: 2px solid ${iconAndColor.borderColor}; border-radius: 12px; padding: 40px; text-align: center;">
            <div style="font-size: 3rem; color: ${iconAndColor.color}; margin-bottom: 20px;">${iconAndColor.icon}</div>
            <h2 style="color: ${iconAndColor.color}; margin-bottom: 20px;">${success ? 'Upload Complete!' : 'Upload Failed'}</h2>
            <div style="color: #1e293b; margin-bottom: 24px;">${message}</div>
            <button id="closeResultModal" style="background: ${iconAndColor.color}; color: white; border: none; padding: 12px 32px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 1rem;">
              ${success ? 'Continue' : 'Try Again'}
            </button>
          </div>
        </div>
      </div>
    `;
    
    document.body.appendChild(modal);
    
    document.getElementById('closeResultModal').addEventListener('click', () => {
      modal.remove();
      if (success) {
        // Close the modal and navigate back to root/dashboard
        // Use parent.location to navigate the parent frame, not the iframe
        if (window.parent && window.parent !== window) {
          // We're in an iframe, navigate the parent
          window.parent.location.href = '/';
        } else {
          // We're not in an iframe, just navigate normally
          window.location.href = '/';
        }
      }
    });
  }

  function handleUploadSubmit(event, form) {
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
    
    const fileInput = form.querySelector('input[type="file"]');
    
    if (!fileInput || !fileInput.files[0]) {
      alert('Please select a file to upload');
      return false;
    }

    log('Starting upload...');

    // Show progress modal
    const progressElements = showProgressModal();
    if (!progressElements) {
      alert('Error: Could not show progress modal');
      return false;
    }

    // Start simulated progress
    const timer = simulateProgress(progressElements);

    // Create FormData and upload
    const formData = new FormData(form);
    
    fetch(form.action, {
      method: 'POST',
      body: formData,
      credentials: 'include'
    })
    .then(response => {
      clearInterval(timer);
      
      // Jump to 95% when server responds
      updateProgress(progressElements, 95);
      
      if (!response.ok) {
        throw new Error(`Upload failed: ${response.status}`);
      }
      
      return response.text();
    })
    .then(html => {
      updateProgress(progressElements, 100);
      
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');
      
      const successDiv = doc.querySelector('.success-message');
      const errorDiv = doc.querySelector('.error-message');
      
      setTimeout(() => {
        // Remove progress modal
        progressElements.modal.remove();
        
        if (successDiv) {
          // Extract success message text
          const messageText = successDiv.textContent || 'Character data uploaded successfully!';
          showResultModal(true, messageText);
        } else if (errorDiv) {
          // Extract error message text
          const messageText = errorDiv.textContent || 'Upload failed. Please try again.';
          showResultModal(false, messageText);
        } else {
          // No specific message, assume success
          showResultModal(true, 'Character data uploaded successfully!');
        }
      }, 800);
    })
    .catch(error => {
      log('Upload error:', error);
      clearInterval(timer);
      
      setTimeout(() => {
        progressElements.modal.remove();
        showResultModal(false, 'Upload failed: ' + error.message);
      }, 500);
    });
    
    return false;
  }

  // Use event delegation at document level to catch ANY form submit
  document.addEventListener('submit', (event) => {
    const form = event.target;
    
    // Check if this is the WhoDAT upload form
    if (form && (form.id === 'whodatUploadForm' || form.action.includes('upload_whodat'))) {
      log('Upload intercepted');
      
      // CRITICAL: Stop ALL propagation immediately
      event.preventDefault();
      event.stopPropagation();
      event.stopImmediatePropagation();
      
      return handleUploadSubmit(event, form);
    }
  }, true); // Use capture phase to intercept BEFORE main.js

  log('Upload handler loaded');

  // ===== Character Deletion Functionality =====
  function initializeCharacterDeletion() {
    const deleteModal = document.getElementById('deleteModal');
    const finalConfirmModal = document.getElementById('finalConfirmModal');
    const deleteBtn = document.getElementById('deleteCharacterBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const confirmText = document.getElementById('confirmText');
    const confirmHint = document.getElementById('confirmHint');
    const saveCharacterBtn = document.getElementById('saveCharacterBtn');
    const finalDeleteBtn = document.getElementById('finalDeleteBtn');

    // Get character info from section data attributes
    const section = document.querySelector('#tab-conf');
    const characterId = section?.dataset?.characterId;
    const characterName = section?.dataset?.characterName;

    log('Deletion system initialized', { characterId, characterName });

    // Open first modal
    if (deleteBtn) {
      deleteBtn.addEventListener('click', () => {
        log('Delete button clicked');
        
        if (!characterId || !characterName) {
          alert('Error: Character information not found');
          return;
        }

        document.getElementById('deleteCharName').textContent = characterName;
        document.getElementById('finalCharName').textContent = characterName;
        confirmText.value = '';
        confirmHint.textContent = '';
        confirmDeleteBtn.disabled = true;
        deleteModal.classList.add('active');
        
        log('First modal opened');
      });
    } else {
      log('Delete button not found!');
    }

    // Close first modal
    if (cancelDeleteBtn) {
      cancelDeleteBtn.addEventListener('click', () => {
        deleteModal.classList.remove('active');
      });
    }

    // Validate confirmation text
    if (confirmText) {
      confirmText.addEventListener('input', (e) => {
        const value = e.target.value;
        
        if (value === 'PERMANENT') {
          confirmText.classList.remove('error');
          confirmText.classList.add('success');
          confirmHint.textContent = 'Confirmation accepted';
          confirmHint.classList.remove('error');
          confirmHint.classList.add('success');
          confirmDeleteBtn.disabled = false;
        } else if (value.length > 0) {
          confirmText.classList.add('error');
          confirmText.classList.remove('success');
          confirmHint.textContent = 'Please type exactly: PERMANENT';
          confirmHint.classList.add('error');
          confirmHint.classList.remove('success');
          confirmDeleteBtn.disabled = true;
        } else {
          confirmText.classList.remove('error', 'success');
          confirmHint.textContent = '';
          confirmHint.classList.remove('error', 'success');
          confirmDeleteBtn.disabled = true;
        }
      });
    }

    // Open final confirmation modal
    if (confirmDeleteBtn) {
      confirmDeleteBtn.addEventListener('click', () => {
        deleteModal.classList.remove('active');
        finalConfirmModal.classList.add('active');
      });
    }

    // Cancel from final modal (Save character button)
    if (saveCharacterBtn) {
      saveCharacterBtn.addEventListener('click', () => {
        finalConfirmModal.classList.remove('active');
      });
    }

    // Execute deletion
    if (finalDeleteBtn) {
      finalDeleteBtn.addEventListener('click', async () => {
        finalDeleteBtn.disabled = true;
        finalDeleteBtn.textContent = 'Deleting...';

        try {
          const response = await fetch('/sections/delete_character.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            credentials: 'include',
            body: JSON.stringify({
              character_id: parseInt(characterId),
              confirmation: 'PERMANENT'
            })
          });

          const data = await response.json();

          if (!response.ok) {
            throw new Error(data.error || data.message || 'Delete failed');
          }

          // Success - show message and redirect
          finalConfirmModal.classList.remove('active');
          
          const successHTML = `
            <div style="background: linear-gradient(135deg, #e9f7ef 0%, #c8e6d0 100%); border: 2px solid #2e7d32; border-radius: 12px; padding: 40px; margin: 40px auto; max-width: 600px; text-align: center; color: #1b5e20;">
              <h2 style="color: #2e7d32; margin-bottom: 20px;">✔ Character Deleted</h2>
              <p style="font-size: 1.1rem; margin-bottom: 20px;">${data.message}</p>
              <p>Redirecting to character selection...</p>
            </div>
          `;
          
          const sectionEl = document.querySelector('#tab-conf');
          if (sectionEl) {
            sectionEl.innerHTML = successHTML;
          }

          // Redirect after 2 seconds
          setTimeout(() => {
            window.location.href = '/';
          }, 2000);

        } catch (error) {
          console.error('[conf] Delete error:', error);
          alert('Failed to delete character: ' + error.message);
          finalDeleteBtn.disabled = false;
          finalDeleteBtn.textContent = 'Delete Character';
        }
      });
    }

    // Close modals when clicking outside
    [deleteModal, finalConfirmModal].forEach(modal => {
      if (modal) {
        modal.addEventListener('click', (e) => {
          if (e.target === modal) {
            modal.classList.remove('active');
          }
        });
      }
    });
  }

  // Initialize deletion when conf section is loaded
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'conf') {
      log('Conf section loaded, initializing deletion system');
      setTimeout(initializeCharacterDeletion, 100);
    }
  });

  // Also initialize on direct page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (document.querySelector('#tab-conf')) {
        log('Page loaded with conf section, initializing deletion system');
        setTimeout(initializeCharacterDeletion, 100);
      }
    });
  } else {
    if (document.querySelector('#tab-conf')) {
      log('Conf section already in DOM, initializing deletion system');
      setTimeout(initializeCharacterDeletion, 100);
    }
  }


  // ===== Character Sharing Functionality =====
  function initializeCharacterSharing() {
    const shareModal = document.getElementById('shareModal');
    const shareBtn = document.getElementById('shareCharacterBtn');
    const cancelShareBtn = document.getElementById('cancelShareBtn');
    const confirmShareBtn = document.getElementById('confirmShareBtn');
    const unshareBtn = document.getElementById('unshareBtn');
    const copyUrlBtn = document.getElementById('copyUrlBtn');
    
    const section = document.querySelector('#tab-conf');
    const characterId = section?.dataset?.characterId;
    const characterName = section?.dataset?.characterName;
    
    let currentShareStatus = null;
    
    log('Sharing system initialized', { characterId, characterName });

    // Open share modal
    if (shareBtn) {
      shareBtn.addEventListener('click', async () => {
        log('Share button clicked');
        
        if (!characterId || !characterName) {
          alert('Character information not available');
          return;
        }
        
        // Set character name
        document.getElementById('shareCharName').textContent = characterName;
        
        // Check if character is already shared
        try {
          const response = await fetch('/get_share_status.php?character_id=' + characterId);
          const data = await response.json();
          
          if (data.is_shared) {
            currentShareStatus = data;
document.getElementById('showCurrencies').checked = data.show_currencies;
            document.getElementById('showItems').checked = data.show_items;
            document.getElementById('showSocial').checked = data.show_social;
            document.getElementById('shareUrl').value = data.public_url;
            document.getElementById('shareUrlBox').style.display = 'block';
            confirmShareBtn.textContent = 'Update Settings';
            unshareBtn.style.display = 'inline-block';
          } else {
            currentShareStatus = null;
document.getElementById('showCurrencies').checked = false;
            document.getElementById('showItems').checked = false;
            document.getElementById('showSocial').checked = false;
            document.getElementById('shareUrlBox').style.display = 'none';
            confirmShareBtn.textContent = 'Create Public Link';
            unshareBtn.style.display = 'none';
          }
        } catch (error) {
          log('Error checking share status:', error);
        }
        
        shareModal.classList.add('active');
      });
    } else {
      log('Share button not found!');
    }

    // Cancel share
    if (cancelShareBtn) {
      cancelShareBtn.addEventListener('click', () => {
        shareModal.classList.remove('active');
        const copySuccess = document.getElementById('copySuccess');
        if (copySuccess) copySuccess.style.display = 'none';
      });
    }

    // Confirm share
    if (confirmShareBtn) {
      confirmShareBtn.addEventListener('click', async () => {
const showCurrencies = document.getElementById('showCurrencies').checked;
        const showItems = document.getElementById('showItems').checked;
        const showSocial = document.getElementById('showSocial').checked;
        
        if (!characterId) {
          alert('Character information not available');
          return;
        }
        
        try {
          confirmShareBtn.disabled = true;
          confirmShareBtn.textContent = 'Creating...';
          
          const response = await fetch('/share_character_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              character_id: parseInt(characterId),
              action: 'share',
show_currencies: showCurrencies,
              show_items: showItems,
              show_social: showSocial
            })
          });
          
          const data = await response.json();
          
          if (data.success) {
            // Show the URL
            document.getElementById('shareUrl').value = data.public_url;
            document.getElementById('shareUrlBox').style.display = 'block';
            confirmShareBtn.textContent = 'Update Settings';
            unshareBtn.style.display = 'inline-block';
            currentShareStatus = data;
            
            // Show success message briefly
            const tempMsg = document.createElement('p');
            tempMsg.textContent = '✅ ' + data.message;
            tempMsg.style.color = '#16a34a';
            tempMsg.style.fontWeight = '500';
            tempMsg.style.marginTop = '12px';
            document.querySelector('#shareModal .modal-body').appendChild(tempMsg);
            
            setTimeout(() => tempMsg.remove(), 3000);
            
            log('Character shared successfully');
          } else {
            alert('Error: ' + (data.error || 'Failed to share character'));
          }
        } catch (error) {
          log('Error sharing character:', error);
          alert('Error sharing character: ' + error.message);
        } finally {
          confirmShareBtn.disabled = false;
          confirmShareBtn.textContent = currentShareStatus ? 'Update Settings' : 'Create Public Link';
        }
      });
    }

    // Unshare character
    if (unshareBtn) {
      unshareBtn.addEventListener('click', async () => {
        if (!confirm(`Are you sure you want to make ${characterName} private? The public link will stop working.`)) {
          return;
        }
        
        try {
          unshareBtn.disabled = true;
          unshareBtn.textContent = 'Processing...';
          
          const response = await fetch('/share_character_api.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              character_id: parseInt(characterId),
              action: 'unshare'
            })
          });
          
          const data = await response.json();
          
          if (data.success) {
            // Reset the modal
            document.getElementById('shareUrlBox').style.display = 'none';
document.getElementById('showCurrencies').checked = false;
            document.getElementById('showItems').checked = false;
            document.getElementById('showSocial').checked = false;
            confirmShareBtn.textContent = 'Create Public Link';
            unshareBtn.style.display = 'none';
            currentShareStatus = null;
            
            alert('✅' + data.message);
            log('Character unshared successfully');
          } else {
            alert('Error: ' + (data.error || 'Failed to unshare character'));
          }
        } catch (error) {
          log('Error unsharing character:', error);
          alert('Error: ' + error.message);
        } finally {
          unshareBtn.disabled = false;
          unshareBtn.textContent = 'Make Private';
        }
      });
    }

    // Copy URL to clipboard
    if (copyUrlBtn) {
      copyUrlBtn.addEventListener('click', async () => {
        const urlInput = document.getElementById('shareUrl');
        
        try {
          await navigator.clipboard.writeText(urlInput.value);
          document.getElementById('copySuccess').style.display = 'block';
          copyUrlBtn.textContent = '✅ Copied!';
          
          setTimeout(() => {
            copyUrlBtn.textContent = 'Copy Link';
            document.getElementById('copySuccess').style.display = 'none';
          }, 3000);
        } catch (error) {
          // Fallback for older browsers
          urlInput.select();
          document.execCommand('copy');
          document.getElementById('copySuccess').style.display = 'block';
          copyUrlBtn.textContent = '✅ Copied!';
          
          setTimeout(() => {
            copyUrlBtn.textContent = 'Copy Link';
            document.getElementById('copySuccess').style.display = 'none';
          }, 3000);
        }
      });
    }

    // Close modal when clicking outside
    if (shareModal) {
      shareModal.addEventListener('click', (e) => {
        if (e.target === shareModal) {
          shareModal.classList.remove('active');
          const copySuccess = document.getElementById('copySuccess');
          if (copySuccess) copySuccess.style.display = 'none';
        }
      });
    }
  }
function initializeBankAlt() {
  const modal        = document.getElementById('bankAltModal');
  const openBtn      = document.getElementById('bankAltBtn');
  const closeBtn     = document.getElementById('bankAltCloseBtn');
  const toggleBtn    = document.getElementById('bankAltToggleBtn');
  const charNameEl   = document.getElementById('bankAltCharName');
  const statusLine   = document.getElementById('bankAltStatusLine');
  const shareInfo    = document.getElementById('bankAltShareInfo');
  const shareUrlRow  = document.getElementById('bankAltShareUrlRow');
  const shareUrlEl   = document.getElementById('bankAltShareUrl');
  const copyUrlBtn   = document.getElementById('bankAltCopyUrlBtn');
  const copySuccess  = document.getElementById('bankAltCopySuccess');

  // Screenshot elements
  const fileInput        = document.getElementById('bankAltFileInput');
  const fileNameEl       = document.getElementById('bankAltFileName');
  const uploadBtn        = document.getElementById('bankAltUploadBtn');
  const uploadProgress   = document.getElementById('bankAltUploadProgress');
  const uploadError      = document.getElementById('bankAltUploadError');
  const screenshotPreview = document.getElementById('bankAltScreenshotPreview');
  const screenshotImg    = document.getElementById('bankAltScreenshotImg');
  const noScreenshot     = document.getElementById('bankAltNoScreenshot');
  const removeBtn        = document.getElementById('bankAltRemoveScreenshotBtn');

  const section     = document.querySelector('#tab-conf');
  const characterId = section?.dataset?.characterId;
  const characterName = section?.dataset?.characterName;

  if (!modal || !openBtn) {
    log('Bank Alt modal or button not found — skipping init');
    return;
  }

  let currentStatus = null; // cached API response

  // ── Helpers ──────────────────────────────────────────────────────────────
  function setError(msg) {
    uploadError.textContent = msg;
    uploadError.style.display = msg ? 'block' : 'none';
  }

  function renderStatus(data) {
    currentStatus = data;

    // Toggle button
    toggleBtn.disabled = false;
    if (data.is_bank_alt) {
      toggleBtn.textContent = '✅ Disable Bank Alt';
      toggleBtn.classList.add('danger-btn');
      toggleBtn.classList.remove('secondary-btn');
      statusLine.textContent = '🏦 This character is marked as a Bank Alt.';
      statusLine.style.color = '#16a34a';
    } else {
      toggleBtn.textContent = '🏦 Enable Bank Alt';
      toggleBtn.classList.remove('danger-btn');
      toggleBtn.classList.add('secondary-btn');
      statusLine.textContent = 'Not a Bank Alt.';
      statusLine.style.color = '';
    }

    if (data.guild_flagged) {
      statusLine.textContent += ' (Also designated by guild.)';
    }

    // Share status (read-only; users change share settings via the Share modal)
    if (data.is_shared) {
      shareInfo.textContent = '✅ Bank Alt profile is public.';
      shareInfo.style.color = '#16a34a';
      shareUrlEl.value = data.share_url || '';
      shareUrlRow.style.display = 'block';
    } else {
      shareInfo.textContent = '🔒 Not shared publicly. Use the Share modal to create a public link.';
      shareInfo.style.color = '';
      shareUrlRow.style.display = 'none';
    }

    // Screenshot
    if (data.has_screenshot && data.screenshot_url) {
      screenshotImg.src = data.screenshot_url + '?t=' + Date.now();
      screenshotPreview.style.display = 'block';
      noScreenshot.style.display = 'none';
    } else {
      screenshotPreview.style.display = 'none';
      noScreenshot.style.display = 'block';
    }
  }

  async function loadStatus() {
    toggleBtn.textContent = 'Loading…';
    toggleBtn.disabled = true;
    shareInfo.textContent = 'Checking…';
    try {
      const res  = await fetch('/bank_alt_api.php?action=status&character_id=' + characterId);
      const data = await res.json();
      if (data.success) {
        renderStatus(data);
      } else {
        statusLine.textContent = '⚠️ ' + (data.error || 'Could not load status.');
        toggleBtn.textContent = 'Error';
      }
    } catch (err) {
      log('Bank Alt status error:', err);
      statusLine.textContent = '⚠️ Network error.';
      toggleBtn.textContent = 'Error';
    }
  }

  // ── Open modal ────────────────────────────────────────────────────────────
  openBtn.addEventListener('click', async () => {
    if (!characterId || !characterName) {
      alert('Character information not available.');
      return;
    }
    charNameEl.textContent = characterName;
    setError('');
    uploadBtn.style.display = 'none';
    fileNameEl.textContent = 'No file chosen';
    fileInput.value = '';
    modal.classList.add('active');
    await loadStatus();
  });

  // ── Close modal ───────────────────────────────────────────────────────────
  closeBtn.addEventListener('click', () => modal.classList.remove('active'));
  modal.addEventListener('click', e => {
    if (e.target === modal) modal.classList.remove('active');
  });

  // ── Toggle bank alt flag ──────────────────────────────────────────────────
  toggleBtn.addEventListener('click', async () => {
    if (!characterId) return;
    try {
      toggleBtn.disabled = true;
      toggleBtn.textContent = 'Updating…';
      const res = await fetch('/bank_alt_api.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/json' },
        body:    JSON.stringify({ action: 'toggle', character_id: parseInt(characterId) }),
      });
      const data = await res.json();
      if (data.success) {
        // Reload fresh status after toggle
        await loadStatus();
      } else {
        alert('Error: ' + (data.error || 'Failed to update Bank Alt flag.'));
        toggleBtn.disabled = false;
        toggleBtn.textContent = currentStatus?.is_bank_alt ? '✅ Disable Bank Alt' : '🏦 Enable Bank Alt';
      }
    } catch (err) {
      log('Toggle error:', err);
      alert('Network error: ' + err.message);
      toggleBtn.disabled = false;
    }
  });

  // ── File input change → show Upload button ────────────────────────────────
  fileInput.addEventListener('change', () => {
    const file = fileInput.files[0];
    if (file) {
      fileNameEl.textContent = file.name;
      uploadBtn.style.display = 'inline-block';
      setError('');
    } else {
      fileNameEl.textContent = 'No file chosen';
      uploadBtn.style.display = 'none';
    }
  });

  // ── Upload screenshot ─────────────────────────────────────────────────────
  uploadBtn.addEventListener('click', async () => {
    const file = fileInput.files[0];
    if (!file || !characterId) return;

    // Client-side size check (4 MB)
    if (file.size > 4 * 1024 * 1024) {
      setError('File too large. Maximum 4 MB. Consider converting to WebP first.');
      return;
    }

    const allowed = ['image/webp', 'image/jpeg', 'image/png'];
    if (!allowed.includes(file.type)) {
      setError('Please choose a WebP, JPEG, or PNG image.');
      return;
    }

    const fd = new FormData();
    fd.append('action',       'upload_screenshot');
    fd.append('character_id', characterId);
    fd.append('screenshot',   file);

    try {
      setError('');
      uploadBtn.disabled    = true;
      uploadBtn.textContent = 'Uploading…';
      uploadProgress.style.display = 'block';

      const res  = await fetch('/bank_alt_api.php', { method: 'POST', body: fd });
      const data = await res.json();

      if (data.success) {
        fileInput.value        = '';
        fileNameEl.textContent = 'No file chosen';
        uploadBtn.style.display = 'none';
        uploadProgress.style.display = 'none';
        // Refresh status to show new screenshot
        await loadStatus();
      } else {
        setError(data.error || 'Upload failed.');
        uploadProgress.style.display = 'none';
      }
    } catch (err) {
      log('Screenshot upload error:', err);
      setError('Network error: ' + err.message);
      uploadProgress.style.display = 'none';
    } finally {
      uploadBtn.disabled    = false;
      uploadBtn.textContent = 'Upload Screenshot';
    }
  });

  // ── Remove screenshot ─────────────────────────────────────────────────────
  if (removeBtn) {
    removeBtn.addEventListener('click', async () => {
      if (!confirm('Remove the custom screenshot? The random default artwork will be used instead.')) return;
      try {
        removeBtn.disabled    = true;
        removeBtn.textContent = 'Removing…';
        const res  = await fetch('/bank_alt_api.php', {
          method:  'POST',
          headers: { 'Content-Type': 'application/json' },
          body:    JSON.stringify({ action: 'remove_screenshot', character_id: parseInt(characterId) }),
        });
        const data = await res.json();
        if (data.success) {
          await loadStatus();
        } else {
          alert('Error: ' + (data.error || 'Could not remove screenshot.'));
        }
      } catch (err) {
        alert('Network error: ' + err.message);
      } finally {
        removeBtn.disabled    = false;
        removeBtn.textContent = 'Remove';
      }
    });
  }

  // ── Copy share URL ────────────────────────────────────────────────────────
  if (copyUrlBtn) {
    copyUrlBtn.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(shareUrlEl.value);
      } catch {
        shareUrlEl.select();
        document.execCommand('copy');
      }
      copySuccess.style.display = 'block';
      copyUrlBtn.textContent    = '✅ Copied!';
      setTimeout(() => {
        copySuccess.style.display = 'none';
        copyUrlBtn.textContent    = 'Copy';
      }, 3000);
    });
  }
}
// Initialize sharing when conf section is loaded
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'conf') {
      log('Conf section loaded, initializing sharing system');
      setTimeout(initializeCharacterSharing, 100);
      setTimeout(initializeBankAlt, 100);  // ← add this
    }
  });

  // Also initialize on direct page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (document.querySelector('#tab-conf')) {
        log('Page loaded with conf section, initializing sharing system');
        setTimeout(initializeCharacterSharing, 100);
        setTimeout(initializeBankAlt, 100);  // ← add this
      }
    });
  } else {
    if (document.querySelector('#tab-conf')) {
      log('Conf section already in DOM, initializing sharing system');
      setTimeout(initializeCharacterSharing, 100);
      setTimeout(initializeBankAlt, 100);  // ← add this
    }
  }
  // ============================================
  // API KEY MANAGEMENT
  // ============================================
  
  let currentApiKeys = [];
  let selectedKeyForAction = null;

  function initializeApiKeyManagement() {
    const log = (...args) => console.log('[API Keys]', ...args);
    log('Initializing API key management');

    // Show the API keys section (remove display:none)
    const apiKeysSection = document.getElementById('api-keys-section');
    if (apiKeysSection) {
      apiKeysSection.style.display = 'block';
    }

    // Get modal elements
    const generateModal = document.getElementById('generateApiKeyModal');
    const showKeyModal = document.getElementById('showApiKeyModal');
    const revokeModal = document.getElementById('revokeApiKeyModal');
    const deleteModal = document.getElementById('deleteApiKeyModal');

    // Get button elements
    const generateBtn = document.getElementById('generateApiKeyBtn');
    const cancelGenerateBtn = document.getElementById('cancelGenerateBtn');
    const confirmGenerateBtn = document.getElementById('confirmGenerateBtn');
    const closeShowKeyBtn = document.getElementById('closeShowKeyBtn');
    const copyApiKeyBtn = document.getElementById('copyApiKeyBtn');
    const cancelRevokeBtn = document.getElementById('cancelRevokeBtn');
    const confirmRevokeBtn = document.getElementById('confirmRevokeBtn');
    const cancelDeleteApiKeyBtn = document.getElementById('cancelDeleteApiKeyBtn');
    const confirmDeleteApiKeyBtn = document.getElementById('confirmDeleteApiKeyBtn');

    if (!generateBtn) {
      log('Generate button not found, aborting initialization');
      return;
    }

    // Load existing keys on init
    loadApiKeys();

    // Generate new key button
    generateBtn.addEventListener('click', () => {
      log('Opening generate modal');
      document.getElementById('apiKeyName').value = '';
      document.getElementById('apiKeyExpiry').value = '';
      generateModal.classList.add('active');
    });

    // Cancel generate
    if (cancelGenerateBtn) {
      cancelGenerateBtn.addEventListener('click', () => {
        generateModal.classList.remove('active');
      });
    }

    // Confirm generate
    if (confirmGenerateBtn) {
      confirmGenerateBtn.addEventListener('click', async () => {
        const keyName = document.getElementById('apiKeyName').value.trim();
        const expiryDays = document.getElementById('apiKeyExpiry').value;

        if (!keyName) {
          alert('Please enter a name for this API key');
          return;
        }

        try {
          confirmGenerateBtn.disabled = true;
          confirmGenerateBtn.textContent = 'Generating...';

          const response = await fetch('/api/manage_api_keys.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'generate',
              key_name: keyName,
              expires_days: expiryDays ? parseInt(expiryDays) : null
            })
          });

          const data = await response.json();

          if (data.success) {
            // Close generate modal
            generateModal.classList.remove('active');

            // Show the generated key
            document.getElementById('generatedApiKey').value = data.key.api_key;
            showKeyModal.classList.add('active');

            // Reload keys list
            loadApiKeys();

            log('API key generated successfully');
          } else {
            alert('Error: ' + (data.error || 'Failed to generate key'));
          }
        } catch (error) {
          log('Error generating key:', error);
          alert('Error generating API key: ' + error.message);
        } finally {
          confirmGenerateBtn.disabled = false;
          confirmGenerateBtn.textContent = 'Generate Key';
        }
      });
    }

    // Close show key modal
    if (closeShowKeyBtn) {
      closeShowKeyBtn.addEventListener('click', () => {
        showKeyModal.classList.remove('active');
        document.getElementById('apiKeyCopySuccess').style.display = 'none';
      });
    }

    // Copy API key to clipboard
    if (copyApiKeyBtn) {
      copyApiKeyBtn.addEventListener('click', async () => {
        const keyInput = document.getElementById('generatedApiKey');
        
        try {
          await navigator.clipboard.writeText(keyInput.value);
          document.getElementById('apiKeyCopySuccess').style.display = 'block';
          copyApiKeyBtn.textContent = '✅ Copied!';
          
          setTimeout(() => {
            copyApiKeyBtn.textContent = '📋 Copy';
          }, 3000);
        } catch (error) {
          // Fallback for older browsers
          keyInput.select();
          document.execCommand('copy');
          document.getElementById('apiKeyCopySuccess').style.display = 'block';
          copyApiKeyBtn.textContent = '✅ Copied!';
          
          setTimeout(() => {
            copyApiKeyBtn.textContent = '📋 Copy';
          }, 3000);
        }
      });
    }

    // Cancel revoke
    if (cancelRevokeBtn) {
      cancelRevokeBtn.addEventListener('click', () => {
        revokeModal.classList.remove('active');
        selectedKeyForAction = null;
      });
    }

    // Confirm revoke
    if (confirmRevokeBtn) {
      confirmRevokeBtn.addEventListener('click', async () => {
        if (!selectedKeyForAction) return;

        try {
          confirmRevokeBtn.disabled = true;
          confirmRevokeBtn.textContent = 'Revoking...';

          const response = await fetch('/api/manage_api_keys.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'revoke',
              key_id: selectedKeyForAction.id
            })
          });

          const data = await response.json();

          if (data.success) {
            revokeModal.classList.remove('active');
            loadApiKeys();
            log('API key revoked');
          } else {
            alert('Error: ' + (data.error || 'Failed to revoke key'));
          }
        } catch (error) {
          log('Error revoking key:', error);
          alert('Error: ' + error.message);
        } finally {
          confirmRevokeBtn.disabled = false;
          confirmRevokeBtn.textContent = 'Revoke Key';
          selectedKeyForAction = null;
        }
      });
    }

    // Cancel delete
    if (cancelDeleteApiKeyBtn) {
      cancelDeleteApiKeyBtn.addEventListener('click', () => {
        deleteModal.classList.remove('active');
        selectedKeyForAction = null;
      });
    }

    // Confirm delete
    if (confirmDeleteApiKeyBtn) {
      confirmDeleteApiKeyBtn.addEventListener('click', async () => {
        if (!selectedKeyForAction) return;

        try {
          confirmDeleteApiKeyBtn.disabled = true;
          confirmDeleteApiKeyBtn.textContent = 'Deleting...';

          const response = await fetch('/api/manage_api_keys.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'delete',
              key_id: selectedKeyForAction.id
            })
          });

          const data = await response.json();

          if (data.success) {
            deleteModal.classList.remove('active');
            loadApiKeys();
            log('API key deleted');
          } else {
            alert('Error: ' + (data.error || 'Failed to delete key'));
          }
        } catch (error) {
          log('Error deleting key:', error);
          alert('Error: ' + error.message);
        } finally {
          confirmDeleteApiKeyBtn.disabled = false;
          confirmDeleteApiKeyBtn.textContent = 'Delete Forever';
          selectedKeyForAction = null;
        }
      });
    }

    // Close modals when clicking outside
    [generateModal, showKeyModal, revokeModal, deleteModal].forEach(modal => {
      if (modal) {
        modal.addEventListener('click', (e) => {
          if (e.target === modal) {
            modal.classList.remove('active');
          }
        });
      }
    });
  }

  async function loadApiKeys() {
    const log = (...args) => console.log('[API Keys]', ...args);
    const container = document.getElementById('apiKeysList');
    
    if (!container) return;

    // Show loading
    container.innerHTML = `
      <div class="loading-keys">
        <div class="spinner"></div>
        <p>Loading API keys...</p>
      </div>
    `;

    try {
      const response = await fetch('/api/manage_api_keys.php?action=list');
      const data = await response.json();

      if (data.success) {
        currentApiKeys = data.keys;
        renderApiKeys(data.keys);
        log(`Loaded ${data.keys.length} API keys`);
      } else {
        container.innerHTML = `<p class="error-message">Failed to load API keys</p>`;
      }
    } catch (error) {
      log('Error loading keys:', error);
      container.innerHTML = `<p class="error-message">Error loading API keys: ${error.message}</p>`;
    }
  }

  function renderApiKeys(keys) {
    const container = document.getElementById('apiKeysList');
    
    if (!keys || keys.length === 0) {
      container.innerHTML = `
        <div class="no-keys-message">
          <p><strong>No API keys yet</strong></p>
          <p>Generate your first API key to start using WhoDATUploader!</p>
        </div>
      `;
      return;
    }

    container.innerHTML = keys.map(key => {
      const isActive = key.is_active == 1;
      const isExpired = key.is_expired == 1;
      
      let statusClass = 'active';
      let statusText = 'Active';
      
      if (isExpired) {
        statusClass = 'expired';
        statusText = 'Expired';
      } else if (!isActive) {
        statusClass = 'inactive';
        statusText = 'Inactive';
      }

      const createdDate = new Date(key.created_at).toLocaleDateString();
      const lastUsed = key.last_used_at 
        ? new Date(key.last_used_at).toLocaleString()
        : 'Never';
      
      const expiresAt = key.expires_at
        ? new Date(key.expires_at).toLocaleDateString()
        : 'Never';

      return `
        <div class="api-key-item">
          <div class="api-key-header">
            <div class="key-name">${key.key_name || 'Unnamed Key'}</div>
            <span class="key-status ${statusClass}">${statusText}</span>
          </div>
          
          <div class="api-key-details">
            <div class="key-detail">
              <span class="key-detail-label">Key Preview</span>
              <span class="key-detail-value">${key.key_preview}...</span>
            </div>
            <div class="key-detail">
              <span class="key-detail-label">Created</span>
              <span class="key-detail-value">${createdDate}</span>
            </div>
            <div class="key-detail">
              <span class="key-detail-label">Last Used</span>
              <span class="key-detail-value">${lastUsed}</span>
            </div>
            <div class="key-detail">
              <span class="key-detail-label">Expires</span>
              <span class="key-detail-value">${expiresAt}</span>
            </div>
          </div>

          <div class="api-key-actions-row">
            ${!isActive && !isExpired ? `
              <button class="key-btn key-btn-activate" onclick="activateApiKey(${key.id})">
                ✅ Activate
              </button>
            ` : ''}
            ${isActive && !isExpired ? `
              <button class="key-btn key-btn-revoke" onclick="revokeApiKey(${key.id})">
                ⏸️ Revoke
              </button>
            ` : ''}
            <button class="key-btn key-btn-delete" onclick="deleteApiKey(${key.id})">
              🗑️ Delete
            </button>
          </div>
        </div>
      `;
    }).join('');
  }

  // Make these functions global so they can be called from onclick
  window.activateApiKey = async function(keyId) {
    const log = (...args) => console.log('[API Keys]', ...args);
    
    try {
      const response = await fetch('/api/manage_api_keys.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'update',
          key_id: keyId,
          is_active: 1
        })
      });

      const data = await response.json();

      if (data.success) {
        loadApiKeys();
        log('API key activated');
      } else {
        alert('Error: ' + (data.error || 'Failed to activate key'));
      }
    } catch (error) {
      log('Error activating key:', error);
      alert('Error: ' + error.message);
    }
  };

  window.revokeApiKey = function(keyId) {
    const key = currentApiKeys.find(k => k.id == keyId);
    if (!key) return;

    selectedKeyForAction = key;
    document.getElementById('revokeKeyName').textContent = key.key_name || 'Unnamed Key';
    document.getElementById('revokeApiKeyModal').classList.add('active');
  };

  window.deleteApiKey = function(keyId) {
    const key = currentApiKeys.find(k => k.id == keyId);
    if (!key) return;

    selectedKeyForAction = key;
    document.getElementById('deleteKeyName').textContent = key.key_name || 'Unnamed Key';
    document.getElementById('deleteApiKeyModal').classList.add('active');
  };

  // Initialize API key management when conf section loads
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'conf') {
      console.log('[API Keys] Conf section loaded, initializing API key management');
      setTimeout(initializeApiKeyManagement, 100);
    }
  });

  // Also initialize on direct page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (document.querySelector('#tab-conf')) {
        console.log('[API Keys] Page loaded with conf section, initializing');
        setTimeout(initializeApiKeyManagement, 100);
      }
    });
  } else {
    if (document.querySelector('#tab-conf')) {
      console.log('[API Keys] Conf section already in DOM, initializing');
      setTimeout(initializeApiKeyManagement, 100);
    }
  }

  // ============================================
  // END API KEY MANAGEMENT
  // ============================================
})();