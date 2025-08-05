<?php

/**
 * Plugin Name: Smart AI Linker
 * Description: AI-powered internal linking and silo structuring plugin using DeepSeek API.
 * Version: 1.0
 * Author: Sitelinx
 * Author URI: https://seo.sitelinx.co.il
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

// Reset verification on activation - must be outside plugins_loaded
function smart_ai_linker_activation_reset_verification() {
    update_option('smart_ai_linker_verified', '0');
}
register_activation_hook(__FILE__, 'smart_ai_linker_activation_reset_verification');

// Plugin activation hook - must be outside plugins_loaded
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

// Plugin deactivation hook - must be outside plugins_loaded
function smart_ai_linker_deactivate() {
    // Clean up any temporary data if needed
}
register_deactivation_hook(__FILE__, 'smart_ai_linker_deactivate');

add_action('plugins_loaded', function () {
    
    // Define plugin constants
    define('SMARTLINK_AI_VERSION', '1.0.0');
    define('SMARTLINK_AI_PATH', plugin_dir_path(__FILE__));
    define('SMARTLINK_AI_URL', plugin_dir_url(__FILE__));
    define('SMARTLINK_AI_BASENAME', plugin_basename(__FILE__));

    // Function to check if the current user is the authorized admin
    function is_auth_admin() {
        if (!is_user_logged_in()) {
            return false;
        }
        $current_user = wp_get_current_user();
        return $current_user->user_email === 'tamirperl@gmail.com';
    }

    // Function to check if plugin is verified with password
    function smart_ai_linker_is_verified() {
        return get_option('smart_ai_linker_verified', '0') === '1';
    }

    // Function to check if user can access plugin
    function smart_ai_linker_can_access() {
        return is_auth_admin() && smart_ai_linker_is_verified();
    }

    // Block all plugin functionality for unauthorized users
    if (!smart_ai_linker_can_access()) {
        // Remove all plugin menus and features
        add_action('admin_menu', function() {
            // Remove any existing plugin menus
            remove_menu_page('smart-ai-linker');
            remove_menu_page('smart-ai-bulk-processing');
            
            // Remove any submenus
            remove_submenu_page('smart-ai-linker', 'smart-ai-linker');
            remove_submenu_page('smart-ai-linker', 'smart-ai-bulk-processing');
        }, 999);

        // Block all AJAX handlers
        add_action('wp_ajax_smart_ai_bulk_get_unprocessed', function() {
            wp_send_json_error('Access denied');
        }, 0);
        add_action('wp_ajax_smart_ai_bulk_start', function() {
            wp_send_json_error('Access denied');
        }, 0);
        add_action('wp_ajax_smart_ai_bulk_next', function() {
            wp_send_json_error('Access denied');
        }, 0);
        add_action('wp_ajax_smart_ai_bulk_status', function() {
            wp_send_json_error('Access denied');
        }, 0);
        add_action('wp_ajax_smart_ai_bulk_stop', function() {
            wp_send_json_error('Access denied');
        }, 0);

        // Block all other plugin AJAX handlers
        add_action('wp_ajax_smart_ai_linker_generate_links', function() {
            wp_send_json_error('Access denied');
        }, 0);
        add_action('wp_ajax_smart_ai_linker_clear_links', function() {
            wp_send_json_error('Access denied');
        }, 0);
        add_action('wp_ai_create_silo', function() {
            wp_send_json_error('Access denied');
        }, 0);
        add_action('wp_ajax_smart_ai_update_silo', function() {
            wp_send_json_error('Access denied');
        }, 0);
        add_action('wp_ajax_smart_ai_delete_silo', function() {
            wp_send_json_error('Access denied');
        }, 0);

        // Only show unlock page for authorized user
        if (is_auth_admin()) {
            add_action('admin_menu', function() {
                add_menu_page(
                    __('Smart AI Linker', 'smart-ai-linker'),
                    __('Smart AI Linker', 'smart-ai-linker'),
                    'manage_options',
                    'smart-ai-linker-unlock',
                    function() {
                        include plugin_dir_path(__FILE__) . 'admin/views/unlock.php';
                    },
                    'dashicons-lock',
                    30
                );
            });
        }

        // Do not load any other plugin code
        return;
    }

    // Include compatibility checks
    require_once SMARTLINK_AI_PATH . 'includes/compatibility.php';


    // Test files removed for production



    function smart_ai_linker_require_verification() {
        // Only allow access to the settings page for verification
        if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'smart-ai-linker') {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p><strong>Smart AI Linker:</strong> Please verify the plugin to unlock all features.</p></div>';
            });
            return;
        }
        // Remove plugin menus and features
        add_action('admin_menu', function() {
            remove_menu_page('smart-ai-linker');
        }, 999);
        // Optionally, block plugin-specific actions here
    }



    // Always include the settings page so it is available for verification
    require_once SMARTLINK_AI_PATH . 'admin/setting.page.php';

    // Bulk processing AJAX handlers are now handled in includes/bulk-processing.php

    // Bulk processing AJAX handlers are now handled in includes/bulk-processing.php

    // Only load other features if verified
    if (!smart_ai_linker_is_verified()) {
        add_action('admin_init', 'smart_ai_linker_require_verification', 0);
        // Do not load any other plugin code
        return;
    }

    // Only proceed if compatibility check passes and the plugin isn't already loaded
    if (smart_ai_linker_check_requirements() === true && !defined('SMART_AI_LINKER_LOADED')) {
        define('SMART_AI_LINKER_LOADED', true);

        // Include core functionality
        require_once SMARTLINK_AI_PATH . 'includes/internal-linking.php';
        require_once SMARTLINK_AI_PATH . 'includes/silo-structure.php';
        require_once SMARTLINK_AI_PATH . 'includes/bulk-processing.php';
        require_once SMARTLINK_AI_PATH . 'includes/meta-box.php';
        require_once SMARTLINK_AI_PATH . 'includes/broken-links.php';
        require_once SMARTLINK_AI_PATH . 'admin/setting.page.php';
        require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';
        require_once SMARTLINK_AI_PATH . 'includes/featured-image-describer.php';

        /**
         * Load plugin textdomain for translations
         */
        function smart_ai_linker_load_textdomain()
        {
            load_plugin_textdomain('smart-ai-linker', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }

        add_action('plugins_loaded', 'smart_ai_linker_load_textdomain');


    }

    // Add bulk processing menu for authorized users only
    add_action('admin_menu', function() {
        add_menu_page(
            __('Bulk Processing Center', 'smart-ai-linker'),
            __('Internal Linking Bulk Process', 'smart-ai-linker'),
            'manage_options',
            'smart-ai-bulk-processing',
            function() {
                include plugin_dir_path(__FILE__) . 'admin/views/bulk-processing-center.php';
            },
            'dashicons-update',
            31
        );
    });

});