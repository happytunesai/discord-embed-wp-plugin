<?php
/**
 * Admin Database Debug Page for Discord Embed Plugin
 * Accessible via WordPress Admin Panel
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

// Handle database setup if requested
if (isset($_POST['setup_database']) && wp_verify_nonce($_POST['setup_nonce'], 'discord_setup_database')) {
    echo '<div class="notice notice-info"><p>Running database setup...</p></div>';
    
    // Create tables and initialize options
    $setup_results = run_database_setup();
    
    if ($setup_results['success']) {
        echo '<div class="notice notice-success"><p>Database setup completed successfully!</p></div>';
    } else {
        echo '<div class="notice notice-error"><p>Database setup encountered errors. See details below.</p></div>';
    }
}

function run_database_setup() {
    global $wpdb;
    $results = ['success' => true, 'messages' => []];
    
    // Create discord_embeds table
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
        $results['messages'][] = "✅ discord_embeds table created/verified";
    } else {
        $results['success'] = false;
        $results['messages'][] = "❌ Failed to create discord_embeds table: " . $wpdb->last_error;
    }
    
    // Create discord_sent_messages table
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
        $results['messages'][] = "✅ discord_sent_messages table created/verified";
    } else {
        $results['success'] = false;
        $results['messages'][] = "❌ Failed to create discord_sent_messages table: " . $wpdb->last_error;
    }
    
    // Initialize default options
    $default_options = [
        'discord_embed_webhook_type' => 'channel',
        'discord_embed_bot_token' => '',
        'discord_embed_server_id' => '',
        'discord_embed_channel_id' => '',
        'discord_embed_webhook_url' => ''
    ];
    
    foreach ($default_options as $option_name => $default_value) {
        $existing = get_option($option_name, null);
        if ($existing === null) {
            add_option($option_name, $default_value);
            $results['messages'][] = "✅ Initialized option: $option_name";
        }
    }
    
    return $results;
}

?>

<div class="wrap">
    <h1>Discord Embed Plugin - Database Debug</h1>
    
    <?php if (isset($setup_results)): ?>
        <h3>Setup Results:</h3>
        <ul>
            <?php foreach ($setup_results['messages'] as $message): ?>
                <li><?php echo esc_html($message); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    
    <div class="card">
        <h2>1. WordPress Options Table</h2>
        <?php
        $options_table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->options}'");
        if ($options_table_exists): ?>
            <p style="color: green;">✅ WordPress options table exists: <?php echo esc_html($wpdb->options); ?></p>
        <?php else: ?>
            <p style="color: red;">❌ WordPress options table missing!</p>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <h2>2. Discord Plugin Options</h2>
        <?php
        $discord_options = [
            'discord_embed_webhook_type',
            'discord_embed_bot_token', 
            'discord_embed_server_id',
            'discord_embed_channel_id',
            'discord_embed_webhook_url'
        ];
        ?>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Option Name</th>
                    <th>Value Preview</th>
                    <th>Length</th>
                    <th>Exists in DB</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($discord_options as $option_name): 
                    $option_value = get_option($option_name, null);
                    $db_value = $wpdb->get_var($wpdb->prepare(
                        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                        $option_name
                    ));
                ?>
                    <tr>
                        <td><?php echo esc_html($option_name); ?></td>
                        <td><?php echo esc_html(substr($option_value ?? '', 0, 50)); ?></td>
                        <td><?php echo strlen($option_value ?? ''); ?></td>
                        <td><?php echo $db_value !== null ? '✅ Yes' : '❌ No'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <div class="card">
        <h2>3. Discord Plugin Tables</h2>
        <?php
        $discord_tables = [
            $wpdb->prefix . 'discord_embeds',
            $wpdb->prefix . 'discord_sent_messages'
        ];
        
        foreach ($discord_tables as $table_name):
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            if ($table_exists):
                $count = $wpdb->get_var("SELECT COUNT(*) FROM `$table_name`");
        ?>
                <p style="color: green;">✅ Table exists: <?php echo esc_html($table_name); ?> (<?php echo $count; ?> records)</p>
        <?php else: ?>
                <p style="color: red;">❌ Table missing: <?php echo esc_html($table_name); ?></p>
        <?php 
            endif;
        endforeach; 
        ?>
    </div>
    
    <div class="card">
        <h2>4. Option Write/Read Test</h2>
        <?php
        $test_option = 'discord_embed_test_' . time();
        $test_value = 'test_value_' . rand(1000, 9999);
        
        // Write test
        $write_result = update_option($test_option, $test_value);
        echo "<p>Write test: " . ($write_result ? '✅ Success' : '❌ Failed') . "</p>";
        
        // Read test
        $read_value = get_option($test_option, 'NOT_FOUND');
        echo "<p>Read test: " . ($read_value === $test_value ? '✅ Success' : '❌ Failed') . "</p>";
        
        // Clean up
        delete_option($test_option);
        ?>
    </div>
    
    <div class="card">
        <h2>5. WordPress Context</h2>
        <table class="wp-list-table widefat fixed striped">
            <tbody>
                <tr><td>WordPress Version</td><td><?php echo get_bloginfo('version'); ?></td></tr>
                <tr><td>Site URL</td><td><?php echo get_site_url(); ?></td></tr>
                <tr><td>Database Prefix</td><td><?php echo esc_html($wpdb->prefix); ?></td></tr>
                <tr><td>Current User ID</td><td><?php echo get_current_user_id(); ?></td></tr>
                <tr><td>Can Manage Options</td><td><?php echo current_user_can('manage_options') ? '✅ Yes' : '❌ No'; ?></td></tr>
                <tr><td>Is Admin</td><td><?php echo is_admin() ? '✅ Yes' : '❌ No'; ?></td></tr>
            </tbody>
        </table>
    </div>
    
    <div class="card">
        <h2>6. Database Setup</h2>
        <p>If any of the above checks failed, you can run the database setup to create missing tables and initialize options.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('discord_setup_database', 'setup_nonce'); ?>
            <input type="submit" name="setup_database" class="button button-primary" value="Run Database Setup" />
        </form>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    border-radius: 3px;
}
</style>
