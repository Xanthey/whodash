/* eslint-disable no-console */
/* WhoDASH Professions — Skills & Tradeskills Tracker */
(() => {
  const q = (sel, root = document) => root.querySelector(sel);
  const qa = (sel, root = document) => Array.from(root.querySelectorAll(sel));
  const log = (...a) => console.log('[professions]', ...a);

  // ===== Tab System =====
  function setupTabs(container) {
    const tabs = qa('.prof-tab', container);
    const contents = qa('.prof-tab-content', container);

    tabs.forEach(tab => {
      tab.addEventListener('click', () => {
        const target = tab.dataset.tab;
        
        tabs.forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        
        contents.forEach(c => c.classList.remove('active'));
        const targetContent = q(`#prof-${target}`, container);
        if (targetContent) targetContent.classList.add('active');
      });
    });
  }

  // ===== Overview Tab =====
  function renderOverview(data) {
    const container = document.createElement('div');
    container.id = 'prof-overview';
    container.className = 'prof-tab-content active';

    // Stats Grid
    const statsGrid = document.createElement('div');
    statsGrid.className = 'prof-stats-grid';
    statsGrid.innerHTML = `
      <div class="prof-stat-card">
        <div class="stat-icon">🛠️</div>
        <div class="stat-value">${data.total_professions || 0}</div>
        <div class="stat-label">Primary Professions</div>
      </div>
      
      <div class="prof-stat-card">
        <div class="stat-icon">📚</div>
        <div class="stat-value">${(data.total_recipes || 0).toLocaleString()}</div>
        <div class="stat-label">Recipes Known</div>
      </div>
      
      <div class="prof-stat-card">
        <div class="stat-icon">🎯</div>
        <div class="stat-value">${data.highest_skill?.rank || 0}</div>
        <div class="stat-label">Highest Skill</div>
        <div class="stat-sublabel">${data.highest_skill?.name || 'N/A'}</div>
      </div>
    `;
    container.appendChild(statsGrid);

    // Primary Professions
    if (data.primary_professions && data.primary_professions.length > 0) {
      const profSection = document.createElement('div');
      profSection.className = 'professions-section';
      
      const title = document.createElement('h3');
      title.textContent = '🛠️ Primary Professions';
      profSection.appendChild(title);

      const profGrid = document.createElement('div');
      profGrid.className = 'profession-cards';
      
      data.primary_professions.forEach(prof => {
        const progress = prof.max_rank > 0 ? (prof.rank / prof.max_rank * 100) : 0;
        const card = document.createElement('div');
        card.className = 'profession-card';
        card.innerHTML = `
          <div class="profession-header">
            <div class="profession-name">${prof.name}</div>
            <div class="profession-rank">${prof.rank}/${prof.max_rank}</div>
          </div>
          <div class="profession-progress-bar">
            <div class="profession-progress-fill" style="width: ${progress}%"></div>
          </div>
          <div class="profession-stats">
            📖 ${prof.recipes_known || 0} recipes known
          </div>
        `;
        profGrid.appendChild(card);
      });
      
      profSection.appendChild(profGrid);
      container.appendChild(profSection);
    }

    // Secondary Skills
    if (data.secondary_skills && data.secondary_skills.length > 0) {
      const secondarySection = document.createElement('div');
      secondarySection.className = 'secondary-section';
      
      const title = document.createElement('h3');
      title.textContent = '📖 Secondary Skills';
      secondarySection.appendChild(title);

      const secondaryGrid = document.createElement('div');
      secondaryGrid.className = 'secondary-cards';
      
      data.secondary_skills.forEach(skill => {
        const progress = skill.max_rank > 0 ? (skill.rank / skill.max_rank * 100) : 0;
        const card = document.createElement('div');
        card.className = 'secondary-card';
        card.innerHTML = `
          <div class="secondary-name">${skill.name}</div>
          <div class="secondary-progress">
            <div class="secondary-progress-bar">
              <div class="secondary-progress-fill" style="width: ${progress}%"></div>
            </div>
            <div class="secondary-rank">${skill.rank}/${skill.max_rank}</div>
          </div>
        `;
        secondaryGrid.appendChild(card);
      });
      
      secondarySection.appendChild(secondaryGrid);
      container.appendChild(secondarySection);
    }

    // Recipe Counts by Profession
    if (data.recipe_counts_by_profession && data.recipe_counts_by_profession.length > 0) {
      const recipeSection = document.createElement('div');
      recipeSection.className = 'recipe-counts-section';
      
      const title = document.createElement('h3');
      title.textContent = '📊 Recipe Collection';
      recipeSection.appendChild(title);

      const table = document.createElement('table');
      table.className = 'recipe-counts-table';
      table.innerHTML = `
        <thead>
          <tr>
            <th>Profession</th>
            <th>Total Recipes</th>
            <th>Rare</th>
            <th>Epic</th>
          </tr>
        </thead>
        <tbody>
          ${data.recipe_counts_by_profession.map(prof => `
            <tr>
              <td><strong>${prof.profession || 'Unknown'}</strong></td>
              <td>${prof.recipe_count || 0}</td>
              <td>${prof.rare_recipes || 0}</td>
              <td>${prof.epic_recipes || 0}</td>
            </tr>
          `).join('')}
        </tbody>
      `;
      
      recipeSection.appendChild(table);
      container.appendChild(recipeSection);
    }

    return container;
  }

  // ===== Recipes Tab =====
  function renderRecipes(data) {
    const container = document.createElement('div');
    container.id = 'prof-recipes';
    container.className = 'prof-tab-content';

    const header = document.createElement('div');
    header.className = 'recipes-header';
    header.innerHTML = `
      <h3>📚 Recipe Collection</h3>
      <div class="recipe-filters">
        <input type="text" id="recipeSearchInput" class="recipe-search-input" placeholder="Search recipes...">
        <select id="professionFilter" class="profession-filter">
          <option value="">All Professions</option>
        </select>
        <button id="clearRecipeFilters" class="clear-filters-btn">Clear</button>
      </div>
    `;
    container.appendChild(header);

    // Populate profession filter
    const professionSet = new Set();
    data.all_recipes.forEach(r => {
      if (r.profession) professionSet.add(r.profession);
    });
    
    setTimeout(() => {
      const profFilter = q('#professionFilter', container);
      if (profFilter) {
        Array.from(professionSet).sort().forEach(prof => {
          const option = document.createElement('option');
          option.value = prof;
          option.textContent = prof;
          profFilter.appendChild(option);
        });
      }
    }, 0);

    if (!data.all_recipes || data.all_recipes.length === 0) {
      const msg = document.createElement('p');
      msg.className = 'muted';
      msg.textContent = 'No recipes learned yet';
      container.appendChild(msg);
      return container;
    }

    // Recipe cards container
    const recipeCardsContainer = document.createElement('div');
    recipeCardsContainer.className = 'recipe-cards-container';
    recipeCardsContainer.id = 'recipeCardsContainer';
    container.appendChild(recipeCardsContainer);

    const paginationInfo = document.createElement('div');
    paginationInfo.className = 'pagination-info';
    paginationInfo.id = 'recipesPaginationInfo';
    container.appendChild(paginationInfo);

    // Notable Recipes Sections
    const notableSection = document.createElement('div');
    notableSection.className = 'notable-recipes-section';

    // Rare/Epic Recipes
    if (data.rare_recipes && data.rare_recipes.length > 0) {
      const rareCard = document.createElement('div');
      rareCard.className = 'notable-recipe-card';
      rareCard.innerHTML = `
        <h4>✨ Rare & Epic Recipes</h4>
        <div class="notable-recipe-list">
          ${data.rare_recipes.map(r => `
            <div class="notable-recipe-item">
              <span class="recipe-name">${r.name}</span>
              <span class="recipe-prof">${r.profession}</span>
            </div>
          `).join('')}
        </div>
      `;
      notableSection.appendChild(rareCard);
    }

    // Cooldown Recipes
    if (data.cooldown_recipes && data.cooldown_recipes.length > 0) {
      const cooldownCard = document.createElement('div');
      cooldownCard.className = 'notable-recipe-card';
      cooldownCard.innerHTML = `
        <h4>⏰ Recipes with Cooldowns</h4>
        <div class="notable-recipe-list">
          ${data.cooldown_recipes.map(r => `
            <div class="notable-recipe-item">
              <span class="recipe-name">${r.name}</span>
              <span class="recipe-cooldown">${r.cooldown_text || 'Cooldown'}</span>
            </div>
          `).join('')}
        </div>
      `;
      notableSection.appendChild(cooldownCard);
    }

    container.appendChild(notableSection);

    // Store data for filtering
    container.dataset.recipeData = JSON.stringify(data.all_recipes);

    // Initial render
    setTimeout(() => filterAndRenderRecipes(data.all_recipes, container), 0);

    // Setup filters
    setTimeout(() => {
      const searchInput = q('#recipeSearchInput', container);
      const profFilter = q('#professionFilter', container);
      const clearBtn = q('#clearRecipeFilters', container);

      const applyFilters = () => {
        const allData = JSON.parse(container.dataset.recipeData);
        filterAndRenderRecipes(allData, container);
      };

      if (searchInput) searchInput.addEventListener('input', applyFilters);
      if (profFilter) profFilter.addEventListener('change', applyFilters);
      if (clearBtn) {
        clearBtn.addEventListener('click', () => {
          if (searchInput) searchInput.value = '';
          if (profFilter) profFilter.value = '';
          applyFilters();
        });
      }
    }, 0);

    return container;
  }

  function filterAndRenderRecipes(allData, container) {
    const searchInput = q('#recipeSearchInput', container);
    const profFilter = q('#professionFilter', container);
    const cardsContainer = q('#recipeCardsContainer', container);
    const paginationInfo = q('#recipesPaginationInfo', container);

    if (!cardsContainer) return;

    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
    const selectedProf = profFilter ? profFilter.value : '';

    const filtered = allData.filter(recipe => {
      const matchesSearch = !searchTerm || 
        (recipe.name && recipe.name.toLowerCase().includes(searchTerm));
      
      const matchesProf = !selectedProf || recipe.profession === selectedProf;

      return matchesSearch && matchesProf;
    });

    // Render recipe cards
    cardsContainer.innerHTML = '';
    const grid = document.createElement('div');
    grid.className = 'recipe-grid';
    
    filtered.forEach(recipe => {
      const card = document.createElement('div');
      card.className = 'recipe-card';
      card.innerHTML = `
        <div class="recipe-card-name">${recipe.name || 'Unknown Recipe'}</div>
        <div class="recipe-card-profession">${recipe.profession || 'Unknown'}</div>
        ${recipe.type ? `<div class="recipe-card-type">${recipe.type}</div>` : ''}
        ${recipe.cooldown_text ? `<div class="recipe-card-cooldown">⏰ ${recipe.cooldown_text}</div>` : ''}
      `;
      grid.appendChild(card);
    });
    
    cardsContainer.appendChild(grid);

    if (paginationInfo) {
      paginationInfo.textContent = `Showing ${filtered.length.toLocaleString()} of ${allData.length.toLocaleString()} recipes`;
    }
  }

  // ===== Main Render =====
  async function initProfessions() {
    const section = q('#tab-professions');
    if (!section) {
      log('Section not found');
      return;
    }

    const characterId = section.dataset.characterId;
    if (!characterId) {
      section.innerHTML = '<div class="muted">No character selected</div>';
      return;
    }

    log('Loading profession data for character', characterId);

    try {
      const response = await fetch(`/sections/professions-data.php?character_id=${characterId}`, {
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
      log('Profession data loaded:', data);

      const container = document.createElement('div');
      container.className = 'prof-container';

      // Tab Navigation
      const tabNav = document.createElement('div');
      tabNav.className = 'prof-tabs';
      tabNav.innerHTML = `
        <button class="prof-tab active" data-tab="overview">📊 Overview</button>
        <button class="prof-tab" data-tab="recipes">📚 Recipes</button>
      `;
      container.appendChild(tabNav);

      // Tab Contents
      const contentWrapper = document.createElement('div');
      contentWrapper.className = 'prof-content-wrapper';
      
      contentWrapper.appendChild(renderOverview(data));
      contentWrapper.appendChild(renderRecipes(data));
      
      container.appendChild(contentWrapper);

      section.innerHTML = '';
      section.appendChild(container);

      setupTabs(section);

    } catch (error) {
      log('Error loading profession data:', error);
      section.innerHTML = `<div class="muted">Error loading profession data: ${error.message}</div>`;
    }
  }

  // ===== Auto-init =====
  document.addEventListener('whodat:section-loaded', (event) => {
    if (event?.detail?.section === 'professions') {
      log('Section loaded event received');
      initProfessions();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      if (q('#tab-professions')) initProfessions();
    });
  } else {
    if (q('#tab-professions')) initProfessions();
  }

  log('Professions module loaded');
})();