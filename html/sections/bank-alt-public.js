/**
 * Bank Alt Public Profile — Client Logic
 * Handles tab switching, data loading, rendering, and glow hue animation.
 */

(function () {
  'use strict';

  /* -----------------------------------------------------------------------
     Hue animation — slowly shift the CSS custom property --vortex-hue
     and sync --glow-color so the stone glows follow the portal's hue.
     Cycle: blue → green → red → purple → gunmetal → blue (240s total)
     ----------------------------------------------------------------------- */
  const HUE_STOPS = [
    { hue: 200, label: 'blue'     },   // 0%
    { hue: 120, label: 'green'    },   // 20%
    { hue:   0, label: 'red'      },   // 40%
    { hue: 280, label: 'purple'   },   // 60%
    { hue: 205, label: 'gunmetal' },   // 80%
    { hue: 200, label: 'blue'     },   // 100%
  ];
  const CYCLE_MS   = 240_000; // 4 minutes full loop
  const ROOT       = document.documentElement;

  function lerpHue(a, b, t) {
    let diff = b - a;
    // take the short way around the colour wheel
    if (diff > 180)  diff -= 360;
    if (diff < -180) diff += 360;
    return a + diff * t;
  }

  function tickHue() {
    const now  = (performance.now() % CYCLE_MS) / CYCLE_MS; // 0–1
    const seg  = 1 / (HUE_STOPS.length - 1);
    const idx  = Math.min(Math.floor(now / seg), HUE_STOPS.length - 2);
    const t    = (now - idx * seg) / seg;

    const hue  = lerpHue(HUE_STOPS[idx].hue, HUE_STOPS[idx + 1].hue, t);
    const hueR = Math.round(hue);

    ROOT.style.setProperty('--vortex-hue', hueR);

    // Derive RGB values for the glow from the hue
    // Use a fixed saturation/lightness that looks good
    const [r, g, b] = hslToRgb(hueR / 360, 0.85, 0.55);
    ROOT.style.setProperty('--glow-color',   `hsl(${hueR}, 85%, 60%)`);
    ROOT.style.setProperty('--glow-rgb',     `${r}, ${g}, ${b}`);
    ROOT.style.setProperty('--glow-soft',    `rgba(${r},${g},${b},0.18)`);
    ROOT.style.setProperty('--glow-mid',     `rgba(${r},${g},${b},0.45)`);
    ROOT.style.setProperty('--glow-hard',    `rgba(${r},${g},${b},0.9)`);

    // Throttle: hue shift is imperceptibly slow, 4fps is plenty
    setTimeout(() => requestAnimationFrame(tickHue), 250);
  }

  /** Convert HSL to integer [0–255] RGB triple */
  function hslToRgb(h, s, l) {
    let r, g, b;
    if (s === 0) {
      r = g = b = l;
    } else {
      const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
      const p = 2 * l - q;
      const hue2rgb = (p, q, t) => {
        if (t < 0) t += 1;
        if (t > 1) t -= 1;
        if (t < 1/6) return p + (q - p) * 6 * t;
        if (t < 1/2) return q;
        if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
        return p;
      };
      r = hue2rgb(p, q, h + 1/3);
      g = hue2rgb(p, q, h);
      b = hue2rgb(p, q, h - 1/3);
    }
    return [Math.round(r * 255), Math.round(g * 255), Math.round(b * 255)];
  }

  /* -----------------------------------------------------------------------
     Tab switching
     ----------------------------------------------------------------------- */
  function initTabs() {
    const tabs     = document.querySelectorAll('.bankalt-tab-btn');
    const panels   = document.querySelectorAll('.bankalt-tab-panel');

    function activate(id) {
      tabs.forEach(t => {
        const active = t.dataset.tab === id;
        t.classList.toggle('active', active);
        t.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      panels.forEach(p => {
        const active = p.id === 'bankalt-panel-' + id;
        p.hidden = !active;
        p.classList.toggle('active', active);
      });

      // Lazy-load AH data when clicking the AH tab
      if (id === 'ah' && !window._bankaltAHLoaded) {
        window._bankaltAHLoaded = true;
        loadAHData();
      }
    }

    tabs.forEach(btn => {
      btn.addEventListener('click', () => activate(btn.dataset.tab));
    });

    // Activate first tab
    if (tabs.length) activate(tabs[0].dataset.tab);
  }

  /* -----------------------------------------------------------------------
     Data loading
     ----------------------------------------------------------------------- */
  const SLUG = window.BANKALT_SLUG || '';

  async function fetchData(endpoint) {
    const url = `/sections/bank-alt-public-data.php?slug=${encodeURIComponent(SLUG)}` +
                (endpoint ? `&endpoint=${endpoint}` : '');
    const res = await fetch(url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
  }

  /* -----------------------------------------------------------------------
     Gold formatter
     ----------------------------------------------------------------------- */
  function formatGold(copper) {
    copper = Math.abs(parseInt(copper, 10) || 0);
    const g = Math.floor(copper / 10000);
    const s = Math.floor((copper % 10000) / 100);
    const c = copper % 100;
    const parts = [];
    if (g) parts.push(`<span class="gold">${g.toLocaleString()}g</span>`);
    if (s) parts.push(`<span class="silver">${s}s</span>`);
    if (!g && !s) parts.push(`<span class="copper">${c}c</span>`);
    else if (c)   parts.push(`<span class="copper">${c}c</span>`);
    return parts.join(' ') || '<span class="copper">0c</span>';
  }

  /* -----------------------------------------------------------------------
     Hello Azeroth tab rendering
     ----------------------------------------------------------------------- */
  function renderHello(data) {
    const id  = data.identity  || {};
    const sch = data.schedule  || {};
    const mts = data.mounts    || [];

    // WoW 3.3.5a API: 2 = Male, 3 = Female
    const sexLabel = id.sex === 2 ? 'Male' : id.sex === 3 ? 'Female' : '—';

    // Info cards
    const infoCards = [
      { label: 'Name',           value: id.name    || '—' },
      { label: 'Level',          value: id.level != null ? `Level ${id.level}` : '—' },
      { label: 'Race',           value: id.race    || '—' },
      { label: 'Class',          value: id.class   || '—' },
      { label: 'Sex',            value: sexLabel },
      { label: 'Realm',          value: id.realm   || '—' },
      { label: 'Current Zone',   value: [id.subzone, id.zone].filter(Boolean).join(', ') || 'Unknown' },
      { label: 'Guild',          value: id.guild   || 'No Guild' },
    ].map(card => `
      <div class="stone-panel bankalt-info-card">
        <div class="bankalt-info-label">${card.label}</div>
        <div class="bankalt-info-value">${escHtml(String(card.value))}</div>
      </div>
    `).join('');

    // Online schedule
    const allDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    const activeDays = new Set(sch.active_days || []);
    const scheduleHtml = allDays.map(d =>
      `<div class="bankalt-schedule-pill ${activeDays.has(d) ? 'active' : ''}">${d.slice(0,3)}</div>`
    ).join('');

    const onlineHtml = sch.online_window
      ? `<div class="bankalt-info-value" style="margin-top:8px">⏰ Usually online: ${escHtml(sch.online_window)}</div>`
      : '';

    const el = document.getElementById('bankalt-hello-content');
    if (!el) return;

    el.innerHTML = `
      <div class="bankalt-hello-grid">
        ${infoCards}

        <div class="stone-panel bankalt-info-card bankalt-hello-wide">
          <p class="bankalt-section-title">You may see me riding these</p>
          <div id="bankalt-mounts-container"></div>
        </div>

        <div class="stone-panel bankalt-info-card bankalt-hello-wide">
          <p class="bankalt-section-title">Typical online days</p>
          <div class="bankalt-schedule">${scheduleHtml}</div>
          ${onlineHtml}
        </div>
      </div>
    `;

    // Build mounts DOM so WDTooltip.attach() works on each tag
    const mountsContainer = el.querySelector('#bankalt-mounts-container');
    if (mts.length) {
      const grid = document.createElement('div');
      grid.className = 'bankalt-mounts-grid';
      mts.forEach(m => {
        const tag = document.createElement('div');
        tag.className = 'bankalt-mount-tag';
        tag.textContent = '🐴 ' + (m.name || m);
        if (window.WDTooltip && m.spell_id) {
          WDTooltip.attach(tag, { item_id: m.spell_id, item_type: 'spell', name: m.name }, null);
        }
        grid.appendChild(tag);
      });
      mountsContainer.appendChild(grid);
      const countEl = document.createElement('div');
      countEl.style.cssText = 'margin-top:8px;font-size:0.75rem;color:var(--stone-text-dim)';
      countEl.textContent = `${mts.length} mount${mts.length !== 1 ? 's' : ''} collected`;
      mountsContainer.appendChild(countEl);
    } else {
      mountsContainer.innerHTML = `<div class="bankalt-empty">No mount data available yet.</div>`;
    }
  }

  /* -----------------------------------------------------------------------
     Auction House tab rendering
     ----------------------------------------------------------------------- */
  function renderAH(data) {
    const auctions    = data.active_auctions || [];
    const popular     = data.popular_items   || [];

    // Stats bar
    const totalVal = auctions.reduce((sum, a) => sum + (a.price_stack || 0), 0);

    const statsHtml = `
      <div class="bankalt-ah-stats-row">
        <div class="stone-panel stone-panel-glow bankalt-stat-card">
          <div class="bankalt-stat-value">${auctions.length}</div>
          <div class="bankalt-stat-label">Active Auctions</div>
        </div>
        <div class="stone-panel bankalt-stat-card">
          <div class="bankalt-stat-value" style="font-size:1rem">${formatGold(totalVal)}</div>
          <div class="bankalt-stat-label">Total Listed Value</div>
        </div>
        <div class="stone-panel bankalt-stat-card">
          <div class="bankalt-stat-value">${popular.length}</div>
          <div class="bankalt-stat-label">Unique Items Sold</div>
        </div>
      </div>
    `;

    const el = document.getElementById('bankalt-ah-content');
    if (!el) return;

    // Render static skeleton with placeholder containers
    el.innerHTML = `
      ${statsHtml}
      <hr class="stone-divider" style="margin: 20px 0;">
      <p class="bankalt-section-title">Current Listings</p>
      <div id="bankalt-auction-table-container"></div>
      <hr class="stone-divider" style="margin: 24px 0;">
      <p class="bankalt-section-title">Popular Items</p>
      <div id="bankalt-popular-container"></div>
    `;

    // --- Auction table (DOM-based for tooltips) ---
    const auctionContainer = el.querySelector('#bankalt-auction-table-container');
    if (auctions.length === 0) {
      auctionContainer.innerHTML = `<div class="bankalt-empty">No active auctions at this time.</div>`;
    } else {
      const wrap = document.createElement('div');
      wrap.className = 'bankalt-table-wrap';
      const table = document.createElement('table');
      table.className = 'bankalt-table';
      table.innerHTML = `
        <thead>
          <tr>
            <th>Item</th>
            <th style="text-align:center">Stack</th>
            <th>Total Price</th>
            <th>Per Item</th>
            <th>Time Left</th>
          </tr>
        </thead>
      `;
      const tbody = document.createElement('tbody');
      auctions.forEach(a => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td><div class="bankalt-item-name"></div></td>
          <td style="text-align:center">${a.stack_size}</td>
          <td>${formatGold(a.price_stack)}</td>
          <td>${formatGold(a.price_each)} <span style="font-size:0.75rem;color:var(--stone-text-dim)">ea</span></td>
          <td><span class="bankalt-duration ${a.duration_class}">${escHtml(a.time_remaining || a.duration_label)}</span></td>
        `;
        const nameEl = tr.querySelector('.bankalt-item-name');
        nameEl.textContent = a.name;
        if (window.WDTooltip && (a.link || a.item_id)) {
          WDTooltip.attach(nameEl, { link: a.link, item_id: a.item_id, name: a.name }, null);
        }
        tbody.appendChild(tr);
      });
      table.appendChild(tbody);
      wrap.appendChild(table);
      auctionContainer.appendChild(wrap);
    }

    // --- Popular items (DOM-based for tooltips) ---
    const popularContainer = el.querySelector('#bankalt-popular-container');
    if (popular.length === 0) {
      popularContainer.innerHTML = `<div class="bankalt-empty">No sales history available yet.</div>`;
    } else {
      const grid = document.createElement('div');
      grid.className = 'bankalt-popular-grid';
      popular.forEach((item, i) => {
        const card = document.createElement('div');
        card.className = 'stone-panel bankalt-popular-card';
        card.innerHTML = `
          <div class="bankalt-popular-rank">#${i + 1}</div>
          <div class="bankalt-popular-info">
            <div class="bankalt-popular-name"></div>
            <div class="bankalt-popular-meta">
              ${item.total_sold_qty.toLocaleString()} sold
              · ${item.auction_count} auctions
              · avg stack ${item.avg_stack}
            </div>
          </div>
        `;
        const nameEl = card.querySelector('.bankalt-popular-name');
        nameEl.textContent = item.name;
        if (window.WDTooltip && (item.link || item.item_id)) {
          WDTooltip.attach(nameEl, { link: item.link, item_id: item.item_id, name: item.name }, null);
        }
        grid.appendChild(card);
      });
      popularContainer.appendChild(grid);
    }
  }

  /* -----------------------------------------------------------------------
     Load all data
     ----------------------------------------------------------------------- */
  async function loadAllData() {
    try {
      const data = await fetchData('');
      if (data.error) {
        showError(data.error);
        return;
      }
      window._bankaltData = data;
      renderHello(data);
    } catch (err) {
      console.error('[BankAlt]', err);
      showError('Failed to load character data.');
    }
  }

  async function loadAHData() {
    const data = window._bankaltData;
    if (data) {
      renderAH(data);
    } else {
      const el = document.getElementById('bankalt-ah-content');
      if (el) el.innerHTML = `<div class="bankalt-loading"><div class="bankalt-spinner"></div> Loading…</div>`;
      try {
        const fresh = await fetchData('');
        window._bankaltData = fresh;
        renderAH(fresh);
      } catch (err) {
        const el = document.getElementById('bankalt-ah-content');
        if (el) el.innerHTML = `<div class="bankalt-empty">Failed to load auction data.</div>`;
      }
    }
  }

  function showError(msg) {
    const el = document.getElementById('bankalt-hello-content');
    if (el) el.innerHTML = `<div class="bankalt-empty">⚠️ ${escHtml(msg)}</div>`;
  }

  /* -----------------------------------------------------------------------
     Utility
     ----------------------------------------------------------------------- */
  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* -----------------------------------------------------------------------
     Spiral spark canvas — stars pulled into a black hole, 4 depth rings
     -----------------------------------------------------------------------
     Ring zones (as fraction of maxRadius):
       Zone 0 — inner 0–33%   : full speed   (1×)
       Zone 1 — mid   33–66%  : half speed   (0.5×)
       Zone 2 — outer 66–98%  : quarter speed (0.25×)
       Zone 3 — thin  98–103% : eighth speed  (0.125×), sparse
     Spark counts are weighted by ring area so density looks uniform.
     All sparks spin clockwise; a small random lateral drift can produce
     the occasional "floater" that moves nearly radially — kept intentionally.
     ----------------------------------------------------------------------- */
  function initSpiralSparks() {
    const canvas = document.getElementById('bankalt-sparks-canvas');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');

    function resize() {
      canvas.width  = window.innerWidth;
      canvas.height = window.innerHeight;
    }
    resize();
    window.addEventListener('resize', resize);

    // Ring areas ∝ (max² - min²).  Normalised so zone 0 = 1.0
    // Zone 0: 0.33² - 0²    = 0.109  → ratio 1.00
    // Zone 1: 0.66² - 0.33² = 0.327  → ratio 3.00
    // Zone 2: 0.98² - 0.66² = 0.523  → ratio 4.80
    // Zone 3: 1.03² - 0.98² = 0.102  → ratio ~0.5 (outer rim, sparser by design)
    const ZONES = [
      { min: 0.00, max: 0.33, speedMult: 1.000, count: 14, spinScale: 1.00 },
      { min: 0.33, max: 0.66, speedMult: 0.500, count: 30, spinScale: 0.70 },
      { min: 0.66, max: 0.98, speedMult: 0.250, count: 38, spinScale: 0.45 },
      { min: 0.98, max: 1.03, speedMult: 0.125, count:  6, spinScale: 0.20 },
    ];
    // Total: 88 sparks

    function getMaxRadius() {
      return Math.sqrt(
        (canvas.width  / 2) * (canvas.width  / 2) +
        (canvas.height / 2) * (canvas.height / 2)
      );
    }

    function spawnSparkInZone(zone) {
      const maxR  = getMaxRadius();
      const minD  = zone.min * maxR;
      const maxD  = zone.max * maxR;
      const dist  = minD + Math.random() * (maxD - minD);
      const angle = Math.random() * Math.PI * 2;
      const baseSpeed = (0.45 + Math.random() * 0.55) * 0.205;
      // All clockwise (positive spin). A tiny random lateral nudge on ~8% of
      // sparks creates the occasional near-radial "floater" — kept intentionally.
      const spinBase = (0.008 + Math.random() * 0.018) * zone.spinScale * 0.20;
      const lateralDrift = Math.random() < 0.08 ? (Math.random() - 0.5) * 0.014 : 0;
      return {
        angle,
        dist,
        zone,
        speedMult:    zone.speedMult,
        speed:        baseSpeed * zone.speedMult,
        spin:         spinBase + lateralDrift,   // always positive = clockwise
        size:         0.5 + Math.random() * (zone === ZONES[3] ? 0.6 : 1.0),
        alpha:        0.5 + Math.random() * 0.5,
        hueOff:       Math.floor(Math.random() * 60) - 30,
        zoneIdx:      ZONES.indexOf(zone),
      };
    }

    // Build initial spark pool spread across their zones
    const sparks = [];
    for (const zone of ZONES) {
      for (let i = 0; i < zone.count; i++) {
        sparks.push(spawnSparkInZone(zone));
      }
    }

    let lastGlowHue = 200;

    function drawFrame() {
      const rawHue = ROOT.style.getPropertyValue('--vortex-hue').trim();
      if (rawHue) lastGlowHue = parseInt(rawHue) || 200;

      ctx.clearRect(0, 0, canvas.width, canvas.height);

      const cx   = canvas.width  / 2;
      const cy   = canvas.height / 2;
      const maxR = getMaxRadius();

      for (let i = 0; i < sparks.length; i++) {
        const s = sparks[i];

        // When a spark crosses into the next inner zone, pick up that zone's speed
        const innerBoundary = ZONES[s.zoneIdx].min * maxR;
        if (s.dist < innerBoundary && s.zoneIdx > 0) {
          s.zoneIdx--;
          const newZone = ZONES[s.zoneIdx];
          s.speedMult = newZone.speedMult;
          s.speed     = (0.45 + Math.random() * 0.55) * newZone.speedMult * 0.15;
          s.spin      = (0.008 + Math.random() * 0.018) * newZone.spinScale * 0.15;
        }

        // Spin + radial pull (accelerates as it nears center)
        s.angle += s.spin + (0.0028 * s.speedMult / Math.max(s.dist / maxR, 0.05));
        s.dist  -= s.speed * (1 + (maxR * 0.028 / Math.max(s.dist, 15)));

        const x = cx + Math.cos(s.angle) * s.dist;
        const y = cy + Math.sin(s.angle) * s.dist;

        // Fade toward drain; dim outer zones for depth illusion
        const fadeIn  = Math.min(1, s.dist < 60 ? s.dist / 60 : 1);
        const zoneDim = s.zoneIdx === 3 ? 0.50 : s.zoneIdx === 2 ? 0.72 : 1.0;
        const alpha   = s.alpha * fadeIn * zoneDim;

        const sparkHue = lastGlowHue + s.hueOff;
        ctx.beginPath();
        ctx.arc(x, y, s.size, 0, Math.PI * 2);
        ctx.fillStyle   = `hsla(${sparkHue}, 90%, 75%, ${alpha.toFixed(2)})`;
        ctx.shadowColor = `hsla(${sparkHue}, 85%, 65%, ${(alpha * 0.7).toFixed(2)})`;
        ctx.shadowBlur  = 4;
        ctx.fill();

        // Respawn at the outer edge of the spark's assigned zone
        if (s.dist <= 6 || x < -20 || x > canvas.width + 20 ||
            y < -20 || y > canvas.height + 20) {
          const zone   = ZONES[s.zoneIdx];
          sparks[i]    = spawnSparkInZone(zone);
          sparks[i].dist = zone.max * maxR * (0.85 + Math.random() * 0.15);
        }
      }

      requestAnimationFrame(drawFrame);
    }

    requestAnimationFrame(drawFrame);
  }

  /* -----------------------------------------------------------------------
     Boot
     ----------------------------------------------------------------------- */
  document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    loadAllData();
    requestAnimationFrame(tickHue);
    initSpiralSparks();
  });

})();