<?php
/**
 * Admin page for Polylang WooCommerce Data Sync
 * 
 * @package Polylang_WooCommerce_Sync
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render admin page for bulk sync
 */
function polylang_wc_sync_render_admin_page() {
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

        <div class="card">
            <h2>Sync Conflicts</h2>
            <p>View and manage synchronization conflicts detected during product updates.</p>
            <div class="card-body">
                <button id="view-conflicts" class="button button-secondary">
                    <span class="dashicons dashicons-warning"></span> View Conflicts
                </button>
                <div id="conflicts-list" style="display:none; margin-top: 20px;"></div>
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
            .conflict-item {
                padding: 10px;
                background: #fff;
                border-left: 4px solid #ff9900;
                margin-bottom: 10px;
                border-radius: 4px;
            }
            .conflict-item.resolved {
                border-left-color: #46b450;
                background: #f0f9f7;
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

            $('#view-conflicts').on('click', function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'polylang_wc_get_conflicts',
                        nonce: '<?php echo wp_create_nonce('polylang_wc_sync'); ?>'
                    },
                    success: function(response) {
                        if (response.success && response.data.conflicts.length > 0) {
                            var html = '<table class="widefat"><thead><tr><th>Time</th><th>Source</th><th>Target</th><th>Status</th></tr></thead><tbody>';
                            $.each(response.data.conflicts, function(index, conflict) {
                                var statusClass = conflict.status === 'resolved' ? 'resolved' : 'unresolved';
                                html += '<tr class="conflict-item ' + statusClass + '">' +
                                    '<td>' + conflict.timestamp + '</td>' +
                                    '<td>' + conflict.source_title + ' (#' + conflict.source_id + ' - ' + conflict.source_lang + ')</td>' +
                                    '<td>#' + conflict.target_id + ' - ' + conflict.target_lang + '</td>' +
                                    '<td><span style="color: #' + (conflict.status === 'resolved' ? '46b450' : 'ff9900') + ';">' + conflict.status.toUpperCase() + '</span></td>' +
                                    '</tr>';
                            });
                            html += '</tbody></table>';
                            $('#conflicts-list').html(html).show();
                        } else {
                            $('#conflicts-list').html('<p><strong>No conflicts found.</strong></p>').show();
                        }
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
