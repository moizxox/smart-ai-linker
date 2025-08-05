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

    // Check if this post is excluded from internal linking
    $excluded_posts = get_option('smart_ai_linker_excluded_posts', array());
    if (in_array($post_ID, (array) $excluded_posts)) {
        error_log('[Smart AI] Post ' . $post_ID . ' is excluded from internal linking');
        return $post_ID;
    }

    // Get the post content
    $content = $post->post_content;
    
    if (empty($content)) {
        error_log('[Smart AI] Empty post content, skipping');
        return $post_ID;
    }
    
    // Ensure content is a string before processing
    if (!is_string($content)) {
        error_log('[Smart AI] Content is not a string, converting...');
        $content = (string) $content;
    }
    
    // Strip shortcodes and tags for the AI analysis only
    $clean_content = wp_strip_all_tags(strip_shortcodes($content));
    
    if (empty($clean_content)) {
        error_log('[Smart AI] No content available for analysis after cleaning');
        return $post_ID;
    }

    // Check word count
    $word_count = str_word_count($clean_content);
    $min_word_count = get_option('smart_ai_min_content_length', 10);
    if ($word_count < $min_word_count) {
        error_log('[Smart AI] Post ' . $post_ID . ' has only ' . $word_count . ' words (minimum: ' . $min_word_count . '), skipping');
        return $post_ID;
    }
    
    error_log('[Smart AI] Post ' . $post_ID . ' has ' . $word_count . ' words, proceeding with analysis');

    // Get AI suggestions for internal links
    error_log('[Smart AI] Getting AI suggestions for post ' . $post_ID);
    
    // Ensure we're passing a string to the function
    $content_for_analysis = is_string($clean_content) ? $clean_content : '';
    
    // Get the post type for context
    $post_type = $post->post_type;
    
    // Check if AI function exists
    if (!function_exists('smart_ai_linker_get_ai_link_suggestions')) {
        error_log('[Smart AI] AI function not found, including API client');
        require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';
    }
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
    error_log('[Smart AI] Calling AI function with content length: ' . strlen($content_for_analysis));
    $suggestions = smart_ai_linker_get_ai_link_suggestions($content_for_analysis, $post_ID, $post_type, $silo_post_ids);
    
    if (is_wp_error($suggestions)) {
        error_log('[Smart AI] Error getting AI suggestions: ' . $suggestions->get_error_message());
        return $post_ID; // Still return post ID to avoid breaking the save process
    }
    
    // Ensure suggestions is an array
    if (!is_array($suggestions)) {
        error_log('[Smart AI] Invalid suggestions format, expected array, got: ' . gettype($suggestions));
        return $post_ID;
    }
    
    error_log('[Smart AI] Received ' . count($suggestions) . ' suggestions from AI');
    
    // Debug: Log the suggestions
    if (function_exists('error_log')) {
        foreach ($suggestions as $index => $suggestion) {
            $anchor = is_array($suggestion) ? ($suggestion['anchor'] ?? '') : ($suggestion->anchor ?? '');
            $url = is_array($suggestion) ? ($suggestion['url'] ?? '') : ($suggestion->url ?? '');
            error_log('[Smart AI] Suggestion ' . ($index + 1) . ': anchor="' . $anchor . '", url="' . $url . '"');
        }
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
    // Store the actual inserted links as an array
    $result2 = update_post_meta($post_ID, '_smart_ai_linker_added_links', $formatted_suggestions);
    
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

    // Use DOMDocument for safer HTML manipulation
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $encoding = '<?xml encoding="utf-8" ?>';
    
    // Wrap content in a div and handle potential HTML issues
    $wrapped_content = '<div>' . $content . '</div>';
    
    // Load HTML with error suppression
    $dom->loadHTML($encoding . $wrapped_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);
    
    // Clear any libxml errors
    libxml_clear_errors();

    $used_anchors = [];
    $used_urls = [];
    $links_added = 0;
    $option_max = (int) get_option('smart_ai_linker_max_links', 7);
    $option_max = $option_max > 0 ? min(7, $option_max) : 7;
    $max_links = $option_max;

    $inserted_links = [];
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
        
        // Only select text nodes NOT inside <a>, <h1>, or <h2>
        $text_nodes = [];
        $xpath_query = '//text()[not(ancestor::a) and not(ancestor::h1) and not(ancestor::h2)]';
        foreach ($xpath->query($xpath_query) as $node) {
            $text_nodes[] = $node;
        }
        
        // Fallback: if no nodes found, use all text nodes not in <a>
        if (empty($text_nodes)) {
            foreach ($xpath->query('//text()[not(ancestor::a)]') as $node) {
                $text_nodes[] = $node;
            }
        }
        
        $inserted = false;
        // Try exact match first
        foreach ($text_nodes as $text_node) {
            $text = $text_node->nodeValue;
            $pos = mb_stripos($text, $anchor);
            if ($pos !== false) {
                $before = mb_substr($text, 0, $pos);
                $match = mb_substr($text, $pos, mb_strlen($anchor));
                $after = mb_substr($text, $pos + mb_strlen($anchor));
                
                $a = $dom->createElement('a', htmlspecialchars($match, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $a->setAttribute('href', esc_url($url));
                $a->setAttribute('class', 'smart-ai-link');
                $a->setAttribute('title', esc_attr($anchor));
                
                $parent = $text_node->parentNode;
                if ($parent !== null) {
                    if ($before !== '') $parent->insertBefore($dom->createTextNode($before), $text_node);
                    $parent->insertBefore($a, $text_node);
                    if ($after !== '') $parent->insertBefore($dom->createTextNode($after), $text_node);
                    $parent->removeChild($text_node);
                    
                    $used_anchors[] = $anchor;
                    $used_urls[] = $url;
                    $links_added++;
                    $inserted_links[] = is_array($link) ? $link : [ 'anchor' => $anchor, 'url' => $url ];
                    $inserted = true;
                    break;
                }
            }
        }
        
        // If not inserted, try partial match
        if (!$inserted) {
            $anchor_words = preg_split('/\s+/', $anchor);
            $partial = implode(' ', array_slice($anchor_words, 0, min(3, count($anchor_words))));
            
            foreach ($text_nodes as $text_node) {
                $text = preg_replace('/\s+/', ' ', $text_node->nodeValue);
                $pos = mb_stripos($text, $partial);
                if ($pos !== false) {
                    $before = mb_substr($text, 0, $pos);
                    $match = mb_substr($text, $pos, mb_strlen($partial));
                    $after = mb_substr($text, $pos + mb_strlen($partial));
                    
                    $a = $dom->createElement('a', htmlspecialchars($match, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                    $a->setAttribute('href', esc_url($url));
                    $a->setAttribute('class', 'smart-ai-link');
                    $a->setAttribute('title', esc_attr($anchor));
                    
                    $parent = $text_node->parentNode;
                    if ($parent !== null) {
                        if ($before !== '') $parent->insertBefore($dom->createTextNode($before), $text_node);
                        $parent->insertBefore($a, $text_node);
                        if ($after !== '') $parent->insertBefore($dom->createTextNode($after), $text_node);
                        $parent->removeChild($text_node);
                        
                        $used_anchors[] = $anchor;
                        $used_urls[] = $url;
                        $links_added++;
                        $inserted_links[] = is_array($link) ? $link : [ 'anchor' => $anchor, 'url' => $url ];
                        $inserted = true;
                        break;
                    }
                }
            }
        }
    }
    
    // Extract the modified content
    $new_content = '';
    $div_element = $dom->getElementsByTagName('div')->item(0);
    if ($div_element) {
        foreach ($div_element->childNodes as $child) {
            $new_content .= $dom->saveHTML($child);
        }
    } else {
        // Fallback if div element is not found
        $new_content = $content;
    }
    libxml_clear_errors();
    
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
        // After updating post content, update post meta with only actually inserted links
        update_post_meta($post_ID, '_smart_ai_linker_added_links', $inserted_links);
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

// --- Admin column: AI Links count ---
add_filter('manage_post_posts_columns', function($columns) {
    $columns['smart_ai_links'] = __('AI Links', 'smart-ai-linker');
    return $columns;
});
add_filter('manage_page_posts_columns', function($columns) {
    $columns['smart_ai_links'] = __('AI Links', 'smart-ai-linker');
    return $columns;
});
add_action('manage_post_posts_custom_column', function($column, $post_id) {
    if ($column === 'smart_ai_links') {
        echo smart_ai_linker_count_actual_links($post_id);
    }
}, 10, 2);
add_action('manage_page_posts_custom_column', function($column, $post_id) {
    if ($column === 'smart_ai_links') {
        echo smart_ai_linker_count_actual_links($post_id);
    }
}, 10, 2);
// --- End Admin column: AI Links count ---

// --- Admin column: AI Status badge ---
add_filter('manage_post_posts_columns', function($columns) {
    $columns['smart_ai_status'] = __('AI Status', 'smart-ai-linker');
    return $columns;
});
add_filter('manage_page_posts_columns', function($columns) {
    $columns['smart_ai_status'] = __('AI Status', 'smart-ai-linker');
    return $columns;
});
add_action('manage_post_posts_custom_column', function($column, $post_id) {
    if ($column === 'smart_ai_status') {
        $processed = get_post_meta($post_id, '_smart_ai_linker_processed', true);
        if ($processed) {
            echo '<span style="display:inline-block;padding:2px 8px;background:#46b450;color:#fff;border-radius:10px;font-size:11px;">'.__('Processed','smart-ai-linker').'</span>';
        } else {
            echo '<span style="display:inline-block;padding:2px 8px;background:#aaa;color:#fff;border-radius:10px;font-size:11px;">'.__('Unprocessed','smart-ai-linker').'</span>';
        }
    }
}, 10, 2);
add_action('manage_page_posts_custom_column', function($column, $post_id) {
    if ($column === 'smart_ai_status') {
        $processed = get_post_meta($post_id, '_smart_ai_linker_processed', true);
        if ($processed) {
            echo '<span style="display:inline-block;padding:2px 8px;background:#46b450;color:#fff;border-radius:10px;font-size:11px;">'.__('Processed','smart-ai-linker').'</span>';
        } else {
            echo '<span style="display:inline-block;padding:2px 8px;background:#aaa;color:#fff;border-radius:10px;font-size:11px;">'.__('Unprocessed','smart-ai-linker').'</span>';
        }
    }
}, 10, 2);
// --- End Admin column: AI Status badge ---

// --- Meta box: AI Links progress bar ---
add_action('add_meta_boxes', function() {
    foreach (array('post','page') as $type) {
        add_meta_box('smart_ai_linker_progress', __('AI Internal Links Progress', 'smart-ai-linker'), function($post) {
            $ai_count = smart_ai_linker_count_actual_links($post->ID);
            $max_links = (int) get_option('smart_ai_linker_max_links', 7);
            $max_links = $max_links > 0 ? min(7, $max_links) : 7;
            // Count manual internal links (excluding smart-ai-link class)
            $content = $post->post_content;
            $manual_count = 0;
            if ($content) {
                // Count all links except smart-ai-link ones
                preg_match_all('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i', $content, $matches);
                $total_links = count($matches[0]);
                $manual_count = $total_links - $ai_count;
                if ($manual_count < 0) $manual_count = 0;
            }
            $total = $ai_count + $manual_count;
            $percent = $max_links > 0 ? min(100, round(($ai_count/$max_links)*100)) : 0;
            echo '<div style="margin-bottom:8px;font-weight:bold;">'.esc_html($ai_count).' of '.esc_html($max_links).' AI links used</div>';
            echo '<div style="background:#e5e5e5;border-radius:4px;height:18px;width:100%;margin-bottom:8px;overflow:hidden;">'
                .'<div style="background:#0073aa;height:18px;width:'.$percent.'%;transition:width 0.3s;"></div>'
                .'</div>';
            echo '<div style="font-size:12px;color:#666;">Manual internal links: '.esc_html($manual_count).'</div>';
        }, $type, 'side', 'high');
    }
});
// --- End Meta box: AI Links progress bar ---

// --- the_content filter for universal builder compatibility (DOMDocument, priority 999, fuzzy matching) ---
add_filter('the_content', function($content) {
    if (is_admin() || defined('REST_REQUEST') && REST_REQUEST) return $content;
    global $post;
    if (!$post || !in_array($post->post_type, array('post','page'))) return $content;
    if (strpos($content, 'class="smart-ai-link"') !== false) return $content;
    
    // Check if this post is excluded from internal linking
    $excluded_posts = get_option('smart_ai_linker_excluded_posts', array());
    if (in_array($post->ID, (array) $excluded_posts)) {
        return $content;
    }
    
    $clean_content = wp_strip_all_tags(strip_shortcodes($content));
    $word_count = str_word_count($clean_content);
    
    // Get minimum word count from settings, default to 10 if not set
    $min_word_count = get_option('smart_ai_min_content_length', 10);
    if ($word_count < $min_word_count) {
        if (function_exists('error_log')) {
            error_log('[Smart AI] Skipping post ID ' . ($post->ID ?? 'unknown') . ' in the_content filter: only ' . $word_count . ' words (minimum: ' . $min_word_count . ')');
        }
        return $content;
    }
    
    $silo_post_ids = [];
    if (class_exists('Smart_AI_Linker_Silos')) {
        $silo_instance = Smart_AI_Linker_Silos::get_instance();
        $post_silos = $silo_instance->get_post_silos($post->ID);
        if (!empty($post_silos)) {
            global $wpdb;
            $silo_ids = array_map(function($silo){ return is_object($silo) ? $silo->id : $silo['id']; }, $post_silos);
            $placeholders = implode(',', array_fill(0, count($silo_ids), '%d'));
            $query = $wpdb->prepare(
                "SELECT post_id FROM {$silo_instance->silo_relationships} WHERE silo_id IN ($placeholders) AND post_id != %d",
                array_merge($silo_ids, [$post->ID])
            );
            $silo_post_ids = $wpdb->get_col($query);
        }
    }
    $suggestions = smart_ai_linker_get_ai_link_suggestions($clean_content, $post->ID, $post->post_type, $silo_post_ids);
    $option_max = (int) get_option('smart_ai_linker_max_links', 7);
    $option_max = $option_max > 0 ? min(7, $option_max) : 7;
    $max_links = $option_max;
    $suggestions = array_slice($suggestions, 0, $max_links);
    if (empty($suggestions)) return $content;
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $encoding = '<?xml encoding="utf-8" ?>';
    $dom->loadHTML($encoding . '<div>' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $xpath = new DOMXPath($dom);
    $used_anchors = [];
    $used_urls = [];
    $links_added = 0;
    foreach ($suggestions as $link) {
        if ($links_added >= $max_links) break;
        $anchor = is_array($link) ? ($link['anchor'] ?? '') : ($link->anchor ?? '');
        $url = is_array($link) ? ($link['url'] ?? '') : ($link->url ?? '');
        if (empty($anchor) || empty($url)) continue;
        if ($post->post_type === 'page') {
            $target_post_id = url_to_postid($url);
            if ($target_post_id && get_post_type($target_post_id) !== 'page') continue;
        }
        if (in_array($anchor, $used_anchors, true) || in_array($url, $used_urls, true)) continue;
        // Only select text nodes NOT inside <a>, <h1>, or <h2>
        $text_nodes = [];
        $xpath_query = '//text()[not(ancestor::a) and not(ancestor::h1) and not(ancestor::h2)]';
        foreach ($xpath->query($xpath_query) as $node) {
            $text_nodes[] = $node;
        }
        // Fallback: if no nodes found, use all text nodes not in <a>
        if (empty($text_nodes)) {
            foreach ($xpath->query('//text()[not(ancestor::a)]') as $node) {
                $text_nodes[] = $node;
            }
        }
        // Debug: log how many nodes are found
        if (function_exists('error_log')) {
            error_log('[Smart AI] Matched text nodes for linking: ' . count($text_nodes));
        }
        $inserted = false;
        // 1. Try exact match
        foreach ($text_nodes as $text_node) {
            $text = $text_node->nodeValue;
            $pos = mb_stripos($text, $anchor);
            if ($pos !== false) {
                error_log('[Smart AI] Found exact match for anchor "' . $anchor . '" in text: "' . substr($text, 0, 100) . '"');
                $before = mb_substr($text, 0, $pos);
                $match = mb_substr($text, $pos, mb_strlen($anchor));
                $after = mb_substr($text, $pos + mb_strlen($anchor));
                $a = $dom->createElement('a', htmlspecialchars($match, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $a->setAttribute('href', esc_url($url));
                $a->setAttribute('class', 'smart-ai-link');
                $a->setAttribute('title', esc_attr($anchor));
                $parent = $text_node->parentNode;
                if ($parent !== null) {
                    if ($before !== '') $parent->insertBefore($dom->createTextNode($before), $text_node);
                    $parent->insertBefore($a, $text_node);
                    if ($after !== '') $parent->insertBefore($dom->createTextNode($after), $text_node);
                    $parent->removeChild($text_node);
                    $used_anchors[] = $anchor;
                    $used_urls[] = $url;
                    $links_added++;
                    $inserted = true;
                    error_log('[Smart AI] Successfully inserted link for anchor "' . $anchor . '"');
                    break;
                }
            }
        }
        if ($inserted) continue;
        // 2. Try partial/fuzzy match (first 2-3 words, normalized)
        $anchor_words = preg_split('/\s+/', $anchor);
        $partial = implode(' ', array_slice($anchor_words, 0, min(3, count($anchor_words))));
        foreach ($text_nodes as $text_node) {
            $text = preg_replace('/\s+/', ' ', $text_node->nodeValue);
            $pos = mb_stripos($text, $partial);
            if ($pos !== false) {
                $before = mb_substr($text, 0, $pos);
                $match = mb_substr($text, $pos, mb_strlen($partial));
                $after = mb_substr($text, $pos + mb_strlen($partial));
                $a = $dom->createElement('a', htmlspecialchars($match, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
                $a->setAttribute('href', esc_url($url));
                $a->setAttribute('class', 'smart-ai-link');
                $a->setAttribute('title', esc_attr($anchor));
                $parent = $text_node->parentNode;
                if ($parent !== null) {
                    if ($before !== '') $parent->insertBefore($dom->createTextNode($before), $text_node);
                    $parent->insertBefore($a, $text_node);
                    if ($after !== '') $parent->insertBefore($dom->createTextNode($after), $text_node);
                    $parent->removeChild($text_node);
                    $used_anchors[] = $anchor;
                    $used_urls[] = $url;
                    $links_added++;
                    $inserted = true;
                    break;
                }
            }
        }
        // No forced insertion, no debug comment, no new words
    }
    $new_content = '';
    foreach ($dom->getElementsByTagName('div')->item(0)->childNodes as $child) {
        $new_content .= $dom->saveHTML($child);
    }
    libxml_clear_errors();
    
    // Log the final result
    if (function_exists('error_log')) {
        error_log('[Smart AI] Link insertion complete. Added ' . $links_added . ' links to post ' . $post->ID);
    }
    
    return $new_content;
}, 999);
// --- end the_content filter ---

/**
 * Count the actual number of smart-ai-link tags in post content
 * This provides the most accurate count of links that are actually present
 * 
 * @param int $post_id The post ID
 * @return int Number of smart-ai-link tags found
 */
function smart_ai_linker_count_actual_links($post_id) {
    $post = get_post($post_id);
    if (!$post || empty($post->post_content)) {
        return 0;
    }
    
    // Count <a class="smart-ai-link"> tags in content
    preg_match_all('/<a\s+[^>]*class=["\']smart-ai-link["\'][^>]*>/i', $post->post_content, $matches);
    return count($matches[0]);
}


