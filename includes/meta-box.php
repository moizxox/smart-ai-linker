<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Meta Box for Smart AI Linker
 * 
 * Adds a meta box to the post editor for manual link generation.
 */

// Add meta box to post editor
add_action('add_meta_boxes', 'smart_ai_linker_add_meta_box');
add_action('admin_enqueue_scripts', 'smart_ai_linker_admin_scripts');
add_action('wp_ajax_smart_ai_linker_generate_links', 'smart_ai_linker_ajax_generate_links');

/**
 * Add meta box to post editor
 */
function smart_ai_linker_add_meta_box() {
    $post_types = get_option('smart_ai_linker_post_types', array('post'));
    
    add_meta_box(
        'smart_ai_linker_meta_box',
        'Smart AI Linker',
        'smart_ai_linker_meta_box_callback',
        $post_types,
        'side',
        'high'
    );
}

/**
 * Meta box callback
 */
function smart_ai_linker_meta_box_callback($post) {
    // Add a nonce field
    wp_nonce_field('smart_ai_linker_meta_box', 'smart_ai_linker_meta_box_nonce');
    
    // Check if links have been generated for this post
    $processed = get_post_meta($post->ID, '_smart_ai_linker_processed', true);
    $suggestions = get_post_meta($post->ID, '_smart_ai_linker_suggestions', true);
    $added_links = get_post_meta($post->ID, '_smart_ai_linker_added_links', true);
    
    ?>
    <div id="smart-ai-linker-meta-box">
        <?php if ($processed) : ?>
            <p>Links were automatically added to this post on <?php echo esc_html($processed); ?>.</p>
            
            <?php if (!empty($added_links)) : ?>
                <h4>Added Links:</h4>
                <ul>
                    <?php foreach ($added_links as $link) : ?>
                        <li>
                            <a href="<?php echo esc_url($link['url']); ?>" target="_blank">
                                <?php echo esc_html($link['anchor']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php else : ?>
            <p>No links have been generated for this post yet.</p>
        <?php endif; ?>
        
        <div class="smart-ai-linker-actions">
            <button type="button" id="smart-ai-linker-generate" class="button button-primary">
                <?php _e('Generate Links', 'smart-ai-linker'); ?>
            </button>
            <span class="spinner"></span>
            
            <?php if ($processed) : ?>
                <button type="button" id="smart-ai-linker-clear" class="button button-link" 
                        style="margin-left: 10px; color: #a00;">
                    <?php _e('Clear Links', 'smart-ai-linker'); ?>
                </button>
            <?php endif; ?>
        </div>
        
        <div id="smart-ai-linker-message" style="margin-top: 10px;"></div>
    </div>
    
    <style>
        #smart-ai-linker-meta-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        #smart-ai-linker-meta-box li {
            margin-bottom: 5px;
            list-style-type: disc;
        }
        .smart-ai-linker-actions {
            margin: 15px 0;
        }
        .smart-ai-linker-actions .spinner {
            float: none;
            margin-top: 0;
        }
    </style>
    <?php
}

/**
 * Enqueue admin scripts
 */
function smart_ai_linker_admin_scripts($hook) {
    global $post;
    
    // Only load on post edit screen
    if (!in_array($hook, array('post.php', 'post-new.php'))) {
        return;
    }
    
    // Check post type
    $post_types = get_option('smart_ai_linker_post_types', array('post'));
    if (!in_array($post->post_type, (array)$post_types)) {
        return;
    }
    
    // Enqueue script
    wp_enqueue_script(
        'smart-ai-linker-admin',
        plugins_url('../assets/js/admin.js', __FILE__),
        array('jquery'),
        SMARTLINK_AI_VERSION,
        true
    );
    
    // Localize script with AJAX URL and nonce
    wp_localize_script('smart-ai-linker-admin', 'smartAILinker', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('smart_ai_linker_nonce'),
        'postId' => $post->ID,
        'i18n' => array(
            'generating' => __('Generating links...', 'smart-ai-linker'),
            'error' => __('An error occurred. Please try again.', 'smart-ai-linker'),
            'success' => __('Links generated successfully! Refreshing...', 'smart-ai-linker'),
            'clearing' => __('Clearing links...', 'smart-ai-linker'),
            'clearConfirm' => __('Are you sure you want to clear all generated links? This cannot be undone.', 'smart-ai-linker'),
        )
    ));
}

/**
 * Handle AJAX request to generate links
 */
function smart_ai_linker_ajax_generate_links() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smart_ai_linker_nonce')) {
        wp_send_json_error('Invalid nonce');
    }
    
    // Check user capabilities
    if (!current_user_can('edit_post', $_POST['post_id'])) {
        wp_send_json_error('Insufficient permissions');
    }
    
    $post_id = intval($_POST['post_id']);
    $action = isset($_POST['action_type']) ? $_POST['action_type'] : 'generate';
    
    if ($action === 'clear') {
        // Clear existing links
        delete_post_meta($post_id, '_smart_ai_linker_processed');
        delete_post_meta($post_id, '_smart_ai_linker_suggestions');
        delete_post_meta($post_id, '_smart_ai_linker_added_links');
        
        wp_send_json_success('Links cleared');
    } else {
        // Generate new links
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error('Post not found');
        }
        
        // Clear existing data
        delete_post_meta($post_id, '_smart_ai_linker_processed');
        delete_post_meta($post_id, '_smart_ai_linker_suggestions');
        delete_post_meta($post_id, '_smart_ai_linker_added_links');
        
        // Get content without shortcodes and tags
        $content = wp_strip_all_tags(strip_shortcodes($post->post_content));
        
        if (empty($content)) {
            wp_send_json_error('Post content is empty');
        }
        
        // Get AI suggestions
        $suggestions = smart_ai_linker_get_ai_link_suggestions($content, $post_id);
        
        if (empty($suggestions)) {
            wp_send_json_error('No link suggestions were generated');
        }
        
        // Insert links into the post
        $result = smart_ai_linker_insert_links_into_post($post_id, $suggestions);
        
        if ($result) {
            // Mark as processed
            update_post_meta($post_id, '_smart_ai_linker_processed', current_time('mysql'));
            wp_send_json_success('Links generated successfully');
        } else {
            wp_send_json_error('Failed to insert links into the post');
        }
    }
}
