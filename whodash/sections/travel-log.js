/* eslint-disable no-console */
/* WhoDASH Travel Log — Journey Tracker */
(() => {
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[travel-log]', ...a);

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

  function timeAgo(ts) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - ts;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
  }

  // ===== Tab System =====
  function setupTabs(container) {
    const tabs = qa('.travel-tab', container);
    const contents = qa('.travel-tab-content', container);

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        
        // Update tabs
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Update content
        contents.forEach(c => c.classList.remove('active'));
        const targetContent = q(`#travel-${target}`, container);
        if (targetContent) targetContent.classList.add('active');
      });
    });
  }

  // ===== Journal Tab (Overview Stats) =====
  function renderJournal(data) {
    const container = document.createElement('div');
    container.id = 'travel-journal';
    container.className = 'travel-tab-content active';

    // Stats Cards
    const statsGrid = document.createElement('div');
    statsGrid.className = 'travel-stats-grid';
    statsGrid.innerHTML = `
      <div class="travel-stat-card">
        <div class="stat-icon">🗺️</div>
        <div class="stat-value">${data.unique_zones || 0}</div>
        <div class="stat-label">Unique Zones Visited</div>
      </div>
      
      <div class="travel-stat-card">
        <div class="stat-icon">🚶</div>
        <div class="stat-value">${(data.total_zone_changes || 0).toLocaleString()}</div>
        <div class="stat-label">Total Zone Changes</div>
      </div>
      
      <div class="travel-stat-card">
        <div class="stat-icon">⏱️</div>
        <div class="stat-value">${data.hours_played || 0}h</div>
        <div class="stat-label">Time Played</div>
      </div>
      
      <div class="travel-stat-card">
        <div class="stat-icon">🧭</div>
        <div class="stat-value">${data.wanderlust_index || 0}</div>
        <div class="stat-label">Wanderlust Index</div>
        <div class="stat-sublabel">(zone changes per hour)</div>
      </div>
      
      <div class="travel-stat-card">
        <div class="stat-icon">🌍</div>
        <div class="stat-value">${data.exploration_score || 0}%</div>
        <div class="stat-label">Exploration Score</div>
        <div class="stat-sublabel">(of ~80 WotLK zones)</div>
      </div>
      
      <div class="travel-stat-card">
        <div class="stat-icon">⚔️</div>
        <div class="stat-value">${data.dungeons_visited || 0}</div>
        <div class="stat-label">Dungeons Visited</div>
        <div class="stat-sublabel">(instances & raids)</div>
      </div>
    `;
    
    container.appendChild(statsGrid);

    // Favorite Zone Card
    if (data.favorite_zone) {
      const favoriteCard = document.createElement('div');
      favoriteCard.className = 'favorite-zone-card';
      favoriteCard.innerHTML = `
        <h3>⭐ Your Favorite Zone</h3>
        <div class="favorite-zone-name">${data.favorite_zone}</div>
        <div class="favorite-zone-stats">
          Visited ${data.favorite_zone_visits.toLocaleString()} times
        </div>
      `;
      container.appendChild(favoriteCard);
    }

    // Zone Timeline Graph
    if (data.zone_timeline && data.zone_timeline.length > 0) {
      const timelineCard = document.createElement('div');
      timelineCard.className = 'zone-timeline-card';
      
      const timelineTitle = document.createElement('h3');
      timelineTitle.textContent = '📊 Zone Activity Timeline';
      timelineCard.appendChild(timelineTitle);

      const canvas = document.createElement('canvas');
      canvas.className = 'zone-timeline-canvas';
      canvas.width = 1000;
      canvas.height = 300;
      timelineCard.appendChild(canvas);

      container.appendChild(timelineCard);

      // Render timeline after DOM insertion
      setTimeout(() => renderZoneTimeline(canvas, data.zone_timeline), 0);
    }

    return container;
  }

  // ===== Zone Timeline Graph =====
  function renderZoneTimeline(canvas, timelineData) {
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    const padding = 50;
    const plotWidth = width - padding * 2;
    const plotHeight = height - padding * 2;

    // Group data by date
    const dateMap = {};
    timelineData.forEach(item => {
      if (!dateMap[item.date]) {
        dateMap[item.date] = {};
      }
      dateMap[item.date][item.zone] = parseInt(item.visit_count);
    });

    // Get unique zones (top 5 most visited for clarity)
    const zoneCounts = {};
    timelineData.forEach(item => {
      zoneCounts[item.zone] = (zoneCounts[item.zone] || 0) + parseInt(item.visit_count);
    });
    const topZones = Object.entries(zoneCounts)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5)
      .map(([zone]) => zone);

    const dates = Object.keys(dateMap).sort();
    if (dates.length === 0) return;

    // Colors for zones
    const colors = ['#3182ce', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

    // Clear canvas
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, width, height);

    // Find max value for scaling
    let maxValue = 0;
    dates.forEach(date => {
      topZones.forEach(zone => {
        maxValue = Math.max(maxValue, dateMap[date][zone] || 0);
      });
    });

    // Draw grid lines
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
      const y = padding + (plotHeight / 5) * i;
      ctx.beginPath();
      ctx.moveTo(padding, y);
      ctx.lineTo(width - padding, y);
      ctx.stroke();
    }

    // Draw lines for each zone
    topZones.forEach((zone, zoneIdx) => {
      ctx.strokeStyle = colors[zoneIdx];
      ctx.lineWidth = 2.5;
      ctx.beginPath();

      let started = false;
      dates.forEach((date, dateIdx) => {
        const value = dateMap[date][zone] || 0;
        const x = padding + (plotWidth / (dates.length - 1 || 1)) * dateIdx;
        const y = padding + plotHeight - (value / maxValue) * plotHeight;

        if (!started) {
          ctx.moveTo(x, y);
          started = true;
        } else {
          ctx.lineTo(x, y);
        }
      });

      ctx.stroke();

      // Draw points
      dates.forEach((date, dateIdx) => {
        const value = dateMap[date][zone] || 0;
        if (value > 0) {
          const x = padding + (plotWidth / (dates.length - 1 || 1)) * dateIdx;
          const y = padding + plotHeight - (value / maxValue) * plotHeight;

          ctx.fillStyle = colors[zoneIdx];
          ctx.beginPath();
          ctx.arc(x, y, 4, 0, Math.PI * 2);
          ctx.fill();
        }
      });
    });

    // Draw axes
    ctx.strokeStyle = '#374151';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();

    // Y-axis labels
    ctx.fillStyle = '#6b7280';
    ctx.font = '12px system-ui';
    ctx.textAlign = 'right';
    for (let i = 0; i <= 5; i++) {
      const value = Math.round((maxValue / 5) * (5 - i));
      const y = padding + (plotHeight / 5) * i;
      ctx.fillText(value.toString(), padding - 10, y + 4);
    }

    // X-axis labels (show every few dates)
    ctx.textAlign = 'center';
    const labelStep = Math.ceil(dates.length / 8);
    dates.forEach((date, idx) => {
      if (idx % labelStep === 0 || idx === dates.length - 1) {
        const x = padding + (plotWidth / (dates.length - 1 || 1)) * idx;
        const shortDate = new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        ctx.save();
        ctx.translate(x, height - padding + 20);
        ctx.rotate(-Math.PI / 4);
        ctx.fillText(shortDate, 0, 0);
        ctx.restore();
      }
    });

    // Legend
    ctx.textAlign = 'left';
    const legendX = padding;
    const legendY = 20;
    topZones.forEach((zone, idx) => {
      const x = legendX + idx * 150;
      
      // Color box
      ctx.fillStyle = colors[idx];
      ctx.fillRect(x, legendY, 12, 12);
      
      // Zone name
      ctx.fillStyle = '#374151';
      ctx.font = '12px system-ui';
      ctx.fillText(zone.substring(0, 15), x + 18, legendY + 10);
    });
  }

  // ===== Log Tab (Searchable, Date-Filtered Table) =====
  function renderLog(data) {
    const container = document.createElement('div');
    container.id = 'travel-log';
    container.className = 'travel-tab-content';

    const header = document.createElement('div');
    header.className = 'log-header';
    header.innerHTML = `
      <h3>📜 Travel History</h3>
      <div class="log-filters">
        <input type="text" id="zoneSearchInput" class="zone-search-input" placeholder="Search zones...">
        <input type="date" id="dateFromInput" class="date-input" placeholder="From">
        <input type="date" id="dateToInput" class="date-input" placeholder="To">
        <button id="clearFilters" class="clear-filters-btn">Clear</button>
      </div>
    `;
    container.appendChild(header);

    if (!data.recent_zones || data.recent_zones.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No zone data available';
      container.appendChild(msg);
      return container;
    }

    const tableContainer = document.createElement('div');
    tableContainer.className = 'log-table-container';
    
    const table = document.createElement('table');
    table.className = 'travel-log-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Zone</th>
          <th>Subzone</th>
          <th>Date</th>
          <th>Time</th>
        </tr>
      </thead>
      <tbody id="logTableBody"></tbody>
    `;
    tableContainer.appendChild(table);
    container.appendChild(tableContainer);

    // Pagination info
    const paginationInfo = document.createElement('div');
    paginationInfo.className = 'pagination-info';
    paginationInfo.id = 'paginationInfo';
    container.appendChild(paginationInfo);

    // Store data for filtering
    container.dataset.zoneData = JSON.stringify(data.recent_zones);

    // Initial render
    setTimeout(() => filterAndRenderLogTable(data.recent_zones, container), 0);

    // Setup event listeners
    setTimeout(() => {
      const searchInput = q('#zoneSearchInput', container);
      const dateFrom = q('#dateFromInput', container);
      const dateTo = q('#dateToInput', container);
      const clearBtn = q('#clearFilters', container);

      const applyFilters = () => {
        const allData = JSON.parse(container.dataset.zoneData);
        filterAndRenderLogTable(allData, container);
      };

      if (searchInput) searchInput.addEventListener('input', applyFilters);
      if (dateFrom) dateFrom.addEventListener('change', applyFilters);
      if (dateTo) dateTo.addEventListener('change', applyFilters);
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          if (searchInput) searchInput.value = '';
          if (dateFrom) dateFrom.value = '';
          if (dateTo) dateTo.value = '';
          applyFilters();
        });
      }
    }, 0);

    return container;
  }

  function filterAndRenderLogTable(allData, container) {
    const searchInput = q('#zoneSearchInput', container);
    const dateFrom = q('#dateFromInput', container);
    const dateTo = q('#dateToInput', container);
    const tbody = q('#logTableBody', container);
    const paginationInfo = q('#paginationInfo', container);

    if (!tbody) return;

    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const fromDate = dateFrom && dateFrom.value ? new Date(dateFrom.value).getTime() / 1000 : null;
    const toDate = dateTo && dateTo.value ? new Date(dateTo.value).getTime() / 1000 + 86400 : null; // End of day

    // Filter data
    const filtered = allData.filter(z => {
      const matchesSearch = !searchTerm || 
        (z.zone && z.zone.toLowerCase().includes(searchTerm)) ||
        (z.subzone && z.subzone.toLowerCase().includes(searchTerm));
      
      const matchesDateFrom = !fromDate || z.ts >= fromDate;
      const matchesDateTo = !toDate || z.ts <= toDate;

      return matchesSearch && matchesDateFrom && matchesDateTo;
    });

    // Render rows
    tbody.innerHTML = filtered.map(z => {
      const date = new Date(z.ts * 1000);
      return `
        <tr>
          <td><strong>${z.zone || 'Unknown'}</strong></td>
          <td>${z.subzone || '—'}</td>
          <td>${date.toLocaleDateString()}</td>
          <td class="muted">${date.toLocaleTimeString()}</td>
        </tr>
      `;
    }).join('');

    // Update pagination info
    if (paginationInfo) {
      paginationInfo.textContent = `Showing ${filtered.length.toLocaleString()} of ${allData.length.toLocaleString()} visits`;
    }
  }

  // ===== Heatmap Tab (Zone Time Analysis) =====
  function renderHeatmap(data) {
    const container = document.createElement('div');
    container.id = 'travel-heatmap';
    container.className = 'travel-tab-content';

    // Zone Distribution
    const heatmapSection = document.createElement('div');
    heatmapSection.className = 'heatmap-section';
    
    const heatmapTitle = document.createElement('h3');
    heatmapTitle.textContent = '🌡️ Zone Distribution';
    heatmapSection.appendChild(heatmapTitle);

    if (!data.zone_heatmap || data.zone_heatmap.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No zone data available';
      heatmapSection.appendChild(msg);
      container.appendChild(heatmapSection);
      return container;
    }

    // Heatmap bars
    const heatmapBars = document.createElement('div');
    heatmapBars.className = 'heatmap-bars';
    
    const maxCount = Math.max(...data.zone_heatmap.map(z => z.visit_count));
    
    data.zone_heatmap.slice(0, 15).forEach(zone => {
      const barContainer = document.createElement('div');
      barContainer.className = 'heatmap-bar-container';
      
      const intensity = zone.visit_count / maxCount;
      const color = `hsl(210, ${50 + intensity * 50}%, ${70 - intensity * 30}%)`;
      
      barContainer.innerHTML = `
        <div class="heatmap-label">
          <span class="zone-name">${zone.zone}</span>
          <span class="zone-percentage">${zone.percentage}%</span>
        </div>
        <div class="heatmap-bar-track">
          <div class="heatmap-bar-fill" style="width: ${zone.percentage}%; background: ${color};"></div>
        </div>
        <div class="heatmap-stats">${zone.visit_count.toLocaleString()} visits</div>
      `;
      
      heatmapBars.appendChild(barContainer);
    });
    
    heatmapSection.appendChild(heatmapBars);
    container.appendChild(heatmapSection);

    // Zone Transitions
    if (data.zone_transitions && data.zone_transitions.length > 0) {
      const transitionsSection = document.createElement('div');
      transitionsSection.className = 'transitions-section';
      
      const transitionsTitle = document.createElement('h3');
      transitionsTitle.textContent = '🔀 Most Common Routes';
      transitionsSection.appendChild(transitionsTitle);

      const transitionsList = document.createElement('div');
      transitionsList.className = 'transitions-list';
      
      data.zone_transitions.slice(0, 10).forEach(t => {
        const item = document.createElement('div');
        item.className = 'transition-item';
        item.innerHTML = `
          <div class="transition-route">
            <span class="from-zone">${t.from_zone}</span>
            <span class="transition-arrow">→</span>
            <span class="to-zone">${t.to_zone}</span>
          </div>
          <div class="transition-count">${t.transition_count} times</div>
        `;
        transitionsList.appendChild(item);
      });
      
      transitionsSection.appendChild(transitionsList);
      container.appendChild(transitionsSection);
    }

    // Subzone Breakdown
    if (data.subzone_breakdown && data.subzone_breakdown.length > 0) {
      const subzoneSection = document.createElement('div');
      subzoneSection.className = 'subzone-section';
      
      const subzoneTitle = document.createElement('h3');
      subzoneTitle.textContent = '📍 Top Subzones';
      subzoneSection.appendChild(subzoneTitle);

      const subzoneTable = document.createElement('table');
      subzoneTable.className = 'subzone-table';
      subzoneTable.innerHTML = `
        <thead>
          <tr>
            <th>Zone</th>
            <th>Subzone</th>
            <th>Visits</th>
          </tr>
        </thead>
        <tbody>
          ${data.subzone_breakdown.map(s => `
            <tr>
              <td><strong>${s.zone}</strong></td>
              <td>${s.subzone}</td>
              <td>${s.visit_count.toLocaleString()}</td>
            </tr>
          `).join('')}
        </tbody>
      `;
      
      subzoneSection.appendChild(subzoneTable);
      container.appendChild(subzoneSection);
    }

    return container;
  }

  // ===== Main Render =====
  async function initTravelLog() {
    const section = q('#tab-travel-log');
    if (!section) {
      log('Section not found');
      return;
    }

    const characterId = section.dataset.characterId;
    if (!characterId) {
      section.innerHTML = '<div class="muted">No character selected</div>';
      return;
    }

    log('Loading travel data for character', characterId);

    try {
      const response = await fetch(`/sections/travel-log-data.php?character_id=${characterId}`, {
        credentials: 'include'
      });

      if (!response.ok) {
        // Try to get error details from JSON
        const errorData = await response.json().catch(() => null);
        if (errorData && errorData.message) {
          throw new Error(`HTTP ${response.status}: ${errorData.message}`);
        }
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      log('Travel data loaded:', data);

      // Build UI
      const container = document.createElement('div');
      container.className = 'travel-container';

      // Tab Navigation
      const tabNav = document.createElement('div');
      tabNav.className = 'travel-tabs';
      tabNav.innerHTML = `
        <button class="travel-tab active" data-tab="journal">📔 Journal</button>
        <button class="travel-tab" data-tab="log">📜 Log</button>
        <button class="travel-tab" data-tab="heatmap">🗺️ Heatmap</button>
      `;
      container.appendChild(tabNav);

      // Tab Contents
      const contentWrapper = document.createElement('div');
      contentWrapper.className = 'travel-content-wrapper';
      
      contentWrapper.appendChild(renderJournal(data));
      contentWrapper.appendChild(renderLog(data));
      contentWrapper.appendChild(renderHeatmap(data));
      
      container.appendChild(contentWrapper);

      // Replace loading indicator
      section.innerHTML = '';
      section.appendChild(container);

      // Setup tab switching
      setupTabs(section);

    } catch (error) {
      log('Error loading travel data:', error);
      section.innerHTML = `<div class="muted">Error loading travel data: ${error.message}</div>`;
    }
  }

  // ===== Auto-init on section load =====
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'travel-log') {
      log('Section loaded event received');
      initTravelLog();
    }
  });

  // Also try immediate init if section already exists
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (q('#tab-travel-log')) initTravelLog();
    });
  } else {
    if (q('#tab-travel-log')) initTravelLog();
  }

  log('Travel log module loaded');
})();