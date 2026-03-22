/**
 * Reputation Module
 * Displays faction reputation with visual journey and detailed faction views
 */

(function() {
    'use strict';
    
    console.log('[Reputation] Module loaded');
    
    let currentData = null;
    
    // Initialize when section loads
    document.addEventListener('whodat:section-loaded', function(e) {
        if (e.detail.section === 'reputation') {
            console.log('[Reputation] Section loaded, initializing...');
            init();
        }
    });
    
    // Also init if already on page
    if (document.getElementById('tab-reputation')) {
        init();
    }
    
    function init() {
        const section = document.getElementById('tab-reputation');
        if (!section) return;
        
        const characterId = section.dataset.characterId;
        if (!characterId) {
            console.error('[Reputation] No character ID found');
            return;
        }
        
        // Set up tab switching
        setupTabs();
        
        // Load data
        loadReputationData(characterId);
    }
    
    function setupTabs() {
        const tabButtons = document.querySelectorAll('.reputation-tab');
        const panes = document.querySelectorAll('.reputation-pane');
        
        tabButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetTab = button.dataset.tab;
                
                // Update active states
                tabButtons.forEach(b => b.classList.remove('active'));
                panes.forEach(p => p.classList.remove('active'));
                
                button.classList.add('active');
                document.getElementById(`reputation-${targetTab}`).classList.add('active');
            });
        });
    }
    
    async function loadReputationData(characterId) {
        try {
            const response = await fetch(`/sections/reputation-data.php?character_id=${characterId}`);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.error || 'Failed to load reputation data');
            }
            
            currentData = data;
            console.log('[Reputation] Data loaded:', data);
            
            renderJourneyTab(data);
            renderFactionsTab(data);
            
        } catch (error) {
            console.error('[Reputation] Error loading data:', error);
            showError('journey', error.message);
            showError('factions', error.message);
        }
    }
    
    function renderJourneyTab(data) {
        const container = document.getElementById('reputation-journey');
        const summary = data.summary;
        const factions = data.factions;
        
        container.innerHTML = `
            <div class="reputation-journey-container">
                <!-- Header Stats -->
                <div class="journey-header">
                    <div class="journey-title">
                        <h2>🌟 Your Reputation Journey</h2>
                        <p>Standing with ${summary.total_factions} factions across Azeroth</p>
                    </div>
                </div>
                
                <!-- Key Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card exalted">
                        <div class="stat-icon">👑</div>
                        <div class="stat-content">
                            <div class="stat-value">${summary.exalted}</div>
                            <div class="stat-label">Exalted</div>
                            <div class="stat-sublabel">${summary.exalted_percentage}% of all factions</div>
                        </div>
                    </div>
                    
                    <div class="stat-card revered">
                        <div class="stat-icon">💎</div>
                        <div class="stat-content">
                            <div class="stat-value">${summary.revered}</div>
                            <div class="stat-label">Revered</div>
                            <div class="stat-sublabel">Nearly there!</div>
                        </div>
                    </div>
                    
                    <div class="stat-card honored">
                        <div class="stat-icon">⭐</div>
                        <div class="stat-content">
                            <div class="stat-value">${summary.honored}</div>
                            <div class="stat-label">Honored</div>
                            <div class="stat-sublabel">Well respected</div>
                        </div>
                    </div>
                    
                    <div class="stat-card friendly">
                        <div class="stat-icon">🤝</div>
                        <div class="stat-content">
                            <div class="stat-value">${summary.friendly}</div>
                            <div class="stat-label">Friendly</div>
                            <div class="stat-sublabel">Building trust</div>
                        </div>
                    </div>
                </div>
                
                <!-- Standing Distribution Chart -->
                <div class="distribution-section">
                    <h3>📊 Standing Distribution</h3>
                    <div class="distribution-container">
                        <div class="distribution-chart">
                            ${renderDistributionChart(summary.standing_distribution, data.standing_colors)}
                        </div>
                        <div class="distribution-legend">
                            ${renderDistributionLegend(summary.standing_distribution, data.standing_names, data.standing_colors)}
                        </div>
                    </div>
                </div>
                
                <!-- Top Factions Progress -->
                <div class="top-factions-section">
                    <h3>🏆 Closest to Exalted</h3>
                    ${renderTopFactions(factions)}
                </div>
            </div>
        `;
    }
    
    function renderDistributionChart(distribution, colors) {
        const total = distribution.reduce((sum, val) => sum + val, 0);
        if (total === 0) return '<div class="no-data">No reputation data</div>';
        
let accumulated = 0;
        const segments = distribution.map((count, index) => {
            if (count === 0) return '';
            
            const percentage = (count / total) * 100;
            const startAngle = (accumulated / total) * 360;
            const endAngle = ((accumulated + count) / total) * 360;
            accumulated += count;
            
            // index is 0-7, but colors array is 1-8 indexed
            const standingId = index + 1;
            
            return `
                <div class="donut-segment" 
                     style="--start: ${startAngle}deg; --end: ${endAngle}deg; --color: ${colors[standingId]};"
                     title="${count} faction(s) at standing ${standingId}">
                </div>
            `;
        }).join('');
        
        return `
            <div class="donut-chart">
                <div class="donut-segments">${segments}</div>
                <div class="donut-center">
                    <div class="donut-total">${total}</div>
                    <div class="donut-label">Factions</div>
                </div>
            </div>
        `;
    }
    
function renderDistributionLegend(distribution, names, colors) {
        return distribution.map((count, index) => {
            if (count === 0) return '';
            
            // index is 0-7, but names and colors arrays are 1-8 indexed
            const standingId = index + 1;
            
            return `
                <div class="legend-item">
                    <div class="legend-color" style="background: ${colors[standingId]};"></div>
                    <div class="legend-text">
                        <span class="legend-name">${names[standingId]}</span>
                        <span class="legend-count">${count}</span>
                    </div>
                </div>
            `;
        }).filter(Boolean).join('');
    }
    
    function renderTopFactions(factions) {
// Get factions close to exalted (Revered with high progress or already Exalted)
        const nearExalted = factions
            .filter(f => (f.standing_id === 7 && f.progress > 50) || f.standing_id === 8)
            .slice(0, 8);
        
        if (nearExalted.length === 0) {
            return '<div class="no-data">No factions near Exalted yet - keep grinding!</div>';
        }
        
        return `
            <div class="top-factions-grid">
                ${nearExalted.map(faction => `
                    <div class="top-faction-card ${faction.is_exalted ? 'exalted' : ''}">
                        <div class="faction-header">
                            <div class="faction-name">${escapeHtml(faction.faction_name)}</div>
                            <div class="faction-standing" style="color: ${faction.standing_color};">
                                ${faction.is_exalted ? '👑' : '💎'} ${faction.standing_name}
                            </div>
                        </div>
                        <div class="faction-progress-bar">
                            <div class="progress-fill" style="width: ${faction.is_exalted ? 100 : faction.progress}%; background: ${faction.standing_color};"></div>
                        </div>
                        <div class="faction-progress-text">
                            ${faction.is_exalted ? 'Max Standing!' : `${faction.progress.toFixed(1)}% through ${faction.standing_name}`}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    function renderFactionsTab(data) {
        const container = document.getElementById('reputation-factions');
        const factions = data.factions;
        
        if (factions.length === 0) {
            container.innerHTML = '<div class="no-data">No faction reputation data available</div>';
            return;
        }
        
        container.innerHTML = `
            <div class="factions-container">
                <div class="factions-header">
                    <h3>All Factions (${factions.length})</h3>
                    <div class="factions-filters">
                        <button class="filter-btn active" data-filter="all">All</button>
                        <button class="filter-btn" data-filter="exalted">Exalted</button>
                        <button class="filter-btn" data-filter="progress">In Progress</button>
                    </div>
                </div>
                
                <div class="factions-list">
                    ${factions.map((faction, index, arr) => {
                        // Check if this is the first faction without recent changes
                        const isFirstStatic = faction.recent_change === 0 && 
                                            (index === 0 || arr[index - 1].recent_change !== 0);
                        return renderFactionCard(faction, data.standing_names, isFirstStatic);
                    }).join('')}
                </div>
            </div>
        `;
        
        setupFactionFilters(factions);
    }
    
function renderFactionCard(faction, standing_names, isFirstStatic = false) {
        const trendIcon = faction.trend === 'up' ? '📈' : faction.trend === 'down' ? '📉' : '➡️';
        const trendClass = faction.trend === 'up' ? 'trend-up' : faction.trend === 'down' ? 'trend-down' : 'trend-stable';
        const separatorClass = isFirstStatic ? 'first-static-faction' : '';
        
        const timeAgo = formatTimeAgo(faction.change_time_ago);
        const changeText = faction.recent_change !== 0 
            ? `${faction.recent_change > 0 ? '+' : ''}${faction.recent_change} rep ${timeAgo}`
            : 'No recent changes';
        
        return `
            <div class="faction-card ${separatorClass}" data-standing="${faction.standing_id}">
                <div class="faction-card-header">
                    <div class="faction-info">
                        <div class="faction-name">${escapeHtml(faction.faction_name)}</div>
                        <div class="faction-standing-badge" style="background: ${faction.standing_color};">
                            ${faction.standing_name}
                        </div>
                    </div>
                    <div class="faction-trend ${trendClass}">
                        ${trendIcon} ${changeText}
                    </div>
                </div>
                
                <div class="faction-progress">
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" style="width: ${faction.progress}%; background: ${faction.standing_color};"></div>
                    </div>
                    <div class="progress-stats">
                        <span>${faction.value} / ${faction.max}</span>
                        <span>${faction.is_exalted ? 'MAX' : faction.progress.toFixed(1) + '%'}</span>
                    </div>
                </div>
                
                ${faction.standing_change !== 0 ? `
                    <div class="standing-change-alert">
                        ${faction.standing_change > 0 ? '⬆️' : '⬇️'} 
                        Standing changed from ${standing_names[faction.standing_id - faction.standing_change]} ${timeAgo}
                    </div>
                ` : ''}
            </div>
        `;
    }
    function setupFactionFilters(factions) {
        const filterBtns = document.querySelectorAll('.filter-btn');
        const factionCards = document.querySelectorAll('.faction-card');
        
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const filter = btn.dataset.filter;
                
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                factionCards.forEach(card => {
                    const standing = parseInt(card.dataset.standing);
                    let show = true;
                    
if (filter === 'exalted') {
                        show = standing === 8;
                    } else if (filter === 'progress') {
                        show = standing >= 5 && standing < 8;
                    }
                    
                    card.style.display = show ? 'block' : 'none';
                });
            });
        });
    }
    
    function formatTimeAgo(seconds) {
        if (!seconds || seconds === 0) return 'recently';
        
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        const weeks = Math.floor(days / 7);
        
        if (weeks > 0) return `${weeks} week${weeks > 1 ? 's' : ''} ago`;
        if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
        if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        return 'just now';
    }
    
    function showError(paneId, message) {
        const pane = document.getElementById(`reputation-${paneId}`);
        if (!pane) return;
        
        pane.innerHTML = `
            <div class="error-message" style="text-align: center; padding: 60px 20px;">
                <div style="font-size: 3rem; margin-bottom: 20px;">⚠️</div>
                <div style="font-size: 1.3rem; margin-bottom: 12px; color: #d32f2f;">Failed to Load Reputation Data</div>
                <div style="font-size: 0.95rem; color: #666;">${escapeHtml(message)}</div>
            </div>
        `;
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
})();