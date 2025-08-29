# Discord Embed Creator

**Version:** 2.1.0  
**Requires PHP:** 7.4+  
**Requires WordPress:** 5.0+  

A WordPress plugin to create and send Discord embeds with a live preview, template management, and flexible webhook/bot configurations.

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

- **UI/UX**  
  - Toast notifications instead of alert()  
  - Autofill prevention and small admin UI fixes  
  - Responsive layout: editor and live preview side by side

- **Debug & Logging**  
  - Optional console logs and PHP error_log entries for AJAX and API calls  
  - Built-in Debug Console on the admin page

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

---

## Usage

1. Select an existing template or start a new one.  
2. Build your embed (title, description with Markdown, color, images, fields, author/footer, timestamp).  
3. Configure the webhook:
   - Channel Webhook: paste the webhook URL.
   - Server Webhook: enter Bot Token + Server ID, then load and select a channel.
4. Save the template or send the embed to Discord.  
5. View sent messages in the history, filter by time range, or load a message by URL to edit it.

---

## Configuration Details

- **Webhook types**  
  - Channel Webhook: posts directly to a webhook URL.  
  - Server Webhook: sends via the Discord Bot API to a selected channel (requires bot token + server membership and permissions).

- **Optional fields on send**  
  - `username` and `avatar_url` are supported when posting to webhooks.

---

## Security Notes

- Do not commit or share secrets (e.g., bot tokens, webhook URLs) publicly.  
- Settings are stored as WordPress options; ensure proper capability checks (`manage_options`) are in place (already implemented).  
- Only administrators (`manage_options`) can access AJAX endpoints.

---

## Internationalization

- Text Domain: discord-embed-creator  
- Languages in `/languages` (includes .po/.mo files for English and German)

---

## Database

On activation, the plugin creates/updates database tables:

- `wp_discord_embeds`
  - id, name, embed_data (JSON), created_at, updated_at
- `wp_discord_sent_messages`
  - id, discord_message_id, embed_data, webhook_url, channel_id, sent_at, status, webhook_type, error_message

---

## Admin UI Entry Points (AJAX)

- `save_embed_template`, `load_embed_templates`, `load_embed_template`, `delete_embed_template`
- `send_discord_message`, `edit_discord_message`, `load_message_by_url`
- `load_server_channels`, `load_server_roles`
- `save_webhook_settings`, `load_webhook_settings`

---

## Project Structure (high level)

- `discord-embed-plugin.php` — main plugin file and hooks
- `includes/admin-page.php` — admin UI markup
- `assets/admin.css`, `assets/admin.js` — styles and logic for the editor/preview
- `webhook-config-modal.js`, `role-manager.js`, `toast-notifications.js`, `ui-fix.js` — admin helpers
- `languages/` — translations (.po/.mo)

---

## Known Limitations & Roadmap

- Minor UI quirks in some WordPress admin themes
- Occasional Discord API errors if rate limits are hit
- Improved validation for some fields planned
- Roadmap ideas: multi-embed payloads, richer template sharing, per-user permissions

---

## Support & License

- **License:** GPL v2 or later  
- **Author:** happytunesai  
- **Repository:** [https://github.com/happytunesai/discord-embed-wp-plugin](https://github.com/happytunesai/discord-embed-wp-plugin)

© 2025 happytunesai. All rights reserved.
