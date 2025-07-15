<?php
/**
 * Test file for Smart AI Linker plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Smart_AI_Linker_Test {
    public function __construct() {
        // Add test menu item
        add_action('admin_menu', [$this, 'add_test_page']);
    }

    public function add_test_page() {
        add_menu_page(
            'Smart AI Linker Test',
            'AI Linker Test',
            'manage_options',
            'smart-ai-linker-test',
            [$this, 'render_test_page']
        );
    }

    public function render_test_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle test form submission
        if (isset($_POST['test_ai_linking'])) {
            $this->run_test();
            return;
        }

        // Display test form
        ?>
        <div class="wrap">
            <h1>Smart AI Linker Test</h1>
            <p>This test will create a test post and attempt to add internal links to it.</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('test_ai_linking', 'ai_linker_test_nonce'); ?>
                <p>
                    <label for="test_content">Test Content:</label><br>
                    <textarea id="test_content" name="test_content" rows="10" style="width: 100%;">
This is a test post for the Smart AI Linker plugin. 
It contains some sample content that we'll use to test the internal linking functionality.

WordPress is a popular content management system that powers millions of websites.
You can use it to create blogs, business websites, and even e-commerce stores.

PHP is the programming language that WordPress is built with.
It's a powerful language for web development.

Plugins extend the functionality of WordPress.
Themes control the appearance of your site.

Let's see if the plugin can find relevant internal links in this content.
                    </textarea>
                </p>
                <p>
                    <input type="submit" name="test_ai_linking" class="button button-primary" value="Run Test">
                </p>
            </form>
        </div>
        <?php
    }

    private function run_test() {
        if (!current_user_can('manage_options') || 
            !isset($_POST['ai_linker_test_nonce']) || 
            !wp_verify_nonce($_POST['ai_linker_test_nonce'], 'test_ai_linking')) {
            wp_die('Security check failed');
        }

        $test_content = isset($_POST['test_content']) ? sanitize_textarea_field($_POST['test_content']) : '';
        
        if (empty($test_content)) {
            wp_die('Please enter some test content');
        }

        // Create a test post
        $post_id = wp_insert_post([
            'post_title'   => 'Test Post for AI Linker - ' . current_time('mysql'),
            'post_content' => $test_content,
            'post_status'  => 'publish',
            'post_type'    => 'post',
        ]);

        if (is_wp_error($post_id)) {
            wp_die('Error creating test post: ' . $post_id->get_error_message());
        }

        // Manually trigger the link generation
        $post = get_post($post_id);
        
        // Include necessary files if not already loaded
        if (!function_exists('smart_ai_linker_generate_internal_links')) {
            require_once SMARTLINK_AI_PATH . 'includes/internal-linking.php';
        }
        
        // Call the function with the correct number of arguments
        // The third argument is for backward compatibility with WP's save_post hook
        smart_ai_linker_generate_internal_links($post_id, $post, true);

        // Get the updated post
        $updated_post = get_post($post_id);
        $updated_content = $updated_post->post_content;

        // Display results
        ?>
        <div class="wrap">
            <h1>Test Results</h1>
            
            <h2>Original Content</h2>
            <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                <?php echo wpautop(esc_html($test_content)); ?>
            </div>

            <h2>Content with Links</h2>
            <div style="background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin-bottom: 20px;">
                <?php echo wpautop($updated_content); ?>
            </div>

            <h2>Raw HTML</h2>
            <textarea style="width: 100%; height: 200px; font-family: monospace;"><?php echo esc_textarea($updated_content); ?></textarea>
            
            <p>
                <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" class="button">Edit Test Post</a>
                <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="button" target="_blank">View Test Post</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=smart-ai-linker-test')); ?>" class="button">Run Another Test</a>
            </p>
        </div>
        <?php
    }
}

// Initialize the test class
if (defined('WP_DEBUG') && WP_DEBUG) {
    new Smart_AI_Linker_Test();
}
