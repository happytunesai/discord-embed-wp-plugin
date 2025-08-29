<?php
/**
 * Complete Database Setup Script for Discord Embed Plugin
 * Creates all necessary tables and verifies WordPress options functionality
 */

// Only run if WordPress is loaded
if (!defined('ABSPATH')) {
    // If running standalone, load WordPress
    $wp_config_path = '';
    $dir = dirname(__FILE__);
    
    // Look for wp-config.php
    for ($i = 0; $i < 10; $i++) {
        if (file_exists($dir . '/wp-config.php')) {
            $wp_config_path = $dir . '/wp-config.php';
            break;
        }
        $dir = dirname($dir);
    }
    
    if ($wp_config_path) {
        require_once($wp_config_path);
    } else {
        die('WordPress not found. Please run this script from WordPress admin or copy it to your WordPress root directory.');
    }
}

global $wpdb;

echo "<h2>Discord Embed Plugin - Complete Database Setup</h2>\n";

// 1. Create discord_embeds table
echo "<h3>1. Creating discord_embeds table...</h3>\n";
$embeds_table = $wpdb->prefix . 'discord_embeds';

$embeds_sql = "CREATE TABLE IF NOT EXISTS `$embeds_table` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `template_name` varchar(255) NOT NULL,
    `embed_data` longtext NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_template_name` (`template_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$result1 = $wpdb->query($embeds_sql);
if ($result1 !== false) {
    echo "<p style='color: green;'>✅ discord_embeds table created/verified successfully!</p>\n";
} else {
    echo "<p style='color: red;'>❌ Failed to create discord_embeds table!</p>\n";
    echo "<p>Error: " . $wpdb->last_error . "</p>\n";
}

// 2. Create discord_sent_messages table
echo "<h3>2. Creating discord_sent_messages table...</h3>\n";
$messages_table = $wpdb->prefix . 'discord_sent_messages';

$messages_sql = "CREATE TABLE IF NOT EXISTS `$messages_table` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `discord_message_id` varchar(100) DEFAULT NULL,
    `webhook_type` varchar(20) NOT NULL DEFAULT 'channel',
    `bot_token` text,
    `server_id` varchar(100),
    `channel_id` varchar(100),
    `webhook_url` text,
    `embed_data` longtext NOT NULL,
    `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `error_message` text,
    PRIMARY KEY (`id`),
    KEY `idx_discord_message_id` (`discord_message_id`),
    KEY `idx_sent_at` (`sent_at`),
    KEY `idx_webhook_type` (`webhook_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$result2 = $wpdb->query($messages_sql);
if ($result2 !== false) {
    echo "<p style='color: green;'>✅ discord_sent_messages table created/verified successfully!</p>\n";
} else {
    echo "<p style='color: red;'>❌ Failed to create discord_sent_messages table!</p>\n";
    echo "<p>Error: " . $wpdb->last_error . "</p>\n";
}

// 3. Initialize default Discord options
echo "<h3>3. Initializing Discord options...</h3>\n";
$default_options = [
    'discord_embed_webhook_type' => 'channel',
    'discord_embed_bot_token' => '',
    'discord_embed_server_id' => '',
    'discord_embed_channel_id' => '',
    'discord_embed_webhook_url' => ''
];

foreach ($default_options as $option_name => $default_value) {
    $existing_value = get_option($option_name, null);
    if ($existing_value === null) {
        $add_result = add_option($option_name, $default_value);
        echo "<p>Adding $option_name: " . ($add_result ? 'Success' : 'Failed') . "</p>\n";
    } else {
        echo "<p>$option_name already exists with value: " . strlen($existing_value) . " chars</p>\n";
    }
}

// 4. Test option functionality
echo "<h3>4. Testing WordPress options functionality...</h3>\n";
$test_option = 'discord_embed_setup_test';
$test_value = 'setup_test_' . time();

// Test add_option
$add_result = add_option($test_option, $test_value);
echo "<p>Add option test: " . ($add_result ? 'Success' : 'Failed') . "</p>\n";

// Test get_option
$get_result = get_option($test_option, 'NOT_FOUND');
echo "<p>Get option test: " . ($get_result === $test_value ? 'Success' : 'Failed') . "</p>\n";

// Test update_option
$new_test_value = 'updated_' . time();
$update_result = update_option($test_option, $new_test_value);
echo "<p>Update option test: " . ($update_result ? 'Success' : 'Failed') . "</p>\n";

// Verify update
$verify_result = get_option($test_option, 'NOT_FOUND');
echo "<p>Verify update test: " . ($verify_result === $new_test_value ? 'Success' : 'Failed') . "</p>\n";

// Clean up
delete_option($test_option);

// 5. Show final status
echo "<h3>5. Final Database Status</h3>\n";

// Show table info
$tables_to_check = [$embeds_table, $messages_table];
foreach ($tables_to_check as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'");
    if ($exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table`");
        echo "<p style='color: green;'>✅ $table: exists with $count records</p>\n";
    } else {
        echo "<p style='color: red;'>❌ $table: missing</p>\n";
    }
}

// Show Discord options
echo "<h4>Discord Options Status:</h4>\n";
echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>Option</th><th>Length</th><th>Set</th></tr>\n";
foreach ($default_options as $option_name => $default) {
    $value = get_option($option_name, '');
    $length = strlen($value);
    $is_set = $length > 0 ? 'Yes' : 'No';
    echo "<tr><td>$option_name</td><td>$length</td><td>$is_set</td></tr>\n";
}
echo "</table>\n";

echo "<p><strong>Setup completed!</strong></p>\n";
echo "<p>If all tests show 'Success', your Discord Embed Plugin database is ready.</p>\n";
echo "<p>If any tests failed, please check your WordPress installation and database permissions.</p>\n";
?>
