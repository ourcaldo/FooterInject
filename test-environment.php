<?php
/**
 * Test Environment for Analytics Injector Plugin
 * This simulates WordPress environment for testing the plugin functionality
 */

// Simulate WordPress constants and functions for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

// Mock WordPress functions for testing
function get_template_directory() {
    return __DIR__ . '/test-theme';
}

function wp_next_scheduled($hook) {
    // Simulate no scheduled events for testing
    return false;
}

function wp_schedule_event($timestamp, $recurrence, $hook) {
    echo "Scheduled event: $hook to run $recurrence starting from " . date('Y-m-d H:i:s', $timestamp) . "\n";
    return true;
}

function wp_clear_scheduled_hook($hook) {
    echo "Cleared scheduled hook: $hook\n";
    return true;
}

function add_action($hook, $callback) {
    echo "Added action: $hook\n";
}

function register_activation_hook($file, $callback) {
    echo "Registered activation hook for: $file\n";
}

function register_deactivation_hook($file, $callback) {
    echo "Registered deactivation hook for: $file\n";
}

// Create test theme directory and footer.php
if (!is_dir(__DIR__ . '/test-theme')) {
    mkdir(__DIR__ . '/test-theme', 0755, true);
}

// Create a sample footer.php for testing
$sample_footer = '<!DOCTYPE html>
<html>
<head>
    <title>Test Footer</title>
</head>
<body>
    <footer>
        <p>Test Footer Content</p>
    </footer>
    <?php wp_footer(); ?>
</body>
</html>';

file_put_contents(__DIR__ . '/test-theme/footer.php', $sample_footer);

echo "Test environment setup complete!\n";
echo "Created test-theme/footer.php with sample content\n";
echo "You can now test the plugin functionality\n\n";

// Include and test the plugin
require_once 'analytics-injector.php';

// Test the plugin
echo "=== Testing Analytics Injector Plugin ===\n";

$injector = new AnalyticsInjector();

echo "\n=== Initial Status ===\n";
$status = $injector->get_status();
print_r($status);

echo "\n=== Running Manual Check ===\n";
$result = $injector->manual_check();
echo "Manual check result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

echo "\n=== Status After Check ===\n";
$status = $injector->get_status();
print_r($status);

echo "\n=== Testing Duplicate Prevention ===\n";
$result2 = $injector->manual_check();
echo "Second check result: " . ($result2 ? 'SUCCESS' : 'FAILED') . "\n";

echo "\n=== Final Footer Content ===\n";
$footer_content = file_get_contents(__DIR__ . '/test-theme/footer.php');
echo "Footer.php content:\n";
echo $footer_content;

?>