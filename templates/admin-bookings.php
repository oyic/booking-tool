<?php
use LM\Booking\Admin\BookingsList;


$bookingsList = new BookingsList();
$bookings = $bookingsList->getBookings();
$stats = $bookingsList->getBookingStats();
$packages = $bookingsList->getAvailablePackages();
$deliveryOptions = $bookingsList->getAvailableDeliveryOptions();

// Helper function to get selected package index
function getSelectedPackageIndex($serviceName) {
    $settings = get_option('lm_booking_settings', []);
    $services = $settings['services'] ?? [];
    
    foreach ($services as $index => $service) {
        if ($service['label'] === $serviceName) {
            return $index;
        }
    }
    return 0; // Default to first package if not found
}

// Handle individual booking actions
if (isset($_GET['action']) && isset($_GET['booking_id'])) {
    $action = sanitize_text_field($_GET['action']);
    $booking_id = intval($_GET['booking_id']);
    
    if (wp_verify_nonce($_GET['_wpnonce'], 'lm_booking_action_' . $booking_id)) {
        switch ($action) {
            case 'view':
                $bookingsList->showBookingDetails($booking_id);
                return;
            case 'complete':
                update_post_meta($booking_id, '_lm_booking_status', 'completed');
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         __('Booking marked as completed.', 'lm-booking') . '</p></div>';
                });
                break;
            case 'delete':
                wp_delete_post($booking_id, true);
                add_action('admin_notices', function() {
                    echo '<div class="notice notice-success is-dismissible"><p>' . 
                         __('Booking deleted.', 'lm-booking') . '</p></div>';
                });
                break;
        }
    }
}
?>

<div class="wrap">
    <h1><?php esc_html_e('Bookings', 'lm-booking'); ?></h1>
    
    
    
    <?php if (empty($bookings)): ?>
    <div class="notice notice-info">
        <p>
            <strong><?php esc_html_e('No bookings found.', 'lm-booking'); ?></strong><br>
            <?php esc_html_e('Bookings will appear here after customers submit the booking form. Make sure the form is properly configured and accessible to customers.', 'lm-booking'); ?>
        </p>
    </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="lm-stats-cards">
        <div class="lm-stat-card">
            <h3><?php esc_html_e('Total Bookings', 'lm-booking'); ?></h3>
            <div class="lm-stat-number"><?php echo esc_html($stats['total']); ?></div>
        </div>
        <div class="lm-stat-card">
            <h3><?php esc_html_e('Total Revenue', 'lm-booking'); ?></h3>
            <div class="lm-stat-number">€<?php echo esc_html(number_format($stats['revenue'], 2)); ?></div>
        </div>
        <div class="lm-stat-card">
            <h3><?php esc_html_e('Pending', 'lm-booking'); ?></h3>
            <div class="lm-stat-number"><?php echo esc_html($stats['status_counts']['pending']); ?></div>
        </div>
        <div class="lm-stat-card">
            <h3><?php esc_html_e('In Progress', 'lm-booking'); ?></h3>
            <div class="lm-stat-number"><?php echo esc_html($stats['status_counts']['in_progress']); ?></div>
        </div>
        <div class="lm-stat-card">
            <h3><?php esc_html_e('Completed', 'lm-booking'); ?></h3>
            <div class="lm-stat-number"><?php echo esc_html($stats['status_counts']['completed']); ?></div>
        </div>
    </div>

    <!-- Advanced Filters -->
    <div class="lm-filters">
        <form method="get" action="" id="lm-filters-form">
            <input type="hidden" name="page" value="lm-booking">
            
            <div class="lm-filter-section">
                <h3><?php esc_html_e('Advanced Filters', 'lm-booking'); ?></h3>
                
                <div class="lm-filter-row">
                    <div class="lm-filter-group">
                        <label for="status"><?php esc_html_e('Status:', 'lm-booking'); ?></label>
                        <select name="status" id="status">
                            <option value=""><?php esc_html_e('All Statuses', 'lm-booking'); ?></option>
                            <option value="pending" <?php selected($_GET['status'] ?? '', 'pending'); ?>><?php esc_html_e('Pending', 'lm-booking'); ?></option>
                            <option value="in_progress" <?php selected($_GET['status'] ?? '', 'in_progress'); ?>><?php esc_html_e('In Progress', 'lm-booking'); ?></option>
                            <option value="completed" <?php selected($_GET['status'] ?? '', 'completed'); ?>><?php esc_html_e('Completed', 'lm-booking'); ?></option>
                            <option value="cancelled" <?php selected($_GET['status'] ?? '', 'cancelled'); ?>><?php esc_html_e('Cancelled', 'lm-booking'); ?></option>
                        </select>
                    </div>
                    
                    <div class="lm-filter-group">
                        <label for="package"><?php esc_html_e('Service:', 'lm-booking'); ?></label>
                        <select name="package" id="package">
                            <option value=""><?php esc_html_e('All Services', 'lm-booking'); ?></option>
                            <?php foreach ($packages as $package): ?>
                                <option value="<?php echo esc_attr($package); ?>" <?php selected($_GET['package'] ?? '', $package); ?>><?php echo esc_html($package); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="lm-filter-group delivery-group">
                        <label for="delivery"><?php esc_html_e('Delivery Urgency:', 'lm-booking'); ?></label>
                        <select name="delivery" id="delivery">
                            <option value=""><?php esc_html_e('All Delivery Options', 'lm-booking'); ?></option>
                            <?php foreach ($deliveryOptions as $key => $label): ?>
                                <option value="<?php echo esc_attr($key); ?>" <?php selected($_GET['delivery'] ?? '', $key); ?>><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="lm-filter-row">
                    <div class="lm-filter-group">
                        <label for="date_from"><?php esc_html_e('Date From:', 'lm-booking'); ?></label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo esc_attr($_GET['date_from'] ?? ''); ?>">
                        <div class="lm-date-error" id="date_from_error"></div>
                    </div>
                    
                    <div class="lm-filter-group">
                        <label for="date_to"><?php esc_html_e('Date To:', 'lm-booking'); ?></label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo esc_attr($_GET['date_to'] ?? ''); ?>">
                        <div class="lm-date-error" id="date_to_error"></div>
                    </div>
                    
                    <div class="lm-filter-group search-group">
                        <label for="s"><?php esc_html_e('Search:', 'lm-booking'); ?></label>
                        <input type="text" name="s" id="s" value="<?php echo esc_attr($_GET['s'] ?? ''); ?>" placeholder="<?php esc_attr_e('Search by name, email, or service...', 'lm-booking'); ?>">
                    </div>
                </div>
                
                <div class="lm-filter-actions">
                    <input type="submit" class="button button-primary" value="<?php esc_attr_e('Apply Filters', 'lm-booking'); ?>">
                    <a href="<?php echo admin_url('admin.php?page=lm-booking'); ?>" class="button"><?php esc_html_e('Clear All', 'lm-booking'); ?></a>
                    <button type="button" class="button" id="lm-refresh-bookings" title="<?php esc_attr_e('Refresh Bookings List', 'lm-booking'); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <title><?php esc_html_e('Refresh', 'lm-booking'); ?></title>
                            <polyline points="23,4 23,10 17,10"></polyline>
                            <polyline points="1,20 1,14 7,14"></polyline>
                            <path d="M20.49,9A9,9,0,0,0,5.64,5.64L1,10m22,4L18.36,18.36A9,9,0,0,1,3.51,15"></path>
                        </svg>
                        <?php esc_html_e('Refresh', 'lm-booking'); ?>
                    </button>
                    
                    <div class="lm-export-buttons">
                        <span class="lm-export-label"><?php esc_html_e('Export:', 'lm-booking'); ?></span>
                        <button type="button" class="button button-secondary lm-export-btn" data-format="csv" disabled>
                            <span class="lm-export-text"><?php esc_html_e('CSV', 'lm-booking'); ?></span>
                            <span class="lm-selection-count" style="display: none;"> (<span class="count">0</span> selected)</span>
                        </button>
                        <button type="button" class="button button-secondary lm-export-btn" data-format="xls" disabled>
                            <span class="lm-export-text"><?php esc_html_e('XLS', 'lm-booking'); ?></span>
                            <span class="lm-selection-count" style="display: none;"> (<span class="count">0</span> selected)</span>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Bulk Actions -->
    <form method="post" action="" id="lm-bulk-actions-form">
        <?php wp_nonce_field('lm_booking_bulk_action'); ?>
        
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="lm_booking_bulk_action" id="bulk-action-selector-top">
                    <option value=""><?php esc_html_e('Bulk Actions', 'lm-booking'); ?></option>
                    <option value="mark_completed"><?php esc_html_e('Mark as Completed', 'lm-booking'); ?></option>
                    <option value="mark_in_progress"><?php esc_html_e('Mark as In Progress', 'lm-booking'); ?></option>
                    <option value="delete"><?php esc_html_e('Delete', 'lm-booking'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'lm-booking'); ?>">
            </div>
        </div>

        <!-- Status Legend -->
        <div class="lm-status-legend">
            <div class="lm-legend-items">
                <div class="lm-legend-item">
                    <span class="lm-status-circle status-pending"></span>
                    <span class="lm-legend-text"><?php esc_html_e('Pending', 'lm-booking'); ?></span>
                </div>
                <div class="lm-legend-item">
                    <span class="lm-status-circle status-in-progress"></span>
                    <span class="lm-legend-text"><?php esc_html_e('In Progress', 'lm-booking'); ?></span>
                </div>
                <div class="lm-legend-item">
                    <span class="lm-status-circle status-completed"></span>
                    <span class="lm-legend-text"><?php esc_html_e('Completed', 'lm-booking'); ?></span>
                </div>
                <div class="lm-legend-item">
                    <span class="lm-status-circle status-cancelled"></span>
                    <span class="lm-legend-text"><?php esc_html_e('Cancelled', 'lm-booking'); ?></span>
                </div>
            </div>
        </div>

        <!-- Bookings Table -->
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cb-select-all">
                    </td>
                    <th class="manage-column sortable <?php echo ($_GET['orderby'] ?? '') === 'customer_name' ? 'sorted ' . ($_GET['order'] ?? 'asc') : ''; ?>">
                        <a href="<?php echo admin_url('admin.php?page=lm-booking&orderby=customer_name&order=' . (($_GET['orderby'] ?? '') === 'customer_name' && ($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc')); ?>">
                            <?php esc_html_e('Customer', 'lm-booking'); ?>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th class="manage-column"><?php esc_html_e('Service', 'lm-booking'); ?></th>
                    <th class="manage-column"><?php esc_html_e('Words/Pages', 'lm-booking'); ?></th>
                    <th class="manage-column"><?php esc_html_e('Extras', 'lm-booking'); ?></th>
                    <th class="manage-column"><?php esc_html_e('Delivery', 'lm-booking'); ?></th>
                    <th class="manage-column sortable <?php echo ($_GET['orderby'] ?? '') === 'total' ? 'sorted ' . ($_GET['order'] ?? 'asc') : ''; ?>">
                        <a href="<?php echo admin_url('admin.php?page=lm-booking&orderby=total&order=' . (($_GET['orderby'] ?? '') === 'total' && ($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc')); ?>">
                            <?php esc_html_e('Total', 'lm-booking'); ?>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th class="manage-column"><?php esc_html_e('Status', 'lm-booking'); ?></th>
                    <th class="manage-column sortable <?php echo ($_GET['orderby'] ?? '') === 'date' ? 'sorted ' . ($_GET['order'] ?? 'asc') : ''; ?>">
                        <a href="<?php echo admin_url('admin.php?page=lm-booking&orderby=date&order=' . (($_GET['orderby'] ?? '') === 'date' && ($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc')); ?>">
                            <?php esc_html_e('Date', 'lm-booking'); ?>
                            <span class="sorting-indicators">
                                <span class="sorting-indicator asc" aria-hidden="true"></span>
                                <span class="sorting-indicator desc" aria-hidden="true"></span>
                            </span>
                        </a>
                    </th>
                    <th class="manage-column"><?php esc_html_e('Actions', 'lm-booking'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($bookings)): ?>
                <tr>
                    <td colspan="9" class="no-items">
                        <?php esc_html_e('No bookings found.', 'lm-booking'); ?>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($bookings as $booking): ?>
                        <?php 
                        $meta = $bookingsList->getBookingMeta($booking->ID);
                        ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="booking_ids[]" value="<?php echo esc_attr($booking->ID); ?>">
                            </th>
                            <td>
                                <strong><?php echo esc_html($meta['customer_name']); ?></strong><br>
                                <small><?php echo esc_html($meta['customer_email']); ?></small>
                            </td>
                            <td class="lm-service-cell">
                                <?php 
                                // Single service - show badge
                                $service_class = strtolower(str_replace([' ', '-'], ['-', '-'], $meta['service']));
                                ?>
                                <span class="lm-service-badge <?php echo esc_attr($service_class); ?>">
                                    <?php echo esc_html($meta['service']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo esc_html($meta['words']); ?> words<br>
                                <small><?php echo esc_html($meta['norm_pages']); ?> norm pages</small>
                            </td>
                            <td class="lm-extras-cell">
                                <?php 
                                // Debug: Log what we're getting
                                error_log("LM Admin Template: Raw extras for booking {$booking->ID}: " . $meta['extras']);
                                
                                // Check if this booking has multiple extras
                                $extras = json_decode($meta['extras'], true);
                                error_log("LM Admin Template: Decoded extras for booking {$booking->ID}: " . json_encode($extras));
                                
                                if (is_array($extras) && count($extras) > 0) {
                                    if (count($extras) > 1) {
                                        // Multiple extras - show dropdown
                                        ?>
                                        <div class="lm-extras-dropdown">
                                            <button class="lm-extras-toggle" type="button">
                                                <span class="lm-extras-count"><?php echo count($extras); ?> Extras</span>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                    <polyline points="6,9 12,15 18,9"></polyline>
                                                </svg>
                                            </button>
                                            <div class="lm-extras-menu">
                                                <?php foreach ($extras as $extra): 
                                                    // Check if this extra is actually inclusive for the selected package
                                                    $includedPackages = $extra['included_packages'] ?? [];
                                                    $selectedPackageIndex = getSelectedPackageIndex($meta['service']);
                                                    
                                                    // Convert included_packages to integers to ensure proper comparison
                                                    $includedPackages = array_map('intval', $includedPackages);
                                                    $isInclusive = in_array($selectedPackageIndex, $includedPackages);
                                                ?>
                                                    <?php if ($isInclusive): ?>
                                                        <span class="lm-extra-badge" style="background-color: #fff3cd; color: #856404; border-color: #ffc107;">
                                                            <?php echo esc_html($extra['label']); ?> (inkl.)
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="lm-extra-badge">
                                                            <?php echo esc_html($extra['label']); ?> (+€<?php echo esc_html($extra['price']); ?>)
                                                        </span>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <?php
                                    } else {
                                        // Single extra - show badge
                                        $extra = $extras[0];
                                        
                                        // Check if this extra is actually inclusive for the selected package
                                        $includedPackages = $extra['included_packages'] ?? [];
                                        $selectedPackageIndex = getSelectedPackageIndex($meta['service']);
                                        
                                        // Convert included_packages to integers to ensure proper comparison
                                        $includedPackages = array_map('intval', $includedPackages);
                                        $isInclusive = in_array($selectedPackageIndex, $includedPackages);
                                        ?>
                                        <?php if ($isInclusive): ?>
                                            <span class="lm-extra-badge" style="background-color: #fff3cd; color: #856404; border-color: #ffc107;">
                                                <?php echo esc_html($extra['label']); ?> (inkl.)
                                            </span>
                                        <?php else: ?>
                                            <span class="lm-extra-badge">
                                                <?php echo esc_html($extra['label']); ?> (+€<?php echo esc_html($extra['price']); ?>)
                                            </span>
                                        <?php endif; ?>
                                        <?php
                                    }
                                } else {
                                    // No extras
                                    echo '<span class="lm-no-extras">No extras</span>';
                                }
                                ?>
                            </td>
                            <td class="lm-delivery-cell">
                                <div class="lm-delivery-info">
                                    <span class="lm-delivery-type"><?php echo esc_html($meta['delivery']); ?></span>
                                    <span class="lm-delivery-date"><?php echo esc_html($meta['delivery_date_formatted']); ?></span>
                                </div>
                            </td>
                            <td><strong><?php echo '€' . number_format($meta['total'], 2, ',', '.'); ?></strong></td>
                            <td class="lm-status-cell">
                                <div class="lm-status-indicator" data-status="<?php echo esc_attr($meta['status']); ?>" title="<?php echo esc_attr(ucfirst($meta['status'])); ?>">
                                    <span class="lm-status-circle status-<?php echo esc_attr($meta['status']); ?>"></span>
                                </div>
                            </td>
                            <td class="lm-date-cell">
                                <div class="lm-date-info">
                                    <span class="lm-date-day"><?php echo esc_html(date('M j', strtotime($booking->post_date))); ?></span>
                                    <span class="lm-date-time"><?php echo esc_html(date('H:i', strtotime($booking->post_date))); ?></span>
                                </div>
                            </td>
                            <td>
                                <div class="lm-action-buttons">
                                    <button type="button" class="lm-action-btn lm-view-btn <?php echo $meta['status'] === 'completed' ? 'disabled' : ''; ?>" data-booking-id="<?php echo esc_attr($booking->ID); ?>" title="<?php esc_attr_e('View Details', 'lm-booking'); ?>" <?php echo $meta['status'] === 'completed' ? 'disabled' : ''; ?>>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <title><?php esc_html_e('View Details', 'lm-booking'); ?></title>
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                            <circle cx="12" cy="12" r="3"></circle>
                                        </svg>
                                    </button>
                                    
                                    <?php if ($meta['status'] === 'pending'): ?>
                                        <button type="button" class="lm-action-btn lm-progress-btn" data-booking-id="<?php echo esc_attr($booking->ID); ?>" title="<?php esc_attr_e('Mark as In Progress', 'lm-booking'); ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <title><?php esc_html_e('Mark as In Progress', 'lm-booking'); ?></title>
                                                <circle cx="12" cy="12" r="10"></circle>
                                                <polyline points="12,6 12,12 16,14"></polyline>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($meta['status'] !== 'completed'): ?>
                                        <button type="button" class="lm-action-btn lm-complete-btn" data-booking-id="<?php echo esc_attr($booking->ID); ?>" title="<?php esc_attr_e('Mark as Completed', 'lm-booking'); ?>">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <title><?php esc_html_e('Mark as Completed', 'lm-booking'); ?></title>
                                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                <polyline points="22,4 12,14.01 9,11.01"></polyline>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="lm-action-btn lm-delete-btn" data-booking-id="<?php echo esc_attr($booking->ID); ?>" title="<?php esc_attr_e('Delete Booking', 'lm-booking'); ?>">
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <title><?php esc_html_e('Delete Booking', 'lm-booking'); ?></title>
                                            <polyline points="3,6 5,6 21,6"></polyline>
                                            <path d="M19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1,2-2h4a2,2 0 0,1,2,2v2"></path>
                                            <line x1="10" y1="11" x2="10" y2="17"></line>
                                            <line x1="14" y1="11" x2="14" y2="17"></line>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </form>
</div>

<!-- Dialog Overlay -->
<div class="lm-dialog-overlay" id="lm-dialog-overlay">
    <div class="lm-dialog" id="lm-dialog">
        <div class="lm-dialog-header">
            <h3 class="lm-dialog-title" id="lm-dialog-title"><?php esc_html_e('Action Required', 'lm-booking'); ?></h3>
            <button type="button" class="lm-dialog-close" id="lm-dialog-close">&times;</button>
        </div>
        <div class="lm-dialog-content">
            <div class="lm-dialog-message" id="lm-dialog-message">
                <?php esc_html_e('This function is still under development.', 'lm-booking'); ?>
            </div>
            <div class="lm-dialog-actions" id="lm-dialog-actions">
                <button type="button" class="lm-dialog-btn lm-dialog-btn-secondary" id="lm-dialog-cancel"><?php esc_html_e('Cancel', 'lm-booking'); ?></button>
                <button type="button" class="lm-dialog-btn lm-dialog-btn-primary" id="lm-dialog-confirm"><?php esc_html_e('Confirm', 'lm-booking'); ?></button>
            </div>
        </div>
    </div>
</div>

<style>
.lm-stats-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.lm-stat-card {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.lm-stat-card h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.lm-stat-number {
    font-size: 32px;
    font-weight: bold;
    color: #0073aa;
}

.lm-filters {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.lm-filter-section h3 {
    margin: 0 0 15px 0;
    color: #0073aa;
    font-size: 16px;
}

.lm-filter-row {
    display: flex;
    align-items: end;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.lm-filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 150px;
}

.lm-filter-group.search-group {
    min-width: 300px;
    flex: 1;
}

.lm-filter-group.delivery-group {
    min-width: 200px;
}

.lm-filter-group label {
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #666;
}

.lm-filter-group input,
.lm-filter-group select {
    padding: 8px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.lm-filter-group input.error,
.lm-filter-group select.error {
    border-color: #dc3232;
    background-color: #fff5f5;
}

.lm-date-error {
    color: #dc3232;
    font-size: 12px;
    margin-top: 5px;
    display: none;
}

/* Service badges for better visibility */
.lm-service-cell {
    position: relative;
}

.lm-service-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.lm-service-badge.premium-editing {
    background-color: #e3f2fd;
    color: #1976d2;
    border: 1px solid #bbdefb;
}

.lm-service-badge.mac-formatting {
    background-color: #f3e5f5;
    color: #7b1fa2;
    border: 1px solid #ce93d8;
}

.lm-service-badge.all-in-service {
    background-color: #e8f5e8;
    color: #388e3c;
    border: 1px solid #a5d6a7;
}

.lm-service-badge.basic-proofreading {
    background-color: #fff3e0;
    color: #f57c00;
    border: 1px solid #ffcc02;
}

.lm-service-badge.advanced-editing {
    background-color: #fce4ec;
    color: #c2185b;
    border: 1px solid #f8bbd9;
}

.lm-service-badge.scientific-review {
    background-color: #e0f2f1;
    color: #00695c;
    border: 1px solid #80cbc4;
}

.lm-service-badge.thesis-support {
    background-color: #f3e5f5;
    color: #7b1fa2;
    border: 1px solid #ce93d8;
}

.lm-service-badge.dissertation-review {
    background-color: #e8eaf6;
    color: #3f51b5;
    border: 1px solid #9fa8da;
}

/* Extras Column Styles */
.lm-extras-cell {
    position: relative;
}

.lm-extra-badge {
    display: inline-block;
    padding: 3px 6px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 500;
    background-color: #e8f5e8;
    color: #2e7d32;
    border: 1px solid #a5d6a7;
    margin: 1px;
}

.lm-no-extras {
    color: #666;
    font-size: 11px;
    font-style: italic;
}

/* Extras Dropdown Styles */
.lm-extras-dropdown {
    position: relative;
    display: inline-block;
}

.lm-extras-toggle {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 4px 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    font-weight: 600;
    color: #495057;
    transition: all 0.2s ease;
}

.lm-extras-toggle:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.lm-extras-toggle svg {
    transition: transform 0.2s ease;
}

.lm-extras-dropdown.active .lm-extras-toggle svg {
    transform: rotate(180deg);
}

.lm-extras-menu {
    position: absolute;
    top: 100%;
    left: 0;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    padding: 8px;
    min-width: 200px;
    z-index: 1000;
    display: none;
    flex-direction: column;
    gap: 4px;
}

.lm-extras-dropdown.active .lm-extras-menu {
    display: flex;
}

.lm-extras-menu .lm-extra-badge {
    margin: 0;
    font-size: 9px;
    padding: 2px 4px;
}

/* Date and Time Column Styles */
.lm-date-cell, .lm-delivery-cell {
    white-space: nowrap;
    min-width: 80px;
}

.lm-date-info, .lm-delivery-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
}

.lm-date-day, .lm-delivery-type {
    font-size: 11px;
    font-weight: 600;
    color: #333;
}

.lm-date-time, .lm-delivery-date {
    font-size: 10px;
    color: #666;
}

/* Status Circle Styles */
.lm-status-cell {
    text-align: center !important;
}

.lm-status-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
}

.lm-status-circle {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    display: inline-block;
    border: 2px solid transparent;
}

.lm-status-circle.status-pending {
    background-color: #ffc107;
    border-color: #ff9800;
}

.lm-status-circle.status-in-progress {
    background-color: #17a2b8;
    border-color: #138496;
}

.lm-status-circle.status-completed {
    background-color: #28a745;
    border-color: #1e7e34;
}

.lm-status-circle.status-cancelled {
    background-color: #dc3545;
    border-color: #c82333;
}


/* Status Legend Styles */
.lm-status-legend {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 8px 16px;
    margin-bottom: 16px;
    display: flex;
    justify-content: flex-end;
}

.lm-legend-items {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.lm-legend-item {
    display: flex;
    align-items: center;
    gap: 6px;
}

.lm-legend-item .lm-status-circle {
    width: 10px;
    height: 10px;
}

.lm-legend-text {
    font-size: 11px;
    color: #666;
}

/* Table Header Centering */
.wp-list-table th {
    text-align: center !important;
}

/* Status Column Centering */
.lm-status-cell {
    text-align: center !important;
}

/* Total Column Right Alignment */
.wp-list-table td:nth-child(7) {
    text-align: right !important;
}

/* Service Badge - Remove Background, Keep Text Color */
.lm-service-badge {
    background: none !important;
    border: none !important;
    padding: 0 !important;
    font-weight: 500;
}

.lm-service-badge.premium-editing { color: #2e7d32; }
.lm-service-badge.mac-formatting { color: #1976d2; }
.lm-service-badge.all-in-service { color: #7b1fa2; }
.lm-service-badge.basic-proofreading { color: #f57c00; }
.lm-service-badge.advanced-editing { color: #d32f2f; }
.lm-service-badge.scientific-review { color: #388e3c; }
.lm-service-badge.thesis-support { color: #5d4037; }
.lm-service-badge.dissertation-review { color: #e64a19; }

/* Table Column Width Distribution */
.wp-list-table {
    table-layout: fixed;
    width: 100%;
}

.wp-list-table th,
.wp-list-table td {
    width: auto;
}

/* Specific column width adjustments */
.wp-list-table th:nth-child(1),
.wp-list-table td:nth-child(1) { width: 3%; } /* Checkbox */

.wp-list-table th:nth-child(2),
.wp-list-table td:nth-child(2) { width: 15%; } /* Customer */

.wp-list-table th:nth-child(3),
.wp-list-table td:nth-child(3) { width: 12%; } /* Service */

.wp-list-table th:nth-child(4),
.wp-list-table td:nth-child(4) { width: 12%; } /* Words/Pages */

.wp-list-table th:nth-child(5),
.wp-list-table td:nth-child(5) { width: 12%; } /* Extras */

.wp-list-table th:nth-child(6),
.wp-list-table td:nth-child(6) { width: 10%; } /* Delivery */

.wp-list-table th:nth-child(7),
.wp-list-table td:nth-child(7) { width: 8%; } /* Total */

.wp-list-table th:nth-child(8),
.wp-list-table td:nth-child(8) { width: 6%; } /* Status */

.wp-list-table th:nth-child(9),
.wp-list-table td:nth-child(9) { width: 8%; } /* Date */

.wp-list-table th:nth-child(10),
.wp-list-table td:nth-child(10) { width: 14%; } /* Actions */

/* Sortable Column Styles */
.sortable a {
    text-decoration: none;
    color: inherit;
    display: flex;
    align-items: center;
    gap: 5px;
}

.sortable a:hover {
    color: #0073aa;
}

.sorting-indicators {
    position: relative;
    display: inline-block;
    width: 12px;
    height: 12px;
    margin-left: 5px;
}

.sorting-indicator {
    position: absolute;
    width: 0;
    height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    opacity: 0.3;
}

.sorting-indicator.asc {
    top: 1px;
    border-bottom: 5px solid #666;
}

.sorting-indicator.desc {
    bottom: 1px;
    border-top: 5px solid #666;
}

.sortable.sorted.asc .sorting-indicator.asc {
    opacity: 1;
    border-bottom-color: #0073aa;
}

.sortable.sorted.desc .sorting-indicator.desc {
    opacity: 1;
    border-top-color: #0073aa;
}

.sortable.sorted.asc .sorting-indicator.desc {
    opacity: 0.3;
    border-top-color: #666;
}

.sortable.sorted.desc .sorting-indicator.asc {
    opacity: 0.3;
    border-bottom-color: #666;
}

/* WordPress Admin Table Sorting Styles */
.wp-list-table .sortable a {
    position: relative;
    padding-right: 20px;
}

.wp-list-table .sortable .sorting-indicators {
    position: absolute;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    width: 12px;
    height: 12px;
}

.wp-list-table .sortable .sorting-indicator {
    position: absolute;
    width: 0;
    height: 0;
    border-left: 4px solid transparent;
    border-right: 4px solid transparent;
    opacity: 0.3;
}

.wp-list-table .sortable .sorting-indicator.asc {
    top: 1px;
    border-bottom: 5px solid #666;
}

.wp-list-table .sortable .sorting-indicator.desc {
    bottom: 1px;
    border-top: 5px solid #666;
}

.wp-list-table .sortable.sorted.asc .sorting-indicator.asc {
    opacity: 1;
    border-bottom-color: #0073aa;
}

.wp-list-table .sortable.sorted.desc .sorting-indicator.desc {
    opacity: 1;
    border-top-color: #0073aa;
}

.wp-list-table .sortable.sorted.asc .sorting-indicator.desc {
    opacity: 0.3;
    border-top-color: #666;
}

.wp-list-table .sortable.sorted.desc .sorting-indicator.asc {
    opacity: 0.3;
    border-bottom-color: #666;
}

.lm-filter-actions {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
    padding-top: 15px;
    border-top: 1px solid #eee;
}

.lm-export-buttons {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.lm-export-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.lm-export-btn:disabled:hover {
    background-color: #f1f1f1;
    border-color: #ddd;
}

.lm-selection-count {
    font-size: 12px;
    color: #666;
}

.lm-export-label {
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    color: #666;
}

#lm-refresh-bookings {
    display: flex;
    align-items: center;
    gap: 8px;
}

#lm-refresh-bookings svg {
    width: 16px;
    height: 16px;
}

#lm-refresh-bookings:hover svg {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

.status-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-in_progress {
    background-color: #17a2b8;
    border-color: #138496;
}

.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-cancelled {
    background: #f8d7da;
    color: #721c24;
}

.booking-details {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
    margin: 20px 0;
}

.booking-details h2 {
    margin-top: 0;
    color: #0073aa;
}

.booking-details .form-table th {
    width: 200px;
    font-weight: 600;
}

.booking-details .form-table td {
    padding: 10px 0;
}

.booking-details pre {
    background: #f1f1f1;
    padding: 15px;
    border-radius: 4px;
    overflow-x: auto;
    font-size: 12px;
}

.booking-section {
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.booking-section h3 {
    margin: 0 0 15px 0;
    color: #0073aa;
    font-size: 16px;
    border-bottom: 2px solid #0073aa;
    padding-bottom: 5px;
}

.file-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    font-weight: 600;
    font-size: 12px;
}

.file-status.success {
    background: #d4edda;
    color: #155724;
}

.file-status.error {
    background: #f8d7da;
    color: #721c24;
}

.total-amount {
    font-size: 18px;
    color: #0073aa;
}

.button-small {
    padding: 4px 8px;
    font-size: 11px;
    margin-left: 10px;
}

.lm-action-buttons {
    display: flex;
    gap: 8px;
    align-items: center;
}

.lm-action-btn {
    background: none;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
    color: #666;
}

.lm-action-btn:hover {
    background: #f5f5f5;
    border-color: #999;
    color: #333;
}

.lm-action-btn.disabled,
.lm-action-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
    background-color: #f8f9fa;
}

.lm-action-btn.disabled:hover,
.lm-action-btn:disabled:hover {
    background-color: #f8f9fa;
}

.lm-view-btn:hover {
    background: #e3f2fd;
    border-color: #2196f3;
    color: #1976d2;
}

.lm-complete-btn:hover {
    background: #e8f5e8;
    border-color: #4caf50;
    color: #388e3c;
}

.lm-delete-btn:hover {
    background: #ffebee;
    border-color: #f44336;
    color: #d32f2f;
}

.lm-action-btn svg {
    width: 16px;
    height: 16px;
}

/* Dialog Styles */
.lm-dialog-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: none;
}

.lm-dialog {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    min-width: 400px;
    max-width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    z-index: 10000;
}

.lm-dialog-header {
    padding: 20px 20px 0 20px;
    border-bottom: 1px solid #eee;
    margin-bottom: 20px;
}

.lm-dialog-title {
    margin: 0;
    font-size: 18px;
    color: #333;
}

.lm-dialog-close {
    position: absolute;
    top: 15px;
    right: 15px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lm-dialog-close:hover {
    color: #333;
}

.lm-dialog-content {
    padding: 0 20px 20px 20px;
}

.lm-dialog-message {
    margin-bottom: 20px;
    color: #666;
    line-height: 1.5;
}

.lm-dialog-actions {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid #eee;
}

.lm-dialog-btn {
    padding: 8px 16px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s ease;
}

.lm-dialog-btn-primary {
    background: #0073aa;
    color: white;
    border-color: #0073aa;
}

.lm-dialog-btn-primary:hover {
    background: #005a87;
    border-color: #005a87;
}

.lm-dialog-btn-secondary {
    background: white;
    color: #666;
    border-color: #ddd;
}

.lm-dialog-btn-secondary:hover {
    background: #f5f5f5;
    border-color: #999;
}

.lm-dialog-btn-danger {
    background: #dc3232;
    color: white;
    border-color: #dc3232;
}

.lm-dialog-btn-danger:hover {
    background: #a00;
    border-color: #a00;
}

/* Modal Content Styles */
.lm-booking-details-modal {
    max-height: 70vh;
    overflow-y: auto;
}

.lm-detail-section {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.lm-detail-section:last-child {
    border-bottom: none;
}

.lm-detail-section h4 {
    margin: 0 0 10px 0;
    color: #0073aa;
    font-size: 14px;
    font-weight: 600;
}

.lm-detail-table {
    width: 100%;
    border-collapse: collapse;
}

.lm-detail-table td {
    padding: 5px 0;
    vertical-align: top;
}

.lm-detail-table td:first-child {
    width: 120px;
    color: #666;
}

.lm-extras-list {
    margin: 0;
    padding-left: 20px;
}

.lm-extras-list li {
    margin-bottom: 5px;
}

.lm-breakdown {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    font-size: 12px;
    white-space: pre-wrap;
    max-height: 300px;
    overflow-y: auto;
    width: 100%;
    box-sizing: border-box;
}

.lm-breakdown-formatted {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
    border: 1px solid #ddd;
    font-size: 14px;
}

.lm-price-breakdown {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.lm-breakdown-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 6px 0;
    border-bottom: 1px solid #e0e0e0;
}

.lm-breakdown-row:last-child {
    border-bottom: none;
}

.lm-breakdown-label {
    font-weight: 500;
    color: #333;
    flex: 1;
}

.lm-breakdown-value {
    font-weight: 600;
    color: #0073aa;
    text-align: right;
    min-width: 80px;
}

.lm-breakdown-section {
    margin: 8px 0;
    padding-left: 15px;
    border-left: 3px solid #0073aa;
}

.lm-breakdown-indent {
    padding-left: 20px;
    font-size: 13px;
}

.lm-breakdown-subtotal {
    border-top: 2px solid #0073aa;
    margin-top: 10px;
    padding-top: 10px;
    font-weight: 600;
}

.lm-breakdown-total {
    border-top: 2px solid #28a745;
    margin-top: 10px;
    padding-top: 10px;
    font-weight: 700;
    font-size: 16px;
    background: #f0f8ff;
    border-radius: 4px;
    padding: 10px;
}

.lm-breakdown-total .lm-breakdown-value {
    color: #28a745;
    font-size: 18px;
}

/* Two Column Layout */
.lm-modal-columns {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.lm-modal-column {
    display: flex;
    flex-direction: column;
}

/* Full width sections */
.lm-full-width {
    grid-column: 1 / -1;
    width: 100%;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .lm-modal-columns {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}

.lm-complete-form {
    max-width: 500px;
}

.lm-file-upload input[type="file"] {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.lm-message-field label {
    display: block;
    font-weight: 600;
    margin-bottom: 5px;
    color: #333;
}

.lm-message-field textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    resize: vertical;
    font-family: inherit;
}

/* Loading animation for status updates */
.lm-status-circle.lm-loading {
    animation: lm-pulse 1s ease-in-out infinite;
    opacity: 0.6;
}

@keyframes lm-pulse {
    0% { opacity: 0.6; }
    50% { opacity: 1; }
    100% { opacity: 0.6; }
}

/* Notification styles */
.lm-notification {
    position: fixed;
    top: 32px;
    right: 20px;
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 12px 16px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    z-index: 10001;
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 300px;
    max-width: 500px;
}

.lm-notification-success {
    border-left: 4px solid #28a745;
}

.lm-notification-error {
    border-left: 4px solid #dc3545;
}

.lm-notification-info {
    border-left: 4px solid #0073aa;
}

.lm-notification-message {
    flex: 1;
    font-size: 14px;
    color: #333;
}

.lm-notification-close {
    background: none;
    border: none;
    font-size: 18px;
    color: #999;
    cursor: pointer;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.lm-notification-close:hover {
    color: #333;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Select all checkbox
    $('#cb-select-all').on('change', function() {
        $('input[name="booking_ids[]"]').prop('checked', $(this).prop('checked'));
    });
    
    // Individual checkbox change
    $('input[name="booking_ids[]"]').on('change', function() {
        var totalCheckboxes = $('input[name="booking_ids[]"]').length;
        var checkedCheckboxes = $('input[name="booking_ids[]"]:checked').length;
        
        $('#cb-select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
    });
    
    // Bulk action confirmation
    $('#lm-bulk-actions-form').on('submit', function(e) {
        var action = $('#bulk-action-selector-top').val();
        var checkedBoxes = $('input[name="booking_ids[]"]:checked').length;
        
        if (!action) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Please select a bulk action.', 'lm-booking')); ?>');
            return false;
        }
        
        if (checkedBoxes === 0) {
            e.preventDefault();
            alert('<?php echo esc_js(__('Please select at least one booking.', 'lm-booking')); ?>');
            return false;
        }
        
        if (action === 'delete') {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to delete the selected bookings?', 'lm-booking')); ?>')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Dialog functionality - Global variables for dialog state
    window.currentAction = null;
    window.currentBookingId = null;
    
    // Define ajaxurl for WordPress admin
    var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

    function showDialog(title, message, confirmText, confirmClass, action, bookingId) {
        console.log('LM Debug: showDialog called with action:', action, 'bookingId:', bookingId);
        
        $('#lm-dialog-title').text(title);
        $('#lm-dialog-message').text(message);
        $('#lm-dialog-confirm').text(confirmText).removeClass().addClass('lm-dialog-btn lm-dialog-btn-' + confirmClass);
        $('#lm-dialog-overlay').show();
        
        window.currentAction = action;
        window.currentBookingId = bookingId;
        
        console.log('LM Debug: showDialog - window.currentAction set to:', window.currentAction, 'window.currentBookingId set to:', window.currentBookingId);
    }

    function hideDialog() {
        $('#lm-dialog-overlay').hide();
        window.currentAction = null;
        window.currentBookingId = null;
    }

    // Action button handlers
    $('.lm-view-btn').on('click', function() {
        var bookingId = $(this).data('booking-id');
        showBookingDetailsModal(bookingId);
    });

    $('.lm-progress-btn').on('click', function() {
        var bookingId = $(this).data('booking-id');
        console.log('LM Debug: Progress button clicked, bookingId:', bookingId);
        showDialog(
            '<?php echo esc_js(__('Mark as In Progress', 'lm-booking')); ?>',
            '<?php echo esc_js(__('This will notify the customer that their booking is now being processed. Continue?', 'lm-booking')); ?>',
            '<?php echo esc_js(__('Mark as In Progress', 'lm-booking')); ?>',
            'primary',
            'progress',
            bookingId
        );
    });

    $('.lm-complete-btn').on('click', function() {
        var bookingId = $(this).data('booking-id');
        console.log('LM Debug: Complete button clicked, bookingId:', bookingId);
        showCompleteModal(bookingId);
    });

    $('.lm-delete-btn').on('click', function() {
        var bookingId = $(this).data('booking-id');
        showDialog(
            '<?php echo esc_js(__('Delete Booking', 'lm-booking')); ?>',
            '<?php echo esc_js(__('Are you sure you want to delete this booking? This action cannot be undone.', 'lm-booking')); ?>',
            '<?php echo esc_js(__('Delete', 'lm-booking')); ?>',
            'danger',
            'delete',
            bookingId
        );
    });

    // Dialog event handlers
    $('#lm-dialog-close, #lm-dialog-cancel').on('click', function() {
        hideDialog();
    });

    // Old handler removed - using $(document).on() handler below instead

    // Close dialog when clicking overlay
    $('#lm-dialog-overlay').on('click', function(e) {
        if (e.target === this) {
            hideDialog();
        }
    });

    // Close dialog with Escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#lm-dialog-overlay').is(':visible')) {
            hideDialog();
        }
    });

    // Date range validation
    function validateDateRange() {
        var dateFrom = $('#date_from').val();
        var dateTo = $('#date_to').val();
        var isValid = true;

        // Clear previous errors
        $('#date_from').removeClass('error');
        $('#date_to').removeClass('error');
        $('#date_from_error').hide();
        $('#date_to_error').hide();

        // Only validate if both dates are provided
        if (dateFrom && dateTo) {
            var fromDate = new Date(dateFrom);
            var toDate = new Date(dateTo);

            if (toDate < fromDate) {
                $('#date_to').addClass('error');
                $('#date_to_error').text('<?php echo esc_js(__('End date must be on or after start date.', 'lm-booking')); ?>').show();
                isValid = false;
            }
        }

        return isValid;
    }

    // Validate on date change
    $('#date_from, #date_to').on('change', function() {
        validateDateRange();
    });

    // Validate on form submission
    $('#lm-filters-form').on('submit', function(e) {
        if (!validateDateRange()) {
            e.preventDefault();
            return false;
        }
    });

    // Initial validation on page load
    validateDateRange();

    // Refresh bookings functionality
    $('#lm-refresh-bookings').on('click', function() {
        var $button = $(this);
        var $icon = $button.find('svg');
        
        // Disable button and show loading state
        $button.prop('disabled', true);
        $icon.addClass('spinning');
        
        // Reload the page to refresh data
        window.location.reload();
    });


    // Extras Dropdown functionality
    $('.lm-extras-toggle').on('click', function(e) {
        e.stopPropagation();
        var $dropdown = $(this).closest('.lm-extras-dropdown');
        var $menu = $dropdown.find('.lm-extras-menu');
        
        // Close other dropdowns
        $('.lm-extras-dropdown').not($dropdown).removeClass('active');
        
        // Toggle current dropdown
        $dropdown.toggleClass('active');
    });
    
    // Close dropdowns when clicking outside
    $(document).on('click', function() {
        $('.lm-extras-dropdown').removeClass('active');
    });

    // New modal functions
    function showBookingDetailsModal(bookingId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lm_get_booking_details',
                booking_id: bookingId,
                nonce: '<?php echo wp_create_nonce('lm_booking_details'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#lm-dialog-title').text('<?php echo esc_js(__('Booking Details', 'lm-booking')); ?>');
                    $('#lm-dialog-message').html(response.data.html);
                    $('#lm-dialog-actions').html('<button type="button" class="lm-dialog-btn lm-dialog-btn-primary" id="lm-dialog-confirm"><?php echo esc_js(__('Close', 'lm-booking')); ?></button>');
                    $('#lm-dialog-overlay').show();
                } else {
                    alert('Error loading booking details: ' + response.data.message);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error loading booking details.', 'lm-booking')); ?>');
            }
        });
    }

    function showCompleteModal(bookingId) {
        console.log('LM Debug: showCompleteModal called with bookingId:', bookingId);
        
        $('#lm-dialog-title').text('<?php echo esc_js(__('Mark as Completed', 'lm-booking')); ?>');
        $('#lm-dialog-message').html(`
            <div class="lm-complete-form">
                <p><?php echo esc_js(__('Upload the revised document and send it to the customer:', 'lm-booking')); ?></p>
                <div class="lm-file-upload">
                    <input type="file" id="lm-complete-file" accept=".doc,.docx,.pdf,.rtf,.odt" style="margin-bottom: 10px;">
                    <div class="lm-file-info" style="font-size: 12px; color: #666; margin-bottom: 15px;">
                        <?php echo esc_js(__('Accepted formats: DOC, DOCX, PDF, RTF, ODT (max 10MB)', 'lm-booking')); ?>
                    </div>
                </div>
                <div class="lm-message-field">
                    <label for="lm-complete-message"><?php echo esc_js(__('Message to customer (optional):', 'lm-booking')); ?></label>
                    <textarea id="lm-complete-message" rows="3" style="width: 100%; margin-top: 5px;" placeholder="<?php echo esc_js(__('Your document has been reviewed and is ready for download...', 'lm-booking')); ?>"></textarea>
                </div>
            </div>
        `);
        $('#lm-dialog-actions').html(`
            <button type="button" class="lm-dialog-btn lm-dialog-btn-secondary" id="lm-dialog-cancel"><?php echo esc_js(__('Cancel', 'lm-booking')); ?></button>
            <button type="button" class="lm-dialog-btn lm-dialog-btn-primary" id="lm-dialog-confirm"><?php echo esc_js(__('Complete & Send', 'lm-booking')); ?></button>
        `);
        $('#lm-dialog-overlay').show();
        
        window.currentAction = 'complete';
        window.currentBookingId = bookingId;
        
        console.log('LM Debug: showCompleteModal - currentAction set to:', window.currentAction, 'currentBookingId set to:', window.currentBookingId);
    }

    function updateBookingStatus(bookingId, status) {
        console.log('LM Debug: updateBookingStatus called with bookingId:', bookingId, 'status:', status);
        
        // Show loading state
        var $statusCell = $('tr').find(`[data-booking-id="${bookingId}"]`).closest('tr').find('.lm-status-cell');
        var $statusCircle = $statusCell.find('.lm-status-circle');
        var originalClass = $statusCircle.attr('class');
        
        console.log('LM Debug: Found status cell:', $statusCell.length, 'status circle:', $statusCircle.length);
        
        // Add loading animation
        $statusCircle.addClass('lm-loading');
        
        var ajaxData = {
            action: 'lm_update_booking_status',
            booking_id: bookingId,
            status: status,
            nonce: '<?php echo wp_create_nonce('lm_update_status'); ?>'
        };
        
        console.log('LM Debug: AJAX data:', ajaxData);
        console.log('LM Debug: ajaxurl:', ajaxurl);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: ajaxData,
            beforeSend: function() {
                console.log('LM Debug: AJAX request starting...');
            },
            success: function(response) {
                console.log('LM Debug: AJAX success response:', response);
                if (response.success) {
                    // Update status circle immediately
                    $statusCircle.removeClass('lm-loading');
                    $statusCircle.removeClass('status-pending status-in-progress status-completed status-cancelled');
                    $statusCircle.addClass('status-' + status);
                    
                    // Update status badge if visible
                    $statusCell.find('.status-badge').removeClass('status-pending status-in-progress status-completed status-cancelled').addClass('status-' + status).text(status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' '));
                    
                    // Update action buttons
                    updateActionButtons(bookingId, status);
                    
                    // Show success message
                    showNotification('<?php echo esc_js(__('Status updated successfully and customer notified!', 'lm-booking')); ?>', 'success');
                    
                    // Update stats if visible
                    updateStatsDisplay();
                } else {
                    $statusCircle.removeClass('lm-loading');
                    console.log('LM Debug: AJAX error response:', response);
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.log('LM Debug: AJAX error:', xhr, status, error);
                console.log('LM Debug: AJAX error details:', xhr.responseText);
                $statusCircle.removeClass('lm-loading');
                alert('<?php echo esc_js(__('Error updating booking status.', 'lm-booking')); ?>');
            }
        });
    }

    function completeBooking(bookingId, file, message) {
        console.log('LM Debug: completeBooking called with bookingId:', bookingId, 'file:', file, 'message:', message);
        
        var formData = new FormData();
        formData.append('action', 'lm_complete_booking');
        formData.append('booking_id', bookingId);
        formData.append('message', message);
        formData.append('nonce', '<?php echo wp_create_nonce('lm_complete_booking'); ?>');
        
        if (file) {
            formData.append('file', file);
            console.log('LM Debug: File added to FormData:', file.name, file.size);
        }

        console.log('LM Debug: FormData prepared, sending AJAX request');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('LM Debug: Complete booking AJAX success response:', response);
                if (response.success) {
                    location.reload(); // Refresh to show updated status
                } else {
                    console.log('LM Debug: Complete booking error response:', response);
                    alert('Error: ' + response.data.message);
                }
            },
            error: function(xhr, status, error) {
                console.log('LM Debug: Complete booking AJAX error:', xhr, status, error);
                alert('<?php echo esc_js(__('Error completing booking.', 'lm-booking')); ?>');
            }
        });
    }

    function deleteBooking(bookingId) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'lm_delete_booking',
                booking_id: bookingId,
                nonce: '<?php echo wp_create_nonce('lm_delete_booking'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload(); // Refresh to remove deleted booking
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Error deleting booking.', 'lm-booking')); ?>');
            }
        });
    }

    // Update dialog confirm handler for complete action
    $(document).on('click', '#lm-dialog-confirm', function() {
        console.log('LM Debug: Dialog confirm clicked, currentAction:', window.currentAction, 'currentBookingId:', window.currentBookingId);
        
        if (window.currentAction === 'complete') {
            var file = document.getElementById('lm-complete-file').files[0];
            var message = document.getElementById('lm-complete-message').value;
            
            console.log('LM Debug: Complete action - file:', file, 'message:', message, 'currentBookingId:', window.currentBookingId);
            
            if (!file) {
                alert('<?php echo esc_js(__('Please select a file to upload.', 'lm-booking')); ?>');
                return;
            }
            
            if (!window.currentBookingId) {
                alert('<?php echo esc_js(__('Error: Booking ID not found.', 'lm-booking')); ?>');
                return;
            }
            
            var bookingId = window.currentBookingId; // Store before hideDialog resets it
            hideDialog();
            completeBooking(bookingId, file, message);
        } else {
            console.log('LM Debug: Not complete action, currentAction:', window.currentAction);
            
            if (window.currentAction === 'progress') {
                var bookingId = window.currentBookingId; // Store before hideDialog resets it
                console.log('LM Debug: Calling updateBookingStatus with bookingId:', bookingId);
                hideDialog();
                updateBookingStatus(bookingId, 'in_progress');
            } else if (window.currentAction === 'delete') {
                var bookingId = window.currentBookingId; // Store before hideDialog resets it
                console.log('LM Debug: Calling deleteBooking with bookingId:', bookingId);
                hideDialog();
                deleteBooking(bookingId);
            } else {
                console.log('LM Debug: Unknown action:', window.currentAction);
                hideDialog();
            }
        }
    });

    // Helper functions
    function updateActionButtons(bookingId, status) {
        var $row = $('tr').find(`[data-booking-id="${bookingId}"]`).closest('tr');
        var $actionButtons = $row.find('.lm-action-buttons');
        
        // Remove existing action buttons
        $actionButtons.find('.lm-progress-btn, .lm-complete-btn').remove();
        
        // Disable all buttons except delete for completed bookings
        if (status === 'completed') {
            $actionButtons.find('.lm-view-btn').prop('disabled', true).addClass('disabled');
            return; // Don't add any new buttons for completed bookings
        }
        
        // Re-enable view button for non-completed bookings
        $actionButtons.find('.lm-view-btn').prop('disabled', false).removeClass('disabled');
        
        // Add appropriate buttons based on new status
        if (status === 'pending') {
            $actionButtons.prepend(`
                <button type="button" class="lm-action-btn lm-progress-btn" data-booking-id="${bookingId}" title="<?php esc_attr_e('Mark as In Progress', 'lm-booking'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <title><?php esc_html_e('Mark as In Progress', 'lm-booking'); ?></title>
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12,6 12,12 16,14"></polyline>
                    </svg>
                </button>
            `);
        }
        
        if (status !== 'completed') {
            $actionButtons.prepend(`
                <button type="button" class="lm-action-btn lm-complete-btn" data-booking-id="${bookingId}" title="<?php esc_attr_e('Mark as Completed', 'lm-booking'); ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <title><?php esc_html_e('Mark as Completed', 'lm-booking'); ?></title>
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22,4 12,14.01 9,11.01"></polyline>
                    </svg>
                </button>
            `);
        }
        
        // Re-bind event handlers
        $actionButtons.find('.lm-progress-btn').on('click', function() {
            var bookingId = $(this).data('booking-id');
            showDialog(
                '<?php echo esc_js(__('Mark as In Progress', 'lm-booking')); ?>',
                '<?php echo esc_js(__('This will notify the customer that their booking is now being processed. Continue?', 'lm-booking')); ?>',
                '<?php echo esc_js(__('Mark as In Progress', 'lm-booking')); ?>',
                'primary',
                'progress',
                bookingId
            );
        });
        
        $actionButtons.find('.lm-complete-btn').on('click', function() {
            var bookingId = $(this).data('booking-id');
            showCompleteModal(bookingId);
        });
    }

    function showNotification(message, type) {
        var $notification = $(`
            <div class="lm-notification lm-notification-${type}">
                <span class="lm-notification-message">${message}</span>
                <button type="button" class="lm-notification-close">&times;</button>
            </div>
        `);
        
        $('body').append($notification);
        
        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Manual close
        $notification.find('.lm-notification-close').on('click', function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    function updateStatsDisplay() {
        // Update the stats cards if they exist
        var $statsCards = $('.lm-stats-cards');
        if ($statsCards.length) {
            // Reload the page to get updated stats
            // In a more advanced implementation, you could update stats via AJAX
            setTimeout(function() {
                location.reload();
            }, 2000);
        }
    }

    // Export functionality
    function updateExportButtons() {
        var selectedCount = $('input[name="booking_ids[]"]:checked').length;
        var $exportButtons = $('.lm-export-btn');
        var $selectionCounts = $('.lm-selection-count');
        
        if (selectedCount > 0) {
            $exportButtons.prop('disabled', false);
            $selectionCounts.show();
            $('.lm-selection-count .count').text(selectedCount);
        } else {
            $exportButtons.prop('disabled', true);
            $selectionCounts.hide();
        }
    }

    // Handle select all checkbox
    $('#cb-select-all').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('input[name="booking_ids[]"]').prop('checked', isChecked);
        updateExportButtons();
    });

    // Handle individual checkbox changes
    $(document).on('change', 'input[name="booking_ids[]"]', function() {
        var totalCheckboxes = $('input[name="booking_ids[]"]').length;
        var checkedCheckboxes = $('input[name="booking_ids[]"]:checked').length;
        
        // Update select all checkbox
        if (checkedCheckboxes === 0) {
            $('#cb-select-all').prop('indeterminate', false).prop('checked', false);
        } else if (checkedCheckboxes === totalCheckboxes) {
            $('#cb-select-all').prop('indeterminate', false).prop('checked', true);
        } else {
            $('#cb-select-all').prop('indeterminate', true);
        }
        
        updateExportButtons();
    });

    // Handle export button clicks
    $('.lm-export-btn').on('click', function() {
        var format = $(this).data('format');
        var selectedIds = $('input[name="booking_ids[]"]:checked').map(function() {
            return $(this).val();
        }).get();
        
        if (selectedIds.length === 0) {
            alert('<?php echo esc_js(__('Please select at least one booking to export.', 'lm-booking')); ?>');
            return;
        }
        
        // Create form data
        var form = $('<form>', {
            method: 'POST',
            action: '<?php echo admin_url('admin.php?page=lm-booking'); ?>'
        });
        
        // Add export parameter
        form.append($('<input>', {
            type: 'hidden',
            name: 'export',
            value: format
        }));
        
        // Add selected booking IDs
        selectedIds.forEach(function(id) {
            form.append($('<input>', {
                type: 'hidden',
                name: 'selected_booking_ids[]',
                value: id
            }));
        });
        
        // Add page parameter
        form.append($('<input>', {
            type: 'hidden',
            name: 'page',
            value: 'lm-booking'
        }));
        
        // Add nonce for security
        form.append($('<input>', {
            type: 'hidden',
            name: 'export_nonce',
            value: '<?php echo wp_create_nonce('lm_export_bookings'); ?>'
        }));
        
        // Submit form
        $('body').append(form);
        form.submit();
        form.remove();
    });

    // Initialize export buttons on page load
    updateExportButtons();

});
</script>
