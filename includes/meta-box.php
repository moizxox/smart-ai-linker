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
// Clear Links AJAX removed; clearing is now automatic before reprocessing

/**
 * Add meta box to post editor
 */
function smart_ai_linker_add_meta_box()
{
    $post_types = get_option('smart_ai_linker_post_types', array('post', 'page'));

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
function smart_ai_linker_meta_box_callback($post)
{
    // Add a nonce field
    wp_nonce_field('smart_ai_linker_meta_box', 'smart_ai_linker_meta_box_nonce');

    // Check if links have been generated for this post
    $processed = get_post_meta($post->ID, '_smart_ai_linker_processed', true);
    $suggestions = get_post_meta($post->ID, '_smart_ai_linker_suggestions', true);
    $added_links = get_post_meta($post->ID, '_smart_ai_linker_added_links', true);

    // Ensure added_links is an array
    if (!is_array($added_links)) {
        $added_links = array();
    }

?>
    <div id="smart-ai-linker-meta-box">
        <?php if ($processed) : ?>
            <p>Links were automatically added to this post on <?php echo esc_html($processed); ?>.</p>

            <?php if (!empty($added_links)) : ?>
                <h4>Added Links:</h4>
                <ul>
                    <?php
                    $actually_added = array();
                    $post_content = get_post_field('post_content', $post->ID);

                    // Only show links that are actually in the post content
                    foreach ($added_links as $link) {
                        // Handle both array and object formats
                        if (is_array($link)) {
                            $anchor = isset($link['anchor']) ? $link['anchor'] : (isset($link['anchor_text']) ? $link['anchor_text'] : '');
                            $url = isset($link['url']) ? $link['url'] : '';
                        } else {
                            $anchor = isset($link->anchor) ? $link->anchor : (isset($link->anchor_text) ? $link->anchor_text : '');
                            $url = isset($link->url) ? $link->url : '';
                        }

                        if ($anchor && $url) {
                            // Check if this link actually exists in the post content
                            $link_pattern = '/<a[^>]*href=["\']' . preg_quote($url, '/') . '["\'][^>]*>' . preg_quote($anchor, '/') . '<\/a>/i';
                            if (preg_match($link_pattern, $post_content)) {
                                $actually_added[] = array('anchor' => $anchor, 'url' => $url);
                            }
                        }
                    }

                    if (!empty($actually_added)) :
                        foreach ($actually_added as $link) : ?>
                            <li>
                                <a href="<?php echo esc_url($link['url']); ?>" target="_blank">
                                    <?php echo esc_html($link['anchor']); ?>
                                </a>
                            </li>
                        <?php endforeach;
                    else : ?>
                        <li><em>No links were successfully inserted into the content.</em></li>
                    <?php endif; ?>
                </ul>

                <?php if (count($actually_added) !== count($added_links)) : ?>
                    <p><small><em>Note: <?php echo count($added_links) - count($actually_added); ?> suggested links could not be inserted because the anchor text was not found in the content.</em></small></p>
                <?php endif; ?>
            <?php endif; ?>
        <?php else : ?>
            <p>No links have been generated for this post yet.</p>
        <?php endif; ?>

        <div class="smart-ai-linker-actions">
            <button type="button" id="smart-ai-linker-generate" class="button button-primary">
                <?php _e('Generate Links', 'smart-ai-linker'); ?>
            </button>
            <span class="spinner"></span>

            <?php // Clear Links button removed; clearing occurs automatically on reprocessing 
            ?>
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
function smart_ai_linker_admin_scripts($hook)
{
    global $post;

    // Only load on post edit screen
    if (!in_array($hook, array('post.php', 'post-new.php'))) {
        return;
    }

    // Check post type
    $post_types = get_option('smart_ai_linker_post_types', array('post', 'page'));
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

    // Include jQuery UI for Tooltip functionality
    wp_enqueue_script('jquery-ui-tooltip');

    // Localize script with AJAX URL and nonce
    wp_localize_script('smart-ai-linker-admin', 'smartAILinker', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('smart_ai_linker_nonce'),
        'postId'  => $post->ID,
        'i18n'    => array(
            'generating'   => __('Generating links...', 'smart-ai-linker'),
            'error'        => __('An error occurred. Please try again.', 'smart-ai-linker'),
            'success'      => __('Links generated successfully! Refreshing...', 'smart-ai-linker'),
            'clearing'     => __('Clearing links...', 'smart-ai-linker'),
            'clearConfirm' => __('Are you sure you want to clear all generated links? This cannot be undone.', 'smart-ai-linker'),
        )
    ));
}

/**
 * Handle AJAX request to generate links
 */
function smart_ai_linker_ajax_generate_links()
{
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'smart_ai_linker_nonce')) {
        wp_send_json_error('Invalid nonce');
    }

    // Check user capabilities
    if (!current_user_can('edit_post', $_POST['post_id'])) {
        wp_send_json_error('Insufficient permissions');
    }

    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Post not found');
    }

    // Clear existing data first
    delete_post_meta($post_id, '_smart_ai_linker_processed');
    delete_post_meta($post_id, '_smart_ai_linker_suggestions');
    delete_post_meta($post_id, '_smart_ai_linker_added_links');

    // Get content without shortcodes and tags
    $content = wp_strip_all_tags(strip_shortcodes($post->post_content));

    if (empty($content)) {
        wp_send_json_error('Post content is empty');
    }

    // Get silo post IDs for priority linking
    $silo_post_ids = array();
    if (class_exists('Smart_AI_Linker_Silos')) {
        $silo_instance = Smart_AI_Linker_Silos::get_instance();
        $post_silos = $silo_instance->get_post_silos($post_id);
        if (!empty($post_silos)) {
            global $wpdb;
            $silo_ids = array();
            foreach ($post_silos as $silo) {
                $silo_ids[] = is_object($silo) ? $silo->id : $silo['id'];
            }
            $placeholders = implode(',', array_fill(0, count($silo_ids), '%d'));
            $query_params = array_merge($silo_ids, array($post_id));
            $query = $wpdb->prepare(
                "SELECT post_id FROM {$silo_instance->silo_relationships} WHERE silo_id IN ($placeholders) AND post_id != %d",
                $query_params
            );
            $silo_post_ids = $wpdb->get_col($query);
        }
    }

    // Get AI suggestions with post type and silo context
    $suggestions = smart_ai_linker_get_ai_link_suggestions($content, $post_id, $post->post_type, $silo_post_ids);

    if (empty($suggestions)) {
        wp_send_json_error('No link suggestions were generated');
    }

    // Limit the number of links based on settings
    $max_links = max(7, (int) get_option('smart_ai_linker_max_links', 7));
    $suggestions = array_slice($suggestions, 0, $max_links);

    // Insert links into the post
    $result = smart_ai_linker_insert_links_into_post($post_id, $suggestions);

    if ($result) {
        // Mark as processed (links are already stored by insert_links_into_post function)
        update_post_meta($post_id, '_smart_ai_linker_processed', current_time('mysql'));

        // Get the actual inserted links count from content
        $count = smart_ai_linker_count_actual_links($post_id);

        wp_send_json_success(array(
            'message' => 'Links generated successfully',
            'count' => $count
        ));
    } else {
        wp_send_json_error('Failed to insert links into the post');
    }
}

/**
 * Handle AJAX request to clear links
 */
// Removed: smart_ai_linker_ajax_clear_links (clearing now occurs automatically in processing flow)
