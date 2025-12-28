// graphs.js
/**
 * Graphs Tab - Timeline Visualizations using SVG
 * Shows various character statistics over time in a beautiful 2-column layout
 */

(() => {
  'use strict';

  const log = (...a) => console.log('[graphs]', ...a);
  const q = (sel, root = document) => root.querySelector(sel);

  const state = {
    characterId: null,
    data: null
  };

  // Chart color palette - matching the site's blue theme
  const colors = {
    primary: '#3182ce',
    gold: '#f59e0b',
    danger: '#ef4444',
    purple: '#a855f7',
    success: '#10b981',
    teal: '#14b8a6',
    warning: '#f59e0b',
    secondary: '#8b5cf6',
    info: '#3b82f6'
  };

  // Main initialization function
  async function loadGraphs() {
    const section = q('#tab-graphs');
    if (!section) {
      log('No #tab-graphs found');
      return;
    }

    log('Initializing graphs...');
    
    const charId = parseInt(section.dataset.characterId, 10);
    if (!charId) {
      section.innerHTML = '<div class="error-msg">No character selected</div>';
      return;
    }
    
    state.characterId = charId;
    renderGraphsLayout(section);
    await fetchData();
  }

  // Render the graphs layout
  function renderGraphsLayout(section) {
    section.innerHTML = `
      <style>
        .graphs-container {
          padding: 20px;
        }
        
        .graphs-header {
          text-align: center;
          margin-bottom: 40px;
        }
        
        .graphs-header h1 {
          font-size: 2.5rem;
          color: #2456a5;
          margin-bottom: 8px;
        }
        
        .graphs-header p {
          color: #64748b;
          font-size: 1.1rem;
        }
        
        .graphs-grid {
          display: grid;
          grid-template-columns: repeat(2, 1fr);
          gap: 30px;
          margin-top: 30px;
        }
        
        .graph-card {
          background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
          border-radius: 12px;
          padding: 24px;
          box-shadow: 0 4px 16px rgba(49, 130, 206, 0.08);
          border: 1px solid rgba(49, 130, 206, 0.1);
          transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .graph-card:hover {
          transform: translateY(-4px);
          box-shadow: 0 8px 24px rgba(49, 130, 206, 0.16);
        }
        
        .graph-card h3 {
          color: #2456a5;
          font-size: 1.25rem;
          margin: 0 0 20px 0;
          font-weight: 600;
          text-align: center;
        }
        
        .graph-wrapper {
          position: relative;
          height: 200px;
          margin-top: 12px;
        }
        
        .loading-spinner {
          text-align: center;
          padding: 40px;
          color: #3182ce;
        }
        
        .error-msg {
          text-align: center;
          padding: 40px;
          color: #ef4444;
          font-weight: 500;
        }
        
        .no-data-msg {
          text-align: center;
          padding: 60px 20px;
          color: #94a3b8;
          font-style: italic;
          font-size: 0.95rem;
        }
        
        .graph-svg {
          width: 100%;
          height: 100%;
        }
        
        .whodat-tip {
          position: absolute;
          background: rgba(0, 0, 0, 0.9);
          color: white;
          padding: 8px 12px;
          border-radius: 6px;
          font-size: 0.875rem;
          pointer-events: none;
          opacity: 0;
          transition: opacity 0.2s;
          white-space: nowrap;
          z-index: 1000;
        }
        
        .whodat-tip.visible {
          opacity: 1;
        }
        
        @media (max-width: 1024px) {
          .graphs-grid {
            grid-template-columns: 1fr;
          }
        }
      </style>
      
      <div class="graphs-container">
        <div class="graphs-header">
          <h1>📊 Timeline Graphs</h1>
          <p>Visual journey through your character's progression</p>
        </div>
        
        <div class="loading-spinner">
          <div style="font-size: 2rem; margin-bottom: 16px;">⏳</div>
          <div>Loading graph data...</div>
        </div>
        
        <div class="graphs-grid" style="display: none;">
          <!-- Level Progression -->
          <div class="graph-card">
            <h3>📈 Level Progression</h3>
            <div class="graph-wrapper" id="graph-level"></div>
          </div>
          
          <!-- Gold Over Time -->
          <div class="graph-card">
            <h3>💰 Gold Over Time</h3>
            <div class="graph-wrapper" id="graph-gold"></div>
          </div>
          
          <!-- Honor Points -->
          <div class="graph-card">
            <h3>⚔️ Honor Points</h3>
            <div class="graph-wrapper" id="graph-honor"></div>
          </div>
          
          <!-- Arena Points -->
          <div class="graph-card">
            <h3>🏆 Arena Points</h3>
            <div class="graph-wrapper" id="graph-arena"></div>
          </div>
          
          <!-- Deaths Per Day -->
          <div class="graph-card">
            <h3>💀 Deaths Per Day</h3>
            <div class="graph-wrapper" id="graph-deaths"></div>
          </div>
          
          <!-- Boss Kills Per Day -->
          <div class="graph-card">
            <h3>🐉 Boss Kills Per Day</h3>
            <div class="graph-wrapper" id="graph-bosses"></div>
          </div>
          
          <!-- Reputation Gains -->
          <div class="graph-card">
            <h3>🌟 Daily Reputation Gains</h3>
            <div class="graph-wrapper" id="graph-rep"></div>
          </div>
          
          <!-- Achievements -->
          <div class="graph-card">
            <h3>🏅 Achievements Per Day</h3>
            <div class="graph-wrapper" id="graph-achievements"></div>
          </div>
          
          <!-- Quest Completion -->
          <div class="graph-card">
            <h3>📜 Daily Quest Completions</h3>
            <div class="graph-wrapper" id="graph-quests"></div>
          </div>
          
          <!-- Zone Activity -->
          <div class="graph-card">
            <h3>🗺️ Daily Activity Hours</h3>
            <div class="graph-wrapper" id="graph-zones"></div>
          </div>
        </div>
      </div>
    `;
  }

  // Fetch data from backend
  async function fetchData() {
    try {
      const url = `/sections/graphs-data.php?character_id=${encodeURIComponent(state.characterId)}`;
      log('Fetching from:', url);

      const resp = await fetch(url, { credentials: 'include' });
      
      log('Response status:', resp.status);
      
      if (!resp.ok) {
        throw new Error(`HTTP ${resp.status}`);
      }
      
      state.data = await resp.json();
      log('Data loaded:', state.data);
      
      // Hide loading, show graphs
      const container = q('.graphs-container');
      if (container) {
        const loading = container.querySelector('.loading-spinner');
        const grid = container.querySelector('.graphs-grid');
        if (loading) loading.style.display = 'none';
        if (grid) grid.style.display = 'grid';
      }
      
      renderAllGraphs();
    } catch (err) {
      log('Error fetching data:', err);
      const container = q('.graphs-container');
      if (container) {
        container.innerHTML = `<div class="error-msg">Failed to load graph data: ${err.message}</div>`;
      }
    }
  }

  // SVG creation helpers (from main.js pattern)
  function svgLine(points, width, height, stroke, strokeWidth, fill = false) {
    if (!points.length) return '';
    const d = points.map((p, i) => (i === 0 ? `M${p[0]},${p[1]}` : `L${p[0]},${p[1]}`)).join(' ');
    
    let svg = `<svg viewBox="0 0 ${width} ${height}" width="${width}" height="${height}" class="graph-svg" role="img" aria-label="trend">`;
    
    if (fill) {
      // Create filled area under the line
      const fillPath = d + ` L${width - 1},${height - 1} L1,${height - 1} Z`;
      svg += `<path d="${fillPath}" fill="${stroke}" fill-opacity="0.1"/>`;
    }
    
    svg += `<path d="${d}" fill="none" stroke="${stroke}" stroke-width="${strokeWidth}" vector-effect="non-scaling-stroke"/>`;
    svg += `</svg>`;
    
    return svg;
  }

  function scaleSeries(series, width, height, valueKey) {
    const n = series.length;
    if (n === 0) return [];
    
    const vals = series.map(s => Number(s[valueKey] || 0));
    const minV = Math.min(...vals);
    const maxV = Math.max(...vals);
    const dv = maxV - minV || 1;
    const stepX = (width - 2) / Math.max(1, n - 1);
    
    return series.map((s, i) => {
      const x = 1 + i * stepX;
      const y = height - 1 - ((Number(s[valueKey]) - minV) / dv) * (height - 2);
      return [x, y];
    });
  }

  function formatValue(value, type) {
    if (type === 'gold') {
      const gold = Math.floor(value / 10000);
      return `${gold.toLocaleString()}g`;
    }
    if (type === 'hours') {
      return `${value.toFixed(1)}h`;
    }
    return Math.round(value).toLocaleString();
  }

  function attachTooltip(host, series, pts, { width, height, valueKey, color, valueType = 'number' }) {
    if (!host || !pts?.length || !series?.length) return;
    
    const svg = host.querySelector('svg');
    if (!svg) return;

    host.style.position = 'relative';

    // Tooltip container
    const tip = document.createElement('div');
    tip.className = 'whodat-tip';
    host.appendChild(tip);

    // Marker in SVG
    const ns = 'http://www.w3.org/2000/svg';
    const dot = document.createElementNS(ns, 'circle');
    dot.setAttribute('r', '4');
    dot.setAttribute('fill', color);
    dot.setAttribute('stroke', '#fff');
    dot.setAttribute('stroke-width', '2');
    dot.setAttribute('opacity', '0');
    svg.appendChild(dot);

    const vline = document.createElementNS(ns, 'line');
    vline.setAttribute('y1', '0');
    vline.setAttribute('y2', String(height));
    vline.setAttribute('stroke', color);
    vline.setAttribute('stroke-width', '1');
    vline.setAttribute('stroke-dasharray', '4,4');
    vline.setAttribute('opacity', '0');
    svg.appendChild(vline);

    let current = -1;

    function showAtIndex(idx) {
      if (idx < 0 || idx >= series.length) return;
      const item = series[idx];
      const pt = pts[idx];
      
      dot.setAttribute('cx', String(pt[0]));
      dot.setAttribute('cy', String(pt[1]));
      dot.setAttribute('opacity', '1');
      
      vline.setAttribute('x1', String(pt[0]));
      vline.setAttribute('x2', String(pt[0]));
      vline.setAttribute('opacity', '0.5');

      const value = formatValue(Number(item[valueKey]), valueType);
      const date = item.ts ? new Date(item.ts * 1000).toLocaleDateString() : '';
      tip.textContent = `${date}: ${value}`;
      tip.classList.add('visible');
      
      const rect = host.getBoundingClientRect();
      tip.style.left = `${pt[0] + 10}px`;
      tip.style.top = `${pt[1] - 30}px`;
    }

    function hide() {
      dot.setAttribute('opacity', '0');
      vline.setAttribute('opacity', '0');
      tip.classList.remove('visible');
      current = -1;
    }

    host.addEventListener('mousemove', (e) => {
      const rect = svg.getBoundingClientRect();
      const x = e.clientX - rect.left;
      const stepX = (width - 2) / Math.max(1, pts.length - 1);
      const idx = Math.round((x - 1) / stepX);
      if (idx !== current && idx >= 0 && idx < series.length) {
        current = idx;
        showAtIndex(current);
      }
    });

    host.addEventListener('mouseleave', hide);
  }

  // Render individual graphs
  function renderGraph(containerId, data, color, valueKey, valueType = 'number') {
    const container = document.getElementById(containerId);
    if (!container) return;

    if (!data || data.length === 0) {
      container.innerHTML = '<div class="no-data-msg">No data available yet</div>';
      return;
    }

    const width = 600;
    const height = 200;
    const pts = scaleSeries(data, width, height, valueKey);
    container.innerHTML = svgLine(pts, width, height, color, 2.5, true);
    
    attachTooltip(container, data, pts, { width, height, valueKey, color, valueType });
  }

  // Render all graphs
  function renderAllGraphs() {
    if (!state.data) return;
    
    renderGraph('graph-level', state.data.level_progression, colors.primary, 'value');
    renderGraph('graph-gold', state.data.gold_over_time, colors.gold, 'value', 'gold');
    renderGraph('graph-honor', state.data.honor_points, colors.danger, 'value');
    renderGraph('graph-arena', state.data.arena_points, colors.purple, 'value');
    renderGraph('graph-deaths', state.data.deaths_per_day, colors.danger, 'value');
    renderGraph('graph-bosses', state.data.boss_kills_per_day, colors.success, 'value');
    renderGraph('graph-rep', state.data.reputation_gains, colors.teal, 'value');
    renderGraph('graph-achievements', state.data.achievements_per_day, colors.warning, 'value');
    renderGraph('graph-quests', state.data.quest_completion, colors.secondary, 'value');
    renderGraph('graph-zones', state.data.zone_activity_hours, colors.info, 'value', 'hours');
  }

  // Event listeners
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'graphs') {
      loadGraphs();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || '';
    if (currentSection === 'graphs') {
      loadGraphs();
    }
  });

  // Initial load if the section is already present
  if (q('#tab-graphs')) {
    log('Found #tab-graphs on page load, loading now...');
    loadGraphs();
  } else {
    log('No #tab-graphs found on initial load, waiting for event...');
  }

  log('Graphs module loaded and ready');
})();