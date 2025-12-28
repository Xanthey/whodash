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
          <h2>⚔️ Uploading WhoDAT Data</h2>
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

    const timer = setInterval(() => {
      progress = Math.min(90, progress + increment);
      updateProgress(elements, Math.round(progress));
      
      if (progress >= 90) {
        clearInterval(timer);
      }
    }, interval);

    return timer;
  }

  function showResultModal(success, message) {
    const modal = document.createElement('div');
    modal.className = 'modal active';
    
    const iconAndColor = success 
      ? { icon: '✓', color: '#16a34a', bgGradient: 'linear-gradient(135deg, #e9f7ef 0%, #c8e6d0 100%)', borderColor: '#2e7d32' }
      : { icon: '✕', color: '#dc2626', bgGradient: 'linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%)', borderColor: '#d32f2f' };
    
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
              <h2 style="color: #2e7d32; margin-bottom: 20px;">✓ Character Deleted</h2>
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

})();