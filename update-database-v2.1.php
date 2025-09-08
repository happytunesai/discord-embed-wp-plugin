<?php
/**
 * Database Update Script for Discord Embed Plugin v2.1.0
 * Adds Live Notifications functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if user has admin privileges
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

global $wpdb;

echo '<div class="wrap">';
echo '<h1>Discord Embed Plugin - Database Update v2.1.0</h1>';

// Update database tables
$results = array();

// Create live notifications table
$live_notifications_table = $wpdb->prefix . 'discord_live_notifications';
$live_notifications_sql = "CREATE TABLE IF NOT EXISTS `$live_notifications_table` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$result = $wpdb->query($live_notifications_sql);
if ($result !== false) {
    $results[] = "✅ discord_live_notifications table created/verified";
} else {
    $results[] = "❌ Failed to create discord_live_notifications table: " . $wpdb->last_error;
}

// Update plugin version
update_option('discord_embed_version', '2.1.0');
$results[] = "✅ Plugin version updated to 2.1.0";

// Schedule cron job if not already scheduled
if (!wp_next_scheduled('discord_embed_check_live_status')) {
    wp_schedule_event(time() + 180, 'every_three_minutes', 'discord_embed_check_live_status');
    $results[] = "✅ Live status check cron job scheduled";
} else {
    $results[] = "✅ Live status check cron job already scheduled";
}

// Display results
echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin: 20px 0;">';
echo '<h2>Update Results:</h2>';
echo '<ul>';
foreach ($results as $result) {
    echo '<li>' . esc_html($result) . '</li>';
}
echo '</ul>';
echo '</div>';

// Show current cron schedule
$next_check = wp_next_scheduled('discord_embed_check_live_status');
if ($next_check) {
    $next_check_time = date('Y-m-d H:i:s', $next_check);
    echo '<div style="background: #e3f2fd; padding: 15px; border-left: 4px solid #2196f3; border-radius: 4px; margin: 20px 0;">';
    echo '<h3>Cron Status:</h3>';
    echo '<p><strong>Next live check:</strong> ' . $next_check_time . '</p>';
    echo '<p><strong>Interval:</strong> Every 3 minutes</p>';
    echo '</div>';
}

// Show table status
echo '<div style="background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 4px; margin: 20px 0;">';
echo '<h3>Database Tables Status:</h3>';

$tables_to_check = array(
    $wpdb->prefix . 'discord_embeds' => 'Embed Templates',
    $wpdb->prefix . 'discord_sent_messages' => 'Sent Messages History', 
    $wpdb->prefix . 'discord_live_notifications' => 'Live Notifications Tracking'
);

echo '<table class="wp-list-table widefat fixed striped">';
echo '<thead><tr><th>Table Name</th><th>Description</th><th>Status</th><th>Records</th></tr></thead>';
echo '<tbody>';

foreach ($tables_to_check as $table_name => $description) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
    if ($table_exists) {
        $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
        echo '<tr>';
        echo '<td><code>' . esc_html($table_name) . '</code></td>';
        echo '<td>' . esc_html($description) . '</td>';
        echo '<td style="color: green;">✅ Exists</td>';
        echo '<td>' . esc_html($count) . ' records</td>';
        echo '</tr>';
    } else {
        echo '<tr>';
        echo '<td><code>' . esc_html($table_name) . '</code></td>';
        echo '<td>' . esc_html($description) . '</td>';
        echo '<td style="color: red;">❌ Missing</td>';
        echo '<td>-</td>';
        echo '</tr>';
    }
}

echo '</tbody></table>';
echo '</div>';

echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 4px; margin: 20px 0;">';
echo '<h3 style="color: #155724; margin-top: 0;">🎉 Update Complete!</h3>';
echo '<p style="color: #155724; margin-bottom: 0;">Discord Embed Plugin v2.1.0 mit Live Notifications ist jetzt bereit!</p>';
echo '<p style="color: #155724;"><strong>Neue Features:</strong></p>';
echo '<ul style="color: #155724;">';
echo '<li>🔴 Live Benachrichtigungen für Twitch und YouTube</li>';
echo '<li>🔗 Automatische StreamWeasels Integration</li>';
echo '<li>📝 Anpassbare Embed Templates für Live Streams</li>';
echo '<li>🏷️ Role Mentions für Live Benachrichtigungen</li>';
echo '<li>⏰ Automatische Überwachung alle 3 Minuten</li>';
echo '<li>🛡️ Anti-Spam Cooldown System</li>';
echo '</ul>';
echo '<a href="' . admin_url('admin.php?page=discord-embeds') . '" class="button button-primary">Zum Plugin Dashboard →</a>';
echo '</div>';

echo '</div>';
?>
