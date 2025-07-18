<?php
if (!defined('ABSPATH')) {
    exit;
}

class Smart_AI_Linker_Silo_Structure {

    /**
     * Initialize the silo structure functionality
     */
    public static function init() {
        // Register custom taxonomy for silo groups
        add_action('init', [__CLASS__, 'register_silo_taxonomy']);
        
        // Add meta box for silo settings
        add_action('add_meta_boxes', [__CLASS__, 'add_silo_meta_box']);
        
        // Save silo settings
        add_action('save_post', [__CLASS__, 'save_silo_meta_box_data']);
    }

    /**
     * Register custom taxonomy for silo groups
     */
    public static function register_silo_taxonomy() {
        $labels = [
            'name'              => _x('Silo Groups', 'taxonomy general name', 'smart-ai-linker'),
            'singular_name'     => _x('Silo Group', 'taxonomy singular name', 'smart-ai-linker'),
            'search_items'      => __('Search Silo Groups', 'smart-ai-linker'),
            'all_items'         => __('All Silo Groups', 'smart-ai-linker'),
            'parent_item'       => __('Parent Silo Group', 'smart-ai-linker'),
            'parent_item_colon' => __('Parent Silo Group:', 'smart-ai-linker'),
            'edit_item'         => __('Edit Silo Group', 'smart-ai-linker'),
            'update_item'       => __('Update Silo Group', 'smart-ai-linker'),
            'add_new_item'      => __('Add New Silo Group', 'smart-ai-linker'),
            'new_item_name'     => __('New Silo Group Name', 'smart-ai-linker'),
            'menu_name'         => __('Silo Groups', 'smart-ai-linker'),
        ];

        $args = [
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => ['slug' => 'silo-group'],
            'show_in_rest'      => true,
            'public'            => false,
            'show_in_menu'      => true,
        ];

        register_taxonomy('silo_group', ['post'], $args);
    }

    /**
     * Add meta box for silo settings
     */
    public static function add_silo_meta_box() {
        add_meta_box(
            'silo_settings',
            __('Silo Linking Settings', 'smart-ai-linker'),
            [__CLASS__, 'render_silo_meta_box'],
            'post',
            'side',
            'default'
        );
    }

    /**
     * Render the silo settings meta box
     */
    public static function render_silo_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('silo_meta_box', 'silo_meta_box_nonce');

        // Get current settings
        $disable_silo_linking = get_post_meta($post->ID, '_disable_silo_linking', true);
        
        // Get all silo groups
        $silo_groups = get_terms([
            'taxonomy' => 'silo_group',
            'hide_empty' => false,
        ]);
        
        // Get current post's silo groups
        $current_silo_groups = wp_get_post_terms($post->ID, 'silo_group', ['fields' => 'ids']);
        ?>
        <div class="silo-settings">
            <p>
                <label>
                    <input type="checkbox" name="disable_silo_linking" value="1" <?php checked($disable_silo_linking, '1'); ?>>
                    <?php _e('Disable silo linking for this post', 'smart-ai-linker'); ?>
                </label>
            </p>
            
            <p>
                <label for="silo_groups"><?php _e('Silo Groups:', 'smart-ai-linker'); ?></label><br>
                <select name="silo_groups[]" id="silo_groups" class="widefat" multiple>
                    <?php foreach ($silo_groups as $group) : ?>
                        <option value="<?php echo esc_attr($group->term_id); ?>" <?php selected(in_array($group->term_id, $current_silo_groups), true); ?>>
                            <?php echo esc_html($group->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="description"><?php _e('Hold Ctrl/Cmd to select multiple groups', 'smart-ai-linker'); ?></span>
            </p>
        </div>
        <?php
    }

    /**
     * Save silo meta box data
     */
    public static function save_silo_meta_box_data($post_id) {
        // Check if nonce is set
        if (!isset($_POST['silo_meta_box_nonce'])) {
            return;
        }

        // Verify nonce
        if (!wp_verify_nonce($_POST['silo_meta_box_nonce'], 'silo_meta_box')) {
            return;
        }

        // If this is an autosave, don't do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save disable silo linking setting
        $disable_silo_linking = isset($_POST['disable_silo_linking']) ? '1' : '0';
        update_post_meta($post_id, '_disable_silo_linking', $disable_silo_linking);

        // Save silo groups
        if (isset($_POST['silo_groups'])) {
            $silo_groups = array_map('intval', $_POST['silo_groups']);
            wp_set_post_terms($post_id, $silo_groups, 'silo_group');
        } else {
            // If no groups are selected, remove all terms
            wp_set_post_terms($post_id, [], 'silo_group');
        }
    }

    /**
     * Get posts in the same silo group
     * 
     * @param int $post_id The post ID to find related posts for
     * @param array $args Additional query arguments
     * @return WP_Query Query object with posts in the same silo group
     */
    public static function get_posts_in_same_silo($post_id, $args = []) {
        // Get the silo groups for the current post
        $silo_groups = wp_get_post_terms($post_id, 'silo_group', ['fields' => 'ids']);
        
        if (empty($silo_groups) || is_wp_error($silo_groups)) {
            return new WP_Query(['post__in' => [0]]); // Return empty query if no silo groups
        }
        
        // Default query args
        $defaults = [
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__not_in' => [$post_id], // Exclude current post
            'tax_query' => [
                [
                    'taxonomy' => 'silo_group',
                    'field'    => 'term_id',
                    'terms'    => $silo_groups,
                    'operator' => 'IN',
                ],
            ],
        ];
        
        // Merge with provided args
        $query_args = wp_parse_args($args, $defaults);
        
        return new WP_Query($query_args);
    }

    /**
     * Check if silo linking is disabled for a post
     * 
     * @param int $post_id The post ID to check
     * @return bool True if silo linking is disabled, false otherwise
     */
    public static function is_silo_linking_disabled($post_id) {
        return (bool) get_post_meta($post_id, '_disable_silo_linking', true);
    }
}

// Initialize the silo structure functionality
add_action('plugins_loaded', ['Smart_AI_Linker_Silo_Structure', 'init']);
