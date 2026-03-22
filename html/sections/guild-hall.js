// sections/guild-hall.js - Guild Hall JavaScript Controller
// Handles guild selection, tab switching, day/night mode, and data loading

(function() {
  'use strict';

  /* ============================== State Management ============================== */
  const state = {
    currentGuildId: null,
    currentTab: 'treasury',
    cachedData: {},
    tabDataLoaded: {},
    initialized: false,
    isNight: false
  };

  const DEBUG = true;
  const log = DEBUG ? console.log.bind(console, '[Guild Hall]') : () => {};

  /* ============================== Initialization ============================== */
  function init() {
    // Check if guild hall section exists in DOM
    const guildHallSection = document.getElementById('tab-guild-hall');
    if (!guildHallSection) {
      log('Guild Hall section not in DOM yet, skipping init');
      return false;
    }

    // Prevent double initialization
    if (state.initialized) {
      log('Guild Hall already initialized, skipping');
      return false;
    }

    log('Initializing Guild Hall');
    
    // Apply full-page theme
    document.body.classList.add('guild-hall-active');
    
    // Set day/night theme
    const hour = new Date().getHours();
    const isNight = hour >= 18 || hour < 6;
    state.isNight = isNight;
    document.documentElement.setAttribute('data-tavern-theme', isNight ? 'night' : 'day');
    log(`Theme set to: ${isNight ? 'night' : 'day'}`);
    
    // Set up day/night toggle
    setupDayNightToggle();
    
    // Set up guild tabs (if multiple guilds)
    setupGuildTabs();
    
    // Set up content tabs
    setupContentTabs();
    
    // Get initial guild ID
    const firstGuildTab = document.querySelector('.guild-tab');
    if (firstGuildTab) {
      state.currentGuildId = firstGuildTab.dataset.guildId;
    } else {
      // Single guild - get from PHP
      const guildHallSection = document.getElementById('tab-guild-hall');
      const guildIdMeta = guildHallSection?.dataset?.guildId;
      if (guildIdMeta) {
        state.currentGuildId = guildIdMeta;
      }
    }
    
    // Load initial tab data
    if (state.currentGuildId) {
      loadTabData('treasury');
    }
    
    state.initialized = true;
    log('Initialization complete');
    return true;
  }
  
  /* ============================== Cleanup ============================== */
  function cleanup() {
    // Remove full-page theme when leaving
    document.body.classList.remove('guild-hall-active');
    document.documentElement.removeAttribute('data-tavern-theme');
    state.initialized = false;
    log('Guild Hall cleanup complete');
  }

  /* ============================== Day/Night Toggle ============================== */
  function setupDayNightToggle() {
    const toggleButton = document.getElementById('guild-hall-day-night-toggle');
    
    if (!toggleButton) {
      log('Day/night toggle button not found');
      return;
    }
    
    toggleButton.addEventListener('click', () => {
      const newTheme = state.isNight ? 'day' : 'night';
      setTheme(newTheme);
      localStorage.setItem('guildHallTheme', newTheme);
    });
    
    log('Day/night toggle set up');
  }

  function setTheme(theme) {
    state.isNight = theme === 'night';
    document.documentElement.setAttribute('data-tavern-theme', theme);
    log(`Theme set to: ${theme}`);
  }

  /* ============================== Guild Tabs ============================== */
  function setupGuildTabs() {
    const guildTabs = document.querySelectorAll('.guild-tab');
    
    if (guildTabs.length === 0) {
      log('No guild tabs found (single guild mode)');
      return;
    }
    
    guildTabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const guildId = tab.dataset.guildId;
        switchGuild(guildId);
      });
    });
    
    log(`Set up ${guildTabs.length} guild tabs`);
  }

  function switchGuild(guildId) {
    log(`Switching to guild: ${guildId}`);
    
    state.currentGuildId = guildId;
    
    // Update active guild tab
    document.querySelectorAll('.guild-tab').forEach(tab => {
      tab.classList.toggle('active', tab.dataset.guildId === guildId);
      tab.setAttribute('aria-selected', tab.dataset.guildId === guildId);
    });
    
    // Clear cached data for new guild
    state.cachedData = {};
    state.tabDataLoaded = {};
    
    // Reload current tab with new guild
    loadTabData(state.currentTab);
  }

  /* ============================== Content Tabs ============================== */
  function setupContentTabs() {
    const contentTabs = document.querySelectorAll('.hall-tab');
    
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
    document.querySelectorAll('.hall-tab').forEach(tab => {
      tab.classList.toggle('active', tab.dataset.tab === tabName);
      tab.setAttribute('aria-selected', tab.dataset.tab === tabName);
    });
    
    // Show/hide panels
    document.querySelectorAll('.hall-panel').forEach(panel => {
      const panelTab = panel.id.replace('hall-', '').replace('-panel', '');
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
    if (!state.currentGuildId) {
      log('No guild ID available');
      return;
    }

    const panel = document.getElementById(`hall-${tabName}-panel`);
    if (!panel) {
      log(`Panel not found: hall-${tabName}-panel`);
      return;
    }

    const content = panel.querySelector('.room-content');
    if (!content) {
      log(`Content area not found in panel: ${tabName}`);
      return;
    }

    // Show loading state
    content.innerHTML = `
      <div class="tavern-loading">
        <div class="tavern-spinner">🍺</div>
        <div>Loading ${tabName.charAt(0).toUpperCase() + tabName.slice(1)} Data...</div>
      </div>
    `;

    log(`Loading data for tab: ${tabName}`);

    try {
      const dataUrl = `/sections/guild-hall-${tabName}-data.php?guild_id=${state.currentGuildId}`;
      const response = await fetch(dataUrl);

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
      }

      const data = await response.json();
      log(`Data loaded for ${tabName}:`, data);

      // Cache the data
      state.cachedData[tabName] = data;
      state.tabDataLoaded[tabName] = true;

      // Render the appropriate tab
      switch (tabName) {
        case 'treasury':
          renderTreasury(content, data);
          break;
        case 'vault':
          renderVault(content, data);
          break;
        case 'logs':
          renderLogs(content, data);
          break;
        case 'business':
          renderBusiness(content, data);
          break;
        case 'members':
          renderMembers(content, data);
          break;
        default:
          content.innerHTML = '<div class="muted">Tab rendering not implemented yet.</div>';
      }

    } catch (error) {
      log(`Error loading ${tabName} data:`, error);
      content.innerHTML = `
        <div class="error-message">
          <p>❌ Failed to load ${tabName} data</p>
          <p>${error.message}</p>
        </div>
      `;
    }
  }

  /* ============================== Tab Rendering Functions ============================== */
  
  function renderTreasury(container, data) {
    log('Rendering treasury data');

    const netChange = data.net_change !== undefined
      ? data.net_change
      : (data.total_deposits || 0) - (data.total_withdrawals || 0);

    const netColor = netChange >= 0 ? '#4ade80' : '#f87171';
    const dayCount = data.timeline_days || 0;
    const dayLabel = dayCount > 0 ? `${dayCount} day${dayCount !== 1 ? 's' : ''} tracked` : 'balance-based';

    const html = `
      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-label">Current Balance</div>
          <div class="stat-value">${formatGold(data.current_balance || 0)}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Deposits</div>
          <div class="stat-value">${formatGold(data.total_deposits || 0)}</div>
          <div class="stat-subvalue">${dayLabel}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Withdrawals</div>
          <div class="stat-value">${formatGold(data.total_withdrawals || 0)}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Net Change</div>
          <div class="stat-value" style="color:${netColor}">${netChange >= 0 ? '+' : ''}${formatGold(netChange)}</div>
        </div>
      </div>

      <h3>Money Flow</h3>
      <div id="treasury-chart"></div>
    `;

    container.innerHTML = html;
    
    // Render chart if timeline data available
    if (data.timeline && data.timeline.length > 0) {
      renderTreasuryChart(data);
    } else {
      const chartContainer = document.getElementById('treasury-chart');
      if (chartContainer) {
        chartContainer.innerHTML = '<div class="muted">Upload more WhoDAT files to generate chart data</div>';
      }
    }
  }

  function renderVault(container, data) {
    log('Rendering vault data');
    
    if (!data.tabs || data.tabs.length === 0) {
      container.innerHTML = '<div class="muted">No guild bank data available.</div>';
      return;
    }

    // ---- Tab nav buttons ----
    const nav = document.createElement('div');
    nav.className = 'bank-tabs-nav';
    data.tabs.forEach((tab, index) => {
      const btn = document.createElement('button');
      btn.className = 'bank-tab-button' + (index === 0 ? ' active' : '');
      btn.dataset.tabIndex = index;
      btn.innerHTML = `<img src="${tab.icon || '/icons/default-tab.png'}" alt="${tab.name}" class="bank-tab-icon"><span>${tab.name}</span>`;
      btn.addEventListener('click', () => window.switchBankTab(index));
      nav.appendChild(btn);
    });

    // ---- Grid container ----
    const gridContainer = document.createElement('div');
    gridContainer.id = 'bank-grid-container';
    gridContainer.appendChild(renderBankTabEl(data.tabs[0]));

    container.innerHTML = '';
    container.appendChild(nav);
    container.appendChild(gridContainer);

    // Store tabs data globally for tab switching
    window.guildBankTabs = data.tabs;
  }

  // Global function for bank tab switching
  window.switchBankTab = function(tabIndex) {
    // Update active button
    document.querySelectorAll('.bank-tab-button').forEach((btn, i) => {
      btn.classList.toggle('active', i === tabIndex);
    });
    
    // Render tab contents — DOM-based so tooltips stay live
    const container = document.getElementById('bank-grid-container');
    container.replaceChildren(renderBankTabEl(window.guildBankTabs[tabIndex]));
  };

  // DOM-based bank tab renderer — returns a grid element with tooltips attached
  function renderBankTabEl(tab) {
    const grid = document.createElement('div');
    grid.className = 'bank-grid';

    // WoW guild bank has 98 slots per tab (14 columns × 7 rows)
    for (let i = 1; i <= 98; i++) {
      const item = tab.items?.find(it => it.slot === i);

      const slot = document.createElement('div');

      if (item) {
        slot.className = `bank-slot quality-${item.quality || 0}`;
        slot.innerHTML = `
          <img src="${item.icon || '/icons/default.png'}" alt="${item.name}" class="item-icon">
          ${item.count > 1 ? `<span class="item-count">${item.count}</span>` : ''}
        `;

        // Attach Wowhead tooltip — replaces old native title attribute
        if (window.WDTooltip && (item.link || item.item_id)) {
          WDTooltip.attach(slot, { link: item.link, item_id: item.item_id, name: item.name, icon: item.icon }, null);
        } else {
          slot.title = item.name + (item.count > 1 ? ` (x${item.count})` : '');
        }
      } else {
        slot.className = 'bank-slot empty';
      }

      grid.appendChild(slot);
    }

    return grid;
  }

  // Legacy string-based wrapper kept in case any other callers reference renderBankTab
  function renderBankTab(tab) {
    return renderBankTabEl(tab).outerHTML;
  }

  function renderLogs(container, data) {
    log('Rendering logs data');
    
    container.innerHTML = `
      <div class="tavern-controls">
        <input type="text" 
               class="tavern-search" 
               id="log-search" 
               placeholder="Search for items, players, or actions...">
        <button class="tavern-button" onclick="window.searchLogs()">🔍 Search</button>
        <button class="tavern-button" onclick="window.clearLogSearch()">Clear</button>
      </div>

      <h3>Item Transactions</h3>
      <table class="tavern-table" id="item-logs-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Player</th>
            <th>Action</th>
            <th>Item</th>
            <th>Count</th>
            <th>Tab</th>
          </tr>
        </thead>
        <tbody id="item-logs-tbody"></tbody>
      </table>

      <h3 style="margin-top: 30px;">Money Transactions</h3>
      <table class="tavern-table" id="money-logs-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Player</th>
            <th>Action</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody id="money-logs-tbody"></tbody>
      </table>
    `;

    // Insert item log rows via DOM so WDTooltip event listeners survive
    appendItemLogRows(document.getElementById('item-logs-tbody'), data.item_logs || []);

    // Money logs have no item data — innerHTML is fine
    document.getElementById('money-logs-tbody').innerHTML =
      renderLogRows(data.money_logs || [], 'money');

    // Store full logs for searching
    window.guildLogsData = {
      itemLogs: data.item_logs || [],
      moneyLogs: data.money_logs || []
    };
  }

  // DOM-based item log row inserter — preserves WDTooltip event listeners
  function appendItemLogRows(tbody, logs) {
    tbody.innerHTML = '';
    if (!logs || logs.length === 0) {
      tbody.innerHTML = '<tr><td colspan="6" class="muted">No logs found</td></tr>';
      return;
    }
    logs.forEach(log => {
      const date = new Date(log.ts * 1000).toLocaleString();
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${date}</td>
        <td>${log.player_name || 'Unknown'}</td>
        <td>${log.type || 'Unknown'}</td>
        <td class="log-item-name-cell"></td>
        <td>${log.count || 1}</td>
        <td>${log.tab !== null && log.tab !== undefined ? 'Tab ' + log.tab : 'N/A'}</td>
      `;
      const itemCell = tr.querySelector('.log-item-name-cell');
      itemCell.textContent = log.item_name || 'Unknown';
      if (window.WDTooltip && (log.item_link || log.item_id)) {
        WDTooltip.attach(itemCell, { link: log.item_link, item_id: log.item_id, name: log.item_name }, null);
      }
      tbody.appendChild(tr);
    });
  }

  // Global search functions for logs
  window.searchLogs = function() {
    const searchTerm = document.getElementById('log-search').value.toLowerCase();
    
    if (!searchTerm) {
      return;
    }
    
    // Filter item logs
    const filteredItemLogs = window.guildLogsData.itemLogs.filter(log => 
      log.item_name?.toLowerCase().includes(searchTerm) ||
      log.player_name?.toLowerCase().includes(searchTerm) ||
      log.type?.toLowerCase().includes(searchTerm)
    );
    
    // Filter money logs
    const filteredMoneyLogs = window.guildLogsData.moneyLogs.filter(log =>
      log.player_name?.toLowerCase().includes(searchTerm) ||
      log.type?.toLowerCase().includes(searchTerm)
    );
    
    // Item logs — DOM-based to preserve tooltip listeners
    appendItemLogRows(document.querySelector('#item-logs-tbody'), filteredItemLogs);
    // Money logs — no tooltips needed, innerHTML is fine
    document.querySelector('#money-logs-tbody').innerHTML =
      renderLogRows(filteredMoneyLogs, 'money');
  };

  window.clearLogSearch = function() {
    document.getElementById('log-search').value = '';
    appendItemLogRows(document.querySelector('#item-logs-tbody'), window.guildLogsData.itemLogs);
    document.querySelector('#money-logs-tbody').innerHTML =
      renderLogRows(window.guildLogsData.moneyLogs, 'money');
  };

  // String-based renderer for money log rows (no tooltip needed)
  // Item log rows use appendItemLogRows() for DOM-based rendering with tooltips.
  function renderLogRows(logs, type) {
    if (!logs || logs.length === 0) {
      return `<tr><td colspan="${type === 'money' ? 4 : 6}" class="muted">No logs found</td></tr>`;
    }
    return logs.map(log => {
      const date = new Date(log.ts * 1000).toLocaleString();
      return `
        <tr>
          <td>${date}</td>
          <td>${log.player_name || 'Unknown'}</td>
          <td>${log.type || 'Unknown'}</td>
          <td>${formatGold(log.amount_copper || 0)}</td>
        </tr>
      `;
    }).join('');
  }

  function renderBusiness(container, data) {
    log('Rendering business data');
    
    const html = `
      <h3>Bank Alts</h3>
      <div class="tavern-controls">
        <select id="bank-alt-selector" class="tavern-search">
          <option value="">Select a character to designate as bank alt...</option>
          ${(data.available_characters || []).map(char => `
            <option value="${char.character_id}">${char.character_name}</option>
          `).join('')}
        </select>
        <button class="tavern-button primary" onclick="window.addBankAlt()">Add Bank Alt</button>
      </div>

      <div id="bank-alts-list">
        ${renderBankAltsList(data.bank_alts || [])}
      </div>

      <div style="margin-top: 30px;">
        <h3>Incoming (Sales - All Time)</h3>
        <div id="incoming-chart" class="business-chart-container"></div>
      </div>

      <div style="margin-top: 20px;">
        <h3>Outgoing (Postings - All Time)</h3>
        <div id="outgoing-chart" class="business-chart-container"></div>
      </div>

      <h3 style="margin-top: 30px;">Combined Auction Activity</h3>
      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-label">Active Auctions</div>
          <div class="stat-value">${data.auction_stats?.active_count || 0}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Posted Value</div>
          <div class="stat-value">${formatGold(data.auction_stats?.total_value || 0)}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Successful Sales</div>
          <div class="stat-value">${data.auction_stats?.sold_count || 0}</div>
          <div class="stat-subvalue">${formatGold(data.auction_stats?.sold_value || 0)}</div>
        </div>
      </div>

      <h3>Recent Auction Activity</h3>
      <table class="tavern-table">
        <thead>
          <tr>
            <th>Character</th>
            <th>Item</th>
            <th>Status</th>
            <th>Buyout</th>
            <th>Time Left</th>
          </tr>
        </thead>
        <tbody>
          ${renderAuctionRows(data.recent_auctions || [])}
        </tbody>
      </table>
    `;
    
    container.innerHTML = html;
    
    // Store data for bank alt management
    window.guildBusinessData = data;
    
    // Render business charts if we have chart data
    if (data.chart_data) {
      renderBusinessCharts(data.chart_data);
    }
  }

  window.addBankAlt = async function() {
    const selector = document.getElementById('bank-alt-selector');
    const characterId = selector.value;
    
    if (!characterId) {
      alert('Please select a character');
      return;
    }
    
    try {
      const response = await fetch('/sections/guild-hall-business-data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'add_bank_alt',
          guild_id: state.currentGuildId,
          character_id: characterId
        })
      });
      
      if (!response.ok) throw new Error('Failed to add bank alt');
      
      // Reload business tab
      state.tabDataLoaded['business'] = false;
      loadTabData('business');
      
    } catch (error) {
      log('Error adding bank alt:', error);
      alert('Failed to add bank alt');
    }
  };

  function renderBankAltsList(bankAlts) {
    if (!bankAlts || bankAlts.length === 0) {
      return '<div class="muted">No bank alts designated yet.</div>';
    }
    
    // WoW class colors (matching social.js format)
    const CLASS_COLORS = {
      'WARRIOR': '#C79C6E',
      'PALADIN': '#F58CBA',
      'HUNTER': '#ABD473',
      'ROGUE': '#FFF569',
      'PRIEST': '#FFFFFF',
      'DEATHKNIGHT': '#C41F3B',
      'SHAMAN': '#0070DE',
      'MAGE': '#69CCF0',
      'WARLOCK': '#9482C9',
      'DRUID': '#FF7D0A'
    };
    
    // Helper to darken a color for the border
    const darkenColor = (hex, amount = 0.3) => {
      const num = parseInt(hex.slice(1), 16);
      const r = Math.max(0, ((num >> 16) & 0xFF) * (1 - amount));
      const g = Math.max(0, ((num >> 8) & 0xFF) * (1 - amount));
      const b = Math.max(0, (num & 0xFF) * (1 - amount));
      return `#${Math.round(r).toString(16).padStart(2, '0')}${Math.round(g).toString(16).padStart(2, '0')}${Math.round(b).toString(16).padStart(2, '0')}`;
    };
    
    return `
      <div class="bank-alts-container">
        ${bankAlts.map(alt => {
          // Use class_file (DRUID, WARRIOR, etc.) for color lookup
          const classFile = (alt.class_file || alt.class || '').toUpperCase();
          const bgColor = CLASS_COLORS[classFile] || '#888888';
          const borderColor = darkenColor(bgColor);
          
          return `
            <div class="bank-alt-tag" style="background-color: ${bgColor}; border-color: ${borderColor};">
              <span class="bank-alt-name">${alt.character_name}</span>
              <button class="bank-alt-remove" 
                      onclick="window.removeBankAlt(${alt.character_id})"
                      aria-label="Remove ${alt.character_name}">
                ✕
              </button>
            </div>
          `;
        }).join('')}
      </div>
    `;
  }

  window.removeBankAlt = async function(characterId) {
    if (!confirm('Remove this bank alt?')) {
      return;
    }
    
    try {
      const response = await fetch('/sections/guild-hall-business-data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'remove_bank_alt',
          guild_id: state.currentGuildId,
          character_id: characterId
        })
      });
      
      if (!response.ok) throw new Error('Failed to remove bank alt');
      
      // Reload business tab
      state.tabDataLoaded['business'] = false;
      loadTabData('business');
      
    } catch (error) {
      log('Error removing bank alt:', error);
      alert('Failed to remove bank alt');
    }
  };

  /* ── Business chart colors ─────────────────────────────────────────────── */
  const CHART_CHAR_COLORS = [
    '#FF6B6B', '#4ECDC4', '#FFD93D', '#6BCF7F', '#A8E6CF',
    '#FF8B94', '#B4A7D6', '#FFB6D9', '#C7CEEA', '#FFDAC1'
  ];

  function renderBusinessCharts(chartData) {
    log('Rendering business charts with data:', chartData);
    if (!chartData || !chartData.incoming || !chartData.outgoing) return;

    const incomingContainer = document.getElementById('incoming-chart');
    if (incomingContainer) {
      incomingContainer.innerHTML = '';
      incomingContainer.appendChild(
        buildLineChart(chartData.incoming, '#4CAF50')
      );
    }

    const outgoingContainer = document.getElementById('outgoing-chart');
    if (outgoingContainer) {
      outgoingContainer.innerHTML = '';
      outgoingContainer.appendChild(
        buildLineChart(chartData.outgoing, '#F44336')
      );
    }
  }

  /**
   * Build a DOM SVG line chart with hover tooltips.
   * data: { series: [{label, values[]}], labels: [], points: [{date, breakdown{}}] }
   * totalColor: hex string for the Total / single-char line
   */
  function buildLineChart(data, totalColor) {
    const fallback = document.createElement('div');
    fallback.className = 'muted';
    fallback.textContent = 'No data available';

    if (!data || !data.series || data.series.length === 0 || !data.labels || data.labels.length === 0) {
      return fallback;
    }

    const { series, labels, points } = data;
    const nPoints = labels.length;

    // ── Dimensions ──
    const W = 1000, H = 300;
    const pad = { top: 30, right: 160, bottom: 50, left: 75 };
    const cW = W - pad.left - pad.right;
    const cH = H - pad.top  - pad.bottom;

    // ── Max value across all series for Y scale ──
    let maxVal = 0;
    series.forEach(s => { const m = Math.max(...s.values); if (m > maxVal) maxVal = m; });
    maxVal = Math.ceil(maxVal / 100000) * 100000 || 100000;

    const getX = i  => pad.left + (nPoints > 1 ? (i / (nPoints - 1)) * cW : cW / 2);
    const getY = v  => pad.top  + cH - (v / maxVal) * cH;

    // ── Create SVG ──
    const NS = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
    svg.style.cssText = 'width:100%;height:auto;max-height:300px;overflow:visible';

    const mk = tag => document.createElementNS(NS, tag);

    // Y-axis grid + labels
    const yTicks = 5;
    for (let i = 0; i <= yTicks; i++) {
      const val = Math.round(maxVal * i / yTicks);
      const y   = pad.top + cH - (i / yTicks) * cH;

      const line = mk('line');
      line.setAttribute('x1', pad.left);       line.setAttribute('y1', y);
      line.setAttribute('x2', W - pad.right);  line.setAttribute('y2', y);
      line.setAttribute('stroke', 'rgba(255,255,255,0.1)');
      line.setAttribute('stroke-width', '1');
      svg.appendChild(line);

      const txt = mk('text');
      txt.setAttribute('x', pad.left - 8);
      txt.setAttribute('y', y + 5);
      txt.setAttribute('text-anchor', 'end');
      txt.setAttribute('fill', '#d4af37');
      txt.setAttribute('font-size', '13');
      txt.setAttribute('font-family', 'Arial,sans-serif');
      txt.textContent = formatGoldShort(val);
      svg.appendChild(txt);
    }

    // X-axis labels (thin out to ~10 max)
    const step = Math.ceil(nPoints / 10);
    labels.forEach((label, i) => {
      if (i % step !== 0 && i !== nPoints - 1) return;
      const txt = mk('text');
      txt.setAttribute('x', getX(i));
      txt.setAttribute('y', H - pad.bottom + 22);
      txt.setAttribute('text-anchor', 'middle');
      txt.setAttribute('fill', '#d4af37');
      txt.setAttribute('font-size', '12');
      txt.setAttribute('font-family', 'Arial,sans-serif');
      txt.textContent = label;
      svg.appendChild(txt);
    });

    // ── Series lines ──
    series.forEach((s, si) => {
      const isTotal   = s.label === 'Total';
      const lineColor = isTotal ? totalColor : CHART_CHAR_COLORS[si % CHART_CHAR_COLORS.length];
      const pts       = s.values.map((v, i) => `${getX(i)},${getY(v)}`).join(' ');

      const poly = mk('polyline');
      poly.setAttribute('points', pts);
      poly.setAttribute('fill', 'none');
      poly.setAttribute('stroke', lineColor);
      poly.setAttribute('stroke-width', isTotal ? '4' : '2');
      poly.setAttribute('opacity',      isTotal ? '1'  : '0.7');
      poly.setAttribute('stroke-linejoin', 'round');
      poly.setAttribute('stroke-linecap',  'round');
      svg.appendChild(poly);
    });

    // ── Legend ──
    series.forEach((s, si) => {
      const isTotal   = s.label === 'Total';
      const lineColor = isTotal ? totalColor : CHART_CHAR_COLORS[si % CHART_CHAR_COLORS.length];
      const yPos      = pad.top + si * 22;

      const ln = mk('line');
      ln.setAttribute('x1', W - pad.right + 15); ln.setAttribute('y1', yPos + 2);
      ln.setAttribute('x2', W - pad.right + 42); ln.setAttribute('y2', yPos + 2);
      ln.setAttribute('stroke', lineColor);
      ln.setAttribute('stroke-width', isTotal ? '4' : '2');
      svg.appendChild(ln);

      const txt = mk('text');
      txt.setAttribute('x', W - pad.right + 48);
      txt.setAttribute('y', yPos + 7);
      txt.setAttribute('fill', '#d4af37');
      txt.setAttribute('font-size', '13');
      txt.setAttribute('font-family', 'Arial,sans-serif');
      txt.setAttribute('font-weight', isTotal ? 'bold' : 'normal');
      txt.textContent = s.label;
      svg.appendChild(txt);
    });

    // ── Tooltip element (shared, appended to body) ──
    let ttEl = document.getElementById('biz-chart-tooltip');
    if (!ttEl) {
      ttEl = document.createElement('div');
      ttEl.id = 'biz-chart-tooltip';
      ttEl.style.cssText = [
        'position:fixed',
        'pointer-events:none',
        'z-index:9999',
        'background:rgba(10,14,26,0.95)',
        'border:1px solid rgba(212,175,55,0.4)',
        'border-radius:8px',
        'padding:8px 12px',
        'font-size:13px',
        'color:#d4af37',
        'white-space:nowrap',
        'opacity:0',
        'transition:opacity 0.1s',
        'box-shadow:0 4px 16px rgba(0,0,0,0.5)',
      ].join(';');
      document.body.appendChild(ttEl);
    }

    // ── Invisible hit-area circles at each data point ──
    // One vertical strip per X position covers all series at that index
    for (let i = 0; i < nPoints; i++) {
      const x   = getX(i);
      const pt  = points ? points[i] : null;

      // Visible dot on each series line
      series.forEach((s, si) => {
        const v = s.values[i];
        if (v === 0) return;
        const isTotal   = s.label === 'Total';
        const lineColor = isTotal ? totalColor : CHART_CHAR_COLORS[si % CHART_CHAR_COLORS.length];
        const dot = mk('circle');
        dot.setAttribute('cx', x);
        dot.setAttribute('cy', getY(v));
        dot.setAttribute('r',  '3');
        dot.setAttribute('fill', lineColor);
        dot.setAttribute('opacity', '0.85');
        svg.appendChild(dot);
      });

      // Wide invisible hit strip
      const hit = mk('rect');
      hit.setAttribute('x',       x - 14);
      hit.setAttribute('y',       pad.top);
      hit.setAttribute('width',   '28');
      hit.setAttribute('height',  cH);
      hit.setAttribute('fill',    'transparent');
      hit.style.cursor = 'crosshair';

      hit.addEventListener('mouseenter', (e) => {
        // Build tooltip HTML
        let html = `<div style="font-weight:700;margin-bottom:4px;color:#fff">${labels[i]}</div>`;

        if (pt && pt.breakdown && Object.keys(pt.breakdown).length > 0) {
          Object.entries(pt.breakdown).forEach(([charName, val]) => {
            html += `<div style="display:flex;gap:12px;justify-content:space-between">
              <span style="opacity:0.8">${charName}</span>
              <span style="font-weight:600">${formatGold(val)}</span>
            </div>`;
          });
          // Total if multi-char
          const entries = Object.values(pt.breakdown);
          if (entries.length > 1) {
            const tot = entries.reduce((a, b) => a + b, 0);
            html += `<div style="border-top:1px solid rgba(212,175,55,0.3);margin-top:4px;padding-top:4px;display:flex;gap:12px;justify-content:space-between">
              <span style="font-weight:700">Total</span>
              <span style="font-weight:700">${formatGold(tot)}</span>
            </div>`;
          }
        } else {
          html += '<div style="opacity:0.6">No activity</div>';
        }

        ttEl.innerHTML = html;
        ttEl.style.opacity = '1';
        positionTooltip(e, ttEl);
      });

      hit.addEventListener('mousemove', (e) => positionTooltip(e, ttEl));

      hit.addEventListener('mouseleave', () => {
        ttEl.style.opacity = '0';
      });

      svg.appendChild(hit);
    }

    return svg;
  }

  function positionTooltip(e, el) {
    const margin = 14;
    const tw = el.offsetWidth  || 180;
    const th = el.offsetHeight || 80;
    let x = e.clientX + margin;
    let y = e.clientY - th / 2;
    if (x + tw > window.innerWidth  - 8) x = e.clientX - tw - margin;
    if (y < 8)                           y = 8;
    if (y + th > window.innerHeight - 8) y = window.innerHeight - th - 8;
    el.style.left = x + 'px';
    el.style.top  = y + 'px';
  }

  function formatGoldShort(copper) {
    const gold = Math.floor(copper / 10000);
    if (gold >= 10000) {
      return `${Math.floor(gold / 1000)}k`;
    } else if (gold >= 1000) {
      return `${(gold / 1000).toFixed(1)}k`;
    }
    return `${gold}g`;
  }

  function renderAuctionRows(auctions) {
    if (!auctions || auctions.length === 0) {
      return '<tr><td colspan="5" class="muted">No recent auction activity</td></tr>';
    }
    
    return auctions.map(auction => `
      <tr>
        <td>${auction.character_name || 'Unknown'}</td>
        <td>${auction.item_name || 'Unknown'}</td>
        <td>${auction.status || 'Unknown'}</td>
        <td>${formatGold(auction.buyout || 0)}</td>
        <td>${auction.time_left || 'N/A'}</td>
      </tr>
    `).join('');
  }

  function renderMembers(container, data) {
    log('Rendering members data');
    
    if (!data.members || data.members.length === 0) {
      container.innerHTML = '<div class="muted">No guild members found.</div>';
      return;
    }

    const html = `
      <div class="stat-cards">
        <div class="stat-card">
          <div class="stat-label">Total Members</div>
          <div class="stat-value">${data.members.length}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Online</div>
          <div class="stat-value">${data.members.filter(m => m.online).length}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Average Level</div>
          <div class="stat-value">${Math.round(data.members.reduce((sum, m) => sum + (m.level || 0), 0) / data.members.length)}</div>
        </div>
      </div>

      <div class="tavern-controls">
        <input type="text" 
               class="tavern-search" 
               id="member-search" 
               placeholder="Search members..."
               oninput="window.filterMembers()">
        <select class="tavern-search" id="rank-filter" onchange="window.filterMembers()">
          <option value="">All Ranks</option>
          ${getUniqueRanks(data.members).map(rank => `
            <option value="${rank}">${rank}</option>
          `).join('')}
        </select>
      </div>

      <table class="tavern-table" id="members-table">
        <thead>
          <tr>
            <th onclick="window.sortMembers('name')">Name ↕</th>
            <th onclick="window.sortMembers('level')">Level ↕</th>
            <th onclick="window.sortMembers('class')">Class ↕</th>
            <th onclick="window.sortMembers('rank')">Rank ↕</th>
            <th>Zone</th>
            <th>Note</th>
          </tr>
        </thead>
        <tbody>
          ${renderMemberRows(data.members)}
        </tbody>
      </table>
    `;
    
    container.innerHTML = html;
    
    // Store members data for filtering/sorting
    window.guildMembersData = data.members;
  }

  function getUniqueRanks(members) {
    return [...new Set(members.map(m => m.rank).filter(Boolean))].sort();
  }

  function renderMemberRows(members) {
    return members.map(member => `
      <tr>
        <td>${member.name || 'Unknown'}</td>
        <td>${member.level || '?'}</td>
        <td>${member.class || 'Unknown'}</td>
        <td>${member.rank || 'Member'}</td>
        <td>${member.zone || 'Unknown'}</td>
        <td>${member.note || ''}</td>
      </tr>
    `).join('');
  }

  // Global member filtering/sorting functions
  window.filterMembers = function() {
    const searchTerm = document.getElementById('member-search').value.toLowerCase();
    const rankFilter = document.getElementById('rank-filter').value;
    
    let filtered = window.guildMembersData;
    
    if (searchTerm) {
      filtered = filtered.filter(m => 
        m.name?.toLowerCase().includes(searchTerm) ||
        m.class?.toLowerCase().includes(searchTerm)
      );
    }
    
    if (rankFilter) {
      filtered = filtered.filter(m => m.rank === rankFilter);
    }
    
    document.querySelector('#members-table tbody').innerHTML = renderMemberRows(filtered);
  };

  window.sortMembers = function(column) {
    // Simple toggle sort
    window.guildMembersData.sort((a, b) => {
      const aVal = a[column] || '';
      const bVal = b[column] || '';
      return aVal > bVal ? 1 : -1;
    });
    
    window.filterMembers(); // Re-render with current filters
  };

  /* ============================== Helper Functions ============================== */
  
  function formatGold(copper) {
    // Format: ####g ##s ##c (max 4 gold digits, always show 2 silver, 2 copper)
    const gold = Math.floor(copper / 10000);
    const silver = Math.floor((copper % 10000) / 100);
    const copperRemainder = copper % 100;
    
    // Pad silver and copper to 2 digits
    const silverStr = String(silver).padStart(2, '0');
    const copperStr = String(copperRemainder).padStart(2, '0');
    
    // Gold can be up to 4 digits, but we don't pad it
    return `${gold}g ${silverStr}s ${copperStr}c`;
  }

  function renderRecentTransactions(transactions) {
    if (!transactions || transactions.length === 0) {
      return '<div class="muted">No recent transactions</div>';
    }
    
    return `
      <table class="tavern-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Player</th>
            <th>Type</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
          ${transactions.map(t => `
            <tr>
              <td>${new Date(t.ts * 1000).toLocaleString()}</td>
              <td>${t.player_name || 'Unknown'}</td>
              <td>${t.type || 'Unknown'}</td>
              <td>${formatGold(t.amount_copper || 0)}</td>
            </tr>
          `).join('')}
        </tbody>
      </table>
    `;
  }

// Treasury Chart Renderer - Add to sections/guild-hall.js
// Replace the renderTreasuryChart function

function renderTreasuryChart(data) {
  const container = document.getElementById('treasury-chart');
  if (!container) {
    console.log('[Treasury Chart] Container not found');
    return;
  }
  
  const timeline = data.timeline || [];
  
  if (timeline.length < 2) {
    console.log('[Treasury Chart] Not enough data points:', timeline.length);
    container.innerHTML = '<div class="muted">Not enough data for chart (need at least 2 data points). Found: ' + timeline.length + '</div>';
    return;
  }
  
  // Chart dimensions
  const width = container.clientWidth || 800;
  const height = 300;
  const padding = { top: 20, right: 80, bottom: 50, left: 80 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;
  
  // Find min/max values for scaling
  const balances = timeline.map(d => d.balance || 0);
  const maxBalance = Math.max(...balances);
  const minBalance = Math.min(...balances);
  
  // Add 10% padding to y-axis
  const balanceRange = maxBalance - minBalance || maxBalance || 1;
  const yMax = maxBalance + (balanceRange * 0.1);
  const yMin = Math.max(0, minBalance - (balanceRange * 0.1));
  
  // Helper functions
  const getX = (index) => padding.left + (index / (timeline.length - 1)) * chartWidth;
  const getY = (value) => padding.top + chartHeight - ((value - yMin) / (yMax - yMin)) * chartHeight;
  
  const formatGoldShort = (copper) => {
    const gold = Math.floor(copper / 10000);
    if (gold >= 1000000) return `${(gold / 1000000).toFixed(1)}M`;
    if (gold >= 1000) return `${(gold / 1000).toFixed(1)}K`;
    return `${gold}`;
  };
  
  // Build SVG
  let svg = `
    <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}" 
         style="background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%); border-radius: 12px; font-family: system-ui;">
      <defs>
        <!-- Grid pattern -->
        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
          <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
        </pattern>
        
        <!-- Balance gradient -->
        <linearGradient id="balanceGradient" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" style="stop-color:#10b981;stop-opacity:0.3" />
          <stop offset="100%" style="stop-color:#10b981;stop-opacity:0.05" />
        </linearGradient>
      </defs>
      
      <!-- Grid background -->
      <rect x="${padding.left}" y="${padding.top}" 
            width="${chartWidth}" height="${chartHeight}" 
            fill="url(#grid)" />
      
      <!-- Y-axis labels and grid lines -->
  `;
  
  // Add 5 Y-axis labels
  for (let i = 0; i <= 5; i++) {
    const value = yMin + ((yMax - yMin) * i / 5);
    const y = getY(value);
    
    svg += `
      <line x1="${padding.left - 5}" y1="${y}" 
            x2="${padding.left + chartWidth}" y2="${y}" 
            stroke="rgba(255,255,255,0.1)" stroke-width="1" stroke-dasharray="4,4" />
      <text x="${padding.left - 10}" y="${y + 4}" 
            text-anchor="end" fill="#94a3b8" font-size="12">
        ${formatGoldShort(value)}g
      </text>
    `;
  }
  
  // Build path data for balance line
  let balancePath = '';
  let balanceAreaPath = '';
  timeline.forEach((point, i) => {
    const x = getX(i);
    const y = getY(point.balance || 0);
    
    if (i === 0) {
      balancePath = `M ${x} ${y}`;
      balanceAreaPath = `M ${x} ${padding.top + chartHeight} L ${x} ${y}`;
    } else {
      balancePath += ` L ${x} ${y}`;
      balanceAreaPath += ` L ${x} ${y}`;
    }
  });
  
  // Close area path
  const lastX = getX(timeline.length - 1);
  balanceAreaPath += ` L ${lastX} ${padding.top + chartHeight} Z`;
  
  // Add balance area fill
  svg += `
    <path d="${balanceAreaPath}" 
          fill="url(#balanceGradient)" 
          opacity="0.6" />
  `;
  
  // Add balance line
  svg += `
    <path d="${balancePath}" 
          fill="none" 
          stroke="#10b981" 
          stroke-width="3" 
          stroke-linecap="round" 
          stroke-linejoin="round" />
  `;
  
  // Add data points for balance line
  timeline.forEach((point, i) => {
    const x = getX(i);
    const y = getY(point.balance || 0);
    
    svg += `
      <circle cx="${x}" cy="${y}" r="4" fill="#10b981" stroke="#1e293b" stroke-width="2" />
    `;
  });
  
  // X-axis date labels (show first, middle, last)
  const labelIndices = [0, Math.floor(timeline.length / 2), timeline.length - 1];
  labelIndices.forEach(i => {
    if (timeline[i]) {
      const x = getX(i);
      const date = new Date(timeline[i].ts * 1000);
      const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      
      svg += `
        <text x="${x}" y="${height - 20}" 
              text-anchor="middle" fill="#94a3b8" font-size="12">
          ${dateStr}
        </text>
      `;
    }
  });
  
  // Simple legend
  svg += `
    <!-- Balance legend -->
    <line x1="${width - padding.right + 10}" y1="30" 
          x2="${width - padding.right + 40}" y2="30" 
          stroke="#10b981" stroke-width="3" />
    <text x="${width - padding.right + 45}" y="35" 
          fill="#94a3b8" font-size="13" font-weight="600">
      Guild Balance
    </text>
    
    <!-- Chart title -->
    <text x="${padding.left}" y="15" 
          fill="#f1f5f9" font-size="14" font-weight="600">
      💰 Guild Bank Balance Over Time
    </text>
  `;
  
  svg += '</svg>';
  
  container.innerHTML = svg;
  
  // Add hover tooltips
  addChartTooltips(container, timeline, getX, getY);
}

function addChartTooltips(container, timeline, getX, getY) {
  const svg = container.querySelector('svg');
  if (!svg) return;
  
  // Create tooltip element
  const tooltip = document.createElement('div');
  tooltip.style.position = 'absolute';
  tooltip.style.background = 'rgba(15, 23, 42, 0.95)';
  tooltip.style.color = '#f1f5f9';
  tooltip.style.padding = '8px 12px';
  tooltip.style.borderRadius = '8px';
  tooltip.style.fontSize = '12px';
  tooltip.style.pointerEvents = 'none';
  tooltip.style.opacity = '0';
  tooltip.style.transition = 'opacity 0.2s';
  tooltip.style.border = '1px solid rgba(255,255,255,0.1)';
  tooltip.style.boxShadow = '0 4px 12px rgba(0,0,0,0.5)';
  tooltip.style.zIndex = '1000';
  container.style.position = 'relative';
  container.appendChild(tooltip);
  
  const formatGold = (copper) => {
    const gold = Math.floor(copper / 10000);
    const silver = Math.floor((copper % 10000) / 100);
    const copperRem = copper % 100;
    return `${gold}g ${String(silver).padStart(2, '0')}s ${String(copperRem).padStart(2, '0')}c`;
  };
  
  // Add invisible hover areas for each data point
  timeline.forEach((point, i) => {
    const x = getX(i);
    const y = getY(point.balance || 0);
    
    const hoverCircle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
    hoverCircle.setAttribute('cx', x);
    hoverCircle.setAttribute('cy', y);
    hoverCircle.setAttribute('r', '12');
    hoverCircle.setAttribute('fill', 'transparent');
    hoverCircle.setAttribute('cursor', 'pointer');
    
    hoverCircle.addEventListener('mouseenter', (e) => {
      const date = new Date(point.ts * 1000).toLocaleDateString();
      const time = new Date(point.ts * 1000).toLocaleTimeString();
      const balance = formatGold(point.balance || 0);

      let extras = '';
      if (point.deposits > 0) {
        extras += `<div style="color:#4ade80;">▲ +${formatGold(point.deposits)}</div>`;
      }
      if (point.withdrawals > 0) {
        extras += `<div style="color:#f87171;">▼ -${formatGold(point.withdrawals)}</div>`;
      }
      
      tooltip.innerHTML = `
        <div style="font-weight: 600; margin-bottom: 4px;">${date}</div>
        <div style="font-size: 11px; color: #94a3b8; margin-bottom: 4px;">${time}</div>
        <div style="color: #10b981;">Balance: ${balance}</div>
        ${extras}
      `;
      
      const rect = container.getBoundingClientRect();
      tooltip.style.left = (x - 60) + 'px';
      tooltip.style.top = (y - 80) + 'px';
      tooltip.style.opacity = '1';
    });
    
    hoverCircle.addEventListener('mouseleave', () => {
      tooltip.style.opacity = '0';
    });
    
    svg.appendChild(hoverCircle);
  });
}
  /* ============================== Auto-init on section load ============================== */
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'guild-hall') {
      log('Section loaded event received');
      init();
    }
  });

  // Cleanup when navigating away
  document.addEventListener('whodat:section-unloaded', (event) => {
    if (event?.detail?.section === 'guild-hall') {
      cleanup();
    }
  });

  // Also try immediate init if section already exists
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (document.getElementById('tab-guild-hall')) init();
    });
  } else {
    if (document.getElementById('tab-guild-hall')) init();
  }

  log('Guild Hall module loaded');
})();