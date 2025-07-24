<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles detection and replacement of broken links in posts
 */
class Smart_AI_Linker_Broken_Links {
    
    /**
     * Initialize the broken links handler
     */
    public static function init() {
        // Check for broken links when a post is published or updated
        add_action('save_post', [__CLASS__, 'check_post_for_broken_links'], 20, 3);
    }
    
    /**
     * Check a post for broken links and replace them if needed
     * 
     * @param int $post_id The post ID
     * @param WP_Post $post The post object
     * @param bool $update Whether this is an update
     */
    public static function check_post_for_broken_links($post_id, $post, $update) {
        // Skip if this is an autosave or revision
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Skip if user doesn't have permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Skip if this is not a published post or page
        if (!in_array($post->post_status, ['publish', 'future', 'draft', 'pending', 'private'], true)) {
            return;
        }
        
        // Get the post content
        $content = $post->post_content;
        
        // Find all links in the content
        $links = self::extract_links($content);
        
        if (empty($links)) {
            return; // No links to check
        }
        
        // Check each link
        $updated = false;
        foreach ($links as $link) {
            if (self::is_broken_link($link['url'])) {
                // Get a relevant replacement link
                $replacement = self::get_replacement_link($link['text'], $post_id);
                if ($replacement) {
                    // Replace the broken link in the content
                    $content = str_replace(
                        $link['full_match'],
                        sprintf('<a href="%s" class="smart-ai-link" title="%s">%s</a>',
                            esc_url($replacement['url']),
                            esc_attr($replacement['title']),
                            esc_html($link['text'])
                        ),
                        $content
                    );
                    $updated = true;
                } else {
                    // Remove the broken link, leave just the anchor text
                    $content = str_replace($link['full_match'], esc_html($link['text']), $content);
                    $updated = true;
                }
            }
        }
        
        // Update the post if we made changes
        if ($updated) {
            // Temporarily remove the save_post hook to prevent infinite loops
            remove_action('save_post', [__CLASS__, 'check_post_for_broken_links'], 20);
            
            // Update the post
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $content
            ]);
            
            // Re-add the hook
            add_action('save_post', [__CLASS__, 'check_post_for_broken_links'], 20, 3);
        }
    }
    
    /**
     * Extract all links from content
     * 
     * @param string $content The content to search in
     * @return array Array of links with their details
     */
    private static function extract_links($content) {
        $links = [];
        $matches = [];
        
        if (preg_match_all('/<a\s+([^>]*)href=(["\'])(.*?)\2([^>]*)>([^<]*)<\/a>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                if (isset($match[0]) && isset($match[3]) && isset($match[5])) {
                    $links[] = [
                        'full_match' => $match[0],
                        'url' => $match[3],
                        'text' => $match[5],
                        'attributes' => (isset($match[1]) ? $match[1] : '') . ' ' . (isset($match[4]) ? $match[4] : '')
                    ];
                }
            }
        }
        
        return $links;
    }
    
    /**
     * Check if a link is broken
     * 
     * @param string $url The URL to check
     * @return bool True if the link is broken
     */
    private static function is_broken_link($url) {
        // Skip if it's not a URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        // Check internal links as well
        $site_url = get_site_url();
        $is_internal = (strpos($url, $site_url) === 0);
        $response = wp_remote_head($url, [
            'timeout' => 5,
            'redirection' => 5,
            'sslverify' => false,
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
        ]);
        // Consider it broken if there's an error or status code is 4xx/5xx
        return is_wp_error($response) || ($response['response']['code'] >= 400);
    }
    
    /**
     * Get a relevant replacement link for broken links
     * 
     * @param string $anchor_text The anchor text of the broken link
     * @param int $current_post_id The ID of the current post
     * @return array|false Replacement link data or false if none found
     */
    private static function get_replacement_link($anchor_text, $current_post_id) {
        // Search for relevant posts/pages
        $args = [
            'post_type' => ['post', 'page'],
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'post__not_in' => [$current_post_id],
            's' => $anchor_text,
            'fields' => 'ids'
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $post_id = $query->posts[0];
            return [
                'url' => get_permalink($post_id),
                'title' => get_the_title($post_id)
            ];
        }
        
        return false;
    }
}

// Initialize the broken links handler
add_action('init', ['Smart_AI_Linker_Broken_Links', 'init']);
