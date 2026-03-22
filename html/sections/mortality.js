// sections/mortality.js - Fresh mortality analysis system
(() => {
  'use strict';
  
  const q = (sel, ctx = document) => ctx.querySelector(sel);
  const qa = (sel, ctx = document) => ctx.querySelectorAll(sel);
  
  // ===== UTILITY FUNCTIONS =====
  
  function formatDate(timestamp) {
    const date = new Date(timestamp * 1000);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
  }

  function timeAgo(timestamp) {
    const now = Date.now() / 1000;
    const diff = now - timestamp;
    
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
    return new Date(timestamp * 1000).toLocaleDateString();
  }

  function getKillerIcon(type) {
    switch(type) {
      case 'player': return '⚔️';
      case 'npc': return '👹';
      case 'pet': return '🐺';
      case 'environmental': return '🌊';
      default: return '❓';
    }
  }

  function getConfidenceColor(confidence) {
    switch(confidence) {
      case 'fatality_primary': return '#10b981';
      case 'high': return '#059669';  
      case 'medium': return '#f59e0b';
      case 'low': return '#ef4444';
      default: return '#6b7280';
    }
  }

  // ===== UNKNOWN KILLER FILTER =====

  function isUnknownKiller(killerName) {
    if (!killerName) return true;
    const name = killerName.toString().trim().toLowerCase();
    return name === 'unknown' || name === '' || name === 'unknown killer';
  }

  function filterKnownDeaths(deaths) {
    if (!deaths) return [];
    return deaths.filter(d => !isUnknownKiller(d.killer_name || d.killer));
  }

  function filterKnownKillers(killers) {
    if (!killers) return [];
    return killers.filter(k => !isUnknownKiller(k.killer_name));
  }

  // ===== MODULE STATE =====
  // Store PVP deaths for use by The Grudge tab (populated after data load)
  let _pvpDeaths = [];
  let _characterId = null;

  // ===== MAIN RENDERER =====
  
  function renderMortality(data, characterName) {
    const root = q('#tab-mortality');
    if (!root) return;

    // Cache pvp deaths and character id for The Grudge tab
    _pvpDeaths = data.pvp_deaths || [];
    _characterId = root.dataset?.characterId || null;
    
    root.innerHTML = `
      <div class="mortality-header">
        <h1>💀 Mortality Analysis</h1>
        <p class="subtitle">Death analysis for ${characterName}</p>
      </div>
      
      <div class="mortality-nav">
        <button class="nav-tab active" data-tab="overview">📊 Overview</button>
        <button class="nav-tab" data-tab="pve">⚔️ PVE (${data.pve_deaths.length})</button>
        <button class="nav-tab" data-tab="pvp">🗡️ PVP (${data.pvp_deaths.length})</button>
        <button class="nav-tab" data-tab="spirit-healer">👻 Spirit Healer</button>
        <button class="nav-tab" data-tab="grudge">💀 The Grudge</button>
      </div>
      
      <div class="mortality-content">
        ${renderOverviewTab(data)}
        ${renderPVETab(filterKnownDeaths(data.pve_deaths))}
        ${renderPVPTab(filterKnownDeaths(data.pvp_deaths), data.pvp_stats)}
        ${renderSpiritHealerTab()}
        ${renderGrudgeTab()}
      </div>
    `;
    
    setupTabs(root);
    setupSpiritHealerFlame(root);

    // Wire up clickable PVP death cards
    const filteredPvp = filterKnownDeaths(data.pvp_deaths);
    setupPVPDeathModal(root, filteredPvp);
  }

  // ===== OVERVIEW TAB =====
  
  function renderOverviewTab(data) {
    const overview = data.overview;
    
    return `
      <div class="tab-content active" id="overview-tab">
        <div class="stats-grid">
          <div class="stat-card primary">
            <div class="stat-icon">💀</div>
            <div class="stat-value">${overview.total_deaths}</div>
            <div class="stat-label">Total Deaths</div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon">⚔️</div>
            <div class="stat-value">${overview.pve_deaths}</div>
            <div class="stat-label">PVE Deaths</div>
          </div>
          
          <div class="stat-card ${overview.pvp_deaths > 0 ? 'danger' : ''}">
            <div class="stat-icon">🗡️</div>
            <div class="stat-value">${overview.pvp_deaths}</div>
            <div class="stat-label">PVP Deaths</div>
          </div>
          
          <div class="stat-card">
            <div class="stat-icon">🌊</div>
            <div class="stat-value">${overview.environmental_deaths}</div>
            <div class="stat-label">Environmental</div>
          </div>
        </div>
        
        ${renderDeathTypeChart(overview.deaths_by_type)}
        
        <div class="analysis-grid">
          ${renderDangerousZones(overview.dangerous_zones)}
          ${renderLethalKillers(filterKnownKillers(overview.lethal_killers))}
        </div>
        
        ${renderTimeline(data.timeline)}
      </div>
    `;
  }

  function renderDeathTypeChart(deathTypes) {
    if (!deathTypes || deathTypes.length === 0) {
      return '<div class="chart-card"><p class="no-data">No death data available</p></div>';
    }
    
    const total = deathTypes.reduce((sum, type) => sum + type.count, 0);
    
    const bars = deathTypes.map(type => {
      const percentage = (type.count / total * 100);
      const icon = getKillerIcon(type.type);
      
      return `
        <div class="chart-bar">
          <div class="bar-label">${icon} ${type.type}</div>
          <div class="bar-container">
            <div class="bar-fill" style="width: ${percentage}%"></div>
          </div>
          <div class="bar-value">${type.count}</div>
        </div>
      `;
    }).join('');
    
    return `
      <div class="chart-card">
        <h3>Death Type Breakdown</h3>
        <div class="horizontal-chart">${bars}</div>
      </div>
    `;
  }

  function renderDangerousZones(zones) {
    if (!zones || zones.length === 0) {
      return '<div class="list-card"><h3>🗺️ Dangerous Zones</h3><p class="no-data">No zone data</p></div>';
    }
    
    const zoneList = zones.slice(0, 5).map((zone, index) => `
      <div class="list-item ${index === 0 ? 'highlight' : ''}">
        <div class="item-rank">#${index + 1}</div>
        <div class="item-info">
          <div class="item-name">${zone.zone}</div>
          <div class="item-meta">${zone.death_count} deaths</div>
        </div>
      </div>
    `).join('');
    
    return `
      <div class="list-card">
        <h3>🗺️ Most Dangerous Zones</h3>
        <div class="list-container">${zoneList}</div>
      </div>
    `;
  }

  function renderLethalKillers(killers) {
    if (!killers || killers.length === 0) {
      return '<div class="list-card"><h3>☠️ Lethal Enemies</h3><p class="no-data">No killer data</p></div>';
    }
    
    const killerList = killers.slice(0, 5).map((killer, index) => `
      <div class="list-item ${index === 0 ? 'highlight' : ''}">
        <div class="item-rank">#${index + 1}</div>
        <div class="item-info">
          <div class="item-name">${getKillerIcon(killer.killer_type)} ${killer.killer_name}</div>
          <div class="item-meta">${killer.kill_count} kills</div>
        </div>
      </div>
    `).join('');
    
    return `
      <div class="list-card">
        <h3>☠️ Most Lethal Enemies</h3>
        <div class="list-container">${killerList}</div>
      </div>
    `;
  }

  function renderTimeline(timeline) {
    if (!timeline || timeline.length === 0) {
      return '<div class="timeline-card"><h3>📅 Recent Activity</h3><p class="no-data">No recent deaths</p></div>';
    }
    
    const maxDeaths = Math.max(...timeline.map(day => day.deaths));
    const timelineBars = timeline.slice(0, 14).map(day => {
      const height = maxDeaths > 0 ? (day.deaths / maxDeaths * 100) : 0;
      const date = new Date(day.date + 'T00:00:00');
      
      return `
        <div class="timeline-bar" title="${day.date}: ${day.deaths} deaths">
          <div class="bar-fill" style="height: ${height}%"></div>
          <div class="bar-label">${date.getMonth() + 1}/${date.getDate()}</div>
        </div>
      `;
    }).join('');
    
    return `
      <div class="timeline-card">
        <h3>📅 Recent Death Activity</h3>
        <div class="timeline-chart">${timelineBars}</div>
      </div>
    `;
  }

  // ===== PVE TAB =====
  
  function renderPVETab(pveDeaths) {
    if (!pveDeaths || pveDeaths.length === 0) {
      return `
        <div class="tab-content" id="pve-tab">
          <div class="empty-state">
            <div class="empty-icon">⚔️</div>
            <h3>No PVE Deaths</h3>
            <p>You haven't died to any NPCs or environmental hazards yet!</p>
          </div>
        </div>
      `;
    }
    
    const deathCards = pveDeaths.map(death => renderDeathCard(death)).join('');
    
    return `
      <div class="tab-content" id="pve-tab">
        <div class="deaths-header">
          <h2>⚔️ PVE Deaths (${pveDeaths.length})</h2>
          <p>Deaths to NPCs, creatures, and environmental hazards</p>
        </div>
        <div class="deaths-grid">${deathCards}</div>
      </div>
    `;
  }

  // ===== PVP TAB =====
  
  function renderPVPTab(pvpDeaths, pvpStats) {
    if (!pvpDeaths || pvpDeaths.length === 0) {
      return `
        <div class="tab-content" id="pvp-tab">
          <div class="empty-state">
            <div class="empty-icon">🗡️</div>
            <h3>No PVP Deaths</h3>
            <p>You haven't been killed by other players yet!</p>
          </div>
        </div>
      `;
    }
    
    const deathCards = pvpDeaths.map((death, idx) => renderPVPDeathCard(death, idx)).join('');
    
    return `
      <div class="tab-content" id="pvp-tab">
        <div class="deaths-header">
          <h2>🗡️ PVP Deaths (${pvpDeaths.length})</h2>
          <p>Deaths to other players in combat · <span style="opacity:0.6;font-size:0.85em;">Click any card for full details</span></p>
        </div>
        
        ${renderPVPStats(pvpStats)}
        
        <div class="deaths-grid" id="pvp-deaths-grid">${deathCards}</div>

        <!-- PVP Death Detail Modal -->
        <div class="pvp-detail-overlay" id="pvp-detail-modal" role="dialog" aria-modal="true">
          <div class="pvp-detail-modal">
            <button class="pvp-detail-close" id="pvp-detail-close" title="Close">✕</button>
            <div id="pvp-detail-content"></div>
          </div>
        </div>
      </div>
    `;
  }

  function renderPVPStats(stats) {
    if (!stats || (!stats.dangerous_zones?.length && !stats.frequent_killers?.length && !stats.lethal_spells?.length)) {
      return '';
    }
    
    return `
      <div class="pvp-stats-grid">
        ${renderPVPZones(stats.dangerous_zones)}
        ${renderPVPKillers(filterKnownKillers(stats.frequent_killers))}
        ${renderPVPSpells(stats.lethal_spells)}
      </div>
    `;
  }

  function renderPVPZones(zones) {
    if (!zones || zones.length === 0) return '';
    
    const zoneList = zones.slice(0, 3).map(zone => `
      <div class="pvp-stat-item">
        <div class="stat-name">${zone.zone}${zone.subzone ? ` (${zone.subzone})` : ''}</div>
        <div class="stat-value">${zone.death_count} deaths</div>
      </div>
    `).join('');
    
    return `
      <div class="pvp-stat-card">
        <h4>🏴‍☠️ Dangerous PVP Zones</h4>
        <div class="pvp-stat-list">${zoneList}</div>
      </div>
    `;
  }

  function renderPVPKillers(killers) {
    if (!killers || killers.length === 0) return '';
    
    const killerList = killers.slice(0, 3).map(killer => `
      <div class="pvp-stat-item">
        <div class="stat-name">🗡️ ${killer.killer_name}</div>
        <div class="stat-value">${killer.kill_count} kills</div>
      </div>
    `).join('');
    
    return `
      <div class="pvp-stat-card">
        <h4>😈 Your Nemeses</h4>
        <div class="pvp-stat-list">${killerList}</div>
      </div>
    `;
  }

  function renderPVPSpells(spells) {
    if (!spells || spells.length === 0) return '';
    
    const spellList = spells.slice(0, 3).map(spell => `
      <div class="pvp-stat-item">
        <div class="stat-name">💥 ${spell.killer_spell}</div>
        <div class="stat-value">${spell.kill_count} kills</div>
      </div>
    `).join('');
    
    return `
      <div class="pvp-stat-card">
        <h4>⚡ Lethal Spells</h4>
        <div class="pvp-stat-list">${spellList}</div>
      </div>
    `;
  }

  // ===== DEATH CARD =====
  
  function renderDeathCard(death) {
    const location = death.subzone ? `${death.zone} (${death.subzone})` : death.zone;
    const coordinates = death.x && death.y ? ` • ${(death.x * 100).toFixed(1)}, ${(death.y * 100).toFixed(1)}` : '';
    
    return `
      <div class="death-card">
        <div class="death-header">
          <div class="death-time">${timeAgo(death.timestamp)}</div>
          <div class="confidence-badge" style="background-color: ${getConfidenceColor(death.confidence)}">
            ${death.confidence}
          </div>
        </div>
        
        <div class="death-killer">
          <div class="killer-info">
            <span class="killer-icon">${getKillerIcon(death.killer_type)}</span>
            <span class="killer-name">${death.killer}</span>
          </div>
          ${death.spell ? `<div class="killer-spell">💥 ${death.spell}</div>` : ''}
        </div>
        
        <div class="death-details">
          ${death.damage ? `<div class="detail-item">⚔️ ${death.damage.toLocaleString()} damage</div>` : ''}
          ${death.combat_duration ? `<div class="detail-item">⏱️ ${death.combat_duration}s combat</div>` : ''}
          ${death.rez_time ? `<div class="detail-item">⏳ ${death.rez_time}s to resurrect</div>` : ''}
          ${death.attacker_count > 1 ? `<div class="detail-item">👥 ${death.attacker_count} attackers</div>` : ''}
        </div>
        
        <div class="death-location">
          📍 ${location}${coordinates} • Level ${death.level}
        </div>
      </div>
    `;
  }

  // ===== PVP DEATH CARD (clickable variant) =====

  function renderPVPDeathCard(death, idx) {
    const location  = death.subzone ? `${death.zone} (${death.subzone})` : (death.zone || 'Unknown Zone');
    const d         = new Date(death.timestamp * 1000);
    const dateStr   = d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
    const timeStr   = d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const killCount = _pvpDeaths.filter(d => d.killer === death.killer).length;
    const killLabel = killCount > 1 ? `${killCount}× kills` : `1× kill`;

    return `
      <div class="death-card pvp-death-card-clickable" data-pvp-idx="${idx}" title="Click for full details" style="cursor:pointer;">
        <div class="death-header">
          <div class="death-time">${timeAgo(death.timestamp)}</div>
          <div class="confidence-badge" style="background-color: ${getConfidenceColor(death.confidence)}">
            ${(death.confidence || 'unknown').toUpperCase()}
          </div>
        </div>

        <div class="death-killer">
          <div class="killer-info">
            <span class="killer-icon">⚔️</span>
            <span class="killer-name">${escapeHtml(death.killer)}</span>
            <span class="pvp-kill-count" title="Total times killed by this player">${killLabel}</span>
          </div>
          ${death.spell ? `<div class="killer-spell" style="margin-top:0.25rem;font-size:0.85rem;opacity:0.8;">💥 ${escapeHtml(death.spell)}</div>` : ''}
        </div>

        <div class="death-location" style="margin-top:0.6rem;">
          📍 ${escapeHtml(location)}
        </div>
        <div class="pvp-card-datetime">
          🕐 ${dateStr} · ${timeStr}
        </div>

        <div class="pvp-card-hint">Click for full record →</div>
      </div>
    `;
  }

  // ===== DOSSIER HELPERS =====

  function dossierStats(kills) {
    // Most common zone
    const zoneCounts = {};
    kills.forEach(d => {
      const z = d.subzone ? `${d.zone} — ${d.subzone}` : (d.zone || 'Unknown');
      zoneCounts[z] = (zoneCounts[z] || 0) + 1;
    });
    const topZone = Object.entries(zoneCounts).sort((a, b) => b[1] - a[1])[0];

    // Most used spell/method
    const spellCounts = {};
    kills.forEach(d => {
      if (d.spell) spellCounts[d.spell] = (spellCounts[d.spell] || 0) + 1;
    });
    const topSpell = Object.entries(spellCounts).sort((a, b) => b[1] - a[1])[0];

    // Avg damage (only entries with damage recorded)
    const dmgEntries = kills.filter(d => d.damage);
    const avgDamage  = dmgEntries.length
      ? Math.round(dmgEntries.reduce((s, d) => s + d.damage, 0) / dmgEntries.length)
      : null;

    // Avg fight duration
    const durEntries = kills.filter(d => d.combat_duration);
    const avgDur     = durEntries.length
      ? (durEntries.reduce((s, d) => s + parseFloat(d.combat_duration), 0) / durEntries.length).toFixed(1)
      : null;

    // First / last seen
    const timestamps = kills.map(d => d.timestamp).sort((a, b) => a - b);
    const firstSeen  = timestamps[0];
    const lastSeen   = timestamps[timestamps.length - 1];

    // Ganks (attacker_count > 1)
    const ganks = kills.filter(d => d.attacker_count > 1).length;

    return { topZone, topSpell, avgDamage, avgDur, firstSeen, lastSeen, ganks };
  }

  function renderDeathEventBlock(d, highlighted) {
    const location    = d.subzone ? `${d.zone} — ${d.subzone}` : (d.zone || 'Unknown Zone');
    const fullDate    = new Date(d.timestamp * 1000).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
    const coordinates = (d.x != null && d.y != null)
      ? `${(d.x * 100).toFixed(2)}, ${(d.y * 100).toFixed(2)}`
      : null;

    const rows = [
      ['💥 Killing blow',    d.spell ? escapeHtml(d.spell) : null],
      ['🩸 Damage dealt',    d.damage ? d.damage.toLocaleString() + ' dmg' : null],
      ['⏱️ Fight duration',  d.combat_duration ? d.combat_duration + 's' : null],
      ['⏳ Res timer',       d.rez_time ? d.rez_time + 's' : null],
      ['👥 Attackers',       d.attacker_count > 1 ? d.attacker_count + ' players' : null],
      ['📍 Location',        escapeHtml(location)],
      ['🗺️ Coordinates',     coordinates],
      ['🎯 Your level',      d.level ? 'Level ' + d.level : null],
      ['💔 Durability lost', d.durability_loss ? d.durability_loss + '%' : null],
      ['🔎 Method',          d.method && d.method !== 'unknown' ? escapeHtml(d.method) : null],
      ['📊 Confidence',      d.confidence && d.confidence !== 'unknown'
                               ? d.confidence.replace(/_/g, ' ') : null],
    ].filter(([, val]) => val != null);

    // Split rows into two columns
    const mid   = Math.ceil(rows.length / 2);
    const left  = rows.slice(0, mid);
    const right = rows.slice(mid);
    const maxRows = Math.max(left.length, right.length);

    let colRows = '';
    for (let i = 0; i < maxRows; i++) {
      const [ll, lv] = left[i]  || ['', ''];
      const [rl, rv] = right[i] || ['', ''];
      colRows += `
        <tr class="pvp-detail-row">
          <td class="pvp-detail-label">${ll}</td>
          <td class="pvp-detail-value">${lv}</td>
          <td class="pvp-detail-spacer"></td>
          <td class="pvp-detail-label">${rl}</td>
          <td class="pvp-detail-value">${rv}</td>
        </tr>
      `;
    }

    return `
      <div class="pvp-event-block ${highlighted ? 'pvp-event-highlighted' : ''}">
        <div class="pvp-event-dateline">
          ${highlighted ? '<span class="pvp-event-this-tag">this death</span>' : ''}
          <span class="pvp-event-date">🕐 ${fullDate}</span>
        </div>
        <table class="pvp-detail-table pvp-detail-table-2col">
          <tbody>${colRows}</tbody>
        </table>
      </div>
    `;
  }

  function renderPVPDeathDetailContent(death) {
    const killerName     = death.killer;
    const allKills       = _pvpDeaths
      .filter(d => d.killer === killerName)
      .sort((a, b) => b.timestamp - a.timestamp);
    const killCount      = allKills.length;
    const alreadyGrudged = _grudgeList.some(e => e.name === killerName);
    const stats          = dossierStats(allKills);

    const otherKills = allKills.filter(d => d !== death);

    // ---- ID card dossier stats ----
    const fmtDate = ts => new Date(ts * 1000).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });

    const dossierItems = [
      { icon: '💀', label: 'Confirmed Kills',  value: killCount.toString() },
      { icon: '📍', label: 'Most Encountered', value: stats.topZone   ? escapeHtml(stats.topZone[0])   : '—' },
      { icon: '💥', label: 'Signature Move',   value: stats.topSpell  ? escapeHtml(stats.topSpell[0])  : '—' },
      { icon: '🩸', label: 'Avg Damage',        value: stats.avgDamage ? stats.avgDamage.toLocaleString() : '—' },
      { icon: '⏱️', label: 'Avg Fight Length',  value: stats.avgDur    ? stats.avgDur + 's'              : '—' },
      { icon: '📅', label: 'First Encountered', value: fmtDate(stats.firstSeen) },
      { icon: '🗓️', label: 'Last Encountered',  value: fmtDate(stats.lastSeen)  },
      { icon: '🐺', label: 'Ganks',             value: stats.ganks > 0 ? `${stats.ganks} of ${killCount}` : 'None recorded' },
    ];

    const dossierGrid = dossierItems.map(item => `
      <div class="dos-stat">
        <div class="dos-stat-icon">${item.icon}</div>
        <div class="dos-stat-body">
          <div class="dos-stat-label">${item.label}</div>
          <div class="dos-stat-value">${item.value}</div>
        </div>
      </div>
    `).join('');

    const highlightedBlock = renderDeathEventBlock(death, true);
    const otherBlocks = otherKills.length > 0
      ? `<div class="pvp-other-kills-section">
           <h4 class="pvp-other-kills-heading">Previous Encounters</h4>
           ${otherKills.map(d => renderDeathEventBlock(d, false)).join('')}
         </div>`
      : '';

    return `
      <!-- DOSSIER ID CARD -->
      <div class="dos-idcard">
        <div class="dos-idcard-left">
          <div class="dos-avatar">
            <span class="dos-avatar-icon">⚔️</span>
          </div>
          <div class="dos-idcard-name-block">
            <div class="dos-subject-label">SUBJECT</div>
            <div class="dos-subject-name">${escapeHtml(killerName)}</div>
            <div class="dos-subject-type">Player · PVP Killer</div>
          </div>
          <button class="pvp-detail-grudge-btn ${alreadyGrudged ? 'pvp-detail-grudge-added' : ''}"
                  id="pvp-detail-grudge-btn"
                  data-killer="${escapeHtml(killerName)}"
                  ${alreadyGrudged ? 'disabled' : ''}>
            ${alreadyGrudged ? '💀 On The Grudge' : '⚔️ Send to The Grudge'}
          </button>
        </div>
        <div class="dos-idcard-right">
          <div class="dos-stats-grid">
            ${dossierGrid}
          </div>
        </div>
      </div>

      <!-- INCIDENT LOG -->
      <div class="dos-incidents-heading">
        <span class="dos-incidents-label">INCIDENT LOG</span>
        <span class="dos-incidents-count">${killCount} record${killCount !== 1 ? 's' : ''}</span>
      </div>

      <div class="pvp-events-list">
        ${highlightedBlock}
        ${otherBlocks}
      </div>
    `;
  }

  function setupPVPDeathModal(root, pvpDeaths) {
    const grid    = q('#pvp-deaths-grid', root);
    const overlay = q('#pvp-detail-modal', root);
    const closeBtn = q('#pvp-detail-close', root);
    const content = q('#pvp-detail-content', root);
    if (!grid || !overlay) return;

    function openModal(idx) {
      const death = pvpDeaths[idx];
      if (!death) return;
      content.innerHTML = renderPVPDeathDetailContent(death);
      overlay.classList.add('pvp-detail-visible');

      const grudgeBtn = q('#pvp-detail-grudge-btn', overlay);
      if (grudgeBtn && !grudgeBtn.disabled) {
        grudgeBtn.addEventListener('click', async () => {
          const killerName = grudgeBtn.dataset.killer;
          grudgeBtn.disabled = true;
          grudgeBtn.textContent = '⏳ Adding…';
          try {
            await addToGrudge(root, killerName);
            grudgeBtn.textContent = '💀 Added to The Grudge!';
            grudgeBtn.classList.add('pvp-detail-grudge-added');
          } catch {
            grudgeBtn.disabled = false;
            grudgeBtn.textContent = '⚔️ Send to The Grudge';
          }
        });
      }
    }

    function closeModal() {
      overlay.classList.remove('pvp-detail-visible');
    }

    grid.addEventListener('click', (e) => {
      const card = e.target.closest('.pvp-death-card-clickable');
      if (!card) return;
      openModal(parseInt(card.dataset.pvpIdx, 10));
    });

    closeBtn.addEventListener('click', closeModal);

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeModal();
    });

    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && overlay.classList.contains('pvp-detail-visible')) closeModal();
    });
  }

  // ===== SPIRIT HEALER TAB =====

  // State for the Spirit Healer search
  const SH = {
    debounceTimer: null,
    currentPage: 1,
    isLoading: false,
    lastQuery: null,
  };

  function renderSpiritHealerTab() {
    return `
      <div class="tab-content" id="spirit-healer-tab">
        <div class="sh-header">
          <div class="sh-ghost-icon">👻</div>
          <div class="sh-title-block">
            <h2 class="sh-title">Spirit Healer</h2>
            <p class="sh-subtitle">Complete death archive — every fall, every story</p>
          </div>
        </div>

        <div class="sh-search-panel">
          <div class="sh-search-row">
            <div class="sh-search-field sh-search-main">
              <span class="sh-search-icon">🔍</span>
              <input
                type="text"
                id="sh-query"
                class="sh-input"
                placeholder="Search killer, zone, spell, method…"
                autocomplete="off"
              />
              <button class="sh-clear-btn" id="sh-clear" title="Clear search">✕</button>
            </div>
          </div>

          <div class="sh-filter-row">
            <div class="sh-date-group">
              <label class="sh-label">From</label>
              <input type="date" id="sh-date-from" class="sh-date-input" />
            </div>
            <div class="sh-date-sep">→</div>
            <div class="sh-date-group">
              <label class="sh-label">To</label>
              <input type="date" id="sh-date-to" class="sh-date-input" />
            </div>
            <button class="sh-search-btn" id="sh-submit">Search</button>
            <button class="sh-reset-btn" id="sh-reset">Reset</button>
          </div>
        </div>

        <div class="sh-status-bar" id="sh-status">
          <span class="sh-status-text">Enter a search term or date range to begin, or load all records.</span>
          <button class="sh-load-all-btn" id="sh-load-all">Load All Deaths</button>
        </div>

        <div id="sh-results" class="sh-results-container"></div>

        <div id="sh-pagination" class="sh-pagination"></div>
      </div>
    `;
  }

  function setupSpiritHealer(root) {
    const queryInput  = q('#sh-query', root);
    const dateFrom    = q('#sh-date-from', root);
    const dateTo      = q('#sh-date-to', root);
    const submitBtn   = q('#sh-submit', root);
    const resetBtn    = q('#sh-reset', root);
    const clearBtn    = q('#sh-clear', root);
    const loadAllBtn  = q('#sh-load-all', root);
    if (!queryInput) return;

    const characterId = root.dataset?.characterId;

    function triggerSearch(page = 1) {
      SH.currentPage = page;
      doSpiritHealerSearch(characterId, root);
    }

    // Debounced live search on typing
    queryInput.addEventListener('input', () => {
      clearTimeout(SH.debounceTimer);
      SH.debounceTimer = setTimeout(() => triggerSearch(1), 450);
      toggleClearBtn(clearBtn, queryInput);
    });

    dateFrom.addEventListener('change', () => triggerSearch(1));
    dateTo.addEventListener('change', () => triggerSearch(1));
    submitBtn.addEventListener('click', () => triggerSearch(1));
    loadAllBtn.addEventListener('click', () => {
      queryInput.value = '';
      dateFrom.value = '';
      dateTo.value = '';
      triggerSearch(1);
    });

    clearBtn.addEventListener('click', () => {
      queryInput.value = '';
      toggleClearBtn(clearBtn, queryInput);
      triggerSearch(1);
    });

    resetBtn.addEventListener('click', () => {
      queryInput.value = '';
      dateFrom.value = '';
      dateTo.value = '';
      toggleClearBtn(clearBtn, queryInput);
      q('#sh-results', root).innerHTML = '';
      q('#sh-pagination', root).innerHTML = '';
      q('#sh-status', root).innerHTML = `
        <span class="sh-status-text">Enter a search term or date range to begin, or load all records.</span>
        <button class="sh-load-all-btn" id="sh-load-all">Load All Deaths</button>
      `;
      // Re-bind load all after reset
      const newLoadAll = q('#sh-load-all', root);
      if (newLoadAll) newLoadAll.addEventListener('click', () => triggerSearch(1));
    });

    queryInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        clearTimeout(SH.debounceTimer);
        triggerSearch(1);
      }
    });
  }

  function toggleClearBtn(btn, input) {
    if (!btn) return;
    btn.style.opacity = input.value ? '1' : '0';
    btn.style.pointerEvents = input.value ? 'auto' : 'none';
  }

  async function doSpiritHealerSearch(characterId, root) {
    if (SH.isLoading) return;
    SH.isLoading = true;

    const queryInput = q('#sh-query', root);
    const dateFrom   = q('#sh-date-from', root);
    const dateTo     = q('#sh-date-to', root);
    const resultsEl  = q('#sh-results', root);
    const statusEl   = q('#sh-status', root);
    const paginationEl = q('#sh-pagination', root);

    const params = new URLSearchParams({
      character_id: characterId,
      search:       queryInput?.value?.trim() || '',
      date_from:    dateFrom?.value || '',
      date_to:      dateTo?.value || '',
      page:         SH.currentPage,
    });

    // Show loading skeleton
    resultsEl.innerHTML = renderSHSkeleton();
    statusEl.innerHTML  = `<span class="sh-status-text sh-loading-text">⏳ Searching the records…</span>`;
    paginationEl.innerHTML = '';

    try {
      const response = await fetch(`/sections/mortality-spirit-healer-data.php?${params}`, {
        credentials: 'include'
      });
      if (!response.ok) throw new Error(`HTTP ${response.status}`);
      const data = await response.json();

      renderSHResults(data, root);
    } catch (err) {
      resultsEl.innerHTML = `<div class="sh-error">Failed to load records: ${err.message}</div>`;
      statusEl.innerHTML = '';
    } finally {
      SH.isLoading = false;
    }
  }

  function renderSHResults(data, root) {
    const resultsEl    = q('#sh-results', root);
    const statusEl     = q('#sh-status', root);
    const paginationEl = q('#sh-pagination', root);

    // Status bar
    const searchLabel = data.search
      ? `for "<strong>${escapeHtml(data.search)}</strong>"`
      : '';
    const dateLabel = (data.date_from || data.date_to)
      ? ` between <strong>${data.date_from || '∞'}</strong> → <strong>${data.date_to || 'now'}</strong>`
      : '';

    statusEl.innerHTML = `
      <span class="sh-status-text">
        Found <strong>${data.total.toLocaleString()}</strong> death${data.total !== 1 ? 's' : ''}
        ${searchLabel}${dateLabel}
        — page <strong>${data.page}</strong> of <strong>${data.total_pages || 1}</strong>
      </span>
    `;

    if (!data.deaths || data.deaths.length === 0) {
      resultsEl.innerHTML = `
        <div class="sh-empty">
          <div class="sh-empty-icon">🕯️</div>
          <p>No deaths found matching your query.</p>
          <p class="sh-empty-sub">Try broadening your search or changing the date range.</p>
        </div>
      `;
      paginationEl.innerHTML = '';
      return;
    }

    // Result cards
    resultsEl.innerHTML = data.deaths.map(d => renderSHDeathCard(d)).join('');

    // Pagination
    if (data.total_pages > 1) {
      paginationEl.innerHTML = renderSHPagination(data.page, data.total_pages, root);
    } else {
      paginationEl.innerHTML = '';
    }
  }

  function renderSHDeathCard(death) {
    const killerName  = death.killer || 'Unknown';
    const isUnknown   = isUnknownKiller(killerName);
    const killerIcon  = getKillerIcon(death.killer_type);
    const typeLabel   = (death.killer_type || 'unknown').replace(/_/g, ' ');

    const zone = death.zone || 'Unknown Zone';
    const location = death.subzone ? `${zone} — ${death.subzone}` : zone;
    const coords = (death.x && death.y)
      ? `(${(death.x * 100).toFixed(1)}, ${(death.y * 100).toFixed(1)})`
      : null;

    const date     = new Date(death.timestamp * 1000);
    const dateStr  = date.toLocaleDateString([], { year:'numeric', month:'short', day:'numeric' });
    const timeStr  = date.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
    const ago      = timeAgo(death.timestamp);

    const confColor = getConfidenceColor(death.confidence);
    const confLabel = (death.confidence || 'unknown').replace(/_/g, ' ');

    // Build data rows — only show fields that have real values
    const rows = [];

    if (death.spell)            rows.push(['💥 Killing Blow', death.spell]);
    if (death.method)           rows.push(['⚙️ Method', death.method]);
    if (death.damage)           rows.push(['🗡️ Damage Dealt', death.damage.toLocaleString()]);
    if (death.combat_duration)  rows.push(['⏱️ Fight Duration', `${death.combat_duration}s`]);
    if (death.attacker_count > 1) rows.push(['👥 Attackers', death.attacker_count]);
    if (death.rez_time)         rows.push(['⏳ Resurrect Time', `${death.rez_time}s`]);
    if (death.durability_loss)  rows.push(['🔧 Durability Loss', `${death.durability_loss}%`]);
                                rows.push(['🎖️ Level at Death', death.level]);

    const rowsHTML = rows.map(([label, value]) => `
      <div class="sh-data-row">
        <span class="sh-data-label">${label}</span>
        <span class="sh-data-value">${escapeHtml(String(value))}</span>
      </div>
    `).join('');

    return `
      <div class="sh-death-card ${isUnknown ? 'sh-unknown' : ''}" data-killer-type="${escapeHtml(death.killer_type || 'unknown')}">
        <div class="sh-card-left">
          <div class="sh-killer-type-badge">${killerIcon}</div>
          <div class="sh-type-label">${typeLabel}</div>
        </div>

        <div class="sh-card-body">
          <div class="sh-card-top">
            <div class="sh-killer-block">
              <span class="sh-killer-name">${escapeHtml(killerName)}</span>
              ${death.spell ? `<span class="sh-killer-spell">via ${escapeHtml(death.spell)}</span>` : ''}
            </div>
            <div class="sh-card-meta">
              <span class="sh-confidence-pill" style="border-color:${confColor}; color:${confColor}">${confLabel}</span>
              <span class="sh-timestamp" title="${dateStr} ${timeStr}">${ago} · ${dateStr}</span>
            </div>
          </div>

          <div class="sh-location-bar">
            <span class="sh-loc-icon">📍</span>
            <span class="sh-loc-text">${escapeHtml(location)}</span>
            ${coords ? `<span class="sh-coords">${coords}</span>` : ''}
          </div>

          <div class="sh-data-grid">
            ${rowsHTML}
          </div>
        </div>
      </div>
    `;
  }

  function renderSHPagination(currentPage, totalPages, root) {
    const MAX_VISIBLE = 7;
    const pages = [];

    // Build page list with ellipsis
    if (totalPages <= MAX_VISIBLE) {
      for (let i = 1; i <= totalPages; i++) pages.push(i);
    } else {
      pages.push(1);
      if (currentPage > 3) pages.push('…');
      for (let i = Math.max(2, currentPage - 1); i <= Math.min(totalPages - 1, currentPage + 1); i++) {
        pages.push(i);
      }
      if (currentPage < totalPages - 2) pages.push('…');
      pages.push(totalPages);
    }

    const buttons = pages.map(p => {
      if (p === '…') return `<span class="sh-page-ellipsis">…</span>`;
      const active = p === currentPage ? 'sh-page-active' : '';
      return `<button class="sh-page-btn ${active}" data-page="${p}">${p}</button>`;
    }).join('');

    const prevDisabled = currentPage <= 1 ? 'disabled' : '';
    const nextDisabled = currentPage >= totalPages ? 'disabled' : '';

    // Store root reference to re-run search on click — use event delegation
    const html = `
      <div class="sh-pagination-inner" id="sh-pagination-inner">
        <button class="sh-page-btn sh-page-nav" data-page="${currentPage - 1}" ${prevDisabled}>← Prev</button>
        ${buttons}
        <button class="sh-page-btn sh-page-nav" data-page="${currentPage + 1}" ${nextDisabled}>Next →</button>
      </div>
    `;

    // Bind events after rendering via a small timeout
    setTimeout(() => {
      const inner = q('#sh-pagination-inner');
      if (!inner) return;
      inner.addEventListener('click', (e) => {
        const btn = e.target.closest('.sh-page-btn:not([disabled])');
        if (!btn || !btn.dataset.page) return;
        SH.currentPage = parseInt(btn.dataset.page);
        doSpiritHealerSearch(root.dataset?.characterId, root);
        // Scroll to top of results
        q('#sh-results')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }, 0);

    return html;
  }

  function renderSHSkeleton() {
    return Array.from({ length: 5 }).map(() => `
      <div class="sh-death-card sh-skeleton">
        <div class="sh-card-left">
          <div class="sh-skel-circle"></div>
        </div>
        <div class="sh-card-body">
          <div class="sh-skel-line sh-skel-wide"></div>
          <div class="sh-skel-line sh-skel-med"></div>
          <div class="sh-skel-line sh-skel-narrow"></div>
        </div>
      </div>
    `).join('');
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // ===== THE GRUDGE TAB =====

  // In-memory cache of the grudge list (array of {name, addedAt})
  // populated on first load and kept in sync after mutations.
  let _grudgeList = [];
  let _grudgeLoaded = false;

  function grudgeApiUrl(action) {
    return `/sections/mortality-grudge-api.php?action=${action}&character_id=${_characterId}`;
  }

  async function fetchGrudgeList() {
    if (!_characterId) return [];
    try {
      const res = await fetch(grudgeApiUrl('get'), { credentials: 'include' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      return data.grudge_list || [];
    } catch (err) {
      console.error('Grudge fetch error:', err);
      return [];
    }
  }

  async function apiAddToGrudge(playerName) {
    const res = await fetch('/sections/mortality-grudge-api.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'add', character_id: _characterId, player_name: playerName }),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  async function apiRemoveFromGrudge(playerName) {
    const res = await fetch('/sections/mortality-grudge-api.php', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action: 'remove', character_id: _characterId, player_name: playerName }),
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  function renderGrudgeTab() {
    return `
      <div class="tab-content" id="grudge-tab">

        <!-- Header -->
        <div class="gr-header">
          <div class="gr-skull-icon">💀</div>
          <div class="gr-title-block">
            <h2 class="gr-title">The Grudge</h2>
            <p class="gr-subtitle">Those who will answer for what they've done</p>
          </div>
        </div>

        <!-- Export panel -->
        <div class="gr-export-panel" id="gr-export-panel">
          <div class="gr-export-info">
            <span class="gr-export-icon">📦</span>
            <div class="gr-export-text">
              <strong>Export for The Grudge Addon</strong>
              <span>Downloads <code>TheGrudgeDB.lua</code> — place it in <code>Interface\\AddOns\\TheGrudge\\</code></span>
            </div>
          </div>
          <button class="gr-export-btn" id="gr-export-btn" title="Download TheGrudgeDB.lua">
            <span class="gr-export-btn-icon">⬇</span>
            <span class="gr-export-btn-label">Export .lua</span>
          </button>
        </div>

        <!-- Search -->
        <div class="gr-search-panel">
          <div class="gr-search-field">
            <span class="gr-search-icon">🔍</span>
            <input
              type="text"
              id="gr-query"
              class="gr-input"
              placeholder="Search a player who killed you…"
              autocomplete="off"
            />
            <button class="gr-clear-btn" id="gr-clear" title="Clear">✕</button>
          </div>
          <!-- Autocomplete dropdown -->
          <div class="gr-dropdown" id="gr-dropdown"></div>
        </div>

        <!-- Grudge list -->
        <div id="gr-list" class="gr-list"></div>

        <!-- Confirmation modal (hidden by default) -->
        <div class="gr-modal-overlay" id="gr-modal" role="dialog" aria-modal="true" aria-labelledby="gr-modal-title">
          <div class="gr-modal">
            <div class="gr-modal-skull">💀</div>
            <h3 class="gr-modal-title" id="gr-modal-title">Send <span id="gr-modal-name"></span> to The Grudge?</h3>
            <p class="gr-modal-sub">They will be added to your grudge list.</p>
            <div class="gr-modal-btns">
              <button class="gr-modal-cancel" id="gr-modal-cancel">Cancel</button>
              <button class="gr-modal-confirm" id="gr-modal-confirm">Yes, add them</button>
            </div>
          </div>
        </div>

      </div>
    `;
  }

  function setupGrudge(root) {
    // Show loading state while we fetch from the DB
    const listEl = q('#gr-list', root);
    if (listEl) {
      listEl.innerHTML = `<div class="gr-empty"><div class="gr-empty-icon">⏳</div><p class="gr-empty-text">Loading grudge list…</p></div>`;
    }

    fetchGrudgeList().then(list => {
      _grudgeList = list;
      _grudgeLoaded = true;
      renderGrudgeList(root);
    });

    bindGrudgeSearch(root);
    bindGrudgeModal(root);
    setupGrudgeExport(root);
  }

  // ---- Export button ----

  function setupGrudgeExport(root) {
    const btn = q('#gr-export-btn', root);
    if (!btn) return;

    btn.addEventListener('click', async () => {
      // Require a non-empty grudge list
      if (_grudgeList.length === 0) {
        showGrudgeError(root, 'Your grudge list is empty — nothing to export.');
        return;
      }

      // Visual: loading state
      btn.disabled = true;
      btn.querySelector('.gr-export-btn-icon').textContent = '⏳';
      btn.querySelector('.gr-export-btn-label').textContent = 'Exporting…';

      try {
        const url = `/sections/mortality-grudge-api.php?action=export&character_id=${_characterId}`;
        const res = await fetch(url, { credentials: 'include' });

        if (!res.ok) {
          const err = await res.json().catch(() => ({ error: `HTTP ${res.status}` }));
          throw new Error(err.error || `HTTP ${res.status}`);
        }

        // Trigger browser download
        const blob = await res.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'TheGrudgeDB.lua';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(a.href);

        // Success flash
        btn.querySelector('.gr-export-btn-icon').textContent = '✅';
        btn.querySelector('.gr-export-btn-label').textContent = 'Downloaded!';
        setTimeout(() => {
          btn.querySelector('.gr-export-btn-icon').textContent = '⬇';
          btn.querySelector('.gr-export-btn-label').textContent = 'Export .lua';
          btn.disabled = false;
        }, 2500);

      } catch (err) {
        console.error('Grudge export failed:', err);
        showGrudgeError(root, `Export failed: ${err.message}`);
        btn.querySelector('.gr-export-btn-icon').textContent = '⬇';
        btn.querySelector('.gr-export-btn-label').textContent = 'Export .lua';
        btn.disabled = false;
      }
    });
  }

  // ---- Search / Autocomplete ----

  function getKnownPvpKillers() {
    // Build a deduplicated list of {name, deaths[]} from pvp_deaths
    const map = new Map();
    for (const death of _pvpDeaths) {
      const name = death.killer;
      if (!name || isUnknownKiller(name)) continue;
      if (!map.has(name)) map.set(name, []);
      map.get(name).push(death);
    }
    return map;
  }

  function bindGrudgeSearch(root) {
    const input    = q('#gr-query', root);
    const dropdown = q('#gr-dropdown', root);
    const clearBtn = q('#gr-clear', root);
    if (!input) return;

    const killers = getKnownPvpKillers();

    function updateDropdown(term) {
      const t = term.trim().toLowerCase();
      if (!t) { dropdown.innerHTML = ''; dropdown.classList.remove('gr-dropdown-open'); return; }

      const matches = [...killers.entries()]
        .filter(([name]) => name.toLowerCase().includes(t))
        .sort((a, b) => b[1].length - a[1].length) // most deaths first
        .slice(0, 8);

      if (matches.length === 0) {
        dropdown.innerHTML = `<div class="gr-dropdown-empty">No PVP killers match "${escapeHtml(term)}"</div>`;
        dropdown.classList.add('gr-dropdown-open');
        return;
      }

      dropdown.innerHTML = matches.map(([name, deaths]) => `
        <div class="gr-dropdown-item" data-name="${escapeHtml(name)}">
          <span class="gr-dropdown-name">⚔️ ${escapeHtml(name)}</span>
          <span class="gr-dropdown-count">${deaths.length} kill${deaths.length !== 1 ? 's' : ''}</span>
        </div>
      `).join('');
      dropdown.classList.add('gr-dropdown-open');
    }

    input.addEventListener('input', () => {
      updateDropdown(input.value);
      clearBtn.style.opacity = input.value ? '1' : '0';
      clearBtn.style.pointerEvents = input.value ? 'auto' : 'none';
    });

    clearBtn.addEventListener('click', () => {
      input.value = '';
      dropdown.innerHTML = '';
      dropdown.classList.remove('gr-dropdown-open');
      clearBtn.style.opacity = '0';
      clearBtn.style.pointerEvents = 'none';
      input.focus();
    });

    // Click on a result → flicker red → show modal
    dropdown.addEventListener('click', (e) => {
      const item = e.target.closest('.gr-dropdown-item');
      if (!item) return;
      const name = item.dataset.name;
      if (!name) return;

      // Red flicker
      item.classList.add('gr-flicker');
      setTimeout(() => {
        showGrudgeModal(root, name);
        item.classList.remove('gr-flicker');
        dropdown.innerHTML = '';
        dropdown.classList.remove('gr-dropdown-open');
        input.value = '';
        clearBtn.style.opacity = '0';
        clearBtn.style.pointerEvents = 'none';
      }, 420);
    });

    // Close dropdown on outside click
    document.addEventListener('click', (e) => {
      if (!root.contains(e.target)) {
        dropdown.innerHTML = '';
        dropdown.classList.remove('gr-dropdown-open');
      }
    });

    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        dropdown.innerHTML = '';
        dropdown.classList.remove('gr-dropdown-open');
        input.blur();
      }
    });
  }

  // ---- Modal ----

  function bindGrudgeModal(root) {
    const overlay    = q('#gr-modal', root);
    const cancelBtn  = q('#gr-modal-cancel', root);
    const confirmBtn = q('#gr-modal-confirm', root);
    if (!overlay) return;

    cancelBtn.addEventListener('click', () => closeGrudgeModal(root));
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeGrudgeModal(root);
    });

    confirmBtn.addEventListener('click', async () => {
      const name = confirmBtn.dataset.pendingName;
      if (!name) return;
      closeGrudgeModal(root);
      await addToGrudge(root, name);
    });

    // Keyboard: Escape closes, Enter confirms
    document.addEventListener('keydown', (e) => {
      if (!overlay.classList.contains('gr-modal-visible')) return;
      if (e.key === 'Escape') closeGrudgeModal(root);
      if (e.key === 'Enter')  confirmBtn.click();
    });
  }

  function showGrudgeModal(root, playerName) {
    const overlay    = q('#gr-modal', root);
    const nameSpan   = q('#gr-modal-name', root);
    const confirmBtn = q('#gr-modal-confirm', root);
    if (!overlay) return;
    nameSpan.textContent = playerName;
    confirmBtn.dataset.pendingName = playerName;
    overlay.classList.add('gr-modal-visible');
    // Focus confirm after short delay
    setTimeout(() => q('#gr-modal-confirm', root)?.focus(), 50);
  }

  function closeGrudgeModal(root) {
    const overlay = q('#gr-modal', root);
    if (overlay) overlay.classList.remove('gr-modal-visible');
  }

  // ---- Grudge list management ----

  async function addToGrudge(root, playerName) {
    try {
      const result = await apiAddToGrudge(playerName);
      // Update local cache: remove existing, unshift to top
      _grudgeList = _grudgeList.filter(e => e.name !== playerName);
      _grudgeList.unshift({ name: playerName, addedAt: result.added_at || Math.floor(Date.now() / 1000) });
      renderGrudgeList(root);
    } catch (err) {
      console.error('Failed to add to grudge:', err);
      showGrudgeError(root, 'Failed to save. Please try again.');
    }
  }

  async function removeFromGrudge(root, playerName) {
    try {
      await apiRemoveFromGrudge(playerName);
      _grudgeList = _grudgeList.filter(e => e.name !== playerName);
      renderGrudgeList(root);
    } catch (err) {
      console.error('Failed to remove from grudge:', err);
      showGrudgeError(root, 'Failed to remove. Please try again.');
    }
  }

  function showGrudgeError(root, msg) {
    const listEl = q('#gr-list', root);
    if (!listEl) return;
    const err = document.createElement('div');
    err.className = 'sh-error';
    err.style.marginBottom = '1rem';
    err.textContent = msg;
    listEl.insertAdjacentElement('beforebegin', err);
    setTimeout(() => err.remove(), 4000);
  }

  function renderGrudgeList(root) {
    const container = q('#gr-list', root);
    if (!container) return;

    const list    = _grudgeList;
    const killers = getKnownPvpKillers();

    if (list.length === 0) {
      container.innerHTML = `
        <div class="gr-empty">
          <div class="gr-empty-icon">🕯️</div>
          <p class="gr-empty-text">Your grudge list is empty.</p>
          <p class="gr-empty-sub">Search for a player above to begin.</p>
        </div>
      `;
      return;
    }

    container.innerHTML = list.map(entry => {
      const deaths = (killers.get(entry.name) || [])
        .sort((a, b) => b.timestamp - a.timestamp);

      const addedDate = new Date(entry.addedAt * 1000).toLocaleDateString([], {
        year: 'numeric', month: 'short', day: 'numeric'
      });

      const incidentRows = deaths.length > 0
        ? deaths.map(d => {
            const zone     = d.subzone ? `${d.zone} — ${d.subzone}` : (d.zone || 'Unknown Zone');
            const dateStr  = new Date(d.timestamp * 1000).toLocaleDateString([], {
              year: 'numeric', month: 'short', day: 'numeric'
            });
            const timeStr  = new Date(d.timestamp * 1000).toLocaleTimeString([], {
              hour: '2-digit', minute: '2-digit'
            });
            const spell    = d.spell ? `<span class="gr-incident-spell">via ${escapeHtml(d.spell)}</span>` : '';
            return `
              <div class="gr-incident">
                <span class="gr-incident-date">📅 ${dateStr} · ${timeStr}</span>
                <span class="gr-incident-location">📍 ${escapeHtml(zone)}</span>
                ${spell}
              </div>
            `;
          }).join('')
        : `<div class="gr-incident gr-incident-none">No recorded PVP deaths from this player.</div>`;

      return `
        <div class="gr-entry" data-name="${escapeHtml(entry.name)}">
          <div class="gr-entry-header">
            <div class="gr-entry-left">
              <span class="gr-entry-skull">💀</span>
              <div class="gr-entry-info">
                <span class="gr-entry-name">${escapeHtml(entry.name)}</span>
                <span class="gr-entry-meta">
                  ${deaths.length} recorded kill${deaths.length !== 1 ? 's' : ''}
                  &nbsp;·&nbsp; Marked ${addedDate}
                </span>
              </div>
            </div>
            <button class="gr-remove-btn" data-name="${escapeHtml(entry.name)}" title="Remove from The Grudge">✕</button>
          </div>
          <div class="gr-incidents-list">
            ${incidentRows}
          </div>
        </div>
      `;
    }).join('');

    // Bind remove buttons
    container.querySelectorAll('.gr-remove-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        removeFromGrudge(root, btn.dataset.name);
      });
    });
  }

  // ===== GHOST FLAME BUTTON EFFECT =====

  function setupSpiritHealerFlame(container) {
    // The flame effect is pure CSS — just ensure overflow:visible on the nav
    // so the pseudo-elements that extend outside the button aren't clipped.
    const nav = container.querySelector('.mortality-nav');
    if (nav) nav.style.overflow = 'visible';
  }

  // ===== TAB SYSTEM =====
  
  function setupTabs(container) {
    const tabs = qa('.nav-tab', container);
    const contents = qa('.tab-content', container);
    let spiritHealerInitialized = false;
    let grudgeInitialized = false;

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const targetTab = tab.dataset.tab;
        
        // Update active tab
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        // Update active content
        contents.forEach(content => {
          content.classList.remove('active');
          if (content.id === `${targetTab}-tab`) {
            content.classList.add('active');
          }
        });

        // Lazy-init Spirit Healer on first visit
        if (targetTab === 'spirit-healer' && !spiritHealerInitialized) {
          spiritHealerInitialized = true;
          setupSpiritHealer(container);
        }

        // Lazy-init The Grudge on first visit
        if (targetTab === 'grudge' && !grudgeInitialized) {
          grudgeInitialized = true;
          setupGrudge(container);
        }
      });
    });
  }

  // ===== DATA LOADING =====
  
  async function loadMortalityData() {
    const root = q('#tab-mortality');
    if (!root) return;

    const characterId = root.dataset?.characterId;
    const characterName = root.dataset?.charName || 'Unknown';

    if (!characterId) {
      root.innerHTML = '<div class="error-message">No character selected</div>';
      return;
    }

    try {
      const response = await fetch(`/sections/mortality-data.php?character_id=${characterId}`, {
        credentials: 'include'
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      const data = await response.json();
      renderMortality(data, characterName);
      
    } catch (error) {
      console.error('Failed to load mortality data:', error);
      root.innerHTML = `
        <div class="error-message">
          <h3>Failed to Load Data</h3>
          <p>Error: ${error.message}</p>
        </div>
      `;
    }
  }

  // ===== EVENT LISTENERS =====
  
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'mortality') {
      loadMortalityData();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    if (window.location.hash === '#mortality' || window.history?.state?.section === 'mortality') {
      loadMortalityData();
    }
  });

  // Initial load
  if (q('#tab-mortality')) {
    loadMortalityData();
  }
  
})();