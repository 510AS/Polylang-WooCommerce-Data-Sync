<?php
/**
 * Uninstall handler for Polylang WooCommerce Data Sync
 * 
 * Removes plugin data when the plugin is deleted
 */

// Exit if accessed directly or not called by WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Define plugin slug
define('POLYLANG_WC_SYNC_PLUGIN_SLUG', 'polylang-wc-sync');

/**
 * Clean up plugin options from database
 */
function polylang_wc_sync_cleanup() {
    global $wpdb;
    
    // Remove plugin options
    delete_option('polylang_wc_sync_version');
    delete_option('polylang_wc_sync_settings');
    
    // Drop sync conflicts table
    $table_name = $wpdb->prefix . 'polylang_wc_sync_conflicts';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }
    
    // Remove scheduled hooks
    wp_clear_scheduled_hook('polylang_wc_sync_cleanup_old_conflicts');
    
    // Remove user meta related to sync settings if any
    $wpdb->query(
        "DELETE FROM {$wpdb->usermeta} 
        WHERE meta_key LIKE 'polylang_wc_sync_%'"
    );
    
    // Remove product meta timestamps
    $wpdb->query(
        "DELETE FROM {$wpdb->postmeta} 
        WHERE meta_key = '_polylang_wc_sync_timestamp'"
    );
}

// Execute cleanup
polylang_wc_sync_cleanup();
