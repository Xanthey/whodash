/* eslint-disable no-console */
/* WhoDASH Professions — Skills & Tradeskills Tracker */
(() => {
  const q  = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[professions]', ...a);

  // =========================================================================
  // Icon helpers
  // =========================================================================

  // WoW addon sends icons as full Windows paths:
  //   "Interface\\Icons\\INV_Jewelry_Necklace_28"
  //   "Interface/Icons/INV_Jewelry_Necklace_28"
  //   or just "inv_jewelry_necklace_28"
  // We need just the filename, lowercased.
  function cleanIconName(raw) {
    if (!raw) return null;
    // Extract everything after the last \ or /
    const parts = raw.replace(/\\/g, '/').split('/');
    const name = parts[parts.length - 1].trim().toLowerCase();
    return name || null;
  }

  // Build zamimg CDN URL from raw icon value (handles full paths)
  function iconUrl(rawIcon, size = 'small') {
    const name = cleanIconName(rawIcon) || 'inv_misc_questionmark';
    return `https://wow.zamimg.com/images/wow/icons/${size}/${name}.jpg`;
  }

  const FALLBACK_ICON = 'https://wow.zamimg.com/images/wow/icons/small/inv_misc_questionmark.jpg';

  // =========================================================================
  // Tooltip System
  // =========================================================================

  let _tooltipEl       = null;
  let _tooltipHovering = false;
  let _tooltipTimeout  = null;
  const _tooltipCache  = {}; // key → resolved data OR in-flight Promise

  function getTooltipEl() {
    if (!_tooltipEl) {
      _tooltipEl = document.createElement('div');
      _tooltipEl.className = 'wd-tooltip';
      _tooltipEl.style.display = 'none';
      document.body.appendChild(_tooltipEl);
    }
    return _tooltipEl;
  }

  function positionTooltip(e) {
    const el  = getTooltipEl();
    const pad = 16;
    const tw  = el.offsetWidth  || 280;
    const th  = el.offsetHeight || 80;
    let x = e.clientX + pad;
    let y = e.clientY + pad;
    if (x + tw + 4 > window.innerWidth)  x = e.clientX - tw - pad;
    if (y + th + 4 > window.innerHeight) y = e.clientY - th - pad;
    el.style.left = Math.max(4, x) + 'px';
    el.style.top  = Math.max(4, y) + 'px';
  }

  function hideTooltip() {
    clearTimeout(_tooltipTimeout);
    if (_tooltipHovering) return;
    const el = getTooltipEl();
    el.style.display = 'none';
    el.innerHTML = '';
  }

  // Render tooltip HTML from tooltip.php response
  function renderTooltipHtml(data, fallbackName) {
    const qualityColors = {
      'q-poor': '#9d9d9d', 'q-common': '#ffffff', 'q-uncommon': '#1eff00',
      'q-rare': '#0070dd', 'q-epic': '#a335ee',   'q-legendary': '#ff8000',
      'q-artifact': '#e6cc80', 'q-heirloom': '#00ccff',
    };
    const qualityColor = qualityColors[data.quality_class] || '#ffffff';
    const iconName  = data.icon ? data.icon.toLowerCase() : null;
    const iconHtml  = iconName
      ? `<img class="wd-tooltip-icon" src="${iconUrl(iconName, 'medium')}" alt="" onerror="this.style.display='none'">`
      : '';

    // Render stat lines (PHP has already parsed tooltip_enus into clean text)
    let bodyHtml = '';
    const stats = (data.stats || []);
    const statHtml = stats.map(line => {
      if (/^\+\d/.test(line) || /^-\d/.test(line))                return `<div class="wd-tooltip-stat wd-tooltip-stat--bonus">${esc(line)}</div>`;
      if (/^[\u201c\u201d""]/.test(line))                          return `<div class="wd-tooltip-stat wd-tooltip-stat--flavor">${esc(line)}</div>`;
      if (/^Requires/i.test(line))                                 return `<div class="wd-tooltip-stat wd-tooltip-stat--req">${esc(line)}</div>`;
      if (/^(Use|Equip|Chance|Cooldown):/i.test(line))             return `<div class="wd-tooltip-stat wd-tooltip-stat--use">${esc(line)}</div>`;
      if (/^Sell Price:/i.test(line))                              return `<div class="wd-tooltip-stat wd-tooltip-stat--sell">${esc(line)}</div>`;
      return `<div class="wd-tooltip-stat">${esc(line)}</div>`;
    }).join('');
    bodyHtml = statHtml ? `<div class="wd-tooltip-stats">${statHtml}</div>` : '';

    // Reagents section — stacked list with availability indicator
    let reagentHtml = '';
    if (data.reagents && data.reagents.length > 0) {
      const rows = data.reagents.map(r => {
        const iconEl = r.icon
          ? `<img src="${iconUrl(r.icon, 'small')}" class="wd-tooltip-reagent-icon" alt="" onerror="this.style.display='none'">`
          : '<span class="wd-tooltip-reagent-icon-placeholder"></span>';
        const nameEl  = esc(r.name || `Item #${r.id}`);
        const haveQty = r.have ?? null;
        let availEl = '';
        if (haveQty !== null) {
          if (r.available) {
            availEl = `<span class="wd-reagent-have wd-reagent-have--ok" title="${haveQty} in bags/bank">✓ ${haveQty}</span>`;
          } else {
            availEl = `<span class="wd-reagent-have wd-reagent-have--low" title="${haveQty} of ${r.qty} needed">${haveQty}/${r.qty}</span>`;
          }
        }
        return `<div class="wd-tooltip-reagent">${iconEl}<span class="wd-reagent-qty">${esc(r.qty)}x</span><span class="wd-reagent-name">${nameEl}</span>${availEl}</div>`;
      }).join('');
      reagentHtml = `<div class="wd-tooltip-reagents"><div class="wd-tooltip-reagent-label">Reagents</div>${rows}</div>`;
    }

    const link = data.wowhead_url
      ? `<a class="wd-tooltip-link" href="${data.wowhead_url}" target="_blank" rel="noopener">View on Wowhead ↗</a>`
      : '';

    return `
      <div class="wd-tooltip-header">
        ${iconHtml}
        <div class="wd-tooltip-name" style="color:${qualityColor}">${esc(data.name || fallbackName)}</div>
      </div>
      ${bodyHtml}
      ${reagentHtml}
      ${link ? `<div class="wd-tooltip-footer">${link}</div>` : ''}
    `;
  }

  // Parse link type and ID from WoW hyperlink: |Hitem:30422:...|  |Henchant:7454|
  function parseLinkInfo(link) {
    if (!link) return null;
    const m = link.match(/H(item|enchant|spell):(\d+)/);
    if (!m) return null;
    return { type: m[1], id: parseInt(m[2], 10) };
  }

  // Fetch with caching — keyed by type+id from the WoW link
  async function fetchTooltip(linkInfo, fallbackName, characterId) {
    if (!linkInfo) return null;
    const key = `${linkInfo.type}_${linkInfo.id}`;
    if (key in _tooltipCache) return _tooltipCache[key];

    const params = new URLSearchParams({ type: linkInfo.type, id: String(linkInfo.id) });
    if (characterId) params.set('character_id', String(characterId));
    const p = fetch(`/tooltip.php?${params}`, { credentials: 'include' })
      .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
      .then(data => { _tooltipCache[key] = data; return data; })
      .catch(err => { delete _tooltipCache[key]; throw err; });

    _tooltipCache[key] = p;
    return p;
  }

  function attachTooltip(el, recipe, characterId) {
    const linkInfo = parseLinkInfo(recipe.link);
    const spellName = recipe.name || '';

    el.addEventListener('mouseenter', async (e) => {
      _tooltipHovering = true;
      clearTimeout(_tooltipTimeout);

      const tooltip = getTooltipEl();
      tooltip.innerHTML = '<div class="wd-tooltip-loading">Loading…</div>';
      tooltip.style.display = 'block';
      positionTooltip(e);

      try {
        const data = await fetchTooltip(linkInfo, spellName, characterId);
        if (!data) throw new Error("No link info available");
        if (_tooltipHovering) {
          tooltip.innerHTML = renderTooltipHtml(data, recipe.name);
          tooltip.style.display = 'block';
          positionTooltip(e);
        }
      } catch (err) {
        log('Tooltip error for', recipe.name, ':', err.message);
        if (_tooltipHovering) {
          tooltip.innerHTML = `
            <div class="wd-tooltip-header">
              <div class="wd-tooltip-name q-common">${esc(recipe.name)}</div>
            </div>
            <div class="wd-tooltip-stats">
              <div class="wd-tooltip-stat wd-tooltip-stat--req">Tooltip data unavailable</div>
            </div>`;
          tooltip.style.display = 'block';
          positionTooltip(e);
        }
      }
    });

    el.addEventListener('mousemove', (e) => {
      if (_tooltipHovering) positionTooltip(e);
    });

    el.addEventListener('mouseleave', () => {
      _tooltipHovering = false;
      _tooltipTimeout = setTimeout(hideTooltip, 220);
    });
  }

  // =========================================================================
  // Recipe quality dot
  // =========================================================================
  const RECIPE_QUALITY = {
    'Trivial'  : 'rq-trivial',
    'Easy'     : 'rq-easy',
    'Medium'   : 'rq-medium',
    'Optimal'  : 'rq-optimal',
    'Difficult': 'rq-difficult',
  };

  function getQualityInfo(typeStr) {
    if (!typeStr) return null;
    for (const [key, cssClass] of Object.entries(RECIPE_QUALITY)) {
      if (typeStr.includes(key)) return { key, cssClass };
    }
    return null;
  }

  // =========================================================================
  // Utilities
  // =========================================================================

  function esc(str) {
    return String(str || '')
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // =========================================================================
  // Tab system
  // =========================================================================
  function setupTabs(container) {
    qa('.prof-tab', container).forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        qa('.prof-tab', container).forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        qa('.prof-tab-content', container).forEach(c => c.classList.remove('active'));
        const tc = q(`#prof-${target}`, container);
        if (tc) tc.classList.add('active');
      });
    });
  }

  // =========================================================================
  // Overview Tab
  // =========================================================================
  function renderOverview(data) {
    const container = document.createElement('div');
    container.id = 'prof-overview';
    container.className = 'prof-tab-content active';

    container.innerHTML = `
      <div class="prof-stats-grid">
        <div class="prof-stat-card">
          <div class="stat-icon">🛠️</div>
          <div class="stat-value">${data.total_professions || 0}</div>
          <div class="stat-label">Primary Professions</div>
        </div>
        <div class="prof-stat-card">
          <div class="stat-icon">📚</div>
          <div class="stat-value">${(data.total_recipes || 0).toLocaleString()}</div>
          <div class="stat-label">Recipes Known</div>
        </div>
        <div class="prof-stat-card">
          <div class="stat-icon">🎯</div>
          <div class="stat-value">${data.highest_skill?.rank || 0}</div>
          <div class="stat-label">Highest Skill</div>
          <div class="stat-sublabel">${data.highest_skill?.name || 'N/A'}</div>
        </div>
      </div>
    `;

    if (data.primary_professions?.length > 0) {
      const sec = document.createElement('div');
      sec.className = 'professions-section';
      sec.innerHTML = `<h3>🛠️ Primary Professions</h3>
        <div class="profession-cards">
          ${data.primary_professions.map(prof => {
            const pct = prof.max_rank > 0 ? (prof.rank / prof.max_rank * 100) : 0;
            return `
              <div class="profession-card">
                <div class="profession-header">
                  <div class="profession-name">${esc(prof.name)}</div>
                  <div class="profession-rank">${prof.rank}/${prof.max_rank}</div>
                </div>
                <div class="profession-progress-bar">
                  <div class="profession-progress-fill" style="width:${pct}%"></div>
                </div>
                <div class="profession-stats">📖 ${prof.recipes_known || 0} recipes known</div>
              </div>`;
          }).join('')}
        </div>`;
      container.appendChild(sec);
    }

    if (data.secondary_skills?.length > 0) {
      const sec = document.createElement('div');
      sec.className = 'secondary-section';
      sec.innerHTML = `<h3>📖 Secondary Skills</h3>
        <div class="secondary-cards">
          ${data.secondary_skills.map(skill => {
            const pct = skill.max_rank > 0 ? (skill.rank / skill.max_rank * 100) : 0;
            return `
              <div class="secondary-card">
                <div class="secondary-name">${esc(skill.name)}</div>
                <div class="secondary-progress">
                  <div class="secondary-progress-bar">
                    <div class="secondary-progress-fill" style="width:${pct}%"></div>
                  </div>
                  <div class="secondary-rank">${skill.rank}/${skill.max_rank}</div>
                </div>
              </div>`;
          }).join('')}
        </div>`;
      container.appendChild(sec);
    }

    if (data.recipe_counts_by_profession?.length > 0) {
      const sec = document.createElement('div');
      sec.className = 'recipe-counts-section';
      sec.innerHTML = `<h3>📊 Recipe Collection</h3>
        <table class="recipe-counts-table">
          <thead><tr><th>Profession</th><th>Total</th><th>Rare</th><th>Epic</th></tr></thead>
          <tbody>
            ${data.recipe_counts_by_profession.map(p => `
              <tr>
                <td><strong>${esc(p.profession || 'Unknown')}</strong></td>
                <td>${p.recipe_count || 0}</td>
                <td>${p.rare_recipes || 0}</td>
                <td>${p.epic_recipes || 0}</td>
              </tr>`).join('')}
          </tbody>
        </table>`;
      container.appendChild(sec);
    }

    return container;
  }

  // =========================================================================
  // Recipes Tab
  // =========================================================================

  function buildRecipeRow(recipe, characterId) {
    const quality = getQualityInfo(recipe.type);
    const row = document.createElement('div');
    row.className = 'recipe-row';
    row.dataset.recipeName = recipe.name || '';

    // Icon — extract clean name from full WoW path, then hit zamimg directly
    const iconEl = document.createElement('img');
    iconEl.className = 'recipe-row-icon';
    iconEl.alt = '';
    iconEl.loading = 'lazy';
    iconEl.src = iconUrl(recipe.icon, 'small');
    iconEl.onerror = function () { this.src = FALLBACK_ICON; this.onerror = null; };
    row.appendChild(iconEl);

    // Name + quality dot
    const nameWrap = document.createElement('div');
    nameWrap.className = 'recipe-row-name-wrap';
    if (quality) {
      const dot = document.createElement('span');
      dot.className = `recipe-quality-dot ${quality.cssClass}`;
      dot.title = quality.key;
      nameWrap.appendChild(dot);
    }
    const nameEl = document.createElement('span');
    nameEl.className = 'recipe-row-name';
    nameEl.textContent = recipe.name || 'Unknown Recipe';
    nameWrap.appendChild(nameEl);
    row.appendChild(nameWrap);

    // Cooldown right column
    if (recipe.cooldown_text) {
      const cd = document.createElement('div');
      cd.className = 'recipe-row-stats recipe-row-stats--cooldown';
      cd.textContent = `⏰ ${recipe.cooldown_text}`;
      row.appendChild(cd);
    }

    attachTooltip(row, recipe, characterId);
    return row;
  }

  function groupByProfession(recipes) {
    const groups = {};
    recipes.forEach(r => {
      const p = r.profession || 'Other';
      if (!groups[p]) groups[p] = [];
      groups[p].push(r);
    });
    return groups;
  }

  function renderProfBlock(profName, recipes, searchTerm, characterId) {
    const block = document.createElement('div');
    block.className = 'recipe-prof-block';

    block.innerHTML = `
      <div class="recipe-prof-header">
        <span class="recipe-prof-name">${esc(profName)}</span>
        <span class="recipe-prof-count">${recipes.length} recipes</span>
      </div>
      <div class="recipe-prof-list"></div>
    `;

    const list  = q('.recipe-prof-list', block);
    const lower = searchTerm.toLowerCase();
    let visible = 0;

    recipes.forEach(recipe => {
      if (lower && !(recipe.name || '').toLowerCase().includes(lower)) return;
      list.appendChild(buildRecipeRow(recipe, characterId));
      visible++;
    });

    if (visible === 0 && searchTerm) {
      list.innerHTML = '<div class="recipe-empty-filter">No matching recipes</div>';
    }

    block._visible = visible;
    return block;
  }

  function renderRecipes(data, characterId) {
    const container = document.createElement('div');
    container.id = 'prof-recipes';
    container.className = 'prof-tab-content';

    container.innerHTML = `
      <div class="recipes-header">
        <h3>📚 Recipe Collection</h3>
        <div class="recipe-filters">
          <div class="recipe-search-wrap">
            <span class="recipe-search-icon">🔍</span>
            <input type="text" id="recipeSearchInput" class="recipe-search-input" placeholder="Search recipes…">
          </div>
          <select id="professionFilter" class="profession-filter">
            <option value="">All Professions</option>
          </select>
          <button id="clearRecipeFilters" class="clear-filters-btn">Clear</button>
        </div>
        <div class="recipe-results-info" id="recipeResultsInfo"></div>
      </div>
      <div class="recipes-body" id="recipesBody"></div>
    `;

    if (!data.all_recipes?.length) {
      q('#recipesBody', container).innerHTML = '<p class="muted">No recipes learned yet</p>';
      return container;
    }

    const profSet   = new Set(data.all_recipes.map(r => r.profession).filter(Boolean));
    const profOrder = Array.from(profSet).sort();

    function applyFilters() {
      const searchTerm   = (q('#recipeSearchInput', container)?.value || '').trim();
      const selectedProf = q('#professionFilter', container)?.value || '';
      const filtered     = selectedProf ? data.all_recipes.filter(r => r.profession === selectedProf) : data.all_recipes;
      const groups       = groupByProfession(filtered);
      const body         = q('#recipesBody', container);
      body.innerHTML = '';

      let totalVisible = 0;
      profOrder.forEach(prof => {
        if (!groups[prof]) return;
        const block = renderProfBlock(prof, groups[prof], searchTerm, characterId);
        totalVisible += block._visible;
        if (!searchTerm || block._visible > 0) body.appendChild(block);
      });

      const info = q('#recipeResultsInfo', container);
      if (info) {
        info.textContent = (searchTerm || selectedProf)
          ? `Showing ${totalVisible.toLocaleString()} of ${data.all_recipes.length.toLocaleString()} recipes`
          : `${data.all_recipes.length.toLocaleString()} recipes`;
      }
    }

    setTimeout(() => {
      const profFilter  = q('#professionFilter', container);
      const searchInput = q('#recipeSearchInput', container);
      const clearBtn    = q('#clearRecipeFilters', container);

      profOrder.forEach(prof => {
        const opt = document.createElement('option');
        opt.value = prof; opt.textContent = prof;
        profFilter?.appendChild(opt);
      });

      searchInput?.addEventListener('input', applyFilters);
      profFilter?.addEventListener('change', applyFilters);
      clearBtn?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (profFilter)  profFilter.value  = '';
        applyFilters();
      });

      applyFilters();
    }, 0);

    return container;
  }

  // =========================================================================
  // Main Init
  // =========================================================================
  async function initProfessions() {
    const section = q('#tab-professions');
    if (!section) { log('Section not found'); return; }

    const characterId = section.dataset.characterId;
    if (!characterId) {
      section.innerHTML = '<div class="muted">No character selected</div>';
      return;
    }

    log('Loading profession data for character', characterId);

    try {
      const response = await fetch(`/sections/professions-data.php?character_id=${characterId}`, { credentials: 'include' });
      if (!response.ok) {
        const err = await response.json().catch(() => null);
        throw new Error(`HTTP ${response.status}${err?.message ? ': ' + err.message : ''}`);
      }

      const data = await response.json();
      log('Profession data loaded:', data);

      const container = document.createElement('div');
      container.className = 'prof-container';
      container.innerHTML = `
        <div class="prof-tabs">
          <button class="prof-tab active" data-tab="overview">📊 Overview</button>
          <button class="prof-tab" data-tab="recipes">📚 Recipes</button>
        </div>
        <div class="prof-content-wrapper"></div>
      `;

      const wrapper = q('.prof-content-wrapper', container);
      wrapper.appendChild(renderOverview(data));
      wrapper.appendChild(renderRecipes(data, characterId));

      section.innerHTML = '';
      section.appendChild(container);
      setupTabs(section);

    } catch (error) {
      log('Error loading profession data:', error);
      section.innerHTML = `<div class="muted">Error loading profession data: ${error.message}</div>`;
    }
  }

  // =========================================================================
  // Auto-init
  // =========================================================================
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'professions') initProfessions();
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => { if (q('#tab-professions')) initProfessions(); });
  } else {
    if (q('#tab-professions')) initProfessions();
  }

  log('Professions module loaded');
})();