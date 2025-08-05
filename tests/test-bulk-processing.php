<?php
/**
 * Test Bulk Processing for Smart AI Linker
 * 
 * This script tests the bulk processing functionality to ensure it works correctly
 * with the new error handling and retry mechanisms.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Only run this in admin area
if (!is_admin()) {
    wp_die('This script can only be run from the WordPress admin area.');
}

// Check user capabilities
if (!current_user_can('manage_options')) {
    wp_die('You do not have sufficient permissions to access this page.');
}

// Include necessary files
require_once SMARTLINK_AI_PATH . 'includes/bulk-processing.php';
require_once SMARTLINK_AI_PATH . 'api/deepseek-client.php';

/**
 * Test the single post processing function
 */
function test_single_post_processing() {
    echo "<h2>Testing Single Post Processing</h2>";
    
    // Get a test post
    $test_posts = get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    if (empty($test_posts)) {
        echo "<p style='color: red;'>No test posts found. Please create a post first.</p>";
        return;
    }
    
    $test_post = $test_posts[0];
    echo "<p><strong>Testing with post:</strong> {$test_post->post_title} (ID: {$test_post->ID})</p>";
    
    // Test the processing function
    $result = smart_ai_linker_process_single_post($test_post->ID);
    
    echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>Processing Result:</h3>";
    echo "<p><strong>Status:</strong> " . esc_html($result['status']) . "</p>";
    echo "<p><strong>Reason:</strong> " . esc_html($result['reason']) . "</p>";
    echo "</div>";
    
    return $result;
}

/**
 * Test bulk processing with a small batch
 */
function test_bulk_processing() {
    echo "<h2>Testing Bulk Processing</h2>";
    
    // Get a few test posts
    $test_posts = get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 3,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    if (empty($test_posts)) {
        echo "<p style='color: red;'>No test posts found. Please create some posts first.</p>";
        return;
    }
    
    $post_ids = array_map(function($post) { return $post->ID; }, $test_posts);
    
    echo "<p><strong>Testing with posts:</strong></p>";
    echo "<ul>";
    foreach ($test_posts as $post) {
        echo "<li>{$post->post_title} (ID: {$post->ID})</li>";
    }
    echo "</ul>";
    
    // Test the bulk processing
    $start_time = microtime(true);
    
    $processed = 0;
    $skipped = 0;
    $errors = [];
    
    foreach ($post_ids as $post_id) {
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
    
    $end_time = microtime(true);
    $duration = round($end_time - $start_time, 2);
    
    echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>Bulk Processing Results:</h3>";
    echo "<p><strong>Duration:</strong> {$duration} seconds</p>";
    echo "<p><strong>Processed:</strong> {$processed}</p>";
    echo "<p><strong>Skipped:</strong> {$skipped}</p>";
    echo "<p><strong>Errors:</strong> " . count($errors) . "</p>";
    
    if (!empty($errors)) {
        echo "<p><strong>Error Details:</strong></p>";
        echo "<ul>";
        foreach (array_slice($errors, 0, 5) as $error) {
            echo "<li>" . esc_html($error) . "</li>";
        }
        if (count($errors) > 5) {
            echo "<li>... and " . (count($errors) - 5) . " more errors</li>";
        }
        echo "</ul>";
    }
    echo "</div>";
}

/**
 * Test API connection and response parsing
 */
function test_api_connection() {
    echo "<h2>Testing API Connection</h2>";
    
    // Get API key
    $api_key = get_option('smart_ai_linker_api_key', '');
    
    if (empty($api_key)) {
        echo "<p style='color: red;'>API key not configured. Please set it in the plugin settings.</p>";
        return false;
    }
    
    echo "<p><strong>API Key:</strong> Configured</p>";
    
    // Test with a simple content
    $test_content = "WordPress is a powerful content management system that allows users to create and manage websites easily. It provides a user-friendly interface for content creation and management.";
    $test_post_id = 1; // Dummy post ID for testing
    
    echo "<p><strong>Testing with content:</strong> " . substr($test_content, 0, 100) . "...</p>";
    
    try {
        $suggestions = smart_ai_linker_get_ai_link_suggestions($test_content, $test_post_id, 'post', []);
        
        echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>API Test Results:</h3>";
        
        if (empty($suggestions)) {
            echo "<p style='color: orange;'>No suggestions returned. This might be normal if no suitable content exists.</p>";
        } else {
            echo "<p style='color: green;'>Successfully received " . count($suggestions) . " suggestions.</p>";
            echo "<p><strong>Suggestions:</strong></p>";
            echo "<ul>";
            foreach (array_slice($suggestions, 0, 3) as $suggestion) {
                echo "<li>Anchor: " . esc_html($suggestion['anchor']) . " → URL: " . esc_html($suggestion['url']) . "</li>";
            }
            if (count($suggestions) > 3) {
                echo "<li>... and " . (count($suggestions) - 3) . " more</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
        
        return !empty($suggestions);
    } catch (Exception $e) {
        echo "<p style='color: red;'>API Error: " . esc_html($e->getMessage()) . "</p>";
        return false;
    }
}

/**
 * Test error handling and retry logic
 */
function test_error_handling() {
    echo "<h2>Testing Error Handling</h2>";
    
    // Test with invalid post ID
    $result = smart_ai_linker_process_single_post(999999);
    
    echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>Invalid Post Test:</h3>";
    echo "<p><strong>Status:</strong> " . esc_html($result['status']) . "</p>";
    echo "<p><strong>Reason:</strong> " . esc_html($result['reason']) . "</p>";
    echo "</div>";
    
    // Test with empty content
    $test_post = get_posts([
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    if (!empty($test_post)) {
        // Temporarily modify the post content to be very short
        $original_content = $test_post[0]->post_content;
        wp_update_post([
            'ID' => $test_post[0]->ID,
            'post_content' => 'Short'
        ]);
        
        $result = smart_ai_linker_process_single_post($test_post[0]->ID);
        
        echo "<div style='background: #f9f9f9; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h3>Short Content Test:</h3>";
        echo "<p><strong>Status:</strong> " . esc_html($result['status']) . "</p>";
        echo "<p><strong>Reason:</strong> " . esc_html($result['reason']) . "</p>";
        echo "</div>";
        
        // Restore original content
        wp_update_post([
            'ID' => $test_post[0]->ID,
            'post_content' => $original_content
        ]);
    }
}

// Run the tests
?>
<div class="wrap">
    <h1>Smart AI Linker - Bulk Processing Test</h1>
    
    <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
        <h2>Test Overview</h2>
        <p>This page tests the bulk processing functionality to ensure it works correctly with the new error handling and retry mechanisms.</p>
        
        <form method="post">
            <?php wp_nonce_field('test_bulk_processing'); ?>
            <p>
                <input type="submit" name="run_tests" class="button button-primary" value="Run All Tests">
                <input type="submit" name="test_single" class="button" value="Test Single Post">
                <input type="submit" name="test_bulk" class="button" value="Test Bulk Processing">
                <input type="submit" name="test_api" class="button" value="Test API Connection">
                <input type="submit" name="test_errors" class="button" value="Test Error Handling">
            </p>
        </form>
    </div>
    
    <?php
    if (isset($_POST['run_tests']) || isset($_POST['test_single']) || isset($_POST['test_bulk']) || isset($_POST['test_api']) || isset($_POST['test_errors'])) {
        check_admin_referer('test_bulk_processing');
        
        echo "<div style='background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px;'>";
        
        if (isset($_POST['run_tests']) || isset($_POST['test_api'])) {
            test_api_connection();
        }
        
        if (isset($_POST['run_tests']) || isset($_POST['test_errors'])) {
            test_error_handling();
        }
        
        if (isset($_POST['run_tests']) || isset($_POST['test_single'])) {
            test_single_post_processing();
        }
        
        if (isset($_POST['run_tests']) || isset($_POST['test_bulk'])) {
            test_bulk_processing();
        }
        
        echo "</div>";
    }
    ?>
    
    <div style="background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin-top: 20px;">
        <h2>Test Results Summary</h2>
        <p>After running the tests, you should see:</p>
        <ul>
            <li><strong>API Connection:</strong> Should show successful connection and response parsing</li>
            <li><strong>Error Handling:</strong> Should properly handle invalid posts and short content</li>
            <li><strong>Single Post Processing:</strong> Should process a single post with proper status reporting</li>
            <li><strong>Bulk Processing:</strong> Should process multiple posts with timing and error reporting</li>
        </ul>
        
        <h3>Expected Improvements</h3>
        <ul>
            <li>Better error handling with retry mechanisms</li>
            <li>Improved JSON parsing for AI responses</li>
            <li>Batch processing to prevent timeouts</li>
            <li>Detailed progress tracking and reporting</li>
            <li>Professional UI with real-time updates</li>
        </ul>
    </div>
</div> 