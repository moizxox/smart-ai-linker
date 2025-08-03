<?php
/**
 * Bulk Processing for Smart AI Linker
 * 
 * @package SmartAILinker
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add bulk action to process multiple posts
 */
function smart_ai_linker_register_bulk_actions($bulk_actions) {
    $bulk_actions['smart_ai_linker_generate_links'] = __('Generate AI Links', 'smart-ai-linker');
    return $bulk_actions;
}
add_filter('bulk_actions-edit-post', 'smart_ai_linker_register_bulk_actions');
add_filter('bulk_actions-edit-page', 'smart_ai_linker_register_bulk_actions');

/**
 * Handle the bulk action
 */
function smart_ai_linker_bulk_action_handler($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'smart_ai_linker_generate_links') {
        return $redirect_to;
    }

    $processed = 0;
    $skipped = 0;
    $skipped_details = [];
    
    // Include necessary files if not already loaded
    if (!function_exists('smart_ai_linker_get_ai_link_suggestions')) {
        require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';
    }
    if (!function_exists('smart_ai_linker_insert_links_into_post')) {
        require_once SMARTLINK_AI_PATH . 'includes/internal-linking.php';
    }

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        
        // Skip if post doesn't exist or is not published
        if (!$post || $post->post_status !== 'publish') {
            $skipped++;
            $skipped_details[] = sprintf('Post ID %d skipped: not published or does not exist', $post_id);
            continue;
        }

        // Get clean content for AI processing
        $clean_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        
        // Skip if content is too short
        $word_count = str_word_count($clean_content);
        if ($word_count < 10) {
            $skipped++;
            $skipped_details[] = sprintf('Post ID %d skipped: only %d words', $post_id, $word_count);
            continue;
        }

        // --- Silo Linking Priority for Bulk ---
        $silo_post_ids = [];
        if (class_exists('Smart_AI_Linker_Silos')) {
            $silo_instance = Smart_AI_Linker_Silos::get_instance();
            $post_silos = $silo_instance->get_post_silos($post_id);
            if (!empty($post_silos)) {
                global $wpdb;
                $silo_ids = array_map(function($silo){ return is_object($silo) ? $silo->id : $silo['id']; }, $post_silos);
                $placeholders = implode(',', array_fill(0, count($silo_ids), '%d'));
                $query = $wpdb->prepare(
                    "SELECT post_id FROM {$silo_instance->silo_relationships} WHERE silo_id IN ($placeholders) AND post_id != %d",
                    array_merge($silo_ids, [$post_id])
                );
                $silo_post_ids = $wpdb->get_col($query);
            }
        }
        // --- End Silo Linking Priority for Bulk ---

        // Get AI suggestions (with post type and silo info)
        $suggestions = smart_ai_linker_get_ai_link_suggestions($clean_content, $post_id, $post->post_type, $silo_post_ids);
        
        if (empty($suggestions) || !is_array($suggestions)) {
            if (is_wp_error($suggestions)) {
                error_log('[Smart AI] Bulk DeepSeek error for post ' . $post_id . ': ' . $suggestions->get_error_message());
            }
            $skipped++;
            $skipped_details[] = sprintf('Post ID %d skipped: no AI suggestions', $post_id);
            continue;
        }

        // Limit the number of links based on settings
        $option_max = (int) get_option('smart_ai_linker_max_links', 7);
        $option_max = $option_max > 0 ? min(7, $option_max) : 7;
        $max_links = $option_max;
        $suggestions = array_slice($suggestions, 0, $max_links);

        // Insert the links
        $result = smart_ai_linker_insert_links_into_post($post_id, $suggestions);
        
        if ($result) {
            update_post_meta($post_id, '_smart_ai_linker_processed', current_time('mysql'));
            update_post_meta($post_id, '_smart_ai_linker_added_links', count($suggestions));
            $processed++;
        } else {
            $skipped++;
            $skipped_details[] = sprintf('Post ID %d skipped: failed to insert links', $post_id);
        }
    }

    // Add results to the redirect URL
    $redirect_to = add_query_arg(
        array(
            'smart_ai_links_processed' => $processed,
            'smart_ai_links_skipped' => $skipped,
            'smart_ai_links_skipped_details' => urlencode(json_encode($skipped_details)),
        ),
        $redirect_to
    );

    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-post', 'smart_ai_linker_bulk_action_handler', 10, 3);
add_filter('handle_bulk_actions-edit-page', 'smart_ai_linker_bulk_action_handler', 10, 3);

/**
 * Show admin notice after bulk processing
 */
function smart_ai_linker_bulk_admin_notice() {
    if (!empty($_REQUEST['smart_ai_links_processed']) || !empty($_REQUEST['smart_ai_links_skipped'])) {
        $processed = isset($_REQUEST['smart_ai_links_processed']) ? intval($_REQUEST['smart_ai_links_processed']) : 0;
        $skipped = isset($_REQUEST['smart_ai_links_skipped']) ? intval($_REQUEST['smart_ai_links_skipped']) : 0;
        $skipped_details = [];
        if (!empty($_REQUEST['smart_ai_links_skipped_details'])) {
            $skipped_details = json_decode(urldecode($_REQUEST['smart_ai_links_skipped_details']), true);
        }
        $message = '';
        if ($processed > 0) {
            $message .= sprintf(
                _n('%d post was successfully processed with AI links.', '%d posts were successfully processed with AI links.', $processed, 'smart-ai-linker'),
                $processed
            );
            $message .= ' ';
        }
        if ($skipped > 0) {
            $message .= sprintf(
                _n('%d post was skipped.', '%d posts were skipped.', $skipped, 'smart-ai-linker'),
                $skipped
            );
            if (!empty($skipped_details)) {
                $message .= '<ul style="margin:8px 0 0 20px;">';
                foreach ($skipped_details as $detail) {
                    $message .= '<li>' . esc_html($detail) . '</li>';
                }
                $message .= '</ul>';
            }
        }
        if (!empty($message)) {
            echo '<div class="notice notice-info is-dismissible"><p>' . $message . '</p></div>';
        }
    }
}
add_action('admin_notices', 'smart_ai_linker_bulk_admin_notice');

// Removed old 'Process All' button and related JS

/**
 * AJAX handler for processing all unprocessed posts
 */
function smart_ai_linker_process_all_ajax() {
    // Verify nonce
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'smart_ai_linker_process_all')) {
        wp_send_json_error(__('Security check failed.', 'smart-ai-linker'));
    }
    
    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(__('You do not have sufficient permissions.', 'smart-ai-linker'));
    }
    
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    
    // Get all unprocessed posts
    $args = array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => '_smart_ai_linker_processed',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => '_smart_ai_linker_processed',
                'value' => '',
                'compare' => '=',
            )
        ),
    );
    
    $unprocessed_posts = get_posts($args);
    $processed = 0;
    $errors = array();
    
    // Include necessary files if not already loaded
    if (!function_exists('smart_ai_linker_get_ai_link_suggestions')) {
        require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';
    }
    if (!function_exists('smart_ai_linker_insert_links_into_post')) {
        require_once SMARTLINK_AI_PATH . 'includes/internal-linking.php';
    }
    
    // Process each post
    foreach ($unprocessed_posts as $post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            continue;
        }
        
        // Get clean content for AI processing
        $clean_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        
        // Skip if content is too short
        if (str_word_count($clean_content) < 30) {
            continue;
        }
        
        // Get AI suggestions
        $suggestions = smart_ai_linker_get_ai_link_suggestions($clean_content, $post_id);
        
        if (empty($suggestions) || !is_array($suggestions)) {
            $errors[] = sprintf(__('No suggestions for post #%d', 'smart-ai-linker'), $post_id);
            continue;
        }
        
        // Limit the number of links based on settings
        $option_max = (int) get_option('smart_ai_linker_max_links', 7);
        $option_max = $option_max > 0 ? min(7, $option_max) : 7;
        $max_links = $option_max;
        $suggestions = array_slice($suggestions, 0, $max_links);
        
        // Insert the links
        $result = smart_ai_linker_insert_links_into_post($post_id, $suggestions);
        
        if ($result) {
            update_post_meta($post_id, '_smart_ai_linker_processed', current_time('mysql'));
            update_post_meta($post_id, '_smart_ai_linker_added_links', count($suggestions));
            $processed++;
        } else {
            $errors[] = sprintf(__('Failed to process post #%d', 'smart-ai-linker'), $post_id);
        }
        
        // Add a small delay to prevent server overload
        usleep(500000); // 0.5 second
    }
    
    // Prepare response
    $response = array(
        'processed' => $processed,
        'total' => count($unprocessed_posts),
        'errors' => $errors,
    );
    
    wp_send_json_success($response);
}
add_action('wp_ajax_smart_ai_linker_process_all', 'smart_ai_linker_process_all_ajax');

// --- Background Bulk Processing with Progress Tracking ---
add_action('wp_ajax_smart_ai_linker_start_bulk', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
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
    $unprocessed = get_posts($args);
    update_option('smart_ai_linker_bulk_queue', $unprocessed);
    update_option('smart_ai_linker_bulk_progress', array('total' => count($unprocessed), 'processed' => 0));
    wp_send_json_success(array('total' => count($unprocessed)));
});

add_action('wp_ajax_smart_ai_linker_process_next', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    $queue = get_option('smart_ai_linker_bulk_queue', []);
    $progress = get_option('smart_ai_linker_bulk_progress', array('total' => 0, 'processed' => 0));
    if (empty($queue)) {
        wp_send_json_success(array('done' => true, 'progress' => $progress));
    }
    $post_id = array_shift($queue);
    if ($post_id) {
        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish') {
            $clean_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
            $silo_post_ids = [];
            if (class_exists('Smart_AI_Linker_Silos')) {
                $silo_instance = Smart_AI_Linker_Silos::get_instance();
                $post_silos = $silo_instance->get_post_silos($post_id);
                if (!empty($post_silos)) {
                    global $wpdb;
                    $silo_ids = array_map(function($silo){ return is_object($silo) ? $silo->id : $silo['id']; }, $post_silos);
                    $placeholders = implode(',', array_fill(0, count($silo_ids), '%d'));
                    $query = $wpdb->prepare(
                        "SELECT post_id FROM {$silo_instance->silo_relationships} WHERE silo_id IN ($placeholders) AND post_id != %d",
                        array_merge($silo_ids, [$post_id])
                    );
                    $silo_post_ids = $wpdb->get_col($query);
                }
            }
            $suggestions = smart_ai_linker_get_ai_link_suggestions($clean_content, $post_id, $post->post_type, $silo_post_ids);
            $max_links = max(7, (int) get_option('smart_ai_linker_max_links', 7));
            $suggestions = array_slice($suggestions, 0, $max_links);
            smart_ai_linker_insert_links_into_post($post_id, $suggestions);
            update_post_meta($post_id, '_smart_ai_linker_processed', current_time('mysql'));
            update_post_meta($post_id, '_smart_ai_linker_added_links', count($suggestions));
        }
        $progress['processed']++;
        update_option('smart_ai_linker_bulk_queue', $queue);
        update_option('smart_ai_linker_bulk_progress', $progress);
        wp_send_json_success(array('done' => false, 'progress' => $progress));
    } else {
        wp_send_json_success(array('done' => true, 'progress' => $progress));
    }
});

add_action('wp_ajax_smart_ai_linker_stop_bulk', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    delete_option('smart_ai_linker_bulk_queue');
    delete_option('smart_ai_linker_bulk_progress');
    wp_send_json_success();
});
// --- End Background Bulk Processing ---

// Ensure admin.js is enqueued on edit.php (posts/pages list)
add_action('admin_enqueue_scripts', function($hook) {
    if ($hook === 'edit.php') {
        wp_enqueue_script(
            'smart-ai-linker-admin-bulk',
            plugins_url('assets/js/admin.js', dirname(dirname(__FILE__))),
            array('jquery'),
            SMARTLINK_AI_VERSION,
            true
        );
    }
});
