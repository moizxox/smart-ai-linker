<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package   Smart_AI_Linker
 * @author    M Moiz <moizxox@gmail.com>
 * @license   GPL-2.0+
 * @link      https://github.com/moizxox/smart-ai-linker
 * @copyright 2023 M Moiz
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Only remove ALL plugin data if the "SMART_AI_LINKER_REMOVE_ALL_DATA" constant is set to true in wp-config.php
if (defined('SMART_AI_LINKER_REMOVE_ALL_DATA') && SMART_AI_LINKER_REMOVE_ALL_DATA === true) {
    global $wpdb;
    
    // Delete options
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE 'smart_ai_linker_%';");
    
    // Delete post meta
    $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_smart_ai_linker_%';");
    
    // Delete any transients we've set
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_smart_ai_linker_%';");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_smart_ai_linker_%';");
    
    // Clear any cached data that might be in the object cache
    wp_cache_flush();
}
