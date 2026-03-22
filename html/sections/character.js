// sections/character.js - Character progression and stats
(() => {
  'use strict';
  
  const q = (sel, ctx = document) => ctx.querySelector(sel);
  const qa = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const log = (...a) => console.log('[character]', ...a);
  
  // ===== UTILITY FUNCTIONS =====
  
  function formatNumber(num) {
    return num.toLocaleString();
  }
  
  function formatDate(timestamp) {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString();
  }
  
  // Sparkline chart generator
  function createSparkline(data, width = 180, height = 40) {
    if (!data || data.length < 2) return '';
    
    const values = data.map(d => d.value);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    
    const points = data.map((d, i) => {
      const x = (i / (data.length - 1)) * width;
      const y = height - ((d.value - min) / range) * height;
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
    
    return `
      <svg viewBox="0 0 ${width} ${height}" preserveAspectRatio="none" 
           style="width: 100%; height: 40px; display: block;">
        <polyline points="${points}" 
                  fill="none" 
                  stroke="currentColor" 
                  stroke-width="2" 
                  stroke-linejoin="round" 
                  stroke-linecap="round" 
                  opacity="0.8"/>
      </svg>
    `;
  }
  
  // ===== TAB NAVIGATION SETUP =====
  
  function setupTabs(root) {
    const navButtons = qa('.nav-tab', root);
    const tabContents = qa('.tab-content', root);
    
    navButtons.forEach(btn => {
      btn.addEventListener('click', () => {
        const targetTab = btn.dataset.tab;
        
        // Update nav buttons
        navButtons.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Update tab contents
        tabContents.forEach(content => {
          if (content.id === `${targetTab}-tab`) {
            content.classList.add('active');
          } else {
            content.classList.remove('active');
          }
        });
      });
    });
  }
  
  // ===== OVERVIEW TAB =====
  
  function renderOverviewTab(data) {
    const stats = data.overview?.stats || [];
    const timeseries = data.overview?.timeseries || {};
    
    if (stats.length === 0) {
      return `
        <div class="tab-content active" id="overview-tab">
          <div class="no-data">
            <p>No character growth data available yet.</p>
            <p class="muted">Stats will appear here as your character progresses.</p>
          </div>
        </div>
      `;
    }
    
    const statCards = stats.map(stat => {
      const changeClass = stat.change > 0 ? 'positive' : stat.change < 0 ? 'negative' : 'neutral';
      const changeSign = stat.change > 0 ? '+' : '';
      const sparklineData = timeseries[stat.label.toLowerCase().replace(' ', '')] || 
                            timeseries[stat.label.toLowerCase().replace(' ap', '').replace(' power', '')] ||
                            timeseries[stat.label.toLowerCase().split(' ')[0]];
      
      return `
        <div class="stat-card">
          <div class="stat-header">
            <span class="stat-icon">${stat.icon}</span>
            <span class="stat-label">${stat.label}</span>
          </div>
          <div class="stat-value">${formatNumber(stat.current)}</div>
          <div class="stat-change ${changeClass}">
            ${changeSign}${formatNumber(stat.change)} total gain
          </div>
          ${sparklineData ? `
            <div class="stat-sparkline">
              ${createSparkline(sparklineData)}
            </div>
          ` : ''}
        </div>
      `;
    }).join('');
    
    return `
      <div class="tab-content active" id="overview-tab">
        <div class="overview-header">
          <h2>📈 Character Growth</h2>
          <p class="subtitle">Track your character's progression over time</p>
        </div>
        
        <div class="stats-grid">
          ${statCards}
        </div>
        
        <div class="overview-info">
          <div class="info-card">
            <div class="info-icon">💡</div>
            <div class="info-content">
              <h3>Growth Tracking</h3>
              <p>These stats show your character's total improvement since first tracked. 
              Sparklines visualize the progression timeline, helping you see how your character 
              has developed through gear upgrades, level gains, and other improvements.</p>
            </div>
          </div>
        </div>
      </div>
    `;
  }
  
  // ===== GEAR TAB =====
  
  function renderGearTab(data) {
    const gear = data.gear || [];
    const identity = data.identity || {};
    
    if (gear.length === 0) {
      return `
        <div class="tab-content" id="gear-tab">
          <div class="no-data">
            <p>No gear data available.</p>
          </div>
        </div>
      `;
    }
    
    const qualityColors = {
      'Poor': '#9d9d9d',
      'Common': '#ffffff',
      'Uncommon': '#1eff00',
      'Rare': '#0070dd',
      'Epic': '#a335ee',
      'Legendary': '#ff8000',
      'Artifact': '#e6cc80',
      'Heirloom': '#00ccff'
    };
    
    // Create a map of gear by slot (normalize to lowercase)
    const gearBySlot = {};
    gear.forEach(item => {
      const normalizedSlot = (item.slot || '').toLowerCase();
      gearBySlot[normalizedSlot] = item;
    });
    
    // Debug: log what slots we have
    console.log('[character] Gear slots found:', Object.keys(gearBySlot));
    
    // Calculate average item level and find outliers
    const ilvls = gear.map(g => g.item_level).filter(lvl => lvl > 0);
    const avgIlvl = ilvls.length > 0 ? ilvls.reduce((a, b) => a + b, 0) / ilvls.length : 0;
    const outlierThreshold = 15; // Items more than 15 ilvls below average
    const outliers = gear.filter(g => g.item_level > 0 && (avgIlvl - g.item_level) > outlierThreshold);
    
    // Render individual gear slot
    function renderSlot(slotKey, label) {
      const item = gearBySlot[slotKey];
      if (!item) {
        return `
          <div class="paperdoll-slot empty" data-looking-for="${slotKey}">
            <div class="slot-icon">⬚</div>
            <div class="slot-label">${label}</div>
          </div>
        `;
      }
      
      const color = qualityColors[item.quality] || '#ffffff';
      const isOutlier = outliers.some(o => (o.slot || '').toLowerCase() === slotKey);
      const iconUrl = item.item_id 
        ? `/icon.php?type=item&id=${item.item_id}&name=${encodeURIComponent(item.item_name)}&size=medium`
        : '';
      
      return `
        <div class="paperdoll-slot filled ${isOutlier ? 'outlier' : ''}" data-slot="${slotKey}">
          ${iconUrl ? `<div class="slot-icon" style="background-image: url(&quot;${iconUrl}&quot;)"></div>` : '<div class="slot-icon">📦</div>'}
          <div class="slot-overlay">
            <div class="slot-name" style="color: ${color}">${item.item_name}</div>
            <div class="slot-ilvl">iLvl ${item.item_level}</div>
          </div>
        </div>
      `;
    }
    
    // WoW-style paperdoll layout
    const paperdoll = `
      <div class="paperdoll-container" data-race="${(identity.race || '').toLowerCase()}" data-sex="${(identity.sex || '').toLowerCase()}">
        <div class="paperdoll-wp-img"></div>
        <div class="paperdoll-left">
          ${renderSlot('head', 'Head')}
          ${renderSlot('neck', 'Neck')}
          ${renderSlot('shoulder', 'Shoulder')}
          ${renderSlot('back', 'Back')}
          ${renderSlot('chest', 'Chest')}
          ${renderSlot('shirt', 'Shirt')}
          ${renderSlot('tabard', 'Tabard')}
          ${renderSlot('wrist', 'Wrist')}
        </div>
        
        <div class="paperdoll-center"></div>
        
        <div class="paperdoll-right">
          ${renderSlot('hands', 'Hands')}
          ${renderSlot('waist', 'Waist')}
          ${renderSlot('legs', 'Legs')}
          ${renderSlot('feet', 'Feet')}
          ${renderSlot('finger1', 'Ring')}
          ${renderSlot('finger2', 'Ring')}
          ${renderSlot('trinket1', 'Trinket')}
          ${renderSlot('trinket2', 'Trinket')}
        </div>
        
        <div class="avg-ilvl-display">
          <div class="avg-ilvl-label">Avg iLvl</div>
          <div class="avg-ilvl-value">${Math.round(avgIlvl)}</div>
        </div>
      </div>
      
      <div class="paperdoll-bottom">
        ${renderSlot('mainhand', 'Main Hand')}
        ${renderSlot('offhand', 'Off Hand')}
        ${renderSlot('ranged', 'Ranged')}
      </div>
    `;
    
    // Outlier warning section
    const outlierSection = outliers.length > 0 ? `
      <div class="gear-outliers" data-wps-outlier="true">
        <div class="outlier-header">
          <span class="outlier-icon">⚠️</span>
          <h3>Upgradeable Gear</h3>
        </div>
        <div class="outlier-description">
          These items are significantly below your average item level (${Math.round(avgIlvl)}):
        </div>
        <div class="outlier-list">
          ${outliers.map(item => {
            const color = qualityColors[item.quality] || '#ffffff';
            const diff = Math.round(avgIlvl - item.item_level);
            return `
              <div class="outlier-item">
                <div class="outlier-slot">${item.slot}</div>
                <div class="outlier-name" style="color: ${color}">${item.item_name}</div>
                <div class="outlier-ilvl">iLvl ${item.item_level}</div>
                <div class="outlier-diff">-${diff} below avg</div>
              </div>
            `;
          }).join('')}
        </div>
      </div>
    ` : '';
    
    return `
      <div class="tab-content" id="gear-tab">
        <div class="gear-header">
          <h2>⚔️ Current Equipment</h2>
          <p class="subtitle">Character gear and stats</p>
        </div>
        
        ${paperdoll}
        ${outlierSection}
      </div>
    `;
  }
  
  // ===== WPS WALLPAPER LOADER =====

  async function loadWpsImages(root) {
    const container = root.querySelector('.paperdoll-container');
    if (!container) return;

    const race = container.dataset.race || '';
    const sex  = container.dataset.sex  || '';

    try {
      const res = await fetch(`/wps_images.php?race=${encodeURIComponent(race)}&sex=${encodeURIComponent(sex)}`);
      if (!res.ok) return;
      const json = await res.json();
      const images = json.images || [];
      if (images.length === 0) return;

      // Pick a random image for the main paperdoll background
      const modelImg = images[Math.floor(Math.random() * images.length)];
      const imgEl = container.querySelector('.paperdoll-wp-img');
      if (imgEl) {
        applyWpsImage(imgEl, container, modelImg);
        container.classList.add('has-wp-image');
      }

      // Pick a different random image for the outlier section
      const outlierEl = root.querySelector('.gear-outliers[data-wps-outlier]');
      if (outlierEl && images.length > 0) {
        const outlierImg = images[Math.floor(Math.random() * images.length)];
        outlierEl.style.setProperty('--wps-bg', `url('${outlierImg}')`);
        outlierEl.classList.add('has-wp-image');
      }
    } catch (e) {
      // Silently fail — default background remains
    }
  }

  // Fit the image to fill the container without distortion.
  // Wide image (wider aspect than container) → fit to height, crop sides.
  // Tall image (taller aspect than container) → fit to width, crop top/bottom.
  // Near-square or matched → cover (fills either axis).
  function applyWpsImage(imgEl, container, url) {
    const img = new Image();
    img.onload = function () {
      const imgRatio = img.naturalWidth / img.naturalHeight;
      const containerW = container.offsetWidth  || 900;
      const containerH = container.offsetHeight || 400;
      const containerRatio = containerW / containerH;

      // Ratio of ratios: how much wider the image is compared to the container
      const r = imgRatio / containerRatio;

      let size, position;

      if (r > 1.15) {
        // Image significantly wider than container — fit height, let sides overflow/crop
        size     = 'auto 100%';
        position = 'center center';
      } else if (r < 0.85) {
        // Image significantly taller than container — fit width, let top/bottom overflow/crop
        // Anchor to top so the character's face/upper body is visible
        size     = '100% auto';
        position = 'center center';
      } else {
        // Aspect ratios are close — cover fills cleanly
        size     = 'cover';
        position = 'center center';
      }

      imgEl.style.backgroundImage    = `url('${url}')`;
      imgEl.style.backgroundSize     = size;
      imgEl.style.backgroundPosition = position;
    };
    img.onerror = function () {
      // Fallback: just set the URL with cover
      imgEl.style.backgroundImage = `url('${url}')`;
    };
    img.src = url;
  }

  // ===== STATS TAB =====

  function renderStatsTab(data) {
    const stats = data.stats || [];
    
    if (stats.length === 0) {
      return `
        <div class="tab-content" id="stats-tab">
          <div class="no-data">
            <p>No character stats available.</p>
          </div>
        </div>
      `;
    }
    
    // Group stats into categories
    const primaryStats = stats.filter(s => 
      ['Strength', 'Agility', 'Stamina', 'Intellect', 'Spirit'].includes(s.label)
    );
    
    const combatStats = stats.filter(s => 
      ['Crit %', 'Dodge %', 'Parry %', 'Block %'].includes(s.label)
    );
    
    const renderStatGroup = (groupStats, title) => {
      if (groupStats.length === 0) return '';
      
      const statCards = groupStats.map(stat => `
        <div class="stat-item">
          <span class="stat-icon">${stat.icon}</span>
          <div class="stat-details">
            <div class="stat-label">${stat.label}</div>
            <div class="stat-value">${stat.value % 1 === 0 ? formatNumber(stat.value) : stat.value.toFixed(2)}</div>
          </div>
        </div>
      `).join('');
      
      return `
        <div class="stat-group">
          <h3>${title}</h3>
          <div class="stat-items">
            ${statCards}
          </div>
        </div>
      `;
    };
    
    return `
      <div class="tab-content" id="stats-tab">
        <div class="stats-header">
          <h2>📊 Character Stats</h2>
          <p class="subtitle">Current character attributes</p>
        </div>
        
        ${renderStatGroup(primaryStats, 'Primary Attributes')}
        ${renderStatGroup(combatStats, 'Combat Ratings')}
      </div>
    `;
  }
  
  // ===== CURRENCIES TAB =====
  
  function renderCurrenciesTab(data) {
    const currencies = data.currencies || [];
    
    if (currencies.length === 0) {
      return `
        <div class="tab-content" id="currencies-tab">
          <div class="no-data">
            <p>No currency data available.</p>
          </div>
        </div>
      `;
    }
    
    // Group by category
    const byCategory = {};
    currencies.forEach(currency => {
      const cat = currency.category || 'Other';
      if (!byCategory[cat]) byCategory[cat] = [];
      byCategory[cat].push(currency);
    });
    
    // Render each category
    const categoryBlocks = Object.keys(byCategory).sort().map(category => {
      const items = byCategory[category];
      const rows = items.map(currency => {
        const sparklineSvg = createSparkline(currency.timeseries || []);
        
        return `
          <div class="currency-row">
            <div class="currency-info">
              <div class="currency-name">${currency.name}</div>
              <div class="currency-description">${currency.description || ''}</div>
            </div>
            <div class="currency-visual">
              <div class="currency-sparkline">${sparklineSvg}</div>
              <div class="currency-count">${formatNumber(currency.count)}</div>
            </div>
          </div>
        `;
      }).join('');
      
      return `
        <div class="currency-category">
          <h3 class="category-title">${category}</h3>
          <div class="currency-category-list">
            ${rows}
          </div>
        </div>
      `;
    }).join('');
    
    return `
      <div class="tab-content" id="currencies-tab">
        <div class="currencies-header">
          <h2>💰 Currencies</h2>
          <p class="subtitle">Current currency balances</p>
        </div>
        
        ${categoryBlocks}
      </div>
    `;
  }
  
  // Helper: Create simple sparkline SVG
  function createSparkline(timeseries) {
    if (!timeseries || timeseries.length < 2) {
      return '<svg width="80" height="20"></svg>';
    }
    
    const width = 80;
    const height = 20;
    const padding = 2;
    
    // Extract values
    const values = timeseries.map(d => d.count || 0);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    
    // Create path points
    const points = values.map((val, i) => {
      const x = padding + (i / (values.length - 1)) * (width - padding * 2);
      const y = height - padding - ((val - min) / range) * (height - padding * 2);
      return `${x},${y}`;
    }).join(' ');
    
    return `
      <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
        <polyline
          points="${points}"
          fill="none"
          stroke="#3b82f6"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    `;
  }
  
  // ===== MAIN RENDERER =====
  
  // Store gear data for tooltip attachment
  let _currentGearBySlot = {};
  
  function renderCharacter(data, characterName) {
    const root = q('#tab-character');
    if (!root) return;
    
    const identity = data.identity || {};
    const cid = root.dataset?.characterId;
    
    // Store gear data for tooltip access
    const gear = data.gear || [];
    _currentGearBySlot = {};
    gear.forEach(item => {
      const normalizedSlot = (item.slot || '').toLowerCase();
      _currentGearBySlot[normalizedSlot] = item;
    });
    
    root.innerHTML = `
      <div class="character-header">
        <h1>🎭 ${characterName || identity.name || 'Character'}</h1>
        <p class="subtitle">
          Level ${identity.level || '?'} ${identity.race || ''} ${identity.spec ? identity.spec + ' ' : ''}${identity.class || ''}
          ${identity.guild ? ` &lt;${identity.guild}&gt;` : ''}
        </p>
      </div>
      
      <div class="character-nav">
        <button class="nav-tab active" data-tab="overview">📈 Overview</button>
        <button class="nav-tab" data-tab="gear">⚔️ Gear</button>
        <button class="nav-tab" data-tab="stats">📊 Stats</button>
        <button class="nav-tab" data-tab="currencies">💰 Currencies</button>
      </div>
      
      <div class="character-content">
        ${renderOverviewTab(data)}
        ${renderGearTab(data)}
        ${renderStatsTab(data)}
        ${renderCurrenciesTab(data)}
      </div>
    `;
    
    setupTabs(root);
    
    // Load WPS wallpaper images for the paperdoll model area
    loadWpsImages(root);
    
    // Attach tooltips to gear slots
    if (window.WDTooltip) {
      qa('.paperdoll-slot.filled', root).forEach(slot => {
        const slotKey = slot.dataset.slot;
        const item = _currentGearBySlot[slotKey];
        if (item && item.item_id) {
          WDTooltip.attach(slot, {
            link: item.link || null,
            item_id: item.item_id,
            name: item.item_name,
            icon: item.icon || null
          }, cid);
          slot.style.cursor = 'pointer';
        }
      });
      
      // Also attach to outlier items
      qa('.outlier-item', root).forEach(outlierRow => {
        const slotName = outlierRow.querySelector('.outlier-slot')?.textContent;
        const normalizedSlot = slotName?.toLowerCase();
        const item = _currentGearBySlot[normalizedSlot];
        if (item && item.item_id) {
          WDTooltip.attach(outlierRow, {
            link: item.link || null,
            item_id: item.item_id,
            name: item.item_name,
            icon: item.icon || null
          }, cid);
          outlierRow.style.cursor = 'pointer';
        }
      });
    }
    
    // Stagger animation for stat cards
    qa('.stat-card', root).forEach((card, i) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      setTimeout(() => {
        card.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, 50 + i * 80);
    });
  }
  
  // ===== LOAD DATA =====
  
  async function loadCharacter() {
    const root = q('#tab-character');
    if (!root) return;
    
    const cid = root.dataset?.characterId;
    const name = root.dataset?.charName || '';
    
    root.innerHTML = `
      <div class="muted" style="text-align: center; padding: 40px 0;">
        <div style="font-size: 2rem; margin-bottom: 16px;">⏳</div>
        <div>Loading character data...</div>
      </div>
    `;
    
    if (!cid) {
      root.innerHTML = '<div class="muted" style="text-align: center; padding: 40px 0;">No character selected.</div>';
      return;
    }
    
    try {
      const res = await fetch(`/sections/character-data.php?character_id=${encodeURIComponent(cid)}`, {
        credentials: 'include'
      });
      
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      
      const data = await res.json();
      renderCharacter(data, name);
    } catch (err) {
      log('Error loading character data:', err);
      root.innerHTML = `
        <div class="muted" style="text-align: center; padding: 40px 0; color: #d32f2f;">
          Failed to load character data: ${err.message}
        </div>
      `;
    }
  }
  
  // ===== EVENT LISTENERS =====
  
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'character') {
      loadCharacter();
    }
  });
  
  document.addEventListener('whodat:character-changed', () => {
    if ((history?.state?.section || 'dashboard') === 'character') {
      loadCharacter();
    }
  });
  
  // Initial load if on character tab
  if (q('#tab-character')) {
    loadCharacter();
  }
  
  log('Character module ready');
})();