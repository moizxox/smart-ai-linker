<?php
/**
 * DeepSeek API Client
 * 
 * @package SmartAILinker
 */

// Check if required extensions are loaded
if (!extension_loaded('curl')) {
    error_log('[Smart AI] cURL extension is required but not loaded');
    return [];
}

if (!extension_loaded('json')) {
    error_log('[Smart AI] JSON extension is required but not loaded');
    return [];
}

// Define cURL constants if not defined (for IDE support)
if (!defined('CURLOPT_URL')) {
    define('CURLOPT_URL', 10002);
    define('CURLOPT_RETURNTRANSFER', 19913);
    define('CURLOPT_POST', 47);
    define('CURLOPT_POSTFIELDS', 10015);
    define('CURLOPT_HTTPHEADER', 10023);
    define('CURLOPT_TIMEOUT', 13);
    define('CURLOPT_SSL_VERIFYPEER', 64);
    define('CURLOPT_SSL_VERIFYHOST', 81);
    define('CURLINFO_HTTP_CODE', 2097154);
}

// Make sure Exception class is available
if (!class_exists('Exception')) {
    class_alias('\Exception', 'Exception');
}

if (!defined('ABSPATH')) {
    exit;
}

// Define constants if not already defined
if (!defined('JSON_ERROR_NONE')) {
    define('JSON_ERROR_NONE', 0);
}

if (!defined('PREG_SET_ORDER')) {
    define('PREG_SET_ORDER', 1);
}

if (!defined('FILTER_VALIDATE_URL')) {
    define('FILTER_VALIDATE_URL', 273);
}

/**
 * DeepSeek API Integration
 */

/**
 * Get AI-powered internal link suggestions from DeepSeek API
 * 
 * @param string $content The post content to analyze
 * @param int $post_id The ID of the post being processed
 * @param string $post_type The post type (post/page)
 * @param array $priority_post_ids (optional) Array of post IDs to prioritize (e.g., from silo group)
 * @return array<array{anchor: string, url: string}>|WP_Error Array of link suggestions with 'anchor' and 'url' keys, or WP_Error on failure
 */
function smart_ai_linker_get_ai_link_suggestions($content, $post_id, $post_type = 'post', $priority_post_ids = []) {
    // Check if Exception class exists
    if (!class_exists('Exception')) {
        error_log('[Smart AI] Required Exception class not found');
        return [];
    }
    
    // Get API key from options
    $api_key = get_option('smart_ai_linker_api_key', '');
    
    if (empty($api_key)) {
        $error_msg = 'DeepSeek API key not set in plugin settings';
        error_log('[Smart AI] ' . $error_msg);
        
        // Add admin notice if in admin area
        if (is_admin() && !wp_doing_ajax()) {
            add_action('admin_notices', function() use ($error_msg) {
                ?>
                <div class="notice notice-error">
                    <p><strong>Smart AI Linker:</strong> <?php echo esc_html($error_msg); ?>. 
                    <a href="<?php echo esc_url(admin_url('admin.php?page=smart-ai-linker')); ?>">Configure the plugin</a>.</p>
                </div>
                <?php
            });
        }
        return [];
    }
    
    // Check if cURL is available
    if (!function_exists('curl_init')) {
        error_log('[Smart AI] cURL is not available on this server');
        return [];
    }

    // Get site information for context
    $site_url = rtrim(site_url(), '/');
    $site_name = get_bloginfo('name');
    $post_title = get_the_title($post_id);
    
    // Get existing links to avoid suggesting duplicates
    $existing_links = get_post_meta($post_id, '_smart_ai_linker_added_links', true) ?: [];
    
    // Handle both array and object formats for existing links
    $existing_urls = [];
    if (!empty($existing_links)) {
        foreach ($existing_links as $link) {
            if (is_array($link) && isset($link['url'])) {
                $existing_urls[] = $link['url'];
            } elseif (is_object($link) && isset($link->url)) {
                $existing_urls[] = $link->url;
            }
        }
    }
    
    // Get current post type
    $current_post_type = get_post_type($post_id);
    
    // Set up query args for getting linkable posts/pages
    $query_args = [
        'post_status' => 'publish',
        'posts_per_page' => 200, // Increased to get more content for better suggestions
        'exclude' => [$post_id], // Exclude current post
        'fields' => 'ids',
        'orderby' => 'date',
        'order' => 'DESC',
        'post_parent__in' => [0], // Include both top-level and child pages
        'suppress_filters' => false, // Ensure all filters are applied
    ];
    
    // If current post is a page, only get other pages
    // If current post is a post, get both posts and pages
    $query_args['post_type'] = ($current_post_type === 'page') ? 'page' : ['post', 'page'];
    
    // If priority_post_ids (silo group) is provided, fetch those first
    $priority_titles = [];
    if (!empty($priority_post_ids) && is_array($priority_post_ids)) {
        $priority_posts = get_posts([
            'post__in' => $priority_post_ids,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'post__in',
            'post_type' => $query_args['post_type'],
        ]);
        foreach ($priority_posts as $pid) {
            $priority_titles[] = get_the_title($pid) . ' (' . get_permalink($pid) . ')';
        }
        // Remove these from the main candidate list
        $query_args['exclude'] = array_merge($query_args['exclude'], $priority_post_ids);
    }

    // Get the posts/pages for linking (excluding priority ones)
    $existing_posts = get_posts($query_args);
    
    // If no posts found, return empty array
    if (empty($existing_posts)) {
        error_log('[Smart AI] No other posts found to link to');
        return [];
    }
    
    $post_titles = [];
    foreach ($existing_posts as $pid) {
        $post_titles[] = get_the_title($pid) . ' (' . get_permalink($pid) . ')';
    }
    
    // Prepare the prompt with silo priority
    $prompt = "You are an expert content strategist helping to improve internal linking for a WordPress website. Follow these guidelines carefully:\n\n" .
             "WEBSITE: {$site_name} ({$site_url})\n" .
             "CURRENT POST TITLE: {$post_title}\n\n";
    if (!empty($priority_titles)) {
        $prompt .= "PRIORITY: The following posts/pages are in the same silo group as the current post. Suggest links to these first if relevant.\n" .
                  implode("\n", $priority_titles) . "\n\n";
    }
    $prompt .= "AVAILABLE PAGES/POSTS TO LINK TO (format: Title - URL):\n" . 
             implode("\n", array_slice($post_titles, 0, 50)) . 
             (count($post_titles) > 50 ? "\n...and " . (count($post_titles) - 50) . " more" : "") . 
             "\n\n" .
             "CRITICAL INSTRUCTIONS:\n" .
             "1. Analyze the content and suggest 7-10 relevant internal links.\n" .
             "2. " . ($current_post_type === 'page' ? 'Only link to other PAGES (not posts). ' : 'You may link to both PAGES and POSTS. ') . "\n" .
             "3. Choose anchor text that:\n" .
             "   - Is 2-4 words long\n" .
             "   - Appears naturally in the content\n" .
             "   - Is not a proper noun or common phrase\n" .
             "   - Doesn't contain special characters, quotes, or HTML tags\n" .
             "4. Only suggest links that provide clear value to the reader.\n" .
             "5. Never suggest linking the same URL more than once.\n" .
             "6. Ensure the link makes sense in context and adds value.\n" .
             "7. PRIORITIZE links to posts/pages in the same silo group if provided above.\n";
    if ($current_post_type !== 'page') {
        $prompt .= "8. Include at least 2 links to PAGES (not posts) if possible.\n";
    }
    $prompt .= "9. Include a good mix of both post and page links when relevant.\n\n" .
             "IMPORTANT: Your response MUST be a valid JSON array of objects. Each object MUST have 'anchor' and 'url' keys.\n" .
             "DO NOT include any text before or after the JSON array.\n" .
             "DO NOT include code block markers (```json or ```).\n" .
             "DO NOT include any explanations or notes.\n\n" .
             "Example response:\n" .
             "[{\"anchor\": \"content strategy guide\", \"url\": \"https://example.com/strategy/\"},\
" .
             " {\"anchor\": \"WordPress optimization\", \"url\": \"https://example.com/optimize/\"}]\n\n" .
             "Now analyze this content and provide your response:\n" .
             substr($content, 0, 15000); // Increased content limit for better context and analysis

    // Prepare the API request
    $api_url = 'https://api.deepseek.com/v1/chat/completions';
    
    $headers = [
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
        'Accept' => 'application/json',
    ];

    $body = [
        'model' => 'deepseek-chat',
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are an expert content strategist specializing in natural internal linking. You help create meaningful connections between content while maintaining readability and user experience.'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => 2000,
        'temperature' => 0.3, // Lower temperature for more predictable, focused results
        'top_p' => 0.8,
        'frequency_penalty' => 0.5, // Discourage repetition
        'presence_penalty' => 0.5, // Encourage topic variety
    ];

    // Log the API request (without the API key)
    $loggable_body = $body;
    $loggable_body['messages'][1]['content'] = substr($loggable_body['messages'][1]['content'], 0, 200) . '...';
    $loggable_headers = $headers;
    if (isset($loggable_headers['Authorization'])) {
        $loggable_headers['Authorization'] = 'Bearer ' . substr($api_key, 0, 4) . '...';
    }
    
    // Set up the request arguments
    $args = [
        'method'      => 'POST',
        'timeout'     => 120, // 2 minutes timeout
        'sslverify'   => false, // Bypass SSL verification if needed
        'redirection' => 5,
        'httpversion' => '1.1',
        'blocking'    => true,
        'headers'     => $headers,
        'body'        => json_encode($body),
        'data_format' => 'body',
    ];
    
    // Log request details
    error_log('[Smart AI] Sending request to DeepSeek API: ' . print_r([
        'url' => $api_url,
        'headers' => $loggable_headers,
        'body' => $loggable_body,
        'args' => array_merge($args, ['body' => $loggable_body])
    ], true));

    // Make the API request with error handling
    $response = wp_remote_post($api_url, $args);
    
    // Log response details
    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = wp_remote_retrieve_body($response);
    $response_headers = wp_remote_retrieve_headers($response);
    
    error_log('[Smart AI] DeepSeek API response code: ' . $response_code);
    error_log('[Smart AI] DeepSeek API response headers: ' . print_r($response_headers, true));
    error_log('[Smart AI] DeepSeek API response body: ' . $response_body);
    
    // Check for errors in the WordPress HTTP API
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log('[Smart AI] WordPress HTTP API request failed: ' . $error_message);
        
        // Try a direct cURL request as a fallback
        error_log('[Smart AI] Attempting direct cURL request as fallback...');
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($body),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0
        ]);
        
        $response_body = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            error_log('[Smart AI] cURL request failed: ' . $curl_error);
            return [];
        }
        
        // Process the direct cURL response
        $response = [
            'response' => ['code' => $http_code],
            'body' => $response_body
        ];
    }

    // Get the response code and body
    $response_code = is_array($response) ? $response['response']['code'] : 0;
    $response_body = is_array($response) ? $response['body'] : '';
    
    // Try to decode the response body
    $response_data = json_decode($response_body, true);
    
    // Log a truncated version of the response for debugging
    $log_response = $response_data;
    
    // Check if we have a valid response with choices
    if (!empty($response_data['choices'][0]['message']['content'])) {
        $ai_response = $response_data['choices'][0]['message']['content'];
        
        // Log the raw AI response (truncated for logs)
        error_log('[Smart AI] Raw AI response: ' . substr($ai_response, 0, 500) . 
                 (strlen($ai_response) > 500 ? '...' : ''));
        
        // Initialize matches array
        $matches = [];
        
        // Extract JSON from markdown code block if present
        if (preg_match('/```(?:json\n)?(.*?)```/s', $ai_response, $matches) && !empty($matches[1])) {
            $ai_response = trim($matches[1]);
            error_log('[Smart AI] Extracted JSON from code block: ' . $ai_response);
        }
        
        // Try to parse the JSON
        $suggestions = json_decode($ai_response, true);
        
        // If JSON parsing failed, try to extract JSON array directly
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to find a JSON array in the response
            if (preg_match('/\[(?:\s*\{.*?\}\s*,?\s*)+\]/s', $ai_response, $matches) && !empty($matches[0])) {
                $suggestions = json_decode($matches[0], true);
                error_log('[Smart AI] Extracted JSON array from response');
            }
        }
        
        // If we still don't have valid suggestions, try to fix common JSON issues
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($suggestions)) {
            error_log('[Smart AI] Initial JSON parse failed, attempting to fix common issues');
            
            // Try to fix common JSON issues
            $ai_response = trim($ai_response);
            
            // Remove any trailing commas before closing brackets/braces
            $ai_response = preg_replace('/,\s*([\}\]])/m', '$1', $ai_response);
            
            // Fix any unescaped quotes within strings
            $ai_response = preg_replace_callback('/"([^"]*?)"/', function($m) {
                return '"' . str_replace('"', '\\"', $m[1]) . '"';
            }, $ai_response);
            
            // Try parsing again
            $suggestions = json_decode($ai_response, true);
            
            // If still failing, try to extract just the array portion
            if (json_last_error() !== JSON_ERROR_NONE) {
                if (preg_match('/\[(?:\s*\{.*?\}\s*,?\s*)+\]/s', $ai_response, $matches) && !empty($matches[0])) {
                    $suggestions = json_decode($matches[0], true);
                }
            }
            
            // If still failing, try a more aggressive approach
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($suggestions)) {
                // Try to clean up the response more aggressively
                $ai_response = preg_replace('/[\r\n\t]/', ' ', $ai_response);
                $ai_response = preg_replace('/\s+/', ' ', $ai_response);
                
                // Try to find JSON array with more flexible regex
                if (preg_match('/\[.*?\]/s', $ai_response, $matches)) {
                    $cleaned_json = $matches[0];
                    // Remove any trailing commas
                    $cleaned_json = preg_replace('/,\s*([}\]])/m', '$1', $cleaned_json);
                    $suggestions = json_decode($cleaned_json, true);
                }
            }
            
            // If we still don't have valid suggestions, log and return empty array
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($suggestions)) {
                error_log('[Smart AI] Failed to parse JSON response after fixes: ' . json_last_error_msg());
                error_log('[Smart AI] Response content: ' . substr($ai_response, 0, 500));
                return [];
            }
        }
        
        // Filter out any suggestions that point to the current post or are empty
        $filtered_suggestions = [];
        $current_post_url = trailingslashit(urldecode(get_permalink($post_id)));
        $current_post_url = strtok($current_post_url, '?#');
        $post_slug = get_post_field('post_name', $post_id);
        
        foreach ($suggestions as $suggestion) {
            if (empty($suggestion['url']) || empty($suggestion['anchor'])) {
                continue;
            }
            
            // Normalize URLs for comparison
            $suggestion_url = trailingslashit(urldecode($suggestion['url']));
            $suggestion_url = strtok($suggestion_url, '?#');
            
            // Check if this is a link to the current post
            $is_same_post = ($suggestion_url === $current_post_url) || 
                           ($post_slug && strpos($suggestion_url, $post_slug) !== false);
            
            if ($is_same_post) {
                error_log('[Smart AI] Filtered out self-referential link: ' . $suggestion_url);
                continue;
            }
            
            $filtered_suggestions[] = [
                'anchor' => $suggestion['anchor'],
                'url' => $suggestion_url
            ];
        }
        
        error_log('[Smart AI] Successfully parsed ' . count($filtered_suggestions) . ' link suggestions');
        return $filtered_suggestions;
    } else {
        error_log('[Smart AI] Failed to parse JSON response: ' . json_last_error_msg());
    }
    
    // Log the response data for debugging if we get here
    error_log('[Smart AI] Invalid or empty response data: ' . print_r($response_data, true));
    
    // Log the error for admin notice if needed
    if (is_admin() && !wp_doing_ajax()) {
        add_action('admin_notices', function() {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong>Smart AI Linker Error:</strong> Failed to get valid response from DeepSeek API. Please check the error log for details.</p>
            </div>
            <?php
        });
    }
    
    return [];
}
