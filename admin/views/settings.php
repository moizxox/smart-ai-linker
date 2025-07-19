<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap smart-ai-settings">
    <h1><?php _e('Smart AI Linker Settings', 'smart-ai-linker'); ?></h1>
    
    <div class="settings-container">
        <div class="settings-content">
            <form method="post" action="options.php" class="settings-form">
                <?php
                settings_fields('smart_ai_linker_settings');
                do_settings_sections('smart-ai-settings');
                submit_button();
                ?>
            </form>
            
            <div class="settings-sidebar">
                <div class="settings-card">
                    <h3><?php _e('About Smart AI Linker', 'smart-ai-linker'); ?></h3>
                    <p><?php _e('Smart AI Linker helps you organize your content into silos and automatically creates relevant internal links to improve your site\'s SEO and user experience.', 'smart-ai-linker'); ?></p>
                    <p><?php _e('Version', 'smart-ai-linker'); ?>: <?php echo esc_html(SMARTLINK_AI_VERSION); ?></p>
                </div>
                
                <div class="settings-card">
                    <h3><?php _e('Quick Links', 'smart-ai-linker'); ?></h3>
                    <ul class="quick-links">
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=smart-ai-linker'); ?>">
                                <?php _e('Dashboard', 'smart-ai-linker'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="<?php echo admin_url('admin.php?page=smart-ai-silos'); ?>">
                                <?php _e('Manage Silos', 'smart-ai-linker'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="#" id="reset-settings">
                                <?php _e('Reset to Defaults', 'smart-ai-linker'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="#" id="export-settings">
                                <?php _e('Export Settings', 'smart-ai-linker'); ?>
                            </a>
                        </li>
                        <li>
                            <a href="#" id="import-settings">
                                <?php _e('Import Settings', 'smart-ai-linker'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
                
                <div class="settings-card">
                    <h3><?php _e('Need Help?', 'smart-ai-linker'); ?></h3>
                    <p><?php _e('Check out our documentation or contact support if you need assistance.', 'smart-ai-linker'); ?></p>
                    <p>
                        <a href="#" class="button"><?php _e('Documentation', 'smart-ai-linker'); ?></a>
                        <a href="#" class="button"><?php _e('Support', 'smart-ai-linker'); ?></a>
                    </p>
                </div>
            </div>
        </div>
        
        <div class="settings-advanced" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
            <h2><?php _e('Advanced Settings', 'smart-ai-linker'); ?></h2>
            <p class="description">
                <?php _e('These settings are for advanced users. Only modify them if you know what you\'re doing.', 'smart-ai-linker'); ?>
            </p>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Debug Mode', 'smart-ai-linker'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="smart_ai_debug_mode" value="1" 
                                <?php checked(get_option('smart_ai_debug_mode', 0), 1); ?>>
                            <?php _e('Enable debug mode', 'smart-ai-linker'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, detailed debug information will be logged.', 'smart-ai-linker'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('API Endpoint', 'smart-ai-linker'); ?></th>
                    <td>
                        <input type="text" name="smart_ai_api_endpoint" class="regular-text" 
                               value="<?php echo esc_attr(get_option('smart_ai_api_endpoint', 'https://api.smart-ai-linker.com/v1')); ?>">
                        <p class="description">
                            <?php _e('Custom API endpoint for AI processing.', 'smart-ai-linker'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Cache Lifetime', 'smart-ai-linker'); ?></th>
                    <td>
                        <input type="number" name="smart_ai_cache_lifetime" class="small-text" min="0" step="1" 
                               value="<?php echo esc_attr(get_option('smart_ai_cache_lifetime', HOUR_IN_SECONDS)); ?>">
                        <span><?php _e('seconds', 'smart-ai-linker'); ?></span>
                        <p class="description">
                            <?php _e('How long to cache AI analysis results (0 = no cache).', 'smart-ai-linker'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" id="clear-cache" class="button button-secondary">
                    <?php _e('Clear Cache', 'smart-ai-linker'); ?>
                </button>
                <span class="spinner"></span>
            </p>
            
            <div id="cache-cleared" class="notice notice-success" style="display: none; margin-top: 10px;">
                <p><?php _e('Cache cleared successfully!', 'smart-ai-linker'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Import/Export Modal -->
<div id="import-export-modal" style="display: none;">
    <div class="import-export-content">
        <div id="export-section">
            <h3><?php _e('Export Settings', 'smart-ai-linker'); ?></h3>
            <p><?php _e('Copy the following text to save your settings:', 'smart-ai-linker'); ?></p>
            <textarea id="export-data" class="widefat" rows="5" readonly></textarea>
            <p class="submit">
                <button type="button" id="copy-export" class="button button-primary">
                    <?php _e('Copy to Clipboard', 'smart-ai-linker'); ?>
                </button>
                <span id="copy-success" style="display: none; color: #46b450; margin-left: 10px;">
                    <?php _e('Copied!', 'smart-ai-linker'); ?>
                </span>
            </p>
        </div>
        
        <div id="import-section" style="display: none;">
            <h3><?php _e('Import Settings', 'smart-ai-linker'); ?></h3>
            <p><?php _e('Paste your settings data:', 'smart-ai-linker'); ?></p>
            <textarea id="import-data" class="widefat" rows="5"></textarea>
            <p class="submit">
                <button type="button" id="import-settings-btn" class="button button-primary">
                    <?php _e('Import Settings', 'smart-ai-linker'); ?>
                </button>
                <span id="import-status" style="margin-left: 10px;"></span>
            </p>
        </div>
        
        <p class="toggle-import-export">
            <a href="#" id="toggle-import-export">
                <?php _e('Switch to Import', 'smart-ai-linker'); ?>
            </a>
        </p>
    </div>
</div>
