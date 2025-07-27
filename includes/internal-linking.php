<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Internal Linking Module (Smart AI Linker)
 * 
 * Handles automatic internal linking when posts are published or updated.
 */

// Hook into post saving
add_action('save_post', 'smart_ai_linker_generate_internal_links', 20, 3);

/**
 * Generate and insert internal links when a post is saved or updated
 * 
 * @param int     $post_ID The post ID
 * @param WP_Post $post    The post object
 * @param bool    $update  Whether this is an existing post being updated
 * @return int|WP_Error The post ID on success, WP_Error on failure
 * 
 * @var array<array{anchor: string, url: string}> $suggestions Array of link suggestions
 */
function smart_ai_linker_generate_internal_links($post_ID, $post = null, $update = null) {
    // Ensure we have a valid post object
    if (is_null($post)) {
        $post = get_post($post_ID);
        if (!$post) {
            return new WP_Error('invalid_post', 'Invalid post ID');
        }
    }
    error_log('[Smart AI] Starting internal link generation for post ' . $post_ID);
    
    // Skip if auto-linking is disabled or this is an autosave
    if (!get_option('smart_ai_linker_enable_auto_linking', true)) {
        error_log('[Smart AI] Auto-linking is disabled in settings');
        return $post_ID;
    }
    
    // Skip autosaves and revisions
    if (wp_is_post_autosave($post_ID) || wp_is_post_revision($post_ID)) {
        error_log('[Smart AI] Skipping autosave/revision');
        return $post_ID;
    }
    
    // Skip if this is not a published post or page
    if (!in_array($post->post_status, ['publish', 'future', 'draft', 'pending', 'private'], true)) {
        error_log('[Smart AI] Skipping post with status: ' . $post->post_status);
        return $post_ID;
    }

    // Run for published posts and pages, or when status is 'future' (scheduled)
    if (!in_array($post->post_status, ['publish', 'future'])) {
        error_log('[Smart AI] Skipping - post status is ' . $post->post_status);
        return $post_ID;
    }

    // Get enabled post types for linking (default to both posts and pages)
    $enabled_post_types = get_option('smart_ai_linker_post_types', array('post', 'page'));
    if (!in_array($post->post_type, (array) $enabled_post_types)) {
        error_log('[Smart AI] Post type is not enabled for linking');
        return $post_ID;
    }
    error_log('[Smart AI] Post type is enabled for linking');

    // Get the post content and clean it up for processing
    $content = $post->post_content;
    
    // First, decode HTML entities and clean up content
    if (function_exists('wp_strip_all_tags')) {
        $content = wp_strip_all_tags($content, true);
    } else {
        $content = strip_tags($content);
    }
    
    // Normalize whitespace - replace multiple spaces/tabs with single space
    $content = preg_replace('/\s+/', ' ', $content);
    
    // Remove any existing smart-ai-link spans to prevent duplicates
    $content = preg_replace('/<a\s+[^>]*class=["\']smart-ai-link["\'][^>]*>.*?<\/a>/i', '', $content);
    
    // Clean up any leftover HTML tags or attributes that might cause issues
    $content = wp_kses_post($content);
    
    if (empty($content)) {
        error_log('[Smart AI] Empty post content, skipping');
        return $post_ID;
    }
    
    // Ensure content is a string before processing
    if (!is_string($content)) {
        error_log('[Smart AI] Content is not a string, converting...');
        $content = (string) $content;
    }
    
    
    // Strip shortcodes and tags for the AI analysis
    $clean_content = wp_strip_all_tags(strip_shortcodes($content));
    
    if (empty($clean_content)) {
        error_log('[Smart AI] No content available for analysis after cleaning');
        return $post_ID;
    }

    // Get AI suggestions for internal links
    error_log('[Smart AI] Getting AI suggestions for post ' . $post_ID);
    
    // Ensure we're passing a string to the function
    $content_for_analysis = is_string($clean_content) ? $clean_content : '';
    
    // Get the post type for context
    $post_type = $post->post_type;
    if (empty($content_for_analysis)) {
        error_log('[Smart AI] No valid content available for analysis');
        return $post_ID;
    }

    // --- Silo Linking Priority ---
    // Try to get silo group post IDs for this post
    $silo_post_ids = [];
    if (class_exists('Smart_AI_Linker_Silos')) {
        $silo_instance = Smart_AI_Linker_Silos::get_instance();
        $post_silos = $silo_instance->get_post_silos($post_ID);
        if (!empty($post_silos)) {
            // Get all posts in these silos (excluding current post)
            global $wpdb;
            $silo_ids = array_map(function($silo){ return is_object($silo) ? $silo->id : $silo['id']; }, $post_silos);
            $placeholders = implode(',', array_fill(0, count($silo_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT post_id FROM {$silo_instance->silo_relationships} WHERE silo_id IN ($placeholders) AND post_id != %d",
                array_merge($silo_ids, [$post_ID])
            );
            $silo_post_ids = $wpdb->get_col($query);
        }
    }
    // --- End Silo Linking Priority ---

    // Pass silo_post_ids to the AI suggestion function
    $suggestions = smart_ai_linker_get_ai_link_suggestions($content_for_analysis, $post_ID, $post_type, $silo_post_ids);
    
    if (is_wp_error($suggestions)) {
        error_log('[Smart AI] Error getting AI suggestions: ' . $suggestions->get_error_message());
        return $post_ID; // Still return post ID to avoid breaking the save process
    }
    
    // Ensure suggestions is an array
    if (!is_array($suggestions)) {
        error_log('[Smart AI] Invalid suggestions format, expected array');
        return $post_ID;
    }


    if (empty($suggestions)) {
        error_log('[Smart AI] No valid link suggestions received');
        return $post_ID;
    }
    
    error_log('[Smart AI] Received ' . count($suggestions) . ' link suggestions');
    
    // Ensure we have the maximum number of links based on settings
    // Respect admin-defined limit (1-7) but never exceed 7
    $option_max = (int) get_option('smart_ai_linker_max_links', 7);
    $option_max = $option_max > 0 ? min(7, $option_max) : 7;
    $max_links = $option_max;
    $suggestions = array_slice($suggestions, 0, $max_links);
    
    // Log the number of links we're trying to insert
    error_log('[Smart AI] Will attempt to insert up to ' . count($suggestions) . ' links into the post');

    // Insert the links into the post content
    error_log('[Smart AI] Inserting ' . count($suggestions) . ' links into post ' . $post_ID);
    
    // Ensure we have a valid array of suggestions
    if (!is_array($suggestions)) {
        $error_msg = 'Invalid suggestions format, expected array';
        error_log('[Smart AI] ' . $error_msg);
        return new WP_Error('invalid_suggestions', $error_msg);
    }
    
    // Convert suggestions to the expected format with 'anchor' and 'url' keys
    $formatted_suggestions = [];
    foreach ($suggestions as $suggestion) {
        if (is_array($suggestion) && isset($suggestion['anchor_text']) && isset($suggestion['url'])) {
            $formatted_suggestions[] = (object) [
                'anchor' => $suggestion['anchor_text'],
                'url' => $suggestion['url']
            ];
        } elseif (is_object($suggestion) && isset($suggestion->anchor_text) && isset($suggestion->url)) {
            $formatted_suggestions[] = (object) [
                'anchor' => $suggestion->anchor_text,
                'url' => $suggestion->url
            ];
        } elseif (is_array($suggestion) && isset($suggestion['anchor']) && isset($suggestion['url'])) {
            $formatted_suggestions[] = (object) [
                'anchor' => $suggestion['anchor'],
                'url' => $suggestion['url']
            ];
        } elseif (is_object($suggestion) && isset($suggestion->anchor) && isset($suggestion->url)) {
            $formatted_suggestions[] = (object) [
                'anchor' => $suggestion->anchor,
                'url' => $suggestion->url
            ];
        } else {
            error_log('[Smart AI] Invalid suggestion format: ' . print_r($suggestion, true));
        }
    }
    
    if (empty($formatted_suggestions)) {
        error_log('[Smart AI] No valid link suggestions found');
        return $post_ID;
    }
    
    error_log('[Smart AI] Processed ' . count($formatted_suggestions) . ' valid link suggestions');
    
    // Ensure we're passing an array of objects to the function
    $result = smart_ai_linker_insert_links_into_post($post_ID, $formatted_suggestions);
    
    // Log the result of the link insertion
    if (is_wp_error($result)) {
        error_log('[Smart AI] Error inserting links: ' . $result->get_error_message());
    } elseif ($result === false) {
        error_log('[Smart AI] Failed to insert links into post ' . $post_ID);
    } else {
        error_log('[Smart AI] Successfully inserted links into post ' . $post_ID);
    }
    
    if (is_wp_error($result)) {
        error_log('[Smart AI] Error inserting links: ' . $result->get_error_message());
        return $post_ID;
    }
    
    // Mark post as processed and store the links
    $result1 = update_post_meta($post_ID, '_smart_ai_linker_processed', current_time('mysql'));
    $result2 = update_post_meta($post_ID, '_smart_ai_linker_added_links', $suggestions);
    
    if ($result1 === false || $result2 === false) {
        $error_message = 'Failed to update post meta for post ' . $post_ID;
        error_log('[Smart AI] ' . $error_message);
        // Still return post ID to avoid breaking the save process
    } else {
        error_log('[Smart AI] Successfully processed post ' . $post_ID . ' with ' . count($suggestions) . ' links');
    }
    
    // Run duplicate-link cleanup unconditionally
    smart_ai_linker_deduplicate_links($post_ID);
    // Always return the post ID to maintain WordPress filter compatibility
    return $post_ID;
}

/**
 * Insert links into the post content at appropriate positions using DOMDocument for safe HTML manipulation
 * 
 * @param int $post_ID The post ID
 * @param array<array|object> $links Array of link suggestions, where each item is either an array with 'anchor' and 'url' keys
 *                                   or an object with 'anchor' and 'url' properties
 * @return bool|WP_Error True on success, false or WP_Error on failure
 */
function smart_ai_linker_insert_links_into_post($post_ID, $links = []) {
    if (empty($links) || !is_array($links)) {
        $error_msg = 'No valid links provided for insertion';
        error_log('[Smart AI] ' . $error_msg);
        return new WP_Error('no_links', $error_msg);
    }

    $post = get_post($post_ID);
    $source_type = $post ? $post->post_type : 'post';
    if (!$post) {
        return false;
    }

    $content = $post->post_content;
    if (empty($content)) {
        return false;
    }

    // Split content into paragraphs for better distribution
    $paragraphs = preg_split('/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
    $paragraphs = array_map('trim', $paragraphs);
    $paragraphs = array_values(array_filter($paragraphs, function($p) { return $p !== ''; }));

    $used_anchors = [];
    $used_urls = [];
    $links_added = 0;
    $option_max = (int) get_option('smart_ai_linker_max_links', 7);
    $option_max = $option_max > 0 ? min(7, $option_max) : 7;
    $max_links = $option_max;

    // Distribute links: intro, middle, end
    $num_paragraphs = count($paragraphs);
    $target_indices = [];
    if ($num_paragraphs >= 3) {
        $target_indices = [0, intval($num_paragraphs/2), $num_paragraphs-1];
    } else {
        $target_indices = range(0, $num_paragraphs-1);
    }
    $link_idx = 0;
    foreach ($links as $link) {
        if ($links_added >= $max_links) break;
        $anchor = is_array($link) ? ($link['anchor'] ?? '') : ($link->anchor ?? '');
        $url = is_array($link) ? ($link['url'] ?? '') : ($link->url ?? '');
        if (empty($anchor) || empty($url)) continue;
        // If the source is a PAGE, do not link to posts
        if ($source_type === 'page') {
            $target_post_id = url_to_postid($url);
            if ($target_post_id && get_post_type($target_post_id) !== 'page') {
                continue; // skip – violates page→post rule
            }
        }
        if (in_array($anchor, $used_anchors, true) || in_array($url, $used_urls, true)) continue;
        // Try to insert in target paragraphs first
        $inserted = false;
        foreach ($target_indices as $idx) {
            if (stripos($paragraphs[$idx], $anchor) !== false && stripos($paragraphs[$idx], '<a ') === false) {
                $paragraphs[$idx] = preg_replace('/' . preg_quote($anchor, '/') . '/i', '<a href="' . esc_url($url) . '" class="smart-ai-link" title="' . esc_attr($anchor) . '">' . esc_html($anchor) . '</a>', $paragraphs[$idx], 1);
                $inserted = true;
                break;
            }
        }
        // If not inserted, try all paragraphs
        if (!$inserted) {
            foreach ($paragraphs as $idx => $para) {
                if (stripos($para, $anchor) !== false && stripos($para, '<a ') === false) {
                    $paragraphs[$idx] = preg_replace('/' . preg_quote($anchor, '/') . '/i', '<a href="' . esc_url($url) . '" class="smart-ai-link" title="' . esc_attr($anchor) . '">' . esc_html($anchor) . '</a>', $para, 1);
                    $inserted = true;
                    break;
                }
            }
        }
        if ($inserted) {
            $used_anchors[] = $anchor;
            $used_urls[] = $url;
            $links_added++;
        }
    }
    $new_content = implode('', $paragraphs);
    if ($new_content && $new_content !== $content) {
        // Deduplicate links: keep only the first occurrence of each URL
        $seen_urls = [];
        $new_content = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', function($matches) use (&$seen_urls) {
            $url = $matches[1];
            $anchor = $matches[2];
            if (!in_array($url, $seen_urls)) {
                $seen_urls[] = $url;
                return $matches[0]; // keep the first occurrence
            } else {
                return $anchor; // remove link, keep anchor text
            }
        }, $new_content);
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->posts,
            array('post_content' => $new_content),
            array('ID' => $post_ID),
            array('%s'),
            array('%d')
        );
        clean_post_cache($post_ID);
        return $result !== false;
    }
    return false;
}


/**
 * Deduplicate repeated links that point to the same URL (keep only first occurrence).
 * Runs independently so even posts with no new links still get cleaned.
 *
 * @param int $post_ID
 */
function smart_ai_linker_deduplicate_links($post_ID){
    $post = get_post($post_ID);
    if(!$post){
        return;
    }
    $content = $post->post_content;
    if(empty($content)){
        return;
    }
    $original = $content;
    $seen_urls = [];
    $content = preg_replace_callback('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/is',function($matches) use (&$seen_urls){
        $url = $matches[1];
        $anchor = $matches[2];
        if(!in_array($url,$seen_urls,true)){
            $seen_urls[] = $url;
            return $matches[0];
        }
        return $anchor; // strip duplicate link keep anchor
    },$content);
    if($content!==$original){
        remove_action('save_post','smart_ai_linker_generate_internal_links',20);
        wp_update_post(['ID'=>$post_ID,'post_content'=>$content]);
        add_action('save_post','smart_ai_linker_generate_internal_links',20,3);
    }
}

// --- 301 Redirects for Changed Permalinks ---
// This handles automatic redirects when post slugs are changed to prevent broken links
add_action('post_updated', function($post_ID, $post_after, $post_before) {
    // Only handle posts and pages
    if (!in_array($post_after->post_type, ['post', 'page'])) {
        return;
    }
    
    $old_slug = $post_before->post_name;
    $new_slug = $post_after->post_name;
    
    // If slug changed, create a redirect from old URL to new URL
    if ($old_slug && $new_slug && $old_slug !== $new_slug) {
        $old_url = str_replace($new_slug, $old_slug, get_permalink($post_after));
        $new_url = get_permalink($post_after);
        
        if ($old_url && $new_url && $old_url !== $new_url) {
            $redirects = get_option('smart_ai_linker_redirects', []);
            $old_path = parse_url($old_url, PHP_URL_PATH);
            $redirects[$old_path] = $new_url;
            update_option('smart_ai_linker_redirects', $redirects);
            
            error_log('[Smart AI] Created redirect from ' . $old_url . ' to ' . $new_url);
        }
    }
}, 10, 3);

// Handle the actual redirects
add_action('template_redirect', function() {
    if (is_404()) {
        $request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $redirects = get_option('smart_ai_linker_redirects', []);
        
        if (isset($redirects[$request_path])) {
            wp_redirect($redirects[$request_path], 301);
            exit;
        }
    }
});
// --- End 301 Redirects ---
