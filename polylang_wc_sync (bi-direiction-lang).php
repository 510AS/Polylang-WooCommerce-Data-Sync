<?php
/**
 * Plugin Name: Polylang WooCommerce Data Sync
 * Plugin URI: https://github.com/510AS/Polylang-WooCommerce-Data-Sync
 * Description: Synchronizes critical WooCommerce product data from default language to translations when using Polylang free version
 * Version: 1.0.0
 * Author: Ahmed Shehata
 * Author URI: https://www.linkedin.com/in/ahmed-shehata-0a171820b
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Text Domain: polylang-wc-sync
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('POLYLANG_WC_SYNC_VERSION', '1.0.0');
define('POLYLANG_WC_SYNC_PLUGIN_FILE', __FILE__);
define('POLYLANG_WC_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Include admin page file
require_once(POLYLANG_WC_SYNC_PLUGIN_DIR . 'includes/admin-page.php');

/**
 * Plugin activation hook - Create database table for conflicts
 */
function polylang_wc_sync_activate() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'polylang_wc_sync_conflicts';
    $charset_collate = $wpdb->get_charset_collate();
    
    // Check if table already exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
        return;
    }
    
    // Create conflicts table
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        source_id mediumint(9) NOT NULL,
        source_lang varchar(10) NOT NULL,
        target_id mediumint(9) NOT NULL,
        target_lang varchar(10) NOT NULL,
        source_title varchar(255) NOT NULL,
        status varchar(20) DEFAULT 'unresolved',
        notes longtext,
        PRIMARY KEY (id),
        KEY source_id (source_id),
        KEY target_id (target_id),
        KEY timestamp (timestamp),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
register_activation_hook(POLYLANG_WC_SYNC_PLUGIN_FILE, 'polylang_wc_sync_activate');

/**
 * Error logging function for debugging
 * 
 * @param string $message Log message
 * @param array $data Additional data to log
 * @param string $level Log level (info, warning, error)
 */
function polylang_wc_sync_log($message, $data = [], $level = 'info') {
    // Only log if WP_DEBUG is enabled
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $log_entry = sprintf(
        '[%s] [%s] %s',
        current_time('Y-m-d H:i:s'),
        strtoupper($level),
        $message
    );
    
    if (!empty($data)) {
        $log_entry .= ' | Data: ' . wp_json_encode($data);
    }
    
    // Log to WordPress debug.log
    error_log($log_entry);
}

/**
 * Check if required plugins are active
 */
function polylang_wc_sync_check_dependencies() {
    $missing_plugins = [];
    
    // Check if Polylang is active
    if (!function_exists('pll_get_post_translations')) {
        $missing_plugins[] = 'Polylang';
    }
    
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        $missing_plugins[] = 'WooCommerce';
    }
    
    return $missing_plugins;
}

/**
 * Display admin notice for missing dependencies
 */
function polylang_wc_sync_admin_notice() {
    $missing = polylang_wc_sync_check_dependencies();
    
    if (!empty($missing)) {
        $plugin_names = implode(' and ', $missing);
        ?>
        <div class="notice notice-error">
            <p>
                <strong>Polylang WooCommerce Data Sync</strong> requires <?php echo esc_html($plugin_names); ?> to be installed and activated.
                <br>
                Please install and activate the required plugin(s) to use this plugin.
            </p>
        </div>
        <?php
        
        // Deactivate this plugin
        deactivate_plugins(plugin_basename(__FILE__));
    }
}
add_action('admin_notices', 'polylang_wc_sync_admin_notice');

/**
 * Add plugin action links in plugins listing table
 */
function polylang_wc_sync_plugin_action_links($links, $file) {
    // Only apply to this plugin
    if ($file !== plugin_basename(__FILE__)) {
        return $links;
    }
    
    // Add Settings link
    $settings_link = '<a href="' . esc_url(admin_url('edit.php?post_type=product&page=polylang-wc-sync')) . '">' . __('Settings', 'polylang-wc-sync') . '</a>';
    array_unshift($links, $settings_link);
    
    // Check if required plugins are active
    $woocommerce_active = class_exists('WooCommerce');
    $polylang_active = function_exists('pll_get_post_translations');
    
    // If both required plugins are active, disable deactivate link
    if ($woocommerce_active && $polylang_active) {
        // Remove deactivate link
        if (isset($links['deactivate'])) {
            $links['deactivate'] = '<span style="color: #999; cursor: not-allowed;">' . __('Deactivate', 'polylang-wc-sync') . '</span>';
        }
    }
    
    return $links;
}
add_filter('plugin_action_links', 'polylang_wc_sync_plugin_action_links', 10, 2);

/**
 * Display dependency message in plugin row on plugins page
 */
function polylang_wc_sync_plugin_row_meta($plugin_meta, $plugin_file) {
    // Check if this is our plugin
    if ($plugin_file !== plugin_basename(__FILE__)) {
        return $plugin_meta;
    }
    
    // Check if required plugins are active
    $woocommerce_active = class_exists('WooCommerce');
    $polylang_active = function_exists('pll_get_post_translations');
    
    // If both required plugins are active, add dependency message
    if ($woocommerce_active && $polylang_active) {
        $plugin_meta[] = '<span style="color: #dadd19ff; font-weight: bold;">' . 
            __('⚠ Can be deactivated only if WooCommerce and Polylang are deactivated', 'polylang-wc-sync') . 
            '</span>';
        $plugin_meta[] = '<span style="color: #999; font-style: italic;">' . 
            __('Cannot be deactivated while required plugins are active', 'polylang-wc-sync') . 
            '</span>';
    }
    
    return $plugin_meta;
}
add_filter('plugin_row_meta', 'polylang_wc_sync_plugin_row_meta', 10, 2);

/**
 * Check dependencies before initializing the plugin
 */
function polylang_wc_sync_init() {
    $missing = polylang_wc_sync_check_dependencies();
    
    if (empty($missing)) {
        // Initialize the plugin only if dependencies are met
        $sync = new Polylang_WooCommerce_Sync();
    }
}
add_action('plugins_loaded', 'polylang_wc_sync_init');

class Polylang_WooCommerce_Sync {
    
    /**
     * Flag to prevent infinite loops during sync
     */
    private $syncing = false;
    
    /**
     * Track last sync timestamps to prevent conflicts
     */
    private $sync_timestamps = [];
    
    /**
     * Fields to synchronize across translations
     */
    private $sync_fields = [
        '_sku',
        '_price',                // Calculated selling price (CRITICAL for lookup table)
        '_regular_price',
        '_sale_price',
        '_stock',
        '_stock_status',
        '_manage_stock',
        '_backorders',
        '_weight',
        '_length',
        '_width',
        '_height',
        '_product_image_gallery',
        // Additional safe fields
        '_upsell_ids',           // Up-sells
        '_crosssell_ids',        // Cross-sells
        '_download_limit',       // Download limit for digital products
        '_download_expiry',      // Download expiry
        '_downloadable',         // Is downloadable
        '_virtual',              // Is virtual
        '_sold_individually',    // Sold individually
        '_purchase_note',        // Purchase note
        '_featured',             // Featured product
        '_tax_status',           // Tax status
        '_tax_class',            // Tax class
        'total_sales',           // Total sales count
        '_wc_average_rating',    // Average rating
        '_wc_review_count',      // Review count
        // Product identifiers (GTIN, UPC, EAN, ISBN, MPN)
        '_wpm_gtin_code',        // GTIN from WooCommerce Product Manager
        '_hwp_product_gtin',     // GTIN from other plugins
        '_gtin',                 // Generic GTIN
        '_upc',                  // UPC
        '_ean',                  // EAN
        '_isbn',                 // ISBN
        '_mpn',                  // Manufacturer Part Number
        'hwp_product_gtin',      // GTIN without underscore
        'hwp_var_gtin',          // Variation GTIN
        'global_unique_id',      // Global Unique ID
        '_global_unique_id'      // Global Unique ID with underscore
    ];
    
    /**
     * Constructor - register hooks
     */
    public function __construct() {
        // Hook to fill empty fields BEFORE WooCommerce processing
        add_action('save_post_product', [$this, 'fill_empty_fields_from_translation'], 5, 3);
        
        // Hook into product save for bi-directional sync
        add_action('woocommerce_update_product', [$this, 'sync_product_data'], 20, 1);
        add_action('woocommerce_new_product', [$this, 'sync_product_data'], 20, 1);
        
        // Update lookup table AFTER WooCommerce processes everything
        add_action('woocommerce_update_product', [$this, 'update_lookup_after_save'], 999, 1);
        add_action('woocommerce_new_product', [$this, 'update_lookup_after_save'], 999, 1);
        
        // Hook into stock reduction from orders
        add_action('woocommerce_reduce_order_stock', [$this, 'sync_stock_after_order'], 10, 1);
        
        // Hook into variable product variation save
        add_action('woocommerce_save_product_variation', [$this, 'sync_variation_data'], 20, 2);
        
        // Hook into downloadable files sync
        add_action('woocommerce_process_product_meta', [$this, 'sync_downloadable_files'], 30, 1);
        
        // Sync taxonomies (categories, tags, shipping class)
        add_action('set_object_terms', [$this, 'sync_product_terms'], 10, 6);
        
        // Sync reviews when posted
        add_action('comment_post', [$this, 'sync_review_on_post'], 10, 3);
        add_action('wp_set_comment_status', [$this, 'sync_review_on_status_change'], 10, 2);
        
        // Hook to pre-fill data when creating new translation
        add_filter('default_content', [$this, 'prefill_translation_data'], 10, 2);
        add_filter('default_title', [$this, 'prefill_translation_title'], 10, 2);
        add_action('add_meta_boxes', [$this, 'prefill_product_meta'], 1);
        
        // Add admin menu for bulk sync
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // AJAX handlers for bulk sync
        add_action('wp_ajax_polylang_wc_get_products', [$this, 'ajax_get_products']);
        add_action('wp_ajax_polylang_wc_sync_product', [$this, 'ajax_sync_product']);
        add_action('wp_ajax_polylang_wc_get_conflicts', [$this, 'ajax_get_conflicts']);
    }
    
    /**
     * Main sync function - BI-DIRECTIONAL (ANY language → ALL languages)
     * 
     * @param int $product_id Product ID that was saved
     */
    public function sync_product_data($product_id) {
        // Prevent infinite loops
        if ($this->syncing) {
            return;
        }
        
        // Check if Polylang is active
        if (!function_exists('pll_get_post_language') || !function_exists('pll_get_post_translations')) {
            return;
        }
        
        // Get all translations of this product
        $translations = pll_get_post_translations($product_id);
        
        if (empty($translations) || count($translations) <= 1) {
            return;
        }
        
        // Set syncing flag
        $this->syncing = true;
        
        // BI-DIRECTIONAL SYNC STRATEGY:
        // The product being saved is the "master" for this sync
        // Update its timestamp and sync TO all others
        
        // Update timestamp for this product
        $this->update_sync_timestamp($product_id);
        
        // Get the product being saved
        $master_product = wc_get_product($product_id);
        
        if (!$master_product) {
            $this->syncing = false;
            return;
        }
        
        // Sync FROM this product TO all translations
        foreach ($translations as $lang => $translation_id) {
            // Skip the product being saved (master)
            if ($translation_id === $product_id) {
                continue;
            }
            
            // Check if target was recently edited (conflict detection)
            if ($this->is_recently_edited($translation_id, 30)) {
                // Target was edited in last 30 seconds - potential conflict
                $this->log_sync_conflict($product_id, $translation_id);
                // Continue anyway - last save wins
            }
            
            // Sync meta fields
            $this->sync_meta_fields($product_id, $translation_id);
            
            // Sync menu order
            $this->sync_menu_order($product_id, $translation_id);
            
            // Sync reviews and comments
            $this->sync_reviews($product_id, $translation_id);
            
            // Sync featured status
            $this->sync_featured_status($product_id, $translation_id);
            
            // Sync product type
            $this->sync_product_type($product_id, $translation_id);
            
            // Sync featured image
            $this->sync_featured_image($product_id, $translation_id);
            
            // Sync product attributes (for variable products)
            if ($master_product->is_type('variable')) {
                $this->sync_product_attributes($product_id, $translation_id);
                $this->sync_variations($master_product, $translation_id);
            }
            
            // Update timestamp for synced product
            $this->update_sync_timestamp($translation_id);
            
            // Clear cache for translation (lookup table updated separately)
            wc_delete_product_transients($translation_id);
        }
        
        // Reset syncing flag
        $this->syncing = false;
    }
    
    /**
     * Update lookup table AFTER all WooCommerce processing is complete
     * This ensures _price is calculated before we update the lookup table
     * 
     * @param int $product_id Product ID
     */
    public function update_lookup_after_save($product_id) {
        if ($this->syncing) {
            return;
        }
        
        if (!function_exists('pll_get_post_translations')) {
            return;
        }
        
        // Update lookup table for this product
        $this->update_wc_lookup_table($product_id);
        
        // Update lookup table for all translations
        $translations = pll_get_post_translations($product_id);
        
        if (!empty($translations)) {
            foreach ($translations as $lang => $translation_id) {
                if ($translation_id !== $product_id) {
                    $this->update_wc_lookup_table($translation_id);
                }
            }
        }
    }
    
    /**
     * Update sync timestamp for a product
     * 
     * @param int $product_id Product ID
     */
    private function update_sync_timestamp($product_id) {
        update_post_meta($product_id, '_polylang_wc_sync_timestamp', current_time('timestamp'));
    }
    
    /**
     * Check if product was recently edited (conflict detection)
     * 
     * @param int $product_id Product ID
     * @param int $seconds Time window in seconds
     * @return bool True if edited within time window
     */
    private function is_recently_edited($product_id, $seconds = 30) {
        $last_sync = get_post_meta($product_id, '_polylang_wc_sync_timestamp', true);
        
        if (empty($last_sync)) {
            return false;
        }
        
        $time_diff = current_time('timestamp') - $last_sync;
        
        return $time_diff < $seconds;
    }
    
    /**
     * Log sync conflict for admin review
     * 
     * @param int $source_id Source product ID
     * @param int $target_id Target product ID
     */
    private function log_sync_conflict($source_id, $target_id) {
        global $wpdb;
        
        $source_lang = pll_get_post_language($source_id);
        $target_lang = pll_get_post_language($target_id);
        $source_title = get_the_title($source_id);
        
        // Log to error log for debugging
        polylang_wc_sync_log(
            'Sync conflict detected',
            [
                'source_id' => $source_id,
                'target_id' => $target_id,
                'source_lang' => $source_lang,
                'target_lang' => $target_lang,
            ],
            'warning'
        );
        
        // Insert into database table
        $table_name = $wpdb->prefix . 'polylang_wc_sync_conflicts';
        
        $wpdb->insert(
            $table_name,
            [
                'source_id' => $source_id,
                'source_lang' => $source_lang,
                'target_id' => $target_id,
                'target_lang' => $target_lang,
                'source_title' => $source_title,
                'status' => 'unresolved',
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s']
        );
    }
    
    /**
     * Update WooCommerce product lookup table
     * 
     * @param int $product_id Product ID to update
     */
    private function update_wc_lookup_table($product_id) {
        global $wpdb;
        
        // Get product object
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return;
        }
        
        // Check if HPOS (High-Performance Order Storage) is enabled
        // WooCommerce uses wp_wc_product_meta_lookup table for product data
        
        $table_name = $wpdb->prefix . 'wc_product_meta_lookup';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            return;
        }
        
        // Prepare data for lookup table
        $data = [
            'product_id' => $product_id,
            'sku' => $product->get_sku(),
            'virtual' => $product->get_virtual() ? 1 : 0,
            'downloadable' => $product->get_downloadable() ? 1 : 0,
            'min_price' => $product->get_price(),
            'max_price' => $product->get_price(),
            'onsale' => $product->is_on_sale() ? 1 : 0,
            'stock_quantity' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'rating_count' => $product->get_rating_count(),
            'average_rating' => $product->get_average_rating(),
            'total_sales' => get_post_meta($product_id, 'total_sales', true),
            'tax_status' => $product->get_tax_status(),
            'tax_class' => $product->get_tax_class(),
        ];
        
        // Check if record exists
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT product_id FROM $table_name WHERE product_id = %d",
                $product_id
            )
        );
        
        if ($exists) {
            // Update existing record
            $wpdb->update(
                $table_name,
                $data,
                ['product_id' => $product_id],
                ['%d', '%s', '%d', '%d', '%f', '%f', '%d', '%f', '%s', '%d', '%f', '%d', '%s', '%s'],
                ['%d']
            );
        } else {
            // Insert new record
            $wpdb->insert(
                $table_name,
                $data,
                ['%d', '%s', '%d', '%d', '%f', '%f', '%d', '%f', '%s', '%d', '%f', '%d', '%s', '%s']
            );
        }
        
        // For variable products, update variation lookup
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $this->update_wc_lookup_table($variation_id);
            }
        }
    }
    
    /**
     * Sync meta fields between products
     * 
     * @param int $master_id Master product ID
     * @param int $translation_id Translation product ID
     */
    private function sync_meta_fields($master_id, $translation_id) {
        foreach ($this->sync_fields as $meta_key) {
            $value = get_post_meta($master_id, $meta_key, true);
            update_post_meta($translation_id, $meta_key, $value);
        }
    }
    
    /**
     * Sync product type (simple, variable, grouped, external)
     * 
     * @param int $master_id Master product ID
     * @param int $translation_id Translation product ID
     */
    private function sync_product_type($master_id, $translation_id) {
        // Get product type from master
        $product_types = wp_get_post_terms($master_id, 'product_type', ['fields' => 'slugs']);
        
        if (!empty($product_types)) {
            $product_type = $product_types[0];
            
            // Set product type on translation
            wp_set_object_terms($translation_id, $product_type, 'product_type');
        }
    }
    
    /**
     * Sync featured product status
     * WooCommerce stores featured status in product_visibility taxonomy
     * 
     * @param int $master_id Master product ID
     * @param int $translation_id Translation product ID
     */
    private function sync_featured_status($master_id, $translation_id) {
        // Check if master product is featured
        $is_featured = has_term('featured', 'product_visibility', $master_id);
        
        if ($is_featured) {
            // Add featured term to translation
            wp_set_object_terms($translation_id, 'featured', 'product_visibility', true);
        } else {
            // Remove featured term from translation
            wp_remove_object_terms($translation_id, 'featured', 'product_visibility');
        }
        
        // Also sync the _featured meta for backwards compatibility
        $featured_meta = get_post_meta($master_id, '_featured', true);
        update_post_meta($translation_id, '_featured', $featured_meta);
    }
    
    /**
     * Sync featured image
     * 
     * @param int $master_id Master product ID
     * @param int $translation_id Translation product ID
     */
    private function sync_featured_image($master_id, $translation_id) {
        $thumbnail_id = get_post_thumbnail_id($master_id);
        
        if ($thumbnail_id) {
            set_post_thumbnail($translation_id, $thumbnail_id);
        } else {
            delete_post_thumbnail($translation_id);
        }
        
        // Also sync product gallery
        $gallery_ids = get_post_meta($master_id, '_product_image_gallery', true);
        update_post_meta($translation_id, '_product_image_gallery', $gallery_ids);
    }
    
    /**
     * Sync menu order (post field, not meta)
     * Title, description, and short description are NOT synced to preserve translations
     * 
     * @param int $master_id Master product ID
     * @param int $translation_id Translation product ID
     */
    private function sync_menu_order($master_id, $translation_id) {
        global $wpdb;
        
        // Get menu_order from master product
        $menu_order = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT menu_order FROM {$wpdb->posts} WHERE ID = %d",
                $master_id
            )
        );
        
        // Update menu_order in translation (skip title, content, excerpt)
        $wpdb->update(
            $wpdb->posts,
            ['menu_order' => $menu_order],
            ['ID' => $translation_id],
            ['%d'],
            ['%d']
        );
    }
    
    /**
     * Sync review immediately when posted
     * 
     * @param int $comment_id Comment ID
     * @param int|string $comment_approved Comment approval status
     * @param array $commentdata Comment data
     */
    public function sync_review_on_post($comment_id, $comment_approved, $commentdata) {
        if ($this->syncing) {
            return;
        }
        
        // Check if this is a product review
        $comment = get_comment($comment_id);
        if (!$comment) {
            return;
        }
        
        $product_id = $comment->comment_post_ID;
        
        // Check if this is a product (WooCommerce reviews are regular comments on products)
        if (get_post_type($product_id) !== 'product') {
            return;
        }
        
        if (!function_exists('pll_get_post_translations')) {
            return;
        }
        
        // Get all translations
        $translations = pll_get_post_translations($product_id);
        
        if (empty($translations) || count($translations) <= 1) {
            return;
        }
        
        $this->syncing = true;
        
        // Copy this review to all translations
        foreach ($translations as $lang => $translation_id) {
            if ($translation_id === $product_id) {
                continue;
            }
            
            // Check if this review already exists (by author email AND content)
            $existing = get_comments([
                'post_id' => $translation_id,
                'author_email' => $comment->comment_author_email,
                'status' => 'all'
            ]);
            
            // Check if exact same comment already exists
            $duplicate_found = false;
            foreach ($existing as $existing_comment) {
                if ($existing_comment->comment_content === $comment->comment_content) {
                    $duplicate_found = true;
                    break;
                }
            }
            
            // Skip if duplicate exists
            if ($duplicate_found) {
                continue;
            }
            
            // Create comment data
            $comment_data = [
                'comment_post_ID' => $translation_id,
                'comment_author' => $comment->comment_author,
                'comment_author_email' => $comment->comment_author_email,
                'comment_author_url' => $comment->comment_author_url,
                'comment_author_IP' => $comment->comment_author_IP,
                'comment_content' => $comment->comment_content,
                'comment_type' => $comment->comment_type,
                'comment_parent' => 0,
                'user_id' => $comment->user_id,
                'comment_date' => $comment->comment_date,
                'comment_date_gmt' => $comment->comment_date_gmt,
                'comment_approved' => $comment->comment_approved,
            ];
            
            $new_comment_id = wp_insert_comment($comment_data);
            
            // Copy meta data
            if ($new_comment_id) {
                $rating = get_comment_meta($comment_id, 'rating', true);
                if ($rating) {
                    update_comment_meta($new_comment_id, 'rating', $rating);
                }
                
                $verified = get_comment_meta($comment_id, 'verified', true);
                if ($verified) {
                    update_comment_meta($new_comment_id, 'verified', $verified);
                }
            }
            
            // Update review stats
            $this->update_review_stats($translation_id);
        }
        
        // Update stats for the original product too
        $this->update_review_stats($product_id);
        
        $this->syncing = false;
    }
    
    /**
     * Sync review status changes (approve, spam, trash)
     * 
     * @param int $comment_id Comment ID
     * @param string $status New status
     */
    public function sync_review_on_status_change($comment_id, $status) {
        if ($this->syncing) {
            return;
        }
        
        $comment = get_comment($comment_id);
        if (!$comment) {
            return;
        }
        
        $product_id = $comment->comment_post_ID;
        
        if (get_post_type($product_id) !== 'product') {
            return;
        }
        
        if (!function_exists('pll_get_post_translations')) {
            return;
        }
        
        $translations = pll_get_post_translations($product_id);
        
        if (empty($translations) || count($translations) <= 1) {
            return;
        }
        
        $this->syncing = true;
        
        // Find and update matching comments in translations
        foreach ($translations as $lang => $translation_id) {
            if ($translation_id === $product_id) {
                continue;
            }
            
            // Find matching comment by author email and content
            $matching_comments = get_comments([
                'post_id' => $translation_id,
                'author_email' => $comment->comment_author_email,
                'status' => 'all'
            ]);
            
            foreach ($matching_comments as $match) {
                if ($match->comment_content === $comment->comment_content) {
                    wp_set_comment_status($match->comment_ID, $status);
                }
            }
            
            // Update review stats
            $this->update_review_stats($translation_id);
        }
        
        $this->syncing = false;
    }
    
    /**
     * Sync reviews and comments between products
     * Copies all reviews from master product to translation
     * 
     * @param int $master_id Master product ID
     * @param int $translation_id Translation product ID
     */
    private function sync_reviews($master_id, $translation_id) {
        global $wpdb;
        
        // Get all reviews from master product
        $master_reviews = get_comments([
            'post_id' => $master_id,
            'status' => 'approve'
        ]);
        
        // Get existing reviews on translation to avoid duplicates
        $existing_reviews = get_comments([
            'post_id' => $translation_id,
            'status' => 'approve'
        ]);
        
        // Create a map of existing reviews by author and content
        $existing_map = [];
        foreach ($existing_reviews as $review) {
            $key = $review->comment_author_email . '|' . $review->comment_content;
            $existing_map[$key] = $review->comment_ID;
        }
        
        // Copy reviews that don't exist in translation
        foreach ($master_reviews as $review) {
            $key = $review->comment_author_email . '|' . $review->comment_content;
            
            // Skip if this review already exists on the translation
            if (isset($existing_map[$key])) {
                // Update rating if needed
                $master_rating = get_comment_meta($review->comment_ID, 'rating', true);
                if ($master_rating) {
                    update_comment_meta($existing_map[$key], 'rating', $master_rating);
                }
                continue;
            }
            
            // Create new comment for translation
            $comment_data = [
                'comment_post_ID' => $translation_id,
                'comment_author' => $review->comment_author,
                'comment_author_email' => $review->comment_author_email,
                'comment_author_url' => $review->comment_author_url,
                'comment_content' => $review->comment_content,
                'comment_type' => 'review',
                'comment_parent' => 0,
                'user_id' => $review->user_id,
                'comment_date' => $review->comment_date,
                'comment_date_gmt' => $review->comment_date_gmt,
                'comment_approved' => $review->comment_approved,
            ];
            
            $new_comment_id = wp_insert_comment($comment_data);
            
            // Copy rating meta
            if ($new_comment_id) {
                $rating = get_comment_meta($review->comment_ID, 'rating', true);
                if ($rating) {
                    update_comment_meta($new_comment_id, 'rating', $rating);
                }
                
                // Copy verified buyer status
                $verified = get_comment_meta($review->comment_ID, 'verified', true);
                if ($verified) {
                    update_comment_meta($new_comment_id, 'verified', $verified);
                }
            }
        }
        
        // Update review count and average rating
        $this->update_review_stats($translation_id);
    }
    
    /**
     * Update product review statistics
     * 
     * @param int $product_id Product ID
     */
    private function update_review_stats($product_id) {
        global $wpdb;
        
        // Get all approved reviews
        $reviews = get_comments([
            'post_id' => $product_id,
            'status' => 'approve'
        ]);
        
        $total = 0;
        $count = 0;
        
        foreach ($reviews as $review) {
            $rating = get_comment_meta($review->comment_ID, 'rating', true);
            if ($rating) {
                $total += intval($rating);
                $count++;
            }
        }
        
        $average = $count > 0 ? round($total / $count, 2) : 0;
        
        // Update product meta
        update_post_meta($product_id, '_wc_review_count', $count);
        update_post_meta($product_id, '_wc_average_rating', $average);
        
        // Update comment count
        $wpdb->update(
            $wpdb->posts,
            ['comment_count' => $count],
            ['ID' => $product_id],
            ['%d'],
            ['%d']
        );
    }
    
    /**
     * Sync product attributes for variable products
     * 
     * @param int $master_id Master product ID
     * @param int $translation_id Translation product ID
     */
    private function sync_product_attributes($master_id, $translation_id) {
        $attributes = get_post_meta($master_id, '_product_attributes', true);
        
        if ($attributes) {
            update_post_meta($translation_id, '_product_attributes', $attributes);
        }
    }
    
    /**
     * Sync variations between variable products
     * Creates missing variations and syncs data for existing ones
     * 
     * @param WC_Product_Variable $master_product Master product object
     * @param int $translation_id Translation product ID
     */
    private function sync_variations($master_product, $translation_id) {
        $master_variations = $master_product->get_children();
        $translation_product = wc_get_product($translation_id);
        
        if (!$translation_product || !$translation_product->is_type('variable')) {
            return;
        }
        
        $translation_variations = $translation_product->get_children();
        
        // Create a map of existing variations by attributes
        $existing_variations_map = [];
        foreach ($translation_variations as $trans_var_id) {
            $trans_variation = wc_get_product($trans_var_id);
            if ($trans_variation) {
                $key = $this->get_variation_key($trans_variation->get_attributes());
                $existing_variations_map[$key] = $trans_var_id;
            }
        }
        
        // Process each master variation
        foreach ($master_variations as $master_var_id) {
            $master_variation = wc_get_product($master_var_id);
            
            if (!$master_variation) {
                continue;
            }
            
            $master_attributes = $master_variation->get_attributes();
            $variation_key = $this->get_variation_key($master_attributes);
            
            // Check if variation exists in translation
            if (isset($existing_variations_map[$variation_key])) {
                // Variation exists - sync data
                $trans_var_id = $existing_variations_map[$variation_key];
                
                // Sync variation meta fields (excluding _sku to avoid duplicates)
                foreach ($this->sync_fields as $meta_key) {
                    // Skip SKU for variations to prevent duplicate errors
                    if ($meta_key === '_sku') {
                        // Only sync variation SKU if it's different from parent
                        $var_sku = get_post_meta($master_var_id, '_sku', true);
                        $parent_sku = get_post_meta($master_product->get_id(), '_sku', true);
                        if ($var_sku && $var_sku !== $parent_sku) {
                            $value = get_post_meta($master_var_id, $meta_key, true);
                            update_post_meta($trans_var_id, $meta_key, $value);
                        }
                        continue;
                    }
                    
                    $value = get_post_meta($master_var_id, $meta_key, true);
                    update_post_meta($trans_var_id, $meta_key, $value);
                }
                
                // Sync variation image
                $variation_image_id = get_post_meta($master_var_id, '_thumbnail_id', true);
                if ($variation_image_id) {
                    update_post_meta($trans_var_id, '_thumbnail_id', $variation_image_id);
                }
                
                // Sync variation description
                $variation_desc = get_post_meta($master_var_id, '_variation_description', true);
                update_post_meta($trans_var_id, '_variation_description', $variation_desc);
                
            } else {
                // Variation doesn't exist - create it
                $variation = new WC_Product_Variation();
                $variation->set_parent_id($translation_id);
                $variation->set_attributes($master_attributes);
                
                // Set prices
                $variation->set_regular_price($master_variation->get_regular_price());
                $variation->set_sale_price($master_variation->get_sale_price());
                $variation->set_price($master_variation->get_price());
                
                // Set stock
                $variation->set_stock_quantity($master_variation->get_stock_quantity());
                $variation->set_stock_status($master_variation->get_stock_status());
                $variation->set_manage_stock($master_variation->get_manage_stock());
                
                // DON'T set SKU - let WooCommerce auto-generate to avoid duplicates
                // Variations can share inventory with parent via manage_stock
                
                // Set weight and dimensions
                $variation->set_weight($master_variation->get_weight());
                $variation->set_length($master_variation->get_length());
                $variation->set_width($master_variation->get_width());
                $variation->set_height($master_variation->get_height());
                
                $new_var_id = $variation->save();
                
                // After saving, copy SKU if it exists and is unique
                if ($new_var_id) {
                    $master_sku = $master_variation->get_sku();
                    if ($master_sku) {
                        // Use direct meta update to bypass validation
                        update_post_meta($new_var_id, '_sku', $master_sku);
                    }
                }
                
                // Copy all other meta fields (excluding _sku as we handled it above)
                if ($new_var_id) {
                    foreach ($this->sync_fields as $meta_key) {
                        // Skip SKU - already handled above
                        if ($meta_key === '_sku') {
                            continue;
                        }
                        
                        $value = get_post_meta($master_var_id, $meta_key, true);
                        if ($value !== '' && $value !== false) {
                            update_post_meta($new_var_id, $meta_key, $value);
                        }
                    }
                    
                    // Copy variation image
                    $variation_image_id = get_post_meta($master_var_id, '_thumbnail_id', true);
                    if ($variation_image_id) {
                        update_post_meta($new_var_id, '_thumbnail_id', $variation_image_id);
                    }
                }
            }
        }
        
        // Update translation product to refresh variation data
        WC_Product_Variable::sync($translation_id);
    }
    
    /**
     * Generate a unique key for variation attributes
     * 
     * @param array $attributes Variation attributes
     * @return string Unique key
     */
    private function get_variation_key($attributes) {
        ksort($attributes);
        return md5(serialize($attributes));
    }
    
    /**
     * Check if variation attributes match
     * 
     * @param array $attrs1 First set of attributes
     * @param array $attrs2 Second set of attributes
     * @return bool True if attributes match
     */
    private function attributes_match($attrs1, $attrs2) {
        if (count($attrs1) !== count($attrs2)) {
            return false;
        }
        
        foreach ($attrs1 as $key => $value) {
            if (!isset($attrs2[$key]) || $attrs2[$key] !== $value) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Sync stock after order is placed
     * 
     * @param WC_Order $order Order object
     */
    public function sync_stock_after_order($order) {
        if ($this->syncing) {
            return;
        }
        
        if (!function_exists('pll_get_post_language') || !function_exists('pll_get_post_translations') || !function_exists('pll_default_language')) {
            return;
        }
        
        $this->syncing = true;
        
        // Get order items
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();
            
            // Determine which ID to sync (variation or product)
            $sync_id = $variation_id ? $variation_id : $product_id;
            
            // Get product language
            $product_lang = pll_get_post_language($sync_id);
            
            if (!$product_lang) {
                continue;
            }
            
            // Get translations
            $translations = pll_get_post_translations($sync_id);
            
            if (empty($translations)) {
                continue;
            }
            
            // Get current stock from the product in order
            $stock = get_post_meta($sync_id, '_stock', true);
            $stock_status = get_post_meta($sync_id, '_stock_status', true);
            
            // Sync stock to all translations
            foreach ($translations as $lang => $translation_id) {
                if ($translation_id === $sync_id) {
                    continue;
                }
                
                update_post_meta($translation_id, '_stock', $stock);
                update_post_meta($translation_id, '_stock_status', $stock_status);
                
                // Clear cache
                wc_delete_product_transients($translation_id);
            }
        }
        
        $this->syncing = false;
    }
    
    /**
     * Force refresh WooCommerce lookup table for a product
     * 
     * @param int $product_id Product ID
     */
    public function force_refresh_lookup($product_id) {
        if (function_exists('wc_update_product_lookup_tables_column')) {
            wc_update_product_lookup_tables_column($product_id);
        } else {
            // Fallback: manually update
            $this->update_wc_lookup_table($product_id);
        }
    }
    
    /**
     * Sync variation data when saved
     * 
     * @param int $variation_id Variation ID
     * @param int $loop Loop index (not used)
     */
    public function sync_variation_data($variation_id, $loop) {
        if ($this->syncing) {
            return;
        }
        
        if (!function_exists('pll_get_post_language') || !function_exists('pll_get_post_translations')) {
            return;
        }
        
        // Get parent product ID
        $variation = wc_get_product($variation_id);
        if (!$variation) {
            return;
        }
        
        $parent_id = $variation->get_parent_id();
        
        // Trigger sync from parent product
        $this->sync_product_data($parent_id);
    }
    
    /**
     * Sync downloadable files between products
     * 
     * @param int $product_id Product ID being saved
     */
    public function sync_downloadable_files($product_id) {
        if ($this->syncing) {
            return;
        }
        
        if (!function_exists('pll_get_post_language') || !function_exists('pll_get_post_translations') || !function_exists('pll_default_language')) {
            return;
        }
        
        $product_lang = pll_get_post_language($product_id);
        $default_lang = pll_default_language();
        
        // Only sync from default language
        if ($product_lang !== $default_lang) {
            return;
        }
        
        $translations = pll_get_post_translations($product_id);
        
        if (empty($translations) || count($translations) <= 1) {
            return;
        }
        
        $this->syncing = true;
        
        // Get downloadable files from master product
        $downloadable_files = get_post_meta($product_id, '_downloadable_files', true);
        
        // Sync to translations
        foreach ($translations as $lang => $translation_id) {
            if ($lang === $default_lang || $translation_id === $product_id) {
                continue;
            }
            
            update_post_meta($translation_id, '_downloadable_files', $downloadable_files);
        }
        
        $this->syncing = false;
    }
    
    /**
     * Sync product terms (categories, tags, shipping class)
     * 
     * @param int $object_id Object ID
     * @param array $terms Array of term taxonomy IDs
     * @param array $tt_ids Array of term taxonomy IDs
     * @param string $taxonomy Taxonomy slug
     * @param bool $append Whether to append or replace
     * @param array $old_tt_ids Old term taxonomy IDs
     */
    public function sync_product_terms($object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids) {
        if ($this->syncing) {
            return;
        }
        
        // Only process product taxonomies
        $product_taxonomies = ['product_cat', 'product_tag', 'product_shipping_class'];
        
        if (!in_array($taxonomy, $product_taxonomies)) {
            return;
        }
        
        if (!function_exists('pll_get_post_language') || !function_exists('pll_get_post_translations') || !function_exists('pll_default_language')) {
            return;
        }
        
        // Check if this is a product
        if (get_post_type($object_id) !== 'product') {
            return;
        }
        
        $product_lang = pll_get_post_language($object_id);
        $default_lang = pll_default_language();
        
        // Only sync from default language
        if ($product_lang !== $default_lang) {
            return;
        }
        
        $translations = pll_get_post_translations($object_id);
        
        if (empty($translations) || count($translations) <= 1) {
            return;
        }
        
        $this->syncing = true;
        
        // Get translated term IDs for each language
        foreach ($translations as $lang => $translation_id) {
            if ($lang === $default_lang || $translation_id === $object_id) {
                continue;
            }
            
            $translated_terms = [];
            
            // Try to find translated terms
            if (function_exists('pll_get_term_translations')) {
                foreach ($tt_ids as $tt_id) {
                    $term = get_term_by('term_taxonomy_id', $tt_id);
                    if ($term) {
                        $term_translations = pll_get_term_translations($term->term_id);
                        if (isset($term_translations[$lang])) {
                            $translated_terms[] = $term_translations[$lang];
                        }
                    }
                }
            }
            
            // If no translated terms found, use original terms
            // (Better than having no categories at all)
            if (empty($translated_terms)) {
                $translated_terms = $tt_ids;
            }
            
            // Set terms for translation
            wp_set_object_terms($translation_id, $translated_terms, $taxonomy, false);
        }
        
        $this->syncing = false;
    }
    
    /**
     * Prefill product meta when creating new translation
     * Copies data from source language to show in the new translation edit screen
     */
    public function prefill_product_meta() {
        global $post, $pagenow;
        
        // Only on new post page for products
        if ($pagenow !== 'post-new.php' || !isset($_GET['from_post']) || !isset($_GET['new_lang'])) {
            return;
        }
        
        if (!isset($post) || $post->post_type !== 'product') {
            return;
        }
        
        $source_id = intval($_GET['from_post']);
        
        if (!$source_id) {
            return;
        }
        
        // Copy all WooCommerce meta from source to this new product
        foreach ($this->sync_fields as $meta_key) {
            $value = get_post_meta($source_id, $meta_key, true);
            if ($value !== '' && $value !== false) {
                update_post_meta($post->ID, $meta_key, $value);
            }
        }
        
        // Copy featured image
        $thumbnail_id = get_post_thumbnail_id($source_id);
        if ($thumbnail_id) {
            set_post_thumbnail($post->ID, $thumbnail_id);
        }
        
        // Copy product attributes
        $attributes = get_post_meta($source_id, '_product_attributes', true);
        if ($attributes) {
            update_post_meta($post->ID, '_product_attributes', $attributes);
        }
        
        // Copy downloadable files
        $files = get_post_meta($source_id, '_downloadable_files', true);
        if ($files) {
            update_post_meta($post->ID, '_downloadable_files', $files);
        }
        
        // Add JavaScript to make prefilled fields readonly with unlock option
        add_action('admin_footer', [$this, 'add_readonly_fields_script']);
    }
    
    /**
     * Add JavaScript to make prefilled WooCommerce fields readonly
     */
    public function add_readonly_fields_script() {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Add notice about auto-filled data
            $('.woocommerce_options_panel').first().prepend(
                '<div class="notice notice-info inline" style="margin: 10px 0;">' +
                '<p><strong>Auto-filled from source translation.</strong> Fields are read-only. Click "Enable Editing" to modify values.</p>' +
                '<button type="button" class="button" id="enable-editing-btn">Enable Editing</button>' +
                '</div>'
            );
            
            // Make price, SKU, and stock fields readonly
            var readonlyFields = [
                '#_regular_price',
                '#_sale_price', 
                '#_sku',
                '#_stock',
                '#_weight',
                '#_length',
                '#_width',
                '#_height',
                'input[name="_weight"]',
                'input[name="_length"]',
                'input[name="_width"]',
                'input[name="_height"]',
                '#global_unique_id',
                '#_global_unique_id',
                '#_gtin',
                '#_upc',
                '#_ean',
                '#_isbn',
                '#_mpn',
                '#_wpm_gtin_code',
                '#_hwp_product_gtin'
            ];
            
            // Set readonly attribute and add visual styling
            $.each(readonlyFields, function(index, selector) {
                $(selector).prop('readonly', true).css({
                    'background-color': '#f0f0f1',
                    'cursor': 'not-allowed'
                });
            });
            
            // Disable stock status and manage stock checkboxes
            $('#_manage_stock, #_stock_status').prop('disabled', true);
            
            // Enable editing button click handler
            $('#enable-editing-btn').on('click', function() {
                $.each(readonlyFields, function(index, selector) {
                    $(selector).prop('readonly', false).css({
                        'background-color': '#fff',
                        'cursor': 'text'
                    });
                });
                $('#_manage_stock, #_stock_status').prop('disabled', false);
                $(this).parent().html('<p style="color: #2271b1;"><strong>✓ Editing enabled.</strong> You can now modify all fields.</p>');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Prefill title for translation (not needed, just placeholder)
     */
    public function prefill_translation_title($title, $post) {
        return $title;
    }
    
    /**
     * Prefill content for translation (not needed, just placeholder)
     */
    public function prefill_translation_data($content, $post) {
        return $content;
    }
    
    /**
     * Fill empty fields from translations on every update
     * This runs BEFORE WooCommerce processing to ensure empty fields get populated
     * 
     * @param int $post_id Post ID being saved
     * @param WP_Post $post Post object
     * @param bool $update Whether this is an update or new post
     */
    public function fill_empty_fields_from_translation($post_id, $post, $update) {
        // Prevent infinite loops
        if ($this->syncing) {
            return;
        }
        
        // Skip auto-drafts and revisions
        if ($post->post_status === 'auto-draft' || wp_is_post_revision($post_id)) {
            return;
        }
        
        if (!function_exists('pll_get_post_translations')) {
            return;
        }
        
        // Get translations
        $translations = pll_get_post_translations($post_id);
        
        // Skip if no translations exist yet
        if (empty($translations) || count($translations) <= 1) {
            return;
        }
        
        // Find a translation with data to use as source
        $source_id = null;
        
        // First, try to get source from URL parameters (when creating new translation)
        if (isset($_GET['from_post']) && isset($_GET['new_lang'])) {
            $source_id = intval($_GET['from_post']);
        }
        
        // If no URL parameter, find ANY translation with complete data
        if (!$source_id) {
            foreach ($translations as $lang => $trans_id) {
                if ($trans_id === $post_id) {
                    continue;
                }
                
                // Check if this translation has price data
                $trans_price = get_post_meta($trans_id, '_regular_price', true);
                
                if (!empty($trans_price)) {
                    $source_id = $trans_id;
                    break;
                }
            }
        }
        
        // If we found a source translation, fill any empty fields
        if ($source_id && $source_id !== $post_id) {
            $this->syncing = true;
            
            // Only auto-fill if this is a new translation being created (has URL params)
            // Don't auto-fill on regular updates where user might be entering data
            $is_new_translation = isset($_GET['from_post']) && isset($_GET['new_lang']);
            
            if ($is_new_translation) {
                // Copy all meta fields for new translations
                foreach ($this->sync_fields as $meta_key) {
                    $current_value = get_post_meta($post_id, $meta_key, true);
                    
                    // If field is empty, copy from source
                    if ($current_value === '' || $current_value === false || $current_value === null) {
                        $source_value = get_post_meta($source_id, $meta_key, true);
                        if ($source_value !== '' && $source_value !== false) {
                            update_post_meta($post_id, $meta_key, $source_value);
                        }
                    }
                }
            }
            
            // Copy featured image if not set
            if (!has_post_thumbnail($post_id)) {
                $thumbnail_id = get_post_thumbnail_id($source_id);
                if ($thumbnail_id) {
                    set_post_thumbnail($post_id, $thumbnail_id);
                }
            }
            
            // Copy product attributes if not set
            $current_attributes = get_post_meta($post_id, '_product_attributes', true);
            if (empty($current_attributes)) {
                $attributes = get_post_meta($source_id, '_product_attributes', true);
                if ($attributes) {
                    update_post_meta($post_id, '_product_attributes', $attributes);
                }
            }
            
            // Don't update lookup table here - it will be done after WooCommerce processing
            
            $this->syncing = false;
        }
    }
    
    /**
     * Add admin menu for bulk sync
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=product',
            'Polylang Product Sync',
            'Sync Translations',
            'manage_options',
            'polylang-wc-sync',
            function() {
                polylang_wc_sync_render_admin_page();
            }
        );
    }
    
    /**
     * Render admin page for bulk sync
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>Polylang WooCommerce Product Sync</h1>
            <p>Synchronize existing products and their translations. This will sync prices, stock, SKU, images, and all other data.</p>
            
            <div class="card">
                <h2>Bulk Sync Unsynced Products</h2>
                <p>This will find products with translations that haven't been synced yet and sync their data. Products that have already been synced automatically on save are excluded.</p>
                <div class="card-body">
                    <button id="start-bulk-sync" class="button button-primary button-large">
                        <span class="dashicons dashicons-update"></span> Start Bulk Sync
                    </button>
                    
                    <div id="sync-progress" style="display:none; margin-top: 20px;">
                        <p><strong>Syncing products...</strong></p>
                        <progress id="sync-progress-bar" value="0" max="100" style="width: 100%; height: 30px;"></progress>
                        <p id="sync-status">Preparing...</p>
                        <div id="sync-results" style="margin-top: 10px; max-height: 300px; overflow-y: auto; background: #f0f0f1; padding: 10px; border-radius: 4px;"></div>
                    </div>
                </div>
                <div class="card-footer">
                    <p><em>Note: Depending on the number of products, this process may take some time. Please do not close the browser window until the sync is complete.</em></p>
                </div>
            </div>
            
            <style>
                .card { 
                    max-width: 800px; 
                    padding: 20px; 
                    margin-top: 20px; 
                }
                #sync-results div {
                    padding: 5px;
                    border-bottom: 1px solid #ddd;
                }
                #sync-results div.success {
                    color: #46b450;
                }
                #sync-results div.error {
                    color: #dc3232;
                }
            </style>
            
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                let products = [];
                let currentIndex = 0;
                
                $('#start-bulk-sync').on('click', function() {
                    if (!confirm('This will sync all products with their translations. Continue?')) {
                        return;
                    }
                    
                    $(this).prop('disabled', true);
                    $('#sync-progress').show();
                    $('#sync-results').html('');
                    
                    // Get all products with translations
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'polylang_wc_get_products',
                            nonce: '<?php echo wp_create_nonce('polylang_wc_sync'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                products = response.data.products;
                                if (products.length === 0) {
                                    var message = response.data.message || 'No products with translations found.';
                                    $('.card-footer').append('<div class="notice notice-success" style="margin-top: 20px;"><p><strong>✓ ' + message + '</strong></p></div>');
                                    $('#start-bulk-sync').prop('disabled', false);
                                    $('.card-body').hide();
                                    return;
                                }
                                
                                $('#sync-status').text('Found ' + products.length + ' products. Starting sync...');
                                $('#sync-progress-bar').attr('max', products.length);
                                syncNextProduct();
                            } else {
                                alert('Error: ' + response.data.message);
                                $('#start-bulk-sync').prop('disabled', false);
                            }
                        },
                        error: function() {
                            alert('Failed to get products list.');
                            $('#start-bulk-sync').prop('disabled', false);
                        }
                    });
                });
                
                function syncNextProduct() {
                    if (currentIndex >= products.length) {
                        $('#sync-status').html('<strong style="color: #46b450;">✓ Sync completed! ' + products.length + ' products synced.</strong>');
                        $('#start-bulk-sync').prop('disabled', false);
                        currentIndex = 0;
                        products = [];
                        return;
                    }
                    
                    let product = products[currentIndex];
                    $('#sync-status').text('Syncing ' + (currentIndex + 1) + ' of ' + products.length + ': ' + product.title);
                    $('#sync-progress-bar').val(currentIndex + 1);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'polylang_wc_sync_product',
                            nonce: '<?php echo wp_create_nonce('polylang_wc_sync'); ?>',
                            product_id: product.id
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#sync-results').prepend('<div class="success">✓ ' + product.title + ' (ID: ' + product.id + ')</div>');
                            } else {
                                $('#sync-results').prepend('<div class="error">✗ ' + product.title + ': ' + response.data.message + '</div>');
                            }
                            currentIndex++;
                            syncNextProduct();
                        },
                        error: function() {
                            $('#sync-results').prepend('<div class="error">✗ ' + product.title + ': AJAX error</div>');
                            currentIndex++;
                            syncNextProduct();
                        }
                    });
                }
            });
            </script>
        </div>
        <?php
    }
    
    /**
     * AJAX: Get all products with translations that haven't been synced yet
     */
    public function ajax_get_products() {
        check_ajax_referer('polylang_wc_sync', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        global $wpdb;
        
        // Get all products that have translations
        $products = $wpdb->get_results("
            SELECT DISTINCT p.ID, p.post_title
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
            WHERE p.post_type = 'product'
            AND p.post_status = 'publish'
            AND tt.taxonomy = 'language'
            ORDER BY p.post_title
        ");
        
        if (empty($products)) {
            wp_send_json_error(['message' => 'No products found']);
        }
        
        $product_list = [];
        foreach ($products as $product) {
            // Check if this product has translations
            $translations = pll_get_post_translations($product->ID);
            if (count($translations) > 1) {
                // Check if this product has NOT been synced yet
                $sync_timestamp = get_post_meta($product->ID, '_polylang_wc_sync_timestamp', true);
                
                // Only include products that haven't been synced
                if (empty($sync_timestamp)) {
                    $product_list[] = [
                        'id' => $product->ID,
                        'title' => $product->post_title
                    ];
                }
            }
        }
        
        if (empty($product_list)) {
            wp_send_json_success(['products' => [], 'message' => 'All products with translations have already been synced! No unsynced products found.']);
        } else {
            wp_send_json_success(['products' => $product_list]);
        }
    }
    
    /**
     * AJAX: Sync a single product
     */
    public function ajax_sync_product() {
        check_ajax_referer('polylang_wc_sync', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        $product_id = intval($_POST['product_id']);
        
        if (!$product_id) {
            wp_send_json_error(['message' => 'Invalid product ID']);
        }
        
        // Verify it's a product
        if (get_post_type($product_id) !== 'product') {
            wp_send_json_error(['message' => 'Not a product']);
        }
        
        // Trigger the sync
        $this->sync_product_data($product_id);
        
        wp_send_json_success(['message' => 'Product synced successfully']);
    }
    
    /**
     * AJAX: Get sync conflicts from database
     */
    public function ajax_get_conflicts() {
        check_ajax_referer('polylang_wc_sync', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'polylang_wc_sync_conflicts';
        
        // Get recent conflicts (last 100)
        $conflicts = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY timestamp DESC LIMIT 100",
            ARRAY_A
        );
        
        wp_send_json_success(['conflicts' => $conflicts ?: []]);
    }
}
