<?php
/**
 * Test connection to DeepSeek API
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

// Get the API key
$api_key = get_option('smart_ai_linker_api_key', '');

// Test connection function
function test_deepseek_connection($api_key) {
    if (empty($api_key)) {
        return [
            'success' => false,
            'message' => 'API key is not set. Please configure it in the plugin settings.'
        ];
    }

    $api_url = 'https://api.deepseek.com/v1/chat/completions';
    
    // Test with a simple request
    $response = wp_remote_post($api_url, [
        'headers' => [
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key,
        ],
        'body' => json_encode([
            'model' => 'deepseek-chat',
            'messages' => [
                ['role' => 'user', 'content' => 'Hello']
            ],
            'max_tokens' => 10
        ]),
        'timeout' => 15,
        'sslverify' => false
    ]);

    if (is_wp_error($response)) {
        return [
            'success' => false,
            'message' => 'Connection failed: ' . $response->get_error_message()
        ];
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if ($response_code === 200) {
        return [
            'success' => true,
            'message' => 'Successfully connected to DeepSeek API!',
            'response' => $response_body
        ];
    } else {
        return [
            'success' => false,
            'message' => sprintf('API returned status code %d: %s', 
                $response_code,
                $response_body['error']['message'] ?? 'Unknown error'
            )
        ];
    }
}

// Run the test if form is submitted
$test_result = null;
if (isset($_POST['run_test'])) {
    check_admin_referer('test_deepseek_connection');
    $test_result = test_deepseek_connection($api_key);
}

// Display the test page
?>
<div class="wrap">
    <h1>DeepSeek API Connection Test</h1>
    
    <div class="card">
        <h2>Test Connection</h2>
        <p>This tool will test the connection to the DeepSeek API using your configured API key.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('test_deepseek_connection'); ?>
            <p>
                <strong>API Key:</strong> 
                <?php echo $api_key ? 'Configured' : 'Not configured'; ?>
            </p>
            <p>
                <input type="submit" name="run_test" class="button button-primary" value="Run Connection Test">
            </p>
        </form>
        
        <?php if ($test_result !== null): ?>
            <div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?>" style="margin-top: 20px; padding: 10px 15px;">
                <p><strong><?php echo $test_result['success'] ? 'Success!' : 'Error:'; ?></strong> 
                <?php echo esc_html($test_result['message']); ?></p>
                
                <?php if (isset($test_result['response'])): ?>
                    <details style="margin-top: 10px;">
                        <summary>View Response</summary>
                        <pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;">
                            <?php echo esc_html(print_r($test_result['response'], true)); ?>
                        </pre>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="card" style="margin-top: 20px;">
        <h2>Troubleshooting</h2>
        <p>If you're experiencing connection issues, please check the following:</p>
        <ol>
            <li>Make sure your API key is correct and has the necessary permissions.</li>
            <li>Check if your server can connect to api.deepseek.com (port 443).</li>
            <li>Verify that your server's firewall allows outbound HTTPS connections.</li>
            <li>Check your server's error logs for more detailed error messages.</li>
        </ol>
        <p>If the issue persists, please contact your hosting provider and share the error message above.</p>
    </div>
</div>

<style>
    .card {
        background: #fff;
        border: 1px solid #ccd0d4;
        box-shadow: 0 1px 1px rgba(0,0,0,.04);
        padding: 20px;
        margin-bottom: 20px;
    }
    .card h2 {
        margin-top: 0;
    }
</style>
