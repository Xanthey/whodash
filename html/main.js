/* WhoDAT Ã¢â‚¬â€ SPA main.js with unified Character Selection
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
  onboarding: "/sections/onboarding.php",
  summary: "/sections/summary.php",
  "travel-log": "/sections/travel-log.php",
  professions: "/sections/professions.php",
  achievements: "/sections/achievements.php",
  character: "/sections/character.php", 
  graphs: "/sections/graphs.php",
  items: "/sections/items.php",
  currencies: "/sections/currencies.php",
  reputation: "/sections/reputation.php",
  progression: "/sections/progression.php",
  mortality: "/sections/mortality.php",
  role: "/sections/role.php",
  quests: "/sections/quests.php",
  social: "/sections/social.php",
  guild: "/sections/guild.php",
  bazaar: "/sections/bazaar.php",
  "guild-hall": "/sections/guild-hall.php"
};
/* ------------------------------ Elements ------------------------------ */
const sectionContent = document.getElementById('sectionContent');
const sectionSearch = document.getElementById('sectionSearch');
const sectionDropdown = document.getElementById('sectionDropdown');
/* ------------------------------ Global character state ------------------------------ */
let WhoDAT_currentCharacterId = null; // string (normalized)
let WhoDAT_characterName = null;

/** Update the browser tab title whenever the active character changes */
function updatePageTitle(name) {
  document.title = name
    ? `${name} - WhoDASH`
    : 'Belmont Labs -WhoDASH';
}
/* ------------------------------ Logging helper ------------------------------ */
const log = (...a) => console.log("[WhoDAT]", ...a);
/* ------------------------------ Utils ------------------------------ */
const getSectionContent = () => document.getElementById('sectionContent');
/** Build URL for a section, appending ?character_id=... when available */
function urlForSection(section) {
  const base = sectionRoutes[section];
  if (!base) return null;
  
  // Bazaar section doesn't use character_id (uses user_id from session)
  if (section === 'bazaar') {
    return base;
  }
  // For regular sections, append character_id if available
  const qs = WhoDAT_currentCharacterId && WhoDAT_currentCharacterId !== 'bazaar'
    ? `?character_id=${encodeURIComponent(WhoDAT_currentCharacterId)}`
    : '';
  return base + qs;
}

/* ============================== Chart helpers & tooltips ============================== */
// Copper Ã¢â€ â€™ "Xg Ys Zc"
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
        updatePageTitle(data.name);
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
    // Add "The Bazaar" special option at the top
    const bazaarItem = document.createElement('div');
    const isBazaarActive = WhoDAT_currentCharacterId === 'bazaar';
    bazaarItem.className = 'charselect-item bazaar-option' + (isBazaarActive ? ' active' : '');
    bazaarItem.setAttribute('role', 'menuitem');
    bazaarItem.innerHTML = `
      <span class="char-emoji" aria-hidden="true">\uD83D\uDECD\uFE0F</span>
      <div class="char-text">
        <div class="char-name">The Bazaar</div>
        <div class="char-meta">Multi-character view</div>
      </div>
    `;
    bazaarItem.onclick = () => {
      // Update global state to "bazaar"
      WhoDAT_currentCharacterId = 'bazaar';
      WhoDAT_characterName = 'The Bazaar';
      updatePageTitle('The Bazaar');
      labelEl.textContent = 'The Bazaar';
      // Close menu
      menuEl.classList.add('hidden');
      trigger.setAttribute('aria-expanded', 'false');
      // Navigate to bazaar section
      navigateTo('bazaar');
    };
    menuEl.appendChild(bazaarItem);
    
    // Add Guild Hall option
    const guildHallItem = document.createElement('div');
    const isGuildHallActive = (history && history.state && history.state.section === 'guild-hall');
    guildHallItem.className = 'charselect-item guild-hall-option' + (isGuildHallActive ? ' active' : '');
    guildHallItem.setAttribute('role', 'menuitem');
    guildHallItem.innerHTML = `
      <span class="char-emoji" aria-hidden="true">🏰</span>
      <div class="char-text">
        <div class="char-name">Guild Hall</div>
        <div class="char-meta">Guild management & bank</div>
      </div>
    `;
    guildHallItem.onclick = () => {
      // Keep current character selected if one is selected
      // The Guild Hall section has its own guild selector
      labelEl.textContent = WhoDAT_characterName ? `${WhoDAT_characterName} (Guild Hall)` : 'Guild Hall';
      // Close menu
      menuEl.classList.add('hidden');
      trigger.setAttribute('aria-expanded', 'false');
      // Navigate to guild hall
      navigateTo('guild-hall');
    };
    menuEl.appendChild(guildHallItem);
    
    // Add separator
    const separator = document.createElement('div');
    separator.className = 'charselect-separator';
    separator.innerHTML = '<div style="border-top: 1px solid #e2e8f0; margin: 8px 0;"></div>';
    menuEl.appendChild(separator);
    
// Add regular character options
    characters.forEach(c => {
      const item = document.createElement('div');
      const isActive = String(c.id) === WhoDAT_currentCharacterId;
      item.className = 'charselect-item' + (isActive ? ' active' : '');
      item.setAttribute('role', 'menuitem');
      item.innerHTML = `
        <span class="char-emoji" aria-hidden="true">\uD83E\uDDD1\uD83C\uDFFB</span>
        <div class="char-text">
          <div class="char-name">${c.name ?? 'Unknown'}</div>
          <div class="char-meta">${c.updated_at ? `Updated ${new Date(c.updated_at).toLocaleString()}` : 'No recent updates'}</div>
        </div>
      `;
      item.onclick = () => {
        // Update global state + label
        WhoDAT_currentCharacterId = String(c.id);
        WhoDAT_characterName = c.name ?? null;
        updatePageTitle(WhoDAT_characterName);
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
        // Notify listeners
        document.dispatchEvent(new CustomEvent('whodat:character-changed', {
          detail: { id: WhoDAT_currentCharacterId, name: WhoDAT_characterName }
        }));
        // If switching FROM bazaar to a character, go to dashboard
        // Otherwise, reload current section
        const currentSection =
          (history && history.state && history.state.section)
          ? history.state.section
          : 'dashboard';
        const targetSection = currentSection === 'bazaar' ? 'dashboard' : currentSection;
        loadSection(targetSection, { push: false });
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

/* ------------------------------ Enhanced Command Palette Search ------------------------------ */

// Search index with all searchable content (using Unicode escapes for reliable icon rendering)
const searchIndex = {
sections: [
    { name: "Dashboard", icon: "📊", section: "dashboard", description: "Overview and quick stats" },
    { name: "Achievements", icon: "🎯", section: "achievements", description: "Completed achievements" },
    { name: "Character", icon: "🎭", section: "character", description: "Character progression and stats" },
    { name: "Currencies", icon: "💰", section: "currencies", description: "Gold and currency tracking" },
    { name: "Graphs", icon: "📈", section: "graphs", description: "Visual data trends" },
    { name: "Guild Hall", icon: "🏰", section: "guild-hall", description: "Guild activity and bank management" },
    { name: "Items", icon: "🧳", section: "items", description: "Equipment and inventory" },
    { name: "Mortality", icon: "☠️", section: "mortality", description: "Death log and analysis" },
    { name: "Professions", icon: "🛠️", section: "professions", description: "Crafting and gathering skills" },
    { name: "Progression", icon: "🏆", section: "progression", description: "Level and experience history" },
    { name: "Quests", icon: "📜", section: "quests", description: "Quest log and completions" },
    { name: "Reputation", icon: "👑", section: "reputation", description: "Faction standings" },
    { name: "Role Performance", icon: "📊", section: "role", description: "DPS, Healing, and Tanking metrics" },
    { name: "Social", icon: "👥", section: "social", description: "Groups, friends, and social network" },
    { name: "Summary", icon: "🏠", section: "summary", description: "Character summary" },
    { name: "Travel Log", icon: "🗺️", section: "travel-log", description: "Zones and exploration" },
    { name: "Config", icon: "⚙️", section: "conf", description: "Settings and file upload" },
    { name: "The Bazaar", icon: "🛍️", section: "bazaar", description: "Trading and auctions" }
  ],
  
  stats: [
    { name: "DPS", icon: "⚔️", section: "role", description: "Damage per second" },
    { name: "HPS", icon: "💚", section: "role", description: "Healing per second" },
    { name: "DTPS", icon: "🛡️", section: "role", description: "Damage taken per second" },
    { name: "Gold", icon: "💰", section: "currencies", description: "Current gold amount" },
    { name: "Level", icon: "🏆", section: "progression", description: "Character level" },
    { name: "Experience", icon: "📊", section: "progression", description: "XP and leveling" },
    { name: "Deaths", icon: "☠️", section: "mortality", description: "Death count and causes" },
    { name: "Achievement Points", icon: "🎯", section: "achievements", description: "Total achievement score" },
    { name: "Item Level", icon: "⚔️", section: "character", description: "Average equipped item level" },
    { name: "Health", icon: "❤️", section: "character", description: "Maximum health" },
    { name: "Mana", icon: "💙", section: "character", description: "Maximum mana" },
    { name: "Armor", icon: "🛡️", section: "character", description: "Total armor rating" },
    { name: "Attack Power", icon: "⚡", section: "character", description: "Attack power rating" },
    { name: "Spell Power", icon: "✨", section: "character", description: "Spell power rating" },
    { name: "Strength", icon: "💪", section: "character", description: "Strength attribute" },
    { name: "Agility", icon: "🏃", section: "character", description: "Agility attribute" },
    { name: "Stamina", icon: "🏋️", section: "character", description: "Stamina attribute" },
    { name: "Intellect", icon: "🧠", section: "character", description: "Intellect attribute" },
    { name: "Spirit", icon: "✨", section: "character", description: "Spirit attribute" }
  ],
  
  features: [
    { name: "Combat Logs", icon: "⚔️", section: "role", description: "Detailed combat encounters" },
    { name: "Boss Fights", icon: "👹", section: "role", description: "Boss encounter performance" },
    { name: "Item Search", icon: "🔍", section: "items", description: "Find equipment and items" },
    { name: "Quest Completion", icon: "✅", section: "quests", description: "Completed quests" },
    { name: "Zone Exploration", icon: "🗺️", section: "travel-log", description: "Visited zones" },
    { name: "Reputation Factions", icon: "👥", section: "reputation", description: "Faction standings" },
    { name: "Profession Skills", icon: "🔨", section: "professions", description: "Crafting proficiency" },
    { name: "Death Analysis", icon: "📉", section: "mortality", description: "Death causes and patterns" },
    { name: "Level History", icon: "📈", section: "progression", description: "Leveling timeline" },
    { name: "Gold Trends", icon: "📹", section: "currencies", description: "Gold over time" },
    { name: "Guild Bank", icon: "🏰", section: "guild-hall", description: "Guild bank and transactions" },
    { name: "Guild Roster", icon: "👥", section: "guild-hall", description: "Guild member list" },
    { name: "Guild Activity", icon: "📈", section: "guild-hall", description: "Recent guild events" },
    { name: "Character Growth", icon: "📈", section: "character", description: "Stat progression over time" },
    { name: "Gear", icon: "⚔️", section: "character", description: "Equipped items and enchants" },
    { name: "Attributes", icon: "📊", section: "character", description: "Character stats and ratings" },
    { name: "Currency Balances", icon: "💰", section: "character", description: "All currency types" }
  ]
};

// Fuzzy matching function
function fuzzyMatch(query, target) {
  query = String(query || '').toLowerCase();
  target = String(target || '').toLowerCase();
  
  // Debug log for testing
  if (query === 'circle' || query === 'test') {
    console.log('[FuzzyMatch] Testing:', {query, target, includes: target.includes(query)});
  }
  
  // Exact substring match gets priority
  if (target.includes(query)) return 100;
  
  // Fuzzy match
  let qi = 0;
  let score = 0;
  let consecutive = 0;
  
  for (let ti = 0; ti < target.length && qi < query.length; ti++) {
    if (target[ti] === query[qi]) {
      qi++;
      consecutive++;
      score += consecutive * 2; // Bonus for consecutive matches
    } else {
      consecutive = 0;
    }
  }
  
  return qi === query.length ? score : 0;
}

// Search across all categories
async function performSearch(query) {
  if (!query || query.trim().length === 0) {
    return {
      sections: searchIndex.sections.slice(0, 24),
      stats: [],
      features: [],
      items: []
    };
  }
  
  const results = {
    sections: [],
    stats: [],
    features: [],
    items: []
  };
  
  // Search sections
  searchIndex.sections.forEach(item => {
    const nameScore = fuzzyMatch(query, item.name);
    const descScore = fuzzyMatch(query, item.description) * 0.5;
    const totalScore = nameScore + descScore;
    
    if (totalScore > 0) {
      results.sections.push({ ...item, score: totalScore });
    }
  });
  
  // Search stats
  searchIndex.stats.forEach(item => {
    const nameScore = fuzzyMatch(query, item.name);
    const descScore = fuzzyMatch(query, item.description) * 0.5;
    const totalScore = nameScore + descScore;
    
    if (totalScore > 0) {
      results.stats.push({ ...item, score: totalScore });
    }
  });
  
  // Search features
  searchIndex.features.forEach(item => {
    const nameScore = fuzzyMatch(query, item.name);
    const descScore = fuzzyMatch(query, item.description) * 0.5;
    const totalScore = nameScore + descScore;
    
    if (totalScore > 0) {
      results.features.push({ ...item, score: totalScore });
    }
  });
  
  // Search actual items from database (if we have a character)
  if (WhoDAT_currentCharacterId && WhoDAT_currentCharacterId !== 'bazaar') {
    try {
      // Fetch both equipped items (from summary) and inventory items
      const [summaryRes, itemsRes] = await Promise.all([
        fetch(`/sections/summary-data.php?character_id=${encodeURIComponent(WhoDAT_currentCharacterId)}`, { credentials: 'include' }),
        fetch(`/sections/items-data.php?character_id=${encodeURIComponent(WhoDAT_currentCharacterId)}`, { credentials: 'include' })
      ]);
      
      console.log('[Search] Items API response:', itemsRes.status, itemsRes.ok);
      console.log('[Search] Summary API response:', summaryRes.status, summaryRes.ok);
      
      const allItems = [];
      
      // Get equipped items from summary
      if (summaryRes.ok) {
        const summaryData = await summaryRes.json();
        if (summaryData.equipment && Array.isArray(summaryData.equipment)) {
          summaryData.equipment.forEach(item => {
            if (item.name) {
              allItems.push({
                name: item.name,
                quality_name: 'Equipped',
                count: 1,
                location: 'Equipped',
                slot: item.slot
              });
            }
          });
        }
      }
      
      // Get inventory items
      if (itemsRes.ok) {
        const itemsData = await itemsRes.json();
        allItems.push(
          ...(itemsData.bags || []),
          ...(itemsData.bank || []),
          ...(itemsData.mail || [])
        );
      }
      
      console.log('[Search] Total items to search:', allItems.length);
      
      // Log first few item names for debugging
      if (allItems.length > 0) {
        console.log('[Search] Sample item names:', allItems.slice(0, 5).map(i => i.name));
      }
      
      allItems.forEach(item => {
        if (item.name) {
          const score = fuzzyMatch(query, item.name);
          
          // Debug: log items that should match
          if (item.name.toLowerCase().includes(query.toLowerCase())) {
            console.log('[Search] Item contains query but fuzzy score:', item.name, score);
          }
          
          if (score > 0) {
            const quality = item.quality_name || 'Common';
            const count = item.count > 1 ? ` (x${item.count})` : '';
            const location = item.location || 'Unknown';
            
            // Choose icon based on location
            let icon = "\uD83C\uDF92"; // 🎲 bags default
            if (location === 'Equipped') icon = "\uD83D\uDEE1\uFE0F"; // 🛡️
            else if (location === 'Bank') icon = "\uD83C\uDFE6"; // 🏦
            else if (location === 'Mail') icon = "\u2709\uFE0F"; // ✉️
            
            const desc = location === 'Equipped' && item.slot 
              ? `Equipped • ${item.slot}`
              : `${quality}${count} • ${location}`;
            
            results.items.push({
              name: item.name,
              icon: icon,
              section: "items",
              description: desc,
              score: score
            });
          }
        }
      });
      
      console.log('[Search] Found item matches:', results.items.length);
      
    } catch (err) {
      // Log error for debugging
      console.error('[Search] Error fetching items:', err);
    }
  }
  
  // Sort by score
  results.sections.sort((a, b) => b.score - a.score);
  results.stats.sort((a, b) => b.score - a.score);
  results.features.sort((a, b) => b.score - a.score);
  results.items.sort((a, b) => b.score - a.score);
  
  // Limit results
  results.sections = results.sections.slice(0, 5);
  results.stats = results.stats.slice(0, 4);
  results.features = results.features.slice(0, 4);
  results.items = results.items.slice(0, 6);
  
  return results;
}

// Render the dropdown with categorized results
async function renderDropdown(query = "") {
  if (!sectionDropdown) return;
  
  // Show loading state if we have a query and character
  if (query && query.trim().length > 0 && WhoDAT_currentCharacterId && WhoDAT_currentCharacterId !== 'bazaar') {
    sectionDropdown.innerHTML = `
      <div class="search-loading">
        <div>Searching...</div>
      </div>
    `;
  }
  
  const results = await performSearch(query);
  const totalResults = results.sections.length + results.stats.length + results.features.length + results.items.length;
  
  if (totalResults === 0) {
    sectionDropdown.innerHTML = `
      <div class="no-results">
        <div>No matches found</div>
        <div style="margin-top: 8px; font-size: 0.85rem;">Try searching for pages, stats, features, or items</div>
      </div>
    `;
    return;
  }
  
  sectionDropdown.innerHTML = "";
  
  // Render items first if there are any (most relevant for content search)
  if (results.items.length > 0) {
    const categoryDiv = document.createElement('div');
    categoryDiv.className = 'search-category';
    categoryDiv.textContent = 'Your Items';
    sectionDropdown.appendChild(categoryDiv);
    
    results.items.forEach(item => {
      const itemDiv = document.createElement('div');
      itemDiv.className = 'section-item';
      itemDiv.innerHTML = `
        <div class="item-icon">${item.icon}</div>
        <div class="item-content">
          <div class="item-title">${item.name}</div>
          <div class="item-description">${item.description}</div>
        </div>
      `;
      itemDiv.onclick = () => {
        loadSection(item.section);
        sectionDropdown.classList.add('hidden');
        sectionSearch.value = "";
      };
      sectionDropdown.appendChild(itemDiv);
    });
  }
  
  // Render sections
  if (results.sections.length > 0) {
    const categoryDiv = document.createElement('div');
    categoryDiv.className = 'search-category';
    categoryDiv.textContent = 'Pages';
    sectionDropdown.appendChild(categoryDiv);
    
    results.sections.forEach(item => {
      const itemDiv = document.createElement('div');
      itemDiv.className = 'section-item';
      itemDiv.innerHTML = `
        <div class="item-icon">${item.icon}</div>
        <div class="item-content">
          <div class="item-title">${item.name}</div>
          <div class="item-description">${item.description}</div>
        </div>
      `;
      itemDiv.onclick = () => {
        loadSection(item.section);
        sectionDropdown.classList.add('hidden');
        sectionSearch.value = "";
      };
      sectionDropdown.appendChild(itemDiv);
    });
  }
  
  // Render stats
  if (results.stats.length > 0) {
    const categoryDiv = document.createElement('div');
    categoryDiv.className = 'search-category';
    categoryDiv.textContent = 'Stats & Metrics';
    sectionDropdown.appendChild(categoryDiv);
    
    results.stats.forEach(item => {
      const itemDiv = document.createElement('div');
      itemDiv.className = 'section-item';
      itemDiv.innerHTML = `
        <div class="item-icon">${item.icon}</div>
        <div class="item-content">
          <div class="item-title">${item.name}</div>
          <div class="item-description">${item.description}</div>
        </div>
      `;
      itemDiv.onclick = () => {
        loadSection(item.section);
        sectionDropdown.classList.add('hidden');
        sectionSearch.value = "";
      };
      sectionDropdown.appendChild(itemDiv);
    });
  }
  
  // Render features
  if (results.features.length > 0) {
    const categoryDiv = document.createElement('div');
    categoryDiv.className = 'search-category';
    categoryDiv.textContent = 'Features';
    sectionDropdown.appendChild(categoryDiv);
    
    results.features.forEach(item => {
      const itemDiv = document.createElement('div');
      itemDiv.className = 'section-item';
      itemDiv.innerHTML = `
        <div class="item-icon">${item.icon}</div>
        <div class="item-content">
          <div class="item-title">${item.name}</div>
          <div class="item-description">${item.description}</div>
        </div>
      `;
      itemDiv.onclick = () => {
        loadSection(item.section);
        sectionDropdown.classList.add('hidden');
        sectionSearch.value = "";
      };
      sectionDropdown.appendChild(itemDiv);
    });
  }
}

// Debounce function for better performance
let searchTimeout;
function debounceSearch(query) {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(async () => {
    await renderDropdown(query);
  }, 200); // Slightly increased for API calls
}

// Event listeners
sectionSearch?.addEventListener('focus', async () => {
  sectionDropdown.classList.remove('hidden');
  await renderDropdown(sectionSearch.value);
});

sectionSearch?.addEventListener('input', (e) => {
  debounceSearch(e.target.value);
});

// Keyboard navigation
let selectedIndex = -1;
sectionSearch?.addEventListener('keydown', (e) => {
  const items = sectionDropdown?.querySelectorAll('.section-item');
  if (!items || items.length === 0) return;
  
  if (e.key === 'ArrowDown') {
    e.preventDefault();
    selectedIndex = Math.min(selectedIndex + 1, items.length - 1);
    updateSelection(items);
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    selectedIndex = Math.max(selectedIndex - 1, -1);
    updateSelection(items);
  } else if (e.key === 'Enter' && selectedIndex >= 0) {
    e.preventDefault();
    items[selectedIndex].click();
  } else if (e.key === 'Escape') {
    sectionDropdown.classList.add('hidden');
    sectionSearch.value = "";
    selectedIndex = -1;
  }
});

function updateSelection(items) {
  items.forEach((item, i) => {
    if (i === selectedIndex) {
      item.classList.add('active');
      item.scrollIntoView({ block: 'nearest' });
    } else {
      item.classList.remove('active');
    }
  });
}

// Close dropdown when clicking outside
document.addEventListener('click', (e) => {
  if (!sectionSearch?.contains(e.target) && !sectionDropdown?.contains(e.target)) {
    sectionDropdown?.classList.add('hidden');
    selectedIndex = -1;
  }
});

// Global keyboard shortcut: Ctrl+/ or Cmd+/ to focus search
document.addEventListener('keydown', async (e) => {
  // Check for Ctrl+/ or Cmd+/ (keyCode 191 is forward slash)
  if ((e.ctrlKey || e.metaKey) && e.key === '/') {
    e.preventDefault();
    if (sectionSearch) {
      sectionSearch.focus();
      sectionDropdown.classList.remove('hidden');
      await renderDropdown(sectionSearch.value);
    }
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
        log("401 from", url, "— loading login");
        await loadSection('login', { push: false });
        return;
      }
      // Database not ready yet
      if (res.status === 503) {
        log("503 from", url, "— database starting up");
        host.innerHTML = `
          <div style="text-align:center; padding: 60px 20px;">
            <div style="font-size: 2rem; margin-bottom: 16px;">⏳</div>
            <div style="font-size: 1.1rem; color: #334e88; margin-bottom: 20px;">
              Database is starting up&hellip;
            </div>
            <button onclick="navigateTo('${section}')"
              style="background:#2456a5;color:#fff;border:none;border-radius:8px;padding:10px 24px;font-size:1rem;cursor:pointer;">
              Retry
            </button>
          </div>`;
        host.classList.remove('fade-out');
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
        if (push && history && history.pushState) {
          const hashName = section.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('');
          history.pushState({ section }, '', '#' + hashName);
        }
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
          const hashName = section.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('');
          history.pushState({ section }, '', '#' + hashName);
        }
      }
      // Toggle landing-page body class for login vs all other sections
      if (section === 'login') {
        document.body.classList.add('wd-landing-page');
      } else {
        document.body.classList.remove('wd-landing-page');
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
  // No route Ã¢â€ â€™ placeholder
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
window.navigateTo = navigateTo;

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
          navigateTo('onboarding');
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
    // If MO isn't available, no big dealÃ¢â‚¬â€other re-init paths still work.
  }
})();

/* ------------------------------ Controllers event hook ------------------------------ */
// Initialize controllers whenever a section-loaded event is broadcast
// (loadSection() also calls initSectionControllers() directly; guards prevent duplicate wiring)
document.addEventListener('whodat:section-loaded', () => {
  initSectionControllers();
});

/* ------------------------------ Hash routing helpers ------------------------------ */
/** Convert a section key like "guild-hall" → "GuildHall" */
function sectionToHash(section) {
  return section.split('-').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join('');
}
/** Convert a hash like "#GuildHall" → "guild-hall"; returns null if not a known section */
function hashToSection(hash) {
  const raw = (hash || '').replace(/^#/, '').toLowerCase();
  // Direct match
  if (sectionRoutes[raw]) return raw;
  // Try kebab-case reconstruction from CamelCase
  const kebab = raw.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
  if (sectionRoutes[kebab]) return kebab;
  // Scan all keys for case-insensitive match
  for (const key of Object.keys(sectionRoutes)) {
    if (key.replace(/-/g, '').toLowerCase() === raw) return key;
  }
  return null;
}

/* ------------------------------ popstate (back/forward) ------------------------------ */
window.addEventListener('popstate', (e) => {
  const section = (e.state && e.state.section)
    ? e.state.section
    : hashToSection(location.hash) || 'dashboard';
  loadSection(section, { push: false });
});

/* ------------------------------ Initial load ------------------------------ */
(async function initialLoad() {
  const host = getSectionContent();
  // 1) Ensure we have a character default from server (if any)
  await ensureActiveCharacterId();
  // 2) Build the character selector (menu + label)
  await WhoDAT_initCharacterSelect();
  // 3) Determine starting section from URL hash (supports direct links & refresh)
  const startSection = hashToSection(location.hash) || 'dashboard';
  await loadSection(startSection, { push: false });
  if (history && history.replaceState) {
    history.replaceState({ section: startSection }, '', '#' + sectionToHash(startSection));
  }
})();