<div align="center">

# 📊 WhoDASH

### World of Warcraft WotLK 3.3.5a — Self-Hosted Character Analytics Dashboard

[![Version](https://img.shields.io/badge/version-4.0.0-blue.svg)](https://github.com/Xanthey/whodash)
[![WoW](https://img.shields.io/badge/WoW-3.3.5a-orange.svg)](https://github.com/Xanthey/whodash)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Belmont Labs](https://img.shields.io/badge/Belmont%20Labs-BL--002-purple.svg)](https://belmontlabs.dev)

*A mad-science analytics lab for your characters — powered by the **WhoDAT** addon and the **SyncDAT** uploader.*

</div>

---

## 🎮 What is WhoDASH?

WhoDASH is a comprehensive self-hosted web dashboard for WoW: Wrath of the Lich King 3.3.5a private servers. The **WhoDAT** in-game addon silently captures your character data, **SyncDAT** syncs it to your server, and WhoDASH turns it into beautiful, actionable analytics — all on hardware you control.

- **No cloud. No accounts. No tracking.** Everything lives on your own server.
- **Single Page Application** — instant navigation, zero page reloads.
- **Public character pages** — share a link; visitors see a themed showcase of your character without any account required.
- **Multi-character, multi-user** — one installation tracks every alt across your whole roster.

---

## 🚀 Features

### 📊 Dashboard
Your character at a glance — level, resources, current gear, gold sparkline, profession bars, reputation standing, active auctions, container space, a rotating tips widget, and a live Grudge List. Dark navy frosted-glass widget layout with Cinzel character nameplate.

### 📈 Timeline Graphs *(14 tracked metrics)*
Level · Gold · Honor · Arena Points · Deaths per Day · Boss Kills per Day · Reputation Gains · Achievement Points · Quest Completions · Zone Activity · Max HP · Max Mana · Attack Power · Items Looted per Day. Every graph zero-filters noise automatically.

### 📋 Summary
Session tracking, playtime heatmaps, gold sparklines, death-tier breakdowns, reputation key display, activity feed, and a high-level overview of your character's life.

### ⚔️ Role Performance
Three deep-dive sub-sections:
- **Combat (DPS)** — damage breakdown, target priorities, burst analysis, consistency metrics
- **Healing** — HPS tracking, overheal analysis, healing-target distribution
- **Tanking** — damage taken, mitigation effectiveness, threat management

### 💰 Currencies & Economy
Gold progression with trend analysis · Honor & Arena Points · income/expense breakdown · gold-per-hour efficiency · auction house analytics · milestone tracking.

### 🧳 Items & Inventory
Real-time bag / bank / keyring / mailbox tracking · equipment snapshot history · item acquisition log · quality distribution · epic loot timeline · WoWhead WotLK tooltip integration.

### 📜 Quests
Active quest log · completion history & trends · acceptance/abandonment tracking · daily/weekly patterns.

### 🛠️ Professions
Skill progression over time · recipe mastery · Apprentice → Grand Master bracket display · profession milestone achievements.

### 🏅 Achievements
Full history with earned-date tracking · achievement point progression · category breakdowns.

### 🗺️ Travel Log
Zone visit history · time-spent-per-zone · exploration heatmaps · zone discovery timeline.

### 🏆 Progression
Current-tier raid tracking · boss kill statistics · instance lockout view · difficulty breakdowns · kill-count analysis.

### 💀 Mortality
Full PvP death dossier with modal incident logs · NPC vs Player kill breakdown · zone danger ratings · durability loss tracking · death frequency timeline · resurrection-type statistics · **The Grudge** integration (send PvP killers directly to your nemesis tracker).

### 🤝 Social
Group composition history · dungeon/raid companions · social bracket analytics.

### 🏦 Bazaar *(Auction House Analytics)*
Heatmaps · character comparison · auction listings · workshop · fortune tracking · inventory · social · timeline · alerts · progression views.

### 🏰 Guild Hall
Member roster · bank vault viewer · treasury log · transaction history · business analytics · activity logs.

### 🌐 Public Character Page
Shareable URL (`/public_character.php?key=…`) with an animated Stone Citadel / Dark Portal vortex theme. Tabs: **Dashboard · Summary · Mortality · Travel Log · Achievements · Progression**. Grudge controls are hidden from public viewers.

### ⚙️ Config & API
API key management for SyncDAT · character sharing controls · account preferences.

---

## 🐳 Quick Start with Docker

### Prerequisites
- [Docker](https://docs.docker.com/get-docker/) + [Docker Compose](https://docs.docker.com/compose/install/)
- The **WhoDAT** addon in your WoW `Interface/AddOns/` folder
- The **SyncDAT** uploader on your machine

### 1 — Clone the repo
```bash
git clone https://github.com/Xanthey/whodash.git
cd whodash
```

### 2 — (Optional) Set secrets
```bash
cp .env.example .env   # then edit passwords
```
If you skip this step, the defaults in `docker-compose.yml` are used.

### 3 — Start everything
```bash
docker compose up -d
```

Two services come up:
| Service | Port | What it does |
|---|---|---|
| `whodash_frontend` | **8090** | Nginx + PHP 8.4 — the WhoDASH web app |
| `whodash_backend` | 3306 (internal) | MySQL 8.0 — character data storage |

### 4 — Access the dashboard
Open `http://localhost:8090` → create your first account → done. 🎉

---

## 🔧 Configuration

### Environment Variables *(docker-compose.yml)*
```yaml
environment:
  DB_HOST: backend
  DB_USER: whodatuser
  DB_PASSWORD: ${MYSQL_PASSWORD:-changeme}
  DB_NAME: whodat
```

### Port Mapping
```yaml
ports:
  - "8090:8080"   # Change the left side to your preferred host port
```

### PHP Limits *(php.ini)*
```ini
upload_max_filesize = 128M
post_max_size       = 128M
memory_limit        = 256M
max_execution_time  = 300
```

---

## 🎮 WhoDAT Addon Setup

**WhoDAT** captures everything WhoDASH displays — stats, deaths, loot, quests, gold, professions, and more — silently in the background while you play.

1. Drop the `WhoDAT` folder into `WoW/Interface/AddOns/`
2. Restart WoW (or `/reload`)
3. Type `/whodat` to open the addon panel

---

## 📤 SyncDAT — Uploader Tool

**SyncDAT** reads your WoW SavedVariables files and pushes them to your WhoDASH server.

1. [Download SyncDAT](https://www.belmontlabs.dev/uploader.html)
2. Generate an API key in WhoDASH → **Config** → **API Keys**
3. Point SyncDAT at your server URL and paste the key
4. Hit **Sync** — your data appears in the dashboard instantly

SyncDAT also handles **The Grudge** export: it can pull your nemesis list from WhoDASH and write it back to `TheGrudgeDB.lua` so the in-game addon stays in sync.

---

## 📁 Project Structure

```
whodash/
├── html/
│   ├── index.html              # SPA shell
│   ├── main.js                 # Hash-router & section loader
│   ├── style.css               # Global styles
│   ├── tooltip-engine.js       # WDTooltip — shared Wowhead tooltip engine
│   ├── db.php                  # Database connection
│   ├── public_character.php    # Public shareable character page
│   ├── tips.json               # Rotating dashboard tips
│   ├── api/
│   │   ├── index.php           # SyncDAT upload endpoint
│   │   ├── grudge_export.php   # The Grudge Lua export endpoint
│   │   └── manage_api_keys.php # API key CRUD
│   └── sections/
│       ├── dashboard.*         # Dashboard widgets
│       ├── graphs.*            # 14-metric timeline graphs
│       ├── summary.*           # Character summary / highlights
│       ├── mortality.*         # Death analytics + Grudge integration
│       ├── combat.*            # DPS analytics
│       ├── healing.*           # Healer analytics
│       ├── role.*              # Role picker / combat-role display
│       ├── tanking.*           # Tank analytics
│       ├── currencies.*        # Gold & currency tracking
│       ├── items.*             # Inventory & item history
│       ├── quests.*            # Quest log & history
│       ├── professions.*       # Tradeskill tracking
│       ├── achievements.*      # Achievement log
│       ├── travel-log.*        # Zone exploration
│       ├── progression.*       # Raid/boss progression
│       ├── reputation.*        # Faction standings
│       ├── social.*            # Group & companion history
│       ├── bazaar.*            # Auction house analytics
│       ├── guild-hall.*        # Guild roster, vault, treasury
│       ├── character.*         # Character sheet
│       ├── conf.*              # Config & API key management
│       └── onboarding.*        # First-run setup guide
├── docker-compose.yml
├── Dockerfile
├── mysql_Dockerfile
├── mysql_init.sql
├── default.conf                # Nginx config
└── php.ini
```

---

## 🛠️ Technology Stack

| Layer | Tech |
|---|---|
| Frontend | Vanilla JavaScript (ES6+), SPA with hash routing |
| Charts | SVG + custom canvas renderers |
| Backend | PHP 8.4 |
| Database | MySQL 8.0 |
| Web server | Nginx |
| Containerization | Docker + Docker Compose |
| Tooltip data | Wowhead WotLK (`/wotlk/item=ID`) via **WDTooltip** |

---

## 🔒 Privacy & Security

- **Self-hosted** — your data never leaves your server
- **Session-based auth** — secure login with per-user data isolation
- **API key auth** — SyncDAT uploads use rotating API keys, not passwords
- **Public pages** — opt-in sharing; private data (Grudge actions, wallet details) is always hidden from public viewers

---

## 🧪 Belmont Labs Ecosystem

WhoDASH (BL-002) is part of the **Belmont Labs** personal projects suite:

| Project | ID | Description |
|---|---|---|
| **WhoDAT** | BL-001 | In-game Lua addon — data capture |
| **WhoDASH** | BL-002 | This dashboard |
| **SyncDAT** | BL-003 | Desktop uploader / sync tool |
| **The Grudge** | BL-007 | PvP nemesis tracker addon |

---

## 🤝 Contributing

Bug reports, feature suggestions, and PRs are welcome!

- 🐛 [Open an issue](https://github.com/Xanthey/whodash/issues)
- 💬 [Discord](https://discord.com/channels/269396747875385345/1446301955273265242)

---

## 📝 License

MIT — see [LICENSE](LICENSE) for details.

---

<div align="center">
  Built with ❤️ and questionable science by <a href="https://belmontlabs.dev">Belmont Labs</a>
</div>
