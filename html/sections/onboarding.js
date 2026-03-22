/* sections/onboarding.js — WhoDASH new user onboarding wizard */
(function () {
  'use strict';

  const ANIM_DURATION = 300; // ms — keep in sync with CSS animation durations

  let currentSlide = 0;
  const totalSlides = 4;

  // ── DOM refs ──────────────────────────────────────────────────────────────
  function getSlide(n) {
    return document.querySelector(`.onboarding-slide[data-slide="${n}"]`);
  }
  function getStep(n) {
    return document.querySelector(`.onboarding-step[data-step="${n}"]`);
  }
  function getLines() {
    return document.querySelectorAll('.onboarding-step-line');
  }

  // ── Step tracker ──────────────────────────────────────────────────────────
  function updateStepTracker(activeIndex) {
    for (let i = 0; i < totalSlides; i++) {
      const step = getStep(i);
      if (!step) continue;
      step.classList.remove('active', 'completed');
      if (i < activeIndex) step.classList.add('completed');
      else if (i === activeIndex) step.classList.add('active');
    }
    const lines = getLines();
    lines.forEach((line, i) => {
      line.classList.toggle('completed', i < activeIndex);
    });
  }

  // ── Slide transition ──────────────────────────────────────────────────────
  function goTo(targetIndex, direction) {
    if (targetIndex < 0 || targetIndex >= totalSlides) return;
    if (targetIndex === currentSlide) return;

    const outClass = direction === 'forward' ? 'slide-out-left' : 'slide-out-right';
    const inClass  = direction === 'forward' ? 'slide-in-right' : 'slide-in-left';

    const outSlide = getSlide(currentSlide);
    const inSlide  = getSlide(targetIndex);
    if (!outSlide || !inSlide) return;

    // Animate out
    outSlide.classList.remove('active');
    outSlide.classList.add(outClass);

    // After out animation completes, hide and animate in
    setTimeout(() => {
      outSlide.classList.remove(outClass);
      outSlide.style.display = 'none';

      currentSlide = targetIndex;
      updateStepTracker(currentSlide);

      inSlide.style.display = 'block';
      inSlide.classList.add(inClass);

      // Force reflow so the browser registers the starting state before animating
      void inSlide.offsetWidth;

      inSlide.classList.remove(inClass);
      inSlide.classList.add('active');
    }, ANIM_DURATION);
  }

  function goNext(n) { goTo(n, 'forward'); }
  function goPrev(n) { goTo(n, 'backward'); }

  // ── File input ────────────────────────────────────────────────────────────
  function initFileInput() {
    const fileInput  = document.getElementById('whodatFileInput');
    const fileLabel  = document.getElementById('onboardingFileName');
    const uploadBtn  = document.getElementById('onboardingUploadBtn');
    const dropZone   = document.getElementById('onboardingDropZone');

    if (!fileInput) return;

    function onFileChosen(file) {
      if (!file) return;
      fileInput.files = createFileList(file); // assign back if from drop
      fileLabel.textContent = `✓ ${file.name}`;
      if (uploadBtn) uploadBtn.disabled = false;
    }

    // Helper: build a DataTransfer to assign dropped file to input
    function createFileList(file) {
      try {
        const dt = new DataTransfer();
        dt.items.add(file);
        return dt.files;
      } catch (e) {
        return fileInput.files; // fallback — some older browsers
      }
    }

    fileInput.addEventListener('change', () => {
      if (fileInput.files[0]) onFileChosen(fileInput.files[0]);
    });

    // Drag-and-drop on the drop zone
    if (dropZone) {
      dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
      });
      dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-over');
      });
      dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const file = e.dataTransfer?.files?.[0];
        if (file && file.name.endsWith('.lua')) {
          onFileChosen(file);
        }
      });
      // Clicking the drop zone also opens the file picker
      dropZone.addEventListener('click', (e) => {
        // Don't re-trigger if they clicked the label itself
        if (e.target.tagName === 'LABEL') return;
        fileInput.click();
      });
    }
  }

  // ── Upload handling ───────────────────────────────────────────────────────
  // The upload form is caught by conf.js which shows the progress modal.
  // After a successful upload we want to advance to step 3.
  // We hook into the whodat:section-loaded event that conf.js fires,
  // or fall back to intercepting the form submit result ourselves.
  function initUploadSuccess() {
    // conf.js dispatches a custom event after a successful upload
    document.addEventListener('whodat:upload-complete', () => {
      // Small delay so the conf.js success modal can show briefly
      setTimeout(() => goNext(3), 800);
    });

    // Fallback: watch for the form submit result directly
    const form = document.getElementById('whodatUploadForm');
    if (!form) return;
    form.addEventListener('upload:success', () => goNext(3));
  }

  // ── Button delegation ─────────────────────────────────────────────────────
  function initButtons() {
    const root = document.getElementById('tab-onboarding');
    if (!root) return;

    root.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-next], button[data-prev], button[data-goto], a[data-next], a[data-prev], a[data-goto]');
      if (!btn) return;

      // Next
      if (btn.dataset.next !== undefined) {
        e.preventDefault();
        goNext(parseInt(btn.dataset.next, 10));
        return;
      }
      // Prev
      if (btn.dataset.prev !== undefined) {
        e.preventDefault();
        goPrev(parseInt(btn.dataset.prev, 10));
        return;
      }
      // Go to a named section (navigateTo is defined in main.js)
      if (btn.dataset.goto !== undefined) {
        e.preventDefault();
        if (typeof navigateTo === 'function') {
          navigateTo(btn.dataset.goto);
        }
        return;
      }
    });
  }

  // ── Init ──────────────────────────────────────────────────────────────────
  function init() {
    const root = document.getElementById('tab-onboarding');
    if (!root) return;

    updateStepTracker(0);
    initButtons();
    initFileInput();
    initUploadSuccess();

    console.log('[Onboarding] Initialized');
  }

  let _initialized = false;

  function tryInit() {
    if (_initialized) return;
    if (!document.getElementById('tab-onboarding')) return;
    _initialized = true;
    init();
  }

 // Primary: section-loaded event (with small delay to ensure DOM is painted)
  document.addEventListener('whodat:section-loaded', (e) => {
    if (e?.detail?.section === 'onboarding') {
      _initialized = false; // reset so re-navigation re-inits cleanly
      setTimeout(tryInit, 50);
    }
  });

  // Fallback: MutationObserver watches for #tab-onboarding appearing in DOM
  const observer = new MutationObserver(() => {
    if (document.getElementById('tab-onboarding')) {
      observer.disconnect();
      setTimeout(tryInit, 50);
    }
  });
  observer.observe(document.body, { childList: true, subtree: true });

  // Also try immediately if already in DOM
  if (document.getElementById('tab-onboarding')) {
    _initialized = false;
    tryInit();
  }
})();
