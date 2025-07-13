<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Internal Linking Module (Smart AI Linker)
 * 
 * Handles automatic internal linking when posts are published.
 */

// Hook into post publishing
add_action('publish_post', 'smart_ai_linker_generate_internal_links', 10, 2);

/**
 * Generate and insert internal links when a post is published
 * 
 * @param int $post_ID The post ID
 * @param WP_Post $post The post object
 */
function smart_ai_linker_generate_internal_links($post_ID, $post) {
    // Don't run on revisions or autosaves
    if (wp_is_post_revision($post_ID) || wp_is_post_autosave($post_ID)) {
        return;
    }

    // Check if auto-linking is enabled
    $enable_auto_linking = get_option('smart_ai_linker_enable_auto_linking', '1');
    if (!$enable_auto_linking) {
        return;
    }

    // Check if this is a post type we want to process
    $post_types = get_option('smart_ai_linker_post_types', array('post'));
    if (!in_array($post->post_type, (array)$post_types, true)) {
        return;
    }

    // Check if we've already processed this post
    $processed = get_post_meta($post_ID, '_smart_ai_linker_processed', true);
    if ($processed) {
        return;
    }

    // Get the post content without shortcodes and HTML tags
    $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
    
    if (empty($content)) {
        error_log('[Smart AI] Empty post content, skipping');
        return;
    }

    // Get AI suggestions for internal links
    $suggestions = smart_ai_linker_get_ai_link_suggestions($content, $post_ID);
    
    if (empty($suggestions)) {
        error_log('[Smart AI] No link suggestions received');
        return;
    }
    
    // Limit the number of links based on settings
    $max_links = (int) get_option('smart_ai_linker_max_links', 7);
    $suggestions = array_slice($suggestions, 0, $max_links);

    // Insert the links into the post
    $result = smart_ai_linker_insert_links_into_post($post_ID, $suggestions);
    
    if ($result) {
        // Mark as processed
        update_post_meta($post_ID, '_smart_ai_linker_processed', current_time('mysql'));
        error_log('[Smart AI] Successfully added ' . count($suggestions) . ' internal links to post ' . $post_ID);
    }
}

/**
 * Insert links into the post content at appropriate positions
 * 
 * @param int $post_ID The post ID
 * @param array $links Array of link suggestions with 'anchor' and 'url' keys
 * @return bool True on success, false on failure
 */
function smart_ai_linker_insert_links_into_post($post_ID, $links = []) {
    if (empty($links) || !is_array($links)) {
        return false;
    }

    // Get the post
    $post = get_post($post_ID);
    if (!$post) {
        return false;
    }

    $content = $post->post_content;
    $paragraphs = preg_split('/\r\n|\r|\n/', $content);
    $updated = false;
    $used_anchors = [];
    $used_urls = [];
    $links_added = 0;
    $max_links = min(
        (int) get_option('smart_ai_linker_max_links', 7),
        count($links)
    );

    // Process each paragraph to find good places to insert links
    foreach ($paragraphs as &$paragraph) {
        // Skip if we've added enough links
        if ($links_added >= $max_links) {
            break;
        }

        // Skip short paragraphs
        if (str_word_count(strip_tags($paragraph)) < 15) {
            continue;
        }

        // Find the best link to insert in this paragraph
        foreach ($links as $key => $link) {
            $anchor = $link['anchor'];
            $url = $link['url'];
            
            // Skip if we've already used this anchor or URL
            if (in_array($anchor, $used_anchors, true) || in_array($url, $used_urls, true)) {
                continue;
            }

            // Check if the anchor text appears in this paragraph
            if (stripos($paragraph, $anchor) !== false) {
                // Create the link
                $link_html = sprintf(
                    ' <a href="%s" class="smart-ai-link" title="%s">%s</a> ',
                    esc_url($url),
                    esc_attr(get_the_title(url_to_postid($url))),
                    esc_html($anchor)
                );

                // Replace the first occurrence of the anchor text with the link
                $paragraph = preg_replace(
                    '/\b' . preg_quote($anchor, '/') . '\b/i',
                    $link_html,
                    $paragraph,
                    1
                );

                // Mark this link as used
                $used_anchors[] = $anchor;
                $used_urls[] = $url;
                $links_added++;
                $updated = true;
                
                // Move to the next paragraph
                continue 2;
            }
        }
    }

    // If we found places to insert links, update the post
    if ($updated) {
        $new_content = implode("\n\n", $paragraphs);
        
        // Update the post with the new content
        $result = wp_update_post([
            'ID' => $post_ID,
            'post_content' => $new_content
        ], true);

        if (is_wp_error($result)) {
            error_log('[Smart AI] Error updating post: ' . $result->get_error_message());
            return false;
        }

        // Store the links that were added
        $added_links = array_intersect_key($links, array_flip($used_anchors));
        update_post_meta($post_ID, '_smart_ai_linker_added_links', $added_links);
        
        return true;
    }

    return false;
}
