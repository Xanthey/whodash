/* eslint-disable no-console */
/* WhoDASH Role Performance - Unified Role Analytics */
(() => {
  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[role]', ...a);

  function formatNumber(num) {
    if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
    if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
    return Math.round(num).toLocaleString();
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

  // Track which sub-modules have been loaded
  const loadedModules = {
    damage: false,
    tanking: false,
    healing: false,
  };

  // ===== ROLE OVERVIEW RENDERER =====
  
  function renderRoleOverview(data) {
    const pane = q('#role-pane-overview');
    if (!pane) return;

    pane.innerHTML = '';

    // Title
    const title = document.createElement('h2');
    title.className = 'role-overview-title';
    title.innerHTML = '📊 Multi-Role Performance Overview';
    pane.appendChild(title);

    // Primary role badge
    if (data.primary_role) {
      const roleLabels = {
        damage: '⚔️ Primary: Damage Dealer',
        tanking: '🛡️ Primary: Tank',
        healing: '💚 Primary: Healer'
      };
      
      const badge = document.createElement('div');
      badge.className = `primary-role-badge ${data.primary_role}`;
      badge.textContent = roleLabels[data.primary_role] || 'Hybrid';
      pane.appendChild(badge);
    }

    // Role stats grid
    const grid = document.createElement('div');
    grid.className = 'role-stats-grid';
    
    // Damage Card
    const damageCard = document.createElement('div');
    damageCard.className = 'role-stat-card damage';
    damageCard.innerHTML = `
      <div class="role-card-header">
        <span class="role-card-icon">⚔️</span>
        <span class="role-card-title">Damage</span>
      </div>
      <div class="role-card-stats">
        <div class="role-stat">
          <div class="role-stat-label">Avg DPS</div>
          <div class="role-stat-value">${formatNumber(data.damage_stats.avg_dps)}</div>
        </div>
        <div class="role-stat">
          <div class="role-stat-label">Max DPS</div>
          <div class="role-stat-value">${formatNumber(data.damage_stats.max_dps)}</div>
        </div>
        <div class="role-stat">
          <div class="role-stat-label">Total Damage</div>
          <div class="role-stat-value">${formatNumber(data.damage_stats.total_damage)}</div>
        </div>
        <div class="role-stat">
          <div class="role-stat-label">Encounters</div>
          <div class="role-stat-value">${formatNumber(data.damage_stats.encounters)}</div>
        </div>
      </div>
      <div class="role-card-footer">
        ${data.damage_stats.uptime_hours}h combat time
      </div>
    `;
    grid.appendChild(damageCard);

    // Tanking Card
    const tankCard = document.createElement('div');
    tankCard.className = 'role-stat-card tanking';
    tankCard.innerHTML = `
      <div class="role-card-header">
        <span class="role-card-icon">🛡️</span>
        <span class="role-card-title">Tanking</span>
      </div>
      <div class="role-card-stats">
        <div class="role-stat">
          <div class="role-stat-label">Avg DTPS</div>
          <div class="role-stat-value">${formatNumber(data.tanking_stats.avg_dtps)}</div>
        </div>
        <div class="role-stat">
          <div class="role-stat-label">Survival Rate</div>
          <div class="role-stat-value ${data.tanking_stats.survival_rate >= 95 ? 'positive' : ''}">${data.tanking_stats.survival_rate}%</div>
        </div>
        <div class="role-stat">
          <div class="role-stat-label">Damage Taken</div>
          <div class="role-stat-value">${formatNumber(data.tanking_stats.total_damage_taken)}</div>
        </div>
        <div class="role-stat">
          <div class="role-stat-label">Deaths</div>
          <div class="role-stat-value ${data.tanking_stats.deaths === 0 ? 'positive' : 'negative'}">${data.tanking_stats.deaths}</div>
        </div>
      </div>
      <div class="role-card-footer">
        ${data.tanking_stats.encounters} encounters
      </div>
    `;
    grid.appendChild(tankCard);

    // Healing Card
    const healCard = document.createElement('div');
    healCard.className = 'role-stat-card healing';
    healCard.innerHTML = `
      <div class="role-card-header">
        <span class="role-card-icon">💚</span>
        <span class="role-card-title">Healing</span>
      </div>
      <div class="role-card-stats">
        <div class="role-stat">
          <div class="role-stat-label">Avg HPS</div>
          <div class="role-stat-value">${formatNumber(data.healing_stats.avg_hps)}</div>
        </div>
        <div class="role-stat">
          <div class="role-stat-label">Max HPS</div>
          <div class="role-stat-value">${formatNumber(data.healing_stats.max_hps)}</div>
        </div>
        <div class="role-stat">
          <div class="role-stat-label">Total Healing</div>
          <div class="role-stat-value">${formatNumber(data.healing_stats.total_healing)}</div>
        </div>
        <div class="role-stat">
          <div class="role-stat-label">Overheal</div>
          <div class="role-stat-value ${data.healing_stats.avg_overheal_pct < 20 ? 'positive' : ''}">${data.healing_stats.avg_overheal_pct}%</div>
        </div>
      </div>
      <div class="role-card-footer">
        ${data.healing_stats.encounters} encounters
      </div>
    `;
    grid.appendChild(healCard);

    pane.appendChild(grid);

    // Role distribution pie chart
    const total = data.role_distribution.damage_time + 
                  data.role_distribution.tanking_time + 
                  data.role_distribution.healing_time;
    
    if (total > 0) {
      const chartCard = document.createElement('div');
      chartCard.className = 'role-distribution-card';
      chartCard.innerHTML = '<h3>⏱️ Time Distribution by Role</h3>';
      
      const canvas = document.createElement('canvas');
      canvas.width = 400;
      canvas.height = 400;
      chartCard.appendChild(canvas);

      const ctx = canvas.getContext('2d');
      const centerX = canvas.width / 2;
      const centerY = canvas.height / 2;
      const radius = 120;

      const roles = [
        { label: 'Damage', value: data.role_distribution.damage_time, color: '#ef4444' },
        { label: 'Tanking', value: data.role_distribution.tanking_time, color: '#3b82f6' },
        { label: 'Healing', value: data.role_distribution.healing_time, color: '#10b981' },
      ].filter(d => d.value > 0);

      let currentAngle = -Math.PI / 2;

      roles.forEach(role => {
        const sliceAngle = (role.value / total) * 2 * Math.PI;
        
        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, currentAngle, currentAngle + sliceAngle);
        ctx.closePath();
        ctx.fillStyle = role.color;
        ctx.fill();
        
        currentAngle += sliceAngle;
      });

      // Legend
      const legend = document.createElement('div');
      legend.className = 'pie-legend';
      
      roles.forEach(role => {
        const percent = ((role.value / total) * 100).toFixed(1);
        const item = document.createElement('div');
        item.className = 'legend-item';
        item.innerHTML = `
          <span class="legend-color" style="background: ${role.color}"></span>
          <span class="legend-label">${role.label}: ${percent}%</span>
          <span class="legend-value">${formatDuration(role.value)}</span>
        `;
        legend.appendChild(item);
      });
      
      chartCard.appendChild(legend);
      pane.appendChild(chartCard);
    }

    // Quick insights
    const insights = document.createElement('div');
    insights.className = 'role-insights';
    insights.innerHTML = '<h3>💡 Quick Insights</h3>';
    
    const insightList = document.createElement('ul');
    insightList.className = 'insight-list';
    
    // Add insights based on data
    if (data.damage_stats.encounters > 0) {
      insightList.innerHTML += `<li>You've completed <strong>${data.damage_stats.encounters}</strong> damage encounters with an average of <strong>${formatNumber(data.damage_stats.avg_dps)} DPS</strong></li>`;
    }
    
    if (data.tanking_stats.encounters > 0) {
      const survivalClass = data.tanking_stats.survival_rate >= 95 ? 'positive' : data.tanking_stats.survival_rate < 85 ? 'negative' : '';
      insightList.innerHTML += `<li>Your tank survival rate is <strong class="${survivalClass}">${data.tanking_stats.survival_rate}%</strong> across ${data.tanking_stats.encounters} encounters</li>`;
    }
    
    if (data.healing_stats.encounters > 0) {
      const overhealClass = data.healing_stats.avg_overheal_pct < 20 ? 'positive' : '';
      insightList.innerHTML += `<li>Average healing efficiency: <strong class="${overhealClass}">${100 - data.healing_stats.avg_overheal_pct}%</strong> (${data.healing_stats.avg_overheal_pct}% overheal)</li>`;
    }
    
    if (data.primary_role) {
      const roleNames = { damage: 'DPS', tanking: 'Tank', healing: 'Healer' };
      insightList.innerHTML += `<li>Your primary role is <strong>${roleNames[data.primary_role]}</strong> based on time played</li>`;
    }
    
    insights.appendChild(insightList);
    pane.appendChild(insights);
  }

  // ===== MAIN TAB SWITCHING =====
  
  function switchRoleTab(role) {
    log('Switching to role:', role);

    // Update active tab
    qa('.role-main-tab').forEach(tab => {
      tab.classList.toggle('active', tab.dataset.role === role);
    });

    // Update active pane
    qa('.role-pane').forEach(pane => {
      pane.classList.remove('active');
    });
    
    const targetPane = q(`#role-pane-${role}`);
    if (targetPane) {
      targetPane.classList.add('active');
    }

    // Trigger sub-module loading if needed
    if (role === 'damage' && !loadedModules.damage) {
      loadedModules.damage = true;
      log('Triggering combat module load');
      document.dispatchEvent(new CustomEvent('whodat:section-loaded', {
        detail: { section: 'combat' }
      }));
    } else if (role === 'tanking' && !loadedModules.tanking) {
      loadedModules.tanking = true;
      log('Triggering tanking module load');
      document.dispatchEvent(new CustomEvent('whodat:section-loaded', {
        detail: { section: 'tanking' }
      }));
    } else if (role === 'healing' && !loadedModules.healing) {
      loadedModules.healing = true;
      log('Triggering healing module load');
      document.dispatchEvent(new CustomEvent('whodat:section-loaded', {
        detail: { section: 'healing' }
      }));
    }
  }

  // ===== DATA LOADING =====
  
  async function loadRoleOverview() {
    const root = q('#tab-role');
    if (!root) {
      log('ERROR: #tab-role not found in DOM');
      return;
    }

    const cid = root.dataset?.characterId;
    log('Loading role overview for character:', cid);

    if (!cid) {
      const pane = q('#role-pane-overview');
      if (pane) pane.innerHTML = '<p class="muted">No character selected</p>';
      return;
    }

    try {
      const url = `/sections/role-data.php?character_id=${encodeURIComponent(cid)}`;
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
      
      renderRoleOverview(data);
    } catch (err) {
      log('Failed to load role overview:', err);
      const pane = q('#role-pane-overview');
      if (pane) {
        pane.innerHTML = `<p style="color:#d32f2f;">Failed to load role overview: ${err.message}</p>`;
      }
    }
  }

  // ===== INITIALIZATION =====
  
  function initRolePage() {
    log('Initializing role page');

    // Set up tab click handlers
    qa('.role-main-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        const role = tab.dataset.role;
        switchRoleTab(role);
      });
    });

    // Load overview data
    loadRoleOverview();
  }

  // ===== EVENT LISTENERS =====
  
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'role') {
      initRolePage();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || '';
    if (currentSection === 'role') {
      // Reset loaded modules
      loadedModules.damage = false;
      loadedModules.tanking = false;
      loadedModules.healing = false;
      
      // Reload overview
      loadRoleOverview();
      
      // Switch back to overview
      switchRoleTab('overview');
    }
  });

  // Auto-load if already on page
  if (q('#tab-role')) {
    log('Found #tab-role on page load, initializing now...');
    initRolePage();
  } else {
    log('No #tab-role found on initial load, waiting for event...');
  }

  log('Role Performance module loaded');
})();