/* eslint-disable no-console */
/* WhoDASH Tanking Analytics - Complete Tank Performance Tracking */
(() => {
  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[tanking]', ...a);

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
    card.className = 'tanking-overview-card';
    
    const overview = data.overview || {};
    
    card.innerHTML = `
      <h2 class="tanking-title">🛡️ Tanking Performance Overview</h2>
      <div class="tanking-stats-grid">
        <div class="tanking-stat-main">
          <div class="stat-label">Average DTPS</div>
          <div class="stat-value-huge">${formatNumber(overview.avg_dtps)}</div>
        </div>
        <div class="tanking-stat">
          <div class="stat-label">Highest DTPS</div>
          <div class="stat-value highlight">${formatNumber(overview.highest_dtps)}</div>
          ${overview.highest_dtps_target ? `<div class="stat-sublabel">${overview.highest_dtps_target}</div>` : ''}
          ${overview.highest_dtps_date ? `<div class="stat-sublabel muted">${overview.highest_dtps_date}</div>` : ''}
        </div>
        <div class="tanking-stat">
          <div class="stat-label">Total Damage Taken</div>
          <div class="stat-value">${formatNumber(overview.total_damage_taken)}</div>
        </div>
        <div class="tanking-stat">
          <div class="stat-label">Total Deaths</div>
          <div class="stat-value ${overview.total_deaths > 10 ? 'negative' : overview.total_deaths === 0 ? 'positive' : ''}">${overview.total_deaths}</div>
        </div>
        <div class="tanking-stat">
          <div class="stat-label">Survival Rate</div>
          <div class="stat-value ${overview.survival_rate >= 95 ? 'positive' : overview.survival_rate < 80 ? 'negative' : ''}">${overview.survival_rate}%</div>
        </div>
        <div class="tanking-stat">
          <div class="stat-label">Tanking Uptime</div>
          <div class="stat-value">${formatDuration(overview.tanking_uptime_seconds)}</div>
        </div>
      </div>
    `;
    
    return card;
  }

  // ===== DTPS OVER TIME CHART =====
  
 function renderDTPSTimeseries(timeseries) {
    const card = document.createElement('div');
    card.className = 'dtps-chart-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '📈 DTPS Trend (Last 30 Days)';
    card.appendChild(title);

    if (!timeseries || timeseries.length < 2) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'Not enough tanking data for chart';
      card.appendChild(msg);
      return card;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'dtps-canvas';
    canvas.width = 800;
    canvas.height = 250;
    card.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const padding = 50;
    const w = canvas.width - padding * 2;
    const h = canvas.height - padding * 2;

    const dtpsValues = timeseries.map(d => d.dtps);
    const minVal = Math.min(...dtpsValues);
    const maxVal = Math.max(...dtpsValues);
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

    // DTPS line
    ctx.strokeStyle = '#3b82f6';
    ctx.lineWidth = 3;
    ctx.beginPath();

    timeseries.forEach((d, i) => {
      const x = padding + (i / (timeseries.length - 1)) * w;
      const y = padding + h - ((d.dtps - minVal) / range) * h;
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });

    ctx.stroke();

    // Fill gradient
    ctx.lineTo(padding + w, padding + h);
    ctx.lineTo(padding, padding + h);
    ctx.closePath();
    const gradient = ctx.createLinearGradient(0, padding, 0, padding + h);
    gradient.addColorStop(0, 'rgba(59, 130, 246, 0.3)');
    gradient.addColorStop(1, 'rgba(59, 130, 246, 0.0)');
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

  // ===== TANKING BREAKDOWN PIE CHART =====
  
  function renderTankingBreakdown(breakdown) {
    const card = document.createElement('div');
    card.className = 'tanking-breakdown-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '🥧 Tanking Time Distribution';
    card.appendChild(title);

    const total = (breakdown.solo || 0) + (breakdown.party || 0) + (breakdown.raid || 0);
    
    if (total === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No tanking data available';
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
      { label: 'Party', value: breakdown.party || 0, color: '#8b5cf6' },
      { label: 'Raid', value: breakdown.raid || 0, color: '#06b6d4' },
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
    title.innerHTML = '🏰 Damage Taken by Instance';
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
    
    const maxDtps = Math.max(...instances.map(i => i.avg_dtps));

    instances.forEach((inst, idx) => {
      const barHeight = (inst.avg_dtps / maxDtps) * chartHeight;
      const x = padding + (idx * barWidth);
      const y = canvas.height - padding - barHeight;

      // Bar
      ctx.fillStyle = '#3b82f6';
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

      // DTPS value on top
      ctx.fillStyle = '#374151';
      ctx.font = 'bold 12px system-ui';
      ctx.textAlign = 'center';
      ctx.fillText(formatNumber(inst.avg_dtps), x + barWidth / 2, y - 5);
    });

    return card;
  }

  // ===== TANKING ENCOUNTERS TABLE =====
  
  function renderTankingEncountersTable(encounters) {
    const card = document.createElement('div');
    card.className = 'tanking-table-card';
    
    card.innerHTML = `
      <h3>🛡️ Tanking Encounters</h3>
      <div class="tanking-table-controls">
        <input type="text" id="tankingSearch" placeholder="Search encounter or instance..." class="search-input">
        <div class="filter-buttons">
          <button class="filter-btn active" data-filter="all">All</button>
          <button class="filter-btn" data-filter="high">High DTPS</button>
          <button class="filter-btn" data-filter="survived">Survived</button>
          <button class="filter-btn" data-filter="died">Died</button>
          <button class="filter-btn" data-filter="boss">Boss Only</button>
        </div>
      </div>
      <div class="table-wrapper">
        <table class="tanking-encounters-table">
          <thead>
            <tr>
              <th data-sort="date">Date <span class="sort-arrow">↕</span></th>
              <th data-sort="target">Target <span class="sort-arrow">↕</span></th>
              <th data-sort="instance">Instance <span class="sort-arrow">↕</span></th>
              <th data-sort="dtps">DTPS <span class="sort-arrow">↕</span></th>
              <th data-sort="duration">Duration <span class="sort-arrow">↕</span></th>
              <th data-sort="damage_taken">Damage Taken <span class="sort-arrow">↕</span></th>
              <th data-sort="group">Group <span class="sort-arrow">↕</span></th>
              <th data-sort="outcome">Outcome <span class="sort-arrow">↕</span></th>
            </tr>
          </thead>
          <tbody id="tankingTableBody">
          </tbody>
        </table>
      </div>
      <div class="table-pagination">
        <button id="tankingPrevPage" class="page-btn" disabled>← Previous</button>
        <span id="tankingPageInfo">Page 1 of 1</span>
        <button id="tankingNextPage" class="page-btn" disabled>Next →</button>
      </div>
    `;

    return card;
  }

  function initTankingTable(encounters) {
    if (!encounters || encounters.length === 0) return;

    let currentSort = { column: 'date', direction: 'desc' };
    let currentFilter = 'all';
    let currentPage = 1;
    const rowsPerPage = 20;
    let filteredData = [...encounters];

    // Calculate average DTPS for "high" filter
    const avgDtps = encounters.reduce((sum, e) => sum + e.dtps, 0) / encounters.length;

    function applyFilters() {
      const searchTerm = q('#tankingSearch')?.value.toLowerCase() || '';
      
      filteredData = encounters.filter(e => {
        const matchesSearch = !searchTerm || 
          e.target.toLowerCase().includes(searchTerm) ||
          (e.instance && e.instance.toLowerCase().includes(searchTerm));
        
        const matchesFilter = 
          currentFilter === 'all' ||
          (currentFilter === 'high' && e.dtps >= avgDtps) ||
          (currentFilter === 'survived' && !e.died) ||
          (currentFilter === 'died' && e.died) ||
          (currentFilter === 'boss' && e.is_boss);
        
        return matchesSearch && matchesFilter;
      });
      
      currentPage = 1;
      renderTable();
    }

    function renderTable() {
      const tbody = q('#tankingTableBody');
      if (!tbody) return;

      // Sort
      const sorted = [...filteredData].sort((a, b) => {
        let aVal = a[currentSort.column];
        let bVal = b[currentSort.column];

        if (currentSort.column === 'date') {
          aVal = a.ts;
          bVal = b.ts;
        } else if (currentSort.column === 'outcome') {
          aVal = a.died ? 1 : 0;
          bVal = b.died ? 1 : 0;
        } else if (currentSort.column === 'damage_taken') {
          aVal = a.total_damage_taken;
          bVal = b.total_damage_taken;
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
        
        const dtpsClass = row.dtps >= avgDtps ? 'high-dtps' : '';
        const outcomeClass = row.died ? 'died' : 'survived';
        const outcomeText = row.died ? '💀 Died' : '✓ Survived';
        
        tr.innerHTML = `
          <td>${row.date}</td>
          <td class="target-name">${row.target}${row.is_boss ? ' 👑' : ''}</td>
          <td>${row.instance || '—'}</td>
          <td class="dtps-value ${dtpsClass}">${formatNumber(row.dtps)}</td>
          <td>${formatDuration(row.duration)}</td>
          <td>${formatNumber(row.total_damage_taken)}</td>
          <td>${row.group_type} (${row.group_size})</td>
          <td class="outcome ${outcomeClass}">${outcomeText}</td>
        `;
        tbody.appendChild(tr);
      });

      // Update pagination
      q('#tankingPageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
      q('#tankingPrevPage').disabled = currentPage === 1;
      q('#tankingNextPage').disabled = currentPage === totalPages || totalPages === 0;

      // Update sort arrows
      document.querySelectorAll('.tanking-encounters-table th').forEach(th => {
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
    const searchInput = q('#tankingSearch');
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
    document.querySelectorAll('.tanking-encounters-table th[data-sort]').forEach(th => {
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
    const prevBtn = q('#tankingPrevPage');
    const nextBtn = q('#tankingNextPage');
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

  // ===== DTPS DISTRIBUTION HISTOGRAM =====
  
  function renderDTPSDistribution(distribution, percentiles) {
    const card = document.createElement('div');
    card.className = 'dtps-distribution-card';
    
    card.innerHTML = `
      <h3>📊 DTPS Distribution</h3>
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
      ctx.fillStyle = '#3b82f6';
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
          <span class="percentile-value">${formatNumber(percentiles.p50)} DTPS</span>
        </div>
        <div class="percentile-item">
          <span class="percentile-label">75th percentile:</span>
          <span class="percentile-value">${formatNumber(percentiles.p75)} DTPS</span>
        </div>
        <div class="percentile-item">
          <span class="percentile-label">90th percentile:</span>
          <span class="percentile-value">${formatNumber(percentiles.p90)} DTPS</span>
        </div>
      `;
      card.appendChild(info);
    }

    return card;
  }

  // ===== DEATH ANALYSIS =====
  
  function renderDeathAnalysis(deathData) {
    const card = document.createElement('div');
    card.className = 'death-analysis-card';
    
    card.innerHTML = `
      <h3>💀 Death Analysis</h3>
    `;

    const container = document.createElement('div');
    container.className = 'death-analysis-container';

    // Deaths by killer type
    if (deathData.by_killer_type && deathData.by_killer_type.length > 0) {
      const killerSection = document.createElement('div');
      killerSection.className = 'death-section';
      killerSection.innerHTML = '<h4>Deaths by Killer Type</h4>';
      
      const killerList = document.createElement('div');
      killerList.className = 'killer-list';
      
      deathData.by_killer_type.forEach(item => {
        const killerItem = document.createElement('div');
        killerItem.className = 'killer-item';
        killerItem.innerHTML = `
          <span class="killer-label">${item.killer_type}:</span>
          <span class="killer-count">${item.death_count} deaths</span>
        `;
        killerList.appendChild(killerItem);
      });
      
      killerSection.appendChild(killerList);
      container.appendChild(killerSection);
    }

    // Death hotspots
    if (deathData.death_hotspots && deathData.death_hotspots.length > 0) {
      const hotspotSection = document.createElement('div');
      hotspotSection.className = 'death-section';
      hotspotSection.innerHTML = '<h4>⚠️ Death Hotspots</h4>';
      
      const hotspotTable = document.createElement('table');
      hotspotTable.className = 'hotspot-table';
      hotspotTable.innerHTML = `
        <thead>
          <tr>
            <th>Location</th>
            <th>Deaths</th>
            <th>Last Death</th>
          </tr>
        </thead>
      `;
      
      const tbody = document.createElement('tbody');
      deathData.death_hotspots.forEach(spot => {
        const tr = document.createElement('tr');
        const location = spot.subzone ? `${spot.zone} - ${spot.subzone}` : spot.zone;
        tr.innerHTML = `
          <td>${location}</td>
          <td class="death-count-cell">${spot.death_count}</td>
          <td>${spot.last_death}</td>
        `;
        tbody.appendChild(tr);
      });
      
      hotspotTable.appendChild(tbody);
      hotspotSection.appendChild(hotspotTable);
      container.appendChild(hotspotSection);
    }

    // Recent deaths
    if (deathData.recent_deaths && deathData.recent_deaths.length > 0) {
      const recentSection = document.createElement('div');
      recentSection.className = 'death-section';
      recentSection.innerHTML = '<h4>Recent Deaths</h4>';
      
      const recentTable = document.createElement('table');
      recentTable.className = 'recent-deaths-table';
      recentTable.innerHTML = `
        <thead>
          <tr>
            <th>Date</th>
            <th>Killer</th>
            <th>Location</th>
            <th>Combat Duration</th>
          </tr>
        </thead>
      `;
      
      const tbody = document.createElement('tbody');
      deathData.recent_deaths.slice(0, 10).forEach(death => {
        const tr = document.createElement('tr');
        const location = death.subzone ? `${death.zone} - ${death.subzone}` : death.zone;
        tr.innerHTML = `
          <td>${death.date}</td>
          <td>${death.killer_name || 'Unknown'}</td>
          <td>${location}</td>
          <td>${formatDuration(death.combat_duration)}</td>
        `;
        tbody.appendChild(tr);
      });
      
      recentTable.appendChild(tbody);
      recentSection.appendChild(recentTable);
      container.appendChild(recentSection);
    }

    card.appendChild(container);
    return card;
  }

  // ===== SURVIVABILITY METRICS =====
  
  function renderSurvivabilityMetrics(survData) {
    const card = document.createElement('div');
    card.className = 'survivability-card';
    
    card.innerHTML = `
      <h3>💪 Survivability Metrics</h3>
    `;

    const container = document.createElement('div');
    container.className = 'survivability-container';

    // Best survivability
    if (survData.longest_survived && survData.longest_survived.length > 0) {
      const bestSection = document.createElement('div');
      bestSection.className = 'surv-section';
      bestSection.innerHTML = '<h4>✨ Toughest Fights Survived</h4>';
      
      const bestList = document.createElement('div');
      bestList.className = 'surv-list';
      
      survData.longest_survived.forEach(enc => {
        const item = document.createElement('div');
        item.className = 'surv-item';
        item.innerHTML = `
          <div class="surv-target">${enc.target}</div>
          <div class="surv-stats">
            <span class="surv-dtps">${formatNumber(enc.dtps)} DTPS</span>
            <span class="surv-duration">${formatDuration(enc.duration)}</span>
            <span class="surv-damage">${formatNumber(enc.total_damage_taken)} dmg</span>
            <span class="surv-date">${enc.date}</span>
          </div>
        `;
        bestList.appendChild(item);
      });
      
      bestSection.appendChild(bestList);
      container.appendChild(bestSection);
    }

    // Worst survivability
    if (survData.most_dangerous && survData.most_dangerous.length > 0) {
      const worstSection = document.createElement('div');
      worstSection.className = 'surv-section';
      worstSection.innerHTML = '<h4>⚠️ Quickest Deaths</h4>';
      
      const worstList = document.createElement('div');
      worstList.className = 'surv-list';
      
      survData.most_dangerous.forEach(enc => {
        const item = document.createElement('div');
        item.className = 'surv-item dangerous';
        item.innerHTML = `
          <div class="surv-target">${enc.target}</div>
          <div class="surv-stats">
            <span class="surv-duration negative">Died in ${formatDuration(enc.duration)}</span>
            <span class="surv-zone">${enc.zone}</span>
            <span class="surv-date">${enc.date}</span>
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
  
  function renderTankingPage(data) {
    const root = q('#tab-tanking');
    if (!root) return;

    root.innerHTML = '';

    // Create tab navigation
    const tabsContainer = document.createElement('div');
    tabsContainer.className = 'tanking-tabs-container';
    tabsContainer.innerHTML = `
      <div class="tanking-tabs">
        <button class="tanking-tab active" data-tab="overview">📊 Overview</button>
        <button class="tanking-tab" data-tab="encounters">🛡️ Encounters</button>
        <button class="tanking-tab" data-tab="survivability">💪 Survivability</button>
      </div>
      <div class="tanking-tab-content">
        <div class="tanking-tab-pane active" id="tanking-tab-overview"></div>
        <div class="tanking-tab-pane" id="tanking-tab-encounters"></div>
        <div class="tanking-tab-pane" id="tanking-tab-survivability"></div>
      </div>
    `;
    root.appendChild(tabsContainer);

    // Tab switching logic
    qa('.tanking-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        const targetTab = btn.dataset.tab;
        
        // Update active states
        qa('.tanking-tab').forEach(b => b.classList.remove('active'));
        qa('.tanking-tab-pane').forEach(pane => pane.classList.remove('active'));
        
        btn.classList.add('active');
        q(`#tanking-tab-${targetTab}`).classList.add('active');
      });
    });

    // ===== OVERVIEW TAB =====
    const overviewPane = q('#tanking-tab-overview');
    
    // Overview stats
    overviewPane.appendChild(renderOverviewStats(data));
    
    // Performance Trends section
    const trendsSection = document.createElement('div');
    trendsSection.className = 'tanking-section';
    const trendsTitle = document.createElement('h2');
    trendsTitle.className = 'section-title';
    trendsTitle.textContent = 'Performance Trends';
    trendsSection.appendChild(trendsTitle);
    
    trendsSection.appendChild(renderDTPSTimeseries(data.dtps_timeseries));
    trendsSection.appendChild(renderTankingBreakdown(data.tanking_breakdown));
    trendsSection.appendChild(renderInstancePerformance(data.performance_by_instance));
    overviewPane.appendChild(trendsSection);

    // ===== ENCOUNTERS TAB =====
    const encountersPane = q('#tanking-tab-encounters');
    const encountersSection = document.createElement('div');
    encountersSection.className = 'tanking-section';
    const encountersTable = renderTankingEncountersTable(data.tanking_encounters);
    encountersSection.appendChild(encountersTable);
    encountersPane.appendChild(encountersSection);
    
    // Initialize table after adding to DOM
    initTankingTable(data.tanking_encounters);

    // Add DTPS distribution to encounters tab
    const distSection = document.createElement('div');
    distSection.className = 'tanking-section';
    distSection.appendChild(renderDTPSDistribution(data.dtps_distribution, data.dtps_percentiles));
    encountersPane.appendChild(distSection);

    // ===== SURVIVABILITY TAB =====
    const survPane = q('#tanking-tab-survivability');
    const survSection = document.createElement('div');
    survSection.className = 'tanking-section';
    
    survSection.appendChild(renderSurvivabilityMetrics(data.survivability_metrics));
    survSection.appendChild(renderDeathAnalysis(data.death_analysis));
    survPane.appendChild(survSection);
  }

  // ===== Data Loading =====
  async function loadTankingPage() {
    const root = q('#tab-tanking');
    if (!root) {
      log('ERROR: #tab-tanking not found in DOM');
      return;
    }

    const cid = root.dataset?.characterId;
    log('Loading tanking data for character:', cid);

    if (!cid) {
      root.innerHTML = '<p class="muted">No character selected</p>';
      return;
    }

    root.innerHTML = '<div class="muted" style="text-align: center; padding: 40px 0;"><div style="font-size: 2rem; margin-bottom: 16px;">🛡️</div><div>Loading tanking analytics...</div></div>';

    try {
      const url = `/sections/tanking-data.php?character_id=${encodeURIComponent(cid)}`;
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
      
      renderTankingPage(data);
    } catch (err) {
      log('Failed to load tanking data:', err);
      root.innerHTML = `<p style="color:#d32f2f;">Failed to load tanking data: ${err.message}</p>`;
    }
  }

  // ===== Event Listeners =====
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'tanking') {
      loadTankingPage();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || '';
    if (currentSection === 'tanking') {
      loadTankingPage();
    }
  });

  if (q('#tab-tanking')) {
    log('Found #tab-tanking on page load, loading now...');
    loadTankingPage();
  } else {
    log('No #tab-tanking found on initial load, waiting for event...');
  }

  log('Tanking Analytics module loaded');
})();