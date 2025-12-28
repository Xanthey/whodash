/* eslint-disable no-console */
/* WhoDASH Currencies — Complete Enhanced Version with ALL Analytics */
(() => {
  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[currencies]', ...a);

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

  function formatGoldPlain(copper) {
    const g = Math.floor(copper / 10000);
    const s = Math.floor((copper % 10000) / 100);
    const c = copper % 100;
    const parts = [];
    if (g > 0) parts.push(`${g.toLocaleString()}g`);
    if (s > 0 || (g > 0 && c > 0)) parts.push(`${s}s`);
    if (c > 0 || parts.length === 0) parts.push(`${c}c`);
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

  function formatTimeAgo(ts) {
    const seconds = Math.floor(Date.now() / 1000) - ts;
    if (seconds < 60) return 'Just now';
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)}h ago`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)}d ago`;
    return new Date(ts * 1000).toLocaleDateString();
  }

  // ===== EXISTING ECONOMY WIDGETS =====
  
  function renderFinancialHealth(data) {
    const card = document.createElement('div');
    card.className = 'finance-health-card';
    
    const current = data.current || {};
    
    card.innerHTML = `
      <h2 class="finance-title">💰 Financial Health</h2>
      <div class="finance-stats-grid">
        <div class="finance-stat-main">
          <div class="stat-label">Current Gold</div>
          <div class="stat-value-huge">${formatGold(current.gold || 0)}</div>
        </div>
        <div class="finance-stat">
          <div class="stat-label">24 Hour Change</div>
          <div class="stat-value ${current.change_24h >= 0 ? 'positive' : 'negative'}">
            ${current.change_24h >= 0 ? '+' : ''}${formatGold(Math.abs(current.change_24h || 0))}
          </div>
        </div>
        <div class="finance-stat">
          <div class="stat-label">7 Day Change</div>
          <div class="stat-value ${current.change_7d >= 0 ? 'positive' : 'negative'}">
            ${current.change_7d >= 0 ? '+' : ''}${formatGold(Math.abs(current.change_7d || 0))}
          </div>
        </div>
        <div class="finance-stat">
          <div class="stat-label">30 Day Change</div>
          <div class="stat-value ${current.change_30d >= 0 ? 'positive' : 'negative'}">
            ${current.change_30d >= 0 ? '+' : ''}${formatGold(Math.abs(current.change_30d || 0))}
          </div>
        </div>
      </div>
    `;
    
    return card;
  }

  function renderNetWorthChart(timeseries) {
    const card = document.createElement('div');
    card.className = 'networth-chart-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '📈 Net Worth (Last 30 Days)';
    card.appendChild(title);

    if (!timeseries || timeseries.length < 2) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'Not enough data for chart';
      card.appendChild(msg);
      return card;
    }

    const canvas = document.createElement('canvas');
    canvas.className = 'networth-canvas';
    canvas.width = 800;
    canvas.height = 200;
    card.appendChild(canvas);

    const ctx = canvas.getContext('2d');
    const padding = 40;
    const w = canvas.width - padding * 2;
    const h = canvas.height - padding * 2;

    const minVal = Math.min(...timeseries.map(d => d.value));
    const maxVal = Math.max(...timeseries.map(d => d.value));
    const range = maxVal - minVal || 1;

    ctx.strokeStyle = '#e6eefb';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 5; i++) {
      const y = padding + (i / 5) * h;
      ctx.beginPath();
      ctx.moveTo(padding, y);
      ctx.lineTo(padding + w, y);
      ctx.stroke();
    }

    ctx.strokeStyle = '#10b981';
    ctx.lineWidth = 3;
    ctx.beginPath();

    timeseries.forEach((d, i) => {
      const x = padding + (i / (timeseries.length - 1)) * w;
      const y = padding + h - ((d.value - minVal) / range) * h;
      if (i === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    });

    ctx.stroke();

    ctx.lineTo(padding + w, padding + h);
    ctx.lineTo(padding, padding + h);
    ctx.closePath();
    const gradient = ctx.createLinearGradient(0, padding, 0, padding + h);
    gradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
    gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');
    ctx.fillStyle = gradient;
    ctx.fill();

    ctx.fillStyle = '#6e7f9b';
    ctx.font = '12px system-ui';
    ctx.textAlign = 'right';
    for (let i = 0; i <= 5; i++) {
      const val = Math.round(minVal + (range * i / 5));
      const gold = Math.floor(val / 10000);
      const y = padding + h - (i / 5) * h;
      ctx.fillText(`${gold}g`, padding - 10, y + 4);
    }

    return card;
  }

  function renderGoldPerHour(gphData) {
    const card = document.createElement('div');
    card.className = 'gph-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '⚡ Gold Per Hour (Last 30 Days)';
    card.appendChild(title);

    if (!gphData || gphData.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No session data available';
      card.appendChild(msg);
      return card;
    }

    const table = document.createElement('table');
    table.className = 'gph-table';
    
    table.innerHTML = `
      <thead>
        <tr>
          <th>Date</th>
          <th>Time Played</th>
          <th>Gold Earned</th>
          <th>Gold/Hour</th>
        </tr>
      </thead>
    `;

    const tbody = document.createElement('tbody');
    
    const sorted = gphData
      .filter(d => d.gold_per_hour > 0)
      .sort((a, b) => b.gold_per_hour - a.gold_per_hour)
      .slice(0, 10);

    sorted.forEach(day => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${day.date}</td>
        <td>${formatDuration(day.total_time)}</td>
        <td class="${day.gold_earned >= 0 ? 'positive' : 'negative'}">
          ${day.gold_earned >= 0 ? '+' : ''}${formatGold(Math.abs(day.gold_earned))}
        </td>
        <td class="gph-value">${formatGold(day.gold_per_hour)}/hr</td>
      `;
      tbody.appendChild(row);
    });

    table.appendChild(tbody);
    card.appendChild(table);

    const avg = sorted.reduce((sum, d) => sum + d.gold_per_hour, 0) / sorted.length;
    const avgDiv = document.createElement('div');
    avgDiv.className = 'gph-average';
    avgDiv.innerHTML = `Average: <strong>${formatGold(Math.round(avg))}/hr</strong>`;
    card.appendChild(avgDiv);

    return card;
  }

  function renderMilestones(milestones) {
  const card = document.createElement('div');
  card.className = 'milestones-card';
  
  const title = document.createElement('h3');
  title.innerHTML = '🏆 Wealth Milestones';
  card.appendChild(title);

  if (!milestones || milestones.length === 0) {
    const msg = document.createElement('p');
    msg.className = 'muted';
    msg.textContent = 'No milestones reached yet';
    card.appendChild(msg);
    return card;
  }

  const timeline = document.createElement('div');
  timeline.className = 'milestone-timeline';

  // FIX: Reverse the order to show newest first
  const reversedMilestones = [...milestones].reverse();

  reversedMilestones.forEach(m => {
    const item = document.createElement('div');
    item.className = 'milestone-item';
    
    item.innerHTML = `
      <div class="milestone-icon">💰</div>
      <div class="milestone-content">
        <div class="milestone-title">${m.label} Reached</div>
        <div class="milestone-desc">${m.date}</div>
      </div>
    `;
    
    timeline.appendChild(item);
  });

  card.appendChild(timeline);

  const epicFlyingCost = 5000000;
  const currentGold = milestones.length > 0 
    ? milestones[milestones.length - 1].amount 
    : 0;

  if (currentGold < epicFlyingCost) {
    const remaining = epicFlyingCost - currentGold;
    const progress = (currentGold / epicFlyingCost * 100).toFixed(1);
    
    const progressDiv = document.createElement('div');
    progressDiv.className = 'milestone-progress';
    progressDiv.innerHTML = `
      <div class="progress-label">Progress to Epic Flying (500g)</div>
      <div class="progress-bar-container">
        <div class="progress-bar-fill" style="width: ${progress}%"></div>
      </div>
      <div class="progress-text">${formatGold(remaining)} remaining (${progress}%)</div>
    `;
    card.appendChild(progressDiv);
  }

  return card;
}

  function renderRecentLoot(loot) {
    const card = document.createElement('div');
    card.className = 'recent-loot-card';
    
    const title = document.createElement('h3');
    title.innerHTML = '✨ Recent Loot';
    card.appendChild(title);

    if (!loot || loot.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No loot data available';
      card.appendChild(msg);
      return card;
    }

    const list = document.createElement('div');
    list.className = 'loot-list';

    const qualityColors = {
      'ff9d9d9d': 'poor',
      'ffffffff': 'common',
      'ff1eff00': 'uncommon',
      'ff0070dd': 'rare',
      'ffa335ee': 'epic',
      'ffff8000': 'legendary',
    };

    const validLoot = loot
      .map(item => {
        let itemName = item.name;
        let quality = 'common';
        
        if (!itemName && item.link) {
          const match = item.link.match(/\[([^\]]+)\]/);
          if (match) {
            itemName = match[1];
          }
        }
        
        if (item.link) {
          const colorMatch = item.link.match(/\|c([a-f0-9]{8})/i);
          if (colorMatch) {
            const colorCode = colorMatch[1].toLowerCase();
            quality = qualityColors[colorCode] || 'common';
          }
        }
        
        return {
          ...item,
          displayName: itemName || 'Unknown Item',
          quality: quality
        };
      })
      .filter(item => item.displayName !== 'Unknown Item')
      .slice(0, 15);

    if (validLoot.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No named items found in loot history';
      card.appendChild(msg);
      return card;
    }

    validLoot.forEach(item => {
      const row = document.createElement('div');
      row.className = 'loot-item';
      
      const timeAgo = formatTimeAgo(item.ts);
      
      row.innerHTML = `
        <div class="loot-name quality-${item.quality}">${item.displayName}</div>
        <div class="loot-source">${item.source || item.location || 'Looted'}</div>
        <div class="loot-time">${timeAgo}</div>
      `;
      
      list.appendChild(row);
    });

    card.appendChild(list);
    return card;
  }

  // ===== EXISTING AUCTION WIDGETS =====

  function renderAuctionPerformance(auctions) {
    const card = document.createElement('div');
    card.className = 'auction-performance-card';

    const stats = auctions.stats || {};

    card.innerHTML = `
      <h2 class="finance-title">🏛️ Auction House Performance</h2>
      <div class="auction-stats-grid">
        <div class="auction-stat">
          <div class="stat-label">Active Auctions</div>
          <div class="stat-value">${stats.active_count || 0}</div>
        </div>
        <div class="auction-stat">
          <div class="stat-label">Total Value</div>
          <div class="stat-value">${formatGold(stats.active_value || 0)}</div>
        </div>
        <div class="auction-stat">
          <div class="stat-label">Sold (All Time)</div>
          <div class="stat-value">${stats.sold_count || 0}</div>
        </div>
        <div class="auction-stat">
          <div class="stat-label">Total Sales</div>
          <div class="stat-value">${formatGold(stats.sold_value || 0)}</div>
        </div>
      </div>
    `;

    return card;
  }

  function renderActiveAuctions(auctions) {
    const card = document.createElement('div');
    card.className = 'active-auctions-card';

    const title = document.createElement('h3');
    title.innerHTML = '📋 Active Auctions';
    card.appendChild(title);

    if (!auctions || auctions.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No active auctions';
      card.appendChild(msg);
      return card;
    }

    const list = document.createElement('div');
    list.className = 'auction-list';

    auctions.slice(0, 10).forEach(auction => {
      const row = document.createElement('div');
      row.className = 'auction-item';

      const itemName = auction.name || 'Unknown Item';
      const stackSize = auction.stack_size || 1;
      const price = auction.price_stack || 0;
      const timeLeft = formatAuctionTime(auction.duration_bucket);

      row.innerHTML = `
        <div class="auction-name">${itemName}${stackSize > 1 ? ` x${stackSize}` : ''}</div>
        <div class="auction-price">${formatGold(price)}</div>
        <div class="auction-time">${timeLeft}</div>
      `;

      list.appendChild(row);
    });

    card.appendChild(list);
    return card;
  }

  function formatAuctionTime(bucket) {
    if (bucket === 1) return 'Short (< 30m)';
    if (bucket === 2) return 'Medium (2h)';
    if (bucket === 3) return 'Long (12h)';
    if (bucket === 4) return 'Very Long (48h)';
    return 'Unknown';
  }

  function renderAuctionSuccessRate(auctions) {
    const card = document.createElement('div');
    card.className = 'success-rate-card';

    const title = document.createElement('h3');
    title.innerHTML = '📊 Success Rate';
    card.appendChild(title);

    const stats = auctions.stats || {};
    const total = (stats.sold_count || 0) + (stats.expired_count || 0);
    const successRate = total > 0 ? Math.round(((stats.sold_count || 0) / total) * 100) : 0;

    const chart = document.createElement('div');
    chart.className = 'success-chart';
    
    // FIX: Increased SVG size from 200x200 to 280x280
    // Increased radius from 80 to 110
    // Increased stroke-width from 20 to 24
    // Updated stroke-dasharray calculation for new radius
    // Increased font-size from 36 to 48
    const radius = 110;
    const circumference = 2 * Math.PI * radius; // 690.8
    
    chart.innerHTML = `
      <div class="success-circle">
        <svg width="280" height="280" viewBox="0 0 280 280">
          <circle cx="140" cy="140" r="${radius}" fill="none" stroke="#e6eefb" stroke-width="24"/>
          <circle cx="140" cy="140" r="${radius}" fill="none" stroke="#10b981" stroke-width="24"
                  stroke-dasharray="${(successRate / 100) * circumference} ${circumference}"
                  transform="rotate(-90 140 140)"
                  stroke-linecap="round"/>
          <text x="140" y="140" text-anchor="middle" dy=".3em" font-size="48" font-weight="700" fill="#2456a5">
            ${successRate}%
          </text>
        </svg>
      </div>
      <div class="success-stats">
        <div class="success-stat">
          <span class="success-label">Sold:</span>
          <span class="success-value sold">${stats.sold_count || 0}</span>
        </div>
        <div class="success-stat">
          <span class="success-label">Expired:</span>
          <span class="success-value expired">${stats.expired_count || 0}</span>
        </div>
      </div>
    `;

    card.appendChild(chart);
    return card;
}

  function renderBestSellers(sellers) {
    const card = document.createElement('div');
    card.className = 'best-sellers-card';

    const title = document.createElement('h3');
    title.innerHTML = '🏆 Best Selling Items';
    card.appendChild(title);

    if (!sellers || sellers.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No sales data available';
      card.appendChild(msg);
      return card;
    }

    const table = document.createElement('table');
    table.className = 'best-sellers-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Item</th>
          <th>Units Sold</th>
          <th>Total Revenue</th>
          <th>Avg Price</th>
        </tr>
      </thead>
    `;

    const tbody = document.createElement('tbody');

    sellers.slice(0, 10).forEach((item, index) => {
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>
          <span class="rank">#${index + 1}</span>
          ${item.name}
        </td>
        <td>${item.units_sold || 0}</td>
        <td class="positive">${formatGold(item.total_revenue || 0)}</td>
        <td>${formatGold(item.avg_price || 0)}</td>
      `;
      tbody.appendChild(row);
    });

    table.appendChild(tbody);
    card.appendChild(table);

    return card;
  }

  // ===== NEW ADVANCED ANALYTICS (ALL 9 FEATURES) =====
  
  function renderSellThroughByDuration(data) {
  const card = document.createElement('div');
  card.className = 'auction-analytics-card';
  
  card.innerHTML = `
    <h3>📊 Sell-Through by Duration</h3>
    <div class="sell-through-chart"></div>
  `;
  
  if (!data || data.length === 0) {
    card.querySelector('.sell-through-chart').innerHTML = '<p class="muted">No auction data available</p>';
    return card;
  }
  
  // FIX: Filter out or properly label "Unknown" durations
  const filteredData = data.filter(d => {
    // Only include known durations (1=12h, 2=24h, 3=48h)
    return d.duration_bucket >= 1 && d.duration_bucket <= 3;
  });

  if (filteredData.length === 0) {
    card.querySelector('.sell-through-chart').innerHTML = '<p class="muted">No valid duration data available</p>';
    return card;
  }
  
  const chartDiv = card.querySelector('.sell-through-chart');
  const maxRate = Math.max(...filteredData.map(d => d.success_rate));
  
  filteredData.forEach(duration => {
    const bar = document.createElement('div');
    bar.className = 'duration-bar-item';
    
    const widthPct = maxRate > 0 ? (duration.success_rate / maxRate) * 100 : 0;
    const color = duration.success_rate >= 80 ? '#10b981' : 
                  duration.success_rate >= 60 ? '#f59e0b' : '#ef4444';
    
    bar.innerHTML = `
      <div class="duration-label">${duration.duration_label}</div>
      <div class="duration-bar-bg">
        <div class="duration-bar-fill" style="width: ${widthPct}%; background: ${color};">
          <span class="duration-bar-text">${duration.success_rate}%</span>
        </div>
      </div>
      <div class="duration-stats">
        ${duration.sold_count} sold / ${duration.expired_count} expired (${duration.total} total)
      </div>
    `;
    
    chartDiv.appendChild(bar);
  });
  
  return card;
}


  function renderMarketSeasonality(data) {
    const card = document.createElement('div');
    card.className = 'auction-analytics-card seasonality-card';
    
    card.innerHTML = `
      <h3>📅 Market Seasonality</h3>
      <div class="seasonality-container">
        <div class="seasonality-section">
          <h4>Day of Week</h4>
          <div class="day-heatmap"></div>
        </div>
        <div class="seasonality-section">
          <h4>Hour of Day</h4>
          <div class="hour-heatmap"></div>
        </div>
      </div>
    `;
    
    const dayHeatmap = card.querySelector('.day-heatmap');
    const hourHeatmap = card.querySelector('.hour-heatmap');
    
    if (data.by_day && data.by_day.length > 0) {
      const maxDaySales = Math.max(...data.by_day.map(d => d.sale_count));
      
      data.by_day.forEach(day => {
        const intensity = maxDaySales > 0 ? day.sale_count / maxDaySales : 0;
        const heatDiv = document.createElement('div');
        heatDiv.className = 'heat-cell';
        heatDiv.style.background = `rgba(16, 185, 129, ${intensity})`;
        heatDiv.innerHTML = `
          <div class="heat-label">${day.day_name}</div>
          <div class="heat-value">${day.sale_count}</div>
        `;
        dayHeatmap.appendChild(heatDiv);
      });
    } else {
      dayHeatmap.innerHTML = '<p class="muted">No data</p>';
    }
    
    if (data.by_hour && data.by_hour.length > 0) {
      const maxHourSales = Math.max(...data.by_hour.map(h => h.sale_count));
      const blocks = [
        { label: '12AM-4AM', hours: [0,1,2,3] },
        { label: '4AM-8AM', hours: [4,5,6,7] },
        { label: '8AM-12PM', hours: [8,9,10,11] },
        { label: '12PM-4PM', hours: [12,13,14,15] },
        { label: '4PM-8PM', hours: [16,17,18,19] },
        { label: '8PM-12AM', hours: [20,21,22,23] }
      ];
      
      blocks.forEach(block => {
        const blockSales = data.by_hour
          .filter(h => block.hours.includes(h.hour))
          .reduce((sum, h) => sum + h.sale_count, 0);
        
        const intensity = maxHourSales > 0 ? blockSales / (maxHourSales * 4) : 0;
        const heatDiv = document.createElement('div');
        heatDiv.className = 'heat-cell';
        heatDiv.style.background = `rgba(16, 185, 129, ${intensity})`;
        heatDiv.innerHTML = `
          <div class="heat-label">${block.label}</div>
          <div class="heat-value">${blockSales}</div>
        `;
        hourHeatmap.appendChild(heatDiv);
      });
    } else {
      hourHeatmap.innerHTML = '<p class="muted">No data</p>';
    }
    
    return card;
  }

  function renderDepositBurnVsMargin(data) {
    const card = document.createElement('div');
    card.className = 'auction-analytics-card';
    
    card.innerHTML = `
      <h3>💸 Deposit Burn vs. Margin</h3>
      <div class="scatter-plot-container">
        <canvas class="scatter-canvas" width="600" height="400"></canvas>
      </div>
    `;
    
    if (!data || data.length === 0) {
      card.querySelector('.scatter-plot-container').innerHTML = '<p class="muted">No data available</p>';
      return card;
    }
    
    const canvas = card.querySelector('.scatter-canvas');
    const ctx = canvas.getContext('2d');
    const padding = 60;
    const w = canvas.width - padding * 2;
    const h = canvas.height - padding * 2;
    
    const maxDeposit = Math.max(...data.map(d => d.deposit_burn), 1);
    const minMargin = Math.min(...data.map(d => d.total_margin));
    const maxMargin = Math.max(...data.map(d => d.total_margin));
    const marginRange = maxMargin - minMargin || 1;
    
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(padding, padding);
    ctx.lineTo(padding, padding + h);
    ctx.lineTo(padding + w, padding + h);
    ctx.stroke();
    
    ctx.fillStyle = '#64748b';
    ctx.font = '12px system-ui';
    ctx.textAlign = 'center';
    ctx.fillText('Deposit Burn (copper)', padding + w / 2, canvas.height - 10);
    
    ctx.save();
    ctx.translate(20, padding + h / 2);
    ctx.rotate(-Math.PI / 2);
    ctx.fillText('Margin (copper)', 0, 0);
    ctx.restore();
    
    data.forEach(item => {
      const x = padding + (item.deposit_burn / maxDeposit) * w;
      const y = padding + h - ((item.total_margin - minMargin) / marginRange) * h;
      
      const color = item.total_margin > 0 ? '#10b981' : '#ef4444';
      const radius = Math.min(8, 4 + item.expire_count);
      
      ctx.fillStyle = color;
      ctx.globalAlpha = 0.7;
      ctx.beginPath();
      ctx.arc(x, y, radius, 0, Math.PI * 2);
      ctx.fill();
      ctx.globalAlpha = 1;
    });
    
    const legendDiv = document.createElement('div');
    legendDiv.className = 'scatter-legend';
    legendDiv.innerHTML = `
      <div class="legend-item">
        <span class="legend-dot" style="background: #10b981;"></span>
        <span>Profitable</span>
      </div>
      <div class="legend-item">
        <span class="legend-dot" style="background: #ef4444;"></span>
        <span>Loss</span>
      </div>
      <div class="muted" style="margin-top: 8px; font-size: 0.85rem;">
        Dot size = number of expirations
      </div>
    `;
    card.appendChild(legendDiv);
    
    return card;
  }

  function renderPriceHistory(data) {
    const card = document.createElement('div');
    card.className = 'auction-analytics-card price-history-card';
    
    card.innerHTML = `
      <h3>📈 Price History (Market Prices)</h3>
      <div class="price-history-charts"></div>
    `;
    
    if (!data || data.length === 0) {
      card.querySelector('.price-history-charts').innerHTML = '<p class="muted">No market price data available. Post auctions to track competition!</p>';
      return card;
    }
    
    const chartsDiv = card.querySelector('.price-history-charts');
    
    data.slice(0, 3).forEach(item => {
      if (!item.history || item.history.length < 2) return;
      
      const itemCard = document.createElement('div');
      itemCard.className = 'price-history-item';
      
      const header = document.createElement('h4');
      header.textContent = item.item_name;
      itemCard.appendChild(header);
      
      // Add legend
      const legend = document.createElement('div');
      legend.className = 'price-legend';
      legend.innerHTML = `
        <span class="legend-item"><span class="legend-dot" style="background:#10b981"></span>Min Competitor</span>
        <span class="legend-item"><span class="legend-dot" style="background:#3b82f6"></span>Avg Competitor</span>
        <span class="legend-item"><span class="legend-dot" style="background:#f59e0b"></span>Your Price</span>
      `;
      itemCard.appendChild(legend);
      
      const canvas = document.createElement('canvas');
      canvas.width = 500;
      canvas.height = 150;
      itemCard.appendChild(canvas);
      
      const ctx = canvas.getContext('2d');
      const padding = 40;
      const w = canvas.width - padding * 2;
      const h = canvas.height - padding * 2;
      
      // Get all prices for scaling
      const allPrices = item.history.flatMap(h => [h.min_price, h.avg_price, h.max_price, h.my_price].filter(p => p > 0));
      const minPrice = Math.min(...allPrices);
      const maxPrice = Math.max(...allPrices);
      const range = maxPrice - minPrice || 1;
      
      // Draw min competitor price (green)
      ctx.strokeStyle = '#10b981';
      ctx.lineWidth = 2;
      ctx.beginPath();
      item.history.forEach((point, i) => {
        if (point.min_price > 0) {
          const x = padding + (i / (item.history.length - 1)) * w;
          const y = padding + h - ((point.min_price - minPrice) / range) * h;
          if (i === 0) ctx.moveTo(x, y);
          else ctx.lineTo(x, y);
        }
      });
      ctx.stroke();
      
      // Draw avg competitor price (blue)
      ctx.strokeStyle = '#3b82f6';
      ctx.lineWidth = 2;
      ctx.beginPath();
      item.history.forEach((point, i) => {
        if (point.avg_price > 0) {
          const x = padding + (i / (item.history.length - 1)) * w;
          const y = padding + h - ((point.avg_price - minPrice) / range) * h;
          if (i === 0) ctx.moveTo(x, y);
          else ctx.lineTo(x, y);
        }
      });
      ctx.stroke();
      
      // Draw your price (orange)
      ctx.strokeStyle = '#f59e0b';
      ctx.lineWidth = 2;
      ctx.setLineDash([5, 5]);
      ctx.beginPath();
      item.history.forEach((point, i) => {
        if (point.my_price > 0) {
          const x = padding + (i / (item.history.length - 1)) * w;
          const y = padding + h - ((point.my_price - minPrice) / range) * h;
          if (i === 0) ctx.moveTo(x, y);
          else ctx.lineTo(x, y);
        }
      });
      ctx.stroke();
      ctx.setLineDash([]);
      
      // Draw data points
      item.history.forEach((point, i) => {
        const x = padding + (i / (item.history.length - 1)) * w;
        
        // Min price point
        if (point.min_price > 0) {
          const y = padding + h - ((point.min_price - minPrice) / range) * h;
          ctx.fillStyle = '#10b981';
          ctx.beginPath();
          ctx.arc(x, y, 3, 0, Math.PI * 2);
          ctx.fill();
        }
        
        // Avg price point
        if (point.avg_price > 0) {
          const y = padding + h - ((point.avg_price - minPrice) / range) * h;
          ctx.fillStyle = '#3b82f6';
          ctx.beginPath();
          ctx.arc(x, y, 3, 0, Math.PI * 2);
          ctx.fill();
        }
      });
      
      // Draw axes
      ctx.strokeStyle = '#d1d5db';
      ctx.lineWidth = 1;
      ctx.beginPath();
      ctx.moveTo(padding, padding);
      ctx.lineTo(padding, padding + h);
      ctx.lineTo(padding + w, padding + h);
      ctx.stroke();
      
      // Price labels
      ctx.fillStyle = '#6b7280';
      ctx.font = '10px sans-serif';
      ctx.textAlign = 'right';
      ctx.fillText(formatGoldShort(maxPrice), padding - 5, padding + 5);
      ctx.fillText(formatGoldShort(minPrice), padding - 5, padding + h + 5);
      
      chartsDiv.appendChild(itemCard);
    });
    
    return card;
  }
  
  function formatGoldShort(copper) {
    const g = Math.floor(copper / 10000);
    if (g > 0) return `${g}g`;
    const s = Math.floor((copper % 10000) / 100);
    if (s > 0) return `${s}s`;
    return `${copper}c`;
  }

  function renderUndercutLadder(data) {
    const card = document.createElement('div');
    card.className = 'auction-analytics-card';
    
    card.innerHTML = `
      <h3>🎯 Undercut Opportunities</h3>
      <div class="undercut-list"></div>
    `;
    
    if (!data || data.length === 0) {
      card.querySelector('.undercut-list').innerHTML = '<p class="muted">No active auctions to analyze</p>';
      return card;
    }
    
    const listDiv = card.querySelector('.undercut-list');
    
    data.forEach(item => {
      const itemDiv = document.createElement('div');
      itemDiv.className = 'undercut-item';
      
      const positionClass = item.my_position === 'competitive' ? 'competitive' : 'overpriced';
      const positionIcon = item.my_position === 'competitive' ? '✅' : '⚠️';
      
      itemDiv.innerHTML = `
        <div class="undercut-header">
          <span class="undercut-name">${item.name}</span>
          <span class="position-badge ${positionClass}">${positionIcon} ${item.my_position}</span>
        </div>
        <div class="undercut-pricing">
          <div class="price-row">
            <span class="price-label">Your Price:</span>
            <span class="price-value">${formatGold(item.my_price)}</span>
          </div>
          <div class="price-row">
            <span class="price-label">Lowest Competitor:</span>
            <span class="price-value">${formatGold(item.competitor_low)}</span>
          </div>
        </div>
        <div class="undercut-options">
          <div class="undercut-option">
            <span>Undercut 1%:</span>
            <strong>${formatGold(item.undercut_1pct)}</strong>
          </div>
          <div class="undercut-option">
            <span>Undercut 2%:</span>
            <strong>${formatGold(item.undercut_2pct)}</strong>
          </div>
          <div class="undercut-option">
            <span>Undercut 5%:</span>
            <strong>${formatGold(item.undercut_5pct)}</strong>
          </div>
        </div>
      `;
      
      listDiv.appendChild(itemDiv);
    });
    
    return card;
  }

  function renderTopItemsPerformance(data) {
    const card = document.createElement('div');
    card.className = 'auction-analytics-card';
    
    card.innerHTML = `
      <h3>🏆 Top Items Performance</h3>
      <div class="performance-table-container"></div>
    `;
    
    if (!data || data.length === 0) {
      card.querySelector('.performance-table-container').innerHTML = '<p class="muted">No performance data</p>';
      return card;
    }
    
    const table = document.createElement('table');
    table.className = 'performance-table';
    
    table.innerHTML = `
      <thead>
        <tr>
          <th>Item</th>
          <th>Success Rate</th>
          <th>Avg Time to Sell</th>
          <th>Sales</th>
          <th>Total Profit</th>
        </tr>
      </thead>
    `;
    
    const tbody = document.createElement('tbody');
    
    data.forEach(item => {
      const row = document.createElement('tr');
      const successClass = item.success_rate >= 80 ? 'excellent' :
                          item.success_rate >= 60 ? 'good' : 'poor';
      
      row.innerHTML = `
        <td class="item-name">${item.name}</td>
        <td class="success-rate ${successClass}">${item.success_rate}%</td>
        <td>${item.avg_hours_to_sell > 0 ? item.avg_hours_to_sell.toFixed(1) + 'h' : '-'}</td>
        <td>${item.sold_count}/${item.total_listings}</td>
        <td class="${item.total_profit >= 0 ? 'positive' : 'negative'}">
          ${formatGold(Math.abs(item.total_profit))}
        </td>
      `;
      
      tbody.appendChild(row);
    });
    
    table.appendChild(tbody);
    card.querySelector('.performance-table-container').appendChild(table);
    
    return card;
  }

  function renderCompetitorAnalysis(data) {
    const card = document.createElement('div');
    card.className = 'auction-analytics-card competitor-analysis-card';
    
    card.innerHTML = `
      <h3>👥 Top Competitors (Last 7 Days)</h3>
      <div class="competitor-list"></div>
    `;
    
    if (!data || data.length === 0) {
      card.querySelector('.competitor-list').innerHTML = '<p class="muted">No competitor data. Upload market snapshots to track competition!</p>';
      return card;
    }
    
    const listDiv = card.querySelector('.competitor-list');
    
    data.forEach((comp, index) => {
      const compDiv = document.createElement('div');
      compDiv.className = 'competitor-item';
      
      const headerDiv = document.createElement('div');
      headerDiv.className = 'competitor-header';
      headerDiv.innerHTML = `
        <div class="competitor-rank">#${index + 1}</div>
        <div class="competitor-name">${comp.seller}</div>
        <div class="competitor-summary">${comp.total_items} items · ${comp.total_snapshots} snapshots</div>
      `;
      compDiv.appendChild(headerDiv);
      
      // Show top 3 items for this competitor
      if (comp.items && comp.items.length > 0) {
        const itemsDiv = document.createElement('div');
        itemsDiv.className = 'competitor-items';
        
        comp.items.slice(0, 3).forEach(item => {
          const itemDiv = document.createElement('div');
          itemDiv.className = 'competitor-item-row';
          itemDiv.innerHTML = `
            <div class="item-name">${item.item_name}${item.stack_size > 1 ? ` x${item.stack_size}` : ''}</div>
            <div class="item-price">${formatGold(item.lowest_price)}</div>
          `;
          itemsDiv.appendChild(itemDiv);
        });
        
        if (comp.items.length > 3) {
          const moreDiv = document.createElement('div');
          moreDiv.className = 'competitor-more';
          moreDiv.textContent = `+${comp.items.length - 3} more items`;
          itemsDiv.appendChild(moreDiv);
        }
        
        compDiv.appendChild(itemsDiv);
      }
      
      listDiv.appendChild(compDiv);
    });
    
    return card;
  }

  function renderRepostTracker(data) {
    const card = document.createElement('div');
    card.className = 'auction-analytics-card';
    
    card.innerHTML = `
      <h3>🔄 Repost Tracker (High Churn Items)</h3>
      <div class="repost-list"></div>
    `;
    
    if (!data || data.length === 0) {
      card.querySelector('.repost-list').innerHTML = '<p class="muted">No repost data</p>';
      return card;
    }
    
    const listDiv = card.querySelector('.repost-list');
    
    data.forEach(item => {
      const itemDiv = document.createElement('div');
      itemDiv.className = 'repost-item';
      
      const statusIcon = item.eventually_sold ? '✅' : '❌';
      const statusText = item.eventually_sold ? 'Eventually sold' : 'All expired';
      const statusClass = item.eventually_sold ? 'sold' : 'expired';
      
      itemDiv.innerHTML = `
        <div class="repost-header">
          <span class="repost-name">${item.name}</span>
          <span class="repost-badge ${statusClass}">${statusIcon} ${statusText}</span>
        </div>
        <div class="repost-stats">
          ${item.repost_count} listings on ${item.date}
          <span class="muted">— Consider different pricing/duration</span>
        </div>
      `;
      
      listDiv.appendChild(itemDiv);
    });
    
    return card;
  }

  function renderProfitCalculator() {
    const card = document.createElement('div');
    card.className = 'auction-analytics-card profit-calculator';
    
    card.innerHTML = `
      <h3>🧮 Profit Calculator</h3>
      <div class="calculator-form">
        <div class="calc-input-group">
          <label>Crafting Cost:</label>
          <div class="gsc-input">
            <input type="number" id="craftCostGold" placeholder="0" min="0" value="5">
            <span class="coin-label coin-gold">g</span>
            <input type="number" id="craftCostSilver" placeholder="0" min="0" max="99" value="0">
            <span class="coin-label coin-silver">s</span>
            <input type="number" id="craftCostCopper" placeholder="0" min="0" max="99" value="0">
            <span class="coin-label coin-copper">c</span>
          </div>
        </div>
        <div class="calc-input-group">
          <label>Listing Price:</label>
          <div class="gsc-input">
            <input type="number" id="listPriceGold" placeholder="0" min="0" value="6">
            <span class="coin-label coin-gold">g</span>
            <input type="number" id="listPriceSilver" placeholder="0" min="0" max="99" value="50">
            <span class="coin-label coin-silver">s</span>
            <input type="number" id="listPriceCopper" placeholder="0" min="0" max="99" value="0">
            <span class="coin-label coin-copper">c</span>
          </div>
        </div>
        <div class="calc-input-group">
          <label>Duration:</label>
          <select id="duration">
            <option value="1">12 hours</option>
            <option value="2" selected>24 hours</option>
            <option value="3">48 hours</option>
          </select>
        </div>
        <div class="calc-results">
          <div class="calc-result-row">
            <span>Deposit Fee:</span>
            <strong id="depositFee">-</strong>
          </div>
          <div class="calc-result-row">
            <span>AH Cut (5%):</span>
            <strong id="ahCut">-</strong>
          </div>
          <div class="calc-result-row calc-total">
            <span>Net Profit:</span>
            <strong id="netProfit" class="profit-value">-</strong>
          </div>
          <div class="calc-result-row">
            <span>Margin:</span>
            <strong id="margin">-</strong>
          </div>
        </div>
      </div>
    `;
    
    const calcInputs = [
      card.querySelector('#craftCostGold'),
      card.querySelector('#craftCostSilver'),
      card.querySelector('#craftCostCopper'),
      card.querySelector('#listPriceGold'),
      card.querySelector('#listPriceSilver'),
      card.querySelector('#listPriceCopper'),
      card.querySelector('#duration')
    ];
    
    function calculate() {
      const craftG = parseInt(card.querySelector('#craftCostGold').value) || 0;
      const craftS = parseInt(card.querySelector('#craftCostSilver').value) || 0;
      const craftC = parseInt(card.querySelector('#craftCostCopper').value) || 0;
      const listG = parseInt(card.querySelector('#listPriceGold').value) || 0;
      const listS = parseInt(card.querySelector('#listPriceSilver').value) || 0;
      const listC = parseInt(card.querySelector('#listPriceCopper').value) || 0;
      const duration = parseInt(card.querySelector('#duration').value) || 2;
      
      const cost = craftG * 10000 + craftS * 100 + craftC;
      const price = listG * 10000 + listS * 100 + listC;
      
      const vendorPrice = Math.floor(price * 0.1);
      const depositMultiplier = { 1: 0.15, 2: 0.30, 3: 0.60 }[duration] || 0.30;
      const deposit = Math.floor(vendorPrice * depositMultiplier);
      const ahCut = Math.floor(price * 0.05);
      const netProfit = price - cost - deposit - ahCut;
      const margin = cost > 0 ? ((netProfit / cost) * 100).toFixed(1) : 0;
      
      card.querySelector('#depositFee').innerHTML = formatGold(deposit);
      card.querySelector('#ahCut').innerHTML = formatGold(ahCut);
      card.querySelector('#netProfit').innerHTML = formatGold(Math.abs(netProfit));
      card.querySelector('#netProfit').className = 'profit-value ' + (netProfit >= 0 ? 'positive' : 'negative');
      card.querySelector('#margin').innerHTML = `${margin}%`;
      card.querySelector('#margin').className = netProfit >= 0 ? 'positive' : 'negative';
    }
    
    calcInputs.forEach(input => {
      input.addEventListener('input', calculate);
      input.addEventListener('change', calculate);
    });
    
    setTimeout(calculate, 100);
    
    return card;
  }

  // ===== Tab Switching =====
  function initTabs() {
    const root = q('#tab-currencies');
    if (!root) return;

    const tabContainer = document.createElement('div');
    tabContainer.className = 'currency-tabs';
    tabContainer.innerHTML = `
      <button class="currency-tab active" data-tab="economy">💰 Economy Overview</button>
      <button class="currency-tab" data-tab="auctions">🏛️ Auction House</button>
      <button class="currency-tab" data-tab="history">📜 Auction History</button>
    `;

    const economyTab = document.createElement('div');
    economyTab.className = 'currency-tab-content active';
    economyTab.id = 'economy-tab';

    const auctionTab = document.createElement('div');
    auctionTab.className = 'currency-tab-content';
    auctionTab.id = 'auction-tab';

    const historyTab = document.createElement('div');
    historyTab.className = 'currency-tab-content';
    historyTab.id = 'history-tab';

    root.innerHTML = '';
    root.appendChild(tabContainer);
    root.appendChild(economyTab);
    root.appendChild(auctionTab);
    root.appendChild(historyTab);

    tabContainer.querySelectorAll('.currency-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        tabContainer.querySelectorAll('.currency-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const targetTab = btn.dataset.tab;
        q('#economy-tab').classList.toggle('active', targetTab === 'economy');
        q('#auction-tab').classList.toggle('active', targetTab === 'auctions');
        q('#history-tab').classList.toggle('active', targetTab === 'history');
      });
    });

    return { economyTab, auctionTab, historyTab };
  }

  // ===== Main Dashboard Renderer =====
  function renderCurrenciesPage(data) {
    const tabs = initTabs();
    if (!tabs) return;

    const { economyTab, auctionTab, historyTab } = tabs;

    renderEconomyTab(economyTab, data);
    renderAuctionTab(auctionTab, data);
    renderHistoryTab(historyTab, data);
  }

  function renderEconomyTab(root, data) {
    root.innerHTML = '';

    // Keep ALL existing widgets
    const topSection = document.createElement('div');
    topSection.className = 'dashboard-section';
    topSection.appendChild(renderFinancialHealth(data));
    root.appendChild(topSection);

    const chartSection = document.createElement('div');
    chartSection.className = 'dashboard-section dashboard-row';
    chartSection.appendChild(renderNetWorthChart(data.timeseries || []));
    chartSection.appendChild(renderGoldPerHour(data.gold_per_hour || []));
    root.appendChild(chartSection);

    const bottomSection = document.createElement('div');
    bottomSection.className = 'dashboard-section dashboard-row';
    bottomSection.appendChild(renderMilestones(data.milestones || []));
    bottomSection.appendChild(renderRecentLoot(data.epic_loot || []));
    root.appendChild(bottomSection);
  }

  function renderAuctionTab(root, data) {
    root.innerHTML = '';

    // Keep ALL existing widgets first
    const topSection = document.createElement('div');
    topSection.className = 'dashboard-section';
    topSection.appendChild(renderAuctionPerformance(data.auctions || {}));
    root.appendChild(topSection);

    const midSection = document.createElement('div');
    midSection.className = 'dashboard-section dashboard-row';
    midSection.appendChild(renderActiveAuctions(data.auctions?.active || []));
    midSection.appendChild(renderAuctionSuccessRate(data.auctions || {}));
    root.appendChild(midSection);

    const bottomSection = document.createElement('div');
    bottomSection.className = 'dashboard-section';
    bottomSection.appendChild(renderBestSellers(data.auctions?.best_sellers || []));
    root.appendChild(bottomSection);

    // ADD ALL 9 new analytics features BELOW existing widgets
    const analyticsGrid = document.createElement('div');
    analyticsGrid.className = 'analytics-grid';
    analyticsGrid.style.marginTop = '32px';
    
    // Row 1: Core Performance
    const row1 = document.createElement('div');
    row1.className = 'analytics-row';
    row1.appendChild(renderSellThroughByDuration(data.auctions?.sell_through_by_duration || []));
    row1.appendChild(renderMarketSeasonality(data.auctions?.market_seasonality || {}));
    analyticsGrid.appendChild(row1);
    
    // Row 2: Price Analysis
    const row2 = document.createElement('div');
    row2.className = 'analytics-row';
    row2.appendChild(renderPriceHistory(data.auctions?.price_history || []));
    row2.appendChild(renderDepositBurnVsMargin(data.auctions?.deposit_vs_margin || []));
    analyticsGrid.appendChild(row2);
    
    // Row 3: Tactical Tools
    const row3 = document.createElement('div');
    row3.className = 'analytics-row';
    row3.appendChild(renderUndercutLadder(data.auctions?.undercut_opportunities || []));
    row3.appendChild(renderProfitCalculator());
    analyticsGrid.appendChild(row3);
    
    // Row 4: Performance Dashboard
    const row4 = document.createElement('div');
    row4.className = 'analytics-row';
    row4.appendChild(renderTopItemsPerformance(data.auctions?.top_items_performance || []));
    analyticsGrid.appendChild(row4);
    
    // Row 5: Market Intelligence
    const row5 = document.createElement('div');
    row5.className = 'analytics-row';
    row5.appendChild(renderCompetitorAnalysis(data.auctions?.competitor_analysis || []));
    row5.appendChild(renderRepostTracker(data.auctions?.repost_tracker || []));
    analyticsGrid.appendChild(row5);
    
    root.appendChild(analyticsGrid);
  }

  function renderHistoryTab(root, data) {
    root.innerHTML = '';

    const historyData = data.auctions?.auction_history || [];
    
    const card = document.createElement('div');
    card.className = 'auction-history-card';
    card.innerHTML = `
      <h2>📜 Complete Auction History</h2>
      <div class="history-controls">
        <input type="text" id="historySearch" class="history-search" placeholder="Search by item name...">
        <div class="history-stats">
          <span>Total Records: <strong id="totalRecords">0</strong></span>
          <span>Sold: <strong id="totalSold" class="positive">0</strong></span>
          <span>Expired: <strong id="totalExpired" class="negative">0</strong></span>
        </div>
      </div>
      <div class="table-container">
        <table class="auction-history-table" id="historyTable">
          <thead>
            <tr>
              <th data-sort="last_seen">Last Seen <span class="sort-arrow">↕</span></th>
              <th data-sort="name">Item Name <span class="sort-arrow">↕</span></th>
              <th data-sort="price">Auction Price <span class="sort-arrow">↕</span></th>
              <th data-sort="duration">Time Frame <span class="sort-arrow">↕</span></th>
              <th data-sort="competitor">Top Competitor <span class="sort-arrow">↕</span></th>
              <th data-sort="competitor_price">Competitor Price <span class="sort-arrow">↕</span></th>
              <th data-sort="sold_count">Sold <span class="sort-arrow">↕</span></th>
              <th data-sort="expired_count">Expired <span class="sort-arrow">↕</span></th>
              <th data-sort="avg_profit">Avg. Profit/Loss <span class="sort-arrow">↕</span></th>
            </tr>
          </thead>
          <tbody id="historyTableBody">
          </tbody>
        </table>
      </div>
      <div class="history-pagination">
        <button id="prevPage" class="page-btn" disabled>← Previous</button>
        <span id="pageInfo">Page 1 of 1</span>
        <button id="nextPage" class="page-btn" disabled>Next →</button>
      </div>
    `;

    root.appendChild(card);

    // Initialize table with data
    initAuctionHistoryTable(historyData);
  }

  function initAuctionHistoryTable(data) {
    let currentSort = { column: 'last_seen', direction: 'desc' };
    let currentPage = 1;
    const rowsPerPage = 50;
    let filteredData = [...data];

    // Update stats
    const totalSold = data.filter(d => d.sold_count > 0).length;
    const totalExpired = data.filter(d => d.expired_count > 0).length;
    q('#totalRecords').textContent = data.length;
    q('#totalSold').textContent = totalSold;
    q('#totalExpired').textContent = totalExpired;

    function renderTable() {
      const tbody = q('#historyTableBody');
      if (!tbody) return;

      // Sort data
      const sorted = [...filteredData].sort((a, b) => {
        let aVal = a[currentSort.column];
        let bVal = b[currentSort.column];

        // Handle null/undefined
        if (aVal === null || aVal === undefined) aVal = '';
        if (bVal === null || bVal === undefined) bVal = '';

        // Numeric comparison
        if (typeof aVal === 'number' && typeof bVal === 'number') {
          return currentSort.direction === 'asc' ? aVal - bVal : bVal - aVal;
        }

        // String comparison
        const aStr = String(aVal).toLowerCase();
        const bStr = String(bVal).toLowerCase();
        if (currentSort.direction === 'asc') {
          return aStr < bStr ? -1 : aStr > bStr ? 1 : 0;
        } else {
          return bStr < aStr ? -1 : bStr > aStr ? 1 : 0;
        }
      });

      // Paginate
      const totalPages = Math.ceil(sorted.length / rowsPerPage);
      const start = (currentPage - 1) * rowsPerPage;
      const end = start + rowsPerPage;
      const pageData = sorted.slice(start, end);

      // Render rows
      tbody.innerHTML = '';
      pageData.forEach(row => {
        const tr = document.createElement('tr');
        
        // Format profit/loss with arrow and color (plain text, no boxes)
        let profitDisplay = '<span class="muted">—</span>';
        if (row.avg_profit !== null) {
          const arrow = row.avg_profit >= 0 ? '↑' : '↓';
          const className = row.avg_profit >= 0 ? 'profit-gain' : 'profit-loss';
          profitDisplay = `<span class="${className}">${arrow} ${formatGoldPlain(Math.abs(row.avg_profit))}</span>`;
        }
        
        tr.innerHTML = `
          <td>${formatTimeAgo(row.last_seen)}</td>
          <td class="item-name-cell">${row.name}</td>
          <td>${formatGoldPlain(row.price)}</td>
          <td>${formatDurationBucket(row.duration)}</td>
          <td>${row.competitor || '<span class="muted">—</span>'}</td>
          <td>${row.competitor_price ? formatGoldPlain(row.competitor_price) : '<span class="muted">—</span>'}</td>
          <td class="center">${row.sold_count || 0}</td>
          <td class="center">${row.expired_count || 0}</td>
          <td class="center">${profitDisplay}</td>
        `;
        tbody.appendChild(tr);
      });

      // Update pagination
      q('#pageInfo').textContent = `Page ${currentPage} of ${totalPages}`;
      q('#prevPage').disabled = currentPage === 1;
      q('#nextPage').disabled = currentPage === totalPages || totalPages === 0;

      // Update sort arrows
      document.querySelectorAll('.auction-history-table th').forEach(th => {
        const arrow = th.querySelector('.sort-arrow');
        if (arrow) {
          if (th.dataset.sort === currentSort.column) {
            arrow.textContent = currentSort.direction === 'asc' ? '↑' : '↓';
            th.classList.add('sorted');
          } else {
            arrow.textContent = '↕';
            th.classList.remove('sorted');
          }
        }
      });
    }

    function formatDurationBucket(bucket) {
      const map = { 1: '12h', 2: '24h', 3: '48h', 4: '48h' };
      return map[bucket] || '—';
    }

    // Search functionality
    const searchInput = q('#historySearch');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        filteredData = data.filter(d => d.name.toLowerCase().includes(term));
        currentPage = 1;
        renderTable();
      });
    }

    // Sort functionality
    document.querySelectorAll('.auction-history-table th[data-sort]').forEach(th => {
      th.addEventListener('click', () => {
        const column = th.dataset.sort;
        if (currentSort.column === column) {
          currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
          currentSort.column = column;
          currentSort.direction = 'desc';
        }
        renderTable();
      });
    });

    // Pagination
    const prevBtn = q('#prevPage');
    const nextBtn = q('#nextPage');
    if (prevBtn) {
      prevBtn.addEventListener('click', () => {
        if (currentPage > 1) {
          currentPage--;
          renderTable();
        }
      });
    }
    if (nextBtn) {
      nextBtn.addEventListener('click', () => {
        const totalPages = Math.ceil(filteredData.length / rowsPerPage);
        if (currentPage < totalPages) {
          currentPage++;
          renderTable();
        }
      });
    }

    // Initial render
    renderTable();
  }

  // ===== Data Loading =====
  async function loadCurrenciesPage() {
    const root = q('#tab-currencies');
    if (!root) {
      log('ERROR: #tab-currencies not found in DOM');
      return;
    }

    const cid = root.dataset?.characterId;
    log('Loading currencies for character:', cid);

    if (!cid) {
      root.innerHTML = '<p class="muted">No character selected</p>';
      return;
    }

    root.innerHTML = '<div class="muted" style="text-align: center; padding: 40px 0;"><div style="font-size: 2rem; margin-bottom: 16px;">⏳</div><div>Loading complete analytics...</div></div>';

    try {
      const url = `/sections/currencies-data.php?character_id=${encodeURIComponent(cid)}`;
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
      
      renderCurrenciesPage(data);
    } catch (err) {
      log('Failed to load currencies data:', err);
      root.innerHTML = `<p style="color:#d32f2f;">Failed to load economy data: ${err.message}</p>`;
    }
  }

  // ===== Event Listeners =====
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'currencies') {
      loadCurrenciesPage();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || '';
    if (currentSection === 'currencies') {
      loadCurrenciesPage();
    }
  });

  if (q('#tab-currencies')) {
    log('Found #tab-currencies on page load, loading now...');
    loadCurrenciesPage();
  } else {
    log('No #tab-currencies found on initial load, waiting for event...');
  }

  log('Complete Enhanced Currencies module loaded - ALL 9 features active');
})();