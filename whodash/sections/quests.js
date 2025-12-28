/* eslint-disable no-console */
/* WhoDASH Quests — Enhanced Quest Tracking with Events & Timelines */
(() => {
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[quests]', ...a);

  function formatGold(copper) {
    const g = Math.floor(copper / 10000);
    const s = Math.floor((copper % 10000) / 100);
    const c = copper % 100;
    if (g > 0) return `${g}g ${s}s ${c}c`;
    if (s > 0) return `${s}s ${c}c`;
    return `${c}c`;
  }

  function formatDate(ts) {
    return new Date(ts * 1000).toLocaleDateString('en-US', { 
      month: 'short', day: 'numeric', year: 'numeric' 
    });
  }

  function formatDateTime(ts) {
    return new Date(ts * 1000).toLocaleString('en-US', { 
      month: 'short', day: 'numeric', year: 'numeric',
      hour: '2-digit', minute: '2-digit'
    });
  }

  function getQualityColor(quality) {
    const colors = {
      0: '#9d9d9d', 1: '#ffffff', 2: '#1eff00',
      3: '#0070dd', 4: '#a335ee', 5: '#ff8000',
    };
    return colors[quality] || '#ffffff';
  }

  function getQualityName(quality) {
    const names = {
      0: 'Poor', 1: 'Common', 2: 'Uncommon', 
      3: 'Rare', 4: 'Epic', 5: 'Legendary'
    };
    return names[quality] || 'Unknown';
  }

  function getEventIcon(kind) {
    return { accepted: '✅', abandoned: '❌', completed: '🎉', objective: '📝' }[kind] || '•';
  }

  function getEventColor(kind) {
    return { accepted: '#10b981', abandoned: '#ef4444', completed: '#f59e0b', objective: '#3b82f6' }[kind] || '#6b7280';
  }

  // ===== Tab System =====
  function setupTabs(container) {
    qa('.quest-tab', container).forEach(tab => {
      tab.addEventListener('click', () => {
        qa('.quest-tab', container).forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        qa('.quest-tab-content', container).forEach(c => c.classList.remove('active'));
        const target = q(`#quest-${tab.dataset.tab}`, container);
        if (target) target.classList.add('active');
      });
    });
  }

  // ===== ENHANCED OVERVIEW TAB =====
  function renderOverview(data) {
    const container = document.createElement('div');
    container.id = 'quest-overview';
    container.className = 'quest-tab-content active';

    // Hero Stats Grid
    const statsGrid = document.createElement('div');
    statsGrid.className = 'quest-stats-grid';
    
    const completionRateClass = (data.completion_rate || 0) >= 80 ? 'stat-card-success' : '';
    
    statsGrid.innerHTML = `
      <div class="quest-stat-card">
        <div class="stat-icon">📜</div>
        <div class="stat-value">${(data.total_quests || 0).toLocaleString()}</div>
        <div class="stat-label">Quests Completed</div>
      </div>
      
      <div class="quest-stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value">${(data.total_accepted || 0).toLocaleString()}</div>
        <div class="stat-label">Quests Accepted</div>
      </div>
      
      <div class="quest-stat-card">
        <div class="stat-icon">❌</div>
        <div class="stat-value">${(data.total_abandoned || 0).toLocaleString()}</div>
        <div class="stat-label">Quests Abandoned</div>
      </div>
      
      <div class="quest-stat-card ${completionRateClass}">
        <div class="stat-icon">🎯</div>
        <div class="stat-value">${data.completion_rate || 0}%</div>
        <div class="stat-label">Completion Rate</div>
      </div>
      
      <div class="quest-stat-card">
        <div class="stat-icon">⭐</div>
        <div class="stat-value">${(data.total_xp || 0).toLocaleString()}</div>
        <div class="stat-label">Total XP Earned</div>
      </div>
      
      <div class="quest-stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value">${(data.total_gold || 0).toLocaleString()}g</div>
        <div class="stat-label">Total Gold Earned</div>
      </div>
      
      <div class="quest-stat-card">
        <div class="stat-icon">📊</div>
        <div class="stat-value">${data.quests_per_day || 0}</div>
        <div class="stat-label">Quests Per Day</div>
        <div class="stat-sublabel">(last 90 days)</div>
      </div>

      <div class="quest-stat-card">
        <div class="stat-icon">🔥</div>
        <div class="stat-value">${(data.total_quest_events || 0).toLocaleString()}</div>
        <div class="stat-label">Total Quest Events</div>
      </div>
    `;
    container.appendChild(statsGrid);

    // Most Rewarding Quest
    if (data.most_rewarding_quest) {
      const quest = data.most_rewarding_quest;
      const rewardCard = document.createElement('div');
      rewardCard.className = 'most-rewarding-quest-card';
      rewardCard.innerHTML = `
        <h3>🏆 Most Rewarding Quest</h3>
        <div class="rewarding-quest-name">${quest.quest_title || 'Unknown Quest'}</div>
        <div class="rewarding-quest-details">
          <div>💰 ${formatGold(quest.money || 0)}</div>
          <div>⭐ ${(quest.xp || 0).toLocaleString()} XP</div>
          ${quest.reward_chosen_name ? `<div style="color: ${getQualityColor(quest.reward_chosen_quality)}">🎁 ${quest.reward_chosen_name}</div>` : ''}
        </div>
      `;
      container.appendChild(rewardCard);
    }

    // Quest Event Timeline
    if (data.quest_event_timeline && data.quest_event_timeline.length > 0) {
      const timelineCard = document.createElement('div');
      timelineCard.className = 'quest-trends-card';
      
      const title = document.createElement('h3');
      title.innerHTML = '📅 Quest Activity Timeline <span style="font-size:0.8em;color:#6b7280;font-weight:normal">(Last 90 Days)</span>';
      timelineCard.appendChild(title);

      const canvas = document.createElement('canvas');
      canvas.className = 'quest-timeline-canvas';
      canvas.width = 1000;
      canvas.height = 300;
      timelineCard.appendChild(canvas);

      container.appendChild(timelineCard);
      setTimeout(() => renderQuestEventTimeline(canvas, data.quest_event_timeline), 0);
    }

    // Quest Completion Trends
    if (data.quest_trends && data.quest_trends.length > 0) {
      const trendsCard = document.createElement('div');
      trendsCard.className = 'quest-trends-card';
      
      const title = document.createElement('h3');
      title.innerHTML = '📈 Quest Completions <span style="font-size:0.8em;color:#6b7280;font-weight:normal">(Last 90 Days)</span>';
      trendsCard.appendChild(title);

      const canvas = document.createElement('canvas');
      canvas.className = 'quest-trends-canvas';
      canvas.width = 1000;
      canvas.height = 250;
      trendsCard.appendChild(canvas);

      container.appendChild(trendsCard);
      setTimeout(() => renderQuestTrendsChart(canvas, data.quest_trends), 0);
    }

    // Quests by Zone
    if (data.quests_by_zone && data.quests_by_zone.length > 0) {
      const zoneSection = document.createElement('div');
      zoneSection.className = 'quests-by-zone-section';
      
      const title = document.createElement('h3');
      title.textContent = '🗺️ Top Quest Zones';
      zoneSection.appendChild(title);

      const zoneGrid = document.createElement('div');
      zoneGrid.className = 'zone-bars';
      
      const maxQuests = Math.max(...data.quests_by_zone.map(z => z.quest_count));
      
      data.quests_by_zone.forEach(zone => {
        const bar = document.createElement('div');
        bar.className = 'zone-bar-container';
        bar.innerHTML = `
          <div class="zone-bar-label">${zone.zone}</div>
          <div class="zone-bar-track">
            <div class="zone-bar-fill" style="width: ${(zone.quest_count / maxQuests * 100)}%"></div>
          </div>
          <div class="zone-bar-count">${zone.quest_count}</div>
        `;
        zoneGrid.appendChild(bar);
      });
      
      zoneSection.appendChild(zoneGrid);
      container.appendChild(zoneSection);
    }

    // Quest Level Distribution
    if (data.quest_level_distribution && data.quest_level_distribution.length > 0) {
      const levelSection = document.createElement('div');
      levelSection.className = 'quest-level-section';
      
      const title = document.createElement('h3');
      title.textContent = '📊 Quest Level Distribution';
      levelSection.appendChild(title);

      const levelBars = document.createElement('div');
      levelBars.className = 'level-bars';
      
      const maxQuests = Math.max(...data.quest_level_distribution.map(l => l.quest_count));
      
      data.quest_level_distribution.forEach(level => {
        const bar = document.createElement('div');
        bar.className = 'level-bar-container';
        bar.innerHTML = `
          <div class="level-bar-label">Lvl ${level.level_range}</div>
          <div class="level-bar-track">
            <div class="level-bar-fill" style="width: ${(level.quest_count / maxQuests * 100)}%"></div>
          </div>
          <div class="level-bar-count">${level.quest_count}</div>
        `;
        levelBars.appendChild(bar);
      });
      
      levelSection.appendChild(levelBars);
      container.appendChild(levelSection);
    }

    return container;
  }

  // ===== Quest Event Timeline Chart =====
  function renderQuestEventTimeline(canvas, events) {
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    const padding = 60;
    const plotWidth = width - padding * 2;
    const plotHeight = height - padding * 2;

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, width, height);

    if (events.length === 0) return;

    // Group events by date and kind
    const byDate = {};
    events.forEach(evt => {
      const date = evt.date;
      if (!byDate[date]) byDate[date] = { accepted: 0, completed: 0, abandoned: 0 };
      byDate[date][evt.kind] = parseInt(evt.count);
    });

    const dates = Object.keys(byDate).sort();
    const maxCount = Math.max(...dates.map(d => 
      Math.max(byDate[d].accepted, byDate[d].completed, byDate[d].abandoned)
    ), 1);

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

    // Lines
    const kinds = ['accepted', 'completed', 'abandoned'];
    const colors = { accepted: '#10b981', completed: '#f59e0b', abandoned: '#ef4444' };

    kinds.forEach(kind => {
      ctx.strokeStyle = colors[kind];
      ctx.lineWidth = 2.5;
      ctx.beginPath();

      dates.forEach((date, idx) => {
        const x = padding + (plotWidth / (dates.length - 1 || 1)) * idx;
        const count = byDate[date][kind] || 0;
        const y = padding + plotHeight - (count / maxCount) * plotHeight;

        if (idx === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
      });
      ctx.stroke();

      // Points
      dates.forEach((date, idx) => {
        const x = padding + (plotWidth / (dates.length - 1 || 1)) * idx;
        const count = byDate[date][kind] || 0;
        const y = padding + plotHeight - (count / maxCount) * plotHeight;
        ctx.fillStyle = colors[kind];
        ctx.beginPath();
        ctx.arc(x, y, 4, 0, Math.PI * 2);
        ctx.fill();
      });
    });

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
      const value = Math.round((maxCount / 5) * (5 - i));
      const y = padding + (plotHeight / 5) * i;
      ctx.fillText(value.toString(), padding - 10, y + 4);
    }

    // Legend
    ctx.font = '13px system-ui';
    ctx.textAlign = 'left';
    let legendX = width - padding - 220;
    const legendY = padding - 20;

    kinds.forEach((kind, idx) => {
      ctx.fillStyle = colors[kind];
      ctx.fillRect(legendX, legendY, 12, 12);
      ctx.fillStyle = '#374151';
      ctx.fillText(kind.charAt(0).toUpperCase() + kind.slice(1), legendX + 18, legendY + 10);
      legendX += 80;
    });
  }

  // ===== Quest Trends Chart =====
  function renderQuestTrendsChart(canvas, trends) {
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    const padding = 50;
    const plotWidth = width - padding * 2;
    const plotHeight = height - padding * 2;

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, width, height);

    if (trends.length === 0) return;

    const maxQuests = Math.max(...trends.map(t => t.quests_completed), 1);

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
    ctx.strokeStyle = '#3182ce';
    ctx.lineWidth = 2.5;
    ctx.beginPath();

    trends.forEach((trend, idx) => {
      const x = padding + (plotWidth / (trends.length - 1 || 1)) * idx;
      const y = padding + plotHeight - (trend.quests_completed / maxQuests) * plotHeight;
      if (idx === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });
    ctx.stroke();

    // Points
    trends.forEach((trend, idx) => {
      const x = padding + (plotWidth / (trends.length - 1 || 1)) * idx;
      const y = padding + plotHeight - (trend.quests_completed / maxQuests) * plotHeight;
      ctx.fillStyle = '#3182ce';
      ctx.beginPath();
      ctx.arc(x, y, 4, 0, Math.PI * 2);
      ctx.fill();
    });

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
      const value = Math.round((maxQuests / 5) * (5 - i));
      const y = padding + (plotHeight / 5) * i;
      ctx.fillText(value.toString(), padding - 10, y + 4);
    }

    // X labels
    ctx.textAlign = 'center';
    const labelStep = Math.ceil(trends.length / 8);
    trends.forEach((trend, idx) => {
      if (idx % labelStep === 0 || idx === trends.length - 1) {
        const x = padding + (plotWidth / (trends.length - 1 || 1)) * idx;
        const date = new Date(trend.date);
        const label = `${date.getMonth() + 1}/${date.getDate()}`;
        ctx.save();
        ctx.translate(x, height - padding + 20);
        ctx.rotate(-Math.PI / 4);
        ctx.fillText(label, 0, 0);
        ctx.restore();
      }
    });
  }

  // ===== ENHANCED QUEST LOG TAB =====
  function renderQuestLog(data) {
    const container = document.createElement('div');
    container.id = 'quest-log';
    container.className = 'quest-tab-content';

    // Current Quest Log Section
    if (data.current_quest_log && data.current_quest_log.length > 0) {
      const currentSection = document.createElement('div');
      currentSection.className = 'current-quest-log-section';
      
      const title = document.createElement('h3');
      title.textContent = '📋 Current Quest Log';
      currentSection.appendChild(title);

      const questGrid = document.createElement('div');
      questGrid.className = 'current-quest-grid';

      data.current_quest_log.forEach(quest => {
        const questCard = document.createElement('div');
        questCard.className = `current-quest-card ${quest.quest_complete ? 'quest-complete' : ''}`;
        
        const objectives = Array.isArray(quest.objectives) ? quest.objectives : [];
        const objectivesHTML = objectives.length > 0 ? objectives.map(obj => {
          const completeClass = obj.complete ? 'objective-complete' : '';
          const progressText = obj.cur !== undefined && obj.total !== undefined 
            ? `<span class="objective-progress">${obj.cur}/${obj.total}</span>` 
            : '';
          return `<div class="objective-item ${completeClass}">
            ${obj.complete ? '✓' : '○'} ${obj.text} ${progressText}
          </div>`;
        }).join('') : '<div class="muted">No objectives</div>';

        questCard.innerHTML = `
          <div class="quest-card-header">
            <div class="quest-card-title">${quest.quest_title || 'Unknown Quest'}</div>
            ${quest.quest_complete ? '<div class="quest-complete-badge">Complete</div>' : ''}
          </div>
          <div class="quest-objectives">${objectivesHTML}</div>
          <div class="quest-card-footer muted">Last seen: ${formatDateTime(quest.ts)}</div>
        `;
        questGrid.appendChild(questCard);
      });

      currentSection.appendChild(questGrid);
      container.appendChild(currentSection);
    }

    // Quest Event History
    if (data.quest_event_history && data.quest_event_history.length > 0) {
      const historySection = document.createElement('div');
      historySection.className = 'quest-event-history-section';
      
      const title = document.createElement('h3');
      title.textContent = '📜 Recent Quest Events';
      historySection.appendChild(title);

      const timeline = document.createElement('div');
      timeline.className = 'quest-event-timeline';

      data.quest_event_history.forEach(event => {
        const eventItem = document.createElement('div');
        eventItem.className = 'quest-event-item';
        eventItem.style.borderLeft = `4px solid ${getEventColor(event.kind)}`;

        let eventDetails = '';
        if (event.kind === 'objective' && event.objective_text) {
          const progress = event.objective_progress !== null && event.objective_total !== null
            ? `<span class="objective-progress">${event.objective_progress}/${event.objective_total}</span>`
            : '';
          eventDetails = `<div class="event-detail">${event.objective_text} ${progress}</div>`;
        }

        eventItem.innerHTML = `
          <div class="event-icon">${getEventIcon(event.kind)}</div>
          <div class="event-content">
            <div class="event-title">${event.quest_title || 'Unknown Quest'}</div>
            <div class="event-kind">${event.kind.charAt(0).toUpperCase() + event.kind.slice(1)}</div>
            ${eventDetails}
            <div class="event-time muted">${formatDateTime(event.ts)}</div>
          </div>
        `;
        timeline.appendChild(eventItem);
      });

      historySection.appendChild(timeline);
      container.appendChild(historySection);
    }

    // Quest Completions Table
    const header = document.createElement('div');
    header.className = 'quest-log-header';
    header.innerHTML = `
      <h3>🏆 Quest Completions</h3>
      <div class="quest-filters">
        <input type="text" id="questSearchInput" class="quest-search-input" placeholder="Search quests...">
        <input type="number" id="minLevelInput" class="level-input" placeholder="Min Lvl" min="1" max="80">
        <input type="number" id="maxLevelInput" class="level-input" placeholder="Max Lvl" min="1" max="80">
        <button id="clearQuestFilters" class="clear-filters-btn">Clear</button>
      </div>
    `;
    container.appendChild(header);

    if (!data.all_quests || data.all_quests.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No quest completion data available';
      container.appendChild(msg);
      return container;
    }

    const tableContainer = document.createElement('div');
    tableContainer.className = 'quest-log-table-container';
    
    const table = document.createElement('table');
    table.className = 'quest-log-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Quest</th>
          <th>Zone</th>
          <th>Level</th>
          <th>Reward</th>
          <th>XP</th>
          <th>Gold</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody id="questLogTableBody"></tbody>
    `;
    tableContainer.appendChild(table);
    container.appendChild(tableContainer);

    const paginationInfo = document.createElement('div');
    paginationInfo.className = 'pagination-info';
    paginationInfo.id = 'questLogPaginationInfo';
    container.appendChild(paginationInfo);

    // Notable Quests
    const notableSection = document.createElement('div');
    notableSection.className = 'notable-quests-section';
    notableSection.innerHTML = `
      <h3>🌟 Notable Quests</h3>
      <div class="notable-quests-grid">
        <div class="notable-quest-card">
          <h4>Highest XP</h4>
          <ul>
            ${(data.highest_xp_quests || []).map(q => `
              <li><strong>${q.quest_title}</strong> - ${q.xp.toLocaleString()} XP</li>
            `).join('')}
          </ul>
        </div>
        <div class="notable-quest-card">
          <h4>Highest Gold</h4>
          <ul>
            ${(data.highest_gold_quests || []).map(q => `
              <li><strong>${q.quest_title}</strong> - ${formatGold(q.money)}</li>
            `).join('')}
          </ul>
        </div>
        <div class="notable-quest-card">
          <h4>Epic Rewards</h4>
          <ul>
            ${(data.epic_reward_quests || []).map(q => `
              <li><strong>${q.quest_title}</strong> - ${q.reward_chosen_name}</li>
            `).join('')}
          </ul>
        </div>
      </div>
    `;
    container.appendChild(notableSection);

    // Store data
    container.dataset.questData = JSON.stringify(data.all_quests);

    // Initial render
    setTimeout(() => filterAndRenderQuestLog(data.all_quests, container), 0);

    // Setup filters
    setTimeout(() => {
      const searchInput = q('#questSearchInput', container);
      const minLevel = q('#minLevelInput', container);
      const maxLevel = q('#maxLevelInput', container);
      const clearBtn = q('#clearQuestFilters', container);

      const applyFilters = () => {
        const allData = JSON.parse(container.dataset.questData);
        filterAndRenderQuestLog(allData, container);
      };

      if (searchInput) searchInput.addEventListener('input', applyFilters);
      if (minLevel) minLevel.addEventListener('input', applyFilters);
      if (maxLevel) maxLevel.addEventListener('input', applyFilters);
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          if (searchInput) searchInput.value = '';
          if (minLevel) minLevel.value = '';
          if (maxLevel) maxLevel.value = '';
          applyFilters();
        });
      }
    }, 0);

    return container;
  }

  function filterAndRenderQuestLog(allData, container) {
    const searchInput = q('#questSearchInput', container);
    const minLevel = q('#minLevelInput', container);
    const maxLevel = q('#maxLevelInput', container);
    const tbody = q('#questLogTableBody', container);
    const paginationInfo = q('#questLogPaginationInfo', container);

    if (!tbody) return;

    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const min = minLevel && minLevel.value ? parseInt(minLevel.value) : null;
    const max = maxLevel && maxLevel.value ? parseInt(maxLevel.value) : null;

    const filtered = allData.filter(quest => {
      const matchesSearch = !searchTerm || 
        (quest.quest_title && quest.quest_title.toLowerCase().includes(searchTerm)) ||
        (quest.zone && quest.zone.toLowerCase().includes(searchTerm));
      
      const matchesMinLevel = !min || (quest.quest_level && quest.quest_level >= min);
      const matchesMaxLevel = !max || (quest.quest_level && quest.quest_level <= max);

      return matchesSearch && matchesMinLevel && matchesMaxLevel;
    });

    tbody.innerHTML = filtered.map(quest => {
      const rewardColor = getQualityColor(quest.reward_chosen_quality);
      return `
        <tr>
          <td><strong>${quest.quest_title || 'Unknown'}</strong></td>
          <td>${quest.zone || '—'}</td>
          <td>${quest.quest_level || '?'}</td>
          <td style="color: ${rewardColor}">${quest.reward_chosen_name || 'None'}</td>
          <td>${quest.xp ? quest.xp.toLocaleString() : '0'}</td>
          <td>${quest.money ? formatGold(quest.money) : '0c'}</td>
          <td class="muted">${formatDate(quest.ts)}</td>
        </tr>
      `;
    }).join('');

    if (paginationInfo) {
      paginationInfo.textContent = `Showing ${filtered.length.toLocaleString()} of ${allData.length.toLocaleString()} quests`;
    }
  }

  // ===== REWARDS TAB =====
  function renderRewards(data) {
    const container = document.createElement('div');
    container.id = 'quest-rewards';
    container.className = 'quest-tab-content';

    // Quality Distribution Pie Chart
    if (data.reward_quality_distribution && data.reward_quality_distribution.length > 0) {
      const qualitySection = document.createElement('div');
      qualitySection.className = 'reward-quality-section';
      
      const title = document.createElement('h3');
      title.textContent = '💎 Reward Quality Distribution';
      qualitySection.appendChild(title);

      const canvas = document.createElement('canvas');
      canvas.className = 'quality-pie-chart';
      canvas.width = 300;
      canvas.height = 300;
      qualitySection.appendChild(canvas);

      const legend = document.createElement('div');
      legend.className = 'pie-legend';
      qualitySection.appendChild(legend);

      container.appendChild(qualitySection);

      setTimeout(() => renderQualityPieChart(canvas, legend, data.reward_quality_distribution), 0);
    }

    // Slot Distribution
    if (data.reward_slot_distribution && data.reward_slot_distribution.length > 0) {
      const slotSection = document.createElement('div');
      slotSection.className = 'reward-slot-section';
      
      const title = document.createElement('h3');
      title.textContent = '🎯 Reward Slot Preferences';
      slotSection.appendChild(title);

      const slotBars = document.createElement('div');
      slotBars.className = 'slot-bars';
      
      const maxCount = Math.max(...data.reward_slot_distribution.map(s => s.times_chosen));
      
      data.reward_slot_distribution.forEach(slot => {
        const bar = document.createElement('div');
        bar.className = 'slot-bar-container';
        bar.innerHTML = `
          <div class="slot-bar-label">${slot.slot}</div>
          <div class="slot-bar-track">
            <div class="slot-bar-fill" style="width: ${(slot.times_chosen / maxCount * 100)}%"></div>
          </div>
          <div class="slot-bar-count">${slot.times_chosen}</div>
        `;
        slotBars.appendChild(bar);
      });
      
      slotSection.appendChild(slotBars);
      container.appendChild(slotSection);
    }

    // Top Chosen Rewards
    if (data.top_chosen_rewards && data.top_chosen_rewards.length > 0) {
      const topSection = document.createElement('div');
      topSection.className = 'top-rewards-section';
      
      const title = document.createElement('h3');
      title.textContent = '⭐ Most Chosen Rewards';
      topSection.appendChild(title);

      const rewardGrid = document.createElement('div');
      rewardGrid.className = 'reward-grid';
      
      data.top_chosen_rewards.forEach(reward => {
        const card = document.createElement('div');
        card.className = 'reward-card';
        card.style.borderLeft = `4px solid ${getQualityColor(reward.reward_chosen_quality)}`;
        card.innerHTML = `
          <div class="reward-name" style="color: ${getQualityColor(reward.reward_chosen_quality)}">
            ${reward.reward_chosen_name}
          </div>
          <div class="reward-count">Chosen ${reward.times_chosen}x</div>
        `;
        rewardGrid.appendChild(card);
      });
      
      topSection.appendChild(rewardGrid);
      container.appendChild(topSection);
    }

    return container;
  }

  // ===== Quality Pie Chart =====
  function renderQualityPieChart(canvas, legendEl, data) {
    const ctx = canvas.getContext('2d');
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = Math.min(centerX, centerY) - 20;

    const total = data.reduce((sum, d) => sum + d.times_chosen, 0);

    let currentAngle = -Math.PI / 2;

    data.forEach((item) => {
      const sliceAngle = (item.times_chosen / total) * 2 * Math.PI;
      const color = getQualityColor(item.quality_id);
      
      ctx.fillStyle = color;
      ctx.beginPath();
      ctx.moveTo(centerX, centerY);
      ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
      ctx.closePath();
      ctx.fill();

      ctx.strokeStyle = '#fff';
      ctx.lineWidth = 2;
      ctx.stroke();

      currentAngle += sliceAngle;
    });

    // Legend
    legendEl.innerHTML = data.map((item) => {
      const pct = ((item.times_chosen / total) * 100).toFixed(1);
      return `
        <div class="legend-item">
          <div class="legend-color" style="background: ${getQualityColor(item.quality_id)}"></div>
          <div class="legend-label">${item.quality_name}</div>
          <div class="legend-value">${pct}% (${item.times_chosen})</div>
        </div>
      `;
    }).join('');
  }

  // ===== Main Render =====
  async function initQuests() {
    const section = q('#tab-quests');
    if (!section) {
      log('Section not found');
      return;
    }

    const characterId = section.dataset.characterId;
    if (!characterId) {
      section.innerHTML = '<div class="muted">No character selected</div>';
      return;
    }

    log('Loading quest data for character', characterId);

    try {
      const response = await fetch(`/sections/quests-data.php?character_id=${characterId}`, {
        credentials: 'include'
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        if (errorData && errorData.message) {
          throw new Error(`HTTP ${response.status}: ${errorData.message}`);
        }
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      log('Quest data loaded:', data);

      const container = document.createElement('div');
      container.className = 'quest-container';

      // Tab Navigation
      const tabNav = document.createElement('div');
      tabNav.className = 'quest-tabs';
      tabNav.innerHTML = `
        <button class="quest-tab active" data-tab="overview">📊 Overview</button>
        <button class="quest-tab" data-tab="log">📜 Quest Log</button>
        <button class="quest-tab" data-tab="rewards">🎁 Rewards</button>
      `;
      container.appendChild(tabNav);

      // Tab Contents
      const contentWrapper = document.createElement('div');
      contentWrapper.className = 'quest-content-wrapper';
      
      contentWrapper.appendChild(renderOverview(data));
      contentWrapper.appendChild(renderQuestLog(data));
      contentWrapper.appendChild(renderRewards(data));
      
      container.appendChild(contentWrapper);

      section.innerHTML = '';
      section.appendChild(container);

      setupTabs(section);

    } catch (error) {
      log('Error loading quest data:', error);
      section.innerHTML = `<div class="muted">Error loading quest data: ${error.message}</div>`;
    }
  }

  // ===== Auto-init =====
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'quests') {
      log('Section loaded event received');
      initQuests();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (q('#tab-quests')) initQuests();
    });
  } else {
    if (q('#tab-quests')) initQuests();
  }

  log('Quests module loaded');
})();