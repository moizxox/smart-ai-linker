<?php

/**
 * Bulk Processing for Smart AI Linker
 * 
 * @package SmartAILinker
 */

if (!defined('ABSPATH')) {
    exit;
}

// Removed legacy Bulk Actions registration for "Generate AI Links" in favor of Bulk Processing Center UI

// Removed legacy Bulk Actions handler – processing is centralized in background queue endpoints

/**
 * Process a single post with comprehensive error handling and retry logic
 * 
 * @param int $post_id The post ID to process
 * @param array $options Optional processing options: ['force' => bool, 'clear_before' => bool]
 * @return array Status array with 'status' and 'reason' keys
 */
function smart_ai_linker_process_single_post($post_id, $options = array())
{
    error_log("[Smart AI] Starting processing for post ID: {$post_id}");

    $post = get_post($post_id);

    // Skip if post doesn't exist or is not published
    if (!$post || $post->post_status !== 'publish') {
        error_log("[Smart AI] Post {$post_id} skipped: not published or doesn't exist");
        return [
            'status' => 'skipped',
            'reason' => 'Post not published or does not exist'
        ];
    }

    // Check if this post is excluded from internal linking
    $excluded_posts = get_option('smart_ai_linker_excluded_posts', array());
    if (in_array($post_id, (array) $excluded_posts)) {
        error_log("[Smart AI] Post {$post_id} skipped: excluded from internal linking");
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
        error_log("[Smart AI] Post {$post_id} skipped: only {$word_count} words (minimum: {$min_words})");
        return [
            'status' => 'skipped',
            'reason' => "Only {$word_count} words (minimum: {$min_words})"
        ];
    }

    // Options
    $clear_before = !empty($options['clear_before']);

    // Clear existing AI links if requested OR if the post was previously processed OR if content already has smart-ai-link anchors
    $previously_processed_meta = get_post_meta($post_id, '_smart_ai_linker_processed', true);
    $already_has_smart_links = false;
    if ($post) {
        $already_has_smart_links = (bool) preg_match('/<a\s+[^>]*class=["\']([^"\']*\bsmart-ai-link\b[^"\']*)["\'][^>]*>/i', $post->post_content);
    }
    if ($clear_before || !empty($previously_processed_meta) || $already_has_smart_links) {
        try {
            $post_to_clear = get_post($post_id);
            if ($post_to_clear) {
                $content = $post_to_clear->post_content;
                // Remove anchors with class smart-ai-link
                $content = preg_replace('/<a\s+[^>]*class=["\']smart-ai-link["\'][^>]*>(.*?)<\/a>/i', '$1', $content);
                // Update content only if changed
                if ($content !== $post_to_clear->post_content) {
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_content' => $content
                    ));
                }
                // Clear meta
                delete_post_meta($post_id, '_smart_ai_linker_processed');
                delete_post_meta($post_id, '_smart_ai_linker_suggestions');
                delete_post_meta($post_id, '_smart_ai_linker_added_links');
                error_log("[Smart AI] Cleared existing AI links for post {$post_id} before reprocessing");
            }
        } catch (Exception $e) {
            error_log('[Smart AI] Error clearing links for post ' . $post_id . ': ' . $e->getMessage());
        }
    }

    // --- Silo Linking Priority for Bulk ---
    $silo_post_ids = [];
    if (class_exists('Smart_AI_Linker_Silos')) {
        try {
            $silo_instance = Smart_AI_Linker_Silos::get_instance();
            $post_silos = $silo_instance->get_post_silos($post_id);
            if (!empty($post_silos)) {
                global $wpdb;
                $silo_ids = array_map(function ($silo) {
                    return is_object($silo) ? $silo->id : $silo['id'];
                }, $post_silos);
                $placeholders = implode(',', array_fill(0, count($silo_ids), '%d'));
                $query = $wpdb->prepare(
                    "SELECT post_id FROM {$silo_instance->silo_relationships} WHERE silo_id IN ($placeholders) AND post_id != %d",
                    array_merge($silo_ids, [$post_id])
                );
                $silo_post_ids = $wpdb->get_col($query);
                error_log("[Smart AI] Found " . count($silo_post_ids) . " silo posts for post {$post_id}");
            }
        } catch (Exception $e) {
            error_log("[Smart AI] Error getting silo posts for post {$post_id}: " . $e->getMessage());
        }
    }
    // --- End Silo Linking Priority for Bulk ---

    // Get AI suggestions with retry logic
    $max_retries = 3;
    $suggestions = null;

    error_log("[Smart AI] Getting AI suggestions for post {$post_id} (attempt 1)");

    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        try {
            // Include necessary files if not already loaded
            if (!function_exists('smart_ai_linker_get_ai_link_suggestions')) {
                require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';
            }

            $suggestions = smart_ai_linker_get_ai_link_suggestions($clean_content, $post_id, $post->post_type, $silo_post_ids);

            if (!empty($suggestions) && is_array($suggestions)) {
                error_log("[Smart AI] Successfully got " . count($suggestions) . " suggestions for post {$post_id}");
                break; // Success, exit retry loop
            } else {
                error_log("[Smart AI] Attempt {$attempt} failed for post {$post_id}: empty or invalid suggestions");
            }

            if ($attempt < $max_retries) {
                error_log("[Smart AI] Attempt {$attempt} failed for post {$post_id}, retrying in 2 seconds...");
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
        error_log("[Smart AI] Failed to get AI suggestions after {$max_retries} attempts for post {$post_id}");
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

    error_log("[Smart AI] Processing " . count($suggestions) . " suggestions for post {$post_id}");

    // Insert the links with error handling
    try {
        // Include necessary files if not already loaded
        if (!function_exists('smart_ai_linker_insert_links_into_post')) {
            require_once SMARTLINK_AI_PATH . 'includes/internal-linking.php';
        }

        error_log("[Smart AI] Inserting links into post {$post_id}");
        $result = smart_ai_linker_insert_links_into_post($post_id, $suggestions);

        if ($result) {
            update_post_meta($post_id, '_smart_ai_linker_processed', current_time('mysql'));
            update_post_meta($post_id, '_smart_ai_linker_added_links', $suggestions);

            error_log("[Smart AI] Successfully processed post {$post_id} with " . count($suggestions) . " links");
            return [
                'status' => 'processed',
                'reason' => 'Successfully processed with ' . count($suggestions) . ' links'
            ];
        } else {
            error_log("[Smart AI] Failed to insert links into post {$post_id}");
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

// Removed admin notice for legacy Bulk Actions flow

/**
 * AJAX handler for processing all unprocessed posts with improved strategy
 */
function smart_ai_linker_process_all_ajax()
{
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

// --- Enhanced Background Bulk Processing with Progress Tracking and Post Type Locking ---

// Add cleanup function for stuck processes
function smart_ai_linker_cleanup_stuck_processes()
{
    $current_processing = get_option('smart_ai_linker_current_processing', []);
    $progress = get_option('smart_ai_linker_bulk_progress', []);

    if (!empty($current_processing) && !empty($progress)) {
        // Check if processing has been stuck for more than 30 minutes
        $last_updated = isset($progress['last_updated']) ? strtotime($progress['last_updated']) : 0;
        $thirty_minutes_ago = time() - (30 * 60);

        if ($last_updated < $thirty_minutes_ago) {
            // Process is stuck, clean it up
            delete_option('smart_ai_linker_current_processing');
            delete_option('smart_ai_linker_bulk_queue');
            delete_option('smart_ai_linker_bulk_progress');

            error_log('[Smart AI Linker] Cleaned up stuck bulk processing process');
        }
    }
}

// Run cleanup on admin init
add_action('admin_init', 'smart_ai_linker_cleanup_stuck_processes');

add_action('wp_ajax_smart_ai_bulk_get_unprocessed', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';

    // Check if another post type is currently being processed
    $current_processing = get_option('smart_ai_linker_current_processing', []);
    if (!empty($current_processing) && $current_processing['post_type'] !== $post_type) {
        wp_send_json_error('Another post type (' . $current_processing['post_type'] . ') is currently being processed. Please wait for it to complete.');
    }

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

// New: Get posts with status (all/processed/unprocessed)
add_action('wp_ajax_smart_ai_bulk_get_posts', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all'; // all|processed|unprocessed
    $per_page = isset($_POST['per_page']) ? max(1, min(500, intval($_POST['per_page']))) : 200;
    $paged = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;

    $args = array(
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $per_page,
        'paged' => $paged,
        'fields' => 'ids',
        'orderby' => 'date',
        'order' => 'DESC',
    );

    if ($filter === 'unprocessed') {
        $args['meta_query'] = array(
            'relation' => 'OR',
            array('key' => '_smart_ai_linker_processed', 'compare' => 'NOT EXISTS'),
            array('key' => '_smart_ai_linker_processed', 'value' => '', 'compare' => '=')
        );
    } elseif ($filter === 'processed') {
        $args['meta_query'] = array(
            array('key' => '_smart_ai_linker_processed', 'compare' => 'EXISTS')
        );
    }

    $posts = get_posts($args);

    $rows = array();
    foreach ($posts as $post_id) {
        $post = get_post($post_id);
        if (!$post) {
            continue;
        }
        $processed_meta = get_post_meta($post_id, '_smart_ai_linker_processed', true);
        $added_links = get_post_meta($post_id, '_smart_ai_linker_added_links', true);
        $status = (!empty($processed_meta) && !empty($added_links)) ? 'processed' : 'unprocessed';
        $rows[] = array(
            'id' => $post_id,
            'title' => $post->post_title,
            'word_count' => str_word_count(wp_strip_all_tags($post->post_content)),
            'status' => $status,
            'last_processed' => $processed_meta,
            'link_count' => is_array($added_links) ? count($added_links) : 0,
        );
    }

    wp_send_json_success(array(
        'items' => $rows,
        'page' => $paged,
        'per_page' => $per_page
    ));
});

add_action('wp_ajax_smart_ai_bulk_start', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';

    // Check if another post type is currently being processed
    $current_processing = get_option('smart_ai_linker_current_processing', []);
    if (!empty($current_processing) && $current_processing['post_type'] !== $post_type) {
        wp_send_json_error('Another post type (' . $current_processing['post_type'] . ') is currently being processed. Please wait for it to complete.');
    }

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

    // Set up the processing state
    update_option('smart_ai_linker_bulk_queue', $unprocessed);
    update_option('smart_ai_linker_bulk_progress', array(
        'total' => count($unprocessed),
        'processed' => 0,
        'skipped' => 0,
        'errors' => [],
        'status' => [],
        'post_type' => $post_type,
        'mode' => 'all',
        'selected_ids' => [],
        'started_at' => current_time('mysql'),
        'last_updated' => current_time('mysql')
    ));
    // Default processing options for standard start
    update_option('smart_ai_linker_bulk_options', array(
        'force' => false,
        'clear_before' => false
    ));

    // Lock the post type for processing
    update_option('smart_ai_linker_current_processing', [
        'post_type' => $post_type,
        'started_at' => current_time('mysql'),
        'total_posts' => count($unprocessed)
    ]);

    wp_send_json_success(array('total' => count($unprocessed)));
});

add_action('wp_ajax_smart_ai_bulk_next', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $queue = get_option('smart_ai_linker_bulk_queue', []);
    $progress = get_option('smart_ai_linker_bulk_progress', array('total' => 0, 'processed' => 0, 'skipped' => 0, 'errors' => [], 'status' => []));
    $processing_options = get_option('smart_ai_linker_bulk_options', array('force' => false, 'clear_before' => false));

    if (empty($queue)) {
        // Processing is complete
        delete_option('smart_ai_linker_current_processing');
        wp_send_json_success(array('done' => true, 'progress' => $progress));
    }

    $post_id = array_shift($queue);

    if ($post_id) {
        // Process the post and get result with current options
        $result = smart_ai_linker_process_single_post($post_id, $processing_options);

        // Update progress based on result
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

        // Update timestamps
        $progress['last_updated'] = current_time('mysql');

        // Update the queue and progress in database
        update_option('smart_ai_linker_bulk_queue', $queue);
        update_option('smart_ai_linker_bulk_progress', $progress);

        // Get current post info for display
        $post = get_post($post_id);
        $current_post_info = $post ? $post->post_title : "Post ID: {$post_id}";

        // Verify the actual processing status from database
        $actual_status = get_post_meta($post_id, '_smart_ai_linker_processed', true);
        $actual_links = get_post_meta($post_id, '_smart_ai_linker_added_links', true);

        // Double-check the status if there's a discrepancy
        if ($result['status'] === 'processed' && empty($actual_status)) {
            // If we think it was processed but no meta exists, mark as error
            $progress['status'][$post_id] = 'error';
            $progress['errors'][] = "Post ID {$post_id}: Processing verification failed";
            error_log("[Smart AI] Post {$post_id} verification failed - processed but no meta found");
        } elseif ($result['status'] === 'error' && !empty($actual_status)) {
            // If we think it failed but meta exists, mark as processed
            $progress['status'][$post_id] = 'processed';
            $progress['processed']++;
            $progress['skipped']--; // Adjust the count
            // Remove the error from the list
            $progress['errors'] = array_filter($progress['errors'], function ($error) use ($post_id) {
                return strpos($error, "Post ID {$post_id}:") === false;
            });
            error_log("[Smart AI] Post {$post_id} verification corrected - error but meta exists, marking as processed");
        } elseif ($result['status'] === 'processed' && !empty($actual_status)) {
            // Successfully processed, ensure it's marked as processed
            $progress['status'][$post_id] = 'processed';
            error_log("[Smart AI] Post {$post_id} verified as successfully processed with " . count($actual_links) . " links");
        }

        // Update the progress again with verified status
        update_option('smart_ai_linker_bulk_progress', $progress);

        // Record last processed id for UI sync on reload
        if (isset($progress['status'][$post_id]) && $progress['status'][$post_id] === 'processed') {
            update_option('smart_ai_linker_last_processed_id', (int) $post_id);
        }

        // Thin progress payload to reduce UI glitches due to heavy responses
        $response_progress = array(
            'total' => isset($progress['total']) ? (int) $progress['total'] : 0,
            'processed' => isset($progress['processed']) ? (int) $progress['processed'] : 0,
            'skipped' => isset($progress['skipped']) ? (int) $progress['skipped'] : 0,
            'errors_count' => isset($progress['errors']) && is_array($progress['errors']) ? count($progress['errors']) : 0,
            'started_at' => isset($progress['started_at']) ? $progress['started_at'] : null,
            'last_updated' => isset($progress['last_updated']) ? $progress['last_updated'] : null
        );

        wp_send_json_success(array(
            'done' => false,
            'progress' => $response_progress,
            'current_post' => $current_post_info,
            'current_post_id' => $post_id,
            'verified_status' => isset($progress['status'][$post_id]) ? $progress['status'][$post_id] : null,
            'actual_meta' => !empty($actual_status),
            'queue_remaining' => count($queue),
            'last_processed_id' => (int) get_option('smart_ai_linker_last_processed_id', 0)
        ));
    } else {
        wp_send_json_success(array('done' => true, 'progress' => $progress));
    }
});

add_action('wp_ajax_smart_ai_bulk_stop', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }
    delete_option('smart_ai_linker_bulk_queue');
    delete_option('smart_ai_linker_bulk_progress');
    delete_option('smart_ai_linker_current_processing');
    delete_option('smart_ai_linker_bulk_options');
    wp_send_json_success();
});

add_action('wp_ajax_smart_ai_bulk_status', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $queue = get_option('smart_ai_linker_bulk_queue', []);
    $progress = get_option('smart_ai_linker_bulk_progress', array('total' => 0, 'processed' => 0, 'skipped' => 0, 'errors' => [], 'status' => []));
    $current_processing = get_option('smart_ai_linker_current_processing', []);

    // Only consider it running if there's actually a queue with items
    $is_running = !empty($queue) && count($queue) > 0;

    // If we have progress but no queue, check if processing is actually complete
    if (!empty($progress) && empty($queue) && $progress['total'] > 0) {
        $total_completed = $progress['processed'] + $progress['skipped'];
        if ($total_completed >= $progress['total']) {
            // Processing is actually complete, clean up
            delete_option('smart_ai_linker_current_processing');
            $is_running = false;
        }
    }

    // Verify status accuracy by checking actual post meta, without defaulting unknowns to skipped
    if (!empty($progress['status'])) {
        $verified_status = [];
        $verified_counts = ['processed' => 0, 'skipped' => 0, 'error' => 0];

        foreach ($progress['status'] as $post_id => $reported_status) {
            $actual_status = get_post_meta($post_id, '_smart_ai_linker_processed', true);
            $actual_links = get_post_meta($post_id, '_smart_ai_linker_added_links', true);

            if (!empty($actual_status) && !empty($actual_links)) {
                // Definitely processed
                $verified_status[$post_id] = 'processed';
                $verified_counts['processed']++;
                error_log("[Smart AI] Post {$post_id} verified as processed with " . count($actual_links) . " links");
            } elseif ($reported_status === 'skipped') {
                // Respect explicit skipped status
                $verified_status[$post_id] = 'skipped';
                $verified_counts['skipped']++;
            } elseif ($reported_status === 'error') {
                // Keep error unless proven processed
                $verified_status[$post_id] = 'error';
            } elseif ($reported_status === 'processed') {
                // Reported processed but no meta: treat as error, do not mislabel as skipped
                $verified_status[$post_id] = 'error';
                $verified_counts['error']++;
                error_log("[Smart AI] Post {$post_id} verification failed - reported as processed but no meta found");
            } else {
                // Unknown/pending stays queued
                $verified_status[$post_id] = 'queued';
            }
        }

        // Update progress with verified status
        $progress['status'] = $verified_status;
        $progress['processed'] = $verified_counts['processed'];
        $progress['skipped'] = $verified_counts['skipped'];

        // Update the stored progress
        update_option('smart_ai_linker_bulk_progress', $progress);
    }

    // Thin progress payload
    $response_progress = array(
        'total' => isset($progress['total']) ? (int) $progress['total'] : 0,
        'processed' => isset($progress['processed']) ? (int) $progress['processed'] : 0,
        'skipped' => isset($progress['skipped']) ? (int) $progress['skipped'] : 0,
        'errors_count' => isset($progress['errors']) && is_array($progress['errors']) ? count($progress['errors']) : 0,
        'started_at' => isset($progress['started_at']) ? $progress['started_at'] : null,
        'last_updated' => isset($progress['last_updated']) ? $progress['last_updated'] : null,
        'mode' => isset($progress['mode']) ? $progress['mode'] : null,
        'selected_ids' => isset($progress['selected_ids']) && is_array($progress['selected_ids']) ? array_map('intval', $progress['selected_ids']) : []
    );

    // Provide a compact list of processed IDs for UI resync after reload
    $processed_ids = array();
    if (!empty($progress['status']) && is_array($progress['status'])) {
        foreach ($progress['status'] as $pid => $st) {
            if ($st === 'processed') {
                $processed_ids[] = (int) $pid;
            }
        }
        // Limit to most recent 100 to keep payload small
        if (count($processed_ids) > 100) {
            $processed_ids = array_slice($processed_ids, -100);
        }
    }

    wp_send_json_success(array(
        'running' => $is_running,
        'progress' => $response_progress,
        'current_processing' => $current_processing,
        'queue_count' => count($queue),
        'processed_ids' => $processed_ids,
        'last_processed_id' => (int) get_option('smart_ai_linker_last_processed_id', 0)
    ));
});

// New AJAX handler to get processing status for post type locking
add_action('wp_ajax_smart_ai_bulk_get_processing_status', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $current_processing = get_option('smart_ai_linker_current_processing', []);
    $progress = get_option('smart_ai_linker_bulk_progress', array('total' => 0, 'processed' => 0, 'skipped' => 0, 'errors' => [], 'status' => []));
    $queue = get_option('smart_ai_linker_bulk_queue', []);

    // Check if processing is stuck (has progress but no queue)
    $is_stuck = false;
    if (!empty($current_processing) && !empty($progress) && empty($queue) && $progress['total'] > 0) {
        $total_completed = $progress['processed'] + $progress['skipped'];
        if ($total_completed < $progress['total']) {
            $is_stuck = true;
        }
    }

    // Verify statuses but respond with thin payload
    if (!empty($progress['status'])) {
        $verified_status = [];
        $verified_counts = ['processed' => 0, 'skipped' => 0, 'error' => 0];

        foreach ($progress['status'] as $post_id => $reported_status) {
            $actual_status = get_post_meta($post_id, '_smart_ai_linker_processed', true);
            $actual_links = get_post_meta($post_id, '_smart_ai_linker_added_links', true);

            if (!empty($actual_status) && !empty($actual_links)) {
                $verified_status[$post_id] = 'processed';
                $verified_counts['processed']++;
            } elseif ($reported_status === 'skipped') {
                $verified_status[$post_id] = 'skipped';
                $verified_counts['skipped']++;
            } elseif ($reported_status === 'error') {
                $verified_status[$post_id] = 'error';
            } elseif ($reported_status === 'processed') {
                $verified_status[$post_id] = 'error';
            } else {
                $verified_status[$post_id] = 'queued';
            }
        }

        $progress['status'] = $verified_status;
        $progress['processed'] = $verified_counts['processed'];
        $progress['skipped'] = $verified_counts['skipped'];
        update_option('smart_ai_linker_bulk_progress', $progress);
    }

    $response_progress = array(
        'total' => isset($progress['total']) ? (int) $progress['total'] : 0,
        'processed' => isset($progress['processed']) ? (int) $progress['processed'] : 0,
        'skipped' => isset($progress['skipped']) ? (int) $progress['skipped'] : 0,
        'errors_count' => isset($progress['errors']) && is_array($progress['errors']) ? count($progress['errors']) : 0,
        'started_at' => isset($progress['started_at']) ? $progress['started_at'] : null,
        'last_updated' => isset($progress['last_updated']) ? $progress['last_updated'] : null,
        'mode' => isset($progress['mode']) ? $progress['mode'] : null,
        'selected_ids' => isset($progress['selected_ids']) && is_array($progress['selected_ids']) ? array_map('intval', $progress['selected_ids']) : []
    );

    // Provide a compact list of processed IDs for UI resync
    $processed_ids = array();
    if (!empty($progress['status']) && is_array($progress['status'])) {
        foreach ($progress['status'] as $pid => $st) {
            if ($st === 'processed') {
                $processed_ids[] = (int) $pid;
            }
        }
        if (count($processed_ids) > 100) {
            $processed_ids = array_slice($processed_ids, -100);
        }
    }

    wp_send_json_success(array(
        'is_processing' => !empty($current_processing),
        'current_processing' => $current_processing,
        'progress' => $response_progress,
        'is_stuck' => $is_stuck,
        'queue_count' => count($queue),
        'processed_ids' => $processed_ids
    ));
});

// New AJAX handler to force resume processing
add_action('wp_ajax_smart_ai_bulk_force_resume', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $progress = get_option('smart_ai_linker_bulk_progress', []);
    $current_processing = get_option('smart_ai_linker_current_processing', []);

    if (empty($progress) || empty($current_processing)) {
        wp_send_json_error('No processing to resume');
    }

    // Rebuild the queue from unprocessed posts
    $post_type = $current_processing['post_type'];
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
        // All posts are processed, clean up
        delete_option('smart_ai_linker_current_processing');
        delete_option('smart_ai_linker_bulk_queue');
        delete_option('smart_ai_linker_bulk_progress');
        wp_send_json_success(array('done' => true, 'message' => 'All posts already processed'));
    }

    // Update the queue
    update_option('smart_ai_linker_bulk_queue', $unprocessed);

    // Update progress total if needed
    if ($progress['total'] !== count($unprocessed)) {
        $progress['total'] = count($unprocessed);
        update_option('smart_ai_linker_bulk_progress', $progress);
    }

    wp_send_json_success(array(
        'resumed' => true,
        'queue_count' => count($unprocessed),
        'progress' => $progress
    ));
});

// New AJAX handler to get link count for a post
add_action('wp_ajax_smart_ai_bulk_get_link_count', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

    if (!$post_id) {
        wp_send_json_error('Invalid post ID');
    }

    // Get the actual links from post meta
    $added_links = get_post_meta($post_id, '_smart_ai_linker_added_links', true);
    $link_count = 0;

    if (!empty($added_links) && is_array($added_links)) {
        $link_count = count($added_links);
    }

    // Also count actual links in the content
    $post = get_post($post_id);
    $actual_links_in_content = 0;
    if ($post && !empty($post->post_content)) {
        // Count smart-ai-link class links
        preg_match_all('/<a[^>]*class="[^"]*smart-ai-link[^"]*"[^>]*>/i', $post->post_content, $matches);
        $actual_links_in_content = count($matches[0]);
    }

    wp_send_json_success(array(
        'link_count' => $link_count,
        'actual_links_in_content' => $actual_links_in_content,
        'post_id' => $post_id
    ));
});

// --- End Enhanced Background Bulk Processing ---

// New: Queue selected posts (supports reprocessing options)
add_action('wp_ajax_smart_ai_bulk_queue_selected', function () {
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : 'post';
    $post_ids = isset($_POST['post_ids']) ? (array) $_POST['post_ids'] : array();
    $mode = isset($_POST['mode']) ? sanitize_text_field($_POST['mode']) : 'process'; // process|reprocess
    $force = !empty($_POST['force']);
    $clear_before = !empty($_POST['clear_before']);

    // Validate IDs
    $post_ids = array_values(array_filter(array_map('intval', $post_ids), function ($id) {
        return $id > 0;
    }));

    if (empty($post_ids)) {
        wp_send_json_error('No posts selected');
    }

    // Check lock
    $current_processing = get_option('smart_ai_linker_current_processing', []);
    if (!empty($current_processing) && $current_processing['post_type'] !== $post_type) {
        wp_send_json_error('Another post type (' . $current_processing['post_type'] . ') is currently being processed. Please wait for it to complete.');
    }

    // Set queue exactly to the provided IDs (order preserved)
    update_option('smart_ai_linker_bulk_queue', $post_ids);
    update_option('smart_ai_linker_bulk_progress', array(
        'total' => count($post_ids),
        'processed' => 0,
        'skipped' => 0,
        'errors' => [],
        'status' => [],
        'post_type' => $post_type,
        'mode' => $mode,
        'selected_ids' => $post_ids,
        'started_at' => current_time('mysql'),
        'last_updated' => current_time('mysql')
    ));
    update_option('smart_ai_linker_bulk_options', array(
        'force' => (bool) $force,
        'clear_before' => (bool) $clear_before
    ));

    // Lock the post type
    update_option('smart_ai_linker_current_processing', [
        'post_type' => $post_type,
        'started_at' => current_time('mysql'),
        'total_posts' => count($post_ids)
    ]);

    wp_send_json_success(array('total' => count($post_ids)));
});

// Ensure admin.js is enqueued on edit.php (posts/pages list)
add_action('admin_enqueue_scripts', function ($hook) {
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
