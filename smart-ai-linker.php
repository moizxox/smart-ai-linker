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

add_action('plugins_loaded', function () {
    
    // Define plugin constants
    define('SMARTLINK_AI_VERSION', '1.0.0');
    define('SMARTLINK_AI_PATH', plugin_dir_path(__FILE__));
    define('SMARTLINK_AI_URL', plugin_dir_url(__FILE__));
    define('SMARTLINK_AI_BASENAME', plugin_basename(__FILE__));

    // Function to check if the current user is the authorized admin
    function is_authorized_deepseek_user() {
        if (!is_user_logged_in()) {
            return false;
        }
        $current_user = wp_get_current_user();
        return $current_user->user_email === 'tamirperl@gmail.com';
    }

    // Function to check if plugin is unlocked with password
    function smart_ai_linker_is_unlocked() {
        return get_option('smart_ai_linker_unlocked', '0') === '1';
    }

    // Function to check if user can access plugin
    function smart_ai_linker_can_access() {
        return is_authorized_deepseek_user() && smart_ai_linker_is_unlocked();
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
        if (is_authorized_deepseek_user()) {
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
        update_option('smart_ai_linker_unlocked', '0');
    }
    register_activation_hook(__FILE__, 'smart_ai_linker_activation_reset_verification');

    // Always include the settings page so it is available for verification
    require_once SMARTLINK_AI_PATH . 'admin/setting.page.php';

    // Add AJAX handler for getting unprocessed posts
    add_action('wp_ajax_smart_ai_bulk_get_unprocessed', function() {
        if (!smart_ai_linker_can_access()) {
            wp_send_json_error('Access denied');
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
        if (!smart_ai_linker_can_access()) wp_send_json_error('Access denied');
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
        if (!smart_ai_linker_can_access()) wp_send_json_error('Access denied');
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
            $post = get_post($post_id);
            if ($post && $post->post_status === 'publish') {
                $clean_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
                
                // Skip if content is too short
                if (str_word_count($clean_content) < 30) {
                    $progress['processed']++;
                    $progress['status'][$post_id] = 'skipped';
                    update_option('smart_ai_bulk_queue', $queue);
                    update_option('smart_ai_bulk_progress', $progress);
                    wp_send_json_success(['done' => empty($queue), 'progress' => $progress, 'current' => $post_id]);
                    return;
                }
                
                // Get AI suggestions
                if (function_exists('smart_ai_linker_get_ai_link_suggestions')) {
                    $suggestions = smart_ai_linker_get_ai_link_suggestions($clean_content, $post_id, $post->post_type);
                    
                    if (!empty($suggestions) && is_array($suggestions)) {
                        // Limit the number of links based on settings
                        $option_max = (int) get_option('smart_ai_linker_max_links', 7);
                        $option_max = $option_max > 0 ? min(7, $option_max) : 7;
                        $suggestions = array_slice($suggestions, 0, $option_max);
                        
                        // Insert the links
                        if (function_exists('smart_ai_linker_insert_links_into_post')) {
                            $result = smart_ai_linker_insert_links_into_post($post_id, $suggestions);
                            if ($result) {
                                update_post_meta($post_id, '_smart_ai_linker_processed', current_time('mysql'));
                                update_post_meta($post_id, '_smart_ai_linker_added_links', count($suggestions));
                                $progress['status'][$post_id] = 'processed';
                            } else {
                                $progress['status'][$post_id] = 'error';
                            }
                        } else {
                            $progress['status'][$post_id] = 'error';
                        }
                    } else {
                        $progress['status'][$post_id] = 'error';
                    }
                } else {
                    $progress['status'][$post_id] = 'error';
                }
            } else {
                $progress['status'][$post_id] = 'skipped';
            }
            $progress['processed']++;
        }
        update_option('smart_ai_bulk_queue', $queue);
        update_option('smart_ai_bulk_progress', $progress);
        wp_send_json_success(['done' => empty($queue), 'progress' => $progress, 'current' => $post_id]);
    });

    add_action('wp_ajax_smart_ai_bulk_status', function() {
        if (!smart_ai_linker_can_access()) wp_send_json_error('Access denied');
        $progress = get_option('smart_ai_bulk_progress', array('total' => 0, 'processed' => 0, 'status' => array()));
        $running = get_option('smart_ai_bulk_running', 0);
        wp_send_json_success(['progress' => $progress, 'running' => $running]);
    });

    add_action('wp_ajax_smart_ai_bulk_stop', function() {
        if (!smart_ai_linker_can_access()) wp_send_json_error('Access denied');
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
        require_once SMARTLINK_AI_PATH . 'includes/featured-image-describer.php';

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