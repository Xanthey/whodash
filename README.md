# 📊 WhoDASH - World of Warcraft 3.3.5a Private Server Character Analytics Dashboard

**Your complete WoW character analytics platform** — Track, analyze, and visualize every aspect of your World of Warcraft journey with beautiful real-time dashboards powered by the WhoDAT addon.

---

## 🎮 What is WhoDASH?

WhoDASH is a comprehensive web-based analytics dashboard for World of Warcraft: Wrath of the Lich King 3.3.5a that transforms your gameplay data into stunning visualizations and actionable insights. Powered by the WhoDAT addon, it tracks everything from your gold progression and boss kills to deaths, achievements, and zone exploration—all in one elegant interface.

### ✨ Why WhoDASH?

- **📈 Real-time Analytics** - Watch your character's progression unfold with live-updating charts and graphs
- **🎯 Deep Insights** - Understand your playstyle through detailed combat, healing, and tanking metrics
- **💰 Financial Tracking** - Monitor gold flow, auction house performance, and earning efficiency
- **🗺️ Complete Journey** - Track every zone visited, quest completed, and boss defeated
- **📱 Modern Interface** - Clean, responsive design that works on desktop and mobile
- **🔒 Self-Hosted** - Your data stays yours—host it yourself with Docker in minutes

---

## 🚀 Features

### 📊 **Dashboard**
Your character at a glance—level, health, resources, current equipment, recent activity, and quick stats all in one beautiful overview.

### ⚔️ **Role Performance**
Dive deep into your combat effectiveness:
- **Combat Metrics** - DPS analysis, damage breakdown, target priorities
- **Healing Analytics** - HPS tracking, overheal analysis, healing target distribution  
- **Tanking Stats** - Damage taken, mitigation effectiveness, threat management

### 💰 **Currencies & Economy**
Complete financial overview:
- Gold progression over time with trend analysis
- Honor Points and Arena Points tracking
- Income/expense breakdown
- Gold-per-hour efficiency metrics
- Auction house analytics
- Milestone tracking

### 🧳 **Items & Inventory**
Smart inventory management:
- Real-time bag/bank/keyring/mailbox tracking
- Equipment snapshot history
- Item acquisition tracking
- Quality distribution analytics
- Epic loot timeline

### 📜 **Quests**
Never lose track of your progress:
- Active quest log with objectives
- Completion history and trends
- Quest acceptance/abandonment tracking
- Daily/weekly quest patterns

### 🛠️ **Professions**
Track your crafting journey:
- Skill progression over time
- Recipe mastery tracking
- Profession milestone achievements

### 🏅 **Achievements**
Celebrate every accomplishment:
- Complete achievement history
- Earned date tracking
- Achievement points progression
- Category breakdowns

### 🗺️ **Travel Log**
Explore Azeroth like never before:
- Zone visit history
- Time spent per zone
- Exploration heatmaps
- Zone discovery timeline

### 🏆 **Progression**
Track your raiding career:
- Current tier raid progression
- Boss kill statistics
- Instance lockout tracking
- Difficulty breakdowns
- Kill count analysis

### 💀 **Mortality**
Learn from every death:
- Death timeline and frequency
- Location analysis
- Killer breakdown (NPCs vs Players)
- Durability loss tracking
- Zone danger ratings
- Resurrection type statistics

### 📋 **Summary**
High-level analytics:
- Session tracking
- Playtime analysis
- Activity patterns
- Comprehensive character overview

### 📈 **Timeline Graphs**
Visualize your journey:
- Level progression
- Gold accumulation
- Honor/Arena points
- Daily deaths
- Boss kills per day
- Reputation gains
- Achievement unlocks
- Quest completions
- Zone activity

---

## 🐳 Quick Start with Docker

The easiest way to get WhoDASH running is with Docker Compose. Everything is pre-configured and ready to go!

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) installed
- [Docker Compose](https://docs.docker.com/compose/install/) installed
- The WhoDAT addon installed in your WoW client

### Installation

1. **Clone or download this repository**
   ```bash
   git clone https://github.com/Xanthey/whodash.git
   cd whodash
   ```

2. **Start the services**
   ```bash
   docker-compose up -d
   ```

3. **Access the dashboard**
   - Open your browser to `http://localhost:8090`
   - Create your first user account
   - You're ready to go! 🎉

### What Gets Installed

The Docker Compose setup creates two services:

- **Frontend** (Port 8090) - Nginx + PHP 8.4 serving the WhoDASH web interface
- **Backend** (Port 3306) - MySQL 8.0 database for storing your character data

All data is persisted in Docker volumes, so your information is safe across container restarts.

### Database Setup

On first run, you'll need to initialize the database:

1. Navigate to `http://localhost:8090/sql_setup.php`
2. Click **"🚀 Create Schema"**
3. The database structure will be created automatically

---

## 🎮 WhoDAT Addon Setup

WhoDASH requires the **WhoDAT addon** to collect data from your WoW client.

### Installing the Addon

1. Download the latest WhoDAT addon *(link coming soon)*
2. Extract to your WoW `Interface/AddOns` directory
3. Restart World of Warcraft
4. Type `/whodat` in-game to access settings

### Connecting to WhoDASH

1. In-game, type `/whodat config`
2. Set your WhoDASH server URL: `http://localhost:8090`
3. The addon will automatically sync your character data
4. Refresh the dashboard to see your stats!

---

## 📁 Project Structure

```
whodash/
├── sections/           # Page modules (PHP + JS)
│   ├── dashboard.php
│   ├── dashboard.js
│   ├── graphs.php
│   ├── graphs.js
│   ├── currencies.php
│   └── ...
├── db.php             # Database connection
├── index.html         # Main SPA shell
├── main.js           # Core SPA router
├── style.css         # Global styles
├── docker-compose.yml # Docker orchestration
├── Dockerfile        # Container definition
└── sql_setup.php     # Database schema installer
```

---

## 🔧 Configuration

### Environment Variables

You can customize the database connection via environment variables in `docker-compose.yml`:

```yaml
environment:
  DB_HOST: backend
  DB_USER: whodatuser
  DB_PASSWORD: whodatpass
  DB_NAME: whodat
```

### PHP Configuration

Upload limits and execution time can be adjusted in `php.ini`:

```ini
upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 256M
max_execution_time = 300
```

### Port Mapping

By default, WhoDASH runs on port 8090. Change it in `docker-compose.yml`:

```yaml
ports:
  - "8090:8080"  # Change 8090 to your preferred port
```

---

## 🛠️ Technology Stack

**Frontend:**
- Pure JavaScript (ES6+) - No frameworks, fast and lightweight
- SVG-based visualizations - Smooth, responsive charts
- Single Page Application (SPA) architecture
- Responsive CSS with mobile support

**Backend:**
- PHP 8.4 - Modern, fast server-side processing
- MySQL 8.0 - Robust relational database
- RESTful JSON APIs - Clean data exchange

**Infrastructure:**
- Nginx - High-performance web server
- Docker & Docker Compose - Easy deployment
- Supervisor - Process management

---

## 📊 Data Collection

WhoDASH tracks an extensive array of game data:

### Character Data
✅ Level, XP, and progression  
✅ Health, mana, and resources  
✅ Base stats (Str, Agi, Stam, Int, Spr)  
✅ Combat stats (Attack Power, Crit, Hit, etc.)  
✅ Equipment and inventory  

### Activity Tracking
✅ Sessions and playtime  
✅ Zone visits and exploration  
✅ Quest acceptance and completion  
✅ Profession skill progression  
✅ Achievement unlocks  

### Combat & Encounters
✅ Boss kills and raid progression  
✅ Deaths and mortality analysis  
✅ Damage/healing/tanking metrics  
✅ Group compositions  

### Economy
✅ Gold progression over time  
✅ Currency tracking (Honor, Arena, etc.)  
✅ Auction house activity  
✅ Item acquisition and sales  
✅ Vendor transactions  

---

## 🔒 Privacy & Security

- **Self-Hosted** - All data stays on your server
- **Local Storage** - No cloud services or third-party tracking
- **User Authentication** - Secure login system
- **Character Ownership** - Multi-user support with data isolation

---

## 🤝 Contributing

We welcome contributions! Whether it's:

- 🐛 Bug reports
- 💡 Feature suggestions  
- 📝 Documentation improvements
- 🔧 Code contributions

Feel free to open an issue or submit a pull request!

---

## 📝 License
<div align="center">
[![Version](https://img.shields.io/badge/version-3.0.0-blue.svg)](https://github.com/Xanthey/WhoDAT)
[![WoW](https://img.shields.io/badge/WoW-3.3.5a-orange.svg)](https://wowpedia.fandom.com/wiki/Patch_3.3.5)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
</div>
---

## 🙏 Acknowledgments

Built with ❤️ for the World of Warcraft community.

Special thanks to:
- The WoW addon development community
- All contributors and testers
- Players who provided feedback

---

## 📞 Support

- **Issues**: [GitHub Issues](https://github.com/Xanthey/whodash/issues)
- **Discord**: [Discord Link] (https://discord.com/channels/269396747875385345/1446301955273265242)

---

## 🎯 Roadmap

Planned features for future releases:

- [ ] Multi-character comparison views
- [ ] Guild analytics dashboard
- [ ] Advanced PvP analytics
- [ ] Raid attendance tracking

---

**Ready to dive deep into your WoW data? Get started with WhoDASH today!** 🚀
