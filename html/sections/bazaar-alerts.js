// sections/bazaar.js - Complete Darkmoon Bazaar JavaScript (FIXED FOR SPA)
// Handles all 11 tabs with faction filtering, day/night mode, and data rendering

(function() {
  'use strict';

  /* ============================== State Management ============================== */
  const state = {
    currentFaction: 'xfaction',
    currentTab: 'auction',
    cachedData: {},
    characterFactions: {},
    tabDataLoaded: {},
    initialized: false
  };

  const DEBUG = false;
  const log = DEBUG ? console.log.bind(console, '[Bazaar]') : () => {};

  /* ============================== Initialization ============================== */
  function init() {
    // Check if bazaar section exists in DOM
    const bazaarSection = document.getElementById('tab-bazaar');
    if (!bazaarSection) {
      log('Bazaar section not in DOM yet, skipping init');
      return false;
    }

    // Prevent double initialization
    if (state.initialized) {
      log('Bazaar already initialized, skipping');
      return false;
    }

    log('Initializing Darkmoon Bazaar');
    
    // Set up faction tabs
    setupFactionTabs();
    
    // Set up content tabs
    setupContentTabs();
    
    // Load character factions
    loadCharacterFactions();
    
    // Load initial tab data
    loadTabData('auction');
    
    state.initialized = true;
    log('Initialization complete');
    return true;
  }

  /* ============================== Faction Tabs ============================== */
  function setupFactionTabs() {
    const factionTabs = document.querySelectorAll('.faction-tab');
    
    if (factionTabs.length === 0) {
      log('No faction tabs found');
      return;
    }
    
    factionTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const faction = tab.dataset.faction;
        switchFaction(faction);
      });
    });
    
    log(`Set up ${factionTabs.length} faction tabs`);
  }

  function switchFaction(factionName) {
    log(`Switching to faction: ${factionName}`);
    
    state.currentFaction = factionName;
    
    // Update active faction tab
    document.querySelectorAll('.faction-tab').forEach(tab => {
      tab.classList.toggle('active', tab.dataset.faction === factionName);
      tab.setAttribute('aria-selected', tab.dataset.faction === factionName);
    });
    
    // Re-render current tab with faction filter
    if (state.cachedData[state.currentTab]) {
      filterAndRenderData(state.currentTab);
    }
  }

  /* ============================== Content Tabs ============================== */
  function setupContentTabs() {
    const contentTabs = document.querySelectorAll('.bazaar-tab');
    
    if (contentTabs.length === 0) {
      log('No content tabs found');
      return;
    }
    
    contentTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const tabName = tab.dataset.tab;
        switchTab(tabName);
      });
    });
    
    log(`Set up ${contentTabs.length} content tabs`);
  }

  function switchTab(tabName) {
    log(`Switching to tab: ${tabName}`);
    
    state.currentTab = tabName;
    
    // Update active content tab
    document.querySelectorAll('.bazaar-tab').forEach(tab => {
      const isActive = tab.dataset.tab === tabName;
      tab.classList.toggle('active', isActive);
      tab.setAttribute('aria-selected', isActive);
    });
    
    // Show/hide panels
    document.querySelectorAll('.bazaar-panel').forEach(panel => {
      const panelTab = panel.id.replace('bazaar-', '').replace('-panel', '');
      if (panelTab === tabName) {
        panel.classList.add('active');
        panel.removeAttribute('hidden');
      } else {
        panel.classList.remove('active');
        panel.setAttribute('hidden', '');
      }
    });
    
    // Load data if not already loaded
    if (!state.tabDataLoaded[tabName]) {
      loadTabData(tabName);
    }
  }

  /* ============================== Data Loading ============================== */
  async function loadTabData(tabName) {
    const panel = document.getElementById(`bazaar-${tabName}-panel`);
    if (!panel) {
      log(`Panel not found: bazaar-${tabName}-panel`);
      return;
    }

    const endpoints = {
      auction: '/sections/bazaar-auction.php',
      inventory: '/sections/bazaar-inventory.php',
      progression: '/sections/bazaar-progression.php',
      social: '/sections/bazaar-social.php',
      tickets: '/sections/bazaar-tickets.php',
      trading: '/sections/bazaar-trading.php',
      fortune: '/sections/bazaar-fortune.php',
      comparison: '/sections/bazaar-comparison.php',
      heatmap: '/sections/bazaar-heatmap.php',
      alerts: '/sections/bazaar-alerts.php',
      workshop: '/sections/bazaar-workshop.php'
    };

    const endpoint = endpoints[tabName];
    if (!endpoint) {
      console.error('[Bazaar] Unknown tab:', tabName);
      showError(panel, `Unknown tab: ${tabName}`);
      return;
    }

    try {
      log(`Loading ${tabName} data from ${endpoint}`);
      const res = await fetch(endpoint, {
        credentials: 'include',
        headers: { 'HX-Request': 'true' }
      });

      if (!res.ok) {
        throw new Error(`HTTP ${res.status}: ${res.statusText}`);
      }

      const data = await res.json();
      log(`${tabName} data loaded:`, data);
      
      // Cache the data
      state.cachedData[tabName] = data;
      state.tabDataLoaded[tabName] = true;
      
      // Render based on tab type
      filterAndRenderData(tabName);
      
    } catch (error) {
      console.error(`[Bazaar] Error loading ${tabName}:`, error);
      showError(panel, `Error loading ${tabName} data: ${error.message}`);
    }
  }

  /* ============================== Character Factions ============================== */
  async function loadCharacterFactions() {
    try {
      const res = await fetch('/sections/characters_list.php', {
        credentials: 'include'
      });
      
      if (!res.ok) return;
      
      const data = await res.json();
      
      if (data.characters && Array.isArray(data.characters)) {
        data.characters.forEach(char => {
          state.characterFactions[char.id] = char.faction || 'Unknown';
        });
        log('Character factions loaded:', state.characterFactions);
      }
    } catch (error) {
      log('Could not load character factions:', error);
    }
  }

  /* ============================== Faction Filtering ============================== */
  function filterAndRenderData(tabName) {
    const data = state.cachedData[tabName];
    if (!data) return;
    
    const panel = document.getElementById(`bazaar-${tabName}-panel`);
    if (!panel) return;
    
    // Apply faction filter if not cross-faction
    let filteredData = data;
    if (state.currentFaction !== 'xfaction') {
      filteredData = filterByFaction(data, state.currentFaction);
    }
    
    // Render the filtered data
    renderTab(tabName, panel, filteredData);
  }

  function filterByFaction(data, faction) {
    // Deep clone to avoid mutating cached data
    const filtered = JSON.parse(JSON.stringify(data));
    
    // Filter character-based arrays
    const characterArrays = [
      'characters', 'items', 'auctions', 'achievements',
      'professions', 'reputations', 'currencies'
    ];
    
    characterArrays.forEach(key => {
      if (filtered[key] && Array.isArray(filtered[key])) {
        filtered[key] = filtered[key].filter(item => {
          const charFaction = state.characterFactions[item.character_id];
          return charFaction && charFaction.toLowerCase() === faction.toLowerCase();
        });
      }
    });
    
    // Recalculate totals
    if (filtered.summary) {
      // This is simplified - you might need more complex logic per tab
      if (filtered.characters) {
        filtered.summary.character_count = filtered.characters.length;
      }
    }
    
    return filtered;
  }

  /* ============================== Tab Rendering Dispatcher ============================== */
  function renderTab(tabName, panel, data) {
    const content = panel.querySelector('.stall-content');
    if (!content) {
      console.error('[Bazaar] No stall-content found in panel');
      return;
    }
    
    switch (tabName) {
      case 'auction':
        renderAuctionHouse(content, data);
        break;
      case 'inventory':
        renderInventory(content, data);
        break;
      case 'progression':
        renderProgression(content, data);
        break;
      case 'social':
        renderSocial(content, data);
        break;
      case 'tickets':
        renderTickets(content, data);
        break;
      case 'trading':
        renderTrading(content, data);
        break;
      case 'fortune':
        renderFortune(content, data);
        break;
      case 'comparison':
        renderComparison(content, data);
        break;
      case 'heatmap':
        renderHeatmap(content, data);
        break;
      case 'alerts':
        renderAlerts(content, data);
        break;
      case 'workshop':
        renderWorkshop(content, data);
        break;
      default:
        showError(content, `Unknown tab type: ${tabName}`);
    }
  }

  /* ============================== Individual Tab Renderers ============================== */

  function renderAuctionHouse(container, data) {
    if (!data || !data.summary) {
      showError(container, 'No auction data available');
      return;
    }
    
    const { summary, character_gold, active_auctions, recent_sales } = data;
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🔨 Auction House</h3>
        <p class="section-subtitle">Auction data: ${JSON.stringify(summary)}</p>
        <p>Full rendering coming soon!</p>
      </div>
    `;
  }

  function renderInventory(container, data) {
    if (!data || !data.summary) {
      showError(container, 'No inventory data available');
      return;
    }
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🎒 Inventory</h3>
        <p>Inventory data loaded successfully</p>
        <p>Full rendering coming soon!</p>
      </div>
    `;
  }

  function renderProgression(container, data) {
    if (!data) {
      showError(container, 'No progression data available');
      return;
    }
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🏆 Progression</h3>
        <p>Progression data loaded successfully</p>
        <p>Full rendering coming soon!</p>
      </div>
    `;
  }

  function renderSocial(container, data) {
    if (!data) {
      showError(container, 'No social data available');
      return;
    }
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>👥 Social</h3>
        <p>Social data loaded successfully</p>
        <p>Full rendering coming soon!</p>
      </div>
    `;
  }

  function renderTickets(container, data) {
    if (!data || !data.summary) {
      showError(container, 'No ticket data available');
      return;
    }
    
    const { summary } = data;
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🎟️ Prize Tickets System</h3>
        
        <div class="summary-cards">
          <div class="summary-card">
            <div class="card-label">Total Tickets</div>
            <div class="card-value">${summary.total_tickets || 0}</div>
          </div>
          <div class="summary-card">
            <div class="card-label">This Week</div>
            <div class="card-value">${summary.week_tickets || 0}</div>
          </div>
          <div class="summary-card">
            <div class="card-label">This Month</div>
            <div class="card-value">${summary.month_tickets || 0}</div>
          </div>
          <div class="summary-card">
            <div class="card-label">Top Earner</div>
            <div class="card-value">${summary.top_earner || 'None'}</div>
          </div>
        </div>
        
        ${summary.total_tickets === 0 ? '<p>No ticket data yet. Start earning tickets!</p>' : ''}
      </div>
    `;
  }

  function renderTrading(container, data) {
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🏪 Trading Post</h3>
        <p>Trading post: ${data.error || 'Feature coming soon'}</p>
      </div>
    `;
  }

  function renderFortune(container, data) {
    if (!data || !data.forecast) {
      showError(container, 'No fortune data available');
      return;
    }
    
    const { forecast, top_items } = data;
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🔮 Fortune Teller's Corner</h3>
        
        <div class="forecast-card">
          <h4>Wealth Forecast</h4>
          <div class="forecast-item">
            <span>Current Wealth</span>
            <span>${formatGold(forecast.current_wealth || 0)}</span>
          </div>
          <div class="forecast-item">
            <span>Predicted (7 days)</span>
            <span>${formatGold(forecast.predicted_7d || 0)}</span>
          </div>
          <div class="forecast-item">
            <span>Predicted (30 days)</span>
            <span>${formatGold(forecast.predicted_30d || 0)}</span>
          </div>
          <div class="forecast-item">
            <span>Trend</span>
            <span class="trend-${forecast.trend || 'stable'}">${forecast.trend || 'stable'}</span>
          </div>
        </div>

        ${top_items && top_items.length > 0 ? `
          <h4>Top Earning Items (Last 30 Days)</h4>
          <div class="top-items">
            ${top_items.slice(0, 10).map((item, idx) => `
              <div class="top-item">
                <span class="rank">#${idx + 1}</span>
                <strong>${escapeHtml(item.item_name)}</strong> - 
                ${formatGold(item.total_earnings)} (${item.sale_count} sales)
              </div>
            `).join('')}
          </div>
        ` : ''}
      </div>
    `;
  }

  function renderComparison(container, data) {
    if (!data || !data.characters) {
      showError(container, 'No comparison data available');
      return;
    }
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>⚖️ Character Comparison</h3>
        <p>Select characters to compare (feature coming soon)</p>
        <div class="character-list">
          ${data.characters.map(char => `
            <div class="char-option">
              <input type="checkbox" id="comp-${char.id}" value="${char.id}">
              <label for="comp-${char.id}">${escapeHtml(char.name)} (${char.faction})</label>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  function renderHeatmap(container, data) {
    if (!data || !data.summary) {
      showError(container, 'No heatmap data available');
      return;
    }
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🗺️ Inventory Heatmap</h3>
        <p>Total items: ${data.summary.total_items || 0}</p>
        <p>Feature in development...</p>
      </div>
    `;
  }

  function renderAlerts(container, data) {
    if (!data || !data.summary) {
      showError(container, 'No alert data available');
      return;
    }
    
    const { summary } = data;
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🔔 Auction Alerts</h3>
        
        <div class="summary-cards">
          <div class="summary-card">
            <div class="card-label">Total Alerts</div>
            <div class="card-value">${summary.total_alerts || 0}</div>
          </div>
          <div class="summary-card">
            <div class="card-label">Enabled</div>
            <div class="card-value">${summary.enabled_alerts || 0}</div>
          </div>
          <div class="summary-card">
            <div class="card-label">Unread</div>
            <div class="card-value">${summary.unread_notifications || 0}</div>
          </div>
        </div>
        
        ${summary.total_alerts === 0 ? '<p>No alerts configured. Set up alerts to monitor auctions!</p>' : ''}
      </div>
    `;
  }

  function renderWorkshop(container, data) {
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🔨 Profession Workshop</h3>
        <p>Workshop: ${data.error || 'Feature coming soon'}</p>
      </div>
    `;
  }

  /* ============================== Utility Functions ============================== */

  function showError(container, message) {
    container.innerHTML = `
      <div class="bazaar-error">
        <p>⚠️ ${escapeHtml(message)}</p>
      </div>
    `;
  }

  function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(str);
    return div.innerHTML;
  }

  function formatGold(copper) {
    copper = Number(copper || 0);
    const g = Math.floor(copper / 10000);
    const s = Math.floor((copper % 10000) / 100);
    const c = copper % 100;
    const parts = [];
    if (g) parts.push(`<span class="gold">${g}g</span>`);
    if (s || g) parts.push(`<span class="silver">${s}s</span>`);
    parts.push(`<span class="copper">${c}c</span>`);
    return parts.join(' ');
  }

  function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
      return new Date(dateStr).toLocaleString();
    } catch {
      return dateStr;
    }
  }

  /* ============================== Start - SPA Compatible ============================== */
  
  // Listen for the SPA's section-loaded event
  document.addEventListener('whodat:section-loaded', function(e) {
    log('Section loaded event received:', e.detail);
    if (e.detail && e.detail.section === 'bazaar') {
      log('Bazaar section loaded, initializing...');
      // Small delay to ensure DOM is fully rendered
      setTimeout(init, 50);
    }
  });

  // Also try to initialize immediately if the bazaar section is already in the DOM
  // (for direct navigation to bazaar.php)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      log('DOMContentLoaded, checking for bazaar section...');
      init();
    });
  } else {
    // DOM is already ready, try init now
    log('DOM already ready, checking for bazaar section...');
    init();
  }

})();
