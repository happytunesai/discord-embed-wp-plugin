<?php
/**
 * Plugin Name: Discord Embed Creator
 * Plugin URI: https://github.com/happytunesai/discord-embed-wp-plugin
 * Description: Create and send Discord embeds with live preview and template management. Perfect for community managers and server administrators.
 * Version: 2.4.3
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author: HappyTunesAI
 * Author URI: https://github.com/happytunesai
 * License: MIT License
 * License URI: https://opensource.org/licenses/MIT
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
    define('DISCORD_EMBED_VERSION', '2.4.3');
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
        
        // Twitch/YouTube Live Notifications AJAX actions
        add_action('wp_ajax_save_live_notification_settings', array($this, 'save_live_notification_settings'));
        add_action('wp_ajax_load_live_notification_settings', array($this, 'load_live_notification_settings'));
        add_action('wp_ajax_test_live_notification', array($this, 'test_live_notification'));
        add_action('wp_ajax_check_streamweasels_credentials', array($this, 'check_streamweasels_credentials'));
        add_action('wp_ajax_toggle_live_notifications', array($this, 'toggle_live_notifications'));
    add_action('wp_ajax_load_server_emojis', array($this, 'load_server_emojis'));
    // Saved live notification templates (CRUD)
    add_action('wp_ajax_save_live_notification_template', array($this, 'save_live_notification_template'));
    add_action('wp_ajax_load_live_notification_templates', array($this, 'load_live_notification_templates'));
    add_action('wp_ajax_load_live_notification_template', array($this, 'load_live_notification_template'));
    add_action('wp_ajax_delete_live_notification_template', array($this, 'delete_live_notification_template'));
    add_action('wp_ajax_toggle_live_notification_template', array($this, 'toggle_live_notification_template'));
    // Debug helper to list live templates (admin only)
    add_action('wp_ajax_debug_list_live_templates', array($this, 'debug_list_live_templates'));
    add_action('wp_ajax_send_saved_live_notification', array($this, 'send_saved_live_notification'));
        
        // Cron for live checking
        add_action('discord_embed_check_live_status', array($this, 'check_live_status'));
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        
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
        
        // Schedule live checking cron if not already scheduled
        if (!wp_next_scheduled('discord_embed_check_live_status')) {
            wp_schedule_event(time() + 180, 'every_three_minutes', 'discord_embed_check_live_status');
        }
    }

    public function deactivate() {
        // Clear scheduled cron
        wp_clear_scheduled_hook('discord_embed_check_live_status');
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
        
        // Create live notifications table
        $live_notifications_table = $wpdb->prefix . 'discord_live_notifications';
        $live_notifications_sql = "CREATE TABLE $live_notifications_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            platform varchar(20) NOT NULL,
            channel_name varchar(100) NOT NULL,
            last_live_status tinyint(1) DEFAULT 0,
            last_check datetime DEFAULT CURRENT_TIMESTAMP,
            last_notification_sent datetime NULL,
            notification_count int DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY platform_channel (platform, channel_name),
            INDEX idx_last_check (last_check)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($embeds_sql);
        dbDelta($messages_sql);
        dbDelta($live_notifications_sql);

        // Create saved live notification templates table
        $live_templates_table = $wpdb->prefix . 'discord_live_notification_templates';
        $live_templates_sql = "CREATE TABLE $live_templates_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            platform varchar(20) NOT NULL,
            config longtext NOT NULL,
            enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_platform (platform)
        ) $charset_collate;";

        dbDelta($live_templates_sql);
        
        // Update version
        update_option('discord_embed_version', '2.1.0');
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
        wp_enqueue_script('discord-webhook-config-modal', DISCORD_EMBED_PLUGIN_URL . 'assets/webhook-config-modal.js', array('jquery'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-role-manager', DISCORD_EMBED_PLUGIN_URL . 'assets/role-manager.js', array('jquery'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-toast-notifications', DISCORD_EMBED_PLUGIN_URL . 'assets/toast-notifications.js', array('jquery'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-autofill-prevention', DISCORD_EMBED_PLUGIN_URL . 'assets/autofill-prevention.js', array('jquery'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-ui-fix', DISCORD_EMBED_PLUGIN_URL . 'assets/ui-fix.js', array('jquery', 'discord-embed-admin'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_script('discord-live-notifications', DISCORD_EMBED_PLUGIN_URL . 'assets/live-notifications.js', array('jquery', 'discord-embed-admin'), DISCORD_EMBED_VERSION, true);
        wp_enqueue_style('discord-embed-admin', DISCORD_EMBED_PLUGIN_URL . 'assets/admin.css', array(), DISCORD_EMBED_VERSION);
        wp_enqueue_style('discord-autofill-prevention', DISCORD_EMBED_PLUGIN_URL . 'assets/autofill-prevention.css', array(), DISCORD_EMBED_VERSION);
        
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
            // Live Notifications translations
            'connectedAndReady' => __('Connected and ready', 'discord-embed-creator'),
            'notConfiguredOrMissingCredentials' => __('Not configured or missing credentials', 'discord-embed-creator'),
            'updating' => __('🔄 Updating...', 'discord-embed-creator'),
            'refreshStatus' => __('🔄 Refresh Status', 'discord-embed-creator'),
            'pleaseBotTokenServerId' => __('Please enter Bot Token and Server ID', 'discord-embed-creator'),
            'errorLoadingChannels' => __('Error loading channels: %s', 'discord-embed-creator'),
            'errorLoadingRoles' => __('Error loading roles', 'discord-embed-creator'),
            'loadChannels' => __('Load Channels', 'discord-embed-creator'),
            'testPlatform' => __('Test Platform', 'discord-embed-creator'),
            'saveLiveSettings' => __('Save Live Settings', 'discord-embed-creator'),
            'testLiveNotification' => __('Test Live Notification', 'discord-embed-creator'),
            'saveAsTemplate' => __('Save as Template', 'discord-embed-creator'),
            'copied' => __('Copied!', 'discord-embed-creator'),
            'discordTimestampFormats' => __('Discord Timestamp Formats:', 'discord-embed-creator'),
            'embedDataNotFound' => __('Embed data not found.', 'discord-embed-creator')
            // Live notifications localization
            , 'loadingEmojis' => __('Loading Emojis...', 'discord-embed-creator')
            , 'pleaseEnterBotTokenServerId' => __('Please enter Bot Token and Server ID', 'discord-embed-creator')
            , 'refreshing' => __('🔄 Refreshing...', 'discord-embed-creator')
            , 'refreshStatus' => __('🔄 Refresh Status', 'discord-embed-creator')
            , 'statusActive' => __('Status: Active', 'discord-embed-creator')
            , 'statusDisabled' => __('Status: Disabled', 'discord-embed-creator')
            , 'loadingGeneric' => __('Loading...', 'discord-embed-creator')
            , 'errorLoadingChannels' => __('Error loading channels: ', 'discord-embed-creator')
            , 'loadChannels' => __('Load Channels', 'discord-embed-creator')
            , 'errorLoadingRoles' => __('Error loading roles', 'discord-embed-creator')
            , 'errorLoadingEmojis' => __('Error loading emojis: ', 'discord-embed-creator')
            , 'errorProcessingEmojis' => __('Error processing emojis: ', 'discord-embed-creator')
            , 'enterTwitchChannelName' => __('Please enter Twitch Channel Name', 'discord-embed-creator')
            , 'enterYoutubeChannelId' => __('Please enter YouTube Channel ID', 'discord-embed-creator')
            , 'testingPlatform' => __('🧪 Testing...', 'discord-embed-creator')
            , 'testStatusButtonTwitch' => __('🧪 Test Twitch Status', 'discord-embed-creator')
            , 'testStatusButtonYouTube' => __('🧪 Test YouTube Status', 'discord-embed-creator')
            , 'saving' => __('💾 Saving...', 'discord-embed-creator')
            , 'saveSettings' => __('💾 Save Settings', 'discord-embed-creator')
            , 'errorSavingGeneric' => __('❌ Error saving: ', 'discord-embed-creator')
            , 'pleaseEnterChannelForPlatform' => __('Please enter Channel/ID for the platform.', 'discord-embed-creator')
            , 'testingSend' => __('🧪 Test sending...', 'discord-embed-creator')
            , 'testNotificationSend' => __('🧪 Send Test Notification', 'discord-embed-creator')
            , 'errorGeneric' => __('Error: ', 'discord-embed-creator')
            , 'errorLoadingTemplates' => __('Error loading templates.', 'discord-embed-creator')
            , 'loadTemplate' => __('Load', 'discord-embed-creator')
            , 'load' => __('Load', 'discord-embed-creator')
            , 'send' => __('Send', 'discord-embed-creator')
            , 'pause' => __('Pause', 'discord-embed-creator')
            , 'activate' => __('Activate', 'discord-embed-creator')
            , 'delete' => __('Delete', 'discord-embed-creator')
            , 'enterTemplateName' => __('Please enter a template name', 'discord-embed-creator')
            , 'savingTemplate' => __('Saving...', 'discord-embed-creator')
            , 'saveCurrentAsTemplate' => __('💾 Save Current as Template', 'discord-embed-creator')
            , 'errorLoadingTemplate' => __('Error loading template', 'discord-embed-creator')
            , 'messageSent' => __('Message sent', 'discord-embed-creator')
            , 'confirmSendTemplateNow' => __('Send template now?', 'discord-embed-creator')
            , 'confirmDeleteTemplate' => __('Really delete template?', 'discord-embed-creator')
            , 'notMentionable' => __('not mentionable', 'discord-embed-creator')
            , 'activate' => __('Activate', 'discord-embed-creator')
            , 'pause' => __('Pause', 'discord-embed-creator')
            , 'testSuccessful' => __('Test successful', 'discord-embed-creator')
            , 'connectionSuccessful' => __('API connection successful', 'discord-embed-creator')
            , 'statusLive' => __('Status: LIVE', 'discord-embed-creator')
            , 'statusOffline' => __('Status: OFFLINE', 'discord-embed-creator')
            , 'channelNotLive' => __('Channel is currently not live.', 'discord-embed-creator')
            , 'testFailed' => __('Test failed', 'discord-embed-creator')
            , 'saveError' => __('Error saving', 'discord-embed-creator')
            , 'unknownError' => __('Unknown error', 'discord-embed-creator')
            , 'saveSettings' => __('💾 Save Settings', 'discord-embed-creator')
            , 'sendTestNotification' => __('🧪 Send Test Notification', 'discord-embed-creator')
            , 'saveCurrentAsTemplate' => __('💾 Save Current as Template', 'discord-embed-creator')
            , 'loadChannels' => __('Load Channels', 'discord-embed-creator')
            , 'loading' => __('Loading...', 'discord-embed-creator')
            , 'errorLoadingChannels' => __('Error loading channels', 'discord-embed-creator')
            , 'errorLoadingRoles' => __('Error loading roles', 'discord-embed-creator')
            , 'title' => __('Title', 'discord-embed-creator')
            , 'url' => __('URL', 'discord-embed-creator')
            , 'error' => __('Error', 'discord-embed-creator')
            , 'deleteFailed' => __('Delete failed', 'discord-embed-creator')
            , 'toggleFailed' => __('Toggle failed', 'discord-embed-creator')
            , 'sendFailed' => __('Send failed', 'discord-embed-creator')
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

    /**
     * Save a live notification template (CRUD)
     */
    public function save_live_notification_template() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

    global $wpdb;
    $this->ensure_live_templates_table_exists();
    $table = $wpdb->prefix . 'discord_live_notification_templates';

    $template_id = intval($_POST['template_id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? 'Untitled');
        $platform = sanitize_text_field($_POST['platform'] ?? 'twitch');
        $config_raw = $_POST['config'] ?? '{}';
    $save_as_new = isset($_POST['save_as_new']) && intval($_POST['save_as_new']) === 1;

        // Validate JSON
        $config = json_decode(wp_unslash($config_raw), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid config JSON: ' . json_last_error_msg());
            return;
        }

        $config_clean = json_encode($config);

        // If no template_id provided but user didn't request "save as new",
        // try to find an existing template with the same name and reuse its id
        if ($template_id <= 0 && !$save_as_new) {
            $existing_by_name = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE name = %s", $name));
            if ($existing_by_name) {
                $template_id = intval($existing_by_name);
            }
        }

        if ($template_id > 0 && !$save_as_new) {
            // update existing (by id)
            $res = $wpdb->update($table, array(
                'name' => $name,
                'platform' => $platform,
                'config' => $config_clean,
                'updated_at' => current_time('mysql')
            ), array('id' => $template_id), array('%s','%s','%s','%s'), array('%d'));
        } else {
            // insert new (either no id provided or save_as_new requested)
            $res = $wpdb->insert($table, array(
                'name' => $name,
                'platform' => $platform,
                'config' => $config_clean,
                'enabled' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ), array('%s','%s','%s','%d','%s','%s'));
            $template_id = $wpdb->insert_id;
        }

        if ($res === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }

        wp_send_json_success(array('template_id' => $template_id, 'message' => 'Template saved'));
    }

    public function load_live_notification_templates() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

    global $wpdb;
    $this->ensure_live_templates_table_exists();
    $table = $wpdb->prefix . 'discord_live_notification_templates';
        $rows = $wpdb->get_results("SELECT * FROM $table ORDER BY updated_at DESC");
        if ($wpdb->last_error) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }

        wp_send_json_success($rows);
    }

    public function load_live_notification_template() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

        $template_id = intval($_POST['template_id'] ?? 0);
        if ($template_id <= 0) {
            wp_send_json_error('Invalid template id');
            return;
        }

    global $wpdb;
    $this->ensure_live_templates_table_exists();
    $table = $wpdb->prefix . 'discord_live_notification_templates';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $template_id));
        if ($wpdb->last_error) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }
        if (!$row) {
            wp_send_json_error('Template not found');
            return;
        }

        $config = json_decode($row->config, true);
        wp_send_json_success(array('id' => $row->id, 'name' => $row->name, 'platform' => $row->platform, 'config' => $config, 'enabled' => (bool)$row->enabled));
    }

    public function delete_live_notification_template() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

        $template_id = intval($_POST['template_id'] ?? 0);
        if ($template_id <= 0) {
            wp_send_json_error('Invalid template id');
            return;
        }

    global $wpdb;
    $this->ensure_live_templates_table_exists();
    $table = $wpdb->prefix . 'discord_live_notification_templates';
        $res = $wpdb->delete($table, array('id' => $template_id), array('%d'));
        if ($res === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }

        wp_send_json_success('Template deleted');
    }

    public function toggle_live_notification_template() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

        $template_id = intval($_POST['template_id'] ?? 0);
        $enabled = (bool) ($_POST['enabled'] ?? false);
        if ($template_id <= 0) {
            wp_send_json_error('Invalid template id');
            return;
        }

    global $wpdb;
    $this->ensure_live_templates_table_exists();
    $table = $wpdb->prefix . 'discord_live_notification_templates';
        $res = $wpdb->update($table, array('enabled' => $enabled, 'updated_at' => current_time('mysql')), array('id' => $template_id), array('%d','%s'), array('%d'));
        if ($res === false) {
            wp_send_json_error('Database error: ' . $wpdb->last_error);
            return;
        }

        wp_send_json_success(array('enabled' => $enabled));
    }

    /**
     * Send a saved live notification template immediately
     */
    public function send_saved_live_notification() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }

        $template_id = intval($_POST['template_id'] ?? 0);
        if ($template_id <= 0) {
            wp_send_json_error('Invalid template id');
            return;
        }

    global $wpdb;
    $this->ensure_live_templates_table_exists();
    $table = $wpdb->prefix . 'discord_live_notification_templates';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $template_id));
        if (!$row) {
            wp_send_json_error('Template not found');
            return;
        }

        $config = json_decode($row->config, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid template config');
            return;
        }

        // Merge template config with current global settings so webhooks/roles can inherit
        $global = get_option('discord_live_notification_settings', array());
        $settings = array_merge($global, $config);

        // Prepare embed from provided config or global template.
        $raw_embed = null;
        if (!empty($config['embed_template'])) {
            // embed_template might already be an array or a JSON string
            if (is_array($config['embed_template'])) {
                $raw_embed = $config['embed_template'];
            } else {
                $raw_embed = json_decode($config['embed_template'], true);
            }
        } else {
            $raw_embed = is_string($settings['embed_template']) ? json_decode($settings['embed_template'], true) : $settings['embed_template'];
        }

        if (!$raw_embed || !is_array($raw_embed)) {
            $raw_embed = array('title' => 'Live!', 'description' => 'Stream ist live', 'color' => 9442302);
        }

        // Build sensible live_status values (use test_* if present in config, otherwise derive from embed or platform config)

        $live_url = $config['test_url'] ?? ($raw_embed['url'] ?? '');
        $live_thumbnail = $config['test_thumbnail'] ?? ($raw_embed['thumbnail']['url'] ?? '');

        // If still empty, try to craft from platform-specific fields in config

        if (empty($live_url)) {
            if ($row->platform === 'twitch') {
                $chan = $config['twitch_channel'] ?? ($settings['twitch_channel'] ?? '');
                if ($chan) $live_url = 'https://twitch.tv/' . $chan;
            } else {
                $chan = $config['youtube_channel_id'] ?? ($settings['youtube_channel_id'] ?? '');
                if ($chan) {
                    // If config stored full video URL, keep it; otherwise try youtube channel URL
                    if (strpos($chan, 'http') === 0) {
                        $live_url = $chan;
                    } else {
                        // prefer channel handle URL if provided as '@name' else use channel id watch URL placeholder
                        if (strpos($chan, '@') === 0) {
                            $live_url = 'https://youtube.com/' . $chan;
                        } else {
                            $live_url = 'https://www.youtube.com/channel/' . $chan;
                        }
                    }
                }
            }
        }

    // Do NOT auto-derive thumbnails from platform (e.g. Twitch CDN preview).
    // Prefer the thumbnail provided in the template/test config or the embed_template
    // (embed image/thumbnail). Leaving $live_thumbnail untouched avoids unexpected
    // template overrides and the malformed payloads observed earlier.

        $live_status = array(
            'platform' => ucfirst($row->platform),
            'title' => $config['test_title'] ?? ($raw_embed['title'] ?? 'Live'),
            'url' => $live_url,
            'thumbnail' => $live_thumbnail
        );

        // Debug: log which embed_template source we're using and a preview of the embed
        try {
            $source = !empty($config['embed_template']) ? 'template_config' : 'global_settings';
            // only log non-sensitive keys from settings
            $safe_settings = $settings;
            if (isset($safe_settings['bot_token'])) unset($safe_settings['bot_token']);
            if (isset($safe_settings['webhook_url'])) unset($safe_settings['webhook_url']);

            error_log('[DiscordEmbed] send_saved_live_notification: using embed template source=' . $source . ' for template_id=' . $row->id . ' platform=' . $row->platform);
            error_log('[DiscordEmbed] send_saved_live_notification: raw_embed_preview=' . substr(is_string($raw_embed) ? $raw_embed : json_encode($raw_embed), 0, 800));
            error_log('[DiscordEmbed] send_saved_live_notification: settings_preview=' . substr(json_encode($safe_settings), 0, 800));
        } catch (Exception $e) {
            // ignore logging errors
        }

        $embed_data = $this->prepare_embed_data($live_status, $row->platform, $settings);

        // Log final embed payload (shortened) to help debug template mismatches; exclude large binary data
        try {
            $preview = json_encode($embed_data);
            error_log('[DiscordEmbed] send_saved_live_notification: final_embed_preview=' . substr($preview, 0, 1200));
        } catch (Exception $e) {}

        $result = $this->send_live_notification($embed_data, $settings);

        if ($result['success']) {
            wp_send_json_success('Sent successfully');
        } else {
            wp_send_json_error('Send failed: ' . ($result['error'] ?? ($result['response_body'] ?? 'Unknown')));
        }
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

    /**
     * AJAX handler to load server emojis via Discord API using Bot Token
     */
    public function load_server_emojis() {
        // Verify nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'discord_embed_nonce')) {
            wp_send_json_error('Invalid nonce');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $bot_token = sanitize_text_field($_POST['bot_token'] ?? '');
        $server_id = sanitize_text_field($_POST['server_id'] ?? '');

        if (empty($bot_token) || empty($server_id)) {
            wp_send_json_error('Bot Token und Server ID sind erforderlich');
            return;
        }

        // Use transient caching to avoid rate limits
        $transient_key = 'discord_embed_emojis_' . md5($server_id);
        $cached = get_transient($transient_key);
        if ($cached !== false) {
            wp_send_json_success(array('emojis' => $cached, 'cached' => true));
            return;
        }

        $api_url = "https://discord.com/api/v10/guilds/{$server_id}/emojis";

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bot ' . $bot_token,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 20
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Network error: ' . $response->get_error_message());
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            $msg = 'Discord API error: HTTP ' . $code;
            if ($code === 401) $msg = 'Ungültiger Bot Token (401)';
            if ($code === 403) $msg = 'Bot hat keine Berechtigung, Emojis abzurufen (403)';
            if ($code === 404) $msg = 'Server nicht gefunden oder Bot ist nicht Mitglied des Servers (404)';
            if ($code === 429) $msg = 'Rate limit erreicht (429)';
            wp_send_json_error($msg . '. ' . substr($body, 0, 200));
            return;
        }

        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON from Discord API');
            return;
        }

        $result = array();
        foreach ($data as $e) {
            $id = $e['id'] ?? '';
            $name = $e['name'] ?? '';
            $animated = !empty($e['animated']);
            // prefer webp if available, use .gif for animated
            $ext = $animated ? 'gif' : 'webp';
            $url = "https://cdn.discordapp.com/emojis/{$id}.{$ext}?size=160";
            $result[] = array('id' => $id, 'name' => $name, 'animated' => $animated, 'url' => $url);
        }

    // Cache for 60 minutes to reduce repeated API calls (emojis rarely change)
    set_transient($transient_key, $result, 60 * MINUTE_IN_SECONDS);

        wp_send_json_success(array('emojis' => $result, 'cached' => false));
    }

    /**
     * Helper: return server emojis as name => [id,name,animated,url]
     * Uses the same transient cache as load_server_emojis
     */
    private function get_server_emojis($bot_token, $server_id) {
        if (empty($bot_token) || empty($server_id)) return array();

        $transient_key = 'discord_embed_emojis_' . md5($server_id);
        $cached = get_transient($transient_key);
        if ($cached !== false && is_array($cached)) {
            // convert to map by name
            $map = array();
            foreach ($cached as $e) {
                if (!empty($e['name'])) $map[$e['name']] = $e;
            }
            return $map;
        }

        // If not cached, attempt an API call (this will also populate transient)
        $api_url = "https://discord.com/api/v10/guilds/{$server_id}/emojis";
        $response = wp_remote_get($api_url, array(
            'headers' => array('Authorization' => 'Bot ' . $bot_token, 'Content-Type' => 'application/json'),
            'timeout' => 10
        ));

        if (is_wp_error($response)) return array();

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code !== 200) return array();

        $data = json_decode($body, true);
        if (!is_array($data)) return array();

        $result = array();
        foreach ($data as $e) {
            $id = $e['id'] ?? '';
            $name = $e['name'] ?? '';
            $animated = !empty($e['animated']);
            $ext = $animated ? 'gif' : 'webp';
            $url = "https://cdn.discordapp.com/emojis/{$id}.{$ext}?size=160";
            $result[] = array('id' => $id, 'name' => $name, 'animated' => $animated, 'url' => $url);
        }

        // Cache and return map
        set_transient($transient_key, $result, 60 * MINUTE_IN_SECONDS);
        $map = array();
        foreach ($result as $e) {
            if (!empty($e['name'])) $map[$e['name']] = $e;
        }
        return $map;
    }
    
    /**
     * Add custom cron intervals for live checking
     */
    public function add_cron_intervals($schedules) {
        $schedules['every_three_minutes'] = array(
            'interval' => 180,
            'display' => __('Every 3 Minutes', 'discord-embed-creator')
        );
        return $schedules;
    }
    
    /**
     * Save live notification settings
     */
    public function save_live_notification_settings() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $settings = array(
            'enabled' => (bool) ($_POST['enabled'] ?? false),
            'twitch_enabled' => (bool) ($_POST['twitch_enabled'] ?? false),
            'youtube_enabled' => (bool) ($_POST['youtube_enabled'] ?? false),
            'twitch_channel' => sanitize_text_field($_POST['twitch_channel'] ?? ''),
            'youtube_channel_id' => sanitize_text_field($_POST['youtube_channel_id'] ?? ''),
            'webhook_type' => sanitize_text_field($_POST['webhook_type'] ?? 'channel'),
            'webhook_url' => esc_url_raw($_POST['webhook_url'] ?? ''),
            'bot_token' => sanitize_text_field($_POST['bot_token'] ?? ''),
            'server_id' => sanitize_text_field($_POST['server_id'] ?? ''),
            'channel_id' => sanitize_text_field($_POST['channel_id'] ?? ''),
            'embed_template' => wp_unslash($_POST['embed_template'] ?? '{}'),
            'selected_roles' => array_map('sanitize_text_field', $_POST['selected_roles'] ?? array()),
            'cooldown_minutes' => intval($_POST['cooldown_minutes'] ?? 10)
        );
        
        update_option('discord_live_notification_settings', $settings);
        
        $this->log_debug('Live notification settings saved', $settings);
        wp_send_json_success('Settings saved successfully');
    }
    
    /**
     * Load live notification settings
     */
    public function load_live_notification_settings() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $default_settings = array(
            'enabled' => false,
            'twitch_enabled' => false,
            'youtube_enabled' => false,
            'twitch_channel' => '',
            'youtube_channel_id' => '',
            'webhook_type' => 'channel',
            'webhook_url' => '',
            'bot_token' => '',
            'server_id' => '',
            'channel_id' => '',
            'embed_template' => json_encode(array(
                'title' => '✨ ' . __('STREAMER is Live!', 'discord-embed-creator'),
                'description' => "✨ " . __('Hey , STREAMER is live now at:', 'discord-embed-creator') . "\n📺 {url} !\n\n" . __('Come join the fun! 🚀', 'discord-embed-creator'),
                'color' => 9442302, // Purple color
                'thumbnail' => array('url' => '{thumbnail}'),
                'footer' => array(
                    'text' => __('Live now - Powered by Discord Embed Creator', 'discord-embed-creator')
                )
            )),
            'selected_roles' => array(),
            'cooldown_minutes' => 10
        );
        
        $settings = get_option('discord_live_notification_settings', $default_settings);
        
        // If no live notification settings exist yet, inherit from main Discord Embed settings
        if (empty(get_option('discord_live_notification_settings'))) {
            $main_settings = array(
                'webhook_type' => get_option('discord_embed_webhook_type', 'channel'),
                'webhook_url' => get_option('discord_embed_webhook_url', ''),
                'bot_token' => get_option('discord_embed_bot_token', ''),
                'server_id' => get_option('discord_embed_server_id', ''),
                'channel_id' => get_option('discord_embed_channel_id', '')
            );
            
            // Merge main settings with defaults
            $settings = array_merge($settings, array_filter($main_settings));
            
            $this->log_debug('Inherited Discord settings from main tab', $main_settings);
        }
        
        // Merge with defaults to ensure all keys exist
        $settings = array_merge($default_settings, $settings);
        
        wp_send_json_success($settings);
    }
    
    /**
     * Check StreamWeasels credentials
     */
    public function check_streamweasels_credentials() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $credentials = $this->get_streamweasels_credentials();
        
        $this->log_debug('StreamWeasels credential check', array(
            'twitch_client_id_length' => strlen($credentials['twitch']['client_id']),
            'twitch_access_token_length' => strlen($credentials['twitch']['access_token']),
            'youtube_api_key_length' => strlen($credentials['youtube']['api_key']),
            'twitch_options_raw' => get_option('swti_options', 'not found'),
            'youtube_options_raw' => get_option('swyi_options', 'not found')
        ));
        
        wp_send_json_success(array(
            'twitch_available' => !empty($credentials['twitch']['client_id']) && !empty($credentials['twitch']['access_token']),
            'youtube_available' => !empty($credentials['youtube']['api_key']),
            'credentials' => array(
                'twitch' => array(
                    'client_id' => !empty($credentials['twitch']['client_id']),
                    'access_token' => !empty($credentials['twitch']['access_token'])
                ),
                'youtube' => array(
                    'api_key' => !empty($credentials['youtube']['api_key'])
                )
            ),
            'debug_info' => array(
                'twitch_client_id_preview' => substr($credentials['twitch']['client_id'], 0, 8) . '...',
                'twitch_token_preview' => substr($credentials['twitch']['access_token'], 0, 8) . '...',
                'youtube_key_preview' => substr($credentials['youtube']['api_key'], 0, 8) . '...'
            )
        ));
    }
    
    /**
     * Get StreamWeasels credentials from correct option keys
     */
    private function get_streamweasels_credentials() {
        $credentials = array(
            'twitch' => array('client_id' => '', 'access_token' => ''),
            'youtube' => array('api_key' => '')
        );
        
        // Get Twitch credentials from StreamWeasels Twitch Integration
        $twitch_options = get_option('swti_options', array());
        if (is_array($twitch_options)) {
            $credentials['twitch']['client_id'] = $twitch_options['swti_client_id'] ?? '';
            $credentials['twitch']['access_token'] = $twitch_options['swti_api_access_token'] ?? '';
        }
        
        // Get YouTube credentials from StreamWeasels YouTube Integration  
        $youtube_options = get_option('swyi_options', array());
        if (is_array($youtube_options)) {
            $credentials['youtube']['api_key'] = $youtube_options['swyi_api_key'] ?? '';
        }
        
        $this->log_debug('StreamWeasels credentials loaded', array(
            'twitch_client_id_exists' => !empty($credentials['twitch']['client_id']),
            'twitch_access_token_exists' => !empty($credentials['twitch']['access_token']),
            'youtube_api_key_exists' => !empty($credentials['youtube']['api_key'])
        ));
        
        return $credentials;
    }
    
    /**
     * Test live notification
     */
    public function test_live_notification() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        // Try to use provided platform/channel from POST to construct a realistic test embed
        $platform = sanitize_text_field($_POST['platform'] ?? 'twitch');
        $channel = sanitize_text_field($_POST['channel'] ?? '');

        $settings = get_option('discord_live_notification_settings', array());

        // Validate Discord settings
        if (empty($settings['webhook_url']) && (empty($settings['bot_token']) || empty($settings['channel_id']))) {
            wp_send_json_error('Bitte konfiguriere zuerst die Discord-Einstellungen (Webhook URL oder Bot Token + Channel)');
            return;
        }

        // Build test embed using provided channel (fallback to generic)
        if ($platform === 'twitch') {
            $chan = $channel ?: ($settings['twitch_channel'] ?? 'STREAMER');
            $stream_url = 'https://twitch.tv/' . $chan;
            $thumbnail = null; // No thumbnail for test
            
            $test_embed = array(
                'title' => 'ᴛᴡɪᴛᴄʜ 🔴 🅻🅸🆅🅴 🎧',
                'description' => '✨ Hey , ' . strtoupper($chan) . " is live now at:\n📺 " . esc_url($stream_url) . " !\n\nCome join the fun! 🚀",
                'color' => 9442302, // Purple
                'footer' => array('text' => 'Test-Benachrichtigung'),
                'timestamp' => date('c')
            );
        } else {
            $chan = $channel ?: ($settings['youtube_channel_id'] ?? 'YOURCHANNEL');
            $stream_url = 'https://youtube.com/@' . $chan;
            $thumbnail = null; // No thumbnail for test
            
            $test_embed = array(
                'title' => 'ʏᴏᴜᴛᴜʙᴇ 🔴 🅻🅸🆅🅴 🎧',
                'description' => "✨ Hey , STREAMER is live now at:\n📺 " . esc_url($stream_url) . " !\n\n🎶 Come join the fun! 🚀",
                'color' => 16711680, // Red
                'footer' => array('text' => 'Test-Benachrichtigung'),
                'timestamp' => date('c')
            );
        }

        // Add role mentions if selected
        $selected_roles = $_POST['selected_roles'] ?? $settings['selected_roles'] ?? array();
        if (!empty($selected_roles)) {
            $role_mentions = array_map(function($role_id) {
                return "<@&{$role_id}>";
            }, $selected_roles);

            $test_embed['description'] = implode(' ', $role_mentions) . "\n\n" . $test_embed['description'];
        }

        // Send
        $result = $this->send_live_notification($test_embed, $settings);
        if ($result['success']) {
            wp_send_json_success('✅ Test-Benachrichtigung erfolgreich gesendet! Überprüfe deinen Discord-Channel.');
        } else {
            wp_send_json_error('❌ Test-Benachrichtigung fehlgeschlagen: ' . ($result['error'] ?? ($result['response_body'] ?? 'Unbekannter Fehler')));
        }
    }
    
    /**
     * Toggle live notifications on/off
     */
    public function toggle_live_notifications() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized access');
            return;
        }
        
        $enabled = (bool) ($_POST['enabled'] ?? false);
        $settings = get_option('discord_live_notification_settings', array());
        $settings['enabled'] = $enabled;
        
        update_option('discord_live_notification_settings', $settings);
        
        wp_send_json_success(array(
            'enabled' => $enabled,
            'message' => $enabled ? 'Live notifications enabled' : 'Live notifications disabled'
        ));
    }
    
    /**
     * Main cron function to check live status
     */
    public function check_live_status() {
        $settings = get_option('discord_live_notification_settings', array());
        
        if (empty($settings['enabled'])) {
            return;
        }
        
        $credentials = $this->get_streamweasels_credentials();
        
        // Check Twitch if enabled
        if (!empty($settings['twitch_enabled']) && !empty($settings['twitch_channel'])) {
            $this->check_and_notify_twitch($settings['twitch_channel'], $credentials['twitch'], $settings);
        }
        
        // Check YouTube if enabled
        if (!empty($settings['youtube_enabled']) && !empty($settings['youtube_channel_id'])) {
            $this->check_and_notify_youtube($settings['youtube_channel_id'], $credentials['youtube'], $settings);
        }
    }
    
    /**
     * Check Twitch live status and send notification if needed
     */
    private function check_and_notify_twitch($channel, $credentials, $settings) {
        $live_status = $this->check_twitch_live_status($channel, $credentials);
        
        if ($live_status['is_live'] && !$this->was_recently_notified('twitch', $channel, $settings['cooldown_minutes'])) {
            $embed_data = $this->prepare_embed_data($live_status, 'twitch', $settings);
            $result = $this->send_live_notification($embed_data, $settings);
            
            if ($result['success']) {
                $this->update_notification_status('twitch', $channel, true);
            }
        } elseif (!$live_status['is_live']) {
            $this->update_notification_status('twitch', $channel, false);
        }
    }
    
    /**
     * Check YouTube live status and send notification if needed
     */
    private function check_and_notify_youtube($channel_id, $credentials, $settings) {
        $live_status = $this->check_youtube_live_status($channel_id, $credentials);
        
        if ($live_status['is_live'] && !$this->was_recently_notified('youtube', $channel_id, $settings['cooldown_minutes'])) {
            $embed_data = $this->prepare_embed_data($live_status, 'youtube', $settings);
            $result = $this->send_live_notification($embed_data, $settings);
            
            if ($result['success']) {
                $this->update_notification_status('youtube', $channel_id, true);
            }
        } elseif (!$live_status['is_live']) {
            $this->update_notification_status('youtube', $channel_id, false);
        }
    }
    
    /**
     * Check Twitch live status via API
     */
    private function check_twitch_live_status($channel, $credentials) {
        if (empty($credentials['client_id']) || empty($credentials['access_token'])) {
            return array('error' => 'Missing Twitch credentials', 'is_live' => false);
        }
        
        $url = 'https://api.twitch.tv/helix/streams?user_login=' . rawurlencode($channel);
        
        $response = wp_remote_get($url, array(
            'headers' => array(
                'Client-ID' => $credentials['client_id'],
                'Authorization' => 'Bearer ' . $credentials['access_token']
            ),
            'timeout' => 12
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message(), 'is_live' => false);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $is_live = !empty($data['data'][0]);
        
        return array(
            'is_live' => $is_live,
            'title' => $is_live ? ($data['data'][0]['title'] ?? 'Live auf Twitch') : '',
            'game' => $is_live ? ($data['data'][0]['game_name'] ?? '') : '',
            'viewer_count' => $is_live ? ($data['data'][0]['viewer_count'] ?? 0) : 0,
            'thumbnail' => $is_live ? str_replace(['{width}', '{height}'], ['1920', '1080'], $data['data'][0]['thumbnail_url'] ?? '') : '',
            'url' => 'https://twitch.tv/' . $channel,
            'platform' => 'Twitch'
        );
    }
    
    /**
     * Check YouTube live status via API
     */
    private function check_youtube_live_status($channel_id, $credentials) {
        if (empty($credentials['api_key'])) {
            return array('error' => 'Missing YouTube API key', 'is_live' => false);
        }
        
        // First, get the channel's live videos
        $search_url = 'https://www.googleapis.com/youtube/v3/search?' . http_build_query(array(
            'part' => 'snippet',
            'channelId' => $channel_id,
            'eventType' => 'live',
            'type' => 'video',
            'maxResults' => 1,
            'key' => $credentials['api_key']
        ));
        
        $headers = array(
            'referer' => isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field($_SERVER['HTTP_REFERER']) : '',
        );
        
        $response = wp_remote_get($search_url, array(
            'headers' => $headers,
            'timeout' => 15
        ));
        
        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message(), 'is_live' => false);
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            $this->log_debug('YouTube API error', array('code' => $response_code, 'body' => $body));
            return array('error' => 'YouTube API error: ' . $response_code, 'is_live' => false);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $is_live = !empty($data['items'][0]);
        
        if ($is_live) {
            $video = $data['items'][0];
            $video_id = $video['id']['videoId'];
            
            return array(
                'is_live' => true,
                'title' => $video['snippet']['title'] ?? 'Live auf YouTube',
                'description' => $video['snippet']['description'] ?? '',
                'thumbnail' => $video['snippet']['thumbnails']['high']['url'] ?? ($video['snippet']['thumbnails']['medium']['url'] ?? ''),
                'url' => 'https://www.youtube.com/watch?v=' . $video_id,
                'platform' => 'YouTube',
                'channel_title' => $video['snippet']['channelTitle'] ?? ''
            );
        }
        
        return array(
            'is_live' => false,
            'platform' => 'YouTube'
        );
    }
    
    /**
     * Prepare embed data with live stream information
     */
    private function prepare_embed_data($live_status, $platform, $settings) {
        $raw_template = $settings['embed_template'] ?? null;
        if (is_string($raw_template)) {
            $template = json_decode($raw_template, true);
        } else {
            $template = $raw_template;
        }

        if (!$template) {
            $template = array(
                'title' => 'ᴛᴡɪᴛᴄʜ � 🅻🅸🆅🅴 🎧',
                'description' => '✨ Hey , STREAMER is live now at:\n📺 {url} !\n\nCome join the fun! 🚀',
                'color' => 9442302
            );
        }
        
        // Replace placeholders
        $replacements = array(
            '{platform}' => $live_status['platform'],
            '{title}' => $live_status['title'],
            '{url}' => $live_status['url'],
            '{thumbnail}' => $live_status['thumbnail'] ?? ''
        );
        
        $template = json_decode(str_replace(array_keys($replacements), array_values($replacements), json_encode($template)), true);
        
        // Add thumbnail if available and no image already set
        if (!empty($live_status['thumbnail']) && empty($template['image'])) {
            $template['thumbnail'] = array('url' => $live_status['thumbnail']);
        }
        
        // Handle image field with placeholder replacement
        if (!empty($template['image']['url'])) {
            // If image URL contains {thumbnail} placeholder and we have thumbnail data, use it
            if (strpos($template['image']['url'], '{thumbnail}') !== false && !empty($live_status['thumbnail'])) {
                $template['image']['url'] = str_replace('{thumbnail}', $live_status['thumbnail'], $template['image']['url']);
            }
            // If image URL is just {thumbnail} and we don't have thumbnail data, remove image field
            else if ($template['image']['url'] === '{thumbnail}' && empty($live_status['thumbnail'])) {
                unset($template['image']);
            }
        }
        
        // Add timestamp
        $template['timestamp'] = date('c');
        
        // Add role mentions to description if selected
        if (!empty($settings['selected_roles'])) {
            $role_mentions = array_map(function($role_id) {
                return "<@&{$role_id}>";
            }, $settings['selected_roles']);
            
            $template['description'] = implode(' ', $role_mentions) . "\n\n" . ($template['description'] ?? '');
        }

        array_walk_recursive($template, function(&$value) use ($emoji_shortcodes, $settings) {
            if (is_string($value)) {
                // convert literal backslash-n sequences to real newlines
                $value = str_replace('\\n', "\n", $value);
                // convert Windows style \r\n as well
                $value = str_replace('\\r\\n', "\r\n", $value);

                // Replace explicit known emoji shortcodes first
                foreach ($emoji_shortcodes as $short => $emoji) {
                    if (strpos($value, $short) !== false) {
                        $value = str_replace($short, $emoji, $value);
                    }
                }

                // Replace any :shortcode: occurrences by resolving server emojis when possible
                // but avoid replacing when the shortcode is already inside a full emoji code like <:name:id> or <a:name:id>
                if (preg_match_all('/:([A-Za-z0-9_]+):/', $value, $matches)) {
                    $shorts = $matches[1];

                    // try to fetch server emojis if webhook/bot settings are available
                    $botToken = $settings['bot_token'] ?? ($settings['bot_token'] ?? '');
                    $serverId = $settings['server_id'] ?? ($settings['server_id'] ?? '');
                    $emoji_map = array();
                    if (!empty($botToken) && !empty($serverId)) {
                        $emoji_map = $this->get_server_emojis($botToken, $serverId);
                    }

                    foreach ($shorts as $short) {
                        // If already replaced by earlier known shortcodes, skip
                        if (strpos($value, ':' . $short . ':') === false) continue;

                        // If this shortcode appears inside an existing full emoji code, skip replacement for this occurrence
                        // i.e. patterns like <:name:123> or <a:name:123>
                        $pattern_inside = '/<a?:' . preg_quote($short, '/') . ':[0-9]+>/';
                        if (preg_match($pattern_inside, $value)) {
                            // if the shortcode is already part of a full emoji code, do not replace
                            continue;
                        }

                        // If server emoji map contains it, replace with full code (<:name:id> or <a:name:id>)
                        if (!empty($emoji_map) && isset($emoji_map[$short])) {
                            $e = $emoji_map[$short];
                            $code = ($e['animated'] ? '<a:' : '<:') . $e['name'] . ':' . $e['id'] . '>';
                            // Only replace if the exact full emoji code isn't already present
                            if (strpos($value, $code) === false) {
                                $value = str_replace(':' . $short . ':', $code, $value);
                            }
                            continue;
                        }

                        // otherwise leave the shortcode as-is (it might be intended as plain text)
                    }
                }
            }
        });
        
        return $template;
    }
    
    /**
     * Send live notification to Discord
     */
    private function send_live_notification($embed_data, $settings) {
        if ($settings['webhook_type'] === 'server') {
            $url = "https://discord.com/api/v10/channels/{$settings['channel_id']}/messages";
            $headers = array(
                'Authorization' => 'Bot ' . $settings['bot_token'],
                'Content-Type' => 'application/json'
            );
        } else {
            $url = $settings['webhook_url'];
            $headers = array('Content-Type' => 'application/json');
        }
        
        $payload = array('embeds' => array($embed_data));

        // Use WordPress' wp_json_encode to avoid PHP's json_encode default hex-escaping of < and >
        $body = wp_json_encode($payload);

        $response = wp_remote_post($url, array(
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array('success' => false, 'error' => $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        return array(
            'success' => ($response_code === 200 || $response_code === 204),
            'response_code' => $response_code,
            'response_body' => wp_remote_retrieve_body($response)
        );
    }
    
    /**
     * Check if notification was recently sent to prevent spam
     */
    private function was_recently_notified($platform, $channel, $cooldown_minutes) {
        global $wpdb;
        $table = $wpdb->prefix . 'discord_live_notifications';
        
        $last_notification = $wpdb->get_var($wpdb->prepare(
            "SELECT last_notification_sent FROM $table 
             WHERE platform = %s AND channel_name = %s",
            $platform, $channel
        ));
        
        if (!$last_notification) {
            return false;
        }
        
        $cooldown_seconds = $cooldown_minutes * 60;
        return (time() - strtotime($last_notification)) < $cooldown_seconds;
    }
    
    /**
     * Update notification status in database
     */
    private function update_notification_status($platform, $channel, $is_live) {
        global $wpdb;
        $table = $wpdb->prefix . 'discord_live_notifications';
        
        $data = array(
            'last_live_status' => $is_live ? 1 : 0,
            'last_check' => current_time('mysql')
        );
        
        if ($is_live) {
            $data['last_notification_sent'] = current_time('mysql');
            $data['notification_count'] = '`notification_count` + 1';
        }
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE platform = %s AND channel_name = %s",
            $platform, $channel
        ));
        
        if ($existing) {
            if ($is_live) {
                $wpdb->query($wpdb->prepare(
                    "UPDATE $table SET last_live_status = %d, last_check = %s, 
                     last_notification_sent = %s, notification_count = notification_count + 1
                     WHERE platform = %s AND channel_name = %s",
                    1, current_time('mysql'), current_time('mysql'), $platform, $channel
                ));
            } else {
                $wpdb->update($table, $data, 
                    array('platform' => $platform, 'channel_name' => $channel),
                    array('%d', '%s'), array('%s', '%s')
                );
            }
        } else {
            $wpdb->insert($table, array_merge($data, array(
                'platform' => $platform,
                'channel_name' => $channel,
                'notification_count' => $is_live ? 1 : 0
            )), array('%d', '%s', '%s', '%s', '%s', '%d'));
        }
    }

    /**
     * Ensure live notification templates table exists (create if missing)
     */
    private function ensure_live_templates_table_exists() {
        global $wpdb;
        $table = $wpdb->prefix . 'discord_live_notification_templates';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)));
        if ($exists === $table) return true;

        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            platform varchar(20) NOT NULL,
            config longtext NOT NULL,
            enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_platform (platform)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verify
        $exists2 = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $wpdb->esc_like($table)));
        return ($exists2 === $table);
    }

    /**
     * Debug AJAX: list live notification templates (admin only)
     */
    public function debug_list_live_templates() {
        check_ajax_referer('discord_embed_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'discord_live_notification_templates';
        $rows = $wpdb->get_results("SELECT id, name, platform, LEFT(config, 600) AS config_preview, enabled FROM $table ORDER BY updated_at DESC");
        if ($wpdb->last_error) {
            wp_send_json_error('DB error: ' . $wpdb->last_error);
            return;
        }

        wp_send_json_success($rows);
    }
}

// Initialize the plugin only if class doesn't exist
if (!class_exists('DiscordEmbedPlugin') || !isset($GLOBALS['discord_embed_plugin_instance'])) {
    $GLOBALS['discord_embed_plugin_instance'] = new DiscordEmbedPlugin();
}
