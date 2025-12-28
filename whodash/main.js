/* WhoDAT — SPA main.js with unified Character Selection
 ------------------------------------------------------
 - Text-only trigger in topbar (#charselectTrigger)
 - White-card dropdown (#charselectMenu)
 - Global character state propagated to all sections via ?character_id=
 - Safe defaults, accessibility, and graceful fallbacks
*/

// When items.php is injected into #sectionContent:

"use strict";
/* ------------------------------ Routes (server fragments) ------------------------------ */
const sectionRoutes = {
  dashboard: "/sections/dashboard.php",
  conf: "/sections/conf.php",
  login: "/sections/login.php",
  summary: "/sections/summary.php",
  "travel-log": "/sections/travel-log.php",
  professions: "/sections/professions.php",
  achievements: "/sections/achievements.php",
  graphs: "/sections/graphs.php",
  items: "/sections/items.php",
  currencies: "/sections/currencies.php",
  progression: "/sections/progression.php",
  mortality: "/sections/mortality.php",
  role: "/sections/role.php",
  quests: "/sections/quests.php"
};
/* ------------------------------ Elements ------------------------------ */
const sectionContent = document.getElementById('sectionContent');
const sectionSearch = document.getElementById('sectionSearch');
const sectionDropdown = document.getElementById('sectionDropdown');
/* ------------------------------ Global character state ------------------------------ */
let WhoDAT_currentCharacterId = null; // string (normalized)
let WhoDAT_characterName = null;
/* ------------------------------ Logging helper ------------------------------ */
const log = (...a) => console.log("[WhoDAT]", ...a);
/* ------------------------------ Utils ------------------------------ */
const getSectionContent = () => document.getElementById('sectionContent');
/** Build URL for a section, appending ?character_id=... when available */
function urlForSection(section) {
  const base = sectionRoutes[section];
  if (!base) return null;
  const qs = WhoDAT_currentCharacterId
    ? `?character_id=${encodeURIComponent(WhoDAT_currentCharacterId)}`
    : '';
  return base + qs;
}

/* ============================== Chart helpers & tooltips ============================== */
// Copper → "Xg Ys Zc"
function fmtCoin(copper) {
  copper = Number(copper || 0);
  const g = Math.floor(copper / 10000);
  const s = Math.floor((copper % 10000) / 100);
  const c = copper % 100;
  const parts = [];
  if (g) parts.push(`${g}g`);
  if (s || g) parts.push(`${s}s`);
  parts.push(`${c}c`);
  return parts.join(' ');
}

/**
 * Attach tooltip & markers to an SVG line.
 * Guards against double-wiring using a symbol property on the host element.
 */
function attachLineTooltip(host, series, pts, { width, height, valueKey, color = '#2456a5' }) {
  if (!host || !pts?.length || !series?.length) return;
  if (host.__whodatTipWired) return; // guard
  host.__whodatTipWired = true;

  const svg = host.querySelector('svg');
  if (!svg) return;

  // Ensure host is a positioning context
  if (!host.style.position || host.style.position === 'static') {
    host.style.position = 'relative';
  }

  // Floating tooltip container (styled via your CSS .whodat-tip)
  const tip = document.createElement('div');
  tip.className = 'whodat-tip';
  tip.setAttribute('role', 'status');
  host.appendChild(tip);

  // Marker elements in SVG
  const ns = 'http://www.w3.org/2000/svg';
  const dot = document.createElementNS(ns, 'circle');
  dot.setAttribute('r', '3.5');
  dot.setAttribute('fill', '#fff');
  dot.setAttribute('stroke', color);
  dot.setAttribute('stroke-width', '2');
  dot.setAttribute('opacity', '0');
  svg.appendChild(dot);

  const vline = document.createElementNS(ns, 'line');
  vline.setAttribute('y1', '1');
  vline.setAttribute('y2', String(height - 1));
  vline.setAttribute('stroke', color);
  vline.setAttribute('stroke-width', '1');
  vline.setAttribute('opacity', '0');
  vline.setAttribute('vector-effect', 'non-scaling-stroke');
  svg.appendChild(vline);

  const getBBox = () => svg.getBoundingClientRect();
  const hostBB = () => host.getBoundingClientRect();

  function labelHtml(i) {
    const sample = series[i] || {};
    // PHP sends epoch seconds; if you change to ms, remove *1000.
    const dt = new Date(Number(sample.ts || 0) * 1000);
    const when = dt.toLocaleString();
    const val = valueKey === 'value'
      ? fmtCoin(sample.value)
      : Number(sample[valueKey] || 0).toLocaleString();
    const valLabel = valueKey === 'value' ? 'Gold' : 'Count';
    return `<strong>${when}</strong><br>${valLabel}: ${val}`;
  }

  function nearestIndex(x) {
    let best = 0, bestDist = Infinity;
    for (let i = 0; i < pts.length; i++) {
      const d = Math.abs(x - pts[i][0]);
      if (d < bestDist) { bestDist = d; best = i; }
    }
    return best;
  }

  function showAtIndex(i) {
    const [x, y] = pts[i];
    // Move markers
    dot.setAttribute('cx', String(x));
    dot.setAttribute('cy', String(y));
    dot.setAttribute('opacity', '1');

    vline.setAttribute('x1', String(x));
    vline.setAttribute('x2', String(x));
    vline.setAttribute('opacity', '0.7');

    // Position tooltip above the point, using DOM pixels
    const bb = getBBox();
    const hbb = hostBB();
    const px = bb.left + (x / width) * bb.width;
    const py = bb.top  + (y / height) * bb.height;

    tip.style.left = `${px - hbb.left}px`;
    tip.style.top  = `${py - hbb.top}px`;
    tip.innerHTML = labelHtml(i);
    tip.style.opacity = '1';
  }

  function hide() {
    dot.setAttribute('opacity', '0');
    vline.setAttribute('opacity', '0');
    tip.style.opacity = '0';
  }

  // Pointer interactions
  svg.addEventListener('pointerenter', (e) => {
    const bb = getBBox();
    const x = (e.clientX - bb.left) / bb.width * width;
    showAtIndex(nearestIndex(x));
  });
  svg.addEventListener('pointermove', (e) => {
    const bb = getBBox();
    const x = (e.clientX - bb.left) / bb.width * width;
    showAtIndex(nearestIndex(x));
  });
  svg.addEventListener('pointerleave', () => hide());

  // Touch support
  svg.addEventListener('touchstart', (e) => {
    const t = e.touches[0]; if (!t) return;
    const bb = getBBox();
    const x = (t.clientX - bb.left) / bb.width * width;
    showAtIndex(nearestIndex(x));
  }, { passive: true });
  svg.addEventListener('touchmove', (e) => {
    const t = e.touches[0]; if (!t) return;
    const bb = getBBox();
    const x = (t.clientX - bb.left) / bb.width * width;
    showAtIndex(nearestIndex(x));
  }, { passive: true });

  // Keyboard (left/right) for accessibility
  if (!host.hasAttribute('tabindex')) host.setAttribute('tabindex', '0');
  let current = 0;
  host.addEventListener('focus', () => { showAtIndex(current); });
  host.addEventListener('blur', hide);
  host.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight') { current = Math.min(current + 1, pts.length - 1); showAtIndex(current); }
    if (e.key === 'ArrowLeft')  { current = Math.max(current - 1, 0);             showAtIndex(current); }
  });
}

/* ------------------------------ Currencies fragment initializer ------------------------------ */
// Runs after SPA injects a currencies fragment. No external libs.
function initCurrenciesFragment(root = document.getElementById('currencies-fragment')) {
  if (!root) return;
  try {
    const goldSeries = JSON.parse(root.dataset.goldSeries || '[]'); // [{ts,value}] ascending
    const currSeries = JSON.parse(root.dataset.currSeries || '{}');  // { name: [{ts,count}] }

    function svgLine(points, width, height, stroke, strokeWidth) {
      if (!points.length) return '';
      const d = points.map((p,i) => (i===0 ? `M${p[0]},${p[1]}` : `L${p[0]},${p[1]}`)).join(' ');
      return `<svg viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" role="img" aria-label="trend">
        <path d="${d}" fill="none" stroke="${stroke}" stroke-width="${strokeWidth}" vector-effect="non-scaling-stroke"/>
      </svg>`;
    }
    function scaleSeries(series, width, height, valueKey) {
      const n = series.length;
      if (n === 0) return [];
      const vals = series.map(s => Number(s[valueKey] || 0));
      const minV = Math.min(...vals);
      const maxV = Math.max(...vals);
      const dv = maxV - minV || 1;
      const stepX = (width - 2) / Math.max(1, n - 1);
      return series.map((s,i) => {
        const x = 1 + i * stepX;
        const y = height - 1 - ((Number(s[valueKey]) - minV) / dv) * (height - 2);
        return [x, y];
      });
    }

    // Gold trend
    const goldHost = root.querySelector('#goldTrend');
    if (goldHost) {
      if (!goldSeries.length) {
        goldHost.textContent = 'No gold samples yet.';
      } else {
        const pts = scaleSeries(goldSeries, 560, 80, 'value');
        goldHost.innerHTML = svgLine(pts, 560, 80, '#2456a5', 2);
        goldHost.classList.remove('muted');
        // Tooltip on gold series
        attachLineTooltip(goldHost, goldSeries, pts, { width: 560, height: 80, valueKey: 'value', color: '#2456a5' });
      }
    }

    // Per-currency sparks
    root.querySelectorAll('.spark[data-name]').forEach(el => {
      const nm = el.getAttribute('data-name');
      const s  = currSeries[nm] || [];
      if (!s.length) { el.textContent = ''; return; }
      const pts = scaleSeries(s, 120, 24, 'count');
      el.innerHTML = svgLine(pts, 120, 24, '#3182ce', 1.5);
      // Tooltip on each spark
      attachLineTooltip(el, s, pts, { width: 120, height: 24, valueKey: 'count', color: '#3182ce' });
    });

    // Refresh button
    root.querySelector('#refreshCurrencies')?.addEventListener('click', () => {
      // Simple reload (you can later scope to only this section)
      location.href = location.href;
    });
  } catch (e) {
    log('initCurrenciesFragment error:', e);
  }
}

/* ------------------------------ Section controller bootstrap ------------------------------ */
function initSectionControllers() {
  const root = getSectionContent();
  if (!root) return;
  // Initialize all controllers that use declarative hooks
  root.querySelectorAll('[data-controller="currencies"]').forEach(initCurrenciesFragment);
}

/* ------------------------------ Character selection core ------------------------------ */
/** Fetch server-chosen default (last updated character) if none is known */
async function ensureActiveCharacterId() {
  if (WhoDAT_currentCharacterId) return WhoDAT_currentCharacterId;
  try {
    const res = await fetch('/sections/active_character.php', { credentials: 'include' });
    if (!res.ok) {
      log("active_character.php responded", res.status);
      return null;
    }
    const data = await res.json().catch(() => ({}));
    if (data && data.character_id) {
      WhoDAT_currentCharacterId = String(data.character_id);
      if (data.name) {
        WhoDAT_characterName = data.name;
        const labelEl = document.getElementById('charselectLabel');
        if (labelEl) labelEl.textContent = data.name;
      }
      log("Active character from server:", WhoDAT_currentCharacterId, WhoDAT_characterName);
      return WhoDAT_currentCharacterId;
    }
  } catch (e) {
    log("ensureActiveCharacterId error:", e);
  }
  return null;
}

/** Build & wire the dropdown: fetch list, render items, selection behavior */
async function WhoDAT_initCharacterSelect(retry = true) {
  const trigger = document.getElementById('charselectTrigger');
  const labelEl = document.getElementById('charselectLabel');
  const menuEl = document.getElementById('charselectMenu');
  if (!trigger || !labelEl || !menuEl) {
    console.log('[WhoDAT] character select DOM not found');
    if (retry) {
      // try again on next microtask, gives DOM a moment to appear
      setTimeout(() => WhoDAT_initCharacterSelect(false), 0);
    }
    return;
  }
  // Idempotent wiring guard to avoid duplicate listeners if re-initialized
  if (trigger.dataset.wired === '1' && menuEl.dataset.wired === '1') {
    // Still refresh the menu content & label, but skip re-binding events
  }
  // 1) Default from server (last updated), if unknown
  await ensureActiveCharacterId();
  // 2) Load character list
  let characters = [];
  try {
    const res = await fetch('/sections/characters_list.php', { credentials: 'include' });
    const data = await res.json().catch(() => ({}));
    if (res.ok && data.ok && Array.isArray(data.characters)) {
      characters = data.characters;
    }
  } catch (e) {
    log("characters_list fetch failed:", e);
  }
  // 3) Render menu
  menuEl.innerHTML = '';
  if (!characters.length) {
    menuEl.innerHTML = "<div class='charselect-item' role='menuitem' aria-disabled='true'>No characters yet</div>";
  } else {
    characters.forEach(c => {
      const item = document.createElement('div');
      const isActive = String(c.id) === WhoDAT_currentCharacterId;
      item.className = 'charselect-item' + (isActive ? ' active' : '');
      item.setAttribute('role', 'menuitem');
      item.innerHTML = `
        <span class="char-emoji" aria-hidden="true">🧝</span>
        <div class="char-text">
          <div class="char-name">${c.name ?? 'Unknown'}</div>
          <div class="char-meta">${c.updated_at ? `Updated ${new Date(c.updated_at).toLocaleString()}` : 'No recent updates'}</div>
        </div>
      `;
      item.onclick = () => {
        // Update global state + label
        WhoDAT_currentCharacterId = String(c.id);
        WhoDAT_characterName = c.name ?? null;
        labelEl.textContent = WhoDAT_characterName ?? 'Select Character';
        // Optional: persist choice on server (session or DB); non-blocking
        try {
          fetch('/sections/active_character.php', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `character_id=${encodeURIComponent(WhoDAT_currentCharacterId)}`
          }).catch(() => {});
        } catch {}
        // Close menu
        menuEl.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
        // Notify listeners and reload current section (push:false)
        document.dispatchEvent(new CustomEvent('whodat:character-changed', {
          detail: { id: WhoDAT_currentCharacterId, name: WhoDAT_characterName }
        }));
        const currentSection =
          (history && history.state && history.state.section)
          ? history.state.section
          : 'dashboard';
        loadSection(currentSection, { push: false });
      };
      menuEl.appendChild(item);
    });
  }
  // 4) Wire open/close behavior (idempotent)
  if (trigger.dataset.wired !== '1') {
    trigger.addEventListener('click', (e) => {
      e.preventDefault();
      const isHidden = menuEl.classList.contains('hidden');
      menuEl.classList.toggle('hidden', !isHidden);
      trigger.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
      if (isHidden) menuEl.focus({ preventScroll: true });
    });
    trigger.dataset.wired = '1';
  }
  if (menuEl.dataset.wired !== '1') {
    document.addEventListener('click', (e) => {
      if (!trigger.contains(e.target) && !menuEl.contains(e.target)) {
        menuEl.classList.add('hidden');
        trigger.setAttribute('aria-expanded', 'false');
      }
    });
    menuEl.dataset.wired = '1';
  }
  // 5) Set label immediately if we know a name
  if (WhoDAT_characterName) {
    labelEl.textContent = WhoDAT_characterName;
  } else if (WhoDAT_currentCharacterId) {
    const found = characters.find(c => String(c.id) === WhoDAT_currentCharacterId);
    if (found && found.name) labelEl.textContent = found.name;
  }
}

/* ------------------------------ Section search (existing UI) ------------------------------ */
function fuzzyMatch(query, target) {
  query = String(query || '').toLowerCase();
  target = String(target || '').toLowerCase();
  if (target.includes(query)) return true;
  let qi = 0;
  for (let ti = 0; ti < target.length && qi < query.length; ti++) {
    if (target[ti] === query[qi]) qi++;
  }
  return qi === query.length;
}
const sections = [
  { name: "Dashboard", icon: "📊", section: "dashboard" },
  { name: "Role Performance", icon: "📊", section: "role" },
  { name: "Currencies", icon: "🪙", section: "currencies" },
  { name: "Items", icon: "🧳", section: "items" },
  { name: "Quests", icon: "📜", section: "quests" },
  { name: "Professions", icon: "🛠️", section: "professions" },
  { name: "Achievements",icon: "🎯", section: "achievements" },
  { name: "Travel Log", icon: "🗺️", section: "travel-log" },
  { name: "Progression", icon: "🏆", section: "progression" },
  { name: "Mortality", icon: "☠️", section: "mortality" },
  { name: "Summary", icon: "🏠", section: "summary" },
  { name: "Graphs", icon: "📈", section: "graphs" },
  { name: "Config", icon: "⚙️", section: "conf" }
];
function renderDropdown(query = "") {
  sectionDropdown.innerHTML = "";
  const filtered = sections.filter(s => fuzzyMatch(query, s.name));
  if (filtered.length === 0) {
    sectionDropdown.innerHTML = "<div class='section-item muted'>No matches</div>";
    return;
  }
  filtered.forEach(s => {
    const item = document.createElement('div');
    item.className = 'section-item';
    item.innerHTML = `${s.icon} ${s.name}`;
    item.onclick = () => {
      loadSection(s.section);
      sectionDropdown.classList.add('hidden');
      sectionSearch.value = "";
    };
    sectionDropdown.appendChild(item);
  });
}
sectionSearch?.addEventListener('focus', () => {
  sectionDropdown.classList.remove('hidden');
  renderDropdown(sectionSearch.value);
});
sectionSearch?.addEventListener('input', () => {
  renderDropdown(sectionSearch.value);
});
document.addEventListener('click', (e) => {
  if (!sectionSearch?.contains(e.target) && !sectionDropdown?.contains(e.target)) {
    sectionDropdown?.classList.add('hidden');
  }
});

/* ------------------------------ SPA loader ------------------------------ */
async function loadSection(section, { push = true } = {}) {
  const host = getSectionContent();
  if (!host) { log("No #sectionContent in DOM"); return; }
  const url = urlForSection(section);
  log("loadSection:", section, url);
  host.classList.add('fade-out');
  if (url) {
    try {
      const res = await fetch(url, { headers: { 'HX-Request': 'true' }, credentials: 'include' });
      // If server requires auth, show login
      if (res.status === 401) {
        log("401 from", url, "→ loading login");
        await loadSection('login', { push: false });
        return;
      }
      // Some sections may return 400 if character_id is required but missing
      if (res.status === 400 && !WhoDAT_currentCharacterId) {
        log("400 likely due to missing character_id; trying ensureActiveCharacterId()");
        await ensureActiveCharacterId();
        const retryUrl = urlForSection(section);
        const retry = await fetch(retryUrl, { headers: { 'HX-Request': 'true' }, credentials: 'include' });
        const retryHtml = await retry.text();
        host.innerHTML = retryHtml;
        // Rewire selector + initialize section controllers
        WhoDAT_initCharacterSelect(false);
        initSectionControllers();
        // Broadcast a section-loaded event
        document.dispatchEvent(new CustomEvent('whodat:section-loaded', { detail: { section } }));
        if (push && history && history.pushState) history.pushState({ section }, '', retryUrl);
        host.classList.remove('fade-out'); host.classList.add('fade-in');
        setTimeout(() => host.classList.remove('fade-in'), 220);
        return;
      }
      const html = await res.text();
      if (!res.ok) {
        host.innerHTML = `
          <div class="muted">Error: ${res.status} ${res.statusText}</div>
          ${html ? `<div>${html}</div>` : ''}
        `;
      } else {
        host.innerHTML = html;
        if (push && history && history.pushState) {
          history.pushState({ section }, '', url);
        }
      }
      // After any successful injection: rewire selector + init controllers + broadcast
      WhoDAT_initCharacterSelect(false);
      initSectionControllers();
      document.dispatchEvent(new CustomEvent('whodat:section-loaded', { detail: { section } }));
      host.classList.remove('fade-out'); host.classList.add('fade-in');
      setTimeout(() => host.classList.remove('fade-in'), 220);
      return;
    } catch (err) {
      host.innerHTML = `<div class="muted">Network error: ${String(err)}</div>`;
      // Try to wire selector even on error (in case top bar exists)
      WhoDAT_initCharacterSelect(false);
      // Controllers may not exist in error content, but call defensively
      initSectionControllers();
      host.classList.remove('fade-out'); host.classList.add('fade-in');
      setTimeout(() => host.classList.remove('fade-in'), 220);
      return;
    }
  }
  // No route → placeholder
  host.innerHTML = `
    <h3>${section}</h3>
    <div class="muted">Section not wired yet.</div>
  `;
  // Ensure selector + controllers
  WhoDAT_initCharacterSelect(false);
  initSectionControllers();
  document.dispatchEvent(new CustomEvent('whodat:section-loaded', { detail: { section } }));
  host.classList.remove('fade-out'); host.classList.add('fade-in');
  setTimeout(() => host.classList.remove('fade-in'), 220);
}
function navigateTo(section) { loadSection(section, { push: true }); }

/* ------------------------------ Global event delegation inside #sectionContent ------------------------------ */
(function wireDelegation() {
  const isInsideContent = (el) => {
    const content = getSectionContent();
    return content && content.contains(el);
  };
  // Click delegation (login/register toggles, etc.)
  document.addEventListener('click', (e) => {
    const t = e.target;
    if (!isInsideContent(t)) return;
    // Toggle: show register
    if (t.matches('[data-action="show-register"]')) {
      e.preventDefault();
      const root = getSectionContent();
      root.querySelector('#loginCard')?.classList.add('hidden');
      root.querySelector('#registerCard')?.classList.remove('hidden');
      return;
    }
    // Toggle: show login
    if (t.matches('[data-action="show-login"]')) {
      e.preventDefault();
      const root = getSectionContent();
      root.querySelector('#registerCard')?.classList.add('hidden');
      root.querySelector('#loginCard')?.classList.remove('hidden');
      return;
    }
  }, true);
  // Submit delegation (sample upload/login/register flows)
  document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!isInsideContent(form)) return;
    
    // SKIP whodatUploadForm - it's handled by conf.js with progress bar
    if (form.id === 'whodatUploadForm' || form.action?.includes('upload_whodat')) {
      console.log('[main.js] Skipping whodatUploadForm - handled by conf.js');
      return; // Let conf.js handle it
    }
    
    // Upload handler (legacy - kept for backwards compatibility)
    if (form.id === 'uploadForm') {
      e.preventDefault();
      const formData = new FormData(form);
      try {
        const response = await fetch('/sections/upload_whodat.php', { method: 'POST', body: formData });
        const result = await response.text();
        const el = document.getElementById('uploadResult');
        if (el) el.innerHTML = result;
      } catch (err) {
        const el = document.getElementById('uploadResult');
        if (el) el.textContent = String(err);
      }
      return;
    }
    // Login
    if (form.id === 'loginForm') {
      e.preventDefault();
      const fd = new FormData(form);
      try {
        const res = await fetch('login.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json().catch(async () => {
          const t = await res.text().catch(() => '');
          throw new Error(`Non-JSON response (${res.status} ${res.statusText}): ${t.slice(0, 200)}`);
        });
        if (data.success) {
          // Rebuild the top-left character UI for the authenticated user
          await WhoDAT_refreshCharacterUI();
          // Broadcast auth state change (for any future listeners)
          document.dispatchEvent(new Event('whodat:auth-changed'));
          navigateTo('dashboard');
        } else {
          getSectionContent().querySelector('#loginError').textContent =
            data.message || 'Login failed.';
        }
      } catch (err) {
        getSectionContent().querySelector('#loginError').textContent = String(err);
      }
      return;
    }
    // Register
    if (form.id === 'registerForm') {
      e.preventDefault();
      const fd = new FormData(form);
      try {
        const res = await fetch('/sections/register.php', { method: 'POST', body: fd, credentials: 'include' });
        const data = await res.json().catch(async () => {
          const t = await res.text().catch(() => '');
          throw new Error(`Non-JSON response (${res.status} ${res.statusText}): ${t.slice(0, 200)}`);
        });
        if (data.success) {
          // After registration, refresh character selector (in case a default was created server-side)
          await WhoDAT_refreshCharacterUI();
          document.dispatchEvent(new Event('whodat:auth-changed'));
          navigateTo('dashboard');
        } else {
          getSectionContent().querySelector('#registerError').textContent =
            data.message || 'Registration failed.';
        }
      } catch (err) {
        getSectionContent().querySelector('#registerError').textContent = String(err);
      }
      return;
    }
  }, true);
})();

/* ------------------------------ Refresh helper ------------------------------ */
// Re-initialize active character + menu/label, used after auth changes or SPA changes
async function WhoDAT_refreshCharacterUI() {
  await ensureActiveCharacterId(); // will succeed once authenticated
  await WhoDAT_initCharacterSelect(false); // rebuild menu & label with fresh data
}

/* ------------------------------ Global auth-changed listener ------------------------------ */
document.addEventListener('whodat:auth-changed', async () => {
  await WhoDAT_refreshCharacterUI();
});

/* ------------------------------ MutationObserver hardening ------------------------------ */
// Auto-init character selector when its DOM nodes appear (handles async fragment injection)
(function autoWireCharSelect() {
  try {
    const mo = new MutationObserver(() => {
      const trigger = document.getElementById('charselectTrigger');
      const label = document.getElementById('charselectLabel');
      const menu = document.getElementById('charselectMenu');
      if (trigger && label && menu) {
        WhoDAT_initCharacterSelect(false);
        mo.disconnect();
      }
    });
    mo.observe(document.documentElement, { childList: true, subtree: true });
  } catch (e) {
    // If MO isn't available, no big deal—other re-init paths still work.
  }
})();

/* ------------------------------ Controllers event hook ------------------------------ */
// Initialize controllers whenever a section-loaded event is broadcast
// (loadSection() also calls initSectionControllers() directly; guards prevent duplicate wiring)
document.addEventListener('whodat:section-loaded', () => {
  initSectionControllers();
});

/* ------------------------------ Initial load ------------------------------ */
(async function initialLoad() {
  const host = getSectionContent();
  // 1) Ensure we have a character default from server (if any)
  await ensureActiveCharacterId();
  // 2) Build the character selector (menu + label)
  await WhoDAT_initCharacterSelect();
  // 3) Load dashboard fragment
  const dashUrl = urlForSection('dashboard');
  try {
    const res = await fetch(dashUrl, { headers: { 'HX-Request': 'true' }, credentials: 'include' });
    if (res.status === 401) {
      await loadSection('login', { push: false });
      return;
    }
    const html = await res.text();
    if (res.ok) {
      host.innerHTML = html;
      if (history && history.replaceState) {
        history.replaceState({ section: 'dashboard' }, '', dashUrl);
      }
    } else {
      host.innerHTML = `<div class="muted">Error ${res.status} ${res.statusText}</div>`;
    }
    // Ensure selector & controllers are wired even if dashboard markup injects top bar later
    WhoDAT_initCharacterSelect(false);
    initSectionControllers();
    document.dispatchEvent(new CustomEvent('whodat:section-loaded', { detail: { section: 'dashboard' } }));
  } catch (e) {
    host.innerHTML = `<div class="muted">Network error: ${String(e)}</div>`;
    WhoDAT_initCharacterSelect(false);
    initSectionControllers();
  }
})();