<?php
/**
 * Plugin Name: Smart AI Linker
 * Description: AI-powered internal linking and silo structuring plugin using DeepSeek API.
 * Version: 1.0.0
 * Author: M Moiz
 * Author URI: https://github.com/moizxox/
 * Plugin URI: https://github.com/moizxox/smart-ai-linker
 * Text Domain: smart-ai-linker
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SMARTLINK_AI_VERSION', '1.0.0');
define('SMARTLINK_AI_PATH', plugin_dir_path(__FILE__));
define('SMARTLINK_AI_URL', plugin_dir_url(__FILE__));
define('SMARTLINK_AI_BASENAME', plugin_basename(__FILE__));

// Include compatibility checks
require_once SMARTLINK_AI_PATH . 'includes/compatibility.php';

// Only proceed if compatibility check passes
if (smart_ai_linker_check_requirements() === true) {
    // Include core functionality
    require_once SMARTLINK_AI_PATH . 'includes/internal-linking.php';
    require_once SMARTLINK_AI_PATH . 'includes/silo-structure.php';
    require_once SMARTLINK_AI_PATH . 'includes/meta-box.php';
    require_once SMARTLINK_AI_PATH . 'admin/setting.page.php';
    require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';
    
    /**
     * Load plugin textdomain for translations
     */
    function smart_ai_linker_load_textdomain() {
        load_plugin_textdomain('smart-ai-linker', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    add_action('plugins_loaded', 'smart_ai_linker_load_textdomain');
    
    /**
     * Plugin activation hook
     */
    function smart_ai_linker_activate() {
        // Set default options if they don't exist
        if (false === get_option('smart_ai_linker_enable_auto_linking')) {
            update_option('smart_ai_linker_enable_auto_linking', '1');
        }
        
        if (false === get_option('smart_ai_linker_max_links')) {
            update_option('smart_ai_linker_max_links', '7');
        }
        
        if (false === get_option('smart_ai_linker_post_types')) {
            update_option('smart_ai_linker_post_types', array('post'));
        }
    }
    register_activation_hook(__FILE__, 'smart_ai_linker_activate');
    
    /**
     * Plugin deactivation hook
     */
    function smart_ai_linker_deactivate() {
        // Clean up any temporary data if needed
    }
    register_deactivation_hook(__FILE__, 'smart_ai_linker_deactivate');
}
