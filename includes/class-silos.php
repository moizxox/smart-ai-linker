<?php
/**
 * Handles silo structure functionality
 */
class Smart_AI_Linker_Silos {
    private static $instance = null;
    private $table_name;
    private $db_version = '1.0';
    private $min_content_length = 100; // Minimum content length for AI analysis

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'smart_ai_silos';
        $this->silo_relationships = $wpdb->prefix . 'smart_ai_silo_relationships';
        
        add_action('plugins_loaded', [$this, 'init']);
    }

    public function init() {
        $this->create_tables();
        $this->setup_hooks();
    }

    private function setup_hooks() {
        // Admin hooks
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('add_meta_boxes', [$this, 'add_silo_meta_box']);
        add_action('save_post', [$this, 'save_silo_meta'], 10, 2);
        
        // AJAX handlers
        add_action('wp_ajax_assign_silo_to_post', [$this, 'ajax_assign_silo_to_post']);
        add_action('wp_ajax_bulk_assign_silos', [$this, 'ajax_bulk_assign_silos']);
        
        // Auto-assign on post publish
        add_action('wp_after_insert_post', [$this, 'maybe_auto_assign_silo'], 10, 4);
    }

    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            slug varchar(200) NOT NULL,
            description text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";
        
        $sql[] = "CREATE TABLE IF NOT EXISTS {$this->silo_relationships} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            silo_id bigint(20) NOT NULL,
            post_id bigint(20) NOT NULL,
            confidence_score float DEFAULT 0,
            is_auto_assigned tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY post_silo (post_id, silo_id),
            KEY silo_id (silo_id),
            KEY post_id (post_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $wpdb->hide_errors();
        foreach ($sql as $query) {
            dbDelta($query);
        }
        $wpdb->show_errors();
        
        update_option('smart_ai_silo_db_version', $this->db_version);
    }

    public function enqueue_admin_assets($hook) {
        if (!in_array($hook, ['post.php', 'post-new.php', 'edit.php'])) {
            return;
        }
        
        wp_enqueue_style(
            'smart-ai-silo-admin',
            plugins_url('assets/css/admin-silos.css', dirname(__FILE__)),
            [],
            SMARTLINK_AI_VERSION
        );
        
        wp_enqueue_script(
            'smart-ai-silo-admin',
            plugins_url('assets/js/admin-silos.js', dirname(__FILE__)),
            ['jquery'],
            SMARTLAI_VERSION,
            true
        );
        
        wp_localize_script('smart-ai-silo-admin', 'smartAISilo', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('smart_ai_silo_nonce'),
            'i18n' => [
                'assigning' => __('Assigning silos...', 'smart-ai-linker'),
                'error' => __('An error occurred. Please try again.', 'smart-ai-linker'),
                'success' => __('Silos assigned successfully!', 'smart-ai-linker'),
            ]
        ]);
    }

    public function add_silo_meta_box() {
        $post_types = get_post_types(['public' => true]);
        foreach ($post_types as $post_type) {
            add_meta_box(
                'smart_ai_silo_meta_box',
                __('Silo Assignment', 'smart-ai-linker'),
                [$this, 'render_silo_meta_box'],
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function render_silo_meta_box($post) {
        $silos = $this->get_all_silos();
        $assigned_silos = $this->get_post_silos($post->ID);
        $assigned_silo_ids = wp_list_pluck($assigned_silos, 'id');
        
        wp_nonce_field('save_silo_meta', 'silo_meta_nonce');
        ?>
        <div class="silo-assignment-container">
            <div class="silo-selection">
                <select id="silo_selector" class="widefat" multiple>
                    <?php foreach ($silos as $silo) : ?>
                        <option value="<?php echo esc_attr($silo->id); ?>" <?php selected(in_array($silo->id, $assigned_silo_ids)); ?>>
                            <?php echo esc_html($silo->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="button button-primary assign-silo" data-post-id="<?php echo $post->ID; ?>">
                    <?php _e('Assign Selected', 'smart-ai-linker'); ?>
                </button>
            </div>
            <div class="assigned-silos">
                <h4><?php _e('Assigned Silos:', 'smart-ai-linker'); ?></h4>
                <ul class="silo-list">
                    <?php foreach ($assigned_silos as $silo) : ?>
                        <li data-silo-id="<?php echo $silo->id; ?>">
                            <?php echo esc_html($silo->name); ?>
                            <span class="remove-silo" data-post-id="<?php echo $post->ID; ?>" data-silo-id="<?php echo $silo->id; ?>">&times;</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="silo-actions">
                <button type="button" class="button analyze-content" data-post-id="<?php echo $post->ID; ?>">
                    <?php _e('AI Analyze Content', 'smart-ai-linker'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </div>
        <?php
    }

    public function save_silo_meta($post_id, $post) {
        // Verify nonce and user permissions
        if (!isset($_POST['silo_meta_nonce']) || !wp_verify_nonce($_POST['silo_meta_nonce'], 'save_silo_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Handle manual silo assignments if any
        if (isset($_POST['silo_assignments'])) {
            $silos = array_map('intval', $_POST['silo_assignments']);
            $this->assign_silos_to_post($post_id, $silos, false);
        }
    }

    public function maybe_auto_assign_silo($post_id, $post, $update, $post_before = null) {
        // Skip if not a new post or if auto-assignment is disabled
        if ($update || !get_option('smart_ai_auto_assign_silos', true)) {
            return;
        }

        // Only process published posts
        if ($post->post_status !== 'publish' || empty($post->post_content)) {
            return;
        }

        // Minimum content length check
        if (str_word_count(strip_tags($post->post_content)) < $this->min_content_length) {
            return;
        }

        // Analyze content and assign silos
        $this->analyze_and_assign_silos($post_id, $post);
    }

    private function analyze_and_assign_silos($post_id, $post) {
        $content = $this->prepare_content_for_analysis($post);
        
        // Get AI-suggested silos based on content
        $suggested_silos = $this->get_ai_suggested_silos($content);
        
        if (!empty($suggested_silos)) {
            $this->assign_silos_to_post($post_id, $suggested_silos, true);
            return true;
        }
        
        return false;
    }

    private function prepare_content_for_analysis($post) {
        // Strip shortcodes, HTML, and extra whitespace
        $content = strip_shortcodes($post->post_content);
        $content = wp_strip_all_tags($content);
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Add title and excerpt to provide more context
        $content = $post->post_title . ' ' . $post->post_excerpt . ' ' . $content;
        
        // Limit content length to prevent API timeouts
        return mb_substr($content, 0, 8000);
    }

    private function get_ai_suggested_silos($content) {
        // This is a placeholder for actual AI integration
        // In a real implementation, you would call an AI service here
        
        // For now, we'll return some dummy data
        $all_silos = $this->get_all_silos();
        if (empty($all_silos)) {
            return [];
        }
        
        // Simple keyword matching as fallback
        $keywords = [
            'seo' => ['seo', 'search engine', 'ranking', 'keywords'],
            'wordpress' => ['wordpress', 'wp', 'plugin', 'theme'],
            'marketing' => ['marketing', 'promotion', 'advertising', 'campaign'],
        ];
        
        $scores = [];
        $content = strtolower($content);
        
        foreach ($all_silos as $silo) {
            $slug = strtolower($silo->slug);
            $score = 0;
            
            if (isset($keywords[$slug])) {
                foreach ($keywords[$slug] as $keyword) {
                    $score += substr_count($content, $keyword);
                }
            }
            
            if ($score > 0) {
                $scores[$silo->id] = $score;
            }
        }
        
        arsort($scores);
        return array_slice(array_keys($scores), 0, 3); // Return top 3 matches
    }

    public function assign_silos_to_post($post_id, $silo_ids, $is_auto_assigned = false) {
        global $wpdb;
        
        // Remove existing assignments
        $wpdb->delete(
            $this->silo_relationships,
            ['post_id' => $post_id],
            ['%d']
        );
        
        // Add new assignments
        foreach ($silo_ids as $silo_id) {
            $wpdb->insert(
                $this->silo_relationships,
                [
                    'silo_id' => $silo_id,
                    'post_id' => $post_id,
                    'is_auto_assigned' => $is_auto_assigned ? 1 : 0,
                    'confidence_score' => $is_auto_assigned ? 0.8 : 1.0, // Default confidence
                ],
                ['%d', '%d', '%d', '%f']
            );
        }
        
        // Clear cache
        wp_cache_delete('post_silos_' . $post_id, 'smart_ai_linker');
        
        return true;
    }

    public function get_all_silos() {
        global $wpdb;
        $cache_key = 'all_silos';
        
        $silos = wp_cache_get($cache_key, 'smart_ai_linker');
        
        if (false === $silos) {
            $silos = $wpdb->get_results(
                "SELECT * FROM {$this->table_name} ORDER BY name ASC"
            );
            wp_cache_set($cache_key, $silos, 'smart_ai_linker');
        }
        
        return $silos;
    }

    public function get_post_silos($post_id) {
        global $wpdb;
        $cache_key = 'post_silos_' . $post_id;
        
        $silos = wp_cache_get($cache_key, 'smart_ai_linker');
        
        if (false === $silos) {
            $silos = $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, sr.confidence_score, sr.is_auto_assigned 
                FROM {$this->table_name} s
                INNER JOIN {$this->silo_relationships} sr ON s.id = sr.silo_id
                WHERE sr.post_id = %d
                ORDER BY sr.confidence_score DESC",
                $post_id
            ));
            
            wp_cache_set($cache_key, $silos, 'smart_ai_linker');
        }
        
        return $silos;
    }

    public function ajax_assign_silo_to_post() {
        check_ajax_referer('smart_ai_silo_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smart-ai-linker')]);
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $silo_id = isset($_POST['silo_id']) ? intval($_POST['silo_id']) : 0;
        $action = isset($_POST['action_type']) ? $_POST['action_type'] : 'assign';
        
        if (!$post_id || !$silo_id) {
            wp_send_json_error(['message' => __('Invalid parameters', 'smart-ai-linker')]);
        }
        
        global $wpdb;
        
        if ($action === 'assign') {
            $result = $wpdb->insert(
                $this->silo_relationships,
                [
                    'silo_id' => $silo_id,
                    'post_id' => $post_id,
                    'is_auto_assigned' => 0,
                    'confidence_score' => 1.0
                ],
                ['%d', '%d', '%d', '%f']
            );
            
            if ($result) {
                $silo = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$this->table_name} WHERE id = %d",
                    $silo_id
                ));
                
                wp_send_json_success([
                    'message' => __('Silo assigned successfully', 'smart-ai-linker'),
                    'silo' => $silo
                ]);
            }
        } else {
            $result = $wpdb->delete(
                $this->silo_relationships,
                [
                    'silo_id' => $silo_id,
                    'post_id' => $post_id
                ],
                ['%d', '%d']
            );
            
            if ($result) {
                wp_send_json_success([
                    'message' => __('Silo removed successfully', 'smart-ai-linker')
                ]);
            }
        }
        
        wp_send_json_error(['message' => __('Operation failed', 'smart-ai-linker')]);
    }

    public function ajax_bulk_assign_silos() {
        check_ajax_referer('smart_ai_silo_nonce', 'nonce');
        
        if (!current_user_can('edit_others_posts')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'smart-ai-linker')]);
        }
        
        $post_ids = isset($_POST['post_ids']) ? array_map('intval', $_POST['post_ids']) : [];
        $silo_id = isset($_POST['silo_id']) ? intval($_POST['silo_id']) : 0;
        $action = isset($_POST['action_type']) ? $_POST['action_type'] : 'assign';
        
        if (empty($post_ids) || !$silo_id) {
            wp_send_json_error(['message' => __('Invalid parameters', 'smart-ai-linker')]);
        }
        
        $results = [
            'success' => 0,
            'failed' => 0,
            'total' => count($post_ids)
        ];
        
        foreach ($post_ids as $post_id) {
            if ($action === 'assign') {
                $result = $this->assign_silos_to_post($post_id, [$silo_id], false);
            } else {
                $result = $this->remove_silo_from_post($post_id, $silo_id);
            }
            
            if ($result) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        wp_send_json_success([
            'message' => sprintf(
                _n(
                    'Operation completed: %d success, %d failed',
                    'Operation completed: %d successes, %d failed',
                    $results['success'],
                    'smart-ai-linker'
                ),
                $results['success'],
                $results['failed']
            ),
            'results' => $results
        ]);
    }
    
    private function remove_silo_from_post($post_id, $silo_id) {
        global $wpdb;
        
        $result = $wpdb->delete(
            $this->silo_relationships,
            [
                'post_id' => $post_id,
                'silo_id' => $silo_id
            ],
            ['%d', '%d']
        );
        
        if ($result !== false) {
            wp_cache_delete('post_silos_' . $post_id, 'smart_ai_linker');
            return true;
        }
        
        return false;
    }
}

// Initialize the silo functionality
function smart_ai_linker_init_silos() {
    return Smart_AI_Linker_Silos::get_instance();
}
add_action('plugins_loaded', 'smart_ai_linker_init_silos');
