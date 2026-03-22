/* tooltip-engine.js — WhoDASH Shared Tooltip Engine
 * Extracted from sections_professions.js and promoted to a shared global.
 *
 * Usage:
 *   WDTooltip.attach(el, { link, name, icon }, characterId)
 *     link       — WoW hyperlink string, e.g. "|Hitem:30422:...|" or "|Henchant:7454|"
 *                  OR null/undefined if only item_id is known
 *     name       — fallback display name
 *     icon       — fallback icon (raw WoW path or short name)
 *     item_id    — numeric item id (used when link is absent)
 *     item_type  — 'item'|'enchant'|'spell' (default 'item', used with item_id)
 *   WDTooltip.parseLinkInfo(link)  → { type, id } | null
 *
 * IMPORTANT: Name quality colors use inline style="color:#HEX", never CSS class,
 * because global .q-rare { background: #0070dd } etc. paint entire divs as solid blocks.
 */
(function (global) {
  'use strict';

  // =========================================================================
  // Icon helpers
  // =========================================================================

  function cleanIconName(raw) {
    if (!raw) return null;
    const parts = raw.replace(/\\/g, '/').split('/');
    const name = parts[parts.length - 1].trim().toLowerCase();
    return name || null;
  }

  function iconUrl(rawIcon, size) {
    size = size || 'small';
    const name = cleanIconName(rawIcon) || 'inv_misc_questionmark';
    return 'https://wow.zamimg.com/images/wow/icons/' + size + '/' + name + '.jpg';
  }

  // =========================================================================
  // Escape helper
  // =========================================================================

  function esc(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  // =========================================================================
  // Quality color map
  // MUST use inline style — global .q-rare { background: #0070dd } paints full divs.
  // =========================================================================

  const QUALITY_COLORS = {
    'q-poor':      '#9d9d9d',
    'q-common':    '#ffffff',
    'q-uncommon':  '#1eff00',
    'q-rare':      '#0070dd',
    'q-epic':      '#a335ee',
    'q-legendary': '#ff8000',
    'q-artifact':  '#e6cc80',
    'q-heirloom':  '#00ccff',
  };

  // =========================================================================
  // Tooltip DOM element (single floating instance)
  // =========================================================================

  let _tooltipEl       = null;
  let _tooltipHovering = false;
  let _tooltipTimeout  = null;
  const _tooltipCache  = {}; // key → resolved data OR in-flight Promise

  function getTooltipEl() {
    if (!_tooltipEl) {
      _tooltipEl = document.createElement('div');
      _tooltipEl.className = 'wd-tooltip';
      _tooltipEl.style.display = 'none';
      document.body.appendChild(_tooltipEl);
    }
    return _tooltipEl;
  }

  function positionTooltip(e) {
    const el  = getTooltipEl();
    const pad = 16;
    const tw  = el.offsetWidth  || 280;
    const th  = el.offsetHeight || 80;
    let x = e.clientX + pad;
    let y = e.clientY + pad;
    if (x + tw + 4 > window.innerWidth)  x = e.clientX - tw - pad;
    if (y + th + 4 > window.innerHeight) y = e.clientY - th - pad;
    el.style.left = Math.max(4, x) + 'px';
    el.style.top  = Math.max(4, y) + 'px';
  }

  function hideTooltip() {
    clearTimeout(_tooltipTimeout);
    if (_tooltipHovering) return;
    const el = getTooltipEl();
    el.style.display = 'none';
    el.innerHTML = '';
  }

  // =========================================================================
  // Render tooltip HTML from tooltip.php response JSON
  // =========================================================================

  function renderTooltipHtml(data, fallbackName) {
    const qualityColor = QUALITY_COLORS[data.quality_class] || '#ffffff';
    const iconName     = data.icon ? data.icon.toLowerCase() : null;
    const iconHtml     = iconName
      ? '<img class="wd-tooltip-icon" src="' + iconUrl(iconName, 'medium') + '" alt="" onerror="this.style.display=\'none\'">'
      : '';

    // Stat lines (PHP already parsed tooltip_enus into clean text)
    const stats   = data.stats || [];
    const statHtml = stats.map(function (line) {
      if (/^\+\d/.test(line) || /^-\d/.test(line))               return '<div class="wd-tooltip-stat wd-tooltip-stat--bonus">' + esc(line) + '</div>';
      if (/^[\u201c\u201d""]/.test(line))                         return '<div class="wd-tooltip-stat wd-tooltip-stat--flavor">' + esc(line) + '</div>';
      if (/^Requires/i.test(line))                                return '<div class="wd-tooltip-stat wd-tooltip-stat--req">'    + esc(line) + '</div>';
      if (/^(Use|Equip|Chance|Cooldown):/i.test(line))            return '<div class="wd-tooltip-stat wd-tooltip-stat--use">'    + esc(line) + '</div>';
      if (/^Sell Price:/i.test(line))                             return '<div class="wd-tooltip-stat wd-tooltip-stat--sell">'   + esc(line) + '</div>';
      return '<div class="wd-tooltip-stat">' + esc(line) + '</div>';
    }).join('');
    const bodyHtml = statHtml ? '<div class="wd-tooltip-stats">' + statHtml + '</div>' : '';

    // Reagents section
    let reagentHtml = '';
    if (data.reagents && data.reagents.length > 0) {
      const rows = data.reagents.map(function (r) {
        // Try direct icon URL first, fall back to icon.php if icon name is missing
        let iconEl;
        if (r.icon) {
          iconEl = '<img src="' + iconUrl(r.icon, 'small') + '" class="wd-tooltip-reagent-icon" alt="" onerror="this.style.display=\'none\'">';
        } else if (r.id) {
          // Fallback: use icon.php to fetch icon by item ID
          iconEl = '<img src="/icon.php?type=item&id=' + r.id + '&size=small" class="wd-tooltip-reagent-icon" alt="" onerror="this.style.display=\'none\'">';
        } else {
          iconEl = '<span class="wd-tooltip-reagent-icon-placeholder"></span>';
        }
        const nameEl  = esc(r.name || 'Item #' + r.id);
        const haveQty = (r.have !== null && r.have !== undefined) ? r.have : null;
        let availEl = '';
        if (haveQty !== null) {
          if (r.available) {
            availEl = '<span class="wd-reagent-have wd-reagent-have--ok" title="' + haveQty + ' in bags/bank">✓ ' + haveQty + '</span>';
          } else {
            availEl = '<span class="wd-reagent-have wd-reagent-have--low" title="' + haveQty + ' of ' + r.qty + ' needed">' + haveQty + '/' + r.qty + '</span>';
          }
        }
        return '<div class="wd-tooltip-reagent">' + iconEl + '<span class="wd-reagent-qty">' + esc(r.qty) + 'x</span><span class="wd-reagent-name">' + nameEl + '</span>' + availEl + '</div>';
      }).join('');
      reagentHtml = '<div class="wd-tooltip-reagents"><div class="wd-tooltip-reagent-label">Reagents</div>' + rows + '</div>';
    }

    // Reagent For section
    let reagentForHtml = '';
    if (data.reagent_for && data.reagent_for.length > 0) {
      const rows = data.reagent_for.map(function (rf) {
        const nameEl = esc(rf.name || 'Spell #' + rf.spell_id);
        const levelEl = rf.skill_level ? ' (' + rf.skill_level + ')' : '';
        return '<div class="wd-tooltip-reagent-for-item">' + nameEl + levelEl + '</div>';
      }).join('');
      reagentForHtml = '<div class="wd-tooltip-reagent-for"><div class="wd-tooltip-reagent-for-label">Reagent for:</div>' + rows + '</div>';
    }

    // Dropped By section
    let droppedByHtml = '';
    if (data.dropped_by && data.dropped_by.length > 0) {
      const rows = data.dropped_by.map(function (db) {
        const nameEl = esc(db.name || 'NPC #' + db.npc_id);
        const chanceEl = db.drop_chance ? ' (' + db.drop_chance.toFixed(2) + '%)' : '';
        return '<div class="wd-tooltip-dropped-by-item">' + nameEl + chanceEl + '</div>';
      }).join('');
      droppedByHtml = '<div class="wd-tooltip-dropped-by"><div class="wd-tooltip-dropped-by-label">Dropped by:</div>' + rows + '</div>';
    }

    const link = data.id
      ? '<div class="wd-tooltip-item-id">Item ID: ' + data.id + '</div>'
      : '';

    return (
      '<div class="wd-tooltip-header">' +
        iconHtml +
        '<div class="wd-tooltip-name" style="color:' + qualityColor + '">' + esc(data.name || fallbackName) + '</div>' +
      '</div>' +
      bodyHtml +
      reagentHtml +
      reagentForHtml +
      droppedByHtml +
      (link ? '<div class="wd-tooltip-footer">' + link + '</div>' : '')
    );
  }

  // =========================================================================
  // Parse WoW hyperlink → { type, id }
  // =========================================================================

  function parseLinkInfo(link) {
    if (!link) return null;
    const m = link.match(/H(item|enchant|spell):(\d+)/);
    if (!m) return null;
    return { type: m[1], id: parseInt(m[2], 10) };
  }

  // =========================================================================
  // Fetch with dedup + in-memory cache
  // =========================================================================

  function fetchTooltip(linkInfo, characterId) {
    if (!linkInfo) return Promise.resolve(null);

    // When characterId is present we bypass cache (availability must be fresh)
    const key = linkInfo.type + '_' + linkInfo.id;
    if (!characterId && key in _tooltipCache) return Promise.resolve(_tooltipCache[key]);

    const params = new URLSearchParams({ type: linkInfo.type, id: String(linkInfo.id) });
    if (characterId) params.set('character_id', String(characterId));

    const p = fetch('/tooltip.php?' + params, { credentials: 'include' })
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!characterId) _tooltipCache[key] = data;
        return data;
      })
      .catch(function (err) {
        if (!characterId) delete _tooltipCache[key];
        throw err;
      });

    if (!characterId) _tooltipCache[key] = p;
    return p;
  }

  // =========================================================================
  // Attach tooltip to a DOM element
  //
  // itemData: {
  //   link      — WoW hyperlink string (preferred)
  //   item_id   — numeric item id (fallback when no link)
  //   item_type — 'item'|'enchant'|'spell' (default: 'item', used with item_id)
  //   name      — display name / fallback
  //   icon      — raw icon path (optional, used only in error fallback)
  // }
  // characterId — numeric string or null
  // =========================================================================

  function attach(el, itemData, characterId) {
    // Resolve linkInfo once — prefer parsed link, fall back to explicit item_id
    let linkInfo = parseLinkInfo(itemData.link);
    if (!linkInfo && itemData.item_id) {
      linkInfo = { type: itemData.item_type || 'item', id: parseInt(itemData.item_id, 10) };
    }

    const fallbackName = itemData.name || '';

    el.addEventListener('mouseenter', function (e) {
      _tooltipHovering = true;
      clearTimeout(_tooltipTimeout);

      const tooltip = getTooltipEl();
      tooltip.innerHTML = '<div class="wd-tooltip-loading">Loading…</div>';
      tooltip.style.display = 'block';
      positionTooltip(e);

      fetchTooltip(linkInfo, characterId)
        .then(function (data) {
          if (!data) throw new Error('No link info available');
          if (_tooltipHovering) {
            tooltip.innerHTML = renderTooltipHtml(data, fallbackName);
            tooltip.style.display = 'block';
            positionTooltip(e);
          }
        })
        .catch(function (err) {
          console.warn('[WDTooltip] Error for', fallbackName, ':', err.message);
          if (_tooltipHovering) {
            tooltip.innerHTML =
              '<div class="wd-tooltip-header">' +
                '<div class="wd-tooltip-name" style="color:#ffffff">' + esc(fallbackName) + '</div>' +
              '</div>' +
              '<div class="wd-tooltip-stats">' +
                '<div class="wd-tooltip-stat wd-tooltip-stat--req">Tooltip data unavailable</div>' +
              '</div>';
            tooltip.style.display = 'block';
            positionTooltip(e);
          }
        });
    });

    el.addEventListener('mousemove', function (e) {
      if (_tooltipHovering) positionTooltip(e);
    });

    el.addEventListener('mouseleave', function () {
      _tooltipHovering = false;
      _tooltipTimeout  = setTimeout(hideTooltip, 220);
    });
  }

  // =========================================================================
  // Public API
  // =========================================================================

  global.WDTooltip = {
    attach:        attach,
    parseLinkInfo: parseLinkInfo,
    fetchTooltip:  fetchTooltip,
    renderHtml:    renderTooltipHtml,
    hide:          hideTooltip,
    iconUrl:       iconUrl,
    cleanIconName: cleanIconName,
  };

})(window);