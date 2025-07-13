<?php
if (!defined('ABSPATH'))
    exit;

/**
 * DeepSeek API Integration
 */

/**
 * Get AI-powered internal link suggestions from DeepSeek API
 * 
 * @param string $content The post content to analyze
 * @param int $post_id The ID of the post being processed
 * @return array Array of link suggestions with 'anchor' and 'url' keys
 */
function smart_ai_linker_get_ai_link_suggestions($content, $post_id) {
    // Get API key from options
    $api_key = get_option('smart_ai_linker_api_key', '');
    
    if (empty($api_key)) {
        error_log('[Smart AI] DeepSeek API key not set');
        // Add admin notice if in admin area
        if (is_admin()) {
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><strong>Smart AI Linker:</strong> DeepSeek API key is not set. Please <a href="<?php echo admin_url('admin.php?page=smart-ai-linker'); ?>">configure the plugin</a>.</p>
                </div>
                <?php
            });
        }
        return [];
    }

    // Get the site URL for context
    $site_url = site_url();
    $site_name = get_bloginfo('name');
    
    // Get the post title for context
    $post_title = get_the_title($post_id);
    
    // Get existing links to avoid suggesting duplicates
    $existing_links = get_post_meta($post_id, '_smart_ai_linker_added_links', true) ?: [];
    $existing_urls = array_column($existing_links, 'url');
    
    // Prepare the prompt with more context
    $prompt = "You are an expert content strategist helping to improve internal linking for a WordPress website.\n\n" .
             "Website: {$site_name} ({$site_url})\n" .
             "Current Post Title: {$post_title}\n\n" .
             "Analyze the following blog post content and suggest up to 7 relevant internal links. " . 
             "Only suggest links to other posts/pages on the same WordPress site. " .
             "Do not suggest links that already exist in the post. " .
             "For each suggestion, provide the anchor text and the full target URL. \n\n" .
             "Format your response as a valid JSON array of objects, each with 'anchor' and 'url' keys.\n" .
             "Example: [{\"anchor\": \"example text\", \"url\": \"https://example.com/post/\"}]\n\n" .
             "Content to analyze:\n" .
             $content;

    // Prepare the API request
    $api_url = 'https://api.deepseek.com/v1/chat/completions'; // Replace with actual DeepSeek endpoint
    
    $headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
    );

    $body = array(
        'model' => 'deepseek-chat', // Replace with actual model name
        'messages' => array(
            array(
                'role' => 'user',
                'content' => $prompt
            )
        ),
        'max_tokens' => 1000,
        'temperature' => 0.7,
    );

    // Make the API request with error handling
    $response = wp_remote_post($api_url, array(
        'headers' => $headers,
        'body' => json_encode($body),
        'timeout' => 30, // 30 seconds timeout
    ));

    // Check for errors
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('[Smart AI] DeepSeek API error: ' . $error_message);
        
        // Add admin notice for API errors
        if (is_admin()) {
            add_action('admin_notices', function() use ($error_message) {
                ?>
                <div class="notice notice-error">
                    <p><strong>Smart AI Linker API Error:</strong> <?php echo esc_html($error_message); ?></p>
                </div>
                <?php
            });
        }
        
        return [];
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if ($response_code !== 200) {
        error_log('[Smart AI] DeepSeek API error: ' . print_r($response_body, true));
        return [];
    }

    // Extract the AI response
    $ai_response = $response_body['choices'][0]['message']['content'] ?? '';
    
    // Try to parse the JSON response
    $suggestions = [];
    
    // First, try to extract JSON from the response
    if (preg_match('/\[.*\]/s', $ai_response, $matches)) {
        $json_str = $matches[0];
        $suggestions = json_decode($json_str, true);
        
        // If JSON parsing failed, try to clean up the response
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract anchor and URL pairs using regex as fallback
            preg_match_all('/"anchor"\s*:\s*"(.*?)"\s*,\s*"url"\s*:\s*"(.*?)"/', $ai_response, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $suggestions[] = [
                    'anchor' => $match[1],
                    'url' => $match[2]
                ];
            }
        }
    }

    // Validate and sanitize suggestions
    $valid_suggestions = [];
    
    if (is_array($suggestions)) {
        foreach ($suggestions as $suggestion) {
            if (!empty($suggestion['anchor']) && !empty($suggestion['url'])) {
                // Basic validation - check if URL is internal
                $url = esc_url_raw($suggestion['url']);
                $site_url = site_url();
                
                if (strpos($url, $site_url) === 0) {
                    $valid_suggestions[] = [
                        'anchor' => sanitize_text_field($suggestion['anchor']),
                        'url' => $url
                    ];
                    
                    // Limit to 7 suggestions
                    if (count($valid_suggestions) >= 7) {
                        break;
                    }
                }
            }
        }
    }

    // Store the suggestions in post meta for reference
    if (!empty($valid_suggestions)) {
        update_post_meta($post_id, '_smart_ai_linker_suggestions', $valid_suggestions);
    }

    return $valid_suggestions;
}
