<?php

/**
 * Plugin Name: Smart AI Linker
 * Description: AI-powered internal linking and silo structuring plugin using DeepSeek API.
 * Version: 1.0
 * Author: Sitelinx (https://seo.sitelinx.co.il)
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

// Define plugin constants
define('SMARTLINK_AI_VERSION', '1.0.0');
define('SMARTLINK_AI_PATH', plugin_dir_path(__FILE__));
define('SMARTLINK_AI_URL', plugin_dir_url(__FILE__));
define('SMARTLINK_AI_BASENAME', plugin_basename(__FILE__));

// Include compatibility checks
require_once SMARTLINK_AI_PATH . 'includes/compatibility.php';

// Include silo structure functionality
require_once SMARTLINK_AI_PATH . 'includes/silo-structure.php';

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

// Add plugin verification logic
function smart_ai_linker_is_verified() {
    return get_option('smart_ai_linker_verified', '0') === '1';
}

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

// Reset verification on activation
function smart_ai_linker_activation_reset_verification() {
    update_option('smart_ai_linker_verified', '0');
}
register_activation_hook(__FILE__, 'smart_ai_linker_activation_reset_verification');

// Always include the settings page so it is available for verification
require_once SMARTLINK_AI_PATH . 'admin/setting.page.php';

// Add Bulk Processing Center menu
add_action('admin_menu', function() {
    add_menu_page(
        __('Bulk Processing Center', 'smart-ai-linker'),
        __('Bulk Processing', 'smart-ai-linker'),
        'manage_options',
        'smart-ai-bulk-processing',
        function() {
            include plugin_dir_path(__FILE__) . 'admin/views/bulk-processing-center.php';
        },
        'dashicons-update',
        31
    );
});

// Add AJAX handler for getting unprocessed posts
add_action('wp_ajax_smart_ai_bulk_get_unprocessed', function() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $args = array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => '_smart_ai_linker_processed', 'compare' => 'NOT EXISTS'),
            array('key' => '_smart_ai_linker_processed', 'value' => '', 'compare' => '=')
        ),
    );
    $ids = get_posts($args);
    $posts = array();
    foreach ($ids as $id) {
        $posts[] = array('id' => $id, 'title' => get_the_title($id));
    }
    wp_send_json_success($posts);
});

// --- Bulk Processing Center Backend Logic ---
add_action('wp_ajax_smart_ai_bulk_start', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $args = array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => '_smart_ai_linker_processed', 'compare' => 'NOT EXISTS'),
            array('key' => '_smart_ai_linker_processed', 'value' => '', 'compare' => '=')
        ),
    );
    $ids = get_posts($args);
    update_option('smart_ai_bulk_queue', $ids);
    update_option('smart_ai_bulk_progress', array('total' => count($ids), 'processed' => 0, 'status' => array()));
    update_option('smart_ai_bulk_running', 1);
    wp_send_json_success(['total' => count($ids)]);
});

add_action('wp_ajax_smart_ai_bulk_next', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    if (!get_option('smart_ai_bulk_running')) wp_send_json_error('Not running');
    $queue = get_option('smart_ai_bulk_queue', []);
    $progress = get_option('smart_ai_bulk_progress', array('total' => 0, 'processed' => 0, 'status' => array()));
    if (empty($queue)) {
        update_option('smart_ai_bulk_running', 0);
        wp_send_json_success(['done' => true, 'progress' => $progress]);
    }
    $post_id = array_shift($queue);
    $result = false;
    if ($post_id) {
        // Use the existing internal linking logic
        if (function_exists('smart_ai_linker_generate_internal_links')) {
            $result = smart_ai_linker_generate_internal_links($post_id);
        }
        $progress['processed']++;
        $progress['status'][$post_id] = $result instanceof WP_Error ? 'error' : 'processed';
    }
    update_option('smart_ai_bulk_queue', $queue);
    update_option('smart_ai_bulk_progress', $progress);
    wp_send_json_success(['done' => empty($queue), 'progress' => $progress, 'current' => $post_id]);
});

add_action('wp_ajax_smart_ai_bulk_status', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    $progress = get_option('smart_ai_bulk_progress', array('total' => 0, 'processed' => 0, 'status' => array()));
    $running = get_option('smart_ai_bulk_running', 0);
    wp_send_json_success(['progress' => $progress, 'running' => $running]);
});

add_action('wp_ajax_smart_ai_bulk_stop', function() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    update_option('smart_ai_bulk_running', 0);
    update_option('smart_ai_bulk_queue', array());
    wp_send_json_success();
});
// --- End Bulk Processing Center Backend Logic ---

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
    require_once plugin_dir_path(__FILE__) . 'includes/featured-image-describer.php';

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
