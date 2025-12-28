/* eslint-disable no-console */
/* WhoDASH Mortality – Death Analysis Dashboard */
(() => {
  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[mortality]', ...a);

  function formatDuration(seconds) {
    if (!seconds || seconds < 60) return `${Math.floor(seconds || 0)}s`;
    const hours = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    if (hours > 24) {
      const days = Math.floor(hours / 24);
      const h = hours % 24;
      return `${days}d ${h}h`;
    }
    return hours > 0 ? `${hours}h ${mins}m` : `${mins}m`;
  }

  function formatDate(timestamp) {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString('en-US', { 
      month: 'short', 
      day: 'numeric', 
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }

  function getDeathSubtitle(totalDeaths) {
    if (totalDeaths === 0) return "Immortal Legend";
    if (totalDeaths < 10) return "Cautious Adventurer";
    if (totalDeaths < 50) return "Learning the Hard Way";
    if (totalDeaths < 100) return "Seasoned Corpse Runner";
    if (totalDeaths < 500) return "Frequent Flyer";
    if (totalDeaths < 1000) return "Spirit Healer's Friend";
    return "Death's Doormat";
  }

  function getRezTypeLabel(rezType) {
    const labels = {
      'spirit': 'Spirit Healer',
      'corpse': 'Corpse Run',
      'soulstone': 'Soulstone',
      'class_rez': 'Battle Rez',
      'unknown': 'Unknown'
    };
    return labels[rezType] || rezType;
  }

  // ===== Tab System =====
  function setupTabs(container) {
    const tabs = qa('.mortality-tab', container);
    const contents = qa('.mortality-tab-content', container);

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        contents.forEach(c => c.classList.remove('active'));
        const targetContent = q(`#mort-${target}`, container);
        if (targetContent) targetContent.classList.add('active');
      });
    });
  }

  // ===== TAB 1: OVERVIEW =====
  function renderOverviewTab(data) {
    const container = document.createElement('div');
    container.id = 'mort-overview';
    container.className = 'mortality-tab-content active';

    // Top stats row
    const statsRow = document.createElement('div');
    statsRow.className = 'dashboard-section dashboard-row';
    statsRow.appendChild(renderDeathStatistics(data.overview));
    statsRow.appendChild(renderArchNemesis(data.overview));
    container.appendChild(statsRow);

    // Trends row
    const trendsRow = document.createElement('div');
    trendsRow.className = 'dashboard-section dashboard-row';
    trendsRow.appendChild(renderDeathTrends(data.trends));
    trendsRow.appendChild(renderInterestingStats(data.overview));
    container.appendChild(trendsRow);

    return container;
  }

  function renderDeathStatistics(overview) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    
    const subtitle = getDeathSubtitle(overview.total_deaths);
    
    card.innerHTML = `
      <h3>☠️ Death Statistics</h3>
      <div class="death-subtitle">${subtitle}</div>
      <div class="death-stats-grid">
        <div class="death-stat-item">
          <div class="death-stat-value">${overview.total_deaths.toLocaleString()}</div>
          <div class="death-stat-label">Total Deaths</div>
        </div>
        <div class="death-stat-item">
          <div class="death-stat-value">${overview.deaths_per_hour}</div>
          <div class="death-stat-label">Deaths/Hour</div>
        </div>
        <div class="death-stat-item">
          <div class="death-stat-value">${formatDuration(overview.avg_rez_time)}</div>
          <div class="death-stat-label">Avg Rez Time</div>
        </div>
        <div class="death-stat-item">
          <div class="death-stat-value">${overview.total_repair_cost.toFixed(0)}%</div>
          <div class="death-stat-label">Total Durability Lost</div>
        </div>
      </div>
    `;

    return card;
  }

  function renderArchNemesis(overview) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>🎯 Arch-Nemesis</h3>';

    if (!overview.arch_nemesis) {
      card.innerHTML += '<p class="muted">No deaths recorded</p>';
      return card;
    }

    const nemesis = overview.arch_nemesis;
    const zone = overview.most_dangerous_zone;

    card.innerHTML += `
      <div class="arch-nemesis-container">
        <div class="nemesis-main">
          <div class="nemesis-name">${nemesis.name}</div>
          <div class="nemesis-subtitle">Your Most Frequent Killer</div>
          <div class="nemesis-stats">
            <div class="nemesis-stat">
              <span class="nemesis-stat-value">${nemesis.kills}</span>
              <span class="nemesis-stat-label">kills</span>
            </div>
            <div class="nemesis-stat">
              <span class="nemesis-stat-value">${nemesis.avg_combat_duration.toFixed(1)}s</span>
              <span class="nemesis-stat-label">avg fight</span>
            </div>
            <div class="nemesis-stat">
              <span class="nemesis-stat-value">${nemesis.avg_durability_loss.toFixed(1)}%</span>
              <span class="nemesis-stat-label">avg dmg</span>
            </div>
          </div>
        </div>
        ${zone ? `
          <div class="danger-zone">
            <div class="danger-zone-label">Most Dangerous Zone</div>
            <div class="danger-zone-name">${zone.name}</div>
            <div class="danger-zone-deaths">${zone.deaths} deaths</div>
          </div>
        ` : ''}
      </div>
    `;

    return card;
  }

  function renderDeathTrends(trends) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>📈 Death Trends</h3>';

    if (!trends.deaths_over_time || trends.deaths_over_time.length === 0) {
      card.innerHTML += '<p class="muted">No trend data available</p>';
      return card;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'death-trend-chart';
    canvas.width = 600;
    canvas.height = 250;
    card.appendChild(canvas);

    setTimeout(() => renderDeathTrendChart(canvas, trends.deaths_over_time), 0);
    return card;
  }

  function renderDeathTrendChart(canvas, data) {
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    const padding = { top: 20, right: 20, bottom: 40, left: 50 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;

    // Clear canvas with light background
    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, width, height);

    if (data.length === 0) return;

    // Find max deaths
    const maxDeaths = Math.max(...data.map(d => d.count));
    
    // Draw axes
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(padding.left, padding.top);
    ctx.lineTo(padding.left, height - padding.bottom);
    ctx.lineTo(width - padding.right, height - padding.bottom);
    ctx.stroke();

    // Draw grid lines
    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 1;
    const gridLines = 5;
    for (let i = 0; i <= gridLines; i++) {
      const y = padding.top + (chartHeight / gridLines) * i;
      ctx.beginPath();
      ctx.moveTo(padding.left, y);
      ctx.lineTo(width - padding.right, y);
      ctx.stroke();
    }

    // Draw Y-axis labels
    ctx.fillStyle = '#64748b';
    ctx.font = '11px "Segoe UI", Arial, sans-serif';
    ctx.textAlign = 'right';
    for (let i = 0; i <= gridLines; i++) {
      const value = maxDeaths - (maxDeaths / gridLines) * i;
      const y = padding.top + (chartHeight / gridLines) * i;
      ctx.fillText(Math.round(value).toString(), padding.left - 10, y + 4);
    }

    // Draw line chart
    ctx.strokeStyle = '#d32f2f';
    ctx.lineWidth = 3;
    ctx.beginPath();

    data.forEach((point, idx) => {
      const x = padding.left + (chartWidth / (data.length - 1)) * idx;
      const y = height - padding.bottom - (point.count / maxDeaths) * chartHeight;
      
      if (idx === 0) {
        ctx.moveTo(x, y);
      } else {
        ctx.lineTo(x, y);
      }
    });
    ctx.stroke();

    // Draw points
    ctx.fillStyle = '#d32f2f';
    data.forEach((point, idx) => {
      const x = padding.left + (chartWidth / (data.length - 1)) * idx;
      const y = height - padding.bottom - (point.count / maxDeaths) * chartHeight;
      ctx.beginPath();
      ctx.arc(x, y, 4, 0, Math.PI * 2);
      ctx.fill();
      
      // White border around points
      ctx.strokeStyle = '#fff';
      ctx.lineWidth = 2;
      ctx.stroke();
    });

    // Draw X-axis labels (sample every N dates to avoid overlap)
    ctx.fillStyle = '#64748b';
    ctx.font = '10px "Segoe UI", Arial, sans-serif';
    ctx.textAlign = 'center';
    const labelEvery = Math.max(1, Math.floor(data.length / 8));
    data.forEach((point, idx) => {
      if (idx % labelEvery === 0) {
        const x = padding.left + (chartWidth / (data.length - 1)) * idx;
        const date = new Date(point.date);
        const label = `${date.getMonth() + 1}/${date.getDate()}`;
        ctx.fillText(label, x, height - padding.bottom + 15);
      }
    });
  }

  function renderInterestingStats(overview) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>🏆 Interesting Stats</h3>';

    card.innerHTML += `
      <div class="interesting-stats-grid">
        <div class="interesting-stat">
          <div class="interesting-stat-icon">⏱️</div>
          <div class="interesting-stat-value">${formatDuration(overview.longest_alive_streak)}</div>
          <div class="interesting-stat-label">Longest Time Without Dying</div>
        </div>
        <div class="interesting-stat">
          <div class="interesting-stat-icon">💀</div>
          <div class="interesting-stat-value">${overview.total_deaths}</div>
          <div class="interesting-stat-label">Total Visits to Spirit Healer</div>
        </div>
        <div class="interesting-stat">
          <div class="interesting-stat-icon">🔧</div>
          <div class="interesting-stat-value">${overview.total_repair_cost.toFixed(0)}%</div>
          <div class="interesting-stat-label">Cumulative Durability Lost</div>
          <div class="interesting-stat-note">"Spirit Healer's Best Customer"</div>
        </div>
      </div>
    `;

    return card;
  }

  // ===== TAB 2: DEATH LOG =====
  function renderDeathLogTab(data) {
    const container = document.createElement('div');
    container.id = 'mort-death-log';
    container.className = 'mortality-tab-content';

    container.appendChild(renderDeathLogTable(data.death_log));

    return container;
  }

  function renderDeathLogTable(deaths) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>📋 Recent Deaths</h3>';

    if (!deaths || deaths.length === 0) {
      card.innerHTML += '<p class="muted">No deaths recorded</p>';
      return card;
    }

    const table = document.createElement('table');
    table.className = 'death-log-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Date</th>
          <th>Killer</th>
          <th>Zone</th>
          <th>Instance</th>
          <th>Level</th>
          <th>Group</th>
          <th>Durability</th>
          <th>Rez Type</th>
        </tr>
      </thead>
    `;

    const tbody = document.createElement('tbody');
    deaths.forEach(death => {
      const row = document.createElement('tr');
      row.className = 'death-log-row';
      
      row.innerHTML = `
        <td>${formatDate(death.timestamp)}</td>
        <td class="death-killer">${death.killer || 'Unknown'}</td>
        <td>${death.zone || '-'}</td>
        <td>${death.instance || '-'}</td>
        <td>${death.level}</td>
        <td>${death.group_type}</td>
        <td>${death.durability_loss.toFixed(1)}%</td>
        <td>${getRezTypeLabel(death.rez_type)}</td>
      `;

      // Add click to expand for details
      row.addEventListener('click', () => {
        if (row.classList.contains('expanded')) {
          row.classList.remove('expanded');
          const detailRow = row.nextElementSibling;
          if (detailRow && detailRow.classList.contains('detail-row')) {
            detailRow.remove();
          }
        } else {
          row.classList.add('expanded');
          const detailRow = document.createElement('tr');
          detailRow.className = 'detail-row';
          detailRow.innerHTML = `
            <td colspan="8">
              <div class="death-details">
                <div><strong>Subzone:</strong> ${death.subzone || 'Unknown'}</div>
                <div><strong>Combat Duration:</strong> ${death.combat_duration}s</div>
                ${death.x && death.y ? `<div><strong>Location:</strong> (${death.x.toFixed(1)}, ${death.y.toFixed(1)})</div>` : ''}
              </div>
            </td>
          `;
          row.after(detailRow);
        }
      });

      tbody.appendChild(row);
    });

    table.appendChild(tbody);
    card.appendChild(table);

    return card;
  }

  // ===== TAB 3: BOSS DEATHS =====
  function renderBossDeathsTab(data) {
    const container = document.createElement('div');
    container.id = 'mort-boss-deaths';
    container.className = 'mortality-tab-content';

    const section = document.createElement('div');
    section.className = 'dashboard-section';

    if (data.boss_deaths.hardest_boss) {
      section.appendChild(renderHardestBoss(data.boss_deaths.hardest_boss));
    }
    
    section.appendChild(renderBossDeathTable(data.boss_deaths.by_boss));

    container.appendChild(section);

    return container;
  }

  function renderHardestBoss(boss) {
    const card = document.createElement('div');
    card.className = 'dash-card hardest-boss-card';
    card.innerHTML = `
      <h3>👹 Hardest Boss for You</h3>
      <div class="hardest-boss-content">
        <div class="hardest-boss-name">${boss.boss}</div>
        <div class="hardest-boss-stat">
          <span class="boss-stat-value">${boss.deaths_before_kill || boss.deaths}</span>
          <span class="boss-stat-label">deaths before first kill</span>
        </div>
        <div class="boss-timeline">
          <div class="timeline-item">
            <span class="timeline-label">First Death:</span>
            <span class="timeline-value">${boss.first_death || 'Unknown'}</span>
          </div>
          ${boss.first_kill ? `
            <div class="timeline-item">
              <span class="timeline-label">First Kill:</span>
              <span class="timeline-value">${boss.first_kill}</span>
            </div>
          ` : '<div class="timeline-item muted">Still undefeated!</div>'}
        </div>
      </div>
    `;
    return card;
  }

  function renderBossDeathTable(bosses) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>📊 Boss Death Analysis</h3>';

    if (!bosses || bosses.length === 0) {
      card.innerHTML += '<p class="muted">No boss deaths recorded</p>';
      return card;
    }

    const table = document.createElement('table');
    table.className = 'boss-death-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Boss</th>
          <th>Total Deaths</th>
          <th>Deaths Before Kill</th>
          <th>Avg Repair Cost</th>
          <th>First Death</th>
          <th>First Kill</th>
        </tr>
      </thead>
    `;

    const tbody = document.createElement('tbody');
    bosses.forEach(boss => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td class="boss-name">${boss.boss}</td>
        <td>${boss.deaths}</td>
        <td>${boss.deaths_before_kill !== null ? boss.deaths_before_kill : '-'}</td>
        <td>${boss.avg_repair_cost.toFixed(1)}%</td>
        <td>${boss.first_death || '-'}</td>
        <td>${boss.first_kill || '<span class="muted">Not yet!</span>'}</td>
      `;
      tbody.appendChild(row);
    });

    table.appendChild(tbody);
    card.appendChild(table);

    return card;
  }

  // ===== Main Mortality Renderer =====
  function renderMortality(data, charName) {
    const root = q('#tab-mortality');
    if (!root) return;
    
    root.innerHTML = '';

    // Header
    const header = document.createElement('div');
    header.className = 'mortality-header';
    header.innerHTML = `
      <h1>☠️ Mortality Analysis</h1>
      <p class="mortality-subtitle">Analyzing the many deaths of ${charName}</p>
    `;
    root.appendChild(header);

    // Tab navigation
    const tabNav = document.createElement('div');
    tabNav.className = 'mortality-tabs dashboard-tabs';
    tabNav.innerHTML = `
      <button class="mortality-tab dashboard-tab active" data-tab="overview">📊 Overview</button>
      <button class="mortality-tab dashboard-tab" data-tab="death-log">📋 Death Log</button>
      <button class="mortality-tab dashboard-tab" data-tab="boss-deaths">👹 Boss Deaths</button>
    `;
    root.appendChild(tabNav);

    // Tab contents
    const contentWrapper = document.createElement('div');
    contentWrapper.className = 'mortality-content-wrapper dashboard-content-wrapper';
    
    contentWrapper.appendChild(renderOverviewTab(data));
    contentWrapper.appendChild(renderDeathLogTab(data));
    contentWrapper.appendChild(renderBossDeathsTab(data));
    
    root.appendChild(contentWrapper);

    setupTabs(root);
  }

  // ===== Data Loading =====
  async function loadMortality() {
    const root = q('#tab-mortality');
    if (!root) {
      log('ERROR: #tab-mortality not found in DOM');
      return;
    }

    const cid = root.dataset?.characterId;
    const charName = root.dataset?.charName || '';

    log('Loading mortality for character:', cid, charName);

    if (!cid) {
      root.innerHTML = '<p class="muted">No character selected</p>';
      return;
    }

    root.innerHTML = '<div class="muted" style="text-align: center; padding: 40px 0;"><div style="font-size: 2rem; margin-bottom: 16px;">☠️</div><div>Loading mortality data...</div></div>';

    try {
      const url = `/sections/mortality-data.php?character_id=${encodeURIComponent(cid)}`;
      log('Fetching from:', url);
      
      const res = await fetch(url, {
        credentials: 'include'
      });
      
      log('Response status:', res.status);
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }

      const data = await res.json();
      log('Data loaded:', data);
      
      renderMortality(data, charName);
    } catch (err) {
      log('Failed to load mortality:', err);
      root.innerHTML = `<p style="color:#d32f2f;">Failed to load mortality data: ${err.message}</p>`;
    }
  }

  // ===== Event Listeners =====
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'mortality') {
      loadMortality();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || 'dashboard';
    if (currentSection === 'mortality') {
      loadMortality();
    }
  });

  // Initial load
  if (q('#tab-mortality')) {
    log('Found #tab-mortality on page load, loading now...');
    loadMortality();
  } else {
    log('No #tab-mortality found on initial load, waiting for event...');
  }

  log('Mortality module loaded and ready');
})();