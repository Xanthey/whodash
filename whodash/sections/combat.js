/* eslint-disable no-console */
/* WhoDASH Combat Analytics - Pure DPS Performance Tracking */
(() => {
  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[combat]', ...a);

  function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return Math.round(num).toLocaleString();
  }

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

  // ===== OVERVIEW CARD =====
  
  function renderOverviewStats(data) {
    const card = document.createElement('div');
    card.className = 'combat-overview-card';
    
    const overview = data.overview || {};
    
    card.innerHTML = `
      <h2 class="combat-title">⚔️ Combat Performance Overview</h2>
      <div class="combat-stats-grid">
        <div class="combat-stat-main">
          <div class="stat-label">Average DPS</div>
          <div class="stat-value-huge">${formatNumber(overview.avg_dps)}</div>
        </div>
        <div class="combat-stat">
          <div class="stat-label">Highest DPS</div>
          <div class="stat-value highlight">${formatNumber(overview.highest_dps)}</div>
          ${overview.highest_dps_target ? `<div class="stat-sublabel">${overview.highest_dps_target}</div>` : ''}
          ${overview.highest_dps_date ? `<div class="stat-sublabel muted">${overview.highest_dps_date}</div>` : ''}
        </div>
        <div class="combat-stat">
          <div class="stat-label">Total Damage</div>
          <div class="stat-value">${formatNumber(overview.total_damage)}</div>
        </div>
        <div class="combat-stat">
          <div class="stat-label">Total Encounters</div>
          <div class="stat-value">${formatNumber(overview.total_encounters)}</div>
        </div>
        <div class="combat-stat">
          <div class="stat-label">Combat Uptime</div>
          <div class="stat-value">${formatDuration(overview.combat_uptime_seconds)}</div>
        </div>
        <div class="combat-stat">
          <div class="stat-label">Avg Fight Length</div>
          <div class="stat-value">${formatDuration(overview.avg_encounter_duration)}</div>
        </div>
      </div>
    `;
    
    return card;
  }

  // ===== DPS TIMESERIES CHART =====
  
  function renderDPSTimeseries(timeseries) {
    const card = document.createElement('div');
    card.className = 'dps-chart-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '📈 DPS Trend (Last 30 Days)';
    card.appendChild(title);

    if (!timeseries || timeseries.length < 2) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'Not enough combat data for chart';
      card.appendChild(msg);
      return card;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'dps-canvas';
    canvas.width = 800;
    canvas.height = 250;
    card.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const padding = 50;
    const w = canvas.width - padding * 2;
    const h = canvas.height - padding * 2;

    const dpsValues = timeseries.map(d => d.dps);
    const minVal = Math.min(...dpsValues);
    const maxVal = Math.max(...dpsValues);
    const range = maxVal - minVal || 1;

    // Grid lines
    ctx.strokeStyle = '#e6eefb';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
      const y = padding + (i / 5) * h;
      ctx.beginPath();
      ctx.moveTo(padding, y);
      ctx.lineTo(padding + w, y);
      ctx.stroke();
    }

    // DPS line
    ctx.strokeStyle = '#ef4444';
    ctx.lineWidth = 3;
    ctx.beginPath();

    timeseries.forEach((d, i) => {
      const x = padding + (i / (timeseries.length - 1)) * w;
      const y = padding + h - ((d.dps - minVal) / range) * h;
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });

    ctx.stroke();

    // Fill gradient
    ctx.lineTo(padding + w, padding + h);
    ctx.lineTo(padding, padding + h);
    ctx.closePath();
    const gradient = ctx.createLinearGradient(0, padding, 0, padding + h);
    gradient.addColorStop(0, 'rgba(239, 68, 68, 0.3)');
    gradient.addColorStop(1, 'rgba(239, 68, 68, 0.0)');
    ctx.fillStyle = gradient;
    ctx.fill();

    // Y-axis labels
    ctx.fillStyle = '#6e7f9b';
    ctx.font = '12px system-ui';
    ctx.textAlign = 'right';
    for (let i = 0; i <= 5; i++) {
      const val = Math.round(minVal + (range * i / 5));
      const y = padding + h - (i / 5) * h;
      ctx.fillText(formatNumber(val), padding - 10, y + 4);
    }

    return card;
  }

  // ===== BURST VS SUSTAINED ANALYSIS =====
  
  function renderBurstAnalysis(burstData) {
    const card = document.createElement('div');
    card.className = 'burst-analysis-card';
    
    card.innerHTML = `
      <h3>⚡ Burst vs Sustained DPS</h3>
      <div class="burst-comparison">
        <div class="burst-item">
          <div class="burst-label">💥 Burst DPS</div>
          <div class="burst-desc">Short fights (&lt;30s)</div>
          <div class="burst-value highlight">${formatNumber(burstData.burst_dps)}</div>
          <div class="burst-count">${burstData.burst_count} encounters</div>
        </div>
        <div class="burst-divider">vs</div>
        <div class="burst-item">
          <div class="burst-label">🎯 Sustained DPS</div>
          <div class="burst-desc">Long fights (&gt;2min)</div>
          <div class="burst-value">${formatNumber(burstData.sustained_dps)}</div>
          <div class="burst-count">${burstData.sustained_count} encounters</div>
        </div>
      </div>
      <div class="burst-insight">
        ${burstData.burst_dps > burstData.sustained_dps * 1.2 
          ? '💡 Your burst damage significantly exceeds sustained DPS - excellent for short encounters!'
          : burstData.sustained_dps > burstData.burst_dps * 1.1
          ? '💡 Your sustained DPS exceeds burst - great for long boss fights!'
          : '💡 Your burst and sustained DPS are well-balanced!'}
      </div>
    `;
    
    return card;
  }

  // ===== TARGET TYPE ANALYSIS =====
  
  function renderTargetAnalysis(targetData) {
    const card = document.createElement('div');
    card.className = 'target-analysis-card';
    
    card.innerHTML = `
      <h3>🎯 Boss vs Adds Performance</h3>
      <div class="target-comparison">
        <div class="target-item">
          <div class="target-label">👑 Boss DPS</div>
          <div class="target-value boss">${formatNumber(targetData.boss_dps)}</div>
          <div class="target-detail">Max: ${formatNumber(targetData.max_boss_dps)}</div>
          <div class="target-count">${targetData.boss_count} boss encounters</div>
        </div>
        <div class="target-divider"></div>
        <div class="target-item">
          <div class="target-label">⚔️ Adds DPS</div>
          <div class="target-value adds">${formatNumber(targetData.adds_dps)}</div>
          <div class="target-detail">Max: ${formatNumber(targetData.max_adds_dps)}</div>
          <div class="target-count">${targetData.adds_count} add encounters</div>
        </div>
      </div>
      <div class="target-insight">
        ${targetData.boss_dps > targetData.adds_dps * 1.1
          ? '💡 You excel at single-target (boss) damage!'
          : targetData.adds_dps > targetData.boss_dps * 1.1
          ? '💡 You excel at multi-target (adds) damage!'
          : '💡 Balanced performance on both bosses and adds!'}
      </div>
    `;
    
    return card;
  }

  // ===== CONSISTENCY METRICS =====
  
  function renderConsistencyMetrics(consistency) {
    const card = document.createElement('div');
    card.className = 'consistency-card';
    
    const rating = consistency.consistency_rating;
    const ratingClass = rating === 'Excellent' ? 'excellent' :
                       rating === 'Good' ? 'good' :
                       rating === 'Average' ? 'average' : 'poor';
    
    card.innerHTML = `
      <h3>📊 DPS Consistency Analysis</h3>
      <div class="consistency-grid">
        <div class="consistency-item">
          <div class="consistency-label">Mean DPS</div>
          <div class="consistency-value">${formatNumber(consistency.mean_dps)}</div>
        </div>
        <div class="consistency-item">
          <div class="consistency-label">Standard Deviation</div>
          <div class="consistency-value">±${formatNumber(consistency.std_deviation)}</div>
        </div>
        <div class="consistency-item">
          <div class="consistency-label">Variability</div>
          <div class="consistency-value">${consistency.coefficient_of_variation}%</div>
        </div>
        <div class="consistency-item">
          <div class="consistency-label">Consistency Rating</div>
          <div class="consistency-rating ${ratingClass}">${rating}</div>
        </div>
      </div>
      <div class="consistency-insight">
        ${rating === 'Excellent' 
          ? '💡 Outstanding! Your DPS is very consistent across encounters.'
          : rating === 'Good'
          ? '💡 Good consistency! Minor variations are normal.'
          : rating === 'Average'
          ? '💡 Moderate consistency. Consider reviewing lower-performing encounters.'
          : '💡 High variability detected. Review your rotation and encounter mechanics.'}
      </div>
    `;
    
    return card;
  }

  // ===== COMBAT BREAKDOWN PIE =====
  
  function renderCombatBreakdown(breakdown) {
    const card = document.createElement('div');
    card.className = 'combat-breakdown-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '🥧 Combat Time Distribution';
    card.appendChild(title);

    const total = (breakdown.solo || 0) + (breakdown.party || 0) + (breakdown.raid || 0);
    
    if (total === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No combat data available';
      card.appendChild(msg);
      return card;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 300;
    canvas.height = 300;
    card.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = 100;

    const data = [
      { label: 'Solo', value: breakdown.solo || 0, color: '#ef4444' },
      { label: 'Party', value: breakdown.party || 0, color: '#f97316' },
      { label: 'Raid', value: breakdown.raid || 0, color: '#eab308' },
    ].filter(d => d.value > 0);

    let currentAngle = -Math.PI / 2;

    data.forEach(segment => {
      const sliceAngle = (segment.value / total) * 2 * Math.PI;
      
      ctx.beginPath();
      ctx.moveTo(centerX, centerY);
      ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
      ctx.closePath();
      ctx.fillStyle = segment.color;
      ctx.fill();
      
      currentAngle += sliceAngle;
    });

    // Legend
    const legend = document.createElement('div');
    legend.className = 'pie-legend';
    
    data.forEach(segment => {
      const percent = ((segment.value / total) * 100).toFixed(1);
      const item = document.createElement('div');
      item.className = 'legend-item';
      item.innerHTML = `
        <span class="legend-color" style="background: ${segment.color}"></span>
        <span class="legend-label">${segment.label}: ${percent}%</span>
        <span class="legend-value">${formatDuration(segment.value)}</span>
      `;
      legend.appendChild(item);
    });
    
    card.appendChild(legend);

    return card;
  }

  // ===== INSTANCE PERFORMANCE BAR CHART =====
  
  function renderInstancePerformance(instances) {
    const card = document.createElement('div');
    card.className = 'instance-performance-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '🏰 Performance by Instance';
    card.appendChild(title);

    if (!instances || instances.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No instance data available';
      card.appendChild(msg);
      return card;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 800;
    canvas.height = 400;
    card.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const padding = 50;
    const chartHeight = canvas.height - padding * 2;
    const barWidth = (canvas.width - padding * 2) / instances.length;
    
    const maxDps = Math.max(...instances.map(i => i.avg_dps));

    instances.forEach((inst, idx) => {
      const barHeight = (inst.avg_dps / maxDps) * chartHeight;
      const x = padding + (idx * barWidth);
      const y = canvas.height - padding - barHeight;

      // Bar
      ctx.fillStyle = '#ef4444';
      ctx.fillRect(x + 5, y, barWidth - 10, barHeight);

      // Label
      ctx.fillStyle = '#6e7f9b';
      ctx.font = '11px system-ui';
      ctx.save();
      ctx.translate(x + barWidth / 2, canvas.height - padding + 10);
      ctx.rotate(-Math.PI / 4);
      ctx.textAlign = 'right';
      const shortName = inst.instance.length > 15 
        ? inst.instance.substring(0, 12) + '...' 
        : inst.instance;
      ctx.fillText(shortName, 0, 0);
      ctx.restore();

      // DPS value on top
      ctx.fillStyle = '#374151';
      ctx.font = 'bold 12px system-ui';
      ctx.textAlign = 'center';
      ctx.fillText(formatNumber(inst.avg_dps), x + barWidth / 2, y - 5);
    });

    return card;
  }

  // ===== BOSS ENCOUNTERS TABLE =====
  
  function renderBossEncountersTable(encounters) {
    const card = document.createElement('div');
    card.className = 'combat-table-card';
    
    card.innerHTML = `
      <h3>👹 Combat Encounters</h3>
      <div class="combat-table-controls">
        <input type="text" id="combatSearch" placeholder="Search encounter or instance..." class="search-input">
        <div class="filter-buttons">
          <button class="filter-btn active" data-filter="all">All</button>
          <button class="filter-btn" data-filter="high">High DPS</button>
          <button class="filter-btn" data-filter="recent">Recent (7d)</button>
          <button class="filter-btn" data-filter="boss">Boss Only</button>
        </div>
      </div>
      <div class="table-wrapper">
        <table class="combat-encounters-table">
          <thead>
            <tr>
              <th data-sort="date">Date <span class="sort-arrow">↕</span></th>
              <th data-sort="target">Target <span class="sort-arrow">↕</span></th>
              <th data-sort="instance">Instance <span class="sort-arrow">↕</span></th>
              <th data-sort="dps">DPS <span class="sort-arrow">↕</span></th>
              <th data-sort="duration">Duration <span class="sort-arrow">↕</span></th>
              <th data-sort="damage">Total Damage <span class="sort-arrow">↕</span></th>
              <th data-sort="group">Group <span class="sort-arrow">↕</span></th>
            </tr>
          </thead>
          <tbody id="combatTableBody">
          </tbody>
        </table>
      </div>
      <div class="table-pagination">
        <button id="combatPrevPage" class="page-btn" disabled>← Previous</button>
        <span id="combatPageInfo">Page 1 of 1</span>
        <button id="combatNextPage" class="page-btn" disabled>Next →</button>
      </div>
    `;

    return card;
  }

  function initBossTable(encounters) {
    if (!encounters || encounters.length === 0) return;

    let currentSort = { column: 'date', direction: 'desc' };
    let currentFilter = 'all';
    let currentPage = 1;
    const rowsPerPage = 20;
    let filteredData = [...encounters];

    const avgDps = encounters.reduce((sum, e) => sum + e.dps, 0) / encounters.length;
    const sevenDaysAgo = Math.floor(Date.now() / 1000) - (7 * 24 * 60 * 60);

    function applyFilters() {
      const searchTerm = q('#combatSearch')?.value.toLowerCase() || '';
      
      filteredData = encounters.filter(e => {
        const matchesSearch = !searchTerm || 
          e.target.toLowerCase().includes(searchTerm) ||
          (e.instance && e.instance.toLowerCase().includes(searchTerm));
        
        const matchesFilter = 
          currentFilter === 'all' ||
          (currentFilter === 'high' && e.dps >= avgDps) ||
          (currentFilter === 'recent' && e.ts >= sevenDaysAgo) ||
          (currentFilter === 'boss' && e.is_boss);
        
        return matchesSearch && matchesFilter;
      });
      
      currentPage = 1;
      renderTable();
    }

    function renderTable() {
      const tbody = q('#combatTableBody');
      if (!tbody) return;

      const sorted = [...filteredData].sort((a, b) => {
        let aVal = a[currentSort.column];
        let bVal = b[currentSort.column];

        if (currentSort.column === 'date') {
          aVal = a.ts;
          bVal = b.ts;
        } else if (currentSort.column === 'damage') {
          aVal = a.total_damage;
          bVal = b.total_damage;
        }

        if (typeof aVal === 'number' && typeof bVal === 'number') {
          return currentSort.direction === 'asc' ? aVal - bVal : bVal - aVal;
        }

        const aStr = String(aVal || '').toLowerCase();
        const bStr = String(bVal || '').toLowerCase();
        if (currentSort.direction === 'asc') {
          return aStr < bStr ? -1 : aStr > bStr ? 1 : 0;
        } else {
          return bStr < aStr ? -1 : bStr > aStr ? 1 : 0;
        }
      });

      const totalPages = Math.ceil(sorted.length / rowsPerPage);
      const start = (currentPage - 1) * rowsPerPage;
      const end = start + rowsPerPage;
      const pageData = sorted.slice(start, end);

      tbody.innerHTML = '';
      pageData.forEach(row => {
        const tr = document.createElement('tr');
        
        const dpsClass = row.dps >= avgDps ? 'high-dps' : '';
        
        tr.innerHTML = `
          <td>${row.date}</td>
          <td class="target-name">${row.target}${row.is_boss ? ' 👑' : ''}</td>
          <td>${row.instance || '—'}</td>
          <td class="dps-value ${dpsClass}">${formatNumber(row.dps)}</td>
          <td>${formatDuration(row.duration)}</td>
          <td>${formatNumber(row.total_damage)}</td>
          <td>${row.group_type} (${row.group_size})</td>
        `;
        tbody.appendChild(tr);
      });

      q('#combatPageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
      q('#combatPrevPage').disabled = currentPage === 1;
      q('#combatNextPage').disabled = currentPage === totalPages || totalPages === 0;

      document.querySelectorAll('.combat-encounters-table th').forEach(th => {
        const arrow = th.querySelector('.sort-arrow');
        if (arrow) {
          if (th.dataset.sort === currentSort.column) {
            arrow.textContent = currentSort.direction === 'asc' ? '↑' : '↓';
            th.classList.add('sorted');
          } else {
            arrow.textContent = '↕';
            th.classList.remove('sorted');
          }
        }
      });
    }

    // Event listeners
    const searchInput = q('#combatSearch');
    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }

    qa('.filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        qa('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        applyFilters();
      });
    });

    document.querySelectorAll('.combat-encounters-table th[data-sort]').forEach(th => {
      th.addEventListener('click', () => {
        const column = th.dataset.sort;
        if (currentSort.column === column) {
          currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
          currentSort.column = column;
          currentSort.direction = 'desc';
        }
        renderTable();
      });
    });

    const prevBtn = q('#combatPrevPage');
    const nextBtn = q('#combatNextPage');
    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        if (currentPage > 1) {
          currentPage--;
          renderTable();
        }
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        const totalPages = Math.ceil(filteredData.length / rowsPerPage);
        if (currentPage < totalPages) {
          currentPage++;
          renderTable();
        }
      });
    }

    renderTable();
  }

  // ===== DPS DISTRIBUTION HISTOGRAM =====
  
  function renderDPSDistribution(distribution, percentiles) {
    const card = document.createElement('div');
    card.className = 'dps-distribution-card';
    
    card.innerHTML = `
      <h3>📊 DPS Distribution</h3>
    `;

    if (!distribution || distribution.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No distribution data available';
      card.appendChild(msg);
      return card;
    }

    const canvas = document.createElement('canvas');
    canvas.width = 700;
    canvas.height = 300;
    card.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const padding = 50;
    const chartHeight = canvas.height - padding * 2;
    const barWidth = (canvas.width - padding * 2) / distribution.length;
    
    const maxCount = Math.max(...distribution.map(d => d.count));

    distribution.forEach((bucket, idx) => {
      const barHeight = (bucket.count / maxCount) * chartHeight;
      const x = padding + (idx * barWidth);
      const y = canvas.height - padding - barHeight;

      ctx.fillStyle = '#ef4444';
      ctx.fillRect(x + 2, y, barWidth - 4, barHeight);

      ctx.fillStyle = '#6e7f9b';
      ctx.font = '10px system-ui';
      ctx.save();
      ctx.translate(x + barWidth / 2, canvas.height - padding + 10);
      ctx.rotate(-Math.PI / 4);
      ctx.textAlign = 'right';
      ctx.fillText(bucket.range, 0, 0);
      ctx.restore();
    });

    if (percentiles) {
      const info = document.createElement('div');
      info.className = 'percentiles-info';
      info.innerHTML = `
        <div class="percentile-item">
          <span class="percentile-label">50th percentile:</span>
          <span class="percentile-value">${formatNumber(percentiles.p50)} DPS</span>
        </div>
        <div class="percentile-item">
          <span class="percentile-label">75th percentile:</span>
          <span class="percentile-value">${formatNumber(percentiles.p75)} DPS</span>
        </div>
        <div class="percentile-item">
          <span class="percentile-label">90th percentile:</span>
          <span class="percentile-value">${formatNumber(percentiles.p90)} DPS</span>
        </div>
      `;
      card.appendChild(info);
    }

    return card;
  }

  // ===== MAIN PAGE RENDERER =====
  
  function renderCombatPage(data) {
    const root = q('#tab-combat');
    if (!root) return;

    root.innerHTML = '';

    const tabsContainer = document.createElement('div');
    tabsContainer.className = 'combat-tabs-container';
    tabsContainer.innerHTML = `
      <div class="combat-tabs">
        <button class="combat-tab active" data-tab="overview">📊 Overview</button>
        <button class="combat-tab" data-tab="encounters">👹 Encounters</button>
        <button class="combat-tab" data-tab="analysis">⚡ DPS Analysis</button>
      </div>
      <div class="combat-tab-content">
        <div class="combat-tab-pane active" id="combat-tab-overview"></div>
        <div class="combat-tab-pane" id="combat-tab-encounters"></div>
        <div class="combat-tab-pane" id="combat-tab-analysis"></div>
      </div>
    `;
    root.appendChild(tabsContainer);

    qa('.combat-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        const targetTab = btn.dataset.tab;
        qa('.combat-tab').forEach(b => b.classList.remove('active'));
        qa('.combat-tab-pane').forEach(pane => pane.classList.remove('active'));
        btn.classList.add('active');
        q(`#combat-tab-${targetTab}`).classList.add('active');
      });
    });

    // OVERVIEW TAB
    const overviewPane = q('#combat-tab-overview');
    overviewPane.appendChild(renderOverviewStats(data));
    
    const trendsSection = document.createElement('div');
    trendsSection.className = 'combat-section';
    const trendsTitle = document.createElement('h2');
    trendsTitle.className = 'section-title';
    trendsTitle.textContent = 'Performance Trends';
    trendsSection.appendChild(trendsTitle);
    
    trendsSection.appendChild(renderDPSTimeseries(data.dps_timeseries));
    trendsSection.appendChild(renderCombatBreakdown(data.combat_breakdown));
    trendsSection.appendChild(renderInstancePerformance(data.performance_by_instance));
    overviewPane.appendChild(trendsSection);

    // ENCOUNTERS TAB
    const encountersPane = q('#combat-tab-encounters');
    const encountersSection = document.createElement('div');
    encountersSection.className = 'combat-section';
    encountersSection.appendChild(renderBossEncountersTable(data.boss_encounters));
    encountersPane.appendChild(encountersSection);
    initBossTable(data.boss_encounters);

    const distSection = document.createElement('div');
    distSection.className = 'combat-section';
    distSection.appendChild(renderDPSDistribution(data.dps_distribution, data.dps_percentiles));
    encountersPane.appendChild(distSection);

    // DPS ANALYSIS TAB
    const analysisPane = q('#combat-tab-analysis');
    const analysisSection = document.createElement('div');
    analysisSection.className = 'combat-section';
    
    analysisSection.appendChild(renderBurstAnalysis(data.burst_analysis));
    analysisSection.appendChild(renderTargetAnalysis(data.target_analysis));
    analysisSection.appendChild(renderConsistencyMetrics(data.consistency_metrics));
    analysisPane.appendChild(analysisSection);
  }

  // ===== Data Loading =====
  async function loadCombatPage() {
    const root = q('#tab-combat');
    if (!root) {
      log('ERROR: #tab-combat not found in DOM');
      return;
    }

    const cid = root.dataset?.characterId;
    log('Loading combat data for character:', cid);

    if (!cid) {
      root.innerHTML = '<p class="muted">No character selected</p>';
      return;
    }

    root.innerHTML = '<div class="muted" style="text-align: center; padding: 40px 0;"><div style="font-size: 2rem; margin-bottom: 16px;">⚔️</div><div>Loading combat analytics...</div></div>';

    try {
      const url = `/sections/combat-data.php?character_id=${encodeURIComponent(cid)}`;
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
      
      renderCombatPage(data);
    } catch (err) {
      log('Failed to load combat data:', err);
      root.innerHTML = `<p style="color:#d32f2f;">Failed to load combat data: ${err.message}</p>`;
    }
  }

  // ===== Event Listeners =====
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'combat') {
      loadCombatPage();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || '';
    if (currentSection === 'combat') {
      loadCombatPage();
    }
  });

  if (q('#tab-combat')) {
    log('Found #tab-combat on page load, loading now...');
    loadCombatPage();
  } else {
    log('No #tab-combat found on initial load, waiting for event...');
  }

  log('Combat Analytics module loaded');
})();