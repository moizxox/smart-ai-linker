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
 * Handle the bulk action with improved error handling and retry logic
 */
function smart_ai_linker_bulk_action_handler($redirect_to, $doaction, $post_ids) {
    if ($doaction !== 'smart_ai_linker_generate_links') {
        return $redirect_to;
    }

    $processed = 0;
    $skipped = 0;
    $errors = [];
    $skipped_details = [];
    
    // Include necessary files if not already loaded
    if (!function_exists('smart_ai_linker_get_ai_link_suggestions')) {
        require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';
    }
    if (!function_exists('smart_ai_linker_insert_links_into_post')) {
        require_once SMARTLINK_AI_PATH . 'includes/internal-linking.php';
    }

    // Process posts in batches to avoid timeouts
    $batch_size = 5;
    $total_posts = count($post_ids);
    
    for ($i = 0; $i < $total_posts; $i += $batch_size) {
        $batch = array_slice($post_ids, $i, $batch_size);
        
        foreach ($batch as $post_id) {
            $result = smart_ai_linker_process_single_post($post_id);
            
            switch ($result['status']) {
                case 'processed':
                    $processed++;
                    break;
                case 'skipped':
                    $skipped++;
                    $skipped_details[] = $result['reason'];
                    break;
                case 'error':
                    $errors[] = "Post ID {$post_id}: " . $result['reason'];
            $skipped++;
                    $skipped_details[] = "Post ID {$post_id}: " . $result['reason'];
                    break;
            }
        }
        
        // Add a small delay between batches to prevent server overload
        if ($i + $batch_size < $total_posts) {
            usleep(1000000); // 1 second delay
        }
    }

    // Add results to the redirect URL
    $redirect_to = add_query_arg(
        array(
            'smart_ai_links_processed' => $processed,
            'smart_ai_links_skipped' => $skipped,
            'smart_ai_links_errors' => urlencode(json_encode($errors)),
            'smart_ai_links_skipped_details' => urlencode(json_encode($skipped_details)),
        ),
        $redirect_to
    );

    return $redirect_to;
}
add_filter('handle_bulk_actions-edit-post', 'smart_ai_linker_bulk_action_handler', 10, 3);
add_filter('handle_bulk_actions-edit-page', 'smart_ai_linker_bulk_action_handler', 10, 3);

/**
 * Process a single post with comprehensive error handling and retry logic
 * 
 * @param int $post_id The post ID to process
 * @return array Status array with 'status' and 'reason' keys
 */
function smart_ai_linker_process_single_post($post_id) {
    $post = get_post($post_id);
    
    // Skip if post doesn't exist or is not published
    if (!$post || $post->post_status !== 'publish') {
        return [
            'status' => 'skipped',
            'reason' => 'Post not published or does not exist'
        ];
        }

        // Check if this post is excluded from internal linking
        $excluded_posts = get_option('smart_ai_linker_excluded_posts', array());
        if (in_array($post_id, (array) $excluded_posts)) {
        return [
            'status' => 'skipped',
            'reason' => 'Post excluded from internal linking'
        ];
        }

        // Get clean content for AI processing
        $clean_content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        
        // Skip if content is too short
        $word_count = str_word_count($clean_content);
    $min_words = get_option('smart_ai_min_content_length', 30);
    if ($word_count < $min_words) {
        return [
            'status' => 'skipped',
            'reason' => "Only {$word_count} words (minimum: {$min_words})"
        ];
    }

    // Check if already processed recently (within last 24 hours)
    $last_processed = get_post_meta($post_id, '_smart_ai_linker_processed', true);
    if ($last_processed) {
        $last_processed_time = strtotime($last_processed);
        $twenty_four_hours_ago = time() - (24 * 60 * 60);
        
        if ($last_processed_time > $twenty_four_hours_ago) {
            return [
                'status' => 'skipped',
                'reason' => 'Already processed within last 24 hours'
            ];
        }
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

    // Get AI suggestions with retry logic
    $max_retries = 3;
    $suggestions = null;
    
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        try {
        $suggestions = smart_ai_linker_get_ai_link_suggestions($clean_content, $post_id, $post->post_type, $silo_post_ids);
        
            if (!empty($suggestions) && is_array($suggestions)) {
                break; // Success, exit retry loop
            }
            
            if ($attempt < $max_retries) {
                error_log("[Smart AI] Attempt {$attempt} failed for post {$post_id}, retrying...");
                sleep(2); // Wait 2 seconds before retry
            }
        } catch (Exception $e) {
            error_log("[Smart AI] Exception on attempt {$attempt} for post {$post_id}: " . $e->getMessage());
            if ($attempt < $max_retries) {
                sleep(2);
            }
        }
    }
    
    if (empty($suggestions) || !is_array($suggestions)) {
        return [
            'status' => 'error',
            'reason' => 'Failed to get AI suggestions after ' . $max_retries . ' attempts'
        ];
        }

        // Limit the number of links based on settings
        $option_max = (int) get_option('smart_ai_linker_max_links', 7);
        $option_max = $option_max > 0 ? min(7, $option_max) : 7;
        $max_links = $option_max;
        $suggestions = array_slice($suggestions, 0, $max_links);

    // Insert the links with error handling
    try {
        $result = smart_ai_linker_insert_links_into_post($post_id, $suggestions);
        
        if ($result) {
            update_post_meta($post_id, '_smart_ai_linker_processed', current_time('mysql'));
            update_post_meta($post_id, '_smart_ai_linker_added_links', $suggestions);
            
            return [
                'status' => 'processed',
                'reason' => 'Successfully processed with ' . count($suggestions) . ' links'
            ];
        } else {
            return [
                'status' => 'error',
                'reason' => 'Failed to insert links into post'
            ];
        }
    } catch (Exception $e) {
        error_log("[Smart AI] Exception inserting links for post {$post_id}: " . $e->getMessage());
        return [
            'status' => 'error',
            'reason' => 'Exception: ' . $e->getMessage()
        ];
    }
}

/**
 * Show admin notice after bulk processing with improved messaging
 */
function smart_ai_linker_bulk_admin_notice() {
    if (!empty($_REQUEST['smart_ai_links_processed']) || !empty($_REQUEST['smart_ai_links_skipped'])) {
        $processed = isset($_REQUEST['smart_ai_links_processed']) ? intval($_REQUEST['smart_ai_links_processed']) : 0;
        $skipped = isset($_REQUEST['smart_ai_links_skipped']) ? intval($_REQUEST['smart_ai_links_skipped']) : 0;
        $errors = [];
        $skipped_details = [];
        
        if (!empty($_REQUEST['smart_ai_links_errors'])) {
            $errors = json_decode(urldecode($_REQUEST['smart_ai_links_errors']), true);
        }
        if (!empty($_REQUEST['smart_ai_links_skipped_details'])) {
            $skipped_details = json_decode(urldecode($_REQUEST['smart_ai_links_skipped_details']), true);
        }
        
        $message = '';
        $notice_class = 'notice-info';
        
        if ($processed > 0) {
            $message .= sprintf(
                _n('%d post was successfully processed with AI links.', '%d posts were successfully processed with AI links.', $processed, 'smart-ai-linker'),
                $processed
            );
            $message .= ' ';
            $notice_class = 'notice-success';
        }
        
        if ($skipped > 0) {
            $message .= sprintf(
                _n('%d post was skipped.', '%d posts were skipped.', $skipped, 'smart-ai-linker'),
                $skipped
            );
        }
        
        if (!empty($errors)) {
            $message .= '<br><strong>Errors encountered:</strong><ul style="margin:8px 0 0 20px;">';
            foreach (array_slice($errors, 0, 5) as $error) {
                $message .= '<li>' . esc_html($error) . '</li>';
            }
            if (count($errors) > 5) {
                $message .= '<li>... and ' . (count($errors) - 5) . ' more errors</li>';
            }
            $message .= '</ul>';
            $notice_class = 'notice-warning';
        }
        
            if (!empty($skipped_details)) {
            $message .= '<br><strong>Skipped posts:</strong><ul style="margin:8px 0 0 20px;">';
            foreach (array_slice($skipped_details, 0, 5) as $detail) {
                    $message .= '<li>' . esc_html($detail) . '</li>';
                }
            if (count($skipped_details) > 5) {
                $message .= '<li>... and ' . (count($skipped_details) - 5) . ' more skipped posts</li>';
            }
            $message .= '</ul>';
        }
        
        if (!empty($message)) {
            echo '<div class="notice ' . $notice_class . ' is-dismissible"><p>' . $message . '</p></div>';
        }
    }
}
add_action('admin_notices', 'smart_ai_linker_bulk_admin_notice');

/**
 * AJAX handler for processing all unprocessed posts with improved strategy
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
    
    // Get all unprocessed posts with better filtering
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
        'orderby' => 'date',
        'order' => 'DESC', // Process newer posts first
    );
    
    $unprocessed_posts = get_posts($args);
    $processed = 0;
    $skipped = 0;
    $errors = array();
    
    // Include necessary files if not already loaded
    if (!function_exists('smart_ai_linker_get_ai_link_suggestions')) {
        require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';
    }
    if (!function_exists('smart_ai_linker_insert_links_into_post')) {
        require_once SMARTLINK_AI_PATH . 'includes/internal-linking.php';
    }
    
    // Process posts in smaller batches
    $batch_size = 3;
    $total_posts = count($unprocessed_posts);
    
    for ($i = 0; $i < $total_posts; $i += $batch_size) {
        $batch = array_slice($unprocessed_posts, $i, $batch_size);
        
        foreach ($batch as $post_id) {
            $result = smart_ai_linker_process_single_post($post_id);
            
            switch ($result['status']) {
                case 'processed':
                    $processed++;
                    break;
                case 'skipped':
                    $skipped++;
                    break;
                case 'error':
                    $errors[] = "Post ID {$post_id}: " . $result['reason'];
                    $skipped++;
                    break;
            }
        }
        
        // Add delay between batches
        if ($i + $batch_size < $total_posts) {
            usleep(1500000); // 1.5 second delay
        }
    }
    
    // Prepare response
    $response = array(
        'processed' => $processed,
        'skipped' => $skipped,
        'total' => count($unprocessed_posts),
        'errors' => array_slice($errors, 0, 10), // Limit error messages
    );
    
    wp_send_json_success($response);
}
add_action('wp_ajax_smart_ai_linker_process_all', 'smart_ai_linker_process_all_ajax');

// --- Improved Background Bulk Processing with Progress Tracking ---
add_action('wp_ajax_smart_ai_linker_start_bulk', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    
    // Get unprocessed posts with better filtering
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
        'orderby' => 'date',
        'order' => 'DESC',
    );
    
    $unprocessed = get_posts($args);
    
    if (empty($unprocessed)) {
        wp_send_json_error('No unprocessed posts found');
    }
    
    update_option('smart_ai_linker_bulk_queue', $unprocessed);
    update_option('smart_ai_linker_bulk_progress', array(
        'total' => count($unprocessed), 
        'processed' => 0,
        'skipped' => 0,
        'errors' => []
    ));
    
    wp_send_json_success(array('total' => count($unprocessed)));
});

add_action('wp_ajax_smart_ai_linker_process_next', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $queue = get_option('smart_ai_linker_bulk_queue', []);
    $progress = get_option('smart_ai_linker_bulk_progress', array('total' => 0, 'processed' => 0, 'skipped' => 0, 'errors' => []));
    
    if (empty($queue)) {
        wp_send_json_success(array('done' => true, 'progress' => $progress));
    }
    
    $post_id = array_shift($queue);
    
    if ($post_id) {
        $result = smart_ai_linker_process_single_post($post_id);
        
        switch ($result['status']) {
            case 'processed':
                $progress['processed']++;
                break;
            case 'skipped':
                $progress['skipped']++;
                break;
            case 'error':
                $progress['errors'][] = "Post ID {$post_id}: " . $result['reason'];
                $progress['skipped']++;
                break;
        }
        
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

// --- New AJAX handlers for better bulk processing ---
add_action('wp_ajax_smart_ai_bulk_get_unprocessed', function() {
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
        'orderby' => 'date',
        'order' => 'DESC',
    );
    
    $unprocessed = get_posts($args);
    $posts_data = [];
    
    foreach ($unprocessed as $post_id) {
        $post = get_post($post_id);
        if ($post) {
            $posts_data[] = [
                'id' => $post_id,
                'title' => $post->post_title,
                'word_count' => str_word_count(wp_strip_all_tags($post->post_content))
            ];
        }
    }
    
    wp_send_json_success($posts_data);
});

add_action('wp_ajax_smart_ai_bulk_start', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    
    // Get unprocessed posts
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
        'orderby' => 'date',
        'order' => 'DESC',
    );
    
    $unprocessed = get_posts($args);
    
    if (empty($unprocessed)) {
        wp_send_json_error('No unprocessed posts found');
    }
    
    update_option('smart_ai_linker_bulk_queue', $unprocessed);
    update_option('smart_ai_linker_bulk_progress', array(
        'total' => count($unprocessed), 
        'processed' => 0,
        'skipped' => 0,
        'errors' => [],
        'status' => [] // Track individual post status
    ));
    
    wp_send_json_success(array('total' => count($unprocessed)));
});

add_action('wp_ajax_smart_ai_bulk_next', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $queue = get_option('smart_ai_linker_bulk_queue', []);
    $progress = get_option('smart_ai_linker_bulk_progress', array('total' => 0, 'processed' => 0, 'skipped' => 0, 'errors' => [], 'status' => []));
    
    if (empty($queue)) {
        wp_send_json_success(array('done' => true, 'progress' => $progress));
    }
    
    $post_id = array_shift($queue);
    
    if ($post_id) {
        $result = smart_ai_linker_process_single_post($post_id);
        
        switch ($result['status']) {
            case 'processed':
                $progress['processed']++;
                $progress['status'][$post_id] = 'processed';
                break;
            case 'skipped':
                $progress['skipped']++;
                $progress['status'][$post_id] = 'skipped';
                break;
            case 'error':
                $progress['errors'][] = "Post ID {$post_id}: " . $result['reason'];
                $progress['skipped']++;
                $progress['status'][$post_id] = 'error';
                break;
        }
        
        update_option('smart_ai_linker_bulk_queue', $queue);
        update_option('smart_ai_linker_bulk_progress', $progress);
        
        wp_send_json_success(array('done' => false, 'progress' => $progress));
    } else {
        wp_send_json_success(array('done' => true, 'progress' => $progress));
    }
});

add_action('wp_ajax_smart_ai_bulk_stop', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    delete_option('smart_ai_linker_bulk_queue');
    delete_option('smart_ai_linker_bulk_progress');
    wp_send_json_success();
});

add_action('wp_ajax_smart_ai_bulk_status', function() {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $queue = get_option('smart_ai_linker_bulk_queue', []);
    $progress = get_option('smart_ai_linker_bulk_progress', array('total' => 0, 'processed' => 0, 'skipped' => 0, 'errors' => [], 'status' => []));
    
    wp_send_json_success(array(
        'running' => !empty($queue),
        'progress' => $progress
    ));
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
