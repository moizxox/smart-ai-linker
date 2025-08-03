<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1><?php _e('Smart AI Linker - Unlock Plugin', 'smart-ai-linker'); ?></h1>
    
    <?php
    // Handle password submission
    if (isset($_POST['unlock_password']) && wp_verify_nonce($_POST['_wpnonce'], 'smart_ai_unlock')) {
        $password = sanitize_text_field($_POST['unlock_password']);
        
        if ($password === 'tamir@gmail.com') {
            update_option('smart_ai_linker_unlocked', '1');
            echo '<div class="notice notice-success is-dismissible"><p><strong>Success!</strong> Plugin has been unlocked. You can now access all features.</p></div>';
            echo '<script>setTimeout(function(){ window.location.href = "' . admin_url('admin.php?page=smart-ai-linker') . '"; }, 2000);</script>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Error!</strong> Incorrect password. Please try again.</p></div>';
        }
    }
    
    // Check if already unlocked
    if (get_option('smart_ai_linker_unlocked', '0') === '1') {
        echo '<div class="notice notice-info is-dismissible"><p><strong>Plugin is already unlocked!</strong> You can access all features.</p></div>';
        echo '<p><a href="' . admin_url('admin.php?page=smart-ai-linker') . '" class="button button-primary">Go to Plugin Dashboard</a></p>';
        return;
    }
    ?>
    
    <div class="card" style="max-width: 500px; margin-top: 20px;">
        <h2><?php _e('Enter Password to Unlock', 'smart-ai-linker'); ?></h2>
        <p><?php _e('This plugin is locked for security. Please enter the password to unlock all features.', 'smart-ai-linker'); ?></p>
        
        <form method="post" action="">
            <?php wp_nonce_field('smart_ai_unlock'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="unlock_password"><?php _e('Password', 'smart-ai-linker'); ?></label>
                    </th>
                    <td>
                        <input type="password" 
                               id="unlock_password" 
                               name="unlock_password" 
                               class="regular-text" 
                               required 
                               autocomplete="off" />
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" 
                       name="submit" 
                       id="submit" 
                       class="button button-primary" 
                       value="<?php _e('Unlock Plugin', 'smart-ai-linker'); ?>" />
            </p>
        </form>
    </div>
    
    <div class="card" style="max-width: 500px; margin-top: 20px;">
        <h3><?php _e('Security Information', 'smart-ai-linker'); ?></h3>
        <ul>
            <li><?php _e('Only authorized users can unlock this plugin.', 'smart-ai-linker'); ?></li>
            <li><?php _e('The plugin will remain locked until the correct password is entered.', 'smart-ai-linker'); ?></li>
            <li><?php _e('All plugin features are completely hidden from unauthorized users.', 'smart-ai-linker'); ?></li>
            <li><?php _e('Contact the administrator if you need access to this plugin.', 'smart-ai-linker'); ?></li>
        </ul>
    </div>
</div>

<style>
.card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}
</style> 