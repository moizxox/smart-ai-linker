<?php
/**
 * Compatibility checks for Smart AI Linker
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Check if the system meets the minimum requirements
 * 
 * @return bool|string True if compatible, error message if not
 */
function smart_ai_linker_check_requirements() {
    global $wp_version;
    
    // Minimum required versions
    $min_wp_version = '5.0';
    $min_php_version = '7.4';
    
    // Check WordPress version
    if (version_compare($wp_version, $min_wp_version, '<')) {
        return sprintf(
            'Smart AI Linker requires WordPress %s or higher. You are running version %s. Please upgrade WordPress to use this plugin.',
            $min_wp_version,
            $wp_version
        );
    }
    
    // Check PHP version
    if (version_compare(PHP_VERSION, $min_php_version, '<')) {
        return sprintf(
            'Smart AI Linker requires PHP %s or higher. You are running version %s. Please upgrade PHP or contact your hosting provider to upgrade PHP.',
            $min_php_version,
            PHP_VERSION
        );
    }
    
    // Check for required PHP extensions
    $required_extensions = array('curl', 'json', 'mbstring');
    $missing_extensions = array();
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (!empty($missing_extensions)) {
        return sprintf(
            'The following required PHP extensions are missing: %s. Please contact your hosting provider to enable these extensions.',
            implode(', ', $missing_extensions)
        );
    }
    
    return true;
}

/**
 * Display admin notice for compatibility issues
 */
function smart_ai_linker_compatibility_notice() {
    $compatibility = smart_ai_linker_check_requirements();
    
    if ($compatibility !== true) {
        ?>
        <div class="notice notice-error">
            <p><strong>Smart AI Linker - Compatibility Issue:</strong> <?php echo esc_html($compatibility); ?></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'smart_ai_linker_compatibility_notice');

/**
 * Deactivate the plugin if requirements are not met
 */
function smart_ai_linker_maybe_deactivate() {
    if (is_admin() && current_user_can('activate_plugins') && 
        (!defined('DOING_AJAX') || !DOING_AJAX)) {
        
        $compatibility = smart_ai_linker_check_requirements();
        
        if ($compatibility !== true) {
            // Deactivate the plugin
            deactivate_plugins(plugin_basename(__FILE__));
            
            // Show admin notice
            add_action('admin_notices', function() use ($compatibility) {
                ?>
                <div class="notice notice-error">
                    <p><strong>Smart AI Linker has been deactivated.</strong> <?php echo esc_html($compatibility); ?></p>
                </div>
                <?php
            });
            
            // Hide the default "Plugin activated" notice
            if (isset($_GET['activate'])) {
                unset($_GET['activate']);
            }
        }
    }
}
add_action('admin_init', 'smart_ai_linker_maybe_deactivate');
