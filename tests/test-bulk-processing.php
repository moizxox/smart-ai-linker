<?php

/**
 * Test file for bulk processing functionality
 * 
 * @package SmartAILinker
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test the bulk processing functionality
 */
function test_bulk_processing_functionality()
{
    echo "<h2>Testing Bulk Processing Functionality</h2>";

    // Test 1: Check if cleanup function exists
    if (function_exists('smart_ai_linker_cleanup_stuck_processes')) {
        echo "<p style='color: green;'>✓ Cleanup function exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Cleanup function missing</p>";
    }

    // Test 2: Check if AJAX handlers are registered
    global $wp_filter;
    $ajax_handlers = [
        'wp_ajax_smart_ai_bulk_get_unprocessed',
        'wp_ajax_smart_ai_bulk_start',
        'wp_ajax_smart_ai_bulk_next',
        'wp_ajax_smart_ai_bulk_stop',
        'wp_ajax_smart_ai_bulk_status',
        'wp_ajax_smart_ai_bulk_get_processing_status'
    ];

    foreach ($ajax_handlers as $handler) {
        if (isset($wp_filter[$handler])) {
            echo "<p style='color: green;'>✓ {$handler} is registered</p>";
        } else {
            echo "<p style='color: red;'>✗ {$handler} is not registered</p>";
        }
    }

    // Test 3: Check database options
    $options_to_check = [
        'smart_ai_linker_bulk_queue',
        'smart_ai_linker_bulk_progress',
        'smart_ai_linker_current_processing'
    ];

    foreach ($options_to_check as $option) {
        $value = get_option($option);
        if ($value !== false) {
            echo "<p style='color: orange;'>⚠ {$option} exists in database</p>";
        } else {
            echo "<p style='color: green;'>✓ {$option} is not set (expected)</p>";
        }
    }

    // Test 4: Check if processing function exists
    if (function_exists('smart_ai_linker_process_single_post')) {
        echo "<p style='color: green;'>✓ Single post processing function exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Single post processing function missing</p>";
    }

    // Test 5: Check for unprocessed posts
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'fields' => 'ids',
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => '_smart_ai_linker_processed', 'compare' => 'NOT EXISTS'),
            array('key' => '_smart_ai_linker_processed', 'value' => '', 'compare' => '=')
        ),
    );

    $unprocessed = get_posts($args);
    echo "<p>Found " . count($unprocessed) . " unprocessed posts for testing</p>";

    // Test 6: Test AI client function
    if (function_exists('smart_ai_linker_get_ai_link_suggestions')) {
        echo "<p style='color: green;'>✓ AI client function exists</p>";

        // Test with a sample post
        if (!empty($unprocessed)) {
            $test_post_id = $unprocessed[0];
            $test_post = get_post($test_post_id);
            if ($test_post) {
                $test_content = wp_strip_all_tags($test_post->post_content);
                echo "<p>Testing AI suggestions for post ID: {$test_post_id}</p>";

                try {
                    $suggestions = smart_ai_linker_get_ai_link_suggestions($test_content, $test_post_id, $test_post->post_type);
                    if (!empty($suggestions) && is_array($suggestions)) {
                        echo "<p style='color: green;'>✓ AI suggestions working: " . count($suggestions) . " suggestions</p>";
                        foreach ($suggestions as $i => $suggestion) {
                            echo "<p style='font-size: 12px; margin-left: 20px;'>Suggestion " . ($i + 1) . ": anchor='{$suggestion['anchor']}', url='{$suggestion['url']}'</p>";
                        }
                    } else {
                        echo "<p style='color: orange;'>⚠ AI suggestions returned empty or invalid result</p>";
                    }
                } catch (Exception $e) {
                    echo "<p style='color: red;'>✗ AI suggestions error: " . $e->getMessage() . "</p>";
                }
            }
        }
    } else {
        echo "<p style='color: red;'>✗ AI client function missing</p>";
    }

    // Test 7: Test internal linking function
    if (function_exists('smart_ai_linker_insert_links_into_post')) {
        echo "<p style='color: green;'>✓ Internal linking function exists</p>";

        // Test with sample data
        $test_links = [
            ['anchor' => 'test link', 'url' => home_url('/test-page/')]
        ];

        if (!empty($unprocessed)) {
            $test_post_id = $unprocessed[0];
            try {
                $result = smart_ai_linker_insert_links_into_post($test_post_id, $test_links);
                if ($result === true) {
                    echo "<p style='color: green;'>✓ Internal linking function working</p>";
                } else {
                    echo "<p style='color: orange;'>⚠ Internal linking function returned: " . ($result ? 'true' : 'false') . "</p>";
                }
            } catch (Exception $e) {
                echo "<p style='color: red;'>✗ Internal linking error: " . $e->getMessage() . "</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>✗ Internal linking function missing</p>";
    }

    // Test 8: Check API key
    $api_key = get_option('smart_ai_linker_api_key', '');
    if (!empty($api_key)) {
        echo "<p style='color: green;'>✓ API key is set</p>";
    } else {
        echo "<p style='color: red;'>✗ API key is not set</p>";
    }

    // Test 9: Check required extensions
    $required_extensions = ['curl', 'json', 'mbstring'];
    foreach ($required_extensions as $ext) {
        if (extension_loaded($ext)) {
            echo "<p style='color: green;'>✓ {$ext} extension loaded</p>";
        } else {
            echo "<p style='color: red;'>✗ {$ext} extension not loaded</p>";
        }
    }

    // Test 10: Check WordPress debug mode
    if (defined('WP_DEBUG') && WP_DEBUG) {
        echo "<p style='color: green;'>✓ WordPress debug mode is enabled</p>";
    } else {
        echo "<p style='color: orange;'>⚠ WordPress debug mode is disabled (errors may not be visible)</p>";
    }

    // Test 11: Check if pages are enabled for internal linking
    $enabled_post_types = get_option('smart_ai_linker_post_types', array('post', 'page'));
    if (in_array('page', $enabled_post_types)) {
        echo "<p style='color: green;'>✓ Pages are enabled for internal linking</p>";
    } else {
        echo "<p style='color: red;'>✗ Pages are NOT enabled for internal linking</p>";
    }

    // Test 12: Check if save_post hook is registered for pages
    global $wp_filter;
    if (isset($wp_filter['save_post'])) {
        echo "<p style='color: green;'>✓ save_post hook is registered</p>";

        // Check if our function is hooked
        $has_hook = false;
        foreach ($wp_filter['save_post']->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                if (is_array($callback['function']) && is_object($callback['function'][0])) {
                    $function_name = $callback['function'][1];
                    if ($function_name === 'smart_ai_linker_generate_internal_links') {
                        $has_hook = true;
                        break 2;
                    }
                }
            }
        }

        if ($has_hook) {
            echo "<p style='color: green;'>✓ Internal linking function is hooked to save_post</p>";
        } else {
            echo "<p style='color: red;'>✗ Internal linking function is NOT hooked to save_post</p>";
        }
    } else {
        echo "<p style='color: red;'>✗ save_post hook is not registered</p>";
    }

    // Test 13: Check for pages with content
    $pages = get_posts(array(
        'post_type' => 'page',
        'post_status' => 'publish',
        'posts_per_page' => 5,
        'orderby' => 'date',
        'order' => 'DESC'
    ));

    if (!empty($pages)) {
        echo "<p style='color: green;'>✓ Found " . count($pages) . " published pages</p>";

        // Test with a sample page
        $test_page = $pages[0];
        $word_count = str_word_count(wp_strip_all_tags($test_page->post_content));
        $min_words = get_option('smart_ai_min_content_length', 10);

        echo "<p>Testing page ID: {$test_page->ID} ({$test_page->post_title})</p>";
        echo "<p>Page has {$word_count} words (minimum: {$min_words})</p>";

        if ($word_count >= $min_words) {
            echo "<p style='color: green;'>✓ Page has sufficient content for processing</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Page has insufficient content for processing</p>";
        }
    } else {
        echo "<p style='color: orange;'>⚠ No published pages found</p>";
    }

    echo "<h3>Test Summary</h3>";
    echo "<p>The bulk processing system includes:</p>";
    echo "<ul>";
    echo "<li>Background processing that continues after page reload</li>";
    echo "<li>Progress tracking with 100% accuracy</li>";
    echo "<li>Post type locking to prevent multiple simultaneous processes</li>";
    echo "<li>Automatic cleanup of stuck processes</li>";
    echo "<li>Real-time status updates</li>";
    echo "<li>Enhanced error handling and debugging</li>";
    echo "</ul>";

    echo "<h3>Debug Information</h3>";
    echo "<p>To enable detailed error logging, add this to your wp-config.php:</p>";
    echo "<pre>define('WP_DEBUG', true);\ndefine('WP_DEBUG_LOG', true);\ndefine('WP_DEBUG_DISPLAY', false);</pre>";
    echo "<p>Then check the debug.log file in wp-content/ for detailed error messages.</p>";
}

// Run the test if accessed directly
if (isset($_GET['test_bulk_processing'])) {
    test_bulk_processing_functionality();
}
