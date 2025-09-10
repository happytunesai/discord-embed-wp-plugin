# 🎮 Discord Embed Creator

```
╔═══════════════════════════════════════════════════════════════╗
║                                                               ║
║    ██████╗ ██╗███████╗ ██████╗ ██████╗ ██████╗ ██████╗        ║
║    ██╔══██╗██║██╔════╝██╔════╝██╔═══██╗██╔══██╗██╔══██╗       ║
║    ██║  ██║██║███████╗██║     ██║   ██║██████╔╝██║  ██║       ║
║    ██║  ██║██║╚════██║██║     ██║   ██║██╔══██╗██║  ██║       ║
║    ██████╔╝██║███████║╚██████╗╚██████╔╝██║  ██║██████╔╝       ║
║    ╚═════╝ ╚═╝╚══════╝ ╚═════╝ ╚═════╝ ╚═╝  ╚═╝╚═════╝        ║
║                                                               ║
║           🚀 EMBED CREATOR FOR WORDPRESS 🚀                  ║
║                                                               ║
╚═══════════════════════════════════════════════════════════════╝
```

**📊 Version:** 2.3.5 
**⚡ Requires PHP:** 7.4+  
**🌐 Requires WordPress:** 5.0+  

> 🎯 **The ultimate WordPress plugin** for Discord embeds with live preview, template management, flexible webhook/bot configurations and optional live notifications system for Twitch & YouTube!

---

## 📋 Table of contents
- [✨ Features](#-features)
- [📺 Live Notifications (Twitch & YouTube)](#-live-notifications-twitch--youtube)
- [😀 Emoji fetcher (server guild emojis)](#-emoji-fetcher-server-guild-emojis)
- [⚙️ Requirements](#️-requirements)
- [📦 Installation](#-installation)
- [🚀 Usage](#-usage)
- [🔧 Configuration Details](#-configuration-details)
- [🗄️ Database](#️-database)
- [⏰ Cron](#-cron)
- [🔌 API Integration](#-api-integration)
- [📄 Template System](#-template-system)
- [💻 JavaScript Integration](#-javascript-integration)
- [🐛 Debugging & Troubleshooting](#-debugging--troubleshooting)
- [📝 Changelog](#-changelog)
- [📞 Support & License](#-support--license)

---

## ✨ Features

- **🎨 Embed Builder**
  - Title, description (Markdown), URL, color, timestamp, author, footer, images, custom fields
  - Live preview styled like Discord
  - Role mentions helper (inserts <@&ROLEID>)

- **📚 Template Management**
  - Save, load, and delete embed templates
  - Stored in the database and loaded via AJAX

- **🔗 Webhook Configuration**
  - Channel Webhook: use a standard webhook URL
  - Server Webhook: use Bot Token + Server ID and select channels via Discord API
  - Test connection in a configuration modal
  - Persistent settings saved via WordPress options

- **📩 Message Management**
  - Send via channel webhook or Bot API
  - History of sent messages with filters (today, week, month)
  - Load an existing Discord message by URL and edit it

- **🔴 Live Notifications (optional)**
  - Monitor Twitch and YouTube channels and post live notifications automatically
  - Integrates with StreamWeasels for Twitch/YouTube credentials (optional)
  - Configurable templates, role mentions and cooldown anti-spam

- **😀 Emoji Fetcher**
  - Load guild emojis via Bot Token + Server ID (for custom emoji insertion into templates)
  - Client-side emoji picker with transient caching on the server

- **🎭 UI/UX**
  - Toast notifications instead of alert() (where available)
  - Autofill prevention and small admin UI fixes
  - Responsive layout: editor and live preview side by side
  - Full internationalization support (English/German)

- **🐛 Debug & Logging**
  - Optional console logs and PHP error_log entries for AJAX and API calls
  - Built-in Debug Console on the admin page

---

## 📺 Live Notifications (Twitch & YouTube)

```
🔴 LIVE    📺 Twitch/YouTube Monitor    🔴 LIVE
┌───────────────────────────────────────────────┐
│     ◉ StreamWeasels Integration  ✓           │
│     ◉ Twitch Helix API           ✓           │
│     ◉ YouTube Data API v3        ✓           │
│     ◉ WordPress Cron (3min)      ✓           │
│     ◉ Anti-Spam Cooldown         ✓           │
└───────────────────────────────────────────────┘
```

This plugin includes a Live Notifications module that can periodically check Twitch and YouTube for live streams and post a customizable Discord embed when a channel goes live.

Features:
- StreamWeasels integration: reads Twitch/YouTube credentials from the StreamWeasels plugin if present.
- Twitch: Helix API checks for streams.
- YouTube: Data API v3 checks for live videos.
- Runs on a WordPress Cron every 3 minutes (custom interval `every_three_minutes`).
- Per-channel cooldown (default 10 minutes) to avoid spamming.
- Saved live templates with CRUD, per-template enable/disable and immediate send action.

Setup:
1. Ensure you updated the database (see Database section or run the included update script).
2. Configure StreamWeasels credentials (optional) or provide channel identifiers.
3. Go to Discord Embeds → Live Notifications and configure platform, webhook target and template.

Cron:
- Hook: `discord_embed_check_live_status`
- Interval: `every_three_minutes` (180s)

Database:
- Table `wp_discord_live_notifications` stores monitored channels and last status.

Template placeholders:
- `{platform}`, `{title}`, `{url}`, `{thumbnail}`

---

## 😀 Emoji fetcher (guild emojis)

```
   😀 😃 😄 😁 😆    CUSTOM GUILD EMOJIS    🎉 🎊 🎈 ✨ 🌟
┌────────────────────────────────────────────────────────────┐
│  ◉ Discord Bot API Integration  ✓                         │
│  ◉ Server-side Caching (60min)  ✓                         │
│  ◉ Click-to-Insert Emoji Picker ✓                         │
│  ◉ Fully Localized UI           ✓                         │
└────────────────────────────────────────────────────────────┘
```

- The plugin can load custom guild emojis from Discord so you can insert them into descriptions or fields. Key points:

- Server endpoint: AJAX action `load_server_emojis` (requires `nonce`, `bot_token`, `server_id`).
- The server caches emoji lists in a transient (default: 60 minutes) to avoid rate limits.
- Client behavior:
  - Buttons with class `.open-emoji-picker` open a centralized emoji picker modal.
  - The picker renders guild emojis in a grid and inserts codes like `<:name:id>` or `<a:name:id>` into the currently focused textarea or a target description.
  - Fully localized loading states and error messages.

Recent fixes and UX improvements related to emojis:
- Server: `load_server_emojis` returns `emojis` array and caches results using transients.
- Client:
  - Per-tab scoped handlers so main editor and live notifications handlers don't conflict.
  - Per-button original-label capture (`data('original-text')`) before changing to the loading label; restore in the AJAX `complete` handler.
  - Defensive insertion functions (accept jQuery elements, safe selection/position handling) to avoid runtime exceptions.
  - Complete internationalization of all user-facing text.

---

## ⚙️ Requirements

```
✅ WordPress 5.0+        ✅ PHP 7.4+
✅ Discord Bot Token     ✅ HTTP Outbound Requests  
✅ Server Permissions    ✅ StreamWeasels (optional)
```

- WordPress 5.0+ and PHP 7.4+
- For "Server Webhook" mode: a Discord Bot with proper permissions and the bot added to your server
- Outbound HTTP requests must be allowed from your WordPress host (wp_remote_* functions)

---

## 📦 Installation

```
   📥 INSTALLATION METHODS 📥
┌─────────────────────────────────┐
│  Method 1: WordPress Dashboard  │  ⭐ RECOMMENDED
│  Method 2: FTP Upload           │
│  Method 3: WP-CLI               │
└─────────────────────────────────┘
```

### 🎯 Method 1: WordPress Dashboard (Recommended!)
1. **Add Plugin:** Go to **Plugins** → **Add New** in your WordPress Admin
2. **Upload ZIP:** Click **Upload Plugin** 
3. **Choose File:** Select the `discord-embed-wp-plugin.zip` file
4. **Install:** Click **Install Now**
5. **Activate:** Click **Activate Plugin**
6. **Done!** The new **Discord Embeds** menu is available

### 🗂️ Method 2: FTP Upload
1. Extract the ZIP file
2. Upload the folder to `wp-content/plugins/`
3. Activate the plugin under **Plugins**

### 💻 Method 3: WP-CLI
```bash
wp plugin install discord-embed-wp-plugin.zip --activate
```

If upgrading from an earlier version that added live notifications, run any required database migrations via the plugin's automatic updater or via WP-CLI. One‑off migration scripts that were previously included in the repository have been removed from the plugin root and are no longer bundled with releases.

If you maintain self-hosted copies and need the original migration scripts, check your project's version control history or contact the plugin author for archive copies before proceeding.

---

## 🚀 Usage

```
        🎯 QUICK START GUIDE 🎯
┌──────────────────────────────────────┐
│  1️⃣  Select/create template   ✓     │
│  2️⃣  Build embed              ✓     │
│  3️⃣  Configure webhook        ✓     │
│  4️⃣  Save or send             ✓     │
│  5️⃣  Live Notifications setup ✓     │
└──────────────────────────────────────┘
```

1. **Template Management:** Select an existing template or start a new one
2. **Embed Builder:** Build your embed (title, description with Markdown, color, images, fields, author/footer, timestamp)
3. **Webhook Configuration:**
   - **Channel Webhook:** paste the webhook URL
   - **Server Webhook:** enter Bot Token + Server ID, then load and select a channel
4. **Send or Save:** Save the template or send the embed to Discord
5. **Live Notifications:** Configure templates and enable monitoring in the Live Notifications tab

---

## 🔧 Configuration Details

- **Webhook types**
  - Channel Webhook: posts directly to a webhook URL.
  - Server Webhook: sends via the Discord Bot API to a selected channel (requires bot token + server membership and permissions).

- **Optional fields on send**
  - `username` and `avatar_url` are supported when posting to webhooks.

---

## Database

On activation, the plugin creates/updates database tables:

- `wp_discord_embeds`
  - id, name, embed_data (JSON), created_at, updated_at
- `wp_discord_sent_messages`
  - id, discord_message_id, embed_data, webhook_url, channel_id, sent_at, status, webhook_type, error_message
- `wp_discord_live_notifications` (if live notifications enabled)
  - id, platform, channel_name, last_live_status, last_check, last_notification_sent, notification_count

---

## Cron

The Live Notifications scheduler uses WordPress cron. Use `wp_next_scheduled('discord_embed_check_live_status')` to inspect the next run and `do_action('discord_embed_check_live_status')` to trigger manually.

---

## API Integration

### Twitch API (Helix)
```php
// Endpoint
GET https://api.twitch.tv/helix/streams?user_login={channel}

// Headers
Client-ID: {twitch_client_id}
Authorization: Bearer {twitch_access_token}
```

### YouTube API (Data v3)
```php
// Endpoint  
GET https://www.googleapis.com/youtube/v3/search

// Parameters
part=snippet
channelId={channel_id}
eventType=live
type=video
key={youtube_api_key}
```

---

## Template System

Embed template example:
```json
{
  "title": "ᴛᴡɪᴛᴄʜ 🔴 🅻🅸🆅🅴 🎧",
  "description": "✨ Hey , STREAMER is live now at:\n📺 {url} !\n\nCome join the fun! 🚀",
  "color": 9442302,
  "thumbnail": {"url": "{thumbnail}"},
  "footer": {"text": "Live - "},
  "timestamp": "2024-01-01T12:00:00Z"
}
```

Available placeholders: `{platform}`, `{title}`, `{url}`, `{thumbnail}`

---

## JavaScript Integration & Events

Examples:
```javascript
// Tab switch to Live Notifications
$('#tab-live-notifications-link').click();

// Settings load
loadLiveNotificationSettings();

// Platform tests
testPlatform('twitch');
testPlatform('youtube');
```

Important notes:
- AJAX endpoints are protected by nonces and `manage_options` checks.
- Emoji picker uses `action=load_server_emojis` and requires Bot Token + Server ID.

---

## Debugging & Troubleshooting

Client-side debugging:
- Open browser DevTools and inspect console/network for AJAX calls: `load_server_emojis`, `load_server_roles`, `load_server_channels`, etc.

Server-side debugging:
- Enable WordPress debug logging (`WP_DEBUG` & `WP_DEBUG_LOG`) to see PHP error_log entries generated by the plugin.

Recent fixes you should know about:
- Complete internationalization: All German hardcoded strings replaced with WordPress i18n system
- Hardened markdown/insert functions to accept jQuery objects and avoid selection-related TypeErrors.
- Scoped emoji click handlers and per-button label capture/restore to prevent loading labels from becoming permanent.
- Fixed syntax errors in live-notifications.js that occurred during translation updates.

---

## 📝 Changelog

### 🎉 v2.3.5 (current)
```
🗂️ PROJECT REORGANIZATION 🗂️
├─ 📁 Moved all CSS/JS files to assets/ folder
├─ 🔧 Updated all file references in plugin
├─ 📋 Synchronized package-filelist.txt
├─ ✅ Maintained full functionality
├─ 🧹 Cleaner project structure
└─ 📦 Better maintainability
```

### 🎉 v2.3.4
```
🚀 MAJOR FEATURE UPDATE 🚀
├─ 🌍 Complete internationalization (EN/DE)
├─ 📄 MIT License migration from GPL
├─ 🎨 Redesigned README with ASCII art
├─ ✅ 20+ new localization strings
├─ 🔧 JavaScript syntax fixes
├─ 📦 Improved installation guide
└─ 🌟 Enhanced user experience
```

### 🌍 v2.2.9
- Complete internationalization: All user-facing text now uses WordPress i18n system
- Added localization strings for buttons, error messages, and status text
- Fixed JavaScript syntax errors in live-notifications.js
- Updated German and English .po translation files

### 🔧 v2.2.8
- Updated Live Notifications and emoji picker UX; defensive JS improvements; various bugfixes.

### 🚀 v2.1.0
- Added Live Notifications: StreamWeasels integration, Twitch/YouTube checks, templates, roles, cooldowns, cron scheduling.

---

## 📞 Support & License

```
   📋 PROJECT INFO 📋
┌─────────────────────────────┐
│  License: MIT License       │
│  Author: happytunesai       │
│  GitHub: Repository         │
└─────────────────────────────┘
```

- **📄 License:** MIT License
- **👨‍💻 Author:** happytunesai  
- **🔗 Repository:** https://github.com/happytunesai/discord-embed-wp-plugin

```
┌─────────────────────────────────────────────┐
│  © 2025 happytunesai. All rights reserved.  │
└─────────────────────────────────────────────┘
```
*Created with ❤️ + AI* ![EZ_STT_GUI_LOGO_150](https://github.com/user-attachments/assets/92017d80-a529-49bd-b56f-53ddd3bcabd7)
```