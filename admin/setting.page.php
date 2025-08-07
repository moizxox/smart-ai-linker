<?php
if (!defined('ABSPATH'))
    exit;

/** Admin Settings Page for Smart AI Linker */

// Register settings
add_action('admin_init', 'smart_ai_linker_register_settings');

// Add admin menu
add_action('admin_menu', 'smart_ai_linker_admin_menu');

// Add settings link to plugins page
add_filter('plugin_action_links_' . plugin_basename(dirname(__DIR__) . '/smart-ai-linker.php'), 'smart_ai_linker_settings_link');

/**
 * Register plugin settings
 */
function smart_ai_linker_register_settings()
{
    // Register settings
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_api_key');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_ideogram_api_key');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_enable_auto_linking');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_max_links');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_post_types');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_enable_broken_links');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_enable_page_to_page');
    register_setting('smart_ai_linker_settings', 'smart_ai_linker_excluded_posts');

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
        'smart_ai_linker_ideogram_api_key_field',
        'Ideogram API Key',
        'smart_ai_linker_ideogram_api_key_field_callback',
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

    add_settings_field(
        'smart_ai_linker_enable_broken_links_field',
        'Enable Broken Link Checker',
        'smart_ai_linker_enable_broken_links_field_callback',
        'smart-ai-linker',
        'smart_ai_linker_general_section'
    );

    add_settings_field(
        'smart_ai_linker_enable_page_to_page_field',
        'Enable Page-to-Page Linking',
        'smart_ai_linker_enable_page_to_page_field_callback',
        'smart-ai-linker',
        'smart_ai_linker_general_section'
    );

    add_settings_field(
        'smart_ai_linker_excluded_posts_field',
        'Exclude Posts/Pages from Internal Linking',
        'smart_ai_linker_excluded_posts_field_callback',
        'smart-ai-linker',
        'smart_ai_linker_general_section'
    );
}

/**
 * Add settings link on plugin page
 */
function smart_ai_linker_settings_link($links)
{
    $settings_link = '<a href="' . admin_url('admin.php?page=smart-ai-linker') . '">' . __('Settings') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

/**
 * Add admin menu item
 */
function smart_ai_linker_admin_menu()
{
    if (!current_user_can('administrator')) {
        return;
    }
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
function smart_ai_linker_settings_page()
{
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }
    // Verification logic
    if (function_exists('smart_ai_linker_is_verified') && !smart_ai_linker_is_verified()) {
        if (isset($_POST['smart_ai_linker_verify']) && isset($_POST['smart_ai_linker_password'])) {
            $password = trim($_POST['smart_ai_linker_password']);
            if ($password === 'tamir@gmail.com') {
                update_option('smart_ai_linker_verified', '1');
                echo '<div class="notice notice-success"><p>Plugin verified! Please reload the page.</p></div>';
                echo '<script>setTimeout(function(){window.location.reload();}, 1000);</script>';
                return;
            } else {
                echo '<div class="notice notice-error"><p>Incorrect password. Please try again.</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row">Verification Password</th>
                        <td>
                            <input type="password" name="smart_ai_linker_password" class="regular-text" required />
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="smart_ai_linker_verify" class="button button-primary">Verify Plugin</button>
                </p>
            </form>
        </div>
        <?php
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
function smart_ai_linker_general_section_callback()
{
    echo '<p>Configure the basic settings for Smart AI Linker.</p>';
}

/**
 * Field callbacks
 */
function smart_ai_linker_api_key_field_callback()
{
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

function smart_ai_linker_ideogram_api_key_field_callback()
{
    $ideogram_api_key = get_option('smart_ai_linker_ideogram_api_key', '');
    ?>
    <input type="password" id="smart_ai_linker_ideogram_api_key" 
           name="smart_ai_linker_ideogram_api_key" 
           value="<?php echo esc_attr($ideogram_api_key); ?>" 
           class="regular-text" />
    <p class="description">
        Enter your Ideogram API key for image generation. <a href="https://ideogram.ai/" target="_blank">Get API Key</a>
    </p>
    <?php
}

function smart_ai_linker_enable_auto_linking_field_callback()
{
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

function smart_ai_linker_max_links_field_callback()
{
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

function smart_ai_linker_post_types_field_callback()
{
    $post_types = get_post_types(['public' => true], 'objects');
    $enabled_types = get_option('smart_ai_linker_post_types', ['post', 'page']);

    foreach ($post_types as $post_type) {
        $checked = in_array($post_type->name, (array) $enabled_types) ? 'checked' : '';
        echo "<label><input type='checkbox' name='smart_ai_linker_post_types[]' value='{$post_type->name}' {$checked}> {$post_type->label}</label><br>";
    }
    echo '<p class="description">Select which post types should have automatic internal linking.</p>';
}

function smart_ai_linker_enable_broken_links_field_callback()
{
    $enabled = get_option('smart_ai_linker_enable_broken_links', '1');
    echo "<label><input type='checkbox' name='smart_ai_linker_enable_broken_links' value='1' " . checked('1', $enabled, false) . '> Enable automatic broken link checking and fixing</label>';
    echo '<p class="description">When enabled, the plugin will automatically check for and fix broken links when posts are saved also fix broken links for pages.</p>';
}

function smart_ai_linker_enable_page_to_page_field_callback()
{
    $enabled = get_option('smart_ai_linker_enable_page_to_page', '1');
    echo "<label><input type='checkbox' name='smart_ai_linker_enable_page_to_page' value='1' " . checked('1', $enabled, false) . '> Enable linking between pages</label>';
    echo '<p class="description">When enabled, the plugin will suggest and create links between pages as well as posts.</p>';
}

function smart_ai_linker_excluded_posts_field_callback()
{
    $excluded = get_option('smart_ai_linker_excluded_posts', []);
    if (!is_array($excluded))
        $excluded = [];
    $args = array(
        'post_type' => array('post', 'page'),
        'posts_per_page' => 100,
        'post_status' => array('publish', 'draft', 'pending', 'private', 'future'),
        'fields' => 'ids',
    );
    $ids = get_posts($args);
    echo '<input type="text" id="smart-ai-linker-exclude-search" placeholder="Search posts/pages..." style="width: 100%; max-width: 400px; margin-bottom: 8px;" onkeyup="smartAiLinkerFilterExcludeList()">';
    echo '<div id="smart-ai-linker-exclude-list" style="max-height: 220px; overflow-y: auto; border: 1px solid #ccc; padding: 8px; background: #fff; width: 100%; max-width: 400px;">';
    foreach ($ids as $id) {
        $title = esc_html(get_the_title($id));
        $checked = in_array($id, $excluded) ? 'checked' : '';
        echo "<label class='smart-ai-linker-exclude-item' style='display:block; margin-bottom:4px;'><input type='checkbox' name='smart_ai_linker_excluded_posts[]' value='$id' $checked> [$id] $title</label>";
    }
    echo '</div>';
    echo '<p class="description">Select posts or pages to exclude from all internal linking (to and from). Use the search box to filter. Hold Ctrl (Cmd on Mac) to select multiple.</p>';
    // Add inline JS for filtering
    echo '<script>
    function smartAiLinkerFilterExcludeList() {
        var input = document.getElementById("smart-ai-linker-exclude-search");
        var filter = input.value.toLowerCase();
        var list = document.getElementById("smart-ai-linker-exclude-list");
        var items = list.getElementsByClassName("smart-ai-linker-exclude-item");
        for (var i = 0; i < items.length; i++) {
            var label = items[i];
            if (label.textContent.toLowerCase().indexOf(filter) > -1) {
                label.style.display = "block";
            } else {
                label.style.display = "none";
            }
        }
    }
    </script>';
}
