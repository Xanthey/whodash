/* eslint-disable no-console */
/* WhoDASH Achievements — Achievement & Collection Tracker */
(() => {
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[achievements]', ...a);

  function formatDate(ts) {
    if (!ts) return 'Unknown';
    return new Date(ts * 1000).toLocaleDateString('en-US', { 
      month: 'short', day: 'numeric', year: 'numeric' 
    });
  }

  function timeAgo(ts) {
    if (!ts) return 'Unknown';
    const now = Math.floor(Date.now() / 1000);
    const diff = now - ts;
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    const days = Math.floor(diff / 86400);
    if (days < 30) return `${days}d ago`;
    return formatDate(ts);
  }

  // ===== Tab System =====
  function setupTabs(container) {
    const tabs = qa('.ach-tab', container);
    const contents = qa('.ach-tab-content', container);

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        contents.forEach(c => c.classList.remove('active'));
        const targetContent = q(`#ach-${target}`, container);
        if (targetContent) targetContent.classList.add('active');
      });
    });
  }

  // ===== Overview Tab =====
  function renderOverview(data) {
    const container = document.createElement('div');
    container.id = 'ach-overview';
    container.className = 'ach-tab-content active';

    // Stats Grid
    const statsGrid = document.createElement('div');
    statsGrid.className = 'ach-stats-grid';
    statsGrid.innerHTML = `
      <div class="ach-stat-card">
        <div class="stat-icon">🏆</div>
        <div class="stat-value">${(data.total_points || 0).toLocaleString()}</div>
        <div class="stat-label">Achievement Points</div>
      </div>
      
      <div class="ach-stat-card">
        <div class="stat-icon">🏅</div>
        <div class="stat-value">${(data.total_achievements || 0).toLocaleString()}</div>
        <div class="stat-label">Achievements Earned</div>
      </div>
      
      <div class="ach-stat-card">
        <div class="stat-icon">🐴</div>
        <div class="stat-value">${data.mount_count || 0}</div>
        <div class="stat-label">Mounts Collected</div>
      </div>
      
      <div class="ach-stat-card">
        <div class="stat-icon">🐾</div>
        <div class="stat-value">${data.pet_count || 0}</div>
        <div class="stat-label">Pets Collected</div>
      </div>
    `;
    container.appendChild(statsGrid);

    // Categories Breakdown
    if (data.achievements_by_category && data.achievements_by_category.length > 0) {
      const categorySection = document.createElement('div');
      categorySection.className = 'category-section';
      
      const title = document.createElement('h3');
      title.textContent = '📊 Achievements by Category';
      categorySection.appendChild(title);

      const categoryGrid = document.createElement('div');
      categoryGrid.className = 'category-grid';
      
      data.achievements_by_category.forEach(cat => {
        const card = document.createElement('div');
        card.className = 'category-card';
        card.innerHTML = `
          <div class="category-name">${cat.category}</div>
          <div class="category-stats">
            <div class="category-count">${cat.count} achievements</div>
            <div class="category-points">${cat.points} points</div>
          </div>
        `;
        categoryGrid.appendChild(card);
      });
      
      categorySection.appendChild(categoryGrid);
      container.appendChild(categorySection);
    }

    // Recent Achievements
    if (data.recent_achievements && data.recent_achievements.length > 0) {
      const recentSection = document.createElement('div');
      recentSection.className = 'recent-achievements-section';
      
      const title = document.createElement('h3');
      title.textContent = '⭐ Recent Achievements';
      recentSection.appendChild(title);

      const recentList = document.createElement('div');
      recentList.className = 'recent-achievements-list';
      
      data.recent_achievements.forEach(ach => {
        const item = document.createElement('div');
        item.className = 'recent-achievement-item';
        item.innerHTML = `
          <div class="achievement-content">
            <div class="achievement-name">${ach.name}</div>
            ${ach.description ? `<div class="achievement-desc">${ach.description}</div>` : ''}
          </div>
          <div class="achievement-meta">
            <div class="achievement-points">${ach.points} points</div>
            <div class="achievement-date">${timeAgo(ach.earned_date)}</div>
          </div>
        `;
        recentList.appendChild(item);
      });
      
      recentSection.appendChild(recentList);
      container.appendChild(recentSection);
    }

    // Highest Point Achievements
    if (data.highest_point_achievements && data.highest_point_achievements.length > 0) {
      const highestSection = document.createElement('div');
      highestSection.className = 'highest-achievements-section';
      
      const title = document.createElement('h3');
      title.textContent = '🌟 Highest Point Achievements';
      highestSection.appendChild(title);

      const table = document.createElement('table');
      table.className = 'highest-achievements-table';
      table.innerHTML = `
        <thead>
          <tr>
            <th>Achievement</th>
            <th>Points</th>
            <th>Earned</th>
          </tr>
        </thead>
        <tbody>
          ${data.highest_point_achievements.map(ach => `
            <tr>
              <td><strong>${ach.name}</strong></td>
              <td class="points-cell">${ach.points}</td>
              <td class="muted">${formatDate(ach.earned_date)}</td>
            </tr>
          `).join('')}
        </tbody>
      `;
      
      highestSection.appendChild(table);
      container.appendChild(highestSection);
    }

    return container;
  }

  // ===== Collections Tab =====
  function renderCollections(data) {
    const container = document.createElement('div');
    container.id = 'ach-collections';
    container.className = 'ach-tab-content';

    // Mounts Section
    const mountsSection = document.createElement('div');
    mountsSection.className = 'mounts-section';
    
    const mountsTitle = document.createElement('h3');
    mountsTitle.textContent = `🐴 Mounts (${data.mount_count || 0})`;
    mountsSection.appendChild(mountsTitle);

    if (data.mounts && data.mounts.length > 0) {
      const mountsGrid = document.createElement('div');
      mountsGrid.className = 'collection-grid';
      
      data.mounts.forEach(mount => {
        const card = document.createElement('div');
        card.className = 'collection-card';
        if (mount.active) card.classList.add('active-mount');
        
        // Use icon.php with spell_id just like items.js does
        const iconUrl = mount.spell_id 
          ? `/icon.php?type=spell&id=${mount.spell_id}&size=large`
          : null;
        
        card.innerHTML = `
          ${iconUrl ? `<div class="collection-icon" style="background-image: url('${iconUrl}')"></div>` : '<div class="collection-icon-placeholder">🐴</div>'}
          <div class="collection-name">${mount.name}</div>
          ${mount.active ? '<div class="active-badge">Active</div>' : ''}
        `;
        mountsGrid.appendChild(card);
      });
      
      mountsSection.appendChild(mountsGrid);
    } else {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No mounts collected yet';
      mountsSection.appendChild(msg);
    }
    
    container.appendChild(mountsSection);

    // Pets Section
    const petsSection = document.createElement('div');
    petsSection.className = 'pets-section';
    
    const petsTitle = document.createElement('h3');
    petsTitle.textContent = `🐾 Companion Pets (${data.pet_count || 0})`;
    petsSection.appendChild(petsTitle);

    if (data.pets && data.pets.length > 0) {
      const petsGrid = document.createElement('div');
      petsGrid.className = 'collection-grid';
      
      data.pets.forEach(pet => {
        const card = document.createElement('div');
        card.className = 'collection-card';
        if (pet.active) card.classList.add('active-pet');
        
        // Use icon.php with spell_id just like items.js does
        const iconUrl = pet.spell_id 
          ? `/icon.php?type=spell&id=${pet.spell_id}&size=large`
          : null;
        
        card.innerHTML = `
          ${iconUrl ? `<div class="collection-icon" style="background-image: url('${iconUrl}')"></div>` : '<div class="collection-icon-placeholder">🐾</div>'}
          <div class="collection-name">${pet.name}</div>
          ${pet.active ? '<div class="active-badge">Active</div>' : ''}
        `;
        petsGrid.appendChild(card);
      });
      
      petsSection.appendChild(petsGrid);
    } else {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No pets collected yet';
      petsSection.appendChild(msg);
    }
    
    container.appendChild(petsSection);

    return container;
  }

  // ===== Timeline Tab =====
  function renderTimeline(data) {
    const container = document.createElement('div');
    container.id = 'ach-timeline';
    container.className = 'ach-tab-content';

    // Achievement Spam Days
    if (data.achievement_spam_days && data.achievement_spam_days.length > 0) {
      const spamSection = document.createElement('div');
      spamSection.className = 'spam-days-section';
      
      const title = document.createElement('h3');
      title.textContent = '💥 Achievement Spam Days';
      spamSection.appendChild(title);

      const spamGrid = document.createElement('div');
      spamGrid.className = 'spam-days-grid';
      
      data.achievement_spam_days.forEach(day => {
        const card = document.createElement('div');
        card.className = 'spam-day-card';
        card.innerHTML = `
          <div class="spam-day-date">${day.achievement_date}</div>
          <div class="spam-day-stats">
            <div class="spam-day-count">${day.achievement_count} achievements</div>
            <div class="spam-day-points">${day.points_earned} points</div>
          </div>
        `;
        spamGrid.appendChild(card);
      });
      
      spamSection.appendChild(spamGrid);
      container.appendChild(spamSection);
    }

    // Monthly Achievement Graph
    if (data.achievements_per_month && data.achievements_per_month.length > 0) {
      const graphSection = document.createElement('div');
      graphSection.className = 'monthly-graph-section';
      
      const title = document.createElement('h3');
      title.textContent = '📈 Achievement Activity';
      graphSection.appendChild(title);

      const canvas = document.createElement('canvas');
      canvas.className = 'monthly-graph-canvas';
      canvas.width = 1000;
      canvas.height = 250;
      graphSection.appendChild(canvas);

      container.appendChild(graphSection);

      setTimeout(() => renderMonthlyGraph(canvas, data.achievements_per_month), 0);
    }

    // Achievement Timeline (searchable)
    const timelineSection = document.createElement('div');
    timelineSection.className = 'timeline-section';
    
    const header = document.createElement('div');
    header.className = 'timeline-header';
    header.innerHTML = `
      <h3>📅 Achievement Timeline</h3>
      <input type="text" id="timelineSearch" class="timeline-search" placeholder="Search achievements...">
    `;
    timelineSection.appendChild(header);

    if (data.achievement_timeline && data.achievement_timeline.length > 0) {
      const timelineList = document.createElement('div');
      timelineList.className = 'timeline-list';
      timelineList.id = 'timelineList';

      timelineSection.appendChild(timelineList);

      // Store data
      timelineSection.dataset.timelineData = JSON.stringify(data.achievement_timeline);

      // Initial render
      setTimeout(() => filterAndRenderTimeline(data.achievement_timeline, timelineSection), 0);

      // Setup search
      setTimeout(() => {
        const searchInput = q('#timelineSearch', timelineSection);
        if (searchInput) {
          searchInput.addEventListener('input', () => {
            const allData = JSON.parse(timelineSection.dataset.timelineData);
            filterAndRenderTimeline(allData, timelineSection);
          });
        }
      }, 0);
    } else {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No achievements earned yet';
      timelineSection.appendChild(msg);
    }

    container.appendChild(timelineSection);

    return container;
  }

  function filterAndRenderTimeline(allData, container) {
    const searchInput = q('#timelineSearch', container);
    const timelineList = q('#timelineList', container);

    if (!timelineList) return;

    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';

    const filtered = allData.filter(ach => {
      return !searchTerm || 
        (ach.name && ach.name.toLowerCase().includes(searchTerm)) ||
        (ach.description && ach.description.toLowerCase().includes(searchTerm));
    });

    timelineList.innerHTML = filtered.map(ach => `
      <div class="timeline-item">
        <div class="timeline-date">${formatDate(ach.earned_date)}</div>
        <div class="timeline-achievement">
          <div class="timeline-name">${ach.name}</div>
          ${ach.description ? `<div class="timeline-desc">${ach.description}</div>` : ''}
          <div class="timeline-points">${ach.points} points</div>
        </div>
      </div>
    `).join('');
  }

  // ===== Monthly Graph =====
  function renderMonthlyGraph(canvas, monthlyData) {
    const ctx = canvas.getContext('2d');
    const width = canvas.width;
    const height = canvas.height;
    const padding = 50;
    const plotWidth = width - padding * 2;
    const plotHeight = height - padding * 2;

    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, width, height);

    if (monthlyData.length === 0) return;

    const maxCount = Math.max(...monthlyData.map(m => m.achievement_count));

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

    // Bars
    const barWidth = plotWidth / monthlyData.length;
    monthlyData.forEach((month, idx) => {
      const x = padding + idx * barWidth;
      const barHeight = (month.achievement_count / maxCount) * plotHeight;
      const y = padding + plotHeight - barHeight;

      ctx.fillStyle = '#3182ce';
      ctx.fillRect(x + 2, y, barWidth - 4, barHeight);
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

    // X labels
    ctx.textAlign = 'center';
    const labelStep = Math.ceil(monthlyData.length / 10);
    monthlyData.forEach((month, idx) => {
      if (idx % labelStep === 0) {
        const x = padding + idx * barWidth + barWidth / 2;
        ctx.fillText(month.month, x, height - padding + 20);
      }
    });
  }

  // ===== Main Render =====
  async function initAchievements() {
    const section = q('#tab-achievements');
    if (!section) {
      log('Section not found');
      return;
    }

    const characterId = section.dataset.characterId;
    if (!characterId) {
      section.innerHTML = '<div class="muted">No character selected</div>';
      return;
    }

    log('Loading achievement data for character', characterId);

    try {
      const response = await fetch(`/sections/achievements-data.php?character_id=${characterId}`, {
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
      log('Achievement data loaded:', data);

      const container = document.createElement('div');
      container.className = 'ach-container';

      // Tab Navigation
      const tabNav = document.createElement('div');
      tabNav.className = 'ach-tabs';
      tabNav.innerHTML = `
        <button class="ach-tab active" data-tab="overview">📊 Overview</button>
        <button class="ach-tab" data-tab="collections">🎁 Collections</button>
        <button class="ach-tab" data-tab="timeline">📅 Timeline</button>
      `;
      container.appendChild(tabNav);

      // Tab Contents
      const contentWrapper = document.createElement('div');
      contentWrapper.className = 'ach-content-wrapper';
      
      contentWrapper.appendChild(renderOverview(data));
      contentWrapper.appendChild(renderCollections(data));
      contentWrapper.appendChild(renderTimeline(data));
      
      container.appendChild(contentWrapper);

      section.innerHTML = '';
      section.appendChild(container);

      setupTabs(section);

    } catch (error) {
      log('Error loading achievement data:', error);
      section.innerHTML = `<div class="muted">Error loading achievement data: ${error.message}</div>`;
    }
  }

  // ===== Auto-init =====
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'achievements') {
      log('Section loaded event received');
      initAchievements();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (q('#tab-achievements')) initAchievements();
    });
  } else {
    if (q('#tab-achievements')) initAchievements();
  }

  log('Achievements module loaded');
})();