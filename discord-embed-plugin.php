<?php
/**
 * Plugin Name: Discord Embed Creator
 * Plugin URI: https://github.com/happytunesai/discord-embed-wp-plugin
 * Description: Create and send Discord embeds with live preview and template management. Perfect for community managers and server administrators.
 * Version: 2.1.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: Zelika
 * Author URI: https://github.com/happytunesai
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: discord-embed-creator
 * Domain Path: /languages
 * Network: false
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
if (!defined('DISCORD_EMBED_PLUGIN_URL')) {
    define('DISCORD_EMBED_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('DISCORD_EMBED_PLUGIN_PATH')) {
    define('DISCORD_EMBED_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('DISCORD_EMBED_VERSION')) {
    define('DISCORD_EMBED_VERSION', '2.1.0');
}

class DiscordEmbedPlugin {
    
    private $debug = false; // Enable debug logging
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_save_embed_template', array($this, 'save_embed_template'));
        add_action('wp_ajax_load_embed_templates', array($this, 'load_embed_templates'));
        add_action('wp_ajax_load_embed_template', array($this, 'load_embed_template'));
        add_action('wp_ajax_delete_embed_template', array($this, 'delete_embed_template'));
        add_action('wp_ajax_send_discord_message', array($this, 'send_discord_message'));
        add_action('wp_ajax_load_message_history', array($this, 'load_message_history'));
        add_action('wp_ajax_load_server_channels', array($this, 'load_server_channels'));
        add_action('wp_ajax_save_webhook_settings', array($this, 'save_webhook_settings'));
        add_action('wp_ajax_load_webhook_settings', array($this, 'load_webhook_settings'));
        add_action('wp_ajax_edit_discord_message', array($this, 'edit_discord_message'));
        add_action('wp_ajax_load_message_by_url', array($this, 'load_message_by_url'));
        add_action('wp_ajax_load_server_roles', array($this, 'load_server_roles'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    private function log_debug($message, $data = null) {
        if (!$this->debug) return;
        
        $log_entry = '[' . date('Y-m-d H:i:s') . '] Discord Embed Plugin: ' . $message;
        if ($data !== null) {
            $log_entry .= ' | Data: ' . print_r($data, true);
        }
        error_log($log_entry);
    }

    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('discord-embed-creator', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }

    public function activate() {
        $this->create_tables();
    }

    public function deactivate() {
        // Cleanup if needed
    }

    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create embeds table - simplified schema
        $embeds_table = $wpdb->prefix . 'discord_embeds';
        $embeds_sql = "CREATE TABLE $embeds_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            embed_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_name (name)
        ) $charset_collate;";
        
        // Create sent messages table
        $messages_table = $wpdb->prefix . 'discord_sent_messages';
        $messages_sql = "CREATE TABLE $messages_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            discord_message_id varchar(100),
            embed_data longtext,
            webhook_url varchar(500),
            channel_id varchar(100),
            sent_at datetime DEFAULT CURRENT_TIMESTAMP,
            status varchar(50) DEFAULT 'sent',
            webhook_type varchar(50) DEFAULT 'channel',
            error_message text DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_sent_at (sent_at),
            INDEX idx_status (status),
            INDEX idx_discord_message_id (discord_message_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($embeds_sql);
        dbDelta($messages_sql);
        
        // Update version
        update_option('discord_embed_version', '1.3.0');
    }

    public function add_admin_menu() {
        add_menu_page(
            'Discord Embeds',
            'Discord Embeds',
            'manage_options',
            'discord-embeds',
            array($this, 'admin_page'),
            'dashicons-format-chat',
            30
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'toplevel_page_discord-embeds') {
            return;
        }
        
        wp_enqueue_script('discord-embed-admin', DISCORD_EMBED_PLUGIN_URL . 'assets/admin.js', array('jquery'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-webhook-config-modal', DISCORD_EMBED_PLUGIN_URL . 'webhook-config-modal.js', array('jquery'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-role-manager', DISCORD_EMBED_PLUGIN_URL . 'role-manager.js', array('jquery'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-toast-notifications', DISCORD_EMBED_PLUGIN_URL . 'toast-notifications.js', array('jquery'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-autofill-prevention', DISCORD_EMBED_PLUGIN_URL . 'autofill-prevention.js', array('jquery'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-ui-fix', DISCORD_EMBED_PLUGIN_URL . 'ui-fix.js', array('jquery', 'discord-embed-admin'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_style('discord-embed-admin', DISCORD_EMBED_PLUGIN_URL . 'assets/admin.css', array(), DISCORD_EMBED_VERSION);
        wp_enqueue_style('discord-autofill-prevention', DISCORD_EMBED_PLUGIN_URL . 'autofill-prevention.css', array(), DISCORD_EMBED_VERSION);
        
        wp_localize_script('discord-embed-admin', 'discordEmbed', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('discord_embed_nonce'),
            'debug' => true
        ));
        
        // Localize script for translations
        wp_localize_script('discord-toast-notifications', 'discordEmbedL10n', array(
            'confirm' => __('Confirm', 'discord-embed-creator'),
            'cancel' => __('Cancel', 'discord-embed-creator'),
            'deleteTemplateConfirm' => __('Do you really want to delete the template "%s"?', 'discord-embed-creator'),
            'cancelEditingConfirm' => __('Cancel editing? All unsaved changes will be lost.', 'discord-embed-creator'),
            'errorDeletingTemplate' => __('Error deleting template.', 'discord-embed-creator'),
            'deletionCancelled' => __('Deletion cancelled.', 'discord-embed-creator'),
            'errorSaving' => __('Error saving: %s', 'discord-embed-creator'),
            'errorSending' => __('Error sending: %s', 'discord-embed-creator'),
            'messageSentSuccessfully' => __('Message sent successfully!', 'discord-embed-creator'),
            'unknownError' => __('Unknown error', 'discord-embed-creator'),
            'errorSendingMessage' => __('Error sending message. Check console for details.', 'discord-embed-creator'),
            'sendToDiscord' => __('Send to Discord', 'discord-embed-creator'),
            'error' => __('Error: %s', 'discord-embed-creator'),
            'messageEditedSuccessfully' => __('Message edited successfully!', 'discord-embed-creator'),
            'errorEditing' => __('Error editing: %s', 'discord-embed-creator'),
            'errorEditingMessage' => __('Error editing message. See console for details.', 'discord-embed-creator'),
            'editMessage' => __('Edit Message', 'discord-embed-creator'),
            'messageCannotBeEdited' => __('Message cannot be edited - missing IDs.', 'discord-embed-creator'),
            'pleaseConfigureBotToken' => __('Please configure Bot Token and Server ID in the webhook settings first.', 'discord-embed-creator'),
            'pleaseFillBotToken' => __('Please fill in Bot Token and Channel ID.', 'discord-embed-creator'),
            'sending' => __('Sending...', 'discord-embed-creator'),
            'pleaseSelectDateTime' => __('Please select a date and time first.', 'discord-embed-creator'),
            'copied' => __('Copied!', 'discord-embed-creator'),
            'discordTimestampFormats' => __('Discord Timestamp Formats:', 'discord-embed-creator'),
            'embedDataNotFound' => __('Embed data not found.', 'discord-embed-creator')
        ));
    }

    public function admin_page() {
        include DISCORD_EMBED_PLUGIN_PATH . 'includes/admin-page.php';
    }

    public function save_embed_template() {
        $this->log_debug('Save template function called', $_POST);
        
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            $this->log_debug('Unauthorized access attempt');
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'discord_embeds';
        
        $template_name = sanitize_text_field($_POST['template_name'] ?? '');
        $embed_data_raw = $_POST['embed_data'] ?? '';
        
        // Clean and validate the JSON - remove WordPress slashes
        $embed_data = wp_unslash($embed_data_raw);
        
        $this->log_debug('Processing template save', array(
            'template_name' => $template_name,
            'embed_data_length' => strlen($embed_data),
            'embed_data_raw_length' => strlen($embed_data_raw),
            'table_name' => $table_name,
            'embed_data_sample' => substr($embed_data, 0, 200)
        ));
        
        if (empty($template_name)) {
            $this->log_debug('Template name is empty');
            wp_send_json_error('Template name is required');
            return;
        }
        
        // Validate JSON
        $decoded_data = json_decode($embed_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_debug('Invalid JSON data', array(
                'json_error' => json_last_error_msg(),
                'json_error_code' => json_last_error(),
                'embed_data_sample' => substr($embed_data, 0, 500)
            ));
            wp_send_json_error('Invalid embed data format: ' . json_last_error_msg());
            return;
        }
        
        // Re-encode the validated data to ensure it's clean
        $clean_embed_data = json_encode($decoded_data);
        
        // Check if template exists - only name column
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE name = %s",
            $template_name
        ));
        
        if ($existing) {
            // Update existing template
            $result = $wpdb->update(
                $table_name,
                array(
                    'name' => $template_name,
                    'embed_data' => $clean_embed_data, // Use cleaned data
                    'updated_at' => current_time('mysql')
                ),
                array('id' => $existing),
                array('%s', '%s', '%s'),
                array('%d')
            );
            
            $this->log_debug('Template update result', array(
                'existing_id' => $existing,
                'result' => $result,
                'wpdb_error' => $wpdb->last_error
            ));
        } else {
            // Create new template
            $result = $wpdb->insert(
                $table_name,
                array(
                    'name' => $template_name,
                    'embed_data' => $clean_embed_data, // Use cleaned data
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            $this->log_debug('Template insert result', array(
                'result' => $result,
                'insert_id' => $wpdb->insert_id,
                'wpdb_error' => $wpdb->last_error
            ));
        }
        
        if ($result === false) {
            $this->log_debug('Database operation failed', $wpdb->last_error);
            wp_send_json_error('Database operation failed: ' . $wpdb->last_error);
            return;
        }
        
        $this->log_debug('Template saved successfully');
        wp_send_json_success(array(
            'message' => 'Template saved successfully',
            'template_name' => $template_name
        ));
    }

    public function load_embed_templates() {
        $this->log_debug('Load templates function called');
        
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'discord_embeds';
        
        $templates = $wpdb->get_results("SELECT * FROM $table_name ORDER BY updated_at DESC");
        
        $this->log_debug('Templates loaded', array(
            'count' => count($templates),
            'wpdb_error' => $wpdb->last_error
        ));
        
        if ($wpdb->last_error) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }
        
        wp_send_json_success($templates);
    }

    public function load_embed_template() {
        $this->log_debug('Load single template function called', $_POST);
        
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $template_id = intval($_POST['template_id'] ?? 0);
        
        if ($template_id <= 0) {
            wp_send_json_error('Invalid template ID');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'discord_embeds';
        
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $template_id
        ));
        
        $this->log_debug('Template loaded', array(
            'template_id' => $template_id,
            'found' => !empty($template),
            'wpdb_error' => $wpdb->last_error
        ));
        
        if ($wpdb->last_error) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }
        
        if (!$template) {
            wp_send_json_error('Template not found');
            return;
        }
        
        // Parse embed data
        $embed_data = json_decode($template->embed_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_debug('Invalid JSON in template', array('json_error' => json_last_error_msg()));
            wp_send_json_error('Invalid template data');
            return;
        }
        
        wp_send_json_success($embed_data);
    }

    public function delete_embed_template() {
        $this->log_debug('Delete template function called', $_POST);
        
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $template_id = intval($_POST['template_id'] ?? 0);
        
        if ($template_id <= 0) {
            wp_send_json_error('Invalid template ID');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'discord_embeds';
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $template_id),
            array('%d')
        );
        
        $this->log_debug('Template delete result', array(
            'template_id' => $template_id,
            'result' => $result,
            'wpdb_error' => $wpdb->last_error
        ));
        
        if ($result === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }
        
        if ($result === 0) {
            wp_send_json_error('Template not found');
            return;
        }
        
        wp_send_json_success('Template deleted successfully');
    }

    public function send_discord_message() {
        $this->log_debug('Send Discord message function called', $_POST);
        
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $webhook_type = sanitize_text_field($_POST['webhook_type'] ?? 'channel');
        $embed_data_raw = $_POST['embed_data'] ?? '';
        
        // Clean the embed data
        $embed_data = wp_unslash($embed_data_raw);
        
        // Validate JSON
        $decoded_embed = json_decode($embed_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_debug('Invalid embed JSON for sending', array(
                'json_error' => json_last_error_msg(),
                'embed_data_sample' => substr($embed_data, 0, 500)
            ));
            wp_send_json_error('Invalid embed data format: ' . json_last_error_msg());
            return;
        }
        
        if ($webhook_type === 'server') {
            $bot_token = sanitize_text_field($_POST['bot_token'] ?? '') ?: get_option('discord_embed_bot_token', '');
            $channel_id = sanitize_text_field($_POST['channel_id'] ?? '') ?: get_option('discord_embed_channel_id', '');
            
            if (empty($bot_token) || empty($channel_id)) {
                wp_send_json_error('Bot token and channel ID are required for server webhooks. Please configure them in the settings.');
                return;
            }
            
            // Send via Discord Bot API
            $url = "https://discord.com/api/v10/channels/$channel_id/messages";
            $headers = array(
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json; charset=utf-8'
            );
            $webhook_to_save = null; // Not using a webhook URL directly
        } else {
            $webhook_url = esc_url_raw($_POST['webhook_url'] ?? '') ?: get_option('discord_embed_webhook_url', '');
            
            if (empty($webhook_url)) {
                wp_send_json_error('Webhook URL is required. Please configure it in the settings.');
                return;
            }
            
            $url = $webhook_url;
            $headers = array('Content-Type' => 'application/json; charset=utf-8');
            $webhook_to_save = $webhook_url;
            $channel_id = null; // Not using a channel ID directly
        }
        
        $payload = json_decode($embed_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid embed data for payload.');
            return;
        }
        
        // Add username and avatar_url if they are set in the form
        $username = sanitize_text_field($_POST['username'] ?? '');
        $avatar_url = esc_url_raw($_POST['avatar_url'] ?? '');

        if (!empty($username)) {
            $payload['username'] = $username;
        }
        if (!empty($avatar_url)) {
            $payload['avatar_url'] = $avatar_url;
        }

        $body = array('embeds' => array($payload));
        
        $response = wp_remote_post($url, array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => wp_json_encode($body),
            'timeout' => 30,
            'httpversion' => '1.1'
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_debug('Discord API error', $error_message);
            wp_send_json_error('Failed to send message: ' . $error_message);
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Parse Discord response to get message ID
        $discord_message_id = null;
        if ($response_code === 200 || $response_code === 204) {
            $discord_response = json_decode($response_body, true);
            if (isset($discord_response['id'])) {
                $discord_message_id = $discord_response['id'];
            }
        }
        
        // Log the message
        global $wpdb;
        $messages_table = $wpdb->prefix . 'discord_sent_messages';
        
        $wpdb->insert(
            $messages_table,
            array(
                'discord_message_id' => $discord_message_id,
                'embed_data' => $embed_data,
                'webhook_url' => $webhook_type === 'server' ? $url : ($webhook_url ?? ''),
                'channel_id' => $webhook_type === 'server' ? $channel_id : '',
                'sent_at' => current_time('mysql'),
                'status' => ($response_code === 200 || $response_code === 204) ? 'sent' : 'failed',
                'webhook_type' => $webhook_type,
                'error_message' => ($response_code !== 200 && $response_code !== 204) ? $response_body : null
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($response_code === 200 || $response_code === 204) {
            $this->log_debug('Message sent successfully');
            wp_send_json_success('Message sent successfully');
        } else {
            $this->log_debug('Discord API error', array('code' => $response_code, 'body' => $response_body));
            wp_send_json_error('Failed to send message. Response code: ' . $response_code . '. ' . $response_body);
        }
    }

    public function load_message_history() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'discord_sent_messages';
        
        // Pagination parameters
        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 10);
        $filter = sanitize_text_field($_POST['filter'] ?? 'all');
        
        // Calculate offset
        $offset = ($page - 1) * $per_page;
        
        // Build WHERE clause based on filter
        $where_clause = '';
        switch ($filter) {
            case 'today':
                $where_clause = "WHERE DATE(sent_at) = CURDATE()";
                break;
            case 'week':
                $where_clause = "WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $where_clause = "WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case 'all':
            default:
                $where_clause = '';
                break;
        }
        
        // Get total count for pagination
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_clause");
        
        // Get messages with pagination
        $query = "SELECT * FROM $table_name $where_clause ORDER BY sent_at DESC LIMIT $per_page OFFSET $offset";
        $messages = $wpdb->get_results($query);
        
        if ($wpdb->last_error) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }
        
        $has_more = ($offset + $per_page) < $total_count;
        
        wp_send_json_success(array(
            'messages' => $messages,
            'page' => $page,
            'per_page' => $per_page,
            'total' => $total_count,
            'has_more' => $has_more,
            'filter' => $filter
        ));
    }

    public function load_server_channels() {
        $this->log_debug('=== LOAD SERVER CHANNELS START ===');
        
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $server_id = sanitize_text_field($_POST['server_id'] ?? '');
        $bot_token = sanitize_text_field($_POST['bot_token'] ?? '');
        
        $this->log_debug('Request data', array(
            'bot_token_length' => strlen($bot_token),
            'server_id' => $server_id,
            'post_data' => array_keys($_POST)
        ));
        
        if (empty($server_id) || empty($bot_token)) {
            wp_send_json_error('Server ID and bot token are required');
            return;
        }
        
        $url = "https://discord.com/api/v10/guilds/$server_id/channels";
        
        $this->log_debug('Making Discord API request', array(
            'url' => $url
        ));
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_debug('WP Remote error', $error_message);
            wp_send_json_error('Failed to load channels: ' . $error_message);
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        $this->log_debug('Discord API response', array(
            'code' => $response_code,
            'body_length' => strlen($response_body)
        ));
        
        if ($response_code !== 200) {
            $error_message = 'Failed to load channels. Response code: ' . $response_code;
            if ($response_code === 401) {
                $error_message = 'Ungültiger Bot Token. Überprüfen Sie das Token im Discord Developer Portal.';
            } elseif ($response_code === 403) {
                $error_message = 'Bot hat keine Berechtigung, Kanäle zu lesen. Stellen Sie sicher, dass der Bot die richtigen Permissions hat.';
            } elseif ($response_code === 404) {
                $error_message = 'Server nicht gefunden oder Bot ist nicht Mitglied des Servers. Server ID: ' . $server_id;
            }
            wp_send_json_error($error_message);
            return;
        }
        
        $channels = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_debug('JSON decode error', json_last_error_msg());
            wp_send_json_error('Invalid response from Discord API');
            return;
        }
        
        // Filter only text channels (type 0) and announcement channels (type 5)
        $text_channels = array_filter($channels, function($channel) {
            return isset($channel['type']) && ($channel['type'] === 0 || $channel['type'] === 5);
        });
        
        $this->log_debug('Channels processed successfully', array(
            'total_channels' => count($channels),
            'text_channels' => count($text_channels)
        ));
        
        wp_send_json_success(array('channels' => array_values($text_channels)));
    }
    
    public function save_webhook_settings() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $webhook_type = sanitize_text_field($_POST['webhook_type'] ?? '');
        $bot_token = sanitize_text_field($_POST['bot_token'] ?? '');
        $server_id = sanitize_text_field($_POST['server_id'] ?? '');
        $channel_id = sanitize_text_field($_POST['channel_id'] ?? '');
        $webhook_url = esc_url_raw($_POST['webhook_url'] ?? '');
        
        // Save webhook settings to WordPress options
        update_option('discord_embed_webhook_type', $webhook_type);
        update_option('discord_embed_bot_token', $bot_token);
        update_option('discord_embed_server_id', $server_id);
        update_option('discord_embed_channel_id', $channel_id);
        update_option('discord_embed_webhook_url', $webhook_url);
        
        $this->log_debug('Webhook settings saved', array(
            'webhook_type' => $webhook_type,
            'server_id' => $server_id,
            'channel_id' => $channel_id,
            'webhook_url' => $webhook_url ? 'Yes' : 'No',
            'bot_token' => $bot_token ? 'Yes' : 'No'
        ));
        
        wp_send_json_success('Webhook settings saved successfully');
    }
    
    /**
     * Load webhook settings from WordPress options
     */
    public function load_webhook_settings() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $settings = array(
            'webhook_type' => get_option('discord_embed_webhook_type', 'channel'),
            'bot_token' => get_option('discord_embed_bot_token', ''),
            'server_id' => get_option('discord_embed_server_id', ''),
            'channel_id' => get_option('discord_embed_channel_id', ''),
            'webhook_url' => get_option('discord_embed_webhook_url', '')
        );
        
        $this->log_debug('Webhook settings loaded from database', array(
            'webhook_type' => $settings['webhook_type'],
            'server_id' => $settings['server_id'],
            'channel_id' => $settings['channel_id'],
            'webhook_url' => $settings['webhook_url'] ? 'Yes' : 'No',
            'bot_token' => $settings['bot_token'] ? 'Yes' : 'No'
        ));
        
        wp_send_json_success($settings);
    }
    
    /**
     * Edit an existing Discord message
     */
    public function edit_discord_message() {
        $this->log_debug('Edit Discord message function called', $_POST);
        
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $message_id = sanitize_text_field($_POST['message_id'] ?? '');
        $channel_id = sanitize_text_field($_POST['channel_id'] ?? '');
        $embed_data_raw = $_POST['embed_data'] ?? '';
        $bot_token = sanitize_text_field($_POST['bot_token'] ?? '') ?: get_option('discord_embed_bot_token', '');
        
        if (empty($message_id) || empty($channel_id) || empty($bot_token)) {
            wp_send_json_error('Message ID, Channel ID and Bot Token are required for editing messages.');
            return;
        }
        
        // Clean the embed data
        $embed_data = wp_unslash($embed_data_raw);
        
        // Validate JSON
        $decoded_embed = json_decode($embed_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid embed data format: ' . json_last_error_msg());
            return;
        }
        
        // Discord API endpoint for editing messages
        $url = "https://discord.com/api/v10/channels/$channel_id/messages/$message_id";
        
        $headers = array(
            'Authorization' => 'Bot ' . $bot_token,
            'Content-Type' => 'application/json; charset=utf-8'
        );
        
        $payload = json_decode($embed_data, true);
        
        $args = array(
            'method' => 'PATCH',
            'headers' => $headers,
            'body' => json_encode($payload),
            'timeout' => 30
        );
        
        $this->log_debug('Editing Discord message', array(
            'url' => $url,
            'payload_size' => strlen(json_encode($payload))
        ));
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_debug('Discord API error', $error_message);
            wp_send_json_error('Failed to edit message: ' . $error_message);
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $this->log_debug('Message edited successfully');
            wp_send_json_success('Message edited successfully');
        } else {
            $this->log_debug('Discord API edit error', array('code' => $response_code, 'body' => $response_body));
            wp_send_json_error('Failed to edit message. Response code: ' . $response_code . '. ' . $response_body);
        }
    }
    
    /**
     * Load message data from Discord URL
     */
    public function load_message_by_url() {
        $this->log_debug('Load message by URL function called', $_POST);
        
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $discord_url = esc_url_raw($_POST['discord_url'] ?? '');
        $bot_token = sanitize_text_field($_POST['bot_token'] ?? '') ?: get_option('discord_embed_bot_token', '');
        
        if (empty($discord_url) || empty($bot_token)) {
            wp_send_json_error('Discord URL and Bot Token are required.');
            return;
        }
        
        // Parse Discord URL: https://discord.com/channels/server_id/channel_id/message_id
        $pattern = '/https:\/\/discord\.com\/channels\/(\d+)\/(\d+)\/(\d+)/';
        if (!preg_match($pattern, $discord_url, $matches)) {
            wp_send_json_error('Invalid Discord message URL format. Expected: https://discord.com/channels/server_id/channel_id/message_id');
            return;
        }
        
        $server_id = $matches[1];
        $channel_id = $matches[2];
        $message_id = $matches[3];
        
        // Discord API endpoint for getting message
        $url = "https://discord.com/api/v10/channels/$channel_id/messages/$message_id";
        
        $headers = array(
            'Authorization' => 'Bot ' . $bot_token,
            'Content-Type' => 'application/json; charset=utf-8'
        );
        
        $args = array(
            'method' => 'GET',
            'headers' => $headers,
            'timeout' => 30
        );
        
        $this->log_debug('Fetching Discord message', array(
            'url' => $url,
            'server_id' => $server_id,
            'channel_id' => $channel_id,
            'message_id' => $message_id
        ));
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_debug('Discord API error', $error_message);
            wp_send_json_error('Failed to fetch message: ' . $error_message);
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $message_data = json_decode($response_body, true);
            
            // Extract embed data from the message
            $embed_data = null;
            if (isset($message_data['embeds']) && !empty($message_data['embeds'])) {
                $embed_data = $message_data['embeds'][0]; // Take first embed
            }
            
            $this->log_debug('Message fetched successfully', array(
                'has_embeds' => isset($message_data['embeds']),
                'embed_count' => isset($message_data['embeds']) ? count($message_data['embeds']) : 0
            ));
            
            wp_send_json_success(array(
                'message_id' => $message_id,
                'channel_id' => $channel_id,
                'server_id' => $server_id,
                'embed_data' => $embed_data,
                'full_message' => $message_data
            ));
        } else {
            $this->log_debug('Discord API fetch error', array('code' => $response_code, 'body' => $response_body));
            wp_send_json_error('Failed to fetch message. Response code: ' . $response_code . '. ' . $response_body);
        }
    }
    
    /**
     * AJAX handler to load server roles via Discord API
     */
    public function load_server_roles() {
        $this->log_debug('=== LOAD SERVER ROLES START ===');
        
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'discord_embed_nonce')) {
            $this->log_debug('Invalid nonce provided');
            wp_send_json_error('Invalid nonce');
            return;
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            $this->log_debug('User lacks manage_options capability');
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $bot_token = sanitize_text_field($_POST['bot_token'] ?? '');
        $server_id = sanitize_text_field($_POST['server_id'] ?? '');
        
        $this->log_debug('Request data', array(
            'bot_token_length' => strlen($bot_token),
            'server_id' => $server_id,
            'post_data' => array_keys($_POST)
        ));
        
        if (empty($bot_token) || empty($server_id)) {
            $this->log_debug('Missing required parameters');
            wp_send_json_error('Bot Token und Server ID sind erforderlich');
            return;
        }
        
        // Validate bot token format
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $bot_token)) {
            $this->log_debug('Invalid bot token format');
            wp_send_json_error('Ungültiges Bot Token Format');
            return;
        }
        
        // Validate server ID format
        if (!preg_match('/^\d+$/', $server_id)) {
            $this->log_debug('Invalid server ID format');
            wp_send_json_error('Ungültige Server ID (nur Zahlen erlaubt)');
            return;
        }
        
        $this->log_debug('Loading server roles', array('server_id' => $server_id));
        
        // Discord API endpoint for guild roles
        $api_url = "https://discord.com/api/v10/guilds/{$server_id}/roles";
        
        // Prepare headers
        $headers = array(
            'Authorization' => 'Bot ' . $bot_token,
            'Content-Type' => 'application/json',
            'User-Agent' => 'Discord Embed Plugin/1.4.0'
        );
        
        $this->log_debug('Making Discord API request', array(
            'url' => $api_url,
            'headers' => array_keys($headers)
        ));
        
        // Make API request
        $response = wp_remote_get($api_url, array(
            'headers' => $headers,
            'timeout' => 30,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_debug('WP Remote error', $error_message);
            wp_send_json_error('Network error: ' . $error_message);
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        $this->log_debug('Discord API response', array(
            'code' => $response_code,
            'body_length' => strlen($response_body),
            'content_type' => $response_headers['content-type'] ?? 'unknown',
            'rate_limit_remaining' => $response_headers['x-ratelimit-remaining'] ?? 'unknown'
        ));
        
        if ($response_code === 200) {
            $roles_data = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log_debug('JSON decode error', json_last_error_msg());
                wp_send_json_error('Invalid JSON response from Discord API: ' . json_last_error_msg());
                return;
            }
            
            if (!is_array($roles_data)) {
                $this->log_debug('Unexpected response format', gettype($roles_data));
                wp_send_json_error('Unexpected response format from Discord API');
                return;
            }
            
            $this->log_debug('Raw roles data', array(
                'total_roles' => count($roles_data),
                'sample_role' => count($roles_data) > 0 ? $roles_data[0] : null
            ));
            
            // Filter and format roles for frontend
            $formatted_roles = array();
            foreach ($roles_data as $role) {
                // Include @everyone but mark it specially
                $is_everyone = $role['name'] === '@everyone';
                
                // Skip managed roles (bots) unless it's @everyone
                if (!$is_everyone && isset($role['managed']) && $role['managed'] === true) {
                    continue;
                }
                
                $formatted_roles[] = array(
                    'id' => $role['id'],
                    'name' => $role['name'],
                    'color' => intval($role['color']), // Discord uses decimal color values
                    'position' => intval($role['position']),
                    'mentionable' => $role['mentionable'] ?? true,
                    'hoist' => $role['hoist'] ?? false,
                    'everyone' => $is_everyone
                );
            }
            
            // Sort by position (higher position = more important), but keep @everyone at bottom
            usort($formatted_roles, function($a, $b) {
                if ($a['everyone']) return 1;
                if ($b['everyone']) return -1;
                return $b['position'] - $a['position'];
            });
            
            $this->log_debug('Roles processed successfully', array(
                'total_roles' => count($roles_data),
                'filtered_roles' => count($formatted_roles),
                'roles_preview' => array_slice($formatted_roles, 0, 3)
            ));
            
            wp_send_json_success(array(
                'roles' => $formatted_roles,
                'total' => count($formatted_roles),
                'server_id' => $server_id,
                'debug' => array(
                    'raw_count' => count($roles_data),
                    'filtered_count' => count($formatted_roles),
                    'timestamp' => current_time('mysql')
                )
            ));
            
        } elseif ($response_code === 401) {
            $this->log_debug('Unauthorized - invalid token');
            wp_send_json_error('Ungültiger Bot Token. Überprüfen Sie das Token im Discord Developer Portal.');
        } elseif ($response_code === 403) {
            $this->log_debug('Forbidden - insufficient permissions');
            wp_send_json_error('Bot hat keine Berechtigung, Rollen zu lesen. Stellen Sie sicher, dass der Bot Mitglied des Servers ist.');
        } elseif ($response_code === 404) {
            $this->log_debug('Not found - server not found or bot not member');
            wp_send_json_error('Server nicht gefunden oder Bot ist nicht Mitglied des Servers.');
        } elseif ($response_code === 429) {
            $this->log_debug('Rate limited');
            wp_send_json_error('Discord API Rate Limit erreicht. Versuchen Sie es in ein paar Minuten erneut.');
        } else {
            $this->log_debug('Discord API error', array(
                'code' => $response_code, 
                'body' => substr($response_body, 0, 500)
            ));
            wp_send_json_error('Discord API Fehler: HTTP ' . $response_code . '. ' . substr($response_body, 0, 100));
        }
    }
}

// Initialize the plugin only if class doesn't exist
if (!class_exists('DiscordEmbedPlugin') || !isset($GLOBALS['discord_embed_plugin_instance'])) {
    $GLOBALS['discord_embed_plugin_instance'] = new DiscordEmbedPlugin();
}
