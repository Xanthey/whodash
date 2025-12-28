/* eslint-disable no-console */
/* WhoDASH Healing Analytics - Complete Healer Performance Tracking */
(() => {
  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[healing]', ...a);

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

  function formatTimeAgo(ts) {
    const seconds = Math.floor(Date.now() / 1000) - ts;
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
    return new Date(ts * 1000).toLocaleDateString();
  }

  // ===== OVERVIEW CARD =====
  
  function renderOverviewStats(data) {
    const card = document.createElement('div');
    card.className = 'healing-overview-card';
    
    const overview = data.overview || {};
    
    card.innerHTML = `
      <h2 class="healing-title">💚 Healing Performance Overview</h2>
      <div class="healing-stats-grid">
        <div class="healing-stat-main">
          <div class="stat-label">Average HPS</div>
          <div class="stat-value-huge">${formatNumber(overview.avg_hps)}</div>
        </div>
        <div class="healing-stat">
          <div class="stat-label">Highest HPS</div>
          <div class="stat-value highlight">${formatNumber(overview.highest_hps)}</div>
          ${overview.highest_hps_target ? `<div class="stat-sublabel">${overview.highest_hps_target}</div>` : ''}
          ${overview.highest_hps_date ? `<div class="stat-sublabel muted">${overview.highest_hps_date}</div>` : ''}
        </div>
        <div class="healing-stat">
          <div class="stat-label">Total Healing Done</div>
          <div class="stat-value">${formatNumber(overview.total_healing_done)}</div>
        </div>
        <div class="healing-stat">
          <div class="stat-label">Avg Overheal %</div>
          <div class="stat-value ${overview.avg_overheal_pct > 30 ? 'negative' : overview.avg_overheal_pct < 15 ? 'positive' : ''}">${overview.avg_overheal_pct}%</div>
        </div>
        <div class="healing-stat">
          <div class="stat-label">Effective Healing</div>
          <div class="stat-value positive">${overview.effective_healing_pct}%</div>
        </div>
        <div class="healing-stat">
          <div class="stat-label">Healing Uptime</div>
          <div class="stat-value">${formatDuration(overview.healing_uptime_seconds)}</div>
        </div>
      </div>
    `;
    
    return card;
  }

  // ===== HPS OVER TIME CHART =====
  
  function renderHPSTimeseries(timeseries) {
    const card = document.createElement('div');
    card.className = 'hps-chart-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '📈 HPS Trend (Last 30 Days)';
    card.appendChild(title);

    if (!timeseries || timeseries.length < 2) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'Not enough healing data for chart';
      card.appendChild(msg);
      return card;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'hps-canvas';
    canvas.width = 800;
    canvas.height = 250;
    card.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const padding = 50;
    const w = canvas.width - padding * 2;
    const h = canvas.height - padding * 2;

    const hpsValues = timeseries.map(d => d.hps);
    const minVal = Math.min(...hpsValues);
    const maxVal = Math.max(...hpsValues);
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

    // HPS line
    ctx.strokeStyle = '#10b981';
    ctx.lineWidth = 3;
    ctx.beginPath();

    timeseries.forEach((d, i) => {
      const x = padding + (i / (timeseries.length - 1)) * w;
      const y = padding + h - ((d.hps - minVal) / range) * h;
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });

    ctx.stroke();

    // Fill gradient
    ctx.lineTo(padding + w, padding + h);
    ctx.lineTo(padding, padding + h);
    ctx.closePath();
    const gradient = ctx.createLinearGradient(0, padding, 0, padding + h);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');
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

  // ===== HEALING BREAKDOWN PIE CHART =====
  
  function renderHealingBreakdown(breakdown) {
    const card = document.createElement('div');
    card.className = 'healing-breakdown-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '🥧 Healing Time Distribution';
    card.appendChild(title);

    const total = (breakdown.solo || 0) + (breakdown.party || 0) + (breakdown.raid || 0);
    
    if (total === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No healing data available';
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
      { label: 'Solo', value: breakdown.solo || 0, color: '#3b82f6' },
      { label: 'Party', value: breakdown.party || 0, color: '#10b981' },
      { label: 'Raid', value: breakdown.raid || 0, color: '#f59e0b' },
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

  // ===== PERFORMANCE BY INSTANCE BAR CHART =====
  
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
    
    const maxHps = Math.max(...instances.map(i => i.avg_hps));

    instances.forEach((inst, idx) => {
      const barHeight = (inst.avg_hps / maxHps) * chartHeight;
      const x = padding + (idx * barWidth);
      const y = canvas.height - padding - barHeight;

      // Bar
      ctx.fillStyle = '#10b981';
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

      // HPS value on top
      ctx.fillStyle = '#374151';
      ctx.font = 'bold 12px system-ui';
      ctx.textAlign = 'center';
      ctx.fillText(formatNumber(inst.avg_hps), x + barWidth / 2, y - 5);
    });

    return card;
  }

  // ===== HEALING ENCOUNTERS TABLE =====
  
  function renderHealingEncountersTable(encounters) {
    const card = document.createElement('div');
    card.className = 'healing-table-card';
    
    card.innerHTML = `
      <h3>💉 Healing Encounters</h3>
      <div class="healing-table-controls">
        <input type="text" id="healingSearch" placeholder="Search encounter or instance..." class="search-input">
        <div class="filter-buttons">
          <button class="filter-btn active" data-filter="all">All</button>
          <button class="filter-btn" data-filter="high">High HPS</button>
          <button class="filter-btn" data-filter="efficient">Low Overheal</button>
          <button class="filter-btn" data-filter="boss">Boss Only</button>
        </div>
      </div>
      <div class="table-wrapper">
        <table class="healing-encounters-table">
          <thead>
            <tr>
              <th data-sort="date">Date <span class="sort-arrow">↕</span></th>
              <th data-sort="target">Target <span class="sort-arrow">↕</span></th>
              <th data-sort="instance">Instance <span class="sort-arrow">↕</span></th>
              <th data-sort="hps">HPS <span class="sort-arrow">↕</span></th>
              <th data-sort="overheal_pct">Overheal % <span class="sort-arrow">↕</span></th>
              <th data-sort="duration">Duration <span class="sort-arrow">↕</span></th>
              <th data-sort="group">Group <span class="sort-arrow">↕</span></th>
            </tr>
          </thead>
          <tbody id="healingTableBody">
          </tbody>
        </table>
      </div>
      <div class="table-pagination">
        <button id="healingPrevPage" class="page-btn" disabled>← Previous</button>
        <span id="healingPageInfo">Page 1 of 1</span>
        <button id="healingNextPage" class="page-btn" disabled>Next →</button>
      </div>
    `;

    return card;
  }

  function initHealingTable(encounters) {
    if (!encounters || encounters.length === 0) return;

    let currentSort = { column: 'date', direction: 'desc' };
    let currentFilter = 'all';
    let currentPage = 1;
    const rowsPerPage = 20;
    let filteredData = [...encounters];

    // Calculate average HPS for "high" filter
    const avgHps = encounters.reduce((sum, e) => sum + e.hps, 0) / encounters.length;

    function applyFilters() {
      const searchTerm = q('#healingSearch')?.value.toLowerCase() || '';
      
      filteredData = encounters.filter(e => {
        const matchesSearch = !searchTerm || 
          e.target.toLowerCase().includes(searchTerm) ||
          (e.instance && e.instance.toLowerCase().includes(searchTerm));
        
        const matchesFilter = 
          currentFilter === 'all' ||
          (currentFilter === 'high' && e.hps >= avgHps) ||
          (currentFilter === 'efficient' && e.overheal_pct < 20) ||
          (currentFilter === 'boss' && e.is_boss);
        
        return matchesSearch && matchesFilter;
      });
      
      currentPage = 1;
      renderTable();
    }

    function renderTable() {
      const tbody = q('#healingTableBody');
      if (!tbody) return;

      // Sort
      const sorted = [...filteredData].sort((a, b) => {
        let aVal = a[currentSort.column];
        let bVal = b[currentSort.column];

        if (currentSort.column === 'date') {
          aVal = a.ts;
          bVal = b.ts;
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

      // Paginate
      const totalPages = Math.ceil(sorted.length / rowsPerPage);
      const start = (currentPage - 1) * rowsPerPage;
      const end = start + rowsPerPage;
      const pageData = sorted.slice(start, end);

      // Render rows
      tbody.innerHTML = '';
      pageData.forEach(row => {
        const tr = document.createElement('tr');
        
        const hpsClass = row.hps >= avgHps ? 'high-hps' : '';
        const overhealClass = row.overheal_pct < 15 ? 'low-overheal' : row.overheal_pct > 30 ? 'high-overheal' : '';
        
        tr.innerHTML = `
          <td>${row.date}</td>
          <td class="target-name">${row.target}${row.is_boss ? ' 👑' : ''}</td>
          <td>${row.instance || '—'}</td>
          <td class="hps-value ${hpsClass}">${formatNumber(row.hps)}</td>
          <td class="overheal-value ${overhealClass}">${row.overheal_pct}%</td>
          <td>${formatDuration(row.duration)}</td>
          <td>${row.group_type} (${row.group_size})</td>
        `;
        tbody.appendChild(tr);
      });

      // Update pagination
      q('#healingPageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
      q('#healingPrevPage').disabled = currentPage === 1;
      q('#healingNextPage').disabled = currentPage === totalPages || totalPages === 0;

      // Update sort arrows
      document.querySelectorAll('.healing-encounters-table th').forEach(th => {
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
    const searchInput = q('#healingSearch');
    if (searchInput) {
      searchInput.addEventListener('input', applyFilters);
    }

    // Filter buttons
    qa('.filter-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        qa('.filter-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        currentFilter = btn.dataset.filter;
        applyFilters();
      });
    });

    // Sort
    document.querySelectorAll('.healing-encounters-table th[data-sort]').forEach(th => {
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

    // Pagination
    const prevBtn = q('#healingPrevPage');
    const nextBtn = q('#healingNextPage');
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

  // ===== HPS DISTRIBUTION HISTOGRAM =====
  
  function renderHPSDistribution(distribution, percentiles) {
    const card = document.createElement('div');
    card.className = 'hps-distribution-card';
    
    card.innerHTML = `
      <h3>📊 HPS Distribution</h3>
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

      // Bar
      ctx.fillStyle = '#10b981';
      ctx.fillRect(x + 2, y, barWidth - 4, barHeight);

      // Label
      ctx.fillStyle = '#6e7f9b';
      ctx.font = '10px system-ui';
      ctx.save();
      ctx.translate(x + barWidth / 2, canvas.height - padding + 10);
      ctx.rotate(-Math.PI / 4);
      ctx.textAlign = 'right';
      ctx.fillText(bucket.range, 0, 0);
      ctx.restore();
    });

    // Percentiles info
    if (percentiles) {
      const info = document.createElement('div');
      info.className = 'percentiles-info';
      info.innerHTML = `
        <div class="percentile-item">
          <span class="percentile-label">50th percentile:</span>
          <span class="percentile-value">${formatNumber(percentiles.p50)} HPS</span>
        </div>
        <div class="percentile-item">
          <span class="percentile-label">75th percentile:</span>
          <span class="percentile-value">${formatNumber(percentiles.p75)} HPS</span>
        </div>
        <div class="percentile-item">
          <span class="percentile-label">90th percentile:</span>
          <span class="percentile-value">${formatNumber(percentiles.p90)} HPS</span>
        </div>
      `;
      card.appendChild(info);
    }

    return card;
  }

  // ===== OVERHEAL ANALYSIS =====
  
  function renderOverhealAnalysis(overhealData) {
    const card = document.createElement('div');
    card.className = 'overheal-analysis-card';
    
    card.innerHTML = `
      <h3>🎯 Overheal Analysis</h3>
    `;

    // By encounter type
    if (overhealData.by_encounter_type && overhealData.by_encounter_type.length > 0) {
      const typeSection = document.createElement('div');
      typeSection.className = 'overheal-by-type';
      typeSection.innerHTML = '<h4>Average Overheal by Group Type</h4>';
      
      const typeList = document.createElement('div');
      typeList.className = 'type-list';
      
      overhealData.by_encounter_type.forEach(item => {
        const typeItem = document.createElement('div');
        typeItem.className = 'type-item';
        const overhealClass = item.avg_overheal_pct < 15 ? 'low-overheal' : item.avg_overheal_pct > 30 ? 'high-overheal' : '';
        typeItem.innerHTML = `
          <span class="type-label">${item.type}:</span>
          <span class="type-value ${overhealClass}">${item.avg_overheal_pct}%</span>
          <span class="type-count">(${item.encounter_count} encounters)</span>
        `;
        typeList.appendChild(typeItem);
      });
      
      typeSection.appendChild(typeList);
      card.appendChild(typeSection);
    }

    // Improvement opportunities
    if (overhealData.improvement_opportunities && overhealData.improvement_opportunities.length > 0) {
      const oppSection = document.createElement('div');
      oppSection.className = 'improvement-opportunities';
      oppSection.innerHTML = '<h4>Improvement Opportunities (High Overheal)</h4>';
      
      const oppTable = document.createElement('table');
      oppTable.className = 'opportunities-table';
      oppTable.innerHTML = `
        <thead>
          <tr>
            <th>Target</th>
            <th>Instance</th>
            <th>Overheal %</th>
            <th>Date</th>
          </tr>
        </thead>
      `;
      
      const tbody = document.createElement('tbody');
      overhealData.improvement_opportunities.forEach(opp => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${opp.target}</td>
          <td>${opp.instance || '—'}</td>
          <td class="high-overheal">${opp.overheal_pct}%</td>
          <td>${opp.date}</td>
        `;
        tbody.appendChild(tr);
      });
      
      oppTable.appendChild(tbody);
      oppSection.appendChild(oppTable);
      card.appendChild(oppSection);
    }

    return card;
  }

  // ===== EFFICIENCY METRICS =====
  
  function renderEfficiencyMetrics(efficiency) {
    const card = document.createElement('div');
    card.className = 'efficiency-metrics-card';
    
    card.innerHTML = `
      <h3>⚡ Healing Efficiency</h3>
    `;

    const container = document.createElement('div');
    container.className = 'efficiency-container';

    // Best efficiency
    if (efficiency.best_efficiency && efficiency.best_efficiency.length > 0) {
      const bestSection = document.createElement('div');
      bestSection.className = 'efficiency-section';
      bestSection.innerHTML = '<h4>✨ Most Efficient (High HPS, Low Overheal)</h4>';
      
      const bestList = document.createElement('div');
      bestList.className = 'efficiency-list';
      
      efficiency.best_efficiency.forEach(enc => {
        const item = document.createElement('div');
        item.className = 'efficiency-item';
        item.innerHTML = `
          <div class="efficiency-target">${enc.target}</div>
          <div class="efficiency-stats">
            <span class="efficiency-hps">${formatNumber(enc.hps)} HPS</span>
            <span class="efficiency-overheal low-overheal">${enc.overheal_pct}% overheal</span>
            <span class="efficiency-date">${enc.date}</span>
          </div>
        `;
        bestList.appendChild(item);
      });
      
      bestSection.appendChild(bestList);
      container.appendChild(bestSection);
    }

    // Worst efficiency
    if (efficiency.worst_efficiency && efficiency.worst_efficiency.length > 0) {
      const worstSection = document.createElement('div');
      worstSection.className = 'efficiency-section';
      worstSection.innerHTML = '<h4>⚠️ Needs Improvement (High Overheal)</h4>';
      
      const worstList = document.createElement('div');
      worstList.className = 'efficiency-list';
      
      efficiency.worst_efficiency.forEach(enc => {
        const item = document.createElement('div');
        item.className = 'efficiency-item';
        item.innerHTML = `
          <div class="efficiency-target">${enc.target}</div>
          <div class="efficiency-stats">
            <span class="efficiency-hps">${formatNumber(enc.hps)} HPS</span>
            <span class="efficiency-overheal high-overheal">${enc.overheal_pct}% overheal</span>
            <span class="efficiency-date">${enc.date}</span>
          </div>
        `;
        worstList.appendChild(item);
      });
      
      worstSection.appendChild(worstList);
      container.appendChild(worstSection);
    }

    card.appendChild(container);
    return card;
  }

  // ===== MAIN PAGE RENDERER =====
  
  function renderHealingPage(data) {
    const root = q('#tab-healing');
    if (!root) return;

    root.innerHTML = '';

    // Create tab navigation
    const tabsContainer = document.createElement('div');
    tabsContainer.className = 'healing-tabs-container';
    tabsContainer.innerHTML = `
      <div class="healing-tabs">
        <button class="healing-tab active" data-tab="overview">📊 Overview</button>
        <button class="healing-tab" data-tab="encounters">💉 Encounters</button>
        <button class="healing-tab" data-tab="efficiency">⚡ Efficiency</button>
      </div>
      <div class="healing-tab-content">
        <div class="healing-tab-pane active" id="healing-tab-overview"></div>
        <div class="healing-tab-pane" id="healing-tab-encounters"></div>
        <div class="healing-tab-pane" id="healing-tab-efficiency"></div>
      </div>
    `;
    root.appendChild(tabsContainer);

    // Tab switching logic
    qa('.healing-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        const targetTab = btn.dataset.tab;
        
        // Update active states
        qa('.healing-tab').forEach(b => b.classList.remove('active'));
        qa('.healing-tab-pane').forEach(pane => pane.classList.remove('active'));
        
        btn.classList.add('active');
        q(`#healing-tab-${targetTab}`).classList.add('active');
      });
    });

    // ===== OVERVIEW TAB =====
    const overviewPane = q('#healing-tab-overview');
    
    // Overview stats
    overviewPane.appendChild(renderOverviewStats(data));
    
    // Performance Trends section
    const trendsSection = document.createElement('div');
    trendsSection.className = 'healing-section';
    const trendsTitle = document.createElement('h2');
    trendsTitle.className = 'section-title';
    trendsTitle.textContent = 'Performance Trends';
    trendsSection.appendChild(trendsTitle);
    
    trendsSection.appendChild(renderHPSTimeseries(data.hps_timeseries));
    trendsSection.appendChild(renderHealingBreakdown(data.healing_breakdown));
    trendsSection.appendChild(renderInstancePerformance(data.performance_by_instance));
    overviewPane.appendChild(trendsSection);

    // ===== ENCOUNTERS TAB =====
    const encountersPane = q('#healing-tab-encounters');
    const encountersSection = document.createElement('div');
    encountersSection.className = 'healing-section';
    const encountersTable = renderHealingEncountersTable(data.healing_encounters);
    encountersSection.appendChild(encountersTable);
    encountersPane.appendChild(encountersSection);
    
    // Initialize table after adding to DOM
    initHealingTable(data.healing_encounters);

    // Add HPS distribution to encounters tab
    const distSection = document.createElement('div');
    distSection.className = 'healing-section';
    distSection.appendChild(renderHPSDistribution(data.hps_distribution, data.hps_percentiles));
    encountersPane.appendChild(distSection);

    // ===== EFFICIENCY TAB =====
    const effPane = q('#healing-tab-efficiency');
    const effSection = document.createElement('div');
    effSection.className = 'healing-section';
    
    effSection.appendChild(renderOverhealAnalysis(data.overheal_analysis));
    effSection.appendChild(renderEfficiencyMetrics(data.efficiency_metrics));
    effPane.appendChild(effSection);
  }

  // ===== Data Loading =====
  async function loadHealingPage() {
    const root = q('#tab-healing');
    if (!root) {
      log('ERROR: #tab-healing not found in DOM');
      return;
    }

    const cid = root.dataset?.characterId;
    log('Loading healing data for character:', cid);

    if (!cid) {
      root.innerHTML = '<p class="muted">No character selected</p>';
      return;
    }

    root.innerHTML = '<div class="muted" style="text-align: center; padding: 40px 0;"><div style="font-size: 2rem; margin-bottom: 16px;">💚</div><div>Loading healing analytics...</div></div>';

    try {
      const url = `/sections/healing-data.php?character_id=${encodeURIComponent(cid)}`;
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
      
      renderHealingPage(data);
    } catch (err) {
      log('Failed to load healing data:', err);
      root.innerHTML = `<p style="color:#d32f2f;">Failed to load healing data: ${err.message}</p>`;
    }
  }

  // ===== Event Listeners =====
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'healing') {
      loadHealingPage();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || '';
    if (currentSection === 'healing') {
      loadHealingPage();
    }
  });

  if (q('#tab-healing')) {
    log('Found #tab-healing on page load, loading now...');
    loadHealingPage();
  } else {
    log('No #tab-healing found on initial load, waiting for event...');
  }

  log('Healing Analytics module loaded');
})();