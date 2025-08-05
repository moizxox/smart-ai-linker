<?php
/**
 * Test Bulk Processing Functionality
 * 
 * This file tests the bulk processing AJAX handlers and functionality
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Test function to verify bulk processing handlers
function test_bulk_processing_handlers() {
    echo "<h2>Testing Bulk Processing Handlers</h2>";
    
    // Test 1: Check if AJAX handlers are registered
    echo "<h3>Test 1: AJAX Handler Registration</h3>";
    
    global $wp_filter;
    $ajax_handlers = [
        'wp_ajax_smart_ai_bulk_get_unprocessed',
        'wp_ajax_smart_ai_bulk_start', 
        'wp_ajax_smart_ai_bulk_next',
        'wp_ai_bulk_stop',
        'wp_ajax_smart_ai_bulk_status'
    ];
    
    foreach ($ajax_handlers as $handler) {
        if (isset($wp_filter[$handler])) {
            echo "✅ {$handler} is registered<br>";
        } else {
            echo "❌ {$handler} is NOT registered<br>";
        }
    }
    
    // Test 2: Check if bulk processing functions exist
    echo "<h3>Test 2: Function Existence</h3>";
    
    $functions = [
        'smart_ai_linker_process_single_post',
        'smart_ai_linker_bulk_action_handler'
    ];
    
    foreach ($functions as $function) {
        if (function_exists($function)) {
            echo "✅ {$function} exists<br>";
        } else {
            echo "❌ {$function} does NOT exist<br>";
        }
    }
    
    // Test 3: Check if options are properly managed
    echo "<h3>Test 3: Options Management</h3>";
    
    // Test setting options
    update_option('smart_ai_linker_bulk_queue', [1, 2, 3]);
    update_option('smart_ai_linker_bulk_progress', [
        'total' => 3,
        'processed' => 0,
        'skipped' => 0,
        'errors' => []
    ]);
    
    $queue = get_option('smart_ai_linker_bulk_queue', []);
    $progress = get_option('smart_ai_linker_bulk_progress', []);
    
    if (!empty($queue) && count($queue) === 3) {
        echo "✅ Queue options working correctly<br>";
    } else {
        echo "❌ Queue options not working<br>";
    }
    
    if (isset($progress['total']) && $progress['total'] === 3) {
        echo "✅ Progress options working correctly<br>";
    } else {
        echo "❌ Progress options not working<br>";
    }
    
    // Clean up test data
    delete_option('smart_ai_linker_bulk_queue');
    delete_option('smart_ai_linker_bulk_progress');
    
    echo "<h3>Test Complete!</h3>";
    echo "<p>If you see mostly ✅ marks, the bulk processing functionality is working correctly.</p>";
}

// Add test to admin menu if in debug mode
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('admin_menu', function() {
        add_submenu_page(
            'smart-ai-linker',
            'Test Bulk Processing',
            'Test Bulk Processing',
            'manage_options',
            'test-bulk-processing',
            function() {
                echo '<div class="wrap">';
                echo '<h1>Bulk Processing Test</h1>';
                test_bulk_processing_handlers();
                echo '</div>';
            }
        );
    });
} 