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
    
    // Remove any existing links to avoid duplicates
    if (is_string($content)) {
        $content = preg_replace('/<a\b[^>]*>(.*?)<\/a>/i', '$1', $content);
    } else {
        error_log('[Smart AI] Content is not a string, cannot process links');
        return $post_ID;
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
    
    // Get suggestions based on post type
    $suggestions = smart_ai_linker_get_ai_link_suggestions($content_for_analysis, $post_ID, $post_type);
    
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
    $max_links = max(7, (int) get_option('smart_ai_linker_max_links', 7));
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
    
    // Always return the post ID to maintain WordPress filter compatibility
    return $post_ID;
}

/**
 * Insert links into the post content at appropriate positions
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
    $paragraphs = array_values(array_filter($paragraphs, function($p) {
        return !empty($p) && $p !== '<p>' && $p !== '</p>' && $p !== '<p></p>';
    }));
    
    $updated = false;
    $links_added = 0;
    $used_anchors = [];
    $used_urls = [];
    $added_links = [];
    
    error_log('[Smart AI] Processing content as a single paragraph');

    // Process each paragraph to find good places to insert links
    foreach ($paragraphs as $p_index => $paragraph) {
        // If we've added all possible links, break out of the loop
        if (count($used_urls) >= count($links)) {
            error_log('[Smart AI] All possible links have been added, stopping processing');
            break;
        }
        
        // Store the original paragraph for comparison
        $original_paragraph = $paragraph;
        
        // Skip short paragraphs (minimum 3 words instead of 5 to allow more linking opportunities)
        if (str_word_count(strip_tags($paragraph)) < 3) {
            error_log('[Smart AI] Skipping short paragraph');
            continue;
        }

        // Find the best link to insert in this paragraph
        foreach ($links as $key => $link) {
            // Handle both array and object access
            $anchor = is_array($link) ? ($link['anchor'] ?? '') : ($link->anchor ?? '');
            $url = is_array($link) ? ($link['url'] ?? '') : ($link->url ?? '');
            
            // Skip if we don't have valid anchor or URL
            if (empty($anchor) || empty($url)) {
                error_log('[Smart AI] Skipping invalid link: ' . print_r($link, true));
                continue;
            }
            
            // Skip if we've already used this anchor or URL
            if (in_array($anchor, $used_anchors, true) || in_array($url, $used_urls, true)) {
                error_log('[Smart AI] Skipping already used anchor/URL: ' . $anchor . ' | ' . $url);
                continue;
            }
            
            // Log the anchor text we're trying to match
            error_log('[Smart AI] Looking for anchor text: ' . $anchor);
            
            // Check if the anchor text exists in the paragraph (case-insensitive)
            $replaced = false;
            $replace_count = 0; // Initialize replace_count
            
            // Store the original paragraph for comparison
            $original_paragraph = $paragraph;
            
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
                
                // Get the characters before and after the match
                $before = $pos > 0 ? substr($paragraph, $pos - 1, 1) : '';
                $after = isset($paragraph[$pos + strlen($anchor)]) ? substr($paragraph, $pos + strlen($anchor), 1) : '';
                
                error_log(sprintf(
                    '[Smart AI] Characters around match: "%s" and "%s"',
                    $before,
                    $after
                ));
                
                // Check if this exact anchor is already linked to avoid duplicates
                $next_char = isset($paragraph[$pos + strlen($anchor)]) ? $paragraph[$pos + strlen($anchor)] : '';
                $prev_char = $pos > 0 ? $paragraph[$pos - 1] : '';
                
                // Skip if the anchor is already inside a link or next to special characters
                if ($next_char === '<' || $prev_char === '>') {
                    error_log('[Smart AI] Skipping anchor inside HTML tag');
                    continue;
                }
                
                // Check if the anchor is already wrapped in a link
                $surrounding = substr($paragraph, max(0, $pos - 10), strlen($anchor) + 20);
                if (preg_match('/<a\s+[^>]*>.*' . preg_quote($anchor, '/') . '.*<\/a>/i', $surrounding)) {
                    error_log('[Smart AI] Skipping already linked anchor');
                    continue;
                }
                
                // Clean the anchor text to prevent XSS and HTML injection
                $clean_anchor = wp_strip_all_tags($anchor);
                $clean_anchor = trim($clean_anchor);
                
                // Create the link HTML with proper escaping
                $link_html = sprintf(
                    '<a href="%s" class="smart-ai-link" title="%s">%s</a>',
                    esc_url($url),
                    esc_attr($clean_anchor),
                    esc_html($clean_anchor)
                );
                
                // Ensure we're not creating nested links
                $paragraph = preg_replace('/<a\s+[^>]*>\s*' . preg_quote($clean_anchor, '/') . '\s*<\/a>/i', $clean_anchor, $paragraph);

                error_log('[Smart AI] Link HTML: ' . $link_html);
                
                // Replace the matched text with the link
                $new_paragraph = substr_replace($paragraph, $link_html, $pos, strlen($anchor));
                
                // Update the paragraph if it changed
                if ($new_paragraph !== $paragraph) {
                    error_log('[Smart AI] Successfully replaced anchor with link');
                    $paragraph = $new_paragraph;
                    $paragraphs[$p_index] = $paragraph; // Update the paragraphs array
                    $replaced = true;
                    $updated = true; // Mark that we've made changes
                    
                    // Update the content with the modified paragraph
                    $content = $paragraph;
                    error_log('[Smart AI] Updated content with modified paragraph');
                    $replace_count = 1;
                    
                    // Add this anchor to used anchors to prevent duplicate replacements
                    $used_anchors[] = $anchor;
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
                            $paragraphs[$p_index] = $paragraph; // Update the paragraphs array
                            $replaced = true;
                            error_log('[Smart AI] Successfully replaced partial anchor: ' . $partial_anchor);
                            
                            // Log the change
                            error_log('[Smart AI] Original paragraph: ' . $original_paragraph);
                            error_log('[Smart AI] Modified paragraph: ' . $paragraph);
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
                    error_log('[Smart AI] Current paragraph content: ' . $paragraph);
                    
                    // Log the changes
                    if ($original_paragraph !== $paragraph) {
                        error_log('[Smart AI] Paragraph was modified successfully');
                        error_log('[Smart AI] Original paragraph: ' . $original_paragraph);
                        error_log('[Smart AI] Modified paragraph: ' . $paragraph);
                        
                        // Update the content with the modified paragraph
                        $content = implode("\n\n", $paragraphs);
                        error_log('[Smart AI] Updated content with modified paragraph');
                    } else {
                        error_log('[Smart AI] WARNING: Paragraph content was not modified');
                        error_log('[Smart AI] Original paragraph length: ' . strlen($original_paragraph));
                        error_log('[Smart AI] Modified paragraph length: ' . strlen($paragraph));
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
    
    // If we have content to update, clean and save it
    if ($updated && !empty($content) && $content !== $post->post_content) {
        // Clean up any malformed HTML
        $content = preg_replace_callback('/<a\s+([^>]*)>([^<]*)<\/a>/i', function($matches) {
            // Get attributes and content
            $attrs = $matches[1];
            $content = $matches[2];
            
            // Extract URL and title
            preg_match('/href=["\']([^"\']*)["\']/', $attrs, $url_matches);
            preg_match('/title=["\']([^"\']*)["\']/', $attrs, $title_matches);
            
            $url = $url_matches[1] ?? '';
            $title = $title_matches[1] ?? '';
            
            // Clean up the content
            $clean_content = wp_strip_all_tags($content);
            $clean_content = trim($clean_content);
            
            // Rebuild the link with clean attributes
            return sprintf(
                '<a href="%s" class="smart-ai-link" title="%s">%s</a>',
                esc_url($url),
                esc_attr($clean_content),
                esc_html($clean_content)
            );
        }, $content);
        
        // Fix any remaining malformed HTML
        $content = force_balance_tags($content);
        $content = wp_kses_post($content);
        
        // Remove any empty paragraphs or other empty tags
        $content = preg_replace('/<[a-z]+[^>]*>\s*<\/[a-z]+>/i', '', $content);
        error_log('[Smart AI] Preparing to update post content');
        error_log('[Smart AI] New content length: ' . strlen($content));
        error_log('[Smart AI] First 200 chars of new content: ' . substr($content, 0, 200));
        
        // Check if our save_post hook is registered before trying to remove it
        $has_hook = has_action('save_post', 'smart_ai_linker_save_post');
        if ($has_hook !== false) {
            remove_action('save_post', 'smart_ai_linker_save_post', 10);
        }
        
        // First try direct database update
        global $wpdb;
        $result = $wpdb->update(
            $wpdb->posts,
            array('post_content' => $content),
            array('ID' => $post_ID),
            array('%s'),
            array('%d')
        );
        
        // Clear the post cache
        clean_post_cache($post_ID);
        
        // Only add the hook back if it was registered initially
        if ($has_hook !== false) {
            add_action('save_post', 'smart_ai_linker_save_post', 10, 2);
        }
        
        if ($result === false) {
            error_log('[Smart AI] Direct database update failed: ' . $wpdb->last_error);
            
            // Fallback to wp_update_post if direct update fails
            error_log('[Smart AI] Trying fallback update method using wp_update_post');
            $update_result = wp_update_post(array(
                'ID' => $post_ID,  // Changed from $post_id to $post_ID
                'post_content' => $content
            ), true);
            
            if (is_wp_error($update_result)) {
                error_log('[Smart AI] Fallback update failed: ' . $update_result->get_error_message());
                return false;
            }
            
            error_log('[Smart AI] Fallback update successful');
        } else {
            error_log('[Smart AI] Direct database update successful');
        }
        
        // Verify the update
        $updated_post = get_post($post_ID);  // Changed from $post_id to $post_ID
        if ($updated_post && strpos($updated_post->post_content, 'smart-ai-link') !== false) {
            error_log('[Smart AI] Post content verified - links found in updated content');
            return true;
        } else {
            error_log('[Smart AI] WARNING: Post content verification failed');
            error_log('[Smart AI] Expected content to contain smart-ai-link class');
            error_log('[Smart AI] Actual content: ' . ($updated_post ? substr($updated_post->post_content, 0, 500) : 'Post not found'));
            return false;
        }

        // Include broken links handler
require_once SMARTLINK_AI_PATH . 'includes/broken-links.php';

// Initialize broken links handler
add_action('plugins_loaded', ['Smart_AI_Linker_Broken_Links', 'init']);

        // Store the links that were added
        $added_links = [];
        foreach ($used_anchors as $anchor) {
            foreach ($links as $link) {
                if ($link['anchor'] === $anchor) {
                    $added_links[] = $link;
                    break;
                }
            }
        }
        
        if (!empty($added_links)) {
            update_post_meta($post_ID, '_smart_ai_linker_added_links', $added_links);
            error_log('[Smart AI] Successfully updated post with ' . count($added_links) . ' new links');
            
            // Verify the update was successful
            $updated_post = get_post($post_ID);
            if ($updated_post) {
                error_log('[Smart AI] Verified update - new content length: ' . strlen($updated_post->post_content));
                error_log('[Smart AI] First 200 chars of updated content: ' . substr($updated_post->post_content, 0, 200));
            } else {
                error_log('[Smart AI] Could not verify update - post not found');
            }
            
            return true;
        }
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
