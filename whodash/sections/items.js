/* eslint-disable no-console */
/* WhoDASH Items — Inventory, Bags, Bank, Mail */
(() => {
  // ===== Helpers =====
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[items]', ...a);

  // Quality color mapping - DARKER versions for visibility on light backgrounds
  const QUALITY_COLORS = {
    'Poor': '#6b7280',      // Dark gray (instead of light gray)
    'Common': '#111827',    // Nearly black (instead of white)
    'Uncommon': '#16a34a',  // Dark green (instead of bright green)
    'Rare': '#0070dd',      // Blue (unchanged - already good)
    'Epic': '#9333ea',      // Dark purple (instead of light purple)
    'Legendary': '#ea580c', // Dark orange (instead of bright orange)
  };

  function getQualityColor(quality) {
    return QUALITY_COLORS[quality] || '#111827';
  }

  // ===== VAULT TAB =====
  function renderVaultTab(root, data) {
    root.innerHTML = '';

    const stats = data.vault_stats || {};

    // Top section - Overview stats
    const topSection = document.createElement('div');
    topSection.className = 'vault-overview';
    topSection.innerHTML = `
      <div class="vault-stat-card">
        <div class="vault-stat-icon">📦</div>
        <div class="vault-stat-content">
          <div class="vault-stat-label">Total Items</div>
          <div class="vault-stat-value">${stats.total_items || 0}</div>
        </div>
      </div>
      <div class="vault-stat-card">
        <div class="vault-stat-icon">✨</div>
        <div class="vault-stat-content">
          <div class="vault-stat-label">Unique Items</div>
          <div class="vault-stat-value">${stats.unique_items || 0}</div>
        </div>
      </div>
      <div class="vault-stat-card">
        <div class="vault-stat-icon">💎</div>
        <div class="vault-stat-content">
          <div class="vault-stat-label">Rare+ Items</div>
          <div class="vault-stat-value">${stats.rarest_items?.length || 0}</div>
        </div>
      </div>
    `;
    root.appendChild(topSection);

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
    rareSection.innerHTML = '<h3>🌟 Rarest Items (Epic & Legendary)</h3>';
    
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
  function renderBagsTab(root, data) {
    root.innerHTML = '';

    const bags = data.bags || [];
    
    if (bags.length === 0) {
      root.innerHTML = '<p class="muted">No bag items found</p>';
      return;
    }

    // Combine ALL bags into one container
    const bagSection = document.createElement('div');
    bagSection.className = 'bag-container';
    bagSection.innerHTML = `<h3>🎒 All Bags</h3>`;
    
    const itemsGrid = document.createElement('div');
    itemsGrid.className = 'items-grid';
    
    // Sort by bag and slot for organized display
    const sortedBags = [...bags].sort((a, b) => {
      if (a.bag_id !== b.bag_id) return a.bag_id - b.bag_id;
      return a.slot - b.slot;
    });
    
    sortedBags.forEach(item => {
      const itemCard = document.createElement('div');
      itemCard.className = 'item-card';
      itemCard.style.borderColor = getQualityColor(item.quality_name);
      
      // Use icon.php for icon loading
      const iconUrl = `/icon.php?type=item&id=${item.item_id}&name=${encodeURIComponent(item.name)}&size=large`;
      
      itemCard.innerHTML = `
        <div class="item-icon" style="background-image: url('${iconUrl}')"></div>
        <div class="item-name-dark" style="color: ${getQualityColor(item.quality_name)}">${item.name}</div>
        <div class="item-count-dark">x${item.count}</div>
        ${item.ilvl > 0 ? `<div class="item-ilvl-dark">iLvl ${item.ilvl}</div>` : ''}
      `;
      itemsGrid.appendChild(itemCard);
    });
    
    bagSection.appendChild(itemsGrid);
    root.appendChild(bagSection);
  }

  // ===== BANK TAB =====
  function renderBankTab(root, data) {
    root.innerHTML = '';

    const bank = data.bank || [];
    
    if (bank.length === 0) {
      root.innerHTML = '<p class="muted">No bank items found</p>';
      return;
    }

    // Filter out bank bag items themselves (they show as "Bank Bag 1", etc)
    const bankItems = bank.filter(item => !item.name.startsWith('Bank Bag'));
    
    if (bankItems.length === 0) {
      root.innerHTML = '<p class="muted">No items in bank (only empty bank bags)</p>';
      return;
    }

    const bankSection = document.createElement('div');
    bankSection.className = 'bag-container';
    bankSection.innerHTML = `<h3>🏦 Bank</h3>`;
    
    const itemsGrid = document.createElement('div');
    itemsGrid.className = 'items-grid';
    
    bankItems.forEach(item => {
      const itemCard = document.createElement('div');
      itemCard.className = 'item-card';
      itemCard.style.borderColor = getQualityColor(item.quality_name);
      
      // Use icon.php for icon loading
      const iconUrl = `/icon.php?type=item&id=${item.item_id}&name=${encodeURIComponent(item.name)}&size=large`;
      
      itemCard.innerHTML = `
        <div class="item-icon" style="background-image: url('${iconUrl}')"></div>
        <div class="item-name-dark" style="color: ${getQualityColor(item.quality_name)}">${item.name}</div>
        <div class="item-count-dark">x${item.count}</div>
        ${item.ilvl > 0 ? `<div class="item-ilvl-dark">iLvl ${item.ilvl}</div>` : ''}
      `;
      itemsGrid.appendChild(itemCard);
    });
    
    bankSection.appendChild(itemsGrid);
    root.appendChild(bankSection);
  }

  // ===== MAIL TAB =====
  function renderMailTab(root, data) {
    root.innerHTML = '';

    const mail = data.mail || [];
    
    if (mail.length === 0) {
      root.innerHTML = '<p class="muted">No mail attachments found. This may be correct if your mailbox is empty.</p>';
      return;
    }

    const mailSection = document.createElement('div');
    mailSection.className = 'bag-container';
    mailSection.innerHTML = `<h3>📬 Mailbox Attachments</h3>`;
    
    const itemsGrid = document.createElement('div');
    itemsGrid.className = 'items-grid';
    
    mail.forEach(item => {
      const itemCard = document.createElement('div');
      itemCard.className = 'item-card';
      itemCard.style.borderColor = getQualityColor(item.quality_name);
      
      // Use icon.php for icon loading
      const iconUrl = `/icon.php?type=item&id=${item.item_id}&name=${encodeURIComponent(item.name)}&size=large`;
      
      itemCard.innerHTML = `
        <div class="item-icon" style="background-image: url('${iconUrl}')"></div>
        <div class="item-name-dark" style="color: ${getQualityColor(item.quality_name)}">${item.name}</div>
        <div class="item-count-dark">x${item.count}</div>
        ${item.sender ? `<div class="item-sender-dark">From: ${item.sender}</div>` : ''}
      `;
      itemsGrid.appendChild(itemCard);
    });
    
    mailSection.appendChild(itemsGrid);
    root.appendChild(mailSection);
  }

  // ===== COMBINED TAB (Searchable Table) =====
  function renderCombinedTab(root, data) {
    root.innerHTML = '';

    const combined = data.combined || [];
    
    const container = document.createElement('div');
    container.className = 'combined-container';
    container.innerHTML = `
      <h3>🔍 All Items</h3>
      <div class="combined-controls">
        <input type="text" id="combinedSearch" class="combined-search" placeholder="Search items...">
        <span class="combined-count">Total: <strong>${combined.length}</strong> items</span>
      </div>
      <div class="table-container">
        <table class="combined-table" id="combinedTable">
          <thead>
            <tr>
              <th data-sort="name">Item Name <span class="sort-arrow">↕</span></th>
              <th data-sort="count">Count <span class="sort-arrow">↕</span></th>
              <th data-sort="ilvl">iLvl <span class="sort-arrow">↕</span></th>
              <th data-sort="quality_name">Quality <span class="sort-arrow">↕</span></th>
              <th data-sort="location">Location <span class="sort-arrow">↕</span></th>
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
            arrow.textContent = '↕';
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
  function renderItemsPage(data) {
    const tabs = initTabs();
    if (!tabs) return;

    const { vaultTab, bagsTab, bankTab, mailTab, combinedTab } = tabs;

    renderVaultTab(vaultTab, data);
    renderBagsTab(bagsTab, data);
    renderBankTab(bankTab, data);
    renderMailTab(mailTab, data);
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

    root.innerHTML = '<div class="muted" style="text-align: center; padding: 40px 0;"><div style="font-size: 2rem; margin-bottom: 16px;">⏳</div><div>Loading inventory...</div></div>';

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
      
      renderItemsPage(data);
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