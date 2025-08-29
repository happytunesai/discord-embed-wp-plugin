<?php
/**
 * Database Update Script for Discord Embed Plugin
 * Adds discord_message_id column to existing installations
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

echo "<h2>Discord Embed Plugin - Database Update</h2>\n";

// Check if discord_message_id column already exists
$messages_table = $wpdb->prefix . 'discord_sent_messages';
$column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$messages_table` LIKE 'discord_message_id'");

if (empty($column_exists)) {
    echo "<p>Adding discord_message_id column to $messages_table...</p>\n";
    
    $sql = "ALTER TABLE `$messages_table` ADD COLUMN `discord_message_id` varchar(100) AFTER `id`";
    $result = $wpdb->query($sql);
    
    if ($result !== false) {
        echo "<p style='color: green;'>✅ Successfully added discord_message_id column!</p>\n";
        
        // Add index
        $index_sql = "ALTER TABLE `$messages_table` ADD INDEX `idx_discord_message_id` (`discord_message_id`)";
        $index_result = $wpdb->query($index_sql);
        
        if ($index_result !== false) {
            echo "<p style='color: green;'>✅ Successfully added index for discord_message_id!</p>\n";
        } else {
            echo "<p style='color: orange;'>⚠️ Index creation failed, but column was added successfully.</p>\n";
        }
    } else {
        echo "<p style='color: red;'>❌ Failed to add discord_message_id column!</p>\n";
        echo "<p>Error: " . $wpdb->last_error . "</p>\n";
    }
} else {
    echo "<p style='color: blue;'>ℹ️ discord_message_id column already exists - no update needed.</p>\n";
}

// Show table structure
echo "<h3>Current table structure:</h3>\n";
$columns = $wpdb->get_results("SHOW COLUMNS FROM `$messages_table`");
echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
foreach ($columns as $column) {
    echo "<tr>";
    echo "<td>{$column->Field}</td>";
    echo "<td>{$column->Type}</td>";
    echo "<td>{$column->Null}</td>";
    echo "<td>{$column->Key}</td>";
    echo "<td>{$column->Default}</td>";
    echo "<td>{$column->Extra}</td>";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<p><strong>Update completed!</strong></p>\n";
echo "<p>You can now use the message editing functionality.</p>\n";
?>
