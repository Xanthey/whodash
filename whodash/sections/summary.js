
/* eslint-disable no-console */
/* WhoDASH Summary Dashboard - Enhanced with hero stats, activity feed, and insights */
(() => {
  // ===== Constants =====
  const GOLD_BRACKETS = [
    { name: 'Scrapling', min: 0, max: 10000, desc: 'Fresh off the cart, barely any dust on your boots.' },
    { name: 'Shiny Seeker', min: 10001, max: 100000, desc: 'Silver glints in your eyes, and ambition in your heart.' },
    { name: 'Vault Dabbler', min: 100001, max: 1000000, desc: "You've tasted treasure, now you want more." },
    { name: 'Quartermaster of Chaos', min: 1000001, max: 2500000, desc: 'Quarter of the way to vault-worthy riches.' },
    { name: 'Half-Hoard Hero', min: 2500001, max: 5000000, desc: 'Halfway to legendary loot status.' },
    { name: 'Loot Baron', min: 5000001, max: 7500000, desc: 'Your stash is starting to attract attention... and envy.' },
    { name: 'Epic Tycoon', min: 7500001, max: 10000000, desc: 'Your vault echoes with the sound of gold.' },
    { name: 'Fortune Slayer', min: 10000001, max: 13370000, desc: 'You slay dragons and economies alike.' },
    { name: 'Zer0C00l', min: 13370001, max: 20000000, desc: 'Zero Cool? Crashed 1507 computers in one day?' },
    { name: 'Treasure Architect', min: 20000001, max: 30000000, desc: 'You build riches, not homes.' },
    { name: 'Vault Visionary', min: 30000001, max: 50000000, desc: 'You see loot where others see dirt.' },
    { name: 'Empire Engineer', min: 50000001, max: 75000000, desc: 'Your bank is deeper than my mana pool' },
    { name: 'Capital Commander', min: 75000001, max: 100000000, desc: 'You destroy armies and economies.' },
    { name: 'Mythic Merchant', min: 100000001, max: 133700000, desc: 'Your name is whispered in both taverns and treasure halls.' }
  ];

  const QUEST_COMPLETION_TIERS = [
    { name: 'Questling', min: 1, max: 10, desc: 'Just picked up your first quest scroll—don’t forget to read it!' },
    { name: 'Scroll Seeker', min: 11, max: 20, desc: 'You chase exclamation marks like they owe you gold.' },
    { name: 'Task Tinkerer', min: 21, max: 30, desc: 'You’ve got a knack for turning errands into epic tales.' },
    { name: 'Objective Operator', min: 31, max: 40, desc: 'You run quests like a well-oiled goblin machine.' },
    { name: 'Half-Map Hero', min: 41, max: 50, desc: 'You’ve explored half the map and looted every bush.' },
    { name: 'Loot Ledger', min: 51, max: 60, desc: 'Your quest journal is thicker than a troll’s skull.' },
    { name: 'Epic Errandist', min: 61, max: 70, desc: 'You turn "fetch 10 mushrooms" into a saga of glory.' },
    { name: 'Fortune Fulfiller', min: 71, max: 80, desc: 'You complete quests before breakfast—and with flair.' },
    { name: 'Legend Scripter', min: 81, max: 90, desc: 'Your quest tales are told around campfires and taverns.' },
    { name: 'Mythic Missionary', min: 91, max: 100, desc: 'You don’t just complete quests—you inspire them.' }
  ];

  const STANDING_COLOR = {
    Neutral: '#cccccc',
    Friendly: '#1eff00',
    Honored: '#00aaff',
    Revered: '#a335ee',
    Exalted: '#ff8000',
    Hated: '#9d9d9d',
    Hostile: '#9d9d9d',
    Unfriendly: '#9d9d9d'
  };

  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[summary]', ...a);

  function formatGold(copper) {
    const g = Math.floor(copper / 10000);
    const s = Math.floor((copper % 10000) / 100);
    const c = copper % 100;
    const parts = [];
    if (g > 0) parts.push(`<span class="coin coin-gold">${g.toLocaleString()}g</span>`);
    if (s > 0) parts.push(`<span class="coin coin-silver">${s}s</span>`);
    if (c > 0) parts.push(`<span class="coin coin-copper">${c}c</span>`);
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

  function getGoldTier(copper) {
    for (let i = GOLD_BRACKETS.length - 1; i >= 0; i--) {
      if (copper >= GOLD_BRACKETS[i].min) return GOLD_BRACKETS[i];
    }
    return GOLD_BRACKETS[0];
  }

  function getQuestTier(percent) {
    for (let i = QUEST_COMPLETION_TIERS.length - 1; i >= 0; i--) {
      if (percent >= QUEST_COMPLETION_TIERS[i].min) return QUEST_COMPLETION_TIERS[i];
    }
    return QUEST_COMPLETION_TIERS[0];
  }

  function getReputationColor(standingID) {
    const standings = ['Hated', 'Hostile', 'Unfriendly', 'Neutral', 'Friendly', 'Honored', 'Revered', 'Exalted'];
    const name = standings[standingID] || 'Neutral';
    return STANDING_COLOR[name] || STANDING_COLOR.Neutral;
  }

  function extractItemName(raw = '') {
    if (!raw || raw === 'None') return '';
    const m = /\[(.+?)\]/.exec(raw);
    return m ? m[1] : raw;
  }

  // ===== Hero Stats Card =====
  function renderHeroStats(stats) {
    const card = document.createElement('div');
    card.className = 'hero-stats-grid';
    card.innerHTML = `
      <div class="hero-stat">
        <div class="hero-stat-icon">⏱️</div>
        <div class="hero-stat-value">${formatDuration(stats.totalPlayTime)}</div>
        <div class="hero-stat-label">Total Playtime</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-icon">💰</div>
        <div class="hero-stat-value">${formatGold(stats.lifetimeGold)}</div>
        <div class="hero-stat-label">Lifetime Gold</div>
        <div class="hero-stat-tier">${stats.goldTier.name}</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-icon">💀</div>
        <div class="hero-stat-value">${stats.deaths.toLocaleString()}</div>
        <div class="hero-stat-label">Deaths</div>
        <div class="hero-stat-subtitle">${stats.deathTitle}</div>
      </div>
      <div class="hero-stat">
        <div class="hero-stat-icon">⚔️</div>
        <div class="hero-stat-value">${stats.kills.toLocaleString()}</div>
        <div class="hero-stat-label">Boss Kills</div>
        <div class="hero-stat-subtitle">K/D: ${stats.kdRatio}</div>
      </div>
    `;
    return card;
  }

  // ===== This Week's Highlights =====
function renderHighlights(highlights) {
  const card = document.createElement('div');
  card.className = 'highlights-card';
  card.innerHTML = `
    <h3>📊 This Week's Highlights</h3>
    <ul class="highlights-list">
      <li>
        <span class="hl-icon">📈</span>
        <span class="hl-label">Levels gained:</span>
        <strong>${highlights.levelsGained}</strong>
      </li>
      <li>
        <span class="hl-icon">💰</span>
        <span class="hl-label">Gold earned:</span>
        <strong>${formatGold(highlights.goldEarned)}</strong>
      </li>
      <li>
        <span class="hl-icon">📦</span>
        <span class="hl-label">Items looted:</span>
        <strong>${highlights.itemsLooted}</strong>
      </li>
      <li>
        <span class="hl-icon">💀</span>
        <span class="hl-label">Deaths:</span>
        <strong>${highlights.deaths}</strong>
        ${highlights.deaths <= 2 ? ' 🎉' : ''}
      </li>
      <li>
        <span class="hl-icon">🗺️</span>
        <span class="hl-label">Zones explored:</span>
        <strong>${highlights.zonesExplored}</strong>
      </li>
    </ul>
  `;
  return card;
}

  // ===== Activity Heatmap =====
  function renderActivityHeatmap(sessions) {
    const card = document.createElement('div');
    card.className = 'activity-card';
    const title = document.createElement('h3');
    title.textContent = '📅 Activity (Last 90 Days)';
    card.appendChild(title);

    const heatmap = document.createElement('div');
    heatmap.className = 'activity-heatmap';

    // Create last 90 days
    const today = new Date();
    const dayMap = new Map();
    for (let i = 89; i >= 0; i--) {
      const date = new Date(today);
      date.setDate(date.getDate() - i);
      const dateStr = date.toISOString().split('T')[0];
      dayMap.set(dateStr, { count: 0, duration: 0 });
    }

    // Fill in session data
    sessions.forEach(session => {
      const date = new Date(session.date);
      const dateStr = date.toISOString().split('T')[0];
      if (dayMap.has(dateStr)) {
        const day = dayMap.get(dateStr);
        day.count += session.count;
        day.duration += session.duration;
      }
    });

    // Find max for scaling
    let maxDuration = 0;
    dayMap.forEach(day => {
      if (day.duration > maxDuration) maxDuration = day.duration;
    });

    // Render cells
    dayMap.forEach((day, dateStr) => {
      const cell = document.createElement('div');
      cell.className = 'heatmap-cell';
      let intensity = 0;
      if (maxDuration > 0 && day.duration > 0) {
        intensity = Math.min(4, Math.ceil((day.duration / maxDuration) * 4));
      }
      cell.setAttribute('data-intensity', intensity);
      cell.setAttribute('title', `${dateStr}: ${formatDuration(day.duration)} (${day.count} sessions)`);
      heatmap.appendChild(cell);
    });

    card.appendChild(heatmap);
    return card;
  }

  // ===== Top Loot Widget =====
  function renderTopLoot(itemEvents) {
    const card = document.createElement('div');
    card.className = 'top-loot-card';
    const title = document.createElement('h3');
    title.textContent = '✨ Top Loot (Last 7 Days)';
    card.appendChild(title);

    // Filter last 7 days and obtained items
    const weekAgo = Math.floor(Date.now() / 1000) - (7 * 24 * 60 * 60);
    const recentLoot = itemEvents.filter(e => e.ts >= weekAgo && e.action === 'obtained');

    if (recentLoot.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No loot this week';
      card.appendChild(msg);
      return card;
    }

    // Count items
    const itemCounts = {};
    recentLoot.forEach(item => {
      const name = extractItemName(item.itemName || item.link || '');
      if (!name) return;
      if (!itemCounts[name]) {
        itemCounts[name] = {
          count: 0,
          link: item.link,
          lastSeen: item.ts
        };
      }
      itemCounts[name].count += item.count || 1;
      if (item.ts > itemCounts[name].lastSeen) {
        itemCounts[name].lastSeen = item.ts;
      }
    });

    // Sort by count
    const sorted = Object.entries(itemCounts)
      .sort((a, b) => b[1].count - a[1].count)
      .slice(0, 5);

    const list = document.createElement('div');
    list.className = 'top-loot-list';

    sorted.forEach(([name, data], index) => {
      const item = document.createElement('div');
      item.className = 'top-loot-item';

      const rank = document.createElement('span');
      rank.className = 'loot-rank';
      rank.textContent = `#${index + 1}`;

      const nameEl = document.createElement('span');
      nameEl.className = 'loot-name';
      nameEl.textContent = name;

      const countEl = document.createElement('span');
      countEl.className = 'loot-count';
      countEl.textContent = `×${data.count}`;

      item.appendChild(rank);
      item.appendChild(nameEl);
      item.appendChild(countEl);
      list.appendChild(item);
    });

    card.appendChild(list);
    return card;
  }

  // ===== Gold Timeline Sparkline =====
  function renderGoldSparkline(moneyData) {
    const card = document.createElement('div');
    card.className = 'sparkline-card';
    card.style.position = 'relative';

    const title = document.createElement('h3');
    title.textContent = '💰 Gold Timeline (Last 30 Days)';
    card.appendChild(title);

    const canvas = document.createElement('canvas');
    canvas.className = 'sparkline-canvas';
    canvas.width = 800;
    canvas.height = 120;
    card.appendChild(canvas);

    // Tooltip element
    const tooltip = document.createElement('div');
    tooltip.className = 'sparkline-tooltip';
    tooltip.style.cssText = 'position:absolute;display:none;background:rgba(0,0,0,0.9);color:#fff;padding:8px 12px;border-radius:6px;font-size:0.85rem;pointer-events:none;z-index:100;white-space:nowrap;';
    card.appendChild(tooltip);

    // Filter last 30 days
    const thirtyDaysAgo = Math.floor(Date.now() / 1000) - (30 * 24 * 60 * 60);
    const recent = moneyData.filter(d => d.ts >= thirtyDaysAgo);

    if (recent.length > 1) {
      const ctx = canvas.getContext('2d');
      const padding = 10;
      const w = canvas.width - padding * 2;
      const h = canvas.height - padding * 2;

      const minVal = Math.min(...recent.map(d => d.value));
      const maxVal = Math.max(...recent.map(d => d.value));
      const range = (maxVal - minVal) || 1;

      // Store points for tooltip detection
      const points = [];

      ctx.strokeStyle = '#3182ce';
      ctx.lineWidth = 2;
      ctx.beginPath();

      recent.forEach((d, i) => {
        const x = padding + (i / (recent.length - 1)) * w;
        const y = padding + h - ((d.value - minVal) / range) * h;
        points.push({ x, y, value: d.value, ts: d.ts });
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });

      ctx.stroke();

      // Fill gradient
      ctx.lineTo(padding + w, padding + h);
      ctx.lineTo(padding, padding + h);
      ctx.closePath();

      const gradient = ctx.createLinearGradient(0, padding, 0, padding + h);
      gradient.addColorStop(0, 'rgba(49, 130, 206, 0.3)');
      gradient.addColorStop(1, 'rgba(49, 130, 206, 0.0)');
      ctx.fillStyle = gradient;
      ctx.fill();

      // Mouse interaction
      canvas.addEventListener('mousemove', (e) => {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const mouseX = (e.clientX - rect.left) * scaleX;
        const mouseY = (e.clientY - rect.top) * scaleY;

        // Find nearest point
        let nearest = null;
        let minDist = Infinity;

        points.forEach(p => {
          const dist = Math.sqrt((p.x - mouseX) ** 2 + (p.y - mouseY) ** 2);
          if (dist < minDist && dist < 20) {
            minDist = dist;
            nearest = p;
          }
        });

        if (nearest) {
          tooltip.style.display = 'block';
          tooltip.style.left = (e.clientX - rect.left + 10) + 'px';
          tooltip.style.top = (e.clientY - rect.top - 30) + 'px';
          tooltip.innerHTML = formatGold(nearest.value) +
            '<br><span style="font-size:0.75rem;opacity:0.8;">' +
            new Date(nearest.ts * 1000).toLocaleDateString() +
            '</span>';
        } else {
          tooltip.style.display = 'none';
        }
      });

      canvas.addEventListener('mouseleave', () => {
        tooltip.style.display = 'none';
      });

      // Current value
      const current = recent[recent.length - 1].value;
      const change = recent.length > 1 ? current - recent[0].value : 0;
      const pct = recent.length > 1 && recent[0].value > 0
        ? ((change / recent[0].value) * 100).toFixed(1)
        : '0.0';

      const info = document.createElement('div');
      info.className = 'sparkline-info';
      info.innerHTML = `
        Current: ${formatGold(current)}
        <span style="color: ${change >= 0 ? '#2e7d32' : '#d32f2f'}">
          ${change >= 0 ? '+' : ''}${formatGold(change)} (${pct}%)
        </span>
      `;
      card.appendChild(info);
    } else {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'Not enough data for sparkline';
      card.appendChild(msg);
    }

    return card;
  }

  // ===== Recent Activity Feed =====
  function renderActivityFeed(events) {
    const card = document.createElement('div');
    card.className = 'activity-feed-card';

    const title = document.createElement('h3');
    title.textContent = '📜 Recent Activity';
    card.appendChild(title);

    const feed = document.createElement('div');
    feed.className = 'activity-feed';

    events.slice(0, 10).forEach(event => {
      const item = document.createElement('div');
      item.className = 'activity-item';

      const icon = document.createElement('span');
      icon.className = 'activity-icon';
      icon.textContent = event.icon;

      const content = document.createElement('div');
      content.className = 'activity-content';

      const desc = document.createElement('div');
      desc.className = 'activity-desc';
      desc.innerHTML = event.description;

      const time = document.createElement('div');
      time.className = 'activity-time';
      time.textContent = formatRelativeTime(event.ts);

      content.appendChild(desc);
      content.appendChild(time);
      item.appendChild(icon);
      item.appendChild(content);
      feed.appendChild(item);
    });

    if (events.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No recent activity';
      feed.appendChild(msg);
    }

    card.appendChild(feed);
    return card;
  }

  function formatRelativeTime(ts) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - ts;
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(ts * 1000).toLocaleDateString();
  }

  // ===== Reputation List (Animated) =====
  function renderReputationList(repData) {
    const card = document.createElement('div');
    card.className = 'reputation-card';

    const title = document.createElement('h3');
    title.textContent = '🎖️ Reputation';
    card.appendChild(title);

    // Color legend
    const legend = document.createElement('div');
    legend.className = 'rep-legend';
    legend.innerHTML = `
      <span><span class="chip" style="background:${STANDING_COLOR.Friendly}"></span> Friendly</span>
      <span><span class="chip" style="background:${STANDING_COLOR.Honored}"></span> Honored</span>
      <span><span class="chip" style="background:${STANDING_COLOR.Revered}"></span> Revered</span>
      <span><span class="chip" style="background:${STANDING_COLOR.Exalted}"></span> Exalted</span>
    `;
    card.appendChild(legend);

    const list = document.createElement('div');
    list.className = 'rep-list';

    // Get latest rep for each faction
    const latest = new Map();
    repData.forEach(row => {
      const name = row.name;
      if (!name) return;
      const ts = row.timestamp ?? 0;
      const prev = latest.get(name);
      if (!prev || ts >= prev.ts) {
        latest.set(name, {
          value: row.value ?? 0,
          standingID: row.standingID ?? 4,
          ts
        });
      }
    });

    const sortedNames = Array.from(latest.keys()).sort((a, b) => a.localeCompare(b));
    const fills = [];

    sortedNames.forEach(name => {
      const { value, standingID } = latest.get(name);
      const color = getReputationColor(standingID);
      // Calculate percentage (simplified - assumes 0-42000 range)
      const pct = Math.min(100, (value / 42000) * 100);

      const row = document.createElement('div');
      row.className = 'rep-row';

      const label = document.createElement('span');
      label.className = 'rep-name';
      label.textContent = name;

      const bar = document.createElement('div');
      bar.className = 'rep-bar';

      const fill = document.createElement('div');
      fill.className = 'rep-fill';
      fill.setAttribute('data-value', pct.toFixed(1));
      fill.style.width = '0%';
      // 8-digit hex for alpha is supported widely; `${color}cc` == ~80% alpha
      fill.style.background = `linear-gradient(90deg, ${color} 0%, ${color}cc 100%)`;
      fill.style.transition = 'width 0.5s ease-out';

      const valueSpan = document.createElement('span');
      valueSpan.className = 'rep-value';
      valueSpan.textContent = value.toLocaleString();

      bar.appendChild(fill);
      row.appendChild(label);
      row.appendChild(bar);
      row.appendChild(valueSpan);
      list.appendChild(row);
      fills.push(fill);
    });

    if (sortedNames.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No reputation data';
      list.appendChild(msg);
    }

    card.appendChild(list);

    // Animate on scroll
    if (fills.length > 0) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            const fill = entry.target;
            const value = fill.getAttribute('data-value');
            const index = fills.indexOf(fill);
            setTimeout(() => {
              fill.style.width = value + '%';
            }, index * 30);
            observer.unobserve(fill);
          }
        });
      }, { threshold: 0.1 });

      fills.forEach(f => observer.observe(f));
    }

    return card;
  }

  // ===== Main Dashboard Renderer =====
  function renderDashboard(data) {
    const root = q('#tab-summary');
    if (!root) return;
    root.innerHTML = '';

    // Calculate stats
    const stats = calculateStats(data);
    const highlights = calculateHighlights(data);
    const activityEvents = buildActivityFeed(data);

    // Hero stats (top row)
    const heroSection = document.createElement('div');
    heroSection.className = 'dashboard-section';
    heroSection.appendChild(renderHeroStats(stats));
    root.appendChild(heroSection);

    // Highlights + Heatmap row
    const midSection = document.createElement('div');
    midSection.className = 'dashboard-section dashboard-row';
    midSection.appendChild(renderHighlights(highlights));
    midSection.appendChild(renderActivityHeatmap(data.sessions ?? []));
    root.appendChild(midSection);

    // Charts row
    const chartsSection = document.createElement('div');
    chartsSection.className = 'dashboard-section dashboard-row';
    chartsSection.appendChild(renderGoldSparkline(data.timeseries?.money ?? []));
    chartsSection.appendChild(renderTopLoot(data.items?.history ?? []));
    root.appendChild(chartsSection);

    // Activity feed + Reputation
    const bottomSection = document.createElement('div');
    bottomSection.className = 'dashboard-section dashboard-row';
    bottomSection.appendChild(renderActivityFeed(activityEvents));
    bottomSection.appendChild(renderReputationList(data.reputation?.history ?? []));
    root.appendChild(bottomSection);
  }

function calculateStats(data) {
  const sessions = data.sessions ?? [];
  const totalPlayTime = sessions.reduce((sum, s) => sum + (s.duration ?? 0), 0);

  // FIX: Calculate lifetime gold as the MAXIMUM gold ever reached, not current gold
  const moneyData = data.timeseries?.money ?? [];
  let lifetimeGold = 0;
  if (moneyData.length > 0) {
    // Find the maximum gold value ever reached
    lifetimeGold = Math.max(...moneyData.map(d => d.value));
  }
  const goldTier = getGoldTier(lifetimeGold);

  const deaths = data.stats?.deaths ?? 0;
  const kills = data.stats?.kills ?? 0;
  const kdRatio = kills > 0 ? (kills / Math.max(1, deaths)).toFixed(2) : '0.00';

  let deathTitle = 'Immortal Legend';
  if (deaths > 1000) deathTitle = 'Resurrection Champion';
  else if (deaths > 500) deathTitle = 'Death Magnet';
  else if (deaths > 100) deathTitle = 'Frequent Flyer';
  else if (deaths > 10) deathTitle = 'Learning the Ropes';

  return {
    totalPlayTime,
    lifetimeGold,
    goldTier,
    deaths,
    kills,
    kdRatio,
    deathTitle
  };
}

  function calculateHighlights(data) {
    const now = Math.floor(Date.now() / 1000);
    const weekAgo = now - (7 * 24 * 60 * 60);

    // Levels gained
    const levelData = data.timeseries?.level ?? [];
    const recentLevels = levelData.filter(d => d.ts >= weekAgo);
    const levelsGained = recentLevels.length > 1
      ? recentLevels[recentLevels.length - 1].value - recentLevels[0].value
      : 0;

    // Gold earned
    const moneyData = data.timeseries?.money ?? [];
    const recentMoney = moneyData.filter(d => d.ts >= weekAgo);
    const goldEarned = recentMoney.length > 1
      ? recentMoney[recentMoney.length - 1].value - recentMoney[0].value
      : 0;

    // Items looted
    const itemEvents = data.items?.history ?? [];
    const recentLoots = itemEvents.filter(e => e.ts >= weekAgo && e.action === 'obtained');
    const itemsLooted = recentLoots.reduce((sum, e) => sum + (e.count ?? 1), 0);

    // Deaths
    const deathEvents = (data.events?.death ?? []).filter(e => e.ts >= weekAgo);
    const deaths = deathEvents.length;

    // Zones
    const zoneData = data.zones?.history ?? [];
    const recentZones = zoneData.filter(z => z.ts >= weekAgo);
    const uniqueZones = new Set(recentZones.map(z => z.zone));
    const zonesExplored = uniqueZones.size;

    return {
      levelsGained,
      goldEarned,
      itemsLooted,
      deaths,
      zonesExplored
    };
  }

  function buildActivityFeed(data) {
    const events = [];

    // Level ups
    const levelData = data.timeseries?.level ?? [];
    levelData.forEach(d => {
      events.push({
        ts: d.ts,
        icon: '⬆️',
        description: `<strong>Leveled up</strong> to ${d.value}`
      });
    });

    // Item events
    const itemEvents = data.items?.history ?? [];
    itemEvents.forEach(e => {
      let icon = '📦';
      let action = e.action;
      if (action === 'obtained') icon = '✨';
      else if (action === 'sold') icon = '💰';

      const itemName = extractItemName(e.itemName || e.link || '');
      const count = e.count > 1 ? ` ×${e.count}` : '';

      events.push({
        ts: e.ts,
        icon,
        description: `<strong>${action}</strong> ${itemName}${count}`
      });
    });

    // Deaths
    const deathEvents = data.events?.death ?? [];
    deathEvents.forEach(e => {
      events.push({
        ts: e.ts,
        icon: '💀',
        description: `<strong>Died</strong> in ${e.zone || 'Unknown'}`
      });
    });

    // Sort by timestamp descending
    events.sort((a, b) => b.ts - a.ts);
    return events;
  }

  // ===== Data Loading =====
  async function loadDashboard() {
    const root = q('#tab-summary');
    if (!root) return;

    const cid = root.dataset?.characterId;
    if (!cid) {
      root.innerHTML = '<p class="muted">No character selected</p>';
      return;
    }

    try {
      const res = await fetch(`/sections/summary-data.php?character_id=${encodeURIComponent(cid)}`, {
        credentials: 'include'
      });
      if (!res.ok) {
        throw new Error(`HTTP ${res.status}`);
      }
      const data = await res.json();
      renderDashboard(data);
    } catch (err) {
      log('Failed to load dashboard:', err);
      root.innerHTML = '<p style="color:#d32f2f;">Failed to load dashboard data</p>';
    }
  }

  // ===== Event Listeners =====
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'summary') {
      loadDashboard();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || 'dashboard';
    if (currentSection === 'summary') {
      loadDashboard();
    }
  });

  // Initial load
  if (q('#tab-summary')) {
    loadDashboard();
  }

  log('Enhanced dashboard loaded');
})();
