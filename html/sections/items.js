/* eslint-disable no-console */
/* WhoDASH Items â€“ Inventory, Bags, Bank, Mail, Keyring */
(() => {
  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[items]', ...a);

  // Quality color mapping - DARKER versions for visibility on light backgrounds
  const QUALITY_COLORS = {
    'Poor': '#6b7280',      // Dark gray
    'Common': '#111827',    // Nearly black
    'Uncommon': '#16a34a',  // Dark green
    'Rare': '#0070dd',      // Blue
    'Epic': '#9333ea',      // Dark purple
    'Legendary': '#ea580c', // Dark orange
  };

  function getQualityColor(quality) {
    return QUALITY_COLORS[quality] || '#111827';
  }

  // ===== EQUIPMENT BAR (for displaying equipped bags/bank bags) =====
  function renderEquipmentBar(equipment, label = 'Equipped', mainBankSlots = 0) {
    const bar = document.createElement('div');
    bar.className = 'equipment-bar';
    bar.style.cssText = `
      background: linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%);
      padding: 12px 16px;
      border-radius: 8px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 20px;
      flex-wrap: wrap;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    `;

    const items = [];
    
    // Add main bank/backpack slots if provided
    if (mainBankSlots > 0) {
      items.push(`
        <div style="display: flex; align-items: center; gap: 8px;">
          <div style="
            width: 32px; 
            height: 32px; 
            background: #0369a1;
            border-radius: 4px;
            border: 2px solid #0284c7;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
          ">🏦</div>
          <div style="font-size: 14px; font-weight: 600; color: #0c4a6e;">
            Main: ${mainBankSlots} slots
          </div>
        </div>
      `);
    }
    
    // Sort equipment by bag_id
    if (equipment && equipment.length > 0) {
      const sortedEquipment = [...equipment].sort((a, b) => a.bag_id - b.bag_id);
      
      sortedEquipment.forEach(bag => {
        if (bag.slots === 0 && !bag.name) {
          return; // Skip empty slots
        }
        
        const iconUrl = bag.icon 
          ? `/icon.php?type=item&id=${bag.item_id}&name=${encodeURIComponent(bag.name)}&size=medium`
          : 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"%3E%3Crect fill="%23cbd5e1" width="24" height="24"/%3E%3C/svg%3E';
        
        const slotInfo = bag.slots > 0 ? `${bag.slots} slots` : (bag.name || 'No bag');
        
        items.push(`
          <div style="display: flex; align-items: center; gap: 8px;">
            <div style="
              width: 32px; 
              height: 32px; 
              background-image: url(&quot;${iconUrl}&quot;); 
              background-size: cover; 
              border-radius: 4px;
              border: 2px solid #0284c7;
            "></div>
            <div style="font-size: 14px; font-weight: 600; color: #0c4a6e;">
              ${slotInfo}
            </div>
          </div>
        `);
      });
    }
    
    if (items.length === 0) {
      bar.innerHTML = `<div style="color: #64748b; font-size: 14px;">No ${label.toLowerCase()} found</div>`;
    } else {
      bar.innerHTML = items.join('');
    }
    
    return bar;
  }

  // ===== VAULT TAB =====
  function renderVaultTab(root, data) {
    root.innerHTML = '';

    const stats = data.vault_stats || {};
    const stackStats = stats.stack_stats || {};

    // Top section - Overview stats with enhanced info
    const topSection = document.createElement('div');
    topSection.className = 'vault-overview';
    topSection.innerHTML = `
      <div class="vault-stat-card">
        <div class="vault-stat-icon">📦</div>
        <div class="vault-stat-content">
          <div class="vault-stat-label">Total Items</div>
          <div class="vault-stat-value">${stats.total_items || 0}</div>
          <div class="vault-stat-sublabel">${stackStats.total_stacks || 0} stacks</div>
        </div>
      </div>
      <div class="vault-stat-card">
        <div class="vault-stat-icon">✨</div>
        <div class="vault-stat-content">
          <div class="vault-stat-label">Unique Items</div>
          <div class="vault-stat-value">${stats.unique_items || 0}</div>
          <div class="vault-stat-sublabel">Avg ${stackStats.avg_stack || 0} per stack</div>
        </div>
      </div>
      <div class="vault-stat-card">
        <div class="vault-stat-icon">💎</div>
        <div class="vault-stat-content">
          <div class="vault-stat-label">Epic+ Items</div>
          <div class="vault-stat-value">${stats.rarest_items?.length || 0}</div>
          <div class="vault-stat-sublabel">Max stack: ${stackStats.max_stack || 0}</div>
        </div>
      </div>
    `;
    root.appendChild(topSection);

    // Location Distribution - NEW!
    if (stats.by_location && stats.by_location.length > 0) {
      const locationSection = document.createElement('div');
      locationSection.className = 'vault-section';
      locationSection.innerHTML = '<h3>📍 Items by Location</h3>';
      
      const locationChart = document.createElement('div');
      locationChart.className = 'quality-chart';
      
      const locationIcons = {
        'Bags': '🎒',
        'Bank': '🏦',
        'Mail': '📬',
        'Keyring': '🔑'
      };
      
      const locationColors = {
        'Bags': '#3b82f6',
        'Bank': '#8b5cf6',
        'Mail': '#ec4899',
        'Keyring': '#f59e0b'
      };
      
      stats.by_location.forEach(loc => {
        const bar = document.createElement('div');
        bar.className = 'quality-bar';
        const icon = locationIcons[loc.location] || '📦';
        const color = locationColors[loc.location] || '#6b7280';
        bar.innerHTML = `
          <div class="quality-label" style="color: ${color}">${icon} ${loc.location}</div>
          <div class="quality-bar-fill" style="width: ${(loc.count / stats.total_items * 100)}%; background: ${color}"></div>
          <div class="quality-count">${loc.count}</div>
        `;
        locationChart.appendChild(bar);
      });
      
      locationSection.appendChild(locationChart);
      root.appendChild(locationSection);
    }

    // Quality breakdown
    const qualitySection = document.createElement('div');
    qualitySection.className = 'vault-section';
    qualitySection.innerHTML = '<h3>📊 Items by Quality</h3>';
    
    const qualityChart = document.createElement('div');
    qualityChart.className = 'quality-chart';
    
    if (stats.by_quality && stats.by_quality.length > 0) {
      stats.by_quality.forEach(q => {
        const bar = document.createElement('div');
        bar.className = 'quality-bar';
        bar.innerHTML = `
          <div class="quality-label" style="color: ${getQualityColor(q.quality)}">${q.quality}</div>
          <div class="quality-bar-fill" style="width: ${(q.count / stats.total_items * 100)}%; background: ${getQualityColor(q.quality)}"></div>
          <div class="quality-count">${q.count}</div>
        `;
        qualityChart.appendChild(bar);
      });
    } else {
      qualityChart.innerHTML = '<p class="muted">No quality data available</p>';
    }
    
    qualitySection.appendChild(qualityChart);
    root.appendChild(qualitySection);

    // Largest Stacks - NEW!
    if (stats.largest_stacks && stats.largest_stacks.length > 0) {
      const stackSection = document.createElement('div');
      stackSection.className = 'vault-section';
      stackSection.innerHTML = '<h3>📚 Largest Stacks</h3>';
      
      const table = document.createElement('div');
      table.className = 'vault-items-list';
      
      stats.largest_stacks.slice(0, 10).forEach(item => {
        const row = document.createElement('div');
        row.className = 'vault-item-row';
        row.innerHTML = `
          <div class="vault-item-name" style="color: ${getQualityColor(item.quality)}">${item.name}</div>
          <div class="vault-item-count">x${item.count}</div>
          <div class="vault-item-location">${item.location}</div>
        `;
        table.appendChild(row);
      });
      
      stackSection.appendChild(table);
      root.appendChild(stackSection);
    }

    // Most Valuable Items
    const valuableSection = document.createElement('div');
    valuableSection.className = 'vault-section';
    valuableSection.innerHTML = '<h3>💰 Most Valuable Items (by iLvl)</h3>';
    
    if (stats.most_valuable && stats.most_valuable.length > 0) {
      const table = document.createElement('div');
      table.className = 'vault-items-list';
      
      stats.most_valuable.slice(0, 10).forEach(item => {
        const row = document.createElement('div');
        row.className = 'vault-item-row';
        row.innerHTML = `
          <div class="vault-item-name" style="color: ${getQualityColor(item.quality)}">${item.name}</div>
          <div class="vault-item-ilvl">iLvl ${item.ilvl}</div>
          <div class="vault-item-location">${item.location}</div>
        `;
        table.appendChild(row);
      });
      
      valuableSection.appendChild(table);
    } else {
      valuableSection.innerHTML += '<p class="muted">No items found</p>';
    }
    
    root.appendChild(valuableSection);

    // Rarest Items
    const rareSection = document.createElement('div');
    rareSection.className = 'vault-section';
    rareSection.innerHTML = '<h3>🌟 Epic & Legendary Items</h3>';
    
    if (stats.rarest_items && stats.rarest_items.length > 0) {
      const table = document.createElement('div');
      table.className = 'vault-items-list';
      
      stats.rarest_items.slice(0, 10).forEach(item => {
        const row = document.createElement('div');
        row.className = 'vault-item-row';
        row.innerHTML = `
          <div class="vault-item-name" style="color: ${getQualityColor(item.quality)}">${item.name}</div>
          <div class="vault-item-ilvl">iLvl ${item.ilvl}</div>
          <div class="vault-item-location">${item.location}</div>
        `;
        table.appendChild(row);
      });
      
      rareSection.appendChild(table);
    } else {
      rareSection.innerHTML += '<p class="muted">No epic or legendary items found</p>';
    }
    
    root.appendChild(rareSection);
  }

  // ===== BAGS TAB =====
  function renderBagsTab(root, data, characterId) {
    const cid = characterId || null;
    root.innerHTML = '';

    const bags = data.bags || [];
    const bagEquipment = data.bag_equipment || [];
    
    try {
      // Add equipment bar at top
      if (bagEquipment.length > 0) {
        root.appendChild(renderEquipmentBar(bagEquipment, 'Bags'));
      }
    } catch (err) {
      console.error('[items] Error rendering bag equipment:', err);
    }
    
    if (bags.length === 0) {
      const emptyMsg = document.createElement('p');
      emptyMsg.className = 'muted';
      emptyMsg.textContent = 'No bag items found';
      root.appendChild(emptyMsg);
      return;
    }

    try {
      // Group by bag_id for organized display
      const bagGroups = {};
      bags.forEach(item => {
        const bagName = item.bag_name || 'Bag ' + item.bag_id;
        if (!bagGroups[bagName]) {
          bagGroups[bagName] = [];
        }
        bagGroups[bagName].push(item);
      });

      // Display each bag as a section
      Object.keys(bagGroups).sort().forEach(bagName => {
        const bagSection = document.createElement('div');
        bagSection.className = 'bag-container';
        bagSection.innerHTML = `<h3>🎒 ${bagName} (${bagGroups[bagName].length} items)</h3>`;
        
        const itemsGrid = document.createElement('div');
        itemsGrid.className = 'items-grid';
        
        bagGroups[bagName].forEach(item => {
          const itemCard = document.createElement('div');
          itemCard.className = 'item-card';
          itemCard.style.borderColor = getQualityColor(item.quality_name);
          
          const iconSrc = `/icon.php?type=item&id=${item.item_id}&name=${encodeURIComponent(item.name)}&size=large`;
          
          itemCard.innerHTML = `
            <div class="item-icon" style="background-image: url(&quot;${iconSrc}&quot;)"></div>
            <div class="item-name-dark" style="color: ${getQualityColor(item.quality_name)}">${item.name}</div>
            <div class="item-count-dark">x${item.count}</div>
            ${item.ilvl > 0 ? `<div class="item-ilvl-dark">iLvl ${item.ilvl}</div>` : ''}
          `;

          // Attach Wowhead tooltip
          if (window.WDTooltip) {
            itemCard.style.cursor = 'default';
            WDTooltip.attach(itemCard, { link: item.link, item_id: item.item_id, name: item.name, icon: item.icon }, cid);
          }

          itemsGrid.appendChild(itemCard);
        });
        
        bagSection.appendChild(itemsGrid);
        root.appendChild(bagSection);
      });
    } catch (err) {
      console.error('[items] Error rendering bags:', err);
      root.innerHTML += '<p style="color: red;">Error displaying bags: ' + err.message + '</p>';
    }
  }

  // ===== BANK TAB =====
  function renderBankTab(root, data, characterId) {
    const cid = characterId || null;
    root.innerHTML = '';

    const bank = data.bank || [];
    const bankEquipment = data.bank_equipment || [];
    
    // Add equipment bar at top
    // Extract main bank info
    const mainBank = bankEquipment.find(b => b.bag_id === -1);
    const bankBags = bankEquipment.filter(b => b.bag_id !== -1);
    
    // Add equipment bar at top
    if (mainBank || bankBags.length > 0) {
      root.appendChild(renderEquipmentBar(bankBags, 'Bank Bags', mainBank ? mainBank.slots : 0));
    }
    
    if (bank.length === 0) {
      const emptyMsg = document.createElement('p');
      emptyMsg.className = 'muted';
      emptyMsg.textContent = 'No bank items found';
      root.appendChild(emptyMsg);
      return;
    }

    // Group by bag_id (bank container)
    const bankGroups = {};
    bank.forEach(item => {
      const containerName = item.container_name || (item.bag_id === -1 ? 'Main Bank' : `Bank Bag ${item.bag_id - 4}`);
      if (!bankGroups[containerName]) {
        bankGroups[containerName] = [];
      }
      bankGroups[containerName].push(item);
    });

    // Display each bank container
    Object.keys(bankGroups).sort().forEach(containerName => {
      const bankSection = document.createElement('div');
      bankSection.className = 'bag-container';
      bankSection.innerHTML = `<h3>🏦 ${containerName} (${bankGroups[containerName].length} items)</h3>`;
      
      const itemsGrid = document.createElement('div');
      itemsGrid.className = 'items-grid';
      
      bankGroups[containerName].forEach(item => {
        const itemCard = document.createElement('div');
        itemCard.className = 'item-card';
        itemCard.style.borderColor = getQualityColor(item.quality_name);
        
        const iconSrc = `/icon.php?type=item&id=${item.item_id}&name=${encodeURIComponent(item.name)}&size=large`;
        
        itemCard.innerHTML = `
          <div class="item-icon" style="background-image: url(&quot;${iconSrc}&quot;)"></div>
          <div class="item-name-dark" style="color: ${getQualityColor(item.quality_name)}">${item.name}</div>
          <div class="item-count-dark">x${item.count}</div>
          ${item.ilvl > 0 ? `<div class="item-ilvl-dark">iLvl ${item.ilvl}</div>` : ''}
        `;

        // Attach Wowhead tooltip
        if (window.WDTooltip) {
          itemCard.style.cursor = 'default';
          WDTooltip.attach(itemCard, { link: item.link, item_id: item.item_id, name: item.name, icon: item.icon }, cid);
        }

        itemsGrid.appendChild(itemCard);
      });
      
      bankSection.appendChild(itemsGrid);
      root.appendChild(bankSection);
    });
  }

  // ===== MAIL TAB =====
  function renderMailTab(root, data) {
    root.innerHTML = '';

    const mailMessages = data.mail_messages || [];
    
    if (mailMessages.length === 0) {
      const emptyMsg = document.createElement('p');
      emptyMsg.className = 'muted';
      emptyMsg.textContent = 'No mail found';
      root.appendChild(emptyMsg);
      return;
    }

    try {
      // Display each mail message
      mailMessages.forEach(mail => {
        const mailCard = document.createElement('div');
        mailCard.className = 'mail-card';
        mailCard.style.cssText = `
          background: white;
          border: 1px solid #e2e8f0;
          border-radius: 8px;
          padding: 16px;
          margin-bottom: 16px;
          box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        `;
        
        // Header
        const header = document.createElement('div');
        header.style.cssText = 'display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;';
        
        const senderInfo = document.createElement('div');
        const senderLabel = document.createElement('div');
        senderLabel.style.cssText = 'font-size: 12px; color: #64748b; margin-bottom: 4px;';
        senderLabel.textContent = 'From:';
        
        const senderName = document.createElement('div');
        senderName.style.cssText = 'font-weight: 600; color: #1e293b;';
        senderName.textContent = mail.sender || 'Unknown';
        if (mail.is_auction) {
          senderName.textContent = '🏪 ' + senderName.textContent;
        }
        
        senderInfo.appendChild(senderLabel);
        senderInfo.appendChild(senderName);
        
        const daysLeft = document.createElement('div');
        daysLeft.style.cssText = 'font-size: 12px; color: ' + (mail.days_left < 1 ? '#dc2626' : '#64748b') + ';';
        daysLeft.textContent = Math.floor(mail.days_left) + ' days left';
        
        header.appendChild(senderInfo);
        header.appendChild(daysLeft);
        mailCard.appendChild(header);
        
        // Subject
        const subject = document.createElement('div');
        subject.style.cssText = 'font-size: 14px; font-weight: 500; color: #334155; margin-bottom: 12px;';
        subject.textContent = mail.subject || '(No Subject)';
        if (!mail.was_read) {
          subject.style.fontWeight = '700';
          const unreadBadge = document.createElement('span');
          unreadBadge.style.cssText = 'background: #3b82f6; color: white; font-size: 10px; padding: 2px 6px; border-radius: 4px; margin-left: 8px;';
          unreadBadge.textContent = 'UNREAD';
          subject.appendChild(unreadBadge);
        }
        mailCard.appendChild(subject);
        
        // Money/COD
        if (mail.money > 0 || mail.cod > 0) {
          const moneyInfo = document.createElement('div');
          moneyInfo.style.cssText = 'display: flex; gap: 16px; margin-bottom: 12px;';
          
          if (mail.money > 0) {
            const gold = Math.floor(mail.money / 10000);
            const silver = Math.floor((mail.money % 10000) / 100);
            const copper = mail.money % 100;
            const moneyDiv = document.createElement('div');
            moneyDiv.style.cssText = 'font-size: 13px; color: #059669;';
            moneyDiv.innerHTML = `<strong>Gold:</strong> ${gold}g ${silver}s ${copper}c`;
            moneyInfo.appendChild(moneyDiv);
          }
          
          if (mail.cod > 0) {
            const gold = Math.floor(mail.cod / 10000);
            const silver = Math.floor((mail.cod % 10000) / 100);
            const copper = mail.cod % 100;
            const codDiv = document.createElement('div');
            codDiv.style.cssText = 'font-size: 13px; color: #dc2626;';
            codDiv.innerHTML = `<strong>COD:</strong> ${gold}g ${silver}s ${copper}c`;
            moneyInfo.appendChild(codDiv);
          }
          
          mailCard.appendChild(moneyInfo);
        }
        
        // Attachments
        if (mail.attachments && mail.attachments.length > 0) {
          const attachHeader = document.createElement('div');
          attachHeader.style.cssText = 'font-size: 12px; color: #64748b; margin-bottom: 8px;';
          attachHeader.textContent = 'Attachments (' + mail.attachments.length + '):';
          mailCard.appendChild(attachHeader);
          
          const attachGrid = document.createElement('div');
          attachGrid.style.cssText = 'display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 8px;';
          
          mail.attachments.forEach(attach => {
            const attachCard = document.createElement('div');
            attachCard.style.cssText = `
              border: 2px solid #cbd5e1;
              border-radius: 6px;
              padding: 8px;
              text-align: center;
              background: #f8fafc;
            `;
            
            const iconSrc = `/icon.php?type=item&id=${attach.item_id}&name=${encodeURIComponent(attach.name)}&size=medium`;
            
            attachCard.innerHTML = `
              <div style="width: 48px; height: 48px; margin: 0 auto 6px; background-image: url(&quot;${iconSrc}&quot;); background-size: cover; border-radius: 4px;"></div>
              <div style="font-size: 11px; color: #475569; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${attach.name}</div>
              <div style="font-size: 10px; color: #64748b; margin-top: 2px;">x${attach.count}</div>
            `;

            // Attach Wowhead tooltip
            if (window.WDTooltip) {
              attachCard.style.cursor = 'default';
              WDTooltip.attach(attachCard, { link: attach.link, item_id: attach.item_id, name: attach.name, icon: attach.icon }, null);
            }
            
            attachGrid.appendChild(attachCard);
          });
          
          mailCard.appendChild(attachGrid);
        }
        
        root.appendChild(mailCard);
      });
    } catch (err) {
      console.error('[items] Error rendering mail:', err);
      root.innerHTML += '<p style="color: red;">Error displaying mail: ' + err.message + '</p>';
    }
  }

  // ===== COMBINED TAB =====
  function renderCombinedTab(root, data) {
    root.innerHTML = '';
    
    const combined = data.combined || [];
    
    if (combined.length === 0) {
      root.innerHTML = '<p class="muted">No items found</p>';
      return;
    }

    const container = document.createElement('div');
    container.className = 'combined-container';
    container.innerHTML = `
      <div class="combined-header">
        <h3>📋 All Items Combined (${combined.length} entries)</h3>
        <input type="text" id="combinedSearch" placeholder="🔍 Search items..." 
               style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 300px;">
      </div>
      <div class="combined-table-wrapper">
        <table class="combined-table">
          <thead>
            <tr>
            <th data-sort="name">Item Name <span class="sort-arrow">⇅</span></th>
            <th data-sort="count" class="center">Count <span class="sort-arrow">⇅</span></th>
            <th data-sort="ilvl" class="center">iLvl <span class="sort-arrow">⇅</span></th>
            <th data-sort="quality_name">Quality <span class="sort-arrow">⇅</span></th>
            <th data-sort="location">Location <span class="sort-arrow">⇅</span></th>
            </tr>
          </thead>
          <tbody id="combinedTableBody">
          </tbody>
        </table>
      </div>
    `;
    
    root.appendChild(container);
    
    // Initialize sortable/searchable table
    initCombinedTable(combined);
  }

  function initCombinedTable(data) {
    let currentSort = { column: 'name', direction: 'asc' };
    let filteredData = [...data];

    function renderTable() {
      const tbody = q('#combinedTableBody');
      if (!tbody) return;

      // Sort
      const sorted = [...filteredData].sort((a, b) => {
        let aVal = a[currentSort.column];
        let bVal = b[currentSort.column];

        if (aVal === null || aVal === undefined) aVal = '';
        if (bVal === null || bVal === undefined) bVal = '';

        if (typeof aVal === 'number' && typeof bVal === 'number') {
          return currentSort.direction === 'asc' ? aVal - bVal : bVal - aVal;
        }

        const aStr = String(aVal).toLowerCase();
        const bStr = String(bVal).toLowerCase();
        if (currentSort.direction === 'asc') {
          return aStr < bStr ? -1 : aStr > bStr ? 1 : 0;
        } else {
          return bStr < aStr ? -1 : bStr > aStr ? 1 : 0;
        }
      });

      // Render
      tbody.innerHTML = '';
      sorted.forEach(item => {
        const tr = document.createElement('tr');
        const qualityColor = getQualityColor(item.quality_name);
        tr.innerHTML = `
          <td><strong style="color: ${qualityColor};">${item.name}</strong></td>
          <td class="center"><strong>${item.count}</strong></td>
          <td class="center"><strong>${item.ilvl || '—'}</strong></td>
          <td><strong style="color: ${qualityColor};">${item.quality_name}</strong></td>
          <td><strong>${item.location}</strong></td>
        `;
        tbody.appendChild(tr);
      });

      // Update sort arrows
      document.querySelectorAll('.combined-table th[data-sort]').forEach(th => {
        const arrow = th.querySelector('.sort-arrow');
        if (arrow) {
          if (th.dataset.sort === currentSort.column) {
            arrow.textContent = currentSort.direction === 'asc' ? '↑' : '↓';
            th.classList.add('sorted');
          } else {
            arrow.textContent = '⇅';
            th.classList.remove('sorted');
          }
        }
      });
    }

    // Search
    const searchInput = q('#combinedSearch');
    if (searchInput) {
      searchInput.addEventListener('input', (e) => {
        const term = e.target.value.toLowerCase();
        filteredData = data.filter(d => d.name.toLowerCase().includes(term));
        renderTable();
      });
    }

    // Sort
    document.querySelectorAll('.combined-table th[data-sort]').forEach(th => {
      th.addEventListener('click', () => {
        const column = th.dataset.sort;
        if (currentSort.column === column) {
          currentSort.direction = currentSort.direction === 'asc' ? 'desc' : 'asc';
        } else {
          currentSort.column = column;
          currentSort.direction = 'asc';
        }
        renderTable();
      });
    });

    renderTable();
  }

  // ===== TAB INITIALIZATION =====
  function initTabs() {
    const root = q('#tab-items');
    if (!root) return;

    const tabContainer = document.createElement('div');
    tabContainer.className = 'items-tabs';
    tabContainer.innerHTML = `
<button class="items-tab active" data-tab="vault">🏆 Vault</button>
<button class="items-tab" data-tab="bags">🎒 Bags</button>
<button class="items-tab" data-tab="bank">🏦 Bank</button>
<button class="items-tab" data-tab="mail">📬 Mail</button>
<button class="items-tab" data-tab="combined">📋 Combined</button>
    `;

    const vaultTab = document.createElement('div');
    vaultTab.className = 'items-tab-content active';
    vaultTab.id = 'vault-tab';

    const bagsTab = document.createElement('div');
    bagsTab.className = 'items-tab-content';
    bagsTab.id = 'bags-tab';

    const bankTab = document.createElement('div');
    bankTab.className = 'items-tab-content';
    bankTab.id = 'bank-tab';

    const mailTab = document.createElement('div');
    mailTab.className = 'items-tab-content';
    mailTab.id = 'mail-tab';

    const combinedTab = document.createElement('div');
    combinedTab.className = 'items-tab-content';
    combinedTab.id = 'combined-tab';

    root.innerHTML = '';
    root.appendChild(tabContainer);
    root.appendChild(vaultTab);
    root.appendChild(bagsTab);
    root.appendChild(bankTab);
    root.appendChild(mailTab);
    root.appendChild(combinedTab);

    tabContainer.querySelectorAll('.items-tab').forEach(btn => {
      btn.addEventListener('click', () => {
        tabContainer.querySelectorAll('.items-tab').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const targetTab = btn.dataset.tab;
        q('#vault-tab').classList.toggle('active', targetTab === 'vault');
        q('#bags-tab').classList.toggle('active', targetTab === 'bags');
        q('#bank-tab').classList.toggle('active', targetTab === 'bank');
        q('#mail-tab').classList.toggle('active', targetTab === 'mail');
        q('#combined-tab').classList.toggle('active', targetTab === 'combined');
      });
    });

    return { vaultTab, bagsTab, bankTab, mailTab, combinedTab };
  }

  // ===== MAIN RENDERER =====
  function renderItemsPage(data, characterId) {
    const tabs = initTabs();
    if (!tabs) return;

    const { vaultTab, bagsTab, bankTab, mailTab, combinedTab } = tabs;

    renderVaultTab(vaultTab, data);
    renderBagsTab(bagsTab, data, characterId);
    renderBankTab(bankTab, data, characterId);
    renderMailTab(mailTab, data, characterId);
    renderCombinedTab(combinedTab, data);
  }

  // ===== DATA LOADING =====
  async function loadItemsPage() {
    const root = q('#tab-items');
    if (!root) {
      log('ERROR: #tab-items not found in DOM');
      return;
    }

    const cid = root.dataset?.characterId;
    log('Loading items for character:', cid);

    if (!cid) {
      root.innerHTML = '<p class="muted">No character selected</p>';
      return;
    }

    
root.innerHTML = `
  <div class="muted" style="text-align: center; padding: 40px 0;">
    <div style="font-size: 2rem; margin-bottom: 16px;">⏳</div>
    <div>Loading inventory...</div>
  </div>
`;


    try {
      const url = `/sections/items-data.php?character_id=${encodeURIComponent(cid)}`;
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
      
      renderItemsPage(data, cid);
    } catch (err) {
      log('Failed to load items data:', err);
      root.innerHTML = `<p style="color:#d32f2f;">Failed to load items data: ${err.message}</p>`;
    }
  }

  // ===== EVENT LISTENERS =====
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'items') {
      loadItemsPage();
    }
  });

  document.addEventListener('whodat:character-changed', () => {
    const currentSection = history?.state?.section || '';
    if (currentSection === 'items') {
      loadItemsPage();
    }
  });

  if (q('#tab-items')) {
    log('Found #tab-items on page load, loading now...');
    loadItemsPage();
  }
})();