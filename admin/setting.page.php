<?php
if (!defined('ABSPATH'))
    exit;

/**
 * Admin Settings Page for Smart AI Linker
 */

// Register settings
add_action('admin_init', 'smart_ai_linker_register_settings');

// Add admin menu
add_action('admin_menu', 'smart_ai_linker_admin_menu');

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/smart-ai-linker.php'), 'smart_ai_linker_settings_link');

/**
 * Register plugin settings
 */
function smart_ai_linker_register_settings() {
    // Register settings
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_api_key');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_enable_auto_linking');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_max_links');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_post_types');
    
    // Add settings section
    add_settings_section(
        'smart_ai_linker_general_section',
        'General Settings',
        'smart_ai_linker_general_section_callback',
        'smart-ai-linker'
    );
    
    // Add settings fields
    add_settings_field(
        'smart_ai_linker_api_key_field',
        'DeepSeek API Key',
        'smart_ai_linker_api_key_field_callback',
        'smart-ai-linker',
        'smart_ai_linker_general_section'
    );
    
    add_settings_field(
        'smart_ai_linker_enable_auto_linking_field',
        'Enable Auto-Linking',
        'smart_ai_linker_enable_auto_linking_field_callback',
        'smart-ai-linker',
        'smart_ai_linker_general_section'
    );
    
    add_settings_field(
        'smart_ai_linker_max_links_field',
        'Maximum Links per Post',
        'smart_ai_linker_max_links_field_callback',
        'smart-ai-linker',
        'smart_ai_linker_general_section'
    );
    
    add_settings_field(
        'smart_ai_linker_post_types_field',
        'Enable for Post Types',
        'smart_ai_linker_post_types_field_callback',
        'smart-ai-linker',
        'smart_ai_linker_general_section'
    );
}

/**
 * Add settings link on plugin page
 */
function smart_ai_linker_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=smart-ai-linker') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Add admin menu item
 */
function smart_ai_linker_admin_menu() {
    add_menu_page(
        'Smart AI Linker',
        'Smart AI Linker',
        'manage_options',
        'smart-ai-linker',
        'smart_ai_linker_settings_page',
        'dashicons-admin-links',
        30
    );
}

/**
 * Settings page callback
 */
function smart_ai_linker_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Show success/error messages
    if (isset($_GET['settings-updated'])) {
        add_settings_error('smart_ai_linker_messages', 'smart_ai_linker_message', 
            'Settings Saved', 'updated');
    }
    
    // Show error messages
    settings_errors('smart_ai_linker_messages');
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <form action="options.php" method="post">
            <?php
            // Output security fields
            settings_fields('smart_ai_linker_settings');
            // Output setting sections
            do_settings_sections('smart-ai-linker');
            // Output save settings button
            submit_button('Save Settings');
            ?>
        </form>
        
        <div class="card">
            <h2>How It Works</h2>
            <p>Smart AI Linker automatically adds relevant internal links to your posts using DeepSeek AI.</p>
            <ol>
                <li>Enter your DeepSeek API key above</li>
                <li>Configure the settings to your preference</li>
                <li>New posts will be automatically processed when published</li>
                <li>Existing posts can be processed manually from the post editor</li>
            </ol>
            <p><strong>Note:</strong> You need a valid DeepSeek API key for the plugin to work.</p>
        </div>
    </div>
    <?php
}

/**
 * Section callbacks
 */
function smart_ai_linker_general_section_callback() {
    echo '<p>Configure the basic settings for Smart AI Linker.</p>';
}

/**
 * Field callbacks
 */
function smart_ai_linker_api_key_field_callback() {
    $api_key = get_option('smart_ai_linker_api_key', '');
    ?>
    <input type="password" id="smart_ai_linker_api_key" 
           name="smart_ai_linker_api_key" 
           value="<?php echo esc_attr($api_key); ?>" 
           class="regular-text" />
    <p class="description">
        Enter your DeepSeek API key. <a href="https://platform.deepseek.com/" target="_blank">Get API Key</a>
    </p>
    <?php
}

function smart_ai_linker_enable_auto_linking_field_callback() {
    $enabled = get_option('smart_ai_linker_enable_auto_linking', '1');
    ?>
    <label>
        <input type="checkbox" id="smart_ai_linker_enable_auto_linking" 
               name="smart_ai_linker_enable_auto_linking" 
               value="1" <?php checked('1', $enabled); ?> />
        Automatically add links when publishing posts
    </label>
    <?php
}

function smart_ai_linker_max_links_field_callback() {
    $max_links = get_option('smart_ai_linker_max_links', '7');
    ?>
    <input type="number" id="smart_ai_linker_max_links" 
           name="smart_ai_linker_max_links" 
           value="<?php echo esc_attr($max_links); ?>" 
           min="1" max="10" class="small-text" />
    <p class="description">
        Maximum number of internal links to add per post (1-10)
    </p>
    <?php
}

function smart_ai_linker_post_types_field_callback() {
    $selected_types = get_option('smart_ai_linker_post_types', array('post'));
    $post_types = get_post_types(array('public' => true), 'objects');
    
    foreach ($post_types as $post_type) {
        if ($post_type->name === 'attachment') continue;
        
        $checked = in_array($post_type->name, (array)$selected_types) ? 'checked="checked"' : '';
        ?>
        <label style="display: block; margin: 5px 0;">
            <input type="checkbox" name="smart_ai_linker_post_types[]" 
                   value="<?php echo esc_attr($post_type->name); ?>" 
                   <?php echo $checked; ?> />
            <?php echo esc_html($post_type->labels->singular_name); ?>
        </label>
        <?php
    }
    ?>
    <p class="description">
        Select which post types should be processed for internal linking
    </p>
    <?php
}
