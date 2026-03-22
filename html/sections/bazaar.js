// sections/bazaar.js - Complete Darkmoon Bazaar JavaScript with Full Rendering
// SPA-compatible with all tabs fully implemented

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

  const DEBUG = true; // Enable for faction debugging
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
    
    // Apply full-page Bazaar theme
    document.body.classList.add('bazaar-active');
    
    // Set day/night theme
    const hour = new Date().getHours();
    const isNight = hour >= 18 || hour < 6;
    document.documentElement.setAttribute('data-dmf-theme', isNight ? 'night' : 'day');
    window.DMF_IS_NIGHT = isNight;
    log(`Theme set to: ${isNight ? 'night' : 'day'}`);
    
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
  
  /* ============================== Cleanup ============================== */
  function cleanup() {
    // Remove full-page Bazaar theme when leaving
    document.body.classList.remove('bazaar-active');
    state.initialized = false;
    log('Bazaar cleanup complete');
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
      timeline: '/sections/bazaar-timeline.php',
      fortune: '/sections/bazaar-fortune.php',
      comparison: '/sections/bazaar-comparison.php',
      heatmap: '/sections/bazaar-heatmap.php',
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
          const charId = char.id || char.character_id;
          const faction = char.faction || 'Unknown';
          // Store under both possible keys for compatibility
          state.characterFactions[charId] = faction;
          if (char.character_id && char.character_id !== charId) {
            state.characterFactions[char.character_id] = faction;
          }
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
    
    log(`Filtering data for faction: ${faction}`);
    
    // Filter character-based arrays
    const characterArrays = [
      'characters', 'items', 'auctions', 'achievements',
      'professions', 'reputations', 'currencies', 'character_gold',
      'active_auctions', 'recent_sales', 'lockouts', 'recent_dungeons'
    ];
    
    characterArrays.forEach(key => {
      if (filtered[key] && Array.isArray(filtered[key])) {
        const originalLength = filtered[key].length;
        filtered[key] = filtered[key].filter(item => {
          // Try multiple possible ID fields
          const charId = item.character_id || item.id || item.char_id;
          if (!charId) {
            log(`Item missing character ID in ${key}:`, item);
            return false;
          }
          
          // Look up faction
          const charFaction = state.characterFactions[charId];
          
          if (!charFaction || charFaction === 'Unknown') {
            log(`No faction found for character ID ${charId} in ${key}`);
            return false;
          }
          
          const matches = charFaction.toLowerCase() === faction.toLowerCase();
          return matches;
        });
        
        if (originalLength !== filtered[key].length) {
          log(`${key}: filtered from ${originalLength} to ${filtered[key].length} items`);
        }
      }
    });
    
    // Recalculate summary totals if filtering affected counts
    if (filtered.summary) {
      if (filtered.character_gold) {
        filtered.summary.total_gold = filtered.character_gold.reduce((sum, char) => sum + (char.gold || 0), 0);
      }
      if (filtered.active_auctions) {
        filtered.summary.active_auctions = filtered.active_auctions.length;
      }
      if (filtered.recent_sales) {
        filtered.summary.sold_24h = filtered.recent_sales.length;
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
      case 'timeline':
        renderTimeline(content, data);
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
      case 'workshop':
        renderWorkshop(content, data);
        break;
      default:
        showError(content, `No renderer for tab: ${tabName}`);
    }
  }

  /* ============================== AUCTION HOUSE RENDERER ============================== */
  function renderAuctionHouse(container, data) {
    if (!data || !data.summary) {
      showError(container, 'No auction data available');
      return;
    }
    
    const { summary, character_gold, active_auctions, recent_sales } = data;
    
    // ---- Static summary section (innerHTML is fine — no tooltippable elements) ----
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🔨 Auction House Overview</h3>
        
        <div class="summary-cards">
          <div class="summary-card gold">
            <div class="card-label">Total Gold</div>
            <div class="card-value">${formatGold(summary.total_gold || 0)}</div>
          </div>
          <div class="summary-card">
            <div class="card-label">Active Auctions</div>
            <div class="card-value">${summary.active_auctions || 0}</div>
          </div>
          <div class="summary-card">
            <div class="card-label">Sold (24h)</div>
            <div class="card-value">${summary.sold_24h || 0}</div>
          </div>
          <div class="summary-card success">
            <div class="card-label">Earnings (7d)</div>
            <div class="card-value">${formatGold(summary.earnings_7d || 0)}</div>
          </div>
        </div>

        ${character_gold && character_gold.length > 0 ? `
          <h4>💰 Gold by Character</h4>
          <div class="character-list">
            ${character_gold.map(char => `
              <div class="character-item">
                <span class="char-name">${escapeHtml(char.name)}</span>
                <span class="char-gold">${formatGold(char.gold)}</span>
              </div>
            `).join('')}
          </div>
        ` : ''}

        ${active_auctions && active_auctions.length > 0 ? `
          <h4>📋 Active Auctions (${active_auctions.length})</h4>
          <div class="auction-list" id="bazaar-active-auctions-list"></div>
          ${active_auctions.length > 20 ? `<p class="note">Showing 20 of ${active_auctions.length} auctions</p>` : ''}
        ` : '<p class="empty-state">No active auctions</p>'}

        ${recent_sales && recent_sales.length > 0 ? `
          <h4>✅ Recent Sales (Last 24h)</h4>
          <div class="sales-list" id="bazaar-recent-sales-list"></div>
        ` : '<p class="empty-state">No recent sales</p>'}
      </div>
    `;

    // ---- Active Auctions — DOM-built so WDTooltip.attach() can be called ----
    if (active_auctions && active_auctions.length > 0) {
      const auctionList = container.querySelector('#bazaar-active-auctions-list');
      if (auctionList) {
        active_auctions.slice(0, 20).forEach(auction => {
          const item = document.createElement('div');
          item.className = 'auction-item';
          item.innerHTML = `
            <div class="auction-header">
              <strong>${escapeHtml(auction.item_name)}</strong>
              <span class="auction-char">${escapeHtml(auction.character_name)}</span>
            </div>
            <div class="auction-details">
              <span>Qty: ${auction.quantity}</span>
              <span>Unit: ${formatGold(auction.unit_price)}</span>
              <span class="auction-total">Total: ${formatGold(auction.total_price)}</span>
              <span class="auction-expires">Expires: ${formatDate(auction.expires_at)}</span>
            </div>
          `;
          if (window.WDTooltip && (auction.link || auction.item_id)) {
            WDTooltip.attach(item, { link: auction.link, item_id: auction.item_id, name: auction.item_name }, null);
          }
          auctionList.appendChild(item);
        });
      }
    }

    // ---- Recent Sales — DOM-built so WDTooltip.attach() can be called ----
    if (recent_sales && recent_sales.length > 0) {
      const salesList = container.querySelector('#bazaar-recent-sales-list');
      if (salesList) {
        recent_sales.forEach(sale => {
          const item = document.createElement('div');
          item.className = 'sale-item';
          item.innerHTML = `
            <div class="sale-header">
              <strong>${escapeHtml(sale.item_name)}</strong>
              <span class="sale-char">${escapeHtml(sale.character_name)}</span>
            </div>
            <div class="sale-details">
              <span>Qty: ${sale.quantity}</span>
              <span class="sale-price">${formatGold(sale.total_price)}</span>
              <span class="sale-time">${formatDate(sale.sold_at)}</span>
            </div>
          `;
          if (window.WDTooltip && (sale.link || sale.item_id)) {
            WDTooltip.attach(item, { link: sale.link, item_id: sale.item_id, name: sale.item_name }, null);
          }
          salesList.appendChild(item);
        });
      }
    }
  }

/* ============================== INVENTORY RENDERER ============================== */
  
  function renderInventory(container, data) {
    if (!data || (!data.characters && !data.guilds)) {
      showError(container, 'No inventory data available');
      return;
    }

    const { characters, guilds, summary } = data;
    const allEntities = [
      ...characters.map(c => ({...c, type: 'character'})),
      ...guilds.map(g => ({...g, type: 'guild'}))
    ];

    // Render static header/filters via innerHTML (no tooltippable elements here)
    container.innerHTML = `
      <div class="inventory-container">
        <div class="inventory-header">
          <div>
            <div class="inventory-title">🎒 Inventory Browser</div>
            <div class="inventory-subtitle">Search items across all your characters and guild banks</div>
          </div>
          <div class="inventory-stats">
            <div class="inventory-stat">
              <div class="inventory-stat-value">${summary.total_characters}</div>
              <div class="inventory-stat-label">Characters</div>
            </div>
            <div class="inventory-stat">
              <div class="inventory-stat-value">${summary.total_guilds}</div>
              <div class="inventory-stat-label">Guilds</div>
            </div>
            <div class="inventory-stat">
              <div class="inventory-stat-value">${summary.unique_items.toLocaleString()}</div>
              <div class="inventory-stat-label">Unique Items</div>
            </div>
            <div class="inventory-stat">
              <div class="inventory-stat-value">${summary.total_count.toLocaleString()}</div>
              <div class="inventory-stat-label">Total Items</div>
            </div>
          </div>
        </div>

        <div class="inventory-filters">
          <div class="filter-group">
            <label class="filter-label">Search Items</label>
            <input type="text" class="inventory-filter-input" id="inventory-search" placeholder="Search for an item (e.g., Fel Iron Bar)...">
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Quality</label>
            <select class="inventory-filter-select" id="inventory-quality-filter">
              <option value="">All Qualities</option>
              <option value="0">Poor (Gray)</option>
              <option value="1">Common (White)</option>
              <option value="2">Uncommon (Green)</option>
              <option value="3">Rare (Blue)</option>
              <option value="4">Epic (Purple)</option>
              <option value="5">Legendary (Orange)</option>
              <option value="6">Artifact (Gold)</option>
              <option value="7">Heirloom (Cyan)</option>
            </select>
          </div>

          <div class="filter-group">
            <label class="filter-label">Location</label>
            <select class="inventory-filter-select" id="inventory-location-filter">
              <option value="">All Locations</option>
              <option value="Bags">Bags</option>
              <option value="Bank">Bank</option>
              <option value="Mail">Mail</option>
              <option value="Guild Bank">Guild Bank</option>
            </select>
          </div>

          <div class="filter-group">
            <label class="filter-label">Entity Type</label>
            <select class="inventory-filter-select" id="inventory-type-filter">
              <option value="">All (Characters + Guilds)</option>
              <option value="character">Characters Only</option>
              <option value="guild">Guilds Only</option>
            </select>
          </div>
        </div>

        <div class="inventory-grid" id="inventory-grid"></div>

        <div class="inventory-no-results" id="inventory-no-results" style="display: none;">
          <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
          <div>No matching items found</div>
          <div style="font-size: 14px; margin-top: 10px; color: #666;">Try adjusting your search or filters</div>
        </div>
      </div>
    `;

    // Build entity cards as DOM nodes so WDTooltip.attach() can be wired up
    const grid = container.querySelector('#inventory-grid');
    allEntities.forEach(entity => {
      grid.appendChild(renderInventoryEntityCard(entity));
    });

    // Set up filter event listeners
    setupInventoryFilters();
  }

  function renderInventoryEntityCard(entity) {
    const isGuild = entity.type === 'guild';
    const iconSrc = isGuild
      ? ''
      : `/icon.php?name=${encodeURIComponent(entity.name)}&realm=${encodeURIComponent(entity.realm)}&type=avatar`;

    const card = document.createElement('div');
    card.className = `inventory-entity-card${isGuild ? ' guild-card' : ''}`;
    card.dataset.entityId   = entity.id || entity.guild_id;
    card.dataset.entityType = entity.type;
    // Store item names/qualities for filter logic (no large JSON in dataset)
    card.dataset.items = JSON.stringify(entity.items);

    // ---- Card header + stats (static, no tooltips needed) ----
    card.innerHTML = `
      <div class="inventory-entity-header">
        ${isGuild
          ? `<div class="inventory-entity-icon">🏰</div>`
          : `<img class="inventory-entity-icon" src="${iconSrc}" alt="${escapeHtml(entity.name)}">`
        }
        <div class="inventory-entity-info">
          <div class="inventory-entity-name">${escapeHtml(isGuild ? entity.guild_name : entity.name)}</div>
          <div class="inventory-entity-realm">
            ${escapeHtml(entity.realm)} • 
            ${escapeHtml(entity.faction)}
            ${!isGuild && entity.level ? ` • Level ${entity.level}` : ''}
            ${isGuild ? ' • Guild Bank' : ''}
          </div>
        </div>
      </div>
      <div class="inventory-entity-stats">
        <div class="inventory-entity-stat">
          Unique Items: <span class="inventory-entity-stat-value">${entity.unique_items}</span>
        </div>
        <div class="inventory-entity-stat">
          Total Count: <span class="inventory-entity-stat-value">${entity.item_count.toLocaleString()}</span>
        </div>
      </div>
      <button class="inventory-items-toggle">View Items (${entity.items.length})</button>
    `;

    // Wire up the toggle button (replaces old onclick="window.toggleInventoryItems(this)")
    const toggleBtn = card.querySelector('.inventory-items-toggle');
    toggleBtn.addEventListener('click', function () {
      const itemsList = this.nextElementSibling;
      const isExpanded = itemsList.classList.contains('expanded');
      if (isExpanded) {
        itemsList.classList.remove('expanded');
        this.textContent = this.textContent.replace('Hide', 'View');
      } else {
        itemsList.classList.add('expanded');
        this.textContent = this.textContent.replace('View', 'Hide');
      }
    });

    // ---- Item list — DOM-built so WDTooltip.attach() works ----
    const itemsList = document.createElement('div');
    itemsList.className = 'inventory-items-list';

    if (entity.items.length > 0) {
      entity.items.forEach(item => {
        itemsList.appendChild(renderInventoryItemEl(item));
      });
    } else {
      itemsList.innerHTML = '<p style="padding: 20px; text-align: center; color: #999;">No items</p>';
    }

    card.appendChild(itemsList);
    return card;
  }

  // DOM-based item renderer — returns an element with WDTooltip already attached
  function renderInventoryItemEl(item) {
    const qualityClass = `quality-${item.quality_name.toLowerCase()}`;

    const el = document.createElement('div');
    el.className = `inventory-item ${qualityClass}`;
    el.dataset.itemName      = item.name.toLowerCase();
    el.dataset.itemQuality   = item.quality;
    el.dataset.itemLocations = JSON.stringify(item.locations.map(l => l.location));

    const locationsHtml = item.locations.length > 1
      ? item.locations.map(loc => `
          <div class="inventory-item-location">
            <span class="inventory-item-location-name">${escapeHtml(loc.location)}</span>: 
            ${escapeHtml(loc.location_detail)} 
            (×${loc.count})
          </div>`).join('')
      : `<div class="inventory-item-location">
           <span class="inventory-item-location-name">${escapeHtml(item.locations[0].location)}</span>: 
           ${escapeHtml(item.locations[0].location_detail)}
         </div>`;

    el.innerHTML = `
      <div class="inventory-item-header">
        <img class="inventory-item-icon ${qualityClass}-border"
             src="/icon.php?type=item&id=${item.item_id}&name=${encodeURIComponent(item.name)}&size=large&icon=${encodeURIComponent(item.icon || '')}"
             alt="${escapeHtml(item.name)}"
             onerror="this.src='/icon.php?type=item&id=0&name=Unknown&size=large'">
        <div class="inventory-item-info">
          <div class="inventory-item-name">${escapeHtml(item.name)}</div>
          <div class="inventory-item-details">
            ${item.quality_name}${item.ilvl > 0 ? ` • iLvl ${item.ilvl}` : ''}
          </div>
        </div>
        <div class="inventory-item-count">×${item.total_count.toLocaleString()}</div>
      </div>
      <div class="inventory-item-locations">${locationsHtml}</div>
    `;

    // Attach Wowhead tooltip
    if (window.WDTooltip && (item.link || item.item_id)) {
      WDTooltip.attach(el, { link: item.link, item_id: item.item_id, name: item.name, icon: item.icon }, null);
    }

    return el;
  }

  // Legacy string-based wrapper (kept for any remaining callers)
  function renderInventoryItem(item) {
    return renderInventoryItemEl(item).outerHTML;
  }

  function setupInventoryFilters() {
    const searchInput = document.getElementById('inventory-search');
    const qualityFilter = document.getElementById('inventory-quality-filter');
    const locationFilter = document.getElementById('inventory-location-filter');
    const typeFilter = document.getElementById('inventory-type-filter');
    
    if (!searchInput || !qualityFilter || !locationFilter || !typeFilter) return;

    const filterInventory = () => {
      const searchTerm = searchInput.value.toLowerCase();
      const selectedQuality = qualityFilter.value;
      const selectedLocation = locationFilter.value;
      const selectedType = typeFilter.value;
      
      const grid = document.getElementById('inventory-grid');
      const noResults = document.getElementById('inventory-no-results');
      const cards = grid.querySelectorAll('.inventory-entity-card');
      let visibleCount = 0;
      
      cards.forEach(card => {
        const entityType = card.dataset.entityType;
        const items = JSON.parse(card.dataset.items || '[]');
        
        // Check entity type filter
        if (selectedType && entityType !== selectedType) {
          card.style.display = 'none';
          return;
        }
        
        let hasVisibleItems = false;
        const itemElements = card.querySelectorAll('.inventory-item');
        
        itemElements.forEach(itemEl => {
          const itemName = itemEl.dataset.itemName || '';
          const itemQuality = itemEl.dataset.itemQuality || '';
          const itemLocations = JSON.parse(itemEl.dataset.itemLocations || '[]');
          
          const matchesSearch = !searchTerm || itemName.includes(searchTerm);
          const matchesQuality = !selectedQuality || itemQuality === selectedQuality;
          const matchesLocation = !selectedLocation || itemLocations.includes(selectedLocation);
          
          if (matchesSearch && matchesQuality && matchesLocation) {
            itemEl.style.display = 'block';
            hasVisibleItems = true;
          } else {
            itemEl.style.display = 'none';
          }
        });
        
        // Show card if it has visible items or no filters are active
        if (hasVisibleItems || (!searchTerm && !selectedQuality && !selectedLocation)) {
          card.style.display = 'block';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });
      
      // Show/hide no results message
      if (visibleCount === 0) {
        grid.style.display = 'none';
        noResults.style.display = 'block';
      } else {
        grid.style.display = 'grid';
        noResults.style.display = 'none';
      }
    };

    searchInput.addEventListener('input', filterInventory);
    qualityFilter.addEventListener('change', filterInventory);
    locationFilter.addEventListener('change', filterInventory);
    typeFilter.addEventListener('change', filterInventory);
  }

  /* ============================== PROGRESSION RENDERER ============================== */
  function renderProgression(container, data) {
    if (!data) {
      showError(container, 'No progression data available');
      return;
    }
    
    const { lockouts, recent_dungeons } = data;
    
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🏆 Dungeon & Raid Progression</h3>
        
        <!-- Instance Lockouts -->
        ${lockouts && lockouts.length > 0 ? `
          <h4>🔒 Active Lockouts</h4>
          <div class="lockout-list">
            ${lockouts.map(lockout => `
              <div class="lockout-item">
                <div class="lockout-header">
                  <strong>${escapeHtml(lockout.instance_name)}</strong>
                  <span class="lockout-diff">${lockout.difficulty}</span>
                </div>
                <div class="lockout-details">
                  <span class="lockout-char">${escapeHtml(lockout.character_name)}</span>
                  <span>Bosses: ${lockout.bosses_killed}</span>
                  <span class="lockout-expires">Until: ${formatDate(lockout.locked_until)}</span>
                </div>
              </div>
            `).join('')}
          </div>
        ` : '<p class="empty-state">No active lockouts</p>'}

        <!-- Recent Dungeons -->
        ${recent_dungeons && recent_dungeons.length > 0 ? `
          <h4>📜 Recent Runs (Last 30 Days)</h4>
          <div class="dungeon-list">
            ${recent_dungeons.slice(0, 20).map(run => `
              <div class="dungeon-item">
                <div class="dungeon-header">
                  <strong>${escapeHtml(run.instance_name)}</strong>
                  <span class="dungeon-diff">${run.difficulty}</span>
                </div>
                <div class="dungeon-details">
                  <span class="dungeon-char">${escapeHtml(run.character_name)}</span>
                  <span>Bosses: ${run.bosses_killed}</span>
                  <span>Duration: ${formatDuration(run.duration_seconds)}</span>
                  <span class="dungeon-time">${formatDate(run.completed_at)}</span>
                </div>
              </div>
            `).join('')}
          </div>
          ${recent_dungeons.length > 20 ? `<p class="note">Showing 20 of ${recent_dungeons.length} runs</p>` : ''}
        ` : '<p class="empty-state">No recent dungeon runs</p>'}
      </div>
    `;
  }

  /* ============================== SOCIAL RENDERER ============================== */
  function renderSocial(container, data) {
    if (!data) {
      showError(container, 'No social data available');
      return;
    }

    const friends = data.friends || [];
    const mostPlayedWith = data.most_played_with || [];

    let html = '<div class="bazaar-section"><h3>👥 Social Activity</h3>';

    // Friends — grouped by character
    if (friends.length > 0) {
      // Group by character_name
      const byChar = {};
      friends.forEach(f => {
        const key = f.character_name || 'Unknown';
        if (!byChar[key]) byChar[key] = [];
        byChar[key].push(f);
      });

      html += '<h4>👋 Friends by Character</h4>';
      Object.entries(byChar).forEach(([charName, charFriends]) => {
        html += `<div class="social-char-group">
          <div class="social-char-header">${escapeHtml(charName)} <span class="social-char-count">${charFriends.length} friends</span></div>
          <div class="social-friends-list">
            ${charFriends.map(f => `
              <div class="social-friend-item">
                <span class="friend-name">${escapeHtml(f.friend_name)}</span>
                ${f.class_name ? `<span class="friend-class">${escapeHtml(f.class_name)}</span>` : ''}
                ${f.level ? `<span class="friend-level">Lvl ${f.level}</span>` : ''}
              </div>`).join('')}
          </div>
        </div>`;
      });
    } else {
      html += '<p class="empty-state">No friend data yet — friend tracking requires the WhoDAT addon to record friend list changes.</p>';
    }

    // Most Played With
    if (mostPlayedWith.length > 0) {
      html += '<h4>🎮 Most Played With (Last 30 Days)</h4><div class="mpw-list">';
      mostPlayedWith.slice(0, 20).forEach((p, i) => {
        html += `<div class="mpw-item">
          <span class="mpw-rank">#${i+1}</span>
          <span class="mpw-name">${escapeHtml(p.player_name)}</span>
          ${p.class_name ? `<span class="mpw-class">${escapeHtml(p.class_name)}</span>` : ''}
          <span class="mpw-count">${p.groups_together} groups</span>
          <span class="mpw-breakdown">${p.party_pct}% party / ${p.raid_pct}% raid</span>
        </div>`;
      });
      html += '</div>';
    }

    if (friends.length === 0 && mostPlayedWith.length === 0) {
      html += '<p class="empty-state">No social data available yet.</p>';
    }

    html += '</div>';
    container.innerHTML = html;
  }

  /* ============================== OTHER RENDERERS (Tickets, Trading, etc.) ============================== */
  
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
            <div class="card-value">${summary.this_week || 0}</div>
          </div>
          <div class="summary-card">
            <div class="card-label">This Month</div>
            <div class="card-value">${summary.this_month || 0}</div>
          </div>
          <div class="summary-card">
            <div class="card-label">Top Earner</div>
            <div class="card-value">${summary.top_earner || 'None'}</div>
          </div>
        </div>
        
        ${summary.total_tickets === 0 ? '<p class="empty-state">No ticket data yet. Start earning tickets!</p>' : ''}
      </div>
    `;
  }

  function renderTrading(container, data) {
    container.innerHTML = `
      <div class="bazaar-section">
        <h3>🏪 Trading Post</h3>
        <p class="empty-state">Trading post feature coming soon!</p>
      </div>
    `;
  }

  function renderFortune(container, data) {
    if (!data || !data.forecast) {
      showError(container, 'No fortune data available');
      return;
    }
    
    const { forecast, top_items, character_fortunes, market_opportunities, cosmic_insights, wealth_history } = data;
    
    // Create mystical atmosphere with particles
    const particleEffect = `
      <div class="mystical-particles">
        ${Array.from({length: 20}, (_, i) => `<div class="particle" style="--delay: ${i * 0.3}s; --x: ${Math.random() * 100}%"></div>`).join('')}
      </div>
    `;
    
    container.innerHTML = `
      <div class="bazaar-section fortune-teller-realm">
        ${particleEffect}
        
        <!-- Mystical Header -->
        <div class="fortune-header">
          <div class="crystal-ball">🔮</div>
          <h2 class="fortune-title">Madame Zarya's Fortune Parlor</h2>
          <p class="fortune-subtitle">✨ Where the Veil Between Gold and Destiny Grows Thin ✨</p>
        </div>
        
        <!-- Mystical Welcome Message -->
        <div class="mystical-message welcome-message">
          <div class="incense-smoke">💨</div>
          <p class="fortune-reading">${forecast.mystical_message || 'The spirits await your presence...'}</p>
        </div>
        
        <!-- Wealth Forecast - The Crystal Ball's Vision -->
        <div class="forecast-section mystical-panel">
          <h3>
            <span class="icon">🔮</span>
            The Crystal Ball Reveals Your Fortune
            <span class="icon">🔮</span>
          </h3>
          
          <div class="forecast-cards">
            <div class="forecast-card wealth-current ${forecast.trend}">
              <div class="card-icon">💰</div>
              <div class="card-label">Current Wealth</div>
              <div class="card-value">${formatGold(forecast.current_wealth || 0)}</div>
              <div class="card-subtitle">Gold in your coffers</div>
            </div>
            
            <div class="forecast-card prediction ${forecast.trend}">
              <div class="card-icon">🌙</div>
              <div class="card-label">Seven Days Hence</div>
              <div class="card-value">${formatGold(forecast.predicted_7d || 0)}</div>
              <div class="card-subtitle">The near future beckons</div>
            </div>
            
            <div class="forecast-card prediction ${forecast.trend}">
              <div class="card-icon">⭐</div>
              <div class="card-label">Thirty Days Hence</div>
              <div class="card-value">${formatGold(forecast.predicted_30d || 0)}</div>
              <div class="card-subtitle">The distant horizon</div>
            </div>
            
            <div class="forecast-card trend-card trend-${forecast.trend || 'stable'}">
              <div class="card-icon">${getTrendIcon(forecast.trend)}</div>
              <div class="card-label">The Cosmic Trend</div>
              <div class="card-value trend-text">${(forecast.trend || 'stable').toUpperCase()}</div>
              <div class="card-subtitle">${getTrendMessage(forecast.trend)}</div>
            </div>
          </div>
        </div>
        
        <!-- Cosmic Insights -->
        ${cosmic_insights && cosmic_insights.length > 0 ? `
          <div class="cosmic-insights mystical-panel">
            <h3>
              <span class="icon">✨</span>
              The Cosmic Tapestry
              <span class="icon">✨</span>
            </h3>
            <div class="insights-grid">
              ${cosmic_insights.map(insight => `
                <div class="insight-card ${insight.type}">
                  <div class="insight-icon">${insight.icon}</div>
                  <p class="insight-message">${insight.message}</p>
                </div>
              `).join('')}
            </div>
          </div>
        ` : ''}

        <!-- Top Earning Items - The Cards of Fortune -->
        ${top_items && top_items.length > 0 ? `
          <div class="top-items-section mystical-panel">
            <h3>
              <span class="icon">🃏</span>
              The Cards of Fortune Speak
              <span class="icon">🃏</span>
            </h3>
            <p class="section-subtitle">Your most profitable ventures in the past moon cycle</p>
            <div class="fortune-cards">
              ${top_items.slice(0, 10).map((item, idx) => `
                <div class="fortune-card rank-${Math.min(idx, 2)}">
                  <div class="card-rank">${getRankMedal(idx)}</div>
                  <div class="card-content">
                    <div class="item-name">${escapeHtml(item.item_name)}</div>
                    <div class="item-stats">
                      <span class="stat earnings">
                        <span class="stat-icon">💎</span>
                        ${formatGold(item.total_earnings)}
                      </span>
                      <span class="stat sales">
                        <span class="stat-icon">📦</span>
                        ${item.sales_count} sales
                      </span>
                    </div>
                  </div>
                  <div class="card-shimmer"></div>
                </div>
              `).join('')}
            </div>
          </div>
        ` : `
          <div class="mystical-panel empty-state">
            <div class="empty-icon">🌫️</div>
            <p>The mists obscure your trading history... Begin your journey to reveal your fortune!</p>
          </div>
        `}
        
        <!-- Character Fortunes -->
        ${character_fortunes && character_fortunes.length > 0 ? `
          <div class="character-fortunes mystical-panel">
            <h3>
              <span class="icon">👥</span>
              The Destinies of Your Champions
              <span class="icon">👥</span>
            </h3>
            <div class="fortunes-grid">
              ${character_fortunes.map(char => `
                <div class="character-fortune-card ${char.fortune_level}">
                  <div class="char-header">
                    <div class="char-name">${escapeHtml(char.character_name)}</div>
                    <div class="fortune-badge ${char.fortune_level}">${getFortuneBadge(char.fortune_level)}</div>
                  </div>
                  <div class="char-wealth">
                    <span class="wealth-label">Current Gold:</span>
                    <span class="wealth-value">${formatGold(char.current_wealth)}</span>
                  </div>
                  <div class="char-earned">
                    <span class="earned-label">Earned (30d):</span>
                    <span class="earned-value">${formatGold(char.total_earned)}</span>
                  </div>
                  <div class="mystical-reading">
                    <em>"${char.mystical_reading}"</em>
                  </div>
                </div>
              `).join('')}
            </div>
          </div>
        ` : ''}
        
        <!-- Market Opportunities -->
        ${market_opportunities && market_opportunities.length > 0 ? `
          <div class="market-opportunities mystical-panel">
            <h3>
              <span class="icon">🌟</span>
              The Spirits Whisper of Opportunities
              <span class="icon">🌟</span>
            </h3>
            <div class="opportunities-list">
              ${market_opportunities.map(opp => `
                <div class="opportunity-card ${opp.type}">
                  <div class="opp-header">
                    <div class="opp-type-badge ${opp.type}">
                      ${opp.type === 'buy' ? '📉 BUY LOW' : '📈 SELL HIGH'}
                    </div>
                    <div class="opp-price">${formatGold(opp.current_price)}</div>
                  </div>
                  <div class="opp-item">${escapeHtml(opp.item_name)}</div>
                  <div class="opp-reason">${opp.reason}</div>
                  <div class="mystical-advice">${opp.mystical_advice}</div>
                </div>
              `).join('')}
            </div>
          </div>
        ` : ''}
        
        <!-- Footer Blessing -->
        <div class="fortune-footer">
          <div class="tarot-cards">🎴 🎴 🎴</div>
          <p class="blessing">May the Bazaar's fortune smile upon your ventures, traveler.</p>
          <div class="candles">🕯️ 🕯️ 🕯️</div>
        </div>
      </div>
    `;
  }
  
  // Helper functions for fortune teller
  function getTrendIcon(trend) {
    switch(trend) {
      case 'rising': return '📈';
      case 'falling': return '📉';
      default: return '⚖️';
    }
  }
  
  function getTrendMessage(trend) {
    switch(trend) {
      case 'rising': return 'The stars align!';
      case 'falling': return 'Storm clouds gather...';
      default: return 'Balance prevails';
    }
  }
  
  function getRankMedal(index) {
    switch(index) {
      case 0: return '🥇 #1';
      case 1: return '🥈 #2';
      case 2: return '🥉 #3';
      default: return `#${index + 1}`;
    }
  }
  
  function getFortuneBadge(level) {
    switch(level) {
      case 'champion': return '👑 Champion';
      case 'strong': return '⭐ Strong';
      case 'moderate': return '💫 Moderate';
      case 'struggling': return '🌙 Developing';
      case 'dormant': return '💤 Dormant';
      default: return '✨';
    }
  }

  function renderComparison(container, data) {
    if (!data || !data.characters) {
      showError(container, 'No comparison data available');
      return;
    }

    const { characters, comparison } = data;

    // Class color map (WoW standard)
    const classColors = {
      'WARRIOR':     '#C79C6E',
      'PALADIN':     '#F58CBA',
      'HUNTER':      '#ABD473',
      'ROGUE':       '#FFF569',
      'PRIEST':      '#FFFFFF',
      'DEATHKNIGHT': '#C41F3B',
      'SHAMAN':      '#0070DE',
      'MAGE':        '#69CCF0',
      'WARLOCK':     '#9482C9',
      'DRUID':       '#FF7D0A',
    };

    function classColor(classFile) {
      return classColors[(classFile || '').toUpperCase()] || '#aaa';
    }

    function formatGoldCopper(copper) {
      if (!copper) return '0g';
      const g = Math.floor(copper / 10000);
      const s = Math.floor((copper % 10000) / 100);
      const c = copper % 100;
      let out = '';
      if (g) out += `<span class="cmp-gold">${g.toLocaleString()}g</span>`;
      if (s) out += `<span class="cmp-silver">${s}s</span>`;
      if (!g && c) out += `<span class="cmp-copper">${c}c</span>`;
      return out || '0g';
    }

    // Track selected IDs
    const selectedIds = new Set(
      comparison ? comparison.map(c => c.character_id) : []
    );

    // ---- Scaffold ----
    container.innerHTML = `
      <div class="cmp-container">

        <div class="cmp-header">
          <div>
            <div class="cmp-title">⚖️ Character Comparison</div>
            <div class="cmp-subtitle">Select 2–5 characters to compare side-by-side</div>
          </div>
        </div>

        <!-- Character selector -->
        <div class="cmp-selector">
          <div class="cmp-selector-label">Select Characters</div>
          <div class="cmp-char-pills" id="cmp-char-pills">
            ${characters.map(char => {
              const color = classColor(char.class_file);
              const checked = selectedIds.has(char.id);
              return `
                <label class="cmp-pill${checked ? ' selected' : ''}" data-id="${char.id}"
                       style="--class-color:${color}">
                  <input type="checkbox" value="${char.id}" ${checked ? 'checked' : ''}
                         class="cmp-pill-checkbox">
                  <img class="cmp-pill-icon"
                       src="/icon.php?name=${encodeURIComponent(char.name)}&realm=${encodeURIComponent(char.realm)}&type=avatar"
                       alt="${escapeHtml(char.name)}"
                       onerror="this.style.display='none'">
                  <span class="cmp-pill-name" style="color:${color}">${escapeHtml(char.name)}</span>
                  <span class="cmp-pill-meta">${escapeHtml(char.faction)}</span>
                </label>`;
            }).join('')}
          </div>
          <button class="cmp-compare-btn" id="cmp-compare-btn">Compare Selected</button>
        </div>

        <!-- Results area -->
        <div id="cmp-results">
          ${comparison && comparison.length >= 2
            ? '' /* will be built by DOM after innerHTML */
            : '<p class="cmp-hint">Select at least 2 characters and click <strong>Compare Selected</strong>.</p>'
          }
        </div>

      </div>
    `;

    // Wire up pill toggles
    const pillsEl = container.querySelector('#cmp-char-pills');
    pillsEl.addEventListener('change', e => {
      if (!e.target.classList.contains('cmp-pill-checkbox')) return;
      const id  = parseInt(e.target.value);
      const pill = e.target.closest('.cmp-pill');
      if (e.target.checked) {
        selectedIds.add(id);
        pill.classList.add('selected');
      } else {
        selectedIds.delete(id);
        pill.classList.remove('selected');
      }
      // Enforce max 5
      if (selectedIds.size > 5) {
        e.target.checked = false;
        selectedIds.delete(id);
        pill.classList.remove('selected');
      }
      container.querySelector('#cmp-compare-btn').disabled = selectedIds.size < 2;
    });

    // Set initial button state
    container.querySelector('#cmp-compare-btn').disabled = selectedIds.size < 2;

    // Compare button handler — re-fetches with ids param
    container.querySelector('#cmp-compare-btn').addEventListener('click', async () => {
      if (selectedIds.size < 2) return;
      const btn = container.querySelector('#cmp-compare-btn');
      btn.disabled   = true;
      btn.textContent = 'Loading…';

      const resultsEl = container.querySelector('#cmp-results');
      resultsEl.innerHTML = '<p class="cmp-hint">Loading comparison…</p>';

      try {
        const ids = Array.from(selectedIds).join(',');
        const res = await fetch(`/sections/bazaar-comparison.php?ids=${ids}`, { credentials: 'include' });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const newData = await res.json();
        if (newData.comparison && newData.comparison.length >= 2) {
          buildComparisonTable(resultsEl, newData.comparison);
        } else {
          resultsEl.innerHTML = '<p class="cmp-hint">No data returned. Have these characters been synced?</p>';
        }
      } catch (err) {
        resultsEl.innerHTML = `<p class="cmp-hint" style="color:#f88">Error: ${escapeHtml(err.message)}</p>`;
      } finally {
        btn.disabled   = false;
        btn.textContent = 'Compare Selected';
      }
    });

    // If we already have comparison data on load, render it immediately
    if (comparison && comparison.length >= 2) {
      buildComparisonTable(container.querySelector('#cmp-results'), comparison);
    }
  }

  // ---- Builds the comparison table and bar-chart rows ----
  function buildComparisonTable(resultsEl, chars) {

    const classColors = {
      'WARRIOR':'#C79C6E','PALADIN':'#F58CBA','HUNTER':'#ABD473',
      'ROGUE':'#FFF569','PRIEST':'#FFFFFF','DEATHKNIGHT':'#C41F3B',
      'SHAMAN':'#0070DE','MAGE':'#69CCF0','WARLOCK':'#9482C9','DRUID':'#FF7D0A',
    };
    function classColor(cf) { return classColors[(cf||'').toUpperCase()] || '#aaa'; }

    function fmtGold(copper) {
      if (!copper) return '<span class="cmp-zero">—</span>';
      const g = Math.floor(copper / 10000);
      const s = Math.floor((copper % 10000) / 100);
      let out = '';
      if (g) out += `<span class="cmp-gold">${g.toLocaleString()}g</span> `;
      if (s) out += `<span class="cmp-silver">${s}s</span>`;
      return out.trim() || '<span class="cmp-zero">0g</span>';
    }

    // Stat definitions: label, key, format fn, higher-is-better
    const stats = [
      { label: '⚔️ Level',           key: 'level',         fmt: v => v || '—',              hib: true  },
      { label: '💰 Gold',             key: 'gold',          fmt: fmtGold,                     hib: true  },
      { label: '🛡️ Avg iLvl',        key: 'avg_ilvl',      fmt: v => v ? v : '—',            hib: true  },
      { label: '🔱 Max iLvl',         key: 'max_ilvl',      fmt: v => v ? v : '—',            hib: true  },
      { label: '🏆 Achievements',     key: 'achievements',  fmt: v => v.toLocaleString(),     hib: true  },
      { label: '⭐ Ach Points',       key: 'ach_points',    fmt: v => v.toLocaleString(),     hib: true  },
      { label: '💀 Deaths',           key: 'deaths',        fmt: v => v.toLocaleString(),     hib: false },
      { label: '👹 Boss Kills',       key: 'boss_kills',    fmt: v => v.toLocaleString(),     hib: true  },
      { label: '🗺️ Unique Bosses',   key: 'unique_bosses', fmt: v => v.toLocaleString(),     hib: true  },
      { label: '📜 Quests Done',      key: 'quests',        fmt: v => v.toLocaleString(),     hib: true  },
      { label: '🎒 Bag Items',        key: 'bag_slots',     fmt: v => v.toLocaleString(),     hib: false },
      { label: '📦 Sales (30d)',      key: 'sales_30d',     fmt: v => v.toLocaleString(),     hib: true  },
      { label: '💵 Earnings (30d)',   key: 'earnings_30d',  fmt: fmtGold,                     hib: true  },
    ];

    resultsEl.innerHTML = '';

    // --- Header cards ---
    const headerRow = document.createElement('div');
    headerRow.className = 'cmp-header-row';

    // Empty label cell
    const labelCell = document.createElement('div');
    labelCell.className = 'cmp-row-label cmp-header-label';
    headerRow.appendChild(labelCell);

    chars.forEach(char => {
      const color = classColor(char.class_file);
      const cell = document.createElement('div');
      cell.className = 'cmp-header-card';
      cell.style.setProperty('--class-color', color);
      cell.innerHTML = `
        <img class="cmp-header-avatar"
             src="/icon.php?name=${encodeURIComponent(char.name)}&realm=${encodeURIComponent(char.realm)}&type=avatar"
             alt="${escapeHtml(char.name)}"
             onerror="this.style.display='none'">
        <div class="cmp-header-name" style="color:${color}">${escapeHtml(char.name)}</div>
        <div class="cmp-header-sub">${escapeHtml(char.realm)}</div>
        <div class="cmp-header-sub">${escapeHtml(char.faction)}${char.guild_name ? ` • <em>${escapeHtml(char.guild_name)}</em>` : ''}</div>
      `;
      headerRow.appendChild(cell);
    });
    resultsEl.appendChild(headerRow);

    // --- Stat rows ---
    stats.forEach((stat, rowIdx) => {
      const values = chars.map(c => c[stat.key] ?? 0);
      const numericValues = values.map(v => typeof v === 'number' ? v : 0);
      const maxVal = Math.max(...numericValues);
      const minVal = Math.min(...numericValues);

      const row = document.createElement('div');
      row.className = `cmp-stat-row${rowIdx % 2 === 0 ? ' cmp-row-even' : ''}`;

      const label = document.createElement('div');
      label.className = 'cmp-row-label';
      label.textContent = stat.label;
      row.appendChild(label);

      chars.forEach((char, i) => {
        const val      = numericValues[i];
        const rawVal   = values[i];
        const isBest   = stat.hib ? val === maxVal : val === minVal;
        const isWorst  = stat.hib ? val === minVal : val === maxVal;
        const barPct   = maxVal > 0 ? Math.round((val / maxVal) * 100) : 0;
        const color    = classColor(char.class_file);

        const cell = document.createElement('div');
        cell.className = `cmp-stat-cell${isBest && chars.length > 1 ? ' cmp-best' : ''}${isWorst && chars.length > 1 && val !== maxVal ? ' cmp-worst' : ''}`;
        cell.innerHTML = `
          <div class="cmp-stat-value">${stat.fmt(rawVal)}</div>
          ${maxVal > 0 ? `
            <div class="cmp-bar-track">
              <div class="cmp-bar-fill" style="width:${barPct}%;background:${color};"></div>
            </div>` : ''}
        `;
        row.appendChild(cell);
      });

      resultsEl.appendChild(row);
    });

    // --- Legend ---
    const legend = document.createElement('div');
    legend.className = 'cmp-legend';
    legend.innerHTML = `
      <span class="cmp-legend-item cmp-best-sample">▲ Best</span>
      <span class="cmp-legend-item cmp-worst-sample">▼ Worst</span>
      <span class="cmp-legend-item" style="color:#888;font-size:11px;">Bars show relative value. Deaths: lower is better.</span>
    `;
    resultsEl.appendChild(legend);
  }

  function renderHeatmap(container, data) {
    if (!data || !data.summary) {
      showError(container, 'No heatmap data available');
      return;
    }

    const { summary, characters, quality_breakdown, top_items } = data;

    const qualityColors = {
      'Poor':      '#9d9d9d',
      'Common':    '#ffffff',
      'Uncommon':  '#1eff00',
      'Rare':      '#0070dd',
      'Epic':      '#a335ee',
      'Legendary': '#ff8000',
    };

    // Helper: build the stacked quality bar for a character
    function qualityBarHtml(byQuality, total) {
      if (!total) return '<div class="hm-bar-empty">Empty</div>';
      const order = ['Legendary','Epic','Rare','Uncommon','Common','Poor'];
      return order.map(q => {
        const count = byQuality[q] || 0;
        if (!count) return '';
        const pct = ((count / total) * 100).toFixed(1);
        const color = qualityColors[q] || '#888';
        return `<div class="hm-bar-segment" style="width:${pct}%;background:${color};"
                     title="${q}: ${count} (${pct}%)"></div>`;
      }).join('');
    }

    // ---- Static scaffold (no tooltippable elements) ----
    container.innerHTML = `
      <div class="heatmap-container">

        <div class="heatmap-header">
          <div>
            <div class="heatmap-title">🗺️ Inventory Heatmap</div>
            <div class="heatmap-subtitle">Quality distribution and fill rates across all characters</div>
          </div>
          <div class="heatmap-stats">
            <div class="heatmap-stat">
              <div class="heatmap-stat-value">${summary.total_items.toLocaleString()}</div>
              <div class="heatmap-stat-label">Total Items</div>
            </div>
            <div class="heatmap-stat">
              <div class="heatmap-stat-value">${summary.total_characters}</div>
              <div class="heatmap-stat-label">Characters</div>
            </div>
          </div>
        </div>

        <!-- Quality Legend -->
        <div class="hm-legend">
          ${Object.entries(qualityColors).map(([q, c]) => `
            <div class="hm-legend-item">
              <div class="hm-legend-swatch" style="background:${c};"></div>
              <span>${q}</span>
            </div>`).join('')}
        </div>

        <!-- Global Quality Breakdown -->
        ${quality_breakdown && quality_breakdown.length > 0 ? `
          <div class="hm-section">
            <h4 class="hm-section-title">📊 Overall Quality Breakdown</h4>
            <div class="hm-global-bar">
              ${qualityBarHtml(
                Object.fromEntries(quality_breakdown.map(q => [q.quality, q.count])),
                summary.total_items
              )}
            </div>
            <div class="hm-quality-grid">
              ${quality_breakdown.map(q => `
                <div class="hm-quality-pill" style="border-color:${qualityColors[q.quality] || '#888'}">
                  <span class="hm-quality-name" style="color:${qualityColors[q.quality] || '#888'}">${q.quality}</span>
                  <span class="hm-quality-count">${q.count.toLocaleString()}</span>
                </div>`).join('')}
            </div>
          </div>
        ` : ''}

        <!-- Per-character bag fill heatmap -->
        ${characters && characters.length > 0 ? `
          <div class="hm-section">
            <h4 class="hm-section-title">🎒 Bag Fill by Character</h4>
            <div class="hm-char-grid" id="hm-char-grid"></div>
          </div>
          <div class="hm-section">
            <h4 class="hm-section-title">🏦 Bank Distribution by Character</h4>
            <div class="hm-char-grid" id="hm-bank-grid"></div>
          </div>
        ` : '<p class="empty-state">No character data available.</p>'}

        <!-- Top Items -->
        ${top_items && top_items.length > 0 ? `
          <div class="hm-section">
            <h4 class="hm-section-title">📦 Most Stocked Items</h4>
            <div class="hm-top-items" id="hm-top-items"></div>
          </div>
        ` : ''}

      </div>
    `;

    // ---- Per-character bag cards (DOM-built) ----
    if (characters && characters.length > 0) {
      const bagGrid  = container.querySelector('#hm-char-grid');
      const bankGrid = container.querySelector('#hm-bank-grid');

      characters.forEach(char => {
        const iconSrc = `/icon.php?name=${encodeURIComponent(char.name)}&realm=${encodeURIComponent(char.realm)}&type=avatar`;

        // --- Bag card ---
        const bagTotal = char.bag.total;
        const fillPct  = char.bag_fill_pct;
        const fillClass = fillPct >= 90 ? 'fill-critical' : fillPct >= 70 ? 'fill-high' : fillPct >= 40 ? 'fill-mid' : 'fill-low';

        const bagCard = document.createElement('div');
        bagCard.className = 'hm-char-card';
        bagCard.innerHTML = `
          <div class="hm-char-header">
            <img class="hm-char-icon" src="${iconSrc}" alt="${escapeHtml(char.name)}"
                 onerror="this.style.display='none'">
            <div class="hm-char-info">
              <div class="hm-char-name">${escapeHtml(char.name)}</div>
              <div class="hm-char-meta">${escapeHtml(char.realm)} • ${escapeHtml(char.faction || '')}</div>
            </div>
            <div class="hm-fill-badge ${fillClass}">${fillPct}%</div>
          </div>
          <div class="hm-fill-track" title="${bagTotal} items / ~${summary.bag_slot_baseline} slots">
            <div class="hm-fill-bar ${fillClass}" style="width:${fillPct}%"></div>
          </div>
          <div class="hm-quality-bar">${qualityBarHtml(char.bag.by_quality, bagTotal)}</div>
          <div class="hm-char-count">${bagTotal} items in bags</div>
        `;
        bagGrid.appendChild(bagCard);

        // --- Bank card ---
        const bankTotal = char.bank.total;
        const bankCard = document.createElement('div');
        bankCard.className = 'hm-char-card';
        bankCard.innerHTML = `
          <div class="hm-char-header">
            <img class="hm-char-icon" src="${iconSrc}" alt="${escapeHtml(char.name)}"
                 onerror="this.style.display='none'">
            <div class="hm-char-info">
              <div class="hm-char-name">${escapeHtml(char.name)}</div>
              <div class="hm-char-meta">${escapeHtml(char.realm)} • ${escapeHtml(char.faction || '')}</div>
            </div>
          </div>
          <div class="hm-quality-bar">${bankTotal ? qualityBarHtml(char.bank.by_quality, bankTotal) : '<div class="hm-bar-empty">No bank data</div>'}</div>
          <div class="hm-char-count">${bankTotal} items in bank</div>
        `;
        bankGrid.appendChild(bankCard);
      });
    }

    // ---- Top items list (DOM-built for tooltips) ----
    if (top_items && top_items.length > 0) {
      const topList = container.querySelector('#hm-top-items');
      top_items.forEach((item, idx) => {
        const qualityClass = `quality-${(item.quality_name || 'common').toLowerCase()}`;
        const color = qualityColors[item.quality_name] || '#fff';
        const el = document.createElement('div');
        el.className = 'hm-top-item';
        el.innerHTML = `
          <div class="hm-top-rank">#${idx + 1}</div>
          <img class="hm-top-icon"
               src="/icon.php?type=item&id=${item.item_id}&name=${encodeURIComponent(item.name)}&size=large&icon=${encodeURIComponent(item.icon || '')}"
               alt="${escapeHtml(item.name)}"
               onerror="this.src='/icon.php?type=item&id=0&name=Unknown&size=large'">
          <div class="hm-top-info">
            <div class="hm-top-name ${qualityClass}">${escapeHtml(item.name)}</div>
            <div class="hm-top-meta">
              <span class="hm-top-quality" style="color:${color}">${item.quality_name}</span>
              <span class="hm-top-chars">Held by ${item.held_by} character${item.held_by !== 1 ? 's' : ''}</span>
            </div>
          </div>
          <div class="hm-top-count">×${item.total_count.toLocaleString()}</div>
        `;
        if (window.WDTooltip && item.item_id) {
          WDTooltip.attach(el, { item_id: item.item_id, name: item.name, icon: item.icon }, null);
        }
        topList.appendChild(el);
      });
    }
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
        
        ${summary.total_alerts === 0 ? '<p class="empty-state">No alerts configured. Set up alerts to monitor auctions!</p>' : ''}
      </div>
    `;
  }

/* ============================== WORKSHOP RENDERER ============================== */
  
  function renderWorkshop(container, data) {
    if (!data || !data.characters) {
      showError(container, 'No workshop data available');
      return;
    }

    const { characters, summary } = data;

    // Create the workshop HTML
    container.innerHTML = `
      <div class="workshop-container">
        <div class="workshop-header">
          <div>
            <div class="workshop-title">🔨 Profession Workshop</div>
            <div class="workshop-subtitle">Browse your characters' crafting abilities and find who can make what you need</div>
          </div>
          <div class="workshop-stats">
            <div class="workshop-stat">
              <div class="workshop-stat-value">${summary.total_characters}</div>
              <div class="workshop-stat-label">Characters</div>
            </div>
            <div class="workshop-stat">
              <div class="workshop-stat-value">${summary.total_professions}</div>
              <div class="workshop-stat-label">Professions</div>
            </div>
            <div class="workshop-stat">
              <div class="workshop-stat-value">${summary.total_recipes}</div>
              <div class="workshop-stat-label">Recipes</div>
            </div>
          </div>
        </div>

        <div class="workshop-filters">
          <div class="filter-group">
            <label class="filter-label">Search Recipes</label>
            <input type="text" class="workshop-filter-input" id="workshop-recipe-search" placeholder="Search for a recipe or item...">
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Profession</label>
            <select class="workshop-filter-select" id="workshop-profession-filter">
              <option value="">All Professions</option>
              ${Object.keys(summary.professions_breakdown).sort().map(prof => 
                `<option value="${escapeHtml(prof)}">${escapeHtml(prof)} (${summary.professions_breakdown[prof]})</option>`
              ).join('')}
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Recipe Difficulty</label>
            <select class="workshop-filter-select" id="workshop-recipe-type-filter">
              <option value="">All Difficulties</option>
              <option value="trivial">Trivial (Gray)</option>
              <option value="easy">Easy (Green)</option>
              <option value="medium">Medium (Yellow)</option>
              <option value="optimal">Optimal (Orange)</option>
              <option value="difficult">Difficult (Red)</option>
            </select>
          </div>
        </div>

        <div class="workshop-grid" id="workshop-grid">
          ${characters.map(char => renderCharacterCard(char)).join('')}
        </div>

        <div class="no-results" id="workshop-no-results" style="display: none;">
          <div style="font-size: 48px; margin-bottom: 20px;">🔍</div>
          <div>No matching recipes found</div>
          <div style="font-size: 14px; margin-top: 10px; color: #666;">Try adjusting your filters or search terms</div>
        </div>
      </div>
    `;

    // Set up filter event listeners
    setupWorkshopFilters();
  }

  function renderCharacterCard(char) {
    return `
      <div class="character-workshop-card" 
           data-character-id="${char.id}"
           data-professions='${JSON.stringify(char.professions.map(p => p.name))}'>
        
        <div class="character-workshop-header">
          <img class="character-class-icon" 
               src="/icon.php?name=${encodeURIComponent(char.name)}&realm=${encodeURIComponent(char.realm)}&type=avatar" 
               alt="${escapeHtml(char.name)}">
          <div class="character-workshop-info">
            <div class="character-workshop-name">${escapeHtml(char.name)}</div>
            <div class="character-workshop-realm">
              ${escapeHtml(char.realm)} • 
              ${escapeHtml(char.faction)} • 
              Level ${char.level || '?'}
            </div>
          </div>
        </div>

        <div class="profession-badges">
          ${char.professions.map(prof => `
            <div class="profession-badge" data-profession="${escapeHtml(prof.name)}">
              <span>${escapeHtml(prof.name)}</span>
              <span class="profession-badge-skill">${prof.rank}/${prof.max_rank}</span>
            </div>
          `).join('')}
        </div>

        ${Object.entries(char.recipes_by_profession).map(([profession, recipes]) => `
          <div class="profession-section" data-profession="${escapeHtml(profession)}">
            <button class="expand-toggle" onclick="window.toggleWorkshopRecipes(this)">
              View ${escapeHtml(profession)} Recipes (${recipes.length})
            </button>
            
            <div class="recipe-list">
              <div class="recipe-stats">
                <div class="recipe-stat">
                  Total: <span class="recipe-stat-value">${recipes.length}</span>
                </div>
              </div>
              ${recipes.map(recipe => renderRecipeItem(recipe)).join('')}
            </div>
          </div>
        `).join('')}
      </div>
    `;
  }

  function renderRecipeItem(recipe) {
    const recipeType = recipe.type || '';
    const typeLower = recipeType.toLowerCase();
    let difficultyClass = 'unknown';
    
    if (typeLower.includes('trivial')) difficultyClass = 'trivial';
    else if (typeLower.includes('easy')) difficultyClass = 'easy';
    else if (typeLower.includes('medium')) difficultyClass = 'medium';
    else if (typeLower.includes('optimal')) difficultyClass = 'optimal';
    else if (typeLower.includes('difficult')) difficultyClass = 'difficult';

    return `
      <div class="recipe-item" 
           data-recipe-name="${escapeHtml(recipe.name.toLowerCase())}"
           data-recipe-type="${difficultyClass}">
        <div class="recipe-name">${escapeHtml(recipe.name)}</div>
        ${recipeType ? `<span class="recipe-type ${difficultyClass}">${escapeHtml(recipeType)}</span>` : ''}
        ${recipe.cooldown_text ? `<div class="recipe-cooldown">⏱️ ${escapeHtml(recipe.cooldown_text)}</div>` : ''}
      </div>
    `;
  }

  function toggleWorkshopRecipes(button) {
    const recipeList = button.nextElementSibling;
    const isExpanded = recipeList.classList.contains('expanded');
    
    if (isExpanded) {
      recipeList.classList.remove('expanded');
      button.textContent = button.textContent.replace('Hide', 'View');
    } else {
      recipeList.classList.add('expanded');
      button.textContent = button.textContent.replace('View', 'Hide');
    }
  }

  function setupWorkshopFilters() {
    const searchInput = document.getElementById('workshop-recipe-search');
    const professionFilter = document.getElementById('workshop-profession-filter');
    const recipeTypeFilter = document.getElementById('workshop-recipe-type-filter');
    
    if (!searchInput || !professionFilter || !recipeTypeFilter) return;

    const filterWorkshop = () => {
      const searchTerm = searchInput.value.toLowerCase();
      const selectedProfession = professionFilter.value;
      const selectedType = recipeTypeFilter.value;
      
      const grid = document.getElementById('workshop-grid');
      const noResults = document.getElementById('workshop-no-results');
      const cards = grid.querySelectorAll('.character-workshop-card');
      let visibleCount = 0;
      
      cards.forEach(card => {
        let shouldShow = false;
        const professions = JSON.parse(card.dataset.professions || '[]');
        
        // Check if character has the selected profession
        const hasProfession = !selectedProfession || professions.includes(selectedProfession);
        
        if (!hasProfession) {
          card.style.display = 'none';
          return;
        }
        
        // Check each recipe
        const recipeItems = card.querySelectorAll('.recipe-item');
        let visibleRecipes = 0;
        
        recipeItems.forEach(recipeItem => {
          const recipeName = recipeItem.dataset.recipeName || '';
          const recipeType = recipeItem.dataset.recipeType || '';
          
          const matchesSearch = !searchTerm || recipeName.includes(searchTerm);
          const matchesType = !selectedType || recipeType === selectedType;
          
          const professionSection = recipeItem.closest('.profession-section');
          const recipeProfession = professionSection ? professionSection.dataset.profession : '';
          const matchesProfession = !selectedProfession || recipeProfession === selectedProfession;
          
          if (matchesSearch && matchesType && matchesProfession) {
            recipeItem.style.display = 'block';
            visibleRecipes++;
            shouldShow = true;
          } else {
            recipeItem.style.display = 'none';
          }
        });
        
        // Show/hide profession sections
        const professionSections = card.querySelectorAll('.profession-section');
        professionSections.forEach(section => {
          const sectionProfession = section.dataset.profession;
          const matchesProfession = !selectedProfession || sectionProfession === selectedProfession;
          const hasVisibleRecipes = Array.from(section.querySelectorAll('.recipe-item'))
            .some(item => item.style.display !== 'none');
          
          if (matchesProfession && (hasVisibleRecipes || !searchTerm)) {
            section.style.display = 'block';
          } else {
            section.style.display = 'none';
          }
        });
        
        if (shouldShow || (!searchTerm && !selectedType)) {
          card.style.display = 'block';
          visibleCount++;
        } else {
          card.style.display = 'none';
        }
      });
      
      // Show/hide no results message
      if (visibleCount === 0) {
        grid.style.display = 'none';
        noResults.style.display = 'block';
      } else {
        grid.style.display = 'grid';
        noResults.style.display = 'none';
      }
    };

    searchInput.addEventListener('input', filterWorkshop);
    professionFilter.addEventListener('change', filterWorkshop);
    recipeTypeFilter.addEventListener('change', filterWorkshop);
  }

  // Make toggleWorkshopRecipes globally available
  window.toggleWorkshopRecipes = toggleWorkshopRecipes;

/* ============================== CHARACTER JOURNEY TIMELINE RENDERER ============================== */
  
  function renderTimeline(container, data) {
    if (!data || !data.timeline) {
      showError(container, 'No timeline data available');
      return;
    }

    const { characters, timeline, summary } = data;
    
    // Character selector and filter controls
    const characterOptions = characters.map(char => 
      `<option value="${char.character.id}">${escapeHtml(char.character.name)} - ${escapeHtml(char.character.realm)}</option>`
    ).join('');

    container.innerHTML = `
      <div class="timeline-container">
        <!-- Header with summary -->
        <div class="timeline-header">
          <div class="timeline-title">
            <h3>📖 Your Epic Journey</h3>
            <div class="timeline-subtitle">A chronicle of adventures across ${summary.total_characters} characters</div>
          </div>
          
          <div class="timeline-stats">
            <div class="timeline-stat">
              <div class="stat-value">${summary.total_milestones}</div>
              <div class="stat-label">Total Milestones</div>
            </div>
            <div class="timeline-stat">
              <div class="stat-value">${formatTimelineDate(summary.journey_started)}</div>
              <div class="stat-label">Journey Started</div>
            </div>
            <div class="timeline-stat">
              <div class="stat-value">${formatTimelineDate(summary.latest_activity)}</div>
              <div class="stat-label">Latest Milestone</div>
            </div>
          </div>
        </div>

        <!-- Character Quick Stats -->
        <div class="character-quick-stats">
          <h4>Character Highlights</h4>
          <div class="character-highlights-grid">
            ${characters.slice(0, 6).map(char => `
              <div class="character-highlight" data-character-id="${char.character.id}">
                <div class="character-info">
                  <div class="character-name">${escapeHtml(char.character.name)}</div>
                  <div class="character-details">${escapeHtml(char.character.race)} ${escapeHtml(char.character.class)} • ${escapeHtml(char.character.realm)}</div>
                </div>
                <div class="character-milestone-count">
                  <div class="milestone-number">${char.milestone_count}</div>
                  <div class="milestone-label">milestones</div>
                </div>
              </div>
            `).join('')}
          </div>
        </div>

        <!-- Timeline Controls -->
        <div class="timeline-controls">
          <div class="timeline-filters">
            <select id="timeline-character-filter" class="timeline-filter-select">
              <option value="">All Characters</option>
              ${characterOptions}
            </select>
            
            <select id="timeline-type-filter" class="timeline-filter-select">
              <option value="">All Events</option>
              <option value="character_created">Character Created</option>
              <option value="level_milestone">Level Milestones</option>
              <option value="guild_joined">Guild Events</option>
              <option value="first_encounter">Boss Encounters</option>
              <option value="achievement_unlocked">Achievements</option>
              <option value="profession_milestone">Profession Mastery</option>
            </select>
            
            <select id="timeline-period-filter" class="timeline-filter-select">
              <option value="">All Time</option>
              <option value="30">Last 30 Days</option>
              <option value="90">Last 3 Months</option>
              <option value="365">Last Year</option>
            </select>
          </div>
        </div>

        <!-- Timeline Visualization -->
        <div class="timeline-visualization">
          <div class="timeline-line"></div>
          <div class="timeline-events" id="timeline-events">
            ${renderTimelineEvents(timeline)}
          </div>
          
          ${timeline.length === 0 ? `
            <div class="timeline-empty">
              <div class="timeline-empty-icon">📚</div>
              <div class="timeline-empty-title">Your journey awaits!</div>
              <div class="timeline-empty-description">Complete achievements, level up, and join guilds to build your epic story.</div>
            </div>
          ` : ''}
        </div>
      </div>
    `;

    // Set up timeline filtering
    setupTimelineFilters();
    
    // Trigger animations after a brief delay to ensure DOM is ready
    setTimeout(() => {
      triggerTimelineAnimations();
    }, 100);
  }

  function triggerTimelineAnimations() {
    const events = document.querySelectorAll('.timeline-event');
    events.forEach((event, index) => {
      setTimeout(() => {
        event.style.opacity = '1';
        event.classList.add('timeline-event-visible');
      }, index * 50); // Stagger the animations
    });
  }

  function renderTimelineEvents(timeline) {
    return timeline.map((event, index) => `
      <div class="timeline-event ${event.type}" 
           data-character-id="${event.character.id}"
           data-event-type="${event.type}"
           data-timestamp="${event.timestamp}"
           style="animation-delay: ${Math.min(index * 0.05, 1)}s; opacity: 0;">
        
        <div class="timeline-event-marker">
          <div class="timeline-event-icon">${event.details.icon}</div>
        </div>
        
        <div class="timeline-event-content">
          <div class="timeline-event-header">
            <div class="timeline-event-title">${escapeHtml(event.details.title)}</div>
            <div class="timeline-event-date">${formatTimelineDate(event.timestamp)}</div>
          </div>
          
          <div class="timeline-event-details">
            <div class="timeline-event-character">
              <span class="character-name ${event.character.faction.toLowerCase()}">${escapeHtml(event.character.name)}</span>
              <span class="character-realm">${escapeHtml(event.character.realm)}</span>
            </div>
            <div class="timeline-event-description">${escapeHtml(event.details.description)}</div>
          </div>
        </div>
      </div>
    `).join('');
  }

  function formatTimelineDate(timestamp) {
    if (!timestamp) return '—';
    try {
      const date = new Date(timestamp * 1000);
      return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric' 
      });
    } catch {
      return '—';
    }
  }

  function setupTimelineFilters() {
    const characterFilter = document.getElementById('timeline-character-filter');
    const typeFilter = document.getElementById('timeline-type-filter');
    const periodFilter = document.getElementById('timeline-period-filter');
    
    if (!characterFilter || !typeFilter || !periodFilter) return;

    const filterTimeline = () => {
      const selectedCharacter = characterFilter.value;
      const selectedType = typeFilter.value;
      const selectedPeriod = periodFilter.value;
      
      const events = document.querySelectorAll('.timeline-event');
      const now = Math.floor(Date.now() / 1000);
      const periodLimit = selectedPeriod ? now - (parseInt(selectedPeriod) * 24 * 60 * 60) : null;
      
      let visibleCount = 0;
      
      events.forEach((event) => {
        const characterId = event.dataset.characterId;
        const eventType = event.dataset.eventType;
        const timestamp = parseInt(event.dataset.timestamp);
        
        let shouldShow = true;
        
        // Filter by character
        if (selectedCharacter && characterId !== selectedCharacter) {
          shouldShow = false;
        }
        
        // Filter by event type
        if (selectedType && eventType !== selectedType) {
          shouldShow = false;
        }
        
        // Filter by time period
        if (periodLimit && timestamp < periodLimit) {
          shouldShow = false;
        }
        
        if (shouldShow) {
          event.style.display = 'block';
          visibleCount++;
        } else {
          event.style.display = 'none';
          event.classList.remove('timeline-event-visible');
          event.style.opacity = '0';
        }
      });
      
      // Re-trigger animations for visible events
      setTimeout(() => {
        const visibleEvents = Array.from(events).filter(event => event.style.display !== 'none');
        visibleEvents.forEach((event, index) => {
          setTimeout(() => {
            event.style.opacity = '1';
            event.classList.add('timeline-event-visible');
          }, index * 50);
        });
      }, 100);
      
      // Show/hide timeline line
      const timelineLine = document.querySelector('.timeline-line');
      if (timelineLine) {
        timelineLine.style.display = visibleCount > 0 ? 'block' : 'none';
      }
    };

    characterFilter.addEventListener('change', filterTimeline);
    typeFilter.addEventListener('change', filterTimeline);
    periodFilter.addEventListener('change', filterTimeline);
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
      const date = new Date(dateStr);
      return date.toLocaleString();
    } catch {
      return dateStr;
    }
  }

  function formatDuration(seconds) {
    if (!seconds) return '—';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
  }

  /* ============================== Start - SPA Compatible ============================== */
  
  // Listen for the SPA's section-loaded event
  document.addEventListener('whodat:section-loaded', function(e) {
    log('Section loaded event received:', e.detail);
    if (e.detail && e.detail.section === 'bazaar') {
      log('Bazaar section loaded, initializing...');
      // Small delay to ensure DOM is fully rendered
      setTimeout(init, 50);
    } else if (state.initialized) {
      // User navigated away from Bazaar, clean up
      log('Left Bazaar section, cleaning up...');
      cleanup();
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