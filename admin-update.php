<?php
/**
 * Admin page for database updates
 * Access via: wp-admin/admin.php?page=discord-embed-update
 */

// Only accessible in WordPress admin
if (!defined('ABSPATH')) {
    exit;
}

// Check permissions
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

global $wpdb;

if (isset($_POST['run_update'])) {
    echo '<div class="notice notice-info"><p>Running database update...</p></div>';
    
    // Check if discord_message_id column already exists
    $messages_table = $wpdb->prefix . 'discord_sent_messages';
    $column_exists = $wpdb->get_results("SHOW COLUMNS FROM `$messages_table` LIKE 'discord_message_id'");

    if (empty($column_exists)) {
        echo '<div class="notice notice-info"><p>Adding discord_message_id column to ' . $messages_table . '...</p></div>';
        
        $sql = "ALTER TABLE `$messages_table` ADD COLUMN `discord_message_id` varchar(100) AFTER `id`";
        $result = $wpdb->query($sql);
        
        if ($result !== false) {
            echo '<div class="notice notice-success"><p>✅ Successfully added discord_message_id column!</p></div>';
            
            // Add index
            $index_sql = "ALTER TABLE `$messages_table` ADD INDEX `idx_discord_message_id` (`discord_message_id`)";
            $index_result = $wpdb->query($index_sql);
            
            if ($index_result !== false) {
                echo '<div class="notice notice-success"><p>✅ Successfully added index for discord_message_id!</p></div>';
            } else {
                echo '<div class="notice notice-warning"><p>⚠️ Index creation failed, but column was added successfully.</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>❌ Failed to add discord_message_id column!</p></div>';
            echo '<div class="notice notice-error"><p>Error: ' . $wpdb->last_error . '</p></div>';
        }
    } else {
        echo '<div class="notice notice-info"><p>ℹ️ discord_message_id column already exists - no update needed.</p></div>';
    }
}

?>
<div class="wrap">
    <h1>Discord Embed Plugin - Database Update</h1>
    
    <div class="card">
        <h2>Update Database Schema</h2>
        <p>This will add the <code>discord_message_id</code> column to enable message editing functionality.</p>
        
        <form method="post">
            <p>
                <input type="submit" name="run_update" class="button button-primary" value="Run Database Update" 
                       onclick="return confirm('Are you sure you want to update the database?');">
            </p>
        </form>
    </div>
    
    <div class="card">
        <h3>Current Table Structure</h3>
        <?php
        $messages_table = $wpdb->prefix . 'discord_sent_messages';
        $columns = $wpdb->get_results("SHOW COLUMNS FROM `$messages_table`");
        
        if ($columns) {
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr></thead>';
            echo '<tbody>';
            foreach ($columns as $column) {
                echo '<tr>';
                echo '<td><strong>' . esc_html($column->Field) . '</strong></td>';
                echo '<td>' . esc_html($column->Type) . '</td>';
                echo '<td>' . esc_html($column->Null) . '</td>';
                echo '<td>' . esc_html($column->Key) . '</td>';
                echo '<td>' . esc_html($column->Default) . '</td>';
                echo '<td>' . esc_html($column->Extra) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        ?>
    </div>
</div>
