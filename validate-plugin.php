<?php
// Test script to validate plugin structure
echo "Testing Discord Embed Creator Plugin Structure...\n\n";

$plugin_dir = __DIR__;
$required_files = [
    'discord-embed-plugin.php',
    'includes/admin-page.php',
    'assets/admin.css',
    'assets/admin.js'
];

echo "Plugin Directory: $plugin_dir\n\n";

foreach ($required_files as $file) {
    $full_path = $plugin_dir . '/' . $file;
    if (file_exists($full_path)) {
        echo "✓ $file - EXISTS\n";
    } else {
        echo "✗ $file - MISSING\n";
    }
}

// Check main plugin file header
$main_file = $plugin_dir . '/discord-embed-plugin.php';
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    if (strpos($content, 'Plugin Name:') !== false) {
        echo "\n✓ Plugin header found in main file\n";
        
        // Extract plugin name
        preg_match('/Plugin Name:\s*(.+)/', $content, $matches);
        if (isset($matches[1])) {
            echo "Plugin Name: " . trim($matches[1]) . "\n";
        }
    } else {
        echo "\n✗ Plugin header missing in main file\n";
    }
}

echo "\nValidation complete!\n";
?>
