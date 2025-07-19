<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap smart-ai-silos">
    <h1><?php _e('Silo Management', 'smart-ai-linker'); ?></h1>
    
    <div class="silo-management-container">
        <!-- Create New Silo -->
        <div class="silo-create-card">
            <h2><?php _e('Create New Silo', 'smart-ai-linker'); ?></h2>
            <form id="create-silo-form" class="silo-form">
                <div class="form-field">
                    <label for="silo-name"><?php _e('Silo Name', 'smart-ai-linker'); ?></label>
                    <input type="text" id="silo-name" name="name" required 
                           placeholder="<?php esc_attr_e('e.g., SEO, WordPress, Marketing', 'smart-ai-linker'); ?>">
                </div>
                <div class="form-field">
                    <label for="silo-description"><?php _e('Description', 'smart-ai-linker'); ?></label>
                    <textarea id="silo-description" name="description" rows="3" 
                              placeholder="<?php esc_attr_e('Brief description of this silo', 'smart-ai-linker'); ?>"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="button button-primary">
                        <?php _e('Create Silo', 'smart-ai-linker'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </form>
        </div>
        
        <!-- Existing Silos -->
        <div class="silos-list-card">
            <h2><?php _e('Your Silos', 'smart-ai-linker'); ?></h2>
            
            <?php if (!empty($silos)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'smart-ai-linker'); ?></th>
                            <th><?php _e('Slug', 'smart-ai-linker'); ?></th>
                            <th><?php _e('Posts', 'smart-ai-linker'); ?></th>
                            <th><?php _e('Actions', 'smart-ai-linker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($silos as $silo) : 
                            $post_count = $wpdb->get_var($wpdb->prepare(
                                "SELECT COUNT(*) FROM {$wpdb->prefix}smart_ai_silo_relationships WHERE silo_id = %d",
                                $silo->id
                            ));
                            ?>
                            <tr data-silo-id="<?php echo $silo->id; ?>">
                                <td class="silo-name">
                                    <strong><?php echo esc_html($silo->name); ?></strong>
                                    <?php if (!empty($silo->description)) : ?>
                                        <p class="description"><?php echo esc_html($silo->description); ?></p>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html($silo->slug); ?></code></td>
                                <td><?php echo number_format($post_count); ?></td>
                                <td class="actions">
                                    <a href="#" class="button button-small edit-silo" 
                                       data-silo-id="<?php echo $silo->id; ?>"
                                       data-name="<?php echo esc_attr($silo->name); ?>"
                                       data-description="<?php echo esc_attr($silo->description); ?>">
                                        <?php _e('Edit', 'smart-ai-linker'); ?>
                                    </a>
                                    <a href="#" class="button button-small view-posts" 
                                       data-silo-id="<?php echo $silo->id; ?>">
                                        <?php _e('View Posts', 'smart-ai-linker'); ?>
                                    </a>
                                    <a href="#" class="button button-small delete-silo" 
                                       data-silo-id="<?php echo $silo->id; ?>">
                                        <?php _e('Delete', 'smart-ai-linker'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <div class="notice notice-info">
                    <p><?php _e('No silos found. Create your first silo to get started!', 'smart-ai-linker'); ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Bulk Assign Silos -->
        <div class="bulk-assign-card">
            <h2><?php _e('Bulk Assign Silos', 'smart-ai-linker'); ?></h2>
            <div class="bulk-assign-form">
                <div class="form-field">
                    <label for="bulk-post-type"><?php _e('Post Type', 'smart-ai-linker'); ?></label>
                    <select id="bulk-post-type" class="widefat">
                        <?php foreach ($post_types as $post_type) : 
                            if ($post_type->name === 'attachment') continue;
                            ?>
                            <option value="<?php echo esc_attr($post_type->name); ?>">
                                <?php echo esc_html($post_type->labels->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label for="bulk-silo"><?php _e('Silo', 'smart-ai-linker'); ?></label>
                    <select id="bulk-silo" class="widefat" <?php echo empty($silos) ? 'disabled' : ''; ?>>
                        <?php if (!empty($silos)) : ?>
                            <?php foreach ($silos as $silo) : ?>
                                <option value="<?php echo $silo->id; ?>">
                                    <?php echo esc_html($silo->name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <option value=""><?php _e('No silos available', 'smart-ai-linker'); ?></option>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" id="bulk-assign-button" class="button button-primary" 
                            <?php echo empty($silos) ? 'disabled' : ''; ?>>
                        <?php _e('Assign Silo to All', 'smart-ai-linker'); ?>
                    </button>
                    <button type="button" id="bulk-analyze-button" class="button">
                        <?php _e('AI Analyze All', 'smart-ai-linker'); ?>
                    </button>
                    <span class="spinner"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Silo Modal -->
<div id="edit-silo-modal" style="display: none;">
    <div class="edit-silo-content">
        <h3><?php _e('Edit Silo', 'smart-ai-linker'); ?></h3>
        <form id="edit-silo-form">
            <input type="hidden" id="edit-silo-id" name="id">
            <div class="form-field">
                <label for="edit-silo-name"><?php _e('Name', 'smart-ai-linker'); ?></label>
                <input type="text" id="edit-silo-name" name="name" required>
            </div>
            <div class="form-field">
                <label for="edit-silo-description"><?php _e('Description', 'smart-ai-linker'); ?></label>
                <textarea id="edit-silo-description" name="description" rows="3"></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="button button-secondary cancel-edit">
                    <?php _e('Cancel', 'smart-ai-linker'); ?>
                </button>
                <button type="submit" class="button button-primary">
                    <?php _e('Save Changes', 'smart-ai-linker'); ?>
                </button>
                <span class="spinner"></span>
            </div>
        </form>
    </div>
</div>
