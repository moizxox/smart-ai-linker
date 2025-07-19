<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap smart-ai-dashboard">
    <h1><?php _e('Smart AI Linker Dashboard', 'smart-ai-linker'); ?></h1>
    
    <div class="dashboard-widgets">
        <!-- Stats Overview -->
        <div class="dashboard-card">
            <h2><?php _e('Content Overview', 'smart-ai-linker'); ?></h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($post_count); ?></span>
                    <span class="stat-label"><?php _e('Posts', 'smart-ai-linker'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($page_count); ?></span>
                    <span class="stat-label"><?php _e('Pages', 'smart-ai-linker'); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-number"><?php echo number_format($silo_count); ?></span>
                    <span class="stat-label"><?php _e('Silos', 'smart-ai-linker'); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="dashboard-card">
            <h2><?php _e('Quick Actions', 'smart-ai-linker'); ?></h2>
            <div class="quick-actions">
                <button type="button" class="button button-primary" id="analyze-all-content">
                    <?php _e('Analyze All Content', 'smart-ai-linker'); ?>
                </button>
                <button type="button" class="button" id="generate-links">
                    <?php _e('Generate Internal Links', 'smart-ai-linker'); ?>
                </button>
                <button type="button" class="button" id="view-reports">
                    <?php _e('View Reports', 'smart-ai-linker'); ?>
                </button>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="dashboard-card full-width">
            <h2><?php _e('Recent Activity', 'smart-ai-linker'); ?></h2>
            <?php if (!empty($recent_activity)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Post', 'smart-ai-linker'); ?></th>
                            <th><?php _e('Silo', 'smart-ai-linker'); ?></th>
                            <th><?php _e('Date', 'smart-ai-linker'); ?></th>
                            <th><?php _e('Actions', 'smart-ai-linker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_activity as $activity) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo get_edit_post_link($activity->ID); ?>">
                                        <?php echo esc_html($activity->post_title); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($activity->silo_name); ?></td>
                                <td>
                                    <?php echo date_i18n(
                                        get_option('date_format') . ' ' . get_option('time_format'),
                                        strtotime($activity->created_at)
                                    ); ?>
                                </td>
                                <td>
                                    <a href="#" class="button button-small analyze-single" 
                                       data-post-id="<?php echo $activity->ID; ?>">
                                        <?php _e('Re-analyze', 'smart-ai-linker'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No recent activity found.', 'smart-ai-linker'); ?></p>
            <?php endif; ?>
        </div>
        
        <!-- AI Recommendations -->
        <div class="dashboard-card full-width">
            <h2><?php _e('AI Recommendations', 'smart-ai-linker'); ?></h2>
            <div class="ai-recommendations">
                <div class="recommendation">
                    <h3><?php _e('Content Gaps', 'smart-ai-linker'); ?></h3>
                    <p><?php _e('Analyzing your content to find opportunities for new silos...', 'smart-ai-linker'); ?></p>
                    <button type="button" class="button button-secondary" id="analyze-gaps">
                        <?php _e('Run Analysis', 'smart-ai-linker'); ?>
                    </button>
                </div>
                <div class="recommendation">
                    <h3><?php _e('Internal Linking', 'smart-ai-linker'); ?></h3>
                    <p><?php _e('Optimize internal linking structure...', 'smart-ai-linker'); ?></p>
                    <button type="button" class="button button-secondary" id="optimize-links">
                        <?php _e('Optimize Now', 'smart-ai-linker'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Progress Modal -->
<div id="ai-progress-modal" style="display: none;">
    <div class="ai-progress-content">
        <h3 id="progress-title"><?php _e('Processing...', 'smart-ai-linker'); ?></h3>
        <div class="progress-bar-container">
            <div class="progress-bar" style="width: 0%;"></div>
        </div>
        <p id="progress-message"><?php _e('Please wait while we process your request...', 'smart-ai-linker'); ?></p>
        <div id="progress-details"></div>
    </div>
</div>
