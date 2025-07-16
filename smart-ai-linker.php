<?php

/**
 * Plugin Name: Smart AI Linker
 * Description: AI-powered internal linking and silo structuring plugin using DeepSeek API.
 * Version: 1.0.0
 * Author: NerdX Solution
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

// Include test files in debug mode
if (defined('WP_DEBUG') && WP_DEBUG && !defined('SMART_AI_LINKER_TEST_LOADED')) {
    define('SMART_AI_LINKER_TEST_LOADED', true);

    // Check if the test file exists before including it
    $test_file = SMARTLINK_AI_PATH . 'tests/test-internal-linking.php';
    if (file_exists($test_file)) {
        require_once $test_file;
    }

    // Add test menu
    add_action('admin_menu', function () {
        // Check if the menu already exists
        global $menu, $submenu;
        $menu_exists = false;

        // Check if the main menu exists
        foreach ($menu as $item) {
            if (isset($item[2]) && $item[2] === 'smart-ai-linker') {
                $menu_exists = true;
                break;
            }
        }

        // If the main menu doesn't exist, create it
        if (!$menu_exists) {
            add_menu_page(
                'Smart AI Linker',
                'Smart AI Linker',
                'manage_options',
                'smart-ai-linker',
                '',
                'dashicons-admin-links',
                30
            );

            // Remove the default submenu item that gets added
            remove_submenu_page('smart-ai-linker', 'smart-ai-linker');
        }

        // Now add our test page
        add_submenu_page(
            'smart-ai-linker',
            'API Connection Test',
            'API Test',
            'manage_options',
            'smart-ai-linker-test-connection',
            function () {
                $test_connection_file = SMARTLINK_AI_PATH . 'tests/test-connection.php';
                if (file_exists($test_connection_file)) {
                    require_once $test_connection_file;
                } else {
                    echo '<div class="notice notice-error"><p>Test connection file not found.</p></div>';
                }
            }
        );
    }, 11);  // Higher priority to ensure main menu is processed first
}

// Only proceed if compatibility check passes and the plugin isn't already loaded
if (smart_ai_linker_check_requirements() === true && !defined('SMART_AI_LINKER_LOADED')) {
    define('SMART_AI_LINKER_LOADED', true);

    // Include core functionality
    require_once SMARTLINK_AI_PATH . 'includes/internal-linking.php';
    require_once SMARTLINK_AI_PATH . 'includes/silo-structure.php';
    require_once SMARTLINK_AI_PATH . 'includes/meta-box.php';
    require_once SMARTLINK_AI_PATH . 'admin/setting.page.php';
    require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';

    /**
     * Load plugin textdomain for translations
     */
    function smart_ai_linker_load_textdomain()
    {
        load_plugin_textdomain('smart-ai-linker', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    add_action('plugins_loaded', 'smart_ai_linker_load_textdomain');

    /**
     * Plugin activation hook
     */
    function smart_ai_linker_activate()
    {
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
    function smart_ai_linker_deactivate()
    {
        // Clean up any temporary data if needed
    }

    register_deactivation_hook(__FILE__, 'smart_ai_linker_deactivate');
}
