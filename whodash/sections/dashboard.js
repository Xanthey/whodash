/* eslint-disable no-console */
/* WhoDASH Dashboard — Tabbed Character Overview */
(() => {
  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[dashboard]', ...a);

  function formatGold(copper) {
    const g = Math.floor(copper / 10000);
    const s = Math.floor((copper % 10000) / 100);
    const c = copper % 100;
    const parts = [];
    if (g > 0) parts.push(`<span class="coin coin-gold">${g.toLocaleString()}g</span>`);
    if (s > 0 || (g > 0 && c > 0)) parts.push(`<span class="coin coin-silver">${s}s</span>`);
    if (c > 0 || parts.length === 0) parts.push(`<span class="coin coin-copper">${c}c</span>`);
    return parts.join(' ');
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

  function getSexLabel(sex) {
    const map = { 0: 'Unknown', 1: 'Unknown', 2: 'Male', 3: 'Female' };
    return map[sex] || 'Unknown';
  }

  function calculateTotalPlaytime(data) {
    const sessions = data.sessions || [];
    return sessions.reduce((sum, s) => sum + (s.duration || 0), 0);
  }

  function getCurrentGold(data) {
    const moneyData = data.timeseries?.money || [];
    return moneyData.length > 0 ? moneyData[moneyData.length - 1].value : 0;
  }

  // Quality color mapping
  const QUALITY_COLORS = {
    0: '#9d9d9d', // Poor (gray)
    1: '#ffffff', // Common (white)
    2: '#1eff00', // Uncommon (green)
    3: '#0070dd', // Rare (blue)
    4: '#a335ee', // Epic (purple)
    5: '#ff8000', // Legendary (orange)
    6: '#e6cc80', // Artifact (gold)
    7: '#00ccff'  // Heirloom (cyan)
  };

  // Equipment slot names
  const SLOT_NAMES = {
    0: 'Ammo', 1: 'Head', 2: 'Neck', 3: 'Shoulder', 4: 'Shirt', 5: 'Chest',
    6: 'Waist', 7: 'Legs', 8: 'Feet', 9: 'Wrist', 10: 'Hands', 11: 'Finger',
    12: 'Finger', 13: 'Trinket', 14: 'Trinket', 15: 'Back', 16: 'Main Hand',
    17: 'Off Hand', 18: 'Ranged', 19: 'Tabard'
  };

  // Paper doll slot positions (left/right layout)
  const PAPERDOLL_LAYOUT = {
    left: [1, 2, 3, 15, 5, 4, 19, 9],  // Head, Neck, Shoulder, Back, Chest, Shirt, Tabard, Wrist
    right: [10, 6, 7, 8, 11, 12, 13, 14]  // Hands, Waist, Legs, Feet, Finger1, Finger2, Trinket1, Trinket2
  };

  // ===== Tab System =====
  function setupTabs(container) {
    const tabs = qa('.dashboard-tab', container);
    const contents = qa('.dashboard-tab-content', container);

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        contents.forEach(c => c.classList.remove('active'));
        const targetContent = q(`#dash-${target}`, container);
        if (targetContent) targetContent.classList.add('active');
      });
    });
  }

  // ===== CHARACTER CARD (appears above tabs) =====
  function renderCharacterCard(data, charName) {
    const card = document.createElement('div');
    card.className = 'char-profile-card';
    
    const identity = data.identity || {};
    const player = data.player || {};
    
    const levelData = data.timeseries?.level || [];
    const currentLevel = levelData.length > 0 
      ? levelData[levelData.length - 1].value 
      : identity.level || 1;

    const className = identity.className || player.class || 'Unknown';
    const spec = identity.spec || '';
    const race = identity.race || '';
    const sex = identity.sex ? getSexLabel(identity.sex) : '';
    
    let charInfo = `Level ${currentLevel}`;
    if (sex) charInfo += ` ${sex}`;
    if (race) charInfo += ` ${race}`;
    if (spec) charInfo += ` ${spec}`;
    charInfo += ` ${className}`;

    card.innerHTML = `
      <div class="char-header">
        <div class="char-avatar">
          <div class="char-avatar-placeholder">
            ${(charName || 'Character').charAt(0).toUpperCase()}
          </div>
        </div>
        <div class="char-info">
          <h2 class="char-name">${charName || 'Character'}</h2>
          <div class="char-meta">
            <span class="char-identity">${charInfo}</span>
          </div>
          ${identity.guild || player.guild ? `<div class="char-guild">⚔️ ${identity.guild || player.guild}</div>` : ''}
        </div>
      </div>
      <div class="char-stats-grid">
        <div class="char-stat-item">
          <div class="stat-label">Item Level</div>
          <div class="stat-value">${data.avgIlvl || '--'}</div>
        </div>
        <div class="char-stat-item">
          <div class="stat-label">Played Time</div>
          <div class="stat-value">${formatDuration(calculateTotalPlaytime(data))}</div>
        </div>
        <div class="char-stat-item">
          <div class="stat-label">Current Gold</div>
          <div class="stat-value">${formatGold(getCurrentGold(data))}</div>
        </div>
      </div>
    `;
    
    return card;
  }

  // ===== TAB 1: PRESSED C (Original Dashboard) =====
  function renderPressedCTab(data) {
    const container = document.createElement('div');
    container.id = 'dash-pressed-c';
    container.className = 'dashboard-tab-content active';

    // Stats + Progression row
    const midSection = document.createElement('div');
    midSection.className = 'dashboard-section dashboard-row';
    midSection.appendChild(renderStatsDashboard(data));
    midSection.appendChild(renderLevelProgression(data.timeseries?.level || []));
    container.appendChild(midSection);

    // Speed + Milestones row
    const bottomSection = document.createElement('div');
    bottomSection.className = 'dashboard-section dashboard-row';
    bottomSection.appendChild(renderLevelingSpeed(data.timeseries?.level || []));
    bottomSection.appendChild(renderMilestones(data));
    container.appendChild(bottomSection);

    return container;
  }

  function renderStatsDashboard(data) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>📊 Quick Stats</h3>';

    const stats = document.createElement('div');
    stats.className = 'quick-stats-grid';

    const achievements = data.achievements || {};
    const sessions = data.sessions || [];
    const avgSessionLength = sessions.length > 0
      ? sessions.reduce((sum, s) => sum + s.duration, 0) / sessions.length
      : 0;

    stats.innerHTML = `
      <div class="quick-stat">
        <div class="quick-stat-icon">🏆</div>
        <div class="quick-stat-content">
          <div class="quick-stat-value">${achievements.total || 0}</div>
          <div class="quick-stat-label">Achievements</div>
        </div>
      </div>
      <div class="quick-stat">
        <div class="quick-stat-icon">⭐</div>
        <div class="quick-stat-content">
          <div class="quick-stat-value">${achievements.points || 0}</div>
          <div class="quick-stat-label">Achievement Points</div>
        </div>
      </div>
      <div class="quick-stat">
        <div class="quick-stat-icon">🎮</div>
        <div class="quick-stat-content">
          <div class="quick-stat-value">${sessions.length || 0}</div>
          <div class="quick-stat-label">Sessions</div>
        </div>
      </div>
      <div class="quick-stat">
        <div class="quick-stat-icon">⏱️</div>
        <div class="quick-stat-content">
          <div class="quick-stat-value">${formatDuration(avgSessionLength)}</div>
          <div class="quick-stat-label">Avg Session</div>
        </div>
      </div>
    `;

    card.appendChild(stats);
    return card;
  }

  function renderLevelProgression(levelData) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>📈 Level Progression</h3>';

    if (!levelData || levelData.length === 0) {
      card.innerHTML += '<p class="muted">No level data available</p>';
      return card;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'level-chart';
    canvas.width = 600;
    canvas.height = 200;
    card.appendChild(canvas);

    setTimeout(() => renderLevelChart(canvas, levelData), 0);
    return card;
  }

  function renderLevelChart(canvas, levelData) {
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    const padding = 40;
    const plotWidth = width - padding * 2;
    const plotHeight = height - padding * 2;

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, width, height);

    if (levelData.length === 0) return;

    const levels = levelData.map(d => d.value);
    const minLevel = Math.min(...levels);
    const maxLevel = Math.max(...levels);
    const levelRange = maxLevel - minLevel || 1;

    // Grid
    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
      const y = padding + (plotHeight / 5) * i;
      ctx.beginPath();
      ctx.moveTo(padding, y);
      ctx.lineTo(width - padding, y);
      ctx.stroke();
    }

    // Line
    ctx.strokeStyle = '#2456a5';
    ctx.lineWidth = 3;
    ctx.beginPath();

    levelData.forEach((point, idx) => {
      const x = padding + (idx / (levelData.length - 1)) * plotWidth;
      const normalizedLevel = (point.value - minLevel) / levelRange;
      const y = padding + plotHeight - (normalizedLevel * plotHeight);

      if (idx === 0) {
        ctx.moveTo(x, y);
      } else {
        ctx.lineTo(x, y);
      }
    });

    ctx.stroke();

    // Axes
    ctx.strokeStyle = '#374151';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, height - padding);
    ctx.lineTo(width - padding, height - padding);
    ctx.stroke();

    // Y labels
    ctx.fillStyle = '#6b7280';
    ctx.font = '12px system-ui';
    ctx.textAlign = 'right';
    for (let i = 0; i <= 5; i++) {
      const level = Math.round(minLevel + (levelRange / 5) * (5 - i));
      const y = padding + (plotHeight / 5) * i;
      ctx.fillText(level.toString(), padding - 10, y + 4);
    }
  }

  function renderLevelingSpeed(levelData) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>⚡ Leveling Speed</h3>';

    if (!levelData || levelData.length < 2) {
      card.innerHTML += '<p class="muted">Not enough data</p>';
      return card;
    }

    // Calculate time spent at each level and aggregate by level
    const levelTimeMap = {};
    
    for (let i = 1; i < levelData.length; i++) {
      const level = levelData[i - 1].value; // Time spent AT this level before leveling up
      const duration = levelData[i].ts - levelData[i - 1].ts;
      
      if (!levelTimeMap[level]) {
        levelTimeMap[level] = 0;
      }
      levelTimeMap[level] += duration;
    }

    // Convert to array and sort by level (highest first)
    const levelTimes = Object.entries(levelTimeMap)
      .map(([level, duration]) => ({ level: parseInt(level), duration }))
      .sort((a, b) => b.level - a.level);

    // Take last 15 levels or all if less
    const recentLevels = levelTimes.slice(0, 15);
    const maxDuration = Math.max(...recentLevels.map(l => l.duration));

    const barsContainer = document.createElement('div');
    barsContainer.className = 'level-speed-bars';

    recentLevels.forEach(({ level, duration }) => {
      const percentage = (duration / maxDuration) * 100;
      const bar = document.createElement('div');
      bar.className = 'level-speed-bar';
      bar.innerHTML = `
        <div class="level-speed-label">Lvl ${level}</div>
        <div class="level-speed-bar-track">
          <div class="level-speed-bar-fill" style="width: ${percentage}%"></div>
        </div>
        <div class="level-speed-time">${formatDuration(duration)}</div>
      `;
      barsContainer.appendChild(bar);
    });

    card.appendChild(barsContainer);
    return card;
  }

  function renderMilestones(data) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>🏠 Recent Milestones</h3>';

    const milestones = [];
    const levelData = data.timeseries?.level || [];
    const moneyData = data.timeseries?.money || [];
    const achievements = data.achievements || {};
    
    // Level milestones (every 5 levels)
    levelData.forEach((point, idx) => {
      if (point.value % 5 === 0 && point.value > 0) {
        const icon = point.value % 10 === 0 ? '🎯' : '⬆️';
        const importance = point.value % 10 === 0 ? 'high' : 'normal';
        milestones.push({
          icon,
          title: `Level ${point.value}`,
          desc: point.value % 10 === 0 ? `Major milestone reached!` : `Reached level ${point.value}`,
          ts: point.ts,
          importance
        });
      }
    });

    // Gold milestones (100g, 500g, 1000g, etc.)
    const goldMilestones = [1000000, 5000000, 10000000, 50000000, 100000000]; // in copper
    moneyData.forEach((point, idx) => {
      if (idx > 0) {
        const prevValue = moneyData[idx - 1].value;
        goldMilestones.forEach(threshold => {
          if (prevValue < threshold && point.value >= threshold) {
            milestones.push({
              icon: '💰',
              title: `${Math.floor(threshold / 10000)} Gold`,
              desc: `Accumulated ${Math.floor(threshold / 10000)}g`,
              ts: point.ts,
              importance: threshold >= 10000000 ? 'high' : 'normal'
            });
          }
        });
      }
    });

    // Achievement milestones (every 50 achievements)
    if (achievements.total) {
      const achievementMilestones = [10, 25, 50, 100, 150, 200, 250, 500, 1000];
      achievementMilestones.forEach(threshold => {
        if (achievements.total >= threshold) {
          // Use a recent timestamp (we don't have exact timestamp, so approximate)
          const ts = levelData.length > 0 ? levelData[levelData.length - 1].ts : Date.now() / 1000;
          milestones.push({
            icon: '🏆',
            title: `${threshold} Achievements`,
            desc: `Unlocked ${threshold} achievements`,
            ts: ts - (achievementMilestones.indexOf(threshold) * 86400), // Spread them out
            importance: threshold >= 100 ? 'high' : 'normal'
          });
        }
      });
    }

    milestones.sort((a, b) => b.ts - a.ts);

    if (milestones.length === 0) {
      card.innerHTML += '<p class="muted">No milestones yet</p>';
      return card;
    }

    const timeline = document.createElement('div');
    timeline.className = 'milestone-timeline';

    milestones.slice(0, 12).forEach(m => {
      const item = document.createElement('div');
      item.className = `milestone-item ${m.importance === 'high' ? 'milestone-important' : ''}`;
      
      item.innerHTML = `
        <div class="milestone-icon">${m.icon}</div>
        <div class="milestone-content">
          <div class="milestone-title">${m.title}</div>
          <div class="milestone-desc">${m.desc}</div>
        </div>
      `;
      
      timeline.appendChild(item);
    });

    card.appendChild(timeline);
    return card;
  }

  // ===== TAB 2: GEAR =====
  function renderGearTab(data) {
    const container = document.createElement('div');
    container.id = 'dash-gear';
    container.className = 'dashboard-tab-content';

    const section = document.createElement('div');
    section.className = 'dashboard-section';

    // Paper doll full width on top
    section.appendChild(renderPaperDoll(data));
    
    // Upgrade opportunities full width below
    section.appendChild(renderUpgradeOpportunities(data));

    container.appendChild(section);

    return container;
  }

  function renderPaperDoll(data) {
    const card = document.createElement('div');
    card.className = 'dash-card paperdoll-card';
    card.innerHTML = '<h3>⚔️ Equipped Gear</h3>';

    const equipment = data.equipment || {};
    const avgIlvl = data.avgIlvl || 0;

    const paperdoll = document.createElement('div');
    paperdoll.className = 'paperdoll';

    // Left column
    const leftCol = document.createElement('div');
    leftCol.className = 'paperdoll-column';
    PAPERDOLL_LAYOUT.left.forEach(slotId => {
      leftCol.appendChild(renderEquipmentSlot(slotId, equipment, avgIlvl));
    });

    // Center (Main Hand + Off Hand)
    const centerCol = document.createElement('div');
    centerCol.className = 'paperdoll-column paperdoll-center';
    centerCol.appendChild(renderEquipmentSlot(16, equipment, avgIlvl)); // Main Hand
    centerCol.appendChild(renderEquipmentSlot(17, equipment, avgIlvl)); // Off Hand

    // Right column
    const rightCol = document.createElement('div');
    rightCol.className = 'paperdoll-column';
    PAPERDOLL_LAYOUT.right.forEach(slotId => {
      rightCol.appendChild(renderEquipmentSlot(slotId, equipment, avgIlvl));
    });

    paperdoll.appendChild(leftCol);
    paperdoll.appendChild(centerCol);
    paperdoll.appendChild(rightCol);

    card.appendChild(paperdoll);
    return card;
  }

  function renderEquipmentSlot(slotId, equipment, avgIlvl) {
    const slot = document.createElement('div');
    slot.className = 'equipment-slot';

    const item = equipment[slotId];
    const slotName = SLOT_NAMES[slotId] || `Slot ${slotId}`;

    if (!item) {
      slot.classList.add('empty');
      slot.innerHTML = `
        <div class="slot-icon empty-slot">?</div>
        <div class="slot-info">
          <div class="slot-name">${slotName}</div>
          <div class="slot-empty">Empty</div>
        </div>
      `;
      return slot;
    }

    const quality = item.quality || 0;
    const qualityColor = QUALITY_COLORS[quality] || '#9d9d9d';
    // Exclude shirt (slot 4) from upgrade calculations since it has no stats
    const isUpgrade = slotId !== 4 && item.ilvl < avgIlvl - 5; // Flag if 5+ below average

    if (isUpgrade) slot.classList.add('needs-upgrade');

    const iconUrl = item.item_id ? `/icon.php?type=item&id=${item.item_id}&size=medium` : null;

    slot.innerHTML = `
      <div class="slot-icon" style="border-color: ${qualityColor}; background-color: rgba(0, 0, 0, 0.4);">
        ${iconUrl ? `<img src="${iconUrl}" alt="${item.name}" />` : '📦'}
      </div>
      <div class="slot-info" style="background-color: rgba(0, 0, 0, 0.3); padding: 8px; border-radius: 4px;">
        <div class="slot-name" style="color: ${qualityColor}; font-weight: 600; text-shadow: 0 1px 3px rgba(0,0,0,0.8);">${item.name || slotName}</div>
        <div class="slot-ilvl">iLvl ${item.ilvl || '?'}</div>
        ${isUpgrade ? '<div class="upgrade-flag">⬆️ Upgrade</div>' : ''}
      </div>
    `;

    // Tooltip on hover
    slot.title = `${item.name}\niLvl: ${item.ilvl}${item.stats ? '\n' + item.stats : ''}`;

    return slot;
  }

  function renderUpgradeOpportunities(data) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>📈 Upgrade Opportunities</h3>';

    const equipment = data.equipment || {};
    const avgIlvl = data.avgIlvl || 0;

    const upgrades = [];
    Object.keys(equipment).forEach(slotId => {
      const item = equipment[slotId];
      // Exclude shirt (slot 4) since it has no stats
      if (slotId != 4 && item && item.ilvl < avgIlvl - 5) {
        upgrades.push({
          slot: SLOT_NAMES[slotId] || `Slot ${slotId}`,
          name: item.name,
          ilvl: item.ilvl,
          deficit: avgIlvl - item.ilvl,
          quality: item.quality || 0
        });
      }
    });

    upgrades.sort((a, b) => b.deficit - a.deficit);

    if (upgrades.length === 0) {
      card.innerHTML += '<p class="success-message">✔ All gear is within 5 ilvl of your average!</p>';
      return card;
    }

    const list = document.createElement('div');
    list.className = 'upgrade-list';

    upgrades.forEach(up => {
      const qualityColor = QUALITY_COLORS[up.quality] || '#9d9d9d';
      const item = document.createElement('div');
      item.className = 'upgrade-item';
      item.innerHTML = `
        <div class="upgrade-slot">${up.slot}</div>
        <div class="upgrade-info">
          <div class="upgrade-name" style="color: ${qualityColor}">${up.name}</div>
          <div class="upgrade-deficit">iLvl ${up.ilvl} <span class="deficit-badge">-${Math.round(up.deficit)}</span></div>
        </div>
      `;
      list.appendChild(item);
    });

    card.appendChild(list);
    return card;
  }

  // ===== TAB 3: STATS =====
  function renderStatsTab(data) {
    const container = document.createElement('div');
    container.id = 'dash-stats';
    container.className = 'dashboard-tab-content';

    const section = document.createElement('div');
    section.className = 'dashboard-section';

    section.appendChild(renderCurrentStats(data));
    section.appendChild(renderStatTrends(data));

    container.appendChild(section);

    return container;
  }

  function renderCurrentStats(data) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>💪 Current Stats</h3>';

    // Get latest stats from timeseries
    const healthData = data.timeseries?.health || [];
    const manaData = data.timeseries?.mana || [];
    
    // DEBUG logging
    console.log('[renderCurrentStats] Health data count:', healthData.length);
    console.log('[renderCurrentStats] Mana data count:', manaData.length);
    
    const currentHealth = healthData.length > 0 ? healthData[healthData.length - 1].value : 0;
    const currentMana = manaData.length > 0 ? manaData[manaData.length - 1].value : 0;

    // Calculate max values for percentage bars
    const maxHealth = Math.max(...healthData.map(d => d.value), currentHealth, 1);
    const maxMana = Math.max(...manaData.map(d => d.value), currentMana, 1);

    const statsGrid = document.createElement('div');
    statsGrid.className = 'stats-bars-grid';

    statsGrid.innerHTML = `
      <div class="stat-bar-item">
        <div class="stat-bar-header">
          <span class="stat-bar-label">❤️ Health</span>
          <span class="stat-bar-value">${currentHealth.toLocaleString()}</span>
        </div>
        <div class="stat-bar-container">
          <div class="stat-bar-fill health" style="width: ${(currentHealth / maxHealth * 100).toFixed(1)}%"></div>
        </div>
      </div>

      <div class="stat-bar-item">
        <div class="stat-bar-header">
          <span class="stat-bar-label">💙 Mana</span>
          <span class="stat-bar-value">${currentMana.toLocaleString()}</span>
        </div>
        <div class="stat-bar-container">
          <div class="stat-bar-fill mana" style="width: ${(currentMana / maxMana * 100).toFixed(1)}%"></div>
        </div>
      </div>

      <div class="stat-bar-item">
        <div class="stat-bar-header">
          <span class="stat-bar-label">⚔️ Item Level</span>
          <span class="stat-bar-value">${data.avgIlvl || 0}</span>
        </div>
        <div class="stat-bar-container">
          <div class="stat-bar-fill ilvl" style="width: ${((data.avgIlvl || 0) / 80 * 100).toFixed(1)}%"></div>
        </div>
      </div>

      <div class="stat-bar-item">
        <div class="stat-bar-header">
          <span class="stat-bar-label">💰 Gold</span>
          <span class="stat-bar-value">${formatGold(getCurrentGold(data))}</span>
        </div>
      </div>
    `;

    card.appendChild(statsGrid);
    return card;
  }

  function renderStatTrends(data) {
    const card = document.createElement('div');
    card.className = 'dash-card';
    card.innerHTML = '<h3>📊 Stat Trends (Last 7 Days)</h3>';

    const healthData = data.timeseries?.health || [];
    const manaData = data.timeseries?.mana || [];
    const goldData = data.timeseries?.money || [];

    const now = Math.floor(Date.now() / 1000);
    const weekAgo = now - (7 * 24 * 60 * 60);

    const recentHealth = healthData.filter(d => d.ts >= weekAgo);
    const recentMana = manaData.filter(d => d.ts >= weekAgo);
    const recentGold = goldData.filter(d => d.ts >= weekAgo);

    const trends = document.createElement('div');
    trends.className = 'trends-grid';

    const healthTrend = calculateTrend(recentHealth);
    const manaTrend = calculateTrend(recentMana);
    const goldTrend = calculateTrend(recentGold);

    trends.innerHTML = `
      <div class="trend-item">
        <div class="trend-label">❤️ Health</div>
        <div class="trend-indicator ${healthTrend > 0 ? 'up' : healthTrend < 0 ? 'down' : 'neutral'}">
          ${healthTrend > 0 ? '↗️' : healthTrend < 0 ? '↘️' : '→'} ${Math.abs(healthTrend).toFixed(1)}%
        </div>
      </div>

      <div class="trend-item">
        <div class="trend-label">💙 Mana</div>
        <div class="trend-indicator ${manaTrend > 0 ? 'up' : manaTrend < 0 ? 'down' : 'neutral'}">
          ${manaTrend > 0 ? '↗️' : manaTrend < 0 ? '↘️' : '→'} ${Math.abs(manaTrend).toFixed(1)}%
        </div>
      </div>

      <div class="trend-item">
        <div class="trend-label">💰 Gold</div>
        <div class="trend-indicator ${goldTrend > 0 ? 'up' : goldTrend < 0 ? 'down' : 'neutral'}">
          ${goldTrend > 0 ? '↗️' : goldTrend < 0 ? '↘️' : '→'} ${Math.abs(goldTrend).toFixed(1)}%
        </div>
      </div>
    `;

    card.appendChild(trends);
    return card;
  }

  function calculateTrend(dataPoints) {
    if (dataPoints.length < 2) return 0;
    const first = dataPoints[0].value;
    const last = dataPoints[dataPoints.length - 1].value;
    if (first === 0) return 0;
    return ((last - first) / first) * 100;
  }

  // ===== TAB 4: TALENT POINTS =====
  function renderTalentPointsTab(data) {
    const container = document.createElement('div');
    container.id = 'dash-talents';
    container.className = 'dashboard-tab-content';

    const section = document.createElement('div');
    section.className = 'dashboard-section';

    section.appendChild(renderTalentTrees(data));

    container.appendChild(section);

    return container;
  }

  function renderTalentTrees(data) {
    const card = document.createElement('div');
    card.className = 'dash-card talents-card';
    card.innerHTML = '<h3>🌳 Talent Distribution</h3>';

    const talents = data.talents || {};
    
    // Debug logging
    log('Talents data:', talents);
    log('Talents trees:', talents.trees);
    log('Total points:', talents.totalPoints);
    
    if (!talents.trees || Object.keys(talents.trees).length === 0) {
      card.innerHTML += '<p class="muted">No talent data available</p>';
      return card;
    }

    const treesContainer = document.createElement('div');
    treesContainer.className = 'talent-trees-container';

    Object.keys(talents.trees).forEach(treeName => {
      const tree = talents.trees[treeName];
      const treeCard = renderTalentTree(treeName, tree, talents.totalPoints || 0);
      treesContainer.appendChild(treeCard);
    });

    card.appendChild(treesContainer);
    return card;
  }

  function renderTalentTree(treeName, treeData, totalPoints) {
    const treeCard = document.createElement('div');
    treeCard.className = 'talent-tree-card';

    const pointsInTree = treeData.points || 0;
    const percentage = totalPoints > 0 ? (pointsInTree / totalPoints * 100).toFixed(0) : 0;

    treeCard.innerHTML = `
      <div class="talent-tree-header">
        <h4>${treeName}</h4>
        <div class="talent-tree-points">${pointsInTree} / ${totalPoints}</div>
      </div>
      <div class="talent-tree-bar-container">
        <div class="talent-tree-bar" style="width: ${percentage}%"></div>
      </div>
      <div class="talent-tree-percentage">${percentage}%</div>
    `;

    // Render talents in this tree
    if (treeData.talents && treeData.talents.length > 0) {
      const talentsList = document.createElement('div');
      talentsList.className = 'talents-list';

      treeData.talents.forEach(talent => {
        const talentItem = document.createElement('div');
        talentItem.className = 'talent-item';

        const iconUrl = talent.spell_id ? `/icon.php?type=spell&id=${talent.spell_id}&size=small` : null;

        talentItem.innerHTML = `
          <div class="talent-icon">
            ${iconUrl ? `<img src="${iconUrl}" alt="${talent.name}" />` : '✨'}
            <div class="talent-rank">${talent.rank}/${talent.max_rank}</div>
          </div>
          <div class="talent-info">
            <div class="talent-name">${talent.name}</div>
            ${talent.description ? `<div class="talent-desc">${talent.description}</div>` : ''}
          </div>
        `;

        talentsList.appendChild(talentItem);
      });

      treeCard.appendChild(talentsList);
    }

    return treeCard;
  }

  // ===== Main Dashboard Renderer =====
  function renderDashboard(data, charName, userName) {
    const root = q('#tab-dashboard');
    if (!root) return;
    
    root.innerHTML = '';

    // Character card (always visible)
    const topSection = document.createElement('div');
    topSection.className = 'dashboard-section';
    topSection.appendChild(renderCharacterCard(data, charName));
    root.appendChild(topSection);

    // Tab navigation
    const tabNav = document.createElement('div');
    tabNav.className = 'dashboard-tabs';
    tabNav.innerHTML = `
      <button class="dashboard-tab active" data-tab="pressed-c">📋 Pressed C</button>
      <button class="dashboard-tab" data-tab="gear">⚔️ Gear</button>
      <button class="dashboard-tab" data-tab="stats">📊 Stats</button>
      <button class="dashboard-tab" data-tab="talents">🌳 Talent Points</button>
    `;
    root.appendChild(tabNav);

    // Tab contents
    const contentWrapper = document.createElement('div');
    contentWrapper.className = 'dashboard-content-wrapper';
    
    contentWrapper.appendChild(renderPressedCTab(data));
    contentWrapper.appendChild(renderGearTab(data));
    contentWrapper.appendChild(renderStatsTab(data));
    contentWrapper.appendChild(renderTalentPointsTab(data));
    
    root.appendChild(contentWrapper);

    setupTabs(root);
  }

  // ===== Data Loading =====
  async function loadDashboard() {
    const root = q('#tab-dashboard');
    if (!root) {
      log('ERROR: #tab-dashboard not found in DOM');
      return;
    }

    const cid = root.dataset?.characterId;
    const charName = root.dataset?.charName || '';
    const userName = root.dataset?.userName || '';

    log('Loading dashboard for character:', cid, charName);

    if (!cid) {
      root.innerHTML = '<p class="muted">No character selected</p>';
      return;
    }

    root.innerHTML = '<div class="muted" style="text-align: center; padding: 40px 0;"><div style="font-size: 2rem; margin-bottom: 16px;">⏳</div><div>Loading data...</div></div>';

    try {
      const url = `/sections/dashboard-data.php?character_id=${encodeURIComponent(cid)}`;
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
      
      renderDashboard(data, charName, userName);
    } catch (err) {
      log('Failed to load dashboard:', err);
      root.innerHTML = `<p style="color:#d32f2f;">Failed to load dashboard data: ${err.message}</p>`;
    }
  }

  // ===== Event Listeners =====
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'dashboard') {
      loadDashboard();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || 'dashboard';
    if (currentSection === 'dashboard') {
      loadDashboard();
    }
  });

  // Initial load
  if (q('#tab-dashboard')) {
    log('Found #tab-dashboard on page load, loading now...');
    loadDashboard();
  } else {
    log('No #tab-dashboard found on initial load, waiting for event...');
  }

  log('Dashboard module loaded and ready');
})();