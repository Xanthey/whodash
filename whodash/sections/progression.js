/* eslint-disable no-console */
/* WhoDASH Progression — Raid & Boss Kill Tracker */
(() => {
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[progression]', ...a);

  function formatDate(ts) {
    return new Date(ts * 1000).toLocaleDateString('en-US', { 
      month: 'short', day: 'numeric', year: 'numeric' 
    });
  }

  function formatDateTime(ts) {
    return new Date(ts * 1000).toLocaleString('en-US', { 
      month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
    });
  }

  function timeAgo(ts) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - ts;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    const days = Math.floor(diff / 86400);
    if (days < 30) return `${days}d ago`;
    return formatDate(ts);
  }

  // ===== Tab System =====
  function setupTabs(container) {
    const tabs = qa('.prog-tab', container);
    const contents = qa('.prog-tab-content', container);

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        contents.forEach(c => c.classList.remove('active'));
        const targetContent = q(`#prog-${target}`, container);
        if (targetContent) targetContent.classList.add('active');
      });
    });
  }

  // ===== Overview Tab =====
  function renderOverview(data) {
    const container = document.createElement('div');
    container.id = 'prog-overview';
    container.className = 'prog-tab-content active';

    // Stats Grid
    const statsGrid = document.createElement('div');
    statsGrid.className = 'prog-stats-grid';
    statsGrid.innerHTML = `
      <div class="prog-stat-card">
        <div class="stat-icon">👹</div>
        <div class="stat-value">${data.unique_bosses || 0}</div>
        <div class="stat-label">Unique Bosses Killed</div>
      </div>
      
      <div class="prog-stat-card">
        <div class="stat-icon">⚔️</div>
        <div class="stat-value">${(data.total_boss_kills || 0).toLocaleString()}</div>
        <div class="stat-label">Total Boss Kills</div>
      </div>
      
      <div class="prog-stat-card">
        <div class="stat-icon">🎯</div>
        <div class="stat-value">${data.most_killed_boss ? data.most_killed_boss.kill_count : 0}</div>
        <div class="stat-label">Most Farmed Boss</div>
        <div class="stat-sublabel">${data.most_killed_boss ? data.most_killed_boss.boss_name : 'N/A'}</div>
      </div>
    `;
    container.appendChild(statsGrid);

    // Raid Progression Section
    if (data.raid_progression && data.raid_progression.length > 0) {
      const raidSection = document.createElement('div');
      raidSection.className = 'raid-progression-section';
      
      const title = document.createElement('h3');
      title.textContent = '🏆 Raid Progression';
      raidSection.appendChild(title);

      // Group by instance
      const byInstance = {};
      data.raid_progression.forEach(prog => {
        if (!byInstance[prog.instance]) {
          byInstance[prog.instance] = [];
        }
        byInstance[prog.instance].push(prog);
      });

      Object.entries(byInstance).forEach(([instance, difficulties]) => {
        const raidCard = document.createElement('div');
        raidCard.className = 'raid-card';
        
        const raidTitle = document.createElement('div');
        raidTitle.className = 'raid-title';
        raidTitle.textContent = instance;
        raidCard.appendChild(raidTitle);

        difficulties.forEach(diff => {
          const diffRow = document.createElement('div');
          diffRow.className = 'raid-difficulty-row';
          
          diffRow.innerHTML = `
            <div class="diff-name">${diff.difficulty_name || 'Normal'}</div>
            <div class="raid-progress-bar">
              <div class="raid-progress-fill" style="width: ${diff.progress_pct}%"></div>
            </div>
            <div class="raid-progress-text">${diff.bosses_killed}/${diff.total_bosses}</div>
          `;
          
          raidCard.appendChild(diffRow);
        });

        raidSection.appendChild(raidCard);
      });

      container.appendChild(raidSection);
    }

    // Difficulty Breakdown Pie Chart
    if (data.difficulty_breakdown && data.difficulty_breakdown.length > 0) {
      const diffSection = document.createElement('div');
      diffSection.className = 'difficulty-breakdown-section';
      
      const title = document.createElement('h3');
      title.textContent = '📊 Difficulty Distribution';
      diffSection.appendChild(title);

      const canvas = document.createElement('canvas');
      canvas.className = 'difficulty-pie-chart';
      canvas.width = 300;
      canvas.height = 300;
      diffSection.appendChild(canvas);

      const legend = document.createElement('div');
      legend.className = 'pie-legend';
      diffSection.appendChild(legend);

      container.appendChild(diffSection);

      // Render pie chart
      setTimeout(() => renderDifficultyPieChart(canvas, legend, data.difficulty_breakdown), 0);
    }

    return container;
  }

  // ===== Difficulty Pie Chart =====
  function renderDifficultyPieChart(canvas, legendEl, data) {
    const ctx = canvas.getContext('2d');
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = Math.min(centerX, centerY) - 20;

    const total = data.reduce((sum, d) => sum + d.kill_count, 0);
    const colors = ['#3182ce', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

    let currentAngle = -Math.PI / 2;

    data.forEach((diff, idx) => {
      const sliceAngle = (diff.kill_count / total) * 2 * Math.PI;
      
      // Draw slice
      ctx.fillStyle = colors[idx % colors.length];
      ctx.beginPath();
      ctx.moveTo(centerX, centerY);
      ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
      ctx.closePath();
      ctx.fill();

      // Outline
      ctx.strokeStyle = '#fff';
      ctx.lineWidth = 2;
      ctx.stroke();

      currentAngle += sliceAngle;
    });

    // Legend
    legendEl.innerHTML = data.map((diff, idx) => {
      const pct = ((diff.kill_count / total) * 100).toFixed(1);
      return `
        <div class="legend-item">
          <div class="legend-color" style="background: ${colors[idx % colors.length]}"></div>
          <div class="legend-label">${diff.difficulty_name || 'Normal'}</div>
          <div class="legend-value">${pct}% (${diff.kill_count})</div>
        </div>
      `;
    }).join('');
  }

  // ===== Boss Kills Tab =====
  function renderBossKills(data) {
    const container = document.createElement('div');
    container.id = 'prog-boss-kills';
    container.className = 'prog-tab-content';

    const header = document.createElement('div');
    header.className = 'boss-kills-header';
    header.innerHTML = `
      <h3>⚔️ Boss Kill Log</h3>
      <div class="boss-filters">
        <input type="text" id="bossSearchInput" class="boss-search-input" placeholder="Search boss or instance...">
        <select id="difficultyFilter" class="difficulty-filter">
          <option value="">All Difficulties</option>
        </select>
        <button id="clearBossFilters" class="clear-filters-btn">Clear</button>
      </div>
    `;
    container.appendChild(header);

    // Populate difficulty filter
    const difficultySet = new Set();
    data.all_boss_kills.forEach(k => {
      if (k.difficulty_name) difficultySet.add(k.difficulty_name);
    });
    
    setTimeout(() => {
      const diffFilter = q('#difficultyFilter', container);
      if (diffFilter) {
        Array.from(difficultySet).sort().forEach(diff => {
          const option = document.createElement('option');
          option.value = diff;
          option.textContent = diff;
          diffFilter.appendChild(option);
        });
      }
    }, 0);

    if (!data.all_boss_kills || data.all_boss_kills.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No boss kills recorded';
      container.appendChild(msg);
      return container;
    }

    const tableContainer = document.createElement('div');
    tableContainer.className = 'boss-kills-table-container';
    
    const table = document.createElement('table');
    table.className = 'boss-kills-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Boss</th>
          <th>Instance</th>
          <th>Difficulty</th>
          <th>Group</th>
          <th>When</th>
        </tr>
      </thead>
      <tbody id="bossKillsTableBody"></tbody>
    `;
    tableContainer.appendChild(table);
    container.appendChild(tableContainer);

    const paginationInfo = document.createElement('div');
    paginationInfo.className = 'pagination-info';
    paginationInfo.id = 'bossKillsPaginationInfo';
    container.appendChild(paginationInfo);

    // Store data
    container.dataset.bossKillsData = JSON.stringify(data.all_boss_kills);

    // Initial render
    setTimeout(() => filterAndRenderBossKills(data.all_boss_kills, container), 0);

    // Setup filters
    setTimeout(() => {
      const searchInput = q('#bossSearchInput', container);
      const diffFilter = q('#difficultyFilter', container);
      const clearBtn = q('#clearBossFilters', container);

      const applyFilters = () => {
        const allData = JSON.parse(container.dataset.bossKillsData);
        filterAndRenderBossKills(allData, container);
      };

      if (searchInput) searchInput.addEventListener('input', applyFilters);
      if (diffFilter) diffFilter.addEventListener('change', applyFilters);
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          if (searchInput) searchInput.value = '';
          if (diffFilter) diffFilter.value = '';
          applyFilters();
        });
      }
    }, 0);

    return container;
  }

  function filterAndRenderBossKills(allData, container) {
    const searchInput = q('#bossSearchInput', container);
    const diffFilter = q('#difficultyFilter', container);
    const tbody = q('#bossKillsTableBody', container);
    const paginationInfo = q('#bossKillsPaginationInfo', container);

    if (!tbody) return;

    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const selectedDiff = diffFilter ? diffFilter.value : '';

    const filtered = allData.filter(kill => {
      const matchesSearch = !searchTerm || 
        (kill.boss_name && kill.boss_name.toLowerCase().includes(searchTerm)) ||
        (kill.instance && kill.instance.toLowerCase().includes(searchTerm));
      
      const matchesDiff = !selectedDiff || kill.difficulty_name === selectedDiff;

      return matchesSearch && matchesDiff;
    });

    tbody.innerHTML = filtered.map(kill => {
      const diffClass = getDifficultyClass(kill.difficulty_name);
      return `
        <tr>
          <td><strong>${kill.boss_name || 'Unknown Boss'}</strong></td>
          <td>${kill.instance || 'Unknown'}</td>
          <td><span class="difficulty-badge ${diffClass}">${kill.difficulty_name || 'Normal'}</span></td>
          <td>${kill.group_size || '?'}-${kill.group_type || 'raid'}</td>
          <td class="muted">${formatDateTime(kill.ts)}</td>
        </tr>
      `;
    }).join('');

    if (paginationInfo) {
      paginationInfo.textContent = `Showing ${filtered.length.toLocaleString()} of ${allData.length.toLocaleString()} kills`;
    }
  }

  function getDifficultyClass(diffName) {
    if (!diffName) return 'diff-normal';
    const lower = diffName.toLowerCase();
    if (lower.includes('heroic')) return 'diff-heroic';
    if (lower.includes('mythic')) return 'diff-mythic';
    if (lower.includes('hard')) return 'diff-heroic';
    return 'diff-normal';
  }

  // ===== Lockouts Tab =====
  function renderLockouts(data) {
    const container = document.createElement('div');
    container.id = 'prog-lockouts';
    container.className = 'prog-tab-content';

    // Active Lockouts
    const activeSection = document.createElement('div');
    activeSection.className = 'lockouts-section';
    
    const activeTitle = document.createElement('h3');
    activeTitle.textContent = '🔒 Active Lockouts';
    activeSection.appendChild(activeTitle);

    if (data.active_lockouts && data.active_lockouts.length > 0) {
      const lockoutsGrid = document.createElement('div');
      lockoutsGrid.className = 'lockouts-grid';
      
      data.active_lockouts.forEach(lockout => {
        const card = document.createElement('div');
        card.className = 'lockout-card';
        card.innerHTML = `
          <div class="lockout-header">
            <div class="lockout-instance">${lockout.instance_name}</div>
            <div class="lockout-difficulty">${lockout.difficulty_name || 'Normal'}</div>
          </div>
          <div class="lockout-progress">
            <div class="lockout-progress-bar">
              <div class="lockout-progress-fill" style="width: ${(lockout.bosses_killed / lockout.total_bosses * 100)}%"></div>
            </div>
            <div class="lockout-progress-text">${lockout.bosses_killed}/${lockout.total_bosses} bosses</div>
          </div>
          <div class="lockout-reset">
            Resets in ${lockout.days_until_reset} day${lockout.days_until_reset !== 1 ? 's' : ''}
            ${lockout.extended ? '<span class="extended-badge">Extended</span>' : ''}
          </div>
        `;
        lockoutsGrid.appendChild(card);
      });
      
      activeSection.appendChild(lockoutsGrid);
    } else {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No active lockouts';
      activeSection.appendChild(msg);
    }
    
    container.appendChild(activeSection);

    // Lockout History
    if (data.lockout_history && data.lockout_history.length > 0) {
      const historySection = document.createElement('div');
      historySection.className = 'lockout-history-section';
      
      const historyTitle = document.createElement('h3');
      historyTitle.textContent = '📊 Lockout History (Last 6 Months)';
      historySection.appendChild(historyTitle);

      const table = document.createElement('table');
      table.className = 'lockout-history-table';
      table.innerHTML = `
        <thead>
          <tr>
            <th>Instance</th>
            <th>Weeks Raided</th>
            <th>Avg Bosses/Week</th>
            <th>Avg Clear %</th>
            <th>Full Clears</th>
            <th>Best Week</th>
          </tr>
        </thead>
        <tbody>
          ${data.lockout_history.map(hist => `
            <tr>
              <td><strong>${hist.instance_name}</strong></td>
              <td>${hist.weeks_raided}</td>
              <td>${hist.avg_bosses_per_week}</td>
              <td>${hist.avg_clear_pct}%</td>
              <td>${hist.full_clears}</td>
              <td>${hist.best_week} bosses</td>
            </tr>
          `).join('')}
        </tbody>
      `;
      
      historySection.appendChild(table);
      container.appendChild(historySection);
    }

    return container;
  }

  // ===== Main Render =====
  async function initProgression() {
    const section = q('#tab-progression');
    if (!section) {
      log('Section not found');
      return;
    }

    const characterId = section.dataset.characterId;
    if (!characterId) {
      section.innerHTML = '<div class="muted">No character selected</div>';
      return;
    }

    log('Loading progression data for character', characterId);

    try {
      const response = await fetch(`/sections/progression-data.php?character_id=${characterId}`, {
        credentials: 'include'
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        if (errorData && errorData.message) {
          throw new Error(`HTTP ${response.status}: ${errorData.message}`);
        }
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      log('Progression data loaded:', data);

      const container = document.createElement('div');
      container.className = 'prog-container';

      // Tab Navigation
      const tabNav = document.createElement('div');
      tabNav.className = 'prog-tabs';
      tabNav.innerHTML = `
        <button class="prog-tab active" data-tab="overview">🏆 Overview</button>
        <button class="prog-tab" data-tab="boss-kills">⚔️ Boss Kills</button>
        <button class="prog-tab" data-tab="lockouts">🔒 Lockouts</button>
      `;
      container.appendChild(tabNav);

      // Tab Contents
      const contentWrapper = document.createElement('div');
      contentWrapper.className = 'prog-content-wrapper';
      
      contentWrapper.appendChild(renderOverview(data));
      contentWrapper.appendChild(renderBossKills(data));
      contentWrapper.appendChild(renderLockouts(data));
      
      container.appendChild(contentWrapper);

      section.innerHTML = '';
      section.appendChild(container);

      setupTabs(section);

    } catch (error) {
      log('Error loading progression data:', error);
      section.innerHTML = `<div class="muted">Error loading progression data: ${error.message}</div>`;
    }
  }

  // ===== Auto-init =====
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'progression') {
      log('Section loaded event received');
      initProgression();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (q('#tab-progression')) initProgression();
    });
  } else {
    if (q('#tab-progression')) initProgression();
  }

  log('Progression module loaded');
})();