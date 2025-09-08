# Discord Embed Creator - Live Notifications Update v2.1.0

## 🆕 Neue Features

### 🔴 Live Benachrichtigungen für Twitch & YouTube

Das Plugin wurde um ein vollständiges Live-Notification System erweitert, das auf dem bestehenden Discord Embed System aufbaut.

## ✨ Features im Detail

### 🔗 StreamWeasels Integration
- Automatisches Auslesen der Twitch/YouTube API Credentials von StreamWeasels Plugin
- Unterstützt verschiedene StreamWeasels Option-Schlüssel automatisch
- Health Check für API-Verbindungen

### 🎯 Platform Support
- **Twitch**: Live Status Check über Helix API
- **YouTube**: Live Status Check über YouTube Data API v3
- Automatische Erkennung von Live-Streams alle 3 Minuten

### 📝 Embed Customization
- Vollständig anpassbare Live-Benachrichtigung Embeds
- Gleiche Editor-Funktionen wie im Haupt-Plugin
- Platzhalter-System: `{platform}`, `{title}`, `{url}`, `{thumbnail}`
- Markdown-Unterstützung für Beschreibungen

### 🏷️ Role Mentions
- Integration des bestehenden Role-Management Systems
- Automatisches Laden von Server-Rollen
- Multi-Role Auswahl für Live-Benachrichtigungen

### 🛡️ Anti-Spam System
- Configurable Cooldown zwischen Benachrichtigungen (Standard: 10 Minuten)
- Verhindert mehrfache Nachrichten für den gleichen Live-Stream
- Database-basiertes Tracking des Live-Status

### ⚙️ Discord Integration
- Unterstützt sowohl Webhook URLs als auch Bot Token + Channel Selection
- Nutzt die bestehende Discord API Integration
- Wiederverwendung der Webhook-Konfiguration aus dem Haupt-Plugin

## 🔧 Installation & Setup

### 1. Database Update
```bash
# Besuche diese URL in deinem Browser:
yoursite.com/wp-content/plugins/discord-embed-wp-plugin/update-database-v2.1.php
```

### 2. StreamWeasels Konfiguration
- Stelle sicher, dass StreamWeasels Plugin installiert und konfiguriert ist
- Verbinde Twitch API (Client ID + Access Token)
- Verbinde YouTube API (API Key)

### 3. Live Notifications Setup
1. Gehe zu **Discord Embeds → Live Notifications** Tab
2. Aktiviere die gewünschten Plattformen (Twitch/YouTube)
3. Konfiguriere Channel Names/IDs
4. Wähle Discord-Ziel (Webhook oder Bot)
5. Passe das Embed Template an
6. Wähle Rollen für Mentions
7. Speichere Einstellungen

## 📊 Cron System

Das Plugin nutzt WordPress Cron:
- **Hook**: `discord_embed_check_live_status`
- **Interval**: Alle 3 Minuten (`every_three_minutes`)
- **Funktion**: Prüft Live-Status und sendet Benachrichtigungen

### Cron Debugging
```php
// Nächste geplante Ausführung prüfen
wp_next_scheduled('discord_embed_check_live_status');

// Cron manuell ausführen
do_action('discord_embed_check_live_status');
```

## 🗄️ Database Schema

### Neue Tabelle: `wp_discord_live_notifications`
```sql
CREATE TABLE `wp_discord_live_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `platform` varchar(20) NOT NULL,
  `channel_name` varchar(100) NOT NULL,
  `last_live_status` tinyint(1) DEFAULT 0,
  `last_check` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_notification_sent` timestamp NULL DEFAULT NULL,
  `notification_count` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `platform_channel` (`platform`,`channel_name`),
  KEY `idx_last_check` (`last_check`)
);
```

## 🔌 API Integration

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

## 🎨 Template System

### Embed Template Format
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

### Verfügbare Platzhalter
- `{platform}` - "Twitch" oder "YouTube"
- `{title}` - Stream/Video Titel
- `{url}` - Direct Link zum Stream
- `{thumbnail}` - Thumbnail URL (falls verfügbar)

## 🚀 JavaScript API

### Events
```javascript
// Tab wechsel zu Live Notifications
$('#tab-live-notifications-link').click();

// Settings laden
loadLiveNotificationSettings();

// Platform testen
testPlatform('twitch');
testPlatform('youtube');
```

## 🔐 Sicherheit

- Alle AJAX-Calls mit WordPress Nonce geschützt
- `manage_options` Capability erforderlich
- Input-Sanitization für alle User-Inputs
- Password-Felder für sensible Daten

## 🐛 Debugging

### Debug Console erweitert
```javascript
// Live Notifications spezifische Logs
debugLog('Live notification sent', 'success');
debugLog('StreamWeasels credentials loaded', 'info');
debugLog('Platform test failed', 'error');
```

### WordPress Debug
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Logs in: wp-content/debug.log
```

## 📝 Changelog v2.1.0

### Added
- ✅ Neuer "Live Notifications" Tab
- ✅ StreamWeasels API Integration
- ✅ Twitch Live Status Checking
- ✅ YouTube Live Status Checking  
- ✅ Anpassbare Live Embed Templates
- ✅ Role Mentions für Live Notifications
- ✅ Anti-Spam Cooldown System
- ✅ Automatischer Cron für Live Checking
- ✅ Live Notification History Tracking
- ✅ Platform Test Funktionalität

### Technical
- ✅ Neue Database Table: `discord_live_notifications`
- ✅ WordPress Cron Integration
- ✅ AJAX Handler für Live Notifications
- ✅ JavaScript Module: `live-notifications.js`
- ✅ Erweiterte CSS Styles für Tabs
- ✅ Database Update Script

## 🎯 Nächste Features (geplant)

- 📊 Live Notification Analytics Dashboard
- 🔔 Custom Notification Sounds
- 📱 Mobile App Notifications
- 🎮 Weitere Gaming Platforms (Kick, etc.)
- 🤖 AI-generierte Stream Announcements

## 📞 Support

Bei Fragen oder Problemen:
1. Prüfe die Debug Console im Plugin
2. Aktiviere WordPress Debug Logging
3. Teste die StreamWeasels API Verbindungen
4. Überprüfe Cron Status

---
**Discord Embed Creator v2.1.0** - Erweitert mit Live Notifications
