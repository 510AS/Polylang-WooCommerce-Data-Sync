=== Polylang WooCommerce Data Sync ===
Contributors: Ahmed Shehata
Tags: polylang, woocommerce, synchronization, multilingual, e-commerce
Requires at least: 5.8
Requires PHP: 7.4
Tested up to: 6.4
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Synchronizes critical WooCommerce product data from default language to translations when using Polylang free version.

== Description ==

Polylang WooCommerce Data Sync automatically synchronizes critical WooCommerce product data (SKU, prices, stock quantities, images, and attributes) across all language translations. This plugin ensures consistency across your multilingual e-commerce store without manual intervention.

**Key Features:**
- Bi-directional data synchronization across languages
- Syncs prices, stock, SKU, and product images
- Conflict detection for simultaneous edits
- Automatic attribute synchronization
- Support for variable products
- Admin panel for monitoring sync conflicts
- Full compatibility with Polylang free version

**Requirements:**
- WordPress 5.8 or higher
- PHP 7.4 or higher
- Polylang (free or pro)
- WooCommerce 3.0 or higher

== Installation ==

1. Upload the `polylang-wc-sync` folder to `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Ensure both Polylang and WooCommerce are activated
4. Navigate to WooCommerce > Sync Conflicts to monitor synchronization

== Usage ==

Once activated, the plugin automatically synchronizes product data when you:
- Update product prices in any language
- Modify product stock quantities
- Change product SKU
- Upload or change product images
- Update product attributes

You can view synchronization conflicts in **WooCommerce > Sync Conflicts** admin page.

== Changelog ==

= 1.0.0 =
* Initial release
* Bi-directional synchronization support
* Conflict detection system
* Admin dashboard integration

== Support ==

For issues, questions, or feature requests, please visit:
https://github.com/510AS/Polylang-WooCommerce-Data-Sync

== Author ==

Ahmed Shehata (WordPress & Laravel Developer)
LinkedIn: https://www.linkedin.com/in/ahmed-shehata-0a171820b/
