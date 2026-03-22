/* eslint-disable no-console */
/* WhoDASH Social — Group Composition & Friend Network Tracker */
(() => {
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[social]', ...a);

  // Class color mapping
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

  function getClassColor(className) {
    return CLASS_COLORS[className?.toUpperCase()] || '#999';
  }

  function formatDate(ts) {
    return new Date(ts * 1000).toLocaleDateString('en-US', {
      month: 'short', day: 'numeric', year: 'numeric'
    });
  }

  function formatDateTime(ts) {
    return new Date(ts * 1000).toLocaleString('en-US', {
      month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit'
    });
  }

  function timeAgo(ts) {
    const now = Math.floor(Date.now() / 1000);
    const diff = now - ts;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    const days = Math.floor(diff / 86400);
    if (days < 30) return `${days}d ago`;
    return formatDate(ts);
  }

  function formatNumber(num) {
    if (!num) return '0';
    if (num >= 1000000) return `${(num / 1000000).toFixed(1)}M`;
    if (num >= 1000) return `${(num / 1000).toFixed(1)}K`;
    return num.toLocaleString();
  }

  // ===== Tab System =====
  function setupTabs(container) {
    const tabs = qa('.social-tab', container);
    const contents = qa('.social-tab-content', container);

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;

        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');

        contents.forEach(c => c.classList.remove('active'));
        const targetContent = q(`#social-${target}`, container);
        if (targetContent) targetContent.classList.add('active');
      });
    });
  }

  // ===== THE TRUE ADVENTURE Tab =====
  function renderTrueAdventure(data) {
    const container = document.createElement('div');
    container.id = 'social-adventure';
    container.className = 'social-tab-content active';

    const overview = data.overview || {};
    const groups = data.groups || {};
    const friends = data.friends || {};

    // Hero Stats
    const heroStats = document.createElement('div');
    heroStats.className = 'social-hero-stats';
    heroStats.innerHTML = `
      <div class="hero-stat-card hero-primary">
        <div class="hero-icon">🌟</div>
        <div class="hero-value">${overview.unique_players || 0}</div>
        <div class="hero-label">Unique Adventurers</div>
        <div class="hero-sublabel">You've shared the journey with</div>
      </div>
      
      <div class="hero-stat-card hero-secondary">
        <div class="hero-icon">⚔️</div>
        <div class="hero-value">${overview.total_groups || 0}</div>
        <div class="hero-label">Groups Formed</div>
        <div class="hero-sublabel">Parties and raids assembled</div>
      </div>
      
      <div class="hero-stat-card hero-tertiary">
        <div class="hero-icon">🏰</div>
        <div class="hero-value">${overview.instance_stats?.unique_instances || 0}</div>
        <div class="hero-label">Dungeons & Raids</div>
        <div class="hero-sublabel">Different instances conquered</div>
      </div>
    `;
    container.appendChild(heroStats);

    // Fun Facts Section
    const funFacts = document.createElement('div');
    funFacts.className = 'social-fun-facts';
    funFacts.innerHTML = '<h3>🎯 Your Social Adventure</h3>';
    
    const factsGrid = document.createElement('div');
    factsGrid.className = 'facts-grid';

    // Calculate fun facts
    const partyData = overview.group_types?.find(g => g.type === 'party');
    const raidData = overview.group_types?.find(g => g.type === 'raid');
    const friendsAdded = overview.friend_stats?.friends_added || 0;
    const friendsRemoved = overview.friend_stats?.friends_removed || 0;
    const netFriends = friendsAdded - friendsRemoved;

    const facts = [
      {
        icon: '🎉',
        title: 'Party Animal',
        value: partyData?.count || 0,
        subtitle: `${partyData ? Math.round(partyData.avg_size) : 0}-player parties on average`
      },
      {
        icon: '🎪',
        title: 'Raid Leader',
        value: raidData?.count || 0,
        subtitle: `${raidData ? Math.round(raidData.avg_size) : 0}-player raids on average`
      },
      {
        icon: '💫',
        title: 'Social Butterfly',
        value: `${friendsAdded > 0 ? '+' : ''}${netFriends}`,
        subtitle: `${friendsAdded} added, ${friendsRemoved} removed`
      },
      {
        icon: '🏆',
        title: 'Instance Master',
        value: overview.instance_stats?.total_runs || 0,
        subtitle: 'Total instance runs'
      }
    ];

    facts.forEach(fact => {
      factsGrid.innerHTML += `
        <div class="fact-card">
          <div class="fact-icon">${fact.icon}</div>
          <div class="fact-value">${fact.value}</div>
          <div class="fact-title">${fact.title}</div>
          <div class="fact-subtitle">${fact.subtitle}</div>
        </div>
      `;
    });

    funFacts.appendChild(factsGrid);
    container.appendChild(funFacts);

    // Class Distribution Pie Chart
    if (groups.class_distribution && groups.class_distribution.length > 0) {
      const chartSection = document.createElement('div');
      chartSection.className = 'social-chart-section';
      chartSection.innerHTML = '<h3>🎨 Who You Adventure With</h3>';
      
      const chartContainer = document.createElement('div');
      chartContainer.className = 'class-pie-chart';
      chartContainer.style.cssText = 'width: 100%; max-width: 500px; margin: 0 auto;';
      chartSection.appendChild(chartContainer);

      // Create pie chart
      const total = groups.class_distribution.reduce((sum, c) => sum + parseInt(c.count), 0);
      const chartData = groups.class_distribution.map(c => ({
        label: c.class || 'Unknown',
        value: parseInt(c.count),
        percentage: ((parseInt(c.count) / total) * 100).toFixed(1),
        color: getClassColor(c.class)
      }));

      renderPieChart(chartContainer, chartData);
      container.appendChild(chartSection);
    }

    // Group Size Distribution Bar Chart
    if (groups.size_distribution && groups.size_distribution.length > 0) {
      const sizeSection = document.createElement('div');
      sizeSection.className = 'social-chart-section';
      sizeSection.innerHTML = '<h3>📊 Preferred Group Sizes</h3>';
      
      const sizeChart = document.createElement('div');
      sizeChart.className = 'size-bar-chart';
      
      const maxCount = Math.max(...groups.size_distribution.map(s => parseInt(s.count)));
      
      groups.size_distribution.forEach(sizeData => {
        const size = parseInt(sizeData.size);
        const count = parseInt(sizeData.count);
        const percentage = (count / maxCount) * 100;
        
        sizeChart.innerHTML += `
          <div class="size-bar-row">
            <div class="size-label">${size} ${size === 1 ? 'player' : 'players'}</div>
            <div class="size-bar-container">
              <div class="size-bar" style="width: ${percentage}%">
                <span class="size-count">${count} groups</span>
              </div>
            </div>
          </div>
        `;
      });
      
      sizeSection.appendChild(sizeChart);
      container.appendChild(sizeSection);
    }

    return container;
  }

  // Simple Pie Chart Renderer
  function renderPieChart(container, data) {
    const total = data.reduce((sum, d) => sum + d.value, 0);
    let currentAngle = 0;

    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 400 400');
    svg.style.width = '100%';
    svg.style.height = 'auto';

    const centerX = 200;
    const centerY = 200;
    const radius = 150;

    data.forEach((slice, index) => {
      const angle = (slice.value / total) * 360;
      const endAngle = currentAngle + angle;

      // Create pie slice path
      const startAngleRad = (currentAngle - 90) * Math.PI / 180;
      const endAngleRad = (endAngle - 90) * Math.PI / 180;

      const x1 = centerX + radius * Math.cos(startAngleRad);
      const y1 = centerY + radius * Math.sin(startAngleRad);
      const x2 = centerX + radius * Math.cos(endAngleRad);
      const y2 = centerY + radius * Math.sin(endAngleRad);

      const largeArc = angle > 180 ? 1 : 0;

      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', `M ${centerX} ${centerY} L ${x1} ${y1} A ${radius} ${radius} 0 ${largeArc} 1 ${x2} ${y2} Z`);
      path.setAttribute('fill', slice.color);
      path.setAttribute('stroke', '#fff');
      path.setAttribute('stroke-width', '2');
      
      const title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
      title.textContent = `${slice.label}: ${slice.value} (${slice.percentage}%)`;
      path.appendChild(title);

      svg.appendChild(path);

      currentAngle = endAngle;
    });

    // Add legend
    const legend = document.createElement('div');
    legend.className = 'chart-legend';
    data.forEach(slice => {
      legend.innerHTML += `
        <div class="legend-item">
          <span class="legend-color" style="background-color: ${slice.color}"></span>
          <span class="legend-label">${slice.label}</span>
          <span class="legend-value">${slice.percentage}%</span>
        </div>
      `;
    });

    container.appendChild(svg);
    container.appendChild(legend);
  }

  // ===== PARTY ANIMALS Tab =====
  function renderPartyAnimals(data) {
    const container = document.createElement('div');
    container.id = 'social-party';
    container.className = 'social-tab-content';

    const groups = data.groups || {};

    // Top Companions
    if (groups.most_grouped_with && groups.most_grouped_with.length > 0) {
      const topSection = document.createElement('div');
      topSection.className = 'companions-section';
      topSection.innerHTML = '<h3>🎖️ Your Trusted Companions</h3>';

      const companionsList = document.createElement('div');
      companionsList.className = 'companions-list';

      groups.most_grouped_with.slice(0, 20).forEach((player, index) => {
        const classColor = getClassColor(player.class);
        const rank = index + 1;
        const medal = rank === 1 ? '🥇' : rank === 2 ? '🥈' : rank === 3 ? '🥉' : '';

        companionsList.innerHTML += `
          <div class="companion-card">
            <div class="companion-rank">${medal || rank}</div>
            <div class="companion-info">
              <div class="companion-name" style="color: ${classColor}">${player.player_name}</div>
              <div class="companion-class">${player.class || 'Unknown'}</div>
            </div>
            <div class="companion-stats">
              <div class="companion-count">${player.times_grouped}</div>
              <div class="companion-label">adventures</div>
              <div class="companion-last">Last: ${timeAgo(player.last_grouped)}</div>
            </div>
          </div>
        `;
      });

      topSection.appendChild(companionsList);
      container.appendChild(topSection);
    }

    // Popular Instances
    if (groups.popular_instances && groups.popular_instances.length > 0) {
      const instanceSection = document.createElement('div');
      instanceSection.className = 'instances-section';
      instanceSection.innerHTML = '<h3>🏰 Favorite Dungeons & Raids</h3>';

      const instanceGrid = document.createElement('div');
      instanceGrid.className = 'instance-grid';

      groups.popular_instances.forEach(inst => {
        const difficultyBadge = inst.instance_difficulty ? 
          `<span class="difficulty-badge">${inst.instance_difficulty}</span>` : '';
        
        instanceGrid.innerHTML += `
          <div class="instance-card">
            <div class="instance-icon">🛡️</div>
            <div class="instance-name">${inst.instance}${difficultyBadge}</div>
            <div class="instance-stats">
              <div class="instance-runs">${inst.runs} runs</div>
              <div class="instance-detail">Avg ${Math.round(parseFloat(inst.avg_group_size))} players</div>
              <div class="instance-last">Last: ${formatDate(inst.last_run)}</div>
            </div>
          </div>
        `;
      });

      instanceSection.appendChild(instanceGrid);
      container.appendChild(instanceSection);
    }

    return container;
  }

  // ===== FRIEND NETWORK Tab =====
  function renderFriendNetwork(data) {
    const container = document.createElement('div');
    container.id = 'social-friends';
    container.className = 'social-tab-content';

    const friends = data.friends || {};

    // If no friend data exists at all, show an explanatory message
    if (!friends.has_data) {
      const msg = document.createElement('div');
      msg.className = 'social-empty-state';
      msg.innerHTML = `
        <div class="empty-icon">👥</div>
        <h3>No Friend Data Yet</h3>
        <p class="muted">Friend tracking requires the WhoDAT addon to record friend list changes while you play.</p>
        <p class="muted" style="margin-top:8px;">Once you've played with the addon active and uploaded data, your friend network will appear here.</p>
      `;
      container.appendChild(msg);
      return container;
    }

    // Friend Growth Chart
    if (friends.changes_over_time && friends.changes_over_time.length > 0) {
      const chartSection = document.createElement('div');
      chartSection.className = 'social-chart-section';
      chartSection.innerHTML = '<h3>📈 Friend Network Growth</h3>';

      const chartContainer = document.createElement('div');
      chartContainer.className = 'friend-timeline-chart';
      chartContainer.style.cssText = 'width: 100%; height: 300px; position: relative; margin: 20px 0;';

      // Calculate cumulative friends
      let cumulative = 0;
      const chartData = friends.changes_over_time.map(day => {
        cumulative += parseInt(day.added) - parseInt(day.removed);
        return {
          date: day.date,
          added: parseInt(day.added),
          removed: parseInt(day.removed),
          cumulative: cumulative
        };
      });

      renderLineChart(chartContainer, chartData);
      chartSection.appendChild(chartContainer);
      container.appendChild(chartSection);
    }

    // Recent Additions
    if (friends.recent_additions && friends.recent_additions.length > 0) {
      const addSection = document.createElement('div');
      addSection.className = 'friends-section';
      addSection.innerHTML = '<h3>➕ Recent Friend Additions</h3>';

      const friendsList = document.createElement('div');
      friendsList.className = 'friends-list';

      friends.recent_additions.forEach(friend => {
        const classColor = getClassColor(friend.friend_class);
        
        friendsList.innerHTML += `
          <div class="friend-card">
            <div class="friend-info">
              <div class="friend-name" style="color: ${classColor}">${friend.friend_name}</div>
              <div class="friend-details">
                ${friend.friend_class || 'Unknown'} • 
                Level ${friend.friend_level || '?'}
              </div>
            </div>
            <div class="friend-time">${formatDate(friend.ts)}</div>
          </div>
        `;
      });

      addSection.appendChild(friendsList);
      container.appendChild(addSection);
    }

    return container;
  }

  // Simple Line Chart Renderer
  function renderLineChart(container, data) {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 800 300');
    svg.style.width = '100%';
    svg.style.height = '100%';

    const padding = 40;
    const width = 800 - (padding * 2);
    const height = 300 - (padding * 2);

    const maxCumulative = Math.max(...data.map(d => d.cumulative));
    const minCumulative = Math.min(...data.map(d => d.cumulative), 0);
    const range = maxCumulative - minCumulative;

    // Draw axes
    const axes = document.createElementNS('http://www.w3.org/2000/svg', 'g');
    axes.innerHTML = `
      <line x1="${padding}" y1="${padding}" x2="${padding}" y2="${300 - padding}" 
            stroke="#ccc" stroke-width="2"/>
      <line x1="${padding}" y1="${300 - padding}" x2="${800 - padding}" y2="${300 - padding}" 
            stroke="#ccc" stroke-width="2"/>
    `;
    svg.appendChild(axes);

    // Draw line
    const points = data.map((d, i) => {
      const x = padding + (i / (data.length - 1)) * width;
      const y = 300 - padding - ((d.cumulative - minCumulative) / range) * height;
      return `${x},${y}`;
    }).join(' ');

    const polyline = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
    polyline.setAttribute('points', points);
    polyline.setAttribute('fill', 'none');
    polyline.setAttribute('stroke', '#3182ce');
    polyline.setAttribute('stroke-width', '3');
    svg.appendChild(polyline);

    // Draw points
    data.forEach((d, i) => {
      const x = padding + (i / (data.length - 1)) * width;
      const y = 300 - padding - ((d.cumulative - minCumulative) / range) * height;

      const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      circle.setAttribute('cx', x);
      circle.setAttribute('cy', y);
      circle.setAttribute('r', '4');
      circle.setAttribute('fill', '#2456a5');
      
      const title = document.createElementNS('http://www.w3.org/2000/svg', 'title');
      title.textContent = `${d.date}: ${d.cumulative} friends (+${d.added}, -${d.removed})`;
      circle.appendChild(title);

      svg.appendChild(circle);
    });

    container.appendChild(svg);
  }

  // ===== GROUP TIMELINE Tab =====
  function renderGroupTimeline(data) {
    const container = document.createElement('div');
    container.id = 'social-timeline';
    container.className = 'social-tab-content';

    const timeline = data.timeline || {};

    // Activity Heatmap (by hour)
    if (timeline.activity_by_hour && timeline.activity_by_hour.length > 0) {
      const heatmapSection = document.createElement('div');
      heatmapSection.className = 'heatmap-section';
      heatmapSection.innerHTML = '<h3>⏰ When You Group Up</h3>';

      const heatmap = document.createElement('div');
      heatmap.className = 'hour-heatmap';

      const maxCount = Math.max(...timeline.activity_by_hour.map(h => parseInt(h.count)));

      // Fill in all 24 hours
      for (let hour = 0; hour < 24; hour++) {
        const hourData = timeline.activity_by_hour.find(h => parseInt(h.hour) === hour);
        const count = hourData ? parseInt(hourData.count) : 0;
        const intensity = count > 0 ? (count / maxCount) : 0;

        const hourLabel = hour === 0 ? '12am' : hour < 12 ? `${hour}am` : hour === 12 ? '12pm' : `${hour - 12}pm`;

        heatmap.innerHTML += `
          <div class="hour-cell" style="opacity: ${0.3 + (intensity * 0.7)}; background: #3182ce;" 
               title="${hourLabel}: ${count} groups">
            <div class="hour-label">${hourLabel}</div>
            <div class="hour-count">${count}</div>
          </div>
        `;
      }

      heatmapSection.appendChild(heatmap);
      container.appendChild(heatmapSection);
    }

    // Day of Week Activity
    if (timeline.activity_by_day && timeline.activity_by_day.length > 0) {
      const daySection = document.createElement('div');
      daySection.className = 'day-section';
      daySection.innerHTML = '<h3>📅 Favorite Days to Group</h3>';

      const dayChart = document.createElement('div');
      dayChart.className = 'day-chart';

      const days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
      const maxCount = Math.max(...timeline.activity_by_day.map(d => parseInt(d.count)));

      days.forEach((dayName, index) => {
        const dayData = timeline.activity_by_day.find(d => parseInt(d.day_of_week) === index + 1);
        const count = dayData ? parseInt(dayData.count) : 0;
        const percentage = maxCount > 0 ? (count / maxCount) * 100 : 0;

        dayChart.innerHTML += `
          <div class="day-bar-row">
            <div class="day-label">${dayName}</div>
            <div class="day-bar-container">
              <div class="day-bar" style="width: ${percentage}%">
                <span class="day-count">${count}</span>
              </div>
            </div>
          </div>
        `;
      });

      daySection.appendChild(dayChart);
      container.appendChild(daySection);
    }

    // Recent Groups Feed
    if (timeline.recent_groups && timeline.recent_groups.length > 0) {
      const feedSection = document.createElement('div');
      feedSection.className = 'timeline-feed-section';
      feedSection.innerHTML = '<h3>📜 Recent Adventures</h3>';

      const feed = document.createElement('div');
      feed.className = 'timeline-feed';

      timeline.recent_groups.slice(0, 30).forEach(group => {
        // Ensure members is always an array
        const members = Array.isArray(group.members) ? group.members : [];
        const typeIcon = group.type === 'raid' ? '🎪' : '⚔️';
        const location = group.instance || group.zone || 'Unknown';
        const difficulty = group.instance_difficulty ? ` (${group.instance_difficulty})` : '';

        const memberList = members.map(m => {
          const color = getClassColor(m.class);
          return `<span class="member-tag" style="color: ${color}">${m.name}</span>`;
        }).join('');

        feed.innerHTML += `
          <div class="timeline-item">
            <div class="timeline-icon">${typeIcon}</div>
            <div class="timeline-content">
              <div class="timeline-header">
                <strong>${group.type === 'raid' ? 'Raid' : 'Party'}</strong> of ${group.size}
                <span class="timeline-time">${formatDateTime(group.ts)}</span>
              </div>
              <div class="timeline-location">${location}${difficulty}</div>
              <div class="timeline-members">${memberList}</div>
            </div>
          </div>
        `;
      });

      feedSection.appendChild(feed);
      container.appendChild(feedSection);
    }

    return container;
  }

  // ===== Role Icon Mapping =====
  function getRoleIcon(role) {
    const roleIcons = {
      'tank': '🛡️',
      'healer': '❤️',
      'dps': '⚔️',
      'damage': '⚔️'
    };
    return roleIcons[role?.toLowerCase()] || '⚔️';
  }

  // ===== GROUP BRACKETS Tab =====
  function renderGroupBrackets(data) {
    const container = document.createElement('div');
    container.id = 'social-brackets';
    container.className = 'social-tab-content';

    // Search and controls
    const controlsSection = document.createElement('div');
    controlsSection.className = 'brackets-controls';
    controlsSection.innerHTML = `
      <div class="brackets-search-bar">
        <input type="text" id="brackets-search" placeholder="Search dungeons or players..." />
        <input type="date" id="brackets-date-from" />
        <input type="date" id="brackets-date-to" />
        <button id="brackets-clear">Clear Filters</button>
      </div>
    `;
    container.appendChild(controlsSection);

    // Load bracket data
    loadBracketData(container);

    return container;
  }

  function loadBracketData(container) {
    const characterId = container.closest('#tab-social')?.dataset.characterId;
    console.log('[social-brackets] Character ID:', characterId);
    if (!characterId) {
      console.error('[social-brackets] No character ID found');
      return;
    }

    // Show loading
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'brackets-loading';
    loadingDiv.innerHTML = '<div class="loading-spinner">⏳ Loading group brackets...</div>';
    container.appendChild(loadingDiv);

    const url = `sections/social-brackets-data.php?character_id=${characterId}`;
    console.log('[social-brackets] Fetching from:', url);

    fetch(url)
      .then(res => {
        console.log('[social-brackets] Response status:', res.status);
        if (!res.ok) {
          throw new Error(`HTTP ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        console.log('[social-brackets] Data received:', data);
        loadingDiv.remove();
        renderBracketsContent(container, data);
        setupBracketSearch(container, data);
      })
      .catch(err => {
        console.error('[social-brackets] Error loading bracket data:', err);
        loadingDiv.innerHTML = `<div class="error-message">Failed to load bracket data: ${err.message}</div>`;
      });
  }

  function renderBracketsContent(container, data) {
    console.log('[social-brackets] Rendering content with data:', data);
    
    const contentDiv = document.createElement('div');
    contentDiv.className = 'brackets-content';

    // Dungeon Groups Section
    const dungeonSection = document.createElement('div');
    dungeonSection.className = 'bracket-section';
    dungeonSection.innerHTML = '<h3>🏰 Dungeon & Raid Groups</h3>';
    
    const dungeonContainer = document.createElement('div');
    dungeonContainer.className = 'bracket-groups dungeon-groups';
    
    // Show only last 5 groups initially
    const dungeonGroups = data.dungeon_groups || [];
    console.log('[social-brackets] Dungeon groups:', dungeonGroups.length);
    renderBracketGroups(dungeonContainer, dungeonGroups.slice(0, 5));
    
    dungeonSection.appendChild(dungeonContainer);
    contentDiv.appendChild(dungeonSection);

    // Miscellaneous Groups Section
    const miscSection = document.createElement('div');
    miscSection.className = 'bracket-section';
    miscSection.innerHTML = '<h3>⚔️ Miscellaneous Parties</h3>';
    
    const miscContainer = document.createElement('div');
    miscContainer.className = 'bracket-groups misc-groups';
    
    // Show only last 5 groups initially
    const miscGroups = data.misc_groups || [];
    console.log('[social-brackets] Misc groups:', miscGroups.length);
    renderBracketGroups(miscContainer, miscGroups.slice(0, 5));
    
    miscSection.appendChild(miscContainer);
    contentDiv.appendChild(miscSection);

    container.appendChild(contentDiv);
  }

  function renderBracketGroups(container, groups) {
    console.log('[social-brackets] Rendering groups:', groups);
    container.innerHTML = '';
    
    groups.forEach((group, index) => {
      console.log('[social-brackets] Rendering group', index, ':', group);
      const bracketDiv = document.createElement('div');
      bracketDiv.className = 'tournament-bracket';
      
      // Main bracket (dungeon name or "Manual Party")
      const mainBracket = document.createElement('div');
      mainBracket.className = 'bracket-main';
      mainBracket.textContent = group.instance || 'Manual Party';
      if (group.instance_difficulty) {
        mainBracket.textContent += ` (${group.instance_difficulty})`;
      }
      bracketDiv.appendChild(mainBracket);

      // Connection line
      const connector = document.createElement('div');
      connector.className = 'bracket-connector';
      bracketDiv.appendChild(connector);

      // Members brackets
      const membersContainer = document.createElement('div');
      membersContainer.className = 'bracket-members';
      
      const members = group.members || [];
      console.log('[social-brackets] Group members:', members);
      
      members.forEach((member, memberIndex) => {
        console.log('[social-brackets] Rendering member', memberIndex, ':', member);
        const memberBracket = document.createElement('div');
        memberBracket.className = 'bracket-member';
        memberBracket.style.setProperty('--class-color', getClassColor(member.class));
        
        const memberName = document.createElement('div');
        memberName.className = 'member-name';
        memberName.textContent = member.name || 'Unknown';
        
        const memberRole = document.createElement('div');
        memberRole.className = 'member-role';
        memberRole.innerHTML = `${getRoleIcon(member.role)} ${member.role || 'DPS'}`;
        
        const classStrip = document.createElement('div');
        classStrip.className = 'member-class-strip';
        classStrip.style.backgroundColor = getClassColor(member.class);
        classStrip.textContent = member.class || 'Unknown';
        
        memberBracket.appendChild(memberName);
        memberBracket.appendChild(memberRole);
        memberBracket.appendChild(classStrip);
        
        // Add any additional data brackets if available
        if (member.level) {
          const levelBracket = document.createElement('div');
          levelBracket.className = 'bracket-extra';
          levelBracket.textContent = `Lvl ${member.level}`;
          memberBracket.appendChild(levelBracket);
        }
        
        membersContainer.appendChild(memberBracket);
      });
      
      bracketDiv.appendChild(membersContainer);

      // Add timestamp info
      const timeInfo = document.createElement('div');
      timeInfo.className = 'bracket-time';
      timeInfo.textContent = formatDateTime(group.ts);
      bracketDiv.appendChild(timeInfo);

      container.appendChild(bracketDiv);
    });

    if (groups.length === 0) {
      console.log('[social-brackets] No groups to display');
      container.innerHTML = '<div class="no-results">No groups found matching your criteria.</div>';
    }
  }

  function setupBracketSearch(container, data) {
    const searchInput = container.querySelector('#brackets-search');
    const dateFromInput = container.querySelector('#brackets-date-from');
    const dateToInput = container.querySelector('#brackets-date-to');
    const clearButton = container.querySelector('#brackets-clear');

    function performSearch() {
      const searchTerm = searchInput.value.toLowerCase();
      const dateFrom = dateFromInput.value ? new Date(dateFromInput.value).getTime() / 1000 : null;
      const dateTo = dateToInput.value ? new Date(dateToInput.value).getTime() / 1000 : null;

      // Filter dungeon groups
      let filteredDungeonGroups = data.dungeon_groups || [];
      if (searchTerm || dateFrom || dateTo) {
        filteredDungeonGroups = filteredDungeonGroups.filter(group => {
          // Text search
          if (searchTerm) {
            const matchesInstance = (group.instance || '').toLowerCase().includes(searchTerm);
            const matchesPlayer = group.members.some(m => 
              (m.name || '').toLowerCase().includes(searchTerm)
            );
            if (!matchesInstance && !matchesPlayer) return false;
          }
          
          // Date filters
          if (dateFrom && group.ts < dateFrom) return false;
          if (dateTo && group.ts > dateTo) return false;
          
          return true;
        });
      } else {
        // No search - show only last 5
        filteredDungeonGroups = filteredDungeonGroups.slice(0, 5);
      }

      // Filter misc groups
      let filteredMiscGroups = data.misc_groups || [];
      if (searchTerm || dateFrom || dateTo) {
        filteredMiscGroups = filteredMiscGroups.filter(group => {
          // Text search
          if (searchTerm) {
            const matchesPlayer = group.members.some(m => 
              (m.name || '').toLowerCase().includes(searchTerm)
            );
            if (!matchesPlayer) return false;
          }
          
          // Date filters
          if (dateFrom && group.ts < dateFrom) return false;
          if (dateTo && group.ts > dateTo) return false;
          
          return true;
        });
      } else {
        // No search - show only last 5
        filteredMiscGroups = filteredMiscGroups.slice(0, 5);
      }

      // Re-render
      const dungeonContainer = container.querySelector('.dungeon-groups');
      const miscContainer = container.querySelector('.misc-groups');
      
      renderBracketGroups(dungeonContainer, filteredDungeonGroups);
      renderBracketGroups(miscContainer, filteredMiscGroups);
    }

    // Event listeners
    searchInput.addEventListener('input', performSearch);
    dateFromInput.addEventListener('change', performSearch);
    dateToInput.addEventListener('change', performSearch);
    
    clearButton.addEventListener('click', () => {
      searchInput.value = '';
      dateFromInput.value = '';
      dateToInput.value = '';
      performSearch();
    });
  }

  // ===== Main Initialization =====
  function init() {
    log('Initializing social tracker...');

    const tabContent = q('#tab-social');
    if (!tabContent) {
      log('Social tab content not found');
      return;
    }

    const characterId = tabContent.dataset.characterId;
    if (!characterId) {
      log('No character ID found');
      return;
    }

    log('Fetching social data for character:', characterId);

    fetch(`/sections/social-data.php?character_id=${characterId}`)
      .then(res => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then(data => {
        log('Social data loaded:', data);
        render(tabContent, data);
      })
      .catch(err => {
        console.error('[social] Error loading data:', err);
        tabContent.innerHTML = `
          <div class="error-message">
            <div style="font-size: 2rem; margin-bottom: 16px;">❌</div>
            <div>Failed to load social data</div>
            <div style="font-size: 0.9rem; color: #999; margin-top: 8px;">${err.message}</div>
          </div>
        `;
      });
  }

  function render(container, data) {
    container.innerHTML = '';

    // Create tabs
    const tabsContainer = document.createElement('div');
    tabsContainer.className = 'social-tabs';
    tabsContainer.innerHTML = `
      <button class="social-tab active" data-tab="adventure">The True Adventure</button>
      <button class="social-tab" data-tab="party">Party Animals</button>
      <button class="social-tab" data-tab="friends">Friend Network</button>
      <button class="social-tab" data-tab="timeline">Group Timeline</button>
      <button class="social-tab" data-tab="brackets">Group Brackets</button>
    `;
    container.appendChild(tabsContainer);

    // Create tab contents
    const contentContainer = document.createElement('div');
    contentContainer.className = 'social-content-container';

    contentContainer.appendChild(renderTrueAdventure(data));
    contentContainer.appendChild(renderPartyAnimals(data));
    contentContainer.appendChild(renderFriendNetwork(data));
    contentContainer.appendChild(renderGroupTimeline(data));
    contentContainer.appendChild(renderGroupBrackets(data));

    container.appendChild(contentContainer);

    // Setup tab switching
    setupTabs(container);
  }

  // ===== Event Listeners =====
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'social') {
      log('Section loaded event received');
      init();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || 'dashboard';
    if (currentSection === 'social') {
      log('Character changed, reloading social');
      init();
    }
  });

  // Initial load - check if social tab already exists in DOM
  if (q('#tab-social')) {
    log('Found #tab-social on page load, loading now...');
    init();
  } else {
    log('No #tab-social found on initial load, waiting for event...');
  }

  log('Social module loaded and ready');
})();