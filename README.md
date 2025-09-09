# Discord Embed Creator

**Version:** 2.2.5 
**Requires PHP:** 7.4+  
**Requires WordPress:** 5.0+  

A WordPress plugin to create and send Discord embeds with a live preview, template management, flexible webhook/bot configurations and an optional Live Notifications system for Twitch & YouTube.

---

## Table of contents
- Features
- Live Notifications (Twitch & YouTube)
- Emoji fetcher (server guild emojis)
- Requirements
- Installation
- Usage
- Configuration Details
- Database
- Cron
- API Integration
- Template System
- JavaScript Integration
- Debugging & Troubleshooting
- Changelog
- Support & License

---

## Features

- **Embed Builder**
  - Title, description (Markdown), URL, color, timestamp, author, footer, images, custom fields
  - Live preview styled like Discord
  - Role mentions helper (inserts <@&ROLEID>)

- **Template Management**
  - Save, load, and delete embed templates
  - Stored in the database and loaded via AJAX

- **Webhook Configuration**
  - Channel Webhook: use a standard webhook URL
  - Server Webhook: use Bot Token + Server ID and select channels via Discord API
  - Test connection in a configuration modal
  - Persistent settings saved via WordPress options

- **Message Management**
  - Send via channel webhook or Bot API
  - History of sent messages with filters (today, week, month)
  - Load an existing Discord message by URL and edit it

- **Live Notifications (optional)**
  - Monitor Twitch and YouTube channels and post live notifications automatically
  - Integrates with StreamWeasels for Twitch/YouTube credentials (optional)
  - Configurable templates, role mentions and cooldown anti-spam

- **Emoji Fetcher**
  - Load guild emojis via Bot Token + Server ID (for custom emoji insertion into templates)
  - Client-side emoji picker with transient caching on the server

- **UI/UX**
  - Toast notifications instead of alert() (where available)
  - Autofill prevention and small admin UI fixes
  - Responsive layout: editor and live preview side by side

- **Debug & Logging**
  - Optional console logs and PHP error_log entries for AJAX and API calls
  - Built-in Debug Console on the admin page

---

## Live Notifications (Twitch & YouTube)

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

## Emoji fetcher (guild emojis)

The plugin can load custom guild emojis from Discord so you can insert them into descriptions or fields. Key points:

- Server endpoint: AJAX action `load_server_emojis` (requires `nonce`, `bot_token`, `server_id`).
- The server caches emoji lists in a transient (default: 60 minutes) to avoid rate limits.
- Client behavior:
  - Buttons with class `.open-emoji-picker` open a centralized emoji picker modal.
  - The picker renders guild emojis in a grid and inserts codes like `<:name:id>` or `<a:name:id>` into the currently focused textarea or a target description.
  - Buttons temporarily show `Lade Emojis...` while loading; recent fixes ensure the original button labels are preserved and restored correctly.

Recent fixes and UX improvements related to emojis:
- Server: `load_server_emojis` returns `emojis` array and caches results using transients.
- Client:
  - Per-tab scoped handlers so main editor and live notifications handlers don't conflict.
  - Per-button original-label capture (`data('original-text')`) before changing to the loading label; restore in the AJAX `complete` handler.
  - Defensive insertion functions (accept jQuery elements, safe selection/position handling) to avoid runtime exceptions that previously caused 'undefined' to be inserted.

---

## Requirements

- WordPress 5.0+ and PHP 7.4+
- For "Server Webhook" mode: a Discord Bot with proper permissions and the bot added to your server
- Outbound HTTP requests must be allowed from your WordPress host (wp_remote_* functions)

---

## Installation

1. Copy this folder into `wp-content/plugins/` (or install a zipped archive via the WordPress dashboard).
2. Activate the plugin under **Plugins**.
3. Open the new menu item: **Discord Embeds**.

If upgrading from an earlier version that added live notifications, run any required database migrations via the plugin's automatic updater or via WP-CLI. One‑off migration scripts that were previously included in the repository have been removed from the plugin root and are no longer bundled with releases.

If you maintain self-hosted copies and need the original migration scripts, check your project's version control history or contact the plugin author for archive copies before proceeding.

---

## Usage

1. Select an existing template or start a new one.
2. Build your embed (title, description with Markdown, color, images, fields, author/footer, timestamp).
3. Configure the webhook:
   - Channel Webhook: paste the webhook URL.
   - Server Webhook: enter Bot Token + Server ID, then load and select a channel.
4. Save the template or send the embed to Discord.
5. For Live Notifications, configure templates and enable monitoring in the Live Notifications tab.

---

## Configuration Details

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
  "title": "🟣 Live auf {platform}!",
  "description": "**{title}**\n\nKomm vorbei und schau zu!\n\n[Zum Stream]({url})",
  "color": 9442302,
  "thumbnail": {"url": "{thumbnail}"},
  "footer": {"text": "Live seit"},
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
- Hardened markdown/insert functions to accept jQuery objects and avoid selection-related TypeErrors.
- Scoped emoji click handlers and per-button label capture/restore to prevent the "Lade Emojis..." label from becoming permanent.

---

## Changelog

### v2.2.3.1 (current)
- Updated Live Notifications and emoji picker UX; defensive JS improvements; various bugfixes.

### v2.1.0
- Added Live Notifications: StreamWeasels integration, Twitch/YouTube checks, templates, roles, cooldowns, cron scheduling.

---

## Support & License

- **License:** GPL v2 or later
- **Author:** happytunesai
- **Repository:** https://github.com/happytunesai/discord-embed-wp-plugin

© 2025 happytunesai. All rights reserved.
