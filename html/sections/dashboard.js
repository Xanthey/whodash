/* WhoDASH v2 — Widget Dashboard with Frosted Glass Layout */
(() => {
  const q   = (sel, ctx = document) => ctx.querySelector(sel);
  const qa  = (sel, ctx = document) => Array.from(ctx.querySelectorAll(sel));
  const log = (...a) => console.log('[dashboard]', ...a);

  // ── Body class: exposes blue background when dashboard is active ─────────────
  function activateDashboardMode() {
    document.body.classList.add('db2-active');
  }
  function deactivateDashboardMode() {
    document.body.classList.remove('db2-active');
  }
  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section !== 'dashboard') deactivateDashboardMode();
  });

  // ── Formatters ───────────────────────────────────────────────────────────────

  function formatGold(copper) {
    if (copper === null || copper === undefined) return '<span class="db2-copper">0c</span>';
    const neg = copper < 0;
    const abs = Math.abs(Math.round(copper));
    const g = Math.floor(abs / 10000);
    const s = Math.floor((abs % 10000) / 100);
    const c = abs % 100;
    const parts = [];
    if (g > 0) parts.push(`<span class="db2-gold">${g.toLocaleString()}g</span>`);
    if (s > 0 || (g > 0 && c > 0)) parts.push(`<span class="db2-silver">${s}s</span>`);
    if (c > 0 || parts.length === 0) parts.push(`<span class="db2-copper">${c}c</span>`);
    return (neg ? '<span class="db2-neg">−</span>' : '') + parts.join(' ');
  }

  function formatDuration(sec) {
    if (!sec || sec < 1) return '0s';
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    if (h >= 24) { const d = Math.floor(h / 24); return `${d}d ${h % 24}h`; }
    return h > 0 ? `${h}h ${m}m` : `${m}m`;
  }

  function timeAgo(ts) {
    if (!ts) return '';
    const d = Date.now() / 1000 - ts;
    if (d < 120)    return 'just now';
    if (d < 3600)   return `${Math.floor(d / 60)}m ago`;
    if (d < 86400)  return `${Math.floor(d / 3600)}h ago`;
    if (d < 604800) return `${Math.floor(d / 86400)}d ago`;
    return new Date(ts * 1000).toLocaleDateString();
  }

  // ── Progress bar ─────────────────────────────────────────────────────────────

  function bar(pct, color = null) {
    const fill = color
      ? `style="width:${Math.min(100, pct || 0)}%;background:${color}"`
      : `style="width:${Math.min(100, pct || 0)}%"`;
    return `<div class="db2-bar-track"><div class="db2-bar-fill" ${fill}></div></div>`;
  }

  // ── Hero banner ──────────────────────────────────────────────────────────────

  function heroHTML(d) {
    const id = d.identity || {};
    const parts = [];
    if (id.level) parts.push(`Level ${id.level}`);
    if (id.sex && id.sex !== 'Unknown') parts.push(id.sex);
    if (id.race) parts.push(id.race);
    if (id.spec) parts.push(id.spec);
    if (id.class) parts.push(id.class);

    const badges = [
      id.realm   ? `<span class="db2-badge">${id.realm}</span>` : '',
      id.faction ? `<span class="db2-badge db2-faction-${id.faction.toLowerCase()}">${id.faction}</span>` : '',
      d.zone?.zone ? `<span class="db2-badge db2-badge-zone">📍 ${d.zone.subzone ? d.zone.subzone + ', ' : ''}${d.zone.zone}</span>` : ''
    ].join('');

    return `
      <div class="db2-card db2-hero">
        <div class="db2-hero-left">
          <div class="db2-hero-name">${id.name || 'Character'}</div>
          <div class="db2-hero-sub">${parts.join(' ')}</div>
          ${id.guild ? `<div class="db2-hero-guild">⚔️ ${id.guild}</div>` : ''}
        </div>
        <div class="db2-badges">${badges}</div>
      </div>
    `;
  }

  // ── Widgets ──────────────────────────────────────────────────────────────────

  function wAchievements(d) {
    const a = d.achievements || {};
    const last = a.last;
    const truncate = (str, max = 28) => str && str.length > max ? str.slice(0, max - 1).trimEnd() + '…' : (str || '');
    return `
      <div class="db2-card db2-widget">
        <div class="db2-w-icon">🏆</div>
        <div class="db2-w-label">Achievements</div>
        <div class="db2-w-value">${(a.points || 0).toLocaleString()} <span class="db2-unit">pts</span></div>
        <div class="db2-w-sub">${a.total || 0} earned</div>
        ${last ? `<div class="db2-w-foot" title="${last.name}">Last: <strong class="db2-ach-name">${truncate(last.name)}</strong> <span class="db2-dim">${last.points ? `+${last.points}pts` : ''} · ${timeAgo(last.earned_ts)}</span></div>` : ''}
      </div>
    `;
  }

  function sparkline(values) {
    if (!values || values.length < 2) return '';
    const W = 200, H = 28; // logical viewBox units
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min || 1;
    const pts = values.map((v, i) => {
      const x = (i / (values.length - 1)) * W;
      const y = H - ((v - min) / range) * H;
      return `${x.toFixed(1)},${y.toFixed(1)}`;
    }).join(' ');
    return `<svg viewBox="0 0 ${W} ${H}" preserveAspectRatio="none" fill="none" xmlns="http://www.w3.org/2000/svg" class="db2-spark">
      <polyline points="${pts}" stroke="#4a90d9" stroke-width="1.8" stroke-linejoin="round" stroke-linecap="round" fill="none" opacity="0.85"/>
    </svg>`;
  }

  function wCurrency(d) {
    const cur = d.currency || {};
    const net = cur.net_30 || 0;
    const dir = net > 0 ? 'up' : net < 0 ? 'dn' : '';
    const arrow = net > 0 ? '▲' : net < 0 ? '▼' : '—';
    const spark = sparkline(cur.history || []);
    return `
      <div class="db2-card db2-widget">
        <div class="db2-w-icon">💰</div>
        <div class="db2-w-label">Wallet</div>
        <div class="db2-w-value db2-goldline">${formatGold(cur.current)}</div>
        <div class="db2-w-sub db2-trend-${dir}">${arrow} 30d: ${formatGold(Math.abs(net))} ${net > 0 ? 'gain' : net < 0 ? 'loss' : ''}</div>
        ${spark ? `<div class="db2-spark-row">${spark}</div>` : ''}
      </div>
    `;
  }

  function wPlaytime(d) {
    return `
      <div class="db2-card db2-widget">
        <div class="db2-w-icon">⏱️</div>
        <div class="db2-w-label">Total Playtime</div>
        <div class="db2-w-value">${formatDuration(d.playtime || 0)}</div>
        <div class="db2-w-sub">across all sessions</div>
      </div>
    `;
  }

  function wMail(d) {
    const n = d.mail?.unread || 0;
    return `
      <div class="db2-card db2-widget ${n > 0 ? 'db2-alert' : ''}">
        <div class="db2-w-icon">${n > 0 ? '📬' : '📭'}</div>
        <div class="db2-w-label">Mailbox</div>
        <div class="db2-w-value">${n}</div>
        <div class="db2-w-sub">${n === 1 ? 'unread message' : 'unread messages'}</div>
        <div class="db2-w-foot">${n > 0 ? `<span class="db2-warn">📬 You've got mail!</span>` : `<span class="db2-ok">✓ All clear</span>`}</div>
      </div>
    `;
  }

  function wAuctions(d) {
    const auc = d.auctions || {};
    const exp = auc.expired || 0;
    return `
      <div class="db2-card db2-widget">
        <div class="db2-w-icon">🏪</div>
        <div class="db2-w-label">Auction House</div>
        <div class="db2-w-value">${auc.active || 0}</div>
        <div class="db2-w-sub">active listings</div>
        <div class="db2-w-foot">${exp > 0 ? `<span class="db2-warn">⚠️ ${exp} expired / unclaimed</span>` : `<span class="db2-ok">✓ No expired listings</span>`}</div>
      </div>
    `;
  }

  function wGrudge(d) {
    const g = d.grudge || {};
    const last = g.last_killer;
    return `
      <div class="db2-card db2-widget">
        <div class="db2-w-icon">😤</div>
        <div class="db2-w-label">The Grudge List</div>
        <div class="db2-w-value">${g.total || 0}</div>
        <div class="db2-w-sub">${g.total === 1 ? 'player nemesis' : 'player nemeses'}</div>
        <div class="db2-w-foot">${last
          ? `Last: <strong>${last.name}</strong> <span class="db2-dim">(${last.kills}× · ${timeAgo(last.last_ts)})</span>`
          : `<span class="db2-ok">✓ No grudges held</span>`
        }</div>
      </div>
    `;
  }

  function wBags(d) {
    const b = d.bags || {};
    const pct = b.total > 0 ? Math.round((b.used / b.total) * 100) : 0;
    return `
      <div class="db2-card db2-widget">
        <div class="db2-w-icon">🎒</div>
        <div class="db2-w-label">Container Space</div>
        <div class="db2-w-value">${b.used} <span class="db2-unit">/ ${b.total}</span></div>
        <div class="db2-w-sub">bag slots used</div>
        <div class="db2-bar-row">${bar(pct)}<span class="db2-barlabel">${pct}%</span></div>
        ${b.bank_used > 0 ? `<div class="db2-w-foot"><span class="db2-dim">🏦 Bank: ${b.bank_used} items stored</span></div>` : ''}
      </div>
    `;
  }

  function wSharing(d) {
    const sh = d.sharing || {};
    const flag = (on, lbl) => `<div class="db2-flag ${on ? 'on' : 'off'}"><span class="db2-dot"></span><span>${lbl}</span></div>`;
    return `
      <div class="db2-card db2-widget">
        <div class="db2-w-icon">🔗</div>
        <div class="db2-w-label">Sharing</div>
        <div class="db2-flags">
          ${flag(sh.shared_character, 'Shared Profile')}
          ${flag(sh.shared_bank_alt, 'Bank Alt')}
        </div>
      </div>
    `;
  }

  function wProfessions(d) {
    const profs = d.professions || [];
    if (!profs.length) return `
      <div class="db2-card db2-widget db2-wide">
        <div class="db2-w-icon">🔨</div><div class="db2-w-label">Professions</div>
        <div class="db2-w-sub db2-dim">No professions learned</div>
      </div>`;
    const rows = profs.map(p => {
      const pct = p.max_rank > 0 ? (p.rank / p.max_rank) * 100 : 0;
      return `<div class="db2-prof-row">
        <span class="db2-prof-name">${p.name}</span>
        <span class="db2-prof-rank">${p.rank}/${p.max_rank}</span>
        ${bar(pct)}
      </div>`;
    }).join('');
    return `
      <div class="db2-card db2-widget db2-wide">
        <div class="db2-w-icon">🔨</div><div class="db2-w-label">Professions</div>
        <div class="db2-prof-list">${rows}</div>
      </div>
    `;
  }

  const REP_COLORS = { 7:'#c8a040', 6:'#4a90d9', 5:'#4aaa4a', 4:'#b8a040', 3:'#d07040', 2:'#c05030', 1:'#b03030' };

  function wReputation(d) {
    const reps = d.reputation || [];
    if (!reps.length) return `
      <div class="db2-card db2-widget db2-wide">
        <div class="db2-w-icon">🌟</div><div class="db2-w-label">Closest to Exalted</div>
        <div class="db2-w-sub db2-dim">No reputation data</div>
      </div>`;
    const rows = reps.map(r => {
      const pct = r.max_value > 0 ? (r.value / r.max_value) * 100 : 0;
      const col = REP_COLORS[r.standing_id] || '#888';
      return `<div class="db2-rep-row">
        <div class="db2-rep-head"><span class="db2-rep-name">${r.name}</span><span class="db2-rep-standing" style="color:${col}">${r.standing_name}</span></div>
        <div class="db2-bar-row">${bar(pct, col)}<span class="db2-barlabel">${r.value.toLocaleString()} / ${r.max_value.toLocaleString()}</span></div>
      </div>`;
    }).join('');
    return `
      <div class="db2-card db2-widget db2-wide">
        <div class="db2-w-icon">🌟</div><div class="db2-w-label">Closest to Exalted</div>
        <div class="db2-rep-list">${rows}</div>
      </div>
    `;
  }

  const ROLE_ICON  = { damage:'⚔️', tanking:'🛡️', healing:'💚' };
  const ROLE_LABEL = { damage:'Damage', tanking:'Tanking', healing:'Healing' };

  function wRole(d) {
    const r = d.role || {};
    if (!r.primary || !r.stats?.length) return `
      <div class="db2-card db2-widget db2-wide">
        <div class="db2-w-icon">⚔️</div><div class="db2-w-label">Combat Role</div>
        <div class="db2-w-sub db2-dim">No combat data yet</div>
      </div>`;
    const stats = r.stats.map(s => `
      <div class="db2-role-stat">
        <div class="db2-role-val">${s.value}</div>
        <div class="db2-role-lbl">${s.label}</div>
      </div>`).join('');
    return `
      <div class="db2-card db2-widget db2-wide">
        <div class="db2-role-head">
          <span>${ROLE_ICON[r.primary] || '⚔️'}</span>
          <span class="db2-w-label" style="margin:0">${ROLE_LABEL[r.primary] || 'Combat'} Role</span>
          <span class="db2-role-badge db2-role-${r.primary}">${ROLE_LABEL[r.primary]}</span>
        </div>
        <div class="db2-role-stats">${stats}</div>
      </div>
    `;
  }

  function wTips(d) {
    const tips = d.tips || [];
    if (!tips.length) return '';
    window._db2tips = tips;
    window._db2tipIdx = 0;
    return `
      <div class="db2-card db2-widget db2-wide db2-tip">
        <div class="db2-tip-head">
          <span>💡</span>
          <span class="db2-w-label" style="margin:0">Level ${d.identity?.level || '?'} Tip</span>
          ${tips.length > 1 ? `<button class="db2-tip-btn" id="db2TipNext">Next tip ›</button>` : ''}
        </div>
        <div class="db2-tip-text" id="db2TipText">${tips[0]}</div>
      </div>
    `;
  }

  // ── Render ───────────────────────────────────────────────────────────────────

  function renderDashboard(data, charName) {
    const root = q('#tab-dashboard');
    if (!root) return;
    if (data.identity) data.identity.name = charName || data.identity.name;
    activateDashboardMode();

    root.innerHTML = `
      <div class="db2-root">
        ${heroHTML(data)}
        <div class="db2-grid">
          ${wAchievements(data)}
          ${wCurrency(data)}
          ${wPlaytime(data)}
          ${wMail(data)}
          ${wAuctions(data)}
          ${wGrudge(data)}
          ${wBags(data)}
          ${wSharing(data)}
          ${wProfessions(data)}
          ${wReputation(data)}
          ${wRole(data)}
          ${wTips(data)}
        </div>
      </div>
    `;

    // Tip rotation
    q('#db2TipNext', root)?.addEventListener('click', () => {
      const tips = window._db2tips || [];
      if (tips.length < 2) return;
      window._db2tipIdx = (window._db2tipIdx + 1) % tips.length;
      const el = q('#db2TipText', root);
      if (!el) return;
      el.style.opacity = '0';
      setTimeout(() => { el.textContent = tips[window._db2tipIdx]; el.style.opacity = '1'; }, 180);
    });

    // Stagger in
    qa('.db2-card', root).forEach((card, i) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(16px)';
      setTimeout(() => {
        card.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, 40 + i * 45);
    });
  }

  // ── Load ─────────────────────────────────────────────────────────────────────

  async function loadDashboard() {
    const root = q('#tab-dashboard');
    if (!root) return;
    const cid  = root.dataset?.characterId;
    const name = root.dataset?.charName || '';

    activateDashboardMode();
    root.innerHTML = `<div class="db2-loading"><div class="db2-spinner"></div><span>Loading dashboard…</span></div>`;

    if (!cid) { root.innerHTML = '<div class="db2-loading">No character selected.</div>'; return; }

    try {
      const res = await fetch(`/sections/dashboard-data.php?character_id=${encodeURIComponent(cid)}`, { credentials: 'include' });
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      const data = await res.json();
      renderDashboard(data, name);
    } catch (err) {
      log('Error:', err);
      root.innerHTML = `<div class="db2-loading" style="color:#d32f2f">Failed to load: ${err.message}</div>`;
    }
  }

  // ── Events ───────────────────────────────────────────────────────────────────

  document.addEventListener('whodat:section-loaded', (ev) => {
    if (ev?.detail?.section === 'dashboard') loadDashboard();
  });
  document.addEventListener('whodat:character-changed', () => {
    if ((history?.state?.section || 'dashboard') === 'dashboard') loadDashboard();
  });

  if (q('#tab-dashboard')) loadDashboard();
  log('Dashboard v2 ready');
})();