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
 */
function smart_ai_linker_generate_internal_links($post_ID, $post, $update) {
    error_log('[Smart AI] Starting internal link generation for post ' . $post_ID);
    
    // Don't run on autosaves or revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        error_log('[Smart AI] Skipping - this is an autosave');
        return;
    }
    if (wp_is_post_revision($post_ID) || wp_is_post_autosave($post_ID)) {
        error_log('[Smart AI] Skipping - this is a revision or autosave');
        return;
    }

    // Only run for published posts
    if ($post->post_status !== 'publish') {
        error_log('[Smart AI] Skipping - post status is ' . $post->post_status);
        return;
    }
    
    error_log('[Smart AI] Post is published, proceeding with link generation');

    // Check if auto-linking is enabled
    $enable_auto_linking = get_option('smart_ai_linker_enable_auto_linking', '1');
    error_log('[Smart AI] Auto-linking setting: ' . ($enable_auto_linking ? 'enabled' : 'disabled'));
    
    if (!$enable_auto_linking) {
        error_log('[Smart AI] Auto-linking is disabled in settings');
        return;
    }

    // Check if this is a post type we want to process
    $post_types = get_option('smart_ai_linker_post_types', array('post'));
    error_log('[Smart AI] Checking post type: ' . $post->post_type);
    error_log('[Smart AI] Allowed post types: ' . print_r($post_types, true));
    
    if (!in_array($post->post_type, (array)$post_types, true)) {
        error_log('[Smart AI] Post type not enabled for linking: ' . $post->post_type);
        return;
    }
    error_log('[Smart AI] Post type is enabled for linking');

    // Get the post content and clean it up for processing
    $content = $post->post_content;
    
    if (empty($content)) {
        error_log('[Smart AI] Empty post content, skipping');
        return;
    }
    
    // Remove any existing links to avoid duplicates
    $content = preg_replace('/<a\b[^>]*>(.*?)<\/a>/i', '$1', $content);
    
    // Strip shortcodes and tags for the AI analysis
    $clean_content = wp_strip_all_tags(strip_shortcodes($content));
    
    if (empty($clean_content)) {
        error_log('[Smart AI] No content available for analysis after cleaning');
        return;
    }

    // Get AI suggestions for internal links
    error_log('[Smart AI] Getting AI suggestions for post ' . $post_ID);
    $suggestions = smart_ai_linker_get_ai_link_suggestions($clean_content, $post_ID);
    
    if (empty($suggestions) || !is_array($suggestions)) {
        error_log('[Smart AI] No valid link suggestions received');
        return;
    }
    
    error_log('[Smart AI] Received ' . count($suggestions) . ' link suggestions');
    
    // Limit the number of links based on settings
    $max_links = (int) get_option('smart_ai_linker_max_links', 7);
    $suggestions = array_slice($suggestions, 0, $max_links);

    // Insert the links into the post
    error_log('[Smart AI] Attempting to insert links into post ' . $post_ID);
    $result = smart_ai_linker_insert_links_into_post($post_ID, $suggestions);
    
    if ($result) {
        // Store when we last processed this post
        update_post_meta($post_ID, '_smart_ai_linker_processed', current_time('mysql'));
        error_log('[Smart AI] Successfully added ' . count($suggestions) . ' internal links to post ' . $post_ID);
        
        // Store the added links for reference
        update_post_meta($post_ID, '_smart_ai_linker_added_links', $suggestions);
    } else {
        error_log('[Smart AI] Failed to insert links into post ' . $post_ID);
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
        error_log('[Smart AI] No links provided for insertion');
        return false;
    }
    
    error_log('[Smart AI] Attempting to insert ' . count($links) . ' links into post ' . $post_ID);

    // Get the post
    $post = get_post($post_ID);
    if (!$post) {
        return false;
    }

    // Break content into paragraphs
    $paragraphs = preg_split(
        '/(<\/p>\s*<p[^>]*>|<\/p>|<p[^>]*>)/i', 
        $post->post_content, 
        -1, 
        1 | 2  // PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
    );
    $paragraphs = array_map('trim', $paragraphs);
    $paragraphs = array_filter($paragraphs, function($p) {
        return !empty($p) && $p !== '<p>' && $p !== '</p>' && $p !== '<p></p>';
    });
    
    // Reset array keys to ensure sequential numeric indexes
    $paragraphs = array_values($paragraphs);
    
    // Log the number of paragraphs found
    error_log('[Smart AI] Found ' . count($paragraphs) . ' paragraphs in the content');

    $updated = false;
    $used_anchors = [];
    $used_urls = [];
    $links_added = 0;
    $max_links = min(
        (int) get_option('smart_ai_linker_max_links', 7),
        count($links)
    );

    // Process each paragraph to find good places to insert links
    foreach ($paragraphs as $p_index => $paragraph) {
        // Store the original paragraph for comparison
        $original_paragraph = $paragraph;
        
        // Skip short paragraphs
        if (str_word_count(strip_tags($paragraph)) < 5) {
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
            
            // Log the anchor text we're trying to match
            error_log('[Smart AI] Looking for anchor text: ' . $anchor);
            
            // Check if the anchor text exists in the paragraph (case-insensitive)
            $replaced = false;
            $replace_count = 0; // Initialize replace_count
            
            // Log the paragraph and anchor being checked
            error_log('[Smart AI] Checking paragraph for anchor: ' . $anchor);
            error_log('[Smart AI] Paragraph content: ' . substr($paragraph, 0, 200) . (strlen($paragraph) > 200 ? '...' : ''));
            
            // First try exact match
            $pos = stripos($paragraph, $anchor);
            if ($pos !== false) {
                error_log(sprintf(
                    '[Smart AI] Found exact match for anchor "%s" at position %d in paragraph',
                    $anchor,
                    $pos
                ));
                
                // Create the link HTML
                $link_html = sprintf(
                    '<a href="%s" class="smart-ai-link">%s</a>',
                    esc_url($url),
                    esc_html($anchor)
                );
                
                // Get the actual matched text from the paragraph
                $matched_text = substr($paragraph, $pos, strlen($anchor));
                error_log('[Smart AI] Matched text in paragraph: ' . $matched_text);
                
                // Replace the first occurrence of the anchor text with the link
                $new_paragraph = substr_replace(
                    $paragraph,
                    $link_html,
                    $pos,
                    strlen($matched_text)
                );
                
                if ($new_paragraph !== $paragraph) {
                    error_log('[Smart AI] Successfully replaced anchor with link');
                    $paragraph = $new_paragraph;
                    $replaced = true;
                    $replace_count = 1;
                } else {
                    error_log('[Smart AI] Failed to replace anchor with link (no change after replacement)');
                }
            } 
            // If no exact match, try word boundary match
            if (!$replaced) {
                $pattern = '/\b' . preg_quote($anchor, '/') . '\b/i';
                if (preg_match($pattern, $paragraph)) {
                    error_log('[Smart AI] Found word boundary match for anchor: ' . $anchor);
                    $new_paragraph = preg_replace(
                        $pattern,
                        $link_html,
                        $paragraph,
                        1,
                        $replace_count
                    );
                    
                    if ($new_paragraph !== null && $new_paragraph !== $paragraph) {
                        $paragraph = $new_paragraph;
                        $replaced = ($replace_count > 0);
                        error_log('[Smart AI] Successfully replaced word boundary match');
                    } else {
                        error_log('[Smart AI] Failed to replace word boundary match');
                    }
                } else {
                    error_log('[Smart AI] No word boundary match found for: ' . $anchor);
                }
            }
            // If still no match, try partial matches
            if (!$replaced) {
                $words = explode(' ', $anchor);
                
                // If still no match, try partial match with first 3 words
                if (!$replaced && count($words) > 3) {
                    $partial_anchor = implode(' ', array_slice($words, 0, 3));
                    error_log('[Smart AI] Trying partial match for: ' . $partial_anchor);
                    
                    $pos = stripos($paragraph, $partial_anchor);
                    if ($pos !== false) {
                        error_log('[Smart AI] Found partial match at position: ' . $pos);
                        
                        // Get the actual matched text for replacement
                        $matched_text = substr($paragraph, $pos, strlen($partial_anchor));
                        $new_paragraph = substr_replace(
                            $paragraph,
                            $link_html,
                            $pos,
                            strlen($matched_text)
                        );
                        
                        if ($new_paragraph !== $paragraph) {
                            $paragraph = $new_paragraph;
                            $replaced = true;
                            $replace_count = 1;
                            error_log('[Smart AI] Successfully replaced partial anchor: ' . $partial_anchor);
                        } else {
                            error_log('[Smart AI] Failed to replace partial anchor (substr_replace failed)');
                            
                            // Fallback to string replacement
                            $new_paragraph = str_ireplace(
                                $partial_anchor,
                                $link_html,
                                $paragraph,
                                $replace_count
                            );
                            
                            if ($new_paragraph !== $paragraph) {
                                $paragraph = $new_paragraph;
                                $replaced = true;
                                error_log('[Smart AI] Successfully replaced using str_ireplace: ' . $partial_anchor);
                            } else {
                                error_log('[Smart AI] Fallback str_ireplace also failed');
                            }
                        }
                    } else {
                        error_log('[Smart AI] Partial anchor not found in paragraph: ' . $partial_anchor);
                    }
                }
            }
            
            // If still no match, try to find a good position to insert the link
            if (!$replaced) {
                error_log('[Smart AI] Could not find anchor text, attempting to insert at optimal position');
                
                // First try middle of the paragraph
                $words = explode(' ', $paragraph);
                $position = floor(count($words) / 2);
                if ($position > 0) {
                    $words[$position] = $link_html . ' ' . $words[$position];
                    $paragraph = implode(' ', $words);
                    $replaced = true;
                    error_log('[Smart AI] Inserted link in the middle of paragraph');
                } 
                // If that fails, try to find a good position based on word matches
                else {
                    $paragraph_text = is_string($paragraph) ? strip_tags($paragraph) : '';
                    $paragraph_words = !empty($paragraph_text) ? str_word_count($paragraph_text, 1) : [];
                    
                    // Look for any word from the anchor in the paragraph
                    $anchor_words = array_filter(array_unique(array_merge(
                        is_string($anchor) ? explode(' ', $anchor) : [],
                        is_string($anchor) ? explode(' ', str_replace(['-', '_'], ' ', $anchor)) : []
                    )), 'strlen');
                    
                    $best_position = -1;
                    $best_score = 0;
                    
                    // Score each position based on word matches
                    foreach ($paragraph_words as $i => $word) {
                        $score = 0;
                        foreach ($anchor_words as $aword) {
                            if (stripos($word, $aword) !== false) {
                                $score++;
                            }
                        }
                        
                        if ($score > $best_score) {
                            $best_score = $score;
                            $best_position = $i;
                        }
                    }
                    
                    // If we found a good position, insert the link
                    if ($best_position >= 0) {
                        $words = explode(' ', $paragraph_text);
                        if (isset($words[$best_position])) {
                            $words[$best_position] .= ' ' . $link_html;
                            $paragraph = implode(' ', $words);
                            $replaced = true;
                            error_log('[Smart AI] Inserted link near matching words');
                        }
                    }
                    
                    // If still no match, skip to the next suggestion
                    if (!$replaced) {
                        error_log('[Smart AI] Could not find a good position for anchor: ' . $anchor);
                        continue;
                    }
                }

                // Mark this link as used
                if (!in_array($anchor, $used_anchors, true)) {
                    $used_anchors[] = $anchor;
                    $used_urls[] = $url;
                    $links_added++;
                    $updated = true;
                    
                    // Save the modified paragraph back to the array
                    $paragraphs[$p_index] = $paragraph;
                    error_log('[Smart AI] Successfully inserted link: ' . $anchor . ' -> ' . $url);
                    
                    // Log the updated paragraph
                    error_log('[Smart AI] Updated paragraph content: ' . substr($paragraph, 0, 200) . (strlen($paragraph) > 200 ? '...' : ''));
                    
                    // Log the changes
                    if ($original_paragraph !== $paragraph) {
                        error_log('[Smart AI] Paragraph was modified successfully');
                    } else {
                        error_log('[Smart AI] WARNING: Paragraph content was not modified');
                    }
                    
                    // Move to the next paragraph after successful insertion
                    break;
                }
            }
        }
    }
    
    // If we get here, we've processed all paragraphs and links
    // Log the state before attempting to update
    error_log('[Smart AI] Link insertion summary:');
    error_log('[Smart AI] - Links found in content: ' . count($used_anchors));
    error_log('[Smart AI] - Links to insert: ' . count($links));
    error_log('[Smart AI] - Links added: ' . $links_added);
    error_log('[Smart AI] - Updated flag: ' . ($updated ? 'true' : 'false'));
    
    if ($updated && $links_added > 0) {
        // Rebuild the content with paragraph tags
        $new_content = '';
        foreach ($paragraphs as $p) {
            if (!empty($p)) {
                $new_content .= '<p>' . $p . '</p>' . "\n";
            }
        }
        
        // Log the old and new content for comparison
        error_log('[Smart AI] Old content length: ' . strlen($post->post_content));
        error_log('[Smart AI] New content length: ' . strlen($new_content));
        
        // Update the post with the new content
        error_log('[Smart AI] Attempting to update post ' . $post_ID);
        
        // Temporarily remove our save_post hook to prevent infinite loop
        remove_action('save_post', 'smart_ai_linker_generate_internal_links', 20);
        
        // Update the post
        $result = wp_update_post([
            'ID' => $post_ID,
            'post_content' => $new_content
        ], true);
        
        // Re-add our hook
        add_action('save_post', 'smart_ai_linker_generate_internal_links', 20, 3);

        if (is_wp_error($result)) {
            error_log('[Smart AI] Error updating post: ' . $result->get_error_message());
            return false;
        }

        // Store the links that were added
        $added_links = array_intersect_key($links, array_flip($used_anchors));
        update_post_meta($post_ID, '_smart_ai_linker_added_links', $added_links);
        
        error_log('[Smart AI] Successfully updated post with ' . count($added_links) . ' new links');
        
        // Log the updated post content
        $updated_post = get_post($post_ID);
        error_log('[Smart AI] Updated post content: ' . substr($updated_post->post_content, 0, 500) . '...');
        
        return true;
    } else {
        error_log('[Smart AI] No links were inserted. Possible reasons:');
        error_log('[Smart AI] - No matching anchor text found in content');
        error_log('[Smart AI] - All suggested links were already used');
        error_log('[Smart AI] - Content may not have enough context for linking');
        
        // Log the current state of the paragraphs
        error_log('[Smart AI] Paragraphs after processing: ' . print_r($paragraphs, true));
        
        return false;
    }
}
