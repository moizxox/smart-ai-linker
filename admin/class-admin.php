<?php
/**
 * Admin functionality for Smart AI Linker
 */
class Smart_AI_Linker_Admin {
    private static $instance = null;
    private $silos;
    private $page_hook = '';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // AJAX handlers
        add_action('wp_ajax_smart_ai_create_silo', [$this, 'ajax_create_silo']);
        add_action('wp_ajax_smart_ai_delete_silo', [$this, 'ajax_delete_silo']);
        add_action('wp_ajax_smart_ai_analyze_post', [$this, 'ajax_analyze_post']);
        
        // Initialize components
        $this->silos = smart_ai_linker_init_silos();
    }

    public function add_admin_menu() {
        if (!current_user_can('administrator')) {
            return;
        }
        // Main menu item
        $this->page_hook = add_menu_page(
            __('Smart AI Linker', 'smart-ai-linker'),
            __('AI Linker', 'smart-ai-linker'),
            'manage_options',
            'smart-ai-linker',
            [$this, 'render_dashboard_page'],
            'dashicons-admin-links',
            30
        );
        
        // Dashboard submenu
        add_submenu_page(
            'smart-ai-linker',
            __('Dashboard', 'smart-ai-linker'),
            __('Dashboard', 'smart-ai-linker'),
            'manage_options',
            'smart-ai-linker',
            [$this, 'render_dashboard_page']
        );
        
        // Silo Management
        add_submenu_page(
            'smart-ai-linker',
            __('Silo Management', 'smart-ai-linker'),
            __('Silo Management', 'smart-ai-linker'),
            'manage_options',
            'smart-ai-silos',
            [$this, 'render_silo_management_page']
        );
        
        // Settings
        add_submenu_page(
            'smart-ai-linker',
            __('Settings', 'smart-ai-linker'),
            __('Settings', 'smart-ai-linker'),
            'manage_options',
            'smart-ai-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'smart-ai-') === false) {
            return;
        }
        
        // Admin CSS
        wp_enqueue_style(
            'smart-ai-admin',
            plugins_url('assets/css/admin.css', dirname(__FILE__)),
            [],
            SMARTLINK_AI_VERSION
        );
        
        // Admin JS
        wp_enqueue_script(
            'smart-ai-admin',
            plugins_url('assets/js/admin.js', dirname(__FILE__)),
            ['jquery', 'jquery-ui-sortable', 'wp-util'],
            SMARTLINK_AI_VERSION,
            true
        );
        
        // Localize script with data
        wp_localize_script('smart-ai-admin', 'smartAIData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smart_ai_admin_nonce'),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this silo? This action cannot be undone.', 'smart-ai-linker'),
                'analyzing' => __('Analyzing content...', 'smart-ai-linker'),
                'error' => __('An error occurred. Please try again.', 'smart-ai-linker'),
            ]
        ]);
        
        // Add thickbox for media uploads
        add_thickbox();
    }

    public function register_settings() {
        // Register settings
        register_setting('smart_ai_linker_settings', 'smart_ai_linker_enable_auto_linking');
        register_setting('smart_ai_linker_settings', 'smart_ai_linker_max_links');
        register_setting('smart_ai_linker_settings', 'smart_ai_linker_post_types');
        register_setting('smart_ai_linker_settings', 'smart_ai_auto_assign_silos');
        register_setting('smart_ai_linker_settings', 'smart_ai_min_content_length');
        
        // Add settings section
        add_settings_section(
            'smart_ai_general_settings',
            __('General Settings', 'smart-ai-linker'),
            [$this, 'render_general_settings_section'],
            'smart-ai-settings'
        );
        
        // Add settings fields
        add_settings_field(
            'smart_ai_enable_auto_linking',
            __('Enable Auto Linking', 'smart-ai-linker'),
            [$this, 'render_checkbox_field'],
            'smart-ai-settings',
            'smart_ai_general_settings',
            [
                'id' => 'smart_ai_linker_enable_auto_linking',
                'description' => __('Automatically add internal links when publishing content', 'smart-ai-linker')
            ]
        );
        
        add_settings_field(
            'smart_ai_max_links',
            __('Maximum Links per Post', 'smart-ai-linker'),
            [$this, 'render_number_field'],
            'smart-ai-settings',
            'smart_ai_general_settings',
            [
                'id' => 'smart_ai_linker_max_links',
                'min' => 1,
                'max' => 20,
                'default' => 7,
                'description' => __('Maximum number of internal links to add per post', 'smart-ai-linker')
            ]
        );
        
        add_settings_field(
            'smart_ai_post_types',
            __('Post Types', 'smart-ai-linker'),
            [$this, 'render_post_types_field'],
            'smart-ai-settings',
            'smart_ai_general_settings',
            [
                'id' => 'smart_ai_linker_post_types',
                'description' => __('Select which post types to enable for auto-linking', 'smart-ai-linker')
            ]
        );
        
        add_settings_field(
            'smart_ai_auto_assign_silos',
            __('Auto-assign Silos', 'smart-ai-linker'),
            [$this, 'render_checkbox_field'],
            'smart-ai-settings',
            'smart_ai_general_settings',
            [
                'id' => 'smart_ai_auto_assign_silos',
                'description' => __('Automatically assign silos to new content based on AI analysis', 'smart-ai-linker')
            ]
        );
        
        add_settings_field(
            'smart_ai_min_content_length',
            __('Minimum Content Length', 'smart-ai-linker'),
            [$this, 'render_number_field'],
            'smart-ai-settings',
            'smart_ai_general_settings',
            [
                'id' => 'smart_ai_min_content_length',
                'min' => 50,
                'max' => 1000,
                'default' => 100,
                'description' => __('Minimum word count for AI analysis', 'smart-ai-linker')
            ]
        );
    }

    public function render_dashboard_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get stats
        $post_count = wp_count_posts('post')->publish;
        $page_count = wp_count_posts('page')->publish;
        $silo_count = count($this->silos->get_all_silos());
        
        // Get link statistics
        global $wpdb;
        
        // Get all posts with added links and count them properly
        // Calculate total links by counting actual smart-ai-link tags in content
        $posts_with_links = $wpdb->get_results(
            "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND (post_type = 'post' OR post_type = 'page')"
        );
        
        $total_links = 0;
        foreach ($posts_with_links as $post) {
            $total_links += smart_ai_linker_count_actual_links($post->ID);
        }
        
        $processed_posts = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->postmeta} 
             WHERE meta_key = '_smart_ai_linker_processed'"
        );
        $processed_posts = $processed_posts ? intval($processed_posts) : 0;
        
        // Get recent activity with link counts
        $recent_activity = $wpdb->get_results(
            "SELECT p.post_title, p.ID, p.post_type, sr.created_at, s.name as silo_name
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->prefix}smart_ai_silo_relationships sr ON p.ID = sr.post_id
            LEFT JOIN {$wpdb->prefix}smart_ai_silos s ON sr.silo_id = s.id
            WHERE p.post_status = 'publish' AND (p.post_type = 'post' OR p.post_type = 'page')
            ORDER BY p.post_modified DESC
            LIMIT 10"
        );
        
        // Process the link counts using real content counting
        foreach ($recent_activity as $activity) {
            $activity->links_count = smart_ai_linker_count_actual_links($activity->ID);
        }
        
        include plugin_dir_path(__FILE__) . 'views/dashboard.php';
    }

    public function render_silo_management_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $silos = $this->silos->get_all_silos();
        $post_types = get_post_types(['public' => true], 'objects');
        
        include plugin_dir_path(__FILE__) . 'views/silo-management.php';
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include plugin_dir_path(__FILE__) . 'views/settings.php';
    }

    public function render_general_settings_section() {
        echo '<p>' . __('Configure the general settings for Smart AI Linker.', 'smart-ai-linker') . '</p>';
    }

    public function render_checkbox_field($args) {
        $option = get_option($args['id']);
        $value = isset($option) ? $option : '1';
        ?>
        <input type="checkbox" 
               id="<?php echo esc_attr($args['id']); ?>" 
               name="<?php echo esc_attr($args['id']); ?>" 
               value="1" 
               <?php checked(1, $value, true); ?> />
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    public function render_number_field($args) {
        $value = get_option($args['id'], $args['default'] ?? '');
        ?>
        <input type="number" 
               id="<?php echo esc_attr($args['id']); ?>" 
               name="<?php echo esc_attr($args['id']); ?>" 
               value="<?php echo esc_attr($value); ?>" 
               min="<?php echo esc_attr($args['min'] ?? 0); ?>" 
               max="<?php echo esc_attr($args['max'] ?? 100); ?>" 
               step="1" />
        <?php if (!empty($args['description'])) : ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif;
    }

    public function render_post_types_field($args) {
        $selected_types = get_option($args['id'], ['post']);
        $post_types = get_post_types(['public' => true], 'objects');
        
        foreach ($post_types as $post_type) {
            if ($post_type->name === 'attachment') continue;
            ?>
            <label>
                <input type="checkbox" 
                       name="<?php echo esc_attr($args['id']); ?>[]" 
                       value="<?php echo esc_attr($post_type->name); ?>" 
                       <?php checked(in_array($post_type->name, (array)$selected_types), true); ?> />
                <?php echo esc_html($post_type->labels->name); ?>
            </label><br>
            <?php
        }
        
        if (!empty($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }

    public function ajax_create_silo() {
        check_ajax_referer('smart_ai_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smart-ai-linker')]);
        }
        
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        
        if (empty($name)) {
            wp_send_json_error(['message' => __('Silo name is required', 'smart-ai-linker')]);
        }
        
        global $wpdb;
        $slug = sanitize_title($name);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'smart_ai_silos',
            [
                'name' => $name,
                'slug' => $slug,
                'description' => $description
            ],
            ['%s', '%s', '%s']
        );
        
        if ($result) {
            $silo = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}smart_ai_silos WHERE id = %d",
                $wpdb->insert_id
            ));
            
            wp_send_json_success([
                'message' => __('Silo created successfully', 'smart-ai-linker'),
                'silo' => $silo
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to create silo', 'smart-ai-linker')
            ]);
        }
    }

    public function ajax_delete_silo() {
        check_ajax_referer('smart_ai_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smart-ai-linker')]);
        }
        
        $silo_id = isset($_POST['silo_id']) ? intval($_POST['silo_id']) : 0;
        
        if (!$silo_id) {
            wp_send_json_error(['message' => __('Invalid silo ID', 'smart-ai-linker')]);
        }
        
        global $wpdb;
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete relationships
            $wpdb->delete(
                $wpdb->prefix . 'smart_ai_silo_relationships',
                ['silo_id' => $silo_id],
                ['%d']
            );
            
            // Delete silo
            $result = $wpdb->delete(
                $wpdb->prefix . 'smart_ai_silos',
                ['id' => $silo_id],
                ['%d']
            );
            
            if ($result) {
                $wpdb->query('COMMIT');
                wp_send_json_success([
                    'message' => __('Silo deleted successfully', 'smart-ai-linker')
                ]);
            } else {
                throw new Exception(__('Failed to delete silo', 'smart-ai-linker'));
            }
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error([
                'message' => $e->getMessage()
            ]);
        }
    }

    public function ajax_analyze_post() {
        check_ajax_referer('smart_ai_admin_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smart-ai-linker')]);
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => __('Invalid post ID', 'smart-ai-linker')]);
        }
        
        $post = get_post($post_id);
        
        if (!$post) {
            wp_send_json_error(['message' => __('Post not found', 'smart-ai-linker')]);
        }
        
        // This is where you would call your AI service
        // For now, we'll return a mock response
        $suggested_silos = $this->silos->get_ai_suggested_silos($post->post_content);
        
        if (!empty($suggested_silos)) {
            $this->silos->assign_silos_to_post($post_id, $suggested_silos, true);
            
            $assigned_silos = [];
            foreach ($suggested_silos as $silo_id) {
                $silo = $this->silos->get_silo($silo_id);
                if ($silo) {
                    $assigned_silos[] = $silo;
                }
            }
            
            wp_send_json_success([
                'message' => __('Content analyzed successfully', 'smart-ai-linker'),
                'silos' => $assigned_silos
            ]);
        } else {
            wp_send_json_error([
                'message' => __('No relevant silos found for this content', 'smart-ai-linker')
            ]);
        }
    }
}

// Initialize the admin functionality
function smart_ai_linker_init_admin() {
    return Smart_AI_Linker_Admin::get_instance();
}
add_action('plugins_loaded', 'smart_ai_linker_init_admin');
